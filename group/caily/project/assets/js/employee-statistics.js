const { createApp } = Vue;

createApp({
    data() {
        return {
            teams: [],
            statistics: [],
            summary: [],
            teamStatistics: [],
            loading: false,
            calculating: false,
            activeTab: 'teams', // 'teams' or 'employees'
            filters: {
                period_type: 'month',
                team_id: null
            }
        }
    },
    
    mounted() {
        this.loadTeams();
        // Auto load statistics for last 12 months
        this.loadStatistics();
        this.loadSummary();
    },
    
    methods: {
        async loadTeams() {
            try {
                const response = await axios.get('/api/index.php?model=team&method=list');
                this.teams = response.data || [];
                
                // Set default team to first team if not set
                if (this.teams.length > 0) {
                    if (!this.filters.team_id) {
                        this.filters.team_id = this.teams[0].id;
                    }
                    await this.loadStatistics();
                    await this.loadSummary();
                } else {
                    await this.loadSummary();
                }
            } catch (error) {
                console.error('Error loading teams:', error);
                this.showError('チームの読み込みに失敗しました');
            }
        },
        
        async onTeamChange() {
            await this.loadStatistics();
        },
        
        async onPeriodTypeChange() {
            await this.loadStatistics();
            await this.loadSummary();
        },
        
        switchTab(tab) {
            this.activeTab = tab;
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
        
        getPeriodTypeLabel(type) {
            const labels = {
                'month': '月',
                'year': '年'
            };
            return labels[type] || type;
        },
        
        formatNumber(num) {
            if (!num) return '0';
            return parseFloat(num).toLocaleString('ja-JP');
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

