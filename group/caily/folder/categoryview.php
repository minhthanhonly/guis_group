<?php

require_once('../application/loader.php');
$view->heading('カテゴリ詳細', $hash['data']['folder_type']);
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
				<h4 class="card-title mb-0"><span id="timecard_title">カテゴリ詳細</h4>
            </div>
			<div class="col-md-6 d-flex justify-content-end gap-4">

				<div><a href="category.php?type=<?=$hash['data']['folder_type']?>" class="btn btn-info">一覧に戻る</a></div>
				<?php
				if ($view->permitted($hash['data'], 'edit')) {
					echo '<div><a href="categoryedit.php?id='.$hash['data']['id'].'" class="btn btn-primary">編集</a></div>';
					echo '<div><a href="categorydelete.php?id='.$hash['data']['id'].'" class="btn btn-danger">削除</a></div>';
				}
				?>
			</div>
        </div>
		<div class="card-body">

		<table class="table table-bordered table-striped mt-12 mb-12">
			<tr><th class="w-25">カテゴリ名</th><td><?=$hash['data']['folder_caption']?>&nbsp;</td></tr>
			<tr><th class="w-25">名前</th><td><?=$hash['data']['folder_name']?>&nbsp;</td></tr>
			<tr><th class="w-25">日時</th><td><?=date('Y/m/d H:i:s', strtotime($hash['data']['folder_date']))?>&nbsp;</td></tr>
			<tr><th class="w-25">順序</th><td><?=$hash['data']['folder_order']?>&nbsp;</td></tr>
			<tr><th class="w-25">書き込み権限</th><td><?=$add[$hash['data']['add_level']]?>&nbsp;</td></tr>
		</table>

<?php
$view->property($hash['data']);
?>

</div>
</div>
</div>
<!-- / Content -->
<?php

$view->footing();
?>