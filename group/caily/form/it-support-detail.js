export default {
  props: {
    data: { type: Object, required: true },
    requestId: { type: [Number, String], default: null }
  },
  computed: {
    categoryLabel() {
      return this.data.category || '';
    },
    priorityLabel() {
      return this.data.priority || '';
    },
    attachmentsList() {
      if (!this.data || !this.data.attachments) return [];
      return Array.isArray(this.data.attachments) ? this.data.attachments : [];
    }
  },
  methods: {
    attachmentDownloadUrl(item) {
      if (!item || !item.filename || !this.requestId) return '#';
      return 'download.php?file=' + encodeURIComponent(item.filename) + '&request_id=' + encodeURIComponent(this.requestId);
    }
  },
  template: `
    <table class="table">
      <tbody>
        <tr v-if="data.category">
          <th>区分</th>
          <td>{{ categoryLabel }}</td>
        </tr>
        <tr v-if="data.subject">
          <th>件名</th>
          <td>{{ data.subject }}</td>
        </tr>
        <tr v-if="data.description">
          <th>内容・詳細</th>
          <td class="text-break" style="white-space: pre-wrap;">{{ data.description }}</td>
        </tr>
        <tr v-if="data.priority">
          <th>緊急度</th>
          <td>{{ priorityLabel }}</td>
        </tr>
        <tr v-if="attachmentsList.length">
          <th>添付資料（画像・書類）</th>
          <td>
            <ul class="list-unstyled mb-0">
              <li v-for="(item, index) in attachmentsList" :key="index" class="mb-1">
                <a :href="attachmentDownloadUrl(item)" target="_blank" class="btn btn-sm btn-outline-primary">
                  <i class="fa fa-download me-1"></i>{{ item.original_name || item.filename }}
                </a>
              </li>
            </ul>
          </td>
        </tr>
        <tr v-if="data.note">
          <th>注記</th>
          <td class="text-break" style="white-space: pre-wrap;">{{ data.note }}</td>
        </tr>
        <tr v-if="data.approver_user_id">
          <th>承認者(指定)</th>
          <td>{{ $root.request.approver_user_realname }}</td>
        </tr>
      </tbody>
    </table>
  `
};
