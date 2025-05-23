<?php
require_once('../application/loader.php');
$view->heading('プロジェクト管理');
?>

<nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="#">Project Detail</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#projectNavbar" aria-controls="projectNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="projectNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link active" aria-current="page" href="#">Project Overview</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="task.php">Task</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="gantt.php">Gantt Chart</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="attachment.php">Attachment</a>
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
                <i class="ti ti-arrow-left"></i> Back
            </a>
        </div>

        <!-- Left Column - Project Details -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title">General Information</h5>
                        <div>
                            <button class="btn btn-outline-primary btn-sm me-2">
                                <i class="ti ti-pencil"></i>
                            </button>
                            <button class="btn btn-outline-primary btn-sm">
                                <i class="ti ti-download"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Project ID</label>
                            <input type="text" class="form-control" v-model="project.id" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Project Name</label>
                            <input type="text" class="form-control" v-model="project.name" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="text" class="form-control" v-model="project.startDate" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date</label>
                            <input type="text" class="form-control" v-model="project.endDate" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Actual Start Date</label>
                            <input type="text" class="form-control" v-model="project.actualStartDate" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Actual End Date</label>
                            <input type="text" class="form-control" v-model="project.actualEndDate" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <div>
                                <span class="badge bg-success">{{ project.status }}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Priority</label>
                            <div>
                                <span class="badge bg-info">{{ project.priority }}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Manager</label>
                            <input type="text" class="form-control" v-model="project.manager" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assignee</label>
                            <div class="avatar-group">
                                <div v-for="user in project.assignee" :key="user.name" 
                                     class="avatar avatar-sm" :title="user.name">
                                    <span class="avatar-initial rounded-circle bg-primary">
                                        {{ user.name.charAt(0) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Estimated Time</label>
                            <input type="text" class="form-control" v-model="project.estimatedTime" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Time Tracked</label>
                            <input type="text" class="form-control" v-model="project.timeTracked" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expected Time</label>
                            <input type="text" class="form-control" v-model="project.expectedTime" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Progress</label>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar" role="progressbar" 
                                     :style="{ width: project.progress + '%' }"
                                     :aria-valuenow="project.progress" 
                                     aria-valuemin="0" aria-valuemax="100">
                                </div>
                            </div>
                            <small class="text-muted">{{ project.progress }}%</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" rows="3" v-model="project.description" readonly></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Stats & Comments -->
        <div class="col-md-4">
            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-4">
                    <div class="card bg-primary text-white text-center">
                        <div class="card-body">
                            <div class="mb-1">
                                <i class="ti ti-list fs-3"></i>
                            </div>
                            <h2 class="mb-1">{{ project.stats.totalTasks }}</h2>
                            <span>Total task</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-4">
                    <div class="card bg-success text-white text-center">
                        <div class="card-body">
                            <div class="mb-1">
                                <i class="ti ti-chart-pie fs-3"></i>
                            </div>
                            <h2 class="mb-1">{{ project.stats.progress }}%</h2>
                            <span>Progress</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-4">
                    <div class="card bg-info text-white text-center">
                        <div class="card-body">
                            <div class="mb-1">
                                <i class="ti ti-clock fs-3"></i>
                            </div>
                            <h2 class="mb-1">{{ project.stats.timeTracked }}</h2>
                            <span>Time Tracked</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-6">
                    <div class="card bg-secondary text-white text-center">
                        <div class="card-body">
                            <div class="mb-1">
                                <i class="ti ti-calendar fs-3"></i>
                            </div>
                            <h2 class="mb-1">{{ project.stats.actualTotalDays }}</h2>
                            <span>Actual Total day</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-6">
                    <div class="card bg-warning text-white text-center">
                        <div class="card-body">
                            <div class="mb-1">
                                <i class="ti ti-paperclip fs-3"></i>
                            </div>
                            <h2 class="mb-1">{{ project.stats.attachments }}</h2>
                            <span>Attachment</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Comments Section -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Discussion</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="text-center py-5" v-if="comments.length === 0">
                            <img src="/assets/img/illustrations/mail.png" alt="No comments" 
                                 class="mb-3" style="width: 100px;">
                            <p class="text-muted">No comments yet</p>
                        </div>
                        <!-- Comments List -->
                        <div v-else class="comment-list mb-4">
                            <div v-for="comment in comments" :key="comment.id" class="comment-item mb-3">
                                <div class="d-flex gap-3">
                                    <div class="flex-shrink-0">
                                        <div class="avatar avatar-sm">
                                            <span class="avatar-initial rounded-circle" 
                                                  :class="'bg-' + comment.userColor">
                                                {{ comment.userName.charAt(0) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="comment-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0">{{ comment.userName }}</h6>
                                            <small class="text-muted">{{ formatDate(comment.timestamp) }}</small>
                                        </div>
                                        <div class="comment-content">
                                            {{ comment.content }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Comment Input -->
                    <div class="d-flex gap-3">
                        <div class="flex-shrink-0">
                            <div class="avatar avatar-sm">
                                <span class="avatar-initial rounded-circle bg-primary">
                                    {{ currentUser.name.charAt(0) }}
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <div class="input-group">
                                <input type="text" class="form-control" 
                                       v-model="newComment" 
                                       @keyup.enter="addComment"
                                       placeholder="Write a comment...">
                                <button class="btn btn-primary" 
                                        @click="addComment"
                                        :disabled="!newComment.trim()">
                                    <i class="ti ti-send"></i>
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
.progress {
    height: 6px;
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
.input-group .btn {
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script>
const { createApp } = Vue;

createApp({
    data() {
        return {
            project: {
                "id": "PD0000264",
                "name": "Dự án tuyển sinh TLU",
                "startDate": "2/1/2024",
                "endDate": "2/22/2024",
                "actualStartDate": "",
                "actualEndDate": "",
                "status": "Open",
                "priority": "Normal",
                "manager": "Tong Cong Tu",
                "assignee": [{
                    "name": "Nguyen Dac Son",
                    "avatar": "/assets/img/avatars/1.png"
                }, {
                    "name": "Pham Duc Thuy",
                    "avatar": "/assets/img/avatars/2.png"
                }, {
                    "name": "Pham Minh Tien",
                    "avatar": "/assets/img/avatars/3.png"
                }],
                "estimatedTime": "120",
                "timeTracked": "2",
                "expectedTime": "128",
                "progress": 0,
                "description": "Dự án tuyển sinh TLU",
                "stats": {
                    "totalTasks": 0,
                    "progress": 0,
                    "timeTracked": 2,
                    "actualTotalDays": 7,
                    "attachments": 1
                }
            },
            comments: [],
            newComment: '',
            currentUser: {
                id: 1,
                name: 'Current User',
                color: 'primary'
            }
        }
    },
    methods: {
        formatDate(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diffTime = Math.abs(now - date);
            const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays === 0) {
                const hours = date.getHours().toString().padStart(2, '0');
                const minutes = date.getMinutes().toString().padStart(2, '0');
                return `Today at ${hours}:${minutes}`;
            } else if (diffDays === 1) {
                return 'Yesterday';
            } else {
                return date.toLocaleDateString('ja-JP');
            }
        },
        addComment() {
            if (!this.newComment.trim()) return;
            
            const comment = {
                id: Date.now(),
                userId: this.currentUser.id,
                userName: this.currentUser.name,
                userColor: this.currentUser.color,
                content: this.newComment.trim(),
                timestamp: new Date().toISOString()
            };
            
            // Add comment to the list
            this.comments.push(comment);
            
            // Clear input
            this.newComment = '';
            
            // Optional: Save to backend
            this.saveComment(comment);
        },
        async saveComment(comment) {
            try {
                // Example API call - replace with your actual API endpoint
                await fetch('/api/index.php?model=project&method=addComment', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        project_id: this.project.id,
                        comment: comment
                    })
                });
            } catch (error) {
                console.error('Error saving comment:', error);
            }
        },
        async loadComments() {
            try {
                // Example API call - replace with your actual API endpoint
                const response = await fetch(`/api/index.php?model=project&method=getComments&id=${this.project.id}`);
                const data = await response.json();
                this.comments = data.comments || [];
            } catch (error) {
                console.error('Error loading comments:', error);
            }
        }
    },
    mounted() {
        // Load existing comments when component mounts
        this.loadComments();
    }
}).mount('#app');
</script>
