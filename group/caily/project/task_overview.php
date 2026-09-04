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
        <div class="col-md-3 mb-2" v-if="canSelectUser">
            <label class="form-label"><span data-i18n="ユーザー">ユーザー</span></label>
            <select id="overviewUserFilter" class="form-select" ref="userFilterSelect">
                <option value="" data-i18n="すべて">すべて</option>
                <option v-for="user in filteredUsers" :key="user.id" :value="String(user.id)">{{ user.realname }}</option>
            </select>
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label"><span data-i18n="作成月">作成月</span></label>
            <select class="form-select" v-model="filters.created_month" @change="loadOverview">
                <option value="" data-i18n="すべて">すべて</option>
                <option v-for="month in createdMonthOptions" :key="month.value" :value="month.value">{{ month.label }}</option>
            </select>
        </div>
        <div class="col-md-12 mt-4 d-flex flex-wrap align-items-center gap-4">
            <div class="form-check form-switch mb-1">
                <input class="form-check-input" type="checkbox" id="excludeCompleted"
                       v-model="filters.excludeCompleted" @change="loadOverview">
                <label class="form-check-label" for="excludeCompleted"><span data-i18n="完了タスクを除外">完了タスクを除外</span></label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="myTask"
                       v-model="filters.myTask" @change="onMyTaskChange">
                <label class="form-check-label" for="myTask"><span data-i18n="自分のタスク">自分のタスク</span></label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="filterTimerActiveOnly"
                       v-model="filters.timerActiveOnly" @change="syncFiltersToUrl">
                <label class="form-check-label text-nowrap" for="filterTimerActiveOnly" data-i18n="作業計測中のタスクのみ">作業計測中のタスクのみ</label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="showUnassignedTasks"
                       v-model="filters.showUnassignedTasks" @change="syncFiltersToUrl">
                <label class="form-check-label text-nowrap" for="showUnassignedTasks" data-i18n="未割り当てタスクを表示">未割り当てタスクを表示</label>
            </div>
            <button class="btn btn-sm btn-outline-secondary" type="button" @click="resetFilters" :disabled="loading || weeklyLoading">
                <i class="fa fa-undo me-1"></i><span data-i18n="リセット">リセット</span>
            </button>
        </div>
    </div>

    <!-- Tabs -->
    <div class="row mb-3">
        <div class="col-12">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link" :class="{ active: activeTab === 'tasks' }"
                            @click="setActiveTab('tasks')" type="button">
                        <i class="fa fa-list me-1"></i><span data-i18n="タスク一覧">タスク一覧</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" :class="{ active: activeTab === 'weekly' }"
                            @click="selectWeeklyTab" type="button">
                        <i class="fa fa-calendar-week me-1"></i><span data-i18n="週間タスク">週間タスク</span>
                    </button>
                </li>
                <?php if($_SESSION['isProjectManager']): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" :class="{ active: activeTab === 'unassigned' }"
                                @click="setActiveTab('unassigned')" type="button">
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
                                        <a :href="getTaskPageUrl(task)" class="text-decoration-none fw-bold">
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
                                                    <div class="avatar avatar-sm position-relative" data-bs-toggle="tooltip" :title="getAssigneeTooltip(task, userId)">
                                                        <span class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(getAssigneeUser(userId) || '') }}</span>
                                                        <img v-if="getAssigneeUser(userId) && !getAssigneeUser(userId).avatarError && getAvatarSrc(getAssigneeUser(userId))" class="rounded-circle" :src="getAvatarSrc(getAssigneeUser(userId))" :alt="getAssigneeUser(userId).realname" style="display:none;" @load="$event.target.style.display='block'; if ($event.target.previousElementSibling) $event.target.previousElementSibling.style.display='none';" @error="handleAvatarError(getAssigneeUser(userId)); $event.target.remove()">
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
                                                    <span v-else class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(getCreatorMember(task)) }}</span>
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
                                    <td class="overview-workload-cell">
                                        <span
                                            class="task-workload-input-shell text-nowrap"
                                            :class="{ 'task-workload-input-shell--timer-active': hasActiveTaskTimer(task) }">
                                            <span class="task-workload-display small">{{ formatEstimatedHours(task.estimated_hours) || '—' }}</span>
                                        </span>
                                    </td>
                                    <td>
                                        <span v-if="getTaskNoteSnippet(task.note)" class="small text-truncate d-inline-block overview-task-note" :title="getTaskNoteSnippet(task.note)">{{ getTaskNoteSnippet(task.note) }}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1 small">
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

    <!-- Weekly tasks -->
    <div class="row" v-show="activeTab === 'weekly'">
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="card-title mb-0">
                        <span data-i18n="週間タスク">週間タスク</span>
                        <span v-if="weeklyUserName" class="text-muted fw-normal ms-2">{{ weeklyUserName }}</span>
                        <span v-if="!weeklyLoading" class="ms-3 fs-6">
                            <span class="badge bg-label-primary">
                                <span data-i18n="週の工数合計">週の工数合計</span>:
                                <span class="fw-bold" :class="{ 'text-danger': weeklyTotalWeekHours < 0 }">
                                    {{ formatEstimatedHours(weeklyTotalWeekHours) || '0h' }}
                                </span>
                            </span>
                        </span>
                    </h5>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="form-check form-switch mb-0 me-1">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="weeklyHoursByWeek"
                                v-model="weeklyHoursByWeek"
                                @change="syncFiltersToUrl">
                            <label class="form-check-label text-nowrap" for="weeklyHoursByWeek">
                                <span data-i18n="週単位で計算">週単位で計算</span>
                            </label>
                        </div>
                        <div class="form-check form-switch mb-0 me-1">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="weeklyShowEntries"
                                v-model="weeklyShowEntries"
                                @change="syncFiltersToUrl">
                            <label class="form-check-label text-nowrap" for="weeklyShowEntries">
                                <span data-i18n="作業時間を表示">作業時間を表示</span>
                            </label>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary" type="button" @click="shiftWeeklyWeek(-1)" :disabled="weeklyLoading">
                            <i class="fa fa-chevron-left"></i>
                        </button>
                        <span class="small text-nowrap">{{ weeklyRangeLabel }}</span>
                        <button class="btn btn-sm btn-outline-secondary" type="button" @click="shiftWeeklyWeek(1)" :disabled="weeklyLoading">
                            <i class="fa fa-chevron-right"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" type="button" @click="goToCurrentWeek" :disabled="weeklyLoading">
                            <span data-i18n="今週">今週</span>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" type="button" @click="loadWeeklyTasks" :disabled="weeklyLoading">
                            <i class="fa fa-refresh me-1"></i><span data-i18n="更新">更新</span>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div v-if="weeklyLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <div v-else-if="weeklyTasks.length === 0" class="text-center text-muted py-3">
                        <i class="fa fa-inbox fa-2x mb-2"></i>
                        <p class="mb-0"><span data-i18n="この週のタスクがありません">この週のタスクがありません</span></p>
                    </div>
                    <div v-else class="table-responsive">
                        <table class="table align-middle m mb-0 weekly-tasks-table">
                            <thead>
                                <tr>
                                    <th><span data-i18n="案件">案件</span></th>
                                    <th><span data-i18n="タスク">タスク</span></th>
                                    <th><span data-i18n="ステータス">ステータス</span></th>
                                    <th class="text-center">
                                        {{ weeklyHoursByWeek ? $t('週の工数') : $t('ユーザー工数') }}
                                    </th>
                                    <th class="text-center"><span data-i18n="工数合計">工数合計</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="task in weeklyTasks" :key="'weekly-' + task.id">
                                    <tr class="weekly-task-row">
                                        <td>
                                            <a :href="`detail.php?id=${task.project_id}`" class="text-decoration-none">
                                                <span v-if="task.project_construction_number" class="badge bg-label-info me-1">
                                                    <span data-i18n="工事">工事</span>: {{ task.project_construction_number }}
                                                </span>
                                                <span class="badge bg-label-primary me-1">#{{ task.project_id }}</span>
                                                <span>{{ task.project_name }}</span>
                                            </a>
                                        </td>
                                        <td>
                                            <a :href="getTaskPageUrl(task)" class="text-decoration-none fw-bold">
                                                <span class="badge bg-label-secondary me-1">#{{ task.id }}</span>
                                                {{ task.title }}
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge" :class="'bg-' + getStatusColor(task.status)">{{ getStatusLabel(task.status) || '-' }}</span>
                                        </td>
                                        <td class="text-center fw-semibold" :class="{ 'text-danger': Number(getWeeklyHoursDisplay(task)) < 0 }">
                                            {{ formatEstimatedHours(getWeeklyHoursDisplay(task)) || '0h' }}
                                        </td>
                                        <td class="text-center">{{ formatEstimatedHours(task.estimated_hours) || '—' }}</td>
                                    </tr>
                                    <tr v-if="weeklyShowEntries" class="weekly-entries-row">
                                        <td colspan="5">
                                            <div v-if="!task.time_entries || task.time_entries.length === 0" class="weekly-entries-empty text-muted small">
                                                <span data-i18n="作業時間がありません">作業時間がありません</span>
                                            </div>
                                            <div v-else class="weekly-entries-wrap">
                                                <table class="table table-borderless table-sm mb-0 weekly-time-entries-table">
                                                    <colgroup>
                                                        <col class="col-user">
                                                        <col class="col-start">
                                                        <col class="col-end">
                                                        <col class="col-hours">
                                                        <col class="col-note">
                                                        <col v-if="canEditWeeklyTimeEntries" class="col-actions">
                                                    </colgroup>
                                                    <thead>
                                                        <tr>
                                                            <th><span data-i18n="ユーザー">ユーザー</span></th>
                                                            <th><span data-i18n="開始">開始</span></th>
                                                            <th><span data-i18n="終了">終了</span></th>
                                                            <th class="text-center"><span data-i18n="工数">工数</span></th>
                                                            <th><span data-i18n="メモ">メモ</span></th>
                                                            <th v-if="canEditWeeklyTimeEntries" class="text-center"><span data-i18n="操作">操作</span></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr
                                                            v-for="entry in task.time_entries"
                                                            :key="entry.id"
                                                            :class="{
                                                                'is-cross-day': isCrossDayTimeEntry(entry),
                                                                'is-in-week': !isCrossDayTimeEntry(entry) && weeklyHoursByWeek && entry.in_week
                                                            }">
                                                            <td class="small">{{ entry.user_name || entry.user_id || '—' }}</td>
                                                            <td class="small" :class="{ 'text-danger fw-semibold': isCrossDayTimeEntry(entry) }">{{ formatDate(entry.start_time) }}</td>
                                                            <td class="small" :class="{ 'text-danger fw-semibold': isCrossDayTimeEntry(entry) }">
                                                                <span v-if="entry.running" class="badge bg-success" data-i18n="作業計測中">作業計測中</span>
                                                                <span v-else>{{ formatDate(entry.end_time) }}</span>
                                                            </td>
                                                            <td class="text-center small" :class="{ 'text-danger': Number(entry.hours) < 0 }">{{ entry.running ? '—' : (formatEstimatedHours(entry.hours) || '0h') }}</td>
                                                            <td class="small text-truncate" :title="entry.description || ''">{{ entry.description || '—' }}</td>
                                                            <td v-if="canEditWeeklyTimeEntries" class="text-center">
                                                                <button
                                                                    v-if="!entry.running"
                                                                    type="button"
                                                                    class="btn btn-sm btn-icon btn-text-secondary rounded-pill"
                                                                    :title="$t('編集')"
                                                                    @click="openWeeklyTimeEntryModal(task, entry)">
                                                                    <i class="fa fa-pencil-alt"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
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

    <!-- Modal: admin edit weekly time entry (same UX as task.php 工数 modal) -->
    <div v-if="weeklyEntryModal.show" class="modal-backdrop fade show" @click="closeWeeklyTimeEntryModal"></div>
    <div class="modal fade" tabindex="-1" :class="{show: weeklyEntryModal.show}" style="display: block;" v-if="weeklyEntryModal.show">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><span data-i18n="工数を編集">工数を編集</span></h5>
                    <button type="button" class="btn-close" @click="closeWeeklyTimeEntryModal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small mb-1" for="weeklyEntryStartAtPicker"><span data-i18n="開始">開始</span></label>
                        <input type="text"
                               id="weeklyEntryStartAtPicker"
                               class="form-control"
                               autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small mb-1" for="weeklyEntryEndAtPicker"><span data-i18n="終了">終了</span></label>
                        <input type="text"
                               id="weeklyEntryEndAtPicker"
                               class="form-control"
                               autocomplete="off">
                    </div>
                    <div class="row g-2 align-items-end">
                        <div class="col">
                            <label class="form-label small mb-1"><span data-i18n="時間">時間</span></label>
                            <input type="number" class="form-control" step="1" v-model.number="weeklyEntryModal.hours" @keyup.enter="confirmWeeklyTimeEntryModal">
                        </div>
                        <div class="col-auto pb-2 text-muted">:</div>
                        <div class="col">
                            <label class="form-label small mb-1"><span data-i18n="分">分</span></label>
                            <input type="number" class="form-control" step="1" v-model.number="weeklyEntryModal.minutes" @keyup.enter="confirmWeeklyTimeEntryModal">
                        </div>
                    </div>
                    <p class="small text-muted mt-2 mb-0">
                        <span data-i18n="換算">換算</span>: {{ getWeeklyEntryModalPreview() }}
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="closeWeeklyTimeEntryModal"><span data-i18n="キャンセル">キャンセル</span></button>
                    <button type="button" class="btn btn-primary" @click="confirmWeeklyTimeEntryModal" :disabled="weeklyEntryModal.saving">
                        <span data-i18n="保存">保存</span>
                    </button>
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
.overview-workload-cell .task-workload-input-shell {
    display: inline-flex;
}
.overview-workload-cell .task-workload-display {
    min-width: 2.75rem;
    padding-right: 0.9rem;
}
.overview-workload-cell .task-workload-input-shell--timer-active .task-workload-display {
    border-color: transparent;
    background-color: transparent;
    animation: none;
}

