<?php

require_once('../application/loader.php');
$view->heading('スケジュール');
$calendar = new Calendar;
$data = $calendar->prepare($hash['list'], $_GET['year'], $_GET['month'], 1, $_GET['year'], $_GET['month'], date('t', mktime(0, 0, 0, $_GET['month'], 1, $_GET['year'])));
$timestamp = mktime(0, 0, 0, $_GET['month'], 1, $_GET['year']);
$previous = mktime(0, 0, 0, $_GET['month']-1, 1, $_GET['year']);
$next = mktime(0, 0, 0, $_GET['month']+1, 1, $_GET['year']);
if (strlen($hash['owner']['realname']) > 0 && (isset($_GET['member']) || $hash['owner']['userid'] != $_SESSION['userid'])) {
    $caption = ' - '.$hash['owner']['realname'];
}
?>
<!-- Content -->
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="card app-calendar-wrapper">
    <div class="row g-0">
      <!-- Calendar Sidebar -->
      <div class="col app-calendar-sidebar border-end" id="app-calendar-sidebar">
        <div class="border-bottom p-6 my-sm-0 mb-4">
          <button class="btn btn-primary btn-toggle-sidebar w-100" data-bs-toggle="offcanvas" data-bs-target="#addEventSidebar" aria-controls="addEventSidebar">
            <i class="icon-base ti tabler-plus icon-16px me-2"></i>
            <span class="align-middle">予定追加</span>
          </button>
        </div>
        <!-- <div class="px-3 pt-2">
          
        </div> -->
        <!-- <hr class="mb-6 mx-n4 mt-3" /> -->
        <div class="px-6 pb-2 pt-3">
          <!-- Filter -->
          <div>
            <h5>予定のフィルター</h5>
          </div>

          <div class="form-check form-check-secondary mb-5 ms-2">
            <input class="form-check-input select-all" type="checkbox" id="selectAll" data-value="all" checked />
            <label class="form-check-label" for="selectAll">全て表示</label>
          </div>

          <div class="app-calendar-events-filter text-heading">
            
            <div class="form-check form-check-primary mb-5 ms-2">
              <input class="form-check-input input-filter" type="checkbox" id="select-business" data-value="仕事" checked />
              <label class="form-check-label" for="select-business">仕事</label>
            </div>
            <div class="form-check form-check-warning mb-5 ms-2">
              <input class="form-check-input input-filter" type="checkbox" id="select-off" data-value="勤怠" checked />
              <label class="form-check-label" for="select-off">勤怠</label>
            </div>
            <div class="form-check form-check-danger mb-5 ms-2 d-none">
              <input class="form-check-input input-filter" type="checkbox" id="select-holiday" data-value="休日" checked />
              <label class="form-check-label" for="select-holiday">休日</label>
            </div>
            <div class="form-check form-check-info mb-5 ms-2">
              <input class="form-check-input input-filter" type="checkbox" id="select-personal" data-value="個人" checked />
              <label class="form-check-label" for="select-personal">個人</label>
            </div>
            <div class="form-check form-check-success mb-5 ms-2">
              <input class="form-check-input input-filter" type="checkbox" id="select-etc" data-value="その他" checked />
              <label class="form-check-label" for="select-etc">その他</label>
            </div>
          </div>
        </div>
      </div>
      <!-- /Calendar Sidebar -->

      <!-- Calendar & Modal -->
      <div class="col app-calendar-content">
        <div class="card shadow-none border-0">
          <div class="card-body pb-0">
            <!-- FullCalendar -->
            <div id="calendar" class="schedule-calendar"></div>
          </div>
        </div>
        <div class="app-overlay"></div>
        <!-- FullCalendar Offcanvas -->
        <div class="offcanvas offcanvas-end event-sidebar" tabindex="-1" id="addEventSidebar" aria-labelledby="addEventSidebarLabel">
          <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title" id="addEventSidebarLabel">予定追加</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
          </div>
          <div class="offcanvas-body">
            <form class="event-form pt-0" id="eventForm" onsubmit="return false">
              <div class="mb-5 form-control-validation">
                <label class="form-label" for="eventTitle">タイトル</label>
                <input type="text" class="form-control" id="eventTitle" name="eventTitle" placeholder="タイトル" />
              </div>
              <div class="mb-5">
                <label class="form-label" for="eventLabel">予定の種類</label>
                <select class="select2 select-event-label form-select" id="eventLabel" name="eventLabel">
                  <option data-label="primary" value="仕事" selected>仕事</option>
                  <option data-label="warning" value="勤怠">勤怠</option>
                  <option data-label="info" value="個人">個人</option>
                  <option data-label="success" value="その他">その他</option>
                </select>
              </div>
              <div class="mb-5 form-control-validation" id="eventStartDateDiv">
                <label class="form-label" for="eventStartDate">開始日</label>
                <input type="text" class="form-control" id="eventStartDate" name="eventStartDate" placeholder="開始日" />
              </div>
              <div class="mb-5 form-control-validation" id="eventEndDateDiv">
                <label class="form-label" for="eventEndDate">終了日</label>
                <input type="text" class="form-control" id="eventEndDate" name="eventEndDate" placeholder="終了日" />
                <input type="text" class="form-control d-none" id="eventEndDateHidden" name="eventEndDateHidden"/>
              </div>
              <div class="mb-5 form-control-validation d-none" id="eventStartDateDiv2">
                <label class="form-label" for="eventStartDate2">開始日</label>
                <input type="text" class="form-control" id="eventStartDate2" name="eventStartDate" placeholder="開始日" />
              </div>
              <div class="mb-5 form-control-validation d-none" id="eventEndDateDiv2">
                <label class="form-label" for="eventEndDate2">終了日</label>
                <input type="text" class="form-control" id="eventEndDate2" name="eventEndDate" placeholder="終了日" />
              </div>
              <div class="mb-5">
                <div class="form-check form-switch">
                  <input type="checkbox" class="form-check-input allDay-switch" id="allDaySwitch" checked />
                  <label class="form-check-label" for="allDaySwitch">終日</label>
                </div>
              </div>
              
              <div class="mb-4">
                <label class="form-label" for="eventPublic">公開設定</label>
                <select class="form-select" id="eventPublic" name="eventPublic">
                  <option value="0">公開</option>
                  <option value="1">非公開</option>
                </select>
              </div>
              <div class="mb-5">
                <label class="form-label" for="eventComment">コメント</label>
                <textarea class="form-control" name="eventComment" id="eventComment"></textarea>
              </div>
              <div class="d-flex justify-content-sm-between justify-content-start mt-6 gap-2" id="eventBtn">
                <div class="d-flex">
                  <button type="submit" id="addEventBtn" class="btn btn-primary btn-add-event me-4">追加</button>
                  <button type="reset" class="btn btn-label-secondary btn-cancel me-sm-0 me-1" data-bs-dismiss="offcanvas">キャンセル</button>
                </div>
                <button class="btn btn-label-danger btn-delete-event d-none">削除</button>
              </div>
              <div class="mt-3">
                <p id="eventLastUpdate" class="text-muted d-none">最終更新： <span id="eventLastUpdateTime"></span></p>
              </div>
            </form>
          </div>
        </div>
      </div>
      <!-- /Calendar & Modal -->
    </div>
  </div>
</div>
<!-- / Content -->
<?php
$view->footing();
?>
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/fullcalendar/fullcalendar.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/css/pages/app-calendar.css" />
<script src="<?=ROOT?>assets/vendor/libs/fullcalendar/fullcalendar.js"></script>
<script src="<?=ROOT?>assets/js/app-calendar.js"></script>