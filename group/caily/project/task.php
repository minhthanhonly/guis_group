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
            <a class="navbar-brand fw-bold" href="#"><span class="badge badge-sm bg-label-info">#P{{ projectInfo?.project_number }}</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#projectNavbar" aria-controls="projectNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="projectNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                <a class="nav-link" href="detail.php?id=<?php echo $project_id; ?>">概要</a>
                </li>
                <li class="nav-item">
                <a class="nav-link active" aria-current="page" href="task.php?project_id=<?php echo $project_id; ?>">タスク<span class="badge badge-sm ms-1 rounded-pill">{{ projectInfo?.task_count }}</span></a>
                </li>
                <li class="nav-item">
                <a class="nav-link" href="gantt.php?project_id=<?php echo $project_id; ?>">ガントチャート</a>
                </li>
                <li class="nav-item">
                <a class="nav-link" href="drawings.php?project_id=<?php echo $project_id; ?>">図面<span class="badge badge-sm bg-info ms-1 rounded-pill">{{ projectInfo?.drawing_count }}</span></a>
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
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ taskStats.total }}</div>
                            </div>
                            <div class="col-md-auto">
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
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ taskStats.completed }}</div>
                            </div>
                            <div class="col-md-auto">
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
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ taskStats.overdue }}</div>
                            </div>
                            <div class="col-md-auto">
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
         
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="d-flex align-items-center gap-2">
            <select class="form-select" v-model="filterStatus">
                <option value="">全てのステータス</option>
                <option value="todo">未開始</option>
                <option value="in-progress">進行中</option>
                <option value="confirming">確認中</option>
                <option value="paused">一時停止</option>
                <option value="completed">完了</option>
                <option value="cancelled">キャンセル</option>
            </select>
            <select class="form-select" v-model="filterPriority">
                <option value="">全ての優先度</option>
                <option value="high">高</option>
                <option value="medium">中</option>
                <option value="low">低</option>
            </select>
            </div>
            <button class="btn btn-primary ms-2" @click="openNewTaskModal">
                <i class="bi bi-plus"></i> 新規タスク
            </button>
        </div>
        
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="row w-100 g-0 align-items-center fw-bold text-primary bg-light">
                <div class="col-md-3 py-2 px-2">タスク</div>
                <div class="col-md-1 py-2 pe-2">優先度</div>
                <div class="col-md-2 py-2 pe-2">期間</div>
                <div class="col-md-2 py-2 pe-2">担当者</div>
                <div class="col-md-1 py-2 pe-2">ステータス</div>
                <div class="col-md-1 py-2 pe-2">進捗</div>
                <div class="col-md-1 py-2">操作</div>
            </div>
        </div>
        <!-- Danh sách task dạng div card/list -->
        <div class="task-list">
            <div v-if="displayTasks.length === 0" class="text-center text-muted py-4">
                <i class="bi bi-inbox fs-1 mb-2"></i>
                <div class="card mb-2 p-2">タスクがありません</div>
            </div>
            <div v-for="task in displayTasks" :key="task.id || 'inline-' + task._inlineIndex" class="card mb-2" :data-id="task.id" :class="{'subtask': task.indent_level > 0}" :style="{marginLeft: (task.indent_level * 20) + 'px'}">
                <!-- Inline Edit Mode -->
                <div v-if="task._isInlineEdit" class="row g-0 align-items-center">
                    <div class="col-md-3">
                        <div class="p-2">
                            <input type="text" class="form-control inline-task-input" :value="task.title" placeholder="タスク名" required @input="updateTaskField(task._inlineIndex, 'title', $event.target.value)">
                        </div>
                    </div>
                    <div class="col-md-1">
                        <div class="py-2 pe-2">
                            <div class="btn-group w-100">
                                <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light w-100"
                                        :class="getPriorityButtonClass(task.priority)"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    {{ getPriorityLabel(task.priority) }}
                                </button>
                                <ul class="dropdown-menu">
                                    <li v-for="priority in taskPriorities" :key="priority.value">
                                        <a class="dropdown-item waves-effect" href="#" @click="updateInlineTaskPriority(task._inlineIndex, priority.value)">
                                            {{ priority.label }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="pe-2 d-flex align-items-center gap-2">
                            <input type="text" class="form-control px-1 py-0 datetimepicker" :value="task.start_date" placeholder="開始日" @input="updateTaskField(task._inlineIndex, 'start_date', $event.target.value)">
                            <input type="text" class="form-control px-1 py-0 datetimepicker" :value="task.due_date" placeholder="期限日" @input="updateTaskField(task._inlineIndex, 'due_date', $event.target.value)">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="d-flex align-items-center flex-wrap" @click="openAssigneeModal(task._inlineIndex)">
                            <template v-if="task.assignees && task.assignees.length">
                                <template v-for="(userId, i) in task.assignees.slice(0, 5)">
                                    <div :key="userId" class="avatar me-1">
                                        <img v-if="!projectMembers.find(m => m.user_id == userId)?.avatarError && getAvatarSrc(projectMembers.find(m => m.user_id == userId))" class="rounded-circle" :src="getAvatarSrc(projectMembers.find(m => m.user_id == userId))" :alt="projectMembers.find(m => m.user_id == userId)?.user_name" @error="handleAvatarError(projectMembers.find(m => m.user_id == userId))" width="28" height="28">
                                        <span v-else class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(projectMembers.find(m => m.user_id == userId)?.user_name) }}</span>
                                    </div>
                                </template>
                                <div v-if="task.assignees.length > 5" class="avatar">
                                    <span class="avatar-initial rounded-circle pull-up" data-bs-toggle="tooltip" data-bs-placement="bottom" :data-bs-original-title="assigneeNames(task.assignees.slice(5))">
                                        +{{ task.assignees.length - 5 }}
                                    </span>
                                </div>
                                <div class="avatar">
                                    <span class="avatar-initial rounded-circle pull-up bg-label-success" data-bs-toggle="tooltip" data-bs-placement="bottom">
                                        <i class="fa fa-plus"></i>
                                    </span>
                                </div>
                            </template>
                            <div v-else class="avatar">
                                <span class="avatar-initial rounded-circle pull-up bg-label-success">
                                    <i class="fa fa-plus"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-1">
                        <div class="py-2 pe-2">
                            <div class="btn-group w-100">
                                <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light w-100"
                                        :class="getStatusButtonClass(task.status)"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    {{ getStatusLabel(task.status) }}
                                </button>
                                <ul class="dropdown-menu">
                                    <li v-for="status in taskStatuses" :key="status.value" class="dropdown-item" style="cursor:pointer" @click="updateInlineTaskStatus(task._inlineIndex, status.value)">
                                        {{ status.label }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <div class="py-2 pe-2 d-flex align-items-center">
                            <input type="range" min="0" max="100" step="1" :value="task.progress" class="w-100" @input="updateTaskField(task._inlineIndex, 'progress', parseInt($event.target.value))">
                            <span class="ms-2">{{ task.progress || 0 }}%</span>
                        </div>
                    </div>
                    <div class="col-md-1 d-flex align-items-center justify-content-center gap-2">
                        <button class="btn btn-sm btn-success me-1" @click="saveTaskInline(task._inlineIndex)"><i class="fas fa-check"></i></button>
                        <button class="btn btn-sm btn-secondary" @click="cancelTaskInline(task._inlineIndex)"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                
                <!-- Normal Display Mode -->
                <div v-else class="row g-0 align-items-center">
                    <div class="col-md-3 d-flex align-items-center">
                        <span class="drag-handle ps-2 pe-2 fs-16" style="cursor: move;">≡</span>
                        <span class="badge badge-sm bg-label-primary me-2">#{{ task.id }}</span>
                        <div class="d-flex align-items-center justify-content-between gap-2 flex-grow-1 task-title">
                            <span class="fw-bold" @click="openTaskDetails(task)" style="cursor: pointer; max-width: 100%;overflow: hidden;text-overflow: ellipsis;white-space: nowrap;">{{ task.title }}</span>
                            <i class="fa fa-expand-alt" style="cursor: pointer;" @click="openTaskDetails(task)"></i>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <div class="py-2 pe-2">
                            <div class="btn-group w-100">
                                <button type="button" class="btn btn-sm waves-effect waves-light w-100"
                                        :class="getPriorityButtonClass(task.priority)">
                                    {{ getPriorityLabel(task.priority) }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="d-flex align-items-center gap-2">
                            <span>{{ formatDate(task.start_date) }}</span> ~
                            <span>{{ formatDate(task.due_date) }}</span>
                        </div>
                        <i v-if="isTaskOverdue(task)" class="fas fa-exclamation-triangle text-warning ms-1" 
                           data-bs-toggle="tooltip" data-bs-placement="top" 
                           :title="getOverdueTooltip(task)"></i>
                    </div>
                    <div class="col-md-2">
                        <div class="d-flex align-items-center flex-wrap">
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
                            <span v-else class="text-muted">未選択</span>
                        </div>
                    </div>
                   
                    <div class="col-md-1">
                        <div class="py-2 pe-2">
                            <div class="btn-group w-100">
                                <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light w-100"
                                        :class="getStatusButtonClass(task.status)"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    {{ getStatusLabel(task.status) }}
                                </button>
                                <ul class="dropdown-menu">
                                    <li v-for="status in taskStatuses" :key="status.value" class="dropdown-item" style="cursor:pointer" @click="updateTaskStatus(task, status.value); $nextTick(() => closeAllDropdowns())">
                                        {{ status.label }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <div class="py-2 pe-2 d-flex align-items-center">
                            <input type="range" min="0" max="100" step="1" v-model.number="task.progress" class="w-100" @change="updateTaskProgress(task)">
                            <span class="ms-2">{{ task.progress || 0 }}%</span>
                        </div>
                    </div>
                    <div class="col-md-1 d-flex align-items-center justify-content-center gap-2">
                        <button class="btn btn-sm btn-outline-primary me-1" @click="editTaskInline(task)"><i class="fas fa-edit"></i></button>
                        <button v-if="!isFirstTask(task)" class="btn btn-sm btn-outline-secondary me-1" @click="increaseIndent(task)" title="サブタスクにする">
                            <i class="fas fa-arrow-right"></i>
                        </button>
                        <button v-if="task.indent_level > 0" class="btn btn-sm btn-outline-secondary me-1" @click="decreaseIndent(task)" title="サブタスクを解除">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" @click="deleteTask(task)"><i class="fas fa-trash"></i></button>
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

    <!-- Task Details Modal -->
    <div class="modal fade" id="taskDetailsModal" tabindex="-1" aria-labelledby="taskDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen" style="max-width: 70%;max-height: 90%; margin: 10% auto; 0">
            <div class="modal-content h-100">
                <div class="modal-header">
                    <h5 class="modal-title" id="taskDetailsModalLabel">
                        <span v-if="selectedTask"><span class="badge badge-sm bg-label-primary">#{{ selectedTask.id }}</span> {{ selectedTask.title }}</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body d-flex flex-column h-100 p-0">
                    <div v-if="selectedTask" class="d-flex flex-column h-100">
                    <!-- Tabs Navigation at Bottom -->
                        <div class="nav-align-top nav-tabs-shadow">
                            <ul class="nav nav-tabs nav-fill" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button type="button" class="nav-link waves-effect active" role="tab" data-bs-toggle="tab" data-bs-target="#content" aria-controls="content" aria-selected="true">
                                        <span class="d-none d-sm-inline-flex align-items-center">
                                            <i class="fas fa-file-alt me-1_5"></i>タスク内容
                                        </span>
                                        <i class="fas fa-file-alt d-sm-none"></i>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button type="button" class="nav-link waves-effect" role="tab" data-bs-toggle="tab" data-bs-target="#comments" aria-controls="comments" aria-selected="false" tabindex="-1">
                                        <span class="d-none d-sm-inline-flex align-items-center">
                                            <i class="fas fa-comments me-1_5"></i>コメント
                                        </span>
                                        <i class="fas fa-comments d-sm-none"></i>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button type="button" class="nav-link waves-effect" role="tab" data-bs-toggle="tab" data-bs-target="#history" aria-controls="history" aria-selected="false" tabindex="-1">
                                        <span class="d-none d-sm-inline-flex align-items-center">
                                            <i class="fas fa-history me-1_5"></i>活動履歴
                                            <span v-if="taskLogs.length > 0" class="badge rounded-pill badge-center h-px-20 w-px-20 bg-label-info ms-1_5">{{ taskLogs.length }}</span>
                                        </span>
                                        <i class="fas fa-history d-sm-none"></i>
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <!-- Tab Content -->
                        <div class="tab-content flex-grow-1 p-3">
                            <!-- Tab 1: Task Content -->
                            <div class="tab-pane fade show active" id="content" role="tabpanel">
                                <div class="row h-100">
                                    <div class="col-md-12">
                                        <!-- Task Description Editor -->
                                        <div class="card h-100">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">タスクの説明</h6>
                                                <button class="btn btn-sm btn-primary" @click="saveTaskDescription">
                                                    <i class="fas fa-save me-1"></i>保存
                                                </button>
                                            </div>
                                            <div class="card-body">
                                                <div id="taskDescriptionEditor" style="min-height: 400px;">
                                                    <!-- Quill editor will be initialized here -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tab 2: Comments -->
                            <div class="tab-pane fade" id="comments" role="tabpanel">
                                <div class="h-100">
                                    <!-- Comment Component -->
                                    <comment-component
                                        v-if="selectedTask"
                                        entity-type="task"
                                        :entity-id="selectedTask.id"
                                        :current-user="currentUser"
                                        :api-endpoints="taskCommentApiEndpoints"
                                        :show-load-more="true"
                                        @comment-added="onTaskCommentAdded"
                                        @comment-liked="onTaskCommentLiked"
                                        @error="handleCommentError"
                                        ref="taskCommentComponent"
                                    ></comment-component>
                                </div>
                            </div>

                            <!-- Tab 3: Activity History -->
                            <div class="tab-pane fade" id="history" role="tabpanel">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h6 class="mb-0">活動履歴</h6>
                                    </div>
                                    <div class="card-body">
                                        <div v-if="taskLogs.length === 0" class="text-center text-muted py-4">
                                            <i class="fas fa-history fa-2x mb-2"></i>
                                            <p>活動履歴がありません</p>
                                        </div>
                                        <ul class="list-group">
                                            <li v-for="log in sortedTaskLogs" :key="log.time" class="list-group-item">
                                                <div class="d-flex">
                                                    <div class="d-flex flex-row align-items-start justify-content-start me-3" style="min-width:160px;">
                                                        <div class="d-flex flex-column align-items-center justify-content-start" style="width:40px;">
                                                            <span v-if="log.user_image">
                                                                <img :src="'/assets/upload/avatar/' + log.user_image" alt="avatar" class="rounded-circle" width="32" height="32">
                                                            </span>
                                                            <span v-else>
                                                                <span class="avatar-placeholder rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;">
                                                                    {{ getInitials(log.username || log.realname) }}
                                                                </span>
                                                            </span>
                                                        </div>
                                                        <div class="d-flex flex-column align-items-start justify-content-start ms-2">
                                                            <span class="fw-bold small">{{ log.username || log.realname }}</span>
                                                            <span class="text-muted small">{{ formatDate(log.time) }}</span>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex flex-column align-items-start justify-content-start flex-grow-1">
                                                        <div class="d-flex align-items-center">
                                                            <i :class="getTaskLogIcon(log.action)" class="me-2"></i>
                                                            <span class="fw-bold">{{ log.note }}</span>
                                                        </div>
                                                        <div class="d-flex align-items-center">
                                                            <span v-if="log.value1" :class="getTaskLogBadgeClass(log, 'value1')" class="mx-1">{{ getTaskLogBadgeLabel(log, 'value1') }}</span>
                                                            <span v-if="log.value2" class="mx-1">→</span>
                                                            <span v-if="log.value2" :class="getTaskLogBadgeClass(log, 'value2')" class="mx-1">{{ getTaskLogBadgeLabel(log, 'value2') }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                       
                    </div>
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

/* Indent styling */
.subtask {
    border-left: 3px solid var(--bs-primary);
    background-color: var(--bs-primary-bg-subtle);
}

.task-list .card {
    transition: all 0.2s ease;
}

.task-list .card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}


/* Modal content styles */
#taskDetailsModal .modal-body {
    max-height: 80vh;
    overflow-y: auto;
}

#taskDetailsModal .tab-content {
    height: calc(100vh - 200px);
    overflow-y: auto;
}

/* Comment component in modal */
#taskDetailsModal .comment-component {
    height: 100%;
    display: flex;
    flex-direction: column;
}

