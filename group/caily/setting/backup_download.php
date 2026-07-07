<?php
require_once('../application/loader.php');
require_once(dirname(__DIR__) . '/application/model/backup.php');

$backup = new Backup();
$backup->streamDownload();
