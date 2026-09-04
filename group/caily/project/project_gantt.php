<?php

require_once('../application/loader.php');
$view->heading('プロジェクトガントチャート');
$isCailyBranchUser = false;
try {
    require_once('../application/model/branch.php');
    $branchModel = new Branch();
    $branch = $branchModel->get_user_branch_name();
    if ($branch && isset($branch['name']) && $branch['name'] === 'CAILY') {
        $isCailyBranchUser = true;
    }
} catch (Exception $e) {
    // fallback
}
if($_SESSION['show_project'] == 0){
    echo '<div class="container-fluid mt-4"><div class="alert alert-danger">権限がありません。</div></div>';
    exit;
}
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
            <div class="col-md-3 col-6">
              <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="納品状況">納品状況</label>
              <select class="form-select form-select-sm" id="filterDeliveryStatus">
                <option value="" data-i18n="すべて">すべて</option>
                <option value="納品済み" data-i18n="納品済み">納品済み</option>
                <option value="未納品" data-i18n="未納品">未納品</option>
              </select>
            </div>
            <div class="col-md-3 col-6">
              <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="予定工程">予定工程</label>
              <input type="text" class="form-control form-control-sm" id="filterYoteiMonth" autocomplete="off">
            </div>
            <div class="col-md-4 col-12">
              <label class="form-label form-label-sm mb-0 text-nowrap" data-i18n="キーワード">キーワード</label>
              <input type="text" class="form-control form-control-sm" id="filterKeyword" placeholder="検索...">
            </div>
            <div class="col-md-2 col-6">
              <label class="form-label form-label-sm mb-0 text-nowrap">案件ID</label>
              <input type="text" class="form-control form-control-sm" id="filterProjectId" placeholder="ID">
            </div>
            <div class="col-12 d-flex align-items-end">
              <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="form-check mb-0 form-switch">
                  <input class="form-check-input" type="checkbox" id="filterMyProjects">
                  <label class="form-check-label" for="filterMyProjects" data-i18n="私の案件">私の案件</label>
                </div>
                <div class="form-check mb-0 form-switch">
                  <input class="form-check-input" type="checkbox" id="filterNoDates">
                  <label class="form-check-label" for="filterNoDates" data-i18n="開始日・終了日未設定">開始日・終了日未設定</label>
                </div>
                <div class="form-check mb-0 form-switch">
                  <input class="form-check-input" type="checkbox" id="showInactiveSwitch">
                  <label class="form-check-label" for="showInactiveSwitch" data-i18n="完了・中止案件等も表示">完了・中止案件等も表示</label>
                </div>
                <button class="btn btn-sm btn-outline-primary" id="filterReset" type="button">
                  <i class="fa fa-undo me-1"></i><span data-i18n="リセット">リセット</span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="projectGanttFilterResetPrefsBtn"
                        data-bs-toggle="offcanvas" data-bs-target="#offcanvasProjectGanttFilterResetPrefs"
                        aria-controls="offcanvasProjectGanttFilterResetPrefs" title="フィルター設定">
                  <i class="fa fa-sliders-h me-1"></i><span data-i18n="フィルター設定">フィルター設定</span>
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

<!-- Offcanvas: Gantt reset filter preferences (left side) -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasProjectGanttFilterResetPrefs"
     aria-labelledby="offcanvasProjectGanttFilterResetPrefsLabel" style="width: min(360px, 92vw);">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="offcanvasProjectGanttFilterResetPrefsLabel">
            <i class="fa fa-sliders-h me-2"></i><span data-i18n="フィルター設定">フィルター設定</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="閉じる"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
        <p class="text-muted small mb-3" data-i18n="チェックした項目は「リセット」後も値が保持されます。">
            チェックした項目は「リセット」後も値が保持されます。
        </p>
        <div id="projectGanttFilterResetPrefsList" class="flex-grow-1 overflow-auto"></div>
        <div class="d-flex gap-2 mt-3 pt-3 border-top">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="projectGanttFilterResetPrefsSelectAll">
                <span data-i18n="すべて選択">すべて選択</span>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="projectGanttFilterResetPrefsClearAll">
                <span data-i18n="すべて解除">すべて解除</span>
            </button>
        </div>
    </div>
