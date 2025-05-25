// Task Manager Vue Component
const TaskManager = {
    data() {
        return {
            tasks: [],
            selectedTask: null,
            editingTask: null,
            taskForm: {
                title: '',
                description: '',
                status: 'new',
                priority: 'medium',
                assigned_to: '',
                due_date: '',
                start_date: '',
                estimated_hours: 0,
                parent_id: ''
            },
            users: [],
            taskStatuses: [
                { value: 'new', label: '新規' },
                { value: 'in_progress', label: '進行中' },
                { value: 'review', label: 'レビュー中' },
                { value: 'completed', label: '完了' }
            ],
            taskPriorities: [
                { value: 'low', label: '低' },
                { value: 'medium', label: '中' },
                { value: 'high', label: '高' },
                { value: 'urgent', label: '緊急' }
            ],
            dataTable: null,
            projectId: new URLSearchParams(window.location.search).get('project_id')
        }
    },
    computed: {
        availableParentTasks() {
            if (!this.editingTask) {
                return this.tasks.filter(task => !task.parent_id);
            }
            return this.tasks.filter(task => 
                task.id !== this.editingTask.id && 
                !this.isChildOf(task, this.editingTask.id)
            );
        }
    },
    methods: {
        async loadTasks() {
            try {
                const response = await axios.get(`api/index.php?model=task&method=list&project_id=${this.projectId}`);
                if (response.data && response.data.data) {
                    this.tasks = Array.isArray(response.data.data) ? response.data.data : [];
                    this.updateDataTable();
                } else {
                    this.tasks = [];
                    this.updateDataTable();
                }
            } catch (error) {
                console.error('Error loading tasks:', error);
                this.tasks = [];
                this.updateDataTable();
            }
        },
        async loadUsers() {
            try {
                const response = await axios.get('api/index.php?model=user&method=list');
                if (response.data && response.data.data) {
                    this.users = Array.isArray(response.data.data) ? response.data.data : [];
                }
            } catch (error) {
                console.error('Error loading users:', error);
                this.users = [];
            }
        },
        updateDataTable() {
            if (this.dataTable) {
                this.dataTable.destroy();
            }

            this.dataTable = $('#taskTable').DataTable({
                data: this.tasks,
                responsive: true,
                columns: [
                    { 
                        data: 'title',
                        render: (data, type, row) => {
                            if (type === 'display') {
                                return `<div class="d-flex align-items-center">
                                    ${row.parent_id ? '<i class="bi bi-arrow-return-right me-2"></i>' : ''}
                                    <span>${data}</span>
                                </div>`;
                            }
                            return data;
                        }
                    },
                    { 
                        data: 'assigned_to_name',
                        defaultContent: '-'
                    },
                    { 
                        data: 'priority',
                        render: (data) => {
                            const priority = this.taskPriorities.find(p => p.value === data);
                            return priority ? priority.label : data;
                        }
                    },
                    { 
                        data: 'status',
                        render: (data) => {
                            const status = this.taskStatuses.find(s => s.value === data);
                            return status ? status.label : data;
                        }
                    },
                    { 
                        data: 'progress',
                        render: (data) => {
                            return `<div class="progress" style="height: 20px;">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: ${data}%;" 
                                     aria-valuenow="${data}" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                    ${data}%
                                </div>
                            </div>`;
                        }
                    },
                    {
                        data: null,
                        render: (data, type, row) => {
                            return `<div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary" onclick="app.viewTask(${row.id})">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="app.editTask(${row.id})">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="app.deleteTask(${row.id})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>`;
                        }
                    }
                ],
                order: [[0, 'asc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/ja.json'
                }
            });
        },
        formatDate(date) {
            return moment(date).format('YYYY/MM/DD');
        },
        hasSubtasks(task) {
            return this.tasks.some(t => t.parent_id === task.id);
        },
        isChildOf(task, parentId) {
            if (!task.parent_id) return false;
            if (task.parent_id === parentId) return true;
            const parent = this.tasks.find(t => t.id === task.parent_id);
            return parent ? this.isChildOf(parent, parentId) : false;
        },
        openNewTaskModal() {
            this.editingTask = null;
            this.taskForm = {
                title: '',
                description: '',
                status: 'new',
                priority: 'medium',
                assigned_to: '',
                due_date: '',
                start_date: '',
                estimated_hours: 0,
                parent_id: ''
            };
            $('#taskModal').modal('show');
        },
        async viewTask(taskId) {
            try {
                const response = await axios.get(`api/index.php?model=task&method=getById&id=${taskId}`);
                if (response.data && response.data.data) {
                    this.selectedTask = response.data.data;
                }
            } catch (error) {
                console.error('Error loading task details:', error);
                alert('タスクの詳細を読み込めませんでした。');
            }
        },
        async editTask(taskId) {
            try {
                const response = await axios.get(`api/index.php?model=task&method=getById&id=${taskId}`);
                if (response.data && response.data.data) {
                    this.editingTask = response.data.data;
                    this.taskForm = { ...response.data.data };
                    $('#taskModal').modal('show');
                }
            } catch (error) {
                console.error('Error loading task for edit:', error);
                alert('タスクの編集データを読み込めませんでした。');
            }
        },
        async saveTask() {
            try {
                const data = {
                    ...this.taskForm,
                    project_id: this.projectId
                };

                let response;
                if (this.editingTask) {
                    response = await axios.post(`api/index.php?model=task&method=edit&id=${this.editingTask.id}`, data);
                } else {
                    response = await axios.post('api/index.php?model=task&method=add', data);
                }

                if (response.data && response.data.success) {
                    $('#taskModal').modal('hide');
                    await this.loadTasks();
                    alert(this.editingTask ? 'タスクを更新しました。' : 'タスクを作成しました。');
                } else {
                    throw new Error(response.data.message || '保存に失敗しました。');
                }
            } catch (error) {
                console.error('Error saving task:', error);
                alert(error.message || 'タスクの保存に失敗しました。');
            }
        },
        async deleteTask(taskId) {
            if (!confirm('このタスクを削除してもよろしいですか？')) {
                return;
            }

            try {
                const response = await axios.post(`api/index.php?model=task&method=delete&id=${taskId}`);
                if (response.data && response.data.success) {
                    await this.loadTasks();
                    alert('タスクを削除しました。');
                } else {
                    throw new Error(response.data.message || '削除に失敗しました。');
                }
            } catch (error) {
                console.error('Error deleting task:', error);
                alert(error.message || 'タスクの削除に失敗しました。');
            }
        },
        async updateTaskStatus() {
            try {
                const response = await axios.post(
                    `api/index.php?model=task&method=updateStatus&id=${this.selectedTask.id}`,
                    { status: this.selectedTask.status }
                );
                if (response.data && response.data.success) {
                    await this.loadTasks();
                } else {
                    throw new Error(response.data.message || 'ステータスの更新に失敗しました。');
                }
            } catch (error) {
                console.error('Error updating task status:', error);
                alert(error.message || 'タスクのステータス更新に失敗しました。');
            }
        },
        async updateTaskProgress() {
            try {
                const response = await axios.post(
                    `api/index.php?model=task&method=updateProgress&id=${this.selectedTask.id}`,
                    { progress: this.selectedTask.progress }
                );
                if (response.data && response.data.success) {
                    await this.loadTasks();
                } else {
                    throw new Error(response.data.message || '進捗の更新に失敗しました。');
                }
            } catch (error) {
                console.error('Error updating task progress:', error);
                alert(error.message || 'タスクの進捗更新に失敗しました。');
            }
        }
    },
    mounted() {
        this.loadTasks();
        this.loadUsers();
    }
};

// Create Vue app
const app = Vue.createApp(TaskManager).mount('#app'); 