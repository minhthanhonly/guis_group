# Chuyển 3 hạng mục 省エネ từ công trình (建物) sang dự án con

Ngày: 2026-11-11 · Trạng thái: chờ duyệt · Cỡ việc: thay đổi nhỏ–vừa (bản thiết kế ngắn)

## Mục đích

省エネ向け資料 (kèm ghi chú その他), 申請提出先の審査機関 và BELSのZEH等級 hiện được nhập ở form công trình khi 依頼 có 省エネ. Người dùng muốn nhập chúng ở dự án con, vì đó là thông tin của phần việc 省エネ chứ không phải của cả công trình.

## Mục tiêu

1. Modal tạo (案件依頼を作成) và modal sửa dự án con trong trang chi tiết công trình: hiện 3 hạng mục khi 部署 được chọn có tên chứa 「省エネ」 (cùng quy tắc `LIKE '%省エネ%'` đang dùng ở `project.php`).
2. Giá trị được lưu riêng cho từng dự án con.
3. Bỏ 3 hạng mục khỏi form công trình: trang tạo, trang chi tiết, modal/danh sách công trình.
4. Trang chi tiết dự án con, danh sách dự án (2 cột 申請提出先の審査機関 / BELSのZEH等級) và tìm kiếm từ khoá đọc từ dự án con.
5. Dữ liệu đã nhập ở công trình được chép sang các dự án con 省エネ của công trình đó.

## Ngoài phạm vi

- Ô tick 依頼「省エネ」 và trường 特徴 của công trình: giữ nguyên.
- Form `project/create.php` và sửa trực tiếp ở trang chi tiết dự án con: không thêm ô nhập (trang chi tiết chỉ hiển thị). *Đây là quyết định của tôi theo "Target" trong yêu cầu; nói nếu bạn muốn thêm.*
- 省エネ図面共有, phí 省エネ, trạng thái 計算終了/申請作業中: không đụng.
- Không xoá 4 cột cũ khỏi bảng công trình.

## Điều không được xảy ra

- Mất giá trị đã nhập ở công trình.
- Dự án con không thuộc 部署 省エネ mang giá trị 3 hạng mục.
- Hỏng phần nhiều khách hàng (multi-customer) đang sửa dở, chưa commit, trên cùng các file.

## Các cách đã cân nhắc

| | Cách làm | Đánh đổi |
|---|---|---|
| **1 (đã chọn)** | Thêm cột ở dự án con, chép dữ liệu cũ sang, giữ cột cũ ở công trình làm bản lưu | Không mất dữ liệu, quay lại được; DB còn 4 cột không dùng |
| 2 | Thêm cột ở dự án con, không chép | Ít việc; dữ liệu cũ biến mất khỏi giao diện |
| 3 | Vẫn lưu ở công trình, chỉ đổi chỗ nhập sang modal dự án con | Ít đổi DB; nhiều dự án con 省エネ dùng chung một giá trị, trái mục tiêu 2 |

Người dùng chọn 1, và chọn bỏ hẳn khỏi form công trình.

## Thiết kế

**DB.** Thêm vào `groupware_projects`: `energy_materials` TEXT, `energy_materials_memo` VARCHAR(500), `application_review_agency` VARCHAR(255), `bels_zeh_grade` VARCHAR(10), đều NULL mặc định. Tự thêm khi chạy theo kiểu `ensureProgressStartedAtColumn`, kèm file `sql/add_energy_fields_to_projects.sql`.

**Chuyển dữ liệu.** Trong cùng file SQL, một câu UPDATE chép từ công trình sang mọi dự án con có `parent_project_id` khớp và 部署 `LIKE '%省エネ%'`, chỉ khi cột đích còn trống (chạy lại không ghi đè). Chạy tay một lần. *Bổ sung sau rà soát (2026-11-12): chỉ chép từ công trình có 依頼 chứa 省エネ — đúng điều kiện mà giao diện cũ dùng để hiện 3 hạng mục — vì cột `bels_zeh_grade` của công trình có mặc định `'0'` nên chép tất cả sẽ làm hiện 「0 : 該当なし（非ZEH）」 trên hàng nghìn dự án trước đây không hiển thị gì.* Công trình có dữ liệu nhưng chưa có dự án con 省エネ: giá trị chỉ còn trong cột cũ, không hiển thị.

**Model `project.php`.** `create()` và `update()` nhận 4 trường; nếu 部署 không phải 省エネ thì lưu rỗng. Câu SELECT danh sách, ánh xạ cột `parent_application_review_agency` / `parent_bels_zeh_grade` và tìm kiếm từ khoá đổi nguồn từ `pp.*` sang `p.*` (giữ nguyên key cột để không hỏng bố cục cột người dùng đã lưu). Ghi lịch sử thay đổi cho 3 hạng mục như các trường khác.

**Model `parentproject.php`.** Ngừng đọc/ghi 4 trường này (`features` vẫn giữ). Gợi ý tagify 審査機関 gom từ bảng dự án con.

**Giao diện.**
- `parent_project/detail.php` + `parent-project-detail.js`: thêm khối 3 hạng mục vào hai modal dự án con (checkbox 資料 + ô ghi chú khi tick その他, tagify 審査機関, select ZEH), điều kiện theo 部署; gỡ khối tương ứng khỏi form công trình.
- `parent_project/create.php`, `index.php` và JS của chúng: gỡ 3 hạng mục.
- `project/detail.php` + `project-detail.js`: hiển thị từ dữ liệu dự án con, điều kiện là dự án thuộc 部署 省エネ thay cho 依頼 của công trình.

## Để người thực hiện quyết

- Tách khối 3 hạng mục thành partial/mixin dùng chung cho hai modal hay viết lặp lại.
- Tên hàm, thứ tự cột, vị trí khối trong modal (đề xuất: ngay dưới hàng 案件名 / 部署).

## Kiểm tra

Tạo và sửa dự án con với 部署 省エネ và 部署 khác; đổi 部署 qua lại; xem trang chi tiết dự án con, danh sách dự án, tìm kiếm theo 審査機関 và nhãn ZEH; xác nhận form công trình không còn 3 hạng mục và phần nhiều khách hàng vẫn chạy.
