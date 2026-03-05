export default {
  props: {
    defaultData: { type: Object, default: () => ({}) },
    mode: { type: String, default: 'add' }
  },
  data() {
    return {
      typeOptions: ['出社', '退社'],
      formData: {
        date: '',
        time: '',
        correction_type: '',
        reason: '',
        note: '',
        approver_user_id: ''
      },
      errors: {},
      modalTitle: this.mode === 'edit' ? '勤怠打刻修正 編集' : '勤怠打刻修正',
      submitting: false,
      approvers: [],
      originalData: null
    };
  },
  created() {
    if (this.defaultData && Object.keys(this.defaultData).length > 0) {
      const raw = Object.assign({
        date: '',
        time: '',
        correction_type: '',
        reason: '',
        note: '',
        approver_user_id: ''
      }, this.defaultData);
      raw.correction_type = this.typeOptions.includes(raw.correction_type) ? raw.correction_type : '';
      this.formData = raw;
      this.originalData = JSON.parse(JSON.stringify(this.formData));
    }
    if (this.mode === 'add' && !this.formData.time) this.formData.time = '09:00';
  },
  mounted() {
    this.loadApprovers();
  },
  watch: {
    defaultData: {
      handler(newVal) {
        if (newVal && Object.keys(newVal).length > 0) {
          const raw = Object.assign({
            date: '',
            time: '',
            correction_type: '',
            reason: '',
            note: '',
            approver_user_id: ''
          }, newVal);
          raw.correction_type = this.typeOptions.includes(raw.correction_type) ? raw.correction_type : '';
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
    hourOptions() {
      return Array.from({ length: 24 }, (_, i) => ({ value: i, label: String(i).padStart(2, '0') }));
    },
    minuteOptions() {
      return Array.from({ length: 60 }, (_, i) => ({ value: i, label: String(i).padStart(2, '0') }));
    },
    timeHour() {
      if (!this.formData.time || !/^\d{1,2}:\d{2}$/.test(this.formData.time)) return 9;
      return parseInt(this.formData.time.slice(0, 2), 10) || 0;
    },
    timeMinute() {
      if (!this.formData.time || !/^\d{1,2}:\d{2}$/.test(this.formData.time)) return 0;
      return parseInt(this.formData.time.slice(3, 5), 10) || 0;
    }
  },
  methods: {
    setTime(hour, minute) {
      const h = Number(hour);
      const m = Number(minute);
      this.formData.time = `${String(h >= 0 && h <= 23 ? h : 0).padStart(2, '0')}:${String(m >= 0 && m <= 59 ? m : 0).padStart(2, '0')}`;
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
      if (!this.formData.date) {
        this.errors.date = '日付を入力してください。';
        valid = false;
      }
      if (!this.formData.time) {
        this.errors.time = '時間を入力してください。';
        valid = false;
      }
      if (!this.formData.correction_type || !this.typeOptions.includes(this.formData.correction_type)) {
        this.errors.correction_type = '区分を選択してください。';
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
      if (field === 'date') {
        if (!this.formData.date) err.date = '日付を入力してください。';
        else { delete err.date; }
      } else if (field === 'time') {
        if (!this.formData.time) err.time = '時間を入力してください。';
        else { delete err.time; }
      } else if (field === 'correction_type') {
        if (!this.formData.correction_type || !this.typeOptions.includes(this.formData.correction_type)) err.correction_type = '区分を選択してください。';
        else { delete err.correction_type; }
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
          const payload = Object.assign({ type: 'attendance_correction', status }, payloadBase);
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
            <label class="col-sm-3 col-form-label">日付 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <input type="date" class="form-control" v-model="formData.date" @blur="validateField('date')">
              <div class="text-danger small" v-if="errors.date">{{ errors.date }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">時間 <span class="text-danger">*</span></label>
            <div class="col-sm-9 d-flex align-items-center gap-2 flex-wrap">
              <select class="form-select" style="width: auto; min-width: 4.5rem;" :value="timeHour" @change="setTime($event.target.value, timeMinute); validateField('time')" @blur="validateField('time')">
                <option v-for="opt in hourOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
              <span>:</span>
              <select class="form-select" style="width: auto; min-width: 4.5rem;" :value="timeMinute" @change="setTime(timeHour, $event.target.value); validateField('time')" @blur="validateField('time')">
                <option v-for="opt in minuteOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
              <div class="w-100 text-danger small" v-if="errors.time">{{ errors.time }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">区分 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <div class="d-flex flex-wrap gap-3">
                <div class="form-check" v-for="opt in typeOptions" :key="opt">
                  <input class="form-check-input" type="radio" :id="'type-' + opt" :value="opt" v-model="formData.correction_type" @change="validateField('correction_type')">
                  <label class="form-check-label" :for="'type-' + opt">{{ opt }}</label>
                </div>
              </div>
              <div class="text-danger small" v-if="errors.correction_type">{{ errors.correction_type }}</div>
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
            <li>当日までに提出してください。</li>
            <li>打刻を忘れた、誤って打刻をしてしまった場合に提出してください。</li>
            <li>勤怠システム上では各自で打刻修正ができません。申請～承認の後に総務課で打刻修正します。 </li>
          </ul>
        </div>
    </div>
  `
};
