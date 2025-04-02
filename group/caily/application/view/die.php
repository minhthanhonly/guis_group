<?php require_once('../loader.php'); ?>
<!doctype html>
<?php $root = ROOT;?>
<html
  lang="en"
  class="layout-wide customizer-hide"
  dir="ltr"
  data-skin="default"
  data-assets-path="assets/"
  data-template="vertical-menu-template"
  data-bs-theme="dark">
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>ERROR</title>

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

    <!-- Core CSS -->
    <!-- build:css assets/vendor/css/theme.css  -->

    <link rel="stylesheet" href="<?=$root?>assets/vendor/libs/node-waves/node-waves.css" />

    <link rel="stylesheet" href="<?=$root?>assets/vendor/libs/pickr/pickr-themes.css" />

    <link rel="stylesheet" href="<?=$root?>assets/vendor/css/core.css" />

    <!-- Vendors CSS -->

    <link rel="stylesheet" href="<?=$root?>assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <!-- endbuild -->

    <!-- Page CSS -->
    <!-- Page -->
    <link rel="stylesheet" href="<?=$root?>assets/vendor/css/pages/page-misc.css" />

    <script src="<?=$root?>assets/js/config.js"></script>
  </head>

  <body>
    <!-- Content -->

    <!-- Error -->
    <div class="container-xxl container-p-y">
      <div class="misc-wrapper">
        <h1 class="mb-2 mx-2" style="line-height: 6rem; font-size: 6rem">ERROR</h1>
        <h4 class="mb-2 mx-2">⚠️</h4>
        <p class="mb-6 mx-2"><?php echo $message; ?></p>
        <a href="<?=$root?>" class="btn btn-primary mb-10">Back to home</a>
        <div class="mt-4">
          <img
            src="<?=$root?>assets/img/illustrations/page-misc-error.png"
            alt="page-misc-error-light"
            width="225"
            class="img-fluid" />
        </div>
      </div>
    </div>
    <div class="container-fluid misc-bg-wrapper">
      <img
        src="<?=$root?>assets/img/illustrations/bg-shape-image-light.png"
        height="355"
        alt="page-misc-error"
        data-app-light-img="illustrations/bg-shape-image-light.png"
        data-app-dark-img="illustrations/bg-shape-image-dark.png" />
    </div>
    <!-- /Error -->

    <!-- / Content -->

  </body>
</html>
