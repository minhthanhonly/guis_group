/**
 * Vue 3 mixin for business document (決済情報) modal.
 * Extracted from parent-project-detail.js patterns.
 */
(function() {
    'use strict';

    var SERVER_TASK_TIMEZONE = 'Asia/Tokyo';
    var VIETNAM_TASK_TIMEZONE = 'Asia/Ho_Chi_Minh';
    var PROJECT_DATETIME_MOMENT_FORMAT = 'YYYY/M/D HH:mm';
    var PROJECT_DATETIME_JA_DISPLAY_FORMAT = 'M月D日 HH:mm';
    var PROJECT_DATETIME_SERVER_FORMAT = 'YYYY-MM-DD HH:mm:ss';
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

    var BUSINESS_ESTIMATE_STATUSES = [
        { value: '未発行', label: '未発行', color: 'secondary' },
        { value: '見積作成中', label: '見積作成中', color: 'primary' },
        { value: '発行済', label: '発行済', color: 'success' },
        { value: '発行済み', label: '発行済', color: 'success' },
        { value: '無償', label: '無償', color: 'info' }
    ];

    var BUSINESS_INVOICE_STATUSES = [
        { value: '未発行', label: '未発行', color: 'secondary' },
        { value: '請求準備', label: '請求準備', color: 'warning' },
        { value: '発行済', label: '発行済', color: 'success' },
        { value: '発行済み', label: '発行済', color: 'success' },
        { value: '無償', label: '無償', color: 'info' }
    ];

    var BUSINESS_PAYMENT_STATUSES = [
        { value: '未入金', label: '未入金', color: 'secondary' },
        { value: '入金済', label: '入金済', color: 'success' },
        { value: '入金拒否', label: '入金拒否', color: 'danger' }
    ];

    var BUSINESS_DOCUMENT_LOG_ACTIONS = new Set([
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
        'payment_note_updated'
    ]);

    var BUSINESS_DOCUMENT_FIELDS = [
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
        'payment_note'
    ];

    var BUSINESS_DOCUMENT_DATE_FIELDS = ['estimate_date', 'invoice_date'];

    var BD_MODAL_PICKER_IDS = {
        estimate_date: 'bd_modal_estimate_date_picker',
        invoice_date: 'bd_modal_invoice_date_picker'
    };

    function isVietnameseLocale() {
        if (typeof getAppLanguage === 'function') {
            return String(getAppLanguage() || '').toLowerCase().startsWith('vi');
        }
        return typeof i18next !== 'undefined'
            && !!i18next.language
            && String(i18next.language || '').toLowerCase().startsWith('vi');
    }

    function getProjectDisplayTimezone() {
        return isVietnameseLocale() ? VIETNAM_TASK_TIMEZONE : SERVER_TASK_TIMEZONE;
    }

    function parseProjectDateMomentServer(value) {
        if (value === undefined || value === null) return null;
        var s = String(value).trim();
        if (!s || s === '-') return null;
        var normalized = s.replace(/\//g, '-');
        if (typeof moment !== 'undefined') {
            var formats = ['YYYY-MM-DD HH:mm:ss', 'YYYY-MM-DD HH:mm', 'YYYY-M-D HH:mm', 'YYYY-MM-DD', 'YYYY-M-D'];
            var m = typeof moment.tz === 'function'
                ? moment.tz(normalized, formats, SERVER_TASK_TIMEZONE)
                : moment(normalized, formats, true);
            if (m.isValid()) return m;
        }
        var d = new Date(normalized);
        if (isNaN(d.getTime())) return null;
        return typeof moment !== 'undefined' ? moment(d) : null;
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
        var parsed = parseProjectDateMomentServer(date);
        if (!parsed || !parsed.isValid()) return '';
        var localized = moment.tz
            ? parsed.clone().tz(getProjectDisplayTimezone())
            : parsed;
        return localized.format(PROJECT_DATETIME_FLATPICKR_MOMENT_FORMAT);
    }

    function fromProjectDateTimeInputValue(value) {
        var raw = String(value || '').trim();
        if (!raw) return '';
        if (isProjectServerDateTimeFormat(raw)) {
            var parsedServer = parseProjectDateMomentServer(raw);
            if (!parsedServer || !parsedServer.isValid()) return raw;
            return parsedServer.clone().tz(SERVER_TASK_TIMEZONE).format(PROJECT_DATETIME_SERVER_FORMAT);
        }
        var parsed = parseProjectDateTimeInDisplayTz(raw);
        if (!parsed) return raw;
        if (moment.tz) {
            return parsed.clone().tz(SERVER_TASK_TIMEZONE).format(PROJECT_DATETIME_SERVER_FORMAT);
        }
        return parsed.format(PROJECT_DATETIME_SERVER_FORMAT);
    }

    function formatProjectDateTimeForDisplay(value) {
        var parsed = parseProjectDateMomentServer(value);
        if (!parsed) return '-';
        var localized = moment.tz
            ? parsed.clone().tz(getProjectDisplayTimezone())
            : parsed;
        return localized.format(
            isVietnameseLocale()
                ? PROJECT_DATETIME_MOMENT_FORMAT
                : PROJECT_DATETIME_JA_DISPLAY_FORMAT
        );
    }

    function getProjectDateTimePlaceholder() {
        return isVietnameseLocale() ? 'YYYY/M/D HH:mm' : 'YYYY年M月D日 HH:mm';
    }

    function isProjectServerDateTimeFormat(value) {
        var s = String(value || '').trim();
        return /^\d{4}-\d{1,2}-\d{1,2}(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?$/.test(s);
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

    function makeBdModalTimeInputsEditable(selectedDates, dateStr, instance) {
        var cal = instance && instance.calendarContainer;
        if (!cal) return;
        cal.querySelectorAll('.flatpickr-time input, .flatpickr-time .numInputWrapper input').forEach(function(input) {
            input.removeAttribute('readonly');
            input.readOnly = false;
        });
    }

    function getBdModalFlatpickrOptions(extra) {
        var options = {
            enableTime: true,
            time_24hr: true,
            dateFormat: PROJECT_DATETIME_FLATPICKR_FORMAT,
            allowInput: true,
            locale: getProjectFlatpickrLocale(),
            onOpen: makeBdModalTimeInputsEditable
        };
        if (!isVietnameseLocale()) {
            options.altInput = true;
            options.altFormat = PROJECT_DATETIME_FLATPICKR_JA_ALT_FORMAT;
            options.altInputClass = 'form-control';
        }
        if (extra) {
            Object.assign(options, extra);
        }
        return options;
    }

    function initBdModalFlatpickr(el, extra, serverValue, opts) {
        if (!el || typeof flatpickr === 'undefined') return null;
        if (el._flatpickr) el._flatpickr.destroy();
        var options = opts || {};
        var inputVal = options.alreadyDisplay
            ? String(serverValue || '').trim()
            : toProjectDateTimeInputValue(serverValue);
        if (inputVal) el.value = inputVal;
        var fp = flatpickr(el, getBdModalFlatpickrOptions(extra || {}));
        if (inputVal) {
            fp.setDate(inputVal, false, PROJECT_DATETIME_FLATPICKR_FORMAT);
        }
        return fp;
    }

    function normalizeProjectVersion(version) {
        var n = Number(version);
        return Number.isFinite(n) && n > 0 ? n : 1;
    }

    function applyProjectVersionFromResponse(project, responseData) {
        if (project && responseData && responseData.version != null) {
            project.version = normalizeProjectVersion(responseData.version);
        }
    }

    function appendPaymentVersionToFormData(formData, projectOrVersion) {
        if (!formData) return;
        var paymentVersion = typeof projectOrVersion === 'object'
            ? projectOrVersion.payment_version
            : projectOrVersion;
        formData.append('payment_version', normalizeProjectVersion(paymentVersion));
    }

    function applyPaymentVersionFromResponse(project, responseData) {
        if (project && responseData && responseData.payment_version != null) {
            project.payment_version = normalizeProjectVersion(responseData.payment_version);
        }
    }

    function resolveHandleProjectVersionConflict() {
        if (typeof window.handleProjectVersionConflict === 'function') {
            return window.handleProjectVersionConflict;
        }
        return function(responseData, onReload) {
            if (!responseData || (responseData.error !== 'version_conflict' && responseData.error !== 'version_required')) {
                return false;
            }
            var msg = responseData.message || '他のユーザーが先に更新しました。ページを再読み込みしてください。';
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
                }).then(function() {
                    window.location.reload();
                });
            } else if (typeof showMessage === 'function') {
                showMessage(msg, true);
                window.location.reload();
            } else if (typeof alert === 'function') {
                alert(msg);
                window.location.reload();
            }
            return true;
        };
    }

    window.BusinessDocumentModalMixin = {
        data: function() {
            return {
                businessDocumentProject: null,
                businessDocumentProjectId: null,
                businessDocumentLogs: [],
                showBusinessDocumentLogModal: false,
                businessDocumentSaveStatus: null,
                businessDocumentSaveHideTimer: null,
                businessDocumentDirty: false,
                businessDocumentError: '',
                businessDocumentUpdateTimer: null,
                isUpdatingBusinessDocument: false,
                _bdSuppressAutoSave: false,
                _bdServerDates: null,
                businessEstimateStatuses: BUSINESS_ESTIMATE_STATUSES.filter(function(s) {
                    return s.value !== '発行済み';
                }),
                businessInvoiceStatuses: BUSINESS_INVOICE_STATUSES.filter(function(s) {
                    return s.value !== '発行済み';
                })
            };
        },
        computed: {
            canEditBusinessDocuments: function() {
                if (typeof USER_ROLE !== 'undefined' && USER_ROLE === 'administrator') return true;
                if (this.userPermissions && this.userPermissions.project_director_edit == 1) return true;
                return false;
            },
            sortedBusinessDocumentLogs: function() {
                if (!this.businessDocumentLogs) return [];
                var self = this;
                return this.businessDocumentLogs
                    .filter(function(log) { return self.isBusinessDocumentLog(log); })
                    .slice()
                    .sort(function(a, b) { return b.time > a.time ? 1 : -1; });
            }
        },
        methods: {
            openBusinessDocumentModal: function(project) {
                var self = this;
                if (!this.canEditBusinessDocuments || !project || !project.id) return;
                axios.get('/api/index.php?model=project&method=getById&id=' + project.id)
                    .then(function(response) {
                        self.businessDocumentProject = response.data;
                        if (self.businessDocumentProject) {
                            self.businessDocumentProject.version = normalizeProjectVersion(self.businessDocumentProject.version);
                            self.businessDocumentProject.payment_version = normalizeProjectVersion(self.businessDocumentProject.payment_version);
                        }
                        self.businessDocumentProjectId = project.id;
                        self.normalizeBdFields();
                        self.businessDocumentSaveStatus = null;
                        self.businessDocumentError = '';
                        self.businessDocumentDirty = false;
                        var modalEl = document.getElementById('businessDocumentModal');
                        var modal = bootstrap.Modal.getInstance(modalEl);
                        if (!modal) {
                            modal = new bootstrap.Modal(modalEl);
                        }
                        modal.show();
                        self.$nextTick(function() {
                            setTimeout(function() { self.initBdDatePickers(); }, 150);
                        });
                    })
                    .catch(function(error) {
                        console.error('Error loading business document:', error);
                        if (typeof showMessage === 'function') {
                            showMessage('決済情報の読み込みに失敗しました。', true);
                        }
                    });
            },
            closeBusinessDocumentModal: function() {
                clearTimeout(this.businessDocumentUpdateTimer);
                this.businessDocumentUpdateTimer = null;
                this.destroyBdDatePickers();
                this.businessDocumentProject = null;
                this.businessDocumentProjectId = null;
                this._bdServerDates = null;
                this.businessDocumentError = '';
            },
            destroyBdDatePickers: function() {
                Object.values(BD_MODAL_PICKER_IDS).forEach(function(elId) {
                    var el = document.getElementById(elId);
                    if (el && el._flatpickr) {
                        el._flatpickr.destroy();
                    }
                });
            },
            normalizeBdFields: function() {
                if (!this.businessDocumentProject) return;
                var p = this.businessDocumentProject;
                p.estimate_status = this.normalizeBusinessDocumentStatus(p.estimate_status, '未発行');
                p.invoice_status = this.normalizeBusinessDocumentStatus(p.invoice_status, '未発行');
                p.estimate_number = p.estimate_number || '';
                p.invoice_number = p.invoice_number || '';
                p.payment_note = p.payment_note || '';
                p.invoice_amount = p.invoice_amount != null ? Number(p.invoice_amount) : 0;
                p.amount = p.amount != null ? Number(p.amount) : 0;
                this.normalizeBdDateFields();
            },
            normalizeBdDateFields: function() {
                var self = this;
                if (!this.businessDocumentProject) return;
                BUSINESS_DOCUMENT_DATE_FIELDS.forEach(function(key) {
                    var raw = self.businessDocumentProject[key];
                    if (!raw || !String(raw).trim()) {
                        self.businessDocumentProject[key] = '';
                    }
                });
                this.syncBdServerDatesFromProject();
            },
            syncBdServerDatesFromProject: function() {
                var self = this;
                if (!this.businessDocumentProject) return;
                var server = {};
                BUSINESS_DOCUMENT_DATE_FIELDS.forEach(function(key) {
                    var raw = String(self.businessDocumentProject[key] || '').trim();
                    if (!raw) {
                        server[key] = '';
                        return;
                    }
                    server[key] = fromProjectDateTimeInputValue(raw) || raw;
                });
                this._bdServerDates = server;
            },
            getBdServerDate: function(key) {
                if (this._bdServerDates && this._bdServerDates[key] != null && String(this._bdServerDates[key]).trim()) {
                    return this._bdServerDates[key];
                }
                var raw = String((this.businessDocumentProject && this.businessDocumentProject[key]) || '').trim();
                if (!raw) return '';
                if (isProjectServerDateTimeFormat(raw)) return raw;
                return fromProjectDateTimeInputValue(raw) || raw;
            },
            setBdServerDate: function(key, displayOrServerValue) {
                if (!this._bdServerDates) {
                    this._bdServerDates = {};
                }
                var raw = String(displayOrServerValue || '').trim();
                if (!raw) {
                    this._bdServerDates[key] = '';
                    return;
                }
                this._bdServerDates[key] = fromProjectDateTimeInputValue(raw) || raw;
            },
            getBdPickerDisplayValue: function(el) {
                if (!el) return '';
                var fp = el._flatpickr;
                if (fp) {
                    var visibleInput = fp.altInput || fp._input;
                    return String((visibleInput && visibleInput.value) || '').trim();
                }
                return String(el.value || '').trim();
            },
            getBdDateForApi: function(key) {
                if (!this.businessDocumentProject) return '';
                var elId = BD_MODAL_PICKER_IDS[key];
                var el = document.getElementById(elId);
                if (el) {
                    var displayVal = this.getBdPickerDisplayValue(el);
                    if (!displayVal) {
                        return '';
                    }
                    var fp = el._flatpickr;
                    if (fp && fp.selectedDates && fp.selectedDates.length > 0) {
                        return fromProjectDateTimeInputValue(fp.formatDate(fp.selectedDates[0], PROJECT_DATETIME_FLATPICKR_FORMAT));
                    }
                    return fromProjectDateTimeInputValue(displayVal);
                }
                var fallback = String(this.businessDocumentProject[key] || '').trim();
                return fallback ? fromProjectDateTimeInputValue(fallback) : '';
            },
            hasBdDate: function(key) {
                return !!String(this.getBdServerDate(key) || (this.businessDocumentProject && this.businessDocumentProject[key]) || '').trim();
            },
            hasBdAmount: function(amount) {
                return amount != null && amount !== '' && Number(amount) > 0;
            },
            hasBdNumber: function(value) {
                return !!String(value || '').trim();
            },
            normalizeBusinessDocumentStatusValue: function(status) {
                if (status === '発行済み') return '発行済';
                return status;
            },
            normalizeBusinessDocumentStatus: function(status, fallback) {
                if (!status || status === '発行済み') {
                    return status === '発行済み' ? '発行済' : (fallback || '未発行');
                }
                return status;
            },
            isBdDocumentReadyForCompletion: function(status) {
                var normalized = this.normalizeBusinessDocumentStatusValue(status);
                return normalized === '発行済' || normalized === '無償';
            },
            needsPaymentInfoBeforeComplete: function(project) {
                var p = project || this.businessDocumentProject;
                if (!p) return true;
                return !this.isBdDocumentReadyForCompletion(p.estimate_status)
                    || !this.isBdDocumentReadyForCompletion(p.invoice_status);
            },
            getPaymentInfoBeforeCompleteMessage: function(project) {
                var p = project || this.businessDocumentProject;
                var t = function(key) {
                    if (typeof i18next !== 'undefined' && i18next.isInitialized && typeof i18next.t === 'function') {
                        return i18next.t(key) || key;
                    }
                    if (typeof translateText === 'function') return translateText(key);
                    return key;
                };
                var missing = [];
                if (!this.isBdDocumentReadyForCompletion(p && p.estimate_status)) {
                    missing.push(t('見積状況'));
                }
                if (!this.isBdDocumentReadyForCompletion(p && p.invoice_status)) {
                    missing.push(t('請求状況'));
                }
                var base = t('完了にする前に見積・請求の決済情報を設定してください。（発行済または無償）');
                return missing.length ? (base + '\n（' + missing.join(' / ') + '）') : base;
            },
            confirmPaymentInfoBeforeComplete: async function(project) {
                if (!this.needsPaymentInfoBeforeComplete(project)) return true;
                var t = function(key) {
                    if (typeof i18next !== 'undefined' && i18next.isInitialized && typeof i18next.t === 'function') {
                        return i18next.t(key) || key;
                    }
                    if (typeof translateText === 'function') return translateText(key);
                    return key;
                };
                if (typeof Swal === 'undefined') {
                    alert(this.getPaymentInfoBeforeCompleteMessage(project));
                    return false;
                }
                await Swal.fire({
                    icon: 'warning',
                    title: t('決済情報が未設定です'),
                    text: this.getPaymentInfoBeforeCompleteMessage(project),
                    confirmButtonText: t('OK')
                });
                return false;
            },
            isBdEstimateDocumentFieldsComplete: function() {
                if (!this.businessDocumentProject) return false;
                // Do not call syncBdDatesFromPickers() here — this is used in the template
                // during render; mutating reactive state would hang the page (RESULT_CODE_HUNG).
                return this.hasBdDate('estimate_date')
                    && this.hasBdAmount(this.businessDocumentProject.amount)
                    && this.hasBdNumber(this.businessDocumentProject.estimate_number);
            },
            isBdInvoiceDocumentFieldsComplete: function() {
                if (!this.businessDocumentProject) return false;
                return this.hasBdDate('invoice_date')
                    && this.hasBdAmount(this.businessDocumentProject.invoice_amount)
                    && this.hasBdNumber(this.businessDocumentProject.invoice_number);
            },
            getBdEstimateDocumentFieldsValidationError: function() {
                if (!this.businessDocumentProject) return '';
                var missing = [];
                if (!this.hasBdDate('estimate_date')) missing.push('見積日');
                if (!this.hasBdAmount(this.businessDocumentProject.amount)) missing.push('見積金額');
                if (!this.hasBdNumber(this.businessDocumentProject.estimate_number)) missing.push('見積番号');
                if (!missing.length) return '';
                return '発行済にするには以下を入力してください: ' + missing.join('、');
            },
            getBdInvoiceDocumentFieldsValidationError: function() {
                if (!this.businessDocumentProject) return '';
                var missing = [];
                if (!this.hasBdDate('invoice_date')) missing.push('請求日');
                if (!this.hasBdAmount(this.businessDocumentProject.invoice_amount)) missing.push('請求金額');
                if (!this.hasBdNumber(this.businessDocumentProject.invoice_number)) missing.push('請求番号');
                if (!missing.length) return '';
                return '発行済にするには以下を入力してください: ' + missing.join('、');
            },
            getBusinessDocumentTaxAmount: function(amount) {
                var base = Number(amount);
                if (!Number.isFinite(base) || base <= 0) return 0;
                return Math.round(base * 0.1);
            },
            getBusinessDocumentTotalWithTax: function(amount) {
                var base = Number(amount);
                if (!Number.isFinite(base) || base <= 0) return 0;
                return base + this.getBusinessDocumentTaxAmount(base);
            },
            formatBusinessDocumentTaxAmount: function(amount) {
                return this.formatBusinessDocumentCurrency(this.getBusinessDocumentTaxAmount(amount));
            },
            formatBusinessDocumentTotalWithTax: function(amount) {
                return this.formatBusinessDocumentCurrency(this.getBusinessDocumentTotalWithTax(amount));
            },
            formatBusinessDocumentCurrency: function(amount) {
                if (!amount) return '¥0';
                return '¥' + parseInt(amount, 10).toLocaleString();
            },
            hideBdStatusDropdown: function(dropdownId) {
                var dropdownElement = document.querySelector(dropdownId);
                if (dropdownElement && typeof bootstrap !== 'undefined') {
                    var dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                    if (dropdown) dropdown.hide();
                }
            },
            scheduleBdUpdate: function() {
                var self = this;
                if (this._bdSuppressAutoSave) return;
                if (!this.canEditBusinessDocuments || !this.businessDocumentProject || this.isUpdatingBusinessDocument) return;
                this.businessDocumentDirty = true;
                clearTimeout(this.businessDocumentUpdateTimer);
                this.businessDocumentUpdateTimer = setTimeout(function() {
                    self.businessDocumentUpdateTimer = null;
                    self.updateBdProjectStatus();
                }, 800);
            },
            updateBdProjectStatus: function() {
                var self = this;
                if (this.isUpdatingBusinessDocument || !this.businessDocumentProject || !this.businessDocumentProjectId) return;
                clearTimeout(this.businessDocumentUpdateTimer);
                this.businessDocumentUpdateTimer = null;
                clearTimeout(this.businessDocumentSaveHideTimer);
                this.businessDocumentSaveStatus = 'loading';
                this.isUpdatingBusinessDocument = true;

                this.syncBdDatesFromPickers();
                var p = this.businessDocumentProject;
                if (this.normalizeBusinessDocumentStatusValue(p.estimate_status) === '発行済'
                    && !this.isBdEstimateDocumentFieldsComplete()) {
                    this.businessDocumentSaveStatus = null;
                    this.businessDocumentError = this.getBdEstimateDocumentFieldsValidationError();
                    this.isUpdatingBusinessDocument = false;
                    return;
                }
                if (this.normalizeBusinessDocumentStatusValue(p.invoice_status) === '発行済'
                    && !this.isBdInvoiceDocumentFieldsComplete()) {
                    this.businessDocumentSaveStatus = null;
                    this.businessDocumentError = this.getBdInvoiceDocumentFieldsValidationError();
                    this.isUpdatingBusinessDocument = false;
                    return;
                }

                var formData = new FormData();
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
                appendPaymentVersionToFormData(formData, p);

                axios.post('/api/index.php?model=project&method=updateProjectStatus', formData)
                    .then(function(response) {
                        if (response.data && response.data.status === 'success') {
                            applyPaymentVersionFromResponse(self.businessDocumentProject, response.data);
                            self.businessDocumentDirty = false;
                            self.businessDocumentError = '';
                            self._bdSuppressAutoSave = true;
                            BUSINESS_DOCUMENT_DATE_FIELDS.forEach(function(key) {
                                var apiVal = self.getBdDateForApi(key);
                                self.setBdServerDate(key, apiVal || '');
                                var nextVal = apiVal ? toProjectDateTimeInputValue(apiVal) : '';
                                if (String(self.businessDocumentProject[key] || '').trim() !== String(nextVal || '').trim()) {
                                    self.businessDocumentProject[key] = nextVal;
                                }
                                var el = document.getElementById(BD_MODAL_PICKER_IDS[key]);
                                if (el && el._flatpickr) {
                                    if (apiVal) {
                                        el._flatpickr.setDate(toProjectDateTimeInputValue(apiVal), false, PROJECT_DATETIME_FLATPICKR_FORMAT);
                                    } else {
                                        el._flatpickr.clear();
                                    }
                                }
                            });
                            self.syncBusinessDocumentAfterSave();
                            self.$nextTick(function() {
                                setTimeout(function() {
                                    self._bdSuppressAutoSave = false;
                                }, 300);
                            });
                            if (response.data.payment_version != null && self.childProjects) {
                                var idx = self.childProjects.findIndex(function(item) {
                                    return String(item.id) === String(self.businessDocumentProjectId);
                                });
                                if (idx >= 0) {
                                    self.childProjects[idx].payment_version = normalizeProjectVersion(response.data.payment_version);
                                }
                            }
                            self.businessDocumentSaveStatus = 'saved';
                            self.businessDocumentSaveHideTimer = setTimeout(function() {
                                self.businessDocumentSaveStatus = null;
                                self.businessDocumentSaveHideTimer = null;
                            }, 5000);
                        } else {
                            var handleConflict = resolveHandleProjectVersionConflict();
                            if (handleConflict(response.data, function() {
                                self.openBusinessDocumentModal({ id: self.businessDocumentProjectId });
                            })) {
                                return;
                            }
                            self.businessDocumentSaveStatus = null;
                        }
                    })
                    .catch(function(error) {
                        console.error('Error updating business document:', error);
                        self.businessDocumentSaveStatus = null;
                    })
                    .then(function() {
                        self.isUpdatingBusinessDocument = false;
                    });
            },
            syncBusinessDocumentAfterSave: function() {
                if (typeof this.syncBusinessDocumentListRow === 'function') {
                    this.syncBusinessDocumentListRow();
                } else {
                    this.syncChildProjectFromBd();
                }
            },
            syncChildProjectFromBd: function() {
                var self = this;
                if (!this.businessDocumentProject || !this.businessDocumentProjectId || !this.childProjects) return;
                var idx = this.childProjects.findIndex(function(p) {
                    return String(p.id) === String(self.businessDocumentProjectId);
                });
                if (idx < 0) return;
                BUSINESS_DOCUMENT_FIELDS.forEach(function(key) {
                    self.childProjects[idx][key] = self.businessDocumentProject[key];
                });
            },
            syncBdDatesFromPickers: function() {
                var self = this;
                if (!this.businessDocumentProject) return;
                Object.keys(BD_MODAL_PICKER_IDS).forEach(function(key) {
                    var el = document.getElementById(BD_MODAL_PICKER_IDS[key]);
                    if (!el) return;
                    var displayVal = self.getBdPickerDisplayValue(el);
                    if (String(self.businessDocumentProject[key] || '').trim() !== String(displayVal || '').trim()) {
                        self.businessDocumentProject[key] = displayVal;
                        self.setBdServerDate(key, displayVal);
                    }
                });
            },
            initBdDatePickers: function() {
                var self = this;
                if (!this.businessDocumentProject) return;
                Object.keys(BD_MODAL_PICKER_IDS).forEach(function(key) {
                    self.initBdDatePicker(BD_MODAL_PICKER_IDS[key], key);
                });
            },
            initBdDatePicker: function(elId, key) {
                var self = this;
                if (!this.businessDocumentProject) return;
                var el = document.getElementById(elId);
                if (!el) return;
                var serverValue = this.getBdServerDate(key);
                var inputVal = toProjectDateTimeInputValue(serverValue);
                this._bdSuppressAutoSave = true;
                initBdModalFlatpickr(el, {
                    onChange: function(selectedDates, dateStr) {
                        if (self._bdSuppressAutoSave) return;
                        self.businessDocumentProject[key] = dateStr || '';
                        self.setBdServerDate(key, dateStr || '');
                        self.scheduleBdUpdate();
                    },
                    onClose: function() {
                        if (self._bdSuppressAutoSave) return;
                        var displayVal = self.getBdPickerDisplayValue(el);
                        if (!displayVal) {
                            if (el._flatpickr) {
                                el._flatpickr.clear();
                            }
                            self.businessDocumentProject[key] = '';
                            self.setBdServerDate(key, '');
                            self.scheduleBdUpdate();
                        }
                    }
                }, serverValue);
                if (inputVal && this.businessDocumentProject[key] !== inputVal) {
                    this.businessDocumentProject[key] = inputVal;
                }
                if (serverValue) {
                    this.setBdServerDate(key, serverValue);
                }
                var selfInit = this;
                setTimeout(function() {
                    selfInit._bdSuppressAutoSave = false;
                }, 200);
            },
            setBdDateToday: function(field) {
                if (!this.businessDocumentProject) return;
                var serverNow = moment.tz(SERVER_TASK_TIMEZONE).format('YYYY-MM-DD HH:mm:ss');
                var dateStr = toProjectDateTimeInputValue(serverNow);
                this.businessDocumentProject[field] = dateStr || '';
                this.setBdServerDate(field, serverNow);
                var el = document.getElementById(BD_MODAL_PICKER_IDS[field]);
                if (el && el._flatpickr) {
                    el._flatpickr.setDate(dateStr, false, PROJECT_DATETIME_FLATPICKR_FORMAT);
                }
                this.scheduleBdUpdate();
            },
            copyBdEstimateAmountToInvoice: function() {
                if (!this.businessDocumentProject) return;
                this.businessDocumentProject.invoice_amount = this.businessDocumentProject.amount != null
                    ? Number(this.businessDocumentProject.amount) : 0;
                this.scheduleBdUpdate();
            },
            findBdStatusOption: function(list, status) {
                var normalized = this.normalizeBusinessDocumentStatus(status, '');
                return list.find(function(s) {
                    return s.value === normalized || s.value === status;
                });
            },
            getBdEstimateStatusLabel: function(status) {
                var item = this.findBdStatusOption(BUSINESS_ESTIMATE_STATUSES, status);
                return item ? this.translateLabel(item.label) : this.translateLabel('未発行');
            },
            getBdEstimateStatusButtonClass: function(status) {
                var item = this.findBdStatusOption(BUSINESS_ESTIMATE_STATUSES, status);
                return item ? 'btn-' + item.color : 'btn-secondary';
            },
            getBdInvoiceStatusLabel: function(status) {
                var item = this.findBdStatusOption(BUSINESS_INVOICE_STATUSES, status);
                return item ? this.translateLabel(item.label) : this.translateLabel('未発行');
            },
            getBdInvoiceStatusButtonClass: function(status) {
                var item = this.findBdStatusOption(BUSINESS_INVOICE_STATUSES, status);
                return item ? 'btn-' + item.color : 'btn-secondary';
            },
            selectBdEstimateStatus: function(status) {
                if (!this.businessDocumentProject) return;
                this.syncBdDatesFromPickers();
                var normalized = this.normalizeBusinessDocumentStatusValue(status);
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
            selectBdInvoiceStatus: function(status) {
                if (!this.businessDocumentProject) return;
                this.syncBdDatesFromPickers();
                var normalized = this.normalizeBusinessDocumentStatusValue(status);
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
            loadBusinessDocumentLogs: function() {
                var self = this;
                if (!this.businessDocumentProjectId) return Promise.resolve();
                return axios.get('/api/index.php?model=project&method=getLogs&project_id=' + this.businessDocumentProjectId)
                    .then(function(res) {
                        self.businessDocumentLogs = (res.data && Array.isArray(res.data)) ? res.data : [];
                    })
                    .catch(function() {
                        self.businessDocumentLogs = [];
                    });
            },
            isBusinessDocumentLog: function(log) {
                if (!log) return false;
                if (log.action && BUSINESS_DOCUMENT_LOG_ACTIONS.has(log.action)) {
                    return true;
                }
                var note = String(log.note || '');
                return /見積|請求|入金|決済|金額変更|領収書/.test(note);
            },
            openBusinessDocumentLogModal: function() {
                this.loadBusinessDocumentLogs();
                this.showBusinessDocumentLogModal = true;
            },
            closeBusinessDocumentLogModal: function() {
                this.showBusinessDocumentLogModal = false;
            },
            hasBdLogValue: function(value) {
                return value !== null && value !== undefined && String(value).trim() !== '';
            },
            getBusinessDocumentLogValue: function(log, field) {
                var value = log[field];
                if (!this.hasBdLogValue(value)) return '—';
                if (log.action && log.action.endsWith('_date_updated')) {
                    return this.formatShortDateTime(value) || value;
                }
                if (log.action === 'amount_updated' || log.action === 'invoice_amount_updated' || log.action === 'payment_amount_updated') {
                    var num = Number(value);
                    if (!isNaN(num)) {
                        return this.formatCurrency(num);
                    }
                }
                return this.getLogBadgeLabel(log, field) || value;
            },
            getBdLogBadgeClass: function(log, field) {
                var value = log[field];
                if (!this.hasBdLogValue(value)) return 'badge bg-secondary';
                if (log.action === 'estimate_status_updated' || log.action === 'invoice_status_updated') {
                    var cls = log.action === 'estimate_status_updated'
                        ? this.getBusinessEstimateStatusBadgeClass(value)
                        : this.getBusinessInvoiceStatusBadgeClass(value);
                    return 'badge ' + cls;
                }
                if (log.action === 'payment_status_updated') {
                    return 'badge ' + this.getBusinessPaymentStatusBadgeClass(value);
                }
                return field === 'value1' ? 'badge bg-secondary' : 'badge bg-primary';
            },
            bdHistoryIcon: function(action) {
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
            getLogNote: function(log) {
                if (!log || !log.note) return '';
                if (log.action === 'status_changed' || log.note === 'ステータスを変更' || log.note.indexOf('ステータス変更') === 0) {
                    return this.translateLabel('ステータス変更');
                }
                return log.note;
            },
            formatShortDateTime: function(datetime) {
                return formatProjectDateTimeForDisplay(datetime);
            },
            translateLabel: function(label) {
                if (typeof i18next !== 'undefined' && i18next.isInitialized) {
                    return i18next.t(label) || label;
                }
                return label;
            },
            getProjectDateTimePlaceholder: function() {
                return getProjectDateTimePlaceholder();
            },
            getBusinessEstimateStatusBadgeClass: function(status) {
                var item = this.findBdStatusOption(BUSINESS_ESTIMATE_STATUSES, status);
                return item ? 'bg-' + item.color : 'bg-secondary';
            },
            getBusinessInvoiceStatusBadgeClass: function(status) {
                var item = this.findBdStatusOption(BUSINESS_INVOICE_STATUSES, status);
                return item ? 'bg-' + item.color : 'bg-secondary';
            },
            getBusinessPaymentStatusBadgeClass: function(status) {
                var item = BUSINESS_PAYMENT_STATUSES.find(function(s) { return s.value === status; });
                return item ? 'bg-' + item.color : 'bg-secondary';
            },
            getLogBadgeLabel: function(log, field) {
                var value = log[field];
                if (!value) return '';
                if (log.action === 'estimate_status_updated') {
                    return this.getBusinessEstimateStatusLabel(value);
                }
                if (log.action === 'invoice_status_updated') {
                    return this.getBusinessInvoiceStatusLabel(value);
                }
                if (log.action === 'payment_status_updated') {
                    var paymentItem = BUSINESS_PAYMENT_STATUSES.find(function(s) { return s.value === value; });
                    return paymentItem ? this.translateLabel(paymentItem.label) : this.translateLabel('未入金');
                }
                return value;
            },
            getBusinessEstimateStatusLabel: function(status) {
                return this.getBdEstimateStatusLabel(status);
            },
            getBusinessInvoiceStatusLabel: function(status) {
                return this.getBdInvoiceStatusLabel(status);
            },
            formatCurrency: function(amount) {
                if (amount === null || amount === undefined || isNaN(amount)) {
                    return '0';
                }
                var integerAmount = Math.floor(parseFloat(amount));
                return new Intl.NumberFormat('ja-JP').format(integerAmount);
            },
            getInitials: function(name, userid, ruby) {
                if (!name && !userid) return '?';
                if (typeof getAvatarName === 'function') {
                    return getAvatarName(name || '', {
                        userid: userid || '',
                        user_ruby: ruby || ''
                    });
                }
                if (!name) return '?';
                var parts = String(name).trim().split(/\s+/);
                if (parts.length >= 2) {
                    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
                }
                return String(name).charAt(0).toUpperCase();
            },
            getBdLogDisplayName: function(log) {
                if (!log) return '';
                var name = log.username || log.realname || log.user || '';
                if (typeof getUserDisplayName === 'function') {
                    return getUserDisplayName(name, {
                        userid: log.userid || log.user_id || '',
                        user_ruby: log.user_ruby || ''
                    }) || name;
                }
                return name;
            }
        }
    };
})();
