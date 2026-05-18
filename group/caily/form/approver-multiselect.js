import { formatUserDisplayName } from '/assets/js/user-display-name.js';

function decodeHtmlEntities(value) {
  return String(value)
    .replace(/&quot;/g, '"')
    .replace(/&#34;/g, '"')
    .replace(/&#39;/g, "'")
    .replace(/&amp;/g, '&');
}

export function normalizeApproverUserIds(value) {
  if (Array.isArray(value)) {
    return value.reduce((acc, v) => {
      if (v === null || v === undefined) return acc;
      if (Array.isArray(v)) {
        acc.push(...normalizeApproverUserIds(v));
        return acc;
      }
      const str = decodeHtmlEntities(v).trim();
      if (!str) return acc;
      if (str[0] === '[') {
        try {
          const parsed = JSON.parse(str);
          if (Array.isArray(parsed)) {
            acc.push(...normalizeApproverUserIds(parsed));
            return acc;
          }
        } catch (e) { /* legacy single id */ }
      }
      acc.push(str);
      return acc;
    }, []);
  }
  if (value === null || value === undefined) return [];
  const str = decodeHtmlEntities(value).trim();
  if (!str) return [];
  if (str[0] === '[') {
    try {
      const parsed = JSON.parse(str);
      if (Array.isArray(parsed)) return normalizeApproverUserIds(parsed);
    } catch (e) { /* legacy single id */ }
  }
  return [str];
}

export function encodeApproverUserIds(ids) {
  const list = normalizeApproverUserIds(ids);
  return list.length ? JSON.stringify(list) : '';
}

export function userIsDesignatedApprover(approverField, userId) {
  if (!userId) return false;
  return normalizeApproverUserIds(approverField).includes(userId);
}

export const approverMultiselectMixin = {
  created() {
    this._normalizeFormApproverIds();
  },
  mounted() {
    this._normalizeFormApproverIds();
  },
  methods: {
    formatUserDisplayName,
    normalizeApproverUserIds,
    encodeApproverUserIds,
    userIsDesignatedApprover,
    _normalizeFormApproverIds() {
      if (!this.formData || !Object.prototype.hasOwnProperty.call(this.formData, 'approver_user_ids')) {
        return;
      }
      this.formData.approver_user_ids = this.normalizeApproverUserIds(
        this.formData.approver_user_id || this.formData.approver_user_ids
      );
    },
    syncApproverUserIdsFromDom() {
      if (!this.formData) return;
      const root = this.$el || document.getElementById('formModal');
      if (!root) return;
      const el = root.querySelector('select.approver-select2');
      if (!el) return;
      const arr = Array.from(el.selectedOptions || [])
        .map((o) => String(o.value).trim())
        .filter(Boolean);
      if (arr.length) {
        this.formData.approver_user_ids = normalizeApproverUserIds(arr);
      }
    },
    getApproverUserIds() {
      this.syncApproverUserIdsFromDom();
      return normalizeApproverUserIds(this.formData?.approver_user_ids);
    },
    setApproverUserIds(ids) {
      if (!this.formData) return;
      this.formData.approver_user_ids = normalizeApproverUserIds(ids);
    },
    onApproverUserIdsChange(ids) {
      if (Array.isArray(ids) || typeof ids === 'string' || ids == null) {
        this.setApproverUserIds(ids);
      }
      this.validateField('approver_user_ids');
    },
    onApproverUserIdsBlur() {
      this.syncApproverUserIdsFromDom();
      this.validateField('approver_user_ids');
    },
    validateApproverUserIds(ids) {
      const fromArg = ids !== undefined && ids !== null
        ? normalizeApproverUserIds(ids)
        : [];
      if (fromArg.length > 0) return true;
      return this.getApproverUserIds().length > 0;
    },
    formatApproverUserIdsLabel(ids) {
      const list = normalizeApproverUserIds(ids);
      if (!list.length) return '-';
      const names = list.map((uid) => {
        const user = (this.approvers || []).find((u) => String(u.userid) === String(uid));
        return user ? `${formatUserDisplayName(user)} (${uid})` : uid;
      });
      return names.join('、');
    },
    refreshApproverSelect() {
      this.$nextTick(() => {
        this.$nextTick(() => {
          const ref = this.$refs.approverSelectRef;
          const comp = Array.isArray(ref) ? ref[0] : ref;
          if (comp && typeof comp.syncFromModel === 'function') {
            comp.syncFromModel();
          }
        });
      });
    },
    async loadApprovers() {
      try {
        const res = await axios.get('/api/index.php?model=member&method=list_request_approvers');
        this.approvers = Array.isArray(res.data) ? res.data : [];
      } catch (e) {
        this.approvers = [];
      }
      this.refreshApproverSelect();
    }
  }
};
