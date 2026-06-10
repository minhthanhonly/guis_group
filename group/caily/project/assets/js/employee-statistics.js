const { createApp } = Vue;

// Helper function to get current month in YYYY-MM format
function getCurrentMonth() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    return `${year}-${month}`;
}

function getCurrentFiscalEndYear() {
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth() + 1;
    return month >= 7 ? year + 1 : year;
}

function fiscalEndYearFromMonth(ym) {
    if (!ym || !/^\d{4}-\d{2}$/.test(ym)) {
        return getCurrentFiscalEndYear();
    }
    const parts = ym.split('-');
    const y = parseInt(parts[0], 10);
    const m = parseInt(parts[1], 10);
    return m >= 7 ? y + 1 : y;
}

createApp({
    data() {
        return {
            teams: [],
            departments: [],
            sharedFilters: {
                department_id: null,
                team_id: null
            },
            statistics: [],
            summary: [],
            teamStatistics: [],
            revenueTargets: {}, // Map of team_id -> monthly_target for current year/month
            annualSummary: [],
            annualLoading: false,
            loading: false,
            calculating: false,
            deleting: false,
            generating: false,
            chartLoading: false,
            activeTab: 'departments', // 'departments', 'teams', 'employees', 'annual'
            monthlyChartData: [],
            chartInstance: null,
            departmentStatistics: [],
            selectedDepartmentId: null,
            departmentChartLoading: false,
            departmentMonthlyChartData: [],
            departmentChartInstance: null,
            selectedUserId: null,
            selectedUserName: '',
            employeeChartData: [],
            employeeChartInstance: null,
            employeeChartLoading: false,
            sortColumn: null, // Column to sort employee stats by
            sortDirection: 'asc', // 'asc' or 'desc'
            annualSortColumn: null, // Column to sort annual summary by
            annualSortDirection: 'asc', // 'asc' or 'desc'
            selectedYear: getCurrentFiscalEndYear(),
            yearOptions: [],
            filters: {
                period_type: 'month',
                selected_month: getCurrentMonth() // Default to current month (Format: YYYY-MM)
            }
        }
    },
    
    mounted() {
        this.initYearOptions();
        this.loadTeams();
        this.loadDepartments(); // used by teams + employees tabs
        // Auto load statistics for last 12 months
        this.loadStatistics();
        this.loadSummary();
        this.loadDepartmentSummary();
        this.loadRevenueTargets();
        this.loadAnnualSummary();
        
        // Auto calculate statistics on first visit
        this.autoCalculateStatistics();
    },
    
    computed: {
        filterTeams() {
            const deptId = this.sharedFilters.department_id;
            if (!deptId) {
                return this.teams;
            }
            return this.teams.filter(team => String(team.department_id) === String(deptId));
        },

        displayedTeamStatistics() {
            let stats = this.teamStatistics;

            if (this.sharedFilters.department_id) {
                const allowedTeamIds = new Set(
                    this.filterTeams.map(team => String(team.id))
                );
                stats = stats.filter(stat => stat.team_id && allowedTeamIds.has(String(stat.team_id)));
            }
            
            if (this.sharedFilters.team_id !== null && this.sharedFilters.team_id !== undefined) {
                stats = stats.filter(stat => stat.team_id == this.sharedFilters.team_id);
            }
            
            return stats;
        },

        displayedDepartmentStatistics() {
            let stats = this.departmentStatistics || [];
            if (this.sharedFilters.department_id) {
                stats = stats.filter(
                    stat => String(stat.department_id) === String(this.sharedFilters.department_id)
                );
            }
            return stats;
        },

        employeeSummaryTotals() {
            const stats = this.filteredStatistics;
            const totals = {
                row_count: stats.length,
                member_count: new Set(stats.map(s => s.user_id).filter(Boolean)).size,
                total_revenue: 0,
                total_drawings_revenue: 0,
                total_drawing_count: 0,
                total_task_count: 0,
                total_likes: 0,
                total_dislikes: 0,
                total_workload: 0,
                workload_new: 0,
                workload_error_fix: 0,
                workload_change_fix: 0,
                workload_other: 0
            };
            stats.forEach((stat) => {
                totals.total_revenue += parseFloat(stat.revenue || 0);
                totals.total_drawings_revenue += parseFloat(stat.total_drawings_revenue || 0);
                totals.total_drawing_count += parseInt(stat.drawing_count || 0, 10);
                totals.total_task_count += parseInt(stat.task_count || 0, 10);
                totals.total_likes += parseInt(stat.task_likes || 0, 10);
                totals.total_dislikes += parseInt(stat.task_dislikes || 0, 10);
                totals.total_workload += parseFloat(stat.total_workload || 0);
                totals.workload_new += parseFloat(stat.workload_new || 0);
                totals.workload_error_fix += parseFloat(stat.workload_error_fix || 0);
                totals.workload_change_fix += parseFloat(stat.workload_change_fix || 0);
                totals.workload_other += parseFloat(stat.workload_other || 0);
            });
            return totals;
        },
        
        availableMonths() {
            const months = [];
            const today = new Date();
            
            // Generate last 12 months
            for (let i = 11; i >= 0; i--) {
                const date = new Date(today.getFullYear(), today.getMonth() - i, 1);
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const value = `${year}-${month}`;
                const label = `${year}年${month}月`;
                months.push({ value, label });
            }
            
            return months;
        },
        
        filteredStatistics() {
            let stats = this.statistics;
            
            // Filter by selected month
            if (this.filters.selected_month && this.filters.selected_month !== '') {
                stats = stats.filter(stat => {
                    const periodMonth = stat.period_start ? stat.period_start.substring(0, 7) : '';
                    return periodMonth === this.filters.selected_month;
                });
            }
            
            // Sort statistics
            if (this.sortColumn) {
                stats = [...stats].sort((a, b) => {
                    let aVal, bVal;
                    
                    switch (this.sortColumn) {
                        case 'period_start':
                            aVal = a.period_start || '';
                            bVal = b.period_start || '';
                            break;
                        case 'team_name':
                            aVal = (a.team_name || '').toLowerCase();
                            bVal = (b.team_name || '').toLowerCase();
                            break;
                        case 'user_name':
                            aVal = (a.user_name || '').toLowerCase();
                            bVal = (b.user_name || '').toLowerCase();
                            break;
                        case 'revenue':
                            aVal = parseFloat(a.revenue || 0);
                            bVal = parseFloat(b.revenue || 0);
                            break;
                        case 'total_drawings_revenue':
                            aVal = parseFloat(a.total_drawings_revenue || 0);
                            bVal = parseFloat(b.total_drawings_revenue || 0);
                            break;
                        case 'drawing_count':
                            aVal = parseInt(a.drawing_count || 0);
                            bVal = parseInt(b.drawing_count || 0);
                            break;
                        case 'task_count':
                            aVal = parseInt(a.task_count || 0);
                            bVal = parseInt(b.task_count || 0);
                            break;
                        case 'task_likes':
                            aVal = parseInt(a.task_likes || 0);
                            bVal = parseInt(b.task_likes || 0);
                            break;
                        case 'task_dislikes':
                            aVal = parseInt(a.task_dislikes || 0);
                            bVal = parseInt(b.task_dislikes || 0);
                            break;
                        case 'department_name':
                            aVal = (a.department_name || '').toLowerCase();
                            bVal = (b.department_name || '').toLowerCase();
                            break;
                        case 'total_workload':
                            aVal = parseFloat(a.total_workload || 0);
                            bVal = parseFloat(b.total_workload || 0);
                            break;
                        case 'workload_new':
                            aVal = parseFloat(a.workload_new || 0);
                            bVal = parseFloat(b.workload_new || 0);
                            break;
                        case 'workload_error_fix':
                            aVal = parseFloat(a.workload_error_fix || 0);
                            bVal = parseFloat(b.workload_error_fix || 0);
                            break;
                        case 'workload_change_fix':
                            aVal = parseFloat(a.workload_change_fix || 0);
                            bVal = parseFloat(b.workload_change_fix || 0);
                            break;
                        case 'workload_other':
                            aVal = parseFloat(a.workload_other || 0);
                            bVal = parseFloat(b.workload_other || 0);
                            break;
                        case 'updated_at':
                            aVal = a.updated_at || '';
                            bVal = b.updated_at || '';
                            break;
                        default:
                            return 0;
                    }
                    
                    if (aVal < bVal) {
                        return this.sortDirection === 'asc' ? -1 : 1;
                    }
                    if (aVal > bVal) {
                        return this.sortDirection === 'asc' ? 1 : -1;
                    }
                    return 0;
                });
            }
            
            return stats;
        },
        
        showAnnualDepartmentColumn() {
            return !this.sharedFilters.department_id;
        },

        // Sorted data for 年間サマリー
        sortedAnnualSummary() {
            if (!this.annualSummary || this.annualSummary.length === 0) {
                return [];
            }
            
            let data = [...this.annualSummary];
            const column = this.annualSortColumn;
            const direction = this.annualSortDirection === 'desc' ? -1 : 1;
            
            if (!column) {
                return data;
            }
            
            data.sort((a, b) => {
                let aVal, bVal;
                
                switch (column) {
                    case 'team_name':
                        aVal = (a.team_name || '').toLowerCase();
                        bVal = (b.team_name || '').toLowerCase();
                        break;
                    case 'department_name':
                        aVal = (a.department_name || '').toLowerCase();
                        bVal = (b.department_name || '').toLowerCase();
                        break;
                    case 'revenue_year':
                        aVal = parseFloat(a.revenue_year || 0);
                        bVal = parseFloat(b.revenue_year || 0);
                        break;
                    case 'pct_year':
                        aVal = parseFloat(a.pct_year || 0);
                        bVal = parseFloat(b.pct_year || 0);
                        break;
                    case 'best_month':
                        // Sort by best month pct, fallback to revenue
                        aVal = a.best_month ? (a.best_month.pct ?? a.best_month.revenue ?? 0) : -Infinity;
                        bVal = b.best_month ? (b.best_month.pct ?? b.best_month.revenue ?? 0) : -Infinity;
                        break;
                    case 'worst_month':
                        // Sort by worst month pct, fallback to revenue (0 means no data)
                        aVal = a.worst_month ? (a.worst_month.pct ?? a.worst_month.revenue ?? 0) : Infinity;
                        bVal = b.worst_month ? (b.worst_month.pct ?? b.worst_month.revenue ?? 0) : Infinity;
                        break;
                    case 'months_hit':
                        aVal = parseInt(a.months_hit || 0);
                        bVal = parseInt(b.months_hit || 0);
                        break;
                    case 'likes_dislikes':
                        // Sort by (likes - dislikes)
                        aVal = parseInt(a.total_likes || 0) - parseInt(a.total_dislikes || 0);
                        bVal = parseInt(b.total_likes || 0) - parseInt(b.total_dislikes || 0);
                        break;
                    case 'task_count':
                        aVal = parseInt(a.total_task_count || 0);
                        bVal = parseInt(b.total_task_count || 0);
                        break;
                    case 'drawing_count':
                        aVal = parseInt(a.total_drawing_count || 0);
                        bVal = parseInt(b.total_drawing_count || 0);
                        break;
                    case 'score':
                        aVal = parseFloat(a.score || 0);
                        bVal = parseFloat(b.score || 0);
                        break;
                    case 'rank':
                        aVal = (a.rank || '').toString();
                        bVal = (b.rank || '').toString();
                        break;
                    default:
                        return 0;
                }
                
                if (aVal < bVal) return -1 * direction;
                if (aVal > bVal) return 1 * direction;
                return 0;
            });
            
            return data;
        }
    },
    
    methods: {
        getSelectedFiscalEndYear() {
            if (this.activeTab === 'annual' && this.selectedYear) {
                return parseInt(this.selectedYear, 10);
            }
            if (this.filters.selected_month) {
                return fiscalEndYearFromMonth(this.filters.selected_month);
            }
            return getCurrentFiscalEndYear();
        },

        appendFiscalFilterParams(params) {
            params.append('fiscal_year', String(this.getSelectedFiscalEndYear()));
            if (this.filters.selected_month) {
                params.append('selected_month', this.filters.selected_month);
            }
            return params;
        },

        /** Chart APIs always use full monthly range (not single selected_month). */
        appendChartFilterParams(params) {
            params.append('fiscal_year', String(this.getSelectedFiscalEndYear()));
            return params;
        },

        getActiveDepartmentId() {
            return this.sharedFilters.department_id || this.selectedDepartmentId || null;
        },

        async refreshChartsForFilters() {
            if (this.activeTab === 'teams' && this.sharedFilters.team_id) {
                await this.loadMonthlyStatistics();
            } else if (this.chartInstance) {
                this.chartInstance.destroy();
                this.chartInstance = null;
            }

            const departmentId = this.getActiveDepartmentId();
            if (this.activeTab === 'departments' && departmentId) {
                this.selectedDepartmentId = departmentId;
                await this.loadDepartmentMonthlyStatistics();
            } else if (!departmentId && this.departmentChartInstance) {
                this.departmentChartInstance.destroy();
                this.departmentChartInstance = null;
            }
        },

        async loadTeams() {
            try {
                const response = await axios.get('/api/index.php?model=team&method=list');
                this.teams = response.data || [];
                await this.loadSummary();
            } catch (error) {
                console.error('Error loading teams:', error);
                this.showError('チームの読み込みに失敗しました');
            }
        },

        async loadDepartments() {
            try {
                const response = await axios.get('/api/index.php?model=department&method=list');
                this.departments = response.data || [];
            } catch (error) {
                console.error('Error loading departments:', error);
                this.departments = [];
            }
        },

        async onSharedFilterChange(changedField) {
            if (changedField === 'department' && this.sharedFilters.team_id) {
                const teamStillValid = this.filterTeams.some(
                    team => String(team.id) === String(this.sharedFilters.team_id)
                );
                if (!teamStillValid) {
                    this.sharedFilters.team_id = null;
                    if (this.chartInstance) {
                        this.chartInstance.destroy();
                        this.chartInstance = null;
                    }
                }
            }

            if (changedField === 'department') {
                if (this.sharedFilters.department_id) {
                    this.selectedDepartmentId = this.sharedFilters.department_id;
                } else {
                    this.clearDepartmentSelection();
                }
            }

            await this.$nextTick();
            await this.refreshChartsForFilters();

            await this.loadStatistics();
            await this.loadSummary();

            if (this.activeTab === 'annual') {
                await this.loadAnnualSummary();
            }
        },

        initYearOptions() {
            const fiscalEndYear = getCurrentFiscalEndYear();
            // Fiscal end year (Jul-Jun): current, previous, and next
            this.yearOptions = [fiscalEndYear, fiscalEndYear + 1, fiscalEndYear - 1].sort((a, b) => b - a);
        },
        
        selectTeam(teamId) {
            if (this.isTeamSelected(teamId)) {
                this.clearTeamSelection();
                return;
            }
            
            this.sharedFilters.team_id = teamId;
            this.loadMonthlyStatistics();
        },
        
        clearTeamSelection() {
            this.sharedFilters.team_id = null;
            if (this.chartInstance) {
                this.chartInstance.destroy();
                this.chartInstance = null;
            }
        },
        
        isTeamSelected(teamId) {
            if (this.sharedFilters.team_id === null || this.sharedFilters.team_id === undefined) {
                return (teamId === null || teamId === undefined || teamId === '');
            }
            return this.sharedFilters.team_id == teamId;
        },
        
        getSelectedTeamName() {
            if (this.sharedFilters.team_id === null || this.sharedFilters.team_id === undefined) {
                return 'チーム未所属';
            }
            const team = this.teams.find(t => t.id == this.sharedFilters.team_id)
                || this.teamStatistics.find(t => t.team_id == this.sharedFilters.team_id);
            return team ? (team.name || team.team_name || 'チーム未所属') : '';
        },
        
        async loadMonthlyStatistics() {
            if (this.sharedFilters.team_id === null || this.sharedFilters.team_id === undefined || this.sharedFilters.team_id === '' || this.sharedFilters.team_id === 'null') {
                if (this.chartInstance) {
                    this.chartInstance.destroy();
                    this.chartInstance = null;
                }
                return;
            }
            
            this.chartLoading = true;
            try {
                const params = new URLSearchParams({
                    model: 'employeestatistics',
                    method: 'getMonthlyByTeam',
                    team_id: this.sharedFilters.team_id,
                    months: 12
                });
                this.appendChartFilterParams(params);
                
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                const data = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
                this.monthlyChartData = Array.isArray(data) ? data : [];
            } catch (error) {
                console.error('Error loading monthly statistics:', error);
                this.showError('月別統計データの読み込みに失敗しました');
                this.monthlyChartData = [];
            } finally {
                this.chartLoading = false;
            }
            await this.$nextTick();
            await this.$nextTick();
            this.renderChart();
        },
        
        renderChart() {
            const chartElement = document.getElementById('team-monthly-chart');
            if (!chartElement) {
                return;
            }

            if (!this.monthlyChartData || this.monthlyChartData.length === 0) {
                if (this.chartInstance) {
                    this.chartInstance.destroy();
                    this.chartInstance = null;
                }
                chartElement.innerHTML = '<div class="text-center text-muted py-5">選択した期間にチャートデータがありません</div>';
                return;
            }
            
            const categories = this.monthlyChartData.map(row => {
                const [year, monthNum] = row.ym.split('-');
                return `${year}年${monthNum}月`;
            });
            const drawingRevenueData = this.monthlyChartData.map(row => parseFloat(row.drawing_revenue || 0));
            const workloadNewData = this.monthlyChartData.map(row => parseFloat(row.workload_new || 0));
            const workloadErrorFixData = this.monthlyChartData.map(row => parseFloat(row.workload_error_fix || 0));
            const workloadChangeFixData = this.monthlyChartData.map(row => parseFloat(row.workload_change_fix || 0));
            const workloadOtherData = this.monthlyChartData.map(row => parseFloat(row.workload_other || 0));
            const taskCountData = this.monthlyChartData.map(row => parseInt(row.task_count || 0));
            const likesData = this.monthlyChartData.map(row => parseInt(row.likes || 0));
            const dislikesData = this.monthlyChartData.map(row => parseInt(row.dislikes || 0));
            
            const chartColors = ['#1cc88a', '#28c76f', '#ea5455', '#ff9f43', '#a8aaae', '#7367f0', '#3b82f6', '#dc2626'];
            const borderColor = 'rgba(224,224,224,0.2)';
            const labelColor = '#ccc';
            
            if (this.chartInstance) {
                this.chartInstance.destroy();
                this.chartInstance = null;
            }
            chartElement.innerHTML = '';
            
            const options = {
                series: [
                    { name: '図面売上', type: 'column', data: drawingRevenueData },
                    { name: '新規作成', type: 'line', data: workloadNewData },
                    { name: '修正(エラー)', type: 'line', data: workloadErrorFixData },
                    { name: '修正(変更)', type: 'line', data: workloadChangeFixData },
                    { name: 'その他工数', type: 'line', data: workloadOtherData },
                    { name: 'タスク数', type: 'line', data: taskCountData },
                    { name: '良い', type: 'line', data: likesData },
                    { name: '悪い', type: 'line', data: dislikesData }
                ],
                chart: {
                    height: 350,
                    type: 'line',
                    stacked: false,
                    toolbar: {
                        show: false
                    }
                },
                stroke: {
                    width: [0, 3, 3, 3, 3, 3, 3, 3],
                    curve: 'smooth'
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        borderRadius: 4,
                        columnWidth: '30px',
                        maxWidth: '30px'
                    }
                },
                colors: chartColors,
                dataLabels: {
                    enabled: false
                },
                grid: {
                    borderColor: borderColor,
                    xaxis: {
                        lines: {
                            show: true
                        }
                    }
                },
                xaxis: {
                    categories: categories,
                    axisBorder: {
                        show: false
                    },
                    axisTicks: {
                        show: false
                    },
                    labels: {
                        style: {
                            colors: labelColor,
                            fontSize: '12px'
                        },
                        rotate: -45,
                        rotateAlways: false
                    }
                },
                yaxis: [
                    {
                        title: {
                            text: '金額 (¥)',
                            style: {
                                color: labelColor,
                                fontSize: '12px'
                            }
                        },
                        labels: {
                            formatter: function(val) {
                                return '¥' + Math.round(val).toLocaleString('ja-JP');
                            },
                            style: {
                                colors: labelColor,
                                fontSize: '12px'
                            }
                        },
                        min: 0,
                        forceNiceScale: true
                    },
                    {
                        opposite: true,
                        title: {
                            text: '工数 / 数量',
                            style: {
                                color: labelColor,
                                fontSize: '12px'
                            }
                        },
                        labels: {
                            formatter: function(val) {
                                const n = parseFloat(val);
                                if (Number.isInteger(n)) return String(n);
                                return n.toFixed(1);
                            },
                            style: {
                                colors: labelColor,
                                fontSize: '12px'
                            }
                        },
                        min: 0,
                        forceNiceScale: true
                    }
                ],
                tooltip: {
                    shared: true,
                    intersect: false,
                    y: {
                        formatter: function(val, opts) {
                            const seriesIndex = opts.seriesIndex;
                            if (seriesIndex === 0) {
                                return '¥' + Math.round(val).toLocaleString('ja-JP');
                            }
                            if (seriesIndex >= 1 && seriesIndex <= 4) {
                                const n = parseFloat(val);
                                if (Number.isNaN(n) || n <= 0) return '0h';
                                const formatted = Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
                                return formatted + 'h';
                            }
                            return Math.round(val).toLocaleString('ja-JP');
                        }
                    }
                },
                legend: {
                    position: 'top',
                    horizontalAlign: 'left',
                    fontSize: '12px',
                    labels: {
                        colors: labelColor,
                        useSeriesColors: false
                    }
                }
            };
            
            // Wait for ApexCharts to be available and element to be rendered
            let retryCount = 0;
            const maxRetries = 50; // 5 seconds max
            
            const renderChart = () => {
                retryCount++;
                
                // Check if ApexCharts is available
                if (typeof ApexCharts === 'undefined' && typeof window.ApexCharts === 'undefined') {
                    if (retryCount < maxRetries) {
                        console.warn('ApexCharts is not loaded yet, retrying...', retryCount);
                        setTimeout(renderChart, 100);
                        return;
                    } else {
                        console.error('ApexCharts failed to load after', maxRetries, 'retries');
                        this.showError('チャートライブラリの読み込みに失敗しました');
                        return;
                    }
                }
                
                // Use window.ApexCharts if available, otherwise use ApexCharts
                const ApexChartsClass = window.ApexCharts || ApexCharts;
                
                // Wait for Vue to render the element (v-show might delay rendering)
                this.$nextTick(() => {
                    setTimeout(() => {
                        const chartElement = document.getElementById('team-monthly-chart');
                        if (chartElement) {
                            const section = chartElement.closest('.col-12');
                            const sectionHidden = section && window.getComputedStyle(section).display === 'none';
                            if (!sectionHidden) {
                                try {
                                    chartElement.innerHTML = '';
                                    this.chartInstance = new ApexChartsClass(chartElement, options);
                                    this.chartInstance.render();
                                } catch (error) {
                                    console.error('Error rendering chart:', error);
                                    this.showError('チャートの表示に失敗しました: ' + error.message);
                                }
                            } else if (retryCount < maxRetries) {
                                setTimeout(renderChart, 100);
                            }
                        } else {
                            // Element not found, retry
                            if (retryCount < maxRetries) {
                                console.warn('Chart element not found, retrying...', retryCount);
                                setTimeout(renderChart, 100);
                            } else {
                                console.error('Chart element not found after', maxRetries, 'retries');
                            }
                        }
                    }, 100);
                });
            };
            
            renderChart();
        },
        
        async onMonthChange() {
            // Reload statistics when month filter changes
            await this.loadStatistics();
            await this.loadSummary();
            await this.loadDepartmentSummary();
            await this.$nextTick();
            await this.refreshChartsForFilters();
            await this.loadRevenueTargets();
        },

        onYearChange() {
            this.loadAnnualSummary();
        },
        
        async loadRevenueTargets() {
            try {
                // Get year from selected_month (YYYY-MM format)
                const year = this.filters.selected_month ? 
                    parseInt(this.filters.selected_month.substring(0, 4)) : 
                    new Date().getFullYear();
                
                const params = new URLSearchParams({
                    model: 'teamrevenuetarget',
                    method: 'list',
                    year: year
                });
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                const data = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
                const targets = Array.isArray(data) ? data : [];
                
                // Create a map of team_id -> monthly_target
                this.revenueTargets = {};
                targets.forEach(target => {
                    if (target.team_id) {
                        const monthly = parseFloat(target.monthly_target) || 0;
                        const yearly = parseFloat(target.yearly_target) || 0;
                        const monthlyValue = monthly > 0 ? monthly : (yearly > 0 ? yearly / 12 : 0);
                        this.revenueTargets[target.team_id] = monthlyValue;
                    }
                });
            } catch (error) {
                console.error('Error loading revenue targets:', error);
                // Don't show error to user, just log it
                this.revenueTargets = {};
            }
        },

        async loadAnnualSummary() {
            this.annualLoading = true;
            try {
                const params = new URLSearchParams({
                    model: 'employeestatistics',
                    method: 'getAnnualSummary',
                    year: this.selectedYear
                });
                if (this.sharedFilters.department_id) {
                    params.append('department_id', this.sharedFilters.department_id);
                }
                if (this.sharedFilters.team_id) {
                    params.append('team_id', this.sharedFilters.team_id);
                }
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                const data = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
                if (Array.isArray(data)) {
                    this.annualSummary = data;
                } else if (data && data.error) {
                    console.error('Annual summary API error:', data.error);
                    this.showError('年間サマリーの読み込みに失敗しました');
                    this.annualSummary = [];
                } else {
                    this.annualSummary = [];
                }
            } catch (error) {
                console.error('Error loading annual summary:', error);
                this.showError('年間サマリーの読み込みに失敗しました');
                this.annualSummary = [];
            } finally {
                this.annualLoading = false;
            }
        },
        
        // revenue: aggregated revenue for period
        // teamId: team id
        // months: number of months in the aggregated period (default 1)
        getRevenueWithTarget(revenue, teamId, months = 1) {
            const revenueValue = parseFloat(revenue) || 0;
            const monthlyTarget = this.revenueTargets[teamId] || 0;
            const targetValue = monthlyTarget * Math.max(1, months);
            
            // If no data or target is zero, show "データなし"
            if ((revenueValue <= 0 && targetValue <= 0) || targetValue <= 0) {
                return '<span class="text-muted">データなし</span>';
            }
            
            // Calculate percentage
            const percentage = Math.round((revenueValue / targetValue) * 100);
            
            // Format: ¥9,000 (目標¥150,000, 8%)
            // Use HTML to style percentage if needed
            const percentageClass = percentage >= 100 ? 'text-success' : (percentage >= 80 ? 'text-warning' : 'text-danger');
            return `${this.formatCurrency(revenueValue)} <span class="text-muted">(目標${this.formatCurrency(targetValue)}. <span class="${percentageClass}">${percentage}%</span>)</span>`;
        },

        // For team tab: keep old behavior (no months multiplier, show revenue if no target)
        getRevenueWithTargetTeam(revenue, teamId) {
            const revenueValue = parseFloat(revenue) || 0;
            const monthlyTarget = this.revenueTargets[teamId] || 0;
            if (monthlyTarget <= 0) {
                return this.formatCurrency(revenueValue);
            }
            const percentage = Math.round((revenueValue / monthlyTarget) * 100);
            const percentageClass = percentage >= 100 ? 'text-success' : (percentage >= 80 ? 'text-warning' : 'text-danger');
            return `${this.formatCurrency(revenueValue)} <span class="text-muted">(目標${this.formatCurrency(monthlyTarget)}. <span class="${percentageClass}">${percentage}%</span>)</span>`;
        },
        
        sortBy(column) {
            if (this.sortColumn === column) {
                // Toggle sort direction if clicking the same column
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                // Set new column and default to ascending
                this.sortColumn = column;
                this.sortDirection = 'asc';
            }
        },
        
        getSortIcon(column) {
            if (this.sortColumn !== column) {
                return 'fa-sort';
            }
            return this.sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
        },
        
        // Sorting for annual summary
        sortAnnualBy(column) {
            if (this.annualSortColumn === column) {
                this.annualSortDirection = this.annualSortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.annualSortColumn = column;
                this.annualSortDirection = 'asc';
            }
        },
        
        getAnnualSortIcon(column) {
            if (this.annualSortColumn !== column) {
                return 'fa-sort';
            }
            return this.annualSortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
        },
        
        calculateTeamStatisticsByMonth() {
            if (!this.statistics || this.statistics.length === 0) {
                return [];
            }
            
            // Filter statistics by selected month
            const monthStats = this.statistics.filter(stat => {
                const periodMonth = stat.period_start ? stat.period_start.substring(0, 7) : '';
                return periodMonth === this.filters.selected_month;
            });
            
            // Group by team and aggregate
            const teamMap = {};
            
            monthStats.forEach(stat => {
                const teamId = stat.team_id || 'no-team';
                const teamName = stat.team_name || 'チーム未所属';
                
                if (!teamMap[teamId]) {
                    teamMap[teamId] = {
                        team_id: teamId === 'no-team' ? null : teamId,
                        team_name: teamName,
                        member_count: 0,
                        total_revenue: 0,
                        total_likes: 0,
                        total_dislikes: 0,
                        total_drawings_revenue: 0,
                        total_drawing_count: 0,
                        total_task_count: 0,
                        members: new Set()
                    };
                }
                
                // Count unique members
                if (stat.user_id) {
                    teamMap[teamId].members.add(stat.user_id);
                }
                
                // Aggregate values
                teamMap[teamId].total_revenue += parseFloat(stat.revenue || 0);
                teamMap[teamId].total_likes += parseInt(stat.task_likes || 0);
                teamMap[teamId].total_dislikes += parseInt(stat.task_dislikes || 0);
                teamMap[teamId].total_drawings_revenue += parseFloat(stat.total_drawings_revenue || 0);
                teamMap[teamId].total_drawing_count += parseInt(stat.drawing_count || 0);
                teamMap[teamId].total_task_count += parseInt(stat.task_count || 0);
            });
            
            // Convert to array and set member_count
            return Object.values(teamMap).map(team => {
                team.member_count = team.members.size;
                delete team.members;
                return team;
            });
        },
        
        switchTab(tab) {
            this.activeTab = tab;
            // Clear selection when switching tabs
            if (tab === 'employees') {
                this.loadDepartments();
            } else if (tab === 'teams') {
                this.loadDepartments();
                this.selectedUserId = null;
                this.selectedUserName = '';
                this.selectedDepartmentId = null;
                if (this.employeeChartInstance) {
                    this.employeeChartInstance.destroy();
                    this.employeeChartInstance = null;
                }
                if (this.departmentChartInstance) {
                    this.departmentChartInstance.destroy();
                    this.departmentChartInstance = null;
                }
                this.$nextTick(() => this.refreshChartsForFilters());
            } else if (tab === 'departments') {
                this.selectedUserId = null;
                this.selectedUserName = '';
                if (this.chartInstance) {
                    this.chartInstance.destroy();
                    this.chartInstance = null;
                }
                if (this.employeeChartInstance) {
                    this.employeeChartInstance.destroy();
                    this.employeeChartInstance = null;
                }
                this.loadDepartmentSummary();
                this.$nextTick(() => this.refreshChartsForFilters());
            } else if (tab === 'annual') {
                this.selectedDepartmentId = null;
                if (this.departmentChartInstance) {
                    this.departmentChartInstance.destroy();
                    this.departmentChartInstance = null;
                }
                this.loadAnnualSummary();
            }
        },

        isDepartmentSelected(departmentId) {
            return this.selectedDepartmentId == departmentId;
        },

        getSelectedDepartmentName() {
            if (!this.selectedDepartmentId) return '';
            const dept = this.departmentStatistics.find(d => d.department_id == this.selectedDepartmentId);
            return dept ? (dept.department_name || '') : '';
        },

        selectDepartment(departmentId) {
            if (this.isDepartmentSelected(departmentId)) {
                this.clearDepartmentSelection();
                return;
            }
            this.sharedFilters.department_id = departmentId;
            this.selectedDepartmentId = departmentId;
            this.loadDepartmentMonthlyStatistics();
        },

        clearDepartmentSelection() {
            this.selectedDepartmentId = null;
            if (this.departmentChartInstance) {
                this.departmentChartInstance.destroy();
                this.departmentChartInstance = null;
            }
        },

        formatWorkload(value) {
            const n = parseFloat(value);
            if (Number.isNaN(n) || n <= 0) return '0h';
            const formatted = Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
            return formatted + 'h';
        },

        async loadDepartmentSummary() {
            try {
                const params = new URLSearchParams({
                    model: 'employeestatistics',
                    method: 'getSummaryByDepartment',
                    period_type: this.filters.period_type,
                    months: 12
                });
                this.appendFiscalFilterParams(params);
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                this.departmentStatistics = response.data || [];
            } catch (error) {
                console.error('Error loading department summary:', error);
                this.departmentStatistics = [];
            }
        },

        async loadDepartmentMonthlyStatistics() {
            const departmentId = this.getActiveDepartmentId();
            if (!departmentId) {
                if (this.departmentChartInstance) {
                    this.departmentChartInstance.destroy();
                    this.departmentChartInstance = null;
                }
                return;
            }

            this.selectedDepartmentId = departmentId;
            this.departmentChartLoading = true;
            try {
                const params = new URLSearchParams({
                    model: 'employeestatistics',
                    method: 'getMonthlyByDepartment',
                    department_id: departmentId,
                    months: 12
                });
                this.appendChartFilterParams(params);
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                const data = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
                this.departmentMonthlyChartData = Array.isArray(data) ? data : [];
            } catch (error) {
                console.error('Error loading department monthly statistics:', error);
                this.showError('部署の月別統計データの読み込みに失敗しました');
                this.departmentMonthlyChartData = [];
            } finally {
                this.departmentChartLoading = false;
            }
            await this.$nextTick();
            await this.$nextTick();
            this.renderDepartmentChart();
        },

        renderDepartmentChart() {
            const chartElement = document.getElementById('department-monthly-chart');
            if (!chartElement) {
                return;
            }

            if (!this.departmentMonthlyChartData || this.departmentMonthlyChartData.length === 0) {
                if (this.departmentChartInstance) {
                    this.departmentChartInstance.destroy();
                    this.departmentChartInstance = null;
                }
                chartElement.innerHTML = '<div class="text-center text-muted py-5">選択した期間にチャートデータがありません</div>';
                return;
            }

            const categories = this.departmentMonthlyChartData.map(row => {
                const [year, monthNum] = row.ym.split('-');
                return `${year}年${monthNum}月`;
            });
            const revenueData = this.departmentMonthlyChartData.map(row => parseFloat(row.revenue || 0));
            const workloadNewData = this.departmentMonthlyChartData.map(row => parseFloat(row.workload_new || 0));
            const workloadErrorFixData = this.departmentMonthlyChartData.map(row => parseFloat(row.workload_error_fix || 0));
            const workloadChangeFixData = this.departmentMonthlyChartData.map(row => parseFloat(row.workload_change_fix || 0));
            const workloadOtherData = this.departmentMonthlyChartData.map(row => parseFloat(row.workload_other || 0));
            const taskCountData = this.departmentMonthlyChartData.map(row => parseInt(row.task_count || 0));
            const likesData = this.departmentMonthlyChartData.map(row => parseInt(row.likes || 0));
            const dislikesData = this.departmentMonthlyChartData.map(row => parseInt(row.dislikes || 0));

            const chartColors = ['#1cc88a', '#28c76f', '#ea5455', '#ff9f43', '#a8aaae', '#7367f0', '#3b82f6', '#dc2626'];
            const borderColor = 'rgba(224,224,224,0.2)';
            const labelColor = '#ccc';

            if (this.departmentChartInstance) {
                this.departmentChartInstance.destroy();
                this.departmentChartInstance = null;
            }
            chartElement.innerHTML = '';

            const options = {
                series: [
                    { name: '売上高', type: 'column', data: revenueData },
                    { name: '新規作成', type: 'line', data: workloadNewData },
                    { name: '修正(エラー)', type: 'line', data: workloadErrorFixData },
                    { name: '修正(変更)', type: 'line', data: workloadChangeFixData },
                    { name: 'その他工数', type: 'line', data: workloadOtherData },
                    { name: 'タスク数', type: 'line', data: taskCountData },
                    { name: '良い', type: 'line', data: likesData },
                    { name: '悪い', type: 'line', data: dislikesData }
                ],
                chart: {
                    height: 350,
                    type: 'line',
                    stacked: false,
                    toolbar: { show: false }
                },
                stroke: {
                    width: [0, 3, 3, 3, 3, 3, 3, 3],
                    curve: 'smooth'
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        borderRadius: 4,
                        columnWidth: '30px',
                        maxWidth: '30px'
                    }
                },
                colors: chartColors,
                dataLabels: { enabled: false },
                grid: {
                    borderColor: borderColor,
                    xaxis: { lines: { show: true } }
                },
                xaxis: {
                    categories,
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: {
                        style: { colors: labelColor, fontSize: '12px' },
                        rotate: -45,
                        rotateAlways: false
                    }
                },
                yaxis: [
                    {
                        title: { text: '金額 (¥)', style: { color: labelColor, fontSize: '12px' } },
                        labels: {
                            formatter(val) { return '¥' + Math.round(val).toLocaleString('ja-JP'); },
                            style: { colors: labelColor, fontSize: '12px' }
                        },
                        min: 0,
                        forceNiceScale: true
                    },
                    {
                        opposite: true,
                        title: { text: '工数 / 数量', style: { color: labelColor, fontSize: '12px' } },
                        labels: {
                            formatter(val) {
                                const n = parseFloat(val);
                                if (Number.isInteger(n)) return String(n);
                                return n.toFixed(1);
                            },
                            style: { colors: labelColor, fontSize: '12px' }
                        },
                        min: 0,
                        forceNiceScale: true
                    }
                ],
                tooltip: {
                    shared: true,
                    intersect: false,
                    y: {
                        formatter(val, opts) {
                            if (opts.seriesIndex === 0) {
                                return '¥' + Math.round(val).toLocaleString('ja-JP');
                            }
                            if (opts.seriesIndex >= 1 && opts.seriesIndex <= 4) {
                                const n = parseFloat(val);
                                if (Number.isNaN(n) || n <= 0) return '0h';
                                const formatted = Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
                                return formatted + 'h';
                            }
                            return Math.round(val).toLocaleString('ja-JP');
                        }
                    }
                },
                legend: {
                    position: 'top',
                    horizontalAlign: 'left',
                    fontSize: '12px',
                    labels: { colors: labelColor, useSeriesColors: false }
                }
            };

            const ApexChartsClass = window.ApexCharts || ApexCharts;
            let retryCount = 0;
            const maxRetries = 50;
            const renderDepartmentChartElement = () => {
                retryCount++;
                this.$nextTick(() => {
                    setTimeout(() => {
                        const el = document.getElementById('department-monthly-chart');
                        if (el) {
                            const section = el.closest('.col-12');
                            const sectionHidden = section && window.getComputedStyle(section).display === 'none';
                            if (!sectionHidden) {
                                try {
                                    el.innerHTML = '';
                                    this.departmentChartInstance = new ApexChartsClass(el, options);
                                    this.departmentChartInstance.render();
                                } catch (error) {
                                    console.error('Error rendering department chart:', error);
                                }
                                return;
                            }
                        }
                        if (retryCount < maxRetries) {
                            setTimeout(renderDepartmentChartElement, 100);
                        }
                    }, 100);
                });
            };
            renderDepartmentChartElement();
        },
        
        selectEmployee(userId, userName) {
            this.selectedUserId = userId;
            this.selectedUserName = userName;
            this.loadEmployeeMonthlyStatistics();
            
            // Scroll to chart after a short delay to ensure it's rendered
            this.$nextTick(() => {
                setTimeout(() => {
                    const chartSection = document.getElementById('employee-chart-section');
                    if (chartSection) {
                        chartSection.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'start' 
                        });
                    }
                }, 300);
            });
        },
        
        clearEmployeeSelection() {
            this.selectedUserId = null;
            this.selectedUserName = '';
            if (this.employeeChartInstance) {
                this.employeeChartInstance.destroy();
                this.employeeChartInstance = null;
            }
        },
        
        async loadEmployeeMonthlyStatistics() {
            if (!this.selectedUserId) {
                if (this.employeeChartInstance) {
                    this.employeeChartInstance.destroy();
                    this.employeeChartInstance = null;
                }
                return;
            }
            
            this.employeeChartLoading = true;
            try {
                const params = new URLSearchParams({
                    model: 'employeestatistics',
                    method: 'list',
                    period_type: 'month',
                    months: 12,
                    user_id: this.selectedUserId
                });
                this.appendFiscalFilterParams(params);
                
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                this.employeeChartData = response.data || [];
                
                // Render chart
                this.renderEmployeeChart();
            } catch (error) {
                console.error('Error loading employee monthly statistics:', error);
                this.showError('従業員の月別統計データの読み込みに失敗しました');
            } finally {
                this.employeeChartLoading = false;
            }
        },
        
        renderEmployeeChart() {
            if (!this.employeeChartData || this.employeeChartData.length === 0) {
                console.log('No employee chart data available');
                return;
            }
            
            console.log('Rendering employee chart with data:', this.employeeChartData.length, 'records');
            
            // Group data by month
            const monthlyData = {};
            
            this.employeeChartData.forEach(stat => {
                const monthKey = stat.period_start.substring(0, 7); // YYYY-MM
                if (!monthlyData[monthKey]) {
                    monthlyData[monthKey] = {
                        month: monthKey,
                        revenue: 0,
                        drawing_revenue: 0,
                        drawing_count: 0,
                        task_count: 0,
                        likes: 0,
                        dislikes: 0
                    };
                }
                
                monthlyData[monthKey].revenue += parseFloat(stat.revenue || 0);
                monthlyData[monthKey].drawing_revenue += parseFloat(stat.total_drawings_revenue || 0);
                monthlyData[monthKey].drawing_count += parseInt(stat.drawing_count || 0);
                monthlyData[monthKey].task_count += parseInt(stat.task_count || 0);
                monthlyData[monthKey].likes += parseInt(stat.task_likes || 0);
                monthlyData[monthKey].dislikes += parseInt(stat.task_dislikes || 0);
            });
            
            // Sort by month
            const sortedMonths = Object.keys(monthlyData).sort();
            const categories = sortedMonths.map(month => {
                const [year, monthNum] = month.split('-');
                return `${year}年${monthNum}月`;
            });
            
            // Prepare series data
            const revenueData = sortedMonths.map(month => monthlyData[month].revenue);
            const drawingRevenueData = sortedMonths.map(month => monthlyData[month].drawing_revenue);
            const drawingCountData = sortedMonths.map(month => monthlyData[month].drawing_count);
            const taskCountData = sortedMonths.map(month => monthlyData[month].task_count);
            const likesData = sortedMonths.map(month => monthlyData[month].likes);
            const dislikesData = sortedMonths.map(month => monthlyData[month].dislikes);
            
            // Use colors that match the meaning of each metric
            // 図面売上: Green (#1cc88a) - positive/revenue
            // 図面数: Orange (#ff9f43) - neutral/count
            // タスク数: Purple (#7367f0) - neutral/count
            // 良い: Blue (#3b82f6) - positive/good (different from green)
            // 悪い: Red (#dc2626) - negative/bad
            const chartColors = ['#1cc88a', '#7367f0', '#3b82f6', '#dc2626', '#ff9f43'];
            const borderColor = 'rgba(224,224,224,0.2)';
            const labelColor = '#ccc';
            
            // Destroy existing chart
            if (this.employeeChartInstance) {
                this.employeeChartInstance.destroy();
            }
            
            // Create new chart with style similar to index.php
            const options = {
                series: [
                    // {
                    //     name: '売上高',
                    //     type: 'column',
                    //     data: revenueData
                    // },
                    {
                        name: '図面売上',
                        type: 'column',
                        data: drawingRevenueData
                    },
                    // {
                    //     name: '図面数',
                    //     type: 'line',
                    //     data: drawingCountData
                    // },
                    {
                        name: 'タスク数',
                        type: 'line',
                        data: taskCountData
                    },
                    {
                        name: '良い',
                        type: 'line',
                        data: likesData
                    },
                    {
                        name: '悪い',
                        type: 'line',
                        data: dislikesData
                    }
                ],
                chart: {
                    height: 350,
                    type: 'line',
                    stacked: false,
                    toolbar: {
                        show: false
                    }
                },
                stroke: {
                    width: [0, 0, 3, 3, 3, 3],
                    curve: 'smooth'
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        borderRadius: 4,
                        columnWidth: '30px',
                        maxWidth: '30px'
                    }
                },
                colors: chartColors,
                dataLabels: {
                    enabled: false
                },
                grid: {
                    borderColor: borderColor,
                    xaxis: {
                        lines: {
                            show: true
                        }
                    }
                },
                xaxis: {
                    categories: categories,
                    axisBorder: {
                        show: false
                    },
                    axisTicks: {
                        show: false
                    },
                    labels: {
                        style: {
                            colors: labelColor,
                            fontSize: '12px'
                        },
                        rotate: -45,
                        rotateAlways: false
                    }
                },
                yaxis: [
                    {
                        title: {
                            text: '金額 (¥)',
                            style: {
                                color: labelColor,
                                fontSize: '12px'
                            }
                        },
                        labels: {
                            formatter: function(val) {
                                return '¥' + Math.round(val).toLocaleString('ja-JP');
                            },
                            style: {
                                colors: labelColor,
                                fontSize: '12px'
                            }
                        },
                        // Scale only for revenue columns (series 0 and 1)
                        min: 0,
                        forceNiceScale: true
                    },
                    {
                        opposite: true,
                        title: {
                            text: '数量',
                            style: {
                                color: labelColor,
                                fontSize: '12px'
                            }
                        },
                        labels: {
                            formatter: function(val) {
                                return Math.round(val).toLocaleString('ja-JP');
                            },
                            style: {
                                colors: labelColor,
                                fontSize: '12px'
                            }
                        },
                        // Scale only for count metrics (series 2, 3, 4, 5)
                        min: 0,
                        forceNiceScale: true
                    }
                ],
                tooltip: {
                    shared: true,
                    intersect: false,
                    y: {
                        formatter: function(val, opts) {
                            const seriesIndex = opts.seriesIndex;
                            // Only 図面売上 (seriesIndex 0) should have currency symbol
                            if (seriesIndex === 0) {
                                return '¥' + Math.round(val).toLocaleString('ja-JP');
                            }
                            // All other series (タスク数, 良い, 悪い) are counts, no currency
                            return Math.round(val).toLocaleString('ja-JP');
                        }
                    }
                },
                legend: {
                    position: 'top',
                    horizontalAlign: 'left',
                    fontSize: '12px',
                    labels: {
                        colors: labelColor,
                        useSeriesColors: false
                    }
                }
            };
            
            // Wait for ApexCharts to be available and element to be rendered
            let retryCount = 0;
            const maxRetries = 50; // 5 seconds max
            
            const renderChart = () => {
                retryCount++;
                
                // Check if ApexCharts is available
                if (typeof ApexCharts === 'undefined' && typeof window.ApexCharts === 'undefined') {
                    if (retryCount < maxRetries) {
                        console.warn('ApexCharts is not loaded yet, retrying...', retryCount);
                        setTimeout(renderChart, 100);
                        return;
                    } else {
                        console.error('ApexCharts failed to load after', maxRetries, 'retries');
                        this.showError('チャートライブラリの読み込みに失敗しました');
                        return;
                    }
                }
                
                // Use window.ApexCharts if available, otherwise use ApexCharts
                const ApexChartsClass = window.ApexCharts || ApexCharts;
                
                // Wait for Vue to render the element (v-show might delay rendering)
                this.$nextTick(() => {
                    setTimeout(() => {
                        const chartElement = document.getElementById('employee-monthly-chart');
                        if (chartElement) {
                            // Check if element is visible (not hidden by v-show)
                            const isVisible = chartElement.offsetParent !== null || 
                                            chartElement.style.display !== 'none';
                            
                            if (isVisible) {
                                try {
                                    if (this.employeeChartInstance) {
                                        this.employeeChartInstance.destroy();
                                    }
                                    console.log('Creating employee ApexCharts instance...');
                                    this.employeeChartInstance = new ApexChartsClass(chartElement, options);
                                    this.employeeChartInstance.render();
                                    console.log('Employee chart rendered successfully');
                                } catch (error) {
                                    console.error('Error rendering employee chart:', error);
                                    this.showError('チャートの表示に失敗しました: ' + error.message);
                                }
                            } else {
                                // Element not visible yet, retry
                                if (retryCount < maxRetries) {
                                    console.warn('Employee chart element not visible yet, retrying...', retryCount);
                                    setTimeout(renderChart, 100);
                                } else {
                                    console.error('Employee chart element not visible after', maxRetries, 'retries');
                                }
                            }
                        } else {
                            // Element not found, retry
                            if (retryCount < maxRetries) {
                                console.warn('Employee chart element not found, retrying...', retryCount);
                                setTimeout(renderChart, 100);
                            } else {
                                console.error('Employee chart element not found after', maxRetries, 'retries');
                            }
                        }
                    }, 100);
                });
            };
            
            renderChart();
        },
        
        async loadStatistics() {
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    model: 'employeestatistics',
                    method: 'list',
                    period_type: this.filters.period_type,
                    months: 12 // Load last 12 months
                });
                this.appendFiscalFilterParams(params);

                if (this.sharedFilters.department_id) {
                    params.append('department_id', this.sharedFilters.department_id);
                }
                if (this.sharedFilters.team_id) {
                    params.append('team_id', this.sharedFilters.team_id);
                }
                
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                this.statistics = response.data || [];
            } catch (error) {
                console.error('Error loading statistics:', error);
                this.showError('統計データの読み込みに失敗しました');
            } finally {
                this.loading = false;
            }
        },
        
        async loadSummary() {
            try {
                const params = new URLSearchParams({
                    model: 'employeestatistics',
                    method: 'getSummaryByTeam',
                    period_type: this.filters.period_type,
                    months: 12 // Load last 12 months
                });
                this.appendFiscalFilterParams(params);

                if (this.sharedFilters.department_id) {
                    params.append('department_id', this.sharedFilters.department_id);
                }
                if (this.sharedFilters.team_id) {
                    params.append('team_id', this.sharedFilters.team_id);
                }
                
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                this.teamStatistics = response.data || [];
                
                // Load revenue targets after loading summary
                await this.loadRevenueTargets();
            } catch (error) {
                console.error('Error loading summary:', error);
            }
        },
        
        async calculateStatistics() {
            this.calculating = true;
            try {
                // Calculate statistics for last 12 months based on task end dates
                const params = new URLSearchParams({
                    model: 'employeestatistics',
                    method: 'calculateStatistics',
                    period_type: this.filters.period_type,
                    months: 12 // Calculate for last 12 months
                });
                this.appendFiscalFilterParams(params);
                
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                
                if (response.data.status === 'success') {
                    this.showSuccess(response.data.message || '統計を計算しました');
                    await this.loadStatistics();
                    await this.loadSummary();
                    await this.loadDepartmentSummary();
                } else {
                    this.showError(response.data.message || '統計の計算に失敗しました');
                }
            } catch (error) {
                console.error('Error calculating statistics:', error);
                this.showError('統計の計算に失敗しました');
            } finally {
                this.calculating = false;
            }
        },
        
        async deleteStatistics() {
            // Confirm before deleting
            if (!confirm('過去12ヶ月の統計データを削除しますか？この操作は取り消せません。')) {
                return;
            }
            
            this.deleting = true;
            try {
                const params = new URLSearchParams({
                    model: 'employeestatistics',
                    method: 'deleteStatistics',
                    months: 12 // Delete last 12 months
                });
                
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                if (response.data.status === 'success') {
                    this.showSuccess(response.data.message || '統計データを削除しました');
                    await this.loadStatistics();
                    await this.loadSummary();
                    await this.loadDepartmentSummary();
                    // Clear selected team and employee charts
                    this.clearTeamSelection();
                    this.clearEmployeeSelection();
                    this.clearDepartmentSelection();
                } else {
                    this.showError(response.data.message || '統計データの削除に失敗しました');
                }
            } catch (error) {
                console.error('Error deleting statistics:', error);
                this.showError('統計データの削除に失敗しました');
            } finally {
                this.deleting = false;
            }
        },
        
        async generateSampleStatistics() {
            // Generate sample statistics data for last 12 months to simulate reports
            this.generating = true;
            try {
                const params = new URLSearchParams({
                    model: 'employeestatistics',
                    method: 'generateSampleData',
                    period_type: this.filters.period_type,
                    months: 12
                });
                this.appendFiscalFilterParams(params);
                
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                
                if (response.data && response.data.status === 'success') {
                    this.showSuccess(response.data.message || 'サンプル統計データを追加しました');
                    await this.loadStatistics();
                    await this.loadSummary();
                    await this.loadDepartmentSummary();
                    // Clear selection to reflect new data
                    this.clearTeamSelection();
                    this.clearEmployeeSelection();
                    this.clearDepartmentSelection();
                } else {
                    this.showError(response.data?.message || 'サンプル統計データの追加に失敗しました');
                }
            } catch (error) {
                console.error('Error generating sample statistics:', error);
                this.showError('サンプル統計データの追加に失敗しました');
            } finally {
                this.generating = false;
            }
        },
        
        async autoCalculateStatistics() {
            try {
                // Check if auto-calculate is enabled from server config
                const configResponse = await axios.get('/api/index.php', {
                    params: {
                        model: 'employeestatistics',
                        method: 'getAutoCalculateConfig'
                    }
                });
                
                if (configResponse.data && (configResponse.data.enabled == "" || configResponse.data.enabled == "0")) {
                    console.log('Auto-calculate statistics is disabled in configuration');
                    return;
                }
                
                // Check if statistics have been auto-calculated before
                const storageKey = 'employee_statistics_auto_calculated';
                const hasCalculated = localStorage.getItem(storageKey);
                
                if (!hasCalculated) {
                    // First time visit, auto calculate statistics
                    console.log('First time visit, auto calculating statistics...');
                    try {
                        await this.calculateStatistics();
                        // Mark as calculated
                        localStorage.setItem(storageKey, 'true');
                    } catch (error) {
                        console.error('Error in auto calculate statistics:', error);
                        // Don't show error to user, just log it
                    }
                }
            } catch (error) {
                console.error('Error checking auto-calculate config:', error);
                // If config check fails, proceed with default behavior (enabled)
                const storageKey = 'employee_statistics_auto_calculated';
                const hasCalculated = localStorage.getItem(storageKey);
                
                if (!hasCalculated) {
                    console.log('Config check failed, using default behavior (enabled)');
                    try {
                        await this.calculateStatistics();
                        localStorage.setItem(storageKey, 'true');
                    } catch (err) {
                        console.error('Error in auto calculate statistics:', err);
                    }
                }
            }
        },
        
        getPeriodTypeLabel(type) {
            const labels = {
                'month': '月',
                'year': '年'
            };
            return labels[type] || type;
        },
        
        formatNumber(num) {
            if (!num && num !== 0) return '0';
            const numValue = parseFloat(num);
            if (isNaN(numValue)) return '0';
            // Format with no decimal places for currency
            return Math.round(numValue).toLocaleString('ja-JP', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            });
        },
        
        formatCurrency(num) {
            if (!num && num !== 0) return '¥0';
            const numValue = parseFloat(num);
            if (isNaN(numValue)) return '¥0';
            // Format currency with no decimal places
            return '¥' + Math.round(numValue).toLocaleString('ja-JP', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            });
        },
        
        formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('ja-JP', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            });
        },
        
        formatDateTime(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleString('ja-JP', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        },
        
        showSuccess(message) {
            if (typeof showMessage === 'function') {
                showMessage(message);
            } else {
                alert(message);
            }
        },
        
        showError(message) {
            if (typeof showMessage === 'function') {
                showMessage(message, true);
            } else {
                alert(message);
            }
        }
    },
    
    watch: {
        filters: {
            deep: true,
            handler() {
                // Auto reload when filters change (optional)
                // this.loadStatistics();
            }
        }
    }
}).mount('#app');

