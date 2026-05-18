import { formatUserDisplayName } from '/assets/js/user-display-name.js';
import { normalizeApproverUserIds } from './approver-multiselect.js';

export default {
  name: 'ApproverSelect',
  props: {
    modelValue: { type: [Array, String], default: () => [] },
    options:     { type: Array,          default: () => [] },
    placeholder: { type: String,         default: '承認者を選択してください' },
    disabled:    { type: Boolean,        default: false }
  },
  emits: ['update:modelValue', 'change', 'blur'],
  expose: ['syncFromModel'],
  data() {
    return {
      s2: null,            // jQuery wrapped <select>
      busy: false,         // prevent re-entrant applyValue
      _pendingApply: null, // IDs waiting to be applied after init
      _onChange: null,
      _onBlur: null,
    };
  },
  computed: {
    normalizedValue() {
      return normalizeApproverUserIds(this.modelValue);
    },
    displayOptions() {
      const seen = new Set();
      const list = [];
      (this.options || []).forEach((u) => {
        if (!u || u.userid === undefined || u.userid === null) return;
        const userid = String(u.userid);
        if (seen.has(userid)) return;
        seen.add(userid);
        list.push(Object.assign({}, u, { userid }));
      });
      this.normalizedValue.forEach((userid) => {
        const id = String(userid);
        if (seen.has(id)) return;
        seen.add(id);
        list.push({ userid: id, realname: id });
      });
      return list;
    }
  },
  created() {
    this._onChange = () => {
      if (this.busy || !this.s2) return;
      const val  = this.s2.val() || [];
      const arr  = Array.isArray(val) ? val.filter(Boolean) : (val ? [String(val)] : []);
      this.$emit('update:modelValue', arr);
      this.$emit('change', arr);
    };
    this._onBlur = () => this.$emit('blur');
  },
  mounted() {
    this.$nextTick(() => this._boot());
  },
  beforeUnmount() {
    this._teardown();
  },
  watch: {
    /* When the approvers list arrives (async), rebuild Select2 and re-apply selection */
    options: {
      handler() {
        this.$nextTick(() => this._boot());
      },
      deep: true
    },
    /* When parent sets/changes modelValue (e.g. edit modal opens) */
    modelValue: {
      handler(val) {
        const ids = normalizeApproverUserIds(val);
        // Always keep pending IDs so an options re-render can re-apply them.
        this._pendingApply = ids;
        this.$nextTick(() => {
          if (this.s2) this._boot();
        });
      },
      deep: true,
      immediate: true
    },
    disabled(val) {
      if (this.s2) this.s2.prop('disabled', !!val).trigger('change.select2');
    }
  },
  methods: {
    formatUserDisplayName,
    /* Called by parent mixin after loadApprovers() completes */
    syncFromModel() {
      this.$nextTick(() => this._boot());
    },
    _boot() {
      const $ = window.jQuery;
      if (!$ || !$.fn.select2) { return; }
      const el = this.$refs.selectEl;
      if (!el) { return; }
      const $el = $(el);

      // Tear down existing instance cleanly
      this._teardown($el);

      const dropdownParent = $el.closest('.modal-body, .modal-content, .modal').first();
      $el.select2({
        width: '100%',
        placeholder: this.placeholder,
        allowClear: true,
        closeOnSelect: false,
        dropdownParent: dropdownParent.length ? dropdownParent : $('body')
      });

      this.s2 = $el;
      $el.on('change.as select2:select.as select2:unselect.as select2:clear.as', this._onChange);
      $el.on('select2:close.as', this._onBlur);

      // Determine which IDs to pre-select
      const ids = this._pendingApply !== null
        ? this._pendingApply
        : this.normalizedValue;
      // Keep pending IDs until a successful apply; options can arrive later.

      if (ids.length) {
        this.$nextTick(() => this._applyIds(ids));
      }
    },
    _teardown($el) {
      const target = $el || (this.s2 && this.s2.length ? this.s2 : null);
      if (target && target.length) {
        target.off('.as');
        try {
          if (target.hasClass('select2-hidden-accessible')) target.select2('destroy');
        } catch (_) { /* ignore */ }
      }
      this.s2 = null;
    },
    _applyIds(ids) {
      if (!this.s2) return;
      const el   = this.$refs.selectEl;
      if (!el)   return;

      // Only keep IDs that exist as <option> values
      const available = new Set(Array.from(el.options).map(o => String(o.value)));
      const valid = ids.filter(id => available.has(String(id)));
      if (!valid.length && ids.length) return;

      // Compare with current selection
      const current = Array.from(el.selectedOptions || []).map(o => String(o.value));
      const same = current.length === valid.length
        && current.every(v => valid.includes(v));
      if (same) return;

      this.busy = true;
      try {
        Array.from(el.options).forEach(opt => {
          opt.selected = valid.includes(String(opt.value));
        });
        this.s2.val(valid.length ? valid : null).trigger('change.select2');
        if (valid.length) this._pendingApply = null;
      } finally {
        this.busy = false;
      }
    }
  },
  template: `
    <select ref="selectEl"
            class="form-select approver-select2"
            multiple
            :disabled="disabled">
      <option v-for="u in displayOptions" :key="u.userid" :value="String(u.userid)">
        {{ formatUserDisplayName(u) }} ({{ u.userid }})
      </option>
    </select>
  `
};
