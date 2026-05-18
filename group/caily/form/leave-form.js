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
        start_datetime: '',
        end_datetime: '',
        days: '',
        leave_type: '',
        paid_type: '',
        unpaid_type: '',
        reason: '',
        note: '',
        add_to_calendar: false,
        approver_user_ids: []
      },
      errors: {},
      modalTitle: this.mode == 'edit' ? '休暇申請編集' :  '休暇申請' ,
      submitting: false,
      approvers: [],
      originalData: null
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
        note: '',
        add_to_calendar: false,
        approver_user_ids: []
      }, this.defaultData);
      this.formData.add_to_calendar = this.normalizeAddToCalendar(this.formData.add_to_calendar);
      this.originalData = JSON.parse(JSON.stringify(this.formData));
    }
  },
  mounted() {
    // Initialize flatpickr for datetime fields when component is mounted
    this.initDateTimePickers();
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
            leave_type: '',
            paid_type: '',
            unpaid_type: '',
            reason: '',
            note: '',
            add_to_calendar: false,
            approver_user_ids: []
          }, newVal);
          this.formData.add_to_calendar = this.normalizeAddToCalendar(this.formData.add_to_calendar);
          this.originalData = JSON.parse(JSON.stringify(this.formData));
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
  computed: {
    isDirty() {
      if (this.mode !== 'edit') return true;
      if (!this.originalData) return false;
      return JSON.stringify(this.formData) !== JSON.stringify(this.originalData);
    }
  },
  methods: {
    formatUserDisplayName,
    normalizeAddToCalendar(value) {
      if (value === true) return true;
      if (value === false || value === null || value === undefined) return false;
      // Chuyển 0/1 hoặc '0'/'1' sang boolean
      if (value === 1 || value === '1') return true;
      if (value === 0 || value === '0') return false;
      return !!value;
    },
    initDateTimePickers() {
      if (typeof flatpickr === 'undefined') return;
      const startEl = document.getElementById('leave-start-datetime');
      const endEl = document.getElementById('leave-end-datetime');
      if (startEl) {
        if (startEl._flatpickr) startEl._flatpickr.destroy();
        startEl._flatpickr = flatpickr(startEl, {
          enableTime: false,
          dateFormat: 'Y-m-d',
          locale: typeof flatpickr !== 'undefined' && flatpickr.l10ns ? flatpickr.l10ns.ja : undefined,
          onChange: (selectedDates, dateStr) => {
            this.formData.start_datetime = dateStr || '';
            this.updateDaysByDateRange();
            this.validateField('start_datetime');
          }
        });
        if (this.formData.start_datetime) {
          const v = this.formData.start_datetime.replace('T', ' ').slice(0, 10);
          startEl._flatpickr.setDate(v, false, 'Y-m-d');
        }
      }
      if (endEl) {
        if (endEl._flatpickr) endEl._flatpickr.destroy();
        endEl._flatpickr = flatpickr(endEl, {
          enableTime: false,
          dateFormat: 'Y-m-d',
          locale: typeof flatpickr !== 'undefined' && flatpickr.l10ns ? flatpickr.l10ns.ja : undefined,
          onChange: (selectedDates, dateStr) => {
            this.formData.end_datetime = dateStr || '';
            this.updateDaysByDateRange();
            this.validateField('end_datetime');
          }
        });
        if (this.formData.end_datetime) {
          const v = this.formData.end_datetime.replace('T', ' ').slice(0, 10);
          endEl._flatpickr.setDate(v, false, 'Y-m-d');
        }
      }
    },
    updateDaysByDateRange() {
      const startStr = this.formData.start_datetime;
      const endStr = this.formData.end_datetime;
      if (!startStr || !endStr) return;
      const start = new Date(startStr);
      const end = new Date(endStr);
      if (isNaN(start) || isNaN(end) || end < start) return;
      // Tính số ngày làm việc (loại trừ Thứ 7 & Chủ nhật), bao gồm cả ngày bắt đầu và kết thúc
      let workDays = 0;
      const cur = new Date(start.getTime());
      while (cur <= end) {
        const day = cur.getDay(); // 0=Sun, 6=Sat
        if (day !== 0 && day !== 6) {
          workDays += 1;
        }
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
        if (start > end) {
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
      if (this.formData.leave_type === '有給休暇' && !this.formData.paid_type) {
        this.errors.paid_type = '有給休暇の種類を選択してください。';
        valid = false;
      }
      if (this.formData.leave_type === '無給休暇' && !this.formData.unpaid_type) {
        this.errors.unpaid_type = '無給休暇の種類を選択してください。';
        valid = false;
      }
      if (!this.validateApproverUserIds(this.formData.approver_user_ids)) {
        this.errors.approver_user_ids = '承認者(指定)を選択してください。';
        valid = false;
      }
      if (!this.formData.reason || !String(this.formData.reason).trim()) {
        this.errors.reason = '事由を入力してください。';
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
        else if (this.formData.start_datetime && this.formData.end_datetime && new Date(this.formData.start_datetime) > new Date(this.formData.end_datetime)) err.end_datetime = '終了日時は開始日時より後にしてください。';
        else { delete err.end_datetime; }
      } else if (field === 'days') {
        if (!this.formData.days || isNaN(this.formData.days) || Number(this.formData.days) <= 0) err.days = '日間は0より大きい値を入力してください。';
        else { delete err.days; }
      } else if (field === 'leave_type') {
        if (!this.formData.leave_type) err.leave_type = '休暇種別を選択してください。';
        else { delete err.leave_type; }
      } else if (field === 'paid_type') {
        if (this.formData.leave_type === '有給休暇' && !this.formData.paid_type) err.paid_type = '有給休暇の種類を選択してください。';
        else { delete err.paid_type; }
      } else if (field === 'unpaid_type') {
        if (this.formData.leave_type === '無給休暇' && !this.formData.unpaid_type) err.unpaid_type = '無給休暇の種類を選択してください。';
        else { delete err.unpaid_type; }
      } else if (field === 'approver_user_ids') {
        if (!this.validateApproverUserIds(this.formData.approver_user_ids)) err.approver_user_ids = '承認者(指定)を選択してください。';
        else { delete err.approver_user_ids; }
      } else if (field === 'reason') {
        if (!this.formData.reason || !String(this.formData.reason).trim()) err.reason = '事由を入力してください。';
        else { delete err.reason; }
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
          const payload = { id: this.defaultData.id, data: this.formData, start_date: startDate, end_date: endDate, approver_user_id: this.encodeApproverUserIds(this.formData.approver_user_ids) };
          await axios.post('/api/index.php?model=request&method=edit', payload, {
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
          });
          this.$emit('submitted', this.formData);
          this.close();
        } else {
          const payload = { type: 'leave', data: this.formData, status, start_date: startDate, end_date: endDate, approver_user_id: this.encodeApproverUserIds(this.formData.approver_user_ids) };
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
            <label class="col-sm-3 col-form-label">期間</label>
            <div class="col-sm-9">
              <span class="request-print-text">{{ printPeriod(formData.start_datetime, formData.end_datetime) }}</span>
            </div>
          </div>
          <div v-else class="mb-3 row">
            <label class="col-sm-3 col-form-label">期間 <span class="text-danger">*</span></label>
            <div class="col-sm-4">
              <input
                type="text"
                id="leave-start-datetime"
                class="form-control"
                :value="formData.start_datetime ? formData.start_datetime.replace(/-/g, '/').slice(0, 10) : ''"
                readonly
              >
              <div class="text-danger small" v-if="errors.start_datetime">{{ errors.start_datetime }}</div>
            </div>
            <div class="col-sm-1 text-center">~</div>
            <div class="col-sm-4">
              <input
                type="text"
                id="leave-end-datetime"
                class="form-control"
                :value="formData.end_datetime ? formData.end_datetime.replace(/-/g, '/').slice(0, 10) : ''"
                readonly
              >
              <div class="text-danger small" v-if="errors.end_datetime">{{ errors.end_datetime }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">日間 <span class="text-danger">*</span></label>
            <div class="col-sm-4">
              <input type="number" step="0.5" min="0" class="form-control" v-model="formData.days" @blur="validateField('days')">
              <div class="text-danger small" v-if="errors.days">{{ errors.days }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">休暇種別 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="paid" value="有給休暇" v-model="formData.leave_type" @change="validateField('leave_type'); validateField('paid_type'); validateField('unpaid_type')">
                <label class="form-check-label" for="paid">有給休暇</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="unpaid" value="無給休暇" v-model="formData.leave_type" @change="validateField('leave_type'); validateField('paid_type'); validateField('unpaid_type')">
                <label class="form-check-label" for="unpaid">無給休暇</label>
              </div>
              <div class="text-danger small" v-if="errors.leave_type">{{ errors.leave_type }}</div>
            </div>
          </div>
         
          <div class="mb-3 row" v-if="formData.leave_type === 'paid' || formData.leave_type === '有給休暇'">
            <label class="col-sm-3 col-form-label">有給休暇 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="full" value="全休" v-model="formData.paid_type" @change="validateField('paid_type')">
                <label class="form-check-label" for="full">全休</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="am" value="午前休" v-model="formData.paid_type" @change="validateField('paid_type')">
                <label class="form-check-label" for="am">午前休</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="pm" value="午後休" v-model="formData.paid_type" @change="validateField('paid_type')">
                <label class="form-check-label" for="pm">午後休</label>
              </div>
              <div class="text-danger small" v-if="errors.paid_type">{{ errors.paid_type }}</div>
            </div>
          </div>
          <div class="mb-3 row" v-if="formData.leave_type === 'unpaid' || formData.leave_type === '無給休暇'">
            <label class="col-sm-3 col-form-label">無給休暇 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <div class="mb-2">
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" id="absence" value="欠勤" v-model="formData.unpaid_type" @change="validateField('unpaid_type')">
                  <label class="form-check-label" for="absence">欠勤</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" id="congratulatory" value="慶弔休暇" v-model="formData.unpaid_type" @change="validateField('unpaid_type')">
                  <label class="form-check-label" for="congratulatory">慶弔休暇</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" id="menstrual" value="生理休暇" v-model="formData.unpaid_type" @change="validateField('unpaid_type')">
                  <label class="form-check-label" for="menstrual">生理休暇</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" id="child_nursing" value="子の看護等休暇" v-model="formData.unpaid_type" @change="validateField('unpaid_type')">
                  <label class="form-check-label" for="child_nursing">子の看護等休暇</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" id="unpaid_other" value="その他" v-model="formData.unpaid_type" @change="validateField('unpaid_type')">
                  <label class="form-check-label" for="unpaid_other">その他</label>
                </div>
              </div>
              <div class="text-danger small" v-if="errors.unpaid_type">{{ errors.unpaid_type }}</div>
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
            <label class="col-sm-3 col-form-label">注記</label>
            <div class="col-sm-9">
              <textarea class="form-control" v-model="formData.note" rows="2"></textarea>
            </div>
          </div>
          <div class="mb-3 row request-print-hide">
            <label class="col-sm-3">カレンダーに追加</label>
            <div class="col-sm-9">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="leave-add-to-calendar" v-model="formData.add_to_calendar">
                <label class="form-check-label" for="leave-add-to-calendar">承認後にカレンダーに追加する</label>
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
      </div>
       <div class="text-muted small" v-if="mode==='add'">
          <ul>
            <li>1週間前までに提出して下さい。</li>
            <li>有給休暇以外に無給休暇※（欠勤、慶弔休暇、生理休暇、子の看護等休暇、その他）を取得する場合も休暇届で申請してください。<br>※無給休暇とは・・給与計算上は欠勤と同じ扱いになるため休んだ日数について欠勤控除が発生します。</li>
          </ul>
        </div>
    </div>
  `
};