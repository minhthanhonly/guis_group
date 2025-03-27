<!doctype html>

<html
  lang="en"
  class="layout-wide"
  dir="ltr"
  data-skin="default"
  data-assets-path="<?=$root?>assets/"
  data-template="vertical-menu-template"
  data-bs-theme="dark">
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
    <!-- Page CSS -->
    <?=$style?>
    <!-- Helpers -->
    <script src="assets/vendor/js/helpers.js"></script>
    <script src="assets/vendor/js/template-customizer.js"></script>
    <script src="assets/js/config.js"></script>

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