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
    subtotalAmount() {
      if (this.data && this.data.subtotal_amount != null) {
        const v = Number(this.data.subtotal_amount);
        if (!isNaN(v)) return v;
      }
      let sum = 0;
      this.lineList.forEach(l => {
        const v = l && l.amount != null ? Number(l.amount) : 0;
        if (!isNaN(v)) sum += v;
      });
      return sum;
    },
    subtotalTax() {
      if (this.data && this.data.subtotal_tax != null) {
        const v = Number(this.data.subtotal_tax);
        if (!isNaN(v)) return v;
      }
      let sum = 0;
      this.lineList.forEach(l => {
        const v = l && l.tax != null ? Number(l.tax) : 0;
        if (!isNaN(v)) sum += v;
      });
      return sum;
    },
    totalWithTax() {
      if (this.data && this.data.total_with_tax != null) {
        const v = Number(this.data.total_with_tax);
        if (!isNaN(v)) return v;
      }
      return this.subtotalAmount + this.subtotalTax;
    },
    isSubtotalAmountNegative() {
      const v = Number(this.subtotalAmount);
      return !isNaN(v) && v < 0;
    },
    isSubtotalTaxNegative() {
      const v = Number(this.subtotalTax);
      return !isNaN(v) && v < 0;
    },
    isTotalWithTaxNegative() {
      const v = Number(this.totalWithTax);
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
        <tr v-if="$root.request.approver_user_realname">
          <th>承認者(指定)</th>
          <td>{{ $root.request.approver_user_realname }}</td>
        </tr>
        <tr v-if="lineList.length">
          <td colspan="2" style="padding: 0; margin-bottom: 2rem;">
            <table class="table table-sm align-middle mb-4 detail-table">
              <thead>
                <tr>
                  <th>日付</th>
                  <th>内容</th>
                  <th>支払先</th>
                  <th>金額（税抜）</th>
                  <th>消費税</th>
                  <th>軽減税率</th>
                  <th>備考</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(line, idx) in lineList" :key="idx">
                  <td>{{ line.date || '-' }}</td>
                  <td>{{ line.content || '-' }}</td>
                  <td>{{ line.payee || '-' }}</td>
                  <td>¥{{ (line.amount || 0).toLocaleString() }}</td>
                  <td>¥{{ (line.tax || 0).toLocaleString() }}</td>
                  <td>{{ line.reduced_tax || '-' }}</td>
                  <td>{{ line.note || '-' }}</td>
                </tr>
              </tbody>
            </table>
            <div class="text-end fw-bold mb-4">
              <div>小計（金額（税抜））: <span :class="{ 'text-danger': isSubtotalAmountNegative }">¥{{ (subtotalAmount || 0).toLocaleString() }}</span></div>
              <div>小計（消費税）: <span :class="{ 'text-danger': isSubtotalTaxNegative }">¥{{ (subtotalTax || 0).toLocaleString() }}</span></div>
            </div>
          </td>
        </tr>
        <tr>
          <th>合計（税込）</th>
          <td :class="{ 'text-danger': isTotalWithTaxNegative }">¥{{ (totalWithTax || 0).toLocaleString() }}</td>
        </tr>
      </tbody>
    </table>
  `
};
