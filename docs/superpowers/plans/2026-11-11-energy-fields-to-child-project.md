# Chuyển 3 hạng mục 省エネ sang dự án con — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 省エネ向け資料 (+ ghi chú その他), 申請提出先の審査機関, BELSのZEH等級 được nhập và lưu ở dự án con thuộc 部署 省エネ, không còn ở form công trình.

**Architecture:** Thêm 4 cột vào `groupware_projects`; model `project.php` nhận và làm sạch 4 trường theo 部署; hai modal dự án con trong trang chi tiết công trình có khối nhập mới; mọi chỗ đọc (`pp.*`, `parentProject.*`) đổi sang dữ liệu dự án con; form công trình gỡ khối cũ. Dữ liệu cũ chép sang bằng một câu SQL chạy tay.

**Tech Stack:** PHP (model tự viết, MySQL), Vue 2 + axios + Tagify trong các file JS/PHP tĩnh. Không có bộ test tự động.

**Spec:** `docs/superpowers/specs/2026-11-11-energy-fields-to-child-project-design.md`

## Global Constraints

- Quy tắc 部署 省エネ: tên 部署 chứa chuỗi `省エネ` (PHP: `isEnergyDepartmentById()` ở `project.php:5462`; JS: `isEnergyDepartmentName()` ở `parent-project-detail.js:1952`). Không viết quy tắc khác.
- Tên trường, giống hệt bên công trình: `energy_materials` (chuỗi cách nhau dấu phẩy), `energy_materials_memo`, `application_review_agency` (chuỗi cách nhau dấu phẩy), `bels_zeh_grade` (chuỗi `''` hoặc `0`–`9`).
- Giữ nguyên key cột danh sách `parent_application_review_agency`, `parent_bels_zeh_grade`.
- Không xoá cột nào ở `groupware_parent_projects`. Không đụng `features`, ô tick 依頼「省エネ」, 省エネ図面共有, phí 省エネ.
- Các file JS/PHP của `parent_project/` và `parentproject.php` đang có thay đổi chưa commit (multi-customer). Chỉ sửa đúng các đoạn nêu trong kế hoạch; không format lại, không hoàn tác gì.
- CLAUDE.md: trước khi sửa mỗi hàm/method phải chạy `node .gitnexus/run.cjs impact "<symbol>" --direction upstream --repo .`; `risk: UNKNOWN` thì xác nhận bằng tìm kiếm văn bản; HIGH/CRITICAL thì báo người dùng trước khi sửa. Trước khi commit chạy `detect-changes --scope all`.
- Không commit trong kế hoạch này (người dùng chưa yêu cầu, cây làm việc đang lẫn thay đổi khác).
- Sau mỗi file sửa: `php -l <file>` hoặc `node --check <file>` phải sạch. Tăng số trong `group/caily/application/version.php` một lần ở Task cuối để trình duyệt tải lại JS.

## Review Focus

1. Sửa dự án con 省エネ rồi đổi 部署 sang phòng khác → 4 trường phải bị xoá ở DB (Task 1 kiểm).
2. API `project.update` được gọi từ nơi khác không gửi 4 trường (trang chi tiết dự án, đổi trạng thái, AI) → giá trị cũ phải được giữ, không bị ghi rỗng (Task 1 kiểm).
3. Mở modal sửa cho dự án con cũ chưa có giá trị (NULL) → checkbox bỏ trống, select ZEH ở 「選択してください」, không lỗi console (Task 3 kiểm).
4. Mở modal sửa dự án A (省エネ, có giá trị) rồi đóng, mở modal tạo mới → không dính giá trị của A; tagify 審査機関 trống (Task 3 kiểm).
5. Chạy câu SQL chuyển dữ liệu hai lần, hoặc sau khi người dùng đã sửa dự án con → không ghi đè (Task 2 kiểm).

---

### Task 1: Backend dự án con — cột, lưu, đọc

**Files:**
- Modify: `group/caily/application/model/project.php` (schema `:20-82`, constructor `:84`, map cột `:420-421`, SELECT `:1493-1494`, tìm kiếm `:1909`, `:1966-1968`, `create()` `:2874`, `update()` `:3176`, nhãn lịch sử `:3722` gần đó)

