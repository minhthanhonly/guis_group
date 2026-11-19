# Quản lý tòa nhà / công trình (親案件): khả năng tra cứu và thao tác cho AI

Ngày: 2026-11-18. Nguồn: `application/model/parentproject.php`, `parent_project/*.php`, `parent_project/assets/js/*.js`, và số liệu của DB cục bộ `guis2_group_2025`.
Tài liệu này chỉ là nghiên cứu và đề xuất; chưa có thay đổi code nào đi kèm.

## 1. Dữ liệu thật trông như thế nào

Số liệu DB cục bộ (6.241 tòa nhà chưa xóa):

| Điểm | Số liệu | Hệ quả cho tra cứu |
|---|---|---|
| Trạng thái tòa nhà | 100% là `draft` | Lọc theo trạng thái tòa nhà vô nghĩa; trạng thái thật nằm ở dự án con |
| Dự án con mỗi tòa nhà | trung bình 1,1; nhiều nhất 18; 1 tòa nhà không có dự án con | "Tình hình tòa nhà" phải tổng hợp từ dự án con |
| Loại yêu cầu `requests` | 省エネ 5.750; 3D設備 119; 意匠 116; 意匠,設備 91; … | Bộ lọc hữu ích nhất sau từ khóa |
| `type2` (loại nhà) | 長屋 3.036; 共同 1.166; 戸建 81; 集合 68; … | Lọc và thống kê được; có giá trị trùng nghĩa (戸建 / 戸建て) |
| `type1` (dòng sản phẩm) | hàng chục giá trị, có cả xuống dòng trong tên | Chỉ nên tìm gần đúng, không dùng làm danh sách cố định |
| Số công trình (工事番号) | 5.871 tòa nhà có | Khóa tra cứu chính của người dùng |
| Khách hàng | 6.240 có `customer_id`; bảng nhiều khách hàng 6.240 dòng | Tìm theo khách hàng là nhu cầu thật |
| Ghi chú | cột `notes` có ở 5.751 tòa nhà; bảng ghi chú riêng chỉ 38 dòng | Đọc `notes` quan trọng hơn bảng ghi chú |
| Yêu thích, báo giá | 0 dòng | Chưa ai dùng; không ưu tiên |
| Lịch sử thay đổi | 527 dòng | Có thể trả lời "ai sửa gì, khi nào" |

## 2. Tra cứu: hệ thống làm được gì, AI làm được gì

| # | Nhu cầu tra cứu | Hệ thống hiện có | AI hiện nay | Đề xuất |
|---|---|---|---|---|
| T1 | Tìm theo từ khóa: tên chủ nhà, công ty, chi nhánh, người liên hệ, số công trình, mã tòa nhà, id; cả khách hàng phụ | `parentproject.list` tham số `search` | Chưa: `search_buildings` chỉ nhận id, mã hoặc id dự án | Thêm `keyword` vào `search_buildings`, gọi đúng `list()` của trang |
| T2 | Lọc theo loại yêu cầu (意匠 / 設備 / 3D設備 / 省エネ / その他 / 3D) | `list` tham số `request_filter` | Chưa | Thêm `request` vào `search_buildings` |
| T3 | Sắp theo hạn gần nhất của dự án con chưa xong | `list` với `order_column=nearest_nouki` | Chưa | Thêm `sort: nearest_deadline` để trả lời "tòa nhà nào sắp tới hạn" |
| T4 | Sắp theo số dự án con, người tạo, ngày tạo | `list` (`child_project_count`, `created_by_name`, `created_at`) | Chưa | Gộp vào tham số `sort` |
| T5 | Chi tiết một tòa nhà | `getById` | Có (`search_buildings` theo id / mã / id dự án) | — |
| T6 | Dự án con của tòa nhà: tên, bộ phận, trạng thái, hạn, người nhận | `getChildProjects` | Chưa | Tool đọc `get_building_projects`; hoặc mở danh sách dự án lọc theo số công trình |
| T7 | Khách hàng của tòa nhà (một chính, nhiều phụ) | bảng `parent_project_customers` | Chỉ khách hàng chính (`get_customer_info`) | Trả cả danh sách, đánh dấu khách hàng chính |
| T8 | Lịch sử thay đổi | `getLogs` | Chưa | Tool đọc `get_building_history` (20 dòng gần nhất) |
| T9 | Ghi chú của tòa nhà | `getNotes` + cột `notes` | Cột `notes` có trong chi tiết; bảng ghi chú thì chưa | Thêm vào kết quả chi tiết |
| T10 | Kiểm tra trùng trước khi tạo (theo số công trình hoặc tên chủ nhà, khớp chính xác) | `findDuplicate` | Chưa | Gọi tự động trước khi đề xuất tạo tòa nhà |
| T11 | Thư mục và tệp đính kèm | `getByParentProject` | Chỉ mở trang | Tool đọc liệt kê tên thư mục và tên tệp (không đọc nội dung) |
| T12 | Thống kê: số tòa nhà theo loại yêu cầu, loại nhà, khách hàng, theo tháng tạo; tòa nhà chưa có dự án con | Không có API | Chưa | Tool đọc `get_building_statistics`, số cộng sẵn ở server |
| T13 | Hiển thị kết quả trên trang danh sách tòa nhà | Trang không nhận bộ lọc qua URL | Chỉ mở trang trống bộ lọc | Thêm đọc URL có `ai=1` vào `parent-project-index.js`, như đã làm với danh sách dự án |

