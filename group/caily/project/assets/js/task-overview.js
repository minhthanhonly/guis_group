const { createApp } = Vue;

const SERVER_TASK_TIMEZONE = 'Asia/Tokyo';
const VIETNAM_TASK_TIMEZONE = 'Asia/Ho_Chi_Minh';
const TASK_DATETIME_MOMENT_FORMAT = 'YYYY/M/D HH:mm';
const TASK_DATETIME_JA_DISPLAY_FORMAT = 'YYYY年M月D日 HH:mm';
const TASK_DATETIME_FLATPICKR_FORMAT = 'Y/m/d H:i';
const TASK_DATETIME_FLATPICKR_JA_ALT_FORMAT = 'Y年n月j日 H:i';
const TASK_DATETIME_PARSE_FORMATS = [
    'YYYY-MM-DD HH:mm:ss',
    'YYYY-MM-DD HH:mm',
    'YYYY/M/D HH:mm',
    'YYYY/MM/DD HH:mm',
    'YYYY/M/D H:mm',
    'YYYY/MM/DD H:mm'
];

const OVERVIEW_TASK_KINDS_WITHOUT_DRAWING_LINK = ['修正(エラー)', 'チェック', '検討', '相談・会議', '連絡'];
const OVERVIEW_DEFAULT_TASKS_WITHOUT_DRAWING_COUNT = [
    'お客様との連絡・調整・納品対応',
    '全図面のチェック・確認作業'
];

const OVERVIEW_DEFAULT_TASK_KINDS = [
    { value: '新規作成', label: '新規作成', i18nKey: '新規作成', color: 'success' },
    { value: '修正(エラー)', label: '修正(エラー)', i18nKey: '修正(エラー)', color: 'danger' },
    { value: '修正(変更)', label: '修正(変更)', i18nKey: '修正(変更)', color: 'warning' },
    { value: 'チェック', label: 'チェック', i18nKey: 'チェック', color: 'primary' },
    { value: '連絡', label: '連絡', i18nKey: '連絡', color: 'info' },
    { value: '検討', label: '検討', i18nKey: '検討', color: 'secondary' },
    { value: '相談・会議', label: '相談・会議', i18nKey: '相談・会議', color: 'dark' }
];

