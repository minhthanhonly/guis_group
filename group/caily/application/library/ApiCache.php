<?php
/**
 * Cache phản hồi API theo từng user (chỉ GET, whitelist model+method).
 * Key: userid_model_method_[hash(full_payload)] — payload = toàn bộ GET params.
 * Lưu file: application/cache/api/{userid}/{key}.cache
 */
class ApiCache {

    /** Thư mục cache (application/cache/api/) */
    private static $cacheDir;

    /** TTL mặc định (giây), 5 phút */
    const DEFAULT_TTL = 3600;

    /**
     * Danh sách endpoint được phép cache: [ 'model' => [ 'method1', 'method2' ] ]
     */
    private static $cacheable = [
        'department' => [ 'listByUser', 'list', 'list_department', 'get', 'get_users', 'get_user_permission_by_department', 'get_user_permissions', 'getAll' ],
        'user' => [ 'searchMembers', 'getMentionUsers'],
        'customer' => [ 'list_categories'],
        // Thêm model/method khác nếu cần
    ];

    private static function getCacheDir() {
        if (self::$cacheDir === null) {
            $base = defined('DIR_PATH') ? DIR_PATH : dirname(dirname(__FILE__)) . '/';
            self::$cacheDir = rtrim($base, '/') . '/cache/api/';
            if (!is_dir(self::$cacheDir)) {
                @mkdir(self::$cacheDir, 0755, true);
            }
        }
        return self::$cacheDir;
    }

    /**
     * Kiểm tra request GET có được cache không.
     */
    public static function isCacheable($model, $method) {
        $model = strtolower($model);
        if (!isset(self::$cacheable[$model])) {
            return false;
        }
        return in_array($method, self::$cacheable[$model], true);
    }

    /**
     * Tạo cache key kèm payload: userid_model_method_[hash(full_payload)]
     * Payload = toàn bộ params (GET), sort để key ổn định.
     */
    public static function getKey($userId, $model, $method, $params = []) {
        $model = strtolower($model);
        $p = is_array($params) ? $params : [];
        ksort($p);
        $payloadHash = md5(serialize($p));
        $key = $userId . '_' . $model . '_' . $method . '_' . $payloadHash;
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
    }

    /**
     * Đọc cache. Trả về nội dung (string) hoặc false nếu hết hạn/không có.
     */
    public static function get($key, $userId) {
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
    public static function set($key, $userId, $body, $ttl = self::DEFAULT_TTL) {
        $dir = self::getCacheDir();
        $userDir = $dir . 'u' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $userId) . '/';
        if (!is_dir($userDir)) {
            @mkdir($userDir, 0755, true);
        }
        $file = $userDir . $key . '.cache';
        $data = [
            'expiry' => time() + (int) $ttl,
            'body'   => $body,
        ];
        return @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE)) !== false;
    }

    /**
     * Xóa toàn bộ cache của một user (khi đăng xuất hoặc khi cần invalidate).
     */
    public static function invalidateUser($userId) {
        $dir = self::getCacheDir();
        $userDir = $dir . 'u' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $userId) . '/';
        if (!is_dir($userDir)) {
            return true;
        }
        $files = glob($userDir . '*.cache');
        foreach ($files as $f) {
            @unlink($f);
        }
        @rmdir($userDir);
        return true;
    }
}
