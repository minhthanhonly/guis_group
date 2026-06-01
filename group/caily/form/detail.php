<?php require_once('../application/loader.php'); $view->heading('申請詳細'); ?>
<div id="app" class="container-fluid" v-cloak>
  <div v-if="loading" class="text-center py-4"><span class="spinner-border"></span></div>
  <div v-else class="position-relative">
    <div v-if="actionLoading" class="position-fixed top-0 start-0 end-0 bottom-0 d-flex align-items-center justify-content-center bg-white bg-opacity-75" style="z-index: 9999;">
      <div class="text-center">
        <div class="spinner-border text-primary mb-2" role="status" style="width: 3rem; height: 3rem;"></div>
        <div class="text-muted">処理中...</div>
      </div>
    </div>
    <div class="row">
        <!-- Back button -->
        <div class="col-12 mb-3 mt-4">
            <a href="index.php" class="btn btn-outline-primary me-2">
                <i class="fa fa-arrow-left me-2"></i><span data-i18n="一覧へ戻る">一覧へ戻る</span>
            </a>
        </div>
    </div>
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-dark d-flex align-items-center justify-content-between">
        <h5 class="text-white mb-0"><i class="bi bi-file-earmark-text me-2"></i>{{ typeLabel(request.type) }}</h2>
        <span :class="['badge', statusBadgeClass(request.status)]">
          <i :class="statusIcon(request.status)" class="me-1"></i>{{ statusLabel(request.status) }}
        </span>
      </div>
      <div class="card-body">
        <div class="row mb-2 mt-4">
          <div class="col-md-12 d-flex align-items-center">
            <strong class="me-2">申請者:</strong>
            <span class="me-2">
              <div class="avatar">
                <img v-if="request.user_image"
                     :src="'/assets/upload/avatar/' + request.user_image"
                     alt="avatar"
                     class="rounded-circle">
                <span v-else class="avatar-initial rounded-circle bg-label-primary">
                  {{ getAvatarName(request.realname) }}
                </span>
              </div>
            </span>
            <span class="fw-bold">{{ request.realname || request.user_id }}</span>
            <span class="text-muted ms-2">({{ request.user_id }})</span>
          </div>
          <div class="col-md-12 d-flex align-items-center">
            <strong class="me-2">申請日:</strong>
            <span>{{ formatDate(request.created_at) }}</span>
            <button type="button" class="btn btn-link btn-sm ms-3 p-0 align-baseline" @click="openHistoryModal">
              <i class="bi bi-clock-history me-1"></i>履歴
            </button>
          </div>
          <div v-if="isCalendarRequestType" class="col-md-12 d-flex align-items-center mt-2">
            <strong class="me-2">カレンダーに追加:</strong>
            <span>{{ addToCalendarLabel }}</span>
          </div>
        </div>
        <div class="mb-3">
          <component :is="detailComponent" :data="request.data" :request-id="request.id"></component>
        </div>
        <div class="mb-3">
          <div class="d-flex align-items-center">
            <strong>状態:</strong>
            <div class="ms-2 dropdown d-inline-block" v-if="canUpdateStatus">
              <button class="btn dropdown-toggle waves-effect waves-light" :class="statusButtonClass(request.status)" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i :class="statusIcon(request.status)" class="me-1"></i>{{ statusLabel(request.status) }}
              </button>
              <ul class="dropdown-menu">
                <li>
                  <a class="dropdown-item" href="#" @click.prevent="updateStatus('pending')">
                    <i :class="statusIcon('pending')" class="me-1"></i>{{ actionLabel('pending') }}
                  </a>
                </li>
                <li>
                  <a class="dropdown-item" href="#" @click.prevent="updateStatus('approved')">
                    <i :class="statusIcon('approved')" class="me-1"></i>{{ actionLabel('approved') }}
                  </a>
                </li>
                <li>
                  <a class="dropdown-item" href="#" @click.prevent="updateStatus('rejected')">
                    <i :class="statusIcon('rejected')" class="me-1"></i>{{ actionLabel('rejected') }}
                  </a>
                </li>
              </ul>
            </div>
            <span v-else :class="['badge', statusBadgeClass(request.status), 'ms-2']"><i :class="statusIcon(request.status)" class="me-1"></i>{{ statusLabel(request.status) }}</span>
            <template v-if="canSubmitDraft">
              <button class="btn btn-primary btn-sm ms-2" @click="submitDraft"><i class="bi bi-send"></i> 申請</button>
            </template>
            <button v-if="canMarkCompleted" type="button" class="btn btn-info btn-sm ms-2" @click="markCompleted"><i class="bi bi-check2-all"></i> 処理完了にする</button>
            <button v-if="canEdit" class="btn btn-outline-secondary btn-sm ms-2" @click="openEditModal"><i class="bi bi-pencil-square"></i> 編集</button>
            <button v-if="canDelete" type="button" class="btn btn-outline-danger btn-sm ms-2" @click="confirmDelete"><i class="bi bi-trash"></i> 削除</button>
          </div>
          <div class="mt-1 small text-muted" v-if="decisionInfo">
            <span v-if="decisionInfo.status === 'approved'">承認者:</span>
            <span v-else>却下者:</span>
            {{ decisionInfo.name }} ({{ decisionInfo.user }}) / {{ formatDate(decisionInfo.time) }}
          </div>
          <div class="mt-1 small text-muted" v-if="completedInfo">
            処理完了者: {{ completedInfo.name }} ({{ completedInfo.user }}) / {{ formatDate(completedInfo.time) }}
          </div>
        </div>
        <div class="mb-3">
          <strong>コメント:</strong>
          <div v-if="canComment" class="mb-2">
            <div class="mb-2">
              <div class="d-flex align-items-center gap-2 mb-1">
                <label class="form-label form-label-sm mb-0">通知先</label>
                <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-2" style="font-size:0.75rem;" @click="clearAllRecipients">全解除</button>
              </div>
              <select ref="commentRecipientSelect" class="form-select form-select-sm" multiple></select>
            </div>
            <div class="input-group">
              <input v-model="newComment" class="form-control form-control-sm" placeholder="コメントを入力">
              <button class="btn btn-primary btn-sm" @click="addComment"><i class="bi bi-plus-circle"></i> コメント追加</button>
            </div>
          </div>
          <ul class="list-group mb-2">
            <li v-for="c in sortedComments" :key="c.date" class="list-group-item">
              <div class="d-flex">
                <div class="d-flex flex-row align-items-start justify-content-start me-3" style="min-width:160px;">
                  <div class="d-flex flex-column align-items-center justify-content-start" style="width:44px;">
                    <div class="avatar avatar-sm">
                      <img v-if="c.user_image"
                           :src="'/assets/upload/avatar/' + c.user_image"
                           alt="avatar"
                           class="rounded-circle">
                      <span v-else class="avatar-initial rounded-circle bg-label-primary">
                        {{ getAvatarName(c.realname || c.user_id) }}
                      </span>
                    </div>
                  </div>
                  <div class="d-flex flex-column align-items-start justify-content-center ms-2">
                    <span class="fw-bold small">{{ c.realname || c.user_id }}</span>
                    <span class="text-muted small">{{ formatDate(c.date) }}</span>
                  </div>
                </div>
                <div class="flex-grow-1 d-flex align-items-center">
                  <div>
                    <span>{{ c.message }}</span>
                    <div class="small text-muted mt-1">通知先: <span v-html="commentRecipientText(c)"></span></div>
                  </div>
                </div>
              </div>
            </li>
            <li v-if="!request.comments || request.comments.length === 0" class="list-group-item text-muted">コメントはありません。</li>
          </ul>
        </div>
        <div v-if="errorMessage" class="alert alert-danger mt-3">{{ errorMessage }}</div>
      </div>
    </div>
  </div>
  <!-- Modal 履歴 -->
  <div v-if="showHistoryModal">
    <div class="modal fade show" tabindex="-1" style="display:block; background:rgba(0,0,0,0.3);">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">履歴</h5>
            <button type="button" class="btn-close" @click="closeHistoryModal"></button>
          </div>
          <div class="modal-body">
            <ul class="list-group">
              <li v-for="h in sortedHistory" :key="h.time" class="list-group-item">
                <div class="d-flex">
                  <div class="d-flex flex-row align-items-start justify-content-start me-3" style="min-width:160px;">
                    <div class="d-flex flex-column align-items-center justify-content-start" style="width:40px;">
                      <div class="avatar avatar-sm">
                        <img v-if="h.user_image"
                             :src="'/assets/upload/avatar/' + h.user_image"
                             alt="avatar"
                             class="rounded-circle">
                        <span v-else class="avatar-initial rounded-circle bg-label-primary">
                          {{ getAvatarName(h.realname || h.user) }}
                        </span>
                      </div>
                    </div>
                    <div class="d-flex flex-column align-items-start justify-content-center ms-2">
                      <span class="fw-bold small">{{ h.realname || h.user }}</span>
                      <span class="text-muted small">{{ formatDate(h.time) }}</span>
                    </div>
                  </div>
                  <div class="flex-grow-1 d-flex flex-column justify-content-center">
                    <div>
                      <i :class="historyIcon(h.action)" class="me-2"></i>
                      <span class="me-2">{{ actionLabel(h.action) }}</span>
                    </div>
                    <div v-if="h.note" class="text-muted small mt-1" style="white-space: pre-line;">
                      {{ h.note }}
                    </div>
                  </div>
                </div>
              </li>
              <li v-if="!request.history || request.history.length === 0" class="list-group-item text-muted">履歴はありません。</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- Modal sửa -->
  <div v-if="showEditModal">
    <div class="modal fade show" tabindex="-1" style="display:block; background:rgba(0,0,0,0.3);">
      <div class="modal-dialog" :class="request && request.type === 'travel_expense' || request.type === 'expense' || request.type === 'trip_expense' || request.type === 'commuting_allowance' || request.type === 'purchase' ? 'modal-xl' : 'modal-lg'">
        <div class="modal-content">
          <leave-form
            v-if="request.type === 'leave' && editForm && Object.keys(editForm).length > 0"
            :key="'edit-leave-' + editFormKey + '-' + JSON.stringify(editForm)"
            v-bind="{ defaultData: editForm, mode: 'edit' }"
            @submitted="onEditSubmitted"
            @close="closeEditModal"
          ></leave-form>
          <outing-form
            v-else-if="request.type === 'outing' && editForm && Object.keys(editForm).length > 0"
            :key="'edit-outing-' + editFormKey + '-' + JSON.stringify(editForm)"
            v-bind="{ defaultData: editForm, mode: 'edit' }"
            @submitted="onEditSubmitted"
            @close="closeEditModal"
          ></outing-form>
          <trip-form
            v-else-if="request.type === 'trip' && editForm && Object.keys(editForm).length > 0"
            :key="'edit-trip-' + editFormKey + '-' + JSON.stringify(editForm)"
            v-bind="{ defaultData: editForm, mode: 'edit' }"
            @submitted="onEditSubmitted"
            @close="closeEditModal"
          ></trip-form>
          <holiday-work-form
            v-else-if="request.type === 'holiday_work' && editForm && Object.keys(editForm).length > 0"
            :key="'edit-holiday-work-' + editFormKey + '-' + JSON.stringify(editForm)"
            v-bind="{ defaultData: editForm, mode: 'edit' }"
            @submitted="onEditSubmitted"
            @close="closeEditModal"
          ></holiday-work-form>
          <overtime-form
            v-else-if="request.type === 'overtime' && editForm && Object.keys(editForm).length > 0"
            :key="'edit-overtime-' + editFormKey + '-' + JSON.stringify(editForm)"
            v-bind="{ defaultData: editForm, mode: 'edit' }"
            @submitted="onEditSubmitted"
            @close="closeEditModal"
          ></overtime-form>
          <attendance-correction-form
            v-else-if="request.type === 'attendance_correction' && editForm && Object.keys(editForm).length > 0"
            :key="'edit-attendance-correction-' + editFormKey + '-' + JSON.stringify(editForm)"
            v-bind="{ defaultData: editForm, mode: 'edit' }"
            @submitted="onEditSubmitted"
            @close="closeEditModal"
          ></attendance-correction-form>
          <travel-expense-form
            v-else-if="request.type === 'travel_expense' && editForm && Object.keys(editForm).length > 0"
            :key="'edit-travel-expense-' + editFormKey"
            v-bind="{ defaultData: editForm, mode: 'edit' }"
            @submitted="onEditSubmitted"
            @close="closeEditModal"
          ></travel-expense-form>
          <expense-form
            v-else-if="request.type === 'expense' && editForm && Object.keys(editForm).length > 0"
            :key="'edit-expense-' + editFormKey"
            v-bind="{ defaultData: editForm, mode: 'edit' }"
            @submitted="onEditSubmitted"
            @close="closeEditModal"
          ></expense-form>
          <trip-expense-form
            v-else-if="request.type === 'trip_expense' && editForm && Object.keys(editForm).length > 0"
            :key="'edit-trip-expense-' + editFormKey + '-' + JSON.stringify(editForm)"
            v-bind="{ defaultData: editForm, mode: 'edit' }"
            @submitted="onEditSubmitted"
            @close="closeEditModal"
          ></trip-expense-form>
          <commuting-allowance-form
            v-else-if="request.type === 'commuting_allowance' && editForm && Object.keys(editForm).length > 0"
            :key="'edit-commuting-allowance-' + editFormKey + '-' + JSON.stringify(editForm)"
            v-bind="{ defaultData: editForm, mode: 'edit' }"
            @submitted="onEditSubmitted"
            @close="closeEditModal"
          ></commuting-allowance-form>
          <purchase-form
            v-else-if="request.type === 'purchase' && editForm && Object.keys(editForm).length > 0"
            :key="'edit-purchase-' + editFormKey + '-' + JSON.stringify(editForm)"
            v-bind="{ defaultData: editForm, mode: 'edit' }"
            @submitted="onEditSubmitted"
            @close="closeEditModal"
          ></purchase-form>
          <it-support-form
            v-else-if="request.type === 'it_support' && editForm && Object.keys(editForm).length > 0"
            :key="'edit-it-support-' + editFormKey + '-' + JSON.stringify(editForm)"
            v-bind="{ defaultData: editForm, mode: 'edit' }"
            @submitted="onEditSubmitted"
            @close="closeEditModal"
          ></it-support-form>
        </div>
      </div>
    </div>
  </div>
