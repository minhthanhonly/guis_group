<?php

require_once('../application/loader.php');
$view->heading('カテゴリ削除', $hash['data']['folder_type']);
$add = array('許可', '登録者のみ');
if ($hash['data']['add_level'] == 2) {
	$add[2] = $view->permitlist($hash['data'], 'add');
}
?>
<h1></h1>

<!-- Content -->
<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card" id="option-block">
		<div class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-6">
				<h4 class="card-title mb-0"><span id="timecard_title">カテゴリ削除</h4>
            </div>
			<div class="col-md-6 d-flex justify-content-end gap-4">
				<div><a href="category.php?type=<?=$hash['data']['folder_type']?>" class="btn btn-info">一覧に戻る</a></div>
			</div>
        </div>
		<div class="card-body">

<form class="content mt-12" method="post" action="">
	<?=$view->error($hash['error'], '下記のカテゴリを削除します。')?>
	<table class="table table-bordered table-striped  mb-12">
		<tr><th class="w-25">カテゴリ名</th><td><?=$hash['data']['folder_caption']?>&nbsp;</td></tr>
		<tr><th class="w-25">名前</th><td><?=$hash['data']['folder_name']?>&nbsp;</td></tr>
		<tr><th class="w-25">日時</th><td><?=date('Y/m/d H:i:s', strtotime($hash['data']['folder_date']))?>&nbsp;</td></tr>
		<tr><th class="w-25">順序</th><td><?=$hash['data']['folder_order']?>&nbsp;</td></tr>
		<tr><th class="w-25">書き込み権限</th><td><?=$add[$hash['data']['add_level']]?>&nbsp;</td></tr>
	</table>
	<?=$view->property($hash['data'])?>
	<div class="submit mt-4">
		<button type="submit" class="btn btn-danger">削除</button>&nbsp;
		<a href="category.php?type=<?=$hash['data']['folder_type']?>" class="btn btn-secondary">キャンセル</a>
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