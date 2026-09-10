<?php
$pages = hethong_pages();
$active = isset($GLOBALS['hethong_active']) ? $GLOBALS['hethong_active'] : '';
$base = hethong_base_url();
?>
<div class="card hethong-nav-card sticky-lg-top" style="top: 1rem;">
  <div class="card-header py-2">
    <strong>操作ガイド</strong>
  </div>
  <div class="card-body p-2">
    <nav class="nav flex-column hethong-nav gap-1">
      <?php foreach ($pages as $p): ?>
        <a
          class="nav-link<?= $p['id'] === $active ? ' active' : '' ?>"
          href="<?= htmlspecialchars($base . $p['file'], ENT_QUOTES, 'UTF-8') ?>"
        ><?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?></a>
      <?php endforeach; ?>
    </nav>
    <hr class="my-2">
    <a class="btn btn-sm btn-outline-secondary w-100" href="javascript:history.back()">
      <i class="fa fa-arrow-left me-1"></i>戻る
    </a>
  </div>
</div>
