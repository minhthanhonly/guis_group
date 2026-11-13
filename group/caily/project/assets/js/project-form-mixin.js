/**
 * Project form logic shared by every page that shows or edits a 案件:
 *   - the project detail page (project-detail.js) uses it as a mixin of its page app
 *   - the shared edit / create dialog (project-form-modal.js) builds its own app on it
 * so the form behaves the same wherever it is opened.
 *
 * The members below were moved here from project-detail.js unchanged. They reach page-level
 * helpers (date conversion, version lock, composite custom fields, lazy loaders) by bare name;
 * those helpers are repeated inside this closure so the mixin also works on pages that
 * do not load project-detail.js.
 */
(function () {
    // ---- helpers (same as the top of project-detail.js) ----

    /** Asset helpers — Quill / comment deferred off critical path (D6). */
    function projectDetailAssetRoot() {
        return (typeof window.ROOT !== 'undefined' && window.ROOT) ? window.ROOT : '/';
    }
    function projectDetailCacheV() {
        return window.__CACHE_VERSION
            || (document.documentElement && document.documentElement.getAttribute('data-cache-version'))
            || '';
    }
    function projectDetailProjectCacheV() {
        return window.__PROJECT_CACHE_VERSION || projectDetailCacheV();
    }
    function withProjectDetailAppLoader() {
        return window.AppLoader || null;
    }
    function ensureProjectDetailQuill() {
        if (typeof window.Quill !== 'undefined') {
            return Promise.resolve();
        }
        if (window.__projectDetailQuillLoading) {
            return window.__projectDetailQuillLoading;
        }
        var loader = withProjectDetailAppLoader();
        if (!loader) {
            return Promise.reject(new Error('AppLoader missing'));
        }
        var root = projectDetailAssetRoot();
        var cv = projectDetailCacheV();
        var q = function (path) { return root + path + (cv ? ('?v=' + cv) : ''); };
        window.__projectDetailQuillLoading = Promise.all([
            loader.loadStyle(q('assets/vendor/libs/quill/typography.css')),
            loader.loadStyle(q('assets/vendor/libs/quill/editor.css')),
            // Quill image-upload placeholders (was task-manager.css on critical path)
            loader.loadStyle(root + 'project/assets/css/task-manager.css' + (cv ? ('?v=' + cv) : ''))
        ]).then(function () {
            return loader.loadScript(q('assets/vendor/libs/quill/quill.js'));
        }).catch(function (err) {
            delete window.__projectDetailQuillLoading;
            throw err;
        });
        return window.__projectDetailQuillLoading;
    }
    function ensureProjectDetailCommentAssets() {
        if (window.CommentComponent && typeof MentionManager !== 'undefined') {
            return ensureProjectDetailQuill();
        }
        if (window.__projectDetailCommentLoading) {
            return window.__projectDetailCommentLoading;
        }
        var loader = withProjectDetailAppLoader();
        if (!loader) {
            return Promise.reject(new Error('AppLoader missing'));
        }
        var root = projectDetailAssetRoot();
        var cv = projectDetailCacheV();
        var q = function (path) { return root + path + (cv ? ('?v=' + cv) : ''); };
        window.__projectDetailCommentLoading = ensureProjectDetailQuill().then(function () {
            return Promise.all([
                loader.loadStyle(q('assets/css/mention.css')),
                loader.loadStyle(q('assets/css/comment-component.css'))
            ]);
        }).then(function () {
            return loader.loadScripts([
                q('assets/js/mention.js'),
                q('assets/js/comment-component.js')
            ]);
        }).catch(function (err) {
            delete window.__projectDetailCommentLoading;
            throw err;
        });
        return window.__projectDetailCommentLoading;
    }

    /** 設備図 / 意匠図 / 配置図: セットの選択肢のまま、値選択時に【メモ】を表示 */
    /** 省エネ projects show these composite fields inside 省エネ向け資料, in this order. */
    var ENERGY_MATERIAL_COMPOSITE_ORDER = ['意匠図', '設備図', '配置図'];
    var CUSTOM_FIELD_COMPOSITE_BASE_LABELS = { '設備図': true, '意匠図': true, '配置図': true };
    var CUSTOM_FIELD_COMPOSITE_RECEIVED_LABELS = { '設備図': true, '意匠図': true };
    function isCustomFieldCompositeBaseLabel(label) {
        return !!CUSTOM_FIELD_COMPOSITE_BASE_LABELS[String(label || '').trim()];
    }
    function isCustomFieldCompositeReceivedLabel(label) {
        if (window.ProjectCustomFieldComposite && typeof window.ProjectCustomFieldComposite.supportsReceived === 'function') {
            return window.ProjectCustomFieldComposite.supportsReceived(label);
        }
        return !!CUSTOM_FIELD_COMPOSITE_RECEIVED_LABELS[String(label || '').trim()];
    }
    function isCustomFieldCompositeCompanionLabel(label) {
        return /【(?:メモ|受領済み|〇|○)】$/.test(String(label || '').trim());
    }
    function getCustomFieldCompositeMemoLabel(base) {
        if (window.ProjectCustomFieldComposite && typeof window.ProjectCustomFieldComposite.memoLabel === 'function') {
            return window.ProjectCustomFieldComposite.memoLabel(base);
        }
        return String(base || '').trim() + '【メモ】';
    }
    function getCustomFieldCompositeReceivedLabel(base) {
        if (window.ProjectCustomFieldComposite && typeof window.ProjectCustomFieldComposite.receivedLabel === 'function') {
            return window.ProjectCustomFieldComposite.receivedLabel(base);
        }
        return String(base || '').trim() + '【受領済み】';
    }
    function isCustomFieldCompositeReceivedChecked(value) {
        if (window.ProjectCustomFieldComposite && typeof window.ProjectCustomFieldComposite.isReceivedChecked === 'function') {
            return window.ProjectCustomFieldComposite.isReceivedChecked(value);
        }
        var s = String(value == null ? '' : value).trim();
        return s === '1' || s === '受領済み' || s === 'true' || s === 'on';
    }
    function normalizeCustomFieldCompositeReceivedValue(value) {
        if (window.ProjectCustomFieldComposite && typeof window.ProjectCustomFieldComposite.normalizeReceivedValue === 'function') {
            return window.ProjectCustomFieldComposite.normalizeReceivedValue(value);
        }
        return isCustomFieldCompositeReceivedChecked(value) ? '1' : '';
    }
    function isCustomFieldCompositeValueSelected(value) {
        return String(value == null ? '' : value).trim() !== '';
    }
    function getCustomFieldCompositeOptions(field) {
        return String((field && field.options) != null ? field.options : '')
            .split(',')
            .map(function(s) { return s.trim(); })
            .filter(Boolean);
    }
    function isCustomFieldCompositeCheckboxType(type) {
        return type === 'checkbox' || type === 'checkbox_select_all';
    }
    /** Keep only values that exist in the field's current set options (drops legacy orphans). */
    function sanitizeCustomFieldCompositeMainValue(value, options, type) {
        if (window.ProjectCustomFieldComposite && typeof window.ProjectCustomFieldComposite.sanitizeMainValue === 'function') {
            return window.ProjectCustomFieldComposite.sanitizeMainValue(value, options, type);
        }
        var opts = getCustomFieldCompositeOptions({ options: options });
        if (!opts.length) return String(value == null ? '' : value).trim();
        var allowed = {};
        opts.forEach(function(o) { allowed[o] = true; });
        var parts = String(value == null ? '' : value).split(',').map(function(s) { return s.trim(); }).filter(Boolean);
        if (isCustomFieldCompositeCheckboxType(type)) {
            return parts.filter(function(v) { return !!allowed[v]; }).join(',');
        }
        var raw = String(value == null ? '' : value).trim();
        if (raw && allowed[raw]) return raw;
        for (var i = 0; i < parts.length; i++) {
            if (allowed[parts[i]]) return parts[i];
        }
        return '';
    }
    /** 意匠図 type/options = 設備図 (fallback GUIS,他社). */
    function alignCustomFieldIshozuWithSetsubi(fields) {
        if (window.ProjectCustomFieldComposite && typeof window.ProjectCustomFieldComposite.alignOptionsWithSetsubi === 'function') {
            return window.ProjectCustomFieldComposite.alignOptionsWithSetsubi(fields);
        }
        var list = Array.isArray(fields) ? fields.slice() : [];
        var source = null;
        for (var i = 0; i < list.length; i++) {
            if (list[i] && String(list[i].label || '').trim() === '設備図') {
                source = list[i];
                break;
            }
        }
        var srcOptions = source
            ? (Array.isArray(source.options) ? source.options.join(',') : String(source.options != null ? source.options : ''))
            : 'GUIS,他社';
        var srcType = source ? (source.type || 'radio') : 'radio';
        for (var j = 0; j < list.length; j++) {
            var f = list[j];
            if (!f || String(f.label || '').trim() !== '意匠図') continue;
            list[j] = Object.assign({}, f, { options: srcOptions, type: srcType });
        }
        return list;
    }

    // Cột danh sách project (trùng với COLUMN_DEFINITIONS trong project-list.js) + custom_fields bổ sung ở computed
    var NOTE_DISPLAY_COLUMNS = [
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
        { key: 'parent_type2', label: '住宅種類' },
        { key: 'parent_type3', label: '非住宅の主要用途' },
        { key: 'parent_features', label: '特徴' },
        { key: 'parent_application_review_agency', label: '申請提出先の審査機関' },
        { key: 'parent_bels_zeh_grade', label: 'BELSのZEH等級' },
        { key: 'start_date', label: '開始日' },
        { key: 'caily_nouki', label: 'CAILY納期' },
        { key: 'guis_nouki', label: 'GUIS納期' },
        { key: 'end_date', label: '終了日' },
        { key: 'other_dept_nouki', label: '他部署の納期' },
        { key: 'project_order_type', label: '受注形態' },
        { key: 'priority', label: '優先度' },
        { key: 'amount', label: '総額' },
        { key: 'customer_info', label: '顧客情報' },
        { key: 'parent_guis_receiver', label: 'GUIS 受付者' }
    ];

    /** Select value for free-text note label (その他) */
    var NOTE_LABEL_OTHER = '__other__';

    const SERVER_TASK_TIMEZONE = 'Asia/Tokyo';
    const VIETNAM_TASK_TIMEZONE = 'Asia/Ho_Chi_Minh';
    const PROJECT_DATETIME_MOMENT_FORMAT = 'YYYY/M/D HH:mm';
    const PROJECT_DATETIME_JA_DISPLAY_FORMAT = 'YYYY年M月D日 HH:mm';
    const PROJECT_DATETIME_JA_DATE_FORMAT = 'YYYY年M月D日';
    const PROJECT_DATETIME_VI_DATE_FORMAT = 'YYYY/M/D';
    // Always persist/API wall clock as dashed JST (never slash UI format — avoids +2h re-parse loops)
    const PROJECT_DATETIME_SERVER_FORMAT = 'YYYY-MM-DD HH:mm:ss';
    // Flatpickr: n/j = month/day unpadded — must match Moment YYYY/M/D (avoid 4/6 ↔ 04/06 flicker)
    const PROJECT_DATETIME_FLATPICKR_FORMAT = 'Y/n/j H:i';
    const PROJECT_DATETIME_FLATPICKR_JA_ALT_FORMAT = 'Y年n月j日 H:i';
    const PROJECT_DATETIME_FLATPICKR_MOMENT_FORMAT = 'YYYY/M/D HH:mm';
    const PROJECT_DATETIME_PARSE_FORMATS = [
        'YYYY-MM-DD HH:mm:ss',
        'YYYY-MM-DD HH:mm',
        'YYYY/M/D HH:mm',
        'YYYY/MM/DD HH:mm',
        'YYYY/M/D H:mm',
        'YYYY/MM/DD H:mm',
        'Y/M/D H:mm',
        'Y/n/j H:i',
        'Y/m/d H:i'
    ];

    const BUSINESS_DOCUMENT_LOG_ACTIONS = new Set([
        'amount_updated',
        'estimate_status_updated',
        'estimate_date_updated',
        'estimate_number_updated',
        'invoice_status_updated',
        'invoice_date_updated',
        'invoice_amount_updated',
        'invoice_number_updated',
        'payment_status_updated',
        'payment_date_updated',
        'payment_amount_updated',
        'receipt_number_updated',
        'payment_note_updated',
    ]);

    const BUSINESS_DOCUMENT_FIELDS = [
        'amount',
        'estimate_status',
        'estimate_date',
        'estimate_number',
        'estimate_application_amount',
        'estimate_application_date',
        'estimate_application_status',
        'estimate_design_amount',
        'estimate_design_date',
        'estimate_design_status',
        'invoice_status',
        'invoice_date',
        'invoice_amount',
        'invoice_number',
        'invoice_application_amount',
        'invoice_application_date',
        'invoice_application_status',
        'invoice_design_amount',
        'invoice_design_date',
        'invoice_design_status',
        'payment_status',
        'payment_date',
        'payment_amount',
        'receipt_number',
        'payment_note',
    ];

    const BUSINESS_DOCUMENT_DATE_FIELDS = [
        'estimate_date',
        'invoice_date',
        'estimate_application_date',
        'estimate_design_date',
        'invoice_application_date',
        'invoice_design_date',
    ];

    const BUSINESS_DOCUMENT_PICKER_IDS = {
        estimate_date: 'estimate_date_picker',
        invoice_date: 'invoice_date_picker',
        payment_date: 'payment_date_picker',
        estimate_application_date: 'estimate_application_date_picker',
        estimate_design_date: 'estimate_design_date_picker',
        invoice_application_date: 'invoice_application_date_picker',
        invoice_design_date: 'invoice_design_date_picker',
    };

    function normalizeProjectVersion(version) {
        const n = Number(version);
        return Number.isFinite(n) && n > 0 ? n : 1;
    }

    function appendProjectVersionToFormData(formData, projectOrVersion) {
        if (!formData) return;
        const version = typeof projectOrVersion === 'object'
            ? projectOrVersion?.version
            : projectOrVersion;
        formData.append('version', normalizeProjectVersion(version));
    }

    function appendPaymentVersionToFormData(formData, projectOrVersion) {
        if (!formData) return;
        const paymentVersion = typeof projectOrVersion === 'object'
            ? projectOrVersion?.payment_version
            : projectOrVersion;
        formData.append('payment_version', normalizeProjectVersion(paymentVersion));
    }

    function applyProjectVersionFromResponse(project, responseData) {
        if (project && responseData && responseData.version != null) {
            project.version = normalizeProjectVersion(responseData.version);
        }
    }

    function applyPaymentVersionFromResponse(project, responseData) {
        if (project && responseData && responseData.payment_version != null) {
            project.payment_version = normalizeProjectVersion(responseData.payment_version);
        }
    }

    function handleProjectVersionConflict(responseData, onReload) {
        if (!responseData || (responseData.error !== 'version_conflict' && responseData.error !== 'version_required')) {
            return false;
        }
        const msg = responseData.message || '他のユーザーが先に更新しました。ページを再読み込みしてください。';
        if (typeof hideHourglass === 'function') {
            hideHourglass();
        }
        if (typeof Swal !== 'undefined' && Swal.fire) {
            Swal.fire({
                title: 'Error!',
                text: msg,
                icon: 'error',
                customClass: {
                    confirmButton: 'btn btn-primary'
                },
                buttonsStyling: false
            }).then(() => {
                window.location.reload();
            });
        } else if (typeof showMessage === 'function') {
            showMessage(msg, true);
            window.location.reload();
        } else {
            alert(msg);
            window.location.reload();
        }
        return true;
    }


    function isProjectDetailVietnameseLocale() {
        // Prefer getAppLanguage (i18n + localStorage fallback) so VN TZ works
        // even when pickers init before i18next.isInitialized.
        if (typeof getAppLanguage === 'function') {
            return String(getAppLanguage() || '').toLowerCase().startsWith('vi');
        }
        if (typeof i18next !== 'undefined' && i18next.language) {
            return String(i18next.language || '').toLowerCase().startsWith('vi');
        }
        try {
            const tn = (typeof templateName !== 'undefined' && templateName)
                ? templateName
                : (window.templateName || '');
            const fromCustomizer = window.templateCustomizer
                && window.templateCustomizer.settings
                && window.templateCustomizer.settings.lang;
            const fromStorage = tn
                ? localStorage.getItem('templateCustomizer-' + tn + '--Lang')
                : '';
            return String(fromCustomizer || fromStorage || '').toLowerCase().startsWith('vi');
        } catch (e) {
            return false;
        }
    }

    function getProjectDetailDisplayTimezone() {
        return isProjectDetailVietnameseLocale() ? VIETNAM_TASK_TIMEZONE : SERVER_TASK_TIMEZONE;
    }

    function isProjectServerDateTimeFormat(value) {
        const s = String(value || '').trim();
        // DB / API wall clock (JST): dashes, optional seconds
        return /^\d{4}-\d{1,2}-\d{1,2}(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?$/.test(s);
    }

    /** Flatpickr / UI wall clock (may already be VN or JA display) — uses "/" or 年 */
    function isProjectDisplayDateTimeFormat(value) {
        const s = String(value || '').trim();
        if (!s) return false;
        if (s.indexOf('年') !== -1) return true;
        if (s.indexOf('/') !== -1) return true;
        return false;
    }

    function parseProjectDateMomentServer(value) {
        if (value === undefined || value === null) return null;
        const s = String(value).trim();
        if (!s || s === '-') return null;
        // Do not treat display strings (YYYY/M/D...) as Tokyo — that causes double VN/JA shifts
        if (isProjectDisplayDateTimeFormat(s) && !isProjectServerDateTimeFormat(s)) {
            return null;
        }
        const normalized = s.replace(/\//g, '-');
        if (typeof moment !== 'undefined') {
            const formats = ['YYYY-MM-DD HH:mm:ss', 'YYYY-MM-DD HH:mm', 'YYYY-M-D HH:mm', 'YYYY-MM-DD', 'YYYY-M-D'];
            const m = typeof moment.tz === 'function'
                ? moment.tz(normalized, formats, SERVER_TASK_TIMEZONE)
                : moment(normalized, formats, true);
            if (m.isValid()) return m;
        }
        const d = new Date(normalized);
        if (isNaN(d.getTime())) return null;
        return typeof moment !== 'undefined' ? moment(d) : null;
    }

    function parseProjectDateTimeInDisplayTz(value) {
        if (value === undefined || value === null) return null;
        const s = String(value).trim();
        if (!s || s === '-' || s === '0000-00-00 00:00:00' || s === '0000-00-00') return null;
        if (typeof moment === 'undefined') return null;
        const tz = getProjectDetailDisplayTimezone();
        if (moment.tz) {
            for (let i = 0; i < PROJECT_DATETIME_PARSE_FORMATS.length; i++) {
                const parsed = moment.tz(s, PROJECT_DATETIME_PARSE_FORMATS[i], tz);
                if (parsed.isValid()) return parsed;
            }
            const normalized = s.replace(/\//g, '-');
            const normalizedFormats = ['YYYY-MM-DD HH:mm:ss', 'YYYY-MM-DD HH:mm', 'YYYY-M-D HH:mm', 'YYYY-MM-DD', 'YYYY-M-D'];
            for (let j = 0; j < normalizedFormats.length; j++) {
                const parsedNorm = moment.tz(normalized, normalizedFormats[j], tz);
                if (parsedNorm.isValid()) return parsedNorm;
            }
            const loose = moment.tz(s, tz);
            return loose.isValid() ? loose : null;
        }
        const fallback = moment(s, PROJECT_DATETIME_PARSE_FORMATS, true);
        return fallback.isValid() ? fallback : null;
    }

    function toProjectDateTimeInputValue(date) {
        if (date === undefined || date === null) return '';
        const s = String(date).trim();
        if (!s || s === '-') return '';
        // Already UI wall-clock: reformat only (no timezone shift)
        if (isProjectDisplayDateTimeFormat(s) && !isProjectServerDateTimeFormat(s)) {
            const asDisplay = parseProjectDateTimeInDisplayTz(s);
            return asDisplay && asDisplay.isValid()
                ? asDisplay.format(PROJECT_DATETIME_FLATPICKR_MOMENT_FORMAT)
                : '';
        }
        const parsed = parseProjectDateMomentServer(date);
        if (!parsed || !parsed.isValid()) return '';
        const localized = moment.tz
            ? parsed.clone().tz(getProjectDetailDisplayTimezone())
            : parsed;
        return localized.format(PROJECT_DATETIME_FLATPICKR_MOMENT_FORMAT);
    }

    function fromProjectDateTimeInputValue(value) {
        const raw = String(value || '').trim();
        if (!raw) return '';
        // Already server/API format: normalize only (no timezone shift)
        if (isProjectServerDateTimeFormat(raw)) {
            const parsed = parseProjectDateMomentServer(raw);
            if (!parsed || !parsed.isValid()) return raw;
            return parsed.clone().tz(SERVER_TASK_TIMEZONE).format(PROJECT_DATETIME_SERVER_FORMAT);
        }
        const parsed = parseProjectDateTimeInDisplayTz(raw);
        if (!parsed) return raw;
        if (moment.tz) {
            return parsed.clone().tz(SERVER_TASK_TIMEZONE).format(PROJECT_DATETIME_SERVER_FORMAT);
        }
        return parsed.format(PROJECT_DATETIME_SERVER_FORMAT);
    }

    function isFilledProjectDeadline(value) {
        if (value === undefined || value === null) return false;
        const s = String(value).trim();
        return s !== '' && s !== '-' && s !== '0000-00-00' && s !== '0000-00-00 00:00:00';
    }

    function formatProjectDateTimeForDisplay(value) {
        if (value === undefined || value === null) return '-';
        const s = String(value).trim();
        if (!s || s === '-') return '-';
        const displayFmt = isProjectDetailVietnameseLocale()
            ? PROJECT_DATETIME_MOMENT_FORMAT
            : PROJECT_DATETIME_JA_DISPLAY_FORMAT;
        // Already UI wall-clock: format only (no timezone shift)
        if (isProjectDisplayDateTimeFormat(s) && !isProjectServerDateTimeFormat(s)) {
            const asDisplay = parseProjectDateTimeInDisplayTz(s);
            return asDisplay && asDisplay.isValid() ? asDisplay.format(displayFmt) : s;
        }
        const parsed = parseProjectDateMomentServer(value);
        if (!parsed) return '-';
        const localized = moment.tz
            ? parsed.clone().tz(getProjectDetailDisplayTimezone())
            : parsed;
        return localized.format(displayFmt);
    }

    /** Normalize UI datetime to one string (YYYY/M/D HH:mm) — stops 4/6 ↔ 04/06 flicker */
    function canonicalizeProjectDateTimeInputDisplay(value) {
        const raw = String(value || '').trim();
        if (!raw) return '';
        return toProjectDateTimeInputValue(raw) || raw;
    }

    function getProjectDetailFlatpickrLocale() {
        if (typeof window === 'undefined' || !window.flatpickr || !window.flatpickr.l10ns) {
            return 'default';
        }
        if (isProjectDetailVietnameseLocale()) {
            return window.flatpickr.l10ns.vi || 'default';
        }
        return window.flatpickr.l10ns.ja || 'default';
    }

    function makeProjectDetailTimeInputsEditable(selectedDates, dateStr, instance) {
        const cal = instance && instance.calendarContainer;
        if (!cal) return;
        cal.querySelectorAll('.flatpickr-time input, .flatpickr-time .numInputWrapper input').forEach((input) => {
            input.removeAttribute('readonly');
            input.readOnly = false;
        });
    }

    function getProjectDetailFlatpickrOptions(extra) {
        const options = {
            enableTime: true,
            time_24hr: true,
            dateFormat: PROJECT_DATETIME_FLATPICKR_FORMAT,
            allowInput: true,
            locale: getProjectDetailFlatpickrLocale(),
            onOpen: makeProjectDetailTimeInputsEditable
        };
        if (!isProjectDetailVietnameseLocale()) {
            options.altInput = true;
            options.altFormat = PROJECT_DATETIME_FLATPICKR_JA_ALT_FORMAT;
            options.altInputClass = 'form-control';
        }
        if (extra) {
            Object.assign(options, extra);
        }
        return options;
    }

    function initProjectDetailFlatpickr(el, extra, serverValue) {
        if (!el || typeof flatpickr === 'undefined') return null;
        if (el._flatpickr) el._flatpickr.destroy();
        const inputVal = toProjectDateTimeInputValue(serverValue);
        if (inputVal) el.value = inputVal;
        const fp = flatpickr(el, getProjectDetailFlatpickrOptions(extra || {}));
        if (inputVal) {
            // Wall-clock string only — avoid parsed.toDate() which shifts via UTC/browser TZ.
            fp.setDate(inputVal, false, PROJECT_DATETIME_FLATPICKR_FORMAT);
        }
        return fp;
    }

    function getProjectDateTimePlaceholder() {
        return isProjectDetailVietnameseLocale() ? 'YYYY/M/D HH:mm' : 'YYYY年M月D日 HH:mm';
    }

    function getCustomFieldDefaultHour() {
        return isProjectDetailVietnameseLocale() ? 17 : 19;
    }

    function getStartDateDefaultHour() {
        return isProjectDetailVietnameseLocale() ? 7 : 9;
    }

    function getDeadlineDefaultHour() {
        return isProjectDetailVietnameseLocale() ? 16 : 18;
    }

    const TASK_KINDS = [
        { value: '新規作成', label: '新規作成', color: 'success' },
        { value: '修正(エラー)', label: '修正(エラー)', color: 'danger' },
        { value: '修正(変更)', label: '修正(変更)', color: 'warning' },
        { value: 'チェック', label: 'チェック', color: 'primary' },
        { value: '連絡', label: '連絡', color: 'info' },
        { value: '検討', label: '検討', color: 'secondary' },
        { value: '相談・会議', label: '相談・会議', color: 'dark' }
    ];


    window.ProjectFormMixin = {
        data() {
            return {
                permission: {},
                projectId: typeof PROJECT_ID !== 'undefined' ? PROJECT_ID : (typeof this.getProjectIdFromUrl === 'function' ? this.getProjectIdFromUrl() : null),
                project: null,
                parentSiblingProjects: [],
                department: null,
                managers: [],
                members: [],
                tasks: [],
                team_list: [],
                isEditMode: false,
                originalProject: null,
                statuses: [
                    { value: 'draft', label: '受付', color: 'secondary' },
                    { value: 'open', label: '納期検討', color: 'info' },
                    { value: 'confirming', label: '仮受', color: 'info' },
                    { value: 'quotation', label: '見積', color: 'info' },
                    { value: 'contract', label: '請負', color: 'info' },
                    { value: 'waiting_documents', label: '資料待ち', color: 'warning' },
                    { value: 'in_progress', label: '進行中', color: 'primary' },
                    { value: 'calculation_completed', label: '計算終了', color: 'success', energyOnly: true },
                    { value: 'application_in_progress', label: '申請作業中', color: 'primary', energyOnly: true },
                    { value: 'waiting_invoice', label: '請求待ち', color: 'dark' },
                    { value: 'completed', label: '完了', color: 'success' },
                    { value: 'paused', label: '一時停止', color: 'warning' },
                    { value: 'cancelled', label: '中止', color: 'danger' }
                ],
                priorities: [
                    { value: 'low', label: '低', color: 'secondary' },
                    { value: 'medium', label: '中', color: 'primary' },
                    { value: 'high', label: '高', color: 'warning' },
                    { value: 'urgent', label: '緊急', color: 'danger' }
                ],
                companies: [],
                contacts: [],
                category_id: '',
                company_name: '',
                customer_id: '',
                newProject: {
                    members: '',
                    managers: '',
                    teams: '',
                },
                allTeams: [],
                departmentUsers: [],
                membersTagify: null,
                prevTeamIds: [],
                managerTagify: null,
                quillInstance: null,
                projectOrderTypeTagify: null,
                buildingBranchTagify: null,
                customFields: [],
                departmentCustomFieldSets: [],
                _serverProjectDates: null,
                japanPrefectures: [
                    '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
                    '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
                    '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
                    '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
                    '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
                    '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
                    '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'
                ],
                // Notes functionality,
                savingProject: false,
                // Debounce timer for amount updates,
                tagsUpdateTimer: null,
                projectTagsTagify: null,
                // Quill editor content storage (separate from Vue reactivity),
                quillContent: '',
                validationErrors: {
                    category_id: '',
                    company_name: '',
                    customer_id: '',
                    project_number: '',
                    name: '',
                    end_date: '',
                    caily_nouki: '',
                    guis_nouki: '',
                    yotei: ''
                },
                yoteiDraft: (typeof window.YoteiField !== 'undefined' && window.YoteiField.emptyModel)
                    ? window.YoteiField.emptyModel()
                    : { from_month: '', from_part: '', to_month: '', to_part: '' },
                yoteiPartOptionsTick: 0,
                savingCustomFieldLabel: null,
                // Set by the shared form dialog (project-form-modal.js); always off on the detail page.
                // formCreateMode: the form creates a project (project.id is null, 部署 is chosen in the form).
                // formManagerOnly: a 管理 without full edit rights — the .pf-full-only fields are hidden.
                formCreateMode: false,
                formManagerOnly: false,
                formDepartments: [],
                // GUIS受付者 of a child project. Off until a host calls loadGuisReceiverForForm():
                // the form then shows the field and sends guis_receiver ('' = same as the building).
                guisReceiverForm: { enabled: false, useParent: true, userid: '', parentUserid: '', parentName: '' },
            };
        },
        computed: {
            /** 申請提出先の審査機関 of this (省エネ) child project as a list. */
            energyReviewAgencyList() {
                return String(this.project && this.project.application_review_agency ? this.project.application_review_agency : '')
                    .split(',')
                    .map(function(v) { return v.trim(); })
                    .filter(Boolean);
            },
            selectedEnergyMaterialLabels() {
                const options = [
                    '意匠図',
                    '設備図',
                    '配置図',
                    '案内図',
                    '敷地求積図',
                    '確認申請書',
                    '設備仕様書',
                    'その他'
                ];
                const selected = String(this.project && this.project.energy_materials ? this.project.energy_materials : '')
                    .split(',')
                    .map(function(v) { return v.trim(); })
                    .filter(Boolean);
                return options.filter(function(opt) {
                    return selected.indexOf(opt) !== -1;
                });
            },
            belsZehGradeLabel() {
                if (!this.project || this.project.bels_zeh_grade == null || this.project.bels_zeh_grade === '') {
                    return '';
                }
                const code = String(this.project.bels_zeh_grade);
                const options = [
                    { value: '0', label: '0 : 該当なし（非ZEH）' },
                    { value: '1', label: '1 : ZEH' },
                    { value: '2', label: '2 : Nearly ZEH' },
                    { value: '3', label: '3 : ZEH Oriented' },
                    { value: '4', label: '4 : ZEH-M' },
                    { value: '5', label: '5 : Nearly ZEH-M' },
                    { value: '6', label: '6 : ZEH-M Oriented' },
                    { value: '7', label: '7 : ZEH Ready' },
                    { value: '8', label: '8 : DK-ZEH' },
                    { value: '9', label: '9 : 関西限定DK-ZEH' }
                ];
                const found = options.find(function(opt) { return opt.value === code; });
                return found ? found.label : code;
            },
            isCailyBranchUser() {
                return typeof window !== 'undefined' && window.IS_CAILY_BRANCH_USER === true;
            },
            yoteiDisplayText() {
                if (typeof window.YoteiField === 'undefined') return '-';
                const text = window.YoteiField.displayOf(this.project && this.project.yotei);
                return text || '-';
            },
            yoteiPreview() {
                if (typeof window.YoteiField === 'undefined') return '';
                return window.YoteiField.buildDisplay(this.yoteiDraft) || '';
            },
            yoteiPartOptions() {
                this.yoteiPartOptionsTick; // dependency for language refresh
                if (typeof window.YoteiField !== 'undefined' && window.YoteiField.getPartOptions) {
                    return window.YoteiField.getPartOptions();
                }
                return [
                    { value: '', label: '—' },
                    { value: 'early', label: '上旬' },
                    { value: 'mid', label: '中旬' },
                    { value: 'late', label: '下旬' }
                ];
            },
            /** Current project belongs to 省エネ計算 (or 省エネ計算2 etc.). */
            isEnergyDepartmentProject() {
                if (window.EnergyFeePayment && window.EnergyFeePayment.isEnergyProject) {
                    return window.EnergyFeePayment.isEnergyProject(this.project);
                }
                const name = String(this.project && this.project.department_name || '').trim();
                if (window.EnergyDrawingShare && window.EnergyDrawingShare.isEnergyDepartmentName) {
                    return window.EnergyDrawingShare.isEnergyDepartmentName(name);
                }
                return name.indexOf('省エネ') !== -1;
            },
            editableStatuses() {
                var list = this.statuses || [];
                if (!this.isEnergyDepartmentProject) {
                    var current = this.project && this.project.status ? String(this.project.status) : '';
                    list = list.filter(function(s) {
                        return !s.energyOnly || s.value === current;
                    });
                }
                if (!this.isCailyBranchUser || this.canViewEndDate) {
                    return list;
                }
                return list.filter(function(s) { return s.value !== 'completed'; });
            },
            filteredTeams() {
                if (!this.project || !this.project.department_id) return this.allTeams;
                return this.allTeams.filter(team => String(team.department_id) === String(this.project.department_id));
            },
            allDepartmentCustomFieldSets() {
                // Return all custom field sets for the department
                if (!this.project || !this.project.department_id) return [];
                return this.departmentCustomFieldSets || [];
            },
            isProjectCreator() {
                if (this.permission && this.permission.is_creator) return true;
                if (this.project && this.project.created_by && typeof USER_ID !== 'undefined' && USER_ID) {
                    return String(this.project.created_by) === String(USER_ID);
                }
                return false;
            },
            canEditProject() {
                if (this.isProjectCreator) return true;
                return this.permission.can_manage_project || (this.permission.is_member && this.permission.rule && this.permission.rule.project_edit == 1);
            },
            canUpdateProgress() {
                return this.permission.is_member;
            },
            /** Non-CAILY always; CAILY when project_view_end_date (view, and editable in project edit mode). */
            canViewEndDate() {
                if (!this.isCailyBranchUser) return true;
                if (this.isAdministrator()) return true;
                const rule = this.permission && this.permission.rule;
                return !!(rule && (rule.project_view_end_date == 1 || rule.project_view_end_date === '1'));
            },
        },
        watch: {
            isEditMode(newVal) {
                if (!newVal) {
                    this.destroyYoteiMonthPickers();
                }
                if (newVal) {
                    this.$nextTick(() => {
                        this.initYoteiMonthPickers();
                        const saved = this.parseSavedCustomFieldList(this.project.custom_fields);
                        const allFieldsFromSets = this.collectCustomFieldsFromSets(this.allDepartmentCustomFieldSets);
                        this.customFields = this.mapSetFieldsToCustomFields(allFieldsFromSets, saved);
                        if (this.customFields.length > 0) {
                            this.$nextTick(() => {
                                setTimeout(() => {
                                    this.initCustomFieldDatePickers();
                                }, 100);
                            });
                        }
                        // Initialize Select2 dropdowns
                        $('#category_id').select2({
                            placeholder: '選択してください',
                            dropdownParent: $('#category_id').parent(),
                            allowClear: true,
                            minimumResultsForSearch: 0,
                            ajax: {
                                url: '/api/index.php?model=customer&method=list_categories',
                                dataType: 'json',
                                delay: 250,
                                data: (params) => {
                                    const data = {
                                        search: params.term,
                                        page: params.page || 1
                                    };
                                    if (this.project.department_id) {
                                        data.department_id = this.project.department_id;
                                    }
                                    return data;
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
                        }).on('select2:select', (e) => {
                            this.project.category_id = e.params.data.id;
                            this.onCategoryChange();
                        }).on('select2:clear', () => {
                            this.project.category_id = '';
                            this.onCategoryChange();
                        });
                        
                        // Company name (company_name)
                        const $company = $('#company_name');
                        if ($company.length) {
                            $company.select2({
                                placeholder: '選択してください',
                                dropdownParent: $company.parent(),
                                allowClear: true,
                                minimumResultsForSearch: 0,
                                ajax: {
                                    url: '/api/index.php?model=customer&method=list_companies_by_category',
                                    dataType: 'json',
                                    delay: 250,
                                    data: (params) => {
                                        const data = {
                                            search: params.term,
                                            page: params.page || 1,
                                            category_id: this.project.category_id
                                        };
                                        if (this.project.department_id) {
                                            data.department_id = this.project.department_id;
                                        }
                                        return data;
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
                            }).on('select2:select', (e) => {
                                this.project.company_name = e.params.data.id;
                                this.onCompanyChange();
                            }).on('select2:clear', () => {
                                this.project.company_name = '';
                                this.onCompanyChange();
                            });
                        }
                        // 担当者名 (customer_id)
                        const $customer = $('#customer_id');
                        if ($customer.length) {
                            $customer.select2({
                                placeholder: '選択してください',
                                dropdownParent: $customer.parent(),
                                allowClear: true,
                                minimumResultsForSearch: 0,
                                ajax: {
                                    url: '/api/index.php?model=customer&method=list_contacts_by_company',
                                    dataType: 'json',
                                    delay: 250,
                                    data: (params) => {
                                        const data = {
                                            search: params.term,
                                            page: params.page || 1,
                                            company_name: this.project.company_name
                                        };
                                        if (this.project.department_id) {
                                            data.department_id = this.project.department_id;
                                        }
                                        return data;
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
                            }).on('select2:select', (e) => {
                                this.project.customer_id = e.params.data.id;
                            }).on('select2:clear', () => {
                                this.project.customer_id = '';
                            });
                        }
                        
                        // Initialize Tagify with delay to ensure DOM is ready
                        setTimeout(() => {
                            console.log('Initializing Tagify...');
                            
                            // Clear any existing tagify instances first
                            document.querySelectorAll('.tagify').forEach(el => {
                                if (el._tagify) {
                                    try {
                                        el._tagify.destroy();
                                    } catch (e) {
                                        console.log('Error destroying existing tagify:', e);
                                    }
                                }
                            });
                            
                            // --- Tagify for project_order_type ---
                            const input = document.querySelector('#project_order_type');
                            if (input && window.Tagify && !input._tagify) {
                                if (this.projectOrderTypeTagify) {
                                    try {
                                        this.projectOrderTypeTagify.destroy();
                                    } catch (e) {
                                        console.log('Error destroying existing projectOrderTypeTagify:', e);
                                    }
                                }
                                this.projectOrderTypeTagify = new Tagify(input, {
                                    whitelist: this.getProjectOrderTypeWhitelist(),
                                    maxTags: 5,
                                    dropdown: {
                                        maxItems: 20,
                                        classname: "tags-look-project-order-type",
                                        enabled: 0,
                                        closeOnSelect: true
                                    },
                                });
                                // Set default value
                                let tags = [];
                                if (typeof this.project.project_order_type === 'string' && this.project.project_order_type) {
                                    tags = this.project.project_order_type.split(',').map(s => s.trim()).filter(Boolean);
                                }
                                // if (tags.length > 0) {
                                //     this.projectOrderTypeTagify.addTags(tags);
                                // }
                                const updateOrderType = () => {
                                    this.project.project_order_type = this.projectOrderTypeTagify.value.map(tag => tag.value).join(',');
                                };
                                this.projectOrderTypeTagify.on('add', updateOrderType);
                                this.projectOrderTypeTagify.on('remove', updateOrderType);
                            } else if (!input) {
                                // Nếu element chưa tồn tại, thử lại sau 100ms
                                setTimeout(() => {
                                    this.initTagify();
                                }, 100);
                            }
                            
                            // --- Tagify for building_branch ---
                            const buildingBranchInput = document.querySelector('#building_branch');
                            if (buildingBranchInput && window.Tagify && !buildingBranchInput._tagify) {
                                if (this.buildingBranchTagify) {
                                    try {
                                        this.buildingBranchTagify.destroy();
                                    } catch (e) {
                                        console.log('Error destroying existing buildingBranchTagify:', e);
                                    }
                                }
                                this.buildingBranchTagify = new Tagify(buildingBranchInput, {
                                    whitelist: this.japanPrefectures,
                                    maxTags: 10,
                                    dropdown: {
                                        maxItems: 20,
                                        classname: "tags-look-building-branch",
                                        enabled: 0,
                                        closeOnSelect: true
                                    },
                                });
                                // Set default value
                                let buildingBranchTags = [];
                                if (typeof this.project.building_branch === 'string' && this.project.building_branch) {
                                    buildingBranchTags = this.project.building_branch.split(',').map(s => s.trim()).filter(Boolean);
                                }
                                // if (buildingBranchTags.length > 0) {
                                //     this.buildingBranchTagify.addTags(buildingBranchTags);
                                // }
                                const updateBuildingBranch = () => {
                                    this.project.building_branch = this.buildingBranchTagify.value.map(tag => tag.value).join(',');
                                };
                                this.buildingBranchTagify.on('add', updateBuildingBranch);
                                this.buildingBranchTagify.on('remove', updateBuildingBranch);
                            } else {
                            }
                            
                            // Initialize Quill editor after all other components are ready
                            setTimeout(() => {
                                this.initQuillEditor();
                            }, 100);
                            
                        }, 100);
                    });
                    window.addEventListener('beforeunload', this.handleBeforeUnload);
                } else {
                    this.destroyQuillEditor();
                    $('#category_id').select2('destroy');
                    $('#company_name').select2('destroy');
                    $('#customer_id').select2('destroy');
                    
                    // Destroy Tagify for project_order_type
                    if (this.projectOrderTypeTagify) {
                        try {
                            this.projectOrderTypeTagify.destroy();
                        } catch (e) {
                            console.log('Error destroying projectOrderTypeTagify:', e);
                        }
                        this.projectOrderTypeTagify = null;
                    }
                    
                    // Destroy Tagify for building_branch
                    if (this.buildingBranchTagify) {
                        try {
                            this.buildingBranchTagify.destroy();
                        } catch (e) {
                            console.log('Error destroying buildingBranchTagify:', e);
                        }
                        this.buildingBranchTagify = null;
                    }
                    
                    window.removeEventListener('beforeunload', this.handleBeforeUnload);
                }
            },
        },
        methods: {
            // 受注形態 suggestions: a 省エネ department only offers 新規 / 修正
            getProjectOrderTypeWhitelist() {
                const name = String((this.project && this.project.department_name) || '');
                if (name.indexOf('省エネ') !== -1) {
                    return ['新規', '修正'];
                }
                return ['新規', '修正', '新規修正', '変更', '免震', '耐震', '計画変更', '契約図', '実施図'];
            },
            isAdministrator() {
                return typeof USER_ROLE !== 'undefined' && USER_ROLE === 'administrator';
            },
            async loadPermission() {
                try {
                    const response = await axios.get('/api/index.php?model=task&method=getPermission&project_id=' + this.projectId);
                    this.permission = response.data || [];
                } catch (error) {
                    console.error('Error loading permission:', error);
                }
            },
            // Phương thức để dịch label động
            translateLabel(label) {
                if (typeof i18next !== 'undefined' && i18next.isInitialized) {
                    return i18next.t(label) || label;
                }
                return label;
            },
            async loadTeamListByIds(teamIdsStr) {
                try {
                    const ids = teamIdsStr.split(',').map(id => id.trim()).filter(Boolean);
                    if (ids.length === 0) {
                        this.project.team_list = [];
                        return;
                    }
                    const res = await axios.get(`/api/index.php?model=team&method=listbyids&ids=${ids.join(',')}`);
                    if (res.data && Array.isArray(res.data)) {
                        this.project.team_list = res.data;
                    } else {
                        this.project.team_list = [];
                    }
                } catch (e) {
                    this.project.team_list = [];
                }
            },
            async loadMembers() {
                try {
                    const response = await axios.get(`/api/index.php?model=project&method=getMembers&project_id=${this.projectId}`);
                    this.members = response.data || [];
                    this.managers = this.members.filter(m => m && m.role === 'manager');
                    const managerIds = this.managers.map(m => m.user_id);
                    this.members = this.members.filter(m => m && m.role === 'member' && !managerIds.includes(m.user_id));
                } catch (error) {
                    console.error('Error loading members:', error);
                }
            },
            /** Hide overdue/remaining badges: 完了・中止・請求待ち・削除・下書き・一時停止・納品済み */
            shouldSkipDeadlineBadge(status, isDelivered) {
                if (isDelivered) return true;
                const s = String(status || '').trim().toLowerCase();
                return s === 'completed'
                    || s === 'cancelled'
                    || s === 'waiting_invoice'
                    || s === 'deleted'
                    || s === 'draft'
                    || s === 'paused';
            },
            getTimeRemaining() {
                if (!this.project || !this.project.end_date
                    || this.shouldSkipDeadlineBadge(this.project.status, this.isProjectNoukiDelivered())) {
                    return null;
                }
                
                const now = moment.tz('Asia/Tokyo');
                const endDate = moment.tz(this.project.end_date, 'Asia/Tokyo');
                
                // Kiểm tra ngôn ngữ hiện tại
                const isVietnamese = typeof i18next !== 'undefined' && i18next.isInitialized && i18next.language === 'vi';
                
                // Lấy các nhãn đã dịch
                const dayLabel = this.translateLabel('日');
                const hourLabel = this.translateLabel('時間');
                const minuteLabel = this.translateLabel('分');
                const overdueLabel = this.translateLabel('超過');
                const remainingLabel = this.translateLabel('残り');
                
                // Hàm helper để format số và đơn vị với khoảng cách cho tiếng Việt
                const formatUnit = (value, label) => {
                    if (isVietnamese) {
                        return `${value} ${label}`;
                    } else {
                        return `${value}${label}`;
                    }
                };
                
                // Hàm helper để format text với khoảng cách cho tiếng Việt
                const formatTimeText = (parts) => {
                    if (isVietnamese) {
                        return parts.filter(p => p).join(' ');
                    } else {
                        return parts.filter(p => p).join('');
                    }
                };
                
                if (endDate.isBefore(now)) {
                    // Đã quá hạn (loại trừ T7–CN)
                    const parts = (typeof getBusinessDurationParts === 'function')
                        ? getBusinessDurationParts(endDate, now)
                        : null;
                    const days = parts ? parts.days : Math.floor(now.diff(endDate) / (1000 * 60 * 60 * 24));
                    const hours = parts ? parts.hours : Math.floor((now.diff(endDate) % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = parts ? parts.minutes : Math.floor((now.diff(endDate) % (1000 * 60 * 60)) / (1000 * 60));
                    
                    if (days > 0) {
                        return {
                            text: formatTimeText([formatUnit(days, dayLabel), formatUnit(hours, hourLabel), formatUnit(minutes, minuteLabel), overdueLabel]),
                            class: 'bg-danger',
                            isOverdue: true
                        };
                    } else if (hours > 0) {
                        return {
                            text: formatTimeText([formatUnit(hours, hourLabel), formatUnit(minutes, minuteLabel), overdueLabel]),
                            class: 'bg-danger',
                            isOverdue: true
                        };
                    } else {
                        return {
                            text: formatTimeText([formatUnit(minutes, minuteLabel), overdueLabel]),
                            class: 'bg-danger',
                            isOverdue: true
                        };
                    }
                } else {
                    // Còn thời gian (loại trừ T7–CN)
                    const parts = (typeof getBusinessDurationParts === 'function')
                        ? getBusinessDurationParts(now, endDate)
                        : null;
                    const days = parts ? parts.days : Math.floor(endDate.diff(now) / (1000 * 60 * 60 * 24));
                    const hours = parts ? parts.hours : Math.floor((endDate.diff(now) % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = parts ? parts.minutes : Math.floor((endDate.diff(now) % (1000 * 60 * 60)) / (1000 * 60));
                    
                    if (days > 0) {
                        return {
                            text: formatTimeText([remainingLabel, formatUnit(days, dayLabel), formatUnit(hours, hourLabel), formatUnit(minutes, minuteLabel)]),
                            class: 'bg-label-info',
                            isOverdue: false
                        };
                    } else if (hours > 0) {
                        return {
                            text: formatTimeText([remainingLabel, formatUnit(hours, hourLabel), formatUnit(minutes, minuteLabel)]),
                            class: hours <= 24 ? 'bg-label-warning' : 'bg-label-info',
                            isOverdue: false
                        };
                    } else {
                        return {
                            text: formatTimeText([remainingLabel, formatUnit(minutes, minuteLabel)]),
                            class: 'bg-label-warning',
                            isOverdue: false
                        };
                    }
                }
            },
            /**
             * 期限超過 or 進捗遅れ for CAILY/GUIS納期 (only one).
             * @param {'caily'|'guis'} kind
             */
            getNoukiScheduleJudgment(kind) {
                if (!this.project || typeof getScheduleJudgment !== 'function') return null;
                const isCaily = kind === 'caily';
                const deadline = isCaily ? this.project.caily_nouki : this.project.guis_nouki;
                const status = isCaily ? this.project.caily_nouki_status : this.project.guis_nouki_status;
                const isDelivered = !!(status && String(status).indexOf('納品済み') !== -1);
                return getScheduleJudgment(this.project, deadline, { isDelivered: isDelivered });
            },
            /**
             * Judgment badge (期限超過 / 進捗遅れ) to show next to a 納期's remaining-time badge.
             * When the remaining-time badge already says the deadline has passed ("5日…超過"),
             * a second badge reading 期限超過 only repeats it, so it is dropped.
             */
            getNoukiJudgmentBadge(kind) {
                const judgment = this.getNoukiScheduleJudgment(kind);
                if (!judgment) return null;
                const remaining = kind === 'caily' ? this.cailyNoukiTimeRemaining() : this.guisNoukiTimeRemaining();
                if (judgment.key === 'overdue' && remaining && remaining.isOverdue) return null;
                return judgment;
            },
            cailyNoukiTimeRemaining() {
                if (!this.project) return null;
                return this.getTimeRemainingForDate(
                    this.project.caily_nouki,
                    this.isNoukiDelivered(this.project.caily_nouki_status)
                );
            },
            guisNoukiTimeRemaining() {
                if (!this.project) return null;
                return this.getTimeRemainingForDate(
                    this.project.guis_nouki,
                    this.isNoukiDelivered(this.project.guis_nouki_status)
                );
            },
            /** Remaining time for a given date (e.g. caily_nouki, guis_nouki). Null when inactive status or delivered. */
            getTimeRemainingForDate(dateStr, isDelivered) {
                if (!this.project || !dateStr) return null;
                if (this.shouldSkipDeadlineBadge(this.project.status, !!isDelivered)) return null;
                const now = moment.tz('Asia/Tokyo');
                const endDate = moment.tz(dateStr, 'Asia/Tokyo');
                if (!endDate.isValid()) return null;
                const isVietnamese = typeof i18next !== 'undefined' && i18next.isInitialized && i18next.language === 'vi';
                const dayLabel = this.translateLabel('日');
                const hourLabel = this.translateLabel('時間');
                const minuteLabel = this.translateLabel('分');
                const overdueLabel = this.translateLabel('超過');
                const remainingLabel = this.translateLabel('残り');
                const formatUnit = (value, label) => isVietnamese ? `${value} ${label}` : `${value}${label}`;
                const formatTimeText = (parts) => isVietnamese ? parts.filter(p => p).join(' ') : parts.filter(p => p).join('');
                const parts = (typeof getBusinessDurationParts === 'function')
                    ? getBusinessDurationParts(now, endDate)
                    : null;
                const overdue = endDate.isBefore(now);
                const days = parts ? parts.days : Math.floor(Math.abs(endDate.diff(now)) / (1000 * 60 * 60 * 24));
                const hours = parts ? parts.hours : Math.floor((Math.abs(endDate.diff(now)) % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = parts ? parts.minutes : Math.floor((Math.abs(endDate.diff(now)) % (1000 * 60 * 60)) / (1000 * 60));
                if (overdue) {
                    if (days > 0) {
                        return { text: formatTimeText([formatUnit(days, dayLabel), formatUnit(hours, hourLabel), formatUnit(minutes, minuteLabel), overdueLabel]), class: 'bg-danger', isOverdue: true };
                    } else if (hours > 0) {
                        return { text: formatTimeText([formatUnit(hours, hourLabel), formatUnit(minutes, minuteLabel), overdueLabel]), class: 'bg-danger', isOverdue: true };
                    } else {
                        return { text: formatTimeText([formatUnit(minutes, minuteLabel), overdueLabel]), class: 'bg-danger', isOverdue: true };
                    }
                } else {
                    if (days > 0) {
                        return { text: formatTimeText([remainingLabel, formatUnit(days, dayLabel), formatUnit(hours, hourLabel), formatUnit(minutes, minuteLabel)]), class: 'bg-label-info', isOverdue: false };
                    } else if (hours > 0) {
                        return { text: formatTimeText([remainingLabel, formatUnit(hours, hourLabel), formatUnit(minutes, minuteLabel)]), class: hours <= 24 ? 'bg-label-warning' : 'bg-label-info', isOverdue: false };
                    } else {
                        return { text: formatTimeText([remainingLabel, formatUnit(minutes, minuteLabel)]), class: 'bg-label-warning', isOverdue: false };
                    }
                }
            },
            formatDateTime(datetime) {
                return formatProjectDateTimeForDisplay(datetime);
            },
            getProjectDateTimePlaceholder() {
                return getProjectDateTimePlaceholder();
            },
            /**
             * Text of the "VN hh:ii" tooltip shown on JST dates. The tooltip was removed from the
             * project detail page on request: with no text, initVietnamTimeTooltips() creates
             * none. (Other pages still use window.formatVietnamTimeTooltip from main.js.)
             */
            getVietnamTimeTooltip(jpDateTimeStr) {
                return '';
            },
            formatCurrency(amount) {
                if (!amount) return '¥0';
                return '¥' + parseInt(amount).toLocaleString();
            },
            getStatusLabel(status) {
                const s = this.statuses.find(s => s.value === status);
                return s ? this.translateLabel(s.label) : status;
            },
            getStatusBadgeClass(status) {
                const s = this.statuses.find(s => s.value === status);
                return `bg-${s?.color || 'secondary'}`;
            },
            getOrderTypeBadgeClass(orderType) {
                const type = String(orderType || '').trim();
                switch (type) {
                    case '修正':
                        return 'bg-warning';
                    case '新規':
                        return 'bg-primary';
                    case '新規修正':
                        return 'bg-success';
                    case '変更':
                        return 'bg-danger';
                    default:
                        return 'bg-info';
                }
            },
            isNoukiDelivered(status) {
                return String(status || '').indexOf('納品済み') !== -1;
            },
            isProjectNoukiDelivered() {
                if (!this.project) return false;
                return this.isRowNoukiDelivered(this.project);
            },
            isRowNoukiDelivered(row) {
                if (!row) return false;
                if (this.isCailyBranchUser) {
                    return this.isNoukiDelivered(row.caily_nouki_status);
                }
                return this.isNoukiDelivered(row.caily_nouki_status)
                    || this.isNoukiDelivered(row.guis_nouki_status);
            },
            getPriorityLabel(priority) {
                const p = this.priorities.find(p => p.value === priority);
                return p ? p.label : priority;
            },
            getPriorityBadgeClass(priority) {
                const p = this.priorities.find(p => p.value === priority);
                return `bg-${p?.color || 'secondary'}`;
            },
            async updateStatus(shareAnswer) {
                // Close dropdown
                const dropdownElement = document.querySelector('#statusDropdown');
                if (dropdownElement) {
                    const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                    if (dropdown) {
                        dropdown.hide();
                    }
                }
                if (this.isEditMode) {
                    return;
                }
                try {
                    const formData = new FormData();
                    formData.append('id', this.projectId);
                    formData.append('status', this.project.status);
                    formData.append('name', this.project.name);
                    formData.append('project_number', this.project.project_number);
                    if (window.EnergyDrawingShare) {
                        window.EnergyDrawingShare.appendEnergyDrawingShareToFormData(formData, shareAnswer);
                    }
                    const response = await axios.post('/api/index.php?model=project&method=updateStatus', formData);
                    if (this.isQuickEditSaved(response.data)) {
                        if (window.EnergyDrawingShare && shareAnswer) {
                            window.EnergyDrawingShare.applyEnergyDrawingShareToProject(this.project, shareAnswer);
                        }
                        showMessage('ステータスの更新に完了しました。');
                        // The server also changes progress / actual start date with the status
                        this.loadProject({ silent: true });
                    } else {
                        showMessage('ステータスの更新に失敗しました。', true);
                        this.restoreProjectAfterFailedQuickEdit();
                    }
                } catch (error) {
                    console.error('Error updating status:', error);
                    showMessage('ステータスの更新に失敗しました。', true);
                    this.restoreProjectAfterFailedQuickEdit();
                }
            },
            /**
             * Result of updateStatus / updatePriority / updateProgress. The API answers with
             * {status: 'success'|'error'} or with the number of updated rows (sent as a string,
             * so "0" must not count as success), or an empty value when nothing was saved.
             */
            isQuickEditSaved(data) {
                if (data && typeof data === 'object') {
                    if (data.status === 'error' || data.success === false || data.error) return false;
                    return data.status === 'success' || data.success === true;
                }
                return Number(data) > 0;
            },
            /** The value was applied on screen before saving; put the stored one back. */
            restoreProjectAfterFailedQuickEdit() {
                if (!this.isEditMode) {
                    this.loadProject({ silent: true });
                }
            },
            async selectStatus(status) {
                if (this.isCailyBranchUser && status === 'completed' && !this.canViewEndDate) {
                    return;
                }
                const prev = this.project.status;
                if (status === 'completed' && prev !== 'completed') {
                    const ok = await this.confirmPaymentInfoBeforeComplete(this.project);
                    if (!ok) return;
                }
                let shareAnswer = null;
                const needsShare = status === 'completed' && prev !== 'completed';
                if (needsShare && window.EnergyDrawingShare) {
                    const answer = await window.EnergyDrawingShare.ensureEnergyDrawingShareAnswer(this.project, {
                        departmentName: this.project.department_name,
                        siblings: this.parentSiblingProjects,
                        hasEnergySibling: this.project.has_energy_sibling
                    });
                    if (answer === false) return;
                    shareAnswer = answer;
                }
                this.project.status = status;
                this.updateStatus(shareAnswer);
                // Close dropdown
                const dropdownElement = document.querySelector('#statusDropdown');
                if (dropdownElement) {
                    const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                    if (dropdown) {
                        dropdown.hide();
                    }
                }
            },
            getStatusButtonClass(status) {
                const s = this.statuses.find(s => s.value === status);
                return `btn-${s?.color || 'secondary'}`;
            },
            getPriorityButtonClass(priority) {
                const p = this.priorities.find(p => p.value === priority);
                return `btn-${p?.color || 'secondary'}`;
            },
            selectPriority(priority) {
                this.project.priority = priority;
                // Close dropdown
                const dropdownElement = document.querySelector('#priorityDropdown');
                if (dropdownElement) {
                    const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                    if (dropdown) {
                        dropdown.hide();
                    }
                }
                if (this.isEditMode) {
                    return;
                }
                this.updatePriority();
            },
            async updatePriority() {
                try {
                    const formData = new FormData();
                    formData.append('id', this.projectId);
                    formData.append('priority', this.project.priority);
                    const response = await axios.post('/api/index.php?model=project&method=updatePriority', formData);
                    if (this.isQuickEditSaved(response.data)) {
                        showMessage('優先度を更新しました。');
                    } else {
                        showMessage('優先度の更新に失敗しました。', true);
                        this.restoreProjectAfterFailedQuickEdit();
                    }
                } catch (error) {
                    console.error('Error updating priority:', error);
                    showMessage('優先度の更新に失敗しました。', true);
                    this.restoreProjectAfterFailedQuickEdit();
                }
            },
            getAvatarSrc(member) {
                if (!member) return '';
                var img = member.user_image != null ? String(member.user_image).trim() : '';
                if (!img || img === 'null' || img === 'undefined' || img === 'no-image.png' || img === '1.png' || img === 'default.png') {
                    return '';
                }
                if (img.indexOf('http') === 0 || img.indexOf('/') === 0) {
                    return img;
                }
                return '/assets/upload/avatar/' + img;
            },
            handleAvatarError(member) {
                if (!member) return;
                member.avatarError = true;
                member.avatarLoaded = false;
            },
            handleAvatarLoad(member) {
                if (!member) return;
                member.avatarLoaded = true;
            },
            showAvatarInitials(member) {
                return !member || member.avatarError || !this.getAvatarSrc(member) || !member.avatarLoaded;
            },
            getInitials(nameOrUser, userid, ruby) {
                if (typeof getAvatarName === 'function') {
                    if (nameOrUser && typeof nameOrUser === 'object') {
                        return getAvatarName(nameOrUser);
                    }
                    return getAvatarName(nameOrUser || '', {
                        userid: userid || '',
                        user_ruby: ruby || ''
                    });
                }
                return nameOrUser ? String(nameOrUser).charAt(0) : '?';
            },
            onCategoryChange() {
                this.project.company_name = '';
                this.project.customer_id = '';
                this.loadCompaniesByCategory();
                this.contacts = [];
            },
            onCompanyChange() {
                this.project.customer_id = '';
                this.loadContactsByCompany();
            },
            async updateProgress() {
                try {
                    const formData = new FormData();
                    formData.append('id', this.projectId);
                    formData.append('progress', this.project.progress);
                    const response = await axios.post('/api/index.php?model=project&method=updateProgress', formData);
                    if (this.isQuickEditSaved(response.data)) {
                        showMessage('進捗率を更新しました。');
                    } else {
                        showMessage('進捗率の更新に失敗しました。', true);
                        this.restoreProjectAfterFailedQuickEdit();
                    }
                } catch (error) {
                    console.error('Error updating progress:', error);
                    showMessage('進捗率の更新に失敗しました。', true);
                    this.restoreProjectAfterFailedQuickEdit();
                }
            },
            /** 完了にできる決済状態: 発行済 / 無償 / 対象外(省エネ) */
            isBusinessDocumentReadyForCompletion(status) {
                if (window.EnergyFeePayment && window.EnergyFeePayment.isReadyForCompletion) {
                    return window.EnergyFeePayment.isReadyForCompletion(status);
                }
                const normalized = this.normalizeBusinessDocumentStatusValue(status);
                return normalized === '発行済' || normalized === '無償';
            },
            needsPaymentInfoBeforeComplete(project) {
                const p = project || this.project;
                if (!p) return true;
                if (window.EnergyFeePayment && window.EnergyFeePayment.needsPaymentInfoBeforeComplete) {
                    return window.EnergyFeePayment.needsPaymentInfoBeforeComplete(p);
                }
                return !this.isBusinessDocumentReadyForCompletion(p.estimate_status)
                    || !this.isBusinessDocumentReadyForCompletion(p.invoice_status);
            },
            getPaymentInfoBeforeCompleteMessage(project) {
                const p = project || this.project;
                const missing = (window.EnergyFeePayment && window.EnergyFeePayment.getMissingPaymentLabels)
                    ? window.EnergyFeePayment.getMissingPaymentLabels(p, (k) => this.translateLabel(k))
                    : [];
                if (!missing.length) {
                    if (!this.isBusinessDocumentReadyForCompletion(p && p.estimate_status)) {
                        missing.push(this.translateLabel('見積状況'));
                    }
                    if (!this.isBusinessDocumentReadyForCompletion(p && p.invoice_status)) {
                        missing.push(this.translateLabel('請求状況'));
                    }
                }
                const base = this.isEnergyDepartmentProject
                    ? this.translateLabel('完了にする前に見積・請求の決済情報を設定してください。（発行済・無償・対象外）')
                    : this.translateLabel('完了にする前に見積・請求の決済情報を設定してください。（発行済または無償）');
                return missing.length ? (base + '\n（' + missing.join(' / ') + '）') : base;
            },
            async confirmPaymentInfoBeforeComplete(project) {
                if (!this.needsPaymentInfoBeforeComplete(project)) return true;
                if (typeof Swal === 'undefined') {
                    alert(this.getPaymentInfoBeforeCompleteMessage(project));
                    return false;
                }
                await Swal.fire({
                    icon: 'warning',
                    title: this.translateLabel('決済情報が未設定です'),
                    text: this.getPaymentInfoBeforeCompleteMessage(project),
                    confirmButtonText: this.translateLabel('OK')
                });
                return false;
            },
            normalizeBusinessDocumentStatusValue(status) {
                if (status === '発行済み') return '発行済';
                return status;
            },
            updateTags() {
                // Get tags from Tagify instance
                if (this.projectTagsTagify) {
                    const tags = this.projectTagsTagify.value.map(tag => tag.value).join(',');
                    this.project.tags = tags;
                }
                
                // Debounce the tags update to avoid too many API calls
                clearTimeout(this.tagsUpdateTimer);
                this.tagsUpdateTimer = setTimeout(() => {
                    this.saveProjectTags();
                }, 1000); // Wait 1 second after user stops typing
            },
            async saveProjectTags() {
                try {
                    const formData = new FormData();
                    formData.append('id', this.projectId);
                    formData.append('tags', this.project.tags || '');
                    await axios.post('/api/index.php?model=project&method=updatePojectTags', formData);
                } catch (error) {
                    console.error('Error saving project tags:', error);
                }
            },
            clearTagifyTags(field) {
                if (field === 'project_tags') {
                    if (this.projectTagsTagify) {
                        this.projectTagsTagify.removeAllTags();
                    }
                } else if (field === 'team') {
                    if (this.tagify) {
                        this.tagify.removeAllTags();
                    }
                } else if (field === 'manager') {
                    if (this.managerTagify) {
                        this.managerTagify.removeAllTags();
                    }
                } else if (field === 'members') {
                    if (this.membersTagify) {
                        this.membersTagify.removeAllTags();
                    }
                } else if (field === 'building_branch') {
                    if (this.buildingBranchTagify) {
                        this.buildingBranchTagify.removeAllTags();
                    }
                } else if (field === 'project_order_type') {
                    if (this.projectOrderTypeTagify) {
                        this.projectOrderTypeTagify.removeAllTags();
                    }
                }
            },
            async loadAllTeams() {
                try {
                    const res = await axios.get('/api/index.php?model=team&method=list');
                    if (res.data && Array.isArray(res.data)) {
                        this.allTeams = res.data;
                    } else {
                        this.allTeams = [];
                    }
                } catch (e) {
                    this.allTeams = [];
                }
            },
            //     this.tagify.on('change', this.onTeamTagsChange.bind(this));
            // },
            initTagify() {
                this.$nextTick(() => {
                    setTimeout(async () => {
                        // Kiểm tra Tagify library đã được load chưa
                        if (!window.Tagify) {
                            console.log('Tagify library not loaded yet, retrying...');
                            setTimeout(() => {
                                this.initTagify();
                            }, 100);
                            return;
                        }
                        
                        // --- Tagify for team selection ---
                        const teamInput = document.getElementById('team_tags');
                        if (teamInput && window.Tagify && !teamInput._tagify) {
                            // allTeams đã được load sẵn trong toggleEditMode
                            if (this.tagify) {
                                try {
                                    this.tagify.destroy();
                                } catch (e) {
                                    console.log('Error destroying existing tagify:', e);
                                }
                            }
                            // Gán giá trị team đã chọn
                            const tags = (this.project.team_list || []).map(t => ({ value: t.name, id: t.id }));
                            // Danh sách tất cả team cho whitelist
                            const whitelist = this.filteredTeams.map(t => ({ value: t.name, id: t.id }));
                            this.tagify = new Tagify(teamInput, {
                                whitelist: whitelist,
                                enforceWhitelist: false,
                                dropdown: {
                                    maxItems: 1000,
                                    enabled: 0,
                                    closeOnSelect: true
                                },
                            });
                            this.tagify.addTags(tags);
                            this.tagify.on('remove', async (e) => {
                                const removedTeamId = e.detail.data.id;
                                if (!removedTeamId || !this.membersTagify) return;
                                // Lấy danh sách user của team vừa bị xóa
                                try {
                                    const res = await axios.get(`/api/index.php?model=team&method=get&id=${removedTeamId}`);
                                    if (res.data && Array.isArray(res.data.members)) {
                                        const teamMemberIds = res.data.members.map(m => String(m.user_id));
                                        // Xóa các member này khỏi membersTagify
                                        const remain = this.membersTagify.value.filter(tag => !teamMemberIds.includes(String(tag.id)));
                                        this.membersTagify.removeAllTags();
                                        this.membersTagify.addTags(remain);
                                    }
                                } catch (err) {}
                            });
                            // Xử lý khi thêm team thì thêm member của team đó và team leader vào manager
                            this.tagify.on('add', async (e) => {
                                const addedTeamId = e.detail.data.id;
                                if (!addedTeamId) return;
                                try {
                                    const res = await axios.get(`/api/index.php?model=team&method=get&id=${addedTeamId}`);
                                    if (res.data && Array.isArray(res.data.members)) {
                                        if (this.membersTagify) {
                                            const teamMembers = res.data.members.map(m => ({ id: m.user_id, value: m.user_name }));
                                            const currentIds = this.membersTagify.value.map(tag => String(tag.id));
                                            const toAdd = teamMembers.filter(m => !currentIds.includes(String(m.id)));
                                            this.membersTagify.addTags(toAdd);
                                        }
                                        const leaders = res.data.members.filter(m => m.leader == 1 || m.leader === '1');
                                        if (leaders.length && this.managerTagify) {
                                            const leaderTags = leaders.map(m => ({ id: m.user_id, value: m.user_name || '' }));
                                            const managerCurrentIds = this.managerTagify.value.map(tag => String(tag.id));
                                            const leadersToAdd = leaderTags.filter(m => !managerCurrentIds.includes(String(m.id)));
                                            this.managerTagify.addTags(leadersToAdd);
                                        }
                                    }
                                } catch (err) {}
                            });
                            this.tagify.on('change', this.onTeamTagsChange.bind(this));
                        } else if (!teamInput) {
                            // Nếu element chưa tồn tại, thử lại sau 100ms
                            setTimeout(() => {
                                this.initTagify();
                            }, 100);
                        }
                        
                        // --- Tagify for project_order_type ---
                        const orderTypeInput = document.querySelector('#project_order_type');
                        if (orderTypeInput && window.Tagify && !orderTypeInput._tagify) {
                            if (this.projectOrderTypeTagify) {
                                try {
                                    this.projectOrderTypeTagify.destroy();
                                } catch (e) {
                                    console.log('Error destroying existing projectOrderTypeTagify:', e);
                                }
                            }
                            this.projectOrderTypeTagify = new Tagify(orderTypeInput, {
                                whitelist: this.getProjectOrderTypeWhitelist(),
                                maxTags: 5,
                                dropdown: {
                                    maxItems: 20,
                                    classname: "tags-look-project-order-type",
                                    enabled: 0,
                                    closeOnSelect: true
                                },
                            });
                            // Set default value
                            let tags = [];
                            if (typeof this.project.project_order_type === 'string' && this.project.project_order_type) {
                                tags = this.project.project_order_type.split(',').map(s => s.trim()).filter(Boolean);
                            }
                            // if (tags.length > 0) {
                            //     this.projectOrderTypeTagify.addTags(tags);
                            // }
                            const updateOrderType = () => {
                                this.project.project_order_type = this.projectOrderTypeTagify.value.map(tag => tag.value).join(',');
                            };
                            this.projectOrderTypeTagify.on('add', updateOrderType);
                            this.projectOrderTypeTagify.on('remove', updateOrderType);
                        } else if (!orderTypeInput) {
                            // Nếu element chưa tồn tại, thử lại sau 100ms
                            setTimeout(() => {
                                this.initTagify();
                            }, 100);
                        }
                        

                    }, 100);
                });
            },
            async onTeamTagsChange(e) {
                const selected = this.tagify.value; // [{value, id}]
                const ids = selected.map(t => t.id).join(',');
                this.newProject.teams = ids;
                
                // try {
                //     const formData = new FormData();
                //     formData.append('id', this.projectId);
                //     formData.append('teams', ids);
                //     const res = await axios.post('/api/index.php?model=project&method=updateTeams', formData);
                //     if (res.data && res.data.success !== false) {
                //         // Reload lại team_list để hiển thị badge đúng
                //         await this.loadTeamListByIds(ids);
                //         // Chỉ tự động thêm member nếu team thay đổi so với ban đầu hoặc trước đó không có tag nào
                //         const originalTeamIds = (this.originalProject && this.originalProject.team_list) ? this.originalProject.team_list.map(t => String(t.id)).sort() : [];
                //         const newTeamIds = selected.map(t => String(t.id)).sort();
                //         const isChanged = originalTeamIds.length !== newTeamIds.length || originalTeamIds.some((id, idx) => id !== newTeamIds[idx]);
                //         const wasEmpty = !this.prevTeamIds || this.prevTeamIds.length === 0;
                //         if (isChanged || wasEmpty) {
                //             await this.addTeamMembersToMembers(newTeamIds);
                //         }
                //         // Cập nhật prevTeamIds cho lần sau
                //         this.prevTeamIds = [...newTeamIds];
                //     } else {
                //         alert('チームの更新に失敗しました。');
                //     }
                // } catch (error) {
                //     alert('チームの更新に失敗しました。');
                // }
            },
            async addTeamMembersToMembers(teamIds) {
                // Lấy toàn bộ user trong department nếu chưa có
                await this.loadDepartmentUsers();
                let memberIds = [];
                for (const teamId of teamIds) {
                    try {
                        const res = await axios.get(`/api/index.php?model=team&method=get&id=${teamId}`);
                        if (res.data && Array.isArray(res.data.members)) {
                            for (const m of res.data.members) {
                                if (!memberIds.includes(m.user_id)) {
                                    memberIds.push(m.user_id);
                                }
                            }
                        }
                    } catch (e) {}
                }
                // Add vào Tagify members (không trùng)
                if (this.membersTagify) {
                    const current = this.membersTagify.value.map(t => t.id);
                    const toAdd = memberIds.filter(id => !current.includes(id));
                    const allMembers = (this.departmentUsers || []).filter(u => toAdd.includes(u.user_id || u.id));
                    this.membersTagify.addTags(allMembers.map(u => ({
                        id: u.user_id,
                        value: u.user_name
                    })));
                }
            },
            initProjectDatePicker(elId, key, extra) {
                if (!this.isEditMode || !this.project) return;
                const el = document.getElementById(elId);
                if (!el) return;
                const serverValue = (this._serverProjectDates && this._serverProjectDates[key] != null)
                    ? this._serverProjectDates[key]
                    : this.project[key];
                const inputVal = toProjectDateTimeInputValue(serverValue);
                if (el._flatpickr) {
                    const fpVal = String(
                        (el._flatpickr._input && el._flatpickr._input.value) || el.value || ''
                    ).trim();
                    const displayVal = String(this.project[key] || '').trim();
                    if (fpVal && (fpVal === inputVal || fpVal === displayVal)) {
                        return;
                    }
                }
                initProjectDetailFlatpickr(el, {
                    defaultHour: extra.defaultHour,
                    defaultMinute: extra.defaultMinute,
                    onChange: (selectedDates, dateStr) => {
                        this.project[key] = dateStr;
                        if (this._serverProjectDates) {
                            this._serverProjectDates[key] = dateStr
                                ? fromProjectDateTimeInputValue(dateStr)
                                : '';
                        }
                    }
                }, serverValue);
                // Keep project[key] as server value until user edits / sync-from-picker.
                // Writing display TZ into project caused repeated VN/JA shifts on re-init.
                if (this._serverProjectDates && this._serverProjectDates[key] == null && serverValue != null) {
                    this._serverProjectDates[key] = serverValue;
                }
            },
            initDatePickers() {
                if (!this.isEditMode || !this.project) return;
                this.initProjectDatePicker('start_date_picker', 'start_date', { defaultHour: getStartDateDefaultHour(), defaultMinute: 0 });
                this.initProjectDatePicker('end_date_picker', 'end_date', { defaultHour: getDeadlineDefaultHour(), defaultMinute: 0 });
                this.initProjectDatePicker('caily_nouki_picker', 'caily_nouki', { defaultHour: getDeadlineDefaultHour(), defaultMinute: 0 });
                this.initProjectDatePicker('guis_nouki_picker', 'guis_nouki', { defaultHour: getDeadlineDefaultHour(), defaultMinute: 0 });
                this.initProjectDatePicker('actual_end_date_picker', 'actual_end_date', { defaultHour: getDeadlineDefaultHour(), defaultMinute: 0 });
                this.initCustomFieldDatePickers();
            },
            initCustomFieldDatePickers() {
                if (!this.isEditMode || !this.customFields) {
                    return;
                }
                this.$nextTick(() => {
                    this.customFields.forEach((field, idx) => {
                        if (field.type !== 'datetime') return;
                        const el = document.getElementById('custom_datetime_' + idx);
                        if (!el) return;
                        const serverValue = field._serverDatetime != null ? field._serverDatetime : field.value;
                        const inputVal = toProjectDateTimeInputValue(serverValue);
                        if (el._flatpickr) {
                            const fpVal = String(
                                (el._flatpickr._input && el._flatpickr._input.value) || el.value || ''
                            ).trim();
                            const displayVal = String(field.value || '').trim();
                            if (fpVal && (fpVal === inputVal || fpVal === displayVal)) {
                                return;
                            }
                        }
                        initProjectDetailFlatpickr(el, {
                            defaultHour: getCustomFieldDefaultHour(),
                            defaultMinute: 0,
                            onChange: (selectedDates, dateStr) => {
                                this.customFields[idx].value = dateStr;
                                this.customFields[idx]._serverDatetime = dateStr
                                    ? fromProjectDateTimeInputValue(dateStr)
                                    : '';
                            }
                        }, serverValue);
                        // Keep field.value as server until user edits — avoid double TZ convert on re-init
                        if (field._serverDatetime == null && serverValue) {
                            this.customFields[idx]._serverDatetime = serverValue;
                        }
                    });
                });
            },
            async initManagerMembersTagify() {
                // departmentUsers đã được load sẵn trong toggleEditMode; chỉ gọi khi chưa có
                if (!this.departmentUsers || this.departmentUsers.length === 0) {
                    await this.loadDepartmentUsers();
                }
                const allMembers = (this.departmentUsers || []).map(u => ({
                    user_id: u.id,
                    id: u.id,
                    value: u.user_name,
                    name: u.user_name
                }));
                // Khởi tạo Tagify cho manager
                const managerInput = document.getElementById('manager_tags');
                if (managerInput && window.Tagify) {
                    if (managerInput._tagify) managerInput._tagify.destroy();
                    const tagify = new Tagify(managerInput, {
                        whitelist: allMembers,
                        enforceWhitelist: false,
                        dropdown: { maxItems: 1000, enabled: 0, closeOnSelect: true }
                    });
                    tagify.addTags((this.managers || []).map(m => ({ id: m.user_id, value: m.user_name })));
                    tagify.on('change', e => {
                        // Không lưu ngay, chỉ cập nhật UI
                        const selected = tagify.value.map(t => t.id);
                        this.newProject.managers = selected;
                    });
                    this.managerTagify = tagify;
                }
                // Khởi tạo Tagify cho members
                const membersInput = document.getElementById('members_tags');
                if (membersInput && window.Tagify) {
                    if (membersInput._tagify) membersInput._tagify.destroy();
                    const tagify = new Tagify(membersInput, {
                        whitelist: allMembers,
                        enforceWhitelist: false,
                        dropdown: { maxItems: 1000, enabled: 0, closeOnSelect: true }
                    });
                    tagify.addTags((this.members || []).map(m => ({ id: m.user_id, value: m.user_name })));
                    tagify.on('change', e => {
                        // Không lưu ngay, chỉ cập nhật UI
                        const selected = tagify.value.map(t => t.id);
                        this.newProject.members = selected;
                    });
                    this.membersTagify = tagify;
                }
            },
            syncYoteiDraftFromProject() {
                if (typeof window.YoteiField === 'undefined') {
                    this.yoteiDraft = { from_month: '', from_part: '', to_month: '', to_part: '' };
                    return;
                }
                const parsed = window.YoteiField.parse(this.project && this.project.yotei);
                this.yoteiDraft = {
                    from_month: parsed.from_month || '',
                    from_part: parsed.from_part || '',
                    to_month: parsed.to_month || '',
                    to_part: parsed.to_part || ''
                };
                this.$nextTick(() => this.syncYoteiMonthPickers());
            },
            clearYoteiDraft() {
                if (typeof window.YoteiField !== 'undefined' && window.YoteiField.emptyModel) {
                    this.yoteiDraft = window.YoteiField.emptyModel();
                } else {
                    this.yoteiDraft = { from_month: '', from_part: '', to_month: '', to_part: '' };
                }
                if (this.validationErrors) this.validationErrors.yotei = '';
                this.$nextTick(() => this.syncYoteiMonthPickers());
            },
            destroyYoteiMonthPickers() {
                if (typeof window.YoteiField === 'undefined') return;
                window.YoteiField.destroyMonthPicker(document.getElementById('yotei_from_month_picker'));
                window.YoteiField.destroyMonthPicker(document.getElementById('yotei_to_month_picker'));
            },
            initYoteiMonthPickers() {
                if (typeof window.YoteiField === 'undefined' || !this.isEditMode) return;
                const self = this;
                window.YoteiField.initMonthPicker(
                    document.getElementById('yotei_from_month_picker'),
                    () => self.yoteiDraft.from_month,
                    (ym) => { self.yoteiDraft.from_month = ym || ''; }
                );
                window.YoteiField.initMonthPicker(
                    document.getElementById('yotei_to_month_picker'),
                    () => self.yoteiDraft.to_month,
                    (ym) => {
                        self.yoteiDraft.to_month = ym || '';
                        if (!ym) self.yoteiDraft.to_part = '';
                    }
                );
            },
            syncYoteiMonthPickers() {
                if (!this.isEditMode || typeof window.YoteiField === 'undefined') return;
                const fromEl = document.getElementById('yotei_from_month_picker');
                const toEl = document.getElementById('yotei_to_month_picker');
                if (!fromEl || !fromEl._flatpickr || !toEl || !toEl._flatpickr) {
                    this.initYoteiMonthPickers();
                    return;
                }
                window.YoteiField.setMonthPickerValue(fromEl, this.yoteiDraft.from_month);
                window.YoteiField.setMonthPickerValue(toEl, this.yoteiDraft.to_month);
            },
            getYoteiPayloadForSave() {
                if (typeof window.YoteiField === 'undefined') return null;
                return window.YoteiField.toPayload(this.yoteiDraft);
            },
            toggleEditMode() {
                if (!this.canEditProject) {
                    showMessage('管理者のみプロジェクトを編集できます。', true);
                    return;
                }
                this.isEditMode = true;
                this.originalProject = { ...this.project };
                this.syncYoteiDraftFromProject();
                this._serverProjectDates = {
                    start_date: this.project.start_date,
                    end_date: this.project.end_date,
                    caily_nouki: this.project.caily_nouki,
                    guis_nouki: this.project.guis_nouki,
                    actual_end_date: this.project.actual_end_date
                };
                // Lưu lại prevTeamIds khi vào edit mode
                this.prevTeamIds = (this.project.team_list || []).map(t => String(t.id)).sort();
                // Preload teams + department users song song để Tagify không phải chờ API khi init
                Promise.all([
                    this.loadAllTeams(),
                    this.project.department_id ? this.loadDepartmentUsers() : Promise.resolve([])
                ]).then(() => {
                    this.$nextTick(() => {
                        this.initDatePickers();
                        setTimeout(() => {
                            this.initTagify();
                            this.initManagerMembersTagify();
                        }, 200);
                    });
                });
            },
            toAPIDate(str) {
                if (str == null || str === '') return '';
                return fromProjectDateTimeInputValue(str);
            },
            prepareCustomFieldsForSave() {
                // Merge all fields from all department custom field sets
                if (!this.allDepartmentCustomFieldSets || this.allDepartmentCustomFieldSets.length === 0) return [];
                if (!this.customFields || this.customFields.length === 0) return [];
                
                // Create a map of label -> value for quick lookup
                const valueMap = {};
                this.customFields.forEach(field => {
                        if (field.label) {
                        if (field.isComposite) {
                            var compositeRaw = isCustomFieldCompositeCheckboxType(field.type)
                                ? (Array.isArray(field.valueArr) ? field.valueArr.join(',') : (field.value || ''))
                                : (field.value || '');
                            var compositeVal = sanitizeCustomFieldCompositeMainValue(
                                compositeRaw,
                                field.options,
                                field.type
                            );
                            valueMap[field.label.trim()] = compositeVal;
                            var selected = isCustomFieldCompositeValueSelected(compositeVal);
                            valueMap[getCustomFieldCompositeMemoLabel(field.label)] =
                                selected ? (field.memoValue || '') : '';
                            if (isCustomFieldCompositeReceivedLabel(field.label)) {
                                valueMap[getCustomFieldCompositeReceivedLabel(field.label)] =
                                    selected && field.receivedChecked
                                        ? normalizeCustomFieldCompositeReceivedValue('1')
                                        : '';
                            }
                        } else if (field.type === 'checkbox' || field.type === 'checkbox_select_all') {
                            valueMap[field.label.trim()] = Array.isArray(field.valueArr) ? field.valueArr.join(',') : '';
                        } else if (field.type === 'datetime') {
                            valueMap[field.label.trim()] = fromProjectDateTimeInputValue(field.value || '');
                        } else {
                            valueMap[field.label.trim()] = field.value || '';
                        }
                    }
                });
                
                // Collect all fields from all sets
                const allFields = [];
                const seen = {};
                const pushField = (label, type, options, oneRow) => {
                    const key = String(label || '').trim();
                    if (!key || seen[key]) return;
                    seen[key] = true;
                    allFields.push({
                        label: key,
                        type: type || 'text',
                        options: options != null ? options : '',
                        one_row: oneRow ? 1 : 0,
                        value: valueMap[key] != null ? valueMap[key] : ''
                    });
                };
                this.allDepartmentCustomFieldSets.forEach(set => {
                    if (set.fields && Array.isArray(set.fields)) {
                        set.fields.forEach(f => {
                            if (isCustomFieldCompositeCompanionLabel(f.label)) return;
                            if (isCustomFieldCompositeBaseLabel(f.label)) {
                                pushField(f.label, f.type || 'text', f.options, f.one_row);
                                if (isCustomFieldCompositeReceivedLabel(f.label)) {
                                    pushField(getCustomFieldCompositeReceivedLabel(f.label), 'checkbox', '1', false);
                                }
                                pushField(getCustomFieldCompositeMemoLabel(f.label), 'text', '', false);
                                return;
                            }
                            pushField(
                                f.label,
                                f.type,
                                f.options,
                                (f.one_row === 1 || f.one_row === '1' || f.one_row === true)
                            );
                        });
                    }
                });
                // Composite companions may exist only in UI state (not in set definition)
                this.customFields.forEach(field => {
                    if (!field || !field.isComposite) return;
                    pushField(field.label, field.type || 'text', field.options, field.one_row);
                    if (isCustomFieldCompositeReceivedLabel(field.label)) {
                        pushField(getCustomFieldCompositeReceivedLabel(field.label), 'checkbox', '1', false);
                    }
                    pushField(getCustomFieldCompositeMemoLabel(field.label), 'text', '', false);
                });
                
                return allFields;
            },
            parseSavedCustomFieldList(raw) {
                let saved = [];
                let data = raw;
                if (typeof data === 'string' && data.includes('&quot;')) {
                    data = data.replace(/&quot;/g, '"');
                }
                if (typeof data === 'string') {
                    try { saved = JSON.parse(data || '[]'); } catch (e) { saved = []; }
                } else if (Array.isArray(data)) {
                    saved = data;
                }
                return Array.isArray(saved) ? saved : [];
            },
            buildSavedCustomFieldValueMap(saved) {
                const savedValueMap = {};
                (saved || []).forEach(f => {
                    if (f && f.label) {
                        savedValueMap[String(f.label).trim()] = f;
                    }
                });
                return savedValueMap;
            },
            collectCustomFieldsFromSets(sets) {
                const allFieldsFromSets = [];
                (sets || []).forEach(set => {
                    if (!set || !Array.isArray(set.fields)) return;
                    set.fields.forEach(f => {
                        if (!f || !f.label) return;
                        const label = String(f.label).trim();
                        if (!label) return;
                        if (allFieldsFromSets.find(existing => existing.label && String(existing.label).trim() === label)) return;
                        allFieldsFromSets.push({
                            label: label,
                            type: f.type || 'text',
                            options: Array.isArray(f.options) ? f.options.join(',') : (f.options != null ? String(f.options) : ''),
                            one_row: (f.one_row === 1 || f.one_row === '1' || f.one_row === true)
                        });
                    });
                });
                return alignCustomFieldIshozuWithSetsubi(allFieldsFromSets);
            },
            mapSetFieldsToCustomFields(allFieldsFromSets, saved) {
                const savedValueMap = this.buildSavedCustomFieldValueMap(saved);
                const rows = [];
                (allFieldsFromSets || []).forEach(f => {
                    if (!f || !f.label) return;
                    const label = String(f.label).trim();
                    if (isCustomFieldCompositeCompanionLabel(label)) return;

                    const oneRow = (f.one_row === 1 || f.one_row === '1' || f.one_row === true);
                    const savedField = savedValueMap[label];

                    if (isCustomFieldCompositeBaseLabel(label)) {
                        const memoLabel = getCustomFieldCompositeMemoLabel(label);
                        const memoSaved = savedValueMap[memoLabel];
                        const supportsReceived = isCustomFieldCompositeReceivedLabel(label);
                        const receivedLbl = supportsReceived ? getCustomFieldCompositeReceivedLabel(label) : '';
                        const receivedSaved = supportsReceived ? savedValueMap[receivedLbl] : null;
                        const fieldType = f.type || 'text';
                        const fieldOptions = Array.isArray(f.options) ? f.options.join(',') : (f.options != null ? String(f.options) : '');
                        let mainValue = sanitizeCustomFieldCompositeMainValue(
                            savedField && savedField.value != null ? savedField.value : '',
                            fieldOptions,
                            fieldType
                        );
                        let valueArr = [];
                        if (isCustomFieldCompositeCheckboxType(fieldType)) {
                            valueArr = mainValue ? mainValue.split(',').map(s => s.trim()).filter(Boolean) : [];
                            mainValue = valueArr.join(',');
                        }
                        const selected = isCustomFieldCompositeValueSelected(mainValue);
                        rows.push({
                            label: label,
                            type: fieldType,
                            options: fieldOptions,
                            one_row: oneRow,
                            value: mainValue,
                            valueArr: valueArr,
                            isComposite: true,
                            supportsReceived: supportsReceived,
                            receivedLabel: receivedLbl,
                            receivedChecked: selected && receivedSaved
                                ? isCustomFieldCompositeReceivedChecked(receivedSaved.value)
                                : false,
                            memoLabel: memoLabel,
                            // The memo is free text (see the note on text fields below)
                            memoValue: selected && memoSaved && memoSaved.value != null
                                ? (String(memoSaved.value).indexOf('&') !== -1
                                    ? this.decodeHtmlEntities(String(memoSaved.value))
                                    : String(memoSaved.value))
                                : ''
                        });
                        return;
                    }

                    if (f.type === 'checkbox' || f.type === 'checkbox_select_all') {
                        let arr = [];
                        if (savedField && savedField.value) {
                            arr = String(savedField.value).split(',').map(s => s.trim()).filter(Boolean);
                        }
                        rows.push({
                            label: label,
                            type: f.type,
                            options: f.options,
                            one_row: oneRow,
                            value: arr.join(','),
                            valueArr: arr
                        });
                        return;
                    }

                    const row = {
                        label: label,
                        type: f.type,
                        options: f.options,
                        one_row: oneRow,
                        value: savedField ? (savedField.value || '') : ''
                    };
                    // Free text arrives entity-encoded from the API and is only shown through
                    // text bindings and inputs: give it its real characters (it's -> not it&#039;s).
                    // Choice types are left alone: their value is matched against the options,
                    // which are encoded the same way.
                    if ((!f.type || f.type === 'text' || f.type === 'textarea')
                        && typeof row.value === 'string' && row.value.indexOf('&') !== -1) {
                        row.value = this.decodeHtmlEntities(row.value);
                    }
                    if (f.type === 'datetime' && savedField && savedField.value) {
                        row._serverDatetime = savedField.value;
                    }
                    rows.push(row);
                });
                return this.groupStatusCompanionFields(rows);
            },
            /**
             * A custom field whose label is another field's label + 状況 is that field's status
             * (the project list already shows the two in one cell). Put it directly after its
             * field and mark it, so the page shows the pair as one group. The list stays flat:
             * saving and the field indexes used by the form are unaffected.
             */
            groupStatusCompanionFields(rows) {
                const baseOf = (label) => String(label || '').trim().replace(/状況s*$/, '').trim();
                const labels = new Set(rows.map(r => String(r.label || '').trim()));
                const companions = {};
                rows.forEach(r => {
                    const label = String(r.label || '').trim();
                    const base = baseOf(label);
                    if (/状況s*$/.test(label) && base && base !== label && labels.has(base) && !r.isComposite) {
                        r.statusOf = base;
                        (companions[base] = companions[base] || []).push(r);
                    }
                });
                const ordered = [];
                rows.forEach(r => {
                    if (r.statusOf) return;
                    ordered.push(r);
                    (companions[String(r.label || '').trim()] || []).forEach(c => ordered.push(c));
                });
                return ordered;
            },
            async quickUpdateNoukiStatus(kind) {
                if (!this.project || !this.project.id) return;
                const field = kind === 'caily' ? 'caily_nouki_status' : 'guis_nouki_status';
                const newVal = this.project[field] || '';
                // Checkbox already flipped via v-model; previous value is the opposite of 納品済み toggle
                const oldVal = (this.originalProject && this.originalProject[field] != null)
                    ? (this.originalProject[field] || '')
                    : (newVal === '納品済み' ? '' : '納品済み');
                const becameDelivered = newVal === '納品済み' && oldVal !== '納品済み';
                let shareAnswer = null;
                if (becameDelivered && window.EnergyDrawingShare) {
                    const answer = await window.EnergyDrawingShare.ensureEnergyDrawingShareAnswer(this.project, {
                        departmentName: this.project.department_name,
                        siblings: this.parentSiblingProjects,
                        hasEnergySibling: this.project.has_energy_sibling
                    });
                    if (answer === false) {
                        this.project[field] = oldVal;
                        return;
                    }
                    shareAnswer = answer;
                }
                try {
                    const formData = new FormData();
                    formData.append('id', this.project.id);
                    if (kind === 'caily') {
                        formData.append('caily_nouki_status', this.project.caily_nouki_status || '');
                    } else if (kind === 'guis') {
                        formData.append('guis_nouki_status', this.project.guis_nouki_status || '');
                    }
                    if (window.EnergyDrawingShare) {
                        window.EnergyDrawingShare.appendEnergyDrawingShareToFormData(formData, shareAnswer);
                    }
                    appendProjectVersionToFormData(formData, this.project);
                    const response = await axios.post('/api/index.php?model=project&method=update', formData);
                    if (!response.data || response.data.status !== 'success') {
                        if (handleProjectVersionConflict(response.data, () => this.loadProject())) {
                            return;
                        }
                        this.project[field] = oldVal;
                        if (typeof showMessage === 'function') {
                            showMessage('納期状況の更新に失敗しました。', true);
                        }
                    } else{
                        applyProjectVersionFromResponse(this.project, response.data);
                        if (window.EnergyDrawingShare && shareAnswer) {
                            window.EnergyDrawingShare.applyEnergyDrawingShareToProject(this.project, shareAnswer);
                        }
                        if (this.originalProject) {
                            this.originalProject[field] = this.project[field];
                            if (shareAnswer) {
                                this.originalProject.energy_drawing_share_status = this.project.energy_drawing_share_status;
                                this.originalProject.energy_drawing_share_reason = this.project.energy_drawing_share_reason;
                                this.originalProject.energy_drawing_share_note = this.project.energy_drawing_share_note;
                            }
                        }
                        showMessage('納期状況を更新しました。');
                    }

                } catch (e) {
                    this.project[field] = oldVal;
                    if (typeof showMessage === 'function') {
                        showMessage('納期状況の更新に失敗しました。', true);
                    }
                }
            },
            async saveProject() {
                if (!this.project) {
                    if (typeof showMessage === 'function') showMessage('プロジェクトデータが読み込まれていません。', true);
                    return;
                }
                if (!this.validateProjectForm()) {
                    const msg = this.validationErrors.department_id
                        || this.validationErrors.name
                        || this.validationErrors.status
                        || this.validationErrors.project_order_type
                        || this.validationErrors.tantou
                        || this.validationErrors.caily_nouki
                        || this.validationErrors.guis_nouki
                        || this.validationErrors.end_date
                        || this.validationErrors.yotei
                        || this.validationErrors.guis_receiver
                        || this.validationErrors.project_number
                        || '入力内容を確認してください。';
                    if (typeof showMessage === 'function') showMessage(msg, true);
                    else if (typeof this.showNotification === 'function') this.showNotification(msg, 'error');
                    return;
                }
                const prevStatus = this.originalProject && this.originalProject.status;
                if (this.project.status === 'completed' && prevStatus !== 'completed') {
                    const ok = await this.confirmPaymentInfoBeforeComplete(this.project);
                    if (!ok) return;
                }
                const prevCaily = this.originalProject ? (this.originalProject.caily_nouki_status || '') : '';
                const prevGuis = this.originalProject ? (this.originalProject.guis_nouki_status || '') : '';
                const noukiBecameDelivered =
                    ((this.project.caily_nouki_status || '') === '納品済み' && prevCaily !== '納品済み')
                    || ((this.project.guis_nouki_status || '') === '納品済み' && prevGuis !== '納品済み');
                const statusBecameCompleted = this.project.status === 'completed' && prevStatus !== 'completed';
                let shareAnswer = null;
                if ((statusBecameCompleted || noukiBecameDelivered) && window.EnergyDrawingShare) {
                    const answer = await window.EnergyDrawingShare.ensureEnergyDrawingShareAnswer(this.project, {
                        departmentName: this.project.department_name,
                        siblings: this.parentSiblingProjects,
                        hasEnergySibling: this.project.has_energy_sibling
                    });
                    if (answer === false) return;
                    shareAnswer = answer;
                }
                // Use the stored quill content instead of syncing from editor
                if (this.quillContent !== undefined) {
                    this.project.description = this.quillContent;
                }
                
                // Save custom field values (no need to save set_id since we use all sets)
                this.project.custom_fields = JSON.stringify(this.prepareCustomFieldsForSave());
                this.savingProject = true;
                try {
                    const isCreate = !!this.formCreateMode;
                    const formData = new FormData();
                    if (isCreate) {
                        // A new project of a building: the server numbers it and adds its default tasks
                        formData.append('department_id', this.project.department_id);
                        formData.append('parent_project_id', this.project.parent_project_id || '');
                        formData.append('is_kadai', '0');
                        formData.append('amount', '0');
                    } else {
                        formData.append('id', this.project.id);
                    }
                    formData.append('name', this.project.name);
                    // formData.append('building_branch', this.project.building_branch);
                    // formData.append('building_size', this.project.building_size);
                    // formData.append('building_type', this.project.building_type);
                    // formData.append('building_number', this.project.building_number);
                    formData.append('project_number', isCreate ? '' : this.project.project_number);
                    formData.append('progress', this.project.progress);
                    formData.append('priority', this.project.priority || (isCreate ? 'medium' : ''));
                    formData.append('status', this.project.status);
                    // Use Tagify current value (kể cả khi rỗng) hoặc project khi chưa có tagify
                    let teamsVal = '';
                    if (this.tagify) {
                        teamsVal = (this.tagify.value || []).map(t => String(t.id)).join(',');
                    } else if (this.newProject.teams !== undefined && this.newProject.teams !== null) {
                        teamsVal = String(this.newProject.teams);
                    } else if (this.project.teams) {
                        teamsVal = typeof this.project.teams === 'string' ? this.project.teams : String(this.project.teams || '');
                    }
                    let managersVal = this.newProject.managers || '';
                    if (this.managerTagify && this.managerTagify.value && this.managerTagify.value.length) {
                        managersVal = this.managerTagify.value.map(t => String(t.id)).join(',');
                    } else if (!managersVal && this.managers && this.managers.length) {
                        managersVal = this.managers.map(m => String(m.user_id)).join(',');
                    }
                    let membersVal = this.newProject.members || '';
                    if (this.membersTagify && this.membersTagify.value && this.membersTagify.value.length) {
                        membersVal = this.membersTagify.value.map(t => String(t.id)).join(',');
                    } else if (!membersVal && this.members && this.members.length) {
                        membersVal = this.members.map(m => String(m.user_id)).join(',');
                    }
                    formData.append('teams', teamsVal);
                    formData.append('members', membersVal);
                    formData.append('managers', managersVal);
                    formData.append('start_date', this.toAPIDate(this.project.start_date));
                    formData.append('end_date', this.toAPIDate(this.project.end_date));
                    formData.append('actual_end_date', this.toAPIDate(this.project.actual_end_date) || '');
                    formData.append('tantou', this.project.tantou || '');
                    // Hidden for 省エネ departments: not sent, so stored values stay as they are
                    if (!this.isEnergyDepartmentProject) {
                        formData.append('caily_nouki', this.toAPIDate(this.project.caily_nouki) || '');
                        formData.append('guis_nouki', this.toAPIDate(this.project.guis_nouki) || '');
                        formData.append('caily_nouki_status', this.project.caily_nouki_status || '');
                        formData.append('guis_nouki_status', this.project.guis_nouki_status || '');
                    }
                    const yoteiPayload = this.getYoteiPayloadForSave();
                    formData.append('yotei', yoteiPayload ? JSON.stringify(yoteiPayload) : '');
                    formData.append('project_order_type', this.project.project_order_type);
                    formData.append('customer_id', this.project.customer_id || '');
                    // formData.append('amount', this.project.amount);
                    // formData.append('estimate_status', this.project.estimate_status);
                    // formData.append('invoice_status', this.project.invoice_status);
                    formData.append('tags', this.project.tags);
                    // No need to send department_custom_fields_set_id since we use all sets from department
                    formData.append('custom_fields', this.project.custom_fields);
                    formData.append('description', this.project.description || '');
                    this.syncEnergyMaterialFlagsFromComposite();
                    this.appendEnergyFieldsToFormData(formData);
                    if (this.guisReceiverForm && this.guisReceiverForm.enabled) {
                        formData.append('guis_receiver', this.resolveGuisReceiverForSave());
                    }
                    if (!isCreate) {
                        appendProjectVersionToFormData(formData, this.project);
                    }
                    if (window.EnergyDrawingShare) {
                        window.EnergyDrawingShare.appendEnergyDrawingShareToFormData(formData, shareAnswer);
                    }
                    const response = await axios.post('/api/index.php?model=project&method=' + (isCreate ? 'create' : 'update'), formData);
                    if (isCreate) {
                        if (response.data && response.data.status == 'success') {
                            this.project.id = response.data.project_id || null;
                            this.isEditMode = false;
                            this.originalProject = null;
                            this._serverProjectDates = null;
                            showMessage('案件を作成しました。');
                            setTimeout(() => { this.loadProject(); }, 0);
                        } else {
                            const data = response.data || {};
                            showMessage(data.message || data.error || '案件の作成に失敗しました。', true);
                        }
                        return;
                    }
                    if (response.data && response.data.status == 'success') {
                        applyProjectVersionFromResponse(this.project, response.data);
                        if (window.EnergyDrawingShare && shareAnswer) {
                            window.EnergyDrawingShare.applyEnergyDrawingShareToProject(this.project, shareAnswer);
                        }
                        this.isEditMode = false;
                        this.originalProject = null;
                        this._serverProjectDates = null;
                        showMessage('プロジェクトを更新しました。');
                        // Hoãn loadProject để trình duyệt kịp vẽ thông báo trước khi xử lý nặng
                        setTimeout(() => { this.loadProject(); }, 0);
                    } else {
                        if (handleProjectVersionConflict(response.data, () => this.loadProject())) {
                            return;
                        }
                        showMessage('プロジェクトの更新に失敗しました。', true);
                    }
                } catch (error) {
                    console.error('Error saving project:', error);
                    if (typeof showMessage === 'function') {
                        showMessage('プロジェクトの更新に失敗しました。', true);
                    } else {
                        alert('プロジェクトの更新に失敗しました。');
                    }
                } finally {
                    this.savingProject = false;
                }
            },
            cancelEdit() {
                // Sync quill content before canceling
                if (this.quillInstance && this.quillContent !== undefined) {
                    this.project.description = this.quillContent;
                }
                
                this.isEditMode = false;
                this.project = { ...this.originalProject };
                this.syncYoteiDraftFromProject();
                this.destroyYoteiMonthPickers();
                this._serverProjectDates = null;
                this.loadMembers(); // Restore managers and members from backend for correct avatars
                this.initVietnamTimeTooltips();
            },
            async loadDepartmentUsers() {
                if (!this.project.department_id) return [];
                try {
                    const res = await axios.get(`/api/index.php?model=department&method=get_users&department_id=${this.project.department_id}`);
                    this.departmentUsers = res.data || [];
                    return this.departmentUsers;
                } catch (e) {
                    this.departmentUsers = [];
                    return [];
                }
            },
            showNotification(message, type = 'info') {
                // Use showMessage function if available, otherwise use alert
                showMessage(message, type === 'error');
            },
            initQuillEditor() {
                if (this.quillInstance || !this.isEditMode) return;

                ensureProjectDetailQuill().then(() => {
                    if (this.quillInstance || !this.isEditMode) return;
                    // Use a longer delay to ensure all other components are initialized first
                    setTimeout(() => {
                        if (this.quillInstance || !this.isEditMode) return;
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
                        const el = document.getElementById('quill_description');
                        if (!el || typeof Quill === 'undefined') return;
                    
                        // Destroy existing instance if any
                        if (this.quillInstance) {
                            try {
                                this.quillInstance = null;
                            } catch (e) {
                                console.log('Error destroying existing quill instance:', e);
                            }
                        }
                    
                        this.quillInstance = new Quill(el, {
                            bounds: el,
                            placeholder: 'Type Something...',
                            modules: {
                                // syntax: true,
                                toolbar: {
                                    container: toolbarOptions,
                                    handlers: {
                                        image: () => this.imageHandler()
                                    }
                                }
                            },
                            theme: 'snow'
                        });
                    
                        // Set initial content
                        if (this.project.description) {
                            const html = this.decodeHtmlEntities(this.project.description);
                            this.quillInstance.root.innerHTML = html;
                        }
                    
                        // Store content in a separate variable, not in Vue data
                        this.quillContent = this.quillInstance.getSemanticHTML();
                    
                        // Simple text-change handler without debounce
                        this.quillInstance.on('text-change', () => {
                            this.quillContent = this.quillInstance.getSemanticHTML();
                            this.addZoomToDescriptionImages();
                        });
                    
                        // Prevent focus loss by stopping event propagation on toolbar clicks
                        const toolbar = this.quillInstance.getModule('toolbar');
                        if (toolbar && toolbar.container) {
                            toolbar.container.addEventListener('mousedown', (e) => {
                                e.stopPropagation();
                            });
                            toolbar.container.addEventListener('click', (e) => {
                                e.stopPropagation();
                            });
                        }
                    
                        // Focus the editor after initialization
                        // setTimeout(() => {
                        //     if (this.quillInstance) {
                        //         this.quillInstance.focus();
                        //     }
                        // }, 100);
                    
                    }, 400); // Increased delay to ensure other components are initialized first
                    this.addZoomToDescriptionImages();
                }).catch((err) => {
                    console.error('Failed to load Quill for project description:', err);
                });
            },
            destroyQuillEditor() {
                if (this.quillInstance) {
                    try {
                        // Remove event listeners from toolbar
                        const toolbar = this.quillInstance.getModule('toolbar');
                        if (toolbar && toolbar.container) {
                            toolbar.container.removeEventListener('mousedown', (e) => {
                                e.stopPropagation();
                            });
                            toolbar.container.removeEventListener('click', (e) => {
                                e.stopPropagation();
                            });
                        }
                        
                        // Clear the editor content
                        this.quillInstance.setText('');
                        
                        // Clear the stored content
                        this.quillContent = '';
                        
                        // Destroy the instance
                        this.quillInstance = null;
                    } catch (e) {
                        console.log('Error destroying quill editor:', e);
                        this.quillInstance = null;
                    }
                }
            },
            decodeHtmlEntities(str) {
                const txt = document.createElement('textarea');
                txt.innerHTML = str;
                return txt.value;
            },
            // Image handler for Quill editor
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
                                this.showNotification('ファイルサイズは5MB以下にしてください。', 'error');
                                return;
                            }
                            
                            // Upload ảnh
                            const uploadUrl = '/api/quill-image-upload.php';
                            let response;
                            
                            // Debug: log project ID
                            console.log('Project ID for upload:', this.projectId);
                            
                            if (window.swManager && window.swManager.swRegistration) {
                                // Sử dụng Service Worker với project_id
                                response = await window.swManager.uploadFile(file, uploadUrl, { project_id: this.projectId });
                            } else {
                                // Fallback to regular upload
                                const formData = new FormData();
                                formData.append('image', file);
                                formData.append('project_id', this.projectId);
                                const uploadResponse = await axios.post(uploadUrl, formData, {
                                    headers: {
                                        'Content-Type': 'multipart/form-data'
                                    }
                                });
                                response = uploadResponse.data;
                            }
                            
                            if (response.success) {
                                // Chèn ảnh vào cuối editor mà không dùng getSelection
                                requestAnimationFrame(() => {
                                    try {
                                        if (this.quillInstance && this.quillInstance.root) {
                                            // Lấy độ dài hiện tại của nội dung
                                            const length = this.quillInstance.getLength();
                                            
                                            // Chèn ảnh ở cuối
                                            this.quillInstance.insertEmbed(length - 1, 'image', response.url);
                                            this.quillInstance.insertText(length, '\n');
                                            
                                            // Focus vào editor
                                            this.quillInstance.focus();
                                            
                                            // Scroll xuống cuối
                                            if (this.quillInstance.scrollingContainer) {
                                                this.quillInstance.scrollingContainer.scrollTop = this.quillInstance.scrollingContainer.scrollHeight;
                                            }
                                        }
                                    } catch (error) {
                                        console.error('Error inserting image:', error);
                                        // Fallback: append trực tiếp vào HTML
                                        if (this.quillInstance && this.quillInstance.root) {
                                            const imageHtml = `<p><img src="${response.url}" alt="Uploaded image" style="max-width: 100%; height: auto;"></p>`;
                                            this.quillInstance.root.innerHTML += imageHtml;
                                        }
                                    }
                                });
                            } else {
                                this.showNotification('画像のアップロードに失敗しました: ' + (response.error || 'Unknown error'), 'error');
                            }
                        } catch (error) {
                            console.error('Error uploading image:', error);
                            this.showNotification('画像のアップロードに失敗しました。', 'error');
                        }
                    }
                };
            },
            handleBeforeUnload(event) {
                event.preventDefault();
                event.returnValue = '編集中の内容が保存されていません。本当にページを離れますか？';
                return event.returnValue;
            },
            async loadDepartmentCustomFieldSets() {
                if (!this.project || !this.project.department_id) {
                    this.departmentCustomFieldSets = [];
                    return;
                }
                try {
                    const res = await axios.get('/api/index.php?model=department&method=getCustomFields');
                    if (Array.isArray(res.data)) {
                        this.departmentCustomFieldSets = res.data.filter(set => String(set.department_id) === String(this.project.department_id));
                    } else {
                        this.departmentCustomFieldSets = [];
                    }
                } catch (e) {
                    this.departmentCustomFieldSets = [];
                }
            },
            getCustomFieldValue(label) {
                if (!this.project || !this.project.custom_fields) return '';
                let arr = [];
                let raw = this.project.custom_fields;
                if (typeof raw === 'string' && raw.includes('&quot;')) {
                    raw = raw.replace(/&quot;/g, '"');
                }
                if (typeof raw === 'string') {
                    try { arr = JSON.parse(raw); } catch (e) { arr = []; }
                } else if (Array.isArray(raw)) {
                    arr = raw;
                }
                const found = arr.find(f => f.label && f.label.trim() === label.trim());
                return found ? found.value : '';
            },
            getCustomFieldsForView() {
                const allFieldsFromSets = this.collectCustomFieldsFromSets(this.allDepartmentCustomFieldSets);
                const saved = this.parseSavedCustomFieldList(this.project?.custom_fields);
                // The page lists only what has been filled in; empty fields are set in the edit dialog
                const all = this.mapSetFieldsToCustomFields(allFieldsFromSets, saved);
                const withValue = new Set(all.filter(f => this.customFieldHasValue(f)).map(f => String(f.label || '').trim()));
                // A 状況 field follows its own field: shown whenever that field is, even while empty
                const filled = all.filter(f => this.customFieldHasValue(f) || (f.statusOf && withValue.has(f.statusOf)));
                const shown = new Set(filled.map(f => String(f.label || '').trim()));
                // A 状況 whose own field is empty stands alone, under its full label
                return filled.map(f => (f.statusOf && !shown.has(f.statusOf)) ? { ...f, statusOf: null } : f);
            },
            customFieldHasValue(field) {
                if (!field) return false;
                if (field.isComposite) return this.isCompositeValueSelected(field);
                if (Array.isArray(field.valueArr) && field.valueArr.length > 0) return true;
                return this.hasFieldValue(field.value);
            },
            hasFieldValue(value) {
                if (value == null) return false;
                if (Array.isArray(value)) return value.length > 0;
                const text = String(value).trim();
                return text !== '' && text !== '-' && text !== '—';
            },
            /**
             * Whether a row of the project card is shown. The read-only page lists only fields
             * that have a value; the edit dialog shows every field.
             */
            showField(key) {
                // 省エネ departments do not use CAILY納期 / GUIS納期 (page and form)
                if ((key === 'caily' || key === 'guis') && this.isEnergyDepartmentProject) return false;
                if (this.isEditMode || !this.project) return true;
                const p = this.project;
                switch (key) {
                    case 'order_type': return this.hasFieldValue(p.project_order_type);
                    case 'tantou': return this.hasFieldValue(p.tantou);
                    case 'yotei': return this.hasFieldValue(this.yoteiDisplayText);
                    case 'start': return this.hasFieldValue(this.formatDateTime(p.start_date));
                    case 'end': return this.hasFieldValue(this.formatDateTime(p.end_date));
                    case 'actual_end': return this.hasFieldValue(this.formatDateTime(p.actual_end_date));
                    case 'caily': return this.hasFieldValue(this.formatDateTime(p.caily_nouki));
                    case 'guis': return this.hasFieldValue(this.formatDateTime(p.guis_nouki));
                    case 'team': return Array.isArray(p.team_list) && p.team_list.length > 0;
                    case 'managers': return Array.isArray(this.managers) && this.managers.length > 0;
                    case 'members': return Array.isArray(this.members) && this.members.length > 0;
                    case 'tags': return this.hasFieldValue(p.tags);
                    case 'energy_materials':
                        return this.getEnergyMaterialPlainSelected().length > 0
                            || this.getEnergyMaterialCompositeFields(this.getCustomFieldsForView()).length > 0;
                    case 'energy_agency': return this.energyReviewAgencyList.length > 0;
                    case 'energy_grade': return this.hasFieldValue(this.belsZehGradeLabel);
                    case 'description': {
                        const html = this.decodeHtmlEntities(p.description || '');
                        return /<img/i.test(html) || this.hasFieldValue(html.replace(/<[^>]*>/g, '').replace(/&nbsp;| /g, ' '));
                    }
                    default: return true;
                }
            },
            /** A section title is shown while at least one of its rows is. */
            showSection(key) {
                if (this.isEditMode || !this.project) return true;
                const energy = this.isEnergyDepartmentProject;
                const any = (keys) => keys.some(k => this.showField(k));
                switch (key) {
                    case 'schedule': {
                        const keys = ['yotei', 'start', 'caily'];
                        if (this.canViewEndDate) keys.push('end', 'guis');
                        if (this.project.status === 'completed') keys.push('actual_end');
                        return any(keys);
                    }
                    case 'team': return any(energy ? ['managers'] : ['team', 'managers', 'members']);
                    case 'other': return any(['tags', 'description']);
                    case 'energy': return energy && any(['energy_materials', 'energy_agency', 'energy_grade']);
                    default: return true;
                }
            },
            onCompositeRadioChange(field, value) {
                if (!field || !field.isComposite) return;
                field.value = sanitizeCustomFieldCompositeMainValue(value, field.options, field.type);
                const patches = [{ label: field.label, value: field.value }];
                if (!isCustomFieldCompositeValueSelected(field.value)) {
                    field.memoValue = '';
                    field.receivedChecked = false;
                    patches.push({ label: field.memoLabel, value: '' });
                    if (field.supportsReceived && field.receivedLabel) {
                        patches.push({ label: field.receivedLabel, value: '' });
                    }
                }
                this.updateCustomFieldValues(patches);
            },
            onCompositeCheckboxChange(field, opt, checked) {
                if (!field || !field.isComposite) return;
                const allowed = getCustomFieldCompositeOptions(field);
                let arr = Array.isArray(field.valueArr) ? field.valueArr.slice() : [];
                // Drop orphans not in current options before applying the toggle
                arr = arr.filter(v => allowed.indexOf(v) !== -1);
                const i = arr.indexOf(opt);
                if (checked && i === -1 && allowed.indexOf(opt) !== -1) arr.push(opt);
                if (!checked && i !== -1) arr.splice(i, 1);
                field.valueArr = arr;
                field.value = arr.join(',');
                const patches = [{ label: field.label, value: field.value }];
                if (!isCustomFieldCompositeValueSelected(field.value)) {
                    field.memoValue = '';
                    field.receivedChecked = false;
                    patches.push({ label: field.memoLabel, value: '' });
                    if (field.supportsReceived && field.receivedLabel) {
                        patches.push({ label: field.receivedLabel, value: '' });
                    }
                }
                this.updateCustomFieldValues(patches);
            },
            onCompositeReceivedChange(field, checked) {
                if (!field || !field.isComposite || !field.supportsReceived || !field.receivedLabel) return;
                if (!this.isCompositeValueSelected(field)) {
                    field.receivedChecked = false;
                    return;
                }
                field.receivedChecked = !!checked;
                this.updateCustomFieldValues([{
                    label: field.receivedLabel,
                    value: field.receivedChecked ? normalizeCustomFieldCompositeReceivedValue('1') : ''
                }]);
            },
            onCompositeMemoChange(field, value) {
                if (!field || !field.isComposite) return;
                const mainVal = sanitizeCustomFieldCompositeMainValue(
                    isCustomFieldCompositeCheckboxType(field.type)
                        ? (Array.isArray(field.valueArr) ? field.valueArr.join(',') : (field.value || ''))
                        : (field.value || ''),
                    field.options,
                    field.type
                );
                if (!isCustomFieldCompositeValueSelected(mainVal)) return;
                field.memoValue = value != null ? String(value) : '';
                this.updateCustomFieldValues([{ label: field.memoLabel, value: field.memoValue }]);
            },
            // ---- 省エネ向け資料: 意匠図 / 設備図 / 配置図 are the department's composite custom fields ----
            /** A composite custom field of a 省エネ project that is shown inside 省エネ向け資料. */
            isEnergyMaterialCompositeField(field) {
                return !!(field && field.isComposite && this.isEnergyDepartmentProject
                    && ENERGY_MATERIAL_COMPOSITE_ORDER.indexOf(String(field.label || '').trim()) !== -1);
            },
            /** Those fields out of a custom field list, in the order of the 省エネ向け資料 choices. */
            getEnergyMaterialCompositeFields(fields) {
                const list = (fields || []).filter(f => this.isEnergyMaterialCompositeField(f));
                return list.sort((a, b) => ENERGY_MATERIAL_COMPOSITE_ORDER.indexOf(String(a.label).trim())
                    - ENERGY_MATERIAL_COMPOSITE_ORDER.indexOf(String(b.label).trim()));
            },
            /** Labels the department defines as composite custom fields (so not plain checkboxes). */
            getEnergyMaterialCompositeLabels() {
                if (!this.isEnergyDepartmentProject) return [];
                return this.collectCustomFieldsFromSets(this.allDepartmentCustomFieldSets)
                    .map(f => String((f && f.label) || '').trim())
                    .filter(label => ENERGY_MATERIAL_COMPOSITE_ORDER.indexOf(label) !== -1 && isCustomFieldCompositeBaseLabel(label));
            },
            /** 省エネ向け資料 choices that stay plain checkboxes in the form. */
            getEnergyMaterialPlainOptions() {
                const composite = this.getEnergyMaterialCompositeLabels();
                return (this.energyEditMaterialOptions || []).filter(opt => composite.indexOf(opt) === -1);
            },
            /** Selected 省エネ向け資料 shown as badges on the page (the composite ones have their own lines). */
            getEnergyMaterialPlainSelected() {
                const composite = this.getEnergyMaterialCompositeLabels();
                return this.selectedEnergyMaterialLabels.filter(opt => composite.indexOf(opt) === -1);
            },
            /** On save: a composite field with a choice counts as that 資料 being selected. */
            syncEnergyMaterialFlagsFromComposite() {
                if (!this.isEnergyDepartmentProject || !this.energyEdit || !this.energyEdit.flags) return;
                this.getEnergyMaterialCompositeFields(this.customFields).forEach((field) => {
                    this.energyEdit.flags[String(field.label).trim()] = this.isCompositeValueSelected(field);
                });
            },
            isCompositeValueSelected(field) {
                if (!field) return false;
                if (isCustomFieldCompositeCheckboxType(field.type)) {
                    return Array.isArray(field.valueArr) && field.valueArr.length > 0;
                }
                return isCustomFieldCompositeValueSelected(field.value);
            },
            getCompositeOptions(field) {
                return getCustomFieldCompositeOptions(field);
            },
            isCompositeCheckboxType(field) {
                return !!(field && isCustomFieldCompositeCheckboxType(field.type));
            },
            async updateCustomFieldValue(label, value) {
                return this.updateCustomFieldValues([{ label: label, value: value }]);
            },
            async updateCustomFieldValues(patches) {
                if (!this.project || !Array.isArray(patches) || !patches.length) return;
                const validPatches = patches.filter(p => p && p.label);
                if (!validPatches.length) return;
                const lockKey = validPatches.map(p => String(p.label).trim()).sort().join('|');
                if (this.savingCustomFieldLabel === lockKey) return;
                let saved = [];
                let raw = this.project.custom_fields;
                if (typeof raw === 'string' && raw.includes('&quot;')) raw = raw.replace(/&quot;/g, '"');
                try {
                    saved = typeof raw === 'string' ? JSON.parse(raw || '[]') : (Array.isArray(raw) ? raw : []);
                } catch (e) { saved = []; }
                // Drop legacy 【〇】 companions so they are not re-saved / logged
                let updated = saved.filter(f => {
                    const lbl = f && f.label != null ? String(f.label).trim() : '';
                    return lbl && !/【(?:〇|○)】$/.test(lbl);
                });
                validPatches.forEach(patch => {
                    const label = String(patch.label).trim();
                    const newValue = (patch.value != null) ? String(patch.value).trim() : '';
                    let found = false;
                    updated = updated.map(f => {
                        if (!f || String(f.label || '').trim() !== label) return f;
                        found = true;
                        return Object.assign({}, f, { value: newValue });
                    });
                    if (!found) updated.push({ label: label, value: newValue });
                });
                this.savingCustomFieldLabel = lockKey;
                try {
                    const formData = new FormData();
                    formData.append('id', this.project.id);
                    formData.append('custom_fields', JSON.stringify(updated));
                    appendProjectVersionToFormData(formData, this.project);
                    const res = await axios.post('/api/index.php?model=project&method=update', formData);
                    if (res.data && res.data.status === 'success') {
                        applyProjectVersionFromResponse(this.project, res.data);
                        this.project.custom_fields = JSON.stringify(updated);
                        if (typeof showMessage === 'function') showMessage('保存しました');
                    } else {
                        if (handleProjectVersionConflict(res.data, () => this.loadProject())) {
                            return;
                        }
                        showMessage(res.data?.message || res.data?.error || '更新に失敗しました。', true);
                    }
                } catch (err) {
                    console.error('Custom field save:', err);
                    showMessage(err.response?.data?.message || '更新に失敗しました。', true);
                } finally {
                    this.savingCustomFieldLabel = null;
                }
            },
            isCustomFieldCheckboxChecked(fieldLabel, opt) {
                const val = this.getCustomFieldValue(fieldLabel);
                return (val || '').split(',').map(s => s.trim()).includes(opt);
            },
            getCustomCheckboxOptions(field) {
                return (field && field.options != null ? String(field.options) : '')
                    .split(',')
                    .map(s => s.trim())
                    .filter(Boolean);
            },
            isAllCustomCheckboxSelected(field) {
                const opts = this.getCustomCheckboxOptions(field);
                if (!opts.length) return false;
                const arr = Array.isArray(field.valueArr) ? field.valueArr : [];
                return opts.every(o => arr.includes(o));
            },
            toggleAllCustomCheckbox(field) {
                const opts = this.getCustomCheckboxOptions(field);
                const checked = !this.isAllCustomCheckboxSelected(field);
                field.valueArr = checked ? opts.slice() : [];
                field.value = field.valueArr.join(',');
            },
            isAllCustomFieldCheckboxSelected(field) {
                const opts = this.getCustomCheckboxOptions(field);
                if (!opts.length) return false;
                return opts.every(o => this.isCustomFieldCheckboxChecked(field.label, o));
            },
            onCustomFieldSelectAllToggle(field) {
                const opts = this.getCustomCheckboxOptions(field);
                const checked = !this.isAllCustomFieldCheckboxSelected(field);
                this.updateCustomFieldValue(field.label, checked ? opts.join(',') : '');
            },
            onCustomFieldCheckboxChange(field, opt, checked) {
                const current = (this.getCustomFieldValue(field.label) || '').split(',').map(s => s.trim()).filter(Boolean);
                if (checked) {
                    if (!current.includes(opt)) current.push(opt);
                } else {
                    const i = current.indexOf(opt);
                    if (i !== -1) current.splice(i, 1);
                }
                this.updateCustomFieldValue(field.label, current.join(','));
            },
            async loadCompaniesByCategory() {
                if (!this.project.category_id) {
                    this.companies = [];
                    return;
                }
                const params = new URLSearchParams({
                    category_id: this.project.category_id
                });
                // if (this.project.department_id) {
                //     params.append('department_id', this.project.department_id);
                // }
                const res = await axios.get(`/api/index.php?model=customer&method=list_companies_by_category&${params.toString()}`);
                if (res.data && res.data.data) {
                    this.companies = res.data.data;
                }
            },
            async loadContactsByCompany() {
                if (!this.project.company_name) {
                    this.contacts = [];
                    return;
                }
                const params = new URLSearchParams({
                    company_name: this.project.company_name
                });
                // if (this.project.department_id) {
                //     params.append('department_id', this.project.department_id);
                // }
                const res = await axios.get(`/api/index.php?model=customer&method=list_contacts_by_company&${params.toString()}`);
                if (res.data && res.data.data) {
                    this.contacts = res.data.data;
                }
                
                // Mention functionality methods
                await this.loadMentionUsers();
            },
            hasProjectDateValue(value) {
                return !!(value && String(value).trim() !== '');
            },
            parseProjectDateTime(value) {
                if (!this.hasProjectDateValue(value)) return null;
                const parsed = parseProjectDateTimeInDisplayTz(value);
                return parsed ? parsed.toDate() : null;
            },
            syncCustomFieldDatePickers() {
                if (!this.customFields) return;
                this.customFields.forEach((field, idx) => {
                    if (field.type !== 'datetime') return;
                    const el = document.getElementById('custom_datetime_' + idx);
                    if (!el) return;
                    const fp = el._flatpickr;
                    if (fp && fp.selectedDates && fp.selectedDates.length > 0) {
                        field.value = fp.formatDate(fp.selectedDates[0], PROJECT_DATETIME_FLATPICKR_FORMAT);
                    } else if (fp && fp._input) {
                        field.value = String(fp._input.value || '').trim();
                    } else {
                        field.value = String(el.value || '').trim();
                    }
                    if (field.value) {
                        field._serverDatetime = fromProjectDateTimeInputValue(field.value);
                    } else {
                        field._serverDatetime = '';
                    }
                });
            },
            syncProjectDateFieldsFromPickers() {
                const fieldIds = {
                    start_date: 'start_date_picker',
                    end_date: 'end_date_picker',
                    caily_nouki: 'caily_nouki_picker',
                    guis_nouki: 'guis_nouki_picker',
                    actual_end_date: 'actual_end_date_picker'
                };
                Object.keys(fieldIds).forEach((key) => {
                    const el = document.getElementById(fieldIds[key]);
                    if (!el || !this.project) return;
                    const fp = el._flatpickr;
                    if (fp && fp.selectedDates && fp.selectedDates.length > 0) {
                        this.project[key] = fp.formatDate(fp.selectedDates[0], PROJECT_DATETIME_FLATPICKR_FORMAT);
                    } else if (fp && fp._input) {
                        this.project[key] = String(fp._input.value || '').trim();
                    } else {
                        this.project[key] = String(el.value || '').trim();
                    }
                    if (this._serverProjectDates) {
                        this._serverProjectDates[key] = this.project[key]
                            ? fromProjectDateTimeInputValue(this.project[key])
                            : '';
                    }
                });
                this.syncCustomFieldDatePickers();
            },
            validateProjectNoukiFields() {
                // 省エネ departments do not use the 納期 fields
                if (this.isEnergyDepartmentProject) return true;
                const errors = this.validationErrors;
                let isValid = true;
                // 省エネ departments do not use 担当 (hidden), so no 納期 becomes mandatory because of it
                const tantou = this.isEnergyDepartmentProject ? '' : (this.project.tantou || '').trim();
                const caily = (this.project.caily_nouki || '').trim();
                const guis = (this.project.guis_nouki || '').trim();
                const end = (this.project.end_date || '').trim();
                const showGuisFields = !this.isCailyBranchUser;
                const endFilled = showGuisFields && this.hasProjectDateValue(end);

                if (endFilled) {
                    if (tantou === 'CAILY' && !caily) {
                        errors.caily_nouki = '担当がCAILYの場合、CAILY納期は必須です';
                        isValid = false;
                    }
                    if (tantou === 'GUIS' && !guis) {
                        errors.guis_nouki = '担当がGUISの場合、GUIS納期は必須です';
                        isValid = false;
                    }
                }

                if (this.hasProjectDateValue(caily) && this.hasProjectDateValue(guis)) {
                    const cailyDate = this.parseProjectDateTime(caily);
                    const guisDate = this.parseProjectDateTime(guis);
                    if (cailyDate && guisDate && guisDate < cailyDate) {
                        const msg = 'GUIS納期はCAILY納期以降である必要があります';
                        errors.guis_nouki = msg;
                        if (!showGuisFields) {
                            errors.caily_nouki = msg;
                        }
                        isValid = false;
                    }
                }

                return isValid;
            },
            validateProjectForm() {
                this.syncProjectDateFieldsFromPickers();
                this.validationErrors = {
                    category_id: '',
                    company_name: '',
                    customer_id: '',
                    project_number: '',
                    name: '',
                    end_date: '',
                    caily_nouki: '',
                    guis_nouki: '',
                    yotei: '',
                    department_id: '',
                    status: '',
                    project_order_type: '',
                    tantou: ''
                };
                let valid = true;
                if (this.formCreateMode) {
                    if (!this.project.department_id) {
                        this.validationErrors.department_id = '部署は必須です';
                        valid = false;
                    }
                    if (!String(this.project.status || '').trim()) {
                        this.validationErrors.status = 'ステータスを選択してください';
                        valid = false;
                    }
                }
                // Fields a manager-only user cannot see are not checked
                if (!this.formManagerOnly) {
                    const orderType = this.projectOrderTypeTagify
                        ? (this.projectOrderTypeTagify.value || []).map(t => t.value).join(',')
                        : String(this.project.project_order_type || '');
                    if (!orderType.trim()) {
                        this.validationErrors.project_order_type = '受注形態は必須です';
                        valid = false;
                    }
                    // 担当 is not used by 省エネ departments and is read-only for CAILY branch users
                    if (!this.isEnergyDepartmentProject && !this.isCailyBranchUser
                        && !String(this.project.tantou || '').trim()) {
                        this.validationErrors.tantou = '担当は必須です';
                        valid = false;
                    }
                }
                // if (!this.project.category_id) {
                //     this.validationErrors.category_id = '顧客カテゴリーは必須です';
                //     valid = false;
                // }
                // if (!this.project.company_name) {
                //     this.validationErrors.company_name = '会社名は必須です';
                //     valid = false;
                // }
                // if (!this.project.customer_id) {
                //     this.validationErrors.customer_id = '担当者名は必須です';
                //     valid = false;
                // }
                // project_number は必須チェックを削除（フィールド削除に合わせて任意とする）
                if (!this.project.name) {
                    this.validationErrors.name = 'プロジェクト名は必須です';
                    valid = false;
                }
                if (this.project.start_date && this.project.end_date) {
                    const startDate = this.parseProjectDateTime(this.project.start_date);
                    const endDate = this.parseProjectDateTime(this.project.end_date);
                    if (startDate && endDate && startDate >= endDate) {
                        this.validationErrors.end_date = '期限日は開始日より後である必要があります';
                        valid = false;
                    }
                }

                if (!this.validateProjectNoukiFields()) {
                    valid = false;
                }

                if (typeof window.YoteiField !== 'undefined' && !window.YoteiField.isValid(this.yoteiDraft)) {
                    this.validationErrors.yotei = '予定工程の期間が正しくありません';
                    valid = false;
                }

                const gr = this.guisReceiverForm;
                if (gr && gr.enabled && !gr.useParent && !String(gr.userid || '').trim()) {
                    this.validationErrors.guis_receiver = 'GUIS受付者は必須です';
                    valid = false;
                }

                return valid;
            },

            // ---- GUIS受付者 (child projects) ----
            /**
             * Prepare the GUIS受付者 field for the loaded project: the building's receiver is
             * the default, and the child stores its own only when it differs.
             */
            async loadGuisReceiverForForm() {
                const form = { enabled: false, useParent: true, userid: '', parentUserid: '', parentName: '' };
                const project = this.project;
                const parentId = project ? parseInt(project.parent_project_id, 10) : 0;
                if (!project || !(parentId > 0)) {
                    this.guisReceiverForm = form;
                    return;
                }
                try {
                    const response = await axios.get('/api/index.php?model=parentproject&method=getById&id=' + parentId);
                    const parent = response.data && response.data.id ? response.data : ((response.data && response.data.data) || {});
                    form.parentUserid = String(parent.guis_receiver || '').trim();
                } catch (e) {
                    console.error('Error loading the building for GUIS受付者:', e);
                }
                const own = String(project.guis_receiver || '').trim();
                form.useParent = !own || own === form.parentUserid;
                form.userid = form.useParent ? '' : own;
                form.parentName = form.parentUserid ? await this.resolveGuisReceiverName(form.parentUserid) : '';
                form.enabled = true;
                this.guisReceiverForm = form;
            },
            async resolveGuisReceiverName(userid) {
                if (!userid) return '';
                try {
                    if (!this._guisReceiverUsers) {
                        const response = await axios.get('/api/index.php?model=user&method=searchMembers');
                        this._guisReceiverUsers = (response.data && response.data.data) || [];
                    }
                    const user = this._guisReceiverUsers.find((u) => String(u.userid) === String(userid));
                    return user ? user.realname : userid;
                } catch (e) {
                    return userid;
                }
            },
            onUseParentGuisReceiverChange() {
                const form = this.guisReceiverForm;
                if (form.useParent) {
                    this.destroyGuisReceiverSelect();
                    form.userid = '';
                } else {
                    // start from the building's receiver, as the building forms do
                    form.userid = form.parentUserid || '';
                    this.$nextTick(() => this.initGuisReceiverSelect());
                }
            },
            getGuisReceiverSelect() {
                return $('#pf_guis_receiver');
            },
            destroyGuisReceiverSelect() {
                const $el = this.getGuisReceiverSelect();
                if ($el.length && $el.data('select2')) {
                    $el.select2('destroy');
                }
            },
            initGuisReceiverSelect() {
                this.destroyGuisReceiverSelect();
                const form = this.guisReceiverForm;
                const $el = this.getGuisReceiverSelect();
                if (!$el.length || !form || !form.enabled || form.useParent) return;
                const $dialog = $el.closest('.modal');
                $el.select2({
                    placeholder: '選択してください',
                    dropdownParent: $dialog.length ? $dialog : $el.parent(),
                    allowClear: true,
                    minimumResultsForSearch: 0,
                    width: '100%',
                    ajax: {
                        url: '/api/index.php?model=user&method=searchMembers',
                        dataType: 'json',
                        delay: 250,
                        data(params) {
                            return { search: params.term, page: params.page || 1 };
                        },
                        processResults(data) {
                            return {
                                results: (data.data || []).map((item) => ({ id: item.userid, text: item.realname }))
                            };
                        }
                    }
                }).on('select2:select', (e) => {
                    form.userid = e.params.data.id;
                }).on('select2:clear', () => {
                    form.userid = '';
                });
                if (form.userid) {
                    this.resolveGuisReceiverName(form.userid).then((name) => {
                        if (!$el.data('select2')) return;
                        $el.empty().append(new Option(name, form.userid, true, true)).trigger('change');
                    });
                }
            },
            /** Value for the guis_receiver key: '' means "use the building's". */
            resolveGuisReceiverForSave() {
                const form = this.guisReceiverForm;
                if (!form || form.useParent) return '';
                return String(form.userid || '');
            },
        }
    };
})();
