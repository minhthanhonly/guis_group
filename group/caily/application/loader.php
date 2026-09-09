<?php

if (function_exists( 'date_default_timezone_set' ) )
{
	date_default_timezone_set('Asia/Tokyo');
}
require_once dirname(__FILE__) . '/output_compression.php';
require(dirname(__FILE__).'/controller.php');

$controller = new Controller;
$hash = $controller->dispatch();
$helper = new Helper;
$view = new ApplicationView($hash);
?>