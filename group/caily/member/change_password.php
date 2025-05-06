<?php
require_once('../application/loader.php');
$view->heading('セクシュアリティ');
?>

<div class="container-xxl flex-grow-1 container-p-y">
	<div class="row fv-plugins-icon-container">
		<div class="col-md-12">
			<div class="nav-align-top">
				<ul class="nav nav-pills flex-column flex-md-row mb-6 gap-md-0 gap-2">
					<li class="nav-item">
						<a class="nav-link waves-effect waves-light" href="view.php"><i
								class="icon-base ti tabler-users icon-sm me-1_5"></i> アカウント</a>
					</li>
					<li class="nav-item">
						<a class="nav-link active waves-effect waves-light" href="change_password.php"><i
								class="icon-base ti tabler-lock icon-sm me-1_5"></i> セクシュアリティ</a>
					</li>
				</ul>
			</div>
			<form class="content" method="post" action="" enctype="multipart/form-data">
				<?php if(isset($hash['error']) && count($hash['error']) > 0){
					$view->error($hash['error']);
				}?>
                <?php if(isset($hash['data']['message'])){
					echo $hash['data']['message'];
				}?>
				<div class="card mb-6">
                    <h5 class="card-header">パスワードの変更</h5>
                    <div class="card-body pt-1">
                        <div class="row mb-sm-6 mb-2">
                        <div class="col-md-6 form-password-toggle form-control-validation fv-plugins-icon-container">
                            <label class="form-label" for="currentPassword">現在のパスワード</label>
                            <div class="input-group input-group-merge has-validation">
                            <input class="form-control" type="password" name="password" id="currentPassword" placeholder="············">
                            <span class="input-group-text cursor-pointer"><i class="icon-base ti tabler-eye-off icon-xs"></i></span>
                            </div><div class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback"></div>
                        </div>
                        </div>
                        <div class="row gy-sm-6 gy-2 mb-sm-0 mb-2">
                        <div class="mb-6 col-md-6 form-password-toggle form-control-validation fv-plugins-icon-container">
                            <label class="form-label" for="newPassword">新しいパスワード</label>
                            <div class="input-group input-group-merge has-validation">
                            <input class="form-control" type="password" id="newPassword" name="newpassword" placeholder="············">
                            <span class="input-group-text cursor-pointer"><i class="icon-base ti tabler-eye-off icon-xs"></i></span>
                            </div><div class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback"></div>
                        </div>

                        <div class="mb-6 col-md-6 form-password-toggle form-control-validation fv-plugins-icon-container">
                            <label class="form-label" for="confirmPassword">新しいパスワード（確認）</label>
                            <div class="input-group input-group-merge has-validation">
                            <input class="form-control" type="password" name="confirmpassword" id="confirmPassword" placeholder="············">
                            <span class="input-group-text cursor-pointer"><i class="icon-base ti tabler-eye-off icon-xs"></i></span>
                            </div><div class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback"></div>
                        </div>
                        </div>
                        <h6 class="text-body">パスワード要件:</h6>
                        <ul class="ps-4 mb-0">
                            <li class="mb-4">4文字以上32文字以下</li>
                            <li class="mb-4">英数字</li>
                        </ul>
                        <div class="mt-6">
                            <button type="submit" class="btn btn-primary me-3 waves-effect waves-light">編集</button>
                            <button type="reset" class="btn btn-label-secondary waves-effect">キャンセル</button>
                        </div>
                    </div>
                </div>
			</form>
		</div>
	</div>
</div>
<?php
$view->footing();
?>

<script src="<?=ROOT?>assets/js/settings-account.js"></script>