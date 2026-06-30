<?php
require_once('../application/loader.php');
$view->heading('顧客情報');

?>
    
    <div id="app" v-cloak>
        <nav class="navbar navbar-expand-lg bg-dark mb-12">
            <div class="container-fluid">
                <span class="navbar-brand" href="javascript:void(0)"></span>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse justify-content-start" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item" v-for="department in departments" :key="department.id" :class="{ 'active bg-primary text-white rounded-3': selectedDepartment && String(selectedDepartment.id) === String(department.id) }">
                            <a href="#" class="nav-link" @click.prevent="viewDepartment(department)">{{ department.name }}</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        <div id="departmentContent" v-if="showDepartmentContent">
            <div class="container-fluid mt-4">
                <div class="row">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2>{{ selectedDepartment.name }} - 顧客情報</h2>
                            <div class="d-flex gap-2">
                                <a href="<?=ROOT?>customer/import.php" class="btn btn-outline-primary">
                                    <i class="icon-base ti tabler-file-spreadsheet me-1"></i> Excel一括登録
                                </a>
                                <button class="btn btn-primary" @click.prevent="openNewCustomerModal">
                                    <i class="bi bi-plus"></i> 新規顧客
                                </button>
                            </div>
                        </div>
                        <div class="card mb-3">
                            <div class="card-body py-3">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-5 col-lg-4">
                                        <label class="form-label mb-1 small">キーワード検索</label>
                                        <input
                                            type="text"
                                            class="form-control form-control-sm"
                                            v-model="customerSearchKeyword"
                                            placeholder="会社名・支店名・担当者名・電話・メール..."
                                            autocomplete="off">
                                    </div>
                                    <div class="col-md-auto">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="resetCustomerFilters">
                                            リセット
                                        </button>
                                    </div>
                                    <div class="col-md-auto ms-md-auto text-muted small pb-1">
                                        {{ filteredCustomers.length }} / {{ customerListDenominator }} 件
                                        <span v-if="isGlobalCustomerSearch" class="text-primary ms-1">(全部署)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive" id="customerTable">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>会社名</th>
                                        <th>支店名</th>
                                        <th>担当者名</th>
                                        <!-- <th>敬称</th> -->
                                        <th>担当部署</th>
                                        <th>役職</th>
                                        <th>電話番号</th>
                                        <th>携帯</th>
                                        <th>メールアドレス</th>
                                        <th class="text-nowrap">自社担当部署名</th>
                                        <!-- <th>住所</th> -->
                                        <th class="text-nowrap">メモ</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="filteredCustomers.length === 0">
                                        <td colspan="11" class="text-center text-muted py-4">
                                            {{ isGlobalCustomerSearch
                                                ? (allCustomers.length === 0 ? '顧客がありません' : '条件に一致する顧客がありません')
                                                : (departmentCustomers.length === 0 ? '顧客がありません' : '条件に一致する顧客がありません') }}
                                        </td>
                                    </tr>
                                    <tr v-for="customer in filteredCustomers" :key="customer.id" :class="{ 'bg-label-success': lastEditCustomer && lastEditCustomer.id === customer.id }">
                                        <td>{{ customer.company_name }}</td>
                                        <td>{{ customer.branch }}</td>
                                        <td class="text-nowrap">{{ customer.name }}</td>
                                        <!-- <td>{{ customer.title }}</td> -->
                                        <td>{{ customer.department }}</td>
                                        <td class="text-nowrap">{{ customer.position }}</td>
                                        <td class="text-nowrap">{{ customer.tel }}</td>
                                        <td class="text-nowrap">{{ customer.phone }}</td>
                                        <td>{{ customer.email }}</td>
                                        <td class="text-nowrap">{{ formatGuisDepartmentNames(customer) }}</td>
                                        <!-- <td>{{ customer.zip }} {{ customer.address1 }} {{ customer.address2 }}</td> -->
                                        <td>{{ customer.memo }}</td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" @click="editCustomer(customer)">
                                                    <i class="icon-base ti tabler-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" @click="deleteCustomer(customer)">
                                                    <i class="icon-base ti tabler-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- New/Edit customerModal -->
        <div class="modal fade" id="customerModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ selectedDepartment ? selectedDepartment.name : '' }} - {{ editingCustomer ? '顧客編集' : '新規顧客' }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form @submit.prevent="saveCustomer">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">会社名/支店名  <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="newCustomer.company_name" required>
                                    <div v-if="customerErrors.company_name" class="text-danger small mt-1">{{ customerErrors.company_name }}</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">会社名/支店名(ふりがな)</label>
                                    <input type="text" class="form-control" v-model="newCustomer.company_name_kana" required>
                                </div>
                            
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">担当者名  <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="newCustomer.name" required>
                                    <div v-if="customerErrors.name" class="text-danger small mt-1">{{ customerErrors.name }}</div>
                                </div> 
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">担当者名(ふりがな)</label>
                                    <input type="text" class="form-control" v-model="newCustomer.name_kana" required>
                                </div>
                            
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">支店名 <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" v-model="newCustomer.branch" 
                                           :class="{ 'is-invalid': customerErrors.branch }" required>
                                    <div v-if="customerErrors.branch" class="invalid-feedback">
                                        {{ customerErrors.branch }}
                                    </div>
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
                                    <div class="input-group">
                                        <input type="text" class="form-control" v-model="newCustomer.phone" required>
                                    </div>
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
                                    <label class="form-label">自社担当部署名  <span class="text-danger">*</span></label>
                                    <select ref="guisDepartmentSelect" class="form-select select2" v-model="newCustomer.guis_department" required multiple>
                                        <option v-for="department in allDepartments" :key="department.id" :value="department.id">{{ department.name }}</option>
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
<style>
    #customerTable {
       font-size: 0.875rem;
    }
