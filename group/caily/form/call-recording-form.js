import { formatUserDisplayName } from '/assets/js/user-display-name.js';
import { approverMultiselectMixin } from './approver-multiselect.js';

export default {
  mixins: [approverMultiselectMixin],
  props: {
    defaultData: { type: Object, default: () => ({}) },
    mode: { type: String, default: 'add' }
  },
  data() {
    return {
      formData: {
        call_datetime: '',
        call_partner: '',
        reason: '',
        confirm_content: '',
        note: '',
        approver_user_ids: []
      },
      errors: {},
      modalTitle: this.mode === 'edit' ? '通話録音確認 編集' : '通話録音確認',
      submitting: false,
      approvers: [],
      originalData: null,
      callDatetimePicker: null
    };
  },
  created() {
    if (this.defaultData && Object.keys(this.defaultData).length > 0) {
      this.formData = Object.assign({
        call_datetime: '',
        call_partner: '',
        reason: '',
        confirm_content: '',
        note: '',
        approver_user_ids: []
      }, this.defaultData);
      this.formData.call_datetime = this.normalizeDateTimeValue(this.formData.call_datetime);
      this.originalData = JSON.parse(JSON.stringify(this.formData));
    }
  },
  mounted() {
    this.loadApprovers();
    this.$nextTick(() => this.initCallDatetimePicker());
  },
  beforeUnmount() {
    this.destroyCallDatetimePicker();
  },
  watch: {
    defaultData: {
      handler(newVal) {
        if (newVal && Object.keys(newVal).length > 0) {
          this.formData = Object.assign({
            call_datetime: '',
            call_partner: '',
            reason: '',
            confirm_content: '',
            note: '',
            approver_user_ids: []
          }, newVal);
          this.formData.call_datetime = this.normalizeDateTimeValue(this.formData.call_datetime);
          this.originalData = JSON.parse(JSON.stringify(this.formData));
          this.$nextTick(() => this.initCallDatetimePicker());
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
    callDatetimePrintLabel() {
      return this.formatCallDatetime(this.formData.call_datetime);
    }
  },
  methods: {
    formatUserDisplayName,
    normalizeDateTimeValue(value) {
      if (!value) return '';
      let str = String(value).trim();
      // Chuẩn hóa về format giống parent_project/create: Y/m/d H:i
      if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(str)) {
        str = str.replace('T', ' ').slice(0, 16).replace(/-/g, '/');
      } else if (/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}/.test(str)) {
        str = str.slice(0, 16).replace(/-/g, '/');
      } else if (/^\d{4}-\d{2}-\d{2}$/.test(str)) {
        str = str.replace(/-/g, '/') + ' 00:00';
      } else if (/^\d{4}\/\d{2}\/\d{2}\s\d{2}:\d{2}/.test(str)) {
        str = str.slice(0, 16);
      } else if (/^\d{4}\/\d{2}\/\d{2}$/.test(str)) {
        str = str + ' 00:00';
      }
      return str;
    },
    formatCallDatetime(value) {
      const normalized = this.normalizeDateTimeValue(value);
      if (!normalized) return '';
      const d = new Date(normalized.replace(/-/g, '/'));
      if (isNaN(d.getTime())) return value ? String(value) : '';
      const youbi = ['日', '月', '火', '水', '木', '金', '土'];
      const wd = youbi[d.getDay()];
      return `${d.getFullYear()}/${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}(${wd}) ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
    },
    destroyCallDatetimePicker() {
      if (this.callDatetimePicker) {
        this.callDatetimePicker.destroy();
        this.callDatetimePicker = null;
      }
      const el = this.$refs.callDatetimeInput;
      if (el && el._flatpickr) {
        el._flatpickr.destroy();
      }
    },
    // Giống parent_project (makeChildProjectTimeInputsEditable): bỏ readonly để gõ số vào flatpickr-hour
    makeCallTimeInputsEditable(_selectedDates, _dateStr, instance) {
      const cal = instance && instance.calendarContainer;
      if (!cal) return;
      cal.querySelectorAll('.flatpickr-time input, .flatpickr-time .numInputWrapper input').forEach((input) => {
        input.removeAttribute('readonly');
        input.readOnly = false;
      });
    },
    initCallDatetimePicker() {
      if (this.mode === 'print') return;
      if (typeof flatpickr === 'undefined') return;
      const el = this.$refs.callDatetimeInput;
      if (!el) return;
      this.destroyCallDatetimePicker();
      if (this.formData.call_datetime) {
        el.value = this.formData.call_datetime;
      }
      // Cấu hình theo parent_project/assets/js/parent-project-create.js (request_date_picker)
      this.callDatetimePicker = flatpickr(el, {
        enableTime: true,
        dateFormat: 'Y/m/d H:i',
        time_24hr: true,
        allowInput: true,
        locale: 'ja',
        defaultHour: 9,
        defaultMinute: 0,
        onOpen: this.makeCallTimeInputsEditable,
        onChange: (selectedDates, dateStr) => {
          this.formData.call_datetime = dateStr || '';
          this.validateField('call_datetime');
        }
      });
      if (this.formData.call_datetime) {
        this.callDatetimePicker.setDate(this.formData.call_datetime);
      }
    },
    validate() {
      this.errors = {};
      let valid = true;
      if (!this.formData.call_datetime || !String(this.formData.call_datetime).trim()) {
        this.errors.call_datetime = '通話日時を入力してください。';
        valid = false;
      }
      if (!this.formData.call_partner || !String(this.formData.call_partner).trim()) {
        this.errors.call_partner = '通話相手を入力してください。';
        valid = false;
      }
      if (!this.formData.reason || !String(this.formData.reason).trim()) {
        this.errors.reason = '通話録音の確認が必要な理由を入力してください。';
        valid = false;
      }
      if (!this.formData.confirm_content || !String(this.formData.confirm_content).trim()) {
        this.errors.confirm_content = '確認したい内容を入力してください。';
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
      if (field === 'call_datetime') {
        if (!this.formData.call_datetime || !String(this.formData.call_datetime).trim()) err.call_datetime = '通話日時を入力してください。';
        else { delete err.call_datetime; }
      } else if (field === 'call_partner') {
        if (!this.formData.call_partner || !String(this.formData.call_partner).trim()) err.call_partner = '通話相手を入力してください。';
        else { delete err.call_partner; }
      } else if (field === 'reason') {
        if (!this.formData.reason || !String(this.formData.reason).trim()) err.reason = '通話録音の確認が必要な理由を入力してください。';
        else { delete err.reason; }
      } else if (field === 'confirm_content') {
        if (!this.formData.confirm_content || !String(this.formData.confirm_content).trim()) err.confirm_content = '確認したい内容を入力してください。';
        else { delete err.confirm_content; }
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
          const payload = Object.assign({ type: 'call_recording', status }, payloadBase);
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
            <label class="col-sm-3 col-form-label">通話日時 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <span v-if="mode === 'print'" class="request-print-text">{{ callDatetimePrintLabel }}</span>
              <input v-else ref="callDatetimeInput" id="call_datetime_picker" type="text" class="form-control" v-model="formData.call_datetime" placeholder="YYYY/MM/DD HH:mm" autocomplete="off">
              <div class="text-danger small" v-if="errors.call_datetime">{{ errors.call_datetime }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">通話相手 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <input type="text" class="form-control" v-model="formData.call_partner" maxlength="255" @blur="validateField('call_partner')">
              <div class="text-danger small" v-if="errors.call_partner">{{ errors.call_partner }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">通話録音の確認が<br>必要な理由 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <textarea class="form-control" v-model="formData.reason" rows="3" @blur="validateField('reason')"></textarea>
              <div class="text-danger small" v-if="errors.reason">{{ errors.reason }}</div>
            </div>
          </div>
          <div class="mb-3 row">
            <label class="col-sm-3 col-form-label">確認したい内容 <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <textarea class="form-control" v-model="formData.confirm_content" rows="3" @blur="validateField('confirm_content')"></textarea>
              <div class="text-danger small" v-if="errors.confirm_content">{{ errors.confirm_content }}</div>
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
            <label class="col-sm-3 col-form-label">注記</label>
            <div class="col-sm-9">
              <textarea class="form-control" v-model="formData.note" rows="2"></textarea>
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
      <div class="text-muted small px-3 pb-3" v-if="mode!=='print'">
        ※録音確認が必要な理由・確認したい内容を具体的に記載してください。
      </div>
    </div>
  `
};
