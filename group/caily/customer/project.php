<?php

require_once('../application/loader.php');
$view->heading('Quản lý dự án');

?>
<!-- Content -->
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="card mb-6">
        <div class="card-header d-flex justify-content-between">
            <h5 class="card-title mb-0">Danh sách dự án</h5>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProjectModal">Thêm dự án</button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="datatables-projects table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Mã dự án</th>
                            <th>Địa chỉ</th>
                            <th>Tags</th>
                            <th>Ghi chú</th>
                            <th>Specifications</th>
                            <th>Công số dự tính</th>
                            <th>Công số thực hiện</th>
                            <th>Người thực hiện</th>
                            <th>Người chịu trách nhiệm</th>
                            <th>Nhóm có thể xem</th>
                            <th>Trạng thái</th>
                            <th>% Tiến độ</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dữ liệu dự án sẽ được hiển thị ở đây -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Project Modal -->
<div class="modal fade" id="addProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-simple modal-dialog-centered modal-add-new-project">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="text-center mb-6">
                    <h4 class="role-title">Thêm dự án</h4>
                </div>
                <form id="formAddNewProject" class="row g-3">
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddCode">Mã dự án</label>
                        <input type="text" id="modalAddCode" name="code" class="form-control" placeholder="Nhập mã dự án" autofocus required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddAddress">Địa chỉ</label>
                        <input type="text" id="modalAddAddress" name="address" class="form-control" placeholder="Nhập địa chỉ" required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddTags">Tags</label>
                        <input type="text" id="modalAddTags" name="tags" class="form-control" placeholder="Nhập tags" required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddNotes">Ghi chú</label>
                        <textarea id="modalAddNotes" name="notes" class="form-control" placeholder="Nhập ghi chú" required></textarea>
                    </div>
                    <div class="col-12 text-center">
                        <button id="submitAddProject" type="submit" class="btn btn-primary me-sm-4 me-1">Submit</button>
                        <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Project Modal -->
<div class="modal fade" id="editProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-simple modal-dialog-centered modal-edit-project">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="text-center mb-6">
                    <h4 class="role-title">Sửa dự án</h4>
                </div>
                <form id="formEditProject" class="row g-3">
                    <input type="hidden" name="id" id="modalEditID" />
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditCode">Mã dự án</label>
                        <input type="text" id="modalEditCode" name="code" class="form-control" placeholder="Nhập mã dự án" required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditAddress">Địa chỉ</label>
                        <input type="text" id="modalEditAddress" name="address" class="form-control" placeholder="Nhập địa chỉ" required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditTags">Tags</label>
                        <input type="text" id="modalEditTags" name="tags" class="form-control" placeholder="Nhập tags" required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditNotes">Ghi chú</label>
                        <textarea id="modalEditNotes" name="notes" class="form-control" placeholder="Nhập ghi chú" required></textarea>
                    </div>
                    <div class="col-12 text-center">
                        <button id="submitEditProject" type="submit" class="btn btn-primary me-sm-4 me-1">Submit</button>
                        <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$view->footing();
?>
<script src="<?=ROOT?>assets/js/projects.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Ensure the DataTable reflects the updated schema
        const projectTable = document.querySelector('.datatables-projects');
        if (projectTable) {
            const dt_table = new DataTable(projectTable, {
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
                ]
            });
        }

        projectTable.addEventListener('click', function (e) {
            if (e.target.closest('.delete-button')) {
                const projectId = e.target.dataset.id;
                if (confirm('Bạn có chắc chắn muốn xóa dự án này?')) {
                    fetch(`/api/index.php?model=project&method=delete&id=${projectId}`, {
                        method: 'DELETE'
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Xóa thành công!');
                                location.reload();
                            } else {
                                alert('Xóa thất bại!');
                            }
                        });
                }
            }

            if (e.target.closest('.edit-button')) {
                const projectId = e.target.dataset.id;
                fetch(`/api/index.php?model=project&method=get&id=${projectId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const project = data.project;
                            document.querySelector('#modalEditID').value = project.id;
                            document.querySelector('#modalEditCode').value = project.code;
                            document.querySelector('#modalEditAddress').value = project.address;
                            document.querySelector('#modalEditTags').value = project.tags;
                            document.querySelector('#modalEditNotes').value = project.notes;

                            const editModal = new bootstrap.Modal(document.getElementById('editProjectModal'));
                            editModal.show();
                        } else {
                            alert('Không thể tải dữ liệu dự án!');
                        }
                    });
            }
        });

        document.querySelector('#formEditProject').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('/api/index.php?model=project&method=edit', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Cập nhật dự án thành công!');
                        location.reload();
                    } else {
                        alert('Cập nhật dự án thất bại!');
                    }
                });
        });
    });
</script>