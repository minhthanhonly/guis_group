var gantt;
var statuses = [
    {
        key: 'all',
        name: 'すべて',
        color: 'secondary'
    },
    {
        key: 'todo',
        name: '未開始',
        color: 'secondary'
    },
    {
        key: 'in-progress',
        name: '進行中',
        color: 'primary'
    },
    {
        key: 'confirming',
        name: '確認中',
        color: 'warning'
    },
    {
        key: 'paused',
        name: '一時停止',
        color: 'warning'
    },
    {
        key: 'completed',
        name: '完了',
        color: 'success'
    },
    {
        key: 'cancelled',
        name: 'キャンセル',
        color: 'danger'
    }
];

var priorities = [
    {
        key: 'low',
        name: '低',
        color: 'secondary'
    },
    {
        key: 'medium',
        name: '中',
        color: 'primary'
    },
    {
        key: 'high',
        name: '高',
        color: 'warning'
    },
    {
        key: 'urgent',
        name: '緊急',
        color: 'danger'
    },
];

$(document).ready(function() {
    // Initialize Vue app first
    const app = Vue.createApp({
        data() {
            return {
                projectId: PROJECT_ID,
                projectInfo: {},
                tasks: [],
                filteredTasks: [],
                selectedStatus: null,
                statuses: statuses,
                priorities: priorities,
                loading: false,
                ganttInitialized: false,
                isFullscreen: false,
                currentScale: 'week',
                startDate: null,
                endDate: null,
                ganttLinks: []
            }
        },
        async mounted() {
            await this.loadProjectInfo();
            await this.loadTasks();
            await this.loadLinks();
            // Initialize Gantt after Vue has rendered
            this.$nextTick(() => {
                this.initGantt();
            });
            
            // Handle window resize
            window.addEventListener('resize', this.handleResize);
            
            // Handle fullscreen change events
            document.addEventListener('fullscreenchange', this.handleFullscreenChange);
            document.addEventListener('webkitfullscreenchange', this.handleFullscreenChange);
            document.addEventListener('mozfullscreenchange', this.handleFullscreenChange);
            document.addEventListener('MSFullscreenChange', this.handleFullscreenChange);
        },
        beforeUnmount() {
            // Clean up Gantt when component is destroyed
            if (gantt && this.ganttInitialized) {
                gantt.clearAll();
                gantt.destructor();
                this.ganttInitialized = false;
            }
            // Remove resize listener
            window.removeEventListener('resize', this.handleResize);
            
            // Remove fullscreen listeners
            document.removeEventListener('fullscreenchange', this.handleFullscreenChange);
            document.removeEventListener('webkitfullscreenchange', this.handleFullscreenChange);
            document.removeEventListener('mozfullscreenchange', this.handleFullscreenChange);
            document.removeEventListener('MSFullscreenChange', this.handleFullscreenChange);
        },
        methods: {
            async loadProjectInfo() {
                try {
                    this.loading = true;
                    const response = await $.ajax({
                        url: '/api/index.php',
                        type: 'GET',
                        data: {
                            model: 'project',
                            method: 'getById',
                            id: this.projectId
                        }
                    });
                    
                    if (response && response.id) {
                        this.projectInfo = response;
                    }
                } catch (error) {
                    console.error('Error loading project info:', error);
                } finally {
                    this.loading = false;
                }
            },

            async loadTasks() {
                try {
                    this.loading = true;
                    const response = await $.ajax({
                        url: '/api/index.php',
                        type: 'GET',
                        data: {
                            model: 'task',
                            method: 'getByProject',
                            project_id: this.projectId
                        }
                    });
                    
                    if (Array.isArray(response)) {
                        this.tasks = response || [];
                        this.filteredTasks = [...this.tasks];
                        this.updateGanttData();
                    }
                } catch (error) {
                    console.error('Error loading tasks:', error);
                } finally {
                    this.loading = false;
                }
            },

            async loadLinks() {
                try {
                    const response = await $.ajax({
                        url: '/api/index.php',
                        type: 'GET',
                        data: {
                            model: 'task',
                            method: 'getLinksByProject',
                            project_id: this.projectId
                        }
                    });
                    this.ganttLinks = Array.isArray(response) ? response : [];
                } catch (e) {
                    this.ganttLinks = [];
                }
            },

            async addTaskLink(link) {
                const formData = new FormData();
                formData.append('source_task_id', link.source);
                formData.append('target_task_id', link.target);
                formData.append('project_id', this.projectId);
                formData.append('link_type', link.type);
                await axios.post('/api/index.php?model=task&method=addTaskLink', formData);
            },
            async deleteTaskLink(link) {
                const formData = new FormData();
                formData.append('source_task_id', link.source);
                formData.append('target_task_id', link.target);
                formData.append('project_id', this.projectId);
                await axios.post('/api/index.php?model=task&method=deleteTaskLink', formData);
            },

            filterTasksByStatus(status) {
                this.selectedStatus = status.key === 'all' ? null : status;
                this.updateFilteredTasks();
            },

            updateFilteredTasks() {
                if (!this.selectedStatus) {
                    this.filteredTasks = [...this.tasks];
                } else {
                    this.filteredTasks = this.tasks.filter(task => task.status === this.selectedStatus.key);
                }
                this.updateGanttData();
            },

            updateGanttData() {
                if (gantt) {
                    const ganttData = this.convertTasksToGanttData(this.filteredTasks);
                    gantt.clearAll();
                    gantt.parse({
                        data: ganttData.data,
                        links: (this.ganttLinks || []).map(link => ({
                            id: link.id,
                            source: link.source_task_id,
                            target: link.target_task_id,
                            type: link.link_type
                        }))
                    });
                    if (!this.startDate || !this.endDate) {
                        this.setDefaultDateRange();
                    }
                    
                    gantt.addMarker({
                        start_date: new Date(),
                        css: "current_time_marker",
                        title: "現在時刻",
                        text: "現在"
                    });
                    setInterval(function() {
                        gantt.updateMarker("current_time_marker");
                    }, 6000);
                }
                
            },

            convertTasksToGanttData(tasks) {
                const ganttTasks = [];
                if (!this.projectInfo || !this.projectInfo.id) return { data: [], links: [] };

                // Tìm min/max ngày của các task con
                let minDate = null, maxDate = null;
                tasks.forEach(task => {
                    if (task.start_date) {
                        const d = new Date(task.start_date);
                        if (!minDate || d < minDate) minDate = d;
                    }
                    if (task.due_date) {
                        const d = new Date(task.due_date);
                        if (!maxDate || d > maxDate) maxDate = d;
                    }
                });

                // Nếu projectInfo có ngày thì ưu tiên
                const projectStart = this.projectInfo.start_date ? new Date(this.projectInfo.start_date) : minDate;
                const projectEnd = this.projectInfo.end_date ? new Date(this.projectInfo.end_date) : maxDate;

                // Thêm task project ở trên cùng
                ganttTasks.push({
                    id: 1,
                    text: this.projectInfo.name || 'プロジェクト',
                    type: 'project',
                    start_date: projectStart,
                    end_date: projectEnd,
                    progress: 0,
                    parent: null,
                    open: true,
                    status: '',
                    priority: '',
                    statusColor: 'primary',
                    priorityColor: 'primary'
                });

                // Thêm các task con, nếu parent_id null thì parent là 1
                tasks.forEach(task => {
                    const statusObj = statuses.find(s => s.key === task.status);
                    const priorityObj = priorities.find(p => p.key === task.priority);
                    ganttTasks.push({
                        id: task.id,
                        text: task.title,
                        start_date: this.parseDate(task.start_date),
                        end_date: this.parseDate(task.due_date),
                        progress: task.progress || 0,
                        parent: task.parent_id ? task.parent_id : 1,
                        priority: task.priority || 'medium',
                        status: task.status || 'todo',
                        type: this.getTaskType(task),
                        assignee: task.assigned_to,
                        description: task.description || '',
                        estimated_hours: task.estimated_hours || 0,
                        actual_hours: task.actual_hours || 0,
                        open: true,
                        statusColor: statusObj ? statusObj.color : 'secondary',
                        priorityColor: priorityObj ? priorityObj.color : 'secondary'
                    });
                });

                return {
                    data: ganttTasks,
                    links: [] // No links needed, using parent-child structure
                };
            },

            sortTasksByHierarchy(tasks) {
                const result = [];
                const taskMap = new Map();
                
                // Create a map for quick lookup
                tasks.forEach(task => {
                    taskMap.set(task.id, task);
                });
                
                // Add parent tasks first
                tasks.forEach(task => {
                    if (!task.parent_id) {
                        result.push(task);
                        this.addChildTasks(task.id, tasks, result, taskMap);
                    }
                });
                
                return result;
            },

            addChildTasks(parentId, allTasks, result, taskMap) {
                allTasks.forEach(task => {
                    if (task.parent_id === parentId) {
                        result.push(task);
                        this.addChildTasks(task.id, allTasks, result, taskMap);
                    }
                });
            },

            getTaskType(task) {
                if (task.parent_id) {
                    return 'task'; // Subtask
                }
                return 'project'; // Main task
            },

            getTaskClassName(task) {
                const classes = [];
                
                // Status-based classes
                switch (task.status) {
                    case 'completed':
                        classes.push('gantt_completed');
                        break;
                    case 'paused':
                        classes.push('gantt_paused');
                        break;
                    case 'cancelled':
                        classes.push('gantt_cancelled');
                        break;
                    default:
                        classes.push('gantt_task');
                }
                
                // Priority-based classes
                if (task.priority === 'high' || task.priority === 'urgent') {
                    classes.push('gantt_high_priority');
                }
                
                // Overdue check
                if (this.isTaskOverdue(task)) {
                    classes.push('gantt_overdue');
                }
                
                return classes.join(' ');
            },

            isTaskOverdue(task) {
                if (!task.due_date || task.status === 'completed') {
                    return false;
                }
                
                const dueDate = new Date(task.due_date);
                const now = new Date();
                return dueDate < now;
            },

            parseDate(dateString) {
                if (!dateString) {
                    return new Date();
                }
                
                // Try to parse the date string
                const date = new Date(dateString);
                if (isNaN(date.getTime())) {
                    return new Date();
                }
                
                return date;
            },

            setDefaultDateRange() {
                if (this.filteredTasks.length === 0) {
                    const now = new Date();
                    this.startDate = new Date(now.getFullYear(), now.getMonth(), 1);
                    this.endDate = new Date(now.getFullYear(), now.getMonth() + 2, 0);
                } else {
                    let minDate = new Date();
                    let maxDate = new Date();
                    
                    this.filteredTasks.forEach(task => {
                        if (task.start_date) {
                            const startDate = new Date(task.start_date);
                            if (startDate < minDate) minDate = startDate;
                        }
                        if (task.due_date) {
                            const endDate = new Date(task.due_date);
                            if (endDate > maxDate) maxDate = endDate;
                        }
                    });
                    
                    // Add some padding
                    this.startDate = new Date(minDate.getTime() - 7 * 24 * 60 * 60 * 1000);
                    this.endDate = new Date(maxDate.getTime() + 7 * 24 * 60 * 60 * 1000);
                }
                
                this.updateDateInputs(this.startDate, this.endDate);
                if (gantt && this.ganttInitialized) {
                    gantt.setDateRange(this.startDate, this.endDate);
                }
            },

            refreshGantt() {
                this.loadTasks();
            },

            setDefaultScale() {
                this.currentScale = 'week';
                if (gantt && this.ganttInitialized) {
                    gantt.config.scale_unit = 'week';
                    gantt.config.date_scale = '%M %d';
                    gantt.render();
                }
            },

            setMonthScale() {
                if (!gantt || !this.ganttInitialized) return;
                this.currentScale = 'month';
                gantt.config.scale_unit = "month";
                gantt.config.date_scale = "%m月";
                gantt.config.subscales = [
                    { unit: "week", step: 1, date: "%d日" }
                ];
                gantt.render();
            },
            
            setWeekScale() {
                if (!gantt || !this.ganttInitialized) return;
                this.currentScale = 'week';
                gantt.config.scale_unit = "week";
                gantt.config.date_scale = "%m月";
                gantt.config.subscales = [
                    { unit: "day", step: 1, date: "%d日" }
                ];
                gantt.render();
            },
            
            setDayScale() {
                if (!gantt || !this.ganttInitialized) return;
                this.currentScale = 'hour';
                gantt.config.scale_unit = "hour";
                gantt.config.date_scale = "%m月%d日";
                gantt.config.subscales = [
                    { unit: "minute", step: 30, date: "%H:%i" }
                ];
                gantt.render();
            },

            changeDates() {
                if (!gantt || !this.ganttInitialized) return;
                
                const startDateEl = document.querySelector(".start_date");
                const endDateEl = document.querySelector(".end_date");
                
                if (!startDateEl || !endDateEl) return;
                
                const startDate = new Date(startDateEl.value);
                const endDate = new Date(endDateEl.value);

                if (!+startDate || !+endDate) {
                    return;
                }

                // Ensure end date is after start date
                if (endDate <= startDate) {
                    showMessage('終了日は開始日より後である必要があります。', true);
                    return;
                }

                // Set the date range using DHTMLX Gantt methods
                gantt.config.start_date = startDate;
                gantt.config.end_date = endDate;
                
                // Force the Gantt to recalculate and render
                gantt.render();
                
            },

            updateDateInputs(startDate, endDate) {
                const startInput = document.querySelector('.start_date');
                const endInput = document.querySelector('.end_date');
                
                if (startInput && startDate) {
                    startInput.value = this.formatDateForInput(startDate);
                }
                if (endInput && endDate) {
                    endInput.value = this.formatDateForInput(endDate);
                }
            },

            formatDateForInput(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            },

            toggleFullscreen() {
                const container = document.getElementById('gantt_container');
                
                if (!this.isFullscreen) {
                    if (container.requestFullscreen) {
                        container.requestFullscreen();
                    } else if (container.webkitRequestFullscreen) {
                        container.webkitRequestFullscreen();
                    } else if (container.msRequestFullscreen) {
                        container.msRequestFullscreen();
                    }
                } else {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    } else if (document.webkitExitFullscreen) {
                        document.webkitExitFullscreen();
                    } else if (document.msExitFullscreen) {
                        document.msExitFullscreen();
                    }
                }
            },

            handleResize() {
                if (gantt && this.ganttInitialized) {
                    gantt.setSizes();
                }
            },

            handleFullscreenChange() {
                this.isFullscreen = !!(document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement);
                
                if (gantt && this.ganttInitialized) {
                    setTimeout(() => {
                        gantt.setSizes();
                    }, 100);
                }
            },

            initGantt() {
                if (this.ganttInitialized) {
                    return;
                }

                // Configure Gantt - using same config as project-gantt
                gantt.config.date_format = "%m月%d日";
                gantt.config.scale_unit = "week";
                gantt.config.date_scale = "%m月";
                gantt.config.subscales = [
                    { unit: "day", step: 1, date: "%d日" }
                ];
                
                gantt.plugins({
                    tooltip: true,
                    marker: true
                });
                
                gantt.config.drag_lightbox = true;
                gantt.config.drag_timeline = {
                    ignore:".gantt_task_line, .gantt_task_link",
                    useKey: false,
                    render: false
                };
                
                gantt.config.lightbox.sections = [
                    {name: "time", type: "time", map_to: "auto", time_format: ["%Y", "%m", "%d", "%H:%i"]}
                ];
                
                gantt.config.buttons_left = ["gantt_save_btn", "gantt_cancel_btn"];
                gantt.config.buttons_right = [];
                gantt.i18n.setLocale('jp');
                
                // Weekend highlighting
                gantt.templates.scale_cell_class = function (date) {
                    if (date.getDay() == 0 || date.getDay() == 6) {
                        return "weekend";
                    }
                };
                
                gantt.templates.timeline_cell_class = function (item, date) {
                    if (date.getDay() == 0 || date.getDay() == 6) {
                        return "weekend"
                    }
                };
                
                // Enable features
                gantt.config.drag_progress = false;
                gantt.config.drag_resize = true;
                gantt.config.drag_move = true;
                gantt.config.drag_links = true;
                gantt.config.drag_plan = false;
                
                gantt.config.row_height = 30;
                gantt.config.grid_resize = true;
                gantt.config.grid_elastic_columns = true;
                
                // Layout configuration - same as project-gantt
                gantt.config.layout = {
                    css: "gantt_container",
                    cols: [
                        {
                            width: 400,
                            rows: [
                                { view: "grid", scrollable: true, scrollX: "scrollHor1", scrollY: "scrollVer" },
                                { view: "scrollbar", id: "scrollHor1", scroll: 'x', group: 'hor' },
                            ]
                        },
                        { resizer: true, width: 1 },
                        {
                            rows: [
                                { view: "timeline", scrollX: "scrollHor", scrollY: "scrollVer" },
                                { view: "scrollbar", id: "scrollHor", scroll: 'x', group: 'hor' },
                            ]
                        },
                        { view: "scrollbar", id: "scrollVer" }
                    ]
                };
                
                // Columns configuration - adapted for tasks
                gantt.config.columns = [
                    { name: "text", label: "タスク名", width: 200, tree: true, min_width: 150 },
                    { name: "status", label: "ステータス", width: 100, align: "left", min_width: 80, template: function(obj) {
                        const status = statuses.find(s => s.key === obj.status);
                        return status ? status.name : obj.status;
                    }},
                    { name: "priority", label: "優先度", width: 100, align: "left", min_width: 80, template: function(obj) {
                        const priority = priorities.find(p => p.key === obj.priority);
                        return priority ? priority.name : obj.priority;
                    }},
                    { name: "start_date", label: "開始日", width: 140, align: "left", min_width: 120, template: (obj) => {
                        return this.formatDateTimeFull(obj.start_date);
                    }},
                    { name: "end_date", label: "期限日", width: 140, align: "left", min_width: 120, template: (obj) =>  {
                        return this.formatDateTimeFull(obj.end_date);
                    }},
                    { name: "progress", label: "進捗", width: 80, align: "left", min_width: 60, template: function(obj) {
                        return Math.round(obj.progress * 100) + "%";
                    }}
                ];
                
                // Customize task appearance - same color scheme as project-gantt
                gantt.templates.task_class = function(start, end, task) {
                    let classes = [];
                    
                    // Add status color
                    if (task.statusColor) {
                        classes.push(`gantt-status-${task.statusColor}`);
                    }
                    
                    // Add priority indicator
                    if (task.priorityColor) {
                        classes.push(`gantt-priority-${task.priorityColor}`);
                    }
                    
                    // Add overdue indicator
                    if (task.end_date && new Date(task.end_date) < new Date()) {
                        classes.push('gantt-overdue');
                    }
                    
                    return classes.join(' ');
                };
                
                // Customize tooltip
                gantt.templates.tooltip_text = (start, end, task) => {
                    return `
                        <div class="gantt-tooltip">
                            <div class="gantt-tooltip-title">${task.text}</div>
                            <div class="gantt-tooltip-content">
                                <div>開始: ${this.formatDateTimeFull(start)}</div>
                                <div>終了: ${this.formatDateTimeFull(end)}</div>
                                <div>進捗: ${Math.round(task.progress * 100)}%</div>
                                <div>ステータス: ${statuses.find(s => s.key === task.status)?.name || task.status}</div>
                                <div>優先度: ${priorities.find(p => p.key === task.priority)?.name || task.priority}</div>
                            </div>
                        </div>
                    `;
                };
                
                gantt.templates.tooltip_date_format = (date) => {
                    return this.formatDateTimeFull(date);
                };
                
                // Event handlers
                gantt.attachEvent('onTaskDrag', (id, mode, task, original) => {
                    return true; // Allow dragging
                });
                
                gantt.attachEvent('onAfterTaskUpdate', (id, task) => {
                    this.updateTaskInDatabase(task);
                });
                
                gantt.attachEvent('onAfterTaskDrop', (id, parent, tindex) => {
                    // Handle parent-child relationship changes
                    this.updateTaskParent(id, parent);
                });
                
                gantt.attachEvent('onAfterLinkAdd', (id, link) => {
                    this.addTaskLink(link);
                });
                gantt.attachEvent('onAfterLinkDelete', (id, link) => {
                    this.deleteTaskLink(link);
                });
                
                // Initialize Gantt
                gantt.init('gantt_container');
                
                this.updateGanttData();
                this.ganttInitialized = true;
                this.addGanttStyles();

                
            },

            async updateTaskInDatabase(task) {
                try {
                    const formData = new FormData();
                    formData.append('id', task.id);
                    formData.append('start_date', this.formatDateTimeForAPI(task.start_date));
                    formData.append('due_date', this.formatDateTimeForAPI(task.end_date));
                    formData.append('progress', task.progress);
                    
                    const response = await axios.post('/api/index.php?model=task&method=updateTaskDate', formData);
                    if (response.data && response.data.success !== false) {
                        console.log('Task updated successfully');
                    } else {
                        console.error('Failed to update task');
                    }
                } catch (error) {
                    console.error('Error updating task:', error);
                }
            },

            async updateTaskParent(taskId, parentId) {
                try {
                    const formData = new FormData();
                    formData.append('task_id', taskId);
                    formData.append('parent_id', parentId || '');
                    
                    const response = await axios.post('/api/index.php?model=task&method=updateTaskParent', formData);
                    if (response.data && response.data.success !== false) {
                        console.log('Task parent updated successfully');
                        // Refresh the gantt data
                        this.loadTasks();
                    } else {
                        console.error('Failed to update task parent');
                    }
                } catch (error) {
                    console.error('Error updating task parent:', error);
                }
            },

            formatDateTimeForAPI(datetime) {
                if (!datetime) return '';
                const date = new Date(datetime);
                return date.toISOString().slice(0, 19).replace('T', ' ');
            },

            addGanttStyles() {
                // Add custom styles for better appearance - same as project-gantt
                const style = document.createElement('style');
                style.textContent = `
                    /* Current time marker */
                    .current_time_marker {
                        background-color: var(--bs-danger);
                        width: 2px;
                        opacity: 0.8;
                        z-index: 10;
                    }
                    /* Weekend styling */
                    .gantt_scale_cell.weekend {
                        background-color: #f8f9fa;
                    }
                    
                    .gantt_task_cell.weekend {
                        background-color: #f8f9fa;
                    }
                    .gantt_task_content{
                        text-align: left !important;
                        padding-left: 10px !important;
                    }
                    
                    /* Overdue projects */
                    .gantt-overdue .gantt_task_line { 
                        border-color: var(--bs-danger); 
                        border-width: 3px; 
                        border-style: dashed; 
                    }
                    .gantt-overdue .gantt_task_content {
                        background-color: var(--bs-danger);
                        color: white;
                    }

                    /* Status colors for task bars */
                    .gantt-status-info .gantt_task_content { 
                        background-color: var(--bs-info); 
                        color: white;
                    }
                    .gantt-status-warning .gantt_task_content { 
                        background-color: var(--bs-warning); 
                        color: #212529;
                    }
                    .gantt-status-primary .gantt_task_content { 
                        background-color: var(--bs-primary); 
                        color: white;
                    }
                    .gantt-status-success .gantt_task_content { 
                        background-color: var(--bs-success); 
                        color: white;
                    }
                    .gantt-status-danger .gantt_task_content { 
                        background-color: var(--bs-danger); 
                        color: white;
                    }
                    .gantt-status-secondary .gantt_task_content { 
                        background-color: var(--bs-secondary); 
                        color: white;
                    }
                    
                    /* Priority border colors */
                    .gantt-priority-danger .gantt_task_line { 
                        border-color: var(--bs-danger); 
                    }
                    .gantt-priority-warning .gantt_task_line { 
                        border-color: var(--bs-warning); 
                    }
                    .gantt-priority-primary .gantt_task_line { 
                        border-color: var(--bs-primary); 
                    }
                    .gantt-priority-secondary .gantt_task_line { 
                        border-color: var(--bs-secondary); 
                    }
                    
                  
                    /* Grid styling */
                    .gantt_grid_data .gantt_cell {
                        border-right: 1px solid #e0e0e0;
                        border-bottom: 1px solid #e0e0e0;
                    }
                    
                    .gantt_grid_head_cell {
                        background-color: #f8f9fa;
                        border-bottom: 2px solid #dee2e6;
                        font-weight: 600;
                        color: #495057;
                    }
                    
                    /* Progress styling */
                    .gantt_task_progress {
                        background-color: rgba(255, 255, 255, 0.3);
                        border-radius: 2px;
                    }
                    
                    .gantt_task_progress_wrapper {
                        background-color: rgba(0, 0, 0, 0.1);
                        border-radius: 2px;
                    }
                    .gantt-tooltip-content div {
                        margin-bottom: 4px;
                    }
                `;
                document.head.appendChild(style);
            },

            formatDateTimeFull(datetime) {
                if (!datetime) return '';
                const d = new Date(datetime);
                const y = d.getFullYear();
                const m = (d.getMonth() + 1).toString().padStart(2, '0');
                const day = d.getDate().toString().padStart(2, '0');
                const h = d.getHours().toString().padStart(2, '0');
                const min = d.getMinutes().toString().padStart(2, '0');
                // Bạn có thể đổi sang dạng 'YYYY-MM-DD HH:mm' hoặc 'YYYY年MM月DD日 HH:mm'
                return `${m}月${day}日 ${h}:${min}`;
            }
        }
    }).mount('#app');
});

// Utility functions
function getInitials(name) {
    if (!name) return '';
    return name.split(' ').map(n => n.charAt(0)).join('').toUpperCase().substring(0, 2);
}

function decodeHtmlEntities(str) {
    if (!str) return '';
    const textarea = document.createElement('textarea');
    textarea.innerHTML = str;
    return textarea.value;
} 