<?php
header('Content-Type: application/json');
require_once('loader.php');
$controller->initApi();

echo $controller->api($_GET['model'], $_GET['method']);
?>