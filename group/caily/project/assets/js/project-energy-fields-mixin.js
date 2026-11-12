/**
 * Vue 3 mixin for the project detail page: edit mode of the 省エネ fields of a child project
 *   省エネ向け資料 (+ その他メモ) / 申請提出先の審査機関 / BELSのZEH等級
 * The fields exist only for projects of a 省エネ department (same rule as the 案件依頼 modals on
 * the building page, see parent_project/assets/js/parent-project-child-energy-mixin.js, whose
 * option lists these mirror).
 *
 * The host component must provide:
 *   - data: project (the loaded project row), isEditMode
 *   - computed: isEnergyDepartmentProject
 * and call appendEnergyFieldsToFormData(formData) when it saves the project.
 *
 * Edits live in energyEdit and are only written to the server on save, so cancelling the edit
 * needs no restore.
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
    var AGENCY_INPUT_ID = 'project_energy_review_agency_tags';

    function splitList(value) {
        return String(value == null ? '' : value).split(',').map(function (v) { return v.trim(); }).filter(Boolean);
    }

    function emptyFlags() {
        var flags = {};
        MATERIAL_OPTIONS.forEach(function (opt) { flags[opt] = false; });
        return flags;
    }

    window.ProjectDetailEnergyMixin = {
        data() {
            return {
                energyEdit: { flags: emptyFlags(), memo: '', agency: '', grade: '' },
                energyEditMaterialOptions: MATERIAL_OPTIONS.slice(),
                energyEditGradeOptions: BELS_ZEH_GRADE_OPTIONS.slice(),
                energyEditAgencyWhitelist: (window.ParentProjectReviewAgency
                    && Array.isArray(window.ParentProjectReviewAgency.DEFAULT_WHITELIST))
                    ? window.ParentProjectReviewAgency.DEFAULT_WHITELIST.slice()
                    : []
            };
        },
        created() {
            // Tagify instance is kept off the reactive state on purpose.
            this._energyAgencyTagify = null;
        },
        watch: {
            isEditMode(on) {
                if (on) {
                    this.loadEnergyEditFromProject();
                    if (this.isEnergyDepartmentProject) {
                        this.$nextTick(() => this.initEnergyAgencyTagify());
                    }
                } else {
                    this.destroyEnergyAgencyTagify();
                }
            }
        },
        methods: {
            /** Fill the edit state from the loaded project (NULL / missing fields become empty). */
            loadEnergyEditFromProject() {
                var src = this.project || {};
                var flags = emptyFlags();
                splitList(src.energy_materials).forEach(function (item) {
                    if (Object.prototype.hasOwnProperty.call(flags, item)) flags[item] = true;
                });
                this.energyEdit = {
                    flags: flags,
                    memo: src.energy_materials_memo || '',
                    agency: src.application_review_agency || '',
                    grade: (src.bels_zeh_grade != null && src.bels_zeh_grade !== '') ? String(src.bels_zeh_grade) : ''
                };
            },
            buildEnergyMaterialsString() {
                var flags = this.energyEdit.flags;
                return MATERIAL_OPTIONS.filter(function (opt) { return !!flags[opt]; }).join(',');
            },
            /** Adds the four fields for a 省エネ project; sends nothing for other departments. */
            appendEnergyFieldsToFormData(formData) {
                if (!this.isEnergyDepartmentProject) return;
                var state = this.energyEdit;
                if (this._energyAgencyTagify) {
                    state.agency = this._energyAgencyTagify.value.map(function (t) { return t.value; }).join(',');
                }
                formData.append('energy_materials', this.buildEnergyMaterialsString());
                formData.append('energy_materials_memo', state.flags[OTHER_LABEL] ? (state.memo || '') : '');
                formData.append('application_review_agency', state.agency || '');
                formData.append('bels_zeh_grade', state.grade || '');
            },
            clearEnergyAgencyTags() {
                this.energyEdit.agency = '';
                if (this._energyAgencyTagify) this._energyAgencyTagify.removeAllTags();
            },
            destroyEnergyAgencyTagify() {
                if (this._energyAgencyTagify) {
                    try { this._energyAgencyTagify.destroy(); } catch (e) { /* ignore */ }
                    this._energyAgencyTagify = null;
                }
            },
            async initEnergyAgencyTagify() {
                if (!window.Tagify) return;
                if (window.ParentProjectReviewAgency && typeof window.ParentProjectReviewAgency.loadMergedWhitelist === 'function') {
                    try {
                        this.energyEditAgencyWhitelist = await window.ParentProjectReviewAgency.loadMergedWhitelist();
                    } catch (e) { /* keep defaults */ }
                }
                // Edit mode may have been left while the whitelist was loading.
                if (!this.isEditMode || !this.isEnergyDepartmentProject) return;
                var input = document.getElementById(AGENCY_INPUT_ID);
                if (!input) return;
                this.destroyEnergyAgencyTagify();
                input.value = '';
                var tagify = new Tagify(input, {
                    whitelist: this.energyEditAgencyWhitelist.slice(),
                    enforceWhitelist: false,
                    maxTags: 1,
                    dropdown: {
                        maxItems: 40,
                        classname: 'tags-look-review-agency',
                        enabled: 0,
                        closeOnSelect: true
                    }
                });
                this._energyAgencyTagify = tagify;
                var current = String(this.energyEdit.agency || '').trim();
                if (current) tagify.addTags(current);
                var updateAgency = () => {
                    this.energyEdit.agency = tagify.value.map(function (t) { return t.value; }).join(',');
                };
                tagify.on('add', (e) => {
                    updateAgency();
                    var value = e && e.detail && e.detail.data ? e.detail.data.value : '';
                    if (value && window.ParentProjectReviewAgency) {
                        window.ParentProjectReviewAgency.saveLocalCustom(value);
                        this.energyEditAgencyWhitelist = window.ParentProjectReviewAgency.buildWhitelist(
                            this.energyEditAgencyWhitelist
                        );
                        tagify.settings.whitelist = this.energyEditAgencyWhitelist.slice();
                        tagify.whitelist = this.energyEditAgencyWhitelist.slice();
                    }
                });
                tagify.on('remove', updateAgency);
            }
        }
    };
})();
