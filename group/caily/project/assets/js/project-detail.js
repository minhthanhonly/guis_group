const { createApp } = Vue;

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
    { key: 'parent_type2', label: '種類2' },
    { key: 'start_date', label: '開始日' },
    { key: 'caily_nouki', label: 'CAILY納期' },
    { key: 'guis_nouki', label: 'GUIS納期' },
    { key: 'end_date', label: '終了日' },
    { key: 'project_order_type', label: '受注形態' },
    { key: 'priority', label: '優先度' },
    { key: 'amount', label: '総額' },
    { key: 'customer_info', label: '顧客情報' },
    { key: 'parent_guis_receiver', label: 'GUIS 受付者' }
];

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
    'invoice_status',
    'invoice_date',
    'invoice_amount',
    'invoice_number',
    'payment_status',
    'payment_date',
    'payment_amount',
    'receipt_number',
    'payment_note',
];

const BUSINESS_DOCUMENT_DATE_FIELDS = ['estimate_date', 'invoice_date'];

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
            editingNote: {
                id: null,
                title: '',
                content: '',
                is_important: false,
                needs_confirmation: false,
                display_column: '',
                user_id: null
            },
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
        /** Current project belongs to 省エネ計算 */
        isEnergyDepartmentProject() {
            const energyName = (window.EnergyDrawingShare && window.EnergyDrawingShare.ENERGY_DEPT_NAME) || '省エネ計算';
            return String(this.project && this.project.department_name || '').trim() === energyName;
        },
        /** Same-parent has active 省エネ計算 sibling (or flag from API). */
        hasEnergyDrawingShareContext() {
            if (!this.project) return false;
            if (this.isEnergyDepartmentProject) return true;
            if (this.project.has_energy_sibling === 1
                || this.project.has_energy_sibling === true
                || this.project.has_energy_sibling === '1') {
                return true;
            }
            const energyName = (window.EnergyDrawingShare && window.EnergyDrawingShare.ENERGY_DEPT_NAME) || '省エネ計算';
            const siblings = Array.isArray(this.parentSiblingProjects) ? this.parentSiblingProjects : [];
            return siblings.some((p) => p
                && String(p.department_name || '').trim() === energyName
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
        editableStatuses() {
            if (!this.isCailyBranchUser || this.canViewEndDate) {
                return this.statuses;
            }
            return this.statuses.filter(function(s) { return s.value !== 'completed'; });
        },
        isManager() {
            if(USER_ROLE == `administrator`) return true;
            if (!this.managers) return false;
            return this.managers.some(m => String(m.user_id) === String(USER_AUTH_ID)) || this.permission.can_manage_project || (this.permission.rule && this.permission.rule.project_edit == 1);
        },
        filteredTeams() {
            if (!this.project || !this.project.department_id) return this.allTeams;
            return this.allTeams.filter(team => String(team.department_id) === String(this.project.department_id));
        },
        selectedCustomFieldSet() {
            // Always return null since we now use all sets from department
            return null;
        },
        allDepartmentCustomFieldSets() {
            // Return all custom field sets for the department
            if (!this.project || !this.project.department_id) return [];
            return this.departmentCustomFieldSets || [];
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
        canAddProject() {
            return this.permission.can_manage_project || (this.permission.rule && this.permission.rule.project_add == 1);
        },
        canDeleteProject() {
            return this.permission.can_manage_project || (this.permission.rule && this.permission.rule.project_delete == 1);
        },
        canCommentProject() {
            return this.permission.can_manage_project || (this.permission.rule && this.permission.rule.project_comment == 1);
        },
        /** Non-CAILY always; CAILY when project_view_end_date (view-only). */
        canViewEndDate() {
            if (!this.isCailyBranchUser) return true;
            if (this.isAdministrator()) return true;
            const rule = this.permission && this.permission.rule;
            return !!(rule && (rule.project_view_end_date == 1 || rule.project_view_end_date === '1'));
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
        isAdministrator() {
            return typeof USER_ROLE !== 'undefined' && USER_ROLE === 'administrator';
        },
        hasDirectorPermission(field) {
            if (this.isAdministrator()) return true;
            if (!this.permission || this.permission.length === 0) return false;
            return this.permission.some((rule) => rule[field] === '1' || rule[field] === 1);
        },
        async loadPermission() {
            try {
                const response = await axios.get('/api/index.php?model=task&method=getPermission&project_id=' + this.projectId);
                this.permission = response.data || [];
            } catch (error) {
                console.error('Error loading permission:', error);
            }
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
        
        // Phương thức để dịch label động
        translateLabel(label) {
            if (typeof i18next !== 'undefined' && i18next.isInitialized) {
                return i18next.t(label) || label;
            }
            return label;
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
                }
                // Cho phép AI lấy dữ liệu dự án hiện tại đang xem
                if (typeof window !== 'undefined' && this.project) {
                    window.__chatPageContext = window.__chatPageContext || {};
                    window.__chatPageContext.project_id = this.projectId;
                    window.__chatPageContext.page = 'project_detail';
                    window.__chatPageContext.page_project = this.project;
                }
                // Load parent project information if this is a child project
                if (this.project.parent_project_id) {
                    await this.loadParentProjectInfo();
                }
                
                // Khởi tạo trạng thái CAILY納期状況 / GUIS納期状況 từ cột riêng trong DB
                this.project.caily_nouki_status = this.project.caily_nouki_status || '';
                this.project.guis_nouki_status = this.project.guis_nouki_status || '';
                this.syncYoteiDraftFromProject();

                if (businessDocSnapshot) {
                    this.applyBusinessDocumentSnapshot(businessDocSnapshot);
                } else {
                    this.normalizeBusinessDocumentFields();
                }
                
                this.calculateStats();
                this.loadTaskWorkloadStats();
                
                if (this.project.teams) {
                    this.loadTeamListByIds(this.project.teams);
                } else {
                    this.project.team_list = [];
                }
               
                this.loadMembers();
                // Ensure Tagify is updated after loading project and team_list
                this.$nextTick(() => { 
                    //this.initTagify(); 
                    this.setConnectedUsers();
                    this.initVietnamTimeTooltips();
                    this.initBusinessDocumentDatePickers();
                });
                
            } catch (error) {
                console.error('Error loading project:', error);
                alert('プロジェクトの読み込みに失敗しました。');
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

                const response = await axios.get(`/api/index.php?model=parentproject&method=getById&id=${this.project.parent_project_id}`);
                const parentProject = response.data;

                const parentCustomerId = String(parentProject.customer_id || '').trim();
                const childCustomerId = String(rawChildCustomer.customer_id || '').trim();
                this.project.show_child_customer_info = childCustomerId !== '' && childCustomerId !== parentCustomerId;
                if (this.project.show_child_customer_info) {
                    this.project.child_customer_company = rawChildCustomer.company_name;
                    this.project.child_customer_branch = rawChildCustomer.branch_name;
                    this.project.child_customer_contact = rawChildCustomer.contact_name;
                    if (childCustomerId) {
                        try {
                            const customerRes = await axios.get(`/api/index.php?model=customer&method=get&id=${childCustomerId}`);
                            if (customerRes.data && customerRes.data.status === 'success' && customerRes.data.data) {
                                const c = customerRes.data.data;
                                this.project.child_customer_company = c.company_name || '';
                                this.project.child_customer_branch = c.branch || '';
                                this.project.child_customer_contact = c.name || '';
                            }
                        } catch (customerErr) {
                            console.error('Error loading child project customer info:', customerErr);
                        }
                    }
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
                    await this.loadChildGuisReceiverDisplayName();
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
                this.project.type1 = parentProject.type1;
                this.project.type2 = parentProject.type2;
                
                // Additional fields from parent project
                this.project.guis_receiver = parentProject.guis_receiver; // GUIS　受付者
                this.project.structural_office = parentProject.structural_office; // 構造事務所
                this.project.materials = parentProject.materials; // 資料
                this.project.parent_requests = parentProject.requests || ''; // 依頼

                // Sibling children for 依頼 fulfillment badges (未作成)
                this.parentSiblingProjects = [];
                try {
                    const childrenRes = await axios.get(
                        `/api/index.php?model=parentproject&method=getChildProjects&parent_project_id=${this.project.parent_project_id}`
                    );
                    if (Array.isArray(childrenRes.data)) {
                        this.parentSiblingProjects = childrenRes.data;
                    }
                } catch (childrenErr) {
                    console.error('Error loading sibling projects for request fulfillment:', childrenErr);
                    this.parentSiblingProjects = [];
                }
                if (window.EnergyDrawingShare) {
                    this.project.has_energy_sibling = window.EnergyDrawingShare.hasEnergySavingSibling(
                        this.parentSiblingProjects,
                        this.project.id
                    );
                }
                
                // Load GUIS receiver display name if exists
                if (this.project.guis_receiver) {
                    await this.loadGuisReceiverDisplayName();
                }
                
            } catch (error) {
                console.error('Error loading parent project info:', error);
            }
        },
        
        async loadGuisReceiverDisplayName() {
            try {
                const response = await axios.get('/api/index.php?model=user&method=searchMembers');
                if (response.data && response.data.data) {
                    const user = response.data.data.find(u => u.userid === this.project.guis_receiver);
                    if (user) {
                        // Set display name for view mode
                        this.project.guis_receiver_display_name = user.realname;
                    }
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
                const response = await axios.get('/api/index.php?model=user&method=searchMembers');
                if (response.data && response.data.data) {
                    const user = response.data.data.find((u) => u.userid === userid);
                    this.project.child_guis_receiver_display_name = user ? user.realname : '';
                }
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
        async loadDepartment() {
            try {
                const response = await axios.get(`/api/index.php?model=department&method=get&id=${this.project.department_id}`);
                this.department = response.data;
            } catch (error) {
                console.error('Error loading department:', error);
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
                const response = await axios.get(`/api/index.php?model=task&method=list&project_id=${this.projectId}&include_subtasks=1`);
                const tasks = response.data || [];
                const kindMap = {};
                let totalWorkload = 0;
                tasks.forEach((t) => {
                    let kind = this.normalizeTaskKind(t.task_kind);
                    if (!kind) kind = '未設定';
                    const n = parseFloat(t.estimated_hours);
                    const hours = Number.isNaN(n) || n <= 0 ? 0 : n;
                    totalWorkload += hours;
                    if (!kindMap[kind]) {
                        kindMap[kind] = { kind, hours: 0, count: 0 };
                    }
                    kindMap[kind].hours += hours;
                    kindMap[kind].count += 1;
                });
                this.stats.totalWorkload = totalWorkload;
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
        getTimeRemaining() {
            if (!this.project || !this.project.end_date || this.project.status === 'completed' ||
                 this.project.status === 'deleted' ||
                 this.project.status === 'draft' || this.project.status === 'cancelled') {
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
        /** Remaining time for a given date (e.g. caily_nouki, guis_nouki). Returns null if status is draft/paused/cancelled. */
        getTimeRemainingForDate(dateStr) {
            if (!this.project || !dateStr) return null;
            if (['draft', 'paused', 'cancelled', 'completed', 'deleted'].includes(String(this.project.status || '').toLowerCase())) return null;
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
        /** Remaining time for a sibling project's deadline (uses sibling status, not current project). */
        getSiblingDeadlineRemaining(sibling, dateStr) {
            if (!sibling || !dateStr) return null;
            if (['draft', 'paused', 'cancelled', 'completed', 'deleted'].includes(String(sibling.status || '').toLowerCase())) {
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
        formatDateTime(datetime) {
            return formatProjectDateTimeForDisplay(datetime);
        },
        formatBusinessDocumentDateTime(key) {
            const serverVal = this.getBusinessDocumentServerDate(key);
            return formatProjectDateTimeForDisplay(serverVal || this.project?.[key]);
        },
        getProjectDateTimePlaceholder() {
            return getProjectDateTimePlaceholder();
        },
        /** Tooltip giờ VN khi hover lên giờ Nhật: "VN hh:ii" (dùng chung với main.js) */
        getVietnamTimeTooltip(jpDateTimeStr) {
            return typeof window.formatVietnamTimeTooltip === 'function' ? window.formatVietnamTimeTooltip(jpDateTimeStr) : '';
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
        formatCurrency(amount) {
            if (!amount) return '¥0';
            return '¥' + parseInt(amount).toLocaleString();
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
        getRequestBadgeClass(request) {
            const map = {
                '意匠': 'bg-primary',
                '設備': 'bg-info',
                '3D設備': 'bg-success',
                '省エネ': 'bg-warning',
                '3D': 'bg-secondary',
                'その他': 'bg-dark'
            };
            return map[String(request || '').trim()] || 'bg-secondary';
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
            const map = {
                '設備設計': '設備',
                '意匠設計': '意匠',
                '省エネ計算': '省エネ',
                '技術課設備': '3D設備'
            };
            return map[String(departmentName || '').trim()] || '';
        },
        isParentRequestFulfilled(requestType) {
            const type = String(requestType || '').trim();
            if (!type || !Array.isArray(this.parentSiblingProjects)) return false;
            return this.parentSiblingProjects.some((p) => {
                if (String(p.status || '') === 'deleted') return false;
                return this.mapDepartmentNameToRequestType(p.department_name) === type;
            });
        },
        getPriorityLabel(priority) {
            const p = this.priorities.find(p => p.value === priority);
            return p ? p.label : priority;
        },
        getPriorityBadgeClass(priority) {
            const p = this.priorities.find(p => p.value === priority);
            return `bg-${p?.color || 'secondary'}`;
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
                if (response.data && response.data.success !== false) {
                    if (window.EnergyDrawingShare && shareAnswer) {
                        window.EnergyDrawingShare.applyEnergyDrawingShareToProject(this.project, shareAnswer);
                    }
                    showMessage('ステータスの更新に完了しました。');
                } else {
                    showMessage('ステータスの更新に失敗しました。', true);
                }
            } catch (error) {
                console.error('Error updating status:', error);
                showMessage('ステータスの更新に失敗しました。', true);
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
                if (response.data) {
                    showMessage('優先度を更新しました。');
                }
            } catch (error) {
                console.error('Error updating priority:', error);
                showMessage('優先度の更新に失敗しました。', true);
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
        showAvatarImage(member) {
            return !!(member && !member.avatarError && this.getAvatarSrc(member) && member.avatarLoaded);
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
                if (response.data) {
                    showMessage('進捗率を更新しました。');
                }
            } catch (error) {
                console.error('Error updating progress:', error);
                showMessage('進捗率の更新に失敗しました。', true);
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
                const formData = new FormData();
                formData.append('id', this.projectId);
                formData.append('amount', this.project.amount || 0);
                formData.append('estimate_status', this.project.estimate_status || '未発行');
                formData.append('estimate_date', this.getBusinessDocumentDateForApi('estimate_date'));
                formData.append('estimate_number', this.project.estimate_number || '');
                formData.append('invoice_status', this.project.invoice_status || '未発行');
                formData.append('invoice_date', this.getBusinessDocumentDateForApi('invoice_date'));
                formData.append('invoice_amount', this.project.invoice_amount != null ? this.project.invoice_amount : 0);
                formData.append('invoice_number', this.project.invoice_number || '');
                formData.append('payment_note', this.project.payment_note || '');
                appendPaymentVersionToFormData(formData, this.project);
                const response = await axios.post('/api/index.php?model=project&method=updateProjectStatus', formData);
                if (response.data && response.data.status === 'success') {
                    applyPaymentVersionFromResponse(this.project, response.data);
                    this.businessDocumentDirty = false;
                    this.businessDocumentError = '';
                    this._bdSuppressAutoSave = true;
                    BUSINESS_DOCUMENT_DATE_FIELDS.forEach((key) => {
                        const apiVal = this.getBusinessDocumentDateForApi(key);
                        this.setBusinessDocumentServerDate(key, apiVal || '');
                        this.project[key] = apiVal
                            ? canonicalizeProjectDateTimeInputDisplay(toProjectDateTimeInputValue(apiVal))
                            : '';
                        const pickerIds = {
                            estimate_date: 'estimate_date_picker',
                            invoice_date: 'invoice_date_picker',
                            payment_date: 'payment_date_picker',
                        };
                        const el = document.getElementById(pickerIds[key]);
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
                    this.loadLogs();
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
            this.project.estimate_status = this.normalizeBusinessDocumentStatus(this.project.estimate_status, '未発行');
            this.project.invoice_status = this.normalizeBusinessDocumentStatus(this.project.invoice_status, '未発行');
            this.project.payment_status = this.project.payment_status || '未入金';
            this.project.estimate_number = this.project.estimate_number || '';
            this.project.invoice_number = this.project.invoice_number || '';
            this.project.receipt_number = this.project.receipt_number || '';
            this.project.payment_note = this.project.payment_note || '';
            this.project.invoice_amount = this.project.invoice_amount != null ? Number(this.project.invoice_amount) : 0;
            this.project.payment_amount = this.project.payment_amount != null ? Number(this.project.payment_amount) : 0;
            this.project.amount = this.project.amount != null ? Number(this.project.amount) : 0;
            this.normalizeBusinessDocumentDateFields();
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
            const pickerIds = {
                estimate_date: 'estimate_date_picker',
                invoice_date: 'invoice_date_picker',
                payment_date: 'payment_date_picker',
            };
            const el = document.getElementById(pickerIds[key]);
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
            return this.normalizeBusinessDocumentStatusValue(status) === '無償';
        },
        /** 完了にできる決済状態: 発行済 or 無償 */
        isBusinessDocumentReadyForCompletion(status) {
            const normalized = this.normalizeBusinessDocumentStatusValue(status);
            return normalized === '発行済' || normalized === '無償';
        },
        needsPaymentInfoBeforeComplete(project) {
            const p = project || this.project;
            if (!p) return true;
            return !this.isBusinessDocumentReadyForCompletion(p.estimate_status)
                || !this.isBusinessDocumentReadyForCompletion(p.invoice_status);
        },
        getPaymentInfoBeforeCompleteMessage(project) {
            const p = project || this.project;
            const missing = [];
            if (!this.isBusinessDocumentReadyForCompletion(p && p.estimate_status)) {
                missing.push(this.translateLabel('見積状況'));
            }
            if (!this.isBusinessDocumentReadyForCompletion(p && p.invoice_status)) {
                missing.push(this.translateLabel('請求状況'));
            }
            const base = this.translateLabel('完了にする前に見積・請求の決済情報を設定してください。（発行済または無償）');
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
        isEstimateDocumentFieldsComplete() {
            if (!this.project) return false;
            this.syncBusinessDocumentDatesFromPickers();
            return this.hasBusinessDocumentDate('estimate_date')
                && this.hasBusinessDocumentAmount(this.project.amount)
                && this.hasBusinessDocumentNumber(this.project.estimate_number);
        },
        isInvoiceDocumentFieldsComplete() {
            if (!this.project) return false;
            this.syncBusinessDocumentDatesFromPickers();
            return this.hasBusinessDocumentDate('invoice_date')
                && this.hasBusinessDocumentAmount(this.project.invoice_amount)
                && this.hasBusinessDocumentNumber(this.project.invoice_number);
        },
        getEstimateDocumentFieldsValidationError() {
            if (!this.project) return '';
            this.syncBusinessDocumentDatesFromPickers();
            const missing = [];
            if (!this.hasBusinessDocumentDate('estimate_date')) missing.push('見積日');
            if (!this.hasBusinessDocumentAmount(this.project.amount)) missing.push('見積金額');
            if (!this.hasBusinessDocumentNumber(this.project.estimate_number)) missing.push('見積番号');
            if (!missing.length) return '';
            return '発行済にするには以下を入力してください: ' + missing.join('、');
        },
        getInvoiceDocumentFieldsValidationError() {
            if (!this.project) return '';
            this.syncBusinessDocumentDatesFromPickers();
            const missing = [];
            if (!this.hasBusinessDocumentDate('invoice_date')) missing.push('請求日');
            if (!this.hasBusinessDocumentAmount(this.project.invoice_amount)) missing.push('請求金額');
            if (!this.hasBusinessDocumentNumber(this.project.invoice_number)) missing.push('請求番号');
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
        normalizeBusinessDocumentStatusValue(status) {
            if (status === '発行済み') return '発行済';
            return status;
        },
        findBusinessStatusOption(list, status) {
            const normalized = this.normalizeBusinessDocumentStatusValue(status);
            return list.find((s) => s.value === normalized || s.value === status);
        },
        setBusinessDocumentDateToday(field) {
            if (!this.canEditBusinessDocuments || !this.project) return;
            const pickerIds = {
                estimate_date: 'estimate_date_picker',
                invoice_date: 'invoice_date_picker',
                payment_date: 'payment_date_picker',
            };
            const serverNow = moment.tz(SERVER_TASK_TIMEZONE).format('YYYY-MM-DD HH:mm:ss');
            const dateStr = canonicalizeProjectDateTimeInputDisplay(toProjectDateTimeInputValue(serverNow));
            this.project[field] = dateStr || '';
            this.setBusinessDocumentServerDate(field, serverNow);
            const el = document.getElementById(pickerIds[field]);
            if (el && el._flatpickr) {
                el._flatpickr.setDate(dateStr, false, PROJECT_DATETIME_FLATPICKR_FORMAT);
            }
            this.scheduleBusinessDocumentUpdate();
        },
        copyEstimateAmountToInvoice() {
            if (!this.canEditBusinessDocuments || !this.project) return;
            this.project.invoice_amount = this.project.amount != null ? Number(this.project.amount) : 0;
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
            this.initBusinessDocumentDatePicker('estimate_date_picker', 'estimate_date', force);
            this.initBusinessDocumentDatePicker('invoice_date_picker', 'invoice_date', force);
        },
        reinitBusinessDocumentDatePickersOnLocaleChange() {
            if (!this.project || !this.canEditBusinessDocuments) return;
            ['estimate_date_picker', 'invoice_date_picker'].forEach((elId) => {
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
                            whitelist: ['新規', '修正', '新規修正', '変更', '免震', '耐震', '計画変更', '契約図', '実施図'],
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
                    if (field.type === 'checkbox') {
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
            this.allDepartmentCustomFieldSets.forEach(set => {
                if (set.fields && Array.isArray(set.fields)) {
                    set.fields.forEach(f => {
                        // Avoid duplicates by label
                        if (!allFields.find(existing => existing.label && existing.label.trim() === f.label.trim())) {
                            allFields.push({
                                label: f.label,
                                type: f.type,
                                options: f.options,
                                one_row: (f.one_row === 1 || f.one_row === '1' || f.one_row === true),
                                value: valueMap[f.label.trim()] || ''
                            });
                        }
                    });
                }
            });
            
            return allFields;
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
                const msg = this.validationErrors.name
                    || this.validationErrors.caily_nouki
                    || this.validationErrors.guis_nouki
                    || this.validationErrors.end_date
                    || this.validationErrors.yotei
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
                const formData = new FormData();
                formData.append('id', this.project.id);
                formData.append('name', this.project.name);
                // formData.append('building_branch', this.project.building_branch);
                // formData.append('building_size', this.project.building_size);
                // formData.append('building_type', this.project.building_type);
                // formData.append('building_number', this.project.building_number);
                formData.append('project_number', this.project.project_number);
                formData.append('progress', this.project.progress);
                formData.append('priority', this.project.priority || '');
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
                formData.append('caily_nouki', this.toAPIDate(this.project.caily_nouki) || '');
                formData.append('guis_nouki', this.toAPIDate(this.project.guis_nouki) || '');
                formData.append('caily_nouki_status', this.project.caily_nouki_status || '');
                formData.append('guis_nouki_status', this.project.guis_nouki_status || '');
                const yoteiPayload = this.getYoteiPayloadForSave();
                formData.append('yotei', yoteiPayload ? JSON.stringify(yoteiPayload) : '');
                formData.append('project_order_type', this.project.project_order_type);
                formData.append('customer_id', this.project.customer_id);
                // formData.append('amount', this.project.amount);
                // formData.append('estimate_status', this.project.estimate_status);
                // formData.append('invoice_status', this.project.invoice_status);
                formData.append('tags', this.project.tags);
                // No need to send department_custom_fields_set_id since we use all sets from department
                formData.append('custom_fields', this.project.custom_fields);
                formData.append('description', this.project.description || '');
                appendProjectVersionToFormData(formData, this.project);
                if (window.EnergyDrawingShare) {
                    window.EnergyDrawingShare.appendEnergyDrawingShareToFormData(formData, shareAnswer);
                }
                const response = await axios.post('/api/index.php?model=project&method=update', formData);
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
                const swal = await Swal.fire({
                    title: 'プロジェクトに参加しますか？',
                    text: 'このプロジェクトのメンバーとして参加します。',
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
                    formData.append('role', 'member');
                    
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
        },
        openNoteModal(note = null) {
            this.showNoteModal = true;
            this.isNoteEditMode = false;
            
            if (note) {
                // Edit existing note (decode HTML entities so &lt; shows as < in textarea)
                this.editingNote = {
                    id: note.id,
                    title: note.title,
                    content: note.content ? this.decodeHtmlEntities(note.content) : '',
                    is_important: note.is_important == 1,
                    needs_confirmation: Number(note.needs_confirmation) || 0,
                    display_column: (note.display_column != null && note.display_column !== undefined) ? String(note.display_column) : '',
                    user_id: note.user_id
                };
            } else {
                // Create new note
                var defaultType = (typeof window !== 'undefined' && typeof window.NOTE_DEFAULT_TYPE !== 'undefined') ? parseInt(window.NOTE_DEFAULT_TYPE, 10) : 0;
                if (isNaN(defaultType)) defaultType = 0;
                this.editingNote = {
                    id: null,
                    title: '',
                    content: '',
                    is_important: false,
                    needs_confirmation: defaultType,
                    display_column: '',
                    user_id: null
                };
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
            var opts = this.noteDisplayColumnOptions || [];
            var o = opts.find(function(x) { return x.value === value; });
            return o ? o.text : value;
        },
        async saveNote() {
            // Lấy nội dung từ Quill editor nếu có, nếu không dùng editingNote.content
            const rawContent = (this.quillNoteContent && this.quillNoteContent.trim()) || (this.editingNote.content || '').trim();
            if (!rawContent) {
                this.showNotification('内容を入力してください', 'error');
                return;
            }
            try {
                const formData = new FormData();
                formData.append('project_id', this.projectId);
                formData.append('content', rawContent);
                formData.append('is_important', this.editingNote.is_important ? 1 : 0);
                formData.append('needs_confirmation', this.editingNote.needs_confirmation ? this.editingNote.needs_confirmation : 0);
                formData.append('display_column', this.editingNote.display_column || '');
                
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
            const statusObj = this.findBusinessStatusOption(this.businessEstimateStatuses, status)
                || this.estimateStatuses.find(s => s.value === status);
            return statusObj ? statusObj.label : '未発行';
        },
        getEstimateStatusBadgeClass(status) {
            const statusObj = this.findBusinessStatusOption(this.businessEstimateStatuses, status)
                || this.estimateStatuses.find(s => s.value === status);
            return statusObj ? `bg-${statusObj.color}` : 'bg-secondary';
        },
        getEstimateStatusButtonClass(status) {
            const statusObj = this.findBusinessStatusOption(this.businessEstimateStatuses, status)
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
            const statusObj = this.findBusinessStatusOption(this.businessInvoiceStatuses, status)
                || this.invoiceStatuses.find(s => s.value === status);
            return statusObj ? statusObj.label : '未発行';
        },
        getInvoiceStatusBadgeClass(status) {
            const statusObj = this.findBusinessStatusOption(this.businessInvoiceStatuses, status)
                || this.invoiceStatuses.find(s => s.value === status);
            return statusObj ? `bg-${statusObj.color}` : 'bg-secondary';
        },
        getInvoiceStatusButtonClass(status) {
            const statusObj = this.findBusinessStatusOption(this.businessInvoiceStatuses, status)
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
        showNotification(message, type = 'info') {
            // Use showMessage function if available, otherwise use alert
            showMessage(message, type === 'error');
        },
        initQuillEditor() {
            if (this.quillInstance || !this.isEditMode) return;
            
            // Use a longer delay to ensure all other components are initialized first
            setTimeout(() => {
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
                if (!el) return;
                
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
                const el = document.getElementById('quill_note_content_detail');
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
                    const html = this.decodeHtmlEntities ? this.decodeHtmlEntities(this.editingNote.content) : this.editingNote.content;
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
        getDepartmentCustomFieldSetName(id) {
            const set = this.departmentCustomFieldSets.find(s => String(s.id) === String(id));
            return set ? set.name : '-';
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
            // Merge all fields from all department custom field sets with saved values
            const allFieldsFromSets = [];
            if (this.allDepartmentCustomFieldSets && this.allDepartmentCustomFieldSets.length > 0) {
                this.allDepartmentCustomFieldSets.forEach(set => {
                    if (set.fields && Array.isArray(set.fields)) {
                        set.fields.forEach(f => {
                            // Avoid duplicates by label
                            if (!allFieldsFromSets.find(existing => existing.label && existing.label.trim() === f.label.trim())) {
                                allFieldsFromSets.push({
                                    label: f.label,
                                    type: f.type,
                                    // Chuẩn hóa options thành string để template có thể gọi .split(',')
                                    options: Array.isArray(f.options) ? f.options.join(',') : (f.options != null ? String(f.options) : ''),
                                    // one_row từ backend: 0/1, '0'/'1', boolean → chuẩn hóa boolean
                                    one_row: (f.one_row === 1 || f.one_row === '1' || f.one_row === true)
                                });
                            }
                        });
                    }
                });
            }
            
            // Parse saved values
            let saved = [];
            let raw = this.project?.custom_fields;
            if (typeof raw === 'string' && raw.includes('&quot;')) {
                raw = raw.replace(/&quot;/g, '"');
            }
            if (typeof raw === 'string') {
                try { saved = JSON.parse(raw); } catch (e) { saved = []; }
            } else if (Array.isArray(raw)) {
                saved = raw;
            }
            
            // Create value map from saved data
            const savedValueMap = {};
            saved.forEach(f => {
                if (f.label) {
                    savedValueMap[f.label.trim()] = f;
                }
            });
            
            // Merge: use fields from sets, fill values from saved data
            return allFieldsFromSets.map(f => {
                const savedField = savedValueMap[f.label.trim()];
                return {
                    label: f.label,
                    type: f.type,
                    options: f.options,
                    one_row: f.one_row === true,
                    value: savedField ? savedField.value : ''
                };
            });
        },
        async updateCustomFieldValue(label, value) {
            if (!this.project || !label) return;
            const newValue = (value != null) ? String(value).trim() : '';
            let saved = [];
            let raw = this.project.custom_fields;
            if (typeof raw === 'string' && raw.includes('&quot;')) raw = raw.replace(/&quot;/g, '"');
            try {
                saved = typeof raw === 'string' ? JSON.parse(raw || '[]') : (Array.isArray(raw) ? raw : []);
            } catch (e) { saved = []; }
            const updated = saved.map(f => {
                if (!f || String(f.label || '').trim() !== String(label).trim()) return f;
                return Object.assign({}, f, { value: newValue });
            });
            if (!updated.some(f => f && String(f.label || '').trim() === String(label).trim())) {
                updated.push({ label: label, value: newValue });
            }
            if (this.savingCustomFieldLabel === label) return;
            this.savingCustomFieldLabel = label;
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
        async loadLogs() {
            try {
                const res = await axios.get(`/api/index.php?model=project&method=getLogs&project_id=${this.projectId}`);
                if (res.data && Array.isArray(res.data)) {
                    this.logs = res.data;
                } else {
                    this.logs = [];
                }
            } catch (e) {
                this.logs = [];
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
            const errors = this.validationErrors;
            let isValid = true;
            const tantou = (this.project.tantou || '').trim();
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
                yotei: ''
            };
            let valid = true;
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

            return valid;
        },
    },
    watch: {
        canEditBusinessDocuments(newVal) {
            if (newVal) {
                this.$nextTick(() => this.initBusinessDocumentDatePickers());
            }
        },
        isEditMode(newVal) {
            if (!newVal) {
                this.destroyYoteiMonthPickers();
            }
            if (newVal) {
                this.$nextTick(() => {
                    this.initYoteiMonthPickers();
                    // Sync customFields from project.custom_fields or set
                    let saved = [];
                    let raw = this.project.custom_fields;
                    if (typeof raw === 'string' && raw.includes('&quot;')) {
                        raw = raw.replace(/&quot;/g, '"');
                    }
                    if (typeof raw === 'string') {
                        try { saved = JSON.parse(raw); } catch (e) { saved = []; }
                    } else if (Array.isArray(raw)) {
                        saved = raw;
                    }
                    // Merge all fields from all department custom field sets
                    const allFieldsFromSets = [];
                    if (this.allDepartmentCustomFieldSets && this.allDepartmentCustomFieldSets.length > 0) {
                        this.allDepartmentCustomFieldSets.forEach(set => {
                            if (set.fields && Array.isArray(set.fields)) {
                                set.fields.forEach(f => {
                                    // Avoid duplicates by label
                                    if (!allFieldsFromSets.find(existing => existing.label && existing.label.trim() === f.label.trim())) {
                                        allFieldsFromSets.push({
                                            label: f.label,
                                            type: f.type,
                                            options: Array.isArray(f.options) ? f.options.join(',') : (f.options != null ? String(f.options) : ''),
                                            one_row: (f.one_row === 1 || f.one_row === '1' || f.one_row === true)
                                        });
                                    }
                                });
                            }
                        });
                    }
                    // Nếu có dữ liệu custom_fields với cấu trúc đầy đủ (có type), merge với saved values
                    if (saved.length && saved[0]) {
                        // Create value map from saved data
                        const savedValueMap = {};
                        saved.forEach(f => {
                            if (f.label) {
                                savedValueMap[f.label.trim()] = f;
                            }
                        });
                        
                        // Merge: use fields from sets, fill values from saved data
                        this.customFields = allFieldsFromSets.map(f => {
                            const savedField = savedValueMap[f.label.trim()];
                            const oneRow = (f.one_row === 1 || f.one_row === '1' || f.one_row === true);
                            if (f.type === 'checkbox') {
                                let arr = [];
                                if (savedField && savedField.value) {
                                    arr = savedField.value.split(',').map(s => s.trim()).filter(Boolean);
                                }
                                return { label: f.label, type: f.type, options: f.options, one_row: oneRow, value: arr.join(','), valueArr: arr };
                            } else {
                                const row = {
                                    label: f.label,
                                    type: f.type,
                                    options: f.options,
                                    one_row: oneRow,
                                    value: savedField ? savedField.value : ''
                                };
                                if (f.type === 'datetime' && savedField && savedField.value) {
                                    row._serverDatetime = savedField.value;
                                }
                                return row;
                            }
                        });
                        
                        // Initialize datetime pickers after customFields is set
                        this.$nextTick(() => {
                            setTimeout(() => {
                                this.initCustomFieldDatePickers();
                            }, 100);
                        });
                    } else if (allFieldsFromSets.length > 0) {
                        // No saved data, just use fields from all sets
                        this.customFields = allFieldsFromSets.map(f => {
                            const oneRow = (f.one_row === 1 || f.one_row === '1' || f.one_row === true);
                            if (f.type === 'checkbox') {
                                return { label: f.label, type: f.type, options: f.options, one_row: oneRow, value: '', valueArr: [] };
                            } else {
                                return { label: f.label, type: f.type, options: f.options, one_row: oneRow, value: '' };
                            }
                        });
                        
                        // Initialize datetime pickers after customFields is set
                        this.$nextTick(() => {
                            setTimeout(() => {
                                this.initCustomFieldDatePickers();
                            }, 100);
                        });
                    } else {
                        this.customFields = [];
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
                                whitelist: ['新規', '修正', '新規修正', '変更', '免震', '耐震', '計画変更', '契約図', '実施図'],
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
                        // Re-sync custom fields by re-running the sync logic
                        let saved = [];
                        let raw = this.project.custom_fields;
                        if (typeof raw === 'string' && raw.includes('&quot;')) {
                            raw = raw.replace(/&quot;/g, '"');
                        }
                        if (typeof raw === 'string') {
                            try { saved = JSON.parse(raw); } catch (e) { saved = []; }
                        } else if (Array.isArray(raw)) {
                            saved = raw;
                        }
                        
                        const allFieldsFromSets = [];
                        newVal.forEach(set => {
                            if (set.fields && Array.isArray(set.fields)) {
                                set.fields.forEach(f => {
                                    if (!allFieldsFromSets.find(existing => existing.label && existing.label.trim() === f.label.trim())) {
                                        allFieldsFromSets.push(f);
                                    }
                                });
                            }
                        });
                        
                        if (allFieldsFromSets.length > 0) {
                            const savedValueMap = {};
                            saved.forEach(f => {
                                if (f && f.label) {
                                    // Handle both formats: {label, value} or {label, type, value}
                                    savedValueMap[f.label.trim()] = f;
                                }
                            });
                            
                            console.log('allDepartmentCustomFieldSets watcher: setting customFields', {
                                allFieldsFromSetsCount: allFieldsFromSets.length,
                                savedCount: saved.length,
                                savedValueMap: savedValueMap
                            });
                            
                            this.customFields = allFieldsFromSets.map(f => {
                                const savedField = savedValueMap[f.label.trim()];
                                console.log('allDepartmentCustomFieldSets watcher: mapping field', {
                                    label: f.label,
                                    type: f.type,
                                    savedField: savedField,
                                    savedFieldValue: savedField ? savedField.value : null
                                });
                                
                                if (f.type === 'checkbox') {
                                    let arr = [];
                                    if (savedField && savedField.value) {
                                        arr = savedField.value.split(',').map(s => s.trim()).filter(Boolean);
                                    }
                                    return { label: f.label, type: f.type, options: f.options, value: arr.join(','), valueArr: arr };
                                } else {
                                    const row = {
                                        label: f.label,
                                        type: f.type,
                                        options: f.options,
                                        value: savedField ? savedField.value : ''
                                    };
                                    if (f.type === 'datetime' && savedField && savedField.value) {
                                        row._serverDatetime = savedField.value;
                                    }
                                    return row;
                                }
                            });
                            
                            // Initialize datetime pickers after customFields is set
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
                    if (field.type === 'checkbox') {
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
    },
    async mounted() {
        await this.loadPermission();
        // if(!this.permission.is_member){
        //     this.showMessage('権限がありません。', true);
        //     setTimeout(() => {
        //         window.location.href = 'index.php';
        //     }, 1000);
        //     return;
        // }
        await this.loadProject();
        this.loadCategories();
        this.loadDepartmentCustomFieldSets();
        this.loadNotes();
        this.loadLogs();
        this.loadCurrentUser();
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

        // Initialize mention manager
        this.$nextTick(() => {
            if (window.mentionManager) {
                // If MentionManager already exists, set department ID and rebind
                if (this.project && this.project.department_id) {
                    window.mentionManager.setDepartmentId(this.project.department_id);
                }
                // Force rebind to ensure it picks up the contenteditable element
                window.mentionManager.bindToInputs();
            } else {
                // Create new MentionManager instance
                window.mentionManager = new MentionManager({
                    departmentId: this.project ? this.project.department_id : null
                });
            }
            
            this.initProjectTagsTagify();
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
        this.autoRefreshTimer = setInterval(() => {
            if (!this.isEditMode && !this.hasPendingBusinessDocumentChanges()) {
                this.loadProject();
                this.loadLogs();
            }
        }, 60000); // 60 seconds

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
        if (this.autoRefreshTimer) {
            clearInterval(this.autoRefreshTimer);
        }
        if (this.businessDocumentSaveHideTimer) {
            clearTimeout(this.businessDocumentSaveHideTimer);
        }
    }
});

// Register Comment Component
vueApp.component('comment-component', window.CommentComponent);

// Mount the Vue app
vueApp.mount('#app'); 