<?php

require_once('../application/loader.php');
$view->heading('案件一覧');
// Default note type by branch: CAILY branch -> CAILYメモ(1), otherwise GUISメモ(2)
$noteDefaultType = 2;
$isCailyBranchUser = false;
try {
    require_once('../application/model/branch.php');
    $branchModel = new Branch();
    $branch = $branchModel->get_user_branch_name();
    if ($branch && isset($branch['name']) && $branch['name'] === 'CAILY') {
        $noteDefaultType = 1;
        $isCailyBranchUser = true;
    }
} catch (Exception $e) {
    // fallback giữ nguyên GUISメモ
}
echo '<script>window.NOTE_DEFAULT_TYPE = ' . (int)$noteDefaultType . ';</script>';
if($_SESSION['show_project'] == 0){
    echo '<div class="container-fluid mt-4"><div class="alert alert-danger">権限がありません。</div></div>';
    exit;
}
?>

<div id="app" class="container-fluid mt-4 mb-5" v-cloak>
    <nav class="navbar navbar-expand-lg bg-dark mb-4" id="projectDepartmentNav">
        <div class="container-fluid">
            <span class="navbar-brand" href="javascript:void(0)"></span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-start" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0" id="projectDepartmentNavList">
                    <li class="nav-item" v-for="department in departments" :key="department.id" :class="{ 'active bg-primary text-white rounded-3': selectedDepartment && selectedDepartment.id === department.id, 'd-none': department.can_project == 0 }">
                        <a href="#" class="nav-link" @click="viewProjects(department)" >{{ department.name }}</a>
                    </li>
                </ul>
                <div class="d-flex gap-2">
                    <!-- <a v-if="canAddProject()" :href="createUrl" class="btn btn-primary">
                        <i class="fa fa-plus me-1"></i> <span data-i18n="新規プロジェクト">新規プロジェクト</span>
                    </a> -->
                    <a href="project_gantt.php" class="btn btn-info">
                        <i class="fa fa-chart-bar me-1"></i> <span data-i18n="ガントチャート">ガントチャート</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>
    <div id="projectFilterTourTarget">
    <div class="mb-2">
        <button class="btn btn-outline-primary btn-sm" type="button" id="projectFilterToggleBtn" data-bs-toggle="collapse" data-bs-target="#projectFilterBox" aria-expanded="false">
            <i class="fa fa-filter me-1"></i> <span data-i18n="高度なフィルター">高度なフィルター</span>
        </button>
    </div>
    <div class="collapse" id="projectFilterBox">
        <div class="card mb-3">
            <div class="card-body pb-4 pt-3">
                <form class="row g-3" id="projectFilterForm" autocomplete="off">
                    <div class="col-md-3 col-6">
                    <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="開始月">開始月</label>
                    <input type="text" class="form-control form-control-sm" id="filterStartMonth" autocomplete="off">
                    </div>
                    <div class="col-md-3 col-6">
                    <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="期限月">期限月</label>
                    <input type="text" class="form-control form-control-sm" id="filterEndMonth" autocomplete="off">
                    </div>
                    <div class="col-md-3 col-6" v-if="canViewProjectDirectorColumns()">
                    <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="見積月">見積月</label>
                    <input type="text" class="form-control form-control-sm" id="filterEstimateMonth" autocomplete="off">
                    </div>
                    <div class="col-md-3 col-6" v-if="canViewProjectDirectorColumns()">
                    <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="請求月">請求月</label>
                    <input type="text" class="form-control form-control-sm" id="filterInvoiceMonth" autocomplete="off">
                    </div>
                    <div class="col-md-3 col-6" v-if="canViewProjectDirectorColumns()">
                    <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="見積・請求状況">見積・請求状況</label>
                    <select class="form-select form-select-sm" id="filterBusinessDocumentStatus">
                        <option value="" data-i18n="すべて">すべて</option>
                        <option value="未見積" data-i18n="未見積">未見積</option>
                        <option value="見積作成中" data-i18n="見積作成中">見積作成中</option>
                        <option value="見積済" data-i18n="見積済">見積済</option>
                        <option value="未請求" data-i18n="未請求">未請求</option>
                        <option value="請求準備" data-i18n="請求準備">請求準備</option>
                        <option value="請求済" data-i18n="請求済">請求済</option>
                        <option value="無償" data-i18n="無償">無償</option>
                    </select>
                    </div>
                    <div class="col-md-3 col-6">
                    <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="優先度">優先度</label>
                    <select class="form-select form-select-sm" id="filterPriority"></select>
                    </div>
                    <div class="col-md-3 col-6">
                    <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="進捗率">進捗率</label>
                    <select class="form-select form-select-sm" id="filterProgress">
                        <option value="" data-i18n="すべて">すべて</option>
                        <option value="0-50">0-50%</option>
                        <option value="51-99">51-99%</option>
                        <option value="100">100%</option>
                    </select>
                    </div>
                    <div class="col-md-3 col-6">
                    <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="残り時間">残り時間</label>
                    <select class="form-select form-select-sm" id="filterTimeLeft">
                        <option value="" data-i18n="すべて">すべて</option>
                        <option value="7" data-i18n="7日以内">7日以内</option>
                        <option value="30" data-i18n="30日以内">30日以内</option>
                        <option value="overdue" data-i18n="期限切れ">期限切れ</option>
                    </select>
                    </div>
                    <div class="col-md-3 col-6">
                    <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="本日フィルター">本日フィルター</label>
                    <select class="form-select form-select-sm" id="filterToday">
                        <option value="" data-i18n="すべて">すべて</option>
                        <option value="start_today" data-i18n="開始日=本日">開始日=本日</option>
                        <option value="caily_today" data-i18n="CAILY納期=本日">CAILY納期=本日</option>
                        <option v-if="canViewEndDate" value="guis_today" data-i18n="GUIS納期=本日">GUIS納期=本日</option>
                        <option v-if="canViewEndDate" value="end_today" data-i18n="期限日=本日">期限日=本日</option>
                    </select>
                    </div>
                    
                    <div class="col-md-3 col-6">
                    <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="予定工程">予定工程</label>
                    <input type="text" class="form-control form-control-sm" id="filterYoteiMonth" autocomplete="off">
                    </div>
                    <div class="col-md-3 col-6">
                    <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="受注形態">受注形態</label>
                    <select class="form-select form-select-sm" id="filterProjectOrderType">
                        <option value="">すべて</option>
                        <option value="contract">契約図</option>
                        <option value="new">新規・実施図</option>
                        <option value="edit">修正</option>
                        <option value="other">その他</option>
                    </select>
                    </div>
                    <div class="col-md-3 col-6">
                    <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="納品状況">納品状況</label>
                    <select class="form-select form-select-sm" id="filterDeliveryStatus">
                        <option value="" data-i18n="すべて">すべて</option>
                        <option value="納品済み" data-i18n="納品済み">納品済み</option>
                        <option value="未納品" data-i18n="未納品">未納品</option>
                    </select>
                    </div>
                    <div class="col-md-3 col-6">
                    <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="チーム">チーム</label>
                    <select class="form-select form-select-sm" id="filterTeam" multiple>
                    </select>
                    </div>
                    <div class="col-md-3 col-6">
                    <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="会社">会社</label>
                    <select class="form-select form-select-sm" id="filterCompany" multiple>
                        <option value="daito">大東</option>
                        <option value="token">東建</option>
                        <option value="other">他社</option>
                    </select>
                    </div>
                    <div class="col-md-3 col-6">
                    <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="担当">担当</label>
                    <select class="form-select form-select-sm" id="filterTantou">
                        <option value="">すべて</option>
                        <option value="CAILY">CAILY</option>
                        <option value="GUIS">GUIS</option>
                    </select>
                    </div>
                    <div class="col-md-4 col-12">
                    <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="キーワード">キーワード</label>
                    <input
                        type="text"
                        class="form-control form-control-sm"
                        id="filterKeyword"
                        data-i18n="案件名、工事番号、支店名などで検索..."
                        placeholder="案件名、工事番号、支店名などで検索...">
                    </div>
                    <div class="col-md-2 col-6">
                    <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="案件ID">案件ID</label>
                    <input type="text" class="form-control form-control-sm" id="filterProjectId" placeholder="ID">
                    </div>
                    <div class="col-12 d-flex align-items-end">
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <div class="form-check mb-0 form-switch">
                                <input class="form-check-input" type="checkbox" id="filterMyProjects" v-model="filterMyProjects" @change="loadProjects">
                                <label class="form-check-label" for="filterMyProjects" data-i18n="私の案件">私の案件</label>
                            </div>
                            <div class="form-check mb-0 form-switch">
                                <input class="form-check-input" type="checkbox" id="filterNoDates">
                                <label class="form-check-label" for="filterNoDates" data-i18n="開始日・期限日未設定">開始日・期限日未設定</label>
                            </div>
                            <div class="form-check mb-0 form-switch">
                                <input class="form-check-input" type="checkbox" id="showInactiveSwitch">
                                <label class="form-check-label" for="showInactiveSwitch" data-i18n="完了・中止案件等も表示">完了・中止案件等も表示</label>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="d-flex align-items-center gap-2 mb-2 mt-4">
                    <div class="btn-group flex-wrap">
                        <button 
                            v-for="status in statuses" 
                            :key="status.key"
                            class="btn btn-sm status-filter-btn"
                            :data-i18n="status.name"
                            :class="{
                                [`btn-label-${status.color}`]: !isStatusFilterSelected(status),
                                [`btn-${status.color}`]: isStatusFilterSelected(status),
                                'active': isStatusFilterSelected(status)
                            }"
                            @click="toggleProjectStatusFilter(status)"
                        >
                            {{ status.name }}
                            <span v-show="isStatusFilterSelected(status)" class="active-indicator"></span>
                        </button>
                    </div>
                   
                    <button class="btn btn-sm btn-outline-primary" id="filterReset" type="button">
                        <i class="fa fa-undo me-1"></i><span data-i18n="リセット">リセット</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="projectFilterResetPrefsBtn"
                            data-bs-toggle="offcanvas" data-bs-target="#offcanvasProjectFilterResetPrefs"
                            aria-controls="offcanvasProjectFilterResetPrefs" title="フィルター設定">
                        <i class="fa fa-sliders-h me-1"></i><span data-i18n="フィルター設定">フィルター設定</span>
                        </button>
                    <span id="projectFilterKeepOnResetTourTarget"></span>
                    <button type="button" class="btn btn-sm btn-success" id="projectExportExcelBtn" :disabled="!selectedDepartment || loading" @click="exportProjectListExcel" title="Excel出力">
                        <i class="fa fa-file-excel me-1"></i><span data-i18n="Excel出力">Excel出力</span>
                    </button>
                </div>
                <div class="d-flex align-items-center gap-2 mt-6">
                    <div class="form-check d-flex align-items-center">
                        <input class="form-check-input me-1" type="checkbox" id="filterFavoritesOnly" @change="onFavoritesFilterChange">
                        <label class="form-check-label mb-0" for="filterFavoritesOnly">
                            <i class="fa fa-star text-warning me-1"></i><span data-i18n="お気に入りのみ">お気に入りのみ</span>
                        </label>
                    </div>
                    <button v-if="showClearAllFavoritesBtn" class="btn btn-sm btn-outline-danger ms-2" @click="clearAllFavorites">
                        <i class="fa fa-trash me-1"></i><span data-i18n="お気に入りをすべて削除">お気に入りをすべて削除</span>
                    </button>
                </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card" id="projectTableCard">
        <div class="card-body position-relative">
            <!-- Loading overlay -->
            <div v-if="loading" class="position-absolute top-0 start-0 end-0 bottom-0 d-flex align-items-center justify-content-center bg-white bg-opacity-90 rounded" style="z-index: 100;">
                <div class="text-center">
                    <div class="spinner-border text-primary mb-2" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="text-muted" data-i18n="データを読み込み中...">データを読み込み中...</div>
                </div>
            </div>
            
            <!-- Active Filters Display -->
            <div id="activeFilters" class="mb-2"></div>
            <p class="small text-muted mb-2" id="projectTableScrollHint">
                <span id="projectTableTourHintSpaceScroll">
                <i class="fa fa-info-circle me-1 text-info"></i><span data-i18n="Spaceを押したままドラッグで表を横スクロール">Spaceを押したままドラッグで表を横スクロール</span>
                </span>
                <span id="projectTableTourHintReorder" class="ms-4 d-inline-block">
                    <i class="fa fa-info-circle me-1 text-info"></i><span data-i18n="列見出しをドラッグして表示順を変更できます。">列見出しをドラッグして表示順を変更できます。</span>
                </span>
                <span id="projectTableTourHintResize" class="ms-4 d-inline-block">
                    <i class="fa fa-info-circle me-1 text-info"></i><span data-i18n="列見出しの右端をドラッグして列幅を変更できます。">列見出しの右端をドラッグして列幅を変更できます。</span>
                </span>
            </p>
            <div id="projectListColumnToolsRow" class="d-flex justify-content-end align-items-center gap-2 mb-1 flex-wrap" v-show="selectedDepartment">
                <div class="dropdown" id="projectListSortTourTarget">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="projectListSortDropdown"
                            data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <i class="fa fa-sort me-1"></i><span data-i18n="並べ替え">並べ替え</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-3" aria-labelledby="projectListSortDropdown" style="min-width: 260px;">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="sortByStatusSwitch" checked>
                            <label class="form-check-label" for="sortByStatusSwitch" data-i18n="ステータス順で並べ替え">ステータス順で並べ替え</label>
                        </div>
                        <div class="mb-2">
                            <label class="form-label form-label-sm mb-1" for="projectListSortColumn" data-i18n="並べ替え項目">並べ替え項目</label>
                            <select class="form-select form-select-sm" id="projectListSortColumn">
                                <option value="" data-i18n="デフォルト">デフォルト</option>
                                <option value="yotei" data-i18n="予定工程">予定工程</option>
                                <option value="start_date" data-i18n="開始日">開始日</option>
                                <option value="caily_nouki" data-i18n="CAILY納期">CAILY納期</option>
                                <option value="guis_nouki" data-sort-field="guis_nouki" data-i18n="GUIS納期">GUIS納期</option>
                                <option value="end_date" data-sort-field="end_date" data-i18n="期限日">期限日</option>
                                <option value="estimate_date" data-sort-field="estimate_date" data-director-only="1" data-i18n="見積日">見積日</option>
                                <option value="invoice_date" data-sort-field="invoice_date" data-director-only="1" data-i18n="請求日">請求日</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label form-label-sm mb-1" for="projectListSortDir" data-i18n="並び順">並び順</label>
                            <select class="form-select form-select-sm" id="projectListSortDir">
                                <option value="asc" data-i18n="昇順">昇順</option>
                                <option value="desc" data-i18n="降順">降順</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="dropdown" id="projectColumnVisibilityTourTarget" v-if="availableColumns && availableColumns.length > 0">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa fa-columns me-1"></i><span data-i18n="列の表示">列の表示</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="columnVisibilityDropdown" id="columnVisibilityMenu" style="max-height: 400px; overflow-y: auto; min-width: 200px;">
                        <li v-for="column in visibleColumnOptions" :key="column.key" class="dropdown-item-text px-3 py-2">
                            <div class="form-check">
                                <input class="form-check-input column-visibility-checkbox"
                                    type="checkbox"
                                    :value="column.key"
                                    :id="'col-' + column.key"
                                    :checked="column.visible"
                                    @change="toggleColumnVisibility(column.key, $event)">
                                <label class="form-check-label" :for="'col-' + column.key" style="cursor: pointer;">
                                    <span :data-i18n="column.label">{{ column.label }}</span>
                                </label>
                            </div>
                        </li>
                    </ul>
                </div>
                <div id="projectListColumnResetMount" class="project-list-column-reset-tour-target"></div>
            </div>
            <table id="projectTable" class="table table-striped">
                
            </table>
        </div>
    </div>

    <!-- Fixed action buttons (shown when scroll reaches projectTableCard) -->
    <div class="position-fixed d-none d-flex gap-2" id="projectFloatActions" style="bottom: 10px; left: 6rem; z-index: 1050;">
        <button type="button" class="btn btn-sm btn-primary rounded-pill shadow-lg" id="projectFilterFloatBtn" data-bs-toggle="offcanvas" data-bs-target="#offcanvasProjectFilter" aria-controls="offcanvasProjectFilter" title="高度なフィルター">
            <i class="fa fa-filter me-1"></i> <span data-i18n="高度なフィルター">高度なフィルター</span>
        </button>
        <button type="button" class="btn btn-sm btn-info rounded-pill shadow-lg" id="projectReloadFloatBtn" title="更新">
            <i class="fa fa-refresh me-1"></i> <span data-i18n="更新">更新</span>
        </button>
    </div>

    <!-- Shepherd Tour reopen button -->
    <button type="button" id="projectListTourBtn" class="btn btn-warning rounded-circle position-fixed shadow-lg" title="UIガイド / Hướng dẫn UI" aria-label="UIガイド">
        <i class="fas fa-map-signs"></i>
    </button>


    <!-- Offcanvas: nội dung = #projectFilterBox (mở từ bottom giống Todo List) -->
    <div class="offcanvas offcanvas-bottom" tabindex="-1" id="offcanvasProjectFilter" aria-labelledby="offcanvasProjectFilterLabel" style="height: 40rem;">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title" id="offcanvasProjectFilterLabel"><i class="fa fa-filter me-2"></i><span data-i18n="高度なフィルター">高度なフィルター</span></h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="閉じる"></button>
        </div>
        <div class="offcanvas-body overflow-auto" id="projectFilterOffcanvasBody">
            <!-- Nội dung #projectFilterBox sẽ được chuyển vào đây khi mở offcanvas -->
        </div>
    </div>

    <!-- Offcanvas: reset filter preferences (right side) -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasProjectFilterResetPrefs"
         aria-labelledby="offcanvasProjectFilterResetPrefsLabel" style="width: min(360px, 92vw);">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title" id="offcanvasProjectFilterResetPrefsLabel">
                <i class="fa fa-sliders-h me-2"></i><span data-i18n="フィルター設定">フィルター設定</span>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="閉じる"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column">
            <p class="text-muted small mb-3" data-i18n="チェックした項目は「リセット」後も値が保持されます。">
                チェックした項目は「リセット」後も値が保持されます。
            </p>
            <div id="projectFilterResetPrefsList" class="flex-grow-1 overflow-auto"></div>
            <div class="d-flex gap-2 mt-3 pt-3 border-top">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="projectFilterResetPrefsSelectAll">
                    <span data-i18n="すべて選択">すべて選択</span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="projectFilterResetPrefsClearAll">
                    <span data-i18n="すべて解除">すべて解除</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">削除確認</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>本当にこのプロジェクトを削除しますか？</p>
                    <p class="text-danger">この操作は取り消せません。</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-danger" @click="confirmDelete">削除</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Parent Construction Number Modal -->
    <div class="modal fade" id="editParentConstructionNumberModal" tabindex="-1" aria-labelledby="editParentConstructionNumberModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editParentConstructionNumberModalLabel">
                        <span data-i18n="工事番号を編集">工事番号を編集</span>
                        <span class="badge bg-label-primary ms-2" id="editParentConstructionProjectIdBadge"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editParentConstructionProjectId">
                    <div class="mb-3">
                        <label class="form-label" for="editParentConstructionNumberInput"><span data-i18n="工事番号">工事番号</span></label>
                        <input type="text" class="form-control" id="editParentConstructionNumberInput" autocomplete="off" placeholder="工事番号を入力">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><span data-i18n="キャンセル">キャンセル</span></button>
                    <button type="button" class="btn btn-primary" id="editParentConstructionSaveBtn">
                        <span class="spinner-border spinner-border-sm d-none" id="editParentConstructionSaveSpinner"></span>
                        <span data-i18n="更新">更新</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Business Document (決済情報) Modal -->
    <div class="modal fade" id="businessDocumentModal" tabindex="-1" aria-labelledby="businessDocumentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content" v-if="businessDocumentProject">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="modal-title mb-0" id="businessDocumentModalLabel">
                            <span data-i18n="決済情報">決済情報</span>
                            <span class="text-muted small ms-2">#{{ businessDocumentProjectId }} {{ businessDocumentProject.name }}</span>
                    </h5>
                        <span v-if="businessDocumentSaveStatus === 'loading'" class="text-muted" title="保存中">
                            <i class="fa fa-spinner fa-spin"></i>
                        </span>
                        <span v-else-if="businessDocumentSaveStatus === 'saved'" class="text-success" title="保存済み">
                            <i class="fa fa-check-circle"></i>
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2 mx-2">
                        <button type="button" class="btn btn-outline-info btn-sm" @click="openBusinessDocumentLogModal">
                            <i class="fa fa-history me-1"></i><span data-i18n="履歴">履歴</span>
                        </button>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" @click="closeBusinessDocumentModal"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <input type="hidden" v-model.number="businessDocumentProject.payment_version">
                    <h6 class="text-muted mb-3"><span data-i18n="見積">見積</span></h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label mb-0">見積日 <span class="text-danger">*</span></label>
                                <button v-if="!hasBdDate('estimate_date')" type="button" class="btn btn-outline-primary btn-sm py-0 px-2"
                                        @click="setBdDateToday('estimate_date')">今日</button>
                            </div>
                            <input type="text" class="form-control" v-model="businessDocumentProject.estimate_date"
                                   id="bd_modal_estimate_date_picker" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">見積金額(税抜き) <span class="text-danger">*</span></label>
                            <input type="number" autocomplete="off" class="form-control" v-model.number="businessDocumentProject.amount"
                                   @input="scheduleBdUpdate" min="0" step="1">
                    </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">消費税（10%）</label>
                            <input type="text" class="form-control bg-light" readonly tabindex="-1" :value="formatBusinessDocumentTaxAmount(businessDocumentProject.amount)">
                            </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">税込合計</label>
                            <input type="text" class="form-control bg-light fw-semibold" readonly tabindex="-1" :value="formatBusinessDocumentTotalWithTax(businessDocumentProject.amount)">
                            </div>
                        <div class="col-md-6">
                            <label class="form-label">見積番号 <span class="text-danger">*</span></label>
                            <input type="text" autocomplete="off" class="form-control" v-model="businessDocumentProject.estimate_number" @change="scheduleBdUpdate">
                            </div>
                        <div class="col-md-6">
                            <label class="form-label">見積状況</label>
                            <div class="btn-group d-block">
                                <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light"
                                        :class="getBdEstimateStatusButtonClass(businessDocumentProject.estimate_status)"
                                        id="bdEstimateStatusDropdown"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    {{ getBdEstimateStatusLabel(businessDocumentProject.estimate_status) }}
                                </button>
                                <ul class="dropdown-menu">
                                    <li v-for="status in businessEstimateStatuses" :key="status.value">
                                        <a class="dropdown-item waves-effect" href="javascript:void(0);"
                                           :class="{ disabled: status.value === '発行済' && !isBdEstimateDocumentFieldsComplete() }"
                                           @click="selectBdEstimateStatus(status.value)">
                                            {{ status.label }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div v-if="!isBdEstimateDocumentFieldsComplete()" class="form-text text-muted">
                                発行済にするには見積日・見積金額・見積番号が必要です
                            </div>
                                </div>
                            </div>

                    <hr class="my-3">

                    <h6 class="text-muted mb-3"><span data-i18n="請求">請求</span></h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label mb-0">請求日 <span class="text-danger">*</span></label>
                                <button v-if="!hasBdDate('invoice_date')" type="button" class="btn btn-outline-primary btn-sm py-0 px-2"
                                        @click="setBdDateToday('invoice_date')">今日</button>
                                    </div>
                            <input type="text" class="form-control" v-model="businessDocumentProject.invoice_date"
                                   id="bd_modal_invoice_date_picker" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off">
                                    </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label mb-0">請求金額(税抜き) <span class="text-danger">*</span></label>
                                <button v-if="!hasBdAmount(businessDocumentProject.invoice_amount)" type="button" class="btn btn-outline-primary btn-sm py-0 px-2"
                                        @click="copyBdEstimateAmountToInvoice">見積と同額</button>
                                </div>
                            <input type="number" autocomplete="off" class="form-control" v-model.number="businessDocumentProject.invoice_amount"
                                   @input="scheduleBdUpdate" min="0" step="1">
                            </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">消費税（10%）</label>
                            <input type="text" class="form-control bg-light" readonly tabindex="-1" :value="formatBusinessDocumentTaxAmount(businessDocumentProject.invoice_amount)">
                                    </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">税込合計</label>
                            <input type="text" class="form-control bg-light fw-semibold" readonly tabindex="-1" :value="formatBusinessDocumentTotalWithTax(businessDocumentProject.invoice_amount)">
                                </div>
                        <div class="col-md-6">
                            <label class="form-label">請求番号 <span class="text-danger">*</span></label>
                            <input type="text" autocomplete="off" class="form-control" v-model="businessDocumentProject.invoice_number" @change="scheduleBdUpdate">
                            </div>
                        <div class="col-md-6">
                            <label class="form-label">請求状況</label>
                            <div class="btn-group d-block">
                                <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light"
                                        :class="getBdInvoiceStatusButtonClass(businessDocumentProject.invoice_status)"
                                        id="bdInvoiceStatusDropdown"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    {{ getBdInvoiceStatusLabel(businessDocumentProject.invoice_status) }}
                                </button>
                                <ul class="dropdown-menu">
                                    <li v-for="status in businessInvoiceStatuses" :key="status.value">
                                        <a class="dropdown-item waves-effect" href="javascript:void(0);"
                                           :class="{ disabled: status.value === '発行済' && !isBdInvoiceDocumentFieldsComplete() }"
                                           @click="selectBdInvoiceStatus(status.value)">
                                            {{ status.label }}
                                        </a>
                                    </li>
                                </ul>
                                    </div>
                            <div v-if="!isBdInvoiceDocumentFieldsComplete()" class="form-text text-muted">
                                発行済にするには請求日・請求金額・請求番号が必要です
                                </div>
                            </div>
                            </div>

                    <hr class="my-3">

                    <div class="mb-0">
                        <label class="form-label" data-i18n="決済備考">決済備考</label>
                        <textarea class="form-control" rows="3" v-model="businessDocumentProject.payment_note" @change="scheduleBdUpdate"></textarea>
                                </div>

                    <div v-if="businessDocumentError" class="alert alert-danger mt-3 mb-0">{{ businessDocumentError }}</div>
                            </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" @click="closeBusinessDocumentModal">閉じる</button>
                                </div>
                            </div>
                                </div>
                            </div>

    <!-- Business Document History Modal -->
    <div class="modal fade" tabindex="-1" :class="{show: showBusinessDocumentLogModal}" style="display: block;" v-if="showBusinessDocumentLogModal">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">決済情報 履歴</h5>
                    <button type="button" class="btn-close" @click="closeBusinessDocumentLogModal"></button>
                            </div>
                <div class="modal-body p-0">
                    <ul class="list-group list-group-flush">
                        <li v-for="log in sortedBusinessDocumentLogs" :key="log.id" class="list-group-item">
                            <div class="d-flex">
                                <div class="d-flex flex-row align-items-start justify-content-start me-3" style="min-width:130px;">
                                    <div class="d-flex flex-column align-items-center justify-content-start" style="width:40px;">
                                        <div class="avatar">
                                            <span class="avatar-initial rounded-circle bg-label-primary">
                                                {{ getInitials(log.username || log.realname || '?', log.userid || log.user_id || '', log.user_ruby || '') }}
                                            </span>
                                            <img v-if="log.user_image"
                                                :src="'/assets/upload/avatar/' + log.user_image" alt="avatar" class="rounded-circle"
                                                style="display:none;"
                                                @load="$event.target.style.display='block'; if ($event.target.previousElementSibling) $event.target.previousElementSibling.style.display='none';"
                                                @error="$event.target.remove()">
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column align-items-start justify-content-center ms-2">
                                        <span class="fw-bold small">{{ getBdLogDisplayName(log) }}</span>
                                        <span class="text-muted small">{{ formatShortDateTime(log.time) }}</span>
                        </div>
                                </div>
                                <div class="flex-grow-1 d-flex align-items-center">
                                    <span>
                                        <i :class="bdHistoryIcon(log.action) + ' me-2'"></i>
                                        <span class="me-2">{{ getLogNote(log) }}</span>
                                        <br>
                                        <span v-if="hasBdLogValue(log.value1)" :class="getBdLogBadgeClass(log, 'value1')" class="mx-1">{{ getBusinessDocumentLogValue(log, 'value1') }}</span>
                                        <span v-if="hasBdLogValue(log.value1) && hasBdLogValue(log.value2)" class="mx-1">→</span>
                                        <span v-if="hasBdLogValue(log.value2)" :class="getBdLogBadgeClass(log, 'value2')" class="mx-1">{{ getBusinessDocumentLogValue(log, 'value2') }}</span>
                                    </span>
                                </div>
                            </div>
                        </li>
                        <li v-if="!sortedBusinessDocumentLogs || sortedBusinessDocumentLogs.length === 0" class="list-group-item text-muted">決済情報の履歴はありません。</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="closeBusinessDocumentLogModal">閉じる</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show" v-if="showBusinessDocumentLogModal"></div>

    <!-- Note Modal (for 確認必要メモ) -->
    <div class="modal fade" tabindex="-1" :class="{show: showNoteModal}" style="display: block;" v-if="showNoteModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"></h5>
                    <button type="button" class="btn-close" @click="closeNoteModal"></button>
                </div>
                <div class="modal-body">
                    <!-- View mode -->
                    <div v-if="editingNote.id && !isNoteEditMode">
                        <div class="mb-3">
                            <label class="form-label"><span data-i18n="内容">内容</span></label>
                            <div class="form-control ql-editor" style="min-height:100px;max-height:600px;overflow-y:auto;" v-html="editingNote.content || '-'"></div>
                        </div>
                        <div class="mb-3" v-if="editingNote.is_important">
                            <label class="form-label"><span data-i18n="重要メモ">重要メモ</span></label>
                            <div>
                                <i class="fa fa-exclamation-circle text-danger"></i>
                            </div>
                        </div>
                        <div class="mb-3" v-if="editingNote.needs_confirmation">
                            <label class="form-label"><span>区分</span></label>
                            <div>
                                <span class="badge bg-primary" v-if="String(editingNote.needs_confirmation) === '1'">CAILYメモ</span>
                                <span class="badge bg-dark" v-else-if="String(editingNote.needs_confirmation) === '2'">GUISメモ</span>
                                <span class="badge bg-primary" v-else>確認必要</span>
                            </div>
                        </div>
                        <div class="mb-3" v-if="editingNote.display_column">
                            <label class="form-label"><span>表示列</span></label>
                            <div>{{ getNoteDisplayColumnLabel(editingNote.display_column) }}</div>
                        </div>
                    </div>
                    <!-- Edit mode -->
                    <form v-else @submit.prevent="saveNote">
                        <div class="mb-3">
                            <label class="form-label"><span data-i18n="内容">内容</span></label>
                            <div class="custom_editor">
                                <div class="custom_editor_content" id="quill_note_content"></div>
                                <textarea class="custom_editor_textarea d-none" v-model="editingNote.content" id="quill_note_content_textarea"></textarea>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" v-model="editingNote.is_important" id="isImportant">
                                <label class="form-check-label" for="isImportant">
                                    <i class="fa fa-exclamation-circle text-danger me-2"></i> <span data-i18n="重要メモ">重要メモ</span>
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label d-block"><span>区分</span></label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" :value="1" v-model="editingNote.needs_confirmation" id="needsConfirmationCaily">
                                <label class="form-check-label" for="needsConfirmationCaily">CAILYメモ</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" :value="2" v-model="editingNote.needs_confirmation" id="needsConfirmationGuis">
                                <label class="form-check-label" for="needsConfirmationGuis">GUISメモ</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><span>表示列</span></label>
                            <select class="form-select" v-model="editingNote.display_column">
                                <option value="">— 選択 —</option>
                                <option v-for="opt in noteDisplayColumnOptions" :key="opt.value" :value="opt.value">{{ opt.text }}</option>
                            </select>
                        </div>
                    </form>
                </div>
                    <div class="modal-footer">
                        <template v-if="editingNote.id && !isNoteEditMode">
                            <button class="btn btn-primary" @click="isNoteEditMode = true; $nextTick(() => initQuillNoteEditor())"><i class="fa fa-pencil-alt me-2"></i> <span data-i18n="編集">編集</span></button>
                            <button class="btn btn-secondary" @click="closeNoteModal"><span data-i18n="閉じる">閉じる</span></button>
                        </template>
                        <template v-else>
                            <button class="btn btn-danger me-auto" v-if="editingNote.id" @click="deleteCurrentNote">
                                <i class="fa fa-trash me-2"></i> <span data-i18n="削除">削除</span>
                            </button>
                            <button class="btn btn-secondary" @click="closeNoteModal"><i class="fa fa-times me-2"></i> <span data-i18n="キャンセル">キャンセル</span></button>
                            <button class="btn btn-primary" @click="saveNote" :disabled="savingNote || !canSaveNote">
                                <i class="fa fa-save me-2"></i> <span data-i18n="保存">保存</span>
                            </button>
                        </template>
                    </div>
            </div>
        </div>
    </div>

    <!-- Kadai Projects Queue - Fixed Bottom -->
    <div id="kadaiQueue" class="kadai-queue-container" v-show="kadaiProjects.length > 0">
        <div class="kadai-queue-header" @click="toggleKadaiQueue">
            <div class="d-flex align-items-center justify-content-between w-100">
                <div class="d-flex align-items-center">
                    <i class="fa fa-tasks me-2"></i>
                    <span class="fw-bold">課題案件キュー</span>
                    <span v-if="selectedDepartment" class="ms-2 small opacity-75">({{ selectedDepartment.name }})</span>
                    <span class="badge bg-warning ms-2" v-if="kadaiProjects.length > 0">{{ kadaiProjects.length }}</span>
                </div>
                    <div class="d-flex align-items-center">
                        <button class="btn btn-sm btn-outline-warning me-2" @click.stop="refreshKadaiQueue" title="更新">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <i class="fa fa-chevron-up" :class="{ 'fa-chevron-down': !isKadaiQueueExpanded }"></i>
                    </div>
            </div>
        </div>
        
                            <div class="kadai-queue-content" v-show="isKadaiQueueExpanded">
                        <div v-if="!selectedDepartment" class="text-center py-3 text-muted">
                            <i class="fa fa-building fa-2x mb-2"></i>
                            <p class="mb-0">部署を選択してください</p>
                            <small class="text-muted">部署を選択すると課題案件が表示されます</small>
                        </div>
                        
                        <div v-else-if="kadaiProjects.length === 0" class="text-center py-3 text-muted">
                            <i class="fa fa-inbox fa-2x mb-2"></i>
                            <p class="mb-0">課題案件はありません</p>
                        </div>
                        
                        <div v-else class="kadai-queue-list">
                <div v-for="project in kadaiProjects" :key="project.id" class="kadai-queue-item">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-1">
                                <span class="badge bg-warning me-2">課題</span>
                                <span class="badge bg-info">承認待ち</span>
                            </div>
                            <h6 class="mb-1 text-truncate" :title="project.name">{{ project.name }}</h6>
                            <div class="small text-muted">
                                <span class="me-2" :title="project.department_name">{{ project.department_name }}</span>
                                <span v-if="canViewEndDate && project.end_date" class="me-2" :title="'期限: ' + formatDate(project.end_date)">
                                    <i class="fa fa-calendar me-1"></i>{{ formatDate(project.end_date) }}
                                </span>
                            </div>
                        </div>
                        <div class="d-flex flex-column align-items-end">
                                                                    <div class="btn-group btn-group-sm mb-2">
                                            <a :href="`detail.php?id=${project.id}`" class="btn btn-outline-primary btn-sm" title="詳細">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            <button class="btn btn-outline-info btn-sm" @click="confirmProject(project)" title="プロジェクトを承認">
                                                <i class="fa fa-check"></i>
                                            </button>
                                        </div>
                            <small class="text-muted">{{ project.project_number || '-' }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Edit Project Modal (案件を編集) — outside Vue #app so DOM is not rewritten -->
<div class="modal fade" id="quickEditProjectModal" tabindex="-1" aria-labelledby="quickEditProjectModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickEditProjectModalLabel">
                    <span data-i18n="案件を編集">案件を編集</span>
                    <span class="badge bg-label-primary ms-2" id="quickEditProjectIdBadge"></span>
                </h5>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><span data-i18n="キャンセル">キャンセル</span></button>
                    <button type="button" class="btn btn-primary btn-sm" id="quickEditProjectSaveBtnHeader">
                        <span class="spinner-border spinner-border-sm d-none" id="quickEditSaveSpinnerHeader"></span>
                        <span data-i18n="更新">更新</span>
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body position-relative">
                <div id="quickEditModalLoading" class="position-absolute top-0 start-0 end-0 bottom-0 d-flex align-items-center justify-content-center bg-white bg-opacity-90 rounded d-none" style="z-index: 10;">
                    <div class="text-center">
                        <div class="spinner-border text-primary mb-2" role="status" style="width: 2.5rem; height: 2.5rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="small text-muted" data-i18n="読み込み中...">読み込み中...</div>
                    </div>
                </div>
                <form id="quickEditProjectForm">
                    <input type="hidden" name="id" id="quickEditProjectId">
                    <input type="hidden" name="version" id="quickEditProjectVersion" value="1">
                    <div class="row g-3">
                        <div class="col-md-12 quick-edit-full-only">
                            <label class="form-label"><span data-i18n="案件名">案件名</span> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="quickEditName" required>
                            <div class="invalid-feedback" id="quickEditNameError"></div>
                        </div>
                        <div class="col-md-4 quick-edit-full-only">
                            <label class="form-label"><span data-i18n="開始日">開始日</span></label>
                            <input type="text" class="form-control" name="start_date" id="quickEditStartDate" placeholder="YYYY-MM-DD HH:mm" autocomplete="off">
                            <div class="invalid-feedback" id="quickEditStartDateError"></div>
                        </div>
                        <div class="col-md-4 quick-edit-full-only quick-edit-guis-field">
                            <label class="form-label"><span data-i18n="期限日">期限日</span></label>
                            <input type="text" class="form-control" name="end_date" id="quickEditEndDate" placeholder="YYYY-MM-DD HH:mm" autocomplete="off">
                            <div class="invalid-feedback" id="quickEditEndDateError"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><span data-i18n="ステータス">ステータス</span></label>
                            <select class="form-select" name="status" id="quickEditStatus">
                                <option value="draft" data-i18n="受付">受付</option>
                                <option value="open" data-i18n="納期検討">納期検討</option>
                                <option value="confirming" data-i18n="仮受">仮受</option>
                                <option value="quotation" data-i18n="見積">見積</option>
                                <option value="contract" data-i18n="請負">請負</option>
                                <option value="waiting_documents" data-i18n="資料待ち">資料待ち</option>
                                <option value="in_progress" data-i18n="進行中">進行中</option>
                                <option value="completed" data-i18n="完了">完了</option>
                                <option value="paused" data-i18n="一時停止">一時停止</option>
                                <option value="cancelled" data-i18n="中止">中止</option>
                            </select>
                        </div>
                        <div class="col-12" id="quickEditYoteiWrap">
                            <label class="form-label"><span data-i18n="予定工程">予定工程</span></label>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <div class="d-flex gap-1">
                                    <input type="text" class="form-control" style="min-width: 9rem;" id="quickEditYoteiFromMonth" autocomplete="off" placeholder="YYYY-MM">
                                    <select class="form-select" style="width: 6.5rem;" id="quickEditYoteiFromPart">
                                        <option value="">—</option>
                                        <option value="early" data-i18n="上旬">上旬</option>
                                        <option value="mid" data-i18n="中旬">中旬</option>
                                        <option value="late" data-i18n="下旬">下旬</option>
                                    </select>
                                </div>
                                <div class="text-muted">～</div>
                                <div class="d-flex gap-1">
                                    <input type="text" class="form-control" style="min-width: 9rem;" id="quickEditYoteiToMonth" autocomplete="off" placeholder="YYYY-MM">
                                    <select class="form-select" style="width: 6.5rem;" id="quickEditYoteiToPart">
                                        <option value="">—</option>
                                        <option value="early" data-i18n="上旬">上旬</option>
                                        <option value="mid" data-i18n="中旬">中旬</option>
                                        <option value="late" data-i18n="下旬">下旬</option>
                                    </select>
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="quickEditYoteiClear" data-i18n="クリア">クリア</button>
                                <div class="ms-2 small text-body-secondary" id="quickEditYoteiPreview"></div>
                            </div>
                            <div class="invalid-feedback" id="quickEditYoteiError"></div>
                        </div>
                        <div class="col-md-6 quick-edit-full-only">
                            <label class="form-label"><span data-i18n="受注形態">受注形態</span></label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="text" class="form-control tagify" name="project_order_type" id="quickEditProjectOrderType" placeholder="">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="quickEditProjectOrderTypeClear" title="すべて削除"><i class="fa fa-times"></i></button>
                            </div>
                            <div class="invalid-feedback" id="quickEditProjectOrderTypeError"></div>
                        </div>
                        <div class="col-md-4 quick-edit-full-only">
                            <label class="form-label"><span data-i18n="担当">担当</span></label>
                            <div class="d-flex gap-3" id="quickEditTantouWrap">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tantou" id="quickEditTantouCaily" value="CAILY">
                                    <label class="form-check-label" for="quickEditTantouCaily">CAILY</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tantou" id="quickEditTantouGuis" value="GUIS">
                                    <label class="form-check-label" for="quickEditTantouGuis">GUIS</label>
                                </div>
                            </div>
                            <div id="quickEditTantouDisplayText" class="fw-semibold d-none"></div>
                            <div class="invalid-feedback" id="quickEditTantouError"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><span data-i18n="CAILY納期">CAILY納期</span> <span id="quickEditCailyNoukiRequired" class="text-danger d-none">*</span></label>
                            <div class="d-flex flex-column">
                                <input type="text" class="form-control" name="caily_nouki" id="quickEditCailyNouki" placeholder="YYYY-MM-DD HH:mm" autocomplete="off">
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" id="quickEditCailyNoukiStatus" name="caily_nouki_status" value="納品済み">
                                    <label class="form-check-label" for="quickEditCailyNoukiStatus"><span data-i18n="納品済み">納品済み</span></label>
                                </div>
                            </div>
                            <div class="invalid-feedback" id="quickEditCailyNoukiError"></div>
                        </div>
                        <div class="col-md-4 quick-edit-guis-field">
                            <label class="form-label"><span data-i18n="GUIS納期">GUIS納期</span> <span id="quickEditGuisNoukiRequired" class="text-danger d-none">*</span></label>
                            <div class="d-flex flex-column">
                                <input type="text" class="form-control" name="guis_nouki" id="quickEditGuisNouki" placeholder="YYYY-MM-DD HH:mm" autocomplete="off">
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" id="quickEditGuisNoukiStatus" name="guis_nouki_status" value="納品済み">
                                    <label class="form-check-label" for="quickEditGuisNoukiStatus"><span data-i18n="納品済み">納品済み</span></label>
                                </div>
                            </div>
                            <div class="invalid-feedback" id="quickEditGuisNoukiError"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><span data-i18n="進捗率">進捗率</span> (%)</label>
                            <input type="number" class="form-control" name="progress" id="quickEditProgress" min="0" max="100" step="5" value="0" placeholder="0">
                            <div class="invalid-feedback" id="quickEditProgressError"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><span data-i18n="チーム">チーム</span></label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="text" class="form-control" id="quickEditTeamTags" placeholder="チームを選択">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="quickEditTeamTagsClear" title="すべて削除"><i class="fa fa-times"></i></button>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><span data-i18n="管理">管理</span></label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="text" class="form-control" id="quickEditManagerTags" placeholder="管理者を選択">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="quickEditManagerTagsClear" title="すべて削除"><i class="fa fa-times"></i></button>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><span data-i18n="メンバー">メンバー</span></label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="text" class="form-control" id="quickEditMembersTags" placeholder="メンバーを選択">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="quickEditMembersTagsClear" title="すべて削除"><i class="fa fa-times"></i></button>
                            </div>
                        </div>
                        <div class="col-12 mt-4 row" id="quickEditCustomFieldsWrap">
                            <!-- Custom fields rendered by JS -->
                        </div>
                        <div class="col-12 quick-edit-full-only">
                            <label class="form-label"><span data-i18n="説明">説明</span></label>
                            <div class="custom_editor">
                                <div class="custom_editor_content" id="quickEditQuillDescription"></div>
                                <textarea class="custom_editor_textarea d-none" id="quickEditQuillDescriptionTextarea"></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><span data-i18n="キャンセル">キャンセル</span></button>
                <button type="button" class="btn btn-primary" id="quickEditProjectSaveBtn">
                    <span class="spinner-border spinner-border-sm d-none" id="quickEditSaveSpinner"></span>
                    <span data-i18n="更新">更新</span>
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Ensure customer modal markup exists before footer scripts (require_once is safe if layout already included it)
if (!empty($_SESSION['show_project'])) {
    require_once DIR_VIEW . 'customer-global-modal.php';
}
$view->footing();
?>

<style>
/* Time remaining badge styling */
.badge.pulse-animation {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
    }
}

/* Cột 案件状況: cố định đúng 50px */
#projectTable th.dt-status-col,
#projectTable td.dt-status-col {
    width: 50px !important;
    min-width: 50px !important;
    max-width: 50px !important;
    box-sizing: border-box;
}
#projectTable td.dt-status-col .badge {
    display: inline-block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.7rem;
    padding: 0.2rem 0.35rem;
}

