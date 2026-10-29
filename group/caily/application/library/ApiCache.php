<?php
/**
 * Cache phản hồi API (GET đọc; denylist method ghi).
 *
 * Ba lớp owner:
 * - shared     (_shared_): master data giống nhau mọi user (department list, category…)
 * - permission (p{fp}):    user cùng phân quyền department/authority share với nhau
 *                          (project / parentproject=building list…). Favorite được
 *                          strip khi ghi cache và patch lại theo user khi đọc.
 * - user       (u{userid}): dữ liệu gắn identity (task permission/membership,
 *                          permitWhere forum/storage, notification…)
 *
 * Freshness: bumpScopeVersion(model) khi write → key cũ tự miss.
 */
class ApiCache {

    const OWNER_SHARED = '_shared_';
    const SCOPE_SHARED = 'shared';
    const SCOPE_PERMISSION = 'permission';
    const SCOPE_USER = 'user';

    private static $cacheDir;
    private static $permissionFingerprint = null;

    const DEFAULT_TTL = 900;

    private static $uncacheableMethodPrefixes = [
        'add', 'edit', 'delete', 'save', 'update', 'create', 'remove', 'insert',
        'upload', 'import', 'export', 'send', 'suspend', 'resign', 'active',
        'change_', 'invalidate', 'login', 'logout', 'force', 'bulk', 'hard',
        'copy', 'move', 'assign', 'unassign', 'toggle', 'reset', 'clear', 'sync',
        'run', 'execute', 'process', 'generate', 'rebuild', 'migrate', 'fix',
        'set_', 'post', 'put', 'patch', 'destroy', 'restore', 'approve', 'reject',
        'cancel', 'confirm', 'submit', 'attach', 'detach', 'reorder', 'sort_save',
    ];

    /**
     * Share theo fingerprint phân quyền (department flags + authority).
     * Không gồm task: getPermission phụ thuộc project_members của từng user.
     */
    private static $permissionScopedModels = [
        'project',
        'parentproject',
    ];

    /**
     * Luôn theo từng user (identity / membership / permitWhere).
     */
    private static $userScopedModels = [
        'task', 'drawing', 'projectnote', 'parentprojectnote',
        'forum', 'storage', 'schedule', 'facility',
        'ai', 'notification', 'message', 'chat', 'mail', 'authority',
        'online', 'presence', 'timecard', 'request', 'leave',
        'bookmark', 'todo', 'addressbook', 'workflow',
    ];

    /** Method → luôn user-scope (kể cả model permission-scoped). */
    private static $userScopedMethodNeedles = [
        'permission', 'permit', 'mention', 'notification', 'unread', 'profile',
        'personal', 'private', 'session', 'auth', 'password', 'token', 'remember',
        'mine', 'myself', 'self', 'assigned', 'favorite', 'bookmark', 'inbox',
        'current_user', 'me_', '_me', 'my_',
        'searchmembers', 'getmention', 'get_user_permission', 'user_permission',
        'canuser', 'can_view', 'can_edit', 'canview', 'canedit',
        'foraicontext',
    ];

    /** Cột quyền trên user_department (khớp Department::departmentPermissionFields). */
    private static $departmentPermissionFields = [
        'project_manager',
        'project_director_stat',
        'project_director_view',
        'project_director_edit',
        'project_add',
        'project_edit',
        'project_delete',
        'project_comment',
        'project_view_end_date',
        'task_view',
        'task_add',
        'task_edit',
        'task_delete',
    ];

    private static $modelScopes = [
        'customer' => 'customer_category',
    ];

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

    private static function ownerDir($ownerId)
    {
        $dir = self::getCacheDir();
        $ownerId = (string) $ownerId;
        if ($ownerId === self::OWNER_SHARED || $ownerId === '') {
            $sub = $dir . 'shared/';
        } elseif (strpos($ownerId, 'p') === 0 && preg_match('/^p[a-f0-9]+$/', $ownerId)) {
            $sub = $dir . $ownerId . '/';
        } else {
            $sub = $dir . 'u' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $ownerId) . '/';
        }
        if (!is_dir($sub)) {
            @mkdir($sub, 0755, true);
        }
        return $sub;
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
     * Request có filter gắn identity → bắt buộc user-scope.
     */
    private static function requestForcesUserScope($params = null)
    {
        $g = is_array($params) ? $params : (isset($_GET) && is_array($_GET) ? $_GET : []);
        if (isset($g['my_projects']) && (string) $g['my_projects'] === '1') {
            return true;
        }
        if (isset($g['favorites_only']) && (string) $g['favorites_only'] === '1') {
            return true;
        }
        return false;
    }

