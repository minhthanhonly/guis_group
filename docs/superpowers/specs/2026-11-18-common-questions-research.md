# Những câu nhân viên và quản lý dự án hay hỏi: AI đáp ứng tới đâu

Ngày: 2026-11-18.

**Căn cứ:** danh sách này suy ra từ chức năng và dữ liệu của hệ thống (trạng thái dự án, các cột hạn, task, đơn từ, chấm công, thống kê) và từ những gì đã thử trong phiên làm việc này. Hệ thống chưa có nhật ký câu hỏi thật của người dùng: bảng `groupware_ai_usage` chỉ ghi tên tool, không ghi nội dung. Vì vậy đây là giả thuyết cần kiểm chứng bằng sử dụng thật (xem mục 5).

Cột **AI**: `có` = trả lời được bằng tool hiện có; `một phần` = trả lời được nhưng thiếu hoặc phải đi vòng; `chưa` = chưa có tool.

## 1. Nhân viên (người thực hiện)

| # | Câu hỏi / việc muốn làm | AI | Ghi chú |
|---|---|---|---|
| N1 | Hôm nay / tuần này tôi phải làm gì? | một phần | `show_task_overview` lọc "của tôi" nhưng không lọc theo hạn; chưa có "task tới hạn hôm nay" |
| N2 | Task nào của tôi sắp trễ hoặc đã trễ? | chưa | Cần lọc task theo hạn và sắp theo hạn |
| N3 | Dự án của tôi có những gì, hạn khi nào? | có | `show_projects` với "của tôi", lọc theo khoảng ngày |
| N4 | Dự án này đang ở bước nào, ai phụ trách? | có | `search_projects` với cột trạng thái, manager, thành viên |
| N5 | Tòa nhà / khách hàng của dự án này là ai, liên hệ thế nào? | có | `search_buildings` theo dự án, `get_customer_info` |
| N6 | Có ghi chú hay dặn dò gì cho dự án này? | có | `get_project_notes`, ghi chú tòa nhà trong chi tiết tòa nhà |
| N7 | Ghi lại một ghi chú / nhắc việc cho mình | có | `project_note_add`, `todo_add`, `schedule_add` |
| N8 | Cập nhật tiến độ task, báo đã nhận task | có | `task_progress`, `task_acknowledge` |
| N9 | Đổi trạng thái task (bắt đầu, xong) | một phần | `task_update` chỉ dành cho người có quyền sửa dự án; nhân viên thường không dùng được |
| N10 | Bấm giờ làm task, xem tôi đã làm bao nhiêu giờ tuần này | chưa | Hệ thống có bấm giờ và bảng giờ theo tuần (`task.listWeeklyTasks`) |
| N11 | Tôi đã chấm công chưa, tháng này làm bao nhiêu giờ, còn bao nhiêu ngày phép? | chưa | Chỉ mở được trang chấm công |
| N12 | Đơn xin nghỉ của tôi được duyệt chưa? | một phần | `show_requests` đếm và mở danh sách; chưa đọc được trạng thái từng đơn trong chat |
| N13 | Tạo đơn xin nghỉ / làm thêm giờ | chưa | 13 loại đơn, mỗi loại có trường riêng |
| N14 | Lịch của tôi hôm nay / tuần này có gì? Ai nghỉ hôm nay? | chưa | Chỉ thêm được sự kiện; chưa đọc lịch |
| N15 | Todo của tôi còn gì? | chưa | Chỉ thêm và đánh dấu xong; chưa liệt kê |
| N16 | Tìm bản vẽ / tệp của dự án | một phần | Chỉ mở trang bản vẽ, trang đính kèm |
| N17 | Dịch, viết email cho khách, tóm tắt | có | Không cần tool |
| N18 | Có thông báo mới nào cho tôi? | chưa | — |

## 2. Quản lý dự án