/* Badge styling for time remaining in end date column */
#projectTable .d-flex.flex-column .badge {
    font-size: 0.7rem;
    padding: 0.2rem 0.4rem;
    border-radius: 0.375rem;
    font-weight: normal;
    transition: all 0.2s ease;
    max-width: fit-content;
}

#projectTable .d-flex.flex-column .badge:hover {
    transform: scale(1.05);
}
#projectTable{
    border: 0!important;
}
/* Ensure proper spacing in end date column */
#projectTable td {
    vertical-align: baseline;
    padding: 0.2rem;
    border: 1px solid #aaa;
    
}

/* ColReorder: hover tiêu đề cột — cursor move để biết có thể đổi thứ tự */
#projectTable thead th,
#projectTable_wrapper thead th,
div.dt-scroll-head thead th {
    cursor: move;
}

/* Column resize handle (viền phải header) */
#projectTable thead th.pl-col-resizable-th,
#projectTable_wrapper .dt-scroll-head thead th.pl-col-resizable-th,
div.dt-scroll-head thead th.pl-col-resizable-th {
    position: relative;
}
.pl-col-resize-handle {
    position: absolute;
    top: 0;
    right: 0;
    width: 8px;
    height: 100%;
    cursor: col-resize;
    z-index: 5;
    user-select: none;
    touch-action: none;
    pointer-events: auto;
}
.pl-col-resize-handle:hover {
    background-color: rgba(105, 108, 255, 0.35);
}
body.pl-col-resizing {
    user-select: none;
    cursor: col-resize !important;
}
body.pl-col-resizing * {
    cursor: col-resize !important;
}

