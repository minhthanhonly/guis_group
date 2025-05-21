<?php

require_once('../application/loader.php');
$view->heading('プロジェクト管理');

?>

    <div id="app">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item" v-for="category in categories" :key="category.id" :class="{ 'active': currentCategory === category.id }">
                            <a class="nav-link" href="#" @click.prevent="filterByCategory(category.id)">
                                {{ category.name }}
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="container-fluid mt-4">
            <div class="row">
                <!-- Project List -->
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>プロジェクト一覧</h2>
                        <button class="btn btn-primary" @click="showNewProjectModal = true" data-bs-toggle="modal" data-bs-target="#newProjectModal">
                            <i class="bi bi-plus"></i> <span>新規プロジェクト</span>
                        </button>
                    </div>

                    <div class="table-responsive text-nowrap">
                        <table class="datatables-project table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>プロジェクト名</th>
                                    <th>カテゴリー</th>
                                    <th>開始日</th>
                                    <th>終了日</th>
                                    <th>見積時間</th>
                                    <th>ステータス</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody class="table-border-bottom-0"></tbody>
                        </table>
                    </div>
                </div>

                
            </div>
        </div>

        <!-- New Project Modal -->
        <div class="modal fade" id="newProjectModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><span v-if="!isEdit">新規プロジェクト</span><span v-if="isEdit">プロジェクト編集</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form @submit.prevent="saveProject">
                            <div class="mb-3">
                                <label class="form-label">お施主様名</label>
                                <input type="text" class="form-control" v-model="newProject.name" required>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">会社名</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">支店名</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">担当者名</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">建物規模</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">建物種類</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">案件番号</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">連絡番号</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">受注形態</label>
                                        <select class="form-select" required>
                                            <option value="新規">新規</option>
                                            <option value="修正">修正</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">カテゴリー</label>
                                        <select class="form-select" v-model="newProject.category_id" required readonly disabled>
                                            <option v-for="category in categories" :key="category.id" :value="category.id">
                                                {{ category.name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">開始日</label>
                                        <input type="text" class="form-control" v-model="newProject.start_date">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">終了日</label>
                                        <input type="text" class="form-control" v-model="newProject.end_date">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">GUIS担当者</label>
                                        <select class="form-select" v-model="newProject.members" multiple>
                                            <option v-for="user in users" :key="user.userid" :value="user.userid">
                                                {{ user.realname }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">メンバー</label>
                                        <select class="form-select" v-model="newProject.members" multiple>
                                            <option v-for="user in users" :key="user.userid" :value="user.userid">
                                                {{ user.realname }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">案件状況</label>
                                        <select class="form-select" v-model="newProject.status" required>
                                            <option value="draft">下書き</option>
                                            <option value="new">新規</option>
                                            <!-- <option value="in_progress">進行中</option>
                                            <option value="completed">完了</option>
                                            <option value="paused">一時停止</option>
                                            <option value="stop">停止</option> -->
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">優先度</label>
                                        <select class="form-select" required>
                                            <option value="1">低</option>
                                            <option value="2">中</option>
                                            <option value="3">高</option>
                                            <option value="4">緊急</option>
                                        </select>
                                    </div>
                                </div>
                                <!-- <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">受注状況</label>
                                        <select class="form-select" required>
                                            <option value=""></option>
                                            <option value="">見積まだ</option>
                                            <option value="">見積送付済み</option>
                                            <option value="">注文</option>
                                            <option value="">請求書送付済み</option>
                                            <option value="">支払済み</option>
                                        </select>
                                    </div>
                                </div> -->
                            </div>
                            <!-- <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">実納品日</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">請求書送付日</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                            </div> -->
                            <!-- <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">金額</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">予定時間</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                            </div> -->
                            <div class="mb-3">
                                <label class="form-label">メモ</label>
                                <textarea class="form-control" v-model="newProject.description" rows="3"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="button" class="btn btn-primary" @click="saveProject">保存</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

   

<?php
$view->footing();
?>

    <script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        const { createApp } = Vue;

        createApp({
            data() {
                return {
                    isEdit: false,
                    currentCategory: null,
                    categories: [],
                    users: [],
                    selectedProject: null,
                    showNewProjectModal: false,
                    newProject: {
                        name: '',
                        description: '',
                        category_id: null,
                        start_date: '',
                        end_date: '',
                        estimated_hours: 0,
                        status: 'new',
                        members: [],
                    },
                    projectMembers: [],
                    projectTasks: [],
                    newMemberId: '',
                    eventSource: null,
                    dataTable: null
                }
            },
            computed: {
                availableUsers() {
                    return this.users.filter(user => 
                        !this.projectMembers.some(member => member.user_id === user.userid)
                    );
                }
            },
            methods: {
                async loadCategories() {
                    try {
                        const response = await axios.get('/api/index.php?model=category&method=list');
                        this.categories = response.data;
                        // Set default category if none is selected
                        if (!this.currentCategory && this.categories.length > 0) {
                            this.currentCategory = this.categories[0].id;
                            this.newProject.category_id = this.currentCategory;
                            this.loadProjects();
                        }
                    } catch (error) {
                        console.error('Error loading categories:', error);
                    }
                },
                
                async loadUsers() {
                    try {
                        const response = await axios.get('/api/index.php?model=user&method=getList');
                        this.users = response.data.list;
                    } catch (error) {
                        console.error('Error loading users:', error);
                    }
                },
                async saveProject() {
                    try {
                        if (this.selectedProject) {
                            await axios.post('/api/index.php?model=project&method=edit&id=' + this.selectedProject.id, this.newProject, {
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                }
                            });
                        } else {
                            await axios.post('/api/index.php?model=project&method=add', this.newProject, {
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                }
                            });
                        }
                        this.showNewProjectModal = false;
                        this.loadProjects();
                    } catch (error) {
                        console.error('Error saving project:', error);
                        alert('プロジェクトの保存に失敗しました。');
                    }
                },
                
                async loadProjectDetails(projectId) {
                    try {
                        const [membersResponse, tasksResponse] = await Promise.all([
                            axios.get('/api/index.php?model=project&method=getMembers&id=' + projectId),
                            axios.get('/api/index.php?model=project&method=getTasks&id=' + projectId)
                        ]);
                        this.projectMembers = membersResponse.data;
                        this.projectTasks = tasksResponse.data;
                    } catch (error) {
                        console.error('Error loading project details:', error);
                    }
                },
                async updateProjectStatus() {
                    try {
                        await axios.post('/api/index.php?model=project&method=edit&id=' + this.selectedProject.id, {
                            status: this.selectedProject.status
                        });
                        this.loadProjects();
                    } catch (error) {
                        console.error('Error updating project status:', error);
                    }
                },
                async addMember() {
                    if (!this.newMemberId) return;

                    try {
                        await axios.post('/api/index.php?model=project&method=addMember&id=' + this.selectedProject.id, {
                            user_id: this.newMemberId,
                            role: 'member'
                        });
                        this.newMemberId = '';
                        await this.loadProjectDetails(this.selectedProject.id);
                    } catch (error) {
                        console.error('Error adding member:', error);
                        alert('メンバーの追加に失敗しました。');
                    }
                },
                async removeMember(userId) {
                    try {
                        await axios.post('/api/index.php?model=project&method=removeMember&id=' + this.selectedProject.id, {
                            user_id: userId
                        });
                        await this.loadProjectDetails(this.selectedProject.id);
                    } catch (error) {
                        console.error('Error removing member:', error);
                        alert('メンバーの削除に失敗しました。');
                    }
                },
                getStatusColor(status) {
                    const colors = {
                        'draft': 'secondary',
                        'new': 'primary',
                        'in_progress': 'info',
                        'completed': 'success',
                        'paused': 'warning'
                    };
                    return colors[status] || 'secondary';
                },
                getStatusText(status) {
                    const texts = {
                        'draft': '下書き',
                        'new': '新規',
                        'in_progress': '進行中',
                        'completed': '完了',
                        'paused': '一時停止'
                    };
                    return texts[status] || status;
                },
                formatDate(date) {
                    if (!date) return '';
                    return new Date(date).toLocaleDateString('ja-JP');
                },
                setupSSE() {
                    if (this.eventSource) {
                        this.eventSource.close();
                    }

                    this.eventSource = new EventSource('/api/project_management.php?action=events');
                    
                    this.eventSource.onmessage = (event) => {
                        const data = JSON.parse(event.data);
                        this.handleSSEEvent(data);
                    };

                    this.eventSource.onerror = (error) => {
                        console.error('SSE Error:', error);
                        this.eventSource.close();
                        setTimeout(() => this.setupSSE(), 5000);
                    };
                },
                handleSSEEvent(data) {
                    switch (data.type) {
                        case 'project_updated':
                            this.loadProjects();
                            if (this.selectedProject && this.selectedProject.id === data.project_id) {
                                this.loadProjectDetails(data.project_id);
                            }
                            break;
                        case 'task_updated':
                            if (this.selectedProject) {
                                this.loadProjectDetails(this.selectedProject.id);
                            }
                            break;
                    }
                },
                filterByCategory(categoryId) {
                    this.currentCategory = categoryId;
                    this.newProject.category_id = categoryId;
                    this.loadProjects();
                },
                editProject(project) {
                    this.newProject = {
                        name: project.name,
                        description: project.description,
                        category_id: project.category_id,
                        start_date: project.start_date,
                        end_date: project.end_date,
                        estimated_hours: project.estimated_hours,
                        status: project.status,
                        members: project.members || []
                    };
                    this.isEdit = true;
                    $('#newProjectModal').modal('show');
                },

                async loadProjects() {
                    try {
                        let url = '/api/index.php?model=project&method=list';
                        if (this.currentCategory) {
                            url += '&category_id=' + this.currentCategory;
                        }
                        const response = await axios.get(url);
                        
                        if (this.dataTable) {
                            this.dataTable.destroy();
                        }

                        this.dataTable = new DataTable('.datatables-project', {
                            data: response.data,
                            columns: [
                                { 
                                    data: 'id',
                                    visible: true
                                },
                                { 
                                    data: 'name',
                                    render: function(data, type, row) {
                                        return `<a href="#" class="view-project">${data}</a>`;
                                    }
                                },
                                { data: 'category_name' },
                                { 
                                    data: 'start_date',
                                    render: function(data) {
                                        return data ? new Date(data).toLocaleDateString('ja-JP') : '';
                                    }
                                },
                                { 
                                    data: 'end_date',
                                    render: function(data) {
                                        return data ? new Date(data).toLocaleDateString('ja-JP') : '';
                                    }
                                },
                                { data: 'estimated_hours' },
                                { 
                                    data: 'status',
                                    render: function(data) {
                                        const colors = {
                                            'draft': 'secondary',
                                            'new': 'primary',
                                            'in_progress': 'info',
                                            'completed': 'success',
                                            'paused': 'warning'
                                        };
                                        const texts = {
                                            'draft': '下書き',
                                            'new': '新規',
                                            'in_progress': '進行中',
                                            'completed': '完了',
                                            'paused': '一時停止'
                                        };
                                        return `<span class="badge bg-${colors[data] || 'secondary'}">${texts[data] || data}</span>`;
                                    }
                                },
                                {
                                    data: null,
                                    render: function(data) {
                                        return `
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary edit-project">
                                                    <i class="ti tabler-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-danger delete-project">
                                                    <i class="ti tabler-trash"></i>
                                                </button>
                                            </div>
                                        `;
                                    }
                                }
                            ],
                            language: {
                                url: '/assets/vendor/libs/datatables-bs5/ja.json'
                            },
                            order: [[0, 'desc']],
                            pageLength: 25,
                            responsive: true
                        });


                        this.dataTable.on('click', '.edit-project', (e) => {
                            const data = this.dataTable.row(e.target.closest('tr')).data();
                            this.editProject(data);
                        });

                        this.dataTable.on('click', '.delete-project', (e) => {
                            const data = this.dataTable.row(e.target.closest('tr')).data();
                            this.deleteProject(data);
                        });

                    } catch (error) {
                        console.error('Error loading projects:', error);
                        // alert('プロジェクトの読み込みに失敗しました。');
                    }
                }
            },
            mounted() {
                this.loadCategories();
                //await this.loadProjects();
                this.loadUsers();
                //this.setupSSE();
            }
        }).mount('#app');
    </script>
