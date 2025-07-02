<?php
require_once('../application/loader.php');
$view->heading('プロジェクトガントチャート');

// Get project ID from URL
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
if (!$project_id) {
    header('Location: index.php');
    exit;
}
?>
<div id="app" class="container-fluid mt-4" v-cloak>
        <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold" href="#"><span class="badge badge-sm bg-label-info">#P{{ project?.project_number }}</span></a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#projectNavbar" aria-controls="projectNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="projectNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                    <a class="nav-link" href="detail.php?id=<?php echo $project_id; ?>">概要</a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link" href="task.php?project_id=<?php echo $project_id; ?>">タスク<span class="badge badge-sm ms-1 rounded-pill">{{ projectInfo?.task_count }}</span></a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="gantt.php?project_id=<?php echo $project_id; ?>">ガントチャート</a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link" href="drawings.php?project_id=<?php echo $project_id; ?>">図面<span class="badge badge-sm bg-info ms-1 rounded-pill">{{ projectInfo?.drawing_count }}</span></a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link" href="attachment.php?project_id=<?php echo $project_id; ?>">添付ファイル</a>
                    </li>
                </ul>
                </div>
            </div>
        </nav>

    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">ガントチャート - {{ projectInfo.name || 'プロジェクト' }}</h5>
               
                <div class="d-flex gap-2 align-items-center">
                    <!-- Status Filter -->
                    <div class="btn-group">
                        <button 
                            v-for="status in statuses" 
                            :key="status.key"
                            class="btn btn-sm"
                            :class="{
                                [`btn-label-${status.color}`]: !selectedStatus || selectedStatus?.key !== status.key,
                                [`btn-${status.color}`]: selectedStatus?.key === status.key,
                                'active': selectedStatus?.key === status.key
                            }"
                            @click="filterTasksByStatus(status)"
                        >
                            {{ status.name }}
                        </button>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 align-items-center mt-2">
                <!-- Date Range Controls -->
                <div class="d-flex align-items-center gap-2 me-3">
                    <label class="form-label mb-0 small">表示期間:</label>
                    <input type="date" class="form-control form-control-sm start_date" style="width: 140px;" @change="changeDates">
                    <span class="text-muted">–</span>
                    <input type="date" class="form-control form-control-sm end_date" style="width: 140px;" @change="changeDates">
                </div>
                
                <!-- Scale Controls -->
                <div class="btn-group me-2">
                    <button class="btn btn-outline-secondary btn-sm" @click="setDefaultScale" title="デフォルトスケール">
                        <i class="fa fa-calendar me-1"></i> デフォルト
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" @click="setMonthScale" title="月スケール">
                        <i class="fa fa-calendar-alt me-1"></i> 月
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" @click="setWeekScale" title="週スケール">
                        <i class="fa fa-calendar-week me-1"></i> 週
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" @click="setDayScale" title="日スケール">
                        <i class="fa fa-calendar-day me-1"></i> 日
                    </button>
                </div>
                
                <!-- Refresh Button -->
                <button class="btn btn-outline-secondary btn-sm me-2" @click="refreshGantt" title="更新">
                    <i class="fa fa-refresh"></i>
                </button>
                
                <!-- Fullscreen Button -->
                <button class="btn btn-outline-secondary btn-sm me-2" @click="toggleFullscreen" title="フルスクリーン">
                    <i class="fa fa-expand" v-if="!isFullscreen"></i>
                    <i class="fa fa-compress" v-if="isFullscreen"></i>
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="gantt_container" style="width: 100%; height: 600px; min-height: 600px; position: relative;">
                <div v-if="loading" class="gantt-loading" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: white; z-index: 10;">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">データを読み込み中...</p>
                    </div>
                </div>
                <div v-if="!ganttInitialized && !loading" class="gantt-loading" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: white; z-index: 5;">
                    <div class="text-center">
                        <div class="spinner-border text-info" role="status">
                            <span class="visually-hidden">Initializing...</span>
                        </div>
                        <p class="mt-2">ガントチャートを初期化中...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.gantt-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.9);
}

.gantt_row {
    border-bottom: 1px solid #e0e0e0;
}

.gantt_row:hover {
    background-color: #f5f5f5;
}

.gantt_cell {
    border-right: 1px solid #e0e0e0;
}

.gantt_grid_head_cell {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-header .d-flex {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .card-header .btn-group {
        margin-top: 10px;
    }
    
    #gantt_container {
        height: 400px;
    }
}
</style>

<script>
const PROJECT_ID = <?php echo $project_id; ?>;
</script>


<?php
$view->footing();
?>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<!-- DHTMLX Gantt Standard Version -->
<link rel="stylesheet" href="https://cdn.dhtmlx.com/gantt/edge/dhtmlxgantt.css" type="text/css">
<script src="https://cdn.dhtmlx.com/gantt/edge/dhtmlxgantt.js"></script> 
<script src="assets/js/project-task-gantt.js"></script>