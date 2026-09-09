<?php
if (function_exists( 'date_default_timezone_set' ) )
{
	date_default_timezone_set('Asia/Tokyo');
}
require_once dirname(__DIR__) . '/application/output_compression.php';
require('../application/controller.php');
$controller = new Controller;
?>