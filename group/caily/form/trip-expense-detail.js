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
    isLineTotalNegative() {
      const v = Number(this.data && this.data.line_total != null ? this.data.line_total : 0);
      return !isNaN(v) && v < 0;
    },
    isNetTotalNegative() {
      const v = Number(this.data && this.data.net_total != null ? this.data.net_total : 0);
      return !isNaN(v) && v < 0;
    },
    isFinalAmountNegative() {
      const v = Number(this.data && this.data.final_amount != null ? this.data.final_amount : 0);
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
      <col style="width: 200px;">
      <col style="width: auto;">
    </colgroup>
      <tbody>
        <tr v-if="data.destination">
          <th>出張先</th>
          <td>{{ data.destination }}</td>
        </tr>
        <tr v-if="data.trip_type">
          <th>国内/海外</th>
          <td>{{ data.trip_type }}</td>
        </tr>
        
        
        <tr v-if="data.start_date || data.end_date">
          <th>期間</th>
          <td>
            <span v-if="data.start_date">{{ data.start_date }}</span>
            <span v-if="data.start_date || data.end_date"> ~ </span>
            <span v-if="data.end_date">{{ data.end_date }}</span>
          </td>
        </tr>
        <tr v-if="data.settlement_date">
          <th>精算日</th>
          <td>{{ data.settlement_date }}</td>
        </tr>
        <tr v-if="data.trip_type === '海外' && (data.per_diem || data.trip_days || data.trip_allowance)">
          <th>日当・出張手当</th>
          <td>
            <div class="mb-1" v-if="data.per_diem">
              日当: ¥{{ Number(data.per_diem || 0).toLocaleString() }}
            </div>
            <div class="mb-1" v-if="data.trip_days">
              日間: {{ data.trip_days }}
            </div>
            <div v-if="data.trip_allowance">
              出張手当: ¥{{ Number(data.trip_allowance || 0).toLocaleString() }}
            </div>
          </td>
        </tr>
        <tr v-if="data.advance_amount">
          <th>仮払金</th>
          <td>¥{{ Number(data.advance_amount || 0).toLocaleString() }}</td>
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
        <tr v-if="data.lines && data.lines.length">
          <td colspan="2" style="padding: 0; margin-bottom: 2rem;">
            <table class="table table-sm align-middle mb-3 detail-table">
              <thead>
                <tr>
                  <th>日付</th>
                  <th>項目</th>
                  <th>交通費</th>
                  <th>宿泊費</th>
                  <th>交際費</th>
                  <th>食費</th>
                  <th>その他</th>
                  <th>合計</th>
                  <th>備考</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(line, idx) in data.lines" :key="idx">
                  <td>{{ line.date || '-' }}</td>
                  <td>{{ line.item || '-' }}</td>
                  <td>¥{{ Number(line.transportation || 0).toLocaleString() }}</td>
                  <td>¥{{ Number(line.accommodation || 0).toLocaleString() }}</td>
                  <td>¥{{ Number(line.entertainment || 0).toLocaleString() }}</td>
                  <td>¥{{ Number(line.meal || 0).toLocaleString() }}</td>
                  <td>¥{{ Number(line.other || 0).toLocaleString() }}</td>
                  <td>¥{{ Number(line.total || 0).toLocaleString() }}</td>
                  <td>{{ line.note || '-' }}</td>
                </tr>
              </tbody>
            </table>
            <div class="text-end fw-bold mb-2">
              <div>明細合計: <span :class="{ 'text-danger': isLineTotalNegative }">¥{{ Number(data.line_total || 0).toLocaleString() }}</span></div>
            </div>
          </td>
        </tr>
        <tr v-if="data.final_amount">
          <th>仮払金差引合計</th>
          <td :class="{ 'text-danger': isNetTotalNegative }">¥{{ Number(data.net_total || 0).toLocaleString() }}</td>
        </tr>
        <tr>
          <th>精算額</th>
          <td :class="{ 'text-danger': isFinalAmountNegative }">¥{{ Number(data.final_amount || 0).toLocaleString() }}</td>
        </tr>
      </tbody>
    </table>
  `
};
