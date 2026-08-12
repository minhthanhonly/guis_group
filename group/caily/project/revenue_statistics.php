<?php

require_once('../application/loader.php');
$view->heading('月次売上統計');

if ($_SESSION['show_project'] == 0) {
    echo '<div class="container-fluid mt-4"><div class="alert alert-danger">権限がありません。</div></div>';
    exit;
}

$permModel = new ApplicationModel();
$canAccessRevenueStats = ($_SESSION['authority'] ?? '') === 'administrator'
    || $permModel->hasDepartmentPermission('project_director_stat');

if (!$canAccessRevenueStats) {
    echo '<div class="container-fluid mt-4"><div class="alert alert-danger">権限がありません。</div></div>';
    exit;
}
?>

<div id="app" class="container-fluid mt-4 mb-5" v-cloak>
    <div class="row mb-3">
        <div class="col-12">
            <h4 class="mb-0">
                <i class="fa fa-yen-sign me-2"></i><span data-i18n="月次売上統計">月次売上統計</span>
            </h4>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-3">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#revenueDeptNav" aria-controls="revenueDeptNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-between" id="revenueDeptNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item" v-for="department in departments" :key="department.id"
                        :class="{ 'active bg-primary text-white rounded-3': selectedDepartment && selectedDepartment.id === department.id }">
                        <a href="#" class="nav-link" @click.prevent="selectDepartment(department)">{{ department.name }}</a>
                    </li>
                </ul>
                <div class="revenue-dept-nav-month d-flex align-items-center gap-2 flex-wrap ms-lg-auto mb-2 mb-lg-0">
                    <label class="form-label mb-0 text-nowrap revenue-month-label small" data-i18n="対象月">対象月</label>
                    <div class="btn-group btn-group-sm revenue-nav-btn-group" role="group" aria-label="Month navigation">
                        <button class="btn revenue-nav-btn" type="button" @click="goToPrevMonth" :disabled="loading || !selectedDepartment">
                            <i class="fa fa-chevron-left"></i><span class="visually-hidden" data-i18n="前月">前月</span>
                        </button>
                        <button class="btn revenue-nav-btn" type="button" @click="goToThisMonth" :disabled="loading || !selectedDepartment || selectedMonth === currentYearMonthValue">
                            <span data-i18n="今月">今月</span>
                        </button>
                        <button class="btn revenue-nav-btn" type="button" @click="goToNextMonth" :disabled="loading || !selectedDepartment">
                            <i class="fa fa-chevron-right"></i><span class="visually-hidden" data-i18n="次月">次月</span>
                        </button>
                    </div>
                    <select class="form-select form-select-sm revenue-month-select" v-model="selectedMonth" @change="onMonthChange">
                        <option v-for="m in availableMonths" :key="m.month" :value="m.month">{{ m.month_label || m.month }}</option>
                    </select>
                    <button class="btn btn-sm revenue-nav-btn" type="button" @click="loadStats" :disabled="loading || !selectedDepartment">
                        <i class="fa fa-refresh me-1"></i><span data-i18n="更新">更新</span>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <div v-if="!departments.length && !loadingDepartments" class="alert alert-warning" data-i18n="表示できる部署がありません。">
        表示できる部署がありません。
    </div>

    <template v-if="selectedDepartment">
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link" :class="{ active: activeSubTab === 'summary' }" type="button" @click="setActiveSubTab('summary')">
                    <i class="fa fa-table me-1"></i><span data-i18n="まとめ">まとめ</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" :class="{ active: activeSubTab === 'detail' }" type="button" @click="setActiveSubTab('detail')">
                    <i class="fa fa-list me-1"></i><span data-i18n="請求済み案件">請求済み案件</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" :class="{ active: activeSubTab === 'backlog' }" type="button" @click="setActiveSubTab('backlog')">
                    <i class="fa fa-clock-o me-1"></i><span data-i18n="未請求案件(完了・見積済)">未請求案件(完了・見積済)</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" :class="{ active: activeSubTab === 'estimated' }" type="button" @click="setActiveSubTab('estimated')">
                    <i class="fa fa-file-text me-1"></i><span data-i18n="見積済案件(未完了案件)">見積済案件(未完了案件)</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" :class="{ active: activeSubTab === 'cancelled' }" type="button" @click="setActiveSubTab('cancelled')">
                    <i class="fa fa-ban me-1"></i><span data-i18n="キャンセル案件">キャンセル案件</span>
                </button>
            </li>
        </ul>

        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted" data-i18n="読み込み中...">読み込み中...</p>
        </div>

        <div v-else-if="errorMessage" class="alert alert-danger">{{ errorMessage }}</div>

        <div v-else-if="activeSubTab === 'summary'">
            <div class="card mb-3 revenue-target-summary-card">
                <div class="card-header revenue-target-summary-header">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h5 class="mb-0">
                            <i class="fa fa-bullseye me-2 text-primary"></i>
                            <span data-i18n="目標と実績">目標と実績</span>
                            <span class="text-muted fw-normal ms-1">（{{ monthLabel }}）</span>
                        </h5>
                        <span class="badge bg-primary revenue-target-dept-badge">{{ selectedDepartment.name }}</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="revenue-target-panel revenue-target-panel-monthly h-100 mt-3">
                                <div class="revenue-target-panel-head">
                                    <span class="revenue-target-panel-icon"><i class="fa fa-calendar"></i></span>
                                    <span data-i18n="月間">月間</span>
                                </div>
                                <div class="row g-2 revenue-target-metrics">
                                    <div class="col-sm-6">
                                        <div class="revenue-target-metric revenue-target-metric-target">
                                            <span class="revenue-target-metric-label" data-i18n="月間目標売上">月間目標売上</span>
                                            <span class="revenue-target-metric-value">{{ formatCurrency(targetSummary.monthly_target_sales) }}</span>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="revenue-target-metric revenue-target-metric-actual">
                                            <span class="revenue-target-metric-label" data-i18n="月間実績売上">月間実績売上</span>
                                            <span class="revenue-target-metric-value">{{ formatCurrency(targetSummary.monthly_actual_sales) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="revenue-target-rate">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="revenue-target-rate-label" data-i18n="達成率">達成率</span>
                                        <span class="revenue-target-rate-value fw-bold" :class="achievementRateTextClass(monthlyAchievementRate)">{{ formatPercent(monthlyAchievementRate) }}</span>
                                    </div>
                                    <div class="progress revenue-target-progress">
                                        <div class="progress-bar" :class="achievementRateBarClass(monthlyAchievementRate)" :style="{ width: achievementBarWidth(monthlyAchievementRate) }" role="progressbar"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="revenue-target-panel revenue-target-panel-cumulative h-100 mt-3">
                                <div class="revenue-target-panel-head">
                                    <span class="revenue-target-panel-icon"><i class="fa fa-line-chart"></i></span>
                                    <span data-i18n="累計">累計</span>
                                </div>
                                <div class="row g-2 revenue-target-metrics">
                                    <div class="col-sm-6">
                                        <div class="revenue-target-metric revenue-target-metric-target">
                                            <span class="revenue-target-metric-label" data-i18n="累計目標売上">累計目標売上</span>
                                            <span class="revenue-target-metric-value">{{ formatCurrency(targetSummary.cumulative_target_sales) }}</span>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="revenue-target-metric revenue-target-metric-actual">
                                            <span class="revenue-target-metric-label" data-i18n="累計実績売上">累計実績売上</span>
                                            <span class="revenue-target-metric-value">{{ formatCurrency(targetSummary.cumulative_actual_sales) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="revenue-target-rate">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="revenue-target-rate-label" data-i18n="達成率">達成率</span>
                                        <span class="revenue-target-rate-value fw-bold" :class="achievementRateTextClass(cumulativeAchievementRate)">{{ formatPercent(cumulativeAchievementRate) }}</span>
                                    </div>
                                    <div class="progress revenue-target-progress">
                                        <div class="progress-bar" :class="achievementRateBarClass(cumulativeAchievementRate)" :style="{ width: achievementBarWidth(cumulativeAchievementRate) }" role="progressbar"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        {{ selectedDepartment.name }} — <span data-i18n="会社別サマリー">会社別サマリー</span>（{{ monthLabel }}）
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 revenue-stats-table revenue-stats-table-summary-grouped">
                            <colgroup>
                                <col style="width: 10%;">
                                <col style="width: 26%;">
                                <col style="width: 22%;">
                                <col style="width: 14%;">
                                <col style="width: 22%;">
                            </colgroup>
                            <thead class="table-light">
                                <tr>
                                    <th colspan="2"></th>
                                    <th class="text-end" data-i18n="請求金額">請求金額</th>
                                    <th class="text-end" data-i18n="請求件数">請求件数</th>
                                    <th class="text-end" data-i18n="合計">合計</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!summaryByCompany.length">
                                    <td colspan="5" class="text-center text-muted py-4" data-i18n="データがありません">データがありません</td>
                                </tr>
                                <template v-else>
                                    <tr v-for="(row, idx) in summaryByCompany" :key="row.company_name"
                                        :class="{ 'revenue-summary-category-divider': idx < summaryByCompany.length - 1 }">
                                        <td v-if="idx === 0" :rowspan="summaryByCompany.length"
                                            class="revenue-summary-section-label text-center fw-semibold" data-i18n="請求">請求</td>
                                        <td class="revenue-summary-category-label revenue-stats-cell-company" :title="row.company_name">
                                            <span v-html="formatCompanyNameLabel(row.company_name)"></span>
                                            <span class="ms-1">{{ row.company_name }}</span>
                                        </td>
                                        <td class="text-end">{{ formatSummaryAmount(row.invoice_amount) }}</td>
                                        <td class="text-end">{{ formatCount(row.invoice_count) }}</td>
                                        <td class="text-end">
                                            <template v-if="idx === summaryByCompany.length - 1">
                                                {{ formatCurrency(summaryTotals.invoice_amount) }}
                                            </template>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="revenue-summary-unbilled-label text-center fw-semibold" data-i18n="未請求分">未請求分</td>
                                        <td class="text-end">{{ formatSummaryAmount(summaryUnbilledTotals.amount) }}</td>
                                        <td class="text-end">{{ formatCount(summaryUnbilledTotals.project_count) }}</td>
                                        <td class="text-end">{{ formatSummaryAmount(summaryUnbilledTotals.amount) }}</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-6" v-for="block in summaryTantouBlocks" :key="block.tantou">
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <span data-i18n="担当別サマリー">担当別サマリー</span> — {{ block.tantou }}（{{ monthLabel }}）
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0 revenue-stats-table revenue-stats-table-summary">
                                    <colgroup>
                                        <col style="width: 36%;">
                                        <col style="width: 16%;">
                                        <col style="width: 48%;">
                                    </colgroup>
                                    <thead class="table-light">
                                        <tr>
                                            <th data-i18n="会社">会社</th>
                                            <th class="text-end" data-i18n="請求件数">請求件数</th>
                                            <th class="text-end" data-i18n="請求金額">請求金額</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!block.rows.length">
                                            <td colspan="3" class="text-center text-muted py-4" data-i18n="データがありません">データがありません</td>
                                        </tr>
                                        <tr v-for="row in block.rows" :key="block.tantou + '-' + row.company_name">
                                            <td class="revenue-stats-cell-company" :title="row.company_name">
                                                <span v-html="formatCompanyNameLabel(row.company_name)"></span>
                                                <span class="ms-1">{{ row.company_name }}</span>
                                            </td>
                                            <td class="text-end">{{ formatCount(row.invoice_count) }}</td>
                                            <td class="text-end">{{ formatCurrency(row.invoice_amount) }}</td>
                                        </tr>
                                    </tbody>
                                    <tfoot v-if="block.rows.length" class="table-secondary fw-semibold">
                                        <tr>
                                            <td data-i18n="合計">合計</td>
                                            <td class="text-end">{{ formatCount(block.totals.invoice_count) }}</td>
                                            <td class="text-end">{{ formatCurrency(block.totals.invoice_amount) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else-if="activeSubTab === 'detail'">
            

            <div class="card mb-4">
                <div class="card-header revenue-stats-card-header">
                    <h5 class="mb-0">
                        <span data-i18n="請求済み案件">請求済み案件</span>（{{ monthLabel }}）
                        <span class="form-check form-check-inline ms-3 mb-0 align-middle">
                            <input class="form-check-input" type="checkbox" id="tantouCailyInvoicedToggle" v-model="showTantouCaily">
                            <label class="form-check-label small" for="tantouCailyInvoicedToggle">CAILY</label>
                        </span>
                        <span class="form-check form-check-inline ms-2 mb-0 align-middle">
                            <input class="form-check-input" type="checkbox" id="tantouGuisInvoicedToggle" v-model="showTantouGuis">
                            <label class="form-check-label small" for="tantouGuisInvoicedToggle">GUIS</label>
                        </span>
                    </h5>
                    <div class="revenue-stats-card-header-actions">
                        <div v-if="invoicedGroups.length" class="revenue-stats-card-header-totals revenue-stats-card-header-totals-invoiced">
                            <span class="revenue-stats-header-total-label" data-i18n="請求金額合計">請求金額合計</span>
                            <span class="revenue-stats-header-total-value">{{ formatCurrency(invoicedInvoiceAmountTotal) }}</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-success" :disabled="loading || !invoicedGroups.length" @click="exportTabToExcel('detail')" title="Excel出力">
                            <i class="fa fa-file-excel me-1"></i><span data-i18n="Excel出力">Excel出力</span>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div v-if="!invoicedGroups.length" class="text-muted text-center py-3" data-i18n="データがありません">データがありません</div>
                    <div v-for="group in invoicedGroups" :key="'inv-' + group.company_name" class="mb-4">
                        <h6 class="border-bottom pb-2 mb-2">
                            <span v-html="formatCompanyNameLabel(group.company_name)"></span>
                            <span class="ms-1">{{ group.company_name }}</span>
                            <span class="badge bg-label-secondary ms-2">{{ group.projects.length }}</span>
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0 revenue-stats-table revenue-stats-table-invoiced  table-narrow">
                                <colgroup>
                                    <col style="width: 4%;">
                                    <col style="width: 13%;">
                                    <col style="width: 8%;">
                                    <col style="width: 7%;">
                                    <col style="width: 6%;">
                                    <col style="width: 7%;">
                                    <col style="width: 8%;">
                                    <col style="width: 9%;">
                                    <col style="width: 9%;">
                                    <col style="width: 9%;">
                                    <col style="width: 12%;">
                                    <col style="width: 12%;">
                                </colgroup>
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th data-i18n="案件名">案件名</th>
                                        <th data-i18n="支店名">支店名</th>
                                        <th data-i18n="工事番号">工事番号</th>
                                        <th data-i18n="担当">担当</th>
                                        <th data-i18n="受注形態">受注形態</th>
                                        <th data-i18n="規模">規模</th>
                                        <th data-i18n="期限日">期限日</th>
                                        <th data-i18n="チーム">チーム</th>
                                        <th data-i18n="請求日">請求日</th>
                                        <th class="text-end" data-i18n="請求金額">請求金額</th>
                                        <th data-i18n="決済備考">決済備考</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="p in group.projects" :key="p.id">
                                        <td>{{ p.id }}</td>
                                        <td class="revenue-stats-cell-name" :title="p.name"><a :href="'detail.php?id=' + p.id" target="_blank">{{ p.name }}</a></td>
                                        <td class="revenue-stats-cell-text" :title="p.branch_name">{{ p.branch_name || '—' }}</td>
                                        <td class="revenue-stats-cell-text" :title="p.parent_construction_number">{{ p.parent_construction_number || '—' }}</td>
                                        <td>{{ p.tantou || '—' }}</td>
                                        <td>{{ p.project_order_type || '—' }}</td>
                                        <td class="revenue-stats-cell-text" :title="p.parent_scale">{{ p.parent_scale || '—' }}</td>
                                        <td>{{ formatDate(p.end_date) }}</td>
                                        <td class="revenue-stats-cell-text" :title="formatProjectTeams(p)">{{ formatProjectTeams(p) }}</td>
                                        <td>{{ formatDate(p.invoice_date) }}</td>
                                        <td class="text-end">{{ formatCurrency(p.invoice_amount) }}</td>
                                        <td class="revenue-stats-cell-text" :title="p.payment_note">{{ p.payment_note || '—' }}</td>
                                    </tr>
                                </tbody>
                                <tfoot v-if="group.projects.length" class="table-secondary fw-semibold">
                                    <tr>
                                        <td colspan="9" data-i18n="合計">合計</td>
                                        <td></td>
                                        <td class="text-end">{{ formatCurrency(group.totals.invoice_amount) }}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div v-else-if="activeSubTab === 'backlog'">
            <div class="card mb-4">
                <div class="card-header revenue-stats-card-header">
                    <h5 class="mb-0">
                        <span data-i18n="完了・未請求案件(見積済)">完了・未請求案件(見積済)</span>（<span data-i18n="すべての期間">すべての期間</span>）
                        <span class="form-check form-check-inline ms-3 mb-0 align-middle">
                            <input class="form-check-input" type="checkbox" id="tantouCailyBacklogToggle" v-model="showTantouCaily">
                            <label class="form-check-label small" for="tantouCailyBacklogToggle">CAILY</label>
                        </span>
                        <span class="form-check form-check-inline ms-2 mb-0 align-middle">
                            <input class="form-check-input" type="checkbox" id="tantouGuisBacklogToggle" v-model="showTantouGuis">
                            <label class="form-check-label small" for="tantouGuisBacklogToggle">GUIS</label>
                        </span>
                    </h5>
                    <div class="revenue-stats-card-header-actions">
                        <div v-if="backlogGroups.length" class="revenue-stats-card-header-totals revenue-stats-card-header-totals-backlog">
                            <span class="revenue-stats-header-total-label" data-i18n="見積金額合計">見積金額合計</span>
                            <span class="revenue-stats-header-total-value">{{ formatCurrency(backlogAmountTotal) }}</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-success" :disabled="loading || !backlogGroups.length" @click="exportTabToExcel('backlog')" title="Excel出力">
                            <i class="fa fa-file-excel me-1"></i><span data-i18n="Excel出力">Excel出力</span>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div v-if="!backlogGroups.length" class="text-muted text-center py-3" data-i18n="データがありません">データがありません</div>
                    <div v-for="group in backlogGroups" :key="'back-' + group.company_name" class="mb-4">
                        <h6 class="border-bottom pb-2 mb-2">
                            <span v-html="formatCompanyNameLabel(group.company_name)"></span>
                            <span class="ms-1">{{ group.company_name }}</span>
                            <span class="badge bg-label-secondary ms-2">{{ group.projects.length }}</span>
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0 revenue-stats-table revenue-stats-table-backlog table-narrow">
                                <colgroup>
                                    <col style="width: 4%;">
                                    <col style="width: 12%;">
                                    <col style="width: 7%;">
                                    <col style="width: 7%;">
                                    <col style="width: 5%;">
                                    <col style="width: 6%;">
                                    <col style="width: 7%;">
                                    <col style="width: 8%;">
                                    <col style="width: 9%;">
                                    <col style="width: 8%;">
                                    <col style="width: 8%;">
                                    <col style="width: 10%;">
                                </colgroup>
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th data-i18n="案件名">案件名</th>
                                        <th data-i18n="支店名">支店名</th>
                                        <th data-i18n="工事番号">工事番号</th>
                                        <th data-i18n="担当">担当</th>
                                        <th data-i18n="受注形態">受注形態</th>
                                        <th data-i18n="規模">規模</th>
                                        <th data-i18n="期限日">期限日</th>
                                        <th data-i18n="チーム">チーム</th>
                                        <th class="text-end" data-i18n="見積金額">見積金額</th>
                                        <th data-i18n="完了日">完了日</th>
                                        <th data-i18n="決済備考">決済備考</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="p in group.projects" :key="p.id">
                                        <td>{{ p.id }}</td>
                                        <td class="revenue-stats-cell-name" :title="p.name"><a :href="'detail.php?id=' + p.id" target="_blank">{{ p.name }}</a></td>
                                        <td class="revenue-stats-cell-text" :title="p.branch_name">{{ p.branch_name || '—' }}</td>
                                        <td class="revenue-stats-cell-text" :title="p.parent_construction_number">{{ p.parent_construction_number || '—' }}</td>
                                        <td>{{ p.tantou || '—' }}</td>
                                        <td>{{ p.project_order_type || '—' }}</td>
                                        <td class="revenue-stats-cell-text" :title="p.parent_scale">{{ p.parent_scale || '—' }}</td>
                                        <td>{{ formatDate(p.end_date) }}</td>
                                        <td class="revenue-stats-cell-text" :title="formatProjectTeams(p)">{{ formatProjectTeams(p) }}</td>
                                        <td class="text-end">{{ formatCurrency(p.amount) }}</td>
                                        <td>{{ formatDate(p.actual_end_date) }}</td>
                                        <td class="revenue-stats-cell-text" :title="p.payment_note">{{ p.payment_note || '—' }}</td>
                                    </tr>
                                </tbody>
                                <tfoot v-if="group.projects.length" class="table-secondary fw-semibold">
                                    <tr>
                                        <td colspan="9" data-i18n="合計">合計</td>
                                        <td class="text-end">{{ formatCurrency(group.totals.amount) }}</td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else-if="activeSubTab === 'estimated'">
            <div class="card">
                <div class="card-header revenue-stats-card-header">
                    <h5 class="mb-0">
                        <span data-i18n="見積済案件(未完了案件)">見積済案件(未完了案件)</span>
                        （<span v-if="showEstimatedAllTime" data-i18n="すべての期間">すべての期間</span><span v-else>{{ monthLabel }}</span>）
                        <span class="form-check form-check-inline ms-3 mb-0 align-middle">
                            <input class="form-check-input" type="checkbox" id="tantouCailyEstimatedToggle" v-model="showTantouCaily">
                            <label class="form-check-label small" for="tantouCailyEstimatedToggle">CAILY</label>
                        </span>
                        <span class="form-check form-check-inline ms-2 mb-0 align-middle">
                            <input class="form-check-input" type="checkbox" id="tantouGuisEstimatedToggle" v-model="showTantouGuis">
                            <label class="form-check-label small" for="tantouGuisEstimatedToggle">GUIS</label>
                        </span>
                        <span class="form-check form-check-inline ms-3 mb-0 align-middle">
                            <input class="form-check-input" type="checkbox" id="estimatedAllTimeToggle" v-model="showEstimatedAllTime" @change="onEstimatedAllTimeChange($event.target.checked)">
                            <label class="form-check-label small" for="estimatedAllTimeToggle" data-i18n="すべての期間">すべての期間</label>
                        </span>
                    </h5>
                    <div class="revenue-stats-card-header-actions">
                        <div v-if="estimatedGroups.length" class="revenue-stats-card-header-totals revenue-stats-card-header-totals-estimated">
                            <span class="revenue-stats-header-total-label" data-i18n="見積金額合計">見積金額合計</span>
                            <span class="revenue-stats-header-total-value">{{ formatCurrency(estimatedAmountTotal) }}</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-success" :disabled="loading || !estimatedGroups.length" @click="exportTabToExcel('estimated')" title="Excel出力">
                            <i class="fa fa-file-excel me-1"></i><span data-i18n="Excel出力">Excel出力</span>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div v-if="!estimatedGroups.length" class="text-muted text-center py-3" data-i18n="データがありません">データがありません</div>
                    <div v-for="group in estimatedGroups" :key="'est-' + group.company_name" class="mb-4">
                        <h6 class="border-bottom pb-2 mb-2">
                            <span v-html="formatCompanyNameLabel(group.company_name)"></span>
                            <span class="ms-1">{{ group.company_name }}</span>
                            <span class="badge bg-label-secondary ms-2">{{ group.projects.length }}</span>
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0 revenue-stats-table revenue-stats-table-estimated  table-narrow">
                                <colgroup>
                                    <col style="width: 4%;">
                                    <col style="width: 11%;">
                                    <col style="width: 7%;">
                                    <col style="width: 7%;">
                                    <col style="width: 5%;">
                                    <col style="width: 6%;">
                                    <col style="width: 7%;">
                                    <col style="width: 8%;">
                                    <col style="width: 8%;">
                                    <col style="width: 8%;">
                                    <col style="width: 8%;">
                                    <col style="width: 10%;">
                                    <col style="width: 8%;">
                                </colgroup>
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th data-i18n="案件名">案件名</th>
                                        <th data-i18n="支店名">支店名</th>
                                        <th data-i18n="工事番号">工事番号</th>
                                        <th data-i18n="担当">担当</th>
                                        <th data-i18n="受注形態">受注形態</th>
                                        <th data-i18n="規模">規模</th>
                                        <th data-i18n="期限日">期限日</th>
                                        <th data-i18n="チーム">チーム</th>
                                        <th class="text-end" data-i18n="見積金額">見積金額</th>
                                        <th data-i18n="見積日">見積日</th>
                                        <th data-i18n="見積状況">見積状況</th>
                                        <th data-i18n="決済備考">決済備考</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="p in group.projects" :key="p.id">
                                        <td>{{ p.id }}</td>
                                        <td class="revenue-stats-cell-name" :title="p.name"><a :href="'detail.php?id=' + p.id" target="_blank">{{ p.name }}</a></td>
                                        <td class="revenue-stats-cell-text" :title="p.branch_name">{{ p.branch_name || '—' }}</td>
                                        <td class="revenue-stats-cell-text" :title="p.parent_construction_number">{{ p.parent_construction_number || '—' }}</td>
                                        <td>{{ p.tantou || '—' }}</td>
                                        <td>{{ p.project_order_type || '—' }}</td>
                                        <td class="revenue-stats-cell-text" :title="p.parent_scale">{{ p.parent_scale || '—' }}</td>
                                        <td>{{ formatDate(p.end_date) }}</td>
                                        <td class="revenue-stats-cell-text" :title="formatProjectTeams(p)">{{ formatProjectTeams(p) }}</td>
                                        <td class="text-end">{{ formatCurrency(p.amount) }}</td>
                                        <td>{{ formatDate(p.estimate_date) }}</td>
                                        <td>{{ p.estimate_status || '—' }}</td>
                                        <td class="revenue-stats-cell-text" :title="p.payment_note">{{ p.payment_note || '—' }}</td>
                                    </tr>
                                </tbody>
                                <tfoot v-if="group.projects.length" class="table-secondary fw-semibold">
                                    <tr>
                                        <td colspan="9" data-i18n="合計">合計</td>
                                        <td class="text-end">{{ formatCurrency(group.totals.amount) }}</td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div v-else-if="activeSubTab === 'cancelled'">
            <div class="card">
                <div class="card-header revenue-stats-card-header">
                    <h5 class="mb-0">
                        <span data-i18n="キャンセル案件">キャンセル案件</span>
                        （<span v-if="showCancelledAllTime" data-i18n="すべての期間">すべての期間</span><span v-else>{{ monthLabel }}</span>）
                        <span class="form-check form-check-inline ms-3 mb-0 align-middle">
                            <input class="form-check-input" type="checkbox" id="tantouCailyCancelledToggle" v-model="showTantouCaily">
                            <label class="form-check-label small" for="tantouCailyCancelledToggle">CAILY</label>
                        </span>
                        <span class="form-check form-check-inline ms-2 mb-0 align-middle">
                            <input class="form-check-input" type="checkbox" id="tantouGuisCancelledToggle" v-model="showTantouGuis">
                            <label class="form-check-label small" for="tantouGuisCancelledToggle">GUIS</label>
                        </span>
                        <span class="form-check form-check-inline ms-3 mb-0 align-middle">
                            <input class="form-check-input" type="checkbox" id="cancelledAllTimeToggle" v-model="showCancelledAllTime" @change="onCancelledAllTimeChange($event.target.checked)">
                            <label class="form-check-label small" for="cancelledAllTimeToggle" data-i18n="すべての期間">すべての期間</label>
                        </span>
                        <span class="form-check form-check-inline ms-2 mb-0 align-middle">
                            <input class="form-check-input" type="checkbox" id="cancelledEstimatedOnlyToggle" v-model="showCancelledEstimatedOnly" @change="onCancelledEstimatedOnlyChange($event.target.checked)">
                            <label class="form-check-label small" for="cancelledEstimatedOnlyToggle" data-i18n="見積済みのみ">見積済みのみ</label>
                        </span>
                    </h5>
                    <div class="revenue-stats-card-header-actions">
                        <div v-if="cancelledGroups.length" class="revenue-stats-card-header-totals revenue-stats-card-header-totals-cancelled">
                            <span class="revenue-stats-header-total-label" data-i18n="見積金額合計">見積金額合計</span>
                            <span class="revenue-stats-header-total-value">{{ formatCurrency(cancelledAmountTotal) }}</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-success" :disabled="loading || !cancelledGroups.length" @click="exportTabToExcel('cancelled')" title="Excel出力">
                            <i class="fa fa-file-excel me-1"></i><span data-i18n="Excel出力">Excel出力</span>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div v-if="!cancelledGroups.length" class="text-muted text-center py-3" data-i18n="データがありません">データがありません</div>
                    <div v-for="group in cancelledGroups" :key="'cancelled-' + group.company_name" class="mb-4">
                        <h6 class="border-bottom pb-2 mb-2">
                            <span v-html="formatCompanyNameLabel(group.company_name)"></span>
                            <span class="ms-1">{{ group.company_name }}</span>
                            <span class="badge bg-label-secondary ms-2">{{ group.projects.length }}</span>
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0 revenue-stats-table revenue-stats-table-cancelled  table-narrow">
                                <colgroup>
                                    <col style="width: 4%;">
                                    <col style="width: 13%;">
                                    <col style="width: 8%;">
                                    <col style="width: 8%;">
                                    <col style="width: 6%;">
                                    <col style="width: 8%;">
                                    <col style="width: 9%;">
                                    <col style="width: 9%;">
                                    <col style="width: 11%;">
                                    <col style="width: 10%;">
                                    <col style="width: 10%;">
                                </colgroup>
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th data-i18n="案件名">案件名</th>
                                        <th data-i18n="支店名">支店名</th>
                                        <th data-i18n="工事番号">工事番号</th>
                                        <th data-i18n="担当">担当</th>
                                        <th data-i18n="受注形態">受注形態</th>
                                        <th data-i18n="規模">規模</th>
                                        <th data-i18n="期限日">期限日</th>
                                        <th data-i18n="チーム">チーム</th>
                                        <th class="text-end" data-i18n="見積金額">見積金額</th>
                                        <th data-i18n="決済備考">決済備考</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="p in group.projects" :key="p.id">
                                        <td>{{ p.id }}</td>
                                        <td class="revenue-stats-cell-name" :title="p.name"><a :href="'detail.php?id=' + p.id" target="_blank">{{ p.name }}</a></td>
                                        <td class="revenue-stats-cell-text" :title="p.branch_name">{{ p.branch_name || '—' }}</td>
                                        <td class="revenue-stats-cell-text" :title="p.parent_construction_number">{{ p.parent_construction_number || '—' }}</td>
                                        <td>{{ p.tantou || '—' }}</td>
                                        <td>{{ p.project_order_type || '—' }}</td>
                                        <td class="revenue-stats-cell-text" :title="p.parent_scale">{{ p.parent_scale || '—' }}</td>
                                        <td>{{ formatDate(p.end_date) }}</td>
                                        <td class="revenue-stats-cell-text" :title="formatProjectTeams(p)">{{ formatProjectTeams(p) }}</td>
                                        <td class="text-end">{{ formatCurrency(p.amount) }}</td>
                                        <td class="revenue-stats-cell-text" :title="p.payment_note">{{ p.payment_note || '—' }}</td>
                                    </tr>
                                </tbody>
                                <tfoot v-if="group.projects.length" class="table-secondary fw-semibold">
                                    <tr>
                                        <td colspan="9" data-i18n="合計">合計</td>
                                        <td class="text-end">{{ formatCurrency(group.totals.amount) }}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<?php
$view->footing();
?>

<style>
#app .card {
    border: 1px solid rgba(0, 0, 0, 0.2);
}
.revenue-dept-nav-month {
    padding: 0.4rem 0.65rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.28);
    border-radius: 0.5rem;
}
.revenue-dept-nav-month .revenue-month-label {
    color: #fff;
    font-weight: 600;
}
.revenue-dept-nav-month .revenue-month-select {
    width: 10rem;
    background-color: #fff;
    color: #2f2b3d;
    border: 1px solid rgba(255, 255, 255, 0.45);
    font-weight: 600;
}
.revenue-dept-nav-month .revenue-month-select:focus {
    background-color: #fff;
    color: #2f2b3d;
    border-color: #fff;
    box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
}
.revenue-dept-nav-month .revenue-nav-btn {
    background-color: #fff;
    color: #2f2b3d;
    border: 1px solid rgba(255, 255, 255, 0.45);
    font-weight: 600;
}
.revenue-dept-nav-month .revenue-nav-btn:hover:not(:disabled),
.revenue-dept-nav-month .revenue-nav-btn:focus:not(:disabled) {
    background-color: #ececf1;
    color: #1e1e2d;
    border-color: #fff;
}
.revenue-dept-nav-month .revenue-nav-btn:disabled {
    background-color: rgba(255, 255, 255, 0.55);
    color: rgba(47, 43, 61, 0.55);
    border-color: rgba(255, 255, 255, 0.25);
}
.revenue-dept-nav-month .revenue-nav-btn-group .revenue-nav-btn + .revenue-nav-btn {
    border-left-color: rgba(47, 43, 61, 0.12);
}
[data-bs-theme="dark"] .revenue-dept-nav-month .revenue-month-select,
html.dark-style .revenue-dept-nav-month .revenue-month-select,
body.dark-style .revenue-dept-nav-month .revenue-month-select {
    background-color: #f1f0f5;
    color: #2f2b3d;
}
[data-bs-theme="dark"] .revenue-dept-nav-month .revenue-nav-btn,
html.dark-style .revenue-dept-nav-month .revenue-nav-btn,
body.dark-style .revenue-dept-nav-month .revenue-nav-btn {
    background-color: #f1f0f5;
    color: #2f2b3d;
}
.revenue-stats-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
}
.revenue-stats-card-header-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
    flex-shrink: 0;
}
.revenue-stats-card-header-totals {
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.2rem;
    padding: 0.55rem 1rem;
    border-radius: 0.75rem;
    border: 1px solid var(--bs-border-color);
    background: var(--bs-body-bg);
    min-width: 11rem;
    box-shadow: 0 0.125rem 0.35rem rgba(47, 43, 61, 0.08);
}
.revenue-stats-header-total-label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--bs-secondary-color);
    letter-spacing: 0.03em;
    white-space: nowrap;
}
.revenue-stats-header-total-value {
    font-size: 1.45rem;
    font-weight: 700;
    line-height: 1.15;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
.revenue-stats-card-header-totals-invoiced {
    border-color: rgba(var(--bs-success-rgb), 0.35);
    background: linear-gradient(135deg, rgba(var(--bs-success-rgb), 0.14) 0%, rgba(var(--bs-success-rgb), 0.04) 100%);
}
.revenue-stats-card-header-totals-invoiced .revenue-stats-header-total-value {
    color: var(--bs-success);
}
.revenue-stats-card-header-totals-backlog {
    border-color: rgba(var(--bs-warning-rgb), 0.4);
    background: linear-gradient(135deg, rgba(var(--bs-warning-rgb), 0.16) 0%, rgba(var(--bs-warning-rgb), 0.05) 100%);
}
.revenue-stats-card-header-totals-backlog .revenue-stats-header-total-value {
    color: #c87a00;
}
.revenue-stats-card-header-totals-estimated {
    border-color: rgba(var(--bs-info-rgb), 0.35);
    background: linear-gradient(135deg, rgba(var(--bs-info-rgb), 0.14) 0%, rgba(var(--bs-info-rgb), 0.04) 100%);
}
.revenue-stats-card-header-totals-estimated .revenue-stats-header-total-value {
    color: var(--bs-info);
}
.revenue-stats-card-header-totals-cancelled {
    border-color: rgba(var(--bs-danger-rgb), 0.32);
    background: linear-gradient(135deg, rgba(var(--bs-danger-rgb), 0.12) 0%, rgba(var(--bs-danger-rgb), 0.04) 100%);
}
.revenue-stats-card-header-totals-cancelled .revenue-stats-header-total-value {
    color: var(--bs-danger);
}
[data-bs-theme="dark"] .revenue-stats-card-header-totals-backlog .revenue-stats-header-total-value,
html.dark-style .revenue-stats-card-header-totals-backlog .revenue-stats-header-total-value,
body.dark-style .revenue-stats-card-header-totals-backlog .revenue-stats-header-total-value {
    color: #ffb84d;
}
[data-bs-theme="dark"] .revenue-stats-card-header-totals,
html.dark-style .revenue-stats-card-header-totals,
body.dark-style .revenue-stats-card-header-totals {
    box-shadow: 0 0.125rem 0.35rem rgba(0, 0, 0, 0.25);
}
.revenue-stats-card-header-company {
    display: inline-block;
    max-width: 14rem;
    overflow: hidden;
    text-overflow: ellipsis;
    vertical-align: bottom;
}
[data-bs-theme="dark"] #app .card,
html.dark-style #app .card,
body.dark-style #app .card {
    border-color: rgba(255, 255, 255, 0.25);
}
.revenue-stats-table {
    table-layout: fixed;
    width: 100%;
}
.revenue-stats-table-summary {
    min-width: 36rem;
}
.revenue-stats-table-summary-grouped {
    min-width: 42rem;
}
.revenue-stats-table-summary-grouped .revenue-summary-section-label {
    background-color: #4ea5ff;
    color: #000;
    vertical-align: middle;
}
.revenue-stats-table-summary-grouped .revenue-summary-category-label {
    background-color: #b8d9f8;
    color: #000;
}
.revenue-stats-table-summary-grouped .revenue-summary-unbilled-label {
    background-color: #d9d9d9;
    color: #000;
}
.revenue-stats-table-summary-grouped tr.revenue-summary-category-divider td {
    border-bottom-style: dotted !important;
}
.revenue-target-summary-card {
    overflow: hidden;
}
.revenue-target-summary-header {
    background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), 0.08), rgba(var(--bs-info-rgb), 0.06));
    border-bottom: 1px solid rgba(var(--bs-primary-rgb), 0.15);
}
.revenue-target-dept-badge {
    font-size: 0.85rem;
    font-weight: 600;
}
.revenue-target-panel {
    border: 1px solid rgba(var(--bs-primary-rgb), 0.18);
    border-radius: 0.75rem;
    padding: 1rem 1.1rem;
    background: linear-gradient(180deg, rgba(var(--bs-primary-rgb), 0.04) 0%, transparent 100%);
}
.revenue-target-panel-monthly {
    border-left: 4px solid var(--bs-primary);
}
.revenue-target-panel-cumulative {
    border-left: 4px solid var(--bs-info);
}
.revenue-target-panel-head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.85rem;
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--bs-heading-color);
}
.revenue-target-panel-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 0.45rem;
    background: rgba(var(--bs-primary-rgb), 0.12);
    color: var(--bs-primary);
}
.revenue-target-panel-cumulative .revenue-target-panel-icon {
    background: rgba(var(--bs-info-rgb), 0.14);
    color: var(--bs-info);
}
.revenue-target-metric {
    height: 100%;
    padding: 0.75rem 0.85rem;
    border-radius: 0.6rem;
    border: 1px solid var(--bs-border-color);
    background: var(--bs-body-bg);
}
.revenue-target-metric-target {
    border-color: rgba(var(--bs-primary-rgb), 0.22);
    background: rgba(var(--bs-primary-rgb), 0.05);
}
.revenue-target-metric-actual {
    border-color: rgba(var(--bs-success-rgb), 0.22);
    background: rgba(var(--bs-success-rgb), 0.05);
}
.revenue-target-metric-label {
    display: block;
    font-size: 0.78rem;
    color: var(--bs-secondary-color);
    margin-bottom: 0.35rem;
}
.revenue-target-metric-value {
    display: block;
    font-size: 1.15rem;
    font-weight: 700;
    line-height: 1.3;
    word-break: break-all;
}
.revenue-target-rate {
    margin-top: 0.9rem;
    padding-top: 0.85rem;
    border-top: 1px dashed var(--bs-border-color);
}
.revenue-target-rate-label {
    font-size: 0.82rem;
    color: var(--bs-secondary-color);
}
.revenue-target-rate-value {
    font-size: 1rem;
}
.revenue-target-progress {
    height: 0.65rem;
    border-radius: 999px;
    background-color: rgba(var(--bs-secondary-rgb), 0.15);
}
.revenue-target-progress .progress-bar {
    border-radius: 999px;
    transition: width 0.35s ease;
}
[data-bs-theme="dark"] .revenue-target-summary-header,
html.dark-style .revenue-target-summary-header,
body.dark-style .revenue-target-summary-header {
    background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), 0.18), rgba(var(--bs-info-rgb), 0.1));
}
[data-bs-theme="dark"] .revenue-target-panel,
html.dark-style .revenue-target-panel,
body.dark-style .revenue-target-panel {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.03) 0%, transparent 100%);
}
[data-bs-theme="dark"] .revenue-stats-table-summary-grouped .revenue-summary-section-label,
html.dark-style .revenue-stats-table-summary-grouped .revenue-summary-section-label {
    background-color: #2f6fad;
    color: #fff;
}
[data-bs-theme="dark"] .revenue-stats-table-summary-grouped .revenue-summary-category-label,
html.dark-style .revenue-stats-table-summary-grouped .revenue-summary-category-label {
    background-color: #3d5f80;
    color: #fff;
}
[data-bs-theme="dark"] .revenue-stats-table-summary-grouped .revenue-summary-unbilled-label,
html.dark-style .revenue-stats-table-summary-grouped .revenue-summary-unbilled-label {
    background-color: #5a5a5a;
    color: #fff;
}
.revenue-stats-table-invoiced,
.revenue-stats-table-estimated,
.revenue-stats-table-backlog,
.revenue-stats-table-cancelled {
    width: 100%;
    min-width: 72rem;
}
.revenue-stats-table th,
.revenue-stats-table td {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    vertical-align: middle;
}
.revenue-stats-table-summary .revenue-stats-cell-company {
    max-width: 0;
}
.revenue-stats-table .revenue-stats-cell-name a {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
/* Theme-based totals row colors */
[data-bs-theme="light"] .revenue-stats-table tfoot.table-secondary td,
html.light-style .revenue-stats-table tfoot.table-secondary td,
body.light-style .revenue-stats-table tfoot.table-secondary td {
    background-color: #333 !important;
    color: #fff !important;
}
[data-bs-theme="dark"] .revenue-stats-table tfoot.table-secondary td,
html.dark-style .revenue-stats-table tfoot.table-secondary td,
body.dark-style .revenue-stats-table tfoot.table-secondary td {
    background-color: #fff !important;
    color: #000 !important;
}
.table-narrow td,
.table-narrow th{
    padding: 0.5rem 0.2rem;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="../assets/vendor/libs/jszip/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js" crossorigin="anonymous"></script>
<script src="assets/js/revenue-statistics.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
