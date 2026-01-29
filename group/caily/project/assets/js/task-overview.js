const { createApp } = Vue;

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
                myTask: defaultMyTask // Default to true if not project manager
            },
            taskStatuses: [
                { value: 'todo', label: '未開始', color: 'secondary' },
                { value: 'in-progress', label: '進行中', color: 'primary' },
                { value: 'confirming', label: '確認中', color: 'warning' },
                { value: 'paused', label: '一時停止', color: 'warning' },
                { value: 'completed', label: '完了', color: 'success' },
                { value: 'cancelled', label: 'キャンセル', color: 'danger' }
            ],
            taskPriorities: [
                { value: 'low', label: '低', color: 'secondary' },
                { value: 'medium', label: '中', color: 'primary' },
                { value: 'high', label: '高', color: 'warning' },
                { value: 'urgent', label: '緊急', color: 'danger' }
            ]
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
        // Danh sách task sau khi áp bộ lọc (department / team / user / excludeCompleted)
        filteredTasks() {
            return this.tasks.filter(task => this.passesTaskFilters(task));
        }
    },
    methods: {
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

            return true;
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
            return s ? s.label : status;
        },
        getStatusColor(status) {
            const s = this.taskStatuses.find(s => s.value === status);
            return s ? s.color : 'secondary';
        },
        getPriorityLabel(priority) {
            const p = this.taskPriorities.find(p => p.value === priority);
            return p ? p.label : priority;
        },
        getPriorityColor(priority) {
            const p = this.taskPriorities.find(p => p.value === priority);
            return p ? p.color : 'secondary';
        },
        formatDate(dateString) {
            if (!dateString) return '-';
            return moment(dateString).format('M月D日 H:mm');
        },
        isTaskOverdue(task) {
            if (!task || !task.due_date) return false;
            if (task.status === 'completed' || task.status === 'cancelled') return false;
            const due = moment.tz(task.due_date, 'Asia/Tokyo');
            return moment().tz('Asia/Tokyo').isAfter(due, 'minute');
        },
        getOverdueTooltip(task) {
            if (!this.isTaskOverdue(task)) return '';
            const due = moment.tz(task.due_date, 'Asia/Tokyo');
            const now = moment().tz('Asia/Tokyo');
            const duration = moment.duration(now.diff(due));
            const hours = Math.floor(duration.asHours());
            const minutes = Math.floor(duration.asMinutes()) % 60;
            if (hours > 0) {
                return `期限切れ: ${hours}時間${minutes}分`;
            }
            return `期限切れ: ${minutes}分`;
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
            return 'タスクの期限がプロジェクトの期限を超えています';
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
                    if (!bootstrap.Tooltip.getInstance(el)) {
                        new bootstrap.Tooltip(el);
                    }
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
        this.loadOverview();
    }
}).mount('#app');


