export default {
  props: {
    data: { type: Object, required: true }
  },
  computed: {
    callDatetimeLabel() {
      if (!this.data || !this.data.call_datetime) return '';
      let str = String(this.data.call_datetime).trim();
      if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(str)) {
        str = str.replace('T', ' ').slice(0, 16);
      } else if (/^\d{4}[-\/]\d{2}[-\/]\d{2}\s\d{2}:\d{2}/.test(str)) {
        str = str.slice(0, 16);
      }
      const d = new Date(str.replace(/-/g, '/'));
      if (isNaN(d.getTime())) return this.data.call_datetime;
      const youbi = ['日', '月', '火', '水', '木', '金', '土'];
      const wd = youbi[d.getDay()];
      return `${d.getFullYear()}/${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}(${wd}) ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
    }
  },
  template: `
    <table class="table">
      <tbody>
        <tr v-if="data.call_datetime">
          <th>通話日時</th>
          <td>{{ callDatetimeLabel }}</td>
        </tr>
        <tr v-if="data.call_partner">
          <th>通話相手</th>
          <td>{{ data.call_partner }}</td>
        </tr>
        <tr v-if="data.reason">
          <th>通話録音の確認が<br>必要な理由</th>
          <td class="text-break" style="white-space: pre-wrap;">{{ data.reason }}</td>
        </tr>
        <tr v-if="data.confirm_content">
          <th>確認したい内容</th>
          <td class="text-break" style="white-space: pre-wrap;">{{ data.confirm_content }}</td>
        </tr>
        <tr v-if="data.note">
          <th>注記</th>
          <td class="text-break" style="white-space: pre-wrap;">{{ data.note }}</td>
        </tr>
        <tr v-if="$root.request.approver_user_realname">
          <th>承認者(指定)</th>
          <td>{{ $root.request.approver_user_realname }}</td>
        </tr>
      </tbody>
    </table>
  `
};
