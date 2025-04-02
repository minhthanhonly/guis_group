<?php

require_once('application/loader.php');
$authority = new Authority;
$error = $authority->login();
$view->heading('ログイン', 'login');
?>
<div class="authentication-wrapper authentication-cover">
    <!-- Logo -->
	<a href="index.html" class="app-brand auth-cover-brand">
		<span class="app-brand-logo demo">
			<span class="text-primary">
				<img src="assets/img/<?=APP_LOGO?>" alt="" width="80"
				data-app-light-img="<?=APP_LOGO?>"
				data-app-dark-img="<?=APP_LOGO_DARK?>" />
			</span>
		</span>
	</a>
	<!-- /Logo -->
	<div class="authentication-inner row m-0">
	<!-- /Left Text -->
	<div class="d-none d-xl-flex col-xl-8 p-0">
		<div class="auth-cover-bg d-flex justify-content-center align-items-center">
		<img
			src="assets/img/illustrations/auth-register-illustration-light.png"
			alt="auth-login-cover"
			class="my-5 auth-illustration"
			data-app-light-img="illustrations/auth-register-illustration-light.png"
			data-app-dark-img="illustrations/auth-register-illustration-dark.png" />
		<img
			src="assets/img/illustrations/bg-shape-image-light.png"
			alt="auth-login-cover"
			class="platform-bg"
			data-app-light-img="illustrations/bg-shape-image-light.png"
			data-app-dark-img="illustrations/bg-shape-image-dark.png" />
		</div>
	</div>
	<!-- /Left Text -->

	<!-- Login -->
	<div class="d-flex col-12 col-xl-4 align-items-center authentication-bg p-sm-12 p-6">
		<div class="w-px-400 mx-auto mt-12 pt-5">
		<h4 class="mb-1">GUIS HUBへようこそ！ 👋</h4>
		<p class="mb-6">アカウントにサインインして、冒険を始めてください。</p>

		<form id="formAuthentication" class="mb-6" action="login.php" name="login" method="POST">
			<?php if($view->error($error) != '') { 
				echo '<div class="alert alert-danger" role="alert">'.$view->error($error).'</div>';
			} ?>
			<div class="mb-6 form-control-validation">
				<label for="userid" class="form-label">ユーザー名</label>
				<input
					type="text"
					class="form-control"
					id="userid"
					name="userid" class="logininput" value="<?=$view->escape($_POST['userid'])?>"
					placeholder="ユーザー名を入力してください"
					autocomplete="username"
					autofocus />
			</div>
			<div class="mb-6 form-password-toggle form-control-validation">
				<label class="form-label" for="password">パスワード</label>
				<div class="input-group input-group-merge">
					<input
					type="password"
					id="password"
					class="form-control"
					name="password"
					autocomplete="current-password"
					
					placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
					aria-describedby="password" />
					<span class="input-group-text cursor-pointer"><i class="icon-base ti tabler-eye-off"></i></span>
				</div>
			</div>
			<div class="my-8">
				<div class="d-flex justify-content-between">
					<div class="form-check mb-0 ms-2">
						<input class="form-check-input" name="remember_me" type="checkbox" id="remember-me" />
						<label class="form-check-label" for="remember-me"> Remember Me </label>
					</div>
					<!-- <a href="auth-forgot-password-cover.html">
					<p class="mb-0">Forgot Password?</p>
					</a> -->
				</div>
			</div>
			<button class="btn btn-primary d-grid w-100">ログイン</button>
		</form>
		</div>
	</div>
	<!-- /Login -->
	</div>
</div>

<?php
$view->footing();
?>
<script src="<?=ROOT?>assets/js/login.js"></script>