const { createApp } = Vue;

const SERVER_TASK_TIMEZONE = 'Asia/Tokyo';
const VIETNAM_TASK_TIMEZONE = 'Asia/Ho_Chi_Minh';
const TASK_DATETIME_MOMENT_FORMAT = 'YYYY/M/D HH:mm';
const TASK_DATETIME_JA_DISPLAY_FORMAT = 'YYYY年M月D日 HH:mm';
const TASK_DATETIME_JA_SHORT_DISPLAY_FORMAT = 'M月D日 HH:mm';
const TASK_DATETIME_VI_SHORT_DISPLAY_FORMAT = 'M/D HH:mm';
const TASK_DATETIME_FLATPICKR_FORMAT = 'Y/m/d H:i';
const TASK_DATETIME_FLATPICKR_JA_ALT_FORMAT = 'Y年n月j日 H:i';
const TASK_DATETIME_PARSE_FORMATS = [
    'YYYY-MM-DD HH:mm:ss',
    'YYYY-MM-DD HH:mm',
    'YYYY/M/D HH:mm',
    'YYYY/MM/DD HH:mm',
    'YYYY/M/D H:mm',
    'YYYY/MM/DD H:mm'
];

const DEFAULT_TASKS_WITH_AUTO_DRAWING_LINK = [
    'お客様との連絡・調整・納品対応',
    '全図面のチェック・確認作業'
];

const DEFAULT_TASK_KIND_BY_TITLE = {
    'お客様との連絡・調整・納品対応': '連絡',
    '全図面のチェック・確認作業': 'チェック'
};

const DEFAULT_TASK_DRAWING_PRICE_PERCENTS = {
    'お客様との連絡・調整・納品対応': 15,
    '全図面のチェック・確認作業': 20
};

