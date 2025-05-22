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
if(str_contains($_SESSION['firstname'], '社長') || str_contains($_SESSION['lastname'], '社長')) {
  $welcome_message = $_SESSION['lastname'].'社長、';
} else {
  $welcome_message = $_SESSION['lastname'].'さん、';
}
$today_message = '今日は'.$today.'です。';
if(!isset($hash['timecard']['timecard_close']) || $hash['timecard']['timecard_close'] == '') { 
if ($current_hour >= 6 && $current_hour < 12) {
    $welcome_message .= 'ようこそ！';
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
    <div class="col-md-8 col-lg-6 col-xl-4" style="min-width: 440px;">
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
            <div class="card-body pb-0 px-0 text-end" id="ai-image" data-bs-toggle="modal" data-bs-target="#modalAI">
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
  </div>
  <div class="row g-6 mt-1">                
    <!-- Statistics -->
    <div class="col-xl-6 col-md-12">
      <div class="card h-100">
        <div class="card-body pb-0 app-calendar-wrapper">
          <div id="calendar"></div>
        </div>
      </div>
    </div>
    <div class="col-xl-6 col-md-12">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between">
          <h5 class="card-title mb-0">お知らせ</h5>
          <div class="d-flex justify-content-end">
            <a class="btn btn-sm btn-primary" href="<?=$root?>forum/index.php">もっと見る</a>
          </div>
        </div>
        <div class="card-body">
          <table class="table table-bordered">
            <?php
            if (is_array($hash['forum']) && count($hash['forum']) > 0) {
              foreach ($hash['forum'] as $row) {
            ?>
                <tr>
                  <td class="w-20 fs-small text-nowrap px-0"><?=date('Y年m月d日 H:i', strtotime($row['forum_lastupdate']))?><br><span class="badge bg-label-info me-1"><?=$row['forum_name']?></span><?php 
                  if(date('Y-m-d H:i:s', strtotime($row['created'])) > date('Y-m-d H:i:s', strtotime('-1 week'))){
                    echo '<span class="badge bg-label-danger me-2">NEW</span>';
                  }
                  ?></td>
                  <td><a href="/forum/view.php?id=<?=$row['id']?>"><?=$row['forum_title']?></a></td>
               </tr>
            <?php
              }
            }?>
          </table>
        </div>
      </div>
    </div>

	</div>
 <?php if($_SESSION['authority'] == 'administrator' || $_SESSION['authority'] == 'manager') { ?>
  <div class="row g-6 mt-1">
    <div class="col-md-12 col-lg-12 col-xl-6">
      <div class="card">
        <div class="card-header d-flex justify-content-between">
          <h5 class="card-title mb-0">勤怠統計</h5>
          <div class="d-flex justify-content-end align-items-center gap-2">
            <select class="form-select select2" id="timecard-statistic-select" data-placeholder="メンバーを選択">
              <option value="">すべて</option>
              <?php
              foreach ($memberList as $member) {
                echo '<option value="'.$member['id'].'">'.$member['name'].'</option>';
              }
              ?>
            </select>
            <?php if($_SESSION['authority'] == 'administrator') { ?>
              <button class="btn btn-primary text-nowrap flex-shrink-0" id="generate-statistic">更新</button>
            <?php } ?>
          </div>
        </div>
        <div class="card-body">
          <p class="text-end mb-0"><small class="text-body-secondary" id="timecard-statistic-updated">Updated 1 month ago</small></p>
          <div id="timecard-statistic"></div>
        </div>
      </div>
    </div>
    <!-- View sales -->
  </div>
<?php } ?>
	
</div>
<!-- / Content -->
<?php
$view->footing();
?>
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/fullcalendar/fullcalendar.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/css/pages/app-calendar.css" />
<script src="<?=ROOT?>assets/vendor/libs/fullcalendar/fullcalendar.js"></script>
<script src="<?=ROOT?>assets/js/top.js?v=<?=CACHE_VERSION?>"></script>