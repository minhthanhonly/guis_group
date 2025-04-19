document.addEventListener('DOMContentLoaded', function () {
    const specTable = document.querySelector('.datatables-spec');

    if (specTable) {
        function renderUploadedFiles(filesJson) {
            const files = JSON.parse(filesJson || '[]');
            return files.map(file => `<a href="${file}" target="_blank">${file.split('/').pop()}</a>`).join('<br>');
        }

        const dt_table = new DataTable(specTable, {
            paging: true,
            searching: true,
            info: true,
            ordering: true,
            columns: [
                { data: 'id', title: 'ID' },
                { data: 'name', title: 'Tên yêu cầu' },
                { data: 'company_name', title: 'Công ty' },
                { data: 'text', title: 'Nội dung' },
                { data: 'files', title: 'Files đính kèm', render: renderUploadedFiles },
                { data: 'actions', title: 'Hành động', orderable: false }
            ],
            language: {
                zeroRecords: 'Không có dữ liệu',
                emptyTable: 'Không có dữ liệu',
            }
        });

        function fetchSpecifications() {
            fetch('/api/index.php?model=specification&method=listSpecifications')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        dt_table.clear().rows.add(data.list).draw();
                    } else {
                        alert('Không thể tải dữ liệu yêu cầu!');
                    }
                });
        }

        fetchSpecifications();

        document.querySelector('#formAddNewSpec').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('/api/index.php?model=specification&method=addSpecification', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Thêm yêu cầu thành công!');
                        fetchSpecifications();
                        const addModal = bootstrap.Modal.getInstance(document.getElementById('addSpecModal'));
                        if (addModal) {
                            addModal.hide();
                        }
                    } else {
                        alert('Thêm yêu cầu thất bại!');
                    }
                });
        });

        document.querySelector('#formEditSpec').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('/api/index.php?model=specification&method=editSpecification', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Cập nhật yêu cầu thành công!');
                        fetchSpecifications();
                        const editModal = bootstrap.Modal.getInstance(document.getElementById('editSpecModal'));
                        if (editModal) {
                            editModal.hide();
                        }
                    } else {
                        alert('Cập nhật yêu cầu thất bại!');
                    }
                });
        });

        specTable.addEventListener('click', function (e) {
            if (e.target.closest('.delete-button')) {
                const specId = e.target.dataset.id;
                if (confirm('Bạn có chắc chắn muốn xóa yêu cầu này?')) {
                    fetch(`/api/index.php?model=specification&method=deleteSpecification&id=${specId}`, {
                        method: 'DELETE'
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Xóa thành công!');
                                fetchSpecifications();
                            } else {
                                alert('Xóa thất bại!');
                            }
                        });
                }
            }

            if (e.target.closest('.edit-button')) {
                const specId = e.target.dataset.id;
                fetch(`/api/index.php?model=specification&method=getSpecification&id=${specId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const spec = data.specification;
                            document.querySelector('#modalEditSpecID').value = spec.id;
                            document.querySelector('#modalEditSpecName').value = spec.name;
                            document.querySelector('#modalEditSpecCompany').value = spec.company_id;
                            document.querySelector('#modalEditSpecText').value = spec.text;

                            const editModal = new bootstrap.Modal(document.getElementById('editSpecModal'));
                            editModal.show();
                        } else {
                            alert('Không thể tải dữ liệu yêu cầu!');
                        }
                    });
            }
        });
    }
});