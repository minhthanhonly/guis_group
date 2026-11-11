# Parent project: danh sách nhiều khách hàng, có một khách hàng chính

Ngày: 2026-11-11 · Trạng thái: chờ duyệt

## Mục đích

Một parent project (建物) hiện chỉ gắn được một khách hàng. Thực tế một công trình có nhiều công ty liên quan, nên cần gắn được một danh sách khách hàng (mỗi khách là một dòng `groupware_customer` = 会社名 + 支店名 + 担当様, có thể thuộc các công ty khác nhau) và đánh dấu đúng một khách hàng chính dùng để liên lạc.

## Mục tiêu

1. Tạo và sửa parent project: nhập được danh sách khách hàng, thêm bằng tìm kiếm (顧客検索) hoặc tạo khách mới, xóa khỏi danh sách, chọn đúng một khách hàng chính.
2. Dữ liệu cũ hiển thị đúng: khách hàng hiện có của mỗi parent project là khách hàng chính.
3. Project con (tạo/sửa trong trang chi tiết parent project): giữ ô 「顧客情報は建物と同じ」 (= khách hàng chính); nếu bỏ tick thì chọn một khách từ danh sách của parent project.
4. Nút 顧客情報 mở modal danh sách khách hàng của parent project; từ mỗi dòng mở được thông tin chi tiết/sửa khách như hiện nay.
5. Tìm kiếm parent project (ô tìm ở danh sách và command palette) ra kết quả theo bất kỳ khách hàng nào trong danh sách.
6. Danh sách parent project hiển thị khách hàng chính, kèm nhãn "+N" khi có thêm khách.

## Ngoài phạm vi

- Người nhận báo giá vẫn tự điền theo khách hàng chính, không có bước chọn.
- Các màn hình phía project (danh sách project, gantt, hóa đơn, thống kê doanh thu, lọc 大東建託/東建/その他) vẫn theo "khách hàng hiệu lực" như cũ: khách riêng của project con, nếu không có thì khách hàng chính của parent.
- Form chọn khách ở `project/create.php`, trang chi tiết project và modal trong danh sách project không đổi.
- Không sửa các lỗi có sẵn ghi nhận khi khảo sát (xem cuối tài liệu).

## Điều không được xảy ra

- Mất hoặc đổi khách hàng của parent project/project con hiện có sau khi migrate.
- Hỏng các màn hình doanh thu, hóa đơn, danh sách project, API upsert công khai, CLI import.
- Parent project có danh sách nhưng không có, hoặc có hơn một, khách hàng chính.

## Các cách đã cân nhắc

| | Cách lưu | Đánh đổi |
|---|---|---|
| **A (đã chọn)** | Bảng liên kết mới; bốn cột cũ trên `parent_projects` giữ làm bản sao của khách hàng chính | Khoảng 40 chỗ đang đọc khách hàng chạy như cũ; phải ghi đồng bộ hai nơi khi lưu |
| B | Nhiều id cách nhau dấu phẩy trong cột `customer_id` | Không cần bảng mới nhưng hỏng mọi chỗ so `customer_id = id` và khoảng 10 chỗ JS gửi thẳng id |
| C | Bảng mới là nguồn duy nhất, bỏ bốn cột cũ | Gọn nhất nhưng phải viết lại toàn bộ chỗ đọc, rủi ro cao nhất |

Người dùng chọn A.

## Thiết kế

### Dữ liệu

Bảng mới `groupware_parent_project_customers`:

| Cột | Kiểu | Ghi chú |
|---|---|---|
| `id` | INT AUTO_INCREMENT PK | |
| `parent_project_id` | INT NOT NULL | index |
| `customer_id` | INT NOT NULL | index; UNIQUE (`parent_project_id`, `customer_id`) |
| `is_primary` | TINYINT(1) NOT NULL DEFAULT 0 | đúng một dòng = 1 cho mỗi parent project |
| `sort_order` | INT NOT NULL DEFAULT 0 | thứ tự hiển thị |
| `created_at` | DATETIME | |

Bốn cột `customer_id`, `company_name`, `branch_name`, `contact_name` trên `groupware_parent_projects` giữ nguyên và luôn bằng khách hàng chính. Server tự lấy tên từ dòng `groupware_customer` của khách hàng chính khi lưu danh sách, không tin tên do client gửi.

