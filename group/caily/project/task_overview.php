<?php
require_once('../application/loader.php');
$view->heading('タスク一覧');
if($_SESSION['show_project'] == 0){
    echo '<div class="container-fluid mt-4"><div class="alert alert-danger">権限がありません。</div></div>';
    exit;
}

// Fallback department for the board (the page JS prefers the department chosen on the project list):
// the user's first department, or the first active department when the user belongs to none.
$overviewMyDepartmentIds = array();
$overviewDefaultDepartmentId = '';
try {
    $overviewDeptModel = new ApplicationModel();
    $overviewDeptModel->connect();
    $overviewDeptRows = $overviewDeptModel->fetchAll(
        "SELECT DISTINCT ud.department_id
         FROM " . DB_PREFIX . "user_department ud
         JOIN " . DB_PREFIX . "departments d ON d.id = ud.department_id AND d.is_active = 1
         WHERE ud.userid = '" . $overviewDeptModel->quote((string)($_SESSION['userid'] ?? '')) . "'
         ORDER BY ud.department_id ASC"
    );
    foreach ($overviewDeptRows as $overviewDeptRow) {
        $overviewMyDepartmentIds[] = (int)$overviewDeptRow['department_id'];
    }
    if ($overviewMyDepartmentIds) {
        $overviewDefaultDepartmentId = (string)$overviewMyDepartmentIds[0];
    } else {
        $overviewFallbackDept = $overviewDeptModel->fetchOne(
            "SELECT id FROM " . DB_PREFIX . "departments WHERE is_active = 1 ORDER BY id ASC LIMIT 1"
        );
        if (!empty($overviewFallbackDept['id'])) {
            $overviewDefaultDepartmentId = (string)(int)$overviewFallbackDept['id'];
        }
    }
} catch (Exception $e) {
    $overviewMyDepartmentIds = array();
}
?>

