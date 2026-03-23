export default {
  props: {
    defaultData: { type: Object, default: () => ({}) },
    mode: { type: String, default: 'add' }
  },
  data() {
    return {
      formData: {
        destination: '',
        start_date: '',
        end_date: '',
        settlement_date: '',
        trip_type: '国内',
        per_diem: 3000,
        trip_days: '',
        trip_allowance: 0,
        advance_amount: '',
        lines: [],
        line_total: 0,
        net_total: 0,
        final_amount: 0,
        receipts: [],
        note: '',
        approver_user_id: ''
      },
      errors: {},
      modalTitle: this.mode === 'edit' ? '出張旅費精算書 編集' : '出張旅費精算書',
      submitting: false,
      uploading: false,
      uploadingReceipts: false,
      uploadProgress: 0,
      approvers: [],
      fileInputRef: 'tripExpenseFileInput',
      receiptsInputRef: 'tripExpenseReceiptsInput',
      originalData: null
    };
  },
  created() {
    if (this.defaultData && Object.keys(this.defaultData).length > 0) {
      const raw = Object.assign({
        destination: '',
        start_date: '',
        end_date: '',
        settlement_date: '',
        trip_type: '国内',
        receipts: [],
        note: '',
        approver_user_id: ''
      }, this.defaultData);
      raw.start_date = this.normalizeDateValue(raw.start_date);
      raw.end_date = this.normalizeDateValue(raw.end_date);
      raw.settlement_date = this.normalizeDateValue(raw.settlement_date);
      raw.receipts = Array.isArray(raw.receipts) ? raw.receipts : [];
      this.formData = raw;
      this.originalData = JSON.parse(JSON.stringify(this.formData));
    }
  },
  mounted() {
    this.loadApprovers();
  },
  watch: {
    defaultData: {
      handler(newVal) {
        if (newVal && Object.keys(newVal).length > 0) {
          const raw = Object.assign({
            destination: '',
            start_date: '',
            end_date: '',
            settlement_date: '',
            trip_type: '国内',
            per_diem: 3000,
            trip_days: '',
            trip_allowance: 0,
            advance_amount: '',
            lines: [],
            line_total: 0,
            net_total: 0,
            final_amount: 0,
            receipts: [],
            note: '',
            approver_user_id: ''
          }, newVal);
          raw.start_date = this.normalizeDateValue(raw.start_date);
          raw.end_date = this.normalizeDateValue(raw.end_date);
          raw.settlement_date = this.normalizeDateValue(raw.settlement_date);
          raw.receipts = Array.isArray(raw.receipts) ? raw.receipts : [];
          this.formData = raw;
          this.originalData = JSON.parse(JSON.stringify(this.formData));
        }
      },
      immediate: true,
      deep: true
    },
    'formData.start_date'() {
      this.updateTripDays();
      // 日付が変更されたタイミングでも期間の妥当性をチェック
      this.validateDates();
    },
    'formData.end_date'() {
      this.updateTripDays();
      // 終了日選択後に即時バリデーション
      this.validateDates();
    },
    'formData.per_diem'() {
      const per = Number(this.formData.per_diem || 0);
      const d = Number(this.formData.trip_days || 0);
      this.formData.trip_allowance =
        this.formData.trip_type === '海外' && !isNaN(per) && !isNaN(d) ? per * d : 0;
      this.updateTotalsFromLines();
    },
    'formData.trip_days'() {
      const per = Number(this.formData.per_diem || 0);
      const d = Number(this.formData.trip_days || 0);
      this.formData.trip_allowance =
        this.formData.trip_type === '海外' && !isNaN(per) && !isNaN(d) ? per * d : 0;
      this.updateTotalsFromLines();
    },
    'formData.trip_type'() {
      // 国内の場合は出張手当を 0 にする
      const per = Number(this.formData.per_diem || 0);
      const d = Number(this.formData.trip_days || 0);
      if (this.formData.trip_type === '海外' && !isNaN(per) && !isNaN(d)) {
        this.formData.trip_allowance = per * d;
      } else {
        this.formData.trip_allowance = 0;
      }
      this.updateTotalsFromLines();
    },
    'formData.advance_amount'() {
      this.updateTotalsFromLines();
    },
    'formData.lines': {
      handler() {
        this.updateTotalsFromLines();
      },
      deep: true
    }
  },
  computed: {
    isDirty() {
      if (this.mode !== 'edit') return true;
      if (!this.originalData) return false;
      return JSON.stringify(this.formData) !== JSON.stringify(this.originalData);
    }
  },
  methods: {
    normalizeDateValue(value) {
      if (value === null || value === undefined) return '';
      const str = String(value).trim();
      if (!str) return '';
      const m = str.match(/(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})/);
      if (!m) return '';
      return `${m[1]}-${String(m[2]).padStart(2, '0')}-${String(m[3]).padStart(2, '0')}`;
    },
    computeLineSum(line) {
      if (!line || typeof line !== 'object') return 0;
      const keys = ['transportation', 'accommodation', 'entertainment', 'meal', 'other'];
      return keys.reduce((sum, k) => {
        const v = Number(line[k] || 0);
        return isNaN(v) ? sum : sum + v;
      }, 0);
    },
    updateTotalsFromLines() {
      if (!Array.isArray(this.formData.lines)) {
        this.formData.lines = [];
      }
      // 各行の合計を更新
      this.formData.lines.forEach(line => {
        if (!line) return;
        const s = this.computeLineSum(line);
        line.total = s;
      });
      // 全行の合計
      const totalLines = this.formData.lines.reduce((sum, line) => {
        const v = Number(line && line.total != null ? line.total : 0);
        return isNaN(v) ? sum : sum + v;
      }, 0);
      this.formData.line_total = totalLines;
      const adv = Number(this.formData.advance_amount || 0);
      const net = totalLines - (isNaN(adv) ? 0 : adv);
      this.formData.net_total = net;
      const allowance = Number(this.formData.trip_allowance || 0);
      this.formData.final_amount = net + (isNaN(allowance) ? 0 : allowance);
    },
    updateTripDays() {
      if (!this.formData.start_date || !this.formData.end_date) {
        this.formData.trip_days = '';
        this.formData.trip_allowance = 0;
        this.updateTotalsFromLines();
        return;
      }
      const start = new Date(this.formData.start_date);
      const end = new Date(this.formData.end_date);
      if (isNaN(start) || isNaN(end) || end < start) {
        this.formData.trip_days = '';
        this.formData.trip_allowance = 0;
        this.updateTotalsFromLines();
        return;
      }
      // 開始日と終了日を含めた日数（土日も含む）
      const diffMs = end.getTime() - start.getTime();
      const days = Math.floor(diffMs / (1000 * 60 * 60 * 24)) + 1;
      this.formData.trip_days = days > 0 ? String(days) : '';
      const per = Number(this.formData.per_diem || 0);
      const d = Number(this.formData.trip_days || 0);
      this.formData.trip_allowance =
        this.formData.trip_type === '海外' && !isNaN(per) && !isNaN(d) ? per * d : 0;
      this.updateTotalsFromLines();
    },
    onPerDiemBlur() {
      const v = Number(this.formData.per_diem || 0);
      this.formData.per_diem = isNaN(v) || v < 0 ? 0 : v;
      this.updateTripDays();
    },
    validateDates() {
      const err = { ...this.errors };
      let valid = true;
      if (!this.formData.start_date) {
        err.start_date = '開始日を入力してください。';
        valid = false;
      } else {
        delete err.start_date;
      }
      if (!this.formData.end_date) {
        err.end_date = '終了日を入力してください。';
        valid = false;
      } else {
        delete err.end_date;
      }
      if (this.formData.start_date && this.formData.end_date) {
        const s = new Date(this.formData.start_date);
        const e = new Date(this.formData.end_date);
        if (!isNaN(s) && !isNaN(e) && e < s) {
          err.end_date = '終了日は開始日以降にしてください。';
          valid = false;
        }
      }
      this.errors = err;
      return valid;
    },
    async loadApprovers() {
      try {
        const res = await axios.get('/api/index.php?model=member&method=list_request_approvers');
        this.approvers = Array.isArray(res.data) ? res.data : [];
      } catch (e) {
        this.approvers = [];
      }
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
      // 出張先
      if (!this.formData.destination || !String(this.formData.destination).trim()) {
        this.errors.destination = '出張先を入力してください。';
        valid = false;
      }
      // 期間
      if (!this.validateDates()) {
        valid = false;
      }
      if (!this.formData.approver_user_id) {
        this.errors.approver_user_id = '承認者を選択してください。';
        valid = false;
      }
      return valid;
    },
    validateField(field) {
      const err = { ...this.errors };
      if (field === 'destination') {
        if (!this.formData.destination || !String(this.formData.destination).trim()) err.destination = '出張先を入力してください。';
        else { delete err.destination; }
      } else if (field === 'start_date' || field === 'end_date') {
        // 個別フィールド更新後に期間全体の妥当性をチェック
        this.validateDates();
        return;
      } else if (field === 'settlement_date') {
        // 任意項目: エラーは常にクリア
        delete err.settlement_date;
      } else if (field === 'approver_user_id') {
        if (!this.formData.approver_user_id) err.approver_user_id = '承認者を選択してください。';
        else { delete err.approver_user_id; }
      }
      this.errors = err;
    },
    async submit(status = 'pending') {
      if (!this.validate()) return;
      this.submitting = true;
      try {
        const payloadBase = {
          data: this.formData,
          approver_user_id: this.formData.approver_user_id || ''
        };
        if (this.mode === 'edit') {
          const payload = Object.assign({ id: this.defaultData.id }, payloadBase);
          await axios.post('/api/index.php?model=request&method=edit', payload, {
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
          });
          this.$emit('submitted', this.formData);
          this.close();
        } else {
          const payload = Object.assign({ type: 'trip_expense', status }, payloadBase);
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
        <form @submit.prevent="submit('pending')">
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">出張先 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <input type="text" class="form-control" v-model="formData.destination" @blur="validateField('destination')">
              <div class="text-danger small" v-if="errors.destination">{{ errors.destination }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">国内/海外</label>
            <div class="col-sm-9">
              <div role="group">
                <input type="radio" id="trip-expense-type-domestic" autocomplete="off" value="国内" v-model="formData.trip_type">
                <label class="ms-2" for="trip-expense-type-domestic">国内</label> &nbsp;
                <input type="radio" id="trip-expense-type-overseas" autocomplete="off" value="海外" v-model="formData.trip_type">
                <label class="ms-2" for="trip-expense-type-overseas">海外</label>
              </div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">期間 <span class="text-danger">*</span></label>
            <div class="col-sm-4">
              <input type="date" class="form-control" v-model="formData.start_date" @blur="validateField('start_date')">
              <div class="text-danger small" v-if="errors.start_date">{{ errors.start_date }}</div>
            </div>
            <div class="col-sm-1 text-center">〜</div>
            <div class="col-sm-4">
              <input type="date" class="form-control" v-model="formData.end_date" @blur="validateField('end_date')">
              <div class="text-danger small" v-if="errors.end_date">{{ errors.end_date }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">精算日</label>
            <div class="col-sm-4">
              <input type="date" class="form-control" v-model="formData.settlement_date" @blur="validateField('settlement_date')">
              <div class="text-danger small" v-if="errors.settlement_date">{{ errors.settlement_date }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">仮払金</label>
            <div class="col-sm-4">
              <div class="input-group input-group-sm">
                <input
                  type="number"
                  min="0"
                  class="form-control text-end"
                  v-model.number="formData.advance_amount"
                >
                <span class="input-group-text">円</span>
              </div>
            </div>
          </div>
          <div class="mb-3 row" v-if="formData.trip_type === '海外'">
            <label class="col-sm-3 col-form-label">日当・出張手当</label>
            <div class="col-sm-9">
              <div class="row g-2 align-items-center mb-1">
                <div class="col-auto">
                  <label class="col-form-label col-form-label-sm">日当</label>
                </div>
                <div class="col-3">
                  <input type="number" step="100" min="0" class="form-control form-control-sm text-end"
                         v-model.number="formData.per_diem"
                         @blur="onPerDiemBlur">
                </div>
                <div class="col-auto">
                  <span class="small">円</span>
                </div>
              </div>
              <div class="row g-2 align-items-center mb-1">
                <div class="col-auto">
                  <label class="col-form-label col-form-label-sm">日間</label>
                </div>
                <div class="col-3">
                  <input type="number" step="1" min="0" class="form-control form-control-sm text-end" v-model.number="formData.trip_days">
                </div>
              </div>
              <div class="row g-2 align-items-center">
                <div class="col-auto">
                  <label class="col-form-label col-form-label-sm">出張手当:</label>
                </div>
                <div class="col-4">
                  <div class="form-control-plaintext">
                    ¥{{ (formData.trip_allowance || 0).toLocaleString() }}
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">明細</label>
            <div class="col-sm-12">
              <table class="table table-sm align-middle mb-2 detail-table">
                <thead>
                  <tr>
                    <th style="width: 110px;">日付</th>
                    <th>項目</th>
                    <th style="width: 110px;">交通費</th>
                    <th style="width: 110px;">宿泊費</th>
                    <th style="width: 110px;">交際費</th>
                    <th style="width: 110px;">食費</th>
                    <th style="width: 110px;">その他</th>
                    <th style="width: 120px;">合計</th>
                    <th>備考</th>
                    <th style="width: 40px;"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(line, idx) in formData.lines" :key="idx">
                    <td>
                      <input type="date" class="form-control form-control-sm" v-model="line.date">
                    </td>
                    <td>
                      <input type="text" class="form-control form-control-sm" v-model="line.item">
                    </td>
                    <td>
                      <input type="number" min="0" class="form-control form-control-sm text-end"
                             v-model.number="line.transportation">
                    </td>
                    <td>
                      <input type="number" min="0" class="form-control form-control-sm text-end"
                             v-model.number="line.accommodation">
                    </td>
                    <td>
                      <input type="number" min="0" class="form-control form-control-sm text-end"
                             v-model.number="line.entertainment">
                    </td>
                    <td>
                      <input type="number" min="0" class="form-control form-control-sm text-end"
                             v-model.number="line.meal">
                    </td>
                    <td>
                      <input type="number" min="0" class="form-control form-control-sm text-end"
                             v-model.number="line.other">
                    </td>
                    <td class="text-end">
                      ¥{{ Number(line.total || 0).toLocaleString() }}
                    </td>
                    <td>
                      <input type="text" class="form-control form-control-sm" v-model="line.note">
                    </td>
                    <td class="text-center">
                      <button type="button" class="btn btn-sm btn-outline-danger"
                              @click="formData.lines.splice(idx, 1)">
                        <i class="fa fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <button type="button" class="btn btn-sm btn-outline-primary"
                          @click="formData.lines.push({ date: '', item: '', transportation: 0, accommodation: 0, entertainment: 0, meal: 0, other: 0, total: 0, note: '' })">
                    <i class="fa fa-plus me-1"></i> 行を追加
                  </button>
                </div>
                <div class="text-end">
                  <div>明細合計: ¥{{ Number(formData.line_total || 0).toLocaleString() }}</div>
                  <div>仮払金差引合計: ¥{{ Number(formData.net_total || 0).toLocaleString() }}</div>
                  <div class="fw-bold">精算額: ¥{{ Number(formData.final_amount || 0).toLocaleString() }}</div>
                </div>
              </div>
            </div>
          </div>
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
              <small class="text-muted">画像・PDF。複数可。各20MB以下。</small>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">備考</label>
            <div class="col-sm-9">
              <textarea class="form-control" v-model="formData.note" rows="2"></textarea>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">承認者 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <select class="form-select" v-model="formData.approver_user_id" @change="validateField('approver_user_id')" @blur="validateField('approver_user_id')">
                <option value="">指定なし</option>
                <option v-for="user in approvers" :key="user.userid" :value="user.userid">
                  {{ user.realname }} ({{ user.userid }})
                </option>
              </select>
              <div class="text-danger small" v-if="errors.approver_user_id">{{ errors.approver_user_id }}</div>
            </div>
          </div>
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
            <li>宿泊を伴う出張の際にかかった交通費、経費などを精算する際に提出してください。</li>
            <li>領収証も必ず添付してください。</li>
          </ul>
        </div>
    </div>
  `
};
