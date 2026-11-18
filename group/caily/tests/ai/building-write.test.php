<?php
/**
 * Building writes: duplicate warning before creating, version lock when updating, notes on a building.
 * Changes one building's `city` and adds one note in the local database, then puts everything back.
 */
require __DIR__ . '/bootstrap.php';

class BuildingTestAI extends AI {
    public $body = [];
    protected function readJsonBody(): array {
        return $this->body;
    }
    public function confirm($token) {
        $this->body = ['token' => $token];
        ob_start();
        $this->executeAction();
        return json_decode(ob_get_clean(), true);
    }
}

$p = DB_PREFIX;
ai_test_login(ADMIN_USERID);
$db = new ApplicationModel();
$db->connect();
(new AI())->ensureUsageTable();
$b = ai_test_rows("SELECT * FROM {$p}parent_projects WHERE status != 'deleted' AND construction_number <> '' AND project_name <> '' ORDER BY id DESC LIMIT 1")[0];
$bid = (int) $b['id'];
$start = [
    'usage' => (int) ai_test_rows("SELECT COALESCE(MAX(id), 0) AS m FROM {$p}ai_usage")[0]['m'],
    'notes' => (int) ai_test_rows("SELECT COALESCE(MAX(id), 0) AS m FROM {$p}parent_project_notes")[0]['m'],
    'logs' => (int) ai_test_rows("SELECT COALESCE(MAX(id), 0) AS m FROM {$p}parent_projects_logs")[0]['m'],
];
register_shutdown_function(function () use ($db, $p, $b, $bid, $start) {
    $db->query("UPDATE {$p}parent_projects SET city = '" . $db->quote((string) $b['city']) . "', version = " . (int) $b['version'] . ", updated_at = '" . $db->quote((string) $b['updated_at']) . "', updated_by = " . ($b['updated_by'] === null ? 'NULL' : "'" . $db->quote((string) $b['updated_by']) . "'") . " WHERE id = {$bid}");
    $db->query("DELETE FROM {$p}parent_projects_logs WHERE id > {$start['logs']} AND parent_project_id = {$bid}");
    $db->query("DELETE FROM {$p}parent_project_notes WHERE id > {$start['notes']} AND content LIKE '%[ai-test]%'");
    $db->query("DELETE FROM {$p}ai_usage WHERE id > {$start['usage']} AND userid IN ('" . $db->quote(ADMIN_USERID) . "', '" . $db->quote(NORMAL_USERID) . "')");
});
$row = function () use ($p, $bid) {
    return ai_test_rows("SELECT city, version FROM {$p}parent_projects WHERE id = {$bid}")[0];
};
$propose = function (array $actions) {
    $ai = new BuildingTestAI();
    $r = $ai->runConversation('x', [], fake([uses(['propose_actions' => ['summary' => 's', 'actions' => $actions]])]));
    return [$ai, $r];
};

// ---- Duplicate warning when creating ----
list(, $r) = $propose([['type' => 'parent_project_create', 'params' => ['company_name' => 'テスト', 'project_name' => '新しい建物 ' . uniqid(), 'construction_number' => $b['construction_number']]]]);
check('same construction number: a card with a warning that names the existing building', is_array($r['pending']) && count($r['pending']['warnings']) === 1 && strpos($r['pending']['warnings'][0], (string) $b['project_number']) !== false);
list(, $r) = $propose([['type' => 'parent_project_create', 'params' => ['company_name' => 'テスト', 'project_name' => $b['project_name']]]]);
check('same owner name: warned as well', is_array($r['pending']) && count($r['pending']['warnings']) === 1);
list(, $r) = $propose([['type' => 'parent_project_create', 'params' => ['company_name' => 'テスト', 'project_name' => '重複なし ' . uniqid(), 'construction_number' => 'NODUP-' . uniqid()]]]);
check('no duplicate: no warning', is_array($r['pending']) && $r['pending']['warnings'] === []);
check('nothing was created by proposing', (int) ai_test_rows("SELECT COUNT(*) AS c FROM {$p}parent_projects WHERE company_name = 'テスト' AND project_name LIKE '重複なし%'")[0]['c'] === 0);
$_SESSION['ai_pending'] = [];

// ---- Version lock when updating ----
list($ai, $r) = $propose([['type' => 'parent_project_update', 'id' => $bid, 'version' => 999999, 'params' => ['city' => '[ai-test] A']]]);
$stored = $_SESSION['ai_pending'][$r['pending']['token']]['actions'][0];
check('building version stamped from the database', isset($stored['version']) && $stored['version'] === (int) $b['version']);
$res = $ai->confirm($r['pending']['token']);
check('confirmed update is saved', ($res['status'] ?? '') === 'success' && $row()['city'] === '[ai-test] A' && (int) $row()['version'] === (int) $b['version'] + 1);

