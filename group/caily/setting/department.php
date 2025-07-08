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
                                            <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#membersModal" @click="viewMembers(department)">
                                                <i class="icon-base ti tabler-users"></i>
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
            <div class="modal-dialog modal-xl">
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
                                <select class="form-select" v-model="newDepartment.can_project">
                                    <option value="1">有効</option>
                                    <option value="0">無効</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">メンバー</label>
                                <div class="mb-2">
                                    <select class="form-select" v-model="selectedUser" @change="addMember" style="max-width: 300px;">
                                        <option value="">メンバーを選択</option>
                                        <option v-for="user in availableUsers" :key="user.userid" :value="user">
                                            {{ user.realname }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>名前</th>
                                                <th>プロジェクト権限</th>
                                                <th>タスク権限</th>
                                                <th>操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(member, index) in selectedMembers" :key="index">
                                                <td>{{ member.name }}</td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <label class="form-check-label"><input class="form-check-input" type="checkbox" v-model="member.project_manager">
                                                        マネージャー</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <label class="form-check-label"><input class="form-check-input" type="checkbox" v-model="member.project_director">
                                                        業務担当</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <label class="form-check-label"><input class="form-check-input" type="checkbox" v-model="member.project_add">
                                                        追加</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <label class="form-check-label"><input class="form-check-input" type="checkbox" v-model="member.project_edit">
                                                        編集</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <label class="form-check-label"><input class="form-check-input" type="checkbox" v-model="member.project_delete">
                                                        削除</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <label class="form-check-label"><input class="form-check-input" type="checkbox" v-model="member.project_comment">
                                                        コメント</label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <label class="form-check-label"><input class="form-check-input" type="checkbox" v-model="member.task_view">
                                                        閲覧</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <label class="form-check-label"><input class="form-check-input" type="checkbox" v-model="member.task_add">
                                                        追加</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <label class="form-check-label"><input class="form-check-input" type="checkbox" v-model="member.task_edit">
                                                        編集</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <label class="form-check-label"><input class="form-check-input" type="checkbox" v-model="member.task_delete">
                                                        削除</label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" @click="removeMember(index)">
                                                        <i class="icon-base ti tabler-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
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

        <!-- View Members Modal -->
        <div class="modal fade" id="membersModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">部署メンバー</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>名前</th>
                                        <th>プロジェクト権限</th>
                                        <th>タスク権限</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="member in selectedDepartmentMembers" :key="member.user_id">
                                        <td>{{ member.user_name }}</td>
                                        <td>
                                            <span v-if="member.project_manager == 1" class="badge bg-label-primary me-1">マネージャー</span>
                                            <span v-if="member.project_director == 1" class="badge bg-label-info me-1">業務担当</span>
                                            <span v-if="member.project_add == 1" class="badge bg-label-success me-1">追加</span>
                                            <span v-if="member.project_edit == 1" class="badge bg-label-warning me-1">編集</span>
                                            <span v-if="member.project_delete == 1" class="badge bg-label-danger me-1">削除</span>
                                            <span v-if="member.project_comment == 1" class="badge bg-label-secondary me-1">コメント</span>
                                        </td>
                                        <td>
                                            <span v-if="member.task_view == 1" class="badge bg-label-secondary me-1">閲覧</span>
                                            <span v-if="member.task_add == 1" class="badge bg-label-success me-1">追加</span>
                                            <span v-if="member.task_edit == 1" class="badge bg-label-warning me-1">編集</span>
                                            <span v-if="member.task_delete == 1" class="badge bg-label-danger me-1">削除</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
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
                    departments: [],
                    availableUsers: [],
                    selectedDepartmentMembers: [],
                    editingDepartment: null,
                    selectedUser: '',
                    selectedMembers: [],
                    newDepartment: {
                        name: '',
                        can_project: 1,
                        description: '',
                        members: [],
                        project_manager: {},
                        project_director: {},
                        project_add: {},
                        project_edit: {},
                        project_delete: {},
                        project_comment: {},
                        task_view: {},
                        task_add: {},
                        task_edit: {},
                        task_delete: {}
                    }
                }
            },
            methods: {
                async loadDepartments() {
                    try {
                        const response = await axios.get('/api/index.php?model=department&method=list');
                        this.departments = response.data;
                    } catch (error) {
                        console.error('Error loading departments:', error);
                        showMessage('部署の読み込みに失敗しました。', true);
                    }
                },
                async loadAvailableUsers() {
                    try {
                        const response = await axios.get('/api/index.php?model=user&method=getList');
                        this.availableUsers = response.data.list;
                    } catch (error) {
                        console.error('Error loading users:', error);
                        showMessage('ユーザーの読み込みに失敗しました。', true);
                    }
                },
                async editDepartment(department) {
                    try {
                        const response = await axios.get('/api/index.php?model=department&method=get&id=' + department.id);
                        const departmentData = response.data;
                        
                        this.editingDepartment = department;
                        this.newDepartment = {
                            name: departmentData.name,
                            can_project: departmentData.can_project,
                            description: departmentData.description,
                            members: [],
                            project_manager: {},
                            project_director: {},
                            project_add: {},
                            project_edit: {},
                            project_delete: {},
                            project_comment: {},
                            task_view: {},
                            task_add: {},
                            task_edit: {},
                            task_delete: {}
                        };

                        if (departmentData.members && departmentData.members.length > 0) {
                            this.selectedMembers = departmentData.members.map(member => ({
                                id: member.userid,
                                name: member.user_name,
                                project_manager: member.project_manager === '1',
                                project_director: member.project_director === '1',
                                project_add: member.project_add === '1',
                                project_edit: member.project_edit === '1',
                                project_delete: member.project_delete === '1',
                                project_comment: member.project_comment === '1',
                                task_view: member.task_view === '1',
                                task_add: member.task_add === '1',
                                task_edit: member.task_edit === '1',
                                task_delete: member.task_delete === '1'
                            }));
                        }
                    } catch (error) {
                        console.error('Error loading department details:', error);
                        showMessage('部署情報の読み込みに失敗しました。', true);
                    }
                },
                async viewMembers(department) {
                    try {
                        const response = await axios.get('/api/index.php?model=department&method=get&id=' + department.id);
                        this.selectedDepartmentMembers = response.data.members || [];
                    } catch (error) {
                        console.error('Error loading department members:', error);
                        showMessage('部署メンバーの読み込みに失敗しました。', true);
                    }
                },
                async deleteDepartment(department) {
                    if (!confirm('この部署を削除してもよろしいですか？')) {
                        return;
                    }

                    try {
                        await axios.post('/api/index.php?model=department&method=delete&id=' + department.id);
                        this.loadDepartments();
                        showMessage('部署を削除しました。');
                    } catch (error) {
                        console.error('Error deleting department:', error);
                        showMessage('部署の削除に失敗しました。', true);
                    }
                },
                addMember() {
                    if (!this.selectedUser) return;
                    console.log(this.selectedUser);
                    const exists = this.selectedMembers.some(m => m.id === this.selectedUser.userid);
                    if (exists) {
                        showMessage('このメンバーは既に追加されています。', true);
                        return;
                    }

                    this.selectedMembers.push({
                        id: this.selectedUser.userid,
                        name: this.selectedUser.realname,
                        project_manager: false,
                        project_director: false,
                        project_add: false,
                        project_edit: false,
                        project_delete: false,
                        project_comment: true,
                        task_view: true,
                        task_add: true,
                        task_edit: true,
                        task_delete: true
                    });

                    this.selectedUser = '';
                },
                removeMember(index) {
                    this.selectedMembers.splice(index, 1);
                },
                async saveDepartment() {
                    try {
                        const departmentData = {
                            name: this.newDepartment.name,
                            can_project: this.newDepartment.can_project,
                            description: this.newDepartment.description,
                            members: this.selectedMembers.map(m => m.id)
                        };

                        // Add permissions for each member
                        this.selectedMembers.forEach(member => {
                            departmentData[`project_manager[${member.id}]`] = member.project_manager ? 'true' : 'false';
                            departmentData[`project_director[${member.id}]`] = member.project_director ? 'true' : 'false';
                            departmentData[`project_add[${member.id}]`] = member.project_add ? 'true' : 'false';
                            departmentData[`project_edit[${member.id}]`] = member.project_edit ? 'true' : 'false';
                            departmentData[`project_delete[${member.id}]`] = member.project_delete ? 'true' : 'false';
                            departmentData[`project_comment[${member.id}]`] = member.project_comment ? 'true' : 'false';
                            departmentData[`task_view[${member.id}]`] = member.task_view ? 'true' : 'false';
                            departmentData[`task_add[${member.id}]`] = member.task_add ? 'true' : 'false';
                            departmentData[`task_edit[${member.id}]`] = member.task_edit ? 'true' : 'false';
                            departmentData[`task_delete[${member.id}]`] = member.task_delete ? 'true' : 'false';
                        });

                        if (this.editingDepartment) {
                            await axios.post('/api/index.php?model=department&method=edit&id=' + this.editingDepartment.id, departmentData,
                            {
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                }
                            });
                        } else {
                            await axios.post('/api/index.php?model=department&method=add', departmentData,
                            {
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                }
                            });
                        }
                        $('#departmentModal').modal('hide');
                        this.resetDepartmentData();
                        showMessage('部署を保存しました。');
                        this.loadDepartments();
                    } catch (error) {
                        console.error('Error saving department:', error);
                        showMessage('部署の保存に失敗しました。', true);
                    }
                },
                resetDepartmentData() {
                    this.editingDepartment = null;
                    this.selectedUser = '';
                    this.selectedMembers = [];
                    this.newDepartment = {
                        name: '',
                        can_project: 1,
                        description: '',
                        members: [],
                        project_manager: {},
                        project_director: {},
                        project_add: {},
                        project_edit: {},
                        project_delete: {},
                        project_comment: {},
                        task_view: {},
                        task_add: {},
                        task_edit: {},
                        task_delete: {}
                    };
                }
            },
            mounted() {
                this.loadDepartments();
                this.loadAvailableUsers();
                
                const departmentModal = document.getElementById('departmentModal');
                departmentModal.addEventListener('hide.bs.modal', () => {
                    this.resetDepartmentData();
                });
            }
        }).mount('#app');
    </script>