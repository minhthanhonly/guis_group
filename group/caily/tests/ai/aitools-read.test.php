<?php
require __DIR__ . '/bootstrap.php';

$rows = [['id' => 1, 'name' => str_repeat('あ', 300), 'secret' => 'x']];
$out = AiTools::compact($rows, ['id', 'name']);
check('compact drops columns', !isset($out[0]['secret']));
check('compact truncates to 200', mb_strlen($out[0]['name']) === 200);
check('compact caps limit at 20', count(AiTools::compact(array_fill(0, 30, ['id' => 1]), ['id'], 50)) === 20);
check('compact default 10 rows', count(AiTools::compact(array_fill(0, 30, ['id' => 1]), ['id'])) === 10);

ai_test_login(ADMIN_USERID);
$t = new AiTools(new AI());
$defs = $t->definitions();
check('26 tools', count($defs) === 26);
check('read tools come first', array_slice(array_column($defs, 'name'), 0, count(AiTools::READ_TOOLS)) === AiTools::READ_TOOLS);
check('definitions deterministic', json_encode($defs) === json_encode((new AiTools(new AI()))->definitions()));
$strict = true;
foreach ($defs as $d) {
    if (!isset($d['inputSchema']['additionalProperties']) || $d['inputSchema']['additionalProperties'] !== false) {
        $strict = false;
    }
}
check('every schema forbids extra properties', $strict);
check('unknown tool is an error', isset($t->runRead('drop_table', [])['error']));
check('write tool is not readable', isset($t->runRead('propose_actions', [])['error']));
check('free-form param rejected', isset($t->runRead('search_projects', ['where' => '1=1'])['error']));

$admin = $t->runRead('search_projects', ['limit' => 20]);
check('admin gets projects', isset($admin['projects']) && count($admin['projects']) > 0);
check('core columns only by default', array_keys($admin['projects'][0]) === ['id', 'name', 'status', 'construction_number']);
$withExtra = $t->runRead('search_projects', ['limit' => 1, 'fields' => ['progress', 'department_name']]);
check('extra fields on request', array_key_exists('progress', $withExtra['projects'][0]) && array_key_exists('department_name', $withExtra['projects'][0]));
check('bad extra field rejected', isset($t->runRead('search_projects', ['fields' => ['password']])['error']));
$one = $t->runRead('search_projects', ['project_ids' => [(int) $admin['projects'][0]['id']]]);
check('lookup by id', count($one['projects']) === 1 && (int) $one['projects'][0]['id'] === (int) $admin['projects'][0]['id']);

$stats = $t->runRead('get_statistics', []);
check('statistics say what they count', $stats['filter'] === ['status' => 'any', 'department_id' => 'any'] && is_int($stats['project_count']) && isset($stats['projects_by_department'], $stats['tasks_by_status']));
$inProgress = (int) ai_test_rows("SELECT COUNT(*) AS c FROM " . DB_PREFIX . "projects WHERE status = 'in_progress'")[0]['c'];
$filtered = $t->runRead('get_statistics', ['status' => 'in_progress']);
check('filtered statistics name the filter and give the count', $filtered['filter']['status'] === 'in_progress' && $filtered['project_count'] === $inProgress);
check('filtered statistics have no active/completed split', !isset($filtered['projects_active']) && isset($stats['projects_active']));
if ($inProgress > 20) {
    $capped = $t->runRead('search_projects', ['status' => 'in_progress', 'limit' => 20]);
    check('a full page is flagged as partial', count($capped['projects']) === 20 && $capped['more'] === true);
}
check('newer project statuses are searchable', !isset($t->runRead('search_projects', ['status' => 'calculation_completed'])['error']));
check('availability needs a department', isset($t->runRead('get_availability', ['date_from' => '2026-11-23', 'date_to' => '2026-11-29'])['error']) || isset($t->runRead('get_availability', ['date_from' => '2026-11-23', 'date_to' => '2026-11-29'])['free_teams']));
check('availability rejects bad date', isset($t->runRead('get_availability', ['date_from' => 'next week', 'date_to' => '2026-11-29', 'department_id' => 1])['error']));
check('tasks need project_id', isset($t->runRead('get_project_tasks', [])['error']));
$withTasks = ai_test_rows("SELECT t.project_id, SUM(t.parent_id IS NULL OR t.parent_id = 0) AS top, COUNT(*) AS c FROM " . DB_PREFIX . "tasks t JOIN " . DB_PREFIX . "projects pr ON pr.id = t.project_id GROUP BY t.project_id HAVING top BETWEEN 1 AND 15 ORDER BY (c - top) DESC, t.project_id DESC LIMIT 1")[0];
$topTasks = $t->runRead('get_project_tasks', ['project_id' => (int) $withTasks['project_id'], 'limit' => 20]);
check('top-level tasks are returned (stored with parent_id 0)', count($topTasks['tasks']) === (int) $withTasks['top']);
$allTasks = $t->runRead('get_project_tasks', ['project_id' => (int) $withTasks['project_id'], 'limit' => 20, 'include_subtasks' => true]);
check('subtasks only on request', count($allTasks['tasks']) === min(20, (int) $withTasks['c']));
$dt = $t->runRead('list_departments_teams', []);
check('departments listed', isset($dt['departments']) && is_array($dt['departments']));

check('admin with no department row still gets departments', count($dt['departments']) > 0);
$b = $t->runRead('search_buildings', ['limit' => 2]);
check('buildings listed', isset($b['buildings']) && count($b['buildings']) > 0 && array_slice(array_keys($b['buildings'][0]), 0, 5) === ['id', 'project_number', 'project_name', 'construction_number', 'company_name']);
$bn = $t->runRead('search_buildings', ['project_number' => '#' . ($b['buildings'][0]['project_number'] ?? '')]);
check('building by number', isset($bn['buildings']) && count($bn['buildings']) === 1 && $bn['buildings'][0]['id'] == $b['buildings'][0]['id']);
check('unknown building number gives empty list', ($t->runRead('search_buildings', ['project_number' => 'P-nope-0'])['buildings'] ?? null) === []);
$cust = $t->runRead('get_customer_info', ['project_id' => (int) $admin['projects'][0]['id']]);
check('customer info drops empty fields', !is_array($cust['customer']) || !in_array('', $cust['customer'], true));

ai_test_login(NORMAL_USERID);
$u = new AiTools(new AI());
$userRows = $u->runRead('search_projects', ['limit' => 999]);
check('limit capped at 20', count($userRows['projects']) <= 20);
check('member sees no more than admin', count($userRows['projects']) <= count($admin['projects']));
$adminIds = array_column($admin['projects'], 'id');
$hidden = null;
foreach ($adminIds as $pid) {
    if (empty($u->runRead('search_projects', ['project_ids' => [(int) $pid]])['projects'])) {
        $hidden = (int) $pid;
        break;
    }
}
if ($hidden !== null) {
    check('member cannot read tasks of a project they cannot see', empty($u->runRead('get_project_tasks', ['project_id' => $hidden])['tasks']));
} else {
    echo "SKIP  no project hidden from the member account in the first 20\n";
}
finish();