.table thead tr th {
    padding: 0.4rem!important;
}

/* Cột 受注形態: chiều ngang tối thiểu 60px, không khoá max */
#projectTable th.project-order-type-column,
#projectTable td.project-order-type-column,
#projectTable_wrapper .dt-scroll-head th.project-order-type-column {
    min-width: 60px;
    overflow: hidden;
}
#projectTable td.project-order-type-column .d-flex {
    overflow: hidden;
}

/* Cột 担当 / 優先度: chiều ngang tối thiểu 50px */
#projectTable th.tantou-column,
#projectTable td.tantou-column,
#projectTable th.priority-column,
#projectTable td.priority-column,
#projectTable_wrapper .dt-scroll-head th.tantou-column,
#projectTable_wrapper .dt-scroll-head th.priority-column {
    min-width: 50px;
    overflow: hidden;
}

/* Cột CAILY納期 / GUIS納期: chiều ngang tối thiểu 60px */
#projectTable th.caily-nouki-column,
#projectTable td.caily-nouki-column,
#projectTable th.guis-nouki-column,
#projectTable td.guis-nouki-column,
#projectTable_wrapper .dt-scroll-head th.caily-nouki-column,
#projectTable_wrapper .dt-scroll-head th.guis-nouki-column {
    min-width: 60px;
    overflow: hidden;
}
#projectTable td.caily-nouki-column .d-flex,
#projectTable td.guis-nouki-column .d-flex {
    overflow: hidden;
}
/* Cột CAILY納期: nền xanh lá nhạt */
#projectTable td.caily-nouki-column {
    background-color: rgba(25, 135, 84, 0.25);
}

