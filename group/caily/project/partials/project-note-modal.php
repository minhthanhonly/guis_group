<?php
/**
 * メモ modal (add / view / edit) shared by 案件詳細 and 案件一覧.
 * Logic: assets/js/project-note-modal-mixin.js (+ the page's own open/save). Styles: assets/css/project-notes.css.
 *
 * $noteModalEditorId — id of the Quill container the page's initQuillNoteEditor() looks up.
 */
$noteModalEditorId = isset($noteModalEditorId) ? $noteModalEditorId : 'quill_note_content';
?>
<template v-if="showNoteModal">
    <div class="modal fade show project-note-modal" tabindex="-1" style="display: block;"
         role="dialog" aria-modal="true" :aria-label="noteModalTitle"
         @keydown="onNoteModalKeydown">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="project-note-modal-heading">
                        <h5 class="modal-title">{{ noteModalTitle }}</h5>
                        <small v-if="noteModalMeta" class="text-muted">{{ noteModalMeta }}</small>
                    </div>
                    <button type="button" class="btn-close" aria-label="閉じる" @click="closeNoteModal"></button>
                </div>
                <div class="modal-body">
                    <div class="project-note-modal-grid">
                        <!-- Left: the note itself -->
                        <div class="project-note-modal-main">
                            <div v-if="isNoteEditMode" class="custom_editor note-editor-tinted project-note-modal-editor"
                                 :class="'note-bg-' + (editingNote.bg_color || 'default')">
                                <!-- Dynamic attribute on purpose: a fully static node is hoisted by Vue and re-created
                                     by cloning the last mounted one, which still holds the previous note's Quill markup. -->
                                <div class="custom_editor_content" id="<?= htmlspecialchars($noteModalEditorId) ?>" :data-note-id="editingNote.id || 'new'"></div>
                                <textarea class="custom_editor_textarea d-none" v-model="editingNote.content" id="<?= htmlspecialchars($noteModalEditorId) ?>_textarea"></textarea>
                            </div>
                            <div v-else class="ql-editor note-view-content project-note-modal-view"
                                 :class="'note-bg-' + (editingNote.bg_color || 'default')"
                                 v-html="editingNote.content || '-'"></div>
                        </div>

                        <!-- Right: settings (edit) / properties (view) -->
                        <div class="project-note-modal-side">
                            <template v-if="isNoteEditMode">
                                <div class="pnm-field">
                                    <div class="pnm-label">背景色</div>
                                    <div class="note-color-swatches">
                                        <button v-for="c in noteBgColorOptions" :key="c.value"
                                                type="button"
                                                class="note-color-swatch"
                                                :class="['note-bg-' + (c.value || 'default'), { 'is-selected': (editingNote.bg_color || '') === c.value }]"
                                                :title="c.text" :aria-label="c.text"
                                                :aria-pressed="(editingNote.bg_color || '') === c.value ? 'true' : 'false'"
                                                @click="editingNote.bg_color = c.value">
                                            <i v-if="(editingNote.bg_color || '') === c.value" class="fa fa-check"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="pnm-field">
                                    <div class="pnm-label">公開範囲</div>
                                    <div class="btn-group btn-group-sm w-100 pnm-segmented" role="group" aria-label="公開範囲">
                                        <template v-for="v in noteVisibilityOptions" :key="'pnm-vis-' + v.value">
                                            <input type="radio" class="btn-check" name="pnmVisibility" :id="'pnm-vis-' + v.value"
                                                   :value="v.value" v-model="editingNote.visibility" autocomplete="off">
                                            <label class="btn btn-outline-primary" :for="'pnm-vis-' + v.value">
                                                <i class="fa me-1" :class="v.icon"></i>{{ v.text }}
                                            </label>
                                        </template>
                                    </div>
                                </div>
                                <div class="pnm-field">
                                    <div class="pnm-label">ピン</div>
                                    <div class="d-flex flex-wrap gap-2 pnm-segmented">
                                        <!-- 自分のみ: no one else sees the note, so ピン（全員） is not offered -->
                                        <template v-if="editingNote.visibility !== 'self'">
                                            <input type="checkbox" class="btn-check" id="pnm-pin-everyone" autocomplete="off"
                                                   v-model="editingNote.is_important" @change="onNotePinEveryoneChange">
                                            <label class="btn btn-sm btn-outline-danger" for="pnm-pin-everyone">
                                                <i class="fa fa-thumb-tack me-1"></i>ピン（全員）
                                            </label>
                                        </template>
                                        <input type="checkbox" class="btn-check" id="pnm-pin-self" autocomplete="off"
                                               v-model="editingNote.is_pinned_self" @change="onNotePinSelfChange">
                                        <label class="btn btn-sm btn-outline-primary" for="pnm-pin-self">
                                            <i class="fa fa-bookmark me-1"></i>ピン（自分）
                                        </label>
                                    </div>
                                </div>
                                <div class="pnm-field">
                                    <div class="pnm-label">区分</div>
                                    <div class="btn-group btn-group-sm w-100 pnm-segmented" role="group" aria-label="区分">
                                        <input type="radio" class="btn-check" name="pnmType" id="pnm-type-1" :value="1"
                                               v-model="editingNote.needs_confirmation" autocomplete="off">
                                        <label class="btn btn-outline-primary" for="pnm-type-1">{{ cailyNoteLabel }}</label>
                                        <input type="radio" class="btn-check" name="pnmType" id="pnm-type-2" :value="2"
                                               v-model="editingNote.needs_confirmation" autocomplete="off">
                                        <label class="btn btn-outline-primary" for="pnm-type-2">GUISメモ</label>
                                    </div>
                                </div>
                                <div class="pnm-field">
                                    <label class="pnm-label" for="pnm-label-select">ラベル</label>
                                    <select id="pnm-label-select" class="form-select form-select-sm" v-model="editingNote.labelSelect" @change="onNoteLabelSelectChange">
                                        <option value="">— 選択 —</option>
                                        <option v-for="lbl in noteLabelSelectOptions" :key="lbl.name" :value="lbl.name">{{ lbl.name }}</option>
                                        <option :value="NOTE_LABEL_OTHER">その他</option>
                                    </select>
                                    <input v-if="editingNote.labelSelect === NOTE_LABEL_OTHER"
                                           type="text" class="form-control form-control-sm mt-2"
                                           v-model="editingNote.labelOther" maxlength="100" placeholder="ラベルを入力">
                                </div>
                                <div class="pnm-field">
                                    <label class="pnm-label" for="pnm-display-column">表示列</label>
                                    <select id="pnm-display-column" class="form-select form-select-sm" v-model="editingNote.display_column">
                                        <option value="">— 選択 —</option>
                                        <option v-for="opt in noteDisplayColumnOptions" :key="opt.value" :value="opt.value">{{ opt.text }}</option>
                                    </select>
                                </div>
                            </template>
                            <template v-else>
                                <div class="pnm-field">
                                    <div class="pnm-label">公開範囲</div>
                                    <div><i class="fa me-1" :class="noteVisibilityIcon(editingNote.visibility)"></i>{{ noteVisibilityLabel(editingNote.visibility) }}</div>
                                </div>
                                <div class="pnm-field" v-if="editingNote.is_important || editingNote.is_pinned_self">
                                    <div class="pnm-label">ピン</div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span v-if="editingNote.is_important" class="badge bg-danger"><i class="fa fa-thumb-tack me-1"></i>ピン（全員）</span>
                                        <span v-if="editingNote.is_pinned_self" class="badge bg-primary"><i class="fa fa-bookmark me-1"></i>ピン（自分）</span>
                                    </div>
                                </div>
                                <div class="pnm-field" v-if="editingNote.needs_confirmation">
                                    <div class="pnm-label">区分</div>
                                    <span class="badge bg-primary" v-if="String(editingNote.needs_confirmation) === '1'">{{ cailyNoteLabel }}</span>
                                    <span class="badge bg-dark" v-else-if="String(editingNote.needs_confirmation) === '2'">GUISメモ</span>
                                </div>
                                <div class="pnm-field" v-if="editingNote.label">
                                    <div class="pnm-label">ラベル</div>
                                    <span class="note-label-chip" :style="getNoteLabelStyle(editingNote.label)"><i class="fa fa-tag note-label-chip-icon" aria-hidden="true"></i><span class="note-label-chip-text">{{ editingNote.label }}</span></span>
                                </div>
                                <div class="pnm-field" v-if="editingNote.display_column">
                                    <div class="pnm-label">表示列</div>
                                    <div>{{ getNoteDisplayColumnLabel(editingNote.display_column) }}</div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button v-if="isNoteEditMode && canDeleteCurrentNote" type="button" class="btn btn-outline-danger me-auto" @click="deleteCurrentNote">
                        <i class="fa fa-trash me-1"></i>削除
                    </button>
                    <template v-if="isNoteEditMode">
                        <small class="text-muted d-none d-md-inline me-2">Ctrl+Enter で保存 / Esc で閉じる</small>
                        <button type="button" class="btn btn-secondary" @click="closeNoteModal">キャンセル</button>
                        <button type="button" class="btn btn-primary" @click="saveNote" :disabled="savingNote || !canSaveNote">
                            <span v-if="savingNote" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="fa fa-save me-1"></i>保存
                        </button>
                    </template>
                    <template v-else>
                        <button v-if="canEditNote(editingNote)" type="button" class="btn btn-primary" @click="startNoteEdit">
                            <i class="fa fa-pencil-alt me-1"></i>編集
                        </button>
                        <button type="button" class="btn btn-secondary" @click="closeNoteModal">閉じる</button>
                    </template>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
</template>
