/**
 * Todo Modal Management
 * Handles "My Tasks" and "Custom Todos" tabs
 */

document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('todoApp')) {
        const todoApp = Vue.createApp({
            data() {
                return {
                    activeTab: 'tasks',
                    tasks: [],
                    todos: [],
                    newTodo: {
                        title: '',
                        priority: 50, // Default medium
                        deadline: ''
                    },
                    loadingTasks: false,
                    loadingTodos: false,
                    myTaskCount: 0,
                    todoCount: 0,
                    fp: null, // Flatpickr for new-todo date
                    fpEdit: null, // Flatpickr for inline-edit deadline
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
                    ],
                    refreshIntervalId: null,
                    editingTodoId: null,
                    editingTodoTitle: '',
                    editingTodoPriority: 50,
                    editingTodoTerm: '',
                    editingTodoLink: '',
                    editingTodoComment: ''
                };
            },
            computed: {
                incompleteTodoCount() {
                    return (this.todos || []).filter(t => t.todo_complete != 1).length;
                }
            },
            mounted() {
                Promise.all([this.loadTasks(), this.loadTodos()]).then(() => this.applyDefaultTab());
                
                // Initialize Flatpickr
                const dateInput = document.getElementById('new-todo-date');
                if (dateInput && typeof flatpickr !== 'undefined') {
                    this.fp = flatpickr(dateInput, {
                        dateFormat: "Y-m-d H:i",
                        enableTime: true,
                        time_24hr: true,
                        minDate: "today",
                        locale: "ja",
                        onChange: (selectedDates, dateStr) => {
                            this.newTodo.deadline = dateStr;
                        }
                    });
                }
                
                // Refresh on offcanvas open and set default tab by count
                const offcanvasElement = document.getElementById('offcanvasTodo');
                if (offcanvasElement) {
                    offcanvasElement.addEventListener('show.bs.offcanvas', () => {
                        Promise.all([this.loadTasks(), this.loadTodos()]).then(() => this.applyDefaultTab());
                    });
                }
                // Refresh when a todo was added from context menu (e.g. project list/detail)
                this._onTodoAddedFromContextBound = () => { this.loadTodos(); };
                document.body.addEventListener('todo-added-from-context', this._onTodoAddedFromContextBound);
                // Refresh data every 1 minute (skip when inline editing a todo)
                this.refreshIntervalId = setInterval(() => {
                    if (this.editingTodoId) return;
                    this.loadTasks();
                    this.loadTodos();
                }, 60000);
            },
            beforeUnmount() {
                if (this.refreshIntervalId) {
                    clearInterval(this.refreshIntervalId);
                    this.refreshIntervalId = null;
                }
                if (this._onTodoAddedFromContextBound) {
                    document.body.removeEventListener('todo-added-from-context', this._onTodoAddedFromContextBound);
                }
                this.destroyFpEdit();
            },
            methods: {
                /**
                 * Set default tab: show tab with count > 1; if both > 1, show Custom Todo.
                 */
                applyDefaultTab() {
                    const todoCount = this.incompleteTodoCount;
                    const taskCount = this.myTaskCount;
                    if (todoCount >= 1) {
                        this.activeTab = 'todos';
                    } else if (taskCount >= 1) {
                        this.activeTab = 'tasks';
                    } else {
                        this.activeTab = 'tasks';
                    }
                },
                t(key, fallback) {
                    if (typeof i18next !== 'undefined' && i18next.isInitialized && typeof i18next.t === 'function') {
                        const v = i18next.t(key);
                        return (v && v !== key) ? v : (fallback || key);
                    }
                    return fallback || key;
                },
                /**
                 * Load "My Tasks" (Assigned to current user)
                 * Uses existing Task model method via API
                 */
                async loadTasks() {
                    this.loadingTasks = true;
                    try {
                        const userId = window.currentUserId;
                        if (!userId) {
                            this.tasks = [];
                            this.loadingTasks = false;
                            this.updateTotalCount();
                            return;
                        }
                        
                        const response = await axios.get('/api/index.php?model=task&method=listOverview&user_id=' + userId + '&exclude_completed=1');
                        
                        // Response structure: { tasks: [...], ... }
                        if (response.data && response.data.tasks && Array.isArray(response.data.tasks)) {
                             this.tasks = response.data.tasks;
                             this.myTaskCount = this.tasks.length;
                        } else {
                            this.tasks = [];
                            this.myTaskCount = 0;
                        }
                    } catch (error) {
                        console.error('Failed to load tasks', error);
                        this.tasks = [];
                    } finally {
                        this.loadingTasks = false;
                        this.updateTotalCount();
                    }
                },

                /**
                 * Load "Custom Todos"
                 * Uses Todo model
                 */
                async loadTodos() {
                    this.loadingTodos = true;
                    try {
                        const response = await axios.get('/api/index.php?model=todo&method=api_index');
                        if (response.data && Array.isArray(response.data)) {
                            this.todos = response.data;
                        } else {
                            this.todos = [];
                        }
                    } catch (error) {
                        console.error('Failed to load todos', error);
                        this.todos = [];
                    } finally {
                        this.loadingTodos = false;
                        this.updateTotalCount();
                    }
                },

                /**
                 * Add a new Custom Todo
                 */
                async addTodo() {
                    if (!this.newTodo.title.trim()) return;

                    try {
                        const formData = new FormData();
                        formData.append('todo_title', this.newTodo.title);
                        formData.append('todo_priority', this.newTodo.priority); 
                        formData.append('todo_term', this.newTodo.deadline);
                        formData.append('todo_complete', 0);

                        const response = await axios.post('/api/index.php?model=todo&method=api_add', formData);
                        
                        if (response.data && response.data.status === 'success') {
                            this.newTodo.title = '';
                            this.newTodo.priority = 50;
                            this.newTodo.deadline = '';
                            if(this.fp) this.fp.clear(); // Clear datepicker
                            
                            this.loadTodos(); // Reload to get ID and correct order
                        } else {
                            alert('Failed to add todo: ' + (response.data.message || 'Unknown error'));
                        }
                    } catch (error) {
                        console.error('Error adding todo', error);
                    }
                },

                /**
                 * Toggle Todo Completion (Complete/Incomplete)
                 */
                async toggleTodo(todo) {
                    const newStatus = todo.todo_complete == 1 ? 0 : 1;
                    // Optimistic update
                    todo.todo_complete = newStatus;
                    this.updateTotalCount(); // Update toggle button badge immediately
                    
                    try {
                        const formData = new FormData();
                        formData.append('id', todo.id);
                        formData.append('todo_complete', newStatus);
                        
                        const response = await axios.post('/api/index.php?model=todo&method=api_update', formData);
                        if (response.data.status !== 'success') {
                            // Revert on failure
                            todo.todo_complete = !newStatus;
                            this.updateTotalCount();
                            console.error('Failed to update status');
                        }
                    } catch (error) {
                        todo.todo_complete = !newStatus;
                        this.updateTotalCount();
                        console.error('Error updating todo', error);
                    }
                },

                /**
                 * Start inline edit of todo title
                 */
                startEditTodo(todo) {
                    if (todo.todo_complete == 1) return;
                    this.destroyFpEdit();
                    this.editingTodoId = todo.id;
                    this.editingTodoTitle = (todo.todo_title || '').trim();
                    this.editingTodoPriority = parseInt(todo.todo_priority, 10) || 50;
                    this.editingTodoTerm = this.todoTermToFlatpickrStr(todo.todo_term);
                    this.editingTodoLink = (todo.todo_link || '').trim();
                    this.editingTodoComment = (todo.todo_comment || '').trim();
                    this.$nextTick(() => {
                        const ref = this.$refs.editTodoInput;
                        const el = Array.isArray(ref) ? ref[0] : ref;
                        if (el) (el.$el || el).focus();
                        this.initFpEdit();
                    });
                },
                destroyFpEdit() {
                    if (this.fpEdit) {
                        this.fpEdit.destroy();
                        this.fpEdit = null;
                    }
                },
                initFpEdit() {
                    const ref = this.$refs.editTodoDateInput;
                    const el = Array.isArray(ref) ? ref[0] : ref;
                    if (!el || typeof flatpickr === 'undefined') return;
                    const input = el.$el || el;
                    this.fpEdit = flatpickr(input, {
                        dateFormat: "Y-m-d H:i",
                        enableTime: true,
                        time_24hr: true,
                        locale: "ja",
                        allowInput: false,
                        defaultDate: this.editingTodoTerm || null,
                        onChange: (selectedDates, dateStr) => {
                            this.editingTodoTerm = dateStr || '';
                        }
                    });
                    if (this.editingTodoTerm) this.fpEdit.setDate(this.editingTodoTerm, false);
                },
                todoTermToFlatpickrStr(term) {
                    if (!term || term === '0000-00-00 00:00:00' || term === '0000-00-00') return '';
                    return term.slice(0, 16); // "Y-m-d H:i"
                },
                editingTermToTodoTerm(val) {
                    val = (val || '').trim();
                    if (!val) return '';
                    const s = val.replace('T', ' ');
                    return s.length <= 16 ? s + ':00' : s;
                },
                cancelEditTodo() {
                    this.destroyFpEdit();
                    this.editingTodoId = null;
                    this.editingTodoTitle = '';
                    this.editingTodoPriority = 50;
                    this.editingTodoTerm = '';
                    this.editingTodoLink = '';
                    this.editingTodoComment = '';
                },
                /**
                 * Save todo (all fields: title, priority, term)
                 */
                saveTodoEdit(todo) {
                    if (this.editingTodoId !== todo.id) return;
                    const newTitle = (this.editingTodoTitle || '').trim();
                    if (!newTitle) return;
                    const newPriority = parseInt(this.editingTodoPriority, 10) || 50;
                    const newTerm = (this.editingTodoTerm || '').trim() ? this.editingTermToTodoTerm(this.editingTodoTerm.trim()) : '';
                    const newLink = (this.editingTodoLink || '').trim();
                    const newComment = (this.editingTodoComment || '').trim();
                    this.cancelEditTodo();
                    this.updateTodoApi(todo, { title: newTitle, priority: newPriority, term: newTerm, link: newLink, comment: newComment });
                },
                /**
                 * API: update todo (title, priority, term, link, comment)
                 */
                async updateTodoApi(todo, payload) {
                    const prev = { title: todo.todo_title, priority: todo.todo_priority, term: todo.todo_term, link: todo.todo_link, comment: todo.todo_comment };
                    todo.todo_title = payload.title;
                    todo.todo_priority = payload.priority;
                    todo.todo_term = payload.term || null;
                    todo.todo_link = payload.link || null;
                    todo.todo_comment = payload.comment || null;
                    try {
                        const formData = new FormData();
                        formData.append('id', todo.id);
                        formData.append('todo_title', payload.title);
                        formData.append('todo_priority', payload.priority);
                        formData.append('todo_term', payload.term || '');
                        formData.append('todo_link', payload.link || '');
                        formData.append('todo_comment', payload.comment || '');
                        const response = await axios.post('/api/index.php?model=todo&method=api_update', formData);
                        if (response.data.status !== 'success') {
                            todo.todo_title = prev.title;
                            todo.todo_priority = prev.priority;
                            todo.todo_term = prev.term;
                            todo.todo_link = prev.link;
                            todo.todo_comment = prev.comment;
                            console.error('Failed to update todo');
                        }
                        this.updateTotalCount();
                    } catch (error) {
                        todo.todo_title = prev.title;
                        todo.todo_priority = prev.priority;
                        todo.todo_term = prev.term;
                        todo.todo_link = prev.link;
                        todo.todo_comment = prev.comment;
                        console.error('Error updating todo', error);
                    }
                },

                /**
                 * Delete a Todo
                 */
                async deleteTodo(todo) {
                    if (!confirm('Are you sure?')) return;

                    try {
                        const formData = new FormData();
                        formData.append('id', todo.id);
                        
                        const response = await axios.post('/api/index.php?model=todo&method=api_delete', formData);
                        if (response.data.status === 'success') {
                            this.loadTodos();
                        } else {
                            alert('Failed to delete: ' + response.data.message);
                        }
                    } catch (error) {
                        console.error('Error deleting todo', error);
                    }
                },

                /**
                 * Delete completed custom todos (with Swal confirm)
                 */
                async deleteCompletedTodos() {
                    const msg = (typeof i18next !== 'undefined' && i18next.t) ? i18next.t('Delete all completed todos? This cannot be undone.') : '完了済みのTodoをすべて削除しますか？元に戻せません。';
                    const confirmText = (typeof i18next !== 'undefined' && i18next.t) ? i18next.t('Delete completed') : '完了済みを削除';
                    const cancelText = (typeof i18next !== 'undefined' && i18next.t) ? i18next.t('Cancel') : 'キャンセル';
                    const result = await (window.Swal && typeof window.Swal.fire === 'function'
                        ? window.Swal.fire({
                            title: (typeof i18next !== 'undefined' && i18next.t) ? i18next.t('Confirm') : '確認',
                            text: msg,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: confirmText,
                            cancelButtonText: cancelText
                        })
                        : Promise.resolve({ isConfirmed: confirm(msg) }));
                    if (!result.isConfirmed) return;
                    try {
                        const response = await axios.post('/api/index.php?model=todo&method=api_delete_completed', new FormData());
                        if (response.data && response.data.status === 'success') {
                            await this.loadTodos();
                            this.updateTotalCount();
                            if (window.Swal && typeof window.Swal.fire === 'function') {
                                window.Swal.fire({
                                    icon: 'success',
                                    title: (typeof i18next !== 'undefined' && i18next.t) ? i18next.t('Deleted') : '削除しました',
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            }
                        } else {
                            alert(response.data && response.data.message ? response.data.message : 'Failed to delete');
                        }
                    } catch (error) {
                        console.error('Error deleting completed todos', error);
                        alert(error.message || 'Request failed');
                    }
                },

                updateTotalCount() {
                    const count = this.myTaskCount + (this.todos ? this.todos.filter(t => t.todo_complete == 0).length : 0);
                    this.todoCount = count;
                    
                    // Update badge outside Vue scope
                    const badge = document.getElementById('todo-badge');
                    if (badge) {
                        if (count > 0) {
                            badge.innerText = count;
                            badge.style.display = 'block';
                            badge.classList.remove('visually-hidden');
                        } else {
                            badge.style.display = 'none';
                            badge.classList.add('visually-hidden');
                        }
                    }
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
                
                getTodoPriorityLabel(priority) {
                    if (priority >= 100) return '高';
                    if (priority <= 10) return '低';
                    return '中';
                },
                
                getTodoPriorityColor(priority) {
                    if (priority >= 100) return 'danger';
                    if (priority <= 10) return 'secondary'; // or info
                    return 'primary';
                },

                formatDate(dateString) {
                    if (!dateString || dateString === '0000-00-00 00:00:00' || dateString === '0000-00-00') return '-';
                    // Use moment if available for consistent formatting with task-overview.js
                    if (typeof moment !== 'undefined') {
                        return moment(dateString).format('M月D日 H:mm');
                    }
                    const d = new Date(dateString);
                    return d.toLocaleDateString();
                },

                /**
                 * Time remaining or 期限超過 for a due date. Returns { text, class, isOverdue } or null.
                 */
                getTimeRemainingForDue(dateString) {
                    if (!dateString || dateString === '0000-00-00 00:00:00' || dateString === '0000-00-00') return null;
                    const now = typeof moment !== 'undefined' && moment.tz
                        ? moment.tz('Asia/Tokyo')
                        : moment();
                    const end = typeof moment !== 'undefined' && moment.tz
                        ? moment.tz(dateString, 'Asia/Tokyo')
                        : moment(dateString);
                    if (!end.isValid()) return null;
                    const isVietnamese = typeof i18next !== 'undefined' && i18next.isInitialized && i18next.language === 'vi';
                    const dayLabel = (typeof i18next !== 'undefined' && i18next.isInitialized && i18next.t('日')) ? i18next.t('日') : '日';
                    const hourLabel = (typeof i18next !== 'undefined' && i18next.isInitialized && i18next.t('時間')) ? i18next.t('時間') : '時間';
                    const minuteLabel = (typeof i18next !== 'undefined' && i18next.isInitialized && i18next.t('分')) ? i18next.t('分') : '分';
                    const overdueLabel = (typeof i18next !== 'undefined' && i18next.isInitialized && i18next.t('期限超過')) ? i18next.t('期限超過') : '期限超過';
                    const remainingLabel = (typeof i18next !== 'undefined' && i18next.isInitialized && i18next.t('残り')) ? i18next.t('残り') : '残り';
                    const fmt = (v, l) => isVietnamese ? v + ' ' + l : v + l;
                    const join = (parts) => isVietnamese ? parts.filter(Boolean).join(' ') : parts.filter(Boolean).join('');
                    if (end.isBefore(now)) {
                        const diff = now.diff(end);
                        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                        let text = overdueLabel;
                        if (days > 0) text = join([fmt(days, dayLabel), fmt(hours, hourLabel), fmt(minutes, minuteLabel), overdueLabel]);
                        else if (hours > 0) text = join([fmt(hours, hourLabel), fmt(minutes, minuteLabel), overdueLabel]);
                        else if (minutes > 0) text = join([fmt(minutes, minuteLabel), overdueLabel]);
                        return { text: text, class: 'bg-danger', isOverdue: true };
                    } else {
                        const diff = end.diff(now);
                        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                        let text, cls;
                        if (days > 0) {
                            text = join([remainingLabel, fmt(days, dayLabel), fmt(hours, hourLabel), fmt(minutes, minuteLabel)]);
                            cls = 'bg-label-info';
                        } else if (hours > 0) {
                            text = join([remainingLabel, fmt(hours, hourLabel), fmt(minutes, minuteLabel)]);
                            cls = hours <= 24 ? 'bg-label-warning' : 'bg-label-info';
                        } else {
                            text = join([remainingLabel, fmt(minutes, minuteLabel)]);
                            cls = 'bg-label-warning';
                        }
                        return { text: text, class: cls, isOverdue: false };
                    }
                },

                /**
                 * Check if current user has acknowledged a task
                 */
                isAcknowledged(task) {
                    if (!task || !task.acknowledgements || !window.currentUserId) return false;
                    const userId = window.currentUserId.toString();
                    const ack = task.acknowledgements[userId];
                    return ack && ack.acknowledged == 1;
                },

                /**
                 * Acknowledge a task
                 * @param {Object} task
                 * @param {Object} options - { silent: true } to skip message and loadTasks (caller will reload)
                 */
                async acknowledgeTask(task, options = {}) {
                    const silent = options.silent === true;
                    try {
                        const formData = new FormData();
                        formData.append('task_id', task.id);

                        const response = await axios.post(
                            '/api/index.php?model=task&method=acknowledgeTask',
                            formData
                        );

                        if (response.data && response.data.status === 'success') {
                            if (!task.acknowledgements) {
                                task.acknowledgements = {};
                            }
                            const currentUserIdStr = window.currentUserId.toString();
                            task.acknowledgements[currentUserIdStr] = {
                                acknowledged: 1,
                                acknowledged_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
                            };
                            if (!silent && typeof showMessage === 'function') {
                                showMessage('タスクを受領しました。', false);
                            }
                        } else {
                            if (!silent) throw new Error(response.data.message || 'Failed to acknowledge task');
                        }
                    } catch (error) {
                        console.error('Error acknowledging task:', error);
                        if (!silent && typeof showMessage === 'function') {
                            showMessage('タスクの受領に失敗しました。', true);
                        }
                    }
                },

                /**
                 * Update task status
                 */
                async updateTaskStatus(task, newStatus) {
                    if (!task || !task.id) return;
                    
                    try {
                        const formData = new FormData();
                        formData.append('id', task.id);
                        formData.append('status', newStatus);
                        formData.append('project_id', task.project_id);
                        
                        const response = await axios.post(
                            '/api/index.php?model=task&method=updateStatus',
                            formData
                        );
                        
                        if (response.data.status == 'success') {
                            task.status = newStatus;
                            if (typeof showMessage === 'function') {
                                showMessage('ステータスを更新しました。', false);
                            }
                            // Nếu user được giao task và chưa acknowledge thì tự động acknowledge
                            if (!this.isAcknowledged(task)) {
                                await this.acknowledgeTask(task, { silent: true });
                            }
                            await this.loadTasks();
                        } else {
                            throw new Error(response.data.message);
                        }
                    } catch (error) {
                        console.error('Error updating status:', error);
                        if (typeof showMessage === 'function') {
                            showMessage(error.message || 'ステータスの更新に失敗しました', true);
                        }
                    }
                },

                /**
                 * Update task progress
                 */
                async updateTaskProgress(task, newProgress) {
                    if (!task || !task.id) return;
                    
                    try {
                        const formData = new FormData();
                        formData.append('id', task.id);
                        formData.append('progress', newProgress);
                        formData.append('project_id', task.project_id);
                        
                        const response = await axios.post(
                            '/api/index.php?model=task&method=updateProgress',
                            formData
                        );
                        
                        if (response.data.status == 'success') {
                            task.progress = newProgress;
                            if (typeof showMessage === 'function') {
                                showMessage('進捗を更新しました。', false);
                            }
                            // Nếu user được giao task và chưa acknowledge thì tự động acknowledge
                            if (!this.isAcknowledged(task)) {
                                await this.acknowledgeTask(task, { silent: true });
                            }
                            await this.loadTasks();
                        } else {
                            throw new Error(response.data.message);
                        }
                    } catch (error) {
                        console.error('Error updating progress:', error);
                        if (typeof showMessage === 'function') {
                            showMessage(error.message || '進捗の更新に失敗しました', true);
                        }
                    }
                }
            }
        });
        todoApp.mount('#todoApp');
    }
});
