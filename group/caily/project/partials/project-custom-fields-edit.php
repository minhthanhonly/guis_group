<?php
/**
 * Custom fields of a project, edit state of the form.
 * Vue fragment; $pfCustomFieldList is the Vue expression of the list to render.
 * Included by project-form-grid.php (once per group of 部署別項目).
 */
?>
<template v-for="(field, idx) in <?= $pfCustomFieldList ?>" :key="idx">
    <template v-if="isEnergyMaterialCompositeField(field)"></template>
    <div v-else-if="field.type === 'textarea'" class="col-12 mb-3">
        <label class="form-label">{{ translateLabel(field.label) }}</label>
        <textarea class="form-control" v-model="field.value"></textarea>
    </div>
    <div v-else-if="field.isComposite && !isEnergyMaterialCompositeField(field)" :class="['mb-3', field.one_row ? 'col-12' : 'col-md-6']">
        <label class="form-label">{{ translateLabel(field.label) }}</label>
        <?php require __DIR__ . '/project-composite-field-edit.php'; ?>
    </div>
    <div v-else :class="['mb-3', field.one_row ? 'col-12' : 'col-md-6', { 'cf-status-companion': field.statusOf }]">
        <div class="form-label d-flex flex-column align-items-start gap-1">
            <span>{{ field.statusOf ? '' : translateLabel(field.label) }}</span>
            <button v-if="field.type === 'checkbox_select_all'"
                    type="button"
                    class="btn btn-sm btn-outline-secondary py-0"
                    @click="toggleAllCustomCheckbox(field)">
                {{ isAllCustomCheckboxSelected(field) ? translateLabel('すべて解除') : translateLabel('すべて選択') }}
            </button>
        </div>
        <template v-if="field.type === 'radio'">
            <div class="form-check form-check-inline" v-for="opt in field.options.split(',')" :key="opt.trim()">
                <input class="form-check-input" type="radio" :name="'custom_radio_' + idx" :value="opt.trim()" v-model="field.value">
                <label class="form-check-label">{{ opt.trim() }}</label>
            </div>
        </template>
        <template v-else-if="field.type === 'select'">
            <select class="form-select" v-model="field.value">
                <option value="" data-i18n="選択してください">選択してください</option>
                <option v-for="opt in field.options.split(',')" :key="opt.trim()" :value="opt.trim()">{{ opt.trim() }}</option>
            </select>
        </template>
        <template v-else-if="field.type === 'checkbox' || field.type === 'checkbox_select_all'">
            <div class="form-check form-check-inline" v-for="opt in field.options.split(',')" :key="opt.trim()">
                <input class="form-check-input" type="checkbox" :name="'custom_checkbox_' + idx" :value="opt.trim()" v-model="field.valueArr">
                <label class="form-check-label">{{ opt.trim() }}</label>
            </div>
        </template>
        <template v-else-if="field.type === 'datetime'">
            <div class="input-group">
                <input type="text" class="form-control custom-field-datetime" 
                       :id="'custom_datetime_' + idx" 
                       v-model="field.value" 
                       :placeholder="getProjectDateTimePlaceholder()" 
                       autocomplete="off">
                <span class="input-group-text"><i class="fa fa-calendar"></i></span>
            </div>
        </template>
        <template v-else>
            <input class="form-control" v-model="field.value" type="text">
        </template>
    </div>
</template>
