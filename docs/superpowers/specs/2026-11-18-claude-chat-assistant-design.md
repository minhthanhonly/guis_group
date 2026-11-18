# Trợ lý AI dùng Claude API + khung chat đáy màn hình — thiết kế

Ngày: 2026-11-18. Nhánh gốc: `pdfshare` (df20443).

## 1. Hiện trạng

- `group/caily/application/model/ai.php` (3475 dòng) gọi Gemini bằng cURL, tắt kiểm tra TLS (`CURLOPT_SSL_VERIFYPEER=false`).
- Mỗi tin nhắn gọi Gemini 2 lượt: lượt 1 phân loại ý định (`classifyIntentAndDataRequest`), lượt 2 trả lời (`sendToGemini`). Cả hai lượt đều gửi system prompt rất dài (toàn bộ quy tắc ACTION, quy tắc bảng, quy tắc ngày…).
- Hành động ghi được AI viết ra dưới dạng văn bản `ACTION:{...}`, server tách bằng regex (`parseAndStripActionFromResponse`), giao diện hiện nút xác nhận, rồi gọi `execute_action`. `executeAction()` nhận thẳng JSON hành động từ trình duyệt.
- Quyền sửa được kiểm tra trong model (`Project::update` có "Phase 4.1 – Permission check"); quyền xem nằm trong `getForAiContext` / `getStatsForAiContext`.
- Câu trả lời của AI là HTML và được gán vào `innerHTML` (`assets/js/app-chat.js`).
- Khung chat cũ (`application/view/chat.php`) không được layout nào hiển thị; `app-chat.js` vẫn được tải ở mọi trang trừ login và danh sách dự án.

## 2. Mục tiêu

1. Thay Gemini bằng Claude API.
2. Thanh chat cố định ở đáy màn hình (ô nhập + nút biểu tượng Enter); gõ rồi Enter thì khung hội thoại mở rộng lên có hiệu ứng.
3. AI chỉ làm được những việc trong danh mục dưới đây, chỉ qua các hàm model/API sẵn có, không vượt quyền của người đang đăng nhập.
4. Ít token nhất có thể mà kết quả vẫn đúng.

## 3. Danh mục chức năng AI

### Mức 0 — Chỉ trả lời, không cần dữ liệu hệ thống
Soạn/sửa email và tin nhắn, dịch Nhật ↔ Việt, tóm tắt đoạn văn người dùng dán vào, giải thích cách dùng các màn hình của hệ thống.

### Mức 1 — Đọc dữ liệu (tự chạy, lọc theo quyền xem của người dùng)

| Tool | Tham số | Nguồn dữ liệu sẵn có |
|---|---|---|
| `search_projects` | `project_id`, `keyword`, `construction_number`, `company_name`, `team_name`, `status`, `department_id`, `date_field`, `date_from`, `date_to`, `limit` (≤20) | `Project::getForAiContext` |
| `search_buildings` | `keyword`, `project_number` | `ParentProject::getForAiContext` |
| `get_project_tasks` | `project_id`, `status` | `Task::getForAiContext` |
| `get_statistics` | `department_id`, `status` | `Project/Task::getStatsForAiContext` (số đã cộng sẵn ở server) |
| `get_availability` | `date_from`, `date_to`, `department_id` | phần `availability` của `AI::fetchContextByKeys` |
| `get_customer_info` | `project_id` hoặc `project_number` | phần `customer` của `AI::fetchContextByKeys` (đúng 12 trường như hiện nay) |
| `list_departments_teams` | — | bộ phận của người dùng + team trong bộ phận |

### Mức 2 — Điều hướng (chạy ở trình duyệt, không đổi dữ liệu)
`open_page` với `project_id` + `page` ∈ `detail | task | gantt | drawings | attachment`. Bỏ tham số `url` tự do của hành động `script` cũ: URL chỉ được ghép từ danh sách cố định.

### Mức 3 — Ghi dữ liệu (không bao giờ tự chạy; người dùng bấm 確認 mới thực hiện)
Một tool `propose_actions` với `type` thuộc danh sách trắng, đúng các loại `executeAction()` đang hỗ trợ:

- Tòa nhà: `parent_project_create`, `parent_project_update` (không cho đổi `project_number`).
- Dự án: `project_create`, `project_update`, `update_project_status`, `update_project_order_type`.
- Task: `task_update`; tạo task đi qua form (`show_task_form`) như hiện nay.
- Nhân sự dự án: `project_add_manager`, `project_add_member`, `project_add_team_members`, `project_remove_manager`, `project_remove_member`.
- Team dự án: `project_set_teams`, `project_add_team`, `project_remove_team`.
- Xóa hàng loạt (thẻ xác nhận có cảnh báo đỏ): `project_clear_members`, `project_clear_managers`, `project_clear_all_members`, `project_clear_teams`, `project_clear_all`.

