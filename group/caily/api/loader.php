<?php
if (function_exists( 'date_default_timezone_set' ) )
{
	date_default_timezone_set('Asia/Saigon');
}
require('../application/controller.php');
$controller = new Controller;
?>