| # | Câu hỏi / việc muốn làm | AI | Ghi chú |
|---|---|---|---|
| Q1 | Dự án nào đang trễ hạn? | có | `show_projects` không có nút "trễ hạn" riêng; `search_projects` có `overdue`. Nên thêm `overdue` vào `show_projects` |
| Q2 | Tuần này / tháng này dự án nào tới hạn (hạn CAILY, hạn GUIS, ngày kết thúc)? | có | `show_projects` với cột ngày và khoảng ngày |
| Q3 | Bộ phận / team đang có bao nhiêu dự án theo từng trạng thái? | có | `get_statistics`; theo team thì dùng `show_projects` |
| Q4 | Ai đang rảnh, team nào nhận thêm việc được? | có | `get_availability` |
| Q5 | Khối lượng việc của từng người: ai đang giữ bao nhiêu task, bao nhiêu giờ? | một phần | `show_task_overview` theo người; chưa có số giờ và so sánh giữa người với người |
| Q6 | Task nào chưa giao cho ai? Task nào chưa được xác nhận nhận việc? | chưa | Trang tổng quan task có tab "chưa giao" |
| Q7 | Dự án nào chưa có ai phụ trách / chưa có team? | chưa | Cần bộ lọc "không có manager / không có team" |
| Q8 | Dự án đang chờ gì (chờ tài liệu, chờ hóa đơn)? | có | Lọc theo trạng thái `waiting_documents`, `waiting_invoice` |
| Q9 | Giao dự án cho team / người, thêm bớt thành viên | có | `propose_actions` |
| Q10 | Đổi trạng thái, đổi hạn, đổi ưu tiên dự án | có | `project_update`, `update_project_status` |
| Q11 | Tạo task và giao cho người | có | `show_task_form` |
| Q12 | Giao lại / đổi hạn nhiều task một lúc | một phần | Tối đa 5 hành động mỗi lần xác nhận |
| Q13 | Tình hình một tòa nhà: các dự án con tới đâu? | có | `get_building_projects` |
| Q14 | Khách hàng X đang có những dự án / tòa nhà nào? | có | `show_projects` theo công ty, `show_buildings` theo từ khóa |
| Q15 | Doanh thu tháng, so với mục tiêu? | có | `show_revenue_statistics` (chỉ giám đốc bộ phận, administrator) |
| Q16 | Dự án nào đã xong mà chưa xuất hóa đơn? Khoản nào chưa thu? | chưa | Dữ liệu có trong thống kê doanh thu và trang quản lý thanh toán |
| Q17 | Hiệu suất team / nhân viên theo tháng | một phần | Chỉ mở trang thống kê nhân viên |
| Q18 | Có đơn nào đang chờ tôi duyệt? | một phần | `show_requests` đếm đơn chờ; chưa lọc "chờ tôi duyệt" |
| Q19 | Hôm nay ai nghỉ, ai đi công tác? | chưa | Dữ liệu có ở trang thành viên online và đơn từ |
| Q20 | Tuần này có gì thay đổi ở dự án này? Ai sửa gì? | chưa | Hệ thống có lịch sử dự án, task, tòa nhà |
| Q21 | Tóm tắt tình hình bộ phận để báo cáo | một phần | Ghép được từ thống kê; chưa có một tool "báo cáo tuần" |

## 3. Câu hỏi nối tiếp và cách hỏi cần xử lý tốt

Đây là hành vi hội thoại, không phải tính năng, nhưng quyết định người dùng có tiếp tục dùng hay không.

| Hành vi | Hiện trạng |
|---|---|
| Hỏi tiếp bằng "cái đó", "dự án này", "tòa nhà đó" | Được: lịch sử 6 tin gần nhất và ngữ cảnh trang đang mở |
| Hỏi trộn tiếng Việt và tiếng Nhật ("đổi sang 見積") | Hoạt động, nhưng Haiku có lúc trả lời bằng tiếng Nhật |
| Gọi theo tên thay vì id ("task 計算書作成", "team A", "anh Thanh") | Được với dự án, task trong dự án đang mở, team, người |
| Ngày tương đối ("tuần sau", "cuối tháng", "thứ Sáu") | Được; ngày viết tắt kiểu 2/3 phụ thuộc ngôn ngữ |
| Yêu cầu gộp nhiều việc một câu | Được tới 5 thao tác mỗi lần xác nhận |
| Hỏi rồi muốn xem danh sách ngay trên trang | Được với dự án, task, tòa nhà, đơn từ, doanh thu |
| Hỏi việc AI không làm được (xóa, duyệt đơn, sửa chấm công) | AI từ chối; nên kèm gợi ý mở đúng trang |
| Hỏi quá rộng ("cho tôi xem tất cả") | AI trả số lượng và mở trang; danh sách trong chat tối đa 20 dòng |

## 4. Khoảng trống xếp theo giá trị ước tính

Xếp theo số người dùng được lợi và mức độ dùng hằng ngày; đây là ước lượng của tôi.

