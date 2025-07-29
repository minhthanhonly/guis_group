<?php
require_once('../application/loader.php');
$view->heading('親プロジェクト作成');
?>
<div id="app" class="container-fluid mt-4" v-cloak>

    <div class="row">
        <!-- Back button -->
        <div class="col-12 mb-3">
            <a href="index.php" class="btn btn-outline-primary">
                <i class="fa fa-arrow-left me-1"></i> <span data-i18n="戻る">戻る</span>
            </a>
        </div>

        <!-- Left Column - Parent Project Details -->
        <div class="col-xl-8">
            <div class="card edit-mode">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title"><span data-i18n="親プロジェクト作成">親プロジェクト作成</span></h5>
                        <div>
                            <button class="btn btn-success btn-sm me-2" @click="saveParentProject" title="保存">
                                <i class="fa fa-save"></i>
                            </button>
                            <a href="index.php" class="btn btn-secondary btn-sm" title="キャンセル">
                                <i class="fa fa-times"></i>
                            </a>
                        </div>
                    </div>

                    <div class="row g-3" v-if="parentProject">
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="会社名">会社名</span> <span class="text-danger">*</span></label>
                                <select id="company_name" class="form-select select2" v-model="parentProject.company_name" required>
                                    <option value="" data-i18n="選択してください">選択してください</option>
                                </select>
                                <div v-if="validationErrors.company_name" class="invalid-feedback d-block">
                                    {{ validationErrors.company_name }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="支店名">支店名</span></label>
                                <select id="branch_name" class="form-select select2" v-model="parentProject.branch_name">
                                    <option value="" data-i18n="選択してください">選択してください</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="担当様">担当様</span></label>
                                <select id="contact_name" class="form-select select2" v-model="parentProject.contact_name">
                                    <option value="" data-i18n="選択してください">選択してください</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="GUIS　受付者">GUIS　受付者</span></label>
                                <select id="guis_receiver" class="form-select select2" v-model="parentProject.guis_receiver">
                                    <option value="" data-i18n="選択してください">選択してください</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label">
                                    <span data-i18n="依頼日">依頼日</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary ms-2" @click="setCurrentDateTime">
                                        <i class="fa fa-clock"></i> 現在時刻
                                    </button>
                                </label>
                                <input type="text" class="form-control" v-model="parentProject.request_date" id="request_date_picker" placeholder="YYYY/MM/DD HH:mm" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="工事番号">工事番号</span></label>
                                <input type="text" class="form-control" v-model="parentProject.construction_number">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label">
                                    <span data-i18n="プロジェクト番号">プロジェクト番号</span>
                                    <button class="btn btn-sm btn-outline-primary py-0 small ms-2" @click="generateProjectNumber" title="生成">
                                        生成
                                    </button>
                                </label>
                                <input type="text" class="form-control" v-model="parentProject.project_number">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="工事支店">工事支店</span></label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="text" class="form-control tagify" v-model="parentProject.construction_branch" id="construction_branch_tags" name="construction_branch_tags">
                                    <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearTagifyTags('construction_branch')" title="すべて削除"><i class="fa fa-times"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="案件名">案件名</span> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" v-model="parentProject.project_name" required>
                                <div v-if="validationErrors.project_name" class="invalid-feedback d-block">
                                    {{ validationErrors.project_name }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="建物規模">建物規模</span></label>
                                <input type="text" class="form-control" v-model="parentProject.scale">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="種類1">種類1</span></label>
                                <input type="text" class="form-control" v-model="parentProject.type1" id="type1_tags" name="type1_tags">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="種類2">種類2</span></label>
                                <input type="text" class="form-control" v-model="parentProject.type2" id="type2_tags" name="type2_tags">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label">
                                    <span data-i18n="希望納期">希望納期</span>
                                                                            <button type="button" class="btn btn-sm btn-outline-primary ms-2" @click="setTodayDate">
                                        <i class="fa fa-calendar"></i> 今日
                                    </button>
                                </label>
                                <input type="text" class="form-control" v-model="parentProject.desired_delivery_date" id="desired_delivery_date_picker" placeholder="YYYY/MM/DD" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="構造事務所">構造事務所</span></label>
                                <input type="text" class="form-control" v-model="parentProject.structural_office">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="ステータス">ステータス</span></label>
                                <div>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light" 
                                                :class="getStatusButtonClass(parentProject.status)"
                                                id="statusDropdown"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                            {{ getStatusLabel(parentProject.status) }}
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li v-for="status in statuses" :key="status.value">
                                                <a class="dropdown-item waves-effect" href="javascript:void(0);" 
                                                @click="selectStatus(status.value)">
                                                    <span :data-i18n="status.label">{{ status.label }}</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="資料">資料</span></label>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="materials_layout" v-model="parentProject.materials_layout">
                                            <label class="form-check-label" for="materials_layout">
                                                配置図
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="materials_rental" v-model="parentProject.materials_rental">
                                            <label class="form-check-label" for="materials_rental">
                                                家賃審査書
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="materials_contract" v-model="parentProject.materials_contract">
                                            <label class="form-check-label" for="materials_contract">
                                                契約図
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="materials_tac" v-model="parentProject.materials_tac">
                                            <label class="form-check-label" for="materials_tac">
                                                TAC図
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="materials_other" v-model="parentProject.materials_other">
                                            <label class="form-check-label" for="materials_other">
                                                その他
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="備考">備考</span></label>
                                <textarea class="form-control" v-model="parentProject.notes" rows="3" placeholder="備考を入力してください"></textarea>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-5">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Info -->
        <div class="col-xl-4">
            <!-- Info Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><span data-i18n="作成情報">作成情報</span></h5>
                </div>
                <div class="card-body">
                    <div class="text-center py-3">
                        <i class="fa fa-plus-circle fs-1 text-primary"></i>
                        <p class="text-muted mt-2" data-i18n="新しい親プロジェクトを作成します">新しい親プロジェクトを作成します</p>
                        <p class="small text-muted" data-i18n="必須項目（*）を入力してから保存してください">必須項目（*）を入力してから保存してください</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$view->footing();
?>

<style>
.edit-mode .form-control:not([readonly]) {
    border-color: var(--bs-primary);
}

.content-wrapper{
    overflow-x: hidden;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="assets/js/parent-project-create.js?v=<?=CACHE_VERSION?>"></script> 