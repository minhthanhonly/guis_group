## Plan tối ưu `/project/` (cập nhật theo MCP đã login)
Test trên: http://localhost/project/?department_id=5
Dùng network 4G slow.


Baseline đo được: **HTML TTFB ~1.1s · DCL ~7.9s · load ~8.1s · bảng sẵn sàng ~9.6s · LCP 1.9s · transfer ~7.5MB · `project/list` ×3 × ~178KB**.

Mục tiêu thực tế: **bảng usable &lt; 4s**, list API **1 lần**, HTML TTFB **&lt; 400ms**.

---

### P0 — Cắt đường tới data (1–2 ngày, ROI cao nhất)

| # | Việc | Bằng chứng / chỗ sửa | Kỳ vọng |
|---|---|---|---|
| **1** | **`project/list` chỉ 1 lần khi vào trang** | DT init đã ajax; sau `initializeProjectTable()` còn `reloadProjectTable(true)` (~L8485–8487). Tìm thêm trigger thứ 3 (filter restore / sort / `loadProjects`) | −~350–700ms + −~356KB JSON |
| **2** | **`Promise.all` trước DT** | Hiện `await loadTeamMap` rồi `await getCustomFields` tuần tự | −100–400ms trước list |
| **3** | **Gzip HTML + JSON API** ✅ | PHP `output_compression.php`. MCP kanri: HTML −83%, list −90%. **CSS/JS tĩnh không đi qua PHP** — cần bật gzip nginx (xem `docs/nginx-gzip-static.conf.example`). `core.css` hiện ~819KB không nén. | HTML/API nhỏ hơn ~60–80% transfer |
| **4** | **Slim `list` payload** ✅ | Bỏ `description`/field thừa; snippet notes + CAILY/GUISメモ; bỏ custom_fields rỗng. MCP local: **140KB → 100KB** (−29% JSON; custom_fields −70%, CAILYメモ −74%). | list nhỏ hơn rõ |

**Done khi:** Network chỉ còn **1** `method=list`; bảng hiện đủ **&lt; ~5.5s** (không đổi asset lớn).

---

### P1 — Cắt DCL ~8s (2–4 ngày)

Shell đang chặn trước API (moment ~748KB, `project-list.js` ~460KB, `core.css` ~819KB, Quill, Firebase, chat…).

| # | Việc | Kỳ vọng |
|---|---|---|
| **5** | **Page asset profile cho list**: không Quill / Shepherd-tour / Sortable / chat / customer-modal / Excel(jszip) đến khi cần ✅ | MCP local: script count **59→49**; Quill/Shepherd/Sortable/jszip/chat/customer/tour **không** load lúc đầu; DCL ~**0.7s** (mạng thường) |
| **6** | **Bỏ/defer `moment` + `moment-timezone-with-data`** (CDN ~808KB) — dùng dayjs hoặc format native | −0.7–1.5MB |
| **7** | Defer head blocking: chat CSS, task-timer, Tagify trùng, Flatpickr nếu chưa mở filter ✅ | MCP: bỏ `app-chat.css`; Tagify ×1; Flatpickr **không** load khi filter đóng; task-timer non-blocking; DCL ~0.4s |
| **8** | Code-split `project-list.js` (list core vs modal/Excel/tour/BD) ✅ | Excel + Quick Edit lazy; BD mixin async; core **~462→414KB**; tour already separate |
| **9** | Self-host / subset font; bỏ Google Fonts trên critical path | −critical path |

**Done khi:** DCL **&lt; 3–4s**, total transfer first load **&lt; ~3MB**.

---

### P2 — PHP document + API SQL (3–5 ngày)

| # | Việc | Kỳ vọng |
|---|---|---|
| **10** | Giảm work PHP `index.php` (session/query nặng trước HTML) | TTFB **&lt; 400ms** |
| **11** | `list`: không `SELECT p.*`; bỏ `description`/full notes; snippet notes; members gọn | TTFB list + payload |
| **12** | COUNT nhẹ hơn data query; cache permission director 1 lần/request | mỗi draw nhanh hơn |
| **13** | `pageLength` mặc định **25** | −50% rows/render |
| **14** | Index + bỏ `DATE_FORMAT` / `FIND_IN_SET` trên filter hot path | ổn định khi data lớn |

**Done khi:** `list` 1 lần **&lt; 200ms** TTFB, payload **&lt; 80KB**.

---

### Thứ tự triển khai (nhanh nhất có thể)

```
Tuần 1 ngày 1–2:  #1 → #2 → #3     (cắt list×3 + nén)
Tuần 1 ngày 3–4:  #4 → #13          (slim API + pageLength)
Tuần 2:           #5 → #6 → #7 → #8 (shell)
Tuần 2–3:         #10 → #11 → #12 → #14
Sau mỗi phase:    MCP reload lại cùng URL, so sánh bảng dưới đây
```

---

### Bảng đo lại (cùng điều kiện MCP)

| Checkpoint | Hiện tại | Sau P0 | Sau P1 | Mục tiêu cuối |
|---|---|---|---|---|
| HTML TTFB | ~1.1s | ~1.1s (nén size↓) | ~1.1s | **&lt; 400ms** |
| DCL | ~7.9s | ~7–8s | **&lt; 4s** | **&lt; 3s** |
| `list` calls | **3** | **1** | 1 | **1** |
| `list` size | ~178KB×3 | ~178KB×1 → slim | slim | **&lt; 80KB** |
| Bảng ready | ~9.6s | **~5–6s** | **~3–4s** | **&lt; 3s** |

---

### Ngoài scope đợt đầu (để sau)

- Cache Redis list theo `department_id + filter hash`
- SSR/pre-render vài row đầu trong HTML
- Service worker cache asset (đã có `immutable` trên static)

---

Nếu ok, bước code đầu tiên nên là **P0 #1**: bỏ reload thừa sau init + chặn filter-restore kích hoạt thêm `list` (đưa từ 3 → 1).