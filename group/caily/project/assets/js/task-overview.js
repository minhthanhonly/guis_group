const { createApp } = Vue;

createApp({
    data() {
        return {
            loading: false,
            departments: [],
            teams: [],
            users: [],
            tasks: [],
            unassignedUsers: [],
            activeTab: 'tasks',
            filters: {
                department_id: '',
                team_id: '',
                user_id: '',
                // Mặc định loại bỏ completed để giảm tải
                excludeCompleted: true
            }
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
                if (this.filters.user_id) {
                    params.append('user_id', this.filters.user_id);
                }
                if (this.filters.excludeCompleted) {
                    params.append('exclude_completed', 1);
                }
                const response = await axios.get('/api/index.php?' + params.toString());
                console.log('API Response:', response);
                const data = response.data || {};
                console.log('Parsed data:', data);
                this.departments = data.departments || [];
                this.teams = data.teams || [];
                this.users = data.users || [];
                this.tasks = data.tasks || [];
                this.unassignedUsers = data.unassigned_users || [];
                console.log('Loaded:', {
                    departments: this.departments.length,
                    teams: this.teams.length,
                    users: this.users.length,
                    tasks: this.tasks.length,
                    unassignedUsers: this.unassignedUsers.length
                });
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
        }
    },
    mounted() {
        this.loadOverview();
    }
}).mount('#app');


