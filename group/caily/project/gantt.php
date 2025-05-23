<?php
require_once('../application/loader.php');
$view->heading('プロジェクト管理');
?>

<nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="#">Project Detail</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#projectNavbar">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="projectNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link" href="detail.php">Project Overview</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="task.php">Task</a>
        </li>
        <li class="nav-item">
          <a class="nav-link active" href="gantt.php">Gantt Chart</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="attachment.php">Attachment</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-4">
  <div id="gantt"></div>
</div>

<?php
$view->footing();
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/frappe-gantt/1.0.3/frappe-gantt.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/frappe-gantt/1.0.3/frappe-gantt.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tasks = [
        {
            id: 'Task 1',
            name: 'Market Research',
            start: '2025-04-01',
            end: '2025-04-15',
            progress: 50,
            dependencies: ''
        },
        {
            id: 'Task 2',
            name: 'Competitor Analysis',
            start: '2025-04-01',
            end: '2025-04-05',
            progress: 30,
            dependencies: ''
        },
        {
            id: 'Task 3',
            name: 'Product Design',
            start: '2025-04-06',
            end: '2025-04-20',
            progress: 20,
            dependencies: 'Task 1'
        }
    ];

    const gantt = new Gantt("#gantt", tasks, {
        view_mode: 'Week',
        date_format: 'YYYY-MM-DD',
        view_mode: 'Day',
        on_click: function(task) {
            console.log('Task clicked:', task);
        },
        on_date_change: function(task, start, end) {
            console.log('Task date changed:', task, start, end);
        },
        on_progress_change: function(task, progress) {
            console.log('Task progress changed:', task, progress);
        },
        on_view_change: function(mode) {
            console.log('View mode changed:', mode);
        }
    });
});
</script>
