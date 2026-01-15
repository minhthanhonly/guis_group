<?php
require_once('../application/loader.php');
$view->heading('チーム売上目標設定');
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
                    <i class="fa fa-bullseye me-2"></i>チーム売上目標設定
                </h4>
            </div>
        </div>

        <!-- Filters -->
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">年度 (7月〜翌年6月)</label>
                            <select class="form-select" v-model="selectedYear" @change="loadTargets">
                                <option v-for="opt in availableYears" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>
                        <div class="col-md-8 d-flex align-items-end gap-2">
                            <button class="btn btn-primary" @click="saveAllTargets" :disabled="saving">
                                <i class="fa fa-save me-1"></i>
                                <span v-if="saving">保存中...</span>
                                <span v-else>すべて保存</span>
                            </button>
                            <button class="btn btn-outline-secondary" @click="loadTargets" :disabled="loading">
                                <i class="fa fa-refresh me-1"></i>更新
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Card -->
        <div class="col-12" v-if="!loading && teams.length > 0">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">目標サマリー</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <div>
                                    <small class="text-muted d-block">チーム数</small>
                                    <h4 class="mb-0">{{ teams.length }}</h4>
                                </div>
                                <i class="fa fa-users fa-2x text-primary"></i>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <div>
                                    <small class="text-muted d-block">年間目標合計</small>
                                    <h4 class="mb-0">¥{{ formatNumber(totalYearlyTarget) }}</h4>
                                </div>
                                <i class="fa fa-yen-sign fa-2x text-success"></i>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <div>
                                    <small class="text-muted d-block">月間目標合計</small>
                                    <h4 class="mb-0">¥{{ formatNumber(totalMonthlyTarget) }}</h4>
                                </div>
                                <i class="fa fa-calendar fa-2x text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Targets Table -->
        <div class="col-12 mt-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">{{ selectedYear }}年のチーム売上目標</h5>
                    <small class="text-muted">月間目標は年間目標を12で割った値です</small>
                </div>
                <div class="card-body">
                    <!-- Loading State -->
                    <div v-if="loading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">読み込み中...</span>
                        </div>
                        <p class="mt-2 text-muted">データを読み込み中...</p>
                    </div>

                    <!-- Targets Table -->
                    <div v-else-if="teams.length > 0" class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 5%;">#</th>
                                    <th style="width: 20%;">部署</th>
                                    <th style="width: 25%;">チーム名</th>
                                    <th class="text-end" style="width: 20%;">年間目標 (¥)</th>
                                    <th class="text-end" style="width: 20%;">月間目標 (¥)</th>
                                    <th style="width: 10%;">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(team, index) in teams" :key="team.id">
                                    <td>{{ index + 1 }}</td>
                                    <td>{{ team.department_name || '-' }}</td>
                                    <td>
                                        <strong>{{ team.name }}</strong>
                                    </td>
                                    <td class="text-end">
                                        <div class="input-group">
                                            <span class="input-group-text">¥</span>
                                            <input type="number" 
                                                   class="form-control text-end" 
                                                   v-model.number="team.yearly_target" 
                                                   @input="updateMonthlyTarget(team)"
                                                   step="1000"
                                                   min="0"
                                                   placeholder="0">
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <span class="text-muted fw-bold">
                                            ¥{{ formatNumber(team.monthly_target || 0) }}
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" 
                                                @click="saveTarget(team)" 
                                                :disabled="saving">
                                            <i class="fa fa-save me-1"></i>保存
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="text-center py-5">
                        <i class="fa fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">チームがありません</h5>
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
.input-group-text {
    background-color: #f8f9fa;
}
</style>

<?php
$root = ROOT;
?>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="assets/js/team-revenue-targets.js?v=<?=CACHE_VERSION?>"></script>

