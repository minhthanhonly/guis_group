<?php
require_once('loader.php');
header('Content-Type: application/json');

try {
    $controller->initApi();

    $model  = isset($_GET['model']) ? $_GET['model'] : '';
    $method = isset($_GET['method']) ? $_GET['method'] : '';
    $params = $_GET;

    require_once dirname(__DIR__) . '/application/library/ApiCache.php';

    $userId = isset($_SESSION['userid']) ? $_SESSION['userid'] : (isset($_SESSION['id']) ? (string) $_SESSION['id'] : '');
    $numericUserId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
    $isGet  = ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET';

    if ($isGet && $userId !== '' && ApiCache::isCacheable($model, $method)) {
        $owner    = ApiCache::cacheOwnerId($userId, $model, $method, $params);
        $cacheKey = ApiCache::getKey($userId, $model, $method, $params);
        $cached   = ApiCache::get($cacheKey, $owner);
        if ($cached !== false) {
            if (ApiCache::cacheScope($model, $method, $params) === ApiCache::SCOPE_PERMISSION) {
                $cached = ApiCache::applyUserFavorites($model, $cached, $numericUserId);
            }
            echo $cached;
            exit;
        }
    }

    $response = $controller->api($model, $method, $params);

    if ($isGet && $userId !== '' && ApiCache::isCacheable($model, $method)) {
        if (ApiCache::isSuccessfulResponse($response)) {
            $owner    = ApiCache::cacheOwnerId($userId, $model, $method, $params);
            $cacheKey = ApiCache::getKey($userId, $model, $method, $params);
            $toStore  = $response;
            if (ApiCache::cacheScope($model, $method, $params) === ApiCache::SCOPE_PERMISSION) {
                $toStore = ApiCache::stripUserFavorites($response);
            }
            ApiCache::set($cacheKey, $owner, $toStore, ApiCache::DEFAULT_TTL);
        }
    } elseif (
        ApiCache::shouldBumpAfterRequest($isGet, $model, $method)
        && ApiCache::isSuccessfulResponse($response)
    ) {
        // Non-GET success, hoặc GET write (denylist) → bump model + related scopes
        ApiCache::bumpOnWrite($model);
    }

    echo $response;
} catch (DatabaseException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'データベースエラーが発生しました。']);
} catch (Throwable $e) {
    error_log('API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'サーバーエラーが発生しました。']);
}
?>