<div id="app" class="container-fluid mt-4" v-cloak>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h4 class="mb-0">
            <i class="fa fa-tasks me-2"></i><span data-i18n="全タスク一覧">全タスク一覧</span>
            <span v-if="currentDepartmentName" class="kb-dept-pill ms-2">{{ currentDepartmentName }}</span>
        </h4>
    </div>

    <!-- Filters -->
    <div class="card kb-filter-card mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small mb-1"><span data-i18n="部署">部署</span></label>
                    <select autocomplete="off" class="form-select form-select-sm" v-model="filters.department_id" @change="onDepartmentChange">
                        <option v-for="dep in departmentOptions" :key="dep.id" :value="String(dep.id)">{{ isMyDepartment(dep.id) ? '★ ' : '' }}{{ dep.name }}</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2" :class="{ 'kb-filter-on': !!filters.team_id }">
                    <label class="form-label small mb-1"><span data-i18n="チーム">チーム</span></label>
                    <select autocomplete="off" class="form-select form-select-sm" v-model="filters.team_id" @change="loadOverview">
                        <option value="" data-i18n="すべて">すべて</option>
                        <option v-for="team in filteredTeams" :key="team.id" :value="team.id">{{ team.name }}</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2" v-if="canSelectUser" v-show="activeTab === 'weekly'" :class="{ 'kb-filter-on': !!filters.user_id }">
                    <label class="form-label small mb-1"><span data-i18n="ユーザー">ユーザー</span></label>
                    <select autocomplete="off" id="overviewUserFilter" class="form-select form-select-sm" ref="userFilterSelect">
                        <option value="" data-i18n="すべて">すべて</option>
                        <option v-for="user in filteredUsers" :key="user.id" :value="String(user.id)">{{ user.realname }}</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2" v-show="activeTab === 'tasks'" :class="{ 'kb-filter-on': !!filters.created_month }">
                    <label class="form-label small mb-1"><span data-i18n="作成月">作成月</span></label>
                    <select autocomplete="off" class="form-select form-select-sm" v-model="filters.created_month" @change="loadOverview">
                        <option value="" data-i18n="すべて">すべて</option>
                        <option v-for="month in createdMonthOptions" :key="month.value" :value="month.value">{{ month.label }}</option>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-4" v-show="activeTab === 'tasks'" :class="{ 'kb-filter-on': !!searchQuery }">
                    <label class="form-label small mb-1" for="kbSearch"><span data-i18n="検索">検索</span></label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="fa fa-search"></i></span>
                        <input autocomplete="off" id="kbSearch" type="search" class="form-control" v-model.trim="searchQuery"
                               :placeholder="$t('タスク名・案件名・番号で検索')">
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-center column-gap-4 row-gap-2 mt-3">
            <div class="form-check form-switch mb-0" :class="{ 'kb-filter-on': !filters.excludeCompleted }">
                <input autocomplete="off" class="form-check-input" type="checkbox" id="showClosedTasks"
                       :checked="!filters.excludeCompleted" @change="setShowClosedTasks($event.target.checked)">
                <label class="form-check-label text-nowrap" for="showClosedTasks"><span data-i18n="完了・キャンセルを表示">完了・キャンセルを表示</span></label>
            </div>
            <div class="form-check form-switch" :class="{ 'kb-filter-on': filters.myTask }">
                <input autocomplete="off" class="form-check-input" type="checkbox" id="myTask"
                       v-model="filters.myTask" @change="onMyTaskChange">
                <label class="form-check-label" for="myTask"><span data-i18n="自分のタスク">自分のタスク</span></label>
            </div>
            <div class="form-check form-switch" :class="{ 'kb-filter-on': filters.timerActiveOnly }">
                <input autocomplete="off" class="form-check-input" type="checkbox" id="filterTimerActiveOnly"
                       v-model="filters.timerActiveOnly" @change="onTimerActiveOnlyChange">
                <label class="form-check-label text-nowrap" for="filterTimerActiveOnly" data-i18n="作業計測中のタスクのみ">作業計測中のタスクのみ</label>
            </div>
            <div class="form-check form-switch" :class="{ 'kb-filter-on': filters.unassignedOnly }">
                <input autocomplete="off" class="form-check-input" type="checkbox" id="unassignedOnly"
                       v-model="filters.unassignedOnly" @change="onUnassignedOnlyChange">
                <label class="form-check-label text-nowrap" for="unassignedOnly" data-i18n="未割り当てタスクのみ">未割り当てタスクのみ</label>
            </div>
            <button class="btn btn-sm btn-outline-secondary" type="button" @click="resetFilters" :disabled="loading || weeklyLoading"
                    :title="$t('自分の部署の表示に戻す')">
                <i class="fa fa-undo me-1"></i><span data-i18n="リセット">リセット</span>
            </button>
            </div>
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

    <!-- Task board (kanban) / list -->
    <div v-show="activeTab === 'tasks'">
        <!-- Toolbar: quick filters, sort, view mode -->
        <div class="kb-toolbar d-flex flex-wrap align-items-center gap-2 mb-2">
            <div class="kb-stats d-flex flex-wrap gap-2" role="group">
                <button v-for="stat in quickStats" :key="stat.key" type="button" class="kb-stat"
                        :class="['kb-stat--' + stat.tone, { 'is-active': quickFilter === stat.key }]"
                        :aria-pressed="quickFilter === stat.key ? 'true' : 'false'"
                        @click="setQuickFilter(stat.key)">
                    <i :class="stat.icon"></i>
                    <span>{{ $t(stat.label) }}</span>
                    <b>{{ stat.count }}</b>
                </button>
            </div>
            <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">
                <select autocomplete="off" class="form-select form-select-sm kb-sort" v-model="boardSort" @change="persistBoardPrefs" :aria-label="$t('並び替え')">
                    <option v-for="opt in boardSortOptions" :key="opt.value" :value="opt.value">{{ $t(opt.label) }}</option>
                </select>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn" :class="viewMode === 'kanban' ? 'btn-primary' : 'btn-outline-primary'" @click="setViewMode('kanban')">
                        <i class="fa fa-columns me-1"></i><span data-i18n="カンバン">カンバン</span>
                    </button>
                    <button type="button" class="btn" :class="viewMode === 'list' ? 'btn-primary' : 'btn-outline-primary'" @click="setViewMode('list')">
                        <i class="fa fa-list me-1"></i><span data-i18n="リスト">リスト</span>
                    </button>
                </div>
                <button type="button" class="btn btn-sm btn-outline-success text-nowrap" @click="exportTasksExcel" :disabled="loading || !getExportTasks().length" :title="$t('表示中のタスクをExcelに出力')">
                    <i class="fa fa-file-excel me-1"></i><span data-i18n="Excel出力">Excel出力</span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" @click="loadOverview" :disabled="loading" :title="$t('更新')" :aria-label="$t('更新')">
                    <i class="fa fa-refresh" :class="{ 'fa-spin': loading }"></i>
                </button>
            </div>
        </div>

        <!-- Member bar: click a person to see only their tasks -->
        <div v-if="boardMembers.length" class="kb-members mb-3">
            <span class="kb-members-label small text-muted text-nowrap"><i class="fa fa-users me-1"></i><span data-i18n="担当者">担当者</span></span>
            <div class="kb-members-scroll">
                <button v-for="m in boardMembers" :key="m.id" type="button" class="kb-member"
                        :class="{ 'is-active': isMemberSelected(m.id), 'is-idle': m.count === 0 }"
                        :aria-pressed="isMemberSelected(m.id) ? 'true' : 'false'"
                        :title="m.name + ' : ' + m.count"
                        @click="toggleMember(m.id)">
                    <span class="kb-avatar" :class="{ 'kb-avatar--none': m.id === 'none' }">
                        <i v-if="m.id === 'none'" class="fa fa-user-slash"></i>
                        <img v-else-if="m.user && getMemberAvatarSrc(m.user)" :src="getMemberAvatarSrc(m.user)" alt="" @error="handleAvatarError(m.user)">
                        <template v-else>{{ getInitials(m.user || m.name) }}</template>
                    </span>
                    <span class="kb-member-name">{{ m.name }}</span>
                    <span class="kb-member-count">{{ m.count }}</span>
                </button>
            </div>
            <button v-if="memberFilterIds.length" type="button" class="btn btn-sm btn-outline-secondary kb-members-clear text-nowrap" @click="clearMemberFilter">
                <i class="fa fa-times me-1"></i><span data-i18n="選択解除">選択解除</span>
            </button>
        </div>

        <!-- Kanban board -->
        <div v-if="viewMode === 'kanban'" class="kb-board-wrap" :class="{ 'is-loading': loading }">
            <div v-if="loading" class="kb-loading">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
            <div class="kb-board">
                <section v-for="col in boardColumns" :key="col.value" class="kb-col"
                         :class="['kb-col--' + col.value, { 'is-collapsed': col.collapsed }]"
                         :style="{ '--kb-accent': col.accent }">
                    <header class="kb-col-head" @click="col.collapsed && toggleColumn(col.value)">
                        <button type="button" class="kb-col-toggle" @click.stop="toggleColumn(col.value)"
                                :title="col.collapsed ? $t('列を開く') : $t('列を折りたたむ')"
                                :aria-label="col.collapsed ? $t('列を開く') : $t('列を折りたたむ')"
                                :aria-expanded="col.collapsed ? 'false' : 'true'">
                            <i class="fa" :class="col.collapsed ? 'fa-chevron-right' : 'fa-chevron-down'"></i>
                        </button>
                        <span class="kb-col-dot"></span>
                        <span class="kb-col-title">{{ getStatusLabel(col.value) }}</span>
                        <span class="kb-col-count">{{ col.tasks.length }}</span>
                        <span v-if="col.hours && !col.collapsed" class="kb-col-hours" :title="$t('工数合計')">{{ formatEstimatedHours(col.hours) }}</span>
                    </header>
                    <div class="kb-col-body">
                        <div class="kb-list" :data-status="col.value">
                            <article v-for="task in col.visibleTasks" :key="task.id" class="kb-card"
                                     :class="['kb-prio--' + (task.priority || 'medium'), { 'is-timer': hasActiveTaskTimer(task) }]"
                                     :data-task-id="task.id"
                                     @click="onCardClick($event, task)">
                                <div class="kb-card-top">
                                    <a :href="`detail.php?id=${task.project_id}`" class="kb-card-project" :title="task.project_name">
                                        <span v-if="task.project_construction_number" class="kb-card-cno">{{ task.project_construction_number }}</span>
                                        <span class="kb-card-pid">#{{ task.project_id }}</span>
                                        {{ task.project_name }}
                                    </a>
                                </div>
                                <a :href="getTaskPageUrl(task)" class="kb-card-title">{{ task.title }}</a>
                                <div class="kb-card-tags">
                                    <span class="kb-prio-tag" :class="'kb-prio-tag--' + (task.priority || 'medium')">{{ getPriorityLabel(task.priority) }}</span>
                                    <span v-if="getTaskKindDisplayValue(task)" class="badge kb-kind" :class="getTaskKindBadgeClass(getTaskKindDisplayValue(task))">{{ getTaskKindLabel(getTaskKindDisplayValue(task)) }}</span>
                                    <span v-if="formatDrawingWeight(task)" class="kb-meta" :title="$t('作業比重')"><i class="fa fa-drafting-compass"></i>{{ formatDrawingWeight(task) }}</span>
                                    <span v-if="getTaskNoteSnippet(task.note)" class="kb-meta" :title="getTaskNoteSnippet(task.note, 300)"><i class="far fa-sticky-note"></i></span>
                                </div>
                                <div v-if="Number(task.progress) > 0" class="kb-progress" :title="$t('進捗') + ' ' + (task.progress || 0) + '%'">
                                    <div class="kb-progress-bar" :style="{ width: Math.min(100, Number(task.progress) || 0) + '%' }"></div>
                                </div>
                                <div class="kb-card-foot">
                                    <span class="kb-due" :class="getDueClass(task)" :title="getDueTooltip(task)">
                                        <i class="far fa-calendar"></i>{{ formatDueShort(task) }}
                                        <i v-if="isTaskDueExceedsProjectDue(task)" class="fas fa-exclamation-triangle text-warning"></i>
                                    </span>
                                    <span v-if="formatEstimatedHours(task.estimated_hours)" class="kb-meta" :title="$t('工数')"><i class="far fa-clock"></i>{{ formatEstimatedHours(task.estimated_hours) }}</span>
                                    <span v-if="hasActiveTaskTimer(task)" class="kb-timer" :title="$t('作業計測中')"><span class="kb-timer-dot"></span></span>
                                    <div class="kb-assignees">
                                        <template v-if="task.assigned_to_ids && task.assigned_to_ids.length">
                                            <span v-for="userId in task.assigned_to_ids.slice(0, 3)" :key="userId"
                                                  class="kb-avatar kb-avatar--sm" :class="{ 'is-unack': !isAcknowledged(task, userId) }"
                                                  :title="getAssigneeTooltip(task, userId)">
                                                <img v-if="getMemberAvatarSrc(getAssigneeUser(userId))" :src="getMemberAvatarSrc(getAssigneeUser(userId))" alt="" @error="handleAvatarError(getAssigneeUser(userId))">
                                                <template v-else>{{ getInitials(getAssigneeUser(userId) || '') }}</template>
                                            </span>
                                            <span v-if="task.assigned_to_ids.length > 3" class="kb-avatar kb-avatar--sm kb-avatar--more">+{{ task.assigned_to_ids.length - 3 }}</span>
                                        </template>
                                        <span v-else class="kb-unassigned"><i class="fa fa-user-slash me-1"></i><span data-i18n="未選択">未選択</span></span>
                                    </div>
                                </div>
                            </article>
                        </div>
                        <div v-if="!col.tasks.length" class="kb-empty">
                            <i class="fa fa-inbox"></i>
                            <span>{{ $t('タスクがありません') }}</span>
                        </div>
                        <button v-if="col.tasks.length > col.visibleTasks.length" type="button" class="kb-more" @click="showMoreInColumn(col.value)">
                            {{ $t('さらに表示') }} ({{ col.tasks.length - col.visibleTasks.length }})
                        </button>
                    </div>
                </section>
            </div>
        </div>

        <!-- List view -->
        <div v-else class="card mb-3">
            <div class="card-body">
                    <div class="btn-group flex-wrap task-status-filter-group mb-4" role="group" :aria-label="$t('ステータス')">
                        <button type="button" class="btn btn-sm status-filter-btn"
                                :class="{ 'btn-label-secondary': !isAllStatusFilterSelected, 'btn-secondary': isAllStatusFilterSelected, 'active': isAllStatusFilterSelected }"
                                :aria-pressed="isAllStatusFilterSelected ? 'true' : 'false'"
                                @click="selectAllStatusFilter">
                            <span data-i18n="すべて">{{ $t('すべて') }}</span>
                            <span class="status-filter-count">{{ sortedBoardTasks.length }}</span>
                        </button>
                        <button v-for="status in listStatusOptions" :key="status.value" type="button" class="btn btn-sm status-filter-btn"
                                :class="{ [`btn-label-${status.color}`]: !isStatusFilterSelected(status), [`btn-${status.color}`]: isStatusFilterSelected(status), 'active': isStatusFilterSelected(status) }"
                                :aria-pressed="isStatusFilterSelected(status) ? 'true' : 'false'"
                                @click="toggleTaskStatusFilter(status)">
                            {{ $t(status.i18nKey || status.label) }}
                            <span class="status-filter-count">{{ status.count }}</span>
                        </button>
                    </div>
                    <div v-if="loading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <div v-else-if="listTasks.length === 0" class="text-center text-muted py-3">
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
                                    <th class="text-end text-nowrap"><span data-i18n="作業比重">作業比重</span></th>
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
                                <tr v-for="task in listTasks" :key="task.id">
                                    <td>
                                        <div v-if="task.project_construction_number" class="mb-1">
                                            <span class="badge bg-label-info"><span data-i18n="工事">工事</span>: {{ task.project_construction_number }}</span>
                                        </div>
                                        <div>
                                            <a :href="`detail.php?id=${task.project_id}`" class="text-decoration-none">
                                                <span class="badge bg-label-primary me-1">#{{ task.project_id }}</span>
                                                <span>{{ task.project_name }}</span>
                                            </a>
                                        </div>
                                        <div v-if="task.project_company_name" class="small text-muted">
                                            <i class="fa fa-building me-1"></i>{{ task.project_company_name }}
                                        </div>
                                        <div v-if="task.project_branch_name" class="small text-muted">
                                            <i class="fa fa-map-marker-alt me-1"></i>{{ task.project_branch_name }}
                                        </div>
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
                                    <td class="text-end text-nowrap">
                                        <span v-if="formatDrawingWeight(task)" class="small">{{ formatDrawingWeight(task) }}</span>
                                        <span v-else class="text-muted small">—</span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center flex-wrap gap-1">
                                            <template v-if="task.assigned_to_ids && task.assigned_to_ids.length">
                                                <template v-for="userId in task.assigned_to_ids.slice(0, 4)" :key="userId">
                                                    <div class="avatar position-relative" data-bs-toggle="tooltip" :title="getAssigneeTooltip(task, userId)">
                                                        <img v-if="getMemberAvatarSrc(getAssigneeUser(userId))" class="rounded-circle" :src="getMemberAvatarSrc(getAssigneeUser(userId))" :alt="getAssigneeUser(userId).realname" @error="handleAvatarError(getAssigneeUser(userId))">
                                                        <span v-else class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(getAssigneeUser(userId) || '') }}</span>
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
                            <input autocomplete="off"
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
                            <input autocomplete="off"
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
                                            <div v-if="task.project_construction_number" class="mb-1">
                                                <span class="badge bg-label-info"><span data-i18n="工事">工事</span>: {{ task.project_construction_number }}</span>
                                            </div>
                                            <div>
                                                <a :href="`detail.php?id=${task.project_id}`" class="text-decoration-none">
                                                    <span class="badge bg-label-primary me-1">#{{ task.project_id }}</span>
                                                    <span>{{ task.project_name }}</span>
                                                </a>
                                            </div>
                                            <div v-if="task.project_company_name" class="small text-muted">
                                                <i class="fa fa-building me-1"></i>{{ task.project_company_name }}
                                            </div>
                                            <div v-if="task.project_branch_name" class="small text-muted">
                                                <i class="fa fa-map-marker-alt me-1"></i>{{ task.project_branch_name }}
                                            </div>
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
/* ===== Task board (kanban, read-only) ===== */
.kb-dept-pill {
    display: inline-block;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.2rem 0.65rem;
    border-radius: 999px;
    vertical-align: middle;
    color: var(--bs-primary);
    background: rgba(var(--bs-primary-rgb), 0.12);
}
.kb-filter-card .form-label { color: var(--bs-secondary-color); }

