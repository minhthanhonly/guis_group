<?php

require_once('../application/loader.php');
$view->heading('入金管理');

if ($_SESSION['show_project'] == 0) {
    echo '<div class="container-fluid mt-4"><div class="alert alert-danger">権限がありません。</div></div>';
    exit;
}

$permModel = new ApplicationModel();
$canAccess = ($_SESSION['authority'] ?? '') === 'administrator'
    || $permModel->hasDepartmentPermission('project_director_stat');

if (!$canAccess) {
    echo '<div class="container-fluid mt-4"><div class="alert alert-danger">権限がありません。</div></div>';
    exit;
}
?>

<div id="app" class="container-fluid mt-4 mb-5" v-cloak>
    <div class="row mb-3">
        <div class="col-12">
            <h4 class="mb-0">
                <i class="fa fa-file-lines me-2"></i><span data-i18n="入金管理">入金管理</span>
            </h4>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-3">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#invoicedDeptNav" aria-controls="invoicedDeptNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-between" id="invoicedDeptNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item" v-for="department in departments" :key="department.id"
                        :class="{ 'active bg-primary text-white rounded-3': selectedDepartment && selectedDepartment.id === department.id }">
                        <a href="#" class="nav-link" @click.prevent="selectDepartment(department)">{{ department.name }}</a>
                    </li>
                </ul>
                <div class="invoiced-dept-nav-actions d-flex align-items-center gap-2 ms-lg-auto mb-2 mb-lg-0">
                    <button class="btn btn-sm invoiced-nav-btn" type="button" @click="reloadActiveTab" :disabled="loading || !selectedDepartment">
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
                <button class="nav-link" :class="{ active: activeSubTab === 'invoiced' }" type="button" @click="switchSubTab('invoiced')">
                    <i class="fa fa-file-lines me-1"></i><span data-i18n="請求済案件">請求済案件</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" :class="{ active: activeSubTab === 'monthly_payment' }" type="button" @click="switchSubTab('monthly_payment')">
                    <i class="fa fa-money-bill-wave me-1"></i><span data-i18n="月次入金統計">月次入金統計</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" :class="{ active: activeSubTab === 'fiscal_payment' }" type="button" @click="switchSubTab('fiscal_payment')">
                    <i class="fa fa-line-chart me-1"></i><span data-i18n="年度入金統計">年度入金統計</span>
                </button>
            </li>
        </ul>

        <template v-if="activeSubTab === 'invoiced'">
        <div class="card mb-3">
            <div class="card-header py-2">
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#invoicedFilterBox" aria-expanded="true">
                    <i class="fa fa-filter me-1"></i><span data-i18n="フィルター">フィルター</span>
                </button>
            </div>
            <div class="collapse show" id="invoicedFilterBox">
                <div class="card-body pb-3 pt-3">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3 col-6">
                            <label class="form-label form-label-sm mb-1" data-i18n="請求月">請求月</label>
                            <div class="input-group input-group-sm invoiced-month-filter">
                                <select class="form-select" v-model="filters.invoiceMonth">
                                    <option value="" data-i18n="すべての期間">すべての期間</option>
                                    <option v-for="m in availableMonths" :key="'inv-' + m.month" :value="m.month">{{ m.month_label || m.month }}</option>
                                </select>
                                <button class="btn btn-outline-secondary" type="button" @click="setInvoiceMonthThisMonth" :disabled="loading || filters.invoiceMonth === currentYearMonthValue">
                                    <span data-i18n="今月">今月</span>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label form-label-sm mb-1" data-i18n="入金月">入金月</label>
                            <div class="input-group input-group-sm invoiced-month-filter">
                                <select class="form-select" v-model="filters.paymentMonth">
                                    <option value="" data-i18n="すべての期間">すべての期間</option>
                                    <option v-for="m in availableMonths" :key="'pay-' + m.month" :value="m.month">{{ m.month_label || m.month }}</option>
                                </select>
                                <button class="btn btn-outline-secondary" type="button" @click="setPaymentMonthThisMonth" :disabled="loading || filters.paymentMonth === currentYearMonthValue">
                                    <span data-i18n="今月">今月</span>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label form-label-sm mb-1" data-i18n="入金状況">入金状況</label>
                            <select class="form-select form-select-sm" v-model="filters.paymentStatus">
                                <option value="" data-i18n="すべて">すべて</option>
                                <option v-for="opt in paymentStatusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label form-label-sm mb-1" data-i18n="担当">担当</label>
                            <select class="form-select form-select-sm" v-model="filters.tantou">
                                <option value="" data-i18n="すべて">すべて</option>
                                <option v-for="opt in tantouOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label form-label-sm mb-1" data-i18n="請求番号">請求番号</label>
                            <input type="text" class="form-control form-control-sm" v-model="filters.invoiceNumber" :placeholder="filterPlaceholderInvoiceNumber">
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label form-label-sm mb-1" data-i18n="領収書番号">領収書番号</label>
                            <input type="text" class="form-control form-control-sm" v-model="filters.receiptNumber" :placeholder="filterPlaceholderReceiptNumber">
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label form-label-sm mb-1" data-i18n="会社名">会社名</label>
                            <input type="text" class="form-control form-control-sm" v-model="filters.company" :placeholder="filterPlaceholderCompany">
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label form-label-sm mb-1" data-i18n="支店名">支店名</label>
                            <input type="text" class="form-control form-control-sm" v-model="filters.branch" :placeholder="filterPlaceholderBranch">
                        </div>
                        <div class="col-12">
                            <label class="form-label form-label-sm mb-1" data-i18n="キーワード">キーワード</label>
                            <input type="text" class="form-control form-control-sm" v-model="filters.keyword" :placeholder="filterPlaceholderKeyword">
                            <div v-if="activeFilterBadges.length" class="invoiced-active-filters mt-2 d-flex flex-wrap align-items-center gap-2">
                                <span class="text-muted small">
                                    <span data-i18n="適用中のフィルター">適用中のフィルター</span>:
                                </span>
                                <span v-for="badge in activeFilterBadges" :key="badge.key" class="badge bg-label-info">
                                    {{ badge.label }}: {{ badge.value }}
                                </span>
                                <button class="btn btn-outline-danger btn-sm text-nowrap" type="button" @click="clearFilters" :disabled="loading">
                                    <i class="fa fa-times me-1"></i><span data-i18n="クリア">クリア</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted" data-i18n="読み込み中...">読み込み中...</p>
        </div>

        <div v-else-if="errorMessage" class="alert alert-danger">{{ errorMessage }}</div>

        <template v-else>
            <div class="row g-3 mb-3">
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="invoiced-summary-box invoiced-summary-unpaid-count">
                        <span class="invoiced-summary-label" data-i18n="未入金件数">未入金件数</span>
                        <span class="invoiced-summary-value">{{ formatCount(meta.totals.unpaid_count) }}</span>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="invoiced-summary-box invoiced-summary-unpaid-amount">
                        <span class="invoiced-summary-label" data-i18n="未入金請求金額合計">未入金請求金額合計</span>
                        <span class="invoiced-summary-value">{{ formatCurrency(meta.totals.unpaid_invoice_amount) }}</span>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="invoiced-summary-box invoiced-summary-rejected-count">
                        <span class="invoiced-summary-label" data-i18n="入金拒否件数">入金拒否件数</span>
                        <span class="invoiced-summary-value">{{ formatCount(meta.totals.rejected_count) }}</span>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="invoiced-summary-box invoiced-summary-rejected-amount">
                        <span class="invoiced-summary-label" data-i18n="入金拒否請求金額合計">入金拒否請求金額合計</span>
                        <span class="invoiced-summary-value">{{ formatCurrency(meta.totals.rejected_invoice_amount) }}</span>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="invoiced-summary-box invoiced-summary-payment-count">
                        <span class="invoiced-summary-label" data-i18n="入金件数">入金件数</span>
                        <span class="invoiced-summary-value">{{ formatCount(meta.totals.payment_count) }}</span>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="invoiced-summary-box invoiced-summary-payment">
                        <span class="invoiced-summary-label" data-i18n="入金額合計">入金額合計</span>
                        <span class="invoiced-summary-value">{{ formatCurrency(meta.totals.payment_amount) }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="mb-0">
                        {{ selectedDepartment.name }}
                        <span class="text-muted fw-normal ms-1">（{{ resultRangeLabel }}）</span>
                    </h5>
                    <span class="text-muted small">{{ paginationLabel }}</span>
                </div>
                <div class="card-body p-0">
                    <div v-if="!projects.length" class="text-muted text-center py-4" data-i18n="データがありません">データがありません</div>
                    <div v-else class="table-responsive">
                        <table class="table table-sm table-hover table-bordered mb-0 invoiced-projects-table">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th data-i18n="案件名">案件名</th>
                                    <th data-i18n="会社名">会社名</th>
                                    <th data-i18n="支店名">支店名</th>
                                    <th data-i18n="工事番号">工事番号</th>
                                    <th data-i18n="担当">担当</th>
                                    <th data-i18n="受注形態">受注形態</th>
                                    <th data-i18n="請求日">請求日</th>
                                    <th class="text-end" data-i18n="請求金額">請求金額</th>
                                    <th data-i18n="請求番号">請求番号</th>
                                    <th data-i18n="入金状況">入金状況</th>
                                    <th data-i18n="入金日">入金日</th>
                                    <th class="text-end" data-i18n="入金額">入金額</th>
                                    <th data-i18n="領収書番号">領収書番号</th>
                                    <th data-i18n="決済備考">決済備考</th>
                                    <th class="text-center invoiced-actions-col" data-i18n="操作">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="p in projects" :key="p.id">
                                    <td>{{ p.id }}</td>
                                    <td class="invoiced-cell-name" :title="p.name">
                                        <a :href="'detail.php?id=' + p.id" target="_blank">{{ p.name }}</a>
                                    </td>
                                    <td class="invoiced-cell-text" :title="p.company_name">{{ p.company_name || '—' }}</td>
                                    <td class="invoiced-cell-text" :title="p.branch_name">{{ p.branch_name || '—' }}</td>
                                    <td class="invoiced-cell-text" :title="p.parent_construction_number">{{ p.parent_construction_number || '—' }}</td>
                                    <td>{{ p.tantou || '—' }}</td>
                                    <td>{{ p.project_order_type || '—' }}</td>
                                    <td>{{ formatDate(p.invoice_date) }}</td>
                                    <td class="text-end fw-semibold">{{ formatCurrency(p.invoice_amount) }}</td>
                                    <td class="invoiced-cell-text" :title="p.invoice_number">{{ p.invoice_number || '—' }}</td>
                                    <td>
                                        <span class="badge invoiced-payment-badge" :class="paymentStatusClass(p.payment_status)">{{ p.payment_status || '—' }}</span>
                                    </td>
                                    <td>{{ formatDate(p.payment_date) }}</td>
                                    <td class="text-end">{{ formatCurrency(p.payment_amount) }}</td>
                                    <td class="invoiced-cell-text" :title="p.receipt_number">{{ p.receipt_number || '—' }}</td>
                                    <td class="invoiced-cell-text" :title="p.payment_note">{{ p.payment_note || '—' }}</td>
                                    <td class="text-center invoiced-actions-col">
                                        <button type="button" class="btn btn-sm btn-outline-primary invoiced-payment-edit-btn" :title="paymentEditButtonTitle" @click="openPaymentEditModal(p)">
                                            <i class="fa fa-pen-to-square"></i><span class="visually-hidden" data-i18n="入金編集">入金編集</span>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div v-if="meta.total_pages > 1" class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <nav aria-label="Pagination">
                        <ul class="pagination pagination-sm mb-0 mt-4">
                            <li class="page-item" :class="{ disabled: meta.page <= 1 }">
                                <a class="page-link" href="#" @click.prevent="goToPage(meta.page - 1)">&laquo;</a>
                            </li>
                            <li class="page-item" v-for="pageNum in visiblePages" :key="pageNum" :class="{ active: pageNum === meta.page, disabled: pageNum === '...' }">
                                <a v-if="pageNum !== '...'" class="page-link" href="#" @click.prevent="goToPage(pageNum)">{{ pageNum }}</a>
                                <span v-else class="page-link">...</span>
                            </li>
                            <li class="page-item" :class="{ disabled: meta.page >= meta.total_pages }">
                                <a class="page-link" href="#" @click.prevent="goToPage(meta.page + 1)">&raquo;</a>
                            </li>
                        </ul>
                    </nav>
                    <span class="text-muted small">{{ paginationLabel }}</span>
                </div>
            </div>
        </template>
        </template>

        <template v-else-if="activeSubTab === 'monthly_payment'">
            <div class="card mb-3">
                <div class="card-body py-2">
                    <div class="invoiced-report-month-controls d-flex align-items-center gap-2">
                        <label class="form-label mb-0 text-nowrap small fw-semibold flex-shrink-0" data-i18n="入金月">入金月</label>
                        <div class="invoiced-report-period-picker d-flex align-items-center gap-2 flex-nowrap">
                            <div class="btn-group btn-group-sm invoiced-report-nav-btn-group" role="group" aria-label="Payment month navigation">
                                <button class="btn invoiced-report-nav-btn" type="button" @click="goToPaymentReportPrevMonth" :disabled="loading">
                                    <i class="fa fa-chevron-left"></i><span class="visually-hidden" data-i18n="前月">前月</span>
                                </button>
                                <button class="btn invoiced-report-nav-btn" type="button" @click="goToPaymentReportThisMonth" :disabled="loading || paymentReportMonth === currentYearMonthValue">
                                    <span data-i18n="今月">今月</span>
                                </button>
                                <button class="btn invoiced-report-nav-btn" type="button" @click="goToPaymentReportNextMonth" :disabled="loading">
                                    <i class="fa fa-chevron-right"></i><span class="visually-hidden" data-i18n="次月">次月</span>
                                </button>
                            </div>
                            <select class="form-select form-select-sm invoiced-report-month-select" v-model="paymentReportMonth" @change="onPaymentReportMonthChange">
                                <option v-for="m in availableMonths" :key="'report-' + m.month" :value="m.month">{{ m.month_label || m.month }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="loading" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted" data-i18n="読み込み中...">読み込み中...</p>
            </div>

            <div v-else-if="errorMessage" class="alert alert-danger">{{ errorMessage }}</div>

            <template v-else>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="invoiced-summary-box invoiced-summary-payment-count">
                            <span class="invoiced-summary-label" data-i18n="入金件数">入金件数</span>
                            <span class="invoiced-summary-value">{{ formatCount(paymentReportMeta.totals.payment_count) }}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="invoiced-summary-box invoiced-summary-payment">
                            <span class="invoiced-summary-label" data-i18n="入金額合計">入金額合計</span>
                            <span class="invoiced-summary-value">{{ formatCurrency(paymentReportMeta.totals.payment_amount) }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="!paymentReportGroups.length" class="text-muted text-center py-4" data-i18n="データがありません">データがありません</div>

                <div v-for="group in paymentReportGroups" :key="'pay-group-' + group.month" class="card mb-4">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h5 class="mb-0">{{ group.month_label }}</h5>
                        <div class="text-muted small">
                            <span data-i18n="入金件数">入金件数</span>: {{ formatCount(group.totals.payment_count) }}
                            <span class="ms-2" data-i18n="入金額合計">入金額合計</span>: {{ formatCurrency(group.totals.payment_amount) }}
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover table-bordered mb-0 invoiced-projects-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th data-i18n="案件名">案件名</th>
                                        <th data-i18n="会社名">会社名</th>
                                        <th data-i18n="支店名">支店名</th>
                                        <th data-i18n="工事番号">工事番号</th>
                                        <th data-i18n="担当">担当</th>
                                        <th data-i18n="請求日">請求日</th>
                                        <th class="text-end" data-i18n="請求金額">請求金額</th>
                                        <th data-i18n="入金日">入金日</th>
                                        <th class="text-end" data-i18n="入金額">入金額</th>
                                        <th data-i18n="領収書番号">領収書番号</th>
                                        <th data-i18n="決済備考">決済備考</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="p in group.projects" :key="'pay-' + group.month + '-' + p.id">
                                        <td>{{ p.id }}</td>
                                        <td class="invoiced-cell-name" :title="p.name">
                                            <a :href="'detail.php?id=' + p.id" target="_blank">{{ p.name }}</a>
                                        </td>
                                        <td class="invoiced-cell-text" :title="p.company_name">{{ p.company_name || '—' }}</td>
                                        <td class="invoiced-cell-text" :title="p.branch_name">{{ p.branch_name || '—' }}</td>
                                        <td class="invoiced-cell-text" :title="p.parent_construction_number">{{ p.parent_construction_number || '—' }}</td>
                                        <td>{{ p.tantou || '—' }}</td>
                                        <td>{{ formatDate(p.invoice_date) }}</td>
                                        <td class="text-end">{{ formatCurrency(p.invoice_amount) }}</td>
                                        <td>{{ formatDate(p.payment_date) }}</td>
                                        <td class="text-end fw-semibold">{{ formatCurrency(p.payment_amount) }}</td>
                                        <td class="invoiced-cell-text" :title="p.receipt_number">{{ p.receipt_number || '—' }}</td>
                                        <td class="invoiced-cell-text" :title="p.payment_note">{{ p.payment_note || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>
        </template>

        <template v-else-if="activeSubTab === 'fiscal_payment'">
            <div class="card mb-3">
                <div class="card-body py-2">
                    <div class="invoiced-report-month-controls d-flex align-items-center gap-2">
                        <label class="form-label mb-0 text-nowrap small fw-semibold flex-shrink-0" data-i18n="年度">年度</label>
                        <div class="invoiced-report-period-picker d-flex align-items-center gap-2 flex-nowrap">
                            <div class="btn-group btn-group-sm invoiced-report-nav-btn-group" role="group" aria-label="Fiscal year navigation">
                                <button class="btn invoiced-report-nav-btn" type="button" @click="goToFiscalYearPrev" :disabled="loading || selectedFiscalYear <= minFiscalYearValue">
                                    <i class="fa fa-chevron-left"></i><span class="visually-hidden" data-i18n="前年度">前年度</span>
                                </button>
                                <button class="btn invoiced-report-nav-btn" type="button" @click="goToFiscalYearCurrent" :disabled="loading || selectedFiscalYear === currentFiscalYearValue">
                                    <span data-i18n="当年度">当年度</span>
                                </button>
                                <button class="btn invoiced-report-nav-btn" type="button" @click="goToFiscalYearNext" :disabled="loading">
                                    <i class="fa fa-chevron-right"></i><span class="visually-hidden" data-i18n="次年度">次年度</span>
                                </button>
                            </div>
                            <select class="form-select form-select-sm invoiced-fiscal-year-select" v-model.number="selectedFiscalYear" @change="onFiscalYearChange">
                                <option v-for="opt in availableFiscalYears" :key="'fy-' + opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="loading" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted" data-i18n="読み込み中...">読み込み中...</p>
            </div>

            <div v-else-if="errorMessage" class="alert alert-danger">{{ errorMessage }}</div>

            <template v-else>
                <div class="card mb-3 invoiced-fiscal-summary-card">
                    <div class="card-header invoiced-fiscal-summary-header">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <h5 class="mb-0">
                                <i class="fa fa-bullseye me-2 text-primary"></i>
                                <span data-i18n="目標と実績">目標と実績</span>
                                <span class="text-muted fw-normal ms-1">（{{ fiscalPaymentStats.fiscal_year_label }}）</span>
                            </h5>
                            <span class="badge bg-primary">{{ selectedDepartment.name }}</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mt-4">
                            <div class="col-md-4">
                                <div class="invoiced-summary-box invoiced-summary-payment-count">
                                    <span class="invoiced-summary-label" data-i18n="入金件数">入金件数</span>
                                    <span class="invoiced-summary-value">{{ formatCount(fiscalPaymentStats.payment_count) }}</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="invoiced-summary-box invoiced-summary-payment">
                                    <span class="invoiced-summary-label" data-i18n="年間入金額">年間入金額</span>
                                    <span class="invoiced-summary-value">{{ formatCurrency(fiscalPaymentStats.payment_amount) }}</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="invoiced-summary-box invoiced-summary-target">
                                    <span class="invoiced-summary-label" data-i18n="部署年間目標売上">部署年間目標売上</span>
                                    <span class="invoiced-summary-value">{{ formatCurrency(fiscalPaymentStats.yearly_target) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="invoiced-fiscal-rate mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="invoiced-fiscal-rate-label" data-i18n="達成率">達成率</span>
                                <span class="invoiced-fiscal-rate-value fw-bold" :class="achievementRateTextClass(fiscalAchievementRate)">{{ formatPercent(fiscalAchievementRate) }}</span>
                            </div>
                            <div class="progress invoiced-fiscal-progress">
                                <div class="progress-bar" :class="achievementRateBarClass(fiscalAchievementRate)" :style="{ width: achievementBarWidth(fiscalAchievementRate) }" role="progressbar"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </template>
    </template>

    <!-- Payment Edit Modal -->
    <div class="modal fade" id="invoicedPaymentEditModal" tabindex="-1" aria-labelledby="invoicedPaymentEditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content" v-if="paymentEditProject">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h5 class="modal-title mb-0" id="invoicedPaymentEditModalLabel">
                            <span data-i18n="入金編集">入金編集</span>
                            <span class="text-muted small ms-2">#{{ paymentEditProjectId }} {{ paymentEditProject.name }}</span>
                        </h5>
                        <span v-if="paymentEditSaveStatus === 'loading'" class="text-muted" title="保存中">
                            <i class="fa fa-spinner fa-spin"></i>
                        </span>
                        <span v-else-if="paymentEditSaveStatus === 'saved'" class="text-success" title="保存済み">
                            <i class="fa fa-circle-check"></i>
                        </span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" @click="closePaymentEditModal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" v-model.number="paymentEditProject.version">
                    <div class="alert alert-light border mb-3 py-2 small">
                        <span class="text-muted" data-i18n="請求金額">請求金額</span>:
                        <strong>{{ formatCurrency(paymentEditProject.invoice_amount) }}</strong>
                        <span v-if="paymentEditProject.invoice_number" class="text-muted ms-2">（{{ paymentEditProject.invoice_number }}）</span>
                    </div>
                    <h6 class="text-muted mb-3"><span data-i18n="入金">入金</span></h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label mb-0"><span data-i18n="入金日">入金日</span> <span class="text-danger">*</span></label>
                                <button v-if="!hasPaymentEditDate()" type="button" class="btn btn-outline-primary btn-sm py-0 px-2" @click="setPaymentEditDateToday">
                                    <span data-i18n="今日">今日</span>
                                </button>
                            </div>
                            <input type="text" class="form-control" v-model="paymentEditProject.payment_date"
                                   id="invoiced_payment_date_picker" :placeholder="paymentDatePlaceholder" autocomplete="off"
                                   @change="schedulePaymentEditSave">
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label mb-0"><span data-i18n="入金額">入金額</span> <span class="text-danger">*</span></label>
                                <button v-if="!hasPaymentEditAmount()" type="button" class="btn btn-outline-primary btn-sm py-0 px-2" @click="copyInvoiceAmountToPayment">
                                    <span data-i18n="請求と同額">請求と同額</span>
                                </button>
                            </div>
                            <input type="number" class="form-control" v-model.number="paymentEditProject.payment_amount"
                                   @input="schedulePaymentEditSave" min="0" step="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" data-i18n="領収書番号">領収書番号 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" v-model="paymentEditProject.receipt_number" @change="schedulePaymentEditSave">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label d-block mb-1" data-i18n="入金状況">入金状況</label>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm dropdown-toggle"
                                        :class="getPaymentEditStatusButtonClass(paymentEditProject.payment_status)"
                                        id="invoicedPaymentStatusDropdown"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    {{ getPaymentEditStatusLabel(paymentEditProject.payment_status) }}
                                </button>
                                <ul class="dropdown-menu">
                                    <li v-for="status in paymentEditStatuses" :key="status.value">
                                        <a class="dropdown-item"
                                           href="javascript:void(0);"
                                           :class="{ disabled: status.value === '入金済' && !isPaymentEditPaidFieldsComplete() }"
                                           @click="selectPaymentEditStatus(status.value)">
                                            {{ status.label }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div v-if="!isPaymentEditPaidFieldsComplete()" class="form-text text-muted" data-i18n="入金済にするには入金日・入金額・領収書番号が必要です">
                                入金済にするには入金日・入金額・領収書番号が必要です
                            </div>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" data-i18n="決済備考">決済備考</label>
                        <textarea class="form-control" rows="3" v-model="paymentEditProject.payment_note" @change="schedulePaymentEditSave"></textarea>
                    </div>
                    <div v-if="paymentEditError" class="alert alert-danger mt-3 mb-0">{{ paymentEditError }}</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" @click="closePaymentEditModal" data-i18n="閉じる">閉じる</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$view->footing();
?>

<style>
.invoiced-dept-nav-actions .invoiced-nav-btn {
    background-color: #fff;
    color: #2f2b3d;
    border: 1px solid rgba(255, 255, 255, 0.45);
    font-weight: 600;
}
.invoiced-dept-nav-actions .invoiced-nav-btn:hover:not(:disabled) {
    background-color: #ececf1;
    color: #1e1e2d;
}
.invoiced-summary-box {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.85rem 1rem;
    border-radius: 0.65rem;
    border: 1px solid var(--bs-border-color);
    height: 100%;
}
.invoiced-summary-label {
    font-size: 0.78rem;
    color: var(--bs-primary-color);
    font-weight: 600;
}
.invoiced-summary-value {
    font-size: 1.2rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}
.invoiced-summary-unpaid-count {
    border-color: rgba(var(--bs-danger-rgb), 0.35);
    background: rgba(var(--bs-danger-rgb), 0.06);
}
.invoiced-summary-unpaid-count .invoiced-summary-value {
    color: var(--bs-danger);
}
.invoiced-summary-unpaid-amount {
    border-color: rgba(var(--bs-warning-rgb), 0.35);
    background: rgba(var(--bs-warning-rgb), 0.08);
}
.invoiced-summary-unpaid-amount .invoiced-summary-value {
    color: var(--bs-warning-text-emphasis, #b78103);
}
.invoiced-summary-rejected-count {
    border-color: rgba(var(--bs-secondary-rgb), 0.4);
    background: rgba(var(--bs-secondary-rgb), 0.08);
}
.invoiced-summary-rejected-count .invoiced-summary-value {
    color: var(--bs-secondary);
}
.invoiced-summary-rejected-amount {
    border-color: rgba(108, 117, 125, 0.45);
    background: rgba(108, 117, 125, 0.1);
}
.invoiced-summary-rejected-amount .invoiced-summary-value {
    color: #5c636a;
}
.invoiced-summary-invoice {
    border-color: rgba(var(--bs-success-rgb), 0.35);
    background: rgba(var(--bs-success-rgb), 0.06);
}
.invoiced-summary-invoice .invoiced-summary-value {
    color: var(--bs-success);
}
.invoiced-summary-payment {
    border-color: rgba(var(--bs-info-rgb), 0.35);
    background: rgba(var(--bs-info-rgb), 0.06);
}
.invoiced-summary-payment .invoiced-summary-value {
    color: var(--bs-info);
}
.invoiced-summary-payment-count {
    border-color: rgba(var(--bs-primary-rgb), 0.35);
    background: rgba(var(--bs-primary-rgb), 0.06);
}
.invoiced-summary-payment-count .invoiced-summary-value {
    color: var(--bs-primary);
}
.invoiced-summary-target {
    border-color: rgba(var(--bs-warning-rgb), 0.35);
    background: rgba(var(--bs-warning-rgb), 0.08);
}
.invoiced-summary-target .invoiced-summary-value {
    color: var(--bs-warning-text-emphasis, #b78103);
}
.invoiced-fiscal-year-select {
    width: auto;
    min-width: 14rem;
    flex-shrink: 0;
}
.invoiced-fiscal-summary-card {
    border-color: rgba(var(--bs-primary-rgb), 0.2);
}
.invoiced-fiscal-summary-header {
    background: rgba(var(--bs-primary-rgb), 0.05);
    border-bottom: 1px solid rgba(var(--bs-primary-rgb), 0.15);
}
.invoiced-fiscal-rate {
    padding: 0.75rem 1rem;
    border-radius: 0.65rem;
    border: 1px solid var(--bs-border-color);
    background: rgba(var(--bs-body-color-rgb), 0.02);
}
.invoiced-fiscal-rate-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--bs-primary-color);
}
.invoiced-fiscal-progress {
    height: 0.65rem;
    border-radius: 999px;
}
.invoiced-fiscal-progress .progress-bar {
    border-radius: 999px;
}
.invoiced-projects-table {
    min-width: 88rem;
}
.invoiced-projects-table th,
.invoiced-projects-table td {
    vertical-align: middle;
    white-space: nowrap;
}
.invoiced-cell-name {
    max-width: 14rem;
    white-space: normal !important;
}
.invoiced-cell-name a {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.invoiced-cell-text {
    max-width: 10rem;
    overflow: hidden;
    text-overflow: ellipsis;
}
.invoiced-payment-badge.bg-paid {
    background-color: rgba(var(--bs-success-rgb), 0.15) !important;
    color: var(--bs-success) !important;
}
.invoiced-payment-badge.bg-unpaid {
    background-color: rgba(var(--bs-warning-rgb), 0.18) !important;
    color: #b86e00 !important;
}
.invoiced-payment-badge.bg-rejected {
    background-color: rgba(var(--bs-danger-rgb), 0.15) !important;
    color: var(--bs-danger) !important;
}
.invoiced-actions-col {
    width: 4.5rem;
}
.invoiced-payment-edit-btn {
    padding: 0.2rem 0.45rem;
}
.invoiced-active-filters .badge {
    font-weight: 500;
}
.invoiced-report-month-controls {
    padding: 0.35rem 0.5rem;
}
.invoiced-report-period-picker {
    min-width: 0;
    overflow-x: auto;
}
.invoiced-report-month-select {
    width: auto;
    min-width: 10rem;
    flex-shrink: 0;
}
.invoiced-report-nav-btn-group {
    border: 1px solid var(--bs-border-color);
    border-radius: var(--bs-border-radius-sm);
    flex-shrink: 0;
}
.invoiced-report-nav-btn-group > .invoiced-report-nav-btn {
    background-color: #fff;
    color: #2f2b3d;
    border: 0;
    border-right: 1px solid var(--bs-border-color);
    font-weight: 600;
    margin-left: 0 !important;
}
.invoiced-report-nav-btn-group > .invoiced-report-nav-btn:last-child {
    border-right: 0;
}
.invoiced-report-nav-btn-group > .invoiced-report-nav-btn:hover:not(:disabled),
.invoiced-report-nav-btn-group > .invoiced-report-nav-btn:focus:not(:disabled) {
    background-color: #ececf1;
    color: #1e1e2d;
    z-index: 1;
}
.invoiced-report-nav-btn-group > .invoiced-report-nav-btn:disabled {
    background-color: rgba(255, 255, 255, 0.55);
    color: rgba(47, 43, 61, 0.55);
}
.invoiced-report-nav-btn {
    background-color: #fff;
    color: #2f2b3d;
    border: 1px solid var(--bs-border-color);
    font-weight: 600;
}
.invoiced-report-nav-btn:hover:not(:disabled),
.invoiced-report-nav-btn:focus:not(:disabled) {
    background-color: #ececf1;
    color: #1e1e2d;
    border-color: #adb5bd;
}
.invoiced-report-nav-btn:disabled {
    background-color: rgba(255, 255, 255, 0.55);
    color: rgba(47, 43, 61, 0.55);
}
</style>

<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="assets/js/invoiced-projects.js?v=<?=CACHE_VERSION?>"></script>
