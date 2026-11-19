# Chức năng của hệ thống và tool AI tương ứng

Ngày: 2026-11-18. Khảo sát trên nhánh `pdfshare` + thay đổi của worktree `claude-api-chat-ui-fd6d77`.
Cột **AI**: `có` = AI làm được qua tool; `mở trang` = AI chỉ mở trang đó; `chưa` = chưa có tool.
Mọi tool chạy với quyền của người đang đăng nhập; thao tác ghi luôn cần bấm 確認.

## 1. Tòa nhà (親案件)

| Chức năng | Trang / API | AI |
|---|---|---|
| Danh sách, tìm kiếm, lọc trạng thái, yêu thích | `/parent_project/index.php` (không lọc được qua URL) | mở trang (`open_page building_list`); tra theo id / mã bằng `search_buildings` |
| Chi tiết tòa nhà, dự án con, lịch sử | `/parent_project/detail.php?id=` | mở trang (`open_page building`) |
| Tạo / sửa tòa nhà, đổi trạng thái | `parentproject.create / update / updateStatus` | có (`propose_actions`: `parent_project_create`, `parent_project_update`) |
| Thông tin khách hàng của tòa nhà | — | có (`get_customer_info`) |
| Ghi chú tòa nhà (thêm / sửa / xóa) | `parentproject.getNotes / addNote / updateNote / deleteNote` | chưa |
| Thư mục và tệp đính kèm | `/parent_project/attachment.php?id=` | mở trang (`open_page building_files`) |
| Báo giá của tòa nhà | `quotation.*` | chưa |
| Đẩy thông tin khách hàng xuống mọi dự án con | `parentproject.updateCustomerInfoForAllProjects` | chưa (thao tác hàng loạt) |
| Xóa tòa nhà (xóa cả dự án con, chỉ administrator) | `parentproject.delete` | cấm |

## 2. Dự án (案件)

| Chức năng | Trang / API | AI |
|---|---|---|
| Danh sách có bộ lọc (bộ phận, trạng thái, từ khóa, team, công ty, người phụ trách, 担当, ưu tiên, ngày, của tôi) | `/project/index.php` | có (`show_projects`: mở trang với bộ lọc, chat nêu số lượng) |
| Chi tiết, gantt, bản vẽ, đính kèm | `/project/detail.php`, `gantt.php`, `drawings.php`, `attachment.php` | mở trang (`open_page`) |
| Gantt nhiều dự án | `/project/project_gantt.php` | mở trang |
| Tạo / sửa dự án, trạng thái, 受注形態 | `project.create / update` | có (`propose_actions`) |
| Thành viên, manager, team của dự án | `project.addMemberApi`, `updateTeams` … | có (`propose_actions`) |
| Sửa nhanh: ưu tiên, ngày, tiến độ, tag | `project.updatePriority / updateProjectDate / updateProgress / updatePojectTags` | một phần (qua `project_update`) |
| Bình luận, luồng thảo luận, like | `project.getComments / addComment …` | chưa |
| Tệp đính kèm: tải lên, xóa, thư mục | `project.uploadAttachment / deleteAttachment / *Folder` | chưa |
| Số tiền, trạng thái chứng từ / thanh toán | `project.updateAmount / updateProjectStatus` | chưa |
| Hủy dự án (xóa mềm) | `project.delete` | cấm |

## 3. Task

| Chức năng | Trang / API | AI |
|---|---|---|
| Task của một dự án | `/project/task.php?project_id=` | có (`show_tasks`) |
| Task xuyên dự án: theo người, team, bộ phận, của tôi, theo tháng | `/project/task_overview.php` | có (`show_task_overview`) |
| Tạo task | form trong chat | có (`show_task_form`) |
| Sửa task (tiêu đề, mô tả, trạng thái, ưu tiên, người phụ trách, ngày, tiến độ) | `task.edit` | có (`propose_actions`: `task_update`) |
| Thêm task mặc định của bộ phận | `task.createMissingDefaultTasks` | chưa |
| Bấm giờ làm việc, sửa thời gian | `task.start/stopTaskTimer …` | chưa |
| Bình luận, reaction, xác nhận đã nhận task | `task.addComment / toggleTaskReaction / acknowledgeTask` | chưa |
| Sắp xếp, quan hệ cha con, liên kết gantt | `task.updateOrder / setParent / addTaskLink` | chưa |
| Xóa task (xóa cả bản vẽ của task) | `task.delete` | cấm |

## 4. Ghi chú dự án (bảng memo)

| Chức năng | API | AI |
|---|---|---|
| Đọc ghi chú của dự án (theo quyền xem của ghi chú) | `projectnote.list` | có (`get_project_notes`) |
| Thêm ghi chú | `project.addNote` | chưa |
| Sửa, ghim quan trọng | `project.updateNote / toggleNoteImportant` | chưa |
| Sắp xếp bảng của riêng mình | `project.reorderNotes / moveNoteOnBoard` | chưa |
| Xóa ghi chú | `project.deleteNote` | cấm |

## 5. Bản vẽ

| Chức năng | API | AI |
|---|---|---|
| Danh sách bản vẽ của dự án | `/project/drawings.php?project_id=` | mở trang |
| Thêm / sửa / đổi trạng thái / giao người / đơn giá | `drawing.*` | chưa |
| Xóa, xóa hàng loạt | `drawing.delete / bulkDelete` | cấm |

## 6. Thống kê

