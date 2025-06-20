<?php require_once('../application/loader.php'); $view->heading('申請一覧'); ?>
<div id="app" v-cloak>
  <ul class="nav nav-tabs">
    <li class="nav-item" v-for="tab in tabs" :key="tab.type">
      <a class="nav-link" :class="{active: currentTab === tab.type}" @click="selectTab(tab.type)">
        {{ tab.label }}
      </a>
    </li>
  </ul>
  <div class="tab-content mt-3">
    <div>
      <button class="btn btn-primary mb-3" @click="openForm">新規申請</button>
      <div v-if="loading" class="text-center py-4"><span class="spinner-border"></span></div>
      <div v-else>
        <div v-if="requests.length === 0" class="text-muted text-center py-4">まだ申請がありません。</div>
        <div v-else class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>申請日</th>
                <th>内容</th>
                <th>状態</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="req in requests" :key="req.id">
                <td>{{ req.created_at }}</td>
                <td>{{ renderSummary(req) }}</td>
                <td>
                  <span :class="statusClass(req.status)">{{ statusLabel(req.status) }}</span>
                </td>
                <td>
                  <a :href="'detail.php?id=' + req.id" class="btn btn-sm btn-outline-info">詳細</a>
                </td>
              </tr>
            </tbody>
          </table>
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
</div>
<?php $view->footing(); ?>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="/assets/js/axios.min.js"></script>
<script type="module">
import leaveForm from './leave-form.js';
const { createApp, defineAsyncComponent } = Vue;
createApp({
  data() {
    return {
      tabs: [
        {type: 'leave', label: '休暇届', form: 'leave-form'},
        // {type: 'outing', label: '外出申請書', form: 'outing-form'},
        // ... các loại khác
      ],
      currentTab: 'leave',
      requests: [],
      loading: false,
      currentFormComponent: null,
    }
  },
  methods: {
    selectTab(type) {
      this.currentTab = type;
      this.fetchRequests();
    },
    async fetchRequests() {
      this.loading = true;
      try {
        const res = await axios.get('/api/index.php?model=request&method=list&type=' + this.currentTab);
        this.requests = Array.isArray(res.data) ? res.data : [];
      } catch {
        this.requests = [];
      }
      this.loading = false;
    },
    openForm() {
      this.currentFormComponent = this.currentTab === 'leave' ? leaveForm : null;
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
      // ... các loại khác
      return '';
    },
    statusLabel(status) {
      switch(status) {
        case 'pending': return '申請中';
        case 'approved': return '承認済み';
        case 'rejected': return '却下';
        default: return status;
      }
    },
    statusClass(status) {
      return {
        'text-secondary': status === 'pending',
        'text-success': status === 'approved',
        'text-danger': status === 'rejected'
      };
    }
  },
  mounted() {
    this.fetchRequests();
  },
  components: {
    'leave-form': leaveForm,
    // 'outing-form': defineAsyncComponent(() => import('./outing-form.js')),
  }
}).mount('#app');
</script> 