/* Cột 期限日: nền đen nhạt (xám nhạt) */
#projectTable td.end-date-column {
    background-color: rgba(255, 0, 0, 0.15);
}

[data-bs-theme="dark"] #projectTable > :not(caption) > * > *,
[data-bs-theme="dark"] .bg-label-secondary{
    color: #eee!important;
}
[data-bs-theme="dark"] #projectTable_wrapper thead th{
   background-color: #333!important;
   color: #eee!important;
}
[data-bs-theme="dark"] .confirmation-note-item,
[data-bs-theme="light"] #projectTable > :not(caption) > * > *{
    color: #333!important;
}


/* Kadai Queue Container */
.kadai-queue-container {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 50%;
    background: #fff;
    border-top: 3px solid #fd7e14;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
    z-index: 9000;
    transition: all 0.3s ease;
}

/* Responsive design for kadai queue */
@media (max-width: 768px) {
    .kadai-queue-header .d-flex.align-items-center {
        flex-wrap: wrap;
        gap: 0.5rem;
    }
}

.kadai-queue-header {
    padding: 12px 20px;
    background: linear-gradient(135deg, #fd7e14 0%, #ff8c42 100%);
    color: white;
    cursor: pointer;
    user-select: none;
    transition: background 0.3s ease;
}

.kadai-queue-header:hover {
    background: linear-gradient(135deg, #e86a0a 0%, #fd7e14 100%);
}

.kadai-queue-content {
    max-height: 300px;
    overflow-y: auto;
    background: #fff;
    border-top: 1px solid #e9ecef;
}

.kadai-queue-list {
    padding: 0;
}

.kadai-queue-item {
    padding: 15px 20px;
    border-bottom: 1px solid #f8f9fa;
    transition: background-color 0.2s ease;
}

.kadai-queue-item:hover {
    background-color: #f8f9fa;
}

.kadai-queue-item:last-child {
    border-bottom: none;
}

.kadai-queue-item h6 {
    font-size: 0.9rem;
    margin: 0;
    color: #495057;
}

.kadai-queue-item .badge {
    font-size: 0.7rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .kadai-queue-container {
        left: 0;
        right: 0;
    }
    
    .kadai-queue-header {
        padding: 10px 15px;
    }
    
    .kadai-queue-item {
        padding: 12px 15px;
    }
    
    .kadai-queue-item .btn-group {
        flex-direction: column;
    }
    
    .kadai-queue-item .btn-group .btn {
        margin-bottom: 2px;
    }
}

/* Animation for queue expansion */
.kadai-queue-content {
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Order Type Badge Styling */
#projectTable .badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    margin-right: 0.25rem;
    margin-bottom: 0.25rem;
    display: inline-block;
}

#projectTable .badge:last-child {
    margin-right: 0;
}

/* Confirmation notes column */
#projectTable td.confirmation-notes-column {
    min-width: 120px;
    overflow: hidden;
}

