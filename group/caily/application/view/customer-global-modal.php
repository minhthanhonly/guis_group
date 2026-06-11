<div id="global-customer-modal-app">
    <div class="modal fade" id="globalCustomerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ selectedCategory ? selectedCategory.name : '' }} - {{ editingCustomer ? '顧客編集' : '新規顧客' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="saveCustomer">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">カテゴリー</label>
                                <select class="form-select" v-model="newCustomer.category_id" required>
                                    <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">会社名/支店名 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" v-model="newCustomer.company_name" required>
                                <div v-if="customerErrors.company_name" class="text-danger small mt-1">{{ customerErrors.company_name }}</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">会社名/支店名(ふりがな)</label>
                                <input type="text" class="form-control" v-model="newCustomer.company_name_kana" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">担当者名 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" v-model="newCustomer.name" required>
                                <div v-if="customerErrors.name" class="text-danger small mt-1">{{ customerErrors.name }}</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">担当者名(ふりがな)</label>
                                <input type="text" class="form-control" v-model="newCustomer.name_kana" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">支店名 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" v-model="newCustomer.branch" :class="{ 'is-invalid': customerErrors.branch }" required>
                                <div v-if="customerErrors.branch" class="invalid-feedback">{{ customerErrors.branch }}</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">担当部署</label>
                                <input type="text" class="form-control" v-model="newCustomer.department" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">役職</label>
                                <input type="text" class="form-control" v-model="newCustomer.position" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">敬称</label>
                                <select class="form-select" v-model="newCustomer.title" required>
                                    <option>様</option>
                                    <option>御社</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">メールアドレス</label>
                                <input type="text" class="form-control" v-model="newCustomer.email" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">電話番号</label>
                                <input type="text" class="form-control" v-model="newCustomer.tel" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">FAX</label>
                                <input type="text" class="form-control" v-model="newCustomer.fax" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">携帯番号</label>
                                <input type="text" class="form-control" v-model="newCustomer.phone" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">郵便番号</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" v-model="newCustomer.zip" required>
                                    <button class="btn btn-outline-primary waves-effect" type="button" @click.prevent="searchAddressCustomer">住所検索</button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">住所1</label>
                                <input type="text" class="form-control" v-model="newCustomer.address1" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">住所2</label>
                                <input type="text" class="form-control" v-model="newCustomer.address2" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">状況</label>
                                <select class="form-select" v-model="newCustomer.status" required>
                                    <option value="1">有効</option>
                                    <option value="0">無効</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">自社担当部署名 <span class="text-danger">*</span></label>
                                <select ref="guisDepartmentSelect" class="form-select select2" v-model="newCustomer.guis_department" required multiple>
                                    <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                                </select>
                                <div v-if="customerErrors.guis_department" class="text-danger small mt-1">{{ customerErrors.guis_department }}</div>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">メモ</label>
                                <textarea class="form-control" v-model="newCustomer.memo" required></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-primary" @click="saveCustomer">保存</button>
                </div>
            </div>
        </div>
    </div>
</div>
