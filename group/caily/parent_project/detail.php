<?php
require_once('../application/loader.php');
$parent_project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$parent_project_id) {
    header('Location: index.php');
    exit;
}
$view->heading('親プロジェクト詳細');
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
            <div class="card" :class="{ 'edit-mode': isEditMode }">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title"><span data-i18n="親プロジェクト詳細">親プロジェクト詳細</span></h5>
                        <div>
                            <button v-if="!isEditMode" class="btn btn-outline-warning btn-sm me-2" @click="toggleEditMode" title="編集">
                                <i class="fa fa-pencil-alt"></i>
                            </button>
                            <button v-if="isEditMode" class="btn btn-success btn-sm me-2" @click="saveParentProject" title="保存">
                                <i class="fa fa-save"></i>
                            </button>
                            <button v-if="isEditMode" class="btn btn-secondary btn-sm me-2" @click="cancelEdit" title="キャンセル">
                                <i class="fa fa-times"></i>
                            </button>
                            <button v-if="!isEditMode" class="btn btn-outline-danger btn-sm" @click="deleteParentProject" title="削除">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div v-if="parentProject" class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="会社名">会社名</span> <span class="text-danger">*</span></label>
                                <template v-if="isEditMode">
                                    <select id="company_name" class="form-select select2" v-model="parentProject.company_name" name="company_name" required @change="onCompanyChange">
                                        <option value="">選択してください</option>
                                        <option v-for="company in companies" :key="company.company_name" :value="company.company_name">
                                            {{ company.company_name }}
                                        </option>
                                    </select>
                                    <div v-if="validationErrors.company_name" class="invalid-feedback d-block">
                                        {{ validationErrors.company_name }}
                                    </div>
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control" :value="parentProject.company_name" readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="支店名">支店名</span></label>
                                <template v-if="isEditMode">
                                    <select id="branch_name" class="form-select select2" v-model="parentProject.branch_name" name="branch_name" @change="onBranchChange">
                                        <option value="">選択してください</option>
                                        <option v-for="branch in branches" :key="branch.branch" :value="branch.branch">
                                            {{ branch.branch }}
                                        </option>
                                    </select>
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control" :value="parentProject.branch_name || '-'" readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="担当様">担当様</span></label>
                                <template v-if="isEditMode">
                                    <select id="contact_name" class="form-select select2" v-model="parentProject.contact_name" name="contact_name">
                                        <option value="">選択してください</option>
                                        <option v-for="contact in contacts" :key="contact.id" :value="contact.name">
                                            {{ contact.name }}
                                        </option>
                                    </select>
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control" :value="parentProject.contact_name || '-'" readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="GUIS　受付者">GUIS　受付者</span></label>
                                <template v-if="isEditMode">
                                    <select id="guis_receiver" class="form-select select2" v-model="parentProject.guis_receiver" name="guis_receiver">
                                        <option value="">選択してください</option>
                                        <option v-for="user in users" :key="user.id" :value="user.user_name">
                                            {{ user.user_name }}
                                        </option>
                                    </select>
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control" :value="guisReceiverDisplayName || '-'" readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="依頼日">依頼日</span></label>
                                <template v-if="isEditMode">
                                    <div class="input-group">
                                        <input type="text" class="form-control" v-model="parentProject.request_date" id="request_date_picker" placeholder="YYYY/MM/DD HH:mm" autocomplete="off">
                                        <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                                        <button class="btn btn-outline-secondary" type="button" @click="setCurrentDateTime" title="現在時刻">
                                            現在時刻
                                        </button>
                                    </div>
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control" :value="formatDateTime(parentProject.request_date)" readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="工事番号">工事番号</span></label>
                                <template v-if="isEditMode">
                                    <input type="text" class="form-control" v-model="parentProject.construction_number" placeholder="工事番号を入力">
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control" :value="parentProject.construction_number || '-'" readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label">
                                    <span data-i18n="プロジェクト番号">プロジェクト番号</span>
                                    <button v-if="isEditMode" class="btn btn-sm btn-outline-primary py-0 small ms-2" @click="generateProjectNumber" title="生成">
                                        生成
                                    </button>
                                </label>
                                <template v-if="isEditMode">
                                    <input type="text" class="form-control" v-model="parentProject.project_number" placeholder="プロジェクト番号を入力">
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control" :value="parentProject.project_number || '-'" readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="工事支店">工事支店</span></label>
                                <template v-if="isEditMode">
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control tagify" v-model="parentProject.construction_branch" id="construction_branch_tags" name="construction_branch_tags">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearTagifyTags('construction_branch')" title="すべて削除"><i class="fa fa-times"></i></button>
                                    </div>
                                </template>
                                <template v-else>
                                    <div style="min-height:38px;">
                                        <span v-if="parentProject.construction_branch && parentProject.construction_branch.split(',').length > 0">
                                            <span v-for="item in parentProject.construction_branch.split(',')" :key="item.trim()" class="badge bg-primary me-1">{{ item.trim() }}</span>
                                        </span>
                                        <span v-else>-</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="案件名">案件名</span> <span class="text-danger">*</span></label>
                                <template v-if="isEditMode">
                                    <input type="text" class="form-control" v-model="parentProject.project_name" placeholder="案件名を入力" required>
                                    <div v-if="validationErrors.project_name" class="invalid-feedback d-block">
                                        {{ validationErrors.project_name }}
                                    </div>
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control" :value="parentProject.project_name" readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="建物規模">建物規模</span></label>
                                <template v-if="isEditMode">
                                    <input type="text" class="form-control" v-model="parentProject.scale" placeholder="規模を入力">
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control" :value="parentProject.scale || '-'" readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="種類1">種類1</span></label>
                                <template v-if="isEditMode">
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control tagify" v-model="parentProject.type1" id="type1_tags" name="type1_tags">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearTagifyTags('type1')" title="すべて削除"><i class="fa fa-times"></i></button>
                                    </div>
                                </template>
                                <template v-else>
                                    <div style="min-height:38px;">
                                        <span v-if="parentProject.type1 && parentProject.type1.split(',').length > 0">
                                            <span v-for="item in parentProject.type1.split(',')" :key="item.trim()" class="badge bg-primary me-1">{{ item.trim() }}</span>
                                        </span>
                                        <span v-else>-</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="種類2">種類2</span></label>
                                <template v-if="isEditMode">
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control tagify" v-model="parentProject.type2" id="type2_tags" name="type2_tags">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearTagifyTags('type2')" title="すべて削除"><i class="fa fa-times"></i></button>
                                    </div>
                                </template>
                                <template v-else>
                                    <div style="min-height:38px;">
                                        <span v-if="parentProject.type2 && parentProject.type2.split(',').length > 0">
                                            <span v-for="item in parentProject.type2.split(',')" :key="item.trim()" class="badge bg-primary me-1">{{ item.trim() }}</span>
                                        </span>
                                        <span v-else>-</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="希望納期">希望納期</span></label>
                                <template v-if="isEditMode">
                                    <div class="input-group">
                                        <input type="text" class="form-control" v-model="parentProject.desired_delivery_date" id="desired_delivery_date_picker" placeholder="YYYY/MM/DD" autocomplete="off">
                                        <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                                        <button class="btn btn-outline-secondary" type="button" @click="setTodayDate" title="今日">
                                            今日
                                        </button>
                                    </div>
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control" :value="formatDate(parentProject.desired_delivery_date)" readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="構造事務所">構造事務所</span></label>
                                <template v-if="isEditMode">
                                    <input type="text" class="form-control" v-model="parentProject.structural_office" placeholder="構造事務所を入力">
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control" :value="parentProject.structural_office || '-'" readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="ステータス">ステータス</span></label>
                                <div>
                                    <div class="btn-group" v-if="isEditMode">
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
                                                    {{ status.label }}
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    <div v-else>
                                        <span class="badge" :class="getStatusBadgeClass(parentProject.status)">{{ getStatusLabel(parentProject.status) }}</span>
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
                                                <input class="form-check-input" type="checkbox" id="materials_layout" v-model="materials_layout">
                                                <label class="form-check-label" for="materials_layout">
                                                    配置図
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="materials_rental" v-model="materials_rental">
                                                <label class="form-check-label" for="materials_rental">
                                                    家賃審査書
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="materials_contract" v-model="materials_contract">
                                                <label class="form-check-label" for="materials_contract">
                                                    契約図
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="materials_tac" v-model="materials_tac">
                                                <label class="form-check-label" for="materials_tac">
                                                    TAC図
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="materials_other" v-model="materials_other">
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
                                            <div class="col-md-4" v-if="parentProject.materials && parentProject.materials.includes('配置図')">
                                                <i class="fa fa-check text-success me-2"></i>配置図
                                            </div>
                                            <div class="col-md-4" v-if="parentProject.materials && parentProject.materials.includes('家賃審査書')">
                                                <i class="fa fa-check text-success me-2"></i>家賃審査書
                                            </div>
                                            <div class="col-md-4" v-if="parentProject.materials && parentProject.materials.includes('契約図')">
                                                <i class="fa fa-check text-success me-2"></i>契約図
                                            </div>
                                            <div class="col-md-4" v-if="parentProject.materials && parentProject.materials.includes('TAC図')">
                                                <i class="fa fa-check text-success me-2"></i>TAC図
                                            </div>
                                            <div class="col-md-4" v-if="parentProject.materials && parentProject.materials.includes('その他')">
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
                                    <textarea class="form-control" v-model="parentProject.notes" rows="3" placeholder="備考を入力してください"></textarea>
                                </template>
                                <template v-else>
                                    <div class="form-control-plaintext" style="white-space: pre-wrap;">{{ parentProject.notes || '-' }}</div>
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
                        <h5 class="card-title mb-0"><span data-i18n="子プロジェクト">子プロジェクト</span></h5>
                        <div>
                            <a :href="'../project/create.php?parent_project_id=' + (parentProject ? parentProject.id : '')" class="btn btn-success btn-sm">
                                <i class="fa fa-plus me-1"></i> <span data-i18n="子プロジェクト作成">子プロジェクト作成</span>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div v-if="childProjects.length > 0" class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>プロジェクト番号</th>
                                    <th>プロジェクト名</th>
                                    <th>部署</th>
                                    <th>ステータス</th>
                                    <th>進捗</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="project in childProjects" :key="project.id">
                                    <td>{{ project.project_number || '-' }}</td>
                                    <td>
                                        <a :href="'../project/detail.php?id=' + project.id" class="text-decoration-none">
                                            {{ project.name }}
                                        </a>
                                    </td>
                                    <td>{{ project.department_name || '-' }}</td>
                                    <td>
                                        <span class="badge" :class="getProjectStatusBadgeClass(project.status)">
                                            {{ getProjectStatusLabel(project.status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" :style="{ width: project.progress + '%' }">
                                                {{ project.progress }}%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a :href="'../project/detail.php?id=' + project.id" class="btn btn-outline-primary" title="詳細">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            <a :href="'../project/edit.php?id=' + project.id" class="btn btn-outline-secondary" title="編集">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-4">
                        <div class="text-muted">
                            <i class="fa fa-folder-open fa-2x mb-2"></i>
                            <p>子プロジェクトがありません</p>
                            <a :href="'../project/create.php?parent_project_id=' + (parentProject ? parentProject.id : '')" class="btn btn-primary btn-sm">
                                <i class="fa fa-plus me-1"></i> 子プロジェクトを作成
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
                    <h5 class="card-title mb-0"><span data-i18n="プロジェクト情報">プロジェクト情報</span></h5>
                </div>
                <div class="card-body">
                    <div class="text-center py-3">
                        <i class="fa fa-folder fs-1 text-primary"></i>
                        <p class="text-muted mt-2" data-i18n="親プロジェクトの詳細情報">親プロジェクトの詳細情報</p>
                    </div>
                    <div v-if="parentProject" class="mt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>子プロジェクト数:</span>
                            <span class="fw-bold">{{ childProjects.length }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>作成日:</span>
                            <span>{{ formatDate(parentProject.created_at) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>更新日:</span>
                            <span>{{ formatDate(parentProject.updated_at) }}</span>
                        </div>
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

</style>

<script>
const PARENT_PROJECT_ID = <?php echo $parent_project_id; ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="assets/js/parent-project-detail.js?v=<?=CACHE_VERSION?>"></script> 