export default {
  props: { data: { type: Object, required: true } },
  computed: {
    hasDateOrDatetime() {
      return !!(this.data && (this.data.date || this.data.datetime));
    },
    dateLabel() {
      if (!this.data) return '';
      if (this.data.date) {
        const d = new Date(this.data.date.replace(/-/g, '/'));
        if (isNaN(d)) return this.data.date;
        const youbi = ['日','月','火','水','木','金','土'];
        const wd = youbi[d.getDay()];
        return `${d.getFullYear()}/${(d.getMonth()+1).toString().padStart(2,'0')}/${d.getDate().toString().padStart(2,'0')}(${wd})`;
      }
      if (this.data.datetime) {
        const str = this.data.datetime.length >= 10 ? this.data.datetime.slice(0, 10) : this.data.datetime;
        const d = new Date(str.replace(/-/g, '/'));
        if (isNaN(d)) return str;
        const youbi = ['日','月','火','水','木','金','土'];
        const wd = youbi[d.getDay()];
        return `${d.getFullYear()}/${(d.getMonth()+1).toString().padStart(2,'0')}/${d.getDate().toString().padStart(2,'0')}(${wd})`;
      }
      return '';
    },
    timeRangeLabel() {
      if (!this.data) return '';
      if (this.data.start_time && this.data.end_time) return this.data.start_time + ' ~ ' + this.data.end_time;
      if (this.data.datetime) {
        const t = this.data.datetime.slice(11, 16);
        return t || '';
      }
      return '';
    },
    purposeLabel() {
      if (!this.data || !this.data.purpose) return '';
      const p = this.data.purpose;
      if (Array.isArray(p)) return p.join('、');
      if (typeof p === 'string') return p;
      return '';
    }
  },
  template: `
    <table class="table">
      <tbody>
        <tr v-if="hasDateOrDatetime">
          <th>日付</th>
          <td>{{ dateLabel }}{{ timeRangeLabel ? ' ' + timeRangeLabel : '' }}</td>
        </tr>
        <tr v-if="purposeLabel">
          <th>用途</th>
          <td>{{ purposeLabel }}</td>
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
