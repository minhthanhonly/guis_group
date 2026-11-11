<?php
/**
 * Customer list editor of a parent project (建物): several customers, exactly one primary (主顧客).
 * Rendered inside the Vue root of create.php / detail.php (edit mode).
 * Needs ParentProjectCustomersMixin and the host method openNewCustomerModal().
 * Set $customerListEditorAllowNew = false before including when the host page has no
 * new-customer modal (the 新規顧客 button is then left out).
 */
?>
<div class="col-12">
    <div class="mb-3 form-control-validation">
        <label class="form-label d-flex flex-wrap align-items-center gap-2">
            <span><span data-i18n="顧客">顧客</span> <span class="text-danger">*</span></span>
            <button type="button" class="btn btn-sm btn-outline-info py-0 small" @click="openCustomerSearchModal" title="顧客検索">
                <i class="fa fa-search me-1"></i> <span data-i18n="顧客検索">顧客検索</span>
            </button>
            <?php if (!isset($customerListEditorAllowNew) || $customerListEditorAllowNew) { ?>
            <button type="button" class="btn btn-sm btn-outline-primary py-0 small" @click="openNewCustomerModal" title="新規顧客追加">
                <i class="fa fa-plus me-1"></i> <span data-i18n="新規顧客">新規顧客</span>
            </button>
            <?php } ?>
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
                        <th style="width: 48px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="customerList.length === 0">
                        <td colspan="5" class="text-muted text-center py-3">
                            <span data-i18n="「顧客検索」から顧客を追加してください">「顧客検索」から顧客を追加してください</span>
                        </td>
                    </tr>
                    <tr v-for="(customer, index) in customerList" :key="customerListKey(customer, index)">
                        <td class="text-center">
                            <input type="radio" class="form-check-input" name="parent_project_primary_customer"
                                :checked="Number(customer.is_primary) === 1"
                                @change="setPrimaryCustomer(index)"
                                title="主顧客（連絡先）にする">
                        </td>
                        <td>
                            {{ customer.company_name || '-' }}
                            <span v-if="Number(customer.is_primary) === 1" class="badge bg-label-primary ms-1"><span data-i18n="主顧客">主顧客</span></span>
                            <span v-if="!customer.customer_id" class="badge bg-label-warning ms-1"><span data-i18n="未登録">未登録</span></span>
                        </td>
                        <td>{{ customer.branch_name || '-' }}</td>
                        <td>{{ customer.contact_name || '-' }}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-icon btn-text-danger" @click="removeCustomerFromList(index)" title="リストから削除">
                                <i class="fa fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="customerListError" class="invalid-feedback d-block">
            {{ customerListError }}
        </div>
        <div v-if="hasUnlinkedCustomerRow" class="form-text text-warning">
            <span data-i18n="「未登録」の顧客は顧客マスタに無いため、保存時にリストから除外されます。">「未登録」の顧客は顧客マスタに無いため、保存時にリストから除外されます。</span>
        </div>
        <div class="form-text text-muted">
            <span data-i18n="「主」に選択した顧客が連絡先（主顧客）になります。">「主」に選択した顧客が連絡先（主顧客）になります。</span>
        </div>
    </div>
</div>
