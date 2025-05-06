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
			<h5 class="card-title mb-0">Filters</h5>
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
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasAddUser" aria-labelledby="offcanvasAddUserLabel">
	<div class="offcanvas-header border-bottom">
		<h5 id="offcanvasAddUserLabel" class="offcanvas-title">メンバー追加</h5>
		<button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
	</div>
    <div class="offcanvas-body mx-0 flex-grow-0 p-6 h-100">
        <form class="add-new-user pt-0" id="addNewUserForm">
          <div class="mb-4 form-control-validation">
            <label class="form-label" for="add-user-userName">ユーザー名</label>
            <input type="text" class="form-control" id="add-user-userName" placeholder="" name="userid"/>
          </div>
          <div class="mb-4 form-control-validation">
            <label class="form-label" for="add-user-password">パスワード</label>
            <input type="password" class="form-control" id="add-user-password" placeholder="" name="password"/>
          </div>
          <div class="mb-4 form-control-validation">
            <label class="form-label" for="add-user-fullname">氏名</label>
            <input type="text" class="form-control" id="add-user-fullname" placeholder="" name="realname"/>
          </div>
          <div class="mb-4 form-control-validation">
            <label class="form-label" for="add-user-email">メールアドレス</label>
            <input type="text" id="add-user-email" class="form-control" placeholder="" name="email" />
          </div>
          <div class="mb-4 form-control-validation">
            <label class="form-label" for="add-user-contact">電話番号</label>
            <input type="text" id="add-user-contact" class="form-control phone-mask" placeholder="" name="phone" />
          </div>
          <div class="mb-4 form-control-validation">
            <label class="form-label" for="user-role">制限</label>
            <select id="user-role" class="form-select" name="role">
              <option value="">選択してください</option>
              <option value="administrator">administrator</option>
              <option value="manager">manager</option>
              <option value="editor">editor</option>
              <option value="member">member</option>
            </select>
          </div>
          <div class="mb-4 form-control-validation">
            <label class="form-label" for="add-user-group">グループ</label>
            <select id="add-user-group" class="form-select" name="group">
              <option value="">選択してください</option>
            </select>
          </div>
          <div class="mb-4 form-control-validation">
            <label class="form-label" for="add-user-type">従業員の種類</label>
            <select id="add-user-type" class="form-select" name="type">
              <option value="">選択してください</option>
            </select>
          </div>
          <div class="mb-4 form-control-validation">
            <label class="form-label" for="add-user-type">編集設定</label>
            <div id="add-user-permit"></div>
          </div>
          <button type="submit" class="btn btn-primary me-3 data-submit">追加</button>
          <button type="reset" class="btn btn-label-danger" data-bs-dismiss="offcanvas">キャンセル</button>
        </form>
    </div>
</div>

<!-- Offcanvas to edit new user -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasEditUser" aria-labelledby="offcanvasEditUserLabel">
	<div class="offcanvas-header border-bottom">
		<h5 id="offcanvasEditUserLabel" class="offcanvas-title">メンバー編集</h5>
		<button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
	</div>
    <div class="offcanvas-body mx-0 flex-grow-0 p-6 h-100">
        <form class="add-new-user pt-0" id="editUserForm" onsubmit="return false">
          <div class="mb-4 form-control-validation">
            <label class="form-label" for="edit-user-userName">ユーザー名</label>
            <input type="text" class="form-control" id="edit-user-userName" placeholder="" name="userid"/>
          </div>
          <div class="mb-4 form-control-validation">
            <label class="form-label" for="edit-user-password">パスワード</label>
            <input type="password" class="form-control" id="edit-user-password" placeholder="" name="password"/>
          </div>
          <div class="mb-4 form-control-validation">
            <label class="form-label" for="edit-user-fullname">氏名</label>
            <input type="text" class="form-control" id="edit-user-fullname" placeholder="" name="realname"/>
          </div>
          <div class="mb-4 form-control-validation">
            <label class="form-label" for="edit-user-email">メールアドレス</label>
            <input type="text" id="edit-user-email" class="form-control" placeholder="" name="email" />
          </div>
          <div class="mb-4 form-control-validation">
            <label class="form-label" for="edit-user-contact">電話番号</label>
            <input type="text" id="edit-user-contact" class="form-control phone-mask" placeholder="" name="phone" />
          </div>
          <div class="mb-4 form-control-validation">
            <label class="form-label" for="edit-user-role">制限</label>
            <select id="edit-user-role" class="form-select" name="role">
              <option value="">選択してください</option>
              <option value="administrator">administrator</option>
              <option value="manager">manager</option>
              <option value="editor">editor</option>
              <option value="member">member</option>
            </select>
          </div>
          <div class="mb-4 form-control-validation">
            <label class="form-label" for="edit-user-group">グループ</label>
            <select id="edit-user-group" class="form-select" name="group">
              <option value="">選択してください</option>
            </select>
          </div>
          <div class="mb-4 form-control-validation">
            <label class="form-label" for="edit-user-type">従業員の種類</label>
            <select id="edit-user-type" class="form-select" name="type">
              <option value="">選択してください</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary me-3 data-submit">更新</button>
          <button type="reset" class="btn btn-label-danger" data-bs-dismiss="offcanvas">キャンセル</button>
        </form>
    </div>
</div>

<script src="<?=ROOT?>assets/js/member.js"></script>
<?php
$view->footing();
?>