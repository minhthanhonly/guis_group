<?php
/** What a project manager asks every day: overdue projects on the page, unassigned tasks, projects nobody is in charge of. */
require __DIR__ . '/bootstrap.php';

$p = DB_PREFIX;
ai_test_login(ADMIN_USERID);
$t = new AiTools(new AI());
check('get_unstaffed_projects is a read tool', $t->isReadTool('get_unstaffed_projects'));
$dept = (int) ai_test_rows("SELECT department_id FROM {$p}projects WHERE status = 'in_progress' AND department_id > 0 GROUP BY department_id ORDER BY COUNT(*) DESC LIMIT 1")[0]['department_id'];

// ---- Overdue / due soon, shown on the project list ----
$overdue = (int) ai_test_rows("SELECT COUNT(*) AS c FROM {$p}projects p WHERE p.department_id = {$dept} AND p.end_date < NOW() AND p.status NOT IN ('completed', 'cancelled', 'deleted')")[0]['c'];
$r = $t->runPage('show_projects', ['department_id' => $dept, 'deadline' => 'overdue']);
parse_str((string) parse_url((string) $r['url'], PHP_URL_QUERY), $q);
check('overdue projects open on the page with the page\'s own filter', $q['filterTimeLeft'] === 'overdue' && $q['department_id'] === (string) $dept);
check('overdue count matches the database', $r['data']['count'] === $overdue);
$r = $t->runPage('show_projects', ['department_id' => $dept, 'deadline' => 'within_7_days']);
parse_str((string) parse_url((string) $r['url'], PHP_URL_QUERY), $q);
check('due within 7 days', $q['filterTimeLeft'] === '7' && is_int($r['data']['count']));
$r = $t->runPage('show_projects', ['department_id' => $dept, 'deadline' => 'within_30_days']);
parse_str((string) parse_url((string) $r['url'], PHP_URL_QUERY), $q);
check('due within 30 days', $q['filterTimeLeft'] === '30');
check('unknown deadline filter refused', isset($t->runPage('show_projects', ['deadline' => 'someday'])['data']['error']));
$r = $t->runPage('show_projects', ['department_id' => $dept]);
parse_str((string) parse_url((string) $r['url'], PHP_URL_QUERY), $q);
check('no deadline filter by default', $q['filterTimeLeft'] === '');

// ---- Unassigned tasks, people without tasks ----
require_once DIR_MODEL . 'task.php';
$tm = new Task();
$tm->connect();
$overview = $tm->listOverview(['department_id' => $dept, 'exclude_completed' => 1]);
$unassigned = count(array_filter($overview['tasks'], function ($x) { return empty($x['assigned_to_ids']); }));
$r = $t->runPage('show_task_overview', ['department_id' => $dept, 'unassigned_tasks' => true]);
parse_str((string) parse_url((string) $r['url'], PHP_URL_QUERY), $q);
check('unassigned tasks: page switch on, count of tasks nobody holds', $q['show_unassigned'] === '1' && $q['tab'] === 'tasks' && $r['data']['count'] === $unassigned);
$r = $t->runPage('show_task_overview', ['department_id' => $dept, 'people_without_tasks' => true]);
parse_str((string) parse_url((string) $r['url'], PHP_URL_QUERY), $q);
check('people without tasks: the page\'s tab, count of people', $q['tab'] === 'unassigned' && $r['data']['people_without_tasks'] === count($overview['unassigned_users']) && !isset($r['data']['by_status']));
$r = $t->runPage('show_task_overview', ['department_id' => $dept]);
parse_str((string) parse_url((string) $r['url'], PHP_URL_QUERY), $q);
check('default: assigned and unassigned as the page shows them', $q['show_unassigned'] === '0' && $q['tab'] === 'tasks');

// ---- Projects nobody is in charge of ----
$active = "p.status NOT IN ('completed', 'cancelled', 'deleted')";
$noManager = (int) ai_test_rows("SELECT COUNT(*) AS c FROM {$p}projects p WHERE {$active} AND p.department_id = {$dept} AND NOT EXISTS (SELECT 1 FROM {$p}project_members pm WHERE pm.project_id = p.id AND pm.role = 'manager')")[0]['c'];
$noTeam = (int) ai_test_rows("SELECT COUNT(*) AS c FROM {$p}projects p WHERE {$active} AND p.department_id = {$dept} AND (p.teams IS NULL OR TRIM(p.teams) = '')")[0]['c'];
$r = $t->runRead('get_unstaffed_projects', ['department_id' => $dept]);
check('counts of projects without manager and without team', $r['without_manager'] === $noManager && $r['without_team'] === $noTeam);
check('rows default to projects without a manager, compact, capped', count($r['projects']) === min(10, $noManager) && (count($r['projects']) === 0 || array_keys($r['projects'][0]) === ['id', 'name', 'status', 'department', 'end_date']) && $r['listed'] === 'manager');
$r = $t->runRead('get_unstaffed_projects', ['department_id' => $dept, 'missing' => 'team', 'limit' => 20]);
check('rows for projects without a team', count($r['projects']) === min(20, $noTeam) && $r['listed'] === 'team' && $r['more'] === ($noTeam > 20));
$all = $t->runRead('get_unstaffed_projects', []);
check('without department: all departments', $all['without_manager'] >= $noManager);
check('unknown value refused', isset($t->runRead('get_unstaffed_projects', ['missing' => 'budget'])['error']));

ai_test_login(NORMAL_USERID);
$u = new AiTools(new AI());
check('only managers and administrators get the staffing overview', isset($u->runRead('get_unstaffed_projects', [])['error']));
check('prompt names the new lookups', strpos((new AI())->buildSystemBlocks([])[0]['text'], 'get_unstaffed_projects') !== false);
finish();
