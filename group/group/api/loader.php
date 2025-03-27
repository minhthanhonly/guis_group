<?php
if (function_exists( 'date_default_timezone_set' ) )
{
	date_default_timezone_set('Asia/Tokyo');
}
require('../application/controller.php');
$controller = new Controller;
?>