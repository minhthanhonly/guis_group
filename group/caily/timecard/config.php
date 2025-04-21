<?php


require_once('../application/loader.php');
$view->heading('タイムカード設定');
$array = array('0'=>'00', '10'=>'10', '20'=>'20', '30'=>'30', '40'=>'40', '50'=>'50');
?>
<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card mb-6">
        <div class="card-header d-flex justify-content-between">
          <h5 class="card-title mb-0">タイムカード設定</h5>
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
					<div class="col-md-4">
						<input type="hidden" name="type_id" value="<?=$hash['type_id']?>">
						<select class="form-select col-md-4" name="list_config" onchange="redirectConfig()">
						<?php foreach ($hash['data']['list_config'] as $key => $value) { ?>
							<option value="<?php echo $value["config_type"]; ?>" 
							<?php if ($hash['data']['type_id'] == $value["config_type"]) { ?>selected="selected"<?php } ?>>
								<?php echo $value["config_name"]; ?>
							</option>
						<?php } ?>
						</select>
					</div>
					<div class="col-md-4"><a href="javascript:void(0)" class="btn btn-primary" onclick="location.href='add_config.php'">種類を追加する</a></div>
					
				</div>
				<h2><?=$hash['data']['config_name']?></h2>
				<div class="mb-4 row">
					<label class="col-md-3 col-form-label">種類</label>
					<div class="col-md-4">
						<input class="form-control" type="text" name="config_name" size="40" value="<?=$hash['data']['config_name']?>">
					</div>
				</div>
				<div class="mb-4 row">
					<label class="col-md-3 col-form-label">出社時刻</label>
					<div class="col-md-4 row">
						<div class="col-md-5 d-flex align-items-center"><select class="form-select" name="<?=$hash['data']["type_id"]?>[openhour]"><?=$helper->option(0, 23, $hash['data']['openhour'])?></select>&nbsp;時</div>
						<div class="col-md-5 d-flex align-items-center"><?=$helper->selector($hash['data']["type_id"].'[openminute]', $array, $hash['data']['openminute'])?>&nbsp;分</div>
					</div>
				</div>
				<div class="mb-4 row">
					<label class="col-md-3 col-form-label">退社時刻</label>
					<div class="col-md-4 row">
						<div class="col-md-5 d-flex align-items-center"><select class="form-select" name="<?=$hash['data']["type_id"]?>[closehour]"><?=$helper->option(0, 23, $hash['data']['closehour'])?></select>&nbsp;時&nbsp;</div>
						<div class="col-md-5 d-flex align-items-center"><?=$helper->selector($hash['data']["type_id"].'[closeminute]', $array, $hash['data']['closeminute'])?>分</div>
					</div>
				</div>
				<div class="mb-4 row">
					<label class="col-md-3 col-form-label">勤務計算単位</label>
					<div class="col-md-4 row">
						<div class="col-md-5 d-flex align-items-center"><?=$helper->radio($hash['data']["type_id"].'[timeround]', 0, $hash['data']['timeround'], 'timeround0', '1分単位')?></div>
						<div class="col-md-5 d-flex align-items-center"><?=$helper->radio($hash['data']["type_id"].'[timeround]', 1, $hash['data']['timeround'], 'timeround1', '10分単位')?></div>
					</div>
				</div>
				<div class="mb-4 row">
					<label class="col-md-3 col-form-label">固定休憩時刻</label>
					<div class="col-md-6 row">
						<div class="col-md-5 d-flex align-items-center text-nowrap"><select class="form-select" name="<?=$hash['data']["type_id"]?>[lunchopenhour]"><?=$helper->option(0, 23, $hash['data']['lunchopenhour'])?></select>時&nbsp;
							<?=$helper->selector($hash['data']["type_id"].'[lunchopenminute]', $array, $hash['data']['lunchopenminute'])?>分&nbsp;
							~&nbsp;</div>
							<div class="col-md-5 d-flex align-items-center"><select class="form-select" name="<?=$hash['data']["type_id"]?>[lunchclosehour]"><?=$helper->option(0, 23, $hash['data']['lunchclosehour'])?></select>時&nbsp;
							<?=$helper->selector($hash['data']["type_id"].'[lunchcloseminute]', $array, $hash['data']['lunchcloseminute'])?>分&nbsp;</div>
					</div>
				</div>
				<div class="mb-4 row">
					<label class="col-md-3 col-form-label">休憩時間計算単位</label>
					<div class="col-md-4 row">
						<div class="col-md-5 d-flex align-items-center"><?=$helper->radio($hash['data']["type_id"].'[intervalround]', 0, $hash['data']['intervalround'], 'intervalround0', '1分単位')?></div>
						<div class="col-md-5 d-flex align-items-center"><?=$helper->radio($hash['data']["type_id"].'[intervalround]', 1, $hash['data']['intervalround'], 'intervalround1', '10分単位')?></div>
					</div>
				</div>
				<div class="submit">
					<button type="submit" class="btn btn-primary">設定</button>
					<a href="index.php" type="button" class="btn btn-secondary">キャンセル</a>
				</div>
			</form>
		</div>
	</div>
</div>
<?php
$view->footing();
?>