/* Weekly overview tables — theme tokens (light / dark via data-bs-theme) */
.weekly-tasks-table {
    --weekly-surface: var(--bs-paper-bg);
    --weekly-muted: rgba(var(--bs-base-color-rgb, 47, 43, 61), 0.04);
    --weekly-hover: rgba(var(--bs-primary-rgb), 0.06);
    --weekly-line: var(--bs-border-color);
    --weekly-accent: var(--bs-primary);
    border-collapse: separate;
    border-spacing: 0;
    --bs-table-bg: transparent;
    --bs-table-color: var(--bs-body-color);
    --bs-table-border-color: transparent;
}
.weekly-tasks-table > thead > tr > th {
    background: color-mix(in sRGB, var(--bs-primary) 10%, var(--bs-paper-bg));
    color: var(--bs-heading-color);
    font-size: 0.75rem;
    font-weight: 650;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    border-bottom: 2px solid var(--weekly-accent);
    white-space: nowrap;
    padding: 0.65rem 0.75rem;
}
.weekly-tasks-table > tbody > tr.weekly-task-row > td {
    border-top: 1px solid var(--weekly-line);
    background: var(--weekly-surface);
    padding: 0.7rem 0.75rem;
}
.weekly-tasks-table > tbody > tr.weekly-task-row:hover > td {
    background: var(--weekly-hover);
}
.weekly-tasks-table > tbody > tr.weekly-entries-row > td {
    padding: 0 0 0.65rem;
    border-top: 0;
    background: var(--weekly-muted);
}

