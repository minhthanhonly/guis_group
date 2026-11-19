<?php
/**
 * Everyday questions of staff and managers: my attendance, who is away, hours worked, workload of a
 * team, and an assignee changing the status of their own task. Rows changed here are put back.
 */
require __DIR__ . '/bootstrap.php';

class StaffGapsAI extends AI {
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
$db = new ApplicationModel();
$db->connect();
ai_test_login(ADMIN_USERID);
(new AI())->ensureUsageTable();
$usageStart = (int) ai_test_rows("SELECT COALESCE(MAX(id), 0) AS m FROM {$p}ai_usage")[0]['m'];
$logStart = (int) ai_test_rows("SELECT COALESCE(MAX(id), 0) AS m FROM {$p}task_logs")[0]['m'];
register_shutdown_function(function () use ($db, $p, $usageStart, $logStart) {
    $db->query("DELETE FROM {$p}ai_usage WHERE id > {$usageStart}");
    $db->query("DELETE FROM {$p}task_logs WHERE id > {$logStart} AND action = 'status_changed'");
});
$active = "u.show_project = 1 AND (u.is_suspend = 0 OR u.is_suspend IS NULL)";

$t = new AiTools(new AI());
foreach (['get_my_attendance', 'get_absences', 'get_work_hours', 'get_team_workload'] as $name) {
    check($name . ' is a read tool', $t->isReadTool($name));
}

// ---- 4. My attendance ----
$owner = ai_test_rows("SELECT t.owner, COUNT(*) AS c FROM {$p}timecard t JOIN {$p}user u ON u.userid = t.owner AND {$active} WHERE STR_TO_DATE(t.timecard_date, '%Y-%m-%d') BETWEEN '2026-10-21' AND '2026-11-20' GROUP BY t.owner ORDER BY c DESC LIMIT 1")[0];
ai_test_login($owner['owner']);
$getBefore = $_GET;
$a = (new AiTools(new AI()))->runRead('get_my_attendance', ['month' => '2026-11']);
check('attendance: the pay period of the month', ($a['period'] ?? null) === ['from' => '2026-10-21', 'to' => '2026-11-20']);
check('attendance: days recorded as in the database', ($a['days_recorded'] ?? null) === (int) $owner['c']);
check('attendance: totals as hours:minutes', preg_match('/^\d+:\d{2}$/', (string) ($a['work_time'] ?? '')) === 1 && preg_match('/^\d+:\d{2}$/', (string) ($a['overtime'] ?? '')) === 1 && preg_match('/^\d+:\d{2}$/', (string) ($a['holiday_work'] ?? '')) === 1);
check('attendance: today and leave are reported', array_key_exists('today', $a) && is_int($a['leave_requests_this_year'] ?? null) && isset($a['note']));
check('attendance: no day-by-day dump', strlen(json_encode($a)) < 1500);
check('attendance: request globals restored', $_GET === $getBefore);
$now = (new AiTools(new AI()))->runRead('get_my_attendance', []);
check('attendance: default is the period that contains today', isset($now['period']) && $now['period']['from'] <= date('Y-m-d') && $now['period']['to'] >= date('Y-m-d'));
check('attendance: bad month refused', isset((new AiTools(new AI()))->runRead('get_my_attendance', ['month' => '11/2026'])['error']));
ai_test_login(NORMAL_USERID);
$mineCount = (int) ai_test_rows("SELECT COUNT(*) AS c FROM {$p}timecard WHERE owner = '" . NORMAL_USERID . "' AND STR_TO_DATE(timecard_date, '%Y-%m-%d') BETWEEN '2026-10-21' AND '2026-11-20'")[0]['c'];
$b = (new AiTools(new AI()))->runRead('get_my_attendance', ['month' => '2026-11']);
check('attendance: always the logged-in user, never somebody else', $b['days_recorded'] === $mineCount);

// ---- 5. Who is away ----
$busy = ai_test_rows("SELECT start_date, COUNT(*) AS c FROM {$p}requests WHERE type IN ('leave', 'outing', 'trip', 'holiday_work', 'overtime') AND status IN ('approved', 'completed') AND start_date IS NOT NULL GROUP BY start_date ORDER BY c DESC LIMIT 1")[0];
$day = substr((string) $busy['start_date'], 0, 10);
require_once DIR_MODEL . 'request.php';
$rq = new Request();
$rq->connect();
$bundle = $rq->get_forms_for_date_bulk($day);
$expected = 0;
foreach ($bundle['by_user'] as $byType) {
    foreach (['leave', 'outing', 'trip'] as $type) {
        $expected += count($byType[$type]);
    }
}
$r = (new AiTools(new AI()))->runRead('get_absences', ['date' => $day]);
check('absences: counted like the members page for that day', ($r['date'] ?? null) === $day && ($r['total'] ?? null) === $expected && $expected > 0);
check('absences: compact rows with names', count($r['people']) === min(20, $expected) && array_keys($r['people'][0]) === ['name', 'type', 'start_date', 'end_date', 'time'] && $r['people'][0]['name'] !== '');
check('absences: only leave, outing and trip', count(array_filter($r['people'], function ($x) { return !in_array($x['type'], ['leave', 'outing', 'trip'], true); })) === 0);
check('absences: the reason on the form is never returned', strpos(json_encode($r), 'reason') === false && strpos(json_encode($r), '"data"') === false);
$r = (new AiTools(new AI()))->runRead('get_absences', []);
check('absences: default is today', $r['date'] === date('Y-m-d') && is_int($r['total']));
check('absences: bad date refused', isset((new AiTools(new AI()))->runRead('get_absences', ['date' => 'tomorrow'])['error']));

// ---- 6. Hours worked ----
$entry = ai_test_rows("SELECT te.user_id, DATE(te.end_time) AS d, u.id, u.realname FROM {$p}time_entries te JOIN {$p}user u ON u.userid = te.user_id AND {$active} AND u.authority <> 'administrator' WHERE te.end_time IS NOT NULL ORDER BY te.end_time DESC LIMIT 1")[0];
ai_test_login($entry['user_id']);
$_SESSION['isProjectManager'] = false;
require_once DIR_MODEL . 'task.php';
$tm = new Task();
$tm->connect();
$saved = $_GET;
$_GET = ['week_start' => $entry['d']];
$week = $tm->listWeeklyTasks();
$_GET = $saved;
$sum = 0.0;
foreach ($week['tasks'] as $row) {
    $sum += (float) $row['week_hours'];
}
$h = (new AiTools(new AI()))->runRead('get_work_hours', ['week_start' => $entry['d']]);
check('hours: the week of the given day, Monday to Sunday', ($h['week'] ?? null) === ['from' => $week['week_start'], 'to' => $week['week_end']] && date('N', strtotime($h['week']['from'])) === '1');
check('hours: total as the weekly page adds it up', abs(($h['total_hours'] ?? -1) - round($sum, 2)) < 0.01 && $sum > 0);
check('hours: tasks with time, most hours first, compact', count($h['tasks']) > 0 && array_keys($h['tasks'][0]) === ['id', 'title', 'project', 'status', 'week_hours'] && $h['tasks'][0]['week_hours'] >= $h['tasks'][count($h['tasks']) - 1]['week_hours'] && count($h['tasks']) <= 10);
check('hours: for the logged-in user', $h['person'] === $entry['realname']);
check('hours: a member cannot ask for somebody else', isset((new AiTools(new AI()))->runRead('get_work_hours', ['person_name' => 'admin'])['error']));
ai_test_login(ADMIN_USERID);
$_SESSION['isProjectManager'] = true;
$h2 = (new AiTools(new AI()))->runRead('get_work_hours', ['week_start' => $entry['d'], 'person_name' => $entry['realname']]);
check('hours: a manager can ask for one person', ($h2['person'] ?? null) === $entry['realname'] && abs($h2['total_hours'] - round($sum, 2)) < 0.01);
check('hours: unknown person refused', isset((new AiTools(new AI()))->runRead('get_work_hours', ['person_name' => 'zz-nobody-zz'])['error']));

// ---- 6. Workload of a team ----
$dept = (int) ai_test_rows("SELECT department_id FROM {$p}projects WHERE status = 'in_progress' AND department_id > 0 GROUP BY department_id ORDER BY COUNT(*) DESC LIMIT 1")[0]['department_id'];
$overview = $tm->listOverview(['department_id' => $dept, 'exclude_completed' => 1]);
$perUser = [];
foreach ($overview['tasks'] as $row) {
    foreach ($row['assigned_to_ids'] as $uid) {
        $perUser[$uid] = isset($perUser[$uid]) ? $perUser[$uid] + 1 : 1;
    }
}
arsort($perUser);
$w = (new AiTools(new AI()))->runRead('get_team_workload', ['department_id' => $dept]);
check('workload: one row per person holding open tasks', ($w['people_with_tasks'] ?? null) === count($perUser) && count($w['people']) === min(10, count($perUser)));
check('workload: busiest first, counted like the task overview', count($perUser) === 0 || ($w['people'][0]['open_tasks'] === reset($perUser) && array_keys($w['people'][0]) === ['name', 'open_tasks', 'overdue', 'hours_on_open_tasks', 'hours_this_week']));
check('workload: people without tasks counted', $w['people_without_tasks'] === count($overview['unassigned_users']));
ai_test_login(NORMAL_USERID);
check('workload: only managers and administrators', isset((new AiTools(new AI()))->runRead('get_team_workload', [])['error']));

// ---- 8. An assignee changes the status of their own task ----
$valid = function (array $action) {
    return (new AiTools(new AI()))->validateActions([$action])['ok'];
};
check('task_status: needs a task and a known status', $valid(['type' => 'task_status', 'id' => 5, 'params' => ['status' => 'in-progress']]) === true
    && $valid(['type' => 'task_status', 'params' => ['status' => 'in-progress']]) === false
    && $valid(['type' => 'task_status', 'id' => 5, 'params' => ['status' => 'deleted']]) === false
    && $valid(['type' => 'task_status', 'id' => 5, 'params' => []]) === false);
$run = function (array $actions) {
    $ai = new StaffGapsAI();
    $r = $ai->runConversation('x', [], fake([uses(['propose_actions' => ['summary' => 's', 'actions' => $actions]])]));
    if (empty($r['pending']['token'])) {
        return ['status' => 'no-card', 'reply' => $r['reply']];
    }
    return $ai->confirm($r['pending']['token']);
};
// A plain member (not administrator, not project manager) who holds an open task.
$mine = ai_test_rows("SELECT t.id, t.status, t.progress, t.updated_at, u.userid FROM {$p}tasks t JOIN {$p}projects pr ON pr.id = t.project_id JOIN {$p}user u ON u.id = CAST(t.assigned_to AS UNSIGNED) AND {$active} AND u.authority <> 'administrator' WHERE t.status IN ('todo', 'in-progress') AND t.assigned_to REGEXP '^[0-9]+$' AND NOT EXISTS (SELECT 1 FROM {$p}user_department ud WHERE ud.userid = u.userid AND ud.project_manager = 1) AND NOT EXISTS (SELECT 1 FROM {$p}project_members pm WHERE pm.project_id = t.project_id AND pm.user_id = u.id AND pm.role = 'manager') ORDER BY t.id DESC LIMIT 1");
check('local data has a task held by a plain member', count($mine) === 1);
if (count($mine) === 1) {
    $task = $mine[0];
    $restore = function () use ($db, $p, $task) {
        $db->query("UPDATE {$p}tasks SET status = '" . $db->quote($task['status']) . "', progress = " . (int) $task['progress'] . ", updated_at = " . ($task['updated_at'] === null ? 'NULL' : "'" . $db->quote($task['updated_at']) . "'") . " WHERE id = " . (int) $task['id']);
    };
    register_shutdown_function($restore);
    ai_test_login($task['userid']);
    $new = $task['status'] === 'todo' ? 'in-progress' : 'paused';
    $res = $run([['type' => 'task_status', 'id' => (int) $task['id'], 'params' => ['status' => $new, 'project_id' => 1]]]);
    check('task_status: the assignee changes their own task', ($res['status'] ?? '') === 'success' && ai_test_rows("SELECT status FROM {$p}tasks WHERE id = " . (int) $task['id'])[0]['status'] === $new);
    $restore();

    // Somebody who is neither assignee, creator nor manager of that task.
    $other = ai_test_rows("SELECT u.userid FROM {$p}user u WHERE {$active} AND u.authority <> 'administrator' AND u.userid <> '" . $db->quote($task['userid']) . "' AND u.id <> (SELECT created_by FROM {$p}tasks WHERE id = " . (int) $task['id'] . ") AND NOT EXISTS (SELECT 1 FROM {$p}user_department ud WHERE ud.userid = u.userid AND ud.project_manager = 1) AND NOT EXISTS (SELECT 1 FROM {$p}project_members pm WHERE pm.user_id = u.id AND pm.role = 'manager') ORDER BY u.id LIMIT 1");
    if (count($other) === 1) {
        ai_test_login($other[0]['userid']);
        $res = $run([['type' => 'task_status', 'id' => (int) $task['id'], 'params' => ['status' => $new]]]);
        check("task_status: somebody else's task is not changed", ($res['status'] ?? '') !== 'success' && ai_test_rows("SELECT status FROM {$p}tasks WHERE id = " . (int) $task['id'])[0]['status'] === $task['status']);
    }
}
ai_test_login(ADMIN_USERID);
$res = $run([['type' => 'task_status', 'id' => 2000000000, 'params' => ['status' => 'paused']]]);
check('task_status: unknown task refused', ($res['status'] ?? '') !== 'success');
$prompt = (new AI())->buildSystemBlocks([])[0]['text'];
check('prompt names the new lookups', strpos($prompt, 'get_my_attendance') !== false && strpos($prompt, 'get_absences') !== false && strpos($prompt, 'get_work_hours') !== false && strpos($prompt, 'get_team_workload') !== false && strpos($prompt, 'task_status') !== false);
$_SESSION['ai_chat'] = [];
finish();
