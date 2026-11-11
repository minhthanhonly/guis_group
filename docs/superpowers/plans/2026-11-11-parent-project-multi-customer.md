# Parent project – danh sách nhiều khách hàng: kế hoạch thực hiện

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Một parent project gắn được danh sách khách hàng, có đúng một khách hàng chính.

**Architecture:** Bảng liên kết `groupware_parent_project_customers`; bốn cột `customer_id/company_name/branch_name/contact_name` trên `groupware_parent_projects` luôn là bản sao của khách hàng chính, do server tự ghi. Giao diện dùng chung một Vue mixin + ba partial PHP cho trang tạo và trang chi tiết.

**Tech Stack:** PHP (model kế thừa `ApplicationModel`, API qua `/api/index.php?model=…&method=…`), MySQL, Vue 3.2 (CDN, options API), jQuery/select2, Bootstrap 5.

**Spec:** `docs/superpowers/specs/2026-11-11-parent-project-multi-customer-design.md`

## Global Constraints

- Không commit (người dùng chưa yêu cầu). Không đụng tới các màn hình phía project ngoài hai modal project con trong trang chi tiết parent.
- CLAUDE.md: chạy GitNexus impact trước khi sửa symbol; `parent-project-detail.js` và `project-list.js` (>512KB) không có trong index → tìm kiếm văn bản. `detect-changes --scope all` trước khi kết thúc.
- Method của `ParentProject`/`Customer` không tách riêng trong index (impact mức class = CRITICAL do dispatch động qua `api/index.php`) → mọi thay đổi server phải tương thích ngược: không có tham số `customers` thì hành vi cũ giữ nguyên, dữ liệu trả về chỉ thêm trường.
- Nhãn giao diện bằng tiếng Nhật, kèm `data-i18n` như code hiện có. Tăng `PROJECT_CACHE_VERSION` trong `application/version.php`.
- Không có khung test tự động trong repo: kiểm tra bằng script PHP CLI chạy model trên DB cục bộ và bằng trình duyệt tại http://localhost/.

## Review Focus

1. Parent project cũ chưa có dòng liên kết (CLI import ghi thẳng) → `getById` vẫn trả một khách hàng chính dựng từ bốn cột cũ.
2. Parent project cũ có tên nhưng `customer_id` rỗng → hiển thị được, lưu lại không làm mất tên.
3. Lưu qua đường không có `customers` (modal sửa nhanh, API upsert, AI) → bảng liên kết vẫn đúng một dòng chính khớp `customer_id`.
4. `customers` chứa id trùng, id không tồn tại, không dòng nào hoặc nhiều dòng đánh dấu chính → server chuẩn hóa về đúng một dòng chính, bỏ trùng, báo lỗi nếu không còn dòng hợp lệ.
5. Project con cũ có khách ngoài danh sách của parent → mở modal sửa rồi lưu không làm đổi khách.

---

### Task 1: Schema + model server

**Files:**
- Create: `group/caily/sql/create_parent_project_customers_table.sql`
- Modify: `group/caily/application/model/parentproject.php` (`__construct`, `create`, `update`, `upsert_parent_project_public`, `getById`, `list`, `paletteSearch`; thêm các hàm bên dưới)
- Modify: `group/caily/application/model/customer.php` (`delete_customer`)

**Interfaces (Produces):**
- Bảng `groupware_parent_project_customers(id, parent_project_id, customer_id, is_primary, sort_order, created_at)`, UNIQUE(`parent_project_id`,`customer_id`).
- POST `customers` cho `parentproject.create` / `parentproject.update`: chuỗi JSON `[{"customer_id":123,"is_primary":1}, …]` theo thứ tự hiển thị.
- `parentproject.getById` trả thêm `customers: [{customer_id, is_primary, company_name, branch_name, contact_name, tel, phone, email}]` (dòng chính có `is_primary = 1`; `customer_id` có thể null với dữ liệu cũ chỉ có tên) và `customer_count`.
- `parentproject.list` trả thêm `customer_count` cho mỗi dòng.
- Hàm nội bộ: `ensureParentProjectCustomersTable()`, `fetchParentProjectCustomers(int $id, ?array $row): array`, `parseCustomersPayload($raw): ?array`, `resolveCustomersForSave(array $items): array`, `replaceParentProjectCustomers(int $id, array $rows): void`, `syncPrimaryCustomerLink(int $id, $customerId): void`, `buildLinkedCustomerSearchExists(string $search): string`.

