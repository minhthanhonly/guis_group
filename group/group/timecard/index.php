<?php
/*
 * Copyright(c) 2009 limitlink,Inc. All Rights Reserved.
 * http://limitlink.jp/
 * 文字コード UTF-8
 */
require_once('../application/loader.php');
$view->heading('タイムカード');
$calendar = new Calendar;

if (count($hash['list']) <= 0) {
	$attribute = ' onclick="alert(\'出力するデータがありません。\');return false;"';
}
if (strlen($hash['owner']['realname']) > 0 && (isset($_GET['member']) || $hash['owner']['userid'] != $_SESSION['userid'])) {
	$caption = ' - '.$hash['owner']['realname'];
}
?>
<h1>タイムカード<?=$caption?> - <?=$hash['config']['config_name']?></h1>
<ul class="operate">
	<li><a href="csv.php<?=$view->positive(array('year'=>$_GET['year'], 'month'=>$_GET['month']))?>"<?=$attribute?>>CSV出力</a></li>
<?php
if ($hash['owner']['userid'] == $_SESSION['userid']) {
	echo '<li><a href="index.php'.$view->positive(array('year'=>$_GET['year'], 'month'=>$_GET['month'], 'recalculate'=>1)).'">再計算</a></li>';
}
if ($view->authorize('administrator', 'manager')) {
	echo '<li><a href="../administration.php">管理</a></li>';
}
/*月判定(先月21日～当月20日まで当月）*/
/*本日取得*/
$today = array('year'=>date('Y'), 'month'=>date('n'), 'day'=>date('j'));

$monthcurrent = $_GET['month'];
if ($monthcurrent > 12) {
	$_GET['year'] = $_GET['year'] + 1;
	$_GET['month'] = 1;
} else {
	$_GET['year'] = $_GET['year'];
	$_GET['month'] = $_GET['month'];
}

?>

</ul>
<form class="content" method="post" action="">
	<?=$view->error($hash['error'])?>
	<table class="timecard" cellspacing="0">
		<tr><td colspan="9" class="timecardcaption">
			<select name="year" data-member="<?php echo $hash['owner']['userid'];?>" data-member-current="<?php echo $_SESSION['authority'];?>" onchange="Timecard.redirect(this)"><?=$helper->option(2000, 2030, $_GET['year'])?></select>年&nbsp;
			<select name="month" data-member="<?php echo $hash['owner']['userid'];?>" data-member-current="<?php echo $_SESSION['authority'];?>" onchange="Timecard.redirect(this)"><?=$helper->option(1, 12, $_GET['month'])?></select>月
		</td></tr>
		<tr><th>日付</th><th>出社</th><th>退社</th><th>勤務時間</th><th>時間外</th><th>休憩時間</th><th>休日出勤</th><th>備考</th><th>&nbsp;</th></tr>
<?php
/*先月の21日の曜日と最終日を取得する*/
$timestamp = mktime(0, 0, 0, $_GET['month']-1, 21, $_GET['year']);
$lastday = date('t', $timestamp);
$weekday = date('w', $timestamp);
/*曜日表記セット*/
$week = array('日', '月', '火', '水', '木', '金', '土');

$num = 0;
/*DBデータ取得*/
if (is_array($hash['list']) && count($hash['list']) > 0) {
	foreach ($hash['list'] as $row) {
		/*21日*/
		if($row['timecard_day'] > 20){
			$num = $row['timecard_day'] - 20;
		}else{
			$num = $row['timecard_day'] + ($lastday - 20);
		}
		$data[$num] = $row;
	}
}

$sum = 0;
$over_sum=0;
$dt =0;

$syr = $yr = $_GET['year'];
$mt = $_GET['month'];
$smt = $mt - 1;

/*1月(年越し)対応*/
if(strcmp($mt,'1') == 0){
	$smt = $mt + 11;
	$syr = $yr - 1;
}

