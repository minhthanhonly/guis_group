export default {
  props: { data: { type: Object, required: true } },
  computed: {
    hasPeriod() {
      return !!(this.data && (this.data.start_datetime || this.data.end_datetime));
    },
    periodLabel() {
      if (!this.data) return '';
      const fmt = (str) => {
        if (!str) return '';
        const s = (str.length >= 10 ? str.slice(0, 10) : str).replace(/-/g, '/');
        const d = new Date(s);
        if (isNaN(d)) return str.slice(0, 10);
        const youbi = ['日','月','火','水','木','金','土'];
        const wd = youbi[d.getDay()];
        return `${d.getFullYear()}/${(d.getMonth()+1).toString().padStart(2,'0')}/${d.getDate().toString().padStart(2,'0')}(${wd})`;
      };
      const start = fmt(this.data.start_datetime);
      const end = fmt(this.data.end_datetime);
      if (start && end) return start + ' ~ ' + end;
      return start || end || '';
    }
  },
  template: `
    <table class="table">
      <tbody>
        <tr v-if="hasPeriod">
          <th>期間</th>
          <td>{{ periodLabel }}</td>
        </tr>
        <tr v-if="data.days">
          <th>日間</th>
          <td>{{ data.days }}</td>
        </tr>
        <tr v-if="data.destination">
          <th>行先</th>
          <td>{{ data.destination }}</td>
        </tr>
        <tr v-if="data.reason">
          <th>事由</th>
          <td>{{ data.reason }}</td>
        </tr>
        <tr v-if="data.note">
          <th>備考</th>
          <td>{{ data.note }}</td>
        </tr>
        <tr v-if="$root.request.approver_user_realname">
          <th>承認者(指定)</th>
          <td>{{ $root.request.approver_user_realname }}</td>
        </tr>
      </tbody>
    </table>
  `
};
