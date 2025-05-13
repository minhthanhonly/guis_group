<?php

require_once('../application/loader.php');
$view->heading('フォルダ削除');
$add = array('許可', '登録者のみ');
if ($hash['data']['add_level'] == 2) {
	$add[2] = $view->permitlist($hash['data'], 'add');
}
?>
<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card" id="option-block">
		<div
			class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-6">
				<h4 class="card-title mb-0">
					<span>フォルダ削除</span></h4>
			</div>
			<div class="col-md-6">
				<div class="d-flex row">
					<div class="col-md-6">
						<!-- <form method="post" class="searchform" action="<?=$_SERVER['SCRIPT_NAME']?><?=$view->positive(array('folder'=>$_GET['folder']))?>">
							<input type="text" name="search" id="search" class="inputsearch" value="<?=$view->escape($_REQUEST['search'])?>" /><input type="submit" value="検索" />
						</form> -->
					</div>
					<div class="col-md-6">
						<ul class="operate d-flex gap-2 list-unstyled justify-content-end">
							<li><a class="btn btn-info" href="index.php<?=$view->positive(array('folder'=>$hash['data']['storage_folder']))?>">一覧に戻る</a></li>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<div class="card-body">
			<div class="container py-12">
				<form class="content" method="post" action="">
					<?=$view->error($hash['error'], '下記のフォルダを削除します。<br />フォルダを削除するとフォルダ内のデータはすべて削除されます。')?>
					<table class="table table-bordered  mb-4" cellspacing="0">
						<tr><th>フォルダ名</th><td><?=$hash['data']['storage_title']?>&nbsp;</td></tr>
						<tr><th>場所</th><td><?=$hash['folder']['storage_title']?>&nbsp;</td></tr>
						<tr><th>名前</th><td><?=$hash['data']['storage_name']?>&nbsp;</td></tr>
						<tr><th>日時</th><td><?=date('Y/m/d H:i:s', strtotime($hash['data']['storage_date']))?>&nbsp;</td></tr>
						<tr><th>書き込み権限</th><td><?=$add[$hash['data']['add_level']]?>&nbsp;</td></tr>
					</table>
					<?=$view->property($hash['data'])?>
					<div class="submit mt-4">
						<button type="submit" class="btn btn-danger">削除</button>&nbsp;
						<a href="index.php<?=$view->positive(array('folder'=>$_GET['id']))?>" class="btn btn-secondary">キャンセル</a>
					</div>
					<input type="hidden" name="id" value="<?=$hash['data']['id']?>" />
				</form>
			</div>
		</div>
	</div>
</div>
<?php
$view->footing();
?>