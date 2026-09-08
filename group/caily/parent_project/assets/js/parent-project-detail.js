const { createApp } = Vue;

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

const SERVER_TASK_TIMEZONE = 'Asia/Tokyo';
const VIETNAM_TASK_TIMEZONE = 'Asia/Ho_Chi_Minh';
const PROJECT_DATETIME_MOMENT_FORMAT = 'YYYY/M/D HH:mm';
const PROJECT_DATETIME_JA_DISPLAY_FORMAT = 'M月D日 HH:mm';
const PROJECT_DATETIME_FLATPICKR_FORMAT = 'Y/m/d H:i';
const PROJECT_DATETIME_FLATPICKR_JA_ALT_FORMAT = 'Y年n月j日 H:i';
const PROJECT_DATETIME_FLATPICKR_MOMENT_FORMAT = 'Y/M/D H:mm';
const PROJECT_DATETIME_PARSE_FORMATS = [
    'YYYY-MM-DD HH:mm:ss',
    'YYYY-MM-DD HH:mm',
    'YYYY/M/D HH:mm',
    'YYYY/MM/DD HH:mm',
    'YYYY/M/D H:mm',
    'YYYY/MM/DD H:mm',
    'Y/M/D H:mm',
    'Y/n/j H:i'
];

function isVietnameseLocale() {
    return typeof i18next !== 'undefined'
        && i18next.isInitialized
        && String(i18next.language || '').startsWith('vi');
}

function getProjectDisplayTimezone() {
    return isVietnameseLocale() ? VIETNAM_TASK_TIMEZONE : SERVER_TASK_TIMEZONE;
}

function parseProjectDateMomentServer(value) {
    if (value === undefined || value === null) return null;
    const s = String(value).trim();
    if (!s || s === '-') return null;
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
    const tz = getProjectDisplayTimezone();
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
    const parsed = parseProjectDateMomentServer(date);
    if (!parsed || !parsed.isValid()) return '';
    const localized = moment.tz
        ? parsed.clone().tz(getProjectDisplayTimezone())
        : parsed;
    return localized.format(PROJECT_DATETIME_FLATPICKR_MOMENT_FORMAT);
}

function fromProjectDateTimeInputValue(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';
    const parsed = parseProjectDateTimeInDisplayTz(raw);
    if (!parsed) return raw;
    if (moment.tz) {
        return parsed.clone().tz(SERVER_TASK_TIMEZONE).format(PROJECT_DATETIME_MOMENT_FORMAT);
    }
    return parsed.format(PROJECT_DATETIME_MOMENT_FORMAT);
}

function formatProjectDateTimeForDisplay(value) {
    const parsed = parseProjectDateMomentServer(value);
    if (!parsed) return '-';
    const localized = moment.tz
        ? parsed.clone().tz(getProjectDisplayTimezone())
        : parsed;
    return localized.format(
        isVietnameseLocale()
            ? PROJECT_DATETIME_MOMENT_FORMAT
            : PROJECT_DATETIME_JA_DISPLAY_FORMAT
    );
}

function formatProjectDateOnlyForDisplay(value) {
    const parsed = parseProjectDateMomentServer(value);
    if (!parsed) return '-';
    const localized = moment.tz
        ? parsed.clone().tz(getProjectDisplayTimezone())
        : parsed;
    return isVietnameseLocale()
        ? localized.format('M/D')
        : localized.format('M月D日');
}

function formatProjectTimeOnlyForDisplay(value) {
    const parsed = parseProjectDateMomentServer(value);
    if (!parsed) return '';
    const localized = moment.tz
        ? parsed.clone().tz(getProjectDisplayTimezone())
        : parsed;
    return localized.format('H:mm');
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

function makeChildProjectTimeInputsEditable(selectedDates, dateStr, instance) {
    const cal = instance && instance.calendarContainer;
    if (!cal) return;
    cal.querySelectorAll('.flatpickr-time input, .flatpickr-time .numInputWrapper input').forEach((input) => {
        input.removeAttribute('readonly');
        input.readOnly = false;
    });
}

function getFlatpickrVisibleValue(fp, el) {
    if (fp) {
        const visibleInput = fp.altInput || fp._input || el;
        return String((visibleInput && visibleInput.value) || '').trim();
    }
    return String((el && el.value) || '').trim();
}

function getProjectFlatpickrOptions(extra) {
    const userOnClose = extra && typeof extra.onClose === 'function' ? extra.onClose : null;
    const options = {
        enableTime: true,
        time_24hr: true,
        dateFormat: PROJECT_DATETIME_FLATPICKR_FORMAT,
        allowInput: true,
        locale: getProjectFlatpickrLocale(),
        onOpen: makeChildProjectTimeInputsEditable
    };
    if (!isVietnameseLocale()) {
        options.altInput = true;
        options.altFormat = PROJECT_DATETIME_FLATPICKR_JA_ALT_FORMAT;
        options.altInputClass = 'form-control';
    }
    if (extra) {
        Object.keys(extra).forEach((key) => {
            if (key === 'onClose') return;
            options[key] = extra[key];
        });
    }
    // allowInput: clearing the visible field must clear selectedDates, or Save restores the old value
    options.onClose = function(selectedDates, dateStr, instance) {
        const displayVal = getFlatpickrVisibleValue(instance, instance && instance.input);
        if (!displayVal && instance && instance.selectedDates && instance.selectedDates.length) {
            instance.clear();
        }
        if (userOnClose) {
            userOnClose(selectedDates, dateStr, instance);
        }
    };
    return options;
}

function initChildProjectFlatpickr(el, extra, serverValue, opts) {
    if (!el || typeof flatpickr === 'undefined') return null;
    if (el._flatpickr) el._flatpickr.destroy();
    const options = opts || {};
    const inputVal = options.alreadyDisplay
        ? String(serverValue || '').trim()
        : toProjectDateTimeInputValue(serverValue);
    if (inputVal) el.value = inputVal;
    const fp = flatpickr(el, getProjectFlatpickrOptions(extra || {}));
    if (inputVal) {
        fp.setDate(inputVal, false, PROJECT_DATETIME_FLATPICKR_FORMAT);
    }
    return fp;
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

const TASK_KINDS = [
    { value: '新規作成', label: '新規作成', color: 'success' },
    { value: '修正(エラー)', label: '修正(エラー)', color: 'danger' },
    { value: '修正(変更)', label: '修正(変更)', color: 'warning' },
    { value: 'チェック', label: 'チェック', color: 'primary' },
    { value: '連絡', label: '連絡', color: 'info' },
    { value: '検討', label: '検討', color: 'secondary' },
    { value: '相談・会議', label: '相談・会議', color: 'dark' }
];

const BUSINESS_ESTIMATE_STATUSES = [
    { value: '未発行', label: '未発行', color: 'secondary' },
    { value: '見積作成中', label: '見積作成中', color: 'primary' },
    { value: '発行済', label: '発行済', color: 'success' },
    { value: '発行済み', label: '発行済', color: 'success' },
    { value: '無償', label: '無償', color: 'info' },
];

const BUSINESS_INVOICE_STATUSES = [
    { value: '未発行', label: '未発行', color: 'secondary' },
    { value: '請求準備', label: '請求準備', color: 'warning' },
    { value: '発行済', label: '発行済', color: 'success' },
    { value: '発行済み', label: '発行済', color: 'success' },
    { value: '無償', label: '無償', color: 'info' },
];

const BUSINESS_PAYMENT_STATUSES = [
    { value: '未入金', label: '未入金', color: 'secondary' },
    { value: '入金済', label: '入金済', color: 'success' },
    { value: '入金拒否', label: '入金拒否', color: 'danger' },
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

const BD_MODAL_PICKER_IDS = {
    estimate_date: 'bd_modal_estimate_date_picker',
    invoice_date: 'bd_modal_invoice_date_picker',
};

function isProjectServerDateTimeFormat(value) {
    const s = String(value || '').trim();
    return /^\d{4}-\d{1,2}-\d{1,2}(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?$/.test(s);
}

function normalizeProjectVersion(version) {
    const n = Number(version);
    return Number.isFinite(n) && n > 0 ? n : 1;
}

function appendPaymentVersionToFormData(formData, projectOrVersion) {
    if (!formData) return;
    const paymentVersion = typeof projectOrVersion === 'object'
        ? projectOrVersion?.payment_version
        : projectOrVersion;
    formData.append('payment_version', normalizeProjectVersion(paymentVersion));
}

function applyPaymentVersionFromResponse(project, responseData) {
    if (project && responseData && responseData.payment_version != null) {
        project.payment_version = normalizeProjectVersion(responseData.payment_version);
    }
}

function appendProjectVersionToFormData(formData, projectOrVersion) {
    if (!formData) return;
    const version = typeof projectOrVersion === 'object'
        ? projectOrVersion?.version
        : projectOrVersion;
    formData.append('version', normalizeProjectVersion(version));
}

function applyProjectVersionFromResponse(project, responseData) {
    if (project && responseData && responseData.version != null) {
        project.version = normalizeProjectVersion(responseData.version);
    }
}

function handleProjectVersionConflict(responseData, onReload) {
    if (!responseData || (responseData.error !== 'version_conflict' && responseData.error !== 'version_required')) {
        return false;
    }
    const msg = responseData.message || '他のユーザーが先に更新しました。ページを再読み込みしてください。';
    if (typeof showParentProjectError === 'function') {
        showParentProjectError(msg, responseData, {
            onClose: function() {
                if (typeof onReload === 'function') {
                    onReload();
                } else {
                    window.location.reload();
                }
            }
        });
        return true;
    }
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
            if (typeof onReload === 'function') {
                onReload();
            } else {
                window.location.reload();
            }
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

createApp({
    data() {
        return {
            isAdmin: typeof USER_ROLE !== 'undefined' && USER_ROLE === 'administrator',
            isProjectManager: typeof IS_PROJECT_MANAGER !== 'undefined' ? IS_PROJECT_MANAGER : false,
            permission: {},
            parentProject: null,
            childProjects: [],
            workloadStatsByDepartment: [],
            activeWorkloadDeptId: null,
            loadingWorkloadStats: false,
            workloadChartInstance: null,
            workloadChartRenderTimer: null,
            workloadChartRenderToken: 0,
            loading: true,
            isEditMode: false,
            originalParentProject: null,
            companies: [],
            branches: [],
            contacts: [],
            users: [],
            departments: [],
            departmentUsers: [], // Users in the selected department
            guisReceiverDisplayName: '', // Add this to store the display name
            // Display info from customer table (company / branch / contact)
            customerDisplay: {
                company_name: '',
                branch_name: '',
                contact_name: ''
            },
            // Customer modal data
            categories: [],
            selectedCustomer: null,
            customerInfoModalContext: 'parent',
            customerInfoModalChildProjectId: null,
            customerForDisplay: null,
            customerErrors: {
                company_name: '',
                name: '',
                branch: '',
                guis_department: ''
            },
            updatingCustomer: false,
            // New customer modal (建物詳細 edit mode)
            newCustomer: {
                company_name: '大東建託株式会社',
                company_name_kana: '',
                name: '',
                name_kana: '',
                branch: '本社',
                position: '',
                department: '',
                title: '',
                tel: '',
                fax: '',
                phone: '',
                email: '',
                zip: '',
                address1: '',
                address2: '',
                memo: '',
                status: 1,
                category_id: 0,
                guis_department: []
            },
            type1Tagify: null,
            type2Tagify: null,
            constructionBranchTagify: null,
            childProjectOrderTypeTagify: null,
            createChildProjectManagerTagify: null,
            createChildProjectTeamTagify: null,
            createChildProjectMembersTagify: null,
            editChildProjectManagerTagify: null,
            editChildProjectTeamTagify: null,
            editChildProjectMembersTagify: null,
            request_design: false,
            request_equipment: false,
            request_3d_equipment: false,
            request_energy_saving: false,
            request_3d: false,
            materials_layout: false,
            materials_rental: false,
            materials_contract: false,
            materials_tac: false,
            materials_other: false,
            validationErrors: {
                company_name: '',
                project_name: ''
            },
            statuses: [
                { value: 'draft', label: '下書き', color: 'secondary' },
                { value: 'under_contract', label: '契約中', color: 'info' },
                { value: 'in_progress', label: '進行中', color: 'primary' },
                { value: 'completed', label: '完了', color: 'success' },
                { value: 'cancelled', label: 'キャンセル', color: 'danger' }
            ],
            projectStatuses: [
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
            childCustomerModalContext: null,
            // Child project modal data
            newChildProject: {
                name: '',
                department_id: '',
                project_number: '',
                description: '',
                start_date: '',
                end_date: '',
                project_order_type: '',
                parent_project_id: PARENT_PROJECT_ID,
                is_kadai: true,
                status: '',
                amount: 0,
                progress: 0,
                teams: '',
                managers: [],
                members: [],
                tantou: '',
                caily_nouki: '',
                guis_nouki: '',
                yotei: { from_month: '', from_part: '', to_month: '', to_part: '' },
                use_parent_customer: true,
                company_name: '',
                branch_name: '',
                contact_name: '',
                customer_id: '',
                use_parent_guis_receiver: true,
                guis_receiver: ''
            },
            editingChildProject: {
                id: null,
                name: '',
                department_id: '',
                project_number: '',
                description: '',
                start_date: '',
                end_date: '',
                project_order_type: '',
                parent_project_id: PARENT_PROJECT_ID,
                is_kadai: true,
                status: 'draft',
                previous_status: '',
                amount: 0,
                progress: 0,
                teams: '',
                managers: [],
                members: [],
                tantou: '',
                caily_nouki: '',
                guis_nouki: '',
                yotei: { from_month: '', from_part: '', to_month: '', to_part: '' },
                use_parent_customer: true,
                company_name: '',
                branch_name: '',
                contact_name: '',
                customer_id: '',
                use_parent_guis_receiver: true,
                guis_receiver: ''
            },
            yoteiPartOptionsTick: 0,
            childProjectValidationErrors: {
                name: '',
                department_id: '',
                project_number: '',
                start_date: '',
                end_date: '',
                status: '',
                tantou: '',
                caily_nouki: '',
                guis_nouki: '',
                yotei: '',
                company_name: '',
                branch_name: '',
                contact_name: '',
                guis_receiver: ''
            },
            editChildProjectValidationErrors: {
                name: '',
                department_id: '',
                project_number: '',
                start_date: '',
                end_date: '',
                project_order_type: '',
                tantou: '',
                caily_nouki: '',
                guis_nouki: '',
                yotei: '',
                company_name: '',
                branch_name: '',
                contact_name: '',
                guis_receiver: ''
            },
            creatingChildProject: false,
            updatingChildProject: false,
            // Notes (メモ) data
            notes: [],
            showNoteModal: false,
            isNoteEditMode: false,
            editingNote: {
                id: null,
                title: '',
                content: '',
                is_important: false,
                user_id: null
            },
            // Activity logs data
            logs: [],
            loadingLogs: false,
            // Child project logs data
            childProjectLogs: [],
            loadingChildProjectLogs: false,
            selectedChildProject: null,
            childProjectContextMenuVisible: false,
            childProjectContextMenuX: 0,
            childProjectContextMenuY: 0,
            childProjectContextMenuProject: null,
            showBusinessDocumentLogModal: false,
            businessDocumentProject: null,
            businessDocumentProjectId: null,
            businessDocumentLogs: [],
            _bdServerDates: null,
            businessDocumentSaveStatus: null,
            businessDocumentSaveHideTimer: null,
            businessDocumentDirty: false,
            businessDocumentError: '',
            businessDocumentUpdateTimer: null,
            isUpdatingBusinessDocument: false,
            _bdSuppressAutoSave: false,
            businessEstimateStatuses: BUSINESS_ESTIMATE_STATUSES.filter((s) => s.value !== '発行済み'),
            businessInvoiceStatuses: BUSINESS_INVOICE_STATUSES.filter((s) => s.value !== '発行済み'),
            businessPaymentStatuses: BUSINESS_PAYMENT_STATUSES,
            restoringChildProject: false,
            // Quill editor instance for edit child project modal
            editChildProjectQuillInstance: null,
            editChildProjectQuillContent: '',
            editChildProjectQuillInitializing: false,
            // Quill editor instance for create child project modal
            createChildProjectQuillInstance: null,
            createChildProjectQuillContent: '',
            createChildProjectQuillInitializing: false,
            _serverChildProjectDates: null,
            // Quotation data
            quotations: [],
            selectedQuotation: null,
            creatingQuotation: false,
            quotationBranches: [],
            quotationUsers: [],
            selectedContactSeal: null,
            selectedContactSealForEdit: null,
            quotationValidationErrors: {}, // Add field-level validation errors
            newQuotation: {
                issue_date: '',
                quotation_number: '',
                sender_company: '',
                sender_address: '',
                sender_contact: '',
                selected_branch_id: '',
                receiver_company: '',
                receiver_address: '',
                receiver_contact: '',
                receiver_tel: '',
                receiver_fax: '',
                receiver_registration_number: '',
                status: '下書き',
                items: [],
                total_amount: 0,
                tax_rate: 10,
                total_with_tax: 0,
                delivery_date: '御打ち合わせの上',
                delivery_location: '貴社指定場所',
                payment_method: '電子納品',
                valid_until_type: '1_month',
                valid_until: '',
                notes: '',
                parent_project_id: PARENT_PROJECT_ID
            },
            selectedQuotation: null,
            creatingQuotation: false,
            quotationFormBackup: null,
            // Child project selection for quotation
            selectedChildProjectIds: [],
            allChildProjectsSelected: false,
            someChildProjectsSelected: false,
            // Price list data
            priceListProducts: [],
            filteredPriceListProducts: [],
            selectedPriceListType: '',
            priceListSearchTerm: '',
            priceListTagSearchTerm: '',
            priceListModal: null,
            
            // Sortable instances
            createQuotationSortable: null,
            editQuotationSortable: null,
            
            // Multiple product selection
            selectedProducts: [],
            allSelected: false,
            // Price list pagination and UX
            priceListPage: 1,
            priceListPageSize: 50,
            highlightedIndex: 0,
            lastSelectedIndexGlobal: null,
            filterDebounceTimer: null,
            // Quantities per selected product id
            selectedProductQuantities: {},
            // Set editing state
            editingSet: null,
            setEditModal: null,
            // Optional custom name for the selected set
            selectedSetName: '',
            // Order items selection (for bulk delete)
            selectedOrderItemIndexes: [],
            // Edit quotation data
            editingQuotation: {
                id: null,
                issue_date: '',
                quotation_number: '',
                sender_company: '',
                sender_address: '',
                sender_contact: '',
                selected_branch_id: '',
                receiver_company: '',
                receiver_address: '',
                receiver_contact: '',
                receiver_tel: '',
                receiver_fax: '',
                receiver_registration_number: '',
                status: '下書き',
                items: [],
                total_amount: 0,
                tax_rate: 10,
                total_with_tax: 0,
                delivery_date: '御打ち合わせの上',
                delivery_location: '貴社指定場所',
                payment_method: '電子納品',
                valid_until_type: '1_month',
                valid_until: '',
                notes: '',
                parent_project_id: PARENT_PROJECT_ID
            },
            editQuotationValidationErrors: {},
            updatingQuotation: false,
            // Child project selection for edit quotation
            selectedChildProjectIdsForEdit: [],
            allChildProjectsSelectedForEdit: false,
            someChildProjectsSelectedForEdit: false,
            // Order items selection for edit (for bulk delete)
            selectedOrderItemIndexesForEdit: [],
            // Edit quotation form backup
            editQuotationFormBackup: null,
            
            // Context tracking for price list modal
            isPriceListOpenFromEdit: false,
            
            // Quotation history data
            quotationHistory: [],
            selectedQuotationForHistory: null
        }
    },
    watch: {
        'newQuotation.items': {
            handler(newItems, oldItems) {
                // Allow product_code to be freely entered even for set products
                // Removed the restriction that was clearing product_code for set products
                
                // Auto-validate project_id selection
                this.validateProjectIdSelection();
            },
            deep: true
        },
        'quotationValidationErrors': {
            handler(newErrors, oldErrors) {
                // Validation errors changed
            },
            deep: true
        },
        'childProjects': {
            handler(newProjects, oldProjects) {
                // Ensure amounts are properly formatted and displayed
                if (newProjects && Array.isArray(newProjects)) {
                    newProjects.forEach(project => {
                        // Ensure amount field exists and is properly formatted
                        if (project && typeof project.amount === 'undefined') {
                            project.amount = project.total_amount || 0;
                        }
                    });
                }
            },
            deep: true
        },
        activeWorkloadDeptId() {
            if (!this.loadingWorkloadStats) {
                this.$nextTick(() => this.renderActiveWorkloadChart());
            }
        },
        'editingQuotation.items': {
            handler(newItems, oldItems) {
                // Allow product_code to be freely entered even for set products
                // Removed the restriction that was clearing product_code for set products
                
                // Auto-validate project_id selection
                this.validateProjectIdSelectionForEdit();
                
                // Calculate total amount
                this.calculateTotalAmountForEdit();
            },
            deep: true
        },
        'selectedChildProjectIdsForEdit': {
            handler(newIds, oldIds) {
                // Clear unlinked order items when child projects are deselected
                this.clearUnlinkedOrderItemsForEdit();
            }
        },
        'newChildProject.department_id': {
            async handler(newDeptId, oldDeptId) {
                if (newDeptId && newDeptId !== oldDeptId) {
                    // Clear 管理 (manager), team, members when department changes so new Tagify start empty
                    this.newChildProject.managers = [];
                    this.newChildProject.teams = '';
                    this.newChildProject.members = [];
                    // Load department users first, then re-initialize all Tagify so whitelists are set
                    await this.loadDepartmentUsers(newDeptId, false);
                    await this.$nextTick();
                    const managerInput = document.getElementById('create_child_project_manager_tags');
                    if (managerInput) {
                        if (this.createChildProjectManagerTagify) {
                            this.createChildProjectManagerTagify.destroy();
                            this.createChildProjectManagerTagify = null;
                        }
                        managerInput.value = '';
                        await this.initializeCreateChildProjectManagerTagify();
                        await this.initializeCreateChildProjectTeamTagify();
                        await this.initializeCreateChildProjectMembersTagify();
                    }
                } else if (!newDeptId) {
                    // Clear managers, team, members when department is cleared
                    if (this.createChildProjectManagerTagify) {
                        this.createChildProjectManagerTagify.removeAllTags();
                        this.newChildProject.managers = [];
                        this.createChildProjectManagerTagify.settings.whitelist = [];
                        this.createChildProjectManagerTagify.whitelist = [];
                    }
                    if (this.createChildProjectTeamTagify) {
                        this.createChildProjectTeamTagify.removeAllTags();
                        this.newChildProject.teams = '';
                    }
                    if (this.createChildProjectMembersTagify) {
                        this.createChildProjectMembersTagify.removeAllTags();
                        this.newChildProject.members = [];
                    }
                }
            }
        },
        'editingChildProject.department_id': {
            async handler(newDeptId, oldDeptId) {
                if (newDeptId && newDeptId !== oldDeptId) {
                    // Clear 管理 (manager), team, members when department changes so new Tagify start empty
                    this.editingChildProject.managers = [];
                    this.editingChildProject.teams = '';
                    this.editingChildProject.members = [];
                    // Load department users first, then re-initialize all Tagify so whitelists are set
                    await this.loadDepartmentUsers(newDeptId, true);
                    await this.$nextTick();
                    const managerInput = document.getElementById('edit_child_project_manager_tags');
                    if (managerInput) {
                        if (this.editChildProjectManagerTagify) {
                            this.editChildProjectManagerTagify.destroy();
                            this.editChildProjectManagerTagify = null;
                        }
                        managerInput.value = '';
                        await this.initializeEditChildProjectManagerTagify();
                        await this.initializeEditChildProjectTeamTagify();
                        await this.initializeEditChildProjectMembersTagify();
                    }
                } else if (!newDeptId) {
                    // Clear managers, team, members when department is cleared
                    if (this.editChildProjectManagerTagify) {
                        this.editChildProjectManagerTagify.removeAllTags();
                        this.editingChildProject.managers = [];
                        this.editChildProjectManagerTagify.settings.whitelist = [];
                        this.editChildProjectManagerTagify.whitelist = [];
                    }
                    if (this.editChildProjectTeamTagify) {
                        this.editChildProjectTeamTagify.removeAllTags();
                        this.editingChildProject.teams = '';
                    }
                    if (this.editChildProjectMembersTagify) {
                        this.editChildProjectMembersTagify.removeAllTags();
                        this.editingChildProject.members = [];
                    }
                }
            }
        }
    },
    computed: {
        yoteiPartOptions() {
            this.yoteiPartOptionsTick;
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
        isCailyBranchUser() {
            return typeof window !== 'undefined' && window.IS_CAILY_BRANCH_USER === true;
        },
        /** CAILY: 期限日閲覧 unlocks 完了 status + deadline fields. */
        canViewEndDate() {
            if (!this.isCailyBranchUser) return true;
            if (this.isAdmin) return true;
            if (!this.permission || !this.permission.length) return false;
            return this.permission.some(function(rule) {
                return rule.project_view_end_date == 1 || rule.project_view_end_date === '1';
            });
        },
        editableProjectStatuses() {
            if (!this.isCailyBranchUser || this.canViewEndDate) {
                return this.projectStatuses;
            }
            return this.projectStatuses.filter(function(s) { return s.value !== 'completed'; });
        },
        parentRequestTypes() {
            const raw = (this.parentProject && this.parentProject.requests) ? String(this.parentProject.requests) : '';
            return raw.split(',').map(r => r.trim()).filter(Boolean);
        },
        activeWorkloadDept() {
            if (this.activeWorkloadDeptId == null || this.activeWorkloadDeptId === '') return null;
            const activeId = String(this.activeWorkloadDeptId);
            return this.workloadStatsByDepartment.find(d => String(d.department_id) === activeId) || null;
        },
        canAddProject() {
            let canAddProject = false;
            if(this.permission && this.permission.length > 0) {
                for (const rule of this.permission) {
                    if (rule.project_add === "1" || rule.project_add === 1) {
                        canAddProject = true;
                        break;
                    }
                }
            }

            return this.isProjectManager || canAddProject;
        },
        canAddQuotation() {
            if (this.isAdmin) return true;
            let canAddQuotation = false;
            if(this.permission && this.permission.length > 0) {
                for (const rule of this.permission) {
                    if (rule.project_director_edit === "1" || rule.project_director_edit === 1) {
                        canAddQuotation = true;
                        break;
                    }
                }
            }
            return this.isProjectManager || canAddQuotation;
        },
        canViewBusinessDocuments() {
            if (this.isAdmin) return true;
            if (!this.permission || this.permission.length === 0) return false;
            return this.permission.some((rule) =>
                rule.project_director_stat === '1' || rule.project_director_stat === 1
                || rule.project_director_view === '1' || rule.project_director_view === 1
                || rule.project_director_edit === '1' || rule.project_director_edit === 1
                || rule.project_director === '1' || rule.project_director === 1
            );
        },
        canEditBusinessDocuments() {
            if (this.isAdmin) return true;
            if (!this.permission || this.permission.length === 0) return false;
            return this.permission.some((rule) =>
                rule.project_director_edit === '1' || rule.project_director_edit === 1
            );
        },
        sortedBusinessDocumentLogs() {
            if (!this.businessDocumentLogs) return [];
            return [...this.businessDocumentLogs]
                .filter((log) => this.isBusinessDocumentLog(log))
                .sort((a, b) => (b.time > a.time ? 1 : -1));
        },
        canAddNote() {
            return this.isAdmin || (this.permission && this.permission.length > 0);
        },
        paginatedPriceListProducts() {
            const startIndex = (this.priceListPage - 1) * this.priceListPageSize;
            const endIndex = startIndex + this.priceListPageSize;
            return this.filteredPriceListProducts.slice(startIndex, endIndex);
        },
        totalPriceListPages() {
            const total = Math.ceil((this.filteredPriceListProducts.length || 0) / (this.priceListPageSize || 1));
            return Math.max(total, 1);
        },
        defaultSetName() {
            const count = this.selectedProducts.length || 0;
            return `商品セット (${count}件)`;
        },
        displayedSetName: {
            get() {
                return (this.selectedSetName && this.selectedSetName.trim()) ? this.selectedSetName : this.defaultSetName;
            },
            set(value) {
                this.selectedSetName = value;
            }
        },
        alreadyAddedProductIds() {
            const idSet = new Set();
            
            // Detect context: use reactive data property and fallback to DOM check
            let isEditingQuotation = this.isPriceListOpenFromEdit;
            
            // Fallback: check DOM if data property is false
            if (!isEditingQuotation) {
                const editQuotationModal = document.getElementById('editQuotationModal');
                isEditingQuotation = editQuotationModal && editQuotationModal.classList.contains('show');
            }
            
            // Get the appropriate quotation items based on context
            const items = isEditingQuotation ? (this.editingQuotation?.items || []) : (this.newQuotation?.items || []);
            
            for (const item of items) {
                if (item && item.is_set && item.set_json) {
                    let products = [];
                    
                    // Handle both object and JSON string cases
                    if (typeof item.set_json === 'object' && item.set_json !== null) {
                        // set_json is already an object
                        products = Array.isArray(item.set_json) ? item.set_json : [];
                    } else if (typeof item.set_json === 'string') {
                        // set_json is a JSON string, try to parse it
                        try {
                            const parsed = JSON.parse(item.set_json);
                            products = Array.isArray(parsed) ? parsed : [];
                        } catch (e) {
                            console.error('Error parsing set_json in alreadyAddedProductIds:', e);
                            products = [];
                        }
                    }
                    
                    if (Array.isArray(products)) {
                        products.forEach(p => { 
                            if (p && p.id != null) idSet.add(p.id); 
                        });
                    }
                } else if (item && item.product_id != null) {
                    idSet.add(item.product_id);
                }
            }
            
            return idSet;
        },
        allOrderItemsSelected() {
            const total = this.newQuotation?.items?.length || 0;
            const selected = this.selectedOrderItemIndexes.length;
            return total > 0 && selected === total;
        },
        selectedChildProjectsForDropdown() {
            // Filter child projects to only show the selected ones for the dropdown
            // Handle type mismatch: selectedChildProjectIds contains integers, project.id is string
            return this.childProjects.filter(project => 
                this.selectedChildProjectIds.includes(parseInt(project.id))
            );
        },
        hasSetProducts() {
            // Check if any items in newQuotation are sets
            return this.newQuotation?.items?.some(item => item.is_set) || false;
        },
        hasSetProductsInView() {
            // Check if any items in selectedQuotation are sets
            return this.selectedQuotation?.items?.some(item => item.is_set) || false;
        },
        // Edit quotation computed properties
        allOrderItemsSelectedForEdit() {
            const total = this.editingQuotation?.items?.length || 0;
            const selected = this.selectedOrderItemIndexesForEdit.length;
            return total > 0 && selected === total;
        },
        selectedChildProjectsForEditDropdown() {
            // Filter child projects to only show the selected ones for the dropdown
            // This matches the behavior of create mode
            // Handle type mismatch: selectedChildProjectIdsForEdit contains integers, project.id is string
            return this.childProjects.filter(project => 
                this.selectedChildProjectIdsForEdit.includes(parseInt(project.id))
            );
        },
        projectsUsedInActiveQuotations() {
            // Get list of project IDs that are already used in active quotations
            // Active quotations are those not cancelled (却下) or canceled (キャンセル)
            const activeStatuses = ['下書き', '発行済み', '承認済み', '調整'];
            const usedProjectIds = new Set();
            
            if (!this.quotations || this.quotations.length === 0) {
                return usedProjectIds;
            }
            
            this.quotations.forEach(quotation => {
                // Skip inactive quotations
                if (!activeStatuses.includes(quotation.status)) {
                    return;
                }
                
                // Parse selected_child_project_ids
                let selectedIds = [];
                if (typeof quotation.selected_child_project_ids === 'string') {
                    selectedIds = quotation.selected_child_project_ids.split(',')
                        .map(id => id.trim())
                        .filter(id => id && id !== '');
                } else if (Array.isArray(quotation.selected_child_project_ids)) {
                    selectedIds = quotation.selected_child_project_ids;
                }
                
                // Add each used project ID to the set
                selectedIds.forEach(id => {
                    usedProjectIds.add(parseInt(id));
                });
            });
            
            return usedProjectIds;
        }
    },
    methods: {
        isChildProjectCreator(project) {
            if (!project || project.created_by == null || project.created_by === '') return false;
            if (typeof CURRENT_USER_ID === 'undefined' || !CURRENT_USER_ID) return false;
            return String(project.created_by) === String(CURRENT_USER_ID);
        },
        canDeleteChildProject(project) {
            if (this.isAdmin) return true;
            if (this.isChildProjectCreator(project)) return true;
            let canDeleteChildProject = false;
            if (this.permission && this.permission.length > 0) {
                for (const rule of this.permission) {
                    if (((rule.project_delete === "1" || rule.project_delete === 1) || (rule.project_manager === "1" || rule.project_manager === 1))
                        && project.department_id == rule.department_id) {
                        canDeleteChildProject = true;
                        break;
                    }
                }
            }
            return canDeleteChildProject;
        },
        canEditChildProject(project) {
            if (this.isAdmin) return true;
            if (this.isChildProjectCreator(project)) return true;
            let canEditChildProject = false;
            if (this.permission && this.permission.length > 0) {
                for (const rule of this.permission) {
                    if (((rule.project_edit === "1" || rule.project_edit === 1) || (rule.project_manager === "1" || rule.project_manager === 1))
                        && project.department_id == rule.department_id) {
                        canEditChildProject = true;
                        break;
                    }
                }
            }
            return canEditChildProject;
        },
        async loadPermission() {
            try {
                const response = await axios.get('/api/index.php?model=department&method=get_user_permissions&parent_project_id=' + this.PARENT_PROJECT_ID);
                this.permission = response.data || [];
            } catch (error) {
                console.error('Error loading permission:', error);
            }
        },
        async loadParentProject() {
            try {
                const response = await axios.get(`/api/index.php?model=parentproject&method=getById&id=${PARENT_PROJECT_ID}`);
                if (response.data) {
                    this.parentProject = response.data;
                    
                    // Load GUIS receiver display name if exists
                    if (this.parentProject.guis_receiver) {
                        await this.loadGuisReceiverDisplayName();
                    }

                    // Load display info (会社名・支店名・担当様) from customer table if possible
                    await this.loadCustomerDisplayInfo();
                } else {
                    showMessage('親プロジェクトが見つかりません。', true);
                    window.location.href = 'index.php';
                }
            } catch (error) {
                console.error('Error loading parent project:', error);
                showMessage('親プロジェクトの読み込みに失敗しました。', true);
                window.location.href = 'index.php';
            }
        },
        async loadChildProjects() {
            try {
                const response = await axios.get(`/api/index.php?model=parentproject&method=getChildProjects&parent_project_id=${PARENT_PROJECT_ID}`);
                if (response.data) {
                    this.childProjects = response.data;
                    this.initVietnamTimeTooltips();
                }
                await this.loadWorkloadStatsByDepartment();
            } catch (error) {
                console.error('Error loading child projects:', error);
                this.childProjects = [];
                this.workloadStatsByDepartment = [];
                this.activeWorkloadDeptId = null;
            }
        },
        translateLabel(label) {
            if (typeof i18next !== 'undefined' && i18next.isInitialized) {
                return i18next.t(label) || label;
            }
            return label;
        },
        emptyYoteiModel() {
            if (typeof window.YoteiField !== 'undefined' && window.YoteiField.emptyModel) {
                return window.YoteiField.emptyModel();
            }
            return { from_month: '', from_part: '', to_month: '', to_part: '' };
        },
        parseYoteiModel(raw) {
            if (typeof window.YoteiField !== 'undefined' && window.YoteiField.parse) {
                const parsed = window.YoteiField.parse(raw);
                return {
                    from_month: parsed.from_month || '',
                    from_part: parsed.from_part || '',
                    to_month: parsed.to_month || '',
                    to_part: parsed.to_part || ''
                };
            }
            return this.emptyYoteiModel();
        },
        formatYoteiDisplay(raw) {
            if (typeof window.YoteiField === 'undefined') return '';
            return window.YoteiField.displayOf(raw) || window.YoteiField.buildDisplay(raw) || '';
        },
        clearChildProjectYotei(isEdit) {
            const target = isEdit ? this.editingChildProject : this.newChildProject;
            target.yotei = this.emptyYoteiModel();
            if (isEdit && this.editChildProjectValidationErrors) this.editChildProjectValidationErrors.yotei = '';
            if (!isEdit && this.childProjectValidationErrors) this.childProjectValidationErrors.yotei = '';
            this.$nextTick(() => this.syncChildProjectYoteiMonthPickers(!!isEdit));
        },
        destroyChildProjectYoteiMonthPickers(isEdit) {
            if (typeof window.YoteiField === 'undefined') return;
            const fromId = isEdit ? 'edit_yotei_from_month' : 'create_yotei_from_month';
            const toId = isEdit ? 'edit_yotei_to_month' : 'create_yotei_to_month';
            window.YoteiField.destroyMonthPicker(document.getElementById(fromId));
            window.YoteiField.destroyMonthPicker(document.getElementById(toId));
        },
        initChildProjectYoteiMonthPickers(isEdit) {
            if (typeof window.YoteiField === 'undefined') return;
            const target = isEdit ? this.editingChildProject : this.newChildProject;
            if (!target.yotei) target.yotei = this.emptyYoteiModel();
            const fromId = isEdit ? 'edit_yotei_from_month' : 'create_yotei_from_month';
            const toId = isEdit ? 'edit_yotei_to_month' : 'create_yotei_to_month';
            window.YoteiField.initMonthPicker(
                document.getElementById(fromId),
                () => target.yotei.from_month,
                (ym) => { target.yotei.from_month = ym || ''; }
            );
            window.YoteiField.initMonthPicker(
                document.getElementById(toId),
                () => target.yotei.to_month,
                (ym) => {
                    target.yotei.to_month = ym || '';
                    if (!ym) target.yotei.to_part = '';
                }
            );
        },
        syncChildProjectYoteiMonthPickers(isEdit) {
            if (typeof window.YoteiField === 'undefined') return;
            const target = isEdit ? this.editingChildProject : this.newChildProject;
            const fromId = isEdit ? 'edit_yotei_from_month' : 'create_yotei_from_month';
            const toId = isEdit ? 'edit_yotei_to_month' : 'create_yotei_to_month';
            const fromEl = document.getElementById(fromId);
            const toEl = document.getElementById(toId);
            if (!fromEl || !fromEl._flatpickr || !toEl || !toEl._flatpickr) {
                this.initChildProjectYoteiMonthPickers(isEdit);
                return;
            }
            window.YoteiField.setMonthPickerValue(fromEl, target.yotei && target.yotei.from_month);
            window.YoteiField.setMonthPickerValue(toEl, target.yotei && target.yotei.to_month);
        },
        appendYoteiToFormData(formData, draft) {
            let payload = null;
            if (typeof window.YoteiField !== 'undefined') {
                payload = window.YoteiField.toPayload(draft || this.emptyYoteiModel());
            }
            formData.append('yotei', payload ? JSON.stringify(payload) : '');
        },
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
        getTaskKindChartColor(value) {
            const normalized = this.normalizeTaskKind(value);
            let colorName = 'secondary';
            if (normalized && normalized !== '未設定') {
                const kind = TASK_KINDS.find(k => k.value === normalized);
                if (kind) colorName = kind.color;
            }
            if (typeof window.Helpers !== 'undefined' && window.Helpers.getCssVar) {
                const hex = window.Helpers.getCssVar(colorName, true);
                if (hex) return hex;
            }
            const fallbacks = {
                success: '#28c76f',
                danger: '#ea5455',
                warning: '#ff9f43',
                primary: '#7367f0',
                info: '#00cfe8',
                secondary: '#a8aaae',
                dark: '#4b4b4b'
            };
            return fallbacks[colorName] || fallbacks.secondary;
        },
        getWorkloadChartElementId() {
            return 'workload-dept-chart-active';
        },
        isDarkTheme() {
            if (typeof window.Helpers !== 'undefined' && typeof window.Helpers.isDarkStyle === 'function') {
                return window.Helpers.isDarkStyle();
            }
            return document.documentElement.getAttribute('data-bs-theme') === 'dark';
        },
        getWorkloadChartTextColor() {
            if (this.isDarkTheme()) {
                return '#fff';
            }
            if (typeof window.Helpers !== 'undefined' && window.Helpers.getCssVar) {
                return window.Helpers.getCssVar('heading-color', true) || '#566a7f';
            }
            return '#566a7f';
        },
        destroyWorkloadChart() {
            if (this.workloadChartRenderTimer) {
                clearTimeout(this.workloadChartRenderTimer);
                this.workloadChartRenderTimer = null;
            }
            if (this.workloadChartInstance) {
                this.workloadChartInstance.destroy();
                this.workloadChartInstance = null;
            }
            const chartElement = document.getElementById(this.getWorkloadChartElementId());
            if (chartElement) chartElement.innerHTML = '';
        },
        destroyAllWorkloadCharts() {
            this.destroyWorkloadChart();
        },
        renderActiveWorkloadChart() {
            if (this.loadingWorkloadStats) return;

            const dept = this.activeWorkloadDept;
            if (!dept) {
                this.destroyWorkloadChart();
                return;
            }

            const chartItems = dept.byKind.filter(item => item.hours > 0);
            this.destroyWorkloadChart();
            if (!chartItems.length) return;

            const ApexChartsClass = window.ApexCharts || (typeof ApexCharts !== 'undefined' ? ApexCharts : null);
            if (!ApexChartsClass) return;

            const elementId = this.getWorkloadChartElementId();
            const formatHours = (val) => this.formatTotalWorkload(val);
            const totalLabel = this.translateLabel('工数合計');
            const renderToken = ++this.workloadChartRenderToken;

            this.$nextTick(() => {
                this.workloadChartRenderTimer = setTimeout(() => {
                    this.workloadChartRenderTimer = null;
                    if (renderToken !== this.workloadChartRenderToken) return;

                    const chartElement = document.getElementById(elementId);
                    if (!chartElement || chartElement.offsetParent === null) return;

                    chartElement.innerHTML = '';

                    const series = chartItems.map(item => item.hours);
                    const labels = chartItems.map(item => this.getTaskKindLabel(item.kind));
                    const colors = chartItems.map(item => this.getTaskKindChartColor(item.kind));
                    const totalWorkload = dept.totalWorkload;
                    const chartTextColor = this.getWorkloadChartTextColor();

                    const options = {
                        series,
                        chart: {
                            type: 'donut',
                            height: 300,
                            fontFamily: (window.config && window.config.fontFamily) ? window.config.fontFamily : 'inherit'
                        },
                        labels,
                        colors,
                        stroke: { width: 0 },
                        legend: {
                            position: 'bottom',
                            horizontalAlign: 'center',
                            fontSize: '12px',
                            markers: { width: 10, height: 10, radius: 2 },
                            labels: {
                                colors: chartTextColor
                            }
                        },
                        dataLabels: {
                            enabled: true,
                            style: {
                                colors: [chartTextColor]
                            },
                            formatter(val, opts) {
                                return formatHours(opts.w.config.series[opts.seriesIndex]);
                            }
                        },
                        tooltip: {
                            theme: this.isDarkTheme() ? 'dark' : 'light',
                            y: {
                                formatter: (val) => formatHours(val)
                            }
                        },
                        plotOptions: {
                            pie: {
                                donut: {
                                    size: '68%',
                                    labels: {
                                        show: true,
                                        name: {
                                            fontSize: '14px',
                                            color: chartTextColor
                                        },
                                        value: {
                                            fontSize: '18px',
                                            fontWeight: 600,
                                            color: chartTextColor,
                                            formatter: (val) => formatHours(val)
                                        },
                                        total: {
                                            show: true,
                                            label: totalLabel,
                                            fontSize: '13px',
                                            color: chartTextColor,
                                            formatter: () => formatHours(totalWorkload)
                                        }
                                    }
                                }
                            }
                        }
                    };

                    try {
                        this.workloadChartInstance = new ApexChartsClass(chartElement, options);
                        this.workloadChartInstance.render();
                    } catch (error) {
                        console.error('Error rendering workload chart:', error);
                    }
                }, 150);
            });
        },
        formatTotalWorkload(value) {
            const n = parseFloat(value);
            if (Number.isNaN(n) || n <= 0) return '0h';
            const formatted = Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
            return formatted + 'h';
        },
        formatWorkloadPercent(hours, total) {
            const h = parseFloat(hours);
            const t = parseFloat(total);
            if (Number.isNaN(t) || t <= 0 || Number.isNaN(h) || h <= 0) return '0%';
            const pct = Math.round((h / t) * 1000) / 10;
            return (Number.isInteger(pct) ? String(pct) : pct.toFixed(1)) + '%';
        },
        async loadWorkloadStatsByDepartment() {
            if (!this.childProjects || this.childProjects.length === 0) {
                this.destroyAllWorkloadCharts();
                this.workloadStatsByDepartment = [];
                this.activeWorkloadDeptId = null;
                return;
            }
            this.workloadChartRenderToken++;
            this.destroyAllWorkloadCharts();
            this.loadingWorkloadStats = true;
            try {
                const deptMap = {};
                this.childProjects.forEach((p) => {
                    const deptId = String(p.department_id || '0');
                    const deptName = p.department_name || this.translateLabel('未設定');
                    if (!deptMap[deptId]) {
                        deptMap[deptId] = {
                            department_id: deptId,
                            department_name: deptName,
                            projectIds: new Set(),
                            kindMap: {},
                            totalWorkload: 0,
                            taskCount: 0
                        };
                    }
                    deptMap[deptId].projectIds.add(p.id);
                });

                const taskResponses = await Promise.all(
                    this.childProjects.map((p) =>
                        axios.get(`/api/index.php?model=task&method=list&project_id=${p.id}&include_subtasks=1`)
                            .then(r => ({ project: p, tasks: r.data || [] }))
                            .catch(() => ({ project: p, tasks: [] }))
                    )
                );

                taskResponses.forEach(({ project, tasks }) => {
                    const deptId = String(project.department_id || '0');
                    const dept = deptMap[deptId];
                    if (!dept) return;
                    tasks.forEach((t) => {
                        let kind = this.normalizeTaskKind(t.task_kind);
                        if (!kind) kind = '未設定';
                        const n = parseFloat(t.estimated_hours);
                        const hours = Number.isNaN(n) || n <= 0 ? 0 : n;
                        dept.totalWorkload += hours;
                        dept.taskCount += 1;
                        if (!dept.kindMap[kind]) {
                            dept.kindMap[kind] = { kind, hours: 0, count: 0 };
                        }
                        dept.kindMap[kind].hours += hours;
                        dept.kindMap[kind].count += 1;
                    });
                });

                const predefinedOrder = TASK_KINDS.map(k => k.value);
                this.workloadStatsByDepartment = Object.values(deptMap)
                    .map((dept) => ({
                        department_id: dept.department_id,
                        department_name: dept.department_name,
                        projectCount: dept.projectIds.size,
                        taskCount: dept.taskCount,
                        totalWorkload: dept.totalWorkload,
                        byKind: Object.values(dept.kindMap).sort((a, b) => {
                            const ai = predefinedOrder.indexOf(a.kind);
                            const bi = predefinedOrder.indexOf(b.kind);
                            if (ai !== -1 && bi !== -1) return ai - bi;
                            if (ai !== -1) return -1;
                            if (bi !== -1) return 1;
                            return b.hours - a.hours;
                        })
                    }))
                    .sort((a, b) => a.department_name.localeCompare(b.department_name, 'ja'));

                if (this.workloadStatsByDepartment.length) {
                    const activeExists = this.workloadStatsByDepartment.some(
                        d => String(d.department_id) === String(this.activeWorkloadDeptId)
                    );
                    if (!activeExists) {
                        this.activeWorkloadDeptId = this.workloadStatsByDepartment[0].department_id;
                    }
                } else {
                    this.activeWorkloadDeptId = null;
                }
            } catch (error) {
                console.error('Error loading workload stats by department:', error);
                this.workloadStatsByDepartment = [];
                this.activeWorkloadDeptId = null;
            } finally {
                this.loadingWorkloadStats = false;
                this.$nextTick(() => this.renderActiveWorkloadChart());
            }
        },
        async toggleFavorite() {
            if (!this.parentProject || !this.parentProject.id) return;
            
            try {
                const formData = new FormData();
                formData.append('parent_project_id', this.parentProject.id);
                
                const response = await axios.post('/api/index.php?model=parentproject&method=toggleFavorite', formData);
                
                if (response.data && response.data.status === 'success') {
                    // Update the project's favorite status (convert boolean to number for consistency)
                    this.parentProject.is_favorite = response.data.is_favorite ? 1 : 0;
                } else {
                    showMessage(response.data?.message || '操作に失敗しました。', true);
                }
            } catch (error) {
                console.error('Error toggling favorite:', error);
                showMessage('操作に失敗しました。', true);
            }
        },
        async toggleProjectFavorite(project) {
            if (!project || !project.id) return;
            
            try {
                const formData = new FormData();
                formData.append('project_id', project.id);
                
                const response = await axios.post('/api/index.php?model=project&method=toggleFavorite', formData);
                
                if (response.data && response.data.status === 'success') {
                    // Update the project's favorite status (convert boolean to number for consistency)
                    project.is_favorite = response.data.is_favorite ? 1 : 0;
                } else {
                    if (typeof showMessage === 'function') {
                        showMessage(response.data?.message || '操作に失敗しました。', true);
                    } else {
                        alert(response.data?.message || '操作に失敗しました。');
                    }
                }
            } catch (error) {
                console.error('Error toggling project favorite:', error);
                if (typeof showMessage === 'function') {
                    showMessage('操作に失敗しました。', true);
                } else {
                    alert('操作に失敗しました。');
                }
            }
        },
        getParentProjectStatusLabel(status) {
            if (!status) return '-';
            
            // Handle both string and number status values
            const statusStr = String(status).toLowerCase().trim();
            
            // Try exact match first
            let s = this.statuses.find(s => s.value === statusStr);
            
            if (s) return s.label;
            
            // If no label found, format the raw status value nicely
            if (typeof status === 'string') {
                return status.charAt(0).toUpperCase() + status.slice(1).replace(/_/g, ' ');
            }
            return status;
        },
        
        getStatusLabel(status) {
            // This is for quotation status - return as is since quotation uses Japanese labels
            return status || '-';
        },
        getParentProjectStatusBadgeClass(status) {
            if (!status) return 'bg-secondary';
            // Handle both string and number status values
            const statusStr = String(status).toLowerCase().trim();
            const s = this.statuses.find(s => s.value === statusStr);
            return `bg-${s?.color || 'secondary'}`;
        },
        
        getStatusBadgeClass(status) {
            // This is for quotation status - return default class
            if (!status) return 'bg-secondary';
            return 'bg-primary';
        },
        isProjectStatusKey(value) {
            return !!value && this.projectStatuses.some(s => s.value === value);
        },
        getLogNote(log) {
            if (!log || !log.note) return '';
            if (log.action === 'status_changed' || log.note === 'ステータスを変更' || log.note.indexOf('ステータス変更') === 0) {
                return this.translateLabel('ステータス変更');
            }
            return log.note;
        },
        getProjectStatusLabel(status) {
            const s = this.projectStatuses.find(s => s.value === status);
            return s ? this.translateLabel(s.label) : status;
        },
        getProjectStatusBadgeClass(status) {
            const s = this.projectStatuses.find(s => s.value === status);
            return `bg-${s?.color || 'secondary'}`;
        },
        getProjectStatusButtonClass(status) {
            const s = this.projectStatuses.find(s => s.value === status);
            return `btn-${s?.color || 'secondary'}`;
        },
        async selectProjectStatus(status, isEdit = false) {
            if (this.isCailyBranchUser && status === 'completed' && !this.canViewEndDate) {
                return;
            }
            if (isEdit) {
                const prev = this.editingChildProject && this.editingChildProject.status;
                if (status === 'completed' && prev !== 'completed') {
                    const ok = await this.confirmPaymentInfoBeforeComplete(this.editingChildProject);
                    if (!ok) return;
                }
                this.editingChildProject.status = status;
                // Close dropdown
                const dropdownElement = document.querySelector('#editChildProjectStatusDropdown');
                if (dropdownElement) {
                    const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                    if (dropdown) {
                        dropdown.hide();
                    }
                }
            } else {
                this.newChildProject.status = status;
                // Close dropdown
                const dropdownElement = document.querySelector('#createChildProjectStatusDropdown');
                if (dropdownElement) {
                    const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                    if (dropdown) {
                        dropdown.hide();
                    }
                }
            }
        },
        getOrderTypeBadgeClass(orderType) {
            const type = String(orderType || '').trim();
            switch (type) {
                case '修正':
                    return 'bg-warning'; // Yellow for edit
                case '新規':
                    return 'bg-primary'; // Green for new
                case '新規修正':
                    return 'bg-success'; // Green for new revision
                case '変更':
                    return 'bg-danger'; // Red for change
                default:
                    return 'bg-info'; // Gray for unknown types
            }
        },
        getParentRequestColor(request) {
            const map = {
                '意匠': 'primary',
                '設備': 'info',
                '3D設備': 'success',
                '省エネ': 'warning',
                '3D': 'secondary',
                'その他': 'dark'
            };
            return map[String(request || '').trim()] || 'secondary';
        },
        getParentRequestBadgeClass(request) {
            return `bg-${this.getParentRequestColor(request)}`;
        },
        mapDepartmentNameToRequestType(departmentName) {
            const name = String(departmentName || '').trim();
            const map = {
                '設備設計': '設備',
                '意匠設計': '意匠',
                '省エネ計算': '省エネ',
                '技術課設備': '3D設備'
            };
            return map[name] || '';
        },
        resolveChildDepartmentName(departmentId) {
            const id = parseInt(departmentId, 10);
            if (!id) return '';
            const found = (this.departments || []).find(d => parseInt(d.id, 10) === id);
            return found ? (found.name || '') : '';
        },
        getEnergyDrawingShareLabel(project) {
            return window.EnergyDrawingShare
                ? window.EnergyDrawingShare.formatEnergyDrawingShareLabel(project)
                : '';
        },
        getEnergyDrawingShareBadgeClass(project) {
            return window.EnergyDrawingShare
                ? window.EnergyDrawingShare.formatEnergyDrawingShareBadgeClass(project)
                : '';
        },
        shouldShowEnergyDrawingShareBadge(project) {
            return !!(project && project.energy_drawing_share_status);
        },
        isParentRequestFulfilled(requestType) {
            const type = String(requestType || '').trim();
            if (!type || !Array.isArray(this.childProjects)) return false;
            return this.childProjects.some((p) => {
                if (String(p.status || '') === 'deleted') return false;
                return this.mapDepartmentNameToRequestType(p.department_name) === type;
            });
        },
        formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('ja-JP');
        },
        formatDateTime(dateString) {
            return formatProjectDateTimeForDisplay(dateString);
        },
        formatDateTimeDatePart(dateString) {
            return formatProjectDateOnlyForDisplay(dateString);
        },
        formatDateTimeTimePart(dateString) {
            return formatProjectTimeOnlyForDisplay(dateString);
        },
        getProjectDateTimePlaceholder() {
            return getProjectDateTimePlaceholder();
        },
        toChildProjectAPIDate(str) {
            if (str == null || str === '') return '';
            return fromProjectDateTimeInputValue(str);
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
        hasMaterial(materialsString, materialName) {
            if (!materialsString) return false;
            const materials = materialsString.split(',').map(m => m.trim());
            return materials.includes(materialName);
        },
        onBeforeUnload(e) {
            if (this.isEditMode) {
                e.preventDefault();
                e.returnValue = '';
            }
        },
        addBeforeUnloadWarning() {
            if (!this._boundBeforeUnload) {
                this._boundBeforeUnload = this.onBeforeUnload.bind(this);
                window.addEventListener('beforeunload', this._boundBeforeUnload);
            }
        },
        removeBeforeUnloadWarning() {
            if (this._boundBeforeUnload) {
                window.removeEventListener('beforeunload', this._boundBeforeUnload);
                this._boundBeforeUnload = null;
            }
        },
        toggleEditMode() {
            this.isEditMode = true;
            this.addBeforeUnloadWarning();
            this.originalParentProject = JSON.parse(JSON.stringify(this.parentProject));
            this.loadInitialData();
            this.parseRequests();
            this.parseMaterials();
            this.$nextTick(async () => {
                await this.loadBranches();
                await this.loadContacts();
                this.initSelect2();
                this.initDatePickers();
                this.initTagify();
            });
        },
        cancelEdit() {
            this.isEditMode = false;
            this.removeBeforeUnloadWarning();
            this.parentProject = JSON.parse(JSON.stringify(this.originalParentProject));
            this.validationErrors = {
                company_name: '',
                project_name: ''
            };
            
            // Destroy Select2 instances to prevent duplicates
            this.destroySelect2Instances();
        },
        async loadInitialData() {
            await Promise.all([
                this.loadCompanies(),
                this.loadUsers()
            ]);
        },
        async loadCompanies() {
            try {
                const response = await axios.get('/api/index.php?model=customer&method=list_companies');
                if (response.data && response.data.status === 'success') {
                    const list = response.data.data || [];
                    this.companies = list.slice().sort((a, b) => {
                        const nameA = (a.company_name || '').toString();
                        const nameB = (b.company_name || '').toString();
                        const hasA = nameA.includes('大東');
                        const hasB = nameB.includes('大東');
                        if (hasA && !hasB) return -1;
                        if (!hasA && hasB) return 1;
                        return nameA.localeCompare(nameB);
                    });
                }
            } catch (error) {
                console.error('Error loading companies:', error);
            }
        },
        async loadUsers() {
            try {
                const response = await axios.get('/api/index.php?model=user&method=searchMembers');
                if (response.data && response.data.status === 'success') {
                    this.users = response.data.data || [];
                }
                // Ensure current GUIS receiver value is included
                if (this.parentProject.guis_receiver && !this.users.find(u => u.user_name === this.parentProject.guis_receiver)) {
                    this.users.push({ id: 0, user_name: this.parentProject.guis_receiver });
                }
            } catch (error) {
                console.error('Error loading users:', error);
            }
        },
        onCompanyChange() {
            // Clear branch and contact when company changes
            this.parentProject.branch_name = '';
            this.parentProject.contact_name = '';
            
            // Update branch select2 - trigger to reload data
            const $branch = $('#branch_name');
            if ($branch.length && $branch.data('select2')) {
                $branch.val(null).trigger('change');
                // Force reload of branch data
                $branch.select2('destroy');
                this.initBranchSelect2();
            }
            
            // Update contact select2
            const $contact = $('#contact_name');
            if ($contact.length && $contact.data('select2')) {
                $contact.val(null).trigger('change');
                $contact.select2('destroy');
                this.initContactSelect2();
            }
        },
        async loadBranches() {
            if (!this.parentProject.company_name) {
                this.branches = [];
                return;
            }
            try {
                const response = await axios.get(`/api/index.php?model=customer&method=list_branches_by_company&company_name=${encodeURIComponent(this.parentProject.company_name)}`);
                if (response.data && response.data.status === 'success') {
                    this.branches = response.data.data || [];
                }
                // Don't include current branch value when company changes - let user select fresh
            } catch (error) {
                console.error('Error loading branches:', error);
                this.branches = [];
            }
        },
        onBranchChange() {
            // Clear contact when branch changes
            this.parentProject.contact_name = '';
            
            // Update contact select2
            const $contact = $('#contact_name');
            if ($contact.length && $contact.data('select2')) {
                $contact.val(null).trigger('change');
                $contact.select2('destroy');
                this.initContactSelect2();
            }
        },
        async loadContacts() {
            if (!this.parentProject.company_name || !this.parentProject.branch_name) {
                this.contacts = [];
                return;
            }
            try {
                const response = await axios.get(`/api/index.php?model=customer&method=list_contacts_by_company_branch&company_name=${encodeURIComponent(this.parentProject.company_name)}&branch_name=${encodeURIComponent(this.parentProject.branch_name)}`);
                if (response.data && response.data.status === 'success') {
                    this.contacts = response.data.data || [];
                }
                // Don't include current contact value when branch changes - let user select fresh
            } catch (error) {
                console.error('Error loading contacts:', error);
                this.contacts = [];
            }
        },
        initSelect2() {
            // Initialize Select2 dropdowns
            this.initCompanySelect2();
            this.initBranchSelect2();
            this.initContactSelect2();
            this.initGuisReceiverSelect2();
        },
        initCompanySelect2() {
            const $company = $('#company_name');
            if ($company.length) {
                $company.select2({
                    placeholder: '選択してください',
                    dropdownParent: $company.parent(),
                    allowClear: true,
                    minimumResultsForSearch: 0,
                    ajax: {
                        url: '/api/index.php?model=customer&method=list_companies',
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                search: params.term,
                                page: params.page || 1
                            };
                        },
                        processResults: function(data) {
                            const list = (data.data || []).slice().sort((a, b) => {
                                const nameA = (a.company_name || '').toString();
                                const nameB = (b.company_name || '').toString();
                                const hasA = nameA.includes('大東');
                                const hasB = nameB.includes('大東');
                                if (hasA && !hasB) return -1;
                                if (!hasA && hasB) return 1;
                                return nameA.localeCompare(nameB);
                            });
                            return {
                                results: list.map(function(item) {
                                    return {
                                        id: item.company_name,
                                        text: item.company_name
                                    };
                                })
                            };
                        }
                    }
                }).on('select2:select', (e) => {
                    this.parentProject.company_name = e.params.data.id;
                    this.onCompanyChange();
                }).on('select2:clear', () => {
                    this.parentProject.company_name = '';
                    this.onCompanyChange();
                });

                // Set current value if exists
                if (this.parentProject.company_name) {
                    // Add the current option to the select
                    const option = new Option(this.parentProject.company_name, this.parentProject.company_name, true, true);
                    $company.append(option).trigger('change');
                }
            }
        },
        initBranchSelect2() {
            const $branch = $('#branch_name');
            if ($branch.length) {
                $branch.select2({
                    placeholder: '選択してください',
                    dropdownParent: $branch.parent(),
                    allowClear: true,
                    minimumResultsForSearch: 0,
                    ajax: {
                        url: '/api/index.php?model=customer&method=list_branches_by_company',
                        dataType: 'json',
                        delay: 250,
                        data: (params) => {
                            return {
                                search: params.term,
                                page: params.page || 1,
                                company_name: this.parentProject.company_name
                            };
                        },
                        processResults: function(data) {
                            return {
                                results: data.data.map(function(item) {
                                    return {
                                        id: item.branch,
                                        text: item.branch
                                    };
                                })
                            };
                        }
                    }
                }).on('select2:select', (e) => {
                    this.parentProject.branch_name = e.params.data.id;
                    this.onBranchChange();
                }).on('select2:clear', () => {
                    this.parentProject.branch_name = '';
                    this.onBranchChange();
                });

                // Set current value if exists
                if (this.parentProject.branch_name) {
                    // Add the current option to the select
                    const option = new Option(this.parentProject.branch_name, this.parentProject.branch_name, true, true);
                    $branch.append(option).trigger('change');
                }
            }
        },
        initContactSelect2() {
            const $contact = $('#contact_name');
            if ($contact.length) {
                $contact.select2({
                    placeholder: '選択してください',
                    dropdownParent: $contact.parent(),
                    allowClear: true,
                    minimumResultsForSearch: 0,
                    ajax: {
                        url: '/api/index.php?model=customer&method=list_contacts_by_company_branch',
                        dataType: 'json',
                        delay: 250,
                        data: (params) => {
                            return {
                                search: params.term,
                                page: params.page || 1,
                                company_name: this.parentProject.company_name,
                                branch_name: this.parentProject.branch_name
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
                }).on('select2:select', (e) => {
                    this.parentProject.contact_name = e.params.data.text;
                    this.parentProject.customer_id = e.params.data.id;
                }).on('select2:clear', () => {
                    this.parentProject.contact_name = '';
                    this.parentProject.customer_id = '';
                });

                // Set current value if exists
                if (this.parentProject.contact_name) {
                    // Add the current option to the select using customer_id as value
                    const optionValue = this.parentProject.customer_id || this.parentProject.contact_name;
                    const option = new Option(this.parentProject.contact_name, optionValue, true, true);
                    $contact.append(option).trigger('change');
                }
            }
        },
        initGuisReceiverSelect2() {
            const $guisReceiver = $('#guis_receiver');
            if ($guisReceiver.length) {
                $guisReceiver.select2({
                    placeholder: '選択してください',
                    dropdownParent: $guisReceiver.parent(),
                    allowClear: true,
                    minimumResultsForSearch: 0,
                    ajax: {
                        url: '/api/index.php?model=user&method=searchMembers',
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
                                        id: item.userid,
                                        text: item.realname
                                    };
                                })
                            };
                        }
                    }
                }).on('select2:select', (e) => {
                    this.parentProject.guis_receiver = e.params.data.id;
                }).on('select2:clear', () => {
                    this.parentProject.guis_receiver = '';
                });

                // Set current value if exists - load the user name from API
                if (this.parentProject.guis_receiver) {
                    this.loadGuisReceiverDisplayName();
                }
            }
        },
        async loadGuisReceiverDisplayName() {
            try {
                const response = await axios.get('/api/index.php?model=user&method=searchMembers');
                if (response.data && response.data.data) {
                    const user = response.data.data.find(u => u.userid === this.parentProject.guis_receiver);
                    if (user) {
                        // Set display name for view mode
                        this.guisReceiverDisplayName = user.realname;
                        
                        // Update Select2 dropdown if in edit mode
                        const $guisReceiver = $('#guis_receiver');
                        if ($guisReceiver.length && $guisReceiver.data('select2')) {
                            // Clear existing options and add the current user
                            $guisReceiver.empty();
                            const option = new Option(user.realname, this.parentProject.guis_receiver, true, true);
                            $guisReceiver.append(option).trigger('change');
                        }
                    }
                }
            } catch (error) {
                console.error('Error loading GUIS receiver display name:', error);
            }
        },
        initDatePickers() {
            // Request date picker (with time)
            const requestDateOptions = {
                enableTime: true,
                dateFormat: "Y/m/d H:i",
                time_24hr: true,
                allowInput: true,
                locale: "ja",
                defaultHour: 9,
                defaultMinute: 0,
                onChange: (selectedDates, dateStr, instance) => {
                    // Only update if this is actually the request date picker
                    if (instance.input && instance.input.id === 'request_date_picker') {
                        this.parentProject.request_date = dateStr;
                    }
                }
            };

            const requestDateEl = document.getElementById('request_date_picker');
            if (requestDateEl) {
                if (requestDateEl._flatpickr) requestDateEl._flatpickr.destroy();
                flatpickr(requestDateEl, requestDateOptions);
            }

            // Desired delivery date picker (date only)
            const desiredDeliveryOptions = {
                enableTime: false,
                dateFormat: "Y/m/d",
                allowInput: true,
                locale: "ja",
                onChange: (selectedDates, dateStr, instance) => {
                    // Only update if this is actually the desired delivery date picker
                    if (instance.input && instance.input.id === 'desired_delivery_date_picker') {
                        this.parentProject.desired_delivery_date = dateStr;
                    }
                }
            };

            const desiredDeliveryEl = document.getElementById('desired_delivery_date_picker');
            if (desiredDeliveryEl) {
                if (desiredDeliveryEl._flatpickr) desiredDeliveryEl._flatpickr.destroy();
                flatpickr(desiredDeliveryEl, desiredDeliveryOptions);
            }
        },
        // Helper method to safely update Flatpickr instances
        _safeUpdateFlatpickr(elementId, dateStr, dataField) {
            const targetEl = document.getElementById(elementId);
            
            // Update the data
           // this.parentProject[dataField] = dateStr;
            //Update the flatpickr instance
            if (targetEl && targetEl._flatpickr) {
                targetEl._flatpickr.setDate(dateStr);
            }
        },
        
        // Set current date and time for request date
        setCurrentDateTime() {
            const now = new Date();
            const dateStr = now.getFullYear() + '/' + 
                String(now.getMonth() + 1).padStart(2, '0') + '/' + 
                String(now.getDate()).padStart(2, '0') + ' ' + 
                String(now.getHours()).padStart(2, '0') + ':' + 
                String(now.getMinutes()).padStart(2, '0');
            
            this._safeUpdateFlatpickr('request_date_picker', dateStr, 'request_date');
        },
        
        // Set today's date for desired delivery date
        setTodayDate() {
            const today = new Date();
            const dateStr = today.getFullYear() + '/' + 
                String(today.getMonth() + 1).padStart(2, '0') + '/' + 
                String(today.getDate()).padStart(2, '0');
            
            this._safeUpdateFlatpickr('desired_delivery_date_picker', dateStr, 'desired_delivery_date');
        },
        initializeQuotationDatePickers() {
            // Initialize Flatpickr for quotation date fields
            if (window.flatpickr) {
                // Initialize issue date picker
                const issueDateEl = document.getElementById('quotation_issue_date');
                if (issueDateEl) {
                    if (issueDateEl._flatpickr) {
                        issueDateEl._flatpickr.destroy();
                    }
                    issueDateEl._flatpickr = flatpickr(issueDateEl, {
                        dateFormat: 'Y-m-d',
                        locale: 'ja',
                        allowInput: true,
                        clickOpens: true,
                        onChange: (selectedDates, dateStr) => {
                            this.newQuotation.issue_date = dateStr;
                            // Auto-calculate valid_until if not custom
                            if (this.newQuotation.valid_until_type !== 'custom') {
                                this.onValidUntilTypeChange();
                            }
                        }
                    });
                    
                    // Set initial date if available
                    if (this.newQuotation.issue_date) {
                        issueDateEl._flatpickr.setDate(this.newQuotation.issue_date);
                    }
                }
                
                // Initialize delivery date picker using hidden input
                const deliveryDatePickerEl = document.getElementById('quotation_delivery_date_picker');
                if (deliveryDatePickerEl) {
                    if (deliveryDatePickerEl._flatpickr) {
                        deliveryDatePickerEl._flatpickr.destroy();
                    }
                    const modalElement = document.getElementById('createQuotationModal');
                    deliveryDatePickerEl._flatpickr = flatpickr(deliveryDatePickerEl, {
                        dateFormat: 'Y年n月j日',
                        altFormat: 'Y年n月j日',
                        locale: 'ja',
                        allowInput: false,
                        clickOpens: false,
                        static: false,
                        appendTo: modalElement ? modalElement : document.body,
                        onChange: (selectedDates, dateStr) => {
                            // Update the visible input with the selected date
                            if (dateStr) {
                                this.newQuotation.delivery_date = dateStr;
                            }
                        }
                    });
                    
                    // Set initial date if available and it's a valid date
                    if (this.newQuotation.delivery_date) {
                        try {
                            const date = new Date(this.newQuotation.delivery_date);
                            if (!isNaN(date.getTime())) {
                                deliveryDatePickerEl._flatpickr.setDate(this.newQuotation.delivery_date);
                            }
                        } catch (e) {
                            // Ignore invalid date errors
                        }
                    }
                }
                
                // Initialize valid until date picker
                const validUntilEl = document.getElementById('quotation_valid_until');
                if (validUntilEl) {
                    if (validUntilEl._flatpickr) {
                        validUntilEl._flatpickr.destroy();
                    }
                    validUntilEl._flatpickr = flatpickr(validUntilEl, {
                        dateFormat: 'Y-m-d',
                        locale: 'ja',
                        allowInput: true,
                        clickOpens: true,
                        onChange: (selectedDates, dateStr) => {
                            this.newQuotation.valid_until = dateStr;
                        }
                    });
                    
                    // Set initial date if available
                    if (this.newQuotation.valid_until) {
                        validUntilEl._flatpickr.setDate(this.newQuotation.valid_until);
                    }
                }
            }
        },
        initTagify() {
            // Initialize Tagify after Vue is mounted
            this.$nextTick(() => {
                // --- Tagify for Type1 (種類1) ---
                const type1Input = document.querySelector('#type1_tags');
                if (type1Input && window.Tagify && !type1Input._tagify) {
                    if (this.type1Tagify) {
                        try {
                            this.type1Tagify.destroy();
                        } catch (e) {
                            console.log('Error destroying existing type1Tagify:', e);
                        }
                    }
                    this.type1Tagify = new Tagify(type1Input, {
                        whitelist: ['TAC', '特注'],
                        maxTags: 5,
                        dropdown: {
                            maxItems: 20,
                            classname: "tags-look-type1",
                            enabled: 0,
                            closeOnSelect: true
                        },
                    });
                    const updateType1 = () => {
                        this.parentProject.type1 = this.type1Tagify.value.map(tag => tag.value).join(',');
                    };
                    this.type1Tagify.on('add', updateType1);
                    this.type1Tagify.on('remove', updateType1);
                    
                    // Set initial value if exists
                    if (this.parentProject.type1) {
                        const tags = this.parentProject.type1.split(',').map(tag => tag.trim()).filter(tag => tag);
                       // this.type1Tagify.addTags(tags);
                    }
                }
                
                // --- Tagify for Type2 (種類2) ---
                const type2Input = document.querySelector('#type2_tags');
                if (type2Input && window.Tagify && !type2Input._tagify) {
                    if (this.type2Tagify) {
                        try {
                            this.type2Tagify.destroy();
                        } catch (e) {
                            console.log('Error destroying existing type2Tagify:', e);
                        }
                    }
                    this.type2Tagify = new Tagify(type2Input, {
                        whitelist: ['共同', '集合'],
                        maxTags: 5,
                        dropdown: {
                            maxItems: 20,
                            classname: "tags-look-type2",
                            enabled: 0,
                            closeOnSelect: true
                        },
                    });
                    const updateType2 = () => {
                        this.parentProject.type2 = this.type2Tagify.value.map(tag => tag.value).join(',');
                    };
                    this.type2Tagify.on('add', updateType2);
                    this.type2Tagify.on('remove', updateType2);
                    
                    // Set initial value if exists
                    if (this.parentProject.type2) {
                        const tags = this.parentProject.type2.split(',').map(tag => tag.trim()).filter(tag => tag);
                        //this.type2Tagify.addTags(tags);
                    }
                }
                
                // --- Tagify for Construction Branch (工事支店) ---
                const constructionBranchInput = document.querySelector('#construction_branch_tags');
                if (constructionBranchInput && window.Tagify && !constructionBranchInput._tagify) {
                    if (this.constructionBranchTagify) {
                        try {
                            this.constructionBranchTagify.destroy();
                        } catch (e) {
                            console.log('Error destroying existing constructionBranchTagify:', e);
                        }
                    }
                    this.constructionBranchTagify = new Tagify(constructionBranchInput, {
                        whitelist: ['北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
                '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
                '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
                '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
                '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
                '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
                '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'],
                        maxTags: 5,
                        dropdown: {
                            maxItems: 20,
                            classname: "tags-look-construction-branch",
                            enabled: 0,
                            closeOnSelect: true
                        },
                    });
                    const updateConstructionBranch = () => {
                        this.parentProject.construction_branch = this.constructionBranchTagify.value.map(tag => tag.value).join(',');
                    };
                    this.constructionBranchTagify.on('add', updateConstructionBranch);
                    this.constructionBranchTagify.on('remove', updateConstructionBranch);
                    
                    // Set initial value if exists
                    if (this.parentProject.construction_branch) {
                        const tags = this.parentProject.construction_branch.split(',').map(tag => tag.trim()).filter(tag => tag);
                        //this.constructionBranchTagify.addTags(tags);
                    }
                }
            });
        },
        clearTagifyTags(fieldName) {
            if (fieldName === 'type1' && this.type1Tagify) {
                this.type1Tagify.removeAllTags();
            } else if (fieldName === 'type2' && this.type2Tagify) {
                this.type2Tagify.removeAllTags();
            } else if (fieldName === 'construction_branch' && this.constructionBranchTagify) {
                this.constructionBranchTagify.removeAllTags();
            }
        },
        destroySelect2Instances() {
            // Destroy company Select2
            const $company = $('#company_name');
            if ($company.length && $company.data('select2')) {
                $company.select2('destroy');
            }
            
            // Destroy branch Select2
            const $branch = $('#branch_name');
            if ($branch.length && $branch.data('select2')) {
                $branch.select2('destroy');
            }
            
            // Destroy contact Select2
            const $contact = $('#contact_name');
            if ($contact.length && $contact.data('select2')) {
                $contact.select2('destroy');
            }
            
            // Destroy GUIS receiver Select2
            const $guisReceiver = $('#guis_receiver');
            if ($guisReceiver.length && $guisReceiver.data('select2')) {
                $guisReceiver.select2('destroy');
            }
        },
        parseRequests() {
            if (!this.parentProject.requests) {
                this.request_design = false;
                this.request_equipment = false;
                this.request_3d_equipment = false;
                this.request_energy_saving = false;
                this.request_3d = false;
                return;
            }
            const requests = this.parentProject.requests.split(',').map(r => r.trim());
            this.request_design = requests.includes('意匠');
            this.request_equipment = requests.includes('設備');
            this.request_3d_equipment = requests.includes('3D設備');
            this.request_energy_saving = requests.includes('省エネ');
            this.request_3d = requests.includes('3D');
        },
        parseMaterials() {
            if (!this.parentProject.materials) {
                this.materials_layout = false;
                this.materials_rental = false;
                this.materials_contract = false;
                this.materials_tac = false;
                this.materials_other = false;
                return;
            }
            const materials = this.parentProject.materials.split(',').map(m => m.trim());
            this.materials_layout = materials.includes('配置図');
            this.materials_rental = materials.includes('家賃審査書');
            this.materials_contract = materials.includes('契約図');
            this.materials_tac = materials.includes('TAC図');
            this.materials_other = materials.includes('その他');
        },
        validateParentProjectForm() {
            this.validationErrors = {
                company_name: '',
                project_name: ''
            };
            let valid = true;
            
            if (!this.parentProject.company_name) {
                this.validationErrors.company_name = '会社名は必須です';
                valid = false;
            }
            
            if (!this.parentProject.project_name) {
                this.validationErrors.project_name = '案件名は必須です';
                valid = false;
            }
            
            return valid;
        },
        async saveParentProject() {
            if (!this.validateParentProjectForm()) {
                return;
            }

            // Convert checkbox requests to comma-separated string
            const requestsArray = [];
            if (this.request_design) requestsArray.push('意匠');
            if (this.request_equipment) requestsArray.push('設備');
            if (this.request_3d_equipment) requestsArray.push('3D設備');
            if (this.request_energy_saving) requestsArray.push('省エネ');
            if (this.request_3d) requestsArray.push('3D');
            this.parentProject.requests = requestsArray.join(',');

            // Convert checkbox materials to comma-separated string
            const materialsArray = [];
            if (this.materials_layout) materialsArray.push('配置図');
            if (this.materials_rental) materialsArray.push('家賃審査書');
            if (this.materials_contract) materialsArray.push('契約図');
            if (this.materials_tac) materialsArray.push('TAC図');
            if (this.materials_other) materialsArray.push('その他');
            this.parentProject.materials = materialsArray.join(',');

            try {
                const formData = new FormData();
                formData.append('id', this.parentProject.id);
                formData.append('company_name', this.parentProject.company_name || '');
                formData.append('branch_name', this.parentProject.branch_name || '');
                formData.append('contact_name', this.parentProject.contact_name || '');
                formData.append('customer_id', this.parentProject.customer_id || '');
                formData.append('guis_receiver', this.parentProject.guis_receiver || '');
                formData.append('request_date', this.parentProject.request_date || '');
                formData.append('construction_number', this.parentProject.construction_number || '');
                formData.append('project_number', this.parentProject.project_number || '');
                formData.append('project_name', this.parentProject.project_name || '');
                formData.append('construction_branch', this.parentProject.construction_branch || '');
                formData.append('scale', this.parentProject.scale || '');
                formData.append('type1', this.parentProject.type1 || '');
                formData.append('type2', this.parentProject.type2 || '');
                formData.append('request_type', this.parentProject.request_type || '');
                formData.append('desired_delivery_date', this.parentProject.desired_delivery_date || '');
                formData.append('materials', this.parentProject.materials);
                formData.append('structural_office', this.parentProject.structural_office || '');
                formData.append('notes', this.parentProject.notes || '');
                formData.append('status', this.parentProject.status || 'draft');
                formData.append('requests', this.parentProject.requests || '');
                
                const response = await axios.post('/api/index.php?model=parentproject&method=update', formData);
                if (response.data && response.data.status == 'success') {
                    // Show success message with SweetAlert2
                    Swal.fire({
                        title: '成功',
                        text: '建物情報を更新しました。',
                        icon: 'success',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#3085d6'
                    }).then(() => {
                        this.isEditMode = false;
                        this.removeBeforeUnloadWarning();
                        this.destroySelect2Instances();
                        this.loadParentProject();
                    });
                } else {
                    showParentProjectError(response.data?.error || '更新に失敗しました。', response && response.data);
                }
            } catch (error) {
                console.error('Error saving parent project:', error);
                showParentProjectError('更新に失敗しました。', error);
            }
        },
        getParentProjectStatusButtonClass(status) {
            if (!status) return 'btn-secondary';
            // Handle both string and number status values
            const statusStr = String(status).toLowerCase();
            const s = this.statuses.find(s => s.value === statusStr);
            return `btn-${s?.color || 'secondary'}`;
        },
        async selectStatus(status) {
            try {
                // Update local data first for immediate UI feedback
                this.parentProject.status = status;
                
                // Close dropdown
                const dropdownElement = document.querySelector('#statusDropdown');
                if (dropdownElement) {
                    const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                    if (dropdown) {
                        dropdown.hide();
                    }
                }

                                 // Call API to update status in database
                 const formData = new FormData();
                 formData.append('id', this.parentProject.id);
                 formData.append('status', status);
                 
                 const response = await axios.post('/api/index.php?model=parentproject&method=updateStatus', formData);
                
                if (response.data && response.data.status === 'success') {
                    Swal.fire({
                        title: '成功',
                        text: 'ステータスを更新しました',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                } else {
                    showParentProjectError(response.data?.error || 'ステータスの更新に失敗しました', response && response.data);
                    // Revert local change if API call failed
                    this.parentProject.status = this.originalParentProject.status;
                }
            } catch (error) {
                console.error('Error updating status:', error);
                showParentProjectError('ステータスの更新に失敗しました', error);
                // Revert local change if API call failed
                this.parentProject.status = this.originalParentProject.status;
            }
        },
        async generateProjectNumber() {
            try {
                const response = await axios.get('/api/index.php?model=parentproject&method=generateProjectNumber');
                if (response.data && response.data.status === 'success') {
                   this.parentProject.project_number = response.data.project_number;
                   // showMessage('プロジェクト番号を生成しました', false);
                } else {
                    showMessage('プロジェクト番号の生成に失敗しました', true);
                }
            } catch (error) {
                console.error('Error generating project number:', error);
                showMessage('プロジェクト番号の生成に失敗しました', true);
            }
        },

        async deleteParentProject() {
            try {
                const result = await Swal.fire({
                    title: '確認',
                    text: 'この親プロジェクトを削除しますか？子プロジェクトがある場合は削除できません。',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: '削除',
                    cancelButtonText: 'キャンセル'
                });
                
                if (result.isConfirmed && this.parentProject) {
                    const formData = new FormData();
                    formData.append('id', this.parentProject.id);
                    
                    const response = await axios.post('/api/index.php?model=parentproject&method=delete', formData);
                    
                    if (response.data && response.data.status === 'success') {
                        Swal.fire({
                            title: '成功',
                            text: '親プロジェクトを削除しました。',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            window.location.href = 'index.php';
                        });
                    } else {
                        showParentProjectError(response.data?.error || '削除に失敗しました。', response && response.data);
                    }
                }
            } catch (error) {
                console.error('Error deleting parent project:', error);
                showParentProjectError('削除に失敗しました。', error);
            }
        },

        // Child project modal methods

        getChildProjectCustomerModel(isEdit) {
            return isEdit ? this.editingChildProject : this.newChildProject;
        },
        getParentCustomerSummary() {
            const display = this.customerDisplay || {};
            const parent = this.parentProject || {};
            return {
                company_name: display.company_name || parent.company_name || '-',
                branch_name: display.branch_name || parent.branch_name || '-',
                contact_name: display.contact_name || parent.contact_name || '-'
            };
        },
        shouldShowChildProjectCustomer(project) {
            const parentId = String((this.parentProject && this.parentProject.customer_id) || '').trim();
            const childId = String((project && project.customer_id) || '').trim();
            return childId !== '' && childId !== parentId;
        },
        getChildProjectCustomerDisplay(project) {
            const company = (project && project.company_name) ? String(project.company_name).trim() : '';
            const branch = (project && project.branch_name) ? String(project.branch_name).trim() : '';
            const contact = (project && project.contact_name) ? String(project.contact_name).trim() : '';
            return {
                company_name: company || '-',
                branch_name: branch || '-',
                contact_name: contact || '-'
            };
        },
        formatChildProjectCustomerLabel(project) {
            if (!this.shouldShowChildProjectCustomer(project)) {
                return '-';
            }
            const c = this.getChildProjectCustomerDisplay(project);
            const parts = [c.company_name, c.branch_name, c.contact_name].filter((p) => p && p !== '-');
            return parts.length ? parts.join(' / ') : '-';
        },
        getChildProjectGuisReceiverDisplay(project) {
            if (project && project.guis_receiver_name) {
                return project.guis_receiver_name;
            }
            const parentUserid = (this.parentProject && this.parentProject.guis_receiver)
                ? String(this.parentProject.guis_receiver).trim()
                : '';
            const childUserid = (project && project.guis_receiver)
                ? String(project.guis_receiver).trim()
                : '';
            const effectiveUserid = (project && project.effective_guis_receiver)
                ? String(project.effective_guis_receiver).trim()
                : (childUserid || parentUserid);
            if (!effectiveUserid) {
                return '-';
            }
            if (!childUserid && parentUserid && effectiveUserid === parentUserid && this.guisReceiverDisplayName) {
                return this.guisReceiverDisplayName;
            }
            return effectiveUserid;
        },
        applyParentCustomerToChildModel(model) {
            const display = this.customerDisplay || {};
            const parent = this.parentProject || {};
            model.company_name = display.company_name || parent.company_name || '';
            model.branch_name = display.branch_name || parent.branch_name || '';
            model.contact_name = display.contact_name || parent.contact_name || '';
            model.customer_id = parent.customer_id != null && parent.customer_id !== ''
                ? String(parent.customer_id)
                : '';
        },
        getChildProjectCustomerSelectIds(isEdit) {
            return {
                company: isEdit ? '#edit_child_company_name' : '#create_child_company_name',
                branch: isEdit ? '#edit_child_branch_name' : '#create_child_branch_name',
                contact: isEdit ? '#edit_child_contact_name' : '#create_child_contact_name',
                modal: isEdit ? '#editChildProjectModal' : '#createChildProjectModal'
            };
        },
        destroyChildProjectCustomerSelect2(isEdit) {
            const ids = this.getChildProjectCustomerSelectIds(isEdit);
            [ids.company, ids.branch, ids.contact].forEach((selector) => {
                const $el = $(selector);
                if ($el.length && $el.data('select2')) {
                    $el.select2('destroy');
                }
            });
        },
        onChildProjectUseParentCustomerChange(isEdit) {
            const model = this.getChildProjectCustomerModel(isEdit);
            if (model.use_parent_customer) {
                this.destroyChildProjectCustomerSelect2(isEdit);
                model.company_name = '';
                model.branch_name = '';
                model.contact_name = '';
                model.customer_id = '';
            } else {
                this.applyParentCustomerToChildModel(model);
                this.$nextTick(() => {
                    this.initChildProjectCustomerSelect2(isEdit);
                });
            }
        },
        initChildProjectCustomerSelect2(isEdit) {
            this.destroyChildProjectCustomerSelect2(isEdit);
            this.initChildProjectCompanySelect2(isEdit);
            this.initChildProjectBranchSelect2(isEdit);
            this.initChildProjectContactSelect2(isEdit);
        },
        initChildProjectCompanySelect2(isEdit) {
            const model = this.getChildProjectCustomerModel(isEdit);
            const ids = this.getChildProjectCustomerSelectIds(isEdit);
            const $company = $(ids.company);
            if (!$company.length) return;
            $company.select2({
                placeholder: '選択してください',
                dropdownParent: $(ids.modal),
                allowClear: true,
                minimumResultsForSearch: 0,
                ajax: {
                    url: '/api/index.php?model=customer&method=list_companies',
                    dataType: 'json',
                    delay: 250,
                    data(params) {
                        return { search: params.term, page: params.page || 1 };
                    },
                    processResults(data) {
                        const list = (data.data || []).slice().sort((a, b) => {
                            const nameA = (a.company_name || '').toString();
                            const nameB = (b.company_name || '').toString();
                            const hasA = nameA.includes('大東');
                            const hasB = nameB.includes('大東');
                            if (hasA && !hasB) return -1;
                            if (!hasA && hasB) return 1;
                            return nameA.localeCompare(nameB);
                        });
                        return {
                            results: list.map((item) => ({
                                id: item.company_name,
                                text: item.company_name
                            }))
                        };
                    }
                }
            }).on('select2:select', (e) => {
                model.company_name = e.params.data.id;
                model.branch_name = '';
                model.contact_name = '';
                model.customer_id = '';
                this.onChildProjectCompanyChange(isEdit);
            }).on('select2:clear', () => {
                model.company_name = '';
                this.onChildProjectCompanyChange(isEdit);
            });
            if (model.company_name) {
                const option = new Option(model.company_name, model.company_name, true, true);
                $company.append(option).trigger('change');
            }
        },
        initChildProjectBranchSelect2(isEdit) {
            const model = this.getChildProjectCustomerModel(isEdit);
            const ids = this.getChildProjectCustomerSelectIds(isEdit);
            const $branch = $(ids.branch);
            if (!$branch.length) return;
            if ($branch.data('select2')) {
                $branch.select2('destroy');
            }
            $branch.select2({
                placeholder: '選択してください',
                dropdownParent: $(ids.modal),
                allowClear: true,
                minimumResultsForSearch: 0,
                ajax: {
                    url: '/api/index.php?model=customer&method=list_branches_by_company',
                    dataType: 'json',
                    delay: 250,
                    data: (params) => ({
                        search: params.term,
                        page: params.page || 1,
                        company_name: model.company_name
                    }),
                    processResults(data) {
                        return {
                            results: (data.data || []).map((item) => ({
                                id: item.branch,
                                text: item.branch
                            }))
                        };
                    }
                }
            }).on('select2:select', (e) => {
                model.branch_name = e.params.data.id;
                model.contact_name = '';
                model.customer_id = '';
                this.onChildProjectBranchChange(isEdit);
            }).on('select2:clear', () => {
                model.branch_name = '';
                this.onChildProjectBranchChange(isEdit);
            });
            if (model.branch_name) {
                const option = new Option(model.branch_name, model.branch_name, true, true);
                $branch.append(option).trigger('change');
            }
        },
        initChildProjectContactSelect2(isEdit) {
            const model = this.getChildProjectCustomerModel(isEdit);
            const ids = this.getChildProjectCustomerSelectIds(isEdit);
            const $contact = $(ids.contact);
            if (!$contact.length) return;
            if ($contact.data('select2')) {
                $contact.select2('destroy');
            }
            $contact.select2({
                placeholder: '選択してください',
                dropdownParent: $(ids.modal),
                allowClear: true,
                minimumResultsForSearch: 0,
                ajax: {
                    url: '/api/index.php?model=customer&method=list_contacts_by_company_branch',
                    dataType: 'json',
                    delay: 250,
                    data: (params) => ({
                        search: params.term,
                        page: params.page || 1,
                        company_name: model.company_name,
                        branch_name: model.branch_name
                    }),
                    processResults(data) {
                        return {
                            results: (data.data || []).map((item) => ({
                                id: item.id,
                                text: item.name
                            }))
                        };
                    }
                }
            }).on('select2:select', (e) => {
                model.contact_name = e.params.data.text;
                model.customer_id = String(e.params.data.id);
            }).on('select2:clear', () => {
                model.contact_name = '';
                model.customer_id = '';
            });
            if (model.contact_name) {
                const optionValue = model.customer_id || model.contact_name;
                const option = new Option(model.contact_name, optionValue, true, true);
                $contact.append(option).trigger('change');
            }
        },
        onChildProjectCompanyChange(isEdit) {
            const model = this.getChildProjectCustomerModel(isEdit);
            model.branch_name = '';
            model.contact_name = '';
            model.customer_id = '';
            const ids = this.getChildProjectCustomerSelectIds(isEdit);
            const $branch = $(ids.branch);
            const $contact = $(ids.contact);
            if ($branch.length && $branch.data('select2')) {
                $branch.val(null).trigger('change');
                $branch.select2('destroy');
            }
            if ($contact.length && $contact.data('select2')) {
                $contact.val(null).trigger('change');
                $contact.select2('destroy');
            }
            this.initChildProjectBranchSelect2(isEdit);
            this.initChildProjectContactSelect2(isEdit);
        },
        onChildProjectBranchChange(isEdit) {
            const model = this.getChildProjectCustomerModel(isEdit);
            model.contact_name = '';
            model.customer_id = '';
            const ids = this.getChildProjectCustomerSelectIds(isEdit);
            const $contact = $(ids.contact);
            if ($contact.length && $contact.data('select2')) {
                $contact.val(null).trigger('change');
                $contact.select2('destroy');
            }
            this.initChildProjectContactSelect2(isEdit);
        },
        resolveChildProjectCustomerId(model) {
            if (model.use_parent_customer) {
                return (this.parentProject && this.parentProject.customer_id) ? this.parentProject.customer_id : '';
            }
            return model.customer_id || '';
        },
        validateChildProjectCustomer(model, errors) {
            if (model.use_parent_customer) {
                return true;
            }
            let isValid = true;
            if (!model.company_name) {
                errors.company_name = '会社名は必須です';
                isValid = false;
            }
            if (!model.branch_name) {
                errors.branch_name = '支店名は必須です';
                isValid = false;
            }
            if (!model.customer_id) {
                errors.contact_name = '担当様は必須です';
                isValid = false;
            }
            return isValid;
        },
        async openChildProjectNewCustomerModal(isEdit) {
            this.childCustomerModalContext = isEdit ? 'edit' : 'create';
            const model = this.getChildProjectCustomerModel(isEdit);
            const presetCompany = model.company_name;
            const presetBranch = model.branch_name;
            await this.openNewCustomerModal();
            if (presetCompany) {
                this.newCustomer.company_name = presetCompany;
            }
            if (presetBranch) {
                this.newCustomer.branch = presetBranch;
            }
        },
        async applyNewCustomerToChildProject(companyName, branchName, contactName) {
            const isEdit = this.childCustomerModalContext === 'edit';
            const model = this.getChildProjectCustomerModel(isEdit);
            this.childCustomerModalContext = null;
            model.use_parent_customer = false;
            model.company_name = companyName;
            model.branch_name = branchName;
            model.contact_name = contactName;
            model.customer_id = '';
            try {
                const response = await axios.get(
                    `/api/index.php?model=customer&method=list_contacts_by_company_branch&company_name=${encodeURIComponent(companyName)}&branch_name=${encodeURIComponent(branchName)}`
                );
                if (response.data && response.data.data) {
                    const found = response.data.data.find((c) => c.name === contactName);
                    if (found) {
                        model.customer_id = String(found.id);
                    }
                }
            } catch (error) {
                console.error('Error resolving child project customer id:', error);
            }
            this.destroyChildProjectCustomerSelect2(isEdit);
            this.$nextTick(() => {
                this.initChildProjectCustomerSelect2(isEdit);
            });
        },
        getParentGuisReceiverDisplayName() {
            if (this.guisReceiverDisplayName) {
                return this.guisReceiverDisplayName;
            }
            const gr = (this.parentProject && this.parentProject.guis_receiver) || '';
            return gr || '-';
        },
        getChildProjectGuisReceiverModel(isEdit) {
            return isEdit ? this.editingChildProject : this.newChildProject;
        },
        destroyChildProjectGuisReceiverSelect2(isEdit) {
            const selector = isEdit ? '#edit_child_guis_receiver' : '#create_child_guis_receiver';
            const $el = $(selector);
            if ($el.length && $el.data('select2')) {
                $el.select2('destroy');
            }
        },
        onChildProjectUseParentGuisReceiverChange(isEdit) {
            const model = this.getChildProjectGuisReceiverModel(isEdit);
            if (model.use_parent_guis_receiver) {
                this.destroyChildProjectGuisReceiverSelect2(isEdit);
                model.guis_receiver = '';
            } else {
                this.applyParentGuisReceiverToChildModel(model);
                this.$nextTick(() => {
                    this.initChildProjectGuisReceiverSelect2(isEdit);
                });
            }
        },
        applyParentGuisReceiverToChildModel(model) {
            model.guis_receiver = (this.parentProject && this.parentProject.guis_receiver)
                ? String(this.parentProject.guis_receiver)
                : '';
        },
        async setChildGuisReceiverSelectValue(isEdit, userid) {
            const selector = isEdit ? '#edit_child_guis_receiver' : '#create_child_guis_receiver';
            const $guisReceiver = $(selector);
            if (!$guisReceiver.length || !userid) return;
            try {
                const response = await axios.get('/api/index.php?model=user&method=searchMembers');
                if (response.data && response.data.data) {
                    const user = response.data.data.find((u) => u.userid === userid);
                    if (user && $guisReceiver.data('select2')) {
                        $guisReceiver.empty();
                        const option = new Option(user.realname, userid, true, true);
                        $guisReceiver.append(option).trigger('change');
                    }
                }
            } catch (error) {
                console.error('Error loading child project GUIS receiver display name:', error);
            }
        },
        initChildProjectGuisReceiverSelect2(isEdit) {
            this.destroyChildProjectGuisReceiverSelect2(isEdit);
            const model = this.getChildProjectGuisReceiverModel(isEdit);
            const selector = isEdit ? '#edit_child_guis_receiver' : '#create_child_guis_receiver';
            const modal = isEdit ? '#editChildProjectModal' : '#createChildProjectModal';
            const $guisReceiver = $(selector);
            if (!$guisReceiver.length) return;
            $guisReceiver.select2({
                placeholder: '選択してください',
                dropdownParent: $(modal),
                allowClear: true,
                minimumResultsForSearch: 0,
                ajax: {
                    url: '/api/index.php?model=user&method=searchMembers',
                    dataType: 'json',
                    delay: 250,
                    data(params) {
                        return { search: params.term, page: params.page || 1 };
                    },
                    processResults(data) {
                        return {
                            results: (data.data || []).map((item) => ({
                                id: item.userid,
                                text: item.realname
                            }))
                        };
                    }
                }
            }).on('select2:select', (e) => {
                model.guis_receiver = e.params.data.id;
            }).on('select2:clear', () => {
                model.guis_receiver = '';
            });
            if (model.guis_receiver) {
                this.setChildGuisReceiverSelectValue(isEdit, model.guis_receiver);
            }
        },
        resolveChildProjectGuisReceiver(model) {
            if (model.use_parent_guis_receiver) {
                return '';
            }
            return model.guis_receiver || '';
        },
        validateChildProjectGuisReceiver(model, errors) {
            if (model.use_parent_guis_receiver) {
                return true;
            }
            if (!model.guis_receiver || String(model.guis_receiver).trim() === '') {
                errors.guis_receiver = 'GUIS受付者は必須です';
                return false;
            }
            return true;
        },

        async showCreateChildProjectModal() {
            // Clear all Tagify and input values before opening modal
            this.destroyChildProjectTagify();
            const clearInput = (id) => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            };
            clearInput('child_project_order_type');
            clearInput('create_child_project_manager_tags');
            clearInput('create_child_project_team_tags');
            clearInput('create_child_project_members_tags');

            this.resetChildProjectForm();
            // Set default name from parent project's project_name (お施主様名)
            if (this.parentProject && this.parentProject.project_name) {
                this.newChildProject.name = this.parentProject.project_name;
            }
            this.newChildProject.end_date = '';
            this.loadDepartments();
            
            this.generateChildProjectNumber();
            
            // Reuse existing modal instance or create new one
            const modalEl = document.getElementById('createChildProjectModal');
            let modal = bootstrap.Modal.getInstance(modalEl);
            if (!modal) {
                modal = new bootstrap.Modal(modalEl);
            }
            modal.show();
            
            this.$nextTick(() => {
                this.initializeChildProjectDatePickers();
                this.initChildProjectYoteiMonthPickers(false);
                this.initializeChildProjectTagify();
                if (this.newChildProject.department_id) {
                    this.loadCreateChildProjectCustomFields(this.newChildProject.department_id);
                }
                setTimeout(() => {
                    this.initializeCreateChildProjectQuill();
                }, 100);
                if (typeof window.applyDataI18n === 'function') {
                    const createModal = document.getElementById('createChildProjectModal');
                    if (createModal) window.applyDataI18n(createModal);
                }
            });
        },

        onCreateChildProjectDepartmentChange() {
            this.loadCreateChildProjectCustomFields(this.newChildProject.department_id);
        },

        onEditChildProjectDepartmentChange() {
            this.loadEditChildProjectCustomFields(this.editingChildProject.department_id, []);
        },

        resetChildProjectForm() {
            this.newChildProject = {
                name: '',
                department_id: '',
                project_number: '',
                description: '',
                start_date: '',
                end_date: '',
                project_order_type: '',
                parent_project_id: PARENT_PROJECT_ID,
                is_kadai: true,
                status: '',
                amount: 0,
                progress: 0,
                teams: '',
                managers: [],
                members: [],
                tantou: '',
                caily_nouki: '',
                guis_nouki: '',
                yotei: this.emptyYoteiModel(),
                use_parent_customer: true,
                company_name: '',
                branch_name: '',
                contact_name: '',
                customer_id: '',
                use_parent_guis_receiver: true,
                guis_receiver: ''
            };

            // Clear Quill content
            this.createChildProjectQuillContent = '';
            if (this.createChildProjectQuillInstance) {
                this.createChildProjectQuillInstance.setText('');
            }
            
            this.childProjectValidationErrors = {
                name: '',
                department_id: '',
                project_number: '',
                start_date: '',
                end_date: '',
                status: '',
                project_order_type: '',
                tantou: '',
                caily_nouki: '',
                guis_nouki: '',
                company_name: '',
                branch_name: '',
                contact_name: '',
                guis_receiver: ''
            };

            // Destroy existing flatpickr instances if they exist
            const startPicker = document.getElementById('start_date_picker');
            const endPicker = document.getElementById('end_date_picker');
            const cailyNoukiPicker = document.getElementById('create_caily_nouki_picker');
            const guisNoukiPicker = document.getElementById('create_guis_nouki_picker');
            
            if (startPicker && startPicker._flatpickr) {
                startPicker._flatpickr.destroy();
            }
            if (endPicker && endPicker._flatpickr) {
                endPicker._flatpickr.destroy();
            }
            if (cailyNoukiPicker && cailyNoukiPicker._flatpickr) {
                cailyNoukiPicker._flatpickr.destroy();
            }
            if (guisNoukiPicker && guisNoukiPicker._flatpickr) {
                guisNoukiPicker._flatpickr.destroy();
            }
            
            // Destroy Tagify instance
            this.destroyChildProjectTagify();

            const createCfWrap = document.getElementById('createChildProjectCustomFieldsWrap');
            if (createCfWrap) createCfWrap.innerHTML = '';
        },

        async loadDepartments() {
            try {
                // All project-capable departments (not limited to the current user's departments)
                const response = await axios.get('/api/index.php?model=department&method=list_department');
                const list = Array.isArray(response.data)
                    ? response.data
                    : ((response.data && response.data.data) || []);
                this.departments = list.filter(function (d) {
                    return d && (d.can_project == 1 || d.can_project === '1') && (d.is_active == 1 || d.is_active === '1' || d.is_active === undefined);
                });
                // Generate project number after departments are loaded
                this.generateChildProjectNumber();
            } catch (error) {
                console.error('Error loading departments:', error);
                this.departments = [];
            }
        },
        
        async loadDepartmentUsers(departmentId, isEdit = false) {
            if (!departmentId) {
                this.departmentUsers = [];
                // Clear manager Tagify when no department is selected
                if (isEdit && this.editChildProjectManagerTagify) {
                    this.editChildProjectManagerTagify.settings.whitelist = [];
                    this.editChildProjectManagerTagify.whitelist = [];
                    this.editChildProjectManagerTagify.removeAllTags();
                } else if (!isEdit && this.createChildProjectManagerTagify) {
                    this.createChildProjectManagerTagify.settings.whitelist = [];
                    this.createChildProjectManagerTagify.whitelist = [];
                    this.createChildProjectManagerTagify.removeAllTags();
                }
                return;
            }
            
            try {
                const response = await axios.get(`/api/index.php?model=department&method=get_users&department_id=${departmentId}`);
                if (response.data) {
                    this.departmentUsers = Array.isArray(response.data) ? response.data : [];
                    // Update Tagify whitelist - map users correctly
                    const users = this.departmentUsers.map(u => ({
                        id: String(u.id || u.user_id),
                        value: u.user_name || u.realname || u.name,
                        name: u.user_name || u.realname || u.name
                    }));
                    
                    console.log('Loaded department users:', users); // Debug log
                    
                    if (isEdit && this.editChildProjectManagerTagify) {
                        // Update whitelist properly - need to update both settings and whitelist
                        this.editChildProjectManagerTagify.settings.whitelist = users;
                        this.editChildProjectManagerTagify.whitelist = users;
                        // Trigger dropdown refresh
                        this.editChildProjectManagerTagify.dropdown.hide();
                    } else if (!isEdit && this.createChildProjectManagerTagify) {
                        // Update whitelist properly - need to update both settings and whitelist
                        this.createChildProjectManagerTagify.settings.whitelist = users;
                        this.createChildProjectManagerTagify.whitelist = users;
                        // Trigger dropdown refresh
                        this.createChildProjectManagerTagify.dropdown.hide();
                    }
                } else {
                    this.departmentUsers = [];
                }
            } catch (error) {
                console.error('Error loading department users:', error);
                this.departmentUsers = [];
            }
        },
        
        getDefaultChildProjectStartDate() {
            const today = moment.tz
                ? moment.tz(moment(), getProjectDisplayTimezone())
                : moment();
            const startHour = String(getStartDateDefaultHour()).padStart(2, '0');
            return today.format('YYYY/M/D') + ' ' + startHour + ':00';
        },
        initChildProjectDatePicker(elId, key, extra, isEdit = false) {
            const el = document.getElementById(elId);
            if (!el) return;
            const project = isEdit ? this.editingChildProject : this.newChildProject;
            const hasServerValue = this._serverChildProjectDates && this._serverChildProjectDates[key] != null;
            const serverValue = hasServerValue
                ? this._serverChildProjectDates[key]
                : project[key];
            const useDisplayDefault = !isEdit && !hasServerValue && key === 'start_date' && !serverValue;
            const displayDefault = useDisplayDefault ? this.getDefaultChildProjectStartDate() : '';
            const inputVal = useDisplayDefault
                ? displayDefault
                : toProjectDateTimeInputValue(serverValue);
            if (el._flatpickr) {
                const fpVal = String(
                    (el._flatpickr._input && el._flatpickr._input.value) || el.value || ''
                ).trim();
                const displayVal = String(project[key] || '').trim();
                if (fpVal && (fpVal === inputVal || fpVal === displayVal)) {
                    return;
                }
            }
            initChildProjectFlatpickr(el, {
                defaultHour: extra.defaultHour,
                defaultMinute: extra.defaultMinute,
                onChange: (selectedDates, dateStr) => {
                    project[key] = dateStr || '';
                }
            }, useDisplayDefault ? displayDefault : serverValue, {
                alreadyDisplay: useDisplayDefault
            });
            if (inputVal && project[key] !== inputVal) {
                project[key] = inputVal;
            }
            if (this._serverChildProjectDates) {
                delete this._serverChildProjectDates[key];
            }
        },
        initializeChildProjectDatePickers() {
            this.initChildProjectDatePicker('start_date_picker', 'start_date', { defaultHour: getStartDateDefaultHour(), defaultMinute: 0 });
            this.initChildProjectDatePicker('end_date_picker', 'end_date', { defaultHour: getDeadlineDefaultHour(), defaultMinute: 0 });
            this.initChildProjectDatePicker('create_caily_nouki_picker', 'caily_nouki', { defaultHour: getDeadlineDefaultHour(), defaultMinute: 0 });
            this.initChildProjectDatePicker('create_guis_nouki_picker', 'guis_nouki', { defaultHour: getDeadlineDefaultHour(), defaultMinute: 0 });
        },
        
        async initializeChildProjectTagify() {
            // Initialize Tagify for child project order type
            const orderTypeInput = document.querySelector('#child_project_order_type');
            if (orderTypeInput) {
                // Destroy existing instance if it exists
                if (orderTypeInput.tagify) {
                    orderTypeInput.tagify.destroy();
                }
                
                // Clear any existing content
                orderTypeInput.value = '';
                
                this.childProjectOrderTypeTagify = new Tagify(orderTypeInput, {
                    whitelist: ['新規', '修正', '新規修正', '変更', '免震', '耐震', '計画変更', '契約図', '実施図'],
                    maxTags: 5,
                    dropdown: {
                        maxItems: 20,
                        classname: "tags-look-project-order-type",
                        enabled: 0,
                        closeOnSelect: true
                    }
                });
                
                // Update the model when tags change
                const updateOrderType = () => {
                    this.newChildProject.project_order_type = this.childProjectOrderTypeTagify.value.map(tag => tag.value).join(',');
                };
                this.childProjectOrderTypeTagify.on('add', updateOrderType);
                this.childProjectOrderTypeTagify.on('remove', updateOrderType);
            }
            
            // Initialize Tagify for manager, team, members
            await this.initializeCreateChildProjectManagerTagify();
            await this.initializeCreateChildProjectTeamTagify();
            await this.initializeCreateChildProjectMembersTagify();
        },
        
        async initializeCreateChildProjectTeamTagify() {
            const teamInput = document.getElementById('create_child_project_team_tags');
            if (!teamInput || !window.Tagify) return;
            if (teamInput._tagify) {
                teamInput._tagify.destroy();
            }
            teamInput.value = '';
            let departmentTeams = [];
            const deptId = this.newChildProject.department_id;
            if (deptId) {
                try {
                    const res = await axios.get('/api/index.php?model=team&method=listbydepartment&department_id=' + encodeURIComponent(deptId));
                    const raw = res.data;
                    departmentTeams = Array.isArray(raw) ? raw : (raw && Array.isArray(raw.data) ? raw.data : []);
                } catch (e) {
                    departmentTeams = [];
                }
            }
            const teamWhitelist = departmentTeams.map(t => ({ id: String(t.id), value: t.name || String(t.id) }));
            this.createChildProjectTeamTagify = new Tagify(teamInput, {
                whitelist: teamWhitelist,
                enforceWhitelist: false,
                dropdown: {
                    maxItems: 1000,
                    enabled: 0,
                    closeOnSelect: true,
                    searchKeys: ['value'],
                    classname: 'tagify__dropdown'
                }
            });
            this.createChildProjectTeamTagify.settings.whitelist = teamWhitelist;
            this.createChildProjectTeamTagify.whitelist = teamWhitelist;
            this.createChildProjectTeamTagify.on('focus', () => {
                if (this.createChildProjectTeamTagify && this.createChildProjectTeamTagify.whitelist && this.createChildProjectTeamTagify.whitelist.length > 0) {
                    this.createChildProjectTeamTagify.dropdown.show();
                }
            });
            this.createChildProjectTeamTagify.on('change', () => {
                this.newChildProject.teams = this.createChildProjectTeamTagify.value.map(t => t.id).join(',');
            });
            this.createChildProjectTeamTagify.on('add', async (e) => {
                const addedTeamId = e.detail.data && e.detail.data.id;
                if (!addedTeamId) return;
                try {
                    const res = await axios.get(`/api/index.php?model=team&method=get&id=${addedTeamId}`);
                    if (res.data && Array.isArray(res.data.members)) {
                        if (this.createChildProjectMembersTagify) {
                            const teamMembers = res.data.members.map(m => ({ id: m.user_id, value: m.user_name || '' }));
                            const currentIds = this.createChildProjectMembersTagify.value.map(tag => String(tag.id));
                            const toAdd = teamMembers.filter(m => !currentIds.includes(String(m.id)));
                            this.createChildProjectMembersTagify.addTags(toAdd);
                        }
                        const leaders = res.data.members.filter(m => m.leader == 1 || m.leader === '1');
                        if (leaders.length && this.createChildProjectManagerTagify) {
                            const leaderTags = leaders.map(m => ({ id: m.user_id, value: m.user_name || '' }));
                            const managerCurrentIds = this.createChildProjectManagerTagify.value.map(tag => String(tag.id));
                            const leadersToAdd = leaderTags.filter(m => !managerCurrentIds.includes(String(m.id)));
                            this.createChildProjectManagerTagify.addTags(leadersToAdd);
                        }
                    }
                } catch (err) {}
            });
            this.createChildProjectTeamTagify.on('remove', async (e) => {
                const removedTeamId = e.detail.data && e.detail.data.id;
                if (!removedTeamId || !this.createChildProjectMembersTagify) return;
                try {
                    const res = await axios.get(`/api/index.php?model=team&method=get&id=${removedTeamId}`);
                    if (res.data && Array.isArray(res.data.members)) {
                        const teamMemberIds = res.data.members.map(m => String(m.user_id));
                        const remain = this.createChildProjectMembersTagify.value.filter(tag => !teamMemberIds.includes(String(tag.id)));
                        this.createChildProjectMembersTagify.removeAllTags();
                        this.createChildProjectMembersTagify.addTags(remain);
                    }
                } catch (err) {}
            });
        },

        async initializeCreateChildProjectMembersTagify() {
            const membersInput = document.getElementById('create_child_project_members_tags');
            if (!membersInput || !window.Tagify) return;
            if (membersInput._tagify) {
                membersInput._tagify.destroy();
            }
            membersInput.value = '';
            const users = (this.departmentUsers || []).map(u => ({
                id: u.id || u.user_id,
                value: u.user_name || u.realname || u.name,
                name: u.user_name || u.realname || u.name
            }));
            this.createChildProjectMembersTagify = new Tagify(membersInput, {
                whitelist: users,
                enforceWhitelist: false,
                dropdown: { maxItems: 1000, enabled: 0, closeOnSelect: true }
            });
            this.createChildProjectMembersTagify.on('change', () => {
                this.newChildProject.members = this.createChildProjectMembersTagify.value.map(t => t.id);
            });
        },
        
        async initializeCreateChildProjectManagerTagify() {
            const managerInput = document.getElementById('create_child_project_manager_tags');
            if (managerInput && window.Tagify) {
                // Destroy existing instance if it exists
                if (managerInput._tagify) {
                    managerInput._tagify.destroy();
                }
                
                // Load department users if department is selected
                if (this.newChildProject.department_id) {
                    await this.loadDepartmentUsers(this.newChildProject.department_id, false);
                }
                
                const users = (this.departmentUsers || []).map(u => ({
                    id: u.id || u.user_id,
                    value: u.user_name || u.realname || u.name,
                    name: u.user_name || u.realname || u.name
                }));
                
                console.log('Initializing manager Tagify with users:', users); // Debug log
                
                this.createChildProjectManagerTagify = new Tagify(managerInput, {
                    whitelist: users,
                    maxTags: 10,
                    enforceWhitelist: false,
                    dropdown: {
                        maxItems: 20,
                        enabled: 0, // Auto-show on input
                        closeOnSelect: true,
                        classname: 'tagify__dropdown',
                        searchKeys: ['value', 'name']
                    }
                });
                
                // Update the model when tags change
                this.createChildProjectManagerTagify.on('change', (e) => {
                    const selected = this.createChildProjectManagerTagify.value.map(t => t.id);
                    this.newChildProject.managers = selected;
                });
                
                // Set initial value if exists
                if (this.newChildProject.managers && this.newChildProject.managers.length > 0) {
                    const managerTags = this.newChildProject.managers.map(id => {
                        const user = this.departmentUsers.find(u => (u.id || u.user_id) == id);
                        return user ? { id: user.id || user.user_id, value: user.user_name || user.realname || user.name } : null;
                    }).filter(t => t !== null);
                    if (managerTags.length > 0) {
                        this.createChildProjectManagerTagify.addTags(managerTags);
                    }
                }
            }
        },
        
        clearChildProjectTagifyTags(fieldName) {
            if (fieldName === 'project_order_type' && this.childProjectOrderTypeTagify) {
                this.childProjectOrderTypeTagify.removeAllTags();
                this.newChildProject.project_order_type = '';
            }
        },
        
        destroyChildProjectTagify() {
            if (this.childProjectOrderTypeTagify) {
                this.childProjectOrderTypeTagify.destroy();
                this.childProjectOrderTypeTagify = null;
            }
            if (this.createChildProjectManagerTagify) {
                this.createChildProjectManagerTagify.destroy();
                this.createChildProjectManagerTagify = null;
            }
            if (this.createChildProjectTeamTagify) {
                this.createChildProjectTeamTagify.destroy();
                this.createChildProjectTeamTagify = null;
            }
            if (this.createChildProjectMembersTagify) {
                this.createChildProjectMembersTagify.destroy();
                this.createChildProjectMembersTagify = null;
            }
            this.destroyChildProjectCustomerSelect2(false);
            this.destroyChildProjectGuisReceiverSelect2(false);
        },

        /** Load and render custom fields for create child project by department (like project-list quick edit). */
        async loadCreateChildProjectCustomFields(departmentId) {
            const wrap = document.getElementById('createChildProjectCustomFieldsWrap');
            if (!wrap) return;
            wrap.innerHTML = '';
            if (!departmentId) return;
            try {
                const cfRes = await axios.get('/api/index.php?model=department&method=getCustomFields');
                const sets = cfRes.data || [];
                const mergedFields = [];
                sets.filter(s => s && s.department_id != null && String(s.department_id) === String(departmentId)).forEach(s => {
                    if (s.fields && Array.isArray(s.fields)) {
                        s.fields.forEach(f => {
                            if (f && f.label && !mergedFields.some(ex => ex.label && String(ex.label).trim() === String((f.label || '').trim()))) {
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
                this._renderChildProjectCustomFields(wrap, mergedFields, [], 'createChildProject');
                this._initChildProjectCustomFieldsFlatpickr(wrap, 'createChildProjectCustomDatetime');
            } catch (err) {
                console.error('Error loading create child project custom fields:', err);
            }
        },

        /** Load and render custom fields for edit child project by department; savedCustom = array of {label, value}. */
        async loadEditChildProjectCustomFields(departmentId, savedCustom) {
            const wrap = document.getElementById('editChildProjectCustomFieldsWrap');
            if (!wrap) return;
            wrap.innerHTML = '';
            if (!departmentId) return;
            const savedValueMap = {};
            try {
                const raw = typeof savedCustom === 'string' ? (savedCustom.indexOf('&quot;') !== -1 ? savedCustom.replace(/&quot;/g, '"') : savedCustom) : savedCustom;
                const arr = typeof raw === 'string' ? (JSON.parse(raw || '[]') || []) : (Array.isArray(raw) ? raw : []);
                arr.forEach(f => { if (f && f.label) savedValueMap[String(f.label).trim()] = f.value || ''; });
            } catch (e) { /* ignore */ }
            try {
                const cfRes = await axios.get('/api/index.php?model=department&method=getCustomFields');
                const sets = cfRes.data || [];
                const mergedFields = [];
                sets.filter(s => s && s.department_id != null && String(s.department_id) === String(departmentId)).forEach(s => {
                    if (s.fields && Array.isArray(s.fields)) {
                        s.fields.forEach(f => {
                            if (f && f.label && !mergedFields.some(ex => ex.label && String(ex.label).trim() === String((f.label || '').trim()))) {
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
                this._renderChildProjectCustomFields(wrap, mergedFields, savedValueMap, 'editChildProject');
                this._initChildProjectCustomFieldsFlatpickr(wrap, 'editChildProjectCustomDatetime');
            } catch (err) {
                console.error('Error loading edit child project custom fields:', err);
            }
        },

        _renderChildProjectCustomFields(wrap, mergedFields, savedValueMap, prefix) {
            const fieldClass = prefix + 'CustomField';
            const inputClass = prefix + 'CustomInput';
            const checkboxClass = prefix + 'CustomCheckbox';
            const radioClass = prefix + 'CustomRadio';
            const datetimeClass = prefix + 'CustomDatetime';
            const datetimePlaceholder = getProjectDateTimePlaceholder().replace(/"/g, '&quot;');
            mergedFields.forEach((f, idx) => {
                const label = f.label;
                const type = f.type;
                const options = (f.options != null ? String(f.options) : '').trim();
                const opts = options ? options.split(',').map(s => s.trim()).filter(Boolean) : [];
                const val = savedValueMap[String(label).trim()] || '';
                const safeLabel = String(label).replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                const isOneRow = (f.one_row === 1 || f.one_row === '1' || f.one_row === true);
                const colClass = type === 'textarea' || isOneRow ? 'col-12' : 'col-md-6';
                const div = document.createElement('div');
                div.className = colClass + ' mb-3 ' + fieldClass;
                div.setAttribute('data-custom-label', safeLabel);
                div.setAttribute('data-custom-type', type);
                let inner = '<label class="form-label">' + safeLabel + '</label>';
                if (type === 'textarea') {
                    inner += '<textarea class="form-control ' + inputClass + '" data-custom-label="' + safeLabel + '" rows="3">' + (val ? String(val).replace(/</g, '&lt;').replace(/>/g, '&gt;') : '') + '</textarea>';
                } else if (type === 'select') {
                    inner += '<select class="form-select ' + inputClass + '" data-custom-label="' + safeLabel + '"><option value="">選択してください</option>';
                    opts.forEach(opt => { inner += '<option value="' + String(opt).replace(/"/g, '&quot;') + '"' + (val === opt ? ' selected' : '') + '>' + String(opt).replace(/</g, '&lt;') + '</option>'; });
                    inner += '</select>';
                } else if (type === 'radio') {
                    opts.forEach(opt => {
                        inner += '<div class="form-check"><input class="form-check-input ' + radioClass + '" type="radio" name="' + radioClass + '_' + idx + '" data-custom-label="' + safeLabel + '" value="' + String(opt).replace(/"/g, '&quot;') + '"' + (val === opt ? ' checked' : '') + '><label class="form-check-label">' + String(opt).replace(/</g, '&lt;') + '</label></div>';
                    });
                } else if (type === 'checkbox') {
                    const arr = val ? String(val).split(',').map(s => s.trim()).filter(Boolean) : [];
                    opts.forEach(opt => {
                        const checked = arr.indexOf(opt) !== -1;
                        inner += '<div class="form-check"><input class="form-check-input ' + checkboxClass + '" type="checkbox" data-custom-label="' + safeLabel + '" value="' + String(opt).replace(/"/g, '&quot;') + '"' + (checked ? ' checked' : '') + '><label class="form-check-label">' + String(opt).replace(/</g, '&lt;') + '</label></div>';
                    });
                } else if (type === 'datetime') {
                    const datetimeVal = val ? toProjectDateTimeInputValue(val) : '';
                    inner += '<input type="text" class="form-control ' + inputClass + ' ' + datetimeClass + '" data-custom-label="' + safeLabel + '" data-server-datetime="' + (val ? String(val).replace(/"/g, '&quot;') : '') + '" value="' + (datetimeVal ? String(datetimeVal).replace(/"/g, '&quot;') : '') + '" placeholder="' + datetimePlaceholder + '" autocomplete="off">';
                } else {
                    inner += '<input type="text" class="form-control ' + inputClass + '" data-custom-label="' + safeLabel + '" value="' + (val ? String(val).replace(/"/g, '&quot;') : '') + '">';
                }
                div.innerHTML = inner;
                wrap.appendChild(div);
            });
        },

        _initChildProjectCustomFieldsFlatpickr(wrapEl, datetimeClass) {
            if (typeof wrapEl.querySelectorAll !== 'function') return;
            const inputs = wrapEl.querySelectorAll('.' + datetimeClass);
            if (typeof flatpickr !== 'undefined' && inputs.length) {
                inputs.forEach(el => {
                    const serverValue = el.getAttribute('data-server-datetime') || el.value || '';
                    initChildProjectFlatpickr(el, {
                        defaultHour: getCustomFieldDefaultHour(),
                        defaultMinute: 0
                    }, serverValue);
                });
            }
        },

        /** Collect custom field values from a wrap (create or edit). wrapId and rowClass identify the container and row class. */
        collectChildProjectCustomFields(wrapId, rowClass, inputClass, checkboxClass, radioClass) {
            const wrap = document.getElementById(wrapId);
            if (!wrap) return [];
            const rows = wrap.querySelectorAll('.' + rowClass);
            const result = [];
            rows.forEach(row => {
                const label = row.getAttribute('data-custom-label');
                const type = row.getAttribute('data-custom-type');
                if (!label) return;
                let value = '';
                if (type === 'checkbox') {
                    const checked = row.querySelectorAll('.' + checkboxClass + ':checked');
                    value = Array.from(checked).map(el => el.value).join(',');
                } else if (type === 'radio') {
                    const checkedEl = row.querySelector('.' + radioClass + ':checked');
                    value = checkedEl ? checkedEl.value : '';
                } else {
                    const input = row.querySelector('.' + inputClass);
                    value = input ? (input.value || '').trim() : '';
                    if (type === 'datetime' && value) {
                        value = fromProjectDateTimeInputValue(value);
                    }
                }
                result.push({ label: label.replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>'), value });
            });
            return result;
        },

        clearChildProjectTeamTags(isEdit = false) {
            if (isEdit && this.editChildProjectTeamTagify) {
                this.editChildProjectTeamTagify.removeAllTags();
                this.editingChildProject.teams = '';
            } else if (!isEdit && this.createChildProjectTeamTagify) {
                this.createChildProjectTeamTagify.removeAllTags();
                this.newChildProject.teams = '';
            }
        },

        clearChildProjectMembersTags(isEdit = false) {
            if (isEdit && this.editChildProjectMembersTagify) {
                this.editChildProjectMembersTagify.removeAllTags();
                this.editingChildProject.members = [];
            } else if (!isEdit && this.createChildProjectMembersTagify) {
                this.createChildProjectMembersTagify.removeAllTags();
                this.newChildProject.members = [];
            }
        },
        
        async showEditChildProjectModal(project) {
            // Clear all Tagify and input values before opening modal
            this.destroyEditChildProjectTagify();
            const clearInput = (id) => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            };
            clearInput('edit_child_project_order_type');
            clearInput('edit_child_project_manager_tags');
            clearInput('edit_child_project_team_tags');
            clearInput('edit_child_project_members_tags');

            this._serverChildProjectDates = {
                start_date: project.start_date,
                end_date: project.end_date,
                caily_nouki: project.caily_nouki,
                guis_nouki: project.guis_nouki
            };
            const parentCustomerId = String((this.parentProject && this.parentProject.customer_id) || '');
            const childCustomerId = String(project.customer_id || '');
            const useParentCustomer = !childCustomerId || childCustomerId === parentCustomerId;
            const parentGuisReceiver = String((this.parentProject && this.parentProject.guis_receiver) || '');
            const childGuisReceiver = String(project.guis_receiver || '');
            const useParentGuisReceiver = !childGuisReceiver || childGuisReceiver === parentGuisReceiver;
            this._editChildOriginalStatus = project.status || '';
            this.editingChildProject = {
                id: project.id,
                name: project.name || '',
                department_id: project.department_id || '',
                department_name: project.department_name || '',
                project_number: project.project_number || '',
                description: project.description || '',
                start_date: project.start_date || '',
                end_date: project.end_date || '',
                project_order_type: project.project_order_type || '',
                parent_project_id: PARENT_PROJECT_ID,
                is_kadai: true,
                status: project.status || '',
                previous_status: project.previous_status || '',
                amount: project.amount || project.total_amount || 0,
                progress: project.progress != null ? parseInt(project.progress, 10) : 0,
                version: normalizeProjectVersion(project.version),
                teams: project.teams || '',
                managers: [],
                members: [],
                tantou: project.tantou || '',
                caily_nouki: project.caily_nouki || '',
                guis_nouki: project.guis_nouki || '',
                estimate_status: project.estimate_status || '未発行',
                invoice_status: project.invoice_status || '未発行',
                energy_drawing_share_status: project.energy_drawing_share_status || '',
                energy_drawing_share_reason: project.energy_drawing_share_reason || '',
                energy_drawing_share_note: project.energy_drawing_share_note || '',
                yotei: this.parseYoteiModel(project.yotei),
                custom_fields: project.custom_fields != null ? project.custom_fields : '',
                use_parent_customer: useParentCustomer,
                company_name: useParentCustomer ? '' : (project.company_name || ''),
                branch_name: useParentCustomer ? '' : (project.branch_name || ''),
                contact_name: useParentCustomer ? '' : (project.contact_name || ''),
                customer_id: useParentCustomer ? '' : childCustomerId,
                use_parent_guis_receiver: useParentGuisReceiver,
                guis_receiver: useParentGuisReceiver ? '' : childGuisReceiver
            };

            this.loadDepartments();

            const modalEl = document.getElementById('editChildProjectModal');
            let modal = bootstrap.Modal.getInstance(modalEl);
            if (!modal) {
                modal = new bootstrap.Modal(modalEl);
            }
            modal.show();

            const onShown = async () => {
                if (this.editingChildProject.id) {
                    try {
                        const res = await axios.get(`/api/index.php?model=project&method=getById&id=${this.editingChildProject.id}`);
                        const data = res.data && res.data.data ? res.data.data : res.data;
                        if (data) {
                            if (!(this.editingChildProject.teams || '').toString().trim() && data.teams != null) {
                                this.editingChildProject.teams = (data.teams || '').toString();
                            }
                            if (data.estimate_status != null) {
                                this.editingChildProject.estimate_status = data.estimate_status || '未発行';
                            }
                            if (data.invoice_status != null) {
                                this.editingChildProject.invoice_status = data.invoice_status || '未発行';
                            }
                            if (data.department_name) {
                                this.editingChildProject.department_name = data.department_name;
                            }
                            if (data.energy_drawing_share_status != null) {
                                this.editingChildProject.energy_drawing_share_status = data.energy_drawing_share_status || '';
                                this.editingChildProject.energy_drawing_share_reason = data.energy_drawing_share_reason || '';
                                this.editingChildProject.energy_drawing_share_note = data.energy_drawing_share_note || '';
                            }
                        }
                    } catch (e) { /* ignore */ }
                }
                this.initializeEditChildProjectDatePickers();
                this.initChildProjectYoteiMonthPickers(true);
                await this.initializeEditChildProjectTagify();
                if (this.editingChildProject.department_id) {
                    await this.loadEditChildProjectCustomFields(this.editingChildProject.department_id, this.editingChildProject.custom_fields);
                }
                setTimeout(() => {
                    this.initializeEditChildProjectQuill();
                }, 100);
                if (typeof window.applyDataI18n === 'function') {
                    const editModal = document.getElementById('editChildProjectModal');
                    if (editModal) window.applyDataI18n(editModal);
                }
                if (!this.editingChildProject.use_parent_customer) {
                    this.$nextTick(() => {
                        this.initChildProjectCustomerSelect2(true);
                    });
                }
                if (!this.editingChildProject.use_parent_guis_receiver) {
                    this.$nextTick(() => {
                        this.initChildProjectGuisReceiverSelect2(true);
                    });
                }
            };
            modalEl.addEventListener('shown.bs.modal', onShown, { once: true });
        },
        
        initializeEditChildProjectDatePickers() {
            this.initChildProjectDatePicker('edit_start_date_picker', 'start_date', { defaultHour: getStartDateDefaultHour(), defaultMinute: 0 }, true);
            this.initChildProjectDatePicker('edit_end_date_picker', 'end_date', { defaultHour: getDeadlineDefaultHour(), defaultMinute: 0 }, true);
            this.initChildProjectDatePicker('edit_caily_nouki_picker', 'caily_nouki', { defaultHour: getDeadlineDefaultHour(), defaultMinute: 0 }, true);
            this.initChildProjectDatePicker('edit_guis_nouki_picker', 'guis_nouki', { defaultHour: getDeadlineDefaultHour(), defaultMinute: 0 }, true);
        },
        
        async initializeEditChildProjectTagify() {
            const orderTypeInput = document.querySelector('#edit_child_project_order_type');
            if (orderTypeInput) {
                // Destroy existing instance if it exists
                if (orderTypeInput.tagify) {
                    orderTypeInput.tagify.destroy();
                }
                // Clear input so Tagify does not parse existing value (avoids duplicate when we addTags below)
                orderTypeInput.value = '';
                
                this.editChildProjectOrderTypeTagify = new Tagify(orderTypeInput, {
                    whitelist: ['新規', '修正', '新規修正', '変更', '免震', '耐震', '計画変更', '契約図', '実施図'],
                    maxTags: 5,
                    dropdown: {
                        maxItems: 20,
                        classname: "tags-look-project-order-type",
                        enabled: 0,
                        closeOnSelect: true
                    }
                });
                
                // Update the model when tags change
                const updateOrderType = () => {
                    const tags = this.editChildProjectOrderTypeTagify.value.map(tag => tag.value).join(',');
                    this.editingChildProject.project_order_type = tags;
                };
                
                this.editChildProjectOrderTypeTagify.on('add', updateOrderType);
                this.editChildProjectOrderTypeTagify.on('remove', updateOrderType);
                // Set tags once from model (input was cleared so no double-add from Tagify init)
                const orderTypeVal = (this.editingChildProject.project_order_type || '').toString().trim();
                if (orderTypeVal) {
                    this.editChildProjectOrderTypeTagify.addTags(orderTypeVal);
                }
            }
            
            // Initialize Tagify for manager, team, members
            await this.initializeEditChildProjectManagerTagify();
            await this.initializeEditChildProjectTeamTagify();
            await this.initializeEditChildProjectMembersTagify();
        },
        
        async initializeEditChildProjectManagerTagify() {
            const managerInput = document.getElementById('edit_child_project_manager_tags');
            if (managerInput && window.Tagify) {
                // Destroy existing instance if it exists
                if (managerInput._tagify) {
                    managerInput._tagify.destroy();
                }
                if (this.editChildProjectManagerTagify) {
                    this.editChildProjectManagerTagify.destroy();
                    this.editChildProjectManagerTagify = null;
                }
                
                // Clear input value to ensure clean state
                managerInput.value = '';
                
                // Reset managers array to ensure clean state
                this.editingChildProject.managers = [];
                
                // Load department users if department is selected
                if (this.editingChildProject.department_id) {
                    await this.loadDepartmentUsers(this.editingChildProject.department_id, true);
                }
                
                const users = (this.departmentUsers || []).map(u => ({
                    id: String(u.id || u.user_id),
                    value: u.user_name || u.realname || u.name,
                    name: u.user_name || u.realname || u.name
                }));
                
                console.log('Initializing edit manager Tagify with users:', users); // Debug log
                
                this.editChildProjectManagerTagify = new Tagify(managerInput, {
                    whitelist: users,
                    maxTags: 10,
                    enforceWhitelist: false,
                    dropdown: {
                        maxItems: 20,
                        enabled: 0, // Auto-show on input
                        closeOnSelect: true,
                        classname: 'tagify__dropdown',
                        searchKeys: ['value', 'name']
                    }
                });
                
                // Update the model when tags change
                this.editChildProjectManagerTagify.on('change', (e) => {
                    this.editingChildProject.managers = this.editChildProjectManagerTagify.value.map(t => String(t.id != null ? t.id : t.value)).filter(Boolean);
                });
                
                // Load existing managers for the project (clear first to avoid add-then-remove-duplicates)
                if (this.editingChildProject.id) {
                    try {
                        if (this.editChildProjectManagerTagify) {
                            this.editChildProjectManagerTagify.removeAllTags();
                            this.editingChildProject.managers = [];
                        }
                        const response = await axios.get(`/api/index.php?model=project&method=getMembers&project_id=${this.editingChildProject.id}&role=manager`);
                        if (response.data) {
                            const managers = Array.isArray(response.data) ? response.data : (response.data.data || []);
                            this.editingChildProject.managers = managers.map(m => String(m.user_id || m.id));
                            if (this.departmentUsers && this.departmentUsers.length > 0) {
                                const managerTags = managers.map(m => {
                                    const userId = String(m.user_id || m.id);
                                    const user = this.departmentUsers.find(u => String(u.id || u.user_id) === userId);
                                    return user ? { 
                                        id: String(user.id || user.user_id), 
                                        value: user.user_name || user.realname || user.name 
                                    } : null;
                                }).filter(t => t !== null);
                                if (managerTags.length > 0 && this.editChildProjectManagerTagify) {
                                    this.editChildProjectManagerTagify.addTags(managerTags);
                                }
                            }
                        }
                    } catch (error) {
                        console.error('Error loading project managers:', error);
                    }
                }
            }
        },
        
        async initializeEditChildProjectTeamTagify() {
            const teamInput = document.getElementById('edit_child_project_team_tags');
            if (!teamInput || !window.Tagify) return;
            if (teamInput._tagify) {
                teamInput._tagify.destroy();
            }
            teamInput.value = '';
            let departmentTeams = [];
            const deptId = this.editingChildProject.department_id;
            if (deptId) {
                try {
                    const res = await axios.get('/api/index.php?model=team&method=listbydepartment&department_id=' + encodeURIComponent(deptId));
                    const raw = res.data;
                    departmentTeams = Array.isArray(raw) ? raw : (raw && Array.isArray(raw.data) ? raw.data : []);
                } catch (e) {
                    departmentTeams = [];
                }
            }
            const teamWhitelist = departmentTeams.map(t => ({ id: String(t.id), value: t.name || String(t.id) }));
            this.editChildProjectTeamTagify = new Tagify(teamInput, {
                whitelist: teamWhitelist,
                enforceWhitelist: false,
                dropdown: {
                    maxItems: 1000,
                    enabled: 0,
                    closeOnSelect: true,
                    searchKeys: ['value'],
                    classname: 'tagify__dropdown'
                }
            });
            this.editChildProjectTeamTagify.settings.whitelist = teamWhitelist;
            this.editChildProjectTeamTagify.whitelist = teamWhitelist;
            this.editChildProjectTeamTagify.on('focus', () => {
                if (this.editChildProjectTeamTagify && this.editChildProjectTeamTagify.whitelist && this.editChildProjectTeamTagify.whitelist.length > 0) {
                    this.editChildProjectTeamTagify.dropdown.show();
                }
            });
            this.editChildProjectTeamTagify.on('change', () => {
                this.editingChildProject.teams = this.editChildProjectTeamTagify.value.map(t => t.id).join(',');
            });
            // Load existing teams BEFORE attaching 'add'/'remove' so we don't auto-add team leaders/members (keep 管理 and メンバー as saved)
            const teamsStr = (this.editingChildProject.teams || '').toString().trim();
            if (teamsStr) {
                const teamIds = teamsStr.split(',').map(s => s.trim()).filter(Boolean);
                const tagsToAdd = teamIds.map(tid => {
                    const t = departmentTeams.find(tt => String(tt.id) === String(tid));
                    return { id: String(tid), value: (t && t.name) ? t.name : String(tid) };
                });
                if (tagsToAdd.length > 0) {
                    this.$nextTick(() => {
                        if (this.editChildProjectTeamTagify) {
                            this.editChildProjectTeamTagify.addTags(tagsToAdd);
                        }
                    });
                }
            }
            // Attach add/remove after loading existing teams so opening modal doesn't re-add team leaders to 管理 or team members to メンバー
            this.editChildProjectTeamTagify.on('add', async (e) => {
                const addedTeamId = e.detail.data && e.detail.data.id;
                if (!addedTeamId) return;
                try {
                    const res = await axios.get(`/api/index.php?model=team&method=get&id=${addedTeamId}`);
                    if (res.data && Array.isArray(res.data.members)) {
                        if (this.editChildProjectMembersTagify) {
                            const teamMembers = res.data.members.map(m => ({ id: m.user_id, value: m.user_name || '' }));
                            const currentIds = this.editChildProjectMembersTagify.value.map(tag => String(tag.id));
                            const toAdd = teamMembers.filter(m => !currentIds.includes(String(m.id)));
                            this.editChildProjectMembersTagify.addTags(toAdd);
                        }
                        const leaders = res.data.members.filter(m => m.leader == 1 || m.leader === '1');
                        if (leaders.length && this.editChildProjectManagerTagify) {
                            const leaderTags = leaders.map(m => ({ id: m.user_id, value: m.user_name || '' }));
                            const managerCurrentIds = this.editChildProjectManagerTagify.value.map(tag => String(tag.id));
                            const leadersToAdd = leaderTags.filter(m => !managerCurrentIds.includes(String(m.id)));
                            this.editChildProjectManagerTagify.addTags(leadersToAdd);
                        }
                    }
                } catch (err) {}
            });
            this.editChildProjectTeamTagify.on('remove', async (e) => {
                const removedTeamId = e.detail.data && e.detail.data.id;
                if (!removedTeamId || !this.editChildProjectMembersTagify) return;
                try {
                    const res = await axios.get(`/api/index.php?model=team&method=get&id=${removedTeamId}`);
                    if (res.data && Array.isArray(res.data.members)) {
                        const teamMemberIds = res.data.members.map(m => String(m.user_id));
                        const remain = this.editChildProjectMembersTagify.value.filter(tag => !teamMemberIds.includes(String(tag.id)));
                        this.editChildProjectMembersTagify.removeAllTags();
                        this.editChildProjectMembersTagify.addTags(remain);
                    }
                } catch (err) {}
            });
        },
        
        async initializeEditChildProjectMembersTagify() {
            const membersInput = document.getElementById('edit_child_project_members_tags');
            if (!membersInput || !window.Tagify) return;
            if (membersInput._tagify) {
                membersInput._tagify.destroy();
            }
            membersInput.value = '';
            const users = (this.departmentUsers || []).map(u => ({
                id: u.id || u.user_id,
                value: u.user_name || u.realname || u.name,
                name: u.user_name || u.realname || u.name
            }));
            this.editChildProjectMembersTagify = new Tagify(membersInput, {
                whitelist: users,
                enforceWhitelist: false,
                dropdown: { maxItems: 1000, enabled: 0, closeOnSelect: true }
            });
            this.editChildProjectMembersTagify.on('change', () => {
                this.editingChildProject.members = this.editChildProjectMembersTagify.value.map(t => t.id);
            });
            // Load existing members for the project (set editingChildProject.members only after addTags to avoid double-add)
            if (this.editingChildProject.id) {
                try {
                    const response = await axios.get(`/api/index.php?model=project&method=getMembers&project_id=${this.editingChildProject.id}&role=member`);
                    if (response.data) {
                        const members = Array.isArray(response.data) ? response.data : (response.data.data || []);
                        if (members.length > 0 && (this.departmentUsers || []).length > 0) {
                            const memberTags = members.map(m => {
                                const userId = String(m.user_id || m.id);
                                const user = this.departmentUsers.find(u => String(u.id || u.user_id) === userId);
                                return user ? { id: userId, value: user.user_name || user.realname || user.name } : null;
                            }).filter(t => t !== null);
                            if (memberTags.length > 0) {
                                this.$nextTick(() => {
                                    if (this.editChildProjectMembersTagify) {
                                        this.editChildProjectMembersTagify.removeAllTags();
                                        this.editChildProjectMembersTagify.addTags(memberTags);
                                    }
                                    this.editingChildProject.members = members.map(m => String(m.user_id || m.id));
                                });
                            } else {
                                this.editingChildProject.members = members.map(m => String(m.user_id || m.id));
                            }
                        } else {
                            this.editingChildProject.members = members.map(m => String(m.user_id || m.id));
                        }
                    }
                } catch (error) {
                    console.error('Error loading project members:', error);
                }
            }
        },
        
        clearEditChildProjectTagifyTags(fieldName) {
            if (fieldName === 'project_order_type' && this.editChildProjectOrderTypeTagify) {
                this.editChildProjectOrderTypeTagify.removeAllTags();
                this.editingChildProject.project_order_type = '';
            }
        },
        
        clearChildProjectManagerTags(isEdit = false) {
            if (isEdit && this.editChildProjectManagerTagify) {
                this.editChildProjectManagerTagify.removeAllTags();
                this.editingChildProject.managers = [];
            } else if (!isEdit && this.createChildProjectManagerTagify) {
                this.createChildProjectManagerTagify.removeAllTags();
                this.newChildProject.managers = [];
            }
        },
        
        destroyEditChildProjectTagify() {
            if (this.editChildProjectOrderTypeTagify) {
                this.editChildProjectOrderTypeTagify.destroy();
                this.editChildProjectOrderTypeTagify = null;
            }
            if (this.editChildProjectManagerTagify) {
                this.editChildProjectManagerTagify.destroy();
                this.editChildProjectManagerTagify = null;
            }
            if (this.editChildProjectTeamTagify) {
                this.editChildProjectTeamTagify.destroy();
                this.editChildProjectTeamTagify = null;
            }
            if (this.editChildProjectMembersTagify) {
                this.editChildProjectMembersTagify.destroy();
                this.editChildProjectMembersTagify = null;
            }
            this.destroyChildProjectCustomerSelect2(true);
            this.destroyChildProjectGuisReceiverSelect2(true);
        },

        initializeEditChildProjectQuill() {
            // Prevent multiple simultaneous initializations
            if (this.editChildProjectQuillInitializing) {
                console.log('Quill editor already initializing, skipping...');
                return;
            }
            
            try {
                this.editChildProjectQuillInitializing = true;
                
                const el = document.getElementById('edit_child_project_quill_description');
                if (!el) {
                    console.log('Quill editor element not found');
                    this.editChildProjectQuillInitializing = false;
                    return;
                }
                
                // Check if element already has Quill toolbar (indicating duplicate initialization)
                const existingToolbar = el.parentElement.querySelector('.ql-toolbar');
                if (existingToolbar) {
                    console.log('Found existing Quill toolbar, removing...');
                    existingToolbar.remove();
                }
                
                // Check if element has Quill classes
                if (el.classList.contains('ql-container')) {
                    console.log('Element has Quill classes, cleaning...');
                    el.className = 'custom_editor_content';
                    el.setAttribute('id', 'edit_child_project_quill_description');
                }
                
                // Completely reset the element
                el.innerHTML = '';
                
                // Destroy existing instance if any
                if (this.editChildProjectQuillInstance) {
                    try {
                        this.editChildProjectQuillInstance = null;
                    } catch (e) {
                        console.log('Error destroying existing instance:', e);
                    }
                }
                
                // Create new Quill instance
                this.editChildProjectQuillInstance = new Quill(el, {
                    bounds: el,
                    placeholder: '説明を入力してください...',
                    modules: {
                        toolbar: [
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ color: [] }, { background: [] }],
                            ['blockquote', 'code-block'],
                            [{ 'header': 1 }, { 'header': 2 }],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                            [{ 'script': 'sub'}, { 'script': 'super' }],
                            [{ 'indent': '-1'}, { 'indent': '+1' }],
                            [{ 'direction': 'rtl' }, { 'align': [] }],
                            ['link'],
                            ['clean']
                        ]
                    },
                    theme: 'snow'
                });
                
                // Set initial content
                if (this.editingChildProject.description) {
                    this.editChildProjectQuillInstance.root.innerHTML = this.decodeHtmlEntities(this.editingChildProject.description);
                }
                
                // Store content in a separate variable
                this.editChildProjectQuillContent = this.editChildProjectQuillInstance.getSemanticHTML();
                
                // Update content when text changes
                this.editChildProjectQuillInstance.on('text-change', () => {
                    this.editChildProjectQuillContent = this.editChildProjectQuillInstance.getSemanticHTML();
                });

                // Prevent Enter from bubbling to the form so it doesn't trigger submit or move focus
                const editQuillRoot = this.editChildProjectQuillInstance.root;
                if (editQuillRoot) {
                    this._editChildProjectQuillEnterHandler = (e) => {
                        if (e.key === 'Enter') e.stopPropagation();
                    };
                    editQuillRoot.addEventListener('keydown', this._editChildProjectQuillEnterHandler);
                }
                
                console.log('New Quill editor initialized successfully');
            } catch (error) {
                console.error('Error initializing Quill editor:', error);
            } finally {
                this.editChildProjectQuillInitializing = false;
            }
        },

        destroyEditChildProjectQuill() {
            try {
                // Remove Enter key listener before destroying
                if (this.editChildProjectQuillInstance && this.editChildProjectQuillInstance.root && this._editChildProjectQuillEnterHandler) {
                    this.editChildProjectQuillInstance.root.removeEventListener('keydown', this._editChildProjectQuillEnterHandler);
                    this._editChildProjectQuillEnterHandler = null;
                }
                // Destroy Quill instance if it exists
                if (this.editChildProjectQuillInstance) {
                    this.editChildProjectQuillInstance.setText('');
                    this.editChildProjectQuillInstance = null;
                    console.log('Quill editor instance destroyed');
                }
                
                // Clear stored content
                this.editChildProjectQuillContent = '';
                
                // Get the container element
                const quillContainer = document.getElementById('edit_child_project_quill_description');
                if (quillContainer) {
                    // Remove all Quill-generated elements from parent
                    const parent = quillContainer.parentElement;
                    if (parent) {
                        // Remove toolbar if exists
                        const toolbar = parent.querySelector('.ql-toolbar');
                        if (toolbar) {
                            toolbar.remove();
                        }
                        
                        // Remove any other Quill elements
                        const quillElements = parent.querySelectorAll('.ql-container, .ql-editor');
                        quillElements.forEach(el => {
                            if (el !== quillContainer) {
                                el.remove();
                            }
                        });
                    }
                    
                    // Reset the container element completely
                    quillContainer.innerHTML = '';
                    quillContainer.className = 'custom_editor_content';
                    quillContainer.setAttribute('id', 'edit_child_project_quill_description');
                    
                    // Remove any Quill-added attributes
                    quillContainer.removeAttribute('contenteditable');
                    quillContainer.removeAttribute('data-gramm');
                    quillContainer.removeAttribute('data-gramm_editor');
                    quillContainer.removeAttribute('data-enable-grammarly');
                }
                
                console.log('Quill editor DOM cleaned successfully');
            } catch (e) {
                console.log('Error destroying quill editor:', e);
            } finally {
                // Always reset the initialization flag
                this.editChildProjectQuillInitializing = false;
            }
        },

        initializeCreateChildProjectQuill() {
            // Prevent multiple simultaneous initializations
            if (this.createChildProjectQuillInitializing) {
                console.log('Create Quill editor already initializing, skipping...');
                return;
            }
            
            try {
                this.createChildProjectQuillInitializing = true;
                
                const el = document.getElementById('create_child_project_quill_description');
                if (!el) {
                    console.log('Create Quill editor element not found');
                    this.createChildProjectQuillInitializing = false;
                    return;
                }
                
                // Check if element already has Quill toolbar (indicating duplicate initialization)
                const existingToolbar = el.parentElement.querySelector('.ql-toolbar');
                if (existingToolbar) {
                    console.log('Found existing Create Quill toolbar, removing...');
                    existingToolbar.remove();
                }
                
                // Check if element has Quill classes
                if (el.classList.contains('ql-container')) {
                    console.log('Create element has Quill classes, cleaning...');
                    el.className = 'custom_editor_content';
                    el.setAttribute('id', 'create_child_project_quill_description');
                }
                
                // Completely reset the element
                el.innerHTML = '';
                
                // Destroy existing instance if any
                if (this.createChildProjectQuillInstance) {
                    try {
                        this.createChildProjectQuillInstance = null;
                    } catch (e) {
                        console.log('Error destroying existing create instance:', e);
                    }
                }
                
                // Create new Quill instance
                this.createChildProjectQuillInstance = new Quill(el, {
                    bounds: el,
                    placeholder: '説明を入力してください...',
                    modules: {
                        toolbar: [
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ color: [] }, { background: [] }],
                            ['blockquote', 'code-block'],
                            [{ 'header': 1 }, { 'header': 2 }],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                            [{ 'script': 'sub'}, { 'script': 'super' }],
                            [{ 'indent': '-1'}, { 'indent': '+1' }],
                            [{ 'direction': 'rtl' }, { 'align': [] }],
                            ['link'],
                            ['clean']
                        ]
                    },
                    theme: 'snow'
                });
                
                // Set initial content (empty for create modal)
                this.createChildProjectQuillContent = '';
                
                // Update only internal content on text-change to avoid Vue re-render (which causes focus loss when 受注形態 has value)
                this.createChildProjectQuillInstance.on('text-change', () => {
                    this.createChildProjectQuillContent = this.createChildProjectQuillInstance.getSemanticHTML();
                });

                // Prevent Enter from bubbling to the form so it doesn't trigger submit or move focus to 受注形態
                const quillRoot = this.createChildProjectQuillInstance.root;
                if (quillRoot) {
                    this._createChildProjectQuillEnterHandler = (e) => {
                        if (e.key === 'Enter') e.stopPropagation();
                    };
                    quillRoot.addEventListener('keydown', this._createChildProjectQuillEnterHandler);
                }
                
                console.log('New Create Quill editor initialized successfully');
            } catch (error) {
                console.error('Error initializing Create Quill editor:', error);
            } finally {
                this.createChildProjectQuillInitializing = false;
            }
        },

        destroyCreateChildProjectQuill() {
            try {
                // Remove Enter key listener before destroying
                if (this.createChildProjectQuillInstance && this.createChildProjectQuillInstance.root && this._createChildProjectQuillEnterHandler) {
                    this.createChildProjectQuillInstance.root.removeEventListener('keydown', this._createChildProjectQuillEnterHandler);
                    this._createChildProjectQuillEnterHandler = null;
                }
                // Destroy Quill instance if it exists
                if (this.createChildProjectQuillInstance) {
                    this.createChildProjectQuillInstance.setText('');
                    this.createChildProjectQuillInstance = null;
                    console.log('Create Quill editor instance destroyed');
                }
                
                // Clear stored content
                this.createChildProjectQuillContent = '';
                
                // Get the container element
                const quillContainer = document.getElementById('create_child_project_quill_description');
                if (quillContainer) {
                    // Remove all Quill-generated elements from parent
                    const parent = quillContainer.parentElement;
                    if (parent) {
                        // Remove toolbar if exists
                        const toolbar = parent.querySelector('.ql-toolbar');
                        if (toolbar) {
                            toolbar.remove();
                        }
                        
                        // Remove any other Quill elements
                        const quillElements = parent.querySelectorAll('.ql-container, .ql-editor');
                        quillElements.forEach(el => {
                            if (el !== quillContainer) {
                                el.remove();
                            }
                        });
                    }
                    
                    // Reset the container element completely
                    quillContainer.innerHTML = '';
                    quillContainer.className = 'custom_editor_content';
                    quillContainer.setAttribute('id', 'create_child_project_quill_description');
                    
                    // Remove any Quill-added attributes
                    quillContainer.removeAttribute('contenteditable');
                    quillContainer.removeAttribute('data-gramm');
                    quillContainer.removeAttribute('data-gramm_editor');
                    quillContainer.removeAttribute('data-enable-grammarly');
                }
                
                console.log('Create Quill editor DOM cleaned successfully');
            } catch (e) {
                console.log('Error destroying create quill editor:', e);
            } finally {
                // Always reset the initialization flag
                this.createChildProjectQuillInitializing = false;
            }
        },

        decodeHtmlEntities(str) {
            if (!str) return '';
            const textarea = document.createElement('textarea');
            textarea.innerHTML = str;
            return textarea.value;
        },
        getDescriptionPlainText(html) {
            if (!html) return '';
            const decoded = this.decodeHtmlEntities(html);
            const div = document.createElement('div');
            div.innerHTML = decoded;
            return (div.textContent || div.innerText || '').replace(/\s+/g, ' ').trim();
        },
        formatDescriptionPreview(html, maxLength = 50) {
            const text = this.getDescriptionPlainText(html);
            if (!text) return '-';
            return text.length > maxLength ? text.slice(0, maxLength) + '…' : text;
        },

        async cancelChildProject(project) {
            try {
                const result = await Swal.fire({
                    title: '案件依頼をキャンセルしますか？',
                    text: `案件「${project.name}」をキャンセルします。`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'OK',
                    cancelButtonText: '取消'
                });

                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('id', project.id);
                    formData.append('status', 'cancelled');
                    // The backend will automatically save the current status as previous_status

                    const response = await axios.post('/api/index.php?model=project&method=updateStatus', formData);

                    if (response.data && response.data.status === 'success') {
                        await Swal.fire({
                            title: '完了',
                            text: '案件依頼をキャンセルしました。',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });

                        // Reload child projects to show updated status
                        await this.loadChildProjects();
                        
                        // Reload quotations as cancelled projects might affect quotation status
                        await this.loadQuotations();
                    } else {
                        showParentProjectError(response.data.error || response.data.message || 'キャンセルに失敗しました', response && response.data);
                    }
                }
            } catch (error) {
                console.error('Error cancelling child project:', error);
                showParentProjectError(error.message || '案件依頼のキャンセル中にエラーが発生しました。', error);
            }
        },

        async restoreChildProject() {
            try {
                const result = await Swal.fire({
                    title: '案件依頼を復元しますか？',
                    text: `案件「${this.editingChildProject.name}」を復元します。`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '復元',
                    cancelButtonText: '取消'
                });

                if (result.isConfirmed) {
                    this.restoringChildProject = true;
                    
                    const formData = new FormData();
                    formData.append('id', this.editingChildProject.id);
                    // Restore to previous status or default to 'open' if no previous status
                    formData.append('status', this.editingChildProject.previous_status || 'open');

                    const response = await axios.post('/api/index.php?model=project&method=updateStatus', formData);

                    if (response.data.status === 'success') {
                        await Swal.fire({
                            title: '完了',
                            text: '案件依頼を復元しました。',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });

                        // Close modal and reload data
                        const modal = bootstrap.Modal.getInstance(document.getElementById('editChildProjectModal'));
                        modal.hide();
                        await this.loadChildProjects();
                    } else {
                        showParentProjectError(response.data.error || response.data.message || '復元に失敗しました', response && response.data);
                    }
                }
            } catch (error) {
                console.error('Error restoring child project:', error);
                showParentProjectError(error.message || '案件依頼の復元に失敗しました。', error);
            } finally {
                this.restoringChildProject = false;
            }
        },
        
        validateEditChildProjectForm() {
            this.syncChildProjectDateFieldsFromPickers(true);
            this.editChildProjectValidationErrors = {
                name: '',
                department_id: '',
                start_date: '',
                end_date: '',
                project_order_type: '',
                tantou: '',
                caily_nouki: '',
                guis_nouki: '',
                yotei: '',
                company_name: '',
                branch_name: '',
                contact_name: '',
                guis_receiver: ''
            };
            
            let isValid = true;
            
            if (!this.editingChildProject.name.trim()) {
                this.editChildProjectValidationErrors.name = '課題名は必須です。';
                isValid = false;
            }
            
            if (!this.editingChildProject.department_id) {
                this.editChildProjectValidationErrors.department_id = '部署は必須です。';
                isValid = false;
            }

            // Validate that start date is before end date (only when both are filled)
            if (this.editingChildProject.start_date && this.editingChildProject.end_date) {
                const startDate = this.parseChildProjectDateTime(this.editingChildProject.start_date);
                const endDate = this.parseChildProjectDateTime(this.editingChildProject.end_date);
                
                if (startDate >= endDate) {
                    this.editChildProjectValidationErrors.end_date = '期限日は開始日より後である必要があります';
                    isValid = false;
                }
            }

            // Validate project order type is required
            if (!this.editingChildProject.project_order_type || this.editingChildProject.project_order_type.trim() === '') {
                this.editChildProjectValidationErrors.project_order_type = '受注形態は必須です';
                isValid = false;
            }

            // Validate tantou (担当) is required
            if (!this.editingChildProject.tantou || this.editingChildProject.tantou.trim() === '') {
                this.editChildProjectValidationErrors.tantou = '担当は必須です';
                isValid = false;
            }

            if (!this.validateChildProjectNoukiFields(this.editingChildProject, this.editChildProjectValidationErrors)) {
                isValid = false;
            }

            if (!this.validateChildProjectCustomer(this.editingChildProject, this.editChildProjectValidationErrors)) {
                isValid = false;
            }

            if (!this.validateChildProjectGuisReceiver(this.editingChildProject, this.editChildProjectValidationErrors)) {
                isValid = false;
            }

            if (!this.editingChildProject.yotei) {
                this.editingChildProject.yotei = this.emptyYoteiModel();
            }
            if (typeof window.YoteiField !== 'undefined' && !window.YoteiField.isValid(this.editingChildProject.yotei)) {
                this.editChildProjectValidationErrors.yotei = '予定工程の期間が正しくありません';
                isValid = false;
            }
            
            return isValid;
        },
        
        async updateChildProject() {
            this.syncChildProjectDateFieldsFromPickers(true);
            if (!this.validateEditChildProjectForm()) {
                this.notifyChildProjectValidationError();
                return;
            }

            const prevStatus = this._editChildOriginalStatus;
            if (this.editingChildProject.status === 'completed' && prevStatus !== 'completed') {
                const ok = await this.confirmPaymentInfoBeforeComplete(this.editingChildProject);
                if (!ok) return;
            }

            let shareAnswer = null;
            if (this.editingChildProject.status === 'completed' && prevStatus !== 'completed' && window.EnergyDrawingShare) {
                const deptName = this.editingChildProject.department_name
                    || this.resolveChildDepartmentName(this.editingChildProject.department_id)
                    || '';
                const siblings = this.childProjects || this.projects || [];
                const answer = await window.EnergyDrawingShare.ensureEnergyDrawingShareAnswer(
                    this.editingChildProject,
                    {
                        departmentName: deptName,
                        siblings: siblings
                    }
                );
                if (answer === false) return;
                shareAnswer = answer;
            }

            // Sync Quill content with the form data
            if (this.editChildProjectQuillInstance) {
                this.editChildProjectQuillContent = this.editChildProjectQuillInstance.getSemanticHTML();
            }

            this.updatingChildProject = true;

            try {
                // Đồng bộ managers từ Tagify trước khi gửi (giống create)
                if (this.editChildProjectManagerTagify) {
                    this.editingChildProject.managers = this.editChildProjectManagerTagify.value.map(t => String(t.id != null ? t.id : t.value)).filter(Boolean);
                }

                const formData = new FormData();
                formData.append('id', this.editingChildProject.id);
                formData.append('name', this.editingChildProject.name);
                formData.append('department_id', this.editingChildProject.department_id);
                formData.append('project_number', this.editingChildProject.project_number || '');
                formData.append('description', this.editChildProjectQuillContent || '');
                formData.append('start_date', this.toChildProjectAPIDate(this.editingChildProject.start_date));
                formData.append('end_date', this.toChildProjectAPIDate(this.editingChildProject.end_date));
                formData.append('project_order_type', this.editingChildProject.project_order_type || '');
                // Luôn gửi managers (kể cả rỗng) để backend cập nhật đúng project_members
                formData.append('managers', (this.editingChildProject.managers && this.editingChildProject.managers.length > 0) ? this.editingChildProject.managers.join(',') : '');
                formData.append('parent_project_id', this.editingChildProject.parent_project_id);
                formData.append('status', this.editingChildProject.status || 'draft');
                formData.append('amount', this.editingChildProject.amount || 0);
                formData.append('progress', this.editingChildProject.progress != null ? parseInt(this.editingChildProject.progress, 10) : 0);
                formData.append('teams', this.editingChildProject.teams || '');
                if (this.editingChildProject.members && this.editingChildProject.members.length > 0) {
                    formData.append('members', Array.isArray(this.editingChildProject.members) ? this.editingChildProject.members.join(',') : String(this.editingChildProject.members));
                }
                formData.append('tantou', this.editingChildProject.tantou || '');
                formData.append('caily_nouki', this.toChildProjectAPIDate(this.editingChildProject.caily_nouki) || '');
                formData.append('guis_nouki', this.toChildProjectAPIDate(this.editingChildProject.guis_nouki) || '');
                this.appendYoteiToFormData(formData, this.editingChildProject.yotei);

                formData.append('is_kadai', '0');
                formData.append('customer_id', this.resolveChildProjectCustomerId(this.editingChildProject));
                formData.append('guis_receiver', this.resolveChildProjectGuisReceiver(this.editingChildProject));

                const editCustomFields = this.collectChildProjectCustomFields('editChildProjectCustomFieldsWrap', 'editChildProjectCustomField', 'editChildProjectCustomInput', 'editChildProjectCustomCheckbox', 'editChildProjectCustomRadio');
                if (editCustomFields.length) formData.append('custom_fields', JSON.stringify(editCustomFields));

                appendProjectVersionToFormData(formData, this.editingChildProject);
                if (window.EnergyDrawingShare) {
                    window.EnergyDrawingShare.appendEnergyDrawingShareToFormData(formData, shareAnswer);
                }
                const response = await axios.post('/api/index.php?model=project&method=update', formData);

                if (response.data.status === 'success') {
                    showMessage('課題が正常に更新されました。');
                    this._serverChildProjectDates = null;
                    
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editChildProjectModal'));
                    if (modal) {
                        modal.hide();
                    }
                    
                    // Reload child projects
                    await this.loadChildProjects();
                    
                    // Reset form
                    this.editingChildProject = {
                        id: null,
                        name: '',
                        department_id: '',
                        project_number: '',
                        description: '',
                        start_date: '',
                        end_date: '',
                        project_order_type: '',
                        parent_project_id: PARENT_PROJECT_ID,
                        is_kadai: true,
                        status: 'draft',
                        previous_status: '',
                        amount: 0,
                        progress: 0,
                        teams: '',
                        managers: [],
                        members: [],
                        tantou: '',
                        caily_nouki: '',
                        guis_nouki: '',
                        yotei: this.emptyYoteiModel()
                    };
                    
                    // Reset Quill content
                    this.editChildProjectQuillContent = '';
                } else {
                    if (handleProjectVersionConflict(response.data, () => this.loadChildProjects())) {
                        return;
                    }
                    showParentProjectError(response.data.message || '課題の更新に失敗しました。', response && response.data);
                }
            } catch (error) {
                console.error('Error updating child project:', error);
                showParentProjectError('課題の更新に失敗しました。', error);
            } finally {
                this.updatingChildProject = false;
            }
        },
        
        formatDateTimeForInput(dateTimeString) {
            return toProjectDateTimeInputValue(dateTimeString);
        },



        async generateChildProjectNumber() {
            try {
                // Get parent project number and existing child projects count
                const parentProjectNumber = this.parentProject ? this.parentProject.project_number : '';
                const childCount = this.childProjects.length;
                let nextNumber = childCount + 1;
                
                // Check if the generated number already exists and find the next available number
                let childProjectNumber = '';
                if (parentProjectNumber) {
                    let attempts = 0;
                    const maxAttempts = 10; // Prevent infinite loop
                    
                    while (attempts < maxAttempts) {
                        childProjectNumber = `${parentProjectNumber}-${nextNumber.toString().padStart(2, '0')}`;
                        
                        // Check if this number already exists in child projects
                        const exists = this.childProjects.some(project => 
                            project.project_number === childProjectNumber
                        );
                        
                        if (!exists) {
                            break; // Found available number
                        }
                        
                        nextNumber++;
                        attempts++;
                    }
                }
                
                this.newChildProject.project_number = childProjectNumber;
            } catch (error) {
                console.error('Error generating child project number:', error);
                showMessage('プロジェクト番号の生成に失敗しました', true);
            }
        },

        hasChildProjectDateValue(value) {
            return !!(value && String(value).trim() !== '');
        },

        parseChildProjectDateTime(value) {
            if (!this.hasChildProjectDateValue(value)) return null;
            const parsed = parseProjectDateTimeInDisplayTz(value);
            return parsed ? parsed.toDate() : null;
        },

        /** DOM/flatpickr → model (create・edit modal). */
        syncChildProjectDateFieldsFromPickers(isEdit) {
            const project = isEdit ? this.editingChildProject : this.newChildProject;
            const fieldIds = isEdit ? {
                start_date: 'edit_start_date_picker',
                end_date: 'edit_end_date_picker',
                caily_nouki: 'edit_caily_nouki_picker',
                guis_nouki: 'edit_guis_nouki_picker'
            } : {
                start_date: 'start_date_picker',
                end_date: 'end_date_picker',
                caily_nouki: 'create_caily_nouki_picker',
                guis_nouki: 'create_guis_nouki_picker'
            };
            Object.keys(fieldIds).forEach((key) => {
                const el = document.getElementById(fieldIds[key]);
                if (!el) return;
                const fp = el._flatpickr;
                const displayVal = getFlatpickrVisibleValue(fp, el);
                if (!displayVal) {
                    if (fp && fp.selectedDates && fp.selectedDates.length) {
                        try { fp.clear(); } catch (e) {}
                    }
                    project[key] = '';
                    el.value = '';
                    return;
                }
                if (fp && fp.selectedDates && fp.selectedDates.length > 0) {
                    project[key] = fp.formatDate(fp.selectedDates[0], PROJECT_DATETIME_FLATPICKR_FORMAT);
                } else {
                    project[key] = displayVal;
                }
            });
        },

        /** 期限日なし→納期任意。期限日あり→CAILY/GUIS納期は担当に応じて必須。GUIS納期あり→期限日必須。両方入力時はGUIS納期≥CAILY納期。 */
        validateChildProjectNoukiFields(project, errors) {
            let isValid = true;
            const tantou = (project.tantou || '').trim();
            const caily = (project.caily_nouki || '').trim();
            const guis = (project.guis_nouki || '').trim();
            const end = (project.end_date || '').trim();
            const showGuisFields = this.canViewEndDate;
            const endFilled = showGuisFields && this.hasChildProjectDateValue(end);
            const guisFilled = showGuisFields && this.hasChildProjectDateValue(guis);

            if (guisFilled && !endFilled) {
                errors.end_date = 'GUIS納期を入力した場合、期限日(実納期)は必須です';
                isValid = false;
            }

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

            if (this.hasChildProjectDateValue(caily) && this.hasChildProjectDateValue(guis)) {
                const cailyDate = this.parseChildProjectDateTime(caily);
                const guisDate = this.parseChildProjectDateTime(guis);
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

        validateChildProjectForm() {
            this.syncChildProjectDateFieldsFromPickers(false);
            this.childProjectValidationErrors = {
                name: '',
                department_id: '',
                start_date: '',
                end_date: '',
                project_order_type: '',
                tantou: '',
                status: '',
                caily_nouki: '',
                guis_nouki: '',
                yotei: '',
                company_name: '',
                branch_name: '',
                contact_name: '',
                guis_receiver: ''
            };

            let isValid = true;

            if (!this.newChildProject.name || this.newChildProject.name.trim() === '') {
                this.childProjectValidationErrors.name = '案件名は必須です';
                isValid = false;
            }

            // Check department_id - handle both string and number types
            const departmentId = this.newChildProject.department_id;
            if (!departmentId || departmentId === '' || departmentId === null || departmentId === undefined) {
                this.childProjectValidationErrors.department_id = '部署は必須です';
                isValid = false;
            }

            // Validate that start date is before end date (only when both are filled)
            if (this.newChildProject.start_date && this.newChildProject.end_date) {
                const startDate = this.parseChildProjectDateTime(this.newChildProject.start_date);
                const endDate = this.parseChildProjectDateTime(this.newChildProject.end_date);
                
                if (startDate >= endDate) {
                    this.childProjectValidationErrors.end_date = '期限日は開始日より後である必要があります';
                    isValid = false;
                }
            }

            // Validate project order type is required
            if (!this.newChildProject.project_order_type || this.newChildProject.project_order_type.trim() === '') {
                this.childProjectValidationErrors.project_order_type = '受注形態は必須です';
                isValid = false;
            }

            // Validate tantou (担当) is required
            if (!this.newChildProject.tantou || this.newChildProject.tantou.trim() === '') {
                this.childProjectValidationErrors.tantou = '担当は必須です';
                isValid = false;
            }

            // Validate status (ステータス) is required
            if (!this.newChildProject.status || this.newChildProject.status.trim() === '') {
                this.childProjectValidationErrors.status = 'ステータスを選択してください';
                isValid = false;
            }

            if (!this.validateChildProjectNoukiFields(this.newChildProject, this.childProjectValidationErrors)) {
                isValid = false;
            }

            if (!this.validateChildProjectCustomer(this.newChildProject, this.childProjectValidationErrors)) {
                isValid = false;
            }

            if (!this.validateChildProjectGuisReceiver(this.newChildProject, this.childProjectValidationErrors)) {
                isValid = false;
            }

            if (!this.newChildProject.yotei) {
                this.newChildProject.yotei = this.emptyYoteiModel();
            }
            if (typeof window.YoteiField !== 'undefined' && !window.YoteiField.isValid(this.newChildProject.yotei)) {
                this.childProjectValidationErrors.yotei = '予定工程の期間が正しくありません';
                isValid = false;
            }

            return isValid;
        },

        notifyChildProjectValidationError() {
            showMessage('入力内容にエラーがあります。内容をご確認ください。', true);
        },

        async createChildProject() {
            if (!this.validateChildProjectForm()) {
                this.notifyChildProjectValidationError();
                return;
            }

            this.creatingChildProject = true;

            try {
                // Sync Quill content to form data
                if (this.createChildProjectQuillInstance) {
                    this.createChildProjectQuillContent = this.createChildProjectQuillInstance.getSemanticHTML();
                    this.newChildProject.description = this.createChildProjectQuillContent;
                }

                // Sync Tagify values to model before submit (管理, チーム, メンバー) — chỉ gửi id số để backend lưu đúng
                if (this.createChildProjectManagerTagify) {
                    const raw = this.createChildProjectManagerTagify.value.map(t => (t.id != null ? t.id : t.value)).filter(Boolean);
                    this.newChildProject.managers = raw.map(v => String(v)).filter(id => /^\d+$/.test(id));
                }
                if (this.createChildProjectTeamTagify) {
                    this.newChildProject.teams = this.createChildProjectTeamTagify.value.map(t => String(t.id != null ? t.id : t.value)).join(',') || '';
                }
                if (this.createChildProjectMembersTagify) {
                    this.newChildProject.members = this.createChildProjectMembersTagify.value.map(t => String(t.id != null ? t.id : t.value)).filter(Boolean);
                }

                const formData = new FormData();
                formData.append('name', this.newChildProject.name);
                formData.append('department_id', this.newChildProject.department_id);
                formData.append('project_number', '');
                formData.append('description', this.newChildProject.description || '');
                formData.append('start_date', this.toChildProjectAPIDate(this.newChildProject.start_date));
                formData.append('end_date', this.toChildProjectAPIDate(this.newChildProject.end_date));
                formData.append('project_order_type', this.newChildProject.project_order_type || '');
                // Luôn gửi managers (kể cả rỗng) để backend lưu đúng
                formData.append('managers', (this.newChildProject.managers && this.newChildProject.managers.length > 0) ? this.newChildProject.managers.join(',') : '');
                formData.append('parent_project_id', this.newChildProject.parent_project_id);
                formData.append('progress', this.newChildProject.progress != null ? parseInt(this.newChildProject.progress, 10) : 0);
                formData.append('teams', this.newChildProject.teams || '');
                if (this.newChildProject.members && this.newChildProject.members.length > 0) {
                    formData.append('members', Array.isArray(this.newChildProject.members) ? this.newChildProject.members.join(',') : String(this.newChildProject.members));
                }
                // 総額: 必ず数値として送信（NaN/空の場合は 0）
                const amountVal = this.newChildProject.amount;
                const amountNum = (typeof amountVal === 'number' && !Number.isNaN(amountVal)) ? amountVal : (parseFloat(amountVal) || 0);
                formData.append('amount', String(amountNum));
                formData.append('tantou', this.newChildProject.tantou || '');
                formData.append('caily_nouki', this.toChildProjectAPIDate(this.newChildProject.caily_nouki) || '');
                formData.append('guis_nouki', this.toChildProjectAPIDate(this.newChildProject.guis_nouki) || '');
                this.appendYoteiToFormData(formData, this.newChildProject.yotei);

                formData.append('is_kadai', '0');
                formData.append('status', this.newChildProject.status || 'draft');
                formData.append('customer_id', this.resolveChildProjectCustomerId(this.newChildProject));
                const childGuisReceiver = this.resolveChildProjectGuisReceiver(this.newChildProject);
                if (childGuisReceiver) {
                    formData.append('guis_receiver', childGuisReceiver);
                }

                const createCustomFields = this.collectChildProjectCustomFields('createChildProjectCustomFieldsWrap', 'createChildProjectCustomField', 'createChildProjectCustomInput', 'createChildProjectCustomCheckbox', 'createChildProjectCustomRadio');
                if (createCustomFields.length) formData.append('custom_fields', JSON.stringify(createCustomFields));

                const response = await axios.post('/api/index.php?model=project&method=create', formData);

                if (response.data && (response.data.success || response.data.status === 'success')) {
                    Swal.fire({
                        title: '成功',
                        text: '課題を作成しました。',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        // Close modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('createChildProjectModal'));
                        if (modal) {
                            modal.hide();
                        }
                        
                        // Reload child projects
                        this.loadChildProjects();
                        
                        // Reset form
                        this.resetChildProjectForm();
                    });
                } else {
                    showParentProjectError(response.data?.error || response.data?.message || '課題の作成に失敗しました。', response && response.data);
                }
            } catch (error) {
                console.error('Error creating child project:', error);
                showParentProjectError('課題の作成に失敗しました。', error);
            } finally {
                this.creatingChildProject = false;
            }
        },

        // Quotation methods
        async loadQuotations() {
            try {
                const response = await axios.get(`/api/index.php?model=quotation&method=getByParentProject&parent_project_id=${PARENT_PROJECT_ID}`);
                if (response.data && Array.isArray(response.data)) {
                    this.quotations = response.data;
                } else {
                    this.quotations = [];
                }
            } catch (error) {
                console.error('Error loading quotations:', error);
                this.quotations = [];
            }
        },

        async loadQuotationBranches() {
            try {
                const response = await axios.get('/api/index.php?model=branch&method=list');
                if (response.data && Array.isArray(response.data)) {
                    this.quotationBranches = response.data;
                    // Set default selection to first branch if available
                    if (this.quotationBranches.length > 0 && !this.newQuotation.selected_branch_id) {
                        this.newQuotation.selected_branch_id = this.quotationBranches[0].id;
                        this.onBranchSelect();
                    }
                } else {
                    this.quotationBranches = [];
                }
            } catch (error) {
                console.error('Error loading quotation branches:', error);
                this.quotationBranches = [];
            }
        },

        onBranchSelect() {
            if (this.newQuotation.selected_branch_id) {
                const selectedBranch = this.quotationBranches.find(branch => branch.id == this.newQuotation.selected_branch_id);
                if (selectedBranch) {
                    this.newQuotation.receiver_company = selectedBranch.company_name || selectedBranch.name;
                    // Include postal_code in the address field
                    const addressParts = [];
                    if (selectedBranch.postal_code) {
                        addressParts.push(`〒${selectedBranch.postal_code}`);
                    }
                    if (selectedBranch.address1) {
                        addressParts.push('　');
                        addressParts.push(selectedBranch.address1);
                    }
                    if (selectedBranch.address2) {
                        addressParts.push('\n');
                        addressParts.push(selectedBranch.address2);
                    }
                    this.newQuotation.receiver_address = addressParts.join('');
                    this.newQuotation.receiver_tel = selectedBranch.tel || '';
                    this.newQuotation.receiver_fax = selectedBranch.fax || '';
                    this.newQuotation.receiver_registration_number = selectedBranch.registration_number || '';
                }
            } else {
                // Clear fields if no branch is selected
                this.newQuotation.receiver_company = '';
                this.newQuotation.receiver_address = '';
                this.newQuotation.receiver_tel = '';
                this.newQuotation.receiver_fax = '';
                this.newQuotation.receiver_registration_number = '';
            }
        },

            async loadQuotationUsers() {
        try {
            const response = await axios.get('/api/index.php?model=user&method=searchMembers');
            if (response.data && response.data.status === 'success') {
                this.quotationUsers = response.data.data || [];
                // Set default selection to current user if available
                if (this.quotationUsers.length > 0 && !this.newQuotation.receiver_contact) {
                    const currentUser = this.quotationUsers.find(user => user.userid === CURRENT_USER_ID);
                    if (currentUser) {
                        this.newQuotation.receiver_contact = currentUser.realname;
                        // Load seal for the default user
                        await this.loadContactSeal(currentUser.userid);
                    }
                }
            } else {
                this.quotationUsers = [];
            }
        } catch (error) {
            console.error('Error loading quotation users:', error);
            this.quotationUsers = [];
        }
    },

   async loadContactSeal(userId) {
        try {
            const response = await axios.get(`/api/index.php?model=seal&method=getSealsByUser&user_id=${userId}`);
            if (response.data && response.data.length > 0) {
                // Get the first active seal for this user
                this.selectedContactSeal = response.data[0];
            } else {
                this.selectedContactSeal = null;
            }
        } catch (error) {
            console.error('Error loading contact seal:', error);
            this.selectedContactSeal = null;
        }
    },

    async onContactSelect() {
        // Clear previous seal
        this.selectedContactSeal = null;
        
        if (!this.newQuotation.receiver_contact) {
            return;
        }
        
        // Find the selected user to get their userid
        const selectedUser = this.quotationUsers.find(user => user.realname === this.newQuotation.receiver_contact);
        if (!selectedUser) {
            return;
        }
        
        // Load seal for the selected user
        await this.loadContactSeal(selectedUser.userid);
        
        // Save seal path to quotation data
        if (this.selectedContactSeal && this.selectedContactSeal.image_path) {
            this.newQuotation.receiver_seal_path = this.selectedContactSeal.image_path;
        } else {
            this.newQuotation.receiver_seal_path = '';
        }
    },

        async showCreateQuotationModal() {
            // Clear any existing validation errors
            this.quotationValidationErrors = {};
            
            if (this.quotationFormBackup) {
                // Restore previous form data if available, otherwise reset
                this.newQuotation = JSON.parse(JSON.stringify(this.quotationFormBackup));
                
                // Ensure all items have _oldProjectId property initialized and product_code is empty for set products
                if (this.newQuotation.items && this.newQuotation.items.length > 0) {
                    this.newQuotation.items.forEach(item => {
                        if (!item.hasOwnProperty('_oldProjectId')) {
                            item._oldProjectId = item.project_id || '';
                        }
                        // Allow product_code to be freely entered even for set products
                        // Removed the restriction that was clearing product_code for set products
                    });
                }
                
                // Restore child project selection
                if (this.quotationFormBackup.selectedChildProjectIds) {
                    this.selectedChildProjectIds = [...this.quotationFormBackup.selectedChildProjectIds];
                    this.updateChildProjectSelection();
                }
            } else {
                await this.resetQuotationForm();
            }
            
            const modal = new bootstrap.Modal(document.getElementById('createQuotationModal'));
            modal.show();
            
            // Initialize Flatpickr for quotation date fields after modal is shown
            this.$nextTick(() => {
                this.initializeQuotationDatePickers();
                // Load branches if not already loaded
                if (this.quotationBranches.length === 0) {
                    this.loadQuotationBranches();
                }
                // Load users if not already loaded
                if (this.quotationUsers.length === 0) {
                    this.loadQuotationUsers();
                }
            });
        },

        generateQuotationNumber() {
            // Generate quotation number in format G-1A2C3D
            // G-4A2C3D where:
            // 4 = last digit of year (2024 → 4)
            // A = month as letter (1=A, 2=B, 3=C, ..., 12=L)
            // 2 = day (01-31)
            // C = hour as letter (0=A, 1=B, 2=C, ..., 23=X)
            // 3 = minute (00-59)
            // D = second (00-59)
            const now = new Date();
            const year = now.getFullYear().toString().slice(-2); // Last digit of year
            const month = (now.getMonth() + 1).toString().padStart(2, '0');
            const day = now.getDate().toString().padStart(2, '0');
            const hour = now.getHours().toString().padStart(2, '0');
            const minute = now.getMinutes().toString().padStart(2, '0');
            const second = now.getSeconds().toString().padStart(2, '0');
            
            // Convert numbers to letters (1=A, 2=B, 3=C, etc.)
            const monthLetter = String.fromCharCode(64 + parseInt(month)); // 1=A, 2=B, 3=C, etc.
            const dayLetter = String.fromCharCode(64 + parseInt(day)); // 1=A, 2=B, 3=C, etc.
            const hourLetter = String.fromCharCode(65 + parseInt(hour)); // 0=A, 1=B, 2=C, etc.
            
            return `G-${year}${monthLetter}${dayLetter}${hourLetter}${minute}${second}`;
        },

        async resetQuotationForm() {
            // Load customer data to get address
            const customer = await this.loadCustomerDataByProject();
            let customerAddress = '';
            if (customer) {
                const zip = customer.zip ? `〒${customer.zip}　` : '';
                const address1 = customer.address1 || '';
                const address2 = '\n' + customer.address2 || '';
                customerAddress = `${zip}${address1}${address2}`.trim();
            }

            this.newQuotation = {
                issue_date: new Date().toISOString().split('T')[0],
                quotation_number: this.generateQuotationNumber(),
                sender_company: this.parentProject?.company_name || '',
                sender_address: customerAddress,
                sender_contact: this.parentProject?.contact_name ? this.parentProject.contact_name + '様' : '',
                selected_branch_id: '',
                receiver_company: '',
                receiver_address: '',
                receiver_contact: CURRENT_USER_NAME || '',
                receiver_seal_path: '',
                receiver_tel: '',
                receiver_fax: '',
                receiver_registration_number: '',
                status: '下書き',
                items: [], // Ensure this is always an empty array
                total_amount: 0,
                tax_rate: 10, // Initialized
                total_with_tax: 0,
                delivery_date: '御打ち合わせの上', // Set default value
                delivery_location: '貴社指定場所', // Initialized
                payment_method: '電子納品', // Initialized
                valid_until_type: '1_month', // Initialized
                valid_until: '',
                subject: '',
                notes: '',
                parent_project_id: PARENT_PROJECT_ID
            };
            this.selectedContactSeal = null;
            this.quotationValidationErrors = {}; // Clear validation errors
            
            // Reset child project selection
            this.selectedChildProjectIds = [];
            this.allChildProjectsSelected = false;
            this.someChildProjectsSelected = false;
            
            // Reset child project total amounts
            this.childProjects.forEach(project => {
                project.total_amount = 0;
            });
            
            this.loadQuotationBranches();
            this.loadQuotationUsers();
            this.onValidUntilTypeChange(); // Calculate initial valid_until date
        },

        addOrderItem() {
            const newItem = {
                project_id: '', // Add project_id field
                _oldProjectId: '', // Track previous project_id for proper total calculation
                title: '',
                product_code: '',
                type: '',
                quantity: 1,
                unit: '枚',
                unit_price: 0,
                amount: 0,
                notes: ''
            };
            
            // Auto-select project if only one option available
            this.autoSelectSingleProjectOption(newItem, false);
            
            // Use spread operator to ensure reactivity
            this.newQuotation.items = [...this.newQuotation.items, newItem];
            // Clear items validation error when items are added
            delete this.quotationValidationErrors.items;
            
            // Validate project_id selection after adding new item
            this.$nextTick(() => {
                this.validateProjectIdSelection();
            });
        },

        quickSelectProjectNumber(projectId) {
            // Find the first order item that doesn't have a project_id set
            const firstUnassignedItem = this.newQuotation.items.find(item => !item.project_id);
            
            if (firstUnassignedItem) {
                // Set the project_id for the first unassigned item
                firstUnassignedItem.project_id = projectId;
                firstUnassignedItem._oldProjectId = projectId;
                
                // Update the project total amount
                this.updateChildProjectTotalAmount(projectId);
                
                // Calculate the item amount
                const itemIndex = this.newQuotation.items.indexOf(firstUnassignedItem);
                if (itemIndex !== -1) {
                    this.calculateItemAmount(itemIndex);
                }
                
                showMessage(`プロジェクト番号 "${this.getProjectDisplayName(projectId)}" が設定されました。`, false);
                // Validate project_id selection after update
                this.validateProjectIdSelection();
            } else {
                // If all items have project_id, show a message
                showMessage('すべての商品明細にプロジェクト番号が設定されています。新しい商品明細を追加してください。', true);
            }
        },

        quickSelectProjectNumberForCheckedItems(projectId) {
            // Check if there are any selected items
            if (this.selectedOrderItemIndexes.length === 0) {
                showMessage('チェックされた商品がありません。先に商品を選択してください。', true);
                return;
            }

            let updatedCount = 0;
            const projectDisplayName = this.getProjectDisplayName(projectId);

            // Update only the checked items
            this.selectedOrderItemIndexes.forEach(index => {
                if (index >= 0 && index < this.newQuotation.items.length) {
                    const item = this.newQuotation.items[index];
                    const oldProjectId = item.project_id;
                    
                    // Set the project_id for the checked item
                    item.project_id = projectId;
                    item._oldProjectId = projectId;
                    
                    // Update the project total amount
                    this.updateChildProjectTotalAmount(projectId);
                    
                    // If the item had a different project_id before, update that project's total too
                    if (oldProjectId && oldProjectId !== projectId) {
                        this.updateChildProjectTotalAmount(oldProjectId);
                    }
                    
                    // Calculate the item amount
                    this.calculateItemAmount(index);
                    
                    updatedCount++;
                }
            });

            if (updatedCount > 0) {
                showMessage(`プロジェクト番号 "${projectDisplayName}" が ${updatedCount} 件のチェック済み商品に設定されました。`, false);
                // Validate project_id selection after update
                this.validateProjectIdSelection();
            } else {
                showMessage('チェックされた商品の更新に失敗しました。', true);
            }
        },

        clearProjectNumbersForCheckedItems() {
            // Check if there are any selected items
            if (this.selectedOrderItemIndexes.length === 0) {
                showMessage('チェックされた商品がありません。先に商品を選択してください。', true);
                return;
            }

            let updatedCount = 0;

            // Clear project_id for all checked items
            this.selectedOrderItemIndexes.forEach(index => {
                if (index >= 0 && index < this.newQuotation.items.length) {
                    const item = this.newQuotation.items[index];
                    const oldProjectId = item.project_id;
                    
                    // Clear the project_id for the checked item
                    item.project_id = '';
                    item._oldProjectId = '';
                    
                    // If the item had a project_id before, update that project's total
                    if (oldProjectId) {
                        this.updateChildProjectTotalAmount(oldProjectId);
                    }
                    
                    updatedCount++;
                }
            });

            if (updatedCount > 0) {
                showMessage(`${updatedCount} 件のチェック済み商品のプロジェクト番号がクリアされました。`, false);
                // Validate project_id selection after clearing
                this.validateProjectIdSelection();
            } else {
                showMessage('チェックされた商品の更新に失敗しました。', true);
            }
        },

        getProjectDisplayName(projectId) {
            const project = this.childProjects.find(p => p.id == projectId);
            return project ? (project.project_number || project.name) : `ID: ${projectId}`;
        },

        removeOrderItem(index) {
            const itemToRemove = this.newQuotation.items[index];
            const projectId = itemToRemove ? itemToRemove.project_id : null;
            
            // Use spread operator to ensure reactivity
            this.newQuotation.items = this.newQuotation.items.filter((_, i) => i !== index);
            
            this.calculateTotalAmount();
            
            // Update project total amount if the removed item had a project_id
            if (projectId) {
                this.updateChildProjectTotalAmount(projectId);
            }
            
            // Clean up selection after removal
            this.selectedOrderItemIndexes = this.selectedOrderItemIndexes
                .filter(i => i !== index)
                .map(i => (i > index ? i - 1 : i));
        },

        calculateItemAmount(index) {
            const item = this.newQuotation.items[index];
            
            if (item) {
                const quantity = parseFloat(item.quantity) || 0;
                const unitPrice = parseFloat(item.unit_price) || 0;
                const amount = quantity * unitPrice;
                
                item.amount = amount;
                
                // Update project total amount if project_id is set
                if (item.project_id) {
                    this.updateChildProjectTotalAmount(item.project_id);
                }
            }
            
            this.calculateTotalAmount();
        },

        calculateTotalAmount() {
            const total = this.newQuotation.items.reduce((sum, item) => {
                const amount = parseFloat(item.amount) || 0;
                return sum + amount;
            }, 0);
            
            this.newQuotation.total_amount = total;
            this.newQuotation.total_with_tax = this.newQuotation.total_amount * (1 + (this.newQuotation.tax_rate || 0) / 100);
        },

        onValidUntilTypeChange() {
            const issueDate = new Date(this.newQuotation.issue_date);
            let validUntilDate = new Date(issueDate);
            
            switch (this.newQuotation.valid_until_type) {
                case '1_week':
                    validUntilDate.setDate(issueDate.getDate() + 7);
                    break;
                case '1_month':
                    validUntilDate.setMonth(issueDate.getMonth() + 1);
                    break;
                case 'custom':
                    // Keep the existing valid_until value if it exists
                    if (!this.newQuotation.valid_until) {
                        this.newQuotation.valid_until = '';
                    }
                    return; // Don't update the date for custom
                default:
                    return;
            }
            
            this.newQuotation.valid_until = validUntilDate.toISOString().split('T')[0];
        },



        openDeliveryDatePicker() {
            const deliveryDatePickerEl = document.getElementById('quotation_delivery_date_picker');
            if (deliveryDatePickerEl && deliveryDatePickerEl._flatpickr) {
                deliveryDatePickerEl._flatpickr.open();
            }
        },

        clearDeliveryDate() {
            this.newQuotation.delivery_date = '御打ち合わせの上';
        },

        openDeliveryDatePickerForEdit() {
            const deliveryDatePickerEl = document.getElementById('edit_quotation_delivery_date_picker');
            if (deliveryDatePickerEl && deliveryDatePickerEl._flatpickr) {
                deliveryDatePickerEl._flatpickr.open();
            }
        },

        clearDeliveryDateForEdit() {
            this.editingQuotation.delivery_date = '御打ち合わせの上';
        },

        onDeliveryDateInput(event) {
            const value = event.target.value;
            // If the user clears the field, set it to default value
            if (!value || value.trim() === '') {
                this.newQuotation.delivery_date = '御打ち合わせの上';
            }
        },

        checkItemsReady() {
            if (!this.newQuotation.items || !Array.isArray(this.newQuotation.items) || this.newQuotation.items.length === 0) {
                showMessage('商品明細が追加されていません。先に商品を選択してください。', true);
                return false;
            }
            
            return true;
        },

        async createQuotationWithDelay() {
            // Add a small delay to ensure Vue.js has updated the array
            await new Promise(resolve => setTimeout(resolve, 200));
            
            // Now call the original createQuotation function
            await this.createQuotation();
        },

        async createQuotation() {
            // Check if items are ready before proceeding
            if (!this.checkItemsReady()) {
                return;
            }
            if (!this.validateQuotationForm()) {
                return;
            }

            this.creatingQuotation = true;
            // Clear previous validation errors
            this.quotationValidationErrors = {};

            try {
                const formData = new FormData();
                

                
                // Add quotation data - send all fields including empty ones
                Object.keys(this.newQuotation).forEach(key => {
                    if (key === 'items') {
                        // Ensure items exist and are not empty before processing
                        if (!this.newQuotation[key] || !Array.isArray(this.newQuotation[key]) || this.newQuotation[key].length === 0) {
                            console.error('DEBUG: Items are empty or invalid when trying to send!');
                            formData.append(key, JSON.stringify([]));
                            return;
                        }
                        
                        // Create a deep copy of items and properly handle set_json
                        const itemsCopy = this.newQuotation[key].map(item => {
                                const itemCopy = { ...item };
                                
                            if (item.is_set && item.set_json) {
                                // Handle set_json properly - convert to object if it's a string, then back to object for proper JSON encoding
                                if (typeof item.set_json === 'string') {
                                    try {
                                        // Parse the JSON string to object so it gets properly encoded when the whole item is stringified
                                        itemCopy.set_json = JSON.parse(item.set_json);
                                    } catch (e) {
                                        console.error('Error parsing set_json:', e, item.set_json);
                                        // If parsing fails, keep as string but log error
                                        itemCopy.set_json = item.set_json;
                                    }
                                } else if (typeof item.set_json === 'object') {
                                    // Already an object, keep as is
                                    itemCopy.set_json = item.set_json;
                                } else {
                                    // Unknown type, convert to string first then parse
                                    try {
                                        itemCopy.set_json = JSON.parse(String(item.set_json));
                                    } catch (e) {
                                        itemCopy.set_json = item.set_json;
                                    }
                                }
                            }
                            
                            return itemCopy;
                        });
                        
                        const itemsJson = JSON.stringify(itemsCopy);
                        formData.append(key, itemsJson);
                        
                        // Validate the generated JSON
                        try {
                            JSON.parse(itemsJson);
                        } catch (e) {
                            console.error('Generated items JSON is invalid:', e);
                        }
                    } else {
                        // Send all fields, including empty strings and null values
                        const value = this.newQuotation[key] !== null ? this.newQuotation[key] : '';
                        formData.append(key, value);
                    }
                });
                
                // Add selected child project IDs
                if (this.selectedChildProjectIds && this.selectedChildProjectIds.length > 0) {
                    this.selectedChildProjectIds.forEach((id, index) => {
                        formData.append(`selected_child_project_ids[${index}]`, id);
                    });
                }
                
                // Add updated_by field
                formData.append('updated_by', CURRENT_USER_NAME);
                
                // Debug: Check if items is actually in FormData
                const itemsEntry = formData.get('items');
                
                // Debug: Try to parse items back to see if it's valid JSON
                try {
                    const parsedItems = JSON.parse(itemsEntry);
                } catch (parseError) {
                    console.error('Error parsing items from FormData:', parseError);
                }
              

                

                
                const response = await axios.post('/api/index.php?model=quotation&method=create', formData);
                
                if (response.data && response.data.status === 'success') {
                    // Clear backup and close modal
                    this.quotationFormBackup = null;
                    const modal = bootstrap.Modal.getInstance(document.getElementById('createQuotationModal'));
                    modal.hide();
                    
                    // Update selected child projects status based on quotation status
                    // if (this.selectedChildProjectIds.length > 0) {
                    //     await this.updateSelectedChildProjectsStatus();
                    // }
                    
                    // Update child project amounts based on quotation status
                    if (this.newQuotation.status === 'キャンセル' || this.newQuotation.status === '却下') {
                        // Set project amounts to 0 for cancelled or rejected quotations
                        await this.resetChildProjectAmountsFromItems(this.newQuotation.items);
                    } else {
                        // Update project amounts from quotation items for other statuses
                        await this.updateChildProjectAmountsAfterQuotation();
                    }
                    
                    // Reload child projects to show updated amounts
                    await this.loadChildProjects();
                    
                    // Reload quotations
                    await this.loadQuotations();
                    
                    // Reset the quotation form after successful creation
                    this.resetQuotationForm();
                    
                    showMessage('見積書を作成しました', false);
                } else {
                    // Handle field-level validation errors
                    if (response.data && response.data.errors) {
                        this.quotationValidationErrors = response.data.errors;
                    } else {
                        showParentProjectError(response.data?.message || 'エラーが発生しました', response && response.data);
                    }
                }
            } catch (error) {
                console.error('Error creating quotation:', error);
                if (error.response) {
                    console.error('Error response data:', error.response.data);
                }
                showParentProjectError('エラーが発生しました', error);
            } finally {
                this.creatingQuotation = false;
            }
        },

        backupQuotationForm() {
            // Backup current form data before closing
            this.quotationFormBackup = JSON.parse(JSON.stringify(this.newQuotation));
            // Also backup child project selection
            this.quotationFormBackup.selectedChildProjectIds = [...this.selectedChildProjectIds];
        },

        async clearQuotationFormBackup() {
            // Clear backup and reset form
            this.quotationFormBackup = null;
            await this.resetQuotationForm();
        },
        
        destroyQuotationDatePickers() {
            // Destroy issue date picker
            const issueDateEl = document.getElementById('quotation_issue_date');
            if (issueDateEl && issueDateEl._flatpickr) {
                issueDateEl._flatpickr.destroy();
            }
            
            // Destroy delivery date picker (hidden input)
            const deliveryDatePickerEl = document.getElementById('quotation_delivery_date_picker');
            if (deliveryDatePickerEl && deliveryDatePickerEl._flatpickr) {
                deliveryDatePickerEl._flatpickr.destroy();
            }
            
            // Destroy valid until date picker
            const validUntilEl = document.getElementById('quotation_valid_until');
            if (validUntilEl && validUntilEl._flatpickr) {
                validUntilEl._flatpickr.destroy();
            }
        },

        validateQuotationForm() {
            let isValid = true;
            
            // Clear previous validation errors completely
            this.quotationValidationErrors = {};
            
            if (!this.newQuotation.issue_date) {
                this.quotationValidationErrors.issue_date = '発行日は必須です';
                isValid = false;
            }
            
            if (!this.newQuotation.quotation_number) {
                this.quotationValidationErrors.quotation_number = '見積番号は必須です';
                isValid = false;
            }
            
            if (!this.newQuotation.sender_company) {
                this.quotationValidationErrors.sender_company = '発注者会社名は必須です';
                isValid = false;
            }
            
            if (!this.newQuotation.receiver_company) {
                this.quotationValidationErrors.receiver_company = '受注者会社名は必須です';
                isValid = false;
            }
            
            // Validate subject
            if (!this.newQuotation.subject || this.newQuotation.subject.trim() === '') {
                this.quotationValidationErrors.subject = '件名は必須です';
                isValid = false;
            }
            

            
            // Items validation - only check if items exist
            if (!this.newQuotation.items || !Array.isArray(this.newQuotation.items) || this.newQuotation.items.length === 0) {
                this.quotationValidationErrors.items = '商品明細は必須です';
                isValid = false;
            } else {
                // Clear items error if validation passes
                delete this.quotationValidationErrors.items;
                
                // Validate that all items have project_id
                const itemsWithoutProjectId = this.newQuotation.items.filter((item, index) => {
                    return !item.project_id || item.project_id === '' || item.project_id === null;
                });
                
                if (itemsWithoutProjectId.length > 0) {
                    this.quotationValidationErrors.items = 'すべての商品明細に案件番号を選択してください';
                    isValid = false;
                }
            }
            
            // Validate child project selection
            if (!this.selectedChildProjectIds || this.selectedChildProjectIds.length === 0) {
                this.quotationValidationErrors.childProjects = '子プロジェクトの選択は必須です';
                isValid = false;
            }
            
            // Delivery date is optional - user can input freely or use date picker
            
            // Validate delivery location
            if (!this.newQuotation.delivery_location || this.newQuotation.delivery_location.trim() === '') {
                this.quotationValidationErrors.delivery_location = '納入場所は必須です';
                isValid = false;
            }
            
            // Validate payment method
            if (!this.newQuotation.payment_method || this.newQuotation.payment_method.trim() === '') {
                this.quotationValidationErrors.payment_method = '取引方法は必須です';
                isValid = false;
            }
            
            // Validate valid until
            if (!this.newQuotation.valid_until || this.newQuotation.valid_until.trim() === '') {
                this.quotationValidationErrors.valid_until = '有効期限は必須です';
                isValid = false;
            }


            
            // Show SweetAlert2 notification if there are validation errors
            if (!isValid) {
                const errorMessages = Object.values(this.quotationValidationErrors).filter(error => error !== undefined && error !== null && error !== '');
                const errorList = errorMessages.map(error => `• ${error}`).join('<br>');
                
                Swal.fire({
                    title: '入力エラー',
                    html: `以下の項目を確認してください：<br><br>${errorList}`,
                    icon: 'error',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#d33'
                });
            }
            
            return isValid;
        },

        validateProjectIdSelection() {
            // Validate project IDs for all items
            if (this.newQuotation.items && Array.isArray(this.newQuotation.items) && this.newQuotation.items.length > 0) {
                const itemsWithoutProjectId = this.newQuotation.items.filter((item) => {
                    return !item.project_id || item.project_id === '' || item.project_id === null;
                });
                
                if (itemsWithoutProjectId.length > 0) {
                    this.quotationValidationErrors.items = 'すべての商品明細に案件番号を選択してください';
                } else {
                    // Clear error if all items have project_id
                    if (this.quotationValidationErrors.items && this.quotationValidationErrors.items.includes('案件番号')) {
                        delete this.quotationValidationErrors.items;
                    }
                }
            }
        },

        showQuotationModal(quotation) {
            // Create a copy with timestamp to force iframe reload
            this.selectedQuotation = {
                ...quotation,
                timestamp: Date.now()
            };
            const modal = new bootstrap.Modal(document.getElementById('viewQuotationModal'));
            modal.show();
        },

        editQuotation(quotation) {
            // TODO: Implement edit functionality
            showMessage('編集機能は準備中です', true);
        },

        async deleteQuotation(quotation) {
            try {
                const result = await Swal.fire({
                    title: '確認',
                    text: `「${quotation.quotation_number}」を削除しますか？`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: '削除',
                    cancelButtonText: 'キャンセル'
                });
                
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('id', quotation.id);
                    
                    const response = await axios.post('/api/index.php?model=quotation&method=delete', formData);
                    
                                    if (response.data && response.data.status === 'success') {
                    // Update child project amounts after deletion (reset to 0 for affected projects)
                    await this.resetChildProjectAmountsAfterQuotationDeletion(quotation);
                    
                    // Reload child projects to show updated amounts
                    await this.loadChildProjects();
                    
                    await this.loadQuotations();
                    showMessage('見積書を削除しました', false);
                } else {
                    showParentProjectError(response.data?.message || 'エラーが発生しました', response && response.data);
                }
                }
            } catch (error) {
                console.error('Error deleting quotation:', error);
                showParentProjectError('エラーが発生しました', error);
            }
        },

        // Open quotation in new tab for printing
        openQuotationInNewTab() {
            if (this.selectedQuotation && this.selectedQuotation.id) {
                const url = `quotation_view.php?id=${this.selectedQuotation.id}`;
                window.open(url, '_blank');
            }
        },

        // Download PDF directly using html2pdf library
        async downloadQuotationPDF() {
            if (this.selectedQuotation && this.selectedQuotation.id) {
                try {
                    // First try to access iframe function
                    const iframe = document.querySelector('#viewQuotationModal iframe');
                    if (iframe && iframe.contentWindow && iframe.contentWindow.downloadPDF) {
                        iframe.contentWindow.downloadPDF();
                        return;
                    }
                } catch (error) {
                    console.log('Iframe method failed, trying alternative approach');
                }
                
                // Alternative: Load html2pdf and generate PDF from new window
                const quotationWindow = window.open(`quotation_view.php?id=${this.selectedQuotation.id}`, '_blank');
                
                // Wait a bit for the window to load, then trigger PDF download
                setTimeout(() => {
                    try {
                        if (quotationWindow && quotationWindow.downloadPDF) {
                            quotationWindow.downloadPDF();
                        }
                    } catch (error) {
                        console.log('Auto PDF download failed, user can use the PDF button in the new window');
                    }
                }, 2000);
            }
        },

        // Legacy print function (for backward compatibility)
        printQuotation() {
            this.openQuotationInNewTab();
        },

        // Legacy export function (for backward compatibility)
        exportQuotationPDF() {
            this.downloadQuotationPDF();
        },

        formatJapaneseDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            const year = date.getFullYear();
            const month = date.getMonth() + 1;
            const day = date.getDate();
            
            // Convert to Japanese era (Reiwa)
            const reiwaYear = year - 2018;
            return `令和${reiwaYear}年${month}月${day}日`;
        },

        formatNumber(number) {
            // Take only the integer part of the number
            const integerNumber = Math.floor(number);
            return new Intl.NumberFormat('ja-JP').format(integerNumber);
        },

        // Update quotation status
        async updateQuotationStatus(quotationId, status) {
            try {
                const formData = new FormData();    
                formData.append('quotation_id', quotationId);
                formData.append('status', status);
                formData.append('updated_by', CURRENT_USER_NAME);
                const response = await axios.post('/api/index.php?model=quotation&method=updateStatus', formData);
                
                if (response.data && response.data.status === 'success') {
                    // Update the local quotation data
                    const quotation = this.quotations.find(q => q.id === quotationId);
                    if (quotation) {
                        quotation.status = status;
                    }
                    
                    // Reload quotations to get updated data
                    await this.loadQuotations();
                    
                    // Note: Backend API already updates project amounts automatically
                    // We only need to reload child projects to show the updated amounts
                    // Frontend update is optional and only for immediate UI feedback
                    // Reload child projects to show updated amounts (this ensures we have the latest data from backend)
                    await this.loadChildProjects();
                    
                    showMessage('見積書のステータスが更新され、関連プロジェクトの金額も更新されました。', false);
                } else {
                    showMessage('ステータスの更新に失敗しました。', true);
                }
            } catch (error) {
                console.error('Error updating quotation status:', error);
                showMessage('ステータスの更新中にエラーが発生しました。', true);
            }
        },

        // Get CSS class for quotation status badge
        getQuotationStatusBadgeClass(status) {
            switch (status) {
                case '下書き':
                    return 'bg-draft';
                case '発行済み':
                    return 'bg-issued';
                case '承認済み':
                    return 'bg-approved';
                case '却下':
                    return 'bg-rejected';
                case '調整':
                    return 'bg-adjustment';
                case 'キャンセル':
                    return 'bg-danger';
                default:
                    return 'bg-draft';
            }
        },

        // Get CSS class for quotation status button
        getStatusButtonClass(status) {
            switch (status) {
                case '下書き':
                    return 'btn-secondary';
                case '発行済み':
                    return 'btn-primary';
                case '承認済み':
                    return 'btn-success';
                case '却下':
                    return 'btn-danger';
                case '調整':
                    return 'btn-warning';
                case 'キャンセル':
                    return 'btn-danger';
                default:
                    return 'btn-secondary';
            }
        },

        formatPrice(price) {
            // Take only the integer part of the price
            const integerPrice = Math.floor(price);
            return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(integerPrice);
        },

        normalizeBusinessDocumentStatus(status, fallback) {
            if (!status || status === '発行済み') {
                return status === '発行済み' ? '発行済' : (fallback || '未発行');
            }
            return status;
        },
        normalizeBusinessDocumentStatusValue(status) {
            if (status === '発行済み') return '発行済';
            return status;
        },
        isBusinessDocumentReadyForCompletion(status) {
            const normalized = this.normalizeBusinessDocumentStatusValue(status);
            return normalized === '発行済' || normalized === '無償';
        },
        needsPaymentInfoBeforeComplete(project) {
            const p = project || this.editingChildProject;
            if (!p) return true;
            return !this.isBusinessDocumentReadyForCompletion(p.estimate_status)
                || !this.isBusinessDocumentReadyForCompletion(p.invoice_status);
        },
        getPaymentInfoBeforeCompleteMessage(project) {
            const p = project || this.editingChildProject;
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
            await Swal.fire({
                icon: 'warning',
                title: this.translateLabel('決済情報が未設定です'),
                text: this.getPaymentInfoBeforeCompleteMessage(project),
                confirmButtonText: this.translateLabel('OK')
            });
            return false;
        },
        findBusinessDocumentStatusOption(list, status) {
            const normalized = this.normalizeBusinessDocumentStatus(status, '');
            return list.find((item) => item.value === normalized || item.value === status);
        },
        getBusinessEstimateStatusLabel(status) {
            const item = this.findBusinessDocumentStatusOption(BUSINESS_ESTIMATE_STATUSES, status);
            return item ? this.translateLabel(item.label) : this.translateLabel('未発行');
        },
        getBusinessEstimateStatusBadgeClass(status) {
            const item = this.findBusinessDocumentStatusOption(BUSINESS_ESTIMATE_STATUSES, status);
            return item ? `bg-${item.color}` : 'bg-secondary';
        },
        getBusinessInvoiceStatusLabel(status) {
            const item = this.findBusinessDocumentStatusOption(BUSINESS_INVOICE_STATUSES, status);
            return item ? this.translateLabel(item.label) : this.translateLabel('未発行');
        },
        getBusinessInvoiceStatusBadgeClass(status) {
            const item = this.findBusinessDocumentStatusOption(BUSINESS_INVOICE_STATUSES, status);
            return item ? `bg-${item.color}` : 'bg-secondary';
        },
        getBusinessPaymentStatusLabel(status) {
            const item = BUSINESS_PAYMENT_STATUSES.find((s) => s.value === status);
            return item ? this.translateLabel(item.label) : this.translateLabel('未入金');
        },
        getBusinessPaymentStatusBadgeClass(status) {
            const item = BUSINESS_PAYMENT_STATUSES.find((s) => s.value === status);
            return item ? `bg-${item.color}` : 'bg-secondary';
        },
        getChildProjectPaymentLines(project) {
            if (!project) return [];
            return [
                {
                    key: 'estimate',
                    label: this.translateLabel('見積'),
                    statusLabel: this.getBusinessEstimateStatusLabel(project.estimate_status),
                    badgeClass: this.getBusinessEstimateStatusBadgeClass(project.estimate_status),
                    amount: Number(project.amount) || 0,
                },
                {
                    key: 'invoice',
                    label: this.translateLabel('請求'),
                    statusLabel: this.getBusinessInvoiceStatusLabel(project.invoice_status),
                    badgeClass: this.getBusinessInvoiceStatusBadgeClass(project.invoice_status),
                    amount: Number(project.invoice_amount) || 0,
                },
            ];
        },

        onChildProjectContextMenu(event, project) {
            if (!project || !project.id) return;
            this.childProjectContextMenuProject = project;
            this.childProjectContextMenuX = event.pageX;
            this.childProjectContextMenuY = event.pageY;
            this.childProjectContextMenuVisible = true;
        },
        closeChildProjectContextMenu() {
            this.childProjectContextMenuVisible = false;
            this.childProjectContextMenuProject = null;
        },
        goToChildProjectDetailFromContextMenu() {
            const project = this.childProjectContextMenuProject;
            this.closeChildProjectContextMenu();
            if (!project || !project.id) return;
            window.location.href = '../project/detail.php?id=' + encodeURIComponent(project.id);
        },
        openChildProjectEditFromContextMenu() {
            const project = this.childProjectContextMenuProject;
            this.closeChildProjectContextMenu();
            if (!project || !project.id || !this.canEditChildProject(project)) return;
            this.showEditChildProjectModal(project);
        },
        openChildProjectPaymentFromContextMenu() {
            const project = this.childProjectContextMenuProject;
            this.closeChildProjectContextMenu();
            if (!project || !project.id || !this.canEditBusinessDocuments) return;
            this.openBusinessDocumentModal(project);
        },

        async openBusinessDocumentModal(project) {
            if (!this.canEditBusinessDocuments || !project?.id) return;
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getById&id=${project.id}`);
                this.businessDocumentProject = response.data;
                if (this.businessDocumentProject) {
                    this.businessDocumentProject.version = normalizeProjectVersion(this.businessDocumentProject.version);
                    this.businessDocumentProject.payment_version = normalizeProjectVersion(this.businessDocumentProject.payment_version);
                }
                this.businessDocumentProjectId = project.id;
                this.normalizeBdFields();
                this.businessDocumentSaveStatus = null;
                this.businessDocumentError = '';
                this.businessDocumentDirty = false;
                const modalEl = document.getElementById('businessDocumentModal');
                let modal = bootstrap.Modal.getInstance(modalEl);
                if (!modal) {
                    modal = new bootstrap.Modal(modalEl);
                }
                modal.show();
                this.$nextTick(() => {
                    setTimeout(() => this.initBdDatePickers(), 150);
                });
            } catch (error) {
                console.error('Error loading business document:', error);
                if (typeof showMessage === 'function') {
                    showMessage('決済情報の読み込みに失敗しました。', true);
                }
            }
        },
        closeBusinessDocumentModal() {
            clearTimeout(this.businessDocumentUpdateTimer);
            this.businessDocumentUpdateTimer = null;
            this.destroyBdDatePickers();
            this.businessDocumentProject = null;
            this.businessDocumentProjectId = null;
            this._bdServerDates = null;
            this.businessDocumentError = '';
        },
        destroyBdDatePickers() {
            Object.values(BD_MODAL_PICKER_IDS).forEach((elId) => {
                const el = document.getElementById(elId);
                if (el && el._flatpickr) {
                    el._flatpickr.destroy();
                }
            });
        },
        normalizeBdFields() {
            if (!this.businessDocumentProject) return;
            const p = this.businessDocumentProject;
            p.estimate_status = this.normalizeBusinessDocumentStatus(p.estimate_status, '未発行');
            p.invoice_status = this.normalizeBusinessDocumentStatus(p.invoice_status, '未発行');
            p.estimate_number = p.estimate_number || '';
            p.invoice_number = p.invoice_number || '';
            p.payment_note = p.payment_note || '';
            p.invoice_amount = p.invoice_amount != null ? Number(p.invoice_amount) : 0;
            p.amount = p.amount != null ? Number(p.amount) : 0;
            this.normalizeBdDateFields();
        },
        normalizeBdDateFields() {
            if (!this.businessDocumentProject) return;
            BUSINESS_DOCUMENT_DATE_FIELDS.forEach((key) => {
                const raw = this.businessDocumentProject[key];
                if (!raw || !String(raw).trim()) {
                    this.businessDocumentProject[key] = '';
                }
            });
            this.syncBdServerDatesFromProject();
        },
        syncBdServerDatesFromProject() {
            if (!this.businessDocumentProject) return;
            const server = {};
            BUSINESS_DOCUMENT_DATE_FIELDS.forEach((key) => {
                const raw = String(this.businessDocumentProject[key] || '').trim();
                if (!raw) {
                    server[key] = '';
                    return;
                }
                server[key] = isProjectServerDateTimeFormat(raw)
                    ? raw
                    : (fromProjectDateTimeInputValue(raw) || raw);
            });
            this._bdServerDates = server;
        },
        getBdServerDate(key) {
            if (this._bdServerDates && this._bdServerDates[key] != null && String(this._bdServerDates[key]).trim()) {
                return this._bdServerDates[key];
            }
            const raw = String(this.businessDocumentProject?.[key] || '').trim();
            if (!raw) return '';
            if (isProjectServerDateTimeFormat(raw)) return raw;
            return fromProjectDateTimeInputValue(raw) || raw;
        },
        setBdServerDate(key, displayOrServerValue) {
            if (!this._bdServerDates) {
                this._bdServerDates = {};
            }
            const raw = String(displayOrServerValue || '').trim();
            if (!raw) {
                this._bdServerDates[key] = '';
                return;
            }
            this._bdServerDates[key] = isProjectServerDateTimeFormat(raw)
                ? raw
                : (fromProjectDateTimeInputValue(raw) || raw);
        },
        getBdPickerDisplayValue(el) {
            if (!el) return '';
            const fp = el._flatpickr;
            if (fp) {
                const visibleInput = fp.altInput || fp._input;
                return String((visibleInput && visibleInput.value) || '').trim();
            }
            return String(el.value || '').trim();
        },
        getBdDateForApi(key) {
            if (!this.businessDocumentProject) return '';
            const elId = BD_MODAL_PICKER_IDS[key];
            const el = document.getElementById(elId);
            if (el) {
                const displayVal = this.getBdPickerDisplayValue(el);
                if (!displayVal) {
                    return '';
                }
                const fp = el._flatpickr;
                if (fp && fp.selectedDates && fp.selectedDates.length > 0) {
                    return fromProjectDateTimeInputValue(fp.formatDate(fp.selectedDates[0], PROJECT_DATETIME_FLATPICKR_FORMAT));
                }
                return fromProjectDateTimeInputValue(displayVal);
            }
            const fallback = String(this.businessDocumentProject[key] || '').trim();
            return fallback ? fromProjectDateTimeInputValue(fallback) : '';
        },
        hasBdDate(key) {
            return !!String(this.getBdServerDate(key) || this.businessDocumentProject?.[key] || '').trim();
        },
        hasBdAmount(amount) {
            return amount != null && amount !== '' && Number(amount) > 0;
        },
        hasBdNumber(value) {
            return !!String(value || '').trim();
        },
        isBdEstimateDocumentFieldsComplete() {
            if (!this.businessDocumentProject) return false;
            // Do not call syncBdDatesFromPickers() here — used in template during render;
            // mutating reactive state would hang the page (RESULT_CODE_HUNG).
            return this.hasBdDate('estimate_date')
                && this.hasBdAmount(this.businessDocumentProject.amount)
                && this.hasBdNumber(this.businessDocumentProject.estimate_number);
        },
        isBdInvoiceDocumentFieldsComplete() {
            if (!this.businessDocumentProject) return false;
            return this.hasBdDate('invoice_date')
                && this.hasBdAmount(this.businessDocumentProject.invoice_amount)
                && this.hasBdNumber(this.businessDocumentProject.invoice_number);
        },
        getBdEstimateDocumentFieldsValidationError() {
            if (!this.businessDocumentProject) return '';
            const missing = [];
            if (!this.hasBdDate('estimate_date')) missing.push('見積日');
            if (!this.hasBdAmount(this.businessDocumentProject.amount)) missing.push('見積金額');
            if (!this.hasBdNumber(this.businessDocumentProject.estimate_number)) missing.push('見積番号');
            if (!missing.length) return '';
            return '発行済にするには以下を入力してください: ' + missing.join('、');
        },
        getBdInvoiceDocumentFieldsValidationError() {
            if (!this.businessDocumentProject) return '';
            const missing = [];
            if (!this.hasBdDate('invoice_date')) missing.push('請求日');
            if (!this.hasBdAmount(this.businessDocumentProject.invoice_amount)) missing.push('請求金額');
            if (!this.hasBdNumber(this.businessDocumentProject.invoice_number)) missing.push('請求番号');
            if (!missing.length) return '';
            return '発行済にするには以下を入力してください: ' + missing.join('、');
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
            return this.formatBusinessDocumentCurrency(this.getBusinessDocumentTaxAmount(amount));
        },
        formatBusinessDocumentTotalWithTax(amount) {
            return this.formatBusinessDocumentCurrency(this.getBusinessDocumentTotalWithTax(amount));
        },
        formatBusinessDocumentCurrency(amount) {
            if (!amount) return '¥0';
            return '¥' + parseInt(amount).toLocaleString();
        },
        hideBdStatusDropdown(dropdownId) {
            const dropdownElement = document.querySelector(dropdownId);
            if (dropdownElement && typeof bootstrap !== 'undefined') {
                const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                if (dropdown) dropdown.hide();
            }
        },
        scheduleBdUpdate() {
            if (this._bdSuppressAutoSave) return;
            if (!this.canEditBusinessDocuments || !this.businessDocumentProject || this.isUpdatingBusinessDocument) return;
            this.businessDocumentDirty = true;
            clearTimeout(this.businessDocumentUpdateTimer);
            this.businessDocumentUpdateTimer = setTimeout(() => {
                this.businessDocumentUpdateTimer = null;
                this.updateBdProjectStatus();
            }, 800);
        },
        async updateBdProjectStatus() {
            if (this.isUpdatingBusinessDocument || !this.businessDocumentProject || !this.businessDocumentProjectId) return;
            clearTimeout(this.businessDocumentUpdateTimer);
            this.businessDocumentUpdateTimer = null;
            clearTimeout(this.businessDocumentSaveHideTimer);
            this.businessDocumentSaveStatus = 'loading';
            this.isUpdatingBusinessDocument = true;
            try {
                this.syncBdDatesFromPickers();
                const p = this.businessDocumentProject;
                if (this.normalizeBusinessDocumentStatusValue(p.estimate_status) === '発行済'
                    && !this.isBdEstimateDocumentFieldsComplete()) {
                    this.businessDocumentSaveStatus = null;
                    this.businessDocumentError = this.getBdEstimateDocumentFieldsValidationError();
                    return;
                }
                if (this.normalizeBusinessDocumentStatusValue(p.invoice_status) === '発行済'
                    && !this.isBdInvoiceDocumentFieldsComplete()) {
                    this.businessDocumentSaveStatus = null;
                    this.businessDocumentError = this.getBdInvoiceDocumentFieldsValidationError();
                    return;
                }
                const formData = new FormData();
                formData.append('id', this.businessDocumentProjectId);
                formData.append('amount', p.amount || 0);
                formData.append('estimate_status', p.estimate_status || '未発行');
                formData.append('estimate_date', this.getBdDateForApi('estimate_date'));
                formData.append('estimate_number', p.estimate_number || '');
                formData.append('invoice_status', p.invoice_status || '未発行');
                formData.append('invoice_date', this.getBdDateForApi('invoice_date'));
                formData.append('invoice_amount', p.invoice_amount != null ? p.invoice_amount : 0);
                formData.append('invoice_number', p.invoice_number || '');
                formData.append('payment_note', p.payment_note || '');
                appendPaymentVersionToFormData(formData, this.businessDocumentProject);
                const response = await axios.post('/api/index.php?model=project&method=updateProjectStatus', formData);
                if (response.data && response.data.status === 'success') {
                    applyPaymentVersionFromResponse(this.businessDocumentProject, response.data);
                    this.businessDocumentDirty = false;
                    this.businessDocumentError = '';
                    this._bdSuppressAutoSave = true;
                    BUSINESS_DOCUMENT_DATE_FIELDS.forEach((key) => {
                        const apiVal = this.getBdDateForApi(key);
                        this.setBdServerDate(key, apiVal || '');
                        const nextVal = apiVal ? toProjectDateTimeInputValue(apiVal) : '';
                        if (String(this.businessDocumentProject[key] || '').trim() !== String(nextVal || '').trim()) {
                            this.businessDocumentProject[key] = nextVal;
                        }
                        const el = document.getElementById(BD_MODAL_PICKER_IDS[key]);
                        if (el && el._flatpickr) {
                            if (apiVal) {
                                el._flatpickr.setDate(toProjectDateTimeInputValue(apiVal), false, PROJECT_DATETIME_FLATPICKR_FORMAT);
                            } else {
                                el._flatpickr.clear();
                            }
                        }
                    });
                    this.syncChildProjectFromBd();
                    if (response.data.payment_version != null) {
                        const idx = this.childProjects.findIndex((item) => String(item.id) === String(this.businessDocumentProjectId));
                        if (idx >= 0) {
                            this.childProjects[idx].payment_version = normalizeProjectVersion(response.data.payment_version);
                        }
                    }
                    this.$nextTick(() => {
                        setTimeout(() => {
                            this._bdSuppressAutoSave = false;
                        }, 300);
                    });
                    this.businessDocumentSaveStatus = 'saved';
                    this.businessDocumentSaveHideTimer = setTimeout(() => {
                        this.businessDocumentSaveStatus = null;
                        this.businessDocumentSaveHideTimer = null;
                    }, 5000);
                } else {
                    if (handleProjectVersionConflict(response.data, async () => {
                        await this.openBusinessDocumentModal({ id: this.businessDocumentProjectId });
                    })) {
                        return;
                    }
                    this.businessDocumentSaveStatus = null;
                }
            } catch (error) {
                console.error('Error updating business document:', error);
                this.businessDocumentSaveStatus = null;
            } finally {
                this.isUpdatingBusinessDocument = false;
            }
        },
        syncChildProjectFromBd() {
            if (!this.businessDocumentProject || !this.businessDocumentProjectId) return;
            const idx = this.childProjects.findIndex((p) => String(p.id) === String(this.businessDocumentProjectId));
            if (idx < 0) return;
            BUSINESS_DOCUMENT_FIELDS.forEach((key) => {
                this.childProjects[idx][key] = this.businessDocumentProject[key];
            });
        },
        syncBdDatesFromPickers() {
            if (!this.businessDocumentProject) return;
            Object.keys(BD_MODAL_PICKER_IDS).forEach((key) => {
                const el = document.getElementById(BD_MODAL_PICKER_IDS[key]);
                if (!el) return;
                const displayVal = this.getBdPickerDisplayValue(el);
                if (String(this.businessDocumentProject[key] || '').trim() !== String(displayVal || '').trim()) {
                    this.businessDocumentProject[key] = displayVal;
                    this.setBdServerDate(key, displayVal);
                }
            });
        },
        initBdDatePickers() {
            if (!this.businessDocumentProject) return;
            Object.keys(BD_MODAL_PICKER_IDS).forEach((key) => {
                this.initBdDatePicker(BD_MODAL_PICKER_IDS[key], key);
            });
        },
        initBdDatePicker(elId, key) {
            if (!this.businessDocumentProject) return;
            const el = document.getElementById(elId);
            if (!el) return;
            const serverValue = this.getBdServerDate(key);
            const inputVal = toProjectDateTimeInputValue(serverValue);
            this._bdSuppressAutoSave = true;
            initChildProjectFlatpickr(el, {
                onChange: (selectedDates, dateStr) => {
                    if (this._bdSuppressAutoSave) return;
                    this.businessDocumentProject[key] = dateStr || '';
                    this.setBdServerDate(key, dateStr || '');
                    this.scheduleBdUpdate();
                },
                onClose: () => {
                    if (this._bdSuppressAutoSave) return;
                    const displayVal = this.getBdPickerDisplayValue(el);
                    if (!displayVal) {
                        if (el._flatpickr) {
                            el._flatpickr.clear();
                        }
                        this.businessDocumentProject[key] = '';
                        this.setBdServerDate(key, '');
                        this.scheduleBdUpdate();
                    }
                }
            }, serverValue);
            if (inputVal && this.businessDocumentProject[key] !== inputVal) {
                this.businessDocumentProject[key] = inputVal;
            }
            if (serverValue) {
                this.setBdServerDate(key, serverValue);
            }
            setTimeout(() => {
                this._bdSuppressAutoSave = false;
            }, 200);
        },
        setBdDateToday(field) {
            if (!this.businessDocumentProject) return;
            const serverNow = moment.tz(SERVER_TASK_TIMEZONE).format('YYYY-MM-DD HH:mm:ss');
            const dateStr = toProjectDateTimeInputValue(serverNow);
            this.businessDocumentProject[field] = dateStr || '';
            this.setBdServerDate(field, serverNow);
            const el = document.getElementById(BD_MODAL_PICKER_IDS[field]);
            if (el && el._flatpickr) {
                el._flatpickr.setDate(dateStr, false, PROJECT_DATETIME_FLATPICKR_FORMAT);
            }
            this.scheduleBdUpdate();
        },
        copyBdEstimateAmountToInvoice() {
            if (!this.businessDocumentProject) return;
            this.businessDocumentProject.invoice_amount = this.businessDocumentProject.amount != null
                ? Number(this.businessDocumentProject.amount) : 0;
            this.scheduleBdUpdate();
        },
        copyBdInvoiceAmountToPayment() {
            if (!this.businessDocumentProject) return;
            this.businessDocumentProject.payment_amount = this.businessDocumentProject.invoice_amount != null
                ? Number(this.businessDocumentProject.invoice_amount) : 0;
            this.scheduleBdUpdate();
        },
        findBdStatusOption(list, status) {
            const normalized = this.normalizeBusinessDocumentStatus(status, '');
            return list.find((s) => s.value === normalized || s.value === status);
        },
        getBdEstimateStatusLabel(status) {
            const item = this.findBdStatusOption(BUSINESS_ESTIMATE_STATUSES, status);
            return item ? this.translateLabel(item.label) : this.translateLabel('未発行');
        },
        getBdEstimateStatusButtonClass(status) {
            const item = this.findBdStatusOption(BUSINESS_ESTIMATE_STATUSES, status);
            return item ? `btn-${item.color}` : 'btn-secondary';
        },
        getBdInvoiceStatusLabel(status) {
            const item = this.findBdStatusOption(BUSINESS_INVOICE_STATUSES, status);
            return item ? this.translateLabel(item.label) : this.translateLabel('未発行');
        },
        getBdInvoiceStatusButtonClass(status) {
            const item = this.findBdStatusOption(BUSINESS_INVOICE_STATUSES, status);
            return item ? `btn-${item.color}` : 'btn-secondary';
        },
        getBdPaymentStatusLabel(status) {
            const item = BUSINESS_PAYMENT_STATUSES.find((s) => s.value === status);
            return item ? this.translateLabel(item.label) : this.translateLabel('未入金');
        },
        getBdPaymentStatusButtonClass(status) {
            const item = BUSINESS_PAYMENT_STATUSES.find((s) => s.value === status);
            return item ? `btn-${item.color}` : 'btn-secondary';
        },
        selectBdEstimateStatus(status) {
            if (!this.businessDocumentProject) return;
            this.syncBdDatesFromPickers();
            const normalized = this.normalizeBusinessDocumentStatusValue(status);
            if (normalized === '発行済' && !this.isBdEstimateDocumentFieldsComplete()) {
                this.businessDocumentError = this.getBdEstimateDocumentFieldsValidationError();
                this.hideBdStatusDropdown('#bdEstimateStatusDropdown');
                return;
            }
            if (this.normalizeBusinessDocumentStatusValue(this.businessDocumentProject.estimate_status) === normalized) {
                this.hideBdStatusDropdown('#bdEstimateStatusDropdown');
                return;
            }
            this.businessDocumentProject.estimate_status = normalized;
            if (normalized === '無償') {
                this.businessDocumentProject.amount = 0;
            }
            this.businessDocumentError = '';
            this.scheduleBdUpdate();
            this.hideBdStatusDropdown('#bdEstimateStatusDropdown');
        },
        selectBdInvoiceStatus(status) {
            if (!this.businessDocumentProject) return;
            this.syncBdDatesFromPickers();
            const normalized = this.normalizeBusinessDocumentStatusValue(status);
            if (normalized === '発行済' && !this.isBdInvoiceDocumentFieldsComplete()) {
                this.businessDocumentError = this.getBdInvoiceDocumentFieldsValidationError();
                this.hideBdStatusDropdown('#bdInvoiceStatusDropdown');
                return;
            }
            if (this.normalizeBusinessDocumentStatusValue(this.businessDocumentProject.invoice_status) === normalized) {
                this.hideBdStatusDropdown('#bdInvoiceStatusDropdown');
                return;
            }
            this.businessDocumentProject.invoice_status = normalized;
            if (normalized === '無償') {
                this.businessDocumentProject.invoice_amount = 0;
            }
            this.businessDocumentError = '';
            this.scheduleBdUpdate();
            this.hideBdStatusDropdown('#bdInvoiceStatusDropdown');
        },
        selectBdPaymentStatus(status) {
            if (!this.businessDocumentProject) return;
            this.businessDocumentProject.payment_status = status;
            this.scheduleBdUpdate();
            const dropdownElement = document.querySelector('#bdPaymentStatusDropdown');
            if (dropdownElement) {
                const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                if (dropdown) dropdown.hide();
            }
        },
        async loadBusinessDocumentLogs() {
            if (!this.businessDocumentProjectId) return;
            try {
                const res = await axios.get(`/api/index.php?model=project&method=getLogs&project_id=${this.businessDocumentProjectId}`);
                this.businessDocumentLogs = (res.data && Array.isArray(res.data)) ? res.data : [];
            } catch (e) {
                this.businessDocumentLogs = [];
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
            this.loadBusinessDocumentLogs();
            this.showBusinessDocumentLogModal = true;
        },
        closeBusinessDocumentLogModal() {
            this.showBusinessDocumentLogModal = false;
        },
        hasBdLogValue(value) {
            return value !== null && value !== undefined && String(value).trim() !== '';
        },
        getBusinessDocumentLogValue(log, field) {
            const value = log[field];
            if (!this.hasBdLogValue(value)) return '—';
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
        getBdLogBadgeClass(log, field) {
            const value = log[field];
            if (!this.hasBdLogValue(value)) return 'badge bg-secondary';
            if (log.action === 'estimate_status_updated' || log.action === 'invoice_status_updated') {
                const cls = log.action === 'estimate_status_updated'
                    ? this.getBusinessEstimateStatusBadgeClass(value)
                    : this.getBusinessInvoiceStatusBadgeClass(value);
                return `badge ${cls}`;
            }
            if (log.action === 'payment_status_updated') {
                return `badge ${this.getBusinessPaymentStatusBadgeClass(value)}`;
            }
            return field === 'value1' ? 'badge bg-secondary' : 'badge bg-primary';
        },
        bdHistoryIcon(action) {
            switch (action) {
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
                default:
                    return 'fa fa-history text-secondary';
            }
        },

        formatNumberForInput(number) {
            // Format number with 2 decimal places for input display
            if (number === null || number === undefined || isNaN(number)) {
                return '0.00';
            }
            return parseFloat(number).toFixed(2);
        },

        formatCurrency(amount) {
            // Format currency amount as integer with comma thousands separator (no currency symbol)
            if (amount === null || amount === undefined || isNaN(amount)) {
                return '0';
            }
            const integerAmount = Math.floor(parseFloat(amount));
            return new Intl.NumberFormat('ja-JP').format(integerAmount);
        },
        
        // Price list methods
        async loadPriceListData() {
            try {
                const productsResponse = await axios.get('/api/index.php?model=pricelist&method=getAllProducts');
                
                if (productsResponse.data) {
                    this.priceListProducts = productsResponse.data;
                    
                    this.filterPriceListProducts();
                } else {
                    console.warn('No data received from API');
                }
            } catch (error) {
                console.error('Error loading price list data:', error);
                console.error('Response:', error.response);
                // Add some sample data for testing if API fails
                this.priceListProducts = [
                    { id: 1, code: '100', name: 'サンプル商品1', type: '新規', unit: '枚', price: 1000, cost: 800 },
                    { id: 2, code: '101', name: 'サンプル商品2', type: '修正', unit: '枚', price: 2000, cost: 1600 },
                    { id: 3, code: '102', name: 'サンプル商品3', type: 'その他', unit: '枚', price: 1500, cost: 1200 }
                ];
                this.filterPriceListProducts();
            }
        },
        
        filterPriceListProducts() {
            let filtered = [...this.priceListProducts];
            
            // Filter by type
            if (this.selectedPriceListType) {
                filtered = filtered.filter(product => 
                    (product.type || '') === this.selectedPriceListType
                );
            }
            
            // Filter by search term
            if (this.priceListSearchTerm) {
                const term = this.priceListSearchTerm.toLowerCase();
                filtered = filtered.filter(product => 
                    (product.code || '').toLowerCase().includes(term) ||
                    (product.name || '').toLowerCase().includes(term)
                );
            }
            
            // Filter by tags
            if (this.priceListTagSearchTerm) {
                const tagTerm = this.priceListTagSearchTerm.toLowerCase();
                filtered = filtered.filter(product => {
                    if (!product.tags) return false;
                    return product.tags.toLowerCase().includes(tagTerm);
                });
            }
            
            // Sort by product code in ascending order
            filtered.sort((a, b) => {
                const codeA = (a.code || '').toString().toLowerCase();
                const codeB = (b.code || '').toString().toLowerCase();
                return codeA.localeCompare(codeB);
            });
            
            this.filteredPriceListProducts = filtered;
            
            // Do not reset selected products when filter/search changes
            // Only reset pagination and highlight for better UX
            this.priceListPage = 1;
            this.highlightedIndex = 0;
            this.updateAllSelectedStatus();
        },

        scheduleFilterPriceListProducts() {
            if (this.filterDebounceTimer) {
                clearTimeout(this.filterDebounceTimer);
            }
            this.filterDebounceTimer = setTimeout(() => {
                this.filterPriceListProducts();
                this.filterDebounceTimer = null;
            }, 200);
        },
        
        showPriceListModal() {
            if (this.priceListProducts.length === 0) {
                this.loadPriceListData();
            }
            
            // Detect context: check if edit quotation modal is open
            const editQuotationModal = document.getElementById('editQuotationModal');
            this.isPriceListOpenFromEdit = editQuotationModal && editQuotationModal.classList.contains('show');
            
            // Reset selection when opening modal
            this.selectedProducts = [];
            this.allSelected = false;
            this.priceListPage = 1;
            this.highlightedIndex = 0;
            this.selectedProductQuantities = {};
            // Reset filters
            this.selectedPriceListType = '';
            this.priceListSearchTerm = '';
            this.priceListTagSearchTerm = '';
            
            this.priceListModal.show();
        },
        
        selectPriceListProduct(product) {
        // Detect context: use reactive data property and fallback to DOM check
        let isEditingQuotation = this.isPriceListOpenFromEdit;
        if (!isEditingQuotation) {
            const editQuotationModal = document.getElementById('editQuotationModal');
            isEditingQuotation = editQuotationModal && editQuotationModal.classList.contains('show');
        }
        
        // Clean and validate product data
        const cleanPrice = this.cleanPriceValue(product.price);
        
        // Add the selected product as a new order item
        const newItem = {
            title: product.name || '',
            product_code: product.code || '',
            product_name: product.name || '',
            type: product.type || '',
            product_id: product.id,
            quantity: 1,
            unit: product.unit || '枚',
            unit_price: cleanPrice,
            amount: cleanPrice,
            notes: product.notes || '',
            is_set: false
        };
        
        // Auto-select project if only one option available
        this.autoSelectSingleProjectOption(newItem, isEditingQuotation);
        
        // Add to appropriate quotation items based on context
        if (isEditingQuotation) {
            this.editingQuotation.items.push(newItem);
            this.calculateTotalAmountForEdit();
        } else {
            this.newQuotation.items.push(newItem);
            this.calculateTotalAmount();
        }
        
        // Close the modal
        this.priceListModal.hide();
        
        // Reset filters
        this.selectedPriceListType = '';
        this.priceListSearchTerm = '';
        this.priceListTagSearchTerm = '';
        this.filterPriceListProducts();
    },
            
        // Multiple product selection methods
        toggleProductSelection(product, event) {
            // Ignore if product is already added to quotation (individually or in any set)
            if (this.isProductAlreadyAdded(product)) {
                return;
            }
            
            if (event && event.shiftKey && this.lastSelectedIndexGlobal !== null) {
                const currentIndex = this.filteredPriceListProducts.findIndex(p => p.id === product.id);
                if (currentIndex !== -1) {
                    const start = Math.min(this.lastSelectedIndexGlobal, currentIndex);
                    const end = Math.max(this.lastSelectedIndexGlobal, currentIndex);
                    for (let i = start; i <= end; i++) {
                        const id = this.filteredPriceListProducts[i].id;
                        if (this.isProductAlreadyAdded({ id })) continue;
                        if (!this.selectedProducts.includes(id)) {
                            this.selectedProducts.push(id);
                            if (!this.selectedProductQuantities[id]) this.selectedProductQuantities[id] = 1;
                        }
                    }
                }
            } else {
                const index = this.selectedProducts.indexOf(product.id);
                if (index > -1) {
                    this.selectedProducts.splice(index, 1);
                    delete this.selectedProductQuantities[product.id];
                } else {
                    this.selectedProducts.push(product.id);
                    if (!this.selectedProductQuantities[product.id]) this.selectedProductQuantities[product.id] = 1;
                }
                
                // Force Vue reactivity by reassigning the array reference
                this.selectedProducts = [...this.selectedProducts];
            }
            
            this.lastSelectedIndexGlobal = this.filteredPriceListProducts.findIndex(p => p.id === product.id);
            
            // Force Vue reactivity update and update status in next tick
            this.$nextTick(() => {
                this.updateAllSelectedStatus();
            });
        },
        
        toggleSelectAll() {
            const pageIds = this.paginatedPriceListProducts
                .filter(p => !this.isProductAlreadyAdded(p))
                .map(p => p.id);
            
            const allOnPageSelected = pageIds.every(id => this.selectedProducts.includes(id));
            
            if (allOnPageSelected) {
                // Deselect only current page ids
                this.selectedProducts = this.selectedProducts.filter(id => {
                    const keep = !pageIds.includes(id);
                    if (!keep) delete this.selectedProductQuantities[id];
                    return keep;
                });
            } else {
                // Add current page ids
                pageIds.forEach(id => {
                    if (!this.selectedProducts.includes(id)) this.selectedProducts.push(id);
                    if (!this.selectedProductQuantities[id]) this.selectedProductQuantities[id] = 1;
                });
            }
            
            this.updateAllSelectedStatus();
        },
        
        updateAllSelectedStatus() {
            const pageIds = this.paginatedPriceListProducts
                .filter(p => !this.isProductAlreadyAdded(p))
                .map(p => p.id);
            
            this.allSelected = pageIds.length > 0 && pageIds.every(id => this.selectedProducts.includes(id));
        },
        
        selectSingleProduct(product) {
            // Single product selection (original behavior)
            this.selectPriceListProduct(product);
        },
        
        removeFromSelection(productId) {
            const index = this.selectedProducts.indexOf(productId);
            if (index > -1) {
                this.selectedProducts.splice(index, 1);
                delete this.selectedProductQuantities[productId];
            }
            
            this.updateAllSelectedStatus();
        },

        // Auto-select project if only one option available
        autoSelectSingleProjectOption(item, isEditContext) {
            const availableProjects = isEditContext ? this.selectedChildProjectsForEditDropdown : this.selectedChildProjectsForDropdown;
            
            // If there's exactly one project option available, auto-select it
            if (availableProjects.length === 1) {
                const singleProject = availableProjects[0];
                item.project_id = singleProject.id;
                if (!isEditContext) {
                    item._oldProjectId = singleProject.id;
                }
            }
        },
        
        // Helper function to clean price values
        cleanPriceValue(price) {
            if (price === null || price === undefined || price === '') {
                return 0;
            }
            // Convert to number and handle edge cases
            const priceStr = String(price).replace(/[^\d.-]/g, ''); // Remove non-numeric chars except dots and minus
            const cleanPrice = parseFloat(priceStr);
            return isNaN(cleanPrice) ? 0 : cleanPrice;
        },
        
        getSelectedProductsList() {
            const selected = this.priceListProducts.filter(p => this.selectedProducts.includes(p.id));
            return selected;
        },
        
        getSelectedProductsTotal() {
            return this.getSelectedProductsList().reduce((total, product) => {
                const qty = this.selectedProductQuantities[product.id] || 1;
                const cleanPrice = this.cleanPriceValue(product.price);
                return total + (cleanPrice * qty);
            }, 0);
        },
        
        addSelectedProductsAsSet() {
            // Detect context: use reactive data property and fallback to DOM check
            let isEditingQuotation = this.isPriceListOpenFromEdit;
            if (!isEditingQuotation) {
                const editQuotationModal = document.getElementById('editQuotationModal');
                isEditingQuotation = editQuotationModal && editQuotationModal.classList.contains('show');
            }
            
            const selectedProducts = this.getSelectedProductsList();
            if (selectedProducts.length === 0) return;
            
            // Build set details to persist as JSON
            const setDetails = selectedProducts.map(p => ({
                id: p.id,
                code: p.code,
                name: p.name,
                unit: p.unit,
                price: p.price,
                quantity: this.selectedProductQuantities[p.id] || 1
            }));

            // Create a set item with combined information
            const fallbackTitle = `商品セット (${selectedProducts.length}件)`;
            const computedTitle = (this.selectedSetName && this.selectedSetName.trim()) ? this.selectedSetName.trim() : fallbackTitle;
            const setItem = {
                project_id: '', // Add project_id field for consistency
                _oldProjectId: '', // Add _oldProjectId field for consistency
                title: computedTitle,
                product_code: '', // Set empty for set products
                product_name: selectedProducts.map(p => {
                    const qty = this.selectedProductQuantities[p.id] || 1;
                    return `${p.name} x${qty}`;
                }).join(' + '),
                type: '', // Set type for set products
                quantity: 1,
                unit: '式',
                unit_price: this.getSelectedProductsTotal(),
                amount: this.getSelectedProductsTotal(),
                notes: '',
                is_set: true,
                set_json: setDetails // Store as object, not stringified
            };
            
            // Auto-select project if only one option available
            this.autoSelectSingleProjectOption(setItem, isEditingQuotation);
            
            // Add to appropriate quotation items based on context
            if (isEditingQuotation) {
                this.editingQuotation.items = [...this.editingQuotation.items, setItem];
                // Clear items validation error when items are added
                delete this.editQuotationValidationErrors.items;
                this.$nextTick(() => {
                    this.calculateTotalAmountForEdit();
                });
            } else {
                this.newQuotation.items = [...this.newQuotation.items, setItem];
                // Clear items validation error when items are added
                this.quotationValidationErrors.items = undefined;

                this.$nextTick(() => {
                    this.calculateTotalAmount();
                });
            }
            
            // Close the modal and reset
            this.priceListModal.hide();
            this.selectedProducts = [];
            this.allSelected = false;
            this.selectedSetName = '';
            this.selectedPriceListType = '';
            this.priceListSearchTerm = '';
            this.priceListTagSearchTerm = '';
            this.filterPriceListProducts();
        },
        
        // --- Set editing ---
        showEditSetModal(itemIndex) {
            // Detect context: use reactive data property and fallback to DOM check
            let isEditingQuotation = this.isPriceListOpenFromEdit;
            if (!isEditingQuotation) {
                const editQuotationModal = document.getElementById('editQuotationModal');
                isEditingQuotation = editQuotationModal && editQuotationModal.classList.contains('show');
            }
            
            // Get the appropriate quotation items
            const items = isEditingQuotation ? this.editingQuotation.items : this.newQuotation.items;
            const item = items[itemIndex];
            
            if (!item || !item.is_set) {
                return;
            }
            
            let products = [];
            
            // Handle both object and JSON string cases
            if (typeof item.set_json === 'object' && item.set_json !== null) {
                // set_json is already an object
                products = Array.isArray(item.set_json) ? item.set_json : [];
            } else if (typeof item.set_json === 'string') {
                // set_json is a JSON string, try to parse it
                try {
                    const parsed = JSON.parse(item.set_json || '[]');
                    products = Array.isArray(parsed) ? parsed : [];
                } catch (e) {
                    products = [];
                }
            } else {
                products = [];
            }
            
            this.editingSet = { 
                index: itemIndex, 
                products,
                isEditContext: isEditingQuotation,
                name: item.title || '商品セット'
            };
            
            if (!this.setEditModal) {
                const el = document.getElementById('editSetModal');
                
                if (el) {
                    this.setEditModal = new bootstrap.Modal(el);
                    
                    // Bind stacking handlers once
                    el.addEventListener('shown.bs.modal', () => {
                        // Raise z-index of edit modal
                        // Raise the last backdrop slightly above base backdrop
                        const backdrops = document.querySelectorAll('.modal-backdrop');
                        const lastBackdrop = backdrops[backdrops.length - 1];
                        if (lastBackdrop) lastBackdrop.classList.add('edit-set-backdrop');
                    }, { once: false });
                    el.addEventListener('hidden.bs.modal', () => {
                        const backdrops = document.querySelectorAll('.modal-backdrop.edit-set-backdrop');
                        backdrops.forEach(b => b.classList.remove('edit-set-backdrop'));
                    }, { once: false });
                }
            }
            
            if (this.setEditModal) {
                this.setEditModal.show();
            }
        },
        updateEditingSetQty(productId, value) {
            if (!this.editingSet) return;
            const target = this.editingSet.products.find(p => p.id === productId);
            if (!target) return;
            let qty = Number(value);
            if (!Number.isFinite(qty) || qty <= 0) qty = 1;
            target.quantity = Math.floor(qty);
        },
        removeFromEditingSet(productId) {
            if (!this.editingSet) return;
            this.editingSet.products = this.editingSet.products.filter(p => p.id !== productId);
        },
        getEditingSetTotal() {
            if (!this.editingSet) return 0;
            return this.editingSet.products.reduce((sum, p) => sum + (p.price || 0) * (p.quantity || 1), 0);
        },
        saveEditedSet() {
            if (!this.editingSet) {
                return;
            }
            
            const { index, products, isEditContext } = this.editingSet;
            
            // Get the appropriate quotation items based on context
            const items = isEditContext ? this.editingQuotation.items : this.newQuotation.items;
            const item = items[index];
            if (!item) {
                return;
            }
            
            const total = products.reduce((sum, p) => sum + (p.price || 0) * (p.quantity || 1), 0);
            // Allow product_code to be freely entered even for set products
            // Removed the restriction that was clearing product_code for set products
            item.product_name = products.map(p => `${p.name} x${(p.quantity || 1)}`).join(' + ');
            item.unit_price = total;
            item.amount = total;
            // Do not auto-fill notes when editing set
            item.is_set = true;
            item.set_json = products; // Store as object, not stringified for consistency
            item.title = `商品セット (${products.length}件)`;
            
            // Call appropriate calculation method based on context
            if (isEditContext) {
                this.calculateTotalAmountForEdit();
            } else {
                this.calculateTotalAmount();
            }
            
            if (this.setEditModal) {
                this.setEditModal.hide();
            }
        },

        // --- Order items bulk selection/deletion ---
        toggleSelectOrderItem(index) {
            const pos = this.selectedOrderItemIndexes.indexOf(index);
            if (pos >= 0) {
                this.selectedOrderItemIndexes.splice(pos, 1);
            } else {
                this.selectedOrderItemIndexes.push(index);
            }
            // Force Vue reactivity update
            this.selectedOrderItemIndexes = [...this.selectedOrderItemIndexes];
        },
        selectAllOrderItems() {
            if (this.allOrderItemsSelected) {
                this.selectedOrderItemIndexes = [];
            } else {
                this.selectedOrderItemIndexes = (this.newQuotation.items || []).map((_, idx) => idx);
            }
            // Force Vue reactivity update
            this.$nextTick(() => {
                this.$forceUpdate();
            });
        },
        deleteSelectedOrderItems() {
            if (!this.selectedOrderItemIndexes.length) return;
            
            const toDelete = new Set(this.selectedOrderItemIndexes);
            this.newQuotation.items = (this.newQuotation.items || []).filter((_, idx) => !toDelete.has(idx));
            
            this.selectedOrderItemIndexes = [];
            this.calculateTotalAmount();
        },

        addSelectedProductsIndividually() {
            // Detect context: use reactive data property and fallback to DOM check
            let isEditingQuotation = this.isPriceListOpenFromEdit;
            if (!isEditingQuotation) {
                const editQuotationModal = document.getElementById('editQuotationModal');
                isEditingQuotation = editQuotationModal && editQuotationModal.classList.contains('show');
            }
            
            const selectedProducts = this.getSelectedProductsList();
            if (selectedProducts.length === 0) return;
            
            const newItems = [];
            selectedProducts.forEach(product => {
                const cleanPrice = this.cleanPriceValue(product.price);
                const quantity = this.selectedProductQuantities[product.id] || 1;
                const item = {
                    title: product.name,
                    product_code: product.code,
                    product_name: product.name,
                    type: product.type || '',
                    product_id: product.id,
                    quantity: quantity,
                    unit: product.unit,
                    unit_price: cleanPrice,
                    amount: cleanPrice * quantity,
                    notes: product.notes || '',
                    is_set: false
                };
                
                // Auto-select project if only one option available
                this.autoSelectSingleProjectOption(item, isEditingQuotation);
                
                newItems.push(item);
            });
            
            // Add to appropriate quotation items based on context
            if (isEditingQuotation) {
                this.editingQuotation.items = [...this.editingQuotation.items, ...newItems];
                // Clear items validation error when items are added
                delete this.editQuotationValidationErrors.items;
                this.calculateTotalAmountForEdit();
            } else {
                this.newQuotation.items = [...this.newQuotation.items, ...newItems];
                // Clear items validation error when items are added
                this.quotationValidationErrors.items = undefined;
                this.calculateTotalAmount();
            }
            
            // Close and reset
            this.priceListModal.hide();
            this.selectedProducts = [];
            this.allSelected = false;
            this.selectedProductQuantities = {};
            this.selectedPriceListType = '';
            this.priceListSearchTerm = '';
            this.priceListTagSearchTerm = '';
            this.filterPriceListProducts();
        },

        // Pagination controls
        goToPrevPriceListPage() {
            if (this.priceListPage > 1) {
                this.priceListPage -= 1;
                this.highlightedIndex = 0;
                this.updateAllSelectedStatus();
            }
        },
        goToNextPriceListPage() {
            if (this.priceListPage < this.totalPriceListPages) {
                this.priceListPage += 1;
                this.highlightedIndex = 0;
                this.updateAllSelectedStatus();
            }
        },
        onChangePriceListPageSize() {
            // Reset to first page when page size changes
            this.priceListPage = 1;
            this.highlightedIndex = 0;
            this.updateAllSelectedStatus();
        },
        selectAllFiltered() {
            this.selectedProducts = this.filteredPriceListProducts
                .filter(p => !this.isProductAlreadyAdded(p))
                .map(p => p.id);
            
            const map = {};
            this.filteredPriceListProducts
                .filter(p => !this.isProductAlreadyAdded(p))
                .forEach(p => { map[p.id] = this.selectedProductQuantities[p.id] || 1; });
            
            this.selectedProductQuantities = map;
            
            this.updateAllSelectedStatus();
        },

        normalizeSelectedQuantity(productId) {
            let qty = Number(this.selectedProductQuantities[productId] || 1);
            if (!Number.isFinite(qty) || qty <= 0) qty = 1;
            
            this.selectedProductQuantities[productId] = Math.floor(qty);
        },

        // Keyboard navigation inside price list modal
        handlePriceListKeydown(e) {
            if (!document.body.classList.contains('modal-open')) return;
            
            const list = this.paginatedPriceListProducts;
            if (!list || list.length === 0) return;
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.highlightedIndex = Math.min(this.highlightedIndex + 1, list.length - 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.highlightedIndex = Math.max(this.highlightedIndex - 1, 0);
            } else if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const product = list[this.highlightedIndex];
                if (product && !this.isProductAlreadyAdded(product)) {
                    this.toggleProductSelection(product, e);
                }
            }
        },

        isProductAlreadyAdded(product) {
            if (!product || product.id == null) return false;
            return this.alreadyAddedProductIds.has(product.id);
        },
        
        // Debug method to set test data
        setTestData() {
            this.priceListProducts = [
                { id: 1, code: '100', name: 'サンプル商品1', type: '新規', unit: '枚', price: 1000, cost: 800 },
                { id: 2, code: '101', name: 'サンプル商品2', type: '修正', unit: '枚', price: 2000, cost: 1600 },
                { id: 3, code: '102', name: 'サンプル商品3', type: 'その他', unit: '枚', price: 1500, cost: 1200 },
                { id: 4, code: '103', name: 'サンプル商品4', type: '新規', unit: '枚', price: 3000, cost: 2400 },
                { id: 5, code: '104', name: 'サンプル商品5', type: '修正', unit: '枚', price: 2500, cost: 2000 }
            ];
            this.filterPriceListProducts();
        },

        testFormData() {
            const testData = new FormData();
            testData.append('test_field', 'test_value');
            testData.append('test_array', JSON.stringify([1, 2, 3]));
            
            // Test JSON parsing
            try {
                const parsedArray = JSON.parse(testData.get('test_array'));
            } catch (e) {
                console.error('Error parsing test array:', e);
            }
        },

        async testRequest() {
            try {
                const testData = new FormData();
                testData.append('test_field', 'test_value');
                testData.append('test_array', JSON.stringify([1, 2, 3]));
                
                const response = await axios.post('/api/index.php?model=quotation&method=create', testData);
            } catch (error) {
                console.error('Test request error:', error);
                if (error.response) {
                    console.error('Test response data:', error.response.data);
                }
            }
        },

        async testAlternativeRequest() {
            try {
                // Test with URLSearchParams instead of FormData
                const testData = new URLSearchParams();
                testData.append('test_field', 'test_value');
                testData.append('test_array', JSON.stringify([1, 2, 3]));
                
                const response = await axios.post('/api/index.php?model=quotation&method=create', testData, {
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }
                });
            } catch (error) {
                console.error('Alternative test request error:', error);
                if (error.response) {
                    console.error('Alternative test response data:', error.response.data);
                }
            }
        },

        // Child project selection methods
        selectAllChildProjects() {
            // Only select projects that are not disabled (not already used in active quotations)
            this.selectedChildProjectIds = this.childProjects
                .filter(project => !this.projectsUsedInActiveQuotations.has(parseInt(project.id)))
                .map(project => parseInt(project.id));
            this.updateChildProjectSelection();
        },
        
        deselectAllChildProjects() {
            this.selectedChildProjectIds = [];
            this.updateChildProjectSelection();
        },
        
        toggleAllChildProjects() {
            if (this.allChildProjectsSelected) {
                this.deselectAllChildProjects();
            } else {
                this.selectAllChildProjects();
            }
        },
        
        updateChildProjectSelection() {
            // Calculate totals excluding disabled projects
            const availableProjects = this.childProjects.filter(project => 
                !this.projectsUsedInActiveQuotations.has(parseInt(project.id))
            );
            const totalAvailable = availableProjects.length;
            const selected = this.selectedChildProjectIds.length;
            
            this.allChildProjectsSelected = selected === totalAvailable && totalAvailable > 0;
            this.someChildProjectsSelected = selected > 0 && selected < totalAvailable;
            

            
            // Clear project_id from order items that are no longer linked to selected projects
            this.clearUnlinkedOrderItems();
        },
        
        async updateSelectedChildProjectsStatus() {
            try {
                if (this.selectedChildProjectIds.length === 0) {
                    return;
                }
                
                const quotationStatus = this.newQuotation.status;
                let newStatus = 'open'; // Default status
                
                // Map quotation status to project status
                switch (quotationStatus) {
                    case '下書き':
                        newStatus = 'draft';
                        break;
                    case '発行済み':
                        newStatus = 'quoted';
                        break;
                    case '承認済み':
                        newStatus = 'confirming';
                        break;
                    case '却下':
                        newStatus = 'cancelled';
                        break;
                    case '調整':
                        newStatus = 'in_progress';
                        break;
                    default:
                        newStatus = 'open';
                }
                
                let successCount = 0;
                let errorCount = 0;
                
                // Update each selected child project status
                for (const projectId of this.selectedChildProjectIds) {
                    try {
                        const formData = new FormData();
                        formData.append('id', projectId);
                        formData.append('status', newStatus);
                        
                        const response = await axios.post('/api/index.php?model=project&method=updateStatus', formData);
                        
                        if (response.data && response.data.status === 'success') {
                            successCount++;
                        } else {
                            console.warn(`Failed to update child project ${projectId} status:`, response.data?.message);
                            errorCount++;
                        }
                    } catch (error) {
                        console.error(`Error updating child project ${projectId} status:`, error);
                        errorCount++;
                    }
                }
                
                // Reload child projects to reflect the changes
                await this.loadChildProjects();
                
                // Show appropriate message based on results
                if (successCount > 0 && errorCount === 0) {
                    showMessage(`${successCount}件の子プロジェクトのステータスが「${this.getProjectStatusLabel(newStatus)}」に更新されました。`, false);
                } else if (successCount > 0 && errorCount > 0) {
                    showMessage(`${successCount}件の子プロジェクトのステータスが更新されましたが、${errorCount}件の更新に失敗しました。`, true);
                } else if (successCount === 0) {
                    showMessage('子プロジェクトのステータスの更新に失敗しました。', true);
                }
                
            } catch (error) {
                console.error('Error updating selected child projects status:', error);
                showMessage('子プロジェクトのステータスの更新中にエラーが発生しました。', true);
            }
        },

        async updateChildProjectAmountsAfterQuotation() {
            try {
                if (!this.newQuotation.items || this.newQuotation.items.length === 0) {
                    return;
                }

                // Group items by project_id and calculate totals
                const projectAmounts = {};
                
                // Get tax rate from the new quotation
                const taxRate = parseFloat(this.newQuotation.tax_rate) || 0;
                
                this.newQuotation.items.forEach(item => {
                    if (item.project_id && item.amount) {
                        const projectId = item.project_id;
                        if (!projectAmounts[projectId]) {
                            projectAmounts[projectId] = 0;
                        }
                        // Add amount including tax (amount * (1 + tax_rate/100))
                        const itemAmount = parseFloat(item.amount) || 0;
                        const itemAmountWithTax = itemAmount * (1 + taxRate / 100);
                        projectAmounts[projectId] += itemAmountWithTax;
                    }
                });

                // Update each child project's amount in the database (including tax)
                let successCount = 0;
                let errorCount = 0;

                for (const [projectId, totalAmount] of Object.entries(projectAmounts)) {
                    try {
                        const formData = new FormData();
                        formData.append('id', projectId);
                        formData.append('amount', totalAmount);
                        
                        const response = await axios.post('/api/index.php?model=project&method=updateAmount', formData);
                        
                        if (response.data && response.data.status === 'success') {
                            successCount++;
                            
                            // Also update local childProjects array
                            const localProject = this.childProjects.find(p => p.id == projectId);
                            if (localProject) {
                                localProject.total_amount = totalAmount;
                                localProject.amount = totalAmount; // Update both fields for compatibility
                            }
                        } else {
                            console.warn(`Failed to update child project ${projectId} total_amount:`, response.data?.message);
                            errorCount++;
                        }
                    } catch (error) {
                        console.error(`Error updating child project ${projectId} total_amount:`, error);
                        errorCount++;
                    }
                }

                // Force Vue reactivity update
                this.$forceUpdate();

                // Show appropriate message based on results
                if (successCount > 0 && errorCount === 0) {
                    showMessage(`${successCount}件の子プロジェクトの金額が更新されました。`, false);
                } else if (successCount > 0 && errorCount > 0) {
                    showMessage(`${successCount}件の子プロジェクトの金額が更新されましたが、${errorCount}件の更新に失敗しました。`, true);
                } else if (successCount === 0) {
                    showMessage('子プロジェクトの金額の更新に失敗しました。', true);
                }

            } catch (error) {
                console.error('Error updating child project amounts after quotation:', error);
                showMessage('子プロジェクトの金額（税込）の更新中にエラーが発生しました。', true);
            }
        },

        async updateChildProjectAmountsFromQuotation(quotationId) {
            try {
                // Use editingQuotation data if available and matches quotationId, otherwise find in quotations array
                let quotationItems = null;
                let quotationTotal = 0;
                
                if (this.editingQuotation && parseInt(this.editingQuotation.id) === parseInt(quotationId)) {
                    quotationItems = this.editingQuotation.items;
                    quotationTotal = parseFloat(this.editingQuotation.total_with_tax) || 0;
                } else {
                    const quotation = this.quotations.find(q => parseInt(q.id) === parseInt(quotationId));
                    if (quotation) {
                        quotationItems = quotation.items;
                        quotationTotal = parseFloat(quotation.total_with_tax) || 0;
                    }
                }
                
                // If items not found, load quotation detail from API
                if (!quotationItems || quotationItems.length === 0) {
                    try {
                        const detailResponse = await axios.get(`/api/index.php?model=quotation&method=get&id=${quotationId}`);
                        if (detailResponse.data && detailResponse.data.status === 'success' && detailResponse.data.data) {
                            quotationItems = detailResponse.data.data.items || [];
                            quotationTotal = parseFloat(detailResponse.data.data.total_with_tax) || 0;
                            
                            // Update local quotation data
                            const quotation = this.quotations.find(q => parseInt(q.id) === parseInt(quotationId));
                            if (quotation) {
                                quotation.items = quotationItems;
                            }
                        }
                    } catch (error) {
                        // Silently handle error - backend API should have already updated the amounts
                    }
                }
                
                if (!quotationItems || quotationItems.length === 0) {
                    // Backend API should have already updated the amounts
                    return;
                }

                // Calculate total amount of all items (without tax)
                let totalItemsAmount = 0;
                quotationItems.forEach(item => {
                    if (item.project_id && item.amount) {
                        totalItemsAmount += parseFloat(item.amount) || 0;
                    }
                });

                if (totalItemsAmount === 0) {
                    return;
                }

                // Calculate ratio to distribute quotation total_with_tax proportionally
                const ratio = quotationTotal / totalItemsAmount;

                // Group items by project_id and calculate totals based on quotation total_with_tax
                const projectAmounts = {};
                
                quotationItems.forEach(item => {
                    if (item.project_id && item.amount) {
                        const projectId = item.project_id;
                        const itemAmount = parseFloat(item.amount) || 0;
                        // Calculate proportional amount from quotation total_with_tax
                        const projectAmount = itemAmount * ratio;
                        
                        if (!projectAmounts[projectId]) {
                            projectAmounts[projectId] = 0;
                        }
                        projectAmounts[projectId] += projectAmount;
                    }
                });

                // Update each child project's amount in the database
                let successCount = 0;
                let errorCount = 0;

                for (const [projectId, totalAmount] of Object.entries(projectAmounts)) {
                    try {
                        const formData = new FormData();
                        formData.append('id', projectId);
                        formData.append('amount', totalAmount);
                        
                        const response = await axios.post('/api/index.php?model=project&method=updateAmount', formData);
                        
                        if (response.data && response.data.status === 'success') {
                            successCount++;
                            
                            // Also update local childProjects array
                            const localProject = this.childProjects.find(p => p.id == projectId);
                            if (localProject) {
                                localProject.total_amount = totalAmount;
                                localProject.amount = totalAmount; // Update both fields for compatibility
                            }
                        } else {
                            console.warn(`Failed to update child project ${projectId} amount:`, response.data?.message);
                            errorCount++;
                        }
                    } catch (error) {
                        console.error(`Error updating child project ${projectId} amount:`, error);
                        errorCount++;
                    }
                }

                // Force Vue reactivity update
                this.$forceUpdate();

                // Show appropriate message based on results
                if (successCount > 0 && errorCount === 0) {
                    showMessage(`${successCount}件の子プロジェクトの金額が更新されました。`, false);
                } else if (successCount > 0 && errorCount > 0) {
                    showMessage(`${successCount}件の子プロジェクトの金額が更新されましたが、${errorCount}件の更新に失敗しました。`, true);
                } else if (successCount === 0) {
                    showMessage('子プロジェクトの金額の更新に失敗しました。', true);
                }

            } catch (error) {
                console.error('Error updating child project amounts from quotation:', error);
                showMessage('子プロジェクトの金額の更新中にエラーが発生しました。', true);
            }
        },

        async resetChildProjectAmountsFromItems(items) {
            try {
                if (!items || items.length === 0) {
                    return;
                }

                // Get unique project IDs from the items
                const projectIds = [...new Set(items
                    .filter(item => item.project_id)
                    .map(item => item.project_id))];

                if (projectIds.length === 0) {
                    return;
                }

                // Reset amount to 0 for each affected project
                let successCount = 0;
                let errorCount = 0;

                for (const projectId of projectIds) {
                    try {
                        const formData = new FormData();
                        formData.append('id', projectId);
                        formData.append('amount', 0);
                        
                        const response = await axios.post('/api/index.php?model=project&method=updateAmount', formData);
                        
                        if (response.data && response.data.status === 'success') {
                            successCount++;
                            
                            // Also update local childProjects array
                            const localProject = this.childProjects.find(p => p.id == projectId);
                            if (localProject) {
                                localProject.total_amount = 0;
                                localProject.amount = 0; // Update both fields for compatibility
                            }
                        } else {
                            console.warn(`Failed to reset child project ${projectId} amount:`, response.data?.message);
                            errorCount++;
                        }
                    } catch (error) {
                        console.error(`Error resetting child project ${projectId} amount:`, error);
                        errorCount++;
                    }
                }

                // Force Vue reactivity update
                this.$forceUpdate();

                // Show appropriate message based on results
                if (successCount > 0 && errorCount === 0) {
                    showMessage(`${successCount}件の子プロジェクトの金額が0に設定されました。`, false);
                } else if (successCount > 0 && errorCount > 0) {
                    showMessage(`${successCount}件の子プロジェクトの金額が0に設定されましたが、${errorCount}件の更新に失敗しました。`, true);
                } else if (successCount === 0) {
                    showMessage('子プロジェクトの金額の更新に失敗しました。', true);
                }

            } catch (error) {
                console.error('Error resetting child project amounts from items:', error);
                showMessage('子プロジェクトの金額の更新中にエラーが発生しました。', true);
            }
        },

        async resetChildProjectAmountsFromQuotation(quotationId) {
            try {
                // Get quotation data
                let quotationItems = null;
                if (this.editingQuotation && parseInt(this.editingQuotation.id) === parseInt(quotationId)) {
                    quotationItems = this.editingQuotation.items;
                } else {
                    const quotation = this.quotations.find(q => parseInt(q.id) === parseInt(quotationId));
                    if (quotation) {
                        quotationItems = quotation.items;
                    }
                }
                
                // If items not found, load quotation detail from API
                if (!quotationItems || quotationItems.length === 0) {
                    try {
                        const detailResponse = await axios.get(`/api/index.php?model=quotation&method=get&id=${quotationId}`);
                        if (detailResponse.data && detailResponse.data.status === 'success' && detailResponse.data.data) {
                            quotationItems = detailResponse.data.data.items || [];
                            
                            // Update local quotation data
                            const quotation = this.quotations.find(q => parseInt(q.id) === parseInt(quotationId));
                            if (quotation) {
                                quotation.items = quotationItems;
                            }
                        }
                    } catch (error) {
                        // Silently handle error - backend API should have already updated the amounts
                    }
                }
                
                if (!quotationItems || quotationItems.length === 0) {
                    // Backend API should have already updated the amounts
                    return;
                }

                // Use the helper function
                await this.resetChildProjectAmountsFromItems(quotationItems);

            } catch (error) {
                console.error('Error resetting child project amounts from quotation:', error);
                showMessage('子プロジェクトの金額の更新中にエラーが発生しました。', true);
            }
        },

        async resetChildProjectAmountsAfterQuotationDeletion(quotation) {
            try {
                if (!quotation || !quotation.items || quotation.items.length === 0) {
                    return;
                }

                // Get unique project IDs from the deleted quotation
                const projectIds = [...new Set(quotation.items
                    .filter(item => item.project_id)
                    .map(item => item.project_id))];

                if (projectIds.length === 0) {
                    return;
                }

                // Reset amount to 0 for each affected project
                let successCount = 0;
                let errorCount = 0;

                for (const projectId of projectIds) {
                    try {
                        const formData = new FormData();
                        formData.append('id', projectId);
                        formData.append('amount', 0);
                        
                        const response = await axios.post('/api/index.php?model=project&method=updateAmount', formData);
                        
                        if (response.data && response.data.status === 'success') {
                            successCount++;
                            
                            // Also update local childProjects array
                            const localProject = this.childProjects.find(p => p.id == projectId);
                            if (localProject) {
                                localProject.total_amount = 0;
                                localProject.amount = 0; // Update both fields for compatibility
                            }
                        } else {
                            console.warn(`Failed to reset child project ${projectId} amount:`, response.data?.message);
                            errorCount++;
                        }
                    } catch (error) {
                        console.error(`Error resetting child project ${projectId} amount:`, error);
                        errorCount++;
                    }
                }

                // Force Vue reactivity update
                this.$forceUpdate();

                // Show appropriate message based on results
                if (successCount > 0 && errorCount === 0) {
                    showMessage(`${successCount}件の子プロジェクトの金額がリセットされました。`, false);
                } else if (successCount > 0 && errorCount > 0) {
                    showMessage(`${successCount}件の子プロジェクトの金額がリセットされましたが、${errorCount}件のリセットに失敗しました。`, true);
                } else if (successCount === 0) {
                    showMessage('子プロジェクトの金額のリセットに失敗しました。', true);
                }

            } catch (error) {
                console.error('Error resetting child project amounts after quotation deletion:', error);
                showMessage('子プロジェクトの金額のリセット中にエラーが発生しました。', true);
            }
        },

        updateProjectTotalAmount(projectId, itemIndex) {
            const item = this.newQuotation.items[itemIndex];
            if (!item) {
                return;
            }
            
            // Get the old project ID before the change
            const oldProjectId = item._oldProjectId || null;
            
            // Store the new project ID for future reference
            item._oldProjectId = projectId;
            
            // Calculate item amount first
            this.calculateItemAmount(itemIndex);
            
            // If there was a previous project, update its total amount (decrement)
            if (oldProjectId && oldProjectId !== projectId) {
                this.updateChildProjectTotalAmount(oldProjectId);
            }
            
            // Update new project total amount (increment)
            if (projectId) {
                this.updateChildProjectTotalAmount(projectId);
            }
        },

        updateChildProjectTotalAmount(projectId) {
            // Find the project in childProjects
            const project = this.childProjects.find(p => p.id == projectId);
            if (!project) {
                return;
            }
            
            // Calculate total amount for this project from all items
            const projectItems = this.newQuotation.items.filter(item => item.project_id == projectId);
            const projectTotal = projectItems.reduce((sum, item) => sum + (item.amount || 0), 0);
            
            // Include tax in the total amount (total * (1 + tax_rate/100))
            const taxRate = parseFloat(this.newQuotation.tax_rate) || 0;
            const projectTotalWithTax = projectTotal * (1 + taxRate / 100);
            
            // Update the project's total_amount in childProjects array
            project.total_amount = projectTotalWithTax;
            
            // Force Vue reactivity update
            this.$forceUpdate();
        },
        
        clearUnlinkedOrderItems() {
            // Clear project_id from order items that are no longer linked to selected projects
            if (!this.newQuotation.items || this.newQuotation.items.length === 0) {
                return;
            }
            
            let clearedCount = 0;
            this.newQuotation.items.forEach((item, index) => {
                if (item.project_id && !this.selectedChildProjectIds.includes(parseInt(item.project_id))) {
                    item.project_id = '';
                    item._oldProjectId = ''; // Reset old project ID when clearing
                    clearedCount++;
                    
                    // Recalculate item amount since project link was removed
                    this.calculateItemAmount(index);
                }
            });
            
            if (clearedCount > 0) {
                // Update total amounts for all child projects
                this.childProjects.forEach(project => {
                    this.updateChildProjectTotalAmount(project.id);
                });
            }
        },

        updateEditingSetTotal() {
            // This function updates the total amount for the editing set
            // It's called when quantities are changed in the edit set modal
            if (this.editingSet && this.editingSet.products) {
                // The total will be calculated automatically by the template
                // This function can be extended if additional logic is needed
                this.$forceUpdate();
            }
        },

        removeProductFromSet(productId) {
            // Remove a product from the editing set
            if (this.editingSet && this.editingSet.products) {
                this.editingSet.products = this.editingSet.products.filter(p => p.id !== productId);
                this.updateEditingSetTotal();
            }
        },

        // Edit Quotation Methods
        async editQuotation(quotation) {
            // Load quotation details with items
            try {
                const response = await axios.get(`/api/index.php?model=quotation&method=getById&id=${quotation.id}`);
                
                if (response.data && response.data.status === 'success') {
                    // Copy quotation data to editingQuotation
                    this.editingQuotation = JSON.parse(JSON.stringify(response.data.data));
                    
                    // Ensure project_id in items are integers for proper select binding
                    // and fix set_json encoding issues
                    if (this.editingQuotation.items && this.editingQuotation.items.length > 0) {
                        this.editingQuotation.items.forEach(item => {
                            if (item.project_id) {
                                item.project_id = parseInt(item.project_id);
                            }
                            
                            // Fix set_json if it's double-encoded or has HTML entities
                            if (item.is_set && item.set_json) {
                                try {
                                    let setJson = item.set_json;
                                    
                                    // If it's a string, try to parse it
                                    if (typeof setJson === 'string') {
                                        // Remove extra quotes if double-encoded
                                        if (setJson.startsWith('""') && setJson.endsWith('""')) {
                                            setJson = setJson.slice(2, -2);
                                        } else if (setJson.startsWith('"') && setJson.endsWith('"')) {
                                            setJson = setJson.slice(1, -1);
                                        }
                                        
                                        // Decode HTML entities
                                        setJson = setJson.replace(/&quot;/g, '"');
                                        setJson = setJson.replace(/&amp;/g, '&');
                                        setJson = setJson.replace(/&lt;/g, '<');
                                        setJson = setJson.replace(/&gt;/g, '>');
                                        
                                        // Try to parse as JSON
                                        const parsed = JSON.parse(setJson);
                                        item.set_json = parsed; // Store as object for proper processing
                                    }
                                } catch (e) {
                                    console.warn('Failed to parse set_json for item:', item.title, e);
                                    // Keep original value if parsing fails
                                }
                            }
                        });
                    }
                    
                    // Set selected child projects based on saved data or items
                    this.selectedChildProjectIdsForEdit = [];
                    
                    if (this.editingQuotation.selected_child_project_ids && this.editingQuotation.selected_child_project_ids.length > 0) {
                        // Use saved selected child project IDs
                        this.selectedChildProjectIdsForEdit = this.editingQuotation.selected_child_project_ids.map(id => parseInt(id));
                    } else if (this.editingQuotation.items && this.editingQuotation.items.length > 0) {
                        // Fallback: extract from items
                        this.editingQuotation.items.forEach(item => {
                            if (item.project_id && !this.selectedChildProjectIdsForEdit.includes(parseInt(item.project_id))) {
                                this.selectedChildProjectIdsForEdit.push(parseInt(item.project_id));
                            }
                        });
                    } else {
                        // Fallback 2: If no items have project_id, select all child projects to show all options
                        this.selectedChildProjectIdsForEdit = this.childProjects.map(p => parseInt(p.id));
                    }
                    
                    // Set valid_until_type based on valid_until value
                    if (this.editingQuotation.valid_until) {
                        const issueDate = new Date(this.editingQuotation.issue_date);
                        const validUntil = new Date(this.editingQuotation.valid_until);
                        const daysDiff = Math.round((validUntil - issueDate) / (1000 * 60 * 60 * 24));
                        
                        if (daysDiff === 7) {
                            this.editingQuotation.valid_until_type = '1_week';
                        } else if (daysDiff >= 28 && daysDiff <= 31) {
                            this.editingQuotation.valid_until_type = '1_month';
                        } else {
                            this.editingQuotation.valid_until_type = 'custom';
                        }
                    } else {
                        this.editingQuotation.valid_until_type = '1_month';
                    }
                    
                    // Backup form data
                    this.editQuotationFormBackup = JSON.parse(JSON.stringify(this.editingQuotation));
                    
                    // Reset validation errors
                    this.editQuotationValidationErrors = {};
                    
                    // Reset selection states
                    this.selectedOrderItemIndexesForEdit = [];
                    this.allChildProjectsSelectedForEdit = false;
                    this.someChildProjectsSelectedForEdit = false;
                    
                    // Update child project selection state
                    this.updateChildProjectSelectionForEdit();
                    
                    // Load branches and users if not already loaded
                    if (this.quotationBranches.length === 0) {
                        await this.loadQuotationBranches();
                    }
                    if (this.quotationUsers.length === 0) {
                        await this.loadQuotationUsers();
                    }
                    
                    // Ensure current receiver_contact is in the quotationUsers list
                    // This handles cases where the user is no longer active
                    if (this.editingQuotation.receiver_contact) {
                        const existingUser = this.quotationUsers.find(user => user.realname === this.editingQuotation.receiver_contact);
                        if (!existingUser) {
                            // Add the inactive user to the list with a special flag
                            this.quotationUsers.push({
                                id: 'inactive_user',
                                userid: 'inactive_user', 
                                realname: this.editingQuotation.receiver_contact,
                                is_inactive: true
                            });
                        }
                    }
                    
                    // Trigger branch selection to populate address if branch is selected
                    if (this.editingQuotation.selected_branch_id) {
                        this.onBranchSelectForEdit();
                    }
                    
                    // Show modal
                    const editModal = new bootstrap.Modal(document.getElementById('editQuotationModal'));
                    editModal.show();
                    
                    // Initialize date pickers after modal is shown
                    this.$nextTick(() => {
                        this.initializeEditQuotationDatePickers();
                        
                        // Load seal for selected contact
                        if (this.editingQuotation.receiver_contact) {
                            this.onContactSelectForEdit();
                        }
                    });
                } else {
                    const errorMsg = response.data?.message || '見積書の詳細を取得できませんでした。';
                    showMessage(errorMsg, true);
                }
            } catch (error) {
                console.error('Error loading quotation details:', error);
                const errorMsg = error.response?.data?.message || error.message || '見積書の読み込みに失敗しました。';
                showMessage(errorMsg, true);
            }
        },

        // Child project selection methods for edit
        selectAllChildProjectsForEdit() {
            this.selectedChildProjectIdsForEdit = this.childProjects.map(p => p.id);
            this.allChildProjectsSelectedForEdit = true;
            this.someChildProjectsSelectedForEdit = false;
        },

        deselectAllChildProjectsForEdit() {
            this.selectedChildProjectIdsForEdit = [];
            this.allChildProjectsSelectedForEdit = false;
            this.someChildProjectsSelectedForEdit = false;
        },

        toggleAllChildProjectsForEdit() {
            if (this.allChildProjectsSelectedForEdit) {
                this.deselectAllChildProjectsForEdit();
            } else {
                this.selectAllChildProjectsForEdit();
            }
        },

        updateChildProjectSelectionForEdit() {
            const total = this.childProjects.length;
            const selected = this.selectedChildProjectIdsForEdit.length;
            
            this.allChildProjectsSelectedForEdit = selected === total;
            this.someChildProjectsSelectedForEdit = selected > 0 && selected < total;
        },

        // Order item methods for edit
        addOrderItemForEdit() {
            const newItem = {
                project_id: '',
                title: '',
                product_code: '',
                type: '',
                quantity: 1,
                unit: '枚',
                unit_price: 0,
                amount: 0,
                notes: '',
                is_set: false,
                set_json: null
            };
            
            // Auto-select project if only one option available
            this.autoSelectSingleProjectOption(newItem, true);
            
            this.editingQuotation.items.push(newItem);
            // Clear items validation error when items are added
            delete this.editQuotationValidationErrors.items;
            this.$nextTick(() => {
                this.validateProjectIdSelectionForEdit();
            });
        },

        removeOrderItemForEdit(index) {
            if (index >= 0 && index < this.editingQuotation.items.length) {
                const item = this.editingQuotation.items[index];
                if (item.project_id) {
                    this.updateProjectTotalAmountForEdit(item.project_id, index);
                }
                this.editingQuotation.items.splice(index, 1);
                this.validateProjectIdSelectionForEdit();
            }
        },

        calculateItemAmountForEdit(index) {
            if (index >= 0 && index < this.editingQuotation.items.length) {
                const item = this.editingQuotation.items[index];
                const quantity = parseFloat(item.quantity) || 0;
                const unitPrice = parseFloat(item.unit_price) || 0;
                item.amount = quantity * unitPrice;
                
                // Update project total amount if project_id is set
                if (item.project_id) {
                    this.updateProjectTotalAmountForEdit(item.project_id, index);
                }
                
                this.calculateTotalAmountForEdit();
            }
        },

        calculateTotalAmountForEdit() {
            const total = this.editingQuotation.items.reduce((sum, item) => {
                const amount = parseFloat(item.amount) || 0;
                return sum + amount;
            }, 0);
            this.editingQuotation.total_amount = total;
            this.editingQuotation.total_with_tax = total * (1 + this.editingQuotation.tax_rate / 100);
        },

        updateProjectTotalAmountForEdit(projectId, itemIndex) {
            // Find the project in childProjects
            const project = this.childProjects.find(p => p.id == projectId);
            if (!project) {
                return;
            }
            
            // Auto-select the child project if it's not already selected
            if (projectId && !this.selectedChildProjectIdsForEdit.includes(parseInt(projectId))) {
                this.selectedChildProjectIdsForEdit.push(parseInt(projectId));
            }
            
            // Calculate total amount for this project from all items
            const projectItems = this.editingQuotation.items.filter(item => item.project_id == projectId);
            const projectTotal = projectItems.reduce((sum, item) => sum + (item.amount || 0), 0);
            
            // Include tax in the total amount (total * (1 + tax_rate/100))
            const taxRate = parseFloat(this.editingQuotation.tax_rate) || 0;
            const projectTotalWithTax = projectTotal * (1 + taxRate / 100);
            
            // Update the project's total_amount in childProjects array
            project.total_amount = projectTotalWithTax;
            
            // Force Vue reactivity update
            this.$forceUpdate();
        },

        // Selection methods for edit
        toggleSelectOrderItemForEdit(index) {
            const itemIndex = this.selectedOrderItemIndexesForEdit.indexOf(index);
            if (itemIndex > -1) {
                this.selectedOrderItemIndexesForEdit.splice(itemIndex, 1);
            } else {
                this.selectedOrderItemIndexesForEdit.push(index);
            }
            // Force Vue reactivity update
            this.selectedOrderItemIndexesForEdit = [...this.selectedOrderItemIndexesForEdit];
        },

        selectAllOrderItemsForEdit() {
            if (this.allOrderItemsSelectedForEdit) {
                this.selectedOrderItemIndexesForEdit = [];
            } else {
                this.selectedOrderItemIndexesForEdit = (this.editingQuotation.items || []).map((_, idx) => idx);
            }
            // Force Vue reactivity update
            this.$nextTick(() => {
                this.$forceUpdate();
            });
        },

        deleteSelectedOrderItemsForEdit() {
            if (this.selectedOrderItemIndexesForEdit.length === 0) {
                return;
            }
            
            // Sort indices in descending order to avoid index shifting issues
            const sortedIndices = [...this.selectedOrderItemIndexesForEdit].sort((a, b) => b - a);
            
            sortedIndices.forEach(index => {
                if (index >= 0 && index < this.editingQuotation.items.length) {
                    const item = this.editingQuotation.items[index];
                    if (item.project_id) {
                        this.updateProjectTotalAmountForEdit(item.project_id, index);
                    }
                }
            });
            
            // Remove items
            sortedIndices.forEach(index => {
                if (index >= 0 && index < this.editingQuotation.items.length) {
                    this.editingQuotation.items.splice(index, 1);
                }
            });
            
            // Clear selection
            this.selectedOrderItemIndexesForEdit = [];
            this.validateProjectIdSelectionForEdit();
        },

        // Quick project number selection for edit
        quickSelectProjectNumberForCheckedItemsForEdit(projectId) {
            if (this.selectedOrderItemIndexesForEdit.length === 0) {
                showMessage('チェックされた商品がありません。先に商品を選択してください。', true);
                return;
            }
            
            let updatedCount = 0;
            this.selectedOrderItemIndexesForEdit.forEach(index => {
                if (index >= 0 && index < this.editingQuotation.items.length) {
                    const item = this.editingQuotation.items[index];
                    const oldProjectId = item.project_id;
                    item.project_id = projectId;
                    if (oldProjectId && oldProjectId !== projectId) {
                        this.updateProjectTotalAmountForEdit(oldProjectId, index);
                    }
                    updatedCount++;
                }
            });
            
            if (updatedCount > 0) {
                showMessage(`${updatedCount} 件のチェック済み商品にプロジェクト番号が適用されました。`, false);
                this.validateProjectIdSelectionForEdit();
            }
        },

        clearProjectNumbersForCheckedItemsForEdit() {
            if (this.selectedOrderItemIndexesForEdit.length === 0) {
                showMessage('チェックされた商品がありません。先に商品を選択してください。', true);
                return;
            }
            
            let updatedCount = 0;
            this.selectedOrderItemIndexesForEdit.forEach(index => {
                if (index >= 0 && index < this.editingQuotation.items.length) {
                    const item = this.editingQuotation.items[index];
                    const oldProjectId = item.project_id;
                    item.project_id = '';
                    if (oldProjectId) {
                        this.updateProjectTotalAmountForEdit(oldProjectId, index);
                    }
                    updatedCount++;
                }
            });
            
            if (updatedCount > 0) {
                showMessage(`${updatedCount} 件のチェック済み商品のプロジェクト番号がクリアされました。`, false);
                this.validateProjectIdSelectionForEdit();
            }
        },

        // Validation methods for edit
        validateProjectIdSelectionForEdit() {
            // Validate project IDs for all items
            if (this.editingQuotation.items && Array.isArray(this.editingQuotation.items) && this.editingQuotation.items.length > 0) {
                const itemsWithoutProjectId = this.editingQuotation.items.filter((item) => {
                    return !item.project_id || item.project_id === '' || item.project_id === null;
                });
                
                if (itemsWithoutProjectId.length > 0) {
                    this.editQuotationValidationErrors.items = 'すべての商品明細に案件番号を選択してください';
                } else {
                    // Clear error if all items have project_id
                    if (this.editQuotationValidationErrors.items && this.editQuotationValidationErrors.items.includes('案件番号')) {
                        delete this.editQuotationValidationErrors.items;
                    }
                }
            }
        },

        // Modal cleanup helper method
        cleanupModalBackdrop() {
            // Check if there are any open modals before cleanup
            const openModals = document.querySelectorAll('.modal.show');
            
            // Only cleanup if no modals are currently open
            if (openModals.length === 0) {
                // Remove any lingering modal backdrops
                const backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(backdrop => {
                    backdrop.remove();
                });
                
                // Remove modal-open class from body
                document.body.classList.remove('modal-open');
                
                // Reset body style
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }
        },

        // Safe modal close method
        safeCloseModal(modalId) {
            const modalElement = document.getElementById(modalId);
            if (modalElement) {
                const modalInstance = bootstrap.Modal.getInstance(modalElement);
                if (modalInstance) {
                    modalInstance.hide();
                    // Ensure cleanup after modal is hidden
                    setTimeout(() => {
                        this.cleanupModalBackdrop();
                    }, 300);
                }
            }
        },

        // Close edit quotation modal safely
        closeEditQuotationModal() {
            this.safeCloseModal('editQuotationModal');
        },

        // Form reset and update methods
        resetEditQuotationForm() {
            if (this.editQuotationFormBackup) {
                this.editingQuotation = JSON.parse(JSON.stringify(this.editQuotationFormBackup));
                this.selectedChildProjectIdsForEdit = [];
                if (this.editingQuotation.items && this.editingQuotation.items.length > 0) {
                    this.editingQuotation.items.forEach(item => {
                        if (item.project_id && !this.selectedChildProjectIdsForEdit.includes(parseInt(item.project_id))) {
                            this.selectedChildProjectIdsForEdit.push(parseInt(item.project_id));
                        }
                    });
                }
                this.updateChildProjectSelectionForEdit();
                this.editQuotationValidationErrors = {};
                this.selectedOrderItemIndexesForEdit = [];
            }
        },

        async updateQuotation() {
            // Validate form
            if (!this.validateEditQuotationForm()) {
                return;
            }

            this.updatingQuotation = true;
            
            try {
                // Prepare data for update
                const formData = new FormData();
                formData.append('id', this.editingQuotation.id);
                formData.append('subject', this.editingQuotation.subject);
                formData.append('issue_date', this.editingQuotation.issue_date);
                formData.append('quotation_number', this.editingQuotation.quotation_number);
                formData.append('sender_company', this.editingQuotation.sender_company);
                formData.append('sender_address', this.editingQuotation.sender_address);
                formData.append('sender_contact', this.editingQuotation.sender_contact);
                formData.append('receiver_company', this.editingQuotation.receiver_company);
                formData.append('receiver_address', this.editingQuotation.receiver_address);
                formData.append('receiver_contact', this.editingQuotation.receiver_contact);
                formData.append('receiver_seal_path', this.editingQuotation.receiver_seal_path || '');
                formData.append('receiver_tel', this.editingQuotation.receiver_tel);
                formData.append('receiver_fax', this.editingQuotation.receiver_fax);
                formData.append('receiver_registration_number', this.editingQuotation.receiver_registration_number);
                formData.append('status', this.editingQuotation.status);
                formData.append('total_amount', this.editingQuotation.total_amount);
                formData.append('tax_rate', this.editingQuotation.tax_rate);
                formData.append('total_with_tax', this.editingQuotation.total_with_tax);
                formData.append('delivery_date', this.editingQuotation.delivery_date);
                formData.append('delivery_location', this.editingQuotation.delivery_location);
                formData.append('payment_method', this.editingQuotation.payment_method);
                formData.append('valid_until', this.editingQuotation.valid_until);
                formData.append('notes', this.editingQuotation.notes);
                formData.append('parent_project_id', this.editingQuotation.parent_project_id);
                formData.append('selected_branch_id', this.editingQuotation.selected_branch_id || '');
                
                // Add items as JSON string (same as createQuotation)
                if (this.editingQuotation.items && this.editingQuotation.items.length > 0) {
                    // Create a deep copy of items and properly handle set_json
                    const itemsCopy = this.editingQuotation.items.map(item => {
                        // Create a clean copy without database-specific fields
                        const cleanItem = {
                            project_id: item.project_id,
                            title: item.title,
                            product_code: item.product_code,
                            type: item.type || '',
                            quantity: item.quantity,
                            unit: item.unit,
                            unit_price: item.unit_price,
                            amount: item.amount,
                            notes: item.notes,
                            is_set: item.is_set ? true : false
                        };
                        
                        // Handle set_json with proper encoding to avoid JSON syntax errors
                        if (item.is_set && item.set_json) {
                            // Handle set_json properly - convert to object if it's a string, then back to object for proper JSON encoding
                            if (typeof item.set_json === 'string') {
                                try {
                                    // Parse the JSON string to object so it gets properly encoded when the whole item is stringified
                                    cleanItem.set_json = JSON.parse(item.set_json);
                                    } catch (e) {
                                    console.error('Error parsing set_json in edit:', e, item.set_json);
                                    // If parsing fails, keep as string but log error
                                    cleanItem.set_json = item.set_json;
                                    }
                            } else if (typeof item.set_json === 'object') {
                                // Already an object, keep as is
                                cleanItem.set_json = item.set_json;
                                } else {
                                // Unknown type, convert to string first then parse
                                try {
                                    cleanItem.set_json = JSON.parse(String(item.set_json));
                            } catch (e) {
                                    cleanItem.set_json = item.set_json;
                                }
                            }
                        }
                        
                        return cleanItem;
                    });
                    
                    const itemsJson = JSON.stringify(itemsCopy);
                    formData.append('items', itemsJson);
                    
                    // Debug: Check if items JSON is valid
                    try {
                        const parsed = JSON.parse(itemsJson);
                    } catch (e) {
                        console.error('Items JSON is invalid:', e);
                    }
                } else {
                    // Send empty array as JSON string
                    formData.append('items', JSON.stringify([]));
                }
                
                // Add selected child project IDs
                if (this.selectedChildProjectIdsForEdit && this.selectedChildProjectIdsForEdit.length > 0) {
                    this.selectedChildProjectIdsForEdit.forEach((id, index) => {
                        formData.append(`selected_child_project_ids[${index}]`, id);
                    });
                }
                
                // Add updated_by field
                formData.append('updated_by', CURRENT_USER_NAME);

                // Update quotation
                const response = await axios.post('/api/index.php?model=quotation&method=update', formData);
                
                if (response.data && response.data.status === 'success') {
                    showMessage('見積書が正常に更新されました。', false);
                    
                    // Update child project amounts based on status
                    if (this.editingQuotation.status === 'キャンセル' || this.editingQuotation.status === '却下') {
                        // Set project amounts to 0 for cancelled or rejected quotations
                        await this.resetChildProjectAmountsFromQuotation(parseInt(this.editingQuotation.id));
                    } else {
                        // Update project amounts from quotation items for other statuses
                        await this.updateChildProjectAmountsFromQuotation(parseInt(this.editingQuotation.id));
                    }
                    
                    // Refresh data
                    await this.loadQuotations();
                    await this.loadChildProjects();
                    
                    // Close modal safely
                    this.safeCloseModal('editQuotationModal');
                } else {
                    showParentProjectError(response.data?.message || '見積書の更新に失敗しました。', response && response.data);
                }
            } catch (error) {
                console.error('Error updating quotation:', error);
                showParentProjectError('見積書の更新中にエラーが発生しました。', error);
            } finally {
                this.updatingQuotation = false;
            }
        },

        validateEditQuotationForm() {
            let isValid = true;
            this.editQuotationValidationErrors = {};

            // Validate required fields
            if (!this.editingQuotation.issue_date) {
                this.editQuotationValidationErrors.issue_date = '発行日は必須です';
                isValid = false;
            }

            if (!this.editingQuotation.quotation_number) {
                this.editQuotationValidationErrors.quotation_number = '見積番号は必須です';
                isValid = false;
            }

            if (!this.editingQuotation.sender_company) {
                this.editQuotationValidationErrors.sender_company = '受注者会社名は必須です';
                isValid = false;
            }

            if (!this.editingQuotation.receiver_company) {
                this.editQuotationValidationErrors.receiver_company = '発注者会社名は必須です';
                isValid = false;
            }

            // Validate subject
            if (!this.editingQuotation.subject || this.editingQuotation.subject.trim() === '') {
                this.editQuotationValidationErrors.subject = '件名は必須です';
                isValid = false;
            }

            // Items validation - only check if items exist
            if (!this.editingQuotation.items || !Array.isArray(this.editingQuotation.items) || this.editingQuotation.items.length === 0) {
                this.editQuotationValidationErrors.items = '商品明細は必須です';
                isValid = false;
            } else {
                // Clear items error if validation passes
                delete this.editQuotationValidationErrors.items;
                
                // Validate that all items have project_id
                const itemsWithoutProjectId = this.editingQuotation.items.filter((item, index) => {
                    return !item.project_id || item.project_id === '' || item.project_id === null;
                });
                
                if (itemsWithoutProjectId.length > 0) {
                    this.editQuotationValidationErrors.items = 'すべての商品明細に案件番号を選択してください';
                    isValid = false;
                }
            }

            // Delivery date is optional - user can input freely or use date picker
            
            // Validate delivery location
            if (!this.editingQuotation.delivery_location || this.editingQuotation.delivery_location.trim() === '') {
                this.editQuotationValidationErrors.delivery_location = '納入場所は必須です';
                isValid = false;
            }
            
            // Validate payment method
            if (!this.editingQuotation.payment_method || this.editingQuotation.payment_method.trim() === '') {
                this.editQuotationValidationErrors.payment_method = '取引方法は必須です';
                isValid = false;
            }
            
            // Validate valid until
            if (!this.editingQuotation.valid_until || this.editingQuotation.valid_until.trim() === '') {
                this.editQuotationValidationErrors.valid_until = '有効期限は必須です';
                isValid = false;
            }

            // Show SweetAlert2 notification if there are validation errors
            if (!isValid) {
                const errorMessages = Object.values(this.editQuotationValidationErrors).filter(error => error !== undefined && error !== null && error !== '');
                const errorList = errorMessages.map(error => `• ${error}`).join('<br>');
                
                Swal.fire({
                    title: '入力エラー',
                    html: `以下の項目を確認してください：<br><br>${errorList}`,
                    icon: 'error',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#d33'
                });
            }

            return isValid;
        },

        // Date picker methods for edit
        openDeliveryDatePickerForEdit() {
            // Initialize Flatpickr for delivery date
            const deliveryDateInput = document.getElementById('edit_quotation_delivery_date');
            if (deliveryDateInput) {
                const picker = flatpickr(deliveryDateInput, {
                    dateFormat: 'Y年n月j日',
                    altFormat: 'Y年n月j日',
                    locale: 'ja',
                    allowInput: true,
                    clickOpens: true
                });
                picker.open();
            }
        },

        clearDeliveryDateForEdit() {
            this.editingQuotation.delivery_date = '';
        },

        onValidUntilTypeChangeForEdit() {
            if (this.editingQuotation.valid_until_type === 'custom') {
                this.$nextTick(() => {
                    const validUntilInput = document.getElementById('edit_quotation_valid_until');
                    if (validUntilInput) {
                        flatpickr(validUntilInput, {
                            dateFormat: 'Y-m-d',
                            locale: 'ja',
                            allowInput: true,
                            clickOpens: true,
                            minDate: 'today'
                        });
                    }
                });
            } else {
                // Calculate valid_until based on type
                if (this.editingQuotation.issue_date) {
                    const issueDate = new Date(this.editingQuotation.issue_date);
                    let validUntil = new Date(issueDate);
                    
                    if (this.editingQuotation.valid_until_type === '1_week') {
                        validUntil.setDate(validUntil.getDate() + 7);
                    } else if (this.editingQuotation.valid_until_type === '1_month') {
                        validUntil.setMonth(validUntil.getMonth() + 1);
                    }
                    
                    // Format date as YYYY-MM-DD
                    const year = validUntil.getFullYear();
                    const month = String(validUntil.getMonth() + 1).padStart(2, '0');
                    const day = String(validUntil.getDate()).padStart(2, '0');
                    this.editingQuotation.valid_until = `${year}-${month}-${day}`;
                }
            }
        },

        // Branch and contact selection methods for edit
        onBranchSelectForEdit() {
            // Handle branch selection for edit
            if (this.editingQuotation.selected_branch_id) {
                const selectedBranch = this.quotationBranches.find(branch => branch.id == this.editingQuotation.selected_branch_id);
                if (selectedBranch) {
                    this.editingQuotation.receiver_company = selectedBranch.company_name || selectedBranch.name;
                    
                    // Always update address when branch changes in edit mode
                    // Include postal_code in the address field
                    const addressParts = [];
                    if (selectedBranch.postal_code) {
                        addressParts.push(`〒${selectedBranch.postal_code}`);
                    }
                    if (selectedBranch.address1) {
                        addressParts.push('　');
                        addressParts.push(selectedBranch.address1);
                    }
                    if (selectedBranch.address2) {
                        addressParts.push('\n');
                        addressParts.push(selectedBranch.address2);
                    }
                    this.editingQuotation.receiver_address = addressParts.join('');
                    this.editingQuotation.receiver_tel = selectedBranch.tel || '';
                    this.editingQuotation.receiver_fax = selectedBranch.fax || '';
                    this.editingQuotation.receiver_registration_number = selectedBranch.registration_number || '';
                }
            } else {
                // Clear fields if no branch is selected
                this.editingQuotation.receiver_company = '';
                this.editingQuotation.receiver_address = '';
                this.editingQuotation.receiver_tel = '';
                this.editingQuotation.receiver_fax = '';
                this.editingQuotation.receiver_registration_number = '';
            }
        },

        async onContactSelectForEdit() {
            // Clear previous seal
            this.selectedContactSealForEdit = null;
            
            if (!this.editingQuotation.receiver_contact) {
                // Keep existing seal path if just clearing contact
                return;
            }
            
            // Find the selected user to get their userid
            const selectedUser = this.quotationUsers.find(user => user.realname === this.editingQuotation.receiver_contact);
            if (!selectedUser) {
                return;
            }
            
            // If user is inactive, preserve existing seal path
            if (selectedUser.is_inactive) {
                console.log('Selected inactive user, preserving existing seal path');
                // Don't clear the existing receiver_seal_path
                return;
            }
            
            // Load seal for the selected user using the same logic as create modal
            await this.loadContactSealForEdit(selectedUser.userid);
            
            // Save seal path to quotation data
            if (this.selectedContactSealForEdit && this.selectedContactSealForEdit.image_path) {
                this.editingQuotation.receiver_seal_path = this.selectedContactSealForEdit.image_path;
            } else {
                this.editingQuotation.receiver_seal_path = '';
            }
        },

        async loadContactSealForEdit(userId) {
            try {
                const response = await axios.get(`/api/index.php?model=seal&method=getSealsByUser&user_id=${userId}`);
                if (response.data && response.data.length > 0) {
                    // Get the first active seal for this user
                    this.selectedContactSealForEdit = response.data[0];
                } else {
                    this.selectedContactSealForEdit = null;
                }
            } catch (error) {
                console.error('Error loading contact seal for edit:', error);
                this.selectedContactSealForEdit = null;
            }
        },






        initializeEditQuotationDatePickers() {
            // Initialize issue date picker
            const issueDateInput = document.getElementById('edit_quotation_issue_date');
            if (issueDateInput) {
                flatpickr(issueDateInput, {
                    dateFormat: 'Y-m-d',
                    locale: 'ja',
                    defaultDate: this.editingQuotation.issue_date || 'today',
                    allowInput: true
                });
            }

            // Initialize delivery date picker using hidden input
            const deliveryDatePickerEl = document.getElementById('edit_quotation_delivery_date_picker');
            if (deliveryDatePickerEl) {
                if (deliveryDatePickerEl._flatpickr) {
                    deliveryDatePickerEl._flatpickr.destroy();
                }
                const modalElement = document.getElementById('editQuotationModal');
                deliveryDatePickerEl._flatpickr = flatpickr(deliveryDatePickerEl, {
                    dateFormat: 'Y年n月j日',
                    altFormat: 'Y年n月j日',
                    locale: 'ja',
                    allowInput: false,
                    clickOpens: false,
                    static: false,
                    appendTo: modalElement ? modalElement : document.body,
                    onChange: (selectedDates, dateStr) => {
                        // Update the visible input with the selected date
                        if (dateStr) {
                            this.editingQuotation.delivery_date = dateStr;
                        }
                    }
                });
                
                // Set initial date if available and it's a valid date
                if (this.editingQuotation.delivery_date) {
                    try {
                        const date = new Date(this.editingQuotation.delivery_date);
                        if (!isNaN(date.getTime())) {
                            deliveryDatePickerEl._flatpickr.setDate(this.editingQuotation.delivery_date);
                        }
                    } catch (e) {
                        // Ignore invalid date errors
                    }
                }
            }

            // Initialize valid until picker if custom
            if (this.editingQuotation.valid_until_type === 'custom') {
                const validUntilInput = document.getElementById('edit_quotation_valid_until');
                if (validUntilInput) {
                    flatpickr(validUntilInput, {
                        dateFormat: 'Y-m-d',
                        locale: 'ja',
                        defaultDate: this.editingQuotation.valid_until,
                        allowInput: true,
                        minDate: 'today'
                    });
                }
            }
        },





        clearUnlinkedOrderItemsForEdit() {
            // Clear project_id from order items that are no longer linked to selected projects
            if (!this.editingQuotation.items || this.editingQuotation.items.length === 0) {
                return;
            }
            
            let clearedCount = 0;
            this.editingQuotation.items.forEach((item, index) => {
                if (item.project_id && !this.selectedChildProjectIdsForEdit.includes(parseInt(item.project_id))) {
                    item.project_id = '';
                    clearedCount++;
                    
                    // Recalculate item amount since project link was removed
                    this.calculateItemAmountForEdit(index);
                }
            });
            
            if (clearedCount > 0) {
                // Update total amounts for all child projects
                this.childProjects.forEach(project => {
                    this.updateProjectTotalAmountForEdit(project.id);
                });
            }
        },

        // Sortable functionality
        initializeSortable() {
            // Initialize sortable for create quotation modal
            const createSortableEl = document.getElementById('quotation-items-sortable');
            if (createSortableEl && typeof Sortable !== 'undefined') {
                this.createQuotationSortable = Sortable.create(createSortableEl, {
                    handle: '.drag-handle',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    dragClass: 'sortable-drag',
                    onEnd: (evt) => {
                        this.reorderQuotationItems(evt.oldIndex, evt.newIndex, false);
                    }
                });
            }

            // Initialize sortable for edit quotation modal
            const editSortableEl = document.getElementById('edit-quotation-items-sortable');
            if (editSortableEl && typeof Sortable !== 'undefined') {
                this.editQuotationSortable = Sortable.create(editSortableEl, {
                    handle: '.drag-handle',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    dragClass: 'sortable-drag',
                    onEnd: (evt) => {
                        this.reorderQuotationItems(evt.oldIndex, evt.newIndex, true);
                    }
                });
            }
        },

        reorderQuotationItems(oldIndex, newIndex, isEditMode) {
            if (oldIndex === newIndex) return;

            const targetArray = isEditMode ? this.editingQuotation.items : this.newQuotation.items;
            
            // Move the item from oldIndex to newIndex
            const movedItem = targetArray.splice(oldIndex, 1)[0];
            targetArray.splice(newIndex, 0, movedItem);

            // Update selected item indexes to maintain selection after reorder
            if (isEditMode) {
                this.updateSelectedIndexesAfterReorder(oldIndex, newIndex, this.selectedOrderItemIndexesForEdit);
            } else {
                this.updateSelectedIndexesAfterReorder(oldIndex, newIndex, this.selectedOrderItemIndexes);
            }

            // Recalculate totals
            if (isEditMode) {
                this.calculateTotalAmountForEdit();
            } else {
                this.calculateTotalAmount();
            }
        },

        updateSelectedIndexesAfterReorder(oldIndex, newIndex, selectedIndexes) {
            // Update the selected indexes array to reflect the new positions after drag & drop
            const updatedIndexes = selectedIndexes.map(index => {
                if (index === oldIndex) {
                    // The dragged item moves to newIndex
                    return newIndex;
                } else if (oldIndex < newIndex && index > oldIndex && index <= newIndex) {
                    // Items between oldIndex and newIndex shift left
                    return index - 1;
                } else if (oldIndex > newIndex && index >= newIndex && index < oldIndex) {
                    // Items between newIndex and oldIndex shift right
                    return index + 1;
                } else {
                    // Other items remain at the same index
                    return index;
                }
            });

            // Replace the original array with updated indexes
            selectedIndexes.length = 0;
            selectedIndexes.push(...updatedIndexes);
        },

        destroySortable() {
            if (this.createQuotationSortable) {
                this.createQuotationSortable.destroy();
                this.createQuotationSortable = null;
            }
            if (this.editQuotationSortable) {
                this.editQuotationSortable.destroy();
                this.editQuotationSortable = null;
            }
        },

        getQuotationProjectNumbers(quotation) {
            // Check if required data is available
            if (!quotation || !quotation.selected_child_project_ids) {
                return [];
            }
            
            if (!this.childProjects || this.childProjects.length === 0) {
                console.warn('Child projects not loaded yet');
                return [];
            }
            
            try {
                // Handle different possible formats of selected_child_project_ids
                let selectedIds = [];
                
                if (typeof quotation.selected_child_project_ids === 'string') {
                    // It's a comma-separated string
                    selectedIds = quotation.selected_child_project_ids.split(',')
                        .map(id => id.trim())
                        .filter(id => id && id !== '');
                } else if (Array.isArray(quotation.selected_child_project_ids)) {
                    // It's already an array
                    selectedIds = quotation.selected_child_project_ids;
                } else {
                    console.warn('Unexpected format for selected_child_project_ids:', quotation.selected_child_project_ids);
                    return [];
                }
                
                if (selectedIds.length === 0) {
                    return [];
                }
                
                // Map the IDs to project numbers
                const projectNumbers = selectedIds.map(id => {
                    const childProject = this.childProjects.find(cp => cp.id == id || cp.id == parseInt(id));
                    return childProject ? childProject.project_number : null;
                }).filter(projectNumber => projectNumber); // Remove null values
                
                return projectNumbers;
            } catch (error) {
                console.error('Error parsing quotation project IDs:', error, quotation);
                return [];
            }
        },
        
        // Quotation history methods
        async showQuotationHistory(quotation) {
            try {
                this.selectedQuotationForHistory = quotation;
                this.quotationHistory = [];
                
                // Load quotation history
                const response = await axios.get(`/api/index.php?model=quotation&method=getLogs&quotation_id=${quotation.id}`);
                if (response.data && Array.isArray(response.data)) {
                    this.quotationHistory = response.data;
                }
                
                // Show the modal
                const modal = new bootstrap.Modal(document.getElementById('quotationHistoryModal'));
                modal.show();
            } catch (error) {
                console.error('Error loading quotation history:', error);
                this.quotationHistory = [];
                
                // Still show the modal even if loading fails
                const modal = new bootstrap.Modal(document.getElementById('quotationHistoryModal'));
                modal.show();
            }
        },
        
        historyIcon(action) {
            switch(action) {
                case 'created': return 'fa fa-pencil-alt text-primary';
                case 'updated': return 'fa fa-sync text-info';
                case 'status_changed': return 'fa fa-random text-primary';
                case 'deleted': return 'fa fa-trash text-danger';
                default: return 'fa fa-history text-secondary';
            }
        },
        
        getLogBadgeClass(log, field) {
            const value = log[field];
            if (log.action === 'status_changed' || this.isProjectStatusKey(value)) {
                return 'badge ' + this.getProjectStatusBadgeClass(value);
            }
            return field === 'value1' ? 'badge bg-secondary' : 'badge bg-primary';
        },
        
        getStatusBadgeClass(status) {
            switch(status) {
                case '下書き': return 'bg-secondary';
                case '発行済み': return 'bg-info';
                case '承認済み': return 'bg-success';
                case '却下': return 'bg-danger';
                case '調整': return 'bg-warning';
                case 'キャンセル': return 'bg-dark';
                default: return 'bg-secondary';
            }
        },
        
        getStatusLabel(status) {
            switch(status) {
                case '下書き': return '下書き';
                case '発行済み': return '発行済み';
                case '承認済み': return '承認済み';
                case '却下': return '却下';
                case '調整': return '調整';
                case 'キャンセル': return 'キャンセル';
                default: return status;
            }
        },
        
        formatShortDateTime(datetime) {
            return formatProjectDateTimeForDisplay(datetime);
        },
        
        // Customer modal methods
        async loadCategories() {
            try {
                const response = await axios.get('/api/index.php?model=customer&method=list_category');
                this.categories = response.data;
            } catch (error) {
                console.error('Error loading categories:', error);
                showMessage('カテゴリーの読み込みに失敗しました。', true);
            }
        },

        async openNewCustomerModal() {
            if (this.categories.length === 0) {
                await this.loadCategories();
            }
            if (this.departments.length === 0) {
                await this.loadDepartments();
            }
            this.resetNewCustomerData();
            const modalEl = document.getElementById('newCustomerModal');
            if (modalEl) {
                const onShown = () => {
                    this.$nextTick(() => {
                        this.initNewCustomerGuisDepartmentSelect2();
                    });
                };
                modalEl.addEventListener('shown.bs.modal', onShown, { once: true });
                const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modal.show();
            }
        },

        initNewCustomerGuisDepartmentSelect2() {
            const el = this.$refs.newCustomerGuisDepartmentSelect;
            if (!el) return;
            const $el = $(el);
            if ($el.data('select2')) {
                $el.select2('destroy');
            }
            $el.select2({ placeholder: '選択してください', allowClear: true });
            $el.off('change').on('change', (event) => {
                const val = $(event.target).val();
                this.newCustomer.guis_department = val ? val : [];
            });
            const ids = Array.isArray(this.newCustomer.guis_department) ? this.newCustomer.guis_department : [];
            $el.val(ids).trigger('change');
        },

        resetNewCustomerData() {
            this.newCustomer = {
                company_name: '大東建託株式会社',
                company_name_kana: '',
                name: '',
                name_kana: '',
                branch: '本社',
                position: '',
                department: '',
                title: '',
                tel: '',
                fax: '',
                phone: '',
                email: '',
                zip: '',
                address1: '',
                address2: '',
                memo: '',
                status: 1,
                category_id: this.categories.length > 0 ? this.categories[0].id : 0,
                guis_department: []
            };
            this.customerErrors = { company_name: '', name: '', branch: '', guis_department: '' };
        },

        async saveNewCustomer() {
            this.customerErrors = { company_name: '', name: '', branch: '', guis_department: '' };
            let hasError = false;
            if (!this.newCustomer.company_name) {
                this.customerErrors.company_name = '会社名は必須です。';
                hasError = true;
            }
            if (!this.newCustomer.name) {
                this.customerErrors.name = '担当者名は必須です。';
                hasError = true;
            }
            if (!this.newCustomer.guis_department || this.newCustomer.guis_department.length === 0) {
                this.customerErrors.guis_department = '自社担当部署名は必須です。';
                hasError = true;
            }
            if (hasError) return;
            if (!this.newCustomer.branch || this.newCustomer.branch.trim() === '') {
                this.newCustomer.branch = '本社';
            }
            try {
                const response = await axios.post('/api/index.php?model=customer&method=add_customer', this.newCustomer, {
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                });
                if (response.data.status === 'success') {
                    showMessage('顧客を保存しました。');
                    const company_name = this.newCustomer.company_name;
                    const branch_name = this.newCustomer.branch;
                    const contact_name = this.newCustomer.name;
                    $('#newCustomerModal').modal('hide');
                    this.resetNewCustomerData();
                    if (this.childCustomerModalContext) {
                        await this.applyNewCustomerToChildProject(company_name, branch_name, contact_name);
                        return;
                    }
                    this.parentProject.company_name = company_name;
                    this.parentProject.branch_name = branch_name;
                    this.parentProject.contact_name = contact_name;
                    this.customerDisplay = this.customerDisplay || {};
                    this.customerDisplay.company_name = company_name;
                    this.customerDisplay.branch_name = branch_name;
                    this.customerDisplay.contact_name = contact_name;
                    await this.loadCompanies();
                    this.$nextTick(() => {
                        this.initCompanySelect2();
                        this.initBranchSelect2();
                        this.initContactSelect2();
                    });
                } else {
                    showParentProjectError(response.data.message_code || '顧客の保存に失敗しました。', response && response.data);
                }
            } catch (error) {
                console.error('Error saving customer:', error);
                showParentProjectError('顧客の保存に失敗しました。', error);
            }
        },

        searchAddressNewCustomer() {
            const postalCode = (this.newCustomer.zip || '').toString().replace(/\D/g, '');
            if (postalCode.length >= 7) {
                const apiUrl = `https://zipcloud.ibsnet.co.jp/api/search?zipcode=${postalCode}`;
                axios.get(apiUrl)
                    .then(response => {
                        if (response.data.results && response.data.results.length > 0) {
                            const result = response.data.results[0];
                            this.newCustomer.address1 = result.address1 || '';
                            this.newCustomer.address2 = result.address2 || '';
                        } else {
                            showMessage('郵便番号が見つかりません。', true);
                        }
                    })
                    .catch(error => {
                        console.error('Error searching address:', error);
                        showMessage('住所の検索に失敗しました。', true);
                    });
            } else {
                showMessage('郵便番号が正しくありません。', true);
            }
        },

        async loadCustomerDataByProject() {
            // 優先的にcustomer_idで顧客情報を取得する
            if (this.parentProject.customer_id) {
                try {
                    const response = await axios.get(`/api/index.php?model=customer&method=get&id=${this.parentProject.customer_id}`);
                    if (response.data && response.data.status === 'success' && response.data.data) {
                        return response.data.data;
                    }
                } catch (error) {
                    console.error('Error loading customer by id:', error);
                    // フォールバックとして従来の検索ロジックを使用する
                }
            }

            // customer_idが無い場合、従来どおり会社名＋支店名＋担当者名で検索
            if (!this.parentProject.contact_name || !this.parentProject.company_name) {
                return null;
            }

            try {
                // Load categories if not already loaded
                if (this.categories.length === 0) {
                    await this.loadCategories();
                }
                
                // Find customer by contact name, company name, and branch name
                let customer = null;
                for (const category of this.categories) {
                    const customersResponse = await axios.get(`/api/index.php?model=customer&method=list_customer&category_id=${category.id}`);
                    if (customersResponse.data.status === 'success' && customersResponse.data.data) {
                        customer = customersResponse.data.data.find(c => 
                            c.name === this.parentProject.contact_name &&
                            c.company_name === this.parentProject.company_name &&
                            c.branch === this.parentProject.branch_name
                        );
                        if (customer) break;
                    }
                }

                return customer;
            } catch (error) {
                console.error('Error loading customer data:', error);
                return null;
            }
        },

        async loadCustomerDisplayInfo() {
            // Reset current display
            this.customerDisplay = {
                company_name: '',
                branch_name: '',
                contact_name: ''
            };

            if (!this.parentProject) {
                return;
            }

            try {
                const customer = await this.loadCustomerDataByProject();
                if (customer) {
                    this.customerDisplay.company_name = customer.company_name || '';
                    this.customerDisplay.branch_name = customer.branch || '';
                    this.customerDisplay.contact_name = customer.name || '';
                }
            } catch (error) {
                console.error('Error loading customer display info:', error);
            }
        },

        async showCustomerInfoModal(customer) {
            await this.loadDepartments();
            await this.loadCategories();

            if (typeof customer.guis_department === 'string') {
                customer.guis_department = customer.guis_department ? customer.guis_department.split(',').map(id => id.trim()) : [];
            } else if (!Array.isArray(customer.guis_department)) {
                customer.guis_department = [];
            }

            this.selectedCustomer = { ...customer };
            $('#customerInfoModal').modal('show');

            setTimeout(() => {
                const selectElement = $(this.$refs.customerGuisDepartmentSelect);
                if (selectElement.length) {
                    if (selectElement.hasClass('select2-hidden-accessible')) {
                        selectElement.select2('destroy');
                    }
                    selectElement.select2({
                        placeholder: '部署を選択してください',
                        allowClear: true,
                        width: '100%',
                        dropdownParent: $('#customerInfoModal')
                    });
                    selectElement.val(this.selectedCustomer.guis_department).trigger('change');
                    selectElement.off('change.customerModal').on('change.customerModal', (event) => {
                        const val = $(event.target).val();
                        this.selectedCustomer.guis_department = val ? val : [];
                    });
                }
            }, 300);
        },

        async openCustomerInfoModal() {
            if (!this.parentProject || (!this.parentProject.contact_name && !this.parentProject.customer_id)) {
                showMessage('担当者が選択されていません。', true);
                return;
            }

            try {
                const customer = await this.loadCustomerDataByProject();
                if (customer) {
                    this.customerInfoModalContext = 'parent';
                    this.customerInfoModalChildProjectId = null;
                    await this.showCustomerInfoModal(customer);
                } else {
                    showMessage('顧客情報が見つかりません。', true);
                }
            } catch (error) {
                console.error('Error loading customer info:', error);
                showMessage('顧客情報の読み込みに失敗しました。', true);
            }
        },

        async openChildProjectCustomerInfoModal(project) {
            if (!project || !this.shouldShowChildProjectCustomer(project)) {
                return;
            }

            await this.openChildProjectCustomerInfoById(project.customer_id, project.id);
        },

        async openEditingChildProjectCustomerInfoModal() {
            const customerId = this.editingChildProject && this.editingChildProject.customer_id;
            const projectId = this.editingChildProject && this.editingChildProject.id;
            if (!customerId) {
                showMessage('担当者が選択されていません。', true);
                return;
            }
            await this.openChildProjectCustomerInfoById(customerId, projectId);
        },

        async openChildProjectCustomerInfoById(customerId, projectId = null) {
            if (!customerId) {
                showMessage('担当者が選択されていません。', true);
                return;
            }

            try {
                const response = await axios.get(`/api/index.php?model=customer&method=get&id=${customerId}`);
                if (response.data && response.data.status === 'success' && response.data.data) {
                    this.customerInfoModalContext = 'child';
                    this.customerInfoModalChildProjectId = projectId || null;
                    await this.showCustomerInfoModal(response.data.data);
                } else {
                    showMessage('顧客情報が見つかりません。', true);
                }
            } catch (error) {
                console.error('Error loading child project customer info:', error);
                showMessage('顧客情報の読み込みに失敗しました。', true);
            }
        },

        async updateCustomer() {
            // Reset errors
            this.customerErrors = { company_name: '', name: '', branch: '', guis_department: '' };
            let hasError = false;
            
            if (!this.selectedCustomer.company_name) {
                this.customerErrors.company_name = '会社名は必須です。';
                hasError = true;
            }
            if (!this.selectedCustomer.name) {
                this.customerErrors.name = '担当者名は必須です。';
                hasError = true;
            }
            if (!this.selectedCustomer.branch || this.selectedCustomer.branch.trim() === '') {
                this.customerErrors.branch = '支店名は必須です。';
                hasError = true;
            }
            if (!this.selectedCustomer.guis_department || this.selectedCustomer.guis_department.length === 0) {
                this.customerErrors.guis_department = '自社担当部署名は必須です。';
                hasError = true;
            }
            if (hasError) return;

            const isChildCustomerContext = this.customerInfoModalContext === 'child';
            const confirmResult = await Swal.fire({
                title: '確認',
                text: 'お客様情報を更新すると、このお客様の情報を利用している他の建物の情報もすべて更新されます。更新しますか？',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '更新する',
                cancelButtonText: 'キャンセル',
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#6c757d'
            });
            if (!confirmResult.isConfirmed) return;

            this.updatingCustomer = true;

            try {
                // Prepare data for submission - convert guis_department array to string
                const customerData = { ...this.selectedCustomer };
                if (Array.isArray(customerData.guis_department)) {
                    customerData.guis_department = customerData.guis_department.join(',');
                }

                const response = await axios.post(`/api/index.php?model=customer&method=edit_customer&id=${this.selectedCustomer.id}`, customerData, {
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }
                });
                
                if (response.data.status === 'success') {
                    showMessage('顧客情報を更新しました。');

                    if (isChildCustomerContext) {
                        await this.loadChildProjects();
                        $('#customerInfoModal').modal('hide');
                        this.selectedCustomer = null;
                        this.customerInfoModalContext = 'parent';
                        this.customerInfoModalChildProjectId = null;
                        return;
                    }
                    
                    // Always update customerDisplay with the latest customer info
                    this.customerDisplay.company_name = this.selectedCustomer.company_name || '';
                    this.customerDisplay.branch_name = this.selectedCustomer.branch || '';
                    this.customerDisplay.contact_name = this.selectedCustomer.name || '';
                    
                    // Update all parent projects with the same customer_id if customer info changed
                    if (this.selectedCustomer.name !== this.parentProject.contact_name ||
                        this.selectedCustomer.company_name !== this.parentProject.company_name ||
                        this.selectedCustomer.branch !== this.parentProject.branch_name) {
                        
                        try {
                            // Update all parent projects with the same customer_id
                            const formData = new URLSearchParams();
                            formData.append('customer_id', this.selectedCustomer.id);
                            formData.append('company_name', this.selectedCustomer.company_name);
                            formData.append('branch_name', this.selectedCustomer.branch);
                            formData.append('contact_name', this.selectedCustomer.name);
                            
                            console.log('Sending update request:', {
                                customer_id: this.selectedCustomer.id,
                                company_name: this.selectedCustomer.company_name,
                                branch_name: this.selectedCustomer.branch,
                                contact_name: this.selectedCustomer.name
                            });
                            
                            const updateAllResponse = await axios.post(`/api/index.php?model=parentproject&method=updateCustomerInfoForAllProjects`, formData, {
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                }
                            });
                            
                            console.log('Update response:', updateAllResponse.data);
                            
                            if (updateAllResponse.data.status === 'success') {
                                console.log(`Updated ${updateAllResponse.data.affected_rows} parent project(s)`);
                                
                                // Update current parent project data in memory
                                this.parentProject.contact_name = this.selectedCustomer.name;
                                this.parentProject.company_name = this.selectedCustomer.company_name;
                                this.parentProject.branch_name = this.selectedCustomer.branch;
                                
                                // Update customer display info directly from selectedCustomer
                                this.customerDisplay.company_name = this.selectedCustomer.company_name || '';
                                this.customerDisplay.branch_name = this.selectedCustomer.branch || '';
                                this.customerDisplay.contact_name = this.selectedCustomer.name || '';
                            } else {
                                console.error('Failed to update all parent projects:', updateAllResponse.data.message);
                            }
                        } catch (error) {
                            console.error('Error updating all parent projects:', error);
                        }
                    }
                    
                    // Close modal
                    $('#customerInfoModal').modal('hide');
                    this.selectedCustomer = null;
                } else {
                    showParentProjectError(response.data.message_code || '顧客情報の更新に失敗しました。', response && response.data);
                }
            } catch (error) {
                console.error('Error updating customer:', error);
                showParentProjectError('顧客情報の更新に失敗しました。', error);
            } finally {
                this.updatingCustomer = false;
            }
        },

        searchAddressCustomer() {
            const postalCode = this.selectedCustomer.zip;
            if (postalCode.length >= 7) {
                const apiUrl = `https://zipcloud.ibsnet.co.jp/api/search?zipcode=${postalCode}`;
                axios.get(apiUrl)
                    .then(response => {
                        if (response.data.results && response.data.results.length > 0) {
                            const result = response.data.results[0];
                            this.selectedCustomer.address1 = result.address1;
                            this.selectedCustomer.address2 = result.address2;
                        } else {
                            showMessage('郵便番号が見つかりません。', true);
                        }
                    })
                    .catch(error => {
                        console.error('Error searching address:', error);
                        showMessage('住所の検索に失敗しました。', true);
                    });
            } else {
                showMessage('郵便番号が正しくありません。', true);  
            }
        },

        getManagerName(managerString) {
            if (!managerString) return '';
            const parts = managerString.split(':');
            return parts[1] || parts[0] || '';
        },
        getManagerImage(managerString) {
            if (!managerString) return '';
            const parts = managerString.split(':');
            return parts[2] || '';
        },
        getManagerInitials(managerString) {
            if (!managerString) return '?';
            const parts = managerString.split(':');
            const userid = parts[0] || '';
            const name = parts[1] || parts[0] || '';
            return this.getInitials(name, userid);
        },
        getRemainingManagers(managerIdString) {
            if (!managerIdString) return '';
            const managers = managerIdString.split('|').filter(m => m.trim() !== '');
            if (managers.length <= 1) return '';
            const remaining = managers.slice(1).map(manager => {
                const parts = manager.split(':');
                return parts[1] || parts[0] || '';
            }).filter(name => name).join(', ');
            return remaining;
        },
        getInitials(name, userid) {
            if (!name && !userid) return '?';
            // Use the same logic as getAvatarName from main.js
            if (typeof getAvatarName === 'function') {
                return getAvatarName(name || '', { userid: userid || '' });
            }
            // Fallback if getAvatarName is not available
            try {
                // Check if name contains Japanese characters
                const hasJapanese = /[\u3040-\u309f\u30a0-\u30ff\u4e00-\u9faf]/.test(name);
                if (hasJapanese) {
                    // For Japanese names, take first 2 characters
                    return name.substring(0, 2);
                } else {
                    // For English names, take the last word
                    const words = name.trim().split(' ');
                    const lastWord = words[words.length - 1];
                    return lastWord;
                }
            } catch (error) {
                return name.charAt(0).toUpperCase();
            }
        },

        // Notes (メモ) methods
        async loadNotes() {
            try {
                const response = await axios.get(`/api/index.php?model=parentproject&method=getNotes&parent_project_id=${PARENT_PROJECT_ID}`);
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
        openNoteModal(note = null) {
            this.showNoteModal = true;
            this.isNoteEditMode = false;
            if (note) {
                this.editingNote = {
                    id: note.id,
                    title: note.title,
                    content: note.content,
                    is_important: note.is_important == 1,
                    user_id: note.user_id
                };
            } else {
                this.editingNote = {
                    id: null,
                    title: '',
                    content: '',
                    is_important: false,
                    user_id: null
                };
            }
        },
        closeNoteModal() {
            this.showNoteModal = false;
            this.isNoteEditMode = false;
            this.editingNote = {
                id: null,
                title: '',
                content: '',
                is_important: false,
                user_id: null
            };
        },
        async saveNote() {
            const rawContent = (this.editingNote.content || '').trim();
            if (!rawContent) {
                if (typeof showMessage === 'function') {
                    showMessage('内容を入力してください', true);
                } else {
                    alert('内容を入力してください');
                }
                return;
            }
            let title = (this.editingNote.title || '').trim();
            if (!title) {
                title = rawContent.split(/\r?\n/)[0].slice(0, 50) || 'メモ';
            }
            try {
                const formData = new FormData();
                formData.append('parent_project_id', PARENT_PROJECT_ID);
                formData.append('title', title);
                formData.append('content', rawContent);
                formData.append('is_important', this.editingNote.is_important ? 1 : 0);
                let response;
                if (this.editingNote.id) {
                    formData.append('id', this.editingNote.id);
                    response = await axios.post('/api/index.php?model=parentproject&method=updateNote', formData);
                } else {
                    response = await axios.post('/api/index.php?model=parentproject&method=addNote', formData);
                }
                if (response.data && response.data.status === 'success') {
                    if (typeof showMessage === 'function') {
                        showMessage('メモが保存されました', false);
                    } else {
                        alert('メモが保存されました');
                    }
                    this.closeNoteModal();
                    await this.loadNotes();
                } else {
                    showParentProjectError(response.data?.error || 'メモの保存に失敗しました', response && response.data);
                }
            } catch (error) {
                console.error('Error saving note:', error);
                showParentProjectError('メモの保存に失敗しました', error);
            }
        },
        async deleteNote(noteId) {
            if (!confirm('このメモを削除しますか？')) return;
            try {
                const formData = new FormData();
                formData.append('id', noteId);
                const response = await axios.post('/api/index.php?model=parentproject&method=deleteNote', formData);
                if (response.data && response.data.status === 'success') {
                    if (typeof showMessage === 'function') {
                        showMessage('メモが削除されました', false);
                    } else {
                        alert('メモが削除されました');
                    }
                    await this.loadNotes();
                } else {
                    if (typeof showMessage === 'function') {
                        showMessage(response.data?.error || 'メモの削除に失敗しました', true);
                    } else {
                        alert(response.data?.error || 'メモの削除に失敗しました');
                    }
                }
            } catch (error) {
                console.error('Error deleting note:', error);
                if (typeof showMessage === 'function') {
                    showMessage('メモの削除に失敗しました', true);
                } else {
                    alert('メモの削除に失敗しました');
                }
            }
        },
        canDeleteNote(note) {
            return this.isAdmin || (note.user_id && typeof USER_ID !== 'undefined' && String(note.user_id) === String(USER_ID));
        },
        canEditNote(note) {
            return this.isAdmin || (note.user_id && typeof USER_ID !== 'undefined' && String(note.user_id) === String(USER_ID));
        },

        // Activity logs methods
        async showLogs() {
            try {
                this.loadingLogs = true;
                const response = await axios.get(`/api/index.php?model=parentproject&method=getLogs&parent_project_id=${PARENT_PROJECT_ID}`);
                if (response.data && Array.isArray(response.data)) {
                    this.logs = response.data;
                } else {
                    this.logs = [];
                }
                $('#logsModal').modal('show');
            } catch (error) {
                console.error('Error loading logs:', error);
                showMessage('ログの読み込みに失敗しました。', true);
                this.logs = [];
            } finally {
                this.loadingLogs = false;
            }
        },

        async showChildProjectLogs(project) {
            try {
                this.selectedChildProject = project;
                this.loadingChildProjectLogs = true;
                this.childProjectLogs = [];
                
                const response = await axios.get(`/api/index.php?model=project&method=getLogs&project_id=${project.id}`);
                if (response.data && Array.isArray(response.data)) {
                    this.childProjectLogs = response.data;
                } else {
                    this.childProjectLogs = [];
                }
                
                // Show the modal using Bootstrap 5 method
                const modal = new bootstrap.Modal(document.getElementById('childProjectLogsModal'));
                modal.show();
            } catch (error) {
                console.error('Error loading child project logs:', error);
                Swal.fire({
                    title: 'エラー',
                    text: '案件ログの読み込みに失敗しました。',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                this.childProjectLogs = [];
            } finally {
                this.loadingChildProjectLogs = false;
            }
        },

        historyIcon(action) {
            const iconMap = {
                'created': 'fa fa-plus text-success',
                'updated': 'fa fa-edit text-primary',
                'status_changed': 'fa fa-exchange-alt text-warning',
                'deleted': 'fa fa-trash text-danger'
            };
            return iconMap[action] || 'fa fa-info-circle text-muted';
        },

        getLogBadgeClass(log, field) {
            const value = log[field];
            if (!value) return 'badge bg-secondary';

            if (log.action === 'status_changed' || log.action === 'confirmed' || this.isProjectStatusKey(value)) {
                const projectStatus = this.projectStatuses.find(s => s.value === value);
                if (projectStatus) {
                    return `badge bg-${projectStatus.color}`;
                }

                const projectStatusByLabel = this.projectStatuses.find(s => s.label === value);
                if (projectStatusByLabel) {
                    return `badge bg-${projectStatusByLabel.color}`;
                }

                if (value === 'kadai') {
                    return 'badge bg-warning';
                }
                if (value === 'project') {
                    return 'badge bg-success';
                }
            }
            
            // Default colors for value1 and value2
            if (field === 'value1') {
                return 'badge bg-secondary';
            } else if (field === 'value2') {
                return 'badge bg-primary';
            }
            return 'badge bg-secondary';
        },

        getLogBadgeLabel(log, field) {
            const value = log[field];
            if (!value) return '';

            if (log.action === 'status_changed' || this.isProjectStatusKey(value)) {
                return this.getProjectStatusLabel(value);
            }

            if (field === 'value1' || field === 'value2') {
                if (value === 'kadai') {
                    return '承認待ち';
                }
                if (value === 'project') {
                    return '案件';
                }

                const statusMap = {
                    'draft': '下書き',
                    'under_contract': '契約中',
                    'in_progress': '進行中',
                    'completed': '完了',
                    'cancelled': 'キャンセル',
                    'deleted': '削除済み'
                };
                return statusMap[value] || value;
            }

            return value;
        }
    },
    async mounted() {
        try {
            await this.loadPermission();
            await this.loadParentProject();
            await this.loadChildProjects();
            await this.loadQuotations();
            await this.loadNotes();
            
            // Initialize price list modal
            this.priceListModal = new bootstrap.Modal(document.getElementById('priceListModal'));
            
            // Initialize sortable for drag & drop functionality
            this.initializeSortable();
            
            // Add event listeners for modal close events
            const createQuotationModal = document.getElementById('createQuotationModal');
            if (createQuotationModal) {
                createQuotationModal.addEventListener('hidden.bs.modal', () => {
                    // Backup form data when modal is closed
                    this.backupQuotationForm();
                    // Destroy Flatpickr instances
                    this.destroyQuotationDatePickers();
                });
            }

            // Add event listeners for edit quotation modal
            const editQuotationModal = document.getElementById('editQuotationModal');
            if (editQuotationModal) {
                editQuotationModal.addEventListener('hidden.bs.modal', () => {
                    // Reset form when modal is closed
                    this.resetEditQuotationForm();
                    // Ensure backdrop cleanup
                    setTimeout(() => {
                        this.cleanupModalBackdrop();
                    }, 100);
                });

                // Also handle hide.bs.modal event for additional cleanup
                editQuotationModal.addEventListener('hide.bs.modal', () => {
                    // Pre-cleanup before modal starts hiding
                    setTimeout(() => {
                        this.cleanupModalBackdrop();
                    }, 200);
                });
            }

            const businessDocumentModal = document.getElementById('businessDocumentModal');
            if (businessDocumentModal) {
                businessDocumentModal.addEventListener('hidden.bs.modal', () => {
                    this.closeBusinessDocumentModal();
                });
            }

            // Add event listener for customer info modal
            const customerInfoModal = document.getElementById('customerInfoModal');
            if (customerInfoModal) {
                customerInfoModal.addEventListener('hidden.bs.modal', () => {
                    // Cleanup Select2 when modal is closed
                    const selectElement = $(this.$refs.customerGuisDepartmentSelect);
                    if (selectElement.length && selectElement.hasClass('select2-hidden-accessible')) {
                        selectElement.select2('destroy');
                    }
                    // Reset customer data
                    this.selectedCustomer = null;
                    this.customerErrors = { company_name: '', name: '', branch: '', guis_department: '' };
                });
            }

            // Add event listener for new customer modal (自社担当部署名 Select2 cleanup)
            const newCustomerModal = document.getElementById('newCustomerModal');
            if (newCustomerModal) {
                newCustomerModal.addEventListener('hidden.bs.modal', () => {
                    const el = this.$refs.newCustomerGuisDepartmentSelect;
                    if (el) {
                        const $el = $(el);
                        if ($el.data('select2')) {
                            $el.select2('destroy');
                        }
                    }
                });
            }

            // Add event listener for edit child project modal
            const editChildProjectModal = document.getElementById('editChildProjectModal');
            if (editChildProjectModal) {
                editChildProjectModal.addEventListener('hidden.bs.modal', () => {
                    // Cleanup Quill editor when modal is closed
                    this.destroyEditChildProjectQuill();
                    // Cleanup Tagify
                    this.destroyEditChildProjectTagify();
                });
            }

            // Add event listener for create child project modal
            const createChildProjectModal = document.getElementById('createChildProjectModal');
            if (createChildProjectModal) {
                createChildProjectModal.addEventListener('hidden.bs.modal', () => {
                    // Cleanup Quill editor when modal is closed
                    this.destroyCreateChildProjectQuill();
                });
            }

            // Keyboard navigation for price list modal
            const priceListModalEl = document.getElementById('priceListModal');
            if (priceListModalEl) {
                priceListModalEl.addEventListener('shown.bs.modal', () => {
                    this.highlightedIndex = 0;
                    document.addEventListener('keydown', this.handlePriceListKeydown);
                });
                priceListModalEl.addEventListener('hidden.bs.modal', () => {
                    document.removeEventListener('keydown', this.handlePriceListKeydown);
                    // Reset context flag when price list modal is closed
                    this.isPriceListOpenFromEdit = false;
                });
            }

            this._childProjectContextMenuDocClickBound = () => {
                this.closeChildProjectContextMenu();
            };
            document.addEventListener('click', this._childProjectContextMenuDocClickBound);

            this._onWorkloadChartThemeChange = () => {
                if (!this.loadingWorkloadStats && this.activeWorkloadDept) {
                    this.renderActiveWorkloadChart();
                }
            };
            this._workloadChartThemeObserver = new MutationObserver(this._onWorkloadChartThemeChange);
            this._workloadChartThemeObserver.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['data-bs-theme']
            });
            this._workloadChartSystemThemeMedia = window.matchMedia('(prefers-color-scheme: dark)');
            if (this._workloadChartSystemThemeMedia.addEventListener) {
                this._workloadChartSystemThemeMedia.addEventListener('change', this._onWorkloadChartThemeChange);
            } else if (this._workloadChartSystemThemeMedia.addListener) {
                this._workloadChartSystemThemeMedia.addListener(this._onWorkloadChartThemeChange);
            }
        } catch (error) {
            console.error('Error in mounted:', error);
        } finally {
            this.loading = false;
        }
    },
    
    beforeUnmount() {
        if (this._childProjectContextMenuDocClickBound) {
            document.removeEventListener('click', this._childProjectContextMenuDocClickBound);
            this._childProjectContextMenuDocClickBound = null;
        }
        // Clean up sortable instances
        this.destroySortable();
        this.destroyAllWorkloadCharts();
        if (this._workloadChartThemeObserver) {
            this._workloadChartThemeObserver.disconnect();
            this._workloadChartThemeObserver = null;
        }
        if (this._workloadChartSystemThemeMedia && this._onWorkloadChartThemeChange) {
            if (this._workloadChartSystemThemeMedia.removeEventListener) {
                this._workloadChartSystemThemeMedia.removeEventListener('change', this._onWorkloadChartThemeChange);
            } else if (this._workloadChartSystemThemeMedia.removeListener) {
                this._workloadChartSystemThemeMedia.removeListener(this._onWorkloadChartThemeChange);
            }
        }
        
        // Clean up Quill editor instances
        this.destroyEditChildProjectQuill();
        this.destroyCreateChildProjectQuill();
    }
}).mount('#app'); 