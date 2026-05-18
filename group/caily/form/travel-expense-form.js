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
        // 交通費精算書ファイルは廃止、領収書明細のみ
        receipts: [],
        // 明細行
        lines: [],
        // 合計金額
        total_amount: 0,
        note: '',
        approver_user_ids: []
      },
      errors: {},
      modalTitle: this.mode === 'edit' ? '交通費精算書 編集' : '交通費精算書',
      submitting: false,
      uploading: false,
      uploadingReceipts: false,
      uploadProgress: 0,
      approvers: [],
      fileInputRef: 'travelExpenseFileInput',
      receiptsInputRef: 'travelExpenseReceiptsInput',
      originalData: null
    };
  },
  created() {
    if (this.defaultData && Object.keys(this.defaultData).length > 0) {
      // defaultData からの deep clone を作成して、親データと分離する
      const base = Object.assign({
        receipts: [],
        lines: [],
        total_amount: 0,
        note: '',
        approver_user_ids: []
      }, this.defaultData);
      const raw = JSON.parse(JSON.stringify(base));
      raw.receipts = Array.isArray(raw.receipts) ? raw.receipts : [];
      raw.lines = Array.isArray(raw.lines) ? raw.lines : [];
      raw.lines = raw.lines.map(line => ({
        ...line,
        date: this.normalizeDateValue(line && line.date)
      }));
      raw.total_amount = this.computeTotalAmount(raw.lines);
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
          const base = Object.assign({
            receipts: [],
            lines: [],
            total_amount: 0,
            note: '',
            approver_user_ids: []
          }, newVal);
          const raw = JSON.parse(JSON.stringify(base));
          raw.receipts = Array.isArray(raw.receipts) ? raw.receipts : [];
          raw.lines = Array.isArray(raw.lines) ? raw.lines : [];
          raw.lines = raw.lines.map(line => ({
            ...line,
            date: this.normalizeDateValue(line && line.date)
          }));
          raw.total_amount = this.computeTotalAmount(raw.lines);
          this.formData = raw;
          this.originalData = JSON.parse(JSON.stringify(this.formData));
        }
      },
      immediate: true,
      deep: false
    }
  },
  computed: {
    isDirty() {
      // 編集モードのときだけ、元データと現在の formData（明細行を含む）を比較して判定
      if (this.mode !== 'edit') return true;
      if (!this.originalData) return false;
      return JSON.stringify(this.formData) !== JSON.stringify(this.originalData);
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
        const v = line && line.amount != null ? Number(line.amount) : 0;
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
        date: '',
        route: '',
        from: '',
        to: '',
        amount: '',
        way: '片道',
        note: ''
      });
    },
    removeLine(index) {
      if (!Array.isArray(this.formData.lines)) return;
      this.formData.lines.splice(index, 1);
      this.formData.total_amount = this.computeTotalAmount(this.formData.lines);
    },
    onLineAmountChange() {
      this.formData.total_amount = this.computeTotalAmount(this.formData.lines);
    },
    async loadApprovers() {
      if (Array.isArray(this.approvers) && this.approvers.length > 0) {
        this.refreshApproverSelect();
        return;
      }
      if (typeof window !== 'undefined' && Array.isArray(window._travelExpenseApproversCache) && window._travelExpenseApproversCache.length > 0) {
        this.approvers = window._travelExpenseApproversCache;
        this.refreshApproverSelect();
        return;
      }
      try {
        const res = await axios.get('/api/index.php?model=member&method=list_request_approvers');
        const list = Array.isArray(res.data) ? res.data : [];
        this.approvers = list;
        if (typeof window !== 'undefined') {
          window._travelExpenseApproversCache = list;
        }
      } catch (e) {
        this.approvers = [];
      }
      this.refreshApproverSelect();
    },
    // 交通費精算書ファイル用の onFileSelect/uploadFile/clearFile は廃止
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
      if (!this.validateApproverUserIds(this.formData.approver_user_ids)) {
        this.errors.approver_user_ids = '承認者(指定)を選択してください。';
        valid = false;
      }
      return valid;
    },
    validateField(field) {
      const err = { ...this.errors };
      if (field === 'approver_user_ids') {
        if (!this.validateApproverUserIds(this.formData.approver_user_ids)) err.approver_user_ids = '承認者(指定)を選択してください。';
        else { delete err.approver_user_ids; }
      }
      this.errors = err;
    },
    async submit(status = 'pending') {
      if (!this.validate()) return;
      this.submitting = true;
      try {
        // 再計算してから送信
        this.formData.total_amount = this.computeTotalAmount(this.formData.lines || []);
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
          const payload = Object.assign({ type: 'travel_expense', status }, payloadBase);
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
              <small v-if="mode==='add'" class="text-muted">画像・PDF。複数可。各20MB以下。</small>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">明細</label>
            <div class="col-sm-12">
              <table class="table table-sm align-middle mb-2 detail-table">
                <thead>
                  <tr>
                    <th style="width: 90px;">日付</th>
                    <th>路線</th>
                    <th style="width: 80px;">乗車駅</th>
                    <th style="width: 80px;">下車駅</th>
                    <th style="width: 130px;">往復/片道</th>
                    <th style="width: 110px;">金額</th>
                    <th>備考</th>
                    <th style="width: 40px;"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(line, idx) in formData.lines" :key="idx">
                    <td>
                      <span v-if="mode === 'print'" class="request-print-text">{{ printDate(line.date) }}</span>
                      <input v-else type="date" class="form-control form-control-sm" v-model="line.date">
                    </td>
                    <td>
                      <input type="text" class="form-control form-control-sm" v-model="line.route">
                    </td>
                    <td>
                      <input type="text" class="form-control form-control-sm" v-model="line.from">
                    </td>
                    <td>
                      <input type="text" class="form-control form-control-sm" v-model="line.to">
                    </td>
                     <td>
                      <div class="btn-group btn-group-sm" role="group">
                        <button type="button"
                                class="btn"
                                :class="line.way === '往復' ? 'btn-primary' : 'btn-outline-secondary'"
                                @click="line.way = '往復'">往復</button>
                        <button type="button"
                                class="btn"
                                :class="line.way === '片道' ? 'btn-primary' : 'btn-outline-secondary'"
                                @click="line.way = '片道'">片道</button>
                      </div>
                    </td>
                    <td>
                      <input type="number" min="0" class="form-control form-control-sm text-end"
                             v-model.number="line.amount"
                             @change="onLineAmountChange"
                             @blur="onLineAmountChange">
                    </td>
                    <td>
                      <input type="text" class="form-control form-control-sm" v-model="line.note">
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
                <div class="fw-bold">
                  合計: ¥{{ (formData.total_amount || 0).toLocaleString() }}
                </div>
              </div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">備考</label>
            <div class="col-sm-9">
              <textarea class="form-control" v-model="formData.note" rows="2"></textarea>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">承認者(指定) <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <approver-select
                v-if="mode !== 'print'"
                ref="approverSelectRef"
                :options="approvers"
                :model-value="formData.approver_user_ids"
                @update:model-value="setApproverUserIds"
                @change="onApproverUserIdsChange"
                @blur="onApproverUserIdsBlur"
              ></approver-select>
              <span v-else class="request-print-text">{{ formatApproverUserIdsLabel(formData.approver_user_ids) }}</span>
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
            <li>できる限り領収証の添付をお願いします。税込み3万円以上の運賃の場合は必ず領収証を添付してください。</li>
          </ul>
        </div>
    </div>
  `
};
