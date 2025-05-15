<?php

require_once('../application/loader.php');
$view->heading('タイムカード');

?>
<!-- Content -->
<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card mb-6">
        <!-- <div class="card-header d-flex justify-content-between">
          <h5 class="card-title mb-0">時間合計</h5>
         
        </div> -->
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
		<div class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-3">
				<h4 class="card-title mb-0" d><span id="timecard_title"><?= $_SESSION['realname'] ?></span><br><small
            id="timecard_type" class="badge bg-label-info fs-6"></small></h4>
      </div>
      <div class="col-md-6 justify-content-center row">
        <?php if ($_SESSION['authority'] == 'administrator' || $_SESSION['authority'] == 'manager') { ?>
          <div class="col-md-4">
            <label for="selectpickerGroup" class="col-md-2 col-form-label">グループ</label>
            <div class="col-md-10">
              <select class="selectpicker w-100 show-tick" id="selectpickerGroup"
                data-current-group="<?= $_SESSION['group'] ?>" data-icon-base="icon-base ti" data-tick-icon="tabler-check"
                data-style="btn-default">
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <label for="selectpickerUser" class="col-md-2 col-form-label">ユーザー</label>
            <div class="col-md-10">
              <select class="selectpicker w-100 show-tick" id="selectpickerUser"
                data-current-user="<?= $_SESSION['userid'] ?>" data-icon-base="icon-base ti" data-tick-icon="tabler-check"
                data-style="btn-default">
              </select>
            </div>
          </div>
        <?php } ?>
        <div class="col-md-4">
          <label for="timecard-month-input" class="col-md-2 col-form-label">年月</label>
          <div class="col-md-10">
            <input class="form-control" type="text" value="" id="timecard-month-input">
          </div>
        </div>
      </div>
      <div class="col-md-3 d-flex justify-content-end gap-2">
        <button class="btn btn-primary rounded-2 waves-effect waves-light" type="button" data-recalculation>
          <span><i class="icon-base ti tabler-calculator me-0 me-sm-1 icon-16px"></i><span
              class="d-none d-sm-inline-block">再計算</span></span>
        </button>
        <a href="csv.php?year=2025&month=5&userid=<?= $_SESSION['userid'] ?>" class="btn btn-success rounded-2 waves-effect waves-light" type="button" data-export-csv>
          <span><i class="icon-base ti tabler-file-spreadsheet me-0 me-sm-1 icon-16px"></i><span
              class="d-none d-sm-inline-block">CSV出力</span></span>
        </a>
        <!-- <a href="javascript:void(0);" class="btn btn-success rounded-2 waves-effect waves-light" type="button" data-export-excel>
          <span><i class="icon-base ti tabler-file-spreadsheet me-0 me-sm-1 icon-16px"></i><span
              class="d-none d-sm-inline-block">Excel出力</span></span>
        </a> -->
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
</div>
<!-- Modal view timecard -->
<div class="modal fade" id="modalViewTimecard" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-simple modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
        <div class="text-center mb-6">
          <h4 class="modal-title" id="modalViewTimecardTitle"></h4>
        </div>
        <form class="pt-0" id="viewTimecardForm">
          <input type="hidden" id="viewTimecardId" name="id" />
          <div class="form-group row">
            <div class="col-md-12 mb-4 form-control-validation">
              <label class="form-label" for="viewTimecardDate">日付</label>
              <input type="text" class="form-control" id="viewTimecardDate" placeholder="" name="date" readonly/>
            </div>
          </div>

          <div class="form-group row">
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="viewTimecardOpen">チェックイン</label>
              <input type="text" class="form-control" id="viewTimecardOpen" placeholder="" name="open" readonly/>
              <p class="mt-2" id="viewTimecardOriginOpenText">編集前の時刻: <span id="viewTimecardOriginOpen"></span></p>
            </div>
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="viewTimecardClose">チェックアウト</label>
              <input type="text" class="form-control" id="viewTimecardClose" placeholder="" name="close" readonly/>
              <p class="mt-2" id="viewTimecardOriginalCloseText">編集前の時刻: <span id="viewTimecardOriginalClose"></span></p>
            </div>
          </div>
          <div class="form-group row">
            <div class="col-md-12 mb-4 form-control-validation">
              <label class="form-label" for="viewTimecardNote">備考</label>
              <textarea class="form-control" id="viewTimecardNote" placeholder="" name="note" readonly></textarea>
            </div>
          </div>
          <button type="button" class="btn btn-primary me-3 data-submit" data-bs-dismiss="modal">閉じる</button>
          <div class="form-group row mt-4">
            <div class="col-md-12 form-control-validation">
              <p class="text-info" id="viewTimecardLastEdit">最終編集: <span id="viewTimecardLastEditTime"></span></p>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal edit timecard -->
