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
                <i class="fa fa-arrow-left"></i> 戻る
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
                                <i class="fa fa-pencil-alt"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-sm" @click="deleteProject">
                                <i class="fa fa-trash"></i>
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
                            <label class="form-label">工事番号</label>
                            <input type="text" class="form-control" :value="formatDate(project.start_date)" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">開始日</label>
                            <input type="text" class="form-control" :value="formatDate(project.start_date)" readonly>
                        </div><div class="col-md-6">
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
                            <label class="form-label">部署</label>
                            <input type="text" class="form-control" :value="department?.name || '-'" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">建物規模</label>
                            <input type="text" class="form-control" :value="project.building_size || '-'" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">建物種類</label>
                            <input type="text" class="form-control" :value="project.building_type || '-'" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ステータス</label>
                            <div>
                                <div class="btn-group">
                                    <button type="button" class="btn dropdown-toggle waves-effect waves-light" 
                                            :class="getStatusButtonClass(project.status)"
                                            id="statusDropdown"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                        {{ getStatusLabel(project.status) }}
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li v-for="status in statuses" :key="status.value">
                                            <a class="dropdown-item waves-effect" href="javascript:void(0);" 
                                            @click="selectStatus(status.value)">
                                                {{ status.label }}
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">優先度</label>
                            <div>
                                <div class="btn-group">
                                    <button type="button" class="btn dropdown-toggle waves-effect waves-light" 
                                            :class="getPriorityButtonClass(project.priority)"
                                            id="priorityDropdown"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                        {{ getPriorityLabel(project.priority) }}
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li v-for="priority in priorities" :key="priority.value">
                                            <a class="dropdown-item waves-effect" href="javascript:void(0);" 
                                               @click="selectPriority(priority.value)">
                                                {{ priority.label }}
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- <div class="col-md-4">
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
                        </div> -->
                        <div class="col-6">
                            <label class="form-label">担当者</label>
                            <div class="d-flex align-items-center my-4" v-if="managers && managers.length > 0">
                                <div v-for="member in managers" :key="member.user_id"
                                    class="avatar me-2 avatar-online"
                                    data-bs-toggle="tooltip"
                                    data-popup="tooltip-custom"
                                    data-bs-placement="top"
                                    :aria-label="member.user_name"
                                    :data-bs-original-title="member.user_name">
                                    <img v-if="!member.avatarError" class="rounded-circle" :src="getAvatarSrc(member)" :alt="member.user_name" @error="handleAvatarError(member)">
                                    <span v-else class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(member.user_name) }}</span>
                                </div>
                            </div>
                            <div v-else class="text-muted">
                                メンバーがいません
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">プロジェクトメンバー</label>
                            <div class="d-flex align-items-center my-4" v-if="members.length > 0">
                                <div v-for="member in members" :key="member.user_id"
                                    class="avatar me-2 avatar-online"
                                    data-bs-toggle="tooltip"
                                    data-popup="tooltip-custom"
                                    data-bs-placement="top"
                                    :aria-label="member.user_name"
                                    :data-bs-original-title="member.user_name">
                                    <img v-if="!member.avatarError" class="rounded-circle" :src="getAvatarSrc(member)" :alt="member.user_name" @error="handleAvatarError(member)">
                                    <span v-else class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(member.user_name) }}</span>
                                </div>
                            </div>
                            <div v-else class="text-muted">
                                メンバーがいません
                            </div>
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

        </div>

        <!-- Right Column - Stats & Comments -->
        <div class="col-md-4">
            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6">
                    <div class="card bg-primary text-white text-center">
                        <div class="card-body">
                            <div class="mb-1">
                                <i class="fa fa-list-alt fs-3"></i>
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
                                <i class="fa fa-check-circle fs-3"></i>
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
                                <i class="fa fa-clock fs-3"></i>
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
                                <i class="fa fa-calendar fs-3"></i>
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
                            <i class="fa fa-comment-dots fs-1 text-muted"></i>
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
                                    <i class="fa fa-paper-plane"></i>
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

<!-- Define PROJECT_ID before loading Vue and project-detail.js -->
<script>
const PROJECT_ID = <?php echo $project_id; ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="assets/js/project-detail.js"></script>
