var gantt;
var projectData = [];
var statuses = [
    {
        key: 'all',
        name: 'すべて',
        color: 'secondary'
    },
    {
        key: 'open',
        name: 'オープン',
        color: 'info'
    },
    {
        key: 'confirming',
        name: '確認中',
        color: 'warning'
    },
    {
        key: 'in_progress',
        name: '進行中',
        color: 'primary'
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
                departments: [],
                selectedDepartment: null,
                selectedStatus: null,
                statuses: statuses,
                priorities: priorities,
                userPermissions: {},
                loading: false,
                ganttInitialized: false,
                isFullscreen: false,
                currentScale: 'week'
            }
        },
        computed: {
            createUrl() {
                return this.selectedDepartment ? `create.php?department_id=${this.selectedDepartment.id}` : 'create.php';
            }
        },
        async mounted() {
            await this.loadDepartments();
            await this.loadUserPermissions();
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
            async loadDepartments() {
                try {
                    const response = await $.ajax({
                        url: '/api/index.php',
                        type: 'GET',
                        data: {
                            model: 'department',
                            method: 'list'
                        }
                    });
                    
                    if (Array.isArray(response) && response.length > 0) {
                        this.departments = response || [];
                        // Auto-select first department if available
                        if (this.departments.length > 0) {
                            const firstDept = this.departments.find(dept => dept.can_project == 1);
                            if (firstDept) {
                                this.viewProjects(firstDept);
                            }
                        }
                    }
                } catch (error) {
                    console.error('Error loading departments:', error);
                }
            },
            
            async loadUserPermissions() {
                try {
                    const response = await $.ajax({
                        url: '/api/index.php',
                        type: 'GET',
                        data: {
                            model: 'department',
                            method: 'get_user_permissions'
                        }
                    });
                    
                    if (Array.isArray(response) && response.length > 0) {
                        this.userPermissions = response || {};
                    }
                } catch (error) {
                    console.error('Error loading user permissions:', error);
                }
            },
            
            hasPermission(permission) {
                return this.userPermissions[permission] || false;
            },
            
            canAddProject() {
                return this.hasPermission('project_add') && this.selectedDepartment;
            },
            
            canEditProject() {
                return this.hasPermission('project_edit');
            },
            
            canDeleteProject() {
                return this.hasPermission('project_delete');
            },
            
            canManageProject() {
                return this.hasPermission('project_manage');
            },
            
            canCommentProject() {
                return this.hasPermission('project_comment');
            },
            
            viewProjects(department) {
                this.selectedDepartment = department;
                this.selectedStatus = null;
                this.loadProjects();
            },
            
            filterProjectByStatus(status) {
                this.selectedStatus = status.key === 'all' ? null : status;
                this.loadProjects();
            },
            
            async loadProjects() {
                if (!this.selectedDepartment) return;
                
                this.loading = true;
                try {
                    const response = await $.ajax({
                        url: '/api/index.php',
                        type: 'GET',
                        data: {
                            model: 'project',
                            method: 'listForGantt',
                            department_id: this.selectedDepartment.id,
                            status: this.selectedStatus?.key || 'active'
                        }
                    });
                    
                    if (Array.isArray(response) && response.length > 0) {
                        projectData = response || [];
                        this.updateGanttData();
                    } else {
                        projectData = [];
                        this.updateGanttData();
                    }
                } catch (error) {
                    console.error('Error loading projects:', error);
                    projectData = [];
                    this.updateGanttData();
                } finally {
                    this.loading = false;
                }
            },
            
            updateGanttData() {
                if (!gantt || !this.ganttInitialized) return;
                
                const ganttData = this.convertProjectsToGanttData(projectData);
                console.log('Gantt data to be parsed:', ganttData);
                
                gantt.clearAll();
                gantt.parse(ganttData);
                
                // Set default date range and initialize date inputs
                if (ganttData.data && ganttData.data.length > 0) {
                    const dates = ganttData.data.map(task => [task.start_date, task.end_date]).flat();
                    console.log('All dates from tasks:', dates);
                    
                    const minDate = new Date(Math.min(...dates.map(d => d.getTime())));
                    const maxDate = new Date(Math.max(...dates.map(d => d.getTime())));
                    
                    // Calculate 1 week ago from today
                    const today = new Date();
                    const oneWeekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                    
                    // Use the earlier of: 1 week ago or the earliest project date
                    const viewStart = oneWeekAgo;
                    
                    // Add some padding to the view
                    const padding = 7 * 24 * 60 * 60 * 1000; // 7 days
                    const viewEnd = new Date(maxDate.getTime() + padding);
                    
                    // Set the view range using proper DHTMLX methods
                    gantt.config.start_date = viewStart;
                    gantt.config.end_date = viewEnd;
                    gantt.render();
                    
                    // Initialize date inputs
                    this.updateDateInputs(viewStart, viewEnd);
                    
                    console.log('Gantt view range:', {
                        minDate: minDate,
                        maxDate: maxDate,
                        oneWeekAgo: oneWeekAgo,
                        viewStart: viewStart,
                        viewEnd: viewEnd
                    });
                } else {
                    // Set default range if no data
                    const today = new Date();
                    const oneWeekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000); // 1 week ago
                    const startDate = oneWeekAgo; // Start from 1 week ago
                    const endDate = new Date(today.getFullYear(), today.getMonth() + 2, 0); // End of next month
                    
                    gantt.config.min_date = startDate;
                    gantt.config.max_date = endDate;
                    gantt.render();
                    
                    // Initialize date inputs
                    this.updateDateInputs(startDate, endDate);
                }

                // Add current time marker
                gantt.addMarker({
                    start_date: new Date(),
                    css: "current_time_marker",
                    title: "現在時刻",
                    text: "現在"
                });
                
                // Update marker position every minute
                setInterval(function() {
                    gantt.updateMarker("current_time_marker");
                }, 6000);
            },
            
            convertProjectsToGanttData(projects) {
                const tasks = [];
                const links = [];
                
                projects.forEach((project, index) => {
                    // Improved date parsing
                    let startDate, endDate;
                    
                    if (project.start_date) {
                        startDate = this.parseDate(project.start_date);
                        // Check if date is valid
                        if (isNaN(startDate.getTime())) {
                            console.warn('Invalid start_date for project:', project.id, project.start_date);
                            startDate = new Date();
                        }
                    } else {
                        startDate = new Date();
                    }
                    
                    if (project.end_date) {
                        endDate = this.parseDate(project.end_date);
                        // Check if date is valid
                        if (isNaN(endDate.getTime())) {
                            console.warn('Invalid end_date for project:', project.id, project.end_date);
                            endDate = new Date(startDate.getTime() + 7 * 24 * 60 * 60 * 1000);
                        }
                    } else {
                        endDate = new Date(startDate.getTime() + 7 * 24 * 60 * 60 * 1000);
                    }
                    
                    // Ensure end date is after start date
                    if (endDate <= startDate) {
                        endDate = new Date(startDate.getTime() + 7 * 24 * 60 * 60 * 1000);
                    }
                    
                    // Debug logging
                    console.log('Project dates:', project.id, {
                        original_start: project.start_date,
                        parsed_start: startDate,
                        original_end: project.end_date,
                        parsed_end: endDate
                    });
                    
                    // Get status color
                    const status = statuses.find(s => s.key === project.status);
                    const statusColor = status ? status.color : 'secondary';
                    
                    // Get priority color
                    const priority = priorities.find(p => p.key === project.priority);
                    const priorityColor = priority ? priority.color : 'secondary';
                    
                    
                    const task = {
                        id: project.id,
                        text: `${project.project_number || 'N/A'} - ${project.name}`,
                        start_date: startDate,
                        end_date: endDate,
                        progress: project.progress / 100,
                        parent: 0,
                        priority: project.priority || 'medium',
                        status: project.status || 'draft',
                        manager: project.manager_name || 'N/A',
                        customer: project.customer_name || 'N/A',
                        company: project.company_name.replace('株式会社', '') || 'N/A',
                        building_type: project.building_type || 'N/A',
                        building_size: project.building_size || 'N/A',
                        project_order_type: project.project_order_type || 'N/A',
                        description: project.description || '',
                        statusColor: statusColor,
                        priorityColor: priorityColor
                    };
                    
                    tasks.push(task);
                });
                
                return { data: tasks, links: links };
            },
            
            parseDate(dateString) {
                if (!dateString) return new Date();
                
                console.log('Parsing date:', dateString, 'Type:', typeof dateString);
                
                // Handle different date formats more robustly
                let date = null;
                
                // Try parsing as ISO string first
                if (typeof dateString === 'string') {
                    // Handle MySQL datetime format: "2024-01-15 10:30:00"
                    if (dateString.includes(' ')) {
                        const mysqlDate = dateString.replace(' ', 'T');
                        date = new Date(mysqlDate);
                        console.log('MySQL format parsed:', mysqlDate, 'Result:', date);
                    }
                    
                    // Handle date only format: "2024-01-15"
                    if (!date || isNaN(date.getTime())) {
                        if (dateString.match(/^\d{4}-\d{2}-\d{2}$/)) {
                            date = new Date(dateString + 'T00:00:00');
                            console.log('Date only format parsed:', dateString + 'T00:00:00', 'Result:', date);
                        }
                    }
                    
                    // Handle Japanese date format: "2024年1月15日"
                    if (!date || isNaN(date.getTime())) {
                        const japaneseMatch = dateString.match(/(\d{4})年(\d{1,2})月(\d{1,2})日/);
                        if (japaneseMatch) {
                            const year = parseInt(japaneseMatch[1]);
                            const month = parseInt(japaneseMatch[2]) - 1; // Month is 0-indexed
                            const day = parseInt(japaneseMatch[3]);
                            date = new Date(year, month, day);
                            console.log('Japanese format parsed:', japaneseMatch, 'Result:', date);
                        }
                    }
                    
                    // Try standard Date constructor as fallback
                    if (!date || isNaN(date.getTime())) {
                        date = new Date(dateString);
                        console.log('Standard Date constructor result:', date);
                    }
                } else if (dateString instanceof Date) {
                    date = dateString;
                    console.log('Already a Date object:', date);
                }
                
                // Validate the parsed date
                if (!date || isNaN(date.getTime())) {
                    console.error('Unable to parse date:', dateString, 'Returning current date');
                    return new Date();
                }
                
                // Check for unreasonable dates (before 1900 or after 2100)
                const year = date.getFullYear();
                if (year < 1900 || year > 2100) {
                    console.warn('Unreasonable year detected:', year, 'for date:', dateString, 'Returning current date');
                    return new Date();
                }
                
                console.log('Final parsed date:', date, 'Year:', date.getFullYear());
                return date;
            },
            
            refreshGantt() {
                this.loadProjects();
            },
            
            // Scale Controls
            setDefaultScale() {
                if (!gantt || !this.ganttInitialized) return;
                this.currentScale = 'week';
                gantt.config.scale_unit = "week";
                gantt.config.date_scale = "%m月";
                gantt.config.subscales = [
                    { unit: "day", step: 1, date: "%d日" }
                ];
                gantt.render();
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
            
            // Zoom Controls
            zoomIn() {
                if (!gantt || !this.ganttInitialized) return;
                const currentDate = gantt.getState().min_date;
                const currentRange = gantt.getState().max_date - gantt.getState().min_date;
                const newRange = currentRange * 0.3;
                const newMinDate = new Date(currentDate.getTime() + currentRange * 0.15);
                const newMaxDate = new Date(newMinDate.getTime() + newRange);
                
                // Set new date range
                gantt.config.start_date = newMinDate;
                gantt.config.end_date = newMaxDate;
                gantt.render();
                
                // Update date inputs
                this.updateDateInputs(newMinDate, newMaxDate);
            },
            
            zoomOut() {
                if (!gantt || !this.ganttInitialized) return;
                const currentDate = gantt.getState().min_date;
                const currentRange = gantt.getState().max_date - gantt.getState().min_date;
                const newRange = currentRange * 1.4;
                const newMinDate = new Date(currentDate.getTime() - currentRange * 0.2);
                const newMaxDate = new Date(newMinDate.getTime() + newRange);
                
                // Set new date range
                gantt.config.start_date = newMinDate;
                gantt.config.end_date = newMaxDate;
                gantt.render();
                
                // Update date inputs
                this.updateDateInputs(newMinDate, newMaxDate);
            },
            
            // Date Range Controls
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
                    alert('終了日は開始日より後である必要があります。');
                    return;
                }

                // Set the date range using DHTMLX Gantt methods
                gantt.config.start_date = startDate;
                gantt.config.end_date = endDate;
                
                // Force the Gantt to recalculate and render
                gantt.render();
                
            },
            
            updateDateInputs(startDate, endDate) {
                const startDateEl = document.querySelector(".start_date");
                const endDateEl = document.querySelector(".end_date");
                
                if (startDateEl && endDateEl) {
                    startDateEl.value = this.formatDateForInput(startDate);
                    endDateEl.value = this.formatDateForInput(endDate);
                }
            },
            
            formatDateForInput(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            },
            
            // Fullscreen Toggle
            toggleFullscreen() {
                const container = document.getElementById('gantt_container');
                if (!container) return;
                
                if (!this.isFullscreen) {
                    // Enter fullscreen
                    if (container.requestFullscreen) {
                        container.requestFullscreen();
                    } else if (container.webkitRequestFullscreen) {
                        container.webkitRequestFullscreen();
                    } else if (container.msRequestFullscreen) {
                        container.msRequestFullscreen();
                    }
                } else {
                    // Exit fullscreen
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
                // if (gantt && this.ganttInitialized) {
                //     // Force Gantt to recalculate its layout
                //     setTimeout(() => {
                //         gantt.setSizes();
                //         // Maintain grid width after resize
                //         const gridElement = document.querySelector('.gantt_grid');
                //         if (gridElement) {
                //             gridElement.style.width = '400px';
                //             gridElement.style.minWidth = '400px';
                //             gridElement.style.maxWidth = '400px';
                //             gridElement.style.overflow = 'hidden';
                //         }
                        
                //         // Ensure grid data area maintains scroll width
                //         const gridDataArea = document.querySelector('.gantt_grid_data_area');
                //         if (gridDataArea) {
                //             gridDataArea.style.minWidth = '800px';
                //         }
                        
                //         const gridHead = document.querySelector('.gantt_grid_head');
                //         if (gridHead) {
                //             gridHead.style.minWidth = '800px';
                //         }
                //     }, 100);
                // }
            },
            
            handleFullscreenChange() {
                this.isFullscreen = document.fullscreenElement || document.webkitFullscreenElement || document.mozFullscreenElement || document.msFullscreenElement;
            },
            
            initGantt() {
                // Wait for container to be ready
                const container = document.getElementById('gantt_container');
                if (!container) {
                    console.error('Gantt container not found');
                    return;
                }
                
                // Check if container has proper dimensions
                if (container.offsetWidth === 0 || container.offsetHeight === 0) {
                    // Wait a bit more for Vue to finish rendering
                    setTimeout(() => {
                        this.initGantt();
                    }, 100);
                    return;
                }
                
                // Check if Gantt is already initialized
                if (this.ganttInitialized && gantt) {
                    return;
                }
                
                // // Configure Gantt
                gantt.config.date_format = "%m月%d日";
                gantt.config.scale_unit = "week";
                gantt.config.date_scale = "%m月";
                gantt.config.subscales = [
                    { unit: "day", step: 1, date: "%d日" }
                ];
                gantt.plugins({
                    tooltip: true,
                    marker: true ,
                    //quick_info: true
                });
                gantt.config.quickinfo_buttons=["icon_edit"];
                gantt.config.drag_lightbox = true;
                gantt.config.drag_timeline = {
                    ignore:".gantt_task_line, .gantt_task_link",
                    useKey: false,
                    render: false
                };
                gantt.config.lightbox.sections = [
                   // {name:"description", height:38, map_to:"text", type:"textarea",focus:true},
                    {name: "time", type: "time", map_to: "auto", time_format: ["%Y", "%m", "%d", "%H:%i"]}
                ];
                gantt.config.buttons_right = ["gantt_save_btn", "gantt_cancel_btn"];
                gantt.config.buttons_left = [];
                gantt.i18n.setLocale('jp');
                // gantt.i18n.setLocale({
                //     labels: {
                //        gantt_save_btn: "保存",
                //        gantt_cancel_btn: "キャンセル",
                //        gantt_delete_btn: "削除"
                //     }
                // });

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
                
                // // Set reasonable date range
                
                // gantt.config.start_on_monday = false;
                // gantt.config.work_time = true;
                // gantt.config.skip_off_time = true;
                
                // // Enable features
                gantt.config.drag_progress = false;
                // gantt.config.drag_resize = true;
                gantt.config.drag_move = false;
                gantt.config.drag_links = false;
                gantt.config.drag_plan = false;

                
                gantt.config.row_height = 30;
	            gantt.config.grid_resize = true;

              
                
                // // Set work time
                // gantt.config.work_time = true;
                // gantt.config.skip_off_time = true;
                
                // // Enable horizontal scroll
                // gantt.config.scroll_size = 20;
                // gantt.config.scroll_horizontal = true;
                // gantt.config.scroll_vertical = true;
                
                // // Set minimum column width to ensure scroll
                // gantt.config.min_column_width = 80;
                
                // // Set fixed grid width
                gantt.config.grid_elastic_columns = true;
                //gantt.config.grid_width = 400;

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
                }
                
                // // Customize columns
                gantt.config.columns = [
                    { name: "text", label: "プロジェクト名", width: 200, tree: true, min_width: 150 },
                    { name: "company", label: "会社", width: 120, min_width: 100 },
                    { name: "start_date", label: "開始日", width: 100, align: "left", min_width: 80, template: function(obj) {
                        if (!obj.start_date) return 'N/A';
                        const date = new Date(obj.start_date);
                        return date.getMonth() + 1 + '月' + date.getDate() + '日';
                    }},
                    { name: "end_date", label: "終了日", width: 100, align: "left", min_width: 80, template: function(obj) {
                        if (!obj.end_date) return 'N/A';
                        const date = new Date(obj.end_date);
                        return date.getMonth() + 1 + '月' + date.getDate() + '日';
                    }},
                    { name: "progress", label: "進捗", width: 80, align: "left", min_width: 60, template: function(obj) {
                        return Math.round(obj.progress * 100) + "%";
                    }},
                    { name: "status", label: "状況", width: 100, align: "left", min_width: 80, template: function(obj) {
                        const status = statuses.find(s => s.key === obj.status);
                        return status ? status.name : obj.status;
                    }},
                    { name: "priority", label: "優先度", width: 100, align: "left", min_width: 80, template: function(obj) {
                        const priority = priorities.find(p => p.key === obj.priority);
                        return priority ? priority.name : obj.priority;
                    }}
                ];
                
                // // Customize task appearance
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
                
                // // Customize task text
                // gantt.templates.task_text = function(start, end, task) {
                //     return task.text;
                // };
                
                // // Customize tooltip
                // gantt.templates.tooltip_text = function(start, end, task) {
                //     return `
                //         <div class="gantt-tooltip">
                //             <h6>${task.text}</h6>
                //             <p><strong>担当者:</strong> ${task.manager}</p>
                //             <p><strong>顧客:</strong> ${task.customer}</p>
                //             <p><strong>会社:</strong> ${task.company}</p>
                //             <p><strong>建物種類:</strong> ${task.building_type}</p>
                //             <p><strong>建物規模:</strong> ${task.building_size}</p>
                //             <p><strong>受注形態:</strong> ${task.project_order_type}</p>
                //             <p><strong>進捗:</strong> ${Math.round(task.progress * 100)}%</p>
                //             <p><strong>開始日:</strong> ${gantt.templates.tooltip_date_format(start)}</p>
                //             <p><strong>終了日:</strong> ${gantt.templates.tooltip_date_format(end)}</p>
                //             ${task.description ? `<p><strong>メモ:</strong> ${task.description}</p>` : ''}
                //         </div>
                //     `;
                // };
                
                // gantt.templates.tooltip_date_format = function(date) {
                //     return gantt.date.date_to_str("%Y年%m月%d日")(date);
                // };
                
                // // Add click event to open project detail
                // gantt.attachEvent("onTaskClick", function(id, e) {
                //     window.open(`detail.php?id=${id}`, '_blank');
                //     return false;
                // });
                
                // // Sync horizontal scroll between grid header and data
                // gantt.attachEvent("onGanttReady", function() {
                //     const gridData = document.querySelector('.gantt_grid_data');
                //     const gridScale = document.querySelector('.gantt_grid_scale');
                    
                //     if (gridData && gridScale) {
                //         gridData.addEventListener('scroll', function() {
                //             gridScale.scrollLeft = this.scrollLeft;
                //         });
                        
                //         gridScale.addEventListener('scroll', function() {
                //             gridData.scrollLeft = this.scrollLeft;
                //         });
                //     }
                // });
                
                try {
                    // // Add custom CSS first
                   this.addGanttStyles();
                    
                    // // Initialize Gantt
                    gantt.init("gantt_container");
                    this.ganttInitialized = true;
                    
                    // Load initial data if department is selected
                    if (this.selectedDepartment) {
                        this.loadProjects();
                    }

                   
                } catch (error) {
                    console.error('Error initializing Gantt:', error);
                    this.ganttInitialized = false;
                }
            },
            
            addGanttStyles() {
                const style = document.createElement('style');
                style.textContent = `
                    /* Ensure Gantt container has proper dimensions */
                    #gantt_container {
                        width: 100% !important;
                        height: 600px !important;
                        min-height: 600px !important;
                        position: relative !important;
                        overflow: hidden !important;
                    }
                    .weekend {
                        background: var(--dhx-gantt-base-colors-background-alt);
                    }
                  
                    .gantt_task_content{
                        text-align: left !important;
                        padding-left: 10px !important;
                    }
                    
                    /* Current time marker */
                    .current_time_marker {
                        background-color: #ff0000;
                        width: 2px;
                        opacity: 0.8;
                        z-index: 10;
                    }
                    
                    /* Status colors for task bars */
                    .gantt-status-info .gantt_task_content { 
                        background-color: #17a2b8; 
                        color: white;
                    }
                    .gantt-status-warning .gantt_task_content { 
                        background-color: #ffc107; 
                        color: #212529;
                    }
                    .gantt-status-primary .gantt_task_content { 
                        background-color: #007bff; 
                        color: white;
                    }
                    .gantt-status-success .gantt_task_content { 
                        background-color: #28a745; 
                        color: white;
                    }
                    .gantt-status-danger .gantt_task_content { 
                        background-color: #dc3545; 
                        color: white;
                    }
                    .gantt-status-secondary .gantt_task_content { 
                        background-color: #6c757d; 
                        color: white;
                    }
                    
                    /* Priority border colors */
                    .gantt-priority-danger .gantt_task_line { 
                        border-color: #dc3545; 
                        border-width: 3px; 
                    }
                    .gantt-priority-warning .gantt_task_line { 
                        border-color: #ffc107; 
                        border-width: 3px; 
                    }
                    .gantt-priority-primary .gantt_task_line { 
                        border-color: #007bff; 
                        border-width: 2px; 
                    }
                    .gantt-priority-secondary .gantt_task_line { 
                        border-color: #6c757d; 
                        border-width: 2px; 
                    }
                    
                    /* Overdue projects */
                    .gantt-overdue .gantt_task_line { 
                        border-color: #dc3545; 
                        border-width: 3px; 
                        border-style: dashed; 
                    }
                    .gantt-overdue .gantt_task_content {
                        background-color: #dc3545 !important;
                        color: white;
                    }
                    
                   
                    
                    /* Loading indicator */
                    .gantt-loading {
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        height: 200px;
                        color: #6c757d;
                        font-size: 14px;
                    }
                    
                    
                  
                `;
                document.head.appendChild(style);
            }
        }
    });
    
    app.mount('#app');
});

function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(n => n.charAt(0)).join('').toUpperCase().substring(0, 2);
}

function decodeHtmlEntities(str) {
    if (typeof str !== 'string') return str;
    const textarea = document.createElement('textarea');
    textarea.innerHTML = str;
    return textarea.value;
} 