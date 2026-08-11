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
                    <div class="table-responsive">
                        <!-- Loading indicator -->
                        <div v-if="loading" class="text-center py-4">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2"><span data-i18n="読み込み中">読み込み中</span>...</p>
                        </div>
                        
                        <table v-else class="table table-hover">
                            <thead>
                                <tr>
                                    <th class="text-center"><span data-i18n="お気に入り">お気に入り</span></th>
                                    <th v-if="isColumnVisible('project_number')" @click="sortBy('project_number')" style="cursor: pointer;" class="user-select-none">
                                        <span data-i18n="管理番号">管理番号</span>
                                        <i class="fa fa-fw" :class="getSortIcon('project_number')"></i>
                                    </th>
                                    <th v-if="isColumnVisible('project_name')" @click="sortBy('project_name')" style="cursor: pointer;" class="user-select-none">
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
                                    <th v-if="isColumnVisible('branch_name')" @click="sortBy('branch_name')" style="cursor: pointer;" class="user-select-none">
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
                                        <span data-i18n="種類2">種類2</span>
                                        <i class="fa fa-fw" :class="getSortIcon('type2')"></i>
                                    </th>
                                    <th v-if="isColumnVisible('requests')" @click="sortBy('requests')" style="cursor: pointer;" class="user-select-none">
                                        <span data-i18n="依頼">依頼</span>
                                        <i class="fa fa-fw" :class="getSortIcon('requests')"></i>
                                    </th>
                                    <th v-if="isColumnVisible('child_project_count')" @click="sortBy('child_project_count')" style="cursor: pointer;" class="user-select-none">
                                        <span data-i18n="件数">件数</span>
                                        <i class="fa fa-fw" :class="getSortIcon('child_project_count')"></i>
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
                                        <td v-if="isColumnVisible('project_name')">
                                            <div style="max-width: 200px;">
                                                <a :href="'detail.php?id=' + project.id" class="text-decoration-none" 
                                                :title="'詳細を表示: ' + project.project_name">
                                                    {{ project.project_name }}
                                                </a>
                                            </div>            
                                        </td>
                                        <td v-if="isColumnVisible('construction_number')">{{ project.construction_number || '-' }}</td>
                                        <td v-if="isColumnVisible('company_name')">{{ project.company_name }}</td>
                                        <td v-if="isColumnVisible('branch_name')">{{ project.branch_name || '-' }}</td>
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
                                        <td v-if="isColumnVisible('requests')">
                                            <span v-if="project.requests">
                                                <span v-for="item in project.requests.split(',').map(v => v.trim()).filter(v => v)"
                                                      :key="item"
                                                      class="badge me-1 mb-1"
                                                      :class="isParentRequestFulfilled(project, item) ? getRequestBadgeClass(item) : 'bg-warning text-dark'"
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
                                                        class="avatar me-1"
                                                        data-bs-toggle="tooltip"
                                                        :title="getManagerName(manager)">
                                                        <img v-if="getManagerImage(manager)" 
                                                            :src="'/assets/upload/avatar/' + getManagerImage(manager)" 
                                                            alt="avatar" 
                                                            class="rounded-circle pull-up" 
                                                            width="32" 
                                                            height="32"
                                                            @error="$event.target.style.display='none'; $event.target.nextElementSibling.style.display='inline-flex';">
                                                        <span class="avatar-initial rounded-circle bg-label-primary pull-up">
                                                            {{ getManagerInitials(manager) }}
                                                        </span>
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

    <!-- Edit Parent Project Modal (cấu trúc giống parent_project/detail.php edit mode) -->
    <div class="modal fade" id="editParentProjectModal" tabindex="-1" aria-labelledby="editParentProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editParentProjectModalLabel">
                        <span data-i18n="建物編集">建物編集</span>
                        <span v-if="editingParentProject.id" class="badge bg-label-primary ms-2">#{{ editingParentProject.id }}</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div v-if="editParentProjectLoading" class="text-center text-muted py-5">
                        <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                        <span data-i18n="読み込み中...">読み込み中...</span>
                    </div>
                    <div v-else class="row g-3">
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="会社名">会社名</span> <span class="text-danger">*</span></label>
                                <select id="edit_pp_company_name" class="form-select select2" name="edit_pp_company_name">
                                    <option value="">選択してください</option>
                                </select>
                                <div class="invalid-feedback d-block" v-if="editParentProjectErrors.company_name">{{ editParentProjectErrors.company_name }}</div>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="支店名">支店名</span></label>
                                <select id="edit_pp_branch_name" class="form-select select2" name="edit_pp_branch_name">
                                    <option value="">選択してください</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="担当様">担当様</span></label>
                                <select id="edit_pp_contact_name" class="form-select select2" name="edit_pp_contact_name">
                                    <option value="">選択してください</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="GUIS受付者">GUIS受付者</span></label>
                                <select id="edit_pp_guis_receiver" class="form-select select2" name="edit_pp_guis_receiver">
                                    <option value="">選択してください</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="お施主様名">お施主様名</span> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" v-model="editingParentProject.project_name"
                                       placeholder="案件名を入力" :class="{ 'is-invalid': !!editParentProjectErrors.project_name }">
                                <div class="invalid-feedback d-block" v-if="editParentProjectErrors.project_name">{{ editParentProjectErrors.project_name }}</div>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="依頼日">依頼日</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" v-model="editingParentProject.request_date"
                                           id="edit_pp_request_date" placeholder="YYYY/MM/DD HH:mm" autocomplete="off">
                                    <button class="btn btn-outline-secondary" type="button" @click="setEditParentCurrentDateTime" title="現在時刻">
                                        現在時刻
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="管理番号">管理番号</span></label>
                                <input type="text" class="form-control" :value="editingParentProject.project_number || '-'" readonly>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="工事番号">工事番号</span></label>
                                <input type="text" class="form-control" v-model="editingParentProject.construction_number" placeholder="工事番号を入力">
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="建物規模">建物規模</span></label>
                                <input type="text" class="form-control" v-model="editingParentProject.scale" placeholder="規模を入力">
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="構造事務所">構造事務所</span></label>
                                <input type="text" class="form-control" v-model="editingParentProject.structural_office" placeholder="構造事務所を入力">
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="種類1">種類1</span></label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="text" class="form-control tagify" id="edit_pp_type1_tags" name="edit_pp_type1_tags">
                                    <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearEditParentTagifyTags('type1')" title="すべて削除">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="種類2">種類2</span></label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="text" class="form-control tagify" id="edit_pp_type2_tags" name="edit_pp_type2_tags">
                                    <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearEditParentTagifyTags('type2')" title="すべて削除">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="依頼">依頼</span></label>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="edit_pp_request_design" v-model="editParentRequestFlags.design">
                                            <label class="form-check-label" for="edit_pp_request_design">意匠</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="edit_pp_request_equipment" v-model="editParentRequestFlags.equipment">
                                            <label class="form-check-label" for="edit_pp_request_equipment">設備</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="edit_pp_request_3d_equipment" v-model="editParentRequestFlags.equipment3d">
                                            <label class="form-check-label" for="edit_pp_request_3d_equipment">3D設備</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="edit_pp_request_energy_saving" v-model="editParentRequestFlags.energy">
                                            <label class="form-check-label" for="edit_pp_request_energy_saving">省エネ</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="edit_pp_request_other" v-model="editParentRequestFlags.other">
                                            <label class="form-check-label" for="edit_pp_request_other">その他</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="資料">資料</span></label>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="edit_pp_materials_layout" v-model="editParentMaterialFlags.layout">
                                            <label class="form-check-label" for="edit_pp_materials_layout">配置図</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="edit_pp_materials_rental" v-model="editParentMaterialFlags.rental">
                                            <label class="form-check-label" for="edit_pp_materials_rental">家賃審査書</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="edit_pp_materials_contract" v-model="editParentMaterialFlags.contract">
                                            <label class="form-check-label" for="edit_pp_materials_contract">契約図</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="edit_pp_materials_tac" v-model="editParentMaterialFlags.tac">
                                            <label class="form-check-label" for="edit_pp_materials_tac">TAC図</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="edit_pp_materials_other" v-model="editParentMaterialFlags.other">
                                            <label class="form-check-label" for="edit_pp_materials_other">その他</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-i18n="キャンセル">キャンセル</button>
                    <button type="button" class="btn btn-primary" @click="saveParentProjectFromModal"
                            :disabled="editParentProjectLoading || editParentProjectSaving">
                        <span v-if="editParentProjectSaving" class="spinner-border spinner-border-sm me-1" role="status"></span>
                        <span data-i18n="保存">保存</span>
                    </button>
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

<?php
$view->footing();
?>

<style>
.table th {
    background-color: #f8f9fa;
    border-top: none;
    font-weight: 600;
    color: #495057;
}

.table th[style*="cursor: pointer"]:hover {
    background-color: #e9ecef;
}

.table td {
    vertical-align: middle;
}

.badge {
    font-size: 0.75rem;
    padding: 0.375rem 0.5rem;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.table-responsive {
    border-radius: 0.375rem;
    overflow: hidden;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

.table-hover tbody tr.parent-project-row-saved,
.table-hover tbody tr.parent-project-row-saved:hover {
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
.request-filter-btn.btn-warning.active::after,
.request-filter-btn.btn-label-warning.active::after { background-color: #ffc107; }
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
<script src="assets/js/parent-project-index.js?v=<?=CACHE_VERSION?>"></script> 