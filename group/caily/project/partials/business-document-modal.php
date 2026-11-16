<?php
/**
 * 決済情報 modal — 案件一覧 (project/index.php) and 建物詳細 (parent_project/detail.php).
 * Same block layout as the 決済情報 card on 案件詳細.
 *
 * Host: businessDocumentProject, the Bd* methods (BusinessDocumentModalMixin / parent-project-detail.js)
 * and BusinessDocumentUiMixin (bdm* adapters, ¥ amount input).
 * Styles: project/assets/css/project-business-doc.css
 */
?>
    <!-- Business Document (決済情報) Modal -->
    <div class="modal fade bd-edit-modal" id="businessDocumentModal" tabindex="-1" aria-labelledby="businessDocumentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content bd-scope" v-if="businessDocumentProject">
                <div class="modal-header">
                    <div class="bd-modal-heading">
                        <h5 class="modal-title mb-0 d-flex align-items-center gap-2" id="businessDocumentModalLabel">
                            <span data-i18n="決済情報">決済情報</span>
                            <span v-if="businessDocumentSaveStatus === 'loading'" class="text-muted fs-6" title="保存中">
                                <i class="fa fa-spinner fa-spin"></i>
                            </span>
                            <span v-else-if="businessDocumentSaveStatus === 'saved'" class="text-success fs-6" title="保存済み">
                                <i class="fa fa-check-circle"></i>
                            </span>
                        </h5>
                        <div class="bd-modal-project">#{{ businessDocumentProjectId }} {{ businessDocumentProject.name }}</div>
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <button type="button" class="btn btn-outline-info btn-sm" @click="openBusinessDocumentLogModal">
                            <i class="fa fa-history me-1"></i><span data-i18n="履歴">履歴</span>
                        </button>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" @click="closeBusinessDocumentModal"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <input type="hidden" v-model.number="businessDocumentProject.payment_version">

                    <!-- 見積 / 請求: any number of lines (payment-items.js) -->
                    <div v-for="section in bdmSections" :key="'bdm-' + section.kind" class="bd-section">
                        <div class="bd-section-title">
                            <i :class="section.icon" class="me-1"></i><span :data-i18n="section.heading">{{ section.heading }}</span>
                        </div>
                        <payment-items-editor :project="businessDocumentProject" :kind="section.kind" :editable="true"
                                              :energy="!!isBdEnergyDepartment" :translate="translateLabel" @change="scheduleBdUpdate"></payment-items-editor>
                    </div>

                    <div class="mb-0 bd-note">
                        <label class="bd-label" data-i18n="決済備考">決済備考</label>
                        <textarea class="form-control form-control-sm" rows="3" placeholder="備考を入力" v-model="businessDocumentProject.payment_note" @change="scheduleBdUpdate"></textarea>
                    </div>
                    <div v-if="businessDocumentError" class="alert alert-danger mt-3 mb-0">{{ businessDocumentError }}</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" @click="closeBusinessDocumentModal">閉じる</button>
                </div>
            </div>
        </div>
    </div>
