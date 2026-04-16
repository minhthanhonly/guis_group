export default {
  props: {
    defaultData: { type: Object, default: () => ({}) },
    mode: { type: String, default: 'add' }
  },
  data() {
    return {
      categoryOptions: ['備品', '事務用品', 'ソフトウェア', 'その他'],
      formData: {
        category: '',
        item_name: '',
        product_link: '',
        quantity: '',
        estimated_price: '',
        item_list: '',
        reason: '',
        note: '',
        approver_user_id: ''
      },
      errors: {},
      modalTitle: this.mode === 'edit' ? '購入申請 編集' : '購入申請',
      submitting: false,
      approvers: [],
      originalData: null
    };
  },
  created() {
    if (this.defaultData && Object.keys(this.defaultData).length > 0) {
      const raw = Object.assign({
        category: '',
        item_name: '',
        product_link: '',
        quantity: '',
        estimated_price: '',
        item_list: '',
        reason: '',
        note: '',
        approver_user_id: ''
      }, this.defaultData);
      raw.category = this.categoryOptions.includes(raw.category) ? raw.category : '';
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
            category: '',
            item_name: '',
            product_link: '',
            quantity: '',
            estimated_price: '',
            item_list: '',
            reason: '',
            note: '',
            approver_user_id: ''
          }, newVal);
          raw.category = this.categoryOptions.includes(raw.category) ? raw.category : '';
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
    }
  },
  methods: {
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
      if (!this.formData.category || !this.categoryOptions.includes(this.formData.category)) {
        this.errors.category = '購入区分を選択してください。';
        valid = false;
      }
      if (!this.formData.item_name || !String(this.formData.item_name).trim()) {
        this.errors.item_name = '品名を入力してください。';
        valid = false;
      }
      const q = this.formData.quantity;
      if (q === '' || q === null || q === undefined || !/^\d+$/.test(String(q)) || parseInt(q, 10) < 1) {
        this.errors.quantity = '数量は1以上の整数を入力してください。';
        valid = false;
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
      if (field === 'category') {
        if (!this.formData.category || !this.categoryOptions.includes(this.formData.category)) err.category = '購入区分を選択してください。';
        else { delete err.category; }
      } else if (field === 'item_name') {
        if (!this.formData.item_name || !String(this.formData.item_name).trim()) err.item_name = '品名を入力してください。';
        else { delete err.item_name; }
      } else if (field === 'quantity') {
        const q = this.formData.quantity;
        if (q === '' || q === null || q === undefined || !/^\d+$/.test(String(q)) || parseInt(q, 10) < 1) err.quantity = '数量は1以上の整数を入力してください。';
        else { delete err.quantity; }
      } else if (field === 'reason') {
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
        <h5 class="modal-title">{{ modalTitle }} <span class="text-muted small">※1点あたり1万円以上の購入は申請が必要です。</span></h5>
        <button type="button" class="btn-close" @click="close"></button>
      </div>
      <div class="modal-body">
        <form @submit.prevent="submit('pending')">
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">購入区分 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <select class="form-select" v-model="formData.category" @change="validateField('category')">
                <option value="">選択してください</option>
                <option v-for="opt in categoryOptions" :key="opt" :value="opt">{{ opt }}</option>
              </select>
              <div class="text-danger small" v-if="errors.category">{{ errors.category }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">品名 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <input type="text" class="form-control" v-model="formData.item_name" maxlength="255" @blur="validateField('item_name')">
              <div class="text-danger small" v-if="errors.item_name">{{ errors.item_name }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">商品リンク</label>
            <div class="col-sm-9">
              <input type="url" class="form-control" v-model="formData.product_link" placeholder="https://..." maxlength="500">
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">数量 <span class="text-danger">*</span></label>
            <div class="col-sm-4">
              <input type="number" min="1" step="1" class="form-control" v-model.number="formData.quantity" @blur="validateField('quantity')">
              <div class="text-danger small" v-if="errors.quantity">{{ errors.quantity }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">見積金額（円）</label>
            <div class="col-sm-4">
              <input type="number" min="0" step="1" class="form-control" v-model="formData.estimated_price" placeholder="任意">
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">購入品目詳細</label>
            <div class="col-sm-9">
              <textarea class="form-control" v-model="formData.item_list" rows="4" placeholder="購入する品目の詳細リストを入力してください"></textarea>
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
