<?php
/**
 * Read-only customer list of a parent project (建物): same table as customer-list-editor.php,
 * without the editing controls. Rendered inside the Vue root of detail.php (view mode).
 * Needs ParentProjectCustomersMixin.
 * $customerListViewRowDetail = true: no 顧客情報 button; each row gets a 詳細 button that opens the
 * customer detail modal directly (the host must provide showCustomerInfoModal).
 * $customerListViewBare = true: no 顧客 caption and no frame — the list is the body of a titled
 * section table (.pd-section).
 */
$customerListViewRowDetail = !empty($customerListViewRowDetail);
$customerListViewBare = !empty($customerListViewBare);
?>
<div class="col-12">
    <div class="<?php echo $customerListViewBare ? '' : 'mb-3'; ?>">
<?php if (!$customerListViewBare): ?>
        <label class="form-label d-flex flex-wrap align-items-center gap-2">
            <span><span data-i18n="顧客">顧客</span></span>
<?php if (!$customerListViewRowDetail): ?>
            <button v-if="customerList.length > 0" type="button" class="btn btn-sm btn-outline-info py-0 small"
                @click="openCustomerListModal" title="顧客情報表示・編集">
                <i class="fa fa-info-circle me-1"></i> <span data-i18n="顧客情報">顧客情報</span>
            </button>
<?php endif; ?>
        </label>
<?php endif; ?>
        <div class="table-responsive <?php echo $customerListViewBare ? 'pd-bare-table' : 'border rounded'; ?>">
            <table class="table table-sm align-middle mb-0 parent-project-customer-table">
                <thead class="table-light">
                    <tr>
                        <th class="text-center text-nowrap" style="width: 56px;"><span data-i18n="主">主</span></th>
                        <th><span data-i18n="会社名">会社名</span></th>
                        <th><span data-i18n="支店名">支店名</span></th>
                        <th><span data-i18n="担当様">担当様</span></th>
<?php if ($customerListViewRowDetail): ?>
                        <th style="width: 96px;"></th>
<?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="customerList.length === 0">
                        <td colspan="<?php echo $customerListViewRowDetail ? 5 : 4; ?>" class="text-muted text-center py-3">
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
<?php if ($customerListViewRowDetail): ?>
                        <td class="text-end">
                            <button v-if="customer.customer_id" type="button" class="btn btn-sm btn-outline-info py-0 text-nowrap"
                                @click="openCustomerInfoFromList(customer)" title="顧客情報表示・編集">
                                <i class="fa fa-info-circle me-1"></i> <span data-i18n="詳細">詳細</span>
                            </button>
                        </td>
<?php endif; ?>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
