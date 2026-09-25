<?php
define('APP_VERSION', '2.2.91');
define('CACHE_VERSION', APP_VERSION);
define('PROJECT_CACHE_VERSION', '1.4.58');
define('STATS_CACHE_VERSION', '1.0.26');

//セッションバージョン (変更すると全ユーザーがログアウトされます)
define('SESSION_VERSION', '6');


$host = strtolower(preg_replace('/:\\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')));
$productionHosts = array('kanri.guis.co.jp', 'group.caily.com.vn');
$isProductionHost = in_array($host, $productionHosts, true);

if (!defined('APP_ENV')) {
	$appEnv = getenv('APP_ENV');
	if ($appEnv === false || $appEnv === '') {
		$appEnv = $isProductionHost ? 'production' : 'test';
	}
	define('APP_ENV', $appEnv);
}
if (!defined('SHOW_TEST_BANNER')) {
	define(
		'SHOW_TEST_BANNER',
		!$isProductionHost && in_array(APP_ENV, array('test', 'local', 'demo'), true)
	);
}

?>