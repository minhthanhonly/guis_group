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
                team_id: null,
                period_start: this.getDefaultStartDate('month'),
                period_end: this.getDefaultEndDate('month')
            }
        }
    },
    
    mounted() {
        this.loadTeams();
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
        
        switchTab(tab) {
            this.activeTab = tab;
        },
        
        async loadStatistics() {
            if (!this.filters.team_id) {
                this.statistics = [];
                return;
            }
            
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    model: 'employeestatistics',
                    method: 'list',
                    period_type: this.filters.period_type,
                    period_start: this.filters.period_start,
                    period_end: this.filters.period_end,
                    team_id: this.filters.team_id
                });
                
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
                    period_start: this.filters.period_start,
                    period_end: this.filters.period_end
                });
                
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                this.teamStatistics = response.data || [];
            } catch (error) {
                console.error('Error loading summary:', error);
            }
        },
        
        async calculateStatistics() {
            if (!this.filters.period_start || !this.filters.period_end) {
                this.showError('開始日と終了日を選択してください');
                return;
            }
            
            if (new Date(this.filters.period_start) > new Date(this.filters.period_end)) {
                this.showError('開始日は終了日より前である必要があります');
                return;
            }
            
            this.calculating = true;
            try {
                // Calculate statistics for all teams
                const params = new URLSearchParams({
                    model: 'employeestatistics',
                    method: 'calculateStatistics',
                    period_type: this.filters.period_type,
                    period_start: this.filters.period_start,
                    period_end: this.filters.period_end
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
        
        getDefaultStartDate(periodType) {
            const now = new Date();
            switch(periodType) {
                case 'week':
                    const dayOfWeek = now.getDay();
                    const diff = now.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1);
                    return new Date(now.setDate(diff)).toISOString().split('T')[0];
                case 'month':
                    return new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
                case 'quarter':
                    const quarter = Math.floor(now.getMonth() / 3);
                    return new Date(now.getFullYear(), quarter * 3, 1).toISOString().split('T')[0];
                case 'year':
                    return new Date(now.getFullYear(), 0, 1).toISOString().split('T')[0];
                default:
                    return new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
            }
        },
        
        getDefaultEndDate(periodType) {
            const now = new Date();
            switch(periodType) {
                case 'week':
                    const dayOfWeek = now.getDay();
                    const diff = now.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1) + 6;
                    return new Date(now.setDate(diff)).toISOString().split('T')[0];
                case 'month':
                    return new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0];
                case 'quarter':
                    const quarter = Math.floor(now.getMonth() / 3);
                    return new Date(now.getFullYear(), (quarter + 1) * 3, 0).toISOString().split('T')[0];
                case 'year':
                    return new Date(now.getFullYear(), 11, 31).toISOString().split('T')[0];
                default:
                    return new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0];
            }
        },
        
        getPeriodTypeLabel(type) {
            const labels = {
                'week': '週',
                'month': '月',
                'quarter': '四半期',
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
        'filters.period_type'(newVal) {
            this.filters.period_start = this.getDefaultStartDate(newVal);
            this.filters.period_end = this.getDefaultEndDate(newVal);
        },
        
        filters: {
            deep: true,
            handler() {
                // Auto reload when filters change (optional)
                // this.loadStatistics();
            }
        }
    }
}).mount('#app');

