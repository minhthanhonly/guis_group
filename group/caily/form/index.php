<?php require_once('../application/loader.php'); $view->heading('申請一覧'); ?>
<div id="app" class="container-fluid mt-4 mb-5" v-cloak>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid pl-0">
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#formNavbarContent" aria-controls="formNavbarContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse justify-content-start" id="formNavbarContent">
        <!-- Inline tabs (khi đủ chiều ngang) -->
        <ul v-show="!navUseDropdown" ref="navTabsEl" class="navbar-nav me-auto mb-2 mb-lg-0 flex-wrap">
          <li class="nav-item" v-for="tab in visibleTabs" :key="tab.type" :class="{ 'active bg-primary text-white rounded-3': currentTab === tab.type }">
            <a href="#" class="nav-link d-flex align-items-center text-nowrap" @click.prevent="selectTab(tab.type)">
              <span>{{ tab.label }}</span>
              <span v-if="pendingCounts[tab.type] > 0" class="badge rounded-pill bg-warning text-dark ms-2">
                {{ pendingCounts[tab.type] }}
              </span>
            </a>
          </li>
        </ul>
        <!-- Dropdown (khi thiếu chiều ngang) -->
        <div v-show="navUseDropdown" class="dropdown me-auto mb-2 mb-lg-0">
          <button class="btn btn-outline-light dropdown-toggle text-nowrap" type="button" id="formTabDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            {{ currentTabLabel }}
            <span v-if="pendingCounts[currentTab] > 0" class="badge rounded-pill bg-warning text-dark ms-2">{{ pendingCounts[currentTab] }}</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-dark form-tab-dropdown-menu" aria-labelledby="formTabDropdown">
            <li v-for="tab in visibleTabs" :key="tab.type">
              <a class="dropdown-item d-flex align-items-center justify-content-between" href="#" @click.prevent="selectTab(tab.type); closeTabDropdown()" :class="{ 'active': currentTab === tab.type }">
                <span>{{ tab.label }}</span>
                <span v-if="pendingCounts[tab.type] > 0" class="badge rounded-pill bg-warning text-dark">{{ pendingCounts[tab.type] }}</span>
              </a>
            </li>
          </ul>
        </div>
        <div class="d-flex gap-2">
          <button v-if="currentTab !== 'all'" class="btn btn-primary text-nowrap" @click="openForm">
            <i class="fa fa-plus me-1"></i>新規申請
          </button>
        </div>
      </div>
    </div>
  </nav>
  <div class="card" id="requestTableCard">
    <div class="card-body position-relative">
      <div v-if="loading" class="position-absolute top-0 start-0 end-0 bottom-0 d-flex align-items-center justify-content-center bg-white bg-opacity-90 rounded" style="z-index: 100;">
        <div class="text-center">
          <div class="spinner-border text-primary mb-2" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
          </div>
          <div class="text-muted">データを読み込み中...</div>
        </div>
      </div>
      <div v-if="actionLoading" class="position-absolute top-0 start-0 end-0 bottom-0 d-flex align-items-center justify-content-center bg-white bg-opacity-75 rounded" style="z-index: 101;">
        <div class="text-center">
          <div class="spinner-border text-primary mb-2" role="status" style="width: 3rem; height: 3rem;"></div>
          <div class="text-muted">処理中...</div>
        </div>
      </div>
      <div class="row mb-3 align-items-end">
        <div class="col-md-2">
          <label for="form-month-input" class="col-form-label col-form-label-sm">年月</label>
          <div class="d-flex align-items-center gap-1">
            <input type="text" class="form-control form-control-sm" id="form-month-input" ref="formMonthInput" readonly placeholder="すべて" :value="monthFilter">
            <button v-if="monthFilter" type="button" class="btn btn-outline-secondary btn-sm" @click="clearMonthFilter" title="フィルターを解除">×</button>
          </div>
        </div>
        <div class="col-md-3" v-if="canUseUserFilter">
          <label class="col-form-label col-form-label-sm d-block">&nbsp;</label>
          <select class="form-select form-select-sm" v-model="userFilter" @change="onFilterChange">
            <option value="">ユーザー: すべて</option>
            <option v-for="u in userFilterOptions" :key="u.userid" :value="u.userid">
              {{ formatUserDisplayName(u) }}
            </option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="col-form-label col-form-label-sm d-block">&nbsp;</label>
          <div class="input-group input-group-sm">
            <input type="text" class="form-control" v-model="keyword" placeholder="ユーザー名、ユーザーID、事由、備考で検索..." @input="onSearchInput" @keyup.enter="onSearch">
            <button class="btn btn-outline-secondary" type="button" @click="clearSearch">
              <i class="fa fa-times"></i>
            </button>
          </div>
        </div>
        <div class="col-md-3">
          <label class="col-form-label col-form-label-sm d-block">&nbsp;</label>
          <select class="form-select form-select-sm" v-model="statusFilter" @change="onFilterChange">
            <option value="">状態: すべて</option>
            <option value="pending">申請中</option>
            <option value="approved">承認済（総務対応待ち）</option>
            <option value="rejected">却下</option>
            <option value="completed">処理完了</option>
          </select>
        </div>
       
        <div class="col-md-auto">
          <label class="col-form-label col-form-label-sm d-block">&nbsp;</label>
          <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
            <div class="form-check form-check-sm mb-0">
              <input class="form-check-input" type="checkbox" id="showDraftsCheckbox" v-model="showDrafts" @change="onFilterChange">
              <label class="form-check-label" for="showDraftsCheckbox">下書きも表示</label>
            </div>
            <div v-if="currentUserRole === 'administrator' || currentUserIsSoumu" class="form-check form-check-sm mb-0">
              <input class="form-check-input" type="checkbox" id="filterAssignedApproverCheckbox" v-model="filterAssignedApprover" @change="onFilterChange">
              <label class="form-check-label text-nowrap" for="filterAssignedApproverCheckbox">承認者(指定)が自分</label>
            </div>
          </div>
        </div>
      </div>
      <div v-if="!loading && requests.length === 0" class="text-muted text-center py-5">まだ申請がありません。</div>
      <div v-else-if="!loading && currentTab === 'all'" class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>申請者</th>
              <th>申請種別</th>
              <th class="user-select-none" style="cursor:pointer;" @click="changeSort('created_at')">
                申請日
                <i class="fa fa-fw" :class="sortIcon('created_at')"></i>
              </th>
              <th>注記</th>
              <th>承認者(指定)</th>
              <th>コメント数</th>
              <th>承認者</th>
              <th class="user-select-none" style="cursor:pointer;" @click="changeSort('approved_at')">
                承認日時
                <i class="fa fa-fw" :class="sortIcon('approved_at')"></i>
              </th>
              <th class="user-select-none" style="cursor:pointer;" @click="changeSort('status')">
                状態
                <i class="fa fa-fw" :class="sortIcon('status')"></i>
              </th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="req in requests" :key="req.id">
              <td>{{ req.user_realname || req.user_id || '-' }}</td>
              <td class="text-nowrap">{{ requestTypeLabel(req) }}</td>
              <td class="text-nowrap">{{ formatDateTime(req.created_at) }}</td>
              <td>{{ req.data?.note || '-' }}</td>
              <td>{{ req.approver_user_realname || req.approver_user_id || '-' }}</td>
              <td>{{ req.comment_count }}</td>
              <td>{{ req.status === 'approved' && req.approver_realname ? req.approver_realname : '-' }}</td>
              <td>{{ req.status === 'approved' && req.approved_at ? formatDateTime(req.approved_at) : '-' }}</td>
              <td>
                <span :class="['badge', statusBadgeClass(req.status)]">
                  <i :class="statusIcon(req.status)" class="me-1"></i>{{ statusLabel(req.status) }}
                </span>
                <div v-if="req.status === 'completed' && (req.completed_realname || req.completed_userid || req.completed_at)" class="small text-muted mt-1">
                  {{ req.completed_realname || req.completed_userid || '-' }} / {{ req.completed_at ? formatDateTime(req.completed_at) : '-' }}
                </div>
              </td>
              <td>
                <a :href="'detail.php?id=' + req.id" class="btn btn-sm btn-outline-info">詳細</a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else-if="!loading" class="table-responsive">

        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>登録者</th>
              <th v-if="currentTab === 'leave'">期間</th>
              <th v-if="currentTab === 'leave'">日間</th>
              <th v-if="currentTab === 'leave'">休暇種別</th>
              <th v-if="currentTab === 'leave'">有給休暇</th>
              <th v-if="currentTab === 'outing'">日時</th>
              <th v-if="currentTab === 'outing'">行先</th>
              <th v-if="currentTab === 'trip'">期間</th>
              <th v-if="currentTab === 'trip'">日間</th>
              <th v-if="currentTab === 'trip'">行先</th>
              <th v-if="currentTab === 'holiday_work'">日時</th>
              <th v-if="currentTab === 'overtime'">日時</th>
              <th v-if="currentTab === 'overtime'">用途</th>
              <th v-if="currentTab === 'attendance_correction'">日時</th>
              <th v-if="currentTab === 'attendance_correction'">区分</th>
              <th v-if="currentTab === 'purchase'">合計金額</th>
              <th v-if="currentTab === 'it_support'">区分</th>
              <th v-if="currentTab === 'it_support'">件名</th>
              <th v-if="currentTab === 'it_support'">緊急度</th>
              <th v-if="currentTab === 'trip_expense'">期間</th>
              <th v-if="currentTab === 'trip_expense'">出張先</th>
              <th v-if="currentTab === 'trip_expense'">精算額</th>
              <th v-if="currentTab === 'travel_expense'">合計金額</th>
              <th v-if="currentTab === 'expense'">合計金額（税込）</th>
              <th v-if="currentTab === 'commuting_allowance'">申請区分</th>
              <th v-if="currentTab === 'commuting_allowance'">適用開始日</th>
              <th v-if="currentTab === 'commuting_allowance'">合計片道運賃</th>
              <th v-if="currentTab === 'commuting_allowance'">１か月定期代</th>
              <th v-if="currentTab === 'leave' || currentTab === 'outing' || currentTab === 'trip' || currentTab === 'holiday_work' || currentTab === 'overtime' || currentTab === 'attendance_correction' || currentTab === 'purchase'">事由</th>
              <th v-if="currentTab === 'leave' || currentTab === 'outing' || currentTab === 'trip' || currentTab === 'holiday_work' || currentTab === 'overtime' || currentTab === 'attendance_correction' || currentTab === 'travel_expense' || currentTab === 'expense' || currentTab === 'trip_expense' || currentTab === 'commuting_allowance' || currentTab === 'purchase' || currentTab === 'it_support'">注記</th>
              <th v-if="currentTab === 'leave' || currentTab === 'outing' || currentTab === 'trip' || currentTab === 'holiday_work' || currentTab === 'overtime' || currentTab === 'attendance_correction' || currentTab === 'travel_expense' || currentTab === 'expense' || currentTab === 'trip_expense' || currentTab === 'commuting_allowance' || currentTab === 'purchase' || currentTab === 'it_support'">承認者(指定)</th>
              <th>コメント数</th>
              <th>承認者</th>
              <th class="user-select-none" style="cursor:pointer;" @click="changeSort('approved_at')">
                承認日時
                <i class="fa fa-fw" :class="sortIcon('approved_at')"></i>
              </th>
              <th class="user-select-none" style="cursor:pointer;" @click="changeSort('status')">
                状態
                <i class="fa fa-fw" :class="sortIcon('status')"></i>
              </th>
              <th class="user-select-none" style="cursor:pointer;" @click="changeSort('created_at')">
                申請日
                <i class="fa fa-fw" :class="sortIcon('created_at')"></i>
              </th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="req in requests" :key="req.id">
              <td>{{ req.user_realname || '-' }}</td>
              <td v-if="currentTab === 'leave'">
                {{ formatDateOnly(req.data?.start_datetime) }} ~ {{ formatDateOnly(req.data?.end_datetime) }}
              </td>
              <td v-if="currentTab === 'leave'">
                <span v-if="req.data?.days" class="badge bg-primary">{{ req.data.days }}</span>
                <span v-else>-</span>
              </td>
              <td v-if="currentTab === 'leave'">{{ leaveTypeLabel(req) }}</td>
              <td v-if="currentTab === 'leave'">{{ paidTypeLabel(req) }}</td>
              <td v-if="currentTab === 'outing'">{{ formatOutingDateTime(req) }}</td>
              <td v-if="currentTab === 'outing'">{{ req.data?.destination || '-' }}</td>
              <td v-if="currentTab === 'trip'">{{ formatDate(req.data?.start_datetime) }} ~ {{ formatDate(req.data?.end_datetime) }}</td>
              <td v-if="currentTab === 'trip'">
                <span v-if="req.data?.days" class="badge bg-primary">{{ req.data.days }}</span>
                <span v-else>-</span>
              </td>
              <td v-if="currentTab === 'trip'">{{ req.data?.destination || '-' }}</td>
              <td v-if="currentTab === 'holiday_work'">{{ formatHolidayWorkDateTime(req) }}</td>
              <td v-if="currentTab === 'overtime'">{{ formatOvertimeDateTime(req) }}</td>
              <td v-if="currentTab === 'overtime'">{{ formatOvertimePurpose(req.data?.purpose) }}</td>
              <td v-if="currentTab === 'attendance_correction'">{{ formatAttendanceCorrectionDateTime(req) }}</td>
              <td v-if="currentTab === 'attendance_correction'">{{ req.data?.correction_type || '-' }}</td>
              <td v-if="currentTab === 'purchase'">
                <span v-if="req.data && (req.data.total_amount != null && req.data.total_amount !== '')">
                  ¥{{ Number(req.data.total_amount || 0).toLocaleString() }}
                </span>
                <span v-else-if="req.data && req.data.estimated_price != null && req.data.estimated_price !== ''">
                  ¥{{ Number(req.data.estimated_price || 0).toLocaleString() }}
                </span>
                <span v-else>-</span>
              </td>
              <td v-if="currentTab === 'travel_expense'">
                <span v-if="req.data && (req.data.total_amount != null)">
                  ¥{{ Number(req.data.total_amount || 0).toLocaleString() }}
                </span>
                <span v-else>-</span>
              </td>
              <td v-if="currentTab === 'expense'">
                <span v-if="req.data && (req.data.total_with_tax != null)">
                  ¥{{ Number(req.data.total_with_tax || 0).toLocaleString() }}
                </span>
                <span v-else>-</span>
              </td>
              <td v-if="currentTab === 'commuting_allowance'">
                {{ req.data?.application_type || '-' }}
              </td>
              <td v-if="currentTab === 'commuting_allowance'">
                <span v-if="req.data && req.data.effective_from">
                  {{ formatDate(req.data.effective_from) }}
                </span>
                <span v-else>-</span>
              </td>
              <td v-if="currentTab === 'commuting_allowance'">
                <span v-if="req.data && (req.data.total_amount != null && req.data.total_amount !== '')">
                  ¥{{ Number(req.data.total_amount || 0).toLocaleString() }}
                </span>
                <span v-else>-</span>
              </td>
              <td v-if="currentTab === 'commuting_allowance'">
                <span v-if="req.data && (req.data.one_month_commuter_pass != null && req.data.one_month_commuter_pass !== '')">
                  ¥{{ Number(req.data.one_month_commuter_pass || 0).toLocaleString() }}
                </span>
                <span v-else>-</span>
              </td>
              <td v-if="currentTab === 'trip_expense'">
                <span v-if="req.data && (req.data.start_date || req.data.end_date)">
                  {{ req.data.start_date ? formatDate(req.data.start_date) : '-' }}
                  ~
                  {{ req.data.end_date ? formatDate(req.data.end_date) : '-' }}
                </span>
                <span v-else>-</span>
              </td>
              <td v-if="currentTab === 'trip_expense'">
                {{ req.data?.destination || '-' }}
              </td>
              <td v-if="currentTab === 'trip_expense'">
                <span v-if="req.data && (req.data.final_amount != null)">
                  ¥{{ Number(req.data.final_amount || 0).toLocaleString() }}
                </span>
                <span v-else>-</span>
              </td>
              <td v-if="currentTab === 'it_support'">{{ req.data?.category || '-' }}</td>
              <td v-if="currentTab === 'it_support'">{{ req.data?.subject || '-' }}</td>
              <td v-if="currentTab === 'it_support'">{{ req.data?.priority || '-' }}</td>
              <td v-if="currentTab === 'leave' || currentTab === 'outing' || currentTab === 'trip' || currentTab === 'holiday_work' || currentTab === 'overtime' || currentTab === 'attendance_correction' || currentTab === 'purchase'">{{(req.data?.reason || '-') }}</td>
              <td v-if="currentTab === 'leave' || currentTab === 'outing' || currentTab === 'trip' || currentTab === 'holiday_work' || currentTab === 'overtime' || currentTab === 'attendance_correction' || currentTab === 'travel_expense' || currentTab === 'expense' || currentTab === 'trip_expense' || currentTab === 'commuting_allowance' || currentTab === 'purchase' || currentTab === 'it_support'">{{ req.data?.note || '-' }}</td>
              <td v-if="currentTab === 'leave' || currentTab === 'outing' || currentTab === 'trip' || currentTab === 'holiday_work' || currentTab === 'overtime' || currentTab === 'attendance_correction' || currentTab === 'travel_expense' || currentTab === 'expense' || currentTab === 'trip_expense' || currentTab === 'commuting_allowance' || currentTab === 'purchase' || currentTab === 'it_support'">{{ req.approver_user_realname || req.approver_user_id || '-' }}</td>
              <td>{{ req.comment_count }}</td>
              <td>{{ req.status === 'approved' && req.approver_realname ? req.approver_realname : '-' }}</td>
              <td>{{ req.status === 'approved' && req.approved_at ? formatDateTime(req.approved_at) : '-' }}</td>
              <td>
                <span :class="['badge', statusBadgeClass(req.status)]">
                  <i :class="statusIcon(req.status)" class="me-1"></i>{{ statusLabel(req.status) }}
                </span>
                <div v-if="req.status === 'completed' && (req.completed_realname || req.completed_userid || req.completed_at)" class="small text-muted mt-1">
                  {{ req.completed_realname || req.completed_userid || '-' }} / {{ req.completed_at ? formatDateTime(req.completed_at) : '-' }}
                </div>
              </td>
              <td>{{ formatDateTime(req.created_at) }}</td>
              <td>
                <a :href="'detail.php?id=' + req.id" class="btn btn-sm btn-outline-info me-1">詳細</a>
                <button type="button" class="btn btn-sm btn-outline-secondary me-1" @click="openPrint(req)" title="印刷">
                  <i class="fa fa-print"></i> 印刷
                </button>
                <button v-if="canDelete(req)" type="button" class="btn btn-sm btn-outline-danger" @click="deleteRequest(req)" title="削除">削除</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="!loading && requests.length > 0" class="d-flex justify-content-between align-items-center mt-3">
        <div class="text-muted small">
          全 {{ total }} 件中 {{ pageStart }}-{{ pageEnd }} 件を表示
        </div>
        <nav v-if="totalPages > 1">
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item" :class="{ disabled: page === 1 }">
              <a class="page-link" href="#" @click.prevent="goToPage(page - 1)">前へ</a>
            </li>
            <li v-for="p in visiblePages" :key="p" class="page-item" :class="{ active: Number(p) === Number(page) }">
              <a class="page-link" href="#" @click.prevent="goToPage(p)">{{ p }}</a>
            </li>
            <li class="page-item" :class="{ disabled: page === totalPages }">
              <a class="page-link" href="#" @click.prevent="goToPage(page + 1)">次へ</a>
            </li>
          </ul>
        </nav>
      </div>
    </div>
  </div>
  <!-- Modal đăng ký mới -->
  <div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog" :class="modalDialogClass">
      <div class="modal-content">
        <component :is="currentFormComponent" @submitted="onFormSubmitted" @close="closeForm"></component>
      </div>
    </div>
  </div>
  <!-- 印刷用（編集モーダルと同じフォーム構成） -->
  <div v-if="printTarget" id="printArea" class="request-print-area">
    <div class="request-print-sheet">
      <div class="request-print-meta mb-3 pb-2 border-bottom">
        <h4 class="mb-2">{{ printTypeLabel(printTarget) }}</h4>
        <div class="row g-1 small">
          <div class="col-sm-6"><strong>申請者:</strong> {{ printTarget.user_realname || printTarget.user_id }} ({{ printTarget.user_id }})</div>
          <div class="col-sm-6"><strong>申請日:</strong> {{ formatDateTime(printTarget.created_at) }}</div>
          <div class="col-sm-6"><strong>状態:</strong> {{ statusLabel(printTarget.status) }}</div>
          <div class="col-sm-6" v-if="printTarget.status === 'approved' && printTarget.approver_realname">
            <strong>承認者:</strong> {{ printTarget.approver_realname }}
          </div>
          <div class="col-sm-6" v-if="printTarget.status === 'approved' && printTarget.approved_at">
            <strong>承認日時:</strong> {{ formatDateTime(printTarget.approved_at) }}
          </div>
          <div class="col-sm-6" v-if="printTarget.status === 'completed' && (printTarget.completed_realname || printTarget.completed_userid)">
            <strong>処理完了者:</strong> {{ printTarget.completed_realname || printTarget.completed_userid }}
          </div>
          <div class="col-sm-6" v-if="printTarget.status === 'completed' && printTarget.completed_at">
            <strong>処理完了日時:</strong> {{ formatDateTime(printTarget.completed_at) }}
          </div>
        </div>
      </div>
      <component
        v-if="printFormComponent"
        :is="printFormComponent"
        class="request-print-form"
        :default-data="printFormData"
        mode="print"
        @close="finishPrint"
      ></component>
    </div>
  </div>