/* Quick stats */
.kb-stat {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.75rem;
    font-size: 0.8125rem;
    line-height: 1.4;
    border-radius: 999px;
    border: 1px solid var(--bs-border-color);
    background: var(--bs-paper-bg, var(--bs-body-bg));
    color: var(--bs-body-color);
    cursor: pointer;
    transition: background-color .15s, border-color .15s, color .15s;
}
.kb-stat b { font-weight: 700; }
.kb-stat:hover { border-color: var(--kb-stat-color, var(--bs-primary)); }
.kb-stat--neutral { --kb-stat-color: var(--bs-primary); --kb-stat-rgb: var(--bs-primary-rgb); }
.kb-stat--danger  { --kb-stat-color: var(--bs-danger);  --kb-stat-rgb: var(--bs-danger-rgb); }
.kb-stat--warning { --kb-stat-color: var(--bs-warning); --kb-stat-rgb: var(--bs-warning-rgb); }
.kb-stat--info    { --kb-stat-color: var(--bs-info);    --kb-stat-rgb: var(--bs-info-rgb); }
.kb-stat i { color: var(--kb-stat-color); }
.kb-stat.is-active {
    border-color: var(--kb-stat-color);
    background: rgba(var(--kb-stat-rgb), 0.12);
    color: var(--kb-stat-color);
}
.kb-sort { width: auto; min-width: 9.5rem; }

