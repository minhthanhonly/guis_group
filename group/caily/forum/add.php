<?php

require_once('../application/loader.php');
$view->heading('スレッド作成');
$hash['data']['folder_id'] = $view->initialize($hash['data']['folder_id'], intval($_REQUEST['folder']));
$hash['folder'] = array('&nbsp;') + $hash['folder'];
?>
<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card" id="option-block">
		<div
			class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-6">
				<h4 class="card-title mb-0">
					<span>スレッド作成</span></h4>
			</div>
			<div class="col-md-6">
				<div class="d-flex row">
					<div class="col-md-6">
						
					</div>
					<div class="col-md-6">
						<ul class="operate d-flex gap-2 list-unstyled justify-content-end">
							<li><a class="btn btn-info" href="index.php<?=$view->positive(array('folder'=>$_GET['folder']))?>">一覧に戻る</a></li>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<div class="card-body">
			<div class="container py-12">

				<form class="content" method="post" action="" enctype="multipart/form-data">
					<?=$view->error($hash['error'])?>
					<table class="form" cellspacing="0">
						<tr><th class="py-2">タイトル<span class="badge bg-label-danger mx-1">必須</span></th>
						<td class="py-2"><input type="text" name="forum_title" class="form-control" value="<?=$hash['data']['forum_title']?>" /></td></tr>
						<tr><th class="py-2">内容<span class="badge bg-label-danger mx-1">必須</span></th>
							<td class="">
								<div class="custom_editor">
									<div class="custom_editor_content"><?=html_entity_decode($hash['data']['forum_comment'])?></div>
									<textarea name="forum_comment" class="custom_editor_textarea form-control" rows="4"></textarea>
								</div>
							</td>
						</tr>
						<tr><th class="py-2">&nbsp;</th><td class=""><?=$view->uploadfile($hash['data']['forum_file'])?></td></tr>
						<tr><th class="py-2">カテゴリ</th><td class=""><?=$helper->selector('folder_id', $hash['folder'], $hash['data']['folder_id'])?></td></tr>
						<tr><th class="py-2">公開設定<?=$view->explain('categorypublic')?></th><td class=""><?=$view->permit($hash['data'], 'public', array(0=>'公開', 2=>'公開するグループ・ユーザーを設定'))?></td></tr>
					</table>
					<div class="submit mt-4">
						<button type="submit" class="btn btn-primary" ><i class="icon-base ti tabler-plus"></i>追加</button>&nbsp;
						<a href="index.php<?=$view->parameter(array('folder'=>$_GET['folder']))?>" class="btn btn-secondary">キャンセル</a>
					</div>
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