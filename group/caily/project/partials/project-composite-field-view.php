<?php
/**
 * Controls of one composite custom field (設備図 / 意匠図 / 配置図: choice, 受領済み, 【メモ】) —
 * read-only page (inline edit for users who may edit). Vue fragment: expects `field` and `idx`.
 * Included by project-form-grid.php, in the custom field list and inside 省エネ向け資料.
 */
?>
<template v-if="canEditProject">
    <div class="d-flex flex-wrap align-items-center gap-3">
        <template v-if="isCompositeCheckboxType(field)">
            <div class="form-check form-check-inline mb-0" v-for="opt in getCompositeOptions(field)" :key="opt">
                <input class="form-check-input" type="checkbox" :value="opt" :checked="(field.valueArr || []).includes(opt)" @change="onCompositeCheckboxChange(field, opt, $event.target.checked)">
                <label class="form-check-label">{{ opt }}</label>
            </div>
        </template>
        <template v-else>
            <div class="form-check form-check-inline mb-0" v-for="opt in getCompositeOptions(field)" :key="opt">
                <input class="form-check-input" type="radio" :name="'cf_composite_'+field.label+'_'+idx" :value="opt" :checked="field.value === opt" @change="onCompositeRadioChange(field, opt)">
                <label class="form-check-label">{{ opt }}</label>
            </div>
        </template>
        <div v-if="isCompositeValueSelected(field) && field.supportsReceived" class="form-check form-check-inline mb-0">
            <input class="form-check-input" type="checkbox" :checked="field.receivedChecked" @change="onCompositeReceivedChange(field, $event.target.checked)">
            <label class="form-check-label text-nowrap">受領済み</label>
        </div>
    </div>
    <div v-if="isCompositeValueSelected(field)" class="d-flex align-items-center gap-2 mt-2" style="min-width: 8rem;">
        <span class="text-nowrap small text-muted">【メモ】</span>
        <input type="text" class="form-control form-control-sm" :value="field.memoValue" placeholder="メモ" style="min-width:6rem;" @change="onCompositeMemoChange(field, $event.target.value)">
    </div>
</template>
<template v-else>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span v-if="field.value" v-for="val in String(field.value).split(',')" :key="val.trim()" class="badge bg-primary me-1">{{ val.trim() }}</span>
        <span v-if="field.value && field.receivedChecked" class="badge bg-success me-1">受領済み</span>
        <span v-if="!field.value">-</span>
    </div>
    <div v-if="field.value && field.memoValue" class="mt-1 text-muted small">{{ field.memoValue }}</div>
</template>
