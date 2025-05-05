<?php

require_once('../application/loader.php');
$view->heading('休日設定');
?>
<!-- Content -->
<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card" id="option-block">
		<div class="card-header sticky-element bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-4">
				<h4 class="card-title mb-0"><span>休日設定</span></h4>
			</div>
		</div>
		<div class="card-body">
			<div class="table-responsive text-nowrap">
				<table class="datatables-holiday table table-striped">
					<thead>
						<tr>
							 <th>日付</th>
							 <th>内容</th>
							 <th>操作</th>
						</tr>
					</thead>
					<tbody class="table-border-bottom-0"></tbody>
				</table>
			</div>

      <div class="modal fade" id="modalSyncAPI" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-simple modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-body">
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
              <div class="text-center mb-6">
                <h4 class="role-title">APIと同期する</h4>
                <p>APIの休日を取得して、休日を追加します。</p>
              </div>
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th>日付</th>
                    <th>内容</th>
                    <th>操作</th>
                  </tr>
                </thead>
                <tbody id="modalSyncAPITableBody">
                 
                </tbody>
              </table>
              <div class="col-12 text-center mt-3">
                <button id="submitSyncAPI" type="submit" class="btn btn-primary me-sm-4 me-1">送信</button>
                <button type="reset" class="btn btn-label-secondary" data-bs-dismiss="modal" aria-label="閉じる">キャンセル</button>
              </div>
            </div>
          </div>
        </div>
      </div>

			 <!-- 役割追加モーダル -->
			 <div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-simple modal-dialog-centered modal-add-new-role">
                  <div class="modal-content">
                    <div class="modal-body">
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                      <div class="text-center mb-6">
                        <h4 class="role-title">休日を追加</h4>
                      </div>
                      <!-- 役割追加フォーム -->
                      <form id="formAddNewRecord" class="row g-3">
                        <div class="col-12 form-control-validation mb-3">
                          <label class="form-label" for="modalAddDate">日付</label>
                          <input
                            type="text"
                            id="modalAddDate"
                            name="date"
                            class="form-control"
                            placeholder="休日を入力"
                            autofocus
                            tabindex="-1" />
                        </div>

						            <div class="col-12 form-control-validation mb-3">
                          <label class="form-label" for="modalEditName">休日名</label>
                          <input
                            type="text"
                            id="modalAddName"
                            name="name"
                            class="form-control"
                            placeholder="休日名を入力"
                            tabindex="-1" />
                        </div>
                       
                        <div class="col-12 text-center">
                          <button id="submitAddDate" type="submit" class="btn btn-primary me-sm-4 me-1">送信</button>
                          <button
                            type="reset"
                            class="btn btn-label-secondary"
                            data-bs-dismiss="modal"
                            aria-label="閉じる">
                            キャンセル
                          </button>
                        </div>
                      </form>
                      <!--/ 役割追加フォーム -->
                    </div>
                  </div>
                </div>
              </div>
              <!--/ 役割追加モーダル -->


               <!-- 役割編集モーダル -->
			 <div class="modal fade" id="editRoleModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-simple modal-dialog-centered modal-add-new-role">
                  <div class="modal-content">
                    <div class="modal-body">
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                      <div class="text-center mb-6">
                        <h4 class="role-title">休日を編集</h4>
                      </div>
                      <!-- 役割編集フォーム -->
                      <form id="formEditRecord" class="row g-3">
                        <div class="col-12 form-control-validation mb-3">
                          <label class="form-label" for="modalEditDate">日付</label>
                          <input type="hidden" name="id" id="modalEditID" />
                          <input type="hidden" name="date_old" id="modalEditDateOld" />
                          <input
                            type="text"
                            id="modalEditDate"
                            name="date"
                            class="form-control"
                            placeholder="休日を入力"
                            autofocus
                            tabindex="-1" />
                        </div>

						            <div class="col-12 form-control-validation mb-3">
                          <label class="form-label" for="modalEditName">休日名</label>
                          <input
                            type="text"
                            id="modalEditName"
                            name="name"
                            class="form-control"
                            placeholder="休日名を入力"
                            tabindex="-1" />
                        </div>
                       
                        <div class="col-12 text-center">
                          <button id="submitAddDate" type="submit" class="btn btn-primary me-sm-4 me-1">送信</button>
                          <button
                            type="reset"
                            class="btn btn-label-secondary"
                            data-bs-dismiss="modal"
                            aria-label="閉じる">
                            キャンセル
                          </button>
                        </div>
                      </form>
                      <!--/ 役割編集フォーム -->
                    </div>
                  </div>
                </div>
              </div>
              <!--/ 役割編集モーダル -->
		</div>
	</div>
</div>
<!-- / Content -->
 
<script src="<?=ROOT?>assets/js/holiday.js"></script>
<?php
$view->footing();
?>
