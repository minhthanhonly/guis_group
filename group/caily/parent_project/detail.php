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
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><span data-i18n="親プロジェクト詳細">親プロジェクト詳細</span></h5>
                        <div>
                            <a :href="'edit.php?id=' + parentProject.id" class="btn btn-primary btn-sm me-2" title="編集">
                                <i class="fa fa-edit"></i>
                            </a>
                            <button class="btn btn-danger btn-sm" @click="deleteParentProject" title="削除">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div v-if="parentProject" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">会社名</label>
                            <p class="form-control-plaintext">{{ parentProject.company_name }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">支店名</label>
                            <p class="form-control-plaintext">{{ parentProject.branch_name || '-' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">担当様</label>
                            <p class="form-control-plaintext">{{ parentProject.contact_name || '-' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">GUIS　受付者</label>
                            <p class="form-control-plaintext">{{ parentProject.guis_receiver || '-' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">依頼日</label>
                            <p class="form-control-plaintext">{{ formatDate(parentProject.request_date) }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">工事番号</label>
                            <p class="form-control-plaintext">{{ parentProject.construction_number || '-' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">案件名</label>
                            <p class="form-control-plaintext">{{ parentProject.project_name }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">規模</label>
                            <p class="form-control-plaintext">{{ parentProject.scale || '-' }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">種類1</label>
                            <p class="form-control-plaintext">{{ parentProject.type1 || '-' }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">種類2</label>
                            <p class="form-control-plaintext">{{ parentProject.type2 || '-' }}</p>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">種類3</label>
                            <p class="form-control-plaintext">{{ parentProject.type3 || '-' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">依頼</label>
                            <p class="form-control-plaintext">{{ parentProject.request_type || '-' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">希望納期</label>
                            <p class="form-control-plaintext">{{ formatDate(parentProject.desired_delivery_date) }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">構造事務所</label>
                            <p class="form-control-plaintext">{{ parentProject.structural_office || '-' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">ステータス</label>
                            <p class="form-control-plaintext">
                                <span class="badge" :class="getStatusBadgeClass(parentProject.status)">
                                    {{ getStatusLabel(parentProject.status) }}
                                </span>
                            </p>
                        </div>
                        <div class="col-12" v-if="parentProject.materials">
                            <label class="form-label fw-bold">資料</label>
                            <div class="form-control-plaintext" style="white-space: pre-wrap;">{{ parentProject.materials }}</div>
                        </div>
                        <div class="col-12" v-if="parentProject.notes">
                            <label class="form-label fw-bold">備考</label>
                            <div class="form-control-plaintext" style="white-space: pre-wrap;">{{ parentProject.notes }}</div>
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
                            <a :href="'../project/create.php?parent_project_id=' + parentProject.id" class="btn btn-success btn-sm">
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
                            <a :href="'../project/create.php?parent_project_id=' + parentProject.id" class="btn btn-primary btn-sm">
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
.form-control-plaintext {
    padding: 0.375rem 0;
    margin-bottom: 0;
    color: #212529;
    background-color: transparent;
    border: solid transparent;
    border-width: 1px 0;
}

.progress {
    background-color: #e9ecef;
}

.progress-bar {
    background-color: #007bff;
}
</style>

<script>
const PARENT_PROJECT_ID = <?php echo $parent_project_id; ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="assets/js/parent-project-detail.js?v=<?=CACHE_VERSION?>"></script> 