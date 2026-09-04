<?php


class Authority
{
	const REMEMBER_ME_DAYS = 30;
	const SESSION_ACCESS_WRITE_INTERVAL = 60;

	function __construct()
	{
		session_name(APP_TYPE . 'sid');
		if (!isset($_SESSION)) {
			// Persist groupsid across Electron restarts / multi-window SSO (was 0 unless remember_me already set)
			$lifetime = self::REMEMBER_ME_DAYS * 24 * 60 * 60;
			$this->applySessionCookieParams($lifetime);
			session_start();
		}
	}

	/**
	 * Cookie params shared by PHP session + remember_me (SameSite=Lax for Electron BrowserWindow).
	 */
	private function buildCookieParams($expiresOrLifetime, $isLifetimeLifetime = false)
	{
		$secure = $this->isHttps();
		if ($isLifetimeTime) {
			$lifetime = (int) $expiresOrLifetime;
			if (PHP_VERSION_ID >= 70300) {
				return array(
					'lifetime' => $lifetime,
					'path' => '/',
					'domain' => '',
					'secure' => $secure,
					'httponly' => true,
					'samesite' => 'Lax',
				);
			}
			return null; // use legacy 5-arg API
		}

		$expires = (int) $expiresOrLifetime;
		if (PHP_VERSION_ID >= 70300) {
			return array(
				'expires' => $expires,
				'path' => '/',
				'domain' => '',
				'secure' => $secure,
				'httponly' => true,
				'samesite' => 'Lax',
			);
		}
		return null;
	}

	private function applySessionCookieParams($lifetime)
	{
		$params = $this->buildCookieParams($lifetime, true);
		if (is_array($params)) {
			session_set_cookie_params($params);
			return;
		}
		session_set_cookie_params(
			(int) $lifetime,
			'/',
			'',
			$this->isHttps(),
			true
		);
	}

	/**
	 * Fast path: validate session in memory first; DB lookup only when session is missing/invalid.
	 */
	function ensureAuthenticated()
	{
		if ($this->authorize() === true) {
			return true;
		}
		return $this->checkRememberMe() === true;
	}

	function check()
	{
		if ($this->ensureAuthenticated()) {
			return;
		}
		if (basename($_SERVER['SCRIPT_NAME']) != 'login.php') {
			$_SESSION['referer'] = $_SERVER['REQUEST_URI'];
			header('Location:' . ROOT . 'login.php');
			exit();
		}
	}

	function authorize()
	{
		$authorized = false;
		if (!isset($_SESSION['authorized'])) {
			return false;
		}
		if (!isset($_SESSION['logintime']) || $_SESSION['authorized'] !== md5(__FILE__ . $_SESSION['logintime'])) {
			$this->clearAuthSession();
			return false;
		}
		if (APP_EXPIRE > 0 && (time() - $_SESSION['logintime']) > APP_EXPIRE) {
			$this->clearAuthSession('expire');
			return false;
		}
		if (APP_IDLE > 0 && (time() - $_SESSION['accesstime']) > APP_IDLE) {
			$this->clearAuthSession('idle');
			return false;
		}
		$authorized = true;
		$now = time();
		$lastAccess = isset($_SESSION['accesstime']) ? (int)$_SESSION['accesstime'] : 0;
		if ($now - $lastAccess >= self::SESSION_ACCESS_WRITE_INTERVAL) {
			$_SESSION['accesstime'] = $now;
		}
		return $authorized;
	}

