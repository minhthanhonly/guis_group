const { createApp } = Vue;

const SERVER_TASK_TIMEZONE = 'Asia/Tokyo';
const VIETNAM_TASK_TIMEZONE = 'Asia/Ho_Chi_Minh';
const TASK_DATETIME_MOMENT_FORMAT = 'YYYY/M/D HH:mm';
const TASK_DATETIME_JA_DISPLAY_FORMAT = 'YYYY年M月D日 HH:mm';
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
            filters: {
                department_id: isProjectManager ? userDepartmentId : '', // Default to user's department if project manager
                team_id: '',
                user_id: '',
                // Mặc định loại bỏ completed để giảm tải
                excludeCompleted: true,
                myTask: defaultMyTask, // Default to true if not project manager
                timerActiveOnly: false
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
        // Danh sách task sau khi áp bộ lọc (department / team / user / excludeCompleted / timer)
        filteredTasks() {
            return this.tasks.filter(task => this.passesTaskFilters(task));
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
            if (Number.isNaN(n) || n <= 0) return '';
            const formatted = Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
            return formatted + 'h';
        },
        decodeHtmlEntities(str) {
            const txt = document.createElement('textarea');
            txt.innerHTML = str;
            return txt.value;
        },
        getTaskNoteSnippet(note, maxLen) {
            if (!note) return '';
            const decoded = this.decodeHtmlEntities(String(note));
            const text = decoded.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
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
            // Nếu filter theo team: ít nhất 1 user được assign thuộc team đó
            if (this.filters.team_id) {
                const teamId = parseInt(this.filters.team_id, 10);
                const assignedIds = Array.isArray(task.assigned_to_ids) ? task.assigned_to_ids : [];
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
        getInitials(name) {
            if (typeof getAvatarName === 'function') return getAvatarName(name || '');
            if (!name || !String(name).trim()) return '?';
            return String(name).trim().split(/\s+/).map(s => s[0]).join('').toUpperCase().slice(0, 2);
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
            }
        },
        onDepartmentChange() {
            // Reset team and user filter when department changes
            this.filters.team_id = '';
            this.filters.user_id = '';
            this.loadOverview();
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
        },
        onUserChange() {
            // When user is manually selected, uncheck "My Task"
            if (this.filters.user_id) {
                this.filters.myTask = false;
            }
            this.loadOverview();
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
    mounted() {
        // If not project manager, ensure myTask is enabled and department_id is cleared
        if (!window.currentUser?.isProjectManager) {
            this.filters.myTask = true;
            this.filters.department_id = '';
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
        this.loadOverview();
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


