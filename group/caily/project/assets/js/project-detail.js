const { createApp } = Vue;

createApp({
    data() {
        return {
            projectId: PROJECT_ID,
            project: null,
            department: null,
            members: [],
            tasks: [],
            comments: [],
            newComment: '',
            stats: {
                totalTasks: 0,
                completedTasks: 0,
                timeTracked: 0,
                totalDays: 0
            },
            statuses: [
                { value: 'draft', label: '下書き', color: 'secondary' },
                { value: 'open', label: 'オープン', color: 'info' },
                { value: 'confirming', label: '確認中', color: 'warning' },
                { value: 'in_progress', label: '進行中', color: 'primary' },
                { value: 'paused', label: '一時停止', color: 'warning' },
                { value: 'completed', label: '完了', color: 'success' },
                { value: 'cancelled', label: 'キャンセル', color: 'danger' }
            ],
            priorities: [
                { value: 'low', label: '低', color: 'secondary' },
                { value: 'medium', label: '中', color: 'primary' },
                { value: 'high', label: '高', color: 'warning' },
                { value: 'urgent', label: '緊急', color: 'danger' }
            ]
        }
    },
    computed: {
        // managers() {
            
        // }
    },
    methods: {
        async loadProject() {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getById&id=${this.projectId}`);
                this.project = response.data;
                if (this.project.department_id) {
                    this.loadDepartment();
                }
            } catch (error) {
                console.error('Error loading project:', error);
                alert('プロジェクトの読み込みに失敗しました。');
            }
        },
        async loadDepartment() {
            try {
                const response = await axios.get(`/api/index.php?model=department&method=get&id=${this.project.department_id}`);
                this.department = response.data;
            } catch (error) {
                console.error('Error loading department:', error);
            }
        },
        async loadMembers() {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getMembers&project_id=${this.projectId}`);
                this.members = response.data || [];
                this.managers = this.members.filter(m => m && m.role === 'manager');
                const managerIds = this.managers.map(m => m.user_id);
                this.members = this.members.filter(m => m && m.role === 'member' && !managerIds.includes(m.user_id));
            } catch (error) {
                console.error('Error loading members:', error);
            }
        },
        async loadTasks() {
            try {
                const response = await axios.get(`/api/index.php?model=task&method=list&project_id=${this.projectId}`);
                this.tasks = response.data || [];
                this.calculateStats();
            } catch (error) {
                console.error('Error loading tasks:', error);
            }
        },
        async loadComments() {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getComments&project_id=${this.projectId}`);
                this.comments = response.data || [];
            } catch (error) {
                console.error('Error loading comments:', error);
            }
        },
        calculateStats() {
            this.stats.totalTasks = this.tasks.length;
            this.stats.completedTasks = this.tasks.filter(t => t.status === 'completed').length;
            this.stats.timeTracked = this.tasks.reduce((sum, task) => sum + parseFloat(task.actual_hours || 0), 0);
            if (this.project && this.project.start_date) {
                const start = new Date(this.project.start_date);
                const end = this.project.actual_end_date ? new Date(this.project.actual_end_date) : new Date();
                const diffTime = Math.abs(end - start);
                this.stats.totalDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            }
        },
        async addComment() {
            if (!this.newComment.trim()) return;
            try {
                const formData = new FormData();
                formData.append('model', 'project');
                formData.append('method', 'addComment');
                formData.append('project_id', this.projectId);
                formData.append('content', this.newComment.trim());
                formData.append('user_id', USER_ID);
                await axios.post('/api/index.php', formData);
                this.newComment = '';
                this.loadComments();
            } catch (error) {
                console.error('Error adding comment:', error);
                alert('コメントの追加に失敗しました。');
            }
        },
        editProject() {
            window.location.href = `index.php?edit=${this.projectId}`;
        },
        async deleteProject() {
            if (!confirm('本当にこのプロジェクトを削除しますか？')) {
                return;
            }
            try {
                const formData = new FormData();
                formData.append('id', this.projectId);
                await axios.post('/api/index.php?model=project&method=delete', formData);
                alert('プロジェクトを削除しました。');
                window.location.href = 'index.php';
            } catch (error) {
                console.error('Error deleting project:', error);
                if (error.response?.data?.error) {
                    alert(error.response.data.error);
                } else {
                    alert('プロジェクトの削除に失敗しました。');
                }
            }
        },
        formatDate(date) {
            if (!date) return '-';
            return moment(date).format('YYYY/MM/DD');
        },
        formatDateTime(datetime) {
            if (!datetime) return '-';
            return moment(datetime).format('YYYY/MM/DD HH:mm');
        },
        formatCurrency(amount) {
            if (!amount) return '¥0';
            return '¥' + parseInt(amount).toLocaleString();
        },
        getStatusLabel(status) {
            const s = this.statuses.find(s => s.value === status);
            return s ? s.label : status;
        },
        getStatusBadgeClass(status) {
            const s = this.statuses.find(s => s.value === status);
            return `bg-${s?.color || 'secondary'}`;
        },
        getPriorityLabel(priority) {
            const p = this.priorities.find(p => p.value === priority);
            return p ? p.label : priority;
        },
        getPriorityBadgeClass(priority) {
            const p = this.priorities.find(p => p.value === priority);
            return `bg-${p?.color || 'secondary'}`;
        },
        getRoleLabel(role) {
            const roles = {
                'manager': 'マネージャー',
                'member': 'メンバー',
                'viewer': '閲覧者'
            };
            return roles[role] || role;
        },
        getRoleBadgeClass(role) {
            const roleColors = {
                'manager': 'bg-primary',
                'member': 'bg-info',
                'viewer': 'bg-secondary'
            };
            return roleColors[role] || 'bg-secondary';
        },
        async updateStatus() {
            try {
                const formData = new FormData();
                formData.append('id', this.projectId);
                formData.append('status', this.project.status);
                const response = await axios.post('/api/index.php?model=project&method=updateStatus', formData);
                if (response.data && response.data.success !== false) {
                    // Show success message
                    console.log('Status updated successfully');
                    // Close dropdown
                    const dropdownElement = document.querySelector('#statusDropdown');
                    if (dropdownElement) {
                        const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                        if (dropdown) {
                            dropdown.hide();
                        }
                    }
                } else {
                    // If server returned error, show error message
                }
            } catch (error) {
                console.error('Error updating status:', error);
                showMessage('ステータスの更新に失敗しました。', true);
            }
        },
        selectStatus(status) {
            this.project.status = status;
            this.updateStatus();
        },
        getStatusButtonClass(status) {
            const s = this.statuses.find(s => s.value === status);
            return `btn-${s?.color || 'secondary'}`;
        },
        getPriorityButtonClass(priority) {
            const p = this.priorities.find(p => p.value === priority);
            return `btn-${p?.color || 'secondary'}`;
        },
        selectPriority(priority) {
            this.project.priority = priority;
            this.updatePriority();
        },
        async updatePriority() {
            try {
                const formData = new FormData();
                formData.append('id', this.projectId);
                formData.append('priority', this.project.priority);
                const response = await axios.post('/api/index.php?model=project&method=updatePriority', formData);
                if (response.data && response.data.success !== false) {
                    // Show success message
                    console.log('Priority updated successfully');
                    // Close dropdown
                    const dropdownElement = document.querySelector('#priorityDropdown');
                    if (dropdownElement) {
                        const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                        if (dropdown) {
                            dropdown.hide();
                        }
                    }
                } else {
                    // If server returned error, show error message
                }
            } catch (error) {
                console.error('Error updating priority:', error);
                showMessage('優先度の更新に失敗しました。', true);
            }
        },
        getAvatarSrc(member) {
            // Trả về đường dẫn ảnh từ user_image, fallback nếu không có
            return member.user_image || '';
        },
        handleAvatarError(member) {
            member.avatarError = true;
        },
        getInitials(name) {
            if (!name) return '?';
            // Check if name contains Japanese characters
            const hasJapanese = /[\u3040-\u309f\u30a0-\u30ff\u4e00-\u9faf]/.test(name);
            if (hasJapanese) {
                // For Japanese names, take first 2 characters
                return name.substring(0, 2);
            } else {
                // For English names, take first letter of each word
                const initials = name.split(' ').map(n => n.charAt(0)).join('');
                return initials.toUpperCase();
            }
        },
        initTooltips() {
            // Dispose previous tooltips to avoid duplicates
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(el => {
                if (el._tooltipInstance) {
                    el._tooltipInstance.dispose();
                }
                el._tooltipInstance = new bootstrap.Tooltip(el);
            });
        }
    },
    mounted() {
        this.loadProject();
        this.loadMembers();
        this.loadTasks();
        this.loadComments();
        this.$nextTick(() => {
            this.initTooltips();
        });
    },
    updated() {
        this.$nextTick(() => {
            this.initTooltips();
        });
    }
}).mount('#app'); 