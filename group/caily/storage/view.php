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
$isProtected = !empty($hash['data']['is_protected']);
$canEdit = $view->permitted($hash['data'], 'edit');
$viewerUserid = isset($_SESSION['userid']) ? (string)$_SESSION['userid'] : '';
$viewerRealname = isset($_SESSION['realname']) ? (string)$_SESSION['realname'] : '';
$previewable = array();
foreach ($storageFiles as $filename) {
	$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
	if (in_array($ext, array('pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'), true)) {
		$previewable[] = array(
			'name' => $filename,
			'isPdf' => ($ext === 'pdf'),
			'isImage' => ($ext !== 'pdf'),
		);
	}
}
$firstPreview = count($previewable) > 0 ? $previewable[0] : null;
?>

<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card" id="option-block">
		<div
			class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-6">
				<h4 class="card-title mb-0">
					<span>ファイル情報</span>
					<?php if ($isProtected) { ?>
					<span class="badge bg-warning text-dark ms-2">保護ファイル</span>
					<?php } ?>
				</h4>
			</div>
			<div class="col-md-6">
				<div class="d-flex row">
					<div class="col-md-6">
					</div>
					<div class="col-md-6">
						<ul class="operate d-flex gap-2 list-unstyled justify-content-end">
							<?php
							if ($canEdit) {
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
							<?php foreach ($storageFiles as $filename) {
								if ($isProtected) {
							?>
							<li class="mb-1"><?=$view->escape($filename)?>&nbsp;<span class="text-muted small">[ダウンロード不可]</span></li>
							<?php } else {
								$dl = 'download.php?id='.intval($hash['data']['id']).'&file='.urlencode($filename);
							?>
							<li class="mb-1">
								<a href="<?=$dl?>"><?=$view->escape($filename)?>&nbsp;[ダウンロード]</a>
							</li>
							<?php }
							} ?>
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

				<?php if ($firstPreview) { ?>
				<div class="mt-4">
					<h5 class="mb-2">プレビュー</h5>
					<?php if (count($previewable) > 1) { ?>
					<select id="storagePreviewSelect" class="form-select mb-2" style="max-width:420px;">
						<?php foreach ($previewable as $i => $p) { ?>
						<option value="<?=$i?>" data-name="<?=$view->escape($p['name'])?>" data-pdf="<?=$p['isPdf'] ? '1' : '0'?>" data-image="<?=$p['isImage'] ? '1' : '0'?>">
							<?=$view->escape($p['name'])?>
						</option>
						<?php } ?>
					</select>
					<?php } ?>
					<div id="storageViewerHost" class="border rounded p-2 bg-light"></div>
				</div>
				<?php } ?>
			</div>
		</div>
	</div>
</div>
<?php
$view->footing();
?>
<?php if ($firstPreview) { ?>
<script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js"></script>
<script src="<?=ROOT?>assets/js/protected-file-viewer.js?v=<?=defined('CACHE_VERSION')?CACHE_VERSION:APP_VERSION?>"></script>
<script>
(function () {
	var storageId = <?=intval($hash['data']['id'])?>;
	var isProtected = <?= $isProtected ? 'true' : 'false' ?>;
	var previewToken = <?= json_encode((string)($hash['preview_token'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
	var realname = <?= json_encode($viewerRealname, JSON_UNESCAPED_UNICODE) ?>;
	var userid = <?= json_encode($viewerUserid, JSON_UNESCAPED_UNICODE) ?>;
	var host = document.getElementById('storageViewerHost');
	var select = document.getElementById('storagePreviewSelect');

	function previewUrl(name) {
		return 'download.php?id=' + storageId + '&file=' + encodeURIComponent(name) + '&inline=1';
	}

	function mountViewer(opt, name, extra) {
		CailyProtectedViewer.mount(Object.assign({
			container: host,
			isPdf: opt.getAttribute('data-pdf') === '1',
			isImage: opt.getAttribute('data-image') === '1',
			isProtected: isProtected,
			realname: realname,
			userid: userid,
			alt: name,
			fitMode: 'page'
		}, extra || {}));
	}

	function mountFromOption(opt) {
		if (!host || !window.CailyProtectedViewer || !opt) return;
		var name = (opt.getAttribute('data-name') || opt.textContent || '').trim();
		if (!isProtected) {
			mountViewer(opt, name, { url: previewUrl(name) });
			return;
		}
		host.innerHTML = '<div class="text-muted p-3">読み込み中...</div>';
		fetch('download.php', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'Accept': 'application/octet-stream'
			},
			body: new URLSearchParams({
				id: String(storageId),
				file: name,
				inline: '1',
				preview_token: previewToken
			})
		}).then(function (res) {
			if (!res.ok) {
				throw new Error('preview denied');
			}
			return res.arrayBuffer();
		}).then(function (buf) {
			mountViewer(opt, name, { data: buf });
		}).catch(function () {
			host.innerHTML = '<div class="alert alert-warning mb-0">プレビューを表示できません。</div>';
		});
	}

	if (select) {
		select.addEventListener('change', function () {
			var opt = select.options[select.selectedIndex];
			mountFromOption(opt);
		});
		mountFromOption(select.options[select.selectedIndex]);
	} else {
		var fake = document.createElement('option');
		fake.setAttribute('data-name', <?= json_encode($firstPreview['name'], JSON_UNESCAPED_UNICODE) ?>);
		fake.setAttribute('data-pdf', <?= $firstPreview['isPdf'] ? "'1'" : "'0'" ?>);
		fake.setAttribute('data-image', <?= $firstPreview['isImage'] ? "'1'" : "'0'" ?>);
		mountFromOption(fake);
	}

	if (isProtected) {
		document.addEventListener('keydown', function (e) {
			var key = (e.key || '').toLowerCase();
			if ((e.ctrlKey || e.metaKey) && (key === 'p' || key === 'c' || key === 'x' || key === 's')) {
				e.preventDefault();
			}
		});
	}
})();
</script>
<?php } ?>