1. **Việc của tôi hôm nay** (N1, N2, N15, N14): task tới hạn và trễ hạn của tôi, todo, lịch hôm nay, gom trong một câu trả lời. Mọi nhân viên dùng mỗi ngày; chỉ đọc.
2. **Dự án và task trễ hạn, chưa giao** (Q1 trên trang, Q6, Q7): quản lý hỏi mỗi ngày; chỉ đọc.
3. **Đơn từ** (N12, Q18, sau đó N13): đọc trạng thái đơn của tôi và đơn chờ tôi duyệt; tạo đơn là thao tác ghi cần thiết kế riêng theo từng loại.
4. **Chấm công và ngày phép của tôi** (N11): chỉ đọc dữ liệu của chính mình.
5. **Ai nghỉ hôm nay** (Q19): chỉ đọc; ảnh hưởng tới việc giao việc.
6. **Giờ làm và khối lượng việc** (N10, Q5): cần đọc bảng giờ theo tuần.
7. **Lịch sử thay đổi** (Q20) và **tiền chưa thu** (Q16): ít người hỏi hơn, có giới hạn quyền.
8. **Đổi trạng thái task cho nhân viên thường** (N9): cần quyết định về quyền, vì hiện AI chỉ cho người có quyền sửa dự án.

## 5. Cách kiểm chứng danh sách này bằng sử dụng thật

- Ghi thêm vào `groupware_ai_usage` hai cột không chứa nội dung: ngôn ngữ câu hỏi và kết quả (trả lời, mở trang, thẻ xác nhận, từ chối, lỗi). Sau hai tuần sẽ biết tool nào được gọi nhiều và tỷ lệ bị từ chối.
- Nếu muốn biết chính xác người dùng hỏi gì mà AI không làm được, cần lưu nội dung câu hỏi. Việc này đụng tới quyền riêng tư nên phải do bạn quyết định và báo cho người dùng.
- Thêm nút đánh giá (hữu ích / không) dưới câu trả lời.

## 6. Khoảng trống số 1 đã làm (2026-11-18)

Tool đọc `get_my_day` (tham số `date`, `days`): task đang mở của người đăng nhập chia theo trễ hạn / tới hạn trong ngày / sắp tới hạn, todo chưa xong của chính họ, và lịch của ngày (họp, sự kiện, người nghỉ). Trả lời ngay trong chat. Nhờ đó các câu N1, N2, N14, N15 và Q19 chuyển sang "có". Test: `tests/ai/my-day.test.php` (14 kiểm tra).

## 7. Khoảng trống số 2 đã làm (2026-11-18)

- `show_projects` nhận `deadline` (`overdue`, `within_7_days`, `within_30_days`): dùng đúng bộ lọc 残り時間 của trang danh sách dự án (so theo ngày kết thúc).
- `show_task_overview` nhận `unassigned_tasks` (task chưa giao cho ai) và `people_without_tasks` (người đang không giữ task mở nào; mở tab tương ứng của trang).
- Tool đọc `get_unstaffed_projects`: số dự án đang chạy chưa có manager và chưa có team, kèm danh sách một trong hai loại. Chỉ cho administrator và project manager.

Các câu Q1, Q6, Q7 chuyển sang "có". Test: `tests/ai/manager-gaps.test.php` (17 kiểm tra).

## 8. Khoảng trống số 3 đã làm (2026-11-18)

Tool đọc `get_requests` (tham số `scope`: `mine` | `to_approve`, `status`, `type`, `limit`): đơn của chính người đăng nhập kèm trạng thái, hoặc đơn đang chờ người đăng nhập duyệt. Đi qua `Request::list()` nên quyền xem do chính trang đơn từ quyết định; chỉ trả loại đơn, trạng thái, người nộp và ngày, không trả nội dung đơn. Các câu N12 và Q18 chuyển sang "có". Tạo đơn (N13) chưa làm. Test: `tests/ai/requests.test.php` (14 kiểm tra).

## 9. Giao diện thanh chat (2026-11-18)

- Nút 新しい会話: có viền, biểu tượng dấu cộng, chú thích khi rê chuột, và sau khi bấm hiện dòng báo "đã bắt đầu cuộc trò chuyện mới, nội dung trước đó không còn gửi cho AI".
- Nút ẩn (») trong hàng nhập: thu thanh chat thành nút tròn nằm bên trái `projectListTourBtn` (trang danh sách dự án) hoặc bên trái `command-palette-toggle` (các trang khác). Lựa chọn lưu trong `localStorage` (`aiChatHidden`) theo từng trình duyệt.

