<?php
require_once('../application/loader.php');
$view->heading('プロジェクト詳細');

// Get project ID from URL
$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$project_id) {
    header('Location: index.php');
    exit;
}
?>

<nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="#">プロジェクト詳細</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#projectNavbar" aria-controls="projectNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="projectNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link active" aria-current="page" href="detail.php?id=<?php echo $project_id; ?>">概要</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="task.php?project_id=<?php echo $project_id; ?>">タスク</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="gantt.php?project_id=<?php echo $project_id; ?>">ガントチャート</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="attachment.php?project_id=<?php echo $project_id; ?>">添付ファイル</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container-fluid mt-4" id="app">
    <div class="row">
        <!-- Back button -->
        <div class="col-12 mb-3">
            <a href="index.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> 戻る
            </a>
        </div>

        <!-- Left Column - Project Details -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title">基本情報</h5>
                        <div>
                            <button class="btn btn-outline-primary btn-sm me-2" @click="editProject">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-sm" @click="deleteProject">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="row g-3" v-if="project">
                        <div class="col-md-6">
                            <label class="form-label">プロジェクト番号</label>
                            <input type="text" class="form-control" :value="project.project_number || '-'" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">プロジェクト名</label>
                            <input type="text" class="form-control" :value="project.name" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">開始日</label>
                            <input type="text" class="form-control" :value="formatDate(project.start_date)" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">終了日</label>
                            <input type="text" class="form-control" :value="formatDate(project.end_date)" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">実開始日</label>
                            <input type="text" class="form-control" :value="formatDate(project.actual_start_date)" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">実終了日</label>
                            <input type="text" class="form-control" :value="formatDate(project.actual_end_date)" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ステータス</label>
                            <div>
                                <span class="badge" :class="getStatusBadgeClass(project.status)">{{ getStatusLabel(project.status) }}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">優先度</label>
                            <div>
                                <span class="badge" :class="getPriorityBadgeClass(project.priority)">{{ getPriorityLabel(project.priority) }}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">部署</label>
                            <input type="text" class="form-control" :value="department?.name || '-'" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">建物種類</label>
                            <input type="text" class="form-control" :value="project.building_type || '-'" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">予定時間</label>
                            <input type="text" class="form-control" :value="project.estimated_hours + 'h'" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">実績時間</label>
                            <input type="text" class="form-control" :value="project.actual_hours + 'h'" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">金額</label>
                            <input type="text" class="form-control" :value="formatCurrency(project.amount)" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label">進捗率</label>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar" role="progressbar" 
                                     :class="project.progress === 100 ? 'bg-success' : 'bg-primary'"
                                     :style="{ width: project.progress + '%' }"
                                     :aria-valuenow="project.progress" 
                                     aria-valuemin="0" aria-valuemax="100">
                                    {{ project.progress }}%
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">説明</label>
                            <textarea class="form-control" rows="3" :value="project.description || '-'" readonly></textarea>
                        </div>
                    </div>
                    <div v-else class="text-center py-5">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Project Members -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">プロジェクトメンバー</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>名前</th>
                                    <th>役割</th>
                                    <th>参加日</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="member in members" :key="member.user_id">
                                    <td>{{ member.user_name }}</td>
                                    <td>
                                        <span class="badge" :class="getRoleBadgeClass(member.role)">
                                            {{ getRoleLabel(member.role) }}
                                        </span>
                                    </td>
                                    <td>{{ formatDate(member.created_at) }}</td>
                                </tr>
                                <tr v-if="members.length === 0">
                                    <td colspan="3" class="text-center text-muted">メンバーがいません</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Stats & Comments -->
        <div class="col-md-4">
            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6">
                    <div class="card bg-primary text-white text-center">
                        <div class="card-body">
                            <div class="mb-1">
                                <i class="bi bi-list-task fs-3"></i>
                            </div>
                            <h2 class="mb-1">{{ stats.totalTasks }}</h2>
                            <small>タスク総数</small>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card bg-success text-white text-center">
                        <div class="card-body">
                            <div class="mb-1">
                                <i class="bi bi-check-circle fs-3"></i>
                            </div>
                            <h2 class="mb-1">{{ stats.completedTasks }}</h2>
                            <small>完了タスク</small>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card bg-info text-white text-center">
                        <div class="card-body">
                            <div class="mb-1">
                                <i class="bi bi-clock fs-3"></i>
                            </div>
                            <h2 class="mb-1">{{ stats.timeTracked }}h</h2>
                            <small>記録時間</small>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card bg-warning text-white text-center">
                        <div class="card-body">
                            <div class="mb-1">
                                <i class="bi bi-calendar fs-3"></i>
                            </div>
                            <h2 class="mb-1">{{ stats.totalDays }}</h2>
                            <small>日数</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Comments Section -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">コメント</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="text-center py-5" v-if="comments.length === 0">
                            <i class="bi bi-chat-text fs-1 text-muted"></i>
                            <p class="text-muted">コメントはまだありません</p>
                        </div>
                        <!-- Comments List -->
                        <div v-else class="comment-list mb-4" style="max-height: 400px; overflow-y: auto;">
                            <div v-for="comment in comments" :key="comment.id" class="comment-item mb-3">
                                <div class="d-flex gap-3">
                                    <div class="flex-shrink-0">
                                        <div class="avatar avatar-sm">
                                            <span class="avatar-initial rounded-circle bg-primary">
                                                {{ comment.user_name ? comment.user_name.charAt(0) : '?' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="comment-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0">{{ comment.user_name }}</h6>
                                            <small class="text-muted">{{ formatDateTime(comment.created_at) }}</small>
                                        </div>
                                        <div class="comment-content mt-1">
                                            {{ comment.content }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Comment Input -->
                    <div class="d-flex gap-3">
                        <div class="flex-grow-1">
                            <div class="input-group">
                                <input type="text" class="form-control" 
                                       v-model="newComment" 
                                       @keyup.enter="addComment"
                                       placeholder="コメントを入力...">
                                <button class="btn btn-primary" 
                                        @click="addComment"
                                        :disabled="!newComment.trim()">
                                    <i class="bi bi-send"></i>
                                </button>
                            </div>
                        </div>
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
.avatar-group {
    display: inline-flex;
}
.avatar-group .avatar {
    margin-left: -0.5rem;
}
.avatar-sm {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.avatar-initial {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 500;
}
.comment-item {
    padding: 1rem;
    border-radius: 0.375rem;
    background-color: #f8f9fa;
}
.comment-header {
    margin-bottom: 0.5rem;
}
.comment-content {
    white-space: pre-wrap;
    word-break: break-word;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
<script>
const { createApp } = Vue;

createApp({
    data() {
        return {
            projectId: <?php echo $project_id; ?>,
            project: null,
            department: null,
            members: [],
            tasks: [],
            comments: [],
            newComment: '',
            stats: {
                totalTasks: 0,
                completedTasks: 0,
                timeTracked: 0,
                totalDays: 0
            },
            statuses: [
                { value: 'draft', label: '下書き', color: 'secondary' },
                { value: 'open', label: 'オープン', color: 'info' },
                { value: 'in_progress', label: '進行中', color: 'primary' },
                { value: 'paused', label: '一時停止', color: 'warning' },
                { value: 'completed', label: '完了', color: 'success' },
                { value: 'cancelled', label: 'キャンセル', color: 'danger' }
            ],
            priorities: [
                { value: 'low', label: '低', color: 'secondary' },
                { value: 'medium', label: '中', color: 'primary' },
                { value: 'high', label: '高', color: 'warning' },
                { value: 'urgent', label: '緊急', color: 'danger' }
            ]
        }
    },
    methods: {
        async loadProject() {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getById&id=${this.projectId}`);
                this.project = response.data;
                
                // Load department info if available
                if (this.project.department_id) {
                    this.loadDepartment();
                }
            } catch (error) {
                console.error('Error loading project:', error);
                alert('プロジェクトの読み込みに失敗しました。');
            }
        },
        
        async loadDepartment() {
            try {
                const response = await axios.get(`/api/index.php?model=department&method=get&id=${this.project.department_id}`);
                this.department = response.data;
            } catch (error) {
                console.error('Error loading department:', error);
            }
        },
        
        async loadMembers() {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getMembers&project_id=${this.projectId}`);
                this.members = response.data || [];
            } catch (error) {
                console.error('Error loading members:', error);
            }
        },
        
        async loadTasks() {
            try {
                const response = await axios.get(`/api/index.php?model=task&method=list&project_id=${this.projectId}`);
                this.tasks = response.data || [];
                this.calculateStats();
            } catch (error) {
                console.error('Error loading tasks:', error);
            }
        },
        
        async loadComments() {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getComments&project_id=${this.projectId}`);
                this.comments = response.data || [];
            } catch (error) {
                console.error('Error loading comments:', error);
            }
        },
        
        calculateStats() {
            this.stats.totalTasks = this.tasks.length;
            this.stats.completedTasks = this.tasks.filter(t => t.status === 'completed').length;
            
            // Calculate time tracked (actual hours)
            this.stats.timeTracked = this.tasks.reduce((sum, task) => sum + parseFloat(task.actual_hours || 0), 0);
            
            // Calculate total days
            if (this.project && this.project.start_date) {
                const start = new Date(this.project.start_date);
                const end = this.project.actual_end_date ? new Date(this.project.actual_end_date) : new Date();
                const diffTime = Math.abs(end - start);
                this.stats.totalDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            }
        },
        
        async addComment() {
            if (!this.newComment.trim()) return;
            
            try {
                const formData = new FormData();
                formData.append('model', 'project');
                formData.append('method', 'addComment');
                formData.append('project_id', this.projectId);
                formData.append('content', this.newComment.trim());
                formData.append('user_id', '<?php echo $_SESSION['user_id']; ?>');
                
                await axios.post('/api/index.php', formData);
                
                this.newComment = '';
                this.loadComments();
            } catch (error) {
                console.error('Error adding comment:', error);
                alert('コメントの追加に失敗しました。');
            }
        },
        
        editProject() {
            window.location.href = `index.php?edit=${this.projectId}`;
        },
        
        async deleteProject() {
            if (!confirm('本当にこのプロジェクトを削除しますか？')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('model', 'project');
                formData.append('method', 'delete');
                formData.append('id', this.projectId);
                
                await axios.post('/api/index.php', formData);
                
                alert('プロジェクトを削除しました。');
                window.location.href = 'index.php';
            } catch (error) {
                console.error('Error deleting project:', error);
                if (error.response?.data?.error) {
                    alert(error.response.data.error);
                } else {
                    alert('プロジェクトの削除に失敗しました。');
                }
            }
        },
        
        formatDate(date) {
            if (!date) return '-';
            return moment(date).format('YYYY/MM/DD');
        },
        
        formatDateTime(datetime) {
            if (!datetime) return '-';
            return moment(datetime).format('YYYY/MM/DD HH:mm');
        },
        
        formatCurrency(amount) {
            if (!amount) return '¥0';
            return '¥' + parseInt(amount).toLocaleString();
        },
        
        getStatusLabel(status) {
            const s = this.statuses.find(s => s.value === status);
            return s ? s.label : status;
        },
        
        getStatusBadgeClass(status) {
            const s = this.statuses.find(s => s.value === status);
            return `bg-${s?.color || 'secondary'}`;
        },
        
        getPriorityLabel(priority) {
            const p = this.priorities.find(p => p.value === priority);
            return p ? p.label : priority;
        },
        
        getPriorityBadgeClass(priority) {
            const p = this.priorities.find(p => p.value === priority);
            return `bg-${p?.color || 'secondary'}`;
        },
        
        getRoleLabel(role) {
            const roles = {
                'manager': 'マネージャー',
                'member': 'メンバー',
                'viewer': '閲覧者'
            };
            return roles[role] || role;
        },
        
        getRoleBadgeClass(role) {
            const roleColors = {
                'manager': 'bg-primary',
                'member': 'bg-info',
                'viewer': 'bg-secondary'
            };
            return roleColors[role] || 'bg-secondary';
        }
    },
    mounted() {
        this.loadProject();
        this.loadMembers();
        this.loadTasks();
        this.loadComments();
    }
}).mount('#app');
</script>
