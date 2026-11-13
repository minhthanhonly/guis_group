<?php
/**
 * 建物 (parent project), read-only: the fields as section tables in two columns — the layout of
 * the project detail page (.project-detail-grid > .pd-col > .pd-section, CSS in demo.css).
 * Only fields that have a value are listed. Vue fragment used by the building page
 * (parent_project/detail.php) and by 建物情報 in the shared modal; $ppv holds the Vue
 * expressions of the host:
 *   p (the building object), receiver, requestDate, type1 / type2 (lists), hasNonRes,
 *   nonResLabel, requests (list), reqFulfilled / reqBadge / reqBadgeUnfulfilled (method
 *   names), materials (list), features (list)
 * Wrap it in <div class="project-detail-card"><div class="card-body">…</div></div>.
 */
$P = $ppv['p'];
?>
<div class="row g-3 project-detail-grid parent-project-view-grid">
    <!-- 顧客: spans both columns -->
    <div class="pd-section pd-span">
        <div class="col-12 project-form-section">
            <h6 class="project-form-section-title"><span data-i18n="顧客">顧客</span></h6>
        </div>
        <?php $customerListViewRowDetail = true; $customerListViewBare = true; include __DIR__ . '/customer-list-view.php'; unset($customerListViewRowDetail, $customerListViewBare); ?>
    </div>
    <div class="pd-col">
        <div class="pd-section">
            <div class="col-12 project-form-section">
                <h6 class="project-form-section-title"><span data-i18n="基本情報">基本情報</span></h6>
            </div>
            <div class="col-12" v-if="<?= $P ?>.project_name">
                <label class="form-label"><span data-i18n="お施主様名">お施主様名</span></label>
                <div class="pd-value">{{ <?= $P ?>.project_name }}</div>
            </div>
            <div class="col-12" v-if="<?= $P ?>.project_number">
                <label class="form-label"><span data-i18n="管理番号">管理番号</span></label>
                <div class="pd-value">{{ <?= $P ?>.project_number }}</div>
            </div>
            <div class="col-12" v-if="<?= $P ?>.construction_number">
                <label class="form-label"><span data-i18n="工事番号">工事番号</span></label>
                <div class="pd-value">{{ <?= $P ?>.construction_number }}</div>
            </div>
            <div class="col-12" v-if="<?= $P ?>.request_date">
                <label class="form-label"><span data-i18n="依頼日">依頼日</span></label>
                <div class="pd-value">{{ <?= $ppv['requestDate'] ?> }}</div>
            </div>
            <div class="col-12" v-if="<?= $ppv['receiver'] ?>">
                <label class="form-label"><span data-i18n="GUIS受付者">GUIS受付者</span></label>
                <div class="pd-value">{{ <?= $ppv['receiver'] ?> }}</div>
            </div>
        </div>
        <div class="pd-section" v-if="<?= $P ?>.scale || <?= $P ?>.city || <?= $P ?>.structural_office || (<?= $ppv['type1'] ?>).length || (<?= $ppv['type2'] ?>).length || (<?= $ppv['hasNonRes'] ?>)">
            <div class="col-12 project-form-section">
                <h6 class="project-form-section-title"><span data-i18n="建物情報">建物情報</span></h6>
            </div>
            <div class="col-12" v-if="<?= $P ?>.scale">
                <label class="form-label"><span data-i18n="建物規模">建物規模</span></label>
                <div class="pd-value">{{ <?= $P ?>.scale }}</div>
            </div>
            <div class="col-12" v-if="<?= $P ?>.city">
                <label class="form-label"><span data-i18n="市区町村">市区町村</span></label>
                <div class="pd-value">{{ <?= $P ?>.city }}</div>
            </div>
            <div class="col-12" v-if="<?= $P ?>.structural_office">
                <label class="form-label"><span data-i18n="構造事務所">構造事務所</span></label>
                <div class="pd-value">{{ <?= $P ?>.structural_office }}</div>
            </div>
            <div class="col-12" v-if="(<?= $ppv['type1'] ?>).length">
                <label class="form-label"><span data-i18n="種類1">種類1</span></label>
                <div class="pd-value">
                    <span v-for="item in <?= $ppv['type1'] ?>" :key="'t1_' + item" class="badge bg-primary me-1">{{ item }}</span>
                </div>
            </div>
            <div class="col-12" v-if="(<?= $ppv['type2'] ?>).length">
                <label class="form-label"><span data-i18n="住宅種類">住宅種類</span></label>
                <div class="pd-value">
                    <span v-for="item in <?= $ppv['type2'] ?>" :key="'t2_' + item" class="badge bg-primary me-1">{{ item }}</span>
                </div>
            </div>
            <div class="col-12" v-if="(<?= $ppv['hasNonRes'] ?>) && (<?= $ppv['nonResLabel'] ?>)">
                <label class="form-label"><span data-i18n="非住宅の主要用途">非住宅の主要用途</span></label>
                <div class="pd-value">{{ <?= $ppv['nonResLabel'] ?> }}</div>
            </div>
        </div>
    </div>
    <div class="pd-col">
        <div class="pd-section" v-if="(<?= $ppv['requests'] ?>).length || (<?= $ppv['materials'] ?>).length || (<?= $ppv['features'] ?>).length">
            <div class="col-12 project-form-section">
                <h6 class="project-form-section-title"><span data-i18n="依頼・資料">依頼・資料</span></h6>
            </div>
            <div class="col-12" v-if="(<?= $ppv['requests'] ?>).length">
                <label class="form-label"><span data-i18n="依頼">依頼</span></label>
                <div class="pd-value d-flex flex-wrap gap-1 align-items-center">
                    <span v-for="item in <?= $ppv['requests'] ?>" :key="'rq_' + item" class="badge"
                          :class="<?= $ppv['reqFulfilled'] ?>(item) ? <?= $ppv['reqBadge'] ?>(item) : <?= $ppv['reqBadgeUnfulfilled'] ?>(item)"
                          :title="<?= $ppv['reqFulfilled'] ?>(item) ? '' : '未作成'">
                        <i v-if="!<?= $ppv['reqFulfilled'] ?>(item)" class="fa fa-exclamation-triangle me-1"></i>
                        <i v-else class="fa fa-check me-1"></i>
                        {{ item }}
                    </span>
                </div>
            </div>
            <div class="col-12" v-if="(<?= $ppv['materials'] ?>).length">
                <label class="form-label"><span data-i18n="お客様提供資料">お客様提供資料</span></label>
                <div class="pd-value d-flex flex-wrap gap-1 align-items-center">
                    <span v-for="item in <?= $ppv['materials'] ?>" :key="'mt_' + item" class="me-2">
                        <i class="fa fa-check text-success me-1"></i>{{ item }}
                        <span v-if="item === 'その他' && <?= $P ?>.materials_memo" class="small text-muted ms-1">({{ <?= $P ?>.materials_memo }})</span>
                    </span>
                </div>
            </div>
            <div class="col-12" v-if="(<?= $ppv['features'] ?>).length">
                <label class="form-label"><span data-i18n="特徴">特徴</span></label>
                <div class="pd-value d-flex flex-wrap gap-1 align-items-center">
                    <span v-for="opt in <?= $ppv['features'] ?>" :key="'ft_' + opt" class="badge badge-outline-primary">{{ opt }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
