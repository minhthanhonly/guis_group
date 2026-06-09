<?php
require_once('../application/loader.php');
$view->heading('タスク一覧');
if($_SESSION['show_project'] == 0){
    echo '<div class="container-fluid mt-4"><div class="alert alert-danger">権限がありません。</div></div>';
    exit;
}
?>

<div id="app" class="container-fluid mt-4" v-cloak>
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="fa fa-tasks me-2"></i><span data-i18n="全タスク一覧">全タスク一覧</span>
            </h4>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-3">
        <div class="col-md-3 mb-2">
            <label class="form-label"><span data-i18n="部署">部署</span></label>
            <select class="form-select" v-model="filters.department_id" @change="onDepartmentChange">
                <option value="" data-i18n="すべて">すべて</option>
                <option v-for="dep in departments" :key="dep.id" :value="dep.id">{{ dep.name }}</option>
            </select>
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label"><span data-i18n="チーム">チーム</span></label>
            <select class="form-select" v-model="filters.team_id" @change="loadOverview">
                <option value="" data-i18n="すべて">すべて</option>
                <option v-for="team in filteredTeams" :key="team.id" :value="team.id">{{ team.name }}</option>
            </select>
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label"><span data-i18n="ユーザー">ユーザー</span></label>
            <select class="form-select" v-model="filters.user_id" @change="onUserChange">
                <option value="" data-i18n="すべて">すべて</option>
                <option v-for="user in filteredUsers" :key="user.id" :value="user.id">{{ user.realname }}</option>
            </select>
        </div>
        <div class="col-md-3 mb-2 d-flex flex-column justify-content-end">
            <div class="form-check mb-1">
                <input class="form-check-input" type="checkbox" id="excludeCompleted"
                       v-model="filters.excludeCompleted" @change="loadOverview">
                <label class="form-check-label" for="excludeCompleted"><span data-i18n="完了タスクを除外">完了タスクを除外</span></label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="myTask"
                       v-model="filters.myTask" @change="onMyTaskChange">
                <label class="form-check-label" for="myTask"><span data-i18n="自分のタスク">自分のタスク</span></label>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="row mb-3">
        <div class="col-12">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link" :class="{ active: activeTab === 'tasks' }"
                            @click="activeTab = 'tasks'" type="button">
                        <i class="fa fa-list me-1"></i><span data-i18n="タスク一覧">タスク一覧</span>
                    </button>
                </li>
                <?php if($_SESSION['isProjectManager']): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" :class="{ active: activeTab === 'unassigned' }"
                                @click="activeTab = 'unassigned'" type="button">
                                <i class="fa fa-user-times me-1"></i><span data-i18n="タスク未割り当てユーザー">タスク未割り当てユーザー</span>
                            </button>
                        </li>
                    <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Task list (1 task per row, filters only by department / team / user) -->
    <div class="row" v-show="activeTab === 'tasks'">
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><span data-i18n="タスク一覧">タスク一覧</span></h5>
                    <button class="btn btn-sm btn-outline-secondary" @click="loadOverview" :disabled="loading">
                        <i class="fa fa-refresh me-1"></i><span data-i18n="更新">更新</span>
                    </button>
                </div>
                <div class="card-body">
                    <div v-if="loading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <div v-else-if="filteredTasks.length === 0" class="text-center text-muted py-3">
                        <i class="fa fa-inbox fa-2x mb-2"></i>
                        <p class="mb-0"><span data-i18n="タスクがありません">タスクがありません</span></p>
                    </div>
                    <div v-else class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th><span data-i18n="案件">案件</span></th>
                                    <th><span data-i18n="タスク">タスク</span></th>
                                    <th><span data-i18n="種別">種別</span></th>
                                    <th><span data-i18n="図面">図面</span></th>
                                    <th><span data-i18n="担当者">担当者</span></th>
                                    <th><span data-i18n="作成者">作成者</span></th>
                                    <th><span data-i18n="ステータス">ステータス</span></th>
                                    <th><span data-i18n="優先度">優先度</span></th>
                                    <th><span data-i18n="進捗">進捗</span></th>
                                    <th><span data-i18n="工数">工数</span></th>
                                    <th><span data-i18n="メモ">メモ</span></th>
                                    <th><span data-i18n="期限">期限</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="task in filteredTasks" :key="task.id">
                                    <td>
                                        <a :href="`detail.php?id=${task.project_id}`" class="text-decoration-none">
                                            <span class="badge bg-label-primary me-1">#{{ task.project_id }}</span>
                                            <span>{{ task.project_name }}</span>
                                        </a>
                                    </td>
                                    <td>
                                        <a :href="`task.php?project_id=${task.project_id}`" class="text-decoration-none fw-bold">
                                            <span class="badge bg-label-secondary me-1">#{{ task.id }}</span>
                                            {{ task.title }}
                                        </a>
                                    </td>
                                    <td>
                                        <span v-if="getTaskKindDisplayValue(task)" class="badge small" :class="getTaskKindBadgeClass(getTaskKindDisplayValue(task))">{{ getTaskKindLabel(getTaskKindDisplayValue(task)) }}</span>
                                    </td>
                                    <td>
                                        <span v-if="shouldShowTaskDrawingCount(task)" class="small">{{ getTaskDrawingCount(task) }}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center flex-wrap gap-1">
                                            <template v-if="task.assigned_to_ids && task.assigned_to_ids.length">
                                                <template v-for="userId in task.assigned_to_ids.slice(0, 4)" :key="userId">
                                                    <div class="avatar position-relative" data-bs-toggle="tooltip" :title="getAssigneeTooltip(task, userId)">
                                                        <img v-if="getAssigneeUser(userId) && !getAssigneeUser(userId).avatarError && getAvatarSrc(getAssigneeUser(userId))" class="rounded-circle" :src="getAvatarSrc(getAssigneeUser(userId))" :alt="getAssigneeUser(userId).realname" @error="handleAvatarError(getAssigneeUser(userId))" width="28" height="28">
                                                        <span v-else class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(getAssigneeUser(userId) ? getAssigneeUser(userId).realname : '') }}</span>
                                                        <span v-if="isAcknowledged(task, userId)" class="badge bg-success position-absolute top-0 start-100 translate-middle" style="font-size: 8px; padding: 2px 4px;"><i class="fa fa-check"></i></span>
                                                        <span v-else class="badge bg-secondary position-absolute top-0 start-100 translate-middle" style="font-size: 8px; padding: 2px 4px;"><i class="fa fa-clock"></i></span>
                                                    </div>
                                                </template>
                                                <span v-if="task.assigned_to_ids.length > 4" class="small text-muted">+{{ task.assigned_to_ids.length - 4 }}</span>
                                            </template>
                                            <span v-else class="text-muted small">未選択</span>
                                        </div>
                                    </td>
                                    <td>
                                        <template v-if="getCreatorMember(task)">
                                            <div class="d-flex align-items-center">
                                                <!-- <div class="avatar me-1" data-bs-toggle="tooltip" :title="getCreatorTooltip(getCreatorMember(task))">
                                                    <img v-if="shouldShowCreatorAvatar(getCreatorMember(task))" class="rounded-circle" :src="getAvatarSrc(getCreatorMember(task))" :alt="getCreatorTooltip(getCreatorMember(task))" @error="handleAvatarError(getCreatorMember(task))" width="28" height="28">
                                                    <span v-else class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(getCreatorMember(task).user_name) }}</span>
                                                </div> -->
                                                <span class="small">{{ getCreatorMember(task).user_name || '—' }}</span>
                                            </div>
                                        </template>
                                        <span v-else class="text-muted small">—</span>
                                    </td>
                                    <td>
                                        <span class="badge" :class="'bg-' + getStatusColor(task.status)">{{ getStatusLabel(task.status) || '-' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge" :class="'bg-' + getPriorityColor(task.priority)">{{ getPriorityLabel(task.priority) || '-' }}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1" style="height: 6px;">
                                                <div class="progress-bar bg-primary" role="progressbar"
                                                     :style="{ width: (task.progress || 0) + '%' }"
                                                     :aria-valuenow="task.progress || 0" aria-valuemin="0" aria-valuemax="100">
                                                </div>
                                            </div>
                                            <small class="ms-2 text-nowrap">{{ task.progress || 0 }}%</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="small text-nowrap">{{ formatEstimatedHours(task.estimated_hours) }}</span>
                                    </td>
                                    <td>
                                        <span v-if="getTaskNoteSnippet(task.note)" class="small text-truncate d-inline-block overview-task-note" :title="getTaskNoteSnippet(task.note)">{{ getTaskNoteSnippet(task.note) }}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1 flex-wrap small">
                                            <span class="text-nowrap" :class="{ 'text-danger fw-bold': isTaskDueExceedsProjectDue(task) }">{{ formatDate(task.due_date) }}</span>
                                            <i v-if="hasPeriodWarning(task)" class="fas fa-exclamation-triangle text-warning ms-1"
                                               data-bs-toggle="tooltip" data-bs-placement="top"
                                               :title="getPeriodWarningTooltip(task)"></i>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Unassigned users -->
    <div class="row" v-show="activeTab === 'unassigned'">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><span data-i18n="タスク未割り当てユーザー">タスク未割り当てユーザー</span></h5>
                </div>
                <div class="card-body">
                    <div v-if="unassignedUsers.length === 0" class="text-muted"><span data-i18n="すべてのユーザーにタスクが割り当てられています"></span>すべてのユーザーにタスクが割り当てられています。</span></div>
                    <div v-else class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th><span data-i18n="部署">部署</span></th>
                                    <th><span data-i18n="チーム">チーム</span></th>
                                    <th><span data-i18n="ユーザー">ユーザー</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="user in unassignedUsers" :key="user.id">
                                    <td>{{ user.department_name || '-' }}</td>
                                    <td>
                                        <span v-if="user.teams && user.teams.length" class="badge bg-info me-1" v-for="team in user.teams" :key="team.id">{{ team.name }}</span>
                                        <span v-else class="text-muted">-</span>
                                    </td>
                                    <td>{{ user.realname }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.overview-task-note {
    max-width: 7rem;
    vertical-align: bottom;
}
</style>

<?php
$view->footing();
?>

<script>
// Pass current user data to JavaScript
window.currentUser = {
    user_id: <?= json_encode($_SESSION['id'] ?? '') ?>,
    id: <?= json_encode($_SESSION['userid'] ?? '') ?>,
    department_id: <?= json_encode($_SESSION['department_id'] ?? '') ?>,
    isProjectManager: <?= json_encode($_SESSION['isProjectManager'] ?? false) ?>
};
</script>

<script src="assets/js/task-overview.js?v=<?=CACHE_VERSION?>"></script>


