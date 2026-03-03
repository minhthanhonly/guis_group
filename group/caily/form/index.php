<?php require_once('../application/loader.php'); $view->heading('申請一覧'); ?>
<div id="app" class="container-fluid mt-4 mb-5" v-cloak>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
      <span class="navbar-brand"></span>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#formNavbarContent" aria-controls="formNavbarContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse justify-content-start" id="formNavbarContent">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item" v-for="tab in tabs" :key="tab.type" :class="{ 'active bg-primary text-white rounded-3': currentTab === tab.type }">
            <a href="#" class="nav-link d-flex align-items-center" @click.prevent="selectTab(tab.type)">
              <span>{{ tab.label }}</span>
              <span v-if="pendingCounts[tab.type] > 0" class="badge rounded-pill bg-warning text-dark ms-2">
                {{ pendingCounts[tab.type] }}
              </span>
            </a>
          </li>
        </ul>
        <div class="d-flex gap-2">
          <button class="btn btn-primary" @click="openForm">
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
      <div class="row mb-3 align-items-end">
        <div class="col-md-2">
          <label for="form-month-input" class="col-form-label col-form-label-sm">年月</label>
          <div class="d-flex align-items-center gap-1">
            <input type="text" class="form-control form-control-sm" id="form-month-input" ref="formMonthInput" readonly placeholder="すべて" :value="monthFilter">
            <button v-if="monthFilter" type="button" class="btn btn-outline-secondary btn-sm" @click="clearMonthFilter" title="フィルターを解除">×</button>
          </div>
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
            <option value="approved">承認済み</option>
            <option value="rejected">却下</option>
          </select>
        </div>
      </div>
      <div v-if="!loading && requests.length === 0" class="text-muted text-center py-5">まだ申請がありません。</div>
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
              <th v-if="currentTab === 'leave' || currentTab === 'outing' || currentTab === 'trip' || currentTab === 'holiday_work' || currentTab === 'overtime' || currentTab === 'attendance_correction'">事由</th>
              <th v-if="currentTab === 'leave' || currentTab === 'outing' || currentTab === 'trip' || currentTab === 'holiday_work' || currentTab === 'overtime' || currentTab === 'attendance_correction' || currentTab === 'travel_expense' || currentTab === 'expense' || currentTab === 'trip_expense' || currentTab === 'commuting_allowance'">注記</th>
              <th v-if="currentTab === 'leave' || currentTab === 'outing' || currentTab === 'trip' || currentTab === 'holiday_work' || currentTab === 'overtime' || currentTab === 'attendance_correction' || currentTab === 'travel_expense' || currentTab === 'expense' || currentTab === 'trip_expense' || currentTab === 'commuting_allowance'">指定承認者</th>
              <th>コメント数</th>
              <th>承認者</th>
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
                {{ formatDate(req.data?.start_datetime) }} ~ {{ formatDate(req.data?.end_datetime) }}
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
              <td v-if="currentTab === 'leave' || currentTab === 'outing' || currentTab === 'trip' || currentTab === 'holiday_work' || currentTab === 'overtime' || currentTab === 'attendance_correction'">{{ req.data?.reason || '-' }}</td>
              <td v-if="currentTab === 'leave' || currentTab === 'outing' || currentTab === 'trip' || currentTab === 'holiday_work' || currentTab === 'overtime' || currentTab === 'attendance_correction' || currentTab === 'travel_expense' || currentTab === 'expense' || currentTab === 'trip_expense' || currentTab === 'commuting_allowance'">{{ req.data?.note || '-' }}</td>
              <td v-if="currentTab === 'leave' || currentTab === 'outing' || currentTab === 'trip' || currentTab === 'holiday_work' || currentTab === 'overtime' || currentTab === 'attendance_correction' || currentTab === 'travel_expense' || currentTab === 'expense' || currentTab === 'trip_expense' || currentTab === 'commuting_allowance'">{{ req.approver_user_realname || req.approver_user_id || '-' }}</td>
              <td>{{ req.comment_count }}</td>
              <td>{{ req.approver_realname || '-' }}</td>
              <td>
                <span :class="['badge', statusBadgeClass(req.status)]">
                  <i :class="statusIcon(req.status)" class="me-1"></i>{{ statusLabel(req.status) }}
                </span>
              </td>
              <td>{{ formatDateTime(req.created_at) }}</td>
              <td>
                <a :href="'detail.php?id=' + req.id" class="btn btn-sm btn-outline-info">詳細</a>
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
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <component :is="currentFormComponent" @submitted="onFormSubmitted" @close="closeForm"></component>
      </div>
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
</style>
<?php $view->footing(); ?>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="/assets/js/axios.min.js"></script>
<script type="module">
import leaveForm from './leave-form.js';
import outingForm from './outing-form.js';
import tripForm from './trip-form.js';
import holidayWorkForm from './holiday-work-form.js';
import overtimeForm from './overtime-form.js';
import attendanceCorrectionForm from './attendance-correction-form.js';
import travelExpenseForm from './travel-expense-form.js';
import expenseForm from './expense-form.js';
import tripExpenseForm from './trip-expense-form.js';
import commutingAllowanceForm from './commuting-allowance-form.js';
const { createApp, defineAsyncComponent } = Vue;
createApp({
  data() {
    return {
      tabs: [
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
      ],
      currentTab: 'leave',
      requests: [],
      loading: false,
      currentFormComponent: null,
      keyword: '',
      searchDebounceTimer: null,
      statusFilter: '',
      monthFilter: '', // YYYY-MM, period 21/(M-1)～20/M
      formMonthPicker: null, // flatpickr instance
      page: 1,
      perPage: 50,
      total: 0,
      totalPages: 0,
      sortBy: 'created_at',
      sortDir: 'desc',
      pendingCounts: {},
    }
  },
  computed: {
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
    }
  },
  methods: {
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
    async updatePendingCountForTab(type) {
      try {
        const params = new URLSearchParams({
          type,
          status: 'pending',
          page: 1,
          per_page: 1
        });
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
        if (d.start_time && d.end_time) return datePart + ' ' + d.start_time + '~' + d.end_time;
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
          type: this.currentTab,
          page: this.page,
          per_page: this.perPage,
          sort_by: this.sortBy,
          sort_dir: this.sortDir
        });
        if (this.keyword && this.keyword.trim() !== '') {
          params.append('keyword', this.keyword.trim());
        }
        if (this.statusFilter) {
          params.append('status', this.statusFilter);
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
      if (req.type === 'commuting_allowance') return req.data?.attachment_original || req.data?.attachment || '';
      return '';
    },
    statusLabel(status) {
      switch(status) {
        case 'pending': return '申請中';
        case 'approved': return '承認済み';
        case 'rejected': return '却下';
        case 'draft': return '下書き';
        default: return status;
      }
    },
    statusIcon(status) {
      switch(status) {
        case 'pending': return 'bi bi-hourglass-split';
        case 'approved': return 'bi bi-check-circle';
        case 'rejected': return 'bi bi-x-circle';
        case 'draft': return 'bi bi-pencil-square';
        default: return 'bi bi-question-circle';
      }
    },
    statusBadgeClass(status) {
      switch(status) {
        case 'pending': return 'bg-primary';
        case 'approved': return 'bg-success';
        case 'rejected': return 'bg-danger';
        case 'draft': return 'bg-light';
        default: return 'bg-light text-dark';
      }
    },
    statusClass(status) {
      return {
        'text-white': status === 'pending',
        'text-success': status === 'approved',
        'text-danger': status === 'rejected'
      };
    }
  },
  mounted() {
    const params = new URLSearchParams(window.location.search);
    const tabParam = params.get('tab');
    if (tabParam && this.tabs.some(t => t.type === tabParam)) {
      this.currentTab = tabParam;
    } else {
      const saved = localStorage.getItem('form_index_current_tab');
      if (saved && this.tabs.some(t => t.type === saved)) {
        this.currentTab = saved;
      }
    }
    this.updateTabUrl(this.currentTab);
    this.fetchRequests();
    this.tabs.forEach(tab => {
      this.updatePendingCountForTab(tab.type);
    });
    this.$nextTick(() => {
      this.initFormMonthPicker();
    });
  },
  beforeUnmount() {
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
  }
}).mount('#app');
</script> 