</div>
<style>
.modal-xl {
  --bs-modal-width: 1140px;
}
.detail-table{
  border: 2px solid #dee2e6;
  margin-top: 1rem;
  margin-left: 1rem;
  margin-right:  1rem;
}
.detail-table td,
.detail-table th{
 padding: 0.25rem;
}
.select2-container--default .select2-results > .select2-results__options{
  max-height: 300px;
}
</style>
<?php $view->footing(); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="/assets/js/axios.min.js?v=<?=CACHE_VERSION?>"></script>
<script type="module">
import leaveDetail from './leave-detail.js?v=<?=CACHE_VERSION?>';
import leaveForm from './leave-form.js?v=<?=CACHE_VERSION?>';
import outingDetail from './outing-detail.js?v=<?=CACHE_VERSION?>';
import outingForm from './outing-form.js?v=<?=CACHE_VERSION?>';
import tripDetail from './trip-detail.js?v=<?=CACHE_VERSION?>';
import tripForm from './trip-form.js?v=<?=CACHE_VERSION?>';
import holidayWorkDetail from './holiday-work-detail.js?v=<?=CACHE_VERSION?>';
import holidayWorkForm from './holiday-work-form.js?v=<?=CACHE_VERSION?>';
import overtimeDetail from './overtime-detail.js?v=<?=CACHE_VERSION?>';
import overtimeForm from './overtime-form.js?v=<?=CACHE_VERSION?>';
import attendanceCorrectionDetail from './attendance-correction-detail.js?v=<?=CACHE_VERSION?>';
import attendanceCorrectionForm from './attendance-correction-form.js?v=<?=CACHE_VERSION?>';
import travelExpenseDetail from './travel-expense-detail.js?v=<?=CACHE_VERSION?>';
import travelExpenseForm from './travel-expense-form.js?v=<?=CACHE_VERSION?>';
import expenseDetail from './expense-detail.js?v=<?=CACHE_VERSION?>';
import expenseForm from './expense-form.js?v=<?=CACHE_VERSION?>';
import tripExpenseDetail from './trip-expense-detail.js?v=<?=CACHE_VERSION?>';
import tripExpenseForm from './trip-expense-form.js?v=<?=CACHE_VERSION?>';
import commutingAllowanceDetail from './commuting-allowance-detail.js?v=<?=CACHE_VERSION?>';
import commutingAllowanceForm from './commuting-allowance-form.js?v=<?=CACHE_VERSION?>';
import purchaseDetail from './purchase-detail.js?v=<?=CACHE_VERSION?>';
import purchaseForm from './purchase-form.js?v=<?=CACHE_VERSION?>';
import itSupportDetail from './it-support-detail.js?v=<?=CACHE_VERSION?>';
import itSupportForm from './it-support-form.js?v=<?=CACHE_VERSION?>';
import { approverMultiselectMixin } from './approver-multiselect.js?v=<?=CACHE_VERSION?>';
import approverSelect from './approver-select.js?v=<?=CACHE_VERSION?>';
const { createApp } = Vue;
const CURRENT_USER_ID = USER_ID || '';
const CURRENT_USER_ROLE = USER_ROLE || '';
const CURRENT_USER_IS_SOUMU = (typeof USER_IS_SOUMU !== 'undefined') && String(USER_IS_SOUMU) === '1';


