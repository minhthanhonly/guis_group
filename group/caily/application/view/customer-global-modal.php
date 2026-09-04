<div id="global-customer-modal-app">
    <div class="modal fade" id="globalCustomerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <template v-if="editingCustomer">
                            {{ selectedCategory ? selectedCategory.name + ' - ' : '' }}<span data-i18n="顧客情報">顧客情報</span>
                        </template>
                        <template v-else>
                            {{ selectedCategory ? selectedCategory.name + ' - ' : '' }}<span data-i18n="新規顧客">新規顧客</span>
                        </template>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-body">
                    <div v-if="editingCustomer" class="alert alert-warning mb-3 mb-md-4" role="alert">
                        <i class="fa fa-exclamation-triangle me-2"></i>
                        <span data-i18n="お客様情報を更新すると、このお客様の情報を利用している他の建物の情報もすべて更新されます。">お客様情報を更新すると、このお客様の情報を利用している他の建物の情報もすべて更新されます。</span>
                    </div>
                    <form @submit.prevent="saveCustomer">
                        <fieldset :disabled="!canEditCustomer">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="カテゴリー">カテゴリー</span></label>
                                    <select class="form-select" v-model="newCustomer.category_id" required>
                                        <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="会社名/支店名">会社名/支店名</span> <span v-if="canEditCustomer" class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="newCustomer.company_name" required>
                                    <div v-if="customerErrors.company_name" class="text-danger small mt-1">{{ customerErrors.company_name }}</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="会社名/支店名(ふりがな)">会社名/支店名(ふりがな)</span></label>
                                    <input type="text" class="form-control" v-model="newCustomer.company_name_kana" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="担当者名">担当者名</span> <span v-if="canEditCustomer" class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="newCustomer.name" required>
                                    <div v-if="customerErrors.name" class="text-danger small mt-1">{{ customerErrors.name }}</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="担当者名(ふりがな)">担当者名(ふりがな)</span></label>
                                    <input type="text" class="form-control" v-model="newCustomer.name_kana" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="支店名">支店名</span> <span v-if="canEditCustomer" class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="newCustomer.branch" :class="{ 'is-invalid': customerErrors.branch }" required>
                                    <div v-if="customerErrors.branch" class="invalid-feedback">{{ customerErrors.branch }}</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="担当部署">担当部署</span></label>
                                    <input type="text" class="form-control" v-model="newCustomer.department" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="役職">役職</span></label>
                                    <input type="text" class="form-control" v-model="newCustomer.position" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="敬称">敬称</span></label>
                                    <select class="form-select" v-model="newCustomer.title" required>
                                        <option>様</option>
                                        <option>御社</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="メールアドレス">メールアドレス</span></label>
                                    <input type="text" class="form-control" v-model="newCustomer.email" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="電話番号">電話番号</span></label>
                                    <input type="text" class="form-control" v-model="newCustomer.tel" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">FAX</label>
                                    <input type="text" class="form-control" v-model="newCustomer.fax" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="携帯番号">携帯番号</span></label>
                                    <input type="text" class="form-control" v-model="newCustomer.phone" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="郵便番号">郵便番号</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" v-model="newCustomer.zip" required>
                                        <button v-if="canEditCustomer" class="btn btn-outline-primary waves-effect" type="button" @click.prevent="searchAddressCustomer">住所検索</button>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="住所1">住所1</span></label>
                                    <input type="text" class="form-control" v-model="newCustomer.address1" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="住所2">住所2</span></label>
                                    <input type="text" class="form-control" v-model="newCustomer.address2" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="状況">状況</span></label>
                                    <select class="form-select" v-model="newCustomer.status" required>
                                        <option value="1">有効</option>
                                        <option value="0">無効</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><span data-i18n="自社担当部署名">自社担当部署名</span> <span v-if="canEditCustomer" class="text-danger">*</span></label>
                                    <select ref="guisDepartmentSelect" class="form-select select2" v-model="newCustomer.guis_department" required multiple>
                                        <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                                    </select>
                                    <div v-if="customerErrors.guis_department" class="text-danger small mt-1">{{ customerErrors.guis_department }}</div>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label"><span data-i18n="メモ">メモ</span></label>
                                    <textarea class="form-control" v-model="newCustomer.memo" required></textarea>
                                </div>
                            </div>
                        </fieldset>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <span v-if="canEditCustomer" data-i18n="キャンセル">キャンセル</span>
                        <span v-else data-i18n="閉じる">閉じる</span>
                    </button>
                    <button v-if="canEditCustomer" type="button" class="btn btn-primary" @click="saveCustomer" :disabled="savingCustomer">
                        <span v-if="savingCustomer" class="spinner-border spinner-border-sm me-1"></span>
                        <span data-i18n="更新">更新</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
