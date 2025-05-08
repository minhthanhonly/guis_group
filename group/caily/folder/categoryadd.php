<?php

require_once('../application/loader.php');
$view->heading('カテゴリ追加', $_GET['type']);
$hash['data']['folder_type'] = $view->initialize($hash['data']['folder_type'], $_GET['type']);
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
			</div>
        </div>
		<div class="card-body">
<form class="content mt-12 mb-12" method="post" action="">
	<?=$view->error($hash['error'])?>
	<div class="mb-3 row">
		<label class="col-sm-3 col-form-label">カテゴリ名<span class="text-danger">(必須)</span></label>
		<div class="col-sm-6">
			<input type="text" name="folder_caption" class="form-control" value="<?=$hash['data']['folder_caption']?>" />
		</div>
	</div>
	<div class="mb-3 row">
		<label class="col-sm-3 col-form-label">順序</label>
		<div class="col-sm-6">
			<input type="text" name="folder_order" class="form-control" value="<?=$hash['data']['folder_order']?>" />
		</div>
	</div>
	<div class="mb-3 row">
		<label class="col-sm-3 col-form-label">書き込み権限<?=$view->explain('add')?></label>
		<div class="col-sm-6">
			<?=$view->permit($hash['data'], 'add', array('許可', '登録者のみ', '許可するグループ・ユーザーを設定'))?>
		</div>
	</div>
	<div class="mb-3 row">
		<label class="col-sm-3 col-form-label">公開設定<?=$view->explain('categorypublic')?></label>
		<div class="col-sm-6">
			<?=$view->permit($hash['data'], 'public', array(0=>'公開', 2=>'公開するグループ・ユーザーを設定'))?>
		</div>
	</div>
	<div class="mb-3 row">
		<label class="col-sm-3 col-form-label">編集設定<?=$view->explain('categoryedit')?></label>
		<div class="col-sm-6">
			<?=$view->permit($hash['data'], 'edit')?>
		</div>
	</div>
	<div class="submit">
		<button type="submit" class="btn btn-primary">追加</button>&nbsp;
		<a href="category.php?type=<?=$hash['data']['folder_type']?>" class="btn btn-secondary">キャンセル</a>
	</div>
	<input type="hidden" name="folder_type" value="<?=$hash['data']['folder_type']?>" />
</form>
</div>
</div>
</div>
<!-- / Content -->
<?php
$view->footing();
?>