<?php
require_once('../loader.php');
include 'general.php';
    $general = new General;
	if (isset($_GET['type'])) {
		$status_timecard =  $_GET['dev'];
		$call = $general->updateTemp($status_timecard);
	}
?>