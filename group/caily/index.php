<?php
require_once('application/loader.php');
$view->script('general.js');
$view->heading('', 'top');
// $hash['group'] = array('グループ') + $hash['group'];
// $calendar = new Calendar;
// $previous = mktime(0, 0, 0, $hash['month'], $hash['day'] - 7, $hash['year']);
// $next = mktime(0, 0, 0, $hash['month'], $hash['day'] + 7, $hash['year']);
$week = array('日', '月', '火', '水', '木', '金', '土');
$today = $hash['year'].'年'.$hash['month'].'月'.$hash['day'].'日('.$week[$hash['weekday']].')';

$current_hour = date('H');
$welcome_message = $_SESSION['realname'].'さん、';
$today_message = '今日は'.$today.'です。';
if ($current_hour >= 6 && $current_hour < 12) {
    $welcome_message .= 'おはようございます！';
} elseif ($current_hour >= 12 && $current_hour < 18) {
    $welcome_message .= 'こんにちは！';
} else {
    $welcome_message .= 'こんばんは！';
}

?>

<!-- Content -->
<div class="container-xxl flex-grow-1 container-p-y">
	<div class="row g-6">
    <!-- View sales -->
    <div class="col-xl-4">
      <div class="card">
        <div class="d-flex align-items-center row">
          <div class="col-7">
            <div class="card-body text-nowrap">
              <h5 class="card-title mb-0"><?=$welcome_message?></h5>
              <p class="mb-4"><?=$today_message?></p>
			  <?php if($hash['timecard'] && $hash['timecard']['timecard_open'] == ''){ ?>
              <a href="javascript:;" class="btn btn-primary waves-effect waves-light" id="checkin">チェックイン</a>
			  <?php } ?>
			  <?php if($hash['timecard'] && $hash['timecard']['timecard_open'] != '' && $hash['timecard']['timecard_close'] == ''){ ?>
              <a href="javascript:;" class="btn btn-primary waves-effect waves-light" id="checkout">チェックアウト</a>
			  <?php } ?>
            </div>
          </div>
          <div class="col-5 text-center text-sm-left">
            <div class="card-body pb-0 px-0 px-md-4">
              <img src="<?=$root?>assets/img/illustrations/card-advance-sale.png" height="140" alt="view sales">
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- View sales -->

    <!-- Statistics -->
    <!-- <div class="col-xl-8 col-md-12">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between">
          <h5 class="card-title mb-0">今期の統計</h5>
          <small class="text-body-secondary">更新日：<?=$today?></small>
        </div>
        <div class="card-body d-flex align-items-end">
          <div class="w-100">
            <div class="row gy-3">
              <div class="col-md-3 col-6">
                <div class="d-flex align-items-center">
                  <div class="badge rounded bg-label-primary me-4 p-2"><i class="icon-base ti tabler-briefcase icon-lg"></i></div>
                  <div class="card-info">
                    <h5 class="mb-0">999k</h5>
                    <small>案件</small>
                  </div>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="d-flex align-items-center">
                  <div class="badge rounded bg-label-info me-4 p-2"><i class="icon-base ti tabler-list-check icon-lg"></i></div>
                  <div class="card-info">
                    <h5 class="mb-0">999k</h5>
                    <small>タスク</small>
                  </div>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="d-flex align-items-center">
                  <div class="badge rounded bg-label-success me-4 p-2"><i class="icon-base ti tabler-list-check icon-lg"></i></div>
                  <div class="card-info">
                    <h5 class="mb-0">999k</h5>
                    <small>品質</small>
                  </div>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="d-flex align-items-center">
                  <div class="badge rounded bg-label-warning me-4 p-2"><i class="icon-base ti tabler-currency-yen icon-lg"></i></div>
                  <div class="card-info">
                    <h5 class="mb-0">99,999万円</h5>
                    <small>収益</small>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div> -->
    <!--/ Statistics -->

	</div>

	
</div>
<!-- / Content -->
<?php
$view->footing();
?>