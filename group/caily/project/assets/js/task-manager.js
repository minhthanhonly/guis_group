const { createApp } = Vue;

createApp({
    data() {
        return {
            tasks: [],
            users: [],
            selectedTask: null,
            editingTask: null,
            taskForm: {
                name: '',
                description: '',
                start_date: '',
                due_date: '',
                assignee_id: null,
                priority: 'medium',
                parent_id: null,
                progress: 0
            },
            expandedTasks: new Set(),
            taskModal: null,
            dataTable: null
        }
    },
    computed: {
        flattenedTasks() {
            const flattened = [];
            const flatten = (tasks, level = 0) => {
                tasks.forEach(task => {
                    const taskCopy = { ...task, level };
                    flattened.push(taskCopy);
                    if (task.subtasks && task.subtasks.length > 0) {
                        flatten(task.subtasks, level + 1);
                    }
                });
            };
            flatten(this.tasks);
            return flattened;
        },
        availableParentTasks() {
            const currentTaskId = this.editingTask ? this.editingTask.id : null;
            return this.tasks.filter(task => task.id !== currentTaskId);
        }
    },
    methods: {
        async loadData() {
            try {
                const response = await fetch('assets/js/sample-tasks.json');
                const data = await response.json();
                this.tasks = data.tasks;
                this.users = data.users;
                this.initializeDataTable();
            } catch (error) {
                console.error('Error loading data:', error);
                alert('データの読み込みに失敗しました。');
            }
        },
        initializeDataTable() {
            if (this.dataTable) {
                this.dataTable.destroy();
            }

            this.dataTable = $('#taskTable').DataTable({
                data: this.flattenedTasks,
                responsive: true,
                language: {
                },
                columns: [
                    {
                        data: 'name',
                        render: (data, type, row) => {
                            const padding = '&nbsp;'.repeat(row.level * 4);
                            const hasSubtasks = row.subtasks && row.subtasks.length > 0;
                            const icon = hasSubtasks ? '<i class="bi bi-folder"></i>' : '<i class="bi bi-file-text"></i>';
                            return `${padding}${icon} <a href="#" onclick="app.viewTask(${row.id})">${data}</a>`;
                        }
                    },
                    { 
                        data: 'assignee_name' 
                    },
                    {
                        data: 'priority',
                        render: (data) => {
                            const colors = {
                                'low': 'success',
                                'medium': 'warning',
                                'high': 'danger'
                            };
                            const texts = {
                                'low': '低',
                                'medium': '中',
                                'high': '高'
                            };
                            return `<span class="badge bg-${colors[data]}">${texts[data]}</span>`;
                        }
                    },
                    {
                        data: 'status',
                        render: (data) => {
                            const colors = {
                                'new': 'primary',
                                'in_progress': 'info',
                                'review': 'warning',
                                'completed': 'success'
                            };
                            const texts = {
                                'new': '新規',
                                'in_progress': '進行中',
                                'review': 'レビュー中',
                                'completed': '完了'
                            };
                            return `<span class="badge bg-${colors[data]}">${texts[data]}</span>`;
                        }
                    },
                    {
                        data: 'progress',
                        render: (data) => {
                            const color = data >= 100 ? 'bg-success' : 
                                        data >= 70 ? 'bg-info' :
                                        data >= 30 ? 'bg-warning' : 'bg-danger';
                            return `
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar ${color}" style="width: ${data}%" title="${data}%"></div>
                                </div>
                                <small class="text-muted">${data}%</small>
                            `;
                        }
                    },
                    {
                        data: null,
                        render: (data) => {
                            return `
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="app.editTask(${data.id})">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="app.deleteTask(${data.id})">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            `;
                        }
                    }
                ]
            });
        },
        hasSubtasks(task) {
            return task.subtasks && task.subtasks.length > 0;
        },
        openNewTaskModal() {
            this.editingTask = null;
            this.taskForm = {
                name: '',
                description: '',
                start_date: '',
                due_date: '',
                assignee_id: null,
                priority: 'medium',
                parent_id: null,
                progress: 0
            };
            const modal = new bootstrap.Modal(document.getElementById('taskModal'));
            modal.show();
        },
        viewTask(taskId) {
            const task = this.findTask(taskId);
            if (task) {
                this.selectedTask = task;
            }
        },
        editTask(taskId) {
            const task = this.findTask(taskId);
            if (task) {
                this.editingTask = task;
                this.taskForm = { ...task };
                const modal = new bootstrap.Modal(document.getElementById('taskModal'));
                modal.show();
            }
        },
        findTask(taskId) {
            const searchTask = (tasks) => {
                for (let task of tasks) {
                    if (task.id === taskId) return task;
                    if (task.subtasks) {
                        const found = searchTask(task.subtasks);
                        if (found) return found;
                    }
                }
                return null;
            };
            return searchTask(this.tasks);
        },
        saveTask() {
            if (this.editingTask) {
                // Update existing task
                const updateTask = (tasks) => {
                    for (let i = 0; i < tasks.length; i++) {
                        if (tasks[i].id === this.editingTask.id) {
                            tasks[i] = { ...tasks[i], ...this.taskForm };
                            return true;
                        }
                        if (tasks[i].subtasks && updateTask(tasks[i].subtasks)) {
                            return true;
                        }
                    }
                    return false;
                };
                updateTask(this.tasks);
            } else {
                // Add new task
                const newTask = {
                    ...this.taskForm,
                    id: Date.now(),
                    subtasks: []
                };
                
                if (newTask.parent_id) {
                    const addToParent = (tasks) => {
                        for (let task of tasks) {
                            if (task.id === newTask.parent_id) {
                                task.subtasks.push(newTask);
                                return true;
                            }
                            if (task.subtasks && addToParent(task.subtasks)) {
                                return true;
                            }
                        }
                        return false;
                    };
                    addToParent(this.tasks);
                } else {
                    this.tasks.push(newTask);
                }
            }
            
            this.initializeDataTable();
            const modal = bootstrap.Modal.getInstance(document.getElementById('taskModal'));
            modal.hide();
        },
        deleteTask(taskId) {
            if (!confirm('このタスクを削除してもよろしいですか？')) {
                return;
            }
            
            const removeTask = (tasks) => {
                for (let i = 0; i < tasks.length; i++) {
                    if (tasks[i].id === taskId) {
                        tasks.splice(i, 1);
                        return true;
                    }
                    if (tasks[i].subtasks && removeTask(tasks[i].subtasks)) {
                        return true;
                    }
                }
                return false;
            };
            
            removeTask(this.tasks);
            this.initializeDataTable();
            if (this.selectedTask && this.selectedTask.id === taskId) {
                this.selectedTask = null;
            }
        },
        updateTaskStatus() {
            const updateStatus = (tasks) => {
                for (let task of tasks) {
                    if (task.id === this.selectedTask.id) {
                        task.status = this.selectedTask.status;
                        return true;
                    }
                    if (task.subtasks && updateStatus(task.subtasks)) {
                        return true;
                    }
                }
                return false;
            };
            updateStatus(this.tasks);
            this.initializeDataTable();
        },
        updateTaskProgress() {
            const updateProgress = (tasks) => {
                for (let task of tasks) {
                    if (task.id === this.selectedTask.id) {
                        task.progress = parseInt(this.selectedTask.progress);
                        return true;
                    }
                    if (task.subtasks && updateProgress(task.subtasks)) {
                        return true;
                    }
                }
                return false;
            };
            updateProgress(this.tasks);
            this.initializeDataTable();
        },
        formatDate(date) {
            if (!date) return '';
            return new Date(date).toLocaleDateString('ja-JP');
        }
    },
    mounted() {
        this.loadData();
        window.app = this;
    }
}).mount('#app');
