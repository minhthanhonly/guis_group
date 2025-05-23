<?php

require_once('../application/loader.php');
$view->heading('プロジェクト管理');

?>

    <div id="app">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item" v-for="category in categories" :key="category.id" :class="{ 'active': currentCategory === category.id }">
                            <a class="nav-link" href="#" @click.prevent="filterByCategory(category.id)">
                                {{ category.name }}
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="container-fluid mt-4">
            <div class="row">
                <!-- Project List -->
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>プロジェクト一覧</h2>
                        <button class="btn btn-primary" @click="showNewProjectModal = true" data-bs-toggle="modal" data-bs-target="#newProjectModal">
                            <i class="bi bi-plus"></i> <span>新規プロジェクト</span>
                        </button>
                    </div>

                    <div class="table-responsive text-nowrap">
                        <table id="projectTable" class="table table-striped" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên dự án</th>
                                    <th>Người phụ trách</th>
                                    <th>Trạng thái</th>
                                    <th>Tiến độ</th>
                                    <th>Thời gian dự kiến</th>
                                    <th>Thời gian thực tế</th>
                                    <th>Ngày bắt đầu</th>
                                    <th>Ngày kết thúc</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>

                
            </div>
        </div>

        <!-- New Project Modal -->
        <div class="modal fade" id="newProjectModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><span v-if="!isEdit">新規プロジェクト</span><span v-if="isEdit">プロジェクト編集</span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form @submit.prevent="saveProject">
                            <div class="mb-3">
                                <label class="form-label">お施主様名</label>
                                <input type="text" class="form-control" v-model="newProject.name" required>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">会社名</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">支店名</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">担当者名</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">建物規模</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">建物種類</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">案件番号</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">連絡番号</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">受注形態</label>
                                        <select class="form-select" required>
                                            <option value="新規">新規</option>
                                            <option value="修正">修正</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">カテゴリー</label>
                                        <select class="form-select" v-model="newProject.category_id" required readonly disabled>
                                            <option v-for="category in categories" :key="category.id" :value="category.id">
                                                {{ category.name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">開始日</label>
                                        <input type="text" class="form-control" v-model="newProject.start_date">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">終了日</label>
                                        <input type="text" class="form-control" v-model="newProject.end_date">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">GUIS担当者</label>
                                        <select class="form-select" v-model="newProject.members" multiple>
                                            <option v-for="user in users" :key="user.userid" :value="user.userid">
                                                {{ user.realname }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">メンバー</label>
                                        <select class="form-select" v-model="newProject.members" multiple>
                                            <option v-for="user in users" :key="user.userid" :value="user.userid">
                                                {{ user.realname }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">案件状況</label>
                                        <select class="form-select" v-model="newProject.status" required>
                                            <option value="draft">下書き</option>
                                            <option value="new">新規</option>
                                            <!-- <option value="in_progress">進行中</option>
                                            <option value="completed">完了</option>
                                            <option value="paused">一時停止</option>
                                            <option value="stop">停止</option> -->
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">優先度</label>
                                        <select class="form-select" required>
                                            <option value="1">低</option>
                                            <option value="2">中</option>
                                            <option value="3">高</option>
                                            <option value="4">緊急</option>
                                        </select>
                                    </div>
                                </div>
                                <!-- <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">受注状況</label>
                                        <select class="form-select" required>
                                            <option value=""></option>
                                            <option value="">見積まだ</option>
                                            <option value="">見積送付済み</option>
                                            <option value="">注文</option>
                                            <option value="">請求書送付済み</option>
                                            <option value="">支払済み</option>
                                        </select>
                                    </div>
                                </div> -->
                            </div>
                            <!-- <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">実納品日</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">請求書送付日</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                            </div> -->
                            <!-- <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">金額</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">予定時間</label>
                                        <input type="text" class="form-control">
                                    </div>
                                </div>
                            </div> -->
                            <div class="mb-3">
                                <label class="form-label">メモ</label>
                                <textarea class="form-control" v-model="newProject.description" rows="3"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="button" class="btn btn-primary" @click="saveProject">保存</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

   

<?php
$view->footing();
?>

<script>
$(document).ready(function() {
    // Dữ liệu JSON mẫu
    var data = [
        {
            "ID": "P00001",
            "Tên dự án": "Dự án quản lý thông tin bệnh nhân",
            "Người phụ trách": "Nguyễn Văn A",
            "Trạng thái": '<span class="badge bg-success">Completed</span>',
            "Tiến độ": '<div class="progress" style="height: 20px;"><div class="progress-bar bg-success" role="progressbar" style="width: 100%;">100%</div></div>',
            "Thời gian dự kiến": "120h",
            "Thời gian thực tế": "118h",
            "Ngày bắt đầu": "01/01/2024",
            "Ngày kết thúc": "02/06/2024"
        },
        {
            "ID": "P00002",
            "Tên dự án": "Dự án tiêu điện tiền cao",
            "Người phụ trách": "Trần Thị B",
            "Trạng thái": '<span class="badge bg-danger">Cancelled</span>',
            "Tiến độ": '<div class="progress" style="height: 20px;"><div class="progress-bar bg-danger" role="progressbar" style="width: 50%;">50%</div></div>',
            "Thời gian dự kiến": "100h",
            "Thời gian thực tế": "50h",
            "Ngày bắt đầu": "01/02/2024",
            "Ngày kết thúc": "02/07/2024"
        }
    ];

    // Khởi tạo DataTable
    $('#projectTable').DataTable({
        data: data,
        columns: [
            { data: 'ID' },
            { data: 'Tên dự án' },
            { data: 'Người phụ trách' },
            { data: 'Trạng thái' },
            { data: 'Tiến độ' },
            { data: 'Thời gian dự kiến' },
            { data: 'Thời gian thực tế' },
            { data: 'Ngày bắt đầu' },
            { data: 'Ngày kết thúc' }
        ],
        language: {
            //url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/ja.json'
        },
        paging: false,
        searching: false,
        info: false
    });
});
</script>
