# Giải pháp import Excel CAILY(管理) → dept 7 (省エネ)

**Trạng thái:** Đặc tả đã chốt — **chưa chạy import** (chờ lệnh bắt đầu).  
**Nguồn:** `Copy of CAILY(管理).xlsx` → sheet **`省エネ（進行形）`** (~10.043 dòng).  
**Target:** `department_id = 7`.

---

## 1. Quyết định đã chốt

| # | Nội dung |
|---|----------|
| 1 | Company map: `東建`→`東建コーポレーション`, `大東`→`大東建託株式会社`, `他社` giữ nguyên |
| 2 | Customer `category_id = 4` |
| 3 | `設備図` / `意匠図` / `配置図` → `projects.custom_fields` |
| 4 | Gộp 1 building theo `工事番号`; mọi dòng Excel → **Task mới**; **project đã có** → không overwrite field/CF, chỉ **thêm notes E/AF** + Task mới |
| 5 | Chỉ sheet `省エネ（進行形）` |
| 6 | Title task: `Task 1`, `Task 2`, … (thứ tự trong group) |
| 7 | Dòng đại diện = **dòng đầu** trong sheet (trong group) |
| 8 | **Không** tạo default tasks dept 7 — chỉ task từ Excel |
| 9 | Assignee: match `user.realname` sau normalize (lowercase, bỏ dấu Việt) |
| 10 | `PJ No.` → custom field **`PJ No(旧)`** (không ghi `projects.project_number`) |
| 11 | `連絡番号` (長屋/共同/戸建…) → building **`type2` = 住宅種類** |
| 12 | `thành phố` → building **`city`** (cột DB mới: `parent_projects.city` / UI 市区町村) |
| 13 | **Status project** (dòng đại diện): có `実納品日` → `completed` + `progress=100` + `actual_end_date`; không có → `in_progress` + `progress=0` |
| 14 | Cột **E** + **AF** (cả hai header メモ) → `project_notes` (`メモ(E)`, `メモ(AF)`); **区分 `needs_confirmation = 2`** (GUISメモ) |
| 15 | **Project trùng**: không ghi đè field/CF/status; **thêm notes** E/AF (`メモ(E) #excelRow` / `メモ(AF) #excelRow`) + **thêm Task mới** |

---

## 2. Hierarchy

```
Customer (会社名 + 支店名 + 担当者名)
  └── ParentProject / 建物  ← khóa: 工事番号
        └── Project (department_id = 7)  ← 1 / building
              ├── custom_fields (từ dòng đại diện)
              └── Task 1..N (mọi dòng trong group)
```

Ước lượng:

| Entity | ~Số |
|--------|-----|
| Customer unique | ~1.895 |
| Building (có 工事番号) | ~5.613 |
| Project dept 7 | ~5.613 (+ fallback thiếu 工事番号) |
| Task | ~10.043 |

---

## 3. Map cột Excel

### 3.1 Customer

| Excel | CAILY |
|--------|--------|
| 会社名 (sau map) | `company_name` |
| 支店名 | `branch` |
| 担当者名 | `name` |
| — | `category_id = 4` |
| — | Upsert theo `(company_name, branch, name)` |

### 3.2 Building (`parent_projects`)

| Excel | CAILY |
|--------|--------|
| お施主様名 | `project_name` |
| 建物規模 | `scale` |
| Project type / 建物種類 | `type1` |
| 連絡番号 (loại nhà) | `type2` (住宅種類; whitelist: 長屋/共同/戸建/非住宅/集合; lấy phần trước `\|`) |
| thành phố | `city` |
| 工事番号 | `construction_number` (**khóa gộp**) |
| メモ cột **E** (dòng đầu) | Building `notes` (plain) + **project_notes** `メモ(E)` (**giữ màu chữ Excel** → HTML `<span style="color:…">`) |
| メモ cột **AF** (dòng đầu) | **project_notes** `メモ(AF)` (giữ màu / bold / italic Excel nếu có) |
| 会社/支店/担当 (qua customer) | `company_name`, `branch_name`, `contact_name`, `customer_id` |

`parent_projects.project_number`: để CAILY generate (không lấy PJ No. Excel).

### 3.3 Project (`projects`, dept 7)

| Excel | CAILY |
|--------|--------|
| — | `department_id = 7` |
| — | `parent_project_id` |
| お施主様名 | `name` |
| — | `description` **để trống** (không lấy cột E) |
| 建物規模 | `building_size` |
| Project type | `building_type` |
| 受注形態 | `project_order_type` (Excel chủ yếu `修正`) |
| 開始予定 (ngày đã resolve, cột date) | `start_date` |
| CAILY納期 (ngày) | `caily_nouki` |
| 実納品日 | xem **Status** bên dưới |
| — | `project_number` **để trống / generate** — không dùng PJ No. Excel |

**Status / progress (từ dòng đại diện):**

| Điều kiện | `status` | `progress` | `actual_end_date` |
|-----------|----------|------------|-------------------|
| Có `実納品日` | `completed` | `100` | = `実納品日` |
| Không có | `in_progress` | `0` | null |

