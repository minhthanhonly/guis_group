<?php
/**
 * What the chat box shows again is kept apart from what is sent to the AI: the box shows the whole
 * conversation of the login session, the AI gets only the last few turns of the last half hour.
 */
require __DIR__ . '/bootstrap.php';

class ChatLogAI extends AI {
    public $body = [];
    protected function readJsonBody(): array {
        return $this->body;
    }
    public function shown(): array {
        ob_start();
        $this->getChatHistory();
        return json_decode(ob_get_clean(), true)['history'];
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
$render = function () {
    ob_start();
    require DIR_VIEW . 'ai-chat-bar.php';
    return ob_get_clean();
};

for ($i = 1; $i <= 5; $i++) {
    (new AI())->runConversation('question ' . $i, [], fake([text('answer ' . $i)]));
}
$ai = new ChatLogAI();
$shown = $ai->shown();
check('the AI is sent only the last turns', count($ai->chatHistory) === AI::HISTORY_ITEMS);
check('the box shows the whole conversation again', count($shown) === 10 && $shown[0] === ['role' => 'user', 'text' => 'question 1'] && $shown[9] === ['role' => 'assistant', 'text' => 'answer 5']);

// A long answer is shown again in full, although the AI gets a shortened copy.
$long = str_repeat('あ', 3000);
(new AI())->runConversation('long one', [], fake([text($long)]));
$ai = new ChatLogAI();
$shown = $ai->shown();
check('a long answer comes back whole', $shown[count($shown) - 1]['text'] === $long && mb_strlen($ai->chatHistory[count($ai->chatHistory) - 1]['text']) === AI::HISTORY_ITEM_CHARS);

// Half an hour later the AI starts afresh, the box still shows what was said.
$_SESSION['ai_chat_at'] = time() - AI::HISTORY_IDLE_SECONDS - 5;
$ai = new ChatLogAI();
check('after the idle limit the AI has no context', count($ai->chatHistory) === 0);
check('after the idle limit the box still shows the conversation', count($ai->shown()) === 12);
check('the bar says there is something to show', strpos($render(), 'data-has-history="1"') !== false);

// Fixed replies (a blocked question, a failed call) were on screen too.
$ai = new ChatLogAI();
$ai->body = ['message' => '今月の売上を見せて'];
ob_start();
$ai->chat();
ob_end_clean();
$shown = (new ChatLogAI())->shown();
check('a blocked question and its reply are shown again', count($shown) === 14 && $shown[12]['text'] === '今月の売上を見せて' && $shown[13]['text'] === AI::MESSAGES['money']);
check('a blocked question is not kept for the AI', strpos(json_encode($_SESSION['ai_chat'] ?? [], JSON_UNESCAPED_UNICODE), '売上') === false);
(new AI())->runConversation('will fail', [], fakeThrows('unavailable'));
$shown = (new ChatLogAI())->shown();
check('a failed call is shown again with its error text', count($shown) === 16 && $shown[14]['text'] === 'will fail' && $shown[15]['text'] === AI::MESSAGES['unavailable']);

// The log is bounded.
for ($i = 1; $i <= 12; $i++) {
    (new AI())->runConversation('more ' . $i, [], fake([text('ok ' . $i)]));
}
$shown = (new ChatLogAI())->shown();
check('the log keeps the latest ' . AI::LOG_ITEMS . ' messages', count($shown) === AI::LOG_ITEMS && $shown[count($shown) - 1]['text'] === 'ok 12');

// New conversation: everything is gone.
$ai = new ChatLogAI();
ob_start();
$ai->clearChat();
ob_end_clean();
check('new conversation empties the box and the context', count((new ChatLogAI())->shown()) === 0 && count((new AI())->chatHistory) === 0);
check('the bar then has nothing to show', strpos($render(), 'data-has-history="0"') !== false);
finish();