	function login()
	{

		$authorized = false;
		$error = array();
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			if (strlen($_POST['userid']) > 0) {
				if (preg_match('/^[-_\.a-zA-Z0-9]+$/', $_POST['userid'])) {
					$postuserid = trim($_POST['userid']);
				} else {
					$error[] = 'ユーザー名は半角英数字で入力してください。';
				}
				// Allow all printable characters including special characters
				if (preg_match('/^[\x20-\x7E]*$/', $_POST['password'])) {
					$password = md5(trim($_POST['password']));
				} else {
					$error[] = 'パスワードに無効な文字が含まれています。';
				}
				if ($postuserid != '' && count($error) <= 0) {
					$connection = new Connection;
					$query = sprintf(
						"SELECT id,userid,password,firstname,lastname,realname,user_ruby,user_group,user_groupname,authority,user_image,show_project,is_soumu,updated FROM %suser WHERE userid = '%s'",
						DB_PREFIX,
						$connection->quote($postuserid)
					);
					$data = $connection->fetchOne($query);
					$connection->close();
					if (count($data) > 0 && $data['userid'] === $postuserid && $data['password'] === $password) {
						$authorized = true;

						if (isset($_POST['remember_me'])) {
							$token = bin2hex(random_bytes(16));
							$this->setRememberMeCookie($token);
							$connection = new Connection;
							$query = sprintf(
								"UPDATE %suser SET remember_token = '%s' WHERE userid = '%s'",
								DB_PREFIX,
								$connection->quote($token),
								$connection->quote($postuserid)
							);
							$connection->query($query);
							$connection->close();
						}
					} else {
						$error[] = 'ユーザー名もしくはパスワードが異なります。';
					}
				}
			} else {
				$error[] = 'ユーザー名を入力してください。';
			}
		} elseif (isset($_SESSION['status'])) {
			if ($_SESSION['status'] == 'idle') {
				$error[] = '自動的にログアウトしました。<br />ログインしなおしてください。';
			} elseif ($_SESSION['status'] == 'expire') {
				$error[] = 'ログインの有効期限が切れました。<br />ログインしなおしてください。';
			}
			unset($_SESSION['status']);
		}
		if ($authorized === true && count($error) <= 0) {
			session_regenerate_id(true);
			$this->populateSessionFromUser($data);
			// Persist session cookie (30d) so GUIS Plus ArchLib/Kanri windows share login
			$this->refreshSessionCookieLifetime(true);

			if ($this->isGuisPlusAppRequest()) {
				unset($_SESSION['referer']);
				header('Location: ' . ROOT . 'login-wait.php');
				exit();
			}

			if (isset($_SESSION['referer'])) {
				header('Location: ' . $_SESSION['referer']);
				unset($_SESSION['referer']);
			} else {
				header('Location: index.php');
			}
			exit();
		} else {
			return $error;
		}

	}

	/**
	 * Login initiated from GUIS Plus Electron (query/body from=guis_plus, or Electron UA).
	 */
	function isGuisPlusAppRequest()
	{
		$from = '';
		if (isset($_POST['from'])) {
			$from = (string) $_POST['from'];
		} elseif (isset($_GET['from'])) {
			$from = (string) $_GET['from'];
		}
		$from = strtolower(trim($from));
		if ($from === 'guis_plus' || $from === 'guis-plus' || $from === 'guisplus' || $from === '1') {
			return true;
		}
		$ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
		if ($ua !== '' && stripos($ua, 'Electron') !== false && stripos($ua, 'GUIS') !== false) {
			return true;
		}
		return false;
	}

	function checkRememberMe()
	{
		if (!isset($_COOKIE['remember_me']) || $_COOKIE['remember_me'] === '') {
			return false;
		}
		$token = $_COOKIE['remember_me'];
		$connection = new Connection;
		$query = sprintf(
			"SELECT id,userid,firstname,lastname,realname,user_ruby,user_group,user_groupname,authority,user_image,show_project,is_soumu,updated FROM %suser WHERE remember_token = '%s' LIMIT 1",
			DB_PREFIX,
			$connection->quote($token)
		);
		$data = $connection->fetchOne($query);
		$connection->close();

		if (count($data) <= 0) {
			$this->clearRememberMeCookie();
			return false;
		}

		session_regenerate_id(true);
		$this->populateSessionFromUser($data);
		$this->refreshSessionCookieLifetime(true);
		return true;
	}

	function logout()
	{
		$userId = isset($_SESSION['userid']) ? $_SESSION['userid'] : '';
		if ($userId !== '') {
			$connection = new Connection;
			$query = sprintf(
				"UPDATE %suser SET remember_token = NULL WHERE userid = '%s'",
				DB_PREFIX,
				$connection->quote($userId)
			);
			$connection->query($query);
			$connection->close();
		}

		$this->sessionDestroy();
		$this->clearRememberMeCookie();

		header('Location:' . ROOT . 'login.php');
		exit();

	}

	function sessionDestroy()
	{
		$userId = isset($_SESSION['userid']) ? $_SESSION['userid'] : '';
		if ($userId !== '' && defined('DIR_LIBRARY') && file_exists(DIR_LIBRARY . 'ApiCache.php')) {
			require_once DIR_LIBRARY . 'ApiCache.php';
			ApiCache::invalidateUser($userId);
		}

		$_SESSION = array();
		if (isset($_COOKIE[session_name()])) {
			$params = $this->buildCookieParams(time() - 42000, false);
			if (is_array($params)) {
				setcookie(session_name(), '', $params);
			} else {
				setcookie(session_name(), '', time() - 42000, '/', '', $this->isHttps(), true);
			}
		}
		session_destroy();
	}

	private function populateSessionFromUser($data)
	{
		unset($_SESSION['_suspend_checked_at'], $_SESSION['_pm_checked_at']);
		$_SESSION['logintime'] = time();
		$_SESSION['accesstime'] = $_SESSION['logintime'];
		$_SESSION['authorized'] = md5(__FILE__ . $_SESSION['logintime']);
		$_SESSION['session_version'] = SESSION_VERSION;
		$_SESSION['userid'] = $data['userid'];
		$_SESSION['id'] = $data['id'];
		$_SESSION['lastname'] = $data['lastname'];
		$_SESSION['firstname'] = $data['firstname'];
		$_SESSION['realname'] = $data['realname'];
		$_SESSION['user_ruby'] = isset($data['user_ruby']) ? $data['user_ruby'] : '';
		$_SESSION['group'] = $data['user_group'];
		$_SESSION['authority'] = $data['authority'];
		$_SESSION['user_image'] = $data['user_image'];
		$_SESSION['user_groupname'] = isset($data['user_groupname']) ? $data['user_groupname'] : '';
		$_SESSION['show_project'] = $data['show_project'];
		$_SESSION['is_soumu'] = isset($data['is_soumu']) ? $data['is_soumu'] : 0;
		$_SESSION['user_updated'] = isset($data['updated']) ? (string) $data['updated'] : '';
	}

	private function clearAuthSession($status = null)
	{
		$referer = isset($_SESSION['referer']) ? $_SESSION['referer'] : null;
		$_SESSION = array();
		if ($status !== null) {
			$_SESSION['status'] = $status;
		}
		if ($referer !== null) {
			$_SESSION['referer'] = $referer;
		}
	}

	private function setRememberMeCookie($token)
	{
		$expires = time() + (self::REMEMBER_ME_DAYS * 24 * 60 * 60);
		$params = $this->buildCookieParams($expires, false);
		if (is_array($params)) {
			setcookie('remember_me', $token, $params);
			return;
		}
		setcookie(
			'remember_me',
			$token,
			$expires,
			'/',
			'',
			$this->isHttps(),
			true
		);
	}

	private function clearRememberMeCookie()
	{
		$params = $this->buildCookieParams(time() - 3600, false);
		if (is_array($params)) {
			setcookie('remember_me', '', $params);
		} else {
			setcookie('remember_me', '', time() - 3600, '/', '', $this->isHttps(), true);
		}
		unset($_COOKIE['remember_me']);
	}

	private function refreshSessionCookieLifetime($rememberMe)
	{
		// Always refresh persistent lifetime so Electron multi-window SSO keeps groupsid
		$expires = time() + (self::REMEMBER_ME_DAYS * 24 * 60 * 60);
		$params = $this->buildCookieParams($expires, false);
		if (is_array($params)) {
			setcookie(session_name(), session_id(), $params);
			return;
		}
		setcookie(
			session_name(),
			session_id(),
			$expires,
			'/',
			'',
			$this->isHttps(),
			true
		);
	}

	private function isHttps()
	{
		return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
	}

}

?>