### Mức 4 — Cấm (không có tool, AI không thể làm dù được yêu cầu)
Xóa dự án / tòa nhà / task / khách hàng; quản lý người dùng, mật khẩu, phân quyền, bộ phận; chấm công, nghỉ phép, lương; cấu hình hệ thống; đọc tin nhắn hay tệp của người khác; chạy SQL, đọc/ghi tệp trên server, gọi URL bên ngoài; mọi thao tác thay mặt người dùng khác.

### Tùy chọn (chưa làm trong đợt này, chờ quyết định)
Tool đọc dữ liệu của chính người dùng: `get_my_tasks`, `get_my_schedule`, `get_my_todos`, `get_my_timecard`.

## 4. Các lớp bảo vệ

1. **Phiên đăng nhập:** `/ai/index.php` đi qua `Controller::dispatch()` (Authority + `checkSuspend`) như hiện nay.
2. **Danh tính lấy từ `$_SESSION`:** không tool nào nhận tham số "làm thay ai"; mọi truy vấn chạy với người đang đăng nhập.
3. **Không có đường tắt:** tool chỉ gọi hàm model sẵn có. Không tool nào nhận SQL, đường dẫn tệp hay URL.
4. **Danh sách trắng ở server:** tên tool, `type` của hành động và tên từng tham số đều được kiểm tra bằng code (`AiTools::validateActions`); tham số lạ bị loại bỏ.
5. **Kiểm tra quyền hai lần:** trước khi đưa ra thẻ xác nhận (administrator / project_manager của bộ phận / manager của dự án đó; không đủ quyền thì AI hướng dẫn nhờ người có quyền), và lần nữa trong model khi thực thi.
6. **Mã xác nhận một lần:** hành động chờ được lưu trong `$_SESSION['ai_pending'][token]` (hết hạn 10 phút). `execute_action` chỉ nhận `{token}`; không còn nhận JSON hành động từ trình duyệt. Token dùng xong bị xóa.
7. **Chống chèn lệnh (prompt injection):** dữ liệu từ DB chỉ đi vào `tool_result`; system prompt ghi rõ nội dung đó là dữ liệu, không phải chỉ thị. Vì mọi thao tác ghi cần người bấm xác nhận nên dữ liệu độc hại không tự gây thay đổi được.
8. **Hiển thị an toàn:** AI trả Markdown; trình duyệt escape toàn bộ rồi mới dựng in đậm / danh sách / bảng / liên kết. Liên kết chỉ được bắt đầu bằng `/`.
9. **Khóa API:** `ANTHROPIC_API_KEY` chỉ nằm trong `group/.env` (đã có trong `.gitignore`), không bao giờ gửi về trình duyệt; bỏ nhánh `$_SESSION['api_key']`. SDK kiểm tra TLS đầy đủ.
10. **Giới hạn:** tin nhắn ≤ 2000 ký tự; 10 tin/phút và 200 tin/ngày mỗi người (đổi được trong `.env`); tối đa 4 lần gọi Claude cho một tin nhắn; `maxTokens` 8000.
11. **Nhật ký:** bảng `groupware_ai_usage` ghi người dùng, số token vào/ra/cache, tên tool đã gọi, thời điểm. Không ghi nội dung hội thoại.

## 5. Tiết kiệm token

| Biện pháp | Thay cho |
|---|---|
| Một vòng tool-use: Claude tự gọi đúng tool cần | 2 lượt Gemini, mỗi lượt gửi lại prompt dài |
| System prompt tĩnh ngắn (vai trò, ngôn ngữ, quy tắc dữ liệu); quy tắc nghiệp vụ chuyển vào schema tool và code server | prompt hàng nghìn token mô tả ACTION, bảng, ngày |
| `cacheControl` trên khối system tĩnh → cache cả tools + system, dùng chung cho mọi người dùng | Gemini context cache theo từng phiên |
| Phần thay đổi (ngày giờ server, tên, quyền, trang đang mở) nằm ở khối system thứ hai, sau điểm cache | ngày giờ trộn vào prompt |
| Kết quả tool gọn: chỉ cột cần, mặc định 10 dòng (tối đa 20), chuỗi cắt 200 ký tự, thống kê cộng sẵn | nhét cả danh sách vào prompt |
| Lịch sử: 6 tin gần nhất, chỉ văn bản, mỗi tin cắt 1500 ký tự; không lưu `tool_result` | bộ từ khóa đoán "có cần lịch sử không" |
| Model nhỏ `claude-haiku-5-5`, effort `medium`, đều đặt trong `.env` | — |

