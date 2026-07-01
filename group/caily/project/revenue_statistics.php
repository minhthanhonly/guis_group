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
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h4 class="mb-0">
                    <i class="fa fa-yen-sign me-2"></i><span data-i18n="月次売上統計">月次売上統計</span>
                </h4>
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label mb-0 text-nowrap" data-i18n="対象月">対象月</label>
                    <select class="form-select form-select-sm" style="width: 10rem;" v-model="selectedMonth" @change="onMonthChange">
                        <option v-for="m in availableMonths" :key="m.month" :value="m.month">{{ m.month_label || m.month }}</option>
                    </select>
                    <button class="btn btn-sm btn-outline-secondary" type="button" @click="loadStats" :disabled="loading || !selectedDepartment">
                        <i class="fa fa-refresh me-1"></i><span data-i18n="更新">更新</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg bg-dark mb-3">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#revenueDeptNav" aria-controls="revenueDeptNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-start" id="revenueDeptNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item" v-for="department in departments" :key="department.id"
                        :class="{ 'active bg-primary text-white rounded-3': selectedDepartment && selectedDepartment.id === department.id }">
                        <a href="#" class="nav-link" @click.prevent="selectDepartment(department)">{{ department.name }}</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div v-if="!departments.length && !loadingDepartments" class="alert alert-warning" data-i18n="表示できる部署がありません。">
        表示できる部署がありません。
    </div>

    <template v-if="selectedDepartment">
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link" :class="{ active: activeSubTab === 'summary' }" type="button" @click="activeSubTab = 'summary'">
                    <i class="fa fa-table me-1"></i><span data-i18n="サマリー">サマリー</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" :class="{ active: activeSubTab === 'detail' }" type="button" @click="activeSubTab = 'detail'">
                    <i class="fa fa-list me-1"></i><span data-i18n="詳細">詳細</span>
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
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        {{ selectedDepartment.name }} — <span data-i18n="会社別サマリー">会社別サマリー</span>（{{ monthLabel }}）
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0 revenue-stats-table revenue-stats-table-summary">
                            <colgroup>
                                <col style="width: 24%;">
                                <col style="width: 8%;">
                                <col style="width: 8%;">
                                <col style="width: 14%;">
                                <col style="width: 8%;">
                                <col style="width: 14%;">
                                <col style="width: 8%;">
                                <col style="width: 16%;">
                            </colgroup>
                            <thead class="table-light">
                                <tr>
                                    <th data-i18n="会社">会社</th>
                                    <th class="text-end" data-i18n="案件数">案件数</th>
                                    <th class="text-end" data-i18n="見積件数">見積件数</th>
                                    <th class="text-end" data-i18n="見積金額">見積金額</th>
                                    <th class="text-end" data-i18n="請求件数">請求件数</th>
                                    <th class="text-end" data-i18n="請求金額">請求金額</th>
                                    <th class="text-end" data-i18n="入金件数">入金件数</th>
                                    <th class="text-end" data-i18n="入金金額">入金金額</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!summaryByCompany.length">
                                    <td colspan="8" class="text-center text-muted py-4" data-i18n="データがありません">データがありません</td>
                                </tr>
                                <tr v-for="row in summaryByCompany" :key="row.company_name">
                                    <td class="revenue-stats-cell-company" :title="row.company_name">
                                        <span v-html="formatCompanyNameLabel(row.company_name)"></span>
                                        <span class="ms-1">{{ row.company_name }}</span>
                                    </td>
                                    <td class="text-end">{{ formatCount(row.project_count) }}</td>
                                    <td class="text-end">{{ formatCount(row.estimate_count) }}</td>
                                    <td class="text-end">{{ formatCurrency(row.estimate_amount) }}</td>
                                    <td class="text-end">{{ formatCount(row.invoice_count) }}</td>
                                    <td class="text-end">{{ formatCurrency(row.invoice_amount) }}</td>
                                    <td class="text-end">{{ formatCount(row.payment_count) }}</td>
                                    <td class="text-end">{{ formatCurrency(row.payment_amount) }}</td>
                                </tr>
                            </tbody>
                            <tfoot v-if="summaryByCompany.length" class="table-secondary fw-semibold">
                                <tr>
                                    <td data-i18n="合計">合計</td>
                                    <td class="text-end">{{ formatCount(summaryTotals.project_count) }}</td>
                                    <td class="text-end">{{ formatCount(summaryTotals.estimate_count) }}</td>
                                    <td class="text-end">{{ formatCurrency(summaryTotals.estimate_amount) }}</td>
                                    <td class="text-end">{{ formatCount(summaryTotals.invoice_count) }}</td>
                                    <td class="text-end">{{ formatCurrency(summaryTotals.invoice_amount) }}</td>
                                    <td class="text-end">{{ formatCount(summaryTotals.payment_count) }}</td>
                                    <td class="text-end">{{ formatCurrency(summaryTotals.payment_amount) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12" v-for="block in summaryTantouBlocks" :key="block.tantou">
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
                                        <col style="width: 24%;">
                                        <col style="width: 8%;">
                                        <col style="width: 8%;">
                                        <col style="width: 14%;">
                                        <col style="width: 8%;">
                                        <col style="width: 14%;">
                                        <col style="width: 8%;">
                                        <col style="width: 16%;">
                                    </colgroup>
                                    <thead class="table-light">
                                        <tr>
                                            <th data-i18n="会社">会社</th>
                                            <th class="text-end" data-i18n="案件数">案件数</th>
                                            <th class="text-end" data-i18n="見積件数">見積件数</th>
                                            <th class="text-end" data-i18n="見積金額">見積金額</th>
                                            <th class="text-end" data-i18n="請求件数">請求件数</th>
                                            <th class="text-end" data-i18n="請求金額">請求金額</th>
                                            <th class="text-end" data-i18n="入金件数">入金件数</th>
                                            <th class="text-end" data-i18n="入金金額">入金金額</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!block.rows.length">
                                            <td colspan="8" class="text-center text-muted py-4" data-i18n="データがありません">データがありません</td>
                                        </tr>
                                        <tr v-for="row in block.rows" :key="block.tantou + '-' + row.company_name">
                                            <td class="revenue-stats-cell-company" :title="row.company_name">
                                                <span v-html="formatCompanyNameLabel(row.company_name)"></span>
                                                <span class="ms-1">{{ row.company_name }}</span>
                                            </td>
                                            <td class="text-end">{{ formatCount(row.project_count) }}</td>
                                            <td class="text-end">{{ formatCount(row.estimate_count) }}</td>
                                            <td class="text-end">{{ formatCurrency(row.estimate_amount) }}</td>
                                            <td class="text-end">{{ formatCount(row.invoice_count) }}</td>
                                            <td class="text-end">{{ formatCurrency(row.invoice_amount) }}</td>
                                            <td class="text-end">{{ formatCount(row.payment_count) }}</td>
                                            <td class="text-end">{{ formatCurrency(row.payment_amount) }}</td>
                                        </tr>
                                    </tbody>
                                    <tfoot v-if="block.rows.length" class="table-secondary fw-semibold">
                                        <tr>
                                            <td data-i18n="合計">合計</td>
                                            <td class="text-end">{{ formatCount(block.totals.project_count) }}</td>
                                            <td class="text-end">{{ formatCount(block.totals.estimate_count) }}</td>
                                            <td class="text-end">{{ formatCurrency(block.totals.estimate_amount) }}</td>
                                            <td class="text-end">{{ formatCount(block.totals.invoice_count) }}</td>
                                            <td class="text-end">{{ formatCurrency(block.totals.invoice_amount) }}</td>
                                            <td class="text-end">{{ formatCount(block.totals.payment_count) }}</td>
                                            <td class="text-end">{{ formatCurrency(block.totals.payment_amount) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else>
            

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><span data-i18n="請求済み案件">請求済み案件</span>（{{ monthLabel }}）</h5>
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
                            <table class="table table-sm table-bordered mb-0 revenue-stats-table revenue-stats-table-invoiced">
                                <colgroup>
                                    <col style="width: 4%;">
                                    <col style="width: 11%;">
                                    <col style="width: 7%;">
                                    <col style="width: 5%;">
                                    <col style="width: 6%;">
                                    <col style="width: 6%;">
                                    <col style="width: 7%;">
                                    <col style="width: 7%;">
                                    <col style="width: 8%;">
                                    <col style="width: 7%;">
                                    <col style="width: 8%;">
                                    <col style="width: 7%;">
                                    <col style="width: 8%;">
                                    <col style="width: 7%;">
                                </colgroup>
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th data-i18n="案件名">案件名</th>
                                        <th data-i18n="支店名">支店名</th>
                                        <th data-i18n="担当">担当</th>
                                        <th data-i18n="受注形態">受注形態</th>
                                        <th data-i18n="規模">規模</th>
                                        <th data-i18n="納期">納期</th>
                                        <th data-i18n="チーム">チーム</th>
                                        <th class="text-end" data-i18n="総額">総額</th>
                                        <th data-i18n="請求日">請求日</th>
                                        <th class="text-end" data-i18n="請求金額">請求金額</th>
                                        <th data-i18n="入金日">入金日</th>
                                        <th class="text-end" data-i18n="入金金額">入金金額</th>
                                        <th data-i18n="入金状況">入金状況</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="p in group.projects" :key="p.id">
                                        <td>{{ p.id }}</td>
                                        <td class="revenue-stats-cell-name" :title="p.name"><a :href="'detail.php?id=' + p.id" target="_blank">{{ p.name }}</a></td>
                                        <td class="revenue-stats-cell-text" :title="p.branch_name">{{ p.branch_name || '—' }}</td>
                                        <td>{{ p.tantou || '—' }}</td>
                                        <td>{{ p.project_order_type || '—' }}</td>
                                        <td class="revenue-stats-cell-text" :title="p.parent_scale">{{ p.parent_scale || '—' }}</td>
                                        <td>{{ formatProjectNouki(p) }}</td>
                                        <td class="revenue-stats-cell-text" :title="formatProjectTeams(p)">{{ formatProjectTeams(p) }}</td>
                                        <td class="text-end">{{ formatCurrency(p.amount) }}</td>
                                        <td>{{ formatDate(p.invoice_date) }}</td>
                                        <td class="text-end">{{ formatCurrency(p.invoice_amount) }}</td>
                                        <td>{{ formatDate(p.payment_date) }}</td>
                                        <td class="text-end">{{ formatCurrency(p.payment_amount) }}</td>
                                        <td>{{ p.payment_status || '—' }}</td>
                                    </tr>
                                </tbody>
                                <tfoot v-if="group.projects.length" class="table-secondary fw-semibold">
                                    <tr>
                                        <td colspan="8" data-i18n="合計">合計</td>
                                        <td class="text-end">{{ formatCurrency(group.totals.amount) }}</td>
                                        <td></td>
                                        <td class="text-end">{{ formatCurrency(group.totals.invoice_amount) }}</td>
                                        <td></td>
                                        <td class="text-end">{{ formatCurrency(group.totals.payment_amount) }}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><span data-i18n="完了・未請求案件">完了・未請求案件</span>（<span data-i18n="バックログ">バックログ</span>）</h5>
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
                            <table class="table table-sm table-bordered mb-0 revenue-stats-table revenue-stats-table-backlog">
                                <colgroup>
                                    <col style="width: 4%;">
                                    <col style="width: 12%;">
                                    <col style="width: 8%;">
                                    <col style="width: 5%;">
                                    <col style="width: 6%;">
                                    <col style="width: 7%;">
                                    <col style="width: 8%;">
                                    <col style="width: 8%;">
                                    <col style="width: 9%;">
                                    <col style="width: 9%;">
                                    <col style="width: 9%;">
                                </colgroup>
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th data-i18n="案件名">案件名</th>
                                        <th data-i18n="支店名">支店名</th>
                                        <th data-i18n="担当">担当</th>
                                        <th data-i18n="受注形態">受注形態</th>
                                        <th data-i18n="規模">規模</th>
                                        <th data-i18n="納期">納期</th>
                                        <th data-i18n="チーム">チーム</th>
                                        <th class="text-end" data-i18n="総額">総額</th>
                                        <th data-i18n="完了日">完了日</th>
                                        <th data-i18n="期限日">期限日</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="p in group.projects" :key="p.id">
                                        <td>{{ p.id }}</td>
                                        <td class="revenue-stats-cell-name" :title="p.name"><a :href="'detail.php?id=' + p.id" target="_blank">{{ p.name }}</a></td>
                                        <td class="revenue-stats-cell-text" :title="p.branch_name">{{ p.branch_name || '—' }}</td>
                                        <td>{{ p.tantou || '—' }}</td>
                                        <td>{{ p.project_order_type || '—' }}</td>
                                        <td class="revenue-stats-cell-text" :title="p.parent_scale">{{ p.parent_scale || '—' }}</td>
                                        <td>{{ formatProjectNouki(p) }}</td>
                                        <td class="revenue-stats-cell-text" :title="formatProjectTeams(p)">{{ formatProjectTeams(p) }}</td>
                                        <td class="text-end">{{ formatCurrency(p.amount) }}</td>
                                        <td>{{ formatDate(p.actual_end_date) }}</td>
                                        <td>{{ formatDate(p.end_date) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot v-if="group.projects.length" class="table-secondary fw-semibold">
                                    <tr>
                                        <td colspan="8" data-i18n="合計">合計</td>
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

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><span data-i18n="見積案件">見積案件</span>（{{ monthLabel }}）</h5>
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
                            <table class="table table-sm table-bordered mb-0 revenue-stats-table revenue-stats-table-estimated">
                                <colgroup>
                                    <col style="width: 4%;">
                                    <col style="width: 12%;">
                                    <col style="width: 8%;">
                                    <col style="width: 5%;">
                                    <col style="width: 6%;">
                                    <col style="width: 7%;">
                                    <col style="width: 8%;">
                                    <col style="width: 8%;">
                                    <col style="width: 9%;">
                                    <col style="width: 9%;">
                                    <col style="width: 9%;">
                                </colgroup>
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th data-i18n="案件名">案件名</th>
                                        <th data-i18n="支店名">支店名</th>
                                        <th data-i18n="担当">担当</th>
                                        <th data-i18n="受注形態">受注形態</th>
                                        <th data-i18n="規模">規模</th>
                                        <th data-i18n="納期">納期</th>
                                        <th data-i18n="チーム">チーム</th>
                                        <th class="text-end" data-i18n="総額">総額</th>
                                        <th data-i18n="見積日">見積日</th>
                                        <th data-i18n="見積状況">見積状況</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="p in group.projects" :key="p.id">
                                        <td>{{ p.id }}</td>
                                        <td class="revenue-stats-cell-name" :title="p.name"><a :href="'detail.php?id=' + p.id" target="_blank">{{ p.name }}</a></td>
                                        <td class="revenue-stats-cell-text" :title="p.branch_name">{{ p.branch_name || '—' }}</td>
                                        <td>{{ p.tantou || '—' }}</td>
                                        <td>{{ p.project_order_type || '—' }}</td>
                                        <td class="revenue-stats-cell-text" :title="p.parent_scale">{{ p.parent_scale || '—' }}</td>
                                        <td>{{ formatProjectNouki(p) }}</td>
                                        <td class="revenue-stats-cell-text" :title="formatProjectTeams(p)">{{ formatProjectTeams(p) }}</td>
                                        <td class="text-end">{{ formatCurrency(p.amount) }}</td>
                                        <td>{{ formatDate(p.estimate_date) }}</td>
                                        <td>{{ p.estimate_status || '—' }}</td>
                                    </tr>
                                </tbody>
                                <tfoot v-if="group.projects.length" class="table-secondary fw-semibold">
                                    <tr>
                                        <td colspan="8" data-i18n="合計">合計</td>
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
    </template>
</div>

<?php
$view->footing();
?>

<style>
.revenue-stats-table {
    table-layout: fixed;
    width: 100%;
}
.revenue-stats-table-summary {
    min-width: 56rem;
}
.revenue-stats-table-invoiced,
.revenue-stats-table-estimated,
.revenue-stats-table-backlog {
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
</style>

<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="assets/js/revenue-statistics.js?v=<?=CACHE_VERSION?>"></script>
