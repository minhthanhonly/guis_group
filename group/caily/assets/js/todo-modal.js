/**
 * Todo Modal Management
 * Handles "My Tasks" and "Custom Todos" tabs
 */

const TODO_TASK_KINDS_WITHOUT_DRAWING_LINK = ['チェック', '検討', '相談・会議', '連絡'];

const TODO_SERVER_TIMEZONE = 'Asia/Tokyo';
const TODO_VIETNAM_TIMEZONE = 'Asia/Ho_Chi_Minh';
const TODO_DATETIME_MOMENT_FORMAT = 'YYYY/M/D HH:mm';
const TODO_DATETIME_JA_DISPLAY_FORMAT = 'YYYY年M月D日 HH:mm';
const TODO_DATETIME_PARSE_FORMATS = [
    'YYYY-MM-DD HH:mm:ss',
    'YYYY-MM-DD HH:mm',
    'YYYY/M/D HH:mm',
    'YYYY/MM/DD HH:mm',
    'YYYY/M/D H:mm',
    'YYYY/MM/DD H:mm',
    'YYYY-MM-DD',
    'YYYY/M/D'
];

const TODO_DEFAULT_TASK_KINDS = [
    { value: '新規作成', label: '新規作成', color: 'success' },
    { value: '修正(エラー)', label: '修正(エラー)', color: 'danger' },
    { value: '修正(変更)', label: '修正(変更)', color: 'warning' },
    { value: 'チェック', label: 'チェック', color: 'primary' },
    { value: '連絡', label: '連絡', color: 'info' },
    { value: '検討', label: '検討', color: 'secondary' },
    { value: '相談・会議', label: '相談・会議', color: 'dark' }
];

function todoNormalizeTaskKind(value, taskKinds) {
    const kinds = taskKinds || TODO_DEFAULT_TASK_KINDS;
    const v = (value || '').trim();
    return kinds.some(function (k) { return k.value === v; }) ? v : '';
}

function todoGetTaskKindLabel(value, taskKinds, tFn) {
    const normalized = todoNormalizeTaskKind(value, taskKinds);
    if (!normalized) return '—';
    const kind = (taskKinds || TODO_DEFAULT_TASK_KINDS).find(function (k) { return k.value === normalized; });
    if (!kind) return value || '—';
    return tFn ? tFn(kind.label, kind.label) : kind.label;
}

function todoGetTaskKindBadgeClass(value, taskKinds) {
    const normalized = todoNormalizeTaskKind(value, taskKinds);
    if (!normalized) return 'bg-label-secondary';
    const kind = (taskKinds || TODO_DEFAULT_TASK_KINDS).find(function (k) { return k.value === normalized; });
    const color = kind && kind.color ? kind.color : 'secondary';
    return 'bg-label-' + color;
}

