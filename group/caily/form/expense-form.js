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
        receipts: [],
        // 明細行（カスタムセット）
        lines: [],
        // 小計（税抜）
        subtotal_amount: 0,
        // 小計（消費税）
        subtotal_tax: 0,
        // 合計（税込）
        total_with_tax: 0,
        note: '',
        approver_user_ids: []
      },
      errors: {},
      modalTitle: this.mode === 'edit' ? '経費精算書 編集' : '経費精算書',
      submitting: false,
      uploading: false,
      uploadingReceipts: false,
      uploadProgress: 0,
      approvers: [],
      receiptsInputRef: 'expenseReceiptsInput',
      originalData: null
    };
  },
  created() {
    if (this.defaultData && Object.keys(this.defaultData).length > 0) {
      // defaultData から deep clone を作成して、親データと分離する
      const base = Object.assign({
        receipts: [],
        lines: [],
        subtotal_amount: 0,
        subtotal_tax: 0,
        total_with_tax: 0,
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
      const totals = this.computeTotals(raw.lines);
      raw.subtotal_amount = totals.subtotal_amount;
      raw.subtotal_tax = totals.subtotal_tax;
      raw.total_with_tax = totals.total_with_tax;
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
            subtotal_amount: 0,
            subtotal_tax: 0,
            total_with_tax: 0,
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
          const totals = this.computeTotals(raw.lines);
          raw.subtotal_amount = totals.subtotal_amount;
          raw.subtotal_tax = totals.subtotal_tax;
          raw.total_with_tax = totals.total_with_tax;
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
    computeTotals(lines) {
      const result = { subtotal_amount: 0, subtotal_tax: 0, total_with_tax: 0 };
      if (!Array.isArray(lines)) return result;
      lines.forEach(line => {
        if (!line) return;
        const na = line.amount != null ? Number(line.amount) : 0;
        const nt = line.tax != null ? Number(line.tax) : 0;
        if (!isNaN(na)) result.subtotal_amount += na;
        if (!isNaN(nt)) result.subtotal_tax += nt;
      });
      result.total_with_tax = result.subtotal_amount + result.subtotal_tax;
      return result;
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
        content: '',
        payee: '',
        amount: '',
        tax: '',
        reduced_tax: '', // 軽減税率: 任意の文字列/フラグ
        note: ''
      });
    },
    removeLine(index) {
      if (!Array.isArray(this.formData.lines)) return;
      this.formData.lines.splice(index, 1);
      const totals = this.computeTotals(this.formData.lines);
      this.formData.subtotal_amount = totals.subtotal_amount;
      this.formData.subtotal_tax = totals.subtotal_tax;
      this.formData.total_with_tax = totals.total_with_tax;
    },
    onLineAmountOrTaxChange() {
      const totals = this.computeTotals(this.formData.lines);
      this.formData.subtotal_amount = totals.subtotal_amount;
      this.formData.subtotal_tax = totals.subtotal_tax;
      this.formData.total_with_tax = totals.total_with_tax;
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
        const totals = this.computeTotals(this.formData.lines || []);
        this.formData.subtotal_amount = totals.subtotal_amount;
        this.formData.subtotal_tax = totals.subtotal_tax;
        this.formData.total_with_tax = totals.total_with_tax;
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
          const payload = Object.assign({ type: 'expense', status }, payloadBase);
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
              <small class="text-muted">画像・PDF。複数可。各20MB以下。</small>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">明細</label>
            <div class="col-sm-12">
              <table class="table table-sm align-middle mb-2 detail-table">
                <thead>
                  <tr>
                    <th style="width: 90px;">日付</th>
                    <th>内容</th>
                    <th style="width: 120px;">支払先</th>
                    <th style="width: 110px;">金額（税抜）</th>
                    <th style="width: 110px;">消費税</th>
                    <th style="width: 110px;">軽減税率</th>
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
                      <input type="text" class="form-control form-control-sm" v-model="line.content">
                    </td>
                    <td>
                      <input type="text" class="form-control form-control-sm" v-model="line.payee">
                    </td>
                    <td>
                      <input
                        type="number"
                        min="0"
                        class="form-control form-control-sm text-end"
                        v-model.number="line.amount"
                        @change="onLineAmountOrTaxChange"
                        @blur="onLineAmountOrTaxChange"
                      >
                    </td>
                    <td>
                      <input
                        type="number"
                        min="0"
                        class="form-control form-control-sm text-end"
                        v-model.number="line.tax"
                        @change="onLineAmountOrTaxChange"
                        @blur="onLineAmountOrTaxChange"
                      >
                    </td>
                    <td>
                      <input type="text" class="form-control form-control-sm" v-model="line.reduced_tax">
                    </td>
                    <td>
                      <input type="text" class="form-control form-control-sm" v-model="line.note">
                    </td>
                    <td class="text-center">
                      <button
                        type="button"
                        class="btn btn-sm btn-outline-danger"
                        @click="removeLine(idx)"
                      >
                        <i class="fa fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <button type="button" class="btn btn-sm btn-outline-primary" @click="addLine">
                    <i class="fa fa-plus me-1"></i> 行を追加
                  </button>
                </div>
                <div class="text-end">
                  <div>小計（金額（税抜））: ¥{{ (formData.subtotal_amount || 0).toLocaleString() }}</div>
                  <div>小計（消費税）: ¥{{ (formData.subtotal_tax || 0).toLocaleString() }}</div>
                  <div class="fw-bold">合計（税込）: ¥{{ (formData.total_with_tax || 0).toLocaleString() }}</div>
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
            <li>在宅勤務者が経費を立て替えた場合の清算時に提出してください。</li>
            <li>立替の場合は毎月20日までに提出してください。立替分の振込は給与支払日に合算して清算します。</li>
            <li>交通費以外は領収証も必ず添付してください。</li>
            <li>経費精算書、領収証原本（交通費以外）を総務課が受領した時点の勤怠締めにあわせて給与と合算して振込清算とします。</li>
          </ul>
        </div>
    </div>
  `
};
