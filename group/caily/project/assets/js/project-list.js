var projectTable;
    var projectData = [];
    var teamIdToName = {};
    // Tooltip giờ VN (dùng chung với main.js); fallback khi main.js chưa load
    if (typeof window.formatVietnamTimeTooltip !== 'function') {
        window.formatVietnamTimeTooltip = function (jpDateTimeStr) {
            if (!jpDateTimeStr || typeof jpDateTimeStr !== 'string') return '';
            var s = String(jpDateTimeStr).trim().replace(/\//g, '-');
            if (!/^\d{4}-\d{2}-\d{2}/.test(s)) return '';
            if (typeof moment === 'undefined' || !moment.parseZone) return '';
            var m = moment.parseZone(s + '+09:00');
            if (!m.isValid()) return '';
            var vn = m.clone().subtract(2, 'hours');
            return 'VN ' + vn.format('DD/MM/YYYY HH:mm');
        };
    }
    var isInitializingTable = false;
    var autoRefreshTimer = null;
    var statuses = [
        { key: 'draft', name: '受付', color: 'secondary' },
        { key: 'open', name: '納期検討', color: 'info' },
        { key: 'confirming', name: '仮受', color: 'info' },
        { key: 'quotation', name: '見積', color: 'info' },
        { key: 'contract', name: '請負', color: 'info' },
        { key: 'in_progress', name: '進行中', color: 'primary' },
        { key: 'completed', name: '納品', color: 'success' },
        { key: 'paused', name: '一時停止', color: 'warning' },
        { key: 'cancelled', name: '中止', color: 'danger' }
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
    // --- LocalStorage filter state ---
    const FILTER_STORAGE_KEY = 'projectListFilters';
    const SELECTED_DEPARTMENT_KEY = 'projectListSelectedDepartment';
    const COLUMN_VISIBILITY_KEY = 'projectListColumnVisibility';
    
    // Column definitions with mapping to DataTable column indices
    const COLUMN_DEFINITIONS = [
        { key: 'is_favorite', label: 'お気に入り', index: 0, defaultVisible: true },
        { key: 'id', label: 'ID', index: 1, defaultVisible: true },
        { key: 'confirmation_notes', label: '確認必要メモ', index: 2, defaultVisible: false },
        { key: 'status', label: '案件状況', index: 3, defaultVisible: true },
        { key: 'progress', label: '進捗率', index: 4, defaultVisible: true },
        { key: 'tantou', label: '担当', index: 5, defaultVisible: false },
        { key: 'manager', label: '管理', index: 6, defaultVisible: false },
        { key: 'teams', label: 'チーム', index: 7, defaultVisible: true },
        { key: 'members', label: 'メンバー', index: 8, defaultVisible: false },
        { key: 'parent_construction_number', label: '工事番号', index: 9, defaultVisible: true },
        { key: 'parent_branch_name', label: '支店名', index: 10, defaultVisible: true },
        { key: 'name', label: 'お施主様名', index: 11, defaultVisible: true },
        { key: 'parent_scale', label: '規模', index: 12, defaultVisible: false },
        { key: 'parent_type1', label: '種類1', index: 13, defaultVisible: false },
        { key: 'parent_type2', label: '種類2', index: 14, defaultVisible: false },
        { key: 'start_date', label: '開始日', index: 15, defaultVisible: true },
        { key: 'caily_nouki', label: 'CAILY納期', index: 16, defaultVisible: true },
        { key: 'guis_nouki', label: 'GUIS納期', index: 17, defaultVisible: false },
        { key: 'end_date', label: '終了日', index: 18, defaultVisible: true },
        { key: 'project_order_type', label: '受注形態', index: 19, defaultVisible: true },
        { key: 'priority', label: '優先度', index: 20, defaultVisible: true },
        { key: 'amount', label: '総額', index: 21, defaultVisible: false },
        { key: 'customer_info', label: '顧客情報', index: 22, defaultVisible: true },
        { key: 'parent_guis_receiver', label: 'GUIS 受付者', index: 23, defaultVisible: false }
    ];
    
    function escapeHtmlForNote(s) {
        if (s == null || s === '') return '';
        const t = String(s);
        return t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function decodeHtmlForNote(s) {
        if (s == null || s === '') return '';
        const t = String(s);
        return t.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&amp;/g, '&');
    }

    function saveColumnVisibilityToLocalStorage(visibility) {
        localStorage.setItem(COLUMN_VISIBILITY_KEY, JSON.stringify(visibility));
    }
    
    function loadColumnVisibilityFromLocalStorage() {
        const saved = JSON.parse(localStorage.getItem(COLUMN_VISIBILITY_KEY) || '{}');
        const visibility = {};
        COLUMN_DEFINITIONS.forEach(col => {
            visibility[col.key] = saved[col.key] !== undefined ? saved[col.key] : col.defaultVisible;
        });
        return visibility;
    }
    
    function applyColumnVisibility(table, visibility) {
        if (!table || !$.fn.DataTable.isDataTable('#projectTable')) {
            return;
        }
        
        COLUMN_DEFINITIONS.forEach(col => {
            const isVisible = visibility[col.key] !== false;
            table.column(col.index).visible(isVisible, false);
        });
        table.columns.adjust().draw(false);
    }

    function saveFiltersToLocalStorage() {
        const filters = {
            filterStartMonth: $('#filterStartMonth').val(),
            filterEndMonth: $('#filterEndMonth').val(),
            filterPriority: $('#filterPriority').val(),
            filterProgress: $('#filterProgress').val(),
            filterTimeLeft: $('#filterTimeLeft').val(),
            filterProjectOrderType: $('#filterProjectOrderType').val(),
            filterTeam: $('#filterTeam').val(),
            filterTantou: $('#filterTantou').val(),
            filterNoDates: $('#filterNoDates').is(':checked') ? 1 : 0,
            filterKeyword: $('#filterKeyword').val(),
            showInactive: $('#showInactiveSwitch').is(':checked') ? 1 : 0,
            myProjects: app && app.filterMyProjects ? 1 : 0
        };
        // Lưu thêm department hiện tại và status hiện tại để đồng bộ với URL
        try {
            if (app && app.selectedDepartment && app.selectedDepartment.id) {
                filters.department_id = app.selectedDepartment.id;
            }
        } catch (e) {
            console.warn('Failed to read selectedDepartment when saving list filters', e);
        }
        try {
            if (app && app.selectedStatus && app.selectedStatus.key) {
                filters.statusKey = app.selectedStatus.key;
            } else {
                filters.statusKey = '';
            }
        } catch (e) {
            console.warn('Failed to read selectedStatus when saving list filters', e);
        }
        localStorage.setItem(FILTER_STORAGE_KEY, JSON.stringify(filters));
        updateUrlFromFilters(filters);
    }

    // Đọc filter từ URL (nếu có) và merge vào localStorage để share link
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

        if (params.has('filterStartMonth')) merged.filterStartMonth = params.get('filterStartMonth') || '';
        if (params.has('filterEndMonth')) merged.filterEndMonth = params.get('filterEndMonth') || '';
        if (params.has('filterPriority')) merged.filterPriority = params.get('filterPriority') || '';
        if (params.has('filterProgress')) merged.filterProgress = params.get('filterProgress') || '';
        if (params.has('filterTimeLeft')) merged.filterTimeLeft = params.get('filterTimeLeft') || '';
        if (params.has('filterProjectOrderType')) merged.filterProjectOrderType = params.get('filterProjectOrderType') || '';
        if (params.has('filterTeam')) merged.filterTeam = params.get('filterTeam') || '';
        if (params.has('filterTantou')) merged.filterTantou = params.get('filterTantou') || '';
        if (params.has('filterNoDates')) merged.filterNoDates = getBool('filterNoDates');
        if (params.has('filterKeyword')) merged.filterKeyword = params.get('filterKeyword') || '';
        if (params.has('showInactive')) merged.showInactive = getBool('showInactive');
        if (params.has('my_projects')) merged.myProjects = getBool('my_projects');
        if (params.has('status')) merged.statusKey = params.get('status') || '';

        // Department id để auto chọn đúng 部署 khi mở link
        if (params.has('department_id')) {
            const depId = parseInt(params.get('department_id'), 10);
            if (!isNaN(depId) && depId > 0) {
                merged.department_id = depId;
                try {
                    localStorage.setItem(SELECTED_DEPARTMENT_KEY, JSON.stringify({ id: depId }));
                } catch (e) {
                    console.warn('Failed to save department from URL into localStorage (list)', e);
                }
            }
        }

        localStorage.setItem(FILTER_STORAGE_KEY, JSON.stringify(merged));
    }

    function loadFiltersFromLocalStorage() {
        let filters = {};
        try {
            filters = JSON.parse(localStorage.getItem(FILTER_STORAGE_KEY) || '{}');
        } catch (e) {
            console.warn('Failed to parse project list filters, clearing', e);
            localStorage.removeItem(FILTER_STORAGE_KEY);
            filters = {};
        }
        if (filters.filterStartMonth !== undefined) $('#filterStartMonth').val(filters.filterStartMonth);
        if (filters.filterEndMonth !== undefined) $('#filterEndMonth').val(filters.filterEndMonth);
        if (filters.filterPriority !== undefined) $('#filterPriority').val(filters.filterPriority);
        if (filters.filterProgress !== undefined) $('#filterProgress').val(filters.filterProgress);
        if (filters.filterTimeLeft !== undefined) $('#filterTimeLeft').val(filters.filterTimeLeft);
        if (filters.filterProjectOrderType !== undefined) $('#filterProjectOrderType').val(filters.filterProjectOrderType);
        if (filters.filterTeam !== undefined) $('#filterTeam').val(filters.filterTeam);
        if (filters.filterTantou !== undefined) $('#filterTantou').val(filters.filterTantou);
        if (filters.filterNoDates !== undefined) $('#filterNoDates').prop('checked', filters.filterNoDates == 1);
        if (filters.filterKeyword !== undefined) $('#filterKeyword').val(filters.filterKeyword);
        if (filters.showInactive !== undefined) $('#showInactiveSwitch').prop('checked', filters.showInactive == 1);
        if (filters.myProjects !== undefined && app) app.filterMyProjects = filters.myProjects == 1;
    }

    function getFiltersFromLocalStorage() {
        let filters = {};
        try {
            filters = JSON.parse(localStorage.getItem(FILTER_STORAGE_KEY) || '{}');
        } catch (e) {
            console.warn('Failed to parse project list filters, clearing', e);
            localStorage.removeItem(FILTER_STORAGE_KEY);
            filters = {};
        }
        return {
            startMonth: filters.filterStartMonth || '',
            endMonth: filters.filterEndMonth || '',
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
            statusKey: filters.statusKey || '',
            department_id: filters.department_id || null
        };
    }

    function renderActiveFilters() {
        const filters = getFiltersFromLocalStorage();
        const badges = [];
        // Nếu tất cả filter đều rỗng, không hiển thị gì
        if (
            (!filters.startMonth || filters.startMonth.trim() === '') &&
            (!filters.endMonth || filters.endMonth.trim() === '') &&
            (!filters.priority || filters.priority.trim() === '') &&
            (!filters.progress || filters.progress.trim() === '') &&
            (!filters.timeLeft || filters.timeLeft.trim() === '') &&
            (!filters.projectOrderType || filters.projectOrderType.trim() === '') &&
            (!filters.team || filters.team.trim() === '') &&
            (!filters.tantou || filters.tantou.trim() === '') &&
            !filters.noDates &&
            !filters.myProjects &&
            !filters.showInactive &&
            (!filters.keyword || filters.keyword.trim() === '')
        ) {
            $('#activeFilters').html('');
            return;
        }
        if (filters.keyword && filters.keyword.trim() !== '') {
            badges.push(`<span class="badge bg-label-info me-1" >キーワード: ${filters.keyword}</span>`);
        } else {
            // Nếu có status hiện tại (từ Vue), hiển thị đầu tiên
            if (app && app.selectedStatus && app.selectedStatus.name) {
                badges.push(`<span class="badge bg-label-info me-1">案件状況: ${app.selectedStatus.name}</span>`);
            }
            if (filters.startMonth && filters.startMonth.trim() !== '') {
                badges.push(`<span class="badge bg-label-info me-1" >開始月: ${filters.startMonth}</span>`);
            }
            if (filters.endMonth && filters.endMonth.trim() !== '') {
                badges.push(`<span class="badge bg-label-info me-1" >期限月: ${filters.endMonth}</span>`);
            }
            if (filters.priority && filters.priority.trim() !== '') {
                const label = (window.priorities||[]).find(p=>p.key===filters.priority)?.name || filters.priority;
                badges.push(`<span class="badge bg-label-info me-1" >優先度: ${label}</span>`);
            }
            if (filters.projectOrderType && filters.projectOrderType.trim() !== '') {
                let label = '';
                switch (filters.projectOrderType) {
                    case 'contract':
                        label = '契約図';
                        break;
                    case 'new':
                        label = '新規';
                        break;
                    case 'edit':
                        label = '修正';
                        break;
                    case 'other':
                        label = 'その他';
                        break;
                    default:
                        label = filters.projectOrderType;
                }
                badges.push(`<span class="badge bg-label-info me-1" >受注形態: ${label}</span>`);
            }
            if (filters.team && filters.team.trim() !== '') {
                const teamName = teamIdToName[filters.team] || filters.team;
                badges.push(`<span class="badge bg-label-info me-1" >チーム: ${teamName}</span>`);
            }
            if (filters.tantou && filters.tantou.trim() !== '') {
                let label = filters.tantou;
                badges.push(`<span class="badge bg-label-info me-1" >担当: ${label}</span>`);
            }
            if (filters.noDates) {
                badges.push(`<span class="badge bg-label-info me-1" >開始日・終了日未設定</span>`);
            }
            if (filters.myProjects) {
                badges.push(`<span class="badge bg-label-info me-1">私の案件</span>`);
            }
            if (filters.showInactive) {
                badges.push(`<span class="badge bg-label-info me-1">完了・中止案件等も表示</span>`);
            }
            if (filters.progress && filters.progress.trim() !== '') {
                let label = '';
                if (filters.progress === '0-50') label = '0-50%';
                else if (filters.progress === '51-99') label = '51-99%';
                else if (filters.progress === '100') label = '100%';
                else label = filters.progress;
                badges.push(`<span class="badge bg-label-info me-1" >進捗率: ${label}</span>`);
            }
            if (filters.timeLeft && filters.timeLeft.trim() !== '') {
                let label = '';
                if (filters.timeLeft === '7') label = '7日以内';
                else if (filters.timeLeft === '30') label = '30日以内';
                else if (filters.timeLeft === 'overdue') label = '期限切れ';
                else label = filters.timeLeft;
                badges.push(`<span class="badge bg-label-info me-1" >残り時間: ${label}</span>`);
            }
        }
        

        
        if (badges.length > 0) {
            $('#activeFilters').html(`<span class="me-2 text-muted small" >適用中のフィルター:</span>` + badges.join(''));
        } else {
           // $('#activeFilters').html(`<span class="text-muted small" >すべて表示中</span>`);
        }
    }

    // Đồng bộ filters -> query string cho project list
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
        setOrDelete('filterStartMonth', filters.filterStartMonth);
        setOrDelete('filterEndMonth', filters.filterEndMonth);
        setOrDelete('filterPriority', filters.filterPriority);
        setOrDelete('filterProgress', filters.filterProgress);
        setOrDelete('filterTimeLeft', filters.filterTimeLeft);
        setOrDelete('filterProjectOrderType', filters.filterProjectOrderType);
        setOrDelete('filterTeam', filters.filterTeam);
        setOrDelete('filterTantou', filters.filterTantou);
        setOrDelete('filterNoDates', filters.filterNoDates ? 1 : '');
        setOrDelete('filterKeyword', filters.filterKeyword);
        setOrDelete('showInactive', filters.showInactive ? 1 : '');
        setOrDelete('my_projects', filters.myProjects ? 1 : '');
        setOrDelete('status', filters.statusKey);
        setOrDelete('department_id', filters.department_id);

        const baseUrl = window.location.protocol + '//' + window.location.host + window.location.pathname;
        const query = params.toString();
        const newUrl = query ? `${baseUrl}?${query}` : baseUrl;
        window.history.replaceState(null, '', newUrl);
    }

    // Load team id->name map for display in table
    async function loadTeamMap() {
        try {
            const response = await axios.get('/api/index.php?model=team&method=list');
            const list = response.data || [];
            teamIdToName = {};
            list.forEach(function(team) {
                if (team.id != null) teamIdToName[String(team.id)] = team.name || '';
            });
            return teamIdToName;
        } catch (e) {
            console.error('Error loading team list:', e);
            teamIdToName = {};
            return teamIdToName;
        }
    }

    // Function to initialize DataTable
    async function initializeProjectTable() {
        // Check if DataTable is already initialized
        if (projectTable && $.fn.DataTable.isDataTable('#projectTable')) {
            return; // Already initialized
        }
        
        // Check if already initializing to avoid race condition
        if (isInitializingTable) {
            console.log('DataTable initialization already in progress');
            return;
        }
        
        // Check if selectedDepartment exists
        if (!app || !app.selectedDepartment || !app.selectedDepartment.id) {
            console.warn('Cannot initialize DataTable: selectedDepartment is not set');
            return;
        }
        
        // Set flag to prevent multiple initializations
        isInitializingTable = true;
        
        // Load team map (id -> name) for display in table (badge, tooltip, ...)
        await loadTeamMap();
        
        // Khôi phục filter từ localStorage trước khi load projectTable
        loadFiltersFromLocalStorage();
        // Khởi tạo DataTable sau khi filter đã được khôi phục
        projectData = [];
        projectTable = $('#projectTable').DataTable({
            serverSide: true,
            processing: true,
            ajax: {
                url: '/api/index.php',
                type: 'GET',
                data: function(d) {
                    // Thu thập filter từ form
                    const filterStartMonth = $('#filterStartMonth').val();
                    const filterEndMonth = $('#filterEndMonth').val();
                    const filterPriority = $('#filterPriority').val();
                    const filterProgress = $('#filterProgress').val();
                    const filterTimeLeft = $('#filterTimeLeft').val();
                    const filterProjectOrderType = $('#filterProjectOrderType').val();
                    const filterTeam = $('#filterTeam').val();
                    const filterTantou = $('#filterTantou').val();
                    const filterNoDates = $('#filterNoDates').is(':checked') ? 1 : 0;
                    const filterKeyword = $('#filterKeyword').val();
                    const showInactive = $('#showInactiveSwitch').is(':checked') ? 1 : 0;
                    const myProjects = $('#filterMyProjects').is(':checked') ? 1 : 0;
                    const favoritesOnly = $('#filterFavoritesOnly').is(':checked') ? 1 : 0;
                    return {
                        model: 'project',
                        method: 'list',
                        department_id: app.selectedDepartment?.id,
                        status: app.selectedStatus?.key,
                        draw: d.draw,
                        start: d.start,
                        length: d.length,
                        search: d.search.value,
                        order_column: d.order && d.order[0] && d.columns[d.order[0].column]?.data || 'created_at',
                        order_dir: d.order && d.order[0] ? d.order[0].dir : 'desc',
                        filterStartMonth,
                        filterEndMonth,
                        filterPriority,
                        filterProgress,
                        filterTimeLeft,
                        filterProjectOrderType,
                        filterTeam,
                        filterTantou,
                        filterNoDates,
                        my_projects: myProjects,
                        filterKeyword,
                        showInactive,
                        favorites_only: favoritesOnly
                    };
                },
                dataSrc: function(response) {
                    return response.data || [];
                }
            },
            paging: true,
            info: true,
            searching: false,
            dom: '<"row"<"col"l><"col text-end"p>>rti',
            scrollX: true,
            //scrollY: Math.round(window.innerHeight * 0.8) + 'px',
            columns: [
                { 
                    data: 'is_favorite',
                    render: function(data, type, row) {
                        if (type === 'sort' || type === 'type') {
                            return data || 0;
                        }
                        const isFavorite = row.is_favorite == 1;
                        const starHtml = `<div class="d-flex flex-column align-items-center gap-1"><i class="fa fa-star ${isFavorite ? 'text-warning' : 'text-muted'}" 
                                   style="cursor: pointer; font-size: 1.2em;"
                                   onclick="window.toggleProjectFavorite(${row.id}, this)"
                                   title="${isFavorite ? 'お気に入りから削除' : 'お気に入りに追加'}"></i></div>`;
                        const startLabel = getStartDateLabel(row.start_date);
                        if (startLabel) {
                            return '<div class="d-flex flex-column align-items-center gap-1">' +
                                starHtml +
                                '<span class="badge ' + startLabel.class + ' mt-1" style="font-size: 0.65rem; padding: 0.15rem 0.35rem; white-space: nowrap;">' + startLabel.text + '</span>' +
                                '</div>';
                        }
                        return starHtml;
                    },
                    title: '<span data-i18n="お気に入り">お気に入り</span>',
                    orderable: false,
                    width: '70px'
                },
                { 
                    data: 'id',
                    render: function(data, type, row) {
                        return `<div class="d-flex align-items-center">
                                    <a href="detail.php?id=${row.id}" class="text-decoration-none"><span class="project-id badge bg-primary">${data || '-'}</span></a>
                                </div>`;
                    },
                    title: '<span data-i18n="ID">ID</span>',
                    width: '40px'
                },
                { 
                    data: 'confirmation_notes',
                    width: '240px',
                    className: 'confirmation-notes-column',
                    render: function(data, type, row) {
                        if (!data || data === '') {
                            return `<div class="empty-notes-cell" data-project-id="${row.id}">
                                        <span class="text-muted empty-notes-text">-</span>
                                        <span class="add-note-icon d-none" title="メモを追加" style="cursor: pointer;">
                                            <i class="fa fa-pencil-alt text-primary"></i>
                                        </span>
                                    </div>`;
                        }
                        // Hiển thị toàn bộ nội dung (có thể nhiều ghi chú), giữ nguyên xuống dòng
                        // Định dạng data: "noteId::content | noteId::content | ..."
                        const notes = data.split(' | ').filter(note => note.trim() !== '');
                        if (notes.length === 0) {
                            return `<div class="empty-notes-cell" data-project-id="${row.id}">
                                        <span class="text-muted empty-notes-text">-</span>
                                        <span class="add-note-icon d-none" title="メモを追加" style="cursor: pointer;">
                                            <i class="fa fa-pencil-alt text-primary"></i>
                                        </span>
                                    </div>`;
                        }
                        const html = notes.map(note => {
                            const raw = note.trim();
                            const delimiterIndex = raw.indexOf('::');
                            let id = null;
                            let text = raw;
                            if (delimiterIndex !== -1) {
                                id = raw.substring(0, delimiterIndex);
                                text = raw.substring(delimiterIndex + 2);
                            }
                            const isEditing = window.app && window.app.currentEditingNoteId === id;
                            return `
                                <div class="confirmation-note-item mb-1 ${isEditing ? 'editing-note' : ''}" ${id ? `data-note-id="${id}"` : ''}>
                                    <span class="note-text small" style="white-space: pre-wrap;">${escapeHtmlForNote(text)}</span>
                                    <span class="note-actions d-none ms-1">
                                        <span class="note-edit-icon me-1" title="メモを編集" style="cursor: pointer;">
                                            <i class="fa fa-pencil-alt"></i>
                                        </span>
                                        <span class="note-delete-icon text-danger" title="メモを削除" style="cursor: pointer;">
                                            <i class="fa fa-trash"></i>
                                        </span>
                                    </span>
                                </div>
                            `;
                        }).join('');
                        return `
                            <div class="confirmation-notes-wrapper" 
                                 style="max-width: 250px; max-height: 200px; overflow-y: auto;">
                                ${html}
                            </div>
                        `;
                    },
                    title: '<span data-i18n="確認必要メモ">確認必要メモ</span>',
                    orderable: false
                },
                {
                    data: 'status',
                    render: function(data, type, row) {
                        // Return original data value for sorting
                        if (type === 'sort' || type === 'type') {
                            return data || '';
                        }
                        // Return HTML for display
                        const status = statuses.find(status => status.key === data);
                        return `<span class="badge bg-${status?.color || 'secondary'}">${status?.name || data}</span>`;
                    },
                    title: '<span data-i18n="案件状況">案件状況</span>',
                    orderable: false,
                },
                {
                    data: 'progress',
                    render: function(data) {
                        const color = data === 100 ? 'success' : 'primary';
                        return `<div class="progress" style="width: 100px;">
                                    <div class="progress-bar bg-${color}" role="progressbar" 
                                            style="width: ${data}%" aria-valuenow="${data}" 
                                            aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                                <small class="text-muted">${data}%</small>`;
                    },
                    title: '<span data-i18n="進捗率">進捗率</span>'
                },
                { 
                    data: 'tantou',
                    render: function(data, type, row) {
                        if (!data || data === '') {
                            return '<span class="text-muted">-</span>';
                        }
                        // 期待値: 'CAILY' または 'GUIS'
                        if(data === 'CAILY') {
                            return `<span class="badge bg-primary small">${data}</span>`;
                        } else if(data === 'GUIS') {
                            return `<span class="badge bg-secondary small">${data}</span>`;
                        } else {
                            return `<span class="badge bg-secondary small">${data}</span>`;
                        }
                    },
                    title: '<span data-i18n="担当">担当</span>',
                    visible: false
                },
                {
                    data: 'manager_id',
                    orderable: false,
                    render: function(data) {
                        if (!data) return '-';
                        const members = data.split('|').filter(member => member.trim() !== '');
                        if (members.length === 0) return '-';

                        let html = '<div class="d-flex align-items-center">';
                        const maxAvatars = 1;
                        members.slice(0, maxAvatars).forEach(member => {
                            const [userId, realname, userImage] = member.split(':');
                            html += `<div class="avatar me-1" data-bs-toggle="tooltip" title="${realname || userId}">
                                <img src="/assets/upload/avatar/${userImage ?? 'no-image.png'}" alt="${realname || userId}" 
                                    class="rounded-circle  pull-up" width="32" height="32" 
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                                <span class="avatar-initial rounded-circle bg-label-primary pull-up" style="display:none;">
                                    ${getInitials(realname || userId)}
                                </span>
                            </div>`;
                        });

                        if (members.length > maxAvatars) {
                            const remaining = members.slice(maxAvatars).map(member => {
                                const [, realname,] = member.split(':');
                                return realname;
                            }).join(', ');
                            html += `
                                <span class="avatar-initial rounded-circle pull-up" 
                                    data-bs-toggle="tooltip" title="${remaining}" 
                                    style="display:inline-flex;">
                                    +${members.length - maxAvatars}
                                </span>
                            `;
                        }
                        html += '</div>';
                        return html;
                    },
                    title: '<span data-i18n="管理">管理</span>'
                },
                {
                    data: 'teams',
                    orderable: false,
                    render: function(data, type, row) {
                        if (!data || data === '') {
                            return '<span class="text-muted">-</span>';
                        }
                        const ids = typeof data === 'string' ? data.split(',').map(item => item.trim()).filter(item => item) : [String(data)];
                        if (ids.length === 0) return '<span class="text-muted">-</span>';
                        const labels = ids.map(function(id) { return teamIdToName[id] || id; });
                        return labels.map(function(label) { return '<span class="badge bg-label-secondary me-1">' + label + '</span>'; }).join('');
                    },
                    title: '<span data-i18n="チーム">チーム</span>'
                },
                {
                    data: 'assignment_id',
                    orderable: false,
                    render: function(data) {
                        if (!data) return '-';
                        const members = data.split('|').filter(member => member.trim() !== '');
                        if (members.length === 0) return '-';

                        let html = '<div class="d-flex align-items-center">';
                        const maxAvatars = 1;
                        members.slice(0, maxAvatars).forEach(member => {
                            const [userId, realname, userImage] = member.split(':');
                            html += `<div class="avatar me-1" data-bs-toggle="tooltip" title="${realname || userId}">
                                <img src="/assets/upload/avatar/${userImage ?? 'no-image.png'}" alt="${realname || userId}" 
                                    class="rounded-circle pull-up" width="32" height="32" 
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                                <span class="avatar-initial rounded-circle bg-label-primary pull-up" style="display:none;">
                                    ${getInitials(realname || userId)}
                                </span>
                            </div>`;
                        });

                        if (members.length > maxAvatars) {
                            const remaining = members.slice(maxAvatars).map(member => {
                                const [, realname,] = member.split(':');
                                return realname;
                            }).join(', ');
                            html += `
                                <span class="avatar-initial rounded-circle pull-up" 
                                    data-bs-toggle="tooltip" title="${remaining}" 
                                    style="display:inline-flex;">
                                    +${members.length - maxAvatars}
                                </span>
                            `;
                        }
                        html += '</div>';
                        return html;
                    },
                    title: '<span data-i18n="メンバー">メンバー</span>'
                },
                { 
                    width: '60px',
                    data: 'parent_construction_number',
                    render: function(data, type, row) {
                        if (!data || data === '') {
                            return '<span class="text-muted">-</span>';
                        }
                        return `<span class="text-nowrap small">${data}</span>`;
                    },
                    title: '<span data-i18n="工事番号">工事番号</span>'
                },
                { 
                    width: '40px',
                    data: 'parent_branch_name',
                    render: function(data, type, row) {
                        if (!data || data === '') {
                            return '<span class="text-muted">-</span>';
                        }
                        return `<span class="text-nowrap small">${data}</span>`;
                    },
                    title: '<span data-i18n="支店名">支店名</span>'
                },
                { 
                    data: 'name',
                    width: '150px',
                    render: function(data, type, row) {
                        return `<div class="d-flex align-items-start justify-content-start flex-column">
                                    <a href="detail.php?id=${row.id}" class="text-decoration-none small">${data}</a>
                                </div>`;
                    },
                    title: '<span data-i18n="お施主様名">お施主様名</span>'
                },
                { 
                    data: 'parent_scale',
                    render: function(data, type, row) {
                        if (!data || data === '') {
                            return '<span class="text-muted">-</span>';
                        }
                        return `<span class="text-nowrap small">${data}</span>`;
                    },
                    title: '<span data-i18n="規模">規模</span>',
                    visible: false
                },
                { 
                    data: 'parent_type1',
                    render: function(data, type, row) {
                        if (!data || data === '') {
                            return '<span class="text-muted">-</span>';
                        }
                        // Handle comma-separated values
                        if (typeof data === 'string' && data.includes(',')) {
                            const items = data.split(',').map(item => item.trim()).filter(item => item);
                            return items.map(item => `<span class="badge bg-info me-1">${item}</span>`).join('');
                        }
                        return `<span class="badge bg-info small">${data}</span>`;
                    },
                    title: '<span data-i18n="種類1">種類1</span>',
                    visible: false
                },
                { 
                    data: 'parent_type2',
                    render: function(data, type, row) {
                        if (!data || data === '') {
                            return '<span class="text-muted">-</span>';
                        }
                        // Handle comma-separated values
                        if (typeof data === 'string' && data.includes(',')) {
                            const items = data.split(',').map(item => item.trim()).filter(item => item);
                            return items.map(item => `<span class="badge bg-info small me-1">${item}</span>`).join('');
                        }
                        return `<span class="badge bg-info small">${data}</span>`;
                    },
                    title: '<span data-i18n="種類2">種類2</span>',
                    visible: false
                },
                { data: 'start_date', title: '<span data-i18n="開始日">開始日</span>', render: function(data) {
                    if(data) {
                        var vnTip = (typeof window.formatVietnamTimeTooltip === 'function') ? window.formatVietnamTimeTooltip(data) : '';
                        var rawEsc = String(data).replace(/"/g, '&quot;').replace(/</g, '&lt;');
                        var attrs = ' data-time="' + rawEsc + '"';
                        if (vnTip) attrs += ' data-bs-toggle="tooltip" data-bs-title="' + vnTip.replace(/"/g, '&quot;') + '"';
                        return '<span class="text-muted small text-nowrap"' + attrs + '>' + moment(data).format('M月D日 H:mm') + '</span>';
                    } else {
                        return '-';
                    }
                }},
                {
                    data: 'caily_nouki',
                    render: function(data, type, row) {
                        if (!data || data === '') {
                            return '<span class="text-muted">-</span>';
                        }
                        var vnTip = (typeof window.formatVietnamTimeTooltip === 'function') ? window.formatVietnamTimeTooltip(data) : '';
                        var rawEsc = String(data).replace(/"/g, '&quot;').replace(/</g, '&lt;');
                        var attrs = ' data-time="' + rawEsc + '"';
                        if (vnTip) attrs += ' data-bs-toggle="tooltip" data-bs-title="' + vnTip.replace(/"/g, '&quot;') + '"';
                        const timeRemaining = getTimeRemaining(data, row.status);
                        const dateStr = moment(data).format('M月D日 H:mm');
                        if (timeRemaining) {
                            const pulseClass = timeRemaining.isOverdue ? 'pulse-animation' : '';
                            const titleText = timeRemaining.isOverdue
                                ? (typeof i18next !== 'undefined' && i18next.isInitialized ? i18next.t('期限を超過しています') : '期限を超過しています')
                                : (typeof i18next !== 'undefined' && i18next.isInitialized ? i18next.t('残り時間') : '残り時間');
                            return '<div class="d-flex flex-column">' +
                                        '<span class="text-muted small text-nowrap"' + attrs + '>' + dateStr + '</span>' +
                                        '<span class="badge ' + timeRemaining.class + ' ' + pulseClass + ' mt-1" ' +
                                             'title="' + titleText.replace(/"/g, '&quot;') + '" ' +
                                             'style="font-size: 0.7rem; padding: 0.2rem 0.4rem;">' +
                                             timeRemaining.text +
                                        '</span>' +
                                    '</div>';
                        } else {
                            return '<span class="text-nowrap small text-muted"' + attrs + '>' + dateStr + '</span>';
                        }
                    },
                    title: '<span data-i18n="CAILY納期">CAILY納期</span>',
                    className: 'caily-nouki-column',
                    visible: false
                },
                {
                    data: 'guis_nouki',
                    render: function(data, type, row) {
                        if (!data || data === '') {
                            return '<span class="text-muted">-</span>';
                        }
                        var vnTip = (typeof window.formatVietnamTimeTooltip === 'function') ? window.formatVietnamTimeTooltip(data) : '';
                        var rawEsc = String(data).replace(/"/g, '&quot;').replace(/</g, '&lt;');
                        var attrs = ' data-time="' + rawEsc + '"';
                        if (vnTip) attrs += ' data-bs-toggle="tooltip" data-bs-title="' + vnTip.replace(/"/g, '&quot;') + '"';
                        const timeRemaining = getTimeRemaining(data, row.status);
                        const dateStr = moment(data).format('M月D日 H:mm');
                        if (timeRemaining) {
                            const pulseClass = timeRemaining.isOverdue ? 'pulse-animation' : '';
                            const titleText = timeRemaining.isOverdue
                                ? (typeof i18next !== 'undefined' && i18next.isInitialized ? i18next.t('期限を超過しています') : '期限を超過しています')
                                : (typeof i18next !== 'undefined' && i18next.isInitialized ? i18next.t('残り時間') : '残り時間');
                            return '<div class="d-flex flex-column">' +
                                        '<span class="text-muted small text-nowrap"' + attrs + '>' + dateStr + '</span>' +
                                        '<span class="badge ' + timeRemaining.class + ' ' + pulseClass + ' mt-1" ' +
                                             'title="' + titleText.replace(/"/g, '&quot;') + '" ' +
                                             'style="font-size: 0.7rem; padding: 0.2rem 0.4rem;">' +
                                             timeRemaining.text +
                                        '</span>' +
                                    '</div>';
                        } else {
                            return '<span class="text-nowrap small text-muted"' + attrs + '>' + dateStr + '</span>';
                        }
                    },
                    title: '<span data-i18n="GUIS納期">GUIS納期</span>',
                    visible: false
                },
                { data: 'end_date', title: '<span data-i18n="終了日">終了日</span>', render: function(data, type, row) {
                    if(data) {
                        var vnTip = (typeof window.formatVietnamTimeTooltip === 'function') ? window.formatVietnamTimeTooltip(data) : '';
                        var rawEsc = String(data).replace(/"/g, '&quot;').replace(/</g, '&lt;');
                        var attrs = ' data-time="' + rawEsc + '"';
                        if (vnTip) attrs += ' data-bs-toggle="tooltip" data-bs-title="' + vnTip.replace(/"/g, '&quot;') + '"';
                        const timeRemaining = getTimeRemaining(data, row.status);
                        const dateStr = moment(data).format('M月D日 H:mm');
                        
                        if (timeRemaining) {
                            const pulseClass = timeRemaining.isOverdue ? 'pulse-animation' : '';
                            const titleText = timeRemaining.isOverdue 
                                ? (typeof i18next !== 'undefined' && i18next.isInitialized ? i18next.t('期限を超過しています') : '期限を超過しています')
                                : (typeof i18next !== 'undefined' && i18next.isInitialized ? i18next.t('残り時間') : '残り時間');
                            return '<div class="d-flex flex-column">' +
                                        '<span class="text-muted small text-nowrap"' + attrs + '>' + dateStr + '</span>' +
                                        '<span class="badge ' + timeRemaining.class + ' ' + pulseClass + ' mt-1" ' +
                                             'title="' + titleText.replace(/"/g, '&quot;') + '" ' +
                                             'style="font-size: 0.7rem; padding: 0.2rem 0.4rem;">' +
                                             timeRemaining.text +
                                        '</span>' +
                                    '</div>';
                        } else {
                            return '<span class="text-nowrap text-muted small"' + attrs + '>' + dateStr + '</span>';
                        }
                    } else {
                        return '-';
                    }
                }, className: 'end-date-column'},
                { 
                    data: 'project_order_type',
                    render: function(data, type, row) {
                        if (!data || data === '') {
                            return '<span class="text-muted">-</span>';
                        }
                        
                        // Helper function to get badge class
                        const getOrderTypeBadgeClass = function(orderType) {
                            const type = orderType.trim().toLowerCase();
                            switch (type) {
                                case '修正':
                                    return 'bg-warning'; // Yellow for edit
                                case '新規':
                                    return 'bg-primary'; // Blue for new
                                default:
                                    return 'bg-info'; // Gray for unknown types
                            }
                        };
                        
                        // Handle comma-separated string
                        if (typeof data === 'string') {
                            const items = data.split(',').map(item => item.trim()).filter(item => item);
                            if (items.length > 0) {
                                return items.map(item => {
                                    const badgeClass = getOrderTypeBadgeClass(item);
                                    return `<span class="badge ${badgeClass} small me-1">${item}</span>`;
                                }).join('');
                            }
                        }
                        
                        // Handle array format
                        if (Array.isArray(data)) {
                            return data.map(item => {
                                const badgeClass = getOrderTypeBadgeClass(item);
                                return `<span class="badge ${badgeClass} small me-1">${item}</span>`;
                            }).join('');
                        }
                        
                        // Try to parse JSON if it's a string
                        try {
                            const decoded = decodeHtmlEntities(data);
                            const arr = JSON.parse(decoded);
                            if (Array.isArray(arr)) {
                                return arr.map(item => {
                                    const badgeClass = getOrderTypeBadgeClass(item);
                                    return `<span class="badge ${badgeClass} small me-1">${item}</span>`;
                                }).join('');
                            }
                        } catch (e) {
                            // If parsing fails, treat as single item
                            const badgeClass = getOrderTypeBadgeClass(data);
                            return `<span class="badge ${badgeClass}">${data}</span>`;
                        }
                        
                        return '<span class="text-muted">-</span>';
                    },
                    title: '<span data-i18n="受注形態">受注形態</span>'
                },
                {
                    data: 'priority',
                    render: function(data, type, row) {
                        // Return original data value for sorting
                        if (type === 'sort' || type === 'type') {
                            return data || '';
                        }
                        // Return HTML for display
                        const priority = priorities.find(priority => priority.key === data);
                        return `<span class="badge bg-${priority?.color || 'secondary'}">${priority?.name || data}</span>`;
                    },
                    title: '<span data-i18n="優先度">優先度</span>'
                },
                {
                    data: 'amount',
                    render: function(data, type, row) {
                        if (type === 'sort' || type === 'type') {
                            return parseFloat(data) || 0;
                        }
                        const val = parseFloat(data);
                        if (isNaN(val) || val === 0) return '<span class="text-muted">-</span>';
                        return '<span class="text-nowrap">¥' + parseInt(val).toLocaleString() + '</span>';
                    },
                    title: '<span data-i18n="総額">総額</span>'
                },
                { 
                    data: 'name',
                    width: '150px',
                    render: function(data, type, row) {
                        return `<div class="d-flex align-items-start justify-content-start flex-column">
                                    <div class="mt-1">
                                        <small class="text-muted d-block">${row.company_name.replace('株式会社', '').replace('有限会社', '') || '-'}</small>
                                        <small class="text-muted d-block">${row.customer_name || '-'}</small>
                                    </div>
                                </div>`;
                    },
                    title: '<span data-i18n="顧客情報">顧客情報</span>'
                },
                { 
                    data: 'parent_guis_receiver',
                    render: function(data, type, row) {
                        if (!data || data === '') {
                            return '<span class="text-muted">-</span>';
                        }
                        return `<span class="text-nowrap small">${data}</span>`;
                    },
                    title: '<span data-i18n="GUIS 受付者">GUIS 受付者</span>',
                    visible: false
                }
            ],
            order: [[COLUMN_DEFINITIONS.find(col => col.key === 'end_date').index, 'asc']],
           
            pageLength: 50,
            ordering: true,
            responsive: true,
            language: {
                search: '<span data-i18n="検索">検索</span>:',
                lengthMenu: '<span data-i18n="表示">表示</span>: _MENU_',
                info: '<span data-i18n="合計">合計</span>: _TOTAL_ <span data-i18n="件中">件中</span> _START_ <span data-i18n="から">から</span> _END_ <span data-i18n="まで">まで</span>',
                paginate: {
                    first: '<span data-i18n="先頭">先頭</span>',
                    previous: '<span data-i18n="前">前</span>',
                    next: '<span data-i18n="次">次</span>',
                    last: '<span data-i18n="最終">最終</span>'
                }
            },
            createdRow: function(row, data, dataIndex) {
                // Set background color based on status
                if (data.status) {
                    const status = statuses.find(s => s.key === data.status);
                    if (status) {
                        $(row).addClass(`table-row-status-${status.color}`);
                    }
                }
            }
            
        });
        
        // Apply column visibility after table initialization
        const columnVisibility = loadColumnVisibilityFromLocalStorage();
        applyColumnVisibility(projectTable, columnVisibility);
        
        // Reset flag after initialization
        isInitializingTable = false;

        // Khởi tạo Bootstrap tooltip cho ô giờ (data-time) mỗi khi DataTable vẽ lại
        $('#projectTable').on('draw.dt', function() {
            var table = document.getElementById('projectTable');
            if (!table || !window.bootstrap || !bootstrap.Tooltip) return;
            var triggers = table.querySelectorAll('[data-bs-toggle="tooltip"][data-time]');
            triggers.forEach(function(el) {
                var t = bootstrap.Tooltip.getInstance(el);
                if (t) t.dispose();
                new bootstrap.Tooltip(el);
            });
        });

        // Giữ Space + kéo chuột để scroll ngang và dọc bảng
        (function() {
            var spaceHeld = false;
            var dragging = false;
            var startX = 0;
            var startY = 0;
            var startScrollLeft = 0;
            var startScrollTop = 0;
            function getScrollContainer() {
                var el = document.getElementById('projectTable');
                if (!el) return null;
                var wrapper = el.closest('.dataTables_scrollBody') || el.closest('.dt-scroll-body');
                if (wrapper) return wrapper;
                var parent = el.parentElement;
                while (parent && parent !== document.body) {
                    var style = getComputedStyle(parent);
                    var scrollsX = parent.scrollWidth > parent.clientWidth && style.overflowX !== 'visible';
                    var scrollsY = parent.scrollHeight > parent.clientHeight && style.overflowY !== 'visible';
                    if (scrollsX || scrollsY) return parent;
                    parent = parent.parentElement;
                }
                return el.parentElement;
            }
            $(document).on('keydown', function(e) {
                if (e.key === ' ' || e.which === 32) {
                    var tag = (e.target || e.srcElement).tagName.toLowerCase();
                    if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
                    e.preventDefault();
                    spaceHeld = true;
                }
            });
            $(document).on('keyup', function(e) {
                if (e.key === ' ' || e.which === 32) {
                    spaceHeld = false;
                    dragging = false;
                }
            });
            $('#projectTable').closest('.card-body').on('mousedown', function(e) {
                if (!spaceHeld || e.which !== 1) return;
                var container = getScrollContainer();
                if (!container) return;
                e.preventDefault();
                dragging = true;
                startX = e.clientX;
                startY = e.clientY;
                startScrollLeft = container.scrollLeft;
                startScrollTop = container.scrollTop;
            });
            $(document).on('mousemove', function(e) {
                if (!dragging) return;
                var container = getScrollContainer();
                if (!container) return;
                container.scrollLeft = startScrollLeft + (startX - e.clientX);
                container.scrollTop = startScrollTop + (startY - e.clientY);
            });
            $(document).on('mouseup', function() {
                dragging = false;
            });
        })();

        // Khôi phục filter từ localStorage khi load trang
        // Khi thay đổi filter thì lưu lại
        let timer2= null;
        $('#projectFilterForm select, #projectFilterForm input').on('change keyup', function() {
            clearTimeout(timer2);
            timer2 = setTimeout(function() {
                saveFiltersToLocalStorage();
                renderActiveFilters();
                if (projectTable) projectTable.ajax.reload();
            }, 500);
        });
        $('#showInactiveSwitch').on('change', function() {
            saveFiltersToLocalStorage();
            renderActiveFilters();
            if (projectTable) projectTable.ajax.reload();
        });
        

        // $('#filterReset').on('click', function() {
        //     localStorage.removeItem(FILTER_STORAGE_KEY);
        //     $('#projectFilterForm')[0].reset();
        //     $('#showInactiveSwitch').prop('checked', false);
        //     if (projectTable) projectTable.ajax.reload();
        // });

        // イベントハンドラー
        $(document).on('click', '.item-edit', function() {
            const id = $(this).data('id');
            console.log('Looking for project with id:', id);
            
            // Since projectData is a Proxy of Array, we can use it directly
            console.log(projectData);
            const project = projectData.find(p => p.id === id);
         
            
            if (project) {
                app.editProject(project);
            } else {
                console.error('Project not found with id:', id);
            }
        });

        $(document).on('click', '.item-delete', function() {
            const id = $(this).data('id');
            app.deleteProject(id);
        });

        // ----- 確認必要メモ: hover pencil & context menu -----
        // Custom context menu for adding confirmation notes
        const $noteContextMenu = $('<div id="confirmationNoteContextMenu" class="dropdown-menu" style="position:absolute; display:none; z-index:9999;"></div>');
        $noteContextMenu.append('<button class="dropdown-item" type="button" id="addConfirmationNoteBtn"><i class="fa fa-plus me-1"></i>メモを追加</button>');
        $('body').append($noteContextMenu);

        let contextMenuProjectId = null;

        $('#projectTable tbody').on('contextmenu', 'td.confirmation-notes-column', function(e) {
            e.preventDefault();
            if (!projectTable) return;
            const rowData = projectTable.row($(this).closest('tr')).data();
            if (!rowData) return;
            contextMenuProjectId = rowData.id;
            $noteContextMenu
                .css({ top: e.pageY + 'px', left: e.pageX + 'px' })
                .show();
        });

        // ----- Context menu "案件を編集" (chỉ project manager) -----
        const $rowContextMenu = $('<div id="projectRowContextMenu" class="dropdown-menu" style="position:absolute; display:none; z-index:9999;"></div>');
        $rowContextMenu.append('<button class="dropdown-item" type="button" id="quickEditProjectRowBtn"><i class="fa fa-pencil-alt me-1"></i><span data-i18n="案件を編集">案件を編集</span></button>');
        $('body').append($rowContextMenu);
        let contextMenuRowProjectId = null;
        let contextMenuIsManagerOnly = false;

        function isCurrentUserManagerOfProject(rowData) {
            if (typeof USER_AUTH_ID === 'undefined' || !USER_AUTH_ID || !rowData || !rowData.manager_id) return false;
            const managerIdStr = String(rowData.manager_id).trim();
            if (!managerIdStr) return false;
            const members = managerIdStr.split('|').filter(function(m) { return m.trim() !== ''; });
            for (var i = 0; i < members.length; i++) {
                const parts = members[i].split(':');
                if (parts.length && String(parts[0]).trim() === String(USER_AUTH_ID)) return true;
            }
            return false;
        }

        $('#projectTable tbody').on('contextmenu', 'tr', function(e) {
            if ($(e.target).closest('td.confirmation-notes-column').length) return;
            if (!window.app) return;
            if (!projectTable) return;
            const rowData = projectTable.row($(this)).data();
            if (!rowData) return;
            var canFullEdit = window.app.canManageProject();
            var isManagerOfProject = isCurrentUserManagerOfProject(rowData);
            if (!canFullEdit && !isManagerOfProject) return;
            e.preventDefault();
            contextMenuRowProjectId = rowData.id;
            contextMenuIsManagerOnly = !canFullEdit && isManagerOfProject;
            $rowContextMenu
                .css({ top: e.pageY + 'px', left: e.pageX + 'px' })
                .show();
        });

        $(document).on('click', function() {
            $rowContextMenu.hide();
        });
        $rowContextMenu.on('click', '#quickEditProjectRowBtn', function(ev) {
            ev.stopPropagation();
            $rowContextMenu.hide();
            if (contextMenuRowProjectId && typeof window.openQuickEditProjectModal === 'function') {
                window.openQuickEditProjectModal(contextMenuRowProjectId, contextMenuIsManagerOnly);
            }
        });

        // Quick Edit Tagify instances (destroy on each open, re-init after load)
        let quickEditTeamTagify = null, quickEditManagerTagify = null, quickEditMembersTagify = null;
        let quickEditIsManagerOnly = false;
        function destroyQuickEditTagify() {
            [quickEditTeamTagify, quickEditManagerTagify, quickEditMembersTagify].forEach(function(t) {
                if (t && typeof t.destroy === 'function') { try { t.destroy(); } catch (e) {} }
            });
            quickEditTeamTagify = quickEditManagerTagify = quickEditMembersTagify = null;
            // Clear value các input Tagify trước khi load dự án mới
            $('#quickEditTeamTags, #quickEditManagerTags, #quickEditMembersTags').val('');
        }

        // Quick Edit Project Modal: open and save (isManagerOnly = true: chỉ hiện ステータス, 進捗率, チーム, 管理, メンバー)
        window.openQuickEditProjectModal = function(projectId, isManagerOnly) {
            quickEditIsManagerOnly = !!isManagerOnly;
            var $form = $('#quickEditProjectForm');
            if (quickEditIsManagerOnly) $form.addClass('quick-edit-manager-only-mode'); else $form.removeClass('quick-edit-manager-only-mode');
            destroyQuickEditTagify();
            var modalEl = document.getElementById('quickEditProjectModal');
            var quickEditModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            quickEditModal.show();
            $('#quickEditModalLoading').removeClass('d-none');

            axios.get('/api/index.php?model=project&method=getById&id=' + projectId).then(function(res) {
                const p = res.data && res.data.data ? res.data.data : (res.data || {});
                $('#quickEditProjectId').val(p.id || projectId);
                $('#quickEditName').val(p.name || '');
                $('#quickEditStartDate').val(p.start_date || '');
                $('#quickEditEndDate').val(p.end_date || '');
                $('#quickEditStatus').val(p.status || 'draft');
                $('#quickEditAmount').val(p.amount || '');
                $('#quickEditProjectOrderType').val(typeof p.project_order_type === 'string' ? p.project_order_type : (Array.isArray(p.project_order_type) ? (p.project_order_type || []).join(', ') : ''));
                $('input[name="tantou"]').prop('checked', false);
                if (p.tantou === 'CAILY') $('#quickEditTantouCaily').prop('checked', true);
                else if (p.tantou === 'GUIS') $('#quickEditTantouGuis').prop('checked', true);
                $('#quickEditCailyNouki').val(p.caily_nouki || '');
                $('#quickEditGuisNouki').val(p.guis_nouki || '');
                $('#quickEditProgress').val(p.progress != null && p.progress !== '' ? parseInt(p.progress, 10) : 0);
                if (typeof $().flatpickr === 'function') {
                    function makeTimeInputsEditable(selectedDates, dateStr, instance) {
                        var cal = instance.calendarContainer;
                        if (cal) {
                            var inputs = cal.querySelectorAll('.flatpickr-time input, .flatpickr-time .numInputWrapper input');
                            for (var i = 0; i < inputs.length; i++) {
                                inputs[i].removeAttribute('readonly');
                                inputs[i].readOnly = false;
                            }
                        }
                    }
                    var fpCommon = {
                        enableTime: true,
                        time_24hr: true,
                        dateFormat: 'Y/m/d H:i',
                        allowInput: true,
                        locale: 'ja',
                        onOpen: makeTimeInputsEditable
                    };
                    if ($('#quickEditStartDate').data('flatpickr')) $('#quickEditStartDate').data('flatpickr').destroy();
                    $('#quickEditStartDate').flatpickr(Object.assign({}, fpCommon, { defaultHour: 9, defaultMinute: 0 }));
                    if ($('#quickEditEndDate').data('flatpickr')) $('#quickEditEndDate').data('flatpickr').destroy();
                    $('#quickEditEndDate').flatpickr(Object.assign({}, fpCommon, { defaultHour: 18, defaultMinute: 0 }));
                    ['#quickEditCailyNouki', '#quickEditGuisNouki'].forEach(function(sel) {
                        if ($(sel).data('flatpickr')) $(sel).data('flatpickr').destroy();
                        $(sel).flatpickr(Object.assign({}, fpCommon, { defaultHour: 18, defaultMinute: 0 }));
                    });
                }

                // Load team list, project members, department users then init Tagify
                const teamIdsStr = (p.teams || '').toString().trim();
                const depId = p.department_id || '';
                Promise.all([
                    teamIdsStr ? axios.get('/api/index.php?model=team&method=listbyids&ids=' + teamIdsStr.split(',').map(function(id) { return id.trim(); }).filter(Boolean).join(',')) : Promise.resolve({ data: [] }),
                    axios.get('/api/index.php?model=project&method=getMembers&project_id=' + projectId).catch(function() { return { data: [] }; }),
                    depId ? axios.get('/api/index.php?model=department&method=get_users&department_id=' + depId).catch(function() { return { data: [] }; }) : Promise.resolve({ data: [] }),
                    axios.get('/api/index.php?model=team&method=list').catch(function() { return { data: [] }; })
                ]).then(function(results) {
                    const teamList = (results[0].data && Array.isArray(results[0].data)) ? results[0].data : [];
                    const membersRaw = results[1].data || [];
                    const managersRaw = membersRaw.filter(function(m) { return m && m.role === 'manager'; });
                    const managerIds = managersRaw.map(function(m) { return m.user_id; });
                    const membersOnly = membersRaw.filter(function(m) { return m && m.role === 'member' && managerIds.indexOf(m.user_id) === -1; });
                    const departmentUsers = (results[2].data && Array.isArray(results[2].data)) ? results[2].data : [];
                    const allTeams = (results[3].data && Array.isArray(results[3].data)) ? results[3].data : [];
                    const departmentTeams = depId ? allTeams.filter(function(t) { return String(t.department_id) === String(depId); }) : allTeams;

                    if (!window.Tagify) {
                        $('#quickEditModalLoading').addClass('d-none');
                        return;
                    }

                    // Clear trước khi gán tag mới, tránh giữ tag của dự án cũ
                    $('#quickEditTeamTags, #quickEditManagerTags, #quickEditMembersTags').val('');

                    const teamInput = document.getElementById('quickEditTeamTags');
                    if (teamInput) {
                        teamInput.value = '';
                        quickEditTeamTagify = new window.Tagify(teamInput, {
                            whitelist: departmentTeams.map(function(t) { return { value: t.name, id: t.id }; }),
                            enforceWhitelist: false,
                            dropdown: { maxItems: 1000, enabled: 0, closeOnSelect: true }
                        });
                        quickEditTeamTagify.addTags(teamList.map(function(t) { return { value: t.name, id: t.id }; }));
                        // Tự động thêm/xóa members khi chọn/bỏ team (giống project/detail.php)
                        quickEditTeamTagify.on('remove', function(e) {
                            const removedTeamId = e.detail.data && e.detail.data.id;
                            if (!removedTeamId || !quickEditMembersTagify) return;
                            axios.get('/api/index.php?model=team&method=get&id=' + removedTeamId).then(function(res) {
                                if (res.data && Array.isArray(res.data.members)) {
                                    const teamMemberIds = res.data.members.map(function(m) { return String(m.user_id); });
                                    const remain = quickEditMembersTagify.value.filter(function(tag) { return teamMemberIds.indexOf(String(tag.id)) === -1; });
                                    quickEditMembersTagify.removeAllTags();
                                    quickEditMembersTagify.addTags(remain);
                                }
                            }).catch(function() {});
                        });
                        quickEditTeamTagify.on('add', function(e) {
                            const addedTeamId = e.detail.data && e.detail.data.id;
                            if (!addedTeamId || !quickEditMembersTagify) return;
                            axios.get('/api/index.php?model=team&method=get&id=' + addedTeamId).then(function(res) {
                                if (res.data && Array.isArray(res.data.members)) {
                                    const teamMembers = res.data.members.map(function(m) { return { id: m.user_id, value: m.user_name || '' }; });
                                    const currentIds = quickEditMembersTagify.value.map(function(tag) { return String(tag.id); });
                                    const toAdd = teamMembers.filter(function(m) { return currentIds.indexOf(String(m.id)) === -1; });
                                    quickEditMembersTagify.addTags(toAdd);
                                }
                            }).catch(function() {});
                        });
                    }

                    const managerInput = document.getElementById('quickEditManagerTags');
                    if (managerInput) {
                        managerInput.value = '';
                        const allMembersForWhitelist = departmentUsers.map(function(u) { return { id: u.id || u.user_id, value: u.user_name || u.realname || '' }; });
                        quickEditManagerTagify = new window.Tagify(managerInput, {
                            whitelist: allMembersForWhitelist,
                            enforceWhitelist: false,
                            dropdown: { maxItems: 1000, enabled: 0, closeOnSelect: true }
                        });
                        quickEditManagerTagify.addTags(managersRaw.map(function(m) { return { id: m.user_id, value: m.user_name || '' }; }));
                    }

                    const membersInput = document.getElementById('quickEditMembersTags');
                    if (membersInput) {
                        membersInput.value = '';
                        const allMembersForWhitelist = departmentUsers.map(function(u) { return { id: u.id || u.user_id, value: u.user_name || u.realname || '' }; });
                        quickEditMembersTagify = new window.Tagify(membersInput, {
                            whitelist: allMembersForWhitelist,
                            enforceWhitelist: false,
                            dropdown: { maxItems: 1000, enabled: 0, closeOnSelect: true }
                        });
                        quickEditMembersTagify.addTags(membersOnly.map(function(m) { return { id: m.user_id, value: m.user_name || '' }; }));
                    }
                    $('#quickEditModalLoading').addClass('d-none');
                }).catch(function(err) {
                    console.error('Quick edit load team/members:', err);
                    $('#quickEditModalLoading').addClass('d-none');
                });
            }).catch(function(err) {
                console.error('Load project for quick edit:', err);
                $('#quickEditModalLoading').addClass('d-none');
                quickEditModal.hide();
                if (typeof alert === 'function') alert('プロジェクトの取得に失敗しました。');
            });
        };

        $('#quickEditTeamTagsClear').on('click', function() { if (quickEditTeamTagify) quickEditTeamTagify.removeAllTags(); });
        $('#quickEditManagerTagsClear').on('click', function() { if (quickEditManagerTagify) quickEditManagerTagify.removeAllTags(); });
        $('#quickEditMembersTagsClear').on('click', function() { if (quickEditMembersTagify) quickEditMembersTagify.removeAllTags(); });

        function isValidDateOrDateTime(str) {
            if (!str || typeof str !== 'string') return false;
            var s = str.trim();
            if (s === '') return false;
            var t = Date.parse(s);
            return !isNaN(t);
        }

        $('#quickEditProjectSaveBtn').on('click', function() {
            const id = $('#quickEditProjectId').val();
            if (!id) return;
            var $name = $('#quickEditName');
            var $orderType = $('#quickEditProjectOrderType');
            var $progress = $('#quickEditProgress');
            var $startDate = $('#quickEditStartDate');
            var $endDate = $('#quickEditEndDate');
            var $cailyNouki = $('#quickEditCailyNouki');
            var $guisNouki = $('#quickEditGuisNouki');
            var $tantouWrap = $('#quickEditTantouWrap');
            var errorIds = ['quickEditNameError', 'quickEditProjectOrderTypeError', 'quickEditTantouError', 'quickEditStartDateError', 'quickEditEndDateError', 'quickEditCailyNoukiError', 'quickEditGuisNoukiError', 'quickEditProgressError'];
            errorIds.forEach(function(id) { $('#' + id).text(''); });
            $name.removeClass('is-invalid');
            $orderType.removeClass('is-invalid');
            $progress.removeClass('is-invalid');
            $startDate.removeClass('is-invalid');
            $endDate.removeClass('is-invalid');
            $cailyNouki.removeClass('is-invalid');
            $guisNouki.removeClass('is-invalid');
            $tantouWrap.removeClass('is-invalid');
            var hasError = false;
            if (!quickEditIsManagerOnly) {
                if (!$name.val() || $name.val().toString().trim() === '') {
                    $name.addClass('is-invalid');
                    $('#quickEditNameError').text('案件名は必須です。');
                    hasError = true;
                }
                if (!$orderType.val() || $orderType.val().toString().trim() === '') {
                    $orderType.addClass('is-invalid');
                    $('#quickEditProjectOrderTypeError').text('受注形態は必須です。');
                    hasError = true;
                }
                if (!$('input[name="tantou"]:checked').length) {
                    $tantouWrap.addClass('is-invalid');
                    $('#quickEditTantouError').text('担当は必須です。');
                    hasError = true;
                }
                if ($startDate.val() && $startDate.val().toString().trim() !== '' && !isValidDateOrDateTime($startDate.val())) {
                    $startDate.addClass('is-invalid');
                    $('#quickEditStartDateError').text('開始日の形式が正しくありません。（例: 2025-01-15 09:00）');
                    hasError = true;
                }
                if ($endDate.val() && $endDate.val().toString().trim() !== '' && !isValidDateOrDateTime($endDate.val())) {
                    $endDate.addClass('is-invalid');
                    $('#quickEditEndDateError').text('期限日の形式が正しくありません。（例: 2025-02-28 18:00）');
                    hasError = true;
                }
                if ($cailyNouki.val() && $cailyNouki.val().toString().trim() !== '' && !isValidDateOrDateTime($cailyNouki.val())) {
                    $cailyNouki.addClass('is-invalid');
                    $('#quickEditCailyNoukiError').text('CAILY納期の形式が正しくありません。（例: 2025-01-20 18:00）');
                    hasError = true;
                }
                if ($guisNouki.val() && $guisNouki.val().toString().trim() !== '' && !isValidDateOrDateTime($guisNouki.val())) {
                    $guisNouki.addClass('is-invalid');
                    $('#quickEditGuisNoukiError').text('GUIS納期の形式が正しくありません。（例: 2025-01-25 18:00）');
                    hasError = true;
                }
            }
            var progressVal = $progress.val();
            if (progressVal !== '' && progressVal != null) {
                var p = parseInt(progressVal, 10);
                if (isNaN(p) || p < 0 || p > 100) {
                    $progress.addClass('is-invalid');
                    $('#quickEditProgressError').text('進捗率は0〜100の範囲で入力してください。');
                    hasError = true;
                }
            }
            if (hasError) {
                return;
            }
            const $btn = $('#quickEditProjectSaveBtn');
            const $spinner = $('#quickEditSaveSpinner');
            $btn.prop('disabled', true);
            $spinner.removeClass('d-none');
            const formData = new FormData();
            formData.append('model', 'project');
            formData.append('method', 'update');
            formData.append('id', id);
            formData.append('name', $('#quickEditName').val() || '');
            formData.append('start_date', $('#quickEditStartDate').val() || '');
            formData.append('end_date', $('#quickEditEndDate').val() || '');
            formData.append('status', $('#quickEditStatus').val() || 'draft');
            formData.append('amount', $('#quickEditAmount').val() || '');
            formData.append('tantou', $('input[name="tantou"]:checked').val() || '');
            formData.append('caily_nouki', $('#quickEditCailyNouki').val() || '');
            formData.append('guis_nouki', $('#quickEditGuisNouki').val() || '');
            formData.append('progress', $('#quickEditProgress').val() !== '' ? parseInt($('#quickEditProgress').val(), 10) : 0);
            formData.append('project_order_type', $('#quickEditProjectOrderType').val() || '');
            formData.append('teams', (quickEditTeamTagify && quickEditTeamTagify.value) ? quickEditTeamTagify.value.map(function(t) { return t.id; }).join(',') : '');
            formData.append('managers', (quickEditManagerTagify && quickEditManagerTagify.value) ? quickEditManagerTagify.value.map(function(t) { return t.id; }).join(',') : '');
            formData.append('members', (quickEditMembersTagify && quickEditMembersTagify.value) ? quickEditMembersTagify.value.map(function(t) { return t.id; }).join(',') : '');
            axios.post('/api/index.php?model=project&method=update', formData, { headers: { 'Content-Type': 'multipart/form-data' } }).then(function() {
                bootstrap.Modal.getInstance(document.getElementById('quickEditProjectModal')).hide();
                if (projectTable) projectTable.ajax.reload(null, false);
            }).catch(function(err) {
                console.error('Quick edit save:', err);
                if (typeof alert === 'function') alert(err.response && err.response.data && err.response.data.message ? err.response.data.message : '更新に失敗しました。');
            }).finally(function() {
                $btn.prop('disabled', false);
                $spinner.addClass('d-none');
            });
        });

        // Hide context menu on click elsewhere
        $(document).on('click', function() {
            $noteContextMenu.hide();
            $rowContextMenu.hide();
        });

        // Handle "メモを追加" click
        $noteContextMenu.on('click', '#addConfirmationNoteBtn', function(e) {
            e.stopPropagation();
            $noteContextMenu.hide();
            if (contextMenuProjectId && window.app && app.openNoteModalFromList) {
                app.openNoteModalFromList(contextMenuProjectId, null);
            }
        });

        // Hover to show/hide note action icons (edit/delete)
        $('#projectTable tbody').on('mouseenter', 'td.confirmation-notes-column .confirmation-note-item', function() {
            $(this).find('.note-actions').removeClass('d-none');
        }).on('mouseleave', 'td.confirmation-notes-column .confirmation-note-item', function() {
            $(this).find('.note-actions').addClass('d-none');
        });

        // Hover to show/hide add note icon for empty cells
        $('#projectTable tbody').on('mouseenter', 'td.confirmation-notes-column .empty-notes-cell', function() {
            $(this).find('.add-note-icon').removeClass('d-none');
            $(this).find('.empty-notes-text').addClass('d-none');
        }).on('mouseleave', 'td.confirmation-notes-column .empty-notes-cell', function() {
            $(this).find('.add-note-icon').addClass('d-none');
            $(this).find('.empty-notes-text').removeClass('d-none');
        });

        // Click add note icon to create new note
        $('#projectTable tbody').on('click', '.empty-notes-cell .add-note-icon', function(e) {
            e.stopPropagation();
            const projectId = $(this).closest('.empty-notes-cell').data('project-id');
            if (projectId && window.app && app.openNoteModalFromList) {
                app.openNoteModalFromList(projectId, null);
            }
        });

        // Click note item to edit the corresponding note
        $('#projectTable tbody').on('click', '.confirmation-note-item', function(e) {
            // Don't trigger if clicking on action buttons
            if ($(e.target).closest('.note-actions').length > 0) {
                return;
            }
            
            e.stopPropagation();
            const $item = $(this);
            if (!projectTable) return;
            const rowData = projectTable.row($item.closest('tr')).data();
            if (!rowData) return;
            const projectId = rowData.id;
            const noteId = $item.data('note-id');
            const noteText = $item.find('.note-text').text();
            if (window.app) {
                if (noteId && app.openNoteModalFromListById) {
                    app.openNoteModalFromListById(projectId, noteId);
                } else if (app.openNoteModalFromList) {
                    // Fallback cho dữ liệu cũ nếu không có note-id
                    app.openNoteModalFromList(projectId, noteText);
                }
            }
        });

        // Click pencil to edit the corresponding note
        $('#projectTable tbody').on('click', '.confirmation-note-item .note-edit-icon', function(e) {
            e.stopPropagation();
            const $item = $(this).closest('.confirmation-note-item');
            if (!projectTable) return;
            const rowData = projectTable.row($item.closest('tr')).data();
            if (!rowData) return;
            const projectId = rowData.id;
            const noteId = $item.data('note-id');
            const noteText = $item.find('.note-text').text();
            if (window.app) {
                if (noteId && app.openNoteModalFromListById) {
                    app.openNoteModalFromListById(projectId, noteId);
                } else if (app.openNoteModalFromList) {
                    // Fallback cho dữ liệu cũ nếu không có note-id
                    app.openNoteModalFromList(projectId, noteText);
                }
            }
        });

        // Click trash icon to delete the corresponding note
        $('#projectTable tbody').on('click', '.confirmation-note-item .note-delete-icon', async function(e) {
            e.stopPropagation();
            const $item = $(this).closest('.confirmation-note-item');
            const noteId = $item.data('note-id');
            if (!noteId) return;

            if (!confirm('このメモを削除しますか？')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('id', noteId);
                const response = await axios.post('/api/index.php?model=project&method=deleteNote', formData);
                if (response.data && response.data.status === 'success') {
                    showMessage('メモが削除されました');
                    if (projectTable) {
                        projectTable.ajax.reload(null, false);
                    }
                } else {
                    showMessage('メモの削除に失敗しました', true);
                }
            } catch (error) {
                console.error('Error deleting note:', error);
                showMessage('メモの削除に失敗しました', true);
            }
        });

        $('#start_date').flatpickr({    
            dateFormat: 'Y-m-d',
            onChange: function(date) {
                console.log(date);
            }
        });

        $('#end_date').flatpickr({
            dateFormat: 'Y-m-d',
            onChange: function(date) {
                console.log(date);
            }
        });

        // Khởi tạo flatpickr dạng tháng (month picker) cho filterStartMonth và filterEndMonth
        if (window.flatpickr) {
            $('#filterStartMonth').flatpickr({
                locale: 'ja',
                plugins: [new monthSelectPlugin({
                    shorthand: true,
                    dateFormat: 'Y-m',
                    altFormat: 'Y年m月',
                })],
                onChange: function(date) {
                    saveFiltersToLocalStorage();
                    renderActiveFilters();
                }
            });
            $('#filterEndMonth').flatpickr({
                locale: 'ja',
                plugins: [new monthSelectPlugin({
                    shorthand: true,
                    dateFormat: 'Y-m',
                    altFormat: 'Y年m月',
                })],
                onChange: function(date) {
                    saveFiltersToLocalStorage();
                    renderActiveFilters();
                }
            });
        }

        var category_id = $('#category_id');
        var company_name = $('#company_name');
        var customer_id = $('#customer_id');
        if (category_id.length) {
            // Initialize Select2 for category
            category_id.wrap('<div class="position-relative"></div>').select2({
                placeholder: '選択してください',
                dropdownParent: category_id.parent(),
                allowClear: true,
                minimumResultsForSearch: 0,
                ajax: {
                    url: '/api/index.php?model=customer&method=list_categories',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            search: params.term,
                            page: params.page || 1
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.data.map(function(item) {
                                return {
                                    id: item.id,
                                    text: item.name
                                };
                            })
                        };
                    }
                }
            }).on('select2:select', function(e) {
                console.log('Category selected:', e.params.data);
                app.newProject.category_id = e.params.data.id;
            }).on('select2:unselect', function() {
                console.log('Category unselected');
                app.newProject.category_id = '';
            });

            // Hide company_name and customer_id initially and disable their validation
            $('#company_name').closest('.form-group').hide();
            $('#customer_id').closest('.form-group').hide();
            
            // Use setTimeout to ensure app.formValidator is initialized
            setTimeout(() => {
                if (app.formValidator) {
                    app.formValidator.disableValidator('company_name');
                    app.formValidator.disableValidator('customer_id');
                }
            }, 100);

            // Check initial values of company_name and customer_id
            if (company_name.val()) {
                $('#company_name').closest('.form-group').show();
                setTimeout(() => {
                    if (app.formValidator) {
                        app.formValidator.enableValidator('company_name');
                    }
                }, 100);
            }
            if (customer_id.val()) {
                $('#customer_id').closest('.form-group').show();
                setTimeout(() => {
                    if (app.formValidator) {
                        app.formValidator.enableValidator('customer_id');
                    }
                }, 100);
            }

            // When category_id changes
            category_id.on('change', function() {
                const categoryValue = $(this).val();
                console.log('Category changed:', categoryValue);
                
                if (categoryValue) {
                    // Show company_name and enable its validation when category_id has value
                    $('#company_name').closest('.form-group').show();
                    setTimeout(() => {
                        if (app.formValidator) {
                            app.formValidator.enableValidator('company_name');
                            app.formValidator.revalidateField('category_id');
                        }
                    }, 100);
                    // Reset and refresh company_name
                    company_name.val(null).trigger('change');
                    // Hide customer_id, disable its validation and reset
                    $('#customer_id').closest('.form-group').hide();
                    setTimeout(() => {
                        if (app.formValidator) {
                            app.formValidator.disableValidator('customer_id');
                        }
                    }, 100);
                    customer_id.val(null).trigger('change');
                } else {
                    // Hide both fields and disable their validation when no category_id
                    $('#company_name').closest('.form-group').hide();
                    $('#customer_id').closest('.form-group').hide();
                    setTimeout(() => {
                        if (app.formValidator) {
                            app.formValidator.disableValidator('company_name');
                            app.formValidator.disableValidator('customer_id');
                        }
                    }, 100);
                    company_name.val(null).trigger('change');
                    customer_id.val(null).trigger('change');
                }
            });
        }
        
        if (company_name.length) {
            // Initialize Select2 for company
            company_name.wrap('<div class="position-relative"></div>').select2({
                placeholder: '選択してください',
                dropdownParent: company_name.parent(),
                allowClear: true,
                escapeMarkup: function(markup) {
                    return markup;
                },
                language: {
                    noResults: function() {
                        return '見つかりません。<button class="btn btn-warning btn-sm w-50" onclick="app.openNewCustomerModal()">新規顧客を追加</button>';
                    },
                },
                minimumResultsForSearch: 0,
                ajax: {
                    url: '/api/index.php?model=customer&method=list_companies_by_category',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            search: params.term,
                            page: params.page || 1,
                            category_id: category_id.val()
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.data.map(function(item) {
                                return {
                                    id: item.company_name,
                                    text: item.company_name
                                };
                            })
                        };
                    }
                }
            }).on('select2:select', function(e) {
                console.log('Company selected:', e.params.data);
                app.newProject.company_name = e.params.data.id;
            }).on('select2:unselect', function() {
                console.log('Company unselected');
                app.newProject.company_name = '';
            });

            // When company_name changes
            company_name.on('change', function() {
                const companyValue = $(this).val();
                console.log('Company changed:', companyValue);
                
                if (companyValue) {
                    // Show customer_id and enable its validation when company_name has value
                    $('#customer_id').closest('.form-group').show();
                    setTimeout(() => {
                        if (app.formValidator) {
                            app.formValidator.enableValidator('customer_id');
                            app.formValidator.revalidateField('company_name');
                        }
                    }, 100);
                    // Reset and refresh customer_id
                    customer_id.val(null).trigger('change');
                } else {
                    // Hide customer_id, disable its validation and reset when no company_name
                    $('#customer_id').closest('.form-group').hide();
                    setTimeout(() => {
                        if (app.formValidator) {
                            app.formValidator.disableValidator('customer_id');
                        }
                    }, 100);
                    customer_id.val(null).trigger('change');
                }
            });
        }

        if (customer_id.length) {
            // Initialize Select2 for customer
            customer_id.wrap('<div class="position-relative"></div>').select2({
                placeholder: '選択してください',
                dropdownParent: customer_id.parent(),
                allowClear: true,
                minimumResultsForSearch: 0,
                ajax: {
                    url: '/api/index.php?model=customer&method=list_contacts_by_company',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            search: params.term,
                            page: params.page || 1,
                            company_name: company_name.val()
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.data.map(function(item) {
                                return {
                                    id: item.id,
                                    text: item.name
                                };
                            })
                        };
                    }
                }
            }).on('select2:select', function(e) {
                console.log('Customer selected:', e.params.data);
                app.newProject.customer_id = e.params.data.id;
            }).on('select2:unselect', function() {
                console.log('Customer unselected');
                app.newProject.customer_id = '';
            });
        }
        
        // Render option cho filterPriority dựa trên priorities (có i18n)
        var $priority = $('#filterPriority');
        $priority.empty();
        var allLabel = translateText('すべて');
        $priority.append('<option value="" data-i18n="すべて">' + allLabel + '</option>');
        priorities.forEach(function(p) {
            var label = translateText(p.name);
            $priority.append('<option value="' + p.key + '" data-i18n="' + p.name + '">' + label + '</option>');
        });
        
        // Gọi khi filter thay đổi hoặc khi load trang
        renderActiveFilters();
        // Gọi lại renderActiveFilters mỗi khi filter thay đổi
        $('#filterStartMonth, #filterEndMonth, #filterPriority, #filterProgress, #filterTimeLeft, #filterProjectOrderType, #filterTeam, #filterTantou, #filterNoDates, #filterKeyword, #showInactiveSwitch').on('change input', function() {
           renderActiveFilters();
        });
        let timer = null;
        $('#filterKeyword').on('input', function() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                renderActiveFilters();
            }, 500);
        });
        // Đảm bảo badge update khi reset filter
        $('#filterReset').on('click', function() {
            // Reset các filter về mặc định
            $('#projectFilterForm')[0].reset();
            $('#filterStartMonth').val('');
            $('#filterEndMonth').val('');
            $('#filterPriority').val('');
            $('#filterProgress').val('');
            $('#filterTimeLeft').val('');
            $('#filterProjectOrderType').val('');
            $('#filterTeam').val('');
            $('#filterTantou').val('');
            $('#filterNoDates').prop('checked', false);
            $('#filterKeyword').val('');
            $('#showInactiveSwitch').prop('checked', true); // hoặc giá trị mặc định
            // Reset favorites filter
            $('#filterFavoritesOnly').prop('checked', false);
            $('#filterMyProjects').prop('checked', false);
            if (app) {
                app.showClearAllFavoritesBtn = false;
            }
            localStorage.removeItem(FILTER_STORAGE_KEY);
            // Reset status filter
            if (app && app.selectedStatus) {
                app.selectedStatus = null;
            }
            renderActiveFilters();
            projectTable.ajax.reload();
        });
        
    }
    
    // Setup auto-refresh timer once (independent of DataTable initialization)
    $(document).ready(function() {
        // Setup auto-refresh timer for project list
        if (!autoRefreshTimer) {
            autoRefreshTimer = setInterval(function() {
                if (projectTable && $.fn.DataTable.isDataTable('#projectTable')) {
                    projectTable.ajax.reload(null, false); // false để giữ nguyên trang hiện tại
                }
            }, 60000); // Cập nhật mỗi phút
        }
        
        // Wait a bit for Vue app to mount
        setTimeout(function() {
            if (app && app.selectedDepartment && app.selectedDepartment.id) {
                initializeProjectTable();
            }
        }, 100);
    });

    // Helper function to get initials from name
    function getInitials(name) {
        return getAvatarName(name);
    }

    function decodeHtmlEntities(str) {
        var txt = document.createElement('textarea');
        txt.innerHTML = str;
        return txt.value;
    }

    // Helper function để dịch text
    function translateText(key) {
        if (typeof i18next !== 'undefined' && i18next.isInitialized) {
            return i18next.t(key) || key;
        }
        return key;
    }

    function getTimeRemaining(endDate, status) {
        if (!endDate || status === 'completed' || status === 'deleted' || status === 'draft' || status === 'cancelled') {
            return null;
        }
        
        const now = moment.tz('Asia/Tokyo');
        const end = moment.tz(endDate, 'Asia/Tokyo');
        
        // Kiểm tra ngôn ngữ hiện tại
        const isVietnamese = typeof i18next !== 'undefined' && i18next.isInitialized && i18next.language === 'vi';
        
        // Lấy các nhãn đã dịch
        const dayLabel = translateText('日');
        const hourLabel = translateText('時間');
        const minuteLabel = translateText('分');
        const overdueLabel = translateText('超過');
        
        // Hàm helper để format số và đơn vị với khoảng cách cho tiếng Việt
        const formatUnit = (value, label, isOverdue = false) => {
            if (isVietnamese) {
                return `${value} ${label} ${isOverdue ? overdueLabel : ''}`;
            } else {
                return `${value}${label}${isOverdue ? overdueLabel : ''}`;
            }
        };
        
        if (end.isBefore(now)) {
            // Đã quá hạn
            const diff = now.diff(end);
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            
            let text = '';
            
            if (days > 0) {
                if (isVietnamese) {
                    text = `${days} ${dayLabel}`;
                    if (hours > 0) {
                        text += ` ${hours} ${hourLabel}`;
                    }
                    text += ` ${overdueLabel}`;
                } else {
                    text = `${days}${dayLabel}`;
                    if (hours > 0) {
                        text += `${hours}時`;
                    }
                    text += `${overdueLabel}`;
                }
            } else if (hours > 0) {
                if (isVietnamese) {
                    text = `${hours} ${hourLabel} ${overdueLabel}`;
                } else {
                    text = `${hours}時${overdueLabel}`;
                }
            } else {
                if (isVietnamese) {
                    text = `${minutes} ${minuteLabel} ${overdueLabel}`;
                } else {
                    text = `${minutes}分${overdueLabel}`;
                }
            }
            
            return {
                text: text,
                class: 'bg-danger',
                isOverdue: true
            };
        } else {
            // Còn thời gian
            const diff = end.diff(now);
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            
            let text = '+';
            let class_name = 'bg-label-info';
            
            if (days > 0) {
                if (isVietnamese) {
                    text += `${days} ${dayLabel}`;
                    if (hours > 0) {
                        text += ` ${hours} ${hourLabel}`;
                    }
                } else {
                    text += `${days}${dayLabel}`;
                    if (hours > 0) {
                        text += `${hours}時`;
                    }
                }
                class_name = 'bg-label-info';
            } else if (hours > 0) {
                if (isVietnamese) {
                    text += `${hours} ${hourLabel}`;
                } else {
                    text += `${hours}時`;
                }
                class_name = hours <= 24 ? 'bg-label-warning' : 'bg-label-info';
            } else {
                if (isVietnamese) {
                    text += `${minutes} ${minuteLabel}`;
                } else {
                    text += `${minutes}分`;
                }
                class_name = 'bg-label-warning';
            }
            
            return {
                text: text,
                class: class_name,
                isOverdue: false
            };
        }
    }

    function getStartDateLabel(startDate) {
        if (!startDate) return null;
        const now = moment.tz('Asia/Tokyo');
        const start = moment.tz(startDate, 'Asia/Tokyo');
        const nowStart = now.clone().startOf('day');
        const startStart = start.clone().startOf('day');
        const daysDiff = startStart.diff(nowStart, 'days');
        if (daysDiff < 0) return null;
        if (daysDiff === 0) {
            const t = translateText('start_today');
            return { text: (t && t !== 'start_today') ? t : '開始今日', class: 'bg-label-danger' };
        }
        if (daysDiff === 1) {
            const t = translateText('start_tomorrow');
            return { text: (t && t !== 'start_tomorrow') ? t : '開始明日', class: 'bg-label-info' };
        }
        if (daysDiff >= 2) {
            let template = translateText('start_in_n_days') || '';
            if (!template || template === 'start_in_n_days' || template.indexOf('{{n}}') === -1) {
                template = '開始{{n}}日後';
            }
            const text = template.replace(/\{\{n\}\}/g, daysDiff);
            return { text: text, class: 'bg-label-info' };
        }
        return null;
    }

    const { createApp } = Vue;
    const app = createApp({
        data() {
            return {
                selectedDepartment: null,
                showClearAllFavoritesBtn: false,
                projects: [],
                departments: [],
                branches: [],
                selectedStatus: null,
                statuses: statuses,
                userPermissions: null,
                newProject: {
                    name: '',
                    description: '',
                    status: 'draft',
                    priority: 'medium',
                    start_date: '',
                    end_date: '',
                    team: [],
                    members: [],
                    manager: [],
                    department_id: '',
                    building_size: '',
                    building_type: '',
                    building_number: '',
                    building_branch: '',
                    project_number: '',
                    project_order_type: '新規',
                    estimated_hours: '',
                    amount: '',
                    customer_id: '',
                    company_name: '',
                    teams: '',
                    branch_id: '',
                    contact_name: '',
                    contact_phone: ''
                },
                categories: [],
                companies: [],
                contacts: [],
                users: [],
                teams: [],
                isEdit: false,
                perPage: 20,
                currentPage: 1,
                editingId: null,
                deletingId: null,
                tagifyInstance: null,
                teamTagifyInstance: null,
                membersTagifyInstance: null,
                managerTagifyInstance: null,
                customerTagifyInstance: null,
                formValidator: null,
                // Notes (確認必要メモ)
                notes: [],
                showNoteModal: false,
                isNoteEditMode: false,
                currentEditingNoteId: null, // Track which note is being edited
                editingNote: {
                    id: null,
                    title: '',
                    content: '',
                    is_important: false,
                    needs_confirmation: false,
                    user_id: null
                },
                currentNoteProjectId: null,
                // Kadai queue properties
                kadaiProjects: [],
                isKadaiQueueExpanded: false,
                // Column visibility
                availableColumns: COLUMN_DEFINITIONS.map(col => ({
                    key: col.key,
                    label: col.label,
                    visible: true
                }))
            }
        },
        computed: {
            createUrl() {
               if(this.selectedDepartment) {
                return `create.php?department_id=${this.selectedDepartment.id}`;
               }
               return `create.php`;
            }
        },
        mounted() {
            // Load filter state from localStorage
            let filters = {};
            try {
                filters = JSON.parse(localStorage.getItem(FILTER_STORAGE_KEY) || '{}');
            } catch (e) {
                filters = {};
            }
            if (filters.myProjects !== undefined) {
                this.filterMyProjects = filters.myProjects == 1;
            }
            // Khôi phục status đã lưu (nếu có) để hiển thị trong 適用中のフィルター
            if (filters.statusKey) {
                const st = statuses.find(s => s.key === filters.statusKey);
                if (st) {
                    this.selectedStatus = st;
                }
            }
            
            // Load column visibility
            const columnVisibility = loadColumnVisibilityFromLocalStorage();
            this.availableColumns = COLUMN_DEFINITIONS.map(col => ({
                key: col.key,
                label: col.label,
                visible: columnVisibility[col.key] !== false
            }));
            
            this.loadDepartments();
            // Không load dự án ngay lập tức, chỉ load khi có department được chọn
            
            // Auto-refresh kadai queue every 5 minutes (chỉ khi có department được chọn)
            // setInterval(() => {
            //     if (this.selectedDepartment && this.selectedDepartment.id) {
            //         this.loadKadaiProjects();
            //     }
            // }, 5 * 60 * 1000);

            // Initialize Tagify for project_order_type
            this.$nextTick(() => {
                const input = document.querySelector('#project_order_type');
                if (input && window.Tagify) {
                    this.tagifyInstance = new Tagify(input, {
                        whitelist: ['新規', '修正', '免震', '耐震', '計画変更'],
                        maxTags: 5,
                        dropdown: {
                            maxItems: 20,
                            classname: "tags-look",
                            enabled: 0,
                            closeOnSelect: true
                        },
                        callbacks: {
                            add: (e) => {
                                const tags = this.tagifyInstance.value.map(tag => tag.value);
                                this.newProject.project_order_type = tags;
                                if (this.formValidator) {
                                    this.formValidator.revalidateField('project_order_type');
                                }
                            },
                            remove: (e) => {
                                const tags = this.tagifyInstance.value.map(tag => tag.value);
                                this.newProject.project_order_type = tags;
                                if (this.formValidator) {
                                    this.formValidator.revalidateField('project_order_type');
                                }
                            }
                        }
                    });

                    // Set default value
                    if (!this.newProject.project_order_type || this.newProject.project_order_type.length === 0) {
                        this.tagifyInstance.addTags(['新規']);
                    } else if (Array.isArray(this.newProject.project_order_type)) {
                        this.tagifyInstance.addTags(this.newProject.project_order_type);
                    }
                }
            });

            // Initialize Tagify for team, members, and manager
            this.$nextTick(() => {
                // Initialize Tagify for team
                const teamInput = document.querySelector('input[name="team_tags"]');
                if (teamInput && window.Tagify) {
                    this.teamTagifyInstance = new Tagify(teamInput, {
                        whitelist: [],
                        maxTags: 5,
                        dropdown: {
                            maxItems: 20,
                            classname: "tags-look",
                            enabled: 0,
                            closeOnSelect: false
                        },
                        callbacks: {
                            add: (e) => {
                                const teamId = e.detail.tag.id;
                                const teams = this.teamTagifyInstance.value.map(team => team.id);
                                this.newProject.teams = teams;
                                this.loadTeamMembers(teamId);
                                if (this.formValidator) {
                                    this.formValidator.revalidateField('team_tags');
                                }
                            },
                            remove: (e) => {
                                const teams = this.teamTagifyInstance.value.map(team => team.id);
                                this.newProject.teams = teams;
                                this.newProject.members = [];
                                if (this.membersTagifyInstance) {
                                    this.membersTagifyInstance.removeAllTags();
                                }
                                if (this.formValidator) {
                                    this.formValidator.revalidateField('team_tags');
                                }
                            }
                        }
                    });
                }

                // Initialize Tagify for members
                const membersInput = document.querySelector('input[name="members_tags"]');
                if (membersInput && window.Tagify) {
                    this.membersTagifyInstance = new Tagify(membersInput, {
                        whitelist: [],
                        maxTags: 10,
                        dropdown: {
                            maxItems: 20,
                            classname: "tags-look",
                            enabled: 0,
                            closeOnSelect: true
                        },
                        callbacks: {
                            add: (e) => {
                                const members = this.membersTagifyInstance.value.map(member => member.id);
                                this.newProject.members = members;
                                if (this.formValidator) {
                                    this.formValidator.revalidateField('members_tags');
                                }
                            },
                            remove: (e) => {
                                const members = this.membersTagifyInstance.value.map(member => member.id);
                                this.newProject.members = members;
                                if (this.formValidator) {
                                    this.formValidator.revalidateField('members_tags');
                                }
                            }
                        }
                    });
                }

                // Initialize Tagify for manager
                const managerInput = document.querySelector('input[name="manager_tags"]');
                if (managerInput && window.Tagify) {
                    this.managerTagifyInstance = new Tagify(managerInput, {
                        whitelist: [],
                        maxTags: 10,
                        dropdown: {
                            maxItems: 20,
                            classname: "tags-look",
                            enabled: 0,
                            closeOnSelect: true
                        },
                        callbacks: {
                            add: (e) => {
                                const manager = this.managerTagifyInstance.value.map(manager => manager.id);
                                this.newProject.manager = manager;
                                if (this.formValidator) {
                                    this.formValidator.revalidateField('manager_tags');
                                }
                            },
                            remove: (e) => {
                                const manager = this.managerTagifyInstance.value.map(manager => manager.id);
                                this.newProject.manager = manager;
                                if (this.formValidator) {
                                    this.formValidator.revalidateField('manager_tags');
                                }
                            }
                        }
                    });
                }
            });

            // Initialize form validation
            const form = document.querySelector('#newProjectModal form');
            if (form) {
                this.formValidator = FormValidation.formValidation(form, {
                    fields: {
                        name: {
                            validators: {
                                notEmpty: {
                                    message: 'お施主様名を入力してください'
                                }
                            }
                        },
                        department_id: {
                            validators: {
                                notEmpty: {
                                    message: '部署を選択してください'
                                }
                            }
                        },
                        category_id: {
                            validators: {
                                callback: {
                                    message: 'カテゴリを選択してください',
                                    callback: function(input) {
                                        const categoryValue = $('#category_id').val();
                                        console.log('Validating category_id:', categoryValue);
                                        return categoryValue && categoryValue !== '';
                                    }
                                }
                            }
                        },
                        company_name: {
                            validators: {
                                callback: {
                                    message: '会社名を選択してください',
                                    callback: function(input) {
                                        const companyValue = $('#company_name').val();
                                        console.log('Validating company_name:', companyValue);
                                        return companyValue && companyValue !== '';
                                    }
                                }
                            }
                        },
                        customer_id: {
                            validators: {
                                callback: {
                                    message: '担当者名を選択してください',
                                    callback: function(input) {
                                        const customerValue = $('#customer_id').val();
                                        console.log('Validating customer_id:', customerValue);
                                        return customerValue && customerValue !== '';
                                    }
                                }
                            }
                        },
                        building_size: {
                            validators: {
                                notEmpty: {
                                    message: '建物規模を入力してください'
                                }
                            }
                        },
                        building_type: {
                            validators: {
                                notEmpty: {
                                    message: '建物種類を入力してください'
                                }
                            }
                        },
                        project_number: {
                            validators: {
                                notEmpty: {
                                    message: '連絡番号を入力してください'
                                }
                            }
                        },
                        project_order_type: {
                            validators: {
                                callback: {
                                    message: '受注形態を選択してください',
                                    callback: function(input) {
                                        return app.tagifyInstance && app.tagifyInstance.value.length > 0;
                                    }
                                }
                            }
                        },
                        priority: {
                            validators: {
                                notEmpty: {
                                    message: '優先度を選択してください'
                                }
                            }
                        },
                        status: {
                            validators: {
                                notEmpty: {
                                    message: '案件状況を選択してください'
                                }
                            }
                        },
                        start_date: {
                            validators: {
                                notEmpty: {
                                    message: '開始日を選択してください'
                                }
                            }
                        },
                        end_date: {
                            validators: {
                                notEmpty: {
                                    message: '終了日を選択してください'
                                }
                            }
                        },
                        team_tags: {
                            validators: {
                                callback: {
                                    message: 'チームを選択してください',
                                    callback: function(input) {
                                        return app.teamTagifyInstance && app.teamTagifyInstance.value.length > 0;
                                    }
                                }
                            }
                        },
                        members_tags: {
                            validators: {
                                callback: {
                                    message: 'メンバーを選択してください',
                                    callback: function(input) {
                                        return app.membersTagifyInstance && app.membersTagifyInstance.value.length > 0;
                                    }
                                }
                            }
                        },
                        manager_tags: {
                            validators: {
                                callback: {
                                    message: 'メンバーを選択してください',
                                    callback: function(input) {
                                        return app.managerTagifyInstance && app.managerTagifyInstance.value.length > 0;
                                    }
                                }
                            }
                        }
                    },
                    plugins: {
                        trigger: new FormValidation.plugins.Trigger(),
                        bootstrap5: new FormValidation.plugins.Bootstrap5({
                            eleValidClass: '',
                            rowSelector: '.form-control-validation'
                        }),
                    },
                });
            }
        },
        beforeUnmount() {
            // Clean up Tagify instances when component is destroyed
            if (this.tagifyInstance) {
                this.tagifyInstance.destroy();
            }
            if (this.teamTagifyInstance) {
                this.teamTagifyInstance.destroy();
            }
            if (this.membersTagifyInstance) {
                this.membersTagifyInstance.destroy();
            }
            if (this.managerTagifyInstance) {
                this.managerTagifyInstance.destroy();
            }
        },
        methods: {
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
                    const response = await axios.get('/api/index.php?model=department&method=listByUser');
                    this.departments = response.data || [];
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
                        const firstDepartment = this.departments.find(d => d && d.can_project == 1);
                        if (firstDepartment) {
                            this.viewProjects(firstDepartment);
                        }
                    } else {
                        throw new Error('No department found');
                    }
                } catch (error) {
                    console.error('Error loading departments:', error);
                    this.departments = [];
                    showMessage('どの部署にも所属していません。管理者に問い合わせてください。', true);
                }
            },
            async getUserPermissions(departmentId) {
                try {
                    const response = await axios.get(`/api/index.php?model=department&method=get_user_permission_by_department&userid=${USER_ID}&department_id=${departmentId}`);
                    this.userPermissions = response.data;
                    return this.userPermissions;
                } catch (error) {
                    console.error('Error loading user permissions:', error);
                    this.userPermissions = null;
                    return null;
                }
            },
            // Check if user has specific permission
            hasPermission(permission) {
                if(USER_ROLE == 'administrator') return true;
                if (!this.userPermissions) return false;
                return this.userPermissions[permission] == 1;
            },
            // Check if user can perform project actions
            canAddProject() {
                return this.hasPermission('project_add');
            },
            canEditProject() {
                return this.hasPermission('project_edit');
            },
            canDeleteProject() {
                return this.hasPermission('project_delete');
            },
            canManageProject() {
                return this.hasPermission('project_manager');
            },
            canCommentProject() {
                return this.hasPermission('project_comment');
            },
            // ----- Notes (確認必要メモ) methods -----
            async loadNotesForProject(projectId) {
                try {
                    const response = await axios.get(`/api/index.php?model=project&method=getNotes&project_id=${projectId}`);
                    if (response.data && response.data.status === 'success') {
                        this.notes = response.data.data || [];
                    } else {
                        this.notes = [];
                    }
                } catch (error) {
                    console.error('Error loading notes:', error);
                    this.notes = [];
                }
            },
            openNoteModalFromListById(projectId, noteId) {
                this.currentNoteProjectId = projectId;
                this.showNoteModal = true;
                this.isNoteEditMode = true;
                this.currentEditingNoteId = noteId; // Track which note is being edited
                
                // Refresh table to show highlight
                if (projectTable) {
                    projectTable.draw(false);
                }
                
                // Reset editing note
                this.editingNote = {
                    id: null,
                    title: '',
                    content: '',
                    is_important: false,
                    needs_confirmation: true,
                    user_id: null
                };
                this.loadNotesForProject(projectId).then(() => {
                    const match = this.notes.find(n => String(n.id) === String(noteId));
                    if (match) {
                        this.editingNote = {
                            id: match.id,
                            title: match.title,
                            content: decodeHtmlForNote(match.content),
                            is_important: match.is_important == 1,
                            needs_confirmation: match.needs_confirmation == 1,
                            user_id: match.user_id
                        };
                    }
                });
            },
            openNoteModalFromList(projectId, noteContent = null) {
                this.currentNoteProjectId = projectId;
                this.showNoteModal = true;
                this.isNoteEditMode = true;
                this.currentEditingNoteId = null; // No specific note ID for fallback mode
                // Reset editing note
                this.editingNote = {
                    id: null,
                    title: '',
                    content: '',
                    is_important: false,
                    needs_confirmation: true,
                    user_id: null
                };
                this.loadNotesForProject(projectId).then(() => {
                    if (noteContent) {
                        const trimmed = noteContent.trim();
                        const match = this.notes.find(n => (n.content || '').trim() === trimmed && n.needs_confirmation == 1);
                        if (match) {
                            this.editingNote = {
                                id: match.id,
                                title: match.title,
                                content: decodeHtmlForNote(match.content),
                                is_important: match.is_important == 1,
                                needs_confirmation: match.needs_confirmation == 1,
                                user_id: match.user_id
                            };
                        } else {
                            this.editingNote.content = decodeHtmlForNote(noteContent);
                        }
                    }
                });
            },
            closeNoteModal() {
                this.showNoteModal = false;
                this.isNoteEditMode = false;
                this.currentEditingNoteId = null; // Clear editing note tracking
                
                // Refresh table to remove highlight
                if (projectTable) {
                    projectTable.draw(false);
                }
                
                this.editingNote = {
                    id: null,
                    title: '',
                    content: '',
                    is_important: false,
                    needs_confirmation: false,
                    user_id: null
                };
            },
            async saveNote() {
                // Tự động sinh title từ nội dung (ẩn field title khỏi UI)
                const rawContent = (this.editingNote.content || '').trim();
                let title = (this.editingNote.title || '').trim();
                if (!title) {
                    // Lấy dòng đầu tiên của nội dung, giới hạn độ dài
                    title = rawContent.split(/\r?\n/)[0].slice(0, 50) || 'メモ';
                }
                try {
                    const formData = new FormData();
                    formData.append('project_id', this.currentNoteProjectId);
                    formData.append('title', title);
                    formData.append('content', rawContent);
                    formData.append('is_important', this.editingNote.is_important ? 1 : 0);
                    formData.append('needs_confirmation', this.editingNote.needs_confirmation ? 1 : 0);
                    
                    let response;
                    if (this.editingNote.id) {
                        formData.append('id', this.editingNote.id);
                        response = await axios.post('/api/index.php?model=project&method=updateNote', formData);
                    } else {
                        response = await axios.post('/api/index.php?model=project&method=addNote', formData);
                    }
                    
                    if (response.data && response.data.status === 'success') {
                        showMessage('メモが保存されました');
                        this.closeNoteModal();
                        if (projectTable) {
                            projectTable.ajax.reload(null, false);
                        }
                    } else {
                        showMessage('メモの保存に失敗しました', true);
                    }
                } catch (error) {
                    console.error('Error saving note:', error);
                    showMessage('メモの保存に失敗しました', true);
                }
            },
            canEditNote(note) {
                // 案件一覧ではひとまず全ユーザーに編集を許可
                return true;
            },
            async loadUsers() {
                try {
                    const response = await axios.get('/api/index.php?model=department&method=get_users&department_id=' + this.selectedDepartment.id);
                    this.users = response.data || [];
                } catch (error) {
                    console.error('Error loading users:', error);
                }
            },
            async loadTeams() {
                try {
                    const response = await axios.get('/api/index.php?model=team&method=listbydepartment&department_id=' + this.selectedDepartment.id);
                    this.teams = response.data || [];
                    return this.teams;
                } catch (error) {
                    console.error('Error loading teams:', error);
                    return [];
                }
            },
            getOrderTypeBadgeClass(orderType) {
                const type = orderType.trim().toLowerCase();
                switch (type) {
                    case '修正':
                        return 'bg-warning'; // Yellow for edit
                    case '新規':
                        return 'bg-primary'; // Blue for new
                    default:
                        return 'bg-info'; // Gray for unknown types
                }
            },
            viewProjects(department) {
                if (!department || !department.id) {
                    console.error('Invalid department object:', department);
                    return;
                }
                
                this.selectedDepartment = department;
                
                // Save selected department to localStorage
                this.saveSelectedDepartmentToLocalStorage(department);
                
                // Initialize DataTable if not already initialized
                this.$nextTick(() => {
                    initializeProjectTable();
                });
                
                this.loadProjects();

                
                // Load user permissions for the selected department
                this.getUserPermissions(department.id);
                
                // Reset teams and members when department changes
                if (this.teamTagifyInstance) {
                    this.teamTagifyInstance.removeAllTags();
                }
                if (this.membersTagifyInstance) {
                    this.membersTagifyInstance.removeAllTags();
                }
                this.newProject.members = [];
                
                // Reload teams for new department
                this.loadTeams().then(() => {
                    if (this.teamTagifyInstance) {
                        this.teamTagifyInstance.whitelist = this.teams.map(team => ({
                            id: team.id,
                            value: team.name,
                            name: team.name
                        }));
                    }
                    // Populate team filter options based on this.teams (only current department)
                    const $teamFilter = $('#filterTeam');
                    if ($teamFilter && $teamFilter.length) {
                        const saved = JSON.parse(localStorage.getItem(FILTER_STORAGE_KEY) || '{}');
                        const savedTeam = saved.filterTeam || '';
                        $teamFilter.empty();
                        $teamFilter.append('<option value="">すべて</option>');
                        // Option: 未割り当て (chưa phân công team)
                        const noneSelected = savedTeam && savedTeam === 'none' ? ' selected' : '';
                        $teamFilter.append('<option value="none"' + noneSelected + '>未割り当て</option>');
                        (this.teams || []).forEach(team => {
                            if (team.id != null) {
                                const id = String(team.id);
                                const name = team.name || id;
                                const selected = savedTeam && String(savedTeam) === id ? ' selected' : '';
                                $teamFilter.append('<option value="' + id + '"' + selected + '>' + name + '</option>');
                            }
                        });
                    }
                });
                this.loadUsers().then(() => {
                    if (this.membersTagifyInstance) {
                        this.membersTagifyInstance.whitelist = this.users.map(user => ({
                            id: user.id,
                            value: user.user_name,
                            name: user.user_name
                        }));
                    }
                    if (this.managerTagifyInstance) {
                        this.managerTagifyInstance.whitelist = this.users.map(user => ({
                            id: user.id,
                            value: user.user_name,
                            name: user.user_name
                        }));
                    }
                });
                
                // Reload kadai projects for the new department
               // this.loadKadaiProjects();
            },
            filterProjectByStatus(status) {
                this.selectedStatus = status;
                this.loadProjects();
                // Lưu trạng thái status + các filter khác vào localStorage và cập nhật URL / badge
                if (typeof saveFiltersToLocalStorage === 'function') {
                    saveFiltersToLocalStorage();
                }
                renderActiveFilters();
            },
            onFavoritesFilterChange() {
                const isChecked = $('#filterFavoritesOnly').is(':checked');
                this.showClearAllFavoritesBtn = isChecked;
                if (projectTable) {
                    projectTable.ajax.reload();
                }
            },
            async toggleProjectFavorite(projectId, element) {
                try {
                    const formData = new FormData();
                    formData.append('project_id', projectId);
                    
                    const response = await axios.post('/api/index.php?model=project&method=toggleFavorite', formData);
                    
                    if (response.data && response.data.status === 'success') {
                        // Update icon appearance
                        const isFavorite = response.data.is_favorite;
                        $(element).toggleClass('text-warning', isFavorite).toggleClass('text-muted', !isFavorite);
                        $(element).attr('title', isFavorite ? 'お気に入りから削除' : 'お気に入りに追加');
                        
                        // Update row data if table exists
                        if (projectTable) {
                            const row = $(element).closest('tr');
                            const rowData = projectTable.row(row).data();
                            if (rowData) {
                                rowData.is_favorite = isFavorite ? 1 : 0;
                            }
                        }
                    } else {
                        showMessage(response.data?.message || '操作に失敗しました。', true);
                    }
                } catch (error) {
                    console.error('Error toggling favorite:', error);
                    showMessage('操作に失敗しました。', true);
                }
            },
            async clearAllFavorites() {
                try {
                    const result = await Swal.fire({
                        title: '確認',
                        text: 'すべてのお気に入りを削除しますか？',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: '削除',
                        cancelButtonText: 'キャンセル'
                    });
                    
                    if (result.isConfirmed) {
                        const response = await axios.post('/api/index.php?model=project&method=clearAllFavorites');
                        
                        if (response.data && response.data.status === 'success') {
                            // Uncheck the favorites filter
                            $('#filterFavoritesOnly').prop('checked', false);
                            this.showClearAllFavoritesBtn = false;
                            // Reload the table
                            if (projectTable) {
                                projectTable.ajax.reload();
                            }
                        } else {
                            showMessage(response.data?.message || '削除に失敗しました。', true);
                        }
                    }
                } catch (error) {
                    console.error('Error clearing all favorites:', error);
                    showMessage('削除に失敗しました。', true);
                }
            },
            async loadProjects() {
                try {
                    // Ensure DataTable is initialized before reloading
                    if (!projectTable || !$.fn.DataTable.isDataTable('#projectTable')) {
                        initializeProjectTable();
                    } else {
                        projectTable.ajax.reload();
                    }
                } catch (error) {
                    console.error('Error loading projects:', error);
                }
            },
            openNewProjectModal() {
                this.isEdit = false;
                this.editingId = null;
                this.resetProjectForm();
                if (this.selectedDepartment) {
                    this.newProject.department_id = this.selectedDepartment.id;
                }
                const modal = new bootstrap.Modal(document.getElementById('newProjectModal'));
                modal.show();
            },
            editProject(project) {
                this.isEdit = true;
                this.editingId = project.id;
                this.newProject = {
                    name: project.name || '',
                    description: project.description || '',
                    status: project.status || 'draft',
                    priority: project.priority || 'medium',
                    start_date: project.start_date || '',
                    end_date: project.end_date || '',
                    members: [],
                    department_id: project.department_id || '',
                    building_size: project.building_size || '',
                    building_type: project.building_type || '',
                    project_number: project.project_number || '',
                    project_order_type: project.project_order_type || ['new'],
                    estimated_hours: project.estimated_hours || '',
                    amount: project.amount || '',
                    teams: project.teams || '',
                    customer_id: project.customer_id || '',
                    company_name: project.company_name || '',
                    branch_id: project.branch_id || '',
                    contact_name: project.contact_name || '',
                    contact_phone: project.contact_phone || ''
                };
                const modal = new bootstrap.Modal(document.getElementById('newProjectModal'));
                modal.show();
            },
            async saveProject() {
                if (!this.formValidator) {
                    console.error('Form validator not initialized');
                    return;
                }

                // Check field visibility and disable validation for hidden fields
                const companyNameVisible = $('#company_name').closest('.form-group').is(':visible');
                const customerIdVisible = $('#customer_id').closest('.form-group').is(':visible');

                // Store original validation state
                const originalValidators = {};
                
                if (!companyNameVisible) {
                    originalValidators.company_name = true;
                    this.formValidator.disableValidator('company_name');
                }
                if (!customerIdVisible) {
                    originalValidators.customer_id = true;
                    this.formValidator.disableValidator('customer_id');
                }

                // Validate the form
                const status = await this.formValidator.validate();

                // Restore original validation state
                Object.keys(originalValidators).forEach(field => {
                    this.formValidator.enableValidator(field);
                });

                if (status === 'Valid') {
                    try {
                        const formData = new FormData();
                        formData.append('model', 'project');
                        formData.append('method', this.isEdit ? 'edit' : 'add');
                        
                        if (this.isEdit) {
                            formData.append('id', this.editingId);
                        }
                        
                        // Get and validate Select2 values
                        const categorySelect = $('#category_id');
                        const companySelect = $('#company_name');
                        const customerSelect = $('#customer_id');
                        
                        const categoryId = categorySelect.select2('data')[0]?.id || '';
                        const companyName = companySelect.select2('data')[0]?.id || '';
                        const customerId = customerSelect.select2('data')[0]?.id || '';
                        
                        // Update newProject with Select2 values
                        this.newProject.category_id = categoryId;
                        this.newProject.company_name = companyName;
                        this.newProject.customer_id = customerId;
                        
                        // Get team data from Tagify
                        const teamData = this.teamTagifyInstance ? this.teamTagifyInstance.value.map(tag => tag.id || tag.value) : [];
                        const membersData = this.membersTagifyInstance ? this.membersTagifyInstance.value.map(tag => tag.id || tag.value) : [];
                        const managerData = this.managerTagifyInstance ? this.managerTagifyInstance.value.map(tag => tag.id || tag.value) : [];
                        
                        // Append all project data
                        Object.keys(this.newProject).forEach(key => {
                            if (key === 'members' && Array.isArray(this.newProject[key])) {
                                // Use membersData from Tagify instead of this.newProject.members
                                membersData.forEach(member => {
                                    formData.append('members[]', member);
                                });
                            } else if (key === 'team') {
                                // Add team data from Tagify
                                teamData.forEach(team => {
                                    formData.append('team[]', team);
                                });
                            } else if (key === 'manager') {
                                // Add manager data from Tagify
                                managerData.forEach(manager => {
                                    formData.append('manager[]', manager);
                                });
                            } else if (key === 'project_order_type' && this.tagifyInstance) {
                                const tags = this.tagifyInstance.value.map(tag => tag.value);
                                formData.append(key, JSON.stringify(tags));
                            } else {
                                formData.append(key, this.newProject[key] || '');
                            }
                        });
                        
                        // Explicitly add Select2 values to ensure they're included
                        if (categoryId) {
                            formData.set('category_id', categoryId);
                        }
                        if (companyName) {
                            formData.set('company_name', companyName);
                        }
                        if (customerId) {
                            formData.set('customer_id', customerId);
                        }


                        const response = await axios.post('/api/index.php?model=project&method=add', formData);
                        
                        if (response.data.status == 'success') {
                            showMessage(this.isEdit ? 'プロジェクトを更新しました。' : 'プロジェクトを作成しました。');
                            bootstrap.Modal.getInstance(document.getElementById('newProjectModal')).hide();
                            this.loadProjects();
                            this.resetProjectForm();
                        } else{
                            showMessage('プロジェクトの保存に失敗しました。', true);
                        }
                    } catch (error) {
                        console.error('Error saving project:', error);
                        showMessage('プロジェクトの保存に失敗しました。', true);
                    }
                }
            },
            deleteProject(id) {
                this.deletingId = id;
                const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
                modal.show();
            },
            async confirmDelete() {
                try {
                    const formData = new FormData();
                    formData.append('model', 'project');
                    formData.append('method', 'delete');
                    formData.append('id', this.deletingId);
                    
                    const response = await axios.post('/api/index.php?model=project&method=delete', formData);
                    
                    if (response.data) {
                        showMessage('プロジェクトを削除しました。');
                        bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                        this.loadProjects();
                    }
                } catch (error) {
                    console.error('Error deleting project:', error);
                    if (error.response?.data?.error) {
                        showMessage(error.response.data.error, true);
                    } else {
                        showMessage('プロジェクトの削除に失敗しました。', true);
                    }
                }
            },
            resetProjectForm() {
                this.newProject = {
                    name: '',
                    description: '',
                    status: 'draft',
                    priority: 'medium',
                    start_date: '',
                    end_date: '',
                    team: [],
                    members: [],
                    manager: [],
                    department_id: '',
                    building_size: '',
                    building_type: '',
                    building_number: '',
                    building_branch: '',
                    project_number: '',
                    project_order_type: '新規',
                    estimated_hours: '',
                    amount: '',
                    customer_id: '',
                    company_name: '',
                    branch_id: '',
                    contact_name: '',
                    contact_phone: ''
                };
                this.companies = [];
                this.contacts = [];
                
                // Reset Tagify instances
                if (this.tagifyInstance) {
                    this.tagifyInstance.removeAllTags();
                    this.tagifyInstance.addTags(['新規']);
                }
                if (this.teamTagifyInstance) {
                    this.teamTagifyInstance.removeAllTags();
                }
                if (this.membersTagifyInstance) {
                    this.membersTagifyInstance.removeAllTags();
                }
                if (this.managerTagifyInstance) {
                    this.managerTagifyInstance.removeAllTags();
                }

                // Reset Select2 dropdowns
                $('#category_id').val(null).trigger('change');
                $('#company_name').val(null).trigger('change');
                $('#customer_id').val(null).trigger('change');

                // Hide dependent fields
                $('#company_name').closest('.form-group').hide();
                $('#customer_id').closest('.form-group').hide();

                // Reset form validation
                if (this.formValidator) {
                    this.formValidator.resetForm();
                    this.formValidator.disableValidator('company_name');
                    this.formValidator.disableValidator('customer_id');
                }
            },
            async loadCategories() {
                try {
                    const response = await axios.get('/api/index.php?model=customer&method=list_categories');
                    if (response.data && response.data.data) {
                        this.categories = response.data.data;
                    }
                } catch (error) {
                    console.error('Error loading categories:', error);
                }
            },
            async loadCompanies() {
                try {
                    const response = await axios.get('/api/index.php?model=customer&method=list_companies');
                    if (response.data && response.data.data) {
                        this.companies = response.data.data;
                    }
                } catch (error) {
                    console.error('Error loading companies:', error);
                }
            },
            async loadContacts() {
                try {
                    const response = await axios.get('/api/index.php?model=customer&method=list_contacts');
                    if (response.data && response.data.data) {
                        this.contacts = response.data.data;
                    }
                } catch (error) {
                    console.error('Error loading contacts:', error);
                }
            },
            async onCategoryChange() {
                if (this.newProject.category_id) {
                    try {
                        const response = await axios.get(`/api/index.php?model=customer&method=list_companies_by_category&category_id=${this.newProject.category_id}`);
                        if (response.data && response.data.data) {
                            this.companies = response.data.data;
                            // Reset company and contact selections
                            this.newProject.company_name = '';
                            this.newProject.customer_id = '';
                            this.contacts = [];
                        }
                    } catch (error) {
                        console.error('Error loading companies:', error);
                        this.companies = [];
                    }
                } else {
                    this.companies = [];
                    this.newProject.company_name = '';
                    this.newProject.customer_id = '';
                    this.contacts = [];
                }
            },
            async onCompanyChange() {
                if (this.newProject.company_name) {
                    try {
                        const response = await axios.get(`/api/index.php?model=customer&method=list_contacts_by_company&company_name=${encodeURIComponent(this.newProject.company_name)}`);
                        if (response.data && response.data.data) {
                            this.contacts = response.data.data;
                            // Reset contact selection
                            this.newProject.customer_id = '';
                        }
                    } catch (error) {
                        console.error('Error loading contacts:', error);
                        this.contacts = [];
                    }
                } else {
                    this.contacts = [];
                    this.newProject.customer_id = '';
                }
            },
            openNewCustomerModal() {
                // Close dropdown of select2
                $('#customer_id').select2('close');
                // Open new customer modal
                const modal = new bootstrap.Modal(document.getElementById('newCustomerModal'));
                modal.show();
            },
            async loadTeamMembers(teamId) {
                try {
                    const response = await axios.get(`/api/index.php?model=team&method=get&id=${teamId}`);
                    if (response.data && response.data.members && response.data.whitelist) {
                        const members = response.data.members.map(member => ({
                            id: member.user_id,
                            value: member.user_name,
                            name: member.user_name
                        }));
                        
                        if (this.membersTagifyInstance) {
                            this.membersTagifyInstance.addTags(members);
                            this.newProject.members = [...this.newProject.members, ...members.map(m => m.id)];
                        }
                    }
                } catch (error) {
                    console.error('Error loading team members:', error);
                }
            },
            
            // Kadai Queue Methods
            async loadKadaiProjects() {
                // Chỉ load dự án khi có department được chọn
                if (!this.selectedDepartment || !this.selectedDepartment.id) {
                    this.kadaiProjects = [];
                    return;
                }
                
                try {
                    const url = `/api/index.php?model=project&method=list_kadai&department_id=${this.selectedDepartment.id}`;
                    const response = await axios.get(url);
                    if (response.data && response.data.data) {
                        this.kadaiProjects = response.data.data;
                    } else {
                        this.kadaiProjects = [];
                    }
                } catch (error) {
                    console.error('Error loading kadai projects:', error);
                    this.kadaiProjects = [];
                }
            },
            
            toggleKadaiQueue() {
                this.isKadaiQueueExpanded = !this.isKadaiQueueExpanded;
            },
            
            async refreshKadaiQueue() {
                await this.loadKadaiProjects();
            },
            
            getStatusBadgeClass(status) {
                const statusObj = this.statuses.find(s => s.key === status);
                return statusObj ? `bg-${statusObj.color}` : 'bg-secondary';
            },
            
            getStatusName(status) {
                const statusObj = this.statuses.find(s => s.key === status);
                return statusObj ? statusObj.name : status;
            },
            
            formatDate(dateString) {
                if (!dateString) return '-';
                return moment(dateString).format('M/D H:mm');
            },
            
            async moveToMainProject(project) {
                try {
                    const formData = new FormData();
                    formData.append('model', 'project');
                    formData.append('method', 'edit');
                    formData.append('id', project.id);
                    formData.append('is_kadai', '0');
                    
                    const response = await axios.post('/api/index.php?model=project&method=edit', formData);
                    
                    if (response.data.status === 'success') {
                        showMessage('プロジェクトをメインプロジェクトに移動しました。');
                        // Refresh both lists
                        await this.loadKadaiProjects();
                        if (projectTable) {
                            projectTable.ajax.reload();
                        }
                    } else {
                        showMessage('プロジェクトの移動に失敗しました。', true);
                    }
                } catch (error) {
                    console.error('Error moving project:', error);
                    showMessage('プロジェクトの移動に失敗しました。', true);
                }
            },
            
            toggleColumnVisibility(columnKey, event) {
                const isVisible = event.target.checked;
                const column = this.availableColumns.find(col => col.key === columnKey);
                if (column) {
                    column.visible = isVisible;
                }
                
                // Save to localStorage
                const visibility = {};
                this.availableColumns.forEach(col => {
                    visibility[col.key] = col.visible;
                });
                saveColumnVisibilityToLocalStorage(visibility);
                
                // Apply to DataTable if it exists
                if (projectTable && $.fn.DataTable.isDataTable('#projectTable')) {
                    const colDef = COLUMN_DEFINITIONS.find(col => col.key === columnKey);
                    if (colDef) {
                        projectTable.column(colDef.index).visible(isVisible, false);
                        projectTable.columns.adjust().draw(false);
                    }
                }
            },
            
            async confirmProject(project) {
                try {
                    // Hiển thị confirm dialog
                    if (!confirm(`プロジェクト「${project.name}」を承認しますか？\n\nこの操作により、プロジェクトは課題案件から削除され、メインプロジェクトリストに表示されます。`)) {
                        return;
                    }
                    
                    const formData = new FormData();
                    formData.append('model', 'project');
                    formData.append('method', 'confirm');
                    formData.append('id', project.id);
                    
                    const response = await axios.post('/api/index.php?model=project&method=confirm', formData);
                    
                    if (response.data.status === 'success') {
                        showMessage('プロジェクトを承認しました。課題案件から削除されました。');
                        // Refresh both lists
                        await this.loadKadaiProjects();
                        if (projectTable) {
                            projectTable.ajax.reload();
                        }
                    } else {
                        showMessage(response.data.message || 'プロジェクトの承認に失敗しました。', true);
                    }
                } catch (error) {
                    console.error('Error confirming project:', error);
                    showMessage('プロジェクトの承認に失敗しました。', true);
                }
            },
            

        },
        watch: {
            filterMyProjects(newVal) {
                // Save to localStorage when filter changes
                saveFiltersToLocalStorage();
            }
        }
    }).mount('#app');

    window.app = app;

    // Global function for toggling favorite from DataTable render
    window.toggleProjectFavorite = async function(projectId, element) {
        try {
            const formData = new FormData();
            formData.append('project_id', projectId);
            
            const response = await axios.post('/api/index.php?model=project&method=toggleFavorite', formData);
            
            if (response.data && response.data.status === 'success') {
                // Update icon appearance
                const isFavorite = response.data.is_favorite;
                $(element).toggleClass('text-warning', isFavorite).toggleClass('text-muted', !isFavorite);
                $(element).attr('title', isFavorite ? 'お気に入りから削除' : 'お気に入りに追加');
                
                // Update row data if table exists
                if (projectTable) {
                    const row = $(element).closest('tr');
                    const rowData = projectTable.row(row).data();
                    if (rowData) {
                        rowData.is_favorite = isFavorite ? 1 : 0;
                    }
                }
            } else {
                showMessage(response.data?.message || '操作に失敗しました。', true);
            }
        } catch (error) {
            console.error('Error toggling favorite:', error);
            showMessage('操作に失敗しました。', true);
        }
    };
