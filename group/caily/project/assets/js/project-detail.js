const { createApp } = Vue;

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

window.handleProjectVersionConflict = handleProjectVersionConflict;
window.appendPaymentVersionToFormData = appendPaymentVersionToFormData;
window.applyPaymentVersionFromResponse = applyPaymentVersionFromResponse;

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

const vueApp = createApp({
    // 省エネ fields edit mode (省エネ向け資料 / 申請提出先の審査機関 / BELSのZEH等級)
    // ProjectFormMixin (project-form-mixin.js): the form fields, validation, widgets and save logic,
    // shared with the edit / create dialog. This file keeps what only the detail page needs.
    mixins: [window.ProjectDetailEnergyMixin, window.ProjectFormMixin],
    data() {
        return {
            permission: {},
            projectId: typeof PROJECT_ID !== 'undefined' ? PROJECT_ID : this.getProjectIdFromUrl(),
            project: null,
            parentSiblingProjects: [],
            savingEnergyDrawingShare: false,
            department: null,
            managers: [],
            members: [],
            tasks: [],
            team_list: [],
            isEditMode: false,
            originalProject: null,
            stats: {
                totalTasks: 0,
                completedTasks: 0,
                timeTracked: 0,
                totalDays: 0,
                totalWorkload: 0
            },
            workloadByKind: [],
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
            estimateStatuses: [
                { value: '未発行', label: '未発行', color: 'secondary' },
                { value: '発行済み', label: '発行済み', color: 'info' },
                { value: '承認済み', label: '承認済み', color: 'success' },
                { value: '却下', label: '却下', color: 'danger' },
                { value: '調整', label: '調整', color: 'warning' }
            ],
            invoiceStatuses: [
                { value: '未発行', label: '未発行', color: 'secondary' },
                { value: '発行済み', label: '発行済み', color: 'info' },
                { value: '承認済み', label: '承認済み', color: 'success' },
                { value: '却下', label: '却下', color: 'danger' },
                { value: '調整', label: '調整', color: 'warning' }
            ],
            businessEstimateStatuses: [
                { value: '未発行', label: '未発行', color: 'secondary' },
                { value: '見積作成中', label: '見積作成中', color: 'primary' },
                { value: '発行済', label: '発行済', color: 'success' },
                { value: '無償', label: '無償', color: 'info' },
            ],
            businessInvoiceStatuses: [
                { value: '未発行', label: '未発行', color: 'secondary' },
                { value: '請求準備', label: '請求準備', color: 'warning' },
                { value: '発行済', label: '発行済', color: 'success' },
                { value: '無償', label: '無償', color: 'info' },
            ],
            paymentStatuses: [
                { value: '未入金', label: '未入金', color: 'secondary' },
                { value: '入金済', label: '入金済', color: 'success' },
                { value: '入金拒否', label: '入金拒否', color: 'danger' },
            ],
            categories: [],
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
            showMemberModal: false,
            memberSelectType: '', // 'manager' or 'member'
            memberSelected: [],
            allUsers: [], // all users for selection
            departmentUsers: [],
            membersTagify: null,
            prevTeamIds: [],
            managerTagify: null,
            quillInstance: null,
            projectOrderTypeTagify: null,
            buildingBranchTagify: null,
            customFields: [],
            departmentCustomFieldSets: [],
            commentsMountReady: false,
            // コメント section: collapsed while the project has no comment (checked once on load)
            commentsExpanded: false,
            commentsChecked: false,
            commentsHaveAny: false,
            _serverProjectDates: null,
            _serverBusinessDocumentDates: null,
            // Danh sách các tỉnh/thành phố của Nhật Bản
            japanPrefectures: [
                '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
                '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
                '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
                '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
                '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
                '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
                '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'
            ],
            // Notes functionality
            notes: [],
            showNoteModal: false,
            isNoteEditMode: false,
            NOTE_LABEL_OTHER: (typeof NOTE_LABEL_OTHER !== 'undefined' ? NOTE_LABEL_OTHER : '__other__'),
            noteLabels: [],
            noteFilterLabel: '',
            noteFilterType: '',
            noteFilterVisibility: '', // '' | all | members | self
            noteFilterPin: '',        // '' | everyone | self | none
            editingNote: {
                id: null,
                title: '',
                content: '',
                is_important: false,
                is_pinned_self: false,
                needs_confirmation: false,
                display_column: '',
                label: '',
                labelSelect: '',
                labelOther: '',
                bg_color: '',
                visibility: 'all',
                user_id: null
            },
            // メモボード: 背景色 palette keys (match ProjectNote::BG_COLORS) and 公開範囲
            noteBgColorOptions: [
                { value: '', text: 'デフォルト' },
                { value: 'coral', text: 'コーラル' },
                { value: 'peach', text: 'ピーチ' },
                { value: 'sand', text: 'サンド' },
                { value: 'mint', text: 'ミント' },
                { value: 'sage', text: 'セージ' },
                { value: 'fog', text: 'フォグ' },
                { value: 'storm', text: 'ストーム' },
                { value: 'dusk', text: 'ダスク' },
                { value: 'blossom', text: 'ブロッサム' },
                { value: 'clay', text: 'クレイ' },
                { value: 'chalk', text: 'チョーク' }
            ],
            noteFilterVisibilityOptions: [
                { value: '', text: 'すべて' },
                { value: 'all', text: '全員', icon: 'fa-globe' },
                { value: 'members', text: '案件メンバー', icon: 'fa-users' },
                { value: 'self', text: '自分のみ', icon: 'fa-lock' }
            ],
            noteFilterPinOptions: [
                { value: '', text: 'すべて' },
                { value: 'everyone', text: 'ピン（全員）', icon: 'fa-thumb-tack text-danger' },
                { value: 'self', text: 'ピン（自分）', icon: 'fa-bookmark text-primary' },
                { value: 'none', text: 'ピンなし' }
            ],
            noteVisibilityOptions: [
                { value: 'all', text: '全員', icon: 'fa-globe' },
                { value: 'members', text: '案件メンバー', icon: 'fa-users' },
                { value: 'self', text: '自分のみ', icon: 'fa-lock' }
            ],
            _detailNotesSortable: null,
            _detailNotesSortablePending: false,
            quillNoteInstance: null,
            quillNoteContent: '',
            // Project status update loading
            isUpdatingStatus: false,
            businessDocumentSaveStatus: null,
            businessDocumentSaveHideTimer: null,
            businessDocumentError: '',
            businessDocumentDirty: false,
            savingProject: false,
            // Debounce timer for amount updates
            amountUpdateTimer: null,
            businessDocumentUpdateTimer: null,
            _bdSuppressAutoSave: false,
            tagsUpdateTimer: null,
            projectTagsTagify: null,
            // Quill editor content storage (separate from Vue reactivity)
            quillContent: '',
            userPermissions: null,
            // mention-related variables removed
            logs: [], // Thêm biến lưu log lịch sử
            showBusinessDocumentLogModal: false,
            showGeneralLogModal: false,
            logsLoading: false,
            logsLoaded: false,
            // Current user data
            currentUser: {
                userid: typeof USER_ID !== 'undefined' ? USER_ID : null,
                realname: '',
                user_image: null,
            },
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
            timeRemainingTimer: null,
            autoRefreshTimer: null,
            savingCustomFieldLabel: null,
        }
    },
    computed: {
        hasNonResidentialType() {
            const type2 = this.project && this.project.type2 ? String(this.project.type2) : '';
            return type2
                .split(',')
                .map(function(v) { return v.trim(); })
                .filter(Boolean)
                .includes('非住宅');
        },
        hasSharedHousingType() {
            const type2 = this.project && this.project.type2 ? String(this.project.type2) : '';
            return type2
                .split(',')
                .map(function(v) { return v.trim(); })
                .filter(Boolean)
                .includes('共同');
        },
        /** Note type 1 is CAILYメモ; 省エネ departments call it 入力注意点項目 (same name as the list column). */
        cailyNoteLabel() {
            const label = this.isEnergyDepartmentProject ? '入力注意点項目' : 'CAILYメモ';
            return typeof translateText === 'function' ? translateText(label) : label;
        },
        nonResidentialMainUseLabel() {
            if (!this.project || !this.project.type3) {
                return '';
            }
            const code = String(this.project.type3);
            const options = [
                { value: '08340', label: '08340 工場' },
                { value: '08440', label: '08440 物品販売店舗' },
                { value: '08450', label: '08450 飲食店' },
                { value: '08470', label: '08470 事務所' },
                { value: '08510', label: '08510 倉庫業を営む倉庫' },
                { value: '08520', label: '08520 倉庫業を営まない倉庫' },
                { value: '08400', label: '08400 ホテル・旅館' },
                { value: '08260', label: '08260 病院' },
                { value: '08550', label: '08550 集会場・公会堂' },
                { value: '08990', label: '08990 その他' }
            ];
            const found = options.find(function(opt) { return opt.value === code; });
            return found ? found.label : code;
        },
        selectedFeatureLabels() {
            const featureOptions = [
                '施主宅付き',
                'マルチルームあり',
                '店舗併用住宅',
                '事務所併用住宅',
                '複合用途',
                '共同住宅（共用部計算なし）',
                '補助金案件',
                '増築',
                '用途変更'
            ];
            const sharedLabel = '共同住宅（共用部計算なし）';
            const selected = String(this.project && this.project.features ? this.project.features : '')
                .split(',')
                .map(function(v) { return v.trim(); })
                .filter(Boolean);
            const self = this;
            return featureOptions.filter(function(opt) {
                if (selected.indexOf(opt) === -1) return false;
                if (opt === sharedLabel && !self.hasSharedHousingType) return false;
                return true;
            });
        },
        isPeriodUndecided() {
            const project = this.project;
            if (!project) return false;
            const skipStatuses = ['completed', 'cancelled', 'paused', 'deleted'];
            if (skipStatuses.includes(project.status)) return false;
            return !(isFilledProjectDeadline(project.guis_nouki)
                || isFilledProjectDeadline(project.caily_nouki)
                || isFilledProjectDeadline(project.end_date));
        },
        parentRequestTypes() {
            const raw = (this.project && this.project.parent_requests) ? String(this.project.parent_requests) : '';
            return raw.split(',').map(r => r.trim()).filter(Boolean);
        },
        /** Child projects of the same parent that belong to other departments. */
        otherDepartmentSiblingProjects() {
            if (!this.project || !this.project.parent_project_id) {
                return [];
            }
            if (!Array.isArray(this.parentSiblingProjects) || !this.parentSiblingProjects.length) {
                return [];
            }
            const currentId = String(this.project.id || '');
            const currentDept = String(this.project.department_id || '');
            return this.parentSiblingProjects.filter((p) => {
                if (!p) return false;
                if (String(p.id) === currentId) return false;
                if (String(p.status || '').toLowerCase() === 'deleted') return false;
                if (currentDept && String(p.department_id || '') === currentDept) return false;
                return true;
            }).sort((a, b) => {
                const da = String(a.department_name || '');
                const db = String(b.department_name || '');
                if (da !== db) return da.localeCompare(db, 'ja');
                return (Number(a.id) || 0) - (Number(b.id) || 0);
            });
        },
        energyEstimateFeeRows() {
            const rows = (window.EnergyFeePayment && window.EnergyFeePayment.FEE_ROWS) || [];
            return rows.filter((r) => r.kind === 'estimate');
        },
        energyInvoiceFeeRows() {
            const rows = (window.EnergyFeePayment && window.EnergyFeePayment.FEE_ROWS) || [];
            return rows.filter((r) => r.kind === 'invoice');
        },
        /** 省エネ fee status list: base + 対象外 */
        businessEnergyEstimateStatuses() {
            const extra = (window.EnergyFeePayment && window.EnergyFeePayment.ENERGY_EXTRA_STATUSES) || [];
            return this.businessEstimateStatuses.concat(extra);
        },
        businessEnergyInvoiceStatuses() {
            const extra = (window.EnergyFeePayment && window.EnergyFeePayment.ENERGY_EXTRA_STATUSES) || [];
            return this.businessInvoiceStatuses.concat(extra);
        },
        /** Default tasks with show_on_project for this project's department. */
        namedDefaultTaskRows() {
            const p = this.project || {};
            const cols = Array.isArray(p.named_default_task_columns) ? p.named_default_task_columns : [];
            const map = (p.named_default_task_summaries && typeof p.named_default_task_summaries === 'object')
                ? p.named_default_task_summaries
                : {};
            if (cols.length) {
                return cols.map((c) => {
                    const title = c && c.title ? String(c.title) : '';
                    let task = title && map[title] ? map[title] : null;
                    if (!task && title === '計算確認') task = p.keisan_kakunin_task || null;
                    if (!task && title === '申請代行') task = p.shinsei_daikou_task || null;
                    return {
                        key: (c && c.key) || title,
                        title: title,
                        task: task
                    };
                }).filter((r) => r.title);
            }
            // Legacy fallback
            const legacy = [];
            if (p.keisan_kakunin_task) {
                legacy.push({ key: 'keisan_kakunin', title: '計算確認', task: p.keisan_kakunin_task });
            }
            if (p.shinsei_daikou_task) {
                legacy.push({ key: 'shinsei_daikou', title: '申請代行', task: p.shinsei_daikou_task });
            }
            return legacy;
        },
        /** Same-parent has active 省エネ sibling (or flag from API). */
        hasEnergyDrawingShareContext() {
            if (!this.project) return false;
            if (this.isEnergyDepartmentProject) return true;
            if (this.project.has_energy_sibling === 1
                || this.project.has_energy_sibling === true
                || this.project.has_energy_sibling === '1') {
                return true;
            }
            if (window.EnergyDrawingShare && window.EnergyDrawingShare.hasEnergySavingSibling) {
                return window.EnergyDrawingShare.hasEnergySavingSibling(
                    this.parentSiblingProjects,
                    this.project.id
                );
            }
            const siblings = Array.isArray(this.parentSiblingProjects) ? this.parentSiblingProjects : [];
            return siblings.some((p) => p
                && String(p.department_name || '').trim().indexOf('省エネ') !== -1
                && String(p.status || '') !== 'cancelled'
                && String(p.status || '').toLowerCase() !== 'deleted');
        },
        /** 意匠/設備/技術課設備: show editable 省エネ図面共有 box (not for 省エネ itself). */
        showEnergyDrawingShareEditBox() {
            if (!this.project || this.isEnergyDepartmentProject) return false;
            if (!this.hasEnergyDrawingShareContext) return false;
            if (window.EnergyDrawingShare && window.EnergyDrawingShare.isShareSourceDepartment) {
                return window.EnergyDrawingShare.isShareSourceDepartment(this.project.department_name);
            }
            const dept = String(this.project.department_name || '').trim();
            return dept === '意匠設計' || dept === '設備設計' || dept === '技術課設備';
        },
        energyDrawingShareReasonOptions() {
            return (window.EnergyDrawingShare && window.EnergyDrawingShare.REASON_OPTIONS)
                ? window.EnergyDrawingShare.REASON_OPTIONS
                : [];
        },
        isManager() {
            if(USER_ROLE == `administrator`) return true;
            if (!this.managers) return false;
            return this.managers.some(m => String(m.user_id) === String(USER_AUTH_ID)) || this.permission.can_manage_project || (this.permission.rule && this.permission.rule.project_edit == 1);
        },
        selectedCustomFieldSet() {
            // Always return null since we now use all sets from department
            return null;
        },
        /** Predefined labels for select (exclude その他 which is always the free-text option) */
        noteLabelSelectOptions() {
            const otherVal = this.NOTE_LABEL_OTHER;
            return (this.noteLabels || []).filter(function(l) {
                return l && l.name && l.name !== 'その他' && l.name !== otherVal;
            });
        },
        /** Unique labels present on current notes (for filter dropdown) */
        noteFilterLabelOptions() {
            const seen = {};
            const out = [];
            (this.notes || []).forEach(function(n) {
                const name = n && n.label != null ? String(n.label).trim() : '';
                if (!name || seen[name]) return;
                seen[name] = true;
                out.push(name);
            });
            out.sort(function(a, b) {
                return a.localeCompare(b, 'ja');
            });
            return out;
        },
        /** Any board filter set — drag reorder is off while filtering (would scramble sort_order) */
        noteFiltersActive() {
            return !!(this.noteFilterLabel || this.noteFilterType !== ''
                || this.noteFilterVisibility || this.noteFilterPin);
        },
        filteredNotes() {
            const labelFilter = this.noteFilterLabel;
            const visibilityFilter = this.noteFilterVisibility;
            const pinFilter = this.noteFilterPin;
            const typeFilter = this.noteFilterType === '' || this.noteFilterType === null || this.noteFilterType === undefined
                ? ''
                : String(this.noteFilterType);
            return (this.notes || []).filter(function(note) {
                if (labelFilter === '__none__') {
                    if (note.label != null && String(note.label).trim() !== '') return false;
                } else if (labelFilter) {
                    if (String(note.label || '').trim() !== labelFilter) return false;
                }
                if (typeFilter !== '') {
                    const t = Number(note.needs_confirmation) || 0;
                    if (String(t) !== typeFilter) return false;
                }
                if (visibilityFilter && (note.visibility || 'all') !== visibilityFilter) return false;
                if (pinFilter) {
                    const everyone = note.is_important == 1;
                    const self = note.is_pinned_self == 1;
                    if (pinFilter === 'everyone' && !everyone) return false;
                    if (pinFilter === 'self' && !self) return false;
                    if (pinFilter === 'none' && (everyone || self)) return false;
                }
                return true;
            });
        },
        /** Options cho select "表示列" (display_column): cột danh sách project + custom fields. Loại trùng tên khác suffix 状況 */
        noteDisplayColumnOptions() {
            function normLabel(t) { return (t || '').replace(/状況$/, ''); }
            const hiddenForCaily = this.isCailyBranchUser
                ? { guis_nouki: !this.canViewEndDate, end_date: !this.canViewEndDate }
                : {};
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
            (this.allDepartmentCustomFieldSets || []).forEach(function(set) {
                if (set.fields && Array.isArray(set.fields)) {
                    set.fields.forEach(function(f) {
                        if (f && f.label && f.label.trim()) {
                            var label = f.label.trim();
                            if (isCustomFieldCompositeCompanionLabel(label)) return;
                            var val = 'custom:' + label;
                            var n = normLabel(label);
                            if (!seen[val] && !seenNorm[n]) {
                                seen[val] = true;
                                seenNorm[n] = true;
                                list.push({ value: val, text: label });
                            }
                        }
                    });
                }
            });
            return list;
        },
        canViewProject() {
            // Administrator can always view
            if (typeof USER_ROLE !== 'undefined' && USER_ROLE === 'administrator') return true;
            
            // Check permission from API
            if (this.permission && this.permission.is_member) return true;
            
            // Check if user is creator of the project
            if (this.project && this.project.created_by && typeof USER_ID !== 'undefined') {
                if (String(this.project.created_by) === String(USER_ID)) return true;
            }
            
            // Check if user is manager or member
            if (this.managers && typeof USER_AUTH_ID !== 'undefined') {
                if (this.managers.some(m => String(m.user_id) === String(USER_AUTH_ID))) return true;
            }
            if (this.members && typeof USER_AUTH_ID !== 'undefined') {
                if (this.members.some(m => String(m.user_id) === String(USER_AUTH_ID))) return true;
            }
            
            // Check if user has project_manager or project_director permission
            if (this.permission && this.permission.rule) {
                const rule = this.permission.rule;
                if (rule.project_manager == 1) return true;
                if (rule.project_director_stat == 1 || rule.project_director_view == 1 || rule.project_director_edit == 1) return true;
            }
            
            // Check if user is in the same department (even if not a member)
            if (this.permission && this.permission.is_in_department) return true;
            
            return false;
        },
        canAddNote() {
            return this.permission.can_manage_project || this.permission.is_member;
        },
        isProjectMember() {
            // Check if current user is already a member or manager
            if (typeof USER_AUTH_ID === 'undefined') return false;
            
            if (this.managers && this.managers.some(m => String(m.user_id) === String(USER_AUTH_ID))) return true;
            if (this.members && this.members.some(m => String(m.user_id) === String(USER_AUTH_ID))) return true;
            
            return false;
        },
        canJoinProject() {
            // User can join if:
            // 1. They are not yet a member
            // 2. They are in the same department as the project
            if (this.isProjectMember) return false;

            // check if administrator
            if (typeof USER_ROLE !== 'undefined' && USER_ROLE === 'administrator') return true;
            
            // Check if user is in the same department as the project
            if (!this.project || !this.project.department_id) return false;
            
            // User must be in the same department
            return this.permission && this.permission.is_in_department == 1;
        },
        canAddProject() {
            return this.permission.can_manage_project || (this.permission.rule && this.permission.rule.project_add == 1);
        },
        canDeleteProject() {
            return this.permission.can_manage_project || (this.permission.rule && this.permission.rule.project_delete == 1);
        },
        canCommentProject() {
            return this.permission.can_manage_project || (this.permission.rule && this.permission.rule.project_comment == 1);
        },
        canDocumentProject() {
            return this.canViewBusinessDocuments;
        },
        canViewBusinessDocuments() {
            if (this.isAdministrator()) return true;
            if (!this.permission || !this.permission.rule) return false;
            const rule = this.permission.rule;
            return rule.project_director_stat == 1
                || rule.project_director_view == 1
                || rule.project_director_edit == 1
                || rule.project_director == 1;
        },
        canViewDrawings() {
            return this.isAdministrator() || this.canViewBusinessDocuments;
        },
        canEditBusinessDocuments() {
            if (this.isAdministrator()) return true;
            if (!this.permission || !this.permission.rule) return false;
            return this.permission.rule.project_director_edit == 1;
        },
        sortedGeneralLogs() {
            if (!this.logs) return [];
            return [...this.logs]
                .filter((log) => !this.isBusinessDocumentLog(log))
                .sort((a, b) => (b.time > a.time ? 1 : -1));
        },
        sortedBusinessDocumentLogs() {
            if (!this.logs) return [];
            return [...this.logs]
                .filter((log) => this.isBusinessDocumentLog(log))
                .sort((a, b) => (b.time > a.time ? 1 : -1));
        },
        sortedLogs() {
            return this.sortedGeneralLogs;
        },
    },
    methods: {
        /** 編集: the project is edited in the shared form dialog (project-form-modal.js); this page stays read-only. */
        /** A project row (e.g. one of the same building) that belongs to a 省エネ department. */
        isEnergyDepartmentRow(row) {
            const name = String((row && row.department_name) || '').trim();
            if (window.EnergyDrawingShare && typeof window.EnergyDrawingShare.isEnergyDepartmentName === 'function') {
                return window.EnergyDrawingShare.isEnergyDepartmentName(name);
            }
            return name.indexOf('省エネ') !== -1;
        },
        openEditForm() {
            if (!this.canEditProject) {
                showMessage('管理者のみプロジェクトを編集できます。', true);
                return;
            }
            if (typeof window.openProjectFormModal !== 'function') {
                // dialog script missing: fall back to editing in the page
                this.toggleEditMode();
                return;
            }
            window.openProjectFormModal({
                projectId: this.projectId,
                onSaved: () => {
                    this.loadProject();
                    this.refreshLogsIfOpen();
                }
            });
        },
        // 建物情報: shared building modal (parent_project/assets/js/parent-project-quick-modal.js)
        openParentProjectInfoModal() {
            if (!this.project || !this.project.parent_project_id || typeof window.openParentProjectModal !== 'function') {
                return;
            }
            window.openParentProjectModal(this.project.parent_project_id, {
                mode: 'view',
                onSaved: () => {
                    // Building data is copied into this.project on load: reload it (not while this project is being edited)
                    if (!this.isEditMode) {
                        this.loadProject();
                    }
                }
            });
        },
        hasDirectorPermission(field) {
            if (this.isAdministrator()) return true;
            if (!this.permission || this.permission.length === 0) return false;
            return this.permission.some((rule) => rule[field] === '1' || rule[field] === 1);
        },
        getProjectIdFromUrl() {
            // Lấy project ID từ URL nếu không có biến PROJECT_ID
            const urlParams = new URLSearchParams(window.location.search);
            const id = urlParams.get('id');
            if (id) return parseInt(id);
            
            // Hoặc lấy từ pathname
            const pathMatch = window.location.pathname.match(/\/project\/detail\.php\?id=(\d+)/);
            if (pathMatch) return parseInt(pathMatch[1]);
            
            // Fallback: lấy từ URL hiện tại
            const currentUrl = window.location.href;
            const urlMatch = currentUrl.match(/[?&]id=(\d+)/);
            if (urlMatch) return parseInt(urlMatch[1]);
            
            console.error('Could not determine project ID from URL');
            return null;
        },

        /** Opens the section on load when the project has a comment (or the URL points at one). */
        async checkCommentsPresence() {
            const linkedComment = String(window.location.hash || '').startsWith('#comment-');
            try {
                const res = await axios.get(`/api/index.php?model=project&method=getLatestCommentInfo&project_id=${this.projectId}`);
                this.commentsHaveAny = !!(res.data && res.data.latest_comment_id);
            } catch (e) {
                // unknown: show the section open, as before
                this.commentsHaveAny = true;
            }
            this.commentsChecked = true;
            this.commentsExpanded = this.commentsHaveAny || linkedComment;
        },
        toggleCommentsSection() {
            this.commentsExpanded = !this.commentsExpanded;
            if (this.commentsExpanded) {
                this.commentsMountReady = true;
            }
        },
        /** D6: mount comment-component after idle or when section nears viewport. */
        async armCommentsLazyMount() {
            if (this.commentsMountReady || this._commentsArmed || !this.canCommentProject) return;
            this._commentsArmed = true;
            await this.checkCommentsPresence();
            // No comment yet: stay collapsed; the component mounts when the user opens the section.
            if (!this.commentsExpanded) return;
            const mount = () => {
                if (this.commentsMountReady) return;
                this.commentsMountReady = true;
            };
            this.$nextTick(() => {
                const el = this.$refs.commentsSection;
                if (el && typeof IntersectionObserver !== 'undefined') {
                    const io = new IntersectionObserver((entries) => {
                        if (entries.some((e) => e.isIntersecting)) {
                            io.disconnect();
                            mount();
                        }
                    }, { rootMargin: '240px' });
                    io.observe(el);
                    this._commentsIo = io;
                }
                if (typeof requestIdleCallback === 'function') {
                    requestIdleCallback(() => mount(), { timeout: 4000 });
                } else {
                    setTimeout(mount, 2000);
                }
            });
        },
        
        
        async loadProject(options = {}) {
            const preserveBusinessDocs = options.preserveBusinessDocs !== false
                && this.hasPendingBusinessDocumentChanges();
            const businessDocSnapshot = preserveBusinessDocs ? this.getBusinessDocumentSnapshot() : null;

            try {
                const response = await axios.get(`/api/index.php?model=project&method=getById&id=${this.projectId}`);
                this.project = response.data;
                if (this.project) {
                    this.project.version = normalizeProjectVersion(this.project.version);
                    this.project.payment_version = normalizeProjectVersion(this.project.payment_version);
                    // The API entity-encodes every value. These plain-text fields are only shown
                    // through Vue text bindings and form inputs (never as HTML), so give them
                    // their real text: otherwise "A & B" is displayed and re-saved as "A &amp; B".
                    ['name', 'project_number', 'tags', 'project_order_type', 'payment_note', 'energy_drawing_share_note'].forEach((field) => {
                        const value = this.project[field];
                        if (typeof value === 'string' && value.indexOf('&') !== -1) {
                            this.project[field] = this.decodeHtmlEntities(value);
                        }
                    });
                }
                // Cho phép AI lấy dữ liệu dự án hiện tại đang xem
                if (typeof window !== 'undefined' && this.project) {
                    window.__chatPageContext = window.__chatPageContext || {};
                    window.__chatPageContext.project_id = this.projectId;
                    window.__chatPageContext.page = 'project_detail';
                    window.__chatPageContext.page_project = this.project;
                }

                // Khởi tạo trạng thái CAILY納期状況 / GUIS納期状況 từ cột riêng trong DB
                this.project.caily_nouki_status = this.project.caily_nouki_status || '';
                this.project.guis_nouki_status = this.project.guis_nouki_status || '';
                this.project.energy_drawing_share_status = this.project.energy_drawing_share_status || '';
                this.project.energy_drawing_share_reason = this.project.energy_drawing_share_reason || '';
                this.project.energy_drawing_share_note = this.project.energy_drawing_share_note || '';
                this.syncYoteiDraftFromProject();

                if (businessDocSnapshot) {
                    this.applyBusinessDocumentSnapshot(businessDocSnapshot);
                } else {
                    this.normalizeBusinessDocumentFields();
                }

                this.calculateStats();

                // D1: secondary loads in parallel after getById
                const secondary = [
                    this.loadTaskWorkloadStats(),
                    this.loadMembers(),
                ];
                if (this.project.parent_project_id) {
                    secondary.push(this.loadParentProjectInfo());
                }
                if (this.project.teams) {
                    secondary.push(this.loadTeamListByIds(this.project.teams));
                } else {
                    this.project.team_list = [];
                }
                await Promise.all(secondary);

                // Ensure Tagify is updated after loading project and team_list
                this.$nextTick(() => { 
                    //this.initTagify(); 
                    this.setConnectedUsers();
                    this.initVietnamTimeTooltips();
                    this.initBusinessDocumentDatePickers();
                });
                
            } catch (error) {
                console.error('Error loading project:', error);
                // The background refresh must not raise a blocking dialog every minute
                // (offline laptop, expired session); only a load the user started reports it.
                if (!options.silent) {
                    alert('プロジェクトの読み込みに失敗しました。');
                }
            }
        },
        
        async toggleFavorite() {
            if (!this.project || !this.project.id) return;
            
            try {
                const formData = new FormData();
                formData.append('project_id', this.project.id);
                
                const response = await axios.post('/api/index.php?model=project&method=toggleFavorite', formData);
                
                if (response.data && response.data.status === 'success') {
                    // Update the project's favorite status (convert boolean to number for consistency)
                    this.project.is_favorite = response.data.is_favorite ? 1 : 0;
                } else {
                    if (typeof showMessage === 'function') {
                        showMessage(response.data?.message || '操作に失敗しました。', true);
                    } else {
                        alert(response.data?.message || '操作に失敗しました。');
                    }
                }
            } catch (error) {
                console.error('Error toggling favorite:', error);
                if (typeof showMessage === 'function') {
                    showMessage('操作に失敗しました。', true);
                } else {
                    alert('操作に失敗しました。');
                }
            }
        },
        
        async loadParentProjectInfo() {
            try {
                const rawChildCustomer = {
                    customer_id: this.project.customer_id,
                    company_name: this.project.company_name || '',
                    branch_name: this.project.branch_name || '',
                    contact_name: this.project.contact_name || '',
                    guis_receiver: this.project.guis_receiver || ''
                };
                const parentId = this.project.parent_project_id;

                // D2: parent + siblings in one round (no searchMembers ×2)
                const [parentRes, childrenRes] = await Promise.all([
                    axios.get(`/api/index.php?model=parentproject&method=getById&id=${parentId}`),
                    axios.get(`/api/index.php?model=parentproject&method=getChildProjects&parent_project_id=${parentId}`)
                        .catch((childrenErr) => {
                            console.error('Error loading sibling projects for request fulfillment:', childrenErr);
                            return { data: [] };
                        }),
                ]);
                const parentProject = parentRes.data || {};

                const parentCustomerId = String(parentProject.customer_id || '').trim();
                const childCustomerId = String(rawChildCustomer.customer_id || '').trim();
                this.project.show_child_customer_info = childCustomerId !== '' && childCustomerId !== parentCustomerId;
                if (this.project.show_child_customer_info) {
                    this.project.child_customer_company = rawChildCustomer.company_name;
                    this.project.child_customer_branch = rawChildCustomer.branch_name;
                    this.project.child_customer_contact = rawChildCustomer.contact_name;
                } else {
                    this.project.child_customer_company = '';
                    this.project.child_customer_branch = '';
                    this.project.child_customer_contact = '';
                }

                const parentGuisReceiver = String(parentProject.guis_receiver || '').trim();
                const childGuisReceiver = String(rawChildCustomer.guis_receiver || '').trim();
                this.project.show_child_guis_receiver = childGuisReceiver !== '' && childGuisReceiver !== parentGuisReceiver;
                if (this.project.show_child_guis_receiver) {
                    this.project.child_guis_receiver_userid = childGuisReceiver;
                } else {
                    this.project.child_guis_receiver_userid = '';
                    this.project.child_guis_receiver_display_name = '';
                }

                // Copy parent project information to child project
                this.project.parent_project_branch_name = parentProject.branch_name || '';
                this.project.project_branch_name = rawChildCustomer.branch_name || '';
                this.project.company_name = parentProject.company_name;
                this.project.branch_name = parentProject.branch_name;
                this.project.contact_name = parentProject.contact_name; // 担当様 from parent project
                this.project.building_name = parentProject.project_name; // お施主様名 from parent project
                this.project.building_number = parentProject.construction_number;
                this.project.building_size = parentProject.scale;
                this.project.building_type = parentProject.type1;
                this.project.building_branch = parentProject.construction_branch;
                this.project.parent_request_date = parentProject.request_date || '';
                this.project.parent_project_number = parentProject.project_number || '';
                this.project.type1 = parentProject.type1;
                this.project.type2 = parentProject.type2;
                this.project.type3 = parentProject.type3 || '';
                this.project.features = parentProject.features || '';

                // Additional fields from parent project
                this.project.guis_receiver = parentProject.guis_receiver; // GUIS　受付者
                this.project.structural_office = parentProject.structural_office; // 構造事務所
                this.project.materials = parentProject.materials; // お客様提供資料
                this.project.materials_memo = parentProject.materials_memo || '';
                this.project.parent_requests = parentProject.requests || ''; // 依頼

                this.parentSiblingProjects = Array.isArray(childrenRes.data) ? childrenRes.data : [];
                if (window.EnergyDrawingShare) {
                    this.project.has_energy_sibling = window.EnergyDrawingShare.hasEnergySavingSibling(
                        this.parentSiblingProjects,
                        this.project.id
                    );
                }

                const userids = [];
                if (parentGuisReceiver) userids.push(parentGuisReceiver);
                if (this.project.show_child_guis_receiver && childGuisReceiver) userids.push(childGuisReceiver);
                const uniqueUserids = [...new Set(userids)];

                const followUps = [];
                if (uniqueUserids.length > 0) {
                    followUps.push(
                        axios.get(
                            `/api/index.php?model=user&method=getByUserids&userids=${encodeURIComponent(uniqueUserids.join(','))}`
                        )
                    );
                } else {
                    followUps.push(Promise.resolve(null));
                }
                if (this.project.show_child_customer_info && childCustomerId) {
                    followUps.push(
                        axios.get(`/api/index.php?model=customer&method=get&id=${childCustomerId}`)
                    );
                } else {
                    followUps.push(Promise.resolve(null));
                }

                const [usersRes, customerRes] = await Promise.all(followUps);

                const users = (usersRes && usersRes.data && usersRes.data.data) ? usersRes.data.data : [];
                const byUserid = {};
                users.forEach((u) => {
                    if (u && u.userid) byUserid[u.userid] = u;
                });
                if (parentGuisReceiver && byUserid[parentGuisReceiver]) {
                    this.project.guis_receiver_display_name = byUserid[parentGuisReceiver].realname;
                }
                if (this.project.show_child_guis_receiver && childGuisReceiver) {
                    this.project.child_guis_receiver_display_name = byUserid[childGuisReceiver]
                        ? byUserid[childGuisReceiver].realname
                        : '';
                }

                if (customerRes && customerRes.data && customerRes.data.status === 'success' && customerRes.data.data) {
                    const c = customerRes.data.data;
                    this.project.child_customer_company = c.company_name || '';
                    this.project.child_customer_branch = c.branch || '';
                    this.project.child_customer_contact = c.name || '';
                }
            } catch (error) {
                console.error('Error loading parent project info:', error);
            }
        },

        async loadGuisReceiverDisplayName() {
            const userid = this.project && this.project.guis_receiver;
            if (!userid) return;
            try {
                const response = await axios.get(
                    `/api/index.php?model=user&method=getByUserids&userids=${encodeURIComponent(userid)}`
                );
                const users = (response.data && response.data.data) ? response.data.data : [];
                const user = users.find((u) => u.userid === userid);
                if (user) {
                    this.project.guis_receiver_display_name = user.realname;
                }
            } catch (error) {
                console.error('Error loading GUIS receiver display name:', error);
            }
        },

        async loadChildGuisReceiverDisplayName() {
            const userid = this.project && this.project.child_guis_receiver_userid;
            if (!userid) {
                this.project.child_guis_receiver_display_name = '';
                return;
            }
            try {
                const response = await axios.get(
                    `/api/index.php?model=user&method=getByUserids&userids=${encodeURIComponent(userid)}`
                );
                const users = (response.data && response.data.data) ? response.data.data : [];
                const user = users.find((u) => u.userid === userid);
                this.project.child_guis_receiver_display_name = user ? user.realname : '';
            } catch (error) {
                console.error('Error loading child project GUIS receiver display name:', error);
            }
        },
        setConnectedUsers() {
            const connectedUsers = JSON.parse(sessionStorage.getItem('connected_users'));
            if (connectedUsers) {
                document.querySelectorAll('.avatar[data-userid]').forEach(avatar => {
                    const uid = avatar.getAttribute('data-userid');
                    if (connectedUsers.includes(uid)) {
                        avatar.classList.add('avatar-online');
                        avatar.classList.remove('avatar-offline');
                    } else {
                        avatar.classList.remove('avatar-online');
                        avatar.classList.add('avatar-offline');
                    }
                });
            }
        },
        async loadDepartment() {
            try {
                if (!this.project || !this.project.department_id) {
                    this.department = null;
                    this.noteLabels = [];
                    return;
                }
                const response = await axios.get(`/api/index.php?model=department&method=get&id=${this.project.department_id}`);
                this.department = response.data;
                this.noteLabels = Array.isArray(response.data && response.data.note_labels)
                    ? response.data.note_labels
                    : [];
            } catch (error) {
                console.error('Error loading department:', error);
                this.noteLabels = [];
            }
        },
        async loadTasks() {
            // try {
            //     const response = await axios.get(`/api/index.php?model=task&method=list&project_id=${this.projectId}`);
            //     this.tasks = response.data || [];
            //     this.calculateStats();
            // } catch (error) {
            //     console.error('Error loading tasks:', error);
            // }
        },
        // Comment functionality is now handled by CommentComponent
        normalizeTaskKind(value) {
            const v = String(value || '').trim();
            return v === '新規' ? '新規作成' : v;
        },
        getTaskKindLabel(value) {
            const normalized = this.normalizeTaskKind(value);
            if (!normalized) return this.translateLabel('未設定');
            const kind = TASK_KINDS.find(k => k.value === normalized);
            return kind ? this.translateLabel(kind.label) : normalized;
        },
        getTaskKindBadgeClass(value) {
            const normalized = this.normalizeTaskKind(value);
            if (!normalized) return 'bg-label-secondary';
            const kind = TASK_KINDS.find(k => k.value === normalized);
            return `bg-label-${kind?.color || 'secondary'}`;
        },
        async loadTaskWorkloadStats() {
            try {
                const response = await axios.get(
                    `/api/index.php?model=task&method=workloadStats&project_id=${this.projectId}`
                );
                const data = response.data || {};
                const byKind = Array.isArray(data.by_kind) ? data.by_kind : [];
                const kindMap = {};
                byKind.forEach((row) => {
                    let kind = this.normalizeTaskKind(row.kind);
                    if (!kind) kind = '未設定';
                    const hours = parseFloat(row.hours);
                    const count = parseInt(row.count, 10) || 0;
                    kindMap[kind] = {
                        kind,
                        hours: Number.isNaN(hours) || hours <= 0 ? 0 : hours,
                        count,
                    };
                });
                this.stats.totalWorkload = parseFloat(data.total_workload) || 0;
                const predefinedOrder = TASK_KINDS.map(k => k.value);
                this.workloadByKind = Object.values(kindMap).sort((a, b) => {
                    const ai = predefinedOrder.indexOf(a.kind);
                    const bi = predefinedOrder.indexOf(b.kind);
                    if (ai !== -1 && bi !== -1) return ai - bi;
                    if (ai !== -1) return -1;
                    if (bi !== -1) return 1;
                    return b.hours - a.hours;
                });
            } catch (error) {
                console.error('Error loading task workload stats:', error);
                this.stats.totalWorkload = 0;
                this.workloadByKind = [];
            }
        },
        formatTotalWorkload(value) {
            const n = parseFloat(value);
            if (Number.isNaN(n) || n <= 0) return '0h';
            const formatted = Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
            return formatted + 'h';
        },
        calculateStats() {
            this.stats.totalTasks = this.project.task_count || 0;
            // this.stats.completedTasks = this.tasks.filter(t => t.status === 'completed').length;
            // this.stats.timeTracked = this.tasks.reduce((sum, task) => sum + parseFloat(task.actual_hours || 0), 0);
            if (this.project && this.project.start_date) {
                let start = new Date(this.project.start_date);
                let end = this.project.end_date ? new Date(this.project.end_date) : new Date();
                if(this.project.actual_end_date){
                    end = new Date(this.project.actual_end_date);
                }
                const diffTime = Math.abs(end - start);
                this.stats.totalDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            }
        },
        // Comment component event handlers
        onCommentAdded(event) {
            this.commentsHaveAny = true;
            this.showNotification('コメントが追加されました', 'success');
        },
        
        onCommentError(event) {
            this.showNotification(event.message || 'エラーが発生しました', 'error');
        },

        onCommentMessage(event) {
            this.showNotification(event.message || 'エラーが発生しました', 'info');
        },
        async deleteProject() {
            const swal = await Swal.fire({
                title: '本当にこのプロジェクトを削除しますか？',
                icon: 'warning',
                showCancelButton: true,
            });
            if (swal.isConfirmed) {
                try {
                    const formData = new FormData();
                    formData.append('id', this.projectId);
                    const response = await axios.post('/api/index.php?model=project&method=delete', formData);
                    if(response.data.status == 'success'){
                        await Swal.fire({
                            title: 'プロジェクトが削除されました。',
                            icon: 'success',
                        }).then(() => {
                            window.location.href = 'index.php';
                        });
                    } else {
                        showMessage('プロジェクトの削除に失敗しました。', true);
                    }
                } catch (error) {
                    console.error('Error deleting project:', error);
                    showMessage('プロジェクトの削除に失敗しました。', true);
                }
            }
        },
        copyProject() {
            // Get the current custom fields data
            let customFieldsData = [];
            let raw = this.project.custom_fields;
            if (typeof raw === 'string' && raw.includes('&quot;')) {
                raw = raw.replace(/&quot;/g, '"');
            }
            if (typeof raw === 'string') {
                try { customFieldsData = JSON.parse(raw); } catch (e) { customFieldsData = []; }
            } else if (Array.isArray(raw)) {
                customFieldsData = raw;
            }
            
            // Prepare project data for copying
            const projectData = {
                category_id: this.project.category_id,
                // company_name: this.project.company_name,
                // customer_id: this.project.customer_id,
                // project_number: this.project.project_number,
                name: this.project.name + ' (コピー)',
                department_id: this.project.department_id,
                building_branch: this.project.building_branch,
                building_size: this.project.building_size,
                building_type: this.project.building_type,
                building_number: this.project.building_number,
                priority: this.project.priority,
                status: 'draft', // Set to draft for new copy
                project_order_type: this.project.project_order_type,
                start_date: this.project.start_date,
                end_date: this.project.end_date,
                progress: 0, // Reset progress
                description: this.project.description,
                department_custom_fields_set_id: this.project.department_custom_fields_set_id,
                teams: this.project.teams,
                managers: this.managers.map(m => m.user_id).join(','),
                members: this.members.map(m => m.user_id).join(','),
                custom_fields: customFieldsData,
                tags: this.project.tags
            };
            
            // Store the data in sessionStorage
            sessionStorage.setItem('copyProjectData', JSON.stringify(projectData));
            
            // Redirect to create page with department_id if available
            const url = this.project.department_id 
                ? `create.php?department_id=${this.project.department_id}` 
                : 'create.php';
            window.location.href = url;
        },
        formatDate(date) {
            if (!date) return '-';
            const parsed = parseProjectDateMomentServer(date);
            if (!parsed) return '-';
            const localized = moment.tz
                ? parsed.clone().tz(getProjectDetailDisplayTimezone())
                : parsed;
            return localized.format(
                isProjectDetailVietnameseLocale()
                    ? PROJECT_DATETIME_VI_DATE_FORMAT
                    : PROJECT_DATETIME_JA_DATE_FORMAT
            );
        },
        /** Remaining time for a sibling project's deadline (uses sibling status, not current project). */
        getSiblingDeadlineRemaining(sibling, dateStr, isDelivered) {
            if (!sibling || !dateStr) return null;
            if (this.shouldSkipDeadlineBadge(sibling.status, !!isDelivered)) {
                return null;
            }
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
                }
                return { text: formatTimeText([formatUnit(minutes, minuteLabel), overdueLabel]), class: 'bg-danger', isOverdue: true };
            }
            if (days > 0) {
                return { text: formatTimeText([remainingLabel, formatUnit(days, dayLabel), formatUnit(hours, hourLabel), formatUnit(minutes, minuteLabel)]), class: 'bg-label-info', isOverdue: false };
            } else if (hours > 0) {
                return { text: formatTimeText([remainingLabel, formatUnit(hours, hourLabel), formatUnit(minutes, minuteLabel)]), class: hours <= 24 ? 'bg-label-warning' : 'bg-label-info', isOverdue: false };
            }
            return { text: formatTimeText([remainingLabel, formatUnit(minutes, minuteLabel)]), class: 'bg-label-warning', isOverdue: false };
        },
        formatDateForInput(date) {
            return toProjectDateTimeInputValue(date);
        },
        formatBusinessDocumentDateTime(key) {
            const serverVal = this.getBusinessDocumentServerDate(key);
            return formatProjectDateTimeForDisplay(serverVal || this.project?.[key]);
        },
        /** Khởi tạo Bootstrap tooltip cho ô có data-time (giờ JST → tooltip VN) */
        initVietnamTimeTooltips() {
            this.$nextTick(() => {
                const app = document.getElementById('app');
                if (!app || typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
                app.querySelectorAll('[data-bs-toggle="tooltip"][data-time]').forEach(el => {
                    if (!document.contains(el)) return;
                    try {
                        const t = bootstrap.Tooltip.getInstance(el);
                        if (t) t.dispose();
                    } catch (e) { /* element may be detached */ }
                    if (el.getAttribute('data-bs-title')) {
                        try { new bootstrap.Tooltip(el); } catch (e) { /* skip */ }
                    }
                });
            });
        },
        formatShortDateTime(datetime) {
            return formatProjectDateTimeForDisplay(datetime);
        },
        getBusinessDocumentTaxAmount(amount) {
            const base = Number(amount);
            if (!Number.isFinite(base) || base <= 0) return 0;
            return Math.round(base * 0.1);
        },
        getBusinessDocumentTotalWithTax(amount) {
            const base = Number(amount);
            if (!Number.isFinite(base) || base <= 0) return 0;
            return base + this.getBusinessDocumentTaxAmount(base);
        },
        formatBusinessDocumentTaxAmount(amount) {
            return this.formatCurrency(this.getBusinessDocumentTaxAmount(amount));
        },
        formatBusinessDocumentTotalWithTax(amount) {
            return this.formatCurrency(this.getBusinessDocumentTotalWithTax(amount));
        },
        getNamedDefaultTaskStatusMeta(status) {
            const list = [
                { value: 'todo', label: '未開始', color: 'secondary' },
                { value: 'in-progress', label: '進行中', color: 'primary' },
                { value: 'confirming', label: '確認中', color: 'warning' },
                { value: 'paused', label: '一時停止', color: 'warning' },
                { value: 'completed', label: '完了', color: 'success' },
                { value: 'cancelled', label: 'キャンセル', color: 'danger' }
            ];
            const s = String(status || '');
            return list.find((x) => x.value === s) || { value: s, label: s || '-', color: 'secondary' };
        },
        getNamedDefaultTaskStatusLabel(status) {
            const meta = this.getNamedDefaultTaskStatusMeta(status);
            return this.translateLabel ? this.translateLabel(meta.label) : meta.label;
        },
        getNamedDefaultTaskStatusBadgeClass(status) {
            return 'bg-' + this.getNamedDefaultTaskStatusMeta(status).color;
        },
        getRequestBadgeClass(request) {
            const map = {
                '意匠': 'bg-primary',
                '設備': 'bg-info',
                '3D設備': 'bg-success',
                '省エネ': 'bg-lime',
                '3D': 'bg-secondary',
                'その他': 'bg-dark'
            };
            return map[String(request || '').trim()] || 'bg-secondary';
        },
        getRequestUnfulfilledBadgeClass(request) {
            return String(this.getRequestBadgeClass(request) || 'bg-secondary').replace(/^bg-/, 'badge-outline-');
        },
        getEnergyDrawingShareLabel(project) {
            return window.EnergyDrawingShare
                ? window.EnergyDrawingShare.formatEnergyDrawingShareLabel(project || this.project)
                : '';
        },
        getEnergyDrawingShareBadgeClass(project) {
            return window.EnergyDrawingShare
                ? window.EnergyDrawingShare.formatEnergyDrawingShareBadgeClass(project || this.project)
                : '';
        },
        isEnergyDrawingShareSourceDept(departmentName) {
            if (window.EnergyDrawingShare && window.EnergyDrawingShare.isShareSourceDepartment) {
                return window.EnergyDrawingShare.isShareSourceDepartment(departmentName);
            }
            const name = String(departmentName || '').trim();
            return name === '意匠設計' || name === '設備設計' || name === '技術課設備';
        },
        getEnergyDrawingShareReasonLabel(code) {
            return window.EnergyDrawingShare
                ? window.EnergyDrawingShare.reasonLabel(code)
                : (code || '');
        },
        onEnergyDrawingShareStatusChange() {
            if (!this.project) return;
            if (this.project.energy_drawing_share_status !== 'not_shared') {
                this.project.energy_drawing_share_reason = '';
                this.project.energy_drawing_share_note = '';
            } else if (!this.project.energy_drawing_share_reason) {
                this.project.energy_drawing_share_reason = 'waiting_assignee';
            }
        },
        onEnergyDrawingShareReasonChange() {
            if (!this.project) return;
            if (this.project.energy_drawing_share_reason !== 'other') {
                this.project.energy_drawing_share_note = '';
            }
        },
        async saveEnergyDrawingShare() {
            if (!this.project || !this.project.id || !this.canEditProject) return;
            if (!this.showEnergyDrawingShareEditBox) return;
            const status = String(this.project.energy_drawing_share_status || '').trim();
            if (status !== 'shared' && status !== 'not_shared') {
                if (typeof showMessage === 'function') {
                    showMessage('共有状況を選択してください。', true);
                }
                return;
            }
            let reason = '';
            let note = '';
            if (status === 'not_shared') {
                reason = String(this.project.energy_drawing_share_reason || '').trim();
                note = String(this.project.energy_drawing_share_note || '').trim();
                if (!reason) {
                    if (typeof showMessage === 'function') {
                        showMessage('共有しない理由を選択してください。', true);
                    }
                    return;
                }
                if (reason === 'other' && !note) {
                    if (typeof showMessage === 'function') {
                        showMessage('理由を入力してください。', true);
                    }
                    return;
                }
            }
            this.savingEnergyDrawingShare = true;
            try {
                const formData = new FormData();
                formData.append('id', this.project.id);
                formData.append('energy_drawing_share_status', status);
                if (status === 'not_shared') {
                    formData.append('energy_drawing_share_reason', reason);
                    formData.append('energy_drawing_share_note', reason === 'other' ? note : '');
                } else {
                    formData.append('energy_drawing_share_reason', '');
                    formData.append('energy_drawing_share_note', '');
                }
                appendProjectVersionToFormData(formData, this.project);
                const response = await axios.post('/api/index.php?model=project&method=update', formData);
                if (!response.data || response.data.status !== 'success') {
                    if (handleProjectVersionConflict(response.data, () => this.loadProject())) {
                        return;
                    }
                    if (typeof showMessage === 'function') {
                        showMessage((response.data && response.data.message) || '共有状況の更新に失敗しました。', true);
                    }
                    return;
                }
                applyProjectVersionFromResponse(this.project, response.data);
                if (window.EnergyDrawingShare) {
                    window.EnergyDrawingShare.applyEnergyDrawingShareToProject(this.project, {
                        status: status,
                        reason: reason,
                        note: note
                    });
                }
                if (this.originalProject) {
                    this.originalProject.energy_drawing_share_status = this.project.energy_drawing_share_status;
                    this.originalProject.energy_drawing_share_reason = this.project.energy_drawing_share_reason;
                    this.originalProject.energy_drawing_share_note = this.project.energy_drawing_share_note;
                    this.originalProject.energy_drawing_share_at = this.project.energy_drawing_share_at;
                    this.originalProject.energy_drawing_share_by = this.project.energy_drawing_share_by;
                }
                if (typeof showMessage === 'function') {
                    showMessage('共有状況を更新しました。');
                }
            } catch (e) {
                if (typeof showMessage === 'function') {
                    showMessage('共有状況の更新に失敗しました。', true);
                }
            } finally {
                this.savingEnergyDrawingShare = false;
            }
        },
        mapDepartmentNameToRequestType(departmentName) {
            const name = String(departmentName || '').trim();
            if (!name) return '';
            const rules = [
                ['3D設備', '3D設備'],
                ['技術課設備', '3D設備'],
                ['省エネ', '省エネ'],
                ['意匠', '意匠'],
                ['設備', '設備']
            ];
            for (let i = 0; i < rules.length; i++) {
                if (name.indexOf(rules[i][0]) !== -1) return rules[i][1];
            }
            return '';
        },
        isParentRequestFulfilled(requestType) {
            const type = String(requestType || '').trim();
            if (!type || !Array.isArray(this.parentSiblingProjects)) return false;
            return this.parentSiblingProjects.some((p) => {
                if (String(p.status || '') === 'deleted') return false;
                return this.mapDepartmentNameToRequestType(p.department_name) === type;
            });
        },
        getRoleLabel(role) {
            const roles = {
                'manager': 'マネージャー',
                'member': 'メンバー',
                'viewer': '閲覧者'
            };
            return roles[role] || role;
        },
        getRoleBadgeClass(role) {
            const roleColors = {
                'manager': 'bg-primary',
                'member': 'bg-info',
                'viewer': 'bg-secondary'
            };
            return roleColors[role] || 'bg-secondary';
        },
        showAvatarImage(member) {
            return !!(member && !member.avatarError && this.getAvatarSrc(member) && member.avatarLoaded);
        },
        getBdLogDisplayName(log) {
            if (!log) return '';
            const name = log.username || log.realname || log.user || '';
            if (typeof getUserDisplayName === 'function') {
                return getUserDisplayName(name, {
                    userid: log.userid || log.user_id || '',
                    user_ruby: log.user_ruby || ''
                }) || name;
            }
            return name;
        },
        initTooltips() {
            if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(el => {
                if (!document.contains(el)) return;
                try {
                    const t = bootstrap.Tooltip.getInstance(el);
                    if (t) t.dispose();
                } catch (e) { /* element may be detached */ }
                try {
                    new bootstrap.Tooltip(el);
                } catch (e) { /* skip invalid elements */ }
            });
        },
        async loadCategories() {
            const res = await axios.get('/api/index.php?model=customer&method=list_categories');
            if (res.data && res.data.data) {
                this.categories = res.data.data;
            }
        },
        async loadCompanies() {
            if (!this.project.department_id) {
                this.companies = [];
                return;
            }
            const res = await axios.get(`/api/index.php?model=customer&method=list_companies_by_department&department_id=${this.project.department_id}`);
            if (res.data && res.data.data) {
                this.companies = res.data.data;
            }
        },
        async loadContacts() {
            if (!this.project.department_id) {
                this.contacts = [];
                return;
            }
            const res = await axios.get(`/api/index.php?model=customer&method=list_contacts_by_department&department_id=${this.project.department_id}`);
            if (res.data && res.data.data) {
                this.contacts = res.data.data;
            }
        },
        async updateProjectStatus() {
            if (this.isUpdatingStatus) return;
            if (!this.canEditBusinessDocuments) return;

            clearTimeout(this.businessDocumentUpdateTimer);
            this.businessDocumentUpdateTimer = null;
            clearTimeout(this.businessDocumentSaveHideTimer);
            this.businessDocumentSaveStatus = 'loading';
            this.isUpdatingStatus = true;
            try {
                this.syncBusinessDocumentDatesFromPickers();
                const energyIssuedError = this.getEnergyFeeIssuedValidationError();
                if (energyIssuedError) {
                    this.businessDocumentSaveStatus = null;
                    this.businessDocumentError = energyIssuedError;
                    return;
                }
                if (!this.isEnergyDepartmentProject) {
                    if (this.normalizeBusinessDocumentStatusValue(this.project.estimate_status) === '発行済'
                        && !this.isEstimateDocumentFieldsComplete()) {
                        this.businessDocumentSaveStatus = null;
                        this.businessDocumentError = this.getEstimateDocumentFieldsValidationError();
                        return;
                    }
                    if (this.normalizeBusinessDocumentStatusValue(this.project.invoice_status) === '発行済'
                        && !this.isInvoiceDocumentFieldsComplete()) {
                        this.businessDocumentSaveStatus = null;
                        this.businessDocumentError = this.getInvoiceDocumentFieldsValidationError();
                        return;
                    }
                }
                const formData = new FormData();
                formData.append('id', this.projectId);
                formData.append('amount', this.getBusinessDocumentAmountForApi(this.project.amount));
                formData.append('estimate_status', this.project.estimate_status || '未発行');
                formData.append('estimate_date', this.getBusinessDocumentDateForApi('estimate_date'));
                formData.append('estimate_number', this.project.estimate_number || '');
                formData.append('invoice_status', this.project.invoice_status || '未発行');
                formData.append('invoice_date', this.getBusinessDocumentDateForApi('invoice_date'));
                formData.append('invoice_amount', this.getBusinessDocumentAmountForApi(this.project.invoice_amount));
                formData.append('invoice_number', this.project.invoice_number || '');
                formData.append('payment_note', this.project.payment_note || '');
                if (window.EnergyFeePayment && window.EnergyFeePayment.appendEnergyFieldsToFormData) {
                    window.EnergyFeePayment.appendEnergyFieldsToFormData(
                        formData,
                        this.project,
                        (v) => this.getBusinessDocumentAmountForApi(v),
                        (key) => this.getBusinessDocumentDateForApi(key)
                    );
                }
                appendPaymentVersionToFormData(formData, this.project);
                const response = await axios.post('/api/index.php?model=project&method=updateProjectStatus', formData);
                if (response.data && response.data.status === 'success') {
                    applyPaymentVersionFromResponse(this.project, response.data);
                    this.businessDocumentDirty = false;
                    this.businessDocumentError = '';
                    this._bdSuppressAutoSave = true;
                    // Keep cleared amounts as empty so placeholder 未入力 stays visible
                    this.project.amount = this.normalizeBusinessDocumentAmountInputValue(this.project.amount);
                    this.project.invoice_amount = this.normalizeBusinessDocumentAmountInputValue(this.project.invoice_amount);
                    if (window.EnergyFeePayment && window.EnergyFeePayment.AMOUNT_FIELDS) {
                        window.EnergyFeePayment.AMOUNT_FIELDS.forEach((key) => {
                            this.project[key] = this.normalizeBusinessDocumentAmountInputValue(this.project[key]);
                        });
                    }
                    BUSINESS_DOCUMENT_DATE_FIELDS.forEach((key) => {
                        const apiVal = this.getBusinessDocumentDateForApi(key);
                        this.setBusinessDocumentServerDate(key, apiVal || '');
                        this.project[key] = apiVal
                            ? canonicalizeProjectDateTimeInputDisplay(toProjectDateTimeInputValue(apiVal))
                            : '';
                        const el = document.getElementById(BUSINESS_DOCUMENT_PICKER_IDS[key]);
                        if (el && el._flatpickr) {
                            if (apiVal) {
                                el._flatpickr.setDate(this.project[key], false, PROJECT_DATETIME_FLATPICKR_FORMAT);
                            } else {
                                el._flatpickr.clear();
                            }
                        }
                    });
                    this.$nextTick(() => {
                        setTimeout(() => {
                            this._bdSuppressAutoSave = false;
                        }, 200);
                    });
                    this.businessDocumentSaveStatus = 'saved';
                    this.refreshLogsIfOpen();
                    this.businessDocumentSaveHideTimer = setTimeout(() => {
                        this.businessDocumentSaveStatus = null;
                        this.businessDocumentSaveHideTimer = null;
                    }, 5000);
                } else {
                    if (handleProjectVersionConflict(response.data, () => this.loadProject())) {
                        return;
                    }
                    this.businessDocumentSaveStatus = null;
                }
            } catch (error) {
                console.error('Error updating project status:', error);
                this.businessDocumentSaveStatus = null;
            } finally {
                this.isUpdatingStatus = false;
            }
        },
        normalizeBusinessDocumentStatus(status, fallback) {
            if (!status || status === '発行済み') {
                return status === '発行済み' ? '発行済' : (fallback || '未発行');
            }
            return status;
        },
        normalizeBusinessDocumentFields() {
            if (!this.project) return;
            if (window.EnergyFeePayment && window.EnergyFeePayment.hydrateApplicationFromLegacy) {
                window.EnergyFeePayment.hydrateApplicationFromLegacy(this.project);
            }
            this.project.estimate_status = this.normalizeBusinessDocumentStatus(this.project.estimate_status, '未発行');
            this.project.invoice_status = this.normalizeBusinessDocumentStatus(this.project.invoice_status, '未発行');
            this.project.payment_status = this.project.payment_status || '未入金';
            this.project.estimate_number = this.project.estimate_number || '';
            this.project.invoice_number = this.project.invoice_number || '';
            this.project.receipt_number = this.project.receipt_number || '';
            this.project.payment_note = this.project.payment_note || '';
            this.project.invoice_amount = this.normalizeBusinessDocumentAmountInputValue(this.project.invoice_amount);
            this.project.payment_amount = this.project.payment_amount != null ? Number(this.project.payment_amount) : 0;
            this.project.amount = this.normalizeBusinessDocumentAmountInputValue(this.project.amount);
            if (window.EnergyFeePayment && window.EnergyFeePayment.AMOUNT_FIELDS) {
                window.EnergyFeePayment.AMOUNT_FIELDS.forEach((key) => {
                    this.project[key] = this.normalizeBusinessDocumentAmountInputValue(this.project[key]);
                });
            }
            if (window.EnergyFeePayment && window.EnergyFeePayment.STATUS_FIELDS) {
                window.EnergyFeePayment.STATUS_FIELDS.forEach((key) => {
                    this.project[key] = this.normalizeBusinessDocumentStatus(this.project[key], '未発行');
                });
            }
            this.normalizeBusinessDocumentDateFields();
        },
        normalizeBusinessDocumentAmountInputValue(value) {
            // Empty/null → '' (placeholder 未入力). Keep 0 as a real value.
            if (value == null || value === '') return '';
            const n = Number(value);
            if (!Number.isFinite(n)) return '';
            return n;
        },
        getBusinessDocumentAmountForApi(value) {
            if (value == null || value === '') return '';
            const n = Number(value);
            if (!Number.isFinite(n)) return '';
            return String(n);
        },
        normalizeBusinessDocumentDateFields() {
            if (!this.project) return;
            BUSINESS_DOCUMENT_DATE_FIELDS.forEach((key) => {
                const raw = this.project[key];
                if (!raw || !String(raw).trim()) {
                    this.project[key] = '';
                }
            });
            this.syncServerBusinessDocumentDatesFromProject();
        },
        syncServerBusinessDocumentDatesFromProject() {
            if (!this.project) return;
            const server = {};
            BUSINESS_DOCUMENT_DATE_FIELDS.forEach((key) => {
                const raw = String(this.project[key] || '').trim();
                if (!raw) {
                    server[key] = '';
                    return;
                }
                // Keep dashed JST only (fromProject is no-op shift for dash server strings)
                server[key] = fromProjectDateTimeInputValue(raw) || raw;
            });
            this._serverBusinessDocumentDates = server;
        },
        getBusinessDocumentServerDate(key) {
            if (this._serverBusinessDocumentDates
                && this._serverBusinessDocumentDates[key] != null
                && String(this._serverBusinessDocumentDates[key]).trim()) {
                return this._serverBusinessDocumentDates[key];
            }
            const raw = String(this.project?.[key] || '').trim();
            if (!raw) return '';
            if (isProjectServerDateTimeFormat(raw)) return raw;
            return fromProjectDateTimeInputValue(raw) || raw;
        },
        setBusinessDocumentServerDate(key, displayOrServerValue) {
            if (!this._serverBusinessDocumentDates) {
                this._serverBusinessDocumentDates = {};
            }
            const raw = String(displayOrServerValue || '').trim();
            if (!raw) {
                this._serverBusinessDocumentDates[key] = '';
                return;
            }
            // Always store dashed JST wall-clock so re-init never re-parses UI "/" as VN again
            this._serverBusinessDocumentDates[key] = fromProjectDateTimeInputValue(raw) || raw;
        },
        getBusinessDocumentPickerDisplayValue(el) {
            if (!el) return '';
            const fp = el._flatpickr;
            if (fp) {
                // Always use dateFormat (not altInput 年月日) so v-model stays stable
                if (fp.selectedDates && fp.selectedDates.length > 0) {
                    return fp.formatDate(fp.selectedDates[0], PROJECT_DATETIME_FLATPICKR_FORMAT);
                }
                return String((fp._input && fp._input.value) || el.value || '').trim();
            }
            return String(el.value || '').trim();
        },
        getBusinessDocumentDateForApi(key) {
            if (!this.project) return '';
            const el = document.getElementById(BUSINESS_DOCUMENT_PICKER_IDS[key]);
            if (el) {
                const displayVal = this.getBusinessDocumentPickerDisplayValue(el);
                if (!displayVal) {
                    return '';
                }
                const fp = el._flatpickr;
                if (fp && fp.selectedDates && fp.selectedDates.length > 0) {
                    return this.toAPIDate(fp.formatDate(fp.selectedDates[0], PROJECT_DATETIME_FLATPICKR_FORMAT));
                }
                return this.toAPIDate(displayVal);
            }
            const fallback = String(this.project[key] || '').trim();
            return fallback ? this.toAPIDate(fallback) : '';
        },
        getBusinessDocumentDateForTooltip(key) {
            const serverVal = this.getBusinessDocumentServerDate(key);
            if (serverVal) return serverVal;
            const apiVal = this.getBusinessDocumentDateForApi(key);
            return apiVal || String(this.project?.[key] || '').trim();
        },
        hasBusinessDocumentDate(key) {
            return !!String(this.getBusinessDocumentServerDate(key) || this.project?.[key] || '').trim();
        },
        hasBusinessDocumentAmount(amount) {
            return amount != null && amount !== '' && Number(amount) > 0;
        },
        hasBusinessDocumentNumber(value) {
            return !!String(value || '').trim();
        },
        isBusinessDocumentIssuedStatus(status) {
            const normalized = this.normalizeBusinessDocumentStatusValue(status);
            return normalized === '発行済';
        },
        isBusinessDocumentFreeStatus(status) {
            if (window.EnergyFeePayment && window.EnergyFeePayment.isWaivedStatus) {
                return window.EnergyFeePayment.isWaivedStatus(status);
            }
            return this.normalizeBusinessDocumentStatusValue(status) === '無償';
        },
        isEnergyFeeRowComplete(row) {
            if (!this.project || !row) return false;
            return this.hasBusinessDocumentDate(row.dateKey)
                && this.hasBusinessDocumentAmount(this.project[row.amountKey]);
        },
        /** Hint for 発行済: only while still draft-like (未発行 / 見積作成中 or 請求準備). */
        shouldShowIssuedRequirementHint(status, kind) {
            const n = this.normalizeBusinessDocumentStatusValue(status);
            if (kind === 'invoice') {
                return n === '未発行' || n === '請求準備';
            }
            return n === '未発行' || n === '見積作成中';
        },
        shouldShowEnergyFeeIssuedHint(row) {
            if (!this.project || !row || this.isEnergyFeeRowComplete(row)) return false;
            return this.shouldShowIssuedRequirementHint(this.project[row.statusKey], row.kind);
        },
        shouldShowEstimateIssuedHint() {
            if (!this.project || this.isEstimateDocumentFieldsComplete()) return false;
            return this.shouldShowIssuedRequirementHint(this.project.estimate_status, 'estimate');
        },
        shouldShowInvoiceIssuedHint() {
            if (!this.project || this.isInvoiceDocumentFieldsComplete()) return false;
            return this.shouldShowIssuedRequirementHint(this.project.invoice_status, 'invoice');
        },
        getEnergyFeeIssuedValidationError() {
            if (!this.isEnergyDepartmentProject || !this.project) return '';
            const rows = (window.EnergyFeePayment && window.EnergyFeePayment.FEE_ROWS) || [];
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                if (this.normalizeBusinessDocumentStatusValue(this.project[row.statusKey]) !== '発行済') {
                    continue;
                }
                if (this.isEnergyFeeRowComplete(row)) continue;
                const missing = [];
                if (!this.hasBusinessDocumentDate(row.dateKey)) missing.push(row.dateLabel);
                if (!this.hasBusinessDocumentAmount(this.project[row.amountKey])) missing.push(row.amountLabel);
                return '発行済にするには以下を入力してください: ' + missing.join('、') + '（' + row.title + '）';
            }
            return '';
        },
        selectEnergyFeeStatus(row, status) {
            if (!this.project || !row || !this.canEditBusinessDocuments) return;
            this.syncBusinessDocumentDatesFromPickers();
            const normalized = this.normalizeBusinessDocumentStatusValue(status);
            if (normalized === '発行済' && !this.isEnergyFeeRowComplete(row)) {
                this.businessDocumentError = this.getEnergyFeeIssuedValidationError()
                    || ('発行済にするには' + row.dateLabel + '・' + row.amountLabel + 'が必要です');
                this.hideBusinessDocumentStatusDropdown('#' + row.dropdownId);
                return;
            }
            if (this.normalizeBusinessDocumentStatusValue(this.project[row.statusKey]) === normalized) {
                this.hideBusinessDocumentStatusDropdown('#' + row.dropdownId);
                return;
            }
            this.project[row.statusKey] = normalized;
            if (this.isBusinessDocumentFreeStatus(normalized)) {
                this.project[row.amountKey] = 0;
            }
            this.businessDocumentError = '';
            this.scheduleBusinessDocumentUpdate();
            this.hideBusinessDocumentStatusDropdown('#' + row.dropdownId);
        },
        getEnergyFeeStatusLabel(kind, status) {
            return kind === 'invoice'
                ? this.getInvoiceStatusLabel(status)
                : this.getEstimateStatusLabel(status);
        },
        getEnergyFeeStatusButtonClass(kind, status) {
            return kind === 'invoice'
                ? this.getInvoiceStatusButtonClass(status)
                : this.getEstimateStatusButtonClass(status);
        },
        getEnergyFeeStatusBadgeClass(kind, status) {
            return kind === 'invoice'
                ? this.getInvoiceStatusBadgeClass(status)
                : this.getEstimateStatusBadgeClass(status);
        },
        getEnergyFeeStatusOptions(kind) {
            return kind === 'invoice'
                ? this.businessEnergyInvoiceStatuses
                : this.businessEnergyEstimateStatuses;
        },
        businessDocumentAmountsDiffer(a, b) {
            const na = (a == null || a === '') ? null : Number(a);
            const nb = (b == null || b === '') ? null : Number(b);
            if (na == null && nb == null) return false;
            if (na == null || nb == null) return true;
            if (!Number.isFinite(na) || !Number.isFinite(nb)) return true;
            return na !== nb;
        },
        canCopyEnergyFeeAmountToInvoice(row) {
            if (!this.project || !row || !row.copyFromAmountKey) return false;
            return this.businessDocumentAmountsDiffer(
                this.project[row.amountKey],
                this.project[row.copyFromAmountKey]
            );
        },
        canCopyEstimateAmountToInvoice() {
            if (!this.project) return false;
            return this.businessDocumentAmountsDiffer(this.project.invoice_amount, this.project.amount);
        },
        copyEnergyFeeAmountToInvoice(row) {
            if (!this.project || !row || !row.copyFromAmountKey) return;
            this.project[row.amountKey] = this.project[row.copyFromAmountKey];
            this.scheduleBusinessDocumentUpdate();
        },
        businessDocumentPickerId(dateKey) {
            return BUSINESS_DOCUMENT_PICKER_IDS[dateKey] || (dateKey + '_picker');
        },
        isEstimateDocumentFieldsComplete() {
            if (!this.project) return false;
            this.syncBusinessDocumentDatesFromPickers();
            return this.hasBusinessDocumentDate('estimate_date')
                && this.hasBusinessDocumentAmount(this.project.amount);
        },
        isInvoiceDocumentFieldsComplete() {
            if (!this.project) return false;
            this.syncBusinessDocumentDatesFromPickers();
            return this.hasBusinessDocumentDate('invoice_date')
                && this.hasBusinessDocumentAmount(this.project.invoice_amount);
        },
        getEstimateDocumentFieldsValidationError() {
            if (!this.project) return '';
            this.syncBusinessDocumentDatesFromPickers();
            const missing = [];
            if (!this.hasBusinessDocumentDate('estimate_date')) missing.push('見積日');
            if (!this.hasBusinessDocumentAmount(this.project.amount)) missing.push('見積金額');
            if (!missing.length) return '';
            return '発行済にするには以下を入力してください: ' + missing.join('、');
        },
        getInvoiceDocumentFieldsValidationError() {
            if (!this.project) return '';
            this.syncBusinessDocumentDatesFromPickers();
            const missing = [];
            if (!this.hasBusinessDocumentDate('invoice_date')) missing.push('請求日');
            if (!this.hasBusinessDocumentAmount(this.project.invoice_amount)) missing.push('請求金額');
            if (!missing.length) return '';
            return '発行済にするには以下を入力してください: ' + missing.join('、');
        },
        hideBusinessDocumentStatusDropdown(dropdownId) {
            const dropdownElement = document.querySelector(dropdownId);
            if (dropdownElement && typeof bootstrap !== 'undefined') {
                const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                if (dropdown) dropdown.hide();
            }
        },
        scheduleBusinessDocumentUpdate() {
            if (this._bdSuppressAutoSave) return;
            if (!this.canEditBusinessDocuments || this.isUpdatingStatus) return;
            this.businessDocumentDirty = true;
            clearTimeout(this.businessDocumentUpdateTimer);
            this.businessDocumentUpdateTimer = setTimeout(() => {
                this.businessDocumentUpdateTimer = null;
                this.updateProjectStatus();
            }, 800);
        },
        hasPendingBusinessDocumentChanges() {
            return !!(
                this.businessDocumentDirty
                || this.isUpdatingStatus
                || this.businessDocumentUpdateTimer
            );
        },
        getBusinessDocumentSnapshot() {
            if (!this.project) return null;
            const snapshot = {};
            BUSINESS_DOCUMENT_FIELDS.forEach((key) => {
                snapshot[key] = this.project[key];
            });
            return snapshot;
        },
        applyBusinessDocumentSnapshot(snapshot) {
            if (!this.project || !snapshot) return;
            BUSINESS_DOCUMENT_FIELDS.forEach((key) => {
                if (Object.prototype.hasOwnProperty.call(snapshot, key)) {
                    this.project[key] = snapshot[key];
                }
            });
            this.normalizeBusinessDocumentFields();
        },
        findBusinessStatusOption(list, status) {
            const normalized = this.normalizeBusinessDocumentStatusValue(status);
            return list.find((s) => s.value === normalized || s.value === status);
        },
        setBusinessDocumentDateToday(field) {
            if (!this.canEditBusinessDocuments || !this.project) return;
            const serverNow = moment.tz(SERVER_TASK_TIMEZONE).format('YYYY-MM-DD HH:mm:ss');
            const dateStr = canonicalizeProjectDateTimeInputDisplay(toProjectDateTimeInputValue(serverNow));
            this.project[field] = dateStr || '';
            this.setBusinessDocumentServerDate(field, serverNow);
            const el = document.getElementById(BUSINESS_DOCUMENT_PICKER_IDS[field]);
            if (el && el._flatpickr) {
                el._flatpickr.setDate(dateStr, false, PROJECT_DATETIME_FLATPICKR_FORMAT);
            }
            this.scheduleBusinessDocumentUpdate();
        },
        copyEstimateAmountToInvoice() {
            if (!this.canEditBusinessDocuments || !this.project) return;
            this.project.invoice_amount = this.normalizeBusinessDocumentAmountInputValue(this.project.amount);
            this.scheduleBusinessDocumentUpdate();
        },
        copyInvoiceAmountToPayment() {
            if (!this.canEditBusinessDocuments || !this.project) return;
            this.project.payment_amount = this.project.invoice_amount != null ? Number(this.project.invoice_amount) : 0;
            this.scheduleBusinessDocumentUpdate();
        },
        syncBusinessDocumentDatesFromPickers() {
            if (!this.project) return;
            const fieldIds = {
                estimate_date: 'estimate_date_picker',
                invoice_date: 'invoice_date_picker',
                payment_date: 'payment_date_picker',
            };
            Object.keys(fieldIds).forEach((key) => {
                const el = document.getElementById(fieldIds[key]);
                if (!el) return;
                const displayVal = this.getBusinessDocumentPickerDisplayValue(el);
                const canonical = canonicalizeProjectDateTimeInputDisplay(displayVal);
                if (String(this.project[key] || '').trim() !== String(canonical || '').trim()) {
                    this.project[key] = canonical;
                    this.setBusinessDocumentServerDate(key, canonical);
                }
            });
        },
        initBusinessDocumentDatePicker(elId, key, force) {
            if (!this.project || !this.canEditBusinessDocuments) return;
            const el = document.getElementById(elId);
            if (!el) return;
            const serverValue = this.getBusinessDocumentServerDate(key);
            const inputVal = toProjectDateTimeInputValue(serverValue);
            if (!force && el._flatpickr) {
                const fpVal = canonicalizeProjectDateTimeInputDisplay(
                    String(
                        (el._flatpickr._input && el._flatpickr._input.value) || el.value || ''
                    ).trim()
                );
                const displayVal = canonicalizeProjectDateTimeInputDisplay(
                    String(this.project[key] || '').trim()
                );
                if (fpVal && (fpVal === inputVal || fpVal === displayVal)) {
                    return;
                }
            }
            this._bdSuppressAutoSave = true;
            initProjectDetailFlatpickr(el, {
                onChange: (selectedDates, dateStr) => {
                    if (this._bdSuppressAutoSave) return;
                    const canonical = canonicalizeProjectDateTimeInputDisplay(dateStr);
                    this.project[key] = canonical;
                    this.setBusinessDocumentServerDate(key, canonical);
                    this.scheduleBusinessDocumentUpdate();
                },
                onClose: () => {
                    if (this._bdSuppressAutoSave) return;
                    const displayVal = this.getBusinessDocumentPickerDisplayValue(el);
                    if (!displayVal) {
                        if (el._flatpickr) {
                            el._flatpickr.clear();
                        }
                        this.project[key] = '';
                        this.setBusinessDocumentServerDate(key, '');
                        this.scheduleBusinessDocumentUpdate();
                    }
                }
            }, serverValue);
            const canonical = canonicalizeProjectDateTimeInputDisplay(
                this.getBusinessDocumentPickerDisplayValue(el) || inputVal
            );
            if (canonical && this.project[key] !== canonical) {
                this.project[key] = canonical;
            } else if (!canonical && !serverValue) {
                this.project[key] = '';
            }
            if (serverValue) {
                this.setBusinessDocumentServerDate(key, serverValue);
            }
            setTimeout(() => {
                this._bdSuppressAutoSave = false;
            }, 200);
        },
        initBusinessDocumentDatePickers(force) {
            if (!this.project || !this.canEditBusinessDocuments) return;
            Object.keys(BUSINESS_DOCUMENT_PICKER_IDS).forEach((key) => {
                if (key === 'payment_date') return;
                this.initBusinessDocumentDatePicker(BUSINESS_DOCUMENT_PICKER_IDS[key], key, force);
            });
        },
        reinitBusinessDocumentDatePickersOnLocaleChange() {
            if (!this.project || !this.canEditBusinessDocuments) return;
            Object.values(BUSINESS_DOCUMENT_PICKER_IDS).forEach((elId) => {
                const el = document.getElementById(elId);
                if (el && el._flatpickr) {
                    el._flatpickr.destroy();
                }
            });
            // Rebuild display from stored server dates only (never re-convert display→display)
            BUSINESS_DOCUMENT_DATE_FIELDS.forEach((key) => {
                const serverVal = this.getBusinessDocumentServerDate(key);
                this.project[key] = serverVal
                    ? canonicalizeProjectDateTimeInputDisplay(toProjectDateTimeInputValue(serverVal))
                    : '';
            });
            this.initBusinessDocumentDatePickers(true);
        },
        updateAmount() {
            if (!this.canEditBusinessDocuments) return;
            clearTimeout(this.amountUpdateTimer);
            this.amountUpdateTimer = setTimeout(() => {
                this.updateProjectStatus();
            }, 1000);
        },

        // initTagify() {
        //     if (!this.isEditMode) return;
        //     const input = document.getElementById('team_tags');
        //     if (!input) return;
        //     // Destroy previous Tagify instance if exists
        //     if (input._tagify) {
        //         input._tagify.destroy();
        //     }
        //     // Gán giá trị team đã chọn
        //     const tags = (this.project.team_list || []).map(t => ({ value: t.name, id: t.id }));
        //     // Danh sách tất cả team cho whitelist
        //     const whitelist = this.filteredTeams.map(t => ({ value: t.name, id: t.id }));
        //     this.tagify = new Tagify(input, {
        //         whitelist: whitelist,
        //         enforceWhitelist: true,
        //         dropdown: { enabled: 0 }
        //     });
        //     this.tagify.addTags(tags);
        //     // Xử lý khi xóa team thì xóa member của team đó
           
        initProjectTagsTagify() {
            const input = document.getElementById('project_tags');
            if (!input) return;
            
            // Destroy previous Tagify instance if exists
            if (input._tagify) {
                input._tagify.destroy();
            }
            
            // Initialize Tagify for project tags
            const tagify = new Tagify(input );
            
            // // Add existing tags if any
            // if (this.project.tags) {
            //     const tags = this.project.tags.split(',').map(tag => tag.trim()).filter(tag => tag);
            //     tagify.addTags(tags);
            // }
            
            // // Store reference
            this.projectTagsTagify = tagify;
            
            // Add event listeners for auto-save
            tagify.on('add', () => {
                this.updateTags();
            });
            
            tagify.on('remove', () => {
                this.updateTags();
            });
            
            tagify.on('change', () => {
                this.updateTags();
            });
        },
        reinitProjectDatePickersOnLocaleChange() {
            if (!this.isEditMode || !this.project) return;
            const pickerIds = [
                'start_date_picker',
                'end_date_picker',
                'caily_nouki_picker',
                'guis_nouki_picker',
                'actual_end_date_picker'
            ];
            pickerIds.forEach((elId) => {
                const el = document.getElementById(elId);
                if (el && el._flatpickr) {
                    el._flatpickr.destroy();
                }
            });
            if (this.customFields) {
                this.customFields.forEach((field, idx) => {
                    if (field.type !== 'datetime') return;
                    const el = document.getElementById('custom_datetime_' + idx);
                    if (el && el._flatpickr) {
                        el._flatpickr.destroy();
                    }
                    // Restore server wall-clock so re-init converts once for new locale
                    if (field._serverDatetime != null) {
                        field.value = field._serverDatetime;
                    }
                });
            }
            if (this._serverProjectDates) {
                ['start_date', 'end_date', 'caily_nouki', 'guis_nouki', 'actual_end_date'].forEach((key) => {
                    if (this._serverProjectDates[key] != null) {
                        this.project[key] = this._serverProjectDates[key];
                    }
                });
            }
            this.initDatePickers();
        },
        async confirmKadaiProject() {
            try {
                const swal = await Swal.fire({
                    title: 'プロジェクトを承認しますか？',
                    text: 'このプロジェクトを正式に受け入れて、通常のプロジェクトとして開始します。',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '承認',
                    cancelButtonText: 'キャンセル',
                    confirmButtonColor: '#28a745'
                });

                if (swal.isConfirmed) {
                    const formData = new FormData();
                    formData.append('id', this.projectId);
                    const response = await axios.post('/api/index.php?model=project&method=confirm', formData);
                    if (response.data && response.data.status === 'success') {
                        showMessage('プロジェクトを承認しました。');
                        setTimeout(() => { this.loadProject(); }, 0);
                    } else {
                        showMessage(response.data?.message || 'プロジェクトの承認に失敗しました。', true);
                    }
                }
            } catch (error) {
                console.error('Error confirming kadai project:', error);
                showMessage('プロジェクトの承認中にエラーが発生しました。', true);
            }
        },
        async copyProjectInfoToClipboard() {
            if (!this.project || !window.ProjectClipboard) return;
            try {
                const text = window.ProjectClipboard.buildText(this.project);
                const copied = await window.ProjectClipboard.copy(text);
                if (!copied) throw new Error('copy failed');
                const msg = (typeof translateText === 'function')
                    ? translateText('案件情報をコピーしました')
                    : '案件情報をコピーしました';
                if (typeof showMessage === 'function') {
                    showMessage(msg, false);
                }
            } catch (error) {
                console.error('Error copying project info:', error);
                const failMsg = (typeof translateText === 'function')
                    ? translateText('案件情報のコピーに失敗しました')
                    : '案件情報のコピーに失敗しました';
                if (typeof showMessage === 'function') {
                    showMessage(failMsg, true);
                }
            }
        },
        async joinProject() {
            if (!this.canJoinProject) return;
            
            try {
                // 省エネ departments only use 管理 (shown as 担当者): joining makes the user a manager
                const joinAsManager = this.isEnergyDepartmentProject;
                const swal = await Swal.fire({
                    title: 'プロジェクトに参加しますか？',
                    text: joinAsManager
                        ? 'このプロジェクトの担当者として参加します。'
                        : 'このプロジェクトのメンバーとして参加します。',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '参加',
                    cancelButtonText: 'キャンセル',
                    confirmButtonColor: '#0d6efd'
                });

                if (swal.isConfirmed) {
                    if (typeof USER_AUTH_ID === 'undefined') {
                        showMessage('ユーザー情報が取得できませんでした。', true);
                        return;
                    }
                    
                    const formData = new FormData();
                    formData.append('project_id', this.projectId);
                    formData.append('user_id', USER_AUTH_ID);
                    formData.append('role', joinAsManager ? 'manager' : 'member');
                    
                    const response = await axios.post('/api/index.php?model=project&method=addMemberApi', formData);
                    
                    if (response.data && response.data.status === 'success') {
                        showMessage('プロジェクトに参加しました。');
                        const self = this;
                        setTimeout(async () => {
                            await self.loadProject();
                            await self.loadPermission();
                        }, 0);
                    } else {
                        showMessage(response.data?.message || 'プロジェクトへの参加に失敗しました。', true);
                    }
                }
            } catch (error) {
                console.error('Error joining project:', error);
                showMessage('プロジェクトへの参加中にエラーが発生しました。', true);
            }
        },
        getCategoryName(id) {
            const cat = this.categories.find(c => String(c.id) === String(id));
            return cat ? cat.name : '-';
        },
        getContactName(id) {
            const contact = this.contacts.find(c => String(c.id) === String(id));
            return contact ? contact.name : '-';
        },
        async loadAllUsers() {
            // Load all users for selection modal
            try {
                const res = await axios.get('/api/index.php?model=user&method=getList');
                this.allUsers = res.data.list || [];
            } catch (e) {
                this.allUsers = [];
            }
        },
        openMemberSelect(type) {
            this.memberSelectType = type;
            this.showMemberModal = true;
            if (type === 'manager') {
                // Lấy toàn bộ user
                this.loadAllUsers();
                this.memberSelected = this.managers.map(m => m.userid);
            } else {
                // Chỉ lấy user thuộc các team đã chọn
                this.loadTeamMembersForModal();
                this.memberSelected = this.members.map(m => m.userid);
            }
        },
        async loadTeamMembersForModal() {
            // Lấy danh sách team đã chọn
            let teamIds = [];
            if (Array.isArray(this.project.team_list)) {
                teamIds = this.project.team_list.map(t => t.id);
            } else if (typeof this.project.teams === 'string') {
                teamIds = this.project.teams.split(',').map(id => id.trim()).filter(Boolean);
            }
            let allMembers = [];
            let seen = new Set();
            for (const teamId of teamIds) {
                try {
                    const res = await axios.get(`/api/index.php?model=team&method=get&id=${teamId}`);
                    if (res.data && Array.isArray(res.data.members)) {
                        for (const m of res.data.members) {
                            if (!seen.has(m.user_id)) {
                                allMembers.push({
                                    userid: m.user_id,
                                    user_name: m.user_name,
                                    user_image: m.user_image
                                });
                                seen.add(m.user_id);
                            }
                        }
                    }
                } catch (e) {}
            }
            this.allUsers = allMembers;
        },
        toggleMemberSelect(userId) {
            const idx = this.memberSelected.indexOf(userId);
            if (idx === -1) {
                this.memberSelected.push(userId);
            } else {
                this.memberSelected.splice(idx, 1);
            }
        },
        async confirmMemberSelect() {
            this.showMemberModal = false;
            if (this.memberSelectType === 'manager') {
                // Chỉ cập nhật UI, không lưu ngay
                this.managers = this.departmentUsers.filter(u => this.memberSelected.includes(String(u.user_id || u.id)));
            } else {
                this.members = this.departmentUsers.filter(u => this.memberSelected.includes(String(u.user_id || u.id)));
            }
        },
        // Notes functionality
        async loadNotes() {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getNotes&project_id=${this.projectId}`);
                if (response.data && response.data.status === 'success') {
                    // Notes content từ server có thể đã là HTML thuần (từ Quill), không cần decode
                    // Chỉ decode nếu có HTML entities bị escape
                    this.notes = (response.data.data || []).map(note => {
                        if (note.content && (note.content.indexOf('&lt;') !== -1 || note.content.indexOf('&gt;') !== -1)) {
                            // Nếu có HTML entities thì decode
                            note.content = this.decodeHtmlEntities(note.content);
                        }
                        return note;
                    });
                } else {
                    this.notes = [];
                }
            } catch (error) {
                console.error('Error loading notes:', error);
                this.notes = [];
            }
            this.$nextTick(() => this.initDetailNotesSortable());
        },
        ensureSortableLoadedForDetailNotes() {
            if (typeof Sortable !== 'undefined') {
                return Promise.resolve();
            }
            if (window.__sortableJsLoading) {
                return window.__sortableJsLoading;
            }
            var loader = window.AppLoader;
            if (loader && typeof loader.loadScript === 'function') {
                window.__sortableJsLoading = loader.loadScript(
                    'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js'
                ).catch(function(err) {
                    delete window.__sortableJsLoading;
                    throw err;
                });
                return window.__sortableJsLoading;
            }
            window.__sortableJsLoading = new Promise(function(resolve, reject) {
                var s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js';
                s.async = true;
                s.onload = function() { resolve(); };
                s.onerror = function() {
                    delete window.__sortableJsLoading;
                    reject(new Error('Failed to load Sortable'));
                };
                document.head.appendChild(s);
            });
            return window.__sortableJsLoading;
        },
        destroyDetailNotesSortable() {
            if (this._detailNotesSortable) {
                try { this._detailNotesSortable.destroy(); } catch (e) { /* ignore */ }
                this._detailNotesSortable = null;
            }
        },
        initDetailNotesSortable() {
            var self = this;
            this.destroyDetailNotesSortable();
            var el = this.$refs.detailNotesList;
            if (!el || !this.filteredNotes || this.filteredNotes.length < 2) return;
            // Filters active → don't allow reorder (would scramble global sort_order)
            if (this.noteFiltersActive) return;

            var bind = function() {
                if (typeof Sortable === 'undefined') return;
                var live = self.$refs.detailNotesList;
                if (!live || !live.isConnected) return;
                self._detailNotesSortable = Sortable.create(live, {
                    handle: '.note-drag-handle',
                    animation: 180,
                    ghostClass: 'note-sortable-ghost',
                    chosenClass: 'note-sortable-chosen',
                    dragClass: 'note-sortable-drag',
                    fallbackClass: 'note-sortable-fallback',
                    forceFallback: true,
                    fallbackOnBody: true,
                    draggable: '.detail-note-item',
                    onClone: function(evt) {
                        var clone = evt.clone;
                        if (!clone) return;
                        var trailing = clone.querySelector('.detail-note-trailing');
                        if (trailing) trailing.remove();
                    },
                    onEnd: function() {
                        self.persistDetailNotesOrder();
                    }
                });
            };

            if (typeof Sortable !== 'undefined') {
                bind();
                return;
            }
            if (this._detailNotesSortablePending) return;
            this._detailNotesSortablePending = true;
            this.ensureSortableLoadedForDetailNotes().then(function() {
                self._detailNotesSortablePending = false;
                bind();
            }).catch(function(err) {
                self._detailNotesSortablePending = false;
                console.error('Failed to load Sortable for detail notes', err);
            });
        },
        persistDetailNotesOrder() {
            var el = this.$refs.detailNotesList;
            if (!el) return;
            var ids = [];
            el.querySelectorAll('.detail-note-item').forEach(function(node) {
                var id = node.getAttribute('data-note-id');
                if (id) ids.push(id);
            });
            if (ids.length < 2) return;

            // Keep local array order in sync with DOM before reload
            var byId = {};
            (this.notes || []).forEach(function(n) { byId[String(n.id)] = n; });
            var reordered = [];
            ids.forEach(function(id) {
                if (byId[String(id)]) reordered.push(byId[String(id)]);
            });
            (this.notes || []).forEach(function(n) {
                if (ids.indexOf(String(n.id)) === -1 && ids.indexOf(n.id) === -1) {
                    reordered.push(n);
                }
            });
            this.notes = reordered;

            var formData = new FormData();
            formData.append('project_id', this.projectId);
            formData.append('note_ids', JSON.stringify(ids));
            axios.post('/api/index.php?model=project&method=reorderNotes', formData).catch(function(err) {
                console.error('Failed to reorder notes', err);
            });
        },
        async toggleNotePin(note, mode) {
            if (!note || !note.id) return;
            var method = mode === 'self' ? 'toggleNotePersonalPin' : 'toggleNoteImportant';
            try {
                var formData = new FormData();
                formData.append('id', note.id);
                var response = await axios.post('/api/index.php?model=project&method=' + method, formData);
                if (response.data && response.data.status === 'success') {
                    await this.loadNotes();
                } else {
                    this.showNotification('ピンの更新に失敗しました', 'error');
                }
            } catch (error) {
                console.error('Error toggling note pin:', error);
                this.showNotification('ピンの更新に失敗しました', 'error');
            }
        },
        buildEmptyEditingNote(overrides = {}) {
            return Object.assign({
                id: null,
                title: '',
                content: '',
                is_important: false,
                is_pinned_self: false,
                needs_confirmation: 0,
                display_column: '',
                label: '',
                labelSelect: '',
                labelOther: '',
                bg_color: '',
                visibility: 'all',
                user_id: null
            }, overrides);
        },
        applySavedLabelToEditingNote(labelValue) {
            const label = (labelValue != null && labelValue !== undefined) ? String(labelValue).trim() : '';
            this.editingNote.label = label;
            if (!label) {
                this.editingNote.labelSelect = '';
                this.editingNote.labelOther = '';
                return;
            }
            const predefined = (this.noteLabelSelectOptions || []).some(l => l && l.name === label);
            if (predefined) {
                this.editingNote.labelSelect = label;
                this.editingNote.labelOther = '';
            } else {
                this.editingNote.labelSelect = this.NOTE_LABEL_OTHER;
                this.editingNote.labelOther = label;
            }
        },
        onNoteLabelSelectChange() {
            if (this.editingNote.labelSelect !== this.NOTE_LABEL_OTHER) {
                this.editingNote.labelOther = '';
            }
        },
        onNotePinEveryoneChange() {
            if (this.editingNote && this.editingNote.is_important) {
                this.editingNote.is_pinned_self = false;
            }
        },
        onNotePinSelfChange() {
            if (this.editingNote && this.editingNote.is_pinned_self) {
                this.editingNote.is_important = false;
            }
        },
        resolveNoteLabelForSave() {
            if (this.editingNote.labelSelect === this.NOTE_LABEL_OTHER) {
                return String(this.editingNote.labelOther || '').trim().slice(0, 100);
            }
            return String(this.editingNote.labelSelect || '').trim().slice(0, 100);
        },
        openNoteModal(note = null) {
            // Already open (e.g. in edit mode): tear the modal and its Quill editor down first,
            // so the new note starts from a fresh editor instead of the previous note's.
            if (this.showNoteModal) {
                this.closeNoteModal();
                this.$nextTick(() => this.openNoteModal(note));
                return;
            }
            this.showNoteModal = true;
            this.isNoteEditMode = false;
            
            if (note) {
                // Edit existing note (decode HTML entities so &lt; shows as < in textarea)
                this.editingNote = this.buildEmptyEditingNote({
                    id: note.id,
                    title: note.title,
                    content: note.content ? this.decodeHtmlEntities(note.content) : '',
                    is_important: note.is_important == 1,
                    is_pinned_self: note.is_pinned_self == 1,
                    needs_confirmation: Number(note.needs_confirmation) || 0,
                    display_column: (note.display_column != null && note.display_column !== undefined) ? String(note.display_column) : '',
                    bg_color: note.bg_color || '',
                    visibility: note.visibility || 'all',
                    user_id: note.user_id
                });
                this.applySavedLabelToEditingNote(note.label);
            } else {
                // Create new note
                var defaultType = (typeof window !== 'undefined' && typeof window.NOTE_DEFAULT_TYPE !== 'undefined') ? parseInt(window.NOTE_DEFAULT_TYPE, 10) : 0;
                if (isNaN(defaultType)) defaultType = 0;
                this.editingNote = this.buildEmptyEditingNote({
                    needs_confirmation: defaultType
                });
            }
            // Init Quill editor nếu ở edit mode
            if (!note) {
                this.isNoteEditMode = true;
                this.$nextTick(() => {
                    this.initQuillNoteEditor();
                });
            }
        },
        closeNoteModal() {
            this.showNoteModal = false;
            this.isNoteEditMode = false;
            this.destroyQuillNoteEditor();
            this.editingNote = this.buildEmptyEditingNote();
            this.quillNoteContent = '';
        },
        noteVisibilityOption(value) {
            var opts = this.noteVisibilityOptions;
            return opts.find(function(o) { return o.value === value; }) || opts[0];
        },
        noteVisibilityLabel(value) {
            return this.noteVisibilityOption(value).text;
        },
        noteVisibilityIcon(value) {
            return this.noteVisibilityOption(value).icon;
        },
        getNoteDisplayColumnLabel(value) {
            if (!value) return '';
            var opts = this.noteDisplayColumnOptions || [];
            var o = opts.find(function(x) { return x.value === value; });
            return o ? o.text : value;
        },
        getNoteLabelStyle(labelName) {
            if (!labelName) return {};
            const found = (this.noteLabels || []).find(l => l && l.name === labelName);
            const color = (found && found.color) ? found.color : '#6c757d';
            return { '--note-label-color': color };
        },
        clearNoteFilters() {
            this.noteFilterLabel = '';
            this.noteFilterType = '';
            this.noteFilterVisibility = '';
            this.noteFilterPin = '';
        },
        async saveNote() {
            // Lấy nội dung từ Quill editor nếu có, nếu không dùng editingNote.content
            const rawContent = (this.quillNoteContent && this.quillNoteContent.trim()) || (this.editingNote.content || '').trim();
            if (!rawContent) {
                this.showNotification('内容を入力してください', 'error');
                return;
            }
            if (this.editingNote.labelSelect === this.NOTE_LABEL_OTHER && !this.resolveNoteLabelForSave()) {
                this.showNotification('その他のラベルを入力してください', 'error');
                return;
            }
            try {
                const formData = new FormData();
                formData.append('project_id', this.projectId);
                formData.append('content', rawContent);
                // Mutual exclusivity: prefer ピン（全員） if both somehow set
                var pinEveryone = !!this.editingNote.is_important && this.editingNote.visibility !== 'self';
                var pinSelf = !!this.editingNote.is_pinned_self && !pinEveryone;
                formData.append('is_important', pinEveryone ? 1 : 0);
                formData.append('is_pinned_self', pinSelf ? 1 : 0);
                formData.append('needs_confirmation', this.editingNote.needs_confirmation ? this.editingNote.needs_confirmation : 0);
                formData.append('display_column', this.editingNote.display_column || '');
                formData.append('label', this.resolveNoteLabelForSave());
                formData.append('bg_color', this.editingNote.bg_color || '');
                formData.append('visibility', this.editingNote.visibility || 'all');
                
                let response;
                if (this.editingNote.id) {
                    // Update existing note
                    formData.append('id', this.editingNote.id);
                    response = await axios.post('/api/index.php?model=project&method=updateNote', formData);
                } else {
                    // Create new note
                    response = await axios.post('/api/index.php?model=project&method=addNote', formData);
                }
                
                if (response.data && response.data.status === 'success') {
                    this.showNotification('メモが保存されました', 'success');
                    this.closeNoteModal();
                    await this.loadNotes();
                } else {
                    this.showNotification('メモの保存に失敗しました', 'error');
                }
            } catch (error) {
                console.error('Error saving note:', error);
                this.showNotification('メモの保存に失敗しました', 'error');
            }
        },
        async deleteNote(noteId) {
            if (!confirm('このメモを削除しますか？')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('id', noteId);
                
                const response = await axios.post('/api/index.php?model=project&method=deleteNote', formData);
                
                if (response.data && response.data.status === 'success') {
                    this.showNotification('メモが削除されました', 'success');
                    await this.loadNotes();
                } else {
                    this.showNotification('メモの削除に失敗しました', 'error');
                }
            } catch (error) {
                console.error('Error deleting note:', error);
                this.showNotification('メモの削除に失敗しました', 'error');
            }
        },
        getNotePreview(content) {
            if (!content) return '';
            // Remove HTML tags and limit to 100 characters
            const textContent = content.replace(/<[^>]*>/g, '');
            return textContent.length > 100 ? textContent.substring(0, 100) + '...' : textContent;
        },
        canDeleteNote(note) {
            // Only note creator or managers can delete notes
            return this.isManager || (note.user_id && String(note.user_id) === String(USER_ID));
        },
        canEditNote(note) {
            // Only note creator or managers can edit notes
            return this.isManager || (note.user_id && String(note.user_id) === String(USER_ID));
        },
        // Estimate status methods
        getEstimateStatusLabel(status) {
            const statusObj = this.findBusinessStatusOption(this.businessEnergyEstimateStatuses, status)
                || this.findBusinessStatusOption(this.businessEstimateStatuses, status)
                || this.estimateStatuses.find(s => s.value === status);
            return statusObj ? statusObj.label : '未発行';
        },
        getEstimateStatusBadgeClass(status) {
            const statusObj = this.findBusinessStatusOption(this.businessEnergyEstimateStatuses, status)
                || this.findBusinessStatusOption(this.businessEstimateStatuses, status)
                || this.estimateStatuses.find(s => s.value === status);
            return statusObj ? `bg-${statusObj.color}` : 'bg-secondary';
        },
        getEstimateStatusButtonClass(status) {
            const statusObj = this.findBusinessStatusOption(this.businessEnergyEstimateStatuses, status)
                || this.findBusinessStatusOption(this.businessEstimateStatuses, status)
                || this.estimateStatuses.find(s => s.value === status);
            return statusObj ? `btn-${statusObj.color}` : 'btn-secondary';
        },
        selectEstimateStatus(status) {
            if (!this.canEditBusinessDocuments || !this.project) return;
            this.syncBusinessDocumentDatesFromPickers();
            const normalized = this.normalizeBusinessDocumentStatusValue(status);
            if (normalized === '発行済' && !this.isEstimateDocumentFieldsComplete()) {
                this.businessDocumentError = this.getEstimateDocumentFieldsValidationError();
                this.hideBusinessDocumentStatusDropdown('#estimateStatusDropdown');
                return;
            }
            if (this.normalizeBusinessDocumentStatusValue(this.project.estimate_status) === normalized) {
                this.hideBusinessDocumentStatusDropdown('#estimateStatusDropdown');
                return;
            }
            this.project.estimate_status = normalized;
            if (normalized === '無償') {
                this.project.amount = 0;
            }
            this.businessDocumentError = '';
            this.scheduleBusinessDocumentUpdate();
            this.hideBusinessDocumentStatusDropdown('#estimateStatusDropdown');
        },
        
        // Quotation status methods
        getQuotationStatusLabel(status) {
            const statusLabels = {
                '下書き': '下書き',
                '発行済み': '発行済み',
                '承認済み': '承認済み',
                '却下': '却下',
                '調整': '調整',
                'キャンセル': 'キャンセル',
                '未発行': '未発行'
            };
            return statusLabels[status] || '未発行';
        },
        getQuotationStatusBadgeClass(status) {
            const statusClasses = {
                '下書き': 'bg-secondary',
                '発行済み': 'bg-primary',
                '承認済み': 'bg-success',
                '却下': 'bg-danger',
                '調整': 'bg-warning',
                'キャンセル': 'bg-dark',
                '未発行': 'bg-light text-dark'
            };
            return statusClasses[status] || 'bg-light text-dark';
        },
        
        // Invoice status methods
        getInvoiceStatusLabel(status) {
            const statusObj = this.findBusinessStatusOption(this.businessEnergyInvoiceStatuses, status)
                || this.findBusinessStatusOption(this.businessInvoiceStatuses, status)
                || this.invoiceStatuses.find(s => s.value === status);
            return statusObj ? statusObj.label : '未発行';
        },
        getInvoiceStatusBadgeClass(status) {
            const statusObj = this.findBusinessStatusOption(this.businessEnergyInvoiceStatuses, status)
                || this.findBusinessStatusOption(this.businessInvoiceStatuses, status)
                || this.invoiceStatuses.find(s => s.value === status);
            return statusObj ? `bg-${statusObj.color}` : 'bg-secondary';
        },
        getInvoiceStatusButtonClass(status) {
            const statusObj = this.findBusinessStatusOption(this.businessEnergyInvoiceStatuses, status)
                || this.findBusinessStatusOption(this.businessInvoiceStatuses, status)
                || this.invoiceStatuses.find(s => s.value === status);
            return statusObj ? `btn-${statusObj.color}` : 'btn-secondary';
        },
        selectInvoiceStatus(status) {
            if (!this.canEditBusinessDocuments || !this.project) return;
            this.syncBusinessDocumentDatesFromPickers();
            const normalized = this.normalizeBusinessDocumentStatusValue(status);
            if (normalized === '発行済' && !this.isInvoiceDocumentFieldsComplete()) {
                this.businessDocumentError = this.getInvoiceDocumentFieldsValidationError();
                this.hideBusinessDocumentStatusDropdown('#invoiceStatusDropdown');
                return;
            }
            if (this.normalizeBusinessDocumentStatusValue(this.project.invoice_status) === normalized) {
                this.hideBusinessDocumentStatusDropdown('#invoiceStatusDropdown');
                return;
            }
            this.project.invoice_status = normalized;
            if (normalized === '無償') {
                this.project.invoice_amount = 0;
            }
            this.businessDocumentError = '';
            this.scheduleBusinessDocumentUpdate();
            this.hideBusinessDocumentStatusDropdown('#invoiceStatusDropdown');
        },
        getPaymentStatusLabel(status) {
            const statusObj = this.paymentStatuses.find(s => s.value === status);
            return statusObj ? statusObj.label : '未入金';
        },
        getPaymentStatusBadgeClass(status) {
            const statusObj = this.paymentStatuses.find(s => s.value === status);
            return statusObj ? `bg-${statusObj.color}` : 'bg-secondary';
        },
        getPaymentStatusButtonClass(status) {
            const statusObj = this.paymentStatuses.find(s => s.value === status);
            return statusObj ? `btn-${statusObj.color}` : 'btn-secondary';
        },
        selectPaymentStatus(status) {
            this.project.payment_status = status;
            this.scheduleBusinessDocumentUpdate();
            const dropdownElement = document.querySelector('#paymentStatusDropdown');
            if (dropdownElement) {
                const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                if (dropdown) {
                    dropdown.hide();
                }
            }
        },
        initQuillNoteEditor() {
            if (this.quillNoteInstance || !this.isNoteEditMode || !this.showNoteModal) return;
            ensureProjectDetailQuill().then(() => {
                if (this.quillNoteInstance || !this.isNoteEditMode || !this.showNoteModal) return;
                setTimeout(() => {
                    if (this.quillNoteInstance || !this.isNoteEditMode || !this.showNoteModal) return;
                    const toolbarOptions = [
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ color: [] }, { background: [] }],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        [{ header: '1' }, { header: '2' }, 'blockquote'],
                        ['link', 'clean']
                    ];
                    const el = document.getElementById('quill_note_content_detail');
                    if (!el || typeof Quill === 'undefined') return;
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
                        const html = this.decodeHtmlEntities ? this.decodeHtmlEntities(this.editingNote.content) : this.editingNote.content;
                        this.quillNoteInstance.root.innerHTML = html;
                    }
                    this.quillNoteContent = this.quillNoteInstance.getSemanticHTML();
                    this.quillNoteInstance.on('text-change', () => {
                        this.quillNoteContent = this.quillNoteInstance.getSemanticHTML();
                    });
                }, 200);
            }).catch((err) => {
                console.error('Failed to load Quill for note editor:', err);
            });
        },
        destroyQuillNoteEditor() {
            if (this.quillNoteInstance) {
                try {
                    this.quillNoteInstance = null;
                } catch (e) {}
            }
            this.quillNoteContent = '';
        },
        decodeNoteHtml(str) {
            if (!str) return '';
            // Nếu đã là HTML thuần thì không cần decode
            // Chỉ decode nếu có HTML entities
            if (str.indexOf('&lt;') !== -1 || str.indexOf('&gt;') !== -1 || str.indexOf('&amp;') !== -1) {
                const txt = document.createElement('textarea');
                txt.innerHTML = str;
                return txt.value;
            }
            return str;
        },
        
        removeImagePlaceholder() {
            try {
                if (this.quillInstance && this.quillInstance.root) {
                    const content = this.quillInstance.getContents();
                    let placeholderIndex = -1;
                    
                    // Tìm vị trí của placeholder
                    for (let i = 0; i < content.ops.length; i++) {
                        if (content.ops[i].insert === '📷') {
                            placeholderIndex = i;
                            break;
                        }
                    }
                    
                    if (placeholderIndex !== -1) {
                        // Xóa placeholder
                        this.quillInstance.deleteText(placeholderIndex, 1);
                    }
                }
            } catch (error) {
                console.error('Error removing placeholder:', error);
            }
        },
        getDepartmentCustomFieldSetName(id) {
            const set = this.departmentCustomFieldSets.find(s => String(s.id) === String(id));
            return set ? set.name : '-';
        },
        // Mention functionality methods
        async loadMentionUsers() {
            try {
                // Load department users and administrators
                const response = await axios.get(`/api/index.php?model=user&method=getMentionUsers&department_id=${this.project.department_id}`);
                this.mentionUsers = response.data || [];
            } catch (error) {
                console.error('Error loading mention users:', error);
                this.mentionUsers = [];
            }
        },
        /** 履歴 data is fetched only for an open history modal. */
        refreshLogsIfOpen() {
            if (this.showGeneralLogModal || this.showBusinessDocumentLogModal) {
                this.loadLogs();
            }
        },
        async loadLogs() {
            this.logsLoading = true;
            try {
                const res = await axios.get(`/api/index.php?model=project&method=getLogs&project_id=${this.projectId}`);
                if (res.data && Array.isArray(res.data)) {
                    this.logs = res.data;
                } else {
                    this.logs = [];
                }
            } catch (e) {
                this.logs = [];
            } finally {
                this.logsLoading = false;
                this.logsLoaded = true;
            }
        },
        isBusinessDocumentLog(log) {
            if (!log) return false;
            if (log.action && BUSINESS_DOCUMENT_LOG_ACTIONS.has(log.action)) {
                return true;
            }
            const note = String(log.note || '');
            return /見積|請求|入金|決済|金額変更|領収書/.test(note);
        },
        openBusinessDocumentLogModal() {
            this.loadLogs();
            this.showBusinessDocumentLogModal = true;
        },
        closeBusinessDocumentLogModal() {
            this.showBusinessDocumentLogModal = false;
        },
        openGeneralLogModal() {
            this.loadLogs();
            this.showGeneralLogModal = true;
        },
        closeGeneralLogModal() {
            this.showGeneralLogModal = false;
        },
        hasLogValue(value) {
            return value !== null && value !== undefined && String(value).trim() !== '';
        },
        getBusinessDocumentLogValue(log, field) {
            const value = log[field];
            if (!this.hasLogValue(value)) return '—';
            if (log.action && log.action.endsWith('_date_updated')) {
                return this.formatShortDateTime(value) || value;
            }
            if (log.action === 'amount_updated' || log.action === 'invoice_amount_updated' || log.action === 'payment_amount_updated') {
                const num = Number(value);
                if (!isNaN(num)) {
                    return this.formatCurrency(num);
                }
            }
            return this.getLogBadgeLabel(log, field) || value;
        },
        loadCurrentUser() {
            // Set basic user data from global variables
            this.currentUser.userid = typeof USER_ID !== 'undefined' ? USER_ID : null;
            this.currentUser.realname =  typeof USER_NAME !== 'undefined' ? USER_NAME : 'User';
            this.currentUser.user_image = typeof USER_IMAGE !== 'undefined' ? USER_IMAGE : null;
        },
        historyIcon(action) {
            switch(action) {
                case 'created': return 'fa fa-pencil-alt text-primary';
                case 'approved': return 'fa fa-check-circle text-success';
                case 'rejected': return 'fa fa-times-circle text-danger';
                case 'draft': return 'fa fa-edit text-warning';
                case 'updated': return 'fa fa-sync text-info';
                case 'status_changed': return 'fa fa-random text-primary';
                case 'comment': return 'fa fa-comment-dots text-secondary';
                case 'member_added': return 'fa fa-user-plus text-success';
                case 'member_removed': return 'fa fa-user-minus text-danger';
                case 'deleted': return 'fa fa-trash text-danger';
                case 'date_updated': return 'fa fa-calendar-alt text-info';
                case 'priority_updated': return 'fa fa-exclamation text-warning';
                case 'amount_updated':
                case 'invoice_amount_updated':
                case 'payment_amount_updated':
                    return 'fa fa-yen-sign text-success';
                case 'estimate_status_updated':
                case 'estimate_date_updated':
                case 'estimate_number_updated':
                    return 'fa fa-file-invoice text-info';
                case 'invoice_status_updated':
                case 'invoice_date_updated':
                case 'invoice_number_updated':
                    return 'fa fa-file-alt text-primary';
                case 'payment_status_updated':
                case 'payment_date_updated':
                case 'payment_amount_updated':
                case 'receipt_number_updated':
                    return 'fa fa-money-bill-wave text-success';
                case 'payment_note_updated':
                    return 'fa fa-sticky-note text-secondary';
                default: return 'fa fa-history';
            }
        },
        actionLabel(action) {
            switch(action) {
                case 'created': return '作成';
                case 'approved': return '承認';
                case 'rejected': return '却下';
                case 'draft': return '下書き';
                case 'updated': return '更新';
                case 'status_changed': return 'ステータス変更';
                case 'comment': return 'コメント';
                case 'member_added': return 'メンバー追加';
                case 'member_removed': return 'メンバー削除';
                default: return action;
            }
        },
        isProjectStatusKey(value) {
            return !!value && this.statuses.some(s => s.value === value);
        },
        getLogNote(log) {
            if (!log || !log.note) return '';
            if (log.action === 'status_changed' || log.note === 'ステータスを変更' || log.note.indexOf('ステータス変更') === 0) {
                return this.translateLabel('ステータス変更');
            }
            return log.note;
        },
        getLogBadgeClass(log, field) {
            const value = log[field];
            if (log.action === 'status_changed' || this.isProjectStatusKey(value)) {
                return 'badge ' + this.getStatusBadgeClass(value);
            }
            if (log.action === 'priority_updated') {
                return 'badge ' + this.getPriorityBadgeClass(log[field]);
            }
            return field === 'value1' ? 'badge bg-secondary' : 'badge bg-primary';
        },
        getLogBadgeLabel(log, field) {
            const value = log[field];
            if (!value) return '';
            if (log.action === 'status_changed' || this.isProjectStatusKey(value)) {
                return this.getStatusLabel(value);
            }
            if (log.action === 'priority_updated') {
                return this.getPriorityLabel(value);
            }
            return value;
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
            this.showNotification(`アップロードに失敗しました: ${fileName}`, 'error');
        },
        
        // Comment functionality moved to CommentComponent
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





    },
    watch: {
        // Leaving edit mode removes the editor's DOM: drop the Quill instance with it,
        // otherwise the next 編集 / メモを追加 reuses an instance bound to a detached node.
        isNoteEditMode(editing) {
            if (!editing) {
                this.destroyQuillNoteEditor();
            }
        },
        canCommentProject(newVal) {
            if (newVal) {
                this.$nextTick(() => this.armCommentsLazyMount());
            }
        },
        canEditBusinessDocuments(newVal) {
            if (newVal) {
                this.$nextTick(() => this.initBusinessDocumentDatePickers());
            }
        },
        'project.department_id': function() {
            // Load companies and contacts for the selected department
            if (this.project && this.project.department_id) {
                this.loadCompaniesByCategory();
                this.loadContactsByCompany();
            } else {
                // Clear companies and contacts when no department is selected
                this.companies = [];
                this.contacts = [];
            }
        },
        'allDepartmentCustomFieldSets': {
            handler(newVal, oldVal) {
                // When department custom field sets change, reload custom fields if in edit mode
                if (this.isEditMode && newVal && newVal.length > 0) {
                    this.$nextTick(() => {
                        const saved = this.parseSavedCustomFieldList(this.project.custom_fields);
                        const allFieldsFromSets = this.collectCustomFieldsFromSets(newVal);
                        if (allFieldsFromSets.length > 0) {
                            this.customFields = this.mapSetFieldsToCustomFields(allFieldsFromSets, saved);
                            this.$nextTick(() => {
                                setTimeout(() => {
                                    this.initCustomFieldDatePickers();
                                }, 100);
                            });
                        }
                    });
                }
            },
            deep: true
        },
        'customFields': {
            handler(newVal, oldVal) {
                // Keep value and valueArr in sync for checkboxes
                if (!this.customFields || this.customFields.length === 0) return;
                
                // Only process if there are actual changes
                if (JSON.stringify(newVal) === JSON.stringify(oldVal)) return;
                
                this.customFields.forEach((field, idx) => {
                    if (field.type === 'checkbox' || field.type === 'checkbox_select_all') {
                        // If valueArr changes, update value
                        if (Array.isArray(field.valueArr)) {
                            this.customFields[idx].value = field.valueArr.join(',');
                        } else if (typeof field.value === 'string') {
                            this.customFields[idx].valueArr = field.value.split(',').map(s => s.trim()).filter(Boolean);
                        }
                    }
                });
            },
            deep: true
        },
        noteFilterLabel() {
            this.$nextTick(() => this.initDetailNotesSortable());
        },
        noteFilterType() {
            this.$nextTick(() => this.initDetailNotesSortable());
        },
        noteFilterVisibility() {
            this.$nextTick(() => this.initDetailNotesSortable());
        },
        noteFilterPin() {
            this.$nextTick(() => this.initDetailNotesSortable());
        },
    },
    beforeUnmount() {
        this.destroyDetailNotesSortable();
    },
    async mounted() {
        // D1: permission ∥ getById(+secondary); notes/logs/categories ∥ critical path (projectId từ URL)
        this.loadCurrentUser();
        await Promise.all([
            this.loadPermission(),
            this.loadProject(),
            this.loadCategories(),
            this.loadNotes(),
        ]);
        // Needs department_id from getById
        await Promise.all([
            this.loadDepartment(),
            this.loadDepartmentCustomFieldSets(),
        ]);
        this.initTooltips();

        // Dịch [data-i18n] sau khi Vue đã vẽ DOM (tránh text không đúng ngôn ngữ khi load)
        this.$nextTick(() => {
            if (typeof window.applyDataI18n === 'function') {
                var appEl = document.getElementById('app');
                if (appEl) window.applyDataI18n(appEl);
            }
        });

        this._refreshDatePickersForLocale = () => {
            this.reinitBusinessDocumentDatePickersOnLocaleChange();
            this.reinitProjectDatePickersOnLocaleChange();
            if (this.isEditMode) {
                this.destroyYoteiMonthPickers();
                this.initYoteiMonthPickers();
            }
            this.initVietnamTimeTooltips();
            if (typeof window.applyDataI18n === 'function') {
                const appEl = document.getElementById('app');
                if (appEl) window.applyDataI18n(appEl);
            }
        };
        this._onI18nLanguageChanged = () => {
            this.yoteiPartOptionsTick++;
            this.$forceUpdate();
            this.$nextTick(() => this._refreshDatePickersForLocale());
        };
        if (typeof i18next !== 'undefined' && i18next.on) {
            i18next.on('languageChanged', this._onI18nLanguageChanged);
            // One rebuild after i18n is ready (avoid stacking reinits that re-parse dates)
            if (i18next.isInitialized) {
                this.$nextTick(() => this._refreshDatePickersForLocale());
            } else {
                const onInit = () => {
                    i18next.off('initialized', onInit);
                    this.$nextTick(() => this._refreshDatePickersForLocale());
                };
                i18next.on('initialized', onInit);
            }
        } else {
            this.$nextTick(() => this._refreshDatePickersForLocale());
        }

        // Initialize mention manager (lazy — mention.js loads with comment assets)
        this.$nextTick(() => {
            if (typeof MentionManager !== 'undefined') {
                if (window.mentionManager) {
                    if (this.project && this.project.department_id) {
                        window.mentionManager.setDepartmentId(this.project.department_id);
                    }
                    window.mentionManager.bindToInputs();
                } else {
                    window.mentionManager = new MentionManager({
                        departmentId: this.project ? this.project.department_id : null
                    });
                }
            }
            
            this.initProjectTagsTagify();
            this.armCommentsLazyMount();
        });
        
        // Add beforeunload event listener
        //window.addEventListener('beforeunload', this.handleBeforeUnload);
        
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

        window.addEventListener('ai-action-success', (event) => {
            const { action, actions, response } = event.detail || {};
            
            // Support both single action (backward compatible) and multiple actions
            const allActions = actions && Array.isArray(actions) ? actions : (action ? [action] : []);
            
            if (allActions.length === 0) return;
            
            // Check if any action affects the current project
            let shouldReload = false;
            const memberManagerTeamActions = [
                'project_add_member', 'project_add_manager',
                'project_remove_member', 'project_remove_manager',
                'project_set_teams', 'project_add_team', 'project_remove_team',
                'project_clear_teams', 'project_clear_members', 'project_clear_managers',
                'project_clear_all_members', 'project_clear_all', 'project_add_team_members'
            ];
            
            for (const act of allActions) {
                const pid = act && (act.id || (act.params && act.params.project_id));
                if (pid && String(pid) === String(this.projectId)) {
                    // If action affects members/managers/teams, reload data
                    if (memberManagerTeamActions.includes(act.type)) {
                        shouldReload = true;
                        break;
                    }
                    // For other project actions, also reload
                    if (act.type && act.type.startsWith('project_')) {
                        shouldReload = true;
                        break;
                    }
                }
            }
            
            if (shouldReload) {
                // Reload project data and members to reflect changes
                this.loadProject().then(() => {
                    // Also reload members separately to ensure avatars are updated
                    this.loadMembers();
                });
            }
        });

        // Start timer to update time remaining every minute
        // this.timeRemainingTimer = setInterval(() => {
        //     // Force Vue to re-render the time remaining badge
        //     this.$forceUpdate();
        // }, 60000); // Update every minute

        // Initialize Tagify for team selection
        // if (document.getElementById('team_tags')) {
        //     new Tagify(document.getElementById('team_tags'), {
        //         whitelist: (this.project.team_list || []).map(team => ({ value: team.id, text: team.name })),
        //         enforceWhitelist: true,
        //         mode: 'select',
        //         templates: {
        //             tag: function(tagData) {
        //                 return `
        //                     <tag title="${tagData.value}"
        //                         contenteditable='false'
        //                         spellcheck='false'
        //                         class='tagify__tag ${tagData.class ? tagData.class : ""}'
        //                         tabindex="0"
        //                         role="option"
        //                         aria-label="${tagData.value}"
        //                         aria-selected="false">
        //                         <x title='' class='tagify__tag__removeBtn' role='button' aria-label='remove tag'></x>
        //                         <div>
        //                             <div class='tagify__tag__avatar-wrap'>
        //                                 <img onerror="this.style.visibility='hidden'" src="">
        //                             </div>
        //                             <div class='tagify__tag__text'>
        //                                 <span>${tagData.text}</span>
        //                             </div>
        //                         </div>
        //                     </tag>
        //                 `
        //             },
        //             dropdownItem: function(tagData) {
        //                 return `
        //                     <div class='tagify__dropdown__item ${tagData.class ? tagData.class : ""}'
        //                          tabindex="0"
        //                          role="option"
        //                          aria-label="${tagData.value}">
        //                         <span>${tagData.text}</span>
        //                     </div>
        //                 `
        //             }
        //         }
        //     });
        // }
        this.addZoomToDescriptionImages();
        
        // Auto-refresh project information and history every 60 seconds if not in edit mode
        // A hidden tab does not refresh; it catches up as soon as it is shown again.
        let lastAutoRefreshAt = Date.now();
        const autoRefresh = () => {
            if (document.hidden) return;
            if (!this.isEditMode && !this.hasPendingBusinessDocumentChanges()) {
                lastAutoRefreshAt = Date.now();
                this.loadProject({ silent: true });
                this.refreshLogsIfOpen();
            }
        };
        this.autoRefreshTimer = setInterval(autoRefresh, 60000); // 60 seconds
        this._onAutoRefreshVisibility = () => {
            if (!document.hidden && Date.now() - lastAutoRefreshAt >= 60000) autoRefresh();
        };
        document.addEventListener('visibilitychange', this._onAutoRefreshVisibility);

    },
    updated() {
        this.$nextTick(() => {
            this.initTooltips();
            this.addZoomToDescriptionImages();
        });
    },
    beforeUnmount() {
        if (typeof i18next !== 'undefined' && i18next.off && this._onI18nLanguageChanged) {
            i18next.off('languageChanged', this._onI18nLanguageChanged);
            i18next.off('initialized', this._onI18nLanguageChanged);
        }
        // Clean up timers
        if (this.timeRemainingTimer) {
            clearInterval(this.timeRemainingTimer);
        }
        if (this._onAutoRefreshVisibility) {
            document.removeEventListener('visibilitychange', this._onAutoRefreshVisibility);
        }
        if (this.autoRefreshTimer) {
            clearInterval(this.autoRefreshTimer);
        }
        if (this.businessDocumentSaveHideTimer) {
            clearTimeout(this.businessDocumentSaveHideTimer);
        }
        if (this._commentsIo) {
            try { this._commentsIo.disconnect(); } catch (e) {}
            this._commentsIo = null;
        }
    }
});

// Register Comment Component (async — Quill + mention + comment-component.js)
vueApp.component(
    'comment-component',
    Vue.defineAsyncComponent({
        loader: function () {
            return ensureProjectDetailCommentAssets().then(function () {
                if (!window.CommentComponent) {
                    return Promise.reject(new Error('CommentComponent missing after load'));
                }
                return window.CommentComponent;
            });
        },
        delay: 100,
        timeout: 30000,
        loadingComponent: {
            template: '<div class="text-center text-muted py-3"><i class="fa fa-spinner fa-spin"></i></div>'
        },
        errorComponent: {
            template: '<div class="text-danger small">コメントの読み込みに失敗しました。</div>'
        }
    })
);

// Mount the Vue app
vueApp.mount('#app'); 