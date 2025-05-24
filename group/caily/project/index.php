<?php

require_once('../application/loader.php');
$view->heading('プロジェクト管理');

?>
<div id="app"  class="container-fluid mt-4" v-cloak>
    <nav class="navbar navbar-expand-lg bg-dark mb-12">
        <div class="container-fluid">
            <span class="navbar-brand" href="javascript:void(0)"></span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-start" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item" v-for="department in departments" :key="department.id" :class="{ 'active bg-primary text-white rounded-3': selectedDepartment && selectedDepartment.id === department.id, 'd-none': department.can_project == 0 }">
                        <a href="#" class="nav-link" @click="viewProjects(department)" >{{ department.name }}</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="card">
        <ul class="list-group list-group-flush">
            <li class="list-group-item" v-for="status in statuses" :key="status.id">
                <a href="#" class="nav-link" @click="filterProjectByStatus(status)" >{{ status.name }}</a>
            </li>
        </ul>
        <div class="card-body">
            <table id="projectTable" class="table table-striped">
                
            </table>
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
    var projectTable;
    var projectData = [];
    var statuses = [
        {
            key: 'all',
            name: 'All',
            color: 'secondary'
        },
        {
            key: 'open',
            name: 'Open',
            color: 'primary'
        },
        {
            key: 'in_progress',
            name: 'In Progress',
            color: 'primary'
        },
        {
            key: 'paused',
            name: 'Paused',
            color: 'warning'
        },
        {
            key: 'completed',
            name: 'Completed',
            color: 'success'
        },
        {
            key: 'cancelled',
            name: 'Cancelled',
            color: 'danger'
        },
        {
            key: 'stopped',
            name: 'Stopped',
            color: 'danger'
        },
        {
            key: 'draft',
            name: 'Draft',
            color: 'secondary'
        },
    ];
    var priorities = [
        {
            key: 'low',
            name: 'Low',
            color: 'secondary'
        },
        {
            key: 'medium',
            name: 'Medium',
            color: 'primary'
        },
        {
            key: 'high',
            name: 'High',
            color: 'warning'
        },
        {
            key: 'urgent',
            name: 'Urgent',
            color: 'danger'
        },
    ];
    $(document).ready(function() {
        projectData = [
        ];

        projectTable = $('#projectTable').DataTable({
            data: projectData,
            paging: false,
            info: false,
            searching: false,
            scrollX: true,
            columns: [
                { 
                    data: 'project_number',
                    render: function(data, type, row) {
                        return `<div class="d-flex align-items-center">
                                    <span class="project-id">${row.project_number}</span>
                                </div>`;
                    },
                    title: '案件番号'
                },
                { 
                    data: 'name',
                    render: function(data, type, row) {
                        return `<div class="d-flex align-items-center">
                                    <span>${data}</span>
                                </div>`;
                    },
                    title: 'お施主様名'
                },
                { 
                    data: 'building_type',
                    render: function(data, type, row) {
                        return `<div class="d-flex align-items-center">
                                    <span class="project-type">${row.building_type}</span>
                                </div>`;
                    },
                    title: '建物種類'
                },
                { 
                    data: 'building_size',
                    render: function(data, type, row) {
                        return `<div class="d-flex align-items-center">
                                    <span class="project-type">${row.building_size}</span>
                                </div>`;
                    },
                    title: '建物規模'
                },
                {
                    data: 'assignment_id',
                    render: function(data) {
                        // return `<div class="avatar-group">
                        //     ${data.map(initial => 
                        //         `<div class="project-avatar">${initial}</div>`
                        //     ).join('')}
                        // </div>`;
                        return data;
                    },
                    title: '担当者'
                },
                {
                    data: 'manager_id',
                    render: function(data) {
                        return `<div class="project-avatar">${data}</div>`;
                    },
                    title: '担当者'
                },
                {
                    data: 'priority',
                    render: function(data) {
                        const priority = priorities.find(priority => priority.key === data);
                        return `<span class="badge bg-${priority.color || 'secondary'}">${priority.name}</span>`;
                    },
                    title: '優先度'
                },
                {
                    data: 'status',
                    render: function(data) {
                        const status = statuses.find(status => status.key === data);
                        return `<span class="badge bg-${status.color || 'secondary'}">${status.name}</span>`;
                    },
                    title: '案件状況'
                },
                {
                    data: 'progress',
                    render: function(data) {
                        const color = 'primary';
                        return `<div class="progress">
                                    <div class="progress-bar bg-${color}" role="progressbar" 
                                            style="width: ${data}%" aria-valuenow="${data}" 
                                            aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                                <small class="text-muted">${data}%</small>`;
                    },
                    title: '進捗率'
                },
                // { data: 'estimated_hours', title: '予定時間' },
                // { data: 'actual_hours', title: '実績時間' },
                { data: 'start_date', title: '開始日', render: function(data) {
                    if(data) {
                        return moment(data).format('YYYY/MM/DD');
                    } else {
                        return '-';
                    }
                }},
                { data: 'end_date', title: '終了日', render: function(data) {
                    if(data) {
                        return moment(data).format('YYYY/MM/DD');
                    } else {
                        return '-';
                    }
                }},
                // { data: 'actual_start_date', title: '実開始日', render: function(data) {
                //     if(data) {
                //         return moment(data).format('YYYY/MM/DD');
                //     } else {
                //         return '-';
                //     }
                // }},
                // { data: 'actual_end_date', title: '実終了日', render: function(data) {
                //     if(data) {
                //         return moment(data).format('YYYY/MM/DD');
                //     } else {
                //         return '-';
                //     }
                // }},
                {
                    data: null,
                    render: function(data, type, row) {
                        return `<div class="d-flex align-items-center">
                                    <button class="btn btn-primary item-edit" data-id="${row.id}">編集</button>
                                    <button class="btn btn-danger item-delete" data-id="${row.id}">削除</button>
                                </div>`;
                    },
                    title: '操作'
                }
            ],
           
            pageLength: 10,
            ordering: true,
            responsive: true,
            language: {
                search: "検索:",
                lengthMenu: "_MENU_ 件表示",
                info: " _TOTAL_ 件中 _START_ から _END_ まで表示",
                paginate: {
                    first: "先頭",
                    previous: "前",
                    next: "次",
                    last: "最終"
                }
            }
        });
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script>
    const { createApp } = Vue;
    createApp({
        data() {
            return {
                selectedDepartment: null,
                projects: [],
                departments: [],
                selectedStatus: null,
                statuses: statuses,
                newProject: {
                    name: '',
                    description: '',
                    status: 'draft',
                    priority: 'low',
                    start_date: '',
                    end_date: '',
                    assignment_id: '',
                    manager_id: '',
                    viewer_id: '',
                    members: '',
                    department_id: '',
                    building_size: '',
                    building_type: '',
                    project_number: '',
                    project_order_type: '',
                    project_estimate_id: '',
                    amount: '',
                    customer_id: '',
                },
                categories: [],
                users: [],
                isEdit: false,
                perPage: 20,
                currentPage: 1,
            }
        },
        mounted() {
            this.loadDepartments();
        },
        methods: {
            async loadDepartments() {
                try {
                    const response = await axios.get('/api/index.php?model=department&method=list');
                    this.departments = response.data;
                    if(!this.selectedDepartment) {
                        this.selectedStatus = this.statuses[0];
                        this.viewProjects(this.departments[0]);
                    }
                } catch (error) {
                    console.error('Error loading departments:', error);
                    showMessage('部署の読み込みに失敗しました。', true);
                }
            },
            viewProjects(department) {
                this.selectedDepartment = department;
                this.loadProjects();
            },
            filterProjectByStatus(status) {
                this.selectedStatus = status;
                this.loadProjects();
            },
            loadProjects() {
                axios.get('/api/index.php?model=project&method=list&department_id=' + this.selectedDepartment.id + '&status=' + this.selectedStatus.key + '&perPage=' + this.perPage + '&currentPage=' + this.currentPage)
                    .then(response => {
                        this.projects = response.data;
                        projectData = this.projects;
                        projectTable.clear();
                        projectTable.rows.add(projectData);
                        projectTable.draw();
                    })
                    .catch(error => console.error('Error loading projects:', error));
            },
            saveProject(){
                console.log(this.newProject);
            }
        },
    }).mount('#app');
</script>