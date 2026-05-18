import { formatUserDisplayName } from '/assets/js/user-display-name.js';

export default {
  props: {
    defaultData: { type: Object, default: () => ({}) },
    mode: { type: String, default: 'add' }
  },
  data() {
    return {
      formData: {
        lines: [],
        total_amount: 0,
        reason: '',
        note: '',
        approver_user_id: ''
      },
      errors: {},
      modalTitle: this.mode === 'edit' ? '備品購入依頼書 編集' : '備品購入依頼書',
      submitting: false,
      approvers: [],
      originalData: null
    };
  },
  created() {
    if (this.defaultData && Object.keys(this.defaultData).length > 0) {
      this.formData = this.normalizeFormData(Object.assign({
        lines: [],
        total_amount: 0,
        reason: '',
        note: '',
        approver_user_id: ''
      }, this.defaultData));
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
          this.formData = this.normalizeFormData(Object.assign({
            lines: [],
            total_amount: 0,
            reason: '',
            note: '',
            approver_user_id: ''
          }, newVal));
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
    }
  },
  methods: {
    formatUserDisplayName,
    emptyLine() {
      return {
        manufacturer: '',
        product_code: '',
        product_name: '',
        quantity: '',
        unit_price: '',
        amount_with_tax: ''
      };
    },
    normalizeFormData(raw) {
      const data = { ...raw };
      data.lines = Array.isArray(data.lines) ? data.lines.map(line => ({
        manufacturer: line.manufacturer || '',
        product_code: line.product_code || '',
        product_name: line.product_name || '',
        quantity: line.quantity !== undefined && line.quantity !== null ? line.quantity : '',
        unit_price: line.unit_price !== undefined && line.unit_price !== null ? line.unit_price : '',
        amount_with_tax: line.amount_with_tax !== undefined && line.amount_with_tax !== null && line.amount_with_tax !== ''
          ? line.amount_with_tax
          : ''
      })) : [];
      if (data.lines.length === 0 && (data.item_name || data.category)) {
        data.lines.push({
          manufacturer: '',
          product_code: '',
          product_name: data.item_name || '',
          quantity: data.quantity !== undefined && data.quantity !== null ? data.quantity : '',
          unit_price: data.estimated_price !== undefined && data.estimated_price !== null ? data.estimated_price : '',
          amount_with_tax: ''
        });
      }
      data.total_amount = this.computeTotalAmount(data.lines);
      return data;
    },
    computeTotalAmount(lines) {
      if (!Array.isArray(lines)) return 0;
      return lines.reduce((sum, line) => sum + (Number(line.amount_with_tax) || 0), 0);
    },
    updateTotalAmount() {
      this.formData.total_amount = this.computeTotalAmount(this.formData.lines);
    },
    ensureAtLeastOneLine() {
      if (!Array.isArray(this.formData.lines)) this.formData.lines = [];
      if (this.formData.lines.length === 0) this.addLine();
    },
    addLine() {
      if (!Array.isArray(this.formData.lines)) this.formData.lines = [];
      this.formData.lines.push(this.emptyLine());
    },
    removeLine(index) {
      if (!Array.isArray(this.formData.lines)) return;
      this.formData.lines.splice(index, 1);
      if (this.formData.lines.length === 0) this.addLine();
      this.updateTotalAmount();
    },
    formatAmount(value) {
      const n = Number(value);
      if (isNaN(n)) return '0';
      return n.toLocaleString('ja-JP');
    },
    validateLine(line, rowNum) {
      if (!String(line.manufacturer || '').trim()) {
        return `明細${rowNum}行目: メーカーを入力してください。`;
      }
      if (!String(line.product_code || '').trim()) {
        return `明細${rowNum}行目: 商品コードを入力してください。`;
      }
      if (!String(line.product_name || '').trim()) {
        return `明細${rowNum}行目: 商品名を入力してください。`;
      }
      const q = line.quantity;
      if (q === '' || q === null || q === undefined || !/^\d+$/.test(String(q)) || parseInt(q, 10) < 1) {
        return `明細${rowNum}行目: 数量は1以上の整数を入力してください。`;
      }
      const p = line.unit_price;
      if (p === '' || p === null || p === undefined || isNaN(Number(p)) || Number(p) < 0) {
        return `明細${rowNum}行目: 単価を入力してください。`;
      }
      const a = line.amount_with_tax;
      if (a === '' || a === null || a === undefined || isNaN(Number(a)) || Number(a) < 0) {
        return `明細${rowNum}行目: 金額（税込み）を入力してください。`;
      }
      return null;
    },
    async loadApprovers() {
      try {
        const res = await axios.get('/api/index.php?model=member&method=list_request_approvers');
        this.approvers = Array.isArray(res.data) ? res.data : [];
      } catch (e) {
        this.approvers = [];
      }
    },
    validate() {
      this.errors = {};
      let valid = true;
      this.updateTotalAmount();
      const lines = this.formData.lines || [];
      if (lines.length === 0) {
        this.errors.lines = '購入品目を1件以上入力してください。';
        valid = false;
      } else {
        for (let i = 0; i < lines.length; i++) {
          const msg = this.validateLine(lines[i], i + 1);
          if (msg) {
            this.errors.lines = msg;
            valid = false;
            break;
          }
        }
      }
      if (!this.formData.reason || !String(this.formData.reason).trim()) {
        this.errors.reason = '事由・用途を入力してください。';
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
      if (field === 'reason') {
        if (!this.formData.reason || !String(this.formData.reason).trim()) err.reason = '事由・用途を入力してください。';
        else { delete err.reason; }
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
          const payload = Object.assign({ type: 'purchase', status }, payloadBase);
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
            <label class="col-sm-3 col-form-label">購入品目 <span class="text-danger">*</span></label>
            <div class="col-sm-12">
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-2">
                  <thead>
                    <tr>
                      <th style="min-width: 100px;">メーカー <span class="text-danger">*</span></th>
                      <th style="min-width: 100px;">商品コード <span class="text-danger">*</span></th>
                      <th style="min-width: 140px;">商品名 <span class="text-danger">*</span></th>
                      <th style="min-width: 70px;">数量 <span class="text-danger">*</span></th>
                      <th style="min-width: 90px;">単価 <span class="text-danger">*</span></th>
                      <th style="min-width: 110px;">金額（税込み） <span class="text-danger">*</span></th>
                      <th style="width: 40px;"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(line, idx) in formData.lines" :key="idx">
                      <td>
                        <input type="text" class="form-control form-control-sm" v-model="line.manufacturer" maxlength="255">
                      </td>
                      <td>
                        <input type="text" class="form-control form-control-sm" v-model="line.product_code" maxlength="100">
                      </td>
                      <td>
                        <input type="text" class="form-control form-control-sm" v-model="line.product_name" maxlength="255">
                      </td>
                      <td>
                        <input type="number" min="1" step="1" class="form-control form-control-sm text-end"
                               v-model.number="line.quantity">
                      </td>
                      <td>
                        <input type="number" min="0" step="1" class="form-control form-control-sm text-end"
                               v-model.number="line.unit_price">
                      </td>
                      <td>
                        <input type="number" min="0" step="1" class="form-control form-control-sm text-end"
                               v-model.number="line.amount_with_tax"
                               @input="updateTotalAmount"
                               @change="updateTotalAmount">
                      </td>
                      <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger" @click="removeLine(idx)">
                          <i class="fa fa-trash"></i>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" @click="addLine">
                  <i class="fa fa-plus me-1"></i> 行を追加
                </button>
                <div class="fw-bold">
                  合計金額: ¥{{ formatAmount(formData.total_amount) }}
                </div>
              </div>
              <div class="text-danger small mt-1" v-if="errors.lines">{{ errors.lines }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">承認者 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <select class="form-select" v-model="formData.approver_user_id" @change="validateField('approver_user_id')" @blur="validateField('approver_user_id')">
                <option value="">指定なし</option>
                <option v-for="user in approvers" :key="user.userid" :value="user.userid">
                  {{ formatUserDisplayName(user) }} ({{ user.userid }})
                </option>
              </select>
              <div class="text-danger small" v-if="errors.approver_user_id">{{ errors.approver_user_id }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">事由・用途 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <input type="text" class="form-control" v-model="formData.reason" maxlength="255" @blur="validateField('reason')">
              <div class="text-danger small" v-if="errors.reason">{{ errors.reason }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">注記</label>
            <div class="col-sm-9">
              <textarea class="form-control" v-model="formData.note" rows="2"></textarea>
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
    </div>
  `
};
