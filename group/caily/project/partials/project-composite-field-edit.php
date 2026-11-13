<?php
/**
 * Controls of one composite custom field (設備図 / 意匠図 / 配置図: choice, 受領済み, 【メモ】) —
 * edit state of the project form. Vue fragment: expects `field` and `idx` in scope.
 * Included by project-form-grid.php, in the custom field list and inside 省エネ向け資料.
 */
?>
<div class="d-flex flex-wrap align-items-center gap-3">
    <template v-if="isCompositeCheckboxType(field)">
        <div class="form-check form-check-inline mb-0" v-for="opt in getCompositeOptions(field)" :key="opt">
            <input class="form-check-input" type="checkbox" :value="opt" v-model="field.valueArr">
            <label class="form-check-label">{{ opt }}</label>
        </div>
    </template>
    <template v-else>
        <div class="form-check form-check-inline mb-0" v-for="opt in getCompositeOptions(field)" :key="opt">
            <input class="form-check-input" type="radio" :name="'custom_composite_radio_' + field.label" :value="opt" v-model="field.value">
            <label class="form-check-label">{{ opt }}</label>
        </div>
    </template>
    <div v-if="isCompositeValueSelected(field) && field.supportsReceived" class="form-check form-check-inline mb-0">
        <input class="form-check-input" type="checkbox" v-model="field.receivedChecked">
        <label class="form-check-label text-nowrap">受領済み</label>
    </div>
</div>
<div v-if="isCompositeValueSelected(field)" class="d-flex align-items-center gap-2 mt-2" style="min-width: 8rem;">
    <span class="text-nowrap small text-muted">【メモ】</span>
    <input type="text" class="form-control form-control-sm" v-model="field.memoValue" placeholder="メモ" style="min-width:6rem;">
</div>
