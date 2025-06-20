export default {
  props: { data: { type: Object, required: true } },
  computed: {
    paidTypeLabel() {
      switch(this.data.paid_type) {
        case 'full': return '全休';
        case 'am': return '午前休';
        case 'pm': return '午後休';
        default: return '';
      }
    },
    unpaidTypeLabel() {
      switch(this.data.unpaid_type) {
        case 'congratulatory': return '慶弔休暇';
        case 'menstrual': return '生理休暇';
        case 'child_nursing': return '子の看護休暇';
        default: return '';
      }
    },
    leaveTypeLabel() {
      if(this.data.leave_type === 'paid') return '有給休暇';
      if(this.data.leave_type === 'unpaid') return '無給休暇';
      return '';
    }
  },
  methods: {
    formatDate(dateStr) {
      if (!dateStr) return '';
      let str = dateStr;
      if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(str)) {
        str += ':00';
      }
      const d = new Date(str);
      if (isNaN(d)) return dateStr;
      const youbi = ['日','月','火','水','木','金','土'];
      const wd = youbi[d.getDay()];
      // yyyy/mm/dd(曜) hh:mm:ss
      return `${d.getFullYear()}/${(d.getMonth()+1).toString().padStart(2,'0')}/${d.getDate().toString().padStart(2,'0')}(${wd}) ` +
        `${d.getHours().toString().padStart(2,'0')}:${d.getMinutes().toString().padStart(2,'0')}:${d.getSeconds().toString().padStart(2,'0')}`;
    }
  },
  template: `
    <table class="table">
      <tbody>
        <tr v-if="data.start_datetime && data.end_datetime">
          <th>期間</th>
          <td>{{ formatDate(data.start_datetime) }} ~ {{ formatDate(data.end_datetime) }}</td>
        </tr>
        <tr v-if="data.days">
          <th>日間</th>
          <td>{{ data.days }}</td>
        </tr>
        <tr v-if="data.leave_type">
          <th>休暇種別</th>
          <td>{{ leaveTypeLabel }}</td>
        </tr>
        <tr v-if="data.leave_type === 'paid' && data.paid_type">
          <th>有給休暇</th>
          <td>{{ paidTypeLabel }}</td>
        </tr>
        <tr v-if="data.leave_type === 'unpaid' && data.unpaid_type">
          <th>無給休暇</th>
          <td>{{ unpaidTypeLabel }}</td>
        </tr>
        <tr v-if="data.reason">
          <th>事由</th>
          <td>{{ data.reason }}</td>
        </tr>
        <tr v-if="data.note">
          <th>注記</th>
          <td>{{ data.note }}</td>
        </tr>
      </tbody>
    </table>
  `
}; 