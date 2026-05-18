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
    breakTimeLabel() {
      if (!this.data || this.data.break_time === undefined || this.data.break_time === null || this.data.break_time === '') return '';
      const v = String(this.data.break_time).trim();
      const labels = { '0.5': '0.5h', '1': '1h', '1.5': '1.5h', '2': '2h', '2.5': '2.5h', '3': '3h', '3.5': '3.5h', '4': '4h' };
      if (labels[v]) return labels[v];
      const minuteToHour = { 30: '0.5h', 60: '1h', 90: '1.5h', 120: '2h', 150: '2.5h', 180: '3h', 210: '3.5h', 240: '4h' };
      if (/^\d+$/.test(v) && minuteToHour[v]) return minuteToHour[v];
      const hm = v.match(/^(\d+):(\d{2})$/);
      if (hm) {
        const minutes = parseInt(hm[1], 10) * 60 + parseInt(hm[2], 10);
        if (minuteToHour[minutes]) return minuteToHour[minutes];
      }
      return `${v}h`;
    },
    timeRangeLabel() {
      if (!this.data) return '';
      if (this.data.start_time && this.data.end_time) {
        const breakPart = this.breakTimeLabel ? `（休憩${this.breakTimeLabel}）` : '';
        return this.data.start_time + ' ~ ' + this.data.end_time + breakPart;
      }
      if (this.data.datetime) {
        const t = this.data.datetime.slice(11, 16);
        return t || '';
      }
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
