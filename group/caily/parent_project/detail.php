<?php
require_once('../application/loader.php');
$isCailyBranchUser = false;
try {
    require_once('../application/model/branch.php');
    $branchModel = new Branch();
    $branch = $branchModel->get_user_branch_name();
    if ($branch && isset($branch['name']) && $branch['name'] === 'CAILY') {
        $isCailyBranchUser = true;
    }
} catch (Exception $e) {
    // keep false
}
$parent_project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$parent_project_id) {
    header('Location: index.php');
    exit;
}
if(!$_SESSION['isProjectManager'] && $_GET['edit'] == 1){
    die('権限がありません。');
}
$view->heading('建物詳細');
?>
<div id="app" class="container-fluid mt-4" v-cloak>

    <div class="row">
        <!-- Back button -->
        <!-- <div class="col-12 mb-3">
            <a href="index.php" class="btn btn-outline-primary">
                <i class="fa fa-arrow-left me-1"></i> <span data-i18n="建物一覧へ戻る">建物一覧へ戻る</span>
            </a>
        </div> -->

        <!-- Navigation Bar -->
        <div class="col-12 mb-3">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <a class="navbar-brand fw-bold" href="#" v-if="parentProject">
                        <span class="badge badge-sm bg-label-info">#{{ parentProject.project_number || 'N/A' }}</span>
                    </a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#parentProjectNavbar" aria-controls="parentProjectNavbar" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="parentProjectNavbar">
                        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                            <li class="nav-item">
                                <a class="nav-link active" aria-current="page" href="detail.php?id=<?php echo $parent_project_id; ?>"><span data-i18n="建物詳細">建物詳細</span></a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="attachment.php?id=<?php echo $parent_project_id; ?>"><span data-i18n="添付ファイル">添付ファイル</span></a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
        </div>

        <!-- Left Column - Parent Project Details -->
        <div class="col-12 mb-3">
            <div class="card" :class="{ 'edit-mode': isEditMode }">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa fa-star" 
                               :class="parentProject && parentProject.is_favorite == 1 ? 'text-warning' : 'text-muted'"
                               style="cursor: pointer; font-size: 1.3em;"
                               @click="toggleFavorite"
                               :title="parentProject && parentProject.is_favorite == 1 ? 'お気に入りから削除' : 'お気に入りに追加'"></i>
                            <h5 class="card-title mb-0"><span data-i18n="建物詳細">建物詳細</span></h5>
                        </div>
                        <div v-if="isProjectManager">
                            <button v-if="!isEditMode" class="btn btn-outline-warning btn-sm me-2"
                                @click="toggleEditMode" title="編集">
                                <i class="fa fa-pencil-alt me-1"></i><span data-i18n="編集">編集</span>
                            </button>
                            <button v-if="isEditMode" class="btn btn-success btn-sm me-2" @click="saveParentProject"
                                title="保存">
                                <i class="fa fa-save me-1"></i><span data-i18n="保存">保存</span>
                            </button>
                            <button v-if="isEditMode" class="btn btn-secondary btn-sm me-2" @click="cancelEdit"
                                title="キャンセル">
                                <i class="fa fa-times me-1"></i><span data-i18n="キャンセル">キャンセル</span>
                            </button>
                            <button v-if="!isEditMode" class="btn btn-outline-info btn-sm me-2"
                                @click="showLogs" title="アクティビティログ">
                                <i class="fa fa-history me-1"></i><span data-i18n="アクティビティログ">アクティビティログ</span>
                            </button>
                            <!-- <button v-if="!isEditMode && childProjects.length == 0" class="btn btn-outline-danger btn-sm"
                                @click="deleteParentProject" title="削除">
                                <i class="fa fa-trash me-1"></i><span data-i18n="削除">削除</span>
                            </button> -->
                        </div>
                    </div>
                    <div v-if="parentProject" class="row g-3">
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="会社名">会社名</span> <span
                                        class="text-danger">*</span></label>
                                <template v-if="isEditMode">
                                    <select id="company_name" class="form-select select2"
                                        v-model="parentProject.company_name" name="company_name" required
                                        @change="onCompanyChange">
                                        <option value="">選択してください</option>
                                        <option v-for="company in companies" :key="company.company_name"
                                            :value="company.company_name">
                                            {{ company.company_name }}
                                        </option>
                                    </select>
                                    <div v-if="validationErrors.company_name" class="invalid-feedback d-block">
                                        {{ validationErrors.company_name }}
                                    </div>
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control" :value="customerDisplay.company_name || parentProject.company_name"
                                        readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="支店名">支店名</span></label>
                                <template v-if="isEditMode">
                                    <select id="branch_name" class="form-select select2"
                                        v-model="parentProject.branch_name" name="branch_name" @change="onBranchChange">
                                        <option value="">選択してください</option>
                                        <option v-for="branch in branches" :key="branch.branch" :value="branch.branch">
                                            {{ branch.branch }}
                                        </option>
                                    </select>
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control" :value="customerDisplay.branch_name || parentProject.branch_name || '-'"
                                        readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label">
                                    <span data-i18n="担当様">担当様</span>
                                    <button v-if="isEditMode" type="button" class="btn btn-sm btn-outline-primary py-0 small ms-2" @click="openNewCustomerModal" title="新規顧客追加">
                                        <i class="fa fa-plus me-1"></i> 新規顧客
                                    </button>
                                    <button v-if="parentProject.contact_name || parentProject.customer_id" type="button"
                                        class="btn btn-sm btn-outline-info py-0 small ms-2" @click="openCustomerInfoModal"
                                        title="顧客情報表示・編集">
                                        <i class="fa fa-info-circle me-1"></i> <span data-i18n="顧客情報">顧客情報</span>
                                    </button>
                                </label>
                                <template v-if="isEditMode">
                                    <select id="contact_name" class="form-select select2"
                                        v-model="parentProject.contact_name" name="contact_name">
                                        <option value="">選択してください</option>
                                        <option v-for="contact in contacts" :key="contact.id" :value="contact.name">
                                            {{ contact.name }}
                                        </option>
                                    </select>
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control" :value="customerDisplay.contact_name || parentProject.contact_name || '-'"
                                        readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="GUIS受付者">GUIS受付者</span></label>
                                <template v-if="isEditMode">
                                    <select id="guis_receiver" class="form-select select2"
                                        v-model="parentProject.guis_receiver" name="guis_receiver">
                                        <option value="">選択してください</option>
                                        <option v-for="user in users" :key="user.id" :value="user.user_name">
                                            {{ user.user_name }}
                                        </option>
                                    </select>
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control" :value="guisReceiverDisplayName || '-'"
                                        readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="お施主様名">お施主様名</span> <span
                                        class="text-danger">*</span></label>
                                <template v-if="isEditMode">
                                    <input type="text" class="form-control" v-model="parentProject.project_name"
                                        placeholder="案件名を入力" required>
                                    <div v-if="validationErrors.project_name" class="invalid-feedback d-block">
                                        {{ validationErrors.project_name }}
                                    </div>
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control" :value="parentProject.project_name"
                                        readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="依頼日">依頼日</span></label>
                                <template v-if="isEditMode">
                                    <div class="input-group">
                                        <input type="text" class="form-control" v-model="parentProject.request_date"
                                            id="request_date_picker" placeholder="YYYY/MM/DD HH:mm" autocomplete="off">
                                        <button class="btn btn-outline-secondary" type="button"
                                            @click="setCurrentDateTime" title="現在時刻">
                                            現在時刻
                                        </button>
                                    </div>
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control"
                                        :value="formatDateTime(parentProject.request_date)" readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label">
                                    <span data-i18n="管理番号">管理番号</span>
                                </label>
                                <input type="text" class="form-control" :value="parentProject.project_number || '-'"
                                    readonly>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="工事番号">工事番号</span></label>
                                <template v-if="isEditMode">
                                    <input type="text" class="form-control" v-model="parentProject.construction_number"
                                        placeholder="工事番号を入力">
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control"
                                        :value="parentProject.construction_number || '-'" readonly>
                                </template>
                            </div>
                        </div>
                        
                        
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="建物規模">建物規模</span></label>
                                <template v-if="isEditMode">
                                    <input type="text" class="form-control" v-model="parentProject.scale"
                                        placeholder="規模を入力">
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control" :value="parentProject.scale || '-'"
                                        readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="構造事務所">構造事務所</span></label>
                                <template v-if="isEditMode">
                                    <input type="text" class="form-control" v-model="parentProject.structural_office"
                                        placeholder="構造事務所を入力">
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control"
                                        :value="parentProject.structural_office || '-'" readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="種類1">種類1</span></label>
                                <template v-if="isEditMode">
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control tagify" v-model="parentProject.type1"
                                            id="type1_tags" name="type1_tags">
                                        <button class="btn btn-outline-secondary btn-sm" type="button"
                                            @click="clearTagifyTags('type1')" title="すべて削除"><i
                                                class="fa fa-times"></i></button>
                                    </div>
                                </template>
                                <template v-else>
                                    <div style="min-height:38px;">
                                        <span v-if="parentProject.type1 && parentProject.type1.split(',').length > 0">
                                            <span v-for="item in parentProject.type1.split(',')" :key="item.trim()"
                                                class="badge bg-primary me-1">{{ item.trim() }}</span>
                                        </span>
                                        <span v-else>-</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="種類2">種類2</span></label>
                                <template v-if="isEditMode">
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control tagify" v-model="parentProject.type2"
                                            id="type2_tags" name="type2_tags">
                                        <button class="btn btn-outline-secondary btn-sm" type="button"
                                            @click="clearTagifyTags('type2')" title="すべて削除"><i
                                                class="fa fa-times"></i></button>
                                    </div>
                                </template>
                                <template v-else>
                                    <div style="min-height:38px;">
                                        <span v-if="parentProject.type2 && parentProject.type2.split(',').length > 0">
                                            <span v-for="item in parentProject.type2.split(',')" :key="item.trim()"
                                                class="badge bg-primary me-1">{{ item.trim() }}</span>
                                        </span>
                                        <span v-else>-</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="依頼">依頼</span></label>
                                <template v-if="isEditMode">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="request_design"
                                                    v-model="request_design">
                                                <label class="form-check-label" for="request_design">
                                                    意匠
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="request_equipment"
                                                    v-model="request_equipment">
                                                <label class="form-check-label" for="request_equipment">
                                                    設備
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="request_3d_equipment"
                                                    v-model="request_3d_equipment">
                                                <label class="form-check-label" for="request_3d_equipment">
                                                    3D設備
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="request_energy_saving"
                                                    v-model="request_energy_saving">
                                                <label class="form-check-label" for="request_energy_saving">
                                                    省エネ
                                                </label>
                                            </div>
                                        </div>
                                        <!-- <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="request_3d"
                                                    v-model="request_3d">
                                                <label class="form-check-label" for="request_3d">
                                                    3D
                                                </label>
                                            </div>
                                        </div> -->
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="form-control-plaintext">
                                        <div class="d-flex flex-wrap gap-1 align-items-center" v-if="parentRequestTypes.length">
                                            <span v-for="item in parentRequestTypes" :key="item"
                                                  class="badge me-1"
                                                  :class="isParentRequestFulfilled(item) ? getParentRequestBadgeClass(item) : 'bg-warning text-dark'"
                                                  :title="isParentRequestFulfilled(item) ? '' : '未作成'">
                                                <i v-if="!isParentRequestFulfilled(item)" class="fa fa-exclamation-triangle me-1"></i>
                                                <i v-else class="fa fa-check me-1"></i>
                                                {{ item }}
                                            </span>
                                        </div>
                                        <span v-else class="text-muted">-</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="資料">資料</span></label>
                                <template v-if="isEditMode">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="materials_layout"
                                                    v-model="materials_layout">
                                                <label class="form-check-label" for="materials_layout">
                                                    配置図
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="materials_rental"
                                                    v-model="materials_rental">
                                                <label class="form-check-label" for="materials_rental">
                                                    家賃審査書
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="materials_contract"
                                                    v-model="materials_contract">
                                                <label class="form-check-label" for="materials_contract">
                                                    契約図
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="materials_tac"
                                                    v-model="materials_tac">
                                                <label class="form-check-label" for="materials_tac">
                                                    TAC図
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="materials_other"
                                                    v-model="materials_other">
                                                <label class="form-check-label" for="materials_other">
                                                    その他
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="form-control-plaintext">
                                        <div class="row">
                                            <div class="col-md-4"
                                                v-if="parentProject.materials && parentProject.materials.includes('配置図')">
                                                <i class="fa fa-check text-success me-2"></i>配置図
                                            </div>
                                            <div class="col-md-4"
                                                v-if="parentProject.materials && parentProject.materials.includes('家賃審査書')">
                                                <i class="fa fa-check text-success me-2"></i>家賃審査書
                                            </div>
                                            <div class="col-md-4"
                                                v-if="parentProject.materials && parentProject.materials.includes('契約図')">
                                                <i class="fa fa-check text-success me-2"></i>契約図
                                            </div>
                                            <div class="col-md-4"
                                                v-if="parentProject.materials && parentProject.materials.includes('TAC図')">
                                                <i class="fa fa-check text-success me-2"></i>TAC図
                                            </div>
                                            <div class="col-md-4"
                                                v-if="parentProject.materials && parentProject.materials.includes('その他')">
                                                <i class="fa fa-check text-success me-2"></i>その他
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <!-- <div class="col-12">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="備考">備考</span></label>
                                <template v-if="isEditMode">
                                    <textarea class="form-control" v-model="parentProject.notes" rows="3"
                                        placeholder="備考を入力してください"></textarea>
                                </template>
                                <template v-else>
                                    <div class="form-control-plaintext" style="white-space: pre-wrap;">{{
                                        parentProject.notes || '-' }}</div>
                                </template>
                            </div>
                        </div> -->
                    </div>
                    <div v-else class="text-center py-5">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- メモ (Notes) - below 備考 -->
            <div class="card mb-4 mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><span data-i18n="メモ">メモ</span></h5>
                    <button v-if="canAddNote" class="btn btn-primary btn-sm" @click="openNoteModal()" title="メモを追加">
                        <i class="fa fa-plus me-1"></i> <span data-i18n="メモを追加">メモを追加</span>
                    </button>
                </div>
                <div class="card-body">
                    <div v-if="notes.length > 0" class="list-group">
                        <div v-for="note in notes" :key="note.id" class="list-group-item d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1" style="cursor: pointer;" @click="openNoteModal(note)">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <small class="text-secondary">
                                        {{ formatShortDateTime(note.created_at) }} - {{ note.realname || 'Unknown' }}
                                    </small>
                                    <div class="ms-2">
                                        <i v-if="note.is_important == 1" class="fa fa-exclamation-circle text-danger me-2"></i>
                                    </div>
                                </div>
                                <div v-if="note.content" class="text-muted" style="white-space: pre-line; word-break: break-word;">
                                    {{ note.content }}
                                </div>
                            </div>
                            <button class="btn btn-outline-danger btn-sm ms-2"
                                    @click="deleteNote(note.id)"
                                    title="削除"
                                    v-if="canDeleteNote(note)">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div v-else class="text-center text-muted py-3">
                        <i class="fa fa-sticky-note fa-2x mb-2"></i>
                        <p><span data-i18n="メモがありません">メモがありません</span></p>
                        <button v-if="canAddNote" class="btn btn-outline-primary btn-sm" @click="openNoteModal()">
                            <span data-i18n="最初のメモを追加">最初のメモを追加</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Child Projects -->
            <div class="card mt-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><span data-i18n="案件依頼">案件依頼</span></h5>
                        <div v-if="canAddProject">
                            <button @click="showCreateChildProjectModal" class="btn btn-success btn-sm">
                                <i class="fa fa-plus me-1"></i> <span data-i18n="案件依頼作成">案件依頼作成</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div v-if="childProjects.length > 0" class="table-responsive">
                        <table class="table table-hover table-sm" id="childProjectsTable">
                            <thead>
                                <tr>
                                    <th class="text-center"><i class="fa fa-star text-muted" title="お気に入り"></i></th>
                                    <th><span data-i18n="ID">ID</span></th>
                                    <th><span data-i18n="受注形態">受注形態</span></th>
                                    <th style="width: 120px;"><span data-i18n="案件名">案件名</span></th>
                                    <th style="min-width: 150px;"><span data-i18n="説明">説明</span></th>
                                    <th style="min-width: 100px;"><span data-i18n="顧客情報">顧客情報</span></th>
                                    <th style="width: 60px;"><span data-i18n="GUIS受付者">GUIS受付者</span></th>
                                    <th style="min-width: 80px;"><span data-i18n="部署">部署</span></th>
                                    <th><span data-i18n="管理">管理</span></th>
                                    <th><span>担当</span></th>
                                    <th style="width: 90px;"><span data-i18n="予定工程">予定工程</span></th>
                                    <th style="width: 60px;"><span data-i18n="開始日">開始日</span></th>
                                    <th style="width: 60px;"><span>CAILY納期</span></th>
                                    <th v-if="!isCailyBranchUser" style="width: 60px;"><span>GUIS納期</span></th>
                                    <th v-if="!isCailyBranchUser"><span data-i18n="期限日">期限日</span></th>
                                    <th style="width: 60px;"><span data-i18n="ステータス">ステータス</span></th>
                                    <th style="width: 60px;"><span data-i18n="進捗">進捗</span></th>
                                    <th v-if="canViewBusinessDocuments" style="width: 140px;"><span data-i18n="決済情報">決済情報</span></th>
                                    <th><span data-i18n="操作">操作</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="project in childProjects" :key="project.id"
                                    :class="{ 'table-active': selectedChildProjectIds.includes(project.id) }"
                                    @contextmenu.prevent="onChildProjectContextMenu($event, project)">
                                    <td class="text-center">
                                        <i class="fa fa-star" 
                                           :class="project.is_favorite == 1 ? 'text-warning' : 'text-muted'"
                                           style="cursor: pointer;"
                                           @click="toggleProjectFavorite(project)"
                                           :title="project.is_favorite == 1 ? 'お気に入りから削除' : 'お気に入りに追加'"></i>
                                    </td>
                                    <td><a :href="'../project/detail.php?id=' + project.id" class="text-decoration-none"><span class="badge bg-primary border me-1">{{ project.id || '-' }}</span></a></td>
                                    <td>
                                        <span
                                            v-if="project.project_order_type && project.project_order_type.split(',').length > 0">
                                            <span v-for="item in project.project_order_type.split(',')"
                                                :key="item.trim()" class="badge me-1" :class="getOrderTypeBadgeClass(item.trim())">{{ item.trim() }}</span>
                                        </span>
                                        <span v-else>-</span>
                                    </td>
                                    <td style="min-width: 150px;">
                                        <a :href="'../project/detail.php?id=' + project.id"
                                            class="text-decoration-none">
                                            {{ project.name }}
                                        </a>
                                        
                                        <div v-if="mapDepartmentNameToRequestType(project.department_name)" class="mt-1">
                                            <span class="badge" :class="getParentRequestBadgeClass(mapDepartmentNameToRequestType(project.department_name))">
                                                {{ mapDepartmentNameToRequestType(project.department_name) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td style="min-width: 150px; max-width: 250px;">
                                        <span :class="{ 'text-muted': !project.description }"
                                              :title="project.description ? getDescriptionPlainText(project.description) : ''">
                                            {{ formatDescriptionPreview(project.description) }}
                                        </span>
                                    </td>
                                    <td style="min-width: 160px; max-width: 220px;" :title="shouldShowChildProjectCustomer(project) ? formatChildProjectCustomerLabel(project) : ''">
                                        <template v-if="shouldShowChildProjectCustomer(project)">
                                            <span class="d-block small">{{ getChildProjectCustomerDisplay(project).company_name }}</span>
                                            <span v-if="getChildProjectCustomerDisplay(project).branch_name !== '-'" class="d-block small text-muted">{{ getChildProjectCustomerDisplay(project).branch_name }}</span>
                                            <span v-if="getChildProjectCustomerDisplay(project).contact_name !== '-'" class="d-block small text-muted">{{ getChildProjectCustomerDisplay(project).contact_name }}</span>
                                            <button type="button" class="btn btn-sm btn-outline-info py-0 px-1 mt-1"
                                                @click.stop="openChildProjectCustomerInfoModal(project)"
                                                title="顧客情報表示・編集">
                                                <i class="fa fa-info-circle me-1"></i> <span data-i18n="顧客情報">顧客情報</span>
                                            </button>
                                        </template>
                                        <span v-else class="text-muted">-</span>
                                    </td>
                                    <td style="min-width: 120px;">{{ getChildProjectGuisReceiverDisplay(project) }}</td>
                                    <td style="min-width: 100px;">{{ project.department_name || '-' }}</td>
                                    <td>
                                        <div class="d-flex align-items-center" v-if="project.manager_id && project.manager_id.split('|').filter(m => m.trim() !== '').length > 0">
                                            <template v-for="(manager, index) in project.manager_id.split('|').filter(m => m.trim() !== '')" :key="manager">
                                                <div v-if="index < 1" 
                                                    class="avatar avatar-sm me-1"
                                                    data-bs-toggle="tooltip"
                                                    :title="getManagerName(manager)">
                                                    <span class="avatar-initial rounded-circle bg-label-primary pull-up">
                                                        {{ getManagerInitials(manager) }}
                                                    </span>
                                                    <img v-if="getManagerImage(manager)" 
                                                        :src="'/assets/upload/avatar/' + getManagerImage(manager)" 
                                                        alt="avatar" 
                                                        class="rounded-circle pull-up"
                                                        style="display:none;"
                                                        @load="$event.target.style.display='block'; if ($event.target.previousElementSibling) $event.target.previousElementSibling.style.display='none';"
                                                        @error="$event.target.remove()">
                                                </div>
                                            </template>
                                            <span v-if="project.manager_id.split('|').filter(m => m.trim() !== '').length > 1" 
                                                class="avatar-initial rounded-circle bg-label-primary pull-up" 
                                                data-bs-toggle="tooltip" 
                                                :title="getRemainingManagers(project.manager_id)"
                                                style="display:inline-flex;">
                                                +{{ project.manager_id.split('|').filter(m => m.trim() !== '').length - 1 }}
                                            </span>
                                        </div>
                                        <span v-else class="text-muted">-</span>
                                    </td>
                                    <td>{{ project.tantou || '-' }}</td>
                                    <td>
                                        <span v-if="formatYoteiDisplay(project.yotei)">{{ formatYoteiDisplay(project.yotei) }}</span>
                                        <span v-else class="text-muted">-</span>
                                    </td>
                                    <td>
                                        <span v-if="project.start_date" :data-time="project.start_date" data-bs-toggle="tooltip" :data-bs-title="getVietnamTimeTooltip(project.start_date)">
                                            <span class="d-block">{{ formatDateTimeDatePart(project.start_date) }}</span>
                                            <span class="d-block">{{ formatDateTimeTimePart(project.start_date) }}</span>
                                        </span>
                                        <span v-else class="text-muted">-</span>
                                    </td>
                                    <td>
                                        <span v-if="project.caily_nouki" :data-time="project.caily_nouki" data-bs-toggle="tooltip" :data-bs-title="getVietnamTimeTooltip(project.caily_nouki)">
                                            <span class="d-block">{{ formatDateTimeDatePart(project.caily_nouki) }}</span>
                                            <span class="d-block">{{ formatDateTimeTimePart(project.caily_nouki) }}</span>
                                        </span>
                                        <span v-else class="text-muted">-</span>
                                    </td>
                                    <td v-if="!isCailyBranchUser">
                                        <span v-if="project.guis_nouki" :data-time="project.guis_nouki" data-bs-toggle="tooltip" :data-bs-title="getVietnamTimeTooltip(project.guis_nouki)">
                                            <span class="d-block">{{ formatDateTimeDatePart(project.guis_nouki) }}</span>
                                            <span class="d-block">{{ formatDateTimeTimePart(project.guis_nouki) }}</span>
                                        </span>
                                        <span v-else class="text-muted">-</span>
                                    </td>
                                   
                                    <td v-if="!isCailyBranchUser">
                                        <span v-if="project.end_date" :data-time="project.end_date" data-bs-toggle="tooltip" :data-bs-title="getVietnamTimeTooltip(project.end_date)">
                                            <span class="d-block">{{ formatDateTimeDatePart(project.end_date) }}</span>
                                            <span class="d-block">{{ formatDateTimeTimePart(project.end_date) }}</span>
                                        </span>
                                        <span v-else class="text-muted">-</span>
                                    </td>
                                    <td>
                                        <span class="badge" :class="getProjectStatusBadgeClass(project.status)">
                                            {{ getProjectStatusLabel(project.status) }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold">{{ project.progress }}%</span>
                                    </td>
                                    <td v-if="canViewBusinessDocuments" style="min-width: 200px;">
                                        <div v-for="line in getChildProjectPaymentLines(project)" :key="line.key"
                                             class="d-flex justify-content-between align-items-center gap-2 small mb-1">
                                            <span class="d-flex align-items-center gap-1 flex-wrap">
                                                <span class="text-muted">{{ line.label }}</span>
                                                <span class="badge" :class="line.badgeClass">{{ line.statusLabel }}</span>
                                            </span>
                                            <span class="fw-semibold text-nowrap">{{ formatPrice(line.amount) }}</span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex flex-column gap-1 align-items-center">
                                            <div class="d-flex gap-1">
                                                <a :href="'../project/detail.php?id=' + project.id" class="btn btn-sm btn-outline-primary"
                                                   title="詳細を表示">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                                <button v-if="canEditBusinessDocuments" type="button" class="btn btn-sm btn-outline-success"
                                                        title="決済情報" @click="openBusinessDocumentModal(project)">
                                                    <i class="fa fa-money-bill-wave"></i>
                                                </button>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="fa fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li v-if="canEditBusinessDocuments">
                                                        <a class="dropdown-item" href="javascript:void(0);" @click.prevent="openBusinessDocumentModal(project)">
                                                            <i class="fa fa-file-invoice me-1"></i> <span data-i18n="決済情報">決済情報</span>
                                                        </a>
                                                    </li>
                                                    <li v-if="canEditChildProject(project)">
                                                        <a class="dropdown-item" href="javascript:void(0);" @click.prevent="showEditChildProjectModal(project)">
                                                            <i class="fa fa-edit me-1"></i> 編集
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="javascript:void(0);" @click.prevent="showChildProjectLogs(project)">
                                                            <i class="fa fa-history me-1"></i> ログ
                                                        </a>
                                                    </li>
                                                    <li v-if="canDeleteChildProject(project) && project.status !== 'cancelled'">
                                                        <a class="dropdown-item text-danger" href="javascript:void(0);" @click.prevent="cancelChildProject(project)">
                                                            <i class="fa fa-trash me-1"></i> 削除
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="childProjects.length === 0">
                                    <td colspan="19" class="text-center text-muted py-4">
                                        案件依頼がありません
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-4">
                        <div class="text-muted">
                            <i class="fa fa-folder-open fa-2x mb-2"></i>
                            <p><span data-i18n="案件依頼がありません">案件依頼がありません</span></p>
                            <button @click="showCreateChildProjectModal" class="btn btn-primary btn-sm" v-if="isProjectManager">
                                <i class="fa fa-plus me-1"></i> <span data-i18n="案件依頼作成">案件依頼作成</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Task workload by department -->
            <div class="card mt-4" v-if="childProjects.length > 0">
                <div class="card-header pb-0">
                    <h5 class="card-title mb-0"><span data-i18n="部署別種別工数">部署別種別工数</span></h5>
                </div>
                <div class="card-body">
                    <div v-if="loadingWorkloadStats" class="text-center py-3">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden"><span data-i18n="読み込み中">読み込み中</span>...</span>
                        </div>
                    </div>
                    <template v-else-if="workloadStatsByDepartment.length">
                        <ul class="nav nav-tabs mb-3" role="tablist">
                            <li v-for="dept in workloadStatsByDepartment" :key="dept.department_id" class="nav-item" role="presentation">
                                <button type="button"
                                        class="nav-link"
                                        :class="{ active: activeWorkloadDeptId === dept.department_id }"
                                        @click="activeWorkloadDeptId = dept.department_id">
                                    {{ dept.department_name }}
                                </button>
                            </li>
                        </ul>
                        <div v-if="activeWorkloadDept">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-2 border-bottom">
                                <small class="text-muted">
                                    <span data-i18n="案件依頼">案件依頼</span> {{ activeWorkloadDept.projectCount }}<span data-i18n="件">件</span>
                                    · <span data-i18n="タスク">タスク</span> {{ activeWorkloadDept.taskCount }}<span data-i18n="件">件</span>
                                </small>
                                <span class="fw-semibold">
                                    <span data-i18n="工数合計">工数合計</span>: {{ formatTotalWorkload(activeWorkloadDept.totalWorkload) }}
                                </span>
                            </div>
                            <div v-if="activeWorkloadDept.byKind.length" class="row">
                                <div v-if="activeWorkloadDept.byKind.some(item => item.hours > 0)" class="col-lg-6 mb-3 mb-lg-0">
                                    <div id="workload-dept-chart-active" style="min-height: 300px;"></div>
                                </div>
                                <div :class="activeWorkloadDept.byKind.some(item => item.hours > 0) ? 'col-lg-6' : 'col-12'">
                                    <div v-for="(item, idx) in activeWorkloadDept.byKind" :key="item.kind"
                                         class="d-flex justify-content-between align-items-center py-2"
                                         :class="{ 'border-bottom': idx < activeWorkloadDept.byKind.length - 1 }">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge" :class="getTaskKindBadgeClass(item.kind)">{{ getTaskKindLabel(item.kind) }}</span>
                                            <small class="text-muted">{{ item.count }}<span data-i18n="件">件</span></small>
                                        </div>
                                        <span class="fw-semibold text-nowrap">
                                            {{ formatTotalWorkload(item.hours) }}
                                            <small class="text-muted ms-1">({{ formatWorkloadPercent(item.hours, activeWorkloadDept.totalWorkload) }})</small>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center text-muted py-3">
                                <span data-i18n="タスクがありません">タスクがありません</span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Right Column - Quotations -->
        <!--<div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><span data-i18n="見積書">見積書</span></h5>
                        <button @click="showCreateQuotationModal" class="btn btn-primary btn-sm" v-if="canAddQuotation">
                            <i class="fa fa-plus me-1"></i> <span data-i18n="新規見積書">新規見積書</span>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div v-if="loading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden"><span data-i18n="読み込み中">読み込み中</span>...</span>
                        </div>
                    </div>
                    <div v-else-if="quotations && quotations.length > 0" class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><span data-i18n="案件番号">案件番号</span></th>
                                    <th><span data-i18n="見積番号">見積番号</span></th>
                                    <th><span data-i18n="件名">件名</span></th>
                                    <th><span data-i18n="作成日">作成日</span></th>
                                    <th><span data-i18n="金額">金額</span></th>
                                    <th><span data-i18n="ステータス">ステータス</span></th>
                                    <th><span data-i18n="更新者">更新者</span></th>
                                    <th><span data-i18n="操作">操作</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="quotation in quotations" :key="quotation.id">
                                    <td>
                                        <div v-if="getQuotationProjectNumbers(quotation).length > 0">
                                            <span v-for="projectNumber in getQuotationProjectNumbers(quotation)" 
                                                  :key="projectNumber" 
                                                  class="badge bg-primary border me-1">
                                                {{ projectNumber }}
                                            </span>
                                        </div>
                                        <span v-else class="text-muted">-</span>
                                    </td>
                                    <td>{{ quotation.quotation_number || '-' }}</td>
                                    <td>{{ quotation.subject || '-' }}</td>
                                    <td>{{ formatDateTime(quotation.created_at) }}</td>
                                    <td>{{ formatPrice(quotation.total_with_tax) }}</td>
                                    <td>
                                        <div v-if="isProjectManager" class="dropdown quotation-status-dropdown">
                                            <button class="btn btn-sm dropdown-toggle" 
                                                :class="getStatusButtonClass(quotation.status)"
                                                type="button" 
                                                data-bs-toggle="dropdown" 
                                                aria-expanded="false"
                                                style="min-width: 120px; text-align: left;">
                                                    {{ quotation.status }}
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" @click="updateQuotationStatus(quotation.id, '下書き')">
                                                    <span class="badge bg-draft me-2">下書き</span>
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" @click="updateQuotationStatus(quotation.id, '発行済み')">
                                                    <span class="badge bg-primary me-2">発行済み</span>
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" @click="updateQuotationStatus(quotation.id, '承認済み')">
                                                    <span class="badge bg-approved me-2">承認済み</span>
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" @click="updateQuotationStatus(quotation.id, '却下')">
                                                    <span class="badge bg-rejected me-2">却下</span>
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" @click="updateQuotationStatus(quotation.id, '調整')">
                                                    <span class="badge bg-adjustment me-2">調整</span>
                                                </a></li>
                                                <li><a class="dropdown-item" href="#" @click="updateQuotationStatus(quotation.id, 'キャンセル')">
                                                    <span class="badge bg-danger me-2">キャンセル</span>
                                                </a></li>
                                            </ul>
                                        </div>
                                        <span v-else class="badge" :class="getQuotationStatusBadgeClass(quotation.status)">{{ quotation.status }}</span>
                                    </td>
                                    <td>
                                        <span v-if="quotation.updated_by" 
                                              class=""
                                              :title="'最終更新者: ' + quotation.updated_by + (quotation.updated_at ? ' (' + formatDateTime(quotation.updated_at) + ')' : '')">
                                            {{ quotation.updated_by }}
                                        </span>
                                        <span v-else class="text-muted">-</span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button v-if="canAddQuotation" class="btn btn-outline-primary" title="表示"
                                                @click="showQuotationModal(quotation)">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                            <button v-if="canAddQuotation" class="btn btn-outline-secondary" title="編集"
                                                @click="editQuotation(quotation)">
                                                <i class="fa fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-info" title="履歴"
                                                @click="showQuotationHistory(quotation)">
                                                <i class="fa fa-history"></i>
                                            </button>
                                            <button v-if="canAddQuotation" class="btn btn-outline-danger" title="削除"
                                                @click="deleteQuotation(quotation)">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else-if="!loading" class="text-center py-4">
                        <div class="text-muted">
                            <i class="fa fa-file-text fa-2x mb-2"></i>
                            <p><span data-i18n="見積書がありません">見積書がありません</span></p>
                            <button @click="showCreateQuotationModal" class="btn btn-primary btn-sm" v-if="isProjectManager">
                                <i class="fa fa-plus me-1"></i> <span data-i18n="見積書作成">見積書作成</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>-->

    </div>

    <!-- Activity Logs Modal -->
    <div class="modal fade" id="logsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">履歴</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div v-if="logs.length > 0">
                        <ul class="list-group" style="max-height: 400px; overflow-y: auto;">
                            <li v-for="log in logs" :key="log.id" class="list-group-item">
                                <div class="d-flex">
                                    <div class="d-flex flex-row align-items-start justify-content-start me-3" style="min-width:130px;">
                                        <div class="d-flex flex-column align-items-center justify-content-start" style="width:40px;">
                                            <div class="avatar avatar-sm">
                                                <span class="avatar-initial rounded-circle bg-label-primary">
                                                    {{ getInitials(log.username ? log.username : (log.realname ? log.realname : '?')) }}
                                                </span>
                                                <img v-if="log.user_image" :src="'/assets/upload/avatar/' + log.user_image" alt="avatar" class="rounded-circle" style="display:none;" @load="$event.target.style.display='block'; if ($event.target.previousElementSibling) $event.target.previousElementSibling.style.display='none';" @error="$event.target.remove()">
                                            </div>
                                        </div>
                                        <div class="d-flex flex-column align-items-start justify-content-center ms-2">
                                            <span class="fw-bold small">{{ log.username || log.realname || log.user }}</span>
                                            <span class="text-muted small">{{ formatShortDateTime(log.time) }}</span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 d-flex align-items-center">
                                        <span>
                                            <i :class="historyIcon(log.action) + ' me-2'"></i>
                                            <span class="me-2">{{ getLogNote(log) }}</span>
                                            <br>
                                            <span v-if="log.value1" :class="getLogBadgeClass(log, 'value1')" class="mx-1">{{ getLogBadgeLabel(log, 'value1') }}</span>
                                            <span v-if="log.value1 && log.value2" class="mx-1">→</span>
                                            <span v-if="log.value2" :class="getLogBadgeClass(log, 'value2')" class="mx-1">{{ getLogBadgeLabel(log, 'value2') }}</span>
                                        </span>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <div v-else class="text-center text-muted py-3">
                        <i class="fa fa-history fa-2x mb-2"></i>
                        <p>履歴はありません。</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Note (メモ) -->
    <div class="modal fade" tabindex="-1" :class="{show: showNoteModal}" style="display: block;" v-if="showNoteModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"></h5>
                    <button type="button" class="btn-close" @click="closeNoteModal"></button>
                </div>
                <div class="modal-body">
                    <!-- View mode -->
                    <div v-if="editingNote.id && !isNoteEditMode">
                        <div class="mb-3">
                            <label class="form-label"><span data-i18n="内容">内容</span></label>
                            <div class="form-control" style="min-height:100px;white-space:pre-line;max-height:300px;overflow-y:auto;">{{ editingNote.content || '-' }}</div>
                        </div>
                        <div class="mb-3" v-if="editingNote.is_important">
                            <label class="form-label"><span data-i18n="重要メモ">重要メモ</span></label>
                            <div>
                                <i class="fa fa-exclamation-circle text-danger"></i>
                            </div>
                        </div>
                    </div>
                    <!-- Edit mode -->
                    <form v-else @submit.prevent="saveNote">
                        <div class="mb-3">
                            <label class="form-label"><span data-i18n="内容">内容</span></label>
                            <textarea class="form-control" v-model="editingNote.content" rows="6" placeholder="メモの詳細を入力してください..."></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" v-model="editingNote.is_important" id="noteIsImportant">
                                <label class="form-check-label" for="noteIsImportant">
                                    <i class="fa fa-exclamation-circle text-danger me-2"></i> <span data-i18n="重要メモ">重要メモ</span>
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <template v-if="editingNote.id && !isNoteEditMode">
                        <button class="btn btn-primary" @click="isNoteEditMode = true" v-if="canEditNote(editingNote)"><i class="fa fa-pencil-alt me-2"></i> <span data-i18n="編集">編集</span></button>
                        <button class="btn btn-secondary" @click="closeNoteModal"><span data-i18n="閉じる">閉じる</span></button>
                    </template>
                    <template v-else>
                        <button class="btn btn-secondary" @click="isNoteEditMode = false" v-if="editingNote.id"><i class="fa fa-times me-2"></i> <span data-i18n="キャンセル">キャンセル</span></button>
                        <button class="btn btn-secondary" @click="closeNoteModal" v-else><span data-i18n="キャンセル">キャンセル</span></button>
                        <button class="btn btn-primary" @click="saveNote" :disabled="!(editingNote.content && editingNote.content.trim())">
                            <i class="fa fa-save me-2"></i> <span data-i18n="保存">保存</span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Child Project Logs Modal -->
    <div class="modal fade" id="childProjectLogsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">案件履歴 - {{ selectedChildProject?.name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div v-if="loadingChildProjectLogs" class="text-center py-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">読み込み中...</span>
                        </div>
                    </div>
                    <div v-else-if="childProjectLogs.length > 0">
                        <ul class="list-group" style="max-height: 400px; overflow-y: auto;">
                            <li v-for="log in childProjectLogs" :key="log.id" class="list-group-item">
                                <div class="d-flex">
                                    <div class="d-flex flex-row align-items-start justify-content-start me-3" style="min-width:130px;">
                                        <div class="d-flex flex-column align-items-center justify-content-start" style="width:40px;">
                                            <div class="avatar avatar-sm">
                                                <span class="avatar-initial rounded-circle bg-label-primary">
                                                    {{ getInitials(log.username ? log.username : (log.realname ? log.realname : '?')) }}
                                                </span>
                                                <img v-if="log.user_image" :src="'/assets/upload/avatar/' + log.user_image" alt="avatar" class="rounded-circle" style="display:none;" @load="$event.target.style.display='block'; if ($event.target.previousElementSibling) $event.target.previousElementSibling.style.display='none';" @error="$event.target.remove()">
                                            </div>
                                        </div>
                                        <div class="d-flex flex-column align-items-start justify-content-center ms-2">
                                            <span class="fw-bold small">{{ log.username || log.realname || log.user }}</span>
                                            <span class="text-muted small">{{ formatShortDateTime(log.time) }}</span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 d-flex align-items-center">
                                        <span>
                                            <i :class="historyIcon(log.action) + ' me-2'"></i>
                                            <span class="me-2">{{ getLogNote(log) }}</span>
                                            <br>
                                            <span v-if="log.value1" :class="getLogBadgeClass(log, 'value1')" class="mx-1">{{ getLogBadgeLabel(log, 'value1') }}</span>
                                            <span v-if="log.value1 && log.value2" class="mx-1">→</span>
                                            <span v-if="log.value2" :class="getLogBadgeClass(log, 'value2')" class="mx-1">{{ getLogBadgeLabel(log, 'value2') }}</span>
                                        </span>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <div v-else class="text-center text-muted py-3">
                        <i class="fa fa-history fa-2x mb-2"></i>
                        <p>履歴はありません。</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Business Document (決済情報) Modal -->
    <div class="modal fade" id="businessDocumentModal" tabindex="-1" aria-labelledby="businessDocumentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content" v-if="businessDocumentProject">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="modal-title mb-0" id="businessDocumentModalLabel">
                            <span data-i18n="決済情報">決済情報</span>
                            <span class="text-muted small ms-2">#{{ businessDocumentProjectId }} {{ businessDocumentProject.name }}</span>
                        </h5>
                        <span v-if="businessDocumentSaveStatus === 'loading'" class="text-muted" title="保存中">
                            <i class="fa fa-spinner fa-spin"></i>
                        </span>
                        <span v-else-if="businessDocumentSaveStatus === 'saved'" class="text-success" title="保存済み">
                            <i class="fa fa-check-circle"></i>
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2 mx-2">
                        <button type="button" class="btn btn-outline-info btn-sm" @click="openBusinessDocumentLogModal">
                            <i class="fa fa-history me-1"></i><span data-i18n="履歴">履歴</span>
                        </button>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" @click="closeBusinessDocumentModal"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <input type="hidden" v-model.number="businessDocumentProject.payment_version">
                    <h6 class="text-muted mb-3"><span data-i18n="見積">見積</span></h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label mb-0">見積日 <span class="text-danger">*</span></label>
                                <button v-if="!hasBdDate('estimate_date')" type="button" class="btn btn-outline-primary btn-sm py-0 px-2"
                                        @click="setBdDateToday('estimate_date')">今日</button>
                            </div>
                            <input type="text" class="form-control" v-model="businessDocumentProject.estimate_date"
                                   id="bd_modal_estimate_date_picker" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">見積金額(税抜き) <span class="text-danger">*</span></label>
                            <input type="number" autocomplete="off" class="form-control" v-model.number="businessDocumentProject.amount"
                                   @input="scheduleBdUpdate" min="0" step="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">消費税（10%）</label>
                            <input type="text" class="form-control bg-light" readonly tabindex="-1" :value="formatBusinessDocumentTaxAmount(businessDocumentProject.amount)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">税込合計</label>
                            <input type="text" class="form-control bg-light fw-semibold" readonly tabindex="-1" :value="formatBusinessDocumentTotalWithTax(businessDocumentProject.amount)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">見積番号 <span class="text-danger">*</span></label>
                            <input type="text" autocomplete="off" class="form-control" v-model="businessDocumentProject.estimate_number" @change="scheduleBdUpdate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">見積状況</label>
                            <div class="btn-group d-block">
                                <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light"
                                        :class="getBdEstimateStatusButtonClass(businessDocumentProject.estimate_status)"
                                        id="bdEstimateStatusDropdown"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    {{ getBdEstimateStatusLabel(businessDocumentProject.estimate_status) }}
                                </button>
                                <ul class="dropdown-menu">
                                    <li v-for="status in businessEstimateStatuses" :key="status.value">
                                        <a class="dropdown-item waves-effect" href="javascript:void(0);"
                                           :class="{ disabled: status.value === '発行済' && !isBdEstimateDocumentFieldsComplete() }"
                                           @click="selectBdEstimateStatus(status.value)">
                                            {{ status.label }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div v-if="!isBdEstimateDocumentFieldsComplete()" class="form-text text-muted">
                                発行済にするには見積日・見積金額・見積番号が必要です
                            </div>
                        </div>
                    </div>

                    <hr class="my-3">

                    <h6 class="text-muted mb-3"><span data-i18n="請求">請求</span></h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label mb-0">請求日 <span class="text-danger">*</span></label>
                                <button v-if="!hasBdDate('invoice_date')" type="button" class="btn btn-outline-primary btn-sm py-0 px-2"
                                        @click="setBdDateToday('invoice_date')">今日</button>
                            </div>
                            <input type="text" class="form-control" v-model="businessDocumentProject.invoice_date"
                                   id="bd_modal_invoice_date_picker" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label mb-0">請求金額(税抜き) <span class="text-danger">*</span></label>
                                <button v-if="!hasBdAmount(businessDocumentProject.invoice_amount)" type="button" class="btn btn-outline-primary btn-sm py-0 px-2"
                                        @click="copyBdEstimateAmountToInvoice">見積と同額</button>
                            </div>
                            <input type="number" autocomplete="off" class="form-control" v-model.number="businessDocumentProject.invoice_amount"
                                   @input="scheduleBdUpdate" min="0" step="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">消費税（10%）</label>
                            <input type="text" class="form-control bg-light" readonly tabindex="-1" :value="formatBusinessDocumentTaxAmount(businessDocumentProject.invoice_amount)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">税込合計</label>
                            <input type="text" class="form-control bg-light fw-semibold" readonly tabindex="-1" :value="formatBusinessDocumentTotalWithTax(businessDocumentProject.invoice_amount)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">請求番号 <span class="text-danger">*</span></label>
                            <input type="text" autocomplete="off" class="form-control" v-model="businessDocumentProject.invoice_number" @change="scheduleBdUpdate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">請求状況</label>
                            <div class="btn-group d-block">
                                <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light"
                                        :class="getBdInvoiceStatusButtonClass(businessDocumentProject.invoice_status)"
                                        id="bdInvoiceStatusDropdown"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    {{ getBdInvoiceStatusLabel(businessDocumentProject.invoice_status) }}
                                </button>
                                <ul class="dropdown-menu">
                                    <li v-for="status in businessInvoiceStatuses" :key="status.value">
                                        <a class="dropdown-item waves-effect" href="javascript:void(0);"
                                           :class="{ disabled: status.value === '発行済' && !isBdInvoiceDocumentFieldsComplete() }"
                                           @click="selectBdInvoiceStatus(status.value)">
                                            {{ status.label }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div v-if="!isBdInvoiceDocumentFieldsComplete()" class="form-text text-muted">
                                発行済にするには請求日・請求金額・請求番号が必要です
                            </div>
                        </div>
                    </div>

                    <hr class="my-3">

                    <div class="mb-0">
                        <label class="form-label" data-i18n="決済備考">決済備考</label>
                        <textarea class="form-control" rows="3" v-model="businessDocumentProject.payment_note" @change="scheduleBdUpdate"></textarea>
                    </div>

                    <div v-if="businessDocumentError" class="alert alert-danger mt-3 mb-0">{{ businessDocumentError }}</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" @click="closeBusinessDocumentModal">閉じる</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Business Document History Modal -->
    <div class="modal fade" tabindex="-1" :class="{show: showBusinessDocumentLogModal}" style="display: block;" v-if="showBusinessDocumentLogModal">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">決済情報 履歴</h5>
                    <button type="button" class="btn-close" @click="closeBusinessDocumentLogModal"></button>
                </div>
                <div class="modal-body p-0">
                    <ul class="list-group list-group-flush">
                        <li v-for="log in sortedBusinessDocumentLogs" :key="log.id" class="list-group-item">
                            <div class="d-flex">
                                <div class="d-flex flex-row align-items-start justify-content-start me-3" style="min-width:130px;">
                                    <div class="d-flex flex-column align-items-center justify-content-start" style="width:40px;">
                                        <div class="avatar avatar-sm">
                                            <span class="avatar-initial rounded-circle bg-label-primary">
                                                {{ getInitials(log.username ? log.username : (log.realname ? log.realname : '?')) }}
                                            </span>
                                            <img v-if="log.user_image" :src="'/assets/upload/avatar/' + log.user_image" alt="avatar" class="rounded-circle" style="display:none;" @load="$event.target.style.display='block'; if ($event.target.previousElementSibling) $event.target.previousElementSibling.style.display='none';" @error="$event.target.remove()">
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column align-items-start justify-content-center ms-2">
                                        <span class="fw-bold small">{{ log.username || log.realname || log.user }}</span>
                                        <span class="text-muted small">{{ formatShortDateTime(log.time) }}</span>
                                    </div>
                                </div>
                                <div class="flex-grow-1 d-flex align-items-center">
                                    <span>
                                        <i :class="bdHistoryIcon(log.action) + ' me-2'"></i>
                                        <span class="me-2">{{ getLogNote(log) }}</span>
                                        <br>
                                        <span v-if="hasBdLogValue(log.value1)" :class="getBdLogBadgeClass(log, 'value1')" class="mx-1">{{ getBusinessDocumentLogValue(log, 'value1') }}</span>
                                        <span v-if="hasBdLogValue(log.value1) && hasBdLogValue(log.value2)" class="mx-1">→</span>
                                        <span v-if="hasBdLogValue(log.value2)" :class="getBdLogBadgeClass(log, 'value2')" class="mx-1">{{ getBusinessDocumentLogValue(log, 'value2') }}</span>
                                    </span>
                                </div>
                            </div>
                        </li>
                        <li v-if="!sortedBusinessDocumentLogs || sortedBusinessDocumentLogs.length === 0" class="list-group-item text-muted">決済情報の履歴はありません。</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="closeBusinessDocumentLogModal">閉じる</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show" v-if="showBusinessDocumentLogModal"></div>

    <!-- Quotation History Modal -->
    <div class="modal fade quotation-history-modal" id="quotationHistoryModal" tabindex="-1" aria-labelledby="quotationHistoryModalLabel"
        aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="quotationHistoryModalLabel">見積書履歴</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div v-if="quotationHistory.length > 0">
                        <ul class="list-group" style="max-height: 400px; overflow-y: auto;">
                            <li v-for="log in quotationHistory" :key="log.id" class="list-group-item">
                                <div class="d-flex">
                                    <div class="d-flex flex-row align-items-start justify-content-start me-3" style="min-width:130px;">
                                        <div class="d-flex flex-column align-items-center justify-content-start" style="width:40px;">
                                            <div class="avatar avatar-sm">
                                                <span class="avatar-initial rounded-circle bg-label-primary">
                                                    {{ getInitials(log.username ? log.username : (log.realname ? log.realname : '?')) }}
                                                </span>
                                                <img v-if="log.user_image" :src="'/assets/upload/avatar/' + log.user_image" alt="avatar" class="rounded-circle" style="display:none;" @load="$event.target.style.display='block'; if ($event.target.previousElementSibling) $event.target.previousElementSibling.style.display='none';" @error="$event.target.remove()">
                                            </div>
                                        </div>
                                        <div class="d-flex flex-column align-items-start justify-content-center ms-2">
                                            <span class="fw-bold small">{{ log.username || log.realname || log.user }}</span>
                                            <span class="text-muted small">{{ formatDateTime(log.time) }}</span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 d-flex align-items-center">
                                        <span>
                                            <i :class="historyIcon(log.action) + ' me-2'"></i>
                                            <span class="me-2">{{ getLogNote(log) }}</span>
                                            <br>
                                            <span v-if="log.value1" :class="getLogBadgeClass(log, 'value1')" class="mx-1">{{ getLogBadgeLabel(log, 'value1') }}</span>
                                            <span v-if="log.value1 && log.value2" class="mx-1">→</span>
                                            <span v-if="log.value2" :class="getLogBadgeClass(log, 'value2')" class="mx-1">{{ getLogBadgeLabel(log, 'value2') }}</span>
                                        </span>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <div v-else class="text-center text-muted py-3">
                        <i class="fa fa-history fa-2x mb-2"></i>
                        <p>履歴はありません。</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Child Project Modal -->
    <div class="modal fade" id="createChildProjectModal" tabindex="-1" aria-labelledby="createChildProjectModalLabel"
        aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createChildProjectModalLabel"><span data-i18n="案件依頼を作成">案件依頼を作成</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="createChildProject">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="案件名">案件名</span> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="newChildProject.name" required>
                                    <div v-if="childProjectValidationErrors.name" class="invalid-feedback d-block">
                                        {{ childProjectValidationErrors.name }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="部署">部署</span> <span class="text-danger">*</span></label>
                                    <select class="form-select" v-model="newChildProject.department_id" required @change="onCreateChildProjectDepartmentChange">
                                        <option value="">選択してください</option>
                                        <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                                            {{ dept.name }}
                                        </option>
                                    </select>
                                    <div v-if="childProjectValidationErrors.department_id"
                                        class="invalid-feedback d-block">
                                        {{ childProjectValidationErrors.department_id }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="create_use_parent_customer"
                                            v-model="newChildProject.use_parent_customer" @change="onChildProjectUseParentCustomerChange(false)">
                                        <label class="form-check-label" for="create_use_parent_customer">
                                            <span data-i18n="顧客情報は建物と同じ">顧客情報は建物と同じ</span>
                                        </label>
                                    </div>
                                </div>
                                <div v-if="newChildProject.use_parent_customer" class="small text-muted border rounded p-2 mb-2">
                                    <div><span data-i18n="会社名">会社名</span>: {{ getParentCustomerSummary().company_name }}</div>
                                    <div><span data-i18n="支店名">支店名</span>: {{ getParentCustomerSummary().branch_name }}</div>
                                    <div><span data-i18n="担当様">担当様</span>: {{ getParentCustomerSummary().contact_name }}</div>
                                </div>
                            </div>
                            <template v-if="!newChildProject.use_parent_customer">
                                <div class="col-md-6">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label"><span data-i18n="会社名">会社名</span> <span class="text-danger">*</span></label>
                                        <select id="create_child_company_name" class="form-select select2"></select>
                                        <div v-if="childProjectValidationErrors.company_name" class="invalid-feedback d-block">
                                            {{ childProjectValidationErrors.company_name }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label"><span data-i18n="支店名">支店名</span> <span class="text-danger">*</span></label>
                                        <select id="create_child_branch_name" class="form-select select2"></select>
                                        <div v-if="childProjectValidationErrors.branch_name" class="invalid-feedback d-block">
                                            {{ childProjectValidationErrors.branch_name }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label">
                                            <span data-i18n="担当様">担当様</span> <span class="text-danger">*</span>
                                            <button type="button" class="btn btn-sm btn-outline-primary py-0 small ms-2"
                                                @click="openChildProjectNewCustomerModal(false)" title="新規顧客追加">
                                                <i class="fa fa-plus me-1"></i> <span data-i18n="新規顧客">新規顧客</span>
                                            </button>
                                        </label>
                                        <select id="create_child_contact_name" class="form-select select2"></select>
                                        <div v-if="childProjectValidationErrors.contact_name" class="invalid-feedback d-block">
                                            {{ childProjectValidationErrors.contact_name }}
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div class="col-12">
                                <div class="mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="create_use_parent_guis_receiver"
                                            v-model="newChildProject.use_parent_guis_receiver" @change="onChildProjectUseParentGuisReceiverChange(false)">
                                        <label class="form-check-label" for="create_use_parent_guis_receiver">
                                            <span data-i18n="GUIS受付者は建物と同じ">GUIS受付者は建物と同じ</span>
                                        </label>
                                    </div>
                                </div>
                                <div v-if="newChildProject.use_parent_guis_receiver" class="small text-muted border rounded p-2 mb-2">
                                    <div><span data-i18n="GUIS受付者">GUIS受付者</span>: {{ getParentGuisReceiverDisplayName() }}</div>
                                </div>
                            </div>
                            <div class="col-12" v-if="!newChildProject.use_parent_guis_receiver">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="GUIS受付者">GUIS受付者</span> <span class="text-danger">*</span></label>
                                    <select id="create_child_guis_receiver" class="form-select select2"></select>
                                    <div v-if="childProjectValidationErrors.guis_receiver" class="invalid-feedback d-block">
                                        {{ childProjectValidationErrors.guis_receiver }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="予定工程">予定工程</span></label>
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <div>
                                            <div class="d-flex gap-1">
                                                <input type="text" class="form-control" style="min-width: 9rem;" id="create_yotei_from_month" :value="newChildProject.yotei.from_month" autocomplete="off" placeholder="YYYY-MM">
                                                <select class="form-select" style="width: 6.5rem;" v-model="newChildProject.yotei.from_part">
                                                    <option v-for="opt in yoteiPartOptions" :key="'create-from-' + opt.value" :value="opt.value">{{ opt.label }}</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="text-muted">～</div>
                                        <div>
                                            <div class="d-flex gap-1">
                                                <input type="text" class="form-control" style="min-width: 9rem;" id="create_yotei_to_month" :value="newChildProject.yotei.to_month" autocomplete="off" placeholder="YYYY-MM">
                                                <select class="form-select" style="width: 6.5rem;" v-model="newChildProject.yotei.to_part" :disabled="!newChildProject.yotei.to_month">
                                                    <option v-for="opt in yoteiPartOptions" :key="'create-to-' + opt.value" :value="opt.value">{{ opt.label }}</option>
                                                </select>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" @click="clearChildProjectYotei(false)" data-i18n="クリア">クリア</button>
                                        <div v-if="formatYoteiDisplay(newChildProject.yotei)" class="ms-2 small text-body-secondary">{{ formatYoteiDisplay(newChildProject.yotei) }}</div>
                                    </div>
                                    <div v-if="childProjectValidationErrors.yotei" class="invalid-feedback d-block">{{ childProjectValidationErrors.yotei }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="開始日">開始日</span></label>
                                    <input type="text" class="form-control" v-model="newChildProject.start_date"
                                        id="start_date_picker" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off">
                                    <div v-if="childProjectValidationErrors.start_date"
                                        class="invalid-feedback d-block">
                                        {{ childProjectValidationErrors.start_date }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4" v-if="canViewEndDate">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="期限日(実納期)">期限日(実納期)</span> <span v-if="(newChildProject.guis_nouki || '').trim()" class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="newChildProject.end_date"
                                        id="end_date_picker" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off"
                                        :class="{ 'is-invalid': childProjectValidationErrors.end_date }">
                                    <div v-if="childProjectValidationErrors.end_date" class="invalid-feedback d-block">
                                        {{ childProjectValidationErrors.end_date }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="ステータス">ステータス</span> <span class="text-danger">*</span></label>
                                    <div class="btn-group" style="width: 100%;">
                                        <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light" 
                                                :class="getProjectStatusButtonClass(newChildProject.status)"
                                                id="createChildProjectStatusDropdown"
                                                data-bs-toggle="dropdown" aria-expanded="false"
                                                style="width: 100%; text-align: left;">
                                            {{ newChildProject.status ? getProjectStatusLabel(newChildProject.status) : '選択してください' }}
                                        </button>
                                        <ul class="dropdown-menu" style="width: 100%;">
                                            <li v-for="status in editableProjectStatuses" :key="status.value">
                                                <a class="dropdown-item waves-effect" href="javascript:void(0);" 
                                                @click="selectProjectStatus(status.value, false)">
                                                    {{ status.label }}
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    <div v-if="childProjectValidationErrors.status" class="invalid-feedback d-block">
                                        {{ childProjectValidationErrors.status }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="受注形態">受注形態</span> <span class="text-danger">*</span></label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control tagify"
                                            v-model="newChildProject.project_order_type" id="child_project_order_type"
                                            name="child_project_order_type">
                                        <button class="btn btn-outline-secondary btn-sm" type="button"
                                            @click="clearChildProjectTagifyTags('project_order_type')" title="すべて削除"><i
                                                class="fa fa-times"></i></button>
                                    </div>
                                    <div v-if="childProjectValidationErrors.project_order_type"
                                        class="invalid-feedback d-block">
                                        {{ childProjectValidationErrors.project_order_type }}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label">担当 <span class="text-danger">*</span></label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" v-model="newChildProject.tantou" value="CAILY" id="create_tantou_caily">
                                            <label class="form-check-label" for="create_tantou_caily">CAILY</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" v-model="newChildProject.tantou" value="GUIS" id="create_tantou_guis">
                                            <label class="form-check-label" for="create_tantou_guis">GUIS</label>
                                        </div>
                                    </div>
                                    <div v-if="childProjectValidationErrors.tantou" class="invalid-feedback d-block">
                                        {{ childProjectValidationErrors.tantou }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label">CAILY納期 <span v-if="newChildProject.end_date && newChildProject.tantou === 'CAILY'" class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="newChildProject.caily_nouki" 
                                        id="create_caily_nouki_picker" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off"
                                        :class="{ 'is-invalid': childProjectValidationErrors.caily_nouki }">
                                    <div v-if="childProjectValidationErrors.caily_nouki" class="invalid-feedback d-block">
                                        {{ childProjectValidationErrors.caily_nouki }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4" v-if="canViewEndDate">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label">GUIS納期 <span v-if="newChildProject.end_date && newChildProject.tantou === 'GUIS'" class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="newChildProject.guis_nouki" 
                                        id="create_guis_nouki_picker" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off"
                                        :class="{ 'is-invalid': childProjectValidationErrors.guis_nouki }">
                                    <div v-if="childProjectValidationErrors.guis_nouki" class="invalid-feedback d-block">
                                        {{ childProjectValidationErrors.guis_nouki }}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="進捗率">進捗率</span> (%)</label>
                                    <input type="number" class="form-control" v-model.number="newChildProject.progress" step="5" min="0" max="100" value="0" placeholder="0">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="チーム">チーム</span></label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control" id="create_child_project_team_tags" placeholder="チームを選択">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearChildProjectTeamTags(false)" title="すべて削除"><i class="fa fa-times"></i></button>
                                    </div>
                                    <small class="form-text text-muted">
                                        <span data-i18n="部署を選択すると、その部署のユーザーが表示されます">部署を選択すると、その部署のユーザーが表示されます</span>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="管理">管理</span></label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input class="form-control" type="text" id="create_child_project_manager_tags" name="create_child_project_manager_tags">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearChildProjectManagerTags(false)" title="すべて削除"><i class="fa fa-times"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="メンバー">メンバー</span></label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control" id="create_child_project_members_tags" placeholder="メンバーを選択">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearChildProjectMembersTags(false)" title="すべて削除"><i class="fa fa-times"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="row" id="createChildProjectCustomFieldsWrap">
                                    <!-- Custom fields rendered by JS when department is selected -->
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label"><span data-i18n="説明">説明</span></label>
                                    <div class="custom_editor">
                                        <div class="custom_editor_content" id="create_child_project_quill_description"></div>
                                        <textarea class="custom_editor_textarea d-none" v-model="newChildProject.description" id="create_child_project_quill_description_textarea"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><span data-i18n="キャンセル">キャンセル</span></button>
                    <button type="button" class="btn btn-primary" @click="createChildProject"
                        :disabled="creatingChildProject">
                        <span v-if="creatingChildProject" class="spinner-border spinner-border-sm me-1"></span>
                        <span data-i18n="作成">作成</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Child Project Modal -->
    <div class="modal fade" id="editChildProjectModal" tabindex="-1" aria-labelledby="editChildProjectModalLabel"
        aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editChildProjectModalLabel"><span data-i18n="案件依頼編集">案件依頼編集</span></h5>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><span data-i18n="キャンセル">キャンセル</span></button>
                        <button type="button" class="btn btn-primary btn-sm" @click="updateChildProject" :disabled="updatingChildProject">
                            <span v-if="updatingChildProject" class="spinner-border spinner-border-sm me-1"></span>
                            <span data-i18n="更新">更新</span>
                        </button>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="updateChildProject">
                        <input type="hidden" v-model.number="editingChildProject.version">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="案件名">案件名</span> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="editingChildProject.name" required>
                                    <div v-if="editChildProjectValidationErrors.name" class="invalid-feedback d-block">
                                        {{ editChildProjectValidationErrors.name }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="部署">部署</span> <span class="text-danger">*</span></label>
                                    <select class="form-select" v-model="editingChildProject.department_id" required @change="onEditChildProjectDepartmentChange">
                                        <option value="">選択してください</option>
                                        <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                                            {{ dept.name }}
                                        </option>
                                    </select>
                                    <div v-if="editChildProjectValidationErrors.department_id"
                                        class="invalid-feedback d-block">
                                        {{ editChildProjectValidationErrors.department_id }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_use_parent_customer"
                                            v-model="editingChildProject.use_parent_customer" @change="onChildProjectUseParentCustomerChange(true)">
                                        <label class="form-check-label" for="edit_use_parent_customer">
                                            <span data-i18n="顧客情報は建物と同じ">顧客情報は建物と同じ</span>
                                        </label>
                                    </div>
                                </div>
                                <div v-if="editingChildProject.use_parent_customer" class="small text-muted border rounded p-2 mb-2">
                                    <div><span data-i18n="会社名">会社名</span>: {{ getParentCustomerSummary().company_name }}</div>
                                    <div><span data-i18n="支店名">支店名</span>: {{ getParentCustomerSummary().branch_name }}</div>
                                    <div><span data-i18n="担当様">担当様</span>: {{ getParentCustomerSummary().contact_name }}</div>
                                </div>
                            </div>
                            <template v-if="!editingChildProject.use_parent_customer">
                                <div class="col-md-6">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label"><span data-i18n="会社名">会社名</span> <span class="text-danger">*</span></label>
                                        <select id="edit_child_company_name" class="form-select select2"></select>
                                        <div v-if="editChildProjectValidationErrors.company_name" class="invalid-feedback d-block">
                                            {{ editChildProjectValidationErrors.company_name }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label"><span data-i18n="支店名">支店名</span> <span class="text-danger">*</span></label>
                                        <select id="edit_child_branch_name" class="form-select select2"></select>
                                        <div v-if="editChildProjectValidationErrors.branch_name" class="invalid-feedback d-block">
                                            {{ editChildProjectValidationErrors.branch_name }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label">
                                            <span data-i18n="担当様">担当様</span> <span class="text-danger">*</span>
                                            <button type="button" class="btn btn-sm btn-outline-primary py-0 small ms-2"
                                                @click="openChildProjectNewCustomerModal(true)" title="新規顧客追加">
                                                <i class="fa fa-plus me-1"></i> <span data-i18n="新規顧客">新規顧客</span>
                                            </button>
                                            <button v-if="editingChildProject.customer_id" type="button"
                                                class="btn btn-sm btn-outline-info py-0 small ms-2"
                                                @click="openEditingChildProjectCustomerInfoModal"
                                                title="顧客情報表示・編集">
                                                <i class="fa fa-info-circle me-1"></i> <span data-i18n="顧客情報">顧客情報</span>
                                            </button>
                                        </label>
                                        <select id="edit_child_contact_name" class="form-select select2"></select>
                                        <div v-if="editChildProjectValidationErrors.contact_name" class="invalid-feedback d-block">
                                            {{ editChildProjectValidationErrors.contact_name }}
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div class="col-12">
                                <div class="mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_use_parent_guis_receiver"
                                            v-model="editingChildProject.use_parent_guis_receiver" @change="onChildProjectUseParentGuisReceiverChange(true)">
                                        <label class="form-check-label" for="edit_use_parent_guis_receiver">
                                            <span data-i18n="GUIS受付者は建物と同じ">GUIS受付者は建物と同じ</span>
                                        </label>
                                    </div>
                                </div>
                                <div v-if="editingChildProject.use_parent_guis_receiver" class="small text-muted border rounded p-2 mb-2">
                                    <div><span data-i18n="GUIS受付者">GUIS受付者</span>: {{ getParentGuisReceiverDisplayName() }}</div>
                                </div>
                            </div>
                            <div class="col-12" v-if="!editingChildProject.use_parent_guis_receiver">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="GUIS受付者">GUIS受付者</span> <span class="text-danger">*</span></label>
                                    <select id="edit_child_guis_receiver" class="form-select select2"></select>
                                    <div v-if="editChildProjectValidationErrors.guis_receiver" class="invalid-feedback d-block">
                                        {{ editChildProjectValidationErrors.guis_receiver }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="予定工程">予定工程</span></label>
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <div>
                                            <div class="d-flex gap-1">
                                                <input type="text" class="form-control" style="min-width: 9rem;" id="edit_yotei_from_month" :value="editingChildProject.yotei.from_month" autocomplete="off" placeholder="YYYY-MM">
                                                <select class="form-select" style="width: 6.5rem;" v-model="editingChildProject.yotei.from_part">
                                                    <option v-for="opt in yoteiPartOptions" :key="'edit-from-' + opt.value" :value="opt.value">{{ opt.label }}</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="text-muted">～</div>
                                        <div>
                                            <div class="d-flex gap-1">
                                                <input type="text" class="form-control" style="min-width: 9rem;" id="edit_yotei_to_month" :value="editingChildProject.yotei.to_month" autocomplete="off" placeholder="YYYY-MM">
                                                <select class="form-select" style="width: 6.5rem;" v-model="editingChildProject.yotei.to_part" :disabled="!editingChildProject.yotei.to_month">
                                                    <option v-for="opt in yoteiPartOptions" :key="'edit-to-' + opt.value" :value="opt.value">{{ opt.label }}</option>
                                                </select>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" @click="clearChildProjectYotei(true)" data-i18n="クリア">クリア</button>
                                        <div v-if="formatYoteiDisplay(editingChildProject.yotei)" class="ms-2 small text-body-secondary">{{ formatYoteiDisplay(editingChildProject.yotei) }}</div>
                                    </div>
                                    <div v-if="editChildProjectValidationErrors.yotei" class="invalid-feedback d-block">{{ editChildProjectValidationErrors.yotei }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="開始日">開始日</span></label>
                                    <input type="text" class="form-control" v-model="editingChildProject.start_date"
                                        id="edit_start_date_picker" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off">
                                    <div v-if="editChildProjectValidationErrors.start_date"
                                        class="invalid-feedback d-block">
                                        {{ editChildProjectValidationErrors.start_date }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4" v-if="!isCailyBranchUser">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="期限日(実納期)">期限日(実納期)</span> <span v-if="(editingChildProject.guis_nouki || '').trim()" class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="editingChildProject.end_date"
                                        id="edit_end_date_picker" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off"
                                        :class="{ 'is-invalid': editChildProjectValidationErrors.end_date }">
                                    <div v-if="editChildProjectValidationErrors.end_date"
                                        class="invalid-feedback d-block">
                                        {{ editChildProjectValidationErrors.end_date }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="ステータス">ステータス</span></label>
                                    <div class="btn-group" style="width: 100%;">
                                        <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light" 
                                                :class="getProjectStatusButtonClass(editingChildProject.status)"
                                                id="editChildProjectStatusDropdown"
                                                data-bs-toggle="dropdown" aria-expanded="false"
                                                style="width: 100%; text-align: left;">
                                            {{ getProjectStatusLabel(editingChildProject.status) }}
                                        </button>
                                        <ul class="dropdown-menu" style="width: 100%;">
                                            <li v-for="status in editableProjectStatuses" :key="status.value">
                                                <a class="dropdown-item waves-effect" href="javascript:void(0);" 
                                                @click="selectProjectStatus(status.value, true)">
                                                    {{ status.label }}
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="受注形態">受注形態</span> <span class="text-danger">*</span></label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control tagify"
                                            v-model="editingChildProject.project_order_type"
                                            id="edit_child_project_order_type" name="edit_child_project_order_type">
                                        <button class="btn btn-outline-secondary btn-sm" type="button"
                                            @click="clearEditChildProjectTagifyTags('project_order_type')"
                                            title="すべて削除"><i class="fa fa-times"></i></button>
                                    </div>
                                    <div v-if="editChildProjectValidationErrors.project_order_type"
                                        class="invalid-feedback d-block">
                                        {{ editChildProjectValidationErrors.project_order_type }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label">担当 <span class="text-danger">*</span></label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" v-model="editingChildProject.tantou" value="CAILY" id="edit_tantou_caily">
                                            <label class="form-check-label" for="edit_tantou_caily">CAILY</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" v-model="editingChildProject.tantou" value="GUIS" id="edit_tantou_guis">
                                            <label class="form-check-label" for="edit_tantou_guis">GUIS</label>
                                        </div>
                                    </div>
                                    <div v-if="editChildProjectValidationErrors.tantou" class="invalid-feedback d-block">
                                        {{ editChildProjectValidationErrors.tantou }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label">CAILY納期 <span v-if="editingChildProject.end_date && editingChildProject.tantou === 'CAILY'" class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="editingChildProject.caily_nouki" 
                                        id="edit_caily_nouki_picker" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off"
                                        :class="{ 'is-invalid': editChildProjectValidationErrors.caily_nouki }">
                                    <div v-if="editChildProjectValidationErrors.caily_nouki" class="invalid-feedback d-block">
                                        {{ editChildProjectValidationErrors.caily_nouki }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4" v-if="!isCailyBranchUser">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label">GUIS納期 <span v-if="editingChildProject.end_date && editingChildProject.tantou === 'GUIS'" class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="editingChildProject.guis_nouki" 
                                        id="edit_guis_nouki_picker" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off"
                                        :class="{ 'is-invalid': editChildProjectValidationErrors.guis_nouki }">
                                    <div v-if="editChildProjectValidationErrors.guis_nouki" class="invalid-feedback d-block">
                                        {{ editChildProjectValidationErrors.guis_nouki }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="進捗率">進捗率</span> (%)</label>
                                    <input type="number" class="form-control" v-model.number="editingChildProject.progress" step="5" min="0" max="100" placeholder="0">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="チーム">チーム</span></label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control" id="edit_child_project_team_tags" placeholder="チームを選択">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearChildProjectTeamTags(true)" title="すべて削除"><i class="fa fa-times"></i></button>
                                    </div>
                                    <small class="form-text text-muted">
                                        <span data-i18n="部署を選択すると、その部署のユーザーが表示されます">部署を選択すると、その部署のユーザーが表示されます</span>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="管理">管理</span></label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input class="form-control" type="text" id="edit_child_project_manager_tags" name="edit_child_project_manager_tags">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearChildProjectManagerTags(true)" title="すべて削除"><i class="fa fa-times"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label"><span data-i18n="メンバー">メンバー</span></label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control" id="edit_child_project_members_tags" placeholder="メンバーを選択">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearChildProjectMembersTags(true)" title="すべて削除"><i class="fa fa-times"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="row" id="editChildProjectCustomFieldsWrap">
                                    <!-- Custom fields rendered by JS when department is selected -->
                                </div>
                            </div>
                             <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label"><span data-i18n="説明">説明</span></label>
                                    <div class="custom_editor">
                                        <div class="custom_editor_content" id="edit_child_project_quill_description"></div>
                                        <textarea class="custom_editor_textarea d-none" v-model="editingChildProject.description" id="edit_child_project_quill_description_textarea"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <div v-if="editingChildProject && editingChildProject.status === 'cancelled'" class="me-2">
                        <button type="button" class="btn btn-warning" @click="restoreChildProject"
                            :disabled="restoringChildProject">
                            <span v-if="restoringChildProject" class="spinner-border spinner-border-sm me-1"></span>
                            <i class="fa fa-undo me-1"></i>
                            <span data-i18n="復元">復元</span>
                        </button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">キャンセル</button>
                        <button type="button" class="btn btn-primary" @click="updateChildProject"
                            :disabled="updatingChildProject">
                            <span v-if="updatingChildProject" class="spinner-border spinner-border-sm me-1"></span>
                            <span data-i18n="更新">更新</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Quotation Modal -->
    <div class="modal fade" id="createQuotationModal" tabindex="-1" aria-labelledby="createQuotationModalLabel"
        aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xxl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createQuotationModalLabel"><span data-i18n="見積書作成">見積書作成</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="createQuotation">
                        <div class="row g-3">
                            <!-- Header Information -->
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><span data-i18n="基本情報">基本情報</span></h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label"><span data-i18n="発行日">発行日</span> <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control"
                                                    :class="{ 'is-invalid': quotationValidationErrors.issue_date }"
                                                    id="quotation_issue_date" v-model="newQuotation.issue_date"
                                                    readonly>
                                                <div v-if="quotationValidationErrors.issue_date"
                                                    class="text-danger small mt-1">
                                                    {{ quotationValidationErrors.issue_date }}
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label"><span data-i18n="見積番号">見積番号</span> <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" class="form-control"
                                                    :class="{ 'is-invalid': quotationValidationErrors.quotation_number }"
                                                    v-model="newQuotation.quotation_number" readonly>
                                                <div v-if="quotationValidationErrors.quotation_number"
                                                    class="text-danger small mt-1">
                                                    {{ quotationValidationErrors.quotation_number }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Child Projects Selection -->
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><span data-i18n="選択する案件">選択する案件</span></h6>
                                        <small class="text-muted"><span data-i18n="この見積書に関連する案件を選択してください">この見積書に関連する案件を選択してください</span></small>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <div class="d-flex justify-content-end align-items-center mb-2">
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary"
                                                            @click="selectAllChildProjects">
                                                            <i class="fa fa-check-square me-1"></i> <span data-i18n="全選択">全選択</span>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-secondary"
                                                            @click="deselectAllChildProjects">
                                                            <i class="fa fa-square me-1"></i> <span data-i18n="全解除">全解除</span>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="table-responsive"
                                                    style="max-height: 300px; overflow-y: auto;">
                                                    <table class="table table-sm table-hover">
                                                        <thead class="table-light sticky-top">
                                                            <tr>
                                                                <th style="width: 50px;">
                                                                    <input type="checkbox" class="form-check-input"
                                                                        @change="toggleAllChildProjects"
                                                                        :checked="allChildProjectsSelected"
                                                                        :indeterminate="someChildProjectsSelected">
                                                                </th>
                                                                <th><span data-i18n="案件番号">案件番号</span></th>
                                                                <th><span data-i18n="案件名">案件名</span></th>
                                                                <th><span data-i18n="部署">部署</span></th>
                                                                <th><span data-i18n="受注形態">受注形態</span></th>
                                                                <th><span data-i18n="開始日">開始日</span></th>
                                                                <th v-if="!isCailyBranchUser"><span data-i18n="期限日">期限日</span></th>
                                                                <th><span data-i18n="現在のステータス">現在のステータス</span></th>
                                                                <th><span data-i18n="総額">総額</span></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="project in childProjects" :key="project.id"
                                                                :class="{ 
                                                                    'table-active': selectedChildProjectIds.includes(parseInt(project.id)),
                                                                    'table-warning': projectsUsedInActiveQuotations.has(parseInt(project.id))
                                                                }">
                                                                <td>
                                                                    <input type="checkbox" class="form-check-input"
                                                                        :value="parseInt(project.id)"
                                                                        v-model="selectedChildProjectIds"
                                                                        @change="updateChildProjectSelection"
                                                                        :disabled="projectsUsedInActiveQuotations.has(parseInt(project.id))"
                                                                        :title="projectsUsedInActiveQuotations.has(parseInt(project.id)) ? 'このプロジェクトは他の見積書で既に使用されています' : ''">
                                                                </td>
                                                                <td>{{ project.project_number || '-' }}</td>
                                                                <td>
                                                                    <a :href="'../project/detail.php?id=' + project.id"
                                                                        class="text-decoration-none" target="_blank">
                                                                        {{ project.name }}
                                                                    </a>
                                                                </td>
                                                                <td>{{ project.department_name || '-' }}</td>
                                                                <td>
                                                                    <span
                                                                        v-if="project.project_order_type && project.project_order_type.split(',').length > 0">
                                                                        <span
                                                                            v-for="item in project.project_order_type.split(',')"
                                                                            :key="item.trim()"
                                                                            class="badge me-1"
                                                                            :class="getOrderTypeBadgeClass(item.trim())">{{ item.trim()
                                                                            }}</span>
                                                                    </span>
                                                                    <span v-else>-</span>
                                                                </td>
                                                                <td>{{ formatDateTime(project.start_date) || '-' }}</td>
                                                                <td v-if="!isCailyBranchUser">{{ formatDateTime(project.end_date) || '-' }}</td>
                                                                <td>
                                                                    <span v-if="project.is_kadai == 1" class="badge bg-warning">
                                                                        承認待ち
                                                                    </span>
                                                                    <span v-else class="badge"
                                                                        :class="getProjectStatusBadgeClass(project.status)">
                                                                        {{ getProjectStatusLabel(project.status) }}
                                                                    </span>
                                                                </td>

                                                                <td class="text-end">
                                                                    <span class="fw-bold text-primary">
                                                                        ¥{{ formatNumber(project.total_amount || 0) }}
                                                                    </span>
                                                                </td>

                                                            </tr>
                                                            <tr v-if="childProjects.length === 0">
                                                                <td colspan="10" class="text-center text-muted py-4">
                                                                <span data-i18n="子プロジェクトがありません">子プロジェクトがありません</span>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="mt-2">
                                                    <small class="text-muted">
                                                        <span data-i18n="選択された案件">選択された案件</span>: {{ selectedChildProjectIds.length }} / {{
                                                        childProjects.filter(p => !projectsUsedInActiveQuotations.has(parseInt(p.id))).length }}
                                                        (<span data-i18n="利用可能">利用可能</span>) / {{ childProjects.length }} (<span data-i18n="全体">全体</span>)
                                                    </small>
                                                    <small v-if="projectsUsedInActiveQuotations.size > 0" class="d-block text-warning mt-1">
                                                        <i class="fa fa-warning me-1"></i>
                                                        {{ projectsUsedInActiveQuotations.size }} <span data-i18n="案件">案件</span> <span data-i18n="は他の見積書で既に使用されているため選択できません">は他の見積書で既に使用されているため選択できません</span>
                                                    </small>
                                                </div>
                                                <div v-if="quotationValidationErrors.childProjects" class="mt-2">
                                                    <div class="text-danger small">
                                                        {{ quotationValidationErrors.childProjects }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sender Information -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><span data-i18n="受注者情報">受注者情報</span></h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label"><span data-i18n="会社名">会社名</span> <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control"
                                                :class="{ 'is-invalid': quotationValidationErrors.sender_company }"
                                                v-model="newQuotation.sender_company"
                                                @input="quotationValidationErrors.sender_company = ''" required>
                                            <div v-if="quotationValidationErrors.sender_company"
                                                class="text-danger small mt-1">
                                                {{ quotationValidationErrors.sender_company }}
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><span data-i18n="住所">住所</span></label>
                                            <textarea class="form-control" v-model="newQuotation.sender_address"
                                                rows="2"></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><span data-i18n="担当様">担当様</span></label>
                                            <input type="text" class="form-control"
                                                v-model="newQuotation.sender_contact">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Receiver Information -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><span data-i18n="発注者情報">発注者情報</span></h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label"><span data-i18n="支社選択">支社選択</span></label>
                                            <select class="form-select" v-model="newQuotation.selected_branch_id"
                                                @change="onBranchSelect">
                                                <option value="">支社を選択してください</option>
                                                <option v-for="branch in quotationBranches" :key="branch.id"
                                                    :value="branch.id">
                                                    {{ branch.name }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><span data-i18n="会社名">会社名</span> <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control"
                                                :class="{ 'is-invalid': quotationValidationErrors.receiver_company }"
                                                v-model="newQuotation.receiver_company"
                                                @input="quotationValidationErrors.receiver_company = ''" required>
                                            <div v-if="quotationValidationErrors.receiver_company"
                                                class="text-danger small mt-1">
                                                {{ quotationValidationErrors.receiver_company }}
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><span data-i18n="住所">住所</span></label>
                                            <textarea class="form-control" v-model="newQuotation.receiver_address"
                                                rows="2"></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">TEL</label>
                                            <input type="text" class="form-control" v-model="newQuotation.receiver_tel">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">FAX</label>
                                            <input type="text" class="form-control" v-model="newQuotation.receiver_fax">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><span data-i18n="登録番号">登録番号</span></label>
                                            <input type="text" class="form-control"
                                                v-model="newQuotation.receiver_registration_number">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><span data-i18n="担当">担当</span></label>
                                            <select class="form-select" v-model="newQuotation.receiver_contact"
                                                @change="onContactSelect">
                                                <option value="">担当者を選択してください</option>
                                                <option v-for="user in quotationUsers" :key="user.id"
                                                    :value="user.realname">
                                                    {{ user.realname }}
                                                </option>
                                            </select>
                                            <!-- Seal display area -->
                                            <div v-if="selectedContactSeal" class="mt-2">
                                                <div class="alert alert-info d-flex align-items-center">
                                                    <i class="bi bi-stamp me-2"></i>
                                                    <div>
                                                        <strong><span data-i18n="印鑑">印鑑</span>:</strong> {{ selectedContactSeal.name }}
                                                        <br>
                                                        <img v-if="selectedContactSeal.image_path"
                                                            :src="selectedContactSeal.image_path" class="mt-1"
                                                            style="max-width: 60px; max-height: 60px; object-fit: contain;"
                                                            alt="印鑑画像">
                                                    </div>
                                                </div>
                                            </div>
                                            <div v-else-if="newQuotation.receiver_contact && !selectedContactSeal"
                                                class="mt-2">
                                                <div class="alert alert-warning d-flex align-items-center">
                                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                                    <span><span data-i18n="選択された担当者の印鑑が見つかりません">選択された担当者の印鑑が見つかりません</span></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Subject -->
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><span data-i18n="件名">件名</span> <span class="text-danger">*</span></h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <input type="text" class="form-control" 
                                                :class="{ 'is-invalid': quotationValidationErrors.subject }"
                                                v-model="newQuotation.subject" 
                                                @input="quotationValidationErrors.subject = ''"
                                                placeholder="件名を入力してください" required>
                                            <div v-if="quotationValidationErrors.subject" class="text-danger small mt-1">
                                                {{ quotationValidationErrors.subject }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Order Items -->
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><span data-i18n="商品明細">商品明細</span></h6>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-success"
                                                @click="showPriceListModal">
                                                <i class="fa fa-search me-1"></i> <span data-i18n="価格表から選択">価格表から選択</span>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                @click="addOrderItem">
                                                <i class="fa fa-plus me-1"></i> <span data-i18n="商品追加">商品追加</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-bordered" id="quotation-table">
                                                <thead>
                                                    <tr>
                                                        <th style="width:30px;">
                                                            <i class="fa fa-arrows-alt text-muted" title="ドラッグして並び替え"></i>
                                                        </th>
                                                        <th style="width:36px;">
                                                            <input type="checkbox" class="form-check-input"
                                                                @change="selectAllOrderItems"
                                                                :checked="allOrderItemsSelected">
                                                        </th>
                                                        <th><span data-i18n="案件番号">案件番号</span></th>
                                                        <th><span data-i18n="件名">件名</span></th>
                                                        <th><span data-i18n="商品コード">商品コード</span></th>
                                                        <th><span data-i18n="タイプ">タイプ</span></th>
                                                        <th style="width:70px;"><span data-i18n="数量">数量</span></th>
                                                        <th style="width:70px;"><span data-i18n="単位">単位</span></th>
                                                        <th style="width:100px;"><span data-i18n="単価">単価</span></th>
                                                        <th style="width:100px;"><span data-i18n="金額">金額</span></th>
                                                        <th><span data-i18n="備考">備考</span></th>
                                                        <th><span data-i18n="操作">操作</span></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="quotation-items-sortable">
                                                    <tr v-if="newQuotation.items.length === 0">
                                                        <td colspan="12" class="text-center text-muted py-4">
                                                            <span data-i18n="商品がありません">商品がありません</span>
                                                        </td>
                                                    </tr>
                                                    <tr v-for="(item, index) in newQuotation.items" :key="index" class="sortable-item">
                                                        <td class="text-center">
                                                            <i class="fa fa-grip-vertical drag-handle text-muted cursor-move" 
                                                               title="ドラッグして移動"></i>
                                                        </td>
                                                        <td class="text-center">
                                                            <input type="checkbox" class="form-check-input"
                                                                :checked="selectedOrderItemIndexes.includes(index)"
                                                                @change="toggleSelectOrderItem(index)">
                                                        </td>
                                                        <td>
                                                            <select class="form-select form-select-sm"
                                                                :class="{ 'is-invalid': quotationValidationErrors.items && (!item.project_id || item.project_id === '' || item.project_id === null) }"
                                                                v-model="item.project_id"
                                                                @change="updateProjectTotalAmount(item.project_id, index); validateProjectIdSelection();">
                                                                <option value="">選択してください</option>
                                                                <option
                                                                    v-for="project in selectedChildProjectsForDropdown"
                                                                    :key="project.id" :value="project.id">
                                                                    {{ project.project_number || project.name }}
                                                                </option>
                                                            </select>
                                                            <small v-if="selectedChildProjectsForDropdown.length === 0"
                                                                class="text-muted d-block mt-1">
                                                                <span data-i18n="案件が選択されていません">案件が選択されていません</span>
                                                            </small>
                                                            <div v-if="quotationValidationErrors.items && (!item.project_id || item.project_id === '' || item.project_id === null)"
                                                                class="invalid-feedback d-block">
                                                                <span data-i18n="案件番号は必須です">案件番号は必須です</span>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm"
                                                                v-model="item.title" placeholder="件名">
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm"
                                                                v-model="item.product_code" placeholder="商品コード">
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm"
                                                                v-model="item.type" placeholder="タイプ">
                                                        </td>
                                                        <td>
                                                            <input type="number" class="form-control form-control-sm"
                                                                v-model.number="item.quantity"
                                                                @input="calculateItemAmount(index)" min="0" step="1">
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm"
                                                                v-model="item.unit" placeholder="">
                                                        </td>
                                                        <td>
                                                            <input type="number" class="form-control form-control-sm"
                                                                v-model.number="item.unit_price"
                                                                @input="calculateItemAmount(index)" min="0" step="1">
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm"
                                                                :value="formatCurrency(item.amount)" readonly>
                                                        </td>
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm"
                                                                v-model="item.notes" placeholder="備考">
                                                        </td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <button type="button" class="btn btn-outline-secondary"
                                                                    v-if="item.is_set" @click="showEditSetModal(index)"
                                                                    title="編集">
                                                                    <i class="fa fa-edit"></i> <span data-i18n="編集">編集</span>
                                                                </button>
                                                                <button type="button" class="btn btn-outline-danger"
                                                                    @click="removeOrderItem(index)" title="削除">
                                                                    <i class="fa fa-trash"></i> <span data-i18n="削除">削除</span>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <div class="text-muted small">選択中: {{ selectedOrderItemIndexes.length }} /
                                                {{ newQuotation.items.length }}</div>
                                            <button type="button" class="btn btn-outline-danger btn-sm"
                                                @click="deleteSelectedOrderItems"
                                                :disabled="selectedOrderItemIndexes.length === 0">
                                                <i class="fa fa-trash me-1"></i> <span data-i18n="選択行を削除">選択行を削除</span>
                                            </button>
                                        </div>

                                        <!-- Quick Project Number Selection -->
                                        <div class="mt-3" v-if="selectedChildProjectsForDropdown.length > 0">
                                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                                <span class="text-muted small me-2"><span data-i18n="案件番号を素早く選択">案件番号を素早く選択</span>:</span>
                                                <button v-for="project in selectedChildProjectsForDropdown"
                                                    :key="project.id" type="button"
                                                    class="btn btn-outline-primary btn-sm"
                                                    @click="quickSelectProjectNumberForCheckedItems(project.id)"
                                                    :title="'案件番号: ' + (project.project_number || project.name) + ' をチェック済み商品に適用'"
                                                    :disabled="selectedOrderItemIndexes.length === 0">
                                                    {{ project.project_number || project.name }}
                                                </button>
                                                <button type="button" class="btn btn-outline-danger btn-sm ms-2"
                                                    @click="clearProjectNumbersForCheckedItems"
                                                    :title="'チェック済み商品の案件番号をクリア'"
                                                    :disabled="selectedOrderItemIndexes.length === 0">
                                                    <i class="fa fa-times me-1"></i> <span data-i18n="クリア">クリア</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Items validation error -->
                            <div v-if="quotationValidationErrors.items" class="col-12">
                                <div class="alert alert-danger">
                                    {{ quotationValidationErrors.items }}
                                </div>
                            </div>

                            <!-- Summary Information -->
                            <div class="col-12">
                                <div class="card">

                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label class="form-label"><span data-i18n="税抜価格">税抜価格</span></label>
                                                <input type="text" class="form-control"
                                                    :value="formatCurrency(newQuotation.total_amount)" readonly>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">
                                                    <span data-i18n="消費税等">消費税等</span> (%)
                                                </label>
                                                <input type="number" class="form-control ms-2"
                                                    v-model.number="newQuotation.tax_rate" min="0" max="100" step="1"
                                                    @input="calculateTotalAmount">

                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label"><span data-i18n="税額">税額</span></label>
                                                <input type="text" class="form-control"
                                                    :value="formatCurrency(newQuotation.total_amount * newQuotation.tax_rate / 100)"
                                                    readonly>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label"><span data-i18n="合計金額">合計金額</span></label>
                                                <input type="text" class="form-control"
                                                    :value="formatCurrency(newQuotation.total_with_tax)" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><span data-i18n="その他情報">その他情報</span></h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label"><span data-i18n="納入期限">納入期限</span></label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" id="quotation_delivery_date"
                                                        v-model="newQuotation.delivery_date" placeholder="納入期限を入力（任意）">
                                                    <!-- Hidden input for Flatpickr -->
                                                    <input type="hidden" id="quotation_delivery_date_picker" style="display: none;">
                                                    <button class="btn btn-outline-secondary" type="button"
                                                        @click="openDeliveryDatePicker" title="日付を選択">
                                                        <i class="fa fa-calendar"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" type="button"
                                                        @click="clearDeliveryDate" title="納入期限をクリア">
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label"><span data-i18n="納入場所">納入場所</span> <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control"
                                                    :class="{ 'is-invalid': quotationValidationErrors.delivery_location }"
                                                    v-model="newQuotation.delivery_location" placeholder="納入場所を入力">
                                                <div v-if="quotationValidationErrors.delivery_location" class="text-danger small mt-1">
                                                    {{ quotationValidationErrors.delivery_location }}
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label"><span data-i18n="取引方法">取引方法</span> <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control"
                                                    :class="{ 'is-invalid': quotationValidationErrors.payment_method }"
                                                    v-model="newQuotation.payment_method" placeholder="取引方法を入力">
                                                <div v-if="quotationValidationErrors.payment_method" class="text-danger small mt-1">
                                                    {{ quotationValidationErrors.payment_method }}
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label"><span data-i18n="有効期限">有効期限</span> <span class="text-danger">*</span></label>
                                                <select class="form-select" v-model="newQuotation.valid_until_type"
                                                    :class="{ 'is-invalid': quotationValidationErrors.valid_until }"
                                                    @change="onValidUntilTypeChange">
                                                    <option value="1_week">発行から1週間</option>
                                                    <option value="1_month" selected>発行から1か月</option>
                                                    <option value="custom">日付指定</option>
                                                </select>
                                                <input v-if="newQuotation.valid_until_type === 'custom'" type="text"
                                                    class="form-control mt-2" id="quotation_valid_until"
                                                    :class="{ 'is-invalid': quotationValidationErrors.valid_until }"
                                                    v-model="newQuotation.valid_until" placeholder="有効期限を選択">
                                                <div v-if="quotationValidationErrors.valid_until" class="text-danger small mt-1">
                                                    {{ quotationValidationErrors.valid_until }}
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label"><span data-i18n="ステータス">ステータス</span></label>
                                                <select class="form-select" v-model="newQuotation.status">
                                                    <option value="下書き" selected>下書き</option>
                                                    <option value="発行済み">発行済み</option>
                                                    <option value="承認済み">承認済み</option>
                                                    <option value="却下">却下</option>
                                                    <option value="調整">調整</option>
                                                    <option value="キャンセル">キャンセル</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label"><span data-i18n="備考">備考</span></label>
                                                <textarea class="form-control" v-model="newQuotation.notes"
                                                    rows="2"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-warning" @click="clearQuotationFormBackup"><span data-i18n="リセット">リセット</span></button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><span data-i18n="キャンセル">キャンセル</span></button>
                    <button type="button" class="btn btn-primary" @click="createQuotationWithDelay"
                        :disabled="creatingQuotation">
                        <span v-if="creatingQuotation" class="spinner-border spinner-border-sm me-1"></span>
                        <span data-i18n="作成">作成</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Quotation Modal -->
    <div class="modal fade" id="editQuotationModal" tabindex="-1" aria-labelledby="editQuotationModalLabel"
        aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xxl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editQuotationModalLabel"><span data-i18n="見積書編集">見積書編集</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="updateQuotation">
                        <div class="row g-3">
                            <!-- Header Information -->
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><span data-i18n="基本情報">基本情報</span></h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label"><span data-i18n="発行日">発行日</span> <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control"
                                                    :class="{ 'is-invalid': editQuotationValidationErrors.issue_date }"
                                                    id="edit_quotation_issue_date" v-model="editingQuotation.issue_date"
                                                    readonly>
                                                <div v-if="editQuotationValidationErrors.issue_date"
                                                    class="text-danger small mt-1">
                                                    {{ editQuotationValidationErrors.issue_date }}
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label"><span data-i18n="見積番号">見積番号</span> <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" class="form-control"
                                                    :class="{ 'is-invalid': editQuotationValidationErrors.quotation_number }"
                                                    v-model="editingQuotation.quotation_number" readonly>
                                                <div v-if="editQuotationValidationErrors.quotation_number"
                                                    class="text-danger small mt-1">
                                                    {{ editQuotationValidationErrors.quotation_number }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Child Projects Selection -->
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><span data-i18n="選択する案件">選択する案件</span></h6>
                                        <small class="text-muted"><span data-i18n="この見積書に関連する案件を選択してください">この見積書に関連する案件を選択してください</span></small>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <div class="d-flex justify-content-end align-items-center mb-2">
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary"
                                                            @click="selectAllChildProjectsForEdit">
                                                            <i class="fa fa-check-square me-1"></i> <span data-i18n="全選択">全選択</span>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-secondary"
                                                            @click="deselectAllChildProjectsForEdit">
                                                            <i class="fa fa-square me-1"></i> <span data-i18n="全解除">全解除</span>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="table-responsive"
                                                    style="max-height: 300px; overflow-y: auto;">
                                                    <table class="table table-sm table-hover">
                                                        <thead class="table-light sticky-top">
                                                            <tr>
                                                                <th style="width: 50px;">
                                                                    <input type="checkbox" class="form-check-input"
                                                                        @change="toggleAllChildProjectsForEdit"
                                                                        :checked="allChildProjectsSelectedForEdit"
                                                                        :indeterminate="someChildProjectsSelectedForEdit">
                                                                </th>
                                                                <th><span data-i18n="案件番号">案件番号</span></th>
                                                                <th><span data-i18n="案件名">案件名</span></th>
                                                                <th><span data-i18n="部署">部署</span></th>
                                                                <th><span data-i18n="受注形態">受注形態</span></th>
                                                                <th><span data-i18n="開始日">開始日</span></th>
                                                                <th v-if="!isCailyBranchUser"><span data-i18n="期限日">期限日</span></th>
                                                                <th><span data-i18n="現在のステータス">現在のステータス</span></th>
                                                                <th><span data-i18n="総額">総額</span></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="project in childProjects" :key="project.id"
                                                                :class="{ 'table-active': selectedChildProjectIdsForEdit.includes(parseInt(project.id)) }">
                                                                <td>
                                                                    <input type="checkbox" class="form-check-input"
                                                                        :value="parseInt(project.id)"
                                                                        v-model="selectedChildProjectIdsForEdit"
                                                                        @change="updateChildProjectSelectionForEdit">
                                                                </td>
                                                                <td>{{ project.project_number || '-' }}</td>
                                                                <td>
                                                                    <a :href="'../project/detail.php?id=' + project.id"
                                                                        class="text-decoration-none" target="_blank">
                                                                        {{ project.name }}
                                                                    </a>
                                                                </td>
                                                                <td>{{ project.department_name || '-' }}</td>
                                                                <td>
                                                                    <span
                                                                        v-if="project.project_order_type && project.project_order_type.split(',').length > 0">
                                                                        <span
                                                                            v-for="item in project.project_order_type.split(',')"
                                                                            :key="item.trim()"
                                                                            class="badge me-1"
                                                                            :class="getOrderTypeBadgeClass(item.trim())">{{ item.trim()
                                                                            }}</span>
                                                                    </span>
                                                                    <span v-else>-</span>
                                                                </td>
                                                                <td>{{ formatDateTime(project.start_date) || '-' }}</td>
                                                                <td v-if="!isCailyBranchUser">{{ formatDateTime(project.end_date) || '-' }}</td>
                                                                <td>
                                                                    <span v-if="project.is_kadai == 1" class="badge bg-warning">
                                                                        承認待ち
                                                                    </span>
                                                                    <span v-else class="badge"
                                                                        :class="getProjectStatusBadgeClass(project.status)">
                                                                        {{ getProjectStatusLabel(project.status) }}
                                                                    </span>
                                                                </td>
                                                                <td class="text-end">
                                                                    <span class="fw-bold text-primary">
                                                                        {{ formatPrice(project.amount ||
                                                                        project.total_amount || 0) }}
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                            <tr v-if="childProjects.length === 0">
                                                                <td colspan="10" class="text-center text-muted py-4">
                                                                    <span data-i18n="子プロジェクトがありません">子プロジェクトがありません</span>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="mt-2">
                                                    <small class="text-muted">
                                                        <span data-i18n="選択された子プロジェクト">選択された子プロジェクト</span>: {{ selectedChildProjectIdsForEdit.length }} / {{
                                                        childProjects.length }}
                                                    </small>
                                                </div>
                                                <div v-if="editQuotationValidationErrors.childProjects" class="mt-2">
                                                    <div class="text-danger small">
                                                        {{ editQuotationValidationErrors.childProjects }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sender Information -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><span data-i18n="受注者情報">受注者情報</span></h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label"><span data-i18n="会社名">会社名</span> <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control"
                                                :class="{ 'is-invalid': editQuotationValidationErrors.sender_company }"
                                                v-model="editingQuotation.sender_company"
                                                @input="editQuotationValidationErrors.sender_company = ''" required>
                                            <div v-if="editQuotationValidationErrors.sender_company"
                                                class="text-danger small mt-1">
                                                {{ editQuotationValidationErrors.sender_company }}
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><span data-i18n="住所">住所</span></label>
                                            <textarea class="form-control" v-model="editingQuotation.sender_address"
                                                rows="2"></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><span data-i18n="担当様">担当様</span></label>
                                            <input type="text" class="form-control"
                                                v-model="editingQuotation.sender_contact">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Receiver Information -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><span data-i18n="発注者情報">発注者情報</span></h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label"><span data-i18n="支社選択">支社選択</span></label>
                                            <select class="form-select" v-model="editingQuotation.selected_branch_id"
                                                @change="onBranchSelectForEdit">
                                                <option value=""><span data-i18n="支社を選択してください">支社を選択してください</span></option>
                                                <option v-for="branch in quotationBranches" :key="branch.id"
                                                    :value="branch.id">
                                                    {{ branch.name }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><span data-i18n="会社名">会社名</span> <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control"
                                                :class="{ 'is-invalid': editQuotationValidationErrors.receiver_company }"
                                                v-model="editingQuotation.receiver_company"
                                                @input="editQuotationValidationErrors.receiver_company = ''" required>
                                            <div v-if="editQuotationValidationErrors.receiver_company"
                                                class="text-danger small mt-1">
                                                {{ editQuotationValidationErrors.receiver_company }}
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><span data-i18n="住所">住所</span></label>
                                            <textarea class="form-control" v-model="editingQuotation.receiver_address"
                                                rows="2"></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">TEL</label>
                                            <input type="text" class="form-control"
                                                v-model="editingQuotation.receiver_tel">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">FAX</label>
                                            <input type="text" class="form-control"
                                                v-model="editingQuotation.receiver_fax">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><span data-i18n="登録番号">登録番号</span></label>
                                            <input type="text" class="form-control"
                                                v-model="editingQuotation.receiver_registration_number">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><span data-i18n="担当">担当</span></label>
                                            <select class="form-select" v-model="editingQuotation.receiver_contact"
                                                @change="onContactSelectForEdit">
                                                <option value=""><span data-i18n="担当者を選択してください">担当者を選択してください</span></option>
                                                <option v-for="user in quotationUsers" :key="user.id"
                                                    :value="user.realname">
                                                    {{ user.realname }}{{ user.is_inactive ? ' (退職済み)' : '' }}
                                                </option>
                                            </select>
                                            <!-- Seal display area -->
                                            <div v-if="selectedContactSealForEdit" class="mt-2">
                                                <div class="alert alert-info d-flex align-items-center">
                                                    <i class="bi bi-stamp me-2"></i>
                                                    <div>
                                                        <strong><span data-i18n="印鑑">印鑑</span>:</strong> {{ selectedContactSealForEdit.name }}
                                                        <br>
                                                        <img v-if="selectedContactSealForEdit.image_path"
                                                            :src="selectedContactSealForEdit.image_path" class="mt-1"
                                                            style="max-width: 60px; max-height: 60px; object-fit: contain;"
                                                            alt="印鑑画像">
                                                    </div>
                                                </div>
                                            </div>
                                            <div v-else-if="editingQuotation.receiver_contact && !selectedContactSealForEdit"
                                                class="mt-2">
                                                <div class="alert alert-warning d-flex align-items-center">
                                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                                    <span><span data-i18n="選択された担当者の印鑑が見つかりません">選択された担当者の印鑑が見つかりません</span>。</span>
                                                </div>
                                            </div>
                                            <!-- Warning for inactive user -->
                                            <div v-if="editingQuotation.receiver_contact && quotationUsers.find(u => u.realname === editingQuotation.receiver_contact && u.is_inactive)"
                                                class="mt-2">
                                                <div class="alert alert-info d-flex align-items-center">
                                                    <i class="bi bi-info-circle me-2"></i>
                                                    <span><span data-i18n="この担当者は退職済みです。">この担当者は退職済みです。</span><span data-i18n="印鑑は保存されたものが使用されます。">印鑑は保存されたものが使用されます。</span></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Subject -->
                        <div class="col-12 mb-3">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><span data-i18n="件名">件名</span> <span class="text-danger">*</span></h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <input type="text" class="form-control" 
                                            :class="{ 'is-invalid': editQuotationValidationErrors.subject }"
                                            v-model="editingQuotation.subject" 
                                            @input="editQuotationValidationErrors.subject = ''"
                                            placeholder="件名を入力してください" required>
                                        <div v-if="editQuotationValidationErrors.subject" class="text-danger small mt-1">
                                            {{ editQuotationValidationErrors.subject }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Order Items -->
                        <div class="col-12 mb-3">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><span data-i18n="商品明細">商品明細</span></h6>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-success"
                                            @click="showPriceListModal">
                                            <i class="fa fa-search me-1"></i> <span data-i18n="価格表から選択">価格表から選択</span>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            @click="addOrderItemForEdit">
                                            <i class="fa fa-plus me-1"></i> <span data-i18n="商品追加">商品追加</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered" id="edit-quotation-table">
                                            <thead>
                                                <tr>
                                                    <th style="width:30px;">
                                                        <i class="fa fa-arrows-alt text-muted" title="ドラッグして並び替え"></i>
                                                    </th>
                                                    <th style="width:36px;">
                                                        <input type="checkbox" class="form-check-input"
                                                            @change="selectAllOrderItemsForEdit"
                                                            :checked="allOrderItemsSelectedForEdit">
                                                    </th>
                                                    <th><span data-i18n="案件番号">案件番号</span></th>
                                                    <th><span data-i18n="件名">件名</span></th>
                                                    <th><span data-i18n="商品コード">商品コード</span></th>
                                                    <th><span data-i18n="タイプ">タイプ</span></th>
                                                    <th style="width:70px;"><span data-i18n="数量">数量</span></th>
                                                    <th style="width:70px;"><span data-i18n="単位">単位</span></th>
                                                    <th style="width:100px;"><span data-i18n="単価">単価</span></th>
                                                    <th style="width:100px;"><span data-i18n="金額">金額</span></th>
                                                    <th><span data-i18n="備考">備考</span></th>
                                                    <th><span data-i18n="操作">操作</span></th>
                                                </tr>
                                            </thead>
                                            <tbody id="edit-quotation-items-sortable">
                                                <tr v-if="editingQuotation.items.length === 0">
                                                    <td colspan="12" class="text-center text-muted py-4">
                                                        <span data-i18n="商品がありません">商品がありません</span>
                                                    </td>
                                                </tr>
                                                <tr v-for="(item, index) in editingQuotation.items" :key="index" class="sortable-item">
                                                    <td class="text-center">
                                                        <i class="fa fa-grip-vertical drag-handle text-muted cursor-move" 
                                                           title="ドラッグして移動"></i>
                                                    </td>
                                                    <td class="text-center">
                                                        <input type="checkbox" class="form-check-input"
                                                            :checked="selectedOrderItemIndexesForEdit.includes(index)"
                                                            @change="toggleSelectOrderItemForEdit(index)">
                                                    </td>
                                                    <td>
                                                        <select class="form-select form-select-sm"
                                                            :class="{ 'is-invalid': editQuotationValidationErrors.items && (!item.project_id || item.project_id === '' || item.project_id === null) }"
                                                            v-model="item.project_id"
                                                            @change="updateProjectTotalAmountForEdit(item.project_id, index); validateProjectIdSelectionForEdit();">
                                                            <option value="">選択してください</option>
                                                            <option
                                                                v-for="project in selectedChildProjectsForEditDropdown"
                                                                :key="project.id" :value="project.id">
                                                                {{ project.project_number || project.name }}
                                                            </option>
                                                        </select>
                                                        <small v-if="selectedChildProjectsForEditDropdown.length === 0"
                                                            class="text-muted d-block mt-1">
                                                            子プロジェクトが選択されていません
                                                        </small>
                                                        <div v-if="editQuotationValidationErrors.items && (!item.project_id || item.project_id === '' || item.project_id === null)"
                                                            class="invalid-feedback d-block">
                                                            <span data-i18n="案件番号は必須です">案件番号は必須です</span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm"
                                                            v-model="item.title" placeholder="件名">
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm"
                                                            v-model="item.product_code" placeholder="商品コード">
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm"
                                                            v-model="item.type" placeholder="タイプ">
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control form-control-sm"
                                                            v-model.number="item.quantity"
                                                            @input="calculateItemAmountForEdit(index)" min="0" step="1">
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm"
                                                            v-model="item.unit" placeholder="">
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control form-control-sm"
                                                            v-model.number="item.unit_price"
                                                            @input="calculateItemAmountForEdit(index)" min="0" step="1">
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm"
                                                            :value="formatCurrency(item.amount)" readonly>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm"
                                                            v-model="item.notes" placeholder="備考">
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <button type="button" class="btn btn-outline-secondary"
                                                                v-if="item.is_set"
                                                                @click="showEditSetModal(index)" title="編集">
                                                                <i class="fa fa-edit"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger"
                                                                @click="removeOrderItemForEdit(index)" title="削除">
                                                                <i class="fa fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <div class="text-muted small"><span data-i18n="選択中">選択中</span>: {{ selectedOrderItemIndexesForEdit.length }}
                                            / {{ editingQuotation.items.length }}</div>
                                        <button type="button" class="btn btn-outline-danger btn-sm"
                                            @click="deleteSelectedOrderItemsForEdit"
                                            :disabled="selectedOrderItemIndexesForEdit.length === 0">
                                            <i class="fa fa-trash me-1"></i> <span data-i18n="選択行を削除">選択行を削除</span>
                                        </button>
                                    </div>

                                    <!-- Quick Project Number Selection -->
                                    <div class="mt-3" v-if="selectedChildProjectsForEditDropdown.length > 0">
                                        <div class="d-flex flex-wrap gap-2 align-items-center">
                                            <span class="text-muted small me-2"><span data-i18n="案件番号を素早く選択">案件番号を素早く選択</span>:</span>
                                            <button v-for="project in selectedChildProjectsForEditDropdown"
                                                :key="project.id" type="button" class="btn btn-outline-primary btn-sm"
                                                @click="quickSelectProjectNumberForCheckedItemsForEdit(project.id)"
                                                :title="'案件番号: ' + (project.project_number || project.name) + ' をチェック済み商品に適用'"
                                                :disabled="selectedOrderItemIndexesForEdit.length === 0">
                                                {{ project.project_number || project.name }}
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-sm ms-2"
                                                @click="clearProjectNumbersForCheckedItemsForEdit"
                                                :title="'チェック済み商品の案件番号をクリア'"
                                                :disabled="selectedOrderItemIndexesForEdit.length === 0">
                                                <i class="fa fa-times me-1"></i> <span data-i18n="クリア">クリア</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Items validation error -->
                        <div v-if="editQuotationValidationErrors.items" class="col-12 mb-3">
                            <div class="alert alert-danger">
                                {{ editQuotationValidationErrors.items }}
                            </div>
                        </div>

                        <!-- Summary Information -->
                        <div class="col-12 mb-3">
                            <div class="card">

                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label"><span data-i18n="税抜価格">税抜価格</span></label>
                                            <input type="text" class="form-control"
                                                :value="formatCurrency(editingQuotation.total_amount)" readonly>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">
                                                <span data-i18n="消費税等">消費税等</span> (%)
                                            </label>
                                            <input type="number" class="form-control ms-2"
                                                v-model.number="editingQuotation.tax_rate" min="0" max="100" step="1"
                                                @input="calculateTotalAmountForEdit">

                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label"><span data-i18n="税額">税額</span></label>
                                            <input type="text" class="form-control"
                                                :value="formatCurrency(editingQuotation.total_amount * editingQuotation.tax_rate / 100)"
                                                readonly>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label"><span data-i18n="合計金額">合計金額</span></label>
                                            <input type="text" class="form-control"
                                                :value="formatCurrency(editingQuotation.total_with_tax)" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><span data-i18n="その他情報">その他情報</span></h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label"><span data-i18n="納入期限">納入期限</span></label>
                                            <div class="input-group">
                                                <input type="text" class="form-control"
                                                    id="edit_quotation_delivery_date"
                                                    v-model="editingQuotation.delivery_date" placeholder="納入期限を入力（任意）">
                                                <!-- Hidden input for Flatpickr -->
                                                <input type="hidden" id="edit_quotation_delivery_date_picker" style="display: none;">
                                                <button class="btn btn-outline-secondary" type="button"
                                                    @click="openDeliveryDatePickerForEdit" title="日付を選択">
                                                    <i class="fa fa-calendar"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" type="button"
                                                    @click="clearDeliveryDateForEdit" title="納入期限をクリア">
                                                    <i class="fa fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label"><span data-i18n="納入場所">納入場所</span> <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control"
                                                :class="{ 'is-invalid': editQuotationValidationErrors.delivery_location }"
                                                v-model="editingQuotation.delivery_location" placeholder="納入場所を入力">
                                            <div v-if="editQuotationValidationErrors.delivery_location" class="text-danger small mt-1">
                                                {{ editQuotationValidationErrors.delivery_location }}
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label"><span data-i18n="取引方法">取引方法</span> <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control"
                                                :class="{ 'is-invalid': editQuotationValidationErrors.payment_method }"
                                                v-model="editingQuotation.payment_method" placeholder="取引方法を入力">
                                            <div v-if="editQuotationValidationErrors.payment_method" class="text-danger small mt-1">
                                                {{ editQuotationValidationErrors.payment_method }}
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label"><span data-i18n="有効期限">有効期限</span> <span class="text-danger">*</span></label>
                                            <select class="form-select" v-model="editingQuotation.valid_until_type"
                                                :class="{ 'is-invalid': editQuotationValidationErrors.valid_until }"
                                                @change="onValidUntilTypeChangeForEdit">
                                                <option value="1_week">発行から1週間</option>
                                                <option value="1_month" selected>発行から1か月</option>
                                                <option value="custom">日付指定</option>
                                            </select>
                                            <input v-if="editingQuotation.valid_until_type === 'custom'" type="text"
                                                class="form-control mt-2" id="edit_quotation_valid_until"
                                                :class="{ 'is-invalid': editQuotationValidationErrors.valid_until }"
                                                v-model="editingQuotation.valid_until" placeholder="有効期限を選択">
                                            <div v-if="editQuotationValidationErrors.valid_until" class="text-danger small mt-1">
                                                {{ editQuotationValidationErrors.valid_until }}
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label"><span data-i18n="ステータス">ステータス</span></label>
                                            <select class="form-select" v-model="editingQuotation.status">
                                                <option value="下書き" selected>下書き</option>
                                                <option value="発行済み">発行済み</option>
                                                <option value="承認済み">承認済み</option>
                                                <option value="却下">却下</option>
                                                <option value="調整">調整</option>
                                                <option value="キャンセル">キャンセル</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label"><span data-i18n="備考">備考</span></label>
                                            <textarea class="form-control" v-model="editingQuotation.notes"
                                                rows="2"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-warning" @click="resetEditQuotationForm"><span data-i18n="リセット">リセット</span></button>
                    <button type="button" class="btn btn-secondary" @click="closeEditQuotationModal"><span data-i18n="キャンセル">キャンセル</span></button>
                    <button type="button" class="btn btn-primary" @click="updateQuotation" :disabled="updatingQuotation">
                        <span v-if="updatingQuotation" class="spinner-border spinner-border-sm me-1"></span>
                        <span data-i18n="更新">更新</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Quotation Modal -->
    <div class="modal fade" id="viewQuotationModal" tabindex="-1" aria-labelledby="viewQuotationModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" v-if="selectedQuotation">
                    <iframe 
                        :src="`quotation_view.php?id=${selectedQuotation.id}&t=${selectedQuotation.timestamp}`"
                        style="width: 100%; height: 80vh; border: none;"
                        frameborder="0">
                    </iframe>
                </div>
            </div>
        </div>
    </div>


    <!-- Price List Selection Modal -->
    <div class="modal fade" id="priceListModal" tabindex="-1" aria-labelledby="priceListModalLabel" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="priceListModalLabel"><span data-i18n="価格表から商品を選択">価格表から商品を選択</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Product Type Filter -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label"><span data-i18n="商品タイプフィルター">商品タイプフィルター</span></label>
                            <select class="form-select" v-model="selectedPriceListType"
                                @change="scheduleFilterPriceListProducts">
                                <option value="">すべてのタイプ</option>
                                <option value="新規">新規</option>
                                <option value="修正">修正</option>
                                <option value="その他">その他</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><span data-i18n="検索">検索</span></label>
                            <input type="text" class="form-control" v-model="priceListSearchTerm"
                                @input="scheduleFilterPriceListProducts" placeholder="コードまたは商品名で検索">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><span data-i18n="タグ検索">タグ検索</span></label>
                            <input type="text" class="form-control" v-model="priceListTagSearchTerm"
                                @input="scheduleFilterPriceListProducts" placeholder="タグで検索">
                        </div>
                    </div>
                    <!-- Pagination and bulk actions -->
                    <div class="row mb-2 align-items-center">
                        <div class="col-md-4 d-flex align-items-center gap-2">
                            <label class="me-2"><span data-i18n="表示件数">表示件数</span></label>
                            <select class="form-select form-select-sm w-auto" v-model.number="priceListPageSize"
                                @change="onChangePriceListPageSize">
                                <option :value="10">10</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                                <option :value="100">100</option>
                            </select>
                        </div>
                        <div class="col-md-8 d-flex justify-content-end align-items-center gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" @click="goToPrevPriceListPage"
                                :disabled="priceListPage <= 1">
                                <i class="fa fa-chevron-left"></i>
                            </button>
                            <span>{{ priceListPage }} / {{ totalPriceListPages }}</span>
                            <button type="button" class="btn btn-outline-secondary btn-sm" @click="goToNextPriceListPage"
                                :disabled="priceListPage >= totalPriceListPages">
                                <i class="fa fa-chevron-right"></i>
                            </button>
                            <span class="ms-2 text-muted">表示: {{ (priceListPage - 1) * priceListPageSize + 1 }} - {{
                                Math.min(priceListPage * priceListPageSize, filteredPriceListProducts.length) }} / {{
                                filteredPriceListProducts.length }}</span>
                            <button type="button" class="btn btn-outline-primary btn-sm ms-3" @click="selectAllFiltered"
                                :disabled="filteredPriceListProducts.length === 0">
                                <span data-i18n="フィルター結果を全選択">フィルター結果を全選択</span>
                            </button>
                        </div>
                    </div>




                    <!-- Products Table -->
                    <div class="table-responsive" id="price-list-table" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover table-sm">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>
                                        <input type="checkbox" class="form-check-input" @change="toggleSelectAll"
                                            :checked="allSelected">
                                    </th>
                                    <th><span data-i18n="コード">コード</span></th>
                                    <th><span data-i18n="商品名">商品名</span></th>
                                    <th><span data-i18n="タイプ">タイプ</span></th>
                                    <th><span data-i18n="タグ">タグ</span></th>
                                    <th><span data-i18n="単位">単位</span></th>
                                    <th><span data-i18n="単価">単価</span></th>
                                    <th><span data-i18n="操作">操作</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(product, idx) in paginatedPriceListProducts" :key="product.id"
                                    class="cursor-pointer"
                                    :class="{ 'table-active': idx === highlightedIndex, 'table-light': isProductAlreadyAdded(product) }"
                                    @click="!isProductAlreadyAdded(product) && toggleProductSelection(product, $event)">
                                    <td @click.stop>
                                        <input type="checkbox" class="form-check-input"
                                            :disabled="isProductAlreadyAdded(product)"
                                            :checked="selectedProducts.includes(product.id)" 
                                            @change="toggleProductSelection(product, $event)">
                                    </td>
                                    <td>{{ product.code }}</td>
                                    <td>{{ product.name }}</td>
                                    <td>{{ product.type }}</td>
                                    <td class="tags-cell">{{ product.tags }}</td>
                                    <td>{{ product.unit }}</td>
                                    <td>{{ formatPrice(product.price) }}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            @click.stop="selectSingleProduct(product)"
                                            :disabled="isProductAlreadyAdded(product)">
                                            <i class="fa fa-plus"></i> <span data-i18n="単品選択">単品選択</span>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredPriceListProducts.length === 0">
                                    <td colspan="8" class="text-center text-muted">
                                        <span data-i18n="商品が見つかりません">商品が見つかりません</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Selected Products Summary -->
                    <div v-if="selectedProducts.length > 0" class="mt-3">
                        <div class="card">
                            <div class="card-header d-flex align-items-center" style="gap: 8px;">
                                <h6 class="mb-0"><span data-i18n="選択された商品">選択された商品</span> ({{ selectedProducts.length }}件)</h6>
                                <input type="text" class="form-control form-control-sm ms-auto" v-model="displayedSetName"
                                    :placeholder="defaultSetName" style="width: 260px;">
                            </div>
                            <div class="card-body">
                                <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th><span data-i18n="コード">コード</span></th>
                                                <th><span data-i18n="商品名">商品名</span></th>
                                                <th><span data-i18n="タイプ">タイプ</span></th>
                                                <th style="width:70px;"><span data-i18n="数量">数量</span></th>
                                                <th style="width:70px;"><span data-i18n="単価">単価</span></th>
                                                <th><span data-i18n="金額">金額</span></th>
                                                <th><span data-i18n="操作">操作</span></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="product in getSelectedProductsList()" :key="product.id">
                                                <td>{{ product.code }}</td>
                                                <td>{{ product.name }}</td>
                                                <td>{{ product.type }}</td>
                                                <td>
                                                    <input type="number" min="1" step="1"
                                                        class="form-control form-control-sm"
                                                        v-model.number="selectedProductQuantities[product.id]"
                                                        @change="normalizeSelectedQuantity(product.id)"
                                                        style="width:100px;">
                                                </td>
                                                <td>{{ formatPrice(product.price) }}</td>
                                                <td>{{ formatPrice((selectedProductQuantities[product.id] || 1) *
                                                    product.price) }}</td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                                        @click="removeFromSelection(product.id)">
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-2">
                                    <strong><span data-i18n="合計金額">合計金額</span>: {{ formatPrice(getSelectedProductsTotal()) }}</strong>
                                </div>
                                <div class="mt-2 text-muted small">
                                    <span data-i18n="Shift + Click で範囲選択、スペース/Enter でハイライト行の選択切替、先頭チェックボックスはページ単位で選択/解除します。">Shift + Click で範囲選択、スペース/Enter でハイライト行の選択切替、先頭チェックボックスはページ単位で選択/解除します。</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <div>
                        <button type="button" class="btn btn-outline-primary me-2" @click="addSelectedProductsIndividually"
                            :disabled="selectedProducts.length === 0">
                            <i class="fa fa-plus me-1"></i> <span data-i18n="個別に追加">個別に追加</span> ({{ selectedProducts.length }}件)
                        </button>
                        <button type="button" class="btn btn-success" @click="addSelectedProductsAsSet"
                            :disabled="selectedProducts.length === 0">
                            <i class="fa fa-layer-group me-1"></i> <span data-i18n="選択セット追加">選択セット追加</span> ({{ selectedProducts.length }}件)
                        </button>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><span data-i18n="閉じる">閉じる</span></button>
                </div>
            </div>
        </div>
    </div>


    <!-- Edit Set Modal -->
    <div class="modal fade" id="editSetModal" tabindex="-1" aria-labelledby="editSetModalLabel" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSetModalLabel"><span data-i18n="セット編集">セット編集</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" v-if="editingSet">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th><span data-i18n="商品コード">商品コード</span></th>
                                    <th><span data-i18n="商品名">商品名</span></th>
                                    <th><span data-i18n="単価">単価</span></th>
                                    <th><span data-i18n="数量">数量</span></th>
                                    <th><span data-i18n="小計">小計</span></th>
                                    <th><span data-i18n="操作">操作</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="product in editingSet.products" :key="product.id">
                                    <td>{{ product.code }}</td>
                                    <td>{{ product.name }}</td>
                                    <td class="text-end">{{ formatPrice(product.price) }}</td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm" v-model="product.quantity"
                                            min="1" @input="updateEditingSetTotal()" style="width: 80px;">
                                    </td>
                                    <td class="text-end">¥{{ ((product.price || 0) * (product.quantity ||
                                        1)).toLocaleString() }}</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-danger"
                                            @click="removeProductFromSet(product.id)">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!editingSet.products || editingSet.products.length === 0">
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <span data-i18n="商品がありません">商品がありません</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><span data-i18n="キャンセル">キャンセル</span></button>
                    <button type="button" class="btn btn-primary" @click="saveEditedSet"><span data-i18n="保存">保存</span></button>
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
                                    <input type="text" class="form-control" v-model="selectedCustomer.branch" 
                                           :class="{ 'is-invalid': customerErrors.branch }" required>
                                    <div v-if="customerErrors.branch" class="invalid-feedback">
                                        {{ customerErrors.branch }}
                                    </div>
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
                                    <div class="input-group">
                                        <input type="text" class="form-control" v-model="selectedCustomer.phone" required>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="郵便番号">郵便番号</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" v-model="selectedCustomer.zip" required>
                                        <button class="btn btn-outline-primary waves-effect" type="button" @click.prevent="searchAddressCustomer">住所検索</button>
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
                                    <select ref="customerGuisDepartmentSelect" class="form-select" v-model="selectedCustomer.guis_department" required multiple>
                                        <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                                    </select>
                                    <div v-if="customerErrors.guis_department" class="text-danger small mt-1">{{ customerErrors.guis_department }}</div>
                                </div>
                            
                                <div class="col-md-12 mb-3">
                                    <label class="form-label"><span data-i18n="メモ">メモ</span></label>
                                    <textarea class="form-control" v-model="selectedCustomer.memo" required></textarea>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div v-else class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden"><span data-i18n="読み込み中">読み込み中</span>...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><span data-i18n="閉じる">閉じる</span></button>
                    <button v-if="isProjectManager" type="button" class="btn btn-primary" @click="updateCustomer" :disabled="updatingCustomer">
                        <span v-if="updatingCustomer" class="spinner-border spinner-border-sm me-1"></span>
                        <span data-i18n="更新">更新</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- New Customer Modal (建物詳細 edit mode) -->
    <div class="modal fade" id="newCustomerModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">新規顧客追加</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="saveNewCustomer">
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
                                <input type="text" class="form-control" v-model="newCustomer.phone" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">郵便番号</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" v-model="newCustomer.zip" required>
                                    <button class="btn btn-outline-primary waves-effect" type="button" @click.prevent="searchAddressNewCustomer">住所検索</button>
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
                                <select ref="newCustomerGuisDepartmentSelect" class="form-select select2" v-model="newCustomer.guis_department" required multiple>
                                    <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                                </select>
                                <div v-if="customerErrors.guis_department" class="text-danger small mt-1">{{ customerErrors.guis_department }}</div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-primary" @click="saveNewCustomer">保存</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Context menu: child project list -->
    <div v-if="childProjectContextMenuVisible"
         class="dropdown-menu show child-project-context-menu"
         :style="{ position: 'absolute', zIndex: 9999, left: childProjectContextMenuX + 'px', top: childProjectContextMenuY + 'px' }"
         @click.stop>
        <button v-if="childProjectContextMenuProject && canEditChildProject(childProjectContextMenuProject)"
                class="dropdown-item" type="button" @click.stop="openChildProjectEditFromContextMenu">
            <i class="fa fa-edit me-1"></i><span data-i18n="案件編集">案件編集</span>
        </button>
        <button class="dropdown-item" type="button" @click.stop="goToChildProjectDetailFromContextMenu">
            <i class="fa fa-external-link-alt me-1"></i><span data-i18n="詳細ページへ">詳細ページへ</span>
        </button>
        <button v-if="canEditBusinessDocuments" class="dropdown-item" type="button" @click.stop="openChildProjectPaymentFromContextMenu">
            <i class="fa fa-file-invoice me-1"></i><span data-i18n="決済情報を編集">決済情報を編集</span>
        </button>
    </div>
</div>



<?php
$view->footing();
?>

<style>
    .form-control-validation .invalid-feedback {
        display: block;
    }

    .modal-body {
        overflow-y: auto;
    }

    /* Tagify styles for child project modal */
    .tags-look-project-order-type .tagify__dropdown__item {
        display: inline-block;
        border-radius: 3px;
        padding: .3em .5em;
        border: 1px solid #CCC;
        background: #F3F3F3;
        margin: .2em;
        font-size: .85em;
        color: #000;
        transition: 0s;
    }

    .tags-look-project-order-type .tagify__dropdown__item--active {
        color: #fff;
    }

    .tags-look-project-order-type .tagify__dropdown__item:hover {
        background: #c1e4e6;
        border-color: #92c0c3;
        color: black;
    }

    .tags-look-project-order-type .tagify__dropdown__item--active {
        background: linear-gradient(45deg, #c1e4e6, #92c0c3);
        color: #fff;
    }

    .tags-look-project-order-type .tagify__dropdown__item--active:hover {
        background: linear-gradient(45deg, #c1e4e6, #92c0c3);
        color: #fff;
    }

    /* Ensure Tagify dropdown appears above modal */
    .modal .tagify__dropdown {
        z-index: 1060 !important;
    }

    .modal .tagify__dropdown__wrapper {
        z-index: 1060 !important;
    }

    /* Fix Tagify input styling in modal */
    .modal .tagify__input {
        min-height: 38px;
    }

    .modal .tagify__tag {
        margin: 2px;
    }

    /* Quotation view modal background */
    .quotation-view-body {
        background-color: #ffffff !important;
        padding: 40px !important;
    }

    .quotation-view-body .modal-content {
        background-color: #ffffff !important;
    }

    #viewQuotationModal .modal-content {
        background-color: #ffffff !important;
    }

    #viewQuotationModal .modal-body {
        background-color: #ffffff !important;
        max-height: 80vh;
        overflow-y: auto;
    }

    /* Quotation document styles */
    .quotation-document {
        font-family: 'Hiragino Kaku Gothic ProN', 'Yu Gothic', sans-serif;
        line-height: 1.6;
        background-color: #ffffff;
        padding: 20px;
        color: #000;
    }

    .quotation-document h2 {
        font-size: 1.8rem;
        font-weight: bold;
        color: #000;
        text-decoration: underline;
        text-decoration-thickness: 3px;
    }

    .quotation-document h5 {
        font-size: 1.2rem;
        font-weight: bold;
        color: #000;
        margin-bottom: 0.5rem;
    }

    .quotation-document h6 {
        font-size: 1rem;
        font-weight: bold;
        color: #000;
        margin-bottom: 0.5rem;
    }

    /* Summary tables styling */
    .quotation-summary-table,
    .quotation-details-table {
        font-size: 0.9rem;
        border: 2px solid #000 !important;
        background-color: #ffffff;
    }

    .quotation-summary-table th,
    .quotation-summary-table td,
    .quotation-details-table th,
    .quotation-details-table td {
        border: 1px solid #000 !important;
        padding: 8px 12px;
        vertical-align: middle;
        color: #000;
        background-color: #ffffff;
    }

    .quotation-summary-table .fw-bold,
    .quotation-details-table .fw-bold {
        font-weight: bold;
        background-color: #f8f9fa;
    }

    /* Items table styling */
    .quotation-items-table {
        font-size: 0.85rem;
        border: 2px solid #000 !important;
        background-color: #ffffff;
    }

    .quotation-items-table th {
        background-color: #f8f9fa !important;
        font-weight: bold;
        text-align: center;
        vertical-align: middle;
        border: 1px solid #000 !important;
        color: #000;
        padding: 8px 4px;
    }

    .quotation-items-table td {
        vertical-align: middle;
        border: 1px solid #000 !important;
        color: #000;
        background-color: #ffffff;
        padding: 6px 4px;
    }

    .quotation-items-table .table-secondary {
        background-color: #f8f9fa !important;
    }

    .quotation-items-table .table-secondary td {
        background-color: #f8f9fa !important;
        font-weight: bold;
    }

    /* Company seal styling */
    .company-seal {
        width: 60px;
        height: 60px;
        border: 2px solid #d32f2f;
        border-radius: 50%;
        background-color: #ffffff;
        position: relative;
    }

    .company-seal::after {
        content: "印";
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #d32f2f;
        font-weight: bold;
        font-size: 1.2rem;
    }

    /* Text styling */
    .quotation-details-text {
        line-height: 1.4;
        color: #000;
    }

    .sender-info {
        color: #000;
        font-size: 0.9rem;
    }

    .sender-info .fw-bold {
        font-weight: bold;
    }

    .quotation-document .text-end {
        text-align: right;
    }

    .quotation-document .card {
        border: 1px solid #dee2e6;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        background-color: #ffffff;
    }

    .quotation-document .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        font-weight: bold;
    }

    /* Print styles for quotation */
    @media print {
        /* Hide everything by default */
        body * {
            visibility: hidden;
        }
        
        /* Show only the quotation modal content */
        #viewQuotationModal,
        #viewQuotationModal * {
            visibility: visible;
        }
        
        /* Reset modal positioning for print */
        #viewQuotationModal .modal-dialog {
            position: static !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: none !important;
            transform: none !important;
        }
        
        #viewQuotationModal .modal-content {
            border: none !important;
            box-shadow: none !important;
            background: white !important;
        }
        
        #viewQuotationModal .modal-body {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .quotation-document {
            font-size: 11pt;
            padding: 15px !important;
            margin: 0 !important;
            box-shadow: none !important;
            border: none !important;
            background: white !important;
        }

        .quotation-document .table {
            font-size: 9pt;
        }
        
        .quotation-document h2 {
            font-size: 14pt;
        }
        
        .quotation-document h5 {
            font-size: 11pt;
        }

        .quotation-document .card {
            border: 1px solid #000;
            box-shadow: none;
        }

        /* Hide modal chrome elements */
        .modal-header,
        .modal-footer,
        .modal-backdrop,
        .navbar,
        .btn-close {
            display: none !important;
        }
        
        /* Page settings */
        @page {
            margin: 10mm;
            size: A4;
        }
        
        /* Ensure proper table borders for print */
        .quotation-summary-table,
        .quotation-details-table,
        .quotation-items-table {
            border-collapse: collapse !important;
        }
        
        .quotation-summary-table th,
        .quotation-summary-table td,
        .quotation-details-table th,
        .quotation-details-table td,
        .quotation-items-table th,
        .quotation-items-table td {
            border: 1px solid #000 !important;
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
        }
    }

    /* Price list modal styles */
    .cursor-pointer {
        cursor: pointer;
    }

    .cursor-pointer:hover {
        background-color: #f8f9fa;
    }

    .sticky-top {
        position: sticky;
        top: 0;
        z-index: 1020;
    }


    /* Checkbox styles for product selection */
    .form-check-input {
        cursor: pointer;
    }

    .form-check-input:checked {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }

    /* Selected products summary styles */
    .selected-products-summary {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        background-color: #f8f9fa;
    }

    .selected-products-summary .card-header {
        background-color: #e9ecef;
        border-bottom: 1px solid #dee2e6;
    }

    /* Table hover effect for selected products */
    .table-hover tbody tr:hover {
        background-color: rgba(0, 123, 255, 0.1);
    }

    /* Quotation status badge styles */

    .badge.bg-draft {
        background-color: #6c757d !important;
    }

    .badge.bg-issued {
        background-color: #0d6efd !important;
    }

    .badge.bg-approved {
        background-color: #198754 !important;
    }

    .badge.bg-rejected {
        background-color: #dc3545 !important;
    }

    .badge.bg-adjustment {
        background-color: #fd7e14 !important;
    }

    /* Quotation status dropdown styles */
    .quotation-status-dropdown .dropdown-toggle {
        border-radius: 0.375rem;
        font-weight: 500;
    }

    .quotation-status-dropdown .dropdown-toggle:focus {
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }

    .quotation-status-dropdown .dropdown-menu {
        min-width: 140px;
        padding: 0.5rem 0;
        border: 1px solid rgba(0, 0, 0, 0.15);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }

    .quotation-status-dropdown .dropdown-item {
        padding: 0.5rem 1rem;
        display: flex;
        align-items: center;
        transition: background-color 0.15s ease-in-out;
    }

    .quotation-status-dropdown .dropdown-item:hover {
        background-color: rgba(0, 123, 255, 0.1);
    }

    .quotation-status-dropdown .dropdown-item .badge {
        font-size: 0.7em;
        margin-right: 0.5rem;
    }
    
    /* Updated by column styling */
    .quotation-updated-by {
        font-size: 0.85rem;
        font-weight: 500;
    }
    
    .quotation-updated-by .badge {
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
    }
    #childProjectsTable {
        font-size: 0.8125rem;
    }
    #childProjectsTable td,
    #childProjectsTable th {
        padding: 0.35rem 0.25rem;
        vertical-align: middle;
        border: 1px solid #ccc;
    }
    #childProjectsTable .badge {
        font-size: 0.7rem;
        padding: 0.25em 0.45em;
    }
    #childProjectsTable .btn-sm {
        font-size: 0.7rem;
        padding: 0.15rem 0.35rem;
    }
  
    #childProjectsTable .fa-star {
        font-size: 0.95rem;
    }
    #edit-quotation-table td,
    #edit-quotation-table th {
        padding-left: 0.25rem;
        padding-right: 0.25rem;
    }
    #quotation-table td,
    #quotation-table th {
        padding-left: 0.25rem;
        padding-right: 0.25rem;
    }

    #price-list-table td {
        padding-top: 0.25rem;
        padding-bottom: 0.25rem;
    }

    /* Tags column styling */
    #price-list-table .tags-cell {
        max-width: 150px;
        word-wrap: break-word;
        font-size: 0.85rem;
        color: #6c757d;
    }

    .modal-xxl {
        width: 90vw;
        max-width: 1400px;
    }

    /* Sortable drag & drop styles */
    .cursor-move {
        cursor: move;
    }

    .drag-handle {
        opacity: 0.6;
        transition: opacity 0.2s ease;
    }

    .drag-handle:hover {
        opacity: 1;
    }

    .sortable-item.sortable-ghost {
        opacity: 0.4;
        background-color: #f8f9fa;
    }

    .sortable-item.sortable-drag {
        opacity: 1;
        transform: rotate(5deg);
        box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        background-color: #fff;
        border: 2px solid #007bff;
    }

    .sortable-item.sortable-chosen {
        background-color: #e3f2fd;
    }

    /* Disable text selection during drag */
    .sortable-item.sortable-drag {
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }
    
    /* Quotation history modal styling */
    .quotation-history-modal .list-group-item {
        border-left: none;
        border-right: none;
        border-radius: 0;
    }
    
    .quotation-history-modal .list-group-item:first-child {
        border-top: none;
    }
    
    .quotation-history-modal .list-group-item:last-child {
        border-bottom: none;
    }
    
    .quotation-history-modal .avatar-initial {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .quotation-history-modal .badge {
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
    }

    /* Avatar styles for logs */
    .avatar {
        display: inline-block;
        position: relative;
    }

    .avatar-sm {
        width: 32px;
        height: 32px;
    }

    .avatar-initial {
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.875rem;
    }

 
    /* List group styles for logs */
  

    /* Quill Editor styles */
    .custom_editor {
        position: relative;
    }
    
    .custom_editor_content {
        min-height: 120px;
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
    }
    
    .custom_editor_textarea {
        display: none;
    }
    
    .ql-editor {
        min-height: 120px;
    }

    /* Fix Flatpickr calendar position in modal */
    .modal .flatpickr-calendar {
        z-index: 1065 !important;
        position: fixed !important;
    }

    /* Ensure Flatpickr calendar is visible in modal */
    #createQuotationModal .flatpickr-calendar,
    #editQuotationModal .flatpickr-calendar {
        z-index: 1065 !important;
    }

</style>

<!-- Quill Editor CSS -->
<link rel="stylesheet" href="../assets/vendor/libs/quill/typography.css" />
<link rel="stylesheet" href="../assets/vendor/libs/quill/editor.css" />

<script>
    const PARENT_PROJECT_ID = <?php echo $parent_project_id; ?>;
    const CURRENT_USER_ID = '<?php echo $_SESSION['userid'] ?? ''; ?>';
    const CURRENT_USER_NAME = '<?php echo $_SESSION['realname'] ?? $_SESSION['userid'] ?? ''; ?>';
    const IS_PROJECT_MANAGER = <?php echo isset($_SESSION['isProjectManager']) && $_SESSION['isProjectManager'] ? 'true' : 'false'; ?>;
    window.IS_CAILY_BRANCH_USER = <?php echo $isCailyBranchUser ? 'true' : 'false'; ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="https://unpkg.com/@yaireo/tagify"></script>
<script src="../assets/vendor/libs/quill/quill.js"></script>
<script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>
<script src="/project/assets/js/yotei-field.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="assets/js/parent-project-error.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="assets/js/parent-project-detail.js?v=<?=PROJECT_CACHE_VERSION?>"></script>