Migration (`group/caily/sql/create_parent_project_customers_table.sql`, chạy lại nhiều lần được): tạo bảng, rồi chép mỗi parent project có `customer_id` là số và tồn tại trong `groupware_customer` thành một dòng `is_primary = 1`.

### Server (`application/model/parentproject.php`, `customer.php`)

- `create` / `update` nhận thêm tham số `customers` (JSON, theo thứ tự: `[{customer_id, is_primary}]`). Có `customers` → thay toàn bộ danh sách, cập nhật bốn cột bản sao theo khách hàng chính. Bắt buộc ít nhất một khách; nếu không dòng nào được đánh dấu thì dòng đầu là chính.
- Không có `customers` (modal sửa nhanh, API upsert công khai, client cũ) → hành vi cũ trên bốn cột, sau đó đồng bộ bảng liên kết: khách mới thành chính (nếu đã có trong danh sách thì được đưa lên làm chính, nếu chưa thì thay dòng chính cũ).
- `getById` trả thêm `customers` (id, 会社名, 支店名, 担当様, TEL, email, `is_primary`), tên lấy trực tiếp từ `groupware_customer` nên luôn mới. Parent project chưa có dòng liên kết (ví dụ do CLI import ghi thẳng) → trả về một dòng chính dựng từ bốn cột cũ.
- `list` trả thêm `customer_count`; `list` và `paletteSearch` tìm thêm theo tên công ty/chi nhánh/担当様 của mọi khách trong danh sách.
- `customer.php::delete_customer` chặn xóa cả khi khách nằm trong danh sách của một parent project chưa bị xóa.
- `updateCustomerInfoForAllProjects` không đổi (vẫn đồng bộ tên bản sao của khách hàng chính).

### Giao diện

**Trang tạo (`create.php`) và chế độ sửa ở trang chi tiết (`detail.php`)**: ba ô chọn xếp tầng 会社名 → 支店名 → 担当様 được thay bằng một bảng danh sách khách hàng:

- Cột: 主 (nút radio) · 会社名 · 支店名 · 担当様 · nút xóa.
- Nút 「顧客検索」 mở hộp tìm kiếm có sẵn; bấm một kết quả là thêm vào danh sách (không thêm trùng). Nút 「新規顧客」 tạo khách mới và thêm luôn vào danh sách.
- Khách đầu tiên được thêm tự thành chính. Xóa khách hàng chính khi còn khách khác → khách đầu danh sách còn lại thành chính.
- Bắt buộc có ít nhất một khách (thay cho ba kiểm tra bắt buộc 会社名/支店名/担当様 hiện nay).

**Chế độ xem ở trang chi tiết**: hiển thị cùng bảng danh sách khách hàng như lúc sửa nhưng chỉ đọc (cột 主 đánh dấu khách hàng chính), thay cho ba ô 会社名/支店名/担当様. *(Cập nhật 2026-11-11 theo yêu cầu của người dùng.)*

**Nút 顧客情報** (trang tạo và trang chi tiết): mở modal danh sách khách hàng của parent project (主, 会社名, 支店名, 担当様, TEL, email); mỗi dòng có nút mở modal thông tin/sửa khách hiện có.

**Modal tạo/sửa project con trong trang chi tiết** (案件依頼を作成 / 案件依頼編集): không còn khối 顧客 (không có ô 「顧客情報は建物と同じ」, không có ô chọn khách). Tạo mới → project con nhận khách hàng chính của parent. Sửa → không gửi `customer_id`, khách hiện có của project con được giữ nguyên. *(Cập nhật 2026-11-11 theo yêu cầu của người dùng; thiết kế ban đầu là ô tick + ô chọn một khách trong danh sách của parent. Muốn đổi khách của project con thì dùng trang chi tiết project hoặc danh sách project.)* Cột 顧客情報 trong bảng 案件依頼 ở trang chi tiết parent cũng đã bỏ theo yêu cầu.

**Danh sách parent project (`index.php`)**: nhãn "+N" cạnh 会社名. Modal sửa nhanh 建物編集 dùng cùng bảng danh sách khách hàng (thêm bằng 顧客検索, chọn khách hàng chính, 顧客情報 mở modal khách hàng dùng chung của hệ thống); trang này không có form tạo khách mới nên không có nút 新規顧客. *(Cập nhật 2026-11-11 theo phản hồi của người dùng; ban đầu modal này chỉ sửa khách hàng chính.)*

