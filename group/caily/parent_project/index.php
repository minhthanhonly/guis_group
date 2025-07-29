<?php
require_once('../application/loader.php');
$view->heading('親プロジェクト一覧');
?>
<div id="app" class="container-fluid mt-4" v-cloak>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><span data-i18n="親プロジェクト一覧">親プロジェクト一覧</span></h5>
                        <div>
                            <a href="create.php" class="btn btn-primary btn-sm">
                                <i class="fa fa-plus me-1"></i> <span data-i18n="新規作成">新規作成</span>
                            </a>
                        </div>
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
                        <div class="col-md-3">
                            <select class="form-select" v-model="statusFilter" @change="onStatusFilterChange">
                                <option value="all">すべてのステータス</option>
                                <option value="draft">下書き</option>
                                <option value="under_contract">契約中</option>
                                <option value="in_progress">進行中</option>
                                <option value="completed">完了</option>
                                <option value="cancelled">キャンセル</option>
                            </select>
                        </div>
                    </div>

                    <!-- Parent Projects Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>会社名</th>
                                    <th>案件名</th>
                                    <th>工事番号</th>
                                    <th>依頼日</th>
                                    <th>希望納期</th>
                                    <th>ステータス</th>
                                    <th>作成日</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="project in filteredParentProjects" :key="project.id">
                                    <td>{{ project.company_name }}</td>
                                    <td>
                                        <a href="detail.php?id={{ project.id }}" class="text-decoration-none">
                                            {{ project.project_name }}
                                        </a>
                                    </td>
                                    <td>{{ project.construction_number || '-' }}</td>
                                    <td>{{ formatDate(project.request_date) }}</td>
                                    <td>{{ formatDate(project.desired_delivery_date) }}</td>
                                    <td>
                                        <span class="badge" :class="getStatusBadgeClass(project.status)">
                                            {{ getStatusLabel(project.status) }}
                                        </span>
                                    </td>
                                    <td>{{ formatDate(project.created_at) }}</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a :href="'detail.php?id=' + project.id" class="btn btn-outline-primary" title="詳細">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            <a :href="'edit.php?id=' + project.id" class="btn btn-outline-secondary" title="編集">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            <button class="btn btn-outline-danger" @click="deleteParentProject(project.id)" title="削除">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredParentProjects.length === 0">
                                    <td colspan="8" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fa fa-inbox fa-2x mb-2"></i>
                                            <p>親プロジェクトが見つかりません</p>
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
}

.badge {
    font-size: 0.75rem;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="assets/js/parent-project-index.js?v=<?=CACHE_VERSION?>"></script> 