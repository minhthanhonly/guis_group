<?php
/** get_absences over a month or a date range: who took leave and how many days each. Approved applications only. */
require __DIR__ . '/bootstrap.php';

$p = DB_PREFIX;
ai_test_login(NORMAL_USERID);
$t = new AiTools(new AI());
$approved = "status IN ('approved', 'completed')";
$busy = ai_test_rows("SELECT DATE_FORMAT(start_date, '%Y-%m') AS m FROM {$p}requests WHERE type = 'leave' AND {$approved} AND start_date IS NOT NULL GROUP BY m ORDER BY COUNT(*) DESC LIMIT 1")[0]['m'];
$from = $busy . '-01';
$to = date('Y-m-t', strtotime($from));
$inMonth = "type = 'leave' AND {$approved} AND start_date <= '{$to}' AND COALESCE(end_date, start_date) >= '{$from}'";
$db = ai_test_rows("SELECT COUNT(*) AS reqs, COUNT(DISTINCT user_id) AS people, SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(data, '$.days')) AS DECIMAL(6,2))) AS days FROM {$p}requests WHERE {$inMonth}")[0];
$top = ai_test_rows("SELECT r.user_id, u.realname, COUNT(*) AS reqs, SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(r.data, '$.days')) AS DECIMAL(6,2))) AS days FROM (SELECT user_id, data FROM {$p}requests WHERE {$inMonth}) r JOIN {$p}user u ON u.userid = r.user_id GROUP BY r.user_id, u.realname ORDER BY days DESC, u.realname LIMIT 1")[0];

$r = $t->runRead('get_absences', ['month' => $busy]);
check("month {$busy}: the period is the whole month", ($r['period'] ?? null) === ['from' => $from, 'to' => $to]);
check('people who took leave, counted like the applications', ($r['people_on_leave'] ?? null) === (int) $db['people'] && $db['people'] > 0);
check('leave applications and days in total', $r['leave_applications'] === (int) $db['reqs'] && abs($r['leave_days_total'] - (float) $db['days']) < 0.001);
check('one row per person, most leave first', count($r['people']) === min(20, (int) $db['people']) && array_keys($r['people'][0]) === ['name', 'leave_days', 'leave_applications', 'leave_dates', 'outings', 'trips'] && $r['people'][0]['leave_days'] >= $r['people'][count($r['people']) - 1]['leave_days']);
$mine = array_values(array_filter($r['people'], function ($x) use ($top) { return $x['name'] === $top['realname']; }));
check("{$top['realname']}: days and applications as on the forms", count($mine) === 1 && abs($mine[0]['leave_days'] - (float) $top['days']) < 0.001 && $mine[0]['leave_applications'] === (int) $top['reqs']);
check('the dates of the leave are listed', is_string($mine[0]['leave_dates']) && preg_match('/\d{2}-\d{2}/', $mine[0]['leave_dates']) === 1);
check('the reason on the form is never returned', strpos(json_encode($r, JSON_UNESCAPED_UNICODE), 'reason') === false && strpos(json_encode($r), '"data"') === false);
$sample = ai_test_rows("SELECT JSON_UNQUOTE(JSON_EXTRACT(data, '$.reason')) AS reason FROM {$p}requests WHERE {$inMonth} AND JSON_UNQUOTE(JSON_EXTRACT(data, '$.reason')) <> '' LIMIT 1");
check('nor any text of a reason', empty($sample) || mb_strpos(json_encode($r, JSON_UNESCAPED_UNICODE), mb_substr($sample[0]['reason'], 0, 12)) === false);

// A date range gives the same as the month when it covers it.
$r2 = $t->runRead('get_absences', ['date_from' => $from, 'date_to' => $to]);
check('a date range works like the month', $r2['people_on_leave'] === $r['people_on_leave'] && abs($r2['leave_days_total'] - $r['leave_days_total']) < 0.001);
$week = $t->runRead('get_absences', ['date_from' => $from, 'date_to' => date('Y-m-d', strtotime($from . ' +6 day'))]);
check('a shorter range counts fewer or equal', $week['leave_applications'] <= $r['leave_applications']);

// One day still answers as before.
$day = $t->runRead('get_absences', ['date' => $from]);
check('a single day keeps its own answer', isset($day['date'], $day['people']) && !isset($day['period']));

// Input rules.
check('a range needs both ends, in order, at most 62 days', isset($t->runRead('get_absences', ['date_from' => $from])['error']) && isset($t->runRead('get_absences', ['date_from' => $to, 'date_to' => $from])['error']) && isset($t->runRead('get_absences', ['date_from' => '2026-01-01', 'date_to' => '2026-06-30'])['error']));
check('bad month refused', isset($t->runRead('get_absences', ['month' => '2026/06'])['error']));
check('prompt says leave over a month comes from get_absences', strpos((new AI())->buildSystemBlocks([])[0]['text'], 'how many days each') !== false);
finish();