#taskDetailsModal .comments-list {
    flex: 1;
    overflow-y: auto;
    max-height: calc(100vh - 400px);
}

#taskDetailsModal .comment-input-section {
    flex-shrink: 0;
}

</style>
<script>
const PROJECT_ID = <?php echo $project_id; ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/quill/typography.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/highlight/highlight.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/quill/editor.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/quill/katex.css" />
<script src="<?=ROOT?>assets/vendor/libs/highlight/highlight.js"></script>
<script src="<?=ROOT?>assets/vendor/libs/quill/katex.js"></script>
<script src="<?=ROOT?>assets/vendor/libs/quill/quill.js"></script>
<script src="<?=ROOT?>assets/js/sw-manager.js"></script>
<link rel="stylesheet" href="<?=ROOT?>assets/css/comment-component.css" />
<script src="<?=ROOT?>assets/js/comment-component.js"></script>
<script src="<?=ROOT?>assets/js/mention.js"></script>
<script src="assets/js/task-manager.js"></script>
<script>
// Reset Quill editor when modal is closed
document.addEventListener('DOMContentLoaded', function() {
    const taskDetailsModal = document.getElementById('taskDetailsModal');
    if (taskDetailsModal) {
        taskDetailsModal.addEventListener('hidden.bs.modal', function() {
            if (window.TaskApp && window.TaskApp.resetQuillEditor) {
                window.TaskApp.resetQuillEditor();
            }
        });
    }
});
</script>

<?php
$view->footing();
?>



