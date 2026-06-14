<?php
require_once('../application/loader.php');
$view->heading('タスク管理');

// Get project ID from URL
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
if (!$project_id) {
    header('Location: index.php');
    exit;
}
if($_SESSION['show_project'] == 0){
    echo '<div class="container-fluid mt-4"><div class="alert alert-danger">権限がありません。</div></div>';
    exit;
}
?>
<script>window.__chatPageContext = { project_id: <?php echo (int)$project_id; ?> };</script>
<div id="app" class="container-fluid mt-4" v-cloak>
    <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="#"><span class="badge badge-sm bg-primary">#{{ projectInfo?.id }}</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#projectNavbar" aria-controls="projectNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="projectNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                <a class="nav-link" href="detail.php?id=<?php echo $project_id; ?>"><span data-i18n="概要">概要</span></a>
                </li>
                <li class="nav-item">
                <a class="nav-link active text-primary" aria-current="page" href="task.php?project_id=<?php echo $project_id; ?>"><span data-i18n="タスク">タスク</span><span class="badge badge-sm ms-1 rounded-pill">{{ projectInfo?.task_count }}</span></a>
                </li>
                <li class="nav-item">
                <a class="nav-link" href="gantt.php?project_id=<?php echo $project_id; ?>"><span data-i18n="ガントチャート">ガントチャート</span></a>
                </li>
                <li class="nav-item">
                <a class="nav-link" href="drawings.php?project_id=<?php echo $project_id; ?>"><span data-i18n="図面">図面</span><span class="badge badge-sm bg-info ms-1 rounded-pill">{{ projectInfo?.drawing_count }}</span></a>
                </li>
                <li class="nav-item">
                <a class="nav-link" href="attachment.php?project_id=<?php echo $project_id; ?>"><span data-i18n="添付ファイル">添付ファイル</span></a>
                </li>
            </ul>
            </div>
        </div>
    </nav>
    <?php $statusBannerVar = 'projectInfo'; require __DIR__ . '/partials/project-status-banner.php'; ?>

    <div class="row">
        <!-- Back button -->
        <div class="col-12">
            <a href="detail.php?id=<?php echo $project_id; ?>" class="btn btn-outline-primary">
                <i class="fa fa-arrow-left me-2"></i><span data-i18n="案件概要へ戻る">案件概要へ戻る</span>
            </a>
            <a v-if="projectInfo && projectInfo.parent_project_id" :href="'../parent_project/detail.php?id=' + projectInfo.parent_project_id" class="btn btn-outline-primary ms-2">
                <i class="fa fa-external-link me-2"></i>
                <span>{{ $t('建物詳細') }}</span>
            </a>
        </div>
    </div>

    <div v-if="canViewTaskList">
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
            <div class="col-md-6 col-lg-3 mb-3">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col me-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    <span data-i18n="タスク総数">タスク総数</span>
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
            <div class="col-md-6 col-lg-3 mb-3">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col me-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    <span data-i18n="工数合計">工数合計</span>
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ formatTotalWorkload(taskStats.totalWorkload) }}</div>
                            </div>
                            <div class="col-md-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3 mb-3">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col me-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    <span data-i18n="完了済み">完了済み</span>
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
            <div class="col-md-6 col-lg-3 mb-3">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col me-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    <span data-i18n="期限切れ">期限切れ</span>
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
                <option value="">{{ $t('全てのステータス') }}</option>
                <option v-for="status in taskStatuses" :key="status.value" :value="status.value">{{ $t(status.i18nKey || status.label) }}</option>
            </select>
            <select class="form-select" v-model="filterPriority">
                <option value="">{{ $t('全ての優先度') }}</option>
                <option v-for="priority in taskPriorities" :key="priority.value" :value="priority.value">{{ $t(priority.i18nKey || priority.label) }}</option>
            </select>
            <div class="form-check mb-0 form-switch">
                <input class="form-check-input" type="checkbox" id="filterMyTasksOnly" v-model="filterMyTasksOnly">
                <label class="form-check-label text-nowrap" for="filterMyTasksOnly" data-i18n="自分のタスクのみ">自分のタスクのみ</label>
            </div>
            </div>
            <button v-if="permission.can_manage_project || permission.is_member || (permission.rule && permission.rule.task_add == 1)" class="btn btn-primary ms-2" @click="openNewTaskModal">
                <i class="fa fa-plus me-1"></i> <span data-i18n="新規タスク">新規タスク</span>
            </button>
        </div>
        
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="task-table-header w-100 g-0 align-items-center fw-bold text-primary bg-light">
                <div class="task-col-title py-2 px-2"><span data-i18n="タスク">タスク</span></div>
                <div class="task-col-kind py-2 pe-2"><span data-i18n="種別">種別</span></div>
                <div class="task-col-drawing py-2 pe-2"><span data-i18n="図面">図面</span></div>
                <div class="task-col-priority py-2 pe-2"><span data-i18n="優先度">優先度</span></div>
                <div class="task-col-period py-2 pe-2"><span data-i18n="期限">期限</span></div>
                <div class="task-col-assignee py-2 pe-2"><span data-i18n="担当者">担当者</span></div>
                <div class="task-col-ack py-2 pe-2"></div>
                <div class="task-col-creator py-2 pe-2"><span data-i18n="作成者">作成者</span></div>
                <div class="task-col-status py-2 pe-2"><span data-i18n="ステータス">ステータス</span></div>
                <div class="task-col-progress py-2 pe-2"><span data-i18n="進捗">進捗</span></div>
                <div class="task-col-workload py-2 pe-2"><span data-i18n="工数">工数</span></div>
                <div class="task-col-note py-2 pe-2"><span data-i18n="メモ">メモ</span></div>
                <div class="task-col-actions py-2"><span data-i18n="操作">操作</span></div>
            </div>
        </div>
        <!-- Danh sách task dạng div card/list -->
        <div class="task-list">
            <div v-if="displayTasks.length === 0" class="text-center text-muted py-4">
                <i class="bi bi-inbox fs-1 mb-2"></i>
                <div class="card mb-2 p-2"><span data-i18n="タスクがありません">タスクがありません</span></div>
            </div>
            <div v-for="task in displayTasks" :key="task.id || 'inline-' + task._inlineIndex" class="card mb-2" :data-id="task.id" :class="{'subtask': task.indent_level > 0}" :style="{marginLeft: (task.indent_level * 20) + 'px'}">
                <!-- Inline Edit Mode -->
                <div v-if="task._isInlineEdit" class="row g-0 align-items-center">
                    <div class="task-col-title">
                        <div class="p-2">
                            <input type="text" class="form-control inline-task-input" :value="task.title" placeholder="タスク名" required @input="updateTaskField(task._inlineIndex, 'title', $event.target.value)">
                        </div>
                    </div>
                    <div class="task-col-kind">
                        <div class="py-2 pe-2">
                            <select class="form-select form-select-sm" :value="normalizeTaskKind(task.task_kind || getDefaultTaskKind())" @change="updateTaskField(task._inlineIndex, 'task_kind', $event.target.value)">
                                <option v-for="kind in taskKinds" :key="kind.value" :value="kind.value">{{ $t(kind.i18nKey || kind.label) }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="task-col-drawing">
                        <div class="py-2 pe-2 d-flex align-items-center gap-1 flex-wrap">
                            <template v-if="isDrawingLinkVisibleForTask(task)">
                                <div class="form-check mb-0" :title="$t('図面リストに追加')">
                                    <input type="checkbox" class="form-check-input" :id="'inline-drawing-link-' + task._inlineIndex"
                                        :checked="isTaskLinkedToDrawings(task)"
                                        :disabled="!canEditDrawingLink()"
                                        @change="onInlineDrawingLinkChange(task._inlineIndex, $event.target.checked)">
                                </div>
                                <input v-if="isTaskLinkedToDrawings(task)" type="number" class="form-control form-control-sm task-drawing-count-input" min="1" step="1"
                                    :value="task.drawing_count > 0 ? task.drawing_count : 1"
                                    :disabled="!canEditDrawingLink()"
                                    @input="updateTaskField(task._inlineIndex, 'drawing_count', $event.target.value)">
                            </template>
                            <span v-else-if="!isDefaultTaskWithAutoDrawingLink(task)" class="text-muted small">—</span>
                        </div>
                    </div>
                    <div class="task-col-priority">
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
                                            {{ $t(priority.i18nKey || priority.label) }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="task-col-period">
                        <div class="pe-2 d-flex align-items-center">
                            <input type="text" class="form-control px-1 py-0 datetimepicker" :value="task.due_date" placeholder="期限日" @input="updateTaskField(task._inlineIndex, 'due_date', $event.target.value)">
                        </div>
                    </div>
                    <div class="task-col-assignee">
                        <div class="d-flex align-items-center flex-wrap" style="cursor: pointer;" @click="openAssigneeModal(task._inlineIndex)">
                            <template v-if="getPrimaryAssigneeId(task)">
                                <div class="avatar me-1" data-bs-toggle="tooltip" :title="(projectMembers.find(m => m.user_id == getPrimaryAssigneeId(task))?.user_name || task.assigned_to_name || getPrimaryAssigneeId(task))">
                                    <img v-if="projectMembers.find(m => m.user_id == getPrimaryAssigneeId(task)) && !projectMembers.find(m => m.user_id == getPrimaryAssigneeId(task)).avatarError && getAvatarSrc(projectMembers.find(m => m.user_id == getPrimaryAssigneeId(task)))" class="rounded-circle" :src="getAvatarSrc(projectMembers.find(m => m.user_id == getPrimaryAssigneeId(task)))" :alt="projectMembers.find(m => m.user_id == getPrimaryAssigneeId(task))?.user_name || task.assigned_to_name" @error="handleAvatarError(projectMembers.find(m => m.user_id == getPrimaryAssigneeId(task)))" width="28" height="28">
                                    <span v-else class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(projectMembers.find(m => m.user_id == getPrimaryAssigneeId(task))?.user_name || task.assigned_to_name || '') }}</span>
                                </div>
                            </template>
                            <span v-else class="text-muted small">{{ $t('未選択') }}</span>
                        </div>
                    </div>
                    <div class="task-col-ack"></div>
                    <div class="task-col-creator">
                        <div class="d-flex align-items-center flex-wrap py-2 pe-2">
                            <template v-if="getCreatorMemberForInlineTask(task)">
                                <div class="avatar me-1" data-bs-toggle="tooltip" :title="getCreatorTooltip(getCreatorMemberForInlineTask(task))">
                                    <img v-if="shouldShowCreatorAvatar(getCreatorMemberForInlineTask(task))" class="rounded-circle" :src="getAvatarSrc(getCreatorMemberForInlineTask(task))" :alt="getCreatorTooltip(getCreatorMemberForInlineTask(task))" @error="handleAvatarError(getCreatorMemberForInlineTask(task))" width="28" height="28">
                                    <span v-else class="avatar-initial rounded-circle bg-label-primary" :title="getCreatorTooltip(getCreatorMemberForInlineTask(task))">{{ getInitials(getCreatorMemberForInlineTask(task).user_name) }}</span>
                                </div>
                            </template>
                            <span v-else class="text-muted small">—</span>
                        </div>
                    </div>
                    <div class="task-col-status">
                        <div class="py-2 pe-2">
                            <div class="btn-group w-100">
                                <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light w-100"
                                        :class="getStatusButtonClass(task.status)"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    {{ getStatusLabel(task.status) }}
                                </button>
                                <ul class="dropdown-menu">
                                    <li v-for="status in taskStatuses" :key="status.value" class="dropdown-item" style="cursor:pointer" @click="updateInlineTaskStatus(task._inlineIndex, status.value)">
                                        {{ $t(status.i18nKey || status.label) }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="task-col-progress">
                        <div class="py-2 pe-2 d-flex align-items-center">
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                        data-bs-toggle="dropdown" 
                                        aria-expanded="false"
                                        :id="'progressDropdownInline' + task._inlineIndex">
                                    {{ task.progress || 0 }}%
                                </button>
                                <ul class="dropdown-menu progress-dropdown" :aria-labelledby="'progressDropdownInline' + task._inlineIndex">
                                    <li v-for="percent in progressOptions" :key="percent">
                                        <a class="dropdown-item" href="#" @click.prevent="setInlineTaskProgress(task._inlineIndex, percent)">
                                            {{ percent }}%
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="task-col-workload">
                        <div class="py-2 pe-2"></div>
                    </div>
                    <div class="task-col-note">
                        <div v-if="task.id" class="py-2 pe-2 task-note-cell" @click="openTaskNoteModal(task)" :title="getTaskNoteSnippet(task.note) || $t('メモ')">
                            <span v-if="getTaskNoteSnippet(task.note)" class="small text-truncate d-block">{{ getTaskNoteSnippet(task.note) }}</span>
                            <i v-else class="fa fa-sticky-note text-muted"></i>
                        </div>
                        <div v-else class="py-2 pe-2"></div>
                    </div>
                    <div class="task-col-actions">
                        <div class="d-flex align-items-center gap-1 pe-2">
                            <button class="btn btn-sm btn-success me-1" @click="saveTaskInline(task._inlineIndex)"><i class="fas fa-check"></i></button>
                            <button class="btn btn-sm btn-secondary" @click="cancelTaskInline(task._inlineIndex)"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                </div>
                
                <!-- Normal Display Mode -->
                <div v-else class="row g-0 align-items-center task-row position-relative">
                    <div class="task-col-title d-flex align-items-center">
                        <span class="drag-handle ps-2 pe-2 fs-16" style="cursor: move;" :class="{'prevent-click': !(permission.can_manage_project || (permission.rule && permission.rule.task_edit == 1 && checkAssignee(task)))}">≡</span>
                        <span class="badge badge-sm bg-label-primary me-2">#{{ task.id }}</span>
                        <div class="d-flex align-items-center justify-content-between gap-2 flex-grow-1 task-title">
                            <span class="fw-bold" @click="openTaskDetails(task)" style="cursor: pointer; max-width: 100%;overflow: hidden;text-overflow: ellipsis; white-space: nowrap;">{{ task.title }}</span>
                            <i class="fa fa-expand-alt" style="cursor: pointer;" @click="openTaskDetails(task)"></i>
                        </div>
                    </div>
                    <div class="task-col-kind">
                        <div class="py-2 pe-2">
                            <span class="badge small" :class="getTaskKindBadgeClass(getTaskKindDisplayValue(task))">{{ getTaskKindLabel(getTaskKindDisplayValue(task)) }}</span>
                        </div>
                    </div>
                    <div class="task-col-drawing">
                        <div class="py-2 pe-2 d-flex align-items-center gap-1 flex-wrap small">
                            <template v-if="isDrawingLinkVisibleForTask(task)">
                                <div class="form-check mb-0" :title="$t('図面リストに追加')">
                                    <input type="checkbox" class="form-check-input" :id="'drawing-link-' + task.id"
                                        :checked="isTaskLinkedToDrawings(task)"
                                        :disabled="!canEditDrawingLink()"
                                        @change="toggleTaskDrawingLink(task, $event)">
                                </div>
                                <input v-if="isTaskLinkedToDrawings(task) && canEditDrawingLink()"
                                    type="number"
                                    class="form-control form-control-sm task-drawing-count-input"
                                    :class="{ 'task-drawing-count-input--loading': isDrawingCountSaving(task.id) }"
                                    min="1"
                                    step="1"
                                    :value="task.drawing_count > 0 ? task.drawing_count : 1"
                                    :disabled="isDrawingCountSaving(task.id)"
                                    @change="saveTaskDrawingCount(task, $event.target.value)">
                                <span v-else-if="isTaskLinkedToDrawings(task)" class="text-nowrap">{{ task.drawing_count }}</span>
                            </template>
                            <span v-else-if="!isDefaultTaskWithAutoDrawingLink(task)" class="text-muted">—</span>
                        </div>
                    </div>
                    <div class="task-col-priority">
                        <div class="py-2 pe-2">
                            <div class="btn-group w-100">
                                <button type="button" class="btn btn-sm waves-effect waves-light w-100"
                                        :class="getPriorityButtonClass(task.priority)">
                                    {{ getPriorityLabel(task.priority) }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="task-col-period">
                        <div class="d-flex align-items-center gap-1 small">
                            <span class="text-nowrap" :class="{ 'text-danger fw-bold': isTaskDueExceedsProjectDue(task) }">{{ formatTaskDueDate(task.due_date) }}</span>
                            <i v-if="hasPeriodWarning(task)" class="fas fa-exclamation-triangle text-warning ms-1" 
                               data-bs-toggle="tooltip" data-bs-placement="top" 
                               :title="getPeriodWarningTooltip(task)"></i>
                        </div>
                    </div>
                    <div class="task-col-assignee">
                        <div class="d-flex align-items-center flex-wrap">
                            <template v-if="getPrimaryAssigneeId(task)">
                                <div class="avatar me-1 position-relative" :data-userid="projectMembers.find(m => m.user_id == getPrimaryAssigneeId(task))?.userid || task.assigned_to_userid" data-bs-toggle="tooltip" :title="getAssigneeTooltip(task, getPrimaryAssigneeId(task))">
                                    <img v-if="projectMembers.find(m => m.user_id == getPrimaryAssigneeId(task)) && !projectMembers.find(m => m.user_id == getPrimaryAssigneeId(task)).avatarError && getAvatarSrc(projectMembers.find(m => m.user_id == getPrimaryAssigneeId(task)))" class="rounded-circle" :src="getAvatarSrc(projectMembers.find(m => m.user_id == getPrimaryAssigneeId(task)))" :alt="projectMembers.find(m => m.user_id == getPrimaryAssigneeId(task))?.user_name || task.assigned_to_name" @error="handleAvatarError(projectMembers.find(m => m.user_id == getPrimaryAssigneeId(task)))" width="28" height="28">
                                    <span v-else class="avatar-initial rounded-circle bg-label-primary" @click="removeAssignee(task, getPrimaryAssigneeId(task))">{{ getInitials(projectMembers.find(m => m.user_id == getPrimaryAssigneeId(task))?.user_name || task.assigned_to_name || '') }}</span>
                                    <span v-if="isAcknowledged(task, getPrimaryAssigneeId(task))" class="badge bg-success position-absolute top-0 start-100 translate-middle" style="font-size: 8px; padding: 2px 4px;">
                                        <i class="fa fa-check"></i>
                                    </span>
                                    <span v-else class="badge bg-secondary position-absolute top-0 start-100 translate-middle" style="font-size: 8px; padding: 2px 4px;">
                                        <i class="fa fa-clock"></i>
                                    </span>
                                </div>
                            </template>
                            <span v-else class="text-muted">{{ $t('未選択') }}</span>
                        </div>
                    </div>
                    <!-- Cột riêng cho nút 受領 -->
                    <div class="task-col-ack">
                        <div class="d-flex align-items-center">
                            <button 
                                v-if="task.assigned_to && isAssignedToMe(task) && !isAcknowledged(task, currentUserId)" 
                                class="btn btn-sm btn-success" 
                                @click="acknowledgeTask(task)"
                                title="このタスクを受領済みにする">
                                <i class="fa fa-check me-1"></i> 受領
                            </button>
                        </div>
                    </div>
                    <!-- Cột người tạo (avatar hoặc fallback text/initials + tooltip tên) -->
                    <div class="task-col-creator">
                        <div class="d-flex align-items-center flex-wrap py-2 pe-2">
                            <template v-if="getCreatorMember(task)">
                                <div class="avatar me-1" :data-userid="getCreatorMember(task).userid" data-bs-toggle="tooltip" :title="getCreatorTooltip(getCreatorMember(task))">
                                    <img v-if="shouldShowCreatorAvatar(getCreatorMember(task))" class="rounded-circle" :src="getAvatarSrc(getCreatorMember(task))" :alt="getCreatorTooltip(getCreatorMember(task))" @error="handleAvatarError(getCreatorMember(task))" width="28" height="28">
                                    <span v-else class="avatar-initial rounded-circle bg-label-primary" :title="getCreatorTooltip(getCreatorMember(task))">{{ getInitials(getCreatorMember(task).user_name) }}</span>
                                </div>
                            </template>
                            <span v-else class="text-muted small">—</span>
                        </div>
                    </div>

                    <!-- Cột hiển thị ステータス (giữ thông tin như cũ) -->
                    <div class="task-col-status">
                        <div class="py-2 pe-2">
                            <div class="btn-group w-100" :class="{'prevent-click': !(permission.can_manage_project || (permission.rule && permission.rule.task_edit == 1 && checkAssignee(task)))}">
                                <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light w-100"
                                        :class="getStatusButtonClass(task.status)"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    {{ getStatusLabel(task.status) }}
                                </button>
                                <ul class="dropdown-menu">
                                    <li v-for="status in taskStatuses" :key="status.value" class="dropdown-item" style="cursor:pointer" @click="updateTaskStatus(task, status.value); $nextTick(() => closeAllDropdowns())">
                                        {{ $t(status.i18nKey || status.label) }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Cột hiển thị 進捗 (giữ thông tin như cũ) -->
                    <div class="task-col-progress">
                        <div class="py-2 pe-2 d-flex align-items-center" :class="{'prevent-click': !(permission.can_manage_project || (permission.rule && permission.rule.task_edit == 1 && checkAssignee(task)))}">
                            <div class="btn-group" v-if="permission.can_manage_project || (permission.rule && permission.rule.task_edit == 1 && checkAssignee(task))">
                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                        data-bs-toggle="dropdown" 
                                        aria-expanded="false"
                                        :id="'progressDropdown' + task.id">
                                    {{ task.progress || 0 }}%
                                </button>
                                <ul class="dropdown-menu progress-dropdown" :aria-labelledby="'progressDropdown' + task.id">
                                    <li v-for="percent in progressOptions" :key="percent">
                                        <a class="dropdown-item" href="#" @click.prevent="setTaskProgress(task, percent)">
                                            {{ percent }}%
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <span v-else>{{ task.progress || 0 }}%</span>
                        </div>
                    </div>
                    <div class="task-col-workload">
                        <div class="py-2 pe-2 small task-workload-cell">
                            <template v-if="canEditTaskWorkload(task)">
                                <span
                                    class="task-workload-input-shell"
                                    :class="{ 'task-workload-input-shell--timer-active': hasActiveTaskTimer(task) }">
                                    <input
                                        type="number"
                                        class="form-control form-control-sm task-workload-input"
                                        min="0"
                                        step="0.1"
                                        :value="task.estimated_hours != null && task.estimated_hours !== '' ? task.estimated_hours : ''"
                                        placeholder="0"
                                        :class="{ 'task-workload-input--loading': isEstimatedHoursSaving(task.id) }"
                                        :disabled="isEstimatedHoursSaving(task.id)"
                                        @change="saveTaskEstimatedHours(task, $event.target.value)">
                                </span>
                                <button
                                    v-if="canTrackTaskTime(task)"
                                    type="button"
                                    class="btn btn-sm task-timer-btn"
                                    :class="isTaskTimerActive(task.id) ? 'btn-danger task-timer-btn--active' : 'btn-outline-success'"
                                    :title="isTaskTimerActive(task.id) ? $t('作業時間を終了') : $t('作業時間を開始')"
                                    :disabled="isTaskTimerToggling(task.id)"
                                    @click="toggleTaskTimer(task)">
                                    <i :class="isTaskTimerActive(task.id) ? 'fa fa-stop' : 'fa fa-play'"></i>
                                </button>
                            </template>
                            <span
                                v-else
                                class="task-workload-input-shell text-nowrap"
                                :class="{ 'task-workload-input-shell--timer-active': hasActiveTaskTimer(task) }">
                                <span class="task-workload-display">{{ formatEstimatedHours(task.estimated_hours) }}</span>
                            </span>
                        </div>
                    </div>
                    <div class="task-col-note">
                        <div class="py-2 pe-2 task-note-cell" @click="openTaskNoteModal(task)" :title="getTaskNoteSnippet(task.note) || $t('メモ')">
                            <span v-if="getTaskNoteSnippet(task.note)" class="small text-truncate d-block">{{ getTaskNoteSnippet(task.note) }}</span>
                            <i v-else class="fa fa-sticky-note text-muted"></i>
                        </div>
                    </div>

                    <div class="task-col-actions">
                        <div class="d-flex align-items-center gap-1 pe-2">
                            <!-- Like / Dislike -->
                            <button v-if="canReactToTask(task)"
                                class="position-relative btn btn-sm btn-outline-secondary px-2"
                                :class="getTaskReactionButtonClass(task, 'like')"
                                @click="openReactionModal(task, 'like')"
                                :title="getTaskReactionTooltip(task, 'like')">
                                <i class="fas fa-thumbs-up"></i>
                                <span v-if="getTaskReactionCount(task, 'like') > 0" class="ms-1 small" style="line-height: 0;">
                                    {{ getTaskReactionCount(task, 'like') }}
                                </span>
                            </button>
                            <button v-if="canReactToTask(task)"
                                class="position-relative btn btn-sm btn-outline-secondary px-2"
                                :class="getTaskReactionButtonClass(task, 'dislike')"
                                @click="openReactionModal(task, 'dislike')"
                                :title="getTaskReactionTooltip(task, 'dislike')">
                                <i class="fas fa-thumbs-down"></i>
                                <span v-if="getTaskReactionCount(task, 'dislike') > 0" class="ms-1 small" style="line-height: 0;">
                                    {{ getTaskReactionCount(task, 'dislike') }}
                                </span>
                            </button>

                            <!-- Xem chi tiết / comment -->
                            <button v-if="canViewTaskList" class="btn btn-sm btn-outline-info position-relative px-2" @click="openTaskComments(task)" title="詳細">
                                <i class="fas fa-eye"></i>
                                <span v-if="getUnreadCommentCount(task.id) > 0" class="position-absolute top-0 start-100 translate-middle text-white badge rounded-pill bg-danger" style="font-size:10px;">{{ getUnreadCommentCount(task.id) }}</span>
                            </button>

                            <!-- Sửa / indent / xóa -->
                            <button v-if="permission.can_manage_project || (permission.rule && permission.rule.task_edit == 1 && checkAssignee(task))" class="btn btn-sm btn-outline-primary" @click="editTaskInline(task)"><i class="fas fa-edit"></i></button>
                            
                            <button v-if="task.indent_level > 0 && (permission.can_manage_project || (permission.rule && permission.rule.task_edit == 1 && checkAssignee(task)))" class="btn btn-sm btn-outline-secondary" @click="decreaseIndent(task)" title="サブタスクを解除">
                                <i class="fas fa-arrow-left"></i>
                            </button>
                            <button v-if="canIncreaseIndent(task) && (permission.can_manage_project || (permission.rule && permission.rule.task_edit == 1 && checkAssignee(task)))" class="btn btn-sm btn-outline-secondary" @click="increaseIndent(task)" title="サブタスクにする">
                                <i class="fas fa-arrow-right"></i>
                            </button>
                            
                            <button v-if="permission.can_manage_project || (permission.rule && permission.rule.task_delete == 1 && isTaskCreatedByMe(task))" class="btn btn-sm btn-outline-danger" @click="deleteTask(task)"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
    <div class="col-12" v-else>
        <div class="text-center py-5">
            <div class="text-muted">
                <i class="fa fa-lock fa-3x mb-2"></i>
                <p>権限がありません</p>
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
                            <input type="radio" class="form-check-input mt-1" name="task_assignee_radio" :checked="assigneeModal.selected.includes(member.user_id)" @change="toggleAssignee(member.user_id)">
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

    <!-- Task Reaction Modal -->
    <div class="modal fade" id="taskReactionModal" tabindex="-1" aria-labelledby="taskReactionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="taskReactionModalLabel">
                        <span v-if="reactionModal.type === 'like'" data-i18n="良いの理由">良いの理由</span>
                        <span v-else-if="reactionModal.type === 'dislike'" data-i18n="悪いの理由">悪いの理由</span>
                        <span v-else data-i18n="リアクション">リアクション</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Predefined reasons checkboxes -->
                    <div class="mb-3">
                        <label class="form-label fw-bold" data-i18n="理由を選択（複数選択可）">理由を選択（複数選択可）</label>
                        <div class="row">
                            <div v-for="reason in (reactionModal.type === 'like' ? likeReasons : dislikeReasons)" 
                                 :key="reason.id" 
                                 class="col-md-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           :value="reason.id"
                                           :id="'reason-' + reason.id"
                                           v-model="reactionModal.selectedReasons">
                                    <label class="form-check-label" :for="'reason-' + reason.id">
                                        {{ $t(reason.i18nKey || reason.label) }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Custom note input -->
                    <div class="mb-3">
                        <label class="form-label fw-bold" data-i18n="その他の理由・メモ（任意）">その他の理由・メモ（任意）</label>
                        <textarea class="form-control" 
                                  rows="3" 
                                  v-model="reactionModal.customNote"
                                  :placeholder="$t('追加の理由やメモを入力してください...')"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button v-if="reactionModal.taskId && tasks.find(t => t.id == reactionModal.taskId)?.current_user_reaction === reactionModal.type" 
                            type="button" class="btn btn-danger" @click="removeReaction">
                        <i class="fas fa-trash me-1"></i><span data-i18n="解除">解除</span>
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><span data-i18n="キャンセル">キャンセル</span></button>
                    <button type="button" class="btn btn-primary" @click="submitReaction">
                        <i class="fas fa-save me-1"></i><span data-i18n="保存">保存</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Task Details Modal -->
    <div class="modal fade" id="taskDetailsModal" tabindex="-1" aria-labelledby="taskDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen" style="max-width: 70%;max-height: 98vh; margin: 1% auto; 0">
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
                                <!--<li class="nav-item" role="presentation">
                                    <button type="button" class="nav-link waves-effect" role="tab" data-bs-toggle="tab" data-bs-target="#comments" aria-controls="comments" aria-selected="false" tabindex="-1">
                                        <span class="d-none d-sm-inline-flex align-items-center">
                                            <i class="fas fa-comments me-1_5"></i>コメント
                                            <span v-if="getUnreadCommentCount(selectedTask.id) > 0" class="badge rounded-pill badge-center h-px-20 w-px-20 bg-label-info ms-1_5">{{ getUnreadCommentCount(selectedTask.id) }}</span>
                                        </span>
                                        <i class="fas fa-comments d-sm-none"></i>
                                    </button>
                                </li>-->
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
                                                <button v-if="permission.can_manage_project || (permission.rule && permission.rule.task_edit == 1 && checkAssignee(selectedTask))" class="btn btn-sm btn-primary" @click="saveTaskDescription">
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
                                        :project-id="projectId"
                                        :current-user="currentUser"
                                        :api-endpoints="taskCommentApiEndpoints"
                                        :show-load-more="true"
                                        @comment-added="onTaskCommentAdded"
                                        @comment-liked="onTaskCommentLiked"
                                        @message="onCommentMessage"
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
                                                                <span class="avatar-initial rounded-circle bg-label-primary d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;">
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

    <!-- Task Note Modal (Quill, tham khảo project-list) -->
    <div class="modal fade" tabindex="-1" :class="{show: showTaskNoteModal}" style="display: block;" v-if="showTaskNoteModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><span data-i18n="メモ">メモ</span></h5>
                    <button type="button" class="btn-close" @click="closeTaskNoteModal"></button>
                </div>
                <div class="modal-body">
                    <div v-if="!taskNoteModal.canEdit">
                        <label class="form-label"><span data-i18n="内容">内容</span></label>
                        <div class="form-control ql-editor" style="min-height:120px;max-height:480px;overflow-y:auto;" v-html="taskNoteModal.content || '-'"></div>
                    </div>
                    <form v-else @submit.prevent="saveTaskNote">
                        <div class="mb-0">
                            <label class="form-label"><span data-i18n="内容">内容</span></label>
                            <div class="custom_editor">
                                <div class="custom_editor_content" id="quill_task_note_content"></div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button v-if="taskNoteModal.canEdit && (taskNoteModal.content || quillTaskNoteContent)" type="button" class="btn btn-danger me-auto" @click="clearTaskNote">
                        <i class="fa fa-trash me-1"></i><span data-i18n="削除">削除</span>
                    </button>
                    <button type="button" class="btn btn-secondary" @click="closeTaskNoteModal"><span data-i18n="キャンセル">キャンセル</span></button>
                    <button v-if="taskNoteModal.canEdit" type="button" class="btn btn-primary" @click="saveTaskNote" :disabled="!((quillTaskNoteContent && quillTaskNoteContent.trim()) || (taskNoteModal.content && taskNoteModal.content.trim()))">
                        <i class="fa fa-save me-1"></i><span data-i18n="保存">保存</span>
                    </button>
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

/* Task table columns: custom classes + % width (no col-md-xx) */
.task-table-header,
.task-list .task-row,
.task-list .row.g-0.align-items-center {
    display: flex;
    flex-wrap: nowrap;
    width: 100%;
}
.task-table-header > div,
.task-list .task-row > div,
.task-list .row.g-0.align-items-center > div {
    flex: 0 0 auto;
    box-sizing: border-box;
}
.task-col-title { width: 16%; min-width: 0; }
.task-col-kind { width: 8%; min-width: 5.5rem; }
.task-col-drawing { width: 7%; min-width: 8rem; }
.task-drawing-count-input {
    width: 4rem;
    min-width: 4rem;
    padding: 0.4rem 0.5rem;
    font-size: 0.95rem;
    line-height: 1.35;
}
.task-drawing-count-input--loading {
    animation: task-drawing-count-border-pulse 0.9s ease-in-out infinite;
    pointer-events: none;
}
@keyframes task-drawing-count-border-pulse {
    0%, 100% {
        border-color: var(--bs-primary, #696cff);
        box-shadow: 0 0 0 0 rgba(105, 108, 255, 0.45);
    }
    50% {
        border-color: var(--bs-primary, #696cff);
        box-shadow: 0 0 0 3px rgba(105, 108, 255, 0.25);
    }
}
.task-col-workload { width: 8%; min-width: 4rem; justify-content: flex-start; }
.task-workload-input {
    width: 5rem;
    min-width: 3rem;
    max-width: 6.5rem;
    padding: 0.35rem 0.5rem;
    font-size: 0.875rem;
    text-align: center;
}
.task-workload-input--loading {
    animation: task-drawing-count-border-pulse 0.9s ease-in-out infinite;
    pointer-events: none;
}
.task-col-note { width: 8%; min-width: 4.5rem; }
.task-col-priority { width: 5%; min-width: 3.5rem; }
.task-col-period { width: 8%; min-width: 0; }
.task-col-assignee { width: 5%; min-width: 0; max-width: 6.5rem; }
.task-col-ack { width: 5%; min-width: 3rem; }
.task-col-creator { width: 5%; min-width: 0; }
.task-col-status { width: 8%; min-width: 4rem; }
.task-col-progress { width: 6%; min-width: 3.5rem; }
.task-col-actions { width: 12%; min-width: 0; }

.task-note-cell {
    cursor: pointer;
    max-width: 100%;
    line-height: 1.3;
}
.task-note-cell:hover .fa-sticky-note {
    color: var(--bs-primary) !important;
}

/* Task row & hover actions */
.task-row {
    position: relative;
}
.task-row-actions {
    position: absolute;
    right: 0.5rem;
    bottom: 0.25rem;
    opacity: 0;
    z-index: 10;
    pointer-events: none; /* disabled by default */
    transition: opacity 0.15s ease-in-out;
}
.task-row:hover .task-row-actions {
    opacity: 1;
    pointer-events: auto; /* enable interactions on hover */
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
    max-height: 100vh;
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
    max-height: calc(91vh - 460px);
}

#taskDetailsModal .comment-input-section {
    flex-shrink: 0;
}
.prevent-click .btn::after{
    display: none !important;
}
.subtask .avatar-initial{
    background: #fff!important;
}

/* Dropdown: position absolute relative to btn-group (default Bootstrap behavior) */
.task-list .btn-group {
    position: relative;
}
.task-list .dropdown-menu[data-bs-popper] {
    position: absolute !important;
}

/* Progress dropdown scroll */
.dropdown-menu.progress-dropdown {
    max-height: 200px;
    overflow-y: auto;
}

</style>
<script>
const PROJECT_ID = <?php echo $project_id; ?>;
</script>

<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/quill/typography.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/quill/editor.css" />
<script src="<?=ROOT?>assets/vendor/libs/quill/quill.js"></script>
<script src="<?=ROOT?>assets/js/sw-manager.js"></script>
<link rel="stylesheet" href="<?=ROOT?>assets/css/comment-component.css" />
<script src="<?=ROOT?>assets/js/comment-component.js?v=<?=CACHE_VERSION?>"></script>
<script src="<?=ROOT?>assets/js/mention.js?v=<?=CACHE_VERSION?>"></script>
<?php $taskManagerJsVer = @filemtime(__DIR__ . '/assets/js/task-manager.js') ?: CACHE_VERSION; ?>
<script src="<?=ROOT?>project/assets/js/task-manager.js?v=<?=$taskManagerJsVer?>"></script>

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

    // Đảm bảo tooltip Bootstrap được ẩn khi mouseout để tránh bị kẹt
    document.addEventListener('mouseleave', function (e) {
        const target = e.target;
        if (!target || typeof target.matches !== 'function' || !target.matches('[data-bs-toggle="tooltip"]')) return;
        if (window.bootstrap && bootstrap.Tooltip) {
            const instance = bootstrap.Tooltip.getInstance(target);
            if (instance) {
                instance.hide();
            }
        }
    }, true);
});
</script>

<?php
$view->footing();
?>



