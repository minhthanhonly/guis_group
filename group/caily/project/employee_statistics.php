<?php
require_once('../application/loader.php');
$view->heading('従業員統計');
$canAccessEmployeeStats = !empty($_SESSION['isProjectManager'])
    || (($_SESSION['userid'] ?? '') === 'hayashida');
if(!$canAccessEmployeeStats){
    echo '<div class="container-fluid mt-4"><div class="alert alert-danger">権限がありません。</div></div>';
    exit;
}
if($_SESSION['show_project'] == 0){
    echo '<div class="container-fluid mt-4"><div class="alert alert-danger">権限がありません。</div></div>';
    exit;
}
?>
<div id="app" class="container-fluid mt-4" v-cloak>
    <div class="row">
        <!-- Header -->
        <div class="col-12 mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="fa fa-chart-bar me-2"></i>従業員統計
                </h4>
            </div>
        </div>

        <!-- Filters -->
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4 col-lg-3">
                            <label class="form-label">期間</label>
                            <select class="form-select" v-model="filters.selected_month" @change="onMonthChange">
                                <option value="" data-i18n="すべての期間">すべての期間</option>
                                <option v-for="month in availableMonths" :key="month.value" :value="month.value">{{ month.label }}</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <label class="form-label"><span data-i18n="部署">部署</span></label>
                            <select class="form-select" v-model="sharedFilters.department_id" @change="onSharedFilterChange('department')">
                                <option :value="null" data-i18n="すべての部署">すべての部署</option>
                                <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <label class="form-label">チーム</label>
                            <select class="form-select" v-model="sharedFilters.team_id" @change="onSharedFilterChange('team')" :disabled="activeTab === 'departments'">
                                <option :value="null" data-i18n="すべてのチーム">すべてのチーム</option>
                                <option v-for="team in filterTeams" :key="team.id" :value="team.id">{{ team.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button class="btn btn-primary" @click="calculateStatistics" :disabled="calculating">
                                <i class="fa fa-calculator me-1"></i>
                                <span v-if="calculating">計算中...</span>
                                <span v-else>統計計算</span>
                            </button>
                            <!-- <button class="btn btn-danger" @click="deleteStatistics" :disabled="deleting">
                                <i class="fa fa-trash me-1"></i>
                                <span v-if="deleting">削除中...</span>
                                <span v-else>12ヶ月削除</span>
                            </button> -->
                            <!-- <button class="btn btn-warning" @click="generateSampleStatistics" :disabled="generating">
                                <i class="fa fa-magic me-1"></i>
                                <span v-if="generating">生成中...</span>
                                <span v-else>サンプルデータ追加</span>
                            </button> -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="col-12 mb-4">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link" :class="{ active: activeTab === 'departments' }" @click="switchTab('departments')" type="button">
                        <i class="fa fa-building me-1"></i><span data-i18n="部署統計">部署統計</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" :class="{ active: activeTab === 'teams' }" @click="switchTab('teams')" type="button">
                        <i class="fa fa-users me-1"></i>チーム統計
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" :class="{ active: activeTab === 'employees' }" @click="switchTab('employees')" type="button">
                        <i class="fa fa-user me-1"></i>従業員統計一覧
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" :class="{ active: activeTab === 'annual' }" @click="switchTab('annual')" type="button">
                        <i class="fa fa-trophy me-1"></i>年間サマリー
                    </button>
                </li>
            </ul>
        </div>

        <!-- Team Statistics Tab -->
        <div class="col-12" v-show="activeTab === 'teams'">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">チーム統計</h5>
                    <div class="d-flex gap-2">
                        <button v-if="sharedFilters.team_id !== null" class="btn btn-sm btn-outline-primary" @click="clearTeamSelection">
                            <i class="fa fa-list me-1"></i>すべて表示
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" @click="loadSummary">
                            <i class="fa fa-refresh me-1"></i>更新
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Loading State -->
                    <div v-if="loading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">読み込み中...</span>
                        </div>
                        <p class="mt-2 text-muted">データを読み込み中...</p>
                    </div>

                    <!-- Team Statistics Cards -->
                    <div v-else-if="displayedTeamStatistics.length > 0" class="row">
                        <div class="col-md-4 mb-3" v-for="stat in displayedTeamStatistics" :key="stat.team_id || 'no-team'">
                            <div class="card border-primary h-100" 
                                 :class="{ 'border-success': isTeamSelected(stat.team_id) }"
                                 style="cursor: pointer; transition: all 0.3s;"
                                 @click="selectTeam(stat.team_id)"
                                 @mouseenter="$event.currentTarget.style.transform = 'scale(1.02)'"
                                 @mouseleave="$event.currentTarget.style.transform = 'scale(1)'">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="card-title mb-0">{{ stat.team_name || 'チーム未所属' }}</h6>
                                        <i v-if="isTeamSelected(stat.team_id)" class="fa fa-check-circle text-success"></i>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">メンバー数:</span>
                                        <strong>{{ stat.member_count }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">売上高:</span>
                                        <strong v-html="getRevenueWithTargetTeam(stat.total_revenue, stat.team_id)"></strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">良い:</span>
                                        <strong class="text-success">{{ stat.total_likes }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">悪い:</span>
                                        <strong class="text-danger">{{ stat.total_dislikes }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">図面数:</span>
                                        <strong>{{ stat.total_drawing_count }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">タスク数:</span>
                                        <strong>{{ stat.total_task_count }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted"><span data-i18n="工数合計">工数合計</span>:</span>
                                        <strong class="text-primary">{{ formatWorkload(stat.total_workload) }}</strong>
                                    </div>
                                    <div class="border-top pt-2 mt-1">
                                        <div class="d-flex justify-content-between mb-1 small">
                                            <span class="text-muted"><span data-i18n="新規作成">新規作成</span>:</span>
                                            <strong>{{ formatWorkload(stat.workload_new) }}</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1 small">
                                            <span class="text-muted"><span data-i18n="修正(エラー)">修正(エラー)</span>:</span>
                                            <strong>{{ formatWorkload(stat.workload_error_fix) }}</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1 small">
                                            <span class="text-muted"><span data-i18n="修正(変更)">修正(変更)</span>:</span>
                                            <strong>{{ formatWorkload(stat.workload_change_fix) }}</strong>
                                        </div>
                                        <div class="d-flex justify-content-between small">
                                            <span class="text-muted"><span data-i18n="その他工数">その他工数</span>:</span>
                                            <strong>{{ formatWorkload(stat.workload_other) }}</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="text-center py-5">
                        <i class="fa fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">チーム統計データがありません</h5>
                        <p class="text-muted">期間を選択して「統計計算」ボタンをクリックしてください</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Team Monthly Chart Section -->
        <div class="col-12 mb-4" v-show="sharedFilters.team_id && activeTab === 'teams'">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fa fa-chart-line me-2"></i>
                        {{ getSelectedTeamName() }} - 月別統計比較
                    </h5>
                    <button class="btn btn-sm btn-outline-secondary" @click="loadMonthlyStatistics">
                        <i class="fa fa-refresh me-1"></i>更新
                    </button>
                </div>
                <div class="card-body position-relative" style="min-height: 400px;">
                    <div v-if="chartLoading" class="position-absolute top-50 start-50 translate-middle text-center" style="z-index: 2;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">読み込み中...</span>
                        </div>
                        <p class="mt-2 text-muted mb-0">チャートデータを読み込み中...</p>
                    </div>
                    <div id="team-monthly-chart" style="min-height: 400px;"></div>
                </div>
            </div>
        </div>

        <!-- Department Statistics Tab -->
        <div class="col-12" v-show="activeTab === 'departments'">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><span data-i18n="部署統計">部署統計</span></h5>
                    <div class="d-flex gap-2">
                        <button v-if="selectedDepartmentId" class="btn btn-sm btn-outline-primary" @click="clearDepartmentSelection">
                            <i class="fa fa-list me-1"></i><span data-i18n="すべて表示">すべて表示</span>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" @click="loadDepartmentSummary">
                            <i class="fa fa-refresh me-1"></i><span data-i18n="更新">更新</span>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div v-if="loading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">読み込み中...</span>
                        </div>
                        <p class="mt-2 text-muted">データを読み込み中...</p>
                    </div>

                    <div v-else-if="displayedDepartmentStatistics.length > 0" class="row">
                        <div class="col-md-4 mb-3" v-for="stat in displayedDepartmentStatistics" :key="stat.department_id">
                            <div class="card border-info h-100"
                                 :class="{ 'border-success': isDepartmentSelected(stat.department_id) }"
                                 style="cursor: pointer; transition: all 0.3s;"
                                 @click="selectDepartment(stat.department_id)"
                                 @mouseenter="$event.currentTarget.style.transform = 'scale(1.02)'"
                                 @mouseleave="$event.currentTarget.style.transform = 'scale(1)'">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="card-title mb-0">{{ stat.department_name }}</h6>
                                        <i v-if="isDepartmentSelected(stat.department_id)" class="fa fa-check-circle text-success"></i>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted"><span data-i18n="チーム数">チーム数</span>:</span>
                                        <strong>{{ stat.team_count }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted"><span data-i18n="メンバー数">メンバー数</span>:</span>
                                        <strong>{{ stat.member_count }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted"><span data-i18n="売上高">売上高</span>:</span>
                                        <strong>{{ formatCurrency(stat.total_revenue) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted"><span data-i18n="良い">良い</span>:</span>
                                        <strong class="text-success">{{ stat.total_likes }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted"><span data-i18n="悪い">悪い</span>:</span>
                                        <strong class="text-danger">{{ stat.total_dislikes }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted"><span data-i18n="タスク数">タスク数</span>:</span>
                                        <strong>{{ stat.total_task_count }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted"><span data-i18n="工数合計">工数合計</span>:</span>
                                        <strong class="text-primary">{{ formatWorkload(stat.total_workload) }}</strong>
                                    </div>
                                    <div class="border-top pt-2 mt-1">
                                        <div class="d-flex justify-content-between mb-1 small">
                                            <span class="text-muted"><span data-i18n="新規作成">新規作成</span>:</span>
                                            <strong>{{ formatWorkload(stat.workload_new) }}</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1 small">
                                            <span class="text-muted"><span data-i18n="修正(エラー)">修正(エラー)</span>:</span>
                                            <strong>{{ formatWorkload(stat.workload_error_fix) }}</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1 small">
                                            <span class="text-muted"><span data-i18n="修正(変更)">修正(変更)</span>:</span>
                                            <strong>{{ formatWorkload(stat.workload_change_fix) }}</strong>
                                        </div>
                                        <div class="d-flex justify-content-between small">
                                            <span class="text-muted"><span data-i18n="その他工数">その他工数</span>:</span>
                                            <strong>{{ formatWorkload(stat.workload_other) }}</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-5">
                        <i class="fa fa-building fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted"><span data-i18n="部署統計データがありません">部署統計データがありません</span></h5>
                        <p class="text-muted">期間を選択して「統計計算」ボタンをクリックしてください</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Department Monthly Chart Section -->
        <div class="col-12 mb-4" v-show="sharedFilters.department_id && activeTab === 'departments'">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fa fa-chart-line me-2"></i>
                        {{ getSelectedDepartmentName() }} - <span data-i18n="月別統計比較">月別統計比較</span>
                    </h5>
                    <button class="btn btn-sm btn-outline-secondary" @click="loadDepartmentMonthlyStatistics">
                        <i class="fa fa-refresh me-1"></i><span data-i18n="更新">更新</span>
                    </button>
                </div>
                <div class="card-body position-relative" style="min-height: 400px;">
                    <div v-if="departmentChartLoading" class="position-absolute top-50 start-50 translate-middle text-center" style="z-index: 2;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">読み込み中...</span>
                        </div>
                        <p class="mt-2 text-muted mb-0">チャートデータを読み込み中...</p>
                    </div>
                    <div id="department-monthly-chart" style="min-height: 400px;"></div>
                </div>
            </div>
        </div>

        <!-- Employee Statistics Tab -->
        <div class="col-12" v-show="activeTab === 'employees'">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">従業員統計一覧</h5>
                    <button class="btn btn-sm btn-outline-secondary" @click="loadStatistics">
                        <i class="fa fa-refresh me-1"></i>更新
                    </button>
                </div>
                <div class="card-body">
                    <!-- Loading State -->
                    <div v-if="loading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">読み込み中...</span>
                        </div>
                        <p class="mt-2 text-muted">データを読み込み中...</p>
                    </div>

                    <template v-else-if="filteredStatistics.length > 0">
                        <div class="card bg-light mb-3">
                            <div class="card-body py-3">
                                <div class="row g-3">
                                    <div class="col-6 col-md-3 col-lg-2">
                                        <small class="text-muted d-block"><span data-i18n="メンバー数">メンバー数</span></small>
                                        <strong>{{ employeeSummaryTotals.member_count }}</strong>
                                    </div>
                                    <div class="col-6 col-md-3 col-lg-2">
                                        <small class="text-muted d-block"><span data-i18n="売上高">売上高</span></small>
                                        <strong>{{ formatCurrency(employeeSummaryTotals.total_revenue) }}</strong>
                                    </div>
                                    <div class="col-6 col-md-3 col-lg-2">
                                        <small class="text-muted d-block"><span data-i18n="良い">良い</span> / <span data-i18n="悪い">悪い</span></small>
                                        <strong><span class="text-success">{{ employeeSummaryTotals.total_likes }}</span> / <span class="text-danger">{{ employeeSummaryTotals.total_dislikes }}</span></strong>
                                    </div>
                                    <div class="col-6 col-md-3 col-lg-2">
                                        <small class="text-muted d-block"><span data-i18n="タスク数">タスク数</span></small>
                                        <strong>{{ employeeSummaryTotals.total_task_count }}</strong>
                                    </div>
                                    <div class="col-6 col-md-3 col-lg-2">
                                        <small class="text-muted d-block"><span data-i18n="工数合計">工数合計</span></small>
                                        <strong class="text-primary">{{ formatWorkload(employeeSummaryTotals.total_workload) }}</strong>
                                    </div>
                                    <div class="col-12 col-lg-4">
                                        <small class="text-muted d-block mb-1"><span data-i18n="種別別工数">種別別工数</span></small>
                                        <div class="d-flex flex-wrap gap-3 small">
                                            <span><span data-i18n="新規作成">新規作成</span>: {{ formatWorkload(employeeSummaryTotals.workload_new) }}</span>
                                            <span><span data-i18n="修正(エラー)">修正(エラー)</span>: {{ formatWorkload(employeeSummaryTotals.workload_error_fix) }}</span>
                                            <span><span data-i18n="修正(変更)">修正(変更)</span>: {{ formatWorkload(employeeSummaryTotals.workload_change_fix) }}</span>
                                            <span><span data-i18n="その他工数">その他工数</span>: {{ formatWorkload(employeeSummaryTotals.workload_other) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th style="cursor: pointer;" @click="sortBy('period_start')">
                                        期間
                                        <i class="fa ms-1" :class="getSortIcon('period_start')"></i>
                                    </th>
                                    <th style="cursor: pointer;" @click="sortBy('department_name')">
                                        <span data-i18n="部署">部署</span>
                                        <i class="fa ms-1" :class="getSortIcon('department_name')"></i>
                                    </th>
                                    <th style="cursor: pointer;" @click="sortBy('team_name')">
                                        チーム
                                        <i class="fa ms-1" :class="getSortIcon('team_name')"></i>
                                    </th>
                                    <th style="cursor: pointer;" @click="sortBy('user_name')">
                                        従業員名
                                        <i class="fa ms-1" :class="getSortIcon('user_name')"></i>
                                    </th>
                                    <th class="text-end" style="cursor: pointer;" @click="sortBy('total_drawings_revenue')">
                                        図面売上
                                        <i class="fa ms-1" :class="getSortIcon('total_drawings_revenue')"></i>
                                    </th>
                                    <th class="text-center" style="cursor: pointer;" @click="sortBy('drawing_count')">
                                        図面数
                                        <i class="fa ms-1" :class="getSortIcon('drawing_count')"></i>
                                    </th>
                                    <th class="text-center" style="cursor: pointer;" @click="sortBy('task_count')">
                                        タスク数
                                        <i class="fa ms-1" :class="getSortIcon('task_count')"></i>
                                    </th>
                                    <th class="text-center" style="cursor: pointer;" @click="sortBy('task_likes')">
                                        <i class="fa fa-thumbs-up text-success"></i> 良い
                                        <i class="fa ms-1" :class="getSortIcon('task_likes')"></i>
                                    </th>
                                    <th class="text-center" style="cursor: pointer;" @click="sortBy('task_dislikes')">
                                        <i class="fa fa-thumbs-down text-danger"></i> 悪い
                                        <i class="fa ms-1" :class="getSortIcon('task_dislikes')"></i>
                                    </th>
                                    <th class="text-center" style="cursor: pointer;" @click="sortBy('total_workload')">
                                        <span data-i18n="工数合計">工数合計</span>
                                        <i class="fa ms-1" :class="getSortIcon('total_workload')"></i>
                                    </th>
                                    <th class="text-center" style="cursor: pointer;" @click="sortBy('workload_new')">
                                        <span data-i18n="新規作成">新規作成</span>
                                        <i class="fa ms-1" :class="getSortIcon('workload_new')"></i>
                                    </th>
                                    <th class="text-center" style="cursor: pointer;" @click="sortBy('workload_error_fix')">
                                        <span data-i18n="修正(エラー)">修正(エラー)</span>
                                        <i class="fa ms-1" :class="getSortIcon('workload_error_fix')"></i>
                                    </th>
                                    <th class="text-center" style="cursor: pointer;" @click="sortBy('workload_change_fix')">
                                        <span data-i18n="修正(変更)">修正(変更)</span>
                                        <i class="fa ms-1" :class="getSortIcon('workload_change_fix')"></i>
                                    </th>
                                    <th class="text-center" style="cursor: pointer;" @click="sortBy('workload_other')">
                                        <span data-i18n="その他工数">その他工数</span>
                                        <i class="fa ms-1" :class="getSortIcon('workload_other')"></i>
                                    </th>
                                    <th style="cursor: pointer;" @click="sortBy('updated_at')">
                                        更新日時
                                        <i class="fa ms-1" :class="getSortIcon('updated_at')"></i>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="stat in filteredStatistics" :key="stat.id">
                                    <td>
                                        <div class="d-flex flex-column">
                                            <small class="text-muted">{{ getPeriodTypeLabel(stat.period_type) }}</small>
                                            <span>{{ formatDate(stat.period_start) }} ～ {{ formatDate(stat.period_end) }}</span>
                                        </div>
                                    </td>
                                    <td>{{ stat.department_name || '-' }}</td>
                                    <td>{{ stat.team_name || '-' }}</td>
                                    <td>
                                        <strong class="text-primary" 
                                                style="cursor: pointer; text-decoration: underline;" 
                                                @click="selectEmployee(stat.user_id, stat.user_name)"
                                                :title="'クリックして' + stat.user_name + 'の統計を表示'">
                                            {{ stat.user_name }}
                                        </strong>
                                    </td>
                                    <td class="text-end">
                                        <span class="text-info">¥{{ formatNumber(stat.total_drawings_revenue) }}</span>
                                    </td>
                                    <td class="text-center">{{ stat.drawing_count }}</td>
                                    <td class="text-center">{{ stat.task_count }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-success">{{ stat.task_likes }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger">{{ stat.task_dislikes }}</span>
                                    </td>
                                    <td class="text-center fw-semibold text-primary">{{ formatWorkload(stat.total_workload) }}</td>
                                    <td class="text-center">{{ formatWorkload(stat.workload_new) }}</td>
                                    <td class="text-center">{{ formatWorkload(stat.workload_error_fix) }}</td>
                                    <td class="text-center">{{ formatWorkload(stat.workload_change_fix) }}</td>
                                    <td class="text-center">{{ formatWorkload(stat.workload_other) }}</td>
                                    <td>
                                        <small class="text-muted">{{ formatDateTime(stat.updated_at) }}</small>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        </div>
                    </template>

                    <!-- Empty State -->
                    <div v-else class="text-center py-5">
                        <i class="fa fa-chart-bar fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">統計データがありません</h5>
                        <p v-if="filters.selected_month" class="text-muted">選択した期間のデータがありません</p>
                        <p v-else class="text-muted">選択した条件に該当する従業員がいません</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employee Monthly Chart Section -->
        <div id="employee-chart-section" class="col-12 mb-4" v-show="selectedUserId && activeTab === 'employees'">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fa fa-chart-line me-2"></i>
                        {{ selectedUserName }} - 月別統計比較
                    </h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" @click="clearEmployeeSelection">
                            <i class="fa fa-times me-1"></i>閉じる
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" @click="loadEmployeeMonthlyStatistics">
                            <i class="fa fa-refresh me-1"></i>更新
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Loading State -->
                    <div v-if="employeeChartLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">読み込み中...</span>
                        </div>
                        <p class="mt-2 text-muted">チャートデータを読み込み中...</p>
                    </div>
                    
                    <!-- Chart Container -->
                    <div v-else>
                        <div id="employee-monthly-chart" style="min-height: 400px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Annual Summary Tab -->
        <div class="col-12" v-show="activeTab === 'annual'">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fa fa-trophy me-1"></i>年間サマリー
                    </h5>
                    <div class="d-flex gap-2 align-items-center">
                        <label class="text-muted mb-0">年度</label>
                        <select class="form-select" style="width: 160px;" v-model="selectedYear" @change="onYearChange">
                            <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}年</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <div v-if="annualLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">読み込み中...</span>
                        </div>
                        <p class="mt-2 text-muted">年間サマリーを読み込み中...</p>
                    </div>
                    <div v-else-if="annualSummary.length === 0" class="text-center py-5">
                        <i class="fa fa-info-circle fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">データがありません</h5>
                        <p class="text-muted">選択した年度・条件に該当するデータがありません</p>
                    </div>
                    <div v-else class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th v-if="showAnnualDepartmentColumn" style="cursor: pointer;" @click="sortAnnualBy('department_name')">
                                        <span data-i18n="部署">部署</span>
                                        <i class="fa ms-1" :class="getAnnualSortIcon('department_name')"></i>
                                    </th>
                                    <th style="cursor: pointer;" @click="sortAnnualBy('team_name')">
                                        チーム
                                        <i class="fa ms-1" :class="getAnnualSortIcon('team_name')"></i>
                                    </th>
                                    <th class="text-end" style="cursor: pointer;" @click="sortAnnualBy('revenue_year')">
                                        売上高 / 目標
                                        <i class="fa ms-1" :class="getAnnualSortIcon('revenue_year')"></i>
                                    </th>
                                    <th class="text-end" style="cursor: pointer;" @click="sortAnnualBy('best_month')">
                                        ベスト月
                                        <i class="fa ms-1" :class="getAnnualSortIcon('best_month')"></i>
                                    </th>
                                    <th class="text-end" style="cursor: pointer;" @click="sortAnnualBy('worst_month')">
                                        ワースト月
                                        <i class="fa ms-1" :class="getAnnualSortIcon('worst_month')"></i>
                                    </th>
                                    <th class="text-center" style="cursor: pointer;" @click="sortAnnualBy('months_hit')">
                                        達成月
                                        <i class="fa ms-1" :class="getAnnualSortIcon('months_hit')"></i>
                                    </th>
                                    <th class="text-center" style="cursor: pointer;" @click="sortAnnualBy('likes_dislikes')">
                                        良い / 悪い
                                        <i class="fa ms-1" :class="getAnnualSortIcon('likes_dislikes')"></i>
                                    </th>
                                    <th class="text-center" style="cursor: pointer;" @click="sortAnnualBy('task_count')">
                                        タスク
                                        <i class="fa ms-1" :class="getAnnualSortIcon('task_count')"></i>
                                    </th>
                                    <th class="text-center" style="cursor: pointer;" @click="sortAnnualBy('drawing_count')">
                                        図面
                                        <i class="fa ms-1" :class="getAnnualSortIcon('drawing_count')"></i>
                                    </th>
                                    <th class="text-end" style="cursor: pointer;" @click="sortAnnualBy('score')">
                                        スコア
                                        <i class="fa ms-1" :class="getAnnualSortIcon('score')"></i>
                                    </th>
                                    <th class="text-center" style="cursor: pointer;" @click="sortAnnualBy('rank')">
                                        ランク
                                        <i class="fa ms-1" :class="getAnnualSortIcon('rank')"></i>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="team in sortedAnnualSummary" :key="team.team_id || 'no-team'">
                                    <td v-if="showAnnualDepartmentColumn">{{ team.department_name || '-' }}</td>
                                    <td>
                                        <strong>{{ team.team_name || 'チーム未所属' }}</strong>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex flex-column align-items-end">
                                            <span class="fw-bold text-primary">
                                                {{ formatCurrency(team.revenue_year || 0) }}
                                            </span>
                                            <small class="text-muted">
                                                目標 {{ formatCurrency(team.target_year || 0) }}
                                                <span :class="team.pct_year >= 100 ? 'text-success' : (team.pct_year >= 80 ? 'text-warning' : 'text-danger')">
                                                    ({{ Math.round(team.pct_year || 0) }}%)
                                                </span>
                                            </small>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex flex-column align-items-end" v-if="team.best_month && (team.best_month.revenue || 0) > 0">
                                            <span>{{ team.best_month.label }}</span>
                                            <small class="text-muted">
                                                {{ formatCurrency(team.best_month.revenue || 0) }}
                                                <span class="text-muted">
                                                    / {{ formatCurrency(team.best_month.target || 0) }}
                                                    ({{ Math.round(team.best_month.pct || 0) }}%)
                                                </span>
                                            </small>
                                        </div>
                                        <span v-else class="text-muted">データなし</span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex flex-column align-items-end" v-if="team.worst_month && (team.worst_month.revenue || 0) > 0">
                                            <span>{{ team.worst_month.label }}</span>
                                            <small class="text-muted">
                                                {{ formatCurrency(team.worst_month.revenue || 0) }}
                                                <span class="text-muted">
                                                    / {{ formatCurrency(team.worst_month.target || 0) }}
                                                    ({{ Math.round(team.worst_month.pct || 0) }}%)
                                                </span>
                                            </small>
                                        </div>
                                        <span v-else class="text-muted">データなし</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success me-1">達成 {{ team.months_hit }}</span>
                                        <span class="badge bg-secondary">未達 {{ team.months_miss }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-success me-1">{{ team.total_likes }}</span>
                                        /
                                        <span class="text-danger ms-1">{{ team.total_dislikes }}</span>
                                    </td>
                                    <td class="text-center">{{ team.total_task_count }}</td>
                                    <td class="text-center">{{ team.total_drawing_count }}</td>
                                    <td class="text-end fw-bold">{{ team.score }}</td>
                                    <td class="text-center">
                                        <span class="badge"
                                              :class="{
                                                'bg-success': team.rank === 'A',
                                                'bg-info': team.rank === 'B',
                                                'bg-warning text-dark': team.rank === 'C',
                                                'bg-danger': team.rank === 'D',
                                                'bg-secondary': !team.rank
                                              }">
                                            {{ team.rank || '-' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
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
.table th {
    white-space: nowrap;
}
.card.border-primary {
    border-width: 2px;
}
.card.border-primary:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
</style>

<?php
$root = ROOT;
?>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="<?=$root?>assets/vendor/libs/apex-charts/apexcharts.js"></script>
<script src="assets/js/employee-statistics.js?v=<?=PROJECT_CACHE_VERSION?>"></script>

