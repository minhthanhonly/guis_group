<?php

require_once('../application/loader.php');
$view->heading('Quản lý yêu cầu kỹ thuật');

?>
<!-- Content -->
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="card mb-6">
        <div class="card-header d-flex justify-content-between">
            <h5 class="card-title mb-0">Danh sách yêu cầu kỹ thuật</h5>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSpecModal">Thêm yêu cầu</button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="datatables-spec table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên yêu cầu</th>
                            <th>Công ty</th>
                            <th>Nội dung</th>
                            <th>File đính kèm</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dữ liệu yêu cầu sẽ được hiển thị ở đây -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Specification Modal -->
<div class="modal fade" id="addSpecModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-simple modal-dialog-centered modal-add-new-spec">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="text-center mb-6">
                    <h4 class="role-title">Thêm yêu cầu kỹ thuật</h4>
                </div>
                <form id="formAddNewSpec" class="row g-3">
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddSpecName">Tên yêu cầu</label>
                        <input type="text" id="modalAddSpecName" name="name" class="form-control" placeholder="Nhập tên yêu cầu" required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddSpecCompany">Công ty</label>
                        <select id="modalAddSpecCompany" name="company" class="form-control" required>
                            <!-- Danh sách công ty sẽ được tải động -->
                        </select>
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddSpecText">Nội dung</label>
                        <textarea id="modalAddSpecText" name="text" class="form-control" placeholder="Nhập nội dung yêu cầu" required></textarea>
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddSpecFiles">Files đính kèm</label>
                        <input type="file" id="modalAddSpecFiles" name="files[]" class="form-control" multiple />
                    </div>
                    <div class="col-12 text-center">
                        <button id="submitAddSpec" type="submit" class="btn btn-primary me-sm-4 me-1">Submit</button>
                        <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Specification Modal -->
<div class="modal fade" id="editSpecModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-simple modal-dialog-centered modal-edit-spec">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="text-center mb-6">
                    <h4 class="role-title">Sửa yêu cầu kỹ thuật</h4>
                </div>
                <form id="formEditSpec" class="row g-3">
                    <input type="hidden" name="id" id="modalEditSpecID" />
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditSpecName">Tên yêu cầu</label>
                        <input type="text" id="modalEditSpecName" name="name" class="form-control" placeholder="Nhập tên yêu cầu" required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditSpecCompany">Công ty</label>
                        <select id="modalEditSpecCompany" name="company" class="form-control" required>
                            <!-- Danh sách công ty sẽ được tải động -->
                        </select>
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditSpecText">Nội dung</label>
                        <textarea id="modalEditSpecText" name="text" class="form-control" placeholder="Nhập nội dung yêu cầu" required></textarea>
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditSpecFiles">Files đính kèm</label>
                        <input type="file" id="modalEditSpecFiles" name="files[]" class="form-control" multiple />
                    </div>
                    <div class="col-12 text-center">
                        <button id="submitEditSpec" type="submit" class="btn btn-primary me-sm-4 me-1">Submit</button>
                        <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- / Content -->
<?php
$view->footing();
?>
<script src="<?=ROOT?>assets/js/specifications.js"></script>