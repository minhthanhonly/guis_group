<?php
/**
 * Cache phản hồi API theo từng user (chỉ GET, whitelist model+method).
 * Key: userid_model_method_v{scopeVer}_[hash(payload)]
 * Backend: Redis (SETEX + index SET) khi bật; fallback file application/cache/api/
 * Shared list freshness: bumpScopeVersion(model) khi DB đổi → key cũ tự miss.
 */
class ApiCache {

    /** Thư mục cache (application/cache/api/) */
    private static $cacheDir;

    /** TTL mặc định (giây) — lưới an toàn; freshness chính = invalidate/bump */
    const DEFAULT_TTL = 900;

    /**
     * Danh sách endpoint được phép cache: [ 'model' => [ 'method1', 'method2' ] ]
     */
    private static $cacheable = [
        'department' => [ 'list_department', 'get_users', 'get_user_permission_by_department', 'get_user_permissions', 'getAll' ],
        'user' => [ 'searchMembers', 'getMentionUsers'],
        'customer' => [ 'list_category', 'list_categories'],
    ];

    /** model API → scope version (shared data) */
    private static $modelScopes = [
        'department' => 'department',
        'user' => 'user',
        'customer' => 'customer_category',
    ];

    private static function ensureRedisClientLoaded()
    {
        if (!class_exists('RedisClient', false)) {
            $path = dirname(__FILE__) . '/RedisClient.php';
            if (is_file($path)) {
                require_once $path;
            }
        }
    }

    private static function getCacheDir()
    {
        if (self::$cacheDir === null) {
            $base = defined('DIR_PATH') ? DIR_PATH : dirname(dirname(__FILE__)) . '/';
            self::$cacheDir = rtrim($base, '/\\') . '/cache/api/';
            if (!is_dir(self::$cacheDir)) {
                @mkdir(self::$cacheDir, 0755, true);
            }
        }
        return self::$cacheDir;
    }

    private static function versionDir()
    {
        $dir = self::getCacheDir() . 'versions/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Scope version cho model (dùng trong cache key). File + Redis đều hỗ trợ.
     */
    public static function getScopeVersion($modelOrScope)
    {
        $scope = self::resolveScope($modelOrScope);
        if ($scope === '') {
            return '0';
        }

        self::ensureRedisClientLoaded();
        if (class_exists('RedisClient', false)) {
            $redis = RedisClient::get();
            if ($redis) {
                try {
                    $key = RedisClient::prefix() . 'ver:' . $scope;
                    $v = $redis->get($key);
                    if ($v === null || $v === false || $v === '') {
                        $redis->set($key, '1');
                        return '1';
                    }
                    return (string) $v;
                } catch (\Throwable $e) {
                    // fall through to file
                }
            }
        }

        $file = self::versionDir() . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $scope) . '.ver';
        if (!is_file($file)) {
            @file_put_contents($file, '1');
            return '1';
        }
        $raw = @file_get_contents($file);
        $n = intval(trim((string) $raw));
        return (string) max(1, $n);
    }

