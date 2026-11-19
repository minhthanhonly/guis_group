<?php
/** Regression tests for the findings of the whole-branch review (2026-11-18). */
require __DIR__ . '/bootstrap.php';

$p = DB_PREFIX;
ai_test_login(ADMIN_USERID);
$t = new AiTools(new AI());

// 2. task_update: the project is taken from the task itself, never from the request.
$task = ai_test_rows("SELECT id, project_id FROM {$p}tasks WHERE project_id > 0 ORDER BY id DESC LIMIT 1");
check('have a task to test with', count($task) === 1);
$taskId = (int) $task[0]['id'];
$realProject = (int) $task[0]['project_id'];
$otherProject = $realProject + 1;
$v = $t->validateActions([['type' => 'task_update', 'id' => $taskId, 'params' => ['project_id' => $otherProject, 'title' => 'x']]]);
check('task_update drops a project_id from the model', $v['ok'] && !isset($v['actions'][0]['params']['project_id']));
$ai = new AI();
$n = $ai->normalizeAction($v['actions'][0]);
check('task_update is bound to the task\'s real project', is_array($n) && $n['params']['project_id'] === $realProject);
check('gate checks the real project', AiTools::actionProjectId($n) === $realProject);
check('task_update for an unknown task is refused', $ai->normalizeAction(['type' => 'task_update', 'id' => 2000000000, 'params' => ['title' => 'x']]) === null);
check('task_update without id is refused', $ai->normalizeAction(['type' => 'task_update', 'params' => ['title' => 'x']]) === null);
check('other actions pass through unchanged', $ai->normalizeAction(['type' => 'update_project_status', 'id' => 5, 'params' => ['status' => 'paused']]) === ['type' => 'update_project_status', 'id' => 5, 'params' => ['status' => 'paused']]);

// 3. parent_project_update: a building id is never authorised through an unrelated project.
check('building id with a project_id needs admin-level rights', AiTools::actionProjectId(['type' => 'parent_project_update', 'id' => 9, 'params' => ['project_id' => 4]]) === null);
check('building reached through its project is gated on that project', AiTools::actionProjectId(['type' => 'parent_project_update', 'params' => ['project_id' => 4]]) === 4);
check('building by number needs admin-level rights', AiTools::actionProjectId(['type' => 'parent_project_update', 'params' => ['project_number' => 'P000008']]) === null);

// 4. status values are whitelisted; "deleted" is not a status the assistant may set.
foreach ([
    ['type' => 'update_project_status', 'id' => 4, 'params' => ['status' => 'deleted']],
    ['type' => 'project_update', 'id' => 4, 'params' => ['status' => 'deleted']],
    ['type' => 'project_create', 'params' => ['name' => 'x', 'status' => 'deleted']],
    ['type' => 'parent_project_update', 'id' => 4, 'params' => ['status' => 'deleted']],
    ['type' => 'parent_project_create', 'params' => ['project_name' => 'x', 'status' => 'nonsense']],
    ['type' => 'task_update', 'id' => $taskId, 'params' => ['status' => 'deleted']],
] as $bad) {
    check('status refused: ' . $bad['type'] . ' ' . $bad['params']['status'], $t->validateActions([$bad])['ok'] === false);
}
check('valid project status accepted', $t->validateActions([['type' => 'update_project_status', 'id' => 4, 'params' => ['status' => 'quotation']]])['ok'] === true);
check('valid building status accepted', $t->validateActions([['type' => 'parent_project_update', 'id' => 4, 'params' => ['status' => 'under_contract']]])['ok'] === true);
check('valid task status accepted', $t->validateActions([['type' => 'task_update', 'id' => $taskId, 'params' => ['status' => 'completed']]])['ok'] === true);
check('task status uses the value the task screen stores', $t->validateActions([['type' => 'task_update', 'id' => $taskId, 'params' => ['status' => 'in-progress']]])['ok'] === true && $t->validateActions([['type' => 'task_update', 'id' => $taskId, 'params' => ['status' => 'in_progress']]])['ok'] === false);
check('all project statuses of the app accepted', $t->validateActions([['type' => 'update_project_status', 'id' => 4, 'params' => ['status' => 'application_in_progress']]])['ok'] === true);
$a = $t->validateActions([['type' => 'task_update', 'id' => $taskId, 'params' => ['assigned_to' => 'Tanaka', 'title' => 'x']]]);
check('assigned_to must be user ids', $a['ok'] && !isset($a['actions'][0]['params']['assigned_to']));
$a = $t->validateActions([['type' => 'task_update', 'id' => $taskId, 'params' => ['assigned_to' => '3,12']]]);
check('assigned_to id list kept', $a['actions'][0]['params']['assigned_to'] === '3,12');

