<?php

$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Bỏ qua dòng comment
        putenv(trim($line));
    }
}
if (function_exists( 'date_default_timezone_set' ) )
{
	date_default_timezone_set('Asia/Tokyo');
}
require(dirname(__FILE__).'/controller.php');
$controller = new Controller;
$hash = $controller->dispatch();
$view = new ApplicationView($hash);
$helper = new Helper;
?>