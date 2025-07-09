const { createApp } = Vue;

const TaskApp = createApp({
    components: {
        'comment-component': window.CommentComponent
    },
    data() {
        return {
            currentUserId: USER_AUTH_ID,
            projectId: null,
            permission: {},
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
                start_date: '',
                due_date: '',
                assigned_to: ''
            },
            projectMembers: [],
            projectManagers: [],
            showMemberModal: false,
            assigneeModal: {
                show: false,
                idx: null,
                selected: [],
                backupData: null
            },
            editingInlineId: null,
            // Offcanvas data
            taskComments: [], // Keep for compatibility, but not used
            taskActivities: [],
            taskLogs: [],
            quillEditor: null,
            // Comment component data
            currentUser: {
                userid: typeof USER_ID !== 'undefined' ? USER_ID : null,
                realname: typeof USER_NAME !== 'undefined' ? USER_NAME : 'User',
                user_image: typeof USER_IMAGE !== 'undefined' ? USER_IMAGE : null
            },
            taskCommentApiEndpoints: {
                getComments: '/api/index.php?model=task&method=getComments',
                addComment: '/api/index.php?model=task&method=addComment',
                toggleLike: '/api/index.php?model=task&method=toggleLike'
            },
            unreadComments: {}, // { taskId: count }
        }
    },
    
    computed: {
        sortedTaskLogs() {
            if (!this.taskLogs) return [];
            // Sắp xếp giảm dần theo thời gian
            return [...this.taskLogs].sort((a, b) => (b.time > a.time ? 1 : -1));
        },
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
        },
        displayTasks() {
            const filtered = this.filteredTasks;
            const result = [];
            
            for (let i = 0; i < filtered.length; i++) {
                const task = filtered[i];
                const inlineTask = this.inlineTasks.find(inline => inline.id === task.id);
                
                if (inlineTask) {
                    // Add inline task with edit mode flag
                    result.push({
                        ...inlineTask,
                        _isInlineEdit: true,
                        _inlineIndex: this.inlineTasks.indexOf(inlineTask)
                    });
                } else {
                    // Add normal task with indent calculation
                    const taskWithIndent = this.calculateIndent(task, i, filtered);
                    result.push(taskWithIndent);
                }
            }
            
            // Add new inline tasks (those without id)
            this.inlineTasks.forEach((inlineTask, index) => {
                if (!inlineTask.id) {
                    result.push({
                        ...inlineTask,
                        _isInlineEdit: true,
                        _inlineIndex: index
                    });
                }
            });
            
            return result;
        },
        taskStats() {
            const now = moment().tz('Asia/Tokyo');
            const total = this.tasks.length;
            const completed = this.tasks.filter(t => t.status === 'completed').length;

            // Overdue: completed => actual_end_date > due_date, not completed => now > due_date
            const overdue = this.tasks.filter(t => {
                if (!t.due_date) return false;
                const due = moment.tz(t.due_date, 'Asia/Tokyo');
                if (t.status === 'completed') {
                    if (!t.actual_end_date) return false;
                    return moment.tz(t.actual_end_date, 'Asia/Tokyo').isAfter(due, 'minute');
                } else {
                    return now.isAfter(due, 'minute');
                }
            }).length;

            return { total, completed, overdue };
        }
    },
    
    // Temporarily disable watcher to prevent data loss
    // watch: {
    //     // Watch for changes in inlineTasks to ensure data integrity
    //     inlineTasks: {
    //         handler(newTasks) {
    //             console.log('Watcher triggered for inlineTasks:', newTasks.length, 'tasks');
    //             newTasks.forEach((task, index) => {
    //                 if (task && typeof task === 'object') {
    //                     this.ensureTaskData(index);
    //                 }
    //             });
    //         },
    //         deep: true
    //     }
    // },
    
    mounted() {
        // Lấy project ID từ URL
        const urlParams = new URLSearchParams(window.location.search);
        this.projectId = urlParams.get('project_id');
        
        if (!this.projectId) {
            alert('プロジェクトIDが指定されていません。');
            window.location.href = 'index.php';
            return;
        }
        (async()=>{
            await this.loadPermission();
            if(!this.permission.is_member || (this.permission.rule && this.permission.rule.task_view != 1)){
                this.showMessage('権限がありません。', true);
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 1000);
                return;
            }
            await this.loadProjectInfo();
            await this.loadTasks();
            await this.loadProjectMembers();
        })();
        this.$nextTick(() => {
            this.initFlatpickr();
            this.initSortable();
            this.initTooltips();
        });
        
        // Add click outside listener to close dropdowns
        document.addEventListener('click', (event) => {
            // Don't close if clicking on dropdown toggle button
            if (event.target.closest('.dropdown-toggle')) {
                return;
            }
            // Close if clicking outside dropdown
            if (!event.target.closest('.dropdown')) {
                this.closeAllDropdowns();
            }
        });
        
        // Add upload progress event listeners
        window.addEventListener('uploadProgress', (event) => {
            const { progress, fileName } = event.detail;
            this.updateUploadProgress(fileName, progress);
        });
        
        window.addEventListener('uploadSuccess', (event) => {
            const { data, fileName } = event.detail;
            this.handleUploadSuccess(fileName, data);
        });
        
        window.addEventListener('uploadError', (event) => {
            const { error, fileName } = event.detail;
            this.handleUploadError(fileName, error);
        });
    },
    
    updated() {
        this.$nextTick(() => {
            this.initFlatpickr();
            this.initTooltips();
        });
    },
    

    
    methods: {
        onCommentAdded(event) {
            this.showNotification('コメントが追加されました', 'success');
        },

        showNotification(message, type = 'info') {
            // Use showMessage function if available, otherwise use alert
            showMessage(message, type === 'error');
        },
        
        onCommentError(event) {
            this.showNotification(event.message || 'エラーが発生しました', 'error');
        },

        onCommentMessage(event) {
            this.showNotification(event.message || 'エラーが発生しました', 'info');
        },
        updateTaskField(index, field, value) {
            if (this.inlineTasks[index]) {
                // Use Vue.set to ensure reactivity
                if (typeof Vue !== 'undefined' && Vue.set) {
                    Vue.set(this.inlineTasks[index], field, value);
                } else {
                    this.inlineTasks[index][field] = value;
                }
            }
        },
        
        calculateTaskPosition(inlineIndex) {
            // Get all display tasks (including inline tasks) to determine position
            const allDisplayTasks = this.displayTasks;
            const inlineTask = this.inlineTasks[inlineIndex];
            
            // Find the position where this inline task appears in the display
            const displayIndex = allDisplayTasks.findIndex(task => 
                task._isInlineEdit && task._inlineIndex === inlineIndex
            );
            
            if (displayIndex === -1) {
                // If not found in display, add at the end
                return this.tasks.length + 1;
            }
            
            // Calculate position based on display index
            // Consider existing tasks and their positions
            const existingTasks = this.tasks;
            const maxPosition = existingTasks.length > 0 ? 
                Math.max(...existingTasks.map(t => t.position || 0)) : 0;
            
            // Insert at the display position
            return displayIndex + 1;
        },
        
        testUpdateStatus() {
            if (this.tasks.length > 0) {
                const firstTask = this.tasks[0];
                console.log('Testing updateStatus with first task:', firstTask);
                this.updateTaskStatus(firstTask, 'completed');
            } else {
                console.log('No tasks available for testing');
            }
        },
        
        async loadProjectInfo() {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getById&id=${this.projectId}`);
                this.projectInfo = response.data;
                if(!this.projectInfo.id){
                    this.showMessage('プロジェクト情報の読み込みに失敗しました。', true);
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 1000);
                    return;
                }
            } catch (error) {
                console.error('Error loading project info:', error);
            }
        },
        
        async loadTasks() {
            try {
                const response = await axios.get(`/api/index.php?model=task&method=list&project_id=${this.projectId}&include_subtasks=1`);
                let tasks = response.data || [];
                
                // Sort tasks by position if available
                tasks = tasks.sort((a, b) => {
                    const posA = a.position || 0;
                    const posB = b.position || 0;
                    return posA - posB;
                });
                
                this.tasks = tasks;
                this.unreadComments = {};
                tasks.forEach(task => {
                    this.unreadComments[task.id] = task.unread_count || 0;
                });
                // Sau khi load tasks, load số comment chưa đọc
                // await this.loadUnreadComments( );
            } catch (error) {
                console.error('Error loading tasks:', error);
                this.showMessage('タスクの読み込みに失敗しました。', true);
            }
        },
        
     
        async loadPermission() {
            try {
                const response = await axios.get('/api/index.php?model=task&method=getPermission&project_id=' + this.projectId);
                this.permission = response.data || [];
            } catch (error) {
                console.error('Error loading permission:', error);
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
        
        // initializeDataTable() {
        //     const self = this;
        //     this.taskTable = $('#taskTable').DataTable({
        //         data: [],
        //         columns: [
        //             {
        //                 data: 'title',
        //                 render: function(data, type, row) {
        //                     let indent = '';
        //                     if (row.parent_id) {
        //                         indent = '<span style="margin-left: 20px;">↳ </span>';
        //                     }
        //                     return `${indent}<a href="#" class="task-link" data-id="${row.id}">${data}</a>`;
        //                 }
        //             },
        //             {
        //                 data: 'assigned_to_name',
        //                 render: function(data) {
        //                     return data || '-';
        //                 }
        //             },
        //             {
        //                 data: 'priority',
        //                 render: function(data) {
        //                     const priority = self.taskPriorities.find(p => p.value === data);
        //                     return `<span class="badge bg-${priority?.color || 'secondary'}">${priority?.label || data}</span>`;
        //                 }
        //             },
        //             {
        //                 data: 'status',
        //                 render: function(data) {
        //                     const status = self.taskStatuses.find(s => s.value === data);
        //                     return `<span class="badge bg-${status?.color || 'secondary'}">${status?.label || data}</span>`;
        //                 }
        //             },
        //             {
        //                 data: 'progress',
        //                 render: function(data) {
        //                     const color = data === 100 ? 'success' : 'primary';
        //                     return `<div class="progress" style="width: 80px;">
        //                                 <div class="progress-bar bg-${color}" style="width: ${data}%"></div>
        //                             </div>
        //                             <small>${data}%</small>`;
        //                 }
        //             },
        //             {
        //                 data: null,
        //                 render: function(data, type, row) {
        //                     return `<div class="btn-group btn-group-sm">
        //                                 <button class="btn btn-primary btn-edit-task" data-id="${row.id}">
        //                                     <i class="bi bi-pencil"></i>
        //                                 </button>
        //                                 <button class="btn btn-danger btn-delete-task" data-id="${row.id}">
        //                                     <i class="bi bi-trash"></i>
        //                                 </button>
        //                             </div>`;
        //                 }
        //             }
        //         ],
        //         language: {
        //             search: "検索:",
        //             lengthMenu: "_MENU_ 件表示",
        //             info: " _TOTAL_ 件中 _START_ から _END_ まで表示",
        //             paginate: {
        //                 first: "先頭",
        //                 previous: "前",
        //                 next: "次",
        //                 last: "最終"
        //             },
        //             emptyTable: "タスクがありません"
        //         },
        //         order: [[0, 'asc']],
        //         pageLength: 25
        //     });
            
        //     // イベントハンドラー
        //     $('#taskTable').on('click', '.task-link', (e) => {
        //         e.preventDefault();
        //         const id = $(e.target).data('id');
        //         const task = this.tasks.find(t => t.id == id);
        //         if (task) {
        //             this.selectedTask = task;
        //         }
        //     });
            
        //     $('#taskTable').on('click', '.btn-edit-task', (e) => {
        //         const id = $(e.target).closest('button').data('id');
        //         const task = this.tasks.find(t => t.id == id);
        //         if (task) {
        //             this.editTask(task);
        //         }
        //     });
            
        //     $('#taskTable').on('click', '.btn-delete-task', (e) => {
        //         const id = $(e.target).closest('button').data('id');
        //         this.deleteTask(id);
        //     });
        // },
        
        // updateDataTable() {
        //     if (this.taskTable) {
        //         // Sắp xếp tasks: parent tasks trước, sau đó subtasks
        //         const sortedTasks = [];
        //         const parentTasks = this.tasks.filter(t => !t.parent_id);
                
        //         parentTasks.forEach(parent => {
        //             sortedTasks.push(parent);
        //             const subtasks = this.tasks.filter(t => t.parent_id == parent.id);
        //             sortedTasks.push(...subtasks);
        //         });
                
        //         this.taskTable.clear();
        //         this.taskTable.rows.add(sortedTasks);
        //         this.taskTable.draw();
        //     }
        // },
        
        openNewTaskModal() {
            const newTask = {
                title: '',
                priority: 'medium',
                status: 'todo',
                start_date: '',
                due_date: '',
                progress: 0,
                assignees: [],
                position: null // Will be calculated when saving
            };
            this.inlineTasks.push(newTask);
            
            this.$nextTick(() => {
                const firstInput = document.querySelector('.inline-task-input');
                if (firstInput) {
                    firstInput.focus();
                }
                document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(el => {
                    if (el._dropdownInstance) {
                        el._dropdownInstance.dispose();
                    }
                    el._dropdownInstance = new (window.bootstrap ? window.bootstrap.Dropdown : bootstrap.Dropdown)(el);
                });
            });
        },
        
       
        
        async saveTask() {
            try {
                const formData = new FormData();
                Object.keys(this.taskForm).forEach(key => {
                    if (this.taskForm[key] !== null && this.taskForm[key] !== '') {
                        formData.append(key, this.taskForm[key]);
                    }
                });
                formData.append('project_id', this.projectId);
                
                let response;
                if (this.editingTask) {
                    response = await axios.post(`/api/index.php?model=task&method=update&id=${this.editingTask.id}`, formData);
                } else {
                    response = await axios.post('/api/index.php?model=task&method=create', formData);
                }
                
                if (response.data.success) {
                    this.showMessage(this.editingTask ? 'タスクが更新されました。' : 'タスクが作成されました。');
                    this.loadTasks();
                    this.resetTaskForm();
                    this.editingTask = null;
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addTaskModal'));
                    if (modal) modal.hide();
                } else {
                    this.showMessage(response.data.message || 'エラーが発生しました。', true);
                }
            } catch (error) {
                console.error('Error saving task:', error);
                this.showMessage('タスクの保存に失敗しました。', true);
            }
        },
        
        async deleteTask(task) {
            if (!confirm('本当にこのタスクを削除しますか？')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('id', task.id);
                const response = await axios.post('/api/index.php?model=task&method=delete', formData);
                
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
            if (!targetTask) {
                return;
            }
            
            if (!targetTask.id) {
                return;
            }
            
            const statusToSet = newStatus !== null ? newStatus : targetTask.status;
            
            try {
                const formData = new FormData();
                formData.append('id', targetTask.id);
                formData.append('status', statusToSet);
                formData.append('project_id', this.projectId);
                
               
                const response = await axios.post(
                    '/api/index.php?model=task&method=updateStatus',
                    formData
                );
                
                if (response.data.status == 'success') {
                    this.showMessage('ステータスを更新しました。');
                    // Reload tasks to get updated data
                    await this.loadTasks();
                } else {
                    throw new Error(response.data.message);
                }
            } catch (error) {
                this.showMessage(error.message || 'ステータスの更新に失敗しました', true);
            }
        },
        
        async updateTaskProgress(task = null) {
            const targetTask = task || this.selectedTask;
            if (!targetTask) {
                return;
            }
            
            if (!targetTask.id) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('id', targetTask.id);
                formData.append('progress', targetTask.progress);
                formData.append('project_id', this.projectId);
                
                const response = await axios.post(
                    '/api/index.php?model=task&method=updateProgress',
                    formData
                );
                if (response.data.status == 'success') {
                    this.showMessage('進捗を更新しました。');
                    // Reload tasks to get updated data
                    await this.loadTasks();
                } else {
                    throw new Error(response.data.message);
                }
            } catch (error) {
                this.showMessage(error.message || '進捗の更新に失敗しました', true);
            }
        },
        
        async updateTaskPriority(task, newPriority = null) {
            const targetTask = task;
            if (!targetTask) {
                return;
            }
            
            if (!targetTask.id) {
                return;
            }
            
            const priorityToSet = newPriority !== null ? newPriority : targetTask.priority;
            
            try {
                const formData = new FormData();
                formData.append('id', targetTask.id);
                formData.append('priority', priorityToSet);
                formData.append('project_id', this.projectId);
                
                const response = await axios.post(
                    '/api/index.php?model=task&method=updatePriority',
                    formData
                );
                if (response.data.status == 'success') {
                    this.showMessage('優先度を更新しました。');
                    // Reload tasks to get updated data
                    await this.loadTasks();
                } else {
                    throw new Error(response.data.message);
                }
            } catch (error) {
                this.showMessage(error.message || '優先度の更新に失敗しました', true);
            }
        },

        async updateTaskAssignee(task = null) {
            const targetTask = task || this.selectedTask;
            if (!targetTask) {
                return;
            }
            
            if (!targetTask.id) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('id', targetTask.id);
                formData.append('assigned_to', targetTask.assigned_to);
                formData.append('project_id', this.projectId);
                
                const response = await axios.post(
                    '/api/index.php?model=task&method=updateAssignee',
                    formData
                );
                if (response.data.status == 'success') {
                    this.showMessage('担当者を更新しました。');
                    // Reload tasks to get updated data
                    await this.loadTasks();
                } else {
                    throw new Error(response.data.message);
                }
            } catch (error) {
                this.showMessage(error.message || '担当者の更新に失敗しました', true);
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
            return moment(date).format('M月D日 HH:mm');
        },
        
        showMessage(message, isError = false) {
            // Simple alert for now
            showMessage(message, isError);
        },
        
        editTaskInline(task) {
            // Convert task to inline editable format
            const inlineTask = {
                id: task.id,
                title: task.title || '',
                priority: task.priority || 'medium',
                start_date: task.start_date || '',
                due_date: task.due_date || '',
                assignees: task.assigned_to ? task.assigned_to.split(',').filter(id => id.trim()) : [],
                status: task.status || 'todo',
                progress: task.progress || 0
            };
            this.editingInlineId = task.id;
            this.inlineTasks.push(inlineTask);
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
                formData.append('start_date', inlineTask.start_date);
                formData.append('due_date', inlineTask.due_date);
                formData.append('assigned_to', inlineTask.assignees.join(','));
                formData.append('status', inlineTask.status);
                formData.append('progress', inlineTask.progress);

                // Calculate position for new task
                if (!inlineTask.id) {
                    const position = this.calculateTaskPosition(idx);
                    formData.append('position', position);
                }

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
            this.inlineTasks.splice(idx, 1);
            this.editingInlineId = null;
        },
        
        getPriorityLabel(priority) {
            const p = this.taskPriorities.find(p => p.value === priority);
            return p ? p.label : priority;
        },
        
        getPriorityButtonClass(priority) {
            const p = this.taskPriorities.find(p => p.value === priority);
            return `btn-${p?.color || 'secondary'}`;
        },
        
        getStatusLabel(status) {
            const s = this.taskStatuses.find(s => s.value === status);
            return s ? s.label : status;
        },
        
        getStatusButtonClass(status) {
            const s = this.taskStatuses.find(s => s.value === status);
            return `btn-${s?.color || 'secondary'}`;
        },
        
        updateTaskStatusLocal(task, value) {
            task.status = value;
            // Gọi API cập nhật nếu cần
        },
        
        closeDropdown(event) {
            // Prevent default behavior
            event.preventDefault();
            event.stopPropagation();
            
            // Find the dropdown menu and close it using jQuery
            const dropdownMenu = event.target.closest('.dropdown-menu');
            if (dropdownMenu) {
                const dropdownButton = dropdownMenu.previousElementSibling;
                if (dropdownButton && dropdownButton.classList.contains('dropdown-toggle')) {
                    // Use jQuery to close the dropdown
                    $(dropdownButton).dropdown('hide');
                    
                    // Backup method: manually hide after a short delay
                    setTimeout(() => {
                        if (dropdownMenu.classList.contains('show')) {
                            dropdownMenu.classList.remove('show');
                            dropdownButton.setAttribute('aria-expanded', 'false');
                        }
                    }, 100);
                }
            }
        },
        
        closeAllDropdowns() {
            // Close all open dropdowns
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
                const button = menu.previousElementSibling;
                if (button && button.classList.contains('dropdown-toggle')) {
                    button.setAttribute('aria-expanded', 'false');
                }
            });
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
                return  '/assets/upload/avatar/' + member.user_image || '';
            }
            return '';
        },
        
        handleAvatarError(member) {
            member.avatarError = true;
        },
        
        getInitials(name) {
            return getAvatarName(name);
        },
        
        formatDateTime(date) {
            if (!date) return '-';
            return moment(date).format('YYYY/MM/DD HH:mm');
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
            // Ensure we have a safe copy of assignees array
            const currentAssignees = this.inlineTasks[idx]?.assignees || [];
            this.assigneeModal.selected = Array.isArray(currentAssignees) ? [...currentAssignees] : [];
            
            // Backup current task data to prevent loss
            this.assigneeModal.backupData = { ...this.inlineTasks[idx] };
            
            this.assigneeModal.show = true;
        },
        
        closeAssigneeModal() {
            this.assigneeModal.show = false;
            this.assigneeModal.backupData = null;
        },
        
        confirmAssigneeModal() {
            if (this.assigneeModal.idx !== null) {
                // Preserve all existing data and only update assignees
                const currentTask = this.inlineTasks[this.assigneeModal.idx];
                
                // Restore backup data if available and merge with new assignees
                if (this.assigneeModal.backupData) {
                    this.inlineTasks[this.assigneeModal.idx] = {
                        ...this.assigneeModal.backupData,
                        assignees: [...this.assigneeModal.selected]
                    };
                } else {
                    // Update only the assignees property to preserve all other data
                    this.inlineTasks[this.assigneeModal.idx].assignees = [...this.assigneeModal.selected];
                }
                
                // Ensure all required fields exist
                this.ensureTaskData(this.assigneeModal.idx);
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
                        
                        // Get the dragged task ID
                        const draggedTaskId = evt.item.getAttribute('data-id');
                        console.log('Dragged task ID:', draggedTaskId);
                        
                        // Calculate new parent_id based on position
                        const newParentId = this.calculateNewParentId(evt.newIndex, taskElements);
                        console.log('New parent ID:', newParentId);
                        
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
                            this.updateTaskOrder(newOrder, draggedTaskId, newParentId);
                        }
                    }
                });
            }
        },
        
        calculateNewParentId(newIndex, taskElements) {
            if (newIndex === 0) {
                // If dropped at the top, no parent
                return null;
            }
            // Get the indent level of the dropped position (after move, so use previous task)
            let parentId = null;
            for (let i = newIndex - 1; i >= 0; i--) {
                const element = taskElements[i];
                const taskId = element.getAttribute('data-id');
                if (!taskId) continue;
                // Find the task in our data
                const task = this.tasks.find(t => t.id == taskId);
                if (!task) continue;
                // Calculate indent level for this task
                const indentLevel = this.calculateIndent(task, i, this.tasks).indent_level;
                // The first previous task with indent_level less than the dropped task will be the parent
                if (indentLevel >= 0) {
                    parentId = taskId;
                    break;
                }
            }
            return parentId;
        },
        
        async updateTaskOrder(taskIds, draggedTaskId = null, newParentId = null) {
            try {
                const formData = new FormData();
                formData.append('task_ids', JSON.stringify(taskIds));
                formData.append('project_id', this.projectId);
                
                // Add dragged task ID and new parent ID if provided
                if (draggedTaskId) {
                    formData.append('dragged_task_id', draggedTaskId);
                }
                if (newParentId !== null) {
                    formData.append('new_parent_id', newParentId);
                }
                
                const response = await axios.post(
                    '/api/index.php?model=task&method=updateOrder',
                    formData
                );
                
                if (response.data.success) {
                    // Reload tasks to reflect the new structure
                    await this.loadTasks();
                } else {
                    this.showMessage(response.data.message || 'タスク順序の更新に失敗しました', true);
                }
                
            } catch (error) {
                console.error('Error updating task order:', error);
                this.showMessage('タスク順序の更新に失敗しました', true);
            }
        },
        
        calculateIndent(task, index, allTasks) {
            // Calculate indent level based on parent_id
            let indentLevel = 0;
            let currentTask = task;
            
            while (currentTask.parent_id) {
                indentLevel++;
                currentTask = allTasks.find(t => t.id === currentTask.parent_id);
                if (!currentTask) break;
            }
            
            return {
                ...task,
                indent_level: indentLevel
            };
        },
        
        async increaseIndent(task) {
            // Find the previous task with less indent
            const currentIndex = this.displayTasks.findIndex(t => t.id === task.id);
            if (currentIndex <= 0) return;
            
            let parentTask = null;
            for (let i = currentIndex - 1; i >= 0; i--) {
                const prevTask = this.displayTasks[i];
                if (prevTask.indent_level <= task.indent_level) {
                    parentTask = prevTask;
                    break;
                }
            }
            
            if (parentTask) {
                await this.setParent(task.id, parentTask.id);
            }
        },
        
        async decreaseIndent(task) {
            if (task.indent_level > 0) {
                // Find the current task's parent
                const currentTask = this.tasks.find(t => t.id === task.id);
                if (!currentTask || !currentTask.parent_id) return;
                
                // Find the parent task
                const parentTask = this.tasks.find(t => t.id === currentTask.parent_id);
                if (!parentTask) return;
                
                // Set the new parent to the parent's parent (one level up)
                const newParentId = parentTask.parent_id;
                await this.setParent(task.id, newParentId);
            }
        },
        
        async setParent(taskId, parentId) {
            try {
                const formData = new FormData();
                formData.append('task_id', taskId);
                formData.append('parent_id', parentId || '');
                
                const response = await axios.post(
                    '/api/index.php?model=task&method=setParent',
                    formData
                );
                
                if (response.data.success) {
                   // this.showMessage(parentId ? 'サブタスクが作成されました。' : 'サブタスクが解除されました。');
                    await this.loadTasks();
                } else {
                    this.showMessage(response.data.message || '操作に失敗しました。', true);
                }
                
            } catch (error) {
                console.error('Error setting parent:', error);
                this.showMessage('操作に失敗しました。', true);
            }
        },
        canIncreaseIndent(task) {
            // Task đầu tiên không thể tăng indent
            if (this.isFirstTask(task)) {
                return false;
            }
            return true;
        },
        
        canDecreaseIndent(task) {
            // Kiểm tra xem task có indent level > 0 không
            if (task.indent_level <= 0) {
                return false;
            }
            
            // Tìm vị trí của task trong displayTasks
            const currentIndex = this.displayTasks.findIndex(t => t.id === task.id);
            if (currentIndex <= 0) {
                return false; // Task đầu tiên hoặc không tìm thấy
            }
            
            // Kiểm tra task trước đó có cùng indent level không
            const previousTask = this.displayTasks[currentIndex - 1];
            if (!previousTask || previousTask._isInlineEdit) {
                return false; // Task trước đó không tồn tại hoặc là inline task
            }
            
            // Có thể giảm indent nếu task trước đó có cùng indent level
            return previousTask.indent_level === task.indent_level;
        },
        
        isFirstTask(task) {
            // Check if this is the first task in the display list
            const firstTask = this.displayTasks.find(t => !t._isInlineEdit);
            return firstTask && firstTask.id === task.id;
        },

        
        updateInlineTaskPriority(index, priority) {
            if (index >= 0 && index < this.inlineTasks.length) {
                const task = this.inlineTasks[index];
                if (task) {
                    const oldPriority = task.priority;
                    task.priority = priority;
                    
                    // Nếu task có id thì gọi API cập nhật
                    // if (task.id) {
                    //     this.updateTaskPriority(task, priority);
                    // }
                    
                    // Đảm bảo dữ liệu task đầy đủ
                    this.ensureTaskData(index);
                }
            }
        },
        
        updateInlineTaskStatus(index, status) {
            if (this.inlineTasks[index]) {
                // Backup current data before updating
                const backupData = { ...this.inlineTasks[index] };
                
                // Use Vue.set to ensure reactivity
                if (typeof Vue !== 'undefined' && Vue.set) {
                    Vue.set(this.inlineTasks[index], 'status', status);
                } else {
                    // Fallback to direct assignment
                    this.inlineTasks[index].status = status;
                }
                
                // Verify data integrity
                if (!this.inlineTasks[index].title && backupData.title) {
                    if (typeof Vue !== 'undefined' && Vue.set) {
                        Vue.set(this.inlineTasks[index], 'title', backupData.title);
                    } else {
                        this.inlineTasks[index].title = backupData.title;
                    }
                }
                
                // Use setTimeout instead of $nextTick to avoid conflicts
                setTimeout(() => {
                    this.closeAllDropdowns();
                }, 100);
            }
        },
        
        ensureTaskData(index) {
            // Ensure all required fields exist in the inline task
            if (this.inlineTasks[index]) {
                const task = this.inlineTasks[index];
                
                // Only set default values if the field is undefined or null, not if it's an empty string
                // Don't overwrite existing data
                if (task.title === undefined || task.title === null) {
                    task.title = '';
                }
                if (task.priority === undefined || task.priority === null) {
                    task.priority = 'medium';
                }
                if (task.status === undefined || task.status === null) {
                    task.status = 'todo';
                }
                if (task.start_date === undefined || task.start_date === null) {
                    task.start_date = '';
                }
                if (task.due_date === undefined || task.due_date === null) {
                    task.due_date = '';
                }
                if (task.progress === undefined || task.progress === null) {
                    task.progress = 0;
                }
                if (!Array.isArray(task.assignees)) {
                    task.assignees = [];
                }
            }
        },
        
        isTaskOverdue(task) {
            if (!task.due_date) return false;
            const due = moment.tz(task.due_date, 'Asia/Tokyo');
            if (task.status === 'completed') {
                if (!task.actual_end_date) return false;
                return moment.tz(task.actual_end_date, 'Asia/Tokyo').isAfter(due, 'minute');
            } else {
                return moment().tz('Asia/Tokyo').isAfter(due, 'minute');
            }
        },
        
        isInlineTaskOverdue(inlineTask) {
            if (!inlineTask.due_date) return false;
            const due = moment.tz(inlineTask.due_date, 'Asia/Tokyo');
            if (inlineTask.status === 'completed') {
                // For inline tasks, we don't have actual_end_date, so just check if due_date is in the past
                return moment().tz('Asia/Tokyo').isAfter(due, 'minute');
            } else {
                return moment().tz('Asia/Tokyo').isAfter(due, 'minute');
            }
        },
        
        initTooltips() {
            // Initialize Bootstrap tooltips
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            } else if (typeof $ !== 'undefined' && $.fn.tooltip) {
                // Fallback to jQuery tooltip if Bootstrap is not available
                $('[data-bs-toggle="tooltip"]').tooltip();
            }
        },
        
        getOverdueTooltip(task) {
            if (!this.isTaskOverdue(task)) return '';
            
            const due = moment.tz(task.due_date, 'Asia/Tokyo');
            let endTime;
            
            if (task.status === 'completed') {
                endTime = moment.tz(task.actual_end_date, 'Asia/Tokyo');
            } else {
                endTime = moment().tz('Asia/Tokyo');
            }
            
            const duration = moment.duration(endTime.diff(due));
            const hours = Math.floor(duration.asHours());
            const minutes = Math.floor(duration.asMinutes()) % 60;
            
            if (hours > 0) {
                return `期限切れ: ${hours}時間${minutes}分`;
            } else {
                return `期限切れ: ${minutes}分`;
            }
        },
        
        getInlineOverdueTooltip(inlineTask) {
            if (!this.isInlineTaskOverdue(inlineTask)) return '';
            
            const due = moment.tz(inlineTask.due_date, 'Asia/Tokyo');
            const now = moment().tz('Asia/Tokyo');
            const duration = moment.duration(now.diff(due));
            const hours = Math.floor(duration.asHours());
            const minutes = Math.floor(duration.asMinutes()) % 60;
            
            if (hours > 0) {
                return `期限切れ: ${hours}時間${minutes}分`;
            } else {
                return `期限切れ: ${minutes}分`;
            }
        },
        
        openTaskDetails(task) {
            this.selectedTask = task;
            
            // Show modal
            const modalEl = document.getElementById('taskDetailsModal');
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
            
            // Initialize Quill editor after modal is shown
            setTimeout(() => {
                this.initQuillEditor();
            }, 300);
            // Add event listener for tab changes
            this.$nextTick(() => {
                const taskDetailsModal = modalEl;
                if (taskDetailsModal) {
                    // Define the handler as a named function so we can remove it later
                    const tabShownHandler = (event) => {
                        if (event.target.getAttribute('data-bs-target') === '#history') {
                            if (this.taskLogs.length === 0) {
                                this.loadTaskLogs(task.id);
                            }
                        }
                        if (event.target.getAttribute('data-bs-target') === '#comments') {
                            this.markCommentsAsRead(task.id);
                        }
                    };
                    // Store handler on the element for later removal
                    taskDetailsModal._tabShownHandler = tabShownHandler;
                    taskDetailsModal.addEventListener('shown.bs.tab', tabShownHandler);

                    // Remove the listener when modal is hidden
                    const removeTabHandler = () => {
                        if (taskDetailsModal._tabShownHandler) {
                            taskDetailsModal.removeEventListener('shown.bs.tab', taskDetailsModal._tabShownHandler);
                            delete taskDetailsModal._tabShownHandler;
                        }
                        taskDetailsModal.removeEventListener('hidden.bs.modal', removeTabHandler);
                    };
                    taskDetailsModal.addEventListener('hidden.bs.modal', removeTabHandler);
                }
            });
        },
        

        
        async loadTaskLogs(taskId) {
            try {
                const response = await axios.get(`/api/index.php?model=task&method=getLogs&task_id=${taskId}`);
                this.taskLogs = response.data || [];
                console.log('Task logs loaded:', this.taskLogs);
            } catch (error) {
                console.error('Error loading task logs:', error);
                this.taskLogs = [];
            }
        },
        
        initQuillEditor() {
            // Wait for DOM to be ready
            this.$nextTick(() => {
                const editorElement = document.getElementById('taskDescriptionEditor');
                if (!editorElement) {
                    console.error('Editor element not found');
                    return;
                }
                
                // Destroy existing editor if any
                try {
                    if (this.quillEditor && typeof this.quillEditor.destroy === 'function') {
                        this.quillEditor.destroy();
                    }
                } catch (error) {
                    console.warn('Error destroying existing Quill editor:', error);
                }
                
                // Clear the container
                editorElement.innerHTML = '';
                const toolbarOptions = [
                    [
                        { font: [] },
                        { size: [] }
                    ],
                    ['bold', 'italic', 'underline', 'strike'],
                    [
                        { color: [] },
                        { background: [] }
                    ],
                    [
                        { script: 'super' },
                        { script: 'sub' }
                    ],
                    [
                        { header: '1' },
                        { header: '2' }, 'blockquote' ],
                    [
                        { list: 'ordered' },
                        { indent: '-1' },
                        { indent: '+1' }
                    ],
                    [{ direction: 'rtl' }, { align: [] }],
                    ['link', 'image', 'video', 'formula'],
                    ['clean']
                ];
                
                // Initialize Quill editor
                this.quillEditor = new Quill('#taskDescriptionEditor', {
                    theme: 'snow',
                    placeholder: 'タスクの説明を入力してください...',
                    modules: {
                        toolbar: {
                            container: toolbarOptions,
                            handlers: {
                                image: this.imageHandler.bind(this)
                            }
                        }
                    }
                });
                
                // Set content if task has description
                if (this.selectedTask && this.selectedTask.description) {
                    // Convert HTML to safe format before displaying
                    const cleanHtml = this.decodeHtmlEntities(this.selectedTask.description);
                    this.quillEditor.root.innerHTML = cleanHtml;
                }
            });
        },
        
        
        decodeHtmlEntities(str) {
            const txt = document.createElement('textarea');
            txt.innerHTML = str;
            return txt.value;
        },
        
        imageHandler() {
            const input = document.createElement('input');
            input.setAttribute('type', 'file');
            input.setAttribute('accept', 'image/*');
            input.click();
            
            input.onchange = async () => {
                const file = input.files[0];
                if (file) {
                    try {
                        // Kiểm tra kích thước file (max 5MB)
                        if (file.size > 5 * 1024 * 1024) {
                            this.showMessage('ファイルサイズは5MB以下にしてください。', true);
                            return;
                        }
                        
                        // Upload trực tiếp không cần placeholder
                        
                        // Upload sử dụng service worker với project_id và task_id
                        const uploadUrl = '/api/quill-image-upload.php';
                        const response = await window.swManager.uploadFile(file, uploadUrl, { 
                            project_id: this.projectId,
                            task_id: this.selectedTask ? this.selectedTask.id : null
                        });
                        
                        if (response.success) {
                            // Thay thế placeholder bằng ảnh thật với requestAnimationFrame để đảm bảo DOM sẵn sàng
                            requestAnimationFrame(() => {
                                try {
                                    if (this.quillEditor && this.quillEditor.root) {
                                        // Lấy độ dài hiện tại của nội dung
                                        const length = this.quillEditor.getLength();
                                        
                                        // Chèn ảnh ở cuối
                                        this.quillEditor.insertEmbed(length - 1, 'image', response.url);
                                        this.quillEditor.insertText(length, '\n');
                                        
                                        // Focus vào editor
                                        this.quillEditor.focus();
                                        
                                        // Scroll xuống cuối
                                        if (this.quillEditor.scrollingContainer) {
                                            this.quillEditor.scrollingContainer.scrollTop = this.quillEditor.scrollingContainer.scrollHeight;
                                        }
                                    }
                                } catch (error) {
                                    console.error('Error inserting image:', error);
                                    // Fallback: append trực tiếp vào HTML
                                    if (this.quillEditor && this.quillEditor.root) {
                                        const imageHtml = `<p><img src="${response.url}" alt="Uploaded image" style="max-width: 100%; height: auto;"></p>`;
                                        this.quillEditor.root.innerHTML += imageHtml;
                                    }
                                }
                            });
                        } else {
                            this.showMessage('画像のアップロードに失敗しました: ' + (response.error || 'Unknown error'), true);
                        }
                    } catch (error) {
                        console.error('Error uploading image:', error);
                        this.showMessage('画像のアップロードに失敗しました。', true);
                    }
                }
            };
        },
        resetQuillEditor() {
            try {
                if (this.quillEditor && typeof this.quillEditor.destroy === 'function') {
                    this.quillEditor.destroy();
                }
            } catch (error) {
                console.warn('Error destroying Quill editor:', error);
            } finally {
                this.quillEditor = null;
            }
            this.selectedTask = null;
            this.taskLogs = [];
        },
        
        getTaskLogIcon(action) {
            switch(action) {
                case 'created': return 'fa fa-plus-circle text-success';
                case 'updated': return 'fa fa-edit text-info';
                case 'deleted': return 'fa fa-trash text-danger';
                case 'status_changed': return 'fa fa-exchange-alt text-primary';
                case 'progress_updated': return 'fa fa-chart-line text-warning';
                case 'priority_updated': return 'fa fa-flag text-warning';
                case 'assigned': return 'fa fa-user-plus text-success';
                case 'comment': return 'fa fa-comment-dots text-secondary';
                default: return 'fa fa-history text-muted';
            }
        },
        
        getTaskLogLabel(action) {
            switch(action) {
                case 'created': return 'タスク作成';
                case 'updated': return 'タスク更新';
                case 'deleted': return 'タスク削除';
                case 'status_changed': return 'ステータス変更';
                case 'progress_updated': return '進捗変更';
                case 'priority_updated': return '優先度変更';
                case 'assigned': return '担当者変更';
                case 'comment': return 'コメント追加';
                default: return action;
            }
        },
        
        getTaskLogBadgeClass(log, field) {
            if (log.action === 'status_changed') {
                const statusColors = {
                    'todo': 'bg-secondary',
                    'in-progress': 'bg-primary', 
                    'confirming': 'bg-warning',
                    'paused': 'bg-warning',
                    'completed': 'bg-success',
                    'cancelled': 'bg-danger'
                };
                return 'badge ' + (statusColors[log[field]] || 'bg-secondary');
            }
            if (log.action === 'priority_updated') {
                const priorityColors = {
                    'low': 'bg-secondary',
                    'medium': 'bg-primary',
                    'high': 'bg-warning', 
                    'urgent': 'bg-danger'
                };
                return 'badge ' + (priorityColors[log[field]] || 'bg-secondary');
            }
            return field === 'value1' ? 'badge bg-secondary' : 'badge bg-primary';
        },
        
        getTaskLogBadgeLabel(log, field) {
            if (log.action === 'status_changed') {
                return this.getStatusLabel(log[field]);
            }
            if (log.action === 'priority_updated') {
                return this.getPriorityLabel(log[field]);
            }
            return log[field];
        },
        
        async saveTaskDescription() {
            if (!this.quillEditor || !this.selectedTask) {
                console.error('Quill editor or selected task not available');
                return;
            }
            
            const content = this.quillEditor.root.innerHTML;
            console.log('Saving description:', content);
            
            try {
                const formData = new FormData();
                formData.append('id', this.selectedTask.id);
                formData.append('project_id', this.projectId);
                formData.append('description', content);
                
                const response = await axios.post('/api/index.php?model=task&method=updateDescription', formData);
                if (response.data.status === 'success') {
                    this.showMessage('説明を保存しました。');
                    this.selectedTask.description = content;
                    // Reload tasks to get updated data
                    await this.loadTasks();
                } else {
                    throw new Error(response.data.message || '保存に失敗しました');
                }
            } catch (error) {
                console.error('Error saving description:', error);
                this.showMessage(error.message || '説明の保存に失敗しました。', true);
            }
        },

        
        getActivityIcon(type) {
            const icons = {
                'created': 'fas fa-plus',
                'updated': 'fas fa-edit',
                'commented': 'fas fa-comment',
                'completed': 'fas fa-check',
                'status_changed': 'fas fa-exchange-alt',
                'assigned': 'fas fa-user-plus'
            };
            return icons[type] || 'fas fa-circle';
        },
        
        getActivityIconClass(type) {
            const classes = {
                'created': 'created',
                'updated': 'updated',
                'commented': 'commented',
                'completed': 'completed',
                'status_changed': 'updated',
                'assigned': 'updated'
            };
            return classes[type] || 'updated';
        },
        
        // Upload progress handling methods
        updateUploadProgress(fileName, progress) {
            // Update progress bar if exists
            const progressBar = document.querySelector(`[data-file="${fileName}"] .progress-bar`);
            if (progressBar) {
                progressBar.style.width = progress + '%';
                progressBar.textContent = progress + '%';
            }
        },
        
        handleUploadSuccess(fileName, data) {
            console.log('Upload successful:', fileName, data);
            // Remove progress indicator if exists
            const progressContainer = document.querySelector(`[data-file="${fileName}"]`);
            if (progressContainer) {
                progressContainer.remove();
            }
        },
        
        handleUploadError(fileName, error) {
            console.error('Upload failed:', fileName, error);
            // Remove progress indicator and show error
            const progressContainer = document.querySelector(`[data-file="${fileName}"]`);
            if (progressContainer) {
                progressContainer.remove();
            }
            this.showMessage(`アップロードに失敗しました: ${fileName}`, true);
        },
        
        // Comment component event handlers
        onTaskCommentAdded(event) {
            console.log('Task comment added:', event);
            // Comment component handles its own state, no need to reload
        },
        
        onTaskCommentLiked(event) {
            console.log('Task comment liked:', event);
            // No need to reload, the component handles it internally
        },
        
        handleCommentError(error) {
            console.error('Comment component error:', error);
            this.showMessage(error.message || 'コメントの処理中にエラーが発生しました', true);
        },
        
        getUnreadCommentCount(taskId) {
            return this.unreadComments[taskId] || 0;
        },
        
        openTaskComments(task) {
            this.selectedTask = task;
            this.openTaskDetails(task);
           
            this.$nextTick(() => {
                const tabBtn = document.querySelector('#taskDetailsModal [data-bs-target="#comments"]');
                if (tabBtn) tabBtn.click();
                this.markCommentsAsRead(task.id);
            });
        },
        
        async markCommentsAsRead(taskId) {
            try {
                const formData = new FormData();
                formData.append('task_id', taskId);
                await axios.post('/api/index.php?model=task&method=markTaskCommentsAsRead', formData);
                // Cập nhật lại số comment chưa đọc
                this.unreadComments[taskId] = 0;
                //await this.loadUnreadComment(taskId);
            } catch (e) {}
        },

        checkAssignee(task) {
            return task.assigned_to.split(',').includes(this.currentUserId);
        },
        
        async removeAssignee(task, userId) {
            if(this.permission.can_manage_project || (this.permission.rule && this.permission.rule.task_edit == 1 && this.checkAssignee(task))){
                if(!this.projectMembers.find(m => m.user_id == userId)){
                    const swal = await Swal.fire({
                        title: '確認',
                        text: '担当者を削除しますか？',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: '削除',
                        cancelButtonText: 'キャンセル'
                    });
                    
                    if (swal.isConfirmed) {
                        const newAssignees = task.assigned_to.split(',').filter(id => id !== userId.toString());
                        task.assigned_to = newAssignees.join(',');
                        await this.updateTaskAssignee(task);
                    }
                }
            }
        }
    }
});

// Mount the app when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const app = TaskApp.mount('#app');
    // Expose app instance globally for external access
    window.TaskApp = app;
});
