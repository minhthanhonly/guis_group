<?php
/**
 * 建物情報 / 建物編集 modal (shared, standalone Vue app). View mode first; 編集 switches to the edit form
 * when the user may edit buildings ($_SESSION['isProjectManager']).
 *
 * Include this OUTSIDE the host page's Vue root (it mounts its own app on #parent-project-quick-modal-app)
 * and load, after Vue / axios:
 *   parent_project/assets/js/parent-project-error.js, parent-project-tagify-options.js,
 *   parent-project-customers-mixin.js, parent-project-quick-modal.js
 * Open it with window.openParentProjectModal(parentProjectId, { mode: 'view' | 'edit', onSaved }).
 */
$parentProjectQuickModalCanEdit = !empty($_SESSION['isProjectManager']);
?>
<div id="parent-project-quick-modal-app" v-cloak>
    <div class="modal fade" id="editParentProjectModal" tabindex="-1" aria-labelledby="editParentProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editParentProjectModalLabel">
                        <span v-if="isEditMode" data-i18n="建物編集">建物編集</span>
                        <span v-else data-i18n="建物情報">建物情報</span>
                        <span v-if="editingParentProject.id" class="badge bg-label-primary ms-2">#{{ editingParentProject.id }}</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div v-if="editParentProjectLoading" class="text-center text-muted py-5">
                        <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                        <span data-i18n="読み込み中...">読み込み中...</span>
                    </div>

                    <!-- View mode (read-only) -->
                    <div v-else-if="!isEditMode" class="row g-3">
                        <?php include __DIR__ . '/customer-list-view.php'; ?>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="GUIS受付者">GUIS受付者</span></label>
                                <input type="text" class="form-control" :value="guisReceiverName || editingParentProject.guis_receiver || '-'" readonly>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="お施主様名">お施主様名</span></label>
                                <input type="text" class="form-control" :value="editingParentProject.project_name || '-'" readonly>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="依頼日">依頼日</span></label>
                                <input type="text" class="form-control" :value="viewRequestDate || '-'" readonly>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="管理番号">管理番号</span></label>
                                <input type="text" class="form-control" :value="editingParentProject.project_number || '-'" readonly>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="工事番号">工事番号</span></label>
                                <input type="text" class="form-control" :value="editingParentProject.construction_number || '-'" readonly>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="建物規模">建物規模</span></label>
                                <input type="text" class="form-control" :value="editingParentProject.scale || '-'" readonly>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3" v-if="editingParentProject.city">
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="市区町村">市区町村</span></label>
                                <input type="text" class="form-control" :value="editingParentProject.city" readonly>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="構造事務所">構造事務所</span></label>
                                <input type="text" class="form-control" :value="editingParentProject.structural_office || '-'" readonly>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="種類1">種類1</span></label>
                                <div style="min-height: 38px;">
                                    <span v-for="item in splitList(editingParentProject.type1)" :key="'t1_' + item" class="badge bg-info me-1">{{ item }}</span>
                                    <span v-if="splitList(editingParentProject.type1).length === 0">-</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="住宅種類">住宅種類</span></label>
                                <div style="min-height: 38px;">
                                    <span v-for="item in splitList(editingParentProject.type2)" :key="'t2_' + item" class="badge bg-info me-1">{{ item }}</span>
                                    <span v-if="splitList(editingParentProject.type2).length === 0">-</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3" v-if="editHasNonResidentialType">
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="非住宅の主要用途">非住宅の主要用途</span></label>
                                <div style="min-height: 38px;">{{ viewNonResidentialMainUseLabel || '-' }}</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="依頼">依頼</span></label>
                                <div class="d-flex flex-wrap gap-1 align-items-center">
                                    <span v-for="item in viewRequestTypes" :key="'rq_' + item" class="badge me-1 mb-1"
                                        :class="isRequestFulfilled(item) ? getRequestBadgeClass(item) : getRequestUnfulfilledBadgeClass(item)"
                                        :title="isRequestFulfilled(item) ? '' : '未作成'">
                                        <i v-if="!isRequestFulfilled(item)" class="fa fa-exclamation-triangle me-1"></i>
                                        <i v-else class="fa fa-check me-1"></i>
                                        {{ item }}
                                    </span>
                                    <span v-if="viewRequestTypes.length === 0" class="text-muted">-</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="お客様提供資料">お客様提供資料</span></label>
                                <div class="row">
                                    <div class="col-md-4" v-for="item in viewMaterials" :key="'mt_' + item">
                                        <i class="fa fa-check text-success me-2"></i>{{ item }}
                                        <span v-if="item === 'その他' && editingParentProject.materials_memo" class="small text-muted ms-1">({{ editingParentProject.materials_memo }})</span>
                                    </div>
                                    <div v-if="viewMaterials.length === 0" class="col-12"><span class="text-muted">-</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="特徴">特徴</span></label>
                                <div class="d-flex flex-wrap gap-1 align-items-center">
                                    <span v-for="opt in viewFeatureLabels" :key="'ft_' + opt" class="badge badge-outline-primary me-1">{{ opt }}</span>
                                    <span v-if="viewFeatureLabels.length === 0" class="text-muted">-</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-12" v-if="viewHasEnergySavingRequest">
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="省エネ向け資料">省エネ向け資料</span></label>
                                <div class="d-flex flex-wrap gap-1 align-items-center">
                                    <span v-for="opt in viewEnergyMaterialLabels" :key="'em_' + opt" class="badge badge-outline-primary me-1">{{ opt }}</span>
                                    <span v-if="editingParentProject.energy_materials_memo && viewEnergyMaterialLabels.indexOf('その他') !== -1" class="small text-muted ms-1">({{ editingParentProject.energy_materials_memo }})</span>
                                    <span v-if="viewEnergyMaterialLabels.length === 0" class="text-muted">-</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6" v-if="viewHasEnergySavingRequest">
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="申請提出先の審査機関">申請提出先の審査機関</span></label>
                                <div style="min-height: 38px;">
                                    <span v-for="item in splitList(editingParentProject.application_review_agency)" :key="'ag_' + item" class="badge badge-outline-primary me-1">{{ item }}</span>
                                    <span v-if="splitList(editingParentProject.application_review_agency).length === 0" class="text-muted">-</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6" v-if="viewHasEnergySavingRequest">
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="BELSのZEH等級">BELSのZEH等級</span></label>
                                <div style="min-height: 38px;">{{ viewBelsZehGradeLabel || '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit mode -->
                    <div v-else class="row g-3">
                            <?php $customerListEditorAllowNew = false; include __DIR__ . '/customer-list-editor.php'; ?>
                            <div class="col-md-4 col-xl-3">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="GUIS受付者">GUIS受付者</span></label>
                                    <select id="edit_pp_guis_receiver" class="form-select" name="edit_pp_guis_receiver">
                                        <option value="">選択してください</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4 col-xl-3">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="お施主様名">お施主様名</span> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="editingParentProject.project_name"
                                           placeholder="案件名を入力" :class="{ 'is-invalid': !!editParentProjectErrors.project_name }">
                                    <div class="invalid-feedback d-block" v-if="editParentProjectErrors.project_name">{{ editParentProjectErrors.project_name }}</div>
                                </div>
                            </div>
                            <div class="col-md-4 col-xl-3">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="依頼日">依頼日</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" v-model="editingParentProject.request_date"
                                               id="edit_pp_request_date" placeholder="YYYY/MM/DD HH:mm" autocomplete="off">
                                        <button class="btn btn-outline-secondary" type="button" @click="setEditParentCurrentDateTime" title="現在時刻">
                                            現在時刻
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 col-xl-3">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="管理番号">管理番号</span></label>
                                    <input type="text" class="form-control" :value="editingParentProject.project_number || '-'" readonly>
                                </div>
                            </div>
                            <div class="col-md-4 col-xl-3">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="工事番号">工事番号</span></label>
                                    <input type="text" class="form-control" v-model="editingParentProject.construction_number" placeholder="工事番号を入力">
                                </div>
                            </div>
                            <div class="col-md-4 col-xl-3">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="建物規模">建物規模</span></label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control tagify" id="edit_pp_scale_tags" name="edit_pp_scale_tags" placeholder="規模を入力">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearEditParentTagifyTags('scale')" title="すべて削除">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 col-xl-3">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="市区町村">市区町村</span></label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control tagify" id="edit_pp_city_tags" name="edit_pp_city_tags" placeholder="市区町村を入力">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearEditParentTagifyTags('city')" title="すべて削除">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 col-xl-3">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="構造事務所">構造事務所</span></label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control tagify" id="edit_pp_structural_office_tags" name="edit_pp_structural_office_tags" placeholder="構造事務所を入力">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearEditParentTagifyTags('structural_office')" title="すべて削除">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 col-xl-3">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="種類1">種類1</span></label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control tagify" id="edit_pp_type1_tags" name="edit_pp_type1_tags">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearEditParentTagifyTags('type1')" title="すべて削除">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 col-xl-3">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="住宅種類">住宅種類</span></label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control tagify" id="edit_pp_type2_tags" name="edit_pp_type2_tags">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearEditParentTagifyTags('type2')" title="すべて削除">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 col-xl-3" v-if="editHasNonResidentialType">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="非住宅の主要用途">非住宅の主要用途</span></label>
                                    <select class="form-select" v-model="editingParentProject.type3">
                                        <option value="">選択してください</option>
                                        <option v-for="opt in nonResidentialMainUseOptions" :key="opt.value" :value="opt.value">
                                            {{ opt.label }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="依頼">依頼</span></label>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="edit_pp_request_design" v-model="editParentRequestFlags.design">
                                                <label class="form-check-label" for="edit_pp_request_design">意匠</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="edit_pp_request_equipment" v-model="editParentRequestFlags.equipment">
                                                <label class="form-check-label" for="edit_pp_request_equipment">設備</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="edit_pp_request_3d_equipment" v-model="editParentRequestFlags.equipment3d">
                                                <label class="form-check-label" for="edit_pp_request_3d_equipment">3D設備</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="edit_pp_request_energy_saving" v-model="editParentRequestFlags.energy">
                                                <label class="form-check-label" for="edit_pp_request_energy_saving">省エネ</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="edit_pp_request_other" v-model="editParentRequestFlags.other">
                                                <label class="form-check-label" for="edit_pp_request_other">その他</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="お客様提供資料">お客様提供資料</span></label>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="edit_pp_materials_layout" v-model="editParentMaterialFlags.layout">
                                                <label class="form-check-label" for="edit_pp_materials_layout">配置図</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="edit_pp_materials_rental" v-model="editParentMaterialFlags.rental">
                                                <label class="form-check-label" for="edit_pp_materials_rental">家賃審査書</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="edit_pp_materials_contract" v-model="editParentMaterialFlags.contract">
                                                <label class="form-check-label" for="edit_pp_materials_contract">契約図</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="edit_pp_materials_tac" v-model="editParentMaterialFlags.tac">
                                                <label class="form-check-label" for="edit_pp_materials_tac">TAC図</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="edit_pp_materials_other" v-model="editParentMaterialFlags.other">
                                                <label class="form-check-label" for="edit_pp_materials_other">その他</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2" v-if="editParentMaterialFlags.other">
                                        <input type="text" class="form-control" v-model="editingParentProject.materials_memo"
                                               placeholder="その他メモ" maxlength="500">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="特徴">特徴</span></label>
                                    <div class="row">
                                        <div class="col-md-4" v-for="opt in editVisibleFeatureOptions" :key="opt">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                    :id="'edit_pp_features_' + opt"
                                                    v-model="editParentFeatureFlags[opt]">
                                                <label class="form-check-label" :for="'edit_pp_features_' + opt">{{ opt }}</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12" v-if="editHasEnergySavingRequest">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="省エネ向け資料">省エネ向け資料</span></label>
                                    <div class="row">
                                        <div class="col-md-4" v-for="opt in energyMaterialOptions" :key="'edit_em_' + opt">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                    :id="'edit_pp_energy_materials_' + opt"
                                                    v-model="editParentEnergyMaterialFlags[opt]">
                                                <label class="form-check-label" :for="'edit_pp_energy_materials_' + opt">{{ opt }}</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2" v-if="editParentEnergyMaterialFlags['その他']">
                                        <input type="text" class="form-control" v-model="editingParentProject.energy_materials_memo"
                                               placeholder="その他メモ" maxlength="500">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6" v-if="editHasEnergySavingRequest">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="申請提出先の審査機関">申請提出先の審査機関</span></label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control tagify" id="edit_pp_application_review_agency_tags" name="edit_pp_application_review_agency_tags">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearEditParentTagifyTags('application_review_agency')" title="すべて削除">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6" v-if="editHasEnergySavingRequest">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="BELSのZEH等級">BELSのZEH等級</span></label>
                                    <select class="form-select" v-model="editingParentProject.bels_zeh_grade">
                                        <option v-for="opt in belsZehGradeOptions" :key="opt.value" :value="opt.value">
                                            {{ opt.label }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <template v-if="!isEditMode">
                        <a v-if="editingParentProject.id" class="btn btn-outline-primary me-auto" :href="parentDetailUrl()">
                            <i class="fa fa-building me-1"></i> <span data-i18n="建物詳細を開く">建物詳細を開く</span>
                        </a>
                        <button v-if="canEdit" type="button" class="btn btn-outline-warning" @click="enterEditMode"
                                :disabled="editParentProjectLoading">
                            <i class="fa fa-pencil-alt me-1"></i> <span data-i18n="編集">編集</span>
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><span data-i18n="閉じる">閉じる</span></button>
                    </template>
                    <template v-else>
                        <button type="button" class="btn btn-secondary" @click="cancelEdit" :disabled="editParentProjectSaving"><span data-i18n="キャンセル">キャンセル</span></button>
                        <button type="button" class="btn btn-primary" @click="saveParentProjectFromModal"
                                :disabled="editParentProjectLoading || editParentProjectSaving">
                            <span v-if="editParentProjectSaving" class="spinner-border spinner-border-sm me-1" role="status"></span>
                            <span data-i18n="保存">保存</span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Search Modal / Customer List Modal (building customer list) -->
    <?php
    // Own ids: the host page may already contain a customer list modal for its own app
    $customerSearchModalId = 'qmCustomerSearchModal';
    $customerListModalId = 'qmCustomerListModal';
    include __DIR__ . '/customer-search-modal.php';
    include __DIR__ . '/customer-list-modal.php';
    unset($customerSearchModalId, $customerListModalId);
    ?>
</div>
<script>
window.PARENT_PROJECT_QUICK_MODAL_CAN_EDIT = <?php echo $parentProjectQuickModalCanEdit ? 'true' : 'false'; ?>;
</script>
<style>
#parent-project-quick-modal-app[v-cloak] {
    display: none;
}

#editParentProjectModal .select2-container {
    width: 100% !important;
}

#editParentProjectModal .select2-container--open {
    z-index: 1065;
}

#editParentProjectModal .tagify {
    width: 100%;
}

