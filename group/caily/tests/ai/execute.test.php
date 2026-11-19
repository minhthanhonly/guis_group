<?php
/**
 * A confirmed action really runs, with the project's version (optimistic lock) taken at proposal time.
 * Changes one project's status in the local database and changes it back.
 */
require __DIR__ . '/bootstrap.php';

class ExecTestAI extends AI {
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
$usageTable = $p . 'ai_usage';
ai_test_login(ADMIN_USERID);
$db = new ApplicationModel();
$db->connect();
(new AI())->ensureUsageTable();
$usageStart = (int) ai_test_rows("SELECT COALESCE(MAX(id), 0) AS m FROM {$usageTable}")[0]['m'];
register_shutdown_function(function () use ($db, $usageTable, $usageStart) {
    $db->query("DELETE FROM {$usageTable} WHERE id > {$usageStart} AND userid = '" . $db->quote(ADMIN_USERID) . "'");
});

$project = ai_test_rows("SELECT id, status, version FROM {$p}projects WHERE status IN ('confirming', 'quotation', 'in_progress') ORDER BY updated_at DESC LIMIT 1")[0];
$pid = (int) $project['id'];
$original = $project['status'];
$other = $original === 'paused' ? 'in_progress' : 'paused';
$row = function () use ($pid, $p) {
    return ai_test_rows("SELECT status, version FROM {$p}projects WHERE id = {$pid}")[0];
};
$propose = function (array $actions) {
    $ai = new ExecTestAI();
    $r = $ai->runConversation('x', [], fake([uses(['propose_actions' => ['summary' => 's', 'actions' => $actions]])]));
    return [$ai, $r['pending']['token'] ?? null];
};

// Proposal records the version the user is looking at; a version from the model is ignored.
list($ai, $token) = $propose([['type' => 'update_project_status', 'id' => $pid, 'version' => 999999, 'params' => ['status' => $other]]]);
$stored = $_SESSION['ai_pending'][$token]['actions'][0];
check('version stamped from the database', isset($stored['version']) && $stored['version'] === (int) $project['version']);

// Confirm: the change is made.
$res = $ai->confirm($token);
check('confirmed action succeeds', isset($res['status']) && $res['status'] === 'success');
check('status changed in the database', $row()['status'] === $other);
check('version advanced', (int) $row()['version'] === (int) $project['version'] + 1);

// A second confirm of the same token does nothing.
$res = $ai->confirm($token);
check('token cannot be replayed', $res['error'] === 'expired' && $row()['status'] === $other);

// Someone else edits the project between proposal and confirmation: the confirm is refused.
list($ai, $token) = $propose([['type' => 'update_project_status', 'id' => $pid, 'params' => ['status' => $original]]]);
$db->query("UPDATE {$p}projects SET version = version + 1 WHERE id = {$pid}");
$res = $ai->confirm($token);
check('stale proposal is refused as a conflict', isset($res['status']) && $res['status'] !== 'success' && $row()['status'] === $other);

// Two changes to one project in one confirmation: the second follows the first.
list($ai, $token) = $propose([
    ['type' => 'update_project_status', 'id' => $pid, 'params' => ['status' => 'quotation']],
    ['type' => 'update_project_status', 'id' => $pid, 'params' => ['status' => $original]],
]);
$res = $ai->confirm($token);
check('two actions on one project both run', isset($res['status']) && $res['status'] === 'success' && (int) $res['success_count'] === 2);
check('project is back to its original status', $row()['status'] === $original);
finish();
