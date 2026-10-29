<?php
/**
 * アプリケーション設定
 */
if (!defined('APP_ENV_LOADED')) {
	define('APP_ENV_LOADED', true);
	$envPath = __DIR__ . '/../.env';
	if (file_exists($envPath)) {
		$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '' || strpos($line, '#') === 0) {
				continue;
			}
			putenv($line);
		}
	}
}

//アプリケーション
define('APP_TYPE', 'group');

/**
 * 制限設定
 */
//表示件数
define('APP_LIMIT', '50');
//最大表示件数
define('APP_LIMITMAX', '1000');
//アップロードファイル
define('APP_FILESIZE', '10000000');
define('APP_EXTENSION', 'exe');

/**
 * 認証設定
 */
//有効期限
define('APP_EXPIRE', '9999999');
//アイドルタイム
define('APP_IDLE', '9999999');

/**
 * パス設定
 */
//アプリケーションディレクトリ
define('DIR_PATH', dirname(__FILE__).'/');
//モデルディレクトリ
define('DIR_MODEL', DIR_PATH.'model/');
//ビューディレクトリ
define('DIR_VIEW', DIR_PATH.'view/');
//ライブラリディレクトリ
define('DIR_LIBRARY', DIR_PATH.'library/');
//ファイルディレクトリ
define('DIR_UPLOAD', DIR_PATH.'upload/');

/**
 * データベース設定
 */
//データベースの種類
define('DB_STORAGE', 'mysql');

//データベースのホスト名
//define('DB_HOSTNAME', 'mysql653.db.sakura.ne.jp');
define('DB_HOSTNAME', 'localhost');
//データベース名
define('DB_DATABASE', 'guis2_group_2025');
//データベースユーザー名
define('DB_USERNAME', 'root');
// define('DB_USERNAME', 'guis2');
//データベースパスワード
// define('DB_PASSWORD', 'URtMzWthwqB5');
define('DB_PASSWORD', 'mysql');
//テーブル接頭辞
define('DB_PREFIX', 'groupware_');
//データベースポート番号
define('DB_PORT', '5432');
// define('DB_PORT', '3306');
//データベース文字コード設定
define('DB_CHARSET', 'utf8mb4');
//データベースファイル
define('DB_FILE', DIR_PATH.'database/group.sqlite2');
//郵便番号データファイル
define('DB_POSTCODE', DIR_PATH.'database/KEN_ALL.CSV');

define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT'] . '/');

define('ROOT', '/');

define('DIR_ROOT', $_SERVER['DOCUMENT_ROOT']);

define('APP_NAME', 'GUISシステム');

define('APP_COMPANY', 'GUIS Co.,Ltd');

define('APP_LOGO', 'logo-site-dark.png');

define('APP_LOGO_DARK', 'logo-site-white.png');

define('TIMECARD_START_DATE', '21');

define('ADMIN_GROUP', '4');

define('RETIRE_GROUP', '5');

define('RETIRE_GROUP_NAME', '退職者');

/**
 * Redis (ApiCache). Opt-in via .env; fail-soft when unset/unreachable.
 * REDIS_ENABLED=1|0  REDIS_HOST  REDIS_PORT  REDIS_PASSWORD  REDIS_DB  REDIS_PREFIX
 */
if (!defined('REDIS_ENABLED')) {
	$redisEnabled = getenv('REDIS_ENABLED');
	if ($redisEnabled === false || $redisEnabled === '') {
		$redisEnabled = (getenv('REDIS_HOST') !== false && getenv('REDIS_HOST') !== '') ? '1' : '0';
	}
	define('REDIS_ENABLED', $redisEnabled);
}
if (!defined('REDIS_HOST')) {
	define('REDIS_HOST', getenv('REDIS_HOST') !== false && getenv('REDIS_HOST') !== '' ? getenv('REDIS_HOST') : '127.0.0.1');
}
if (!defined('REDIS_PORT')) {
	$redisPort = getenv('REDIS_PORT');
	define('REDIS_PORT', ($redisPort !== false && $redisPort !== '') ? (int) $redisPort : 6379);
}
if (!defined('REDIS_PASSWORD')) {
	$redisPass = getenv('REDIS_PASSWORD');
	define('REDIS_PASSWORD', ($redisPass !== false) ? (string) $redisPass : '');
}
if (!defined('REDIS_DB')) {
	$redisDb = getenv('REDIS_DB');
	define('REDIS_DB', ($redisDb !== false && $redisDb !== '') ? (int) $redisDb : 0);
}
if (!defined('REDIS_PREFIX')) {
	$redisPrefix = getenv('REDIS_PREFIX');
	define('REDIS_PREFIX', ($redisPrefix !== false && $redisPrefix !== '') ? $redisPrefix : 'caily:apicache:');
}

require_once 'version.php';

error_reporting(E_ERROR | E_PARSE);
?>