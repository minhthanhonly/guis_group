<?php
/**
 * CLI smoke: file-backend ApiCache version bump + get/set/invalidate.
 * Usage: php docs/redis-apicache-smoke.php
 */
putenv('REDIS_ENABLED=0');
if (!defined('REDIS_ENABLED')) {
    define('REDIS_ENABLED', '0');
}
if (!defined('DIR_PATH')) {
    define('DIR_PATH', dirname(__DIR__) . '/application/');
}
require_once dirname(__DIR__) . '/application/library/ApiCache.php';

$v1 = ApiCache::getScopeVersion('department');
ApiCache::bumpScopeVersion('department');
$v2 = ApiCache::getScopeVersion('department');
$k1 = ApiCache::getKey('u1', 'department', 'list_department', ['a' => 1]);
ApiCache::bumpScopeVersion('department');
$k2 = ApiCache::getKey('u1', 'department', 'list_department', ['a' => 1]);

echo "v1={$v1} v2={$v2} key_changed=" . ($k1 !== $k2 ? 'yes' : 'no') . PHP_EOL;

ApiCache::set($k2, 'u1', '{"ok":1}', 60);
$got = ApiCache::get($k2, 'u1');
echo 'get=' . ($got === '{"ok":1}' ? 'ok' : 'fail') . PHP_EOL;

ApiCache::invalidateUser('u1');
$got2 = ApiCache::get($k2, 'u1');
echo 'after_invalidate=' . ($got2 === false ? 'miss' : 'hit') . PHP_EOL;

$ok = ($v2 > $v1) && ($k1 !== $k2) && ($got === '{"ok":1}') && ($got2 === false);
echo $ok ? "PASS\n" : "FAIL\n";
exit($ok ? 0 : 1);
