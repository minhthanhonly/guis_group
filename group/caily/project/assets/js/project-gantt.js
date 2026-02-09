var gantt;
var projectData = [];
var statuses = [
    {
        key: 'all',
        name: 'すべて',
        color: 'secondary'
    },
    {
        key: 'draft',
        name: '受付',
        color: 'secondary'
    },
    {
        key: 'open',
        name: '納期検討',
        color: 'info'
    },
    {
        key: 'confirming',
        name: '仮受',
        color: 'info'
    },
    {
        key: 'quotation',
        name: '見積',
        color: 'info'
    },
    {
        key: 'contract',
        name: '請負',
        color: 'info'
    },
    {
        key: 'in_progress',
        name: '進行中',
        color: 'primary'
    },
    {
        key: 'completed',
        name: '納品',
        color: 'success'
    },
    {
        key: 'paused',
        name: '一時停止',
        color: 'warning'
    },
    {
        key: 'cancelled',
        name: '中止',
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
    // --- Filter UI logic giống project-list.js ---
    // Render option cho filterPriority dựa trên priorities
    var $priority = $('#filterPriority');
    $priority.empty();
    $priority.append('<option value="">すべて</option>');
    priorities.forEach(function(p) {
        $priority.append('<option value="' + p.key + '">' + p.name + '</option>');
    });

    // LocalStorage filter state
    const FILTER_STORAGE_KEY = 'projectGanttFilters';
    const SELECTED_DEPARTMENT_KEY = 'projectListSelectedDepartment'; // Dùng chung với project-list.js
    function saveFiltersToLocalStorage() {
        const filters = {
            filterPriority: $('#filterPriority').val(),
            filterProgress: $('#filterProgress').val(),
            filterTimeLeft: $('#filterTimeLeft').val(),
            filterProjectOrderType: $('#filterProjectOrderType').val(),
            filterTeam: $('#filterTeam').val(),
            filterTantou: $('#filterTantou').val(),
            filterNoDates: $('#filterNoDates').is(':checked') ? 1 : 0,
            showInactive: $('#showInactiveSwitch').is(':checked') ? 1 : 0,
            myProjects: $('#filterMyProjects').is(':checked') ? 1 : 0,
            showTaskText: $('#toggleTaskText').is(':checked') ? 1 : 0,
            useCailyEndDate: $('#useCailyEndDate').is(':checked') ? 1 : 0,
            useGuisEndDate: $('#useGuisEndDate').is(':checked') ? 1 : 0,
            filterKeyword: $('#filterKeyword').val(),
        };
        // Lưu thêm trạng thái status đang chọn (header buttons)
        try {
            if (window.ganttApp && window.ganttApp.selectedStatus && window.ganttApp.selectedStatus.key) {
                filters.statusKey = window.ganttApp.selectedStatus.key;
            } else {
                // 'all' hoặc chưa chọn gì -> lưu rỗng
                filters.statusKey = '';
            }
        } catch (e) {
            console.warn('Failed to read selectedStatus when saving filters', e);
        }
        // Lưu thêm department hiện tại để chia sẻ qua URL
        try {
            if (window.ganttApp && window.ganttApp.selectedDepartment && window.ganttApp.selectedDepartment.id) {
                filters.department_id = window.ganttApp.selectedDepartment.id;
            }
        } catch (e) {
            console.warn('Failed to read selectedDepartment when saving filters', e);
        }
        localStorage.setItem(FILTER_STORAGE_KEY, JSON.stringify(filters));
        // Cập nhật query string trên URL để có thể share link
        updateUrlFromFilters(filters);
    }
    function loadFiltersFromLocalStorage() {
        let filters = {};
        try {
            filters = JSON.parse(localStorage.getItem(FILTER_STORAGE_KEY) || '{}');
        } catch (e) {
            console.warn('Failed to parse Gantt filter state, clearing it.', e);
            localStorage.removeItem(FILTER_STORAGE_KEY);
            filters = {};
        }
        if (filters.filterPriority !== undefined) $('#filterPriority').val(filters.filterPriority);
        if (filters.filterProgress !== undefined) $('#filterProgress').val(filters.filterProgress);
        if (filters.filterTimeLeft !== undefined) $('#filterTimeLeft').val(filters.filterTimeLeft);
        if (filters.filterProjectOrderType !== undefined) $('#filterProjectOrderType').val(filters.filterProjectOrderType);
        if (filters.filterTeam !== undefined) $('#filterTeam').val(filters.filterTeam);
        if (filters.filterTantou !== undefined) $('#filterTantou').val(filters.filterTantou);
        if (filters.filterNoDates !== undefined) $('#filterNoDates').prop('checked', filters.filterNoDates == 1);
        if (filters.showInactive !== undefined) $('#showInactiveSwitch').prop('checked', filters.showInactive == 1);
        if (filters.myProjects !== undefined) $('#filterMyProjects').prop('checked', filters.myProjects == 1);
        if (filters.showTaskText !== undefined) $('#toggleTaskText').prop('checked', filters.showTaskText == 1);
        if (filters.useCailyEndDate !== undefined) $('#useCailyEndDate').prop('checked', filters.useCailyEndDate == 1);
        if (filters.useGuisEndDate !== undefined) $('#useGuisEndDate').prop('checked', filters.useGuisEndDate == 1);
        if (filters.filterKeyword !== undefined) $('#filterKeyword').val(filters.filterKeyword);
    }

    function getFiltersFromLocalStorage() {
        let filters = {};
        try {
            filters = JSON.parse(localStorage.getItem(FILTER_STORAGE_KEY) || '{}');
        } catch (e) {
            console.warn('Failed to parse Gantt filter state, clearing it.', e);
            localStorage.removeItem(FILTER_STORAGE_KEY);
            filters = {};
        }
        return {
            priority: filters.filterPriority || '',
            progress: filters.filterProgress || '',
            timeLeft: filters.filterTimeLeft || '',
            projectOrderType: filters.filterProjectOrderType || '',
            team: filters.filterTeam || '',
            tantou: filters.filterTantou || '',
            noDates: filters.filterNoDates == 1,
            keyword: filters.filterKeyword || '',
            showInactive: filters.showInactive == 1,
            myProjects: filters.myProjects == 1,
            showTaskText: filters.showTaskText == 1,
            statusKey: filters.statusKey || '',
            department_id: filters.department_id || null
        };
    }

    // Đồng bộ filters -> query string trên URL
    function updateUrlFromFilters(filters) {
        if (typeof window === 'undefined' || !window.history || !window.history.replaceState) {
            return;
        }
        const params = new URLSearchParams(window.location.search || '');
        function setOrDelete(key, val) {
            if (val === undefined || val === null || val === '' || val === 0) {
                params.delete(key);
            } else {
                params.set(key, String(val));
            }
        }
        setOrDelete('filterPriority', filters.filterPriority);
        setOrDelete('filterProgress', filters.filterProgress);
        setOrDelete('filterTimeLeft', filters.filterTimeLeft);
        setOrDelete('filterProjectOrderType', filters.filterProjectOrderType);
        setOrDelete('filterTeam', filters.filterTeam);
        setOrDelete('filterTantou', filters.filterTantou);
        setOrDelete('filterNoDates', filters.filterNoDates ? 1 : '');
        setOrDelete('showInactive', filters.showInactive ? 1 : '');
        setOrDelete('my_projects', filters.myProjects ? 1 : '');
        setOrDelete('filterKeyword', filters.filterKeyword);
        setOrDelete('status', filters.statusKey);
        setOrDelete('showTaskText', filters.showTaskText ? 1 : '');
        setOrDelete('useCailyEndDate', filters.useCailyEndDate ? 1 : '');
        setOrDelete('useGuisEndDate', filters.useGuisEndDate ? 1 : '');
        setOrDelete('department_id', filters.department_id);

        const baseUrl = window.location.protocol + '//' + window.location.host + window.location.pathname;
        const query = params.toString();
        const newUrl = query ? `${baseUrl}?${query}` : baseUrl;
        window.history.replaceState(null, '', newUrl);
    }

    function renderActiveFilters() {
        const filters = getFiltersFromLocalStorage();
        const badges = [];
        // Lấy status hiện tại từ Vue (ganttApp)
        let statusLabel = '';
        if (window.ganttApp && window.ganttApp.selectedStatus) {
            statusLabel = window.ganttApp.selectedStatus.name || '';
        }
        // Nếu tất cả filter đều rỗng, không hiển thị gì
        if (
            (!statusLabel || statusLabel.trim() === '') &&
            (!filters.priority || filters.priority.trim() === '') &&
            (!filters.progress || filters.progress.trim() === '') &&
            (!filters.timeLeft || filters.timeLeft.trim() === '') &&
            (!filters.projectOrderType || filters.projectOrderType.trim() === '') &&
            (!filters.team || filters.team.trim() === '') &&
            (!filters.tantou || filters.tantou.trim() === '') &&
            !filters.noDates &&
            !filters.showInactive &&
            !filters.myProjects &&
            (!filters.keyword || filters.keyword.trim() === '')
        ) {
            $('#activeFilters').html('');
            return;
        }

        if (filters.keyword && filters.keyword.trim() !== '') {
            badges.push(`<span class="badge bg-label-info me-1">キーワード: ${filters.keyword}</span>`);
        } else {
            if (statusLabel && statusLabel.trim() !== '') {
                badges.push(`<span class="badge bg-label-info me-1">案件状況: ${statusLabel}</span>`);
            }
            if (filters.priority && filters.priority.trim() !== '') {
                const label = (window.priorities || []).find(p => p.key === filters.priority)?.name || filters.priority;
                badges.push(`<span class="badge bg-label-info me-1">優先度: ${label}</span>`);
            }
            if (filters.projectOrderType && filters.projectOrderType.trim() !== '') {
                let label = '';
                switch (filters.projectOrderType) {
                    case 'contract': label = '契約図'; break;
                    case 'new':      label = '新規';   break;
                    case 'edit':     label = '修正';   break;
                    case 'other':    label = 'その他'; break;
                    default:         label = filters.projectOrderType;
                }
                badges.push(`<span class="badge bg-label-info me-1">受注形態: ${label}</span>`);
            }
            if (filters.team && filters.team.trim() !== '') {
                // teamIdToName không được dùng trực tiếp trong Gantt; fallback hiển thị id
                const teamName = filters.team;
                badges.push(`<span class="badge bg-label-info me-1">チーム: ${teamName}</span>`);
            }
            if (filters.tantou && filters.tantou.trim() !== '') {
                badges.push(`<span class="badge bg-label-info me-1">担当: ${filters.tantou}</span>`);
            }
            if (filters.progress && filters.progress.trim() !== '') {
                let label = '';
                if (filters.progress === '0-50') label = '0-50%';
                else if (filters.progress === '51-99') label = '51-99%';
                else if (filters.progress === '100') label = '100%';
                else label = filters.progress;
                badges.push(`<span class="badge bg-label-info me-1">進捗率: ${label}</span>`);
            }
            if (filters.timeLeft && filters.timeLeft.trim() !== '') {
                let label = '';
                if (filters.timeLeft === '7') label = '7日以内';
                else if (filters.timeLeft === '30') label = '30日以内';
                else if (filters.timeLeft === 'overdue') label = '期限切れ';
                else label = filters.timeLeft;
                badges.push(`<span class="badge bg-label-info me-1">残り時間: ${label}</span>`);
            }
            if (filters.noDates) {
                badges.push(`<span class="badge bg-label-info me-1">開始日・終了日未設定</span>`);
            }
            if (filters.myProjects) {
                badges.push(`<span class="badge bg-label-info me-1">私の案件</span>`);
            }
            if (filters.showInactive) {
                badges.push(`<span class="badge bg-label-info me-1">完了・中止案件等も表示</span>`);
            }
        }

        $('#activeFilters').html(`<span class="me-2 text-muted small">適用中のフィルター:</span>` + badges.join(''));
    }

    // Nếu URL có query param filter thì ưu tiên áp dụng nó và ghi vào localStorage,
    // giúp có thể share link cho user khác và vẫn giữ nguyên trạng thái filter.
    function applyFiltersFromUrlIfAny() {
        if (typeof window === 'undefined') return;
        const search = window.location.search || '';
        if (!search || search.length <= 1) return;
        const params = new URLSearchParams(search);
        if (Array.from(params.keys()).length === 0) return;

        let stored = {};
        try {
            stored = JSON.parse(localStorage.getItem(FILTER_STORAGE_KEY) || '{}');
        } catch (e) {
            stored = {};
        }
        const merged = Object.assign({}, stored);

        function getBool(name) {
            return params.get(name) === '1' ? 1 : 0;
        }

        if (params.has('filterPriority')) merged.filterPriority = params.get('filterPriority') || '';
        if (params.has('filterProgress')) merged.filterProgress = params.get('filterProgress') || '';
        if (params.has('filterTimeLeft')) merged.filterTimeLeft = params.get('filterTimeLeft') || '';
        if (params.has('filterProjectOrderType')) merged.filterProjectOrderType = params.get('filterProjectOrderType') || '';
        if (params.has('filterTeam')) merged.filterTeam = params.get('filterTeam') || '';
        if (params.has('filterTantou')) merged.filterTantou = params.get('filterTantou') || '';
        if (params.has('filterNoDates')) merged.filterNoDates = getBool('filterNoDates');
        if (params.has('showInactive')) merged.showInactive = getBool('showInactive');
        if (params.has('my_projects')) merged.myProjects = getBool('my_projects');
        if (params.has('filterKeyword')) merged.filterKeyword = params.get('filterKeyword') || '';
        if (params.has('status')) merged.statusKey = params.get('status') || '';
        if (params.has('showTaskText')) merged.showTaskText = getBool('showTaskText');
        if (params.has('useCailyEndDate')) merged.useCailyEndDate = getBool('useCailyEndDate');
        if (params.has('useGuisEndDate')) merged.useGuisEndDate = getBool('useGuisEndDate');

        // Department id cho Gantt – giúp auto chọn đúng 部署 khi mở link
        if (params.has('department_id')) {
            const depId = parseInt(params.get('department_id'), 10);
            if (!isNaN(depId) && depId > 0) {
                merged.department_id = depId;
                // Đồng bộ với key dùng chung với project-list
                try {
                    localStorage.setItem(SELECTED_DEPARTMENT_KEY, JSON.stringify({ id: depId }));
                } catch (e) {
                    console.warn('Failed to save department from URL into localStorage', e);
                }
            }
        }

        localStorage.setItem(FILTER_STORAGE_KEY, JSON.stringify(merged));
    }

    // Ưu tiên áp dụng filter từ URL (nếu có) trước khi load từ localStorage
    applyFiltersFromUrlIfAny();
    loadFiltersFromLocalStorage();

    // Trạng thái hiển thị task text trên bar (task.text)
    window.ganttShowTaskText = $('#toggleTaskText').is(':checked');

    // Chế độ tính end_date cho task: default / caily / guis
    (function initEndDateMode() {
        let cChecked = $('#useCailyEndDate').is(':checked');
        let gChecked = $('#useGuisEndDate').is(':checked');
        // Đảm bảo chỉ một checkbox được chọn cùng lúc
        if (cChecked && gChecked) {
            // Ưu tiên CAILY, tắt GUIS
            $('#useGuisEndDate').prop('checked', false);
            gChecked = false;
        }
        if (cChecked) {
            window.ganttEndDateMode = 'caily';
        } else if (gChecked) {
            window.ganttEndDateMode = 'guis';
        } else {
            window.ganttEndDateMode = 'default';
        }
    })();

    renderActiveFilters();
    // Dùng event delegation để đảm bảo binding kể cả khi DOM thay đổi
    $(document).on('change keyup', '#projectFilterForm select, #projectFilterForm input', function() {
        console.log('filter changed');
        saveFiltersToLocalStorage();
        renderActiveFilters();
        if (window.ganttApp && typeof window.ganttApp.loadProjects === 'function') {
            window.ganttApp.loadProjects();
        }
    });
    // Riêng checkbox タスク名を表示 nằm ngoài form, bind trực tiếp
    $(document).on('change', '#toggleTaskText', function() {
        console.log('toggleTaskText changed');
        saveFiltersToLocalStorage();
        window.ganttShowTaskText = $('#toggleTaskText').is(':checked');
        if (gantt) gantt.render();
        renderActiveFilters();
    });
    // Checkbox hiển thị end_date theo CAILY/GUIS納期 (chỉ 1 trong 2 được chọn)
    $(document).on('change', '#useCailyEndDate, #useGuisEndDate', function() {
        console.log('endDateMode checkbox changed', this.id);
        // Mutual exclusive
        if (this.id === 'useCailyEndDate' && $('#useCailyEndDate').is(':checked')) {
            $('#useGuisEndDate').prop('checked', false);
        } else if (this.id === 'useGuisEndDate' && $('#useGuisEndDate').is(':checked')) {
            $('#useCailyEndDate').prop('checked', false);
        }
        const cChecked = $('#useCailyEndDate').is(':checked');
        const gChecked = $('#useGuisEndDate').is(':checked');
        if (cChecked) {
            window.ganttEndDateMode = 'caily';
        } else if (gChecked) {
            window.ganttEndDateMode = 'guis';
        } else {
            window.ganttEndDateMode = 'default';
        }
        saveFiltersToLocalStorage();
        // Cần load lại projects để tính lại end_date cho task
        if (window.ganttApp && typeof window.ganttApp.loadProjects === 'function') {
            window.ganttApp.loadProjects();
        } else if (gantt) {
            gantt.render();
        }
    });
    $(document).on('click', '#filterReset', function() {
        console.log('filterReset');
        localStorage.removeItem(FILTER_STORAGE_KEY);
        const form = document.getElementById('projectFilterForm');
        if (form) form.reset();
        // Đảm bảo Team filter về giá trị mặc định: rỗng (= すべて)
        $('#filterTeam').val('');
        // Reset trạng thái status filter về "all"
        if (window.ganttApp) {
            window.ganttApp.selectedStatus = null;
        }
        // Lưu lại trạng thái trống mới và cập nhật badge
        if (typeof saveFiltersToLocalStorage === 'function') {
            saveFiltersToLocalStorage();
        }
        renderActiveFilters();
        if (window.ganttApp && typeof window.ganttApp.loadProjects === 'function') {
            window.ganttApp.loadProjects();
        }
    });

    // Initialize Vue app first
    const app = Vue.createApp({
        data() {
            return {
                departments: [],
                selectedDepartment: null,
                selectedStatus: null,
                teams: [],
                selectedTeam: null,
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
            // Initialize Gantt after Vue has rendered
            this.$nextTick(() => {
                this.initGantt();
            });
            
            // Handle window resize
            window.addEventListener('resize', this.handleResize);
            
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
            
        },
        methods: {
            async updateProjectDate(task) {
                try {
                    const formData = new FormData();
                    formData.append('id', task.id);
                    formData.append('start_date', this.formatDateTimeForAPI(task.start_date));
                    formData.append('end_date', this.formatDateTimeForAPI(task.end_date));
                    const response = await axios.post('/api/index.php?model=project&method=updateProjectDate', formData);
                    if (response.data && response.data.success !== false) {
                        showMessage('プロジェクトの日付更新に完了しました。');
                    } else {
                        showMessage('プロジェクトの日付更新に失敗しました。', true);
                    }
                } catch (error) {
                    console.error('Error updating project date:', error);
                    showMessage('プロジェクトの日付更新に失敗しました。', true);
                }
            },

            // Department localStorage methods
            saveSelectedDepartmentToLocalStorage(department) {
                if (department && department.id) {
                    localStorage.setItem(SELECTED_DEPARTMENT_KEY, JSON.stringify({
                        id: department.id,
                        name: department.name,
                        can_project: department.can_project
                    }));
                }
            },
            
            loadSelectedDepartmentFromLocalStorage() {
                try {
                    const savedDepartment = localStorage.getItem(SELECTED_DEPARTMENT_KEY);
                    if (savedDepartment) {
                        return JSON.parse(savedDepartment);
                    }
                } catch (error) {
                    console.error('Error loading selected department from localStorage:', error);
                }
                return null;
            },
            
            async loadDepartments() {
                try {
                    const response = await $.ajax({
                        url: '/api/index.php',
                        type: 'GET',
                        data: {
                            model: 'department',
                            method: 'listByUser'
                        }
                    });
                    
                    if (Array.isArray(response) && response.length > 0) {
                        this.departments = response || [];
                        // Try to restore saved department from localStorage
                        if (!this.selectedDepartment && this.departments.length > 0) {
                            const savedDepartment = this.loadSelectedDepartmentFromLocalStorage();
                            if (savedDepartment) {
                                // Check if saved department still exists and user has access
                                const department = this.departments.find(d => d && d.id == savedDepartment.id && d.can_project == 1);
                                if (department) {
                                    this.viewProjects(department);
                                    return;
                                }
                            }
                            
                            // If no saved department or it's no longer accessible, use first available
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
                            method: 'get_user_permission_by_department',
                            department_id: this.selectedDepartment.id
                        }
                    });
                    if (Object.keys(response).length > 0) {
                        this.userPermissions = response || {};
                    }
                } catch (error) {
                    console.error('Error loading user permissions:', error);
                }
            },

            async loadTeams() {
                if (!this.selectedDepartment) {
                    this.teams = [];
                    return;
                }
                
                try {
                    const response = await $.ajax({
                        url: '/api/index.php',
                        type: 'GET',
                        data: {
                            model: 'team',
                            method: 'listbydepartment',
                            department_id: this.selectedDepartment.id
                        }
                    });
                    this.teams = response || [];
                    this.selectedTeam = null; // Reset team selection when department changes
                    console.log('Loaded teams for department', this.selectedDepartment.id, ':', this.teams);

                    // Populate team filter options (#filterTeam) giống project-list (chỉ team của department hiện tại)
                    const $teamFilter = $('#filterTeam');
                    if ($teamFilter && $teamFilter.length) {
                        const saved = JSON.parse(localStorage.getItem(FILTER_STORAGE_KEY) || '{}');
                        const savedTeam = saved.filterTeam || '';
                        $teamFilter.empty();
                        $teamFilter.append('<option value=\"\">すべて</option>');
                        // Option: 未割り当て (chưa phân công team)
                        const noneSelected = savedTeam && savedTeam === 'none' ? ' selected' : '';
                        $teamFilter.append('<option value=\"none\"' + noneSelected + '>未割り当て</option>');
                        (this.teams || []).forEach(team => {
                            if (team.id != null) {
                                const id = String(team.id);
                                const name = team.name || id;
                                const selected = savedTeam && String(savedTeam) === id ? ' selected' : '';
                                $teamFilter.append('<option value=\"' + id + '\"' + selected + '>' + name + '</option>');
                            }
                        });
                    }
                } catch (error) {
                    console.error('Error loading teams:', error);
                    this.teams = [];
                }
            },
            
            hasPermission(permission) {
                return this.userPermissions[permission] == 1 || false;
            },
            
            canAddProject() {
                return this.hasPermission('project_add') && this.selectedDepartment;
            },
            
            canEditProject() {
                if(USER_ROLE == 'administrator') return true;
                return this.hasPermission('project_edit');
            },
            
            canDeleteProject() {
                if(USER_ROLE == 'administrator') return true;
                return this.hasPermission('project_delete');
            },
            
            canManageProject() {
                if(USER_ROLE == 'administrator') return true;
                return this.hasPermission('project_manager');
            },
            
            canCommentProject() {
                return this.hasPermission('project_comment');
            },
            
            async viewProjects(department) {
                this.selectedDepartment = department;
                this.selectedStatus = null;
                
                // Save selected department to localStorage
                this.saveSelectedDepartmentToLocalStorage(department);
                
                // Load teams for the selected department
                await this.loadTeams();
                
                this.loadProjects();
            },
            
            filterProjectByStatus(status) {
                this.selectedStatus = status.key === 'all' ? null : status;
                this.loadProjects();
                // Lưu lại vào localStorage & cập nhật badge 適用中のフィルター khi đổi status
                if (typeof saveFiltersToLocalStorage === 'function') {
                    saveFiltersToLocalStorage();
                }
                renderActiveFilters();
            },

            filterProjectByTeam(team) {
                this.selectedTeam = team;
                this.loadProjects();
            },
            
            async loadProjects() {
                if (!this.selectedDepartment) return;
                await this.loadUserPermissions();
                this.loading = true;
                try {
                    // Lấy filter từ form (高度なフィルター)
                    const filterPriority = $('#filterPriority').val();
                    const filterProgress = $('#filterProgress').val();
                    const filterTimeLeft = $('#filterTimeLeft').val();
                    const filterProjectOrderType = $('#filterProjectOrderType').val();
                    const filterTeam = $('#filterTeam').val();
                    const filterTantou = $('#filterTantou').val();
                    const filterNoDates = $('#filterNoDates').is(':checked') ? 1 : 0;
                    const showInactive = $('#showInactiveSwitch').is(':checked') ? 1 : 0;
                    const myProjects = $('#filterMyProjects').is(':checked') ? 1 : 0;
                    const filterKeyword = $('#filterKeyword').val();
                    const params = {
                        model: 'project',
                        method: 'listForGantt',
                        department_id: this.selectedDepartment.id,
                        status: this.selectedStatus?.key || 'all',
                        team_id: this.selectedTeam?.id || '',
                        filterPriority,
                        filterProgress,
                        filterTimeLeft,
                        filterProjectOrderType,
                        filterTeam,
                        filterTantou,
                        filterNoDates,
                        showInactive,
                        my_projects: myProjects,
                        filterKeyword
                    };
                    const response = await $.ajax({
                        url: '/api/index.php',
                        type: 'GET',
                        data: params
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

                    // Ghi nhớ endDate gốc đề phòng không có CAILY/GUIS納期 hợp lệ
                    let finalEndDate = endDate;
                    const mode = window.ganttEndDateMode || 'default';
                    try {
                        if (mode === 'caily' && project.caily_nouki) {
                            let rawC = project.caily_nouki;
                            let cDate = null;
                            if (typeof rawC === 'string' && rawC.match(/^\d{4}-\d{2}-\d{2}$/)) {
                                // Nếu chỉ có ngày, mặc định giờ là 19:00
                                cDate = this.parseDate(rawC + ' 19:00:00');
                            } else {
                                cDate = this.parseDate(rawC);
                            }
                            if (!isNaN(cDate.getTime())) {
                                finalEndDate = cDate;
                            }
                        } else if (mode === 'guis' && project.guis_nouki) {
                            let rawG = project.guis_nouki;
                            let gDate = null;
                            if (typeof rawG === 'string' && rawG.match(/^\d{4}-\d{2}-\d{2}$/)) {
                                // Nếu chỉ có ngày, mặc định giờ là 19:00
                                gDate = this.parseDate(rawG + ' 19:00:00');
                            } else {
                                gDate = this.parseDate(rawG);
                            }
                            if (!isNaN(gDate.getTime())) {
                                finalEndDate = gDate;
                            }
                        }
                    } catch (e) {
                        console.warn('Error applying end date mode for project', project.id, e);
                    }

                    endDate = finalEndDate;
                    
                    // Ensure end date is after start date
                    if (endDate <= startDate) {
                        endDate = new Date(startDate.getTime() + 7 * 24 * 60 * 60 * 1000);
                    }
                    
                    
                    // Get status color
                    const status = statuses.find(s => s.key === project.status);
                    const statusColor = status ? status.color : 'secondary';
                    
                    // Get priority color
                    const priority = priorities.find(p => p.key === project.priority);
                    const priorityColor = priority ? priority.color : 'secondary';
                    const manager_ids = [];
                    const manager_names = [];
                    project.manager_id.split('|').forEach(item => {
                        const id = item.split(':')[0];
                        manager_ids.push(id || 'N/A');
                        const name = item.split(':')[1];
                        manager_names.push(name || 'N/A');
                    });
                    
                    // Build team name from first team id in project.teams (if any)
                    let teamName = '';
                    if (project.teams && typeof project.teams === 'string') {
                        const teamIds = project.teams.split(',').map(t => t.trim()).filter(t => t);
                        if (teamIds.length > 0 && Array.isArray(this.teams) && this.teams.length > 0) {
                            const firstId = teamIds[0];
                            const foundTeam = this.teams.find(t => String(t.id) === String(firstId));
                            if (foundTeam && foundTeam.name) {
                                teamName = foundTeam.name;
                            }
                        }
                    }

                    // 期間未定: thiếu start_date, hoặc (thiếu end_date và không có caily_nouki/guis_nouki)
                    const hasNouki = !!(project.caily_nouki && String(project.caily_nouki).trim() && project.caily_nouki !== '-') ||
                        !!(project.guis_nouki && String(project.guis_nouki).trim() && project.guis_nouki !== '-');
                    const periodUndecided = !project.start_date || (!project.end_date && !hasNouki);
                    const task = {
                        id: project.id,
                        text: (project.name || '') + (periodUndecided ? ' 期間未定' : ''),
                        start_date: startDate,
                        end_date: endDate,
                        progress: project.progress / 100,
                        parent: 0,
                        priority: project.priority || 'medium',
                        status: project.status || 'draft',
                        manager: manager_names.join(', ') || '-',
                        company: project.company_name || '-',
                        customer: project.customer_name || '-',
                        branch_name: project.branch_name || '-',
                        construction_number: project.construction_number || '-',
                        building_type: (project.building_type && project.building_type.trim()) ? project.building_type.trim() : '-',
                        building_size: (project.building_size && project.building_size.trim()) ? project.building_size.trim() : '-',
                        // Fields for task bar text
                        team_name: teamName,
                        project_order_type: project.project_order_type || '',
                        project_number: project.project_number || '',
                        project_name: project.name || '',
                        period_undecided: periodUndecided,
                        // Lưu end_date gốc từ project để hiển thị trong tooltip
                        project_end_date: project.end_date || '',
                        description: project.description || '',
                        statusColor: statusColor,
                        priorityColor: priorityColor,
                        isManager: manager_ids.includes(USER_AUTH_ID) || this.canManageProject(),
                        manager_ids: manager_ids,
                        tantou: project.tantou || '-',
                        caily_nouki: project.caily_nouki || '-',
                        guis_nouki: project.guis_nouki || '-'
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
                gantt.config.scales = [
                    { unit: "week", step: 1, format: "%m月" },
                    { unit: "day", step: 1, format: "%d日" }
                ];
                gantt.render();
            },
            
            setMonthScale() {
                if (!gantt || !this.ganttInitialized) return;
                this.currentScale = 'month';
                gantt.config.scales = [
                    { unit: "month", step: 1, format: "%m月" },
                    { unit: "week", step: 1, format: "%d日" }
                ];
                gantt.render();
            },
            
            setWeekScale() {
                if (!gantt || !this.ganttInitialized) return;
                this.currentScale = 'week';
                gantt.config.scales = [
                    { unit: "week", step: 1, format: "%m月" },
                    { unit: "day", step: 1, format: "%d日" }
                ];
                gantt.render();
            },
            
            setDayScale() {
                if (!gantt || !this.ganttInitialized) return;
                this.currentScale = 'day';
                gantt.config.scales = [
                    { unit: "day", step: 1, format: "%m月%d日" },
                    { unit: "hour", step: 1, format: "%H:%i" }
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

           
            formatDateTimeForAPI(datetime) {
                return moment(datetime).format('YYYY-MM-DD HH:mm');
            },
            
            
            // Fullscreen Toggle
            toggleFullscreen() {
                if(gantt && this.ganttInitialized){
                    gantt.ext.fullscreen.toggle();
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
                
                // Store reference to Vue component for use in Gantt events
                const vueComponent = this;
                
                // // Configure Gantt
                gantt.config.date_format = "%m月%d日";
                gantt.config.scales = [
                    { unit: "week", step: 1, format: "%m月" },
                    { unit: "day", step: 1, format: "%d日" }
                ];
                gantt.plugins({
                    tooltip: true,
                    marker: true,
                    fullscreen: true,
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
                gantt.config.buttons_left = ["open_project_btn"];
                gantt.i18n.setLocale('jp');
                gantt.i18n.setLocale({
                    labels: {
                        open_project_btn: "プロジェクト詳細",
                    }
                });

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

                gantt.attachEvent("onLightboxButton", function(button_id, node, e){
                    if(button_id == "open_project_btn"){
                        var id = gantt.getState().lightbox;
                        window.open(`detail.php?id=${id}`, '_blank');
                        return false;
                    }
                });

                gantt.attachEvent("onLightboxSave", function(id, task, is_new){
                    if(!task.isManager){
                        showMessage('プロジェクトの編集権限がありません。', true);
                        return false;
                    }
                    // Get reference to Vue component
                    vueComponent.updateProjectDate(task);
                    return true;
                })
                
                // // Set reasonable date range
                
                // gantt.config.start_on_monday = false;
                // gantt.config.work_time = true;
                // gantt.config.skip_off_time = true;
                
                // // Enable features
                gantt.config.drag_progress = false;
                gantt.config.drag_resize = false;
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
                    { name: "index", label: "ID", width: 50, align: "center", min_width: 40, template: function (obj) {
                        return obj.id || '';
                    }},
                    { name: "branch_name", label: "支店名", width: 90, min_width: 50, template: function(obj) {
                        return obj.branch_name || '-';
                    }},
                    { name: "text", label: "件名", width: 350, tree: true, min_width: 300 },
                    { name: "team_name", label: "チーム", width: 120, min_width: 80, template: function(obj) {
                        return obj.team_name || '-';
                    }},
                    { name: "construction_number", label: "工事番号", width: 100, min_width: 80 },
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
                        return Math.round((obj.progress || 0) * 100) + "%";
                    }},
                    { name: "status", label: "状況", width: 100, align: "left", min_width: 80, template: function(obj) {
                        const status = statuses.find(s => s.key === obj.status);
                        return status ? status.name : (obj.status || 'N/A');
                    }},
                    { name: "priority", label: "優先度", width: 100, align: "left", min_width: 80, template: function(obj) {
                        const priority = priorities.find(p => p.key === obj.priority);
                        return priority ? priority.name : (obj.priority || 'N/A');
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
                
                // Badge class for 受注形態 (order type) in task bar
                const getOrderTypeBadgeClass = function(orderType) {
                    const t = String(orderType).trim().toLowerCase();
                    if (t === '修正') return 'bg-warning small';
                    if (t === '新規') return 'bg-primary small';
                    return 'bg-info small';
                };
                // Customize task text: badge before project_order_type (e.g. 期間未定), then orderType badges, team name, project name
                gantt.templates.task_text = function(start, end, task) {
                    const parts = [];
                    if (task.period_undecided) {
                        parts.push('<span class="badge bg-label-warning small me-1">期間未定</span>');
                    }
                    const teamName = task.team_name || '';
                    const orderTypeRaw = task.project_order_type || '';
                    const projectName = task.project_name || '';
                    if (orderTypeRaw) {
                        const types = orderTypeRaw.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
                        const badges = types.map(function(t) {
                            const cls = getOrderTypeBadgeClass(t);
                            return '<span class="badge ' + cls + ' small me-1">' + (t.replace(/</g, '&lt;').replace(/>/g, '&gt;')) + '</span>';
                        });
                        parts.push(badges.join(''));
                    }
                    if (teamName) {
                        const escaped = teamName.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        parts.push('<span class="badge bg-label-secondary small me-1">' + escaped + '</span>');
                    }
                    if (window.ganttShowTaskText && projectName) {
                        parts.push(projectName.replace(/</g, '&lt;').replace(/>/g, '&gt;'));
                    }
                    return parts.join('');
                };
                
                // Helper function to format date string (for caily_nouki, guis_nouki)
                const formatDateStringWithVN = function(dateString) {
                    if (!dateString || dateString === '-' || dateString === '') {
                        return '-';
                    }
                    
                    // Try to parse the date string
                    let date = null;
                    if (typeof dateString === 'string') {
                        // Handle MySQL datetime format: "2024-01-15 10:30:00"
                        if (dateString.includes(' ')) {
                            const mysqlDate = dateString.replace(' ', 'T');
                            date = new Date(mysqlDate);
                        } else if (dateString.match(/^\d{4}-\d{2}-\d{2}$/)) {
                            // Handle date only format: "2024-01-15" -> mặc định 19:00
                            date = new Date(dateString + 'T19:00:00');
                        } else {
                            date = new Date(dateString);
                        }
                    } else if (dateString instanceof Date) {
                        date = dateString;
                    }
                    
                    // Check if date is valid
                    if (!date || isNaN(date.getTime())) {
                        return dateString; // Return original if can't parse
                    }
                    
                    // Format Japan time (default): MM/DD HH:ii
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    const jpHours = date.getHours();
                    const jpMinutes = date.getMinutes();
                    const jpHoursStr = String(jpHours).padStart(2, '0');
                    const jpMinutesStr = String(jpMinutes).padStart(2, '0');
                    const japanTime = `${month}/${day} ${jpHoursStr}:${jpMinutesStr}`;
                    
                    // Calculate Vietnam time: subtract 2 hours from Japan time
                    let vnHours = jpHours - 2;
                    
                    // Handle day overflow if subtracting hours goes to previous day
                    if (vnHours < 0) {
                        vnHours += 24;
                    }
                    
                    const vnHoursStr = String(vnHours).padStart(2, '0');
                    const vnMinutesStr = String(jpMinutes).padStart(2, '0');
                    const vnTime = `${vnHoursStr}:${vnMinutesStr}`;
                    
                    return `${japanTime} (VN: ${vnTime})`;
                };
                
                // // Customize tooltip
                gantt.templates.tooltip_text = function(start, end, task) {
                    // Safely find status and priority with fallback
                    const status = statuses.find(s => s.key === task.status);
                    const priority = priorities.find(p => p.key === task.priority);

                    // 期限日: luôn dựa trên project.end_date gốc, không phụ thuộc chế độ CAILY/GUIS
                    let originalDeadline = '-';
                    if (task.project_end_date && task.project_end_date !== '-') {
                        let raw = task.project_end_date;
                        let d = null;
                        if (typeof raw === 'string') {
                            if (raw.includes(' ')) {
                                // MySQL datetime "YYYY-MM-DD HH:ii:ss"
                                d = new Date(raw.replace(' ', 'T'));
                            } else {
                                d = new Date(raw);
                            }
                        } else if (raw instanceof Date) {
                            d = raw;
                        }
                        if (d && !isNaN(d.getTime())) {
                            originalDeadline = gantt.templates.tooltip_date_format(d);
                        } else {
                            // Nếu parse không được thì hiển thị raw string
                            originalDeadline = String(task.project_end_date);
                        }
                    }
                    
                    return `
                        <div class="gantt-tooltip">
                            <h6>${task.text || 'N/A'}</h6>
                            <p class="m-0"><strong>顧客:</strong> ${task.customer || '-'}</p>
                            <p class="m-0"><strong>会社:</strong> ${task.company || '-'}</p>
                            <p class="m-0"><strong>支店:</strong> ${task.branch_name || '-'}</p>
                            <p class="m-0"><strong>建物種類:</strong> ${(task.building_type && task.building_type !== '-') ? task.building_type : '-'}</p>
                            <p class="m-0"><strong>建物規模:</strong> ${(task.building_size && task.building_size !== '-') ? task.building_size : '-'}</p>
                            <p class="m-0"><strong>受注形態:</strong> ${task.project_order_type || '-'}</p>
                            <p class="m-0"><strong>管理:</strong> ${task.manager || '-'}</p>
                            <p class="m-0"><strong>進捗:</strong> ${Math.round((task.progress || 0) * 100)}%</p>
                            <p class="m-0"><strong>状況:</strong> ${status ? status.name : (task.status || '-')}</p>
                            <p class="m-0"><strong>優先度:</strong> ${priority ? priority.name : (task.priority || '-')}</p>
                            <p class="m-0"><strong>開始日:</strong> ${gantt.templates.tooltip_date_format(start)}</p>
                            <p class="m-0"><strong>期限日:</strong> ${originalDeadline}</p>
                            <p class="m-0"><strong>担当:</strong> ${task.tantou || '-'}</p>
                            <p class="m-0"><strong>チーム:</strong> ${task.team_name || '-'}</p>
                            <p class="m-0"><strong>CAILY納期:</strong> ${formatDateStringWithVN(task.caily_nouki)}</p>
                            <p class="m-0"><strong>GUIS納期:</strong> ${formatDateStringWithVN(task.guis_nouki)}</p>
                        </div>
                    `;
                };
                
                gantt.templates.tooltip_date_format = function(date) {
                    // Format Japan time (default): MM/DD HH:ii
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    const jpHours = date.getHours();
                    const jpMinutes = date.getMinutes();
                    const jpHoursStr = String(jpHours).padStart(2, '0');
                    const jpMinutesStr = String(jpMinutes).padStart(2, '0');
                    const japanTime = `${month}/${day} ${jpHoursStr}:${jpMinutesStr}`;
                    
                    // Calculate Vietnam time: subtract 2 hours from Japan time
                    let vnHours = jpHours - 2;
                    let vnDate = new Date(date);
                    
                    // Handle day overflow if subtracting hours goes to previous day
                    if (vnHours < 0) {
                        vnHours += 24;
                        vnDate = new Date(vnDate.getTime() - 24 * 60 * 60 * 1000);
                    }
                    
                    const vnHoursStr = String(vnHours).padStart(2, '0');
                    const vnMinutesStr = String(jpMinutes).padStart(2, '0');
                    const vnTime = `${vnHoursStr}:${vnMinutesStr}`;
                    
                    return `${japanTime} (VN: ${vnTime})`;
                };
                
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
                   
                } catch (error) {
                    console.error('Error initializing Gantt:', error);
                    this.ganttInitialized = false;
                }
            },
            
            addGanttStyles() {
                const style = document.createElement('style');
                style.textContent = `
                    #gantt_container {
                        z-index: 2000 !important;
                    }
                    .gantt_tooltip,
                    .gantt_modal_box,
                    .gantt_cal_cover{
                        z-index: 2001 !important;
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
                        background-color: var(--bs-danger);
                        width: 2px;
                        opacity: 0.8;
                        z-index: 10;
                    }
                    /* Overdue projects 
                    .gantt-overdue .gantt_task_line { 
                        border-color: var(--bs-danger); 
                        border-width: 3px; 
                        border-style: dashed; 
                    }
                    .gantt-overdue .gantt_task_content {
                        background-color: var(--bs-danger);
                        color: white;
                    }*/

                    /* Status colors for task bars */
                    .gantt_task_line.gantt-status-info{ 
                        background-color: var(--bs-info); 
                        color: white;
                    }
                    .gantt_task_line.gantt-status-warning{ 
                        background-color: var(--bs-warning); 
                        color: #212529;
                    }
                    .gantt_task_line.gantt-status-primary{ 
                        background-color: var(--bs-primary); 
                        color: white;
                    }
                    .gantt_task_line.gantt-status-success{ 
                        background-color: var(--bs-success); 
                        color: white;
                    }
                    .gantt_task_line.gantt-status-danger{ 
                        background-color: var(--bs-danger); 
                        color: white;
                    }
                    .gantt_task_line.gantt-status-secondary{ 
                        background-color: var(--bs-secondary); 
                        color: white;
                    }
                    
                    /* Priority border colors
                    .gantt-priority-danger .gantt_task_line { 
                        border-color: var(--bs-danger); 
                        border-width: 3px; 
                    }
                    .gantt-priority-warning .gantt_task_line { 
                        border-color: var(--bs-warning); 
                        border-width: 3px; 
                    }
                    .gantt-priority-primary .gantt_task_line { 
                        border-color: var(--bs-primary); 
                        border-width: 2px; 
                    }
                    .gantt-priority-secondary .gantt_task_line { 
                        border-color: var(--bs-secondary); 
                        border-width: 2px; 
                    } */
                    
                    
                    
                    /* Loading indicator */
                    .gantt-loading {
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        height: 200px;
                        color: #6c757d;
                        font-size: 14px;
                    }
                    .gantt_task_content .badge{
                        font-size: 0.75rem;
                        padding: 0.15rem 0.5rem;
                        position: relative;
                        top: -2px;
                        left: -5px;
                    }
                    
                  
                `;
                document.head.appendChild(style);
            }
        }
    });
    const vm = app.mount('#app');
    window.ganttApp = vm;

    // Sau khi Vue mount xong, apply lại trạng thái filter
    // (Vue có thể reset DOM về giá trị mặc định trong template)
    loadFiltersFromLocalStorage();
    // Khôi phục status filter từ localStorage (nếu có)
    try {
        const saved = getFiltersFromLocalStorage();
        if (saved.statusKey && window.ganttApp) {
            const st = statuses.find(s => s.key === saved.statusKey);
            window.ganttApp.selectedStatus = st || null;
        } else if (window.ganttApp) {
            // Mặc định: 'all' (không filter cụ thể)
            window.ganttApp.selectedStatus = null;
        }
    } catch (e) {
        console.warn('Failed to restore status filter from storage', e);
    }
    // Đồng bộ lại biến global hiển thị task text
    window.ganttShowTaskText = $('#toggleTaskText').is(':checked');
    // Đồng bộ lại chế độ end_date (default / caily / guis)
    (function syncEndDateModeAfterMount() {
        let cChecked = $('#useCailyEndDate').is(':checked');
        let gChecked = $('#useGuisEndDate').is(':checked');
        if (cChecked && gChecked) {
            $('#useGuisEndDate').prop('checked', false);
            gChecked = false;
        }
        if (cChecked) {
            window.ganttEndDateMode = 'caily';
        } else if (gChecked) {
            window.ganttEndDateMode = 'guis';
        } else {
            window.ganttEndDateMode = 'default';
        }
    })();
    // Sau khi mọi thứ đã sync, render badge và cập nhật URL để phản ánh filter hiện tại
    renderActiveFilters();
    if (typeof saveFiltersToLocalStorage === 'function') {
        // Hàm này cũng sẽ gọi updateUrlFromFilters để đẩy trạng thái filter lên thanh address
        saveFiltersToLocalStorage();
    }
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