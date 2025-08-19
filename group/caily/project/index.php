<?php

require_once('../application/loader.php');
$view->heading('プロジェクト管理');

?>

<div id="app" class="container-fluid mt-4 mb-5" v-cloak>
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
                    <!-- <a v-if="canAddProject()" :href="createUrl" class="btn btn-primary">
                        <i class="fa fa-plus me-1"></i> <span data-i18n="新規プロジェクト">新規プロジェクト</span>
                    </a> -->
                    <a href="project_gantt.php" class="btn btn-info">
                        <i class="fa fa-chart-bar me-1"></i> <span data-i18n="ガントチャート">ガントチャート</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>
    <div class="mb-2">
        <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#projectFilterBox" aria-expanded="false">
            <i class="fa fa-filter me-1"></i> <span data-i18n="高度なフィルター">高度なフィルター</span>
        </button>
    </div>
    <div class="collapse show" id="projectFilterBox">
    <div class="card mb-3">
        <div class="card-body pb-4 pt-3">
        <form class="row g-3" id="projectFilterForm" autocomplete="off">
            <div class="col-md-3 col-6">
            <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="開始月">開始月</label>
            <input type="text" class="form-control form-control-sm" id="filterStartMonth" autocomplete="off">
            </div>
            <div class="col-md-3 col-6">
            <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="期限月">期限月</label>
            <input type="text" class="form-control form-control-sm" id="filterEndMonth" autocomplete="off">
            </div>
            <div class="col-md-3 col-6">
            <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="優先度">優先度</label>
            <select class="form-select form-select-sm" id="filterPriority"></select>
            </div>
            <div class="col-md-3 col-6">
            <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="進捗率">進捗率</label>
            <select class="form-select form-select-sm" id="filterProgress">
                <option value="">すべて</option>
                <option value="0-50">0-50%</option>
                <option value="51-99">51-99%</option>
                <option value="100">100%</option>
            </select>
            </div>
            <div class="col-md-3 col-6">
            <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="残り時間">残り時間</label>
            <select class="form-select form-select-sm" id="filterTimeLeft">
                <option value="">すべて</option>
                <option value="7">7日以内</option>
                <option value="30">30日以内</option>
                <option value="overdue">期限切れ</option>
            </select>
            </div>
            <div class="col-md-4 col-12">
            <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="キーワード">キーワード</label>
            <input type="text" class="form-control form-control-sm" id="filterKeyword" placeholder="検索...">
            </div>
            <div class="col-md-2 col-12 d-flex align-items-end">
            <button class="btn btn-sm btn-outline-primary w-100" id="filterReset" type="button">
                <i class="fa fa-undo me-1"></i><span data-i18n="リセット">リセット</span>
            </button>
            </div>
        </form>
        </div>
    </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-2">
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
                <div class="form-check form-switch ms-2">
                    <input class="form-check-input" type="checkbox" id="showInactiveSwitch">
                    <label class="form-check-label small" for="showInactiveSwitch" data-i18n="完了案件等も表示">完了等も表示</label>
                </div>

            </div>
            <!-- Active Filters Display -->
            <div id="activeFilters" class="mb-2"></div>
            <table id="projectTable" class="table table-striped">
                
            </table>
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

    <!-- Kadai Projects Queue - Fixed Bottom -->
    <div id="kadaiQueue" class="kadai-queue-container" v-show="kadaiProjects.length > 0">
        <div class="kadai-queue-header" @click="toggleKadaiQueue">
            <div class="d-flex align-items-center justify-content-between w-100">
                <div class="d-flex align-items-center">
                    <i class="fa fa-tasks me-2"></i>
                    <span class="fw-bold">課題案件キュー</span>
                    <span v-if="selectedDepartment" class="ms-2 small opacity-75">({{ selectedDepartment.name }})</span>
                    <span class="badge bg-warning ms-2" v-if="kadaiProjects.length > 0">{{ kadaiProjects.length }}</span>
                </div>
                    <div class="d-flex align-items-center">
                        <button class="btn btn-sm btn-outline-warning me-2" @click.stop="refreshKadaiQueue" title="更新">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <i class="fa fa-chevron-up" :class="{ 'fa-chevron-down': !isKadaiQueueExpanded }"></i>
                    </div>
            </div>
        </div>
        
                            <div class="kadai-queue-content" v-show="isKadaiQueueExpanded">
                        <div v-if="!selectedDepartment" class="text-center py-3 text-muted">
                            <i class="fa fa-building fa-2x mb-2"></i>
                            <p class="mb-0">部署を選択してください</p>
                            <small class="text-muted">部署を選択すると課題案件が表示されます</small>
                        </div>
                        
                        <div v-else-if="kadaiProjects.length === 0" class="text-center py-3 text-muted">
                            <i class="fa fa-inbox fa-2x mb-2"></i>
                            <p class="mb-0">課題案件はありません</p>
                        </div>
                        
                        <div v-else class="kadai-queue-list">
                <div v-for="project in kadaiProjects" :key="project.id" class="kadai-queue-item">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-1">
                                <span class="badge bg-warning me-2">課題</span>
                                <span class="badge bg-info">承認待ち</span>
                            </div>
                            <h6 class="mb-1 text-truncate" :title="project.name">{{ project.name }}</h6>
                            <div class="small text-muted">
                                <span class="me-2" :title="project.department_name">{{ project.department_name }}</span>
                                <span v-if="project.end_date" class="me-2" :title="'期限: ' + formatDate(project.end_date)">
                                    <i class="fa fa-calendar me-1"></i>{{ formatDate(project.end_date) }}
                                </span>
                            </div>
                        </div>
                        <div class="d-flex flex-column align-items-end">
                                                                    <div class="btn-group btn-group-sm mb-2">
                                            <a :href="`detail.php?id=${project.id}`" class="btn btn-outline-primary btn-sm" title="詳細">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            <button class="btn btn-outline-info btn-sm" @click="confirmProject(project)" title="プロジェクトを承認">
                                                <i class="fa fa-check"></i>
                                            </button>
                                        </div>
                            <small class="text-muted">{{ project.project_number || '-' }}</small>
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
    padding: 0.5rem 0.4rem;
}
.table thead tr th {
    padding: 0.5rem 0.4rem!important;
}



