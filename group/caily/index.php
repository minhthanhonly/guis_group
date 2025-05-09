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
if(!isset($hash['timecard']['timecard_close']) || $hash['timecard']['timecard_close'] == '') { 
if ($current_hour >= 6 && $current_hour < 12) {
    $welcome_message .= 'おはようございます！';
} elseif ($current_hour >= 12 && $current_hour < 18) {
    $welcome_message .= 'こんにちは！';
} else {
    $welcome_message .= 'こんばんは！';
}
}
?>

<!-- Content -->
<div class="container-xxl flex-grow-1 container-p-y">
	<div class="row g-6">
    <!-- View sales -->
    <div class="col-xl-4 col-md-6">
      <div class="card">
        <div class="d-flex align-items-end row">
          <div class="col-7">
            <div class="card-body text-nowrap">
              <h5 class="card-title mb-0"><?=$welcome_message?></h5>
              <p class="mb-4"><?=$today_message?></p>
              <button <?php if(isset($hash['timecard']['timecard_open']) && $hash['timecard']['timecard_open']!= ''){ echo 'disabled'; }?> class="me-2 btn btn-primary waves-effect waves-light" id="checkin">出社</button>
              <button <?php if(isset($hash['timecard']['timecard_close']) && $hash['timecard']['timecard_close'] != '') { echo 'disabled'; }?> class="btn btn-warning waves-effect waves-light" id="checkout" data-id="<?=$hash['timecard']['id']?>" data-open="<?=$hash['timecard']['timecard_open']?>">退社</button>
              <div id="timecard-result" class="mt-3">
                <?php if(isset($hash['timecard']['timecard_close']) && $hash['timecard']['timecard_close'] != '') { ?>
                  <p class="text-success mb-0">お疲れ様でした！<br>勤務時間は<?=$hash['timecard']['timecard_time']?>です。
                  <?php if(isset($hash['timecard']['timecard_timeover']) && $hash['timecard']['timecard_timeover'] != '0:00') { ?>
                    時間外は<?=$hash['timecard']['timecard_timeover']?>です。
                  <?php } ?>
                </p> 
                <?php } ?>
              </div>
            </div>
          </div>
          <div class="col-5 text-center text-sm-left">
            <div class="card-body pb-0 px-0 px-md-4" id="ai-image" data-bs-toggle="modal" data-bs-target="#modalAI">
              <img src="<?=$root?>assets/img/illustrations/girl-with-laptop.png" height="140" alt="view sales" >
              <div class="speech-bubble">
                <div class="typing-text">
                  AIチャットで何でも聞いてください！
                </div>
                <div class="bubble-arrow"></div>
              </div>
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
<script src="<?=ROOT?>assets/js/top.js"></script>