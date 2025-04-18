<?php
require_once('../application/loader.php');
$view->heading('個人設定');
?>

<!-- <form class="content" method="post" action="">
	<table class="form" cellspacing="0">
		<tr><th>名前<span class="necessary">(必須)</span></th><td><input type="text" name="realname" class="inputvalue" value="<?=$hash['data']['realname']?>" /></td></tr>
		<tr><th>かな</th><td><input type="text" name="user_ruby" class="inputvalue" value="<?=$hash['data']['user_ruby']?>" /></td></tr>
		<tr><th>郵便番号</th><td>
			<input type="text" name="user_postcode" id="postcode" class="inputalpha" value="<?=$hash['data']['user_postcode']?>" />&nbsp;
			<input type="button" value="検索" onclick="Postcode.feed(this)" />
		</td></tr>
		<tr><th>住所</th><td>
			<input type="text" name="user_address" id="address" class="inputtitle" value="<?=$hash['data']['user_address']?>" />&nbsp;
			<input type="button" value="検索" onclick="Postcode.feed(this, 'address')" />
		</td></tr>
		<tr><th>住所（かな）</th><td><input type="text" name="user_addressruby" id="addressruby" class="inputtitle" value="<?=$hash['data']['user_addressruby']?>" /></td></tr>
		<tr><th>電話番号</th><td><input type="text" name="user_phone" class="inputalpha" value="<?=$hash['data']['user_phone']?>" /></td></tr>
		<tr><th>携帯電話</th><td><input type="text" name="user_mobile" class="inputalpha" value="<?=$hash['data']['user_mobile']?>" /></td></tr>
		<tr><th>メールアドレス</th><td><input type="text" name="user_email" class="inputvalue" value="<?=$hash['data']['user_email']?>" /></td></tr>
		<tr><th>スカイプID</th><td><input type="text" name="user_skype" class="inputalpha" value="<?=$hash['data']['user_skype']?>" /></td></tr>
	</table>
	<h2>パスワードの変更</h2>
	<table class="form" cellspacing="0">
		<tr><th>現在のパスワード</th><td><input type="password" name="password" class="inputvalue" /></td></tr>
		<tr><th>新しいパスワード<?=$view->explain('userpassword')?></th><td><input type="password" name="newpassword" class="inputvalue" /></td></tr>
		<tr><th>新しいパスワード（確認）</th><td><input type="password" name="confirmpassword" class="inputvalue" /></td></tr>
	</table>
	<div class="submit">
		<input type="submit" value="　編集　" />&nbsp;
		<input type="button" value="キャンセル" onclick="location.href='index.php'" />
	</div>
</form> -->

