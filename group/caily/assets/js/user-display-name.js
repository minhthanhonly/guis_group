/**
 * 結婚後の姓がある場合: 姓(結婚後の姓)  名。無い場合は realname。
 * PHP の Helper::userDisplayName と同じルール。
 */
export function formatUserDisplayName(user) {
  if (!user) return '';
  const married = (user.lastname_after_married != null ? String(user.lastname_after_married) : '').trim();
  if (married) {
    const ln = (user.lastname != null ? String(user.lastname) : '').trim();
    const fn = (user.firstname != null ? String(user.firstname) : '').trim();
    return ln + '(' + married + ')' + (fn ? '  ' + fn : '');
  }
  return user.realname ? String(user.realname) : (user.userid || '');
}

if (typeof window !== 'undefined') {
  window.formatUserDisplayName = formatUserDisplayName;
}