Sau khi chạy phải xác nhận `usage.cacheReadInputTokens > 0` ở tin nhắn thứ hai. Nếu tiền tố tools + system ngắn hơn ngưỡng cache tối thiểu của model thì cache không có tác dụng; khi đó giữ nguyên, không độn thêm chữ.

Model đặt qua `CLAUDE_MODEL`, mặc định `claude-haiku-5-5`: phần quyết định tính đúng (quyền, danh sách trắng, số liệu thống kê, xác nhận) nằm ở code server, model chỉ chọn tool và diễn đạt. Nếu bộ câu thử ở Task 8 của plan cho kết quả sai (chọn nhầm tool, hiểu sai ngày, sai ngôn ngữ) thì nâng lên `claude-sonnet-5-5` bằng cách đổi `.env`, không sửa code.

## 6. Kiến trúc server

- `application/library/claudeclient.php` — lớp `ClaudeClient`, nơi duy nhất gọi SDK `anthropic-ai/sdk`. Test thay bằng bản giả nên không tốn tiền.
- `application/model/aitools.php` — lớp `AiTools`: định nghĩa tool, chạy tool đọc, kiểm tra hành động ghi.
- `application/model/ai.php` — `chat()` thành vòng lặp tool-use; `executeAction()` giữ nguyên phần `switch`, thêm cổng token; xóa code riêng của Gemini.
- Gọi `session_write_close()` trước khi gọi Claude và mở lại phiên để lưu lịch sử, để các request khác của cùng người dùng không bị treo trong lúc chờ AI.

Hợp đồng `POST /ai/index.php?method=chat`, body `{message, context: {page, project_id}}`:

```json
{
  "reply": "markdown",
  "pending": {"token": "…", "summary": "…", "danger": false, "actions": [{"type": "…", "label": "…"}]},
  "navigate": {"url": "/project/task.php?project_id=23"},
  "task_form": {"project_id": 23, "initial": {}},
  "error": null
}
```

`pending`, `navigate`, `task_form` là `null` khi không có. `POST ?method=execute_action` nhận `{token}`. `GET ?method=get_chat_history` trả `{history: [{role, text}]}`.

## 7. Giao diện

- Thanh cố định giữa đáy màn hình: rộng `min(720px, 100vw - 32px)`, cách đáy 16px; ô nhập một dòng tự giãn tới 4 dòng; nút gửi dùng biểu tượng Enter (`tabler-corner-down-left`).
- Enter gửi, Shift+Enter xuống dòng. Không gửi khi đang gõ IME (`event.isComposing`) — bắt buộc vì người dùng gõ tiếng Nhật.
- Lần gửi đầu tiên: khung hội thoại mở lên phía trên thanh, cao tới `min(60vh, 560px)`, chuyển động 280ms (`height` + `opacity`); tôn trọng `prefers-reduced-motion`.
- Thu gọn bằng Esc, nút đóng hoặc bấm ra ngoài; hội thoại vẫn giữ, bấm vào thanh thì mở lại.
- Lịch sử chỉ tải khi mở khung lần đầu, không tải lúc mở trang.
- Thẻ xác nhận: tóm tắt + danh sách hành động + 確認 / キャンセル; loại xóa hàng loạt có viền đỏ.
- Hiển thị ở mọi trang đã đăng nhập, kể cả danh sách dự án (file JS/CSS riêng, nhỏ). `app-chat.js` cũ thôi không được tải; các tệp cũ chưa xóa.
- Nhãn tiếng Nhật kèm `data-i18n` như code hiện có.

## 8. Ngoài phạm vi

Streaming từng chữ; chat giữa người với người; nhập giọng nói; lưu hội thoại vào DB; các tool "của tôi" ở mục Tùy chọn; phân quyền đọc theo bộ phận, CSRF toàn hệ thống và khóa của `api/*_public.php` (đã được quyết định bỏ qua ngày 2026-11-12).

## 9. Câu hỏi cần người dùng quyết định

1. **Cách gọi Claude:** SDK chính thức qua Composer (cần PHP ≥ 8.1 trên server thật và chạy `composer install` khi deploy) hay cURL thuần như code Gemini hiện tại? Thiết kế này giả định SDK.
2. **Model:** đã chọn `claude-haiku-5-5` (2026-11-18), nâng lên `claude-sonnet-5-5` nếu bộ câu thử không đạt.
3. **Danh mục Mức 3:** có giữ nhóm "xóa hàng loạt" không? Có thêm các tool "của tôi" không?

## 10. Thay đổi khi triển khai (2026-11-18)

