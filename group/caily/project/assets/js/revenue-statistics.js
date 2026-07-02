(function() {
    const STORAGE_KEY_MONTH = 'revenue_statistics_selected_month';
    const STORAGE_KEY_DEPT = 'revenue_statistics_selected_department';

    function escapeHtml(text) {
        if (text == null) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatCompanyNameLabel(companyName) {
        var company = String(companyName || '').trim();
        if (!company || company === '（未設定）') return '';

        var text = '他社';
        var style = 'font-size: 0.65rem; vertical-align: middle;';
        if (company.indexOf('大東建託') !== -1) {
            text = '大東';
            style += ' background-color: #dc3545; color: #fff;';
        } else if (company.indexOf('東建コーポレーション') !== -1) {
            text = '東建';
            style += ' background-color: #8B4513; color: #fff;';
        } else {
            style += ' background-color: #0d6efd; color: #fff;';
        }
        return '<span class="badge me-1" style="' + style + '">' + escapeHtml(text) + '</span>';
    }

    function sumProjectField(projects, field) {
        return (projects || []).reduce(function(sum, p) {
            const n = Number(p[field]);
            return sum + (Number.isFinite(n) ? n : 0);
        }, 0);
    }

    function groupProjectsByCompany(projects, amountFields) {
        const map = {};
        const order = [];
        (projects || []).forEach(function(p) {
            const key = (p.company_name && String(p.company_name).trim()) ? String(p.company_name).trim() : '（未設定）';
            if (!map[key]) {
                map[key] = [];
                order.push(key);
            }
            map[key].push(p);
        });
        return order.map(function(company_name) {
            const groupProjects = map[company_name];
            const totals = { count: groupProjects.length };
            (amountFields || []).forEach(function(field) {
                totals[field] = sumProjectField(groupProjects, field);
            });
            return { company_name: company_name, projects: groupProjects, totals: totals };
        });
    }

    function currentYearMonth() {
        const d = new Date();
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        return y + '-' + m;
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

    function emptySummaryTotals() {
        return {
            project_count: 0,
            estimate_count: 0,
            estimate_amount: 0,
            invoice_count: 0,
            invoice_amount: 0,
            payment_count: 0,
            payment_amount: 0
        };
    }

    const app = Vue.createApp({
        data() {
            return {
                departments: [],
                selectedDepartment: null,
                selectedMonth: currentYearMonth(),
                availableMonths: [],
                activeSubTab: 'summary',
                loading: false,
                loadingDepartments: false,
                showEstimatedAllTime: false,
                showCancelledAllTime: false,
                showCancelledEstimatedOnly: true,
                showTantouCaily: true,
                showTantouGuis: true,
                errorMessage: '',
                summaryByCompany: [],
                summaryTotals: emptySummaryTotals(),
                summaryByCompanyCaily: [],
                summaryTotalsCaily: emptySummaryTotals(),
                summaryByCompanyGuis: [],
                summaryTotalsGuis: emptySummaryTotals(),
                targetSummary: {
                    monthly_target_sales: 0,
                    cumulative_target_sales: 0,
                    monthly_actual_sales: 0,
                    cumulative_actual_sales: 0
                },
                estimatedProjects: [],
                invoicedProjects: [],
                completedUninvoiced: [],
                cancelledProjects: []
            };
        },
        computed: {
            monthLabel() {
                const found = (this.availableMonths || []).find(m => m.month === this.selectedMonth);
                return found && found.month_label ? found.month_label : buildMonthLabel(this.selectedMonth);
            },
            currentYearMonthValue() {
                return currentYearMonth();
            },
            estimatedGroups() {
                return groupProjectsByCompany(this.filteredEstimatedProjects, ['amount']);
            },
            invoicedGroups() {
                return groupProjectsByCompany(this.filteredInvoicedProjects, ['amount', 'invoice_amount']);
            },
            backlogGroups() {
                return groupProjectsByCompany(this.filteredBacklogProjects, ['amount']);
            },
            cancelledGroups() {
                return groupProjectsByCompany(this.filteredCancelledProjects, ['amount']);
            },
            filteredInvoicedProjects() {
                return this.filterProjectsByTantou(this.invoicedProjects);
            },
            filteredBacklogProjects() {
                return this.filterProjectsByTantou(this.completedUninvoiced);
            },
            filteredEstimatedProjects() {
                return this.filterProjectsByTantou(this.estimatedProjects);
            },
            filteredCancelledProjects() {
                return this.filterProjectsByTantou(this.cancelledProjects);
            },
            invoicedInvoiceAmountTotal() {
                return sumProjectField(this.filteredInvoicedProjects, 'invoice_amount');
            },
            backlogInvoiceAmountTotal() {
                return sumProjectField(this.filteredBacklogProjects, 'invoice_amount');
            },
            estimatedAmountTotal() {
                return sumProjectField(this.filteredEstimatedProjects, 'amount');
            },
            cancelledAmountTotal() {
                return sumProjectField(this.filteredCancelledProjects, 'amount');
            },
            summaryTantouBlocks() {
                return [
                    {
                        tantou: 'CAILY',
                        rows: this.summaryByCompanyCaily,
                        totals: this.summaryTotalsCaily
                    },
                    {
                        tantou: 'GUIS',
                        rows: this.summaryByCompanyGuis,
                        totals: this.summaryTotalsGuis
                    }
                ];
            },
            summaryUnbilledTotals() {
                const projects = this.completedUninvoiced || [];
                const companySet = {};
                let amount = 0;
                projects.forEach(function(p) {
                    amount += Number(p.amount) || 0;
                    const name = (p.company_name && String(p.company_name).trim())
                        ? String(p.company_name).trim()
                        : '（未設定）';
                    companySet[name] = true;
                });
                return {
                    amount: amount,
                    project_count: projects.length,
                    company_count: Object.keys(companySet).length
                };
            },
            monthlyAchievementRate() {
                const target = Number(this.targetSummary.monthly_target_sales) || 0;
                const actual = Number(this.targetSummary.monthly_actual_sales) || 0;
                if (target <= 0) return 0;
                return (actual / target) * 100;
            },
            cumulativeAchievementRate() {
                const target = Number(this.targetSummary.cumulative_target_sales) || 0;
                const actual = Number(this.targetSummary.cumulative_actual_sales) || 0;
                if (target <= 0) return 0;
                return (actual / target) * 100;
            }
        },
        methods: {
            formatCompanyNameLabel,
            formatCurrency(val) {
                const n = Number(val);
                if (!Number.isFinite(n)) return '¥0';
                return '¥' + Math.round(n).toLocaleString('ja-JP');
            },
            formatCount(val) {
                const n = Number(val);
                return Number.isFinite(n) ? String(Math.round(n)) : '0';
            },
            formatSummaryAmount(val) {
                const n = Number(val);
                if (!Number.isFinite(n) || Math.round(n) === 0) return '';
                return this.formatCurrency(n);
            },
            formatPercent(val) {
                const n = Number(val);
                if (!Number.isFinite(n)) return '0%';
                return Math.round(n).toLocaleString('ja-JP') + '%';
            },
            formatDate(val) {
                if (!val) return '—';
                const s = String(val).trim();
                if (!s || /^0000-00-00/.test(s)) return '—';
                return s.length >= 10 ? s.substring(0, 10) : s;
            },
            formatProjectType(p) {
                const parts = [p && p.parent_type1, p && p.parent_type2]
                    .map(function(v) { return v && String(v).trim(); })
                    .filter(Boolean);
                return parts.length ? parts.join(' / ') : '—';
            },
            formatProjectNouki(p) {
                if (!p) return '—';
                const tantou = String(p.tantou || '').trim();
                if (tantou === 'CAILY') return this.formatDate(p.caily_nouki);
                if (tantou === 'GUIS') return this.formatDate(p.guis_nouki);
                const caily = this.formatDate(p.caily_nouki);
                const guis = this.formatDate(p.guis_nouki);
                if (caily !== '—' && guis !== '—') return caily + ' / ' + guis;
                return caily !== '—' ? caily : guis;
            },
            formatProjectTeams(p) {
                const names = p && p.team_names ? String(p.team_names).trim() : '';
                return names || '—';
            },
            filterProjectsByTantou(projects) {
                const allowCaily = !!this.showTantouCaily;
                const allowGuis = !!this.showTantouGuis;
                if (allowCaily && allowGuis) return projects || [];
                if (!allowCaily && !allowGuis) return [];
                return (projects || []).filter(function(p) {
                    const tantou = String((p && p.tantou) || '').trim().toUpperCase();
                    if (tantou === 'CAILY') return allowCaily;
                    if (tantou === 'GUIS') return allowGuis;
                    return false;
                });
            },
            saveMonthToStorage() {
                try {
                    localStorage.setItem(STORAGE_KEY_MONTH, this.selectedMonth);
                } catch (e) {}
            },
            saveDepartmentToStorage(dept) {
                try {
                    if (dept && dept.id != null) {
                        localStorage.setItem(STORAGE_KEY_DEPT, JSON.stringify({ id: dept.id, name: dept.name }));
                    }
                } catch (e) {}
            },
            loadMonthFromStorage() {
                try {
                    const saved = localStorage.getItem(STORAGE_KEY_MONTH);
                    if (!saved || !/^\d{4}-\d{2}$/.test(saved)) return;

                    const allowed = (this.availableMonths || []).some(function(m) {
                        return m && m.month === saved;
                    });
                    if (allowed) {
                        this.selectedMonth = saved;
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
            refreshAvailableMonths() {
                const map = {};
                buildMonthOptions(18, 12).forEach(function(m) {
                    if (m && m.month) map[m.month] = m;
                });
                if (this.selectedMonth && !map[this.selectedMonth]) {
                    map[this.selectedMonth] = {
                        month: this.selectedMonth,
                        month_label: buildMonthLabel(this.selectedMonth)
                    };
                }
                this.availableMonths = Object.values(map).sort(function(a, b) {
                    return String(b.month).localeCompare(String(a.month));
                });
            },
            async departmentHasRevenueStatsAccess(departmentId) {
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
            async filterDepartmentsForRevenueAccess(departments) {
                const list = (departments || []).filter(function(d) { return d && d.can_project != 0; });
                if (typeof USER_ROLE !== 'undefined' && USER_ROLE === 'administrator') {
                    return list;
                }
                const checks = await Promise.all(list.map(async function(d) {
                    const allowed = await this.departmentHasRevenueStatsAccess(d.id);
                    return allowed ? d : null;
                }.bind(this)));
                return checks.filter(Boolean);
            },
            async loadDepartments() {
                this.loadingDepartments = true;
                try {
                    const response = await axios.get('/api/index.php?model=department&method=listByUser');
                    const all = response.data || [];
                    this.departments = await this.filterDepartmentsForRevenueAccess(all);
                    if (!this.departments.length) {
                        this.selectedDepartment = null;
                        return;
                    }
                    const saved = this.loadDepartmentFromStorage();
                    let dept = null;
                    if (saved) {
                        dept = this.departments.find(d => d && String(d.id) === String(saved.id));
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
                await this.loadStats();
            },
            onMonthChange() {
                this.refreshAvailableMonths();
                this.saveMonthToStorage();
                this.loadStats();
            },
            setSelectedMonth(month) {
                if (!month || !/^\d{4}-\d{2}$/.test(month)) return;
                if (this.selectedMonth === month) return;
                this.selectedMonth = month;
                this.refreshAvailableMonths();
                this.saveMonthToStorage();
                this.loadStats();
            },
            goToPrevMonth() {
                this.setSelectedMonth(addMonthsToYearMonth(this.selectedMonth, -1));
            },
            goToNextMonth() {
                this.setSelectedMonth(addMonthsToYearMonth(this.selectedMonth, 1));
            },
            goToThisMonth() {
                this.setSelectedMonth(currentYearMonth());
            },
            onEstimatedAllTimeChange(checked) {
                this.showEstimatedAllTime = !!checked;
                this.loadStats();
            },
            onCancelledAllTimeChange(checked) {
                this.showCancelledAllTime = !!checked;
                this.loadStats();
            },
            onCancelledEstimatedOnlyChange(checked) {
                this.showCancelledEstimatedOnly = !!checked;
                this.loadStats();
            },
            async loadStats() {
                if (!this.selectedDepartment || !this.selectedMonth) return;
                this.loading = true;
                this.errorMessage = '';
                try {
                    const response = await axios.get('/api/index.php', {
                        params: {
                            model: 'project',
                            method: 'getMonthlyRevenueStats',
                            department_id: this.selectedDepartment.id,
                            month: this.selectedMonth,
                            estimated_all_time: this.showEstimatedAllTime ? 1 : 0,
                            cancelled_all_time: this.showCancelledAllTime ? 1 : 0,
                            cancelled_estimated_only: this.showCancelledEstimatedOnly ? 1 : 0
                        }
                    });
                    const data = response.data || {};
                    if (data.status === 'error') {
                        this.errorMessage = data.message || data.error || 'Error';
                        this.summaryByCompany = [];
                        this.summaryTotals = emptySummaryTotals();
                        this.summaryByCompanyCaily = [];
                        this.summaryTotalsCaily = emptySummaryTotals();
                        this.summaryByCompanyGuis = [];
                        this.summaryTotalsGuis = emptySummaryTotals();
                        this.targetSummary = {
                            monthly_target_sales: 0,
                            cumulative_target_sales: 0,
                            monthly_actual_sales: 0,
                            cumulative_actual_sales: 0
                        };
                        this.estimatedProjects = [];
                        this.invoicedProjects = [];
                        this.completedUninvoiced = [];
                        this.cancelledProjects = [];
                        return;
                    }
                    this.summaryByCompany = data.summary_by_company || [];
                    this.summaryTotals = Object.assign(emptySummaryTotals(), data.summary_totals || {});
                    this.summaryByCompanyCaily = data.summary_by_company_caily || [];
                    this.summaryTotalsCaily = Object.assign(emptySummaryTotals(), data.summary_totals_caily || {});
                    this.summaryByCompanyGuis = data.summary_by_company_guis || [];
                    this.summaryTotalsGuis = Object.assign(emptySummaryTotals(), data.summary_totals_guis || {});
                    this.targetSummary = Object.assign({
                        monthly_target_sales: 0,
                        cumulative_target_sales: 0,
                        monthly_actual_sales: 0,
                        cumulative_actual_sales: 0
                    }, data.meta && data.meta.target_summary ? data.meta.target_summary : {});
                    this.estimatedProjects = data.estimated_projects || [];
                    this.invoicedProjects = data.invoiced_projects || [];
                    this.completedUninvoiced = data.completed_uninvoiced || [];
                    this.cancelledProjects = data.cancelled_projects || [];
                } catch (err) {
                    console.error('loadStats:', err);
                    const msg = err.response && err.response.data
                        ? (err.response.data.message || err.response.data.error)
                        : null;
                    this.errorMessage = msg || (typeof translateText === 'function'
                        ? translateText('データの読み込みに失敗しました。')
                        : 'データの読み込みに失敗しました。');
                } finally {
                    this.loading = false;
                }
            }
        },
        mounted() {
            this.selectedMonth = currentYearMonth();
            this.refreshAvailableMonths();
            this.loadDepartments();
        }
    });

    window.revenueStatsApp = app.mount('#app');
})();
