// Sample tasks
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

// Initialize Gantt Chart
let gantt;

function updateTaskList() {
    const tbody = document.getElementById('taskList');
    tbody.innerHTML = '';
    
    tasks.forEach((task, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${index + 1}</td>
            <td>${task.name}</td>
            <td>${moment(task.start).format('YYYY-MM-DD')}</td>
            <td>${moment(task.end).format('YYYY-MM-DD')}</td>
            <td>
                <div class="progress-bar">
                    <div style="width: ${task.progress}%"></div>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize task list
    // updateTaskList();
    
    // Initialize Gantt chart
    gantt = new Gantt("#gantt", tasks, {
        view_mode: 'Day',
    });

    
});

