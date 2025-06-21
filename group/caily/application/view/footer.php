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

<link rel="stylesheet" href="<?=$root?>assets/vendor/libs/flatpickr/flatpickr.css" />
<link rel="stylesheet" href="<?=$root?>assets/vendor/libs/flatpickr/monthSelect.css">
<script src="<?=$root?>assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js"></script>
<script src="<?=$root?>assets/vendor/libs/flatpickr/flatpickr.js"></script>
<script src="<?=$root?>assets/vendor/libs/flatpickr/monthSelect.js"></script>
<script src="<?=$root?>assets/vendor/libs/flatpickr/ja.js"></script> 



<script src="<?=$root?>assets/js/main.js?v=<?=CACHE_VERSION?>"></script>
<script src="<?=$root?>assets/js/app-chat.js?v=<?=CACHE_VERSION?>"></script>
<link rel="stylesheet" href="<?=$root?>assets/css/app-chat.css?v=<?=CACHE_VERSION?>">
<script src="<?=$root?>assets/js/forms-tagify.js"></script>
<?php
if(isset($_SESSION['userid'])) {
?>
<script>
    const USER_ID = '<?= isset($_SESSION['userid']) ? $_SESSION['userid'] : '' ?>';
    const USER_GROUP = '<?= isset($_SESSION['group']) ? $_SESSION['group'] : '' ?>';
    <?php 
        echo 'const USER_ROLE = "'.$_SESSION['authority'].'";'; 
    ?>
</script>
<?php
}
?>

<script type="text/javascript" src="<?=$root?>js/library/jquery-ui.min.js"></script>
<script type="text/javascript" src="<?=$root?>js/application.js?v=<?=CACHE_VERSION?>"></script>

<!-- Notification System -->
<script src="<?=$root?>assets/js/notification.js?v=<?=CACHE_VERSION?>"></script>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

</body>
</html>
