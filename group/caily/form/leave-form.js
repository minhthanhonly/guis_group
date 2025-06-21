export default {
  props: {
    defaultData: { type: Object, default: () => ({}) },
    mode: { type: String, default: 'add' }
  },
  data() {
    return {
      formData: {
        start_datetime: '',
        end_datetime: '',
        days: '',
        leave_type: '',
        paid_type: '',
        unpaid_type: '',
        reason: '',
        note: ''
      },
      errors: {},
      modalTitle: this.mode == 'edit' ? '休暇申請編集' :  '休暇申請' ,
      submitting: false
    }
  },
  created() {
    // Immediately set formData if defaultData exists
    if (this.defaultData && Object.keys(this.defaultData).length > 0) {
      this.formData = Object.assign({
        start_datetime: '',
        end_datetime: '',
        days: '',
        leave_type: '',
        paid_type: '',
        unpaid_type: '',
        reason: '',
        note: ''
      }, this.defaultData);
    }
  },
  mounted() {
  },
  watch: {
    defaultData: {
      handler(newVal) {
        if (newVal && Object.keys(newVal).length > 0) {
          this.formData = Object.assign({
            start_datetime: '',
            end_datetime: '',
            days: '',
            leave_type: '',
            paid_type: '',
            unpaid_type: '',
            reason: '',
            note: ''
          }, newVal);
        }
      },
      immediate: true,
      deep: true
    }
  },
  methods: {
    validate() {
      this.errors = {};
      let valid = true;
      if (!this.formData.start_datetime) {
        this.errors.start_datetime = '開始日時を入力してください。';
        valid = false;
      }
      if (!this.formData.end_datetime) {
        this.errors.end_datetime = '終了日時を入力してください。';
        valid = false;
      }
      if (this.formData.start_datetime && this.formData.end_datetime) {
        const start = new Date(this.formData.start_datetime);
        const end = new Date(this.formData.end_datetime);
        if (start >= end) {
          this.errors.end_datetime = '終了日時は開始日時より後にしてください。';
          valid = false;
        }
      }
      if (!this.formData.days || isNaN(this.formData.days) || Number(this.formData.days) <= 0) {
        this.errors.days = '日間は0より大きい値を入力してください。';
        valid = false;
      }
      if (!this.formData.leave_type) {
        this.errors.leave_type = '休暇種別を選択してください。';
        valid = false;
      }
      if (this.formData.leave_type === 'paid' && !this.formData.paid_type) {
        this.errors.paid_type = '有給休暇の種類を選択してください。';
        valid = false;
      }
      if (this.formData.leave_type === 'unpaid' && !this.formData.unpaid_type) {
        this.errors.unpaid_type = '無給休暇の種類を選択してください。';
        valid = false;
      }
      return valid;
    },
    async submit(status = 'pending') {
      if (!this.validate()) return;
      this.submitting = true;
      try {
        if (this.mode === 'edit') {
          const payload = { id: this.defaultData.id, data: this.formData };
          await axios.post('/api/index.php?model=request&method=edit', payload, {
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
          });
          this.$emit('submitted', this.formData);
          this.close();
        } else {
          const payload = { type: 'leave', data: this.formData, status };
          await axios.post('/api/index.php?model=request&method=add', payload, {
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
          });
          if(status === 'draft') alert('下書き保存しました。');
          this.$emit('submitted', this.formData);
          this.close();
        }
      } catch (e) {
        alert(this.mode === 'edit' ? '編集に失敗しました。' : '申請に失敗しました。');
      }
      this.submitting = false;
    },
    close() {
      this.$emit('close');
    }
  },
  template: `
    <div>
      <div class="modal-header">
        <h5 class="modal-title">{{ modalTitle }}</h5>
        <button type="button" class="btn-close" @click="close"></button>
      </div>
      <div class="modal-body">
        <form @submit.prevent="submit('pending')">
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">期間</label>
            <div class="col-sm-4">
              <input type="datetime-local" class="form-control" v-model="formData.start_datetime">
              <div class="text-danger small" v-if="errors.start_datetime">{{ errors.start_datetime }}</div>
            </div>
            <div class="col-sm-1 text-center">~</div>
            <div class="col-sm-4">
              <input type="datetime-local" class="form-control" v-model="formData.end_datetime">
              <div class="text-danger small" v-if="errors.end_datetime">{{ errors.end_datetime }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">日間</label>
            <div class="col-sm-4">
              <input type="number" step="0.5" min="0" class="form-control" v-model="formData.days">
              <div class="text-danger small" v-if="errors.days">{{ errors.days }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">休暇種別</label>
            <div class="col-sm-9">
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="paid" value="paid" v-model="formData.leave_type">
                <label class="form-check-label" for="paid">有給休暇</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="unpaid" value="unpaid" v-model="formData.leave_type">
                <label class="form-check-label" for="unpaid">無給休暇</label>
              </div>
              <div class="text-danger small" v-if="errors.leave_type">{{ errors.leave_type }}</div>
            </div>
          </div>
          <div class="mb-3 row" v-if="formData.leave_type === 'paid'">
            <label class="col-sm-3 col-form-label">有給休暇</label>
            <div class="col-sm-9">
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="full" value="full" v-model="formData.paid_type">
                <label class="form-check-label" for="full">全休</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="am" value="am" v-model="formData.paid_type">
                <label class="form-check-label" for="am">午前休</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="pm" value="pm" v-model="formData.paid_type">
                <label class="form-check-label" for="pm">午後休</label>
              </div>
              <div class="text-danger small" v-if="errors.paid_type">{{ errors.paid_type }}</div>
            </div>
          </div>
          <div class="mb-3 row" v-if="formData.leave_type === 'unpaid'">
            <label class="col-sm-3 col-form-label">無給休暇</label>
            <div class="col-sm-9">
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="congratulatory" value="congratulatory" v-model="formData.unpaid_type">
                <label class="form-check-label" for="congratulatory">慶弔休暇</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="menstrual" value="menstrual" v-model="formData.unpaid_type">
                <label class="form-check-label" for="menstrual">生理休暇</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="child_nursing" value="child_nursing" v-model="formData.unpaid_type">
                <label class="form-check-label" for="child_nursing">子の看護休暇</label>
              </div>
              <div class="text-danger small" v-if="errors.unpaid_type">{{ errors.unpaid_type }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">事由</label>
            <div class="col-sm-9">
              <input type="text" class="form-control" v-model="formData.reason" maxlength="255">
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
        <button v-if="mode==='edit'" type="button" class="btn btn-primary" :disabled="submitting" @click="submit()">保存</button>
      </div>
    </div>
  `
};