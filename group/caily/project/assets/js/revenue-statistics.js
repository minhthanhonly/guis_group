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
                errorMessage: '',
                summaryByCompany: [],
                summaryTotals: emptySummaryTotals(),
                summaryByCompanyCaily: [],
                summaryTotalsCaily: emptySummaryTotals(),
                summaryByCompanyGuis: [],
                summaryTotalsGuis: emptySummaryTotals(),
                estimatedProjects: [],
                invoicedProjects: [],
                completedUninvoiced: []
            };
        },
        computed: {
            monthLabel() {
                const found = (this.availableMonths || []).find(m => m.month === this.selectedMonth);
                return found && found.month_label ? found.month_label : buildMonthLabel(this.selectedMonth);
            },
            estimatedGroups() {
                return groupProjectsByCompany(this.estimatedProjects, ['amount']);
            },
            invoicedGroups() {
                return groupProjectsByCompany(this.invoicedProjects, ['amount', 'invoice_amount', 'payment_amount']);
            },
            backlogGroups() {
                return groupProjectsByCompany(this.completedUninvoiced, ['amount']);
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
                    if (saved && /^\d{4}-\d{2}$/.test(saved)) {
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
            mergeAvailableMonths(months) {
                const map = {};
                (months || []).forEach(function(m) {
                    if (m && m.month) map[m.month] = m;
                });
                if (this.selectedMonth && !map[this.selectedMonth]) {
                    map[this.selectedMonth] = {
                        month: this.selectedMonth,
                        month_label: buildMonthLabel(this.selectedMonth)
                    };
                }
                return Object.values(map).sort(function(a, b) {
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
                this.saveMonthToStorage();
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
                            month: this.selectedMonth
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
                        this.estimatedProjects = [];
                        this.invoicedProjects = [];
                        this.completedUninvoiced = [];
                        return;
                    }
                    this.summaryByCompany = data.summary_by_company || [];
                    this.summaryTotals = Object.assign(emptySummaryTotals(), data.summary_totals || {});
                    this.summaryByCompanyCaily = data.summary_by_company_caily || [];
                    this.summaryTotalsCaily = Object.assign(emptySummaryTotals(), data.summary_totals_caily || {});
                    this.summaryByCompanyGuis = data.summary_by_company_guis || [];
                    this.summaryTotalsGuis = Object.assign(emptySummaryTotals(), data.summary_totals_guis || {});
                    this.estimatedProjects = data.estimated_projects || [];
                    this.invoicedProjects = data.invoiced_projects || [];
                    this.completedUninvoiced = data.completed_uninvoiced || [];
                    if (data.meta && Array.isArray(data.meta.available_months)) {
                        this.availableMonths = this.mergeAvailableMonths(data.meta.available_months);
                    }
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
            this.loadMonthFromStorage();
            this.availableMonths = this.mergeAvailableMonths([]);
            this.loadDepartments();
        }
    });

    window.revenueStatsApp = app.mount('#app');
})();
