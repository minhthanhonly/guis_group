var gantt;
var projectData = [];
var teamIdToName = {};

function isCailyBranchUser() {
    return typeof window !== 'undefined' && window.IS_CAILY_BRANCH_USER === true;
}
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
        key: 'waiting_documents',
        name: '資料待ち',
        color: 'warning'
    },
    {
        key: 'in_progress',
        name: '進行中',
        color: 'primary'
    },
    {
        key: 'completed',
        name: '完了',
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
    const KEEP_TEAM_ON_RESET_KEY = 'project_list_keep_team_on_reset';
    const KEEP_COMPANY_ON_RESET_KEY = 'project_list_keep_company_on_reset';
    const SELECTED_DEPARTMENT_KEY = 'projectListSelectedDepartment'; // Dùng chung với project-list.js

    function ganttTranslateText(key) {
        if (typeof translateText === 'function') {
            return translateText(key);
        }
        return key;
    }

    function parseFilterTeamValue(raw) {
        if (raw === undefined || raw === null || raw === '') {
            return [];
        }
        if (Array.isArray(raw)) {
            return raw.map(String).filter(Boolean);
        }
        return String(raw).split(',').map(function(s) { return s.trim(); }).filter(Boolean);
    }

    function getFilterTeamValue() {
        const $el = $('#filterTeam');
        if (!$el.length) {
            return [];
        }
        return parseFilterTeamValue($el.val());
    }

    function formatFilterTeamForApi(teamIds) {
        const ids = parseFilterTeamValue(teamIds);
        return ids.length ? ids.join(',') : '';
    }

    function parseFilterCompanyValue(raw) {
        if (raw === undefined || raw === null || raw === '') {
            return [];
        }
        if (Array.isArray(raw)) {
            return raw.map(String).map(function(s) { return s.trim(); }).filter(Boolean);
        }
        return String(raw).split(',').map(function(s) { return s.trim(); }).filter(Boolean);
    }

    function getFilterCompanyValue() {
        const $el = $('#filterCompany');
        if (!$el.length) {
            return [];
        }
        return parseFilterCompanyValue($el.val());
    }

    function formatFilterCompanyForApi(companyKeys) {
        const keys = parseFilterCompanyValue(companyKeys);
        return keys.length ? keys.join(',') : '';
    }

    function loadKeepTeamOnResetFromStorage() {
        try {
            return localStorage.getItem(KEEP_TEAM_ON_RESET_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function saveKeepTeamOnResetToStorage(checked) {
        try {
            localStorage.setItem(KEEP_TEAM_ON_RESET_KEY, checked ? '1' : '0');
        } catch (e) {}
    }

    function initFilterKeepTeamOnResetCheckbox() {
        const $cb = $('#filterKeepTeamOnReset');
        if (!$cb.length) {
            return;
        }
        $cb.prop('checked', loadKeepTeamOnResetFromStorage());
        $cb.off('change.keepTeamOnReset').on('change.keepTeamOnReset', function() {
            saveKeepTeamOnResetToStorage($(this).is(':checked'));
        });
    }

    function loadKeepCompanyOnResetFromStorage() {
        try {
            return localStorage.getItem(KEEP_COMPANY_ON_RESET_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function saveKeepCompanyOnResetToStorage(checked) {
        try {
            localStorage.setItem(KEEP_COMPANY_ON_RESET_KEY, checked ? '1' : '0');
        } catch (e) {}
    }

    function initFilterKeepCompanyOnResetCheckbox() {
        const $cb = $('#filterKeepCompanyOnReset');
        if (!$cb.length) {
            return;
        }
        $cb.prop('checked', loadKeepCompanyOnResetFromStorage());
        $cb.off('change.keepCompanyOnReset').on('change.keepCompanyOnReset', function() {
            saveKeepCompanyOnResetToStorage($(this).is(':checked'));
        });
    }

    function bindFilterCompanySelect2Events($el) {
        $el.off('select2:open.filterCompany').on('select2:open.filterCompany', function() {
            setTimeout(function() {
                const searchField = document.querySelector('.select2-container--open .select2-search__field');
                if (!searchField) {
                    return;
                }
                searchField.value = '';
                searchField.dispatchEvent(new Event('input', { bubbles: true }));
            }, 0);
        });
    }

    function refreshFilterCompanySelect() {
        const $el = $('#filterCompany');
        if (!$el.length) {
            return;
        }
        let filters = {};
        try {
            filters = JSON.parse(localStorage.getItem(FILTER_STORAGE_KEY) || '{}');
        } catch (e) {
            filters = {};
        }
        const allowedCompanyKeys = { daito: true, token: true, other: true };
        const rawSaved = parseFilterCompanyValue(filters.filterCompany);
        const saved = rawSaved.filter(function(key) { return allowedCompanyKeys[key]; });
        if (rawSaved.length !== saved.length) {
            try {
                const stored = JSON.parse(localStorage.getItem(FILTER_STORAGE_KEY) || '{}');
                stored.filterCompany = saved;
                localStorage.setItem(FILTER_STORAGE_KEY, JSON.stringify(stored));
            } catch (e) {}
        }
        if ($el.data('select2')) {
            $el.select2('destroy');
        }
        $el.empty();
        $el.append(new Option('大東', 'daito', false, saved.indexOf('daito') !== -1));
        $el.append(new Option('東建', 'token', false, saved.indexOf('token') !== -1));
        $el.append(new Option('他社', 'other', false, saved.indexOf('other') !== -1));
        $el.select2({
            placeholder: ganttTranslateText('会社'),
            allowClear: true,
            width: '100%',
            dropdownAutoWidth: true,
            closeOnSelect: false,
            minimumResultsForSearch: 0
        });
        bindFilterCompanySelect2Events($el);
        $el.val(saved.length ? saved : null).trigger('change');
    }

    function syncFilterTeamSelect2Value($el, teamIds) {
        const ids = parseFilterTeamValue(teamIds);
        $el.val(ids.length ? ids : null).trigger('change');
    }

    function bindFilterTeamSelect2Events($el) {
        $el.off('select2:open.filterTeam').on('select2:open.filterTeam', function() {
            setTimeout(function() {
                const searchField = document.querySelector('.select2-container--open .select2-search__field');
                if (!searchField) {
                    return;
                }
                searchField.value = '';
                searchField.dispatchEvent(new Event('input', { bubbles: true }));
            }, 0);
        });
    }

    function refreshFilterTeamSelect(teams) {
        const $el = $('#filterTeam');
        if (!$el.length) {
            return;
        }
        let filters = {};
        try {
            filters = JSON.parse(localStorage.getItem(FILTER_STORAGE_KEY) || '{}');
        } catch (e) {
            filters = {};
        }
        const teamIdSet = { none: true };
        (teams || []).forEach(function(team) {
            if (team.id != null) {
                teamIdSet[String(team.id)] = true;
            }
        });
        const rawSaved = parseFilterTeamValue(filters.filterTeam);
        const saved = rawSaved.filter(function(id) {
            return teamIdSet[id];
        });
        if (rawSaved.length !== saved.length) {
            try {
                const stored = JSON.parse(localStorage.getItem(FILTER_STORAGE_KEY) || '{}');
                stored.filterTeam = saved;
                localStorage.setItem(FILTER_STORAGE_KEY, JSON.stringify(stored));
            } catch (e) {}
        }
        if ($el.data('select2')) {
            $el.select2('destroy');
        }
        $el.empty();
        $el.append(new Option(ganttTranslateText('未割り当て'), 'none', false, saved.indexOf('none') !== -1));
        (teams || []).forEach(function(team) {
            if (team.id != null) {
                const id = String(team.id);
                const name = team.name || id;
                $el.append(new Option(name, id, false, saved.indexOf(id) !== -1));
            }
        });
        $el.select2({
            placeholder: ganttTranslateText('すべて'),
            allowClear: true,
            width: '100%',
            dropdownAutoWidth: true,
            closeOnSelect: false,
            language: {
                noResults: function() {
                    return ganttTranslateText('検索結果がありません');
                }
            }
        });
        bindFilterTeamSelect2Events($el);
        syncFilterTeamSelect2Value($el, saved);
    }

    function saveFiltersToLocalStorage() {
        const filters = {
            filterPriority: $('#filterPriority').val(),
            filterProgress: $('#filterProgress').val(),
            filterTimeLeft: $('#filterTimeLeft').val(),
            filterProjectOrderType: $('#filterProjectOrderType').val(),
            filterTeam: getFilterTeamValue(),
            filterCompany: getFilterCompanyValue(),
            filterTantou: $('#filterTantou').val(),
            filterNoDates: $('#filterNoDates').is(':checked') ? 1 : 0,
            showInactive: $('#showInactiveSwitch').is(':checked') ? 1 : 0,
            myProjects: $('#filterMyProjects').is(':checked') ? 1 : 0,
            showTaskText: $('#toggleTaskText').is(':checked') ? 1 : 0,
            showTaskTree: $('#toggleTaskTree').is(':checked') ? 1 : 0,
            useCailyEndDate: $('#useCailyEndDate').is(':checked') ? 1 : 0,
            useGuisEndDate: isCailyBranchUser() ? 0 : ($('#useGuisEndDate').is(':checked') ? 1 : 0),
            useEndDate: isCailyBranchUser() ? 0 : ($('#useEndDate').is(':checked') ? 1 : 0),
            useShowCailyStruct: $('#useShowCailyStruct').is(':checked') ? 1 : 0,
            useShowGuisStruct: $('#useShowGuisStruct').is(':checked') ? 1 : 0,
            useShowEquipmentNouki: $('#useShowEquipmentNouki').is(':checked') ? 1 : 0,
            useShowEquipmentNouki: $('#useShowEquipmentNouki').is(':checked') ? 1 : 0,
            filterKeyword: $('#filterKeyword').val(),
            filterProjectId: $('#filterProjectId').val(),
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
        if (filters.filterCompany !== undefined) {
            const companyValues = parseFilterCompanyValue(filters.filterCompany);
            $('#filterCompany').val(companyValues.length ? companyValues : null).trigger('change');
        }
        // filterTeam: refreshFilterTeamSelect() khôi phục từ localStorage sau khi load teams
        if (filters.filterTantou !== undefined) $('#filterTantou').val(filters.filterTantou);
        if (filters.filterNoDates !== undefined) $('#filterNoDates').prop('checked', filters.filterNoDates == 1);
        if (filters.showInactive !== undefined) $('#showInactiveSwitch').prop('checked', filters.showInactive == 1);
        if (filters.myProjects !== undefined) $('#filterMyProjects').prop('checked', filters.myProjects == 1);
        if (filters.showTaskText !== undefined) $('#toggleTaskText').prop('checked', filters.showTaskText == 1);
        if (filters.showTaskTree !== undefined) $('#toggleTaskTree').prop('checked', filters.showTaskTree == 1);
        if (filters.useCailyEndDate !== undefined) $('#useCailyEndDate').prop('checked', filters.useCailyEndDate == 1);
        if (!isCailyBranchUser() && filters.useGuisEndDate !== undefined) $('#useGuisEndDate').prop('checked', filters.useGuisEndDate == 1);
        if (!isCailyBranchUser() && filters.useEndDate !== undefined) $('#useEndDate').prop('checked', filters.useEndDate == 1);
        if (filters.useShowCailyStruct !== undefined) $('#useShowCailyStruct').prop('checked', filters.useShowCailyStruct == 1);
        if (filters.useShowGuisStruct !== undefined) $('#useShowGuisStruct').prop('checked', filters.useShowGuisStruct == 1);
        if (filters.useShowEquipmentNouki !== undefined) $('#useShowEquipmentNouki').prop('checked', filters.useShowEquipmentNouki == 1);
        if (filters.useShowEquipmentNouki !== undefined) $('#useShowEquipmentNouki').prop('checked', filters.useShowEquipmentNouki == 1);
        if (filters.filterKeyword !== undefined) $('#filterKeyword').val(filters.filterKeyword);
        if (filters.filterProjectId !== undefined) $('#filterProjectId').val(filters.filterProjectId);
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
            teamIds: parseFilterTeamValue(filters.filterTeam),
            companyKeys: parseFilterCompanyValue(filters.filterCompany),
            tantou: filters.filterTantou || '',
            noDates: filters.filterNoDates == 1,
            keyword: filters.filterKeyword || '',
            projectId: filters.filterProjectId || '',
            showInactive: filters.showInactive == 1,
            myProjects: filters.myProjects == 1,
            showTaskText: filters.showTaskText == 1,
            showTaskTree: filters.showTaskTree == 1,
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
        setOrDelete('filterTeam', formatFilterTeamForApi(filters.filterTeam));
        setOrDelete('filterCompany', formatFilterCompanyForApi(filters.filterCompany));
        setOrDelete('filterTantou', filters.filterTantou);
        setOrDelete('filterNoDates', filters.filterNoDates ? 1 : '');
        setOrDelete('showInactive', filters.showInactive ? 1 : '');
        setOrDelete('my_projects', filters.myProjects ? 1 : '');
        setOrDelete('filterKeyword', filters.filterKeyword);
        setOrDelete('filterProjectId', filters.filterProjectId);
        setOrDelete('status', filters.statusKey);
        setOrDelete('showTaskText', filters.showTaskText ? 1 : '');
        setOrDelete('showTaskTree', filters.showTaskTree ? 1 : '');
        setOrDelete('useCailyEndDate', filters.useCailyEndDate ? 1 : '');
        setOrDelete('useGuisEndDate', filters.useGuisEndDate ? 1 : '');
        setOrDelete('useEndDate', filters.useEndDate ? 1 : '');
        setOrDelete('useShowCailyStruct', filters.useShowCailyStruct ? 1 : '');
        setOrDelete('useShowGuisStruct', filters.useShowGuisStruct ? 1 : '');
        setOrDelete('useShowEquipmentNouki', filters.useShowEquipmentNouki ? 1 : '');
        setOrDelete('useShowEquipmentNouki', filters.useShowEquipmentNouki ? 1 : '');
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
            (!filters.teamIds || filters.teamIds.length === 0) &&
            (!filters.companyKeys || filters.companyKeys.length === 0) &&
            (!filters.tantou || filters.tantou.trim() === '') &&
            !filters.noDates &&
            !filters.showInactive &&
            !filters.myProjects &&
            (!filters.keyword || filters.keyword.trim() === '') &&
            (!filters.projectId || filters.projectId.trim() === '')
        ) {
            $('#activeFilters').html('');
            return;
        }

        if (filters.keyword && filters.keyword.trim() !== '') {
            badges.push(`<span class="badge bg-label-info me-1">キーワード: ${filters.keyword}</span>`);
        }
        if (filters.projectId && filters.projectId.trim() !== '') {
            badges.push(`<span class="badge bg-label-info me-1">案件ID: ${filters.projectId}</span>`);
        }
        if (filters.keyword && filters.keyword.trim() !== '') {
            // keyword only: đã thêm badge ở trên
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
            if (filters.teamIds && filters.teamIds.length > 0) {
                const teamNames = filters.teamIds.map(function(id) {
                    if (id === 'none') {
                        return ganttTranslateText('未割り当て');
                    }
                    return teamIdToName[id] || id;
                }).join(', ');
                badges.push(`<span class="badge bg-label-info me-1">チーム: ${teamNames}</span>`);
            }
            if (filters.companyKeys && filters.companyKeys.length > 0) {
                const companyLabels = filters.companyKeys.map(function(key) {
                    if (key === 'daito') return '大東';
                    if (key === 'token') return '東建';
                    if (key === 'other') return '他社';
                    return key;
                }).join(', ');
                badges.push(`<span class="badge bg-label-info me-1">会社: ${companyLabels}</span>`);
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
        if (params.has('filterTeam')) merged.filterTeam = parseFilterTeamValue(params.get('filterTeam') || '');
        if (params.has('filterCompany')) merged.filterCompany = parseFilterCompanyValue(params.get('filterCompany') || '');
        if (params.has('filterTantou')) merged.filterTantou = params.get('filterTantou') || '';
        if (params.has('filterNoDates')) merged.filterNoDates = getBool('filterNoDates');
        if (params.has('showInactive')) merged.showInactive = getBool('showInactive');
        if (params.has('my_projects')) merged.myProjects = getBool('my_projects');
        if (params.has('filterKeyword')) merged.filterKeyword = params.get('filterKeyword') || '';
        if (params.has('filterProjectId')) merged.filterProjectId = params.get('filterProjectId') || '';
        if (params.has('status')) merged.statusKey = params.get('status') || '';
        if (params.has('showTaskText')) merged.showTaskText = getBool('showTaskText');
        if (params.has('showTaskTree')) merged.showTaskTree = getBool('showTaskTree');
        if (params.has('useCailyEndDate')) merged.useCailyEndDate = getBool('useCailyEndDate');
        if (params.has('useGuisEndDate')) merged.useGuisEndDate = getBool('useGuisEndDate');
        if (params.has('useEndDate')) merged.useEndDate = getBool('useEndDate');
        if (params.has('useShowCailyStruct')) merged.useShowCailyStruct = getBool('useShowCailyStruct');
        if (params.has('useShowGuisStruct')) merged.useShowGuisStruct = getBool('useShowGuisStruct');
        if (params.has('useShowEquipmentNouki')) merged.useShowEquipmentNouki = getBool('useShowEquipmentNouki');
        if (params.has('useShowEquipmentNouki')) merged.useShowEquipmentNouki = getBool('useShowEquipmentNouki');

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
    // Trạng thái mở/đóng toàn bộ tree subtask
    window.ganttTreeOpen = $('#toggleTaskTree').is(':checked');

    function applyTreeToggleState() {
        if (!gantt || typeof gantt.eachTask !== 'function') return;
        const shouldOpen = !!window.ganttTreeOpen;
        gantt.batchUpdate(function() {
            gantt.eachTask(function(task) {
                if (!gantt.hasChild(task.id)) return;
                if (shouldOpen) gantt.open(task.id);
                else gantt.close(task.id);
            });
        });
        gantt.render();
    }

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
    $(document).on('change', '#toggleTaskTree', function() {
        saveFiltersToLocalStorage();
        window.ganttTreeOpen = $('#toggleTaskTree').is(':checked');
        applyTreeToggleState();
        renderActiveFilters();
    });
    // Checkbox ẩn/hiện milestone CAILY納期・GUIS納期・構造データ送付 (độc lập)
    $(document).on('change', '#useCailyEndDate, #useGuisEndDate, #useEndDate, #useShowCailyStruct, #useShowGuisStruct, #useShowEquipmentNouki', function() {
        saveFiltersToLocalStorage();
        if (window.ganttApp && typeof window.ganttApp.loadProjects === 'function') {
            window.ganttApp.loadProjects();
        } else if (gantt) {
            gantt.render();
        }
    });
    initFilterKeepTeamOnResetCheckbox();
    initFilterKeepCompanyOnResetCheckbox();

    $(document).on('click', '#filterReset', function() {
        const keepTeam = $('#filterKeepTeamOnReset').is(':checked');
        const preservedTeams = keepTeam ? getFilterTeamValue() : [];
        const keepCompany = $('#filterKeepCompanyOnReset').is(':checked');
        const preservedCompanies = keepCompany ? getFilterCompanyValue() : [];

        localStorage.removeItem(FILTER_STORAGE_KEY);
        const form = document.getElementById('projectFilterForm');
        if (form) form.reset();
        $('#filterKeepTeamOnReset').prop('checked', keepTeam);
        $('#filterKeepCompanyOnReset').prop('checked', keepCompany);
        if (keepTeam) {
            $('#filterTeam').val(preservedTeams.length ? preservedTeams : null).trigger('change');
        } else {
            $('#filterTeam').val(null).trigger('change');
        }
        $('#filterCompany').val(keepCompany && preservedCompanies.length ? preservedCompanies : null).trigger('change');
        const preservedState = {};
        if (keepTeam && preservedTeams.length) {
            preservedState.filterTeam = preservedTeams;
        }
        if (keepCompany && preservedCompanies.length) {
            preservedState.filterCompany = preservedCompanies;
        }
        if (Object.keys(preservedState).length > 0) {
            try {
                localStorage.setItem(FILTER_STORAGE_KEY, JSON.stringify(preservedState));
            } catch (e) {}
        }
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
                refreshFilterCompanySelect();
            });
            
            // Handle window resize
            window.addEventListener('resize', this.handleResize);
            
        },
            beforeUnmount() {
                // Clean up Space+drag scroll listeners
                this.teardownSpaceDragScroll();
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

                    teamIdToName = {};
                    (this.teams || []).forEach(function(team) {
                        if (team.id != null) {
                            teamIdToName[String(team.id)] = team.name || '';
                        }
                    });
                    refreshFilterTeamSelect(this.teams);
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
                    const filterTeam = formatFilterTeamForApi(getFilterTeamValue());
                    const filterCompany = formatFilterCompanyForApi(getFilterCompanyValue());
                    const filterTantou = $('#filterTantou').val();
                    const filterNoDates = $('#filterNoDates').is(':checked') ? 1 : 0;
                    const showInactive = $('#showInactiveSwitch').is(':checked') ? 1 : 0;
                    const myProjects = $('#filterMyProjects').is(':checked') ? 1 : 0;
                    const filterKeyword = $('#filterKeyword').val();
                    const filterProjectId = $('#filterProjectId').val();
                    // 表示期間: từ gantt config hoặc từ input; lần đầu load dùng khoảng mặc định
                    let ganttStart = null, ganttEnd = null;
                    if (gantt && this.ganttInitialized && gantt.config.start_date && gantt.config.end_date) {
                        ganttStart = this.formatDateForInput(gantt.config.start_date);
                        ganttEnd = this.formatDateForInput(gantt.config.end_date);
                    } else {
                        const startEl = document.querySelector('.start_date');
                        const endEl = document.querySelector('.end_date');
                        if (startEl && endEl && startEl.value && endEl.value) {
                            ganttStart = startEl.value;
                            ganttEnd = endEl.value;
                        }
                    }
                    if (!ganttStart || !ganttEnd) {
                        const today = new Date();
                        const start = new Date(today.getFullYear(), today.getMonth() - 6, today.getDate());
                        const end = new Date(today.getFullYear(), today.getMonth() + 6, today.getDate());
                        ganttStart = this.formatDateForInput(start);
                        ganttEnd = this.formatDateForInput(end);
                    }
                    console.log('ganttStart:', ganttStart);
                    console.log('ganttEnd:', ganttEnd);
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
                        filterCompany,
                        filterTantou,
                        filterNoDates,
                        showInactive,
                        my_projects: myProjects,
                        filterKeyword,
                        filterProjectId
                    };
                    params.gantt_start_date = ganttStart;
                    params.gantt_end_date = ganttEnd;
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
                
                // Giữ 表示期間 hiện tại khi chỉ đổi checkbox (bỏ check CAILY納期を表示 v.v.) để view không nhảy
                const preservedStart = (gantt.config.start_date && !isNaN(gantt.config.start_date.getTime())) ? new Date(gantt.config.start_date.getTime()) : null;
                const preservedEnd = (gantt.config.end_date && !isNaN(gantt.config.end_date.getTime())) ? new Date(gantt.config.end_date.getTime()) : null;
                
                const ganttData = this.convertProjectsToGanttData(projectData);
                
                gantt.clearAll();
                gantt.parse(ganttData);
                
                if (preservedStart && preservedEnd && preservedStart < preservedEnd) {
                    // Giữ nguyên 表示期間 đã chọn
                    gantt.config.start_date = preservedStart;
                    gantt.config.end_date = preservedEnd;
                    gantt.render();
                    this.updateDateInputs(preservedStart, preservedEnd);
                } else if (ganttData.data && ganttData.data.length > 0) {
                    // Lần đầu load hoặc chưa có range hợp lệ: tính từ data
                    const dates = ganttData.data.map(task => [task.start_date, task.end_date]).flat();
                    const minDate = new Date(Math.min(...dates.map(d => d.getTime())));
                    const maxDate = new Date(Math.max(...dates.map(d => d.getTime())));
                    const padding = 7 * 24 * 60 * 60 * 1000;
                    const viewStart = new Date(minDate.getTime() - padding);
                    const viewEnd = new Date(maxDate.getTime() + padding);
                    gantt.config.start_date = viewStart;
                    gantt.config.end_date = viewEnd;
                    gantt.render();
                    this.updateDateInputs(viewStart, viewEnd);
                } else {
                    const today = new Date();
                    const oneWeekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                    const startDate = oneWeekAgo;
                    const endDate = new Date(today.getFullYear(), today.getMonth() + 2, 0);
                    gantt.config.min_date = startDate;
                    gantt.config.max_date = endDate;
                    gantt.render();
                    this.updateDateInputs(startDate, endDate);
                }

                // Sau reload/re-render: scroll view để hiển thị ngày hiện tại - 7 ngày
                if (typeof gantt.showDate === 'function') {
                    const today = new Date();
                    const sevenDaysAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                    gantt.showDate(sevenDaysAgo);
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
                    const parseNoukiToDate = (raw) => {
                        if (!raw || String(raw).trim() === '' || String(raw).trim() === '-') return null;
                        let d = null;
                        if (typeof raw === 'string' && raw.match(/^\d{4}-\d{2}-\d{2}$/)) {
                            d = this.parseDate(raw + ' 18:00:00');
                        } else {
                            d = this.parseDate(raw);
                        }
                        return (d && !isNaN(d.getTime())) ? d : null;
                    };

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

                    // Task chính: CAILY user → CAILY納期; user khác → GUIS納期 (tantou=CAILY thì CAILY納期)
                    if (isCailyBranchUser()) {
                        const cailyNoukiEnd = parseNoukiToDate(project.caily_nouki);
                        if (cailyNoukiEnd) {
                            endDate = new Date(cailyNoukiEnd.getTime());
                        }
                    } else {
                        const cailyNoukiEnd = parseNoukiToDate(project.caily_nouki);
                        const guisNoukiEnd = parseNoukiToDate(project.guis_nouki);
                        if (project.tantou === 'CAILY' && cailyNoukiEnd) {
                            endDate = new Date(cailyNoukiEnd.getTime());
                        } else if (guisNoukiEnd) {
                            endDate = new Date(guisNoukiEnd.getTime());
                        }
                    }

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
                    
                    // Build team names from all team ids in project.teams, cách nhau bằng ","
                    let teamName = '';
                    if (project.teams && typeof project.teams === 'string') {
                        const teamIds = project.teams.split(',').map(t => t.trim()).filter(t => t);
                        if (teamIds.length > 0 && Array.isArray(this.teams) && this.teams.length > 0) {
                            const names = teamIds.map(id => {
                                const found = this.teams.find(t => String(t.id) === String(id));
                                return (found && found.name) ? found.name : null;
                            }).filter(Boolean);
                            teamName = names.join(', ');
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
                        open: !!window.ganttTreeOpen,
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
                        caily_nouki_status: project.caily_nouki_status || '',
                        guis_nouki: project.guis_nouki || '-',
                        custom_fields: project.custom_fields || ''
                    };
                    
                    tasks.push(task);

                    // Subtask/link ID: một công thức duy nhất để tránh trùng. pid là số nguyên, slot 0-5 cố định.
                    const SUBTASK_ID_BASE = 900000000;
                    const LINK_ID_BASE = 800000000;
                    const pid = parseInt(project.id, 10) || 0;
                    const subId = (slot) => SUBTASK_ID_BASE + pid * 10 + slot;
                    const linkId = (slot) => LINK_ID_BASE + pid * 10 + slot;
                    const SLOT_CAILY_NOUKI = 0;
                    const SLOT_GUIS_NOUKI = 1;
                    const SLOT_CAILY_STRUCT = 2;
                    const SLOT_GUIS_STRUCT = 3;
                    const SLOT_EQUIPMENT = 4;
                    const SLOT_END_DATE = 5;

                    // Milestone CAILY納期: tantou=CAILY thì hiển thị thêm team name
                    const showCailyNouki = $('#useCailyEndDate').length && $('#useCailyEndDate').is(':checked');
                    const cailyEnd = parseNoukiToDate(project.caily_nouki);
                    if (showCailyNouki && cailyEnd) {
                        const cailyNoukiText = (project.tantou === 'CAILY' && teamName) ? 'CAILY納期' + ': [' + teamName + ']' : 'CAILY納期';
                        tasks.push({
                            id: subId(SLOT_CAILY_NOUKI),
                            text: cailyNoukiText,
                            start_date: new Date(cailyEnd.getTime()),
                            end_date: new Date(cailyEnd.getTime()),
                            type: 'milestone',
                            parent: project.id,
                            open: !!window.ganttTreeOpen,
                            duration: 0
                        });
                        links.push({ id: linkId(SLOT_CAILY_NOUKI), source: project.id, target: subId(SLOT_CAILY_NOUKI), type: 0 });
                    }
                    // Milestone GUIS納期: tantou=GUIS thì hiển thị thêm team name
                    const showGuisNouki = !isCailyBranchUser() && $('#useGuisEndDate').length && $('#useGuisEndDate').is(':checked');
                    const guisEnd = parseNoukiToDate(project.guis_nouki);
                    if (showGuisNouki && guisEnd) {
                        const guisNoukiText = (project.tantou === 'GUIS' && teamName) ? 'GUIS納期:' + '[' + teamName + ']' : 'GUIS納期';
                        tasks.push({
                            id: subId(SLOT_GUIS_NOUKI),
                            text: guisNoukiText,
                            start_date: new Date(guisEnd.getTime()),
                            end_date: new Date(guisEnd.getTime()),
                            type: 'milestone',
                            parent: project.id,
                            open: !!window.ganttTreeOpen,
                            duration: 0
                        });
                        links.push({ id: linkId(SLOT_GUIS_NOUKI), source: project.id, target: subId(SLOT_GUIS_NOUKI), type: 0 });
                    }
                    // Milestone 期限日 (project.end_date)
                    const showEndDate = !isCailyBranchUser() && $('#useEndDate').length && $('#useEndDate').is(':checked');
                    const endDateMilestone = parseNoukiToDate(project.end_date);
                    if (showEndDate && endDateMilestone) {
                        tasks.push({
                            id: subId(SLOT_END_DATE),
                            text: '期限日',
                            start_date: new Date(endDateMilestone.getTime()),
                            end_date: new Date(endDateMilestone.getTime()),
                            type: 'milestone',
                            parent: project.id,
                            open: !!window.ganttTreeOpen,
                            duration: 0
                        });
                        links.push({ id: linkId(SLOT_END_DATE), source: project.id, target: subId(SLOT_END_DATE), type: 0 });
                    }

                    // Helper: decode custom_fields (API có thể trả về &quot; thay vì ") rồi lấy giá trị theo label
                    const getCustomFieldValue = (raw, label) => {
                        if (!raw || !label) return null;
                        try {
                            if (typeof raw === 'string' && raw.includes('&quot;')) {
                                raw = raw.replace(/&quot;/g, '"');
                            }
                            const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
                            if (Array.isArray(parsed)) {
                                const item = parsed.find(f => f && String(f.label).trim() === String(label).trim());
                                return item ? (item.value || null) : null;
                            }
                            if (parsed && typeof parsed === 'object' && parsed[label]) return parsed[label];
                        } catch (e) { return null; }
                        return null;
                    };
                    const parseCustomFieldDate = (val) => {
                        if (!val || String(val).trim() === '') return null;
                        const s = String(val).trim();
                        const defaultHour = 18;
                        const defaultMin = 0;
                        // Đã có phần giờ (có dấu cách): YYYY/M/D HH:mm hoặc YYYY-M-D HH:mm
                        if (s.includes(' ')) {
                            const normalized = s.replace(/\//g, '-').replace(' ', 'T');
                            const d = this.parseDate(normalized);
                            return (d && !isNaN(d.getTime())) ? d : null;
                        }
                        const parts = s.split(/[/-]/).map(p => parseInt(p, 10)).filter(n => !isNaN(n));
                        // yyyy/m/d hoặc yyyy-m-d
                        if (parts.length === 3) {
                            let year = parts[0];
                            if (year < 100) year += 2000; // 24 → 2024
                            const month = Math.max(0, Math.min(11, parts[1] - 1));
                            const day = Math.max(1, Math.min(31, parts[2]));
                            const d = new Date(year, month, day, defaultHour, defaultMin, 0);
                            return !isNaN(d.getTime()) ? d : null;
                        }
                        // m/d hoặc m-d (dùng năm hiện tại)
                        if (parts.length === 2) {
                            const year = new Date().getFullYear();
                            const month = Math.max(0, Math.min(11, parts[0] - 1));
                            const day = Math.max(1, Math.min(31, parts[1]));
                            const d = new Date(year, month, day, defaultHour, defaultMin, 0);
                            return !isNaN(d.getTime()) ? d : null;
                        }
                        const normalized = s.replace(/\//g, '-');
                        const d = this.parseDate(normalized + 'T' + defaultHour + ':00:00');
                        return (d && !isNaN(d.getTime())) ? d : null;
                    };

                    // Milestone 構造データ送付 (CAILY): từ custom field, chỉ khi checkbox được chọn
                    const showCailyStruct = $('#useShowCailyStruct').length && $('#useShowCailyStruct').is(':checked');
                    const cailyStructVal = getCustomFieldValue(project.custom_fields, '構造データ送付 (CAILY)');
                    const cailyStructDate = parseCustomFieldDate(cailyStructVal);
                    if (showCailyStruct && cailyStructDate) {
                        tasks.push({
                            id: subId(SLOT_CAILY_STRUCT),
                            text: '構造データ送付 (CAILY)',
                            start_date: new Date(cailyStructDate.getTime()),
                            end_date: new Date(cailyStructDate.getTime()),
                            type: 'milestone',
                            parent: project.id,
                            open: !!window.ganttTreeOpen,
                            duration: 0
                        });
                        links.push({ id: linkId(SLOT_CAILY_STRUCT), source: project.id, target: subId(SLOT_CAILY_STRUCT), type: 0 });
                    }
                    // Milestone 構造データ送付 (GUIS)
                    const showGuisStruct = $('#useShowGuisStruct').length && $('#useShowGuisStruct').is(':checked');
                    const guisStructVal = getCustomFieldValue(project.custom_fields, '構造データ送付 (GUIS)');
                    const guisStructDate = parseCustomFieldDate(guisStructVal);
                    if (showGuisStruct && guisStructDate) {
                        tasks.push({
                            id: subId(SLOT_GUIS_STRUCT),
                            text: '構造データ送付 (GUIS)',
                            start_date: new Date(guisStructDate.getTime()),
                            end_date: new Date(guisStructDate.getTime()),
                            type: 'milestone',
                            parent: project.id,
                            open: !!window.ganttTreeOpen,
                            duration: 0
                        });
                        links.push({ id: linkId(SLOT_GUIS_STRUCT), source: project.id, target: subId(SLOT_GUIS_STRUCT), type: 0 });
                    }
                    // Milestone 設備 納期 (custom field)
                    const showEquipmentNouki = $('#useShowEquipmentNouki').length && $('#useShowEquipmentNouki').is(':checked');
                    const equipmentVal = getCustomFieldValue(project.custom_fields, '設備 納期');
                    const equipmentDate = parseCustomFieldDate(equipmentVal);
                    if (showEquipmentNouki && equipmentDate) {
                        tasks.push({
                            id: subId(SLOT_EQUIPMENT),
                            text: '設備 納期',
                            start_date: new Date(equipmentDate.getTime()),
                            end_date: new Date(equipmentDate.getTime()),
                            type: 'milestone',
                            parent: project.id,
                            open: !!window.ganttTreeOpen,
                            duration: 0
                        });
                        links.push({ id: linkId(SLOT_EQUIPMENT), source: project.id, target: subId(SLOT_EQUIPMENT), type: 0 });
                    }
                });
                
                return { data: tasks, links: links };
            },
            
            parseDate(dateString) {
                if (!dateString) return new Date();
                
                // Handle different date formats more robustly
                let date = null;
                
                // Try parsing as ISO string first
                if (typeof dateString === 'string') {
                    // Handle MySQL datetime format: "2024-01-15 10:30:00"
                    if (dateString.includes(' ')) {
                        const mysqlDate = dateString.replace(' ', 'T');
                        date = new Date(mysqlDate);
                    }
                    
                    // Handle date only format: "2024-01-15"
                    if (!date || isNaN(date.getTime())) {
                        if (dateString.match(/^\d{4}-\d{2}-\d{2}$/)) {
                            date = new Date(dateString + 'T00:00:00');
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
                        }
                    }
                    
                    // Try standard Date constructor as fallback
                    if (!date || isNaN(date.getTime())) {
                        date = new Date(dateString);
                    }
                } else if (dateString instanceof Date) {
                    date = dateString;
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
                
                return date;
            },
            
            refreshGantt() {
                this.loadProjects();
            },
            
            // Scale Controls
            setDefaultScale() {
                if (!gantt || !this.ganttInitialized) return;
                this.currentScale = 'week';
                if (gantt.ext && gantt.ext.zoom && typeof gantt.ext.zoom.setLevel === 'function') {
                    gantt.ext.zoom.setLevel(1);
                } else {
                    gantt.config.scales = [
                        { unit: "week", step: 1, format: "%m月" },
                        { unit: "day", step: 1, format: "%d日" }
                    ];
                    gantt.render();
                }
            },
            
            setMonthScale() {
                if (!gantt || !this.ganttInitialized) return;
                this.currentScale = 'month';
                if (gantt.ext && gantt.ext.zoom && typeof gantt.ext.zoom.setLevel === 'function') {
                    gantt.ext.zoom.setLevel(0);
                } else {
                    gantt.config.scales = [
                        { unit: "month", step: 1, format: "%m月" },
                        { unit: "week", step: 1, format: "%d日" }
                    ];
                    gantt.render();
                }
            },
            
            setWeekScale() {
                if (!gantt || !this.ganttInitialized) return;
                this.currentScale = 'week';
                if (gantt.ext && gantt.ext.zoom && typeof gantt.ext.zoom.setLevel === 'function') {
                    gantt.ext.zoom.setLevel(1);
                } else {
                    gantt.config.scales = [
                        { unit: "week", step: 1, format: "%m月" },
                        { unit: "day", step: 1, format: "%d日" }
                    ];
                    gantt.render();
                }
            },
            
            setDayScale() {
                if (!gantt || !this.ganttInitialized) return;
                this.currentScale = 'day';
                if (gantt.ext && gantt.ext.zoom && typeof gantt.ext.zoom.setLevel === 'function') {
                    gantt.ext.zoom.setLevel(2);
                } else {
                    gantt.config.scales = [
                        { unit: "day", step: 1, format: "%m月%d日" },
                        { unit: "hour", step: 1, format: "%H:%i" }
                    ];
                    gantt.render();
                }
            },
            
            // Zoom Controls
            zoomIn() {
                if (!gantt || !this.ganttInitialized) return;
                // Nếu có zoom extension thì dùng zoomIn của extension (chuyển level: 月→週→日)
                if (gantt.ext && gantt.ext.zoom && typeof gantt.ext.zoom.zoomIn === 'function') {
                    gantt.ext.zoom.zoomIn();
                } else {
                    // Fallback: zoom bằng cách thu hẹp date range
                    const currentDate = gantt.getState().min_date;
                    const currentRange = gantt.getState().max_date - gantt.getState().min_date;
                    const newRange = currentRange * 0.3;
                    const newMinDate = new Date(currentDate.getTime() + currentRange * 0.15);
                    const newMaxDate = new Date(newMinDate.getTime() + newRange);
                    
                    gantt.config.start_date = newMinDate;
                    gantt.config.end_date = newMaxDate;
                    gantt.render();
                    this.updateDateInputs(newMinDate, newMaxDate);
                }
            },
            
            zoomOut() {
                if (!gantt || !this.ganttInitialized) return;
                // Nếu có zoom extension thì dùng zoomOut của extension (chuyển level: 日→週→月)
                if (gantt.ext && gantt.ext.zoom && typeof gantt.ext.zoom.zoomOut === 'function') {
                    gantt.ext.zoom.zoomOut();
                } else {
                    // Fallback: zoom bằng cách mở rộng date range
                    const currentDate = gantt.getState().min_date;
                    const currentRange = gantt.getState().max_date - gantt.getState().min_date;
                    const newRange = currentRange * 1.4;
                    const newMinDate = new Date(currentDate.getTime() - currentRange * 0.2);
                    const newMaxDate = new Date(newMinDate.getTime() + newRange);
                    
                    gantt.config.start_date = newMinDate;
                    gantt.config.end_date = newMaxDate;
                    gantt.render();
                    this.updateDateInputs(newMinDate, newMaxDate);
                }
            },
            
            // Date Range Controls
            scrollToTodayMinus7() {
                if (!gantt || !this.ganttInitialized || typeof gantt.showDate !== 'function') return;
                const today = new Date();
                const sevenDaysAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                gantt.showDate(sevenDaysAgo);
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
            
            setupSpaceDragScroll() {
                if (!gantt || typeof gantt.getScrollState !== 'function' || typeof gantt.scrollTo !== 'function') return;
                const container = document.getElementById('gantt_container');
                if (!container) return;
                const state = { spaceDown: false, dragging: false, startX: 0, startY: 0, startScroll: null };
                const onKeydown = (e) => {
                    if (e.code === 'Space' || e.key === ' ') {
                        state.spaceDown = true;
                        container.classList.add('gantt-space-pan');
                        e.preventDefault();
                    }
                };
                const onKeyup = (e) => {
                    if (e.code === 'Space' || e.key === ' ') {
                        state.spaceDown = false;
                        state.dragging = false;
                        container.classList.remove('gantt-space-pan', 'gantt-space-pan-dragging');
                        e.preventDefault();
                    }
                };
                const onMousedown = (e) => {
                    if (!state.spaceDown || state.dragging) return;
                    state.dragging = true;
                    state.startX = e.clientX;
                    state.startY = e.clientY;
                    state.startScroll = gantt.getScrollState();
                    container.classList.add('gantt-space-pan-dragging');
                    e.preventDefault();
                };
                const onMousemove = (e) => {
                    if (!state.dragging || !state.startScroll) return;
                    const dx = e.clientX - state.startX;
                    const dy = e.clientY - state.startY;
                    const newX = Math.max(0, state.startScroll.x - dx);
                    const newY = Math.max(0, state.startScroll.y - dy);
                    gantt.scrollTo(newX, newY);
                };
                const onMouseup = () => {
                    state.dragging = false;
                    state.startScroll = null;
                    container.classList.remove('gantt-space-pan-dragging');
                };
                document.addEventListener('keydown', onKeydown);
                document.addEventListener('keyup', onKeyup);
                container.addEventListener('mousedown', onMousedown);
                document.addEventListener('mousemove', onMousemove);
                document.addEventListener('mouseup', onMouseup);
                this._spacePanHandlers = { onKeydown, onKeyup, onMousedown, onMousemove, onMouseup };
            },
            teardownSpaceDragScroll() {
                const container = document.getElementById('gantt_container');
                const h = this._spacePanHandlers;
                if (!h) return;
                document.removeEventListener('keydown', h.onKeydown);
                document.removeEventListener('keyup', h.onKeyup);
                if (container) container.removeEventListener('mousedown', h.onMousedown);
                document.removeEventListener('mousemove', h.onMousemove);
                document.removeEventListener('mouseup', h.onMouseup);
                this._spacePanHandlers = null;
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
                    zoom: true,
                    //quick_info: true
                });
                gantt.config.quickinfo_buttons=["icon_edit"];
                gantt.config.drag_lightbox = true;
                // drag_timeline: chỉ hỗ trợ ignore, useKey, render (theo API). useKey: "ctrlKey" = giữ Ctrl + kéo để scroll.
                gantt.config.drag_timeline = {
                    ignore: ".gantt_task_line, .gantt_task_link",
                    useKey: "ctrlKey",
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

                // Disable lightbox for subtasks
                gantt.attachEvent("onBeforeLightbox", function(id){
                    const task = gantt.getTask(id);
                    if (!task) return true;
                    
                    // Check if this is a subtask
                    // Subtask ID range: SUBTASK_ID_BASE (900000000) and above
                    const SUBTASK_ID_BASE = 900000000;
                    const isSubtaskById = task.id >= SUBTASK_ID_BASE;
                    
                    // Also check by task text pattern
                    const isSubtaskByText = task.text && (
                        task.text.startsWith('CAILY納期') || 
                        task.text.startsWith('GUIS納期') || 
                        task.text === '期限日' ||
                        task.text.startsWith('構造データ送付 (CAILY)') || 
                        task.text.startsWith('構造データ送付 (GUIS)') || 
                        task.text.startsWith('設備 納期')
                    );
                    
                    // If it's a subtask, prevent lightbox from opening
                    if (isSubtaskById || isSubtaskByText) {
                        return false;
                    }
                    
                    return true;
                });

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
                gantt.config.open_tree_initially = !!window.ganttTreeOpen;
                gantt.config.show_tasks_outside_timescale = true;
                gantt.config.initial_scroll = true;
              
                
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
                
                function getOverdueDeadlineMoment(obj) {
                    if (!obj || (obj.parent && obj.parent !== 0)) return null;
                    const skipStatuses = ['completed', 'cancelled', 'paused', 'deleted'];
                    if (skipStatuses.includes(obj.status)) return null;

                    if (isCailyBranchUser()) {
                        const raw = obj.caily_nouki;
                        if (!raw || String(raw).trim() === '' || String(raw).trim() === '-') return null;
                        if (obj.caily_nouki_status && String(obj.caily_nouki_status).indexOf('納品済み') !== -1) return null;
                        const m = typeof moment !== 'undefined' && moment.tz
                            ? moment.tz(raw, 'Asia/Tokyo')
                            : (typeof moment !== 'undefined' ? moment(raw) : null);
                        return m && m.isValid() ? m : null;
                    }

                    if (obj.tantou === 'CAILY') {
                        const cailyRaw = obj.caily_nouki;
                        if (cailyRaw && String(cailyRaw).trim() !== '' && String(cailyRaw).trim() !== '-') {
                            if (obj.caily_nouki_status && String(obj.caily_nouki_status).indexOf('納品済み') !== -1) return null;
                            const m = typeof moment !== 'undefined' && moment.tz
                                ? moment.tz(cailyRaw, 'Asia/Tokyo')
                                : (typeof moment !== 'undefined' ? moment(cailyRaw) : null);
                            if (m && m.isValid()) return m;
                        }
                    }

                    const guisRaw = obj.guis_nouki;
                    if (guisRaw && String(guisRaw).trim() !== '' && String(guisRaw).trim() !== '-') {
                        const m = typeof moment !== 'undefined' && moment.tz
                            ? moment.tz(guisRaw, 'Asia/Tokyo')
                            : (typeof moment !== 'undefined' ? moment(guisRaw) : null);
                        if (m && m.isValid()) return m;
                    }

                    if (!obj.end_date) return null;
                    const m = typeof moment !== 'undefined' && moment.tz
                        ? moment.tz(obj.end_date, 'Asia/Tokyo')
                        : (typeof moment !== 'undefined' ? moment(obj.end_date) : null);
                    return m && m.isValid() ? m : null;
                }

                // Helper function to check if project is overdue
                function isProjectOverdue(obj) {
                    const deadline = getOverdueDeadlineMoment(obj);
                    if (!deadline) return false;
                    const now = typeof moment !== 'undefined' && moment.tz
                        ? moment.tz('Asia/Tokyo')
                        : (typeof moment !== 'undefined' ? moment() : null);
                    if (!now || !now.isValid()) return false;
                    return deadline.clone().startOf('day').isBefore(now.clone().startOf('day'));
                }

                function formatCompanyNameLabel(companyName) {
                    const company = String(companyName || '').trim();
                    if (!company || company === '-') return '';

                    let text = '他社';
                    let style = 'font-size:0.65rem;line-height:1;vertical-align:middle;';
                    if (company.indexOf('大東建託') !== -1) {
                        text = '大東';
                        style += 'background-color:#dc3545;color:#fff;';
                    } else if (company.indexOf('東建コーポレーション') !== -1) {
                        text = '東建';
                        style += 'background-color:#8B4513;color:#fff;';
                    } else {
                        style += 'background-color:#0d6efd;color:#fff;';
                    }
                    return '<span class="badge me-1 px-1" style="' + style + '">' + text + '</span>';
                }
                
                // // Customize columns
                gantt.config.columns = [
                    { name: "index", label: "ID", width: 70, align: "center", min_width: 50, 
                      template: function (obj) {
                          if (obj.parent && obj.parent !== 0) return ''; // Ẩn ID cho subtask (CAILY納期, GUIS納期)
                          return String(obj.id || '');
                      },
                      onrender: function (task, cell) {
                          // Custom render for overdue badge
                          if (task.parent && task.parent !== 0) {
                              cell.innerHTML = '';
                              return;
                          }
                          const idText = String(task.id || '');
                          const isOverdue = isProjectOverdue(task);
                          
                          if (isOverdue) {
                              cell.innerHTML = '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;line-height:1.2;">' +
                                             '<span>' + idText + '</span>' +
                                             '<span style="background-color:#dc3545;color:#fff;font-size:0.625rem;padding:0.1rem 0.25rem;border-radius:0.25rem;line-height:1; position: relative; top: -5px;">' + (typeof i18next !== 'undefined' && i18next.t ? i18next.t('期限超過') : '期限超過') + '</span>' +
                                             '</div>';
                          } else if(task.status == 'paused'){
                              cell.innerHTML = '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;line-height:1.2;">' +
                                             '<span>' + idText + '</span>' +
                                             '<span style="background-color:#fdf3e7;color:rgb(255, 159, 67);font-size:0.625rem;padding:0.1rem 0.25rem;border-radius:0.25rem;line-height:1; position: relative; top: -5px;">停止中</span>' +
                                             '</div>';
                          } else {
                              cell.innerHTML = idText;
                          }
                      }
                    },
                    { name: "branch_name", label: "支店名", width: 90, min_width: 50, template: function(obj) {
                        if (obj.parent && obj.parent !== 0) return '';
                        const branch = String(obj.branch_name || '').trim();
                        const companyLabel = formatCompanyNameLabel(obj.company);
                        if (!branch && !companyLabel) {
                            return '-';
                        }
                        if (!branch) {
                            return companyLabel;
                        }
                        if (!companyLabel) {
                            return branch;
                        }
                        return '<div style="display:flex; align-items:center;gap:2px;">' +
                            companyLabel +
                            '<span>' + branch + '</span>' +
                            '</div>';
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
                    
                    // Sub-task CAILY納期 / GUIS納期 / 構造データ送付 (CAILY/GUIS) colors (task.text có thể kèm team name)
                    if (task.text && task.text.startsWith('CAILY納期')) {
                        classes.push('gantt-task-caily-nouki');
                    } else if (task.text && task.text.startsWith('GUIS納期')) {
                        classes.push('gantt-task-guis-nouki');
                    } else if (task.text && (task.text === '期限日' || task.text.startsWith('期限日'))) {
                        classes.push('gantt-task-end-date');
                    } else if (task.text && task.text.startsWith('構造データ送付 (CAILY)')) {
                        classes.push('gantt-task-caily-struct');
                    } else if (task.text && task.text.startsWith('構造データ送付 (GUIS)')) {
                        classes.push('gantt-task-guis-struct');
                    } else if (task.text && task.text.startsWith('設備 納期')) {
                        classes.push('gantt-task-equipment-nouki');
                    }
                    
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
                    const t = String(orderType).trim();
                    if (t === '修正') return 'bg-warning small';
                    if (t === '新規') return 'bg-primary small';
                    if (t === '新規修正') return 'bg-success small';
                    if (t === '変更') return 'bg-danger small';
                    return 'bg-info small';
                };
                // Customize task text: badge before project_order_type (e.g. 期間未定), then orderType badges, team name, project name
                gantt.templates.task_text = function(start, end, task) {
                    const parts = [];
                    
                    // Check if this is a subtask (not a main project task)
                    const isSubtask = task.text && (
                        task.text.startsWith('CAILY納期') || 
                        task.text.startsWith('GUIS納期') || 
                        task.text === '期限日' ||
                        task.text.startsWith('構造データ送付 (CAILY)') || 
                        task.text.startsWith('構造データ送付 (GUIS)') || 
                        task.text.startsWith('設備 納期')
                    );
                    
                    // Add progress % for project tasks only (not subtasks)
                    
                    
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
                    if (!isSubtask && task.progress != null) {
                        const progressPercent = Math.round((task.progress || 0) * 100);
                        parts.push('<span class="bg-label-primary small ms-1 px-1" style="font-weight: bold;">' + progressPercent + '%</span>');
                    }
                    return parts.join('');
                };
                // Nhãn bên phải thanh task (cho milestone vì thanh có độ dài 0)
                gantt.templates.rightside_text = function(start, end, task) {
                    if (task.type === 'milestone' && task.text) {
                        // Check if this is a subtask milestone
                        const isSubtask = task.text && (
                            task.text.startsWith('CAILY納期') || 
                            task.text.startsWith('GUIS納期') || 
                            task.text === '期限日' ||
                            task.text.startsWith('構造データ送付 (CAILY)') || 
                            task.text.startsWith('構造データ送付 (GUIS)') || 
                            task.text.startsWith('設備 納期')
                        );
                        
                        if (isSubtask && start) {
                            // For subtasks, show task name (without team name) + start time
                            let text = task.text;
                            // Remove team name part (e.g., ": [Team A]" or ":[Team A]")
                            text = text.replace(/:\s*\[[^\]]+\]\s*$/, '');
                            text = text.replace(/\[[^\]]+\]\s*$/, ''); // Also handle case without colon
                            text = text.trim();
                            
                            // Format start date: m月d日 h:i (e.g., 2月5日 9:00)
                            let dateStr = '';
                            if (typeof moment !== 'undefined' && moment(start).isValid()) {
                                dateStr = moment(start).format('M月D日 H:mm');
                            } else if (start instanceof Date) {
                                const month = start.getMonth() + 1;
                                const day = start.getDate();
                                const hours = start.getHours();
                                const minutes = start.getMinutes();
                                dateStr = `${month}月${day}日 ${hours}:${String(minutes).padStart(2, '0')}`;
                            }
                            
                            // Return task name + start time
                            return dateStr ? `${text} ${dateStr}` : text;
                        }
                        // For non-subtask milestones, return text as before
                        return task.text;
                    }
                    return '';
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
                    // Subtask (CAILY納期 / GUIS納期 / 構造データ送付 / 設備 納期): có thể kèm team name
                    const isSubtask = task.text && (
                        task.text.startsWith('CAILY納期') || task.text.startsWith('GUIS納期') ||
                        task.text === '期限日' ||
                        task.text.startsWith('構造データ送付 (CAILY)') || task.text.startsWith('構造データ送付 (GUIS)') ||
                        task.text.startsWith('設備 納期')
                    );
                    if (isSubtask) {
                        const dateStr = start ? gantt.templates.tooltip_date_format(start) : '-';
                        return `<div class="gantt-tooltip"><h6>${task.text}</h6><p class="m-0">${dateStr}</p></div>`;
                    }

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
                    
                    const cailyBranch = isCailyBranchUser();
                    const deadlineLine = cailyBranch ? '' : `<p class="m-0"><strong>期限日:</strong> ${originalDeadline}</p>`;
                    const guisNoukiLine = cailyBranch ? '' : `<p class="m-0"><strong>GUIS納期:</strong> ${formatDateStringWithVN(task.guis_nouki)}</p>`;

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
                            ${deadlineLine}
                            <p class="m-0"><strong>担当:</strong> ${task.tantou || '-'}</p>
                            <p class="m-0"><strong>チーム:</strong> ${task.team_name || '-'}</p>
                            <p class="m-0"><strong>CAILY納期:</strong> ${formatDateStringWithVN(task.caily_nouki)}</p>
                            ${guisNoukiLine}
                            ${formatCustomFieldsForTooltip(task.custom_fields)}
                        </div>
                    `;
                };

                function formatCustomFieldsForTooltip(raw) {
                    if (!raw) return '';
                    try {
                        if (typeof raw === 'string' && raw.includes('&quot;')) {
                            raw = raw.replace(/&quot;/g, '"');
                        }
                        const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
                        if (Array.isArray(parsed) && parsed.length > 0) {
                            return parsed.map(f => {
                                const label = (f && f.label) ? String(f.label).replace(/</g, '&lt;').replace(/>/g, '&gt;') : '';
                                const value = (f && f.value != null) ? String(f.value).replace(/</g, '&lt;').replace(/>/g, '&gt;') : '-';
                                return label ? `<p class="m-0"><strong>${label}:</strong> ${value || '-'}</p>` : '';
                            }).filter(Boolean).join('');
                        }
                        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                            return Object.keys(parsed).map(label => {
                                const value = parsed[label];
                                const escLabel = String(label).replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                const escVal = value != null ? String(value).replace(/</g, '&lt;').replace(/>/g, '&gt;') : '-';
                                return `<p class="m-0"><strong>${escLabel}:</strong> ${escVal}</p>`;
                            }).join('');
                        }
                    } catch (e) { return ''; }
                    return '';
                }
                
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
                
                // Context menu for tasks
                const $ganttContextMenu = $('<div id="ganttTaskContextMenu" class="dropdown-menu" style="position:absolute; display:none; z-index:9999; min-width: 200px;"></div>');
                $ganttContextMenu.html(`
                    <button class="dropdown-item" type="button" id="ganttMenuCopyProjectId">
                        <i class="fa fa-copy me-1"></i> Copy Project ID
                    </button>
                    <button class="dropdown-item" type="button" id="ganttMenuCopyConstructNumber">
                        <i class="fa fa-copy me-1"></i> Copy Construct Number
                    </button>
                    <button class="dropdown-item" type="button" id="ganttMenuCopyProjectName">
                        <i class="fa fa-copy me-1"></i> Copy Project Name
                    </button>
                    <div class="dropdown-divider"></div>
                    <button class="dropdown-item" type="button" id="ganttMenuGoToDetail">
                        <i class="fa fa-external-link-alt me-1"></i> プロジェクト詳細へ
                    </button>
                `);
                $('body').append($ganttContextMenu);
                
                let contextMenuTaskId = null;
                
                // Handle context menu actions
                $ganttContextMenu.on('click', '#ganttMenuCopyProjectId', function(e) {
                    e.stopPropagation();
                    if (contextMenuTaskId) {
                        const task = gantt.getTask(contextMenuTaskId);
                        if (task && task.id) {
                            // For subtasks, get parent project id
                            const projectId = task.parent && task.parent !== 0 ? task.parent : task.id;
                            copyToClipboard(String(projectId));
                            showMessage('Project ID copied to clipboard');
                        }
                    }
                    $ganttContextMenu.hide();
                });
                
                $ganttContextMenu.on('click', '#ganttMenuCopyConstructNumber', function(e) {
                    e.stopPropagation();
                    if (contextMenuTaskId) {
                        const task = gantt.getTask(contextMenuTaskId);
                        if (task) {
                            // For subtasks, get parent task to get construction_number
                            const projectTask = task.parent && task.parent !== 0 ? gantt.getTask(task.parent) : task;
                            const constructNumber = projectTask.construction_number || '-';
                            copyToClipboard(String(constructNumber));
                            showMessage('Construct Number copied to clipboard');
                        }
                    }
                    $ganttContextMenu.hide();
                });
                
                $ganttContextMenu.on('click', '#ganttMenuCopyProjectName', function(e) {
                    e.stopPropagation();
                    if (contextMenuTaskId) {
                        const task = gantt.getTask(contextMenuTaskId);
                        if (task) {
                            // For subtasks, get parent task to get project name
                            const projectTask = task.parent && task.parent !== 0 ? gantt.getTask(task.parent) : task;
                            const projectName = projectTask.project_name || task.text || '-';
                            // Remove "期間未定" suffix if present
                            const cleanName = projectName.replace(/\s*期間未定\s*$/, '');
                            copyToClipboard(cleanName);
                            showMessage('Project Name copied to clipboard');
                        }
                    }
                    $ganttContextMenu.hide();
                });
                
                $ganttContextMenu.on('click', '#ganttMenuGoToDetail', function(e) {
                    e.stopPropagation();
                    if (contextMenuTaskId) {
                        const task = gantt.getTask(contextMenuTaskId);
                        if (task) {
                            // For subtasks, get parent project id
                            const projectId = task.parent && task.parent !== 0 ? task.parent : task.id;
                            window.open(`detail.php?id=${projectId}`, '_blank');
                        }
                    }
                    $ganttContextMenu.hide();
                });
                
                // Hide context menu when clicking elsewhere
                $(document).on('click', function() {
                    $ganttContextMenu.hide();
                });
                
                // Attach context menu event to Gantt
                gantt.attachEvent("onContextMenu", function(taskId, linkId, event) {
                    if (!taskId) return true; // Allow default context menu for non-task areas
                    
                    event.preventDefault();
                    contextMenuTaskId = taskId;
                    
                    // Show menu at mouse position
                    $ganttContextMenu
                        .css({ top: event.pageY + 'px', left: event.pageX + 'px' })
                        .show();
                    
                    return false; // Prevent default context menu
                });
                
                // Helper function to copy to clipboard
                function copyToClipboard(text) {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).catch(function(err) {
                            console.error('Failed to copy:', err);
                            fallbackCopyToClipboard(text);
                        });
                    } else {
                        fallbackCopyToClipboard(text);
                    }
                }
                
                function fallbackCopyToClipboard(text) {
                    const textArea = document.createElement('textarea');
                    textArea.value = text;
                    textArea.style.position = 'fixed';
                    textArea.style.left = '-999999px';
                    textArea.style.top = '-999999px';
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    try {
                        document.execCommand('copy');
                    } catch (err) {
                        console.error('Fallback copy failed:', err);
                    }
                    document.body.removeChild(textArea);
                }
                
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
                    applyTreeToggleState();
                    this.ganttInitialized = true;
                    // Zoom extension: Ctrl + wheel để zoom in/out (levels = 月 / 週 / 日)
                    if (gantt.ext && gantt.ext.zoom && typeof gantt.ext.zoom.init === 'function') {
                        const zoomConfig = {
                            trigger: "wheel",
                            useKey: "ctrlKey",
                            activeLevelIndex: 1,
                            levels: [
                                { name: "month", scale_height: 27, min_column_width: 60, scales: [
                                    { unit: "month", step: 1, format: "%m月" },
                                    { unit: "week", step: 1, format: "%d日" }
                                ]},
                                { name: "week", scale_height: 27, min_column_width: 80, scales: [
                                    { unit: "week", step: 1, format: "%m月" },
                                    { unit: "day", step: 1, format: "%d日" }
                                ]},
                                { name: "day", scale_height: 27, min_column_width: 100, scales: [
                                    { unit: "day", step: 1, format: "%m月%d日" },
                                    { unit: "hour", step: 1, format: "%H:%i" }
                                ]}
                            ]
                        };
                        gantt.ext.zoom.init(zoomConfig);
                    }
                    // Space + drag to scroll chart (pan)
                    this.setupSpaceDragScroll();
                } catch (error) {
                    console.error('Error initializing Gantt:', error);
                    this.ganttInitialized = false;
                }
            },
            
            addGanttStyles() {
                const style = document.createElement('style');
                style.textContent = `
                    #gantt_container {
                        z-index: 1000 !important;
                    }
                    #gantt_container.gantt-space-pan {
                        cursor: grab;
                    }
                    #gantt_container.gantt-space-pan-dragging {
                        cursor: grabbing;
                        user-select: none;
                    }
                    .gantt_tooltip,
                    .gantt_modal_box,
                    .gantt_cal_cover{
                        z-index: 1001 !important;
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
                    
                    /* CAILY納期 / GUIS納期: bản Free không hỗ trợ type milestone → thư viện render
                       zero-duration task thành gantt_bar_task + gantt_thin_task (không có gantt_milestone/gantt_bar_milestone). */
                    .gantt_task_line.gantt-task-caily-nouki {
                        background-color: #90ee90;
                        color: #1a3d1a;
                        width: 4px !important;
                    }
                    .gantt_task_line.gantt-task-guis-nouki {
                        background-color: #000;
                        color: #fff;
                        width: 4px !important;
                    }
                    .gantt_task_line.gantt-task-end-date {
                        background-color: #dc3545;
                        color: #fff;
                        width: 4px !important;
                    }
                    /* 構造データ送付 (CAILY) / (GUIS): cùng màu với CAILY納期・GUIS納期 */
                    .gantt_task_line.gantt-task-caily-struct {
                        background-color: #90ee90;
                        color: #1a3d1a;
                        width: 4px !important;
                    }
                    .gantt_task_line.gantt-task-guis-struct {
                        background-color: #000;
                        color: #fff;
                        width: 4px !important;
                    }
                    /* 設備 納期 (custom field) */
                    .gantt_task_line.gantt-task-equipment-nouki {
                        background-color:rgb(177, 6, 177);
                        color: #1a1a1a;
                        width: 4px !important;
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
    window.ganttTreeOpen = $('#toggleTaskTree').is(':checked');
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