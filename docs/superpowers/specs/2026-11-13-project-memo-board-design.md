# 案件詳細: メモ board (Google Keep style)

## Intent
On `project/detail.php` the メモ card is a vertical list in the right column (`col-xl-4`).
Users want the memos laid out as a board of sticky notes, like Google Keep, right under the
案件詳細 card, so they can scan them at a glance. Two new features come with it:
a background colour per note, and a per-note visibility setting.

## Goals
- Move メモ out of the right column into a full-width board directly under the 案件詳細 card (left column `col-xl-8`).
- Grid of equal-size square-ish cards (as in the reference screenshot), reading order left → right, top → bottom.
- Background colour per note.
- Visibility per note: 全員 / 案件メンバー / 自分のみ, enforced on the server everywhere notes are read.

## Non-goals
- No inline/quick add or in-place editing: add and edit stay in the existing modal.
- No masonry layout, no new JS library.
- Parent project (建物) notes are untouched.

## Anti-goals
- A 自分のみ / 案件メンバー note must never reach a user who may not see it — not on the detail page,
  not on the project list (CAILY/GUIS memo columns, 表示列 notes, hover popup), not through
  keyword search matching its content, not via get/update/delete by id.

## Approaches considered (layout)
1. **Equal-size grid (chosen).** Fixed card size; long content is clipped with a fade at the bottom;
   click opens the modal. Left → right order keeps the existing drag reorder and pin ordering intuitive.
2. Masonry with CSS columns — heights follow content, but order runs down each column, which makes
   drag reorder and pin order confusing.
3. Masonry with a JS library — correct order and Keep-like, but adds a dependency and relayout on every change.

## Decided
- Board position: under 案件詳細, full width of the left column. The old card in the right column is removed.
- Add/edit/view: the existing note modal, plus two new fields: colour swatches and a 公開範囲 select.
- Visibility levels (column `visibility`, values `all` / `members` / `self`):
  - `all`: anyone who can view the project (current behaviour).
  - `members`: the note's creator + users in `project_members` for that project (any role).
  - `self`: the creator only.
  - **No exception** for administrators, project managers or 部署 project_manager.
- Existing notes and notes created by the public API default to `all`.

## Claude's calls (check these)
- Colour stored as a palette key in `bg_color` (VARCHAR, NULL = default white), not a free hex value.
  ~10 Keep-like pastels with dark-mode variants.
- Columns are added by lazy migration in `ProjectNote` (same pattern as `ensureSortOrderColumn`),
  plus an `.sql` file under `sql/` for reference.
- One shared SQL condition builder in `ProjectNote` used by every read: `getByProject`, `getById`,
  update / delete / pin / reorder (they load the note first), project list memo columns,
  display_column notes, keyword search `EXISTS`.
- Card shows: pin icons, label chip, 区分 badge, a small icon when visibility is not 全員
  (👥 members / 🔒 self), content (clipped), author + date at the bottom. Delete and pin actions
  appear on hover, like now.
- Filters (ラベル / 区分) and the 追加 button stay in the board header. Drag reorder kept.
- Card size: ~200px tall, columns via `grid-template-columns: repeat(auto-fill, minmax(180px, 1fr))`.
- Anyone who can edit a note (`canEditNote`) can change its colour and visibility.

## Left to the builder
- Exact palette values, fade styling, hover details, i18n keys.
