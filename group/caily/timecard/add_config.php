<?php

require_once('../application/loader.php');
$view->heading('従業員の種類追加');
$array = array('0'=>'00', '10'=>'10', '20'=>'20', '30'=>'30', '40'=>'40', '50'=>'50');
?>
<h1>従業員の種類追加</h1>
<ul class="operate">
	<li><a href="../administration.php">一覧に戻る</a></li>
</ul>
<form class="content" method="post" action="">
	<?=$view->error($hash['error'])?>
    <div style="margin-bottom: 10px;">
        <input type="hidden" name="type_id" value="<?=$hash['type_id']?>">
        <label>種類: <input type="text" name="config_name" size="40" value="<?=$hash['data']['config_name']?>"></label>
	</div>
	<table class="form time_set" cellspacing="0" border="0">
		<tr><th>出社時刻<?=$view->explain('timecardopen')?></th><td>
			<select name="<?=$hash['type_id']?>[openhour]"><?=$helper->option(0, 23, $hash['data']['openhour'])?></select>時&nbsp;
			<?=$helper->selector($hash['type_id'] . '[openminute]', $array, $hash['data']['openminute'])?>分&nbsp;
		</td></tr>
		<tr><th>退社時刻<?=$view->explain('timecardclose')?></th><td>
			<select name="<?=$hash['type_id']?>[closehour]"><?=$helper->option(0, 23, $hash['data']['closehour'])?></select>時&nbsp;
			<?=$helper->selector($hash['type_id'] . '[closeminute]', $array, $hash['data']['closeminute'])?>分&nbsp;
		</td></tr>
		<tr><th>勤務計算単位<?=$view->explain('timecardround')?></th><td>
			<?=$helper->radio($hash['type_id'] . '[timeround]', 0, $hash['data']['timeround'], 'timeround0', '1分単位')?>
			<?=$helper->radio($hash['type_id'] . '[timeround]', 1, $hash['data']['timeround'], 'timeround1', '10分単位')?>
		</td></tr>
		<tr><th>固定休憩時刻<?=$view->explain('timecardlunch')?></th><td>
			<select name="<?=$hash['type_id']?>[lunchopenhour]"><?=$helper->option(0, 23, $hash['data']['lunchopenhour'])?></select>時&nbsp;
			<?=$helper->selector($hash['type_id'] . '[lunchopenminute]', $array, $hash['data']['lunchopenminute'])?>分&nbsp;
			-&nbsp;
			<select name="<?=$hash['type_id']?>[lunchclosehour]"><?=$helper->option(0, 23, $hash['data']['lunchclosehour'])?></select>時&nbsp;
			<?=$helper->selector($hash['type_id'] . '[lunchcloseminute]', $array, $hash['data']['lunchcloseminute'])?>分&nbsp;
		</td></tr>
		<tr><th>休憩時間計算単位<?=$view->explain('timecardlunchround')?></th><td>
			<?=$helper->radio($hash['type_id'] . '[intervalround]', 0, $hash['data']['intervalround'], 'intervalround0', '1分単位')?>
			<?=$helper->radio($hash['type_id'] . '[intervalround]', 1, $hash['data']['intervalround'], 'intervalround1', '10分単位')?>
		</td></tr>
	</table>
	<div class="submit">
		<input type="submit" value="　追加　" />&nbsp;
		<input type="button" value="キャンセル" onclick="location.href='config.php'" />
	</div>
</form>
<?php
$view->footing();
?>