| Chức năng | Trang / API | AI |
|---|---|---|
| Số dự án theo trạng thái và bộ phận, số task theo trạng thái | — | có (`get_statistics`) |
| Team và người rảnh trong một khoảng thời gian | — | có (`get_availability`) |
| Doanh thu tháng của bộ phận (báo giá, hóa đơn, thanh toán, mục tiêu) | `/project/revenue_statistics.php` | có (`show_revenue_statistics`; chỉ người được trang cho xem) |
| Thống kê nhân viên, team, năm | `/project/employee_statistics.php` | mở trang |
| Mục tiêu doanh thu của team | `/project/team_revenue_targets.php` | chưa |
| Quản lý thanh toán | `/project/invoiced_projects.php` | chưa |
| Thống kê chấm công | trang chủ | chưa |
| Đóng / mở sổ doanh thu tháng | `project.closeRevenueMonth` | cấm |

## 7. Các trang khác

| Khu vực | Chức năng chính | AI |
|---|---|---|
| Khách hàng | danh sách, thêm, sửa, xóa, nhập hàng loạt, phân loại | tìm bằng `search_customers`; mở trang `customers` |
| Đơn từ (申請) | 13 loại đơn; tạo, sửa, duyệt, từ chối, bình luận | đếm và mở danh sách có lọc (`show_requests`); mở một đơn (`open_page request`) |
| Lịch | xem, thêm, sửa, xóa sự kiện | mở trang |
| Todo cá nhân | thêm, sửa, hoàn thành, xóa | chưa |
| Chấm công | vào/ra ca, sửa giờ, ngày nghỉ, cấu hình, CSV | mở trang |
| Thành viên | danh sách, hồ sơ, thêm/sửa/khóa tài khoản, đổi mật khẩu | mở trang "đang online"; phần còn lại cấm |
| お知らせ (forum), lưu trữ tệp | xem, đăng, tải lên | mở trang |
| Thiết lập (bộ phận, team, chi nhánh, con dấu, nhóm, bảo trì, sao lưu) | quản trị | cấm |

## 8. Thao tác ghi có thể thêm cho AI (cần quyết định)

Nhóm A, rủi ro thấp, model đã có kiểm tra quyền hoặc chỉ tác động dữ liệu của chính người dùng:
- Thêm ghi chú dự án; ghim / bỏ ghim ghi chú.
- Todo cá nhân: thêm, đánh dấu hoàn thành.
- Thêm sự kiện lịch của chính mình.
- Task: xác nhận đã nhận, đổi tiến độ.

Nhóm B, model chưa kiểm tra quyền, phải bọc thêm kiểm tra ở lớp AI trước khi mở:
- Bản vẽ: thêm, đổi trạng thái, giao người.
- Ghi chú tòa nhà; báo giá.
- Khách hàng: thêm, sửa.
- Bình luận dự án và task.

Nhóm C, không nên giao cho AI: mọi thao tác xóa, duyệt đơn từ, chấm công thay người khác, tài khoản và mật khẩu, thiết lập hệ thống, đóng sổ doanh thu.

## 9. Vấn đề phân quyền có sẵn trong API (ngoài phạm vi phần AI)

Các lượt khảo sát ghi nhận, chưa kiểm chứng sâu, nhiều hàm gọi được qua `/api/index.php` mà không kiểm tra quyền ngoài việc đã đăng nhập:
- Tòa nhà: tạo, sửa, đổi trạng thái; xóa tệp và thư mục.
- Bản vẽ: thêm, sửa, xóa, đổi trạng thái. Task: đổi người phụ trách, đổi task cha.
- Bộ phận, team, chi nhánh, con dấu: thêm, sửa, xóa.
- Chấm công: vào/ra ca và sửa giờ cho người khác bằng tham số `owner` / `userid`; xuất CSV của người khác.
- Thống kê nhân viên và mục tiêu doanh thu: không kiểm tra ở server; `employeestatistics.getCailyTimecardApiUrl` trả về URL kèm token.
- `api/NotificationExportAPI.php` không yêu cầu đăng nhập; `api/NotificationAPI.php` nhận `user_id` bất kỳ.
- `timecard.getStatistic` đưa `userid` vào SQL không escape.
- `backup.createZipBackup` có thể gọi được qua API mà không kiểm tra admin.

Tool AI không dùng các hàm này trực tiếp; mỗi tool tự kiểm tra quyền trước khi gọi model.

## 10. Cập nhật sau khi liệt kê (2026-11-18)

**Nhóm A đã thêm** (đều qua `propose_actions`, cần bấm 確認):

| Loại hành động | Việc | Ai được làm |
|---|---|---|
| `project_note_add` | Thêm ghi chú vào dự án (nhãn, phạm vi xem all / members / self, ghim) | người nhìn thấy dự án |
| `project_note_toggle_pin` | Ghim / bỏ ghim một ghi chú | người nhìn thấy dự án; model còn kiểm tra quyền sửa ghi chú |
| `todo_add`, `todo_complete` | Thêm todo, đánh dấu hoàn thành | chỉ trên todo của chính mình |
| `schedule_add` | Thêm sự kiện vào lịch (có thể đặt riêng tư) | chỉ lịch của chính mình |
| `task_progress` | Đổi tiến độ task (0–100) | người nhìn thấy dự án; model còn kiểm tra quyền với task |
| `task_acknowledge` | Xác nhận đã nhận task | người được giao task |

Quyền giờ có ba mức (`AI::authorizeAction`): dữ liệu của chính mình; người nhìn thấy dự án; administrator / project manager cho các loại cũ.

**Từ dự án sang tòa nhà:** `search_buildings` nhận `project_id` và trả đầy đủ chi tiết tòa nhà (khách hàng, chi nhánh, người liên hệ, loại, quy mô, thành phố, ghi chú). `search_projects` có thêm cột `building_id`, `building_number`. `open_page building` nhận `project_id`. Trang tòa nhà gửi kèm id tòa nhà đang mở cho AI.
