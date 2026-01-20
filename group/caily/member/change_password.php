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
								class="icon-base fa fa-user icon-sm me-1_5"></i> <span data-i18n="アカウント">アカウント</span></a>
					</li>
					<li class="nav-item">
						<a class="nav-link waves-effect waves-light active" href="change_password.php"><i
								class="icon-base fa fa-lock icon-sm me-1_5"></i> <span data-i18n="セクシュアリティ">セクシュアリティ</span></a>
					</li>
				</ul>
			</div>
			<form class="content" method="post" action="" enctype="multipart/form-data">
				<?php if(isset($hash['error']) && count($hash['error']) > 0){
					echo $view->error($hash['error']);
				}?>
                <?php if(isset($hash['data']['message'])){
					echo $view->success($hash['data']['message']);
				}?>
				<div class="card mb-6">
                    <h5 class="card-header"><span data-i18n="パスワードの変更">パスワードの変更</span></h5>
                    <div class="card-body pt-1">
                        <div class="row mb-sm-6 mb-2">
                        <div class="col-md-6 form-password-toggle form-control-validation fv-plugins-icon-container">
                            <label class="form-label" for="currentPassword"><span data-i18n="現在のパスワード">現在のパスワード</span></label>
                            <div class="input-group input-group-merge has-validation">
                            <input class="form-control" type="password" name="password" id="currentPassword" placeholder="············">
                            <span class="input-group-text cursor-pointer"><i class="icon-base ti tabler-eye-off icon-xs"></i></span>
                            </div><div class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback"></div>
                        </div>
                        </div>
                        <div class="row gy-sm-6 gy-2 mb-sm-0 mb-2">
                        <div class="mb-6 col-md-6 form-password-toggle form-control-validation fv-plugins-icon-container">
                            <label class="form-label" for="newPassword"><span data-i18n="新しいパスワード">新しいパスワード</span></label>
                            <div class="input-group input-group-merge has-validation">
                            <input class="form-control" type="password" id="newPassword" name="newpassword" placeholder="············">
                            <span class="input-group-text cursor-pointer"><i class="icon-base ti tabler-eye-off icon-xs"></i></span>
                            </div><div class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback"></div>
                        </div>

                        <div class="mb-6 col-md-6 form-password-toggle form-control-validation fv-plugins-icon-container">
                            <label class="form-label" for="confirmPassword"><span data-i18n="新しいパスワード（確認）">新しいパスワード（確認）</span></label>
                            <div class="input-group input-group-merge has-validation">
                            <input class="form-control" type="password" name="confirmpassword" id="confirmPassword" placeholder="············">
                            <span class="input-group-text cursor-pointer"><i class="icon-base ti tabler-eye-off icon-xs"></i></span>
                            </div><div class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback"></div>
                        </div>
                        </div>
                        <h6 class="text-body"><span data-i18n="パスワード要件:">パスワード要件:</span></h6>
                        <ul class="ps-4 mb-0">
                            <li class="mb-4"><span data-i18n="4文字以上32文字以下">4文字以上32文字以下</span></li>
                            <li class="mb-4"><span data-i18n="英数字">英数字</span></li>
                        </ul>
                        <div class="mt-6">
                            <button type="submit" class="btn btn-primary me-3 waves-effect waves-light"><span data-i18n="保存">保存</span></button>
                            <a href="view.php" class="btn btn-label-secondary waves-effect"><span data-i18n="戻る">戻る</span></a>
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