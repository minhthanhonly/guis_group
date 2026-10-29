<?php
/**
 * CLI smoke: shared / permission-cohort / user scopes.
 * Usage: php docs/apicache-smoke.php
 */
if (!defined('DIR_PATH')) {
    define('DIR_PATH', dirname(__DIR__) . '/application/');
}
if (!defined('DB_PREFIX')) {
    define('DB_PREFIX', 'groupware_');
}
require_once dirname(__DIR__) . '/application/library/ApiCache.php';

$_SESSION = array(
    'userid' => 'alice',
    'id' => 10,
    'authority' => 'member',
    'group' => '2',
);

$sDept = ApiCache::cacheScope('department', 'list_department');
$sProj = ApiCache::cacheScope('project', 'list');
$sTask = ApiCache::cacheScope('task', 'getPermission');
$sMy = ApiCache::cacheScope('project', 'list', array('my_projects' => '1'));
echo "scopes dept={$sDept} project={$sProj} task={$sTask} my_projects={$sMy}\n";

$oAlice = ApiCache::cacheOwnerId('alice', 'project', 'list');
$_SESSION['userid'] = 'bob';
$_SESSION['id'] = 11;
// Same session authority/group but fingerprint falls back without DB → includes userid in fallback
// With fallback_userid different users get different fp — OK without DB.
$fpAliceSession = array('authority' => 'member', 'group' => '2');
echo 'owner_project_alice=' . $oAlice . "\n";

$body = json_encode(array(
    'data' => array(
        array('id' => 1, 'is_favorite' => 1, 'name' => 'A'),
        array('id' => 2, 'is_favorite' => 1, 'name' => 'B'),
    ),
));
$stripped = ApiCache::stripUserFavorites($body);
$sd = json_decode($stripped, true);
$stripOk = ((int)$sd['data'][0]['is_favorite'] === 0) && ((int)$sd['data'][1]['is_favorite'] === 0);
echo 'strip_favorites=' . ($stripOk ? 'yes' : 'no') . "\n";

$uncache = !ApiCache::isCacheable('storage', 'folderadd')
    && !ApiCache::isCacheable('forum', 'commentadd')
    && !ApiCache::isCacheable('timecard', 'checkin')
    && !ApiCache::isCacheable('project', 'upsert_project_public')
    && !ApiCache::isCacheable('todo', 'api_reorder')
    && ApiCache::isCacheable('project', 'list');
echo 'denylist_writes=' . ($uncache ? 'yes' : 'no') . "\n";

$bumpNonGet = ApiCache::shouldBumpAfterRequest(false, 'storage', 'folderadd');
$bumpGetWrite = ApiCache::shouldBumpAfterRequest(true, 'storage', 'folderadd');
$noBumpGetList = !ApiCache::shouldBumpAfterRequest(true, 'project', 'list');
echo 'should_bump nonGET=' . ($bumpNonGet ? 'yes' : 'no')
    . ' getWrite=' . ($bumpGetWrite ? 'yes' : 'no')
    . ' getListSkip=' . ($noBumpGetList ? 'yes' : 'no') . "\n";

$vProjBefore = (int) ApiCache::getScopeVersion('project');
$vParentBefore = (int) ApiCache::getScopeVersion('parentproject');
$vTaskBefore = (int) ApiCache::getScopeVersion('task');
ApiCache::bumpOnWrite('project');
$relatedOk = ((int) ApiCache::getScopeVersion('project') === $vProjBefore + 1)
    && ((int) ApiCache::getScopeVersion('parentproject') === $vParentBefore + 1)
    && ((int) ApiCache::getScopeVersion('task') === $vTaskBefore + 1);
echo 'bumpOnWrite_related=' . ($relatedOk ? 'yes' : 'no') . "\n";

$ok = ($sDept === ApiCache::SCOPE_SHARED)
    && ($sProj === ApiCache::SCOPE_PERMISSION)
    && ($sTask === ApiCache::SCOPE_USER)
    && ($sMy === ApiCache::SCOPE_USER)
    && $stripOk
    && (strpos($oAlice, 'p') === 0)
    && $uncache
    && $bumpNonGet
    && $bumpGetWrite
    && $noBumpGetList
    && $relatedOk;
echo $ok ? "PASS\n" : "FAIL\n";
exit($ok ? 0 : 1);