/* Child entries — flat, nested, less chrome */
.weekly-entries-wrap,
.weekly-entries-empty {
    margin: 0.35rem 0.75rem 0 1.5rem;
    padding: 0.15rem 0 0.15rem 0.85rem;
    border-left: 2px solid color-mix(in sRGB, var(--bs-primary) 55%, transparent);
    background: transparent;
}
.weekly-entries-empty {
    padding-top: 0.35rem;
    padding-bottom: 0.35rem;
    border-left-color: var(--bs-border-color);
}

.weekly-time-entries-table {
    table-layout: fixed;
    width: 100%;
    margin: 0;
    background: transparent;
    --bs-table-bg: transparent;
    --bs-table-color: var(--bs-body-color);
    --bs-table-border-color: transparent;
}
.weekly-time-entries-table col.col-user { width: 14%; }
.weekly-time-entries-table col.col-start { width: 18%; }
.weekly-time-entries-table col.col-end { width: 18%; }
.weekly-time-entries-table col.col-hours { width: 8%; }
.weekly-time-entries-table col.col-note { width: auto; }
.weekly-time-entries-table col.col-actions { width: 3.25rem; }
.weekly-time-entries-table th,
.weekly-time-entries-table td {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    vertical-align: middle;
    border: 0 !important;
}
.weekly-time-entries-table > thead > tr > th {
    color: var(--bs-secondary-color);
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    padding: 0.25rem 0.5rem 0.35rem;
    text-transform: none;
    background: transparent;
}
.weekly-time-entries-table > tbody > tr > td {
    padding: 0.32rem 0.5rem;
    color: var(--bs-body-color);
    background: transparent;
}
.weekly-time-entries-table > tbody > tr + tr > td {
    box-shadow: inset 0 1px 0 color-mix(in sRGB, var(--bs-border-color) 55%, transparent);
}
.weekly-time-entries-table > tbody > tr.is-in-week > td {
    background: #fff8e8;
}
.weekly-time-entries-table > tbody > tr.is-cross-day > td {
    background: #fdeeee;
}
[data-bs-theme=dark] .weekly-time-entries-table > tbody > tr.is-in-week > td {
    background: color-mix(in sRGB, #ffb400 18%, var(--bs-paper-bg));
}
[data-bs-theme=dark] .weekly-time-entries-table > tbody > tr.is-cross-day > td {
    background: color-mix(in sRGB, var(--bs-danger) 18%, var(--bs-paper-bg));
}
.weekly-time-entries-table .btn-icon {
    width: 1.75rem;
    height: 1.75rem;
    padding: 0;
    line-height: 1.75rem;
}

/* Flatpickr inside weekly time-entry modal */
#app .flatpickr-calendar.static {
    z-index: 1100;
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

<script src="assets/js/task-overview.js?v=<?=STATS_CACHE_VERSION?>"></script>


