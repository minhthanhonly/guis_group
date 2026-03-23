export default {
  props: {
    data: { type: Object, required: true },
    requestId: { type: [Number, String], default: null }
  },
  computed: {
    receiptsList() {
      if (!this.data || !this.data.receipts) return [];
      return Array.isArray(this.data.receipts) ? this.data.receipts : [];
    },
    lineList() {
      if (!this.data || !this.data.lines) return [];
      return Array.isArray(this.data.lines) ? this.data.lines : [];
    },
    totalAmount() {
      const lines = this.lineList;
      let sum = 0;
      lines.forEach(l => {
        const v = l && l.amount != null ? Number(l.amount) : 0;
        if (!isNaN(v)) sum += v;
      });
      if (this.data && this.data.total_amount != null) {
        const extra = Number(this.data.total_amount);
        if (!isNaN(extra) && extra > sum) sum = extra;
      }
      return sum;
    },
    isTotalNegative() {
      const v = Number(this.totalAmount);
      return !isNaN(v) && v < 0;
    }
  },
  methods: {
    receiptDownloadUrl(item) {
      if (!item || !item.filename || !this.requestId) return '#';
      return 'download.php?file=' + encodeURIComponent(item.filename) + '&request_id=' + encodeURIComponent(this.requestId);
    }
  },
  template: `
    <table class="table">
      <colgroup>
        <col style="width: 150px;">
        <col style="width: auto;">
      </colgroup>
      <tbody>
        <tr v-if="receiptsList.length">
          <th>請求書・領収書等</th>
          <td>
            <ul class="list-unstyled mb-0">
              <li v-for="(item, index) in receiptsList" :key="index" class="mb-1">
                <a :href="receiptDownloadUrl(item)" target="_blank" class="btn btn-sm btn-outline-primary">
                  <i class="fa fa-download me-1"></i>{{ item.original_name || item.filename }}
                </a>
              </li>
            </ul>
          </td>
        </tr>
        <tr v-if="data.note">
          <th>備考</th>
          <td>{{ data.note }}</td>
        </tr>
        <tr v-if="data.approver_user_id">
          <th>承認者(指定)</th>
          <td>{{ $root.request.approver_user_realname }}</td>
        </tr>
        <tr v-if="lineList.length">
          <td colspan="2" style="padding: 0; margin-bottom: 2rem;">
            <table class="table table-sm align-middle mb-4 detail-table">
              <thead>
                <tr>
                  <th>日付</th>
                  <th>路線</th>
                  <th>乗車駅</th>
                  <th>下車駅</th>
                  <th>往復/片道</th>
                  <th>金額</th>
                  <th>備考</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(line, idx) in lineList" :key="idx">
                  <td>{{ line.date || '-' }}</td>
                  <td>{{ line.route || '-' }}</td>
                  <td>{{ line.from || '-' }}</td>
                  <td>{{ line.to || '-' }}</td>
                  <td>{{ line.way || '-' }}</td>
                  <td>¥{{ (line.amount || 0).toLocaleString() }}</td>
                  <td>{{ line.note || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </td>
        </tr>
        <tr>
          <th>合計</th>
          <td :class="{ 'text-danger': isTotalNegative }">¥{{ (totalAmount || 0).toLocaleString() }}</td>
        </tr>
      </tbody>
    </table>
  `
};
