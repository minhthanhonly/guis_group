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
                        <li class="nav-item" v-for="category in categories" :key="category.id" :class="{ 'active bg-primary text-white rounded-3': selectedCategory && selectedCategory.id === category.id }">
                            <a href="#" class="nav-link" @click="viewCategory(category)" >{{ category.name }}</a>
                        </li>
                    </ul>
                    <button class="btn btn-primary waves-effect waves-light" type="button" data-bs-toggle="modal" data-bs-target="#categoryListModal">
                        カテゴリー管理
                    </button>
                </div>
            </div>
        </nav>
        <div class="modal fade" id="categoryListModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">カテゴリー管理</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-end align-items-center mb-4">
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
                                        <i class="bi bi-plus"></i> 新規カテゴリー
                                    </button>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>カテゴリー名</th>
                                                <th>カテゴリー名(ふりがな)</th>
                                                <th>顧客数</th>
                                                <th>メモ</th>
                                                <th>操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="category in categories" :key="category.id">
                                                <td>{{ category.name }}</td>
                                                <td>{{ category.name_kana }}</td>
                                                <td>{{ category.num_customers }}</td>
                                                <td>{{ category.memo }}</td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        
                                                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#categoryModal" @click="editCategory(category)">
                                                            <i class="icon-base ti tabler-edit"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger" @click="deleteCategory(category)">
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
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- New/Edit categoryModal -->
        <div class="modal fade" id="categoryModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editingCategory ? 'カテゴリー編集' : '新規カテゴリー' }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form @submit.prevent="saveCategory">
                            <div class="mb-3">
                                <label class="form-label">カテゴリー名</label>
                                <input type="text" class="form-control" v-model="newCategory.name" required>
                            </div>  
                            <div class="mb-3">
                                <label class="form-label">カテゴリー名(ふりがな)</label>
                                <input type="text" class="form-control" v-model="newCategory.name_kana" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">メモ</label>
                                <textarea class="form-control" v-model="newCategory.memo" required></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="button" class="btn btn-primary" @click="saveCategory">保存</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="categoryContent" v-if="showCategoryContent">
            <div class="container-fluid mt-4">
                <div class="row">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2>{{ selectedCategory.name }} - 顧客情報</h2>
                            <button class="btn btn-primary" @click.prevent="openNewCustomerModal">
                                <i class="bi bi-plus"></i> 新規顧客
                            </button>
                        </div>
                        <div class="table-responsive">
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
                                        <!-- <th>住所</th> -->
                                        <th class="text-nowrap">メモ</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="customer in customers" :key="customer.id" :class="{ 'bg-label-success': lastEditCustomer && lastEditCustomer.id === customer.id }">
                                        <td>{{ customer.company_name }}</td>
                                        <td>{{ customer.branch }}</td>
                                        <td class="text-nowrap">{{ customer.name }}</td>
                                        <!-- <td>{{ customer.title }}</td> -->
                                        <td>{{ customer.department }}</td>
                                        <td class="text-nowrap">{{ customer.position }}</td>
                                        <td class="text-nowrap">{{ customer.tel }}</td>
                                        <td class="text-nowrap">{{ customer.phone }}</td>
                                        <td>{{ customer.email }}</td>
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
                        <h5 class="modal-title">{{ selectedCategory ? selectedCategory.name : '' }} - {{ editingCustomer ? '顧客編集' : '新規顧客' }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                                    <label class="form-label">支店名</label>
                                    <input type="text" class="form-control" v-model="newCustomer.branch" required>
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

<?php
$view->footing();
?> 
    <script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
    <script>
        const { createApp } = Vue;
        
        createApp({
            data() {
                return {
                    categories: [],
                    departments: [],
                    selectedCategory: null,
                    showCategoryContent: false,
                    editingCategory: null,
                    newCategory: {
                        name: '',
                        name_kana: '',
                        memo: ''
                    },

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
                        guis_department: ''
                    }
                }
            },
            methods: {

            // カテゴリー
                openNewCategoryModal() {
                    this.showNewCategoryModal = true;
                    this.resetCategoryData();
                },
                async loadDepartments() {
                    try {
                        const response = await axios.get('/api/index.php?model=department&method=list_department');
                        this.departments = response.data;
                    } catch (error) {
                        console.error('Error loading departments:', error);
                        showMessage('部署の読み込みに失敗しました。', true);
                    }
                },
                async loadCategories() {
                    try {
                        const response = await axios.get('/api/index.php?model=customer&method=list_category');
                        this.categories = response.data;
                        if(this.selectedCategory == null) {
                            this.selectedCategory = this.categories[0];
                            this.viewCategory(this.selectedCategory);
                        }
                    } catch (error) {
                        console.error('Error loading categories:', error);
                        showMessage('カテゴリーの読み込みに失敗しました。', true);
                    }
                },
                editCategory(category) {
                    this.editingCategory = category;
                    this.newCategory = { ...category };
                    this.showNewCategoryModal = true;
                },
                async deleteCategory(category) {
                    if (!confirm('このカテゴリーを削除してもよろしいですか？')) {
                        return;
                    }

                    try {
                        await axios.post('/api/index.php?model=customer&method=delete_category&id=' + category.id);
                        this.loadCategories();
                    } catch (error) {
                        console.error('Error deleting category:', error);
                        showMessage('カテゴリーの削除に失敗しました。', true);
                    }
                },
                async saveCategory() {
                    try {
                        if (this.editingCategory) {
                            await axios.post('/api/index.php?model=customer&method=edit_category&id=' + this.editingCategory.id, this.newCategory,
                                {
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded'
                                    }
                                }
                            );
                        } else {
                            await axios.post('/api/index.php?model=customer&method=add_category', this.newCategory, {
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                }
                            });
                        }
                        this.resetCategoryData();
                        $('#categoryModal').modal('hide');
                        showMessage('カテゴリーを保存しました。');
                        this.loadCategories();
                    } catch (error) {
                        console.error('Error saving category:', error);
                        showMessage('カテゴリーの保存に失敗しました。', true);
                    }
                },
                resetCategoryData() {
                    this.editingCategory = null;
                    this.newCategory = { name: '', name_kana: '', memo: '' };
                },
                viewCategory(category) {
                    this.selectedCategory = category;
                    this.showCategoryContent = true;
                    this.customers = [];
                    this.newCustomer.category_id = category.id;
                    this.loadCustomers();
                },
                
                // 担当者
                async loadCustomers() {
                    this.customers = [];
                    try {
                        const response = await axios.get('/api/index.php?model=customer&method=list_customer&category_id=' + this.selectedCategory.id);
                        if (response.data.status == 'success' && response.data.data.length > 0) {
                            this.customers = response.data.data;
                            for (const customer of this.customers) {
                                customer.guis_department = customer.guis_department.split(',');
                            }
                        } else {
                            //showMessage(response.data.message_code, true);
                        }
                    } catch (error) {
                        console.error('Error loading customers:', error);
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
                    $('#customerModal').modal('show');
                },
                async deleteCustomer(customer) {
                    if (!confirm('この担当者を削除してもよろしいですか？')) {
                        return;
                    }
                    try {
                        await axios.post('/api/index.php?model=customer&method=delete_customer&id=' + customer.id);
                        this.loadCustomers();
                    } catch (error) {
                        console.error('Error deleting customer:', error);
                        showMessage('担当者の削除に失敗しました。', true);
                    }
                },
                async saveCustomer() {
                    // Reset errors
                    this.customerErrors = { company_name: '', name: '', guis_department: '' };
                    let hasError = false;
                    if (!this.newCustomer.company_name) {
                        this.customerErrors.company_name = '会社名は必須です。';
                        hasError = true;
                    }
                    if (!this.newCustomer.name) {
                        this.customerErrors.name = '担当者名は必須です。';
                        hasError = true;
                    }
                    if (!this.newCustomer.guis_department || this.newCustomer.guis_department.length === 0) {
                        this.customerErrors.guis_department = '自社担当部署名は必須です。';
                        hasError = true;
                    }
                    if (hasError) return;
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
                            this.loadCustomers();
                            this.loadCategories();
                            for (const category of this.categories) {
                                if (category.id == this.newCustomer.category_id) {
                                    this.viewCategory(category);
                                }
                            }
                            this.lastEditCustomer = this.newCustomer;
                            this.resetCustomerData();
                            this.showNewCustomerModal = false;
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
                    this.newCustomer = { company_name: '', company_name_kana: '', name: '', name_kana: '', branch: '', position: '', department: '', title: '', tel: '', fax: '', phone: '', email: '', zip: '', address1: '', address2: '', memo: '', category_id: this.selectedCategory.id, status: 1, guis_department: [] };
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
            mounted() {
                this.loadDepartments();
                this.loadCategories();
                // Initialize select2
                this.$nextTick(() => {
                    const selectElement = $(this.$refs.guisDepartmentSelect);
                    selectElement.select2();
                    selectElement.on('change', (event) => {
                        const val = $(event.target).val();
                        this.newCustomer.guis_department = val ? val : [];
                    });
                });
                // Add event listener for modal hide
                const categoryModal = document.getElementById('categoryModal');
                categoryModal.addEventListener('hide.bs.modal', () => {
                    this.resetCategoryData();
                });
            }
        }).mount('#app');
    </script>