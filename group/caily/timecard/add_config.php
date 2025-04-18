<?php

require_once('../application/loader.php');
$view->heading('従業員の種類追加');
$array = array('0'=>'00', '10'=>'10', '20'=>'20', '30'=>'30', '40'=>'40', '50'=>'50');
?>
<!-- 
<form class="content" method="post" action="">
	
    <div style="margin-bottom: 10px;">
       
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
</form> -->

<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card mb-6">
        <div class="card-header d-flex justify-content-between">
          <h5 class="card-title mb-0">従業員の種類追加</h5>
          <!-- <small class="text-body-secondary">Updated 1 month ago</small> -->
        </div>
        <div class="card-body d-flex align-items-end">

			<script>
			function redirectConfig() {
				var list_config = document.getElementsByName('list_config')[0].value;
				location.href = 'config.php?type=' + list_config;
			}
			</script>
			<form class="w-100" method="post" action="">
				<?=$view->error($hash['error'])?>
				<div class="mb-4 row">
					<input type="hidden" name="type_id" value="<?=$hash['type_id']?>">
					<label>種類: <input class="form-control" type="text" name="config_name" size="40" value="<?=$hash['data']['config_name']?>"></label>
				</div>
				<div class="mb-4 row">
					<label class="col-md-3 col-form-label">出社時刻</label>
					<div class="col-md-4 row">
						<div class="col-md-5 d-flex align-items-center"><select class="form-select" name="<?=$hash['type_id']?>[openhour]"><?=$helper->option(0, 23, $hash['data']['openhour'])?></select>&nbsp;時</div>
						<div class="col-md-5 d-flex align-items-center"><?=$helper->selector($hash['type_id'].'[openminute]', $array, $hash['data']['openminute'])?>&nbsp;分</div>
					</div>
				</div>
				<div class="mb-4 row">
					<label class="col-md-3 col-form-label">退社時刻</label>
					<div class="col-md-4 row">
						<div class="col-md-5 d-flex align-items-center"><select class="form-select" name="<?=$hash['type_id']?>[closehour]"><?=$helper->option(0, 23, $hash['data']['closehour'])?></select>&nbsp;時&nbsp;</div>
						<div class="col-md-5 d-flex align-items-center"><?=$helper->selector($hash['type_id'].'[closeminute]', $array, $hash['data']['closeminute'])?>分</div>
					</div>
				</div>
				<div class="mb-4 row">
					<label class="col-md-3 col-form-label">勤務計算単位</label>
					<div class="col-md-4 row">
						<div class="col-md-5 d-flex align-items-center"><?=$helper->radio($hash['type_id'].'[timeround]', 0, $hash['data']['timeround'], 'timeround0', '1分単位')?></div>
						<div class="col-md-5 d-flex align-items-center"><?=$helper->radio($hash['type_id'].'[timeround]', 1, $hash['data']['timeround'], 'timeround1', '10分単位')?></div>
					</div>
				</div>
				<div class="mb-4 row">
					<label class="col-md-3 col-form-label">固定休憩時刻</label>
					<div class="col-md-6 row">
						<div class="col-md-5 d-flex align-items-center text-nowrap"><select class="form-select" name="<?=$hash['type_id']?>[lunchopenhour]"><?=$helper->option(0, 23, $hash['data']['lunchopenhour'])?></select>時&nbsp;
							<?=$helper->selector($hash['type_id'].'[lunchopenminute]', $array, $hash['data']['lunchopenminute'])?>分&nbsp;
							~&nbsp;</div>
							<div class="col-md-5 d-flex align-items-center"><select class="form-select" name="<?=$hash['type_id']?>[lunchclosehour]"><?=$helper->option(0, 23, $hash['data']['lunchclosehour'])?></select>時&nbsp;
							<?=$helper->selector($hash['type_id'].'[lunchcloseminute]', $array, $hash['data']['lunchcloseminute'])?>分&nbsp;</div>
					</div>
				</div>
				<div class="mb-4 row">
					<label class="col-md-3 col-form-label">休憩時間計算単位</label>
					<div class="col-md-4 row">
						<div class="col-md-5 d-flex align-items-center"><?=$helper->radio($hash['type_id'].'[intervalround]', 0, $hash['data']['intervalround'], 'intervalround0', '1分単位')?></div>
						<div class="col-md-5 d-flex align-items-center"><?=$helper->radio($hash['type_id'].'[intervalround]', 1, $hash['data']['intervalround'], 'intervalround1', '10分単位')?></div>
					</div>
				</div>
				<div class="submit">
					<button type="submit" class="btn btn-primary">設定</button>
					<a href="config.php" type="button" class="btn btn-secondary">キャンセル</a>
				</div>
			</form>
		</div>
	</div>
</div>
<?php
$view->footing();
?>