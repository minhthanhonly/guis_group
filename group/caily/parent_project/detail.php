<?php
require_once('../application/loader.php');
$parent_project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$parent_project_id) {
    header('Location: index.php');
    exit;
}
$view->heading('建物詳細');
?>
<div id="app" class="container-fluid mt-4" v-cloak>

    <div class="row">
        <!-- Back button -->
        <div class="col-12 mb-3">
            <a href="index.php" class="btn btn-outline-primary">
                <i class="fa fa-arrow-left me-1"></i> <span data-i18n="戻る">戻る</span>
            </a>
        </div>

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
                                <a class="nav-link active" aria-current="page" href="detail.php?id=<?php echo $parent_project_id; ?>">建物詳細</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="attachment.php?id=<?php echo $parent_project_id; ?>">添付ファイル</a>
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
                        <h5 class="card-title"><span data-i18n="建物詳細">建物詳細</span></h5>
                        <div>
                            <button v-if="!isEditMode" class="btn btn-outline-warning btn-sm me-2"
                                @click="toggleEditMode" title="編集">
                                <i class="fa fa-pencil-alt"></i>
                            </button>
                            <button v-if="isEditMode" class="btn btn-success btn-sm me-2" @click="saveParentProject"
                                title="保存">
                                <i class="fa fa-save"></i>
                            </button>
                            <button v-if="isEditMode" class="btn btn-secondary btn-sm me-2" @click="cancelEdit"
                                title="キャンセル">
                                <i class="fa fa-times"></i>
                            </button>
                            <button v-if="!isEditMode" class="btn btn-outline-danger btn-sm"
                                @click="deleteParentProject" title="削除">
                                <i class="fa fa-trash"></i>
                            </button>
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
                                    <input type="text" class="form-control" :value="parentProject.company_name"
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
                                    <input type="text" class="form-control" :value="parentProject.branch_name || '-'"
                                        readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="担当様">担当様</span></label>
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
                                    <input type="text" class="form-control" :value="parentProject.contact_name || '-'"
                                        readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="GUIS　受付者">GUIS　受付者</span></label>
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
                                <label class="form-label">
                                    <span data-i18n="プロジェクト番号">プロジェクト番号</span>
                                </label>
                                <input type="text" class="form-control" :value="parentProject.project_number || '-'"
                                    readonly>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="工事支店">工事支店</span></label>
                                <template v-if="isEditMode">
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control tagify"
                                            v-model="parentProject.construction_branch" id="construction_branch_tags"
                                            name="construction_branch_tags">
                                        <button class="btn btn-outline-secondary btn-sm" type="button"
                                            @click="clearTagifyTags('construction_branch')" title="すべて削除"><i
                                                class="fa fa-times"></i></button>
                                    </div>
                                </template>
                                <template v-else>
                                    <div style="min-height:38px;">
                                        <span
                                            v-if="parentProject.construction_branch && parentProject.construction_branch.split(',').length > 0">
                                            <span v-for="item in parentProject.construction_branch.split(',')"
                                                :key="item.trim()" class="badge bg-primary me-1">{{ item.trim()
                                                }}</span>
                                        </span>
                                        <span v-else>-</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="案件名">案件名</span> <span
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
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="希望納期">希望納期</span></label>
                                <template v-if="isEditMode">
                                    <div class="input-group">
                                        <input type="text" class="form-control"
                                            v-model="parentProject.desired_delivery_date"
                                            id="desired_delivery_date_picker" placeholder="YYYY/MM/DD"
                                            autocomplete="off">
                                        <button class="btn btn-outline-secondary" type="button" @click="setTodayDate"
                                            title="今日">
                                            今日
                                        </button>
                                    </div>
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control"
                                        :value="formatDate(parentProject.desired_delivery_date)" readonly>
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
                                <label class="form-label"><span data-i18n="ステータス">ステータス</span></label>
                                <div>
                                    <div class="btn-group" v-if="isEditMode">
                                        <button type="button"
                                            class="btn btn-sm dropdown-toggle waves-effect waves-light"
                                            :class="getParentProjectStatusButtonClass(parentProject.status)" id="statusDropdown"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                            {{ getStatusLabel(parentProject.status) }}
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li v-for="status in statuses" :key="status.value">
                                                <a class="dropdown-item waves-effect" href="javascript:void(0);"
                                                    @click="selectStatus(status.value)">
                                                    {{ status.label }}
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    <div v-else>
                                        <span class="badge" :class="getStatusBadgeClass(parentProject.status)">{{
                                            getStatusLabel(parentProject.status) }}</span>
                                    </div>
                                </div>
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
                        <div class="col-12">
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
                        </div>
                    </div>
                    <div v-else class="text-center py-5">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Child Projects -->
            <div class="card mt-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><span data-i18n="案件依頼">案件依頼</span></h5>
                        <div>
                            <button @click="showCreateChildProjectModal" class="btn btn-success btn-sm">
                                <i class="fa fa-plus me-1"></i> <span data-i18n="案件依頼作成">案件依頼作成</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div v-if="childProjects.length > 0" class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>案件番号</th>
                                    <th>受注形態</th>
                                    <th>案件名</th>
                                    <th>部署</th>
                                    <th>開始日</th>
                                    <th>期限日</th>
                                    <th>ステータス</th>
                                    <th>進捗</th>
                                    <th>総額</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="project in childProjects" :key="project.id"
                                    :class="{ 'table-active': selectedChildProjectIds.includes(project.id) }">
                                    <td>{{ project.project_number || '-' }}</td>
                                    <td>
                                        <span
                                            v-if="project.project_order_type && project.project_order_type.split(',').length > 0">
                                            <span v-for="item in project.project_order_type.split(',')"
                                                :key="item.trim()" class="badge me-1" :class="getOrderTypeBadgeClass(item.trim())">{{ item.trim() }}</span>
                                        </span>
                                        <span v-else>-</span>
                                    </td>
                                    <td>
                                        <a :href="'../project/detail.php?id=' + project.id"
                                            class="text-decoration-none">
                                            {{ project.name }}
                                        </a>
                                    </td>
                                    <td>{{ project.department_name || '-' }}</td>
                                    <td>{{ formatDateTime(project.start_date) || '-' }}</td>
                                    <td>{{ formatDateTime(project.end_date) || '-' }}</td>
                                    <td>
                                        <span v-if="project.is_kadai == 1" class="badge bg-warning">
                                            承認待ち
                                        </span>
                                        <span v-else class="badge" :class="getProjectStatusBadgeClass(project.status)">
                                            {{ getProjectStatusLabel(project.status) }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold">{{ project.progress }}%</span>
                                    </td>
                                    <td class="text-end">
                                        <span class="fw-bold text-primary">
                                            {{ formatPrice(project.amount || project.total_amount || 0) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a :href="'../project/detail.php?id=' + project.id"
                                                class="btn btn-outline-primary" title="詳細">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            <button class="btn btn-outline-secondary" title="編集"
                                                @click="showEditChildProjectModal(project)">
                                                <i class="fa fa-edit"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="childProjects.length === 0">
                                    <td colspan="10" class="text-center text-muted py-4">
                                        子プロジェクトがありません
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-4">
                        <div class="text-muted">
                            <i class="fa fa-folder-open fa-2x mb-2"></i>
                            <p>案件依頼がありません</p>
                            <button @click="showCreateChildProjectModal" class="btn btn-primary btn-sm">
                                <i class="fa fa-plus me-1"></i> 案件依頼を作成
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Quotations -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">見積書</h5>
                        <button @click="showCreateQuotationModal" class="btn btn-primary btn-sm">
                            <i class="fa fa-plus me-1"></i> 新規見積書
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div v-if="loading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">読み込み中...</span>
                        </div>
                    </div>
                    <div v-else-if="quotations && quotations.length > 0" class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>見積番号</th>
                                    <th>件名</th>
                                    <th>プロジェクト番号</th>
                                    <th>作成日</th>
                                    <th>金額</th>
                                    <th>ステータス</th>
                                    <th>更新者</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="quotation in quotations" :key="quotation.id">
                                    <td>{{ quotation.quotation_number || '-' }}</td>
                                    <td>{{ quotation.subject || '-' }}</td>
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
                                    <td>{{ formatDateTime(quotation.created_at) }}</td>
                                    <td>{{ formatPrice(quotation.total_with_tax) }}</td>
                                    <td>
                                        <div class="dropdown quotation-status-dropdown">
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
                                            <button class="btn btn-outline-primary" title="表示"
                                                @click="showQuotationModal(quotation)">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary" title="編集"
                                                @click="editQuotation(quotation)">
                                                <i class="fa fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-info" title="履歴"
                                                @click="showQuotationHistory(quotation)">
                                                <i class="fa fa-history"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" title="削除"
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
                            <p>見積書がありません</p>
                            <button @click="showCreateQuotationModal" class="btn btn-primary btn-sm">
                                <i class="fa fa-plus me-1"></i> 見積書を作成
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

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
                                            <span v-if="log.user_image">
                                                <img :src="'/assets/upload/avatar/' + log.user_image" alt="avatar" class="rounded-circle" width="32" height="32">
                                            </span>
                                            <div class="avatar avatar-sm" v-else>
                                                <span class="avatar-initial rounded-circle bg-label-primary">
                                                    {{ getInitials(log.username ? log.username : (log.realname ? log.realname : '?')) }}
                                                </span>
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
                                            <span class="me-2">{{ log.note }}</span>
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
                    <h5 class="modal-title" id="createChildProjectModalLabel">案件依頼を作成</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="createChildProject">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label">案件名 <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="newChildProject.name" required>
                                    <div v-if="childProjectValidationErrors.name" class="invalid-feedback d-block">
                                        {{ childProjectValidationErrors.name }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label">部署 <span class="text-danger">*</span></label>
                                    <select class="form-select" v-model="newChildProject.department_id" required>
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
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label">案件番号 <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" v-model="newChildProject.project_number"
                                            readonly required>
                                        <button type="button" class="btn btn-outline-primary"
                                            @click="generateChildProjectNumber">
                                            <i class="fa fa-refresh"></i> 再生成
                                        </button>
                                    </div>
                                    <div v-if="childProjectValidationErrors.project_number"
                                        class="invalid-feedback d-block">
                                        {{ childProjectValidationErrors.project_number }}
                                    </div>
                                    <small class="form-text text-muted">
                                        建物番号 + "-" + 連番
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-4 col-xl-3">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label">開始日 <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="newChildProject.start_date"
                                        id="start_date_picker" placeholder="YYYY/MM/DD HH:mm" autocomplete="off"
                                        required>
                                    <div v-if="childProjectValidationErrors.start_date"
                                        class="invalid-feedback d-block">
                                        {{ childProjectValidationErrors.start_date }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 col-xl-3">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label">期限日 <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="newChildProject.end_date"
                                        id="end_date_picker" placeholder="YYYY/MM/DD HH:mm" autocomplete="off" required>
                                    <div v-if="childProjectValidationErrors.end_date" class="invalid-feedback d-block">
                                        {{ childProjectValidationErrors.end_date }}
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
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">説明</label>
                                    <textarea class="form-control" v-model="newChildProject.description"
                                        rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-primary" @click="createChildProject"
                        :disabled="creatingChildProject">
                        <span v-if="creatingChildProject" class="spinner-border spinner-border-sm me-1"></span>
                        作成
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
                    <h5 class="modal-title" id="editChildProjectModalLabel">案件依頼を編集</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="updateChildProject">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label">案件名 <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="editingChildProject.name" required>
                                    <div v-if="editChildProjectValidationErrors.name" class="invalid-feedback d-block">
                                        {{ editChildProjectValidationErrors.name }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label">部署 <span class="text-danger">*</span></label>
                                    <select class="form-select" v-model="editingChildProject.department_id" required>
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
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label">案件番号 <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="editingChildProject.project_number"
                                        required readonly>
                                    <div v-if="editChildProjectValidationErrors.project_number"
                                        class="invalid-feedback d-block">
                                        {{ editChildProjectValidationErrors.project_number }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 col-xl-3">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label">開始日 <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="editingChildProject.start_date"
                                        id="edit_start_date_picker" placeholder="YYYY/MM/DD HH:mm" autocomplete="off"
                                        required>
                                    <div v-if="editChildProjectValidationErrors.start_date"
                                        class="invalid-feedback d-block">
                                        {{ editChildProjectValidationErrors.start_date }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 col-xl-3">
                                <div class="mb-3 form-control-validation">
                                    <label class="form-label">期限日 <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="editingChildProject.end_date"
                                        id="edit_end_date_picker" placeholder="YYYY/MM/DD HH:mm" autocomplete="off"
                                        required>
                                    <div v-if="editChildProjectValidationErrors.end_date"
                                        class="invalid-feedback d-block">
                                        {{ editChildProjectValidationErrors.end_date }}
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
                            <div class="col-12">
                                <div class="mb-3">
                                    <label class="form-label">説明</label>
                                    <textarea class="form-control" v-model="editingChildProject.description"
                                        rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-primary" @click="updateChildProject"
                        :disabled="updatingChildProject">
                        <span v-if="updatingChildProject" class="spinner-border spinner-border-sm me-1"></span>
                        更新
                    </button>
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
                    <h5 class="modal-title" id="createQuotationModalLabel">見積書作成</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="createQuotation">
                        <div class="row g-3">
                            <!-- Header Information -->
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">基本情報</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">発行日 <span class="text-danger">*</span></label>
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
                                                <label class="form-label">見積番号 <span
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
                                        <h6 class="mb-0">選択する子プロジェクト</h6>
                                        <small class="text-muted">この見積書に関連する子プロジェクトを選択してください</small>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <label class="form-label mb-0">子プロジェクト一覧</label>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary"
                                                            @click="selectAllChildProjects">
                                                            <i class="fa fa-check-square me-1"></i> 全選択
                                                        </button>
                                                        <button type="button" class="btn btn-outline-secondary"
                                                            @click="deselectAllChildProjects">
                                                            <i class="fa fa-square me-1"></i> 全解除
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
                                                                <th>プロジェクト番号</th>
                                                                <th>案件名</th>
                                                                <th>部署</th>
                                                                <th>受注形態</th>
                                                                <th>開始日</th>
                                                                <th>期限日</th>
                                                                <th>現在のステータス</th>
                                                                <th>総額</th>
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
                                                                <td>{{ formatDateTime(project.end_date) || '-' }}</td>
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
                                                                    子プロジェクトがありません
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="mt-2">
                                                    <small class="text-muted">
                                                        選択された子プロジェクト: {{ selectedChildProjectIds.length }} / {{
                                                        childProjects.filter(p => !projectsUsedInActiveQuotations.has(parseInt(p.id))).length }}
                                                        (利用可能) / {{ childProjects.length }} (全体)
                                                    </small>
                                                    <small v-if="projectsUsedInActiveQuotations.size > 0" class="d-block text-warning mt-1">
                                                        <i class="fa fa-warning me-1"></i>
                                                        {{ projectsUsedInActiveQuotations.size }}個のプロジェクトは他の見積書で既に使用されているため選択できません
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
                                        <h6 class="mb-0">受注者情報</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">会社名 <span class="text-danger">*</span></label>
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
                                            <label class="form-label">住所</label>
                                            <textarea class="form-control" v-model="newQuotation.sender_address"
                                                rows="2"></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">担当様</label>
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
                                        <h6 class="mb-0">発注者情報</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">支社選択</label>
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
                                            <label class="form-label">会社名 <span class="text-danger">*</span></label>
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
                                            <label class="form-label">住所</label>
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
                                            <label class="form-label">登録番号</label>
                                            <input type="text" class="form-control"
                                                v-model="newQuotation.receiver_registration_number">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">担当</label>
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
                                                        <strong>印鑑:</strong> {{ selectedContactSeal.name }}
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
                                                    <span>選択された担当者の印鑑が見つかりません。</span>
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
                                        <h6 class="mb-0">件名 <span class="text-danger">*</span></h6>
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
                                        <h6 class="mb-0">商品明細</h6>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-success"
                                                @click="showPriceListModal">
                                                <i class="fa fa-search me-1"></i> 価格表から選択
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                @click="addOrderItem">
                                                <i class="fa fa-plus me-1"></i> 商品追加
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
                                                        <th>プロジェクト番号</th>
                                                        <th>件名</th>
                                                        <th>商品コード</th>
                                                        <th style="width:70px;">数量</th>
                                                        <th style="width:70px;">単位</th>
                                                        <th style="width:100px;">単価</th>
                                                        <th style="width:100px;">金額</th>
                                                        <th>備考</th>
                                                        <th>操作</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="quotation-items-sortable">
                                                    <tr v-if="newQuotation.items.length === 0">
                                                        <td colspan="11" class="text-center text-muted py-4">
                                                            商品がありません
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
                                                                v-model="item.project_id"
                                                                @change="updateProjectTotalAmount(item.project_id, index)">
                                                                <option value="">選択してください</option>
                                                                <option
                                                                    v-for="project in selectedChildProjectsForDropdown"
                                                                    :key="project.id" :value="project.id">
                                                                    {{ project.project_number || project.name }}
                                                                </option>
                                                            </select>
                                                            <small v-if="selectedChildProjectsForDropdown.length === 0"
                                                                class="text-muted d-block mt-1">
                                                                子プロジェクトが選択されていません
                                                            </small>
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
                                                                    <i class="fa fa-edit"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-outline-danger"
                                                                    @click="removeOrderItem(index)" title="削除">
                                                                    <i class="fa fa-trash"></i>
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
                                                <i class="fa fa-trash me-1"></i> 選択行を削除
                                            </button>
                                        </div>

                                        <!-- Quick Project Number Selection -->
                                        <div class="mt-3" v-if="selectedChildProjectsForDropdown.length > 0">
                                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                                <span class="text-muted small me-2">プロジェクト番号を素早く選択:</span>
                                                <button v-for="project in selectedChildProjectsForDropdown"
                                                    :key="project.id" type="button"
                                                    class="btn btn-outline-primary btn-sm"
                                                    @click="quickSelectProjectNumberForCheckedItems(project.id)"
                                                    :title="'プロジェクト番号: ' + (project.project_number || project.name) + ' をチェック済み商品に適用'"
                                                    :disabled="selectedOrderItemIndexes.length === 0">
                                                    {{ project.project_number || project.name }}
                                                </button>
                                                <button type="button" class="btn btn-outline-danger btn-sm ms-2"
                                                    @click="clearProjectNumbersForCheckedItems"
                                                    :title="'チェック済み商品のプロジェクト番号をクリア'"
                                                    :disabled="selectedOrderItemIndexes.length === 0">
                                                    <i class="fa fa-times me-1"></i>クリア
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
                                                <label class="form-label">税抜価格</label>
                                                <input type="text" class="form-control"
                                                    :value="formatCurrency(newQuotation.total_amount)" readonly>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">
                                                    消費税等 (%)
                                                </label>
                                                <input type="number" class="form-control ms-2"
                                                    v-model.number="newQuotation.tax_rate" min="0" max="100" step="1"
                                                    @input="calculateTotalAmount">

                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">税額</label>
                                                <input type="text" class="form-control"
                                                    :value="formatCurrency(newQuotation.total_amount * newQuotation.tax_rate / 100)"
                                                    readonly>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">合計金額</label>
                                                <input type="text" class="form-control"
                                                    :value="formatCurrency(newQuotation.total_with_tax)" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">その他情報</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">納入期限</label>
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
                                                <label class="form-label">納入場所 <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control"
                                                    :class="{ 'is-invalid': quotationValidationErrors.delivery_location }"
                                                    v-model="newQuotation.delivery_location" placeholder="納入場所を入力">
                                                <div v-if="quotationValidationErrors.delivery_location" class="text-danger small mt-1">
                                                    {{ quotationValidationErrors.delivery_location }}
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">取引方法 <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control"
                                                    :class="{ 'is-invalid': quotationValidationErrors.payment_method }"
                                                    v-model="newQuotation.payment_method" placeholder="取引方法を入力">
                                                <div v-if="quotationValidationErrors.payment_method" class="text-danger small mt-1">
                                                    {{ quotationValidationErrors.payment_method }}
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">有効期限 <span class="text-danger">*</span></label>
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
                                                <label class="form-label">ステータス</label>
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
                                                <label class="form-label">備考</label>
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
                    <button type="button" class="btn btn-warning" @click="clearQuotationFormBackup">リセット</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-primary" @click="createQuotationWithDelay"
                        :disabled="creatingQuotation">
                        <span v-if="creatingQuotation" class="spinner-border spinner-border-sm me-1"></span>
                        作成
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
                    <h5 class="modal-title" id="editQuotationModalLabel">見積書編集</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="updateQuotation">
                        <div class="row g-3">
                            <!-- Header Information -->
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">基本情報</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">発行日 <span class="text-danger">*</span></label>
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
                                                <label class="form-label">見積番号 <span
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
                                        <h6 class="mb-0">選択する子プロジェクト</h6>
                                        <small class="text-muted">この見積書に関連する子プロジェクトを選択してください</small>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <label class="form-label mb-0">子プロジェクト一覧</label>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary"
                                                            @click="selectAllChildProjectsForEdit">
                                                            <i class="fa fa-check-square me-1"></i> 全選択
                                                        </button>
                                                        <button type="button" class="btn btn-outline-secondary"
                                                            @click="deselectAllChildProjectsForEdit">
                                                            <i class="fa fa-square me-1"></i> 全解除
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
                                                                <th>プロジェクト番号</th>
                                                                <th>案件名</th>
                                                                <th>部署</th>
                                                                <th>受注形態</th>
                                                                <th>開始日</th>
                                                                <th>期限日</th>
                                                                <th>現在のステータス</th>
                                                                <th>総額</th>
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
                                                                <td>{{ formatDateTime(project.end_date) || '-' }}</td>
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
                                                                    子プロジェクトがありません
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="mt-2">
                                                    <small class="text-muted">
                                                        選択された子プロジェクト: {{ selectedChildProjectIdsForEdit.length }} / {{
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
                                        <h6 class="mb-0">受注者情報</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">会社名 <span class="text-danger">*</span></label>
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
                                            <label class="form-label">住所</label>
                                            <textarea class="form-control" v-model="editingQuotation.sender_address"
                                                rows="2"></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">担当様</label>
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
                                        <h6 class="mb-0">発注者情報</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">支社選択</label>
                                            <select class="form-select" v-model="editingQuotation.selected_branch_id"
                                                @change="onBranchSelectForEdit">
                                                <option value="">支社を選択してください</option>
                                                <option v-for="branch in quotationBranches" :key="branch.id"
                                                    :value="branch.id">
                                                    {{ branch.name }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">会社名 <span class="text-danger">*</span></label>
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
                                            <label class="form-label">住所</label>
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
                                            <label class="form-label">登録番号</label>
                                            <input type="text" class="form-control"
                                                v-model="editingQuotation.receiver_registration_number">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">担当</label>
                                            <select class="form-select" v-model="editingQuotation.receiver_contact"
                                                @change="onContactSelectForEdit">
                                                <option value="">担当者を選択してください</option>
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
                                                        <strong>印鑑:</strong> {{ selectedContactSealForEdit.name }}
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
                                                    <span>選択された担当者の印鑑が見つかりません。</span>
                                                </div>
                                            </div>
                                            <!-- Warning for inactive user -->
                                            <div v-if="editingQuotation.receiver_contact && quotationUsers.find(u => u.realname === editingQuotation.receiver_contact && u.is_inactive)"
                                                class="mt-2">
                                                <div class="alert alert-info d-flex align-items-center">
                                                    <i class="bi bi-info-circle me-2"></i>
                                                    <span>この担当者は退職済みです。印鑑は保存されたものが使用されます。</span>
                                                </div>
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
                                    <h6 class="mb-0">件名</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">件名 <span class="text-danger">*</span></label>
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
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">商品明細</h6>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-success"
                                            @click="showPriceListModal">
                                            <i class="fa fa-search me-1"></i> 価格表から選択
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            @click="addOrderItemForEdit">
                                            <i class="fa fa-plus me-1"></i> 商品追加
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
                                                    <th>プロジェクト番号</th>
                                                    <th>件名</th>
                                                    <th>商品コード</th>
                                                    <th style="width:70px;">数量</th>
                                                    <th style="width:70px;">単位</th>
                                                    <th style="width:100px;">単価</th>
                                                    <th style="width:100px;">金額</th>
                                                    <th>備考</th>
                                                    <th>操作</th>
                                                </tr>
                                            </thead>
                                            <tbody id="edit-quotation-items-sortable">
                                                <tr v-if="editingQuotation.items.length === 0">
                                                    <td colspan="11" class="text-center text-muted py-4">
                                                        商品がありません
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
                                                            v-model="item.project_id"
                                                            @change="updateProjectTotalAmountForEdit(item.project_id, index)">
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
                                        <div class="text-muted small">選択中: {{ selectedOrderItemIndexesForEdit.length }}
                                            / {{ editingQuotation.items.length }}</div>
                                        <button type="button" class="btn btn-outline-danger btn-sm"
                                            @click="deleteSelectedOrderItemsForEdit"
                                            :disabled="selectedOrderItemIndexesForEdit.length === 0">
                                            <i class="fa fa-trash me-1"></i> 選択行を削除
                                        </button>
                                    </div>

                                    <!-- Quick Project Number Selection -->
                                    <div class="mt-3" v-if="selectedChildProjectsForEditDropdown.length > 0">
                                        <div class="d-flex flex-wrap gap-2 align-items-center">
                                            <span class="text-muted small me-2">プロジェクト番号を素早く選択:</span>
                                            <button v-for="project in selectedChildProjectsForEditDropdown"
                                                :key="project.id" type="button" class="btn btn-outline-primary btn-sm"
                                                @click="quickSelectProjectNumberForCheckedItemsForEdit(project.id)"
                                                :title="'プロジェクト番号: ' + (project.project_number || project.name) + ' をチェック済み商品に適用'"
                                                :disabled="selectedOrderItemIndexesForEdit.length === 0">
                                                {{ project.project_number || project.name }}
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-sm ms-2"
                                                @click="clearProjectNumbersForCheckedItemsForEdit"
                                                :title="'チェック済み商品のプロジェクト番号をクリア'"
                                                :disabled="selectedOrderItemIndexesForEdit.length === 0">
                                                <i class="fa fa-times me-1"></i>クリア
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Items validation error -->
                        <div v-if="editQuotationValidationErrors.items" class="col-12">
                            <div class="alert alert-danger">
                                {{ editQuotationValidationErrors.items }}
                            </div>
                        </div>

                        <!-- Summary Information -->
                        <div class="col-12">
                            <div class="card">

                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label">税抜価格</label>
                                            <input type="text" class="form-control"
                                                :value="formatCurrency(editingQuotation.total_amount)" readonly>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">
                                                消費税等 (%)
                                            </label>
                                            <input type="number" class="form-control ms-2"
                                                v-model.number="editingQuotation.tax_rate" min="0" max="100" step="1"
                                                @input="calculateTotalAmountForEdit">

                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">税額</label>
                                            <input type="text" class="form-control"
                                                :value="formatCurrency(editingQuotation.total_amount * editingQuotation.tax_rate / 100)"
                                                readonly>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">合計金額</label>
                                            <input type="text" class="form-control"
                                                :value="formatCurrency(editingQuotation.total_with_tax)" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">その他情報</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">納入期限</label>
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
                                            <label class="form-label">納入場所 <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control"
                                                :class="{ 'is-invalid': editQuotationValidationErrors.delivery_location }"
                                                v-model="editingQuotation.delivery_location" placeholder="納入場所を入力">
                                            <div v-if="editQuotationValidationErrors.delivery_location" class="text-danger small mt-1">
                                                {{ editQuotationValidationErrors.delivery_location }}
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">取引方法 <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control"
                                                :class="{ 'is-invalid': editQuotationValidationErrors.payment_method }"
                                                v-model="editingQuotation.payment_method" placeholder="取引方法を入力">
                                            <div v-if="editQuotationValidationErrors.payment_method" class="text-danger small mt-1">
                                                {{ editQuotationValidationErrors.payment_method }}
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">有効期限 <span class="text-danger">*</span></label>
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
                                            <label class="form-label">ステータス</label>
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
                                            <label class="form-label">備考</label>
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
                    <button type="button" class="btn btn-warning" @click="resetEditQuotationForm">リセット</button>
                    <button type="button" class="btn btn-secondary" @click="closeEditQuotationModal">キャンセル</button>
                    <button type="button" class="btn btn-primary" @click="updateQuotation" :disabled="updatingQuotation">
                        <span v-if="updatingQuotation" class="spinner-border spinner-border-sm me-1"></span>
                        更新
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
                    <h5 class="modal-title" id="priceListModalLabel">価格表から商品を選択</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Product Type Filter -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">商品タイプフィルター</label>
                            <select class="form-select" v-model="selectedPriceListType"
                                @change="scheduleFilterPriceListProducts">
                                <option value="">すべてのタイプ</option>
                                <option value="新規">新規</option>
                                <option value="修正">修正</option>
                                <option value="その他">その他</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">検索</label>
                            <input type="text" class="form-control" v-model="priceListSearchTerm"
                                @input="scheduleFilterPriceListProducts" placeholder="コードまたは商品名で検索">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">タグ検索</label>
                            <input type="text" class="form-control" v-model="priceListTagSearchTerm"
                                @input="scheduleFilterPriceListProducts" placeholder="タグで検索">
                        </div>
                    </div>
                    <!-- Pagination and bulk actions -->
                    <div class="row mb-2 align-items-center">
                        <div class="col-md-4 d-flex align-items-center gap-2">
                            <label class="me-2">表示件数</label>
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
                                フィルター結果を全選択
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
                                    <th>コード</th>
                                    <th>商品名</th>
                                    <th>タイプ</th>
                                    <th>タグ</th>
                                    <th>単位</th>
                                    <th>単価</th>
                                    <th>操作</th>
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
                                            <i class="fa fa-plus"></i> 単品選択
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredPriceListProducts.length === 0">
                                    <td colspan="8" class="text-center text-muted">
                                        商品が見つかりません
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Selected Products Summary -->
                    <div v-if="selectedProducts.length > 0" class="mt-3">
                        <div class="card">
                            <div class="card-header d-flex align-items-center" style="gap: 8px;">
                                <h6 class="mb-0">選択された商品 ({{ selectedProducts.length }}件)</h6>
                                <input type="text" class="form-control form-control-sm ms-auto" v-model="displayedSetName"
                                    :placeholder="defaultSetName" style="width: 260px;">
                            </div>
                            <div class="card-body">
                                <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>コード</th>
                                                <th>商品名</th>
                                                <th style="width:70px;">数量</th>
                                                <th style="width:70px;">単価</th>
                                                <th>金額</th>
                                                <th>操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="product in getSelectedProductsList()" :key="product.id">
                                                <td>{{ product.code }}</td>
                                                <td>{{ product.name }}</td>
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
                                    <strong>合計金額: {{ formatPrice(getSelectedProductsTotal()) }}</strong>
                                </div>
                                <div class="mt-2 text-muted small">
                                    Shift + Click で範囲選択、スペース/Enter でハイライト行の選択切替、先頭チェックボックスはページ単位で選択/解除します。
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <div>
                        <button type="button" class="btn btn-outline-primary me-2" @click="addSelectedProductsIndividually"
                            :disabled="selectedProducts.length === 0">
                            <i class="fa fa-plus me-1"></i> 個別に追加 ({{ selectedProducts.length }}件)
                        </button>
                        <button type="button" class="btn btn-success" @click="addSelectedProductsAsSet"
                            :disabled="selectedProducts.length === 0">
                            <i class="fa fa-layer-group me-1"></i> 選択セット追加 ({{ selectedProducts.length }}件)
                        </button>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
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
                    <h5 class="modal-title" id="editSetModalLabel">セット編集</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" v-if="editingSet">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>商品コード</th>
                                    <th>商品名</th>
                                    <th>単価</th>
                                    <th>数量</th>
                                    <th>小計</th>
                                    <th>操作</th>
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
                                        商品がありません
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-primary" @click="saveEditedSet">保存</button>
                </div>
            </div>
        </div>
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
</style>

<script>
    const PARENT_PROJECT_ID = <?php echo $parent_project_id; ?>;
    const CURRENT_USER_ID = '<?php echo $_SESSION['userid'] ?? ''; ?>';
    const CURRENT_USER_NAME = '<?php echo $_SESSION['realname'] ?? $_SESSION['userid'] ?? ''; ?>';
</script>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="https://unpkg.com/@yaireo/tagify"></script>
<script src="assets/js/parent-project-detail.js?v=<?= CACHE_VERSION ?>"></script>