list($ai, $r) = $propose([['type' => 'parent_project_update', 'id' => $bid, 'params' => ['city' => '[ai-test] B']]]);
$db->query("UPDATE {$p}parent_projects SET version = version + 1 WHERE id = {$bid}");
$res = $ai->confirm($r['pending']['token']);
check('someone else saved in between: the update is refused', ($res['status'] ?? '') !== 'success' && $row()['city'] === '[ai-test] A');

list($ai, $r) = $propose([
    ['type' => 'parent_project_update', 'id' => $bid, 'params' => ['city' => '[ai-test] C']],
    ['type' => 'parent_project_update', 'id' => $bid, 'params' => ['city' => '[ai-test] D']],
]);
$res = $ai->confirm($r['pending']['token']);
check('two updates of one building in one confirmation both run', ($res['status'] ?? '') === 'success' && $row()['city'] === '[ai-test] D');

$child = ai_test_rows("SELECT id FROM {$p}projects WHERE parent_project_id = {$bid} ORDER BY id LIMIT 1");
if (count($child) === 1) {
    list(, $r) = $propose([['type' => 'parent_project_update', 'params' => ['project_id' => (int) $child[0]['id'], 'city' => '[ai-test] E']]]);
    $stored = $_SESSION['ai_pending'][$r['pending']['token']]['actions'][0];
    check('building reached through its project is stamped too', isset($stored['version']) && $stored['version'] === (int) $row()['version']);
}
$_SESSION['ai_pending'] = [];

// ---- Notes on a building ----
$t = new AiTools(new AI());
check('building note needs the building id and text', $t->validateActions([['type' => 'building_note_add', 'params' => ['content' => 'x']]])['ok'] === false && $t->validateActions([['type' => 'building_note_add', 'id' => $bid, 'params' => ['content' => ' ']]])['ok'] === false && $t->validateActions([['type' => 'building_note_add', 'id' => $bid, 'params' => ['content' => 'x']]])['ok'] === true);
check('a building note is not tied to a project', AiTools::actionProjectId(['type' => 'building_note_add', 'id' => $bid, 'params' => ['content' => 'x']]) === null);
list($ai, $r) = $propose([['type' => 'building_note_add', 'id' => $bid, 'params' => ['content' => "[ai-test] 鍵は管理人室 <script>alert(1)</script>\n2行目", 'is_important' => true]]]);
$res = is_array($r['pending']) ? $ai->confirm($r['pending']['token']) : ['status' => 'no-card'];
$note = ai_test_rows("SELECT * FROM {$p}parent_project_notes WHERE id > {$start['notes']} AND parent_project_id = {$bid}");
check('building note saved under the logged-in user', ($res['status'] ?? '') === 'success' && count($note) === 1 && $note[0]['user_id'] === ADMIN_USERID && (int) $note[0]['is_important'] === 1);
check('note is plain text, title is its first line', count($note) === 1 && strpos($note[0]['content'], '<') === false && strpos($note[0]['content'], '2行目') !== false && $note[0]['title'] === '[ai-test] 鍵は管理人室 alert(1)');
list(, $r) = $propose([['type' => 'building_note_add', 'id' => 2000000000, 'params' => ['content' => 'x']]]);
check('unknown building: no card', $r['pending'] === null);

ai_test_login(NORMAL_USERID);
$member = new AI();
check('a member of the project module may add a building note', $member->authorizeAction(['type' => 'building_note_add', 'id' => $bid, 'params' => ['content' => 'x']]) === true);
check('a member still cannot edit the building', $member->authorizeAction(['type' => 'parent_project_update', 'id' => $bid, 'params' => ['city' => 'x']]) === false);
$_SESSION['show_project'] = 0;
check('nothing without project access', (new AI())->authorizeAction(['type' => 'building_note_add', 'id' => $bid, 'params' => ['content' => 'x']]) === false);
$_SESSION['show_project'] = 1;
check('prompt explains a new project and a note for the building of the current page', strpos((new AI())->buildSystemBlocks([])[0]['text'], 'parent_project_id') !== false && strpos((new AI())->buildSystemBlocks([])[0]['text'], 'building_note_add') !== false);
$_SESSION['ai_chat'] = [];
finish();
