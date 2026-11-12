<?php
/**
 * 省エネ fields of a child project (案件依頼): 省エネ向け資料 / 申請提出先の審査機関 / BELSのZEH等級.
 * Shown only while the selected 部署 is a 省エネ department.
 * Included inside the <div class="row g-3"> of the create / edit child project modals in detail.php.
 *
 * Requires: $childEnergyKey = 'create' | 'edit' (state lives in childEnergy[key], see
 * assets/js/parent-project-child-energy-mixin.js).
 */
$childEnergyKey = (isset($childEnergyKey) && $childEnergyKey === 'edit') ? 'edit' : 'create';
$childEnergyState = "childEnergy." . $childEnergyKey;
?>
<template v-if="isChildProjectEnergyDept('<?= $childEnergyKey ?>')">
    <div class="col-12">
        <div class="mb-3 form-control-validation">
            <label class="form-label"><span data-i18n="省エネ向け資料">省エネ向け資料</span></label>
            <div class="row">
                <div class="col-md-4" v-for="opt in childEnergyMaterialOptions" :key="'<?= $childEnergyKey ?>_child_em_' + opt">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                            :id="'<?= $childEnergyKey ?>_child_energy_materials_' + opt"
                            v-model="<?= $childEnergyState ?>.flags[opt]">
                        <label class="form-check-label" :for="'<?= $childEnergyKey ?>_child_energy_materials_' + opt">{{ opt }}</label>
                    </div>
                </div>
            </div>
            <div class="mt-2" v-if="<?= $childEnergyState ?>.flags['その他']">
                <input type="text" class="form-control" v-model="<?= $childEnergyState ?>.memo"
                       placeholder="その他メモ" maxlength="500">
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="mb-3 form-control-validation">
            <label class="form-label"><span data-i18n="申請提出先の審査機関">申請提出先の審査機関</span></label>
            <div class="d-flex align-items-center gap-2">
                <input type="text" class="form-control tagify"
                       id="<?= $childEnergyKey ?>_child_application_review_agency_tags"
                       name="<?= $childEnergyKey ?>_child_application_review_agency_tags">
                <button class="btn btn-outline-secondary btn-sm" type="button"
                        @click="clearChildEnergyAgencyTags('<?= $childEnergyKey ?>')" title="すべて削除">
                    <i class="fa fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="mb-3 form-control-validation">
            <label class="form-label"><span data-i18n="BELSのZEH等級">BELSのZEH等級</span></label>
            <select class="form-select" v-model="<?= $childEnergyState ?>.grade">
                <option v-for="opt in childBelsZehGradeOptions" :key="'<?= $childEnergyKey ?>_child_zeh_' + opt.value" :value="opt.value">
                    {{ opt.label }}
                </option>
            </select>
        </div>
    </div>
</template>
