<?php

require_once('../application/loader.php');
$view->heading('プロジェクト');

?>
<!-- Content -->
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="misc-wrapper d-flex flex-column justify-content-center align-items-center">
      <h4 class="mb-2 mx-2">Under construction! 🚧</h4>
      <p class="mb-6 mx-2">Sorry for the inconvenience but we're performing some construction at the moment</p>
      <a href="index.html" class="btn btn-primary waves-effect waves-light">Back to home</a>
      <div class="mt-12">
        <img src="<?=ROOT?>assets/img/illustrations/page-misc-under-maintenance.png" alt="page-misc-under-maintenance" width="550" class="img-fluid">
      </div>
    </div>
</div>

<?php
$view->footing();
?>
