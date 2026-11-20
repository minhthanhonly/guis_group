<?php
/**
 * A server that cannot run the assistant (old PHP, SDK files missing, usage table cannot be created)
 * or any unexpected error must give the browser a JSON answer that says so, never an empty 500.
 */
require __DIR__ . '/bootstrap.php';

class SetupAI extends AI {
    public $body = [];
    public $problem = null;        // what setupProblem() reports
    public $limitsThrow = null;    // thrown by checkLimits()
    public $clientThrow = null;    // thrown by makeClient()
    public $clientMade = false;
    protected function readJsonBody(): array {
        return $this->body;
    }
    public function setupProblem(): ?array {
        return $this->problem !== null ? $this->problem : parent::setupProblem();
    }
    public function checkLimits(string $message): ?string {
        if ($this->limitsThrow) {
            throw $this->limitsThrow;
        }
        return parent::checkLimits($message);
    }
    protected function makeClient() {
        $this->clientMade = true;
        if ($this->clientThrow) {
            throw $this->clientThrow;
        }
        return fake([text('ok')]);
    }
    public function ask($message) {
        $this->body = ['message' => $message];
        $level = ob_get_level();
        ob_start();
        $this->chat();
        $out = ob_get_clean();
        return ['json' => json_decode($out, true), 'raw' => $out, 'levels_restored' => ob_get_level() === $level];
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
$root = dirname(DIR_MODEL, 2);

// ---- What the server needs ----
check('this server can run the assistant', AI::setupProblemFor(PHP_VERSION_ID, PHP_VERSION, $root) === null);
$old = AI::setupProblemFor(70433, '7.4.33', $root);
check('PHP below 8.1 is reported with the running version', $old !== null && $old['code'] === 'php_version' && strpos($old['detail'], '8.1') !== false && strpos($old['detail'], '7.4.33') !== false);
check('PHP 8.0 is too old as well, 8.1 is enough', AI::setupProblemFor(80030, '8.0.30', $root)['code'] === 'php_version' && AI::setupProblemFor(80100, '8.1.0', $root) === null);
$missing = AI::setupProblemFor(PHP_VERSION_ID, PHP_VERSION, sys_get_temp_dir() . '/no-such-app-root');
check('missing SDK files are reported', $missing !== null && $missing['code'] === 'sdk_missing' && strpos($missing['detail'], 'vendor') !== false);

// ---- The chat endpoint always answers with JSON ----
$ai = new SetupAI();
$ai->problem = ['code' => 'php_version', 'detail' => 'PHP 8.1 以上が必要です（現在 7.4.33）'];
$r = $ai->ask('こんにちは');
check('unusable server: a JSON answer naming the cause', is_array($r['json']) && $r['json']['error'] === 'setup' && strpos($r['json']['reply'], 'PHP 8.1') !== false && strpos($r['json']['reply'], '7.4.33') !== false);
check('unusable server: the SDK is never loaded', $ai->clientMade === false);

$ai = new SetupAI();
$ai->limitsThrow = new RuntimeException("CREATE command denied to user 'app'@'localhost' for table 'groupware_ai_usage'");
$r = $ai->ask('こんにちは');
check('usage table cannot be prepared: a JSON answer pointing to the SQL file', is_array($r['json']) && $r['json']['error'] === 'setup' && strpos($r['json']['reply'], 'ai_usage') !== false && $ai->clientMade === false);
check('the database message itself is not shown to the user', strpos($r['raw'], 'denied') === false && strpos($r['raw'], "'app'") === false);

$ai = new SetupAI();
$ai->clientThrow = new Error('Class "Anthropic\\Client" not found');
$r = $ai->ask('こんにちは');
check('an unexpected error: still a JSON answer', is_array($r['json']) && $r['json']['error'] === 'server' && $r['json']['reply'] !== '' && $r['json']['pending'] === null);
check('an unexpected error: its text stays in the log', strpos($r['raw'], 'Anthropic') === false);
check('output buffers are left as they were', $r['levels_restored'] === true);

$ai = new SetupAI();
$r = $ai->ask('こんにちは');
check('a working server answers as before', is_array($r['json']) && $r['json']['error'] === null && $r['json']['reply'] === 'ok');
$_SESSION['ai_chat'] = [];
$_SESSION['ai_chat_log'] = [];
finish();