/* Style for each confirmation note item */
#projectTable .confirmation-note-item {
    background-color: #fff9e6;
    padding: 4px 4px;
    margin-bottom: 6px !important;
    border-radius: 4px;
}
#projectTable .confirmation-note-item p {
    margin-bottom: 0!important;
}

#projectTable .confirmation-note-item p + p{
    margin-top: 1em!important;
}

#projectTable .confirmation-note-item.important-note {
    border: 1px solid #dc3545;
}

/* Layout for confirmation note content & action icons */
#projectTable td.confirmation-notes-column .confirmation-note-item {
    position: relative;
    display: block;
    cursor: pointer;
}

#projectTable td.confirmation-notes-column .confirmation-note-item .note-text {
    display: block;
}


#projectTable td.confirmation-notes-column .confirmation-note-item .note-text blockquote{
    font-size: 0.8125rem;
    padding-left: 8px;
}


#projectTable td.confirmation-notes-column .note-text.ql-editor a {
    color: var(--bs-primary);
    text-decoration: underline;
}

#projectTable td.confirmation-notes-column .confirmation-note-item .note-actions {
    position: absolute;
    right: 6px;
    top: 4px;
    transform: none;
    display: inline-flex;
    align-items: center;
    gap: 2px;
    pointer-events: none;
}

