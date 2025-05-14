<?php

require_once('../application/loader.php');
$view->heading('ファイル編集');
?>

<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card" id="option-block">
		<div
			class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-6">
				<h4 class="card-title mb-0">
					<span>フォルダ編集</span></h4>
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
							<li><a class="btn btn-danger" href="delete.php?id=<?=$hash['data']['id']?>">削除</a></li>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<div class="card-body">
			<div class="container py-12">
				<form class="content" method="post" action="">
					<?=$view->error($hash['error'])?>
					<table class="form table table-bordered mb-4" cellspacing="0">
						<tr><th>フォルダ名<span class="badge bg-label-danger mx-1">必須</span></th><td><input type="text" name="storage_title" class="inputtitle form-control" value="<?=$hash['data']['storage_title']?>" /></td></tr>
						<tr><th>場所</th><td><?=$hash['folder']['storage_title']?></td></tr>
						<tr><th>書き込み権限<?=$view->explain('storageadd')?></th><td><?=$view->permit($hash['data'], 'add')?></td></tr>
						<tr><th>公開設定<?=$view->explain('public')?></th><td><?=$view->permit($hash['data'])?></td></tr>
						<tr><th>編集設定<?=$view->explain('edit')?></th><td><?=$view->permit($hash['data'], 'edit')?></td></tr>
					</table>
					<div class="submit mt-4">
						<button type="submit" class="btn btn-primary">編集</button>&nbsp;
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