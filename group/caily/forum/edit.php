<?php

require_once('../application/loader.php');
$post_id = $_GET['id'];
if ($hash['data']['forum_parent'] > 0) {
	$post_id = $_GET['post_id'];
	$redirect = 'view.php?id='.$post_id;
	$caption = 'スレッド';
} else {
	$redirect = 'view.php?id='.$post_id;
	$caption = 'コメント';
}
$view->heading($caption.'編集');
?>
<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card" id="option-block">
		<div
			class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-8">
				<h4 class="card-title mb-0"><span><?=$caption?>編集</h4>
			</div>
			<div class="col-md-4 d-flex justify-content-end gap-4">
				<ul class="operate d-flex gap-2 list-unstyled justify-content-end">
					<li><a class="btn btn-info" href="<?=$redirect?>">スレッドに戻る</a></li>
					<li><a class="btn btn-danger" href="delete.php?id=<?=$hash['data']['id']?>&post_id=<?=$post_id?>">削除</a></li>
				</ul>
			</div>
		</div>
		<div class="card-body">
			<div class="container py-12">
				<form class="content" method="post" action="" enctype="multipart/form-data">
				<?php
				echo $view->error($hash['error']);
				if ($hash['data']['forum_parent'] <= 0) {
					$hash['folder'] = array('&nbsp;') + $hash['folder'];
				?>
					<table class="form" cellspacing="0">
						<tr><th>タイトル<span class="badge bg-label-danger mx-1">必須</span></th><td class="py-2"><input type="text" name="forum_title" class="form-control" value="<?=$hash['data']['forum_title']?>" /></td></tr>
						<tr><th>内容<span class="badge bg-label-danger mx-1">必須</span></th>
							<td class="py-2">
								<div class="custom_editor">
									<div class="custom_editor_content"><?=html_entity_decode($hash['data']['forum_comment'])?></div>
									<textarea name="forum_comment" class="custom_editor_textarea form-control" rows="4"></textarea>
								</div>
							</td>
						</tr>
						<tr><th>&nbsp;</th><td class="py-2"><?=$view->uploadfile($hash['data']['forum_file'])?></td></tr>
						<tr><th>カテゴリ</th><td class="py-2"><?=$helper->selector('folder_id', $hash['folder'], $hash['data']['folder_id'])?></td></tr>
						<tr><th>公開設定<?=$view->explain('categorypublic')?></th><td class="py-2"><?=$view->permit($hash['data'], 'public', array(0=>'公開', 2=>'公開するグループ・ユーザーを設定'))?></td></tr>
					</table>
				<?php
				} else {
				?>
					<table class="form" cellspacing="0">
						<tr><th>内容<span class="badge bg-label-danger mx-1">必須</span></th>
						<td class="py-2">
							<div class="custom_editor">
								<div class="custom_editor_content"><?=html_entity_decode($hash['data']['forum_comment'])?></div>
								<textarea name="forum_comment" class="custom_editor_textarea form-control" rows="4"></textarea>
							</div>
							</td>
					</tr>
						<tr><th>&nbsp;</th><td class="py-2"><?=$view->uploadfile($hash['data']['forum_file'])?></td></tr>
					</table>
				<?php
				}
				?>
					<div class="submit mt-4">
						<button type="submit" class="btn btn-primary">更新</button>&nbsp;
						<a href='<?=$redirect?>' class="btn btn-secondary">キャンセル</a>
					</div>
					<input type="hidden" name="id" value="<?=$hash['data']['id']?>" />
					<input type="hidden" name="forum_parent" value="<?=$hash['data']['forum_parent']?>" />
				</form>
			</div>
		</div>
	</div>
</div>
<?php
$view->footing();
?>
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/quill/typography.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/highlight/highlight.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/quill/editor.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/quill/katex.css" />
<script src="<?=ROOT?>assets/vendor/libs/highlight/highlight.js"></script>
<script src="<?=ROOT?>assets/vendor/libs/quill/katex.js"></script>
<script src="<?=ROOT?>assets/vendor/libs/quill/quill.js"></script>
<script src="<?=ROOT?>assets/js/forms-editors.js"></script>