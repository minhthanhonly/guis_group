<?php
/**
 * Read-only customer list of a parent project (建物): same table as customer-list-editor.php,
 * without the editing controls. Rendered inside the Vue root of detail.php (view mode).
 * Needs ParentProjectCustomersMixin.
 */
?>
<div class="col-12">
    <div class="mb-3">
        <label class="form-label d-flex flex-wrap align-items-center gap-2">
            <span><span data-i18n="顧客">顧客</span></span>
            <button v-if="customerList.length > 0" type="button" class="btn btn-sm btn-outline-info py-0 small"
                @click="openCustomerListModal" title="顧客情報表示・編集">
                <i class="fa fa-info-circle me-1"></i> <span data-i18n="顧客情報">顧客情報</span>
            </button>
        </label>
        <div class="table-responsive border rounded">
            <table class="table table-sm align-middle mb-0 parent-project-customer-table">
                <thead class="table-light">
                    <tr>
                        <th class="text-center text-nowrap" style="width: 56px;"><span data-i18n="主">主</span></th>
                        <th><span data-i18n="会社名">会社名</span></th>
                        <th><span data-i18n="支店名">支店名</span></th>
                        <th><span data-i18n="担当様">担当様</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="customerList.length === 0">
                        <td colspan="4" class="text-muted text-center py-3">
                            <span data-i18n="顧客が登録されていません">顧客が登録されていません</span>
                        </td>
                    </tr>
                    <tr v-for="(customer, index) in customerList" :key="'view-' + customerListKey(customer, index)">
                        <td class="text-center">
                            <i v-if="Number(customer.is_primary) === 1" class="fa fa-check-circle text-primary" title="主顧客（連絡先）"></i>
                        </td>
                        <td>
                            {{ customer.company_name || '-' }}
                            <span v-if="Number(customer.is_primary) === 1" class="badge bg-label-primary ms-1"><span data-i18n="主顧客">主顧客</span></span>
                            <span v-if="!customer.customer_id" class="badge bg-label-warning ms-1"><span data-i18n="未登録">未登録</span></span>
                        </td>
                        <td>{{ customer.branch_name || '-' }}</td>
                        <td>{{ customer.contact_name || '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
