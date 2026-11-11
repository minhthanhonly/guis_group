<?php
require_once('../application/loader.php');
$view->heading('建物一覧');
?>
<div id="app" class="container-fluid mt-4" v-cloak>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><span data-i18n="建物一覧">建物一覧</span></h5>
                        
                            <div>
                                <div class="d-inline-flex justify-content-end align-items-center me-2">
                                    <div class="dropdown" v-if="availableColumns && availableColumns.length > 0">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fa fa-columns me-1"></i><span data-i18n="列の表示">列の表示</span>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="columnVisibilityDropdown" style="max-height: 400px; overflow-y: auto; min-width: 200px;">
                                            <li v-for="column in availableColumns" :key="column.key" class="dropdown-item-text px-3 py-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" 
                                                        type="checkbox" 
                                                   v-model="column.visible"
                                                   @change="onColumnVisibilityChange"
                                                        :id="'pp-col-' + column.key">
                                                    <label class="form-check-label" :for="'pp-col-' + column.key" style="cursor: pointer;">
                                                        {{ column.label }}
                                                    </label>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <!-- <a v-if="canManagePriceList" href="../price_list/index.php" class="btn btn-outline-info btn-sm me-2">
                                    <i class="fa fa-list me-1"></i> <span data-i18n="価格表管理">価格表管理</span>
                                </a> -->
                                <a v-if="canCreateParentProject" href="create.php" class="btn btn-primary btn-sm" @click="clearParentProjectHighlight">
                                    <i class="fa fa-plus me-1"></i> <span data-i18n="建物登録">建物登録</span>
                                </a>
                            </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Search and Filter -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="input-group">
                                <input type="text" class="form-control" v-model="searchKeyword" :placeholder="translatePlaceholder('検索...')" @input="onSearch" @blur="onSearchBlur">
                                <button class="btn btn-outline-secondary" type="button" @click="clearSearch">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-auto">
                            <div class="btn-group flex-wrap request-filter-btn-group" role="group" aria-label="依頼フィルタ">
                                <button
                                    v-for="opt in requestFilterOptions"
                                    :key="opt.value === '' ? 'all' : opt.value"
                                    type="button"
                                    class="btn btn-sm request-filter-btn"
                                    :data-i18n="opt.label"
                                    :class="{
                                        [`btn-label-${opt.color}`]: requestFilter !== opt.value,
                                        [`btn-${opt.color}`]: requestFilter === opt.value,
                                        'active': requestFilter === opt.value
                                    }"
                                    @click="selectRequestFilter(opt.value)"
                                >
                                    {{ opt.label }}
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="favoritesOnly" v-model="favoritesOnly" @change="onFavoritesFilterChange">
                                <label class="form-check-label" for="favoritesOnly">
                                    <i class="fa fa-star text-warning me-1"></i><span data-i18n="お気に入りのみ">お気に入りのみ</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-center" v-if="favoritesOnly">
                            <button class="btn btn-outline-danger btn-sm" @click="clearAllFavorites" :disabled="loading">
                                <i class="fa fa-trash me-1"></i><span data-i18n="お気に入りをすべて削除">お気に入りをすべて削除</span>
                            </button>
                        </div>
                        
                    </div>

                    <!-- Parent Projects Table -->
                    <p class="small text-muted mb-2" id="parentProjectTableScrollHint">
                        <i class="fa fa-info-circle me-1 text-info"></i><span data-i18n="Spaceを押したままドラッグで表を横スクロール">Spaceを押したままドラッグで表を横スクロール</span>
                    </p>
                    <div v-if="loading" class="text-center py-4">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2"><span data-i18n="読み込み中">読み込み中</span>...</p>
                    </div>
                    <div v-else class="table-responsive parent-projects-table-scroll" id="parentProjectsTableScroll">
                        <table class="table table-hover parent-projects-table">
                            <thead>
                                <tr>
                                    <th class="text-center"><span data-i18n="お気に入り">お気に入り</span></th>
                                    <th v-if="isColumnVisible('project_number')" @click="sortBy('project_number')" style="cursor: pointer;" class="user-select-none">
                                        <span data-i18n="管理番号">管理番号</span>
                                        <i class="fa fa-fw" :class="getSortIcon('project_number')"></i>
                                    </th>
                                    <th v-if="isColumnVisible('project_name')" @click="sortBy('project_name')" style="cursor: pointer;" class="user-select-none parent-project-name-column">
                                        <span data-i18n="お施主様名">お施主様名</span>
                                        <i class="fa fa-fw" :class="getSortIcon('project_name')"></i>
                                    </th>
                                    <th v-if="isColumnVisible('construction_number')" @click="sortBy('construction_number')" style="cursor: pointer;" class="user-select-none">
                                        <span data-i18n="工事番号">工事番号</span>
                                        <i class="fa fa-fw" :class="getSortIcon('construction_number')"></i>
                                    </th>
                                    <th v-if="isColumnVisible('company_name')" @click="sortBy('company_name')" style="cursor: pointer;" class="user-select-none">
                                        <span data-i18n="会社名">会社名</span>
                                        <i class="fa fa-fw" :class="getSortIcon('company_name')"></i>
                                    </th>
                                    <th v-if="isColumnVisible('branch_name')" @click="sortBy('branch_name')" style="cursor: pointer;" class="user-select-none parent-branch-name-column">
                                        <span data-i18n="支店名">支店名</span>
                                        <i class="fa fa-fw" :class="getSortIcon('branch_name')"></i>
                                    </th>
                                    <th v-if="isColumnVisible('contact_name')" @click="sortBy('contact_name')" style="cursor: pointer;" class="user-select-none">
                                        <span data-i18n="担当様">担当様</span>
                                        <i class="fa fa-fw" :class="getSortIcon('contact_name')"></i>
                                    </th>
                                    <th v-if="isColumnVisible('scale')" @click="sortBy('scale')" style="cursor: pointer;" class="user-select-none">
                                        <span data-i18n="規模">規模</span>
                                        <i class="fa fa-fw" :class="getSortIcon('scale')"></i>
                                    </th>
                                    <th v-if="isColumnVisible('type1')" @click="sortBy('type1')" style="cursor: pointer;" class="user-select-none">
                                        <span data-i18n="種類1">種類1</span>
                                        <i class="fa fa-fw" :class="getSortIcon('type1')"></i>
                                    </th>
                                    <th v-if="isColumnVisible('type2')" @click="sortBy('type2')" style="cursor: pointer;" class="user-select-none">
                                        <span data-i18n="住宅種類">住宅種類</span>
                                        <i class="fa fa-fw" :class="getSortIcon('type2')"></i>
                                    </th>
                                    <th v-if="isColumnVisible('requests')" @click="sortBy('requests')" style="cursor: pointer;" class="user-select-none parent-requests-column">
                                        <span data-i18n="依頼">依頼</span>
                                        <i class="fa fa-fw" :class="getSortIcon('requests')"></i>
                                    </th>
                                    <th v-if="isColumnVisible('child_project_count')" @click="sortBy('child_project_count')" style="cursor: pointer;" class="user-select-none">
                                        <span data-i18n="件数">件数</span>
                                        <i class="fa fa-fw" :class="getSortIcon('child_project_count')"></i>
                                    </th>
                                    <th v-if="isColumnVisible('nearest_nouki')" @click="sortBy('nearest_nouki')" style="cursor: pointer;" class="user-select-none">
                                        <span data-i18n="納期">納期</span>
                                        <i class="fa fa-fw" :class="getSortIcon('nearest_nouki')"></i>
                                    </th>
                                    <th v-if="isColumnVisible('created_by_name')" @click="sortBy('created_by_name')" style="cursor: pointer;" class="user-select-none">
                                        <span data-i18n="作成者">作成者</span>
                                        <i class="fa fa-fw" :class="getSortIcon('created_by_name')"></i>
                                    </th>
                                    <th v-if="isColumnVisible('notes')">
                                        <span data-i18n="メモ">メモ</span>
                                    </th>
                                    <th v-if="isColumnVisible('created_at')" @click="sortBy('created_at')" style="cursor: pointer;" class="user-select-none">
                                        <span data-i18n="作成日">作成日</span>
                                        <i class="fa fa-fw" :class="getSortIcon('created_at')"></i>
                                    </th>
                                    <th v-if="isColumnVisible('request_date')" @click="sortBy('request_date')" style="cursor: pointer;" class="user-select-none">
                                        <span data-i18n="依頼日">依頼日</span>
                                        <i class="fa fa-fw" :class="getSortIcon('request_date')"></i>
                                    </th>
                                    <th><span data-i18n="操作">操作</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="project in filteredParentProjects" :key="project.id"
                                    :class="{ 'parent-project-row-saved': isHighlightedParentProject(project) }"
                                    @contextmenu.prevent="onParentProjectContextMenu($event, project)">
                                        <td class="text-center">
                                            <div class="d-flex flex-column align-items-center gap-1">
                                                <i class="fa fa-star" 
                                                   :class="project.is_favorite == 1 ? 'text-warning' : 'text-muted'"
                                                   style="cursor: pointer; font-size: 1.2em;"
                                                   @click="toggleFavorite(project)"
                                                   :title="project.is_favorite == 1 ? 'お気に入りから削除' : 'お気に入りに追加'"></i>
                                                <span v-if="isNewParentProject(project.created_at)"
                                                      class="badge bg-success"
                                                      style="font-size: 0.65rem; padding: 0.15rem 0.35rem; white-space: nowrap;"
                                                      data-i18n="NEW">NEW</span>
                                            </div>
                                        </td>
                                        <td v-if="isColumnVisible('project_number')">
                                            <span class="badge bg-label-info">{{ project.project_number || '-' }}</span>
                                        </td>
                                        <td v-if="isColumnVisible('project_name')" class="parent-project-name-column">
                                            <a :href="'detail.php?id=' + project.id" class="text-decoration-none parent-project-name-link"
                                               :title="'詳細を表示: ' + project.project_name">
                                                {{ project.project_name }}
                                            </a>
                                        </td>
                                        <td v-if="isColumnVisible('construction_number')">{{ project.construction_number || '-' }}</td>
                                        <td v-if="isColumnVisible('company_name')">
                                            {{ project.company_name }}
                                            <span v-if="Number(project.customer_count) > 1" class="badge bg-label-primary ms-1"
                                                :title="'他の顧客: ' + (Number(project.customer_count) - 1) + '件'">+{{ Number(project.customer_count) - 1 }}</span>
                                        </td>
                                        <td v-if="isColumnVisible('branch_name')" class="parent-branch-name-column">{{ project.branch_name || '-' }}</td>
                                        <td v-if="isColumnVisible('contact_name')">{{ project.contact_name || '-' }}</td>
                                        <td v-if="isColumnVisible('scale')">{{ project.scale || '-' }}</td>
                                        <td v-if="isColumnVisible('type1')">
                                            <span v-if="project.type1">
                                                <span v-for="item in project.type1.split(',').map(v => v.trim()).filter(v => v)" 
                                                      :key="item"
                                                      class="badge bg-info me-1">
                                                    {{ item }}
                                                </span>
                                            </span>
                                            <span v-else>-</span>
                                        </td>
                                        <td v-if="isColumnVisible('type2')">
                                            <span v-if="project.type2">
                                                <span v-for="item in project.type2.split(',').map(v => v.trim()).filter(v => v)" 
                                                      :key="item"
                                                      class="badge bg-info me-1">
                                                    {{ item }}
                                                </span>
                                            </span>
                                            <span v-else>-</span>
                                        </td>
                                        <td v-if="isColumnVisible('requests')" class="parent-requests-column">
                                            <span v-if="project.requests">
                                                <span v-for="item in project.requests.split(',').map(v => v.trim()).filter(v => v)"
                                                      :key="item"
                                                      class="badge me-1 mb-1"
                                                      :class="isParentRequestFulfilled(project, item) ? getRequestBadgeClass(item) : getRequestUnfulfilledBadgeClass(item)"
                                                      :title="isParentRequestFulfilled(project, item) ? '' : '未作成'">
                                                    <i v-if="!isParentRequestFulfilled(project, item)" class="fa fa-exclamation-triangle me-1"></i>
                                                    {{ item }}
                                                </span>
                                            </span>
                                            <span v-else>-</span>
                                        </td>
                                        <td v-if="isColumnVisible('child_project_count')" style="white-space: nowrap;">
                                            <span v-if="project.child_project_count > 0">
                                                <span class="badge bg-info me-2"
                                                      :title="project.child_project_count + ' 個の案件があります'">
                                                    {{ project.child_project_count }}
                                                </span>
                                                <button type="button"
                                                        class="btn btn-outline-primary btn-child-list"
                                                        @click="openChildProjectsWindow(project)">
                                                    子一覧
                                                </button>
                                            </span>
                                            <span v-else class="text-muted">0</span>
                                        </td>
                                        <td v-if="isColumnVisible('nearest_nouki')">
                                            <template v-if="project.nearest_nouki_list && project.nearest_nouki_list.length">
                                                <div v-for="(item, nIdx) in project.nearest_nouki_list"
                                                     :key="(item.department_name || '') + '-' + (item.project_order_type || '') + '-' + (item.deadline || '') + '-' + nIdx"
                                                     class="d-flex align-items-center gap-1 mb-1"
                                                     style="white-space: nowrap;">
                                                    <span class="badge"
                                                          :class="getDepartmentNoukiBadgeClass(item.department_name)"
                                                          :title="item.department_name || ''">{{ getNearestNoukiCombinedLabel(item) }}</span>
                                                    <span>{{ formatDate(item.deadline) }}</span>
                                                </div>
                                            </template>
                                            <span v-else class="text-muted">-</span>
                                        </td>
                                        <td v-if="isColumnVisible('created_by_name')">{{ project.created_by_name || '-' }}</td>
                                        <td v-if="isColumnVisible('notes')" class="confirmation-notes-column">
                                            <div class="confirmation-notes-wrapper" style="max-width: 300px; max-height: 200px; overflow-y: auto;">
                                                <template v-if="parseNotesDisplay(project.notes_display || '').length > 0">
                                                    <div v-for="note in parseNotesDisplay(project.notes_display)" :key="note.id" class="confirmation-note-item mb-1" :data-note-id="note.id">
                                                        <span class="note-text small" style="white-space: pre-wrap;">{{ note.content }}</span>
                                                        <span class="note-actions d-none ms-1">
                                                            <span class="note-edit-icon me-1" title="メモを編集" style="cursor: pointer;" @click.prevent="openNoteEdit(project, note)">
                                                                <i class="fa fa-pencil-alt"></i>
                                                            </span>
                                                            <span class="note-delete-icon text-danger" title="メモを削除" style="cursor: pointer;" @click.prevent="deleteNoteFromList(project, note.id)">
                                                                <i class="fa fa-trash"></i>
                                                            </span>
                                                        </span>
                                                    </div>
                                                </template>
                                                <span v-else class="text-muted">-</span>
                                            </div>
                                        </td>
                                        <td v-if="isColumnVisible('created_at')">{{ formatDate(project.created_at) }}</td>
                                        <td v-if="isColumnVisible('request_date')">{{ formatDate(project.request_date) }}</td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a :href="'detail.php?id=' + project.id" class="btn btn-outline-primary" 
                                                   title="詳細を表示">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                                <button v-if="canCreateParentProject"
                                                        type="button"
                                                        class="btn btn-outline-info"
                                                        @click="copyParentProject(project)"
                                                        title="建物をコピー"
                                                        :disabled="copyingParentProjectId === project.id">
                                                    <i class="fa fa-copy"></i>
                                                </button>
                                                <button v-if="isAdministrator"
                                                        type="button"
                                                        class="btn btn-outline-danger"
                                                        @click="deleteParentProject(project)"
                                                        title="建物を削除（管理者）"
                                                        :disabled="deletingParentProjectId === project.id">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                </tr>
                                <tr v-if="filteredParentProjects.length === 0">
                                    <td colspan="15" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="fa fa-inbox fa-2x mb-2"></i>
                                            <p>建物が見つかりません</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted">
                            全 {{ totalRecords }} 件中 {{ startRecord }}-{{ endRecord }} 件を表示
                            <span v-if="hasActiveFilters" class="ms-2">
                                <span class="badge bg-label-warning"><i class="fa fa-filter me-1"></i><span data-i18n="絞り込み中">絞り込み中</span></span>
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-1" @click="clearAllFilters">
                                    <i class="fa fa-times me-1"></i><span data-i18n="絞り込みを解除">絞り込みを解除</span>
                                </button>
                            </span>
                        </div>
                        <nav v-if="totalPages > 1">
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item" :class="{ disabled: currentPage === 1 }">
                                    <a class="page-link" href="#" @click.prevent="changePage(currentPage - 1)">前へ</a>
                                </li>
                                <li v-for="page in visiblePages" :key="page" class="page-item" :class="{ active: page === currentPage }">
                                    <a class="page-link" href="#" @click.prevent="changePage(page)">{{ page }}</a>
                                </li>
                                <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                                    <a class="page-link" href="#" @click.prevent="changePage(currentPage + 1)">次へ</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Child Projects Modal -->
    <div class="modal fade" id="childProjectsModal" tabindex="-1" aria-labelledby="childProjectsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable child-projects-modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="childProjectsModalLabel">
                        子案件一覧 - {{ selectedParentProject?.project_name || '' }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div v-if="loadingChildProjects" class="text-center py-4">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2"><span data-i18n="読み込み中">読み込み中</span>...</p>
                    </div>
                    <div v-else>
                        <div v-if="childProjectsForModal.length > 0" class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th class="text-center"><i class="fa fa-star text-muted" title="お気に入り"></i></th>
                                        <th><span data-i18n="ID">ID</span></th>
                                        <th><span data-i18n="受注形態">受注形態</span></th>
                                        <th style="min-width: 150px;"><span data-i18n="案件名">案件名</span></th>
                                        <th style="min-width: 100px;"><span data-i18n="部署">部署</span></th>
                                        <th><span data-i18n="管理">管理</span></th>
                                        <th><span>担当</span></th>
                                        <th><span>CAILY納期</span></th>
                                        <th><span>GUIS納期</span></th>
                                        <th><span data-i18n="開始日">開始日</span></th>
                                        <th><span data-i18n="期限日">期限日</span></th>
                                        <th><span data-i18n="ステータス">ステータス</span></th>
                                        <th><span data-i18n="進捗">進捗</span></th>
                                        <th><span data-i18n="総額">総額</span></th>
                                        <th><span data-i18n="操作">操作</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="child in childProjectsForModal" :key="child.id">
                                        <td class="text-center">
                                            <i class="fa fa-star" 
                                               :class="child.is_favorite == 1 ? 'text-warning' : 'text-muted'"
                                               style="cursor: pointer; font-size: 1.1em;"
                                               @click="toggleChildFavorite(child)"
                                               :title="child.is_favorite == 1 ? 'お気に入りから削除' : 'お気に入りに追加'"></i>
                                        </td>
                                        <td><span class="badge bg-primary border me-1">{{ child.id || '-' }}</span></td>
                                        <td>
                                            <span v-if="child.project_order_type && child.project_order_type.split(',').length > 0">
                                                <span v-for="item in child.project_order_type.split(',')" 
                                                      :key="item.trim()" 
                                                      class="badge me-1" 
                                                      :class="getOrderTypeBadgeClass(item.trim())">
                                                    {{ item.trim() }}
                                                </span>
                                            </span>
                                            <span v-else>-</span>
                                        </td>
                                        <td style="min-width: 150px;">
                                            <a :href="'../project/detail.php?id=' + child.id"
                                               class="text-decoration-none">
                                                {{ child.name }}
                                            </a>
                                        </td>
                                        <td style="min-width: 100px;">{{ child.department_name || '-' }}</td>
                                        <td>
                                            <div class="d-flex align-items-center" v-if="child.manager_id && child.manager_id.split('|').filter(m => m.trim() !== '').length > 0">
                                                <template v-for="(manager, index) in child.manager_id.split('|').filter(m => m.trim() !== '')" :key="manager">
                                                    <div v-if="index < 1" 
                                                        class="avatar avatar-sm me-1"
                                                        data-bs-toggle="tooltip"
                                                        :title="getManagerName(manager)">
                                                        <span class="avatar-initial rounded-circle bg-label-primary pull-up">
                                                            {{ getManagerInitials(manager) }}
                                                        </span>
                                                        <img v-if="getManagerImage(manager)" 
                                                            :src="'/assets/upload/avatar/' + getManagerImage(manager)" 
                                                            alt="avatar" 
                                                            class="rounded-circle pull-up"
                                                            style="display:none;"
                                                            @load="$event.target.style.display='block'; if ($event.target.previousElementSibling) $event.target.previousElementSibling.style.display='none';"
                                                            @error="$event.target.remove()">
                                                    </div>
                                                </template>
                                                <span v-if="child.manager_id.split('|').filter(m => m.trim() !== '').length > 1" 
                                                    class="avatar-initial rounded-circle bg-label-primary pull-up" 
                                                    data-bs-toggle="tooltip" 
                                                    :title="getRemainingManagers(child.manager_id)"
                                                    style="display:inline-flex;">
                                                    +{{ child.manager_id.split('|').filter(m => m.trim() !== '').length - 1 }}
                                                </span>
                                            </div>
                                            <span v-else class="text-muted">-</span>
                                        </td>
                                        <td>{{ child.tantou || '-' }}</td>
                                        <td>{{ child.caily_nouki ? formatDateTime(child.caily_nouki) : '-' }}</td>
                                        <td>{{ child.guis_nouki ? formatDateTime(child.guis_nouki) : '-' }}</td>
                                        <td>{{ child.start_date ? formatDateTime(child.start_date) : '-' }}</td>
                                        <td>{{ child.end_date ? formatDateTime(child.end_date) : '-' }}</td>
                                        <td>
                                            <span class="badge" :class="getStatusBadgeClass(child.status)">
                                                {{ getStatusLabel(child.status) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="fw-bold">{{ child.progress || 0 }}%</span>
                                        </td>
                                        <td class="text-end">
                                            <span class="fw-bold text-primary">
                                                {{ formatPrice(child.amount || child.total_amount || 0) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a :href="'../project/detail.php?id=' + child.id" class="btn btn-sm btn-outline-primary" title="子プロジェクト詳細">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center text-muted py-4">
                            <i class="fa fa-folder-open fa-2x mb-2"></i>
                            <p><span data-i18n="子プロジェクトがありません">子プロジェクトがありません</span></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Note Modal (for parent_project メモ) - inside #app -->
    <div class="modal fade" tabindex="-1" :class="{show: showNoteModal}" style="display: block;" v-if="showNoteModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"></h5>
                    <button type="button" class="btn-close" @click="closeNoteModal"></button>
                </div>
                <div class="modal-body">
                    <!-- View mode -->
                    <div v-if="editingNote.id && !isNoteEditMode">
                        <div class="mb-3">
                            <label class="form-label"><span data-i18n="内容">内容</span></label>
                            <div class="form-control" style="min-height:100px;white-space:pre-line;max-height:300px;overflow-y:auto;">{{ editingNote.content || '-' }}</div>
                        </div>
                        <div class="mb-3" v-if="editingNote.is_important">
                            <label class="form-label"><span data-i18n="重要メモ">重要メモ</span></label>
                            <div>
                                <i class="fa fa-exclamation-circle text-danger"></i>
                            </div>
                        </div>
                    </div>
                    <!-- Edit mode -->
                    <form v-else @submit.prevent="saveNote">
                        <div class="mb-3">
                            <label class="form-label"><span data-i18n="内容">内容</span></label>
                            <textarea class="form-control" v-model="editingNote.content" rows="6" placeholder="メモの詳細を入力してください..."></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" v-model="editingNote.is_important" id="ppNoteIsImportant">
                                <label class="form-check-label" for="ppNoteIsImportant">
                                    <i class="fa fa-exclamation-circle text-danger me-2"></i> <span data-i18n="重要メモ">重要メモ</span>
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <template v-if="editingNote.id && !isNoteEditMode">
                        <button class="btn btn-primary" @click="isNoteEditMode = true"><i class="fa fa-pencil-alt me-2"></i> <span data-i18n="編集">編集</span></button>
                        <button class="btn btn-secondary" @click="closeNoteModal"><span data-i18n="閉じる">閉じる</span></button>
                    </template>
                    <template v-else>
                        <button class="btn btn-secondary" @click="isNoteEditMode = false" v-if="editingNote.id"><i class="fa fa-times me-2"></i> <span data-i18n="キャンセル">キャンセル</span></button>
                        <button class="btn btn-secondary" @click="closeNoteModal" v-else><span data-i18n="キャンセル">キャンセル</span></button>
                        <button class="btn btn-primary" @click="saveNote" :disabled="!(editingNote.content && editingNote.content.trim())">
                            <i class="fa fa-save me-2"></i> <span data-i18n="保存">保存</span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Context menu: parent project row -->
    <div v-if="parentProjectContextMenuVisible"
         class="dropdown-menu show parent-project-context-menu"
         :style="{ position: 'absolute', zIndex: 9999, left: parentProjectContextMenuX + 'px', top: parentProjectContextMenuY + 'px' }"
         @click.stop>
        <button v-if="isProjectManager" class="dropdown-item" type="button" @click.stop="openParentProjectEditFromContextMenu">
            <i class="fa fa-edit me-1"></i><span data-i18n="建物編集">建物編集</span>
        </button>
        <button class="dropdown-item" type="button" @click.stop="goToParentProjectDetailFromContextMenu">
            <i class="fa fa-external-link-alt me-1"></i><span data-i18n="詳細ページへ">詳細ページへ</span>
        </button>
        <div class="dropdown-divider"></div>
        <button class="dropdown-item" type="button" @click.stop="addNoteFromContextMenu">
            <i class="fa fa-plus me-1"></i><span data-i18n="メモを追加">メモを追加</span>
        </button>
    </div>
</div>

<?php /* 建物編集 modal (shared standalone app, outside #app) */ include __DIR__ . '/partials/parent-project-quick-modal.php'; ?>

<?php
$view->footing();
?>

<style>
/* 建物一覧 table theme — match project list (grid borders, compact header) */
.parent-projects-table {
    border: 0 !important;
    border-collapse: collapse;
}
.parent-projects-table > :not(caption) > * > * {
    border: 1px solid #aaa;
    padding: 0.4rem 0.5rem !important;
    color: #333;
}
.parent-projects-table thead th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: #495057;
    vertical-align: middle;
    white-space: nowrap;
}
.parent-projects-table thead th[style*="cursor: pointer"]:hover,
.parent-projects-table thead th.user-select-none:hover {
    background-color: #e9ecef;
}
.parent-projects-table tbody td {
    vertical-align: top;
}
[data-bs-theme="dark"] .parent-projects-table > :not(caption) > * > * {
    color: #eee !important;
    border-color: #666;
}
[data-bs-theme="dark"] .parent-projects-table thead th {
    background-color: #333 !important;
    color: #eee !important;
}

.badge {
    font-size: 0.75rem;
    padding: 0.375rem 0.5rem;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

/* Horizontal scroll like project list (do not use overflow:hidden) */
.parent-projects-table-scroll {
    border-radius: 0.375rem;
    overflow-x: auto;
    overflow-y: visible;
    -webkit-overflow-scrolling: touch;
}
.parent-projects-table-scroll.parent-projects-space-pan {
    cursor: grab;
}
.parent-projects-table-scroll.parent-projects-space-pan.is-dragging {
    cursor: grabbing;
    user-select: none;
}
/* Fixed clone of table header while page scrolls (project list UX) */
.parent-projects-sticky-head {
    display: none;
    position: fixed;
    /* top/z-index set in JS from .layout-navbar bottom (clears navbar + blur) */
    z-index: 1002;
    overflow-x: auto;
    overflow-y: hidden;
    background: #fff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
    pointer-events: auto;
    /* hide scrollbar; scrollLeft synced from main table */
    scrollbar-width: none;
}
.parent-projects-sticky-head::-webkit-scrollbar {
    display: none;
}
.parent-projects-sticky-head .parent-projects-sticky-table {
    width: max-content;
    margin-bottom: 0;
    border: 0 !important;
}
.parent-projects-sticky-head .parent-projects-sticky-table thead th {
    background-color: #f8f9fa;
    cursor: pointer;
}
[data-bs-theme="dark"] .parent-projects-sticky-head {
    background: #333;
}
[data-bs-theme="dark"] .parent-projects-sticky-head .parent-projects-sticky-table thead th {
    background-color: #333 !important;
    color: #eee !important;
}
.parent-projects-table-scroll .parent-projects-table {
    width: max-content;
    min-width: 100%;
    margin-bottom: 0;
}
.parent-projects-table-scroll .parent-projects-table th,
.parent-projects-table-scroll .parent-projects-table td {
    white-space: nowrap;
}
/* Allow memo column to wrap / scroll internally */
.parent-projects-table-scroll .parent-projects-table td.confirmation-notes-column {
    white-space: normal;
    min-width: 12rem;
    max-width: 18rem;
}
/* お施主様名: wrap when long */
.parent-projects-table-scroll .parent-projects-table th.parent-project-name-column,
.parent-projects-table-scroll .parent-projects-table td.parent-project-name-column {
    white-space: normal;
    min-width: 8rem;
    max-width: 12rem;
    vertical-align: top;
}
.parent-projects-table-scroll .parent-projects-table .parent-project-name-link {
    display: inline-block;
    max-width: 100%;
    overflow-wrap: anywhere;
    word-break: break-word;
    white-space: normal;
}
/* 支店名: narrower */
.parent-projects-table-scroll .parent-projects-table th.parent-branch-name-column,
.parent-projects-table-scroll .parent-projects-table td.parent-branch-name-column {
    white-space: normal;
    max-width: 5.5rem;
    min-width: 3.5rem;
    overflow-wrap: anywhere;
    word-break: break-word;
}
/* 依頼: narrower, badges can wrap */
.parent-projects-table-scroll .parent-projects-table th.parent-requests-column,
.parent-projects-table-scroll .parent-projects-table td.parent-requests-column {
    white-space: normal;
    max-width: 6.5rem;
    min-width: 4rem;
}

.parent-projects-table.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

.parent-projects-table.table-hover tbody tr.parent-project-row-saved,
.parent-projects-table.table-hover tbody tr.parent-project-row-saved:hover {
    background-color: #d4edda;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .btn-group-sm .btn {
        padding: 0.2rem 0.4rem;
        font-size: 0.8rem;
    }
    
    .badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.4rem;
    }
}

/* Loading animation */
.spinner-border {
    color: #007bff;
}

/* Empty state styling */
.text-muted i {
    opacity: 0.5;
}

/* Clickable child count badge */
.child-count-badge {
    cursor: pointer;
    text-decoration: underline;
}

.child-count-badge:hover {
    background-color: #0dcaf0; /* lighten bg-info */
    color: #000;
}
/* Child projects modal: full width, normal height */
.child-projects-modal-dialog {
    max-width: 100%;
    margin: 0.5rem auto;
}

/* Confirmation notes column styles (similar to project list) */
.confirmation-notes-column {
    min-width: 200px;
    width: 240px;
    max-width: 260px;
}

.confirmation-notes-column .confirmation-note-item.mb-1 {
    background-color: #fff9e6;
    padding: 6px 8px;
    margin-bottom: 6px !important;
    border-radius: 4px;
    border-left: 3px solid #ffd700;
}

.confirmation-notes-column .confirmation-note-item {
    position: relative;
    display: block;
}

.confirmation-notes-column .confirmation-note-item .note-text {
    display: block;
}

.confirmation-notes-column .confirmation-note-item .note-actions {
    position: absolute;
    right: 6px;
    top: 4px;
    align-items: center;
    gap: 2px;
    display: none;
}

.confirmation-notes-column .confirmation-note-item:hover .note-actions {
    display: inline-flex !important;
}

/* Smaller 子一覧 button in 件数 column */
.btn-child-list {
    padding: 0.1rem 0.35rem;
    font-size: 0.7rem;
    line-height: 1.1;
}

/* 依頼 filter button group */
.request-filter-btn-group {
    overflow: visible !important;
}
.request-filter-btn {
    position: relative;
    overflow: visible;
}
.request-filter-btn.active::after {
    content: '';
    position: absolute;
    bottom: -0.55rem;
    left: 50%;
    transform: translateX(-50%);
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: currentColor;
    pointer-events: none;
}
.request-filter-btn.btn-secondary.active::after,
.request-filter-btn.btn-label-secondary.active::after { background-color: #6c757d; }
.request-filter-btn.btn-primary.active::after,
.request-filter-btn.btn-label-primary.active::after { background-color: #7650b0; }
.request-filter-btn.btn-info.active::after,
.request-filter-btn.btn-label-info.active::after { background-color: #0dcaf0; }
.request-filter-btn.btn-success.active::after,
.request-filter-btn.btn-label-success.active::after { background-color: #198754; }
.request-filter-btn.btn-lime.active::after,
.request-filter-btn.btn-label-lime.active::after { background-color: #84cc16; }
.request-filter-btn.btn-dark.active::after,
.request-filter-btn.btn-label-dark.active::after { background-color: #212529; }

/* Edit parent modal: Select2 / Tagify / Flatpickr above modal */
#editParentProjectModal .select2-container {
    width: 100% !important;
}
#editParentProjectModal .select2-container--open {
    z-index: 1065;
}
body > .select2-container--open {
    z-index: 1065 !important;
}
#editParentProjectModal .tagify {
    width: 100%;
}
#editParentProjectModal .flatpickr-calendar,
.flatpickr-calendar.open {
    z-index: 1065 !important;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Global function for showing messages
function showMessage(message, isError = false) {
    if (isError) {
        Swal.fire({
            title: 'エラー',
            text: message,
            icon: 'error',
            confirmButtonText: 'OK'
        });
    } else {
        Swal.fire({
            title: '成功',
            text: message,
            icon: 'success',
            confirmButtonText: 'OK'
        });
    }
}
const IS_PROJECT_MANAGER = <?php echo isset($_SESSION['isProjectManager']) && $_SESSION['isProjectManager'] ? 'true' : 'false'; ?>;
const IS_ADMIN = <?php echo json_encode(($_SESSION['authority'] ?? '') === 'administrator'); ?>;
</script>
<script src="assets/js/parent-project-error.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="assets/js/parent-project-tagify-options.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="assets/js/parent-project-customers-mixin.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="assets/js/parent-project-quick-modal.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="assets/js/parent-project-index.js?v=<?=PROJECT_CACHE_VERSION?>"></script> 