#projectTable td.confirmation-notes-column .confirmation-note-item .note-delete-icon {
    pointer-events: auto;
    cursor: pointer;
}

/* Highlight note being edited */
#projectTable td.confirmation-notes-column .confirmation-note-item.editing-note {
    border: 2px solid #007bff !important;
    border-radius: 4px;
    background-color: rgba(0, 123, 255, 0.1) !important;
    box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
}

/* Empty notes cell styling */
#projectTable td.confirmation-notes-column .empty-notes-cell {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 30px;
    padding: 6px 8px;
    border-radius: 4px;
    transition: background-color 0.2s ease;
}

#projectTable td.confirmation-notes-column .empty-notes-cell:hover {
    background-color: rgba(0, 123, 255, 0.05);
    cursor: pointer;
}

#projectTable td.confirmation-notes-column .empty-notes-cell .add-note-icon {
    font-size: 14px;
    pointer-events: none;
}

/* Row background colors based on status (70% lighter = 30% opacity) */
#projectTable tbody tr.table-row-status-secondary {
    background-color: rgba(108, 117, 125, 0) !important;
}

#projectTable tbody tr.table-row-status-info {
    background-color: rgba(13, 202, 240, 0.2) !important;
}

#projectTable tbody tr.table-row-status-primary {
    background-color: rgba(13, 110, 253, 0.2) !important;
}

