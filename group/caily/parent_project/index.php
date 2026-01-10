<?php
require_once('../application/loader.php');
$view->heading('建物一覧');
?>
<div id="app" class="container-fluid mt-4" v-cloak>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><span data-i18n="建物一覧">建物一覧</span></h5>
                        <?php if($_SESSION['isProjectManager']): ?>
                            <div>
                                <a href="../price_list/index.php" class="btn btn-outline-info btn-sm me-2">
                                    <i class="fa fa-list me-1"></i> <span data-i18n="価格表管理">価格表管理</span>
                                </a>
                                <a href="create.php" class="btn btn-primary btn-sm">
                                    <i class="fa fa-plus me-1"></i> <span data-i18n="建物登録">建物登録</span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Search and Filter -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <input type="text" class="form-control" v-model="searchKeyword" placeholder="検索..." @input="onSearch">
                                <button class="btn btn-outline-secondary" type="button" @click="clearSearch">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="favoritesOnly" v-model="favoritesOnly" @change="onFavoritesFilterChange">
                                <label class="form-check-label" for="favoritesOnly">
                                    <i class="fa fa-star text-warning me-1"></i>お気に入りのみ
                                </label>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-center" v-if="favoritesOnly">
                            <button class="btn btn-outline-danger btn-sm" @click="clearAllFavorites" :disabled="loading">
                                <i class="fa fa-trash me-1"></i>お気に入りをすべて削除
                            </button>
                        </div>
                    </div>

                    <!-- Parent Projects Table -->
                    <div class="table-responsive">
                        <!-- Loading indicator -->
                        <div v-if="loading" class="text-center py-4">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">読み込み中...</p>
                        </div>
                        
                        <table v-else class="table table-hover">
                            <thead>
                                <tr>
                                    <th class="text-center">お気に入り</th>
                                    <th @click="sortBy('project_number')" style="cursor: pointer;" class="user-select-none">
                                        番号
                                        <i class="fa fa-fw" :class="getSortIcon('project_number')"></i>
                                    </th>
                                    <th @click="sortBy('project_name')" style="cursor: pointer;" class="user-select-none">
                                        お施主様名
                                        <i class="fa fa-fw" :class="getSortIcon('project_name')"></i>
                                    </th>
                                    <th @click="sortBy('construction_number')" style="cursor: pointer;" class="user-select-none">
                                        工事番号
                                        <i class="fa fa-fw" :class="getSortIcon('construction_number')"></i>
                                    </th>
                                    <th @click="sortBy('company_name')" style="cursor: pointer;" class="user-select-none">
                                        会社名
                                        <i class="fa fa-fw" :class="getSortIcon('company_name')"></i>
                                    </th>
                                    <th @click="sortBy('request_date')" style="cursor: pointer;" class="user-select-none">
                                        依頼日
                                        <i class="fa fa-fw" :class="getSortIcon('request_date')"></i>
                                    </th>
                                    <th @click="sortBy('child_project_count')" style="cursor: pointer;" class="user-select-none">
                                        件数
                                        <i class="fa fa-fw" :class="getSortIcon('child_project_count')"></i>
                                    </th>
                                    <th @click="sortBy('created_by_name')" style="cursor: pointer;" class="user-select-none">
                                        作成者
                                        <i class="fa fa-fw" :class="getSortIcon('created_by_name')"></i>
                                    </th>
                                    <th @click="sortBy('created_at')" style="cursor: pointer;" class="user-select-none">
                                        作成日
                                        <i class="fa fa-fw" :class="getSortIcon('created_at')"></i>
                                    </th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="project in filteredParentProjects" :key="project.id">
                                    <td class="text-center">
                                        <i class="fa fa-star" 
                                           :class="project.is_favorite == 1 ? 'text-warning' : 'text-muted'"
                                           style="cursor: pointer; font-size: 1.2em;"
                                           @click="toggleFavorite(project)"
                                           :title="project.is_favorite == 1 ? 'お気に入りから削除' : 'お気に入りに追加'"></i>
                                    </td>
                                    <td>
                                        <span class="badge bg-label-info">{{ project.project_number || '-' }}</span>
                                    </td>
                                    <td>
                                        <a :href="'detail.php?id=' + project.id" class="text-decoration-none" 
                                           :title="'詳細を表示: ' + project.project_name">
                                            {{ project.project_name }}
                                        </a>
                                    </td>
                                    <td>{{ project.construction_number || '-' }}</td>
                                    <td>{{ project.company_name }}</td>
                                    <td>{{ formatDate(project.request_date) }}</td>
                                    <td>
                                        <span v-if="project.child_project_count > 0" class="badge bg-info" 
                                              :title="project.child_project_count + ' 個の案件があります'">
                                            {{ project.child_project_count }}
                                        </span>
                                        <span v-else class="text-muted">0</span>
                                    </td>
                                    <td>{{ project.created_by_name || '-' }}</td>
                                    <td>{{ formatDate(project.created_at) }}</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a :href="'detail.php?id=' + project.id" class="btn btn-outline-primary" 
                                               title="詳細を表示">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            
                                            <template v-if="isProjectManager">
                                                <button v-if="project.child_project_count == 0" class="btn btn-outline-danger" @click="deleteParentProject(project.id)" 
                                                         title="削除" 
                                                         :disabled="project.child_project_count > 0">
                                                     <i class="fa fa-trash"></i>
                                                 </button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredParentProjects.length === 0">
                                    <td colspan="12" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fa fa-inbox fa-2x mb-2"></i>
                                            <p>建物が見つかりません</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted">
                            全 {{ totalRecords }} 件中 {{ startRecord }}-{{ endRecord }} 件を表示
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
</div>

<?php
$view->footing();
?>

<style>
.table th {
    background-color: #f8f9fa;
    border-top: none;
    font-weight: 600;
    color: #495057;
}

.table th[style*="cursor: pointer"]:hover {
    background-color: #e9ecef;
}

.table td {
    vertical-align: middle;
}

.badge {
    font-size: 0.75rem;
    padding: 0.375rem 0.5rem;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.table-responsive {
    border-radius: 0.375rem;
    overflow: hidden;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

/* Responsive improvements */
@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .btn-group-sm .btn {
        padding: 0.2rem 0.4rem;
        font-size: 0.8rem;
    }
    
    .badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.4rem;
    }
}

/* Loading animation */
.spinner-border {
    color: #007bff;
}

/* Empty state styling */
.text-muted i {
    opacity: 0.5;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Global function for showing messages
function showMessage(message, isError = false) {
    if (isError) {
        Swal.fire({
            title: 'エラー',
            text: message,
            icon: 'error',
            confirmButtonText: 'OK'
        });
    } else {
        Swal.fire({
            title: '成功',
            text: message,
            icon: 'success',
            confirmButtonText: 'OK'
        });
    }
}
const IS_PROJECT_MANAGER = <?php echo isset($_SESSION['isProjectManager']) && $_SESSION['isProjectManager'] ? 'true' : 'false'; ?>;
</script>
<script src="assets/js/parent-project-index.js?v=<?=CACHE_VERSION?>"></script> 