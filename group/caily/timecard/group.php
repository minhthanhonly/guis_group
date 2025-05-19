<?php

require_once('../application/loader.php');
$view->heading('時間合計');
$calendar = new Calendar;
$timestamp = mktime(0, 0, 0, $_GET['month']-1, 21, $_GET['year']);
$lastday = date('t', $timestamp);
$weekday = date('w', $timestamp);
?>
<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card mb-6">
        <div class="card-header d-flex justify-content-between">
          <h5 class="card-title mb-0">時間合計</h5>
          <!-- <small class="text-body-secondary">Updated 1 month ago</small> -->
        </div>
		<script>
		function redirect(e, id) {
			if(e.name == 'year') {
				var year = e.value;
				var month = document.getElementsByName('month')[0].value;
				var group = document.getElementsByName('group')[0].value;
			} else if(e.name == 'month') {
				var year = document.getElementsByName('year')[0].value;
				var month = e.value;
				var group = document.getElementsByName('group')[0].value;
			} else {
				var year = document.getElementsByName('year')[0].value;
				var month = document.getElementsByName('month')[0].value;
				var group = e.value;
			}
			location.href = 'group.php?group=' + group + '&year=' + year + '&month=' + month;
		}
		</script>
        <div class="card-body">
			<div class="w-100">
				<div class="row gy-3">
					<div class="col-md-3 d-flex align-items-center"><select class="form-select" name="year" onchange="redirect(this,'group')"><?=$helper->option(2000, 2030, $_GET['year'])?></select>&nbsp;年</div>
					<div class="col-md-3 d-flex align-items-center"><select class="form-select" name="month" onchange="redirect(this,'group')"><?=$helper->option(1, 12, $_GET['month'])?></select>&nbsp;月&nbsp;</div>
					<div class="col-md-3"><?=$helper->selector('group', $hash['group'], $_GET['group'], ' onchange="redirect(this,\'group\')"')?></div>
				</div>
			</div>
			<div class="w-100">
				<table class="table table-striped">
					<tr><th>名前</th><th>勤務日数</th><th>勤務時間合計</th><th>時間外合計</th><th>休日出勤</th></tr>
				<?php
				$data = array();
				if (is_array($hash['list']) && count($hash['list']) > 0) {
					foreach ($hash['list'] as $row) {
						$timestamp = mktime(0, 0, 0, $row['timecard_month'], $row['timecard_day'], $row['timecard_year']);
						$lastday = date('t', $timestamp);
						$weekday = date('w', $timestamp);
						if ($row['timecard_open'] && $row['timecard_close'] && $row['timecard_time'] && !$row['holiday']) {
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
						
						$sum = 0;
						$day = 0;
						$intervalsum = 0;
						$sum2 = 0;
						if(isset($data[$key]['sum'])) {
							$day = count($data[$key]['sum']);
							if (is_array($data[$key]['sum'])) {
								$sum = array_sum($data[$key]['sum']);
							} else {
								$sum = 0;
							}
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
				?>
			</table>
			</div>
		</div>
	</div>
</div>
<?php
$view->footing();
?>