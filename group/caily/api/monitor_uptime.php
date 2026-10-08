<?php
/**
 * Same-origin proxy for monitor uptime (avoids browser CORS).
 * Source: https://caily.com.vn/monitor/public/api.php?action=uptime
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, max-age=30');

$sourceUrl = 'https://caily.com.vn/monitor/public/api.php?action=uptime';
$cacheFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'caily_monitor_uptime.json';
$cacheTtl = 30;

if (is_file($cacheFile) && (time() - (int)filemtime($cacheFile)) < $cacheTtl) {
	$cached = @file_get_contents($cacheFile);
	if ($cached !== false && $cached !== '') {
		$cachedData = json_decode($cached, true);
		if (is_array($cachedData) && !empty($cachedData['ok'])) {
			echo $cached;
			exit;
		}
	}
}

/**
 * @return array{0:?string,1:string} [body, error]
 */
function monitor_uptime_fetch($url, $verifySsl)
{
	$error = '';
	$body = '';

	if (function_exists('curl_init')) {
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 3,
			CURLOPT_SSL_VERIFYPEER => $verifySsl ? true : false,
			CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
			CURLOPT_USERAGENT => 'CAILY-MonitorProxy/1.0',
			CURLOPT_HTTPHEADER => array('Accept: application/json'),
		));
		$result = curl_exec($ch);
		$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($result === false) {
			$error = curl_error($ch) ?: 'curl_exec_failed';
		} else {
			$body = (string)$result;
			if ($httpCode < 200 || $httpCode >= 300) {
				$error = 'http_' . $httpCode;
				$body = '';
			}
		}
		curl_close($ch);
		return array($body !== '' ? $body : null, $error);
	}

	$ctx = stream_context_create(array(
		'http' => array(
			'timeout' => 10,
			'header' => "Accept: application/json\r\nUser-Agent: CAILY-MonitorProxy/1.0\r\n",
			'ignore_errors' => true,
		),
		'ssl' => array(
			'verify_peer' => $verifySsl,
			'verify_peer_name' => $verifySsl,
		),
	));
	$result = @file_get_contents($url, false, $ctx);
	if ($result === false) {
		return array(null, 'file_get_contents_failed');
	}
	return array((string)$result, '');
}

$json = null;
$lastError = '';

// Try with SSL verify, then without (common on shared hosting CA bundles)
foreach (array(true, false) as $verifySsl) {
	list($body, $err) = monitor_uptime_fetch($sourceUrl, $verifySsl);
	$lastError = $err;
	if ($body !== null && $body !== '') {
		$data = json_decode($body, true);
		if (is_array($data) && !empty($data['ok']) && isset($data['up_percent'])) {
			@file_put_contents($cacheFile, $body);
			echo $body;
			exit;
		}
		$lastError = $err !== '' ? $err : 'invalid_upstream';
	}
}

echo json_encode(array(
	'ok' => false,
	'up_percent' => null,
	'error' => 'upstream_unavailable',
	'detail' => $lastError,
));
