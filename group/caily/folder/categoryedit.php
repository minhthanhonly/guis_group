<?php

require_once('../application/loader.php');
$view->heading('カテゴリ編集', $hash['data']['folder_type']);
?>
<h1>カテゴリ編集</h1>
<ul class="operate">
	<li><a href="category.php?type=<?=$hash['data']['folder_type']?>">カテゴリ一覧に戻る</a></li>
	<li><a href="categorydelete.php?type=<?=$hash['data']['folder_type']?>&id=<?=$hash['data']['id']?>">削除</a></li>
</ul>
<form class="content" method="post" action="">
	<?=$view->error($hash['error'])?>
	<table class="form" cellspacing="0">
		<tr><th>カテゴリ名<span class="necessary">(必須)</span></th><td><input type="text" name="folder_caption" class="inputtitle" value="<?=$hash['data']['folder_caption']?>" /></td></tr>
		<tr><th>順序</th><td><input type="text" name="folder_order" class="inputnumeric" value="<?=$hash['data']['folder_order']?>" /></td></tr>
		<tr><th>書き込み権限<?=$view->explain('add')?></th><td><?=$view->permit($hash['data'], 'add', array('許可', '登録者のみ', '許可するグループ・ユーザーを設定'))?></td></tr>
		<tr><th>公開設定<?=$view->explain('categorypublic')?></th><td><?=$view->permit($hash['data'], 'public', array(0=>'公開', 2=>'公開するグループ・ユーザーを設定'))?></td></tr>
		<tr><th>編集設定<?=$view->explain('categoryedit')?></th><td><?=$view->permit($hash['data'], 'edit')?></td></tr>
	</table>
	<div class="submit">
		<input type="submit" value="　編集　" />&nbsp;
		<input type="button" value="キャンセル" onclick="location.href='category.php?type=<?=$hash['data']['folder_type']?>'" />
	</div>
	<input type="hidden" name="id" value="<?=$hash['data']['id']?>" />
</form>
<?php
$view->footing();
?>