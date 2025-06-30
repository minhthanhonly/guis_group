<?php

require_once('../application/loader.php');
$view->heading('プロジェクトガントチャート');

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
                    <button class="btn btn-primary" @click="refreshGantt">
                        <i class="fa fa-refresh me-1"></i> 更新
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fa fa-list me-1"></i> リスト表示
                    </a>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">ガントチャート - {{ selectedDepartment ? selectedDepartment.name : '部署を選択してください' }} (アクティブプロジェクト)</h5>
               
                <div class="d-flex gap-2 align-items-center">
                    <!-- Status Filter -->
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
                </div>
            </div>
            <div class="d-flex gap-2 align-items-center">
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
                
                <!-- Zoom Controls -->
                <div class="btn-group me-2">
                    <button class="btn btn-outline-secondary btn-sm" @click="zoomOut" title="ズームアウト">
                        <i class="fa fa-search-minus"></i>
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" @click="zoomIn" title="ズームイン">
                        <i class="fa fa-search-plus"></i>
                    </button>
                </div>
                
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

<?php
$view->footing();
?>

<!-- DHTMLX Gantt Standard Version -->
<link rel="stylesheet" href="https://cdn.dhtmlx.com/gantt/edge/dhtmlxgantt.css" type="text/css">
<script src="https://cdn.dhtmlx.com/gantt/edge/dhtmlxgantt.js"></script>

<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="assets/js/project-gantt.js"></script>
