<?php
/**
 * Lazy Redis client (Predis). Fail-soft: returns null when disabled or unreachable.
 */
class RedisClient
{
    /** @var \Predis\Client|null|false null=untried, false=unavailable, Client=ok */
    private static $client = null;

    /** @var bool */
    private static $loggedFailure = false;

    /**
     * @return \Predis\Client|null
     */
    public static function get()
    {
        if (self::$client === false) {
            return null;
        }
        if (self::$client !== null) {
            return self::$client;
        }

        if (!self::isEnabled()) {
            self::$client = false;
            return null;
        }

        self::loadPredis();
        if (!class_exists('Predis\\Client')) {
            self::logOnce('Predis\\Client not found (upload vendor/predis or run composer)');
            self::$client = false;
            return null;
        }

        $params = [
            'scheme' => 'tcp',
            'host' => defined('REDIS_HOST') ? REDIS_HOST : '127.0.0.1',
            'port' => defined('REDIS_PORT') ? (int) REDIS_PORT : 6379,
            'database' => defined('REDIS_DB') ? (int) REDIS_DB : 0,
            'timeout' => 0.5,
            'read_write_timeout' => 0.5,
        ];
        $password = defined('REDIS_PASSWORD') ? REDIS_PASSWORD : '';
        if ($password !== '') {
            $params['password'] = $password;
        }

        try {
            $client = new \Predis\Client($params);
            $client->ping();
            self::$client = $client;
            return self::$client;
        } catch (\Throwable $e) {
            self::logOnce('Redis unavailable: ' . $e->getMessage());
            self::$client = false;
            return null;
        }
    }

    public static function isEnabled()
    {
        if (defined('REDIS_ENABLED')) {
            $v = REDIS_ENABLED;
            if (is_bool($v)) {
                return $v;
            }
            $v = strtolower(trim((string) $v));
            return in_array($v, ['1', 'true', 'yes', 'on'], true);
        }
        $host = getenv('REDIS_HOST');
        return $host !== false && $host !== '';
    }

    public static function prefix()
    {
        return defined('REDIS_PREFIX') ? REDIS_PREFIX : 'caily:apicache:';
    }

    /**
     * Load Predis without bootstrapping the whole Composer vendor/autoload.php
     * (avoids breaking the site when only part of vendor/ was uploaded).
     */
    private static function loadPredis()
    {
        if (class_exists('Predis\\Client', false)) {
            return;
        }
        $candidates = array(
            dirname(__DIR__, 2) . '/vendor/predis/predis/autoload.php',
            dirname(dirname(__DIR__)) . '/vendor/predis/predis/autoload.php',
        );
        if (defined('DIR_PATH')) {
            $candidates[] = rtrim(DIR_PATH, '/\\') . '/../vendor/predis/predis/autoload.php';
        }
        foreach ($candidates as $path) {
            if (is_file($path)) {
                require_once $path;
                return;
            }
        }
    }

    private static function logOnce($message)
    {
        if (self::$loggedFailure) {
            return;
        }
        self::$loggedFailure = true;
        error_log('[RedisClient] ' . $message);
    }
}