## 10. Khoảng trống số 4, 5, 6, 8 đã làm (2026-11-18)

- **Số 4, chấm công của tôi:** tool đọc `get_my_attendance` (`month`): giờ vào/ra hôm nay, số ngày đã ghi, giờ làm, tăng ca, làm ngày nghỉ của kỳ lương (ngày 21 đến ngày 20), các ngày quên chấm ra, số đơn nghỉ đã duyệt trong năm. Luôn là người đang đăng nhập. Hệ thống không lưu số ngày phép còn lại nên AI không trả lời được câu đó.
- **Số 5, ai nghỉ:** tool đọc `get_absences` (`date`): người nghỉ phép, ra ngoài, đi công tác trong ngày theo đơn đã duyệt, dùng đúng dữ liệu trang thành viên hiển thị cho mọi người. Chỉ trả tên, loại, ngày, giờ; không trả lý do.
- **Số 6, giờ làm và khối lượng việc:** `get_work_hours` (`week_start`, `person_name`): giờ đã bấm trên task trong một tuần; người thường chỉ xem của mình, project manager xem được người khác (đúng quy tắc của trang giờ theo tuần). `get_team_workload` (`department_id`, `team_id`): mỗi người đang giữ bao nhiêu task mở, bao nhiêu task trễ, số giờ trên các task đó, giờ đã bấm tuần này; chỉ administrator và project manager.
- **Số 8, đổi trạng thái task:** thao tác `task_status` qua thẻ xác nhận. Quyền theo đúng trang task (`Task::updateStatus`): người được giao, người tạo task, manager của dự án. `task_update` vẫn chỉ dành cho người quản lý dự án.

Các câu N9, N10, N11, Q5 chuyển sang "có". Tổng cộng 27 tool. Test: `tests/ai/staff-gaps.test.php` (37 kiểm tra).

## 11. Sửa lỗi "質問の範囲が広すぎます" khi nhờ viết email (2026-11-18)

Nguyên nhân: mỗi câu hỏi được tối đa 4 lượt gọi API; nếu ở lượt thứ 4 Claude vẫn muốn tra thêm thì toàn bộ dữ liệu đã tra bị bỏ và trả về thông báo lỗi. Sửa: sau 4 lượt tra cứu có thêm một lượt cuối cấm gọi tool (`tool_choice: none`), buộc Claude trả lời bằng dữ liệu đã có. Prompt cũng dặn tra ít lượt nhất khi viết văn bản về dự án đang mở.

## 12. Chặn câu hỏi về doanh thu và số tiền (2026-11-18)

Theo yêu cầu, trợ lý không xử lý doanh thu và số tiền nữa. Ba lớp:

1. **Chặn ở máy chủ trước khi gọi AI** (`AI::asksAboutMoney`, trong `checkLimits`): câu có từ khóa doanh thu / số tiền (売上, 金額, 請求額, 利益, 単価, いくら, doanh thu, số tiền, đơn giá, tổng tiền, revenue, price, amount …) nhận ngay câu trả lời cố định, không tốn token, không tính vào giới hạn. Chặn theo từ khóa nên câu chỉ nhắc tới số tiền (ví dụ nhờ ghi chú "金額を確認") cũng bị chặn.
2. **Không còn dữ liệu tiền:** bỏ tool `show_revenue_statistics` và `AI::revenueStatsForAi`, bỏ cột `amount` khỏi kết quả tìm dự án, bỏ tham số `amount` khỏi `project_create` / `project_update`, bỏ trang doanh thu khỏi danh sách URL được phép mở. Còn 26 tool.
3. **Prompt:** "Money is out of scope" cho các cách hỏi không trúng từ khóa.

Ghi chú tự do của dự án vẫn có thể chứa con số do người dùng tự viết. Các câu Q15, Q16 chuyển thành "không hỗ trợ". Test: `tests/ai/money-block.test.php` (31 kiểm tra).

## 13. Sửa lỗi: focus vào khung chat không hiện lại tin cũ (2026-11-18)

Nguyên nhân: khung chat hiển thị lại đúng phần hội thoại dùng làm ngữ cảnh cho AI (`$_SESSION['ai_chat']`: 6 tin, mỗi tin 1500 ký tự, hết hạn sau 30 phút không dùng), và các câu trả lời cố định (câu bị chặn về tiền, lỗi kết nối, quá giới hạn) không được lưu. Vì vậy tin cũ hơn 3 lượt hỏi đáp, hội thoại cách quá 30 phút, và câu bị chặn đều không hiện lại sau khi chuyển trang.

