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
</style>

<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="assets/js/project-list.js?v=<?=CACHE_VERSION?>"></script>