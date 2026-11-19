<?php
/**
 * 顧客検索: search the customer master and add results to the parent project's customer list.
 * Element id: $customerSearchModalId (default customerSearchModal) — must match the host's customerSearchModalId.
 * Needs ParentProjectCustomersMixin. Styles (.customer-search-*) live in the host page's <style> block,
 * because <style> tags inside a Vue root template are ignored.
 */
?>
<div class="modal fade" id="<?php echo isset($customerSearchModalId) ? $customerSearchModalId : 'customerSearchModal'; ?>" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><span data-i18n="顧客検索">顧客検索</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label"><span data-i18n="会社名">会社名</span></label>
                        <!-- Options are filled by select2 (initCustomerSearchSelects); 大東 / 東建 / ナミキ first -->
                        <select class="form-select customer-search-company-select"></select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><span data-i18n="支店名">支店名</span></label>
                        <select class="form-select customer-search-branch-select" disabled></select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label"><span data-i18n="キーワード">キーワード</span></label>
                        <input
                            type="text"
                            class="form-control"
                            v-model="customerSearchKeyword"
                            @input="searchCustomersByKeyword"
                            placeholder="会社名・支店名・担当者名・電話・メール..."
                            autocomplete="off">
                    </div>
                    <div class="col-12 form-text text-muted mt-1">
                        <span data-i18n="行をクリックすると顧客リストに追加されます。">行をクリックすると顧客リストに追加されます。</span>
                    </div>
                </div>
                <div v-if="customerSearchLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">読み込み中...</span>
                    </div>
                </div>
                <div v-else-if="!hasCustomerSearchCriteria" class="text-muted text-center py-3">
                    <span data-i18n="会社を選択するか、キーワードを入力してください">会社を選択するか、キーワードを入力してください</span>
                </div>
                <div v-else-if="customerSearchResults.length === 0" class="text-muted text-center py-3">
                    <span data-i18n="該当する顧客が見つかりません">該当する顧客が見つかりません</span>
                </div>
                <div v-else class="table-responsive customer-search-results">
                    <table class="table table-hover table-sm mb-0" style="table-layout: fixed; width: 100%; overflow-wrap: anywhere;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 19%;"><span data-i18n="会社名">会社名</span></th>
                                <th style="width: 13%;"><span data-i18n="支店名">支店名</span></th>
                                <th style="width: 10%;"><span data-i18n="担当者名">担当者名</span></th>
                                <th style="width: 12%;"><span data-i18n="自社担当部署名">自社担当部署名</span></th>
                                <th style="width: 13%;"><span data-i18n="電話">電話</span></th>
                                <th><span data-i18n="メール">メール</span></th>
                                <th style="width: 150px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="customer in customerSearchResults"
                                :key="customer.id"
                                class="customer-search-row"
                                :class="{ 'customer-search-row-added': isCustomerInList(customer.id) }"
                                @click="selectCustomerFromSearch(customer)">
                                <td>{{ customer.company_name }}</td>
                                <td>{{ customer.branch }}</td>
                                <td>{{ customer.name }}</td>
                                <td>{{ customerSearchDepartmentLabel(customer) }}</td>
                                <td>{{ customer.tel || customer.phone }}</td>
                                <td>{{ customer.email }}</td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-info py-0 me-1" title="顧客情報表示・編集"
                                        @click.stop="openCustomerInfoFromSearch(customer)">
                                        <i class="fa fa-info-circle me-1"></i><span data-i18n="詳細">詳細</span>
                                    </button>
                                    <span v-if="isCustomerInList(customer.id)" class="badge bg-label-success"><span data-i18n="追加済み">追加済み</span></span>
                                    <span v-else class="badge bg-label-primary"><i class="fa fa-plus me-1"></i><span data-i18n="追加">追加</span></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="!customerSearchLoading && customerSearchMatchCount > customerSearchResults.length" class="small text-warning mt-2">
                    {{ customerSearchMatchCount }}<span data-i18n="件中、先頭100件を表示しています。支店名・キーワードで絞り込んでください。">件中、先頭100件を表示しています。支店名・キーワードで絞り込んでください。</span>
                </div>
            </div>
            <div class="modal-footer">
                <span class="me-auto text-muted small">
                    <span data-i18n="顧客リスト">顧客リスト</span>: {{ customerList.length }}<span data-i18n="件">件</span>
                </span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><span data-i18n="閉じる">閉じる</span></button>
            </div>
        </div>
    </div>
</div>
