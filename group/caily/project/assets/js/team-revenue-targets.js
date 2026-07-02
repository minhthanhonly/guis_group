const { createApp } = Vue;
const STORAGE_KEY_DEPT = 'team_revenue_targets_selected_department';

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
            departments: [],
            selectedDepartment: null,
            loadingDepartments: false,
            teams: [],
            targets: [],
            loading: false,
            saving: false,
            departmentTargetSaving: false,
            teamSaveTimers: {},
            selectedYear: getCurrentFiscalYear(),
            availableYears: [],
            departmentYearlyTarget: 0
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
        },
        yearlyTotalCompareStatus() {
            const total = parseFloat(this.totalYearlyTarget) || 0;
            const target = parseFloat(this.departmentYearlyTarget) || 0;
            if (total > target) return 'over';
            if (total < target) return 'under';
            return 'equal';
        },
        yearlyTotalCompareMessage() {
            const total = parseFloat(this.totalYearlyTarget) || 0;
            const target = parseFloat(this.departmentYearlyTarget) || 0;
            const diff = Math.abs(total - target);
            if (this.yearlyTotalCompareStatus === 'over') {
                return `部署目標より ¥${this.formatNumber(diff)} 高いです`;
            }
            if (this.yearlyTotalCompareStatus === 'under') {
                return `部署目標より ¥${this.formatNumber(diff)} 低いです`;
            }
            return '';
        },
        departmentMonthlyTarget() {
            const yearly = parseFloat(this.departmentYearlyTarget) || 0;
            return yearly / 12;
        }
    },
    
    async mounted() {
        // Load years first (doesn't depend on anything)
        await this.loadYears();
        await this.loadDepartments();
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
            if (!this.selectedDepartment) {
                this.teams = [];
                return;
            }
            try {
                const params = new URLSearchParams({
                    model: 'teamrevenuetarget',
                    method: 'getTeams',
                    department_id: this.selectedDepartment.id
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
                    year: this.selectedYear,
                    department_id: this.selectedDepartment ? this.selectedDepartment.id : ''
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

        async loadDepartmentTarget() {
            if (!this.selectedDepartment) {
                this.departmentYearlyTarget = 0;
                return;
            }
            try {
                const params = new URLSearchParams({
                    model: 'teamrevenuetarget',
                    method: 'getDepartmentTarget',
                    department_id: this.selectedDepartment.id,
                    year: this.selectedYear
                });
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                const data = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
                this.departmentYearlyTarget = data && data.yearly_target != null
                    ? (parseFloat(data.yearly_target) || 0)
                    : 0;
            } catch (error) {
                console.error('Error loading department target:', error);
                this.departmentYearlyTarget = 0;
            }
        },
        
        updateMonthlyTarget(team) {
            // Calculate monthly target (yearly / 12)
            const yearly = parseFloat(team.yearly_target) || 0;
            team.monthly_target = yearly / 12;
        },

        onTeamTargetInput(team) {
            this.updateMonthlyTarget(team);
            const key = String(team.id);
            if (this.teamSaveTimers[key]) {
                clearTimeout(this.teamSaveTimers[key]);
            }
            this.teamSaveTimers[key] = setTimeout(() => {
                this.saveTarget(team, true);
            }, 600);
        },
        
        async saveTarget(team, silent = false) {
            const yearly = parseFloat(team.yearly_target);
            team.yearly_target = Number.isFinite(yearly) && yearly >= 0 ? yearly : 0;
            this.updateMonthlyTarget(team);
            
            this.saving = true;
            try {
                const params = new URLSearchParams({
                    model: 'teamrevenuetarget',
                    method: 'save',
                    team_id: team.id,
                    year: this.selectedYear,
                    yearly_target: team.yearly_target || 0
                });
                
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                const data = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
                
                if (data && data.id && !data.error) {
                    team.target_id = data.id;
                    if (!silent) this.showSuccess('目標を保存しました');
                } else {
                    if (!silent) this.showError(data?.error || '保存に失敗しました');
                }
            } catch (error) {
                console.error('Error saving target:', error);
                const errorMsg = error.response?.data ? 
                    (typeof error.response.data === 'string' ? JSON.parse(error.response.data) : error.response.data) : 
                    null;
                if (!silent) this.showError('保存に失敗しました: ' + (errorMsg?.error || error.message));
            } finally {
                this.saving = false;
            }
        },
        
        async saveDepartmentTarget() {
            if (!this.selectedDepartment) {
                this.showError('部署を選択してください');
                return;
            }
            this.departmentTargetSaving = true;
            try {
                const params = new URLSearchParams({
                    model: 'teamrevenuetarget',
                    method: 'saveDepartmentTarget',
                    department_id: this.selectedDepartment.id,
                    year: this.selectedYear,
                    yearly_target: this.departmentYearlyTarget || 0
                });
                const response = await axios.get(`/api/index.php?${params.toString()}`);
                const data = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;
                if (data && data.id && !data.error) {
                    this.showSuccess('部署目標を保存しました');
                } else {
                    this.showError(data?.error || '部署目標の保存に失敗しました');
                }
            } catch (error) {
                console.error('Error saving department target:', error);
                this.showError('部署目標の保存に失敗しました');
            } finally {
                this.departmentTargetSaving = false;
            }
        },

        async onDepartmentTargetBlur() {
            const n = parseFloat(this.departmentYearlyTarget);
            this.departmentYearlyTarget = Number.isFinite(n) && n > 0 ? n : 0;
            await this.saveDepartmentTarget();
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

        async loadDepartments() {
            this.loadingDepartments = true;
            try {
                const response = await axios.get('/api/index.php?model=department&method=listByUser');
                const all = Array.isArray(response.data) ? response.data : [];
                this.departments = all.filter(function(d) { return d && d.can_project != 0; });
                if (!this.departments.length) {
                    this.selectedDepartment = null;
                    this.teams = [];
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
            } catch (error) {
                console.error('Error loading departments:', error);
                this.departments = [];
                this.selectedDepartment = null;
                this.teams = [];
                this.showError('部署データの読み込みに失敗しました');
            } finally {
                this.loadingDepartments = false;
            }
        },

        async selectDepartment(department, persist = true) {
            if (persist !== false) {
                this.saveDepartmentToStorage(department);
            }
            this.selectedDepartment = department;
            await this.loadTeams();
            await this.loadTargets();
            await this.loadDepartmentTarget();
        },

        async onYearChange() {
            await this.loadTargets();
            await this.loadDepartmentTarget();
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

