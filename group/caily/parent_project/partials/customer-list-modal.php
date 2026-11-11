<?php
/**
 * 顧客情報: list of the customers of a parent project (建物). Each row opens the existing
 * customer detail / edit modal (#customerInfoModal). Needs ParentProjectCustomersMixin.
 * Element id: $customerListModalId (default customerListModal) — must match the host's customerListModalId.
 */
?>
<div class="modal fade" id="<?php echo isset($customerListModalId) ? $customerListModalId : 'customerListModal'; ?>" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><span data-i18n="顧客情報">顧客情報</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div v-if="customerList.length === 0" class="text-muted text-center py-3">
                    <span data-i18n="顧客が登録されていません">顧客が登録されていません</span>
                </div>
                <div v-else class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-nowrap" style="width: 72px;"></th>
                                <th style="min-width: 11em;"><span data-i18n="会社名">会社名</span></th>
                                <th style="min-width: 7em;"><span data-i18n="支店名">支店名</span></th>
                                <th><span data-i18n="担当様">担当様</span></th>
                                <th><span data-i18n="電話">電話</span></th>
                                <th><span data-i18n="メール">メール</span></th>
                                <th style="width: 96px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(customer, index) in customerList" :key="'info-' + customerListKey(customer, index)">
                                <td>
                                    <span v-if="Number(customer.is_primary) === 1" class="badge bg-label-primary"><span data-i18n="主顧客">主顧客</span></span>
                                </td>
                                <td>{{ customer.company_name || '-' }}</td>
                                <td>{{ customer.branch_name || '-' }}</td>
                                <td class="text-nowrap">{{ customer.contact_name || '-' }}</td>
                                <td class="text-nowrap">{{ customer.tel || customer.phone || '-' }}</td>
                                <td>{{ customer.email || '-' }}</td>
                                <td class="text-end">
                                    <button v-if="customer.customer_id" type="button" class="btn btn-sm btn-outline-info py-0 text-nowrap"
                                        @click="openCustomerInfoFromList(customer)" title="顧客情報表示・編集">
                                        <i class="fa fa-info-circle me-1"></i> <span data-i18n="詳細">詳細</span>
                                    </button>
                                    <span v-else class="badge bg-label-warning"><span data-i18n="未登録">未登録</span></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><span data-i18n="閉じる">閉じる</span></button>
            </div>
        </div>
    </div>
</div>
