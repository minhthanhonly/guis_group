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
        { key: 'completed', name: '完了', color: 'success' },
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
    const KEEP_TEAM_ON_RESET_KEY = 'project_list_keep_team_on_reset';
    const SELECTED_DEPARTMENT_KEY = 'projectListSelectedDepartment';
    const COLUMN_VISIBILITY_KEY = 'projectListColumnVisibility';
    const COLUMN_ORDER_STORAGE_KEY = 'projectListColumnOrder';

    function getProjectColumnOrderStorageKey(departmentId) {
        return COLUMN_ORDER_STORAGE_KEY + '_' + (departmentId != null ? String(departmentId) : '0');
    }

    function isCailyBranchUser() {
        return typeof window !== 'undefined' && window.IS_CAILY_BRANCH_USER === true;
    }

    function parseProjectDateMoment(value) {
        if (value === undefined || value === null) return null;
        var s = String(value).trim();
        if (!s || s === '-') return null;
        var normalized = s.replace(/\//g, '-');
        if (typeof moment !== 'undefined') {
            var formats = ['YYYY-MM-DD HH:mm', 'YYYY-M-D HH:mm', 'YYYY-MM-DD HH:mm:ss', 'YYYY-MM-DD', 'YYYY-M-D'];
            var m = typeof moment.tz === 'function'
                ? moment.tz(normalized, formats, 'Asia/Tokyo')
                : moment(normalized, formats, true);
            if (m.isValid()) return m;
        }
        var d = new Date(normalized);
        if (isNaN(d.getTime())) return null;
        return typeof moment !== 'undefined' ? moment(d) : null;
    }

    var CAILY_HIDDEN_COLUMN_KEYS = { guis_nouki: true, end_date: true };

    function isColumnHiddenForCailyBranch(columnKey) {
        return isCailyBranchUser() && !!CAILY_HIDDEN_COLUMN_KEYS[columnKey];
    }

    function filterColumnKeysForCailyBranch(keys) {
        if (!isCailyBranchUser()) return keys;
        return (keys || []).filter(function(k) { return !CAILY_HIDDEN_COLUMN_KEYS[k]; });
    }

    /** Default DataTable sort column index when end_date is hidden (e.g. CAILY branch). */
    function getDefaultProjectListSortIndex(mergedColumnKeys) {
        var preferred = ['end_date', 'caily_nouki', 'start_date', 'created_at', 'id'];
        for (var i = 0; i < preferred.length; i++) {
            var idx = mergedColumnKeys.indexOf(preferred[i]);
            if (idx >= 0) return idx;
        }
        return 0;
    }

    /** Merge saved column key order with current table keys (append missing keys in default order). */
    function mergeColumnKeyOrder(savedKeys, defaultKeys) {
        var def = defaultKeys || [];
        var saved = Array.isArray(savedKeys) ? savedKeys : [];
        var seen = Object.create(null);
        var out = [];
        var i;
        for (i = 0; i < saved.length; i++) {
            var k = saved[i];
            if (!k || seen[k]) continue;
            if (def.indexOf(k) === -1) continue;
            seen[k] = true;
            out.push(k);
        }
        for (i = 0; i < def.length; i++) {
            var dk = def[i];
            if (!seen[dk]) {
                seen[dk] = true;
                out.push(dk);
            }
        }
        return out;
    }

    function loadProjectColumnOrder(departmentId) {
        try {
            var raw = localStorage.getItem(getProjectColumnOrderStorageKey(departmentId));
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : null;
        } catch (e) {
            return null;
        }
    }

    function saveProjectColumnOrder(departmentId, keys) {
        try {
            localStorage.setItem(getProjectColumnOrderStorageKey(departmentId), JSON.stringify(keys || []));
        } catch (e) { /* ignore */ }
    }

    /** Thứ tự key cột mặc định (khớp fixed + custom + tail): trước CAILY納期 → custom → từ CAILY納期 trở đi. */
    function getDefaultProjectColumnKeys(customColDefs) {
        var customDefs = customColDefs || [];
        var before = [];
        var after = [];
        var pastCaily = false;
        var i;
        for (i = 0; i < COLUMN_DEFINITIONS.length; i++) {
            var ck = COLUMN_DEFINITIONS[i].key;
            if (ck === 'caily_nouki') pastCaily = true;
            if (!pastCaily) before.push(ck);
            else after.push(ck);
        }
        var keys = before.concat(customDefs.map(function(c) { return c.key; })).concat(after);
        return filterColumnKeysForCailyBranch(keys);
    }

    function getMergedProjectColumnKeys(customColDefs, departmentId) {
        return filterColumnKeysForCailyBranch(
            mergeColumnKeyOrder(loadProjectColumnOrder(departmentId), getDefaultProjectColumnKeys(customColDefs))
        );
    }

    /** Dropdown 列の表示 — cùng thứ tự với cột bảng (sau merge localStorage). */
    function buildAvailableColumnsList(customColDefs, departmentId, columnVisibility) {
        var vis = columnVisibility || {};
        var labelMap = {};
        COLUMN_DEFINITIONS.forEach(function(c) { labelMap[c.key] = c.label; });
        (customColDefs || []).forEach(function(c) { labelMap[c.key] = c.label; });
        var keys = getMergedProjectColumnKeys(customColDefs, departmentId);
        return keys.map(function(k) {
            return { key: k, label: labelMap[k] || k, visible: vis[k] !== false };
        }).filter(function(col) {
            return !isColumnHiddenForCailyBranch(col.key);
        });
    }

    // Cột dùng cho note 表示列 (giống project-detail.js, bỏ is_favorite, ID, CAILYメモ, GUISメモ)
    const NOTE_DISPLAY_COLUMNS = [
        { key: 'status', label: '案件状況' },
        { key: 'progress', label: '進捗率' },
        { key: 'tantou', label: '担当' },
        { key: 'manager', label: '管理' },
        { key: 'teams', label: 'チーム' },
        { key: 'members', label: 'メンバー' },
        { key: 'parent_construction_number', label: '工事番号' },
        { key: 'parent_branch_name', label: '支店名' },
        { key: 'name', label: 'お施主様名' },
        { key: 'parent_scale', label: '規模' },
        { key: 'parent_type1', label: '種類1' },
        { key: 'project_order_type', label: '受注形態' },
        { key: 'parent_type2', label: '種類2' },
        { key: 'start_date', label: '開始日' },
        { key: 'caily_nouki', label: 'CAILY納期' },
        { key: 'guis_nouki', label: 'GUIS納期' },
        { key: 'end_date', label: '終了日' },
        { key: 'priority', label: '優先度' },
        { key: 'amount', label: '総額' },
        { key: 'customer_info', label: '顧客情報' },
        { key: 'parent_guis_receiver', label: 'GUIS 受付者' }
    ];
    
    // Column definitions with mapping to DataTable column indices
    const COLUMN_DEFINITIONS = [
        { key: 'is_favorite', label: 'お気に入り', index: 0, defaultVisible: true },
        { key: 'id', label: 'ID', index: 1, defaultVisible: true },
        { key: 'confirmation_notes_caily', label: 'CAILYメモ', index: 2, defaultVisible: false },
        { key: 'confirmation_notes_guis', label: 'GUISメモ', index: 3, defaultVisible: false },
        { key: 'status', label: '案件状況', index: 4, defaultVisible: true },
        { key: 'progress', label: '進捗率', index: 5, defaultVisible: true },
        { key: 'tantou', label: '担当', index: 6, defaultVisible: true },
        { key: 'manager', label: '管理', index: 7, defaultVisible: false },
        { key: 'teams', label: 'チーム', index: 8, defaultVisible: true },
        { key: 'members', label: 'メンバー', index: 9, defaultVisible: false },
        { key: 'parent_construction_number', label: '工事番号', index: 10, defaultVisible: true },
        { key: 'parent_branch_name', label: '支店名', index: 11, defaultVisible: true },
        { key: 'name', label: 'お施主様名', index: 12, defaultVisible: true },
        { key: 'parent_scale', label: '規模', index: 13, defaultVisible: false },
        { key: 'parent_type1', label: '種類1', index: 14, defaultVisible: false },
        { key: 'project_order_type', label: '受注形態', index: 15, defaultVisible: true },
        { key: 'parent_type2', label: '種類2', index: 16, defaultVisible: false },
        { key: 'start_date', label: '開始日', index: 17, defaultVisible: true },
        { key: 'caily_nouki', label: 'CAILY納期', index: 18, defaultVisible: true },
        { key: 'guis_nouki', label: 'GUIS納期', index: 19, defaultVisible: true },
        { key: 'end_date', label: '終了日', index: 20, defaultVisible: true },
        { key: 'priority', label: '優先度', index: 21, defaultVisible: true },
        { key: 'amount', label: '総額', index: 22, defaultVisible: false },
        { key: 'customer_info', label: '顧客情報', index: 23, defaultVisible: true },
        { key: 'parent_guis_receiver', label: 'GUIS 受付者', index: 24, defaultVisible: false }
    ];
    // Số cột base trước khi chèn các cột custom (bắt đầu từ CAILY納期)
    const BASE_CUSTOM_START_INDEX = COLUMN_DEFINITIONS.find(col => col.key === 'caily_nouki').index;
    
    function escapeHtmlForNote(s) {
        if (s == null || s === '') return '';
        const t = String(s);
        return t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /** Format date-time for display (date and time on separate lines, no year; locale + timezone aware) */
    function formatDateTimeWithLineBreak(v) {
        if (typeof moment === 'undefined' || !v) return '';
        var m = toProjectDisplayMoment(v);
        if (!m) return '';
        return formatProjectDateOnly(m) + '<br>' + formatProjectTimeOnly(m);
    }

    function decodeHtmlForNote(s) {
        if (s == null || s === '') return '';
        const t = String(s);
        // Giải mã các entity HTML cơ bản + &nbsp; thành khoảng trắng thường
        return t
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&quot;/g, '"')
            .replace(/&amp;/g, '&')
            .replace(/&nbsp;/g, ' ');
    }

    function isVietnameseLocale() {
        if (typeof i18next === 'undefined' || !i18next.isInitialized) return false;
        return String(i18next.language || '').toLowerCase().indexOf('vi') === 0;
    }

    var SERVER_TASK_TIMEZONE = 'Asia/Tokyo';
    var VIETNAM_TASK_TIMEZONE = 'Asia/Ho_Chi_Minh';
    var PROJECT_DATETIME_MOMENT_FORMAT = 'YYYY/M/D HH:mm';
    var PROJECT_DATETIME_JA_DISPLAY_FORMAT = 'YYYY年M月D日 HH:mm';
    var PROJECT_DATETIME_FLATPICKR_FORMAT = 'Y/m/d H:i';
    var PROJECT_DATETIME_FLATPICKR_JA_ALT_FORMAT = 'Y年n月j日 H:i';
    var PROJECT_DATETIME_FLATPICKR_MOMENT_FORMAT = 'Y/M/D H:mm';
    var PROJECT_DATETIME_PARSE_FORMATS = [
        'YYYY-MM-DD HH:mm:ss',
        'YYYY-MM-DD HH:mm',
        'YYYY/M/D HH:mm',
        'YYYY/MM/DD HH:mm',
        'YYYY/M/D H:mm',
        'YYYY/MM/DD H:mm',
        'Y/M/D H:mm',
        'Y/n/j H:i'
    ];

    function getProjectDisplayTimezone() {
        return isVietnameseLocale() ? VIETNAM_TASK_TIMEZONE : SERVER_TASK_TIMEZONE;
    }

    function getProjectFlatpickrLocale() {
        if (typeof window === 'undefined' || !window.flatpickr || !window.flatpickr.l10ns) {
            return 'default';
        }
        if (isVietnameseLocale()) {
            return window.flatpickr.l10ns.vi || 'default';
        }
        return window.flatpickr.l10ns.ja || 'default';
    }

    function makeQuickEditTimeInputsEditable(selectedDates, dateStr, instance) {
        var cal = instance && instance.calendarContainer;
        if (!cal) return;
        var inputs = cal.querySelectorAll('.flatpickr-time input, .flatpickr-time .numInputWrapper input');
        for (var i = 0; i < inputs.length; i++) {
            inputs[i].removeAttribute('readonly');
            inputs[i].readOnly = false;
        }
    }

    function getProjectFlatpickrOptions(extra) {
        var options = {
            enableTime: true,
            time_24hr: true,
            dateFormat: PROJECT_DATETIME_FLATPICKR_FORMAT,
            allowInput: true,
            locale: getProjectFlatpickrLocale(),
            onOpen: makeQuickEditTimeInputsEditable
        };
        if (!isVietnameseLocale()) {
            options.altInput = true;
            options.altFormat = PROJECT_DATETIME_FLATPICKR_JA_ALT_FORMAT;
            options.altInputClass = 'form-control';
        }
        if (extra) {
            Object.keys(extra).forEach(function(key) {
                options[key] = extra[key];
            });
        }
        return options;
    }

    function parseProjectDateTimeInDisplayTz(value) {
        if (value === undefined || value === null) return null;
        var s = String(value).trim();
        if (!s || s === '-' || s === '0000-00-00 00:00:00' || s === '0000-00-00') return null;
        if (typeof moment === 'undefined') return null;
        var tz = getProjectDisplayTimezone();
        if (moment.tz) {
            for (var i = 0; i < PROJECT_DATETIME_PARSE_FORMATS.length; i++) {
                var parsed = moment.tz(s, PROJECT_DATETIME_PARSE_FORMATS[i], tz);
                if (parsed.isValid()) return parsed;
            }
            var normalized = s.replace(/\//g, '-');
            var normalizedFormats = ['YYYY-MM-DD HH:mm:ss', 'YYYY-MM-DD HH:mm', 'YYYY-M-D HH:mm', 'YYYY-MM-DD', 'YYYY-M-D'];
            for (var j = 0; j < normalizedFormats.length; j++) {
                var parsedNorm = moment.tz(normalized, normalizedFormats[j], tz);
                if (parsedNorm.isValid()) return parsedNorm;
            }
            var loose = moment.tz(s, tz);
            return loose.isValid() ? loose : null;
        }
        var fallback = moment(s, PROJECT_DATETIME_PARSE_FORMATS, true);
        return fallback.isValid() ? fallback : null;
    }

    function toProjectDateTimeInputValue(date) {
        var parsed = parseProjectDateMoment(date);
        if (!parsed || !parsed.isValid()) return '';
        var localized = moment.tz
            ? parsed.clone().tz(getProjectDisplayTimezone())
            : parsed;
        return localized.format(PROJECT_DATETIME_FLATPICKR_MOMENT_FORMAT);
    }

    function fromProjectDateTimeInputValue(value) {
        var raw = String(value || '').trim();
        if (!raw) return '';
        var parsed = parseProjectDateTimeInDisplayTz(raw);
        if (!parsed) return raw;
        if (moment.tz) {
            return parsed.clone().tz(SERVER_TASK_TIMEZONE).format(PROJECT_DATETIME_MOMENT_FORMAT);
        }
        return parsed.format(PROJECT_DATETIME_MOMENT_FORMAT);
    }

    function getProjectDateTimePlaceholder() {
        return isVietnameseLocale() ? 'YYYY/M/D HH:mm' : 'YYYY年M月D日 HH:mm';
    }

    function getCustomFieldDefaultHour() {
        return isVietnameseLocale() ? 17 : 19;
    }

    function getStartDateDefaultHour() {
        return isVietnameseLocale() ? 7 : 9;
    }

    function getDeadlineDefaultHour() {
        return isVietnameseLocale() ? 16 : 18;
    }

    function initQuickEditFlatpickr(target, extra) {
        var $el = (target && target.jquery) ? target : $(target);
        if (!$el.length || typeof $().flatpickr !== 'function') return;
        if ($el.data('flatpickr')) $el.data('flatpickr').destroy();
        var currentVal = ($el.val() || '').trim();
        $el.flatpickr(getProjectFlatpickrOptions(extra || {}));
        if (currentVal) {
            var fp = $el.data('flatpickr');
            if (fp) {
                fp.setDate(currentVal, false, PROJECT_DATETIME_FLATPICKR_FORMAT);
            }
        }
    }

    function toProjectDisplayMoment(value) {
        var parsed = parseProjectDateMoment(value);
        if (!parsed || !parsed.isValid()) return null;
        if (typeof moment.tz === 'function') {
            return parsed.clone().tz(getProjectDisplayTimezone());
        }
        return parsed;
    }

    function formatProjectDateOnly(momentObj) {
        if (!momentObj || !momentObj.isValid()) return '';
        return isVietnameseLocale()
            ? momentObj.format('M/D')
            : momentObj.format('M月D日');
    }

    function formatProjectTimeOnly(momentObj) {
        if (!momentObj || !momentObj.isValid()) return '';
        return momentObj.format('H:mm');
    }

    function formatProjectDateTimeInline(value) {
        var m = toProjectDisplayMoment(value);
        if (!m) return '-';
        return formatProjectDateOnly(m) + ' ' + formatProjectTimeOnly(m);
    }

    function getBranchRomaji(branchName) {
        return (window.ProjectClipboard && window.ProjectClipboard.getBranchRomaji)
            ? window.ProjectClipboard.getBranchRomaji(branchName) : '';
    }

    function formatBranchNameForDisplay(branchName) {
        var raw = String(branchName || '').trim();
        if (!raw) return '';
        if (!isVietnameseLocale()) return raw;
        if (/\([A-Za-z0-9\-\s]+\)$/.test(raw)) return raw;
        var romaji = getBranchRomaji(raw);
        return romaji ? (raw + '<br>' + romaji) : raw;
    }

    /** Giải mã HTML (giống cột CAILYメモ), strip thẻ, rồi cắt còn maxLen ký tự cho snippet note. */
    function noteSnippetText(content, maxLen) {
        if (content == null) return { short: '', full: '' };
        var raw = String(content);
        var decoded = decodeHtmlForNote(raw);
        decoded = (decoded || '').replace(/\u00A0/g, ' ').replace(/&nbsp;/gi, ' ');
        var full = decoded.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
        var short = full.length <= (maxLen || 40) ? full : full.substring(0, maxLen || 40) + '…';
        return { short: short, full: full };
    }

    function saveColumnVisibilityToLocalStorage(visibility) {
        localStorage.setItem(COLUMN_VISIBILITY_KEY, JSON.stringify(visibility));
    }
    
    function loadColumnVisibilityFromLocalStorage(customColDefs) {
        const saved = JSON.parse(localStorage.getItem(COLUMN_VISIBILITY_KEY) || '{}');
        const visibility = {};
        COLUMN_DEFINITIONS.forEach(col => {
            if (isColumnHiddenForCailyBranch(col.key)) return;
            visibility[col.key] = saved[col.key] !== undefined ? saved[col.key] : col.defaultVisible;
        });
        (customColDefs || []).forEach(col => {
            visibility[col.key] = saved[col.key] !== undefined ? saved[col.key] : (col.defaultVisible !== undefined ? col.defaultVisible : false);
        });
        return visibility;
    }
    
    // Index cột trong DataTable theo columns.name (ổn định khi ColReorder đổi thứ tự)
    function getDataTableColumnIndexByKey(columnKey, customColDefs) {
        var dt = projectTable;
        if (!dt || typeof $.fn.DataTable === 'undefined' || !$.fn.DataTable.isDataTable('#projectTable')) {
            return null;
        }
        try {
            var col = dt.column(columnKey + ':name');
            var idx = col.index();
            return typeof idx === 'number' ? idx : null;
        } catch (e) {
            return null;
        }
    }

    function applyColumnVisibility(table, visibility, customColDefs) {
        if (!table || !$.fn.DataTable.isDataTable('#projectTable')) {
            return;
        }
        const customDefs = customColDefs || customFieldColumnDefinitions || [];
        COLUMN_DEFINITIONS.forEach(col => {
            if (isColumnHiddenForCailyBranch(col.key)) return;
            const isVisible = visibility[col.key] !== false;
            const dtIndex = getDataTableColumnIndexByKey(col.key, customDefs);
            if (dtIndex !== null) {
                table.column(dtIndex).visible(isVisible, false);
            }
        });
        customDefs.forEach((col, idx) => {
            const isVisible = visibility[col.key] !== false;
            const dtIndex = getDataTableColumnIndexByKey(col.key, customDefs);
            if (dtIndex !== null) {
                table.column(dtIndex).visible(isVisible, false);
            }
        });
        table.columns.adjust().draw(false);
    }

    // Custom field columns (built when table is initialized for selected department)
    var customFieldColumnDefinitions = [];
    // Map base custom field label -> array of status fields ({ label, type, options })
    var customFieldStatusMap = {};

    function customFieldKey(label) {
        return 'custom_' + String(label).replace(/\s+/g, '_').replace(/[^\w\u3040-\u309F\u30A0-\u30FF\u4E00-\u9FAF]/g, '_');
    }

    function getCustomFieldValueFromRow(row, label) {
        if (!row || !row.custom_fields) return '';
        var raw = row.custom_fields;
        if (typeof raw === 'string' && raw.indexOf('&quot;') !== -1) {
            raw = raw.replace(/&quot;/g, '"');
        }
        var arr = [];
        try {
            arr = typeof raw === 'string' ? JSON.parse(raw) : (Array.isArray(raw) ? raw : []);
        } catch (e) {
            return '';
        }
        var found = arr.find(function (f) { return f && f.label && String(f.label).trim() === String(label).trim(); });
        return found && found.value !== undefined ? found.value : '';
    }

    function formatCustomFieldForList(value, type, options, row, label) {
        if (value === undefined || value === null || String(value).trim() === '') return '<span class="text-muted">-</span>';
        var v = String(value).trim();
        var todoAttrs = (typeof getTodoDataAttrs === 'function' && row ? getTodoDataAttrs(row, label) : '');
        if (type === 'datetime') {
            var rawEsc = v.replace(/\//g, '-').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            var attrs = ' data-time="' + rawEsc + '"' + todoAttrs;
            if (typeof window.formatVietnamTimeTooltip === 'function') {
                var vnTip = window.formatVietnamTimeTooltip(v);
                if (vnTip) attrs += ' data-bs-toggle="tooltip" data-bs-title="' + vnTip.replace(/"/g, '&quot;') + '"';
            }
            if (typeof moment !== 'undefined') {
                var m = toProjectDisplayMoment(v);
                if (!m) {
                    if (typeof window.formatDateTime === 'function') return '<span class="small text-nowrap"' + attrs + '>' + window.formatDateTime(v) + '</span>';
                    return '<span class="small text-nowrap"' + attrs + '>' + v + '</span>';
                }
                var displayStr = formatDateTimeWithLineBreak(v);
                var now = typeof moment.tz === 'function'
                    ? moment.tz(getProjectDisplayTimezone())
                    : moment();
                var isToday = m.isSame(now, 'day');
                var isOverdue = m.isBefore(now);
                
                // Kiểm tra xem có trường trạng thái kèm theo chứa '送信済み' hay không (đã gửi)
                var hasSentStatus = false;
                if (typeof customFieldStatusMap !== 'undefined' && customFieldStatusMap[label] && row) {
                    var statusDefs = customFieldStatusMap[label] || [];
                    for (var i = 0; i < statusDefs.length; i++) {
                        var sf = statusDefs[i];
                        var sv = getCustomFieldValueFromRow(row, sf.label);
                        if (sv && String(sv).indexOf('送信済み') !== -1) {
                            hasSentStatus = true;
                            break;
                        }
                    }
                }
                
                var badgeHtml = '';
                if (isToday) {
                    var todayText = (typeof translateText === 'function' ? translateText('本日') : '本日');
                    badgeHtml = '<span class="badge bg-label-primary mt-1" style="font-size: 0.7rem; padding: 0.2rem 0.4rem;">' + todayText + '</span>';
                }
                if (isOverdue && !hasSentStatus) {
                    var lateText = (typeof translateText === 'function' ? translateText('遅れ') : '遅れ');
                    badgeHtml += '<span class="badge bg-label-danger mt-1" style="font-size: 0.7rem; padding: 0.2rem 0.4rem;">' + lateText + '</span>';
                }
                
                if (badgeHtml) {
                    return '<div class="d-flex flex-column">' +
                        '<span class="small"' + attrs + '>' + displayStr + '</span>' +
                        badgeHtml +
                    '</div>';
                }
                return '<span class="small"' + attrs + '>' + displayStr + '</span>';
            }
            if (typeof window.formatDateTime === 'function') return '<span class="small text-nowrap"' + attrs + '>' + window.formatDateTime(v) + '</span>';
            return '<span class="small text-nowrap"' + attrs + '>' + v + '</span>';
        }
        if (type === 'checkbox' || type === 'radio' || type === 'select') {
            var badgeClass = 'bg-label-secondary';
            // Nếu giá trị chứa '問題' hoặc 'エラー' → đỏ
            if (v.indexOf('問題') !== -1 || v.indexOf('エラー') !== -1) {
                badgeClass = 'bg-label-danger';
            } else if (v.indexOf('済み') !== -1) {
                // Nếu chứa '済み' → xanh
                badgeClass = 'bg-label-success';
            }
            return '<span class="badge ' + badgeClass + ' small">' + (typeof translateText === 'function' ? translateText(escapeHtmlForNote(v)) : escapeHtmlForNote(v)) + '</span>';
        }
        if (type === 'textarea' || type === 'text') {
            var short = v.length > 40 ? v.substring(0, 40) + '...' : v;
            return '<span data-bs-toggle="tooltip" data-bs-title="' + escapeHtmlForNote(v) + '" class="small" style="white-space: pre-wrap; width: 100px; display: block;">' + escapeHtmlForNote(short) + '</span>';
        }
        // Text field: nếu giống ngày (yyyy/m/d, m/d, yyyy/mm/dd hoặc có thời gian) thì format
        if (typeof moment !== 'undefined') {
            var m = toProjectDisplayMoment(v);
            if (m) {
                var serverMoment = parseProjectDateMoment(v);
                var timeStr = serverMoment && serverMoment.isValid()
                    ? serverMoment.format('YYYY/M/D H:mm')
                    : v;
                var vnTip = (typeof window.formatVietnamTimeTooltip === 'function') ? window.formatVietnamTimeTooltip(timeStr) : '';
                var attrs = ' data-time="' + String(timeStr).replace(/"/g, '&quot;') + '"' + todoAttrs;
                if (vnTip) attrs += ' data-bs-toggle="tooltip" data-bs-title="' + vnTip.replace(/"/g, '&quot;') + '"';
                return '<span class="text-nowrap small"' + attrs + '>' + formatProjectDateOnly(m) + '</span>';
            }
        }
        return '<span class="text-break small">' + escapeHtmlForNote(v) + '</span>';
    }

    function normalizeFilterKeyword(value) {
        if (value === undefined || value === null) {
            return '';
        }
        return String(value).trim();
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
        $el.append(new Option(translateText('未割り当て'), 'none', false, saved.indexOf('none') !== -1));
        (teams || []).forEach(function(team) {
            if (team.id != null) {
                const id = String(team.id);
                const name = team.name || id;
                $el.append(new Option(name, id, false, saved.indexOf(id) !== -1));
            }
        });
        $el.select2({
            placeholder: translateText('すべて'),
            allowClear: true,
            width: '100%',
            dropdownAutoWidth: true,
            closeOnSelect: false,
            language: {
                noResults: function() {
                    return typeof translateText === 'function' ? translateText('検索結果がありません') : '検索結果がありません';
                }
            }
        });
        bindFilterTeamSelect2Events($el);
        syncFilterTeamSelect2Value($el, saved);
    }

    function saveFiltersToLocalStorage() {
        const filters = {
            filterStartMonth: $('#filterStartMonth').val(),
            filterEndMonth: $('#filterEndMonth').val(),
            filterPriority: $('#filterPriority').val(),
            filterProgress: $('#filterProgress').val(),
            filterTimeLeft: $('#filterTimeLeft').val(),
            filterToday: $('#filterToday').val(),
            filterProjectOrderType: $('#filterProjectOrderType').val(),
            filterTeam: getFilterTeamValue(),
            filterTantou: $('#filterTantou').val(),
            filterNoDates: $('#filterNoDates').is(':checked') ? 1 : 0,
            filterKeyword: normalizeFilterKeyword($('#filterKeyword').val()),
            filterProjectId: $('#filterProjectId').val(),
            showInactive: $('#showInactiveSwitch').is(':checked') ? 1 : 0,
            myProjects: app && app.filterMyProjects ? 1 : 0,
            favorites_only: $('#filterFavoritesOnly').is(':checked') ? 1 : 0
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
        if (params.has('filterToday')) merged.filterToday = params.get('filterToday') || '';
        if (params.has('filterProjectOrderType')) merged.filterProjectOrderType = params.get('filterProjectOrderType') || '';
        if (params.has('filterTeam')) merged.filterTeam = parseFilterTeamValue(params.get('filterTeam') || '');
        if (params.has('filterTantou')) merged.filterTantou = params.get('filterTantou') || '';
        if (params.has('filterNoDates')) merged.filterNoDates = getBool('filterNoDates');
        if (params.has('filterKeyword')) merged.filterKeyword = normalizeFilterKeyword(params.get('filterKeyword') || '');
        if (params.has('filterProjectId')) merged.filterProjectId = params.get('filterProjectId') || '';
        if (params.has('showInactive')) merged.showInactive = getBool('showInactive');
        if (params.has('my_projects')) merged.myProjects = getBool('my_projects');
        if (params.has('favorites_only')) merged.favorites_only = getBool('favorites_only');
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
        if (filters.filterToday !== undefined) $('#filterToday').val(filters.filterToday);
        if (filters.filterProjectOrderType !== undefined) $('#filterProjectOrderType').val(filters.filterProjectOrderType);
        // filterTeam: refreshFilterTeamSelect() khôi phục từ localStorage sau khi load teams
        if (filters.filterTantou !== undefined) $('#filterTantou').val(filters.filterTantou);
        if (filters.filterNoDates !== undefined) $('#filterNoDates').prop('checked', filters.filterNoDates == 1);
        if (filters.filterKeyword !== undefined) $('#filterKeyword').val(normalizeFilterKeyword(filters.filterKeyword));
        if (filters.filterProjectId !== undefined) $('#filterProjectId').val(filters.filterProjectId);
        if (filters.showInactive !== undefined) $('#showInactiveSwitch').prop('checked', filters.showInactive == 1);
        if (filters.myProjects !== undefined && app) app.filterMyProjects = filters.myProjects == 1;
        // Only restore favorites_only if it's explicitly set in filters (not undefined)
        if (filters.favorites_only !== undefined) {
            $('#filterFavoritesOnly').prop('checked', filters.favorites_only == 1);
            if (app) app.showClearAllFavoritesBtn = filters.favorites_only == 1;
        } else {
            // If not in filters, ensure it's unchecked
            $('#filterFavoritesOnly').prop('checked', false);
            if (app) app.showClearAllFavoritesBtn = false;
        }
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
            today: filters.filterToday || '',
            projectOrderType: filters.filterProjectOrderType || '',
            teamIds: parseFilterTeamValue(filters.filterTeam),
            tantou: filters.filterTantou || '',
            noDates: filters.filterNoDates == 1,
            keyword: normalizeFilterKeyword(filters.filterKeyword),
            showInactive: filters.showInactive == 1,
            myProjects: filters.myProjects == 1,
            statusKey: filters.statusKey || '',
            department_id: filters.department_id || null
        };
    }

    function renderActiveFilters() {
        const filters = getFiltersFromLocalStorage();
        const teamIds = filters.teamIds || [];
        const badges = [];
        // Nếu tất cả filter đều rỗng, không hiển thị gì
        if (
            (!filters.startMonth || filters.startMonth.trim() === '') &&
            (!filters.endMonth || filters.endMonth.trim() === '') &&
            (!filters.priority || filters.priority.trim() === '') &&
            (!filters.progress || filters.progress.trim() === '') &&
            (!filters.timeLeft || filters.timeLeft.trim() === '') &&
            (!filters.today || filters.today.trim() === '') &&
            (!filters.projectOrderType || filters.projectOrderType.trim() === '') &&
            teamIds.length === 0 &&
            (!filters.tantou || filters.tantou.trim() === '') &&
            !filters.noDates &&
            !filters.myProjects &&
            !filters.showInactive &&
            (!filters.keyword || filters.keyword.trim() === '')
        ) {
            $('#activeFilters').html('');
            return;
        }
        if (filters.today && filters.today.trim() !== '') {
            const todayVal = filters.today.trim();
            // Built-in today filters
            if (todayVal === 'start_today' || todayVal === 'caily_today' || todayVal === 'guis_today' || todayVal === 'end_today') {
                let labelKey = '';
                switch (todayVal) {
                    case 'start_today':
                        labelKey = '開始日=本日';
                        break;
                    case 'caily_today':
                        labelKey = 'CAILY納期=本日';
                        break;
                    case 'guis_today':
                        labelKey = 'GUIS納期=本日';
                        break;
                    case 'end_today':
                        labelKey = '終了日=本日';
                        break;
                }
                if (labelKey) {
                    const label = translateText(labelKey);
                    badges.push(`<span class="badge bg-label-primary me-1">${label}</span>`);
                }
            } else if (todayVal.indexOf('cf:') === 0) {
                // Custom datetime field today filter: value = 'cf:' + encodeURIComponent(label)
                try {
                    const encoded = todayVal.substring(3);
                    const rawLabel = decodeURIComponent(encoded || '');
                    if (rawLabel) {
                        const text = escapeHtmlForNote(rawLabel + '=本日');
                        badges.push(`<span class="badge bg-label-primary me-1">${text}</span>`);
                    }
                } catch (e) {
                    // ignore decode error
                }
            }
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
            if (teamIds.length > 0) {
                const teamNames = teamIds.map(function(id) {
                    if (id === 'none') {
                        return translateText('未割り当て');
                    }
                    return teamIdToName[id] || id;
                }).join(', ');
                badges.push(`<span class="badge bg-label-info me-1" >チーム: ${teamNames}</span>`);
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
            $('#activeFilters').html(`<span class="me-2 text-muted small" ><span data-i18n="適用中のフィルター">適用中のフィルター</span>:</span>` + badges.join(''));
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
        setOrDelete('filterToday', filters.filterToday);
        setOrDelete('filterProjectOrderType', filters.filterProjectOrderType);
        setOrDelete('filterTeam', formatFilterTeamForApi(filters.filterTeam));
        setOrDelete('filterTantou', filters.filterTantou);
        setOrDelete('filterNoDates', filters.filterNoDates ? 1 : '');
        setOrDelete('filterKeyword', normalizeFilterKeyword(filters.filterKeyword));
        setOrDelete('filterProjectId', filters.filterProjectId);
        setOrDelete('showInactive', filters.showInactive ? 1 : '');
        setOrDelete('my_projects', filters.myProjects ? 1 : '');
        setOrDelete('favorites_only', filters.favorites_only ? 1 : '');
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

    // Destroy DataTable khi đổi department để refresh đúng custom fields và dropdown 列の表示
    function destroyProjectTable() {
        if (projectTable && $.fn.DataTable.isDataTable('#projectTable')) {
            try {
                // Hủy ajax request đang chờ để tránh response về sau khi destroy gây lỗi mData
                var settings = projectTable.settings();
                if (settings && settings[0] && settings[0].jqXHR && typeof settings[0].jqXHR.abort === 'function') {
                    try { settings[0].jqXHR.abort(); } catch (e) {}
                }
                projectTable.destroy();
            } catch (e) {
                console.warn('DataTable destroy error:', e);
            }
            projectTable = null;
            // Xóa nội dung table để init lại sạch, tránh lỗi mData do DOM cũ
            var $tbl = $('#projectTable');
            if ($tbl.length) $tbl.empty();
        }
        customFieldColumnDefinitions = [];
        // Refresh dropdown 列の表示: chỉ còn cột cơ bản, bỏ hết custom field của department cũ
        if (typeof app !== 'undefined' && app) {
            var baseVis = loadColumnVisibilityFromLocalStorage([]);
            var depId = app.selectedDepartment && app.selectedDepartment.id;
            app.availableColumns = buildAvailableColumnsList([], depId, baseVis);
            scheduleColumnVisibilityMenuI18n();
        }
    }

    // Function to initialize DataTable
    async function initializeProjectTable() {
        // Check if DataTable is already initialized (đổi department thì gọi destroyProjectTable trước)
        if (projectTable && $.fn.DataTable.isDataTable('#projectTable')) {
            if (app) app.loading = false;
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
            if (app) app.loading = false;
            return;
        }
        
        // Set flag to prevent multiple initializations
        isInitializingTable = true;
        try {
        // Ensure DataTables processing indicator is centered in the window (not only inside table)
        if (!document.getElementById('project-table-processing-style')) {
            var processingStyleEl = document.createElement('style');
            processingStyleEl.id = 'project-table-processing-style';
            processingStyleEl.textContent =
                '#projectTable_wrapper .dataTables_processing {' +
                'position: fixed !important;' +
                'top: 50% !important;' +
                'left: 50% !important;' +
                'transform: translate(-50%, -50%) !important;' +
                'margin: 0 !important;' +
                'z-index: 2000 !important;' +
                '}' +
                '#projectTable_wrapper .dataTables_processing .dt-processing {' +
                'box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15);' +
                '}';
            document.head.appendChild(processingStyleEl);
        }

        // Load team map (id -> name) for display in table (badge, tooltip, ...)
        await loadTeamMap();
        
        // Khôi phục filter từ localStorage trước khi load projectTable
        loadFiltersFromLocalStorage();
        if (app && Array.isArray(app.teams)) {
            refreshFilterTeamSelect(app.teams);
        }
        projectData = [];

        // Fetch custom field sets for current department and build custom columns (at end of table, default hidden)
        customFieldColumnDefinitions = [];
        customFieldStatusMap = {};
        var customColumnConfigs = [];
        try {
            var cfRes = await axios.get('/api/index.php?model=department&method=getCustomFields');
            var sets = cfRes.data || [];
            var depId = app.selectedDepartment && app.selectedDepartment.id;
            var mergedFields = [];
            var statusSuffix = '状況';
            var statusMap = {};

            // Gom các trường custom theo label; nếu label kết thúc bằng '状況' thì coi là cột trạng thái cho base label
            sets.filter(function(s) { return s.department_id == depId; }).forEach(function(s) {
                if (s.fields && Array.isArray(s.fields)) {
                    s.fields.forEach(function(f) {
                        if (!f || !f.label) return;
                        var label = String(f.label || '').trim();
                        var type = f.type || 'text';
                        var options = f.options || '';

                        if (label.endsWith(statusSuffix)) {
                            // Ví dụ: '構造データ送付 (CAILY)状況' -> baseLabel = '構造データ送付 (CAILY)'
                            var baseLabel = label.replace(/状況\s*$/,'').trim();
                            if (!statusMap[baseLabel]) statusMap[baseLabel] = [];
                            statusMap[baseLabel].push({ label: label, type: type, options: options });
                        } else {
                            if (!mergedFields.some(function(ex) { return ex.label && String(ex.label).trim() === label; })) {
                                mergedFields.push({
                                    label: label,
                                    type: type,
                                    options: options,
                                    one_row: (f.one_row === 1 || f.one_row === '1' || f.one_row === true)
                                });
                            }
                        }
                    });
                }
            });

            customFieldStatusMap = statusMap;

            mergedFields.forEach(function(f, i) {
                var key = customFieldKey(f.label);
                // Index trong DataTable: ngay sau các cột base trước custom
                customFieldColumnDefinitions.push({ key: key, label: f.label, type: f.type, options: f.options || '', index: BASE_CUSTOM_START_INDEX + i, defaultVisible: true });
                var fieldLabel = f.label;
                var fieldType = f.type;
                var fieldOptions = f.options || '';
                customColumnConfigs.push({
                    name: key,
                    data: null,
                    render: function(data, type, row) {
                        var val = getCustomFieldValueFromRow(row, fieldLabel);
                        var mainHtml = formatCustomFieldForList(val, fieldType, fieldOptions, row, fieldLabel);

                        var statusDefs = (typeof customFieldStatusMap !== 'undefined' && customFieldStatusMap[fieldLabel]) || [];
                        if (!statusDefs.length) return mainHtml;

                        var parts = [mainHtml];
                        statusDefs.forEach(function(sf) {
                            var sv = getCustomFieldValueFromRow(row, sf.label);
                            if (sv !== undefined && String(sv).trim() !== '') {
                                var statusHtml = formatCustomFieldForList(sv, sf.type, sf.options || '', row, sf.label);
                                parts.push('<div class="mt-1">' + statusHtml + '</div>');
                            }
                        });
                        return parts.join('');
                    },
                    title: buildI18nHeaderTitle(fieldLabel),
                    orderable: false,
                    visible: true,
                    width: '80px'
                });
            });

            // Bổ sung các custom datetime fields vào filter "本日フィルター"
            var $today = $('#filterToday');
            if ($today.length) {
                // Xóa các option custom cũ (giữ lại option mặc định/built-in)
                $today.find('option[data-custom-today="1"]').remove();
                mergedFields.forEach(function(f) {
                    if (!f || String(f.type || '').toLowerCase() !== 'datetime') return;
                    var label = String(f.label || '').trim();
                    if (!label) return;
                    var value = 'cf:' + encodeURIComponent(label);
                    var text = label + '=本日';
                    var opt = $('<option>')
                        .val(value)
                        .attr('data-custom-today', '1')
                        .text(text);
                    $today.append(opt);
                });
            }
        } catch (e) {
            console.warn('Failed to load custom fields for list', e);
        }

        var fixedColumnConfigs = [
                { 
                    name: 'is_favorite',
                    data: 'is_favorite',
                    orderable: false,
                    render: function(data, type, row) {
                        if (type === 'sort' || type === 'type') {
                            return data || 0;
                        }
                        const isFavorite = row.is_favorite == 1;
                        const starHtml = `<div class="d-flex flex-column align-items-center gap-1"><i class="fa fa-star ${isFavorite ? 'text-warning' : 'text-muted'}" 
                                   style="cursor: pointer; font-size: 1.2em;"
                                   onclick="window.toggleProjectFavorite(${row.id}, this)"
                                   title="${isFavorite ? 'お気に入りから削除' : 'お気に入りに追加'}"></i></div>`;
                        
                        // Collect all badges to display
                        const badges = [];
                        
                        // Start date label
                        const startLabel = getStartDateLabel(row.start_date);
                        if (startLabel) {
                            var startText = (typeof translateText === 'function' ? translateText(startLabel.text) : startLabel.text);
                            if (startText === startLabel.text && (startLabel.text === '開始今日' || startLabel.text === '開始明日')) {
                                startText = (typeof translateText === 'function' ? translateText(startLabel.text === '開始今日' ? 'start_today' : 'start_tomorrow') : startLabel.text);
                                if (startText === 'start_today' || startText === 'start_tomorrow') startText = startLabel.text;
                            }
                            badges.push('<span class="badge ' + startLabel.class + '" style="font-size: 0.65rem; padding: 0.15rem 0.35rem; white-space: nowrap;">' + startText + '</span>');
                        }
                        
                        // Overdue label — i18n
                        if (isProjectOverdue(row)) {
                            var overdueText = (typeof translateText === 'function' ? translateText('期限超過') : '期限超過');
                            badges.push('<span class="badge bg-danger" style="font-size: 0.65rem; padding: 0.15rem 0.35rem; white-space: nowrap;">' + overdueText + '</span>');
                        }
                        
                        // Period undecided label — i18n
                        if (isPeriodUndecided(row)) {
                            var undecidedText = (typeof translateText === 'function' ? translateText('期間未定') : '期間未定');
                            badges.push('<span class="badge bg-label-warning" style="font-size: 0.65rem; padding: 0.15rem 0.35rem; white-space: nowrap;">' + undecidedText + '</span>');
                        }

                        // NEW badge: 作成から6時間未満 (Japan timezone)
                        if (row.created_at) {
                            var nowJst = moment.tz ? moment.tz('Asia/Tokyo') : moment();
                            var created = moment.tz ? moment.tz(row.created_at, 'Asia/Tokyo') : moment(row.created_at);
                            if (created.isValid() && nowJst.diff(created, 'hours', true) < 6) {
                                var newText = (typeof translateText === 'function' ? translateText('NEW') : 'NEW');
                                badges.push('<span class="badge bg-success" style="font-size: 0.65rem; padding: 0.15rem 0.35rem; white-space: nowrap;">' + newText + '</span>');
                            }
                        }

                        if (badges.length > 0) {
                            return '<div class="d-flex flex-column align-items-center gap-1">' +
                                starHtml +
                                badges.join('') +
                                '</div>';
                        }
                        
                        return starHtml;
                    },
                    title: '',
                    orderable: false,
                    width: '70px'
                },
                { 
                    name: 'id',
                    data: 'id',
                    className: 'project-id-cell',
                    render: function(data, type, row) {
                        return `<div class="d-flex align-items-center project-hover-tasks-trigger" data-project-id="${row.id}">
                                    <a href="detail.php?id=${row.id}" class="text-decoration-none"><span class="project-id border border-primary px-1 py-1 small text-center" style="min-width: 3em; display: inline-block;">${data || '-'}</span></a>
                                </div>`;
                    },
                    title: '<span data-i18n="ID">ID</span>',
                    width: '40px'
                },
                { 
                    name: 'confirmation_notes_caily',
                    data: 'confirmation_notes_caily',
                    width: '250px',
                    className: 'confirmation-notes-column',
                    render: function(data, type, row) {
                        if (type !== 'display') {
                            return data || '';
                        }
                        // Return empty string, HTML will be set in createdCell
                        return '';
                    },
                    createdCell: function(td, cellData, rowData, row, col) {
                        $(td).attr('data-notes-type', 'caily');
                        // Set HTML trực tiếp để đảm bảo HTML được render đúng cách
                        if (!cellData || cellData === '') {
                            $(td).html(`<div class="empty-notes-cell" data-project-id="${rowData.id}">
                                        <span class="text-muted empty-notes-text">-</span>
                                        <span class="add-note-icon d-none" title="メモを追加" style="cursor: pointer;">
                                            <i class="fa fa-pencil-alt text-primary"></i>
                                        </span>
                                    </div>`);
                            return;
                        }
                        // Định dạng data: "noteId_:_content_|_noteId_:_content_|_..."
                        const notes = cellData.split('_|_').filter(note => note.trim() !== '');
                        if (notes.length === 0) {
                            $(td).html(`<div class="empty-notes-cell" data-project-id="${rowData.id}">
                                        <span class="text-muted empty-notes-text">-</span>
                                        <span class="add-note-icon d-none" title="メモを追加" style="cursor: pointer;">
                                            <i class="fa fa-pencil-alt text-primary"></i>
                                        </span>
                                    </div>`);
                            return;
                        }
                        const html = notes.map(note => {
                            const raw = note.trim();
                            const delim = '_:_';
                            let arr = raw.split(delim);
                            let id = arr[0];
                            let text = arr[1];
                            let isImportant = arr[2];
                            let decodedText = text;
                            if (typeof decodeHtmlEntities !== 'undefined') {
                                decodedText = decodeHtmlEntities(text);
                            } else if (text.indexOf('&lt;') !== -1 || text.indexOf('&gt;') !== -1 || text.indexOf('&amp;') !== -1) {
                                decodedText = text.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&').replace(/&quot;/g, '"');
                            }
                            decodedText = (decodedText || '').replace(/\u00A0/g, ' ').replace(/&nbsp;/gi, ' ');
                            const isEditing = window.app && window.app.currentEditingNoteId === id;
                            return `
                                <div class="confirmation-note-item mb-1 ${isEditing ? 'editing-note' : ''} ${isImportant == 1 ? 'important-note' : ''}" ${id ? `data-note-id="${id}"` : ''}>
                                    <div class="note-text small ql-editor">${decodedText || '-'}</div>
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
                        $(td).html(`<div class="confirmation-notes-wrapper" style="max-height: 200px; overflow-y: auto;">${html}</div>`);
                    },
                    title: buildI18nHeaderTitle('CAILYメモ'),
                    orderable: false
                },
                { 
                    name: 'confirmation_notes_guis',
                    data: 'confirmation_notes_guis',
                    width: '250px',
                    className: 'confirmation-notes-column',
                    render: function(data, type, row) {
                        if (type !== 'display') {
                            return data || '';
                        }
                        // Return empty string, HTML will be set in createdCell
                        return '';
                    },
                    createdCell: function(td, cellData, rowData, row, col) {
                        $(td).attr('data-notes-type', 'guis');
                        // Set HTML trực tiếp để đảm bảo HTML được render đúng cách
                        if (!cellData || cellData === '') {
                            $(td).html(`<div class="empty-notes-cell" data-project-id="${rowData.id}">
                                        <span class="text-muted empty-notes-text">-</span>
                                        <span class="add-note-icon d-none" title="メモを追加" style="cursor: pointer;">
                                            <i class="fa fa-pencil-alt text-primary"></i>
                                        </span>
                                    </div>`);
                            return;
                        }
                        // Định dạng data: "noteId_:_content_|_noteId_:_content_|_..."
                        const notes = cellData.split('_|_').filter(note => note.trim() !== '');
                        if (notes.length === 0) {
                            $(td).html(`<div class="empty-notes-cell" data-project-id="${rowData.id}">
                                        <span class="text-muted empty-notes-text">-</span>
                                        <span class="add-note-icon d-none" title="メモを追加" style="cursor: pointer;">
                                            <i class="fa fa-pencil-alt text-primary"></i>
                                        </span>
                                    </div>`);
                            return;
                        }
                        const html = notes.map(note => {
                            const raw = note.trim();
                            const delim = '_:_';
                            let arr = raw.split(delim);
                            let id = arr[0];
                            let text = arr[1];
                            let isImportant = arr[2];
                            let decodedText = text;
                            if (typeof decodeHtmlEntities !== 'undefined') {
                                decodedText = decodeHtmlEntities(text);
                            } else if (text.indexOf('&lt;') !== -1 || text.indexOf('&gt;') !== -1 || text.indexOf('&amp;') !== -1) {
                                decodedText = text.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&').replace(/&quot;/g, '"');
                            }
                            decodedText = (decodedText || '').replace(/\u00A0/g, ' ').replace(/&nbsp;/gi, ' ');
                            const isEditing = window.app && window.app.currentEditingNoteId === id;
                            return `
                                <div class="confirmation-note-item mb-1 ${isEditing ? 'editing-note' : ''} ${isImportant == 1 ? 'important-note' : ''}" ${id ? `data-note-id="${id}"` : ''}>
                                    <div class="note-text small ql-editor">${decodedText || '-'}</div>
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
                        $(td).html(`<div class="confirmation-notes-wrapper" style="max-height: 200px; overflow-y: auto;">${html}</div>`);
                    },
                    title: buildI18nHeaderTitle('GUISメモ'),
                    orderable: false
                },
                {
                    name: 'status',
                    data: 'status',
                    render: function(data, type, row) {
                        // Return original data value for sorting
                        if (type === 'sort' || type === 'type') {
                            return data || '';
                        }
                        // Return HTML for display (i18n: translate status name)
                        const status = statuses.find(s => s.key === data);
                        const label = (status && status.name) ? (typeof translateText === 'function' ? translateText(status.name) : status.name) : (data || '');
                        return `<span class="badge bg-${status?.color || 'secondary'}">${label}</span>`;
                    },
                    title: '<span data-i18n="案件状況">案件状況</span>',
                    orderable: false,
                    width: '60px',
                },
                {
                    name: 'progress',
                    data: 'progress',
                    width: '56px',
                    className: 'project-progress-cell',
                    render: function(data, type, row) {
                        if (type === 'sort' || type === 'type') {
                            return data != null ? parseInt(data, 10) : 0;
                        }
                        const progress = data != null ? parseInt(data, 10) : 0;
                        const color = progress === 100 ? 'success' : 'primary';
                        const completed = parseInt(row.completed_task_count, 10) || 0;
                        const total = parseInt(row.task_count, 10) || 0;
                        const taskCountHtml = total > 0
                            ? `<small class="text-muted d-flex align-items-center gap-1 project-hover-tasks-trigger" data-project-id="${row.id}" style="font-size:0.75rem; cursor: default;">` +
                              `<i class="fas fa-tasks" style="font-size:0.7rem;"></i>` +
                              `<span>${completed}/${total}</span></small>`
                            : '';
                        return `<div class="progress" style="width: 50px;">
                                    <div class="progress-bar bg-${color}" role="progressbar" 
                                            style="width: ${progress}%" aria-valuenow="${progress}" 
                                            aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                                <small class="text-muted d-block">${progress}%</small>
                                ${taskCountHtml}`;
                    },
                    title: '<span data-i18n="進捗率">進捗率</span>'
                },
                { 
                    name: 'tantou',
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
                    className: 'tantou-column',
                    width: '80px',
                    visible: false
                },
                {
                    name: 'manager',
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
                    name: 'teams',
                    data: 'teams',
                    width: '60px',
                    orderable: false,
                    render: function(data, type, row) {
                        if (!data || data === '') {
                            return '<span class="badge bg-danger" data-i18n="未割り当て">'+translateText("未割り当て")+'</span>';
                        }
                        const ids = typeof data === 'string' ? data.split(',').map(item => item.trim()).filter(item => item) : [String(data)];
                        if (ids.length === 0) return '<span class="badge bg-danger" data-i18n="未割り当て">'+translateText("未割り当て")+'</span>';
                        const labels = ids.map(function(id) { return teamIdToName[id] || id; });
                        return labels.map(function(label) {
                            label = label.replace(/CL意匠/g, 'CL_').replace(/G意匠/g, 'G_');
                            return '<span class="badge bg-label-secondary me-1">' + label + '</span>'; }).join('');
                    },
                    title: '<span data-i18n="チーム">チーム</span>'
                },
                {
                    name: 'members',
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
                    name: 'parent_construction_number',
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
                    name: 'parent_branch_name',
                    width: '60px',
                    data: 'parent_branch_name',
                    render: function(data, type, row) {
                        if (!data || data === '') {
                            return '<span class="text-muted">-</span>';
                        }
                        const displayBranchName = formatBranchNameForDisplay(data);
                        return `<span class="small">${displayBranchName}</span>`;
                    },
                    title: '<span data-i18n="支店名">支店名</span>'
                },
                { 
                    name: 'name',
                    data: 'name',
                    width: '150px',
                    className: 'project-name-cell',
                    render: function(data, type, row) {
                        return `<div class="d-flex align-items-start justify-content-start flex-column project-hover-tasks-trigger" data-project-id="${row.id}">
                                    <a href="detail.php?id=${row.id}" class="text-decoration-none small" style="font-weight: bold;">${escapeHtmlForNote(data || '')}</a>
                                </div>`;
                    },
                    title: '<span data-i18n="お施主様名">お施主様名</span>'
                },
                { 
                    name: 'parent_scale',
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
                    name: 'parent_type1',
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
                    name: 'project_order_type',
                    data: 'project_order_type',
                    render: function(data, type, row) {
                        const getOrderTypeBadgeClass = function(orderType) {
                            const t = String(orderType).trim().toLowerCase();
                            switch (t) {
                                case '修正':
                                    return 'bg-warning';
                                case '新規':
                                    return 'bg-primary';
                                default:
                                    return 'bg-info';
                            }
                        };

                        let items = [];
                        if (!data || data === '') {
                            items = [];
                        } else if (typeof data === 'string') {
                            const splitItems = data.split(',').map(item => item.trim()).filter(item => item);
                            if (splitItems.length > 0) {
                                items = splitItems;
                            } else {
                                try {
                                    const decoded = decodeHtmlEntities(data);
                                    const arr = JSON.parse(decoded);
                                    if (Array.isArray(arr)) {
                                        items = arr.map(item => String(item).trim()).filter(item => item);
                                    }
                                } catch (e) {
                                    const one = String(data).trim();
                                    if (one) items = [one];
                                }
                            }
                        } else if (Array.isArray(data)) {
                            items = data.map(item => String(item).trim()).filter(item => item);
                        }

                        if (type === 'sort' || type === 'type') {
                            return items.join(', ') || '';
                        }
                        if (items.length === 0) {
                            return '<span class="text-muted">-</span>';
                        }
                        return '<div class="d-flex flex-column gap-1 align-items-start">' +
                            items.map(item => {
                                const badgeClass = getOrderTypeBadgeClass(item);
                                return `<span class="badge ${badgeClass} small">${item}</span>`;
                            }).join('') +
                            '</div>';
                    },
                    title: '<span data-i18n="受注形態">受注形態</span>',
                    className: 'project-order-type-column',
                    width: '100px'
                },
                { 
                    name: 'parent_type2',
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
                { 
                    name: 'start_date',
                    data: 'start_date',
                    width: '80px',
                    title: '<span data-i18n="開始日">開始日</span>', render: function(data, type, row) {
                    if(data) {
                        var vnTip = (typeof window.formatVietnamTimeTooltip === 'function') ? window.formatVietnamTimeTooltip(data) : '';
                        var rawEsc = String(data).replace(/"/g, '&quot;').replace(/</g, '&lt;');
                        var attrs = ' data-time="' + rawEsc + '"' + (typeof getTodoDataAttrs === 'function' ? getTodoDataAttrs(row, '開始日') : '');
                        if (vnTip) attrs += ' data-bs-toggle="tooltip" data-bs-title="' + vnTip.replace(/"/g, '&quot;') + '"';
                        return '<span class="text-muted small"' + attrs + '>' + formatDateTimeWithLineBreak(data) + '</span>';
                    } else {
                        return '-';
                    }
                }},
        ];
        var tailColumnConfigs = [
                {
                    name: 'caily_nouki',
                    data: 'caily_nouki',
                    render: function(data, type, row) {
                        // Luôn tính statusBadge trước (từ row), kể cả khi caily_nouki rỗng → vẫn hiện 納品済み
                        var statusBadge = '';
                        var isDelivered = false;
                        var statusVal = row && (row.caily_nouki_status !== undefined && row.caily_nouki_status !== null ? row.caily_nouki_status : '');
                        if (statusVal !== '' && String(statusVal).trim() !== '') {
                            var statusEsc = String(statusVal).replace(/"/g, '&quot;').replace(/</g, '&lt;');
                            statusBadge = '<span class="badge bg-label-success mt-1" style="font-size: 0.7rem; padding: 0.2rem 0.4rem;">' + (typeof translateText === 'function' ? translateText(statusEsc) : statusEsc) + '</span>';
                            if (String(statusVal).indexOf('納品済み') !== -1) {
                                isDelivered = true;
                            }
                        }

                        var hasNoDate = !data || (typeof data === 'string' && data.trim() === '');
                        if (hasNoDate) {
                            return statusBadge ? '<div class="d-flex flex-column"><span class="text-muted small">-</span>' + statusBadge + '</div>' : '<span class="text-muted">-</span>';
                        }

                        var vnTip = (typeof window.formatVietnamTimeTooltip === 'function') ? window.formatVietnamTimeTooltip(data) : '';
                        var rawEsc = String(data).replace(/"/g, '&quot;').replace(/</g, '&lt;');
                        var attrs = ' data-time="' + rawEsc + '"' + (typeof getTodoDataAttrs === 'function' ? getTodoDataAttrs(row, 'CAILY納期') : '');
                        if (vnTip) attrs += ' data-bs-toggle="tooltip" data-bs-title="' + vnTip.replace(/"/g, '&quot;') + '"';
                        const dateStr = formatDateTimeWithLineBreak(data);

                        if (isDelivered) {
                            return '<div class="d-flex flex-column">' +
                                '<span class="text-muted small"' + attrs + '>' + dateStr + '</span>' +
                                statusBadge +
                            '</div>';
                        }

                        const timeRemaining = getTimeRemaining(data, row.status);
                        if (timeRemaining) {
                            const pulseClass = timeRemaining.isOverdue ? 'pulse-animation' : '';
                            const titleText = timeRemaining.isOverdue
                                ? (typeof i18next !== 'undefined' && i18next.isInitialized ? i18next.t('期限を超過しています') : '期限を超過しています')
                                : (typeof i18next !== 'undefined' && i18next.isInitialized ? i18next.t('残り時間') : '残り時間');
                            return '<div class="d-flex flex-column">' +
                                        '<span class="text-muted small"' + attrs + '>' + dateStr + '</span>' +
                                        '<span class="badge ' + timeRemaining.class + ' ' + pulseClass + ' mt-1 time-remaining-badge" ' +
                                             'title="' + (timeRemaining.fullText ? timeRemaining.fullText.replace(/"/g, '&quot;') : titleText.replace(/"/g, '&quot;')) + '" ' +
                                             'style="font-size: 0.7rem; padding: 0.2rem 0.4rem; max-width: 70px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' +
                                             timeRemaining.text +
                                        '</span>' +
                                        statusBadge +
                                    '</div>';
                        } else {
                            return '<div class="d-flex flex-column">' +
                                '<span class="small text-muted"' + attrs + '>' + dateStr + '</span>' +
                                statusBadge +
                            '</div>';
                        }
                    },
                    title: buildI18nHeaderTitle('CAILY納期'),
                    className: 'caily-nouki-column',
                    width: '80px',
                    visible: false
                },
                {
                    name: 'guis_nouki',
                    data: 'guis_nouki',
                    render: function(data, type, row) {
                        // Luôn tính statusBadge trước (từ row), kể cả khi guis_nouki rỗng → vẫn hiện 納品済み
                        var statusBadge = '';
                        var isDelivered = false;
                        var statusVal = row && (row.guis_nouki_status !== undefined && row.guis_nouki_status !== null ? row.guis_nouki_status : '');
                        if (statusVal !== '' && String(statusVal).trim() !== '') {
                            var statusEsc = String(statusVal).replace(/"/g, '&quot;').replace(/</g, '&lt;');
                            statusBadge = '<span class="badge bg-label-success mt-1" style="font-size: 0.7rem; padding: 0.2rem 0.4rem;">' + statusEsc + '</span>';
                            if (String(statusVal).indexOf('納品済み') !== -1) {
                                isDelivered = true;
                            }
                        }

                        var hasNoDate = !data || (typeof data === 'string' && data.trim() === '');
                        if (hasNoDate) {
                            return statusBadge ? '<div class="d-flex flex-column"><span class="text-muted small">-</span>' + statusBadge + '</div>' : '<span class="text-muted">-</span>';
                        }

                        var vnTip = (typeof window.formatVietnamTimeTooltip === 'function') ? window.formatVietnamTimeTooltip(data) : '';
                        var rawEsc = String(data).replace(/"/g, '&quot;').replace(/</g, '&lt;');
                        var attrs = ' data-time="' + rawEsc + '"' + (typeof getTodoDataAttrs === 'function' ? getTodoDataAttrs(row, 'GUIS納期') : '');
                        if (vnTip) attrs += ' data-bs-toggle="tooltip" data-bs-title="' + vnTip.replace(/"/g, '&quot;') + '"';
                        const timeRemaining = getTimeRemaining(data, row.status);
                        const dateStr = formatDateTimeWithLineBreak(data);

                        if (isDelivered) {
                            return '<div class="d-flex flex-column">' +
                                '<span class="text-muted small"' + attrs + '>' + dateStr + '</span>' +
                                statusBadge +
                            '</div>';
                        }
                        if (timeRemaining) {
                            const pulseClass = timeRemaining.isOverdue ? 'pulse-animation' : '';
                            const titleText = timeRemaining.isOverdue
                                ? (typeof i18next !== 'undefined' && i18next.isInitialized ? i18next.t('期限を超過しています') : '期限を超過しています')
                                : (typeof i18next !== 'undefined' && i18next.isInitialized ? i18next.t('残り時間') : '残り時間');
                            return '<div class="d-flex flex-column">' +
                                        '<span class="text-muted small"' + attrs + '>' + dateStr + '</span>' +
                                        '<span class="badge ' + timeRemaining.class + ' ' + pulseClass + ' mt-1 time-remaining-badge" ' +
                                             'title="' + (timeRemaining.fullText ? timeRemaining.fullText.replace(/"/g, '&quot;') : titleText.replace(/"/g, '&quot;')) + '" ' +
                                             'style="font-size: 0.7rem; padding: 0.2rem 0.4rem; max-width: 70px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' +
                                             timeRemaining.text +
                                        '</span>' +
                                        statusBadge +
                                    '</div>';
                        } else {
                            return '<div class="d-flex flex-column">' +
                                '<span class="small text-muted"' + attrs + '>' + dateStr + '</span>' +
                                statusBadge +
                            '</div>';
                        }
                    },
                    title: buildI18nHeaderTitle('GUIS納期'),
                    className: 'guis-nouki-column',
                    width: '80px',
                    visible: false
                },
                { name: 'end_date', data: 'end_date', title: buildI18nHeaderTitle('終了日'), render: function(data, type, row) {
                    if(data) {
                        var vnTip = (typeof window.formatVietnamTimeTooltip === 'function') ? window.formatVietnamTimeTooltip(data) : '';
                        var rawEsc = String(data).replace(/"/g, '&quot;').replace(/</g, '&lt;');
                        var attrs = ' data-time="' + rawEsc + '"' + (typeof getTodoDataAttrs === 'function' ? getTodoDataAttrs(row, '終了日') : '');
                        if (vnTip) attrs += ' data-bs-toggle="tooltip" data-bs-title="' + vnTip.replace(/"/g, '&quot;') + '"';
                        const timeRemaining = getTimeRemaining(data, row.status);
                        const dateStr = formatDateTimeWithLineBreak(data);
                        
                        if (timeRemaining) {
                            const pulseClass = timeRemaining.isOverdue ? 'pulse-animation' : '';
                            const titleText = timeRemaining.isOverdue 
                                ? (typeof i18next !== 'undefined' && i18next.isInitialized ? i18next.t('期限を超過しています') : '期限を超過しています')
                                : (typeof i18next !== 'undefined' && i18next.isInitialized ? i18next.t('残り時間') : '残り時間');
                            return '<div class="d-flex flex-column">' +
                                        '<span class="text-muted small"' + attrs + '>' + dateStr + '</span>' +
                                        '<span class="badge ' + timeRemaining.class + ' ' + pulseClass + ' mt-1 time-remaining-badge" ' +
                                             'title="' + (timeRemaining.fullText ? timeRemaining.fullText.replace(/"/g, '&quot;') : titleText.replace(/"/g, '&quot;')) + '" ' +
                                             'style="font-size: 0.7rem; padding: 0.2rem 0.4rem; max-width: 70px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' +
                                             timeRemaining.text +
                                        '</span>' +
                                    '</div>';
                        } else {
                            return '<span class="text-muted small"' + attrs + '>' + dateStr + '</span>';
                        }
                    } else {
                        return '-';
                    }
                }, className: 'end-date-column'},
                {
                    name: 'priority',
                    data: 'priority',
                    render: function(data, type, row) {
                        // Return original data value for sorting
                        if (type === 'sort' || type === 'type') {
                            return data || '';
                        }
                        // Return HTML for display
                        const priority = priorities.find(priority => priority.key === data);
                        return `<span class="badge bg-${priority?.color || 'secondary'}">${translateText(priority?.name || data)}</span>`;
                    },
                    title: '<span data-i18n="優先度">優先度</span>',
                    className: 'priority-column',
                    width: '80px'
                },
                {
                    name: 'amount',
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
                    name: 'customer_info',
                    data: 'name',
                    width: '100px',
                    render: function(data, type, row) {
                        const company = String(row.effective_company_name || row.parent_company_name || '').trim();
                        const branch = String(row.parent_branch_name || '').trim();
                        const contact = String(row.effective_contact_name || row.customer_name || '').trim();
                        const lines = [company, branch, contact].filter(Boolean);
                        if (!lines.length) {
                            return '<span class="text-muted">-</span>';
                        }
                        return `<div class="d-flex align-items-start justify-content-start flex-column mt-1">` +
                            lines.map(function(line) {
                                return `<small class="text-muted d-block">${escapeHtmlForNote(line)}</small>`;
                            }).join('') +
                            `</div>`;
                    },
                    title: '<span data-i18n="顧客情報">顧客情報</span>'
                },
                { 
                    name: 'parent_guis_receiver',
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
        ];
        var tailForTable = tailColumnConfigs;
        if (isCailyBranchUser()) {
            tailForTable = tailColumnConfigs.filter(function(c) { return !CAILY_HIDDEN_COLUMN_KEYS[c.name]; });
        }
        var defaultColumnKeys = fixedColumnConfigs.map(function(c) { return c.name; }).concat(customColumnConfigs.map(function(c) { return c.name; })).concat(tailForTable.map(function(c) { return c.name; }));
        var depIdForColumnOrder = app.selectedDepartment && app.selectedDepartment.id;
        var mergedColumnKeys = mergeColumnKeyOrder(loadProjectColumnOrder(depIdForColumnOrder), defaultColumnKeys);
        var projectColumnRegistry = {};
        fixedColumnConfigs.forEach(function(c) { projectColumnRegistry[c.name] = c; });
        customColumnConfigs.forEach(function(c) { projectColumnRegistry[c.name] = c; });
        tailColumnConfigs.forEach(function(c) { projectColumnRegistry[c.name] = c; });
        var orderedColumns = mergedColumnKeys.map(function(k) { return projectColumnRegistry[k]; }).filter(Boolean);
        var defaultSortIndex = getDefaultProjectListSortIndex(mergedColumnKeys);

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
                    const filterToday = $('#filterToday').val();
                    const filterProjectOrderType = $('#filterProjectOrderType').val();
                    const filterTeam = formatFilterTeamForApi(getFilterTeamValue());
                    const filterTantou = $('#filterTantou').val();
                    const filterNoDates = $('#filterNoDates').is(':checked') ? 1 : 0;
                    const filterKeyword = normalizeFilterKeyword($('#filterKeyword').val());
                    const filterProjectId = $('#filterProjectId').val();
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
                        filterToday,
                        filterProjectOrderType,
                        filterTeam,
                        filterTantou,
                        filterNoDates,
                        my_projects: myProjects,
                        filterKeyword,
                        filterProjectId,
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
            autoWidth: false,
            //scrollY: Math.round(window.innerHeight * 0.8) + 'px',
            columns: orderedColumns,
            colReorder: true,
            order: [[defaultSortIndex, 'asc']],
           
            pageLength: 50,
            ordering: true,
            responsive: false,
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
            },
            initComplete: function() {
                var tableEl = document.getElementById('projectTable');
                if (tableEl && typeof applyStickyScrollHead === 'function') {
                    applyStickyScrollHead(tableEl);
                }
                applyI18nToProjectTableUI();
            }
            
        });

        $('#projectTable').off('columns-reordered.dt').on('columns-reordered.dt', function() {
            if (!projectTable) return;
            var keys = [];
            var cnt = projectTable.columns().count();
            for (var ci = 0; ci < cnt; ci++) {
                try {
                    var colApi = projectTable.column(ci);
                    var nm = typeof colApi.name === 'function' ? colApi.name() : '';
                    if (!nm && projectTable.settings && projectTable.settings()[0] && projectTable.settings()[0].aoColumns) {
                        nm = projectTable.settings()[0].aoColumns[ci] && projectTable.settings()[0].aoColumns[ci].name;
                    }
                    if (nm) keys.push(nm);
                } catch (errCol) { /* skip */ }
            }
            saveProjectColumnOrder(app.selectedDepartment && app.selectedDepartment.id, keys);
            projectTable.columns.adjust();
        });
        
        // Sau mỗi lần vẽ bảng: thêm note snippet vào ô cột có display_column trùng (dưới cùng ô, >40 ký tự thì cắt + tooltip)
        projectTable.on('draw.dt', function() {
            var customDefs = customFieldColumnDefinitions || [];
            var noteColumnConfigs = []; // { colIndex, displayColumnKey }
            (NOTE_DISPLAY_COLUMNS || []).forEach(function(c) {
                if (isColumnHiddenForCailyBranch(c.key)) return;
                var idx = getDataTableColumnIndexByKey(c.key, customDefs);
                if (idx !== null) noteColumnConfigs.push({ colIndex: idx, displayColumnKey: c.key });
            });
            customDefs.forEach(function(c) {
                var idx = getDataTableColumnIndexByKey(c.key, customDefs);
                if (idx !== null) noteColumnConfigs.push({ colIndex: idx, displayColumnKey: 'custom:' + (c.label || '').trim() });
            });
            projectTable.rows({ search: 'applied' }).every(function(rowIdx) {
                var rowData = this.data();
                var notesByCol = rowData.notes_by_display_column || {};
                noteColumnConfigs.forEach(function(cfg) {
                    var notes = notesByCol[cfg.displayColumnKey];
                    if (!notes || !notes.length) return;
                    var node = projectTable.cell(rowIdx, cfg.colIndex).node();
                    if (!node) return;
                    var $cell = $(node);
                    $cell.find('.cell-note-snippets').remove();
                    var parts = [];
                    notes.forEach(function(n) {
                        var content = n.content || '';
                        var decodedHtml = decodeHtmlForNote(content);
                        decodedHtml = (decodedHtml || '').replace(/\u00A0/g, ' ').replace(/&nbsp;/gi, ' ');
                        var sn = noteSnippetText(content, 40);
                        var tooltipAttrs = '';
                        if (sn.full && sn.full.length > 40) {
                            tooltipAttrs = ' data-bs-toggle="tooltip" data-bs-title="' + escapeHtmlForNote(sn.full) + '"';
                        }
                        var isImportant = n.is_important == 1;
                        parts.push(
                            '<div class="confirmation-note-item mb-1 cell-note-snippet-item ' + (isImportant ? 'important-note' : '') + '" data-note-id="' + (n.id || '') + '" data-project-id="' + (rowData.id || '') + '" style="cursor:pointer"' + tooltipAttrs + '>' +
                                '<div class="note-text small ql-editor" style="max-height:2.5em;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;line-clamp:2;">' + (decodedHtml || '-') + '</div>' +
                            '</div>'
                        );
                    });
                    $cell.append('<div class="cell-note-snippets">' + parts.join('') + '</div>');
                });
            });
            applyI18nToProjectTableUI();
        });
        
        // Apply column visibility after table initialization (base + custom columns; custom default hidden)
        var columnVisibility = loadColumnVisibilityFromLocalStorage(customFieldColumnDefinitions);
        applyColumnVisibility(projectTable, columnVisibility, customFieldColumnDefinitions);
        if (app) {
            var depIdAc = app.selectedDepartment && app.selectedDepartment.id;
            app.availableColumns = buildAvailableColumnsList(customFieldColumnDefinitions, depIdAc, columnVisibility);
            scheduleColumnVisibilityMenuI18n();
        }
        if (typeof i18next !== 'undefined' && typeof i18next.on === 'function' && !window.__projectListLanguageBound) {
            window.__projectListLanguageBound = true;
            i18next.on('languageChanged', function() {
                applyI18nToProjectTableUI();
            });
        }
        
        } catch (error) {
            console.error('Error initializing project table:', error);
            if (app) {
                app.loading = false;
            }
        } finally {
            // Reset flag after initialization (cũng chạy trong finally nếu có lỗi)
            isInitializingTable = false;
            // Tắt loading khi table đã init xong
            if (app) {
                app.loading = false;
            }
        }

        // Popup tasks khi hover cột ID hoặc name (di chuyển theo chuột, load task qua API)
        (function initProjectTasksPopup() {
            var popup = null;
            var hideTimer = null;
            var lastProjectId = null;
            var abortController = null;
            var offsetX = 12;
            var offsetY = 8;

            function getPopup() {
                if (!popup) {
                    popup = document.createElement('div');
                    popup.id = 'projectTasksPopup';
                    popup.className = 'project-tasks-popup shadow border rounded bg-white p-2';
                    popup.style.cssText = 'position: fixed; z-index: 9999; min-width: 460px; max-width: 540px; max-height: 360px; overflow: auto; display: none; pointer-events: auto;';
                    popup.setAttribute('role', 'tooltip');
                    document.body.appendChild(popup);
                }
                return popup;
            }

            function movePopup(e) {
                var el = getPopup();
                if (el.style.display !== 'none') {
                    el.style.left = (e.clientX + offsetX) + 'px';
                    el.style.top = (e.clientY + offsetY) + 'px';
                }
            }

            function showPopup(projectId, clientX, clientY) {
                if (hideTimer) {
                    clearTimeout(hideTimer);
                    hideTimer = null;
                }
                var el = getPopup();
                el.style.left = (clientX + offsetX) + 'px';
                el.style.top = (clientY + offsetY) + 'px';
                el.style.display = 'block';
                if (lastProjectId === projectId && el.getAttribute('data-loaded') === '1') return;
                lastProjectId = projectId;
                el.setAttribute('data-loaded', '0');
                el.innerHTML = '<div class="text-muted small"><span class="spinner-border spinner-border-sm me-1" role="status"></span>Loading...</div>';
                if (abortController) abortController.abort();
                abortController = new AbortController();
                var axiosOpt = { signal: abortController.signal };
                if (typeof axios === 'undefined') {
                    el.innerHTML = '<div class="text-muted small">axios not found</div>';
                    return;
                }
                var taskStatuses = [
                    { value: 'todo', label: '未開始', color: 'secondary' },
                    { value: 'in-progress', label: '進行中', color: 'primary' },
                    { value: 'confirming', label: '確認中', color: 'warning' },
                    { value: 'paused', label: '一時停止', color: 'warning' },
                    { value: 'completed', label: '完了', color: 'success' },
                    { value: 'cancelled', label: 'キャンセル', color: 'danger' }
                ];
                function getTaskStatusLabel(s) {
                    var o = taskStatuses.find(function(x) { return x.value === s; });
                    return o ? o.label : (s || '');
                }
                function getTaskStatusBadgeClass(s) {
                    var o = taskStatuses.find(function(x) { return x.value === s; });
                    return 'badge bg-' + (o ? o.color : 'secondary');
                }
                function formatTaskDue(d) {
                    if (!d) return '-';
                    var m = toProjectDisplayMoment(d);
                    if (m) return formatProjectDateOnly(m);
                    return String(d).substring(0, 10);
                }
                var TASK_KIND_META = [
                    { value: '新規作成', color: 'success' },
                    { value: '修正(エラー)', color: 'danger' },
                    { value: '修正(変更)', color: 'warning' },
                    { value: 'チェック', color: 'primary' },
                    { value: '連絡', color: 'info' },
                    { value: '検討', color: 'secondary' },
                    { value: '相談・会議', color: 'dark' }
                ];
                function normalizeTaskKind(value) {
                    var v = String(value || '').trim();
                    return v === '新規' ? '新規作成' : v;
                }
                function getTaskKindLabel(value) {
                    var normalized = normalizeTaskKind(value);
                    if (!normalized) {
                        return '—';
                    }
                    return typeof translateText === 'function' ? translateText(normalized) : normalized;
                }
                function getTaskKindBadgeClass(value) {
                    var normalized = normalizeTaskKind(value);
                    var kind = TASK_KIND_META.find(function(k) { return k.value === normalized; });
                    return 'badge bg-label-' + (kind ? kind.color : 'secondary');
                }
                function formatWorkloadDisplay(value) {
                    if (value === null || value === undefined || value === '') {
                        return '0';
                    }
                    var s = String(value).trim();
                    if (!s) {
                        return '0';
                    }
                    var n = parseFloat(s);
                    if (Number.isNaN(n) || n <= 0) {
                        return '0';
                    }
                    return s;
                }
                function getTaskWorkloadDisplay(t) {
                    return formatWorkloadDisplay(t && t.estimated_hours);
                }
                function sumTaskWorkloads(taskList) {
                    return (taskList || []).reduce(function(sum, t) {
                        var n = parseFloat(t && t.estimated_hours);
                        return sum + (Number.isNaN(n) || n <= 0 ? 0 : n);
                    }, 0);
                }
                function formatWorkloadTotalDisplay(total) {
                    if (!total || total <= 0) {
                        return '0';
                    }
                    if (Number.isInteger(total)) {
                        return String(total);
                    }
                    return String(total);
                }
                function getTaskAssigneeDisplay(t, members) {
                    if (!t.assigned_to && !t.assigned_to_name) return { firstInitials: '', firstTitle: '', restCount: 0 };
                    var ids = t.assigned_to ? String(t.assigned_to).split(',').map(function(x) { return x.trim(); }).filter(Boolean) : [];
                    var firstInitials = '';
                    var firstTitle = '';
                    var restCount = 0;
                    if (ids.length === 0 && t.assigned_to_name) {
                        firstInitials = getInitials(t.assigned_to_name);
                        firstTitle = t.assigned_to_name;
                    } else if (members && members.length > 0) {
                        var names = ids.map(function(uid) {
                            var m = members.find(function(x) { return String(x.user_id) === String(uid) || String(x.id) === String(uid); });
                            return m ? (m.realname || m.user_name || '') : '';
                        }).filter(Boolean);
                        if (names.length > 0) {
                            firstInitials = getInitials(names[0]);
                            firstTitle = names[0];
                            restCount = names.length - 1;
                        } else {
                            firstInitials = t.assigned_to_name ? getInitials(t.assigned_to_name) : '';
                            firstTitle = t.assigned_to_name || '';
                        }
                    } else {
                        firstInitials = t.assigned_to_name ? getInitials(t.assigned_to_name) : '';
                        firstTitle = t.assigned_to_name || '';
                    }
                    return { firstInitials: firstInitials, firstTitle: firstTitle, restCount: restCount };
                }
                Promise.all([
                    axios.get('/api/index.php?model=task&method=list&project_id=' + encodeURIComponent(projectId) + '&include_subtasks=1', axiosOpt),
                    axios.get('/api/index.php?model=project&method=getMembers&project_id=' + encodeURIComponent(projectId), axiosOpt)
                ]).then(function(results) {
                    var tasks = results[0].data || [];
                    var members = results[1].data || [];
                    if (tasks.length === 0) {
                        el.innerHTML = '<div class="text-muted small">タスクなし</div>';
                    } else {
                        var drawingLabel = typeof translateText === 'function' ? translateText('工数') : '工数';
                        var totalDrawingLabel = typeof translateText === 'function' ? translateText('工数合計') : '工数合計';
                        var kindColLabel = typeof translateText === 'function' ? translateText('種別') : '種別';
                        var totalWorkload = sumTaskWorkloads(tasks);
                        var html = '<div class="small fw-bold mb-1 d-flex justify-content-between align-items-center gap-2 flex-wrap">';
                        html += '<span>タスク (' + tasks.length + ')</span>';
                        html += '<span class="text-muted fw-normal">' + escapeHtmlForNote(totalDrawingLabel) + ': ' + escapeHtmlForNote(formatWorkloadTotalDisplay(totalWorkload)) + '</span>';
                        html += '</div>';
                        html += '<ul class="list-unstyled mb-0 small">';
                        tasks.slice(0, 20).forEach(function(t) {
                            var statusLabel = getTaskStatusLabel(t.status);
                            var statusClass = getTaskStatusBadgeClass(t.status);
                            var kindLabel = getTaskKindLabel(t.task_kind);
                            var kindClass = getTaskKindBadgeClass(t.task_kind);
                            var workloadDisplay = getTaskWorkloadDisplay(t);
                            var title = (t.title || '').toString().trim() || '-';
                            if (title.length > 22) title = title.substring(0, 22) + '…';
                            var assigneeDisplay = getTaskAssigneeDisplay(t, members);
                            var dueStr = formatTaskDue(t.due_date);
                            var progressVal = t.progress != null ? parseInt(t.progress, 10) : 0;
                            html += '<li class="py-1 border-bottom border-light d-flex flex-wrap align-items-center gap-1">';
                            html += '<span class="' + statusClass + ' me-1" style="font-size:0.65rem;">' + statusLabel + '</span>';
                            html += '<span class="' + kindClass + ' me-1" style="font-size:0.65rem;" title="' + escapeHtmlForNote(kindColLabel) + '">' + escapeHtmlForNote(kindLabel) + '</span>';
                            html += '<span class="text-nowrap" title="' + escapeHtmlForNote((t.title || '').toString().trim()) + '">' + escapeHtmlForNote(title) + '</span>';
                            html += '<span class="ms-auto d-flex align-items-center gap-1 flex-nowrap">';
                            if (assigneeDisplay.firstInitials) {
                                html += '<span class="avatar-initial rounded-circle bg-label-primary" style="padding: 0 2px;height:18px;font-size:9px;line-height:18px;display:inline-flex;align-items:center;justify-content:center;" title="' + escapeHtmlForNote(assigneeDisplay.firstTitle) + '">' + (assigneeDisplay.firstInitials) + '</span>';
                                for (var r = 1; r <= assigneeDisplay.restCount; r++) {
                                    html += '<span class="avatar-initial rounded-circle bg-label-secondary text-white" style="width:18px;height:18px;font-size:9px;line-height:18px;display:inline-flex;align-items:center;justify-content:center;" title="担当者' + r + '">+' + r + '</span>';
                                }
                            }
                            html += '<span class="text-muted text-nowrap" style="font-size:0.7rem;" title="' + escapeHtmlForNote(drawingLabel) + '">' + escapeHtmlForNote(drawingLabel) + ':' + escapeHtmlForNote(workloadDisplay) + '</span>';
                            html += '<span class="text-muted" style="font-size:0.7rem;">' + dueStr + '</span>';
                            html += '<span class="text-muted" style="font-size:0.7rem;">' + progressVal + '%</span>';
                            html += '</span></li>';
                        });
                        if (tasks.length > 20) html += '<li class="text-muted py-1">+' + (tasks.length - 20) + ' more</li>';
                        html += '</ul>';
                        el.innerHTML = html;
                    }
                    el.setAttribute('data-loaded', '1');
                })
                    .catch(function(err) {
                        if (err.name === 'CanceledError' || err.name === 'AbortError') return;
                        el.innerHTML = '<div class="text-danger small">Failed to load tasks</div>';
                    });
            }

            function scheduleHide() {
                if (hideTimer) clearTimeout(hideTimer);
                hideTimer = setTimeout(function() {
                    hideTimer = null;
                    var el = getPopup();
                    el.style.display = 'none';
                }, 200);
            }

            function cancelHide() {
                if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
            }

            $(document).on('mousemove', function(e) {
                if ($(e.target).closest('#projectTasksPopup').length) return;
                movePopup(e);
            });

            $('#projectTable').on('mouseenter', '.project-hover-tasks-trigger', function(e) {
                var projectId = $(this).data('project-id');
                if (!projectId) return;
                cancelHide();
                showPopup(projectId, e.clientX, e.clientY);
            });
            $('#projectTable').on('mouseleave', '.project-id-cell, .project-name-cell, .project-progress-cell', function() {
                scheduleHide();
            });

            $(document).on('mouseenter', '#projectTasksPopup', cancelHide);
            $(document).on('mouseleave', '#projectTasksPopup', scheduleHide);
        })();

        var fixedScrollHeadUpdate = null;
        function applyStickyScrollHead(tableEl) {
            if (!tableEl) tableEl = document.getElementById('projectTable');
            if (!tableEl || !tableEl.closest) return;
            var wrapper = tableEl.closest('.dt-container') || tableEl.closest('.dataTables_wrapper');
            if (!wrapper) return;
            var containerEl = wrapper.closest('.dt-container') || wrapper;
            var scrollHead = wrapper.querySelector('.dt-scroll-head') || wrapper.querySelector('.dataTables_scrollHead') || wrapper.querySelector('[class*="scrollHead"]');
            if (!scrollHead) {
                var scroll = wrapper.querySelector('.dataTables_scroll') || wrapper.querySelector('.dt-scroll');
                if (scroll && scroll.firstElementChild) scrollHead = scroll.firstElementChild;
            }
            if (!scrollHead) return;
            scrollHead.style.zIndex = '10';
            scrollHead.style.backgroundColor = scrollHead.style.backgroundColor || '#fff';
            scrollHead.style.boxShadow = '0 2px 4px rgba(0,0,0,0.08)';
            var spacer = wrapper.querySelector('.dt-scroll-head-spacer');
            if (!spacer) {
                spacer = document.createElement('div');
                spacer.className = 'dt-scroll-head-spacer';
                spacer.style.display = 'block';
                spacer.style.height = '0';
                spacer.style.overflow = 'hidden';
                scrollHead.parentNode.insertBefore(spacer, scrollHead.nextSibling);
            }
            function updateFixedHeaderPosition() {
                if (!scrollHead.parentNode || !containerEl) return;
                var rect = containerEl.getBoundingClientRect();
                var isPastContainer = rect.top <= -scrollHead.offsetHeight * 2 + 60;
                if (isPastContainer) {
                    scrollHead.style.position = 'fixed';
                    scrollHead.style.top = '4.6rem';
                    scrollHead.style.zIndex = '1000';
                    scrollHead.style.left = rect.left + 'px';
                    scrollHead.style.width = rect.width + 'px';
                    if (spacer) spacer.style.height = scrollHead.offsetHeight + 'px';
                } else {
                    scrollHead.style.position = '';
                    scrollHead.style.top = '';
                    scrollHead.style.zIndex = '';
                    scrollHead.style.left = '';
                    scrollHead.style.width = '';
                    if (spacer) spacer.style.height = '0';
                }
            }
            updateFixedHeaderPosition();
            if (fixedScrollHeadUpdate) {
                window.removeEventListener('scroll', fixedScrollHeadUpdate, true);
                window.removeEventListener('resize', fixedScrollHeadUpdate);
            }
            fixedScrollHeadUpdate = function() { updateFixedHeaderPosition(); };
            window.addEventListener('scroll', fixedScrollHeadUpdate, true);
            window.addEventListener('resize', fixedScrollHeadUpdate);
        }
        // Khởi tạo Bootstrap tooltip cho mọi ô có data-bs-toggle="tooltip" (ô giờ data-time, ô text/textarea có data-bs-title, ...) mỗi khi DataTable vẽ lại
        $('#projectTable').on('draw.dt', function() {
            var table = document.getElementById('projectTable');
            if (!table || !window.bootstrap || !bootstrap.Tooltip) return;
            var triggers = table.querySelectorAll('[data-bs-toggle="tooltip"]');
            triggers.forEach(function(el) {
                var t = bootstrap.Tooltip.getInstance(el);
                if (t) t.dispose();
                new bootstrap.Tooltip(el);
            });
            applyStickyScrollHead(table);
            // Cho phép AI lấy dữ liệu danh sách dự án hiện đang hiển thị trên trang
            if (projectTable && typeof $.fn.DataTable !== 'undefined' && $.fn.DataTable.isDataTable('#projectTable')) {
                try {
                    var rows = projectTable.rows({ search: 'applied' }).data();
                    if (typeof window.__chatPageContext !== 'object' || window.__chatPageContext === null) window.__chatPageContext = {};
                    window.__chatPageContext.page = 'project_list';
                    window.__chatPageContext.page_projects = Array.isArray(rows) ? Array.from(rows) : [];
                } catch (e) { /* ignore */ }
            }
        });
        // Retry sau khi draw (serverSide: ajax trả về mới có DOM scroll) — 300ms và 800ms
        setTimeout(function() {
            var el = document.getElementById('projectTable');
            if (el) applyStickyScrollHead(el);
        }, 300);
        setTimeout(function() {
            var el = document.getElementById('projectTable');
            if (el) applyStickyScrollHead(el);
        }, 800);

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
                    var target = e.target || e.srcElement;
                    var tag = target.tagName.toLowerCase();
                    // Cho phép Space trong input, textarea, select, và editor Quill (contenteditable / .ql-editor)
                    if (tag === 'input' || tag === 'textarea' || tag === 'select' ||
                        target.isContentEditable || $(target).closest('.ql-editor').length) {
                        return;
                    }
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
        // Custom context menu for adding confirmation notes (legacy: per note column, hiện chỉ để tránh lỗi khi hide/click)
        const $noteContextMenu = $('<div id="confirmationNoteContextMenu" class="dropdown-menu" style="position:absolute; display:none; z-index:9999;"></div>');
        $noteContextMenu.append('<button class="dropdown-item" type="button" id="addConfirmationNoteBtn"><i class="fa fa-plus me-1"></i><span data-i18n="メモを追加">メモを追加</span></button>');
        $('body').append($noteContextMenu);

        // ----- Context menu "案件を編集" + "Thêm vào todo" (gộp chung, ẩn/hiện Thêm vào todo theo ô có data-todo-title) -----
        const $rowContextMenu = $('<div id="projectRowContextMenu" class="dropdown-menu" style="position:absolute; display:none; z-index:9999;"></div>');
        $rowContextMenu.append('<button class="dropdown-item" type="button" id="copyProjectInfoRowBtn"><i class="fa fa-copy me-1"></i><span data-i18n="案件情報をコピー">案件情報をコピー</span></button>');
        $rowContextMenu.append('<div class="dropdown-divider project-row-context-divider"></div>');
        $rowContextMenu.append('<button class="dropdown-item" type="button" id="quickEditProjectRowBtn"><i class="fa fa-pencil-alt me-1"></i><span data-i18n="案件を編集">案件を編集</span></button>');
        $rowContextMenu.append('<button class="dropdown-item" type="button" id="editParentConstructionNumberRowBtn"><i class="fa fa-hashtag me-1"></i><span data-i18n="工事番号を編集">工事番号を編集</span></button>');
        $rowContextMenu.append('<button class="dropdown-item" type="button" id="addNoteFromRowBtn"><i class="fa fa-sticky-note me-1"></i><span data-i18n="メモを追加">メモを追加</span></button>');
        $rowContextMenu.append('<button class="dropdown-item" type="button" id="addToTodoFromRowBtn" style="display:none;"><i class="fas fa-list-check me-1"></i><span data-i18n="追加Todo">追加Todo</span></button>');
        $('body').append($rowContextMenu);
        let contextMenuRowProjectId = null;
        let contextMenuRowColumnKey = '';
        let contextMenuIsManagerOnly = false;
        let contextMenuTodoEl = null;
        let contextMenuRowData = null;

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

        function getColumnKeyByDataTableIndex(dtIndex, customDefs) {
            if (!projectTable || dtIndex === null || dtIndex === undefined) return '';
            try {
                var colApi = projectTable.column(dtIndex);
                var nm = typeof colApi.name === 'function' ? colApi.name() : '';
                if (!nm && projectTable.settings && projectTable.settings()[0] && projectTable.settings()[0].aoColumns) {
                    var ac = projectTable.settings()[0].aoColumns[dtIndex];
                    nm = (ac && ac.name) ? ac.name : '';
                }
                return nm || '';
            } catch (e) {
                return '';
            }
        }

        $('#projectTable tbody').on('contextmenu', 'tr', function(e) {
            if (!window.app) return;
            if (!projectTable) return;
            const rowData = projectTable.row($(this)).data();
            if (!rowData) return;
            var canFullEdit = window.app.canManageProject();
            var isManagerOfProject = isCurrentUserManagerOfProject(rowData);
            var canShowEdit = canFullEdit || isManagerOfProject;
            const $td = $(e.target).closest('td');
            contextMenuTodoEl = $td.find('[data-todo-title]')[0] || null;
            contextMenuRowData = rowData;
            // Xác định cột để set display_column cho note
            var dtIndex = null;
            try {
                var cellIdx = projectTable.cell($td).index();
                if (cellIdx && typeof cellIdx.column === 'number') {
                    dtIndex = cellIdx.column;
                }
            } catch (err) {}
            contextMenuRowColumnKey = dtIndex !== null ? getColumnKeyByDataTableIndex(dtIndex, customFieldColumnDefinitions) : '';
            var hasNoteColumn = !!contextMenuRowColumnKey;
            var hasParentProject = !!(rowData.parent_project_id && parseInt(rowData.parent_project_id, 10) > 0);
            var canEditParentConstruction = canShowEdit && hasParentProject;
            e.preventDefault();
            contextMenuRowProjectId = rowData.id;
            contextMenuIsManagerOnly = !canFullEdit && isManagerOfProject;
            $('#copyProjectInfoRowBtn').show();
            $('#quickEditProjectRowBtn').toggle(canShowEdit);
            $('#editParentConstructionNumberRowBtn').toggle(canEditParentConstruction);
            $('#addNoteFromRowBtn').toggle(hasNoteColumn);
            $('#addToTodoFromRowBtn').toggle(!!contextMenuTodoEl);
            var hasSecondaryActions = canShowEdit || canEditParentConstruction || hasNoteColumn || !!contextMenuTodoEl;
            $('.project-row-context-divider').toggle(hasSecondaryActions);
            $rowContextMenu
                .css({ top: e.pageY + 'px', left: e.pageX + 'px' })
                .show();
        });

        $(document).on('click', function() {
            $rowContextMenu.hide();
        });
        $rowContextMenu.on('click', '#copyProjectInfoRowBtn', async function(ev) {
            ev.stopPropagation();
            $rowContextMenu.hide();
            if (!contextMenuRowData || !window.ProjectClipboard) return;
            try {
                var text = window.ProjectClipboard.buildText(contextMenuRowData, teamIdToName);
                var copied = await window.ProjectClipboard.copy(text);
                if (!copied) throw new Error('copy failed');
                if (typeof showMessage === 'function') {
                    showMessage(typeof translateText === 'function' ? translateText('案件情報をコピーしました') : '案件情報をコピーしました', false);
                }
            } catch (err) {
                if (typeof showMessage === 'function') {
                    showMessage(typeof translateText === 'function' ? translateText('案件情報のコピーに失敗しました') : '案件情報のコピーに失敗しました', true);
                }
            }
        });
        $rowContextMenu.on('click', '#quickEditProjectRowBtn', function(ev) {
            ev.stopPropagation();
            $rowContextMenu.hide();
            if (contextMenuRowProjectId && typeof window.openQuickEditProjectModal === 'function') {
                window.openQuickEditProjectModal(contextMenuRowProjectId, contextMenuIsManagerOnly);
            }
        });
        $rowContextMenu.on('click', '#editParentConstructionNumberRowBtn', function(ev) {
            ev.stopPropagation();
            $rowContextMenu.hide();
            if (contextMenuRowData && typeof window.openEditParentConstructionNumberModal === 'function') {
                window.openEditParentConstructionNumberModal(contextMenuRowData);
            }
        });
        $rowContextMenu.on('click', '#addToTodoFromRowBtn', function(ev) {
            ev.stopPropagation();
            $rowContextMenu.hide();
            if (contextMenuTodoEl && typeof window.openAddToTodoModalFromContext === 'function') {
                window.openAddToTodoModalFromContext(contextMenuTodoEl);
            }
            contextMenuTodoEl = null;
        });

        $rowContextMenu.on('click', '#addNoteFromRowBtn', function(ev) {
            ev.stopPropagation();
            $rowContextMenu.hide();
            if (!contextMenuRowProjectId || !window.app || !app.openNoteModalFromList) return;
            var noteType = 0;
            if (contextMenuRowColumnKey === 'confirmation_notes_caily') noteType = 1;
            if (contextMenuRowColumnKey === 'confirmation_notes_guis') noteType = 2;
            var displayKey = '';
            // Chỉ set display_column cho các cột hiển thị note (NOTE_DISPLAY_COLUMNS + custom)
            if (NOTE_DISPLAY_COLUMNS.some(function(c){ return c.key === contextMenuRowColumnKey; })) {
                displayKey = contextMenuRowColumnKey;
            } else if (contextMenuRowColumnKey && contextMenuRowColumnKey.indexOf('custom_') === 0) {
                // Tìm label cho custom field theo key
                var label = '';
                (customFieldColumnDefinitions || []).forEach(function(c){
                    if (!label && c.key === contextMenuRowColumnKey) label = c.label || '';
                });
                if (label) displayKey = 'custom:' + label.trim();
            }
            app.openNoteModalFromList(contextMenuRowProjectId, null, noteType, displayKey);
        });

        // ----- Edit parent construction number -----
        const editParentConstructionModalEl = document.getElementById('editParentConstructionNumberModal');
        const editParentConstructionProjectIdInput = document.getElementById('editParentConstructionProjectId');
        const editParentConstructionNumberInput = document.getElementById('editParentConstructionNumberInput');
        const editParentConstructionProjectIdBadge = document.getElementById('editParentConstructionProjectIdBadge');
        const editParentConstructionSaveBtn = document.getElementById('editParentConstructionSaveBtn');
        const editParentConstructionSaveSpinner = document.getElementById('editParentConstructionSaveSpinner');

        window.openEditParentConstructionNumberModal = function(rowData) {
            if (!rowData || !editParentConstructionModalEl) return;
            var projectId = rowData.id ? String(rowData.id) : '';
            var parentProjectId = rowData.parent_project_id ? String(rowData.parent_project_id) : '';
            if (!parentProjectId) {
                showMessage('親案件が設定されていません。', true);
                return;
            }
            if (editParentConstructionProjectIdInput) editParentConstructionProjectIdInput.value = projectId;
            if (editParentConstructionNumberInput) editParentConstructionNumberInput.value = rowData.parent_construction_number || '';
            if (editParentConstructionProjectIdBadge) editParentConstructionProjectIdBadge.textContent = '#' + projectId;
            bootstrap.Modal.getOrCreateInstance(editParentConstructionModalEl).show();
            setTimeout(function() {
                if (editParentConstructionNumberInput && typeof editParentConstructionNumberInput.focus === 'function') {
                    editParentConstructionNumberInput.focus();
                }
            }, 120);
        };

        if (editParentConstructionSaveBtn) {
            editParentConstructionSaveBtn.addEventListener('click', async function() {
                var projectId = editParentConstructionProjectIdInput ? String(editParentConstructionProjectIdInput.value || '').trim() : '';
                var constructionNumber = editParentConstructionNumberInput ? String(editParentConstructionNumberInput.value || '').trim() : '';
                if (!projectId) return;

                editParentConstructionSaveBtn.disabled = true;
                if (editParentConstructionSaveSpinner) editParentConstructionSaveSpinner.classList.remove('d-none');
                try {
                    var formData = new FormData();
                    formData.append('project_id', projectId);
                    formData.append('construction_number', constructionNumber);
                    var response = await axios.post('/api/index.php?model=parentproject&method=updateConstructionNumberByProject', formData);
                    if (!response || !response.data || response.data.status !== 'success') {
                        var failMsg = (response && response.data && (response.data.message || response.data.error))
                            ? (response.data.message || response.data.error)
                            : '工事番号の更新に失敗しました。';
                        showMessage(failMsg, true);
                        return;
                    }
                    showMessage(response.data.message || '工事番号を更新しました。', false);
                    if (editParentConstructionModalEl) {
                        bootstrap.Modal.getOrCreateInstance(editParentConstructionModalEl).hide();
                    }
                    if (projectTable) {
                        projectTable.ajax.reload(null, false);
                    }
                } catch (err) {
                    console.error('Update parent construction number error:', err);
                    var msg = (err && err.response && err.response.data && (err.response.data.message || err.response.data.error))
                        ? (err.response.data.message || err.response.data.error)
                        : '工事番号の更新に失敗しました。';
                    showMessage(msg, true);
                } finally {
                    editParentConstructionSaveBtn.disabled = false;
                    if (editParentConstructionSaveSpinner) editParentConstructionSaveSpinner.classList.add('d-none');
                }
            });
        }

        // Quick Edit Tagify instances (destroy on each open, re-init after load)
        let quickEditTeamTagify = null, quickEditManagerTagify = null, quickEditMembersTagify = null;
        let quickEditQuillInstance = null;
        let quickEditIsManagerOnly = false;
        function destroyQuickEditQuill() {
            if (quickEditQuillInstance) {
                try {
                    if (typeof quickEditQuillInstance.setText === 'function') quickEditQuillInstance.setText('');
                    if (typeof quickEditQuillInstance.destroy === 'function') quickEditQuillInstance.destroy();
                } catch (e) {}
                quickEditQuillInstance = null;
            }
            var quillContainer = document.getElementById('quickEditQuillDescription');
            if (quillContainer) {
                var parent = quillContainer.parentElement;
                if (parent) {
                    var toolbar = parent.querySelector('.ql-toolbar');
                    if (toolbar) toolbar.remove();
                    parent.querySelectorAll('.ql-container, .ql-editor').forEach(function(el) {
                        if (el !== quillContainer) el.remove();
                    });
                }
                quillContainer.innerHTML = '';
                quillContainer.className = 'custom_editor_content';
                quillContainer.setAttribute('id', 'quickEditQuillDescription');
                quillContainer.removeAttribute('contenteditable');
                quillContainer.removeAttribute('data-gramm');
                quillContainer.removeAttribute('data-gramm_editor');
                quillContainer.removeAttribute('data-enable-grammarly');
            }
        }
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
                const effectiveId = p.id || projectId;
                $('#quickEditProjectId').val(effectiveId);
                $('#quickEditProjectIdBadge').text('#' + effectiveId);
                $('#quickEditName').val(p.name || '');
                var datetimePlaceholder = getProjectDateTimePlaceholder();
                $('#quickEditStartDate, #quickEditEndDate, #quickEditCailyNouki, #quickEditGuisNouki')
                    .attr('placeholder', datetimePlaceholder);
                $('#quickEditStartDate').val(toProjectDateTimeInputValue(p.start_date));
                $('#quickEditEndDate').val(toProjectDateTimeInputValue(p.end_date));
                $('#quickEditStatus').val(p.status || 'draft');
                $('#quickEditAmount').val(p.amount || '');
                $('#quickEditProjectOrderType').val(typeof p.project_order_type === 'string' ? p.project_order_type : (Array.isArray(p.project_order_type) ? (p.project_order_type || []).join(', ') : ''));
                $('input[name="tantou"]').prop('checked', false);
                if (p.tantou === 'CAILY') $('#quickEditTantouCaily').prop('checked', true);
                else if (p.tantou === 'GUIS') $('#quickEditTantouGuis').prop('checked', true);
                $('#quickEditTantouDisplayText').text(p.tantou || '—');
                $('#quickEditCailyNouki').val(toProjectDateTimeInputValue(p.caily_nouki));
                $('#quickEditGuisNouki').val(toProjectDateTimeInputValue(p.guis_nouki));
                $('#quickEditCailyNoukiStatus').prop('checked', !!(p.caily_nouki_status && String(p.caily_nouki_status).indexOf('納品済み') !== -1));
                $('#quickEditGuisNoukiStatus').prop('checked', !!(p.guis_nouki_status && String(p.guis_nouki_status).indexOf('納品済み') !== -1));
                $('#quickEditProgress').val(p.progress != null && p.progress !== '' ? parseInt(p.progress, 10) : 0);
                updateQuickEditNoukiRequiredIndicators();

                // 説明 (description): Quill editor like parent_project edit child project modal (destroy + DOM cleanup để không sinh nhiều instance)
                destroyQuickEditQuill();
                var quickEditDescEl = document.getElementById('quickEditQuillDescription');
                if (quickEditDescEl && window.Quill) {
                    var existingToolbar = quickEditDescEl.parentElement && quickEditDescEl.parentElement.querySelector('.ql-toolbar');
                    if (existingToolbar) existingToolbar.remove();
                    if (quickEditDescEl.classList.contains('ql-container')) {
                        quickEditDescEl.className = 'custom_editor_content';
                        quickEditDescEl.setAttribute('id', 'quickEditQuillDescription');
                    }
                    quickEditDescEl.innerHTML = '';
                    quickEditQuillInstance = new Quill(quickEditDescEl, {
                        bounds: quickEditDescEl,
                        placeholder: '説明を入力してください...',
                        modules: {
                            toolbar: [
                                ['bold', 'italic', 'underline', 'strike'],
                                [{ color: [] }, { background: [] }],
                                ['blockquote', 'code-block'],
                                [{ 'header': 1 }, { 'header': 2 }],
                                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                                [{ 'indent': '-1'}, { 'indent': '+1' }],
                                [{ 'align': [] }],
                                ['link'],
                                ['clean']
                            ]
                        },
                        theme: 'snow'
                    });
                    var descHtml = (p.description || '').toString().trim();
                    if (descHtml) {
                        descHtml = (typeof decodeHtmlEntities === 'function') ? decodeHtmlEntities(descHtml) : descHtml.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&').replace(/&quot;/g, '"');
                        quickEditQuillInstance.root.innerHTML = descHtml;
                    }
                }

                var depId = p.department_id || '';
                var savedCustom = [];
                try {
                    var raw = p.custom_fields;
                    if (typeof raw === 'string' && raw.indexOf('&quot;') !== -1) raw = raw.replace(/&quot;/g, '"');
                    savedCustom = typeof raw === 'string' ? JSON.parse(raw || '[]') : (Array.isArray(raw) ? raw : []);
                } catch (e) { savedCustom = []; }
                var savedValueMap = {};
                savedCustom.forEach(function(f) { if (f && f.label) savedValueMap[String(f.label).trim()] = f.value || ''; });

                axios.get('/api/index.php?model=department&method=getCustomFields').then(function(cfRes) {
                    var sets = cfRes.data || [];
                    var mergedFields = [];
                    // Filter sets by department_id (use strict comparison)
                    sets.filter(function(s) { 
                        return s && s.department_id != null && String(s.department_id) === String(depId); 
                    }).forEach(function(s) {
                        if (s.fields && Array.isArray(s.fields)) {
                            s.fields.forEach(function(f) {
                                if (f && f.label && !mergedFields.some(function(ex) { 
                                    return ex.label && String(ex.label).trim() === String(f.label || '').trim(); 
                                })) {
                                    mergedFields.push({ 
                                        label: f.label || '', 
                                        type: f.type || 'text', 
                                        options: f.options || '',
                                        one_row: (f.one_row === 1 || f.one_row === '1' || f.one_row === true)
                                    });
                                }
                            });
                        }
                    });
                    var $wrap = $('#quickEditCustomFieldsWrap');
                    $wrap.empty();
                    if (mergedFields.length === 0) {
                        // No custom fields found, but don't hide the wrapper
                        return;
                    }
                    mergedFields.forEach(function(f, idx) {
                        var label = f.label;
                        var type = f.type;
                        // Ensure options is a string before calling trim()
                        var options = (f.options != null ? String(f.options) : '').trim();
                        var opts = options ? options.split(',').map(function(s) { return s.trim(); }).filter(Boolean) : [];
                        var val = savedValueMap[String(label).trim()] || '';
                        var safeLabel = String(label).replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        var labelText = (typeof translateText === 'function' ? translateText(safeLabel) : safeLabel);
                        var isOneRow = (f.one_row === 1 || f.one_row === '1' || f.one_row === true);
                        var colClass = type === 'textarea' || isOneRow ? 'col-12' : 'col-md-6';
                        var html = '<div class="' + colClass + ' mb-3 quick-edit-custom-field" data-custom-label="' + safeLabel + '" data-custom-type="' + type + '">';
                        
                        html += '<label class="form-label">' + labelText + '</label>';
                        if (type === 'textarea') {
                            html += '<textarea class="form-control quickEditCustomInput" data-custom-label="' + safeLabel + '" rows="3">' + (val ? String(val).replace(/</g, '&lt;').replace(/>/g, '&gt;') : '') + '</textarea>';
                        } else if (type === 'select') {
                            html += '<select class="form-select quickEditCustomInput" data-custom-label="' + safeLabel + '"><option value="">選択してください</option>';
                            opts.forEach(function(opt) { html += '<option value="' + String(opt).replace(/"/g, '&quot;') + '"' + (val === opt ? ' selected' : '') + '>' + String(opt).replace(/</g, '&lt;') + '</option>'; });
                            html += '</select>';
                        } else if (type === 'radio') {
                            opts.forEach(function(opt) {
                                html += '<div class="form-check"><input class="form-check-input quickEditCustomRadio" type="radio" name="quickEditCustomRadio_' + idx + '" data-custom-label="' + safeLabel + '" value="' + String(opt).replace(/"/g, '&quot;') + '"' + (val === opt ? ' checked' : '') + '><label class="form-check-label">' + String(opt).replace(/</g, '&lt;') + '</label></div>';
                            });
                        } else if (type === 'checkbox') {
                            var arr = val ? String(val).split(',').map(function(s) { return s.trim(); }).filter(Boolean) : [];
                            opts.forEach(function(opt) {
                                var checked = arr.indexOf(opt) !== -1;
                                var optText = (typeof translateText === 'function' ? translateText(String(opt).replace(/</g, '&lt;')) : String(opt).replace(/</g, '&lt;'));
                                html += '<div class="form-check"><input class="form-check-input quickEditCustomCheckbox" type="checkbox" data-custom-label="' + safeLabel + '" value="' + String(opt).replace(/"/g, '&quot;') + '"' + (checked ? ' checked' : '') + '><label class="form-check-label">' + optText + '</label></div>';
                            });
                        } else if (type === 'datetime') {
                            var datetimeVal = val ? toProjectDateTimeInputValue(val) : '';
                            html += '<input type="text" class="form-control quickEditCustomInput quickEditCustomDatetime" data-custom-label="' + safeLabel + '" value="' + (datetimeVal ? String(datetimeVal).replace(/"/g, '&quot;') : '') + '" placeholder="' + datetimePlaceholder.replace(/"/g, '&quot;') + '" autocomplete="off">';
                        } else {
                            html += '<input type="text" class="form-control quickEditCustomInput" data-custom-label="' + safeLabel + '" value="' + (val ? String(val).replace(/"/g, '&quot;') : '') + '">';
                        }
                        html += '</div>';
                        $wrap.append(html);
                    });
                    if (typeof $().flatpickr === 'function') {
                        $wrap.find('.quickEditCustomDatetime').each(function() {
                            initQuickEditFlatpickr(this, { defaultHour: getCustomFieldDefaultHour(), defaultMinute: 0 });
                        });
                    }
                }).catch(function(err) { 
                    console.error('Error loading custom fields:', err);
                    $('#quickEditCustomFieldsWrap').empty(); 
                });

                if (typeof $().flatpickr === 'function') {
                    var fpOnChangeNouki = function() { updateQuickEditNoukiRequiredIndicators(); };
                    initQuickEditFlatpickr('#quickEditStartDate', { defaultHour: getStartDateDefaultHour(), defaultMinute: 0 });
                    initQuickEditFlatpickr('#quickEditEndDate', {
                        defaultHour: getDeadlineDefaultHour(),
                        defaultMinute: 0,
                        onChange: fpOnChangeNouki
                    });
                    ['#quickEditCailyNouki', '#quickEditGuisNouki'].forEach(function(sel) {
                        initQuickEditFlatpickr(sel, {
                            defaultHour: getDeadlineDefaultHour(),
                            defaultMinute: 0,
                            onChange: fpOnChangeNouki
                        });
                    });
                    updateQuickEditNoukiRequiredIndicators();
                }

                // Load team list, project members, department users then init Tagify
                const teamIdsStr = (p.teams || '').toString().trim();
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
                            if (!addedTeamId) return;
                            axios.get('/api/index.php?model=team&method=get&id=' + addedTeamId).then(function(res) {
                                if (res.data && Array.isArray(res.data.members)) {
                                    if (quickEditMembersTagify) {
                                        const teamMembers = res.data.members.map(function(m) { return { id: m.user_id, value: m.user_name || '' }; });
                                        const currentIds = quickEditMembersTagify.value.map(function(tag) { return String(tag.id); });
                                        const toAdd = teamMembers.filter(function(m) { return currentIds.indexOf(String(m.id)) === -1; });
                                        quickEditMembersTagify.addTags(toAdd);
                                    }
                                    var leaders = res.data.members.filter(function(m) { return m.leader == 1 || m.leader === '1'; });
                                    if (leaders.length && quickEditManagerTagify) {
                                        var leaderTags = leaders.map(function(m) { return { id: m.user_id, value: m.user_name || '' }; });
                                        var managerCurrentIds = quickEditManagerTagify.value.map(function(tag) { return String(tag.id); });
                                        var leadersToAdd = leaderTags.filter(function(m) { return managerCurrentIds.indexOf(String(m.id)) === -1; });
                                        quickEditManagerTagify.addTags(leadersToAdd);
                                    }
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

        var quickEditModalEl = document.getElementById('quickEditProjectModal');
        if (quickEditModalEl) {
            quickEditModalEl.addEventListener('hidden.bs.modal', function() {
                destroyQuickEditQuill();
            });
        }

        function isValidDateOrDateTime(str) {
            if (!str || typeof str !== 'string') return false;
            if (str.trim() === '') return false;
            return !!parseProjectDateTimeInDisplayTz(str);
        }

        function hasQuickEditDateValue(value) {
            return !!(value && String(value).trim() !== '');
        }

        function parseQuickEditDateTime(value) {
            if (!hasQuickEditDateValue(value)) return null;
            var parsed = parseProjectDateTimeInDisplayTz(value);
            return parsed ? parsed.toDate() : null;
        }

        function getQuickEditDateFieldValue(selector) {
            syncQuickEditDateFieldsFromPickers();
            return fromProjectDateTimeInputValue($(selector).val() || '');
        }

        function syncQuickEditDateFieldsFromPickers() {
            ['#quickEditStartDate', '#quickEditEndDate', '#quickEditCailyNouki', '#quickEditGuisNouki'].forEach(function(sel) {
                var $el = $(sel);
                if (!$el.length) return;
                var fp = $el.data('flatpickr');
                if (!fp) return;
                if (fp.selectedDates && fp.selectedDates.length > 0) {
                    $el.val(fp.formatDate(fp.selectedDates[0], PROJECT_DATETIME_FLATPICKR_FORMAT));
                } else if (fp._input && fp._input.value) {
                    $el.val(String(fp._input.value).trim());
                }
            });
        }

        function updateQuickEditNoukiRequiredIndicators() {
            var showGuisFields = !isCailyBranchUser();
            var end = ($('#quickEditEndDate').val() || '').trim();
            var endFilled = showGuisFields && hasQuickEditDateValue(end);
            var tantou = ($('input[name="tantou"]:checked').val() || '').trim();
            $('#quickEditCailyNoukiRequired').toggleClass('d-none', !(endFilled && tantou === 'CAILY'));
            $('#quickEditGuisNoukiRequired').toggleClass('d-none', !(endFilled && tantou === 'GUIS'));
        }

        function validateQuickEditNoukiFields() {
            var errors = [];
            var showGuisFields = !isCailyBranchUser();
            var tantou = ($('input[name="tantou"]:checked').val() || '').trim();
            var caily = ($('#quickEditCailyNouki').val() || '').trim();
            var guis = ($('#quickEditGuisNouki').val() || '').trim();
            var end = ($('#quickEditEndDate').val() || '').trim();
            var endFilled = showGuisFields && hasQuickEditDateValue(end);

            if (endFilled) {
                if (tantou === 'CAILY' && !caily) {
                    errors.push({ field: 'caily', message: translateText('担当がCAILYの場合、CAILY納期は必須です') });
                }
                if (tantou === 'GUIS' && !guis) {
                    errors.push({ field: 'guis', message: translateText('担当がGUISの場合、GUIS納期は必須です') });
                }
            }

            if (hasQuickEditDateValue(caily) && hasQuickEditDateValue(guis)) {
                var cailyDate = parseQuickEditDateTime(caily);
                var guisDate = parseQuickEditDateTime(guis);
                if (cailyDate && guisDate && guisDate < cailyDate) {
                    var msg = translateText('GUIS納期はCAILY納期以降である必要があります');
                    if (showGuisFields) {
                        errors.push({ field: 'guis', message: msg });
                    } else {
                        errors.push({ field: 'caily', message: msg });
                    }
                }
            }

            return errors;
        }

        function revalidateQuickEditNoukiOnChange() {
            updateQuickEditNoukiRequiredIndicators();
            var $cailyNouki = $('#quickEditCailyNouki');
            var $guisNouki = $('#quickEditGuisNouki');
            $cailyNouki.removeClass('is-invalid');
            $guisNouki.removeClass('is-invalid');
            $('#quickEditCailyNoukiError').text('');
            $('#quickEditGuisNoukiError').text('');
            var noukiErrors = validateQuickEditNoukiFields();
            noukiErrors.forEach(function(err) {
                if (err.field === 'caily') {
                    $cailyNouki.addClass('is-invalid');
                    $('#quickEditCailyNoukiError').text(err.message);
                } else if (err.field === 'guis') {
                    $guisNouki.addClass('is-invalid');
                    $('#quickEditGuisNoukiError').text(err.message);
                }
            });
        }

        $(document).off('change.quickeditnouki').on('change.quickeditnouki', '#quickEditProjectForm input[name="tantou"]', revalidateQuickEditNoukiOnChange);
        $(document).off('change.quickeditnoukidate input.quickeditnoukidate').on('change.quickeditnoukidate input.quickeditnoukidate', '#quickEditEndDate, #quickEditCailyNouki, #quickEditGuisNouki', revalidateQuickEditNoukiOnChange);

        $('#quickEditProjectSaveBtnHeader').off('click.quickedit').on('click.quickedit', function() { $('#quickEditProjectSaveBtn').trigger('click.quickedit'); });

        $('#quickEditProjectSaveBtn').off('click.quickedit').on('click.quickedit', function() {
            const id = $('#quickEditProjectId').val();
            if (!id) {
                if (typeof showMessage === 'function') showMessage(translateText('プロジェクトデータを読み込み中です。しばらくお待ちください。'), true);
                return;
            }
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
                    $('#quickEditNameError').text(translateText('案件名は必須です。'));
                    hasError = true;
                }
                if (!$orderType.val() || $orderType.val().toString().trim() === '') {
                    $orderType.addClass('is-invalid');
                    $('#quickEditProjectOrderTypeError').text(translateText('受注形態は必須です。'));
                    hasError = true;
                }
                if (!$('input[name="tantou"]:checked').length) {
                    $tantouWrap.addClass('is-invalid');
                    $('#quickEditTantouError').text(translateText('担当は必須です。'));
                    hasError = true;
                }
                if ($startDate.val() && $startDate.val().toString().trim() !== '' && !isValidDateOrDateTime($startDate.val())) {
                    $startDate.addClass('is-invalid');
                    $('#quickEditStartDateError').text(translateText('開始日の形式が正しくありません。（例: 2025-01-15 09:00）'));
                    hasError = true;
                }
                if (!isCailyBranchUser() && $endDate.val() && $endDate.val().toString().trim() !== '' && !isValidDateOrDateTime($endDate.val())) {
                    $endDate.addClass('is-invalid');
                    $('#quickEditEndDateError').text(translateText('期限日の形式が正しくありません。（例: 2025-02-28 18:00）'));
                    hasError = true;
                }
                if ($cailyNouki.val() && $cailyNouki.val().toString().trim() !== '' && !isValidDateOrDateTime($cailyNouki.val())) {
                    $cailyNouki.addClass('is-invalid');
                    $('#quickEditCailyNoukiError').text(translateText('CAILY納期の形式が正しくありません。（例: 2025-01-20 18:00）'));
                    hasError = true;
                }
                if ($guisNouki.length && $guisNouki.val() && $guisNouki.val().toString().trim() !== '' && !isValidDateOrDateTime($guisNouki.val())) {
                    $guisNouki.addClass('is-invalid');
                    $('#quickEditGuisNoukiError').text(translateText('GUIS納期の形式が正しくありません。（例: 2025-01-25 18:00）'));
                    hasError = true;
                }
                var startVal = ($startDate.val() || '').trim();
                var endVal = !isCailyBranchUser() ? ($endDate.val() || '').trim() : '';
                if (startVal && endVal) {
                    var startDt = parseQuickEditDateTime(startVal);
                    var endDt = parseQuickEditDateTime(endVal);
                    if (startDt && endDt && startDt >= endDt) {
                        $endDate.addClass('is-invalid');
                        $('#quickEditEndDateError').text(translateText('期限日は開始日より後である必要があります'));
                        hasError = true;
                    }
                }
                var noukiErrors = validateQuickEditNoukiFields();
                noukiErrors.forEach(function(err) {
                    if (err.field === 'caily') {
                        $cailyNouki.addClass('is-invalid');
                        $('#quickEditCailyNoukiError').text(err.message);
                        hasError = true;
                    } else if (err.field === 'guis') {
                        $guisNouki.addClass('is-invalid');
                        $('#quickEditGuisNoukiError').text(err.message);
                        hasError = true;
                    }
                });
            }
            var progressVal = $progress.val();
                if (progressVal !== '' && progressVal != null) {
                var p = parseInt(progressVal, 10);
                if (isNaN(p) || p < 0 || p > 100) {
                    $progress.addClass('is-invalid');
                    $('#quickEditProgressError').text(translateText('進捗率は0〜100の範囲で入力してください。'));
                    hasError = true;
                }
            }
            if (hasError) {
                return;
            }
            const $btn = $('#quickEditProjectSaveBtn, #quickEditProjectSaveBtnHeader');
            const $spinner = $('#quickEditSaveSpinner, #quickEditSaveSpinnerHeader');
            $btn.prop('disabled', true);
            $spinner.removeClass('d-none');
            const formData = new FormData();
            formData.append('model', 'project');
            formData.append('method', 'update');
            formData.append('id', id);
            formData.append('name', $('#quickEditName').val() || '');
            formData.append('start_date', getQuickEditDateFieldValue('#quickEditStartDate'));
            formData.append('end_date', getQuickEditDateFieldValue('#quickEditEndDate'));
            formData.append('status', $('#quickEditStatus').val() || 'draft');
            formData.append('amount', $('#quickEditAmount').val() || '');
            formData.append('tantou', $('input[name="tantou"]:checked').val() || '');
            formData.append('caily_nouki', getQuickEditDateFieldValue('#quickEditCailyNouki'));
            formData.append('guis_nouki', getQuickEditDateFieldValue('#quickEditGuisNouki'));
            formData.append('caily_nouki_status', $('#quickEditCailyNoukiStatus').is(':checked') ? '納品済み' : '');
            formData.append('guis_nouki_status', $('#quickEditGuisNoukiStatus').is(':checked') ? '納品済み' : '');
            formData.append('progress', $('#quickEditProgress').val() !== '' ? parseInt($('#quickEditProgress').val(), 10) : 0);
            formData.append('project_order_type', $('#quickEditProjectOrderType').val() || '');
            formData.append('teams', (quickEditTeamTagify && quickEditTeamTagify.value) ? quickEditTeamTagify.value.map(function(t) { return t.id; }).join(',') : '');
            formData.append('managers', (quickEditManagerTagify && quickEditManagerTagify.value) ? quickEditManagerTagify.value.map(function(t) { return t.id; }).join(',') : '');
            formData.append('members', (quickEditMembersTagify && quickEditMembersTagify.value) ? quickEditMembersTagify.value.map(function(t) { return t.id; }).join(',') : '');
            var descContent = (quickEditQuillInstance && typeof quickEditQuillInstance.getSemanticHTML === 'function') ? quickEditQuillInstance.getSemanticHTML() : ($('#quickEditQuillDescriptionTextarea').val() || '');
            formData.append('description', descContent);
            var customFieldsData = [];
            $('#quickEditCustomFieldsWrap .quick-edit-custom-field').each(function() {
                var $field = $(this);
                var label = $field.attr('data-custom-label');
                var type = $field.attr('data-custom-type');
                if (!label) return;
                var value = '';
                if (type === 'checkbox') {
                    var checked = $field.find('.quickEditCustomCheckbox:checked').map(function() { return $(this).val(); }).get();
                    value = checked.join(',');
                } else if (type === 'radio') {
                    var checkedEl = $field.find('.quickEditCustomRadio:checked');
                    value = checkedEl.length ? checkedEl.val() : '';
                } else {
                    var input = $field.find('.quickEditCustomInput');
                    value = input.length ? (input.val() || '').trim() : '';
                    if (type === 'datetime') {
                        value = fromProjectDateTimeInputValue(value);
                    }
                }
                customFieldsData.push({ label: label, value: value });
            });
            if (customFieldsData.length) formData.append('custom_fields', JSON.stringify(customFieldsData));
            axios.post('/api/index.php?model=project&method=update', formData, { headers: { 'Content-Type': 'multipart/form-data' } }).then(function() {
                bootstrap.Modal.getInstance(document.getElementById('quickEditProjectModal')).hide();
                if (projectTable) projectTable.ajax.reload(null, false);
                if (typeof showMessage === 'function') showMessage(translateText('プロジェクトを更新しました。'));
            }).catch(function(err) {
                console.error('Quick edit save:', err);
                if (typeof alert === 'function') alert(err.response && err.response.data && err.response.data.message ? err.response.data.message : translateText('更新に失敗しました。'));
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
                app.openNoteModalFromList(contextMenuProjectId, null, contextMenuNoteType);
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
            const $cell = $(this).closest('.empty-notes-cell');
            const projectId = $cell.data('project-id');
            const noteType = $cell.closest('td').data('notes-type') === 'guis' ? 2 : 1;
            if (projectId && window.app && app.openNoteModalFromList) {
                app.openNoteModalFromList(projectId, null, noteType);
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

        if (typeof $().flatpickr === 'function') {
            $('#start_date').flatpickr(getProjectFlatpickrOptions({ defaultHour: getStartDateDefaultHour(), defaultMinute: 0 }));
            $('#end_date').flatpickr(getProjectFlatpickrOptions({ defaultHour: getDeadlineDefaultHour(), defaultMinute: 0 }));
        }

        // Khởi tạo flatpickr dạng tháng (month picker) cho filterStartMonth và filterEndMonth
        if (window.flatpickr) {
            var monthPickerLocale = getProjectFlatpickrLocale();
            var monthAltFormat = isVietnameseLocale() ? 'm/Y' : 'Y年m月';
            $('#filterStartMonth').flatpickr({
                locale: monthPickerLocale,
                plugins: [new monthSelectPlugin({
                    shorthand: true,
                    dateFormat: 'Y-m',
                    altFormat: monthAltFormat,
                })],
                onChange: function(date) {
                    saveFiltersToLocalStorage();
                    renderActiveFilters();
                }
            });
            $('#filterEndMonth').flatpickr({
                locale: monthPickerLocale,
                plugins: [new monthSelectPlugin({
                    shorthand: true,
                    dateFormat: 'Y-m',
                    altFormat: monthAltFormat,
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
        $('#filterStartMonth, #filterEndMonth, #filterPriority, #filterProgress, #filterTimeLeft, #filterToday, #filterProjectOrderType, #filterTeam, #filterTantou, #filterNoDates, #filterKeyword, #showInactiveSwitch').on('change input', function() {
           renderActiveFilters();
        });
        let timer = null;
        $('#filterKeyword').on('input', function() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                renderActiveFilters();
            }, 500);
        });
        initFilterKeepTeamOnResetCheckbox();

        // Đảm bảo badge update khi reset filter
        $('#filterReset').on('click', function() {
            const keepTeam = $('#filterKeepTeamOnReset').is(':checked');
            const preservedTeams = keepTeam ? getFilterTeamValue() : [];

            // Reset các filter về mặc định
            $('#projectFilterForm')[0].reset();
            $('#filterStartMonth').val('');
            $('#filterEndMonth').val('');
            $('#filterPriority').val('');
            $('#filterProgress').val('');
            $('#filterTimeLeft').val('');
            $('#filterToday').val('');
            $('#filterProjectOrderType').val('');
            if (keepTeam) {
                $('#filterTeam').val(preservedTeams.length ? preservedTeams : null).trigger('change');
            } else {
                $('#filterTeam').val(null).trigger('change');
            }
            $('#filterTantou').val('');
            $('#filterNoDates').prop('checked', false);
            $('#filterKeyword').val('');
            $('#filterProjectId').val('');
            $('#showInactiveSwitch').prop('checked', false);
            // Reset favorites filter
            $('#filterFavoritesOnly').prop('checked', false);
            $('#filterMyProjects').prop('checked', false);
            if (app) {
                app.showClearAllFavoritesBtn = false;
            }
            localStorage.removeItem(FILTER_STORAGE_KEY);
            if (keepTeam && preservedTeams.length) {
                try {
                    localStorage.setItem(FILTER_STORAGE_KEY, JSON.stringify({ filterTeam: preservedTeams }));
                } catch (e) {}
            }
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
            }, 10000); // Cập nhật mỗi 10 giây
        }
        
        // Wait a bit for Vue app to mount
        setTimeout(function() {
            if (app && app.selectedDepartment && app.selectedDepartment.id) {
                initializeProjectTable();
            }
        }, 100);
    });
    
    // Handle browser back/forward button (pageshow event)
    window.addEventListener('pageshow', function(event) {
        // event.persisted is true when page is loaded from cache (back/forward navigation)
        if (event.persisted) {
            // Reload filters from localStorage to ensure correct state (especially favorites_only)
            loadFiltersFromLocalStorage();
            if (window.app && Array.isArray(window.app.teams)) {
                refreshFilterTeamSelect(window.app.teams);
            }

            // Reload departments and reinitialize if needed
            if (window.app) {
                // Always reload departments to ensure fresh data
                if (!window.app.departments || window.app.departments.length === 0) {
                    window.app.loadDepartments();
                } else {
                    // If departments are already loaded, check if we need to restore selected department
                    const savedDepartment = window.app.loadSelectedDepartmentFromLocalStorage();
                    if (savedDepartment && (!window.app.selectedDepartment || window.app.selectedDepartment.id !== savedDepartment.id)) {
                        // Find and select the saved department
                        const department = window.app.departments.find(d => d && d.id == savedDepartment.id && d.can_project == 1);
                        if (department) {
                            window.app.viewProjects(department);
                        }
                    } else if (window.app.selectedDepartment && window.app.selectedDepartment.id) {
                        // If department is already selected, ensure DataTable is initialized and reload data
                        setTimeout(function() {
                            if (!projectTable || !$.fn.DataTable.isDataTable('#projectTable')) {
                                initializeProjectTable();
                            } else {
                                // Reload data if table already exists
                                projectTable.ajax.reload(null, false);
                            }
                        }, 100);
                    }
                }
            }
        }
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

    function buildI18nHeaderTitle(key) {
        var k = String(key || '').trim();
        if (!k) return '';
        return '<span data-i18n="' + k + '">' + translateText(k) + '</span>';
    }

    function applyI18nToProjectTableUI() {
        if (typeof window.applyDataI18n !== 'function') return;
        var tableWrapper = document.getElementById('projectTable_wrapper');
        if (tableWrapper) window.applyDataI18n(tableWrapper);
        var tableEl = document.getElementById('projectTable');
        if (tableEl) window.applyDataI18n(tableEl);
        var colVisMenu = document.getElementById('columnVisibilityMenu');
        if (colVisMenu) window.applyDataI18n(colVisMenu);
    }

    /** Sau khi Vue cập nhật danh sách 列の表示 — áp dịch data-i18n cho nhãn cột */
    function scheduleColumnVisibilityMenuI18n() {
        if (typeof window === 'undefined' || !window.app || typeof window.app.$nextTick !== 'function') {
            applyI18nToProjectTableUI();
            return;
        }
        window.app.$nextTick(function() {
            applyI18nToProjectTableUI();
        });
    }

    /** Build data-todo-title and data-todo-link for context menu "Thêm vào todo" */
    function getTodoDataAttrs(data, type) {
        if (!data || data.id == null) return '';
        var title = String('#' + data.id + ' ' + (data.name || '')) + (type ? ' ' + type : '');
        var link = String('/project/detail.php?id=' + data.id).replace(/"/g, '&quot;');
        return ' data-todo-title="' + title + '" data-todo-link="' + link + '"';
    }

    function getTimeRemaining(endDate, status) {
        if (!endDate || status === 'paused' || status === 'completed' || status === 'deleted' || status === 'draft' || status === 'cancelled') {
            return null;
        }
        
        const now = moment.tz('Asia/Tokyo');
        const end = moment.tz(endDate, 'Asia/Tokyo');
        
        const isVietnamese = typeof i18next !== 'undefined' && i18next.isInitialized && i18next.language === 'vi';
        const overdueLabel = translateText('超過');
        const dayLabel = translateText('日');
        const hourLabel = translateText('時間');
        const minuteLabel = translateText('分');
        // Short format for badge (max ~70px): d/h/m + 超
        const d = 'd', h = 'h', m = 'm';
        const 超 = '超';
        
        if (end.isBefore(now)) {
            const diff = now.diff(end);
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            let text = '', fullText = '';
            if (days > 0) {
                text = days + d + (hours > 0 ? ' ' + hours + h : '') + ' ' + 超;
                fullText = isVietnamese ? (days + ' ' + dayLabel + (hours > 0 ? ' ' + hours + ' ' + hourLabel : '') + ' ' + overdueLabel) : (days + '日' + (hours > 0 ? hours + '時' : '') + overdueLabel);
            } else if (hours > 0) {
                text = hours + h + ' ' + 超;
                fullText = isVietnamese ? (hours + ' ' + hourLabel + ' ' + overdueLabel) : (hours + '時' + overdueLabel);
            } else {
                text = minutes + m + ' ' + 超;
                fullText = isVietnamese ? (minutes + ' ' + minuteLabel + ' ' + overdueLabel) : (minutes + '分' + overdueLabel);
            }
            return { text: text, fullText: fullText, class: 'bg-danger', isOverdue: true };
        } else {
            const diff = end.diff(now);
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            let text = '+', fullText = '+';
            let class_name = 'bg-label-info';
            if (days > 0) {
                text += days + d + (hours > 0 ? ' ' + hours + h : '');
                fullText = isVietnamese ? ('+' + days + ' ' + dayLabel + (hours > 0 ? ' ' + hours + ' ' + hourLabel : '')) : ('+' + days + '日' + (hours > 0 ? hours + '時' : ''));
            } else if (hours > 0) {
                text += hours + h;
                fullText = isVietnamese ? ('+' + hours + ' ' + hourLabel) : ('+' + hours + '時');
                class_name = hours <= 24 ? 'bg-label-warning' : 'bg-label-info';
            } else {
                text += minutes + m;
                fullText = isVietnamese ? ('+' + minutes + ' ' + minuteLabel) : ('+' + minutes + '分');
                class_name = 'bg-label-warning';
            }
            return { text: text, fullText: fullText, class: class_name, isOverdue: false };
        }
    }

    function getOverdueDeadlineMoment(row) {
        if (!row) return null;
        const skipStatuses = ['completed', 'cancelled', 'paused', 'deleted'];
        if (skipStatuses.includes(row.status)) return null;

        if (isCailyBranchUser()) {
            if (row.caily_nouki_status && String(row.caily_nouki_status).indexOf('納品済み') !== -1) return null;
            return parseProjectDateMoment(row.caily_nouki);
        }

        return parseProjectDateMoment(row.end_date);
    }

    // Helper function to check if project is overdue (期限超過 badge on index list)
    function isProjectOverdue(row) {
        const deadline = getOverdueDeadlineMoment(row);
        if (!deadline || !deadline.isValid()) return false;
        const now = typeof moment !== 'undefined' && moment.tz
            ? moment.tz('Asia/Tokyo')
            : (typeof moment !== 'undefined' ? moment() : null);
        if (!now || !now.isValid()) return false;
        return deadline.isBefore(now);
    }
    
    // Helper function to check if project period is undecided (期間未定)
    // Similar to project-gantt.js logic
    function isPeriodUndecided(row) {
        const skipStatuses = ['completed', 'cancelled', 'paused', 'deleted'];
        if (skipStatuses.includes(row.status)) return false;
        return !row.start_date || (!row.end_date);
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
                loading: true,
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
                    display_column: '',
                    user_id: null
                },
                currentNoteProjectId: null,
                quillNoteInstance: null,
                quillNoteContent: '',
                // Kadai queue properties
                kadaiProjects: [],
                isKadaiQueueExpanded: false,
                // Column visibility (thứ tự đồng bộ với bảng sau khi merge COLUMN_ORDER)
                availableColumns: buildAvailableColumnsList([], null, loadColumnVisibilityFromLocalStorage([]))
            }
        },
        computed: {
            isCailyBranchUser() {
                return typeof window !== 'undefined' && window.IS_CAILY_BRANCH_USER === true;
            },
            visibleColumnOptions() {
                if (!this.isCailyBranchUser) return this.availableColumns || [];
                return (this.availableColumns || []).filter(function(col) {
                    return col.key !== 'guis_nouki' && col.key !== 'end_date';
                });
            },
            createUrl() {
               if(this.selectedDepartment) {
                return `create.php?department_id=${this.selectedDepartment.id}`;
               }
               return `create.php`;
            },
            /** Options cho select 表示列: NOTE_DISPLAY_COLUMNS (giống detail) + custom fields, loại trùng tên khác suffix 状況 */
            noteDisplayColumnOptions() {
                function normLabel(t) { return (t || '').replace(/状況$/, ''); }
                const hiddenForCaily = this.isCailyBranchUser ? { guis_nouki: true, end_date: true } : {};
                const list = (typeof NOTE_DISPLAY_COLUMNS !== 'undefined' ? NOTE_DISPLAY_COLUMNS : [])
                    .filter(function(c) { return !hiddenForCaily[c.key]; })
                    .map(function(c) {
                    return { value: c.key, text: c.label };
                });
                const seen = {};
                const seenNorm = {};
                list.forEach(function(o) {
                    seen[o.value] = true;
                    seenNorm[normLabel(o.text)] = true;
                });
                const customDefs = (typeof customFieldColumnDefinitions !== 'undefined' && customFieldColumnDefinitions) ? customFieldColumnDefinitions : [];
                customDefs.forEach(function(c) {
                    var val = 'custom:' + (c.label || '').trim();
                    var n = normLabel(c.label);
                    if (val !== 'custom:' && !seen[val] && !seenNorm[n]) {
                        seen[val] = true;
                        seenNorm[n] = true;
                        list.push({ value: val, text: (c.label || '').trim() });
                    }
                });
                return list;
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
            
            // Load column visibility (thứ tự giống bảng khi đã chọn department / có save order)
            const columnVisibility = loadColumnVisibilityFromLocalStorage();
            const depIdMount = this.selectedDepartment && this.selectedDepartment.id;
            this.availableColumns = buildAvailableColumnsList([], depIdMount, columnVisibility);
            this.$nextTick(() => {
                applyI18nToProjectTableUI();
            });
            
            this.loadDepartments();
            // Không load dự án ngay lập tức, chỉ load khi có department được chọn

            window.addEventListener('ai-action-success', (event) => {
                const { action } = event.detail || {};
                if (action && action.type && (action.type.indexOf('project_') === 0 || action.type.indexOf('parent_project_') === 0)) {
                    this.loadProjects();
                }
            });
            
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
                        whitelist: ['新規', '修正', '免震', '耐震', '計画変更', '契約図', '実施図'],
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
                this.loading = true;
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
                        } else {
                            this.loading = false;
                        }
                    } else {
                        throw new Error('No department found');
                    }
                } catch (error) {
                    console.error('Error loading departments:', error);
                    this.departments = [];
                    this.loading = false;
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
                this.destroyQuillNoteEditor();
                this.quillNoteContent = '';
                
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
                    needs_confirmation: 0,
                    display_column: '',
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
                            needs_confirmation: Number(match.needs_confirmation) || 0,
                            display_column: (match.display_column != null && match.display_column !== undefined) ? String(match.display_column) : '',
                            user_id: match.user_id
                        };
                    }
                    this.$nextTick(() => {
                        this.initQuillNoteEditor();
                    });
                });
            },
            openNoteModalFromList(projectId, noteContent = null, noteType = 0, displayColumnKey = '') {
                this.currentNoteProjectId = projectId;
                this.showNoteModal = true;
                this.isNoteEditMode = true;
                this.currentEditingNoteId = null; // No specific note ID for fallback mode
                this.destroyQuillNoteEditor();
                this.quillNoteContent = '';
                // noteType: 1 = CAILYメモ, 2 = GUISメモ. Nếu noteType = 0, set mặc định theo branch hiện tại (NOTE_DEFAULT_TYPE)
                if (!noteType && typeof window !== 'undefined' && typeof window.NOTE_DEFAULT_TYPE !== 'undefined') {
                    var def = parseInt(window.NOTE_DEFAULT_TYPE, 10);
                    if (!isNaN(def)) noteType = def;
                }
                this.editingNote = {
                    id: null,
                    title: '',
                    content: '',
                    is_important: false,
                    needs_confirmation: noteType || 0,
                    display_column: displayColumnKey || '',
                    user_id: null
                };
                this.loadNotesForProject(projectId).then(() => {
                    if (noteContent) {
                        const trimmed = noteContent.trim();
                        const match = this.notes.find(n => (n.content || '').trim() === trimmed && (n.needs_confirmation == 1 || n.needs_confirmation == 2));
                        if (match) {
                            this.editingNote = {
                                id: match.id,
                                title: match.title,
                                content: decodeHtmlForNote(match.content),
                                is_important: match.is_important == 1,
                                needs_confirmation: Number(match.needs_confirmation) || 0,
                                display_column: (match.display_column != null && match.display_column !== undefined) ? String(match.display_column) : (displayColumnKey || ''),
                                user_id: match.user_id
                            };
                        } else {
                            this.editingNote.content = decodeHtmlForNote(noteContent);
                            this.editingNote.needs_confirmation = noteType || 0;
                        }
                    }
                    this.$nextTick(() => {
                        this.initQuillNoteEditor();
                    });
                });
            },
            closeNoteModal() {
                this.showNoteModal = false;
                this.isNoteEditMode = false;
                this.currentEditingNoteId = null; // Clear editing note tracking
                this.destroyQuillNoteEditor();
                
                // Refresh table to remove highlight
                if (projectTable) {
                    projectTable.draw(false);
                }
                
                this.editingNote = {
                    id: null,
                    title: '',
                    content: '',
                    is_important: false,
                    needs_confirmation: 0,
                    display_column: '',
                    user_id: null
                };
                this.quillNoteContent = '';
            },
            getNoteDisplayColumnLabel(value) {
                if (!value) return '';
                const opts = this.noteDisplayColumnOptions || [];
                const o = opts.find(function(x) { return x.value === value; });
                return o ? o.text : value;
            },
            initQuillNoteEditor() {
                if (this.quillNoteInstance || !this.isNoteEditMode || !this.showNoteModal) return;
                setTimeout(() => {
                    const toolbarOptions = [
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ color: [] }, { background: [] }],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        [{ header: '1' }, { header: '2' }, 'blockquote'],
                        ['link', 'clean']
                    ];
                    const el = document.getElementById('quill_note_content');
                    if (!el) return;
                    if (this.quillNoteInstance) {
                        try {
                            this.quillNoteInstance = null;
                        } catch (e) {}
                    }
                    this.quillNoteInstance = new Quill(el, {
                        bounds: el,
                        placeholder: 'メモの詳細を入力してください...',
                        modules: {
                            toolbar: {
                                container: toolbarOptions
                            }
                        },
                        theme: 'snow'
                    });
                    if (this.editingNote.content) {
                        const html = typeof decodeHtmlEntities !== 'undefined' ? decodeHtmlEntities(this.editingNote.content) : this.editingNote.content;
                        this.quillNoteInstance.root.innerHTML = html;
                    }
                    this.quillNoteContent = this.quillNoteInstance.getSemanticHTML();
                    this.quillNoteInstance.on('text-change', () => {
                        this.quillNoteContent = this.quillNoteInstance.getSemanticHTML();
                    });
                }, 200);
            },
            destroyQuillNoteEditor() {
                if (this.quillNoteInstance) {
                    try {
                        this.quillNoteInstance = null;
                    } catch (e) {}
                }
                // Clear Quill DOM content to avoid reusing old HTML when creating new note
                try {
                    const el = document.getElementById('quill_note_content');
                    if (el) {
                        el.innerHTML = '';
                    }
                } catch (e) {}
                this.quillNoteContent = '';
            },
            async saveNote() {
                // Lấy nội dung từ Quill editor nếu có, nếu không dùng editingNote.content
                const rawContent = (this.quillNoteContent && this.quillNoteContent.trim()) || (this.editingNote.content || '').trim();
                let title = (this.editingNote.title || '').trim();
                if (!title) {
                    // Lấy dòng đầu tiên của nội dung, giới hạn độ dài (strip HTML tags)
                    const textContent = rawContent.replace(/<[^>]*>/g, '').trim();
                    title = textContent.split(/\r?\n/)[0].slice(0, 50) || 'メモ';
                }
                try {
                    const formData = new FormData();
                    formData.append('project_id', this.currentNoteProjectId);
                    formData.append('title', title);
                    formData.append('content', rawContent);
                    formData.append('is_important', this.editingNote.is_important ? 1 : 0);
                    formData.append('needs_confirmation', this.editingNote.needs_confirmation ? this.editingNote.needs_confirmation : 0);
                    formData.append('display_column', this.editingNote.display_column || '');
                    
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
            async deleteCurrentNote() {
                if (!this.editingNote || !this.editingNote.id) return;
                if (!confirm('このメモを削除しますか？')) return;
                try {
                    const formData = new FormData();
                    formData.append('id', this.editingNote.id);
                    const response = await axios.post('/api/index.php?model=project&method=deleteNote', formData);
                    if (response.data && response.data.status === 'success') {
                        showMessage('メモが削除されました');
                        this.closeNoteModal();
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
                
                this.loading = true;
                this.selectedDepartment = department;
                // Cập nhật context chat để AI biết đang xem danh sách dự án của department nào
                if (typeof window !== 'undefined') {
                    window.__chatPageContext = window.__chatPageContext || {};
                    window.__chatPageContext.page = 'project_list';
                    window.__chatPageContext.department_id = department && department.id ? department.id : null;
                }
                // Save selected department to localStorage
                this.saveSelectedDepartmentToLocalStorage(department);
                
                // Destroy bảng cũ để refresh đúng custom fields và 列の表示 của department mới
                destroyProjectTable();
                
                // Đợi Vue cập nhật DOM rồi init lại DataTable, xong mới reload (tránh init chưa xong đã gọi loadProjects)
                this.$nextTick(async () => {
                    try {
                        await initializeProjectTable();
                        if (projectTable && $.fn.DataTable.isDataTable('#projectTable')) {
                            projectTable.ajax.reload();
                        }
                    } finally {
                        this.loading = false;
                    }
                });

                
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
                    refreshFilterTeamSelect(this.teams);
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
                // Save filter state to localStorage
                saveFiltersToLocalStorage();
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
                return formatProjectDateTimeInline(dateString);
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
                
                // Apply to DataTable if it exists (base or custom column)
                if (projectTable && $.fn.DataTable.isDataTable('#projectTable')) {
                    const dtIndex = getDataTableColumnIndexByKey(columnKey, customFieldColumnDefinitions);
                    if (dtIndex !== null) {
                        projectTable.column(dtIndex).visible(isVisible, false);
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

    // Fixed filter button: show when scroll reaches #projectTableCard; offcanvas shows #projectFilterBox content
    (function() {
        var floatBtn = document.getElementById('projectFilterFloatBtn');
        var projectTableCard = document.getElementById('projectTableCard');
        var projectFilterBox = document.getElementById('projectFilterBox');
        var offcanvasBody = document.getElementById('projectFilterOffcanvasBody');
        var offcanvasEl = document.getElementById('offcanvasProjectFilter');
        if (!floatBtn || !projectTableCard || !projectFilterBox || !offcanvasBody || !offcanvasEl) return;

        var filterCard = null; // ref to moved .card

        function updateFloatButtonVisibility() {
            var rect = projectTableCard.getBoundingClientRect();
            if (rect.top <= 120) {
                floatBtn.classList.remove('d-none');
            } else {
                floatBtn.classList.add('d-none');
            }
        }

        window.addEventListener('scroll', function() { updateFloatButtonVisibility(); }, { passive: true });
        window.addEventListener('resize', updateFloatButtonVisibility);
        updateFloatButtonVisibility();

        offcanvasEl.addEventListener('show.bs.offcanvas', function() {
            if (!projectFilterBox.firstElementChild) return;
            filterCard = projectFilterBox.firstElementChild;
            var cardHeight = filterCard.offsetHeight;
            projectFilterBox.removeChild(filterCard);
            offcanvasBody.appendChild(filterCard);
            var placeholder = document.createElement('div');
            placeholder.className = 'project-filter-box-placeholder';
            placeholder.setAttribute('aria-hidden', 'true');
            placeholder.style.height = cardHeight + 'px';
            placeholder.style.minHeight = cardHeight + 'px';
            projectFilterBox.appendChild(placeholder);
        });

        offcanvasEl.addEventListener('hidden.bs.offcanvas', function() {
            var placeholder = projectFilterBox.querySelector('.project-filter-box-placeholder');
            if (placeholder) {
                projectFilterBox.removeChild(placeholder);
            }
            if (filterCard && offcanvasBody.contains(filterCard)) {
                offcanvasBody.removeChild(filterCard);
                projectFilterBox.insertBefore(filterCard, projectFilterBox.firstChild);
            }
            filterCard = null;
        });
    })();

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