- Model mặc định `claude-haiku-5-5`, effort `medium`. Biến môi trường nằm trong `group/caily/.env` (mẫu: `.env.demo`).
- Thư viện: `anthropic-ai/sdk` + `guzzlehttp/guzzle`, `composer.json` ghim `platform.php = 8.1.0`. `vendor/` tiếp tục được git theo dõi.
- Lịch sử hội thoại: khóa phiên `ai_chat`, hết hạn sau 30 phút không dùng, endpoint `clear_chat` (nút 新しい会話). Bật `cacheControl` ở cấp request.
- `search_projects`: tham số theo các bộ lọc sẵn có của `Project::getForAiContext` (`project_ids`, `project_name`, `person_name`, `overdue`, `tantou`, khoảng ngày), thêm `fields` để chỉ lấy cột khi cần.
- `search_buildings`: chỉ tra theo id hoặc mã tòa nhà (`ParentProject::getForAiContext` hỏng, đọc qua `AI::getBuildingsForAi`).
- Quyền ghi kiểm tra tại một chỗ (`AI::canProposeWrite` + `AiTools::actionProjectId`) cho mọi loại hành động; tạo dự án/tòa nhà và sửa tòa nhà theo id cần administrator hoặc project manager của bộ phận.
- `task_update`: dự án lấy từ chính task trong DB (`AI::normalizeAction`), không nhận `project_id` từ model.
- `status` phải thuộc danh sách của dự án / task / tòa nhà; `deleted` bị từ chối.
- Người dùng có `show_project = 0` không thấy thanh chat và không dùng được tool.
- Liên kết trong câu trả lời chỉ được trỏ tới các trang: chi tiết / task / gantt / bản vẽ / đính kèm của dự án và chi tiết tòa nhà.
- Lớp `AI` và `AiTools` không gọi được qua `/api/index.php`.
- Dòng usage được ghi trước khi gọi Claude (giới hạn tần suất tính cả request song song). Timeout HTTP 60 giây đặt trên Guzzle.
- Test: `group/caily/tests/ai/` (5 file PHP + 1 file JS), không gọi API thật.

## 11. Kết quả hiển thị trên trang của hệ thống (2026-11-18, theo yêu cầu bổ sung)

- Tool `show_projects`: mở trang danh sách dự án với bộ lọc điền sẵn (bộ phận, trạng thái, từ khóa, team, công ty, người phụ trách, 担当, ưu tiên, khoảng ngày, "dự án của tôi", gồm cả dự án đã xong). Tên team và tên người được server đổi sang id. Khung chat chỉ nhận số lượng: tổng số, và số theo từng bộ phận khi không chỉ định bộ phận (khi đó mở bộ phận có nhiều kết quả nhất).
- Số lượng lấy từ chính `Project::list()` mà trang dùng, nên luôn khớp với số dòng trên trang.
- Tool `show_tasks`: mở trang task của một dự án; khung chat nhận số task theo trạng thái.
- URL có dạng `/project/index.php?ai=1&department_id=…&status=…&filterKeyword=…` và luôn gửi đủ mọi khóa bộ lọc (rỗng nếu không dùng) để ghi đè bộ lọc trang đang nhớ. `project-list.js` chỉ áp dụng khi có `ai=1` (`applyAssistantFiltersFromUrl`), giữ nguyên bộ lọc riêng của các bộ phận khác, rồi xóa `ai=1` khỏi địa chỉ.
- Sau khi trang tải lại, câu trả lời vừa rồi hiện phía trên ô nhập; mở khung thì toàn bộ hội thoại được khôi phục từ server.
- Mỗi câu trả lời chỉ mở một trang (tool mở trang đầu tiên thắng). Lịch sử ghi kèm tên tool đã tạo ra câu trả lời (`[tools: …]`, không hiển thị cho người dùng) để lượt sau AI không tra lại.
- `search_projects` và `get_project_tasks` vẫn còn, chỉ để AI lấy một giá trị cụ thể hoặc xác định bản ghi trước khi đề xuất thay đổi.
- Sửa kèm: `Project::update` cần số phiên bản (ghi nhận lúc đề xuất, gửi lúc thực thi); task cấp cao nhất lưu `parent_id = 0`; danh sách trạng thái dự án (13) và task (6, `in-progress`) lấy theo màn hình thật; thư viện `composer/ca-bundle` cho PHP không có kho chứng chỉ gốc.
- Khối ngữ cảnh theo từng request (không cache) có thêm id số và userid của người đang đăng nhập: `User: <tên> (user id N, login <userid>). Role: …`. Hai giá trị được lọc ký tự trước khi đưa vào. "Của tôi / 私の" → `show_projects` với `my_projects`; tham số nhận id người dùng (`assigned_to`) dùng id số này.