// Confirmation card: long values are visibly cut.
$label = AiTools::describeAction(['type' => 'project_update', 'id' => 4, 'params' => ['description' => str_repeat('あ', 500)]])['label'];
check('long value ends with an ellipsis', mb_substr($label, -1) === '…' && mb_strlen($label) < 260);

// A model result without status=success is a failure (Project::create returns ['success' => false]).
check('success=false is a failure', AI::isActionSuccess(['success' => false, 'message' => 'x']) === false);
check('status=success is a success', AI::isActionSuccess(['status' => 'success']) === true);
check('success=true is a success', AI::isActionSuccess(['success' => true, 'project_id' => 3]) === true);
check('status=error is a failure', AI::isActionSuccess(['status' => 'error']) === false);
check('null is a failure', AI::isActionSuccess(null) === false);

// 6. The AI classes are not reachable through /api/index.php?model=...&method=...
foreach (['ai', 'AI', 'aitools', 'AiTools'] as $name) {
    $out = json_decode($controller->executeApi($name, 'buildSystemBlocks', []), true);
    check('api dispatch refuses model ' . $name, is_array($out) && isset($out['error']) && !isset($out[0]));
}
http_response_code(200);

// 7. The HTTP timeout is real.
$opts = (new ClaudeClient('k', 'm', 'low'))->requestOptions();
check('transport is an HTTP client we configured', isset($opts['transporter']) && $opts['transporter'] instanceof \GuzzleHttp\Client);
$cfg = (function () { return $this->config; })->call($opts['transporter']);
check('TLS verification stays on and has a CA bundle to verify against', is_string($cfg['verify']) && is_readable($cfg['verify']) && filesize($cfg['verify']) > 10000);
check('60 second timeout, 10 second connect timeout', (float) $cfg['timeout'] === 60.0 && (float) $cfg['connect_timeout'] === 10.0);

// 8. The usage row exists before the API call, so parallel requests are counted.
$usageTable = $p . 'ai_usage';
$ai = new AI();
$ai->ensureUsageTable();
$start = (int) ai_test_rows("SELECT COALESCE(MAX(id), 0) AS m FROM {$usageTable}")[0]['m'];
$db = new ApplicationModel();
$db->connect();
register_shutdown_function(function () use ($db, $usageTable, $start) {
    $db->query("DELETE FROM {$usageTable} WHERE id > {$start} AND userid IN ('" . $db->quote(ADMIN_USERID) . "', '" . $db->quote(NORMAL_USERID) . "')");
});
$rowsDuringSend = null;
$f = fake([text('ok')]);
$f->onSend = function () use (&$rowsDuringSend, $usageTable, $start) {
    $rowsDuringSend = count(ai_test_rows("SELECT id FROM {$usageTable} WHERE id > {$start}"));
};
$ai->runConversation('hi', [], $f);
$rows = ai_test_rows("SELECT * FROM {$usageTable} WHERE id > {$start}");
check('usage row reserved before the API call', $rowsDuringSend === 1);
check('same row updated with the token counts', count($rows) === 1 && (int) $rows[0]['input_tokens'] === 10 && (int) $rows[0]['output_tokens'] === 5);

// An unexpected failure inside the loop still yields a normal error response.
$ai = new AI();
$historyBefore = count($ai->chatHistory);
$r = $ai->runConversation('hi', [], fake([]));
check('unexpected exception becomes an error reply', $r['error'] === 'unavailable' && $r['reply'] !== '' && $r['pending'] === null && count($ai->chatHistory) === $historyBefore);

// 10. Customer contact details are returned (Customer model is loaded before it is used).
$withContact = ai_test_rows("SELECT pp.project_number FROM {$p}parent_projects pp JOIN {$p}customer c ON c.id = pp.customer_id WHERE pp.status != 'deleted' AND pp.project_number <> '' AND c.tel <> '' ORDER BY pp.id DESC LIMIT 1");
if (count($withContact) === 1) {
    $c = $t->runRead('get_customer_info', ['project_number' => $withContact[0]['project_number']]);
    check('customer phone number returned', is_array($c['customer']) && !empty($c['customer']['電話番号']));
} else {
    echo "SKIP  no building with a customer phone number in the local DB\n";
}

// 9. Users without access to the project module get no tools, no writes and no bar.
$_SESSION['show_project'] = 0;
$t0 = new AiTools(new AI());
check('no read tools without project access', isset($t0->runRead('search_projects', [])['error']) && isset($t0->runRead('search_buildings', [])['error']));
check('no writes without project access', (new AI())->canProposeWrite(null) === false);
ob_start();
require DIR_VIEW . 'ai-chat-bar.php';
check('bar not rendered without project access', trim(ob_get_clean()) === '');
$_SESSION['show_project'] = 1;
ob_start();
require DIR_VIEW . 'ai-chat-bar.php';
check('bar rendered with project access', strpos(ob_get_clean(), 'id="ai-chat-bar"') !== false);
finish();