const app = createApp({
  mixins: [approverMultiselectMixin],
  data() {
    return {
      request: {},
      loading: true,
      actionLoading: false,
      newComment: '',
      selectedCommentRecipients: [],
      errorMessage: '',
      editForm: null,
      editFormKey: 0,
      CURRENT_USER_ROLE: CURRENT_USER_ID,
      editSubmitting: false,
      showHistoryModal: false
    }
  },
  computed: {
    commentRecipientOptions() {
      const list = Array.isArray(this.request?.comment_recipient_candidates)
        ? this.request.comment_recipient_candidates
        : [];
      return list
        .filter(x => x && x.userid)
        .map(x => ({ id: String(x.userid), text: x.realname || x.userid }));
    },
    commentDefaultRecipientIds() {
      const ids = Array.isArray(this.request?.comment_default_recipient_ids)
        ? this.request.comment_default_recipient_ids.map(String)
        : [];
      // fallback: nếu backend không có, lấy toàn bộ options
      if (ids.length === 0) {
        return this.commentRecipientOptions.map(o => o.id);
      }
      // chỉ giữ lại id nằm trong danh sách options
      const optionIds = this.commentRecipientOptions.map(o => o.id);
      return ids.filter(id => optionIds.includes(id));
    },
    canApprove() {
      return ['pending', 'approved', 'rejected'].includes(this.request.status) && CURRENT_USER_ROLE === 'administrator';
    },
    canComment() {
      if (!this.request || !this.request.id) return false;
      const isAdmin = CURRENT_USER_ROLE === 'administrator';
      const isSoumu = CURRENT_USER_IS_SOUMU;
      const isOwner = this.request.user_id === CURRENT_USER_ID;
      const isApprover = this.userIsDesignatedApprover(this.request.approver_user_id, CURRENT_USER_ID);
      return isAdmin || isSoumu || isOwner || isApprover;
    },
    canSubmitDraft() {
      return this.request.status === 'draft' && this.request.user_id === CURRENT_USER_ID;
    },
    canEdit() {
      return this.request && 
             (this.request.user_id === CURRENT_USER_ID || CURRENT_USER_ROLE === 'administrator') &&
             !['approved', 'rejected', 'completed'].includes(this.request.status);
    },
    canMarkCompleted() {
      return this.request &&
             (CURRENT_USER_ROLE === 'administrator' || CURRENT_USER_IS_SOUMU) &&
             ['approved', 'rejected'].includes(this.request.status);
    },
    canUpdateStatus() {
      const isAdmin = CURRENT_USER_ROLE === 'administrator';
      const isSoumu = CURRENT_USER_IS_SOUMU;
      if (this.request && this.request.status === 'completed') {
        return isAdmin || isSoumu;
      }
      const isDesignatedApprover = this.request && this.userIsDesignatedApprover(this.request.approver_user_id, CURRENT_USER_ID);
      return isAdmin || isDesignatedApprover;
    },
    canDelete() {
      if (!this.request || !this.request.id) return false;
      const isAdmin = CURRENT_USER_ROLE === 'administrator';
      const isApplicant = this.request.user_id === CURRENT_USER_ID;
      if (isAdmin) return true;
      if (isApplicant && (this.request.status === 'draft' || this.request.status === 'pending')) return true;
      return false;
    },
    isCalendarRequestType() {
      return ['leave', 'outing', 'trip', 'holiday_work'].includes(this.request?.type);
    },
    addToCalendarLabel() {
      const v = this.request?.add_to_calendar;
      return (v === 1 || v === '1' || v === true) ? 'する' : 'しない';
    },
    detailComponent() {
      if (this.request.type === 'leave') return 'leave-detail';
      if (this.request.type === 'outing') return 'outing-detail';
      if (this.request.type === 'trip') return 'trip-detail';
      if (this.request.type === 'holiday_work') return 'holiday-work-detail';
      if (this.request.type === 'overtime') return 'overtime-detail';
      if (this.request.type === 'attendance_correction') return 'attendance-correction-detail';
      if (this.request.type === 'travel_expense') return 'travel-expense-detail';
      if (this.request.type === 'expense') return 'expense-detail';
      if (this.request.type === 'trip_expense') return 'trip-expense-detail';
      if (this.request.type === 'commuting_allowance') return 'commuting-allowance-detail';
      if (this.request.type === 'purchase') return 'purchase-detail';
      if (this.request.type === 'it_support') return 'it-support-detail';
      return 'default-detail';
    },
    sortedComments() {
      if (!this.request.comments) return [];
      return [...this.request.comments].sort((a, b) => (b.date > a.date ? 1 : -1));
    },
    sortedHistory() {
      if (!this.request.history) return [];
      return [...this.request.history].sort((a, b) => (b.time > a.time ? 1 : -1));
    },
    decisionInfo() {
      if (!this.request || !this.request.status) return null;
      const history = this.sortedHistory;
      if (!history || !history.length) return null;
      let decisionStatus = this.request.status;
      if (decisionStatus === 'completed') {
        const lastDecision = history.find(h => h.action === 'approved' || h.action === 'rejected');
        if (!lastDecision) return null;
        decisionStatus = lastDecision.action;
      }
      if (!['approved', 'rejected'].includes(decisionStatus)) return null;
      const target = history.find(h => h.action === decisionStatus);
      if (!target) return null;
      return {
        status: decisionStatus,
        user: target.user,
        name: target.realname || target.user,
        time: target.time
      };
    },
    completedInfo() {
      if (!this.request || this.request.status !== 'completed') return null;
      const name = this.request.completed_realname || this.request.completed_userid || '';
      const time = this.request.completed_at || '';
      if (!name && !time) return null;
      return {
        user: this.request.completed_userid || '',
        name: name || '-',
        time: time
      };
    },
    showEditModal() {
      return this.editForm && Object.keys(this.editForm).length > 0;
    }
  },
  methods: {
    getAvatarName(name) {
      if (!name) return '?';
      const hasJapanese = /[\u3040-\u309f\u30a0-\u30ff\u4e00-\u9faf]/.test(name);
      if (hasJapanese) return name.substring(0, 2);
      const words = name.trim().split(' ');
      return words[words.length - 1] || name.substring(0, 1) || '?';
    },
    formatDate(dateStr) {
      if (!dateStr) return '';
      const d = new Date(dateStr.replace(/-/g, '/'));
      if (isNaN(d)) return dateStr;
      return `${d.getFullYear()}/${(d.getMonth()+1).toString().padStart(2,'0')}/${d.getDate().toString().padStart(2,'0')} ` +
        `${d.getHours().toString().padStart(2,'0')}:${d.getMinutes().toString().padStart(2,'0')}`;
    },
    selectAllRecipients() {
      const all = this.commentRecipientOptions.map(o => o.id);
      this.selectedCommentRecipients = all;
      const el = this.$refs.commentRecipientSelect;
      if (el && typeof $ !== 'undefined' && typeof $(el).select2 === 'function') {
        $(el).val(all).trigger('change');
      }
    },
    clearAllRecipients() {
      this.selectedCommentRecipients = [];
      const el = this.$refs.commentRecipientSelect;
      if (el && typeof $ !== 'undefined' && typeof $(el).select2 === 'function') {
        $(el).val([]).trigger('change');
      }
    },
    commentRecipientText(comment) {
      if (!comment) return '-';
      const states = Array.isArray(comment.recipient_read_states) ? comment.recipient_read_states : [];
      if (states.length > 0) {
        return states
          .map(s => {
            const name = this.escapeHtml(s.name || s.user_id || '-');
            const isRead = Number(s.is_read || 0) > 0;
            const icon = isRead
              ? '<i class="fa fa-eye text-success ms-1"></i>'
              : '<i class="fa fa-eye-slash text-danger ms-1"></i>';
            return `${name}${icon}`;
          })
          .join('、');
      }
      const names = Array.isArray(comment.recipient_realnames) ? comment.recipient_realnames.filter(Boolean) : [];
      if (names.length > 0) {
        return names.map(n => this.escapeHtml(n)).join('、');
      }
      const ids = Array.isArray(comment.recipient_user_ids) ? comment.recipient_user_ids.filter(Boolean) : [];
      if (ids.length > 0) {
        return ids.map(n => this.escapeHtml(n)).join('、');
      }
      return this.escapeHtml('全員(旧コメント)');
    },
    escapeHtml(value) {
      return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
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
    statusButtonClass(status) {
      switch(status) {
        case 'pending': return 'btn-primary';
        case 'approved': return 'btn-success';
        case 'rejected': return 'btn-danger';
        case 'completed': return 'btn-info';
        case 'draft': return 'btn-light text-dark';
        default: return 'btn-secondary';
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
    historyIcon(action) {
      switch(action) {
        case 'created': return 'bi bi-pencil-square';
        case 'approved': return 'bi bi-check-circle text-success';
        case 'rejected': return 'bi bi-x-circle text-danger';
        case 'completed': return 'bi bi-check2-all text-info';
        case 'draft': return 'bi bi-pencil text-warning';
        default: return 'bi bi-clock-history';
      }
    },
    openHistoryModal() {
      this.showHistoryModal = true;
    },
    closeHistoryModal() {
      this.showHistoryModal = false;
    },
    async fetchDetail() {
      this.loading = true;
      this.errorMessage = '';
      const id = new URLSearchParams(window.location.search).get('id');
      try {
        const res = await axios.get('/api/index.php?model=request&method=get&id=' + id);
        const payload = res ? res.data : null;
        if (Array.isArray(payload) && payload.length === 0) {
          // API trả về mảng rỗng -> không tìm thấy đơn đăng ký
          this.errorMessage = '該当する申請が見つかりません。';
          this.request = {};
        } else if (payload && payload.error) {
          this.errorMessage = Array.isArray(payload.error) ? payload.error.join('、') : payload.error;
          this.request = {};
        } else if (Array.isArray(payload)) {
          // Trường hợp hiếm: API trả về mảng có phần tử, lấy phần tử đầu tiên
          this.request = payload[0] || {};
        } else {
          this.request = payload || {};
        }
        this.selectedCommentRecipients = this.commentDefaultRecipientIds.slice();
      } catch (e) {
        this.errorMessage = 'データ取得に失敗しました。';
        this.request = {};
        this.selectedCommentRecipients = [];
      }
      this.loading = false;
      this.$nextTick(() => this.initCommentRecipientSelect());
      this.refreshLayoutUnreadCommentBadge();
    },
    async refreshLayoutUnreadCommentBadge() {
      const badgeEl = document.getElementById('form-unread-comment-badge');
      if (!badgeEl) return;
      try {
        const res = await axios.get('/api/index.php?model=request&method=countUnreadCommentBadge');
        let count = Number(res?.data);
        if (!Number.isFinite(count)) {
          count = Number(res?.data?.count || 0);
        }
        if (!Number.isFinite(count) || count < 0) count = 0;
        badgeEl.textContent = String(count);
        if (count > 0) {
          badgeEl.classList.remove('d-none');
        } else {
          badgeEl.classList.add('d-none');
        }
      } catch (e) {
        // ignore refresh error
      }
    },
    initCommentRecipientSelect(retry = 0) {
      const el = this.$refs.commentRecipientSelect;
      if (!el) {
        if (retry < 20) {
          setTimeout(() => this.initCommentRecipientSelect(retry + 1), 150);
        }
        return;
      }
      if (typeof $ === 'undefined' || typeof $(el).select2 !== 'function') {
        if (retry < 20) {
          setTimeout(() => this.initCommentRecipientSelect(retry + 1), 150);
        }
        return;
      }
      const $el = $(el);
      const options = this.commentRecipientOptions || [];
      el.innerHTML = options
        .map(o => `<option value="${String(o.id).replace(/"/g, '&quot;')}">${String(o.text)}</option>`)
        .join('');
      if ($el.data('select2')) {
        $el.off('change.commentRecipients');
        $el.select2('destroy');
      }
      $el.select2({
        width: '100%',
        placeholder: '通知先を選択',
        closeOnSelect: false
      });
      const selected = (Array.isArray(this.selectedCommentRecipients) && this.selectedCommentRecipients.length > 0)
        ? this.selectedCommentRecipients
        : this.commentDefaultRecipientIds;
      this.selectedCommentRecipients = selected;
      $el.val(selected).trigger('change');
      $el.on('change.commentRecipients', () => {
        const value = $el.val();
        this.selectedCommentRecipients = Array.isArray(value) ? value.map(String) : [];
      });
    },
    async markCompleted() {
      if (!this.canMarkCompleted) return;
      if (!confirm('この申請を処理完了にしますか？')) return;
      await this.updateStatus('completed');
    },
    async updateStatus(newStatus) {
      if (!['pending','approved','rejected','completed'].includes(newStatus)) return;
      this.errorMessage = '';
      this.actionLoading = true;
      try {
        const res = await axios.post('/api/index.php?model=request&method=update_status', {id: this.request.id, status: newStatus}, { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
        if (res.data && res.data.error) {
          this.errorMessage = Array.isArray(res.data.error) ? res.data.error.join('、') : res.data.error;
        } else {
          this.fetchDetail();
        }
      } catch (e) {
        this.errorMessage = '状態変更に失敗しました。';
      } finally {
        this.actionLoading = false;
      }
    },
    async submitDraft() {
      this.errorMessage = '';
      this.actionLoading = true;
      try {
        const res = await axios.post('/api/index.php?model=request&method=update_status', {id: this.request.id, status: 'pending'}, { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
        if (res.data && res.data.error) {
          const err = Array.isArray(res.data.error) ? res.data.error.join('、') : res.data.error;
          this.errorMessage = err;
          if (typeof showMessage === 'function') showMessage('申請に失敗しました。\n' + err, true);
        } else {
          if (typeof showMessage === 'function') showMessage('申請しました。');
          this.fetchDetail();
        }
      } catch (e) {
        this.errorMessage = '申請処理に失敗しました。';
        if (typeof showMessage === 'function') showMessage('申請処理に失敗しました。', true);
      } finally {
        this.actionLoading = false;
      }
    },
    async addComment() {
      this.errorMessage = '';
      if (!this.newComment) return;
      if (!Array.isArray(this.selectedCommentRecipients) || this.selectedCommentRecipients.length === 0) {
        this.errorMessage = 'コメント受信者を選択してください。';
        return;
      }
      this.actionLoading = true;
      try {
        const res = await axios.post('/api/index.php?model=request&method=add_comment', {
          id: this.request.id,
          message: this.newComment,
          recipient_user_ids: JSON.stringify(this.selectedCommentRecipients)
        }, { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
        if (res.data && res.data.error) {
          this.errorMessage = Array.isArray(res.data.error) ? res.data.error.join('、') : res.data.error;
        } else {
          this.newComment = '';
          this.fetchDetail();
        }
      } catch (e) {
        this.errorMessage = 'コメント追加に失敗しました。';
      } finally {
        this.actionLoading = false;
      }
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
    actionLabel(action) {
      switch(action) {
        case 'pending': return '申請中';
        case 'created': return '申請';
        case 'approved': return '承認';
        case 'rejected': return '却下';
        case 'completed': return '処理完了';
        case 'draft': return '下書き';
        case 'edited': return '編集';
        default: return action;
      }
    },
    typeLabel(type) {
      switch(type) {
        case 'leave': return '休暇届';
        case 'outing': return '外出申請書';
        case 'trip': return '出張申請書';
        case 'holiday_work': return '休日勤務申請書';
        case 'overtime': return '遅刻・早退・時間外勤務';
        case 'attendance_correction': return '勤怠打刻修正';
        case 'travel_expense': return '交通費精算書';
        case 'expense': return '経費精算書';
        case 'trip_expense': return '出張旅費精算書';
        case 'commuting_allowance': return '通勤手当申請書';
        case 'purchase': return '購入申請';
        case 'it_support': return 'ITサポート';
        default: return type;
      }
    },
    openEditModal() {
      if (!this.canEdit) {
        return;
      }
      // 深いコピーを行い、detail 表示用の this.request.data と編集用データを分離する
      const baseData = this.request && this.request.data
        ? JSON.parse(JSON.stringify(this.request.data))
        : {};
      const data = Object.assign(baseData, {
        id: this.request.id,
        approver_user_ids: this.normalizeApproverUserIds(this.request.approver_user_id),
        add_to_calendar: this.request.add_to_calendar || false
      });
      // 外出申請書: chuẩn hóa dữ liệu cũ (datetime) sang date + start_time + end_time
      if (this.request.type === 'outing' && data.datetime && !data.date) {
        data.date = data.datetime.slice(0, 10);
        data.start_time = data.datetime.slice(11, 16) || '';
        data.end_time = data.end_time || data.datetime.slice(11, 16) || '';
      }
      this.editForm = data;
      this.editFormKey++;
    },
    closeEditModal() {
      this.editForm = null;
    },
    async onEditSubmitted(updatedData) {
      await this.fetchDetail();
      this.closeEditModal();
    },
    confirmDelete() {
      if (!this.canDelete) return;
      if (!confirm('この申請を削除してもよろしいですか？')) return;
      this.doDelete();
    },
    async doDelete() {
      this.errorMessage = '';
      this.actionLoading = true;
      try {
        const res = await axios.post('/api/index.php?model=request&method=delete_request',
          { id: this.request.id },
          { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }
        );
        const data = res.data;
        if (data && data.error) {
          this.errorMessage = Array.isArray(data.error) ? data.error.join('、') : data.error;
          return;
        }
        if (typeof showMessage === 'function') showMessage('削除しました。');
        window.location.href = 'index.php';
      } catch (e) {
        this.errorMessage = '削除に失敗しました。';
      } finally {
        this.actionLoading = false;
      }
    }
  },
  mounted() {
    this.fetchDetail();
  },
  beforeUnmount() {
    const el = this.$refs.commentRecipientSelect;
    if (el && typeof $ !== 'undefined' && typeof $(el).select2 === 'function' && $(el).data('select2')) {
      $(el).off('change.commentRecipients');
      $(el).select2('destroy');
    }
  },
  components: {
    'leave-detail': leaveDetail,
    'outing-detail': outingDetail,
    'trip-detail': tripDetail,
    'holiday-work-detail': holidayWorkDetail,
    'leave-form': leaveForm,
    'outing-form': outingForm,
    'trip-form': tripForm,
    'holiday-work-form': holidayWorkForm,
    'overtime-detail': overtimeDetail,
    'overtime-form': overtimeForm,
    'attendance-correction-detail': attendanceCorrectionDetail,
    'attendance-correction-form': attendanceCorrectionForm,
    'travel-expense-detail': travelExpenseDetail,
    'travel-expense-form': travelExpenseForm,
    'expense-detail': expenseDetail,
    'expense-form': expenseForm,
    'trip-expense-detail': tripExpenseDetail,
    'trip-expense-form': tripExpenseForm,
    'commuting-allowance-detail': commutingAllowanceDetail,
    'commuting-allowance-form': commutingAllowanceForm,
    'purchase-detail': purchaseDetail,
    'purchase-form': purchaseForm,
    'it-support-detail': itSupportDetail,
    'it-support-form': itSupportForm,
    'default-detail': {props:['data'], template:'<div>内容: {{ data }}</div>'}
  }
});
app.component('approver-select', approverSelect);
app.mount('#app');
</script> 