export default {
  props: {
    data: { type: Object, required: true },
    requestId: { type: [Number, String], default: null }
  },
  computed: {
    receiptsList() {
      if (!this.data || !this.data.receipts) return [];
      return Array.isArray(this.data.receipts) ? this.data.receipts : [];
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
      <tbody>
        <tr v-if="data.attachment">
          <th>経費精算書</th>
          <td>
            <a :href="downloadUrl()" target="_blank" class="btn btn-sm btn-outline-primary">
              <i class="fa fa-download me-1"></i>{{ data.attachment_original || data.attachment }}
            </a>
          </td>
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
      </tbody>
    </table>
  `
};
