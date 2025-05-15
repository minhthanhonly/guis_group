<?php


require_once('../application/loader.php');
$view->heading('グループ設定', 'administration');
$pagination = new Pagination;
?>

<!-- Content -->
<div class="container-xxl flex-grow-1 container-p-y">
	
	<div class="card" id="option-block">
		<div class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-6">
				<h4 class="card-title mb-0"><span id="timecard_title">グループ設定</h4>
            </div>
			<div class="col-md-6 d-flex justify-content-end gap-4">
				<div><a href="add.php" class="btn btn-primary">グループ追加</a></div>
			</div>
        </div>
        <div class="card-body">
			<table class="table table-bordered table-striped mt-12">
				<tr><th><?=$pagination->sortby('group_name', 'グループ名')?></th>
				<th><?=$pagination->sortby('group_order', '順序')?></th>
				<th class="listlink">&nbsp;</th></tr>
			<?php
			if (is_array($hash['list']) && count($hash['list']) > 0) {
				foreach ($hash['list'] as $row) {
			?>
				<tr><td><a href="view.php?id=<?=$row['id']?>"><?=$row['group_name']?></a>&nbsp;</td>
				<td><?=$row['group_order']?>&nbsp;</td>
				<td><a href="edit.php?id=<?=$row['id']?>" class="btn btn-primary"> 編集 </a></td></tr>
			<?php
				}
			}
			?>
			</table>
		</div>
    </div>
</div>
<!-- / Content -->
<?php
$view->footing();
?>