for ($i = 1; $i <= $lastday; $i++) {
	/*21日始まり対応*/
	if($i <= ($lastday - 20)){
		$dt = $i+20;
	}else{
		$dt = $i - ($lastday - 20);
		if($dt == 1){
			$syr = $yr;
			$smt = $mt;
		}
	}

	/*曜日・本日クラスセット*/
	$class = $calendar->style($syr, $smt, $dt, $weekday, $lastday);

	$type = array('open', 'close', 'interval');
	foreach ($type as $value) {
		$key = 'timecard_'.$value;
		$original = 'timecard_original'.$value;

		if ($data[$i][$key] != $data[$i][$original]){
			if (strlen($data[$i][$key]) > 0) {
				$data[$i][$key] = '<span class="timecardupdated">'.$data[$i][$key].'</span>';
			}

			if ($_GET['original'] == 1) {
				if ($data[$i][$original]) {
					$data[$i][$key] = $data[$i][$original].'<br />'.$data[$i][$key];
				} else {
					$data[$i][$key] = '-<br />'.$data[$i][$key];
				}
			}
		}
	}

	if ($smt == $today['month'] && $dt == $today['day'] && $syr == $today['year'] && $hash['owner']['userid'] == $_SESSION['userid']) {

		if (!$data[$i] && !$data[$i]['timecard_open']) {
			$data[$i]['timecard_open'] = '<input type="submit" name="timecard_open" value="出社" />';
		} elseif (!$data[$i]['timecard_close']) {
			$data[$i]['timecard_close'] = '<input type="submit" name="timecard_close" value="退社" />';
			/*
			if (strlen($data[$i]['timecard_interval']) <= 0 || preg_match('/.*-[0-9]+:[0-9]+$/', $data[$i]['timecard_interval'])) {
				$data[$i]['timecard_interval'] .= '&nbsp;<input type="submit" name="timecard_interval" value="外出" />';
			} else {
				$data[$i]['timecard_interval'] .= '&nbsp;<input type="submit" name="timecard_interval" value="復帰" />';
			}*/
		}
	}
	if (strlen($data[$i]['timecard_time']) > 0 && !$calendar->checkHoliday($syr, $smt, $dt, $weekday, $lastday)) {
		$array = explode(':', $data[$i]['timecard_time']);
		$sum += intval($array[0]) * 60 + intval($array[1]);
	}
	if (strlen($data[$i]['timecard_time']) > 0 && $calendar->checkHoliday($syr, $smt, $dt, $weekday, $lastday)) {
		$array = explode(':', $data[$i]['timecard_time']);
		$sumholiday += intval($array[0]) * 60 + intval($array[1]);
	}
	if (strlen($data[$i]['timecard_timeover']) > 0 && !$calendar->checkHoliday($syr, $smt, $dt, $weekday, $lastday)) {
		$array = explode(':', $data[$i]['timecard_timeover']);
		$over_sum += intval($array[0]) * 60 + intval($array[1]);
	}
	/*---- timecard for holidays ---*/


	$day_array = explode(':', $data[$i]['timecard_time']);

	$dayminute = intval($day_array[0]) * 60 + intval($day_array[1]);

	/**---close timecard holidays---*/
	if ($_SESSION['authority'] != 'member') {
		$edit = '<a href="edit.php?year='.$syr.'&month='.$smt.'&day='.$dt.'&member='.$hash['owner']['userid'].'">編集</a>';
	} else {
		$edit = '<span class="unlink">編集</span>';
	}
?>
	<tr<?=$class?>>
	<td><?=$smt?>/<?=$dt?> (<?=$week[$weekday]?>)</td>
	<td><?=$data[$i]['timecard_open']?>&nbsp;</td>
	<td><?=$data[$i]['timecard_close']?>&nbsp;</td>
<!--<td><?=$data[$i]['timecard_interval']?>&nbsp;</td>-->
	<td><?php if(!$calendar->checkHoliday($syr, $smt, $dt, $weekday, $lastday))
	 echo $data[$i]['timecard_time']; ?>&nbsp;</td>
	<td><?php if(!$calendar->checkHoliday($syr, $smt, $dt, $weekday, $lastday)) echo $data[$i]['timecard_timeover']; ?>&nbsp;</td>
	<td><?php echo $data[$i]['timecard_timeinterval']?>&nbsp;</td>
	<td><?php if($calendar->checkHoliday($syr, $smt, $dt, $weekday, $lastday))
	 echo $data[$i]['timecard_time']; ?>&nbsp;</td> <!--  time holidays -->
	<td class="memo"><?=$data[$i]['timecard_comment']?></td>
	<td style="white-space:nowrap;"><?=$edit?></td></tr>
<?php
	$weekday = ($weekday + 1) % 7;
}
$sum = sprintf('%d:%02d', (($sum - ($sum % 60)) / 60), ($sum % 60));
$sumholiday = sprintf('%d:%02d', (($sumholiday - ($sumholiday % 60)) / 60), ($sumholiday % 60));
$over_sum = sprintf('%d:%02d', (($over_sum - ($over_sum % 60)) / 60), ($over_sum % 60));
?>
		<tr><td colspan="3" class="timecardtotal">時間合計</td><td class="timecardtotal"><?=$sum?>&nbsp;</td><td class="timecardtotal"><?=$over_sum?>&nbsp;</td><td class="timecardtotal"></td><td class="timecardtotal"><?=$sumholiday?>&nbsp;</td><td class="timecardtotal" colspan="2">&nbsp;</td></tr>
	</table>
	<div class="property">
<?php
if ($hash['config']['lunchclosehour'] > 0 || $hash['config']['lunchcloseminute'] > 0) {
	$lunchtime = sprintf('%d:%02d-%d:%02d', $hash['config']['lunchopenhour'], $hash['config']['lunchopenminute'], $hash['config']['lunchclosehour'], $hash['config']['lunchcloseminute']);
	echo '※休憩時間にはランチタイム（'.$lunchtime.'）が含まれます。<br />';
}
?>
	</div>
	<ul class="operate">
<?php
if ($_GET['original'] == 1) {

	echo '<a href="index.php'.$view->parameter(array('year'=>$_GET['year'], 'month'=>$_GET['month'], 'member'=>$_GET['member'])).'">編集前の時刻を表示しない</a>';
} else {
	echo '<a href="index.php'.$view->parameter(array('year'=>$_GET['year'], 'month'=>$_GET['month'], 'member'=>$_GET['member'], 'original'=>1)).'">編集前の時刻を表示する</a>';
}
?>
		</li>
	</ul>
</form>
<?php
$view->footing();
?>
