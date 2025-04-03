<?php

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
<!-- Content -->
<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card mb-6">
        <div class="card-header d-flex justify-content-between">
          <h5 class="card-title mb-0">時間合計</h5>
          <!-- <small class="text-body-secondary">Updated 1 month ago</small> -->
        </div>
        <div class="card-body d-flex align-items-end">
          <div class="w-100">
            <div class="row gy-3">
              <div class="col-md-3 col-6">
                <div class="d-flex align-items-center">
                  <div class="badge rounded bg-label-primary me-4 p-2"><i class="icon-base ti tabler-chart-pie-2 icon-lg"></i></div>
                  <div class="card-info">
                    <h4 class="mb-0" id="work_time">0</h4>
                    <small>勤務時間</small>
                  </div>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="d-flex align-items-center">
                  <div class="badge rounded bg-label-info me-4 p-2"><i class="icon-base ti tabler-clock-play icon-lg"></i></div>
                  <div class="card-info">
                    <h4 class="mb-0" id="over_time">0</h4>
                    <small>時間外</small>
                  </div>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="d-flex align-items-center">
                  <div class="badge rounded bg-label-danger me-4 p-2"><i class="icon-base ti tabler-arrow-elbow-right icon-lg"></i></div>
                  <div class="card-info">
                    <h4 class="mb-0" id="holiday_time">0</h4>
                    <small>休日出勤</small>
                  </div>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="d-flex align-items-center">
                  <div class="badge rounded bg-label-success me-4 p-2"><i class="icon-base ti tabler-calendar-week icon-lg"></i></div>
                  <div class="card-info">
                    <h4 class="mb-0" id="work_days">0</h4>
                    <small>営業日数</small>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
    </div>
	<!-- Users List Table -->
	<div class="card" id="option-block">
		<div class="card-header sticky-element bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-4">
				<h4 class="card-title mb-0"><span>タイムカード</span></h4>
			</div>
			<div class="col-md-6 justify-content-end row">
        <div class="col-md-6">
				  <button class="btn add-new btn-primary rounded-2 waves-effect waves-light" type="button" data-recalculation>
            <span><i class="icon-base ti tabler-calculator me-0 me-sm-1 icon-16px"></i><span class="d-none d-sm-inline-block">Tính lại</span></span>
          </button>
				</div>
				<div class="col-md-6">
					<label for="selectpickerUser" class="col-md-2 col-form-label">User</label>
					<div class="col-md-10">
						<select class="selectpicker w-100 show-tick" id="selectpickerUser" data-current-user="<?=$_SESSION['userid']?>" data-icon-base="icon-base ti" data-tick-icon="tabler-check" data-style="btn-default">
						</select>
					</div>
				</div>
				<div class="col-md-6">
					<label for="timecard-month-input" class="col-md-2 col-form-label">Month</label>
					<div class="col-md-10">
						<input class="form-control" type="month" value="" id="timecard-month-input" lang="fr-CA">
					</div>
				</div>
			</div>
		</div>
		<div class="card-datatable">
		<div class="table-responsive text-nowrap">
			<table class="datatables-timecard table table-bordered">
				<thead>
					<tr>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</div>
		
	</div>
</div>
<!-- / Content -->
<?php
$view->footing();
?>

<script src="<?=ROOT?>assets/js/timecard.js"></script>