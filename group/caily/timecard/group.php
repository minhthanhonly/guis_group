<?php

require_once('../application/loader.php');
$view->heading('タイムカード');
$calendar = new Calendar;
$timestamp = mktime(0, 0, 0, $_GET['month']-1, 21, $_GET['year']);
$lastday = date('t', $timestamp);
$weekday = date('w', $timestamp);
?>
<h1>タイムカード - グループ</h1>
<ul class="operate">
	<li><a href="../administration.php">一覧に戻る</a></li>
	<li><a href="index.php<?=$view->positive(array('year'=>$_GET['year'], 'month'=>$_GET['month']))?>">タイムカードに戻る</a></li>
</ul>
<table class="timecard" cellspacing="0" style="width:460px;">
	<tr><td colspan="5" class="timecardcaption">
		<select name="year" onchange="Timecard.redirect(this,'group')"><?=$helper->option(2000, 2030, $_GET['year'])?></select>年&nbsp;
		<select name="month" onchange="Timecard.redirect(this,'group')"><?=$helper->option(1, 12, $_GET['month'])?></select>月&nbsp;&nbsp;
		<?=$helper->selector('group', $hash['group'], $_GET['group'], ' onchange="Timecard.redirect(this,\'group\')"')?>
	</td></tr>
	<tr><th>名前</th><th>勤務日数</th><th>勤務時間合計</th><th>時間外合計</th><th>休日出勤</th></tr>
<?php
if (is_array($hash['list']) && count($hash['list']) > 0) {
	foreach ($hash['list'] as $row) {
		$timestamp = mktime(0, 0, 0, $row['timecard_month'], $row['timecard_day'], $row['timecard_year']);
		$lastday = date('t', $timestamp);
		$weekday = date('w', $timestamp);
		if ($row['timecard_open'] && $row['timecard_close'] && $row['timecard_time'] && !$calendar->checkHoliday($row['timecard_year'], $row['timecard_month'], $row['timecard_day'], $weekday, $lastday)) {
			$array = explode(':', $row['timecard_time']);
			$data[$row['owner']]['sum'][$row['timecard_day']] = intval($array[0]) * 60 + intval($array[1]);
			$array = explode(':', $row['timecard_timeover']);
			$data[$row['owner']]['intervalsum'][$row['timecard_day']] = intval($array[0]) * 60 + intval($array[1]);
		} else{
			$array = explode(':', $row['timecard_time']);
			$data[$row['owner']]['sum2'][$row['timecard_day']] = intval($array[0]) * 60 + intval($array[1]);
		}
	}
}
if (is_array($hash['user']) && count($hash['user']) > 0) {
	foreach ($hash['user'] as $key => $value) {
		$day = count($data[$key]['sum']);
		if (is_array($data[$key]['sum'])) {
			$sum = array_sum($data[$key]['sum']);
		} else {
			$sum = 0;
		}
		$sum = sprintf('%d:%02d', (($sum - ($sum % 60)) / 60), ($sum % 60));
		if (is_array($data[$key]['intervalsum'])) {
			$intervalsum = array_sum($data[$key]['intervalsum']);
		} else {
			$intervalsum = 0;
		}
		$intervalsum = sprintf('%d:%02d', (($intervalsum - ($intervalsum % 60)) / 60), ($intervalsum % 60));
		if (is_array($data[$key]['sum2'])) {
			$sum2 = array_sum($data[$key]['sum2']);
		} else {
			$sum2 = 0;
		}
		$sum2 = sprintf('%d:%02d', (($sum2 - ($sum2 % 60)) / 60), ($sum2 % 60));
?>
	<tr><td><a href="index.php?year=<?=$_GET['year']?>&month=<?=$_GET['month']?>&member=<?=$key?>"><?=$value?></a>&nbsp;</td>
	<td><?=$day?>&nbsp;</td>
	<td><?=$sum?>&nbsp;</td>
	<td><?=$intervalsum?>&nbsp;</td>
	<td><?=$sum2?>&nbsp;</td></tr>
<?php
	}
}
echo '</table>';
$view->footing();
?>