<?php
require_once('../application/loader.php');
$view->heading('タスク一覧');
?>

<div id="app" class="container-fluid mt-4" v-cloak>
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="fa fa-tasks me-2"></i>全タスク一覧
            </h4>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-3">
        <div class="col-md-3 mb-2">
            <label class="form-label">部署</label>
            <select class="form-select" v-model="filters.department_id" @change="onDepartmentChange">
                <option value="">すべて</option>
                <option v-for="dep in departments" :key="dep.id" :value="dep.id">{{ dep.name }}</option>
            </select>
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label">チーム</label>
            <select class="form-select" v-model="filters.team_id" @change="loadOverview">
                <option value="">すべて</option>
                <option v-for="team in filteredTeams" :key="team.id" :value="team.id">{{ team.name }}</option>
            </select>
        </div>
        <div class="col-md-3 mb-2">
            <label class="form-label">ユーザー</label>
            <select class="form-select" v-model="filters.user_id" @change="onUserChange">
                <option value="">すべて</option>
                <option v-for="user in filteredUsers" :key="user.id" :value="user.id">{{ user.realname }}</option>
            </select>
        </div>
        <div class="col-md-3 mb-2 d-flex flex-column justify-content-end">
            <div class="form-check mb-1">
                <input class="form-check-input" type="checkbox" id="excludeCompleted"
                       v-model="filters.excludeCompleted" @change="loadOverview">
                <label class="form-check-label" for="excludeCompleted">完了タスクを除外</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="myTask"
                       v-model="filters.myTask" @change="onMyTaskChange">
                <label class="form-check-label" for="myTask">自分のタスク</label>
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
                        <i class="fa fa-list me-1"></i>タスク一覧
                    </button>
                </li>
                <?php if($_SESSION['isProjectManager']): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" :class="{ active: activeTab === 'unassigned' }"
                                @click="activeTab = 'unassigned'" type="button">
                                <i class="fa fa-user-times me-1"></i>タスク未割り当てユーザー
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
                    <h5 class="card-title mb-0">タスク一覧</h5>
                    <button class="btn btn-sm btn-outline-secondary" @click="loadOverview" :disabled="loading">
                        <i class="fa fa-refresh me-1"></i>更新
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
                        <p class="mb-0">タスクがありません</p>
                    </div>
                    <div v-else class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>案件</th>
                                    <th>タスク</th>
                                    <th>担当者</th>
                                    <th>ステータス</th>
                                    <th>優先度</th>
                                    <th>進捗</th>
                                    <th>期間</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="task in filteredTasks" :key="task.id">
                                    <td>
                                        <a :href="`detail.php?id=${task.project_id}`" class="text-decoration-none">
                                            <span class="badge bg-label-primary me-1">#{{ task.project_number }}</span>
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
                                        {{ getAssigneeNames(task) }}
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
                                        <small class="text-muted">
                                            {{ formatDate(task.start_date) }} ～ {{ formatDate(task.due_date) }}
                                        </small>
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
                    <h5 class="card-title mb-0">タスク未割り当てユーザー</h5>
                </div>
                <div class="card-body">
                    <div v-if="unassignedUsers.length === 0" class="text-muted">すべてのユーザーにタスクが割り当てられています。</div>
                    <div v-else class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>部署</th>
                                    <th>チーム</th>
                                    <th>ユーザー</th>
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

<?php
$view->footing();
?>

<script>
// Pass current user data to JavaScript
window.currentUser = {
    id: <?= json_encode($_SESSION['userid'] ?? '') ?>,
    department_id: <?= json_encode($_SESSION['department_id'] ?? '') ?>,
    isProjectManager: <?= json_encode($_SESSION['isProjectManager'] ?? false) ?>
};
</script>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="assets/js/task-overview.js?v=<?=CACHE_VERSION?>"></script>


