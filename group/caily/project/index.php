<?php

require_once('../application/loader.php');
$view->heading('プロジェクト管理');

?>

<body>
    <div class="container-fluid mt-4">
        <div class="card">
            <div class="card-body">
                <table id="projectTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Assignment</th>
                            <th>Manager</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>EstimatedTime</th>
                            <th>TimeTracked</th>
                            <th>StartDate</th>
                            <th>EndDate</th>
                            <th>ActualStartDate</th>
                            <th>ActualEndDate</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

   
 
</body>

<?php
$view->footing();
?>

<script>
    $(document).ready(function() {
        const projectData = [
            {
                "id": "P000000397",
                "name": "Dự án 03",
                "assignment": ["A1", "A2"],
                "manager": "M1",
                "priority": "P2",
                "status": "Completed",
                "progress": 100,
                "estimatedTime": "12h",
                "timeTracked": "8h",
                "startDate": "02/04/2024",
                "endDate": "02/07/2024",
                "actualStartDate": "02/05/2024",
                "actualEndDate": "02/05/2024"
            },
            {
                "id": "P000000396",
                "name": "Dự án quản lý thông tin hành chẩn",
                "assignment": ["A3", "A4"],
                "manager": "M2",
                "priority": "P2",
                "status": "Cancelled",
                "progress": 30,
                "estimatedTime": "24h",
                "timeTracked": "6h",
                "startDate": "02/04/2024",
                "endDate": "02/08/2024",
                "actualStartDate": "02/05/2024",
                "actualEndDate": "02/09/2024"
            }
        ];

        $('#projectTable').DataTable({
            data: projectData,
            columns: [
                { 
                    data: 'name',
                    render: function(data, type, row) {
                        return `<div class="d-flex align-items-center">
                                    <span class="project-id">${row.id}</span>
                                    <span>${data}</span>
                                </div>`;
                    }
                },
                {
                    data: 'assignment',
                    render: function(data) {
                        return `<div class="avatar-group">
                            ${data.map(initial => 
                                `<div class="project-avatar">${initial}</div>`
                            ).join('')}
                        </div>`;
                    }
                },
                {
                    data: 'manager',
                    render: function(data) {
                        return `<div class="project-avatar">${data}</div>`;
                    }
                },
                { data: 'priority' },
                {
                    data: 'status',
                    render: function(data) {
                        const colors = {
                            'Completed': 'success',
                            'Cancelled': 'danger',
                            'In Progress': 'primary',
                            'Pending': 'warning'
                        };
                        return `<span class="badge bg-${colors[data] || 'secondary'}">${data}</span>`;
                    }
                },
                {
                    data: 'progress',
                    render: function(data) {
                        const color = data >= 100 ? 'success' : data >= 50 ? 'primary' : 'warning';
                        return `<div class="progress">
                                    <div class="progress-bar bg-${color}" role="progressbar" 
                                            style="width: ${data}%" aria-valuenow="${data}" 
                                            aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                                <small class="text-muted">${data}%</small>`;
                    }
                },
                { data: 'estimatedTime' },
                { data: 'timeTracked' },
                { data: 'startDate' },
                { data: 'endDate' },
                { data: 'actualStartDate' },
                { data: 'actualEndDate' },
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