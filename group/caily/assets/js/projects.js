document.addEventListener('DOMContentLoaded', function () {
    const projectTable = document.querySelector('.datatables-projects');

    if (projectTable) {
        const dt_table = new DataTable(projectTable, {
            paging: true,
            searching: true,
            info: true,
            ordering: true,
            columns: [
                { data: 'id', title: 'ID' },
                { data: 'code', title: 'Mã dự án' },
                { data: 'address', title: 'Địa chỉ' },
                { data: 'tags', title: 'Tags' },
                { data: 'notes', title: 'Ghi chú' },
                { data: 'specifications', title: 'Specifications' },
                { data: 'estimated_hours', title: 'Công số dự tính', type: 'float' },
                { data: 'actual_hours', title: 'Công số thực hiện', type: 'float' },
                { data: 'assignees', title: 'Người thực hiện' },
                { data: 'responsible_person', title: 'Người chịu trách nhiệm' },
                { data: 'viewable_groups', title: 'Nhóm có thể xem' },
                { data: 'status', title: 'Trạng thái' },
                { data: 'progress', title: '% Tiến độ' },
                { data: 'actions', title: 'Hành động', orderable: false }
            ],
            language: {
                zeroRecords: 'Không có dữ liệu',
                emptyTable: 'Không có dữ liệu',
            }
        });

        function fetchProjects() {
            fetch('/api/index.php?model=project&method=list')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        dt_table.clear().rows.add(data.list).draw();
                    } else {
                        alert('Không thể tải dữ liệu dự án!');
                    }
                });
        }

        fetchProjects();

        document.querySelector('#formAddNewProject').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('/api/index.php?model=project&method=add', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Thêm dự án thành công!');
                        fetchProjects();
                        const addModal = bootstrap.Modal.getInstance(document.getElementById('addProjectModal'));
                        if (addModal) {
                            addModal.hide();
                        }
                    } else {
                        alert('Thêm dự án thất bại!');
                    }
                });
        });
    }
});