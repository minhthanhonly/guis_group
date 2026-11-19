<?php
/** The assistant does not handle revenue or amounts of money: no tool, no data, no answer. */
require __DIR__ . '/bootstrap.php';

$p = DB_PREFIX;
ai_test_login(ADMIN_USERID);
$t = new AiTools(new AI());
$names = array_column($t->definitions(), 'name');
check('26 tools, none for revenue', count($names) === 26 && !in_array('show_revenue_statistics', $names, true) && !in_array('show_revenue_statistics', AiTools::PAGE_TOOLS, true));
check('the revenue tool no longer runs', $t->runPage('show_revenue_statistics', ['department_id' => 1])['url'] === null);
check('no gateway to the revenue numbers', !method_exists('AI', 'revenueStatsForAi'));
check('no tool text offers money', preg_match('/revenue|amount|price|売上|金額/i', json_encode($t->definitions(), JSON_UNESCAPED_UNICODE)) === 0);

// Project data never carries the amount.
check('amount is not a project column', !isset(AiTools::PROJECT_EXTRA['amount']));
check('asking for the amount column is refused', isset($t->runRead('search_projects', ['fields' => ['amount']])['error']));
$rows = $t->runRead('search_projects', ['fields' => ['progress', 'end_date'], 'limit' => 5]);
check('project rows have no amount', !empty($rows['projects']) && strpos(json_encode($rows), 'amount') === false);

// Amounts cannot be written through the assistant either.
$v = $t->validateActions([['type' => 'project_update', 'id' => 5, 'params' => ['name' => 'x', 'amount' => 500000]]]);
check('amount is dropped from a project change', $v['ok'] === true && !isset($v['actions'][0]['params']['amount']));
check('amount is not a parameter of any action', strpos(json_encode(AiTools::ACTION_PARAMS), 'amount') === false);

// Questions about revenue or amounts are answered by the server, without calling the AI.
foreach (['今月の売上を見せて', 'この案件の金額はいくらですか', '請求額を教えて', '利益はどのくらい？', 'Doanh thu tháng này thế nào?', 'dự án này số tiền bao nhiêu', 'Đơn giá của bản vẽ này', 'tổng tiền hợp đồng', 'show me the revenue of November', 'What is the price?'] as $q) {
    check('blocked: ' . $q, AI::asksAboutMoney($q) === true);
}
foreach (['進行中の案件は何件ありますか', '請求書待ちの案件を見せて', 'お客様への納期連絡メールを書いて', 'Cho tôi xem các dự án trễ hạn', 'đánh giá tiến độ dự án này', 'tiền sảnh tầng 1 đã vẽ xong chưa', 'who is free next week?'] as $q) {
    check('allowed: ' . $q, AI::asksAboutMoney($q) === false);
}
$ai = new AI();
$ai->ensureUsageTable();
$before = (int) ai_test_rows("SELECT COUNT(*) AS c FROM {$p}ai_usage")[0]['c'];
check('a money question is stopped before the limits are counted', $ai->checkLimits('今月の売上を見せて') === 'money');
check('nothing was counted or sent', (int) ai_test_rows("SELECT COUNT(*) AS c FROM {$p}ai_usage")[0]['c'] === $before);
check('the reply says so in both languages', strpos(AI::MESSAGES['money'], '売上') !== false && strpos(AI::MESSAGES['money'], 'doanh thu') !== false);

$prompt = $ai->buildSystemBlocks([])[0]['text'];
check('prompt: money is out of scope', strpos($prompt, 'Money is out of scope') !== false && strpos($prompt, 'show_revenue_statistics') === false && strpos($prompt, 'add up the amount') === false);
finish();
