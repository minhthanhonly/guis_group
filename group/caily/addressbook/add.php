<?php

require_once('../application/loader.php');
$view->script('postcode.js');
$view->heading('アドレス追加');
$hash['data']['folder_id'] = $view->initialize($hash['data']['folder_id'], $_GET['folder']);
$hash['folder'] = array('&nbsp;') + $hash['folder'];
if ($hash['data']['addressbook_parent'] > 0) {
	$belong = $helper->checkbox('addressbook_parent', intval($hash['data']['addressbook_parent']), intval($hash['data']['addressbook_parent']), 'addressbook_parent', 'リンク');
}
?>
<!-- Content -->
<div class="container-xxl flex-grow-1 container-p-y">
	
	<div class="card" id="option-block">
		<div class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-6">
				<h4 class="card-title mb-0"><span id="timecard_title">アドレス追加</h4>
            </div>
			<div class="col-md-6 d-flex justify-content-end gap-4">
				<div><a href="index.php" class="btn btn-info">一覧に戻る</a></div>
			</div>
        </div>
		<div class="card-body">

<form class="content mt-12 mb-12" method="post" name="addressbook" action="">
	<?=$view->error($hash['error'])?>
	<div class="row">
		<div class="col-12">
			<div class="mb-3 row">
				<label class="col-md-2 col-form-label">名前<span class="text-danger">(必須)</span></label>
				<div class="col-md-6">
					<input type="text" name="addressbook_name" class="form-control" value="<?=$hash['data']['addressbook_name']?>" />
				</div>
			</div>
			<div class="mb-3 row">
				<label class="col-md-2 col-form-label">かな</label>
				<div class="col-md-6">
					<input type="text" name="addressbook_ruby" class="form-control" value="<?=$hash['data']['addressbook_ruby']?>" />
				</div>
			</div>
			<div class="mb-3 row">
				<label class="col-md-2 col-form-label">郵便番号</label>
				<div class="col-md-6">
					<div class="input-group">
						<input type="text" name="addressbook_postcode" id="postcode" class="form-control" value="<?=$hash['data']['addressbook_postcode']?>" />
						<!-- <button type="button" class="btn btn-outline-secondary" onclick="Postcode.feed(this)">検索</button> -->
					</div>
				</div>
			</div>
			<div class="mb-3 row">
				<label class="col-md-2 col-form-label">住所</label>
				<div class="col-md-6">
					<div class="input-group">
						<input type="text" name="addressbook_address" id="address" class="form-control" value="<?=$hash['data']['addressbook_address']?>" />
						<!-- <button type="button" class="btn btn-outline-secondary" onclick="Postcode.feed(this, 'address')">検索</button> -->
					</div>
				</div>
			</div>
			<div class="mb-3 row">
				<label class="col-md-2 col-form-label">住所（かな）</label>
				<div class="col-md-6">
					<input type="text" name="addressbook_addressruby" id="addressruby" class="form-control" value="<?=$hash['data']['addressbook_addressruby']?>" />
				</div>
			</div>
			<div class="mb-3 row">
				<label class="col-md-2 col-form-label">電話番号</label>
				<div class="col-md-6">
					<input type="text" name="addressbook_phone" class="form-control" value="<?=$hash['data']['addressbook_phone']?>" />
				</div>
			</div>
			<div class="mb-3 row">
				<label class="col-md-2 col-form-label">FAX</label>
				<div class="col-md-6">
					<input type="text" name="addressbook_fax" class="form-control" value="<?=$hash['data']['addressbook_fax']?>" />
				</div>
			</div>
			<div class="mb-3 row">
				<label class="col-md-2 col-form-label">携帯電話番号</label>
				<div class="col-md-6">
					<input type="text" name="addressbook_mobile" class="form-control" value="<?=$hash['data']['addressbook_mobile']?>" />
				</div>
			</div>
			<div class="mb-3 row">
				<label class="col-md-2 col-form-label">メールアドレス</label>
				<div class="col-md-6">
					<input type="text" name="addressbook_email" class="form-control" value="<?=$hash['data']['addressbook_email']?>" />
				</div>
			</div>
			<div class="mb-3 row">
				<label class="col-md-2 col-form-label">会社名</label>
				<div class="col-md-6">
					<div class="input-group">
						<input type="text" name="addressbook_company" class="form-control" value="<?=$hash['data']['addressbook_company']?>" />
						<!-- <button type="button" class="btn btn-outline-secondary" onclick="Addressbook.companylist(this)">検索</button> -->
						<span id="belong" class="ms-2"><?=$belong?></span>
					</div>
				</div>
			</div>
			<div class="mb-3 row">
				<label class="col-md-2 col-form-label">会社名（かな）</label>
				<div class="col-md-6">
					<input type="text" name="addressbook_companyruby" class="form-control" value="<?=$hash['data']['addressbook_companyruby']?>" />
				</div>
			</div>
			<div class="mb-3 row">
				<label class="col-md-2 col-form-label">部署</label>
				<div class="col-md-6">
					<input type="text" name="addressbook_department" class="form-control" value="<?=$hash['data']['addressbook_department']?>" />
				</div>
			</div>
			<div class="mb-3 row">
				<label class="col-md-2 col-form-label">役職</label>
				<div class="col-md-6">
					<input type="text" name="addressbook_position" class="form-control" value="<?=$hash['data']['addressbook_position']?>" />
				</div>
			</div>
			<div class="mb-3 row">
				<label class="col-md-2 col-form-label">URL</label>
				<div class="col-md-6">
					<input type="text" name="addressbook_url" id="addressbook_url" class="form-control" value="<?=$hash['data']['addressbook_url']?>" />
				</div>
			</div>
			<div class="mb-3 row">
				<label class="col-md-2 col-form-label">備考</label>
				<div class="col-md-6">
					<textarea name="addressbook_comment" class="form-control" rows="5"><?=$hash['data']['addressbook_comment']?></textarea>
				</div>
			</div>
			<div class="mb-3 row">
				<label class="col-md-2 col-form-label">カテゴリ</label>
				<div class="col-md-6">
					<?=$helper->selector('folder_id', $hash['folder'], $hash['data']['folder_id'])?>
				</div>
			</div>
			<div class="mb-3 row">
				<label class="col-md-2 col-form-label">公開設定<?=$view->explain('public')?></label>
				<div class="col-md-6">
					<?=$view->permit($hash['data'])?>
				</div>
			</div>
			<div class="mb-3 row">
				<label class="col-md-2 col-form-label">編集設定<?=$view->explain('edit')?></label>
				<div class="col-md-6">
					<?=$view->permit($hash['data'], 'edit')?>
				</div>
			</div>
		</div>
	</div>
	<div class="submit">
		<button type="submit" class="btn btn-primary" ><i class="icon-base ti tabler-plus"></i>追加</button>&nbsp;
		<a href="index.php<?=$view->parameter(array('folder'=>$_GET['folder']))?>" class="btn btn-secondary">キャンセル</a>
	</div>
	<input type="hidden" name="addressbook_type" value="0" />
</form>
</div>
</div>
</div>
<!-- / Content -->
<?php
$view->footing();
?>