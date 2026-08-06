<?php
/**
 * Post-login waiting screen for GUIS Plus Electron app.
 * App captures session cookies, then closes this window.
 */
require_once('application/loader.php');

$userid = isset($_SESSION['userid']) ? (string) $_SESSION['userid'] : '';
$realname = isset($_SESSION['realname']) ? (string) $_SESSION['realname'] : '';
$view->heading('ログイン完了', 'login');
?>
<div class="authentication-wrapper authentication-cover">
	<a href="index.php" class="app-brand auth-cover-brand">
		<span class="app-brand-logo demo">
			<span class="text-primary">
				<img src="assets/img/<?=APP_LOGO_DARK?>" alt="" width="80"
					data-app-light-img="<?=APP_LOGO?>"
					data-app-dark-img="<?=APP_LOGO_DARK?>" />
			</span>
		</span>
	</a>
	<div class="authentication-inner row m-0">
		<div class="d-none d-xl-flex col-xl-8 p-0">
			<div class="auth-cover-bg d-flex justify-content-center align-items-center">
				<img
					src="assets/img/illustrations/auth-register-illustration-light.png"
					alt="auth-wait-cover"
					class="my-5 auth-illustration"
					data-app-light-img="illustrations/auth-register-illustration-light.png"
					data-app-dark-img="illustrations/auth-register-illustration-dark.png" />
				<img
					src="assets/img/illustrations/bg-shape-image-light.png"
					alt="auth-wait-cover"
					class="platform-bg"
					data-app-light-img="illustrations/bg-shape-image-light.png"
					data-app-dark-img="illustrations/bg-shape-image-dark.png" />
			</div>
		</div>
		<div class="d-flex col-12 col-xl-4 align-items-center authentication-bg p-sm-12 p-6">
			<div class="w-px-400 mx-auto mt-12 pt-5 text-center">
				<div class="spinner-border text-primary mb-4" role="status" aria-hidden="true"></div>
				<h4 class="mb-2" data-i18n="少々お待ちください">少々お待ちください</h4>
				<p class="mb-1 text-muted" data-i18n="ログイン処理中です。このウィンドウは自動的に閉じます。">
					ログイン処理中です。このウィンドウは自動的に閉じます。
				</p>
				<p class="small text-muted mb-0" data-i18n="Vui lòng đợi… GUIS Plus đang xác nhận phiên đăng nhập.">
					Vui lòng đợi… GUIS Plus đang xác nhận phiên đăng nhập.
				</p>
				<!-- Hint for GUIS Plus probe (Electron captures userid cookie + this) -->
				<script>
					window.currentUserName = <?= json_encode($userid, JSON_UNESCAPED_UNICODE) ?>;
					window.__GUIS_PLUS_LOGIN_WAIT__ = true;
					window.__GUIS_PLUS_USERID__ = <?= json_encode($userid, JSON_UNESCAPED_UNICODE) ?>;
					window.__GUIS_PLUS_REALNAME__ = <?= json_encode($realname, JSON_UNESCAPED_UNICODE) ?>;
				</script>
			</div>
		</div>
	</div>
</div>
<?php
$view->footing();
?>