</div>
<style>
  .table th {
    background-color: #f8f9fa;
    border-top: none;
    font-weight: 600;
    color: #495057;
}
#requestTableCard .table td { vertical-align: middle; padding: 0.5rem; }
#requestTableCard .table thead th { padding: 0.5rem; }
#requestTableCard .table tbody tr:hover { outline: 2px solid var(--bs-primary); outline-offset: -2px; }
.form-tab-dropdown-menu { max-height: 70vh; overflow-y: auto; }
.modal-xl {
  --bs-modal-width: 1140px;
}
.detail-table td,
.detail-table th{
 padding: 0.25rem;
}
.request-print-area {
  position: fixed;
  left: -10000px;
  top: 0;
  width: 1140px;
  max-width: 100%;
  background: none;
  z-index: -1;
}
.request-print-sheet,
#printArea {
  background: none !important;
}
.request-print-form .modal-header,
.request-print-form .modal-footer,
.request-print-form > .position-relative > .position-absolute,
.request-print-form .text-muted.small,
.request-print-form .request-print-hide,
.request-print-form .text-danger,
.request-print-form button,
.request-print-form .btn,
.request-print-form .btn-close {
  display: none !important;
}
.request-print-form .modal-body {
  padding: 0;
  background: none !important;
}
.request-print-form fieldset {
  border: 0;
  padding: 0;
  margin: 0;
  min-width: 0;
  background: none !important;
}
.request-print-form .request-print-meta {
  border-bottom: 1px solid #333;
  background: none !important;
}
.request-print-form .mb-3.row {
  border-bottom: 1px solid #333;
  padding-top: 0.35rem;
  padding-bottom: 0.5rem;
  margin-bottom: 0 !important;
  background: none !important;
}
.request-print-form .form-control,
.request-print-form .form-select,
.request-print-form textarea.form-control {
  border: none !important;
  padding-left: 0 !important;
  margin: 0 !important;
  background: none !important;
  box-shadow: none !important;
  height: auto !important;
  min-height: 0 !important;
  line-height: 1.5;
  color: #000 !important;
  -webkit-text-fill-color: #000 !important;
  opacity: 1 !important;
  appearance: none;
  -webkit-appearance: none;
  border-radius: 0 !important;
}
.request-print-form .form-select {
  background-image: none !important;
}
.request-print-form .form-check-input {
  display: none !important;
}
.request-print-form .form-check-inline:has(.form-check-input:not(:checked)) {
  display: none !important;
}
.request-print-form .form-check {
  padding-left: 0;
  margin-bottom: 0;
}
.request-print-form .form-check-label {
  padding-left: 0;
  color: #000 !important;
  -webkit-text-fill-color: #000 !important;
  opacity: 1 !important;
}
.request-print-form fieldset:disabled .form-check-label,
.request-print-form fieldset[disabled] .form-check-label {
  color: #000 !important;
  -webkit-text-fill-color: #000 !important;
  opacity: 1 !important;
}
.request-print-form fieldset:disabled .form-check,
.request-print-form fieldset[disabled] .form-check {
  opacity: 1 !important;
}
.request-print-form fieldset:disabled .form-check-input ~ .form-check-label,
.request-print-form fieldset[disabled] .form-check-input ~ .form-check-label,
.request-print-form fieldset:disabled .form-check-input:disabled ~ .form-check-label,
.request-print-form fieldset[disabled] .form-check-input:disabled ~ .form-check-label {
  color: #000 !important;
  -webkit-text-fill-color: #000 !important;
  opacity: 1 !important;
}
.request-print-form .detail-table th:last-child,
.request-print-form .detail-table td:last-child {
  display: none !important;
}
.request-print-form .detail-table .form-control,
.request-print-form .detail-table .form-select {
  width: 100%;
}
.request-print-form .col-form-label {
  font-weight: 600;
  color: #000 !important;
  -webkit-text-fill-color: #000 !important;
  opacity: 1 !important;
}
.request-print-meta,
.request-print-meta * {
  color: #000 !important;
  -webkit-text-fill-color: #000 !important;
  opacity: 1 !important;
}
.request-print-form .request-print-text {
  display: inline-block;
  line-height: 1.5;
  color: #000 !important;
  -webkit-text-fill-color: #000 !important;
  opacity: 1 !important;
}
/* fieldset[disabled] makes Bootstrap gray out all values — force black for print */
.request-print-form fieldset:disabled,
.request-print-form fieldset[disabled] {
  color: #000 !important;
  opacity: 1 !important;
}
.request-print-form fieldset:disabled .request-print-text,
.request-print-form fieldset[disabled] .request-print-text,
.request-print-form fieldset:disabled .form-control,
.request-print-form fieldset[disabled] .form-control,
.request-print-form fieldset:disabled .form-control-plaintext,
.request-print-form fieldset[disabled] .form-control-plaintext,
.request-print-form fieldset:disabled textarea.form-control,
.request-print-form fieldset[disabled] textarea.form-control,
.request-print-form fieldset:disabled .col-sm-9,
.request-print-form fieldset[disabled] .col-sm-9,
.request-print-form fieldset:disabled .col-sm-4,
.request-print-form fieldset[disabled] .col-sm-4,
.request-print-form fieldset:disabled .col-auto,
.request-print-form fieldset[disabled] .col-auto,
.request-print-form fieldset:disabled td,
.request-print-form fieldset[disabled] td,
.request-print-form fieldset:disabled th,
.request-print-form fieldset[disabled] th {
  color: #000 !important;
  -webkit-text-fill-color: #000 !important;
  opacity: 1 !important;
}
@media print {
  body,
  body * {
    visibility: hidden;
    background: none !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  #printArea,
  #printArea * {
    visibility: visible;
    background: none !important;
  }
  #printArea {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    z-index: 99999;
    background: none !important;
  }
  .request-print-area,
  .request-print-sheet {
    position: static;
    left: auto;
    background: none !important;
  }
  #printArea,
  #printArea .request-print-meta,
  #printArea .request-print-meta *,
  #printArea .request-print-form fieldset,
  #printArea .request-print-form fieldset * {
    color: #000 !important;
    -webkit-text-fill-color: #000 !important;
    opacity: 1 !important;
  }
}
</style>
<?php $view->footing(); ?>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="/assets/js/axios.min.js?v=<?=CACHE_VERSION?>"></script>
<script type="module">
import { formatUserDisplayName } from '/assets/js/user-display-name.js';
import { approverMultiselectMixin } from './approver-multiselect.js';
import approverSelect from './approver-select.js?v=<?=CACHE_VERSION?>';
import leaveForm from './leave-form.js?v=<?=CACHE_VERSION?>';
import outingForm from './outing-form.js?v=<?=CACHE_VERSION?>';
import tripForm from './trip-form.js?v=<?=CACHE_VERSION?>';
import holidayWorkForm from './holiday-work-form.js?v=<?=CACHE_VERSION?>';
import overtimeForm from './overtime-form.js?v=<?=CACHE_VERSION?>';
import attendanceCorrectionForm from './attendance-correction-form.js?v=<?=CACHE_VERSION?>';
import travelExpenseForm from './travel-expense-form.js?v=<?=CACHE_VERSION?>';
import expenseForm from './expense-form.js?v=<?=CACHE_VERSION?>';
import tripExpenseForm from './trip-expense-form.js?v=<?=CACHE_VERSION?>';
import commutingAllowanceForm from './commuting-allowance-form.js?v=<?=CACHE_VERSION?>';
import purchaseForm from './purchase-form.js?v=<?=CACHE_VERSION?>';
import itSupportForm from './it-support-form.js?v=<?=CACHE_VERSION?>';
const { createApp, defineAsyncComponent } = Vue;
const app = createApp({
  mixins: [approverMultiselectMixin],
  data() {
    return {
      tabs: [
        {type: 'all', label: 'すべて'},
        {type: 'leave', label: '休暇届', form: 'leave-form'},
        {type: 'outing', label: '外出申請書', form: 'outing-form'},
        {type: 'trip', label: '出張申請書', form: 'trip-form'},
        {type: 'holiday_work', label: '休日勤務申請書', form: 'holiday-work-form'},
        {type: 'overtime', label: '遅刻・早退・時間外勤務', form: 'overtime-form'},
        {type: 'attendance_correction', label: '勤怠打刻修正', form: 'attendance-correction-form'},
        {type: 'travel_expense', label: '交通費精算書', form: 'travel-expense-form'},
        {type: 'expense', label: '経費精算書', form: 'expense-form'},
        {type: 'trip_expense', label: '出張旅費精算書', form: 'trip-expense-form'},
        {type: 'commuting_allowance', label: '通勤手当申請書', form: 'commuting-allowance-form'},
        {type: 'purchase', label: '備品購入依頼書', form: 'purchase-form'},
        {type: 'it_support', label: 'ITサポート', form: 'it-support-form'},
      ],
      // 一時非表示（表示する場合は hiddenTabTypes から削除）
      hiddenTabTypes: ['travel_expense', 'expense', 'trip_expense'],
      currentTab: 'leave',
      requests: [],
      loading: false,
      currentFormComponent: null,
      keyword: '',
      searchDebounceTimer: null,
      statusFilter: '',
      userFilter: '',
      showDrafts: true,
      filterAssignedApprover: false,
      monthFilter: '', // YYYY-MM, period 21/(M-1)～20/M
      formMonthPicker: null, // flatpickr instance
      page: 1,
      perPage: 50,
      total: 0,
      totalPages: 0,
      sortBy: 'created_at',
      sortDir: 'desc',
      pendingCounts: {},
      currentUserId: (typeof USER_ID !== 'undefined') ? USER_ID : '',
      currentUserRole: (typeof USER_ROLE !== 'undefined') ? USER_ROLE : '',
      currentUserIsSoumu: (typeof USER_IS_SOUMU !== 'undefined') ? String(USER_IS_SOUMU) === '1' : false,
      userFilterOptions: [],
      actionLoading: false,
      navUseDropdown: false,
      navBreakpoint: 1200,
      _resizeHandler: null,
      printTarget: null,
      printFormComponent: null,
    }
  },
  computed: {
    printFormData() {
      if (!this.printTarget) return {};
      const d = this.printTarget.data || {};
      return Object.assign({}, d, {
        id: this.printTarget.id,
        approver_user_ids: this.normalizeApproverUserIds(this.printTarget.approver_user_id || d.approver_user_id || d.approver_user_ids || [])
      });
    },
    visibleTabs() {
      return this.tabs.filter(tab => !this.hiddenTabTypes.includes(tab.type));
    },
    currentTabLabel() {
      const t = this.visibleTabs.find(x => x.type === this.currentTab);
      return t ? t.label : '申請種別';
    },
    modalDialogClass() {
      // travel_expense 用フォームは内容が多いため、モーダルを大きくする
      if (this.currentTab === 'travel_expense' || this.currentTab === 'expense' || this.currentTab === 'trip_expense' || this.currentTab === 'commuting_allowance' || this.currentTab === 'purchase') {
        return 'modal-xl';
      }
      return 'modal-lg';
    },
    pageStart() {
      if (this.total === 0) return 0;
      return (this.page - 1) * this.perPage + 1;
    },
    pageEnd() {
      if (this.total === 0) return 0;
      return Math.min(this.total, this.page * this.perPage);
    },
    visiblePages() {
      const pages = [];
      const maxVisible = 5;
      let start = Math.max(1, this.totalPages ? this.page - Math.floor(maxVisible / 2) : 1);
      let end = Math.min(this.totalPages, start + maxVisible - 1);
      if (end - start + 1 < maxVisible) {
        start = Math.max(1, end - maxVisible + 1);
      }
      for (let i = start; i <= end; i++) {
        pages.push(i);
      }
      return pages;
    },
    canUseUserFilter() {
      return this.currentUserRole === 'administrator' || this.userFilterOptions.length > 0;
    }
  },
  methods: {
    formatUserDisplayName,
    async loadUserFilterOptions() {
      try {
        const res = await axios.get('/api/index.php?model=request&method=list_filter_users');
        const list = Array.isArray(res.data) ? res.data : [];
        this.userFilterOptions = list
          .filter(u => u && u.userid)
          .map(u => ({
            userid: String(u.userid),
            realname: u.realname || '',
            lastname: u.lastname,
            firstname: u.firstname,
            lastname_after_married: u.lastname_after_married
          }));
      } catch (e) {
        this.userFilterOptions = [];
      }
      if (!this.canUseUserFilter) {
        this.userFilter = '';
      }
    },
    formatDate(dateStr) {
      if (!dateStr) return '';
      let str = dateStr;
      if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(str)) {
        str += ':00';
      }
      const d = new Date(str);
      if (isNaN(d)) return dateStr;
      const youbi = ['日','月','火','水','木','金','土'];
      const wd = youbi[d.getDay()];
      return `${d.getFullYear()}/${(d.getMonth()+1).toString().padStart(2,'0')}/${d.getDate().toString().padStart(2,'0')}(${wd}) `;
    },
    formatDateOnly(dateStr) {
      if (!dateStr) return '';
      let str = dateStr;
      if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(str)) {
        str += ':00';
      }
      const d = new Date(str);
      if (isNaN(d)) return dateStr;
      const youbi = ['日','月','火','水','木','金','土'];
      const wd = youbi[d.getDay()];
      return `${d.getFullYear()}/${(d.getMonth()+1).toString().padStart(2,'0')}/${d.getDate().toString().padStart(2,'0')}(${wd})`;
    },
    async updatePendingCountForTab(type) {
      try {
        const statusParam = (this.currentUserRole === 'administrator' || this.currentUserIsSoumu)
          ? 'pending,approved'
          : 'pending';
        const params = new URLSearchParams({
          status: statusParam,
          page: 1,
          per_page: 1
        });
        if (type !== 'all') {
          params.append('type', type);
        }
        if (this.monthFilter && /^\d{4}-\d{2}$/.test(this.monthFilter)) {
          const [y, m] = this.monthFilter.split('-').map(Number);
          const fromDate = m === 1 ? `${y - 1}-12-21` : `${y}-${String(m - 1).padStart(2, '0')}-21`;
          const toDate = `${y}-${String(m).padStart(2, '0')}-20`;
          params.append('from_date', fromDate);
          params.append('to_date', toDate);
        }
        const res = await axios.get('/api/index.php?model=request&method=list&' + params.toString());
        const body = res.data;
        let count = 0;
        if (Array.isArray(body)) {
          count = body.length;
        } else if (body && body.pagination && typeof body.pagination.total !== 'undefined') {
          count = body.pagination.total;
        }
        this.pendingCounts = { ...this.pendingCounts, [type]: count };
      } catch {
        // ignore
      }
    },

    formatDateTime(dateStr) {
      if (!dateStr) return '';
      let str = dateStr;
      if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(str)) {
        str += ':00';
      }
      const d = new Date(str);
      if (isNaN(d)) return dateStr;
      const youbi = ['日','月','火','水','木','金','土'];
      const wd = youbi[d.getDay()];
      return `${d.getFullYear()}/${(d.getMonth()+1).toString().padStart(2,'0')}/${d.getDate().toString().padStart(2,'0')}(${wd}) ` +
        `${d.getHours().toString().padStart(2,'0')}:${d.getMinutes().toString().padStart(2,'0')}`;
    },
    formatOutingDateTime(req) {
      if (!req || !req.data) return '-';
      const d = req.data;
      if (d.date) {
        const dateObj = new Date(d.date.replace(/-/g, '/'));
        if (isNaN(dateObj)) return d.date;
        const youbi = ['日','月','火','水','木','金','土'];
        const wd = youbi[dateObj.getDay()];
        const datePart = `${dateObj.getFullYear()}/${(dateObj.getMonth()+1).toString().padStart(2,'0')}/${dateObj.getDate().toString().padStart(2,'0')}(${wd})`;
        if (d.start_time && d.end_time) return datePart + ' ' + d.start_time + '~' + d.end_time;
        return datePart;
      }
      if (d.datetime) return this.formatDateTime(d.datetime);
      return '-';
    },
    formatHolidayWorkDateTime(req) {
      if (!req || !req.data) return '-';
      const d = req.data;
      if (d.date) {
        const dateObj = new Date(d.date.replace(/-/g, '/'));
        if (isNaN(dateObj)) return d.date;
        const youbi = ['日','月','火','水','木','金','土'];
        const wd = youbi[dateObj.getDay()];
        const datePart = `${dateObj.getFullYear()}/${(dateObj.getMonth()+1).toString().padStart(2,'0')}/${dateObj.getDate().toString().padStart(2,'0')}(${wd})`;
        if (d.start_time && d.end_time) {
          const breakLabels = { '0.5': '0.5h', '1': '1h', '1.5': '1.5h', '2': '2h', '2.5': '2.5h', '3': '3h', '3.5': '3.5h', '4': '4h' };
          const minuteToHour = { 30: '0.5h', 60: '1h', 90: '1.5h', 120: '2h', 150: '2.5h', 180: '3h', 210: '3.5h', 240: '4h' };
          let breakLabel = '';
          if (d.break_time !== undefined && d.break_time !== null && d.break_time !== '') {
            const bt = String(d.break_time);
            breakLabel = breakLabels[bt] || minuteToHour[bt] || `${bt}h`;
          }
          const breakPart = breakLabel ? `（休憩${breakLabel}）` : '';
          return datePart + ' ' + d.start_time + '~' + d.end_time + breakPart;
        }
        return datePart;
      }
      if (d.datetime) return this.formatDateTime(d.datetime);
      return '-';
    },
    formatOvertimeDateTime(req) {
      if (!req || !req.data) return '-';
      const d = req.data;
      if (d.date) {
        const dateObj = new Date(d.date.replace(/-/g, '/'));
        if (isNaN(dateObj)) return d.date;
        const youbi = ['日','月','火','水','木','金','土'];
        const wd = youbi[dateObj.getDay()];
        const datePart = `${dateObj.getFullYear()}/${(dateObj.getMonth()+1).toString().padStart(2,'0')}/${dateObj.getDate().toString().padStart(2,'0')}(${wd})`;
        if (d.start_time && d.end_time) return datePart + ' ' + d.start_time + '~' + d.end_time;
        return datePart;
      }
      if (d.datetime) return this.formatDateTime(d.datetime);
      return '-';
    },
    formatOvertimePurpose(purpose) {
      if (!purpose) return '-';
      if (Array.isArray(purpose)) return purpose.join('、');
      if (typeof purpose === 'string') return purpose;
      return '-';
    },
    formatAttendanceCorrectionDateTime(req) {
      if (!req || !req.data) return '-';
      const d = req.data;
      if (d.date) {
        const dateObj = new Date(d.date.replace(/-/g, '/'));
        if (isNaN(dateObj)) return d.date;
        const youbi = ['日','月','火','水','木','金','土'];
        const wd = youbi[dateObj.getDay()];
        const datePart = `${dateObj.getFullYear()}/${(dateObj.getMonth()+1).toString().padStart(2,'0')}/${dateObj.getDate().toString().padStart(2,'0')}(${wd})`;
        if (d.time) return datePart + ' ' + d.time;
        return datePart;
      }
      return '-';
    },
    updateTabUrl(type) {
      try {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', type);
        window.history.replaceState({}, '', url.toString());
      } catch (e) { /* ignore */ }
    },
    selectTab(type) {
      this.currentTab = type;
      this.page = 1;
      this.updateTabUrl(type);
      try {
        localStorage.setItem('form_index_current_tab', type);
      } catch (e) { /* ignore */ }
      this.fetchRequests();
    },
    async fetchRequests() {
      this.loading = true;
      try {
        const params = new URLSearchParams({
          page: this.page,
          per_page: this.perPage,
          sort_by: this.sortBy,
          sort_dir: this.sortDir
        });
        if (this.currentTab !== 'all') {
          params.append('type', this.currentTab);
        }
        if (this.keyword && this.keyword.trim() !== '') {
          params.append('keyword', this.keyword.trim());
        }
        if (this.canUseUserFilter && this.userFilter) {
          params.append('user_id', this.userFilter);
        }
        if (this.statusFilter) {
          if (this.showDrafts && this.statusFilter !== 'draft') {
            params.append('status', this.statusFilter + ',draft');
          } else {
            params.append('status', this.statusFilter);
          }
        } else {
          // 「状態: すべて」のとき、下書きを含める/除外する
          if (!this.showDrafts) {
            params.append('status', 'pending,approved,rejected,completed');
          }
        }
        if (this.monthFilter && /^\d{4}-\d{2}$/.test(this.monthFilter)) {
          const [y, m] = this.monthFilter.split('-').map(Number);
          const fromDate = m === 1 ? `${y - 1}-12-21` : `${y}-${String(m - 1).padStart(2, '0')}-21`;
          const toDate = `${y}-${String(m).padStart(2, '0')}-20`;
          params.append('from_date', fromDate);
          params.append('to_date', toDate);
        }
        if ((this.currentUserRole === 'administrator' || this.currentUserIsSoumu) && this.filterAssignedApprover) {
          params.append('assigned_approver', '1');
        }
        const res = await axios.get('/api/index.php?model=request&method=list&' + params.toString());
        const body = res.data;
        if (Array.isArray(body)) {
          this.requests = body;
          this.total = body.length;
          this.totalPages = 1;
        } else {
          this.requests = Array.isArray(body.data) ? body.data : [];
          const p = body.pagination || {};
          this.total = p.total !== undefined ? p.total : this.requests.length;
          this.perPage = p.per_page || this.perPage;
          this.page = p.page || this.page;
          this.totalPages = p.total_pages || 1;
        }
      } catch {
        this.requests = [];
        this.total = 0;
        this.totalPages = 0;
      }
      this.loading = false;
      // Cập nhật badge pending cho tab hiện tại
      this.updatePendingCountForTab(this.currentTab);
    },
    onSearch() {
      this.page = 1;
      this.fetchRequests();
    },
    onSearchInput() {
      if (this.searchDebounceTimer) clearTimeout(this.searchDebounceTimer);
      this.searchDebounceTimer = setTimeout(() => {
        this.searchDebounceTimer = null;
        this.page = 1;
        this.fetchRequests();
      }, 400);
    },
    clearSearch() {
      if (this.searchDebounceTimer) {
        clearTimeout(this.searchDebounceTimer);
        this.searchDebounceTimer = null;
      }
      this.keyword = '';
      this.page = 1;
      this.fetchRequests();
    },
    initFormMonthPicker() {
      const el = this.$refs.formMonthInput;
      const fp = typeof window !== 'undefined' && window.flatpickr;
      const plugin = typeof window !== 'undefined' && window.monthSelectPlugin;
      if (!el || !fp || !plugin) return;
      if (this.formMonthPicker) {
        this.formMonthPicker.destroy();
        this.formMonthPicker = null;
      }
      const self = this;
      this.formMonthPicker = fp(el, {
        plugins: [new plugin({
          monthSelector: true,
          dateFormat: 'Y-m',
          locale: 'ja'
        })],
        dateFormat: 'Y-m',
        locale: 'ja',
        defaultDate: this.monthFilter ? this.monthFilter + '-01' : null,
        onChange(selectedDates, dateStr) {
          self.monthFilter = dateStr || '';
          self.page = 1;
          self.fetchRequests();
        }
      });
      if (this.monthFilter) {
        this.formMonthPicker.setDate(this.monthFilter + '-01', false);
      }
    },
    clearMonthFilter() {
      this.monthFilter = '';
      if (this.formMonthPicker) {
        this.formMonthPicker.clear();
      }
      this.page = 1;
      this.fetchRequests();
    },
    onMonthChange() {
      this.page = 1;
      this.fetchRequests();
    },
    onFilterChange() {
      this.page = 1;
      this.fetchRequests();
    },
    changeSort(field) {
      if (this.sortBy === field) {
        this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
      } else {
        this.sortBy = field;
        this.sortDir = 'asc';
      }
      this.page = 1;
      this.fetchRequests();
    },
    sortIcon(field) {
      if (this.sortBy !== field) return 'fa-sort';
      return this.sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
    },
    goToPage(p) {
      if (p < 1 || p > this.totalPages || p === this.page) return;
      this.page = p;
      this.fetchRequests();
    },
    leaveTypeLabel(req) {
      const t = req && req.data ? req.data.leave_type : '';
      if (t === '有給休暇') return '有給休暇';
      if (t === '無給休暇') return '無給休暇';
      return '-';
    },
    paidTypeLabel(req) {
      const p = req && req.data ? req.data.paid_type : '';
      switch (p) {
        case '全休': return '全休';
        case '午前休': return '午前休';
        case '午後休': return '午後休';
        default: return '-';
      }
    },
    printTypeLabel(req) {
      const t = this.tabs.find(x => x.type === (req && req.type));
      return t ? t.label : (req && req.type ? req.type : '');
    },
    requestTypeLabel(req) {
      return this.printTypeLabel(req) || '-';
    },
    resolveFormComponentByType(type) {
      const map = {
        leave: leaveForm,
        outing: outingForm,
        trip: tripForm,
        holiday_work: holidayWorkForm,
        overtime: overtimeForm,
        attendance_correction: attendanceCorrectionForm,
        travel_expense: travelExpenseForm,
        expense: expenseForm,
        trip_expense: tripExpenseForm,
        commuting_allowance: commutingAllowanceForm,
        purchase: purchaseForm,
        it_support: itSupportForm
      };
      return map[type] || null;
    },
    openPrint(req) {
      const component = this.resolveFormComponentByType(req.type);
      if (!component) {
        if (typeof showMessage === 'function') showMessage('この申請種別は印刷できません。', true);
        return;
      }
      this.printTarget = req;
      this.printFormComponent = component;
      this.$nextTick(() => {
        setTimeout(() => {
          window.print();
        }, 350);
      });
    },
    finishPrint() {
      this.printTarget = null;
      this.printFormComponent = null;
    },
    openForm() {
      const current = this.tabs.find(t => t.type === this.currentTab);
      if (current && current.form === 'leave-form') {
        this.currentFormComponent = leaveForm;
      } else if (current && current.form === 'outing-form') {
        this.currentFormComponent = outingForm;
      } else if (current && current.form === 'trip-form') {
        this.currentFormComponent = tripForm;
      } else if (current && current.form === 'holiday-work-form') {
        this.currentFormComponent = holidayWorkForm;
      } else if (current && current.form === 'overtime-form') {
        this.currentFormComponent = overtimeForm;
      } else if (current && current.form === 'attendance-correction-form') {
        this.currentFormComponent = attendanceCorrectionForm;
      } else if (current && current.form === 'travel-expense-form') {
        this.currentFormComponent = travelExpenseForm;
      } else if (current && current.form === 'expense-form') {
        this.currentFormComponent = expenseForm;
      } else if (current && current.form === 'trip-expense-form') {
        this.currentFormComponent = tripExpenseForm;
      } else if (current && current.form === 'commuting-allowance-form') {
        this.currentFormComponent = commutingAllowanceForm;
      } else if (current && current.form === 'purchase-form') {
        this.currentFormComponent = purchaseForm;
      } else if (current && current.form === 'it-support-form') {
        this.currentFormComponent = itSupportForm;
      } else {
        this.currentFormComponent = null;
      }
      const modal = new bootstrap.Modal(document.getElementById('formModal'));
      modal.show();
    },
    closeForm() {
      const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('formModal'));
      modal.hide();
    },
    checkNavMode() {
      this.navUseDropdown = window.innerWidth < this.navBreakpoint;
    },
    closeTabDropdown() {
      const btn = document.getElementById('formTabDropdown');
      if (btn && typeof bootstrap !== 'undefined') {
        const inst = bootstrap.Dropdown.getInstance(btn);
        if (inst) inst.hide();
      }
    },
    onFormSubmitted() {
      this.closeForm();
      this.fetchRequests();
    },
    renderSummary(req) {
      if (req.type === 'leave') return req.data?.reason || '';
      if (req.type === 'outing') return req.data?.reason || '';
      if (req.type === 'trip') return req.data?.reason || '';
      if (req.type === 'holiday_work') return req.data?.reason || '';
      if (req.type === 'overtime') return (Array.isArray(req.data?.purpose) ? req.data.purpose.join('、') : req.data?.purpose) || '';
      if (req.type === 'attendance_correction') return req.data?.reason || '';
      if (req.type === 'travel_expense') return req.data?.attachment_original || req.data?.attachment || '';
      if (req.type === 'expense') return req.data?.attachment_original || req.data?.attachment || '';
      if (req.type === 'trip_expense') return req.data?.attachment_original || req.data?.attachment || '';
      if (req.type === 'commuting_allowance') {
        const d = req.data || {};
        const appType = d.application_type ? `申請区分: ${d.application_type}` : '';
        const total = d.total_amount != null && d.total_amount !== '' ? `合計片道運賃: ¥${Number(d.total_amount || 0).toLocaleString()}` : '';
        const pass = d.one_month_commuter_pass != null && d.one_month_commuter_pass !== '' ? `１か月定期代: ¥${Number(d.one_month_commuter_pass || 0).toLocaleString()}` : '';
        return [appType, total, pass].filter(Boolean).join(' / ') || '';
      }
      return '';
    },
    statusLabel(status) {
      switch(status) {
        case 'pending': return '申請中';
        case 'approved': return '承認済（総務対応待ち）';
        case 'rejected': return '却下';
        case 'completed': return '処理完了';
        case 'draft': return '下書き';
        default: return status;
      }
    },
    statusIcon(status) {
      switch(status) {
        case 'pending': return 'bi bi-hourglass-split';
        case 'approved': return 'bi bi-check-circle';
        case 'rejected': return 'bi bi-x-circle';
        case 'completed': return 'bi bi-check2-all';
        case 'draft': return 'bi bi-pencil-square';
        default: return 'bi bi-question-circle';
      }
    },
    statusBadgeClass(status) {
      switch(status) {
        case 'pending': return 'bg-primary';
        case 'approved': return 'bg-success';
        case 'rejected': return 'bg-danger';
        case 'completed': return 'bg-info';
        case 'draft': return 'bg-light';
        default: return 'bg-light text-dark';
      }
    },
    statusClass(status) {
      return {
        'text-white': status === 'pending',
        'text-success': status === 'approved',
        'text-danger': status === 'rejected',
        'text-info': status === 'completed'
      };
    },
    canDelete(req) {
      if (!req || !req.id) return false;
      const isAdmin = this.currentUserRole === 'administrator';
      const isApplicant = req.user_id === this.currentUserId;
      if (isAdmin) return true;
      if (isApplicant && (req.status === 'draft' || req.status === 'pending')) return true;
      return false;
    },
    async deleteRequest(req) {
      if (!this.canDelete(req)) return;
      if (!confirm('この申請を削除してもよろしいですか？')) return;
      this.actionLoading = true;
      try {
        const res = await axios.post('/api/index.php?model=request&method=delete_request',
          { id: req.id },
          { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }
        );
        const data = res.data;
        if (data && data.error) {
          if (typeof showMessage === 'function') showMessage(Array.isArray(data.error) ? data.error.join('、') : data.error, true);
          return;
        }
        if (typeof showMessage === 'function') showMessage('削除しました。');
        this.fetchRequests();
        this.tabs.forEach(tab => { this.updatePendingCountForTab(tab.type); });
      } catch (e) {
        if (typeof showMessage === 'function') showMessage('削除に失敗しました。', true);
      } finally {
        this.actionLoading = false;
      }
    }
  },
  mounted() {
    window.addEventListener('afterprint', this.finishPrint);
    const params = new URLSearchParams(window.location.search);
    const statusParam = params.get('status');
    if (statusParam && ['pending', 'approved', 'rejected', 'completed', 'draft'].includes(statusParam)) {
      this.statusFilter = statusParam;
    }
    const tabParam = params.get('tab');
    if (tabParam && this.visibleTabs.some(t => t.type === tabParam)) {
      this.currentTab = tabParam;
    } else {
      const saved = localStorage.getItem('form_index_current_tab');
      if (saved && this.visibleTabs.some(t => t.type === saved)) {
        this.currentTab = saved;
      } else if (!this.visibleTabs.some(t => t.type === this.currentTab)) {
        this.currentTab = this.visibleTabs[0] ? this.visibleTabs[0].type : 'leave';
      }
    }
    this.updateTabUrl(this.currentTab);
    this.loadUserFilterOptions();
    this.fetchRequests();
    this.tabs.forEach(tab => {
      this.updatePendingCountForTab(tab.type);
    });
    this.checkNavMode();
    this._resizeHandler = () => this.checkNavMode();
    window.addEventListener('resize', this._resizeHandler);
    this.$nextTick(() => {
      this.initFormMonthPicker();
    });
  },
  beforeUnmount() {
    window.removeEventListener('afterprint', this.finishPrint);
    if (this._resizeHandler) {
      window.removeEventListener('resize', this._resizeHandler);
    }
    if (this.formMonthPicker) {
      this.formMonthPicker.destroy();
      this.formMonthPicker = null;
    }
  },
  components: {
    'leave-form': leaveForm,
    'outing-form': outingForm,
    'trip-form': tripForm,
    'holiday-work-form': holidayWorkForm,
    'overtime-form': overtimeForm,
    'attendance-correction-form': attendanceCorrectionForm,
    'travel-expense-form': travelExpenseForm,
    'expense-form': expenseForm,
    'trip-expense-form': tripExpenseForm,
    'commuting-allowance-form': commutingAllowanceForm,
    'purchase-form': purchaseForm,
    'it-support-form': itSupportForm,
  }
});
app.component('approver-select', approverSelect);
app.mount('#app');
</script> 