## 3. Thao tác: hệ thống làm được gì, AI làm được gì

| # | Thao tác | Hệ thống hiện có | Kiểm tra quyền trong model | AI hiện nay | Đề xuất |
|---|---|---|---|---|---|
| O1 | Tạo tòa nhà (bắt buộc công ty và tên; mã tự sinh; nhận danh sách khách hàng) | `create` | không có | Có, cho administrator / project manager | Thêm kiểm tra trùng (T10) trước khi hiện thẻ; hỗ trợ nhiều khách hàng |
| O2 | Sửa thông tin | `update` | không có; khóa phiên bản chỉ kiểm khi gửi `version` | Có | Ghi phiên bản lúc đề xuất và gửi khi thực thi, như với dự án |
| O3 | Đổi trạng thái | `updateStatus` (5 giá trị) | không có | Có (qua sửa) | Không ưu tiên: thực tế không ai dùng trạng thái tòa nhà |
| O4 | Tạo dự án con cho tòa nhà | `project.create` với `parent_project_id` | không có | Có (`project_create`) | Cho AI tự điền `parent_project_id` từ trang đang mở |
| O5 | Thêm ghi chú tòa nhà | `addNote` | không có | Chưa | Thêm, giới hạn cho người có quyền xem dự án |
| O6 | Sửa / xóa ghi chú | `updateNote`, `deleteNote` | tác giả hoặc administrator | Chưa | Chỉ sửa; không xóa |
| O7 | Đánh dấu yêu thích | `toggleFavorite` | của chính mình | Chưa | Rủi ro thấp nhưng chưa ai dùng |
| O8 | Đồng bộ số công trình từ dự án | `updateConstructionNumberByProject` | `canUserEditProject` | Chưa | Có thể thêm |
| O9 | Đẩy thông tin khách hàng xuống mọi dự án con | `updateCustomerInfoForAllProjects` | không có | Chưa | Chỉ thêm nếu có thẻ cảnh báo nêu số dự án bị ảnh hưởng |
| O10 | Tệp và thư mục: tải lên, đổi tên, xóa | `uploadAttachment`, `*Folder`, `deleteFile(s)` | không có | Chưa | Không giao cho AI |
| O11 | Báo giá | `quotation.*` | không có | Chưa | Chưa ai dùng; bỏ qua |
| O12 | Xóa tòa nhà (xóa cả dự án con) | `delete`, cần `confirm=DELETE` | administrator | Cấm | Giữ cấm |

## 4. Rủi ro và điểm cần lưu ý

- **Model không kiểm tra quyền** ở tạo, sửa, đổi trạng thái, ghi chú, tệp. Hiện lớp AI tự chặn: chỉ administrator hoặc project manager của bộ phận mới đề xuất được thay đổi tòa nhà. Mọi thao tác thêm mới phải giữ cách chặn này.
- **`parentproject.list` đưa tham số `status` thẳng vào SQL** (`p.status = '%s'` không escape). Tool AI không được truyền `status` từ model vào hàm này; nếu cần lọc trạng thái thì chỉ nhận giá trị trong danh sách cố định.
- **Sửa tòa nhà không bắt buộc khóa phiên bản**, nên AI có thể ghi đè thay đổi của người khác vừa lưu. Đề xuất O2 xử lý việc này.
- **Dữ liệu không đồng nhất:** `type1` có xuống dòng, `type2` có giá trị trùng nghĩa. Thống kê theo các cột này nên nêu rõ là nhóm theo giá trị gốc.
- **Trạng thái tòa nhà không phản ánh thực tế.** Câu hỏi kiểu "tòa nhà nào đang làm" phải được trả lời bằng trạng thái dự án con.

