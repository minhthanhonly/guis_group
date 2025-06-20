export default {
  props: {
    defaultData: { type: Object, default: () => ({}) },
    mode: { type: String, default: 'add' }
  },
  data() {
    const initialForm = Object.assign({
      start_datetime: '',
      end_datetime: '',
      days: '',
      leave_type: '',
      paid_type: '',
      unpaid_type: '',
      reason: '',
      note: ''
    }, this.defaultData);
    console.log('[leave-form] data() defaultData:', this.defaultData, '=> form:', initialForm);
    return {
      form: initialForm,
      errors: {},
      modalTitle: this.mode == 'edit' ? '休暇申請編集' :  '休暇申請' ,
      submitting: false
    }
  },
  mounted() {
    console.log('[leave-form] mounted, defaultData:', this.defaultData);
  },
  methods: {
    validate() {
      this.errors = {};
      let valid = true;
      if (!this.form.start_datetime) {
        this.errors.start_datetime = '開始日時を入力してください。';
        valid = false;
      }
      if (!this.form.end_datetime) {
        this.errors.end_datetime = '終了日時を入力してください。';
        valid = false;
      }
      if (this.form.start_datetime && this.form.end_datetime) {
        const start = new Date(this.form.start_datetime);
        const end = new Date(this.form.end_datetime);
        if (start >= end) {
          this.errors.end_datetime = '終了日時は開始日時より後にしてください。';
          valid = false;
        }
      }
      if (!this.form.days || isNaN(this.form.days) || Number(this.form.days) <= 0) {
        this.errors.days = '日間は0より大きい値を入力してください。';
        valid = false;
      }
      if (!this.form.leave_type) {
        this.errors.leave_type = '休暇種別を選択してください。';
        valid = false;
      }
      if (this.form.leave_type === 'paid' && !this.form.paid_type) {
        this.errors.paid_type = '有給休暇の種類を選択してください。';
        valid = false;
      }
      if (this.form.leave_type === 'unpaid' && !this.form.unpaid_type) {
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
          const payload = { id: this.defaultData.id, data: this.form };
          await axios.post('/api/index.php?model=request&method=edit', payload, {
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
          });
          this.$emit('submitted', this.form);
          this.close();
        } else {
          const payload = { type: 'leave', data: this.form, status };
          await axios.post('/api/index.php?model=request&method=add', payload, {
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
          });
          if(status === 'draft') alert('下書き保存しました。');
          this.$emit('submitted', this.form);
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
  watch: {
    defaultData: {
      handler(newVal) {
        console.log('[leave-form] defaultData changed:', newVal);
        this.form = Object.assign({
          start_datetime: '',
          end_datetime: '',
          days: '',
          leave_type: '',
          paid_type: '',
          unpaid_type: '',
          reason: '',
          note: ''
        }, newVal);
        console.log('[leave-form] form after assign:', this.form);
      },
      immediate: true,
      deep: true
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
              <input type="datetime-local" class="form-control" v-model="form.start_datetime">
              <div class="text-danger small" v-if="errors.start_datetime">{{ errors.start_datetime }}</div>
            </div>
            <div class="col-sm-1 text-center">~</div>
            <div class="col-sm-4">
              <input type="datetime-local" class="form-control" v-model="form.end_datetime">
              <div class="text-danger small" v-if="errors.end_datetime">{{ errors.end_datetime }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">日間</label>
            <div class="col-sm-4">
              <input type="number" step="0.5" min="0" class="form-control" v-model="form.days">
              <div class="text-danger small" v-if="errors.days">{{ errors.days }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">休暇種別</label>
            <div class="col-sm-9">
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="paid" value="paid" v-model="form.leave_type">
                <label class="form-check-label" for="paid">有給休暇</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="unpaid" value="unpaid" v-model="form.leave_type">
                <label class="form-check-label" for="unpaid">無給休暇</label>
              </div>
              <div class="text-danger small" v-if="errors.leave_type">{{ errors.leave_type }}</div>
            </div>
          </div>
          <div class="mb-3 row" v-if="form.leave_type === 'paid'">
            <label class="col-sm-3 col-form-label">有給休暇</label>
            <div class="col-sm-9">
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="full" value="full" v-model="form.paid_type">
                <label class="form-check-label" for="full">全休</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="am" value="am" v-model="form.paid_type">
                <label class="form-check-label" for="am">午前休</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="pm" value="pm" v-model="form.paid_type">
                <label class="form-check-label" for="pm">午後休</label>
              </div>
              <div class="text-danger small" v-if="errors.paid_type">{{ errors.paid_type }}</div>
            </div>
          </div>
          <div class="mb-3 row" v-if="form.leave_type === 'unpaid'">
            <label class="col-sm-3 col-form-label">無給休暇</label>
            <div class="col-sm-9">
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="congratulatory" value="congratulatory" v-model="form.unpaid_type">
                <label class="form-check-label" for="congratulatory">慶弔休暇</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="menstrual" value="menstrual" v-model="form.unpaid_type">
                <label class="form-check-label" for="menstrual">生理休暇</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="child_nursing" value="child_nursing" v-model="form.unpaid_type">
                <label class="form-check-label" for="child_nursing">子の看護休暇</label>
              </div>
              <div class="text-danger small" v-if="errors.unpaid_type">{{ errors.unpaid_type }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">事由</label>
            <div class="col-sm-9">
              <input type="text" class="form-control" v-model="form.reason" maxlength="255">
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">注記</label>
            <div class="col-sm-9">
              <textarea class="form-control" v-model="form.note" rows="2"></textarea>
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