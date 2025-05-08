<?php

require_once('../application/loader.php');
$view->heading('グループ削除', 'administration');
$add = array('許可', '登録者のみ');
if ($hash['data']['add_level'] == 2) {
	$add[2] = $view->permitlist($hash['data'], 'add');
}
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
<form class="content mt-12 mb-12" method="post" action="">
	<?=$view->error($hash['error'], '下記のグループを削除します。')?>
	<table class="table table-bordered table-striped mb-12">
		<tr><th class="w-25">グループ名</th><td><?=$hash['data']['group_name']?></td></tr>
		<tr><th class="w-25">順序</th><td><?=$hash['data']['group_order']?></td></tr>
		<tr><th class="w-25">権限</th><td><?=$add[$hash['data']['add_level']]?></td></tr>
	</table>
	<div class="submit">
		<button type="submit" class="btn btn-primary" ><i class="icon-base ti tabler-plus"></i>削除</button>&nbsp;
		<a href="index.php" class="btn btn-secondary">キャンセル</a>
	</div>
	<input type="hidden" name="id" value="<?=$hash['data']['id']?>" />
</form>
</div>
    </div>
</div>
<!-- / Content -->
<?php
$view->footing();
?>