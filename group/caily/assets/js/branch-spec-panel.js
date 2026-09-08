/**
 * Shared helpers for 支店・地域仕様 panel (parent_project + project detail).
 */
window.BranchSpecPanel = {
    emptyState: function () {
        return {
            branchSpecLoading: false,
            branchSpecMatched: [],
            branchSpecManual: [],
            branchSpecByDept: [],
            branchSpecActiveDept: '',
            branchSpecScope: '',
            branchSpecUserDeptKeys: [],
            branchSpecDeptLabels: { isho: '意匠設計', setsubi: '設備設計' }
        };
    },

    deptKeyFromName: function (name) {
        var n = String(name || '');
        if (n.indexOf('設備') !== -1) return 'setsubi';
        if (n.indexOf('意匠') !== -1) return 'isho';
        return '';
    },

    computed: {
        branchSpecDeptTabs: function () {
            var labels = this.branchSpecDeptLabels || {};
            var keys = Object.keys(labels).length ? Object.keys(labels) : ['isho', 'setsubi'];
            var counts = {};
            keys.forEach(function (k) { counts[k] = 0; });
            var bump = function (r) {
                if (r.department_key === 'isho') {
                    counts.isho = (counts.isho || 0) + 1;
                } else if (r.department_key === 'setsubi') {
                    counts.setsubi = (counts.setsubi || 0) + 1;
                    if (r.apply_architectural == 1 || r.apply_architectural === '1') {
                        counts.isho = (counts.isho || 0) + 1;
                    }
                } else {
                    counts[r.department_key] = (counts[r.department_key] || 0) + 1;
                }
            };
            (this.branchSpecMatched || []).forEach(bump);
            (this.branchSpecManual || []).forEach(bump);
            return keys.map(function (k) {
                return { key: k, label: labels[k] || k, count: counts[k] || 0 };
            });
        },
        filteredBranchSpecs: function () {
            var dept = this.branchSpecActiveDept;
            var scope = this.branchSpecScope;
            return (this.branchSpecMatched || []).filter(function (r) {
                if (dept === 'isho') {
                    var isIsho = r.department_key === 'isho';
                    var crossArch = r.department_key === 'setsubi' && (r.apply_architectural == 1 || r.apply_architectural === '1');
                    if (!isIsho && !crossArch) return false;
                } else if (dept && r.department_key !== dept) {
                    return false;
                }
                if (dept === 'setsubi' && scope === 'electrical' && !(r.apply_electrical == 1 || r.apply_electrical === '1')) return false;
                if (dept === 'setsubi' && scope === 'sanitary' && !(r.apply_sanitary == 1 || r.apply_sanitary === '1')) return false;
                return true;
            });
        },
        filteredBranchSpecManual: function () {
            var dept = this.branchSpecActiveDept;
            var scope = this.branchSpecScope;
            return (this.branchSpecManual || []).filter(function (r) {
                if (dept === 'isho') {
                    var isIsho = r.department_key === 'isho';
                    var crossArch = r.department_key === 'setsubi' && (r.apply_architectural == 1 || r.apply_architectural === '1');
                    if (!isIsho && !crossArch) return false;
                } else if (dept && r.department_key !== dept) {
                    return false;
                }
                if (dept === 'setsubi' && scope === 'electrical' && !(r.apply_electrical == 1 || r.apply_electrical === '1')) return false;
                if (dept === 'setsubi' && scope === 'sanitary' && !(r.apply_sanitary == 1 || r.apply_sanitary === '1')) return false;
                return true;
            });
        }
    },

    /**
     * @param {object} vm Vue instance with BranchSpecPanel state fields
     * @param {object} opts { parentProjectId, context, preferDeptKey }
     */
    load: async function (vm, opts) {
        opts = opts || {};
        var parentId = opts.parentProjectId || 0;
        if (!parentId) {
            vm.branchSpecMatched = [];
            vm.branchSpecManual = [];
            return;
        }
        vm.branchSpecLoading = true;
        try {
            var params = new URLSearchParams({
                model: 'branchspec',
                method: 'matchForParent',
                parent_project_id: String(parentId)
            });
            var ctx = opts.context || {};
            ['company_name', 'branch_name', 'construction_branch', 'construction_city', 'structure_type', 'spec_features', 'scale', 'type1', 'type2'].forEach(function (f) {
                if (ctx[f] != null && ctx[f] !== '') params.set(f, ctx[f]);
            });
            // Always send structure/features for preview (including empty clear)
            if (Object.prototype.hasOwnProperty.call(ctx, 'structure_type')) {
                params.set('structure_type', ctx.structure_type || '');
            }
            if (Object.prototype.hasOwnProperty.call(ctx, 'spec_features')) {
                params.set('spec_features', ctx.spec_features || '');
            }
            var response = await axios.get('/api/index.php?' + params.toString());
            var data = response.data || {};
            vm.branchSpecMatched = data.data || [];
            vm.branchSpecManual = data.manual || [];
            vm.branchSpecByDept = data.by_department || [];
            vm.branchSpecDeptLabels = data.department_labels || { isho: '意匠設計', setsubi: '設備設計' };
            vm.branchSpecUserDeptKeys = data.user_department_keys || [];

            if (!vm.branchSpecActiveDept) {
                var prefer = opts.preferDeptKey || '';
                if (prefer && (vm.branchSpecDeptLabels[prefer] || prefer === 'isho' || prefer === 'setsubi')) {
                    vm.branchSpecActiveDept = prefer;
                } else if (vm.branchSpecUserDeptKeys.length) {
                    vm.branchSpecActiveDept = vm.branchSpecUserDeptKeys[0];
                } else {
                    vm.branchSpecActiveDept = 'isho';
                }
            }
        } catch (error) {
            console.error('Error loading branch specs:', error);
            vm.branchSpecMatched = [];
            vm.branchSpecManual = [];
        } finally {
            vm.branchSpecLoading = false;
        }
    }
};