<div class="modal fade" id="modalEditTimecard" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-simple modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
        <div class="text-center mb-6">
          <h4 class="modal-title" id="modalEditTimecardTitle"></h4>
        </div>
        <form class="pt-0" id="editTimecardForm">
          <input type="hidden" id="editTimecardId" name="id" />
          <input type="hidden" id="editTimecardUserid" name="userid" />
          <div class="form-group row">
            <div class="col-md-12 mb-4 form-control-validation">
              <label class="form-label" for="editTimecardDate">日付</label>
              <input type="text" class="form-control" id="editTimecardDate" placeholder="" name="date" readonly/>
            </div>
          </div>

          <div class="form-group row">
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="editTimecardOpen">チェックイン</label>
              <div class="input-group timecard-time-input border rounded-2">
                <input type="text" class="form-control timecard-time-input-field d-none" id="editTimecardOpen" placeholder="" name="timecard_open" readonly />
              </div>
            </div>
            <div class="col-md-6 mb-4 form-control-validation">
              <label class="form-label" for="editTimecardClose">チェックアウト</label>
              <div class="input-group timecard-time-input border rounded-2">
                <input type="text" class="form-control timecard-time-input-field d-none" id="editTimecardClose" placeholder="" name="timecard_close" readonly />
              </div>
            </div>
          </div>
          <div class="form-group row">
            <div class="col-md-12 mb-4 form-control-validation">
              <label class="form-label" for="editTimecardNote">備考</label>
              <textarea class="form-control" id="editTimecardNote" placeholder="" name="timecard_comment"></textarea>
            </div>
          </div>
          <button type="submit" class="btn btn-primary me-3 data-submit">更新</button>
          <button type="button" class="btn btn-secondary data-submit" data-bs-dismiss="modal">キャンセル</button>
        </form>
      </div>
    </div>
  </div>
</div>
<!-- / Content -->

<!-- Modal edit timecard -->
<div class="modal fade" id="modalEditTimecardNote" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-simple modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
        <div class="text-center mb-6">
          <h4 class="modal-title" id="modalEditTimecardNoteTitle"></h4>
        </div>
        <form class="pt-0" id="editTimecardNoteForm">
          <input type="hidden" id="editTimecardNoteId" name="id" />
          <input type="hidden" id="editTimecardNoteUserid" name="userid" />
          <div class="form-group row">
            <div class="col-md-12 mb-4 form-control-validation">
              <label class="form-label" for="editTimecardNoteDate">日付</label>
              <input type="text" class="form-control" id="editTimecardNoteDate" placeholder="" name="date" readonly/>
            </div>
          </div>
          <div class="form-group row">
            <div class="col-md-12 mb-4 form-control-validation">
              <label class="form-label" for="editTimecardNoteNote">備考</label>
              <textarea class="form-control" id="editTimecardNoteNote" placeholder="" name="timecard_comment"></textarea>
            </div>
          </div>
          <button type="submit" class="btn btn-primary me-3 data-submit">送信</button>
          <button type="button" class="btn btn-secondary data-submit" data-bs-dismiss="modal">キャンセル</button>
        </form>
      </div>
    </div>
  </div>
</div>
<!-- / Content -->
<?php
$view->footing();
?>

<script src="<?=ROOT?>assets/js/timecard.js?v=<?=CACHE_VERSION?>"></script>