<div class="container-xxl flex-grow-1 container-p-y">
	<div class="row fv-plugins-icon-container">
		<div class="col-md-12">
			<div class="nav-align-top">
				<ul class="nav nav-pills flex-column flex-md-row mb-6 gap-md-0 gap-2">
				<li class="nav-item">
						<a class="nav-link active waves-effect waves-light" href="view.php"><i
								class="icon-base ti tabler-users icon-sm me-1_5"></i> アカウント</a>
					</li>
					<li class="nav-item">
						<a class="nav-link waves-effect waves-light" href="change_password.php"><i
								class="icon-base ti tabler-lock icon-sm me-1_5"></i> セクシュアリティ</a>
					</li>
				</ul>
			</div>
			<form class="content" method="post" action="" enctype="multipart/form-data">
				<?=$view->error($hash['error'])?>
				<div class="card mb-6">
					<!-- Account -->
					<div class="card-body">
						<div class="user-avatar-section">
							<div class="d-flex align-items-center flex-row">
								<?php if($hash['data']['user_image'] != '') {
									echo '<img id="uploadedAvatar" class="img-fluid w-px-100 h-px-100 rounded me-4" src="../../assets/upload/avatar/'.$hash['data']['user_image'].'" height="120" width="120" alt="User avatar">';
								} else {
									echo '<img id="uploadedAvatar" class="img-fluid w-px-100 h-px-100 rounded me-4" src="../../assets/img/avatars/1.png" height="120" width="120" alt="User avatar">';
								} ?>
								
								<div class="user-info text-left">
									<h5><?=$hash['data']['realname']?></h5>
									<span class="badge bg-label-secondary"><?=$hash['data']['authority']?></span>
								</div>
							</div>
						</div>
						<div class="d-flex align-items-start align-items-sm-center gap-6 mt-4">
							<div class="button-wrapper">
								<label for="upload" class="btn btn-primary me-3 mb-4 waves-effect waves-light" tabindex="0">
									<span class="d-none d-sm-block">新しい写真をアップロード</span>
									<i class="icon-base ti tabler-upload d-block d-sm-none"></i>
									<input name="user_image" type="file" id="upload" class="account-file-input" hidden=""
										accept=".png, .jpg, .jpeg, .gif" />
								</label>
								<button type="button" class="btn btn-label-secondary account-image-reset mb-4 waves-effect">
									<i class="icon-base ti tabler-reset d-block d-sm-none"></i>
									<span class="d-none d-sm-block">リセット</span>
								</button>

								<div>PNG、GIF、PNGが許可されます。最大サイズ1MB。</div>
							</div>
						</div>

					</div>
					<div class="card-body pt-4">
						<form id="formAccountSettings" method="GET" onsubmit="return false"
							class="fv-plugins-bootstrap5 fv-plugins-framework" novalidate="novalidate">
							<div class="row gy-4 gx-6 mb-6">
								<div class="col-md-6 form-control-validation fv-plugins-icon-container">
									<label for="realname" class="form-label">名前</label>
									<input class="form-control" type="text" id="realname" name="realname"
										value="<?=$hash['data']['realname']?>">
									<div
										class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
									</div>
								</div>
								<div class="col-md-6 form-control-validation fv-plugins-icon-container">
									<label for="user_ruby" class="form-label">かな</label>
									<input class="form-control" type="text" name="user_ruby" id="user_ruby"
										value="<?=$hash['data']['user_ruby']?>">
									<div
										class="fv-plugins-message-container fv-plugins-message-container--enabled invalid-feedback">
									</div>
								</div>
								<div class="col-md-6">
									<label for="email" class="form-label">メールアドレス</label>
									<input class="form-control" type="text" id="email" name="user_email"
										value="<?=$hash['data']['user_email']?>" placeholder="">
								</div>
								<div class="col-md-6">
									<label for="user_groupname" class="form-label">グループ</label>
									<input type="text" class="form-control" id="user_groupname" name="user_groupname"
										value="<?=$hash['data']['user_groupname']?>">
								</div>
								<div class="col-md-6">
									<label class="form-label" for="phoneNumber">電話番号</label>
									<div class="input-group">
										<input type="text" id="phoneNumber" name="user_phone"
											value="<?=$hash['data']['user_phone']?>" class="form-control" placeholder="">
									</div>
								</div>
								<div class="col-md-6">
									<label class="form-label" for="user_mobile">携帯電話</label>
									<div class="input-group">
										<input type="text" id="user_mobile" name="user_mobile"
											value="<?=$hash['data']['user_mobile']?>" class="form-control" placeholder="">
									</div>
								</div>
								<div class="col-md-6">
									<label for="address" class="form-label">住所</label>
									<input type="text" class="form-control" id="address" name="user_address"
										value="<?=$hash['data']['user_address']?>" placeholder="">
								</div>
								<div class="col-md-6">
									<label for="user_addressruby" class="form-label">住所（かな）</label>
									<input class="form-control" type="text" id="user_addressruby" name="user_addressruby"
										value="<?=$hash['data']['user_addressruby']?>" placeholder="">
								</div>
								<div class="col-md-6">
									<label for="user_skype" class="form-label">スカイプID</label>
									<input type="text" class="form-control" id="user_skype" name="user_skype" placeholder=""
										value="<?=$hash['data']['user_skype']?>">
								</div>
								
							</div>

							<?php if ($hash['data']['userid'] == $_SESSION['userid'] ) {?>
							<div class="mt-2">
								<button type="submit"
									class="btn btn-primary me-3 waves-effect waves-light">編集</button>
								<a href="view.php" class="btn btn-label-secondary waves-effect">キャンセル</a>
							</div>
							<?php } ?>

						</form>
					</div>
					<!-- /Account -->
				</div>
			</form>
		</div>
	</div>
</div>
<?php
$view->footing();
?>

<script src="<?=ROOT?>assets/js/settings-account.js"></script>