const TaskApp = createApp({
    components: {
        'comment-component': window.CommentComponent
    },
    data() {
        return {
            currentUserId: USER_AUTH_ID,
            projectId: null,
            permission: {},
            permissionLoaded: false,
            projectInfo: {},
            tasks: [],
            tasksLoaded: false,
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
                progress: 0,
                created_by: this.currentUserId
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
                { value: 'low', label: '低', i18nKey: '低', color: 'secondary' },
                { value: 'medium', label: '中', i18nKey: '中', color: 'primary' },
                { value: 'high', label: '高', i18nKey: '高', color: 'warning' },
                { value: 'urgent', label: '緊急', i18nKey: '緊急', color: 'danger' }
            ],
            taskStatuses: [
                { value: 'todo', label: '未開始', i18nKey: '未開始', color: 'secondary' },
                { value: 'in-progress', label: '進行中', i18nKey: '進行中', color: 'primary' },
                { value: 'confirming', label: '確認中', i18nKey: '確認中', color: 'warning' },
                { value: 'paused', label: '一時停止', i18nKey: '一時停止', color: 'warning' },
                { value: 'completed', label: '完了', i18nKey: '完了', color: 'success' },
                { value: 'cancelled', label: 'キャンセル', i18nKey: 'キャンセル', color: 'danger' }
            ],
            taskKinds: [
                { value: '新規作成', label: '新規作成', i18nKey: '新規作成', color: 'success' },
                { value: '修正(エラー)', label: '修正(エラー)', i18nKey: '修正(エラー)', color: 'danger' },
                { value: '修正(変更)', label: '修正(変更)', i18nKey: '修正(変更)', color: 'warning' },
                { value: 'チェック', label: 'チェック', i18nKey: 'チェック', color: 'primary' },
                { value: '連絡', label: '連絡', i18nKey: '連絡', color: 'info' },
                { value: '検討', label: '検討', i18nKey: '検討', color: 'secondary' },
                { value: '相談・会議', label: '相談・会議', i18nKey: '相談・会議', color: 'dark' }
            ],
            filterStatus: '',
            filterPriority: '',
            filterDueDate: '',
            filterMyTasksOnly: false,
            addingTaskInline: false,
            inlineTasks: [],
            drawingCountSavingTaskIds: {},
            estimatedHoursSavingTaskIds: {},
            taskTimerTogglingTaskIds: {},
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
            workloadModal: {
                show: false,
                taskId: null,
                hours: 0,
                minutes: 0,
                saving: false
            },
            editingInlineId: null,
            // Offcanvas data
            taskComments: [], // Keep for compatibility, but not used
            taskActivities: [],
            taskLogs: [],
            quillEditor: null,
            quillEditorInitTimer: null,
            taskDetailsModalInstance: null,
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
            // Task like/dislike reaction modal
            reactionModal: {
                show: false,
                taskId: null,
                type: null, // 'like' or 'dislike'
                note: '',
                selectedReasons: [], // Array of selected reason IDs
                customNote: '' // Custom input text
            },
            // Predefined reasons for like/dislike
            likeReasons: [
                { id: 'good_communication', label: 'コミュニケーションが良好', i18nKey: 'コミュニケーションが良好' },
                { id: 'complete_early', label: '早く完了した', i18nKey: '早く完了した' },
                { id: 'quality_work', label: '品質が良い', i18nKey: '品質が良い' },
                { id: 'well_organized', label: '整理されている', i18nKey: '整理されている' },
                { id: 'beautiful_design', label: '設計が美しい・創造的', i18nKey: '設計が美しい・創造的' },
                { id: 'excellent_design_solution', label: '設計ソリューションが優れている', i18nKey: '設計ソリューションが優れている' },
                { id: 'cost_efficient', label: 'コスト効率が良い', i18nKey: 'コスト効率が良い' }
            ],
            dislikeReasons: [
                { id: 'error_alot', label: '多くのエラーが発生した', i18nKey: '多くのエラーが発生した' },
                { id: 'repeat_error', label: '同じエラーが繰り返し発生した', i18nKey: '同じエラーが繰り返し発生した' },
                { id: 'not_follow_instructions', label: '指示に従わなかった', i18nKey: '指示に従わなかった' },
                { id: 'not_complete', label: '完了しなかった', i18nKey: '完了しなかった' },
                { id: 'poor_communication', label: 'コミュニケーション不足', i18nKey: 'コミュニケーション不足' },
                { id: 'delayed', label: '遅延し、期限を守らなかった', i18nKey: '遅延し、期限を守らなかった' },
                { id: 'quality_issues', label: '品質に問題がある', i18nKey: '品質に問題がある' },
                { id: 'disorganized', label: '整理されていない', i18nKey: '整理されていない' }
            ],
            // Sortable instance
            sortableInstance: null,
            // Progress options (0% to 100% with 5% steps)
            progressOptions: Array.from({ length: 21 }, (_, i) => i * 5),
            showTaskNoteModal: false,
            taskNoteModal: {
                taskId: null,
                inlineIndex: null,
                content: '',
                canEdit: false
            },
            quillTaskNoteInstance: null,
            quillTaskNoteContent: '',
            quillTaskNoteInitTimer: null,
            creatingDefaultTasks: false
        }
    },
    
    computed: {
        canCreateMissingDefaultTasks() {
            const canAdd = this.permission.can_manage_project
                || this.permission.is_member
                || (this.permission.rule && this.permission.rule.task_add == 1);
            if (!canAdd) {
                return false;
            }
            const existingTitles = new Set((this.tasks || []).map((t) => String(t.title || '').trim()));
            return DEFAULT_TASKS_WITH_AUTO_DRAWING_LINK.some((title) => !existingTitles.has(title));
        },
        canViewTaskList() {
            return this.permission.can_manage_project || this.permission.is_member;
        },
        canViewBusinessDocuments() {
            if (typeof USER_ROLE !== 'undefined' && USER_ROLE === 'administrator') {
                return true;
            }
            if (!this.permission) return false;
            if (this.permission.can_manage_project) return true;
            const rule = this.permission.rule;
            if (!rule) return false;
            return rule.project_director_stat == 1
                || rule.project_director_view == 1
                || rule.project_director_edit == 1
                || rule.project_director == 1;
        },
        canLikeTask() {
            // Quyền chung: chỉ manager hoặc team_leader mới được like/dislike
            return this.permission.can_manage_project || this.permission.is_team_leader;
        },
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
                if (this.filterMyTasksOnly && !this.isAssignedToMe(task)) match = false;
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

            const totalWorkload = this.tasks.reduce((sum, t) => {
                const n = parseFloat(t.estimated_hours);
                return sum + (Number.isNaN(n) || n <= 0 ? 0 : n);
            }, 0);

            return { total, completed, overdue, totalWorkload };
        },
        isInEditMode() {
            return this.inlineTasks.length > 0 || this.editingInlineId !== null;
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
            await this.loadProjectInfo();
            await this.loadTasks();
            await this.loadProjectMembers();
            this.applyAppDataI18n();
        })();
        this.$nextTick(() => {
            this.initFlatpickr();
            this.initSortable();
            this.initTooltips();
        });

        this._tooltipMouseOutHandler = (e) => {
            if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
            const trigger = e.target && e.target.closest ? e.target.closest('[data-bs-toggle="tooltip"]') : null;
            if (!trigger) return;
            const app = document.getElementById('app');
            if (!app || !app.contains(trigger)) return;
            const related = e.relatedTarget;
            if (related && trigger.contains(related)) return;
            const instance = bootstrap.Tooltip.getInstance(trigger);
            if (instance) instance.hide();
        };
        document.addEventListener('mouseout', this._tooltipMouseOutHandler);
        
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

        this._onTaskTimerChanged = (event) => this.onTaskTimerChanged(event);
        document.addEventListener('task-timer-changed', this._onTaskTimerChanged);

        window.addEventListener('ai-action-success', (event) => {
            const { action } = event.detail || {};
            const pid = action && (action.id || (action.params && action.params.project_id));
            if (pid && String(pid) === String(this.projectId)) {
                this.loadProjectInfo();
                this.loadProjectMembers();
                this.loadTasks();
            }
        });
        
        // Auto-refresh task list every 10 seconds if no task is in edit mode
        setInterval(() => {
            // Check if any task is in edit mode
            const hasEditMode = this.inlineTasks.length > 0 || this.editingInlineId !== null;
            
            // Only refresh if no task is being edited
            if (!hasEditMode) {
                this.loadTasks();
            }
        }, 10000); // 10 seconds
        
        // Warn user when navigating away if in edit mode
        window.addEventListener('beforeunload', (e) => {
            if (this.isInEditMode) {
                e.preventDefault();
                e.returnValue = '編集中のタスクがあります。ページを離れると変更が失われる可能性があります。';
                return e.returnValue;
            }
        });
        
        // Intercept link clicks to warn before navigation
        this.linkClickHandler = (e) => {
            const link = e.target.closest('a');
            if (link && link.href && this.isInEditMode) {
                // Skip if it's a hash link (same page anchor)
                if (link.href.startsWith('#') || link.getAttribute('href')?.startsWith('#')) {
                    return;
                }
                
                // Check if it's an external link (different page)
                try {
                    const currentPath = window.location.pathname;
                    const linkUrl = new URL(link.href, window.location.origin);
                    const linkPath = linkUrl.pathname;
                    
                    // If navigating to a different page
                    if (linkPath !== currentPath) {
                        if (!confirm('編集中のタスクがあります。ページを離れると変更が失われる可能性があります。続行しますか？')) {
                            e.preventDefault();
                            e.stopPropagation();
                            return false;
                        }
                    }
                } catch (err) {
                    // If URL parsing fails, allow navigation
                    console.warn('Error parsing link URL:', err);
                }
            }
        };
        document.addEventListener('click', this.linkClickHandler, true); // Use capture phase to intercept before navigation

        this._onI18nLanguageChanged = () => {
            this.$forceUpdate();
            this.$nextTick(() => {
                this.initFlatpickr();
                this.applyAppDataI18n();
            });
        };
        if (typeof i18next !== 'undefined' && i18next.on) {
            i18next.on('languageChanged', this._onI18nLanguageChanged);
        }
    },

    beforeUnmount() {
        if (this._tooltipInitTimer) {
            clearTimeout(this._tooltipInitTimer);
            this._tooltipInitTimer = null;
        }
        if (this._tooltipMouseOutHandler) {
            document.removeEventListener('mouseout', this._tooltipMouseOutHandler);
            this._tooltipMouseOutHandler = null;
        }
        this.disposeTooltips();
        if (typeof i18next !== 'undefined' && i18next.off && this._onI18nLanguageChanged) {
            i18next.off('languageChanged', this._onI18nLanguageChanged);
        }
    },
    
    updated() {
        this.$nextTick(() => {
            this.initFlatpickr();
            if (this._tooltipInitTimer) clearTimeout(this._tooltipInitTimer);
            this._tooltipInitTimer = setTimeout(() => {
                this._tooltipInitTimer = null;
                this.initTooltips();
            }, 50);
        });
    },
    

    
    methods: {
        applyAppDataI18n() {
            if (typeof window.applyDataI18n !== 'function') {
                return;
            }
            const appEl = document.getElementById('app');
            if (appEl) {
                window.applyDataI18n(appEl);
            }
        },

        // Phương thức để dịch label động
        $t(label) {
            if (typeof i18next !== 'undefined' && i18next.isInitialized) {
                return i18next.t(label) || label;
            }
            return label;
        },
        
        onCommentAdded(event) {
            this.showNotification('コメントが追加されました', 'success');
        },

        showNotification(message, type = 'info') {
            this.showMessage(message, type === 'error');
        },
        
        onCommentError(event) {
            this.showNotification(event.message || 'エラーが発生しました', 'error');
        },

        onCommentMessage(event) {
            this.showNotification(event.message || 'エラーが発生しました', 'info');
        },
        updateTaskField(index, field, value) {
            if (this.inlineTasks[index]) {
                if (field === 'drawing_count') {
                    const n = parseInt(value, 10);
                    if (this.inlineTasks[index].link_to_drawings) {
                        value = Number.isNaN(n) || n < 1 ? 1 : n;
                    } else {
                        value = Number.isNaN(n) || n < 0 ? 0 : n;
                    }
                } else if (field === 'link_to_drawings') {
                    value = !!value;
                } else if (field === 'estimated_hours') {
                    const n = parseFloat(value);
                    value = Number.isNaN(n) || n < 0 ? 0 : Math.round(n * 100) / 100;
                } else if (field === 'task_kind') {
                    value = this.normalizeTaskKind(value);
                }
                // Use Vue.set to ensure reactivity
                if (typeof Vue !== 'undefined' && Vue.set) {
                    Vue.set(this.inlineTasks[index], field, value);
                } else {
                    this.inlineTasks[index][field] = value;
                }
                if (field === 'title') {
                    this.applyDefaultTaskKindForTitle(this.inlineTasks[index]);
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
               //         window.location.href = 'index.php';
                    }, 1000);
                    return;
                }
            } catch (error) {
                console.error('Error loading project info:', error);
            }
        },

        async refreshNavbarCounts() {
            if (!this.projectId) {
                return;
            }
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getById&id=${this.projectId}`);
                if (response.data && response.data.id) {
                    if (!this.projectInfo || !this.projectInfo.id) {
                        this.projectInfo = response.data;
                    } else {
                        this.projectInfo.task_count = response.data.task_count;
                        this.projectInfo.drawing_count = response.data.drawing_count;
                    }
                }
            } catch (error) {
                console.error('Error refreshing navbar counts:', error);
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
                // Cho phép AI lấy dữ liệu task hiện tại đang hiển thị
                if (typeof window !== 'undefined') {
                    window.__chatPageContext = window.__chatPageContext || {};
                    window.__chatPageContext.page = 'task_manager';
                    window.__chatPageContext.page_tasks = this.tasks;
                }
                // Reinitialize sortable after tasks are loaded
                this.$nextTick(() => {
                    this.initSortable();
                });

                await this.refreshNavbarCounts();

                if (window.TaskTimer && window.TaskTimer.active) {
                    await window.TaskTimer.refresh();
                }
                
                // Sau khi load tasks, load số comment chưa đọc
                // await this.loadUnreadComments( );
            } catch (error) {
                console.error('Error loading tasks:', error);
                this.showMessage('タスクの読み込みに失敗しました。', true);
            } finally {
                this.tasksLoaded = true;
            }
        },
        
     
        async loadPermission() {
            try {
                const response = await axios.get('/api/index.php?model=task&method=getPermission&project_id=' + this.projectId);
                this.permission = response.data || [];
            } catch (error) {
                console.error('Error loading permission:', error);
            } finally {
                this.permissionLoaded = true;
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
        
        getDefaultStartDateTime() {
            if (typeof moment !== 'undefined' && moment.tz) {
                return moment().tz(this.getTaskDisplayTimezone()).format(TASK_DATETIME_MOMENT_FORMAT);
            }
            return moment().format(TASK_DATETIME_MOMENT_FORMAT);
        },
        getDefaultDueDateTime() {
            // Server default is 18:00 Asia/Tokyo → 16:00 when display locale is Vietnamese
            if (typeof moment !== 'undefined' && moment.tz) {
                const dueTokyo = moment().tz(SERVER_TASK_TIMEZONE).format('YYYY/M/D') + ' 18:00';
                return this.toTaskDateTimeInputValue(dueTokyo);
            }
            const dueHour = this.isVietnameseLocale() ? '16:00' : '18:00';
            return moment().format('YYYY/M/D') + ' ' + dueHour;
        },
        getDefaultTaskKind() {
            const orderType = (this.projectInfo && this.projectInfo.project_order_type) || '';
            if (String(orderType).includes('修正')) {
                return '修正(エラー)';
            }
            return '新規作成';
        },
        normalizeTaskKind(value) {
            const v = String(value || '').trim();
            return v === '新規' ? '新規作成' : v;
        },
        getTaskKindLabel(value) {
            const normalized = this.normalizeTaskKind(value);
            if (!normalized) return '—';
            const kind = this.taskKinds.find(k => k.value === normalized);
            if (!kind) return value || '—';
            if (typeof this.$t === 'function' && kind.i18nKey) {
                return this.$t(kind.i18nKey);
            }
            return kind.label;
        },
        getTaskKindBadgeClass(value) {
            const normalized = this.normalizeTaskKind(value);
            if (!normalized) return 'bg-label-secondary';
            const kind = this.taskKinds.find(k => k.value === normalized);
            return `bg-label-${kind?.color || 'secondary'}`;
        },
        getTaskKindDisplayValue(task) {
            return this.normalizeTaskKind(task && task.task_kind);
        },
        isDefaultTaskWithAutoDrawingLink(task) {
            if (!task) return false;
            const title = (task.title || '').trim();
            return DEFAULT_TASKS_WITH_AUTO_DRAWING_LINK.indexOf(title) !== -1;
        },
        getExistingDefaultTaskPercentTotal() {
            const titles = new Set((this.tasks || []).map((t) => (t.title || '').trim()));
            let total = 0;
            Object.keys(DEFAULT_TASK_DRAWING_PRICE_PERCENTS).forEach((title) => {
                if (titles.has(title)) {
                    total += DEFAULT_TASK_DRAWING_PRICE_PERCENTS[title];
                }
            });
            return total;
        },
        getDrawingPricePoolPercent() {
            return Math.max(0, 100 - this.getExistingDefaultTaskPercentTotal());
        },
        collectDrawingLinkedTasksForPercent() {
            return (this.tasks || []).filter((task) => {
                return task && task.id
                    && this.isTaskLinkedToDrawings(task)
                    && !this.isDefaultTaskWithAutoDrawingLink(task);
            });
        },
        getTaskDrawingSlotCount(task) {
            const n = parseInt(task && task.drawing_count, 10);
            return (!Number.isNaN(n) && n > 0) ? n : 1;
        },
        getTotalDrawingSlotsForPercent() {
            return this.collectDrawingLinkedTasksForPercent().reduce((sum, task) => {
                return sum + this.getTaskDrawingSlotCount(task);
            }, 0);
        },
        getTaskDrawingPricePercent(task) {
            if (!task || !task.id) {
                return null;
            }
            if (this.isDefaultTaskWithAutoDrawingLink(task)) {
                const title = (task.title || '').trim();
                const fixed = DEFAULT_TASK_DRAWING_PRICE_PERCENTS[title];
                return fixed != null ? fixed : null;
            }
            if (!this.isTaskLinkedToDrawings(task)) {
                return null;
            }
            const totalSlots = this.getTotalDrawingSlotsForPercent();
            if (totalSlots <= 0) {
                return null;
            }
            const pool = this.getDrawingPricePoolPercent();
            const slots = this.getTaskDrawingSlotCount(task);
            return (pool * slots) / totalSlots;
        },
        formatTaskDrawingPricePercent(task) {
            const pct = this.getTaskDrawingPricePercent(task);
            if (pct == null) {
                return '';
            }
            const rounded = Math.round(pct * 10) / 10;
            return Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(1);
        },
        getDefaultTaskKindForTitle(title) {
            const normalized = (title || '').trim();
            return DEFAULT_TASK_KIND_BY_TITLE[normalized] || '';
        },
        applyDefaultTaskKindForTitle(task) {
            if (!task) return;
            const kind = this.getDefaultTaskKindForTitle(task.title);
            if (!kind) return;
            if (typeof Vue !== 'undefined' && Vue.set) {
                Vue.set(task, 'task_kind', kind);
            } else {
                task.task_kind = kind;
            }
        },
        isDrawingLinkVisibleForTask(task) {
            if (!task) return false;
            return !this.isDefaultTaskWithAutoDrawingLink(task);
        },
        formatEstimatedHours(value) {
            const n = parseFloat(value);
            if (Number.isNaN(n) || n <= 0) return '—';
            const formatted = Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
            return formatted + 'h';
        },
        formatWorkloadPickerDisplay(value) {
            const n = parseFloat(value);
            if (Number.isNaN(n) || n <= 0) return '';
            const formatted = Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
            return formatted + 'h';
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
            const task = this.tasks.find(function(t) { return t.id === taskId; });
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
        formatTotalWorkload(value) {
            const n = parseFloat(value);
            if (Number.isNaN(n) || n <= 0) return '0h';
            const formatted = Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
            return formatted + 'h';
        },
        stripHtmlToPlainText(html) {
            if (!html) return '';
            let text = String(html);
            for (let i = 0; i < 3; i++) {
                const txt = document.createElement('textarea');
                txt.innerHTML = text;
                const decoded = txt.value;
                if (decoded === text) break;
                text = decoded;
            }
            const div = document.createElement('div');
            div.innerHTML = text;
            return (div.textContent || div.innerText || '')
                .replace(/&nbsp;/gi, ' ')
                .replace(/\u00A0/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        },
        getTaskNoteSnippet(note, maxLen) {
            if (!note) return '';
            const text = this.stripHtmlToPlainText(note);
            if (!text) return '';
            const limit = maxLen || 28;
            return text.length <= limit ? text : text.substring(0, limit) + '…';
        },
        canEditTaskNote(task) {
            if (!task) return false;
            if (!this.permission || !this.permission.is_member) return false;
            if (this.permission.can_manage_project) return true;
            return this.isAssignedToMe(task);
        },
        canEditDrawingLink(task) {
            if (this.permission && this.permission.can_manage_project) {
                return true;
            }
            if (!this.permission || !this.permission.is_member || !task) {
                return false;
            }
            return this.isDrawingLinkVisibleForTask(task);
        },
        canEditTaskDrawingCount(task) {
            if (!task || !task.id || !this.isTaskLinkedToDrawings(task)) {
                return false;
            }
            return this.canEditDrawingLink(task);
        },
        canEditTaskWorkload(task) {
            if (!task || !task.id) return false;
            return this.permission.can_manage_project
                || (this.permission.rule && this.permission.rule.task_edit == 1 && this.checkAssignee(task));
        },
        canTrackTaskTime(task) {
            if (!task || !task.id) {
                return false;
            }
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
            const canonical = this.tasks.find((t) => parseInt(t.id, 10) === parseInt(taskId, 10));
            return !!(canonical && canonical.timer_active);
        },
        syncTaskTimerActiveFlags(activeTaskIds) {
            const ids = Array.isArray(activeTaskIds) ? activeTaskIds : [];
            const activeSet = new Set(ids.map((id) => parseInt(id, 10)));
            this.tasks.forEach((task) => {
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
                this.taskTimerTogglingTaskIds = { ...this.taskTimerTogglingTaskIds, [taskId]: true };
            } else {
                const next = { ...this.taskTimerTogglingTaskIds };
                delete next[taskId];
                this.taskTimerTogglingTaskIds = next;
            }
        },
        isTerminalTaskStatus(status) {
            return status === 'completed' || status === 'cancelled';
        },
        async stopTaskTimerForTerminalStatus(taskId, status, apiResponse) {
            if (!window.TaskTimer || !taskId || !this.isTerminalTaskStatus(status)) {
                return;
            }
            if (!window.TaskTimer.isActive(taskId)) {
                return;
            }

            const stopped = apiResponse && apiResponse.stopped_timer;
            if (stopped && parseInt(stopped.task_id, 10) === parseInt(taskId, 10)) {
                window.TaskTimer.setActive(null);
                window.TaskTimer.removeActiveTaskId(stopped.task_id);
                this.applyTaskEstimatedHours(stopped.task_id, stopped.estimated_hours);
                return;
            }

            const result = await window.TaskTimer.stop(taskId);
            if (result && result.estimated_hours != null) {
                this.applyTaskEstimatedHours(taskId, result.estimated_hours);
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
                    const result = await window.TaskTimer.start(task.id, this.projectId, {
                        title: task.title,
                        project_name: this.projectInfo && this.projectInfo.name ? this.projectInfo.name : '',
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
            }
        },
        onTaskTimerChanged(event) {
            const detail = event && event.detail ? event.detail : {};
            if (detail.stopped && detail.task_id && detail.estimated_hours != null) {
                this.applyTaskEstimatedHours(detail.task_id, detail.estimated_hours);
            }
            if (window.TaskTimer && Array.isArray(window.TaskTimer.activeTaskIds)) {
                this.syncTaskTimerActiveFlags(window.TaskTimer.activeTaskIds);
            } else if (detail.stopped && detail.task_id) {
                const stoppedId = parseInt(detail.task_id, 10);
                this.tasks.forEach((task) => {
                    if (task && parseInt(task.id, 10) === stoppedId) {
                        task.timer_active = false;
                    }
                });
            }
            this.$forceUpdate();
        },
        isEstimatedHoursSaving(taskId) {
            return !!(taskId && this.estimatedHoursSavingTaskIds[taskId]);
        },
        setEstimatedHoursSaving(taskId, saving) {
            if (!taskId) return;
            if (saving) {
                this.estimatedHoursSavingTaskIds = { ...this.estimatedHoursSavingTaskIds, [taskId]: true };
            } else {
                const next = { ...this.estimatedHoursSavingTaskIds };
                delete next[taskId];
                this.estimatedHoursSavingTaskIds = next;
            }
        },
        applyTaskEstimatedHours(taskId, hours) {
            const n = parseFloat(hours);
            if (!taskId || Number.isNaN(n)) {
                return;
            }
            const value = n < 0 ? 0 : Math.round(n * 100) / 100;
            const canonical = this.tasks.find(t => t.id === taskId);
            if (canonical) {
                canonical.estimated_hours = value;
            }
        },
        isTaskLinkedToDrawings(task) {
            if (!task) return false;
            if (task._inlineIndex !== undefined && task._inlineIndex !== null) {
                return task.link_to_drawings === true;
            }
            const n = parseInt(task.drawing_count, 10);
            return !Number.isNaN(n) && n > 0;
        },
        resolveDrawingCountForSave(task) {
            if (!task || task.link_to_drawings === false) {
                return 0;
            }
            if (task._inlineIndex !== undefined && task._inlineIndex !== null && !task.link_to_drawings) {
                return 0;
            }
            const n = parseInt(task.drawing_count, 10);
            if (task._inlineIndex !== undefined && task._inlineIndex !== null) {
                return task.link_to_drawings ? (Number.isNaN(n) || n < 1 ? 1 : n) : 0;
            }
            return Number.isNaN(n) || n < 1 ? 0 : n;
        },
        getDrawingCountForSave(inlineTask) {
            if (this.canEditDrawingLink(inlineTask)) {
                return this.resolveDrawingCountForSave(inlineTask);
            }
            if (inlineTask && inlineTask.id) {
                const existing = this.tasks.find(t => t.id == inlineTask.id);
                if (existing && existing.drawing_count != null) {
                    return Math.max(0, parseInt(existing.drawing_count, 10) || 0);
                }
            }
            return 0;
        },
        onInlineDrawingLinkChange(index, checked) {
            if (!this.inlineTasks[index]) return;
            this.updateTaskField(index, 'link_to_drawings', checked);
            if (checked) {
                const current = parseInt(this.inlineTasks[index].drawing_count, 10);
                this.updateTaskField(index, 'drawing_count', !Number.isNaN(current) && current > 0 ? current : 1);
            } else {
                this.updateTaskField(index, 'drawing_count', 0);
            }
        },
        isDrawingCountSaving(taskId) {
            return !!(taskId && this.drawingCountSavingTaskIds[taskId]);
        },
        setDrawingCountSaving(taskId, saving) {
            if (!taskId) return;
            if (saving) {
                this.drawingCountSavingTaskIds = { ...this.drawingCountSavingTaskIds, [taskId]: true };
            } else {
                const next = { ...this.drawingCountSavingTaskIds };
                delete next[taskId];
                this.drawingCountSavingTaskIds = next;
            }
        },
        applyTaskDrawingCount(taskId, drawingCount) {
            const count = parseInt(drawingCount, 10);
            if (!taskId || Number.isNaN(count)) {
                return;
            }
            const canonical = this.tasks.find(t => t.id === taskId);
            if (canonical) {
                canonical.drawing_count = count;
            }
        },
        async saveTaskEstimatedHours(task, value) {
            if (!task || !task.id || !this.canEditTaskWorkload(task)) {
                return;
            }
            const n = parseFloat(value);
            const hours = Number.isNaN(n) || n < 0 ? 0 : Math.round(n * 100) / 100;
            const canonical = this.tasks.find(t => t.id === task.id);
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
                formData.append('project_id', this.projectId);
                formData.append('estimated_hours', hours);
                const response = await axios.post('/api/index.php?model=task&method=updateEstimatedHours', formData);
                if (response.data && response.data.status === 'success') {
                    const savedHours = response.data.estimated_hours != null ? response.data.estimated_hours : hours;
                    this.applyTaskEstimatedHours(task.id, savedHours);
                } else {
                    this.showMessage(response.data?.message || '工数の更新に失敗しました', true);
                }
            } catch (error) {
                this.showMessage('工数の更新に失敗しました', true);
            } finally {
                this.setEstimatedHoursSaving(task.id, false);
            }
        },
        async saveTaskDrawingCount(task, value) {
            if (!task || !task.id || !this.canEditTaskDrawingCount(task)) {
                return;
            }
            const n = parseInt(value, 10);
            const drawingCount = Number.isNaN(n) || n < 1 ? 1 : n;
            const canonical = this.tasks.find(t => t.id === task.id);
            const currentCount = parseInt(canonical ? canonical.drawing_count : task.drawing_count, 10);
            if (currentCount === drawingCount) {
                return;
            }
            if (this.isDrawingCountSaving(task.id)) {
                return;
            }
            this.setDrawingCountSaving(task.id, true);
            try {
                const formData = new FormData();
                formData.append('id', task.id);
                formData.append('project_id', this.projectId);
                formData.append('linked', '1');
                formData.append('drawing_count', drawingCount);
                const response = await axios.post('/api/index.php?model=task&method=updateDrawingLink', formData);
                if (response.data && response.data.status === 'success') {
                    const savedCount = response.data.drawing_count != null ? response.data.drawing_count : drawingCount;
                    this.applyTaskDrawingCount(task.id, savedCount);
                    await this.refreshNavbarCounts();
                } else {
                    this.showMessage(response.data?.message || '図面の更新に失敗しました', true);
                }
            } catch (error) {
                this.showMessage('図面の更新に失敗しました', true);
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
                formData.append('project_id', this.projectId);
                formData.append('linked', linked ? '1' : '0');
                formData.append('drawing_count', drawingCount);
                const response = await axios.post('/api/index.php?model=task&method=updateDrawingLink', formData);
                if (response.data && response.data.status === 'success') {
                    const savedCount = response.data.drawing_count != null ? response.data.drawing_count : drawingCount;
                    this.applyTaskDrawingCount(task.id, savedCount);
                    await this.refreshNavbarCounts();
                } else {
                    if (event && event.target) {
                        event.target.checked = this.isTaskLinkedToDrawings(task);
                    }
                    this.showMessage(response.data?.message || '図面の更新に失敗しました', true);
                }
            } catch (error) {
                if (event && event.target) {
                    event.target.checked = this.isTaskLinkedToDrawings(task);
                }
                this.showMessage('図面の更新に失敗しました', true);
            }
        },
        openTaskNoteModal(task) {
            if (!task) return;
            const inlineIndex = task._isInlineEdit && task._inlineIndex != null ? task._inlineIndex : null;
            const taskId = task.id || null;
            if (!taskId && inlineIndex === null) return;
            this.destroyQuillTaskNoteEditor();
            this.showTaskNoteModal = true;
            this.taskNoteModal = {
                taskId: taskId,
                inlineIndex: inlineIndex,
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
            this.taskNoteModal = { taskId: null, inlineIndex: null, content: '', canEdit: false };
            this.quillTaskNoteContent = '';
        },
        resetQuillTaskNoteDom() {
            const el = document.getElementById('quill_task_note_content');
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
                const el = document.getElementById('quill_task_note_content');
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
                    placeholder: 'メモの詳細を入力してください...',
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
            const inlineIndex = this.taskNoteModal.inlineIndex;
            if (inlineIndex !== null && this.inlineTasks[inlineIndex] && !this.taskNoteModal.taskId) {
                this.inlineTasks[inlineIndex].note = rawContent;
                this.showMessage('メモを保存しました（タスク保存時に反映されます）。');
                this.closeTaskNoteModal();
                return;
            }
            if (!this.taskNoteModal.taskId) return;
            try {
                const formData = new FormData();
                formData.append('id', this.taskNoteModal.taskId);
                formData.append('project_id', this.projectId);
                formData.append('note', rawContent);
                const response = await axios.post('/api/index.php?model=task&method=updateNote', formData);
                if (response.data && response.data.status === 'success') {
                    this.showMessage('メモが保存されました。');
                    await this.loadTasks();
                    this.closeTaskNoteModal();
                } else {
                    this.showMessage(response.data?.message || 'メモの保存に失敗しました', true);
                }
            } catch (error) {
                console.error('Error saving task note:', error);
                const msg = error.response?.data?.message || 'メモの保存に失敗しました';
                this.showMessage(msg, true);
            }
        },
        async clearTaskNote() {
            if (!confirm('メモを削除しますか？')) return;
            const inlineIndex = this.taskNoteModal.inlineIndex;
            if (inlineIndex !== null && this.inlineTasks[inlineIndex] && !this.taskNoteModal.taskId) {
                this.inlineTasks[inlineIndex].note = '';
                this.showMessage('メモを削除しました。');
                this.closeTaskNoteModal();
                return;
            }
            if (!this.taskNoteModal.taskId) return;
            try {
                const formData = new FormData();
                formData.append('id', this.taskNoteModal.taskId);
                formData.append('project_id', this.projectId);
                formData.append('note', '');
                const response = await axios.post('/api/index.php?model=task&method=updateNote', formData);
                if (response.data && response.data.status === 'success') {
                    this.showMessage('メモが削除されました。');
                    await this.loadTasks();
                    this.closeTaskNoteModal();
                } else {
                    this.showMessage(response.data?.message || 'メモの削除に失敗しました', true);
                }
            } catch (error) {
                const msg = error.response?.data?.message || 'メモの削除に失敗しました';
                this.showMessage(msg, true);
            }
        },
        async createMissingDefaultTasks() {
            if (!this.projectId || this.creatingDefaultTasks) {
                return;
            }
            this.creatingDefaultTasks = true;
            try {
                const formData = new FormData();
                formData.append('project_id', this.projectId);
                const response = await axios.post('/api/index.php?model=task&method=createMissingDefaultTasks', formData);
                const data = response.data || {};
                if (data.status === 'success') {
                    this.showMessage(data.message || '既定タスクを追加しました');
                    await this.loadTasks();
                    await this.refreshNavbarCounts();
                } else {
                    this.showMessage(data.message || '既定タスクの追加に失敗しました', true);
                }
            } catch (error) {
                console.error('createMissingDefaultTasks', error);
                const msg = error.response?.data?.message || '既定タスクの追加に失敗しました';
                this.showMessage(msg, true);
            } finally {
                this.creatingDefaultTasks = false;
            }
        },
        openNewTaskModal() {
            const newTask = {
                title: '',
                priority: 'medium',
                status: 'todo',
                task_kind: this.getDefaultTaskKind(),
                drawing_count: 0,
                link_to_drawings: false,
                estimated_hours: 0,
                note: '',
                start_date: this.getDefaultStartDateTime(),
                due_date: this.getDefaultDueDateTime(),
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
            if (!confirm(this.$t('本当にこのタスクを削除しますか?'))) {
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
                    await this.stopTaskTimerForTerminalStatus(
                        targetTask.id,
                        statusToSet,
                        response.data
                    );
                    if (window.TaskTimer && Array.isArray(response.data.active_task_ids)) {
                        window.TaskTimer.updateActiveTaskIds(response.data.active_task_ids);
                    }
                    this.showMessage('ステータスを更新しました。');
                    // Nếu user được giao task và chưa acknowledge thì tự động acknowledge
                    if (this.isAssignedToMe(targetTask) && !this.isAcknowledged(targetTask, this.currentUserId)) {
                        await this.acknowledgeTask(targetTask, { silent: true });
                    }
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
                    // Nếu user được giao task và chưa acknowledge thì tự động acknowledge
                    if (this.isAssignedToMe(targetTask) && !this.isAcknowledged(targetTask, this.currentUserId)) {
                        await this.acknowledgeTask(targetTask, { silent: true });
                    }
                    await this.loadTasks();
                } else {
                    throw new Error(response.data.message);
                }
            } catch (error) {
                this.showMessage(error.message || '進捗の更新に失敗しました', true);
            }
        },

        setTaskProgress(task, percent) {
            if (!task) return;
            task.progress = percent;
            this.updateTaskProgress(task);
            // Close dropdown
            this.$nextTick(() => {
                const dropdownElement = document.querySelector('#progressDropdown' + task.id);
                if (dropdownElement) {
                    const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                    if (dropdown) {
                        dropdown.hide();
                    }
                }
            });
        },
        
        setInlineTaskProgress(inlineIndex, percent) {
            if (this.inlineTasks[inlineIndex]) {
                this.inlineTasks[inlineIndex].progress = percent;
                this.updateTaskField(inlineIndex, 'progress', percent);
            }
            // Close dropdown
            this.$nextTick(() => {
                const dropdownElement = document.querySelector('#progressDropdownInline' + inlineIndex);
                if (dropdownElement) {
                    const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                    if (dropdown) {
                        dropdown.hide();
                    }
                }
            });
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
                formData.append('assigned_to', this.getPrimaryAssigneeId(targetTask));
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
        
        isVietnameseLocale() {
            return typeof i18next !== 'undefined'
                && i18next.isInitialized
                && String(i18next.language || '').startsWith('vi');
        },

        getTaskDisplayTimezone() {
            return this.isVietnameseLocale() ? VIETNAM_TASK_TIMEZONE : SERVER_TASK_TIMEZONE;
        },

        getTaskDateTimeDisplayFormat() {
            return this.isVietnameseLocale()
                ? TASK_DATETIME_MOMENT_FORMAT
                : TASK_DATETIME_JA_DISPLAY_FORMAT;
        },

        getTaskDateTimeShortDisplayFormat() {
            return this.isVietnameseLocale()
                ? TASK_DATETIME_VI_SHORT_DISPLAY_FORMAT
                : TASK_DATETIME_JA_SHORT_DISPLAY_FORMAT;
        },

        getFlatpickrLocale() {
            if (typeof window === 'undefined' || !window.flatpickr || !window.flatpickr.l10ns) {
                return 'default';
            }
            if (this.isVietnameseLocale()) {
                return window.flatpickr.l10ns.vi || 'default';
            }
            return window.flatpickr.l10ns.ja || 'default';
        },

        getFlatpickrOptions() {
            const isVi = this.isVietnameseLocale();
            const options = {
                enableTime: true,
                dateFormat: TASK_DATETIME_FLATPICKR_FORMAT,
                time_24hr: true,
                allowInput: true,
                locale: this.getFlatpickrLocale()
            };
            if (!isVi) {
                options.altInput = true;
                options.altFormat = TASK_DATETIME_FLATPICKR_JA_ALT_FORMAT;
                options.altInputClass = 'form-control px-1 py-0';
            }
            return options;
        },

        parseTaskDateTime(date, timezone = SERVER_TASK_TIMEZONE) {
            if (!date) return null;
            const raw = String(date).trim();
            if (!raw || raw === '0000-00-00 00:00:00' || raw === '0000-00-00') return null;
            if (typeof moment === 'undefined') return null;

            if (moment.tz) {
                for (const fmt of TASK_DATETIME_PARSE_FORMATS) {
                    const parsed = moment.tz(raw, fmt, timezone);
                    if (parsed.isValid()) return parsed;
                }
                const loose = moment.tz(raw, timezone);
                return loose.isValid() ? loose : null;
            }

            const fallback = moment(raw, TASK_DATETIME_PARSE_FORMATS, true);
            return fallback.isValid() ? fallback : null;
        },

        parseTaskDateTimeInput(value) {
            return this.parseTaskDateTime(value, this.getTaskDisplayTimezone());
        },

        toTaskDateTimeInputValue(date) {
            const parsed = this.parseTaskDateTime(date);
            if (!parsed) return '';
            return parsed.clone().tz(this.getTaskDisplayTimezone()).format(TASK_DATETIME_MOMENT_FORMAT);
        },

        fromTaskDateTimeInputValue(value) {
            const raw = String(value || '').trim();
            if (!raw) return '';
            const parsed = this.parseTaskDateTimeInput(raw);
            if (!parsed) return raw;
            if (moment.tz) {
                return parsed.clone().tz(SERVER_TASK_TIMEZONE).format(TASK_DATETIME_MOMENT_FORMAT);
            }
            return parsed.format(TASK_DATETIME_MOMENT_FORMAT);
        },

        formatTaskDateTimeInDisplayTz(date) {
            const parsed = this.parseTaskDateTime(date);
            if (!parsed) return '-';
            const localized = moment.tz
                ? parsed.clone().tz(this.getTaskDisplayTimezone())
                : parsed;
            return localized.format(this.getTaskDateTimeDisplayFormat());
        },

        formatDate(date) {
            return this.formatTaskDateTimeInDisplayTz(date);
        },

        formatTaskDueDate(date) {
            const parsed = this.parseTaskDateTime(date);
            if (!parsed) return '-';
            const localized = moment.tz
                ? parsed.clone().tz(this.getTaskDisplayTimezone())
                : parsed;
            return localized.format(this.getTaskDateTimeShortDisplayFormat());
        },
        
        showMessage(message, isError = false) {
            const text = message ? this.$t(String(message)) : '';
            showMessage(text, isError);
        },
        
        editTaskInline(task) {
            // Convert task to inline editable format
            const inlineTask = {
                id: task.id,
                title: task.title || '',
                priority: task.priority || 'medium',
                task_kind: this.normalizeTaskKind(task.task_kind) || this.getDefaultTaskKind(),
                drawing_count: task.drawing_count != null ? parseInt(task.drawing_count, 10) : 0,
                link_to_drawings: (task.drawing_count != null ? parseInt(task.drawing_count, 10) : 0) > 0,
                estimated_hours: task.estimated_hours != null ? parseFloat(task.estimated_hours) : 0,
                note: task.note || '',
                start_date: this.toTaskDateTimeInputValue(task.start_date),
                due_date: this.toTaskDateTimeInputValue(task.due_date),
                assignees: (() => {
                    const id = task.assigned_to
                        ? task.assigned_to.split(',').map(v => v.trim()).filter(Boolean)[0]
                        : '';
                    return id ? [id] : [];
                })(),
                status: task.status || 'todo',
                progress: task.progress || 0,
                created_by: task.created_by != null ? task.created_by : undefined,
                created_by_name: task.created_by_name,
                created_by_user_image: task.created_by_user_image
            };
            this.editingInlineId = task.id;
            this.inlineTasks.push(inlineTask);
        },
        
        async saveTaskInline(idx) {
            const inlineTask = this.inlineTasks[idx];
            if (!inlineTask || inlineTask._saving) {
                return;
            }
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
            this.applyDefaultTaskKindForTitle(inlineTask);
            inlineTask._saving = true;
            try {
                const formData = new FormData();
                const method = inlineTask.id ? 'edit' : 'add';
                if (inlineTask.id) {
                    formData.append('id', inlineTask.id);
                }
                formData.append('project_id', this.projectId);
                formData.append('title', inlineTask.title);
                formData.append('priority', inlineTask.priority);
                formData.append('start_date', inlineTask.id
                    ? (this.fromTaskDateTimeInputValue(inlineTask.start_date) || '')
                    : this.fromTaskDateTimeInputValue(this.getDefaultStartDateTime()));
                formData.append('due_date', this.fromTaskDateTimeInputValue(inlineTask.due_date));
                formData.append('assigned_to', inlineTask.assignees[0] || '');
                formData.append('created_by', this.currentUserId);
                formData.append('status', inlineTask.status);
                formData.append('progress', inlineTask.progress);
                formData.append('task_kind', this.getDefaultTaskKindForTitle(inlineTask.title) || inlineTask.task_kind || this.getDefaultTaskKind());
                formData.append('drawing_count', this.getDrawingCountForSave(inlineTask));
                formData.append('estimated_hours', inlineTask.estimated_hours != null ? inlineTask.estimated_hours : 0);
                formData.append('note', inlineTask.note || '');

                // Calculate position for new task
                if (!inlineTask.id) {
                    const position = this.calculateTaskPosition(idx);
                    formData.append('position', position);
                }

                const response = await axios.post('/api/index.php?model=task&method=' + method, formData);
                if (response.data.status == 'success') {
                    const savedTaskId = inlineTask.id || (response.data && response.data.id);
                    const editingId = inlineTask.id || null;
                    const isNewTask = !inlineTask.id;

                    // Hide inline row immediately so slow reload does not leave the form visible
                    this.inlineTasks.splice(idx, 1);
                    if (editingId && this.editingInlineId === editingId) {
                        this.editingInlineId = null;
                    }

                    this.showMessage(isNewTask ? 'タスクを追加しました。' : 'タスクを更新しました。');

                    try {
                        await this.loadTasks();
                        if (savedTaskId) {
                            const task = this.tasks.find(t => t.id == savedTaskId);
                            if (task && this.isAssignedToMe(task) && !this.isAcknowledged(task, this.currentUserId)) {
                                await this.acknowledgeTask(task, { silent: true });
                                await this.loadTasks();
                            }
                        }
                    } catch (reloadError) {
                        console.error('Error reloading tasks after inline save:', reloadError);
                    }
                } else {
                    this.showMessage(response.data.message || 'エラーが発生しました。', true);
                }
            } catch (error) {
                console.error('Error saving task:', error);
                this.showMessage('エラーが発生しました。', true);
            } finally {
                if (this.inlineTasks[idx] === inlineTask) {
                    inlineTask._saving = false;
                }
            }
        },

        isInlineTaskSaving(inlineIndex) {
            const task = this.inlineTasks[inlineIndex];
            return !!(task && task._saving);
        },
        
        cancelTaskInline(idx) {
            const inlineTask = this.inlineTasks[idx];
            this.inlineTasks.splice(idx, 1);
            this.editingInlineId = null;
        },
        
        getPriorityLabel(priority) {
            const p = this.taskPriorities.find(p => p.value === priority);
            if (!p) return priority || '—';
            if (typeof this.$t === 'function' && p.i18nKey) {
                return this.$t(p.i18nKey);
            }
            return p.label;
        },
        
        getPriorityButtonClass(priority) {
            const p = this.taskPriorities.find(p => p.value === priority);
            return `btn-${p?.color || 'secondary'}`;
        },
        
        getStatusLabel(status) {
            const s = this.taskStatuses.find(s => s.value === status);
            if (!s) return status || '—';
            if (typeof this.$t === 'function' && s.i18nKey) {
                return this.$t(s.i18nKey);
            }
            return s.label;
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
            return this.formatTaskDateTimeInDisplayTz(date);
        },
        
        getAssigneeMember(task, userId) {
            if (userId == null || userId === undefined || userId === '') {
                return null;
            }
            const member = this.projectMembers.find(m => String(m.user_id) === String(userId));
            if (member) {
                return member;
            }
            const primaryId = this.getPrimaryAssigneeId(task);
            if (task && String(primaryId) === String(userId)
                && (task.assigned_to_name != null || task.assigned_to_user_image != null)) {
                return {
                    userid: task.assigned_to_userid,
                    user_id: userId,
                    user_name: task.assigned_to_name || '',
                    user_image: task.assigned_to_user_image || ''
                };
            }
            return null;
        },

        getAssigneeTooltip(task, userId) {
            const member = this.getAssigneeMember(task, userId);
            const name = member?.user_name || userId;
            const isAck = this.isAcknowledged(task, userId);
            if (isAck) {
                const ackAt = this.getAcknowledgedAt(task, userId);
                return `${name} - 受領済み: ${this.formatDateTime(ackAt)}`;
            }
            return `${name} - 未受領`;
        },
        getCreatorName(task) {
            if (!task || task.created_by == null || task.created_by === undefined) return '';
            return this.getCreatorNameByUserId(task.created_by);
        },
        getCreatorNameByUserId(userId) {
            if (userId == null || userId === undefined || userId === '') return '';
            const member = this.projectMembers.find(m => String(m.user_id) === String(userId));
            return member?.user_name || '';
        },
        /** Return creator info for avatar display (from task API or projectMembers) */
        getCreatorMember(task) {
            if (!task) return null;
            if (task.created_by === 0 || task.created_by === '0') {
                return {
                    userid: 'system',
                    user_id: 0,
                    user_name: 'System',
                    user_image: ''
                };
            }
            if (task.created_by == null || task.created_by === undefined) return null;
            // Prefer API data so creator avatar shows even when not a project member
            if (task.created_by_name != null || task.created_by_user_image != null) {
                return {
                    userid: task.created_by_userid,
                    user_id: task.created_by,
                    user_name: task.created_by_name || '',
                    user_image: task.created_by_user_image || ''
                };
            }
            return this.projectMembers.find(m => String(m.user_id) === String(task.created_by)) || null;
        },
        getCreatorMemberByUserId(userId) {
            if (userId == null || userId === undefined || userId === '') return null;
            return this.projectMembers.find(m => String(m.user_id) === String(userId)) || null;
        },
        /** For inline task: prefer task's created_by_name/created_by_user_image if present */
        getCreatorMemberForInlineTask(task) {
            if (!task) return null;
            if (task.created_by === 0 || task.created_by === '0') {
                return {
                    userid: 'system',
                    user_id: 0,
                    user_name: 'System',
                    user_image: ''
                };
            }
            if (task.created_by == null || task.created_by === undefined) return null;
            if (task.created_by_name != null || task.created_by_user_image != null) {
                return {
                    userid: task.created_by_userid,
                    user_id: task.created_by,
                    user_name: task.created_by_name || '',
                    user_image: task.created_by_user_image || ''
                };
            }
            return this.getCreatorMemberByUserId(task.created_by);
        },
        /** True if creator member has a valid avatar to show (has image and no load error). Else fallback to text/initials. */
        shouldShowCreatorAvatar(member) {
            return member && (member.user_image && String(member.user_image).trim() !== '') && !member.avatarError;
        },
        /** Tooltip text for creator (user name). */
        getCreatorTooltip(member) {
            return member ? (member.user_name || '') : '';
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
            const primaryId = Array.isArray(currentAssignees) && currentAssignees.length
                ? String(currentAssignees[0]).trim()
                : '';
            this.assigneeModal.selected = primaryId ? [primaryId] : [];
            
            // Backup current task data to prevent loss
            this.assigneeModal.backupData = { ...this.inlineTasks[idx] };
            
            this.assigneeModal.show = true;
        },

        async assignInlineTaskToSelf(inlineIndex) {
            if (inlineIndex === undefined || inlineIndex === null || !this.inlineTasks[inlineIndex]) {
                return;
            }
            const userId = String(this.currentUserId || '').trim();
            if (!userId) return;
            const isMember = (this.projectMembers || []).some(function(m) {
                return String(m.user_id) === userId;
            });
            if (!isMember) {
                try {
                    const formData = new FormData();
                    formData.append('project_id', this.projectId);
                    formData.append('user_id', userId);
                    formData.append('role', 'member');
                    const response = await axios.post('/api/index.php?model=project&method=addMemberApi', formData);
                    if (!response.data || response.data.status !== 'success') {
                        this.showMessage(response.data?.message || 'プロジェクトメンバーの追加に失敗しました。', true);
                        return;
                    }
                    await this.loadProjectMembers();
                    if (this.permission) {
                        this.permission.is_member = true;
                    }
                } catch (error) {
                    console.error('Error adding self as project member:', error);
                    this.showMessage('プロジェクトメンバーの追加に失敗しました。', true);
                    return;
                }
            }
            this.inlineTasks[inlineIndex].assignees = [userId];
            this.ensureTaskData(inlineIndex);
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
                        assignees: this.assigneeModal.selected.slice(0, 1)
                    };
                } else {
                    // Update only the assignees property to preserve all other data
                    this.inlineTasks[this.assigneeModal.idx].assignees = this.assigneeModal.selected.slice(0, 1);
                }
                
                // Ensure all required fields exist
                this.ensureTaskData(this.assigneeModal.idx);
            }
            this.closeAssigneeModal();
        },
        
        getPrimaryAssigneeId(task) {
            if (!task) return '';
            if (task.assignees && task.assignees.length) {
                return String(task.assignees[0]).trim();
            }
            if (!task.assigned_to) return '';
            const ids = String(task.assigned_to).split(',').map(id => id.trim()).filter(Boolean);
            return ids[0] || '';
        },
        toggleAssignee(userId) {
            if (this.assigneeModal.selected.includes(userId)) {
                this.assigneeModal.selected = [];
            } else {
                this.assigneeModal.selected = [userId];
            }
        },
        
        assigneeNames(userIds) {
            return userIds.map(userId => {
                const m = this.projectMembers.find(u => String(u.user_id) === String(userId));
                return m ? m.user_name : '';
            }).join(', ');
        },

        // Tooltip cho nhóm avatar bị ẩn (>4): hiển thị tên + trạng thái nhận việc
        getOverflowAssigneesTooltip(task) {
            if (!task || !task.assigned_to) return '';
            const allIds = task.assigned_to
                .split(',')
                .map(id => id.trim())
                .filter(id => id);
            const overflowIds = allIds.slice(4); // các user thứ 5 trở đi
            if (!overflowIds.length) return '';

            return overflowIds
                .map(userId => this.getAssigneeTooltip(task, userId))
                .join('\n');
        },
        
        initFlatpickr() {
            if (!window.flatpickr) return;
            const flatpickrOptions = this.getFlatpickrOptions();
            document.querySelectorAll('.datetimepicker').forEach(el => {
                if (el._flatpickr) {
                    el._flatpickr.destroy();
                }
                window.flatpickr(el, {
                    ...flatpickrOptions,
                    onChange: (selectedDates, dateStr) => {
                        el.value = dateStr;
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                });
            });
        },
        
        initSortable() {
            const taskList = document.querySelector('.task-list');
            if (!taskList) return;
            
            // Destroy existing Sortable instance if it exists
            if (this.sortableInstance) {
                this.sortableInstance.destroy();
                this.sortableInstance = null;
            }
            
            // Check if Sortable is available
            if (typeof Sortable === 'undefined') {
                console.warn('Sortable.js is not loaded');
                return;
            }
            
            // Create new Sortable instance
            this.sortableInstance = Sortable.create(taskList, {
                animation: 150,
                handle: '.drag-handle',
                filter: function(evt) {
                    // Prevent dragging if the drag handle has prevent-click class
                    const dragHandle = evt.target.closest('.drag-handle');
                    return dragHandle && dragHandle.classList.contains('prevent-click');
                },
                preventOnFilter: true,
                onEnd: (evt) => {
                    // Don't process if dragged element has prevent-click class
                    const dragHandle = evt.item.querySelector('.drag-handle');
                    if (dragHandle && dragHandle.classList.contains('prevent-click')) {
                        return;
                    }
                    
                    console.log('Drag ended');
                    const taskElements = Array.from(taskList.children);
                    console.log('Task elements:', taskElements);
                    
                    // Get the dragged task ID
                    const draggedTaskId = evt.item.getAttribute('data-id');
                    console.log('Dragged task ID:', draggedTaskId);
                    
                    if (!draggedTaskId) {
                        console.warn('No task ID found for dragged element');
                        return;
                    }
                    
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
            const due = this.parseTaskDateTimeInput(inlineTask.due_date);
            if (!due) return false;
            const now = moment.tz
                ? moment().tz(this.getTaskDisplayTimezone())
                : moment();
            return now.isAfter(due, 'minute');
        },
        
        disposeTooltips(root) {
            const scope = root || document.getElementById('app');
            if (!scope) return;

            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                scope.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
                    const instance = bootstrap.Tooltip.getInstance(el);
                    if (instance) {
                        instance.hide();
                        instance.dispose();
                    }
                });
                document.querySelectorAll('body > .tooltip.show').forEach((el) => el.remove());
            } else if (typeof $ !== 'undefined' && $.fn.tooltip) {
                $(scope).find('[data-bs-toggle="tooltip"]').tooltip('dispose');
            }
        },

        initTooltips() {
            const scope = document.getElementById('app');
            if (!scope) return;

            this.disposeTooltips(scope);

            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                scope.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
                    if (bootstrap.Tooltip.getInstance(el)) return;
                    new bootstrap.Tooltip(el, { trigger: 'hover focus' });
                });
            } else if (typeof $ !== 'undefined' && $.fn.tooltip) {
                $(scope).find('[data-bs-toggle="tooltip"]').tooltip();
            }
        },
        
        formatOverdueDurationText(hours, minutes) {
            const isVi = this.isVietnameseLocale();
            const overdueLabel = this.$t('期限切れ');
            const hourLabel = this.$t('時間');
            const minuteLabel = this.$t('分');
            const fmt = (v, l) => (isVi ? `${v} ${l}` : `${v}${l}`);
            const join = (parts) => (isVi ? parts.filter(Boolean).join(' ') : parts.filter(Boolean).join(''));

            let durationPart = '';
            if (hours > 0) {
                durationPart = join([fmt(hours, hourLabel), fmt(minutes, minuteLabel)]);
            } else {
                durationPart = fmt(minutes, minuteLabel);
            }
            return `${overdueLabel}: ${durationPart}`;
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
            
            return this.formatOverdueDurationText(hours, minutes);
        },
        /** True if task due date is after project end date */
        isTaskDueExceedsProjectDue(task) {
            if (!task || !task.due_date) return false;
            const projectEnd = this.projectInfo && this.projectInfo.end_date;
            if (!projectEnd) return false;
            const taskDue = moment.tz(task.due_date, 'Asia/Tokyo');
            const projEnd = moment.tz(projectEnd, 'Asia/Tokyo');
            return taskDue.isAfter(projEnd, 'minute');
        },
        /** Tooltip when task due exceeds project due */
        getTaskExceedsProjectDueTooltip(task) {
            if (!this.isTaskDueExceedsProjectDue(task)) return '';
            return this.$t ? this.$t('タスクの期限がプロジェクトの期限を超えています') : 'タスクの期限がプロジェクトの期限を超えています';
        },
        /** Combined period warning: overdue and/or exceeds project due */
        hasPeriodWarning(task) {
            return this.isTaskOverdue(task) || this.isTaskDueExceedsProjectDue(task);
        },
        getPeriodWarningTooltip(task) {
            const parts = [];
            if (this.isTaskOverdue(task)) parts.push(this.getOverdueTooltip(task));
            if (this.isTaskDueExceedsProjectDue(task)) parts.push(this.getTaskExceedsProjectDueTooltip(task));
            return parts.join('\n');
        },
        
        getInlineOverdueTooltip(inlineTask) {
            if (!this.isInlineTaskOverdue(inlineTask)) return '';
            
            const due = this.parseTaskDateTimeInput(inlineTask.due_date);
            const now = moment.tz
                ? moment().tz(this.getTaskDisplayTimezone())
                : moment();
            if (!due) return '';
            const duration = moment.duration(now.diff(due));
            const hours = Math.floor(duration.asHours());
            const minutes = Math.floor(duration.asMinutes()) % 60;
            
            return this.formatOverdueDurationText(hours, minutes);
        },
        
        openTaskDetails(task) {
            this.destroyQuillDescriptionEditor();
            this.selectedTask = task;

            const modalEl = document.getElementById('taskDetailsModal');
            if (!modalEl) return;

            if (!this.taskDetailsModalInstance) {
                this.taskDetailsModalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
            }
            this.taskDetailsModalInstance.show();

            this.initQuillEditor();
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
        
        resetQuillEditorDom() {
            const el = document.getElementById('taskDescriptionEditor');
            if (!el) return;

            const parent = el.parentElement;
            if (parent) {
                parent.querySelectorAll('.ql-toolbar').forEach((toolbar) => toolbar.remove());
                parent.querySelectorAll('.ql-container').forEach((container) => {
                    if (container !== el) {
                        container.remove();
                    }
                });
            }

            el.innerHTML = '';
            el.className = '';
            el.setAttribute('id', 'taskDescriptionEditor');
            el.setAttribute('style', 'min-height: 400px;');
            el.removeAttribute('contenteditable');
        },
        destroyQuillDescriptionEditor() {
            if (this.quillEditorInitTimer) {
                clearTimeout(this.quillEditorInitTimer);
                this.quillEditorInitTimer = null;
            }
            if (this.quillEditor) {
                try {
                    if (typeof this.quillEditor.setText === 'function') {
                        this.quillEditor.setText('');
                    }
                    if (typeof this.quillEditor.destroy === 'function') {
                        this.quillEditor.destroy();
                    }
                } catch (error) {
                    console.warn('Error destroying Quill editor:', error);
                }
                this.quillEditor = null;
            }
            this.resetQuillEditorDom();
        },
        initQuillEditor() {
            if (this.quillEditorInitTimer) {
                clearTimeout(this.quillEditorInitTimer);
                this.quillEditorInitTimer = null;
            }

            this.quillEditorInitTimer = setTimeout(() => {
                this.quillEditorInitTimer = null;
                this.$nextTick(() => {
                    const editorElement = document.getElementById('taskDescriptionEditor');
                    if (!editorElement || !window.Quill) {
                        return;
                    }

                    this.destroyQuillDescriptionEditor();

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

                    this.quillEditor = new Quill(editorElement, {
                        bounds: editorElement,
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

                    this.quillEditor.on('text-change', () => {
                        this.addZoomToDescriptionImages();
                    });

                    if (this.selectedTask && this.selectedTask.description) {
                        const cleanHtml = this.decodeHtmlEntities(this.selectedTask.description);
                        this.quillEditor.root.innerHTML = cleanHtml;
                    }
                });
            }, 200);
        },
        
        addZoomToDescriptionImages() {
            this.$nextTick(() => {
                // View mode
                const descEls = document.querySelectorAll('.project-description, #project-description, .ql-editor');
                descEls.forEach(el => {
                    el.querySelectorAll('img:not([data-zoom])').forEach(img => {
                        img.setAttribute('data-zoom', '');
                    });
                });
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
                            this.showMessage(this.$t('画像のアップロードに失敗しました') + ': ' + (response.error || 'Unknown error'), true);
                        }
                    } catch (error) {
                        console.error('Error uploading image:', error);
                        this.showMessage('画像のアップロードに失敗しました。', true);
                    }
                }
            };
        },
        resetQuillEditor() {
            this.destroyQuillDescriptionEditor();
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
            this.showMessage(this.$t('アップロードに失敗しました') + ': ' + fileName, true);
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
        
        // --- Task like/dislike reactions ---
        getTaskReactionCount(task, type) {
            if (!task) return 0;
            return type === 'like' ? (task.like_count || 0) : (task.dislike_count || 0);
        },

        getTaskReactionButtonClass(task, type) {
            const base = 'btn btn-sm';
            const isActive = task && task.current_user_reaction === type;
            if (type === 'like') {
                return `${base} ${isActive ? 'btn-success' : 'btn-outline-success'}`;
            } else {
                return `${base} ${isActive ? 'btn-danger' : 'btn-outline-danger'}`;
            }
        },
        
        getTaskReactionTooltip(task, type) {
            if (!task) return '';
            const names = type === 'like' ? (task.liked_by_names || []) : (task.disliked_by_names || []);
            if (names.length === 0) {
                return type === 'like' ? '良い' : '悪い';
            }
            return names.join(', ');
        },

        async openReactionModal(task, type) {
            if (!task || !task.id) return;

            const scope = document.getElementById('app');
            if (scope && typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                scope.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
                    const instance = bootstrap.Tooltip.getInstance(el);
                    if (instance) instance.hide();
                });
                document.querySelectorAll('body > .tooltip.show').forEach((el) => el.remove());
            }
            
            this.reactionModal.taskId = task.id;
            this.reactionModal.type = type;
            
            // Reset form
            this.reactionModal.selectedReasons = [];
            this.reactionModal.customNote = '';
            
            // If user already reacted with this type, load existing data
            if (task.current_user_reaction === type) {
                try {
                    const response = await axios.get(`/api/index.php?model=task&method=getTaskReaction&task_id=${task.id}`);
                    if (response.data && response.data.success) {
                        // Load selected reasons (if stored as comma-separated string or array)
                        if (response.data.selected_reasons) {
                            if (Array.isArray(response.data.selected_reasons)) {
                                this.reactionModal.selectedReasons = response.data.selected_reasons;
                            } else if (typeof response.data.selected_reasons === 'string') {
                                this.reactionModal.selectedReasons = response.data.selected_reasons.split(',').filter(r => r.trim());
                            }
                        }
                        // Load custom note - only use custom_note, not the combined note
                        // If selected_reasons exists (even if empty array), it means we're using new format
                        if (response.data.hasOwnProperty('selected_reasons')) {
                            // New format: only use custom_note, ignore combined note
                            this.reactionModal.customNote = response.data.custom_note || '';
                        } else {
                            // Old format: use note as custom_note (backward compatibility)
                            this.reactionModal.customNote = response.data.custom_note || response.data.note || '';
                        }
                    } else {
                        this.reactionModal.selectedReasons = [];
                        this.reactionModal.customNote = '';
                    }
                } catch (error) {
                    console.error('Error loading reaction:', error);
                    this.reactionModal.selectedReasons = [];
                    this.reactionModal.customNote = '';
                }
            } else {
                this.reactionModal.selectedReasons = [];
                this.reactionModal.customNote = '';
            }
            
            this.reactionModal.show = true;

            // Open Bootstrap modal
            this.$nextTick(() => {
                const modalEl = document.getElementById('taskReactionModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.show();
                    // Translate i18n elements in modal
                    if (typeof localize === 'function') {
                        localize();
                    } else if (typeof i18next !== 'undefined' && i18next.isInitialized) {
                        const i18nList = modalEl.querySelectorAll('[data-i18n]');
                        i18nList.forEach(function (item) {
                            item.innerHTML = i18next.t(item.dataset.i18n);
                        });
                    }
                }
            });
        },

        async submitReaction() {
            if (!this.reactionModal.taskId || !this.reactionModal.type) return;
            try {
                const formData = new FormData();
                formData.append('task_id', this.reactionModal.taskId);
                formData.append('type', this.reactionModal.type);
                // Send selected reasons as comma-separated string
                formData.append('selected_reasons', this.reactionModal.selectedReasons.join(','));
                // Send custom note
                formData.append('custom_note', this.reactionModal.customNote || '');
                // Keep backward compatibility: combine reasons and custom note into note field
                const reasonLabels = this.reactionModal.selectedReasons.map(reasonId => {
                    const reasons = this.reactionModal.type === 'like' ? this.likeReasons : this.dislikeReasons;
                    const reason = reasons.find(r => r.id === reasonId);
                    // Use i18n translation if available, otherwise use label
                    return reason ? (this.$t(reason.i18nKey || reason.label)) : reasonId;
                });
                const combinedNote = reasonLabels.length > 0 
                    ? reasonLabels.join(', ') + (this.reactionModal.customNote ? '\n' + this.reactionModal.customNote : '')
                    : this.reactionModal.customNote;
                formData.append('note', combinedNote || '');

                const response = await axios.post('/api/index.php?model=task&method=toggleTaskReaction', formData);
                const data = response.data || {};

                if (!data.success) {
                    this.showMessage(data.message || 'リアクションの更新に失敗しました', true);
                    return;
                }

                // Reload tasks to get updated reaction data
                await this.loadTasks();
                
                // Reinitialize tooltips after reload
                this.$nextTick(() => {
                    this.initTooltips();
                });

                // Close modal
                const modalEl = document.getElementById('taskReactionModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
                this.reactionModal.show = false;
                this.reactionModal.taskId = null;
                this.reactionModal.type = null;
                this.reactionModal.selectedReasons = [];
                this.reactionModal.customNote = '';

                this.showMessage('リアクションを保存しました', false);
            } catch (error) {
                console.error('Error submitting reaction:', error);
                this.showMessage('リアクションの更新に失敗しました', true);
            }
        },
        
        async removeReaction() {
            if (!this.reactionModal.taskId || !this.reactionModal.type) return;
            try {
                const formData = new FormData();
                formData.append('task_id', this.reactionModal.taskId);
                formData.append('type', this.reactionModal.type);
                formData.append('delete', '1');

                const response = await axios.post('/api/index.php?model=task&method=toggleTaskReaction', formData);
                const data = response.data || {};

                if (!data.success) {
                    this.showMessage(data.message || 'リアクションの削除に失敗しました', true);
                    return;
                }

                // Reload tasks to get updated reaction data
                await this.loadTasks();
                
                // Reinitialize tooltips after reload
                this.$nextTick(() => {
                    this.initTooltips();
                });

                // Close modal
                const modalEl = document.getElementById('taskReactionModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
                this.reactionModal.show = false;
                this.reactionModal.taskId = null;
                this.reactionModal.type = null;
                this.reactionModal.selectedReasons = [];
                this.reactionModal.customNote = '';

                this.showMessage('リアクションを削除しました', false);
            } catch (error) {
                console.error('Error removing reaction:', error);
                this.showMessage('リアクションの削除に失敗しました', true);
            }
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
            if (!task.assigned_to) return false;
            return task.assigned_to.split(',').includes(this.currentUserId) || task.created_by == this.currentUserId;
        },
        /** True if the current user created this task */
        isTaskCreatedByMe(task) {
            if (!task || task.created_by == null || task.created_by === undefined) return false;
            return String(task.created_by) === String(this.currentUserId);
        },
        
        isAssignedToMe(task) {
            if (!task.assigned_to) return false;
            const assignedIds = task.assigned_to.split(',').map(id => id.trim());
            return assignedIds.includes(this.currentUserId.toString());
        },

        /**
         * Kiểm tra có được like/dislike task này hay không theo rule:
         * - Nếu số người được giao > 1 => luôn cho phép (nếu có quyền canLikeTask)
         * - Nếu số người được giao <= 1 và current user là người được giao => KHÔNG cho phép
         * - Các trường hợp khác => cho phép (nếu có quyền canLikeTask)
         */
        canReactToTask(task) {
            if (!this.canLikeTask) return false;
            if (!task || !task.assigned_to) return true;

            const assignedIds = task.assigned_to
                .split(',')
                .map(id => id.trim())
                .filter(id => id);

            if (assignedIds.length <= 1 &&
                assignedIds.includes(this.currentUserId.toString())) {
                return false;
            }

            return true;
        },
        
        isAcknowledged(task, userId) {
            if (!task.acknowledgements || !userId) return false;
            const ack = task.acknowledgements[userId.toString()];
            return ack && ack.acknowledged == 1;
        },
        
        getAcknowledgedAt(task, userId) {
            if (!task.acknowledgements || !userId) return null;
            const ack = task.acknowledgements[userId.toString()];
            return ack && ack.acknowledged_at ? ack.acknowledged_at : null;
        },
        
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
                    const currentUserIdStr = this.currentUserId.toString();
                    task.acknowledgements[currentUserIdStr] = {
                        acknowledged: 1,
                        acknowledged_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
                    };
                    if (!silent) {
                        this.showMessage('タスクを受領しました。', false);
                    }
                    if (!silent) {
                        await this.loadTasks();
                    }
                } else {
                    if (!silent) {
                        this.showMessage(response.data?.message || 'エラーが発生しました。', true);
                    }
                }
            } catch (error) {
                console.error('Error acknowledging task:', error);
                if (!silent) {
                    this.showMessage('エラーが発生しました。', true);
                }
            }
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
                        task.assigned_to = newAssignees.length ? String(newAssignees[0]).trim() : '';
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
