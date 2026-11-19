<?php
/**
 * Stand-in for ClaudeClient in tests: returns scripted responses, never calls the API.
 * A scripted item that is a ClaudeClientException is thrown instead of returned.
 */
class FakeClaudeClient {
    public $calls = [];
    private $script;
    /** Optional callback run at the start of every send(), e.g. to observe session state. */
    public $onSend = null;

    public function __construct(array $scriptedResponses) {
        $this->script = $scriptedResponses;
    }

    public function isConfigured() {
        return true;
    }

    public function send(array $system, array $tools, array $messages, bool $answerNow = false) {
        if ($this->onSend) {
            call_user_func($this->onSend);
        }
        $this->calls[] = ['system' => $system, 'tools' => $tools, 'messages' => $messages, 'answer_now' => $answerNow];
        if (empty($this->script)) {
            throw new RuntimeException('FakeClaudeClient: script exhausted');
        }
        $next = array_shift($this->script);
        if ($next instanceof Throwable) {
            throw $next;
        }
        return $next;
    }
}

function fake(array $responses) {
    return new FakeClaudeClient($responses);
}

function fakeThrows($reason) {
    return new FakeClaudeClient([new ClaudeClientException($reason)]);
}

function fake_usage() {
    return ['input' => 10, 'output' => 5, 'cache_read' => 0, 'cache_write' => 0];
}

/** A final text answer. */
function text($t) {
    return ['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => $t]], 'text' => $t, 'tool_uses' => [], 'usage' => fake_usage()];
}

/** One assistant turn calling the given tools: ['tool_name' => input, ...]. */
function uses(array $calls, $preamble = '') {
    $content = [];
    $toolUses = [];
    if ($preamble !== '') {
        $content[] = ['type' => 'text', 'text' => $preamble];
    }
    $i = 0;
    foreach ($calls as $name => $input) {
        $id = 'toolu_' . (++$i) . '_' . $name;
        $content[] = ['type' => 'tool_use', 'id' => $id, 'name' => $name, 'input' => $input];
        $toolUses[] = ['id' => $id, 'name' => $name, 'input' => $input];
    }
    return ['stop_reason' => 'tool_use', 'content' => $content, 'text' => $preamble, 'tool_uses' => $toolUses, 'usage' => fake_usage()];
}

/** A response that ends with the given stop reason and no content. */
function stop($reason) {
    return ['stop_reason' => $reason, 'content' => [], 'text' => '', 'tool_uses' => [], 'usage' => fake_usage()];
}
