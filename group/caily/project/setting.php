<?php
require_once('../application/loader.php');
$view->heading('カテゴリー管理');

?>
    <div id="app">
        <nav class="navbar navbar-expand-lg bg-dark mb-12">
            <div class="container-fluid">
            <span class="navbar-brand" href="javascript:void(0)"><i class="icon-base ti tabler-settings"></i></span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="/project/setting.php">カテゴリー一覧</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" aria-current="page" href="/project/teams.php">チーム一覧</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" aria-current="page" href="/project/custom_fields.php">カスタムフィールド</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" aria-current="page" href="/project/specification.php">スペック</a>
                    </li>
                </ul>
            </div>
            </div>
        </nav>

        <div class="container-fluid mt-4">
            <div class="row">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>カテゴリー一覧</h2>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
                            <i class="bi bi-plus"></i> 新規カテゴリー
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>カテゴリー名</th>
                                    <th class="d-none">説明</th>
                                    <th>プロジェクト数</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="category in categories" :key="category.id">
                                    <td>{{ category.name }}</td>
                                    <td class="d-none">{{ category.description }}</td>
                                    <td>{{ category.project_count }}</td>
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

        <!-- New/Edit Category Modal -->
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
                            <div class="mb-3 d-none">
                                <label class="form-label">説明</label>
                                <textarea class="form-control" v-model="newCategory.description" rows="3"></textarea>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        const { createApp } = Vue;
        
        createApp({
            data() {
                return {
                    categories: [],
                    showNewCategoryModal: false,
                    editingCategory: null,
                    newCategory: {
                        name: '',
                        description: ''
                    }
                }
            },
            methods: {
                openNewCategoryModal() {
                    this.showNewCategoryModal = true;
                },
                async loadCategories() {
                    try {
                        const response = await axios.get('/api/index.php?model=category&method=list');
                        this.categories = response.data;
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
                        await axios.post('/api/index.php?model=category&method=delete&id=' + category.id);
                        this.loadCategories();
                    } catch (error) {
                        console.error('Error deleting category:', error);
                        showMessage('カテゴリーの削除に失敗しました。', true);
                    }
                },
                async saveCategory() {
                    try {
                        if (this.editingCategory) {
                            await axios.post('/api/index.php?model=category&method=edit&id=' + this.editingCategory.id, this.newCategory,
                                {
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded'
                                    }
                                }
                            );
                        } else {
                            await axios.post('/api/index.php?model=category&method=add', this.newCategory, {
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                }
                            });
                        }
                        this.showNewCategoryModal = false;
                        this.editingCategory = null;
                        this.newCategory = { name: '', description: '' };
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
                    this.newCategory = { name: '', description: '' };
                }
            },
            mounted() {
                this.loadCategories();
                
                // Add event listener for modal hide
                const categoryModal = document.getElementById('categoryModal');
                categoryModal.addEventListener('hide.bs.modal', () => {
                    this.resetCategoryData();
                });
            }
        }).mount('#app');
    </script>


<?php
$view->footing();
?> 