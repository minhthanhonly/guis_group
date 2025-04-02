<?php
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