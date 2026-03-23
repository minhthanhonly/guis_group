export default {
  props: { data: { type: Object, required: true } },
  computed: {
    paidTypeLabel() {
      switch(this.data.paid_type) {
        case '全休': return '全休';
        case '午前休': return '午前休';
        case '午後休': return '午後休';
        default: return '';
      }
    },
    unpaidTypeLabel() {
      switch(this.data.unpaid_type) {
        case '慶弔休暇': return '慶弔休暇';
        case '生理休暇': return '生理休暇';
        case '子の看護休暇': return '子の看護休暇';
        case 'その他': return 'その他';
        default: return '';
      }
    },
    leaveTypeLabel() {
      if(this.data.leave_type === '有給休暇') return '有給休暇';
      if(this.data.leave_type === '無給休暇') return '無給休暇';
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
      return `${d.getFullYear()}/${(d.getMonth()+1).toString().padStart(2,'0')}/${d.getDate().toString().padStart(2,'0')}(${wd})`;
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
        <tr v-if="data.leave_type === '有給休暇' && data.paid_type">
          <th>有給休暇</th>
          <td>{{ paidTypeLabel }}</td>
        </tr>
        <tr v-if="data.leave_type === '無給休暇' && data.unpaid_type">
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
        <tr v-if="data.approver_user_id">
          <th>承認者(指定)</th>
          <td>{{ $root.request.approver_user_realname }}</td>
        </tr>
      </tbody>
    </table>
  `
}; 