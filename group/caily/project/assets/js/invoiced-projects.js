(function() {
    const STORAGE_KEY_DEPT = 'invoiced_projects_selected_department';
    const PER_PAGE = 50;
    const FILTER_DEBOUNCE_MS = 500;

    function currentYearMonth() {
        const d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    }

    function addMonthsToYearMonth(yearMonth, delta) {
        const parts = String(yearMonth || '').split('-');
        let year = parseInt(parts[0], 10);
        let month = parseInt(parts[1], 10);
        if (!Number.isFinite(year) || !Number.isFinite(month)) return yearMonth;

        month += delta;
        while (month > 12) {
            month -= 12;
            year += 1;
        }
        while (month < 1) {
            month += 12;
            year -= 1;
        }
        return year + '-' + String(month).padStart(2, '0');
    }

    function buildMonthOptions(monthsBefore, monthsAfter) {
        const current = currentYearMonth();
        const months = [];
        const before = Number.isFinite(monthsBefore) ? monthsBefore : 18;
        const after = Number.isFinite(monthsAfter) ? monthsAfter : 12;

        for (let i = -before; i <= after; i++) {
            const month = addMonthsToYearMonth(current, i);
            months.push({
                month: month,
                month_label: buildMonthLabel(month)
            });
        }

        return months.sort(function(a, b) {
            return String(b.month).localeCompare(String(a.month));
        });
    }

    function buildMonthLabel(month) {
        if (!month || !/^\d{4}-\d{2}$/.test(month)) return month || '';
        const parts = month.split('-');
        return parts[0] + '年' + parts[1] + '月';
    }

    const MIN_FISCAL_YEAR = 2026;

    function getCurrentFiscalYear() {
        const today = new Date();
        const year = today.getFullYear();
        const month = today.getMonth() + 1;
        return month >= 7 ? year + 1 : year;
    }

    function getDefaultFiscalYear() {
        return Math.max(getCurrentFiscalYear(), MIN_FISCAL_YEAR);
    }

    function buildFiscalYearLabel(fiscalYear) {
        const y = parseInt(fiscalYear, 10);
        if (!Number.isFinite(y)) return '';
        return 'FY' + y + ' (' + (y - 1) + '年7月〜' + y + '年6月)';
    }

    function buildDefaultFiscalYearOptions() {
        const maxFY = getCurrentFiscalYear() + 2;
        const years = [];
        for (let value = maxFY; value >= MIN_FISCAL_YEAR; value--) {
            years.push({
                value: value,
                label: buildFiscalYearLabel(value)
            });
        }
        return years;
    }

    function emptyFiscalPaymentStats() {
        const fy = getDefaultFiscalYear();
        return {
            fiscal_year: fy,
            fiscal_year_label: buildFiscalYearLabel(fy),
            payment_count: 0,
            payment_amount: 0,
            yearly_target: 0,
            achievement_rate: 0
        };
    }

    function emptyMeta() {
        return {
            page: 1,
            per_page: PER_PAGE,
            total_count: 0,
            total_pages: 0,
            totals: {
                unpaid_count: 0,
                unpaid_invoice_amount: 0,
                rejected_count: 0,
                rejected_invoice_amount: 0,
                payment_count: 0,
                payment_amount: 0
            }
        };
    }

    function emptyFilters() {
        return {
            invoiceMonth: '',
            paymentMonth: '',
            paymentStatus: '未入金',
            company: '',
            branch: '',
            invoiceNumber: '',
            receiptNumber: '',
            tantou: '',
            keyword: ''
        };
    }

    function emptyPaymentReportMeta() {
        return {
            totals: {
                payment_count: 0,
                payment_amount: 0
            }
        };
    }

    const PAYMENT_EDIT_STATUSES = [
        { value: '未入金', label: '未入金', color: 'secondary' },
        { value: '入金済', label: '入金済', color: 'success' },
        { value: '入金拒否', label: '入金拒否', color: 'danger' }
    ];
    const SERVER_TASK_TIMEZONE = 'Asia/Tokyo';
    const PROJECT_DATETIME_FLATPICKR_FORMAT = 'Y/m/d H:i';
    const PROJECT_DATETIME_FLATPICKR_JA_ALT_FORMAT = 'Y年n月j日 H:i';
    const PROJECT_DATETIME_FLATPICKR_MOMENT_FORMAT = 'Y/M/D H:mm';
    const PROJECT_DATETIME_MOMENT_FORMAT = 'YYYY/M/D HH:mm';
    const PROJECT_DATETIME_PARSE_FORMATS = [
        'YYYY-MM-DD HH:mm:ss',
        'YYYY-MM-DD HH:mm',
        'YYYY/M/D HH:mm',
        'YYYY/MM/DD HH:mm',
        'Y/M/D H:mm'
    ];
    const PAYMENT_EDIT_DATE_PICKER_ID = 'invoiced_payment_date_picker';

    function isInvoicedViLocale() {
        return typeof i18next !== 'undefined'
            && i18next.isInitialized
            && String(i18next.language || '').startsWith('vi');
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
        if (typeof showMessage === 'function') {
            showMessage(msg, true);
        }
        if (typeof onReload === 'function') {
            onReload();
        }
        return true;
    }

    function isProjectServerDateTimeFormat(value) {
        const s = String(value || '').trim();
        return /^\d{4}-\d{1,2}-\d{1,2}(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?$/.test(s);
    }

    function parseProjectDateMomentServer(value) {
        const s = String(value || '').trim();
        if (!s || s === '-' || s === '0000-00-00 00:00:00' || s === '0000-00-00') return null;
        if (typeof moment === 'undefined') return null;
        const tz = SERVER_TASK_TIMEZONE;
        if (moment.tz) {
            for (let i = 0; i < PROJECT_DATETIME_PARSE_FORMATS.length; i++) {
                const parsed = moment.tz(s, PROJECT_DATETIME_PARSE_FORMATS[i], tz);
                if (parsed.isValid()) return parsed;
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
        const localized = moment.tz ? parsed.clone().tz(SERVER_TASK_TIMEZONE) : parsed;
        return localized.format(PROJECT_DATETIME_FLATPICKR_MOMENT_FORMAT);
    }

    function fromProjectDateTimeInputValue(value) {
        const raw = String(value || '').trim();
        if (!raw) return '';
        if (typeof moment === 'undefined') return raw;
        let parsed = null;
        if (moment.tz) {
            for (let i = 0; i < PROJECT_DATETIME_PARSE_FORMATS.length; i++) {
                const tryParsed = moment.tz(raw, PROJECT_DATETIME_PARSE_FORMATS[i], SERVER_TASK_TIMEZONE);
                if (tryParsed.isValid()) {
                    parsed = tryParsed;
                    break;
                }
            }
        }
        if (!parsed) {
            parsed = moment(raw, PROJECT_DATETIME_PARSE_FORMATS, true);
        }
        if (!parsed || !parsed.isValid()) return raw;
        if (moment.tz) {
            return parsed.clone().tz(SERVER_TASK_TIMEZONE).format('YYYY-MM-DD HH:mm:ss');
        }
        return parsed.format('YYYY-MM-DD HH:mm:ss');
    }

    function getPaymentDatePlaceholder() {
        return isInvoicedViLocale() ? 'YYYY/M/D HH:mm' : 'YYYY年M月D日 HH:mm';
    }

    function getPaymentFlatpickrLocale() {
        if (typeof window === 'undefined' || !window.flatpickr || !window.flatpickr.l10ns) {
            return 'default';
        }
        if (isInvoicedViLocale()) {
            return window.flatpickr.l10ns.vi || 'default';
        }
        return window.flatpickr.l10ns.ja || 'default';
    }

    function makePaymentTimeInputsEditable(selectedDates, dateStr, instance) {
        const cal = instance && instance.calendarContainer;
        if (!cal) return;
        cal.querySelectorAll('.flatpickr-time input, .flatpickr-time .numInputWrapper input').forEach(function(input) {
            input.removeAttribute('readonly');
            input.readOnly = false;
        });
    }

    function getPaymentFlatpickrOptions(extra) {
        const options = {
            enableTime: true,
            time_24hr: true,
            dateFormat: PROJECT_DATETIME_FLATPICKR_FORMAT,
            allowInput: true,
            locale: getPaymentFlatpickrLocale(),
            onOpen: makePaymentTimeInputsEditable
        };
        if (!isInvoicedViLocale()) {
            options.altInput = true;
            options.altFormat = PROJECT_DATETIME_FLATPICKR_JA_ALT_FORMAT;
            options.altInputClass = 'form-control';
        }
        if (extra) {
            Object.assign(options, extra);
        }
        return options;
    }

    function initPaymentDatePicker(el, serverValue, onChange) {
        if (!el || typeof flatpickr === 'undefined') return null;
        if (el._flatpickr) el._flatpickr.destroy();
        const inputVal = toProjectDateTimeInputValue(serverValue);
        if (inputVal) el.value = inputVal;
        const fp = flatpickr(el, getPaymentFlatpickrOptions({
            onChange: onChange || null
        }));
        if (inputVal) {
            fp.setDate(inputVal, false, PROJECT_DATETIME_FLATPICKR_FORMAT);
        }
        return fp;
    }

    const app = Vue.createApp({
        data() {
            return {
                departments: [],
                selectedDepartment: null,
                loadingDepartments: false,
                activeSubTab: 'invoiced',
                loading: false,
                errorMessage: '',
                projects: [],
                meta: emptyMeta(),
                filters: emptyFilters(),
                appliedFilters: emptyFilters(),
                paymentReportMonth: currentYearMonth(),
                paymentReportGroups: [],
                paymentReportMeta: emptyPaymentReportMeta(),
                selectedFiscalYear: getDefaultFiscalYear(),
                availableFiscalYears: buildDefaultFiscalYearOptions(),
                fiscalPaymentStats: emptyFiscalPaymentStats(),
                availableMonths: buildMonthOptions(18, 12),
                filterTimer: null,
                suppressFilterWatch: false,
                paymentEditProject: null,
                paymentEditProjectId: null,
                paymentEditSaveStatus: null,
                paymentEditSaveHideTimer: null,
                paymentEditUpdateTimer: null,
                paymentEditSaving: false,
                paymentEditError: '',
                paymentEditServerDate: '',
                paymentEditStatuses: PAYMENT_EDIT_STATUSES,
                paymentEditModalInstance: null
            };
        },
        computed: {
            paymentStatusOptions() {
                return [
                    { value: '未入金', label: '未入金' },
                    { value: '入金済', label: '入金済' },
                    { value: '入金拒否', label: '入金拒否' }
                ];
            },
            tantouOptions() {
                return [
                    { value: 'CAILY', label: 'CAILY' },
                    { value: 'GUIS', label: 'GUIS' }
                ];
            },
            filterPlaceholderCompany() {
                return typeof translateText === 'function'
                    ? translateText('会社名で検索')
                    : '会社名で検索';
            },
            filterPlaceholderBranch() {
                return typeof translateText === 'function'
                    ? translateText('支店名で検索')
                    : '支店名で検索';
            },
            filterPlaceholderInvoiceNumber() {
                return typeof translateText === 'function'
                    ? translateText('請求番号で検索')
                    : '請求番号で検索';
            },
            filterPlaceholderReceiptNumber() {
                return typeof translateText === 'function'
                    ? translateText('領収書番号で検索')
                    : '領収書番号で検索';
            },
            filterPlaceholderKeyword() {
                return typeof translateText === 'function'
                    ? translateText('案件名、工事番号、支店名などで検索')
                    : '案件名、工事番号、支店名などで検索';
            },
            resultRangeLabel() {
                const total = Number(this.meta.total_count) || 0;
                if (total <= 0) return '0件';
                const page = Number(this.meta.page) || 1;
                const perPage = Number(this.meta.per_page) || PER_PAGE;
                const start = (page - 1) * perPage + 1;
                const end = Math.min(page * perPage, total);
                return start + '–' + end + ' / ' + total + '件';
            },
            paginationLabel() {
                const page = Number(this.meta.page) || 1;
                const totalPages = Number(this.meta.total_pages) || 0;
                if (totalPages <= 1) {
                    return (Number(this.meta.total_count) || 0) + '件';
                }
                return page + ' / ' + totalPages + 'ページ';
            },
            visiblePages() {
                const current = Number(this.meta.page) || 1;
                const total = Number(this.meta.total_pages) || 0;
                if (total <= 7) {
                    return Array.from({ length: total }, function(_, i) { return i + 1; });
                }

                const pages = [1];
                const start = Math.max(2, current - 1);
                const end = Math.min(total - 1, current + 1);

                if (start > 2) pages.push('...');
                for (let i = start; i <= end; i++) pages.push(i);
                if (end < total - 1) pages.push('...');
                pages.push(total);
                return pages;
            },
            hasActiveFilters() {
                return this.activeFilterBadges.length > 0;
            },
            activeFilterBadges() {
                const f = this.appliedFilters || emptyFilters();
                const badges = [];
                const add = function(key, labelKey, labelFallback, value) {
                    const text = String(value || '').trim();
                    if (!text) return;
                    badges.push({
                        key: key,
                        label: typeof translateText === 'function'
                            ? translateText(labelKey)
                            : labelFallback,
                        value: text
                    });
                };

                if (f.keyword) {
                    add('keyword', 'キーワード', 'キーワード', f.keyword);
                    return badges;
                }

                if (f.invoiceMonth) {
                    add('invoiceMonth', '請求月', '請求月', this.getMonthLabel(f.invoiceMonth));
                }
                if (f.paymentMonth) {
                    add('paymentMonth', '入金月', '入金月', this.getMonthLabel(f.paymentMonth));
                }
                if (f.paymentStatus) {
                    add('paymentStatus', '入金状況', '入金状況', f.paymentStatus);
                }
                if (f.tantou) {
                    add('tantou', '担当', '担当', f.tantou);
                }
                if (f.invoiceNumber) {
                    add('invoiceNumber', '請求番号', '請求番号', f.invoiceNumber);
                }
                if (f.receiptNumber) {
                    add('receiptNumber', '領収書番号', '領収書番号', f.receiptNumber);
                }
                if (f.company) {
                    add('company', '会社名', '会社名', f.company);
                }
                if (f.branch) {
                    add('branch', '支店名', '支店名', f.branch);
                }
                return badges;
            },
            currentYearMonthValue() {
                return currentYearMonth();
            },
            currentFiscalYearValue() {
                return getCurrentFiscalYear();
            },
            minFiscalYearValue() {
                return MIN_FISCAL_YEAR;
            },
            fiscalAchievementRate() {
                const target = Number(this.fiscalPaymentStats.yearly_target) || 0;
                const actual = Number(this.fiscalPaymentStats.payment_amount) || 0;
                if (target <= 0) return 0;
                return (actual / target) * 100;
            },
            paymentEditButtonTitle() {
                return typeof translateText === 'function'
                    ? translateText('入金編集')
                    : '入金編集';
            },
            paymentDatePlaceholder() {
                return getPaymentDatePlaceholder();
            }
        },
        methods: {
            getMonthLabel(month) {
                const found = (this.availableMonths || []).find(function(m) {
                    return m && m.month === month;
                });
                if (found && found.month_label) return found.month_label;
                return buildMonthLabel(month);
            },
            formatCurrency(val) {
                const n = Number(val);
                if (!Number.isFinite(n)) return '¥0';
                return '¥' + Math.round(n).toLocaleString('ja-JP');
            },
            formatCount(val) {
                const n = Number(val);
                return Number.isFinite(n) ? String(Math.round(n)) : '0';
            },
            formatPercent(val) {
                const n = Number(val);
                if (!Number.isFinite(n)) return '0%';
                return Math.round(n).toLocaleString('ja-JP') + '%';
            },
            achievementRateBarClass(rate) {
                const n = Number(rate) || 0;
                if (n >= 100) return 'bg-success';
                if (n >= 70) return 'bg-primary';
                if (n >= 40) return 'bg-warning';
                return 'bg-danger';
            },
            achievementRateTextClass(rate) {
                const n = Number(rate) || 0;
                if (n >= 100) return 'text-success';
                if (n >= 70) return 'text-primary';
                if (n >= 40) return 'text-warning';
                return 'text-danger';
            },
            achievementBarWidth(rate) {
                const n = Math.max(0, Number(rate) || 0);
                return Math.min(100, n) + '%';
            },
            formatDate(val) {
                if (!val) return '—';
                const s = String(val).trim();
                if (/^\d{4}-\d{2}-\d{2}/.test(s)) return s.substring(0, 10);
                return s;
            },
            paymentStatusClass(status) {
                const value = String(status || '').trim();
                if (value === '入金済') return 'bg-paid';
                if (value === '入金拒否') return 'bg-rejected';
                return 'bg-unpaid';
            },
            saveDepartmentToStorage(dept) {
                try {
                    if (dept && dept.id != null) {
                        localStorage.setItem(STORAGE_KEY_DEPT, JSON.stringify({ id: dept.id, name: dept.name }));
                    }
                } catch (e) {}
            },
            loadDepartmentFromStorage() {
                try {
                    const raw = localStorage.getItem(STORAGE_KEY_DEPT);
                    if (!raw) return null;
                    return JSON.parse(raw);
                } catch (e) {
                    return null;
                }
            },
            async departmentHasAccess(departmentId) {
                if (typeof USER_ROLE !== 'undefined' && USER_ROLE === 'administrator') {
                    return true;
                }
                try {
                    const response = await axios.get('/api/index.php', {
                        params: {
                            model: 'department',
                            method: 'get_user_permission_by_department',
                            department_id: departmentId
                        }
                    });
                    const perms = response.data || {};
                    return perms.project_director_stat == 1;
                } catch (e) {
                    return false;
                }
            },
            async filterDepartmentsForAccess(departments) {
                const list = (departments || []).filter(function(d) { return d && d.can_project != 0; });
                if (typeof USER_ROLE !== 'undefined' && USER_ROLE === 'administrator') {
                    return list;
                }
                const checks = await Promise.all(list.map(async function(d) {
                    const allowed = await this.departmentHasAccess(d.id);
                    return allowed ? d : null;
                }.bind(this)));
                return checks.filter(Boolean);
            },
            async loadDepartments() {
                this.loadingDepartments = true;
                try {
                    const response = await axios.get('/api/index.php?model=department&method=listByUser');
                    const all = response.data || [];
                    this.departments = await this.filterDepartmentsForAccess(all);
                    if (!this.departments.length) {
                        this.selectedDepartment = null;
                        return;
                    }
                    const saved = this.loadDepartmentFromStorage();
                    let dept = null;
                    if (saved) {
                        dept = this.departments.find(function(d) {
                            return d && String(d.id) === String(saved.id);
                        });
                    }
                    if (!dept) {
                        dept = this.departments[0];
                    }
                    await this.selectDepartment(dept, false);
                } catch (err) {
                    console.error('loadDepartments:', err);
                    this.departments = [];
                    this.errorMessage = typeof translateText === 'function'
                        ? translateText('部署の読み込みに失敗しました。')
                        : '部署の読み込みに失敗しました。';
                } finally {
                    this.loadingDepartments = false;
                }
            },
            async selectDepartment(department, persist) {
                if (persist !== false) {
                    this.saveDepartmentToStorage(department);
                }
                this.selectedDepartment = department;
                this.errorMessage = '';
                await this.reloadActiveTab();
            },
            switchSubTab(tab) {
                if (this.activeSubTab === tab) return;
                this.activeSubTab = tab;
                this.errorMessage = '';
                if (tab === 'monthly_payment' && !this.paymentReportMonth) {
                    this.paymentReportMonth = currentYearMonth();
                }
                if (tab === 'fiscal_payment' && !this.selectedFiscalYear) {
                    this.selectedFiscalYear = getDefaultFiscalYear();
                }
                this.reloadActiveTab();
            },
            async reloadActiveTab() {
                if (!this.selectedDepartment) return;
                if (this.activeSubTab === 'monthly_payment') {
                    await this.loadPaymentReport();
                } else if (this.activeSubTab === 'fiscal_payment') {
                    await this.loadFiscalYearPaymentStats();
                } else {
                    await this.loadProjects(1);
                }
            },
            ensureFiscalYearInOptions(year) {
                const value = parseInt(year, 10);
                if (!Number.isFinite(value) || value < MIN_FISCAL_YEAR) return;
                const exists = (this.availableFiscalYears || []).some(function(opt) {
                    return opt && opt.value === value;
                });
                if (!exists) {
                    this.availableFiscalYears = this.availableFiscalYears.concat([{
                        value: value,
                        label: buildFiscalYearLabel(value)
                    }]).sort(function(a, b) {
                        return b.value - a.value;
                    });
                }
            },
            setSelectedFiscalYear(year) {
                const value = parseInt(year, 10);
                if (!Number.isFinite(value) || value < MIN_FISCAL_YEAR) return;
                this.ensureFiscalYearInOptions(value);
                if (this.selectedFiscalYear === value) return;
                this.selectedFiscalYear = value;
                this.loadFiscalYearPaymentStats();
            },
            goToFiscalYearPrev() {
                const current = Number(this.selectedFiscalYear) || getDefaultFiscalYear();
                if (current <= MIN_FISCAL_YEAR) return;
                this.setSelectedFiscalYear(current - 1);
            },
            goToFiscalYearNext() {
                this.setSelectedFiscalYear((Number(this.selectedFiscalYear) || getCurrentFiscalYear()) + 1);
            },
            goToFiscalYearCurrent() {
                this.setSelectedFiscalYear(getCurrentFiscalYear());
            },
            onFiscalYearChange() {
                this.ensureFiscalYearInOptions(this.selectedFiscalYear);
                this.loadFiscalYearPaymentStats();
            },
            async loadFiscalYearOptions() {
                try {
                    const response = await axios.get('/api/index.php', {
                        params: {
                            model: 'teamrevenuetarget',
                            method: 'getYears'
                        }
                    });
                    const data = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
                    const years = Array.isArray(data) ? data : [];
                    const currentFY = getCurrentFiscalYear();
                    const yearSet = new Set();

                    years.forEach(function(year) {
                        const y = parseInt(year, 10);
                        if (!Number.isNaN(y) && y >= MIN_FISCAL_YEAR) yearSet.add(y);
                    });
                    for (let i = -5; i <= 2; i++) {
                        const y = currentFY + i;
                        if (y >= MIN_FISCAL_YEAR) yearSet.add(y);
                    }

                    this.availableFiscalYears = Array.from(yearSet)
                        .filter(function(y) { return !Number.isNaN(y) && y >= MIN_FISCAL_YEAR && y <= 2100; })
                        .sort(function(a, b) { return b - a; })
                        .map(function(y) {
                            return { value: y, label: buildFiscalYearLabel(y) };
                        });

                    if (!this.availableFiscalYears.some(function(opt) {
                        return opt.value === this.selectedFiscalYear;
                    }.bind(this))) {
                        this.selectedFiscalYear = getDefaultFiscalYear();
                    }
                } catch (err) {
                    console.error('loadFiscalYearOptions:', err);
                    this.availableFiscalYears = buildDefaultFiscalYearOptions();
                }
            },
            normalizeFilters() {
                return {
                    invoiceMonth: String(this.filters.invoiceMonth || '').trim(),
                    paymentMonth: String(this.filters.paymentMonth || '').trim(),
                    paymentStatus: String(this.filters.paymentStatus || '').trim(),
                    tantou: String(this.filters.tantou || '').trim(),
                    company: String(this.filters.company || '').trim(),
                    branch: String(this.filters.branch || '').trim(),
                    invoiceNumber: String(this.filters.invoiceNumber || '').trim(),
                    receiptNumber: String(this.filters.receiptNumber || '').trim(),
                    keyword: String(this.filters.keyword || '').trim()
                };
            },
            applyFilters() {
                this.appliedFilters = this.normalizeFilters();
                this.loadProjects(1);
            },
            ensureMonthInOptions(month) {
                if (!month || !/^\d{4}-\d{2}$/.test(month)) return;
                const exists = (this.availableMonths || []).some(function(m) {
                    return m && m.month === month;
                });
                if (!exists) {
                    this.availableMonths = this.availableMonths.concat([{
                        month: month,
                        month_label: buildMonthLabel(month)
                    }]).sort(function(a, b) {
                        return String(b.month).localeCompare(String(a.month));
                    });
                }
            },
            setMonthFilter(field) {
                const month = currentYearMonth();
                this.ensureMonthInOptions(month);
                if (this.filters[field] === month) return;
                if (this.filterTimer) {
                    clearTimeout(this.filterTimer);
                    this.filterTimer = null;
                }
                this.suppressFilterWatch = true;
                this.filters[field] = month;
                this.suppressFilterWatch = false;
                this.applyFilters();
            },
            setInvoiceMonthThisMonth() {
                this.setMonthFilter('invoiceMonth');
            },
            setPaymentMonthThisMonth() {
                this.setMonthFilter('paymentMonth');
            },
            setPaymentReportMonth(month) {
                if (!month || !/^\d{4}-\d{2}$/.test(month)) return;
                this.ensureMonthInOptions(month);
                if (this.paymentReportMonth === month) return;
                this.paymentReportMonth = month;
                this.loadPaymentReport();
            },
            goToPaymentReportPrevMonth() {
                this.setPaymentReportMonth(addMonthsToYearMonth(this.paymentReportMonth || currentYearMonth(), -1));
            },
            goToPaymentReportNextMonth() {
                this.setPaymentReportMonth(addMonthsToYearMonth(this.paymentReportMonth || currentYearMonth(), 1));
            },
            goToPaymentReportThisMonth() {
                this.setPaymentReportMonth(currentYearMonth());
            },
            onPaymentReportMonthChange() {
                this.ensureMonthInOptions(this.paymentReportMonth);
                this.loadPaymentReport();
            },
            clearFilters() {
                if (this.filterTimer) {
                    clearTimeout(this.filterTimer);
                    this.filterTimer = null;
                }
                this.suppressFilterWatch = true;
                this.filters = emptyFilters();
                this.applyFilters();
                this.suppressFilterWatch = false;
            },
            scheduleFilterSearch() {
                if (this.suppressFilterWatch) return;
                if (this.filterTimer) {
                    clearTimeout(this.filterTimer);
                }
                this.filterTimer = setTimeout(function() {
                    this.applyFilters();
                }.bind(this), FILTER_DEBOUNCE_MS);
            },
            goToPage(page) {
                const target = Number(page);
                const totalPages = Number(this.meta.total_pages) || 0;
                if (!Number.isFinite(target) || target < 1 || (totalPages > 0 && target > totalPages)) {
                    return;
                }
                if (target === this.meta.page) return;
                this.loadProjects(target);
            },
            async loadProjects(page, options) {
                if (!this.selectedDepartment) return;

                const targetPage = Number(page) || 1;
                const silent = !!(options && options.silent);
                if (!silent) {
                    this.loading = true;
                }
                this.errorMessage = '';

                try {
                    const f = this.appliedFilters;
                    const response = await axios.get('/api/index.php', {
                        params: {
                            model: 'project',
                            method: 'listInvoicedProjects',
                            department_id: this.selectedDepartment.id,
                            page: targetPage,
                            per_page: PER_PAGE,
                            filterInvoiceMonth: f.invoiceMonth,
                            filterPaymentMonth: f.paymentMonth,
                            filterPaymentStatus: f.paymentStatus,
                            filterTantou: f.tantou,
                            filterCompany: f.company,
                            filterBranch: f.branch,
                            filterInvoiceNumber: f.invoiceNumber,
                            filterReceiptNumber: f.receiptNumber,
                            filterKeyword: f.keyword
                        }
                    });
                    const data = response.data || {};
                    if (data.status === 'error') {
                        this.errorMessage = data.message || data.error || 'Error';
                        this.projects = [];
                        this.meta = emptyMeta();
                        return;
                    }

                    this.projects = data.projects || [];
                    this.meta = Object.assign(emptyMeta(), data.meta || {});
                    if (!this.meta.totals) {
                        this.meta.totals = emptyMeta().totals;
                    }
                } catch (err) {
                    console.error('loadProjects:', err);
                    const msg = err.response && err.response.data
                        ? (err.response.data.message || err.response.data.error)
                        : null;
                    this.errorMessage = msg || (typeof translateText === 'function'
                        ? translateText('データの読み込みに失敗しました。')
                        : 'データの読み込みに失敗しました。');
                    this.projects = [];
                    this.meta = emptyMeta();
                } finally {
                    if (!silent) {
                        this.loading = false;
                    }
                }
            },
            async loadPaymentReport() {
                if (!this.selectedDepartment) return;

                this.loading = true;
                this.errorMessage = '';

                try {
                    const response = await axios.get('/api/index.php', {
                        params: {
                            model: 'project',
                            method: 'getMonthlyPaymentReport',
                            department_id: this.selectedDepartment.id,
                            month: this.paymentReportMonth
                        }
                    });
                    const data = response.data || {};
                    if (data.status === 'error') {
                        this.errorMessage = data.message || data.error || 'Error';
                        this.paymentReportGroups = [];
                        this.paymentReportMeta = emptyPaymentReportMeta();
                        return;
                    }

                    this.paymentReportGroups = data.groups || [];
                    this.paymentReportMeta = Object.assign(emptyPaymentReportMeta(), {
                        totals: Object.assign(
                            emptyPaymentReportMeta().totals,
                            data.meta && data.meta.totals ? data.meta.totals : {}
                        )
                    });
                } catch (err) {
                    console.error('loadPaymentReport:', err);
                    const msg = err.response && err.response.data
                        ? (err.response.data.message || err.response.data.error)
                        : null;
                    this.errorMessage = msg || (typeof translateText === 'function'
                        ? translateText('データの読み込みに失敗しました。')
                        : 'データの読み込みに失敗しました。');
                    this.paymentReportGroups = [];
                    this.paymentReportMeta = emptyPaymentReportMeta();
                } finally {
                    this.loading = false;
                }
            },
            async loadFiscalYearPaymentStats() {
                if (!this.selectedDepartment) return;

                this.loading = true;
                this.errorMessage = '';

                try {
                    const response = await axios.get('/api/index.php', {
                        params: {
                            model: 'project',
                            method: 'getFiscalYearPaymentStats',
                            department_id: this.selectedDepartment.id,
                            fiscal_year: this.selectedFiscalYear
                        }
                    });
                    const data = response.data || {};
                    if (data.status === 'error') {
                        this.errorMessage = data.message || data.error || 'Error';
                        this.fiscalPaymentStats = emptyFiscalPaymentStats();
                        this.fiscalPaymentStats.fiscal_year = this.selectedFiscalYear;
                        this.fiscalPaymentStats.fiscal_year_label = buildFiscalYearLabel(this.selectedFiscalYear);
                        return;
                    }

                    const meta = data.meta || {};
                    this.fiscalPaymentStats = Object.assign(emptyFiscalPaymentStats(), {
                        fiscal_year: meta.fiscal_year || this.selectedFiscalYear,
                        fiscal_year_label: meta.fiscal_year_label || buildFiscalYearLabel(this.selectedFiscalYear),
                        payment_count: meta.payment_count,
                        payment_amount: meta.payment_amount,
                        yearly_target: meta.yearly_target,
                        achievement_rate: meta.achievement_rate
                    });
                } catch (err) {
                    console.error('loadFiscalYearPaymentStats:', err);
                    const msg = err.response && err.response.data
                        ? (err.response.data.message || err.response.data.error)
                        : null;
                    this.errorMessage = msg || (typeof translateText === 'function'
                        ? translateText('データの読み込みに失敗しました。')
                        : 'データの読み込みに失敗しました。');
                    this.fiscalPaymentStats = emptyFiscalPaymentStats();
                    this.fiscalPaymentStats.fiscal_year = this.selectedFiscalYear;
                    this.fiscalPaymentStats.fiscal_year_label = buildFiscalYearLabel(this.selectedFiscalYear);
                } finally {
                    this.loading = false;
                }
            },
            getPaymentEditStatusLabel(status) {
                const item = PAYMENT_EDIT_STATUSES.find(function(s) {
                    return s.value === status;
                });
                return item ? item.label : '未入金';
            },
            getPaymentEditStatusButtonClass(status) {
                const item = PAYMENT_EDIT_STATUSES.find(function(s) {
                    return s.value === status;
                });
                return item ? ('btn-' + item.color) : 'btn-secondary';
            },
            selectPaymentEditStatus(status) {
                if (!this.paymentEditProject) return;
                this.syncPaymentEditDateFromPicker();
                if (status === '入金済' && !this.isPaymentEditPaidFieldsComplete()) {
                    this.paymentEditError = this.getPaymentEditPaidFieldsValidationError();
                    const dropdownElement = document.getElementById('invoicedPaymentStatusDropdown');
                    if (dropdownElement && typeof bootstrap !== 'undefined') {
                        const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                        if (dropdown) dropdown.hide();
                    }
                    return;
                }
                if (status === this.paymentEditProject.payment_status) {
                    const dropdownElement = document.getElementById('invoicedPaymentStatusDropdown');
                    if (dropdownElement && typeof bootstrap !== 'undefined') {
                        const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                        if (dropdown) dropdown.hide();
                    }
                    return;
                }
                if (status === '未入金' || status === '入金拒否') {
                    this.clearPaymentEditPaidFields();
                }
                this.paymentEditProject.payment_status = status;
                this.paymentEditError = '';
                this.schedulePaymentEditSave();
                const dropdownElement = document.getElementById('invoicedPaymentStatusDropdown');
                if (dropdownElement && typeof bootstrap !== 'undefined') {
                    const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                    if (dropdown) dropdown.hide();
                }
            },
            hasPaymentEditReceiptNumber() {
                return !!String(this.paymentEditProject?.receipt_number || '').trim();
            },
            isPaymentEditPaidFieldsComplete() {
                // Do not call syncPaymentEditDateFromPicker() here — used in template during render;
                // mutating reactive state would hang the page (RESULT_CODE_HUNG).
                return this.hasPaymentEditDate()
                    && this.hasPaymentEditAmount()
                    && this.hasPaymentEditReceiptNumber();
            },
            getPaymentEditPaidFieldsValidationError() {
                const missing = [];
                if (!this.hasPaymentEditDate()) {
                    missing.push(typeof translateText === 'function' ? translateText('入金日') : '入金日');
                }
                if (!this.hasPaymentEditAmount()) {
                    missing.push(typeof translateText === 'function' ? translateText('入金額') : '入金額');
                }
                if (!this.hasPaymentEditReceiptNumber()) {
                    missing.push(typeof translateText === 'function' ? translateText('領収書番号') : '領収書番号');
                }
                if (!missing.length) return '';
                const prefix = typeof translateText === 'function'
                    ? translateText('入金済にするには以下を入力してください')
                    : '入金済にするには以下を入力してください';
                return prefix + ': ' + missing.join('、');
            },
            clearPaymentEditPaidFields() {
                if (!this.paymentEditProject) return;
                this.paymentEditProject.payment_date = '';
                this.paymentEditProject.payment_amount = 0;
                this.paymentEditProject.receipt_number = '';
                this.setPaymentEditServerDate('');
                const el = document.getElementById(PAYMENT_EDIT_DATE_PICKER_ID);
                if (el) {
                    if (el._flatpickr) {
                        el._flatpickr.clear();
                    }
                    el.value = '';
                }
            },
            hasPaymentEditDate() {
                return !!String(this.getPaymentEditServerDate() || this.paymentEditProject?.payment_date || '').trim();
            },
            hasPaymentEditAmount() {
                const amount = this.paymentEditProject?.payment_amount;
                return amount != null && amount !== '' && Number(amount) > 0;
            },
            getPaymentEditServerDate() {
                if (String(this.paymentEditServerDate || '').trim()) {
                    return this.paymentEditServerDate;
                }
                const raw = String(this.paymentEditProject?.payment_date || '').trim();
                if (!raw) return '';
                if (isProjectServerDateTimeFormat(raw)) return raw;
                return fromProjectDateTimeInputValue(raw) || raw;
            },
            setPaymentEditServerDate(value) {
                const raw = String(value || '').trim();
                if (!raw) {
                    this.paymentEditServerDate = '';
                    return;
                }
                this.paymentEditServerDate = isProjectServerDateTimeFormat(raw)
                    ? raw
                    : (fromProjectDateTimeInputValue(raw) || raw);
            },
            getPaymentEditDateForApi() {
                if (!this.paymentEditProject) return '';
                const el = document.getElementById(PAYMENT_EDIT_DATE_PICKER_ID);
                let displayVal = String(this.paymentEditProject.payment_date || '').trim();
                if (el) {
                    const fp = el._flatpickr;
                    if (fp && fp.selectedDates && fp.selectedDates.length > 0) {
                        displayVal = fp.formatDate(fp.selectedDates[0], PROJECT_DATETIME_FLATPICKR_FORMAT);
                    } else if (fp && fp._input) {
                        displayVal = String(fp._input.value || '').trim();
                    } else if (el.value) {
                        displayVal = String(el.value).trim();
                    }
                }
                return fromProjectDateTimeInputValue(displayVal);
            },
            syncPaymentEditDateFromPicker() {
                if (!this.paymentEditProject) return;
                const el = document.getElementById(PAYMENT_EDIT_DATE_PICKER_ID);
                if (!el) return;
                const fp = el._flatpickr;
                let displayVal = '';
                if (fp && fp.selectedDates && fp.selectedDates.length > 0) {
                    displayVal = fp.formatDate(fp.selectedDates[0], PROJECT_DATETIME_FLATPICKR_FORMAT);
                } else if (fp && fp._input) {
                    displayVal = String(fp._input.value || '').trim();
                } else {
                    displayVal = String(el.value || '').trim();
                }
                this.paymentEditProject.payment_date = displayVal;
                this.setPaymentEditServerDate(displayVal);
            },
            initPaymentEditDatePicker() {
                if (!this.paymentEditProject) return;
                const el = document.getElementById(PAYMENT_EDIT_DATE_PICKER_ID);
                if (!el) return;
                const serverValue = this.getPaymentEditServerDate();
                const self = this;
                initPaymentDatePicker(el, serverValue, function(selectedDates, dateStr) {
                    if (!self.paymentEditProject) return;
                    self.paymentEditProject.payment_date = dateStr;
                    self.setPaymentEditServerDate(dateStr);
                    self.schedulePaymentEditSave();
                });
                const inputVal = toProjectDateTimeInputValue(serverValue);
                if (inputVal && this.paymentEditProject.payment_date !== inputVal) {
                    this.paymentEditProject.payment_date = inputVal;
                }
            },
            destroyPaymentEditDatePicker() {
                const el = document.getElementById(PAYMENT_EDIT_DATE_PICKER_ID);
                if (el && el._flatpickr) {
                    el._flatpickr.destroy();
                }
            },
            normalizePaymentEditProject(project) {
                if (!project) return;
                project.version = normalizeProjectVersion(project.version);
                project.payment_version = normalizeProjectVersion(project.payment_version);
                project.payment_status = project.payment_status || '未入金';
                project.payment_amount = project.payment_amount != null ? Number(project.payment_amount) : 0;
                project.invoice_amount = project.invoice_amount != null ? Number(project.invoice_amount) : 0;
                if (!project.payment_date || !String(project.payment_date).trim()) {
                    project.payment_date = '';
                }
                this.paymentEditServerDate = '';
                const raw = String(project.payment_date || '').trim();
                if (raw) {
                    this.setPaymentEditServerDate(
                        isProjectServerDateTimeFormat(raw) ? raw : (fromProjectDateTimeInputValue(raw) || raw)
                    );
                }
            },
            async openPaymentEditModal(project) {
                if (!project || !project.id) return;
                this.paymentEditError = '';
                this.paymentEditSaveStatus = null;
                try {
                    const response = await axios.get('/api/index.php', {
                        params: {
                            model: 'project',
                            method: 'getById',
                            id: project.id
                        }
                    });
                    const data = response.data || {};
                    if (!data || !data.id) {
                        this.paymentEditError = typeof translateText === 'function'
                            ? translateText('データの読み込みに失敗しました。')
                            : 'データの読み込みに失敗しました。';
                        return;
                    }
                    this.paymentEditProject = data;
                    this.paymentEditProjectId = project.id;
                    this.normalizePaymentEditProject(this.paymentEditProject);
                    const modalEl = document.getElementById('invoicedPaymentEditModal');
                    if (!modalEl || typeof bootstrap === 'undefined') return;
                    let modal = bootstrap.Modal.getInstance(modalEl);
                    if (!modal) {
                        modal = new bootstrap.Modal(modalEl);
                        this.paymentEditModalInstance = modal;
                    }
                    modal.show();
                    this.$nextTick(function() {
                        setTimeout(function() {
                            this.initPaymentEditDatePicker();
                        }.bind(this), 150);
                    }.bind(this));
                } catch (err) {
                    console.error('openPaymentEditModal:', err);
                    const msg = err.response && err.response.data
                        ? (err.response.data.message || err.response.data.error)
                        : null;
                    if (typeof showMessage === 'function') {
                        showMessage(msg || '入金情報の読み込みに失敗しました。', true);
                    }
                }
            },
            closePaymentEditModal() {
                clearTimeout(this.paymentEditUpdateTimer);
                clearTimeout(this.paymentEditSaveHideTimer);
                this.paymentEditUpdateTimer = null;
                this.paymentEditSaveHideTimer = null;
                this.destroyPaymentEditDatePicker();
                this.paymentEditProject = null;
                this.paymentEditProjectId = null;
                this.paymentEditSaveStatus = null;
                this.paymentEditError = '';
                this.paymentEditServerDate = '';
            },
            setPaymentEditDateToday() {
                if (!this.paymentEditProject || typeof moment === 'undefined') return;
                const serverNow = moment.tz
                    ? moment.tz(SERVER_TASK_TIMEZONE).format('YYYY-MM-DD HH:mm:ss')
                    : moment().format('YYYY-MM-DD HH:mm:ss');
                const dateStr = toProjectDateTimeInputValue(serverNow);
                this.paymentEditProject.payment_date = dateStr || '';
                this.setPaymentEditServerDate(serverNow);
                const el = document.getElementById(PAYMENT_EDIT_DATE_PICKER_ID);
                if (el && el._flatpickr) {
                    el._flatpickr.setDate(dateStr, false, PROJECT_DATETIME_FLATPICKR_FORMAT);
                }
                this.schedulePaymentEditSave();
            },
            copyInvoiceAmountToPayment() {
                if (!this.paymentEditProject) return;
                this.paymentEditProject.payment_amount = this.paymentEditProject.invoice_amount != null
                    ? Number(this.paymentEditProject.invoice_amount) : 0;
                this.schedulePaymentEditSave();
            },
            schedulePaymentEditSave() {
                if (!this.paymentEditProject) return;
                clearTimeout(this.paymentEditUpdateTimer);
                this.paymentEditUpdateTimer = setTimeout(function() {
                    this.paymentEditUpdateTimer = null;
                    this.savePaymentEdit();
                }.bind(this), 800);
            },
            syncPaymentEditProjectToList() {
                if (!this.paymentEditProject || !this.paymentEditProjectId) return;
                const idx = this.projects.findIndex(function(p) {
                    return String(p.id) === String(this.paymentEditProjectId);
                }.bind(this));
                if (idx < 0) return;
                const fields = ['payment_status', 'payment_date', 'payment_amount', 'receipt_number', 'payment_note'];
                const self = this;
                fields.forEach(function(key) {
                    self.projects[idx][key] = self.paymentEditProject[key];
                });
            },
            async savePaymentEdit() {
                if (this.paymentEditSaving || !this.paymentEditProject || !this.paymentEditProjectId) return;
                clearTimeout(this.paymentEditUpdateTimer);
                this.paymentEditUpdateTimer = null;
                clearTimeout(this.paymentEditSaveHideTimer);
                this.paymentEditSaveStatus = 'loading';
                this.paymentEditSaving = true;
                this.paymentEditError = '';
                try {
                    this.syncPaymentEditDateFromPicker();
                    const p = this.paymentEditProject;
                    if (p.payment_status === '入金済' && !this.isPaymentEditPaidFieldsComplete()) {
                        this.paymentEditSaveStatus = null;
                        this.paymentEditError = this.getPaymentEditPaidFieldsValidationError();
                        return;
                    }
                    const formData = new FormData();
                    formData.append('id', this.paymentEditProjectId);
                    formData.append('payment_status', p.payment_status || '未入金');
                    formData.append('payment_date', this.getPaymentEditDateForApi());
                    formData.append('payment_amount', p.payment_amount != null ? p.payment_amount : 0);
                    formData.append('receipt_number', p.receipt_number || '');
                    formData.append('payment_note', p.payment_note || '');
                    appendPaymentVersionToFormData(formData, p);
                    const response = await axios.post('/api/index.php?model=project&method=updateProjectStatus', formData);
                    const data = response.data || {};
                    if (data.status === 'success') {
                        applyPaymentVersionFromResponse(this.paymentEditProject, data);
                        const apiDate = this.getPaymentEditDateForApi();
                        if (apiDate) {
                            this.setPaymentEditServerDate(apiDate);
                            this.paymentEditProject.payment_date = toProjectDateTimeInputValue(apiDate);
                        } else {
                            this.paymentEditProject.payment_date = '';
                            this.setPaymentEditServerDate('');
                        }
                        this.syncPaymentEditProjectToList();
                        const currentPage = Number(this.meta.page) || 1;
                        await this.loadProjects(currentPage, { silent: true });
                        this.paymentEditSaveStatus = 'saved';
                        this.paymentEditSaveHideTimer = setTimeout(function() {
                            this.paymentEditSaveStatus = null;
                            this.paymentEditSaveHideTimer = null;
                        }.bind(this), 5000);
                    } else {
                        if (handleProjectVersionConflict(data, async function() {
                            await this.openPaymentEditModal({ id: this.paymentEditProjectId });
                        }.bind(this))) {
                            return;
                        }
                        this.paymentEditSaveStatus = null;
                        this.paymentEditError = data.message || data.error || 'Error';
                    }
                } catch (err) {
                    console.error('savePaymentEdit:', err);
                    this.paymentEditSaveStatus = null;
                    const msg = err.response && err.response.data
                        ? (err.response.data.message || err.response.data.error)
                        : null;
                    this.paymentEditError = msg || (typeof translateText === 'function'
                        ? translateText('データの読み込みに失敗しました。')
                        : '保存に失敗しました。');
                } finally {
                    this.paymentEditSaving = false;
                }
            }
        },
        watch: {
            filters: {
                deep: true,
                handler() {
                    if (this.activeSubTab !== 'invoiced') return;
                    this.scheduleFilterSearch();
                }
            }
        },
        mounted() {
            this.loadFiscalYearOptions();
            this.loadDepartments();
        }
    });

    window.invoicedProjectsApp = app.mount('#app');
})();
