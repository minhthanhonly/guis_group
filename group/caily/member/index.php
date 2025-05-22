<?php


require_once('../application/loader.php');
$view->heading('メンバー一覧');

?>

<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card" id="option-block">
		<div class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-3">
				<h4 class="card-title mb-0"><span>メンバー一覧</span></h4>
			</div>
		</div>
		<div class="card-header border-bottom">
			<h5 class="card-title mb-0">フィルター</h5>
			<div class="d-flex justify-content-between align-items-center row pt-4 gap-4 gap-md-0">
				
				<div class="col-md-3 user_role">
					<select id="UserRole" class="form-select text-capitalize">
						<option value="">制限</option>
						<option value="administrator">administrator</option>
						<option value="manager">manager</option>
						<option value="editor">editor</option>
						<option value="member">member</option>
					</select>
				</div>
				<div class="col-md-3 user_group">
					<select id="UserGroup" class="form-select text-capitalize"><option value="">グループ</option></select>
				</div>
				<div class="col-md-6 user_role">
				</div>
			</div>
		</div>
		<div class="card-datatable">
			<table class="datatables-users table">
				<thead class="border-top">
					<tr>
						<th></th>
						<th>ユーザー</th>
						<th>制限</th>
						<th>グループ</th>
						<th>従業員の種類</th>
						<th>ステータス</th>
						<th>アクション</th>
					</tr>
				</thead>
			</table>
		</div>
	</div>
</div>

<!-- Offcanvas to add new user -->
<div class="modal fade" id="modalAddUser" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg modal-simple modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
        <div class="text-center mb-6">
          <h4 class="modal-title">メンバーを追加</h4>
        </div>
        <form class="add-new-user pt-0" id="addNewUserForm">
          <div class="form-group row">
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="add-user-userName">ユーザー名</label>
              <input type="text" class="form-control" id="add-user-userName" placeholder="" name="userid"/>
            </div>
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="add-user-password">パスワード</label>
              <input type="password" class="form-control" id="add-user-password" placeholder="" name="password"/>
            </div>
          </div>
          <div class="form-group row">
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="add-user-lastname">姓</label>
              <input type="text" class="form-control" id="add-user-lastname" placeholder="" name="lastname"/>
            </div>
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="add-user-firstname">名</label>
              <input type="text" class="form-control" id="add-user-firstname" placeholder="" name="firstname"/>
            </div>
          </div>
          <div class="form-group row">
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="add-user-email">メールアドレス</label>
              <input type="text" id="add-user-email" class="form-control" placeholder="" name="user_email" />
            </div>
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="add-user-contact">電話番号</label>
              <input type="text" id="add-user-contact" class="form-control phone-mask" placeholder="" name="user_phone" />
            </div>
          </div>
          <div class="form-group row">
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="add-user-position">役職</label>
              <input type="text" class="form-control" id="add-user-position" placeholder="" name="position"/>
            </div>
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="add-user-role">制限</label>
              <select id="add-user-role" class="form-select select2" name="authority">
                <option value="">選択してください</option>
                <option value="administrator">administrator</option>
                <option value="manager">manager</option>
                <option value="editor">editor</option>
                <option value="member">member</option>
              </select>
            </div>
          </div>
          <div class="form-group row">
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="add-user-group">グループ</label>
              <select id="add-user-group" class="form-select select2" name="user_group">
              <option value="">選択してください</option>
              </select>
            </div>
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="add-user-type">従業員の種類</label>
              <select id="add-user-type" class="form-select select2" name="member_type">
              <option value="">選択してください</option>
              </select>
            </div>
          </div>
          <div class="form-group row">
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="add-user-branch">支店</label>
              <select id="add-user-branch" class="form-select select2" name="branch_id">
              <option value="">選択してください</option>
              </select>
            </div>
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="add-user-department">部署</label>
              <select id="add-user-department" class="form-select select2" name="department_id[]" multiple>
              </select>
            </div>
          </div>
          <div class="mb-4 form-control-validation">
            <label class="form-label" for="add-user-type">編集設定</label>
            <div id="add-user-permit"></div>
          </div>
          <button type="submit" class="btn btn-primary me-3 data-submit">追加</button>
          <button type="reset" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal to edit new user -->

