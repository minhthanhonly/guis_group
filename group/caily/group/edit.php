<?php

require_once('../application/loader.php');
$view->heading('グループ編集', 'administration');
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
				<div><a href="delete.php?id=<?=$hash['data']['id']?>" class="btn btn-danger">削除</a></div>
			</div>
        </div>
		<div class="card-body">

<form class="content" method="post" action="">
	<?=$view->error($hash['error'])?>
	<div class="table-responsive mt-12 mb-12">
		<table class="table table-bordered">
			<tbody>
				<tr>
					<th class="w-25">グループ名<span class="badge bg-label-danger mx-1">必須</span></th>
					<td><input type="text" name="group_name" class="form-control" value="<?=$hash['data']['group_name']?>" /></td>
				</tr>
				<tr>
					<th class="w-25">順序</th>
					<td><input type="text" name="group_order" class="form-control" value="<?=$hash['data']['group_order']?>" /></td>
				</tr>
				<tr>
					<th class="w-25">権限<?=$view->explain('groupadd')?></th>
					<td><?=$view->permit($hash['data'], 'add')?></td>
				</tr>
				<tr>
					<th class="w-25">編集<?=$view->explain('groupedit')?></th>
					<td><?=$view->permit($hash['data'], 'edit')?></td>
				</tr>
			</tbody>
		</table>
	</div>
	<div class="submit">
		<button type="submit" value="編集" class="btn btn-primary" ><i class="icon-base ti tabler-plus"></i>編集</button>&nbsp;
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