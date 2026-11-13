<?php
/**
 * Custom fields of a project on the read-only page (inline edit where allowed).
 * Vue fragment; $pfCustomFieldList is the Vue expression of the list to render.
 * Included by project-form-grid.php (once per group of 部署別項目).
 */
?>
<template v-for="(field, idx) in <?= $pfCustomFieldList ?>" :key="idx">
    <template v-if="isEnergyMaterialCompositeField(field)"></template>
    <div v-else-if="field.type === 'textarea'" class="col-12 mb-3">
        <label class="form-label">{{ translateLabel(field.label) }}</label>
        <div class="form-control" style="min-height:80px;white-space:pre-line;">{{ field.value || '-' }}</div>
    </div>
    <div v-else-if="field.isComposite && !isEnergyMaterialCompositeField(field)" :class="['mb-3', field.one_row ? 'col-12' : 'col-md-6']">
        <label class="form-label">{{ translateLabel(field.label) }}</label>
        <?php require __DIR__ . '/project-composite-field-view.php'; ?>
    </div>
    <div v-else :class="['mb-3', field.one_row ? 'col-12' : 'col-md-6', { 'cf-status-companion': field.statusOf }]">
        <div class="form-label d-flex flex-column align-items-start gap-1">
            <span>{{ field.statusOf ? '' : translateLabel(field.label) }}</span>
            <button v-if="canEditProject && field.type === 'checkbox_select_all'"
                    type="button"
                    class="btn btn-sm btn-outline-secondary py-0"
                    @click="onCustomFieldSelectAllToggle(field)">
                {{ isAllCustomFieldCheckboxSelected(field) ? translateLabel('すべて解除') : translateLabel('すべて選択') }}
            </button>
        </div>
        <template v-if="canEditProject && (field.type === 'select' || field.type === 'radio' || field.type === 'checkbox' || field.type === 'checkbox_select_all')">
            <select v-if="field.type === 'select'" class="form-select form-select-sm" :value="field.value" @change="updateCustomFieldValue(field.label, $event.target.value)">
                <option value="">選択してください</option>
                <option v-for="opt in (field.options || '').split(',').map(o=>o.trim()).filter(Boolean)" :key="opt" :value="opt">{{ translateLabel(opt) }}</option>
            </select>
            <template v-else-if="field.type === 'radio'">
                <div class="d-flex flex-wrap gap-2">
                    <div v-for="opt in (field.options || '').split(',').map(o=>o.trim()).filter(Boolean)" :key="opt" class="form-check">
                        <input class="form-check-input" type="radio" :name="'cf_'+field.label+'_'+idx" :value="opt" :checked="field.value === opt" @change="updateCustomFieldValue(field.label, opt)">
                        <label class="form-check-label">{{ translateLabel(opt) }}</label>
                    </div>
                </div>
            </template>
            <template v-else-if="field.type === 'checkbox' || field.type === 'checkbox_select_all'">
                <div class="d-flex flex-wrap gap-2">
                    <div v-for="opt in (field.options || '').split(',').map(o=>o.trim()).filter(Boolean)" :key="opt" class="form-check">
                        <input class="form-check-input" type="checkbox" :value="opt" :checked="isCustomFieldCheckboxChecked(field.label, opt)" @change="onCustomFieldCheckboxChange(field, opt, $event.target.checked)">
                        <label class="form-check-label">{{ translateLabel(opt) }}</label>
                    </div>
                </div>
            </template>
        </template>
        <template v-else>
            <template v-if="field.type === 'checkbox' || field.type === 'checkbox_select_all' || field.type === 'radio' || field.type === 'select'">
                <div>
                    <span v-if="field.value">
                        <span v-for="val in field.value.split(',')" :key="val.trim()" class="badge bg-primary me-1">{{ translateLabel(val.trim()) }}</span>
                    </span>
                    <span v-else>-</span>
                </div>
            </template>
            <template v-else-if="field.type === 'datetime'">
                <div class="form-control" :data-time="field.value || ''" :data-todo-title="(project ? ('#' + project.id + ' ' + (project.name || '')) : '') + field.label" :data-todo-link="project ? ('/project/detail.php?id=' + project.id) : ''">{{ formatDateTime(field.value) }}</div>
            </template>
            <template v-else-if="isInlineEditableCustomField(field)">
                <input type="text" class="form-control form-control-sm" :value="field.value" @change="updateCustomFieldValue(field.label, $event.target.value)">
            </template>
            <template v-else>
                <div class="form-control">{{ field.value || '-' }}</div>
            </template>
        </template>
    </div>
</template>
