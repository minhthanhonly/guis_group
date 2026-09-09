<!doctype html>

<html
  lang="ja"
  class="layout-wide layout-navbar-fixed"
  dir="ltr"
  data-skin="default"
  data-assets-path="<?=$root?>assets/"
  data-template="vertical-menu-template"
  data-bs-theme="dark"
  data-timecard-start="<?=TIMECARD_START_DATE?>"
  data-cache-version="<?=CACHE_VERSION?>"
  >
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=1600">
  
    <title><?=$caption?></title>

    <meta name="description" content="" />
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?=$root?>assets/img/favicon/favicon.ico" />
    <!-- CDN preconnect (Vue / moment / Sortable) — no Google Fonts -->
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin />
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin />

    <link rel="stylesheet" href="<?=$root?>assets/vendor/fonts/iconify-icons.css" />
    <link rel="stylesheet" href="<?=$root?>assets/vendor/fonts/fontawesome.css" />
    <link rel="stylesheet" href="<?=$root?>assets/vendor/libs/node-waves/node-waves.css" />
    <link rel="stylesheet" href="<?=$root?>assets/vendor/libs/pickr/pickr-themes.css" />
    <link rel="stylesheet" href="<?=$root?>assets/vendor/css/core.css?v=<?=CACHE_VERSION?>" />
    <link rel="stylesheet" href="<?=$root?>assets/css/demo.css?v=<?=CACHE_VERSION?>" />
    <link rel="stylesheet" href="<?=$root?>assets/css/command-palette.css?v=<?=CACHE_VERSION?>" />
    <link rel="stylesheet" href="<?=$root?>assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="<?=$root?>assets/vendor/libs/@form-validation/form-validation.css" />
    <link rel="stylesheet" href="<?=$root?>assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css" />
    <link rel="stylesheet" href="<?=$root?>assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css" />
    <link rel="stylesheet" href="<?=$root?>assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css" />
    <link rel="stylesheet" href="<?=$root?>assets/vendor/libs/select2/select2.css" />
    <link rel="stylesheet" href="<?=$root?>assets/vendor/libs/sweetalert2/sweetalert2.css" />
    <link rel="stylesheet" href="<?=$root?>assets/vendor/libs/tagify/tagify.css" />
    <link rel="stylesheet" href="<?=$root?>assets/vendor/libs/bootstrap-select/bootstrap-select.css" />
    <link rel="stylesheet" href="<?=$root?>assets/vendor/libs/spinkit/spinkit.css" />
    <link rel="stylesheet" href="<?=$root?>assets/vendor/libs/notiflix/notiflix.css" />
<?php
require_once DIR_VIEW . 'app-asset-config.php';
$appAssets = isset($appAssets) && is_array($appAssets)
    ? $appAssets
    : app_resolve_asset_context($directory ?? '', $page ?? '');
$deferTaskTimerCss = !empty($appAssets['defer_task_timer_css']);
$deferFlatpickrCss = !empty($appAssets['defer_flatpickr_css']);
$skipChatCss = empty($appAssets['needs_chat_css']);
// Flatpickr CSS omitted on project list — loaded via ensureFlatpickr() when filter/modal needs it
if (empty($deferFlatpickrCss)) {
    app_print_stylesheet($root . 'assets/vendor/libs/flatpickr/flatpickr.css');
    app_print_stylesheet($root . 'assets/vendor/libs/flatpickr/monthSelect.css');
}
app_print_stylesheet(
    $root . 'assets/css/task-timer.css?v=' . CACHE_VERSION,
    ['blocking' => !$deferTaskTimerCss]
);
app_print_stylesheet(
    $root . 'assets/css/app-chat.css?v=' . CACHE_VERSION,
    ['skip' => $skipChatCss]
);
?>
    <link rel="stylesheet" href="<?=ROOT?>assets/css/image-modal.css" />
    <!-- Page CSS -->
    <?=$style?>
    <!-- Helpers -->

    <script src="<?=$root?>assets/vendor/libs/pickr/pickr.js"></script>
    <script src="<?=$root?>assets/vendor/js/template-customizer.js?v=<?=CACHE_VERSION?>"></script>
    <?php if (($directory ?? '') !== 'login'): ?>
    <script src="https://cdn.jsdelivr.net/npm/vue@3.2.31/dist/vue.global.prod.js"></script>
    <?php endif; ?>
    <script src="<?=$root?>assets/vendor/js/helpers.js?v=<?=CACHE_VERSION?>"></script>
    <script src="<?=$root?>assets/js/config.js?v=<?=CACHE_VERSION?>"></script>
  </head>

  <body<?=$onload?>>


  <?php
if(isset($_SESSION['userid'])) {
    $avatarRubyMap = array('byUserId' => array(), 'byRealname' => array());
    try {
        if (!class_exists('ApplicationModel', false)) {
            require_once DIR_MODEL.'applicationmodel.php';
        }
        $_avatarRubyModel = new ApplicationModel();
        $avatarRubyMap = $_avatarRubyModel->getAvatarRubyMap();
    } catch (Exception $e) {
        $avatarRubyMap = array('byUserId' => array(), 'byRealname' => array());
    } catch (Error $e) {
        $avatarRubyMap = array('byUserId' => array(), 'byRealname' => array());
    }
?>
<script>
    const USER_AUTH_ID = '<?= isset($_SESSION['id']) ? $_SESSION['id'] : '' ?>';
    const USER_ID = '<?= isset($_SESSION['userid']) ? $_SESSION['userid'] : '' ?>';
    const USER_NAME = <?= json_encode(isset($_SESSION['realname']) ? (string)$_SESSION['realname'] : '', JSON_UNESCAPED_UNICODE) ?>;
    const USER_RUBY = <?= json_encode(isset($_SESSION['user_ruby']) ? (string)$_SESSION['user_ruby'] : '', JSON_UNESCAPED_UNICODE) ?>;
    const USER_IMAGE = '<?= isset($_SESSION['user_image']) ? $_SESSION['user_image'] : '' ?>';
    const USER_GROUP = '<?= isset($_SESSION['group']) ? $_SESSION['group'] : '' ?>';
    const USER_IS_SOUMU = <?= (!empty($_SESSION['is_soumu']) && (string)$_SESSION['is_soumu'] === '1') ? '1' : '0' ?>;
    window.CAILY_AVATAR_RUBY = <?= json_encode($avatarRubyMap, JSON_UNESCAPED_UNICODE) ?>;
    <?php 
        echo 'const USER_ROLE = "'.$_SESSION['authority'].'";'; 
    ?>
</script>
<?php
}
?>