/* Same compact tag inputs on every host page (parent_project/detail.php enlarges .modal .tagify__input) */
#editParentProjectModal .tagify__input {
    min-height: 0;
}

/* Pickers / dropdowns rendered on <body> must sit above the open modal */
body:has(#editParentProjectModal.show) > .select2-container--open,
body:has(#editParentProjectModal.show) .flatpickr-calendar.open,
body:has(#editParentProjectModal.show) .tagify__dropdown {
    z-index: 1100 !important;
}

.customer-search-results {
    max-height: 400px;
    overflow-y: auto;
}

.customer-search-row {
    cursor: pointer;
}

.customer-search-row:hover {
    background-color: rgba(var(--bs-primary-rgb), 0.08);
}

.customer-search-row.customer-search-row-added {
    cursor: default;
    opacity: 0.65;
}

/* These open on top of the building modal: dim the modal underneath (their own backdrop sits below it) */
#qmCustomerSearchModal,
#qmCustomerListModal {
    background-color: rgba(0, 0, 0, 0.45);
}

/* 顧客情報 → 詳細 opens the global customer modal, which sits earlier in the DOM: lift it above the building modal */
body:has(#editParentProjectModal.show) #globalCustomerModal {
    z-index: 1095;
    background-color: rgba(0, 0, 0, 0.45);
}
</style>
