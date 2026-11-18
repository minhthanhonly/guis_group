<?php
/** get_requests: my own applications and their status; applications waiting for my approval. Read only. */
require __DIR__ . '/bootstrap.php';

$p = DB_PREFIX;
ai_test_login(NORMAL_USERID);
$me = NORMAL_USERID;
$t = new AiTools(new AI());
check('get_requests is a read tool', $t->isReadTool('get_requests'));

$mineTotal = (int) ai_test_rows("SELECT COUNT(*) AS c FROM {$p}requests WHERE user_id = '{$me}'")[0]['c'];
$mineApproved = (int) ai_test_rows("SELECT COUNT(*) AS c FROM {$p}requests WHERE user_id = '{$me}' AND status = 'approved'")[0]['c'];
$mineLeave = (int) ai_test_rows("SELECT COUNT(*) AS c FROM {$p}requests WHERE user_id = '{$me}' AND type = 'leave'")[0]['c'];

$getBefore = $_GET;
$r = $t->runRead('get_requests', []);
check('default: my own applications, counted like the database', ($r['scope'] ?? null) === 'mine' && $r['total'] === $mineTotal);
check('request globals are left as they were', $_GET === $getBefore);
check('rows are compact and capped at 10', count($r['requests']) === min(10, $mineTotal) && ($mineTotal === 0 || array_keys($r['requests'][0]) === ['id', 'type', 'status', 'applicant', 'created', 'start_date', 'end_date']));
check('no form contents are returned', strpos(json_encode($r), '"data"') === false && strpos(json_encode($r), 'history') === false);
check('more flag', $r['more'] === ($mineTotal > 10));
check('newest first', $mineTotal < 2 || $r['requests'][0]['created'] >= $r['requests'][1]['created']);

$r = $t->runRead('get_requests', ['status' => 'approved', 'limit' => 20]);
check('filter by status', $r['total'] === $mineApproved && count(array_filter($r['requests'], function ($x) { return $x['status'] !== 'approved'; })) === 0);
$r = $t->runRead('get_requests', ['type' => 'leave']);
check('filter by type', $r['total'] === $mineLeave);
check('unknown status, type and scope refused', isset($t->runRead('get_requests', ['status' => 'lost'])['error']) && isset($t->runRead('get_requests', ['type' => 'salary'])['error']) && isset($t->runRead('get_requests', ['scope' => 'everyone'])['error']));

// "mine" never leaks other people's applications, whatever the role.
ai_test_login(ADMIN_USERID);
$a = new AiTools(new AI());
$adminMine = (int) ai_test_rows("SELECT COUNT(*) AS c FROM {$p}requests WHERE user_id = '" . ADMIN_USERID . "'")[0]['c'];
$r = $a->runRead('get_requests', ['scope' => 'mine']);
check('an administrator asking for "mine" gets only their own', $r['total'] === $adminMine);

// Waiting for my approval.
$r = $a->runRead('get_requests', ['scope' => 'to_approve']);
check('to_approve: only applications still open', $r['scope'] === 'to_approve' && is_int($r['total']) && count(array_filter($r['requests'], function ($x) { return !in_array($x['status'], ['pending', 'approved'], true); })) === 0);
ai_test_login(NORMAL_USERID);
$r = (new AiTools(new AI()))->runRead('get_requests', ['scope' => 'to_approve']);
$allOpen = (int) ai_test_rows("SELECT COUNT(*) AS c FROM {$p}requests WHERE status IN ('pending', 'approved')")[0]['c'];
check('a member who approves nothing does not see the whole queue', $r['total'] <= $allOpen && count(array_filter($r['requests'], function ($x) { return $x['status'] !== 'pending'; })) === 0);
check('prompt points to get_requests', strpos((new AI())->buildSystemBlocks([])[0]['text'], 'get_requests') !== false);
finish();
