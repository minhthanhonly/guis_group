<?php
/**
 * Group A write actions: project notes, personal todos, the user's own schedule entries, task progress
 * and acknowledgement. Each one is proposed, confirmed with a token, and really written; every row
 * this test creates is removed again.
 */
require __DIR__ . '/bootstrap.php';

class ActionTestAI extends AI {
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
$usageStart = (int) ai_test_rows("SELECT COALESCE(MAX(id), 0) AS m FROM {$p}ai_usage")[0]['m'];
$max = function ($table) use ($p) {
    return (int) ai_test_rows("SELECT COALESCE(MAX(id), 0) AS m FROM {$p}{$table}")[0]['m'];
};
$start = ['todo' => $max('todo'), 'schedule' => $max('schedule'), 'project_notes' => $max('project_notes')];
register_shutdown_function(function () use ($db, $p, $start, $usageStart) {
    $marker = "'%[ai-test]%'";
    $db->query("DELETE FROM {$p}todo WHERE id > {$start['todo']} AND todo_title LIKE {$marker}");
    $db->query("DELETE FROM {$p}schedule WHERE id > {$start['schedule']} AND schedule_title LIKE {$marker}");
    $db->query("DELETE FROM {$p}project_note_user_board WHERE note_id IN (SELECT id FROM {$p}project_notes WHERE id > {$start['project_notes']} AND content LIKE {$marker})");
    $db->query("DELETE FROM {$p}project_note_user_pins WHERE note_id IN (SELECT id FROM {$p}project_notes WHERE id > {$start['project_notes']} AND content LIKE {$marker})");
    $db->query("DELETE FROM {$p}project_notes WHERE id > {$start['project_notes']} AND content LIKE {$marker}");
    $db->query("DELETE FROM {$p}ai_usage WHERE id > {$usageStart} AND userid IN ('" . $db->quote(ADMIN_USERID) . "', '" . $db->quote(NORMAL_USERID) . "')");
});

$t = new AiTools(new AI());
$valid = function (array $action) use ($t) {
    return $t->validateActions([$action])['ok'];
};

// ---- Whitelist and value checks ----
check('note needs text', $valid(['type' => 'project_note_add', 'params' => ['project_id' => 4, 'content' => '  ']]) === false && $valid(['type' => 'project_note_add', 'params' => ['project_id' => 4, 'content' => 'x']]) === true);
check('note visibility is one of all/members/self', $valid(['type' => 'project_note_add', 'params' => ['project_id' => 4, 'content' => 'x', 'visibility' => 'everyone']]) === false && $valid(['type' => 'project_note_add', 'params' => ['project_id' => 4, 'content' => 'x', 'visibility' => 'self']]) === true);
check('todo needs a title', $valid(['type' => 'todo_add', 'params' => ['todo_comment' => 'x']]) === false && $valid(['type' => 'todo_add', 'params' => ['todo_title' => 'x']]) === true);
check('todo date must be a date', $valid(['type' => 'todo_add', 'params' => ['todo_title' => 'x', 'todo_term' => 'tomorrow']]) === false && $valid(['type' => 'todo_add', 'params' => ['todo_title' => 'x', 'todo_term' => '2026-11-20']]) === true);
$owned = $t->validateActions([['type' => 'todo_add', 'params' => ['todo_title' => 'x', 'owner' => 'someone', 'todo_complete' => 1]]]);
check('todo cannot be created for someone else', $owned['ok'] && array_keys($owned['actions'][0]['params']) === ['todo_title']);
check('event needs title and start date', $valid(['type' => 'schedule_add', 'params' => ['title' => 'x']]) === false && $valid(['type' => 'schedule_add', 'params' => ['title' => 'x', 'start_date' => '2026-11-20']]) === true);
check('event times are HH:MM', $valid(['type' => 'schedule_add', 'params' => ['title' => 'x', 'start_date' => '2026-11-20', 'start_time' => '9am']]) === false && $valid(['type' => 'schedule_add', 'params' => ['title' => 'x', 'start_date' => '2026-11-20', 'start_time' => '09:30', 'end_time' => '10:00']]) === true);
check('progress is 0 to 100', $valid(['type' => 'task_progress', 'id' => 5, 'params' => ['progress' => 150]]) === false && $valid(['type' => 'task_progress', 'id' => 5, 'params' => ['progress' => 40]]) === true);
check('record id required where the action targets a record', $valid(['type' => 'task_progress', 'params' => ['progress' => 40]]) === false && $valid(['type' => 'todo_complete', 'params' => []]) === false && $valid(['type' => 'project_note_toggle_pin', 'params' => []]) === false);

// ---- Who may do what ----
$projects = $t->runRead('search_projects', ['limit' => 20])['projects'];
ai_test_login(NORMAL_USERID);
$member = new AI();
$hidden = null;
foreach ($projects as $row) {
    if (empty((new AiTools($member))->runRead('search_projects', ['project_ids' => [(int) $row['id']]])['projects'])) {
        $hidden = (int) $row['id'];
        break;
    }
}
check('a member may add their own todo and event', $member->authorizeAction(['type' => 'todo_add', 'params' => ['todo_title' => 'x']]) === true && $member->authorizeAction(['type' => 'schedule_add', 'params' => ['title' => 'x', 'start_date' => '2026-11-20']]) === true);
check('a member still cannot change a project they do not manage', $member->authorizeAction(['type' => 'update_project_status', 'id' => (int) $projects[0]['id'], 'params' => ['status' => 'paused']]) === false);
if ($hidden !== null) {
    check('no note on a project the member cannot see', $member->authorizeAction(['type' => 'project_note_add', 'params' => ['project_id' => $hidden, 'content' => 'x']]) === false);
}
$r = $member->runConversation('nhắc tôi', [], fake([uses(['propose_actions' => ['summary' => 's', 'actions' => [['type' => 'todo_add', 'params' => ['todo_title' => '[ai-test] member todo']]]]])]));
check('a member gets a confirmation card for their own todo', is_array($r['pending']) && $r['pending']['actions'][0]['label'] === 'ToDoを追加: todo_title=[ai-test] member todo');
$_SESSION['ai_pending'] = [];
$_SESSION['ai_chat'] = [];

// ---- Really executed, as the administrator ----
ai_test_login(ADMIN_USERID);
$run = function (array $actions) {
    $ai = new ActionTestAI();
    $r = $ai->runConversation('x', [], fake([uses(['propose_actions' => ['summary' => 's', 'actions' => $actions]])]));
    if (empty($r['pending']['token'])) {
        return ['status' => 'no-card', 'reply' => $r['reply']];
    }
    return $ai->confirm($r['pending']['token']);
};

$res = $run([['type' => 'todo_add', 'params' => ['todo_title' => '[ai-test] 見積を送る', 'todo_term' => '2026-11-20']]]);
$todo = ai_test_rows("SELECT * FROM {$p}todo WHERE id > {$start['todo']} AND todo_title = '[ai-test] 見積を送る'");
check('todo created for the logged-in user', ($res['status'] ?? '') === 'success' && count($todo) === 1 && $todo[0]['owner'] === ADMIN_USERID && (int) $todo[0]['todo_complete'] === 0);
if (count($todo) === 1) {
    $res = $run([['type' => 'todo_complete', 'id' => (int) $todo[0]['id']]]);
    check('todo marked complete', ($res['status'] ?? '') === 'success' && (int) ai_test_rows("SELECT todo_complete FROM {$p}todo WHERE id = " . (int) $todo[0]['id'])[0]['todo_complete'] === 1);
}
$foreignTodo = ai_test_rows("SELECT id, todo_complete FROM {$p}todo WHERE owner <> '" . ADMIN_USERID . "' AND todo_complete = 0 ORDER BY id DESC LIMIT 1");
if (count($foreignTodo) === 1) {
    $res = $run([['type' => 'todo_complete', 'id' => (int) $foreignTodo[0]['id']]]);
    check("someone else's todo is not touched", ($res['status'] ?? '') !== 'success' && (int) ai_test_rows("SELECT todo_complete FROM {$p}todo WHERE id = " . (int) $foreignTodo[0]['id'])[0]['todo_complete'] === 0);
}

$res = $run([['type' => 'schedule_add', 'params' => ['title' => '[ai-test] 打ち合わせ', 'start_date' => '2026-11-20', 'start_time' => '10:00', 'end_time' => '11:00', 'private' => true]]]);
$event = ai_test_rows("SELECT * FROM {$p}schedule WHERE id > {$start['schedule']} AND schedule_title = '[ai-test] 打ち合わせ'");
check('event created for the logged-in user, private', ($res['status'] ?? '') === 'success' && count($event) === 1 && $event[0]['owner'] === ADMIN_USERID && (int) $event[0]['public_level'] === 1 && $event[0]['schedule_date'] === '2026-11-20');

$pid = (int) $projects[0]['id'];
$res = $run([['type' => 'project_note_add', 'params' => ['project_id' => $pid, 'content' => '[ai-test] 図面を確認 <script>alert(1)</script>', 'label' => 'メモ']]]);
$note = ai_test_rows("SELECT * FROM {$p}project_notes WHERE id > {$start['project_notes']} AND content LIKE '%[ai-test] 図面を確認%'");
check('note created by the logged-in user', ($res['status'] ?? '') === 'success' && count($note) === 1 && (int) $note[0]['project_id'] === $pid && $note[0]['user_id'] === ADMIN_USERID);
check('note content is cleaned by the model', count($note) === 1 && stripos($note[0]['content'], '<script') === false);
if (count($note) === 1) {
    $before = (int) $note[0]['is_important'];
    $res = $run([['type' => 'project_note_toggle_pin', 'id' => (int) $note[0]['id']]]);
    check('note pin toggled', ($res['status'] ?? '') === 'success' && (int) ai_test_rows("SELECT is_important FROM {$p}project_notes WHERE id = " . (int) $note[0]['id'])[0]['is_important'] === 1 - $before);
}
$res = $run([['type' => 'project_note_toggle_pin', 'id' => 2000000000]]);
check('unknown note is refused', ($res['status'] ?? '') !== 'success');

$task = ai_test_rows("SELECT t.id, t.project_id, t.progress FROM {$p}tasks t JOIN {$p}projects pr ON pr.id = t.project_id ORDER BY t.id DESC LIMIT 1")[0];
$new = (int) $task['progress'] === 40 ? 50 : 40;
$res = $run([['type' => 'task_progress', 'id' => (int) $task['id'], 'params' => ['progress' => $new, 'project_id' => 1]]]);
check('task progress updated on the task itself', ($res['status'] ?? '') === 'success' && (int) ai_test_rows("SELECT progress FROM {$p}tasks WHERE id = " . (int) $task['id'])[0]['progress'] === $new);
$run([['type' => 'task_progress', 'id' => (int) $task['id'], 'params' => ['progress' => (int) $task['progress']]]]);
check('task progress restored', (int) ai_test_rows("SELECT progress FROM {$p}tasks WHERE id = " . (int) $task['id'])[0]['progress'] === (int) $task['progress']);

$unassigned = ai_test_rows("SELECT t.id FROM {$p}tasks t JOIN {$p}projects pr ON pr.id = t.project_id WHERE (t.assigned_to IS NULL OR t.assigned_to = '') ORDER BY t.id DESC LIMIT 1");
if (count($unassigned) === 1) {
    $res = $run([['type' => 'task_acknowledge', 'id' => (int) $unassigned[0]['id']]]);
    check('acknowledging a task not assigned to the user fails cleanly', is_array($res) && ($res['status'] ?? '') !== 'success');
}
$_SESSION['ai_chat'] = [];
finish();