Sửa: thêm nhật ký hiển thị riêng `$_SESSION['ai_chat_log']` (30 tin gần nhất, mỗi tin 6000 ký tự, không hết hạn theo thời gian, xóa khi bấm 新しい会話 hoặc đăng xuất). `get_chat_history` và cờ `data-has-history` dùng nhật ký này. Phần gửi cho AI không đổi nên không tốn thêm token. Test: `tests/ai/chat-log.test.php` (11 kiểm tra).

## 14. Bộ phận mặc định cho câu hỏi về dự án và task (2026-11-18)

- Thanh chat có ô chọn bộ phận (`#ai-chat-dept`, hiện khi thanh đang dùng). Danh sách lấy từ `/ai/index.php?method=get_departments` (các bộ phận của người dùng; administrator thấy tất cả). Lựa chọn lưu trong `localStorage` (`aiChatDept`) theo từng trình duyệt; chọn "すべての部署" để bỏ giới hạn.
- Mỗi câu hỏi gửi kèm `context.department_id`. Máy chủ kiểm tra lại (`AI::chosenDepartment`): chỉ nhận bộ phận mà người dùng thuộc về.
- Khi có bộ phận mặc định, máy chủ ép `department_id` cho các tool liệt kê/đếm dự án và task (`AiTools::DEPARTMENT_TOOLS`: `search_projects`, `get_statistics`, `get_availability`, `get_unstaffed_projects`, `get_team_workload`, `show_projects`, `show_task_overview`), bất kể Claude truyền gì. Ngoại lệ: tra một dự án theo id ("dự án này" trên trang chi tiết) vẫn tìm được dù thuộc bộ phận khác.
- Không áp dụng cho dữ liệu cá nhân (`get_my_day`, chấm công, đơn từ) và tòa nhà (không gắn với bộ phận).
- Prompt: Claude nói rõ số liệu của bộ phận nào và hướng dẫn đổi bộ phận bằng ô chọn khi được hỏi về bộ phận khác.

Test: `tests/ai/department.test.php` (18 kiểm tra), thêm 5 kiểm tra trong `tests/ai/render.test.js`.

## 15. Sửa lỗi "AIアシスタントが設定されていません" trên Apache (2026-11-18)

Nguyên nhân: `config.php` nạp `.env` bằng `putenv()`. Giá trị `putenv()` thuộc về cả tiến trình máy chủ và PHP gỡ chúng ra khi một yêu cầu kết thúc. Apache trên Windows chạy nhiều luồng trong một tiến trình, nên khi một yêu cầu khác (các lệnh gọi nền của trang) kết thúc, `getenv()` của yêu cầu chat đang chạy trả về rỗng. Đã đo trên localhost: 75/75 lần đọc bị rỗng khi có 60 yêu cầu song song, 0 lần khi chạy một mình. Máy chủ xem thử (một luồng) và CLI không gặp.

Sửa: `config.php` giữ thêm một bản sao các giá trị `.env` trong phạm vi yêu cầu và cung cấp `app_env($name, $default)`; `ClaudeClient` và giới hạn tần suất của AI đọc qua hàm này. Các chỗ khác của hệ thống vẫn dùng `getenv()` trực tiếp (`CAILY_FILE_PROTECT_KEY`, `CAILY_API_URL`, cấu hình mail trong `request.php`, `APP_ENV`) và có cùng rủi ro; chưa sửa. Test: `tests/ai/env.test.php` (9 kiểm tra).

## 16. Ai nghỉ phép trong tháng, mỗi người mấy ngày (2026-11-18)

`get_absences` nhận thêm `month` (YYYY-MM) hoặc `date_from` + `date_to` (tối đa 62 ngày): trả mỗi người một dòng gồm số ngày nghỉ, số đơn, các ngày nghỉ (kèm loại như 午後休), cùng tổng số người và tổng số ngày. Chỉ tính đơn đã duyệt, đọc qua đúng phương thức trang thành viên dùng cho mọi người; số ngày lấy từ trường `days` của đơn (0,5 = nửa ngày); lý do nghỉ không được trả về. Test: `tests/ai/leave-period.test.php` (14 kiểm tra).