**Interfaces:**
- Produces: `project.create` / `project.update` nhận POST `energy_materials`, `energy_materials_memo`, `application_review_agency`, `bels_zeh_grade`. `project.get` và danh sách con trả 4 trường trên từng dự án. Danh sách dự án trả `parent_application_review_agency`, `parent_bels_zeh_grade` lấy từ `p.*`.

- [ ] **Step 1:** Chạy impact cho `create`, `update`, `sqlBelsZehGradeLabelExpr` (file `project.php`) và ghi lại risk.
- [ ] **Step 2:** Thêm 4 key vào mảng schema. Thêm `private function ensureEnergyFieldColumns()` theo đúng mẫu `ensureProgressStartedAtColumn()` (static guard, try/catch + `error_log`), gọi trong constructor. Định nghĩa cột: `energy_materials TEXT NULL`, `energy_materials_memo VARCHAR(500) NULL`, `application_review_agency VARCHAR(255) NULL`, `bels_zeh_grade VARCHAR(10) NULL`, đều `DEFAULT NULL`, COMMENT như bên `parentproject.php:457-460`.
- [ ] **Step 3:** Thêm `private function applyEnergyFields(array &$data, $departmentId, $isCreate)`:
  - 部署 không phải 省エネ → đặt cả 4 trường `''` (cả create lẫn update).
  - 部署 là 省エネ → với mỗi trường, chỉ đặt khi `array_key_exists` trong `$_POST` (update không gửi thì giữ cũ); create không gửi thì `''`. Chuỗi qua `validateUTF8MB4`; `bels_zeh_grade` chỉ nhận `''` hoặc một chữ số `0`–`9`, khác thì `''`; `energy_materials_memo` thành `''` nếu `energy_materials` không chứa mục `その他`.
  - Gọi trong `create()` và `update()` sau khi đã biết `department_id` cuối cùng (update: lấy từ POST, không có thì từ bản ghi cũ).
- [ ] **Step 4:** Đổi `pp.application_review_agency` / `pp.bels_zeh_grade` thành `p.` ở `:420-421`, `:1493-1494`, `:1966-1968`; đổi mặc định tham số của `sqlBelsZehGradeLabelExpr` thành `'p.bels_zeh_grade'`.
- [ ] **Step 5:** Thêm nhãn lịch sử cạnh `'tantou' => '担当を変更'`: `省エネ向け資料を変更`, `省エネ向け資料メモを変更`, `申請提出先の審査機関を変更`, `BELSのZEH等級を変更`.
- [ ] **Step 6: Kiểm.** `php -l`. Rồi qua trình duyệt (đăng nhập sẵn) hoặc curl có cookie:
  - create với 部署 省エネ + 4 trường → `get` trả đúng 4 giá trị.
  - update cùng dự án, không gửi 4 trường → giá trị giữ nguyên.
  - update đổi `department_id` sang 部署 khác → 4 trường rỗng.
  - create với 部署 khác + gửi 4 trường → lưu rỗng.

### Task 2: SQL thêm cột và chuyển dữ liệu

**Files:**
- Create: `group/caily/sql/add_energy_fields_to_projects.sql`

- [ ] **Step 1:** Viết file gồm (a) `ALTER TABLE groupware_projects ADD COLUMN ...` 4 cột như Task 1; (b) câu chép:

```sql
UPDATE groupware_projects p
JOIN groupware_parent_projects pp ON pp.id = p.parent_project_id
JOIN groupware_departments d ON d.id = p.department_id
SET p.energy_materials = pp.energy_materials,
    p.energy_materials_memo = pp.energy_materials_memo,
    p.application_review_agency = pp.application_review_agency,
    p.bels_zeh_grade = pp.bels_zeh_grade
WHERE d.name LIKE '%省エネ%'
  AND COALESCE(p.energy_materials, '') = ''
  AND COALESCE(p.energy_materials_memo, '') = ''
  AND COALESCE(p.application_review_agency, '') = ''
  AND COALESCE(p.bels_zeh_grade, '') = '';
```

  Kèm chú thích đầu file: chạy tay một lần; chạy lại an toàn; công trình không có dự án con 省エネ thì dữ liệu ở lại cột cũ.
