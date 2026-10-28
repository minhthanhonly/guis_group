<?php

/**
 * Site-wide maintenance mode.
 * State file is local (not in git). Missing or unreadable file means off.
 */
class MaintenanceMode
{
	const MESSAGE_MAX = 500;

	private static $cache = null;
	private static $cacheMtime = null;

	public static function statePath()
	{
		return dirname(__DIR__) . '/storage/maintenance.json';
	}

	public static function isEnabled()
	{
		$state = self::read();
		return !empty($state['enabled']);
	}

	public static function message()
	{
		$state = self::read();
		$message = isset($state['message']) ? trim((string) $state['message']) : '';
		if ($message === '') {
			return 'ただいまシステムのメンテナンスを行っています。しばらくしてから再度お試しください。';
		}
		return $message;
	}

	public static function read()
	{
		$path = self::statePath();
		if (!is_file($path)) {
			self::$cache = array('enabled' => false, 'message' => '', 'updated_at' => '', 'updated_by' => '');
			self::$cacheMtime = null;
			return self::$cache;
		}

		$mtime = @filemtime($path);
		if (self::$cache !== null && self::$cacheMtime === $mtime) {
			return self::$cache;
		}

		$raw = @file_get_contents($path);
		$data = is_string($raw) ? json_decode($raw, true) : null;
		if (!is_array($data)) {
			self::$cache = array('enabled' => false, 'message' => '', 'updated_at' => '', 'updated_by' => '');
			self::$cacheMtime = $mtime;
			return self::$cache;
		}

		self::$cache = array(
			'enabled' => !empty($data['enabled']),
			'message' => isset($data['message']) ? (string) $data['message'] : '',
			'updated_at' => isset($data['updated_at']) ? (string) $data['updated_at'] : '',
			'updated_by' => isset($data['updated_by']) ? (string) $data['updated_by'] : '',
		);
		self::$cacheMtime = $mtime;
		return self::$cache;
	}

	/**
	 * @return string empty on success, otherwise an error message
	 */
	public static function save($enabled, $message, $updatedBy)
	{
		$message = trim((string) $message);
		if (function_exists('mb_strlen') && mb_strlen($message) > self::MESSAGE_MAX) {
			return 'メッセージは' . self::MESSAGE_MAX . '文字以内で入力してください。';
		}
		if (strlen($message) > self::MESSAGE_MAX * 4) {
			return 'メッセージは' . self::MESSAGE_MAX . '文字以内で入力してください。';
		}
		$message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $message);

		$dir = dirname(self::statePath());
		if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
			return '設定を保存できません。storage ディレクトリを作成できません。';
		}

		$payload = json_encode(array(
			'enabled' => (bool) $enabled,
			'message' => $message,
			'updated_at' => date('Y-m-d H:i:s'),
			'updated_by' => (string) $updatedBy,
		), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		if ($payload === false) {
			return '設定を保存できません。';
		}
		$payload .= "\n";

		$path = self::statePath();
		$tmp = $path . '.' . uniqid('tmp', true);
		if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
			return '設定を保存できません。storage ディレクトリの書き込み権限を確認してください。';
		}
		if (is_file($path)) {
			@unlink($path);
		}
		if (!@rename($tmp, $path)) {
			@unlink($tmp);
			return '設定を保存できません。storage ディレクトリの書き込み権限を確認してください。';
		}

		self::$cache = null;
		self::$cacheMtime = null;
		clearstatcache(true, $path);
		return '';
	}

	public static function canBypass()
	{
		return (($_SESSION['authority'] ?? '') === 'administrator');
	}

	public static function userCanBypass($user)
	{
		return is_array($user) && (($user['authority'] ?? '') === 'administrator');
	}

	/**
	 * Block key-based / app APIs that do not go through the logged-in API.
	 * Authenticated /api/index.php is checked after login instead.
	 */
	public static function blockPublicApi()
	{
		if (PHP_SAPI === 'cli' || !self::isEnabled()) {
			return;
		}
		$script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
		if (!preg_match('#/api/[^/]+\.php$#', $script)) {
			return;
		}
		if (substr($script, -13) === '/api/index.php' || substr($script, -22) === '/api/monitor_uptime.php') {
			return;
		}
		self::deny(true);
	}

	public static function deny($asJson)
	{
		http_response_code(503);
		header('Retry-After: 300');
		header('Cache-Control: no-store');
		$message = self::message();
		if ($asJson) {
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode(array(
				'status' => 'error',
				'error' => $message,
				'maintenance' => true,
			), JSON_UNESCAPED_UNICODE);
			exit;
		}
		self::renderPage($message);
		exit;
	}

	private static function renderPage($message)
	{
		$root = defined('ROOT') ? ROOT : '/';
		$appName = defined('APP_NAME') ? APP_NAME : 'CAILY';
		$safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
		$safeName = htmlspecialchars($appName, ENT_QUOTES, 'UTF-8');
		$loggedIn = !empty($_SESSION['userid']);
		$loginHref = htmlspecialchars($root . 'login.php', ENT_QUOTES, 'UTF-8');
		$logoutHref = htmlspecialchars($root . 'logout.php', ENT_QUOTES, 'UTF-8');
		$css = htmlspecialchars($root . 'assets/vendor/css/core.css', ENT_QUOTES, 'UTF-8');
		$pageCss = htmlspecialchars($root . 'assets/vendor/css/pages/page-misc.css', ENT_QUOTES, 'UTF-8');
		$img = htmlspecialchars($root . 'assets/img/illustrations/page-misc-under-maintenance.png', ENT_QUOTES, 'UTF-8');
		$icon = htmlspecialchars($root . 'assets/img/favicon/favicon.ico', ENT_QUOTES, 'UTF-8');
		$actionHref = $loggedIn ? $logoutHref : $loginHref;
		$actionLabel = $loggedIn ? 'ログアウト' : '管理者ログイン';

		header('Content-Type: text/html; charset=UTF-8');
		echo '<!doctype html><html lang="ja" class="layout-wide" data-bs-theme="dark"><head><meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>メンテナンス中 | ' . $safeName . '</title>';
		echo '<link rel="icon" href="' . $icon . '">';
		echo '<link rel="stylesheet" href="' . $css . '">';
		echo '<link rel="stylesheet" href="' . $pageCss . '">';
		echo '</head><body>';
		echo '<div class="container-xxl container-p-y"><div class="misc-wrapper">';
		echo '<h2 class="mb-2 mx-2">メンテナンス中</h2>';
		echo '<p class="mb-6 mx-2" style="max-width:36rem;">' . nl2br($safeMessage) . '</p>';
		echo '<a href="' . $actionHref . '" class="btn btn-primary mb-10">' . $actionLabel . '</a>';
		echo '<div class="mt-4"><img src="' . $img . '" alt="" width="280" class="img-fluid"></div>';
		echo '</div></div></body></html>';
	}
}
