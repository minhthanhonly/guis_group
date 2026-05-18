export default {
  props: { data: { type: Object, required: true } },
  computed: {
    displayLines() {
      if (Array.isArray(this.data.lines) && this.data.lines.length) {
        return this.data.lines.filter(line => line && (
          line.manufacturer || line.product_code || line.product_name
          || (line.quantity !== '' && line.quantity != null)
          || (line.unit_price !== '' && line.unit_price != null)
          || (line.amount_with_tax !== '' && line.amount_with_tax != null)
        ));
      }
      if (this.data.item_name || this.data.category) {
        return [{
          manufacturer: '',
          product_code: '',
          product_name: this.data.item_name || '',
          quantity: this.data.quantity,
          unit_price: this.data.estimated_price,
          amount_with_tax: ''
        }];
      }
      return [];
    },
    totalAmountDisplay() {
      if (this.data.total_amount != null && this.data.total_amount !== '') {
        return Number(this.data.total_amount).toLocaleString('ja-JP');
      }
      const sum = this.displayLines.reduce((s, line) => s + (Number(line.amount_with_tax) || 0), 0);
      return sum.toLocaleString('ja-JP');
    }
  },
  methods: {
    formatAmount(value) {
      const n = Number(value);
      if (isNaN(n)) return '-';
      return '¥' + n.toLocaleString('ja-JP');
    }
  },
  template: `
    <table class="table">
      <tbody>
        <tr v-if="displayLines.length">
          <th>購入品目</th>
          <td class="p-0">
            <table class="table table-sm mb-0">
              <thead>
                <tr>
                  <th>メーカー</th>
                  <th>商品コード</th>
                  <th>商品名</th>
                  <th class="text-end">数量</th>
                  <th class="text-end">単価</th>
                  <th class="text-end">金額（税込み）</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(line, idx) in displayLines" :key="idx">
                  <td>{{ line.manufacturer || '-' }}</td>
                  <td>{{ line.product_code || '-' }}</td>
                  <td>{{ line.product_name || '-' }}</td>
                  <td class="text-end">{{ line.quantity != null && line.quantity !== '' ? line.quantity : '-' }}</td>
                  <td class="text-end">{{ line.unit_price != null && line.unit_price !== '' ? formatAmount(line.unit_price) : '-' }}</td>
                  <td class="text-end">{{ formatAmount(line.amount_with_tax) }}</td>
                </tr>
              </tbody>
            </table>
          </td>
        </tr>
        <tr v-if="displayLines.length || data.total_amount != null">
          <th>合計金額</th>
          <td class="fw-bold">¥{{ totalAmountDisplay }}</td>
        </tr>
        <tr v-if="data.reason">
          <th>事由・用途</th>
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