    /**
     * @return string shared|permission|user
     */
    public static function cacheScope($model, $method, $params = null)
    {
        $model = strtolower(trim((string) $model));
        $method = strtolower(trim((string) $method));
        if ($model === '' || $method === '') {
            return self::SCOPE_USER;
        }
        if (self::requestForcesUserScope($params)) {
            return self::SCOPE_USER;
        }
        foreach (self::$userScopedMethodNeedles as $needle) {
            if ($needle !== '' && strpos($method, $needle) !== false) {
                return self::SCOPE_USER;
            }
        }
        if (in_array($model, self::$userScopedModels, true)) {
            return self::SCOPE_USER;
        }
        if (in_array($model, self::$permissionScopedModels, true)) {
            return self::SCOPE_PERMISSION;
        }
        return self::SCOPE_SHARED;
    }

    /** @deprecated dùng cacheScope(); giữ để smoke/compat */
    public static function isUserScoped($model, $method)
    {
        return self::cacheScope($model, $method) === self::SCOPE_USER;
    }

    /**
     * Fingerprint phân quyền: authority + group + từng department flags.
     * User cùng fingerprint → cùng cohort cache.
     */
    public static function permissionFingerprint()
    {
        if (self::$permissionFingerprint !== null) {
            return self::$permissionFingerprint;
        }

        $payload = [
            'authority' => isset($_SESSION['authority']) ? (string) $_SESSION['authority'] : '',
            'group' => isset($_SESSION['group']) ? (string) $_SESSION['group'] : '',
            'depts' => [],
        ];

        $userid = isset($_SESSION['userid']) ? (string) $_SESSION['userid'] : '';
        if ($userid !== '' && defined('DB_PREFIX') && class_exists('Connection', false)) {
            try {
                $conn = new Connection();
                $fields = self::$departmentPermissionFields;
                // project_director legacy column cũng ảnh hưởng director checks
                $cols = array_merge(['department_id', 'project_director'], $fields);
                $cols = array_unique($cols);
                $sql = sprintf(
                    "SELECT %s FROM %suser_department WHERE userid = '%s' ORDER BY department_id ASC",
                    implode(',', $cols),
                    DB_PREFIX,
                    self::sqlEscape($conn, $userid)
                );
                $rows = $conn->fetchAll($sql);
                $conn->close();
                foreach ($rows as $row) {
                    $entry = ['department_id' => (int) ($row['department_id'] ?? 0)];
                    $entry['project_director'] = (int) ($row['project_director'] ?? 0);
                    foreach ($fields as $f) {
                        $entry[$f] = (int) ($row[$f] ?? 0);
                    }
                    $payload['depts'][] = $entry;
                }
            } catch (\Throwable $e) {
                // Fallback: cohort hẹp hơn = userid (an toàn, ít share)
                $payload['fallback_userid'] = $userid;
            }
        } elseif ($userid !== '') {
            $payload['fallback_userid'] = $userid;
        }

        self::$permissionFingerprint = substr(sha1(json_encode($payload)), 0, 16);
        return self::$permissionFingerprint;
    }

    private static function sqlEscape($conn, $value)
    {
        if (isset($conn->handler) && $conn->handler) {
            return mysqli_real_escape_string($conn->handler, $value);
        }
        return addslashes($value);
    }

    /**
     * Owner id: _shared_ | p{fingerprint} | {userid}
     */
    public static function cacheOwnerId($userId, $model, $method, $params = null)
    {
        $scope = self::cacheScope($model, $method, $params);
        if ($scope === self::SCOPE_SHARED) {
            return self::OWNER_SHARED;
        }
        if ($scope === self::SCOPE_PERMISSION) {
            return 'p' . self::permissionFingerprint();
        }
        return (string) $userId;
    }

