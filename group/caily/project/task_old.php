<?php

require_once('../application/loader.php');
$view->heading('タスク');
?>
    <div id="app">
        <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="#">Project Detail</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#projectNavbar">
            <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="projectNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                <a class="nav-link" href="detail.php">Project Overview</a>
                </li>
                <li class="nav-item">
                <a class="nav-link" href="task.php">Task</a>
                </li>
                <li class="nav-item">
                <a class="nav-link active" href="gantt.php">Gantt Chart</a>
                </li>
                <li class="nav-item">
                <a class="nav-link" href="attachment.php">Attachment</a>
                </li>
            </ul>
            </div>
        </div>
        </nav>

        <div class="container-fluid mt-4">
            <div class="row">
                <!-- Task List -->
                <div class="col-md-8">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>タスク一覧</h2>
                        <button class="btn btn-primary" @click="showNewTaskModal = true">
                            <i class="bi bi-plus"></i> 新規タスク
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>タスク名</th>
                                    <th>プロジェクト</th>
                                    <th>担当者</th>
                                    <th>優先度</th>
                                    <th>ステータス</th>
                                    <th>進捗</th>
                                    <th>期限</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="task in tasks" :key="task.id">
                                    <td>
                                        <a href="#" @click.prevent="viewTask(task)">{{ task.name }}</a>
                                        <span v-if="task.subtask_count" class="badge bg-info">
                                            {{ task.subtask_count }} サブタスク
                                        </span>
                                    </td>
                                    <td>{{ task.project_name }}</td>
                                    <td>{{ task.assignee_name }}</td>
                                    <td>
                                        <span :class="'badge bg-' + getPriorityColor(task.priority)">
                                            {{ task.priority }}
                                        </span>
                                    </td>
                                    <td>
                                        <span :class="'badge bg-' + getStatusColor(task.status)">
                                            {{ getStatusText(task.status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar" :style="{ width: task.progress + '%' }">
                                                {{ task.progress }}%
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ formatDate(task.due_date) }}</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" @click="editTask(task)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" @click="deleteTask(task)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <button class="btn btn-outline-success" @click="startTimeTracking(task)"
                                                    v-if="!task.is_tracking">
                                                <i class="bi bi-play"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" @click="stopTimeTracking(task)"
                                                    v-if="task.is_tracking">
                                                <i class="bi bi-stop"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Task Details -->
                <div class="col-md-4" v-if="selectedTask">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">タスク詳細</h5>
                            <button class="btn-close" @click="selectedTask = null"></button>
                        </div>
                        <div class="card-body">
                            <h6>{{ selectedTask.name }}</h6>
                            <p class="text-muted">{{ selectedTask.description }}</p>

                            <div class="mb-3">
                                <label class="form-label">ステータス</label>
                                <select class="form-select" v-model="selectedTask.status" @change="updateTaskStatus">
                                    <option value="new">新規</option>
                                    <option value="in_progress">進行中</option>
                                    <option value="review">レビュー中</option>
                                    <option value="completed">完了</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">進捗</label>
                                <input type="range" class="form-range" v-model="selectedTask.progress" 
                                       min="0" max="100" step="5" @change="updateTaskProgress">
                                <div class="text-center">{{ selectedTask.progress }}%</div>
                            </div>

                            <!-- Time Tracking -->
                            <div class="mb-3">
                                <h6>工数記録</h6>
                                <div v-for="entry in timeEntries" :key="entry.id" class="mb-2 p-2 border rounded">
                                    <div class="d-flex justify-content-between">
                                        <small>{{ formatDateTime(entry.start_time) }}</small>
                                        <small>{{ formatDuration(entry.duration_seconds) }}</small>
                                    </div>
                                    <div>{{ entry.description }}</div>
                                </div>
                            </div>

                            <!-- Comments -->
                            <div class="mb-3">
                                <h6>コメント</h6>
                                <div v-for="comment in comments" :key="comment.id" class="mb-2 p-2 border rounded">
                                    <div class="d-flex justify-content-between">
                                        <strong>{{ comment.user_name }}</strong>
                                        <small>{{ formatDateTime(comment.created_at) }}</small>
                                    </div>
                                    <div>{{ comment.content }}</div>
                                </div>
                                <div class="input-group">
                                    <input type="text" class="form-control" v-model="newComment" 
                                           placeholder="コメントを入力...">
                                    <button class="btn btn-primary" @click="addComment">
                                        <i class="bi bi-send"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- New Task Modal -->
        <div class="modal fade" id="newTaskModal" tabindex="-1" v-if="showNewTaskModal">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">新規タスク</h5>
                        <button type="button" class="btn-close" @click="showNewTaskModal = false"></button>
                    </div>
                    <div class="modal-body">
                        <form @submit.prevent="saveTask">
                            <div class="mb-3">
                                <label class="form-label">プロジェクト</label>
                                <select class="form-select" v-model="newTask.project_id" required>
                                    <option v-for="project in projects" :key="project.id" :value="project.id">
                                        {{ project.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">タスク名</label>
                                <input type="text" class="form-control" v-model="newTask.name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">説明</label>
                                <textarea class="form-control" v-model="newTask.description" rows="3"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">担当者</label>
                                        <select class="form-select" v-model="newTask.assignee_id">
                                            <option v-for="user in users" :key="user.userid" :value="user.userid">
                                                {{ user.realname }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">優先度</label>
                                        <select class="form-select" v-model="newTask.priority" required>
                                            <option value="low">低</option>
                                            <option value="medium">中</option>
                                            <option value="high">高</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">期限</label>
                                <input type="date" class="form-control" v-model="newTask.due_date">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">親タスク</label>
                                <select class="form-select" v-model="newTask.parent_task_id">
                                    <option value="">なし</option>
                                    <option v-for="task in parentTasks" :key="task.id" :value="task.id">
                                        {{ task.name }}
                                    </option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="showNewTaskModal = false">キャンセル</button>
                        <button type="button" class="btn btn-primary" @click="saveTask">保存</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        const { createApp } = Vue;

        createApp({
            data() {
                return {
                    tasks: [],
                    projects: [],
                    users: [],
                    selectedTask: null,
                    showNewTaskModal: false,
                    newTask: {
                        project_id: null,
                        name: '',
                        description: '',
                        assignee_id: null,
                        priority: 'medium',
                        due_date: '',
                        parent_task_id: null
                    },
                    timeEntries: [],
                    comments: [],
                    newComment: '',
                    eventSource: null
                }
            },
            computed: {
                parentTasks() {
                    if (!this.newTask.project_id) return [];
                    return this.tasks.filter(task => 
                        task.project_id === this.newTask.project_id && 
                        !task.parent_task_id
                    );
                }
            },
            methods: {
                async loadTasks() {
                    try {
                        const response = await axios.get('/api/index.php?model=task&method=list');
                        this.tasks = response.data;
                    } catch (error) {
                        console.error('Error loading tasks:', error);
                        alert('タスクの読み込みに失敗しました。');
                    }
                },
                async loadProjects() {
                    try {
                        const response = await axios.get('/api/index.php?model=project&method=list');
                        this.projects = response.data;
                    } catch (error) {
                        console.error('Error loading projects:', error);
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
                async saveTask() {
                    try {
                        if (this.selectedTask) {
                            await axios.post('/api/index.php?model=task&method=edit&id=' + this.selectedTask.id, this.newTask);
                        } else {
                            await axios.post('/api/index.php?model=task&method=add', this.newTask);
                        }
                        this.showNewTaskModal = false;
                        this.loadTasks();
                    } catch (error) {
                        console.error('Error saving task:', error);
                        alert('タスクの保存に失敗しました。');
                    }
                },
                async viewTask(task) {
                    this.selectedTask = task;
                    await this.loadTaskDetails(task.id);
                },
                async loadTaskDetails(taskId) {
                    try {
                        const [timeResponse, commentsResponse] = await Promise.all([
                            axios.get('/api/index.php?model=task&method=getTimeEntries&id=' + taskId),
                            axios.get('/api/index.php?model=task&method=getComments&id=' + taskId)
                        ]);
                        this.timeEntries = timeResponse.data;
                        this.comments = commentsResponse.data;
                    } catch (error) {
                        console.error('Error loading task details:', error);
                    }
                },
                async updateTaskStatus() {
                    try {
                        await axios.post('/api/index.php?model=task&method=edit&id=' + this.selectedTask.id, {
                            status: this.selectedTask.status
                        });
                        this.loadTasks();
                    } catch (error) {
                        console.error('Error updating task status:', error);
                    }
                },
                async updateTaskProgress() {
                    try {
                        await axios.post('/api/index.php?model=task&method=edit&id=' + this.selectedTask.id, {
                            progress: this.selectedTask.progress
                        });
                        this.loadTasks();
                    } catch (error) {
                        console.error('Error updating task progress:', error);
                    }
                },
                async startTimeTracking(task) {
                    try {
                        await axios.post('/api/index.php?model=task&method=addTimeEntry', {
                            task_id: task.id,
                            user_id: '<?php echo $user->getUserId(); ?>',
                            start_time: new Date().toISOString(),
                            description: '作業開始'
                        });
                        task.is_tracking = true;
                        await this.loadTaskDetails(task.id);
                    } catch (error) {
                        console.error('Error starting time tracking:', error);
                        alert('タイムトラッキングの開始に失敗しました。');
                    }
                },
                async stopTimeTracking(task) {
                    try {
                        const endTime = new Date();
                        await axios.post('/api/index.php?model=task&method=updateTimeEntry', {
                            task_id: task.id,
                            end_time: endTime.toISOString(),
                            duration_seconds: Math.floor((endTime - new Date(task.start_time)) / 1000)
                        });
                        task.is_tracking = false;
                        await this.loadTaskDetails(task.id);
                    } catch (error) {
                        console.error('Error stopping time tracking:', error);
                        alert('タイムトラッキングの停止に失敗しました。');
                    }
                },
                async addComment() {
                    if (!this.newComment.trim()) return;

                    try {
                        await axios.post('/api/index.php?model=task&method=addComment', {
                            task_id: this.selectedTask.id,
                            user_id: '<?php echo $user->getUserId(); ?>',
                            content: this.newComment
                        });
                        this.newComment = '';
                        await this.loadTaskDetails(this.selectedTask.id);
                    } catch (error) {
                        console.error('Error adding comment:', error);
                        alert('コメントの追加に失敗しました。');
                    }
                },
                getStatusColor(status) {
                    const colors = {
                        'new': 'primary',
                        'in_progress': 'info',
                        'review': 'warning',
                        'completed': 'success'
                    };
                    return colors[status] || 'secondary';
                },
                getStatusText(status) {
                    const texts = {
                        'new': '新規',
                        'in_progress': '進行中',
                        'review': 'レビュー中',
                        'completed': '完了'
                    };
                    return texts[status] || status;
                },
                getPriorityColor(priority) {
                    const colors = {
                        'low': 'success',
                        'medium': 'warning',
                        'high': 'danger'
                    };
                    return colors[priority] || 'secondary';
                },
                formatDate(date) {
                    if (!date) return '';
                    return new Date(date).toLocaleDateString('ja-JP');
                },
                formatDateTime(datetime) {
                    if (!datetime) return '';
                    return new Date(datetime).toLocaleString('ja-JP');
                },
                formatDuration(seconds) {
                    const hours = Math.floor(seconds / 3600);
                    const minutes = Math.floor((seconds % 3600) / 60);
                    return `${hours}時間${minutes}分`;
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
                        case 'task_updated':
                            this.loadTasks();
                            if (this.selectedTask && this.selectedTask.id === data.task_id) {
                                this.loadTaskDetails(data.task_id);
                            }
                            break;
                        case 'comment_added':
                            if (this.selectedTask && this.selectedTask.id === data.task_id) {
                                this.loadTaskDetails(data.task_id);
                            }
                            break;
                        case 'time_entry_updated':
                            if (this.selectedTask && this.selectedTask.id === data.task_id) {
                                this.loadTaskDetails(data.task_id);
                            }
                            break;
                    }
                }
            },
            mounted() {
                this.loadTasks();
                this.loadProjects();
                this.loadUsers();
                this.setupSSE();
            }
        }).mount('#app');
    </script>
<?php
$view->footing();
?>