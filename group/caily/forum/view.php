<?php


require_once('../application/loader.php');
$view->heading($hash['parent']['forum_title']);
$pagination = new Pagination;
$post_id = $_GET['id'];
$pagination->parameter['id'] = intval($_GET['id']);
if ($hash['parent']['public_level'] == 2) {
	$statuspublic = $view->permitlist($hash['parent'], 'public');
} else {
	$statuspublic = '公開';
}
?>
<script type="text/javascript">
	document.addEventListener('DOMContentLoaded', function() {
		$('.reply').click(function(){
	     var closestDiv = $(this).closest('.forum');
	     $('.cmt_reply').not(closestDiv.next('.cmt_reply')).hide();
	     closestDiv.next('.cmt_reply').slideToggle(100);
	 });
});
</script>
<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card" id="option-block">
		<div
			class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-8">
				<h4 class="card-title mb-0">
					<span><?=$hash['parent']['forum_title']?></span></h4>
			</div>
			<div class="col-md-4 d-flex justify-content-end gap-4">
				
				<ul class="operate d-flex gap-2 list-unstyled justify-content-end">
					<li><a class="btn btn-info" href="index.php<?=$view->positive(array('folder'=>$hash['parent']['folder_id']))?>">一覧に戻る</a></li>
					<?php
					if ($hash['parent']['owner'] == $_SESSION['userid'] || $_SESSION['authority'] == 'administrator' || $_SESSION['authority'] == 'manager') {
						echo '<li><a class="btn btn-primary" href="edit.php?id='.$hash['parent']['id'].'">編集</a></li>';
					}
					?>
				</ul>
			</div>
		</div>
		<div class="card-body p-0">
			<div class="container">
				<div class="content">
					<div class="forum p-4">
							<div class="forumproperty d-flex gap-4 justify-content-end text-secondary">
							<span>投稿者：<?=$hash['parent']['forum_name']?></span>
							<span><?=date('Y/m/d H:i:s', strtotime($hash['parent']['forum_date']))?></span>
							<span>公開設定：<?=$statuspublic?></span>
						</div>
						<div class="forumcontent mt-4 pt-8">
							<div class="ql-editor"><?=html_entity_decode(nl2br($hash['parent']['forum_comment']))?></div>
							<?=$view->attachment($hash['parent']['id'], 'forum', $hash['parent']['owner'].'_'.strtotime($hash['parent']['forum_date']), $hash['parent']['forum_file'])?>
						</div>
					</div>
				<?php
				if (is_array($hash['list']) && count($hash['list']) > 0) {
					echo '<hr>';
					echo '<h5>コメント ('.count($hash['list']).')</h2>';
					foreach ($hash['list'] as $row) {
						if ($row['owner'] == $_SESSION['userid']) {
							$edit = '<a href="edit.php?id='.$row['id'].'&post_id='.$post_id.'" class="btn btn-primary btn-sm">編集</a>';
						} else {
							$edit = '';
						}
						$user_image = ROOT.'assets/img/avatars/1.png';
						if (isset($hash['user_image'][$row['owner']]) && $hash['user_image'][$row['owner']] != '') {
							$user_image = ROOT.'assets/upload/avatar/'.$hash['user_image'][$row['owner']];
						}
				?>
				<div class="border mb-8">
					<div class="forum">
						<div class="d-flex position-relative">
							<div class="avatar m-2">
								<img src="<?=$user_image?>" alt="<?=$row['forum_name']?>" class="rounded-circle">
							</div>
							<div class="d-flex flex-column">
								<div class="forumtitle p-2">
									<span class="fw-bold pe-4 text-primary"><?=$row['forum_name']?></span>
									<p class="p-0 m-0 text-secondary fs-small"><?=date('Y/m/d H:i:s', strtotime($row['forum_date']))?></p>
									<div class="d-flex gap-2 position-absolute top-0 end-0 p-2">
										<button type="button" class="reply btn btn-info btn-sm">返信</button>
										<?=$edit?>
									</div>
								</div>
							</div>
						</div>
						<hr class="mt-0">
						<div class="forumcontent px-4 pb-4">
							<div class="ql-editor"><?=html_entity_decode(nl2br($row['forum_comment']))?></div>
							<?=$view->attachment($row['id'], 'forum', $row['owner'].'_'.strtotime($row['forum_date']), $row['forum_file'])?>
						</div>

						<!-- view reply comment -->
					<?php
					foreach ($row['comment'] as $key => $value) { 
						if ($value['owner'] == $_SESSION['userid']) {
							$edit = '<a href="edit.php?id='.$value['id'].'&post_id='.$post_id.'" class="btn btn-label-primary btn-sm">編集</a>';
						} else {
							$edit = ''; }
						$user_image_reply = ROOT.'assets/img/avatars/1.png';
						if (isset($hash['user_image'][$value['owner']]) && $hash['user_image'][$value['owner']] != '') {
							$user_image_reply = ROOT.'assets/upload/avatar/'.$hash['user_image'][$value['owner']];
						}
						?>
						<hr class="mt-0">
						<div class="forum reply_comment ps-12">
							<div class="d-flex gap-2">
								<div class="avatar me-2">
									<img src="<?=$user_image_reply?>" alt="<?=$value['forum_name']?>" class="rounded-circle">
								</div>
								<div class="d-flex flex-column pb-2 position-relative">
									<div class="forumproperty"><?= '<span class="text-primary">' . $value['forum_name'] . '</span><br><span class="text-secondary fs-small">' . date('Y/m/d H:i:s', strtotime($value['forum_date'])) . '</span>'?></div>
									<div class="forumcontent mt-2"><div class="ql-editor"><?= html_entity_decode(nl2br($value['forum_comment'])) ?></div></div>
									<div class="mt-2"><?=$edit?></div>
								</div>
							</div>
						</div>
					<?php } ?>
						
					</div>
					<!-- form reply comment -->
					 <div class="cmt_reply">
						<form method="post" action="commentreply.php" enctype="multipart/form-data" >
							<hr>
							<div class="ps-12 pb-4 pe-4">
								<div class="custom_editor">
									<div class="custom_editor_content"></div>
									<textarea name="forum_comment" class="custom_editor_textarea form-control" rows="4"></textarea>
								</div>
								<div class="cmt_submit mt-2">
									<button type="submit" class="btn btn-primary">登録</button>
								</div>
								<input type="hidden" name="forum_parent" value="<?=$row['id']?>" />
								<input type="hidden" name="id_reply" value="<?=$hash['parent']['id']?>" />
							</div>
						</form>
					</div>
				</div>
				<?php
				}
					//echo $view->pagination($pagination, $hash['count']);
				}
				?>
					<hr>
					<h3>コメント追加</h3>
					<form class="pb-12" method="post" action="commentadd.php" enctype="multipart/form-data">
						<table class="w-100" cellspacing="0">
							<tr>
								<td>
									<div class="custom_editor">
										<div class="custom_editor_content"></div>
										<textarea name="forum_comment" class="custom_editor_textarea form-control" rows="4"></textarea>
									</div>
								</td>
							</tr>
							<tr><td class="py-4"><?=$view->uploadfile()?></td></tr>
						</table>
						<div class="cmt_submit">
							<button type="submit" class="btn btn-primary">登録</button>
						</div>
						<input type="hidden" name="forum_parent" value="<?=$hash['parent']['id']?>" />
					</form>
				</div>
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
    