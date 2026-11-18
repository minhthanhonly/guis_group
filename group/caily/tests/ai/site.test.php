<?php
/**
 * Tools for the rest of the site: task overview, revenue statistics, requests (申請), project notes,
 * customers, and opening more pages. Read and navigation only: nothing here changes data.
 */
require __DIR__ . '/bootstrap.php';

$p = DB_PREFIX;
ai_test_login(ADMIN_USERID);
$ai = new AI();
$t = new AiTools($ai);
$names = array_column($t->definitions(), 'name');
check('26 tools', count($names) === 26);
check('new tools are registered', count(array_diff(['show_task_overview', 'show_requests', 'get_project_notes', 'search_customers'], $names)) === 0);
check('new page tools are page tools', count(array_diff(['show_task_overview', 'show_requests'], AiTools::PAGE_TOOLS)) === 0);
check('new read tools are read tools', $t->isReadTool('get_project_notes') && $t->isReadTool('search_customers'));

// ---- Task overview ----
require_once DIR_MODEL . 'task.php';
$dept = (int) ai_test_rows("SELECT department_id FROM {$p}projects WHERE status = 'in_progress' AND department_id > 0 GROUP BY department_id ORDER BY COUNT(*) DESC LIMIT 1")[0]['department_id'];
$tm = new Task();
$tm->connect();
$expected = $tm->listOverview(['department_id' => $dept, 'exclude_completed' => 1])['tasks'];
$r = $t->runPage('show_task_overview', ['department_id' => $dept]);
parse_str((string) parse_url((string) $r['url'], PHP_URL_QUERY), $q);
check('task overview opens with the filters', strpos((string) $r['url'], '/project/task_overview.php?') === 0 && $q['department_id'] === (string) $dept && $q['exclude_completed'] === '1' && $q['tab'] === 'tasks');
check('task overview count matches the page source', $r['data']['shown_on_page'] === true && $r['data']['count'] === count($expected) && array_sum($r['data']['by_status']) === count($expected));
$r = $t->runPage('show_task_overview', ['my_tasks' => true, 'include_completed' => true]);
parse_str((string) parse_url((string) $r['url'], PHP_URL_QUERY), $q);
check('my tasks uses the logged-in user id', $q['user_id'] === (string) (int) $_SESSION['id'] && $q['my_task'] === '1' && $q['exclude_completed'] === '0');
$r = $t->runPage('show_task_overview', ['person_name' => 'no-such-person-xyz']);
check('unknown person: error, no navigation', isset($r['data']['error']) && $r['url'] === null);
check('bad month refused', isset($t->runPage('show_task_overview', ['created_month' => '2026/11'])['data']['error']));
$r = $t->runPage('show_task_overview', ['created_month' => '2026-11']);
parse_str((string) parse_url((string) $r['url'], PHP_URL_QUERY), $q);
check('month filter passed on', $q['created_month'] === '2026-11');

// Revenue statistics are no longer offered: see money-block.test.php.

// ---- Requests (申請) ----
$r = $t->runPage('show_requests', ['status' => 'pending']);
$adminPending = $r['data']['count'] ?? null;
check('requests page opens filtered', $r['url'] === '/form/index.php?status=pending' && is_int($adminPending));
$r = $t->runPage('show_requests', ['status' => 'approved', 'type' => 'leave']);
check('request type goes to the tab', $r['url'] === '/form/index.php?status=approved&tab=leave');
check('unknown request type refused', isset($t->runPage('show_requests', ['type' => 'salary'])['data']['error']));

