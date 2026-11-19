<?php
require __DIR__ . '/bootstrap.php';

/** AI with a settable request body, so executeAction() can run without php://input. */
class GateTestAI extends AI {
    public $body = [];
    protected function readJsonBody(): array {
        return $this->body;
    }
    public function runExecute(array $body) {
        $this->body = $body;
        ob_start();
        $this->executeAction();
        return json_decode(ob_get_clean(), true);
    }
}

ai_test_login(ADMIN_USERID);
$t = new AiTools(new AI());
check('unknown type rejected', $t->validateActions([['type' => 'project_delete', 'id' => 1]])['ok'] === false);
check('script type no longer accepted', $t->validateActions([['type' => 'script', 'params' => ['url' => 'https://evil.example']]])['ok'] === false);
$v = $t->validateActions([['type' => 'update_project_status', 'id' => '4', 'params' => ['status' => 'contract', 'created_by' => 1, '_ai_triggered' => 0]]]);
check('unknown params stripped', $v['ok'] && $v['actions'][0]['params'] === ['status' => 'contract']);
check('id cast to int', $v['actions'][0]['id'] === 4);
check('not danger by default', $v['danger'] === false);
check('danger flagged', $t->validateActions([['type' => 'project_clear_all', 'params' => ['project_id' => 4]]])['danger'] === true);
check('at most 5 actions', $t->validateActions(array_fill(0, 6, ['type' => 'project_clear_teams', 'params' => ['project_id' => 4]]))['ok'] === false);
check('empty list rejected', $t->validateActions([])['ok'] === false);
check('non-scalar param dropped', $t->validateActions([['type' => 'project_update', 'id' => 4, 'params' => ['name' => ['a' => 1]]]])['actions'][0]['params'] === []);
check('project id of update', AiTools::actionProjectId(['type' => 'project_update', 'id' => 7, 'params' => []]) === 7);
check('project id of member action', AiTools::actionProjectId(['type' => 'project_add_member', 'params' => ['project_id' => 9]]) === 9);
check('project id of task update', AiTools::actionProjectId(['type' => 'task_update', 'id' => 3, 'params' => ['project_id' => 9]]) === 9);
check('no project id for create', AiTools::actionProjectId(['type' => 'project_create', 'params' => ['name' => 'x']]) === null);

// A project the plain member may not edit, and its current status.
$projects = $t->runRead('search_projects', ['limit' => 20])['projects'];
ai_test_login(NORMAL_USERID);
require_once DIR_MODEL . 'project.php';
$pm = new Project();
$pm->connect();
$foreign = null;
foreach ($projects as $p) {
    if (!$pm->canUserEditProject((int) $p['id'])) {
        $foreign = (int) $p['id'];
        break;
    }
}
check('found a project the member cannot edit', $foreign !== null);
$statusOf = function ($id) {
    $r = ai_test_rows("SELECT status FROM " . DB_PREFIX . "projects WHERE id = " . (int) $id);
    return $r[0]['status'];
};
$before = $statusOf($foreign);
$newStatus = $before === 'paused' ? 'in_progress' : 'paused';

$ai = new GateTestAI();
check('member cannot propose without a project', $ai->canProposeWrite(null) === false);
check('member cannot propose on a foreign project', $ai->canProposeWrite($foreign) === false);

ai_test_login(ADMIN_USERID);
$ai = new GateTestAI();
check('admin can propose', $ai->canProposeWrite(null) === true && $ai->canProposeWrite($foreign) === true);
$tok = $ai->storePending([['type' => 'project_clear_teams', 'params' => ['project_id' => $foreign]]], 's', true);
check('token is 32 hex chars', preg_match('/^[0-9a-f]{32}$/', $tok) === 1);
$taken = $ai->takePending($tok);
check('take returns stored actions', is_array($taken) && $taken['actions'][0]['type'] === 'project_clear_teams' && $taken['danger'] === true);
check('token works once', $ai->takePending($tok) === null);
$tok2 = $ai->storePending([['type' => 'project_clear_teams', 'params' => ['project_id' => $foreign]]], 's', false);
$_SESSION['ai_pending'][$tok2]['expires'] = time() - 1;
check('expired token refused', $ai->takePending($tok2) === null);
check('forged token refused', $ai->takePending(str_repeat('0', 32)) === null);
for ($i = 0; $i < 8; $i++) {
    $ai->storePending([['type' => 'project_clear_teams', 'params' => ['project_id' => $foreign]]], 's', false);
}
check('keeps at most 5 pending', count($_SESSION['ai_pending']) === 5);
$_SESSION['ai_pending'] = [];

// execute_action: the browser can no longer send actions itself.
$forged = ['action' => ['type' => 'update_project_status', 'id' => $foreign, 'params' => ['status' => $newStatus]]];
$r = $ai->runExecute($forged);
check('forged action body refused', $r['status'] === 'error' && $r['error'] === 'expired');
check('forged action changed nothing', $statusOf($foreign) === $before);
$r = $ai->runExecute(['token' => 'nope']);
check('bad token refused', $r['status'] === 'error' && $r['error'] === 'expired');

// A token created while authorised must not run for a user who is not.
$tok3 = $ai->storePending([['type' => 'update_project_status', 'id' => $foreign, 'params' => ['status' => $newStatus]]], 's', false);
$pending = $_SESSION['ai_pending'];
ai_test_login(NORMAL_USERID);
$_SESSION['ai_pending'] = $pending;
$ai = new GateTestAI();
$r = $ai->runExecute(['token' => $tok3]);
check('unauthorised user is refused at execution', isset($r['status']) && $r['status'] !== 'success');
check('refused execution changed nothing', $statusOf($foreign) === $before);
check('token is spent even when refused', $ai->runExecute(['token' => $tok3])['error'] === 'expired');
finish();
