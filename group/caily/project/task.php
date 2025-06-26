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
<div id="app" class="container-fluid mt-4" v-cloak>
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

    <div class="mt-4">
        <!-- ボー lọc -->
        <div class="row mb-4">
            <div class="col-md-3 mb-2">
                <select class="form-select" v-model="filterStatus">
                    <option value="">全てのステータス</option>
                    <option value="todo">未開始</option>
                    <option value="in-progress">進行中</option>
                    <option value="completed">完了</option>
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <select class="form-select" v-model="filterPriority">
                    <option value="">全ての優先度</option>
                    <option value="high">高</option>
                    <option value="medium">中</option>
                    <option value="low">低</option>
                </select>
            </div>
            <!-- <div class="col-md-3 mb-2">
                <input type="text" class="form-control" v-model="filterDueDate" placeholder="yyyy-mm-dd HH:ii">
            </div>
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-secondary w-100" @click="applyFilter">
                    <i class="fas fa-filter me-1"></i> フィルター
                </button>
            </div> -->
        </div>
        <!-- 統計 task -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col me-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    タスク総数
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ stats.total }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-tasks fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col me-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    完了済み
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ stats.completed }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col me-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    期限切れ
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ stats.overdue }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Danh sách member avatar và nút quản lý -->
        <!-- <div class="d-flex align-items-center mb-2">
            <div class="me-2">担当者:</div>
            <div class="d-flex align-items-center flex-wrap">
                <div v-for="member in projectMembers" :key="member.user_id"
                    class="avatar me-1 mb-1 avatar-online"
                    data-bs-toggle="tooltip"
                    :aria-label="member.user_name"
                    :data-bs-original-title="member.user_name"
                    style="cursor:pointer;" @click="openMemberModal">
                    <img v-if="!member.avatarError && getAvatarSrc(member)" class="rounded-circle" :src="getAvatarSrc(member)" :alt="member.user_name" @error="handleAvatarError(member)" width="32" height="32">
                    <span v-else class="avatar-initial rounded-circle bg-label-primary" style="width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;">{{ getInitials(member.user_name) }}</span>
                </div>
                <button class="btn btn-sm btn-outline-secondary ms-2" @click="openMemberModal"><i class="bi bi-people"></i> 管理</button>
            </div>
        </div> -->
        <!-- Tiêu đề các cột và nút tạo task -->
         
        <div class="d-flex align-items-center justify-content-end mb-2">
            <button class="btn btn-primary ms-2" @click="openNewTaskModal">
                <i class="bi bi-plus"></i> 新規タスク
            </button>
        </div>
        
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="row w-100 g-0 align-items-center fw-bold text-primary bg-light">
                <div class="col-3 py-2 px-2">タスク</div>
                <div class="col-1 py-2 pe-2">優先度</div>
                <div class="col-2 py-2 pe-2">期限日</div>
                <div class="col-2 py-2 pe-2">担当者</div>
                <div class="col-1 py-2 pe-2">ステータス</div>
                <div class="col-2 py-2 pe-2">進捗</div>
                <div class="col-1 py-2">操作</div>
            </div>
        </div>
        <!-- Danh sách task dạng div card/list -->
        <div class="task-list">
            <div v-if="filteredTasks.length === 0 && inlineTasks.length === 0" class="text-center text-muted py-4">
                <i class="bi bi-inbox fs-1 mb-2"></i>
                <div class="card mb-2 p-2">タスクがありません</div>
            </div>
            <div v-for="(inlineTask, idx) in inlineTasks" :key="'inline-' + idx" class="card mb-2">
                <div class="row g-0 align-items-center">
                    <div class="col-3">
                        <div class="p-2">
                            <input type="text" class="form-control inline-task-input" v-model="inlineTask.title" placeholder="タスク名" required>
                        </div>
                    </div>
                    <div class="col-1">
                        <div class="py-2 pe-2">
                            <div class="btn-group w-100">
                                <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light w-100"
                                        :class="getPriorityButtonClass(inlineTask.priority)"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    {{ getPriorityLabel(inlineTask.priority) }}
                                </button>
                                <ul class="dropdown-menu">
                                    <li v-for="priority in taskPriorities" :key="priority.value">
                                        <a class="dropdown-item waves-effect" href="#" @click.prevent="inlineTask.priority = priority.value; closeDropdown($event)">
                                            {{ priority.label }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="py-2 pe-2">
                            <input type="text" class="form-control datetimepicker" v-model="inlineTask.due_date" placeholder="選択してください">
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="d-flex align-items-center flex-wrap avatar-group" @click="openAssigneeModal(idx)">
                            <template v-if="inlineTask.assignees && inlineTask.assignees.length">
                                <template v-for="(userId, i) in inlineTask.assignees.slice(0, 5)">
                                    <div :key="userId" class="avatar me-1">
                                        <img v-if="!projectMembers.find(m => m.user_id == userId)?.avatarError && getAvatarSrc(projectMembers.find(m => m.user_id == userId))" class="rounded-circle" :src="getAvatarSrc(projectMembers.find(m => m.user_id == userId))" :alt="projectMembers.find(m => m.user_id == userId)?.user_name" @error="handleAvatarError(projectMembers.find(m => m.user_id == userId))" width="28" height="28">
                                        <span v-else class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(projectMembers.find(m => m.user_id == userId)?.user_name) }}</span>
                                    </div>
                                </template>
                                <div v-if="inlineTask.assignees.length > 5" class="avatar">
                                    <span class="avatar-initial rounded-circle pull-up" data-bs-toggle="tooltip" data-bs-placement="bottom" :data-bs-original-title="assigneeNames(inlineTask.assignees.slice(5))">
                                        +{{ inlineTask.assignees.length - 5 }}
                                    </span>
                                </div>
                            </template>
                            <span v-else class="text-muted">選択してください</span>
                            <i class="bi bi-chevron-down ms-1"></i>
                        </div>
                    </div>
                    
                    <div class="col-1">
                        <div class="py-2 pe-2">
                            <div class="btn-group w-100">
                                <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light w-100"
                                        :class="getStatusButtonClass(inlineTask.status)"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    {{ getStatusLabel(inlineTask.status) }}
                                </button>
                                <ul class="dropdown-menu">
                                    <li v-for="status in taskStatuses" :key="status.value">
                                        <a class="dropdown-item waves-effect" href="#" @click.prevent="inlineTask.status = status.value; closeDropdown($event)">
                                            {{ status.label }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="py-2 pe-2 d-flex align-items-center">
                            <input type="range" min="0" max="100" step="1" v-model.number="inlineTask.progress" class="w-100">
                            <span class="ms-2">{{ inlineTask.progress || 0 }}%</span>
                        </div>
                    </div>
                    <div class="col-1 d-flex align-items-center justify-content-center gap-2">
                        <button class="btn btn-sm btn-success me-1" @click="saveTaskInline(idx)"><i class="fas fa-check"></i></button>
                        <button class="btn btn-sm btn-secondary" @click="cancelTaskInline(idx)"><i class="fas fa-times"></i></button>
                    </div>
                </div>
            </div>
            <div v-for="task in filteredTasks" :key="task.id" class="card mb-2" :data-id="task.id">
                <div class="row g-0 align-items-center">
                    <div class="col-3 d-flex align-items-center">
                        <span class="drag-handle" style="cursor: move;">≡</span>
                        <div class="p-2">
                            <span class="fw-bold">{{ task.title }}</span>
                        </div>
                    </div>
                    <div class="col-1">
                        <div class="py-2 pe-2">
                            <div class="btn-group w-100">
                                <label class="badge waves-light w-100"
                                        :class="getPriorityButtonClass(task.priority)" >
                                    {{ getPriorityLabel(task.priority) }}
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="col-2">
                        <span>{{ formatDate(task.due_date) }}</span>
                    </div>
                    <div class="col-2">
                        <div class="d-flex align-items-center flex-wrap avatar-group">
                            <template v-if="task.assigned_to">
                                <template v-for="(userId, i) in task.assigned_to.split(',').slice(0, 5)">
                                    <div :key="userId" class="avatar me-1">
                                        <img v-if="!projectMembers.find(m => m.user_id == userId)?.avatarError && getAvatarSrc(projectMembers.find(m => m.user_id == userId))" class="rounded-circle" :src="getAvatarSrc(projectMembers.find(m => m.user_id == userId))" :alt="projectMembers.find(m => m.user_id == userId)?.user_name" @error="handleAvatarError(projectMembers.find(m => m.user_id == userId))" width="28" height="28">
                                        <span v-else class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(projectMembers.find(m => m.user_id == userId)?.user_name) }}</span>
                                    </div>
                                </template>
                                <div v-if="task.assigned_to.split(',').length > 5" class="avatar">
                                    <span class="avatar-initial rounded-circle pull-up" data-bs-toggle="tooltip" data-bs-placement="bottom" :data-bs-original-title="assigneeNames(task.assigned_to.split(',').slice(5))">
                                        +{{ task.assigned_to.split(',').length - 5 }}
                                    </span>
                                </div>
                            </template>
                            <span v-else class="text-muted">選択してください</span>
                        </div>
                    </div>
                   
                    <div class="col-1">
                        <div class="py-2 pe-2">
                            <div class="btn-group w-100">
                                <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light w-100"
                                        :class="getStatusButtonClass(task.status)"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    {{ getStatusLabel(task.status) }}
                                </button>
                                <ul class="dropdown-menu">
                                    <li v-for="status in taskStatuses" :key="status.value" class="dropdown-item" style="cursor:pointer" @click="updateTaskStatus(task, status.value)">
                                        {{ status.label }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="py-2 pe-2 d-flex align-items-center">
                            <input type="range" min="0" max="100" step="1" v-model.number="task.progress" class="w-100" @change="updateTaskProgress(task)">
                            <span class="ms-2">{{ task.progress || 0 }}%</span>
                        </div>
                    </div>
                    <div class="col-1 d-flex align-items-center justify-content-center gap-2">
                        <button class="btn btn-sm btn-outline-primary me-1" @click="editTaskInline(task)"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger" @click="deleteTask(task)"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modal thêm/sửa task -->
        <div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addTaskModalLabel">{{ editingTask ? 'タスクを編集' : '新しいタスクを追加' }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                    </div>
                    <div class="modal-body">
                        <form @submit.prevent="saveTask">
                            <div class="mb-3">
                                <label for="taskTitle" class="form-label">タスク名</label>
                                <input type="text" class="form-control" id="taskTitle" v-model="taskForm.title" placeholder="タスク名を入力" required>
                            </div>
                            <div class="mb-3">
                                <label for="taskDescription" class="form-label">説明</label>
                                <textarea class="form-control" id="taskDescription" v-model="taskForm.description" rows="3"></textarea>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="taskPriority" class="form-label">優先度</label>
                                    <select class="form-select" id="taskPriority" v-model="taskForm.priority">
                                        <option value="high">高</option>
                                        <option value="medium">中</option>
                                        <option value="low">低</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="taskDueDate" class="form-label">期限日</label>
                                    <input type="text" class="form-control" id="taskDueDate" v-model="taskForm.due_date" placeholder="yyyy-mm-dd HH:ii">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="taskStatus" class="form-label">ステータス</label>
                                <select class="form-select form-select-sm" id="taskStatus" v-model="taskForm.status" @change="updateTaskStatus(taskForm)">
                                    <option value="todo">未開始</option>
                                    <option value="in-progress">進行中</option>
                                    <option value="completed">完了</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="taskAssignee" class="form-label">担当者</label>
                                <select class="form-select" id="taskAssignee" v-model="taskForm.assigned_to">
                                    <option value="">選択してください</option>
                                    <option v-for="member in projectMembers" :key="member.user_id" :value="member.user_id">
                                        {{ member.user_name }}
                                    </option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="button" class="btn btn-primary" @click="saveTask">{{ editingTask ? '保存' : 'タスク追加' }}</button>
                    </div>
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
                    <select class="form-select form-select-sm" v-model="selectedTask.status" @change="updateTaskStatus(selectedTask)">
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

    <!-- Modal quản lý member -->
    <div class="modal fade" tabindex="-1" :class="{show: showMemberModal}" style="display: block;" v-if="showMemberModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">メンバー管理</h5>
                    <button type="button" class="btn-close" @click="closeMemberModal"></button>
                </div>
                <div class="modal-body">
                    <div v-if="projectMembers.length === 0" class="text-muted">メンバーがいません</div>
                    <ul class="list-group mb-3">
                        <li v-for="member in projectMembers" :key="member.user_id" class="list-group-item d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <img v-if="!member.avatarError && getAvatarSrc(member)" class="rounded-circle me-2" :src="getAvatarSrc(member)" :alt="member.user_name" width="32" height="32" @error="handleAvatarError(member)">
                                <span v-else class="avatar-initial rounded-circle bg-label-primary me-2" style="width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;">{{ getInitials(member.user_name) }}</span>
                                <span>{{ member.user_name }}</span>
                            </div>
                            <button class="btn btn-sm btn-outline-danger" @click="removeMember(member.user_id)"><i class="bi bi-x"></i></button>
                        </li>
                    </ul>
                    <!-- Thêm member mới: demo, thực tế có thể là dropdown hoặc search -->
                    <div class="input-group">
                        <input type="text" class="form-control" v-model="newMemberName" placeholder="ユーザー名で追加 (デモ)">
                        <button class="btn btn-primary" @click="addMember(newMemberName)">追加</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal chọn assignee cho dòng nhập nhanh -->
    <div class="modal fade" tabindex="-1" :class="{show: assigneeModal.show}" style="display: block;" v-if="assigneeModal.show">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">担当者を選択</h5>
                    <button type="button" class="btn-close" @click="closeAssigneeModal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex flex-wrap">
                        <div v-for="member in projectMembers" :key="member.user_id" class="m-2 text-center" style="cursor:pointer;">
                            <div @click="toggleAssignee(member.user_id)" :class="{'border border-primary': assigneeModal.selected.includes(member.user_id)}" style="display:inline-block;border-radius:50%;padding:2px;">
                                <img v-if="!member.avatarError && getAvatarSrc(member)" class="rounded-circle" :src="getAvatarSrc(member)" :alt="member.user_name" width="40" height="40" @error="handleAvatarError(member)">
                                <span v-else class="avatar-initial rounded-circle bg-label-primary" style="width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;">{{ getInitials(member.user_name) }}</span>
                            </div>
                            <div style="font-size:12px;max-width:60px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ member.user_name }}</div>
                            <input type="checkbox" class="form-check-input mt-1" :checked="assigneeModal.selected.includes(member.user_id)" @change="toggleAssignee(member.user_id)">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="closeAssigneeModal">キャンセル</button>
                    <button class="btn btn-primary" @click="confirmAssigneeModal">OK</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.title_block{
    border: 1px solid #ccc;
}
.title_block > div + div {
    border-left: 1px solid #ccc;
}
</style>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="assets/js/task-manager.js"></script>



<?php
$view->footing();
?>


<script>
const PROJECT_ID = <?php echo $project_id; ?>;
</script>

