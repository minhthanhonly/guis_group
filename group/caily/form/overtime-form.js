import { formatUserDisplayName } from '/assets/js/user-display-name.js';
import { printFormatMixin } from './print-format.js';

export default {
  mixins: [printFormatMixin],
  props: {
    defaultData: { type: Object, default: () => ({}) },
    mode: { type: String, default: 'add' }
  },
  data() {
    return {
      purposeOptions: ['遅刻', '早退', '時間外勤務'],
      formData: {
        date: '',
        start_time: '',
        end_time: '',
        purpose: '',
        note: '',
        approver_user_id: ''
      },
      errors: {},
      modalTitle: this.mode === 'edit' ? '遅刻・早退・時間外勤務 編集' : '遅刻・早退・時間外勤務',
      submitting: false,
      approvers: [],
      originalData: null
    };
  },
  created() {
    if (this.defaultData && Object.keys(this.defaultData).length > 0) {
      const raw = Object.assign({
        date: '',
        start_time: '',
        end_time: '',
        purpose: '',
        note: '',
        approver_user_id: ''
      }, this.defaultData);
      raw.date = this.normalizeDateValue(raw.date);
      raw.purpose = this.normalizePurpose(raw.purpose);
      this.formData = raw;
      this.originalData = JSON.parse(JSON.stringify(this.formData));
    }
    if (this.mode === 'add') {
      if (!this.formData.start_time) this.formData.start_time = '09:00';
      if (!this.formData.end_time) this.formData.end_time = '18:00';
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
            date: '',
            start_time: '',
            end_time: '',
            purpose: '',
            note: '',
            approver_user_id: ''
          }, newVal);
          raw.date = this.normalizeDateValue(raw.date);
          raw.purpose = this.normalizePurpose(raw.purpose);
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
    startTimeHour() {
      if (!this.formData.start_time || !/^\d{1,2}:\d{2}$/.test(this.formData.start_time)) return 9;
      return parseInt(this.formData.start_time.slice(0, 2), 10) || 0;
    },
    startTimeMinute() {
      if (!this.formData.start_time || !/^\d{1,2}:\d{2}$/.test(this.formData.start_time)) return 0;
      return parseInt(this.formData.start_time.slice(3, 5), 10) || 0;
    },
    endTimeHour() {
      if (!this.formData.end_time || !/^\d{1,2}:\d{2}$/.test(this.formData.end_time)) return 18;
      return parseInt(this.formData.end_time.slice(0, 2), 10) || 0;
    },
    endTimeMinute() {
      if (!this.formData.end_time || !/^\d{1,2}:\d{2}$/.test(this.formData.end_time)) return 0;
      return parseInt(this.formData.end_time.slice(3, 5), 10) || 0;
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
    normalizePurpose(p) {
      if (typeof p === 'string' && this.purposeOptions.includes(p)) return p;
      if (Array.isArray(p) && p.length) return this.purposeOptions.includes(p[0]) ? p[0] : '';
      return '';
    },
    setStartTime(hour, minute) {
      const h = Number(hour);
      const m = Number(minute);
      this.formData.start_time = `${String(h >= 0 && h <= 23 ? h : 0).padStart(2, '0')}:${String(m >= 0 && m <= 59 ? m : 0).padStart(2, '0')}`;
    },
    setEndTime(hour, minute) {
      const h = Number(hour);
      const m = Number(minute);
      this.formData.end_time = `${String(h >= 0 && h <= 23 ? h : 0).padStart(2, '0')}:${String(m >= 0 && m <= 59 ? m : 0).padStart(2, '0')}`;
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
      if (!this.formData.start_time) {
        this.errors.start_time = '開始時刻を入力してください。';
        valid = false;
      }
      if (!this.formData.end_time) {
        this.errors.end_time = '終了時刻を入力してください。';
        valid = false;
      }
      if (this.formData.start_time && this.formData.end_time && this.formData.start_time >= this.formData.end_time) {
        this.errors.end_time = '終了時刻は開始時刻より後にしてください。';
        valid = false;
      }
      if (!this.formData.purpose || !this.purposeOptions.includes(this.formData.purpose)) {
        this.errors.purpose = '用途を選択してください。';
        valid = false;
      }
      if (!this.formData.approver_user_id) {
        this.errors.approver_user_id = '承認者(指定)を選択してください。';
        valid = false;
      }
      return valid;
    },
    validateField(field) {
      const err = { ...this.errors };
      if (field === 'date') {
        if (!this.formData.date) err.date = '日付を入力してください。';
        else { delete err.date; }
      } else if (field === 'start_time') {
        if (!this.formData.start_time) err.start_time = '開始時刻を入力してください。';
        else { delete err.start_time; }
        if (!err.start_time && this.formData.start_time && this.formData.end_time && this.formData.start_time >= this.formData.end_time) err.end_time = '終了時刻は開始時刻より後にしてください。';
        else if (this.formData.end_time) { delete err.end_time; }
      } else if (field === 'end_time') {
        if (!this.formData.end_time) err.end_time = '終了時刻を入力してください。';
        else if (this.formData.start_time && this.formData.end_time && this.formData.start_time >= this.formData.end_time) err.end_time = '終了時刻は開始時刻より後にしてください。';
        else { delete err.end_time; }
      } else if (field === 'purpose') {
        if (!this.formData.purpose || !this.purposeOptions.includes(this.formData.purpose)) err.purpose = '用途を選択してください。';
        else { delete err.purpose; }
      } else if (field === 'approver_user_id') {
        if (!this.formData.approver_user_id) err.approver_user_id = '承認者(指定)を選択してください。';
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
          const payload = Object.assign({ type: 'overtime', status }, payloadBase);
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
          <div v-if="mode === 'print'" class="mb-3 row">
            <label class="col-sm-3 col-form-label">日時</label>
            <div class="col-sm-9">
              <span class="request-print-text">{{ printDateTimeRange(formData.date, formData.start_time, formData.end_time) }}</span>
            </div>
          </div>
          <template v-else>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">日付 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <input type="date" class="form-control" v-model="formData.date" @blur="validateField('date')">
              <div class="text-danger small" v-if="errors.date">{{ errors.date }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">開始時刻 <span class="text-danger">*</span></label>
            <div class="col-sm-9 d-flex align-items-center gap-2 flex-wrap">
              <select class="form-select" style="width: auto; min-width: 4.5rem;" :value="startTimeHour" @change="setStartTime($event.target.value, startTimeMinute); validateField('start_time')" @blur="validateField('start_time')">
                <option v-for="opt in hourOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
              <span>:</span>
              <select class="form-select" style="width: auto; min-width: 4.5rem;" :value="startTimeMinute" @change="setStartTime(startTimeHour, $event.target.value); validateField('start_time')" @blur="validateField('start_time')">
                <option v-for="opt in minuteOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
              <div class="w-100 text-danger small" v-if="errors.start_time">{{ errors.start_time }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">終了時刻 <span class="text-danger">*</span></label>
            <div class="col-sm-9 d-flex align-items-center gap-2 flex-wrap">
              <select class="form-select" style="width: auto; min-width: 4.5rem;" :value="endTimeHour" @change="setEndTime($event.target.value, endTimeMinute); validateField('end_time')" @blur="validateField('end_time')">
                <option v-for="opt in hourOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
              <span>:</span>
              <select class="form-select" style="width: auto; min-width: 4.5rem;" :value="endTimeMinute" @change="setEndTime(endTimeHour, $event.target.value); validateField('end_time')" @blur="validateField('end_time')">
                <option v-for="opt in minuteOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
              <div class="w-100 text-danger small" v-if="errors.end_time">{{ errors.end_time }}</div>
            </div>
          </div>
          </template>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">用途 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <div class="d-flex flex-wrap gap-3">
                <div class="form-check" v-for="opt in purposeOptions" :key="opt">
                  <input class="form-check-input" type="radio" :id="'purpose-' + opt" :value="opt" v-model="formData.purpose" @change="validateField('purpose')">
                  <label class="form-check-label" :for="'purpose-' + opt">{{ opt }}</label>
                </div>
              </div>
              <div class="text-danger small" v-if="errors.purpose">{{ errors.purpose }}</div>
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
              <select class="form-select" v-model="formData.approver_user_id" @change="validateField('approver_user_id')" @blur="validateField('approver_user_id')">
                <option value="">指定なし</option>
                <option v-for="user in approvers" :key="user.userid" :value="user.userid">
                  {{ formatUserDisplayName(user) }} ({{ user.userid }})
                </option>
              </select>
              <div class="text-danger small" v-if="errors.approver_user_id">{{ errors.approver_user_id }}</div>
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
          <p class="ps-3">以下の場合に提出してください。</p>
          <ul>
            <li>【遅刻（公共交通機関の遅延時含む）をした】
              <ul>
                <li>公共交通機関遅延による事由の場合は公共交通機関が発行した遅延証明書の添付が必要です。事由によっては欠勤控除となる場合があります。</li>
              </ul>
            </li>
            <li>【早退をしたい】
              <ul>
                <li>事由によっては欠勤控除となる場合があります。</li>
              </ul>
            </li>
            <li>【定時以外の早朝および夜間の勤務が必要になった】</li>
              <ul>
                <li>早朝勤務（9時以前）の場合は前日終業時まで、夜間勤務（18時半以降）の場合は当日終業時までに申請してください。</li>
                <li>申請時間と実際の出退勤打刻に30分未満の差がある場合は打刻を基準とします。</li>
                <li>申請時間と実際の出退勤打刻に30分以上の差が生じた場合は速やかに再申請をして承認を受けてください。<br>（再申請がない場合は当初の申請内容を基準とします）</li>
              </ul>
            </li>
          </ul>
        </div>
    </div>
  `
};
