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
            taskStatuses: [
                { value: 'new', label: '新規', color: 'secondary' },
                { value: 'in_progress', label: '進行中', color: 'primary' },
                { value: 'review', label: 'レビュー中', color: 'warning' },
                { value: 'completed', label: '完了', color: 'success' }
            ],
            taskPriorities: [
                { value: 'low', label: '低', color: 'secondary' },
                { value: 'medium', label: '中', color: 'primary' },
                { value: 'high', label: '高', color: 'warning' },
                { value: 'urgent', label: '緊急', color: 'danger' }
            ]
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
    },
    
    methods: {
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
                this.updateDataTable();
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
            this.editingTask = null;
            this.resetTaskForm();
            this.taskForm.project_id = this.projectId;
            const modal = new bootstrap.Modal(document.getElementById('taskModal'));
            modal.show();
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
                
                if (response.data) {
                    this.showMessage(this.editingTask ? 'タスクを更新しました。' : 'タスクを作成しました。');
                    bootstrap.Modal.getInstance(document.getElementById('taskModal')).hide();
                    this.loadTasks();
                    this.resetTaskForm();
                }
            } catch (error) {
                console.error('Error saving task:', error);
                this.showMessage('タスクの保存に失敗しました。', true);
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
        
        async updateTaskStatus() {
            if (!this.selectedTask) return;
            
            try {
                const formData = new FormData();
                formData.append('model', 'task');
                formData.append('method', 'updateStatus');
                formData.append('id', this.selectedTask.id);
                formData.append('status', this.selectedTask.status);
                
                const response = await axios.post('/api/index.php', formData);
                
                if (response.data) {
                    this.showMessage('ステータスを更新しました。');
                    this.loadTasks();
                }
            } catch (error) {
                console.error('Error updating task status:', error);
                this.showMessage('ステータスの更新に失敗しました。', true);
            }
        },
        
        async updateTaskProgress() {
            if (!this.selectedTask) return;
            
            try {
                const formData = new FormData();
                formData.append('model', 'task');
                formData.append('method', 'updateProgress');
                formData.append('id', this.selectedTask.id);
                formData.append('progress', this.selectedTask.progress);
                
                const response = await axios.post('/api/index.php', formData);
                
                if (response.data) {
                    this.showMessage('進捗を更新しました。');
                    this.loadTasks();
                }
            } catch (error) {
                console.error('Error updating task progress:', error);
                this.showMessage('進捗の更新に失敗しました。', true);
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
            return moment(date).format('YYYY/MM/DD');
        },
        
        showMessage(message, isError = false) {
            // Simple alert for now
            alert(message);
        }
    }
});

// Mount the app when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    TaskApp.mount('#app');
});
