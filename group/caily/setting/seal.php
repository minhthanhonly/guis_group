<?php
require_once('../application/loader.php');
$view->heading('印鑑管理');

?>
<link rel="stylesheet" href="../assets/css/seal-management.css">

    <div id="app" v-cloak>
        <div class="container-fluid mt-4">
            <div class="row">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>印鑑一覧</h2>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sealModal">
                            <i class="bi bi-plus"></i> 新規印鑑
                        </button>
                    </div>

                    <!-- Filter Section -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label">タイプ</label>
                                    <select class="form-select" v-model="filterType">
                                        <option value="">すべて</option>
                                        <option value="company">会社印</option>
                                        <option value="employee">従業員印</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">ステータス</label>
                                    <select class="form-select" v-model="filterStatus">
                                        <option value="">すべて</option>
                                        <option value="1">有効</option>
                                        <option value="0">無効</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">検索</label>
                                    <input type="text" class="form-control" v-model="searchText" placeholder="印鑑名、所有者名で検索">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button class="btn btn-outline-secondary w-100" @click="clearFilters">
                                        <i class="bi bi-arrow-clockwise"></i> リセット
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>印鑑画像</th>
                                    <th>印鑑名</th>
                                    <th>タイプ</th>
                                    <th>所有者</th>
                                    <th>説明</th>
                                    <th>ステータス</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="seal in filteredSeals" :key="seal.id">
                                    <td>
                                        <img v-if="seal.image_path" :src="seal.image_path" 
                                             class="seal-thumbnail" alt="印鑑画像"
                                             style="max-width: 60px; max-height: 60px; object-fit: contain;">
                                        <div v-else class="text-muted">画像なし</div>
                                    </td>
                                    <td>{{ seal.name }}</td>
                                    <td>
                                        <span :class="seal.type === 'company' ? 'badge bg-primary' : 'badge bg-success'">
                                            {{ seal.type === 'company' ? '会社印' : '従業員印' }}
                                        </span>
                                    </td>
                                    <td>{{ seal.type === 'employee' ? seal.display_owner_name : '-' }}</td>
                                    <td>{{ seal.description || '-' }}</td>
                                    <td>
                                        <span :class="isSealActive(seal.is_active) ? 'badge bg-success' : 'badge bg-secondary'">
                                            {{ isSealActive(seal.is_active) ? '有効' : '無効' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#sealModal" @click="editSeal(seal)">
                                                <i class="icon-base ti tabler-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-info" @click="viewSeal(seal)">
                                                <i class="icon-base ti tabler-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" @click="deleteSeal(seal)">
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

        <!-- New/Edit Seal Modal -->
        <div class="modal fade" id="sealModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editingSeal ? '印鑑編集' : '新規印鑑' }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form @submit.prevent="saveSeal" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                                                         <div class="mb-3">
                                         <label class="form-label">印鑑名 <span class="text-danger">*</span></label>
                                         <input type="text" class="form-control" 
                                                :class="{ 'is-invalid': formErrors.name }"
                                                v-model="newSeal.name" 
                                                @blur="validateField('name')"
                                                @input="validateField('name')"
                                                required>
                                         <div class="invalid-feedback" v-if="formErrors.name">
                                             {{ formErrors.name }}
                                         </div>
                                     </div>
                                     <div class="mb-3">
                                         <label class="form-label">タイプ <span class="text-danger">*</span></label>
                                         <select class="form-select" 
                                                 :class="{ 'is-invalid': formErrors.type }"
                                                 v-model="newSeal.type" 
                                                 @change="onTypeChange(); validateField('type')"
                                                 required>
                                             <option value="company">会社印</option>
                                             <option value="employee">従業員印</option>
                                         </select>
                                         <div class="invalid-feedback" v-if="formErrors.type">
                                             {{ formErrors.type }}
                                         </div>
                                     </div>
                                     <div class="mb-3" v-if="newSeal.type === 'employee'">
                                         <label class="form-label">従業員選択</label>
                                         <select class="form-select" 
                                                 :class="{ 'is-invalid': formErrors.owner_id }"
                                                 v-model="newSeal.owner_id" 
                                                 @change="onEmployeeChange(); validateField('owner_id')">
                                             <option value="">従業員を選択</option>
                                             <option v-for="employee in employees" :key="employee.userid" :value="employee.userid">
                                                 {{ employee.realname }}
                                             </option>
                                         </select>
                                         <div class="invalid-feedback" v-if="formErrors.owner_id">
                                             {{ formErrors.owner_id }}
                                         </div>
                                     </div>
                                     <div class="mb-3">
                                         <label class="form-label">説明</label>
                                         <textarea class="form-control" 
                                                   :class="{ 'is-invalid': formErrors.description }"
                                                   v-model="newSeal.description" 
                                                   @blur="validateField('description')"
                                                   @input="validateField('description')"
                                                   rows="3"></textarea>
                                         <div class="invalid-feedback" v-if="formErrors.description">
                                             {{ formErrors.description }}
                                         </div>
                                     </div>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" v-model="newSeal.is_active" id="isActive">
                                            <label class="form-check-label" for="isActive">
                                                有効
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                                                         <div class="mb-3">
                                         <label class="form-label">印鑑画像 <span class="text-danger">*</span></label>
                                         <input type="file" class="form-control" 
                                                :class="{ 'is-invalid': formErrors.file }"
                                                @change="onFileChange" 
                                                accept="image/*" 
                                                :required="!editingSeal || !newSeal.image_path">
                                         <div class="form-text">JPEG, PNG, GIF, BMP形式、5MB以下</div>
                                         <div class="invalid-feedback" v-if="formErrors.file">
                                             {{ formErrors.file }}
                                         </div>
                                     </div>
                                    <div class="mb-3" v-if="newSeal.image_path || (editingSeal && editingSeal.image_path)">
                                        <label class="form-label">現在の画像</label>
                                        <div class="text-center">
                                            <img :src="newSeal.image_path || editingSeal.image_path" 
                                                 class="img-fluid border rounded" 
                                                 style="max-height: 200px; max-width: 100%;"
                                                 alt="印鑑画像">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="button" class="btn btn-primary" @click="saveSeal">保存</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- View Seal Modal -->
        <div class="modal fade" id="viewSealModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">印鑑詳細</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row" v-if="viewingSeal">
                            <div class="col-md-6">
                                <h6>基本情報</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="30%">印鑑名:</th>
                                        <td>{{ viewingSeal.name }}</td>
                                    </tr>
                                    <tr>
                                        <th>タイプ:</th>
                                        <td>
                                            <span :class="viewingSeal.type === 'company' ? 'badge bg-primary' : 'badge bg-success'">
                                                {{ viewingSeal.type === 'company' ? '会社印' : '従業員印' }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>所有者:</th>
                                        <td>{{ viewingSeal.type === 'employee' ? viewingSeal.display_owner_name : '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>説明:</th>
                                        <td>{{ viewingSeal.description || '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>ステータス:</th>
                                        <td>
                                            <span :class="isSealActive(viewingSeal.is_active) ? 'badge bg-success' : 'badge bg-secondary'">
                                                {{ isSealActive(viewingSeal.is_active) ? '有効' : '無効' }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>作成日:</th>
                                        <td>{{ formatDate(viewingSeal.created_at) }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>印鑑画像</h6>
                                <div class="text-center">
                                    <img v-if="viewingSeal.image_path" :src="viewingSeal.image_path" 
                                         class="img-fluid border rounded" 
                                         style="max-height: 300px; max-width: 100%;"
                                         alt="印鑑画像">
                                    <div v-else class="text-muted">画像なし</div>
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
                    seals: [],
                    employees: [],
                    showNewSealModal: false,
                    editingSeal: null,
                    viewingSeal: null,
                    filterType: '',
                    filterStatus: '',
                    searchText: '',
                                         newSeal: {
                         name: '',
                         type: 'company',
                         owner_id: '',
                         description: '',
                         image_path: '',
                         file_name: '',
                         file_size: '',
                         mime_type: '',
                         is_active: true
                     },
                     selectedFile: null,
                     formErrors: {}
                }
            },
            computed: {
                filteredSeals() {
                    let filtered = this.seals;
                    
                    if (this.filterType) {
                        filtered = filtered.filter(seal => seal.type === this.filterType);
                    }
                    
                    if (this.filterStatus !== '') {
                        filtered = filtered.filter(seal => this.isSealActive(seal.is_active) == this.filterStatus);
                    }
                    
                    if (this.searchText) {
                        const search = this.searchText.toLowerCase();
                        filtered = filtered.filter(seal => 
                            seal.name.toLowerCase().includes(search) ||
                            (seal.display_owner_name && seal.display_owner_name.toLowerCase().includes(search))
                        );
                    }
                    
                    return filtered;
                }
            },
            methods: {
                async loadSeals() {
                    try {
                        const response = await axios.get('/api/index.php?model=seal&method=list');
                        this.seals = response.data;
                    } catch (error) {
                        console.error('Error loading seals:', error);
                        showMessage('印鑑の読み込みに失敗しました。', true);
                    }
                },
                async loadEmployees() {
                    try {
                        const response = await axios.get('/api/index.php?model=seal&method=get_employees');
                        this.employees = response.data;
                    } catch (error) {
                        console.error('Error loading employees:', error);
                    }
                },
                                 editSeal(seal) {
                    this.editingSeal = seal;
                    this.newSeal = { 
                        name: seal.name,
                        type: seal.type,
                        owner_id: seal.owner_id || '',
                        description: seal.description || '',
                        image_path: seal.image_path || '',
                        file_name: seal.file_name || '',
                        file_size: seal.file_size || '',
                        mime_type: seal.mime_type || '',
                        is_active: this.isSealActive(seal.is_active)
                    };
                    this.selectedFile = null;
                    this.formErrors = {};
                },
                viewSeal(seal) {
                    this.viewingSeal = seal;
                    $('#viewSealModal').modal('show');
                },
                async deleteSeal(seal) {
                    if (!confirm('この印鑑を削除してもよろしいですか？')) {
                        return;
                    }

                    try {
                        await axios.post('/api/index.php?model=seal&method=delete&id=' + seal.id);
                        this.loadSeals();
                        showMessage('印鑑を削除しました。');
                    } catch (error) {
                        console.error('Error deleting seal:', error);
                        showMessage('印鑑の削除に失敗しました。', true);
                    }
                },
                onTypeChange() {
                    if (this.newSeal.type === 'employee') {
                        this.newSeal.owner_id = '';
                    } else {
                        this.newSeal.owner_id = '';
                    }
                },
                onEmployeeChange() {
                    if (this.newSeal.owner_id) {
                        const employee = this.employees.find(emp => emp.userid == this.newSeal.owner_id);
                        if (employee) {
                            this.newSeal.owner_name = employee.realname;
                        }
                    }
                },
                                 onFileChange(event) {
                     this.selectedFile = event.target.files[0];
                     this.validateFile();
                 },
                 validateField(fieldName) {
                     this.formErrors[fieldName] = '';
                     
                     switch (fieldName) {
                         case 'name':
                             if (!this.newSeal.name || this.newSeal.name.trim() === '') {
                                 this.formErrors[fieldName] = '印鑑名は必須です。';
                             } else if (this.newSeal.name.length > 255) {
                                 this.formErrors[fieldName] = '印鑑名は255文字以内で入力してください。';
                             }
                             break;
                         case 'type':
                             if (!this.newSeal.type || !['company', 'employee'].includes(this.newSeal.type)) {
                                 this.formErrors[fieldName] = 'タイプを選択してください。';
                             }
                             break;
                         case 'owner_id':
                             if (this.newSeal.type === 'employee' && !this.newSeal.owner_id) {
                                 this.formErrors[fieldName] = '従業員を選択してください。';
                             }
                             break;
                         case 'description':
                             if (this.newSeal.description && this.newSeal.description.length > 1000) {
                                 this.formErrors[fieldName] = '説明は1000文字以内で入力してください。';
                             }
                             break;
                     }
                 },
                 validateFile() {
                     this.formErrors.file = '';
                     
                     if (!this.editingSeal && !this.selectedFile) {
                         this.formErrors.file = '印鑑画像は必須です。';
                         return;
                     }
                     
                     if (this.selectedFile) {
                         const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp'];
                         if (!allowedTypes.includes(this.selectedFile.type)) {
                             this.formErrors.file = '対応していないファイル形式です。JPEG, PNG, GIF, BMPのみ対応しています。';
                         }
                         
                         const maxSize = 5 * 1024 * 1024; // 5MB
                         if (this.selectedFile.size > maxSize) {
                             this.formErrors.file = 'ファイルサイズが大きすぎます。5MB以下にしてください。';
                         }
                     }
                 },
                validateSealForm() {
                    const errors = [];
                    
                    // Validate name
                    if (!this.newSeal.name || this.newSeal.name.trim() === '') {
                        errors.push('印鑑名は必須です。');
                    } else if (this.newSeal.name.length > 255) {
                        errors.push('印鑑名は255文字以内で入力してください。');
                    }
                    
                    // Validate type
                    if (!this.newSeal.type || !['company', 'employee'].includes(this.newSeal.type)) {
                        errors.push('タイプを選択してください。');
                    }
                    
                    // Validate owner information based on type
                    if (this.newSeal.type === 'employee') {
                        if (!this.newSeal.owner_id) {
                            errors.push('従業員を選択してください。');
                        }
                    }
                    
                    // Validate description
                    if (this.newSeal.description && this.newSeal.description.length > 1000) {
                        errors.push('説明は1000文字以内で入力してください。');
                    }
                    
                    // Validate file for new seals
                    if (!this.editingSeal && !this.selectedFile) {
                        errors.push('印鑑画像は必須です。');
                    }
                    
                    // Validate file type and size if file is selected
                    if (this.selectedFile) {
                        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp'];
                        if (!allowedTypes.includes(this.selectedFile.type)) {
                            errors.push('対応していないファイル形式です。JPEG, PNG, GIF, BMPのみ対応しています。');
                        }
                        
                        const maxSize = 5 * 1024 * 1024; // 5MB
                        if (this.selectedFile.size > maxSize) {
                            errors.push('ファイルサイズが大きすぎます。5MB以下にしてください。');
                        }
                    }
                    
                    return errors;
                },
                async saveSeal() {
                    try {
                        // Validate form
                        const errors = this.validateSealForm();
                        if (errors.length > 0) {
                            showMessage('入力エラー:\n' + errors.join('\n'), true);
                            return;
                        }
                        
                        const formData = new FormData();
                        
                        // Add seal data
                        formData.append('name', this.newSeal.name.trim());
                        formData.append('type', this.newSeal.type);
                        formData.append('owner_id', this.newSeal.owner_id);
                        formData.append('description', this.newSeal.description.trim());
                        formData.append('is_active', this.newSeal.is_active ? '1' : '0');
                        
                        // Add file if selected
                        if (this.selectedFile) {
                            formData.append('seal_image', this.selectedFile);
                        }
                        
                        if (this.editingSeal) {
                            await axios.post('/api/index.php?model=seal&method=edit&id=' + this.editingSeal.id, formData, {
                                headers: {
                                    'Content-Type': 'multipart/form-data'
                                }
                            });
                        } else {
                            await axios.post('/api/index.php?model=seal&method=add', formData, {
                                headers: {
                                    'Content-Type': 'multipart/form-data'
                                }
                            });
                        }
                        
                        $('#sealModal').modal('hide');
                        this.resetSealData();
                        showMessage('印鑑を保存しました。');
                        this.loadSeals();
                    } catch (error) {
                        console.error('Error saving seal:', error);
                        showMessage('印鑑の保存に失敗しました。', true);
                    }
                },
                                 resetSealData() {
                     this.editingSeal = null;
                     this.newSeal = {
                         name: '',
                         type: 'company',
                         owner_id: '',
                         description: '',
                         image_path: '',
                         file_name: '',
                         file_size: '',
                         mime_type: '',
                         is_active: true
                     };
                     this.selectedFile = null;
                     this.formErrors = {};
                 },
                clearFilters() {
                    this.filterType = '';
                    this.filterStatus = '';
                    this.searchText = '';
                },
                formatDate(dateString) {
                    if (!dateString) return '-';
                    const date = new Date(dateString);
                    return date.toLocaleDateString('ja-JP');
                },
                isSealActive(status) {
                    return status === true || status === '1' || status === 1;
                }
            },
            mounted() {
                this.loadSeals();
                this.loadEmployees();
                
                // Add event listener for modal hide
                const sealModal = document.getElementById('sealModal');
                sealModal.addEventListener('hide.bs.modal', () => {
                    this.resetSealData();
                });
            }
        }).mount('#app');
    </script>
