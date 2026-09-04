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
$is_caily_user = ((string)($_SESSION['group'] ?? '') === '7');
if ($is_caily_user) {
  // Group 7: greet by firstname (no ruby)
  $welcome_name = $_SESSION['firstname'] !== '' ? $_SESSION['firstname'] : ($_SESSION['lastname'] !== '' ? $_SESSION['lastname'] : $_SESSION['realname']);
  if (str_contains((string)$_SESSION['firstname'], '社長') || str_contains((string)$_SESSION['lastname'], '社長')) {
    $welcome_suffix = '社長、';
  } else {
    $welcome_suffix = 'さん、';
  }
  $welcome_ruby = '';
} else if(strlen($_SESSION['firstname']) == 0) {
  $welcome_name = $_SESSION['realname'];
  $welcome_suffix = 'さん、';
  $welcome_ruby = isset($_SESSION['user_ruby']) ? trim((string)$_SESSION['user_ruby']) : '';
} else if(str_contains($_SESSION['firstname'], '社長') || str_contains($_SESSION['lastname'], '社長')) {
  $welcome_name = $_SESSION['lastname'];
  $welcome_suffix = '社長、';
  $welcome_ruby = isset($_SESSION['user_ruby']) ? trim((string)$_SESSION['user_ruby']) : '';
} else {
  $welcome_name = $_SESSION['lastname'];
  $welcome_suffix = 'さん、';
  $welcome_ruby = isset($_SESSION['user_ruby']) ? trim((string)$_SESSION['user_ruby']) : '';
}
$today_message = '今日は'.$today.'です。';
$welcome_greeting = '';
if(!isset($hash['timecard']['timecard_close']) || $hash['timecard']['timecard_close'] == '') { 
if ($current_hour >= 6 && $current_hour < 11) {
    $welcome_greeting = 'おはようございます！';
} elseif ($current_hour >= 11 && $current_hour < 17) {
    $welcome_greeting = 'こんにちは！';
} else {
    $welcome_greeting = 'こんばんは！';
}
}