/* Kadai Queue Container */
.kadai-queue-container {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 50%;
    background: #fff;
    border-top: 3px solid #fd7e14;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
    z-index: 9000;
    transition: all 0.3s ease;
}

/* Responsive design for kadai queue */
@media (max-width: 768px) {
    .kadai-queue-header .d-flex.align-items-center {
        flex-wrap: wrap;
        gap: 0.5rem;
    }
}

.kadai-queue-header {
    padding: 12px 20px;
    background: linear-gradient(135deg, #fd7e14 0%, #ff8c42 100%);
    color: white;
    cursor: pointer;
    user-select: none;
    transition: background 0.3s ease;
}

.kadai-queue-header:hover {
    background: linear-gradient(135deg, #e86a0a 0%, #fd7e14 100%);
}

.kadai-queue-content {
    max-height: 300px;
    overflow-y: auto;
    background: #fff;
    border-top: 1px solid #e9ecef;
}

.kadai-queue-list {
    padding: 0;
}

.kadai-queue-item {
    padding: 15px 20px;
    border-bottom: 1px solid #f8f9fa;
    transition: background-color 0.2s ease;
}

.kadai-queue-item:hover {
    background-color: #f8f9fa;
}

.kadai-queue-item:last-child {
    border-bottom: none;
}

.kadai-queue-item h6 {
    font-size: 0.9rem;
    margin: 0;
    color: #495057;
}

.kadai-queue-item .badge {
    font-size: 0.7rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .kadai-queue-container {
        left: 0;
        right: 0;
    }
    
    .kadai-queue-header {
        padding: 10px 15px;
    }
    
    .kadai-queue-item {
        padding: 12px 15px;
    }
    
    .kadai-queue-item .btn-group {
        flex-direction: column;
    }
    
    .kadai-queue-item .btn-group .btn {
        margin-bottom: 2px;
    }
}

/* Animation for queue expansion */
.kadai-queue-content {
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Order Type Badge Styling */
#projectTable .badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    margin-right: 0.25rem;
    margin-bottom: 0.25rem;
    display: inline-block;
}

#projectTable .badge:last-child {
    margin-right: 0;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="assets/js/project-list.js?v=<?=CACHE_VERSION?>"></script>