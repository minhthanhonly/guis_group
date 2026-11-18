<?php
/**
 * Parent-building link + info box shown next to the back button on the project pages
 * (same markup as gantt.php): 建物詳細 button and お施主様名 / 工事番号 box.
 *
 * Expects before include:
 *   $breadcrumbVar   name of the Vue data property holding the project ('project', 'projectInfo')
 */
$bcVar = isset($breadcrumbVar) ? $breadcrumbVar : 'project';
?>
<a v-if="<?= $bcVar ?> && <?= $bcVar ?>.parent_project_id" :href="'../parent_project/detail.php?id=' + <?= $bcVar ?>.parent_project_id" class="btn btn-outline-primary ms-2">
    <i class="fa fa-external-link me-2"></i>
    <span data-i18n="建物詳細">建物詳細</span>
</a>
<span v-if="<?= $bcVar ?> && (<?= $bcVar ?>.parent_project_name || <?= $bcVar ?>.parent_construction_number)"
      class="ms-3 d-inline-flex flex-wrap align-items-center gap-3 border rounded px-3 py-2 align-middle">
    <span v-if="<?= $bcVar ?>.parent_project_name" class="d-inline-flex align-items-center gap-1">
        <span class="badge bg-label-primary" data-i18n="お施主様名">お施主様名</span>
        <span class="fw-semibold">{{ <?= $bcVar ?>.parent_project_name }}</span>
    </span>
    <span v-if="<?= $bcVar ?>.parent_construction_number" class="d-inline-flex align-items-center gap-1">
        <span class="badge bg-label-info" data-i18n="工事番号">工事番号</span>
        <span class="fw-semibold">{{ <?= $bcVar ?>.parent_construction_number }}</span>
    </span>
</span>
