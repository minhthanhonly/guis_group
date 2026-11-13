<?php
/**
 * Shared project (案件) form dialog — one implementation for every page.
 *
 * Standalone Vue app (project/assets/js/project-form-modal.js) built on
 * window.ProjectFormMixin, so it runs the same fields, validation and save logic as the
 * project detail page. Include this partial OUTSIDE the host page's own #app, and load:
 *   project-energy-fields-mixin.js, project-form-mixin.js, project-form-modal.js
 * (after yotei-field.js, energy-drawing-share.js, energy-fee-payment.js and
 * project-custom-field-composite.js, which the host pages already load).
 *
 * Open it with:
 *   window.openProjectFormModal({ projectId: 123, onSaved: function (id) { ... } })
 *   window.openProjectFormModal({ mode: 'create', parentProject: building, onSaved: ... })
 * (all options: project-form-modal.js). It loads the project and the user's permission
 * itself; the host only refreshes its own view in onSaved (a "project:saved" event is also
 * dispatched on document).
 */
?>
<div id="project-form-modal-app" v-cloak>
    <div class="modal fade" id="projectFormModal" tabindex="-1" aria-labelledby="projectFormModalLabel"
         aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl project-form-modal" :class="{ 'pf-manager-only': formManagerOnly, 'pf-create': formCreateMode }">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="projectFormModalLabel">
                        <span v-show="!formCreateMode" data-i18n="案件を編集">案件を編集</span>
                        <span v-show="formCreateMode" data-i18n="案件依頼を作成">案件依頼を作成</span>
                        <span v-if="project && project.id" class="badge bg-label-primary ms-2">#{{ project.id }}</span>
                        <span v-if="project && project.department_name" class="badge border border-info bg-transparent text-info ms-2">{{ project.department_name }}</span>
                    </h5>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <button type="button" class="btn btn-secondary btn-sm" @click="cancelForm" :disabled="savingProject">
                            <span data-i18n="キャンセル">キャンセル</span>
                        </button>
                        <button v-if="formOptions.allowRestore && !formCreateMode && project && project.status === 'cancelled'"
                                type="button" class="btn btn-warning btn-sm" @click="restoreProject" :disabled="restoringProject || savingProject || formLoading">
                            <span v-if="restoringProject" class="spinner-border spinner-border-sm me-1"></span>
                            <span data-i18n="復元">復元</span>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" @click="saveProject" :disabled="savingProject || formLoading || !isEditMode">
                            <span v-if="savingProject" class="spinner-border spinner-border-sm me-1"></span>
                            <span v-show="!formCreateMode" data-i18n="更新">更新</span>
                            <span v-show="formCreateMode" data-i18n="作成">作成</span>
                        </button>
                        <button type="button" class="btn-close" aria-label="Close" @click="cancelForm" :disabled="savingProject"></button>
                    </div>
                </div>
                <div class="modal-body position-relative">
                    <div v-if="formLoading" class="text-center py-5">
                        <div class="spinner-border text-primary mb-2" role="status" style="width: 2.5rem; height: 2.5rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="small text-muted" data-i18n="読み込み中...">読み込み中...</div>
                    </div>
                    <!-- The same field markup as the project detail page, in its edit state -->
                    <template v-if="!formLoading && project && isEditMode">
                        <?php require __DIR__ . '/project-form-grid.php'; ?>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
