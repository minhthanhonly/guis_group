export default {
  props: { data: { type: Object, required: true } },
  computed: {
    hasDate() {
      return !!(this.data && this.data.date);
    },
    dateLabel() {
      if (!this.data || !this.data.date) return '';
      const d = new Date(this.data.date.replace(/-/g, '/'));
      if (isNaN(d)) return this.data.date;
      const youbi = ['日','月','火','水','木','金','土'];
      const wd = youbi[d.getDay()];
      return `${d.getFullYear()}/${(d.getMonth()+1).toString().padStart(2,'0')}/${d.getDate().toString().padStart(2,'0')}(${wd})`;
    },
    timeLabel() {
      return (this.data && this.data.time) ? this.data.time : '';
    }
  },
  template: `
    <table class="table">
      <tbody>
        <tr v-if="hasDate">
          <th>日付</th>
          <td>{{ dateLabel }}{{ timeLabel ? ' ' + timeLabel : '' }}</td>
        </tr>
        <tr v-if="data.correction_type">
          <th>区分</th>
          <td>{{ data.correction_type }}</td>
        </tr>
        <tr v-if="data.reason">
          <th>事由</th>
          <td>{{ data.reason }}</td>
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
