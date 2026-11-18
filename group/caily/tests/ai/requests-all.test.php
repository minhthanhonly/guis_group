<?php
/**
 * get_requests with scope "all": every application (of any of the 13 form types) that the logged-in
 * user is allowed to see on the applications page. Who sees what is decided by Request::list.
 */
require __DIR__ . '/bootstrap.php';

$p = DB_PREFIX;
$busy = ai_test_rows("SELECT DATE_FORMAT(start_date, '%Y-%m') AS m FROM {$p}requests WHERE start_date IS NOT NULL GROUP BY m ORDER BY COUNT(*) DESC LIMIT 1")[0]['m'];
$from = $busy . '-01';
$to = date('Y-m-t', strtotime($from));
// The period rule of the applications page.
$inPeriod = "((start_date IS NOT NULL AND start_date >= '{$from}' AND start_date <= '{$to}') OR (end_date IS NOT NULL AND end_date >= '{$from}' AND end_date <= '{$to}') OR (start_date IS NULL AND end_date IS NULL AND DATE(created_at) >= '{$from}' AND DATE(created_at) <= '{$to}'))";
$count = function ($where) use ($p) {
    return (int) ai_test_rows("SELECT COUNT(*) AS c FROM {$p}requests WHERE {$where}")[0]['c'];
};
$KEYS = ['id', 'type', 'status', 'applicant', 'created', 'start_date', 'end_date', 'detail'];

// ---- An administrator sees every application ----
ai_test_login(ADMIN_USERID);
$t = new AiTools(new AI());
$r = $t->runRead('get_requests', ['scope' => 'all', 'month' => $busy]);
$total = $count($inPeriod);
check("administrator, {$busy}: every application of the month", ($r['scope'] ?? null) === 'all' && $r['total'] === $total && $total > 0 && $r['period'] === ['from' => $from, 'to' => $to]);
check('rows are compact', count($r['requests']) === min(10, $total) && array_keys($r['requests'][0]) === $KEYS);
$byType = [];
foreach (ai_test_rows("SELECT type, COUNT(*) AS c FROM {$p}requests WHERE {$inPeriod} GROUP BY type") as $row) {
    $byType[$row['type']] = (int) $row['c'];
}
arsort($byType);
check('counts per form type', $total > 200 || $r['by_type'] == $byType);
check('counts per person, most applications first', isset($r['by_person'][0]['name'], $r['by_person'][0]['applications']) && $r['by_person'][0]['applications'] >= $r['by_person'][count($r['by_person']) - 1]['applications'] && count($r['by_person']) <= 20);
check('the summary says how much it covers', $r['summary_covers'] === min(200, $total));

foreach (['overtime', 'attendance_correction', 'holiday_work'] as $type) {
    $r = $t->runRead('get_requests', ['scope' => 'all', 'month' => $busy, 'type' => $type]);
    check("form type {$type}: counted like the table", $r['total'] === $count("{$inPeriod} AND type = '{$type}'") && count(array_filter($r['requests'], function ($x) use ($type) { return $x['type'] !== $type; })) === 0);
}
$r = $t->runRead('get_requests', ['scope' => 'all', 'month' => $busy, 'type' => 'overtime', 'limit' => 5]);
check('time-based forms show their time', empty($r['requests']) || preg_match('/^\d{1,2}:\d{2}/', (string) $r['requests'][0]['detail']) === 1);
$r = $t->runRead('get_requests', ['scope' => 'all', 'type' => 'leave', 'status' => 'approved', 'limit' => 5]);
check('leave shows its number of days', empty($r['requests']) || preg_match('/^\d+(\.\d+)? /', (string) $r['requests'][0]['detail']) === 1);
$everything = json_encode($t->runRead('get_requests', ['scope' => 'all', 'month' => $busy, 'limit' => 20]), JSON_UNESCAPED_UNICODE);
check('no reason, note, destination, purchase lines or amount leaves the server', preg_match('/reason|"note"|destination|purpose|total_amount|"lines"|"data"/', $everything) === 0);

