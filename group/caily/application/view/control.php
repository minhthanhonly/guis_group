<?php

if ($_SESSION['realname']) {
	$realname = $this->escape($_SESSION['realname']).'さん';
}
if ($this->authorize('administrator', 'manager')) {
	$administration = '<a'.$current['administration'].' href="'.$root.'administration.php">管理画面</a>';
}
?>
<div class="header">
	<div class="headerset clearFix">
		<div class="headertitle">
			<a href="<?=$root?>index.php"><img src="<?=$root?>images/title.gif" /></a>
		</div>
		<div class="headerright">
			<a href="<?=$root?>index.php"><?=$realname?></a><?=$administration?>
			<a href="<?=$root?>logout.php">ログアウト</a>
		</div>
	</div>
	<div class="control">
		<table cellspacing="0" border="0"><tr>
			<td<?=$current['top']?>><a href="<?=$root?>index.php">トップ</a></td>
			<td<?=$current['timecard']?>><a href="<?=$root?>timecard/">タイムカード</a></td>
			<td<?=$current['schedule']?>><a href="<?=$root?>schedule/">スケジュール</a></td>
			<td<?=$current['forum']?>><a href="<?=$root?>forum/">フォーラム</a></td>
			<td<?=$current['storage']?>><a href="<?=$root?>storage/">ファイル</a></td>
			<td<?=$current['addressbook']?>><a href="<?=$root?>addressbook/">アドレス帳</a></td>
			<td<?=$current['member']?>><a href="<?=$root?>member/">メンバー</a></td>
		</tr></table>
	</div>
</div>
<div class="wrap">
<div class="container">