- [ ] **Step 2: Kiểm.** Không tự chạy trên DB của người dùng. Bàn giao file và 2 câu SELECT đếm (số dòng sẽ được cập nhật; số công trình có dữ liệu nhưng không có dự án con 省エネ) để người dùng chạy.

### Task 3: Hai modal dự án con

**Files:**
- Create: `group/caily/parent_project/partials/child-project-energy-fields.php`
- Modify: `group/caily/parent_project/detail.php` (modal tạo `:1568`, modal sửa ngay sau đó)
- Modify: `group/caily/parent_project/assets/js/parent-project-detail.js` (`newChildProject` `:625`, `editingChildProject` `:653`, các chỗ reset `:3561`, `:3596`, `:5083`, `:5257`, `:5430`, nạp khi mở sửa `:4261`, `onCreate/onEditChildProjectDepartmentChange` `:3535/:3543`, `updateChildProject` `:5155`, `createChildProject` `:5515`)

**Interfaces:**
- Consumes: API Task 1.
- Produces: trên cả hai object dự án con: `energy_materials_memo`, `application_review_agency`, `bels_zeh_grade` (chuỗi) và `energyMaterialFlags` (object `{nhãn: bool}`). Method dùng chung: `isChildProjectEnergyDept(isEdit) -> bool`, `buildChildEnergyMaterialsString(isEdit) -> string`, `appendChildEnergyFieldsToFormData(formData, isEdit)`.

- [ ] **Step 1:** Chạy impact cho `createChildProject`, `updateChildProject`, `onCreateChildProjectDepartmentChange`, `onEditChildProjectDepartmentChange` (graph không thấy `createChildProject` ở lần thử trước → coi là UNKNOWN, xác nhận bằng Grep).
- [ ] **Step 2:** Partial nhận biến PHP `$energyPrefix` (`'create'`/`'edit'`) và `$energyModel` (`'newChildProject'`/`'editingChildProject'`), bọc trong `<template v-if="isChildProjectEnergyDept(<isEdit>)">`. Nội dung chép từ khối cũ `detail.php:520-600` (chế độ sửa): checkbox theo `energyMaterialOptions`, ô memo khi tick その他, input tagify id `<prefix>_child_application_review_agency_tags` + nút xoá, select ZEH dùng cùng danh sách option hiện có. id checkbox có tiền tố `<prefix>_child_` để không trùng. Include partial vào cả hai modal ngay sau hàng 案件名 / 部署.
- [ ] **Step 3:** JS: thêm trường vào hai object và mọi chỗ reset; khi mở modal sửa, nạp từ dự án con (`energy_materials` → flags; NULL → rỗng). Khởi tạo/huỷ Tagify 審査機関 cho từng modal bằng `ParentProjectTagifyOptions` như tagify cũ (`:2532-2582`), khởi tạo trong `$nextTick` khi khối vừa hiện (đổi 部署 hoặc mở modal). `appendChildEnergyFieldsToFormData` luôn gửi đủ 4 trường; 部署 không phải 省エネ thì gửi `''`. Gọi trong `createChildProject` và `updateChildProject`.
- [ ] **Step 4: Kiểm trên trình duyệt** (`node --check` trước): tạo với 部署 省エネ → lưu → mở sửa thấy đúng; đổi 部署 khác → khối ẩn, lưu, mở lại vẫn rỗng; hai mục Review Focus 3 và 4; console không lỗi.

### Task 4: Gỡ khỏi form công trình

**Files:**
- Modify: `group/caily/parent_project/detail.php:520-600` và `parent-project-detail.js` (các dòng `:892-904`, `:1091-1125`, `:1453-1455`, `:2209-2214`, `:2251-2253`, `:2532-2582`, `:2740-2751`, `:2844-2853`, `:3071-3075`, `:3105-3113`)
- Modify: `group/caily/parent_project/create.php:298-335`, `parent-project-create.js` (các dòng grep `energy|application_review_agency|bels_zeh_grade`, gồm phần sao chép từ công trình khác `:1341-1355`)
- Modify: `group/caily/parent_project/index.php:688-710`, `parent-project-index.js` (các dòng grep tương tự)
- Modify: `group/caily/application/model/parentproject.php` — chỉ `collectDistinctTagifyFieldValues` / `listTagifyFieldOptions` (`:1439`, `:1483`)

