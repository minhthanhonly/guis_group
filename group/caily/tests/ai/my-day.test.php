<?php
/** get_my_day: the logged-in user's open tasks by deadline, their todo list, and the schedule of the day. Read only. */
require __DIR__ . '/bootstrap.php';

$p = DB_PREFIX;
ai_test_login(ADMIN_USERID);
$db = new ApplicationModel();
$db->connect();
$today = date('Y-m-d');
$startTodo = (int) ai_test_rows("SELECT COALESCE(MAX(id), 0) AS m FROM {$p}todo")[0]['m'];
register_shutdown_function(function () use ($db, $p, $startTodo) {
    $db->query("DELETE FROM {$p}todo WHERE id > {$startTodo} AND todo_title LIKE '[ai-test]%'");
});
$db->query("INSERT INTO {$p}todo (folder_id, todo_parent, todo_title, todo_term, todo_priority, todo_complete, owner, created) VALUES (0, 0, '[ai-test] mine', '{$today}', 50, 0, '" . ADMIN_USERID . "', NOW())");
$db->query("INSERT INTO {$p}todo (folder_id, todo_parent, todo_title, todo_term, todo_priority, todo_complete, owner, created) VALUES (0, 0, '[ai-test] done', '{$today}', 50, 1, '" . ADMIN_USERID . "', NOW())");
$db->query("INSERT INTO {$p}todo (folder_id, todo_parent, todo_title, todo_term, todo_priority, todo_complete, owner, created) VALUES (0, 0, '[ai-test] theirs', '{$today}', 50, 0, '" . NORMAL_USERID . "', NOW())");

$t = new AiTools(new AI());
check('get_my_day is a read tool', $t->isReadTool('get_my_day'));
$d = $t->runRead('get_my_day', []);
check('answers for today', ($d['date'] ?? null) === $today && isset($d['tasks'], $d['todos'], $d['events']));

// Tasks: compared with the task overview of the same user.
require_once DIR_MODEL . 'task.php';
$tm = new Task();
$tm->connect();
$mine = $tm->listOverview(['user_id' => (int) $_SESSION['id'], 'exclude_completed' => 1])['tasks'];
$overdue = count(array_filter($mine, function ($x) use ($today) { return !empty($x['due_date']) && substr($x['due_date'], 0, 10) < $today; }));
$dueToday = count(array_filter($mine, function ($x) use ($today) { return !empty($x['due_date']) && substr($x['due_date'], 0, 10) === $today; }));
check('open tasks are counted like the task overview', $d['tasks']['total_open'] === count($mine) && $d['tasks']['overdue_count'] === $overdue && $d['tasks']['due_today_count'] === $dueToday);
check('overdue tasks are listed, oldest deadline first', count($d['tasks']['overdue']) === min(10, $overdue) && ($overdue < 2 || $d['tasks']['overdue'][0]['due_date'] <= $d['tasks']['overdue'][1]['due_date']));
if ($overdue > 0) {
    check('task rows are compact', array_keys($d['tasks']['overdue'][0]) === ['id', 'title', 'project_id', 'project', 'due_date', 'status', 'progress']);
}

// Todos: only the user's own, only the open ones.
$titles = array_column($d['todos'], 'title');
check('my open todo is listed', in_array('[ai-test] mine', $titles, true));
check('completed todos and other people\'s todos are not', !in_array('[ai-test] done', $titles, true) && !in_array('[ai-test] theirs', $titles, true));
check('todo rows are compact', array_keys($d['todos'][0]) === ['id', 'title', 'due']);

// Schedule of the day.
check('events are compact and capped', count($d['events']) <= 20 && (count($d['events']) === 0 || array_keys($d['events'][0]) === ['title', 'start', 'end', 'all_day', 'calendar', 'mine']));
$other = $t->runRead('get_my_day', ['date' => '2026-11-17']);
check('another day can be asked for', $other['date'] === '2026-11-17' && count(array_filter($other['events'], function ($e) { return substr($e['start'], 0, 10) <= '2026-11-17' && substr($e['end'], 0, 10) >= '2026-11-17'; })) === count($other['events']));
check('bad date refused', isset($t->runRead('get_my_day', ['date' => 'today'])['error']));
check('days is 1 to 14', isset($t->runRead('get_my_day', ['days' => 30])['error']) && !isset($t->runRead('get_my_day', ['days' => 3])['error']));

ai_test_login(NORMAL_USERID);
$u = (new AiTools(new AI()))->runRead('get_my_day', []);
$theirs = array_column($u['todos'], 'title');
check('each user sees their own day', in_array('[ai-test] theirs', $theirs, true) && !in_array('[ai-test] mine', $theirs, true));
check('prompt points to get_my_day', strpos((new AI())->buildSystemBlocks([])[0]['text'], 'get_my_day') !== false);
finish();
