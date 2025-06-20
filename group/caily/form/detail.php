<?php require_once('../application/loader.php'); $view->heading('申請詳細'); ?>
<div id="app" v-cloak>
  <div v-if="loading" class="text-center py-4"><span class="spinner-border"></span></div>
  <div v-else>
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-light d-flex align-items-center justify-content-between">
        <div>
          <i class="bi bi-file-earmark-text me-2"></i>{{ typeLabel(request.type) }}
        </div>
        <span :class="['badge', statusBadgeClass(request.status)]">
          <i :class="statusIcon(request.status)" class="me-1"></i>{{ statusLabel(request.status) }}
        </span>
      </div>
      <div class="card-body">
        <div class="row mb-2 mt-4">
          <div class="col-md-12 d-flex align-items-center">
            <strong class="me-2">申請者:</strong>
            <span v-if="request.user_image" class="me-2">
              <img :src="'/assets/upload/avatar/' + request.user_image" alt="avatar" class="rounded-circle" width="32" height="32">
            </span>
            <span v-else class="me-2">
              <span class="avatar-placeholder rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;font-size:1.1rem;">
                {{ request.realname ? request.realname.charAt(0) : '?' }}
              </span>
            </span>
            <span class="fw-bold">{{ request.realname || request.user_id }}</span>
            <span class="text-muted ms-2">({{ request.user_id }})</span>
          </div>
          <div class="col-md-12"><strong>申請日:</strong> {{ formatDate(request.created_at) }}</div>
        </div>
        <div class="mb-3">
          <component :is="detailComponent" :data="request.data"></component>
        </div>
        <div class="mb-3 d-flex align-items-center">
          <strong>状態:</strong>
          <div class="ms-2 dropdown d-inline-block" v-if="CURRENT_USER_ROLE === 'administrator'">
            <button class="btn btn-secondary dropdown-toggle waves-effect waves-light" :class="statusBadgeClass(request.status)" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i :class="statusIcon(request.status)" class="me-1"></i>{{ statusLabel(request.status) }}
            </button>
            <ul class="dropdown-menu">
              <li>
                <a class="dropdown-item" href="#" @click.prevent="updateStatus('pending')">
                  <i :class="statusIcon('pending')" class="me-1"></i>{{ statusLabel('pending') }}
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="#" @click.prevent="updateStatus('approved')">
                  <i :class="statusIcon('approved')" class="me-1"></i>{{ statusLabel('approved') }}
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="#" @click.prevent="updateStatus('rejected')">
                  <i :class="statusIcon('rejected')" class="me-1"></i>{{ statusLabel('rejected') }}
                </a>
              </li>
            </ul>
          </div>
          <span v-else :class="['badge', statusBadgeClass(request.status)]"><i :class="statusIcon(request.status)" class="me-1"></i>{{ statusLabel(request.status) }}</span>
          <template v-if="canSubmitDraft">
            <button class="btn btn-primary btn-sm ms-2" @click="submitDraft"><i class="bi bi-send"></i> 申請</button>
          </template>
          <button v-if="canEdit" class="btn btn-outline-secondary btn-sm ms-2" @click="openEditModal"><i class="bi bi-pencil-square"></i> 編集</button>
        </div>
        <div class="mb-3">
          <strong>コメント:</strong>
          <div v-if="canComment" class="input-group mb-2">
            <input v-model="newComment" class="form-control form-control-sm" placeholder="コメントを入力">
            <button class="btn btn-primary btn-sm" @click="addComment"><i class="bi bi-plus-circle"></i> コメント追加</button>
          </div>
          <ul class="list-group mb-2">
            <li v-for="c in sortedComments" :key="c.date" class="list-group-item">
              <div class="d-flex">
                <div class="d-flex flex-row align-items-start justify-content-start me-3" style="min-width:160px;">
                  <div class="d-flex flex-column align-items-center justify-content-start" style="width:44px;">
                    <span v-if="c.user_image">
                      <img :src="'/assets/upload/avatar/' + c.user_image" alt="avatar" class="rounded-circle" width="36" height="36">
                    </span>
                    <span v-else>
                      <span class="avatar-placeholder rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;font-size:1.1rem;">
                        {{ c.realname ? c.realname.charAt(0) : '?' }}
                      </span>
                    </span>
                  </div>
                  <div class="d-flex flex-column align-items-start justify-content-center ms-2">
                    <span class="fw-bold small">{{ c.realname || c.user_id }}</span>
                    <span class="text-muted small">{{ formatDate(c.date) }}</span>
                  </div>
                </div>
                <div class="flex-grow-1 d-flex align-items-center">
                  <span>{{ c.message }}</span>
                </div>
              </div>
            </li>
            <li v-if="!request.comments || request.comments.length === 0" class="list-group-item text-muted">コメントはありません。</li>
          </ul>
        </div>
        <div class="mb-3">
          <strong>履歴:</strong>
          <ul class="list-group">
            <li v-for="h in sortedHistory" :key="h.time" class="list-group-item">
              <div class="d-flex">
                <div class="d-flex flex-row align-items-start justify-content-start me-3" style="min-width:160px;">
                  <div class="d-flex flex-column align-items-center justify-content-start" style="width:40px;">
                    <span v-if="h.user_image">
                      <img :src="'/assets/upload/avatar/' + h.user_image" alt="avatar" class="rounded-circle" width="32" height="32">
                    </span>
                    <span v-else>
                      <span class="avatar-placeholder rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;font-size:1rem;">
                        {{ h.realname ? h.realname.charAt(0) : '?' }}
                      </span>
                    </span>
                  </div>
                  <div class="d-flex flex-column align-items-start justify-content-center ms-2">
                    <span class="fw-bold small">{{ h.realname || h.user }}</span>
                    <span class="text-muted small">{{ formatDate(h.time) }}</span>
                  </div>
                </div>
                <div class="flex-grow-1 d-flex align-items-center">
                  <span>
                    <i :class="historyIcon(h.action)" class="me-2"></i>
                    <span class="me-2">{{ actionLabel(h.action) }}</span>
                    <span v-if="h.note" class="text-muted">({{ h.note }})</span>
                  </span>
                </div>
              </div>
            </li>
            <li v-if="!request.history || request.history.length === 0" class="list-group-item text-muted">履歴はありません。</li>
          </ul>
        </div>
        <div v-if="errorMessage" class="alert alert-danger mt-3">{{ errorMessage }}</div>
      </div>
    </div>
  </div>
  <!-- Modal sửa -->
  <div v-if="showEditModal">
    <div class="modal fade show" tabindex="-1" style="display:block; background:rgba(0,0,0,0.3);">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <leave-form :key="editFormKey" :defaultData="editForm" mode="edit" @submitted="onEditSubmitted" @close="closeEditModal"></leave-form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php $view->footing(); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="/assets/js/axios.min.js"></script>
