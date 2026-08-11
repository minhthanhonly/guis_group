<?php

require_once('../application/loader.php');
$view->heading('ファイル情報');
$storageFiles = array();
if (isset($hash['data']['storage_file']) && strlen($hash['data']['storage_file']) > 0) {
	foreach (explode(',', $hash['data']['storage_file']) as $filename) {
		$filename = trim($filename);
		if ($filename !== '') {
			$storageFiles[] = $filename;
		}
	}
}
?>

<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card" id="option-block">
		<div
			class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-6">
				<h4 class="card-title mb-0">
					<span>ファイル情報</span></h4>
			</div>
			<div class="col-md-6">
				<div class="d-flex row">
					<div class="col-md-6">
					</div>
					<div class="col-md-6">
						<ul class="operate d-flex gap-2 list-unstyled justify-content-end">
							<?php
							if ($view->permitted($hash['data'], 'edit')) {
								echo '<li><a class="btn btn-primary" href="edit.php?id='.$hash['data']['id'].'">編集</a></li>';
								echo '<li><a class="btn btn-danger" href="delete.php?id='.$hash['data']['id'].'">削除</a></li>';
							}
							?>
							<li><a class="btn btn-info" href="index.php<?=$view->positive(array('folder'=>$hash['data']['storage_folder']))?>">一覧に戻る</a></li>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<div class="card-body">
			<div class="container py-12">
				<table class="table table-bordered table-striped mb-4" cellspacing="0">
					<tr><th>タイトル</th><td><?=$hash['data']['storage_title']?>&nbsp;</td></tr>
					<tr><th>ファイル名</th><td>
						<?php if (count($storageFiles) > 0) { ?>
						<ul class="list-unstyled mb-0">
							<?php foreach ($storageFiles as $filename) { ?>
							<li class="mb-1">
								<a href="download.php?id=<?=$hash['data']['id']?>&file=<?=urlencode($filename)?>">
									<?=$view->escape($filename)?>&nbsp;[ダウンロード]
								</a>
							</li>
							<?php } ?>
						</ul>
						<?php } else { ?>
						&nbsp;
						<?php } ?>
					</td></tr>
					<tr><th>ファイルサイズ</th><td><?=$hash['data']['storage_size']?>&nbsp;</td></tr>
					<tr><th>内容</th><td><?=nl2br($hash['data']['storage_comment'])?>&nbsp;</td></tr>
					<tr><th>場所</th><td><?=$hash['folder']['storage_title']?>&nbsp;</td></tr>
					<tr><th>名前</th><td><?=$hash['data']['storage_name']?>&nbsp;</td></tr>
					<tr><th>日時</th><td><?=date('Y/m/d H:i:s', strtotime($hash['data']['storage_date']))?>&nbsp;</td></tr>
				</table>
				<?php
				$view->property($hash['data']);
				?>
			</div>
		</div>
	</div>
</div>
<?php
$view->footing();
?>