Không suy diễn `calculation_completed` / `application_in_progress` từ Excel.

### 3.4 `custom_fields` (dòng đại diện → project)

JSON dạng `[{ "label", "type", "value" }, …]` + `department_custom_fields_set_id` của dept 7.

| Label | Nguồn Excel | Ghi chú |
|--------|-------------|---------|
| **PJ No(旧)** | PJ No. | Giữ text (`7524`, `★`, `△`, …) |
| **設備図** | Excel W=cột GUIS, X=cột 他社 (merge header) | 〇/○ ở W (hoặc text `GUIS`) → `GUIS`; 〇/○ ở X (hoặc text `他`/`他社` ở W) → `他社`; ưu tiên GUIS nếu cả hai |
| **意匠図** | — | **Cùng giá trị với `設備図`** (copy sau khi map) |
| **配置図** | 配置図 | |
| **入力注意点** | 入力注意点 | |
| **面積確認** | 面積確認 | |
| **計算データ納期** | ngày đã resolve | |
| **申請代行納期** | text/ngày | |
| **注文書** | 注文書 | |

Dry-run: nếu label chưa có trong set dept 7 → báo (không tự tạo field trừ khi được phép).

### 3.5 Task (mọi dòng)

| | |
|--|--|
| Title | `Task 1`, `Task 2`, … |
| Assignee | cột **担当者** → lookup user |
| due_date | CAILY納期 của dòng (nếu có) |
| note | メモ + 入力注意点 của dòng |
| Default tasks dept | **Không tạo** |

### 3.6 Cột bỏ / nội bộ

- `dữ liệu trùng` (cờ `X`)
- Cột phone/rác fill ~0%

---

## 4. User match (cột 担当者)

```
normalize(s) = lowercase + bỏ dấu Việt + trim + bỏ khoảng trắng (gồm ideographic space)
"Trường" → "truong"
"QUOC" / "quoc" → "quoc"
```

Thứ tự khớp:
1. **Alias map** (file `docs/import-user-aliases.json`) — ưu tiên tuyệt đối
2. Exact `realname_norm`
3. Không match → task vẫn tạo, `assigned_to` null + liệt kê dry-run

- Ô nhiều tên (`北野\n小林`, `quoc/truong`, `bien→quoc`): split theo newline / `/` / `→` → nhiều assignee.
- Alias đặc biệt (`外注(…)`, `CAILY`, `省エネ1`, URL…): skip hoặc bổ sung map sau dry-run.

### Alias đã chốt

| Excel 担当者 | → user id | realname |
|--------------|-----------|----------|
| `小林` | **13** | 小林 宏和 (`kobayashi_h`) |

(Không dùng id=8 小林　翔.)

---

## 5. Trường hợp biên

| Case | Xử lý |
|------|--------|
| Thiếu 工事番号 (~375 dòng) | Fallback khóa ghi vào `construction_number`: (1) `PJ No.` nếu có và không chỉ `★`/`△`; (2) không thì `会社名\|支店名\|お施主様名`; flag `koji_fallback` trong summary |
| Cùng 工事番号, khác PJ No. (~813 group) | 1 building + 1 project; `PJ No(旧)` từ dòng đầu; PJ khác ghi note task nếu cần |
| `type2` ngoài whitelist | Lấy token đầu; phần lạ ghi dry-run |
| Re-run | Idempotent theo `construction_number` + `department_id=7` |

---

## 6. Pipeline triển khai (khi bắt đầu)

1. **Dry-run** (mặc định): counts create/update/skip; user miss; 工事番号 trống; label custom_fields thiếu; sample `PJ No(旧)` / `city` / `type2`.
2. Duyệt report.
3. **Apply** batch: customer upsert → parent upsert → project create → tasks.
4. Log file; có thể re-run an toàn.

**Hình thức:** script PHP/CLI (hoặc API batch) một lần — không dùng UI customer import cho toàn bộ chuỗi.

**Prerequisite đã làm trong code:** cột building `city` (`sql/add_city_to_parent_projects.sql` + `ensureParentProjectCityColumn`, UI create/detail, i18n `市区町村` → Thành phố).

---

## 7. File / tham chiếu code

- Customer import sẵn có (chỉ khách): `caily/customer/import.php`, `Customer::import_customers`
- Building: `ParentProject` (`application/model/parentproject.php`)
- Project: `Project::create`
- Task: `Task::add` / insert trực tiếp trong script
- Custom fields set: `department_custom_fields` qua `Department::getCustomFields`

---

## 8. Checklist trước khi apply thật

- [ ] Chạy dry-run trên file Excel đích
- [ ] Bổ sung alias user từ danh sách miss
- [ ] Xác nhận set custom_fields dept 7 có đủ label (hoặc cho phép tạo)
- [ ] Xác nhận `department_id = 7` đúng phòng 省エネ trên DB production/staging
- [ ] Backup DB trước apply
]
