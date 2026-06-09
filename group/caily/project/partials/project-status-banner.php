<?php
$statusBannerVar = isset($statusBannerVar) ? $statusBannerVar : 'project';
?>
<div
    v-if="<?php echo $statusBannerVar; ?> && (<?php echo $statusBannerVar; ?>.status === 'cancelled' || <?php echo $statusBannerVar; ?>.status === 'paused')"
    class="alert mb-3 d-flex align-items-center"
    :class="<?php echo $statusBannerVar; ?>.status === 'cancelled' ? 'alert-danger' : 'alert-warning'"
    role="alert"
>
    <i class="fa fa-exclamation-triangle me-2"></i>
    <span v-if="<?php echo $statusBannerVar; ?>.status === 'cancelled'" data-i18n="この案件は中止されています。">この案件は中止されています。</span>
    <span v-else data-i18n="この案件は一時停止中です。">この案件は一時停止中です。</span>
</div>
