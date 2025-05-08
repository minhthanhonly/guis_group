<?php

require_once('../application/loader.php');
$view->heading('グループ詳細', 'administration');
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
				<h4 class="card-title mb-0"><span id="timecard_title">グループ</h4>
            </div>
			<div class="col-md-6 d-flex justify-content-end gap-4">
				<div><a href="index.php" class="btn btn-info">グループに戻る</a></div>
				<?php if ($view->permitted($hash['data'], 'edit')) { ?>
				<div><a href="edit.php?id=<?=$hash['data']['id']?>" class="btn btn-primary">編集</a></div>
				<div><a href="delete.php?id=<?=$hash['data']['id']?>" class="btn btn-danger">削除</a></div>
				<?php } ?>
			</div>
        </div>
        <div class="card-body">
			<table class="table table-bordered table-striped mt-12 mb-12">
				<tr><th class="w-25">グループ名</th><td><?=$hash['data']['group_name']?></td></tr>
				<tr><th class="w-25">順序</th><td><?=$hash['data']['group_order']?></td></tr>
				<tr><th class="w-25">権限</th><td><?=$add[$hash['data']['add_level']]?></td></tr>
			</table>
			<?php $view->property($hash['data']); ?>
		</div>
    </div>
</div>
<!-- / Content -->
<?php

$view->footing();
?>