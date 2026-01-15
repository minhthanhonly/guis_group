<?php
require_once('../application/loader.php');
$view->heading('従業員統計');
if(!$_SESSION['isProjectManager']){
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
                        <div class="col-md-4">
                            <label class="form-label">期間</label>
                            <select class="form-select" v-model="filters.selected_month" @change="onMonthChange">
                                <option value="">すべての期間</option>
                                <option v-for="month in availableMonths" :key="month.value" :value="month.value">{{ month.label }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">チーム</label>
                            <select class="form-select" v-model="filters.team_id" @change="onTeamChange">
                                <option value="">すべてのチーム</option>
                                <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end gap-2">
                            <button class="btn btn-primary flex-fill" @click="calculateStatistics" :disabled="calculating">
                                <i class="fa fa-calculator me-1"></i>
                                <span v-if="calculating">計算中...</span>
                                <span v-else>統計計算</span>
                            </button>
                            <button class="btn btn-danger flex-fill" @click="deleteStatistics" :disabled="deleting">
                                <i class="fa fa-trash me-1"></i>
                                <span v-if="deleting">削除中...</span>
                                <span v-else>12ヶ月削除</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="col-12 mb-4">
            <ul class="nav nav-tabs" role="tablist">
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
                        <button v-if="selectedTeamId !== null" class="btn btn-sm btn-outline-primary" @click="clearTeamSelection">
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
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">タスク数:</span>
                                        <strong>{{ stat.total_task_count }}</strong>
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
        <div class="col-12 mb-4" v-show="selectedTeamId && activeTab === 'teams'">
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
                <div class="card-body">
                    <!-- Loading State -->
                    <div v-if="chartLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">読み込み中...</span>
                        </div>
                        <p class="mt-2 text-muted">チャートデータを読み込み中...</p>
                    </div>
                    
                    <!-- Chart Container -->
                    <div v-else>
                        <div id="team-monthly-chart" style="min-height: 400px;"></div>
                    </div>
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

                    <!-- Statistics Table -->
                    <div v-else-if="filteredStatistics.length > 0" class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th style="cursor: pointer;" @click="sortBy('period_start')">
                                        期間
                                        <i class="fa ms-1" :class="getSortIcon('period_start')"></i>
                                    </th>
                                    <th style="cursor: pointer;" @click="sortBy('team_name')">
                                        チーム
                                        <i class="fa ms-1" :class="getSortIcon('team_name')"></i>
                                    </th>
                                    <th style="cursor: pointer;" @click="sortBy('user_name')">
                                        従業員名
                                        <i class="fa ms-1" :class="getSortIcon('user_name')"></i>
                                    </th>
                                    <th class="text-end" style="cursor: pointer;" @click="sortBy('revenue')">
                                        売上高
                                        <i class="fa ms-1" :class="getSortIcon('revenue')"></i>
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
                                        <strong class="text-primary">¥{{ formatNumber(stat.revenue) }}</strong>
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
                                    <td>
                                        <small class="text-muted">{{ formatDateTime(stat.updated_at) }}</small>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="text-center py-5">
                        <i class="fa fa-chart-bar fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">統計データがありません</h5>
                        <p v-if="filters.selected_month" class="text-muted">選択した期間のデータがありません</p>
                        <p v-else class="text-muted">期間を選択して「統計計算」ボタンをクリックしてください</p>
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
                        <p class="text-muted">選択した年度に統計データまたは目標がありません</p>
                    </div>
                    <div v-else class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>チーム</th>
                                    <th class="text-end">売上高 / 目標</th>
                                    <th class="text-end">ベスト月</th>
                                    <th class="text-end">ワースト月</th>
                                    <th class="text-center">達成月</th>
                                    <th class="text-center">良い / 悪い</th>
                                    <th class="text-center">タスク</th>
                                    <th class="text-center">図面</th>
                                    <th class="text-end">スコア</th>
                                    <th class="text-center">ランク</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="team in annualSummary" :key="team.team_id || 'no-team'">
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
<script src="assets/js/employee-statistics.js?v=<?=CACHE_VERSION?>"></script>