## 5. Thứ tự đề xuất

**Đợt 1 — tra cứu (chỉ đọc):** T1, T2, T3, T4, T6, T7, T9, T12. Mở rộng `search_buildings` (từ khóa, loại yêu cầu, sắp xếp), thêm `get_building_projects` và `get_building_statistics`. Không đụng tới file của trang.

**Đợt 2 — hiển thị trên trang:** T13. Thêm đọc bộ lọc từ URL vào trang danh sách tòa nhà để AI mở trang kèm từ khóa, chat chỉ nêu số lượng.

**Đợt 3 — thao tác an toàn hơn:** T10 + O1 (kiểm tra trùng khi tạo), O2 (khóa phiên bản khi sửa), O4 (tạo dự án con từ trang tòa nhà), O5 (thêm ghi chú tòa nhà).

**Để sau hoặc không làm:** T8, T11, O8, O9; O3, O7, O11 vì chưa ai dùng; O10, O12 không giao cho AI.

## 6. Đợt 1 đã làm (2026-11-18)

| Mục | Kết quả |
|---|---|
| T1, T2, T3, T4 | `search_buildings` nhận thêm `keyword`, `request`, `sort` (`newest`, `nearest_deadline`, `most_projects`); trả tổng số, số dự án con và hạn gần nhất của từng tòa nhà. Dùng chính `ParentProject::list()` của trang; không bao giờ truyền `status` |
| T6 | `get_building_projects`: dự án con của một tòa nhà (theo id hoặc mã), kèm số theo trạng thái |
| T7, T9 | Chi tiết một tòa nhà có thêm danh sách mọi khách hàng (đánh dấu khách hàng chính) và các ghi chú gần nhất |
| T12 | `get_building_statistics`: tổng số, số tòa nhà chưa có dự án con, theo loại yêu cầu, theo loại nhà, 10 khách hàng nhiều tòa nhà nhất, số tạo theo 6 tháng gần nhất |

Test: `tests/ai/building-search.test.php` (26 kiểm tra). Còn lại theo thứ tự đề xuất: đợt 2 (hiển thị trên trang danh sách tòa nhà), đợt 3 (kiểm tra trùng khi tạo, khóa phiên bản khi sửa, tạo dự án con từ trang tòa nhà, ghi chú tòa nhà).

## 7. Đợt 2 đã làm (2026-11-18)

- Tool `show_buildings` (từ khóa, loại yêu cầu, sắp xếp): mở `/parent_project/index.php?ai=1&search=…&request_filter=…&order_column=…&order_dir=…`; khung chat chỉ nhận số lượng, lấy từ chính `ParentProject::list()`.
- `parent-project-index.js`: thêm `applyAssistantFiltersFromUrl()`, chỉ chạy khi địa chỉ có `ai=1`; áp bộ lọc một lần, lưu như bộ lọc người dùng tự đặt, rồi xóa tham số khỏi địa chỉ. `PROJECT_CACHE_VERSION` tăng lên 1.7.19.
- Trang không có nút lọc "3D" nên `show_buildings` không nhận giá trị đó (`search_buildings` vẫn nhận).
- AI dùng `show_buildings` khi người dùng muốn xem / tìm danh sách; dùng `search_buildings` khi cần dữ liệu của một tòa nhà để trả lời.

Test: `tests/ai/building-page.test.php` (14 kiểm tra) và 6 kiểm tra địa chỉ trong `tests/ai/render.test.js`.

## 8. Đợt 3 đã làm (2026-11-18)

- **Cảnh báo trùng khi tạo tòa nhà (T10 + O1):** trước khi hiện thẻ xác nhận, server gọi `ParentProject::findDuplicate` (trùng số công trình hoặc tên chủ nhà, khớp chính xác). Nếu trùng, thẻ có dòng cảnh báo nêu mã, tên và id tòa nhà đã có. Người dùng vẫn có thể xác nhận, giống form tạo tòa nhà.
- **Khóa phiên bản khi sửa (O2):** phiên bản tòa nhà được ghi lúc đề xuất và gửi lúc thực thi. Nếu người khác lưu tòa nhà đó trong lúc chờ xác nhận, thao tác bị từ chối. Hai lần sửa cùng một tòa nhà trong một lần xác nhận vẫn chạy được.
- **Ghi chú tòa nhà (O5):** loại hành động `building_note_add` (id tòa nhà, nội dung, đánh dấu quan trọng). Ghi chú là văn bản thuần, tiêu đề là dòng đầu (tối đa 50 ký tự), như form ghi chú của trang. Ai dùng được mô-đun dự án đều thêm được, giống trang hiện tại.
- **Tạo dự án con từ trang tòa nhà (O4):** AI lấy id tòa nhà của trang đang mở làm `parent_project_id`.
- **Bổ sung trường sửa được:** `city`, `type3`, `request_type` (trước đó bị danh sách trắng loại bỏ nên "sửa thành phố" không có tác dụng).

