import { formatUserDisplayName } from '/assets/js/user-display-name.js';
import { printFormatMixin } from './print-format.js';
import { approverMultiselectMixin } from './approver-multiselect.js';

export default {
  mixins: [printFormatMixin, approverMultiselectMixin],
  props: {
    defaultData: { type: Object, default: () => ({}) },
    mode: { type: String, default: 'add' }
  },
  data() {
    return {
      formData: {
        application_type: '新規',
        address: '',
        nearest_station: '',
        effective_from: '',
        one_month_commuter_pass: '',
        // 通勤経路明細
        lines: [],
        receipts: [],
        total_amount: 0,
        note: '',
        approver_user_ids: []
      },
      errors: {},
      modalTitle: this.mode === 'edit' ? '通勤手当申請書 編集' : '通勤手当申請書',
      submitting: false,
      uploading: false,
      uploadingReceipts: false,
      uploadProgress: 0,
      approvers: [],
      fileInputRef: 'commutingAllowanceFileInput',
      receiptsInputRef: 'commutingAllowanceReceiptsInput',
      originalData: null
    };
  },
  created() {
    if (this.defaultData && Object.keys(this.defaultData).length > 0) {
      const raw = Object.assign({
        application_type: '新規',
        address: '',
        nearest_station: '',
        effective_from: '',
        one_month_commuter_pass: '',
        lines: [],
        receipts: [],
        total_amount: 0,
        note: '',
        approver_user_ids: []
      }, this.defaultData);
      raw.effective_from = this.normalizeDateValue(raw.effective_from);
      raw.receipts = Array.isArray(raw.receipts) ? raw.receipts : [];
      raw.lines = Array.isArray(raw.lines) ? raw.lines : [];
      this.formData = raw;
      this.originalData = JSON.parse(JSON.stringify(this.formData));
    }
  },
  mounted() {
    this.loadApprovers();
    this.ensureAtLeastOneLine();
  },
  watch: {
    defaultData: {
      handler(newVal) {
        if (newVal && Object.keys(newVal).length > 0) {
          const raw = Object.assign({
            application_type: '新規',
            address: '',
            nearest_station: '',
            effective_from: '',
            one_month_commuter_pass: '',
            lines: [],
            receipts: [],
            total_amount: 0,
            note: '',
            approver_user_ids: []
          }, newVal);
          raw.effective_from = this.normalizeDateValue(raw.effective_from);
          raw.receipts = Array.isArray(raw.receipts) ? raw.receipts : [];
          raw.lines = Array.isArray(raw.lines) ? raw.lines : [];
          this.formData = raw;
          this.originalData = JSON.parse(JSON.stringify(this.formData));
        }
      },
      immediate: true,
      deep: true
    }
  },
  computed: {
    isDirty() {
      if (this.mode !== 'edit') return true;
      if (!this.originalData) return false;
      return JSON.stringify(this.formData) !== JSON.stringify(this.originalData);
    },
    oneMonthCommuterPassDisplay() {
      const v = this.formData.one_month_commuter_pass;
      if (v === null || v === undefined || v === '') return '-';
      return '¥' + Number(v).toLocaleString('ja-JP');
    }
  },
  methods: {
    formatUserDisplayName,
    normalizeDateValue(value) {
      if (value === null || value === undefined) return '';
      const str = String(value).trim();
      if (!str) return '';
      const m = str.match(/(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})/);
      if (!m) return '';
      return `${m[1]}-${String(m[2]).padStart(2, '0')}-${String(m[3]).padStart(2, '0')}`;
    },
    computeTotalAmount(lines) {
      if (!Array.isArray(lines)) return 0;
      return lines.reduce((sum, line) => {
        const v = line && line.one_way_fare != null ? Number(line.one_way_fare) : 0;
        return isNaN(v) ? sum : sum + v;
      }, 0);
    },
    ensureAtLeastOneLine() {
      if (!Array.isArray(this.formData.lines)) this.formData.lines = [];
      if (this.formData.lines.length === 0) {
        this.addLine();
      }
    },
    addLine() {
      if (!Array.isArray(this.formData.lines)) this.formData.lines = [];
      this.formData.lines.push({
        railway_company: '',
        line_name: '',
        section_from: '',
        section_to: '',
        one_way_fare: ''
      });
      this.formData.total_amount = this.computeTotalAmount(this.formData.lines);
    },
    removeLine(index) {
      if (!Array.isArray(this.formData.lines)) return;
      this.formData.lines.splice(index, 1);
      this.formData.total_amount = this.computeTotalAmount(this.formData.lines);
    },
    onLineFareChange() {
      this.formData.total_amount = this.computeTotalAmount(this.formData.lines);
    },
    async loadApprovers() {
      try {
        const res = await axios.get('/api/index.php?model=member&method=list_request_approvers');
        this.approvers = Array.isArray(res.data) ? res.data : [];
      } catch (e) {
        this.approvers = [];
      }
    },
    onFileSelect(event) {
      const file = event.target.files && event.target.files[0];
      if (!file) return;
      if (file.size > 20 * 1024 * 1024) {
        if (typeof showMessage === 'function') showMessage('ファイルサイズは20MB以下にしてください。', true);
        event.target.value = '';
        return;
      }
      this.uploadFile(file);
      event.target.value = '';
    },
    async uploadFile(file) {
      this.uploading = true;
      this.uploadProgress = 0;
      try {
        const formData = new FormData();
        formData.append('file', file);
        const xhr = new XMLHttpRequest();
        const url = '/api/index.php?model=request&method=uploadFormFile';
        const result = await new Promise((resolve, reject) => {
          xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) this.uploadProgress = Math.round((e.loaded / e.total) * 100);
          });
          xhr.addEventListener('load', () => {
            try {
              const res = JSON.parse(xhr.responseText);
              resolve(res);
            } catch (e) {
              resolve({ success: false, error: 'Invalid response' });
            }
          });
          xhr.addEventListener('error', () => reject(new Error('Network error')));
          xhr.open('POST', url);
          xhr.send(formData);
        });
        if (result && result.success) {
          this.formData.attachment = result.filename;
          this.formData.attachment_original = result.original_name || file.name;
          this.validateField('attachment');
        } else {
          if (typeof showMessage === 'function') showMessage(result && result.error ? result.error : 'アップロードに失敗しました。', true);
        }
      } catch (e) {
        if (typeof showMessage === 'function') showMessage('アップロードに失敗しました。', true);
      }
      this.uploading = false;
      this.uploadProgress = 0;
    },
    clearFile() {
      this.formData.attachment = '';
      this.formData.attachment_original = '';
      this.validateField('attachment');
    },
    async onReceiptsSelect(event) {
      const files = event.target.files ? Array.from(event.target.files) : [];
      event.target.value = '';
      if (!files.length) return;
      const maxSize = 20 * 1024 * 1024;
      const toUpload = files.filter(f => {
        if (f.size > maxSize) {
          if (typeof showMessage === 'function') showMessage(`ファイル「${f.name}」は20MB以下にしてください。`, true);
          return false;
        }
        return true;
      });
      if (!toUpload.length) return;
      this.uploadingReceipts = true;
      for (const file of toUpload) {
        await this.uploadReceiptFile(file);
      }
      this.uploadingReceipts = false;
    },
    async uploadReceiptFile(file) {
      try {
        const formData = new FormData();
        formData.append('file', file);
        const xhr = new XMLHttpRequest();
        const url = '/api/index.php?model=request&method=uploadFormFile';
        const result = await new Promise((resolve, reject) => {
          xhr.addEventListener('load', () => {
            try {
              resolve(JSON.parse(xhr.responseText));
            } catch (e) {
              resolve({ success: false, error: 'Invalid response' });
            }
          });
          xhr.addEventListener('error', () => reject(new Error('Network error')));
          xhr.open('POST', url);
          xhr.send(formData);
        });
        if (result && result.success) {
          if (!Array.isArray(this.formData.receipts)) this.formData.receipts = [];
          this.formData.receipts.push({
            filename: result.filename,
            original_name: result.original_name || file.name
          });
        } else {
          if (typeof showMessage === 'function') showMessage(result && result.error ? result.error : 'アップロードに失敗しました。', true);
        }
      } catch (e) {
        if (typeof showMessage === 'function') showMessage('アップロードに失敗しました。', true);
      }
    },
    removeReceipt(index) {
      this.formData.receipts.splice(index, 1);
    },
    receiptDownloadUrl(item) {
      if (!item || !item.filename) return '#';
      const requestId = this.mode === 'edit' && this.defaultData && this.defaultData.id ? this.defaultData.id : '';
      if (!requestId) return '#';
      return 'download.php?file=' + encodeURIComponent(item.filename) + '&request_id=' + encodeURIComponent(requestId);
    },
    validate() {
      this.errors = {};
      let valid = true;
      if (!this.formData.address || !String(this.formData.address).trim()) {
        this.errors.address = '住所を入力してください。';
        valid = false;
      }
      if (!this.formData.nearest_station || !String(this.formData.nearest_station).trim()) {
        this.errors.nearest_station = '最寄駅を入力してください。';
        valid = false;
      }
      if (!this.formData.effective_from || !String(this.formData.effective_from).trim()) {
        this.errors.effective_from = '適用開始日を選択してください。';
        valid = false;
      }
      if (!this.validateApproverUserIds(this.formData.approver_user_ids)) {
        this.errors.approver_user_ids = '承認者(指定)を選択してください。';
        valid = false;
      }
      return valid;
    },
    validateField(field) {
      const err = { ...this.errors };
      if (field === 'address') {
        if (!this.formData.address || !String(this.formData.address).trim()) err.address = '住所を入力してください。';
        else delete err.address;
      } else if (field === 'nearest_station') {
        if (!this.formData.nearest_station || !String(this.formData.nearest_station).trim()) err.nearest_station = '最寄駅を入力してください。';
        else delete err.nearest_station;
      } else if (field === 'effective_from') {
        if (!this.formData.effective_from || !String(this.formData.effective_from).trim()) err.effective_from = '適用開始日を選択してください。';
        else delete err.effective_from;
      } else if (field === 'approver_user_ids') {
        if (!this.validateApproverUserIds(this.formData.approver_user_ids)) err.approver_user_ids = '承認者(指定)を選択してください。';
        else { delete err.approver_user_ids; }
      }
      this.errors = err;
    },
    async submit(status = 'pending') {
      if (!this.validate()) return;
      this.submitting = true;
      try {
        const payloadBase = {
          data: this.formData,
          approver_user_id: this.encodeApproverUserIds(this.formData.approver_user_ids)
        };
        if (this.mode === 'edit') {
          const payload = Object.assign({ id: this.defaultData.id }, payloadBase);
          await axios.post('/api/index.php?model=request&method=edit', payload, {
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
          });
          this.$emit('submitted', this.formData);
          this.close();
        } else {
          const payload = Object.assign({ type: 'commuting_allowance', status }, payloadBase);
          await axios.post('/api/index.php?model=request&method=add', payload, {
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
          });
          if (status === 'draft' && typeof showMessage === 'function') showMessage('下書き保存しました。');
          this.$emit('submitted', this.formData);
          this.close();
        }
      } catch (e) {
        if (typeof showMessage === 'function') showMessage(this.mode === 'edit' ? '編集に失敗しました。' : '申請に失敗しました。', true);
      }
      this.submitting = false;
    },
    close() {
      this.$emit('close');
    },
    downloadUrl() {
      if (!this.formData.attachment) return '#';
      const requestId = this.mode === 'edit' && this.defaultData && this.defaultData.id ? this.defaultData.id : '';
      if (!requestId) return '#';
      return 'download.php?file=' + encodeURIComponent(this.formData.attachment) + '&request_id=' + encodeURIComponent(requestId);
    }
  },
  template: `
    <div class="position-relative">
      <div v-if="submitting" class="position-absolute top-0 start-0 end-0 bottom-0 d-flex align-items-center justify-content-center bg-white bg-opacity-75 rounded" style="z-index: 1050;">
        <div class="text-center">
          <div class="spinner-border text-primary mb-2" role="status" style="width: 2rem; height: 2rem;"></div>
          <div class="text-muted small">保存中...</div>
        </div>
      </div>
      <div class="modal-header">
        <h5 class="modal-title">{{ modalTitle }}</h5>
        <button type="button" class="btn-close" @click="close"></button>
      </div>
      <div class="modal-body">
        <form @submit.prevent="submit('pending')"><fieldset :disabled="mode === 'print'">
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">申請区分</label>
            <div class="col-sm-9 d-flex align-items-center flex-wrap gap-3">
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="application_type_new" value="新規" v-model="formData.application_type">
                <label class="form-check-label" for="application_type_new">新規</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="application_type_route" value="経路変更" v-model="formData.application_type">
                <label class="form-check-label" for="application_type_route">経路変更</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="application_type_fare" value="運賃改定" v-model="formData.application_type">
                <label class="form-check-label" for="application_type_fare">運賃改定</label>
              </div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">住所</label>
            <div class="col-sm-9">
              <input type="text" class="form-control" v-model="formData.address" @change="validateField('address')" @blur="validateField('address')">
              <div class="text-danger small mt-1" v-if="errors.address">{{ errors.address }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">最寄駅</label>
            <div class="col-sm-9">
              <input type="text" class="form-control" v-model="formData.nearest_station" @change="validateField('nearest_station')" @blur="validateField('nearest_station')">
              <div class="text-danger small mt-1" v-if="errors.nearest_station">{{ errors.nearest_station }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">適用開始日</label>
            <div class="col-sm-9" v-if="mode === 'print'">
              <span class="request-print-text">{{ formData.effective_from ? printDate(formData.effective_from) : '-' }}</span>
            </div>
            <div class="col-sm-9" v-else>
              <input type="date" class="form-control" v-model="formData.effective_from" @change="validateField('effective_from')" @blur="validateField('effective_from')">
              <div class="text-danger small mt-1" v-if="errors.effective_from">{{ errors.effective_from }}</div>
            </div>
          </div>
          
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">通勤交通費内訳</label>
            <div class="col-sm-12">
              <table class="table table-sm align-middle mb-2 detail-table">
                <thead>
                  <tr>
                    <th style="width: 140px;">鉄道会社名</th>
                    <th style="width: 140px;">路線名</th>
                    <th style="width: 120px;">利用区間(乗車駅)</th>
                    <th style="width: 120px;">利用区間(降車駅)</th>
                    <th style="width: 140px;">片道運賃</th>
                    <th style="width: 40px;"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(line, idx) in formData.lines" :key="idx">
                    <td>
                      <input type="text" class="form-control form-control-sm" v-model="line.railway_company">
                    </td>
                    <td>
                      <input type="text" class="form-control form-control-sm" v-model="line.line_name">
                    </td>
                    <td>
                      <input type="text" class="form-control form-control-sm" v-model="line.section_from" placeholder="乗車駅">
                    </td>
                    <td>
                      <input type="text" class="form-control form-control-sm" v-model="line.section_to" placeholder="降車駅">
                    </td>
                    <td>
                      <input type="number" min="0" class="form-control form-control-sm text-end"
                             v-model.number="line.one_way_fare"
                             @change="onLineFareChange"
                             @blur="onLineFareChange">
                    </td>
                    <td class="text-center">
                      <button type="button" class="btn btn-sm btn-outline-danger"
                              @click="removeLine(idx)"><i class="fa fa-trash"></i></button>
                    </td>
                  </tr>
                </tbody>
              </table>
              <div class="d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-sm btn-outline-primary" @click="addLine">
                  <i class="fa fa-plus me-1"></i> 行を追加
                </button>
                <div class="fw-bold text-end flex-grow-1">
                  合計片道運賃: ¥{{ (formData.total_amount || 0).toLocaleString() }}
                </div>
              </div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">１か月定期代<span class="text-muted ms-1">※月給制のみ</span></label>
            <div class="col-sm-9" v-if="mode === 'print'">
              <span class="request-print-text">{{ oneMonthCommuterPassDisplay }}</span>
            </div>
            <div class="col-sm-9" v-else>
              <div class="input-group">
                <span class="input-group-text">¥</span>
                <input
                  type="number"
                  min="0"
                  class="form-control"
                  v-model.number="formData.one_month_commuter_pass"
                  placeholder="１か月分の定期代">
              </div>
            </div>

            <div class="text-muted small mt-4" v-if="mode==='add'">
              ※支給方法
              <ul>
                <li>月給制の場合：1か月の定期代を支給</li>
                <li>時給制の場合：往復運賃×出勤日数分を支給」と追記してください。</li>
              </ul>
            </div>
          </div>
          <!--<div class="mb-3 row">
            <label class="col-sm-3 col-form-label">請求書・領収書等</label>
            <div class="col-sm-9">
              <input type="file" class="form-control mb-2" :ref="receiptsInputRef" accept=".pdf,.jpg,.jpeg,.png,.gif" multiple @change="onReceiptsSelect" :disabled="uploadingReceipts">
              <div v-if="uploadingReceipts" class="text-muted small">アップロード中...</div>
              <ul v-if="formData.receipts && formData.receipts.length" class="list-group list-group-flush mt-2">
                <li v-for="(item, index) in formData.receipts" :key="index" class="list-group-item d-flex align-items-center justify-content-between py-2">
                  <a v-if="mode==='edit' && defaultData && defaultData.id" :href="receiptDownloadUrl(item)" target="_blank" class="btn btn-sm btn-link p-0 text-start text-truncate">
                    <i class="fa fa-download me-1"></i>{{ item.original_name || item.filename }}
                  </a>
                  <span v-else class="text-truncate">{{ item.original_name || item.filename }}</span>
                  <button type="button" class="btn btn-sm btn-outline-danger ms-2" @click="removeReceipt(index)">削除</button>
                </li>
              </ul>
              <small class="text-muted">画像・PDF。複数可。各20MB以下。</small>

            </div>
          </div>-->
          
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">備考</label>
            <div class="col-sm-9">
              <textarea class="form-control" v-model="formData.note" rows="2"></textarea>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">承認者(指定) <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <select v-if="mode !== 'print'" class="form-select" multiple size="6" v-model="formData.approver_user_ids" @change="validateField('approver_user_ids')" @blur="validateField('approver_user_ids')">
                <option v-for="user in approvers" :key="user.userid" :value="user.userid">
                  {{ formatUserDisplayName(user) }} ({{ user.userid }})
                </option>
              </select>
              <span v-else class="request-print-text">{{ formatApproverUserIdsLabel(formData.approver_user_ids) }}</span>
              <div class="form-text text-muted" v-if="mode !== 'print'">Ctrl / Cmd を押しながらクリックで複数選択</div>
              <div class="text-danger small" v-if="errors.approver_user_ids">{{ errors.approver_user_ids }}</div>
            </div>
          </div>
        </fieldset>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" @click="close">キャンセル</button>
        <button v-if="mode==='add'" type="button" class="btn btn-outline-secondary" :disabled="submitting" @click="submit('draft')">下書き保存</button>
        <button v-if="mode==='add'" type="button" class="btn btn-primary" :disabled="submitting" @click="submit('pending')">申請</button>
        <button v-if="mode==='edit'" type="button" class="btn btn-primary" :disabled="submitting || !isDirty" @click="submit()">保存</button>
      </div>
      <div class="text-muted small" v-if="mode==='add'">
          <ul>
            <li>入社、転居、転勤等により通勤のための交通費が新たに発生、変更になった場合に提出してください。</li>
          </ul>
        </div>
    </div>
  `
};