function mountTodoApp() {
    if (!document.getElementById('todoApp') || typeof Vue === 'undefined') {
        return;
    }
    const todoApp = Vue.createApp({
            data() {
                return {
                    activeTab: 'tasks',
                    tasks: [],
                    usersById: {},
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
                        { value: 'todo', label: '未開始', i18nKey: '未開始', color: 'secondary' },
                        { value: 'in-progress', label: '進行中', i18nKey: '進行中', color: 'primary' },
                        { value: 'confirming', label: '確認中', i18nKey: '確認中', color: 'warning' },
                        { value: 'paused', label: '一時停止', i18nKey: '一時停止', color: 'warning' },
                        { value: 'completed', label: '完了', i18nKey: '完了', color: 'success' },
                        { value: 'cancelled', label: 'キャンセル', i18nKey: 'キャンセル', color: 'danger' }
                    ],
                    taskPriorities: [
                        { value: 'low', label: '低', color: 'secondary' },
                        { value: 'medium', label: '中', color: 'primary' },
                        { value: 'high', label: '高', color: 'warning' },
                        { value: 'urgent', label: '緊急', color: 'danger' }
                    ],
                    taskKinds: TODO_DEFAULT_TASK_KINDS.slice(),
                    progressOptions: Array.from({ length: 21 }, (_, i) => i * 5),
                    refreshIntervalId: null,
                    editingTodoId: null,
                    editingTodoTitle: '',
                    editingTodoPriority: 50,
                    editingTodoTerm: '',
                    editingTodoLink: '',
                    editingTodoComment: '',
                    taskTimerTogglingTaskIds: {},
                    estimatedHoursSavingTaskIds: {},
                    workloadModal: {
                        show: false,
                        taskId: null,
                        hours: 0,
                        minutes: 0,
                        saving: false
                    },
                    drawingCountSavingTaskIds: {},
                    showTaskNoteModal: false,
                    taskNoteModal: {
                        taskId: null,
                        projectId: null,
                        content: '',
                        canEdit: false
                    },
                    quillTaskNoteInstance: null,
                    quillTaskNoteContent: '',
                    quillTaskNoteInitTimer: null,
                    todoSortableInstance: null,
                    savingTodoOrder: false
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
                        dateFormat: 'Y/m/d H:i',
                        enableTime: true,
                        time_24hr: true,
                        minDate: 'today',
                        locale: this.getFlatpickrLocaleName(),
                        onChange: (selectedDates, dateStr) => {
                            this.newTodo.deadline = dateStr;
                        }
                    });
                }
                
                // Refresh on offcanvas open and set default tab by count
                const offcanvasElement = document.getElementById('offcanvasTodo');
                if (offcanvasElement) {
                    offcanvasElement.addEventListener('show.bs.offcanvas', () => {
                        Promise.all([this.loadTasks(), this.loadTodos()]).then(() => {
                            this.applyDefaultTab();
                            this.$nextTick(() => this.initMyTaskStatusDropdowns());
                        });
                    });
                }
                // Refresh when a todo was added from context menu (e.g. project list/detail)
                this._onTodoAddedFromContextBound = () => { this.loadTodos(); };
                document.body.addEventListener('todo-added-from-context', this._onTodoAddedFromContextBound);
                this._onTaskTimerChangedBound = (event) => this.onTaskTimerChanged(event);
                document.addEventListener('task-timer-changed', this._onTaskTimerChangedBound);
                // Refresh data every 1 minute (skip when inline editing a todo)
                this.refreshIntervalId = setInterval(() => {
                    if (this.editingTodoId) return;
                    this.loadTasks();
                    this.loadTodos();
                }, 60000);

                this._onI18nLanguageChanged = () => {
                    this.$forceUpdate();
                    this.initTodoFlatpickrLocales();
                };
                if (typeof i18next !== 'undefined' && i18next.on) {
                    i18next.on('languageChanged', this._onI18nLanguageChanged);
                }
            },
            updated() {
                this.$nextTick(() => {
                    this.initMyTaskStatusDropdowns();
                    this.initTodoSortable();
                });
            },
            beforeUnmount() {
                if (this.refreshIntervalId) {
                    clearInterval(this.refreshIntervalId);
                    this.refreshIntervalId = null;
                }
                if (this._onTodoAddedFromContextBound) {
                    document.body.removeEventListener('todo-added-from-context', this._onTodoAddedFromContextBound);
                }
                if (this._onTaskTimerChangedBound) {
                    document.removeEventListener('task-timer-changed', this._onTaskTimerChangedBound);
                }
                this.destroyFpEdit();
                this.destroyTodoSortable();
                this.destroyQuillTaskNoteEditor();
                if (typeof i18next !== 'undefined' && i18next.off && this._onI18nLanguageChanged) {
                    i18next.off('languageChanged', this._onI18nLanguageChanged);
                }
            },
            methods: {
                /**
                 * Set default tab: prioritize My Tasks when both tabs have items.
                 */
                applyDefaultTab() {
                    const todoCount = this.incompleteTodoCount;
                    const taskCount = this.myTaskCount;
                    if (taskCount >= 1) {
                        this.activeTab = 'tasks';
                    } else if (todoCount >= 1) {
                        this.activeTab = 'todos';
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
                            this.usersById = {};
                            if (Array.isArray(response.data.users)) {
                                response.data.users.forEach((user) => {
                                    if (user && user.id != null) {
                                        this.usersById[user.id] = user;
                                    }
                                });
                            }
                        } else {
                            this.tasks = [];
                            this.myTaskCount = 0;
                            this.usersById = {};
                        }
                    } catch (error) {
                        console.error('Failed to load tasks', error);
                        this.tasks = [];
                    } finally {
                        this.loadingTasks = false;
                        this.updateTotalCount();
                        this.$nextTick(() => this.initMyTaskStatusDropdowns());
                        if (window.TaskTimer && window.TaskTimer.active) {
                            window.TaskTimer.refresh();
                        }
                    }
                },

                initMyTaskStatusDropdowns() {
                    if (typeof bootstrap === 'undefined' || !bootstrap.Dropdown) return;
                    const root = document.getElementById('offcanvasTodo');
                    if (!root) return;
                    root.querySelectorAll('.my-task-status-dropdown [data-bs-toggle="dropdown"]').forEach((el) => {
                        const existing = bootstrap.Dropdown.getInstance(el);
                        if (existing) {
                            existing.dispose();
                        }
                        new bootstrap.Dropdown(el);
                    });
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
                        this.$nextTick(() => this.initTodoSortable());
                    }
                },

                destroyTodoSortable() {
                    if (this.todoSortableInstance) {
                        this.todoSortableInstance.destroy();
                        this.todoSortableInstance = null;
                    }
                },

                ensureSortableLoaded() {
                    if (typeof Sortable !== 'undefined') {
                        return Promise.resolve();
                    }
                    if (window.__sortableJsLoading) {
                        return window.__sortableJsLoading;
                    }
                    const loader = window.AppLoader;
                    if (!loader || typeof loader.loadScript !== 'function') {
                        return Promise.reject(new Error('AppLoader missing'));
                    }
                    window.__sortableJsLoading = loader.loadScript(
                        'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js'
                    ).catch(function(err) {
                        delete window.__sortableJsLoading;
                        throw err;
                    });
                    return window.__sortableJsLoading;
                },

                initTodoSortable() {
                    if (this.activeTab !== 'todos' || this.loadingTodos || this.editingTodoId) {
                        this.destroyTodoSortable();
                        return;
                    }
                    const listBody = document.getElementById('customTodoListBody');
                    if (!listBody) {
                        return;
                    }
                    if (typeof Sortable === 'undefined') {
                        if (this._sortableLoadPending) {
                            return;
                        }
                        this._sortableLoadPending = true;
                        this.ensureSortableLoaded().then(() => {
                            this._sortableLoadPending = false;
                            this.$nextTick(() => this.initTodoSortable());
                        }).catch((err) => {
                            this._sortableLoadPending = false;
                            console.error('Failed to load Sortable for Todo List:', err);
                        });
                        return;
                    }
                    if (this.todoSortableInstance && this.todoSortableInstance.el === listBody) {
                        return;
                    }
                    this.destroyTodoSortable();
                    this.todoSortableInstance = Sortable.create(listBody, {
                        animation: 150,
                        handle: '.todo-drag-handle',
                        draggable: 'tr',
                        ghostClass: 'sortable-ghost',
                        chosenClass: 'sortable-chosen',
                        filter: '.todo-row-editing',
                        preventOnFilter: true,
                        onMove: (evt) => {
                            const draggedComplete = evt.dragged.classList.contains('table-secondary');
                            const relatedComplete = evt.related.classList.contains('table-secondary');
                            return draggedComplete === relatedComplete;
                        },
                        onEnd: (evt) => {
                            if (evt.oldIndex === evt.newIndex) {
                                return;
                            }
                            this.onTodoDragEnd(evt);
                        }
                    });
                },

                async onTodoDragEnd(evt) {
                    const listBody = document.getElementById('customTodoListBody');
                    if (!listBody) {
                        return;
                    }
                    const rowEls = Array.from(listBody.querySelectorAll('tr[data-id]'));
                    const orderedIds = rowEls
                        .map((row) => parseInt(row.getAttribute('data-id'), 10))
                        .filter((id) => !isNaN(id) && id > 0);
                    if (orderedIds.length === 0) {
                        return;
                    }
                    const prevTodos = this.todos.slice();
                    const todoMap = {};
                    prevTodos.forEach((todo) => {
                        todoMap[todo.id] = todo;
                    });
                    this.todos = orderedIds.map((id) => todoMap[id]).filter(Boolean);
                    if (this.savingTodoOrder) {
                        return;
                    }
                    this.savingTodoOrder = true;
                    try {
                        const formData = new FormData();
                        formData.append('ids', orderedIds.join(','));
                        const response = await axios.post('/api/index.php?model=todo&method=api_reorder', formData);
                        if (!response.data || response.data.status !== 'success') {
                            this.todos = prevTodos;
                            this.$nextTick(() => this.initTodoSortable());
                            console.error('Failed to save todo order', response.data);
                        }
                    } catch (error) {
                        this.todos = prevTodos;
                        this.$nextTick(() => this.initTodoSortable());
                        console.error('Error saving todo order', error);
                    } finally {
                        this.savingTodoOrder = false;
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
                    this.destroyTodoSortable();
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
                        dateFormat: 'Y/m/d H:i',
                        enableTime: true,
                        time_24hr: true,
                        locale: this.getFlatpickrLocaleName(),
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
                    this.$nextTick(() => this.initTodoSortable());
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
                    const s = this.taskStatuses.find(item => item.value === status);
                    if (!s) return status || '—';
                    return this.t(s.i18nKey || s.label, s.label);
                },

                getStatusButtonClass(status) {
                    const s = this.taskStatuses.find(item => item.value === status);
                    return `btn-${s?.color || 'secondary'}`;
                },

                getStatusColor(status) {
                    const s = this.taskStatuses.find(item => item.value === status);
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

                normalizeTaskKind(value) {
                    return todoNormalizeTaskKind(value, this.taskKinds);
                },

                getTaskKindLabel(value) {
                    return todoGetTaskKindLabel(value, this.taskKinds, this.t.bind(this));
                },

                getTaskKindBadgeClass(value) {
                    return todoGetTaskKindBadgeClass(value, this.taskKinds);
                },

                getTaskKindDisplayValue(task) {
                    return this.normalizeTaskKind(task && task.task_kind);
                },

                isDrawingLinkVisibleForTask(task) {
                    if (!task) return false;
                    const kind = this.getTaskKindDisplayValue(task);
                    return TODO_TASK_KINDS_WITHOUT_DRAWING_LINK.indexOf(kind) === -1;
                },

                canEditDrawingLink(task) {
                    return !!(task && task.can_edit_drawing);
                },

                isTaskLinkedToDrawings(task) {
                    if (!task) return false;
                    const n = parseInt(task.drawing_count, 10);
                    return !Number.isNaN(n) && n > 0;
                },

                isDrawingCountSaving(taskId) {
                    return !!(taskId && this.drawingCountSavingTaskIds[taskId]);
                },

                setDrawingCountSaving(taskId, saving) {
                    if (!taskId) return;
                    if (saving) {
                        this.drawingCountSavingTaskIds = Object.assign({}, this.drawingCountSavingTaskIds, { [taskId]: true });
                    } else {
                        const next = Object.assign({}, this.drawingCountSavingTaskIds);
                        delete next[taskId];
                        this.drawingCountSavingTaskIds = next;
                    }
                },

                applyTaskDrawingCount(taskId, drawingCount) {
                    const count = parseInt(drawingCount, 10);
                    if (!taskId || Number.isNaN(count)) return;
                    const task = (this.tasks || []).find(function (t) { return t.id === taskId; });
                    if (task) {
                        task.drawing_count = count;
                    }
                },

                async saveTaskDrawingCount(task, value) {
                    if (!task || !task.id || !this.canEditDrawingLink(task) || !this.isTaskLinkedToDrawings(task)) {
                        return;
                    }
                    const n = parseInt(value, 10);
                    const drawingCount = Number.isNaN(n) || n < 1 ? 1 : n;
                    const currentCount = parseInt(task.drawing_count, 10);
                    if (currentCount === drawingCount || this.isDrawingCountSaving(task.id)) {
                        return;
                    }
                    this.setDrawingCountSaving(task.id, true);
                    try {
                        const formData = new FormData();
                        formData.append('id', task.id);
                        formData.append('project_id', task.project_id);
                        formData.append('linked', '1');
                        formData.append('drawing_count', drawingCount);
                        const response = await axios.post('/api/index.php?model=task&method=updateDrawingLink', formData);
                        if (response.data && response.data.status === 'success') {
                            const savedCount = response.data.drawing_count != null ? response.data.drawing_count : drawingCount;
                            this.applyTaskDrawingCount(task.id, savedCount);
                        } else if (typeof showMessage === 'function') {
                            showMessage(response.data?.message || this.t('図面の更新に失敗しました', '図面の更新に失敗しました'), true);
                        }
                    } catch (error) {
                        if (typeof showMessage === 'function') {
                            showMessage(this.t('図面の更新に失敗しました', '図面の更新に失敗しました'), true);
                        }
                    } finally {
                        this.setDrawingCountSaving(task.id, false);
                    }
                },

                async toggleTaskDrawingLink(task, event) {
                    if (!task || !task.id || !this.canEditDrawingLink(task)) {
                        if (event && event.target) {
                            event.target.checked = this.isTaskLinkedToDrawings(task);
                        }
                        return;
                    }
                    const linked = !!(event && event.target && event.target.checked);
                    const prevCount = parseInt(task.drawing_count, 10);
                    const drawingCount = linked
                        ? (!Number.isNaN(prevCount) && prevCount > 0 ? prevCount : 1)
                        : 0;
                    try {
                        const formData = new FormData();
                        formData.append('id', task.id);
                        formData.append('project_id', task.project_id);
                        formData.append('linked', linked ? '1' : '0');
                        formData.append('drawing_count', drawingCount);
                        const response = await axios.post('/api/index.php?model=task&method=updateDrawingLink', formData);
                        if (response.data && response.data.status === 'success') {
                            const savedCount = response.data.drawing_count != null ? response.data.drawing_count : drawingCount;
                            this.applyTaskDrawingCount(task.id, savedCount);
                        } else {
                            if (event && event.target) {
                                event.target.checked = this.isTaskLinkedToDrawings(task);
                            }
                            if (typeof showMessage === 'function') {
                                showMessage(response.data?.message || this.t('図面の更新に失敗しました', '図面の更新に失敗しました'), true);
                            }
                        }
                    } catch (error) {
                        if (event && event.target) {
                            event.target.checked = this.isTaskLinkedToDrawings(task);
                        }
                        if (typeof showMessage === 'function') {
                            showMessage(this.t('図面の更新に失敗しました', '図面の更新に失敗しました'), true);
                        }
                    }
                },

                formatEstimatedHours(value) {
                    const n = parseFloat(value);
                    if (Number.isNaN(n) || n <= 0) return '—';
                    const formatted = Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
                    return `${formatted}h`;
                },

                formatWorkloadPickerDisplay(value) {
                    const n = parseFloat(value);
                    if (Number.isNaN(n) || n <= 0) return '';
                    const formatted = Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
                    return `${formatted}h`;
                },

                parseEstimatedHoursToHoursMinutes(value) {
                    const n = parseFloat(value);
                    if (Number.isNaN(n) || n < 0) {
                        return { hours: 0, minutes: 0 };
                    }
                    const totalMinutes = Math.round(n * 60);
                    return {
                        hours: Math.floor(totalMinutes / 60),
                        minutes: totalMinutes % 60
                    };
                },

                convertHoursMinutesToEstimatedHours(hours, minutes) {
                    const h = parseInt(hours, 10);
                    const m = parseInt(minutes, 10);
                    const safeH = Number.isNaN(h) || h < 0 ? 0 : h;
                    const safeM = Number.isNaN(m) || m < 0 ? 0 : m;
                    const totalMinutes = safeH * 60 + safeM;
                    return Math.round((totalMinutes / 60) * 100) / 100;
                },

                getWorkloadModalPreview() {
                    const total = this.convertHoursMinutesToEstimatedHours(
                        this.workloadModal.hours,
                        this.workloadModal.minutes
                    );
                    if (total <= 0) return '0h';
                    return this.formatEstimatedHours(total);
                },

                canEditTaskWorkload(task) {
                    if (!task || !task.id) return false;
                    return this.isAssignedToMe(task);
                },

                openWorkloadModal(task) {
                    if (!task || !task.id || !this.canEditTaskWorkload(task) || this.isEstimatedHoursSaving(task.id)) {
                        return;
                    }
                    const parts = this.parseEstimatedHoursToHoursMinutes(task.estimated_hours);
                    this.workloadModal.taskId = task.id;
                    this.workloadModal.hours = parts.hours;
                    this.workloadModal.minutes = parts.minutes;
                    this.workloadModal.saving = false;
                    this.workloadModal.show = true;
                    // Modal is v-if; re-apply translations after DOM mount
                    this.$nextTick(() => {
                        if (typeof window.applyDataI18n === 'function') {
                            const root = document.getElementById('todoApp') || document.getElementById('offcanvasTodo');
                            window.applyDataI18n(root || document);
                        }
                    });
                },

                closeWorkloadModal() {
                    this.workloadModal.show = false;
                    this.workloadModal.taskId = null;
                    this.workloadModal.hours = 0;
                    this.workloadModal.minutes = 0;
                    this.workloadModal.saving = false;
                },

                async confirmWorkloadModal() {
                    const taskId = this.workloadModal.taskId;
                    if (!taskId) return;
                    const task = this.tasks.find(function (t) { return parseInt(t.id, 10) === parseInt(taskId, 10); });
                    if (!task || !this.canEditTaskWorkload(task)) return;
                    const hours = this.convertHoursMinutesToEstimatedHours(
                        this.workloadModal.hours,
                        this.workloadModal.minutes
                    );
                    this.workloadModal.saving = true;
                    try {
                        await this.saveTaskEstimatedHours(task, hours);
                        this.closeWorkloadModal();
                    } finally {
                        this.workloadModal.saving = false;
                    }
                },

                isEstimatedHoursSaving(taskId) {
                    return !!(taskId && this.estimatedHoursSavingTaskIds[taskId]);
                },

                setEstimatedHoursSaving(taskId, saving) {
                    if (!taskId) return;
                    if (saving) {
                        this.estimatedHoursSavingTaskIds = Object.assign({}, this.estimatedHoursSavingTaskIds, { [taskId]: true });
                    } else {
                        const next = Object.assign({}, this.estimatedHoursSavingTaskIds);
                        delete next[taskId];
                        this.estimatedHoursSavingTaskIds = next;
                    }
                },

                async saveTaskEstimatedHours(task, value) {
                    if (!task || !task.id || !this.canEditTaskWorkload(task)) {
                        return;
                    }
                    const n = parseFloat(value);
                    const hours = Number.isNaN(n) || n < 0 ? 0 : Math.round(n * 100) / 100;
                    const canonical = this.tasks.find(function (t) { return parseInt(t.id, 10) === parseInt(task.id, 10); });
                    const current = canonical
                        ? parseFloat(canonical.estimated_hours)
                        : parseFloat(task.estimated_hours);
                    const currentHours = Number.isNaN(current) ? 0 : Math.round(current * 100) / 100;
                    if (currentHours === hours) {
                        return;
                    }
                    if (this.isEstimatedHoursSaving(task.id)) {
                        return;
                    }
                    this.setEstimatedHoursSaving(task.id, true);
                    try {
                        const formData = new FormData();
                        formData.append('id', task.id);
                        formData.append('project_id', task.project_id);
                        formData.append('estimated_hours', hours);
                        const response = await axios.post('/api/index.php?model=task&method=updateEstimatedHours', formData);
                        if (response.data && response.data.status === 'success') {
                            const savedHours = response.data.estimated_hours != null ? response.data.estimated_hours : hours;
                            this.applyTaskEstimatedHours(task.id, savedHours);
                            if (window.TaskTimer && window.TaskTimer.active
                                && parseInt(window.TaskTimer.active.task_id, 10) === parseInt(task.id, 10)) {
                                window.TaskTimer.active.estimated_hours = savedHours;
                                if (typeof window.TaskTimer.updateWidget === 'function') {
                                    window.TaskTimer.updateWidget();
                                }
                            }
                        } else if (typeof showMessage === 'function') {
                            showMessage(response.data?.message || this.t('工数の更新に失敗しました', '工数の更新に失敗しました'), true);
                        }
                    } catch (error) {
                        if (typeof showMessage === 'function') {
                            showMessage(this.t('工数の更新に失敗しました', '工数の更新に失敗しました'), true);
                        }
                    } finally {
                        this.setEstimatedHoursSaving(task.id, false);
                    }
                },

                decodeHtmlEntities(str) {
                    const txt = document.createElement('textarea');
                    let decoded = String(str || '');
                    // Decode repeatedly to handle double-encoded entities like &amp;nbsp;
                    for (let i = 0; i < 3; i++) {
                        txt.innerHTML = decoded;
                        const next = txt.value;
                        if (next === decoded) break;
                        decoded = next;
                    }
                    return decoded;
                },

                getTaskNoteSnippet(note, maxLen) {
                    if (!note) return '';
                    const decoded = this.decodeHtmlEntities(String(note));
                    const text = decoded
                        .replace(/<[^>]*>/g, '')
                        .replace(/\u00A0/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim();
                    if (!text) return '';
                    const limit = maxLen || 28;
                    return text.length <= limit ? text : `${text.substring(0, limit)}…`;
                },

                canEditTaskNote(task) {
                    if (!task || !task.id) return false;
                    return this.isAssignedToMe(task);
                },

                openTaskNoteModal(task) {
                    if (!task || !task.id) return;
                    this.destroyQuillTaskNoteEditor();
                    this.showTaskNoteModal = true;
                    this.taskNoteModal = {
                        taskId: task.id,
                        projectId: task.project_id,
                        content: task.note || '',
                        canEdit: this.canEditTaskNote(task)
                    };
                    this.$nextTick(() => {
                        if (this.taskNoteModal.canEdit) {
                            this.initQuillTaskNoteEditor();
                        }
                    });
                },

                closeTaskNoteModal() {
                    this.showTaskNoteModal = false;
                    this.destroyQuillTaskNoteEditor();
                    this.taskNoteModal = { taskId: null, projectId: null, content: '', canEdit: false };
                    this.quillTaskNoteContent = '';
                },

                resetQuillTaskNoteDom() {
                    const el = document.getElementById('quill_mytask_note_content');
                    if (!el) return;
                    const parent = el.closest('.custom_editor');
                    if (parent) {
                        parent.querySelectorAll('.ql-toolbar').forEach((toolbar) => toolbar.remove());
                    }
                    el.innerHTML = '';
                    el.className = 'custom_editor_content';
                },

                initQuillTaskNoteEditor() {
                    if (!this.showTaskNoteModal) return;
                    if (this.quillTaskNoteInitTimer) {
                        clearTimeout(this.quillTaskNoteInitTimer);
                        this.quillTaskNoteInitTimer = null;
                    }
                    this.quillTaskNoteInitTimer = setTimeout(() => {
                        this.quillTaskNoteInitTimer = null;
                        if (!this.showTaskNoteModal || !this.taskNoteModal.canEdit) return;
                        if (this.quillTaskNoteInstance) return;
                        const el = document.getElementById('quill_mytask_note_content');
                        if (!el || !window.Quill) return;
                        this.resetQuillTaskNoteDom();
                        const toolbarOptions = [
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ color: [] }, { background: [] }],
                            [{ list: 'ordered' }, { list: 'bullet' }],
                            [{ header: '1' }, { header: '2' }, 'blockquote'],
                            ['link', 'clean']
                        ];
                        this.quillTaskNoteInstance = new Quill(el, {
                            bounds: el,
                            placeholder: this.t('メモの詳細を入力してください...', 'メモの詳細を入力してください...'),
                            modules: { toolbar: { container: toolbarOptions } },
                            theme: 'snow'
                        });
                        if (this.taskNoteModal.content) {
                            this.quillTaskNoteInstance.root.innerHTML = this.decodeHtmlEntities(this.taskNoteModal.content);
                        }
                        this.quillTaskNoteContent = this.quillTaskNoteInstance.getSemanticHTML();
                        this.quillTaskNoteInstance.on('text-change', () => {
                            this.quillTaskNoteContent = this.quillTaskNoteInstance.getSemanticHTML();
                        });
                    }, 200);
                },

                destroyQuillTaskNoteEditor() {
                    if (this.quillTaskNoteInitTimer) {
                        clearTimeout(this.quillTaskNoteInitTimer);
                        this.quillTaskNoteInitTimer = null;
                    }
                    if (this.quillTaskNoteInstance) {
                        try {
                            const toolbar = this.quillTaskNoteInstance.getModule('toolbar');
                            if (toolbar && toolbar.container) {
                                toolbar.container.remove();
                            }
                        } catch (e) {}
                        this.quillTaskNoteInstance = null;
                    }
                    this.resetQuillTaskNoteDom();
                    this.quillTaskNoteContent = '';
                },

                async saveTaskNote() {
                    const rawContent = (this.quillTaskNoteContent && this.quillTaskNoteContent.trim())
                        || (this.taskNoteModal.content || '').trim();
                    if (!this.taskNoteModal.taskId || !this.taskNoteModal.projectId) return;
                    try {
                        const formData = new FormData();
                        formData.append('id', this.taskNoteModal.taskId);
                        formData.append('project_id', this.taskNoteModal.projectId);
                        formData.append('note', rawContent);
                        const response = await axios.post('/api/index.php?model=task&method=updateNote', formData);
                        if (response.data && response.data.status === 'success') {
                            if (typeof showMessage === 'function') {
                                showMessage(this.t('メモが保存されました。', 'メモが保存されました。'), false);
                            }
                            await this.loadTasks();
                            this.closeTaskNoteModal();
                        } else {
                            throw new Error(response.data?.message || 'Failed to save note');
                        }
                    } catch (error) {
                        console.error('Error saving task note:', error);
                        if (typeof showMessage === 'function') {
                            showMessage(this.t('メモの保存に失敗しました', 'メモの保存に失敗しました'), true);
                        }
                    }
                },

                async clearTaskNote() {
                    const confirmMsg = this.t('メモを削除しますか？', 'メモを削除しますか？');
                    if (!confirm(confirmMsg)) return;
                    if (!this.taskNoteModal.taskId || !this.taskNoteModal.projectId) return;
                    try {
                        const formData = new FormData();
                        formData.append('id', this.taskNoteModal.taskId);
                        formData.append('project_id', this.taskNoteModal.projectId);
                        formData.append('note', '');
                        const response = await axios.post('/api/index.php?model=task&method=updateNote', formData);
                        if (response.data && response.data.status === 'success') {
                            if (typeof showMessage === 'function') {
                                showMessage(this.t('メモが削除されました。', 'メモが削除されました。'), false);
                            }
                            await this.loadTasks();
                            this.closeTaskNoteModal();
                        } else {
                            throw new Error(response.data?.message || 'Failed to delete note');
                        }
                    } catch (error) {
                        if (typeof showMessage === 'function') {
                            showMessage(this.t('メモの削除に失敗しました', 'メモの削除に失敗しました'), true);
                        }
                    }
                },

                getUserById(userId) {
                    if (userId == null || userId === '') return null;
                    return this.usersById[userId] || this.usersById[parseInt(userId, 10)] || null;
                },

                getTaskAssignees(task) {
                    if (!task) return [];
                    const ids = Array.isArray(task.assigned_to_ids) ? task.assigned_to_ids : [];
                    return ids.map((id) => this.getUserById(id)).filter(Boolean);
                },

                getTaskCreator(task) {
                    if (!task) return null;
                    const fromMap = this.getUserById(task.created_by);
                    if (fromMap) return fromMap;
                    if (task.created_by_name) {
                        return {
                            id: task.created_by,
                            realname: task.created_by_name,
                            user_image: task.created_by_user_image || ''
                        };
                    }
                    return null;
                },

                getUserAvatarSrc(user) {
                    if (!user || !user.user_image) return '';
                    const img = String(user.user_image).trim();
                    if (!img || img === 'default.png' || img === 'no-image.png' || img === '1.png' || img === 'null' || img === 'undefined') {
                        return '';
                    }
                    if (img.startsWith('http') || img.startsWith('/')) {
                        return img;
                    }
                    return '/assets/upload/avatar/' + img;
                },

                handleUserAvatarError(user) {
                    if (!user) return;
                    user.avatarError = true;
                    user.avatarLoaded = false;
                },

                handleUserAvatarLoad(user) {
                    if (!user) return;
                    user.avatarLoaded = true;
                },

                showUserAvatarImage(user) {
                    return !!(user && !user.avatarError && this.getUserAvatarSrc(user) && user.avatarLoaded);
                },

                showUserAvatarInitials(user) {
                    return !user || user.avatarError || !this.getUserAvatarSrc(user) || !user.avatarLoaded;
                },

                getUserInitials(nameOrUser) {
                    if (typeof getAvatarName === 'function') {
                        return getAvatarName(nameOrUser);
                    }
                    const name = (nameOrUser && typeof nameOrUser === 'object')
                        ? (nameOrUser.realname || nameOrUser.user_name || '')
                        : nameOrUser;
                    if (!name) return '?';
                    const hasJapanese = /[\u3040-\u309f\u30a0-\u30ff\u4e00-\u9faf]/.test(name);
                    if (hasJapanese) {
                        return name.substring(0, 2);
                    }
                    const words = String(name).trim().split(' ');
                    return words[words.length - 1] || '?';
                },

                isAssignedToMe(task) {
                    if (!task || !window.currentUserId) return false;
                    const currentUserId = String(window.currentUserId);
                    const ids = Array.isArray(task.assigned_to_ids) ? task.assigned_to_ids : [];
                    if (ids.some(function (id) { return String(id) === currentUserId; })) {
                        return true;
                    }
                    if (task.assigned_to) {
                        return String(task.assigned_to).split(',').map(function (id) { return id.trim(); }).includes(currentUserId);
                    }
                    return false;
                },

                canTrackTaskTime(task) {
                    if (!task || !task.id) return false;
                    if (!this.isAssignedToMe(task) && !this.isTaskTimerActive(task.id)) {
                        return false;
                    }
                    if (this.isTaskTimerActive(task.id)) {
                        return true;
                    }
                    return task.status !== 'completed' && task.status !== 'cancelled';
                },

                isTaskTimerActive(taskId) {
                    return !!(window.TaskTimer && window.TaskTimer.isActive(taskId));
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
                    const task = this.tasks.find(function (t) {
                        return parseInt(t.id, 10) === parseInt(taskId, 10);
                    });
                    return !!(task && task.timer_active);
                },

                syncTaskTimerActiveFlags(activeTaskIds) {
                    const ids = Array.isArray(activeTaskIds) ? activeTaskIds : [];
                    const activeSet = new Set(ids.map(function (id) { return parseInt(id, 10); }));
                    this.tasks.forEach(function (task) {
                        if (!task || task.id == null) {
                            return;
                        }
                        task.timer_active = activeSet.has(parseInt(task.id, 10));
                    });
                },

                isTaskTimerToggling(taskId) {
                    return !!(taskId && this.taskTimerTogglingTaskIds[taskId]);
                },

                setTaskTimerToggling(taskId, toggling) {
                    if (!taskId) return;
                    if (toggling) {
                        this.taskTimerTogglingTaskIds = Object.assign({}, this.taskTimerTogglingTaskIds, { [taskId]: true });
                    } else {
                        const next = Object.assign({}, this.taskTimerTogglingTaskIds);
                        delete next[taskId];
                        this.taskTimerTogglingTaskIds = next;
                    }
                },

                applyTaskEstimatedHours(taskId, hours) {
                    const n = parseFloat(hours);
                    if (!taskId || Number.isNaN(n)) return;
                    const value = n < 0 ? 0 : Math.round(n * 100) / 100;
                    const task = this.tasks.find(function (t) { return parseInt(t.id, 10) === parseInt(taskId, 10); });
                    if (task) {
                        task.estimated_hours = value;
                    }
                },

                async toggleTaskTimer(task) {
                    if (!task || !task.id || !this.canTrackTaskTime(task) || !window.TaskTimer) {
                        return;
                    }
                    if (this.isTaskTimerToggling(task.id)) {
                        return;
                    }
                    this.setTaskTimerToggling(task.id, true);
                    try {
                        if (window.TaskTimer.isActive(task.id)) {
                            const result = await window.TaskTimer.stop(task.id);
                            if (result && result.estimated_hours != null) {
                                this.applyTaskEstimatedHours(task.id, result.estimated_hours);
                            }
                        } else {
                            const result = await window.TaskTimer.start(task.id, task.project_id, {
                                title: task.title,
                                project_name: task.project_name || '',
                                estimated_hours: task.estimated_hours,
                            });
                            if (result && result.stopped_previous_task) {
                                this.applyTaskEstimatedHours(
                                    result.stopped_previous_task.task_id,
                                    result.stopped_previous_task.estimated_hours
                                );
                            }
                        }
                    } finally {
                        this.setTaskTimerToggling(task.id, false);
                        this.$forceUpdate();
                    }
                },

                onTaskTimerChanged(event) {
                    const detail = event && event.detail ? event.detail : {};
                    if (detail.stopped && detail.task_id != null && detail.estimated_hours != null) {
                        this.applyTaskEstimatedHours(detail.task_id, detail.estimated_hours);
                    }
                    if (window.TaskTimer && Array.isArray(window.TaskTimer.activeTaskIds)) {
                        this.syncTaskTimerActiveFlags(window.TaskTimer.activeTaskIds);
                    } else if (detail.stopped && detail.task_id != null) {
                        const stoppedId = parseInt(detail.task_id, 10);
                        this.tasks.forEach(function (task) {
                            if (task && parseInt(task.id, 10) === stoppedId) {
                                task.timer_active = false;
                            }
                        });
                    }
                    this.$forceUpdate();
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

                isVietnameseLocale() {
                    return typeof i18next !== 'undefined'
                        && i18next.isInitialized
                        && String(i18next.language || '').startsWith('vi');
                },

                getTaskDisplayTimezone() {
                    return this.isVietnameseLocale() ? TODO_VIETNAM_TIMEZONE : TODO_SERVER_TIMEZONE;
                },

                getTaskDateTimeDisplayFormat() {
                    return this.isVietnameseLocale()
                        ? TODO_DATETIME_MOMENT_FORMAT
                        : TODO_DATETIME_JA_DISPLAY_FORMAT;
                },

                parseTaskDateTime(date, timezone) {
                    if (!date) return null;
                    const raw = String(date).trim();
                    if (!raw || raw === '0000-00-00 00:00:00' || raw === '0000-00-00') return null;
                    if (typeof moment === 'undefined') return null;
                    const tz = timezone || TODO_SERVER_TIMEZONE;

                    if (moment.tz) {
                        for (let i = 0; i < TODO_DATETIME_PARSE_FORMATS.length; i++) {
                            const parsed = moment.tz(raw, TODO_DATETIME_PARSE_FORMATS[i], tz);
                            if (parsed.isValid()) return parsed;
                        }
                        const loose = moment.tz(raw, tz);
                        return loose.isValid() ? loose : null;
                    }

                    const fallback = moment(raw, TODO_DATETIME_PARSE_FORMATS, true);
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

                getFlatpickrLocaleName() {
                    return this.isVietnameseLocale() ? 'vi' : 'ja';
                },

                initTodoFlatpickrLocales() {
                    if (typeof flatpickr === 'undefined') return;
                    const locale = this.getFlatpickrLocaleName();
                    const common = {
                        enableTime: true,
                        time_24hr: true,
                        locale: locale
                    };
                    if (this.fp) {
                        this.fp.set('locale', locale);
                    }
                    if (this.fpEdit) {
                        this.fpEdit.set('locale', locale);
                    }
                },

                /**
                 * Time remaining or 期限超過 for a due date. Returns { text, class, isOverdue } or null.
                 */
                getTimeRemainingForDue(dateString) {
                    if (!dateString || dateString === '0000-00-00 00:00:00' || dateString === '0000-00-00') return null;
                    const now = typeof moment !== 'undefined' && moment.tz
                        ? moment.tz(TODO_SERVER_TIMEZONE)
                        : moment();
                    const end = this.parseTaskDateTime(dateString, TODO_SERVER_TIMEZONE);
                    if (!end) return null;
                    const isVietnamese = this.isVietnameseLocale();
                    const dayLabel = this.t('日', '日');
                    const hourLabel = this.t('時間', '時間');
                    const minuteLabel = this.t('分', '分');
                    const overdueLabel = this.t('期限超過', '期限超過');
                    const remainingLabel = this.t('残り', '残り');
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
                            if (
                                (newStatus === 'completed' || newStatus === 'cancelled')
                                && window.TaskTimer
                                && window.TaskTimer.isActive(task.id)
                            ) {
                                const stopped = response.data.stopped_timer;
                                if (stopped && parseInt(stopped.task_id, 10) === parseInt(task.id, 10)) {
                                    window.TaskTimer.setActive(null);
                                    window.TaskTimer.removeActiveTaskId(stopped.task_id);
                                } else {
                                    await window.TaskTimer.stop(task.id);
                                }
                            }
                            if (window.TaskTimer && Array.isArray(response.data.active_task_ids)) {
                                window.TaskTimer.updateActiveTaskIds(response.data.active_task_ids);
                            }
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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountTodoApp);
} else {
    mountTodoApp();
}