Test: `tests/ai/building-write.test.php` (18 kiểm tra, có thực thi thật rồi hoàn lại).

## Tìm tòa nhà theo 建物情報 (2026-11-18)

`search_buildings` nhận thêm sáu bộ lọc, khớp một phần và kết hợp được: `scale` (建物規模, ví dụ W2F3J hoặc một phần như W2F), `city` (市区町村), `type1` (種類1), `house_type` (住宅種類), `structural_office` (構造事務所), `feature` (特徴). Bộ lọc được thêm vào chính `ParentProject::list` (tham số GET tùy chọn `scale`, `city`, `type1`, `type2`, `structural_office`, `features`; ký tự `%` `_` bị bỏ), nên AI vẫn đi qua API của trang. Trang danh sách tòa nhà chưa có ô lọc cho các trường này, vì vậy kết quả được liệt kê trong chat (tối đa 20 dòng kèm tổng số).

Khi kết hợp nhiều bộ lọc mà không có kết quả, tool trả số tòa nhà khớp từng bộ lọc riêng (`matches_per_filter`) để AI giải thích ngay, không tra lại nhiều lần.

Dữ liệu local: 6.241 tòa nhà; có 建物規模 6.141, 種類1 4.910, 住宅種類 4.556, 市区町村 499, 構造事務所 11, 特徴 1. Các trường ít dữ liệu sẽ cho ít kết quả.

Kèm theo: lượt trả lời cuối của vòng lặp (sau 4 lượt tra cứu) giờ có câu nhắc "Answer now…" và nếu vẫn rỗng thì trả thông báo "質問の範囲が広すぎます" thay vì "リクエストを処理できませんでした". Test: `tests/ai/building-attributes.test.php` (23 kiểm tra).

## Ô lọc 建物情報 trên trang danh sách tòa nhà (2026-11-18)

- Trang `parent_project/index.php` có thêm một hàng sáu ô nhập: 建物規模, 市区町村, 種類1, 住宅種類, 構造事務所, 特徴. Gõ một phần giá trị là đủ; các ô kết hợp với ô tìm kiếm và nút 依頼. Giá trị được lưu cùng bộ lọc khác trong `localStorage` và xóa bằng nút 絞り込みを解除.
- `show_buildings` nhận cùng sáu bộ lọc và mở trang với các ô đã điền sẵn (địa chỉ có `ai=1` và đủ sáu tham số, rỗng khi không dùng).
- `PROJECT_CACHE_VERSION` 1.7.20.

## Gộp bộ lọc 建物情報 vào ô "検索..." (2026-11-18)

Thay cho hàng sáu ô lọc (đã bỏ), ô tìm kiếm duy nhất của trang danh sách tòa nhà giờ tìm cả trong 建物規模, 市区町村, 種類1, 住宅種類, 構造事務所, 特徴, ngoài các trường tên và số như trước.

- Nhiều từ tách bằng dấu cách (thường hoặc toàn chiều rộng), "," , "、" hoặc "，": tòa nhà phải chứa tất cả các từ, mỗi từ ở trường nào cũng được. Tối đa 8 từ. Cả chuỗi vẫn được thử như một cụm như trước, nên kết quả cũ không mất.
- Ô tìm kiếm có chú thích (tooltip) nói rõ nó tìm ở đâu.
- `show_buildings` ghép các giá trị thuộc tính vào ô tìm kiếm (ví dụ "W2F 横浜市"); địa chỉ chỉ còn `search`, `request_filter`, `order_column`, `order_dir`.
- `search_buildings` vẫn giữ sáu bộ lọc chính xác theo từng trường (tham số GET tùy chọn của `ParentProject::list`), dùng khi cần đúng trường và để báo số lượng theo từng điều kiện.
- `PROJECT_CACHE_VERSION` 1.7.21.
