<?php

require_once('../application/loader.php');
$view->heading('メンバー詳細');
?>
<div class="container-xxl flex-grow-1 container-p-y">
	<div class="row fv-plugins-icon-container">
		<div class="col-md-12">
			<div class="nav-align-top">
				<ul class="nav nav-pills flex-column flex-md-row mb-6 gap-md-0 gap-2">
					<?php if ($hash['data']['userid'] == $_SESSION['userid'] ) { ?>
					<li class="nav-item">
						<a class="nav-link active waves-effect waves-light" href="view.php"><i
								class="icon-base fa fa-user icon-sm me-1_5"></i> <span data-i18n="アカウント">アカウント</span></a>
					</li>
					<li class="nav-item">
						<a class="nav-link waves-effect waves-light" href="change_password.php"><i
								class="icon-base fa fa-lock icon-sm me-1_5"></i> <span data-i18n="セクシュアリティ">セクシュアリティ</span></a>
					</li>
					<?php } ?>
				</ul>
			</div>
			<div class="card mb-6">
				<!-- Account -->
				<div class="card-body">
					<div class="user-avatar-section">
						<div class="d-flex align-items-center flex-row">
							<?php if(isset($hash['data']['user_image']) && $hash['data']['user_image'] != '') {
								echo '<img id="uploadedAvatar" class="img-fluid object-fit-cover w-px-100 h-px-100 rounded me-4" src="../../assets/upload/avatar/'.$hash['data']['user_image'].'" height="120" width="120" alt="User avatar">';
							} else {
								echo '<img id="uploadedAvatar" class="img-fluid object-fit-cover w-px-100 h-px-100 rounded me-4" src="../../assets/img/avatars/1.png" height="120" width="120" alt="User avatar">';
							} ?>
						  <div class="user-info text-left">
							<h5><?=$hash['data']['realname']?></h5>
							<span class="badge bg-label-secondary"><?=$hash['data']['authority']?></span>
						  </div>
						</div>
					</div>
				

					<!-- <div class="d-flex justify-content-start flex-wrap my-6 gap-0 gap-md-3 gap-lg-4">
						<div class="d-flex align-items-center me-5 gap-4">
						  <div class="avatar">
							<div class="avatar-initial bg-label-primary rounded">
							  <i class="icon-base ti tabler-checkbox icon-lg"></i>
							</div>
						  </div>
						  <div>
							<h5 class="mb-0">0</h5>
							<span>Task Done</span>
						  </div>
						</div>
						<div class="d-flex align-items-center gap-4">
						  <div class="avatar">
							<div class="avatar-initial bg-label-primary rounded">
							  <i class="icon-base ti tabler-briefcase icon-lg"></i>
							</div>
						  </div>
						  <div>
							<h5 class="mb-0">0</h5>
							<span>Project Done</span>
						  </div>
						</div>
					</div> -->
					<h5 class="pb-4 border-bottom mb-4 mt-4"><span data-i18n="詳細">詳細</span></h5>
					<div class="info-container">
						<ul class="list-unstyled mb-6">
						  <li class="mb-2">
							<span class="h6"><span data-i18n="ユーザー名">ユーザー名</span>:</span>
							<span><?=$hash['data']['userid']?></span>
						  </li>
						  <li class="mb-2">
							<span class="h6"><span data-i18n="姓">姓</span>:</span>
							<span><?=isset($hash['data']['lastname']) ? htmlspecialchars($hash['data']['lastname'], ENT_QUOTES, 'UTF-8') : ''?></span>
						  </li>
						  <li class="mb-2">
							<span class="h6"><span data-i18n="名">名</span>:</span>
							<span><?=isset($hash['data']['firstname']) ? htmlspecialchars($hash['data']['firstname'], ENT_QUOTES, 'UTF-8') : ''?></span>
						  </li>
						  <li class="mb-2">
							<span class="h6"><span data-i18n="結婚後の姓">結婚後の姓</span>:</span>
							<span><?=isset($hash['data']['lastname_after_married']) && $hash['data']['lastname_after_married'] !== '' ? htmlspecialchars($hash['data']['lastname_after_married'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">未登録</span>'?></span>
						  </li>
						  <li class="mb-2">
							<span class="h6"><span data-i18n="メールアドレス">メールアドレス</span>:</span>
							<span><?=$hash['data']['user_email']?></span>
						  </li>
						  <li class="mb-2">
							<span class="h6"><span data-i18n="状態">状態</span>:</span>
							<span class="badge <?=$hash['data']['is_suspend'] != 1 ? 'bg-label-success' : 'bg-label-warning'?>"><?=$hash['data']['is_suspend'] == 1 ? '<span data-i18n="停止">停止</span>' : '<span data-i18n="アクティブ">アクティブ</span>'?></span>
						  </li>
						  <li class="mb-2">
							<span class="h6"><span data-i18n="グループ">グループ</span>:</span>
							<span><?=$hash['data']['user_groupname']?></span>
						  </li>
						  <li class="mb-2">
							<span class="h6"><span data-i18n="電話番号">電話番号</span>:</span>
							<span><?=$hash['data']['user_phone']?></span>
						  </li>
						  <li class="mb-2">
							<span class="h6"><span data-i18n="携帯電話">携帯電話</span>:</span>
							<span><?=$hash['data']['user_mobile']?></span>
						  </li>
						  <li class="mb-2">
							<span class="h6"><span data-i18n="住所">住所</span>:</span>
							<span><?=$hash['data']['user_address']?></span>
						  </li>
						  <li class="mb-2">
							<span class="h6"><span data-i18n="住所（かな）">住所（かな）</span>:</span>
							<span><?=$hash['data']['user_addressruby']?></span>
						  </li>
						  <li class="mb-2">
							<span class="h6">Teams ID:</span>
							<span><?=$hash['data']['user_skype']?></span>
						  </li>
						</ul>
						<?php if ($hash['data']['userid'] == $_SESSION['userid'] ) { ?>
							<div class="mt-2">
								<a href="edit.php?id=<?=$hash['data']['id']?>" type="submit"
									class="btn btn-primary me-3 waves-effect waves-light"><span data-i18n="個人設定">個人設定</span></a>
							</div>
						<?php } ?>
					</div>
				</div>
				
			</div>
		
		</div>
	</div>
</div>
<?php
$view->footing();
?>