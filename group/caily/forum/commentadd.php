<?php

require_once('../application/loader.php');
$view->heading('コメント追加');
?>
<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card" id="option-block">
		<div
			class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-8">
				<h4 class="card-title mb-0"><span>コメント追加</h4>
			</div>
			<div class="col-md-4 d-flex justify-content-end gap-4">
				<ul class="operate d-flex gap-2 list-unstyled justify-content-end">
					<li><a class="btn btn-info" href="view.php?id=<?=$hash['data']['forum_parent']?>">スレッドに戻る</a></li>
				</ul>
			</div>
		</div>
		<div class="card-body">
			<div class="container py-12">
				<form class="content" method="post" action="" enctype="multipart/form-data">
					<?=$view->error($hash['error'])?>
					<table class="w-100" cellspacing="0">
						<tr>
							<td class="py-4">
								<div class="custom_editor">
									<div class="custom_editor_content"><?=html_entity_decode($hash['data']['forum_comment'])?></div>
									<textarea name="forum_comment" class="custom_editor_textarea form-control" rows="4"></textarea>
								</div>
							</td>
						</tr>
						<tr><td class="py-4"><?=$view->uploadfile($hash['data']['forum_file'])?></td></tr>
					</table>
					<div class="submit">
						<button type="submit" class="btn btn-primary">追加</button>&nbsp;
						<a href='view.php?id=<?=$hash['data']['forum_parent']?>'"  class="btn btn-secondary">キャンセル</a>
					</div>
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