<?php
/**
 * The only place that talks to the Claude API (official anthropic-ai/sdk).
 * Returns a plain array so callers and tests do not depend on SDK classes.
 */
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;

class ClaudeClientException extends Exception {
    /** @var string not_configured | rate_limited | unavailable | bad_request */
    public $reason;

    public function __construct($reason) {
        parent::__construct('Claude API: ' . $reason);
        $this->reason = $reason;
    }
}

class ClaudeClient {
    const DEFAULT_MODEL = 'claude-haiku-5-5';
    const DEFAULT_EFFORT = 'medium';
    const MAX_TOKENS = 8000;
    const TIMEOUT_SECONDS = 60;
    const CONNECT_TIMEOUT_SECONDS = 10;

    private $apiKey;
    private $model;
    private $effort;

    public function __construct(?string $apiKey = null, ?string $model = null, ?string $effort = null) {
        // app_env() (application/config.php), not the process environment: under Apache that one can
        // lose its values in the middle of a request.
        $this->apiKey = $apiKey !== null ? $apiKey : (string) app_env('ANTHROPIC_API_KEY');
        $this->model = $model !== null ? $model : (app_env('CLAUDE_MODEL') ?: self::DEFAULT_MODEL);
        $this->effort = $effort !== null ? $effort : (app_env('CLAUDE_EFFORT') ?: self::DEFAULT_EFFORT);
    }

    public function isConfigured(): bool {
        return trim($this->apiKey) !== '';
    }

    /**
     * The SDK's own `timeout` option is advisory, so the limit is set on the HTTP client:
     * a stalled call must not hold a PHP worker forever.
     *
     * TLS certificates are always verified. Some PHP installs (AMPPS on Windows, for one) ship without
     * a CA store, so the store is chosen by composer/ca-bundle: the system's if it has one, else the
     * Mozilla bundle that comes with the package.
     */
    public function requestOptions(): array {
        return [
            'timeout' => (float) self::TIMEOUT_SECONDS,
            'maxRetries' => 1,
            'transporter' => new \GuzzleHttp\Client([
                'timeout' => self::TIMEOUT_SECONDS,
                'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
                'verify' => \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath(),
            ]),
        ];
    }

    /** Named arguments for $client->messages->create(). Keys are the SDK's camelCase names. */
    public function buildParams(array $system, array $tools, array $messages, bool $answerNow = false): array {
        $params = [
            'model' => $this->model,
            'maxTokens' => self::MAX_TOKENS,
            'messages' => $messages,
            'outputConfig' => ['effort' => $this->effort],
            // Caches the conversation so far, so the follow-up requests of one tool loop re-read it at the cache price.
            'cacheControl' => ['type' => 'ephemeral'],
        ];
        if (!empty($system)) {
            $params['system'] = $system;
        }
        if (!empty($tools)) {
            $params['tools'] = $tools;
            if ($answerNow) {
                // The tools stay (earlier turns refer to them) but Claude has to answer with what it has.
                $params['toolChoice'] = ['type' => 'none'];
            }
        }
        return $params;
    }

    /**
     * @return array ['stop_reason' => string, 'content' => array, 'text' => string,
     *                'tool_uses' => [['id','name','input']], 'usage' => ['input','output','cache_read','cache_write']]
     *               'content' holds the SDK blocks unchanged, to append back to messages.
     * @throws ClaudeClientException
     */
    public function send(array $system, array $tools, array $messages, bool $answerNow = false): array {
        if (!$this->isConfigured()) {
            throw new ClaudeClientException('not_configured');
        }
        try {
            $client = new Client(apiKey: $this->apiKey);
            $params = $this->buildParams($system, $tools, $messages, $answerNow);
            $params['requestOptions'] = $this->requestOptions();
            $message = $client->messages->create(...$params);
        } catch (RateLimitException $e) {
            throw $this->fail('rate_limited', $e);
        } catch (AuthenticationException | PermissionDeniedException $e) {
            throw $this->fail('not_configured', $e);
        } catch (APIStatusException $e) {
            throw $this->fail(((int) $e->status) >= 500 ? 'unavailable' : 'bad_request', $e);
        } catch (APIConnectionException $e) {
            throw $this->fail('unavailable', $e);
        } catch (\Throwable $e) {
            throw $this->fail('unavailable', $e);
        }

        $text = '';
        $toolUses = [];
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            } elseif ($block->type === 'tool_use') {
                $toolUses[] = ['id' => $block->id, 'name' => $block->name, 'input' => is_array($block->input) ? $block->input : []];
            }
        }
        $usage = $message->usage;
        return [
            'stop_reason' => (string) $message->stopReason,
            'content' => $message->content,
            'text' => $text,
            'tool_uses' => $toolUses,
            'usage' => [
                'input' => (int) $usage->inputTokens,
                'output' => (int) $usage->outputTokens,
                'cache_read' => (int) ($usage->cacheReadInputTokens ?? 0),
                'cache_write' => (int) ($usage->cacheCreationInputTokens ?? 0),
            ],
        ];
    }

    /** Logs the real cause server-side only; the caller gets a reason code with no API detail. */
    private function fail($reason, \Throwable $e) {
        $detail = str_replace($this->apiKey, '[key]', $e->getMessage());
        error_log('ClaudeClient ' . $reason . ' (' . get_class($e) . '): ' . mb_substr($detail, 0, 500));
        return new ClaudeClientException($reason);
    }
}