$can_approve_requests = false;
$is_request_admin = false;
$is_soumu_user = !empty($_SESSION['is_soumu']) && (string)$_SESSION['is_soumu'] === '1';
if (!empty($_SESSION['userid'])) {
  $is_request_admin = (isset($_SESSION['authority']) && $_SESSION['authority'] === 'administrator') || $is_soumu_user;
  if ($is_request_admin) {
    $can_approve_requests = true;
  } else {
    require_once DIR_MODEL . 'request.php';
    $reqModel = new Request();
    $reqModel->connect();
    $u = $reqModel->fetchOne("SELECT can_approve_request, is_soumu FROM " . DB_PREFIX . "user WHERE userid = '" . $reqModel->quote($_SESSION['userid']) . "'");
    $can_approve_requests = !empty($u['can_approve_request']) || (!empty($u['is_soumu']) && (string)$u['is_soumu'] === '1');
    if (!$is_request_admin && !empty($u['is_soumu']) && (string)$u['is_soumu'] === '1') {
      $is_request_admin = true;
    }
    $reqModel->close();
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
              <h5 class="card-title mb-0">
                <span data-welcome-name="<?=htmlspecialchars($welcome_name, ENT_QUOTES, 'UTF-8')?>"
                  data-welcome-ruby="<?=htmlspecialchars($welcome_ruby, ENT_QUOTES, 'UTF-8')?>"><?=htmlspecialchars($welcome_name, ENT_QUOTES, 'UTF-8')?></span><span data-i18n="<?=$welcome_suffix?>"><?=$welcome_suffix?></span><?php if($welcome_greeting): ?><span data-i18n="<?=$welcome_greeting?>"><?=$welcome_greeting?></span><?php endif; ?>
              </h5>
              <p class="mb-4" data-i18n-today data-date="<?=$today?>" data-ja="今日は{date}です。" data-vi="Hôm nay là {date}."><?=$today_message?></p>
              <?php if($_SESSION['group'] != '6' && $_SESSION['group'] != '7'){ ?>
              <button <?php if(isset($hash['timecard']['timecard_open']) && $hash['timecard']['timecard_open']!= ''){ echo 'disabled'; }?> class="me-2 btn btn-primary waves-effect waves-light" id="checkin" data-i18n="出社">出社</button>
              <button <?php if(isset($hash['timecard']['timecard_close']) && $hash['timecard']['timecard_close'] != '') { echo 'disabled'; }?> class="btn btn-warning waves-effect waves-light" id="checkout" data-id="<?=$hash['timecard']['id']?>" data-open="<?=$hash['timecard']['timecard_open']?>" data-i18n="退社">退社</button>
              <div id="timecard-result" class="mt-3">
                <?php
                  $tcOpen = isset($hash['timecard']['timecard_open']) ? trim((string)$hash['timecard']['timecard_open']) : '';
                  $tcClose = isset($hash['timecard']['timecard_close']) ? trim((string)$hash['timecard']['timecard_close']) : '';
                  $tcTime = isset($hash['timecard']['timecard_time']) ? trim((string)$hash['timecard']['timecard_time']) : '';
                  $tcTimeover = isset($hash['timecard']['timecard_timeover']) ? trim((string)$hash['timecard']['timecard_timeover']) : '';
                  $hasTcOpen = ($tcOpen !== '' && $tcOpen !== '00:00' && $tcOpen !== '00:00:00');
                  $hasTcClose = ($tcClose !== '' && $tcClose !== '00:00' && $tcClose !== '00:00:00');
                ?>
                <?php if ($hasTcOpen || $hasTcClose) { ?>
                  <p class="<?= $hasTcClose ? 'text-success' : 'text-info' ?> mb-0">
                    <?php if ($hasTcOpen) { ?>
                      <span data-i18n-timecard-stamp
                        data-kind="open"
                        data-time="<?= htmlspecialchars($tcOpen, ENT_QUOTES, 'UTF-8') ?>"
                        data-ja="出社: {time}"
                        data-vi="Check-in: {time}">出社: <?= htmlspecialchars($tcOpen, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php } ?>
                    <?php if ($hasTcClose) { ?>
                      <?php if ($hasTcOpen) { ?><span class="mx-3" aria-hidden="true"></span><?php } ?>
                      <span data-i18n-timecard-stamp
                        data-kind="close"
                        data-time="<?= htmlspecialchars($tcClose, ENT_QUOTES, 'UTF-8') ?>"
                        data-ja="退社: {time}"
                        data-vi="Check-out: {time}">退社: <?= htmlspecialchars($tcClose, ENT_QUOTES, 'UTF-8') ?></span>
                      <?php if ($tcTime !== '') { ?>
                        <br><span data-i18n="お疲れ様でした！">お疲れ様でした！</span>
                        <br><span data-i18n-timecard-time
                          data-time="<?= htmlspecialchars($tcTime, ENT_QUOTES, 'UTF-8') ?>"
                          data-ja="勤務時間は{time}です。"
                          data-vi="Giờ làm việc: {time}">勤務時間は<?= htmlspecialchars($tcTime, ENT_QUOTES, 'UTF-8') ?>です。</span>
                      <?php } ?>
                      <?php if ($tcTimeover !== '' && $tcTimeover !== '0:00') { ?>
                        <br><span data-i18n-timecard-time
                          data-time="<?= htmlspecialchars($tcTimeover, ENT_QUOTES, 'UTF-8') ?>"
                          data-ja="時間外は{time}です。"
                          data-vi="Ngoài giờ: {time}">時間外は<?= htmlspecialchars($tcTimeover, ENT_QUOTES, 'UTF-8') ?>です。</span>
                      <?php } ?>
                    <?php } ?>
                  </p>
                <?php } ?>
              </div>
              <?php } ?>
            </div>
          </div>
          <div class="col-5 text-center text-sm-left">
            <!-- <div class="card-body pb-0 px-0 text-end" id="ai-image" data-bs-toggle="modal" data-bs-target="#modalAI">
              <img src="<?=$root?>assets/img/illustrations/girl-with-laptop.png" height="140" alt="view sales" >
              <div class="speech-bubble">
                <div class="typing-text" data-i18n="AIチャットで何でも聞いてください！">
                  AIチャットで何でも聞いてください！
                </div>
                <div class="bubble-arrow"></div>
              </div>
            </div> -->
            <div class="card-body pb-0 px-0 text-end" id="ai-image">
              <img src="<?=$root?>assets/img/illustrations/girl-with-laptop.png" height="140" alt="view sales" >
              <div class="speech-bubble">
                <div class="typing-text" data-i18n="GUISシステムへようこそ！">
                  GUISシステムへようこそ！
                </div>
                <div class="bubble-arrow"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php if($_SESSION['group'] != '7'){ ?>
  <div id="dashboardRequestsApp" v-cloak>
    <div v-if="!dashboardReady" class="row g-6 mt-1">
      <div class="col-12">
        <div class="card">
          <div class="card-body text-center text-muted py-5">
            <span class="spinner-border spinner-border-sm me-2" role="status"></span><span data-i18n="読み込み中...">読み込み中...</span>
          </div>
        </div>
      </div>
    </div>
    <div v-else class="row g-6 mt-1">
    <div v-if="recentRequests.length > 0" class="col-md-12" :class="{'col-lg-6 col-xl-6': canApprove}">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center py-3">
          <h5 class="card-title mb-0" data-i18n="最近の申請">最近の申請</h5>
          <a class="btn btn-sm btn-primary" href="<?=$root?>form/index.php" data-i18n="もっと見る">もっと見る</a>
        </div>
        <div class="card-body pt-0">
          <div v-if="recentLoading" class="text-center text-muted py-4">
            <span class="spinner-border spinner-border-sm me-2" role="status"></span><span data-i18n="読み込み中...">読み込み中...</span>
          </div>
          <div v-else-if="recentRequests.length === 0" class="text-muted text-center py-4" data-i18n="まだ申請がありません。">まだ申請がありません。</div>
          <div v-else class="table-responsive">
            <table class="table table-bordered table-sm mb-0">
              <thead>
                <tr>
                  <th data-i18n="申請種別">申請種別</th>
                  <th class="text-nowrap" data-i18n="申請日">申請日</th>
                  <th data-i18n="コメント">コメント</th>
                  <th data-i18n="状態">状態</th>
                  <th data-i18n="承認者">承認者</th>
                  <th class="text-nowrap" data-i18n="操作">操作</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="req in recentRequests" :key="'recent-' + req.id">
                  <td class="text-nowrap">{{ typeLabel(req.type) }}</td>
                  <td class="text-nowrap">{{ formatDateTime(req.created_at) }}</td>
                  <td class="text-nowrap">
                    {{ Number(req.comment_count || 0) }}
                    <span v-if="Number(req.comment_count || 0) > 0 && Number(req.unread_comment || 0) > 0" class="badge bg-danger ms-1" data-i18n="未読">未読</span>
                  </td>
                  <td>
                    <span :class="['badge', statusBadgeClass(req.status)]">
                      <i :class="statusIcon(req.status)" class="me-1"></i>{{ statusLabel(req.status) }}
                    </span>
                  </td>
                  <td>{{ (req.status === 'approved' || req.status === 'completed') && req.approver_realname ? req.approver_realname : '-' }}</td>
                  <td>
                    <a :href="detailUrl(req.id)" class="btn btn-sm btn-outline-info" data-i18n="詳細">詳細</a>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div v-if="canApprove" class="col-md-12" :class="{'col-lg-6 col-xl-6': recentRequests.length > 0, 'col-lg-12 col-xl-12': recentRequests.length === 0 }">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center py-3">
          <div>
            <h5 class="card-title mb-0"><span data-i18n="承認待ち">承認待ち</span> <span v-if="!pendingLoading" class="text-muted small mb-0 mt-1" v-html="pendingCountMessage"></span></h5>
          </div>
          <a class="btn btn-sm btn-primary flex-shrink-0" :href="formIndexPendingUrl" data-i18n="もっと見る">もっと見る</a>
        </div>
        <div class="card-body pt-0">
          <div v-if="pendingLoading" class="text-center text-muted py-4">
            <span class="spinner-border spinner-border-sm me-2" role="status"></span><span data-i18n="読み込み中...">読み込み中...</span>
          </div>
          <div v-else-if="pendingRequests.length === 0" class="text-muted text-center py-4" data-i18n="承認待ちの申請はありません。">承認待ちの申請はありません。</div>
          <div v-else class="table-responsive">
            <table class="table table-bordered table-sm mb-0">
              <thead>
                <tr>
                  <th data-i18n="申請者">申請者</th>
                  <th data-i18n="申請種別">申請種別</th>
                  <th class="text-nowrap" data-i18n="申請日">申請日</th>
                  <th data-i18n="状態">状態</th>
                  <th class="text-nowrap" data-i18n="操作">操作</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="req in pendingRequests" :key="'pending-' + req.id">
                  <td class="text-nowrap">{{ req.user_realname || req.user_id || '-' }}</td>
                  <td class="text-nowrap">{{ typeLabel(req.type) }}</td>
                  <td class="text-nowrap">{{ formatDateTime(req.created_at) }}</td>
                  <td>
                    <span :class="['badge', statusBadgeClass(req.status)]">
                      <i :class="statusIcon(req.status)" class="me-1"></i>{{ statusLabel(req.status) }}
                    </span>
                  </td>
                  <td>
                    <a :href="detailUrl(req.id)" class="btn btn-sm btn-outline-info" data-i18n="詳細">詳細</a>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    </div>
  </div>
  <?php } ?>

  <div class="row g-6 mt-1">
    <div class="col-xl-6 col-md-12">
      <div class="card h-100">
        <div class="card-body pb-2 app-calendar-wrapper">
          <div id="calendar"></div>
        </div>
      </div>
    </div>
    <div class="col-xl-6 col-md-12">
      <div class="card h-100">
        <div class="card-body pb-2 app-calendar-wrapper">
          <div id="dayoff-calendar"></div>
        </div>
      </div>
    </div>
  </div>
  <?php if($_SESSION['group'] != '7' && $_SESSION['group'] != '6'){ ?>
  <div class="row g-6 mt-1">
    <div class="col-xl-12 col-md-12">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between">
          <h5 class="card-title mb-0" data-i18n="お知らせ">お知らせ</h5>
          <div class="d-flex justify-content-end">
            <a class="btn btn-sm btn-primary" href="<?=$root?>forum/index.php" data-i18n="もっと見る">もっと見る</a>
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
  <?php } ?>
 
 <?php if($_SESSION['authority'] == 'administrator' || $_SESSION['authority'] == 'manager' || $is_soumu_user) { ?>
  <div class="row g-6 mt-1">
    <div class="col-md-12 col-lg-12 col-xl-12">
      <div class="card">
        <div class="card-header d-flex justify-content-between">
          <h5 class="card-title mb-0" data-i18n="勤怠統計">勤怠統計</h5>
          <div class="d-flex justify-content-end align-items-center gap-2">
            <select class="form-select select2" id="timecard-statistic-select" data-placeholder="メンバーを選択">
              <option value="">すべて</option>
              <?php
              foreach ($memberList as $member) {
                echo '<option value="'.$member['id'].'">'.$member['name'].'</option>';
              }
              ?>
            </select>
            <?php if($_SESSION['authority'] == 'administrator' || $is_soumu_user) { ?>
              <button class="btn btn-primary text-nowrap flex-shrink-0" id="generate-statistic" data-i18n="更新">更新</button>
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
<?php if(false) { ?>
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
        <div class="card-header d-flex justify-content-between">
          <h5 class="card-title mb-0">案件統計</h5>
          <select class="form-select" id="project-stats-department" style="width: 200px;">
            <option value="">すべての部署</option>
            <!-- Options will be loaded dynamically -->
          </select>
        </div>
        <div class="card-body">
          <!-- Monthly Stats Chart -->
          <div class="row g-4">
            <div class="col-md-6">
              <div class="card border">
                <div class="card-header">
                  <h5 class="card-title mb-0">月別案件統計</h5>
                </div>
                <div class="card-body">
                  <div id="monthly-stats-chart"></div>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="card border">
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
      </div>
    </div>
  </div>

 
</div>
<?php } ?>
	
</div>
<!-- / Content -->
<?php
// top.js load sau FullCalendar (cuối file); bỏ auto-include từ View::heading()
$view->javascript = preg_replace('#<script[^>]*assets/js/top\.js[^>]*></script>\s*#', '', $view->javascript);
$view->footing();
?>
<script src="<?=$root?>assets/vendor/libs/apex-charts/apexcharts.js"></script>
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/fullcalendar/fullcalendar.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/css/pages/app-calendar.css" />
<script src="<?=ROOT?>assets/vendor/libs/fullcalendar/fullcalendar.js"></script>
<script>
  window.DAYOFF_API_URL = 'https://group.caily.com.vn/api/index.php?type=get_dayoff_all_api&debug=1';
</script>
<script src="<?=ROOT?>assets/js/dayoff-events.js?v=<?=CACHE_VERSION?>"></script>
<script src="<?=ROOT?>assets/js/top.js?v=<?=CACHE_VERSION?>"></script>
<script>
  window.DASHBOARD_FORM_ROOT = <?= json_encode($root . 'form/') ?>;
  window.DASHBOARD_CAN_APPROVE = <?= $can_approve_requests ? 'true' : 'false' ?>;
  window.DASHBOARD_IS_REQUEST_ADMIN = <?= $is_request_admin ? 'true' : 'false' ?>;
</script>
<script src="<?=ROOT?>assets/js/dashboard-requests.js?v=<?=CACHE_VERSION?>"></script>