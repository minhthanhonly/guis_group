<?php
/*
 * Copyright(c) 2009 limitlink,Inc. All Rights Reserved.
 * http://limitlink.jp/
 * 文字コード UTF-8
 */
require_once('../application/loader.php');
$view->heading('タイムカード');
?>
<h1>タイムカード - Holiday</h1>
<ul class="operate">
	<li><a href="../administration.php">一覧に戻る</a></li>
	<li><a href="index.php<?=$view->positive(array('year'=>$_GET['year'], 'month'=>$_GET['month']))?>">タイムカードに戻る</a></li>
</ul>
<form class="content" method="post" action="">
	<?=$view->error($hash['error'])?>
	<table class="form" cellspacing="0">
		<tr><th>休日</th><td><textarea name="holidays" class="inputcomment" rows="20"><?=$hash['data']['holidays']?></textarea></td></tr>
	</table>
	<div class="submit">
		<input type="submit" value="　編集　" />
	</div>
</form>
<?php
$view->footing();
?>