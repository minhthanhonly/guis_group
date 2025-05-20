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
                            <i class="bi bi-plus"></i> 新規プロジェクト
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>プロジェクト名</th>
                                    <th>カテゴリー</th>
                                    <th>メンバー</th>
                                    <th>ステータス</th>
                                    <th>進捗</th>
                                    <th>期間</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="project in projects" :key="project.id">
                                    <td>
                                        <a :href="'/project/view.php?id=' + project.id">{{ project.name }}</a>
                                    </td>
                                    <td>{{ project.category_name }}</td>
                                    <td>{{ project.member_count }}人</td>
                                    <td>
                                        <span :class="'badge bg-' + getStatusColor(project.status)">
                                            {{ getStatusText(project.status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar" :style="{ width: project.progress + '%' }">
                                                {{ project.progress }}%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        {{ formatDate(project.start_date) }} - {{ formatDate(project.end_date) }}
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#newProjectModal" @click="editProject(project)">
                                                <i class="icon-base ti tabler-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" @click="deleteProject(project)">
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

        <!-- Project Details -->
        <div class="col-md-4" v-if="selectedProject">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">プロジェクト詳細</h5>
                    <button class="btn-close" @click="selectedProject = null"></button>
                </div>
                <div class="card-body">
                    <h6>{{ selectedProject.name }}</h6>
                    <p class="text-muted">{{ selectedProject.description }}</p>

                    <div class="mb-3">
                        <label class="form-label">ステータス</label>
                        <select class="form-select" v-model="selectedProject.status" @change="updateProjectStatus">
                            <option value="draft">下書き</option>
                            <option value="new">新規</option>
                            <option value="in_progress">進行中</option>
                            <option value="completed">完了</option>
                            <option value="paused">一時停止</option>
                        </select>
                    </div>

                    <!-- Project Members -->
                    <div class="mb-3">
                        <h6>メンバー</h6>
                        <div class="list-group">
                            <div v-for="member in projectMembers" :key="member.user_id" 
                                    class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>{{ member.realname }}</strong>
                                    <small class="text-muted d-block">{{ member.role }}</small>
                                </div>
                                <button class="btn btn-sm btn-outline-danger" 
                                        @click="removeMember(member.user_id)">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                        </div>
                        <div class="input-group mt-2">
                            <select class="form-select" v-model="newMemberId">
                                <option value="">メンバーを選択...</option>
                                <option v-for="user in availableUsers" :key="user.userid" :value="user.userid">
                                    {{ user.realname }}
                                </option>
                            </select>
                            <button class="btn btn-primary" @click="addMember">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Project Tasks -->
                    <div class="mb-3">
                        <h6>タスク</h6>
                        <div class="list-group">
                            <div v-for="task in projectTasks" :key="task.id" 
                                    class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>{{ task.name }}</strong>
                                        <small class="text-muted d-block">
                                            担当者: {{ task.assignee_name || '未割り当て' }}
                                        </small>
                                    </div>
                                    <span :class="'badge bg-' + getStatusColor(task.status)">
                                        {{ getStatusText(task.status) }}
                                    </span>
                                </div>
                                <div class="progress mt-2">
                                    <div class="progress-bar" :style="{ width: task.progress + '%' }">
                                        {{ task.progress }}%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- New Project Modal -->
        <div class="modal fade" id="newProjectModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">新規プロジェクト</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form @submit.prevent="saveProject">
                            <div class="mb-3">
                                <label class="form-label">プロジェクト名</label>
                                <input type="text" class="form-control" v-model="newProject.name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">説明</label>
                                <textarea class="form-control" v-model="newProject.description" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">カテゴリー</label>
                                <select class="form-select" v-model="newProject.category_id" required readonly>
                                    <option v-for="category in categories" :key="category.id" :value="category.id">
                                        {{ category.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">開始日</label>
                                        <input type="date" class="form-control" v-model="newProject.start_date">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">終了日</label>
                                        <input type="date" class="form-control" v-model="newProject.end_date">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">見積時間</label>
                                        <input type="number" class="form-control" v-model="newProject.estimated_hours" min="0" step="0.5">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">ステータス</label>
                                        <select class="form-select" v-model="newProject.status" required>
                                            <option value="draft">下書き</option>
                                            <option value="new">新規</option>
                                            <option value="in_progress">進行中</option>
                                            <option value="completed">完了</option>
                                            <option value="paused">一時停止</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">メンバー</label>
                                <select class="form-select" v-model="newProject.members" multiple>
                                    <option v-for="user in users" :key="user.userid" :value="user.userid">
                                        {{ user.realname }}
                                    </option>
                                </select>
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

    <script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        const { createApp } = Vue;

        createApp({
            data() {
                return {
                    currentCategory: null,
                    projects: [],
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
                        members: []
                    },
                    projectMembers: [],
                    projectTasks: [],
                    newMemberId: '',
                    eventSource: null
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
                async loadProjects() {
                    try {
                        let url = '/api/index.php?model=project&method=list';
                        if (this.currentCategory) {
                            url += '&category_id=' + this.currentCategory;
                        }
                        const response = await axios.get(url);
                        this.projects = response.data;
                    } catch (error) {
                        console.error('Error loading projects:', error);
                        alert('プロジェクトの読み込みに失敗しました。');
                    }
                },
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
                async viewProject(project) {
                    this.selectedProject = project;
                    await this.loadProjectDetails(project.id);
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
                        members: project.members
                    };
                    this.showNewProjectModal = true;
                }
            },
            mounted() {
                this.loadProjects();
                this.loadCategories();
                this.loadUsers();
                //this.setupSSE();
            }
        }).mount('#app');
    </script>

<?php
$view->footing();
?>
