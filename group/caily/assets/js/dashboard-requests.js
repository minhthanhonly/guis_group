(function () {
  const { createApp } = Vue;
  const formRoot = window.DASHBOARD_FORM_ROOT || '';
  const canApprove = !!window.DASHBOARD_CAN_APPROVE;
  const isRequestAdmin = !!window.DASHBOARD_IS_REQUEST_ADMIN;

  const currentUserId = typeof USER_ID !== 'undefined' ? USER_ID : '';

  const typeLabels = {
    leave: '休暇届',
    outing: '外出申請書',
    trip: '出張申請書',
    holiday_work: '休日勤務申請書',
    overtime: '遅刻・早退・時間外勤務',
    attendance_correction: '勤怠打刻修正',
    travel_expense: '交通費精算書',
    expense: '経費精算書',
    trip_expense: '出張旅費精算書',
    commuting_allowance: '通勤手当申請書',
    purchase: '備品購入依頼書',
    it_support: 'ITサポート',
  };

  createApp({
    data() {
      return {
        canApprove,
        isRequestAdmin,
        recentRequests: [],
        pendingRequests: [],
        pendingTotalCount: 0,
        recentLoading: true,
        pendingLoading: canApprove,
        dashboardReady: false,
        formIndexPendingUrl: formRoot + 'index.php?status=pending',
      };
    },
    computed: {
      pendingCountMessage() {
        if (this.pendingTotalCount === 0) {
          return '';
        }
        return '※処理が必要な申請が<span class="text-danger">' + this.pendingTotalCount + '件</span>あります。';
      },
    },
    async mounted() {
      const tasks = [this.fetchRecentRequests()];
      if (this.canApprove) {
        tasks.push(this.fetchPendingApprovals());
      } else {
        this.pendingLoading = false;
      }
      try {
        await Promise.all(tasks);
      } finally {
        this.dashboardReady = true;
      }
    },
    methods: {
      async fetchRecentRequests() {
        this.recentLoading = true;
        try {
          const params = new URLSearchParams({
            user_id: currentUserId,
            per_page: '5',
            page: '1',
            sort_by: 'created_at',
            sort_dir: 'desc',
          });
          const res = await axios.get('/api/index.php?model=request&method=list&' + params.toString());
          const body = res.data;
          if (Array.isArray(body)) {
            this.recentRequests = body.slice(0, 5);
          } else {
            this.recentRequests = Array.isArray(body.data) ? body.data : [];
          }
        } catch {
          this.recentRequests = [];
        }
        this.recentLoading = false;
      },
      async fetchPendingApprovals() {
        this.pendingLoading = true;
        try {
          const params = new URLSearchParams({
            approval_queue: '1',
            per_page: '5',
            page: '1',
            sort_by: 'created_at',
            sort_dir: 'desc',
          });
          const res = await axios.get('/api/index.php?model=request&method=list&' + params.toString());
          const body = res.data;
          let rows = [];
          if (Array.isArray(body)) {
            rows = body;
          } else {
            rows = Array.isArray(body.data) ? body.data : [];
          }
          if (body && body.pagination && typeof body.pagination.total !== 'undefined') {
            this.pendingTotalCount = body.pagination.total;
          } else {
            this.pendingTotalCount = rows.length;
          }
          this.pendingRequests = rows;
        } catch {
          this.pendingRequests = [];
          this.pendingTotalCount = 0;
        }
        this.pendingLoading = false;
      },
      typeLabel(type) {
        return typeLabels[type] || type || '-';
      },
      detailUrl(id) {
        return formRoot + 'detail.php?id=' + encodeURIComponent(id);
      },
      formatDateTime(dateStr) {
        if (!dateStr) return '-';
        let str = dateStr;
        if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(str)) {
          str += ':00';
        }
        const d = new Date(str);
        if (isNaN(d)) return dateStr;
        const youbi = ['日', '月', '火', '水', '木', '金', '土'];
        const wd = youbi[d.getDay()];
        return d.getFullYear() + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + String(d.getDate()).padStart(2, '0') +
          '(' + wd + ') ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
      },
      statusLabel(status) {
        switch (status) {
          case 'pending': return '申請中';
          case 'approved': return '承認済み';
          case 'rejected': return '却下';
          case 'draft': return '下書き';
          default: return status || '-';
        }
      },
      statusIcon(status) {
        switch (status) {
          case 'pending': return 'fa fa-hourglass-half';
          case 'approved': return 'fa fa-check-circle';
          case 'rejected': return 'fa fa-times-circle';
          case 'draft': return 'fa fa-pencil';
          default: return 'fa fa-question-circle';
        }
      },
      statusBadgeClass(status) {
        switch (status) {
          case 'pending': return 'bg-primary';
          case 'approved': return 'bg-success';
          case 'rejected': return 'bg-danger';
          case 'draft': return 'bg-light text-dark';
          default: return 'bg-light text-dark';
        }
      },
    },
  }).mount('#dashboardRequestsApp');
})();
