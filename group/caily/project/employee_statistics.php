<?php
require_once('../application/loader.php');
$view->heading('従業員統計');
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
                        <div class="col-md-3">
                            <label class="form-label">期間タイプ</label>
                            <select class="form-select" v-model="filters.period_type">
                                <option value="week">週</option>
                                <option value="month">月</option>
                                <option value="quarter">四半期</option>
                                <option value="year">年</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">チーム</label>
                            <select class="form-select" v-model="filters.team_id" @change="onTeamChange">
                                <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">開始日</label>
                            <input type="date" class="form-control" v-model="filters.period_start">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">終了日</label>
                            <input type="date" class="form-control" v-model="filters.period_end">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-primary w-100" @click="calculateStatistics" :disabled="calculating">
                                <i class="fa fa-calculator me-1"></i>
                                <span v-if="calculating">計算中...</span>
                                <span v-else>統計計算</span>
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
            </ul>
        </div>

        <!-- Team Statistics Tab -->
        <div class="col-12" v-show="activeTab === 'teams'">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">チーム統計</h5>
                    <button class="btn btn-sm btn-outline-secondary" @click="loadSummary">
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

                    <!-- Team Statistics Cards -->
                    <div v-else-if="teamStatistics.length > 0" class="row">
                        <div class="col-md-4 mb-3" v-for="stat in teamStatistics" :key="stat.team_id">
                            <div class="card border-primary h-100">
                                <div class="card-body">
                                    <h6 class="card-title">{{ stat.team_name || 'チーム未所属' }}</h6>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">メンバー数:</span>
                                        <strong>{{ stat.member_count }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">売上高:</span>
                                        <strong>¥{{ formatNumber(stat.total_revenue) }}</strong>
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
                    <div v-else-if="statistics.length > 0" class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>期間</th>
                                    <th>チーム</th>
                                    <th>従業員名</th>
                                    <th class="text-end">売上高</th>
                                    <th class="text-end">図面売上</th>
                                    <th class="text-center">図面数</th>
                                    <th class="text-center">タスク数</th>
                                    <th class="text-center">
                                        <i class="fa fa-thumbs-up text-success"></i> 良い
                                    </th>
                                    <th class="text-center">
                                        <i class="fa fa-thumbs-down text-danger"></i> 悪い
                                    </th>
                                    <th>更新日時</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="stat in statistics" :key="stat.id">
                                    <td>
                                        <div class="d-flex flex-column">
                                            <small class="text-muted">{{ getPeriodTypeLabel(stat.period_type) }}</small>
                                            <span>{{ formatDate(stat.period_start) }} ～ {{ formatDate(stat.period_end) }}</span>
                                        </div>
                                    </td>
                                    <td>{{ stat.team_name || '-' }}</td>
                                    <td><strong>{{ stat.user_name }}</strong></td>
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
                        <p class="text-muted">期間を選択して「統計計算」ボタンをクリックしてください</p>
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
</style>

<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="assets/js/employee-statistics.js?v=<?=CACHE_VERSION?>"></script>