#projectTable tbody tr.table-row-status-success {
    background-color: rgba(25, 135, 84, 0.2) !important;
}

#projectTable tbody tr.table-row-status-warning {
    background-color: rgba(255, 193, 7, 0.2) !important;
}

#projectTable tbody tr.table-row-status-waiting-documents {
    background-color: rgba(255, 193, 7, 0.08) !important;
}

#projectTable tbody tr.table-row-status-danger {
    background-color: rgba(220, 53, 69, 0.2) !important;
}

#projectTable tbody tr.table-row-status-secondary:hover,
#projectTable tbody tr.table-row-status-info:hover,
#projectTable tbody tr.table-row-status-primary:hover,
#projectTable tbody tr.table-row-status-success:hover,
#projectTable tbody tr.table-row-status-warning:hover,
#projectTable tbody tr.table-row-status-waiting-documents:hover,
#projectTable tbody tr.table-row-status-danger:hover {
    background-color: inherit;
    opacity: 0.8;
}

/* Row hover: outline màu để nhận diện */
#projectTable tbody tr:hover {
    outline: 2px solid var(--bs-primary);
    outline-offset: -2px;
}
#projectTable tbody tr.table-row-status-secondary:hover, #projectTable tbody tr.table-row-status-info:hover, #projectTable tbody tr.table-row-status-primary:hover, #projectTable tbody tr.table-row-status-success:hover, #projectTable tbody tr.table-row-status-warning:hover, #projectTable tbody tr.table-row-status-waiting-documents:hover, #projectTable tbody tr.table-row-status-danger:hover{
    opacity: 1!important;
}
#projectFilterBox .card-body{
    padding-left: 0.5rem!important;
    padding-right: 0.5rem!important;
}
#projectTableCard .card-body{
    padding-left: 0.5rem!important;
    padding-right: 0.5rem!important;
}

