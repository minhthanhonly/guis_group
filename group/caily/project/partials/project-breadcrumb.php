<?php
/**
 * Hierarchy trail shown at the top of the project pages: 建物一覧 › 建物 › 案件 › (タスク …).
 *
 * Expects before include:
 *   $breadcrumbVar   name of the Vue data property holding the project ('project', 'projectInfo')
 *   $breadcrumbLeaf  label of the page below the project ('' on the project page itself,
 *                    e.g. 'タスク' on the task page); when set, the 案件 crumb links back to it
 *   $project_id      id of the project
 */
$bcVar = isset($breadcrumbVar) ? $breadcrumbVar : 'project';
$bcLeaf = isset($breadcrumbLeaf) ? (string)$breadcrumbLeaf : '';
$bcProjectId = isset($project_id) ? (int)$project_id : 0;
?>
<nav aria-label="breadcrumb" class="project-breadcrumb">
    <ol class="breadcrumb mb-0 align-items-center flex-wrap">
        <li class="breadcrumb-item">
            <a href="../parent_project/"><i class="fa fa-building me-1"></i><span data-i18n="建物一覧">建物一覧</span></a>
        </li>
        <li class="breadcrumb-item" v-if="<?= $bcVar ?> && <?= $bcVar ?>.parent_project_id">
            <a :href="'../parent_project/detail.php?id=' + <?= $bcVar ?>.parent_project_id"
               :title="<?= $bcVar ?>.parent_project_name || ''">
                <span v-if="<?= $bcVar ?>.parent_project_name" class="project-breadcrumb-name">{{ <?= $bcVar ?>.parent_project_name }}</span>
                <span v-else data-i18n="建物詳細">建物詳細</span>
            </a>
            <span v-if="<?= $bcVar ?>.parent_construction_number" class="badge bg-label-info ms-1"
                  title="工事番号">{{ <?= $bcVar ?>.parent_construction_number }}</span>
        </li>
<?php if ($bcLeaf === ''): ?>
        <li class="breadcrumb-item active" aria-current="page">
            <span data-i18n="案件">案件</span> #<?= $bcProjectId ?>
            <span v-if="<?= $bcVar ?> && <?= $bcVar ?>.department_name" class="ms-1">{{ <?= $bcVar ?>.department_name }}</span>
        </li>
<?php else: ?>
        <li class="breadcrumb-item">
            <a href="detail.php?id=<?= $bcProjectId ?>">
                <span data-i18n="案件">案件</span> #<?= $bcProjectId ?>
                <span v-if="<?= $bcVar ?> && <?= $bcVar ?>.department_name" class="ms-1">{{ <?= $bcVar ?>.department_name }}</span>
            </a>
        </li>
        <li class="breadcrumb-item active" aria-current="page">
            <span data-i18n="<?= htmlspecialchars($bcLeaf, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($bcLeaf, ENT_QUOTES, 'UTF-8') ?></span>
        </li>
<?php endif; ?>
    </ol>
</nav>