<div class="modal fade" id="modalEditUser" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg modal-simple modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
        <div class="text-center mb-6">
          <h4 class="modal-title">メンバーを編集</h4>
        </div>
        <form class="add-new-user pt-0" id="editUserForm">
          <input type="hidden" id="edit-user-id" name="id" />
          <div class="form-group row">
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="edit-user-userName">ユーザー名</label>
              <input type="text" class="form-control" id="edit-user-userName" placeholder="" name="userid" readonly/>
            </div>
          </div>
          <div class="form-group row">
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="edit-user-lastname">姓</label>
              <input type="text" class="form-control" id="edit-user-lastname" placeholder="" name="lastname"/>
            </div>
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="edit-user-firstname">名</label>
              <input type="text" class="form-control" id="edit-user-firstname" placeholder="" name="firstname"/>
            </div>
          </div>
          <div class="form-group row">
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="edit-user-email">メールアドレス</label>
              <input type="text" id="edit-user-email" class="form-control" placeholder="" name="user_email" />
            </div>
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="edit-user-contact">電話番号</label>
              <input type="text" id="edit-user-contact" class="form-control phone-mask" placeholder="" name="user_phone" />
            </div>
          </div>
          <div class="form-group row">
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="edit-user-position">役職</label>
              <input type="text" class="form-control" id="edit-user-position" placeholder="" name="position"/>
            </div>
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="edit-user-role">制限</label>
              <select id="edit-user-role" class="form-select select2" name="authority">
                <option value="">選択してください</option>
                <option value="administrator">administrator</option>
                <option value="manager">manager</option>
                <option value="editor">editor</option>
                <option value="member">member</option>
              </select>
            </div>
          </div>
          <div class="form-group row">
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="edit-user-group">グループ</label>
              <select id="edit-user-group" class="form-select select2" name="user_group">
                <option value="">選択してください</option>
              </select>
            </div>
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="edit-user-type">従業員の種類</label>
              <select id="edit-user-type" class="form-select select2" name="member_type">
                <option value="">選択してください</option>
              </select>
            </div>
          </div>
          <div class="form-group row">
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="edit-user-branch">支店</label>
              <select id="edit-user-branch" class="form-select select2" name="branch_id">
                <option value="">選択してください</option>
              </select>
            </div>
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="edit-user-department">部署</label>
              <select id="edit-user-department" class="form-select select2" name="department_id[]" multiple>
              </select>
            </div>
          </div>
          <div class="mb-4 form-control-validation">
            <label class="form-label" for="edit-user-type">編集設定</label>
            <div id="edit-user-permit"></div>
          </div>
          <button type="submit" class="btn btn-primary me-3 data-submit">編集</button>
          <button type="reset" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal to change password -->
<div class="modal fade" id="modalChangePassword" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-simple modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
        <div class="text-center mb-6">
          <h4 class="modal-title">パスワードを変更</h4>
        </div>
        <form class="pt-0" id="changePasswordForm">
          <input type="hidden" id="change-password-id" name="id" />
          <div class="form-group row">
            <div class="col-md-12 mb-4 form-control-validation">
              <label class="form-label" for="change-password-password">パスワード</label>
              <input type="password" class="form-control" id="change-password-password" placeholder="" name="password"/>
            </div>
          </div>
          <button type="submit" class="btn btn-primary me-3 data-submit">変更</button>
          <button type="reset" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
        </form>
      </div>
    </div>
  </div>
</div>


<?php
$view->footing();
?>

<script src="<?=ROOT?>assets/js/member.js?v=<?=CACHE_VERSION?>"></script>