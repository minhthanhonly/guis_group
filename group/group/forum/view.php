<?php
/*
 * Copyright(c) 2009 limitlink,Inc. All Rights Reserved.
 * http://limitlink.jp/
 * 文字コード UTF-8
 */

require_once('../application/loader.php');
$view->heading('フォーラム');
$pagination = new Pagination;
$post_id = $_GET['id'];
$pagination->parameter['id'] = intval($_GET['id']);
if ($hash['parent']['public_level'] == 2) {
	$statuspublic = $view->permitlist($hash['parent'], 'public');
} else {
	$statuspublic = '公開';
}
if ($hash['parent']['owner'] == $_SESSION['userid']) {
	$edit = '<a href="edit.php?id='.$hash['parent']['id'].'&post_id='.$post_id.'">編集</a>';
}
?>
<script type="text/javascript">
	$(document).ready(function(){
		/*$('.reply_comment').addClass('hidden');*/
	$('.reply').click(function(){
	     var closestDiv = $(this).closest('.forum');
	     /*var closestComment = closestDiv.find('.reply_comment');
	     closestComment.toggleClass('hidden');*/
	     $('.cmt_reply').not(closestDiv.next('.cmt_reply')).hide();
	     closestDiv.next('form.cmt_reply').slideToggle(100);
	 });
});
</script>
<h1>フォーラム</h1>
<ul class="operate">
	<li><a href="index.php<?=$view->positive(array('folder'=>$hash['parent']['folder_id']))?>">一覧に戻る</a></li>
</ul>
<div class="content">
	<div class="forum">
		<div class="forumtitle"><?=$hash['parent']['forum_title']?></div>
		<div class="forumedit"><?=$edit?></div>
		<div class="forumproperty">
			<span>投稿者：<?=$hash['parent']['forum_name']?></span>
			<span><?=date('Y/m/d H:i:s', strtotime($hash['parent']['forum_date']))?></span>
			<span>公開設定：<?=$statuspublic?></span>
		</div>
		<div class="forumcontent">
			<div><?=nl2br($hash['parent']['forum_comment'])?></div>
			<?=$view->attachment($hash['parent']['id'], 'forum', $hash['parent']['owner'].'_'.strtotime($hash['parent']['forum_date']), $hash['parent']['forum_file'])?>
		</div>
	</div>
<?php
if (is_array($hash['list']) && count($hash['list']) > 0) {
	echo '<h2>コメント</h2>';
	foreach ($hash['list'] as $row) {
		if ($row['owner'] == $_SESSION['userid']) {
			$edit = '<a href="edit.php?id='.$row['id'].'&post_id='.$post_id.'">編集</a>';
		} else {
			$edit = '';
		}
?>
	<div class="forum">
		<div class="forumtitle"><span style="padding-right: 10px;"><?=$row['forum_name']?></span><button type="button" class="reply">返信</button></div>
		<div class="forumedit"><?=$edit?></div>
		<div class="forumproperty"><?=date('Y/m/d H:i:s', strtotime($row['forum_date']))?></div>
		<div class="forumcontent">
			<div><?=nl2br($row['forum_comment'])?></div>
			<?=$view->attachment($row['id'], 'forum', $row['owner'].'_'.strtotime($row['forum_date']), $row['forum_file'])?>
		</div>
		<!-- view reply comment -->
	<?php
	foreach ($row['comment'] as $key => $value) { 
		if ($value['owner'] == $_SESSION['userid']) {
			$edit = '<a href="edit.php?id='.$value['id'].'&post_id='.$post_id.'">編集</a>';
		} else {
			$edit = ''; }
		?>
		<div class="forum reply_comment" style="padding-left: 100px; box-sizing: border-box;">
			<div class="forumtitle" style="background-color: #2299ff;">コメントへの返信</div>
			<div class="forumedit"><?=$edit?></div>
			<div class="forumproperty"><?= '<span>投稿者：' . $value['forum_name'] . '</span><span>' . date('Y/m/d H:i:s', strtotime($value['forum_date'])) . '</span>'?></div>
			<div class="forumcontent"><?= $value['forum_comment'] ?></div>
		</div>
	<?php } ?>

	</div>

	
	<!-- form reply comment -->
	<form method="post" class="cmt_reply" action="commentreply.php" enctype="multipart/form-data" >
		<textarea name="forum_comment" rows="7" style="width: 100%; padding: 10px; margin-top: -15px; margin-bottom: 5px; box-sizing: border-box; border: 1px solid #2299ff;" placeholder="Reply a comment..."></textarea>
			<div class="cmt_submit">
				<input type="submit" value="　追加　" style="padding: 5px; border-radius: 3px; background-color: #2299ff;" />
			</div>
		<input type="hidden" name="forum_parent" value="<?=$row['id']?>" />
		<input type="hidden" name="id_reply" value="<?=$hash['parent']['id']?>" />
	</form>

<?php
	}
	echo $view->pagination($pagination, $hash['count']);
}
?>
	<h2>コメント追加</h2>
	<form method="post" action="commentadd.php" enctype="multipart/form-data">
		<table class="form" cellspacing="0">
			<tr><td><textarea name="forum_comment" class="inputcomment" rows="20"></textarea></td></tr>
			<tr><td><?=$view->uploadfile()?></td></tr>
		</table>
		<div class="submit">
			<input type="submit" value="　追加　" />
		</div>
		<input type="hidden" name="forum_parent" value="<?=$hash['parent']['id']?>" />
	</form>
</div>
<?php
$view->footing();
?>