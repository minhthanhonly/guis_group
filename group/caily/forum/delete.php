<?php

require_once('../application/loader.php');
if ($hash['data']['forum_parent'] <= 0) {
	$redirect = 'view.php?id='.$hash['data']['id'];
	$caption = 'スレッド';
	if ($hash['data']['public_level'] == 2) {
		$statuspublic = $view->permitlist($hash['data'], 'public');
	} else {
		$statuspublic = '公開';
	}
} else {
	$redirect = 'view.php?id='.$hash['data']['forum_parent'];
	$caption = 'コメント';
}
$view->heading($caption.'削除');
?>
<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card" id="option-block">
		<div
			class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-8">
				<h4 class="card-title mb-0"><span><?=$caption?>削除</h4>
			</div>
			<div class="col-md-4 d-flex justify-content-end gap-4">
				<ul class="operate d-flex gap-2 list-unstyled justify-content-end">
					<li><a class="btn btn-info" href="<?=$redirect?>">スレッドに戻る</a></li>
				</ul>
			</div>
		</div>
		<div class="card-body">
			<div class="container py-12">

				<form class="content" method="post" action="">
				<?php
				echo $view->error($hash['error'], '下記の'.$caption.'を削除します。');
				if ($hash['data']['forum_parent'] <= 0) {
				?>
					<div class="forum">
						<div class="forumtitle"><?=$hash['data']['forum_title']?></div>
						<div class="forumproperty">
							<span>投稿者：<?=$hash['data']['forum_name']?></span>
							<span><?=date('Y/m/d H:i:s', strtotime($hash['data']['forum_date']))?></span>
							<span>公開設定：<?=$statuspublic?></span>
						</div>
						<hr>
						<div class="forumcontent">
							<div class="ql-editor"><?=html_entity_decode(nl2br($hash['data']['forum_comment']))?></div>
							<?=$view->attachment($hash['data']['id'], 'forum', $hash['data']['owner'].'_'.strtotime($hash['data']['forum_date']), $hash['data']['forum_file'])?>
						</div>
					</div>
				<?php
				} else {
				?>
					<div class="forum">
						<div class="forumtitle"><?=$hash['data']['forum_name']?></div>
						<div class="forumproperty"><?=date('Y/m/d H:i:s', strtotime($hash['data']['forum_date']))?></div>
						<hr>
						<div class="forumcontent">
							<div class="ql-editor"><?=html_entity_decode(nl2br($hash['data']['forum_comment']))?></div>
							<?=$view->attachment($hash['data']['id'], 'forum', $hash['data']['owner'].'_'.strtotime($hash['data']['forum_date']), $hash['data']['forum_file'])?>
						</div>
					</div>
				<?php
				}
				?>
					<div class="submit mt-8">
						<button type="submit" class="btn btn-danger">削除</button>&nbsp;
						<a href='<?=$redirect?>'" class="btn btn-secondary">キャンセル</a>
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