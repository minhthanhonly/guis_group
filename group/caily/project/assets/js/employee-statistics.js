const { createApp } = Vue;

// Helper function to get current month in YYYY-MM format
function getCurrentMonth() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    return `${year}-${month}`;
}

createApp({
    data() {
        return {
            teams: [],
            statistics: [],
            summary: [],
            teamStatistics: [],
            revenueTargets: {}, // Map of team_id -> monthly_target for current year/month
            annualSummary: [],
            annualLoading: false,
            loading: false,
            calculating: false,
            deleting: false,
            chartLoading: false,
            activeTab: 'teams', // 'teams' or 'employees'
            selectedTeamId: null,
            monthlyChartData: [],
            chartInstance: null,
            selectedUserId: null,
            selectedUserName: '',
            employeeChartData: [],
            employeeChartInstance: null,
            employeeChartLoading: false,
            sortColumn: null, // Column to sort by
            sortDirection: 'asc', // 'asc' or 'desc'
            selectedYear: new Date().getFullYear(),
            yearOptions: [],
            filters: {
                period_type: 'month',
                team_id: null,
                selected_month: getCurrentMonth() // Default to current month (Format: YYYY-MM)
            }
        }
    },
    
    mounted() {
        this.initYearOptions();
        this.loadTeams();
        // Auto load statistics for last 12 months
        this.loadStatistics();
        this.loadSummary();
        this.loadRevenueTargets();
        this.loadAnnualSummary();
        
        // Auto calculate statistics on first visit
        this.autoCalculateStatistics();
    },
    
    computed: {
        displayedTeamStatistics() {
            let stats = this.teamStatistics;
            
            // Filter by selected month if a month is selected
            if (this.filters.selected_month && this.filters.selected_month !== '') {
                // Calculate team statistics from statistics data filtered by month
                stats = this.calculateTeamStatisticsByMonth();
            }
            
            // Filter by selected team if a team is selected
            if (this.selectedTeamId !== null && this.selectedTeamId !== undefined) {
                stats = stats.filter(stat => {
                    if (this.selectedTeamId === null || this.selectedTeamId === '' || this.selectedTeamId === 'null') {
                        return (stat.team_id === null || stat.team_id === undefined || stat.team_id === '');
                    }
                    return stat.team_id == this.selectedTeamId;
                });
            }
            
            return stats;
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
        }
    },
    
    methods: {
        async loadTeams() {
            try {
                const response = await axios.get('/api/index.php?model=team&method=list');
                this.teams = response.data || [];
                
                // Default to "すべてのチーム" (all teams) - team_id is null
                    await this.loadStatistics();
                    await this.loadSummary();
            } catch (error) {
                console.error('Error loading teams:', error);
                this.showError('チームの読み込みに失敗しました');
            }
        },

        initYearOptions() {
            const currentYear = new Date().getFullYear();
            // Current year, previous, and next year for convenience
            this.yearOptions = [currentYear, currentYear + 1, currentYear - 1].sort((a, b) => b - a);
        },
        
        async onTeamChange() {
            await this.loadStatistics();
            if (this.filters.team_id) {
                this.selectedTeamId = this.filters.team_id;
                await this.loadMonthlyStatistics();
            } else {
                this.selectedTeamId = null;
                if (this.chartInstance) {
                    this.chartInstance.destroy();
                    this.chartInstance = null;
                }
            }
        },
        
        selectTeam(teamId) {
            // Toggle: if clicking the same team, deselect it
            if (this.isTeamSelected(teamId)) {
                this.clearTeamSelection();
                return;
            }
            
            this.selectedTeamId = teamId;
            // Only set filter if teamId is not null
            if (teamId) {
                this.filters.team_id = teamId;
            } else {
                this.filters.team_id = null;
            }
            this.loadMonthlyStatistics();
        },
        
        clearTeamSelection() {
            this.selectedTeamId = null;
            this.filters.team_id = null;
            if (this.chartInstance) {
                this.chartInstance.destroy();
                this.chartInstance = null;
            }
        },
        
        isTeamSelected(teamId) {
            if (this.selectedTeamId === null || this.selectedTeamId === undefined) {
                return (teamId === null || teamId === undefined || teamId === '');
            }
            return this.selectedTeamId == teamId;
        },
        
        getSelectedTeamName() {
            if (this.selectedTeamId === null || this.selectedTeamId === undefined) {
                return 'チーム未所属';
            }
            const team = this.teamStatistics.find(t => {
                if (this.selectedTeamId === null || this.selectedTeamId === '') {
                    return (t.team_id === null || t.team_id === undefined || t.team_id === '');
                }
                return t.team_id == this.selectedTeamId;
            });
            return team ? (team.team_name || 'チーム未所属') : '';
        },
        
        async loadMonthlyStatistics() {
            if (this.selectedTeamId === null || this.selectedTeamId === undefined) {
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
                    method: 'list',
                    period_type: 'month',
                    months: 12
                });
                
                // Handle null team_id (for teams without team_id)
                if (this.selectedTeamId !== null && this.selectedTeamId !== '') {
                    params.append('team_id', this.selectedTeamId);
                } else {
                    // For teams without team_id, we need to filter by team_id IS NULL
                    // This might need API support, but for now we'll try without team_id filter
                }
                
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                let data = response.data || [];
                
                // If selectedTeamId is null/empty string, filter for records with null team_id
                if (this.selectedTeamId === null || this.selectedTeamId === '' || this.selectedTeamId === 'null') {
                    data = data.filter(stat => !stat.team_id || stat.team_id === null);
                }
                
                this.monthlyChartData = data;
                
                // Group data by month and aggregate
                this.renderChart();
            } catch (error) {
                console.error('Error loading monthly statistics:', error);
                this.showError('月別統計データの読み込みに失敗しました');
            } finally {
                this.chartLoading = false;
            }
        },
        
        renderChart() {
            if (!this.monthlyChartData || this.monthlyChartData.length === 0) {
                console.log('No chart data available');
                return;
            }
            
            console.log('Rendering chart with data:', this.monthlyChartData.length, 'records');
            
            // Group data by month
            const monthlyData = {};
            
            this.monthlyChartData.forEach(stat => {
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
            const chartColors = ['#1cc88a',  '#7367f0', '#3b82f6', '#dc2626', '#ff9f43'];
            const borderColor = 'rgba(224,224,224,0.2)';
            const labelColor = '#ccc';
            
            // Destroy existing chart
            if (this.chartInstance) {
                this.chartInstance.destroy();
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
                            if (seriesIndex === 0 || seriesIndex === 1) {
                                return '¥' + Math.round(val).toLocaleString('ja-JP');
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
                            // Check if element is visible (not hidden by v-show)
                            const isVisible = chartElement.offsetParent !== null || 
                                            chartElement.style.display !== 'none';
                            
                            if (isVisible) {
                                try {
                                    if (this.chartInstance) {
                                        this.chartInstance.destroy();
                                    }
                                    console.log('Creating ApexCharts instance...');
                                    this.chartInstance = new ApexChartsClass(chartElement, options);
                                    this.chartInstance.render();
                                    console.log('Chart rendered successfully');
                                } catch (error) {
                                    console.error('Error rendering chart:', error);
                                    this.showError('チャートの表示に失敗しました: ' + error.message);
                                }
                            } else {
                                // Element not visible yet, retry
                                if (retryCount < maxRetries) {
                                    console.warn('Chart element not visible yet, retrying...', retryCount);
                                    setTimeout(renderChart, 100);
                                } else {
                                    console.error('Chart element not visible after', maxRetries, 'retries');
                                }
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
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                const data = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
                this.annualSummary = Array.isArray(data) ? data : [];
            } catch (error) {
                console.error('Error loading annual summary:', error);
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
                this.selectedTeamId = null;
                if (this.chartInstance) {
                    this.chartInstance.destroy();
                    this.chartInstance = null;
                }
            } else if (tab === 'teams') {
                this.selectedUserId = null;
                this.selectedUserName = '';
                if (this.employeeChartInstance) {
                    this.employeeChartInstance.destroy();
                    this.employeeChartInstance = null;
                }
            } else if (tab === 'annual') {
                // Reload annual summary when switching to annual tab
                this.loadAnnualSummary();
            }
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
                            if (seriesIndex === 0 || seriesIndex === 1) {
                                return '¥' + Math.round(val).toLocaleString('ja-JP');
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
                
                if (this.filters.team_id) {
                    params.append('team_id', this.filters.team_id);
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
                
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                
                if (response.data.status === 'success') {
                    this.showSuccess(response.data.message || '統計を計算しました');
                    await this.loadStatistics();
                    await this.loadSummary();
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
                    // Clear selected team and employee charts
                    this.clearTeamSelection();
                    this.clearEmployeeSelection();
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
        
        async autoCalculateStatistics() {
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