- [ ] Viết file SQL (CREATE TABLE IF NOT EXISTS + INSERT…SELECT chép khách hiện có thành dòng chính, bỏ qua dòng đã có).
- [ ] Thêm `ensureParentProjectCustomersTable()` (tạo bảng nếu thiếu, chép dữ liệu một lần khi bảng rỗng) và gọi trong constructor.
- [ ] `create`/`update`: có `customers` → chuẩn hóa, ghi bốn cột từ khách hàng chính, thay danh sách; không có → hành vi cũ + `syncPrimaryCustomerLink`. `upsert_parent_project_public` gọi `syncPrimaryCustomerLink`.
- [ ] `getById`, `list`, `paletteSearch`, `delete_customer` theo spec.
- [ ] Script CLI kiểm tra (trong scratchpad): bảng tồn tại, số dòng chính = số parent project có `customer_id` hợp lệ; tạo/sửa với `customers` hợp lệ, trùng, sai id, đổi chính; đường không có `customers`; `getById` cho parent project chưa có dòng liên kết. Dọn dữ liệu thử sau khi chạy.

### Task 2: Thành phần giao diện dùng chung

**Files:**
- Create: `group/caily/parent_project/assets/js/parent-project-customers-mixin.js` (`window.ParentProjectCustomersMixin`)
- Create: `group/caily/parent_project/partials/customer-list-editor.php`, `customer-list-modal.php`, `customer-search-modal.php`

**Interfaces (Produces):** data `customerList`, `customerListError`, trạng thái tìm kiếm; methods `setCustomerListFromServer(rows)`, `addCustomerToList(customer)`, `removeCustomerFromList(index)`, `setPrimaryCustomer(index)`, `getPrimaryCustomer()`, `syncPrimaryCustomerFields()` (ghi khách hàng chính vào `this.parentProject.*`), `buildCustomersPayload()`, `validateCustomerList()`, `openCustomerSearchModal()`, `searchCustomersByKeyword()`, `selectCustomerFromSearch(customer)`, `addNewCustomerToListByName(company, branch, name)`, `openCustomerListModal()`, `openCustomerInfoFromList(row)`, `formatCustomerLabel(row)`. Trang dùng mixin phải cung cấp `showCustomerInfoModal(customer)`.

### Task 3: Trang tạo (`create.php`, `parent-project-create.js`)

- [ ] Thay ba ô chọn bằng partial danh sách; nạp mixin; bỏ khởi tạo select2 của ba ô và các hàm chỉ phục vụ chúng; `validateParentProjectForm` kiểm tra danh sách; `saveParentProject` gửi `customers`; 新規顧客 thêm khách mới vào danh sách; sao chép parent project nạp cả danh sách; nút 顧客情報 mở modal danh sách.

### Task 4: Trang chi tiết (`detail.php`, `parent-project-detail.js`)

- [ ] Chế độ sửa dùng partial danh sách; chế độ xem hiển thị khách hàng chính + "+N"; nút 顧客情報 mở modal danh sách; lưu gửi `customers`; hủy sửa khôi phục danh sách; payload sao chép kèm `customers`.
- [ ] Modal tạo/sửa project con: bỏ tick → một `<select>` gồm danh sách của parent (+ lựa chọn リスト外 cho khách cũ ngoài danh sách); bỏ ba ô xếp tầng và nút 新規顧客 tại đó.

### Task 5: Danh sách (`index.php`, `parent-project-index.js`)

- [ ] Nhãn "+N" cạnh 会社名 khi `customer_count > 1`; payload sao chép kèm danh sách (lấy từ `getById`).

### Task 6: Kiểm tra trên trình duyệt + GitNexus

- [ ] Các kịch bản trong mục "Kiểm tra" của spec, tại http://localhost/.
- [ ] `node .gitnexus/run.cjs analyze --index-only --pdg` rồi `detect-changes --scope all --repo .`.
