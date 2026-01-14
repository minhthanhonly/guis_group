const { createApp } = Vue;

createApp({
    data() {
        return {
            teams: [],
            targets: [],
            loading: false,
            saving: false,
            selectedYear: new Date().getFullYear(),
            availableYears: []
        };
    },
    
    computed: {
        totalYearlyTarget() {
            return this.teams.reduce((sum, team) => {
                return sum + (parseFloat(team.yearly_target) || 0);
            }, 0);
        },
        totalMonthlyTarget() {
            return this.teams.reduce((sum, team) => {
                return sum + (parseFloat(team.monthly_target) || 0);
            }, 0);
        }
    },
    
    async mounted() {
        // Load years first (doesn't depend on anything)
        await this.loadYears();
        // Load teams, then targets (targets depend on teams)
        await this.loadTeams();
        // Only load targets if teams were loaded successfully
        if (this.teams.length > 0) {
            await this.loadTargets();
        }
    },
    
    methods: {
        async loadYears() {
            try {
                const params = new URLSearchParams({
                    model: 'teamrevenuetarget',
                    method: 'getYears'
                });
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                const data = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
                const years = Array.isArray(data) ? data : [];
                
                // Generate years from current year to 5 years in the future
                const currentYear = new Date().getFullYear();
                const yearSet = new Set();
                
                // Convert all years to numbers and add to set
                years.forEach(year => {
                    yearSet.add(parseInt(year, 10));
                });
                
                // Add current year and next 5 years if not already in the list
                for (let i = 0; i <= 5; i++) {
                    yearSet.add(currentYear + i);
                }
                
                // Also add last 2 years
                for (let i = 1; i <= 2; i++) {
                    yearSet.add(currentYear - i);
                }
                
                // Convert to array, remove any invalid years, and sort descending
                this.availableYears = Array.from(yearSet)
                    .filter(year => !isNaN(year) && year >= 2000 && year <= 2100)
                    .sort((a, b) => b - a);
            } catch (error) {
                console.error('Error loading years:', error);
                // Default to current year if API fails
                const currentYear = new Date().getFullYear();
                this.availableYears = [currentYear, currentYear + 1, currentYear - 1];
            }
        },
        
        async loadTeams() {
            try {
                const params = new URLSearchParams({
                    model: 'teamrevenuetarget',
                    method: 'getTeams'
                });
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                const data = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
                const teams = Array.isArray(data) ? data : [];
                
                // Initialize teams with default values
                this.teams = teams.map(team => ({
                    ...team,
                    yearly_target: 0,
                    monthly_target: 0,
                    target_id: null
                }));
            } catch (error) {
                console.error('Error loading teams:', error);
                this.showError('チームデータの読み込みに失敗しました');
            }
        },
        
        async loadTargets() {
            // Don't load if teams haven't been loaded yet
            if (!this.teams || this.teams.length === 0) {
                console.warn('Teams not loaded yet, skipping loadTargets');
                return;
            }
            
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    model: 'teamrevenuetarget',
                    method: 'list',
                    year: this.selectedYear
                });
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                const data = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
                const targets = Array.isArray(data) ? data : [];
                
                // Create a map of team_id to target
                const targetMap = {};
                targets.forEach(target => {
                    targetMap[target.team_id] = target;
                });
                
                // Reset all teams to default values first, then update with target data
                this.teams = this.teams.map(team => {
                    const target = targetMap[team.id];
                    if (target) {
                        // Found target for this year - use target data
                        return {
                            ...team,
                            yearly_target: parseFloat(target.yearly_target) || 0,
                            monthly_target: parseFloat(target.monthly_target) || 0,
                            target_id: target.id
                        };
                    } else {
                        // No target found for this year - reset to 0
                        return {
                            ...team,
                            yearly_target: 0,
                            monthly_target: 0,
                            target_id: null
                        };
                    }
                });
            } catch (error) {
                console.error('Error loading targets:', error);
                this.showError('目標データの読み込みに失敗しました');
            } finally {
                this.loading = false;
            }
        },
        
        updateMonthlyTarget(team) {
            // Calculate monthly target (yearly / 12)
            const yearly = parseFloat(team.yearly_target) || 0;
            team.monthly_target = yearly / 12;
        },
        
        async saveTarget(team) {
            if (!team.yearly_target || team.yearly_target <= 0) {
                this.showError('年間目標を入力してください');
                return;
            }
            
            this.saving = true;
            try {
                const params = new URLSearchParams({
                    model: 'teamrevenuetarget',
                    method: 'save',
                    team_id: team.id,
                    year: this.selectedYear,
                    yearly_target: team.yearly_target
                });
                
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                const data = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
                
                if (data && data.id && !data.error) {
                    team.target_id = data.id;
                    this.showSuccess('目標を保存しました');
                } else {
                    this.showError(data?.error || '保存に失敗しました');
                }
            } catch (error) {
                console.error('Error saving target:', error);
                const errorMsg = error.response?.data ? 
                    (typeof error.response.data === 'string' ? JSON.parse(error.response.data) : error.response.data) : 
                    null;
                this.showError('保存に失敗しました: ' + (errorMsg?.error || error.message));
            } finally {
                this.saving = false;
            }
        },
        
        async saveAllTargets() {
            // Filter teams that have targets set
            const teamsToSave = this.teams.filter(team => 
                team.yearly_target && team.yearly_target > 0
            );
            
            if (teamsToSave.length === 0) {
                this.showError('保存する目標がありません');
                return;
            }
            
            this.saving = true;
            let successCount = 0;
            let errorCount = 0;
            
            try {
                // Save all targets sequentially
                for (const team of teamsToSave) {
                    try {
                        const params = new URLSearchParams({
                            model: 'teamrevenuetarget',
                            method: 'save',
                            team_id: team.id,
                            year: this.selectedYear,
                            yearly_target: team.yearly_target
                        });
                        
                        const response = await axios.get(`/api/index.php?${params.toString()}`);
                        const data = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
                        
                        if (data && data.id && !data.error) {
                            team.target_id = data.id;
                            successCount++;
                        } else {
                            errorCount++;
                        }
                    } catch (error) {
                        console.error(`Error saving target for team ${team.id}:`, error);
                        errorCount++;
                    }
                }
                
                if (errorCount === 0) {
                    this.showSuccess(`${successCount}件の目標を保存しました`);
                } else {
                    this.showError(`${successCount}件保存成功、${errorCount}件保存失敗`);
                }
            } catch (error) {
                console.error('Error saving targets:', error);
                this.showError('保存に失敗しました');
            } finally {
                this.saving = false;
            }
        },
        
        formatNumber(num) {
            if (!num && num !== 0) return '0';
            return parseFloat(num).toLocaleString('ja-JP', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            });
        },
        
        showSuccess(message) {
            if (typeof showMessage === 'function') {
                showMessage(message, false);
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
    }
}).mount('#app');

