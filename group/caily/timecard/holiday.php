<?php

require_once('../application/loader.php');
$view->heading('Thiết lập ngày nghỉ');
?>
<!-- Content -->
<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card" id="option-block">
		<div class="card-header sticky-element bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-4">
				<h4 class="card-title mb-0"><span>Thiết lập ngày nghỉ</span></h4>
			</div>
		</div>
		<div class="card-body">
			<div class="table-responsive text-nowrap">
				<table class="datatables-holiday table table-striped">
					<thead>
						<tr>
							<th>Date</th>
							<th>Content</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody class="table-border-bottom-0"></tbody>
				</table>
			</div>

			 <!-- Add Role Modal -->
			 <div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-simple modal-dialog-centered modal-add-new-role">
                  <div class="modal-content">
                    <div class="modal-body">
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      <div class="text-center mb-6">
                        <h4 class="role-title">Thêm ngày nghỉ</h4>
                      </div>
                      <!-- Add role form -->
                      <form id="formAddNewRecord" class="row g-3">
                        <div class="col-12 form-control-validation mb-3">
                          <label class="form-label" for="modalAddDate">Ngày</label>
                          <input
                            type="text"
                            id="modalAddDate"
                            name="date"
                            class="form-control"
                            placeholder="Nhập ngày nghỉ"
                            autofocus
                            tabindex="-1" />
                        </div>

						            <div class="col-12 form-control-validation mb-3">
                          <label class="form-label" for="modalEditName">Tên ngày nghỉ</label>
                          <input
                            type="text"
                            id="modalAddName"
                            name="name"
                            class="form-control"
                            placeholder="Nhập tên ngày nghỉ"
                            tabindex="-1" />
                        </div>
                       
                        <div class="col-12 text-center">
                          <button id="submitAddDate" type="submit" class="btn btn-primary me-sm-4 me-1">Submit</button>
                          <button
                            type="reset"
                            class="btn btn-label-secondary"
                            data-bs-dismiss="modal"
                            aria-label="Close">
                            Cancel
                          </button>
                        </div>
                      </form>
                      <!--/ Add role form -->
                    </div>
                  </div>
                </div>
              </div>
              <!--/ Add Role Modal -->


               <!-- Add Role Modal -->
			 <div class="modal fade" id="editRoleModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-simple modal-dialog-centered modal-add-new-role">
                  <div class="modal-content">
                    <div class="modal-body">
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      <div class="text-center mb-6">
                        <h4 class="role-title">Sửa ngày nghỉ</h4>
                      </div>
                      <!-- Add role form -->
                      <form id="formEditRecord" class="row g-3">
                        <div class="col-12 form-control-validation mb-3">
                          <label class="form-label" for="modalEditDate">Ngày</label>
                          <input type="hidden" name="id" id="modalEditID" />
                          <input type="hidden" name="date_old" id="modalEditDateOld" />
                          <input
                            type="text"
                            id="modalEditDate"
                            name="date"
                            class="form-control"
                            placeholder="Nhập ngày nghỉ"
                            autofocus
                            tabindex="-1" />
                        </div>

						            <div class="col-12 form-control-validation mb-3">
                          <label class="form-label" for="modalEditName">Tên ngày nghỉ</label>
                          <input
                            type="text"
                            id="modalEditName"
                            name="name"
                            class="form-control"
                            placeholder="Nhập tên ngày nghỉ"
                            tabindex="-1" />
                        </div>
                       
                        <div class="col-12 text-center">
                          <button id="submitAddDate" type="submit" class="btn btn-primary me-sm-4 me-1">Submit</button>
                          <button
                            type="reset"
                            class="btn btn-label-secondary"
                            data-bs-dismiss="modal"
                            aria-label="Close">
                            Cancel
                          </button>
                        </div>
                      </form>
                      <!--/ Add role form -->
                    </div>
                  </div>
                </div>
              </div>
              <!--/ Add Role Modal -->
		</div>
	</div>
</div>
<!-- / Content -->
 
<script src="<?=ROOT?>assets/js/holiday.js"></script>
<?php
$view->footing();
?>
