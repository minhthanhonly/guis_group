<?php
require_once('../application/loader.php');
$view->heading('支社設定');

?>
    <div id="app">
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
                        <h2>支社一覧</h2>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#branchModal">
                            <i class="bi bi-plus"></i> 新規支社
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>支社名</th>
                                    <th>タイプ</th>
                                    <th>従業員数</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="branch in branches" :key="branch.id">
                                    <td>{{ branch.name }}</td>
                                    <td>{{ branch.type == 1 ? '本社' : '支社' }}</td>
                                    <td>{{ branch.num_employees }}</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#branchModal" @click="editBranch(branch)">
                                                <i class="icon-base ti tabler-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" @click="deleteBranch(branch)">
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
        <div class="modal fade" id="branchModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ editingBranch ? '支社編集' : '新規支社' }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form @submit.prevent="saveBranch">
                            <div class="mb-3">
                                <label class="form-label">支社名</label>
                                <input type="text" class="form-control" v-model="newBranch.name" required>
                            </div>  
                            <div class="mb-3">
                                <label class="form-label">支社名(ふりがな)</label>
                                <input type="text" class="form-control" v-model="newBranch.name_kana" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">タイプ</label>
                                <select class="form-select" v-model="newBranch.type" required>
                                    <option value="1">本社</option>
                                    <option value="2">支社</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">郵便番号</label>
                                <input type="text" class="form-control" v-model="newBranch.postal_code" required>
                                <button class="btn btn-outline-primary" @click.prevent="searchAddress">住所検索</button>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">住所1</label>
                                <input type="text" class="form-control" v-model="newBranch.address1" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">住所2</label>
                                <input type="text" class="form-control" v-model="newBranch.address2" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">電話番号</label>
                                <input type="text" class="form-control" v-model="newBranch.tel" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">FAX番号</label>
                                <input type="text" class="form-control" v-model="newBranch.fax" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">メールアドレス</label>
                                <input type="text" class="form-control" v-model="newBranch.email" required>
                            </div>

                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="button" class="btn btn-primary" @click="saveBranch">保存</button>
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
                    branches: [],
                    showNewBranchModal: false,
                    editingBranch: null,
                    newBranch: {
                        name: '',
                        name_kana: '',
                        type: '2',
                        postal_code: '',
                        address1: '',
                        address2: '',
                        tel: '',
                        fax: '',
                        email: ''
                    }
                }
            },
            methods: {
                openNewBranchModal() {
                    this.showNewBranchModal = true;
                },
                async loadBranches() {
                    try {
                        const response = await axios.get('/api/index.php?model=branch&method=list');
                        this.branches = response.data;
                    } catch (error) {
                        console.error('Error loading branches:', error);
                        showMessage('支社の読み込みに失敗しました。', true);
                    }
                },
                editBranch(branch) {
                    this.editingBranch = branch;
                    this.newBranch = { ...branch };
                    this.showNewBranchModal = true;
                },
                async deleteBranch(branch) {
                    if (!confirm('この支社を削除してもよろしいですか？')) {
                        return;
                    }

                    try {
                        await axios.post('/api/index.php?model=branch&method=delete&id=' + branch.id);
                        this.loadBranches();
                    } catch (error) {
                        console.error('Error deleting branch:', error);
                        showMessage('支社の削除に失敗しました。', true);
                    }
                },
                async saveBranch() {
                    try {
                        if (this.editingBranch) {
                            await axios.post('/api/index.php?model=branch&method=edit&id=' + this.editingBranch.id, this.newBranch,
                                {
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded'
                                    }
                                }
                            );
                        } else {
                            await axios.post('/api/index.php?model=branch&method=add', this.newBranch, {
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                }
                            });
                        }
                        this.showNewBranchModal = false;
                        this.editingBranch = null;
                        this.newBranch = { name: '', name_kana: '', type: '2', postal_code: '', address1: '', address2: '', tel: '', fax: '', email: '' };
                        $('#branchModal').modal('hide');
                        showMessage('支社を保存しました。');
                        this.loadBranches();
                    } catch (error) {
                        console.error('Error saving branch:', error);
                        showMessage('支社の保存に失敗しました。', true);
                    }
                },
                resetBranchData() {
                    this.editingBranch = null;
                    this.newBranch = { name: '', name_kana: '', type: '2', postal_code: '', address1: '', address2: '', tel: '', fax: '', email: '' };
                },
                searchAddress() {
                    const postalCode = this.newBranch.postal_code;
                    if (postalCode.length === 7) {
                        const apiUrl = `https://zipcloud.ibsnet.co.jp/api/search?zipcode=${postalCode}`;
                        axios.get(apiUrl)
                            .then(response => {
                                if (response.data.results && response.data.results.length > 0) {
                                    const result = response.data.results[0];
                                    this.newBranch.address1 = result.address1;
                                    this.newBranch.address2 = result.address2;
                                } else {
                                    showMessage('郵便番号が見つかりません。', true);
                                }
                            })
                            .catch(error => {
                                console.error('Error searching address:', error);
                                showMessage('住所の検索に失敗しました。', true);
                            });
                    } else {
                        showMessage('郵便番号が正しくありません。', true);  
                    }
                }
            },
            mounted() {
                this.loadBranches();
                
                // Add event listener for modal hide
                const branchModal = document.getElementById('branchModal');
                branchModal.addEventListener('hide.bs.modal', () => {
                    this.resetBranchData();
                });
            }
        }).mount('#app');
    </script>