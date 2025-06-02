<?php

require_once('../application/loader.php');
$view->heading('プロジェクト管理');

?>
<div id="app"  class="container-fluid mt-4" v-cloak>
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
                <button class="btn btn-primary" @click="openNewProjectModal">
                    <i class="bi bi-plus"></i> 新規プロジェクト
                </button>
            </div>
        </div>
    </nav>
    <div class="card">
        
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button 
                    v-for="status in statuses" 
                    :key="status.key"
                    class="btn btn-sm"
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
                            <div class="mb-3">
                                <label class="form-label">お施主様名 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" v-model="newProject.name" required>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3 form-group">
                                        <label class="form-label">会社名 <span class="text-danger">*</span></label>
                                        <select id="category_id" class="form-select select2" v-model="newProject.category_id" required @change="onCategoryChange">
                                            <option value="">選択してください</option>
                                            <option v-for="category in categories" :key="category.id" :value="category.id">
                                                {{ category.name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3 form-group">
                                        <label class="form-label">支店名 <span class="text-danger">*</span></label>
                                        <select id="company_name" class="form-select select2" v-model="newProject.company_name" required @change="onCompanyChange">
                                            <option value="">選択してください</option>
                                            <option v-for="company in companies" :key="company.company_name" :value="company.company_name">
                                                {{ company.company_name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3 form-group">
                                        <label class="form-label">担当者名 <span class="text-danger">*</span></label>
                                        <select id="customer_id" class="form-select select2" v-model="newProject.customer_id" required>
                                            <option value="">選択してください</option>
                                            <option v-for="contact in contacts" :key="contact.id" :value="contact.id">
                                                {{ contact.name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">建物規模</label>
                                        <input type="text" class="form-control" v-model="newProject.building_size">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">建物種類</label>
                                        <input type="text" class="form-control" v-model="newProject.building_type">
                                    </div>
                                </div>
                                <!-- <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">案件番号</label>
                                        <input type="text" class="form-control" v-model="newProject.project_number">
                                    </div>
                                </div> -->
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">連絡番号</label>
                                        <input type="text" class="form-control" v-model="newProject.project_number">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">受注形態</label>
                                        <select class="form-select" v-model="newProject.project_order_type">
                                            <option value="">選択してください</option>
                                            <option value="new">新規</option>
                                            <option value="edit">修正</option>
                                            <option value="custom">カスタム</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">部署 <span class="text-danger">*</span></label>
                                        <select class="form-select" v-model="newProject.department_id" required disabled>
                                            <option value="">選択してください</option>
                                            <option v-for="department in departments" :key="department.id" :value="department.id">
                                                {{ department.name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">開始日</label>
                                        <input type="date" class="form-control" v-model="newProject.start_date" id="start_date">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">終了日</label>
                                        <input type="date" class="form-control" v-model="newProject.end_date" id="end_date">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label">プロジェクトメンバー</label>
                                        <select class="form-select" v-model="newProject.members" multiple style="height: 150px;">
                                            <option v-for="user in users" :key="user.userid" :value="user.userid">
                                                {{ user.realname }}
                                            </option>
                                        </select>
                                        <input type="text" id="TagifyUserList" name="TagifyUserList" v-model="newProject.members">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">案件状況</label>
                                        <select class="form-select" v-model="newProject.status" required>
                                            <option value="draft">下書き</option>
                                            <option value="open">オープン</option>
                                            <option value="in_progress">進行中</option>
                                            <option value="completed">完了</option>
                                            <option value="paused">一時停止</option>
                                            <option value="cancelled">キャンセル</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">優先度</label>
                                        <select class="form-select" v-model="newProject.priority">
                                            <option value="low">低</option>
                                            <option value="medium">中</option>
                                            <option value="high">高</option>
                                            <option value="urgent">緊急</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">予定時間</label>
                                        <input type="number" class="form-control" v-model="newProject.estimated_hours" step="0.5">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">金額</label>
                                        <input type="number" class="form-control" v-model="newProject.amount">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">メモ</label>
                                <textarea class="form-control" v-model="newProject.description" rows="3"></textarea>
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

<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="assets/js/project-list.js"></script>