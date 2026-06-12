<?php
require_once DIR_VIEW . 'app-asset-config.php';
$appAssets = app_resolve_asset_context($directory ?? '', $page ?? '');
$cv = defined('CACHE_VERSION') ? CACHE_VERSION : '';
?>
<script src="<?=$root?>assets/js/app-loader.js?v=<?=$cv?>"></script>
<script src="<?=$root?>assets/vendor/libs/jquery/jquery.js"></script>
<script src="<?=$root?>assets/vendor/js/bootstrap.js"></script>
<?php if ($appAssets['needs_full_shell']): ?>
<script src="<?=$root?>assets/vendor/libs/node-waves/node-waves.js"></script>
<script src="<?=$root?>assets/vendor/libs/pickr/pickr.js"></script>
<script src="<?=$root?>assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="<?=$root?>assets/vendor/js/menu.js"></script>
<?php endif; ?>
<script src="<?=$root?>assets/js/axios.min.js"></script>
<script src="<?=$root?>assets/vendor/libs/i18n/i18n.js"></script>

<?php if ($appAssets['needs_form_validation']): ?>
<script src="<?=$root?>assets/vendor/libs/@form-validation/popular.js"></script>
<script src="<?=$root?>assets/vendor/libs/@form-validation/bootstrap5.js"></script>
<script src="<?=$root?>assets/vendor/libs/@form-validation/auto-focus.js"></script>
<?php endif; ?>

<?php if ($appAssets['needs_full_shell']): ?>
<script src="<?=$root?>assets/vendor/libs/moment/moment.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment-timezone/0.5.43/moment-timezone-with-data.min.js" crossorigin="anonymous"></script>
<script src="<?=$root?>assets/vendor/libs/select2/select2.js"></script>
<script src="<?=$root?>assets/vendor/libs/cleave-zen/cleave-zen.js"></script>
<script src="<?=$root?>assets/vendor/libs/tagify/tagify.js"></script>
<script src="<?=$root?>assets/vendor/libs/bootstrap-select/bootstrap-select.js"></script>
<script src="<?=$root?>assets/vendor/libs/notiflix/notiflix.js"></script>
<script src="<?=$root?>assets/vendor/libs/sweetalert2/sweetalert2.js"></script>
<script src="<?=$root?>assets/js/imask.js"></script>
<?php endif; ?>

<?php if ($appAssets['needs_algolia']): ?>
<script src="<?=$root?>assets/vendor/libs/@algolia/autocomplete-js.js"></script>
<?php endif; ?>

<?php if ($appAssets['needs_data_tables']): ?>
<script src="<?=$root?>assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js"></script>
<?php endif; ?>

<?php if ($appAssets['needs_flatpickr']): ?>
<script src="<?=$root?>assets/vendor/libs/flatpickr/flatpickr.js"></script>
<script src="<?=$root?>assets/vendor/libs/flatpickr/monthSelect.js"></script>
<script src="<?=$root?>assets/vendor/libs/flatpickr/ja.js"></script>
<script src="<?=$root?>assets/vendor/libs/flatpickr/vi.js"></script>
<?php endif; ?>

<script>
  window.ROOT = <?= json_encode($root) ?>;
  window.currentUserId = <?= json_encode($_SESSION['id'] ?? 0) ?>;
  window.currentUserName = <?= json_encode($_SESSION['userid'] ?? '') ?>;
  window.__APP_SHORTCUTS = {
    showProject: <?= json_encode(!empty($_SESSION['show_project'])) ?>,
    showExtended: <?= json_encode(($_SESSION['group'] ?? '') != '7' && ($_SESSION['group'] ?? '') != '6') ?>
  };
</script>

<?php if ($appAssets['needs_full_shell']): ?>
<?php if ($appAssets['needs_quill']): ?>
<script src="<?=$root?>assets/vendor/libs/quill/quill.js"></script>
<?php endif; ?>
<script src="<?=$root?>assets/js/todo-modal.js?v=<?=$cv?>"></script>
<script src="<?=$root?>assets/js/main.js?v=<?=$cv?>"></script>
<script src="<?=$root?>assets/js/task-timer.js?v=<?=$cv?>"></script>
<script src="<?=$root?>assets/js/app-chat.js?v=<?=$cv?>"></script>
<script src="<?=$root?>assets/js/command-palette.js?v=<?=$cv?>"></script>
<?php if (!empty($_SESSION['show_project']) && ($_SESSION['group'] ?? '') != '7' && ($_SESSION['group'] ?? '') != '6'): ?>
<script src="<?=$root?>assets/js/customer-global-modal.js?v=<?=$cv?>"></script>
<?php endif; ?>
<script src="<?=$root?>js/library/jquery-ui.min.js"></script>
<script src="<?=$root?>js/application.js?v=<?=$cv?>"></script>
<?php if ($appAssets['needs_sortable']): ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js" crossorigin="anonymous"></script>
<?php endif; ?>
<script src="<?=ROOT?>assets/js/image-modal.js"></script>
<?php if (isset($_SESSION['userid'])): ?>
<script src="<?=$root?>assets/js/notification.js?v=<?=$cv?>"></script>
<?php endif; ?>
<?php endif; ?>

<?php if (!empty($javascript)) { echo $javascript; } ?>

</body>
</html>
