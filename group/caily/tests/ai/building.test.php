<?php
/**
 * From a project to its building (親案件 / tòa nhà / công trình).
 * Bug: asked about "the building of this project", the assistant had no way to get from a project
 * to its building: search_projects returned no building id and search_buildings took only an id or number.
 */
require __DIR__ . '/bootstrap.php';

$p = DB_PREFIX;
ai_test_login(ADMIN_USERID);
$t = new AiTools(new AI());
$pr = ai_test_rows("SELECT id, parent_project_id FROM {$p}projects WHERE parent_project_id > 0 ORDER BY id DESC LIMIT 1")[0];
$pid = (int) $pr['id'];
$bid = (int) $pr['parent_project_id'];
$building = ai_test_rows("SELECT * FROM {$p}parent_projects WHERE id = {$bid}")[0];

$r = $t->runRead('search_buildings', ['project_id' => $pid]);
check('the building of a project is found from the project id', isset($r['buildings']) && count($r['buildings']) === 1 && (int) $r['buildings'][0]['id'] === $bid);
$b = $r['buildings'][0] ?? [];
check('a single building comes with its details', count(array_diff(['id', 'project_number', 'project_name', 'construction_number', 'status', 'company_name', 'branch_name', 'contact_name', 'request_type', 'type1', 'scale', 'city', 'notes'], array_keys($b))) === 0);
check('details match the database', ($b['project_number'] ?? null) === $building['project_number'] && ($b['project_name'] ?? null) === $building['project_name']);
check('long building notes are shortened', mb_strlen((string) ($b['notes'] ?? '')) <= 200);
check('the answer says which project it belongs to', ($r['project_id'] ?? null) === $pid);

$byId = $t->runRead('search_buildings', ['id' => $bid]);
check('by id: same details', isset($byId['buildings'][0]['branch_name']) && (int) $byId['buildings'][0]['id'] === $bid);
$list = $t->runRead('search_buildings', ['limit' => 3]);
check('a list stays compact', count($list['buildings']) === 3 && array_slice(array_keys($list['buildings'][0]), 0, 5) === ['id', 'project_number', 'project_name', 'construction_number', 'company_name']);

$orphan = ai_test_rows("SELECT id FROM {$p}projects WHERE (parent_project_id IS NULL OR parent_project_id = 0) ORDER BY id DESC LIMIT 1");
if (count($orphan) === 1) {
    $r = $t->runRead('search_buildings', ['project_id' => (int) $orphan[0]['id']]);
    check('a project without a building says so', $r['buildings'] === [] && isset($r['note']));
}
check('an unknown project gives no building', $t->runRead('search_buildings', ['project_id' => 2000000000])['buildings'] === []);

$row = $t->runRead('search_projects', ['project_ids' => [$pid], 'fields' => ['building_id', 'building_number']])['projects'][0];
check('project rows can carry the building id and number', (int) $row['building_id'] === $bid && $row['building_number'] === $building['project_number']);

$block = (new AI())->buildSystemBlocks(['page' => '/parent_project/detail.php', 'building_id' => $bid]);
check('the building page tells the assistant which building is open', strpos($block[1]['text'], 'building id ' . $bid) !== false);
$block = (new AI())->buildSystemBlocks(['page' => '/project/detail.php', 'project_id' => $pid, 'building_id' => '9; DROP']);
check('page ids are sanitised', strpos($block[1]['text'], 'project_id ' . $pid) !== false && strpos($block[1]['text'], 'DROP') === false);
check('the prompt explains how to reach the building of a project', strpos($block[0]['text'], 'search_buildings with project_id') !== false);

$ai = new AI();
$usageStart = (int) ai_test_rows("SELECT COALESCE(MAX(id), 0) AS m FROM {$p}ai_usage")[0]['m'];
$db = new ApplicationModel();
$db->connect();
register_shutdown_function(function () use ($db, $p, $usageStart) {
    $db->query("DELETE FROM {$p}ai_usage WHERE id > {$usageStart} AND userid = '" . $db->quote(ADMIN_USERID) . "'");
});
$r = $ai->runConversation('x', [], fake([uses(['open_page' => ['page' => 'building', 'project_id' => $pid]])]));
check('open the building page of a project', $r['navigate'] === ['url' => '/parent_project/detail.php?id=' . $bid]);
$_SESSION['ai_chat'] = [];
finish();