createApp({
    data() {
        // Set default filters based on user role
        const isProjectManager = window.currentUser?.isProjectManager || false;
        const userDepartmentId = window.currentUser?.department_id || '';
        const defaultMyTask = !isProjectManager; // If not project manager, show only own tasks
        
        return {
            loading: false,
            departments: [],
            teams: [],
            users: [],
            tasks: [],
            unassignedUsers: [],
            activeTab: 'tasks',
            weeklyLoading: false,
            weeklyTasks: [],
            weeklyUserName: '',
            weeklyUserLogin: '',
            weeklyWeekStart: '',
            // ON: sum hours with end_time in selected week; OFF: sum all hours of selected user on task
            weeklyHoursByWeek: true,
            // ON: show time entries under each task; OFF (default): hide
            weeklyShowEntries: false,
            weeklyEntryModal: {
                show: false,
                entryId: null,
                taskId: null,
                hours: 0,
                minutes: 0,
                startAt: '',
                endAt: '',
                saving: false
            },
            filters: {
                department_id: isProjectManager ? userDepartmentId : '', // Default to user's department if project manager
                team_id: '',
                user_id: '',
                created_month: '',
                // Mặc định loại bỏ completed để giảm tải
                excludeCompleted: true,
                myTask: defaultMyTask, // Default to true if not project manager
                timerActiveOnly: false,
                // Default OFF: hide tasks without assignee
                showUnassignedTasks: false
            },
            taskStatuses: [
                { value: 'todo', label: '未開始', i18nKey: '未開始', color: 'secondary' },
                { value: 'in-progress', label: '進行中', i18nKey: '進行中', color: 'primary' },
                { value: 'confirming', label: '確認中', i18nKey: '確認中', color: 'warning' },
                { value: 'paused', label: '一時停止', i18nKey: '一時停止', color: 'warning' },
                { value: 'completed', label: '完了', i18nKey: '完了', color: 'success' },
                { value: 'cancelled', label: 'キャンセル', i18nKey: 'キャンセル', color: 'danger' }
            ],
            taskPriorities: [
                { value: 'low', label: '低', i18nKey: '低', color: 'secondary' },
                { value: 'medium', label: '中', i18nKey: '中', color: 'primary' },
                { value: 'high', label: '高', i18nKey: '高', color: 'warning' },
                { value: 'urgent', label: '緊急', i18nKey: '緊急', color: 'danger' }
            ],
            taskKinds: OVERVIEW_DEFAULT_TASK_KINDS.slice()
        };
    },
    computed: {
        filteredTeams() {
            if (!this.filters.department_id) {
                return this.teams;
            }
            return this.teams.filter(t => t.department_id == this.filters.department_id);
        },
        filteredUsers() {
            return this.users.filter(u => {
                if (this.filters.department_id && u.department_id != this.filters.department_id) {
                    return false;
                }
                if (this.filters.team_id) {
                    return (u.teams || []).some(t => t.id == this.filters.team_id);
                }
                return true;
            });
        },
        createdMonthOptions() {
            const options = [];
            const now = new Date();
            // Keep UI simple: last 24 months
            for (let i = 0; i < 24; i++) {
                const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, '0');
                options.push({
                    value: `${year}-${month}`,
                    label: `${year}年${month}月`
                });
            }
            return options;
        },
        // Danh sách task sau khi áp bộ lọc (department / team / user / excludeCompleted / timer)
        filteredTasks() {
            return this.tasks.filter(task => this.passesTaskFilters(task));
        },
        canSelectUser() {
            return !!(window.currentUser && window.currentUser.isProjectManager);
        },
        weeklyRangeLabel() {
            if (!this.weeklyWeekStart) {
                return '';
            }
            const start = this.parseTaskDateTime(this.weeklyWeekStart + ' 00:00:00') || moment(this.weeklyWeekStart);
            const end = start.clone().add(6, 'days');
            if (this.isVietnameseLocale()) {
                return start.format('YYYY/M/D') + ' - ' + end.format('YYYY/M/D');
            }
            return start.format('YYYY年M月D日') + ' 〜 ' + end.format('YYYY年M月D日');
        },
        weeklyTotalWeekHours() {
            const tasks = Array.isArray(this.weeklyTasks) ? this.weeklyTasks : [];
            let sum = 0;
            tasks.forEach((task) => {
                const hours = parseFloat(task && task.week_hours);
                if (!Number.isNaN(hours)) {
                    sum += hours;
                }
            });
            return Math.round(sum * 100) / 100;
        },
        canEditWeeklyTimeEntries() {
            return typeof USER_ROLE !== 'undefined' && USER_ROLE === 'administrator';
        }
    },
    methods: {
        $t(label) {
            if (typeof i18next !== 'undefined' && i18next.isInitialized) {
                return i18next.t(label) || label;
            }
            return label;
        },
        normalizeTaskKind(value) {
            const v = (value || '').trim();
            return this.taskKinds.some(k => k.value === v) ? v : '';
        },
        getTaskKindDisplayValue(task) {
            return this.normalizeTaskKind(task && task.task_kind);
        },
        getTaskKindLabel(value) {
            const normalized = this.normalizeTaskKind(value);
            if (!normalized) return '';
            const kind = this.taskKinds.find(k => k.value === normalized);
            return kind ? this.$t(kind.i18nKey || kind.label) : (value || '');
        },
        getTaskKindBadgeClass(value) {
            const normalized = this.normalizeTaskKind(value);
            if (!normalized) return 'bg-label-secondary';
            const kind = this.taskKinds.find(k => k.value === normalized);
            const color = kind && kind.color ? kind.color : 'secondary';
            return 'bg-label-' + color;
        },
        isDrawingLinkVisibleForTask(task) {
            if (!task) return false;
            const kind = this.getTaskKindDisplayValue(task);
            return OVERVIEW_TASK_KINDS_WITHOUT_DRAWING_LINK.indexOf(kind) === -1;
        },
        isTaskLinkedToDrawings(task) {
            return this.getTaskDrawingCount(task) > 0;
        },
        getTaskDrawingCount(task) {
            if (!task) return 0;
            const n = parseInt(task.drawing_count, 10);
            return Number.isNaN(n) ? 0 : Math.max(0, n);
        },
        isDefaultTaskWithoutDrawingCount(task) {
            if (!task) return false;
            const title = (task.title || '').trim();
            return OVERVIEW_DEFAULT_TASKS_WITHOUT_DRAWING_COUNT.indexOf(title) !== -1;
        },
        shouldShowTaskDrawingCount(task) {
            if (!task || this.isDefaultTaskWithoutDrawingCount(task)) return false;
            if (!this.isDrawingLinkVisibleForTask(task)) return false;
            return this.getTaskDrawingCount(task) > 0;
        },
        formatEstimatedHours(value) {
            const n = parseFloat(value);
            if (Number.isNaN(n) || n === 0) return '';
            const abs = Math.abs(n);
            const formatted = Number.isInteger(abs) ? String(abs) : abs.toFixed(2).replace(/\.?0+$/, '');
            return (n < 0 ? '-' : '') + formatted + 'h';
        },
        getTaskPageUrl(task) {
            if (!task || !task.project_id) {
                return '#';
            }
            const base = `task.php?project_id=${task.project_id}`;
            return task.id ? `${base}&task_id=${task.id}` : base;
        },
        parseBoolParam(value, defaultValue = false) {
            if (value === null || value === undefined || value === '') {
                return defaultValue;
            }
            const v = String(value).toLowerCase();
            if (['1', 'true', 'yes', 'on'].includes(v)) return true;
            if (['0', 'false', 'no', 'off'].includes(v)) return false;
            return defaultValue;
        },
        applyFiltersFromUrl() {
            const params = new URLSearchParams(window.location.search || '');
            if (![...params.keys()].length) {
                return false;
            }

            if (params.has('department_id')) {
                this.filters.department_id = params.get('department_id') || '';
            }
            if (params.has('team_id')) {
                this.filters.team_id = params.get('team_id') || '';
            }
            if (params.has('user_id')) {
                this.filters.user_id = params.get('user_id') || '';
            }
            if (params.has('created_month')) {
                this.filters.created_month = params.get('created_month') || '';
            }
            if (params.has('exclude_completed')) {
                this.filters.excludeCompleted = this.parseBoolParam(params.get('exclude_completed'), true);
            }
            if (params.has('my_task')) {
                this.filters.myTask = this.parseBoolParam(params.get('my_task'), false);
            }
            if (params.has('timer_active_only')) {
                this.filters.timerActiveOnly = this.parseBoolParam(params.get('timer_active_only'), false);
            }
            if (params.has('show_unassigned')) {
                this.filters.showUnassignedTasks = this.parseBoolParam(params.get('show_unassigned'), false);
            }

            const tab = params.get('tab') || '';
            if (['tasks', 'weekly', 'unassigned'].includes(tab)) {
                if (tab === 'unassigned' && !this.canSelectUser) {
                    this.activeTab = 'tasks';
                } else {
                    this.activeTab = tab;
                }
            }

            const weekStart = params.get('week_start') || '';
            if (/^\d{4}-\d{2}-\d{2}$/.test(weekStart)) {
                this.weeklyWeekStart = weekStart;
            }

            if (params.has('week_hours_mode')) {
                this.weeklyHoursByWeek = params.get('week_hours_mode') !== 'total';
            } else if (params.has('week_by_week')) {
                this.weeklyHoursByWeek = this.parseBoolParam(params.get('week_by_week'), true);
            }
            if (params.has('show_entries')) {
                this.weeklyShowEntries = this.parseBoolParam(params.get('show_entries'), false);
            }

            if (this.filters.myTask) {
                this.filters.user_id = '';
            } else if (this.filters.user_id) {
                this.filters.myTask = false;
            }

            if (!this.canSelectUser) {
                this.filters.user_id = '';
            }

            return true;
        },
        syncFiltersToUrl() {
            const params = new URLSearchParams();
            if (this.filters.department_id) {
                params.set('department_id', String(this.filters.department_id));
            }
            if (this.filters.team_id) {
                params.set('team_id', String(this.filters.team_id));
            }
            if (this.filters.user_id && !this.filters.myTask) {
                params.set('user_id', String(this.filters.user_id));
            }
            if (this.filters.created_month) {
                params.set('created_month', String(this.filters.created_month));
            }
            params.set('exclude_completed', this.filters.excludeCompleted ? '1' : '0');
            params.set('my_task', this.filters.myTask ? '1' : '0');
            if (this.filters.timerActiveOnly) {
                params.set('timer_active_only', '1');
            }
            if (this.filters.showUnassignedTasks) {
                params.set('show_unassigned', '1');
            }
            if (this.activeTab && this.activeTab !== 'tasks') {
                params.set('tab', this.activeTab);
            }
            if (this.activeTab === 'weekly' && this.weeklyWeekStart) {
                params.set('week_start', this.weeklyWeekStart);
            }
            if (!this.weeklyHoursByWeek) {
                params.set('week_hours_mode', 'total');
            }
            if (this.weeklyShowEntries) {
                params.set('show_entries', '1');
            }

            const qs = params.toString();
            const nextUrl = qs
                ? `${window.location.pathname}?${qs}`
                : window.location.pathname;
            const currentUrl = `${window.location.pathname}${window.location.search}`;
            if (nextUrl !== currentUrl) {
                window.history.replaceState({}, '', nextUrl);
            }
        },
        setActiveTab(tab) {
            this.activeTab = tab || 'tasks';
            this.syncFiltersToUrl();
        },
        getWeeklyHoursDisplay(task) {
            if (!task) {
                return 0;
            }
            if (this.weeklyHoursByWeek) {
                const weekHours = parseFloat(task.week_hours);
                return Number.isNaN(weekHours) ? 0 : weekHours;
            }
            const login = String(this.weeklyUserLogin || '').toLowerCase();
            const entries = Array.isArray(task.time_entries) ? task.time_entries : [];
            let sum = 0;
            entries.forEach((entry) => {
                if (!entry || entry.running || !entry.end_time) {
                    return;
                }
                if (login && String(entry.user_id || '').toLowerCase() !== login) {
                    return;
                }
                const hours = parseFloat(entry.hours);
                if (!Number.isNaN(hours)) {
                    sum += hours;
                }
            });
            return Math.round(sum * 100) / 100;
        },
        isCrossDayTimeEntry(entry) {
            if (!entry || entry.running || !entry.start_time || !entry.end_time) {
                return false;
            }
            const startDate = String(entry.start_time).slice(0, 10);
            const endDate = String(entry.end_time).slice(0, 10);
            if (!/^\d{4}-\d{2}-\d{2}$/.test(startDate) || !/^\d{4}-\d{2}-\d{2}$/.test(endDate)) {
                return false;
            }
            return startDate !== endDate;
        },
        parseEstimatedHoursToHoursMinutes(value) {
            const n = parseFloat(value);
            if (Number.isNaN(n) || n === 0) {
                return { hours: 0, minutes: 0 };
            }
            const sign = n < 0 ? -1 : 1;
            const abs = Math.abs(n);
            const totalMinutes = Math.round(abs * 60);
            return {
                hours: Math.floor(totalMinutes / 60) * sign,
                minutes: (totalMinutes % 60) * sign
            };
        },
        convertHoursMinutesToEstimatedHours(hours, minutes) {
            const h = parseInt(hours, 10);
            const m = parseInt(minutes, 10);
            const hoursPart = Number.isNaN(h) ? 0 : h;
            const minutesPart = Number.isNaN(m) ? 0 : m;
            return Math.round((hoursPart + (minutesPart / 60)) * 100) / 100;
        },
        getWeeklyEntryModalPreview() {
            const total = this.convertHoursMinutesToEstimatedHours(
                this.weeklyEntryModal.hours,
                this.weeklyEntryModal.minutes
            );
            return this.formatEstimatedHours(total) || '0h';
        },
        toTaskDateTimeInputValue(date) {
            const parsed = this.parseTaskDateTime(date);
            if (!parsed) return '';
            return parsed.clone().tz(this.getTaskDisplayTimezone()).format(TASK_DATETIME_MOMENT_FORMAT);
        },
        fromTaskDateTimeInputValue(value) {
            const parsed = this.parseTaskDateTime(value, this.getTaskDisplayTimezone());
            if (!parsed) return '';
            if (moment.tz) {
                return parsed.clone().tz(SERVER_TASK_TIMEZONE).format(TASK_DATETIME_MOMENT_FORMAT);
            }
            return parsed.format(TASK_DATETIME_MOMENT_FORMAT);
        },
        getFlatpickrLocale() {
            if (typeof window === 'undefined' || !window.flatpickr || !window.flatpickr.l10ns) {
                return 'default';
            }
            if (this.isVietnameseLocale()) {
                return window.flatpickr.l10ns.vi || 'default';
            }
            return window.flatpickr.l10ns.ja || 'default';
        },
        getFlatpickrOptions() {
            const isVi = this.isVietnameseLocale();
            const options = {
                enableTime: true,
                dateFormat: TASK_DATETIME_FLATPICKR_FORMAT,
                time_24hr: true,
                allowInput: true,
                closeOnSelect: false,
                static: true,
                locale: this.getFlatpickrLocale()
            };
            if (!isVi) {
                options.altInput = true;
                options.altFormat = TASK_DATETIME_FLATPICKR_JA_ALT_FORMAT;
                options.altInputClass = 'form-control';
            }
            return options;
        },
        bindWeeklyEntryFlatpickr(elId, modelKey) {
            const el = document.getElementById(elId);
            if (!el || !window.flatpickr) return null;
            if (el._flatpickr) {
                el._flatpickr.destroy();
            }
            const self = this;
            const syncValue = (dateStr) => {
                const next = String(dateStr || '').trim();
                self.weeklyEntryModal[modelKey] = next;
                el.value = next;
            };
            const fp = window.flatpickr(el, {
                ...this.getFlatpickrOptions(),
                defaultDate: this.weeklyEntryModal[modelKey] || undefined,
                onChange: function(selectedDates, dateStr) {
                    syncValue(dateStr);
                },
                onValueUpdate: function(selectedDates, dateStr) {
                    syncValue(dateStr);
                },
                onClose: function(selectedDates, dateStr) {
                    syncValue(dateStr);
                }
            });
            if (this.weeklyEntryModal[modelKey]) {
                fp.setDate(this.weeklyEntryModal[modelKey], false);
            }
            return fp;
        },
        initWeeklyEntryFlatpickr() {
            this.bindWeeklyEntryFlatpickr('weeklyEntryStartAtPicker', 'startAt');
            this.bindWeeklyEntryFlatpickr('weeklyEntryEndAtPicker', 'endAt');
        },
        destroyWeeklyEntryFlatpickr() {
            ['weeklyEntryStartAtPicker', 'weeklyEntryEndAtPicker'].forEach((id) => {
                const el = document.getElementById(id);
                if (el && el._flatpickr) {
                    el._flatpickr.destroy();
                }
            });
        },
        readWeeklyEntryFlatpickrValue(elId, fallback) {
            const el = document.getElementById(elId);
            if (el && el._flatpickr) {
                const fp = el._flatpickr;
                if (fp.selectedDates && fp.selectedDates.length) {
                    return fp.formatDate(fp.selectedDates[0], TASK_DATETIME_FLATPICKR_FORMAT);
                }
                const raw = String(fp.input && fp.input.value ? fp.input.value : (el.value || '')).trim();
                if (raw) return raw;
            }
            return String(fallback || '').trim();
        },
        toServerDateTimeValue(value) {
            const parsed = this.parseTaskDateTime(value, this.getTaskDisplayTimezone());
            if (!parsed) {
                return String(value || '').trim();
            }
            if (moment.tz) {
                return parsed.clone().tz(SERVER_TASK_TIMEZONE).format('YYYY-MM-DD HH:mm:ss');
            }
            return parsed.format('YYYY-MM-DD HH:mm:ss');
        },
        openWeeklyTimeEntryModal(task, entry) {
            if (!this.canEditWeeklyTimeEntries || !task || !entry || entry.running || !entry.id) {
                return;
            }
            const parts = this.parseEstimatedHoursToHoursMinutes(entry.hours);
            this.weeklyEntryModal.entryId = entry.id;
            this.weeklyEntryModal.taskId = task.id;
            this.weeklyEntryModal.hours = parts.hours;
            this.weeklyEntryModal.minutes = parts.minutes;
            this.weeklyEntryModal.startAt = this.toTaskDateTimeInputValue(entry.start_time || entry.end_time);
            this.weeklyEntryModal.endAt = this.toTaskDateTimeInputValue(entry.end_time || entry.start_time);
            if (!this.weeklyEntryModal.startAt || !this.weeklyEntryModal.endAt) {
                let nowStr = '';
                if (typeof moment !== 'undefined' && moment.tz) {
                    nowStr = moment().tz(this.getTaskDisplayTimezone()).format(TASK_DATETIME_MOMENT_FORMAT);
                } else if (typeof moment !== 'undefined') {
                    nowStr = moment().format(TASK_DATETIME_MOMENT_FORMAT);
                }
                if (!this.weeklyEntryModal.startAt) this.weeklyEntryModal.startAt = nowStr;
                if (!this.weeklyEntryModal.endAt) this.weeklyEntryModal.endAt = nowStr;
            }
            this.weeklyEntryModal.saving = false;
            this.weeklyEntryModal.show = true;
            this.$nextTick(() => {
                this.initWeeklyEntryFlatpickr();
                if (typeof applyDataI18n === 'function') {
                    applyDataI18n(document.getElementById('app'));
                }
            });
        },
        closeWeeklyTimeEntryModal() {
            this.destroyWeeklyEntryFlatpickr();
            this.weeklyEntryModal.show = false;
            this.weeklyEntryModal.entryId = null;
            this.weeklyEntryModal.taskId = null;
            this.weeklyEntryModal.hours = 0;
            this.weeklyEntryModal.minutes = 0;
            this.weeklyEntryModal.startAt = '';
            this.weeklyEntryModal.endAt = '';
            this.weeklyEntryModal.saving = false;
        },
        async confirmWeeklyTimeEntryModal() {
            if (!this.canEditWeeklyTimeEntries || !this.weeklyEntryModal.entryId) {
                return;
            }
            const startAt = this.readWeeklyEntryFlatpickrValue(
                'weeklyEntryStartAtPicker',
                this.weeklyEntryModal.startAt
            );
            const endAt = this.readWeeklyEntryFlatpickrValue(
                'weeklyEntryEndAtPicker',
                this.weeklyEntryModal.endAt
            );
            if (!startAt || !endAt) {
                if (typeof showMessage === 'function') {
                    showMessage('日時を入力してください', true);
                }
                return;
            }
            this.weeklyEntryModal.startAt = startAt;
            this.weeklyEntryModal.endAt = endAt;
            const hours = this.convertHoursMinutesToEstimatedHours(
                this.weeklyEntryModal.hours,
                this.weeklyEntryModal.minutes
            );
            this.weeklyEntryModal.saving = true;
            try {
                const formData = new FormData();
                formData.append('id', this.weeklyEntryModal.entryId);
                formData.append('hours', hours);
                formData.append('start_time', this.toServerDateTimeValue(startAt) || startAt);
                formData.append('end_time', this.toServerDateTimeValue(endAt) || endAt);
                const response = await axios.post(
                    '/api/index.php?model=task&method=updateTimeEntryByAdmin',
                    formData
                );
                const data = response.data || {};
                if (data.status !== 'success') {
                    if (typeof showMessage === 'function') {
                        showMessage(data.message || '作業時間の更新に失敗しました', true);
                    }
                    return;
                }
                this.closeWeeklyTimeEntryModal();
                await this.loadWeeklyTasks();
            } catch (e) {
                if (typeof showMessage === 'function') {
                    showMessage('作業時間の更新に失敗しました', true);
                }
            } finally {
                this.weeklyEntryModal.saving = false;
            }
        },
        decodeHtmlEntities(str) {
            const txt = document.createElement('textarea');
            txt.innerHTML = str;
            return txt.value;
        },
        stripHtmlToPlainText(html) {
            if (!html) return '';
            let text = String(html);
            for (let i = 0; i < 3; i++) {
                const txt = document.createElement('textarea');
                txt.innerHTML = text;
                const decoded = txt.value;
                if (decoded === text) break;
                text = decoded;
            }
            const div = document.createElement('div');
            div.innerHTML = text;
            return (div.textContent || div.innerText || '')
                .replace(/&nbsp;/gi, ' ')
                .replace(/\u00A0/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        },
        getTaskNoteSnippet(note, maxLen) {
            if (!note) return '';
            const text = this.stripHtmlToPlainText(note);
            if (!text) return '';
            const limit = maxLen || 28;
            return text.length <= limit ? text : text.substring(0, limit) + '…';
        },
        // Kiểm tra 1 task có thỏa mãn filter hiện tại không
        passesTaskFilters(task) {
            // Department filter on task
            if (this.filters.department_id && task.department_id != this.filters.department_id) {
                return false;
            }

            // Loại bỏ task completed & cancelled nếu excludeCompleted = true
            if (this.filters.excludeCompleted && (task.status === 'completed' || task.status === 'cancelled')) {
                return false;
            }
            
            // My Task filter đã được xử lý ở phía API khi gọi loadOverview(),
            // nên không cần filter lại ở đây để tránh duplicate filtering
            
            // Lọc theoユーザー đã được xử lý ở phía API (listOverview), 
            // nên không cần kiểm tra lại ở đây để tránh sai khi task có nhiều assignee.
            const assignedIds = Array.isArray(task.assigned_to_ids) ? task.assigned_to_ids : [];
            if (!this.filters.showUnassignedTasks && assignedIds.length === 0) {
                return false;
            }

            // Nếu filter theo team: ít nhất 1 user được assign thuộc team đó
            if (this.filters.team_id) {
                const teamId = parseInt(this.filters.team_id, 10);
                const hasUserInTeam = assignedIds.some(uid => {
                    const user = this.users.find(u => u.id == uid);
                    return user && Array.isArray(user.teams) && user.teams.some(t => t.id == teamId);
                });
                if (!hasUserInTeam) {
                    return false;
                }
            }

            if (this.filters.timerActiveOnly && !this.hasActiveTaskTimer(task)) {
                return false;
            }

            return true;
        },
        hasActiveTaskTimer(taskOrId) {
            const taskId = taskOrId && typeof taskOrId === 'object' ? taskOrId.id : taskOrId;
            if (!taskId) {
                return false;
            }
            if (window.TaskTimer) {
                return window.TaskTimer.hasActiveTimerForTask(taskId);
            }
            if (taskOrId && typeof taskOrId === 'object' && taskOrId.timer_active) {
                return true;
            }
            const canonical = this.tasks.find((t) => parseInt(t.id, 10) === parseInt(taskId, 10));
            return !!(canonical && canonical.timer_active);
        },
        syncTaskTimerActiveFlags(activeTaskIds) {
            const ids = Array.isArray(activeTaskIds) ? activeTaskIds : [];
            const activeSet = new Set(ids.map((id) => parseInt(id, 10)));
            this.tasks.forEach((task) => {
                if (!task || task.id == null) {
                    return;
                }
                task.timer_active = activeSet.has(parseInt(task.id, 10));
            });
        },
        onTaskTimerChanged(event) {
            const detail = event && event.detail ? event.detail : {};
            if (window.TaskTimer && Array.isArray(window.TaskTimer.activeTaskIds)) {
                this.syncTaskTimerActiveFlags(window.TaskTimer.activeTaskIds);
            } else if (detail.stopped && detail.task_id) {
                const stoppedId = parseInt(detail.task_id, 10);
                this.tasks.forEach((task) => {
                    if (task && parseInt(task.id, 10) === stoppedId) {
                        task.timer_active = false;
                    }
                });
            } else if (Array.isArray(detail.active_task_ids)) {
                this.syncTaskTimerActiveFlags(detail.active_task_ids);
            }
            this.$forceUpdate();
        },
        // Lấy tên người phụ trách của task (dùng trong danh sách 1 dòng / task)
        getAssigneeNames(task) {
            const assignedIds = Array.isArray(task.assigned_to_ids) ? task.assigned_to_ids : [];
            if (!assignedIds.length) {
                return '未選択';
            }
            const names = assignedIds.map(uid => {
                const user = this.users.find(u => u.id == uid);
                return user ? user.realname : null;
            }).filter(Boolean);
            return names.length ? names.join(', ') : '未選択';
        },
        getAssigneeUser(userId) {
            if (!userId) return null;
            return this.users.find(u => String(u.id) === String(userId)) || null;
        },
        getAvatarSrc(user) {
            if (!user || !user.user_image) return '';
            return '/assets/upload/avatar/' + (user.user_image || '');
        },
        handleAvatarError(user) {
            if (user) user.avatarError = true;
        },
        getInitials(nameOrUser) {
            if (typeof getAvatarName === 'function') return getAvatarName(nameOrUser || '');
            const name = (nameOrUser && typeof nameOrUser === 'object')
                ? (nameOrUser.realname || nameOrUser.user_name || '')
                : (nameOrUser || '');
            if (!name) return '?';
            return String(name).substring(0, 2);
        },
        isAcknowledged(task, userId) {
            if (!task || !task.acknowledgements || userId == null || userId === '') return false;
            const key = String(userId);
            const ack = task.acknowledgements[key] ?? task.acknowledgements[Number(userId)];
            return ack && (ack.acknowledged == 1 || ack.acknowledged === '1');
        },
        getAssigneeTooltip(task, userId) {
            const user = this.getAssigneeUser(userId);
            const name = user ? user.realname : userId;
            const ack = this.isAcknowledged(task, userId);
            return ack ? (name + ' - 受領済み') : (name + ' - 未受領');
        },
        getCreatorMember(task) {
            if (!task) return null;
            if (task.created_by_name != null || task.created_by_user_image != null) {
                return {
                    user_id: task.created_by,
                    user_name: task.created_by_name || '',
                    user_image: task.created_by_user_image || ''
                };
            }
            const user = task.created_by != null ? this.getAssigneeUser(task.created_by) : null;
            if (user) {
                return { user_id: user.id, user_name: user.realname || '', user_image: user.user_image || '' };
            }
            return null;
        },
        shouldShowCreatorAvatar(member) {
            return member && (member.user_image && String(member.user_image).trim() !== '') && !member.avatarError;
        },
        getCreatorTooltip(member) {
            return member ? (member.user_name || '') : '';
        },
        async loadOverview() {
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    model: 'task',
                    method: 'listOverview'
                });
                if (this.filters.department_id) {
                    params.append('department_id', this.filters.department_id);
                }
                if (this.filters.team_id) {
                    params.append('team_id', this.filters.team_id);
                }
                if (this.filters.created_month) {
                    params.append('created_month', this.filters.created_month);
                }
                // Nếu myTask được chọn, gửi user_id của user hiện tại
                if (this.filters.myTask && window.currentUser?.user_id) {
                    params.append('user_id', window.currentUser.user_id);
                } else if (this.filters.user_id) {
                    params.append('user_id', this.filters.user_id);
                }
                if (this.filters.excludeCompleted) {
                    params.append('exclude_completed', 1);
                }
                const response = await axios.get('/api/index.php?' + params.toString());
                const data = response.data || {};
                this.departments = data.departments || [];
                this.teams = data.teams || [];
                this.users = data.users || [];
                this.tasks = data.tasks || [];
                this.unassignedUsers = data.unassigned_users || [];
                if (window.TaskTimer && Array.isArray(data.active_task_ids)) {
                    window.TaskTimer.updateActiveTaskIds(data.active_task_ids);
                } else {
                    this.syncTaskTimerActiveFlags(
                        (this.tasks || []).filter((task) => task.timer_active).map((task) => task.id)
                    );
                }
                this.$nextTick(() => this.initTooltips());
            } catch (e) {
                console.error('Error loading task overview:', e);
                console.error('Error details:', e.response?.data || e.message);
                if (typeof showMessage === 'function') {
                    showMessage('タスク一覧の読み込みに失敗しました: ' + (e.response?.data?.error || e.message), true);
                } else {
                    alert('タスク一覧の読み込みに失敗しました: ' + (e.response?.data?.error || e.message));
                }
            } finally {
                this.loading = false;
                this.syncFiltersToUrl();
                this.$nextTick(() => this.initUserSelect2());
            }
        },
        initUserSelect2() {
            if (!this.canSelectUser || typeof window.jQuery === 'undefined' || !window.jQuery.fn.select2) {
                return;
            }
            const $ = window.jQuery;
            const $el = $('#overviewUserFilter');
            if (!$el.length) {
                return;
            }

            const selected = this.filters.user_id != null && this.filters.user_id !== ''
                ? String(this.filters.user_id)
                : '';

            if ($el.data('select2')) {
                $el.off('change.overviewUser');
                $el.select2('destroy');
            }

            $el.select2({
                width: '100%',
                allowClear: true,
                placeholder: this.$t('すべて'),
                dropdownParent: $el.parent()
            });

            $el.val(selected).trigger('change.select2');

            $el.off('change.overviewUser').on('change.overviewUser', () => {
                const nextValue = $el.val() || '';
                if (String(this.filters.user_id || '') === String(nextValue)) {
                    return;
                }
                this.filters.user_id = nextValue;
                this.onUserChange();
            });
        },
        syncUserSelect2Value() {
            if (!this.canSelectUser || typeof window.jQuery === 'undefined' || !window.jQuery.fn.select2) {
                return;
            }
            const $el = window.jQuery('#overviewUserFilter');
            if (!$el.length || !$el.data('select2')) {
                return;
            }
            const selected = this.filters.user_id != null && this.filters.user_id !== ''
                ? String(this.filters.user_id)
                : '';
            if (String($el.val() || '') !== selected) {
                $el.val(selected).trigger('change.select2');
            }
        },
        onDepartmentChange() {
            // Reset team and user filter when department changes
            this.filters.team_id = '';
            this.filters.user_id = '';
            this.loadOverview();
            if (this.activeTab === 'weekly') {
                this.loadWeeklyTasks();
            }
        },
        onMyTaskChange() {
            // When "My Task" is checked, set user_id to current user and reload from API
            // When unchecked, clear user_id and reload to show all tasks
            if (this.filters.myTask) {
                // Clear manual user selection when showing own tasks
                this.filters.user_id = '';
                // If not project manager, also clear department_id when showing own tasks
                if (!window.currentUser?.isProjectManager) {
                    this.filters.department_id = '';
                }
                // Reload data from API with current user filter
                this.loadOverview();
            } else {
                // When unchecked, reload all tasks
                this.loadOverview();
            }
            this.$nextTick(() => this.syncUserSelect2Value());
            if (this.activeTab === 'weekly') {
                this.loadWeeklyTasks();
            }
        },
        onUserChange() {
            // When user is manually selected, uncheck "My Task"
            if (this.filters.user_id) {
                this.filters.myTask = false;
            }
            this.loadOverview();
            if (this.activeTab === 'weekly') {
                this.loadWeeklyTasks();
            }
        },
        getDefaultFilters() {
            const isProjectManager = !!(window.currentUser && window.currentUser.isProjectManager);
            const userDepartmentId = window.currentUser?.department_id || '';
            return {
                department_id: isProjectManager ? userDepartmentId : '',
                team_id: '',
                user_id: '',
                created_month: '',
                excludeCompleted: true,
                myTask: !isProjectManager,
                timerActiveOnly: false,
                showUnassignedTasks: false
            };
        },
        resetFilters() {
            this.filters = this.getDefaultFilters();
            this.weeklyHoursByWeek = true;
            this.weeklyShowEntries = false;
            this.weeklyWeekStart = this.getCurrentWeekMonday();
            this.loadOverview().then(() => {
                this.$nextTick(() => {
                    this.initUserSelect2();
                    this.syncUserSelect2Value();
                });
                if (this.activeTab === 'weekly') {
                    this.loadWeeklyTasks();
                }
                this.syncFiltersToUrl();
            });
        },
        getCurrentWeekMonday() {
            const now = (typeof moment !== 'undefined' && moment.tz)
                ? moment.tz('Asia/Tokyo')
                : moment();
            return now.clone().startOf('isoWeek').format('YYYY-MM-DD');
        },
        ensureWeeklyWeekStart() {
            if (!this.weeklyWeekStart) {
                this.weeklyWeekStart = this.getCurrentWeekMonday();
            }
        },
        selectWeeklyTab() {
            this.activeTab = 'weekly';
            this.ensureWeeklyWeekStart();
            this.loadWeeklyTasks();
            this.syncFiltersToUrl();
        },
        shiftWeeklyWeek(deltaWeeks) {
            this.ensureWeeklyWeekStart();
            const start = moment(this.weeklyWeekStart, 'YYYY-MM-DD').add(deltaWeeks, 'weeks');
            this.weeklyWeekStart = start.format('YYYY-MM-DD');
            this.loadWeeklyTasks();
            this.syncFiltersToUrl();
        },
        goToCurrentWeek() {
            this.weeklyWeekStart = this.getCurrentWeekMonday();
            this.loadWeeklyTasks();
            this.syncFiltersToUrl();
        },
        getWeeklyTargetUserId() {
            if (!this.canSelectUser) {
                return window.currentUser?.user_id || '';
            }
            if (this.filters.myTask && window.currentUser?.user_id) {
                return window.currentUser.user_id;
            }
            return this.filters.user_id || window.currentUser?.user_id || '';
        },
        async loadWeeklyTasks() {
            this.ensureWeeklyWeekStart();
            const userId = this.getWeeklyTargetUserId();
            if (!userId) {
                this.weeklyTasks = [];
                this.weeklyUserName = '';
                this.weeklyUserLogin = '';
                return;
            }
            this.weeklyLoading = true;
            try {
                const params = new URLSearchParams({
                    model: 'task',
                    method: 'listWeeklyTasks',
                    week_start: this.weeklyWeekStart,
                    user_id: userId
                });
                const response = await axios.get('/api/index.php?' + params.toString());
                const data = response.data || {};
                this.weeklyTasks = Array.isArray(data.tasks) ? data.tasks : [];
                this.weeklyUserName = data.user && data.user.realname ? data.user.realname : '';
                this.weeklyUserLogin = data.user && data.user.userid ? data.user.userid : '';
                if (data.week_start) {
                    this.weeklyWeekStart = data.week_start;
                }
            } catch (e) {
                console.error('Error loading weekly tasks:', e);
                this.weeklyTasks = [];
                this.weeklyUserLogin = '';
            } finally {
                this.weeklyLoading = false;
                this.syncFiltersToUrl();
            }
        },
        getStatusLabel(status) {
            const s = this.taskStatuses.find(s => s.value === status);
            if (!s) return status || '—';
            if (typeof this.$t === 'function' && s.i18nKey) {
                return this.$t(s.i18nKey);
            }
            return s.label;
        },
        getStatusColor(status) {
            const s = this.taskStatuses.find(s => s.value === status);
            return s ? s.color : 'secondary';
        },
        getPriorityLabel(priority) {
            const p = this.taskPriorities.find(p => p.value === priority);
            if (!p) return priority || '—';
            if (typeof this.$t === 'function' && p.i18nKey) {
                return this.$t(p.i18nKey);
            }
            return p.label;
        },
        getPriorityColor(priority) {
            const p = this.taskPriorities.find(p => p.value === priority);
            return p ? p.color : 'secondary';
        },
        isVietnameseLocale() {
            return typeof i18next !== 'undefined'
                && i18next.isInitialized
                && String(i18next.language || '').startsWith('vi');
        },
        getTaskDisplayTimezone() {
            return this.isVietnameseLocale() ? VIETNAM_TASK_TIMEZONE : SERVER_TASK_TIMEZONE;
        },
        getTaskDateTimeDisplayFormat() {
            return this.isVietnameseLocale()
                ? TASK_DATETIME_MOMENT_FORMAT
                : TASK_DATETIME_JA_DISPLAY_FORMAT;
        },
        parseTaskDateTime(date, timezone = SERVER_TASK_TIMEZONE) {
            if (!date) return null;
            const raw = String(date).trim();
            if (!raw || raw === '0000-00-00 00:00:00' || raw === '0000-00-00') return null;
            if (typeof moment === 'undefined') return null;
            if (moment.tz) {
                for (const fmt of TASK_DATETIME_PARSE_FORMATS) {
                    const parsed = moment.tz(raw, fmt, timezone);
                    if (parsed.isValid()) return parsed;
                }
                const loose = moment.tz(raw, timezone);
                return loose.isValid() ? loose : null;
            }
            const fallback = moment(raw, TASK_DATETIME_PARSE_FORMATS, true);
            return fallback.isValid() ? fallback : null;
        },
        formatTaskDateTimeInDisplayTz(date) {
            const parsed = this.parseTaskDateTime(date);
            if (!parsed) return '-';
            const localized = moment.tz
                ? parsed.clone().tz(this.getTaskDisplayTimezone())
                : parsed;
            return localized.format(this.getTaskDateTimeDisplayFormat());
        },
        formatDate(dateString) {
            return this.formatTaskDateTimeInDisplayTz(dateString);
        },
        isTaskOverdue(task) {
            if (!task || !task.due_date) return false;
            if (task.status === 'completed' || task.status === 'cancelled') return false;
            const due = moment.tz(task.due_date, 'Asia/Tokyo');
            return moment().tz('Asia/Tokyo').isAfter(due, 'minute');
        },
        formatOverdueDurationText(hours, minutes) {
            const isVi = this.isVietnameseLocale();
            const overdueLabel = this.$t('期限切れ');
            const hourLabel = this.$t('時間');
            const minuteLabel = this.$t('分');
            const fmt = (v, l) => (isVi ? `${v} ${l}` : `${v}${l}`);
            const join = (parts) => (isVi ? parts.filter(Boolean).join(' ') : parts.filter(Boolean).join(''));

            let durationPart = '';
            if (hours > 0) {
                durationPart = join([fmt(hours, hourLabel), fmt(minutes, minuteLabel)]);
            } else {
                durationPart = fmt(minutes, minuteLabel);
            }
            return `${overdueLabel}: ${durationPart}`;
        },
        getOverdueTooltip(task) {
            if (!this.isTaskOverdue(task)) return '';
            const due = moment.tz(task.due_date, 'Asia/Tokyo');
            const now = moment().tz('Asia/Tokyo');
            const duration = moment.duration(now.diff(due));
            const hours = Math.floor(duration.asHours());
            const minutes = Math.floor(duration.asMinutes()) % 60;
            return this.formatOverdueDurationText(hours, minutes);
        },
        isTaskDueExceedsProjectDue(task) {
            if (!task || !task.due_date) return false;
            const projectEnd = task.project_end_date;
            if (!projectEnd) return false;
            const taskDue = moment.tz(task.due_date, 'Asia/Tokyo');
            const projEnd = moment.tz(projectEnd, 'Asia/Tokyo');
            return taskDue.isAfter(projEnd, 'minute');
        },
        getTaskExceedsProjectDueTooltip(task) {
            if (!this.isTaskDueExceedsProjectDue(task)) return '';
            return this.$t('タスクの期限がプロジェクトの期限を超えています');
        },
        hasPeriodWarning(task) {
            return this.isTaskOverdue(task) || this.isTaskDueExceedsProjectDue(task);
        },
        getPeriodWarningTooltip(task) {
            const parts = [];
            if (this.isTaskOverdue(task)) parts.push(this.getOverdueTooltip(task));
            if (this.isTaskDueExceedsProjectDue(task)) parts.push(this.getTaskExceedsProjectDueTooltip(task));
            return parts.join('\n');
        },
        initTooltips() {
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
                    const instance = bootstrap.Tooltip.getInstance(el);
                    if (instance) instance.dispose();
                    new bootstrap.Tooltip(el);
                });
            }
        }
    },
    watch: {
        filteredUsers() {
            this.$nextTick(() => this.initUserSelect2());
        },
        'filters.user_id'(value) {
            this.$nextTick(() => this.syncUserSelect2Value());
        },
        canSelectUser(value) {
            if (value) {
                this.$nextTick(() => this.initUserSelect2());
            }
        }
    },
    mounted() {
        const appliedFromUrl = this.applyFiltersFromUrl();

        // If not project manager, ensure myTask is enabled and department_id is cleared
        // unless URL explicitly provided filter values.
        if (!window.currentUser?.isProjectManager) {
            if (!appliedFromUrl || !new URLSearchParams(window.location.search).has('my_task')) {
                this.filters.myTask = true;
            }
            if (!appliedFromUrl || !new URLSearchParams(window.location.search).has('department_id')) {
                this.filters.department_id = '';
            }
            this.filters.user_id = '';
        }

        if (!this.weeklyWeekStart) {
            this.weeklyWeekStart = this.getCurrentWeekMonday();
        }
        this._onI18nLanguageChanged = () => {
            this.$forceUpdate();
            this.$nextTick(() => this.initTooltips());
        };
        if (typeof i18next !== 'undefined' && i18next.on) {
            i18next.on('languageChanged', this._onI18nLanguageChanged);
        }
        this._onTaskTimerChanged = (event) => this.onTaskTimerChanged(event);
        document.addEventListener('task-timer-changed', this._onTaskTimerChanged);
        this.loadOverview().then(() => {
            if (this.activeTab === 'weekly') {
                this.loadWeeklyTasks();
            }
            this.syncFiltersToUrl();
        });
    },
    beforeUnmount() {
        if (this._onTaskTimerChanged) {
            document.removeEventListener('task-timer-changed', this._onTaskTimerChanged);
            this._onTaskTimerChanged = null;
        }
        if (typeof i18next !== 'undefined' && i18next.off && this._onI18nLanguageChanged) {
            i18next.off('languageChanged', this._onI18nLanguageChanged);
        }
    }
}).mount('#app');


