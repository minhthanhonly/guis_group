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
        const v = l && l.one_way_fare != null ? Number(l.one_way_fare) : 0;
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
    downloadUrl() {
      if (!this.data || !this.data.attachment) return '#';
      if (!this.requestId) return '#';
      return 'download.php?file=' + encodeURIComponent(this.data.attachment) + '&request_id=' + encodeURIComponent(this.requestId);
    },
    receiptDownloadUrl(item) {
      if (!item || !item.filename || !this.requestId) return '#';
      return 'download.php?file=' + encodeURIComponent(item.filename) + '&request_id=' + encodeURIComponent(this.requestId);
    }
  },
  template: `
    <table class="table">
      <colgroup>
        <col style="width: 200px;">
        <col style="width: auto;">
      </colgroup>
      <tbody>
        <tr v-if="data.application_type">
          <th>申請区分</th>
          <td>{{ data.application_type }}</td>
        </tr>
        <tr v-if="data.address">
          <th>住所</th>
          <td>{{ data.address }}</td>
        </tr>
        <tr v-if="data.nearest_station">
          <th>最寄駅</th>
          <td>{{ data.nearest_station }}</td>
        </tr>
        <tr v-if="data.effective_from">
          <th>適用開始日</th>
          <td>{{ data.effective_from }}</td>
        </tr>
        <tr v-if="data.one_month_commuter_pass">
          <th>１か月定期代</th>
          <td>¥{{ (data.one_month_commuter_pass || 0).toLocaleString() }}<span class="ms-2 text-muted">※月給制のみ</span></td>
        </tr>
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
                  <th>鉄道会社名</th>
                  <th>路線名</th>
                  <th>利用区間(乗車駅)</th>
                  <th>利用区間(降車駅)</th>
                  <th>片道運賃</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(line, idx) in lineList" :key="idx">
                  <td>{{ line.railway_company || '-' }}</td>
                  <td>{{ line.line_name || '-' }}</td>
                  <td>{{ line.section_from || '-' }}</td>
                  <td>{{ line.section_to || '-' }}</td>
                  <td>¥{{ (line.one_way_fare || 0).toLocaleString() }}</td>
                </tr>
              </tbody>
            </table>
          </td>
        </tr>
        <tr>
          <th>合計片道運賃</th>
          <td :class="{ 'text-danger': isTotalNegative }">¥{{ (totalAmount || 0).toLocaleString() }}</td>
        </tr>
      </tbody>
    </table>
  `
};
