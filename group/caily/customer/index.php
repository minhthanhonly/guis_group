<?php

require_once('../application/loader.php');
$view->heading('Quản lý công ty khách hàng');

?>
<!-- Content -->
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="card mb-6">
        <div class="card-header d-flex justify-content-between">
            <h5 class="card-title mb-0">Danh sách công ty khách hàng</h5>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompanyModal">Thêm công ty</button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="datatables-company table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên công ty</th>
                            <th>Loại</th>
                            <th>Địa chỉ</th>
                            <th>Số điện thoại</th>
                            <th>Ngày tạo</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dữ liệu công ty sẽ được hiển thị ở đây -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Company Modal -->
<div class="modal fade" id="addCompanyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-simple modal-dialog-centered modal-add-new-company">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="text-center mb-6">
                    <h4 class="role-title">Thêm công ty</h4>
                </div>
                <form id="formAddNewCompany" class="row g-3">
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddName">Tên công ty</label>
                        <input type="text" id="modalAddName" name="name" class="form-control" placeholder="Nhập tên công ty" autofocus required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddType">Loại</label>
                        <select id="modalAddType" name="type" class="form-control" required>
                            <option value="client">Khách hàng</option>
                            <option value="partner">Đối tác</option>
                        </select>
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddAddress">Địa chỉ</label>
                        <input type="text" id="modalAddAddress" name="address" class="form-control" placeholder="Nhập địa chỉ" required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddPhone">Số điện thoại</label>
                        <input type="text" id="modalAddPhone" name="phone" class="form-control" placeholder="Nhập số điện thoại" required />
                    </div>
                    <div class="col-12 text-center">
                        <button id="submitAddCompany" type="submit" class="btn btn-primary me-sm-4 me-1">Submit</button>
                        <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Company Modal -->
<div class="modal fade" id="editCompanyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-simple modal-dialog-centered modal-edit-company">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="text-center mb-6">
                    <h4 class="role-title">Sửa công ty</h4>
                </div>
                <form id="formEditCompany" class="row g-3">
                    <input type="hidden" name="id" id="modalEditID" />
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditName">Tên công ty</label>
                        <input type="text" id="modalEditName" name="name" class="form-control" placeholder="Nhập tên công ty" required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditType">Loại</label>
                        <select id="modalEditType" name="type" class="form-control" required>
                            <option value="client">Khách hàng</option>
                            <option value="partner">Đối tác</option>
                        </select>
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditAddress">Địa chỉ</label>
                        <input type="text" id="modalEditAddress" name="address" class="form-control" placeholder="Nhập địa chỉ" required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditPhone">Số điện thoại</label>
                        <input type="text" id="modalEditPhone" name="phone" class="form-control" placeholder="Nhập số điện thoại" required />
                    </div>
                    <div class="col-12 text-center">
                        <button id="submitEditCompany" type="submit" class="btn btn-primary me-sm-4 me-1">Submit</button>
                        <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Company Modal -->
<div class="modal fade" id="viewCompanyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-simple modal-dialog-centered modal-view-company">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="text-center mb-6">
                    <h4 class="role-title">Thông tin công ty</h4>
                </div>
                <div id="companyDetails">
                    <!-- Company details will be dynamically loaded here -->
                </div>
                <hr>
                <div class="text-center mb-6">
                    <h5 class="role-title">Danh sách người đại diện</h5>
                </div>
                <div class="table-responsive">
                    <table class="datatables-representatives table table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên</th>
                                <th>Email</th>
                                <th>Số điện thoại</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Representative data will be dynamically loaded here -->
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-primary" id="addRepresentativeButton">Thêm người đại diện</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Representative Modal -->
<div class="modal fade" id="addRepresentativeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-simple modal-dialog-centered modal-add-representative">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="text-center mb-6">
                    <h4 class="role-title">Thêm người đại diện</h4>
                </div>
                <form id="formAddRepresentative" class="row g-3">
                    <input type="hidden" name="company_id" id="modalAddRepCompanyID" />
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddRepName">Tên</label>
                        <input type="text" id="modalAddRepName" name="name" class="form-control" placeholder="Nhập tên người đại diện" required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddRepEmail">Email</label>
                        <input type="email" id="modalAddRepEmail" name="email" class="form-control" placeholder="Nhập email người đại diện" required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalAddRepPhone">Số điện thoại</label>
                        <input type="text" id="modalAddRepPhone" name="phone" class="form-control" placeholder="Nhập số điện thoại người đại diện" required />
                    </div>
                    <div class="col-12 text-center">
                        <button id="submitAddRepresentative" type="submit" class="btn btn-primary me-sm-4 me-1">Submit</button>
                        <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Representative Modal -->
<div class="modal fade" id="editRepresentativeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-simple modal-dialog-centered modal-edit-representative">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="text-center mb-6">
                    <h4 class="role-title">Sửa thông tin người đại diện</h4>
                </div>
                <form id="formEditRepresentative" class="row g-3">
                    <input type="hidden" name="id" id="modalEditRepID" />
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditRepName">Tên</label>
                        <input type="text" id="modalEditRepName" name="name" class="form-control" placeholder="Nhập tên người đại diện" required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditRepEmail">Email</label>
                        <input type="email" id="modalEditRepEmail" name="email" class="form-control" placeholder="Nhập email người đại diện" required />
                    </div>
                    <div class="col-12 form-control-validation mb-3">
                        <label class="form-label" for="modalEditRepPhone">Số điện thoại</label>
                        <input type="text" id="modalEditRepPhone" name="phone" class="form-control" placeholder="Nhập số điện thoại người đại diện" required />
                    </div>
                    <div class="col-12 text-center">
                        <button id="submitEditRepresentative" type="submit" class="btn btn-primary me-sm-4 me-1">Submit</button>
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
<script src="<?=ROOT?>assets/js/company.js"></script>