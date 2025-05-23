<?php
require_once('../application/loader.php');
$view->heading('部署設定');

?>
    <div id="app" v-cloak>
        <!-- <nav class="navbar navbar-expand-lg bg-dark mb-12">
            <div class="container-fluid">
            <span class="navbar-brand" href="javascript:void(0)"><i class="icon-base ti tabler-settings"></i></span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="/setting/department.php">部署一覧</a>
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
        </nav> -->

        <div class="container-fluid mt-4">
            <div class="row">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>部署一覧</h2>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#departmentModal">
                            <i class="bi bi-plus"></i> 新規部署
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>部署名</th>
                                    <th>従業員数</th>
                                    <th>案件数</th>
                                    <th>案件一覧</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="department in departments" :key="department.id">
                                    <td>{{ department.name }}</td>
                                    <td>{{ department.num_employees }}</td>
                                    <td>{{ department.project_count }}</td>
                                    <td>{{ department.can_project == 1 ? '有効' : '無効' }}</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#departmentModal" @click="editDepartment(department)">
                                                <i class="icon-base ti tabler-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" @click="deleteDepartment(department)">
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

        <!-- New/Edit Department Modal -->
        <div class="modal fade" id="departmentModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editingDepartment ? '部署編集' : '新規部署' }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form @submit.prevent="saveDepartment">
                            <div class="mb-3">
                                <label class="form-label">部署名</label>
                                <input type="text" class="form-control" v-model="newDepartment.name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">案件一覧</label>
                                <select class="form-control" v-model="newDepartment.can_project">
                                    <option value="1">有効</option>
                                    <option value="0">無効</option>
                                </select>
                            </div>
                            <div class="mb-3 d-none">
                                <label class="form-label">説明</label>
                                <textarea class="form-control" v-model="newDepartment.description" rows="3"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="button" class="btn btn-primary" @click="saveDepartment">保存</button>
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
                    departments: [],
                    showNewDepartmentModal: false,
                    editingDepartment: null,
                    newDepartment: {
                        name: '',
                        can_project: 1,
                        description: ''
                    }
                }
            },
            methods: {
                openNewDepartmentModal() {
                    this.showNewDepartmentModal = true;
                },
                async loadDepartments() {
                    try {
                        const response = await axios.get('/api/index.php?model=department&method=list');
                        this.departments = response.data;
                    } catch (error) {
                        console.error('Error loading departments:', error);
                        showMessage('部署の読み込みに失敗しました。', true);
                    }
                },
                editDepartment(department) {
                    this.editingDepartment = department;
                    this.newDepartment = { ...department };
                    this.showNewDepartmentModal = true;
                },
                async deleteDepartment(department) {
                    if (!confirm('この部署を削除してもよろしいですか？')) {
                        return;
                    }

                    try {
                        await axios.post('/api/index.php?model=department&method=delete&id=' + department.id);
                        this.loadDepartments();
                    } catch (error) {
                        console.error('Error deleting department:', error);
                        showMessage('部署の削除に失敗しました。', true);
                    }
                },
                async saveDepartment() {
                    try {
                        if (this.editingDepartment) {
                            await axios.post('/api/index.php?model=department&method=edit&id=' + this.editingDepartment.id, this.newDepartment,
                                {
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded'
                                    }
                                }
                            );
                        } else {
                            await axios.post('/api/index.php?model=department&method=add', this.newDepartment, {
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                }
                            });
                        }
                        this.showNewDepartmentModal = false;
                        this.resetDepartmentData();
                        $('#departmentModal').modal('hide');
                        showMessage('部署を保存しました。');
                        this.loadDepartments();
                    } catch (error) {
                        console.error('Error saving department:', error);
                        showMessage('部署の保存に失敗しました。', true);
                    }
                },
                resetDepartmentData() {
                    this.editingDepartment = null;
                    this.newDepartment = { name: '', description: '', can_project: 1 };
                }
            },
            mounted() {
                this.loadDepartments();
                
                // Add event listener for modal hide
                const departmentModal = document.getElementById('departmentModal');
                departmentModal.addEventListener('hide.bs.modal', () => {
                    this.resetDepartmentData();
                });
            }
        }).mount('#app');
    </script>