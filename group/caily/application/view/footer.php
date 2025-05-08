<script src="<?=$root?>assets/vendor/libs/jquery/jquery.js"></script>
<script src="<?=$root?>assets/js/pjax.min.js"></script>
<script src="<?=$root?>assets/vendor/libs/popper/popper.js"></script>
<script src="<?=$root?>assets/vendor/js/bootstrap.js"></script>
<script src="<?=$root?>assets/vendor/libs/node-waves/node-waves.js"></script>
<script src="<?=$root?>assets/vendor/libs/@algolia/autocomplete-js.js"></script>
<script src="<?=$root?>assets/vendor/libs/pickr/pickr.js"></script>
<script src="<?=$root?>assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="<?=$root?>assets/vendor/libs/hammer/hammer.js"></script>
<script src="<?=$root?>assets/vendor/js/menu.js"></script>
<script src="<?=$root?>assets/js/axios.min.js"></script>

<!-- endbuild -->

<!-- Vendors JS -->
<script src="<?=$root?>assets/vendor/libs/@form-validation/popular.js"></script>
<script src="<?=$root?>assets/vendor/libs/@form-validation/bootstrap5.js"></script>
<script src="<?=$root?>assets/vendor/libs/@form-validation/auto-focus.js"></script>
<script src="<?=$root?>assets/vendor/libs/apex-charts/apexcharts.js"></script>
<script src="<?=$root?>assets/vendor/libs/moment/moment.js"></script>
<script src="<?=$root?>assets/vendor/libs/select2/select2.js"></script>
<script src="<?=$root?>assets/vendor/libs/cleave-zen/cleave-zen.js"></script>
<script src="<?=$root?>assets/vendor/libs/tagify/tagify.js"></script>
<script src="<?=$root?>assets/vendor/libs/bootstrap-select/bootstrap-select.js"></script>
<script src="<?=$root?>assets/vendor/libs/notiflix/notiflix.js"></script>
<script src="<?=$root?>assets/vendor/libs/sweetalert2/sweetalert2.js"></script>
<script src="<?=$root?>assets/js/imask.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ja.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>

<script src="<?=$root?>assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js"></script>



<script src="<?=$root?>assets/js/main.js"></script>
<script src="<?=$root?>assets/js/app-chat.js"></script>
<link rel="stylesheet" href="<?=$root?>assets/css/app-chat.css">
<script src="<?=$root?>assets/js/forms-tagify.js"></script>
<?php
if(isset($_SESSION['userid'])) {
?>
<script>
    const USER_ID = '<?= isset($_SESSION['userid']) ? $_SESSION['userid'] : '' ?>';
    const USER_GROUP = '<?= isset($_SESSION['group']) ? $_SESSION['group'] : '' ?>';
    <?php 
    if(isset($_SESSION['authority']) && $_SESSION['authority'] == 'administrator' || $_SESSION['authority'] == 'manager') {
        echo 'const USER_ROLE = "'.$_SESSION['authority'].'";'; 
    }
    ?>
</script>
    <!-- <script src="<?=$root?>js/user-list.js"></script> -->
    <!-- <link rel="stylesheet" href="<?=$root?>css/user-list.css"> -->
<?php
}
?>

<script type="text/javascript" src="<?=$root?>js/library/jquery-ui.min.js"></script>
<script type="text/javascript" src="<?=$root?>js/application.js"></script>

</body>
</html>
