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
        date: '',
        start_time: '',
        end_time: '',
        destination: '',
        reason: '',
        note: '',
        add_to_calendar: false,
        approver_user_ids: []
      },
      errors: {},
      modalTitle: this.mode === 'edit' ? '外出申請編集' : '外出申請書',
      submitting: false,
      approvers: [],
      originalData: null
    };
  },
  created() {
    if (this.defaultData && Object.keys(this.defaultData).length > 0) {
      this.formData = Object.assign({
        date: '',
        start_time: '',
        end_time: '',
        destination: '',
        reason: '',
        note: '',
        add_to_calendar: false,
        approver_user_ids: []
      }, this.defaultData);
      this.formData.date = this.normalizeDateValue(this.formData.date);
      this.formData.add_to_calendar = this.normalizeAddToCalendar(this.formData.add_to_calendar);
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
          this.formData = Object.assign({
            date: '',
            start_time: '',
            end_time: '',
            destination: '',
            reason: '',
            note: '',
            add_to_calendar: false,
            approver_user_ids: []
          }, newVal);
          this.formData.date = this.normalizeDateValue(this.formData.date);
          this.formData.add_to_calendar = this.normalizeAddToCalendar(this.formData.add_to_calendar);
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
    normalizeAddToCalendar(value) {
      if (value === true) return true;
      if (value === false || value === null || value === undefined) return false;
      if (value === 1 || value === '1') return true;
      if (value === 0 || value === '0') return false;
      return !!value;
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
      if (!this.formData.destination) {
        this.errors.destination = '行先を入力してください。';
        valid = false;
      }
      if (!this.formData.reason) {
        this.errors.reason = '事由を入力してください。';
        valid = false;
      }
      if (!this.validateApproverUserIds(this.formData.approver_user_ids)) {
        this.errors.approver_user_ids = '承認者(指定)を選択してください。';
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
      } else if (field === 'destination') {
        if (!this.formData.destination) err.destination = '行先を入力してください。';
        else { delete err.destination; }
      } else if (field === 'reason') {
        if (!this.formData.reason) err.reason = '事由を入力してください。';
        else { delete err.reason; }
      } else if (field === 'approver_user_ids') {
        if (!this.validateApproverUserIds(this.formData.approver_user_ids)) err.approver_user_ids = '承認者(指定)を選択してください。';
        else { delete err.approver_user_ids; }
      }
      this.errors = err;
    },
    async submit(status = 'pending') {
      if (!this.validate()) return;
      this.submitting = true;
      try {
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
          const payload = Object.assign({ type: 'outing', status }, payloadBase);
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
            <label class="col-sm-3 col-form-label">行先 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <input type="text" class="form-control" v-model="formData.destination" maxlength="255" @blur="validateField('destination')">
              <div class="text-danger small" v-if="errors.destination">{{ errors.destination }}</div>
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
           <div class="mb-3 row request-print-hide">
            <label class="col-sm-3">カレンダーに追加</label>
            <div class="col-sm-9">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="outing-add-to-calendar" v-model="formData.add_to_calendar">
                <label class="form-check-label" for="outing-add-to-calendar">承認後にカレンダーに追加する</label>
              </div>
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
        <div class="text-muted small mt-4" v-if="mode==='add'">
          <ul>
            <li>勤務時間内に外出（日帰り出張も含む）が必要になった場合に提出してください。
            <li>直行・直帰のため打刻ができない場合は、外出申請書に記載された時間へ打刻修正します。</li>
            <li>早朝（9時以前）、夜間（18時半以降）の時間外勤務が発生する場合は、時間外勤務申請書も別途提出してください。</li>
          </ul>
        </div>
        </div>
    </div>
  `
};

