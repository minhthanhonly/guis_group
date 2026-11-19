<?php
/**
 * The user picks a default department in the chat bar. The server checks it and then limits every
 * project and task lookup to it, whatever Claude asks for.
 */
require __DIR__ . '/bootstrap.php';

class DepartmentAI extends AI {
    public function departmentsJson(): array {
        ob_start();
        $this->getDepartments();
        return json_decode(ob_get_clean(), true);
    }
}

$p = DB_PREFIX;
ai_test_login(ADMIN_USERID);
(new AI())->ensureUsageTable();
$usageStart = (int) ai_test_rows("SELECT COALESCE(MAX(id), 0) AS m FROM {$p}ai_usage")[0]['m'];
$db = new ApplicationModel();
$db->connect();
register_shutdown_function(function () use ($db, $p, $usageStart) {
    $db->query("DELETE FROM {$p}ai_usage WHERE id > {$usageStart}");
});
$depts = ai_test_rows("SELECT d.id, d.name, COUNT(*) AS c FROM {$p}projects pr JOIN {$p}departments d ON d.id = pr.department_id AND d.is_active = 1 WHERE pr.status = 'in_progress' GROUP BY d.id, d.name ORDER BY c DESC LIMIT 2");
$A = (int) $depts[0]['id'];
$B = (int) $depts[1]['id'];
$nameA = $depts[0]['name'];
$ai = new DepartmentAI();

// ---- The choice is checked on the server ----
check('a department of the user is accepted', $ai->chosenDepartment(['department_id' => $A]) === ['id' => $A, 'name' => $nameA]);
check('no choice, an unknown id or junk means no default', $ai->chosenDepartment([]) === null && $ai->chosenDepartment(['department_id' => 2000000000]) === null && $ai->chosenDepartment(['department_id' => "1 OR 1=1"]) === null && $ai->chosenDepartment(['department_id' => [$A]]) === null);
$list = $ai->departmentsJson();
check('the picker gets the departments of the user', isset($list['departments']) && in_array(['id' => $A, 'name' => $nameA], $list['departments'], true) && array_keys($list['departments'][0]) === ['id', 'name']);

ai_test_login(NORMAL_USERID);
$mine = array_map('intval', array_column((new AI())->getUserDepartments(), 'id'));
$foreign = (int) ai_test_rows("SELECT id FROM {$p}departments WHERE is_active = 1" . (empty($mine) ? '' : ' AND id NOT IN (' . implode(',', $mine) . ')') . " ORDER BY id LIMIT 1")[0]['id'];
check("a member cannot choose a department that is not theirs", (new AI())->chosenDepartment(['department_id' => $foreign]) === null);
ai_test_login(ADMIN_USERID);

// ---- With a default department every project and task lookup is limited to it ----
$t = new AiTools(new AI());
$t->setDepartment($A);
$q = function ($r) {
    parse_str((string) parse_url((string) $r['url'], PHP_URL_QUERY), $query);
    return $query;
};
$r = $t->runPage('show_projects', ['status' => ['in_progress']]);
check('show_projects: the default department, no count of the others', $q($r)['department_id'] === (string) $A && !isset($r['data']['by_department']) && $r['data']['department']['id'] === $A);
$r = $t->runPage('show_projects', ['department_id' => $B, 'status' => ['in_progress']]);
check('show_projects: another department asked by Claude is replaced', $q($r)['department_id'] === (string) $A);
$r = $t->runRead('get_statistics', ['department_id' => $B]);
check('get_statistics: limited to the default department', $r['filter']['department_id'] === $A);
$r = $t->runPage('show_task_overview', []);
check('show_task_overview: limited to the default department', $q($r)['department_id'] === (string) $A);
$r = $t->runRead('search_projects', ['fields' => ['department_name'], 'limit' => 20]);
check('search_projects: only projects of the default department', !empty($r['projects']) && count(array_filter($r['projects'], function ($row) use ($nameA) { return $row['department_name'] !== $nameA; })) === 0);
$free = new AiTools(new AI());
check('get_unstaffed_projects and get_team_workload: limited too', $t->runRead('get_unstaffed_projects', [])['without_manager'] === $free->runRead('get_unstaffed_projects', ['department_id' => $A])['without_manager'] && $t->runRead('get_team_workload', ['department_id' => $B])['people_with_tasks'] === $free->runRead('get_team_workload', ['department_id' => $A])['people_with_tasks']);

// One named project is still found, whichever department it belongs to ("this project" on its page).
$other = (int) ai_test_rows("SELECT id FROM {$p}projects WHERE department_id = {$B} AND status = 'in_progress' ORDER BY id DESC LIMIT 1")[0]['id'];
$r = $t->runRead('search_projects', ['project_ids' => [$other]]);
check('a project named by id is found even in another department', count($r['projects']) === 1 && (int) $r['projects'][0]['id'] === $other);

// Without a default nothing changes.
$r = $free->runPage('show_projects', ['department_id' => $B, 'status' => ['in_progress']]);
check('without a default the department asked for is used', $q($r)['department_id'] === (string) $B);

// ---- Through a conversation: the choice arrives with the page context ----
$ai = new AI();
$f = fake([uses(['show_projects' => ['department_id' => $B, 'status' => ['in_progress']]]), text('ok')]);
$r = $ai->runConversation('進行中の案件を見せて', ['page' => '/project/index.php', 'department_id' => $A], $f);
parse_str((string) parse_url((string) $r['navigate']['url'], PHP_URL_QUERY), $nav);
check('conversation: the page opens for the chosen department', $nav['department_id'] === (string) $A);
$dynamic = $f->calls[0]['system'][1]['text'];
check('conversation: Claude is told the default department', strpos($dynamic, 'Default department') !== false && strpos($dynamic, $nameA) !== false && strpos($dynamic, '(id ' . $A . ')') !== false);
check('the cached part of the prompt does not change with the choice', $f->calls[0]['system'][0]['text'] === AI::SYSTEM_PROMPT);
$f = fake([text('ok')]);
(new AI())->runConversation('x', ['department_id' => 2000000000], $f);
check('conversation: an invalid choice is ignored', strpos($f->calls[0]['system'][1]['text'], 'Default department') === false);

// ---- The bar has the picker ----
ob_start();
require DIR_VIEW . 'ai-chat-bar.php';
$html = ob_get_clean();
check('the bar has a department picker', preg_match('/<select[^>]*id="ai-chat-dept"/', $html) === 1);
check('prompt explains the default department', strpos(AI::SYSTEM_PROMPT, 'default department') !== false && strpos(AI::SYSTEM_PROMPT, 'an amount') === false);
$_SESSION['ai_chat'] = [];
$_SESSION['ai_chat_log'] = [];
finish();