- [ ] **Step 1:** Impact cho `collectDistinctTagifyFieldValues`, `listTagifyFieldOptions`, `listApplicationReviewAgencies`.
- [ ] **Step 2:** Gỡ markup 3 hạng mục và phần JS chỉ phục vụ chúng ở ba trang; không gửi 4 trường khi lưu công trình. Giữ lại những thứ Task 3 dùng trong `parent-project-detail.js`: `energyMaterialOptions`, danh sách option ZEH, `isEnergyDepartmentName`. Giữ `hasEnergySavingRequest` nếu còn chỗ khác dùng (Grep trước khi xoá).
- [ ] **Step 3:** `parentproject.php`: gợi ý `application_review_agency` gom từ `groupware_projects` thay cho bảng công trình. Không sửa `create()`/`update()` của model này: frontend không gửi nữa thì update giữ nguyên giá trị cũ (đúng ý "giữ làm bản lưu"). *Lệch nhỏ so với spec ("ngừng đọc/ghi" ở model): chọn không đụng để tránh va với thay đổi multi-customer.*
- [ ] **Step 4: Kiểm trên trình duyệt:** tạo công trình có tick 省エネ; sửa ở trang chi tiết và modal danh sách → không còn 3 hạng mục, lưu được, console sạch; danh sách khách hàng của công trình vẫn lưu đúng; sau khi sửa công trình, cột cũ trong DB không bị xoá trắng.

### Task 5: Chi tiết dự án con và danh sách dự án

**Files:**
- Modify: `group/caily/project/detail.php:269-300`
- Modify: `group/caily/project/assets/js/project-detail.js` (`:796`, `:819-870`, `:1496-1507`; computed 部署 省エネ sẵn có ở `:942-951`)
- Modify: `group/caily/project/assets/js/project-list.js` (`:6397-6425`, `:7692-7713`)

- [ ] **Step 1:** Impact cho các computed/method bị sửa; Grep `hasEnergySavingRequest` trong `project/` để biết còn ai dùng.
- [ ] **Step 2:** `project-detail.js`: bỏ các dòng ghi đè từ `parentProject` (`:1496-1507`, riêng 4 trường này). `detail.php`: ba khối đổi `v-if` sang computed "dự án thuộc 部署 省エネ" ở `:942`.
- [ ] **Step 3:** `project-list.js`: hai cột đổi điều kiện từ `parentRequestsIncludeEnergySaving(row.parent_requests)` sang tên 部署 của dòng chứa `省エネ` (dùng trường tên 部署 đã có trong row; kiểm tên trường trong dữ liệu trả về). Khối thông tin công trình `:7692-7713`: gỡ phần 3 hạng mục. *Quyết định của tôi: popup đó là thông tin công trình, mà 3 hạng mục không còn thuộc công trình.*
- [ ] **Step 4:** Tăng version trong `group/caily/application/version.php`.
- [ ] **Step 5: Kiểm trên trình duyệt:** trang chi tiết dự án con 省エネ hiện đúng giá trị của chính nó; dự án 部署 khác không hiện khối; danh sách dự án bật 2 cột → đúng giá trị; tìm kiếm theo tên 審査機関 và theo `Nearly ZEH` ra đúng dự án.

### Task 6: Rà soát cuối

- [ ] **Step 1:** Grep toàn `group/caily` cho `parentProject.energy_materials|parentProject.bels_zeh_grade|parentProject.application_review_agency|pp.bels_zeh_grade|pp.application_review_agency` → không còn kết quả ngoài file SQL và `parentproject.php`.
- [ ] **Step 2:** `node .gitnexus/run.cjs detect-changes --scope all --repo .`; nếu `partial`/`truncated` thì chạy lại. Báo kết quả.
- [ ] **Step 3:** Chạy lại toàn bộ mục "Kiểm tra" của spec và 5 mục Review Focus; báo cái nào đã kiểm, cái nào chưa kiểm được và vì sao.
