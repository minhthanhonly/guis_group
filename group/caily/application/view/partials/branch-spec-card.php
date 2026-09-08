<?php
/**
 * Branch / locality specs panel.
 * Expects Vue state + methods from BranchSpecPanel (assets/js/branch-spec-panel.js).
 * Optional: $branchSpecCompact = true for smaller card on project detail.
 */
$branchSpecCompact = !empty($branchSpecCompact);
?>
<div class="card border mb-3">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
        <strong data-i18n="支店・地域仕様">支店・地域仕様</strong>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="btn-group btn-group-sm" role="group" v-if="branchSpecDeptTabs.length">
                <button type="button"
                    v-for="tab in branchSpecDeptTabs" :key="tab.key"
                    class="btn"
                    :class="branchSpecActiveDept === tab.key ? 'btn-primary' : 'btn-outline-primary'"
                    @click="branchSpecActiveDept = tab.key; branchSpecScope = ''">
                    {{ tab.label }}
                    <span class="badge bg-label-secondary ms-1">{{ tab.count }}</span>
                </button>
            </div>
            <div class="btn-group btn-group-sm" v-if="branchSpecActiveDept === 'setsubi'">
                <button type="button" class="btn"
                    :class="branchSpecScope === '' ? 'btn-secondary' : 'btn-outline-secondary'"
                    @click="branchSpecScope = ''" data-i18n="すべて">すべて</button>
                <button type="button" class="btn"
                    :class="branchSpecScope === 'electrical' ? 'btn-secondary' : 'btn-outline-secondary'"
                    @click="branchSpecScope = 'electrical'" data-i18n="電気">電気</button>
                <button type="button" class="btn"
                    :class="branchSpecScope === 'sanitary' ? 'btn-secondary' : 'btn-outline-secondary'"
                    @click="branchSpecScope = 'sanitary'" data-i18n="衛生">衛生</button>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" @click="loadBranchSpecs" :disabled="branchSpecLoading">
                <i class="fa fa-refresh" :class="{'fa-spin': branchSpecLoading}"></i>
            </button>
        </div>
    </div>
    <div class="card-body py-2" <?php if ($branchSpecCompact) echo 'style="max-height:420px;overflow:auto"'; ?>>
        <div v-if="branchSpecLoading" class="text-muted small" data-i18n="読み込み中...">読み込み中...</div>
        <div v-else-if="!filteredBranchSpecs.length && !filteredBranchSpecManual.length" class="text-muted small"
            data-i18n="現在の建物情報に一致する仕様はありません">現在の建物情報に一致する仕様はありません</div>
        <template v-else>
            <div v-for="rule in filteredBranchSpecs" :key="'m-'+rule.id" class="border rounded p-2 mb-2">
                <div class="d-flex flex-wrap gap-1 mb-1">
                    <span class="badge bg-label-primary">{{ rule.title_ja || rule.match_note }}</span>
                    <span v-if="rule.apply_electrical == 1" class="badge bg-label-info" data-i18n="電気">電気</span>
                    <span v-if="rule.apply_sanitary == 1" class="badge bg-label-info" data-i18n="衛生">衛生</span>
                    <span v-if="rule.apply_architectural == 1" class="badge bg-label-warning" data-i18n="意匠">意匠</span>
                    <span v-if="rule.match_structure && rule.match_structure !== 'any'" class="badge bg-label-secondary">{{ rule.match_structure }}</span>
                    <span v-for="r in (rule.match_reasons || [])" :key="r" class="badge bg-label-dark">{{ r }}</span>
                </div>
                <div class="small" style="white-space: pre-wrap;">{{ rule.content_ja }}</div>
                <div v-if="rule.content_vi" class="small text-muted mt-1" style="white-space: pre-wrap;">{{ rule.content_vi }}</div>
            </div>
            <div v-if="filteredBranchSpecManual.length" class="mt-2">
                <div class="fw-semibold small mb-1" data-i18n="要確認（手動）">要確認（手動）</div>
                <div v-for="rule in filteredBranchSpecManual" :key="'s-'+rule.id" class="border border-warning rounded p-2 mb-2">
                    <div class="d-flex flex-wrap gap-1 mb-1">
                        <span class="badge bg-warning text-dark">{{ rule.title_ja || rule.match_note }}</span>
                    </div>
                    <div class="small" style="white-space: pre-wrap;">{{ rule.content_ja }}</div>
                    <div v-if="rule.content_vi" class="small text-muted mt-1" style="white-space: pre-wrap;">{{ rule.content_vi }}</div>
                </div>
            </div>
        </template>
    </div>
</div>
