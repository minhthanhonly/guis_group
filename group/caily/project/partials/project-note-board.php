<?php
/**
 * メモ board — 案件詳細 (inline card) and 案件一覧 (full screen, opened from a memo cell).
 * Logic: assets/js/project-note-board-mixin.js. Styles: assets/css/project-notes.css.
 * $noteBoardStandalone = true: the card exists only while it is shown full screen (案件一覧).
 */
$nbStandalone = !empty($noteBoardStandalone);
?>
<div class="card note-board-card<?= $nbStandalone ? ' note-board-standalone' : ' mt-4' ?>"
     <?= $nbStandalone ? 'v-if="noteBoardFullscreen" v-cloak' : '' ?>
     :class="{ 'note-board-fullscreen': noteBoardFullscreen }">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="card-title mb-0"><i class="fa fa-sticky-note me-2 text-warning"></i><span data-i18n="メモ">メモ</span>
            <small v-if="notes.length > 0" class="text-muted ms-1">({{ notes.length }})</small>
            <small v-if="noteBoardProjectName" class="note-board-project-name ms-2">{{ noteBoardProjectName }}</small>
        </h5>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <template v-if="notes.length > 0">
                <select class="form-select form-select-sm note-board-filter" v-model="noteFilterLabel" :title="translateLabel('ラベル')">
                    <option value="">{{ translateLabel('ラベル: すべて') }}</option>
                    <option value="__none__">{{ translateLabel('ラベルなし') }}</option>
                    <option v-for="lbl in noteFilterLabelOptions" :key="lbl" :value="lbl">{{ lbl }}</option>
                </select>
                <select class="form-select form-select-sm note-board-filter" v-model="noteFilterType" :title="translateLabel('区分')">
                    <option value="">{{ translateLabel('区分: すべて') }}</option>
                    <option value="1">{{ cailyNoteLabel }}</option>
                    <option value="2">{{ translateLabel('GUISメモ') }}</option>
                    <option value="0">{{ translateLabel('未分類') }}</option>
                </select>
                <button v-if="noteFiltersActive"
                        type="button"
                        class="btn btn-sm btn-danger"
                        :title="translateLabel('フィルターをクリア')"
                        @click="clearNoteFilters">
                    <i class="fa fa-times me-1"></i>{{ translateLabel('クリア') }} ({{ filteredNotes.length }} / {{ notes.length }})
                </button>
            </template>
            <div v-if="notes.length > 0" class="form-check form-switch mb-0 note-board-full-switch" :title="translateLabel('メモの内容をすべて表示')">
                <input class="form-check-input" type="checkbox" role="switch" id="noteShowFullSwitch" v-model="noteShowFull">
                <label class="form-check-label" for="noteShowFullSwitch">{{ translateLabel('全文表示') }}</label>
            </div>
            <button v-if="canAddNote" class="btn btn-primary btn-sm" @click="openNoteModal()" :title="translateLabel('メモを追加')">
                <i class="fa fa-plus me-1"></i> <span data-i18n="メモを追加">メモを追加</span>
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm note-board-fullscreen-btn"
                    :title="(noteBoardFullscreen ? translateLabel('全画面表示を終了') : translateLabel('全画面表示'))<?= $nbStandalone ? '' : " + ' (F12)'" ?>"
                    :aria-label="noteBoardFullscreen ? translateLabel('全画面表示を終了') : translateLabel('全画面表示')"
                    :aria-pressed="noteBoardFullscreen ? 'true' : 'false'"
                    @click="toggleNoteBoardFullscreen">
                <i class="fa" :class="noteBoardFullscreen ? 'fa-compress' : 'fa-expand'"></i>
            </button>
        </div>
    </div>
    <div class="card-body">
        <div v-if="noteBoardLoading" class="text-center text-muted py-5">
            <div class="spinner-border spinner-border-sm me-2" role="status"></div>{{ translateLabel('読み込み中...') }}
        </div>
        <template v-else>
            <!-- 公開範囲 / ピン filters (radio) -->
            <div v-if="notes.length > 0" class="note-board-radio-filters">
                <div class="note-board-radio-group">
                    <span class="note-board-radio-label">{{ translateLabel('公開範囲') }}</span>
                    <div class="btn-group btn-group-sm" role="group" :aria-label="translateLabel('公開範囲')">
                        <template v-for="opt in noteFilterVisibilityOptions" :key="'noteFilterVisibility-' + opt.value">
                            <input type="radio" class="btn-check" name="noteFilterVisibility"
                                   :id="'noteFilterVisibility-' + (opt.value || 'any')"
                                   :value="opt.value" v-model="noteFilterVisibility" autocomplete="off">
                            <label class="btn btn-outline-primary" :for="'noteFilterVisibility-' + (opt.value || 'any')">
                                <i v-if="opt.icon" class="fa me-1" :class="opt.icon"></i>{{ translateLabel(opt.text) }}
                            </label>
                        </template>
                    </div>
                </div>
                <div class="note-board-radio-group">
                    <span class="note-board-radio-label">{{ translateLabel('ピン') }}</span>
                    <div class="btn-group btn-group-sm" role="group" :aria-label="translateLabel('ピン')">
                        <template v-for="opt in noteFilterPinOptions" :key="'noteFilterPin-' + opt.value">
                            <input type="radio" class="btn-check" name="noteFilterPin"
                                   :id="'noteFilterPin-' + (opt.value || 'any')"
                                   :value="opt.value" v-model="noteFilterPin" autocomplete="off">
                            <label class="btn btn-outline-primary" :for="'noteFilterPin-' + (opt.value || 'any')">
                                <i v-if="opt.icon" class="fa me-1" :class="opt.icon"></i>{{ translateLabel(opt.text) }}
                            </label>
                        </template>
                    </div>
                </div>
            </div>
            <div v-if="notes.length > 0 && filteredNotes.length > 0"
                 ref="noteBoardGrid"
                 class="note-board detail-notes-list"
                 :class="{ 'note-filters-active': noteFiltersActive, 'note-board-full': noteShowFull, 'note-board-free': noteBoardFree, 'note-board-dragging': !!noteDrag }">
                <div v-for="note in boardNotes"
                     :key="note.id"
                     class="detail-note-item note-card"
                     :class="[
                         'note-bg-' + (note.bg_color || 'default'),
                         {
                             'important-note': note.is_important == 1,
                             'is-dragging': noteDrag && noteDrag.id == note.id
                         }
                     ]"
                     :style="noteCardGridStyle(note)"
                     :data-note-id="note.id">
                    <div class="note-card-top">
                        <span v-if="noteBoardFree"
                              class="note-drag-handle"
                              :title="translateLabel('ドラッグで移動')"
                              :aria-label="translateLabel('ドラッグで移動')"
                              @pointerdown="onNoteDragStart(note, $event)"
                              @click.stop>
                            <i class="fa fa-grip-vertical"></i>
                        </span>
                        <i v-if="note.is_important == 1" class="fa fa-thumb-tack text-danger" :title="translateLabel('ピン（全員）')"></i>
                        <span v-if="note.visibility === 'members'" class="note-visibility-icon" :title="translateLabel('公開範囲: 案件メンバー')"><i class="fa fa-users"></i></span>
                        <span v-else-if="note.visibility === 'self'" class="note-visibility-icon" :title="translateLabel('公開範囲: 自分のみ')"><i class="fa fa-lock"></i></span>
                        <span v-if="note.edit_locked_name" class="note-editing-chip" :title="note.edit_locked_name + translateLabel('さんが編集中')"><i class="fa fa-user-edit"></i> {{ note.edit_locked_name }}</span>
                        <span class="note-actions ms-auto" @click.stop>
                            <span v-if="note.visibility !== 'self'"
                                  class="note-pin-everyone-icon"
                                  :class="{ 'is-active': note.is_important == 1 }"
                                  :title="note.is_important == 1 ? translateLabel('ピン（全員）解除') : translateLabel('ピン（全員）')"
                                  @click="toggleNotePin(note)">
                                <i class="fa fa-thumb-tack"></i>
                            </span>
                            <span v-if="canDeleteNote(note)"
                                  class="detail-note-delete"
                                  :title="translateLabel('削除')"
                                  @click="deleteNote(note.id)">
                                <i class="fa fa-trash"></i>
                            </span>
                        </span>
                    </div>
                    <div class="note-main" @click="openNoteModal(note)">
                        <div v-if="note.label || note.needs_confirmation == 1 || note.needs_confirmation == 2 || note.display_column"
                             class="note-card-tags">
                            <span v-if="note.label" class="note-label-chip" :style="getNoteLabelStyle(note.label)"><i class="fa fa-tag note-label-chip-icon" aria-hidden="true"></i><span class="note-label-chip-text">{{ note.label }}</span></span>
                            <span v-if="note.needs_confirmation == 1" class="badge bg-primary">{{ translateLabel(cailyNoteLabel) }}</span>
                            <span v-else-if="note.needs_confirmation == 2" class="badge bg-dark">{{ translateLabel('GUISメモ') }}</span>
                            <span v-if="note.display_column" class="badge bg-secondary">{{ translateLabel('関連項目') }}: {{ translateLabel(getNoteDisplayColumnLabel(note.display_column)) }}</span>
                        </div>
                        <div v-if="note.content" class="ql-editor note-card-content" v-html="decodeNoteHtml(note.content)"></div>
                    </div>
                    <div class="note-card-footer" @click="openNoteModal(note)">
                        {{ formatShortDateTime(note.created_at) }} · {{ note.realname || 'Unknown' }}
                    </div>
                </div>
                <!-- drag: one extra row to drop below the last note (the target cell is drawn by the mixin) -->
                <div v-if="noteDrag && !noteDrag.pinned" class="note-board-spacer"
                     :style="{ gridColumn: '1 / -1', gridRow: String(noteBoardMaxRow + 1 + notePinRows) }"></div>
            </div>
            <div v-else-if="notes.length > 0" class="text-center text-muted py-3">
                <i class="fa fa-filter fa-2x mb-2"></i>
                <p class="mb-2">{{ translateLabel('条件に一致するメモがありません') }}</p>
                <button type="button" class="btn btn-outline-secondary btn-sm" @click="clearNoteFilters">{{ translateLabel('フィルターをクリア') }}</button>
            </div>
            <div v-else class="text-center text-muted py-3">
                <i class="fa fa-sticky-note fa-2x mb-2"></i>
                <p><span data-i18n="メモがありません">メモがありません</span></p>
                <button v-if="canAddNote" class="btn btn-outline-primary btn-sm" @click="openNoteModal()">
                    <span data-i18n="最初のメモを追加">最初のメモを追加</span>
                </button>
            </div>
        </template>
    </div>
</div>
