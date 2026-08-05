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
            statusFilter: 'all',
            searchKeyword: '',
            loading: false,
            updatedAt: '',
            refreshTimer: null,
            searchPlaceholder: '検索...'
        };
    },
    computed: {
        filteredMembers() {
            const kw = String(this.searchKeyword || '').trim().toLowerCase();
            return this.members.filter((m) => {
                if (!this.matchesStatusFilter(m.userid)) return false;
                if (!kw) return true;
                const name = this.displayName(m).toLowerCase();
                const userid = String(m.userid || '').toLowerCase();
                const group = String(m.group_name || '').toLowerCase();
                return name.indexOf(kw) !== -1 || userid.indexOf(kw) !== -1 || group.indexOf(kw) !== -1;
            });
        },
        onlineCount() {
            return this.filteredMembers.filter((m) => this.isOnline(m.userid)).length;
        }
    },
    methods: {
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
        avatarUrl(m) {
            const base = this.assetsBase();
            if (m && m.user_image) {
                return base + 'upload/avatar/' + m.user_image;
            }
            return base + 'img/avatars/1.png';
        },
        onAvatarError(e) {
            const fallback = this.assetsBase() + 'img/avatars/1.png';
            if (e && e.target && e.target.src !== fallback) {
                e.target.src = fallback;
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
            if (f.data && f.data.reason) {
                parts.push(f.data.reason);
            }
            return parts.filter(Boolean).join(' / ');
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
            });
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
                if (response.data && response.data.status === 'success') {
                    this.formsDate = response.data.date || '';
                    this.formsByUser = response.data.by_user || {};
                }
            } catch (error) {
                console.error('Error loading today forms:', error);
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
        }
    },
    mounted() {
        if (window.i18next && typeof window.i18next.t === 'function') {
            this.searchPlaceholder = window.i18next.t('検索...') || '検索...';
        }
        this.refreshAll();
        this.listenFirebasePresence();
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
