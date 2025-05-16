<?php

require_once('../application/loader.php');
$view->heading('グループ追加', 'administration');
?>
<!-- Content -->
<div class="container-xxl flex-grow-1 container-p-y">
	
	<div class="card" id="option-block">
		<div class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-6">
				<h4 class="card-title mb-0"><span id="timecard_title">グループ追加</h4>
            </div>
			<div class="col-md-6 d-flex justify-content-end gap-4">
				<div><a href="index.php" class="btn btn-info">グループに戻る</a></div>
			</div>
        </div>
		<div class="card-body">

<form class="content mt-4" method="post" action="">
	<?=$view->error($hash['error'])?>
	<div class="container">
		<div class="row mb-3">
			<div class="col-md-3">
				<label >グループ名 (必須) <span class="necessary"></span></label>
			</div>
			<div class="col-md-9">
				<input type="text" name="group_name" class="inputvalue form-control" value="<?=$hash['data']['group_name']?>" />
			</div>
		</div>
		<div class="row mb-3">
			<div class="col-md-3">
				<label >順序</label>
			</div>
			<div class="col-md-9">
				<input type="text" name="group_order" class="inputnumeric form-control" value="<?=$hash['data']['group_order']?>" />
			</div>
		</div>
		<div class="row mb-3">
			<div class="col-md-3">
				<label >権限 <?=$view->explain('groupadd')?></label>
			</div>
			<div class="col-md-9">
				<?=$view->permit($hash['data'], 'add')?>
			</div>
		</div>
		<div class="row mb-3">
			<div class="col-md-3">
				<label >編集 <?=$view->explain('groupedit')?></label>
			</div>
			<div class="col-md-9">
				<?=$view->permit($hash['data'], 'edit')?>
			</div>
		</div>
	</div>
	<div class="submit">
		<button type="submit" value="追加" class="btn btn-primary" ><i class="icon-base ti tabler-plus"></i>&nbsp;追加</button>&nbsp;
		<a href="index.php" class="btn btn-secondary">&nbsp;キャンセル</a>
	</div>
</form>
</div>
    </div>
</div>
<!-- / Content -->
<?php
$view->footing();
?>