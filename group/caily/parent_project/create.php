<?php
require_once('../application/loader.php');
$view->heading('建物登録');
$permModel = new ApplicationModel();
if (!$permModel->hasDepartmentPermission('project_add')) {
    die('権限がありません。');
}
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
                        <h5 class="card-title"><span data-i18n="建物登録">建物登録</span></h5>
                        <div>
                            <button class="btn btn-success btn-sm me-2" @click="saveParentProject" title="保存">
                                <i class="fa fa-save me-1"></i><span data-i18n="保存">保存</span>
                            </button>
                            <a href="index.php" class="btn btn-secondary btn-sm" title="キャンセル">
                                <i class="fa fa-times me-1"></i><span data-i18n="キャンセル">キャンセル</span>
                            </a>
                        </div>
                    </div>

                    <div class="row g-3" v-if="parentProject">
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="会社名">会社名</span> <span class="text-danger">*</span></label>
                                <select id="company_name" class="form-select select2" v-model="parentProject.company_name" @change="onCompanyNameChange" required>
                                    <option value="" data-i18n="選択してください">選択してください</option>
                                </select>
                                <div v-if="validationErrors.company_name" class="invalid-feedback d-block">
                                    {{ validationErrors.company_name }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="支店名">支店名</span> <span class="text-danger">*</span></label>
                                <select id="branch_name" class="form-select select2" v-model="parentProject.branch_name" @change="onBranchNameChange" required>
                                    <option value="" data-i18n="選択してください">選択してください</option>
                                </select>
                                <div v-if="validationErrors.branch_name" class="invalid-feedback d-block">
                                    {{ validationErrors.branch_name }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label">
                                    <span data-i18n="担当様">担当様</span> <span class="text-danger">*</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 small ms-2" @click="openNewCustomerModal" title="新規顧客追加">
                                        <i class="fa fa-plus me-1"></i> 新規顧客
                                    </button>
                                    <button v-if="parentProject.contact_name || parentProject.customer_id" type="button"
                                        class="btn btn-sm btn-outline-info py-0 small ms-2" @click="openCustomerInfoModal"
                                        title="顧客情報表示・編集">
                                        <i class="fa fa-info-circle me-1"></i> <span data-i18n="顧客情報">顧客情報</span>
                                    </button>
                                </label>
                                <select id="contact_name" class="form-select select2" v-model="parentProject.contact_name" @change="onContactNameChange" :disabled="!parentProject.company_name || !parentProject.branch_name" required>
                                    <option value="" data-i18n="選択してください">
                                        {{ !parentProject.company_name || !parentProject.branch_name ? '会社名と支店名を選択してください' : '選択してください' }}
                                    </option>
                                    <option v-for="customer in customers" :key="customer.id" :value="customer.name" :data-customer-id="customer.id">
                                        {{ customer.name }}
                                    </option>
                                </select>
                                <div v-if="validationErrors.contact_name" class="invalid-feedback d-block">
                                    {{ validationErrors.contact_name }}
                                </div>
                                <div v-if="!parentProject.company_name || !parentProject.branch_name" class="form-text text-muted">
                                    会社名と支店名を選択すると担当様が表示されます
                                </div>
                                <div v-else-if="customers.length === 0 && parentProject.company_name && parentProject.branch_name && !parentProject.contact_name" class="form-text text-muted">
                                    選択した会社・支店に担当者が見つかりません
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="GUIS　受付者">GUIS　受付者</span> <span class="text-danger">*</span></label>
                                <select id="guis_receiver" class="form-select select2" v-model="parentProject.guis_receiver" required>
                                    <option value="" data-i18n="選択してください">選択してください</option>
                                </select>
                                <div v-if="validationErrors.guis_receiver" class="invalid-feedback d-block">
                                    {{ validationErrors.guis_receiver }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label">
                                    <span data-i18n="依頼日">依頼日</span> <span class="text-danger">*</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 small ms-2" @click="setCurrentDateTime">
                                        <i class="fa fa-clock me-1"></i> 現在時刻
                                    </button>
                                </label>
                                <input type="text" class="form-control" v-model="parentProject.request_date" id="request_date_picker" placeholder="YYYY/MM/DD HH:mm" autocomplete="off" required>
                                <div v-if="validationErrors.request_date" class="invalid-feedback d-block">
                                    {{ validationErrors.request_date }}
                                </div>
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
                                    <span data-i18n="管理番号">管理番号</span> <span class="text-danger">*</span>
                                    <button class="btn btn-sm btn-outline-primary py-0 small ms-2" @click="generateProjectNumber" title="生成">
                                        生成
                                    </button>
                                </label>
                                <input type="text" class="form-control" v-model="parentProject.project_number" required>
                                <div v-if="validationErrors.project_number" class="invalid-feedback d-block">
                                    {{ validationErrors.project_number }}
                                </div>
                            </div>
                        </div>
                        <!-- <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="工事支店">工事支店</span></label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="text" class="form-control tagify" v-model="parentProject.construction_branch" id="construction_branch_tags" name="construction_branch_tags">
                                    <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearTagifyTags('construction_branch')" title="すべて削除"><i class="fa fa-times"></i></button>
                                </div>
                            </div>
                        </div> -->
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="お施主様名">お施主様名</span> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" v-model="parentProject.project_name" required>
                                <div v-if="validationErrors.project_name" class="invalid-feedback d-block">
                                    {{ validationErrors.project_name }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="建物規模">建物規模</span></label>
                                <input type="text" class="form-control tagify" v-model="parentProject.scale" id="scale_tags" name="scale_tags">
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

                        <!-- <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label">
                                    <span data-i18n="希望納期">希望納期</span> <span class="text-danger">*</span>
                                        <button type="button" class="btn btn-sm btn-outline-primary py-0 small ms-2" @click="setTodayDate">
                                        <i class="fa fa-calendar me-1"></i> 今日
                                    </button>
                                </label>
                                <input type="text" class="form-control" v-model="parentProject.desired_delivery_date" id="desired_delivery_date_picker" placeholder="YYYY/MM/DD" autocomplete="off" required>
                                <div v-if="validationErrors.desired_delivery_date" class="invalid-feedback d-block">
                                    {{ validationErrors.desired_delivery_date }}
                                </div>
                            </div>
                        </div> -->
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="構造事務所">構造事務所</span></label>
                                <input type="text" class="form-control" v-model="parentProject.structural_office">
                            </div>
                        </div>
                        <!-- <div class="col-md-6">
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
                        </div> -->
                        <div class="col-12">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="依頼">依頼</span></label>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="request_design" v-model="parentProject.request_design">
                                            <label class="form-check-label" for="request_design">
                                                意匠
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="request_equipment" v-model="parentProject.request_equipment">
                                            <label class="form-check-label" for="request_equipment">
                                                設備
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="request_energy_saving" v-model="parentProject.request_energy_saving">
                                            <label class="form-check-label" for="request_energy_saving">
                                                省エネ
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="request_3d" v-model="parentProject.request_3d">
                                            <label class="form-check-label" for="request_3d">
                                                3D
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="request_other" v-model="parentProject.request_other">
                                            <label class="form-check-label" for="request_other">
                                                その他
                                            </label>
                                        </div>
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
                        <!-- <div class="col-12">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="備考">備考</span></label>
                                <textarea class="form-control" v-model="parentProject.notes" rows="3" placeholder="備考を入力してください"></textarea>
                            </div>
                        </div> -->
                    </div>
                    <div v-else class="text-center py-5">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <div class="col-12 mt-4">
                        <div class="d-flex justify-content-center">
                            <button class="btn btn-success me-3" @click="saveParentProject">
                                <i class="fa fa-save me-1"></i><span data-i18n="保存">保存</span>
                            </button>
                            <a href="index.php" class="btn btn-secondary" title="キャンセル">
                                <i class="fa fa-times me-1"></i><span data-i18n="キャンセル">キャンセル</span>
                            </a>
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
                        <p class="text-muted mt-2" data-i18n="新しい建物を作成します">新しい建物を作成します</p>
                        <p class="small text-muted" data-i18n="必須項目（*）を入力してから保存してください">必須項目（*）を入力してから保存してください</p>
                    </div>
                </div>
            </div>
        </div>
        
    </div>

    <!-- Customer Info Modal -->
    <div class="modal fade" id="customerInfoModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><span data-i18n="顧客情報">顧客情報</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning mb-3 mb-md-4" role="alert">
                        <i class="fa fa-exclamation-triangle me-2"></i>
                        <span data-i18n="お客様情報を更新すると、このお客様の情報を利用している他の建物の情報もすべて更新されます。">お客様情報を更新すると、このお客様の情報を利用している他の建物の情報もすべて更新されます。</span>
                    </div>
                    <div v-if="selectedCustomer">
                        <form @submit.prevent="updateCustomer">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="カテゴリー">カテゴリー</span></label>
                                    <select class="form-select" v-model="selectedCustomer.category_id" required>
                                        <option value="">選択してください</option>
                                        <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="会社名">会社名</span> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="selectedCustomer.company_name" required>
                                    <div v-if="customerErrors.company_name" class="text-danger small mt-1">{{ customerErrors.company_name }}</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="会社名(ふりがな)">会社名(ふりがな)</span></label>
                                    <input type="text" class="form-control" v-model="selectedCustomer.company_name_kana" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="担当者名">担当者名</span> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="selectedCustomer.name" required>
                                    <div v-if="customerErrors.name" class="text-danger small mt-1">{{ customerErrors.name }}</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="担当者名(ふりがな)">担当者名(ふりがな)</span></label>
                                    <input type="text" class="form-control" v-model="selectedCustomer.name_kana" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="支店名">支店名</span> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="selectedCustomer.branch" required>
                                    <div v-if="customerErrors.branch" class="text-danger small mt-1">{{ customerErrors.branch }}</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="担当部署">担当部署</span></label>
                                    <input type="text" class="form-control" v-model="selectedCustomer.department" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="役職">役職</span></label>
                                    <input type="text" class="form-control" v-model="selectedCustomer.position" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="敬称">敬称</span></label>
                                    <select class="form-select" v-model="selectedCustomer.title" required>
                                        <option>様</option>
                                        <option>御社</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="メールアドレス">メールアドレス</span></label>
                                    <input type="text" class="form-control" v-model="selectedCustomer.email" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="電話番号">電話番号</span></label>
                                    <input type="text" class="form-control" v-model="selectedCustomer.tel" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">FAX</label>
                                    <input type="text" class="form-control" v-model="selectedCustomer.fax" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="携帯番号">携帯番号</span></label>
                                    <input type="text" class="form-control" v-model="selectedCustomer.phone" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="郵便番号">郵便番号</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" v-model="selectedCustomer.zip" required>
                                        <button class="btn btn-outline-primary waves-effect" type="button" @click.prevent="searchAddressSelectedCustomer">住所検索</button>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="住所1">住所1</span></label>
                                    <input type="text" class="form-control" v-model="selectedCustomer.address1" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="住所2">住所2</span></label>
                                    <input type="text" class="form-control" v-model="selectedCustomer.address2" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="状況">状況</span></label>
                                    <select class="form-select" v-model="selectedCustomer.status" required>
                                        <option value="1">有効</option>
                                        <option value="0">無効</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="自社担当部署名">自社担当部署名</span> <span class="text-danger">*</span></label>
                                    <select ref="customerGuisDepartmentSelect" class="form-select select2" v-model="selectedCustomer.guis_department" required multiple>
                                        <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                                    </select>
                                    <div v-if="customerErrors.guis_department" class="text-danger small mt-1">{{ customerErrors.guis_department }}</div>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label"><span data-i18n="メモ">メモ</span></label>
                                    <textarea class="form-control" v-model="selectedCustomer.memo"></textarea>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div v-else class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">読み込み中...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><span data-i18n="閉じる">閉じる</span></button>
                    <button type="button" class="btn btn-primary" @click="updateCustomer" :disabled="updatingCustomer">
                        <span v-if="updatingCustomer" class="spinner-border spinner-border-sm me-1"></span>
                        <span data-i18n="更新">更新</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- New Customer Modal -->
    <div class="modal fade" id="customerModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">新規顧客追加</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="saveCustomer">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">カテゴリー</label>
                                <select class="form-select" v-model="newCustomer.category_id" required>
                                    <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">会社名 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" v-model="newCustomer.company_name" required>
                                <div v-if="customerErrors.company_name" class="text-danger small mt-1">{{ customerErrors.company_name }}</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">会社名(ふりがな)</label>
                                <input type="text" class="form-control" v-model="newCustomer.company_name_kana" required>
                            </div>
                        
                            <div class="col-md-4 mb-3">
                                <label class="form-label">担当者名 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" v-model="newCustomer.name" required>
                                <div v-if="customerErrors.name" class="text-danger small mt-1">{{ customerErrors.name }}</div>
                            </div> 
                            <div class="col-md-4 mb-3">
                                <label class="form-label">担当者名(ふりがな)</label>
                                <input type="text" class="form-control" v-model="newCustomer.name_kana" required>
                            </div>
                        
                            <div class="col-md-4 mb-3">
                                <label class="form-label">支店名</label>
                                <input type="text" class="form-control" v-model="newCustomer.branch" required>
                            </div>
                        
                            <div class="col-md-4 mb-3">
                                <label class="form-label">担当部署</label>
                                <input type="text" class="form-control" v-model="newCustomer.department" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">役職</label>
                                <input type="text" class="form-control" v-model="newCustomer.position" required>
                            </div>
                        
                            <div class="col-md-4 mb-3">
                                <label class="form-label">敬称</label>
                                <select class="form-select" v-model="newCustomer.title" required>
                                    <option>様</option>
                                    <option>御社</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">メールアドレス</label>
                                <input type="text" class="form-control" v-model="newCustomer.email" required>
                            </div>
                        
                            <div class="col-md-4 mb-3">
                                <label class="form-label">電話番号</label>
                                <input type="text" class="form-control" v-model="newCustomer.tel" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">FAX</label>
                                <input type="text" class="form-control" v-model="newCustomer.fax" required>
                            </div>
                        
                            <div class="col-md-4 mb-3">
                                <label class="form-label">携帯番号</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" v-model="newCustomer.phone" required>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">郵便番号</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" v-model="newCustomer.zip" required>
                                    <button class="btn btn-outline-primary waves-effect" type="button" @click.prevent="searchAddressCustomer">住所検索</button>
                                </div>
                            </div>
                        
                            <div class="col-md-4 mb-3">
                                <label class="form-label">住所1</label>
                                <input type="text" class="form-control" v-model="newCustomer.address1" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">住所2</label>
                                <input type="text" class="form-control" v-model="newCustomer.address2" required>
                            </div>
                        
                            <div class="col-md-4 mb-3">
                                <label class="form-label">状況</label>
                                <select class="form-select" v-model="newCustomer.status" required>
                                    <option value="1">有効</option>
                                    <option value="0">無効</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">自社担当部署名 <span class="text-danger">*</span></label>
                                <select ref="guisDepartmentSelect" class="form-select select2" v-model="newCustomer.guis_department" required multiple>
                                    <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                                </select>
                                <div v-if="customerErrors.guis_department" class="text-danger small mt-1">{{ customerErrors.guis_department }}</div>
                            </div>
                        
                            <!-- <div class="col-md-12 mb-3">
                                <label class="form-label">メモ</label>
                                <textarea class="form-control" v-model="newCustomer.memo" required></textarea>
                            </div> -->
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-primary" @click="saveCustomer">保存</button>
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

<script>
// Pass current user data to JavaScript
window.currentUser = {
    id: <?= json_encode($_SESSION['userid'] ?? '') ?>,
    name: <?= json_encode($_SESSION['realname'] ?? '') ?>,
    username: <?= json_encode($_SESSION['username'] ?? '') ?>
};
</script>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="assets/js/parent-project-create.js?v=<?=CACHE_VERSION?>"></script> 