    /**
     * Tăng version scope → mọi cache key gắn version cũ tự miss (mọi user).
     */
    public static function bumpScopeVersion($modelOrScope)
    {
        $scope = self::resolveScope($modelOrScope);
        if ($scope === '') {
            return true;
        }

        self::ensureRedisClientLoaded();
        if (class_exists('RedisClient', false)) {
            $redis = RedisClient::get();
            if ($redis) {
                try {
                    $key = RedisClient::prefix() . 'ver:' . $scope;
                    $redis->incr($key);
                } catch (\Throwable $e) {
                    // continue to file bump
                }
            }
        }

        $file = self::versionDir() . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $scope) . '.ver';
        $cur = 1;
        if (is_file($file)) {
            $cur = max(1, intval(trim((string) @file_get_contents($file))));
        }
        @file_put_contents($file, (string) ($cur + 1));
        return true;
    }

    private static function resolveScope($modelOrScope)
    {
        $s = strtolower(trim((string) $modelOrScope));
        if ($s === '') {
            return '';
        }
        if (isset(self::$modelScopes[$s])) {
            return self::$modelScopes[$s];
        }
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $s);
    }

    /**
     * Kiểm tra request GET có được cache không.
     */
    public static function isCacheable($model, $method)
    {
        $model = strtolower($model);
        if (!isset(self::$cacheable[$model])) {
            return false;
        }
        return in_array($method, self::$cacheable[$model], true);
    }

    /**
     * Tạo cache key kèm payload + scope version.
     */
    public static function getKey($userId, $model, $method, $params = [])
    {
        $model = strtolower($model);
        $p = is_array($params) ? $params : [];
        ksort($p);
        $payloadHash = md5(serialize($p));
        $ver = self::getScopeVersion($model);
        $key = $userId . '_' . $model . '_' . $method . '_v' . $ver . '_' . $payloadHash;
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
    }

    private static function redisBodyKey($cacheKey)
    {
        self::ensureRedisClientLoaded();
        return RedisClient::prefix() . 'b:' . $cacheKey;
    }

    private static function redisIndexKey($userId)
    {
        self::ensureRedisClientLoaded();
        $uid = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) $userId);
        return RedisClient::prefix() . 'idx:' . $uid;
    }

    /**
     * Đọc cache. Trả về nội dung (string) hoặc false nếu hết hạn/không có.
     */
    public static function get($key, $userId)
    {
        self::ensureRedisClientLoaded();
        if (class_exists('RedisClient', false)) {
            $redis = RedisClient::get();
            if ($redis) {
                try {
                    $body = $redis->get(self::redisBodyKey($key));
                    if ($body !== null && $body !== false && $body !== '') {
                        return $body;
                    }
                } catch (\Throwable $e) {
                    // fall through to file
                }
            }
        }

        $dir = self::getCacheDir();
        $userDir = $dir . 'u' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $userId) . '/';
        $file = $userDir . $key . '.cache';
        if (!is_file($file)) {
            return false;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return false;
        }
        $data = @json_decode($raw, true);
        if (!is_array($data) || !isset($data['expiry'], $data['body'])) {
            @unlink($file);
            return false;
        }
        if (time() > (int) $data['expiry']) {
            @unlink($file);
            return false;
        }
        return $data['body'];
    }

    /**
     * Ghi cache. $body là chuỗi JSON response, $ttl là số giây.
     */
    public static function set($key, $userId, $body, $ttl = self::DEFAULT_TTL)
    {
        $ttl = max(1, (int) $ttl);
        $okRedis = false;

        self::ensureRedisClientLoaded();
        if (class_exists('RedisClient', false)) {
            $redis = RedisClient::get();
            if ($redis) {
                try {
                    $pipe = $redis->pipeline();
                    $pipe->setex(self::redisBodyKey($key), $ttl, $body);
                    $pipe->sadd(self::redisIndexKey($userId), $key);
                    $pipe->execute();
                    $okRedis = true;
                } catch (\Throwable $e) {
                    $okRedis = false;
                }
            }
        }

        $dir = self::getCacheDir();
        $userDir = $dir . 'u' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $userId) . '/';
        if (!is_dir($userDir)) {
            @mkdir($userDir, 0755, true);
        }
        $file = $userDir . $key . '.cache';
        $data = [
            'expiry' => time() + $ttl,
            'body'   => $body,
        ];
        $okFile = @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE)) !== false;
        return $okRedis || $okFile;
    }

    /**
     * Xóa toàn bộ cache của một user (khi đăng xuất hoặc khi cần invalidate).
     */
    public static function invalidateUser($userId)
    {
        self::ensureRedisClientLoaded();
        if (class_exists('RedisClient', false)) {
            $redis = RedisClient::get();
            if ($redis) {
                try {
                    $idx = self::redisIndexKey($userId);
                    $members = $redis->smembers($idx);
                    if (is_array($members) && count($members) > 0) {
                        $pipe = $redis->pipeline();
                        foreach ($members as $m) {
                            $pipe->del(self::redisBodyKey($m));
                        }
                        $pipe->del($idx);
                        $pipe->execute();
                    } else {
                        $redis->del($idx);
                    }
                } catch (\Throwable $e) {
                    // continue file cleanup
                }
            }
        }

        $dir = self::getCacheDir();
        $userDir = $dir . 'u' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $userId) . '/';
        if (!is_dir($userDir)) {
            return true;
        }
        $files = glob($userDir . '*.cache');
        if (is_array($files)) {
            foreach ($files as $f) {
                @unlink($f);
            }
        }
        @rmdir($userDir);
        return true;
    }

    /**
     * Xóa cache API của nhiều user (userid string).
     */
    public static function invalidateUsers(array $userIds)
    {
        foreach (array_unique(array_filter(array_map('strval', $userIds), function ($uid) {
            return $uid !== '';
        })) as $userId) {
            self::invalidateUser($userId);
        }
        return true;
    }
}
