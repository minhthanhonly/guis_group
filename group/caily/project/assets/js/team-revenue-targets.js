const { createApp } = Vue;

function getCurrentFiscalYear() {
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth() + 1; // 1-12
    // Fiscal year ends in June; if month >= 7, fiscal end year is next calendar year
    return month >= 7 ? year + 1 : year;
}

createApp({
    data() {
        return {
            teams: [],
            targets: [],
            loading: false,
            saving: false,
            selectedYear: getCurrentFiscalYear(),
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
                
                // Generate fiscal years (value = fiscal end year; FY Y: Jul (Y-1) - Jun (Y))
                const currentFY = getCurrentFiscalYear();
                const yearSet = new Set();
                
                years.forEach(year => {
                    const y = parseInt(year, 10);
                    if (!isNaN(y)) yearSet.add(y);
                });
                
                // Add current FY and +/- 2 years
                for (let i = -2; i <= 2; i++) {
                    yearSet.add(currentFY + i);
                }
                
                const sorted = Array.from(yearSet)
                    .filter(y => !isNaN(y) && y >= 2000 && y <= 2100)
                    .sort((a, b) => b - a);
                
                // Map to {value, label}
                this.availableYears = sorted.map(y => ({
                    value: y,
                    label: `FY${y} (${y-1}年7月〜${y}年6月)`
                }));
                
                // Ensure selectedYear is in options
                if (!this.availableYears.some(opt => opt.value === this.selectedYear)) {
                    this.selectedYear = currentFY;
                }
            } catch (error) {
                console.error('Error loading years:', error);
                const fy = getCurrentFiscalYear();
                this.availableYears = [
                    { value: fy, label: `FY${fy} (${fy-1}年7月〜${fy}年6月)` },
                    { value: fy + 1, label: `FY${fy+1} (${fy}年7月〜${fy+1}年6月)` },
                    { value: fy - 1, label: `FY${fy-1} (${fy-2}年7月〜${fy-1}年6月)` },
                ];
                this.selectedYear = fy;
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

