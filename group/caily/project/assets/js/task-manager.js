const { createApp } = Vue;

const TaskApp = createApp({
    data() {
        return {
            projectId: null,
            projectInfo: {},
            tasks: [],
            taskTable: null,
            selectedTask: null,
            taskForm: {
                title: '',
                description: '',
                status: 'new',
                priority: 'medium',
                start_date: '',
                due_date: '',
                assigned_to: null,
                parent_id: null,
                category_id: null,
                estimated_hours: 0,
                progress: 0
            },
            editingTask: null,
            users: [],
            categories: [],
            stats: {
                total: 0,
                completed: 0,
                overdue: 0
            },
            taskPriorities: [
                { value: 'low', label: '低', color: 'secondary' },
                { value: 'medium', label: '中', color: 'primary' },
                { value: 'high', label: '高', color: 'warning' },
                { value: 'urgent', label: '緊急', color: 'danger' }
            ],
            taskStatuses: [
                { value: 'todo', label: '未開始', color: 'secondary' },
                { value: 'in-progress', label: '進行中', color: 'primary' },
                { value: 'confirming', label: '確認中', color: 'warning' },
                { value: 'paused', label: '一時停止', color: 'warning' },
                { value: 'completed', label: '完了', color: 'success' },
                { value: 'cancelled', label: 'キャンセル', color: 'danger' }
            ],
            filterStatus: '',
            filterPriority: '',
            filterDueDate: '',
            addingTaskInline: false,
            inlineTasks: [],
            newTaskInline: {
                title: '',
                priority: 'medium',
                status: 'todo',
                due_date: '',
                assigned_to: ''
            },
            projectMembers: [],
            projectManagers: [],
            showMemberModal: false,
            assigneeModal: {
                show: false,
                idx: null,
                selected: []
            },
            editingInlineId: null,
        }
    },
    
    computed: {
        availableParentTasks() {
            if (!this.editingTask) {
                return this.tasks.filter(t => !t.parent_id);
            }
            // Không cho phép task là parent của chính nó hoặc con của nó
            return this.tasks.filter(t => 
                !t.parent_id && 
                t.id !== this.editingTask.id &&
                !this.isDescendant(t.id, this.editingTask.id)
            );
        },
        filteredTasks() {
            return this.tasks.filter(task => {
                let match = true;
                if (this.filterStatus && task.status !== this.filterStatus) match = false;
                if (this.filterPriority && task.priority !== this.filterPriority) match = false;
                //if (this.filterDueDate && task.due_date !== this.filterDueDate) match = false;
                return match;
            });
        }
    },
    
    mounted() {
        // Lấy project ID từ URL
        const urlParams = new URLSearchParams(window.location.search);
        this.projectId = urlParams.get('project_id');
        
        if (!this.projectId) {
            alert('プロジェクトIDが指定されていません。');
            window.location.href = 'index.php';
            return;
        }
        
        this.loadProjectInfo();
        this.loadTasks();
        this.loadUsers();
        this.loadCategories();
        this.initializeDataTable();
        this.loadProjectMembers();
        this.$nextTick(() => {
            this.initFlatpickr();
            this.initSortable();
        });
    },
    
    updated() {
        this.$nextTick(() => {
            this.initFlatpickr();
        });
    },
    
    methods: {
        testClick() {
            console.log('Click event works!');
        },
        async loadProjectInfo() {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getById&id=${this.projectId}`);
                this.projectInfo = response.data;
            } catch (error) {
                console.error('Error loading project info:', error);
            }
        },
        
        async loadTasks() {
            try {
                const response = await axios.get(`/api/index.php?model=task&method=list&project_id=${this.projectId}&include_subtasks=1`);
                this.tasks = response.data || [];
                //this.updateDataTable();
            } catch (error) {
                console.error('Error loading tasks:', error);
                this.showMessage('タスクの読み込みに失敗しました。', true);
            }
        },
        
        async loadUsers() {
            try {
                const response = await axios.get('/api/index.php?model=user&method=getList');
                this.users = response.data.list || [];
            } catch (error) {
                console.error('Error loading users:', error);
            }
        },
        
        async loadCategories() {
            try {
                const response = await axios.get('/api/index.php?model=category&method=list');
                this.categories = response.data || [];
            } catch (error) {
                console.error('Error loading categories:', error);
            }
        },
        
        initializeDataTable() {
            const self = this;
            this.taskTable = $('#taskTable').DataTable({
                data: [],
                columns: [
                    {
                        data: 'title',
                        render: function(data, type, row) {
                            let indent = '';
                            if (row.parent_id) {
                                indent = '<span style="margin-left: 20px;">↳ </span>';
                            }
                            return `${indent}<a href="#" class="task-link" data-id="${row.id}">${data}</a>`;
                        }
                    },
                    {
                        data: 'assigned_to_name',
                        render: function(data) {
                            return data || '-';
                        }
                    },
                    {
                        data: 'priority',
                        render: function(data) {
                            const priority = self.taskPriorities.find(p => p.value === data);
                            return `<span class="badge bg-${priority?.color || 'secondary'}">${priority?.label || data}</span>`;
                        }
                    },
                    {
                        data: 'status',
                        render: function(data) {
                            const status = self.taskStatuses.find(s => s.value === data);
                            return `<span class="badge bg-${status?.color || 'secondary'}">${status?.label || data}</span>`;
                        }
                    },
                    {
                        data: 'progress',
                        render: function(data) {
                            const color = data === 100 ? 'success' : 'primary';
                            return `<div class="progress" style="width: 80px;">
                                        <div class="progress-bar bg-${color}" style="width: ${data}%"></div>
                                    </div>
                                    <small>${data}%</small>`;
                        }
                    },
                    {
                        data: null,
                        render: function(data, type, row) {
                            return `<div class="btn-group btn-group-sm">
                                        <button class="btn btn-primary btn-edit-task" data-id="${row.id}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-danger btn-delete-task" data-id="${row.id}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>`;
                        }
                    }
                ],
                language: {
                    search: "検索:",
                    lengthMenu: "_MENU_ 件表示",
                    info: " _TOTAL_ 件中 _START_ から _END_ まで表示",
                    paginate: {
                        first: "先頭",
                        previous: "前",
                        next: "次",
                        last: "最終"
                    },
                    emptyTable: "タスクがありません"
                },
                order: [[0, 'asc']],
                pageLength: 25
            });
            
            // イベントハンドラー
            $('#taskTable').on('click', '.task-link', (e) => {
                e.preventDefault();
                const id = $(e.target).data('id');
                const task = this.tasks.find(t => t.id == id);
                if (task) {
                    this.selectedTask = task;
                }
            });
            
            $('#taskTable').on('click', '.btn-edit-task', (e) => {
                const id = $(e.target).closest('button').data('id');
                const task = this.tasks.find(t => t.id == id);
                if (task) {
                    this.editTask(task);
                }
            });
            
            $('#taskTable').on('click', '.btn-delete-task', (e) => {
                const id = $(e.target).closest('button').data('id');
                this.deleteTask(id);
            });
        },
        
        updateDataTable() {
            if (this.taskTable) {
                // Sắp xếp tasks: parent tasks trước, sau đó subtasks
                const sortedTasks = [];
                const parentTasks = this.tasks.filter(t => !t.parent_id);
                
                parentTasks.forEach(parent => {
                    sortedTasks.push(parent);
                    const subtasks = this.tasks.filter(t => t.parent_id == parent.id);
                    sortedTasks.push(...subtasks);
                });
                
                this.taskTable.clear();
                this.taskTable.rows.add(sortedTasks);
                this.taskTable.draw();
            }
        },
        
        openNewTaskModal() {
            this.inlineTasks.push({
                title: '',
                priority: 'medium',
                status: 'todo',
                due_date: '',
                progress: 0,
                assignees: []
            });
            this.$nextTick(() => {
                const firstInput = document.querySelector('.inline-task-input');
                if (firstInput) firstInput.focus();
                document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(el => {
                    if (el._dropdownInstance) {
                        el._dropdownInstance.dispose();
                    }
                    el._dropdownInstance = new (window.bootstrap ? window.bootstrap.Dropdown : bootstrap.Dropdown)(el);
                });
            });
        },
        
        editTask(task) {
            this.editingTask = task;
            this.taskForm = {
                title: task.title || '',
                description: task.description || '',
                status: task.status || 'new',
                priority: task.priority || 'medium',
                start_date: task.start_date || '',
                due_date: task.due_date || '',
                assigned_to: task.assigned_to || null,
                parent_id: task.parent_id || null,
                category_id: task.category_id || null,
                estimated_hours: task.estimated_hours || 0,
                progress: task.progress || 0,
                project_id: this.projectId
            };
            const modal = new bootstrap.Modal(document.getElementById('taskModal'));
            modal.show();
        },
        
        async saveTask() {
            alert(true);
            // Validate required fields
            if (!this.taskForm.title || !this.taskForm.title.trim()) {
                this.showMessage('タスク名は必須です。', true);
                return;
            }
            if (!this.taskForm.assigned_to) {
                this.showMessage('担当者は必須です。', true);
                return;
            }
            if (!this.taskForm.due_date || !this.taskForm.due_date.trim()) {
                this.showMessage('期限日は必須です。', true);
                return;
            }
            try {
                const formData = new FormData();
                formData.append('model', 'task');
                formData.append('method', this.editingTask ? 'edit' : 'add');
                if (this.editingTask) {
                    formData.append('id', this.editingTask.id);
                }
                Object.keys(this.taskForm).forEach(key => {
                    if (this.taskForm[key] !== null && this.taskForm[key] !== '') {
                        formData.append(key, this.taskForm[key]);
                    }
                });
                formData.append('project_id', this.projectId);
                const response = await axios.post('/api/index.php', formData);
                if (response.data && response.data.success !== false) {
                    this.showMessage(this.editingTask ? 'タスクを更新しました。' : 'タスクを作成しました。');
                    bootstrap.Modal.getInstance(document.getElementById('addTaskModal')).hide();
                    this.loadTasks();
                    this.resetTaskForm();
                } else {
                    this.showMessage(response.data && response.data.error ? response.data.error : 'タスクの保存に失敗しました。', true);
                }
            } catch (error) {
                console.error('Error saving task:', error);
                this.showMessage(error.response?.data?.error || 'タスクの保存に失敗しました。', true);
            }
        },
        
        async deleteTask(id) {
            if (!confirm('本当にこのタスクを削除しますか？')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('model', 'task');
                formData.append('method', 'delete');
                formData.append('id', id);
                
                const response = await axios.post('/api/index.php', formData);
                
                if (response.data) {
                    this.showMessage('タスクを削除しました。');
                    this.loadTasks();
                    if (this.selectedTask && this.selectedTask.id == id) {
                        this.selectedTask = null;
                    }
                }
            } catch (error) {
                console.error('Error deleting task:', error);
                if (error.response?.data?.error) {
                    this.showMessage(error.response.data.error, true);
                } else {
                    this.showMessage('タスクの削除に失敗しました。', true);
                }
            }
        },
        
        async updateTaskStatus(task, newStatus = null) {
            console.log('updateTaskStatus called with task:', task, 'and newStatus:', newStatus);
            const targetTask = task;
            if (!targetTask) return;
            
            const statusToSet = newStatus !== null ? newStatus : targetTask.status;
            
            try {
                const formData = new FormData();
                formData.append('id', targetTask.id);
                formData.append('status', statusToSet);
                
                const response = await axios.post(
                    '/api/index.php?model=task&method=updateStatus',
                    formData
                );
                
                if (response.data) {
                    this.showMessage('ステータスを更新しました。');
                }
            } catch (error) {
                console.error('Error updating status:', error);
                this.showMessage('ステータスの更新に失敗しました', true);
            }
        },
        
        async updateTaskProgress(task = null) {
            const targetTask = task || this.selectedTask;
            if (!targetTask) return;
            
            try {
                const formData = new FormData();
                formData.append('id', targetTask.id);
                formData.append('progress', targetTask.progress);
                
                const response = await axios.post(
                    '/api/index.php?model=task&method=updateProgress',
                    formData
                );
                
            } catch (error) {
                console.error('Error updating progress:', error);
                this.showMessage('進捗の更新に失敗しました', true);
            }
        },
        
        resetTaskForm() {
            this.taskForm = {
                title: '',
                description: '',
                status: 'new',
                priority: 'medium',
                start_date: '',
                due_date: '',
                assigned_to: null,
                parent_id: null,
                category_id: null,
                estimated_hours: 0,
                progress: 0
            };
        },
        
        hasSubtasks(task) {
            return task && this.tasks.some(t => t.parent_id == task.id);
        },
        
        isDescendant(taskId, potentialAncestorId) {
            const task = this.tasks.find(t => t.id == taskId);
            if (!task || !task.parent_id) return false;
            if (task.parent_id == potentialAncestorId) return true;
            return this.isDescendant(task.parent_id, potentialAncestorId);
        },
        
        formatDate(date) {
            if (!date) return '-';
            return moment(date).format('YYYY/MM/DD HH:mm');
        },
        
        showMessage(message, isError = false) {
            // Simple alert for now
            showMessage(message, isError);
        },
        
        editTaskInline(task) {
            // Convert task to inline editable format
            const inlineTask = {
                id: task.id,
                title: task.title,
                priority: task.priority,
                due_date: task.due_date,
                assignees: task.assigned_to ? task.assigned_to.split(',') : [],
                status: task.status,
                progress: task.progress || 0
            };
            this.editingInlineId = task.id;
            this.inlineTasks.push(inlineTask);
            // Remove the task from filteredTasks temporarily
            this.tasks = this.tasks.filter(t => t.id !== task.id);
        },
        
        async saveTaskInline(idx) {
            const inlineTask = this.inlineTasks[idx];
            // Validate required fields
            if (!inlineTask.title || !inlineTask.title.trim()) {
                this.showMessage('タスク名は必須です。', true);
                return;
            }
            if (!inlineTask.assignees || !inlineTask.assignees.length) {
                this.showMessage('担当者は必須です。', true);
                return;
            }
            if (!inlineTask.due_date || !inlineTask.due_date.trim()) {
                this.showMessage('期限日は必須です。', true);
                return;
            }
            try {
                const formData = new FormData();
                const method = inlineTask.id ? 'edit' : 'add';
                if (inlineTask.id) {
                    formData.append('id', inlineTask.id);
                }
                formData.append('project_id', this.projectId);
                formData.append('title', inlineTask.title);
                formData.append('priority', inlineTask.priority);
                formData.append('due_date', inlineTask.due_date);
                formData.append('assigned_to', inlineTask.assignees.join(','));
                formData.append('status', inlineTask.status);
                formData.append('progress', inlineTask.progress);

                const response = await axios.post('/api/index.php?model=task&method=' + method, formData);
                if (response.data.status == 'success') {
                    this.showMessage(inlineTask.id ? 'タスクを更新しました。' : 'タスクを追加しました。');
                    await this.loadTasks();
                    this.inlineTasks.splice(idx, 1);
                    this.editingInlineId = null;
                } else {
                    this.showMessage(response.data.message || 'エラーが発生しました。', true);
                }
            } catch (error) {
                console.error('Error saving task:', error);
                this.showMessage('エラーが発生しました。', true);
            }
        },
        
        cancelTaskInline(idx) {
            const inlineTask = this.inlineTasks[idx];
            if (inlineTask.id) {
                // If canceling an edit, restore the original task
                this.loadTasks();
            }
            this.inlineTasks.splice(idx, 1);
            this.editingInlineId = null;
        },
        
        getPriorityLabel(priority) {
            const p = this.taskPriorities.find(p => p.value === priority);
            return p ? p.label : priority;
        },
        
        getPriorityButtonClass(priority) {
            const p = this.taskPriorities.find(p => p.value === priority);
            return `bg-label-${p?.color || 'secondary'}`;
        },
        
        getStatusLabel(status) {
            const s = this.taskStatuses.find(s => s.value === status);
            return s ? s.label : status;
        },
        
        getStatusButtonClass(status) {
            const s = this.taskStatuses.find(s => s.value === status);
            return `btn-${s?.color || 'secondary'}`;
        },
        
        updateTaskPriority(task, value) {
            task.priority = value;
            // Gọi API cập nhật nếu cần
        },
        
        updateTaskStatus(task, value) {
            task.status = value;
            // Gọi API cập nhật nếu cần
        },
        
        closeDropdown(event) {
            const dropdownMenu = event.target.closest('.dropdown-menu');
            if (dropdownMenu) {
                const dropdown = window.bootstrap
                    ? window.bootstrap.Dropdown.getOrCreateInstance(dropdownMenu.previousElementSibling)
                    : bootstrap.Dropdown.getOrCreateInstance(dropdownMenu.previousElementSibling);
                dropdown.hide();
            }
        },
        
        async loadProjectMembers() {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getMembers&project_id=${this.projectId}`);
                let members = response.data || [];
                // Lọc trùng user_id
                const seen = new Set();
                this.projectMembers = members.filter(m => {
                    if (!m || seen.has(m.user_id)) return false;
                    seen.add(m.user_id);
                    return true;
                });
            } catch (error) {
                console.error('Error loading project members:', error);
            }
        },
        
        getAvatarSrc(member) {
            if(member){
                return member.user_image || '';
            }
            return '';
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
        
        openMemberModal() {
            this.showMemberModal = true;
        },
        
        closeMemberModal() {
            this.showMemberModal = false;
        },
        
        async addMember(userId) {
            // Gọi API thêm member vào project
            // Sau khi thành công, reload lại projectMembers
            await this.loadProjectMembers();
        },
        
        async removeMember(userId) {
            // Gọi API xóa member khỏi project
            // Sau khi thành công, reload lại projectMembers
            await this.loadProjectMembers();
        },
        
        openAssigneeModal(idx) {
            this.assigneeModal.idx = idx;
            this.assigneeModal.selected = [...(this.inlineTasks[idx].assignees || [])];
            this.assigneeModal.show = true;
        },
        
        closeAssigneeModal() {
            this.assigneeModal.show = false;
        },
        
        confirmAssigneeModal() {
            if (this.assigneeModal.idx !== null) {
                this.inlineTasks[this.assigneeModal.idx].assignees = [...this.assigneeModal.selected];
            }
            this.closeAssigneeModal();
        },
        
        toggleAssignee(userId) {
            const idx = this.assigneeModal.selected.indexOf(userId);
            if (idx === -1) {
                this.assigneeModal.selected.push(userId);
            } else {
                this.assigneeModal.selected.splice(idx, 1);
            }
        },
        
        assigneeNames(userIds) {
            return userIds.map(userId => {
                const m = this.projectMembers.find(u => u.user_id == userId);
                return m ? m.user_name : '';
            }).join(', ');
        },
        
        initFlatpickr() {
            if (window.flatpickr) {
                document.querySelectorAll('.datetimepicker').forEach(el => {
                    if (el._flatpickr) {
                        el._flatpickr.destroy();
                    }
                    window.flatpickr(el, {
                        enableTime: true,
                        dateFormat: 'Y/m/d H:i',
                        time_24hr: true,
                        allowInput: true,
                        onChange: (selectedDates, dateStr) => {
                            const vueModel = el.getAttribute('v-model');
                            if (vueModel) {
                                // Không dùng được, nên sẽ đồng bộ thủ công ở template
                            }
                        }
                    });
                });
            }
        },
        
        initSortable() {
            const taskList = document.querySelector('.task-list');
            if (taskList) {
                Sortable.create(taskList, {
                    animation: 150,
                    handle: '.drag-handle',
                    onEnd: (evt) => {
                        console.log('Drag ended');
                        const taskElements = Array.from(taskList.children);
                        console.log('Task elements:', taskElements);
                        const newOrder = taskElements
                            .map(el => {
                                const id = el.getAttribute('data-id');
                                console.log('Element:', el, 'Task ID:', id);
                                return id;
                            })
                            .filter(id => id !== null);
                        console.log('New order:', newOrder);
                        
                        if (newOrder.length > 0) {
                            console.log('Calling updateTaskOrder');
                            this.updateTaskOrder(newOrder);
                        }
                    }
                });
            }
        },
        
        async updateTaskOrder(taskIds) {
            try {
                const formData = new FormData();
                formData.append('task_ids', JSON.stringify(taskIds));
                formData.append('project_id', this.projectId);
                const response = await axios.post(
                    '/api/index.php?model=task&method=updateOrder',
                    formData
                );
                
            } catch (error) {
                console.error('Error updating task order:', error);
                this.showMessage('タスク順序の更新に失敗しました', true);
            }
        }
    }
});

// Mount the app when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    TaskApp.mount('#app');
});
