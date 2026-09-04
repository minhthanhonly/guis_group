/**
 * Member online/offline status page
 */
'use strict';

const { createApp } = Vue;

const FORM_TYPE_ORDER = ['leave', 'outing', 'trip', 'holiday_work', 'overtime'];

createApp({
    data() {
        return {
            members: [],
            presence: {},
            formsByUser: {},
            formsDate: '',
            statusFilter: 'online',
            companyFilter: 'GUIS',
            searchKeyword: '',
            loading: false,
            updatedAt: '',
            refreshTimer: null,
            searchPlaceholder: '検索...',
            lockingUserId: '',
            unlockingUserId: '',
            warningSavingUserId: '',
            lockButtonTitle: 'GUIS Plus アプリをリモートロック',
            unlockButtonTitle: '解除申請を承認してロック解除',
            warningToggleTitle: '残業超過トースト（GUIS Plus）',
            unlockRequests: {}
        };
    },
    computed: {
        isAdministrator() {
            return typeof USER_ROLE !== 'undefined' && USER_ROLE === 'administrator';
        },
        unlockRequestCount() {
            return Object.keys(this.unlockRequests || {}).filter((uid) => this.hasUnlockRequest(uid)).length;
        },
        filteredMembers() {
            const kw = String(this.searchKeyword || '').trim().toLowerCase();
            return this.members.filter((m) => {
                if (!this.matchesCompanyFilter(m)) return false;
                if (!this.matchesStatusFilter(m.userid)) return false;
                if (!kw) return true;
                const name = this.displayName(m).toLowerCase();
                const userid = String(m.userid || '').toLowerCase();
                const group = String(m.group_name || '').toLowerCase();
                const memberType = String(m.member_type_name || m.member_type || '').toLowerCase();
                const workHours = String(m.work_hours_label || this.workHoursLabel(m) || '').toLowerCase();
                const company = String(m.company || this.memberCompany(m) || '').toLowerCase();
                const branch = String(m.branch_name || '').toLowerCase();
                return name.indexOf(kw) !== -1
                    || userid.indexOf(kw) !== -1
                    || group.indexOf(kw) !== -1
                    || memberType.indexOf(kw) !== -1
                    || workHours.indexOf(kw) !== -1
                    || company.indexOf(kw) !== -1
                    || branch.indexOf(kw) !== -1;
            });
        },
        onlineCount() {
            return this.filteredMembers.filter((m) => this.isOnline(m.userid)).length;
        }
    },
    methods: {
        isTruthyFlag(value) {
            return value === true || value === 1 || value === '1' || value === 'true';
        },
        memberTypeLabel(member) {
            if (!member) return '';
            const name = String(member.member_type_name || '').trim();
            if (name) return name;
            return String(member.member_type || '').trim();
        },
        workHoursLabel(member) {
            if (!member) return '';
            if (member.work_hours_label) {
                return String(member.work_hours_label);
            }
            const parts = [];
            const work = this.workHoursPart(member);
            const lunch = this.lunchHoursLabel(member);
            if (work) parts.push(work);
            if (lunch) parts.push(lunch);
            return parts.join(' / ');
        },
        workHoursPart(member) {
            if (!member) return '';
            const start = member.work_start ? String(member.work_start) : '';
            const end = member.work_end ? String(member.work_end) : '';
            if (start && end) {
                return start + '〜' + end;
            }
            return start || end || '';
        },
        lunchHoursLabel(member) {
            if (!member) return '';
            if (member.lunch_label) {
                return String(member.lunch_label);
            }
            const start = member.lunch_start ? String(member.lunch_start) : '';
            const end = member.lunch_end ? String(member.lunch_end) : '';
            if (start === '00:00' && end === '00:00') {
                return '休憩無し';
            }
            if (Number(member.lunch_none) === 1) {
                return '休憩無し';
            }
            if (start && end) {
                return '昼 ' + start + '〜' + end;
            }
            return '';
        },
        hasUnlockRequest(userid) {
            const req = this.unlockRequests[userid];
            return !!(req && this.isTruthyFlag(req.pending) && !this.isTruthyFlag(req.approved));
        },
        unlockRequestAt(userid) {
            const req = this.unlockRequests[userid];
            if (!req || !req.at) return '';
            try {
                return new Date(req.at).toLocaleString('ja-JP');
            } catch (e) {
                return String(req.at);
            }
        },
        isWorkHoursWarningEnabled(member) {
            if (!member) return false;
            return this.isTruthyFlag(member.work_hours_warning);
        },
        normalizeWorkHoursWarningFlag(value) {
            return this.isTruthyFlag(value) ? 1 : 0;
        },
        applyWorkHoursWarningLocal(userid, enabled) {
            const uid = String(userid || '');
            const value = enabled ? 1 : 0;
            const idx = this.members.findIndex((m) => String(m.userid) === uid);
            if (idx >= 0) {
                this.members[idx] = {
                    ...this.members[idx],
                    work_hours_warning: value
                };
            }
        },
        async toggleWorkHoursWarning(member) {
            if (!this.isAdministrator || !member || !member.userid) {
                return;
            }
            const userid = String(member.userid).trim();
            const enabled = this.normalizeWorkHoursWarningFlag(member.work_hours_warning);
            const previous = enabled ? 0 : 1;

            // Keep UI in sync with numbers (API may return string "0"/"1")
            this.applyWorkHoursWarningLocal(userid, enabled);
            this.warningSavingUserId = userid;
            try {
                const response = await axios.get('/api/index.php', {
                    params: {
                        model: 'member',
                        method: 'set_work_hours_warning',
                        userid: userid,
                        enabled: enabled
                    },
                    paramsSerializer: (params) => {
                        // Ensure enabled=0 is always sent (some serializers drop falsy values)
                        const parts = [];
                        Object.keys(params).forEach((key) => {
                            const val = params[key];
                            parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(
                                val === null || typeof val === 'undefined' ? '' : String(val)
                            ));
                        });
                        return parts.join('&');
                    }
                });
                const data = response && response.data ? response.data : null;
                if (!data || data.status !== 'success') {
                    throw new Error((data && (data.message || data.error)) || 'update failed');
                }
                const saved = this.normalizeWorkHoursWarningFlag(data.work_hours_warning);
                this.applyWorkHoursWarningLocal(userid, saved);
                await this.pushWorkHoursWarningSetting(userid, saved);
                if (window.Notiflix && Notiflix.Notify) {
                    Notiflix.Notify.success(saved ? '残業警告をONにしました' : '残業警告をOFFにしました');
                }
            } catch (error) {
                console.error('[online] toggle work_hours_warning failed:', error);
                this.applyWorkHoursWarningLocal(userid, previous);
                if (window.Notiflix && Notiflix.Notify) {
                    Notiflix.Notify.failure('残業警告の更新に失敗しました');
                } else {
                    alert('残業警告の更新に失敗しました');
                }
            } finally {
                this.warningSavingUserId = '';
            }
        },
        /**
         * Notify GUIS Plus clients immediately via RTDB:
         *   guis_plus/work_hours_warning/{userid} = { enabled, at, by, nonce }
         */
        async pushWorkHoursWarningSetting(userid, enabled) {
            const db = this.getFirebaseDatabase();
            if (!db || !userid) {
                console.warn('[online] Firebase unavailable — client will pick up warning setting on next poll');
                return;
            }
            try {
                await db.ref('guis_plus/work_hours_warning/' + userid).set({
                    enabled: enabled ? 1 : 0,
                    at: new Date().toISOString(),
                    by: String(window.currentUserName || window.currentUserId || ''),
                    reason: 'admin_toggle_warning',
                    nonce: String(Date.now()) + '_' + Math.random().toString(36).slice(2, 8)
                });
            } catch (error) {
                console.warn('[online] push work_hours_warning to Firebase failed:', error);
            }
        },
        assetsBase() {
            if (typeof window.assetsPath !== 'undefined' && window.assetsPath) {
                return window.assetsPath;
            }
            return '/assets/';
        },
        displayName(m) {
            if (!m) return '-';
            if (m.realname) return m.realname;
            const last = m.lastname_after_married || m.lastname || '';
            const first = m.firstname || '';
            const full = (last + ' ' + first).trim();
            return full || m.userid || '-';
        },
        avatarInitials(m) {
            if (typeof getAvatarName === 'function') {
                return getAvatarName({
                    realname: this.displayName(m),
                    userid: m && m.userid,
                    user_ruby: m && m.user_ruby
                });
            }
            const name = this.displayName(m);
            return name ? String(name).substring(0, 2) : '?';
        },
        hasValidAvatar(m) {
            if (typeof isValidAvatarFilename === 'function') {
                return isValidAvatarFilename(m && m.user_image);
            }
            const img = m && m.user_image != null ? String(m.user_image).trim() : '';
            return !!img && img !== '1.png' && img !== 'no-image.png' && img !== 'default.png';
        },
        avatarUrl(m) {
            if (typeof getAvatarSrcFromImage === 'function') {
                return getAvatarSrcFromImage(m && m.user_image);
            }
            const base = this.assetsBase();
            if (m && m.user_image) {
                return base + 'upload/avatar/' + m.user_image;
            }
            return '';
        },
        onAvatarLoad(e) {
            if (!e || !e.target) return;
            e.target.style.display = 'block';
            const initials = e.target.previousElementSibling;
            if (initials) initials.style.display = 'none';
        },
        onAvatarError(e) {
            if (e && e.target) {
                e.target.remove();
            }
        },
        presenceOf(userid) {
            return this.presence[userid] || { web: false, app: false, online: false };
        },
        isOnline(userid) {
            return !!(this.presenceOf(userid).online);
        },
        isWeb(userid) {
            return !!(this.presenceOf(userid).web);
        },
        isApp(userid) {
            return !!(this.presenceOf(userid).app);
        },
        avatarClass(userid) {
            const p = this.presenceOf(userid);
            return {
                'avatar-online': !!p.online,
                'avatar-offline': !p.online,
                'avatar-online-app': !!p.app
            };
        },
        matchesStatusFilter(userid) {
            const p = this.presenceOf(userid);
            if (this.statusFilter === 'online') return !!p.online;
            if (this.statusFilter === 'offline') return !p.online;
            if (this.statusFilter === 'app') return !!p.app;
            return true;
        },
        memberCompany(member) {
            if (!member) return 'GUIS';
            if (member.company === 'CAILY' || member.company === 'GUIS') {
                return member.company;
            }
            const branch = String(member.branch_name || '').trim();
            return (branch.toUpperCase() === 'CAILY') ? 'CAILY' : 'GUIS';
        },
        matchesCompanyFilter(member) {
            if (this.companyFilter === 'all') return true;
            return this.memberCompany(member) === this.companyFilter;
        },
        formsOf(userid) {
            const byType = this.formsByUser[userid];
            if (!byType || typeof byType !== 'object') return [];
            const list = [];
            FORM_TYPE_ORDER.forEach((type) => {
                const items = byType[type];
                if (Array.isArray(items)) {
                    items.forEach((f) => list.push(f));
                }
            });
            return list;
        },
        formBadgeClass(f) {
            const type = f && f.type ? f.type : '';
            const map = {
                leave: 'bg-label-warning',
                outing: 'bg-label-info',
                trip: 'bg-label-primary',
                holiday_work: 'bg-label-danger',
                overtime: 'bg-label-secondary'
            };
            const status = f && f.status ? f.status : '';
            if (status === 'pending') {
                const solid = {
                    leave: 'bg-warning',
                    outing: 'bg-info',
                    trip: 'bg-primary',
                    holiday_work: 'bg-danger',
                    overtime: 'bg-secondary'
                };
                return solid[type] || 'bg-secondary';
            }
            return map[type] || 'bg-label-secondary';
        },
        formTooltip(f) {
            if (!f) return '';
            const parts = [
                f.type_label || f.type || '',
                f.status_label || f.status || '',
                f.start_date || ''
            ];
            if (f.end_date && f.end_date !== f.start_date) {
                parts.push('〜 ' + f.end_date);
            }
            const timeLabel = this.formTimeLabel(f);
            if (timeLabel) {
                parts.push(timeLabel);
            }
            if (f.data && f.data.reason) {
                parts.push(f.data.reason);
            }
            return parts.filter(Boolean).join(' / ');
        },
        formTimeLabel(f) {
            if (!f) return '';
            if (f.time_label) {
                return String(f.time_label);
            }
            const start = this.normalizeFormTime(f.start_time || (f.data && f.data.start_time));
            const end = this.normalizeFormTime(f.end_time || (f.data && f.data.end_time));
            if (start && end) {
                return start + '〜' + end;
            }
            return start || end || '';
        },
        normalizeFormTime(value) {
            if (value == null || value === '') return '';
            const raw = String(value).trim();
            const match = raw.match(/^(\d{1,2}):(\d{2})/);
            if (!match) return raw.slice(0, 5);
            return String(match[1]).padStart(2, '0') + ':' + match[2];
        },
        formDetailUrl(id) {
            return '/form/detail.php?id=' + encodeURIComponent(id);
        },
        normalizePresence(raw) {
            const out = {};
            if (!raw || typeof raw !== 'object') return out;
            Object.keys(raw).forEach((uid) => {
                const v = raw[uid];
                if (v === true || v === 1 || v === '1' || v === 'true') {
                    out[uid] = { web: true, app: false, online: true };
                    return;
                }
                if (!v || typeof v !== 'object') return;
                const web = !!(v.web === true || v.web === 1 || v.web === '1' || v.web === 'true');
                const app = !!(v.app === true || v.app === 1 || v.app === '1' || v.app === 'true');
                if (web || app || v.online) {
                    out[uid] = { web, app, online: !!(web || app || v.online) };
                }
            });
            return out;
        },
        applyPresence(raw) {
            this.presence = this.normalizePresence(raw);
            this.updatedAt = new Date().toLocaleString('ja-JP');
            try {
                const onlineIds = Object.keys(this.presence).filter((uid) => this.presence[uid].online);
                sessionStorage.setItem('connected_users', JSON.stringify(onlineIds));
                sessionStorage.setItem('connected_users_presence', JSON.stringify(this.presence));
            } catch (e) { /* ignore */ }
        },
        async loadMembers() {
            const response = await axios.get('/api/index.php?model=member&method=get_member');
            let list = (response.data && response.data.list) ? response.data.list : [];
            list = list.filter((m) => {
                if (String(m.is_suspend) === '1') return false;
                if (String(m.group_name || '') === '退職者') return false;
                return true;
            }).map((m) => ({
                ...m,
                work_hours_warning: this.normalizeWorkHoursWarningFlag(m.work_hours_warning)
            }));
            this.members = list;
        },
        async loadPresence() {
            try {
                const cached = sessionStorage.getItem('connected_users_presence');
                if (cached) {
                    this.applyPresence(JSON.parse(cached));
                }
            } catch (e) { /* ignore */ }

            try {
                const response = await axios.get('/api/index.php?model=member&method=get_presence');
                if (response.data && response.data.status === 'success') {
                    this.applyPresence(response.data.presence || {});
                }
            } catch (error) {
                console.error('Error loading presence:', error);
            }
        },
        async loadTodayForms() {
            try {
                const response = await axios.get('/api/index.php?model=member&method=get_today_forms');
                const data = response && response.data ? response.data : null;
                if (data && data.status === 'success') {
                    this.formsDate = data.date || '';
                    this.formsByUser = data.by_user || {};
                    return;
                }
                console.warn('get_today_forms failed:', data);
                this.formsByUser = {};
            } catch (error) {
                console.error('Error loading today forms:', error);
                this.formsByUser = {};
            }
        },
        async refreshAll() {
            this.loading = true;
            try {
                await Promise.all([this.loadMembers(), this.loadPresence(), this.loadTodayForms()]);
            } catch (error) {
                console.error('Error refreshing online page:', error);
            } finally {
                this.loading = false;
                this.$nextTick(() => {
                    if (typeof window.translateI18n === 'function') {
                        window.translateI18n();
                    }
                    document.querySelectorAll('[data-i18n]').forEach((el) => {
                        const key = el.getAttribute('data-i18n');
                        if (window.i18next && key && window.i18next.exists(key)) {
                            el.textContent = window.i18next.t(key);
                        }
                    });
                });
            }
        },
        listenFirebasePresence() {
            const tryBind = () => {
                const nm = window.notificationManager;
                if (!nm || !nm.database) return false;
                const ref = nm.database.ref('connected_users');
                ref.on('value', (snapshot) => {
                    const connected = snapshot.val() || {};
                    if (typeof nm.parseConnectedUsersPresence === 'function') {
                        this.applyPresence(nm.parseConnectedUsersPresence(connected));
                    } else {
                        this.applyPresence(connected);
                    }
                });
                return true;
            };
            if (tryBind()) return;
            let attempts = 0;
            const timer = setInterval(() => {
                attempts += 1;
                if (tryBind() || attempts > 20) {
                    clearInterval(timer);
                }
            }, 500);
        },
        getFirebaseDatabase() {
            const nm = window.notificationManager;
            if (nm && nm.database) {
                return nm.database;
            }
            if (window.firebase && window.firebase.apps && window.firebase.apps.length) {
                return window.firebase.database();
            }
            return null;
        },
        /**
         * Remote lock GUIS Plus via RTDB:
         *   guis_plus/locks/{userid} = { active, at, by, nonce, reason }
         */
        async lockUserPc(member) {
            if (!this.isAdministrator) {
                if (window.Notiflix && Notiflix.Notify) {
                    Notiflix.Notify.warning('管理者のみロックできます');
                } else {
                    alert('管理者のみロックできます');
                }
                return;
            }
            const userid = member && member.userid ? String(member.userid).trim() : '';
            if (!userid) return;
            if (!this.isApp(userid)) {
                if (window.Notiflix && Notiflix.Notify) {
                    Notiflix.Notify.warning('アプリでオンラインのユーザーのみロックできます');
                } else {
                    alert('アプリでオンラインのユーザーのみロックできます');
                }
                return;
            }

            const name = this.displayName(member);
            const confirmMsg = (window.i18next && window.i18next.t)
                ? (window.i18next.t('このユーザーのPCをロックしますか？') || 'このユーザーのPCをロックしますか？')
                : 'このユーザーのPCをロックしますか？';
            const ok = window.confirm(`${confirmMsg}\n${name} (${userid})`);
            if (!ok) return;

            const db = this.getFirebaseDatabase();
            if (!db) {
                const msg = 'Firebase に接続できません。ページを再読み込みしてください。';
                if (window.Notiflix && Notiflix.Notify) {
                    Notiflix.Notify.failure(msg);
                } else {
                    alert(msg);
                }
                return;
            }

            this.lockingUserId = userid;
            try {
                const payload = {
                    active: true,
                    at: new Date().toISOString(),
                    by: String(window.currentUserName || window.currentUserId || ''),
                    reason: 'admin_remote_lock',
                    nonce: String(Date.now()) + '_' + Math.random().toString(36).slice(2, 8)
                };
                await db.ref('guis_plus/locks/' + userid).set(payload);
                // New lock clears any previous unlock request UI
                await db.ref('guis_plus/unlock_requests/' + userid).set({
                    pending: false,
                    approved: false,
                    cleared_at: new Date().toISOString(),
                    cleared_by: String(window.currentUserName || ''),
                    reason: 'admin_new_lock'
                });
                if (window.Notiflix && Notiflix.Notify) {
                    Notiflix.Notify.success('ロック命令を送信しました');
                }
            } catch (error) {
                console.error('[online] remote lock failed:', error);
                const msg = 'ロック命令の送信に失敗しました';
                if (window.Notiflix && Notiflix.Notify) {
                    Notiflix.Notify.failure(msg);
                } else {
                    alert(msg);
                }
            } finally {
                this.lockingUserId = '';
            }
        },
        /**
         * Approve unlock request from GUIS Plus lock screen:
         *   guis_plus/unlock_requests/{userid}.approved = true
         *   guis_plus/locks/{userid}.active = false
         */
        async approveUnlockRequest(member) {
            if (!this.isAdministrator) {
                if (window.Notiflix && Notiflix.Notify) {
                    Notiflix.Notify.warning('管理者のみ解除承認できます');
                } else {
                    alert('管理者のみ解除承認できます');
                }
                return;
            }
            const userid = member && member.userid ? String(member.userid).trim() : '';
            if (!userid || !this.hasUnlockRequest(userid)) return;

            const name = this.displayName(member);
            const confirmMsg = (window.i18next && window.i18next.t)
                ? (window.i18next.t('このユーザーのロック解除を承認しますか？') || 'このユーザーのロック解除を承認しますか？')
                : 'このユーザーのロック解除を承認しますか？';
            if (!window.confirm(`${confirmMsg}\n${name} (${userid})`)) return;

            const db = this.getFirebaseDatabase();
            if (!db) {
                const msg = 'Firebase に接続できません。ページを再読み込みしてください。';
                if (window.Notiflix && Notiflix.Notify) {
                    Notiflix.Notify.failure(msg);
                } else {
                    alert(msg);
                }
                return;
            }

            this.unlockingUserId = userid;
            try {
                const adminId = String(window.currentUserName || window.currentUserId || '');
                const approveNonce = String(Date.now()) + '_' + Math.random().toString(36).slice(2, 8);
                const prev = this.unlockRequests[userid] || {};
                await db.ref('guis_plus/unlock_requests/' + userid).set({
                    ...prev,
                    pending: false,
                    approved: true,
                    approved_at: new Date().toISOString(),
                    approved_by: adminId,
                    approve_nonce: approveNonce,
                    reason: 'admin_unlock_approve'
                });
                await db.ref('guis_plus/locks/' + userid).set({
                    active: false,
                    at: new Date().toISOString(),
                    by: adminId,
                    reason: 'admin_remote_unlock',
                    nonce: approveNonce
                });
                if (window.Notiflix && Notiflix.Notify) {
                    Notiflix.Notify.success('ロック解除を承認しました');
                }
            } catch (error) {
                console.error('[online] approve unlock failed:', error);
                const msg = '解除承認に失敗しました';
                if (window.Notiflix && Notiflix.Notify) {
                    Notiflix.Notify.failure(msg);
                } else {
                    alert(msg);
                }
            } finally {
                this.unlockingUserId = '';
            }
        },
        listenUnlockRequests() {
            if (!this.isAdministrator) {
                this.unlockRequests = {};
                return;
            }
            const tryBind = () => {
                const db = this.getFirebaseDatabase();
                if (!db) return false;
                db.ref('guis_plus/unlock_requests').on('value', (snapshot) => {
                    const raw = snapshot.val() || {};
                    const next = {};
                    Object.keys(raw).forEach((uid) => {
                        if (raw[uid] && typeof raw[uid] === 'object') {
                            next[uid] = raw[uid];
                        }
                    });
                    this.unlockRequests = next;
                    this.updatedAt = new Date().toLocaleString('ja-JP');
                });
                return true;
            };
            if (tryBind()) return;
            let attempts = 0;
            const timer = setInterval(() => {
                attempts += 1;
                if (tryBind() || attempts > 20) {
                    clearInterval(timer);
                }
            }, 500);
        }
    },
    mounted() {
        if (window.i18next && typeof window.i18next.t === 'function') {
            this.searchPlaceholder = window.i18next.t('検索...') || '検索...';
            this.lockButtonTitle = window.i18next.t('GUIS Plus アプリをリモートロック')
                || 'GUIS Plus アプリをリモートロック';
            this.unlockButtonTitle = window.i18next.t('解除申請を承認してロック解除')
                || '解除申請を承認してロック解除';
            this.warningToggleTitle = window.i18next.t('残業超過トースト（GUIS Plus）')
                || '残業超過トースト（GUIS Plus）';
        }
        this.refreshAll();
        this.listenFirebasePresence();
        this.listenUnlockRequests();
        this.refreshTimer = setInterval(() => {
            this.loadPresence();
            this.loadTodayForms();
        }, 20000);
    },
    beforeUnmount() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
    }
}).mount('#memberOnlineApp');