/* Active indicator dot for status filter buttons */
.btn-group {
    overflow: visible !important;
}

.status-filter-btn {
    position: relative;
    overflow: visible;
}

.status-filter-btn::after {
    content: '';
    position: absolute;
    bottom: -1rem;
    left: 50%;
    transform: translateX(-50%);
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: currentColor;
    z-index: 10;
    pointer-events: none;
    display: none;
}
.status-filter-btn.active::after{
    display: block;
}
/* Color for each status - handle both btn-* and btn-label-* classes when active */
.status-filter-btn.btn-secondary.active::after,
.status-filter-btn.btn-label-secondary.active::after {
    background-color: #6c757d !important;
}

.status-filter-btn.btn-info.active::after,
.status-filter-btn.btn-label-info.active::after {
    background-color: #0dcaf0 !important;
}

.status-filter-btn.btn-primary.active::after,
.status-filter-btn.btn-label-primary.active::after {
    background-color: #7650b0 !important;
}

.status-filter-btn.btn-success.active::after,
.status-filter-btn.btn-label-success.active::after {
    background-color: #198754 !important;
}

.status-filter-btn.btn-warning.active::after,
.status-filter-btn.btn-label-warning.active::after {
    background-color: #ffc107 !important;
}

.status-filter-btn.btn-danger.active::after,
.status-filter-btn.btn-label-danger.active::after {
    background-color: #dc3545 !important;
}
.pagination{
    justify-content: flex-end;
}
.project-list-dt-top-right .dataTables_paginate,
.project-list-dt-top-right .dt-paging {
    margin-top: 0;
}
#projectListColumnToolsRow {
    min-height: 2rem;
}
/* Quick edit: manager-only mode chỉ hiện ステータス, 進捗率, チーム, 管理, メンバー, 予定工程 */
#quickEditProjectForm.quick-edit-manager-only-mode .quick-edit-full-only {
    display: none !important;
}
#quickEditYoteiWrap {
    display: block !important;
}
#quickEditProjectForm .is-invalid + .invalid-feedback,
#quickEditProjectForm .invalid-feedback:not(:empty) {
    display: block;
}
body.is-caily-branch-user #quickEditProjectForm .quick-edit-guis-field {
    display: none !important;
}
body.is-caily-branch-user #quickEditProjectForm #quickEditTantouWrap {
    display: none !important;
}
body.is-caily-branch-user #quickEditProjectForm #quickEditTantouDisplayText {
    display: block !important;
}
body.is-caily-branch-user:not(.can-view-end-date) #quickEditStatus option[value="completed"] {
    display: none;
}

/* Tagify 受注形態 (quick edit) — giống parent_project 案件依頼編集 */
.tags-look-project-order-type .tagify__dropdown__item {
    display: inline-block;
    border-radius: 3px;
    padding: 0.3em 0.5em;
    border: 1px solid #CCC;
    background: #F3F3F3;
    margin: 0.2em;
    font-size: 0.85em;
    color: black;
}
.tags-look-project-order-type .tagify__dropdown__item--active {
    color: black;
}
.tags-look-project-order-type .tagify__dropdown__item:hover {
    background: lightyellow;
    border-color: gold;
}
#quickEditProjectModal .tagify__dropdown {
    z-index: 1090;
}
#quickEditProjectForm .tagify.is-invalid {
    border-color: #ff3e1d;
}

/* Keep Select2 multiple filter boxes compact */
#projectFilterForm .select2-container .select2-selection--multiple .select2-selection__rendered {
    max-height: 3.2rem;
    overflow-y: auto;
}
body > .select2-container--default,
.select2-dropdown{
    width: 300px!important;
}

/* Shepherd Tour floating button — left of holiday button (right:198) */
#projectListTourBtn {
    width: 36px;
    height: 36px;
    bottom: 10px;
    right: 186px;
    z-index: 1000;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
#projectListTourBtn .fas {
    font-size: 0.95rem;
}
.shepherd-modal-overlay-container {
    z-index: 10040 !important;
}
.shepherd-element {
    z-index: 10050 !important;
}
</style>

<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/datatables-colreorder/colReorder.bootstrap5.min.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/tagify/tagify.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/quill/typography.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/quill/editor.css?v=<?=CACHE_VERSION?>" />
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/shepherd/shepherd.css" />
<script src="<?=ROOT?>assets/vendor/libs/quill/quill.js"></script>
<script src="<?=ROOT?>assets/vendor/libs/datatables-colreorder/dataTables.colReorder.min.js"></script>
<script src="<?=ROOT?>assets/vendor/libs/datatables-colreorder/colReorder.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/shepherd.js@10.0.1/dist/js/shepherd.min.js"></script>
<!-- Chat page context: AI can use current project list data -->
<script>
window.__chatPageContext = window.__chatPageContext || {};
window.__chatPageContext.page = 'project_list';
window.IS_CAILY_BRANCH_USER = <?php echo $isCailyBranchUser ? 'true' : 'false'; ?>;
if (window.IS_CAILY_BRANCH_USER) {
    document.body.classList.add('is-caily-branch-user');
}
</script>
<?php if (!empty($_SESSION['show_project'])): ?>
<script src="<?=ROOT?>assets/js/customer-global-modal.js?v=<?=CACHE_VERSION?>"></script>
<?php endif; ?>
<script src="assets/js/project-clipboard.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="assets/js/business-document-modal-mixin.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="assets/js/yotei-field.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="assets/js/energy-drawing-share.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="assets/js/project-list.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="assets/js/project-list-tour.js?v=<?=PROJECT_CACHE_VERSION?>"></script>