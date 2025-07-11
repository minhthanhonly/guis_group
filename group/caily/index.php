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
if(strlen($_SESSION['firstname']) == 0) {
  $welcome_message = $_SESSION['realname'].'さん、';
} else if(str_contains($_SESSION['firstname'], '社長') || str_contains($_SESSION['lastname'], '社長')) {
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
        <div class="d-flex align-items-center row">
          <div class="col-7">
            <div class="card-body text-nowrap">
              <h5 class="card-title mb-0"><?=$welcome_message?></h5>
              <p class="mb-4"><?=$today_message?></p>
              <?php if($_SESSION['group'] != '6'){ ?>
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
              <?php } ?>
            </div>
          </div>
          <div class="col-5 text-center text-sm-left">
            <div class="card-body pb-0 px-0 text-end" id="ai-image" data-bs-toggle="modal" data-bs-target="#modalAI">
              <img src="<?=$root?>assets/img/illustrations/girl-with-laptop.png" height="140" alt="view sales" >
              <div class="speech-bubble">
                <div class="typing-text" data-i18n="AIチャットで何でも聞いてください！">
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
    <?php if($_SESSION['group'] != '7' && $_SESSION['group'] != '6'){ ?>
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
    <?php } ?>
  </div>
 
 <?php if($_SESSION['authority'] == 'administrator' || $_SESSION['authority'] == 'manager') { ?>
  <div class="row g-6 mt-1">
    <div class="col-md-12 col-lg-12 col-xl-12">
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

<!-- Project Statistics Section -->
<?php if($_SESSION['authority'] == 'administrator' || $_SESSION['authority'] == 'manager') { ?>
<div id="project-stats-section">
  <!-- Project Overview Cards -->
  <div class="row g-4 mt-1">
    <div class="col-md-6 col-xl-3">
      <div class="card project-stats-card">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="avatar avatar-md bg-label-primary rounded d-flex align-items-center justify-content-center">
              <i class="fas fa-project-diagram fs-4"></i>
            </div>
            <div class="ms-3">
              <h5 class="mb-0" id="total-projects">0</h5>
              <small class="text-muted">総案件数</small>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3">
      <div class="card project-stats-card">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="avatar avatar-md bg-label-success rounded d-flex align-items-center justify-content-center">
              <i class="fas fa-play-circle fs-4"></i>
            </div>
            <div class="ms-3">
              <h5 class="mb-0" id="active-projects">0</h5>
              <small class="text-muted">進行中案件</small>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3">
      <div class="card project-stats-card">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="avatar avatar-md bg-label-info rounded d-flex align-items-center justify-content-center">
              <i class="fas fa-check-circle fs-4"></i>
            </div>
            <div class="ms-3">
              <h5 class="mb-0" id="completed-projects">0</h5>
              <small class="text-muted">完了案件</small>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3">
      <div class="card project-stats-card">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="avatar avatar-md bg-label-warning rounded d-flex align-items-center justify-content-center">
              <i class="fas fa-calendar-plus fs-4"></i>
            </div>
            <div class="ms-3">
              <h5 class="mb-0" id="new-this-month">0</h5>
              <small class="text-muted">新規案件</small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div class="row g-4 mt-1">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <label for="project-stats-department" class="form-label">部署選択</label>
              <select class="form-select" id="project-stats-department">
                <option value="">すべての部署</option>
                <!-- Options will be loaded dynamically -->
              </select>
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <button type="button" class="btn btn-primary" onclick="loadProjectStats()">
                <i class="fas fa-sync-alt"></i> 更新
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Monthly Stats Chart -->
  <div class="row g-4 mt-1">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0">月別案件統計</h5>
        </div>
        <div class="card-body">
          <div id="monthly-stats-chart"></div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0">部署別売上推移</h5>
        </div>
        <div class="card-body">
          <div id="financial-chart"></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php } ?>
	
</div>
<!-- / Content -->
<?php
$view->footing();
?>
<script src="<?=$root?>assets/vendor/libs/apex-charts/apexcharts.js"></script>
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/fullcalendar/fullcalendar.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/css/pages/app-calendar.css" />
<script src="<?=ROOT?>assets/vendor/libs/fullcalendar/fullcalendar.js"></script>
<script src="<?=ROOT?>assets/js/top.js?v=<?=CACHE_VERSION?>"></script>