</div>

    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">ガントチャート - {{ selectedDepartment ? selectedDepartment.name : '部署を選択してください' }}</h5>
               
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <!-- Status Filter -->
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
                        </button>
                    </div>

                    <!-- Sort Controls -->
                    <div class="dropdown project-gantt-sort-dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="projectGanttSortDropdown"
                                data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                            <i class="fa fa-sort me-1"></i><span data-i18n="並べ替え">並べ替え</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end p-3 project-gantt-sort-dropdown-menu" aria-labelledby="projectGanttSortDropdown" style="min-width: 260px;">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="ganttSortByStatusSwitch" checked>
                                <label class="form-check-label" for="ganttSortByStatusSwitch" data-i18n="ステータス順で並べ替え">ステータス順で並べ替え</label>
                            </div>
                            <div class="mb-2">
                                <label class="form-label form-label-sm mb-1" for="projectGanttSortColumn" data-i18n="並べ替え項目">並べ替え項目</label>
                                <select class="form-select form-select-sm" id="projectGanttSortColumn">
                                    <option value="" data-i18n="デフォルト">デフォルト</option>
                                    <option value="yotei" data-i18n="予定工程">予定工程</option>
                                    <option value="start_date" data-i18n="開始日">開始日</option>
                                    <option value="caily_nouki" data-i18n="CAILY納期">CAILY納期</option>
                                    <option value="guis_nouki" data-end-date-perm="1" data-i18n="GUIS納期"<?php echo $isCailyBranchUser ? ' hidden' : ''; ?>>GUIS納期</option>
                                    <option value="end_date" data-end-date-perm="1" data-i18n="期限日"<?php echo $isCailyBranchUser ? ' hidden' : ''; ?>>期限日</option>
                                    <option value="estimate_date" data-director-only="1" data-i18n="見積日">見積日</option>
                                    <option value="invoice_date" data-director-only="1" data-i18n="請求日">請求日</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label form-label-sm mb-1" for="projectGanttSortDir" data-i18n="並び順">並び順</label>
                                <select class="form-select form-select-sm" id="projectGanttSortDir">
                                    <option value="asc" data-i18n="昇順">昇順</option>
                                    <option value="desc" data-i18n="降順">降順</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="activeFilters" class="mb-2"></div>
            <div class="d-flex gap-2 align-items-center">
                <!-- Date Range Controls -->
                <div class="d-flex align-items-center gap-2 me-3">
                    <label class="form-label mb-0 small">表示期間:</label>
                    <input type="date" class="form-control form-control-sm start_date" style="width: 140px;" @change="changeDates">
                    <span class="text-muted">–</span>
                    <input type="date" class="form-control form-control-sm end_date" style="width: 140px;" @change="changeDates">
                    <button type="button" class="btn btn-outline-primary btn-sm" @click="scrollToTodayMinus7" title="今日-7日付近へスクロール">
                        <i class="fa fa-calendar-check-o me-1"></i>今日付近
                    </button>
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
                <!-- <div class="btn-group me-2">
                    <button class="btn btn-outline-secondary btn-sm" @click="zoomOut" title="ズームアウト">
                        <i class="fa fa-search-minus"></i>
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" @click="zoomIn" title="ズームイン">
                        <i class="fa fa-search-plus"></i>
                    </button>
                </div> -->
                
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
                <div class="form-check ms-2">
                    <input class="form-check-input" type="checkbox" id="toggleTaskText" checked>
                    <label class="form-check-label small" for="toggleTaskText"><span data-i18n="案件名を表示">案件名を表示</span></label>
                </div>
                <div class="form-check ms-2">
                    <input class="form-check-input" type="checkbox" id="toggleTaskTree">
                    <label class="form-check-label small" for="toggleTaskTree"><span data-i18n="各納期を表示">各納期を表示</span></label>
                </div>
            </div>
            <div class="mt-2 d-flex gap-2 align-items-center justify-content-between">
              <div class="d-flex flex-wrap align-items-center gap-2">
                <!-- CAILY納期 -->
                  <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="useCailyEndDate" checked>
                      <label class="form-check-label small" for="useCailyEndDate">CAILY納期を表示</label>
                  </div>
                  <div class="form-check ms-2" id="useGuisEndDateWrap" data-end-date-perm="1"<?php echo $isCailyBranchUser ? ' style="display:none"' : ''; ?>>
                      <input class="form-check-input" type="checkbox" id="useGuisEndDate" checked>
                      <label class="form-check-label small" for="useGuisEndDate">GUIS納期を表示</label>
                  </div>
                  <div class="form-check ms-2" id="useEndDateWrap" data-end-date-perm="1"<?php echo $isCailyBranchUser ? ' style="display:none"' : ''; ?>>
                      <input class="form-check-input" type="checkbox" id="useEndDate" checked>
                      <label class="form-check-label small" for="useEndDate">期限日を表示</label>
                  </div>
                  <div class="form-check ms-2">
                      <input class="form-check-input" type="checkbox" id="useShowCailyStruct" checked>
                      <label class="form-check-label small" for="useShowCailyStruct">構造データ送付 (CAILY)を表示</label>
                  </div>
                  <div class="form-check ms-2">
                      <input class="form-check-input" type="checkbox" id="useShowGuisStruct" checked>
                      <label class="form-check-label small" for="useShowGuisStruct">構造データ送付 (GUIS)を表示</label>
                  </div>
                  <div class="form-check ms-2">
                      <input class="form-check-input" type="checkbox" id="useShowEquipmentNouki" checked>
                      <label class="form-check-label small" for="useShowEquipmentNouki">設備 納期を表示</label>
                  </div>
                </div>
                <div class="ms-2 small text-muted self-end">
                    <div data-i18n="※Spaceキーを押しながらドラッグでチャートをスクロール">※Spaceキーを押しながらドラッグでチャートをスクロール</div>
                    <!-- <div title="Ctrlキーを押しながらホイールでズーム">※Ctrl＋ホイールでズーム</div> -->
                </div>
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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/dhtmlx-gantt@9.1.4/codebase/dhtmlxgantt.min.css" type="text/css">
<script src="https://cdn.jsdelivr.net/npm/dhtmlx-gantt@9.1.4/codebase/dhtmlxgantt.min.js"></script> 

<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script>
window.IS_CAILY_BRANCH_USER = <?php echo $isCailyBranchUser ? 'true' : 'false'; ?>;
</script>
<script src="assets/js/project-gantt.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<style>
body > .select2-container--default,
.select2-dropdown{
    width: 300px!important;
}
.btn-group {
    overflow: visible !important;
}
.project-gantt-sort-dropdown {
    position: relative;
    z-index: 100000;
}
.project-gantt-sort-dropdown .dropdown-menu,
.project-gantt-sort-dropdown-menu {
    z-index: 100000 !important;
}
.project-gantt-sort-dropdown select {
    position: relative;
    z-index: 100000;
}
.card > .card-header {
    overflow: visible;
    position: relative;
    z-index: 1001;
}
.status-filter-btn {
    position: relative;
    overflow: visible;
}
.status-filter-btn::after {
    content: '';
    position: absolute;
    bottom: -0.6rem;
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
</style>
