import { formatUserDisplayName } from '/assets/js/user-display-name.js';

export function normalizeApproverUserIds(value) {
  if (Array.isArray(value)) {
    return value.map((v) => String(v).trim()).filter(Boolean);
  }
  if (value === null || value === undefined) return [];
  const str = String(value).trim();
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
  mounted() {
    if (this.formData && Object.prototype.hasOwnProperty.call(this.formData, 'approver_user_ids')) {
      this.formData.approver_user_ids = this.normalizeApproverUserIds(
        this.formData.approver_user_id || this.formData.approver_user_ids
      );
    }
  },
  methods: {
    formatUserDisplayName,
    normalizeApproverUserIds,
    encodeApproverUserIds,
    userIsDesignatedApprover,
    validateApproverUserIds(ids) {
      return normalizeApproverUserIds(ids).length > 0;
    },
    formatApproverUserIdsLabel(ids) {
      const list = normalizeApproverUserIds(ids);
      if (!list.length) return '-';
      const names = list.map((uid) => {
        const user = (this.approvers || []).find((u) => u.userid === uid);
        return user ? `${formatUserDisplayName(user)} (${uid})` : uid;
      });
      return names.join('、');
    }
  }
};