</style>
<?php
$view->footing();
?> 
    <script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
    <script>
        const { createApp } = Vue;
        
        const OTHER_DEPARTMENT = { id: 'other', name: 'その他', isOther: true };

        createApp({
            data() {
                return {
                    departments: [],
                    userDepartments: [],
                    allDepartments: [],
                    departmentNameMap: {},
                    selectedDepartment: null,
                    showDepartmentContent: false,
                    customerSearchKeyword: '',

                    customers: [],
                    editingCustomer: null,
                    lastEditCustomer: null,
                    newCustomer: {
                        company_name: '',
                        company_name_kana: '',
                        name: '',
                        name_kana: '',
                        branch: '',
                        position: '',
                        department: '',
                        title: '',
                        tel: '',
                        fax: '',
                        phone: '',
                        email: '',
                        zip: '',
                        address1: '',
                        address2: '',
                        memo: '',
                        status: 1,
                        category_id: 0,
                        guis_department: [],
                    },
                    customerErrors: {
                        company_name: '',
                        name: '',
                        branch: '',
                        guis_department: ''
                    }
                }
            },
            computed: {
                isGlobalCustomerSearch() {
                    return (this.customerSearchKeyword || '').trim() !== '';
                },
                departmentCustomers() {
                    if (!this.selectedDepartment) {
                        return [];
                    }
                    if (this.isOtherDepartment(this.selectedDepartment)) {
                        return (this.allCustomers || []).filter((customer) => !this.customerHasGuisDepartment(customer));
                    }
                    if (!this.selectedDepartment.id) {
                        return [];
                    }
                    const deptId = String(this.selectedDepartment.id);
                    return (this.allCustomers || []).filter((customer) => {
                        return this.getCustomerGuisDepartmentIds(customer).includes(deptId);
                    });
                },
                filteredCustomers() {
                    const keyword = (this.customerSearchKeyword || '').trim().toLowerCase();
                    if (keyword) {
                        return (this.allCustomers || []).filter((customer) => this.customerMatchesKeyword(customer, keyword));
                    }
                    return this.departmentCustomers;
                },
                customerListDenominator() {
                    return this.isGlobalCustomerSearch
                        ? (this.allCustomers || []).length
                        : this.departmentCustomers.length;
                },
            },
            methods: {
                isOtherDepartment(department) {
                    return !!(department && (department.isOther === true || String(department.id) === 'other'));
                },
                customerHasGuisDepartment(customer) {
                    return this.getCustomerGuisDepartmentIds(customer).length > 0;
                },
                async loadAllDepartments() {
                    try {
                        const response = await axios.get('/api/index.php?model=department&method=list_department');
                        this.allDepartments = response.data || [];
                        this.departments = [...this.allDepartments, OTHER_DEPARTMENT];
                        const map = {};
                        this.allDepartments.forEach((department) => {
                            if (department && department.id != null) {
                                map[String(department.id)] = department.name || '';
                            }
                        });
                        this.departmentNameMap = map;
                    } catch (error) {
                        console.error('Error loading all departments:', error);
                        this.allDepartments = [];
                        this.departments = [];
                        this.departmentNameMap = {};
                    }
                },
                normalizeCustomerRow(customer) {
                    if (!customer) return customer;
                    if (typeof customer.guis_department === 'string' && customer.guis_department) {
                        customer.guis_department = customer.guis_department.split(',').filter(Boolean);
                    } else if (!Array.isArray(customer.guis_department)) {
                        customer.guis_department = [];
                    }
                    return customer;
                },
                getCustomerGuisDepartmentIds(customer) {                    if (!customer) return [];
                    if (Array.isArray(customer.guis_department)) {
                        return customer.guis_department.map((id) => String(id)).filter(Boolean);
                    }
                    if (typeof customer.guis_department === 'string' && customer.guis_department) {
                        return customer.guis_department.split(',').map((id) => String(id).trim()).filter(Boolean);
                    }
                    return [];
                },
                formatGuisDepartmentNames(customer) {
                    const names = this.getCustomerGuisDepartmentIds(customer)
                        .map((id) => this.departmentNameMap[id] || '')
                        .filter(Boolean);
                    return names.length > 0 ? names.join(', ') : '—';
                },
                customerMatchesKeyword(customer, keyword) {
                    if (!customer || !keyword) return true;
                    const fields = [
                        customer.company_name,
                        customer.company_name_kana,
                        customer.branch,
                        customer.name,
                        customer.name_kana,
                        customer.department,
                        customer.position,
                        customer.tel,
                        customer.phone,
                        customer.email,
                        customer.memo,
                        this.formatGuisDepartmentNames(customer),
                    ];
                    return fields.some((value) => String(value || '').toLowerCase().includes(keyword));
                },
                resetCustomerFilters() {
                    this.customerSearchKeyword = '';
                },
                async loadUserDepartments() {
                    try {
                        const response = await axios.get('/api/index.php?model=department&method=listByUser');
                        this.userDepartments = response.data || [];
                    } catch (error) {
                        console.error('Error loading user departments:', error);
                        this.userDepartments = [];
                        showMessage('部署の読み込みに失敗しました。', true);
                    }
                },
                selectInitialDepartment() {
                    if (this.selectedDepartment) {
                        this.showDepartmentContent = true;
                        return;
                    }
                    let defaultDepartment = null;
                    if (this.userDepartments.length > 0) {
                        const userDeptId = String(this.userDepartments[0].id);
                        defaultDepartment = this.departments.find((d) => String(d.id) === userDeptId);
                    }
                    if (!defaultDepartment && this.departments.length > 0) {
                        defaultDepartment = this.departments[0];
                    }
                    if (defaultDepartment) {
                        this.viewDepartment(defaultDepartment, { resetFilters: false });
                    }
                },
                viewDepartment(department, options = {}) {
                    if (!department || (department.id == null && !department.isOther)) {
                        return;
                    }
                    this.selectedDepartment = department;
                    this.showDepartmentContent = true;
                    if (options.resetFilters !== false) {
                        this.resetCustomerFilters();
                    }
                },

                async loadAllCustomers() {
                    try {
                        const response = await axios.get('/api/index.php?model=customer&method=list_customer&all=1');
                        if (response.data.status == 'success' && Array.isArray(response.data.data)) {
                            this.allCustomers = response.data.data.map((customer) => this.normalizeCustomerRow({ ...customer }));
                        } else {
                            this.allCustomers = [];
                        }
                    } catch (error) {
                        console.error('Error loading customers:', error);
                        this.allCustomers = [];
                        showMessage('担当者の読み込みに失敗しました。', true);
                    }
                },

                editCustomer(customer) {
                    this.editingCustomer = customer;
                    this.newCustomer = { ...customer };
                    this.$nextTick(() => {
                        $(this.$refs.guisDepartmentSelect).val(this.newCustomer.guis_department).trigger('change');
                    });
                    $('#customerModal').modal('show');
                },
                openNewCustomerModal() {
                    this.resetCustomerData();
                    if (this.selectedDepartment && this.selectedDepartment.id && !this.isOtherDepartment(this.selectedDepartment)) {
                        this.newCustomer.guis_department = [String(this.selectedDepartment.id)];
                    }
                    $('#customerModal').modal('show');
                    this.$nextTick(() => {
                        if (this.$refs.guisDepartmentSelect) {
                            $(this.$refs.guisDepartmentSelect).val(this.newCustomer.guis_department).trigger('change');
                        }
                    });
                },
                async deleteCustomer(customer) {
                    if (!confirm('この担当者を削除してもよろしいですか？')) {
                        return;
                    }
                    try {
                        const response = await axios.post('/api/index.php?model=customer&method=delete_customer&id=' + customer.id);
                        if (response.data && response.data.status === 'success') {
                            showMessage('担当者を削除しました。');
                            this.loadAllCustomers();
                            return;
                        }

                        const message = response.data && response.data.message_code
                            ? response.data.message_code
                            : '担当者の削除に失敗しました。';
                        showMessage(message, true);
                    } catch (error) {
                        console.error('Error deleting customer:', error);
                        const message = error.response && error.response.data && error.response.data.message_code
                            ? error.response.data.message_code
                            : '担当者の削除に失敗しました。';
                        showMessage(message, true);
                    }
                },
                async saveCustomer() {
                    // Reset errors
                    this.customerErrors = { company_name: '', name: '', branch: '', guis_department: '' };
                    let hasError = false;
                    if (!this.newCustomer.company_name) {
                        this.customerErrors.company_name = '会社名は必須です。';
                        hasError = true;
                    }
                    if (!this.newCustomer.name) {
                        this.customerErrors.name = '担当者名は必須です。';
                        hasError = true;
                    }
                    if (!this.newCustomer.branch || this.newCustomer.branch.trim() === '') {
                        this.customerErrors.branch = '支店名は必須です。';
                        hasError = true;
                    }
                    if (!this.newCustomer.guis_department || this.newCustomer.guis_department.length === 0) {
                        this.customerErrors.guis_department = '自社担当部署名は必須です。';
                        hasError = true;
                    }
                    if (hasError) return;
                    if (!this.newCustomer.category_id) {
                        this.newCustomer.category_id = 2;
                    }
                    try {
                        $reponse = null;
                        if (this.editingCustomer) {
                            $reponse = await axios.post('/api/index.php?model=customer&method=edit_customer&id=' + this.editingCustomer.id, this.newCustomer,
                                {
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded'
                                    }
                                }
                            );
                        } else {
                            $reponse = await axios.post('/api/index.php?model=customer&method=add_customer', this.newCustomer, {
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                }
                            });
                        }
                        if ($reponse.data.status == 'success') {
                            showMessage('担当者を保存しました。');
                            
                            // If editing customer, update all parent projects with the same customer_id
                            if (this.editingCustomer) {
                                try {
                                    const formData = new URLSearchParams();
                                    formData.append('customer_id', this.editingCustomer.id);
                                    formData.append('company_name', this.newCustomer.company_name);
                                    formData.append('branch_name', this.newCustomer.branch);
                                    formData.append('contact_name', this.newCustomer.name);
                                    
                                    console.log('Updating parent projects for customer:', {
                                        customer_id: this.editingCustomer.id,
                                        company_name: this.newCustomer.company_name,
                                        branch_name: this.newCustomer.branch,
                                        contact_name: this.newCustomer.name
                                    });
                                    
                                    const updateResponse = await axios.post(`/api/index.php?model=parentproject&method=updateCustomerInfoForAllProjects`, formData, {
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded'
                                        }
                                    });
                                    
                                    console.log('Parent projects update response:', updateResponse.data);
                                    
                                    if (updateResponse.data.status === 'success') {
                                        console.log(`Updated ${updateResponse.data.affected_rows} parent project(s)`);
                                    } else {
                                        console.error('Failed to update parent projects:', updateResponse.data.message);
                                    }
                                } catch (error) {
                                    console.error('Error updating parent projects:', error);
                                }
                            }
                            
                            await this.loadAllCustomers();
                            if (this.selectedDepartment) {
                                this.showDepartmentContent = true;
                            }
                            this.lastEditCustomer = this.newCustomer;
                            this.resetCustomerData();
                            $('#customerModal').modal('hide');
                        } else {
                            showMessage($reponse.data.message_code, true);
                            this.lastEditCustomer = null;
                        }
                    } catch (error) {
                        console.error('Error saving customer:', error);
                        showMessage('担当者の保存に失敗しました。', true);
                    }
                },
                resetCustomerData() {
                    this.editingCustomer = null;
                    this.newCustomer = {
                        company_name: '',
                        company_name_kana: '',
                        name: '',
                        name_kana: '',
                        branch: '',
                        position: '',
                        department: '',
                        title: '',
                        tel: '',
                        fax: '',
                        phone: '',
                        email: '',
                        zip: '',
                        address1: '',
                        address2: '',
                        memo: '',
                        category_id: 2,
                        status: 1,
                        guis_department: this.selectedDepartment && this.selectedDepartment.id && !this.isOtherDepartment(this.selectedDepartment)
                            ? [String(this.selectedDepartment.id)]
                            : [],
                    };
                },

                searchAddressCustomer() {
                    const postalCode = this.newCustomer.zip;
                    if (postalCode.length >= 7) {
                        const apiUrl = `https://zipcloud.ibsnet.co.jp/api/search?zipcode=${postalCode}`;
                        axios.get(apiUrl)
                            .then(response => {
                                if (response.data.results && response.data.results.length > 0) {
                                    const result = response.data.results[0];
                                    this.newCustomer.address1 = result.address1;
                                    this.newCustomer.address2 = result.address2;
                                } else {
                                    showMessage('郵便番号が見つかりません。', true);
                                }
                            })
                            .catch(error => {
                                console.error('Error searching address:', error);
                                showMessage('住所の検索に失敗しました。', true);
                            });
                    } else {
                        showMessage('郵便番号が正しくありません。1', true);  
                    }
                },
            },
            async mounted() {
                await Promise.all([
                    this.loadAllDepartments(),
                    this.loadAllCustomers(),
                    this.loadUserDepartments(),
                ]);
                this.selectInitialDepartment();
                // Initialize select2
                this.$nextTick(() => {
                    const selectElement = $(this.$refs.guisDepartmentSelect);
                    selectElement.select2();
                    selectElement.on('change', (event) => {
                        const val = $(event.target).val();
                        this.newCustomer.guis_department = val ? val : [];
                    });
                });
            }
        }).mount('#app');
    </script>