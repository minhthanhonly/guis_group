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
        destination: '',
        reason: '',
        note: '',
        add_to_calendar: false,
        approver_user_id: ''
      },
      errors: {},
      modalTitle: this.mode === 'edit' ? '出張申請編集' : '出張申請書',
      submitting: false,
      approvers: []
    };
  },
  created() {
    if (this.defaultData && Object.keys(this.defaultData).length > 0) {
      this.formData = Object.assign({
        start_datetime: '',
        end_datetime: '',
        days: '',
        destination: '',
        reason: '',
        note: '',
        add_to_calendar: false,
        approver_user_id: ''
      }, this.defaultData);
      this.formData.add_to_calendar = this.normalizeAddToCalendar(this.formData.add_to_calendar);
    }
  },
  mounted() {
    this.loadApprovers();
  },
  watch: {
    defaultData: {
      handler(newVal) {
        if (newVal && Object.keys(newVal).length > 0) {
          this.formData = Object.assign({
            start_datetime: '',
            end_datetime: '',
            days: '',
            destination: '',
            reason: '',
            note: '',
            add_to_calendar: false,
            approver_user_id: ''
          }, newVal);
          this.formData.add_to_calendar = this.normalizeAddToCalendar(this.formData.add_to_calendar);
        }
      },
      immediate: true,
      deep: true
    },
    'formData.start_datetime'() {
      this.updateDaysByDateRange();
    },
    'formData.end_datetime'() {
      this.updateDaysByDateRange();
    }
  },
  methods: {
    normalizeAddToCalendar(value) {
      if (value === true) return true;
      if (value === false || value === null || value === undefined) return false;
      if (value === 1 || value === '1') return true;
      if (value === 0 || value === '0') return false;
      return !!value;
    },
    async loadApprovers() {
      try {
        const res = await axios.get('/api/index.php?model=member&method=list_request_approvers');
        this.approvers = Array.isArray(res.data) ? res.data : [];
      } catch (e) {
        this.approvers = [];
      }
    },
    updateDaysByDateRange() {
      const startStr = this.formData.start_datetime;
      const endStr = this.formData.end_datetime;
      if (!startStr || !endStr) return;
      const start = new Date(startStr);
      const end = new Date(endStr);
      if (isNaN(start) || isNaN(end) || end < start) return;
      let workDays = 0;
      const cur = new Date(start.getTime());
      while (cur <= end) {
        const day = cur.getDay();
        if (day !== 0 && day !== 6) workDays += 1;
        cur.setDate(cur.getDate() + 1);
      }
      this.formData.days = workDays > 0 ? String(workDays) : '';
    },
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
      if (!this.formData.destination || !String(this.formData.destination).trim()) {
        this.errors.destination = '行先を入力してください。';
        valid = false;
      }
      if (!this.formData.reason || !String(this.formData.reason).trim()) {
        this.errors.reason = '事由を入力してください。';
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
      if (field === 'start_datetime') {
        if (!this.formData.start_datetime) err.start_datetime = '開始日時を入力してください。';
        else { delete err.start_datetime; }
      } else if (field === 'end_datetime') {
        if (!this.formData.end_datetime) err.end_datetime = '終了日時を入力してください。';
        else if (this.formData.start_datetime && this.formData.end_datetime && new Date(this.formData.start_datetime) >= new Date(this.formData.end_datetime)) err.end_datetime = '終了日時は開始日時より後にしてください。';
        else { delete err.end_datetime; }
      } else if (field === 'days') {
        if (!this.formData.days || isNaN(this.formData.days) || Number(this.formData.days) <= 0) err.days = '日間は0より大きい値を入力してください。';
        else { delete err.days; }
      } else if (field === 'destination') {
        if (!this.formData.destination || !String(this.formData.destination).trim()) err.destination = '行先を入力してください。';
        else { delete err.destination; }
      } else if (field === 'reason') {
        if (!this.formData.reason || !String(this.formData.reason).trim()) err.reason = '事由を入力してください。';
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
        const startDate = this.formData.start_datetime ? this.formData.start_datetime.slice(0, 10) : '';
        const endDate = this.formData.end_datetime ? this.formData.end_datetime.slice(0, 10) : '';
        if (this.mode === 'edit') {
          const payload = { id: this.defaultData.id, data: this.formData, start_date: startDate, end_date: endDate, approver_user_id: this.formData.approver_user_id || '' };
          await axios.post('/api/index.php?model=request&method=edit', payload, {
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
          });
          this.$emit('submitted', this.formData);
          this.close();
        } else {
          const payload = { type: 'trip', data: this.formData, status, start_date: startDate, end_date: endDate, approver_user_id: this.formData.approver_user_id || '' };
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
    <div>
      <div class="modal-header">
        <h5 class="modal-title">{{ modalTitle }}</h5>
        <button type="button" class="btn-close" @click="close"></button>
      </div>
      <div class="modal-body">
        <form @submit.prevent="submit('pending')">
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">期間 <span class="text-danger">*</span></label>
            <div class="col-sm-4">
              <input type="date" class="form-control" v-model="formData.start_datetime" @blur="validateField('start_datetime')">
              <div class="text-danger small" v-if="errors.start_datetime">{{ errors.start_datetime }}</div>
            </div>
            <div class="col-sm-1 text-center">~</div>
            <div class="col-sm-4">
              <input type="date" class="form-control" v-model="formData.end_datetime" @blur="validateField('end_datetime')">
              <div class="text-danger small" v-if="errors.end_datetime">{{ errors.end_datetime }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">日間 <span class="text-danger">*</span></label>
            <div class="col-sm-4">
              <input type="number" step="1" min="0" class="form-control" v-model="formData.days" readonly>
              <div class="text-danger small" v-if="errors.days">{{ errors.days }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">行先 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <input type="text" class="form-control" v-model="formData.destination" maxlength="255" @blur="validateField('destination')">
              <div class="text-danger small" v-if="errors.destination">{{ errors.destination }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">事由 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <input type="text" class="form-control" v-model="formData.reason" maxlength="255" @blur="validateField('reason')">
              <div class="text-danger small" v-if="errors.reason">{{ errors.reason }}</div>
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
          <div class="mb-3 row">
            <label class="col-sm-3">カレンダーに追加</label>
            <div class="col-sm-9">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="trip-add-to-calendar" v-model="formData.add_to_calendar">
                <label class="form-check-label" for="trip-add-to-calendar">承認後にカレンダーに追加する</label>
              </div>
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
      <div class="text-muted small" v-if="mode==='add'">
          <ul>
            <li>1週間前までに提出して下さい。</li>
            <li>宿泊を伴う出張が必要になった場合に提出してください。</li>
            <li>出張に伴う交通機関や宿泊施設の手配については総務課から都度案内します。 </li>
          </ul>
        </div>
    </div>
  `
};
