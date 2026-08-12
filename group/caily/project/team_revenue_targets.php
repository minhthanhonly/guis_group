<?php
require_once('../application/loader.php');
$view->heading('チーム売上目標設定');
$canAccessTeamRevenueTargets = !empty($_SESSION['isProjectManager'])
    || (($_SESSION['userid'] ?? '') === 'hayashida');
if(!$canAccessTeamRevenueTargets){
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
                    <i class="fa fa-bullseye me-2"></i>チーム売上目標設定
                </h4>
            </div>
        </div>

        <div class="col-12 mb-3">
            <nav class="navbar navbar-expand-lg bg-dark">
                <div class="container-fluid">
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#teamRevenueDeptNav" aria-controls="teamRevenueDeptNav" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse justify-content-start" id="teamRevenueDeptNav">
                        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                            <li class="nav-item" v-for="department in departments" :key="department.id"
                                :class="{ 'active bg-primary text-white rounded-3': selectedDepartment && selectedDepartment.id === department.id }">
                                <a href="#" class="nav-link" @click.prevent="selectDepartment(department)">{{ department.name }}</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
            <div v-if="!departments.length && !loadingDepartments" class="alert alert-warning mt-2 mb-0">
                表示できる部署がありません。
            </div>
        </div>

        <!-- Filters -->
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">年度 (7月〜翌年6月)</label>
                            <select class="form-select" v-model="selectedYear" @change="onYearChange">
                                <option v-for="opt in availableYears" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end gap-2 flex-wrap">
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
                            <div class="d-flex h-100 justify-content-between align-items-center p-3 border border-primary rounded">
                                <div>
                                    <small class="text-muted d-block">チーム数</small>
                                    <h4 class="mb-0">{{ teams.length }}</h4>
                                </div>
                                <i class="fa fa-users fa-2x text-primary"></i>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 border border-primary rounded h-100">
                                <div>
                                    <small class="text-muted d-block">部署目標売上</small>
                                    <div class="input-group mt-2">
                                        <span class="input-group-text">¥</span>
                                        <input type="number"
                                               class="form-control text-end"
                                               v-model.number="departmentYearlyTarget"
                                               @blur="onDepartmentTargetBlur"
                                               step="1000"
                                               min="0"
                                               placeholder="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex h-100 justify-content-between align-items-center p-3 border border-primary rounded">
                                <div>
                                    <small class="text-muted d-block">部署月間目標</small>
                                    <h4 class="mb-0">¥{{ formatNumber(departmentMonthlyTarget) }}</h4>
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
                    <h5 class="card-title mb-0">{{ selectedYear }}年のチーム売上目標 <span v-if="selectedDepartment">- {{ selectedDepartment.name }}</span></h5>
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
                                    <th style="width: 35%;">チーム名</th>
                                    <th class="text-end" style="width: 25%;">年間目標 (¥)</th>
                                    <th class="text-end" style="width: 25%;">月間目標 (¥)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(team, index) in teams" :key="team.id">
                                    <td>{{ index + 1 }}</td>
                                    <td>
                                        <strong>{{ team.name }}</strong>
                                    </td>
                                    <td class="text-end">
                                        <div class="input-group">
                                            <span class="input-group-text">¥</span>
                                            <input type="number" 
                                                   class="form-control text-end" 
                                                   v-model.number="team.yearly_target" 
                                                   @input="onTeamTargetInput(team)"
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
                                </tr>
                            </tbody>
                            <tfoot class="table-secondary fw-semibold">
                                <tr>
                                    <td colspan="2" data-i18n="合計">合計</td>
                                    <td class="text-end">
                                        <div>¥{{ formatNumber(totalYearlyTarget) }}</div>
                                        <small v-if="yearlyTotalCompareMessage"
                                               :class="yearlyTotalCompareStatus === 'over' ? 'text-orange' : 'text-danger'"
                                               class="d-block mt-1 fw-semibold">
                                            {{ yearlyTotalCompareMessage }}
                                        </small>
                                    </td>
                                    <td class="text-end">¥{{ formatNumber(totalMonthlyTarget) }}</td>
                                </tr>
                            </tfoot>
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
.text-orange {
    color: #fd7e14 !important;
}
</style>

<?php
$root = ROOT;
?>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="assets/js/team-revenue-targets.js?v=<?=PROJECT_CACHE_VERSION?>"></script>

