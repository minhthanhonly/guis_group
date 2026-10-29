<?php
/**
 * Redis (ApiCache). Opt-in via .env; fail-soft when unset/unreachable.
 *
 * REDIS_ENABLED=1|0
 * REDIS_HOST
 * REDIS_PORT
 * REDIS_PASSWORD
 * REDIS_DB
 * REDIS_PREFIX
 *
 * Loaded from application/config.php after .env putenv.
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
