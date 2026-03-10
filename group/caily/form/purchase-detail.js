export default {
  props: { data: { type: Object, required: true } },
  computed: {
    categoryLabel() {
      return this.data.category || '';
    },
    estimatedPriceDisplay() {
      const v = this.data.estimated_price;
      if (v === null || v === undefined || v === '') return '';
      const n = Number(v);
      if (isNaN(n)) return String(v);
      return n.toLocaleString('ja-JP') + ' 円';
    }
  },
  template: `
    <table class="table">
      <tbody>
        <tr v-if="data.category">
          <th>購入区分</th>
          <td>{{ categoryLabel }}</td>
        </tr>
        <tr v-if="data.item_name">
          <th>品名</th>
          <td>{{ data.item_name }}</td>
        </tr>
        <tr v-if="data.product_link">
          <th>商品リンク</th>
          <td><a :href="data.product_link" target="_blank" rel="noopener noreferrer" class="text-break">{{ data.product_link }}</a></td>
        </tr>
        <tr v-if="data.quantity != null && data.quantity !== ''">
          <th>数量</th>
          <td>{{ data.quantity }}</td>
        </tr>
        <tr v-if="data.estimated_price != null && data.estimated_price !== ''">
          <th>見積金額</th>
          <td>{{ estimatedPriceDisplay }}</td>
        </tr>
        <tr v-if="data.item_list">
          <th>購入品目詳細</th>
          <td class="text-break" style="white-space: pre-wrap;">{{ data.item_list }}</td>
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
