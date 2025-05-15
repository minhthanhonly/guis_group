<?php


require_once('../application/loader.php');
$view->heading('ファイル共有');
$pagination = new Pagination(array('folder'=>$_GET['folder']));
$current[intval($_GET['folder'])] = ' class="current"';
if (strlen($hash['folder'][$_GET['folder']]) > 0) {
	$caption = ' - '.$hash['folder'][$_GET['folder']];
}
?>
<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card" id="option-block">
		<div
			class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-6">
				<h4 class="card-title mb-0">
					<span>ファイル共有<?=$caption?></span></h4>
			</div>
			<div class="col-md-6">
				<div class="d-flex row">
					<div class="col-md-6">
						<form method="post" class="searchform" action="<?=$_SERVER['SCRIPT_NAME']?><?=$view->positive(array('folder'=>$_GET['folder']))?>">
							<div class="input-group input-group-merge">
								<span class="input-group-text" id="basic-addon-search31"><i class="icon-base ti tabler-search"></i></span>
								<input type="text" name="search" class="form-control" placeholder="検索..." aria-label="検索..." value="<?=$view->escape($_REQUEST['search'])?>">
								<button class="input-group-text" type="submit" id="button-addon2"><i class="icon-base ti tabler-search"></i></button>
							</div>
						</form>
					</div>
					<div class="col-md-6">
						<ul class="operate d-flex gap-2 list-unstyled justify-content-end">
							<?php
							if (isset($hash['parent']) && $view->permitted($hash['parent'], 'add')) {
								echo '<li><a class="btn btn-primary" href="add.php' . $view->positive(array('folder'=>$_GET['folder'])) . '">ファイルアップロード</a></li>';
								echo '<li><a class="btn btn-info" href="folderadd.php' . $view->positive(array('folder'=>$_GET['folder'])) . '">フォルダ追加</a></li>';
							}
							?>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<div class="card-body">
			<div class="container py-12">
				<div class="row">
					<div class="col-md-3 col-lg-2">
						<div class="folder">
							<h5 class="foldercaption">フォルダ</h5>
							<ul class="folderlist">
								<?php
								if (is_array($hash['folder']) && count($hash['folder']) > 0) {
									if ($_GET['folder'] > 0) {
										echo '<li class="storageprevious"><a href="index.php'.$view->positive(array('folder'=>$hash['parent']['storage_folder'])).'">上へ</a></li>';
									}
									foreach ($hash['folder'] as $key => $value) {
										echo sprintf('<li%s><a href="index.php?folder=%s">%s</a></li>', $current[$key], $key, $value);
									}
								} else {
									echo '<li class="current"><a href="index.php">ルート</a></li>';
								}
								?>
							</ul>
						</div>
					</div>
					<div class="col-md-9 col-lg-10">
						<table class="list table table-bordered table-striped" cellspacing="0">
							<tr><th><?=$pagination->sortby('storage_title', 'タイトル')?></th>
							<th><?=$pagination->sortby('storage_file', 'ファイル名')?></th>
							<th><?=$pagination->sortby('storage_size', 'サイズ')?></th>
							<th><?=$pagination->sortby('storage_name', '名前')?></th>
							<th style="width:140px;"><?=$pagination->sortby('storage_date', '日時')?></th>
							<th class="listlink">&nbsp;</th></tr>
					<?php
					if (is_array($hash['list']) && count($hash['list']) > 0) {
						foreach ($hash['list'] as $row) {
							$fileext = '';
							if ($row['storage_type'] == 'file') {
								$fileext = strtolower(substr(strrchr($row['storage_file'], '.'), 1));
								if ($fileext == 'pdf') {
									$fileext = 'file-type-pdf';
								} elseif ($fileext == 'mp4') {
									$fileext = 'file-video';
								} elseif ($fileext == 'mp3') {
									$fileext = 'file-music';
								} elseif ($fileext == 'zip' || $fileext == 'rar' || $fileext == '7z' || $fileext == 'tar' || $fileext == 'gz' || $fileext == 'bz2') {
									$fileext = 'file-type-zip';
								} elseif ($fileext == 'doc' || $fileext == 'docx') {
									$fileext = 'file-type-doc';
								} elseif ($fileext == 'xls' || $fileext == 'xlsx') {
									$fileext = 'file-type-xls';
								} elseif ($fileext == 'ppt' || $fileext == 'pptx') {
									$fileext = 'file-type-ppt';
								}
								$url = 'view.php?id='.$row['id'];
								$file = '<a href="download.php?id='.$row['id'].'&file='.urlencode($row['storage_file']).'">'.$row['storage_file'].'</a>';
								$property = $url;
							} else {
								$fileext = 'folder tabler-filled';
								$url = 'index.php?folder='.$row['id'];
								$property = 'folderview.php?id='.$row['id'];
							}
					?>
							<tr><td><a class="storage<?=$row['storage_type']?> <?=$fileext?>" href="<?=$url?>"><i class="icon-base ti tabler-<?=$fileext?> me-2 text-info"></i><?=$row['storage_title']?></a>&nbsp;</td>
							<td><?=$file?></a>&nbsp;</td>
							<td><?=$row['storage_size']?>&nbsp;</td>
							<td><?=$row['storage_name']?>&nbsp;</td>
							<td><?=date('Y/m/d H:i:s', strtotime($row['storage_date']))?>&nbsp;</td>
							<td><a href="<?=$property?>">詳細</a>&nbsp;</td></tr>
					<?php
						}
					}
					?>
						</table>
						<?=$view->pagination($pagination, $hash['count']);?>
						<?php
						if (isset($hash['parent']['id']) && $view->permitted($hash['parent'], 'edit')) {
						?>
						<div class="mt-4 d-flex gap-2 justify-content-end">
							<a href="folderedit.php?id=<?=$hash['parent']['id']?>" class="btn btn-label-primary">フォルダ編集</a>
							<a href="folderdelete.php?id=<?=$hash['parent']['id']?>" class="btn btn-label-warning">フォルダ削除</a>
						</div>
						<?php
						}
						?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<?php
$view->footing();
?>