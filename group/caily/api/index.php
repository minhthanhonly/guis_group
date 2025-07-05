<?php
header('Content-Type: application/json');
require_once('loader.php');
$controller->initApi();

if ($model == 'task') {
    if ($method == 'getLogs') {
        $result = $modelInstance->getLogs();
        echo json_encode($result);
        exit;
    }
}

echo $controller->api($_GET['model'], $_GET['method'], $_GET);
?>