<script type="module">
import leaveDetail from './leave-detail.js';
import leaveForm from './leave-form.js';
const { createApp } = Vue;
const CURRENT_USER_ID = USER_ID || '';
createApp({
  data() {
    return {
      request: {},
      loading: true,
      newComment: '',
      errorMessage: '',
      CURRENT_USER_ROLE: USER_ROLE,
      showEditModal: false,
      editForm: {},
      editFormKey: 0,
      editSubmitting: false
    }
  },
  computed: {
    canApprove() {
      return ['pending', 'approved', 'rejected'].includes(this.request.status) && CURRENT_USER_ROLE === 'administrator';
    },
    canComment() {
      return this.request.user_id === CURRENT_USER_ID || CURRENT_USER_ROLE === 'administrator';
    },
    canSubmitDraft() {
      return this.request.status === 'draft' && this.request.user_id === CURRENT_USER_ID;
    },
    canEdit() {
      return this.request && (this.request.user_id === CURRENT_USER_ID || this.CURRENT_USER_ROLE === 'administrator');
    },
    detailComponent() {
      if (this.request.type === 'leave') return 'leave-detail';
      return 'default-detail';
    },
    sortedComments() {
      if (!this.request.comments) return [];
      return [...this.request.comments].sort((a, b) => (b.date > a.date ? 1 : -1));
    },
    sortedHistory() {
      if (!this.request.history) return [];
      return [...this.request.history].sort((a, b) => (b.time > a.time ? 1 : -1));
    }
  },
  methods: {
    formatDate(dateStr) {
      if (!dateStr) return '';
      const d = new Date(dateStr.replace(/-/g, '/'));
      if (isNaN(d)) return dateStr;
      return `${d.getFullYear()}/${(d.getMonth()+1).toString().padStart(2,'0')}/${d.getDate().toString().padStart(2,'0')} ` +
        `${d.getHours().toString().padStart(2,'0')}:${d.getMinutes().toString().padStart(2,'0')}`;
    },
    statusBadgeClass(status) {
      switch(status) {
        case 'pending': return 'bg-secondary';
        case 'approved': return 'bg-success';
        case 'rejected': return 'bg-danger';
        case 'draft': return 'bg-warning text-dark';
        default: return 'bg-light text-dark';
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
    historyIcon(action) {
      switch(action) {
        case 'created': return 'bi bi-pencil-square';
        case 'approved': return 'bi bi-check-circle text-success';
        case 'rejected': return 'bi bi-x-circle text-danger';
        case 'draft': return 'bi bi-pencil text-warning';
        default: return 'bi bi-clock-history';
      }
    },
    async fetchDetail() {
      this.loading = true;
      this.errorMessage = '';
      const id = new URLSearchParams(window.location.search).get('id');
      try {
        const res = await axios.get('/api/index.php?model=request&method=get&id=' + id);
        if (res.data && res.data.error) {
          this.errorMessage = Array.isArray(res.data.error) ? res.data.error.join('、') : res.data.error;
        } else {
          this.request = res.data;
        }
      } catch (e) {
        this.errorMessage = 'データ取得に失敗しました。';
      }
      this.loading = false;
    },
    async updateStatus(newStatus) {
      if (!['pending','approved','rejected'].includes(newStatus)) return;
      this.errorMessage = '';
      try {
        const res = await axios.post('/api/index.php?model=request&method=update_status', {id: this.request.id, status: newStatus}, { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
        if (res.data && res.data.error) {
          this.errorMessage = Array.isArray(res.data.error) ? res.data.error.join('、') : res.data.error;
        } else {
          this.fetchDetail();
        }
      } catch (e) {
        this.errorMessage = '状態変更に失敗しました。';
      }
    },
    async submitDraft() {
      this.errorMessage = '';
      try {
        const res = await axios.post('/api/index.php?model=request&method=update_status', {id: this.request.id, status: 'pending'}, { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
        if (res.data && res.data.error) {
          this.errorMessage = Array.isArray(res.data.error) ? res.data.error.join('、') : res.data.error;
        } else {
          this.fetchDetail();
        }
      } catch (e) {
        this.errorMessage = '申請処理に失敗しました。';
      }
    },
    async addComment() {
      this.errorMessage = '';
      if (!this.newComment) return;
      try {
        const res = await axios.post('/api/index.php?model=request&method=add_comment', {id: this.request.id, message: this.newComment}, { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
        if (res.data && res.data.error) {
          this.errorMessage = Array.isArray(res.data.error) ? res.data.error.join('、') : res.data.error;
        } else {
          this.newComment = '';
          this.fetchDetail();
        }
      } catch (e) {
        this.errorMessage = 'コメント追加に失敗しました。';
      }
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
    actionLabel(action) {
      switch(action) {
        case 'created': return '申請';
        case 'approved': return '承認';
        case 'rejected': return '却下';
        case 'draft': return '下書き';
        default: return action;
      }
    },
    typeLabel(type) {
      switch(type) {
        case 'leave': return '休暇届';
        default: return type;
      }
    },
    openEditModal() {
      this.editForm = Object.assign({ id: this.request.id }, JSON.parse(JSON.stringify(this.request.data)));
      this.editFormKey++;
      this.showEditModal = true;
    },
    closeEditModal() {
      this.showEditModal = false;
    },
    async onEditSubmitted(formData) {
      this.editSubmitting = true;
      try {
        const res = await axios.post('/api/index.php?model=request&method=edit', {
          id: this.request.id,
          data: formData
        }, { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
        if (res.data && res.data.error) {
          this.errorMessage = Array.isArray(res.data.error) ? res.data.error.join('、') : res.data.error;
        } else {
          this.showEditModal = false;
          this.fetchDetail();
        }
      } catch (e) {
        this.errorMessage = '編集に失敗しました。';
      }
      this.editSubmitting = false;
    }
  },
  mounted() {
    this.fetchDetail();
  },
  components: {
    'leave-detail': leaveDetail,
    'leave-form': leaveForm,
    'default-detail': {props:['data'], template:'<div>内容: {{ data }}</div>'}
  }
}).mount('#app');
</script> 