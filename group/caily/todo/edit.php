<?php

require_once('../application/loader.php');
$view->heading('ToDo編集');
if (strlen($hash['data']['todo_term']) > 0) {
	$timestamp = strtotime($hash['data']['todo_term']);
} else {
	$timestamp = time();
}
if ($hash['data']['todo_noterm'] == 1) {
	$disabled = ' disabled="disabled"';
}
?>
<h1>ToDo編集</h1>
<ul class="operate">
	<li><a href="index.php">一覧に戻る</a></li>
	<li><a href="delete.php?id=<?=$hash['data']['id']?>">削除</a></li>
</ul>
<form class="content" method="post" name="todo" action="">
	<?=$view->error($hash['error'])?>
	<table class="form" cellspacing="0">
		<tr><th>タイトル<span class="necessary">(必須)</span></th><td><input type="text" name="todo_title" class="inputtitle" value="<?=$hash['data']['todo_title']?>" /></td></tr>
		<tr><th>期限</th><td>
			<select name="todo_year"<?=$disabled?>><?=$helper->option(2000, 2030, date('Y', $timestamp))?></select>年&nbsp;
			<select name="todo_month"<?=$disabled?>><?=$helper->option(1, 12, date('n', $timestamp))?></select>月&nbsp;
			<select name="todo_day"<?=$disabled?>><?=$helper->option(1, 31, date('j', $timestamp))?></select>日&nbsp;
			<?=$helper->checkbox('todo_noterm', 1, $hash['data']['todo_noterm'], 'todo_noterm', '期限なし', 'onclick="Todo.noterm(this)"')?>
		</td></tr>
<?php
if ($hash['data']['todo_complete'] == 1) {
	if(strlen($hash['data']['todo_completedate']) > 0) {
		$completedate = strtotime($hash['data']['todo_completedate']);
		$array = array(date('Y', $completedate), date('n', $completedate), date('j', $completedate), date('G', $completedate), date('i', $completedate));
	}
?>
		<tr><th>完了日</th><td>
			<select name="completeyear"><option value="">&nbsp;</option><?=$helper->option(2000, 2030, $array[0])?></select>年&nbsp;
			<select name="completemonth"><option value="">&nbsp;</option><?=$helper->option(1, 12, $array[1])?></select>月&nbsp;
			<select name="completeday"><option value="">&nbsp;</option><?=$helper->option(1, 31, $array[2])?></select>日&nbsp;
			<select name="completehour"><option value="">&nbsp;</option><?=$helper->option(0, 23, $array[3])?></select>時&nbsp;
			<select name="completeminute"><option value="">&nbsp;</option><?=$helper->option(0, 59, $array[4])?></select>分&nbsp;
		</td></tr>
<?php
}
?>
		<tr><th>重要度</th><td><?=$helper->selector('todo_priority', array('', '重要', '最重要'), $hash['data']['todo_priority'])?></td></tr>
		<tr><th>備考</th><td><textarea name="todo_comment" class="inputcomment" rows="3"><?=$hash['data']['todo_comment']?></textarea></td></tr>
	</table>
	<div class="submit">
		<input type="submit" value="　編集　" />&nbsp;
		<input type="button" value="キャンセル" onclick="location.href='index.php'" />
	</div>
	<input type="hidden" name="id" value="<?=$hash['data']['id']?>" />
</form>
<?php
$view->footing();
?>