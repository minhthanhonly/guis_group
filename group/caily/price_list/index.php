<?php
require_once('../application/loader.php');
$view->heading('価格表管理');
?>

<div id="app" class="container-fluid mt-4" v-cloak>
    <!-- Department Navigation Bar -->
    <nav class="navbar navbar-expand-lg bg-dark mb-3">
        <div class="container-fluid">
            <span class="navbar-brand text-white" href="javascript:void(0)">部署選択</span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-start" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item" v-for="department in departments" :key="department.id" :class="{ 'active bg-primary text-white rounded-3': selectedDepartment && selectedDepartment.id === department.id }">
                        <a href="#" class="nav-link" @click="viewProductsByDepartment(department)">{{ department.name }}</a>
                    </li>
                </ul>
                <div class="d-flex gap-2">
                    <a href="../parent_project/index.php" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-arrow-left me-1"></i> <span data-i18n="戻る">戻る</span>
                    </a>
                    <button @click="startAddRow" class="btn btn-primary btn-sm" v-if="!showAddRow">
                        <i class="fa fa-plus me-1"></i> <span data-i18n="新規追加">新規追加</span>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <span data-i18n="価格表管理">価格表管理</span>
                            <span v-if="selectedDepartment" class="text-muted ms-2">- {{ selectedDepartment.name }}</span>
                        </h5>

                    </div>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="searchInput" class="form-label">検索</label>
                            <input type="text" v-model="searchTerm" @input="filterProducts" class="form-control" id="searchInput" placeholder="コードまたは商品名で検索">
                        </div>
                        <div class="col-md-6">
                            <label for="sortSelect" class="form-label">並び替え</label>
                            <select v-model="sortBy" @change="filterProducts" class="form-select" id="sortSelect">
                                <option value="code">コード</option>
                                <option value="name">商品名</option>
                                <option value="price">価格</option>
                            </select>
                        </div>
                    </div>

                    <!-- Products Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>コード</th>
                                    <th>商品名</th>
                                    <th>単位</th>
                                    <th>単価</th>
                                    <th>作成日時</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- New Product Row (Inline Add) -->
                                <tr v-if="showAddRow" class="table-warning">
                                    <td>
                                        <input type="text" v-model="editingProduct.code" class="form-control form-control-sm" 
                                               placeholder="コード" :class="{ 'is-invalid': validationErrors.code }">
                                        <div class="invalid-feedback" v-if="validationErrors.code">{{ validationErrors.code }}</div>
                                    </td>
                                    <td>
                                        <input type="text" v-model="editingProduct.name" class="form-control form-control-sm" 
                                               placeholder="商品名" :class="{ 'is-invalid': validationErrors.name }">
                                        <div class="invalid-feedback" v-if="validationErrors.name">{{ validationErrors.name }}</div>
                                    </td>
                                    <td>
                                        <input type="text" v-model="editingProduct.unit" class="form-control form-control-sm" 
                                               placeholder="単位" :class="{ 'is-invalid': validationErrors.unit }">
                                        <div class="invalid-feedback" v-if="validationErrors.unit">{{ validationErrors.unit }}</div>
                                    </td>
                                    <td>
                                        <input type="number" v-model="editingProduct.price" class="form-control form-control-sm" 
                                               step="0.01" min="0" placeholder="単価" :class="{ 'is-invalid': validationErrors.price }">
                                        <div class="invalid-feedback" v-if="validationErrors.price">{{ validationErrors.price }}</div>
                                    </td>
                                    <td>-</td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button @click="saveProduct" class="btn btn-success btn-sm" :disabled="saving" title="保存">
                                                <span v-if="saving" class="spinner-border spinner-border-sm"></span>
                                                <i v-else class="fa fa-check"></i>
                                            </button>
                                            <button @click="cancelEdit" class="btn btn-secondary btn-sm" title="キャンセル">
                                                <i class="fa fa-times"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                
                                <!-- Existing Products -->
                                <tr v-for="product in paginatedProducts" :key="product.id" 
                                    :class="{ 'table-info': editingProduct.id === product.id }">
                                    <!-- Code -->
                                    <td v-if="editingProduct.id !== product.id">
                                        {{ product.code || '-' }}
                                    </td>
                                    <td v-else>
                                        <input type="text" v-model="editingProduct.code" class="form-control form-control-sm" 
                                               :class="{ 'is-invalid': validationErrors.code }">
                                        <div class="invalid-feedback" v-if="validationErrors.code">{{ validationErrors.code }}</div>
                                    </td>
                                    
                                                                         <!-- Name -->
                                     <td v-if="editingProduct.id !== product.id">
                                         {{ product.name || '-' }}
                                     </td>
                                     <td v-else>
                                         <input type="text" v-model="editingProduct.name" class="form-control form-control-sm" 
                                                :class="{ 'is-invalid': validationErrors.name }">
                                         <div class="invalid-feedback" v-if="validationErrors.name">{{ validationErrors.name }}</div>
                                     </td>
                                    
                                    <!-- Unit -->
                                    <td v-if="editingProduct.id !== product.id">
                                        {{ product.unit || '-' }}
                                    </td>
                                    <td v-else>
                                        <input type="text" v-model="editingProduct.unit" class="form-control form-control-sm" 
                                               :class="{ 'is-invalid': validationErrors.unit }">
                                        <div class="invalid-feedback" v-if="validationErrors.unit">{{ validationErrors.unit }}</div>
                                    </td>
                                    
                                    <!-- Price -->
                                    <td v-if="editingProduct.id !== product.id">
                                        {{ formatPrice(product.price || 0) }}
                                    </td>
                                    <td v-else>
                                        <input type="number" v-model="editingProduct.price" class="form-control form-control-sm" 
                                               step="0.01" min="0" :class="{ 'is-invalid': validationErrors.price }">
                                        <div class="invalid-feedback" v-if="validationErrors.price">{{ validationErrors.price }}</div>
                                    </td>
                                    
                                                                         <!-- Created At -->
                                     <td>{{ formatDateTime(product.created_at || '') }}</td>
                                    
                                    <!-- Actions -->
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button v-if="editingProduct.id !== product.id" @click="showDetailModal(product)" 
                                                    class="btn btn-outline-info btn-sm" title="詳細">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                            <button v-if="editingProduct.id !== product.id" @click="startEdit(product)" 
                                                    class="btn btn-outline-primary btn-sm" title="編集">
                                                <i class="fa fa-edit"></i>
                                            </button>
                                            <button v-if="editingProduct.id !== product.id" @click="duplicateProduct(product)" 
                                                    class="btn btn-outline-secondary btn-sm" title="複製">
                                                <i class="fa fa-copy"></i>
                                            </button>
                                            <button v-if="editingProduct.id !== product.id" @click="deleteProduct(product)" 
                                                    class="btn btn-outline-danger btn-sm" title="削除">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                            
                                            <!-- Edit Mode Actions -->
                                            <button v-if="editingProduct.id === product.id" @click="saveProduct" 
                                                    class="btn btn-success btn-sm" :disabled="saving" title="保存">
                                                <span v-if="saving" class="spinner-border spinner-border-sm"></span>
                                                <i v-else class="fa fa-check"></i>
                                            </button>
                                            <button v-if="editingProduct.id === product.id" @click="cancelEdit" 
                                                    class="btn btn-secondary btn-sm" title="キャンセル">
                                                <i class="fa fa-times"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                
                                <tr v-if="paginatedProducts.length === 0 && !showAddRow">
                                    <td colspan="6" class="text-center text-muted">
                                        商品が見つかりません
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted">
                            表示中: {{ (currentPage - 1) * itemsPerPage + 1 }}-{{ Math.min(currentPage * itemsPerPage, filteredProducts.length) }} / {{ filteredProducts.length }}件
                        </div>
                        <nav v-if="totalPages > 1">
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item" :class="{ disabled: currentPage === 1 }">
                                    <a class="page-link" href="#" @click.prevent="changePage(currentPage - 1)">前へ</a>
                                </li>
                                <li v-for="page in visiblePages" :key="page" class="page-item" :class="{ active: page === currentPage }">
                                    <a class="page-link" href="#" @click.prevent="changePage(page)">{{ page }}</a>
                                </li>
                                <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                                    <a class="page-link" href="#" @click.prevent="changePage(currentPage + 1)">次へ</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailModalLabel">商品詳細</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" v-if="selectedProduct">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row">
                                <dt class="col-sm-4">コード</dt>
                                <dd class="col-sm-8">{{ selectedProduct.code }}</dd>
                                
                                <dt class="col-sm-4">商品名</dt>
                                <dd class="col-sm-8">{{ selectedProduct.name }}</dd>
                                
                                <dt class="col-sm-4">部署</dt>
                                <dd class="col-sm-8">{{ selectedProduct.department_name }}</dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row">
                                <dt class="col-sm-4">単位</dt>
                                <dd class="col-sm-8">{{ selectedProduct.unit }}</dd>
                                
                                <dt class="col-sm-4">単価</dt>
                                <dd class="col-sm-8">{{ formatPrice(selectedProduct.price) }}</dd>
                                
                                <dt class="col-sm-4">作成日時</dt>
                                <dd class="col-sm-8">{{ formatDateTime(selectedProduct.created_at) }}</dd>
                            </dl>
                        </div>
                    </div>
                    <div class="row" v-if="selectedProduct.notes">
                        <div class="col-12">
                            <dl class="row">
                                <dt class="col-sm-2">備考</dt>
                                <dd class="col-sm-10">{{ selectedProduct.notes }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" @click="editFromDetail">編集</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                </div>
            </div>
        </div>
    </div>

</div>
<?php
$view->footing();
?>
<style>
/* Department navbar styles */
.navbar-nav .nav-link {
    color: rgba(255, 255, 255, 0.8) !important;
    transition: all 0.3s ease;
}

.navbar-nav .nav-link:hover {
    color: rgba(255, 255, 255, 1) !important;
    background-color: rgba(255, 255, 255, 0.1);
    border-radius: 0.375rem;
}

.navbar-nav .nav-item.active .nav-link {
    color: white !important;
}

.navbar-nav .nav-item.active {
    background-color: #0d6efd !important;
    border-radius: 0.375rem;
}

/* Inline editing styles */
.table-warning {
    background-color: #fff3cd !important;
}

.table-info {
    background-color: #d1ecf1 !important;
}

.form-control-sm, .form-select-sm {
    font-size: 0.875rem;
    padding: 0.25rem 0.5rem;
}

.invalid-feedback {
    font-size: 0.75rem;
    margin-top: 0.125rem;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

/* Table cell padding for inline editing */
.table td {
    padding: 0.5rem;
    vertical-align: middle;
}

/* Input focus styles */
.form-control:focus, .form-select:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .form-control-sm, .form-select-sm {
        font-size: 0.8rem;
        padding: 0.2rem 0.4rem;
    }
    
    .btn-sm {
        padding: 0.2rem 0.4rem;
        font-size: 0.8rem;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="../assets/js/axios.min.js"></script>
<script src="assets/js/price-list.js"></script> 