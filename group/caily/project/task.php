<?php
require_once('../application/loader.php');
$view->heading('タスク管理');

// Get project ID from URL
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
if (!$project_id) {
    header('Location: index.php');
    exit;
}
?>
<div id="app">
    <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="#">タスク管理</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#projectNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="projectNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="detail.php?id=<?php echo $project_id; ?>">概要</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="task.php?project_id=<?php echo $project_id; ?>">タスク</a>
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

    <div class="container-fluid mt-4">
        <!-- Back button -->
        <div class="row">
            <div class="col-12 mb-3">
                <a href="detail.php?id=<?php echo $project_id; ?>" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i> プロジェクト詳細に戻る
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Task List -->
            <div class="col-md-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>タスク一覧</h2>
                    <button class="btn btn-primary" @click="openNewTaskModal">
                        <i class="bi bi-plus"></i> 新規タスク
                    </button>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="taskTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>タスク名</th>
                                        <th>担当者</th>
                                        <th>優先度</th>
                                        <th>ステータス</th>
                                        <th>進捗</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
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
                        <h6>{{ selectedTask.title }}</h6>
                        <p class="text-muted">{{ selectedTask.description }}</p>

                        <div class="mb-3" v-if="selectedTask.due_date">
                            <label class="form-label">期限</label>
                            <div class="text-muted">
                                {{ formatDate(selectedTask.due_date) }}
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ステータス</label>
                            <select class="form-select" v-model="selectedTask.status" @change="updateTaskStatus">
                                <option v-for="status in taskStatuses" :key="status.value" :value="status.value">
                                    {{ status.label }}
                                </option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">進捗 ({{ selectedTask.progress }}%)</label>
                            <input type="range" class="form-range" v-model="selectedTask.progress" 
                                   min="0" max="100" step="5" @change="updateTaskProgress">
                        </div>

                        <div class="mb-3" v-if="selectedTask.assigned_to_name">
                            <label class="form-label">担当者</label>
                            <div>{{ selectedTask.assigned_to_name }}</div>
                        </div>

                        <div v-if="hasSubtasks(selectedTask)" class="mb-3">
                            <label class="form-label">サブタスク</label>
                            <div class="alert alert-info">
                                このタスクにはサブタスクがあります
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New/Edit Task Modal -->
    <div class="modal fade" id="taskModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ editingTask ? 'タスクを編集' : '新規タスク' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="saveTask">
                        <div class="mb-3">
                            <label class="form-label">タスク名 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" v-model="taskForm.title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">説明</label>
                            <textarea class="form-control" v-model="taskForm.description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">開始日</label>
                                    <input type="date" class="form-control" v-model="taskForm.start_date">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">期限</label>
                                    <input type="date" class="form-control" v-model="taskForm.due_date">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">担当者</label>
                                    <select class="form-select" v-model="taskForm.assigned_to">
                                        <option value="">選択してください</option>
                                        <option v-for="user in users" :key="user.userid" :value="user.userid">
                                            {{ user.realname }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">優先度</label>
                                    <select class="form-select" v-model="taskForm.priority" required>
                                        <option v-for="priority in taskPriorities" :key="priority.value" :value="priority.value">
                                            {{ priority.label }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ステータス</label>
                                    <select class="form-select" v-model="taskForm.status" required>
                                        <option v-for="status in taskStatuses" :key="status.value" :value="status.value">
                                            {{ status.label }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">予定時間</label>
                                    <input type="number" class="form-control" v-model="taskForm.estimated_hours" step="0.5" min="0">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">親タスク</label>
                            <select class="form-select" v-model="taskForm.parent_id">
                                <option value="">なし</option>
                                <option v-for="task in availableParentTasks" 
                                        :key="task.id" 
                                        :value="task.id">
                                    {{ task.title }}
                                </option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-primary" @click="saveTask">保存</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dependencies -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">

<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
<script src="assets/js/task-manager.js"></script>

<?php
$view->footing();
?>
