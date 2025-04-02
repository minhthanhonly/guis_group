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
  >
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title><?=$caption?></title>

    <meta name="description" content="" />
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?=$root?>assets/img/favicon/favicon.ico" />
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&ampdisplay=swap"
      rel="stylesheet" />

    <link rel="stylesheet" href="<?=$root?>assets/vendor/fonts/iconify-icons.css" />
    <link rel="stylesheet" href="<?=$root?>assets/vendor/libs/node-waves/node-waves.css" />
    <link rel="stylesheet" href="<?=$root?>assets/vendor/libs/pickr/pickr-themes.css" />
    <link rel="stylesheet" href="<?=$root?>assets/vendor/css/core.css" />
    <link rel="stylesheet" href="<?=$root?>assets/css/demo.css" />
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
    <!-- Page CSS -->
    <?=$style?>
    <!-- Helpers -->
    <script src="<?=$root?>assets/vendor/js/helpers.js"></script>
    <script src="<?=$root?>assets/js/config.js"></script>

    <!-- <link href="<?=$root?>css/default.css" rel="stylesheet" type="text/css" />
    <link href="<?=$root?>css/control.css" rel="stylesheet" type="text/css" />
    <link href="<?=$root?>css/application.css" rel="stylesheet" type="text/css" />
    <link href="<?=$root?>css/style.css" rel="stylesheet" type="text/css" />
    <script type="text/javascript" src="<?=$root?>js/library/jquery.js"></script>
    <script type="text/javascript" src="<?=$root?>js/library/ui.core.js"></script>
    <script type="text/javascript" src="<?=$root?>js/library/ui.draggable.js"></script>
    <script type="text/javascript" src="<?=$root?>js/application.js"></script> -->
    
  </head>

  <body<?=$onload?>>