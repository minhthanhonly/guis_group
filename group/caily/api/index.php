<?php
header('Content-Type: application/json');
require_once('loader.php');
$controller->initApi();

$model  = isset($_GET['model']) ? $_GET['model'] : '';
$method = isset($_GET['method']) ? $_GET['method'] : '';
$params = $_GET;

// require_once dirname(__DIR__) . '/application/library/ApiCache.php';

// $userId = isset($_SESSION['userid']) ? $_SESSION['userid'] : (isset($_SESSION['id']) ? (string) $_SESSION['id'] : '');
// $isGet  = ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET';

// if ($isGet && $userId !== '' && ApiCache::isCacheable($model, $method)) {
//     $cacheKey = ApiCache::getKey($userId, $model, $method, $params);
//     $cached   = ApiCache::get($cacheKey, $userId);
//     if ($cached !== false) {
//         echo $cached;
//         exit;
//     }
// }

$response = $controller->api($model, $method, $params);

// if ($isGet && $userId !== '' && ApiCache::isCacheable($model, $method)) {
//     $cacheKey = ApiCache::getKey($userId, $model, $method, $params);
//     ApiCache::set($cacheKey, $userId, $response, ApiCache::DEFAULT_TTL);
// }

echo $response;
?>