/**
 * Vue 3 mixin: 省エネ fields of a child project (案件依頼) inside the building detail page.
 *   省エネ向け資料 (+ その他メモ) / 申請提出先の審査機関 / BELSのZEH等級
 * These fields belong to the child project and are only shown / saved while the selected
 * 部署 is a 省エネ department. Used with partials/child-project-energy-fields.php.
 *
 * The host component must provide:
 *   - data: newChildProject, editingChildProject (department_id; edit also department_name)
 *   - methods: getDepartmentNameById(id), isEnergyDepartmentName(name)
 *
 * State lives in childEnergy.create / childEnergy.edit ("key" below is 'create' or 'edit').
 */
(function () {
    'use strict';

    var MATERIAL_OPTIONS = ['意匠図', '設備図', '配置図', '案内図', '敷地求積図', '確認申請書', '設備仕様書', 'その他'];
    var OTHER_LABEL = 'その他';
    var BELS_ZEH_GRADE_OPTIONS = [
        { value: '', label: '' },
        { value: '0', label: '0 : 該当なし（非ZEH）' },
        { value: '1', label: '1 : ZEH' },
        { value: '2', label: '2 : Nearly ZEH' },
        { value: '3', label: '3 : ZEH Oriented' },
        { value: '4', label: '4 : ZEH-M' },
        { value: '5', label: '5 : Nearly ZEH-M' },
        { value: '6', label: '6 : ZEH-M Oriented' },
        { value: '7', label: '7 : ZEH Ready' },
        { value: '8', label: '8 : DK-ZEH' },
        { value: '9', label: '9 : 関西限定DK-ZEH' }
    ];

    function splitList(value) {
        return String(value == null ? '' : value).split(',').map(function (v) { return v.trim(); }).filter(Boolean);
    }

    function emptyFlags() {
        var flags = {};
        MATERIAL_OPTIONS.forEach(function (opt) { flags[opt] = false; });
        return flags;
    }

    function emptyState() {
        return { flags: emptyFlags(), memo: '', agency: '', grade: '' };
    }

    function agencyInputId(key) {
        return key + '_child_application_review_agency_tags';
    }

    window.ParentProjectChildEnergyMixin = {
        data() {
            return {
                childEnergy: { create: emptyState(), edit: emptyState() },
                childEnergyMaterialOptions: MATERIAL_OPTIONS.slice(),
                childBelsZehGradeOptions: BELS_ZEH_GRADE_OPTIONS.slice(),
                childEnergyAgencyWhitelist: (window.ParentProjectReviewAgency
                    && Array.isArray(window.ParentProjectReviewAgency.DEFAULT_WHITELIST))
                    ? window.ParentProjectReviewAgency.DEFAULT_WHITELIST.slice()
                    : []
            };
        },
        created() {
            // Tagify instances are kept off the reactive state on purpose.
            this._childEnergyAgencyTagify = { create: null, edit: null };
        },
        computed: {
            isCreateChildEnergyDept() {
                var p = this.newChildProject || {};
                return this.isEnergyDepartmentName(this.getDepartmentNameById(p.department_id));
            },
            isEditChildEnergyDept() {
                var p = this.editingChildProject || {};
                var name = this.getDepartmentNameById(p.department_id) || p.department_name || '';
                return this.isEnergyDepartmentName(name);
            }
        },
        watch: {
            isCreateChildEnergyDept(isEnergy) {
                this.onChildEnergyDeptToggle('create', isEnergy);
            },
            isEditChildEnergyDept(isEnergy) {
                this.onChildEnergyDeptToggle('edit', isEnergy);
            }
        },
        methods: {
            isChildProjectEnergyDept(key) {
                return key === 'edit' ? this.isEditChildEnergyDept : this.isCreateChildEnergyDept;
            },
            onChildEnergyDeptToggle(key, isEnergy) {
                if (isEnergy) {
                    this.$nextTick(() => {
                        this.initChildEnergyAgencyTagify(key);
                        // The block is rendered after the modal's own i18n pass: translate its labels now.
                        if (typeof window.applyDataI18n === 'function') {
                            var modalEl = document.getElementById(
                                key === 'edit' ? 'editChildProjectModal' : 'createChildProjectModal'
                            );
                            if (modalEl) window.applyDataI18n(modalEl);
                        }
                    });
                } else {
                    this.destroyChildEnergyAgencyTagify(key);
                }
            },
            resetChildEnergyFields(key) {
                this.childEnergy[key] = emptyState();
                this.syncChildEnergyAgencyTags(key);
            },
            /** Fill the form state from a child project row (NULL / missing fields become empty). */
            loadChildEnergyFields(key, source) {
                var src = source || {};
                var flags = emptyFlags();
                splitList(src.energy_materials).forEach(function (item) {
                    if (Object.prototype.hasOwnProperty.call(flags, item)) flags[item] = true;
                });
                this.childEnergy[key] = {
                    flags: flags,
                    memo: src.energy_materials_memo || '',
                    agency: src.application_review_agency || '',
                    grade: (src.bels_zeh_grade != null && src.bels_zeh_grade !== '') ? String(src.bels_zeh_grade) : ''
                };
                this.syncChildEnergyAgencyTags(key);
            },
            buildChildEnergyMaterialsString(key) {
                var flags = this.childEnergy[key].flags;
                return MATERIAL_OPTIONS.filter(function (opt) { return !!flags[opt]; }).join(',');
            },
            /** Always sends the four fields; empty when the 部署 is not 省エネ. */
            appendChildEnergyFieldsToFormData(formData, key) {
                var isEnergy = this.isChildProjectEnergyDept(key);
                var state = this.childEnergy[key];
                var tagify = this._childEnergyAgencyTagify[key];
                if (isEnergy && tagify) {
                    state.agency = tagify.value.map(function (t) { return t.value; }).join(',');
                }
                formData.append('energy_materials', isEnergy ? this.buildChildEnergyMaterialsString(key) : '');
                formData.append('energy_materials_memo', (isEnergy && state.flags[OTHER_LABEL]) ? (state.memo || '') : '');
                formData.append('application_review_agency', isEnergy ? (state.agency || '') : '');
                formData.append('bels_zeh_grade', isEnergy ? (state.grade || '') : '');
            },
            clearChildEnergyAgencyTags(key) {
                this.childEnergy[key].agency = '';
                this.syncChildEnergyAgencyTags(key);
            },
            /** Push state.agency into an already-built Tagify (no-op when it is not built yet). */
            syncChildEnergyAgencyTags(key) {
                var tagify = this._childEnergyAgencyTagify ? this._childEnergyAgencyTagify[key] : null;
                if (!tagify) return;
                var current = String(this.childEnergy[key].agency || '').trim();
                this._childEnergyAgencySyncing = true;
                try {
                    tagify.removeAllTags();
                    if (current) tagify.addTags(current);
                } finally {
                    this._childEnergyAgencySyncing = false;
                }
                this.childEnergy[key].agency = current;
            },
            destroyChildEnergyAgencyTagify(key) {
                var tagify = this._childEnergyAgencyTagify[key];
                if (tagify) {
                    try { tagify.destroy(); } catch (e) { /* ignore */ }
                    this._childEnergyAgencyTagify[key] = null;
                }
                var input = document.getElementById(agencyInputId(key));
                if (input) {
                    input.value = '';
                    if (input.tagify) {
                        try { input.tagify.destroy(); } catch (e) { /* ignore */ }
                    }
                }
            },
            async initChildEnergyAgencyTagify(key) {
                if (!window.Tagify) return;
                if (window.ParentProjectReviewAgency && typeof window.ParentProjectReviewAgency.loadMergedWhitelist === 'function') {
                    try {
                        this.childEnergyAgencyWhitelist = await window.ParentProjectReviewAgency.loadMergedWhitelist();
                    } catch (e) { /* keep defaults */ }
                }
                // The 部署 may have changed while the whitelist was loading.
                if (!this.isChildProjectEnergyDept(key)) return;
                var input = document.getElementById(agencyInputId(key));
                if (!input) return;
                this.destroyChildEnergyAgencyTagify(key);
                input.value = '';
                var tagify = new Tagify(input, {
                    whitelist: this.childEnergyAgencyWhitelist.slice(),
                    enforceWhitelist: false,
                    maxTags: 1,
                    dropdown: {
                        maxItems: 40,
                        classname: 'tags-look-review-agency',
                        enabled: 0,
                        closeOnSelect: true
                    }
                });
                this._childEnergyAgencyTagify[key] = tagify;
                var updateAgency = () => {
                    if (this._childEnergyAgencySyncing) return;
                    this.childEnergy[key].agency = tagify.value.map(function (t) { return t.value; }).join(',');
                };
                tagify.on('add', (e) => {
                    updateAgency();
                    if (this._childEnergyAgencySyncing) return;
                    var value = e && e.detail && e.detail.data ? e.detail.data.value : '';
                    if (value && window.ParentProjectReviewAgency) {
                        window.ParentProjectReviewAgency.saveLocalCustom(value);
                        this.childEnergyAgencyWhitelist = window.ParentProjectReviewAgency.buildWhitelist(
                            this.childEnergyAgencyWhitelist
                        );
                        tagify.settings.whitelist = this.childEnergyAgencyWhitelist.slice();
                        tagify.whitelist = this.childEnergyAgencyWhitelist.slice();
                    }
                });
                tagify.on('remove', updateAgency);
                this.syncChildEnergyAgencyTags(key);
            }
        }
    };
})();