// One applicant.
$person = ai_test_rows("SELECT r.user_id, u.realname, COUNT(*) AS c FROM {$p}requests r JOIN {$p}user u ON u.userid = r.user_id WHERE r.user_id <> '" . ADMIN_USERID . "' AND r.start_date BETWEEN '{$from}' AND '{$to}' GROUP BY r.user_id, u.realname ORDER BY c DESC LIMIT 1")[0];
$r = $t->runRead('get_requests', ['scope' => 'all', 'month' => $busy, 'applicant_name' => $person['realname']]);
check("one applicant ({$person['realname']}): only their applications", $r['total'] === $count("{$inPeriod} AND user_id = '" . addslashes($person['user_id']) . "'") && count(array_filter($r['requests'], function ($x) use ($person) { return $x['applicant'] !== $person['realname']; })) === 0);
check('unknown applicant refused', isset($t->runRead('get_requests', ['scope' => 'all', 'applicant_name' => 'zz-nobody-zz'])['error']));

// ---- An ordinary member sees only their own, whatever is asked ----
$member = ai_test_rows("SELECT u.userid, u.realname FROM {$p}user u JOIN {$p}requests r ON r.user_id = u.userid WHERE u.authority <> 'administrator' AND u.show_project = 1 AND (u.is_suspend = 0 OR u.is_suspend IS NULL) AND IFNULL(u.can_approve_request, 0) = 0 AND IFNULL(u.is_soumu, 0) = 0 GROUP BY u.userid, u.realname ORDER BY COUNT(*) DESC LIMIT 1")[0];
ai_test_login($member['userid']);
$m = new AiTools(new AI());
$own = $count("user_id = '" . addslashes($member['userid']) . "'");
$r = $m->runRead('get_requests', ['scope' => 'all', 'limit' => 20]);
check('a member asking for "all" gets their own applications only', $r['total'] === $own && $own > 0 && count(array_filter($r['requests'], function ($x) use ($member) { return $x['applicant'] !== $member['realname']; })) === 0);
check('and the summary names nobody else', count($r['by_person']) === 1 && $r['by_person'][0]['name'] === $member['realname']);
$other = $member['userid'] === $person['user_id'] ? ADMIN_USERID : $person['user_id'];
$otherName = ai_test_rows("SELECT realname FROM {$p}user WHERE userid = '" . addslashes($other) . "'")[0]['realname'];
$r = $m->runRead('get_requests', ['scope' => 'all', 'applicant_name' => $otherName]);
check("a member cannot read somebody else's applications", isset($r['error']) && !isset($r['requests']));
$r = $m->runRead('get_requests', ['scope' => 'all', 'applicant_name' => $member['realname']]);
check('but may name themselves', !isset($r['error']) && $r['total'] === $own);

// ---- The earlier scopes still work, now with the period filter ----
ai_test_login(ADMIN_USERID);
$r = $t->runRead('get_requests', ['scope' => 'mine', 'date_from' => $from, 'date_to' => $to]);
check('mine with a period', $r['scope'] === 'mine' && $r['total'] === $count("{$inPeriod} AND user_id = '" . ADMIN_USERID . "'"));
check('a range needs both ends, in order, at most 92 days', isset($t->runRead('get_requests', ['date_from' => $from])['error']) && isset($t->runRead('get_requests', ['date_from' => $to, 'date_to' => $from])['error']) && isset($t->runRead('get_requests', ['date_from' => '2026-01-01', 'date_to' => '2026-12-31'])['error']));
check('unknown scope refused', isset($t->runRead('get_requests', ['scope' => 'everyone'])['error']));
check('prompt explains scope all', strpos((new AI())->buildSystemBlocks([])[0]['text'], 'scope all') !== false);
finish();
