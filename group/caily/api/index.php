<?php
header('Content-Type: application/json');
require_once('loader.php');

try {
    $controller->initApi();

    $model  = isset($_GET['model']) ? $_GET['model'] : '';
    $method = isset($_GET['method']) ? $_GET['method'] : '';
    $params = $_GET;

    require_once dirname(__DIR__) . '/application/library/ApiCache.php';

    $userId = isset($_SESSION['userid']) ? $_SESSION['userid'] : (isset($_SESSION['id']) ? (string) $_SESSION['id'] : '');
    $isGet  = ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET';

    if ($isGet && $userId !== '' && ApiCache::isCacheable($model, $method)) {
        $cacheKey = ApiCache::getKey($userId, $model, $method, $params);
        $cached   = ApiCache::get($cacheKey, $userId);
        if ($cached !== false) {
            echo $cached;
            exit;
        }
    }

    $response = $controller->api($model, $method, $params);

    if ($isGet && $userId !== '' && ApiCache::isCacheable($model, $method)) {
        $cacheKey = ApiCache::getKey($userId, $model, $method, $params);
        ApiCache::set($cacheKey, $userId, $response, ApiCache::DEFAULT_TTL);
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