    public static function getScopeVersion($modelOrScope)
    {
        $scope = self::resolveScope($modelOrScope);
        if ($scope === '') {
            return '0';
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

    public static function bumpScopeVersion($modelOrScope)
    {
        $scope = self::resolveScope($modelOrScope);
        if ($scope === '') {
            return true;
        }
        $file = self::versionDir() . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $scope) . '.ver';
        $cur = 1;
        if (is_file($file)) {
            $cur = max(1, intval(trim((string) @file_get_contents($file))));
        }
        @file_put_contents($file, (string) ($cur + 1));
        return true;
    }

    public static function isCacheable($model, $method)
    {
        $model = trim((string) $model);
        $method = trim((string) $method);
        if ($model === '' || $method === '') {
            return false;
        }
        $methodLower = strtolower($method);
        foreach (self::$uncacheableMethodPrefixes as $prefix) {
            $prefix = strtolower($prefix);
            if ($methodLower === $prefix || strpos($methodLower, $prefix) === 0) {
                return false;
            }
        }
        return true;
    }

    public static function isSuccessfulResponse($body)
    {
        if (!is_string($body) || $body === '') {
            return false;
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return true;
        }
        if (isset($data['error']) && $data['error'] !== '' && $data['error'] !== null) {
            if (!isset($data['status']) || $data['status'] !== 'success') {
                return false;
            }
        }
        if (isset($data['status']) && is_string($data['status'])) {
            $st = strtolower($data['status']);
            if (in_array($st, ['error', 'fail', 'failed'], true)) {
                return false;
            }
        }
        return true;
    }

    public static function getKey($userId, $model, $method, $params = [])
    {
        $model = strtolower($model);
        $owner = self::cacheOwnerId($userId, $model, $method, $params);
        $p = is_array($params) ? $params : [];
        unset($p['PHPSESSID'], $p['_'], $p['nocache']);
        ksort($p);
        $payloadHash = md5(serialize($p));
        $ver = self::getScopeVersion($model);
        $key = $owner . '_' . $model . '_' . $method . '_v' . $ver . '_' . $payloadHash;
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
    }

    public static function get($key, $ownerId)
    {
        $file = self::ownerDir($ownerId) . $key . '.cache';
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

    public static function set($key, $ownerId, $body, $ttl = self::DEFAULT_TTL)
    {
        $dir = self::ownerDir($ownerId);
        $file = $dir . $key . '.cache';
        $data = [
            'expiry' => time() + max(1, (int) $ttl),
            'body'   => $body,
        ];
        return @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE)) !== false;
    }

    /**
     * Trước khi lưu cache permission-scope: xóa is_favorite (gắn user).
     */
    public static function stripUserFavorites($body)
    {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return $body;
        }
        self::walkSetFavorite($data, 0);
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Sau cache hit permission-scope: gắn lại is_favorite của user hiện tại.
     * $numericUserId = $_SESSION['id']
     */
    public static function applyUserFavorites($model, $body, $numericUserId)
    {
        $numericUserId = intval($numericUserId);
        if ($numericUserId <= 0 || !is_string($body) || $body === '') {
            return $body;
        }
        $model = strtolower($model);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return $body;
        }

        $ids = self::collectEntityIds($data);
        if (empty($ids)) {
            return $body;
        }

        $favSet = self::loadFavoriteIdSet($model, $numericUserId, $ids);
        self::walkPatchFavorite($data, $favSet);
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private static function collectEntityIds(array $node)
    {
        $ids = [];
        if (isset($node['data']) && is_array($node['data'])) {
            foreach ($node['data'] as $row) {
                if (is_array($row) && isset($row['id'])) {
                    $ids[] = (int) $row['id'];
                }
            }
        } elseif (isset($node['id']) && (isset($node['is_favorite']) || array_key_exists('is_favorite', $node))) {
            $ids[] = (int) $node['id'];
        }
        return array_values(array_unique(array_filter($ids)));
    }

    private static function loadFavoriteIdSet($model, $numericUserId, array $ids)
    {
        $favSet = [];
        if (!defined('DB_PREFIX') || !class_exists('Connection', false) || empty($ids)) {
            return $favSet;
        }
        try {
            $conn = new Connection();
            $idList = implode(',', array_map('intval', $ids));
            if ($model === 'parentproject') {
                $sql = sprintf(
                    "SELECT parent_project_id AS id FROM %sparent_project_favorites WHERE user_id = %d AND parent_project_id IN (%s)",
                    DB_PREFIX,
                    $numericUserId,
                    $idList
                );
            } else {
                $sql = sprintf(
                    "SELECT project_id AS id FROM %sproject_favorites WHERE user_id = %d AND project_id IN (%s)",
                    DB_PREFIX,
                    $numericUserId,
                    $idList
                );
            }
            $rows = $conn->fetchAll($sql);
            $conn->close();
            foreach ($rows as $row) {
                $favSet[(int) $row['id']] = true;
            }
        } catch (\Throwable $e) {
            // keep empty → is_favorite = 0
        }
        return $favSet;
    }

    private static function walkSetFavorite(array &$node, $value)
    {
        if (array_key_exists('is_favorite', $node)) {
            $node['is_favorite'] = $value;
        }
        foreach ($node as &$child) {
            if (is_array($child)) {
                self::walkSetFavorite($child, $value);
            }
        }
        unset($child);
    }

    private static function walkPatchFavorite(array &$node, array $favSet)
    {
        if (isset($node['id']) && array_key_exists('is_favorite', $node)) {
            $node['is_favorite'] = isset($favSet[(int) $node['id']]) ? 1 : 0;
        }
        foreach ($node as &$child) {
            if (is_array($child)) {
                self::walkPatchFavorite($child, $favSet);
            }
        }
        unset($child);
    }

    public static function invalidateUser($userId)
    {
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
