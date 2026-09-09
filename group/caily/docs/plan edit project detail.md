## Plan tối ưu `/project/detail.php` (phân tích code)

Test trên: `http://localhost/project/detail.php?id=<project_id>`  
Tham chiếu kỹ thuật đã làm trên list: `docs/plan edit project list.md` (gzip, asset profile, Promise.all, slim payload).

### Baseline (ước lượng từ code — đo MCP sau khi có URL thật)

| Thành phần | Size / hành vi |
|---|---|
| `project-detail.js` | **~249KB** / ~5.1k dòng |
| `comment-component.js` | **~117KB** (eager) |
| `quill.js` (+ CSS) | **~204KB** (eager × có thể ×2 qua footer) |
| `mention.js` | **~27KB** |
| Vue CDN **lần 2** | `vue@3.2.31` **non-prod** ở cuối `detail.php` trong khi `header` đã có `vue.global.prod.js` |
| Shell chung | moment ~748KB, core.css, Tagify trùng CSS, chat/sortable theo `app-asset-config` (detail = `page=detail` → **không** nhận profile list) |

**Waterfall API lúc `mounted` (sau P0 D1–D4):**

```
Promise.all([
  task/getPermission,
  project/getById  → Promise.all([ parent+children, getByUserids×1, customer?, workloadStats, members, teams ]),
  notes, logs, categories
])
→ loadDepartmentCustomFieldSets (cần department_id)
```
Không còn `searchMembers` ×2; không còn full `task/list` cho badge.

---

### P0 — Cắt đường tới data (ROI cao)

| # | Việc | Chỗ sửa | Kỳ vọng |
|---|---|---|---|
| **D1** | ✅ **`Promise.all` critical path**: `loadPermission` \|\| `getById` \|\| notes/logs/categories; sau `getById` gom parent/members/workload/teams | `project-detail.js` `mounted` / `loadProject` | −300–800ms TTI data |
| **D2** | ✅ **Gỡ waterfall parent**: `Promise.all([parent, children])` rồi `Promise.all([getByUserids×1, customer])` — **không** `searchMembers` | `loadParentProjectInfo` + `user/getByUserids` | −2–4 RTT |
| **D3** | ✅ **Slim / hẹp `getById`**: `getProjectDetailColumnSql()` thay `SELECT p.*` (schema + extras) | `project.php` `getById` | payload + TTFB nhỏ hơn |
| **D4** | ✅ **Workload nhẹ**: `task/workloadStats` (chỉ `task_kind` + `estimated_hours`) | `task.php` + `loadTaskWorkloadStats` | −payload lớn |

**Done khi:** Network lúc vào detail còn **≤ ~4–5** call tới lúc hiện overview; không còn `searchMembers` ×2. ✅ (P0)

---

### P1 — Cắt shell / parse (giống list #5–#8)

| # | Việc | Kỳ vọng |
|---|---|---|
| **D5** | **Bỏ Vue CDN trùng** ở `detail.php` (dùng Vue prod từ header) | −~100–500KB + tránh double Vue |
| **D6** | **Page asset profile `project/detail`**: defer Quill (+CSS) đến khi edit note/description; defer `comment-component` + mention đến khi scroll/tab comment hoặc `requestIdleCallback`; Tagify CSS trùng; Sortable nếu không drag trên overview | DCL / parse −1–3s |
| **D7** | Bỏ `task-manager.css` nếu detail không dùng task-manager UI | −CSS thừa |
| **D8** | Code-split `project-detail.js` (overview vs edit/Quill/BD/logs) | parse −0.5–1.5s |
| **D9** | Moment: dùng chung hướng list (#6) — defer hoặc dayjs trên detail | −0.7–1.5MB khi làm shell |

**Done khi:** First paint overview không chờ Quill+comment ~320KB; không load Vue 2 lần.

---

### P2 — PHP document + SQL

| # | Việc | Kỳ vọng |
|---|---|---|
| **D10** | Giảm work PHP `detail.php` trước HTML (branch lookup đã có — cache session branch) | TTFB |
| **D11** | `getById`: cột tường minh; aggregate 1–2 query batched (đã có `attachProjectDetailAggregates` — audit N+1) | TTFB API |
| **D12** | Index / bỏ query nặng trong `getChildProjects` + `searchMembers` (đổi sang get-by-userid) | ổn định |

---

### Thứ tự đề xuất

```
Ngày 1:     D5 (Vue trùng) → D1 → D2     (nhanh, ít rủi ro UI)
Ngày 1–2:   D6 (defer Quill/comment) → D7
Ngày 2–3:   D3 → D4 (payload/API)
Sau:        D8 → D9 → D10–D12
Sau mỗi bước: MCP reload cùng detail?id=, so sánh bảng dưới
```

---

### Bảng đo lại (điền sau MCP)

| Checkpoint | Hiện tại (ước lượng) | Sau P0 | Mục tiêu |
|---|---|---|---|
| HTML TTFB | ? | ? | &lt; 400ms |
| DCL | ? | ? | &lt; 3–4s |
| XHR tới overview usable | **~8–12+** (waterfall) | **≤ 5** | **≤ 4** |
| `searchMembers` | **×2** | **×0–1** | **×0** (lookup by id) |
| Vue scripts | **×2** (prod + non-prod) | **×1** | **×1** |
| Quill trên critical path | Có | Không (lazy) | Lazy |
| `getById` size | `p.*` + aggregates | slim | &lt; ~80–120KB gzip |

---

### Ngoài scope đợt đầu

- Prefetch detail từ list hover
- SSR field overview trong HTML PHP
- Gộp comment vào getById (payload phình)

---

### Ghi chú triển khai

- `app-asset-config.php`: hiện chỉ tối ưu `is_project_list`; **detail** vẫn `needs_quill` / `needs_sortable` / chat full — cần flag `is_project_detail`.
- Comment UI nằm trong DOM overview → lazy script phải stub component hoặc mount sau khi script load.
- `loadGuisReceiverDisplayName` nên API `user/getByUserid` thay vì tải cả `searchMembers`.

Nếu ok, bước code đầu nên là **D5 + D1 + D2**.
