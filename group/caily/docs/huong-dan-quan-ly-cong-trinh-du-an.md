# Hướng dẫn sử dụng — Quản lý công trình, dự án, công việc và giờ công

Tài liệu này dành cho người dùng hàng ngày.  
Trên màn hình, các nút và tiêu đề thường hiện **tiếng Nhật**. Trong hướng dẫn, tên trên màn hình được viết trong dấu 「 」 để bạn dễ tìm đúng chỗ bấm.

---

## Mục lục

1. [Hệ thống này dùng để làm gì?](#1-hệ-thống-này-dùng-để-làm-gì)
2. [Các khái niệm cần biết (không dùng từ kỹ thuật)](#2-các-khái-niệm-cần-biết)
3. [Cách mở các màn hình chính](#3-cách-mở-các-màn-hình-chính)
4. [Quản lý công trình (建物)](#4-quản-lý-công-trình-建物)
5. [Quản lý dự án / yêu cầu công việc (案件)](#5-quản-lý-dự-án--yêu-cầu-công-việc-案件)
6. [Công việc trong dự án (タスク)](#6-công-việc-trong-dự-án-タスク)
7. [Bấm giờ công (作業計測)](#7-bấm-giờ-công-作業計測)
8. [Bản vẽ, tệp đính kèm, lịch trình](#8-bản-vẽ-tệp-đính-kèm-lịch-trình)
9. [Báo giá và thanh toán (決済情報)](#9-báo-giá-và-thanh-toán-決済情報)
10. [Ghi chú, trao đổi, yêu thích](#10-ghi-chú-trao-đổi-yêu-thích)
11. [Mẹo dùng hàng ngày](#11-mẹo-dùng-hàng-ngày)
12. [Bảng tra cứu nhanh tên trên màn hình](#12-bảng-tra-cứu-nhanh-tên-trên-màn-hình)

---

## 1. Hệ thống này dùng để làm gì?

Hệ thống giúp cả nhóm theo dõi một công trình từ lúc nhận yêu cầu đến khi hoàn thành, cụ thể:

| Việc cần làm | Lợi ích |
|---|---|
| Ghi lại thông tin tòa nhà / chủ đầu tư | Không bị sót thông tin khách, cùng một nguồn dữ liệu cho mọi người |
| Tách từng phần việc theo phòng ban (thiết kế, thiết bị, tiết kiệm năng lượng…) | Mỗi phòng biết mình phụ trách phần nào |
| Chia nhỏ thành công việc cụ thể và người làm | Dễ giao việc, biết ai đang làm gì |
| Bấm giờ khi làm việc | Biết tổng giờ công theo loại việc / dự án |
| Theo dõi báo giá, hóa đơn | Biết đã ra báo giá / đã xuất hóa đơn chưa |
| Lưu file, bản vẽ, ghi chú | Tài liệu và trao đổi nằm cùng chỗ với công việc |

---

## 2. Các khái niệm cần biết

Hãy hình dung như một **tòa nhà** chứa nhiều **hạng mục công việc**, mỗi hạng mục lại có nhiều **việc nhỏ**.

| Tên trên hệ thống | Nghĩa dễ hiểu | Ví dụ |
|---|---|---|
| **建物** (tòa nhà / công trình) | Thông tin chung của một công trình và chủ đầu tư | Một ngôi nhà / dự án xây dựng của khách A |
| **案件** (dự án / yêu cầu) | Một phần việc thuộc công trình, thường gắn một phòng ban | Phần thiết kế ý tưởng (意匠), phần thiết bị (設備)… |
| **案件依頼** | Cách gọi khi tạo dự án con từ màn hình công trình | “Tạo yêu cầu việc cho phòng thiết bị” |
| **タスク** | Việc nhỏ trong một dự án | “Vẽ mặt bằng”, “Kiểm tra”, “Sửa theo góp ý” |
| **工数** | Tổng thời gian đã ghi nhận cho việc đó | 3 giờ 30 phút |
| **作業計測** | Đang bấm giờ (đồng hồ đang chạy) | Giống bấm giờ làm việc trên một việc cụ thể |

**Quan hệ:**  
`Công trình (建物)` → có nhiều `Dự án (案件)` → mỗi dự án có nhiều `Công việc (タスク)` → mỗi công việc có thể `bấm giờ`.

---

## 3. Cách mở các màn hình chính

Trên menu bên trái, mở nhóm 「**プロジェクト**」:

| Mục menu | Dùng khi nào |
|---|---|
| 「**建物一覧**」 | Xem / tìm / tạo công trình |
| 「**案件一覧**」 | Xem toàn bộ dự án theo phòng ban, lọc, xuất Excel |
| 「**タスク一覧**」 | Xem công việc trên nhiều dự án, theo dõi giờ công |
| 「**案件ガントチャート**」 | Nhìn lịch trình nhiều dự án trên một dòng thời gian |

**Tiện ích luôn có sẵn:**

- Nút bàn phím (F1 hoặc Ctrl+K): tìm nhanh công trình / dự án / khách hàng  
- Nút danh sách (F3): 「**Todoリスト**」 — xem **việc được giao cho tôi** và **việc cá nhân**  
- Góc dưới phải: nếu đang bấm giờ sẽ hiện 「**作業計測中**」 và nút dừng  

---

## 4. Quản lý công trình (建物)

### 4.1 Danh sách công trình 「建物一覧」

**Bạn có thể:**

- Bấm tên 「**お施主様名**」 để mở chi tiết công trình  
- Bấm 「**建物登録**」 để tạo công trình mới (nếu được cấp quyền)  
- Gõ vào ô tìm kiếm 「**検索...**」  
- Lọc theo loại yêu cầu 「**依頼**」: すべて / 意匠 / 設備 / 3D設備 / 省エネ / その他  
- Đánh dấu sao 「**お気に入り**」 để lọc những công trình hay mở  
- Bật/tắt cột bằng 「**列の表示**」  
- Xem số dự án con ở cột 「**件数**」  
- Xem / sửa ghi chú ngay trên danh sách (cột 「**メモ**」)  
- Chuột phải vào dòng: sửa nhanh hoặc mở trang chi tiết  

**Lợi ích:** Tìm đúng công trình nhanh, không phải nhớ mã số; biết công trình nào đang có bao nhiêu phần việc.

### 4.2 Đăng ký công trình mới 「建物登録」

Điền các thông tin chính (dấu * là bắt buộc trên màn hình):

| Trường | Ý nghĩa |
|---|---|
| 「会社名」「支店名」「担当様」 | Công ty / chi nhánh / người liên hệ phía khách |
| 「GUIS　受付者」 | Người tiếp nhận phía GUIS |
| 「依頼日」 | Ngày nhận yêu cầu (có nút lấy giờ hiện tại) |
| 「管理番号」 | Mã quản lý nội bộ (có nút 「生成」 để tạo tự động) |
| 「工事番号」 | Mã công trình (nếu có) |
| 「お施主様名」 | Tên chủ đầu tư / tên gọi công trình |
| 「建物規模」「種類1」「種類2」 | Quy mô, loại hình |
| 「依頼」 | Loại phần việc cần làm: 意匠, 設備, 3D設備, 省エネ… |
| 「資料」 | Tài liệu đã nhận: mặt bằng bố trí, hồ sơ thẩm định… |

Có thể thêm khách hàng mới hoặc mở 「**顧客情報**」 để xem/sửa thông tin khách.  
**Lưu ý:** Sửa thông tin khách có thể ảnh hưởng tới **mọi công trình** đang dùng cùng khách đó — hệ thống sẽ cảnh báo trước.

Cuối cùng bấm 「**保存**」 để lưu, hoặc 「**キャンセル**」 / 「**戻る**」 để hủy / quay lại.

### 4.3 Chi tiết công trình 「建物詳細」

**Những gì bạn làm được ở đây:**

1. **Xem / sửa thông tin chung** của công trình (nếu có quyền: 「編集」 → sửa → 「保存」)  
2. **Thêm ghi chú** 「メモを追加」 — có thể đánh dấu 「重要メモ」  
3. **Tạo dự án con** 「案件依頼作成」 — đây là bước quan trọng nhất để giao việc cho từng phòng  
4. **Xem bảng dự án con** kèm trạng thái, tiến độ, ngày hạn, thông tin thanh toán  
5. **Xem thống kê giờ công theo phòng / loại việc** 「部署別種別工数」  
6. **Quản lý báo giá** 「見積書」 (tạo mới, xem lịch sử)  
7. **Quản lý tệp đính kèm** tab 「添付ファイル」  

**Lợi ích:** Một màn hình “trung tâm” của công trình — từ đây tạo phần việc, đính kèm tài liệu, theo dõi báo giá.

### 4.4 Tạo dự án con từ công trình 「案件依頼を作成」

Khi bấm 「案件依頼作成」, điền ví dụ:

- 「案件名」: tên phần việc  
- 「部署」: phòng phụ trách  
- Có thể chọn **dùng khách hàng giống công trình** hoặc chọn khách khác  
- 「予定工程」: kế hoạch theo tháng / giai đoạn  
- 「開始日」「期限日(実納期)」: ngày bắt đầu / ngày giao thực tế  
- 「ステータス」「受注形態」: trạng thái và hình thức nhận việc  
- Team, người quản lý, thành viên (nếu form có)  

**Lợi ích:** Không phải tạo dự án “trôi nổi” — mọi phần việc đều gắn đúng công trình và phòng ban.

---

## 5. Quản lý dự án / yêu cầu công việc (案件)

### 5.1 Danh sách dự án 「案件一覧」

Đây là màn hình làm việc hàng ngày của nhiều người.

**Chuẩn bị trước khi lọc:**

1. Chọn 「**部署**」 (phòng ban) trên thanh điều hướng  
2. Dùng các nút trạng thái: 受付 / 納期検討 / 仮受 / 見積 / 請負 / 資料待ち / 進行中 / 完了 / 一時停止 / 中止  
3. Bật 「**高度なフィルター**」 nếu cần lọc sâu hơn (tháng bắt đầu, tình trạng báo giá/hóa đơn, mức ưu tiên, còn bao nhiêu ngày đến hạn, team, từ khóa…)  
4. Công tắc hữu ích:  
   - 「**私の案件**」: chỉ việc liên quan đến tôi  
   - 「**完了・中止案件等も表示**」: hiện cả việc đã xong / đã hủy  

**Thao tác thường dùng:**

| Nút / chức năng | Việc làm |
|---|---|
| Sao 「お気に入り」 | Đánh dấu dự án hay mở |
| 「並べ替え」 | Đổi cách sắp xếp (theo hạn, theo kế hoạch…) |
| 「列の表示」 | Chỉ hiện các cột bạn cần |
| 「Excel出力」 | Xuất bảng ra file Excel |
| 「案件を編集」 | Sửa nhanh vài trường mà không cần vào trang chi tiết |
| Nút vàng 「UIガイド」 | Tour giải thích các nút trên danh sách |

**Lợi ích:** Nhìn được bức tranh toàn phòng: việc nào sắp đến hạn, việc nào chưa báo giá, việc nào đang tạm dừng.

### 5.2 Chi tiết dự án 「案件詳細」

Các tab phía trên:

| Tab | Nội dung |
|---|---|
| 「**概要**」 | Thông tin dự án, thanh toán, ghi chú, bình luận, giờ công theo loại |
| 「**タスク**」 | Danh sách công việc nhỏ + bấm giờ |
| 「**ガントチャート**」 | Lịch trình các công việc trên trục thời gian |
| 「**図面**」 | Quản lý bản vẽ |
| 「**添付ファイル**」 | File đính kèm của dự án |

**Trên tab tổng quan bạn có thể:**

- Xem thông tin công trình cha (readonly) và liên kết sang 「建物詳細」  
- Sửa thông tin dự án (「編集」 → 「保存」) nếu được phép  
- Đổi 「ステータス」, 「進捗率」, ngày bắt đầu / hạn, 「予定工程」  
- Ghi 「CAILY納期」「GUIS納期」 và đánh dấu 「納品済み」 khi đã giao  
- Thêm thành viên / team / người quản lý; hoặc bấm 「案件に参加」 để tự tham gia  
- Sao chép thông tin dự án, sao chép cả dự án (nếu có nút)  
- Trao đổi trong 「コメント」  

**Luồng trạng thái dự án (thường gặp):**  
受付 → 納期検討 → 仮受 → 見積 → 請負 → 資料待ち → 進行中 → 完了  
Nhánh phụ: 一時停止 (tạm dừng), 中止 (hủy)

---

## 6. Công việc trong dự án (タスク)

Mở dự án → tab 「**タスク**」.

### 6.1 Bạn làm được gì?

- Xem nhanh: tổng số việc, tổng giờ công, số việc hoàn thành, việc quá hạn  
- Lọc theo trạng thái; bật 「自分のタスクのみ」 để chỉ thấy việc của mình  
- 「**新規タスク**」: tạo việc mới  
- 「**既定タスク追加**」: thêm bộ việc mẫu (nếu phòng đã thiết lập)  
- Sửa trực tiếp trên bảng: tên việc, mức ưu tiên, loại việc, trạng thái, hạn, người phụ trách, số bản vẽ, giờ công, ghi chú  
- Bấm giờ trên từng dòng (xem mục 7)  
- Xóa việc (có hộp xác nhận)

### 6.2 Các trạng thái việc nhỏ

| Trạng thái | Ý nghĩa |
|---|---|
| 未開始 | Chưa bắt đầu |
| 進行中 | Đang làm |
| 確認中 | Đang chờ xác nhận |
| 一時停止 | Tạm dừng |
| 完了 | Xong |
| キャンセル | Hủy |

### 6.3 Loại việc (種別) thường gặp

新規作成 / 修正(エラー) / 修正(変更) / チェック / 連絡 / 検討 / 相談・会議  

**Lợi ích:** Biết rõ từng bước nhỏ; tổng hợp giờ công theo loại việc phục vụ đánh giá công sức và báo cáo.

### 6.4 Việc của tôi 「マイタスク」

Mở 「Todoリスト」 (F3) → tab 「**マイタスク**」:

- Thấy các việc được giao cho bạn trên nhiều dự án  
- Có thể xác nhận đã nhận việc (「受領」 / 「受領済み」)  
- Bấm giờ, xem hạn, mở sang dự án / bản vẽ liên quan  

Tab 「**カスタムTodo**」: việc cá nhân **không** gắn dự án (nhắc việc riêng, có hạn, có thể kéo đổi thứ tự).

---

## 7. Bấm giờ công (作業計測)

### 7.1 Bắt đầu và kết thúc

| Nơi bấm | Cách làm |
|---|---|
| Trong tab 「タスク」 của dự án | Trên dòng việc: bắt đầu 「作業時間を開始」 / kết thúc 「作業時間を終了」 |
| Màn 「タスク一覧」 | Tương tự; có thể lọc 「作業計測中のタスクのみ」 |
| 「マイタスク」 | Bấm giờ trên việc được giao |
| Widget góc màn hình | Luôn thấy đồng hồ đang chạy; bấm dừng 「作業時間を終了」 bất cứ lúc nào |

### 7.2 Quy tắc dễ nhớ

- Thường **chỉ một đồng hồ** chạy cho một người tại một thời điểm. Nếu bắt đầu việc khác, việc trước sẽ dừng.  
- 「**工数**」 là tổng thời gian đã ghi nhận; có thể chỉnh bằng 「工数を編集」 (giờ / phút) nếu được phép.  
- Giờ công góp vào thống kê theo dự án / loại việc / người (ví dụ trên công trình hoặc 「タスク一覧」).

**Lợi ích:** Không cần nhớ “hôm nay làm bao lâu” — hệ thống cộng giúp; quản lý nắm được khối lượng thực tế theo từng loại việc.

---

## 8. Bản vẽ, tệp đính kèm, lịch trình

### 8.1 Bản vẽ 「図面」

Trong dự án → tab 「図面」:

- Thêm / sửa / lọc bản vẽ theo trạng thái  
- Gắn bản vẽ với công việc (タスク), người phụ trách, đơn giá  
- Hệ thống có thể cảnh báo nếu tổng tiền bản vẽ lệch so với tiền dự án, hoặc còn bản vẽ chưa gán  

### 8.2 Tệp đính kèm 「添付ファイル」

Có trên cả **công trình** và **dự án**:

- Tạo thư mục 「フォルダ作成」  
- Tải file lên 「ファイルアップロード」  
- Tải về từng file hoặc 「ZIPでダウンロード」  
- Đổi tên, xem, sao chép đường dẫn, xóa (theo quyền)  

**Lợi ích:** Tài liệu nằm đúng chỗ công việc — không phải lục email.

### 8.3 Lịch trình 「ガントチャート」

- Trong một dự án: nhìn các công việc theo thời gian  
- Toàn cục 「案件ガントチャート」: nhìn nhiều dự án cùng lúc  

Hữu ích khi cần trả lời: “Tuần này / tháng này phòng đang xếp việc thế nào?”

---

## 9. Báo giá và thanh toán (決済情報)

### 9.1 Trên chi tiết dự án

Khối 「**決済情報**」 gồm hai phần chính:

**見積 (báo giá)**  
- Ngày báo giá, số tiền (chưa thuế), thuế 10%, tổng có thuế  
- Số báo giá, tình trạng: 未発行 / 発行済 / 無償  

**請求 (hóa đơn / yêu cầu thanh toán)**  
- Ngày, số tiền (có thể 「見積と同額」), số chứng từ  
- Tình trạng: 未発行 / 請求準備 / 発行済 / 無償  

Có ô 「決済備考」 và nút 「履歴」 để xem lịch sử thay đổi.  
Khi đưa dự án sang **完了**, thường cần báo giá / hóa đơn ở trạng thái **đã phát hành** hoặc **miễn phí (無償)** (tùy quy định trên hệ thống).

### 9.2 Chứng từ báo giá trên công trình

Tại 「建物詳細」 → khu 「見積書」: tạo / xem các tờ báo giá gắn với các dự án con.

### 9.3 Thu tiền

Phần theo dõi **đã thu tiền chưa** nằm ở menu 「統計情報」 → 「**入金管理**」 (không nằm trong tab tổng quan dự án).

**Lợi ích:** Trạng thái kinh doanh (đã báo giá / đã xuất hóa đơn) đi cùng trạng thái tiến độ kỹ thuật trên cùng một dự án.

---

## 10. Ghi chú, trao đổi, yêu thích

| Chức năng | Dùng thế nào |
|---|---|
| 「メモ」 trên công trình / dự án / danh sách | Ghi chú nội bộ; có thể đánh 「重要メモ」 |
| 「CAILYメモ」 / 「GUISメモ」 | Phân loại ghi chú theo bên CAILY hoặc GUIS; có thể gắn với một cột thông tin |
| 「コメント」 trên dự án | Trao đổi theo chủ đề / luồng hội thoại |
| Sao 「お気に入り」 | Đánh dấu công trình / dự án hay dùng; lọc 「お気に入りのみ」 |

---

## 11. Mẹo dùng hàng ngày

1. **Bắt đầu ngày:** mở 「案件一覧」 → chọn phòng → bật 「私の案件」 hoặc mở F3 「マイタスク」.  
2. **Trước khi làm việc:** vào đúng 「タスク」 → bấm bắt đầu giờ.  
3. **Khi tạm nghỉ / hết việc:** nhớ dừng đồng hồ (widget góc màn hoặc nút trên dòng việc).  
4. **Khi giao việc mới:** vào 「建物詳細」 → 「案件依頼作成」 → giao đúng phòng; rồi tạo 「タスク」 chi tiết.  
5. **Khi sắp đến hạn:** dùng bộ lọc “còn trong 7 ngày / quá hạn” trên danh sách dự án.  
6. **Khi cần xuất báo cáo:** 「Excel出力」 trên 「案件一覧」 sau khi đã lọc đúng.  
7. **Lần đầu dùng danh sách dự án:** bấm 「UIガイド」 để xem giải thích từng nút trên màn hình.  
8. **Tìm nhanh:** F1 / Ctrl+K thay vì lục từng trang.  

**Quyền hạn:** Một số nút (sửa, xóa, tạo mới, sửa thông tin thanh toán…) chỉ hiện khi bạn được cấp quyền. Nếu không thấy nút, hãy hỏi quản trị viên / quản lý phòng — không phải lỗi máy.

---

## 12. Bảng tra cứu nhanh tên trên màn hình

| Hiện trên màn hình | Nghĩa ngắn |
|---|---|
| 建物一覧 / 建物登録 / 建物詳細 | Danh sách / đăng ký / chi tiết công trình |
| 案件一覧 / 案件詳細 / 案件依頼作成 | Danh sách / chi tiết dự án / tạo dự án từ công trình |
| お施主様名 / 管理番号 / 工事番号 | Tên chủ đầu tư / mã quản lý / mã công trình |
| 依頼（意匠・設備・省エネ…） | Loại phần việc yêu cầu |
| ステータス / 進捗 / 予定工程 | Trạng thái / tiến độ / kế hoạch giai đoạn |
| タスク / 種別 / 優先度 | Công việc nhỏ / loại việc / mức ưu tiên |
| 工数 / 作業計測中 / 作業時間を開始・終了 | Giờ công / đang đo giờ / bắt đầu·dừng |
| マイタスク / Todoリスト | Việc được giao cho tôi / danh sách việc cá nhân |
| 図面 / 添付ファイル / ガントチャート | Bản vẽ / file đính kèm / lịch dạng biểu đồ |
| 決済情報 / 見積 / 請求 / 見積書 | Thanh toán / báo giá / hóa đơn / tờ báo giá |
| 入金管理 | Quản lý thu tiền |
| メモ / コメント / お気に入り | Ghi chú / trao đổi / yêu thích |
| 高度なフィルター / Excel出力 / 列の表示 | Lọc nâng cao / xuất Excel / chọn cột |
| 案件を編集 / UIガイド | Sửa nhanh / hướng dẫn trên màn hình |
| 保存 / キャンセル / 戻る / 編集 / 削除 | Lưu / hủy / quay lại / sửa / xóa |

---

## Phụ lục — Gợi ý thứ tự thao tác cho người mới

```
1) Đăng ký công trình (建物登録)
2) Mở chi tiết công trình → tạo từng phần việc (案件依頼作成)
3) Vào từng dự án → tạo công việc nhỏ (タスク)
4) Làm việc → bấm giờ (作業計測)
5) Cập nhật tiến độ / trạng thái / ngày giao
6) Điền báo giá · hóa đơn khi đến bước kinh doanh
7) Đính kèm file · bản vẽ · ghi chú khi cần
8) Đưa dự án sang 完了 khi đã xong và đủ điều kiện thanh toán
```

---

*Tài liệu mô tả chức năng theo các màn hình nhóm 「プロジェクト」 (công trình, dự án, công việc, giờ công). Các phần quản trị hệ thống, chấm công nhân sự riêng (タイムカード), hay thống kê nâng cao nằm ngoài phạm vi hướng dẫn này.*