**Sao chép parent project**: chép cả danh sách khách hàng sang form tạo mới.

### Bổ sung 2026-11-11: modal 建物情報 dùng chung (theo yêu cầu của người dùng)

- Modal 建物編集 được tách khỏi `parent_project/index.php` thành một ứng dụng Vue độc lập: markup ở `parent_project/partials/parent-project-quick-modal.php`, logic ở `parent_project/assets/js/parent-project-quick-modal.js`. Mở bằng `window.openParentProjectModal(id, { mode: 'view' | 'edit', onSaved })`.
- Chế độ xem (建物情報): bảng danh sách khách hàng chỉ đọc + các trường của parent project; nút 編集 chỉ hiện khi `$_SESSION['isProjectManager']` và chuyển sang form sửa ngay trong modal; 建物詳細を開く dẫn tới trang chi tiết parent project.
- Nơi dùng: danh sách parent project (建物編集, mở thẳng chế độ sửa), trang chi tiết project (nút 建物情報 thay cho khối xổ xuống cũ), danh sách project (nút 建物詳細; Ctrl/Shift/chuột giữa vẫn mở trang parent project ở tab mới).
- Sau khi lưu: danh sách parent project nạp lại; trang chi tiết project nạp lại dữ liệu (nếu không đang sửa project); danh sách project nạp lại bảng.

### Bổ sung 2026-11-11: bỏ chế độ sửa trực tiếp trên trang 建物詳細 (theo yêu cầu của người dùng)

- Trang `parent_project/detail.php` chỉ còn chế độ xem; nút 編集 mở modal dùng chung ở chế độ sửa, lưu xong trang nạp lại dữ liệu.
- Để không mất trường nào, form sửa trong modal được bổ sung: 市区町村, ghi chú của お客様提供資料 (その他), 省エネ向け資料 kèm ghi chú, giữ nguyên các loại 依頼 không có ô tick (ví dụ 3D), và xác nhận khi trùng お施主様名 / 工事番号 trước khi lưu.
- Không còn trong form sửa: nút 新規顧客 (tạo khách mới ngay trong lúc sửa parent project) và cảnh báo trùng ngay khi rời ô nhập (vẫn còn xác nhận lúc lưu). Tạo khách mới vẫn làm được ở trang tạo parent project và trang 顧客情報.
- Modal tìm kiếm / danh sách khách hàng của modal dùng chung có id riêng (`qmCustomerSearchModal`, `qmCustomerListModal`) để không trùng với modal danh sách của chính trang chi tiết.

## Quyết định do người thực hiện tự chọn (chưa được người dùng nêu, cần xem lại khi duyệt)

1. Thay hẳn ba ô xếp tầng bằng bảng danh sách trên trang tạo và trang chi tiết; thêm khách chỉ qua tìm kiếm hoặc tạo mới.
2. Modal project con: bỏ tick thì chỉ chọn trong danh sách của parent (không còn chọn tự do hay tạo khách mới tại đó; muốn thêm thì thêm vào danh sách của parent trước).
3. ~~Modal sửa nhanh ở danh sách parent project chỉ sửa khách hàng chính.~~ Đã đổi theo phản hồi của người dùng: modal 建物編集 sửa cả danh sách.
4. Ngoài file SQL, model tự tạo bảng và chép dữ liệu lần đầu khi chạy (theo kiểu các hàm `ensure*` đang có) để trang không lỗi nếu quên chạy SQL.
5. Tìm theo khách hàng phụ chỉ áp dụng cho danh sách parent project và command palette, không áp dụng cho danh sách project.

## Kiểm tra

Trên môi trường cục bộ (http://localhost/, DB `guis2_group_2025`): tạo parent project nhiều khách; đổi khách hàng chính; xóa khách; mở parent project cũ; tìm theo khách phụ; tạo/sửa project con với tick và bỏ tick; sửa nhanh ở danh sách; kiểm tra danh sách project, trang chi tiết project và báo giá vẫn hiển thị khách hàng chính.

## Lỗi có sẵn đã ghi nhận, không sửa trong việc này

- Đổi 会社名/支店名 ở trang chi tiết và modal sửa nhanh xóa tên nhưng không xóa `customer_id` cũ.
- `delete_customer` không kiểm tra `groupware_projects.customer_id`.
- `cli/scan_value_errors.php` truy vấn bảng `customers` (sai tên).