// ---- Project notes ----
$noteProject = ai_test_rows("SELECT n.project_id, COUNT(*) AS c FROM {$p}project_notes n JOIN {$p}projects pr ON pr.id = n.project_id GROUP BY n.project_id ORDER BY c DESC LIMIT 1");
if (count($noteProject) === 1) {
    $npid = (int) $noteProject[0]['project_id'];
    $n = $t->runRead('get_project_notes', ['project_id' => $npid, 'limit' => 20]);
    check('notes returned, compact', isset($n['notes']) && count($n['notes']) > 0 && count($n['notes']) <= 20 && array_keys($n['notes'][0]) === ['id', 'label', 'content', 'important', 'author', 'updated_at']);
    $long = array_filter($n['notes'], function ($x) { return mb_strlen((string) $x['content']) > 200; });
    check('note text is cut to 200 characters and has no markup', count($long) === 0 && strpos(json_encode($n['notes']), '<') === false);
} else {
    echo "SKIP  no project notes in the local DB\n";
    $npid = 0;
}
check('notes need a project', isset($t->runRead('get_project_notes', [])['error']));
check('notes of a missing project: error', isset($t->runRead('get_project_notes', ['project_id' => 2000000000])['error']));

// ---- Customers ----
$cust = ai_test_rows("SELECT company_name FROM {$p}customer WHERE company_name <> '' ORDER BY updated_at DESC LIMIT 1");
if (count($cust) === 1) {
    $c = $t->runRead('search_customers', ['keyword' => mb_substr($cust[0]['company_name'], 0, 3)]);
    check('customers found by company name', isset($c['customers']) && count($c['customers']) > 0 && count($c['customers']) <= 10 && array_keys($c['customers'][0]) === ['id', 'company_name', 'branch', 'contact_name', 'category']);
}
check('customer search needs a keyword', isset($t->runRead('search_customers', [])['error']) && isset($t->runRead('search_customers', ['keyword' => '  '])['error']));
$getBefore = $_GET;
$t->runRead('search_customers', ['keyword' => 'x']);
check('request globals are left as they were', $_GET === $getBefore);

// ---- A plain member ----
ai_test_login(NORMAL_USERID);
$u = new AiTools(new AI());
$r = $u->runPage('show_requests', ['status' => 'pending']);
check('a member counts only the requests they may see', is_int($r['data']['count']) && $r['data']['count'] <= $adminPending);
if ($npid > 0 && empty($u->runRead('search_projects', ['project_ids' => [$npid]])['projects'])) {
    check('no notes of a project the member cannot see', isset($u->runRead('get_project_notes', ['project_id' => $npid])['error']));
}
$_SESSION['show_project'] = 0;
check('nothing without project access', isset((new AiTools(new AI()))->runPage('show_task_overview', [])['data']['error']) && isset((new AiTools(new AI()))->runRead('search_customers', ['keyword' => 'a'])['error']));
$_SESSION['show_project'] = 1;

// ---- open_page: more pages, still a fixed list ----
ai_test_login(ADMIN_USERID);
$usageTable = $p . 'ai_usage';
$db = new ApplicationModel();
$db->connect();
(new AI())->ensureUsageTable();
$start = (int) ai_test_rows("SELECT COALESCE(MAX(id), 0) AS m FROM {$usageTable}")[0]['m'];
register_shutdown_function(function () use ($db, $usageTable, $start) {
    $db->query("DELETE FROM {$usageTable} WHERE id > {$start} AND userid = '" . $db->quote(ADMIN_USERID) . "'");
});
$open = function (array $input) {
    return (new AI())->runConversation('x', [], fake([uses(['open_page' => $input])]));
};
check('open a building', $open(['page' => 'building', 'id' => 9])['navigate'] === ['url' => '/parent_project/detail.php?id=9']);
check('open a request', $open(['page' => 'request', 'id' => 12])['navigate'] === ['url' => '/form/detail.php?id=12']);
check('open the schedule', $open(['page' => 'schedule'])['navigate'] === ['url' => '/schedule/index.php']);
check('open employee statistics', $open(['page' => 'employee_statistics'])['navigate'] === ['url' => '/project/employee_statistics.php']);
check('project pages still work', $open(['page' => 'gantt', 'project_id' => 23])['navigate'] === ['url' => '/project/gantt.php?project_id=23']);
check('a building needs its id', $open(['page' => 'building'])['navigate'] === null);
check('unknown page refused', $open(['page' => 'backup'])['navigate'] === null && $open(['page' => '/setting/backup.php'])['navigate'] === null);
$_SESSION['ai_chat'] = [];
finish();
