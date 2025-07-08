<?php

require_once('../application/loader.php');
$view->heading('プロジェクト管理');

?>

<div id="app" class="container-fluid mt-4" v-cloak>
    <nav class="navbar navbar-expand-lg bg-dark mb-12">
        <div class="container-fluid">
            <span class="navbar-brand" href="javascript:void(0)"></span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-start" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item" v-for="department in departments" :key="department.id" :class="{ 'active bg-primary text-white rounded-3': selectedDepartment && selectedDepartment.id === department.id, 'd-none': department.can_project == 0 }">
                        <a href="#" class="nav-link" @click="viewProjects(department)" >{{ department.name }}</a>
                    </li>
                </ul>
                <div class="d-flex gap-2">
                    <a v-if="canAddProject()" :href="createUrl" class="btn btn-primary">
                        <i class="fa fa-plus me-1"></i> <span data-i18n="新規プロジェクト">新規プロジェクト</span>
                    </a>
                    <a href="project_gantt.php" class="btn btn-info">
                        <i class="fa fa-chart-bar me-1"></i> <span data-i18n="ガントチャート">ガントチャート</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>
    <div class="card">
        <div class="card-body">
            <div class="btn-group">
                <button 
                    v-for="status in statuses" 
                    :key="status.key"
                    class="btn btn-sm"
                    :data-i18n="status.name"
                    :class="{
                        [`btn-label-${status.color}`]: !selectedStatus || selectedStatus?.key !== status.key,
                        [`btn-${status.color}`]: selectedStatus?.key === status.key,
                        'active': selectedStatus?.key === status.key
                    }"
                    @click="filterProjectByStatus(status)"
                >
                    {{ status.name }}
                </button>
            </div>
            <table id="projectTable" class="table table-striped">
                
            </table>
        </div>
    </div>

     <!-- New Project Modal -->
    <div class="modal fade" id="newProjectModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><span v-if="!isEdit">新規プロジェクト</span><span v-if="isEdit">プロジェクト編集</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form @submit.prevent="saveProject">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label">お施主様名 <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" v-model="newProject.name" name="name" required>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label">部署 <span class="text-danger">*</span></label>
                                        <select class="form-select" v-model="newProject.department_id" name="department_id" required disabled>
                                            <option value="">選択してください</option>
                                            <option v-for="department in departments" :key="department.id" :value="department.id">
                                                {{ department.name }}
                                            </option>
                                        </select>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label">会社名 <span class="text-danger">*</span></label>
                                        <select id="category_id" class="form-select select2" v-model="newProject.category_id" name="category_id" required @change="onCategoryChange">
                                            <option value="">選択してください</option>
                                            <option v-for="category in categories" :key="category.id" :value="category.id">
                                                {{ category.name }}
                                            </option>
                                        </select>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label">支店名 <span class="text-danger">*</span></label>
                                        <select id="company_name" class="form-select select2" v-model="newProject.company_name" name="company_name" required @change="onCompanyChange">
                                            <option value="">選択してください</option>
                                            <option v-for="company in companies" :key="company.company_name" :value="company.company_name">
                                                {{ company.company_name }}
                                            </option>
                                        </select>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label">担当者名 <span class="text-danger">*</span></label>
                                        <div class="d-flex gap-2 justify-content-between">
                                            <select id="customer_id" class="form-select select2 flex-shrink-1" v-model="newProject.customer_id" name="customer_id" required>
                                                <option value="">選択してください</option>
                                                <option v-for="contact in contacts" :key="contact.id" :value="contact.id">
                                                    {{ contact.name }}
                                                </option>
                                            </select>
                                            <button class="badge bg-primary rounded-pill flex-shrink-0" @click="openNewCustomerModal">+</button>
                                        </div>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label">建物規模</label>
                                        <input type="text" class="form-control" v-model="newProject.building_size" name="building_size" required>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label">建物種類</label>
                                        <input type="text" class="form-control" v-model="newProject.building_type" name="building_type" required>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label">案件番号</label>
                                        <input type="text" class="form-control" v-model="newProject.project_number" name="project_number" required>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label">工事番号</label>
                                        <input type="text" class="form-control" v-model="newProject.buiding_number" name="buiding_number" required>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label">工事支店名</label>
                                        <input type="text" class="form-control" v-model="newProject.building_branch" name="building_branch" required>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label">受注形態</label>
                                        <input type="text" class="form-control tagify" v-model="newProject.project_order_type" id="project_order_type" name="project_order_type" required>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label">優先度</label>
                                        <select class="form-select" v-model="newProject.priority" name="priority" required>
                                            <option value="low">低</option>
                                            <option value="medium">中</option>
                                            <option value="high">高</option>
                                            <option value="urgent">緊急</option>
                                        </select>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label">案件状況</label>
                                        <select class="form-select" v-model="newProject.status" name="status" required>
                                            <option value="draft">下書き</option>
                                            <option value="open">オープン</option>
                                            <option value="confirming">確認中</option>
                                            <option value="in_progress">進行中</option>
                                            <option value="paused">一時停止</option>
                                            <option value="completed">完了</option>
                                            <option value="cancelled">キャンセル</option>
                                        </select>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label">開始日</label>
                                        <input type="date" class="form-control" v-model="newProject.start_date" id="start_date" name="start_date" required>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label">期限日</label>
                                        <input type="date" class="form-control" v-model="newProject.end_date" id="end_date" name="end_date" required>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label">チーム</label>
                                        <input class="form-control" type="text" name="team_tags" required>
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label">管理</label>
                                        <input class="form-control" type="text" name="manager_tags" required>
                                        <input class="form-control" type="hidden" name="members" v-model="newProject.manager">
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3 form-control-validation">
                                        <label class="form-label">メンバー</label>
                                        <input class="form-control" type="text" name="members_tags" required>
                                        <input class="form-control" type="hidden" name="members" v-model="newProject.members">
                                        <div class="invalid-feedback"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">メモ</label>
                                <textarea class="form-control" v-model="newProject.description" name="description" rows="3"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="button" class="btn btn-primary" @click="saveProject">保存</button>
                    </div>
                </div>
            </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">削除確認</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>本当にこのプロジェクトを削除しますか？</p>
                    <p class="text-danger">この操作は取り消せません。</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-danger" @click="confirmDelete">削除</button>
                </div>
            </div>
        </div>
    </div>
</div>
 

<?php
$view->footing();
?>

<style>
/* Time remaining badge styling */
.badge.pulse-animation {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
    }
}

/* Badge styling for time remaining in end date column */
#projectTable .d-flex.flex-column .badge {
    font-size: 0.7rem;
    padding: 0.2rem 0.4rem;
    border-radius: 0.375rem;
    font-weight: normal;
    transition: all 0.2s ease;
    max-width: fit-content;
}

#projectTable .d-flex.flex-column .badge:hover {
    transform: scale(1.05);
}

/* Ensure proper spacing in end date column */
#projectTable td {
    vertical-align: middle;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="assets/js/project-list.js"></script>