/* Member bar */
.kb-members {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}
.kb-members-scroll {
    display: flex;
    gap: 0.4rem;
    overflow-x: auto;
    padding: 0.15rem 0.1rem 0.35rem;
    min-width: 0;
    flex: 1 1 auto;
    scrollbar-width: thin;
}
.kb-member {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    flex: 0 0 auto;
    padding: 0.2rem 0.6rem 0.2rem 0.2rem;
    border-radius: 999px;
    border: 1px solid var(--bs-border-color);
    background: var(--bs-paper-bg, var(--bs-body-bg));
    color: var(--bs-body-color);
    font-size: 0.8125rem;
    cursor: pointer;
    transition: background-color .15s, border-color .15s;
}
.kb-member:hover { border-color: var(--bs-primary); }
.kb-member.is-active {
    border-color: var(--bs-primary);
    background: rgba(var(--bs-primary-rgb), 0.12);
    color: var(--bs-primary);
}
.kb-member.is-idle:not(.is-active) { opacity: 0.55; }
.kb-member-name { max-width: 8rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.kb-member-count {
    min-width: 1.4rem;
    padding: 0 0.35rem;
    border-radius: 999px;
    text-align: center;
    font-weight: 600;
    font-size: 0.75rem;
    background: rgba(var(--bs-secondary-rgb), 0.14);
}
.kb-member.is-active .kb-member-count { background: var(--bs-primary); color: #fff; }

/* Avatars */
/* Filters that are in effect: highlighted input + label */
.kb-filter-card .kb-filter-on .form-label {
    color: var(--bs-primary, #7650b0);
    font-weight: 600;
}
.kb-filter-card .kb-filter-on .form-select,
.kb-filter-card .kb-filter-on .form-control,
.kb-filter-card .kb-filter-on .input-group-text,
.kb-filter-card .kb-filter-on .select2-container--default .select2-selection {
    border-color: var(--bs-primary, #7650b0) !important;
    background-color: rgba(var(--bs-primary-rgb, 118, 80, 176), 0.08);
    font-weight: 600;
}
.kb-filter-card .kb-filter-on .input-group-text { color: var(--bs-primary, #7650b0); }
.kb-filter-card .form-check.kb-filter-on .form-check-label {
    color: var(--bs-primary, #7650b0);
    font-weight: 600;
}

/* List view status filter (same pattern as the project task page) */
.task-status-filter-group { overflow: visible !important; }
.status-filter-btn { position: relative; overflow: visible; }
.status-filter-btn .status-filter-count { margin-left: 0.25rem; opacity: 0.75; font-weight: 600; }
.status-filter-btn::after {
    content: '';
    position: absolute;
    bottom: -0.65rem;
    left: 50%;
    transform: translateX(-50%);
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: currentColor;
    pointer-events: none;
    display: none;
}
.status-filter-btn.active::after { display: block; }
.status-filter-btn.btn-secondary.active::after { background-color: #6c757d; }
.status-filter-btn.btn-primary.active::after { background-color: #7650b0; }
.status-filter-btn.btn-success.active::after { background-color: #198754; }
.status-filter-btn.btn-warning.active::after { background-color: #ffc107; }
.status-filter-btn.btn-danger.active::after { background-color: #dc3545; }

.kb-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 50%;
    overflow: hidden;
    font-size: 0.7rem;
    font-weight: 600;
    line-height: 1;
    white-space: nowrap; /* initials such as "ウエン" / "Duya" must stay on one line */
    letter-spacing: -0.02em;
    color: var(--bs-primary);
    background: rgba(var(--bs-primary-rgb), 0.16);
}
.kb-avatar img { width: 100%; height: 100%; object-fit: cover; }
/* Larger avatars in the 担当者 filter bar */
.kb-member .kb-avatar {
    width: 2.375rem;
    height: 2.375rem;
    font-size: 0.6875rem;
}
.kb-avatar--none { color: var(--bs-secondary-color); background: rgba(var(--bs-secondary-rgb), 0.16); }
.kb-avatar--sm {
    width: 1.5rem;
    height: 1.5rem;
    font-size: 0.625rem;
    box-shadow: 0 0 0 2px var(--bs-paper-bg, var(--bs-body-bg));
}
.kb-avatar--sm + .kb-avatar--sm { margin-left: -0.35rem; }
.kb-avatar--sm.is-unack { opacity: 0.5; }
.kb-avatar--more { color: var(--bs-secondary-color); background: rgba(var(--bs-secondary-rgb), 0.18); }

/* Board */
.kb-board-wrap { position: relative; }
.kb-board-wrap.is-loading .kb-board { opacity: 0.45; pointer-events: none; }
.kb-loading {
    position: absolute;
    inset: 3rem 0 auto 0;
    display: flex;
    justify-content: center;
    z-index: 2;
}
.kb-board {
    display: flex;
    gap: 0.875rem;
    align-items: flex-start;
    overflow-x: auto;
    padding-bottom: 0.75rem;
    scroll-snap-type: x proximity;
    transition: opacity .15s;
}
.kb-col {
    --kb-accent: var(--bs-secondary);
    /* Columns share the full board width (no upper cap) */
    flex: 1 1 0;
    min-width: 18rem;
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 13rem);
    min-height: 12rem;
    border-radius: 0.6rem;
    background: rgba(var(--bs-secondary-rgb), 0.07);
    border-top: 3px solid var(--kb-accent);
    scroll-snap-align: start;
}
[data-bs-theme=dark] .kb-col { background: rgba(255, 255, 255, 0.035); }
.kb-col-head {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.6rem 0.75rem 0.5rem 0.4rem;
}
.kb-col-toggle {
    border: 0;
    background: transparent;
    color: var(--bs-secondary-color);
    width: 1.5rem;
    height: 1.5rem;
    padding: 0;
    border-radius: 0.35rem;
    font-size: 0.7rem;
}
.kb-col-toggle:hover { background: rgba(var(--bs-secondary-rgb), 0.15); }
.kb-col-dot { width: 0.55rem; height: 0.55rem; border-radius: 50%; background: var(--kb-accent); flex: 0 0 auto; }
.kb-col-title { font-weight: 650; font-size: 0.875rem; color: var(--bs-heading-color); white-space: nowrap; }
.kb-col-count {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0 0.45rem;
    border-radius: 999px;
    color: var(--bs-secondary-color);
    background: rgba(var(--bs-secondary-rgb), 0.16);
}
.kb-col-hours { margin-left: auto; font-size: 0.75rem; color: var(--bs-secondary-color); white-space: nowrap; }
.kb-col-body {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0 0.5rem 0.6rem;
    overflow-y: auto;
    min-height: 6rem;
    scrollbar-width: thin;
}
.kb-list { display: flex; flex-direction: column; gap: 0.5rem; }

/* Collapsed column: narrow vertical strip */
.kb-col.is-collapsed {
    flex: 0 0 2.75rem;
    min-width: 2.75rem;
    cursor: pointer;
}
.kb-col.is-collapsed .kb-col-head {
    flex-direction: column;
    padding: 0.5rem 0;
    gap: 0.6rem;
}
.kb-col.is-collapsed .kb-col-title { writing-mode: vertical-rl; }
.kb-col.is-collapsed .kb-col-body { display: none; }

.kb-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    padding: 1.25rem 0.5rem;
    text-align: center;
    font-size: 0.8125rem;
    color: var(--bs-secondary-color);
    border: 1px dashed var(--bs-border-color);
    border-radius: 0.5rem;
}
.kb-empty i { font-size: 1.1rem; opacity: 0.6; }
.kb-more {
    border: 0;
    border-radius: 0.4rem;
    padding: 0.35rem;
    font-size: 0.8125rem;
    color: var(--bs-primary);
    background: rgba(var(--bs-primary-rgb), 0.08);
}
.kb-more:hover { background: rgba(var(--bs-primary-rgb), 0.16); }

/* Card */
.kb-card {
    --kb-prio: var(--bs-primary);
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    padding: 0.6rem 0.7rem 0.55rem 0.8rem;
    border-radius: 0.5rem;
    background: var(--bs-paper-bg, var(--bs-body-bg));
    border: 1px solid var(--bs-border-color);
    border-left: 3px solid var(--kb-prio);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    cursor: pointer;
    transition: box-shadow .15s, transform .15s, border-color .15s;
}
.kb-card:hover { box-shadow: 0 4px 14px rgba(0, 0, 0, 0.09); transform: translateY(-1px); }
.kb-card:focus-within { outline: 2px solid rgba(var(--bs-primary-rgb), 0.45); outline-offset: 1px; }
.kb-card.kb-prio--low    { --kb-prio: var(--bs-secondary); }
.kb-card.kb-prio--medium { --kb-prio: var(--bs-primary); }
.kb-card.kb-prio--high   { --kb-prio: var(--bs-warning); }
.kb-card.kb-prio--urgent { --kb-prio: var(--bs-danger); }
.kb-card.is-timer { border-color: rgba(var(--bs-success-rgb), 0.55); border-left-color: var(--kb-prio); }
.kb-card-project {
    display: block;
    font-size: 0.75rem;
    color: var(--bs-secondary-color);
    text-decoration: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.kb-card-project:hover { color: var(--bs-primary); }
.kb-card-pid { font-weight: 600; margin-right: 0.15rem; }
.kb-card-cno {
    display: inline-block;
    margin-right: 0.3rem;
    padding: 0 0.3rem;
    border-radius: 0.25rem;
    font-weight: 600;
    color: var(--bs-info);
    background: rgba(var(--bs-info-rgb), 0.12);
}
.kb-card-title {
    font-size: 0.875rem;
    font-weight: 600;
    line-height: 1.35;
    color: var(--bs-heading-color);
    text-decoration: none;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-break: break-word;
}
.kb-card-title:hover { color: var(--bs-primary); }
.kb-card-tags, .kb-card-foot {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem 0.5rem;
}
.kb-card-foot { flex-wrap: nowrap; }
.kb-prio-tag {
    font-size: 0.6875rem;
    font-weight: 600;
    padding: 0.05rem 0.4rem;
    border-radius: 0.25rem;
    color: var(--kb-prio);
    background: color-mix(in sRGB, var(--kb-prio) 13%, transparent);
}
.kb-kind { font-size: 0.6875rem; font-weight: 600; }
.kb-meta {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    color: var(--bs-secondary-color);
    white-space: nowrap;
}
.kb-progress {
    height: 4px;
    border-radius: 999px;
    background: rgba(var(--bs-secondary-rgb), 0.18);
    overflow: hidden;
}
.kb-progress-bar { height: 100%; background: var(--bs-primary); border-radius: inherit; }
.kb-col--completed .kb-progress-bar { background: var(--bs-success); }
.kb-due {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    white-space: nowrap;
    color: var(--bs-secondary-color);
}
.kb-due.is-overdue { color: var(--bs-danger); font-weight: 650; }
.kb-due.is-soon { color: var(--bs-warning); font-weight: 600; }
.kb-due.is-none { opacity: 0.7; }
.kb-timer { display: inline-flex; align-items: center; }
.kb-timer-dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    background: var(--bs-success);
    animation: kb-pulse 1.6s ease-in-out infinite;
}
@keyframes kb-pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(var(--bs-success-rgb), 0.55); }
    50% { box-shadow: 0 0 0 5px rgba(var(--bs-success-rgb), 0); }
}
@media (prefers-reduced-motion: reduce) {
    .kb-timer-dot { animation: none; }
    .kb-card, .kb-card:hover { transition: none; transform: none; }
}
.kb-assignees { display: inline-flex; align-items: center; margin-left: auto; }
.kb-unassigned { font-size: 0.75rem; color: var(--bs-secondary-color); white-space: nowrap; }

@media (max-width: 575.98px) {
    .kb-col { flex-basis: 85vw; min-width: 85vw; max-width: 85vw; max-height: none; }
    .kb-col-body { overflow-y: visible; }
    .kb-sort { min-width: 0; }
}

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
    department_id: <?= json_encode($overviewDefaultDepartmentId) ?>,
    department_ids: <?= json_encode(array_map('strval', $overviewMyDepartmentIds)) ?>,
    isProjectManager: <?= json_encode($_SESSION['isProjectManager'] ?? false) ?>,
    // 部署 filter: administrators see every department, others only their own
    isAdministrator: <?= json_encode(($_SESSION['authority'] ?? '') === 'administrator') ?>
};
</script>

<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js" crossorigin="anonymous"></script>
<script src="assets/js/task-overview.js?v=<?=STATS_CACHE_VERSION?>"></script>


