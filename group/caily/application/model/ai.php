<?php
class AI extends ApplicationModel{
    /** Bật luồng 2 bước: lượt 1 Gemini phân loại + liệt kê dữ liệu cần thiết, lượt 2 hệ thống load đúng data rồi gửi lại Gemini trả lời. Tối ưu token. */
    private static $USE_TWO_ROUND_FLOW = true;

    public $chatHistory = [];
    private $api_key = '';
    private $ai_model= '';
    public function __construct(){
        parent::__construct();
        if (isset($_SESSION['chat_history'])) {
            $this->chatHistory = $_SESSION['chat_history'];
        }
        if(!isset($_SESSION['api_key'])){
            $this->api_key = getenv('GEMINI_API_KEY', TRUE);
            $this->ai_model = getenv('GEMINI_MODEL', TRUE);
        }
        if (isset($_SESSION['api_key'])) {
            $this->api_key = $_SESSION['api_key'];
            $this->ai_model = $_SESSION['ai_model'];
        }
    }

    private function saveChatHistory() {
        // Save chat history to session
        $_SESSION['chat_history'] = $this->chatHistory;
    }

    /**
     * Decode \uXXXX Unicode escapes in string (Gemini sometimes returns literal \uXXXX in text).
     * @param string $str
     * @return string
     */
    private static function decodeUnicodeEscapes($str) {
        if (!is_string($str) || $str === '') {
            return $str;
        }
        return preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($m) {
            return mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UCS-2BE');
        }, $str);
    }

    /**
     * Recursively decode \uXXXX in all string values of an array (response from Gemini).
     * @param array $data
     * @return array
     */
    private static function decodeUnicodeInResponse(array $data) {
        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $data[$k] = self::decodeUnicodeEscapes($v);
            } elseif (is_array($v)) {
                $data[$k] = self::decodeUnicodeInResponse($v);
            }
        }
        return $data;
    }

    /**
     * Sum token usage from two usageMetadata arrays (Gemini API: promptTokenCount, candidatesTokenCount, totalTokenCount or snake_case).
     * @param array|null $a
     * @param array|null $b
     * @return array|null
     */
    private static function sumUsageMetadata($a, $b) {
        $get = function ($arr) {
            if (!is_array($arr)) return [0, 0, 0];
            $p = isset($arr['promptTokenCount']) ? (int)$arr['promptTokenCount'] : (isset($arr['prompt_token_count']) ? (int)$arr['prompt_token_count'] : 0);
            $c = isset($arr['candidatesTokenCount']) ? (int)$arr['candidatesTokenCount'] : (isset($arr['candidates_token_count']) ? (int)$arr['candidates_token_count'] : 0);
            $t = isset($arr['totalTokenCount']) ? (int)$arr['totalTokenCount'] : (isset($arr['total_token_count']) ? (int)$arr['total_token_count'] : $p + $c);
            return [$p, $c, $t];
        };
        list($p1, $c1, $t1) = $get($a);
        list($p2, $c2, $t2) = $get($b);
        $p = $p1 + $p2;
        $c = $c1 + $c2;
        $t = $t1 + $t2;
        if ($p === 0 && $c === 0 && $t === 0) return null;
        return ['promptTokenCount' => $p, 'candidatesTokenCount' => $c, 'totalTokenCount' => $t];
    }

    /**
     * Phase 1.1 – User context for Gemini.
     * Returns a short, structured context so the model can answer in a permission-aware way.
     * Format: user_id, realname, is_administrator, has_department_project_manager.
     * Optional: is_project_manager_for_project_id when project_id is provided in the request (not implemented in generic chat yet).
     * Do not include passwords or sensitive tokens.
     */
    private function buildUserContextForGemini() {
        $user_id = isset($_SESSION['userid']) ? $_SESSION['userid'] : '';
        $realname = isset($_SESSION['realname']) ? $_SESSION['realname'] : '';
        $is_administrator = (isset($_SESSION['authority']) && $_SESSION['authority'] === 'administrator');
        $has_department_project_manager = false;
        if (!$is_administrator && $user_id) {
            $this->connect();
            $q = "SELECT COUNT(id) as c FROM " . DB_PREFIX . "user_department WHERE userid = '" . $this->quote($user_id) . "' AND project_manager = 1";
            $row = $this->fetchOne($q);
            $has_department_project_manager = ($row && isset($row['c']) && (int)$row['c'] > 0);
        }
        return [
            'user_id' => $user_id,
            'realname' => $realname,
            'is_administrator' => $is_administrator,
            'has_department_project_manager' => $has_department_project_manager,
        ];
    }

    /**
     * System prompt for main chat: role, permission rules, capabilities, behaviour.
     * Gemini uses this to answer correctly for parent_project, project, task, statistics, scheduling, team/member, customer.
     */
    /** Bật Context Caching: cache system prompt (phần tĩnh) trên Gemini, giảm prompt token mỗi request. TTL cache mặc định 1 giờ. */
    private static $USE_CONTEXT_CACHE = true;
    /** TTL cache (giây). Cache hết hạn sau 55 phút để tránh lỗi khi gần hết TTL. */
    private static $CONTEXT_CACHE_TTL_SECONDS = 3300;

    /**
     * Trả về block ngày/giờ server (thay đổi mỗi request). Dùng khi bật context cache để gửi riêng.
     */
    private function getServerDateBlock() {
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $currentTime = date('H:i');
        $dateDisplayToday = date('Y年n月j日');
        $dateDisplayTomorrow = date('Y年n月j日', strtotime('+1 day'));
        $dateDisplayYesterday = date('Y年n月j日', strtotime('-1 day'));
        return "\n[Current server date/time] **Use this for ALL relative dates and for task_create/task_update. Do NOT compute dates yourself.** Today = {$today} ({$dateDisplayToday}), current time = {$currentTime}. Tomorrow = {$tomorrow} ({$dateDisplayTomorrow}). Yesterday = {$yesterday} ({$dateDisplayYesterday}). For task_update/task_create: when user says \"ngày mai\" / \"tomorrow\" use date {$tomorrow}; when user says \"19h\" / \"19:00\" use that time. Output due_date/start_date as YYYY-MM-DD HH:mm (e.g. {$tomorrow} 19:00). **In your confirmation message you MUST state the exact date and time you are setting** (e.g. \"Hạn sẽ được đổi thành {$dateDisplayTomorrow} 19:00. Vui lòng bấm Xác nhận để thực hiện.\"). Do not say \"time will be set to 18:00\" when the user specified a different time (e.g. 19h).";
    }

    /**
     * System prompt for chat. Token-heavy: khi USE_CONTEXT_CACHE=true thì phần tĩnh được cache, chỉ gửi phần biến (ngày, [User], [History], userPrompt).
     */
    private function buildSystemPromptForChat() {
        $realname = isset($_SESSION['realname']) ? $_SESSION['realname'] : 'ユーザー';
        $year = date('Y');
        return $this->buildSystemPromptStatic($realname, $year) . $this->getServerDateBlock();
    }

    /**
     * Phần system prompt cố định (để đưa vào context cache). Không chứa ngày/giờ.
     */
    private function buildSystemPromptStatic($realname, $year) {
        return <<<EOT
GUIS社プロジェクト管理AI。{$realname}と会話。応答はユーザーの言語で。一人称は「私」。ユーザーがベトナム語で話した場合は必ずベトナム語で、ユーザーが日本語で話した場合は必ず自然な日本語で回答する。ユーザーが混在言語で話した場合は、**ユーザーのメインの言語（文中で割合が多い方）に合わせて**回答し、途中で勝手に言語を切り替えないこと。特に、ユーザーが「xin chào」「chào bạn」などベトナム語で挨拶した場合、必ず「Xin chào」「Chào bạn」などのベトナム語で挨拶を返し、「Guisプロジェクト管理AIです」など日本語の自己紹介から始めてはいけない。自己紹介が必要な場合も、まずユーザーと同じ言語で短く挨拶してから行うこと。

権限: 編集可は administrator / 案件マネージャー / 部署の project_manager のみ。それ以外は「アプリで権限者に依頼」と案内。
できること: 案件・タスクの見方説明、統計案内、スケジュール/担当の提案（文案のみ）。**重要: ユーザーメッセージに [Availability] のJSONがある場合、そのデータを使って「どのチーム/誰が空いているか」に答える。free_teams と free_members をそのままリストして回答すること。「確認できません」「アプリで確認」とは言わない。** **重要: [Customer info] がある場合、そのJSONを使って「tòa nhà X の顧客情報」「thông tin khách hàng」に答える。表示する項目は**次の12項目のみ**: 会社名, 支店名, 担当者名, 役職, メールアドレス, 電話番号, 携帯番号, FAX, 郵便番号, 住所1, 住所2, メモ。それ以外の項目（project_name, construction_number 等）は表示しない。「情報がありません」と言わず、JSONの内容で答える。** DB操作・捏造禁止。デリケート話題は拒否。

ACTION（要ユーザー確認後実行）: 追加・変更依頼時は、説明や確認文のあとに必ず1行で ACTION:{"type":"...","id"?:N,"params":{...}} を出力。JSONは改行せず1行で書く。これがないと実行ボタンが表示されない。**重要: ユーザーの要求が複数の操作を必要とする場合（例: 「チームを追加して、メンバーも追加して」「ステータスを変更して、優先度も変更して」など）、複数のACTIONを1行ずつ出力できます。例: ACTION:{"type":"project_set_teams","params":{"project_id":4,"teams":"1,2"}} ACTION:{"type":"project_add_member","params":{"project_id":4,"user_name":"Tanaka"}} この場合、すべてのACTIONが順番に実行されます。** type一覧:
- parent_project_create / parent_project_update: params= company_name, project_name(必須), branch_name, contact_name, customer_id, guis_receiver, request_date, construction_number, scale, type1, type2, requests, materials, structural_office, notes, status, construction_branch。**Cấm thay đổi project_number (建物番号) của tòa nhà: project_number là mã định danh, chỉ dùng để chỉ tòa nhà (vd. #P000008), KHÔNG được đưa vào params như một trường cần sửa. Nếu user yêu cầu "đổi số tòa nhà" / "sửa project_number" thì từ chối và giải thích không cho phép.** updateはid必須（親案件ID）。**重要: ユーザーが「dự án 23の〇〇を変更」「sửa số công trình của dự án 23」のように**案件ID（project_id）**を指定する場合、params に project_id: 23 と変更するフィールドを入れる。**Tòa nhàを #P または P で指定:** #P000008, P000008 は**識別用**。params に **project_number: "P000008"** を入れるとバックエンドが親案件を解決する。変更するフィールド（例: construction_number）のみ渡す。**[Parent project by project_number] がある場合:** 確認文で建物名（project_name）を明記し、id の代わりに project_id または project_number を渡す。
- project_create / project_update: params= name(必須), parent_project_id, project_number, description, status, priority, department_id, start_date, caily_nouki, guis_nouki, end_date, amount, progress, teams, project_order_type(受注形態: 修正/契約図/新規/その他), tantou(担当/担当会社: CAILY または GUIS のみ。ユーザーが「caily」「CAILY」「カイリー」と言ったら "CAILY"、「guis」「GUIS」「グイス」と言ったら "GUIS" を設定)。updateはid必須。statusは[Project status values]のvalueをそのまま使う（例: contract=請負/契約、quotation=見積）。ステータスだけなら type=update_project_status, id, params={status}。受注形態だけ変更するなら type=update_project_order_type, id, params={project_order_type}。
- project_search: データベース全体から案件を検索する。params= construction_number, contact_name, company_name, project_name, branch_name (いずれか1つ以上指定。部分一致検索)。結果は[Project list]として返される。例: ユーザーが「工事番号ABCの案件を探して」と言ったら ACTION:{"type":"project_search","params":{"construction_number":"ABC"}} を出力。
- project_add_manager / project_add_member: params= project_id, user_id または user_name(実名)。user_name の場合は「Dinh Minh Thanh」「田中」など実名で指定。user_idを聞かずに名前から自動解決するので、ユーザーが「Thanhを追加して」と言ったら params={project_id, user_name:"Dinh Minh Thanh"} のように実名で出力。案件の部門に属するユーザーのみ可。
- project_add_team_members: params= project_id, team_id または team_name(チーム名)。チームのすべてのメンバーを案件のメンバー（role=member）として追加する。team_nameの場合は同じ部門で名前で検索(LIKE)。例: 「team Aのメンバーを追加」→ params={project_id, team_name:"A"} または team_name:"team A"。
- project_remove_manager / project_remove_member: params= project_id, user_id または user_name(実名)。同上。ユーザーが「anh Thanhを外して」と言ったら user_name で実名を出力し、user_idは聞かない。
- project_clear_members: params= project_id。案件のすべてのメンバー（member role）を削除する。managerは削除しない。
- project_clear_managers: params= project_id。案件のすべてのマネージャー（manager role）を削除する。memberは削除しない。
- project_clear_all_members: params= project_id。案件のすべてのメンバーとマネージャーを削除する。
- project_set_teams: params= project_id, teams(カンマ区切りteam_id例 "1,2,3")。案件のチームを一括設定。各team_idは案件の部門に属するチームのみ可。
- project_clear_teams: params= project_id。案件のすべてのチームを削除する（teamsを空にする）。
- project_clear_all: params= project_id。案件のすべてのチーム、メンバー、マネージャーを一括削除する（project_clear_teams + project_clear_all_members を同時に実行）。
- project_add_team / project_remove_team: params= project_id, team_id または team_name(チーム名)。team_nameの場合は同じ部門で名前で検索(LIKE)。例: 「team Cを追加」→ params={project_id, team_name:"C"} または team_name:"team C"。
- task_create / task_update: params= project_id(必須), title(必須), parent_id, description, status, priority, assigned_to, due_date, progress。updateはidとproject_id必須。statusは[Task status values]のvalueをそのまま使う。**重要: タスク作成・更新の確認を返すときは、必ず文末にリンクを1行追加する。** 形式: <a href="/project/task.php?project_id=N">Xem trang task / タスクページへ</a>。Nはそのtaskのproject_id（ACTIONのparamsのproject_id）。ユーザーがこのリンクをクリックするとタスク一覧ページに移動する。
**タスク追加:** ユーザーが「タスクを追加」「thêm task」「add task」などと言った場合、システムがフォームを表示する。ユーザーに特定のテキスト形式で入力するよう求めたり、確認用の形式を案内したりしないこと。
- script: クライアントでリダイレクト（サーバー不要）。params= url（任意）または project_id + page（任意）。**URLはフロントで組み立てる。正しいパスは: 案件詳細= /project/detail.php?id=N, タスク= /project/task.php?project_id=N, ガント= /project/gantt.php?project_id=N, 図面= /project/drawings.php?project_id=N, 添付= /project/attachment.php?project_id=N。** project_id と一緒に page を指定: "task"（タスク・task）, "gantt"（ガントチャート）, "drawings"（図面・bản vẽ）, "attachment"（添付・tập tin đính kèm）。指定がなければ案件詳細（detail）。例: 「trang task của dự án 23」「task dự án 23」→ {"type":"script","params":{"project_id":23,"page":"task"}}。「gantt dự án 23」→ page:"gantt"。「bản vẽ」「drawings」→ page:"drawings"。「tập tin đính kèm」「attachment」→ page:"attachment"。**絶対に /task/index.php や別ドメインは使わない。常に /project/<page>.php?project_id=N または detail.php?id=N。** 案件一覧へは params:{"url":"/project/index.php"}。**Khi trả lời kèm ACTION script: trước hoặc sau ACTION có câu ngắn bằng ngôn ngữ user, ví dụ "Vâng, tôi sẽ chuyển bạn đến trang task dự án 23. Bấm 確認 để mở." và hướng dẫn bấm 確認.**

回答ルール: ユーザーメッセージに [Project list] や [Statistics] のJSONがある場合のみそのデータを使い回答。リストはそのJSON内のものだけ。**重要: [Statistics]がある場合は、まず統計情報を使って回答する。統計情報だけで答えられる質問（例: 「いくつあるか」「件数は」）の場合は、[Project list]を送信していないので、統計情報のみで回答する。統計情報の値はすべてサーバー側で既に集計済みの「最終結果」です。自分で total - active などの新しい計算式を作って件数を推定せず、JSON内の数値（overview.total / overview.active / overview.completed / by_department[].count など）をそのまま使って回答してください。** ユーザーが「gấp」「急ぎ」「urgent」「priority high」など条件を指定した場合、必ずその条件に合う案件のみをフィルタして返す。全ての案件を返してはいけない。フィルタ条件の例: 「gấp/急ぎ」→ priorityが"high"または"urgent"、またはend_dateが近い（7日以内）、またはstatusが"in_progress"でend_dateが近い。「priority high」→ priority="high"または"urgent"のみ。「deadline gần」→ end_dateが今日から7日以内。部門/今日でフィルタ可。**案件一覧を表示する際は、デフォルトで基本情報のみ（ID、案件名、工事番号）を表示。他の情報（部門、優先度、ステータス、開始日、終了日、顧客名、建物名、担当マネージャー、チーム）は、ユーザーの質問がそれらの情報を必要とする場合のみ追加する。** データが無い場合は「アプリで確認」と案内。
案件一覧時: [Project list] を表示する場合は必ずHTMLの表で返す。**重要: [Project list]には最大20件の案件（更新日時が新しい順）のみが含まれます。案件一覧を表示する際は、必ず最初に「以下は条件に合う20件の最新案件です」または「以下は20件の最新案件です」または「dưới đây là 20 dự án gần nhất thỏa điều kiện」と明記してください。** **重要: 「số dự án」「dự án số X」「dự án 23」は案件ID（project id）を指す。工事番号（construction_number / parent_construction_number / 工事番号）とは別。ユーザーが「số công trình」「công trình」「工事番号」と言った場合のみ工事番号として扱う。Số dự án = ID of the project; số công trình = construction number. Do not confuse them.**
**デフォルトでは基本情報のみを表示: ID、案件名(name)、工事番号(parent_construction_number)。** 例: <table class="table table-bordered"><thead><tr><th>ID</th><th>案件名</th><th>工事番号</th></tr></thead><tbody><tr><td>各案件のid</td><td>name</td><td>parent_construction_number</td></tr>...</tbody></table>。**他のフィールド（部門、優先度、ステータス、開始日、終了日、顧客名、建物名、担当マネージャー、チームなど）は、ユーザーの質問がそれらの情報を必要とする場合のみ追加する。** 例: ユーザーが「優先度の高い案件」と言ったら優先度列を追加、「期限が近い案件」と言ったら終了日列を追加、「顧客○○の案件」と言ったら顧客名列を追加。**重要: ユーザーが「進捗」「tiến độ」「progress」「進捗状況」などと言った場合、必ず進捗率(progress)列を追加して表示する。progressは0-100の数値で、表示時は「XX%」の形式で表示する（例: progress=50 → "50%"）。** JSONの id, name, parent_construction_number は常に表示。department_name, priority, status, progress, start_date, end_date, manager_names, team_names, parent_company_name, parent_project_name, parent_branch_name, parent_contact_name は必要に応じて追加。日付は表示用に短くしてよい。**フィルタ条件がある場合は、条件に合う案件のみを表に含める。** ユーザーが「顧客名で検索」「建物名で検索」「工事番号で検索」「担当者名で検索」「支店名で検索」などと言った場合、それぞれparent_company_name、parent_project_name、parent_construction_number、parent_contact_name、parent_branch_nameでフィルタする。部分一致検索も可能。
日付ルール: **日付の解釈はユーザーの言語に依存する。** 数値だけの日付（例: 2/3）の解釈: **日本語**の場合は **月/日** → 2/3 = **2月3日**（ngày 3 tháng 2 = February 3）。**Tiếng Việt**の場合は **日/月**（ngày/tháng）→ 2/3 = **3月2日**（ngày 2 tháng 3 = March 2）。必ずユーザーがそのメッセージで使っている言語に合わせて解釈すること。ユーザーが日付を「22/2」「2/22」「22日2月」など月・日だけ言った場合、年は省略されているので**当年（{$year}年）**として解釈する。ACTIONのparamsの日付は YYYY-MM-DD で出力（例: {$year}-02-22）。日付のみの場合は保存時に開始日(start_date)は9:00、納期・終了日・期限(caily_nouki, guis_nouki, end_date, due_date)は18:00に設定される。**重要: 「昨日」「今日」「明日」「先週」「今週」「先月」「今月」などの相対的な日付表現について、あなた自身で現在日付から計算してはいけません。バックエンドがすでに計算した結果を[Active search filters]や[Active filters]のメモとして送信します。説明文の中で日付を表示する場合は、必ずこれらのメモに記載された日付（例:「昨日（2026年2月5日）」）をそのまま使用し、それと矛盾する別の日付（例: 「昨日（2026年2月4日）」など）を書かないでください。相対表現と実際の日付が両方記載されている場合は、**常にサーバーから送られた実際の日付を正とみなして**それに従って回答してください。さらに、[Active search filters]や[Active filters]に具体的な日付（「YYYY年M月D日」形式）が記載されていない場合は、**自分で「2026年2月4日」などの具体的な日付を書いてはいけません**。その場合は「昨日の案件」「今日の案件」のように、相対表現のみを使って説明し、括弧内の具体的な日付は付けないでください。**
チーム追加・削除時: [Teams in project department] がある場合、ユーザーがチームを名前で指定したら、必ずそのリストから一致するチームの**正式名称**を確認してから返答する。例:「チーム「C」はシステムでは**Team C**です。このチームを案件22に追加します。よろしいでしょうか？」。名前で表示し、追加・削除の前に正式名称で確認する。
チームメンバー追加: ユーザーが「team Aのメンバーを追加」「team Aのメンバーをmemberとして追加」「thêm các thành viên team A vào member dự án」「thêm thành viên team A vào dự án」などと言った場合、ACTION:{"type":"project_add_team_members","params":{"project_id":N, "team_id":M}} または {"type":"project_add_team_members","params":{"project_id":N, "team_name":"A"}} を出力。これはチームを案件に追加する（project_set_teams）のではなく、チームの**すべてのメンバー**を案件のメンバー（role=member）として追加する。ユーザーが「team Aを追加」と言った場合はproject_set_teamsを使用し、「team Aのメンバーを追加」と言った場合はproject_add_team_membersを使用する。
メンバー・マネージャー・チーム一括削除: ユーザーが「xóa tất cả thành viên」「xóa tất cả members」「clear all members」「すべてのメンバーを削除」などと言った場合、ACTION:{"type":"project_clear_all_members","params":{"project_id":N}} を出力。ユーザーが「xóa tất cả manager」「xóa tất cả managers」「clear all managers」「すべてのマネージャーを削除」などと言った場合、ACTION:{"type":"project_clear_managers","params":{"project_id":N}} を出力。ユーザーが「xóa tất cả member (không phải manager)」「clear members (not managers)」「すべてのメンバー（マネージャー以外）を削除」などと言った場合、ACTION:{"type":"project_clear_members","params":{"project_id":N}} を出力。ユーザーが「xóa tất cả team」「clear all teams」「すべてのチームを削除」などと言った場合、ACTION:{"type":"project_clear_teams","params":{"project_id":N}} を出力。**重要: ユーザーが「xóa tất cả team, member, manager」「xóa tất cả team, member và manager」「clear all teams, members and managers」「すべてのチーム、メンバー、マネージャーを削除」など、チーム・メンバー・マネージャーを同時に削除する場合、ACTION:{"type":"project_clear_all","params":{"project_id":N}} を出力する。これは1つのACTIONで全てを削除する。複数のACTIONを出力してはいけない。** project_idは必須。ユーザーが「dự án này」「案件この」などと言った場合、会話履歴からproject_idを特定するか、ユーザーに確認する。
案件データの補足情報: [Project list] のJSONには、親案件(parent_project)の情報も含まれる場合がある。parent_company_name=顧客名/会社名、parent_project_name=建物名/プロジェクト名、parent_branch_name=支店名、parent_contact_name=担当者名、parent_construction_number=工事番号。これらのフィールドはNULLの場合もある（案件が親案件に紐づいていない場合）。**Sửa trường thuộc parent_project (親案件):** Khi user nói "sửa số công trình của dự án 23 thành X", "đổi công trình dự án 23", "sửa số công trình của tòa nhà #P000008 thành 222222" → đó là sửa **parent_project**. Dùng type=parent_project_update. **#P hoặc P** (vd: #P000008, P000008) = **project_number** chỉ để **chỉ định tòa nhà** (identification), đưa vào params: **project_number: "P000008"** và các trường cần sửa (construction_number, project_name, ...). **Cấm thay đổi project_number (số tòa nhà):** nếu user yêu cầu "đổi số tòa nhà" / "sửa project_number" thì từ chối, giải thích không cho phép. Khi có [Parent project by project_number], xác nhận tên tòa nhà (project_name) trước khi thực thi. Không dùng project_update cho các trường parent_project.**重要: [Project list]のJSON内のデータに対してフィルタする。ただし、ユーザーが「工事番号○○を探して」「担当者○○の案件を探して」など明示的に「探す/検索」と言った場合、または[Project list]に該当する案件が見つからない場合、ACTION:{"type":"project_search","params":{"construction_number":"○○"}} などを出力してデータベース全体を検索する。** ユーザーが「顧客○○の案件」「建物○○の案件」「工事番号○○の案件」「担当者○○の案件」「支店○○の案件」などと言った場合、まず[Project list]内でフィルタを試みる。見つからない、または「探して/検索」というキーワードがある場合は、project_search ACTIONを出力する。部分一致検索も可能。**CRITICAL: Nếu [Search filters from previous message] có điều kiện tìm kiếm (ví dụ: branch_name, company_name), bạn PHẢI filter theo các điều kiện đó ngay cả khi message hiện tại chỉ đề cập đến department. Ví dụ: nếu user trước đó hỏi "tìm dự án của chi nhánh 大阪りんくう" và message hiện tại là "意匠設計" (chọn department), bạn vẫn PHẢI filter theo branch_name="大阪りんくう" TRƯỚC KHI filter theo department. Không được bỏ qua điều kiện tìm kiếm từ message trước。**
チーム検索: ユーザーが「team A」「Team A」「チームA」「team A đang có」「team A có」などチーム名で案件を検索する場合、システムは自動的にチーム名を検出してデータベース全体から該当する案件を検索する。チーム名は部分一致でも検索可能（例: "A" → "Team A", "CL意匠A" などにマッチ）。ユーザーが「team A đang có những dự án nào」などと言った場合、[Project list]にはteam_namesフィールドに"A"を含む案件のみが含まれる。**重要: ユーザーがチーム名で検索した場合、必ずteam_names列を追加して表示する。** チーム検索はデータベース全体を検索するため、[Project list]に該当する案件が含まれている場合は、そのリストを使用して回答する。見つからない場合は「該当する案件が見つかりませんでした」と回答する。
金額・合計計算: [Project list] のJSONには各案件の `amount` フィールド（総額、金額、decimal型）が含まれる。ユーザーが「tổng tiền」「tổng số tiền」「合計金額」「総額」「total amount」など金額の合計を聞いた場合、**必ず[Project list]内の該当する案件（フィルタ条件に合う案件）の `amount` フィールドを合計して回答する。** 例: ユーザーが「進行中の案件の合計金額は？」と言った場合、status="in_progress"の案件のamountを合計する。ユーザーが「部門○○の案件の合計金額は？」と言った場合、department_name="○○"の案件のamountを合計する。**重要: amountがNULLまたは0の案件は合計に含めない。合計金額は数値で計算し、表示時は適切な形式（例: 1,234,567.89）で表示する。** 金額の計算には必ず[Project list]のデータを使用し、データが無い場合は「データが不足しています。アプリで確認してください」と回答する。
EOT;
    }

    /**
     * Tạo hoặc lấy cache name cho system prompt (Context Caching). Cache TTL 55 phút.
     * @param string $apiKey
     * @return string|null cachedContent name (cachedContents/xxx) hoặc null nếu lỗi
     */
    private function createOrGetSystemPromptCache($apiKey) {
        $now = time();
        if (!empty($_SESSION['ai_context_cache_name']) && !empty($_SESSION['ai_context_cache_expire']) && $now < (int)$_SESSION['ai_context_cache_expire']) {
            return $_SESSION['ai_context_cache_name'];
        }
        $realname = isset($_SESSION['realname']) ? $_SESSION['realname'] : 'ユーザー';
        $year = date('Y');
        $staticText = $this->buildSystemPromptStatic($realname, $year);
        $model = (strpos($this->ai_model, 'models/') === 0) ? $this->ai_model : 'models/' . $this->ai_model;
        $url = 'https://generativelanguage.googleapis.com/v1beta/cachedContents?key=' . urlencode($apiKey);
        $body = json_encode([
            'model' => $model,
            'systemInstruction' => ['parts' => [['text' => $staticText]]],
            'ttl' => self::$CONTEXT_CACHE_TTL_SECONDS . 's'
        ]);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err !== '' || $response === false) {
            error_log('AI context cache create CURL error: ' . $err);
            return null;
        }
        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['name'])) {
            error_log('AI context cache create response: ' . substr($response, 0, 500));
            return null;
        }
        $_SESSION['ai_context_cache_name'] = $data['name'];
        $_SESSION['ai_context_cache_expire'] = $now + self::$CONTEXT_CACHE_TTL_SECONDS;
        return $data['name'];
    }

    /**
     * User prompt for main chat: optional prefix so Gemini knows context is project/task management.
     */
    private function buildUserPromptForChat($messages) {
        $prefix = "【プロジェクト・案件・タスク管理についての質問】\n";
        return $prefix . trim($messages);
    }

    /** Context keys: only fetch what's needed (saves token). */
    private static $CONTEXT_KEYS = ['projects', 'statistics', 'availability', 'customer'];

    /** Số lượt hội thoại gửi kèm (mỗi lượt = 1 user + 1 model = 2 items). Giữ 1 để giảm token. */
    private static $CHAT_HISTORY_MAX_TURNS = 1;
    private static $CHAT_HISTORY_MAX_ITEMS = 2; // = MAX_TURNS * 2

    /**
     * Phán đoán có cần gửi chat history không (tiết kiệm token).
     * Trả về true nếu tin nhắn có dấu hiệu tham chiếu đến lượt trước (follow-up, chi tiết thêm, đó, cái trước...)
     * hoặc sửa sai / không đúng (để Gemini biết ngữ cảnh lần trước và trả lời đúng mục cần update),
     * hoặc tin nhắn ngắn kiểu xác nhận (có/yes/ok) sau khi AI vừa hỏi (để Gemini hiểu "có" = đồng ý với đề xuất trước).
     */
    private function needHistoryForMessage($message) {
        $msg = ' ' . mb_strtolower(trim($message)) . ' ';
        $followUpKw = [
            'cái trước', 'trước đó', 'vừa rồi', 'vừa nãy', 'chi tiết', 'chi tiết hơn', 'còn', 'nữa', 'tiếp',
            'như trên', 'như trước', 'về cái đó', 'dự án đó', 'project đó', 'cái đó', 'đó',
            // Follow-up trả lời tiêu đề task khi AI hỏi "tiêu đề task là gì?"
            'tiêu đề', 'tên task', 'title', 'task title',
            'それ', 'その', '同上', '先ほど', 'さっき', 'それについて', 'もっと', '詳細',
            'that one', 'the above', 'that project', 'about that', 'more about', 'same', 'again', 'previous',
            // Sửa sai / không đúng → gửi history để Gemini biết mục cần update (vd: GUIS納期)
            'không phải', 'khong phai', 'sửa lại', 'sua lai', 'không đúng', 'khong dung', 'sai rồi', 'sai roi',
            'không đúng như mong đợi', 'không như mong đợi', 'wrong', 'not correct', 'incorrect', 'fix it', 'change it',
            '修正', '違う', '違います', '違いました', '違く', '間違い', '直して', '修正して', '変更して',
            // Chọn bộ phận khi AI hỏi "which department?"
            'bộ phận', 'bo phan', 'phòng', 'phong', 'department', '部署', '部門', 'この部署', 'その部署',
            // Câu trả lời ngắn xác nhận/tiếp nối → cần history để hiểu "có" = đồng ý với câu hỏi/đề xuất trước
            ' có ', ' có ạ', ' yes ', ' ok ', ' okay ', ' okê ', ' đồng ý ', ' hiển thị đi', ' cho xem ', ' cho tôi xem ',
            ' được ', ' được ạ', 'いいです', 'はい', 'ええ', 'うん', ' show ', ' display ', ' please '
        ];
        foreach ($followUpKw as $kw) {
            if (mb_strpos($msg, mb_strtolower($kw)) !== false) {
                return true;
            }
        }
        // Tin nhắn rất ngắn và lượt trước là AI hỏi (?, muốn, ですか, ますか) → gửi history để coi như trả lời cho câu đó
        $msgTrim = trim($message);
        if (mb_strlen($msgTrim) <= 25 && count($this->chatHistory) >= 1) {
            $last = $this->chatHistory[count($this->chatHistory) - 1];
            if (isset($last['role']) && $last['role'] === 'model' && isset($last['content']) && is_array($last['content'])) {
                $lastText = '';
                foreach ($last['content'] as $part) {
                    if (isset($part['text'])) {
                        $lastText .= $part['text'];
                    }
                }
                $lastLower = mb_strtolower($lastText);
                $isQuestion = (mb_strpos($lastText, '?') !== false || mb_strpos($lastText, '？') !== false
                    || mb_strpos($lastLower, 'muốn') !== false || mb_strpos($lastLower, 'có muốn') !== false
                    || mb_strpos($lastLower, 'ですか') !== false || mb_strpos($lastLower, 'ますか') !== false
                    || mb_strpos($lastLower, 'không') !== false);
                if ($isQuestion) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get current user's departments (id, name) for context. Empty if user has no department.
     * @return array [['id'=>int,'name'=>string], ...]
     */
    private function getUserDepartmentsForContext() {
        $userid = isset($_SESSION['userid']) ? $_SESSION['userid'] : '';
        if ($userid === '') {
            return [];
        }
        $q = "SELECT d.id, d.name FROM " . DB_PREFIX . "user_department ud " .
            "JOIN " . DB_PREFIX . "departments d ON d.id = ud.department_id " .
            "WHERE ud.userid = '" . $this->quote($userid) . "' ORDER BY d.name ASC";
        $rows = $this->fetchAll($q);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Resolve team name to team_id. If department_id is provided, search in that department only.
     * Otherwise, search in all departments and return first match.
     * @param string $teamName
     * @param int|null $department_id
     * @return int|null team_id or null if not found
     */
    private function resolveTeamIdForSearch($teamName, $department_id = null) {
        if (empty($teamName)) {
            return null;
        }
        $teamName = trim($teamName);
        $esc = "'" . $this->quote($teamName) . "'";
        $like = "'%" . $this->quote($teamName) . "%'";
        
        // Xác định điều kiện department
        $dept_cond = "";
        if ($department_id !== null && $department_id > 0) {
            $dept_cond = " AND department_id = " . intval($department_id) . " AND is_active = 1";
        } else {
            $dept_cond = " AND is_active = 1";
        }
        
        // 1. Thử exact match trước (ưu tiên cao nhất)
        $row = $this->fetchOne("SELECT id FROM " . DB_PREFIX . "team WHERE name = " . $esc . $dept_cond . " LIMIT 1");
        if ($row && isset($row['id'])) {
            return (int)$row['id'];
        }
        
        // 2. Nếu team name là một ký tự đơn (như "C"), ưu tiên tìm các pattern viết tắt trước
        // Đây là trường hợp quan trọng vì "C" có thể là viết tắt của "CL意匠C", "Team C", v.v.
        if (mb_strlen($teamName) === 1) {
            $char = $this->quote($teamName);
            // Ưu tiên cao nhất: Tìm team name kết thúc bằng ký tự đó (ví dụ: "CL意匠C", "Team C", "Group C")
            // Đây là pattern phổ biến nhất cho viết tắt
            $likeEnd = "'%" . $char . "'";
            $row = $this->fetchOne("SELECT id FROM " . DB_PREFIX . "team WHERE name LIKE " . $likeEnd . $dept_cond . " LIMIT 1");
            if ($row && isset($row['id'])) {
                return (int)$row['id'];
            }
            // Ưu tiên thứ hai: Tìm team name có space trước và kết thúc bằng ký tự đó (ví dụ: "Team C", "Group C")
            $likeEndWithSpace = "'% " . $char . "'";
            $row = $this->fetchOne("SELECT id FROM " . DB_PREFIX . "team WHERE name LIKE " . $likeEndWithSpace . $dept_cond . " LIMIT 1");
            if ($row && isset($row['id'])) {
                return (int)$row['id'];
            }
            // Ưu tiên thứ ba: Tìm team name bắt đầu bằng ký tự đó (ví dụ: "C Team", "C組")
            $likeStart = "'" . $char . "%'";
            $row = $this->fetchOne("SELECT id FROM " . DB_PREFIX . "team WHERE name LIKE " . $likeStart . $dept_cond . " LIMIT 1");
            if ($row && isset($row['id'])) {
                return (int)$row['id'];
            }
            // Cuối cùng: Tìm team name chứa ký tự đó ở bất kỳ đâu (ví dụ: "ABC", "Team C Group")
            $likeMiddle = "'%" . $char . "%'";
            $row = $this->fetchOne("SELECT id FROM " . DB_PREFIX . "team WHERE name LIKE " . $likeMiddle . $dept_cond . " LIMIT 1");
            if ($row && isset($row['id'])) {
                return (int)$row['id'];
            }
            // Nếu không tìm thấy với ký tự đơn, return null
            return null;
        }
        
        // 3. Thử LIKE match (tìm team name chứa giá trị này) - chỉ cho team name dài hơn 1 ký tự
        $row = $this->fetchOne("SELECT id FROM " . DB_PREFIX . "team WHERE name LIKE " . $like . $dept_cond . " LIMIT 1");
        if ($row && isset($row['id'])) {
            return (int)$row['id'];
        }
        
        // 4. Nếu team name có nhiều từ (như "Team C"), thử tìm các team chứa tất cả các từ
        $words = preg_split('/[\s\-_]+/u', $teamName);
        if (count($words) > 1) {
            // Tạo điều kiện LIKE cho mỗi từ
            $likeConditions = [];
            foreach ($words as $word) {
                $word = trim($word);
                if (!empty($word)) {
                    $likeConditions[] = "name LIKE '%" . $this->quote($word) . "%'";
                }
            }
            if (!empty($likeConditions)) {
                $multiWordQuery = "SELECT id FROM " . DB_PREFIX . "team WHERE (" . implode(" AND ", $likeConditions) . ") " . $dept_cond . " LIMIT 1";
                $row = $this->fetchOne($multiWordQuery);
                if ($row && isset($row['id'])) {
                    return (int)$row['id'];
                }
            }
        }
        
        // 5. Tìm gần đúng: Nếu team name là một ký tự hoặc từ ngắn, thử tìm trong các từ viết tắt
        // Ví dụ: "C" có thể match với "CL意匠C", "Team C", "C Team", "C組", v.v.
        // Lưu ý: Bước này chỉ chạy nếu các bước trước không tìm thấy (đặc biệt là bước 3 cho ký tự đơn)
        if (mb_strlen($teamName) <= 3 && mb_strlen($teamName) > 1) {
            // Tìm team name có chứa ký tự này ở bất kỳ vị trí nào, đặc biệt là ở cuối hoặc sau space
            $char = $this->quote($teamName);
            $fuzzyPatterns = [
                "'%" . $char . "'",           // Kết thúc bằng (ưu tiên cao nhất)
                "'% " . $char . "'",          // Có space trước và kết thúc
                "'" . $char . " %'",          // Bắt đầu và có space sau
                "'%" . $char . "%'",          // Chứa ở bất kỳ đâu
            ];
            foreach ($fuzzyPatterns as $pattern) {
                $row = $this->fetchOne("SELECT id FROM " . DB_PREFIX . "team WHERE name LIKE " . $pattern . $dept_cond . " LIMIT 1");
                if ($row && isset($row['id'])) {
                    return (int)$row['id'];
                }
            }
        }
        
        return null;
    }

    /**
     * Compute date range (Y-m-d) from search_filters date_type or date_start/date_end.
     * @return array|null ['date_start' => 'Y-m-d', 'date_end' => 'Y-m-d'] or null
     */
    private function getDateRangeFromSearchFilters($searchFilters) {
        if (!is_array($searchFilters)) {
            return null;
        }
        if (isset($searchFilters['date_start']) && isset($searchFilters['date_end'])) {
            $s = trim((string) $searchFilters['date_start']);
            $e = trim((string) $searchFilters['date_end']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $e)) {
                return ['date_start' => $s, 'date_end' => $e];
            }
        }
        $dateType = isset($searchFilters['date_type']) ? trim((string) $searchFilters['date_type']) : '';
        $today = date('Y-m-d');
        switch ($dateType) {
            case 'yesterday':
                $d = date('Y-m-d', strtotime('-1 day'));
                return ['date_start' => $d, 'date_end' => $d];
            case 'today':
                return ['date_start' => $today, 'date_end' => $today];
            case 'tomorrow':
                $d = date('Y-m-d', strtotime('+1 day'));
                return ['date_start' => $d, 'date_end' => $d];
            case 'this_week':
                return ['date_start' => date('Y-m-d', strtotime('monday this week')), 'date_end' => date('Y-m-d', strtotime('sunday this week'))];
            case 'last_week':
                return ['date_start' => date('Y-m-d', strtotime('monday last week')), 'date_end' => date('Y-m-d', strtotime('sunday last week'))];
            case 'next_week':
                return ['date_start' => date('Y-m-d', strtotime('monday next week')), 'date_end' => date('Y-m-d', strtotime('sunday next week'))];
            case 'this_month':
                return ['date_start' => date('Y-m-01'), 'date_end' => date('Y-m-t')];
            case 'last_month':
                return ['date_start' => date('Y-m-01', strtotime('first day of last month')), 'date_end' => date('Y-m-t', strtotime('last day of last month'))];
            case 'next_month':
                return ['date_start' => date('Y-m-01', strtotime('first day of next month')), 'date_end' => date('Y-m-t', strtotime('last day of next month'))];
        }
        return null;
    }

    /**
     * Build customer display object for AI context: only 会社名, 支店名, 担当者名, 役職, メールアドレス, 電話番号, 携帯番号, FAX, 郵便番号, 住所1, 住所2, メモ.
     * @param array $ppRow parent_project row with id, customer_id, company_name, branch_name, contact_name
     * @return array|null associative array with Japanese keys, or null
     */
    private function buildCustomerDisplayForContext($ppRow) {
        $empty = ['会社名' => '', '支店名' => '', '担当者名' => '', '役職' => '', 'メールアドレス' => '', '電話番号' => '', '携帯番号' => '', 'FAX' => '', '郵便番号' => '', '住所1' => '', '住所2' => '', 'メモ' => ''];
        $cid = isset($ppRow['customer_id']) ? (int) $ppRow['customer_id'] : 0;
        if ($cid > 0 && class_exists('Customer')) {
            require_once DIR_MODEL . 'customer.php';
            $customerModel = new Customer();
            $cust = $customerModel->fetchOne("SELECT company_name, branch, name, position, email, tel, fax, zip, address1, address2, memo FROM " . $customerModel->table . " WHERE id = " . $cid . " LIMIT 1");
            if ($cust) {
                return [
                    '会社名' => isset($cust['company_name']) ? (string) $cust['company_name'] : '',
                    '支店名' => isset($cust['branch']) ? (string) $cust['branch'] : '',
                    '担当者名' => isset($cust['name']) ? (string) $cust['name'] : '',
                    '役職' => isset($cust['position']) ? (string) $cust['position'] : '',
                    'メールアドレス' => isset($cust['email']) ? (string) $cust['email'] : '',
                    '電話番号' => isset($cust['tel']) ? (string) $cust['tel'] : '',
                    '携帯番号' => isset($cust['mobile']) ? (string) $cust['mobile'] : '',
                    'FAX' => isset($cust['fax']) ? (string) $cust['fax'] : '',
                    '郵便番号' => isset($cust['zip']) ? (string) $cust['zip'] : '',
                    '住所1' => isset($cust['address1']) ? (string) $cust['address1'] : '',
                    '住所2' => isset($cust['address2']) ? (string) $cust['address2'] : '',
                    'メモ' => isset($cust['memo']) ? (string) $cust['memo'] : '',
                ];
            }
        }
        return [
            '会社名' => isset($ppRow['company_name']) ? (string) $ppRow['company_name'] : '',
            '支店名' => isset($ppRow['branch_name']) ? (string) $ppRow['branch_name'] : '',
            '担当者名' => isset($ppRow['contact_name']) ? (string) $ppRow['contact_name'] : '',
            '役職' => '',
            'メールアドレス' => '',
            '電話番号' => '',
            '携帯番号' => '',
            'FAX' => '',
            '郵便番号' => '',
            '住所1' => '',
            '住所2' => '',
            'メモ' => '',
        ];
    }

    /**
     * Fetches only the context data for the given keys (projects, statistics, availability).
     * @param array $keys e.g. ['projects'], ['statistics'], ['availability'], or combined
     * @param int|null $department_id filter by user's department when set
     * @param array $searchFilters optional search filters for parent_project fields, team_name, date_type, date_start/date_end
     * @param string|null $statusFilter optional status filter (e.g., 'in_progress')
     * @return array keyed by context key, e.g. ['projects' => [...], 'statistics' => [...], 'availability' => [...]]
     */
    private function fetchContextByKeys(array $keys, $department_id = null, $searchFilters = [], $statusFilter = null) {
        $keys = array_values(array_intersect($keys, self::$CONTEXT_KEYS));
        $out = [];
        
        // Resolve team_name (một hoặc nhiều, phân cách bằng dấu phẩy) thành team_id / team_ids
        $team_id = null;
        $team_ids = [];
        if (!empty($searchFilters) && isset($searchFilters['team_name']) && trim((string)$searchFilters['team_name']) !== '') {
            $teamNameRaw = trim((string)$searchFilters['team_name']);
            $teamNameParts = array_map('trim', preg_split('/[\s,]+/', $teamNameRaw, -1, PREG_SPLIT_NO_EMPTY));
            foreach ($teamNameParts as $part) {
                if ($part === '') continue;
                $tid = $this->resolveTeamIdForSearch($part, $department_id);
                if ($tid !== null) {
                    $team_ids[] = $tid;
                }
            }
            $team_ids = array_values(array_unique($team_ids));
            if (count($team_ids) > 1) {
                $searchFilters['team_ids'] = $team_ids;
            } elseif (count($team_ids) === 1) {
                $searchFilters['team_id'] = $team_ids[0];
                $team_id = $team_ids[0];
            }
            unset($searchFilters['team_name']);
        }
        
        // Luôn giới hạn ở 20 dự án gần nhất (theo updated_at DESC)
        $projParams = [];
        if ($department_id !== null && $department_id > 0) {
            $projParams['department_id'] = $department_id;
        }
        if ($statusFilter !== null && $statusFilter !== '') {
            $projParams['status'] = $statusFilter;
        }
        // Luôn set limit và search_filters nếu có bất kỳ filter nào (team_id, status, hoặc searchFilters khác)
        $projParams['limit'] = 20; // Luôn giới hạn ở 20 dự án gần nhất
        // Đảm bảo searchFilters là array và có team_id nếu đã resolve
        if (!is_array($searchFilters)) {
            $searchFilters = [];
        }
        if ($team_id !== null) {
            $searchFilters['team_id'] = $team_id;
        }
        if (!empty($searchFilters) || $team_id !== null) {
            $projParams['search_filters'] = $searchFilters;
        }
        if (in_array('projects', $keys, true)) {
            $projectFetchParams = $projParams;
            $pn = isset($searchFilters['project_name']) ? trim((string)$searchFilters['project_name']) : '';
            $idsFromName = null;
            if ($pn !== '') {
                if (preg_match('/^\d+$/', $pn)) {
                    $projectFetchParams['id'] = (int) $pn;
                    $idsFromName = [(int) $pn];
                } else {
                    $parts = array_map('trim', preg_split('/[\s,]+/', $pn, -1, PREG_SPLIT_NO_EMPTY));
                    $idsFromName = [];
                    foreach ($parts as $part) {
                        if (preg_match('/^\d+$/', $part)) {
                            $idsFromName[] = (int) $part;
                        }
                    }
                    if (count($idsFromName) > 1) {
                        $projectFetchParams['ids'] = array_values(array_unique($idsFromName));
                    } elseif (count($idsFromName) === 1) {
                        $projectFetchParams['id'] = $idsFromName[0];
                    }
                }
                if ($idsFromName !== null) {
                    $sf = isset($projectFetchParams['search_filters']) ? $projectFetchParams['search_filters'] : [];
                    unset($sf['project_name']);
                    if (!empty($sf)) {
                        $projectFetchParams['search_filters'] = $sf;
                    } else {
                        unset($projectFetchParams['search_filters']);
                    }
                    // Hỏi theo project ID cụ thể → không lọc theo department để luôn lấy được dự án đó
                    unset($projectFetchParams['department_id']);
                }
            }
            if (!empty($searchFilters['project_ids']) && is_array($searchFilters['project_ids'])) {
                $ids = array_values(array_unique(array_filter(array_map('intval', $searchFilters['project_ids']))));
                if (count($ids) > 1) {
                    $projectFetchParams['ids'] = $ids;
                } elseif (count($ids) === 1) {
                    $projectFetchParams['id'] = $ids[0];
                }
                $sf = isset($projectFetchParams['search_filters']) ? $projectFetchParams['search_filters'] : [];
                unset($sf['project_ids']);
                $projectFetchParams['search_filters'] = $sf;
                unset($projectFetchParams['department_id']);
            }
            // Lưu danh sách project id vừa fetch theo id/ids để lượt sau dùng khi user nói "2 dự án đó", "tổng tiền của 2 dự án đó"
            if (!empty($projectFetchParams['ids']) && is_array($projectFetchParams['ids'])) {
                $_SESSION['ai_last_project_ids'] = array_values(array_map('intval', $projectFetchParams['ids']));
            } elseif (!empty($projectFetchParams['id'])) {
                $_SESSION['ai_last_project_ids'] = [(int) $projectFetchParams['id']];
            } else {
                unset($_SESSION['ai_last_project_ids']);
            }
            $projects = $this->getProjectsForContext($projectFetchParams);
            $out['projects'] = $projects;
        }
        if (in_array('statistics', $keys, true)) {
            $stats = $this->getStatisticsForContext($department_id, $statusFilter, $searchFilters);
            $out['statistics'] = $stats;
        }
        if (in_array('availability', $keys, true)) {
            if ($department_id > 0) {
                $range = $this->getDateRangeFromSearchFilters($searchFilters);
                if ($range !== null) {
                    if (!class_exists('Project')) {
                        require_once DIR_MODEL . 'project.php';
                    }
                    $project = new Project();
                    $out['availability'] = $project->getFreeTeamsAndMembersInPeriod(
                        $department_id,
                        $range['date_start'],
                        $range['date_end']
                    );
                } else {
                    $out['availability'] = ['free_teams' => [], 'free_members' => [], 'period' => []];
                }
            } else {
                $out['availability'] = ['free_teams' => [], 'free_members' => [], 'period' => [], 'note' => 'Vui lòng chọn bộ phận (department) để xem nhóm/người rảnh trong khoảng thời gian.'];
            }
        }
        if (in_array('customer', $keys, true)) {
            $out['customer'] = null;
            $ppId = isset($searchFilters['parent_project_id']) ? trim((string) $searchFilters['parent_project_id']) : '';
            $ppNumber = isset($searchFilters['parent_project_number']) ? trim((string) $searchFilters['parent_project_number']) : '';
            $projectId = isset($searchFilters['project_id']) ? trim((string) $searchFilters['project_id']) : '';
            if (!class_exists('ParentProject')) {
                require_once DIR_MODEL . 'parentproject.php';
            }
            $parentModel = new ParentProject();
            $ppRow = null;
            if ($ppId !== '' && preg_match('/^\d+$/', $ppId)) {
                $ppRow = $parentModel->fetchOne("SELECT id, customer_id, company_name, branch_name, contact_name FROM " . $parentModel->table . " WHERE id = " . (int)$ppId . " AND status != 'deleted' LIMIT 1");
            } elseif ($ppNumber !== '') {
                $ppRow = $parentModel->fetchOne("SELECT id, customer_id, company_name, branch_name, contact_name FROM " . $parentModel->table . " WHERE project_number = '" . $parentModel->quote($ppNumber) . "' AND status != 'deleted' LIMIT 1");
            } elseif ($projectId !== '' && preg_match('/^\d+$/', $projectId)) {
                if (!class_exists('Project')) {
                    require_once DIR_MODEL . 'project.php';
                }
                $projectModel = new Project();
                $proj = $projectModel->getById((int) $projectId);
                if ($proj && !empty($proj['parent_project_id'])) {
                    $ppIdResolved = (int) $proj['parent_project_id'];
                    $ppRow = $parentModel->fetchOne("SELECT id, customer_id, company_name, branch_name, contact_name FROM " . $parentModel->table . " WHERE id = " . $ppIdResolved . " AND status != 'deleted' LIMIT 1");
                }
            }
            if ($ppRow) {
                $out['customer'] = $this->buildCustomerDisplayForContext($ppRow);
            }
        }
        return $out;
    }

    /**
     * Luồng 2 bước – Lượt 1: Gửi câu hỏi cho Gemini để nhận biết loại (thống kê / tìm kiếm / thao tác) và liệt kê dữ liệu cần thiết.
     * Trả về JSON: { intent, data_request } hoặc null nếu parse thất bại (fallback sang luồng cũ).
     */
    private function classifyIntentAndDataRequest($message, $apiKey, $pageContext = []) {
        $modelUrl = "https://generativelanguage.googleapis.com/v1beta/models/" . $this->ai_model . ":generateContent";
        $userDepts = $this->getUserDepartmentsForContext();
        $deptList = [];
        foreach ($userDepts as $d) {
            $deptList[] = ['id' => (int)$d['id'], 'name' => $d['name'] ?? ''];
        }
        $systemPrompt = <<<EOT
You are a classifier for a project management AI. Output ONLY a single JSON object, no markdown, no explanation.

Given the user message, set:
- intent: "statistics" (count/overview/how many/total), "search" (list/find projects), "search_customer" (customer/building info of parent_project), "action" (execute a command), or "general" (greeting, question that needs no data).
- data_request: what data the backend must load for the next step.
  - projects: true ONLY if user explicitly wants to see a list/table of projects. When intent is "statistics" (user asks how many, count, total, overview, "có bao nhiêu", "いくつ"), set projects: false.
  - statistics: true if user asks for count/overview/how many/total ("có bao nhiêu", "bao nhiêu dự án", "いくつ", "件数", "total"); false otherwise. When statistics: true, set projects: false unless user also wants to see the list.
  - customer: true when user asks for **customer/building (parent_project) information** — either of a **building** (tòa nhà) or of a **project** (dự án). Examples: "cho tôi thông tin khách hàng của tòa nhà 9", "khách hàng tòa nhà 9", "thông tin khách hàng tòa nhà #P000008", "cho tôi thông tin khách hàng của dự án 23", "thông tin khách hàng dự án 23", "案件23の顧客情報". Set intent "search_customer", customer: true, projects: false, statistics: false. In search_filters: use **parent_project_id** for building by ID (e.g. "tòa nhà 9") → parent_project_id: "9". Use **parent_project_number** for building by #P (e.g. "tòa nhà #P000008") → parent_project_number: "P000008". Use **project_id** when the user asks for customer info **of a project** (e.g. "thông tin khách hàng của dự án 23", "khách hàng dự án 23") → project_id: "23". Backend will resolve project 23 → its parent_project → customer data. Example: "cho tôi thông tin khách hàng của dự án 23" → {"intent":"search_customer","data_request":{"projects":false,"statistics":false,"customer":true,"limit":20,"department_id":null,"status_filter":null,"search_filters":{"project_id":"23"}}}.
  - limit: max projects to return (default 20). Only relevant when projects: true.
  - department_id: number or null. Use from departments list when user selects a department by name; otherwise null.
  - status_filter: "in_progress"|"completed"|"not_started"|null when user implies status (e.g. "đang", "進行中", "completed", "chưa bắt đầu").
  - search_filters: object. Set keys that the user asked for (for both projects and statistics): person_name (member/manager name; multiple names use comma: "Thom,Hoàng"), team_name (multiple teams use comma: "A,B"), construction_number, branch_name, company_name, project_name, contact_name, tantou ("CAILY"|"GUIS"), overdue (true), date (YYYY-MM-DD for a single day), date_type (for relative time – see below), date_start and date_end (YYYY-MM-DD for a date range). For **search_customer** use parent_project_id (building ID), parent_project_number (building #P), or project_id (project ID when user says "khách hàng của dự án X"). **Do NOT confuse:** "Số dự án" / "dự án số X" = project ID → use search_filters.project_name. "Tòa nhà X" / "建物X" = parent_project (building) → use parent_project_id or parent_project_number. Only "số công trình", "công trình", "工事番号" use search_filters.construction_number. **date_type** for relative period: one of "yesterday", "today", "tomorrow", "this_week", "last_week", "next_week", "this_month", "last_month", "next_month". **Date range:** When user asks "trong khoảng ngày X đến Y", "from A to B", "1/1 đến 20/1", set date_start and date_end as YYYY-MM-DD (use current year if only day/month given). **Date format by language:** If user writes in Japanese, 2/3 = February 3 (month/day). If user writes in Vietnamese, 2/3 = March 2 (day/month). Example: "trong khoảng ngày 1/1 đến 20/1 có dự án nào" → search_filters: { "date_start": "2026-01-01", "date_end": "2026-01-20" }. Backend returns projects that **overlap** the range (project that starts before range end and ends after range start).
  - availability: true when user asks which **teams** or **people** are **free / not assigned to any project** in a time period. Examples: "nhóm nào tuần sau rảnh", "nhóm nào rảnh việc tuần tới", "team nào rảnh", "ai rảnh tuần sau", "người nào chưa được phân công dự án trong tuần này", "誰が空いている", "どのチームが暇". Set availability: true, and set search_filters.date_type (or date_start/date_end) for the period. Set intent "search", projects: false, statistics: false. Example: "nhóm nào tuần sau rảnh" → {"intent":"search","data_request":{"projects":false,"statistics":false,"availability":true,"limit":20,"department_id":null,"status_filter":null,"search_filters":{"date_type":"next_week"}}}.

**Navigate/open project page (ưu tiên trước "Show/list by ID"):** When the user asks to **open the page**, **go to**, or **navigate** to a project (e.g. "hãy cho xem trang dự án 23", "cho xem trang dự án 23", "di chuyển đến dự án 23", "mở trang dự án 23", "mở dự án 23", "xem trang dự án 23", "案件23のページを開いて", "dự án 23のページへ"), set intent **"action"**, projects: **false**, statistics: false, and search_filters: { "project_name": "<id>" } (e.g. "23"). The user wants to **redirect to the project page**, not to see project data in chat. Do NOT set intent "search" or projects: true for these. Example: "hãy cho xem trang dự án 23" → intent "action", projects: false, search_filters: { "project_name": "23" }.

**Show/list project by ID (chỉ khi user muốn xem thông tin trong chat):** When the user asks to show, display, or list **project information** (data) in chat BY ID (e.g. "hiển thị thông tin dự án có id 23", "thông tin dự án id 23", "project 23 info"), set intent "search", projects: true, statistics: false, and search_filters: { "project_name": "<id>" } (single id as string). **Multiple project IDs:** When the user asks about **several projects by ID** (e.g. "số tiền của dự án 18 và 17", "dự án 18 và 17", "project 18 and 17", "dự án 18, 17, 20"), set search_filters: { "project_name": "<id1>,<id2>,..." } with comma-separated IDs, no spaces (e.g. "18,17" or "18,17,20"). Do NOT set only one id; include all ids the user mentioned. Example: "số tiền của dự án 18 và 17 là bao nhiêu" → search_filters: { "project_name": "18,17" }.

**Time period (date_type) – MUST set for statistics/count by time:** "trong tuần này" / "tuần này" / "this week" → date_type: "this_week". "tuần trước" / "last week" / "先週" → "last_week". "tuần sau" / "tuần tới" / "next week" / "来週" → date_type: "next_week". "tháng này" / "this month" → "this_month". "tháng trước" / "last month" → "last_month". "tháng sau" / "next month" → "next_month". "hôm qua" / "yesterday" → "yesterday". "hôm nay" / "today" → "today". "ngày mai" / "tomorrow" → "tomorrow". Example: "trong tuần sau có mấy dự án" → intent "statistics", statistics: true, projects: false, search_filters: { "date_type": "next_week" }.

**Current page / on-screen data:** When the user asks about what is displayed ON THE CURRENT PAGE (e.g. "trên trang này", "trong trang", "đang hiển thị", "trang này có bao nhiêu", "hiện tại trên trang", "このページ", "ページに表示") set projects: false AND statistics: false. The backend will use only the page context data (what the frontend sends), NOT database statistics or project list fetch. Example: "trên trang này đang hiển thị bao nhiêu dự án" → intent "search", projects: false, statistics: false.

**"Dự án trên" / "dự án đó" (project mentioned above in the conversation):** Means the project that was previously asked about or referred to in the chat (e.g. user asked "di chuyển đến trang dự án 23" then later "dự án trên do ai tham gia"). Do NOT use intent "general". Set intent "search", projects: true, statistics: false. Leave search_filters empty (no project_name) — the backend will resolve the project id from chat history. Example: "dự án trên do ai tham gia" → {"intent":"search","data_request":{"projects":true,"statistics":false,"limit":20,"department_id":null,"status_filter":null,"search_filters":{}}}.

**"Dự án này" (this project on screen):** When the user asks about the project currently displayed on the page (e.g. "dự án này ai làm"), and [Current page context] below provides project_id, set search_filters: { "project_name": "<id>" }. Otherwise leave search_filters empty; backend may use page or history.

**Department selection (部署選択):** If the user message looks like they are SELECTING a department from a list (e.g. "ID 5: Thiết kế kiến trúc", "id 5", "ID 5:", "意匠設計", or only a department name from the list), do NOT set search_filters.project_name. project_name is for project/building name (案件名・建物名), NOT department name (部署名). Set intent "general", projects: false, statistics: false, search_filters: {} (empty). The backend will handle department selection separately.

**Do NOT use intent "general"** when the user asks for project-related data (participants, members, "dự án trên/đó/này", "project above"). Always set intent "search", projects: true; leave search_filters empty for "dự án trên"/"dự án đó" (backend fills from chat history).

{{PAGE_CONTEXT}}

Departments available to user: {{DEPARTMENTS}}

Output format (strict JSON only):
{"intent":"search","data_request":{"projects":true,"statistics":false,"limit":20,"department_id":null,"status_filter":null,"search_filters":{}}}
Example for "trong tuần này có bao nhiêu dự án": {"intent":"statistics","data_request":{"projects":false,"statistics":true,"limit":20,"department_id":null,"status_filter":null,"search_filters":{"date_type":"this_week"}}}
Example for "trên trang này đang hiển thị bao nhiêu dự án": {"intent":"search","data_request":{"projects":false,"statistics":false,"limit":20,"department_id":null,"status_filter":null,"search_filters":{}}}
Example for "hãy hiển thị thông tin dự án có id 23" or "thông tin dự án id 23": {"intent":"search","data_request":{"projects":true,"statistics":false,"limit":20,"department_id":null,"status_filter":null,"search_filters":{"project_name":"23"}}}
Example for "trong tuần sau có mấy dự án": {"intent":"statistics","data_request":{"projects":false,"statistics":true,"limit":20,"department_id":null,"status_filter":null,"search_filters":{"date_type":"next_week"}}}
Example for "dự án trên do ai tham gia" (project from conversation; backend resolves id from history): {"intent":"search","data_request":{"projects":true,"statistics":false,"limit":20,"department_id":null,"status_filter":null,"search_filters":{}}}
Example for "trong khoảng ngày 1/1 đến 20/1 có dự án nào": {"intent":"search","data_request":{"projects":true,"statistics":false,"limit":20,"department_id":null,"status_filter":null,"search_filters":{"date_start":"2026-01-01","date_end":"2026-01-20"}}}
Example for "hãy cho xem trang dự án 23" or "di chuyển đến dự án 23" (navigate to page, NOT show data in chat): {"intent":"action","data_request":{"projects":false,"statistics":false,"limit":20,"department_id":null,"status_filter":null,"search_filters":{"project_name":"23"}}}
Example for "số tiền của dự án 18 và 17 là bao nhiêu" (multiple projects by ID): {"intent":"search","data_request":{"projects":true,"statistics":false,"limit":20,"department_id":null,"status_filter":null,"search_filters":{"project_name":"18,17"}}}
Example for "tìm các dự án mà Thom và Hoàng cũng tham gia" (multiple person names): {"intent":"search","data_request":{"projects":true,"statistics":false,"limit":20,"department_id":null,"status_filter":null,"search_filters":{"person_name":"Thom,Hoàng"}}}
Example for "nhóm nào tuần sau rảnh" or "ai rảnh tuần sau" (teams/people free in period): {"intent":"search","data_request":{"projects":false,"statistics":false,"availability":true,"limit":20,"department_id":null,"status_filter":null,"search_filters":{"date_type":"next_week"}}}
Example for "cho tôi thông tin khách hàng của tòa nhà 9" or "khách hàng tòa nhà 9" (customer info of building by ID): {"intent":"search_customer","data_request":{"projects":false,"statistics":false,"customer":true,"limit":20,"department_id":null,"status_filter":null,"search_filters":{"parent_project_id":"9"}}}
Example for "thông tin khách hàng tòa nhà #P000008" (customer info of building by project_number): {"intent":"search_customer","data_request":{"projects":false,"statistics":false,"customer":true,"limit":20,"department_id":null,"status_filter":null,"search_filters":{"parent_project_number":"P000008"}}}
Example for "cho tôi thông tin khách hàng của dự án 23" or "khách hàng dự án 23" (customer info of project by project ID): {"intent":"search_customer","data_request":{"projects":false,"statistics":false,"customer":true,"limit":20,"department_id":null,"status_filter":null,"search_filters":{"project_id":"23"}}}
EOT;
        $pageContextLine = '';
        if (!empty($pageContext['project_id'])) {
            $pid = is_numeric($pageContext['project_id']) ? (string)(int)$pageContext['project_id'] : (string)$pageContext['project_id'];
            $pageContextLine = "\n[Current page context] The user is currently viewing project_id=" . $pid . ". Use this ONLY when the user says \"dự án này\" (this project on screen). Do NOT use this for \"dự án trên\" or \"dự án đó\" — those mean the project from the previous conversation; leave search_filters empty for them.";
        }
        $systemPrompt = str_replace('{{PAGE_CONTEXT}}', $pageContextLine, $systemPrompt);
        $systemPrompt = str_replace('{{DEPARTMENTS}}', json_encode($deptList, JSON_UNESCAPED_UNICODE), $systemPrompt);
        $userPrompt = "User message: " . trim($message);
        $payload = json_encode([
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $systemPrompt . "\n\n" . $userPrompt]]]
            ],
            'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 1024]
        ]);
        $headers = ['Content-Type: application/json', 'x-goog-api-key: ' . $apiKey];
        $ch = curl_init($modelUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        curl_close($ch);
        if (!$response) {
            return null;
        }
        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['candidates'][0]['content']['parts'][0]['text'])) {
            return null;
        }
        $text = trim((string) $data['candidates'][0]['content']['parts'][0]['text']);
        $text = trim(preg_replace('/^```\w*\s*|\s*```$/s', '', $text));
        $parsed = json_decode($text, true);
        if (!is_array($parsed) || !isset($parsed['intent'])) {
            $start = strpos($text, '{');
            if ($start !== false) {
                $end = strrpos($text, '}');
                if ($end !== false && $end > $start) {
                    $parsed = json_decode(substr($text, $start, $end - $start + 1), true);
                }
            }
        }
        if (!is_array($parsed) || !isset($parsed['intent'])) {
            return null;
        }
        if (isset($data['usageMetadata']) && is_array($data['usageMetadata'])) {
            $parsed['_usage_round1'] = $data['usageMetadata'];
        }
        if (!isset($parsed['data_request']) || !is_array($parsed['data_request'])) {
            $parsed['data_request'] = ['projects' => false, 'statistics' => false, 'limit' => 20, 'department_id' => null, 'status_filter' => null, 'search_filters' => []];
        }
        $dr = &$parsed['data_request'];
        if (!isset($dr['projects'])) $dr['projects'] = false;
        if (!isset($dr['statistics'])) $dr['statistics'] = false;
        if (!isset($dr['limit'])) $dr['limit'] = 20;
        if (!isset($dr['department_id'])) $dr['department_id'] = null;
        if (!isset($dr['status_filter'])) $dr['status_filter'] = null;
        if (!isset($dr['search_filters']) || !is_array($dr['search_filters'])) $dr['search_filters'] = [];
        $parsed['_raw'] = $text; // raw Gemini response for console log (Lượt 1)
        return $parsed;
    }

    /**
     * Load dữ liệu theo data_request từ Gemini (luồng 2 bước – sau lượt 1).
     */
    private function fetchDataFromDataRequest($dataRequest, $userDepts) {
        $keys = [];
        if (!empty($dataRequest['projects'])) $keys[] = 'projects';
        if (!empty($dataRequest['statistics'])) $keys[] = 'statistics';
        if (!empty($dataRequest['availability'])) $keys[] = 'availability';
        if (!empty($dataRequest['customer'])) $keys[] = 'customer';
        if (empty($keys)) {
            return ['projects' => [], 'statistics' => [], 'availability' => [], 'customer' => null];
        }
        $department_id = isset($dataRequest['department_id']) ? (int)$dataRequest['department_id'] : null;
        if ($department_id <= 0) $department_id = null;
        if ($department_id === null && count($userDepts) === 1) {
            $department_id = (int)$userDepts[0]['id'];
        }
        $searchFilters = isset($dataRequest['search_filters']) && is_array($dataRequest['search_filters']) ? $dataRequest['search_filters'] : [];
        $statusFilter = isset($dataRequest['status_filter']) && $dataRequest['status_filter'] !== '' ? $dataRequest['status_filter'] : null;
        return $this->fetchContextByKeys($keys, $department_id, $searchFilters, $statusFilter);
    }

    /**
     * Phase 2.1 – Parent project list/read for AI context.
     * Returns minimal list or single parent project for use as context sent to Gemini.
     *
     * @param array $params ['department_id' => int, 'status' => string, 'limit' => int, 'id' => int]
     * @return array
     */
    public function getParentProjectsForContext($params = []) {
        if (!class_exists('ParentProject')) {
            require_once DIR_MODEL . 'parentproject.php';
        }
        $parentProject = new ParentProject();
        return $parentProject->getForAiContext($params);
    }

    /**
     * Phase 2.2 – Project list/read for AI context.
     * Returns minimal list or single project for use as context sent to Gemini.
     *
     * @param array $params ['department_id' => int, 'status' => string, 'limit' => int, 'id' => int]
     * @return array
     */
    public function getProjectsForContext($params = []) {
        if (!class_exists('Project')) {
            require_once DIR_MODEL . 'project.php';
        }
        $project = new Project();
        return $project->getForAiContext($params);
    }

    /**
     * Phase 2.3 – Task list/read for AI context.
     * Returns tasks for a project when the user is allowed to see that project (permission check inside Task::getForAiContext).
     *
     * @param int $project_id
     * @param array $params ['limit' => int, 'include_subtasks' => bool]
     * @return array
     */
    public function getTasksForContext($project_id, $params = []) {
        if (!class_exists('Task')) {
            require_once DIR_MODEL . 'task.php';
        }
        $task = new Task();
        return $task->getForAiContext($project_id, $params);
    }

    /**
     * Phase 2.4 – Statistics aggregation for AI.
     * Returns project and task statistics (overview, by_department, task by_status, overdue_count) for use as context sent to Gemini.
     *
     * @param int|null $department_id optional filter
     * @param string|null $statusFilter optional status filter (e.g., 'in_progress')
     * @return array ['projects' => [...], 'tasks' => [...]]
     */
    public function getStatisticsForContext($department_id = null, $statusFilter = null, $searchFilters = []) {
        if (!class_exists('Project')) {
            require_once DIR_MODEL . 'project.php';
        }
        if (!class_exists('Task')) {
            require_once DIR_MODEL . 'task.php';
        }
        $project = new Project();
        $task = new Task();
        return [
            'projects' => $project->getStatsForAiContext($department_id, $statusFilter, $searchFilters),
            'tasks' => $task->getStatsForAiContext($department_id), // Task stats không filter theo project status
        ];
    }

    public function index() {
        $method = isset($_GET["method"]) ? $_GET["method"] : '';
        if ($method == "chat") {
            $this->chat();
        } elseif ($method == "get_chat_history") {
            $this->getChatHistory();
        } elseif ($method == "execute_action") {
            $this->executeAction();
        } elseif ($method == "scheduling_suggestion") {
            $this->schedulingSuggestion();
        } elseif ($method == "assignment_suggestion") {
            $this->assignmentSuggestion();
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Method not found']);
        }
    }

    /**
     * Get user name (realname) from action params. Tries user_name, realname, name, member_name, user.
     * Returns non-empty string or ''.
     */
    private function getUserNameFromParams($params) {
        if (!is_array($params)) return '';
        $keys = ['user_name', 'realname', 'name', 'member_name', 'user'];
        foreach ($keys as $k) {
            if (isset($params[$k])) {
                $v = trim((string) $params[$k]);
                if ($v !== '') return $v;
            }
        }
        return '';
    }

    /**
     * Resolve user_id from realname/user_name. If project_department_id given, prefer users in that department.
     * Returns [user_id, null] or [0, error_message].
     */
    private function resolveUserIdFromName($name, $project_department_id = null) {
        $name = trim((string) $name);
        if ($name === '') {
            return [0, 'user_name required'];
        }
        $this->connect();
        // quote() only escapes; wrap in single quotes for SQL string literal
        $esc = "'" . $this->quote($name) . "'";
        $dept_cond = '';
        if ($project_department_id > 0) {
            $dept_cond = " AND u.userid IN (SELECT userid FROM " . DB_PREFIX . "user_department WHERE department_id = " . intval($project_department_id) . ")";
        }
        $exact = $this->fetchOne("SELECT u.id FROM " . DB_PREFIX . "user u WHERE u.realname = " . $esc . $dept_cond . " LIMIT 1");
        if ($exact && isset($exact['id'])) {
            return [(int)$exact['id'], null];
        }
        $like = "'%" . $this->quote($name) . "%'";
        $row = $this->fetchOne("SELECT u.id FROM " . DB_PREFIX . "user u WHERE u.realname LIKE " . $like . $dept_cond . " LIMIT 1");
        if ($row && isset($row['id'])) {
            return [(int)$row['id'], null];
        }
        return [0, 'User not found or not in project department: ' . $name];
    }

    /**
     * Get team name from action params. Tries team_name, team, name.
     * Returns non-empty string or ''.
     */
    private function getTeamNameFromParams($params) {
        if (!is_array($params)) return '';
        $keys = ['team_name', 'team', 'name'];
        foreach ($keys as $k) {
            if (isset($params[$k])) {
                $v = trim((string) $params[$k]);
                if ($v !== '') return $v;
            }
        }
        return '';
    }

    /**
     * Resolve team_id from team name. Search in same department (LIKE). Returns [team_id, null] or [0, error_message].
     */
    private function resolveTeamIdFromName($name, $department_id) {
        $name = trim((string) $name);
        if ($name === '') {
            return [0, 'team_name required'];
        }
        $dept_id = intval($department_id);
        if ($dept_id <= 0) {
            return [0, 'Department required to resolve team by name'];
        }
        $this->connect();
        $esc = "'" . $this->quote($name) . "'";
        $dept_cond = " AND department_id = " . $dept_id . " AND is_active = 1";
        $exact = $this->fetchOne("SELECT id FROM " . DB_PREFIX . "team WHERE name = " . $esc . $dept_cond . " LIMIT 1");
        if ($exact && isset($exact['id'])) {
            return [(int)$exact['id'], null];
        }
        $like = "'%" . $this->quote($name) . "%'";
        $row = $this->fetchOne("SELECT id FROM " . DB_PREFIX . "team WHERE name LIKE " . $like . $dept_cond . " LIMIT 1");
        if ($row && isset($row['id'])) {
            return [(int)$row['id'], null];
        }
        return [0, 'Team not found in project department: ' . $name];
    }

    /**
     * Lấy nội dung tin nhắn model (AI) gần nhất trong chat history.
     * @return string
     */
    private function getLastModelMessageFromHistory() {
        for ($i = count($this->chatHistory) - 1; $i >= 0; $i--) {
            $item = $this->chatHistory[$i];
            if (isset($item['role']) && $item['role'] === 'model' && isset($item['content'])) {
                if (is_string($item['content'])) {
                    return trim((string) $item['content']);
                }
                if (is_array($item['content'])) {
                    $text = '';
                    foreach ($item['content'] as $part) {
                        if (isset($part['text'])) {
                            $text .= $part['text'];
                        }
                    }
                    return trim($text);
                }
            }
        }
        return '';
    }

    /**
     * Lấy nội dung tin nhắn user gần nhất trong chat history (lượt trước, chưa tính tin nhắn hiện tại).
     * Dùng khi user trả lời ngắn ("có") để lấy câu hỏi gốc (vd. "di chuyển đến trang dự án 23") và fetch project.
     * @return string
     */
    private function getLastUserMessageFromHistory() {
        for ($i = count($this->chatHistory) - 1; $i >= 0; $i--) {
            $item = $this->chatHistory[$i];
            if (isset($item['role']) && $item['role'] === 'user') {
                if (isset($item['content']['text'])) {
                    return trim((string) $item['content']['text']);
                }
                if (is_array($item['content']) && isset($item['content'][0]['text'])) {
                    return trim((string) $item['content'][0]['text']);
                }
                return '';
            }
        }
        return '';
    }

    /**
     * Extract project id from message (e.g. "dự án 22", "project 22", "案件22", "dự án này").
     * Returns 0 or positive int.
     * Nếu detect "dự án này", tìm project_id từ chat history (từ các ACTION trước đó).
     */
    private function getProjectIdFromMessage($message) {
        $msg = ' ' . trim((string) $message) . ' ';
        
        // Detect số ID trực tiếp: "dự án 22", "project 22", "案件22", "dự án ID 23", "dự án số 23"
        if (preg_match('/\b(?:dự án|du an|project|案件|プロジェクト)\s+(?:ID|id|số|so)\s*[#=:]?\s*(\d+)\b/ui', $msg, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\b(?:dự án|du an|project|案件|プロジェクト)\s*[#:]?\s*(\d+)\b/ui', $msg, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\bid\s*[#=:]?\s*(\d+)\b/ui', $msg, $m)) {
            return (int) $m[1];
        }
        
        // Detect "dự án này", "案件この", "this project"
        if (preg_match('/\b(?:dự án|du an|project|案件|プロジェクト)\s+(?:này|nay|この|this)\b/ui', $msg, $m)) {
            // Tìm project_id từ chat history (từ các ACTION trước đó)
            if (!empty($this->chatHistory)) {
                // Tìm ngược từ cuối lên đầu
                for ($i = count($this->chatHistory) - 1; $i >= 0; $i--) {
                    $item = $this->chatHistory[$i];
                    if (isset($item['role']) && $item['role'] === 'model') {
                        // Parse content để tìm ACTION với project_id
                        $content = is_string($item['content']) ? $item['content'] : (isset($item['content']['text']) ? $item['content']['text'] : '');
                        if (preg_match('/ACTION:\s*\{[^}]*"project_id"\s*:\s*(\d+)/i', $content, $m)) {
                            return (int) $m[1];
                        }
                        if (preg_match('/"project_id"\s*:\s*(\d+)/i', $content, $m)) {
                            return (int) $m[1];
                        }
                        if (preg_match('/"id"\s*:\s*(\d+)/i', $content, $m)) {
                            return (int) $m[1];
                        }
                    }
                }
            }
        }
        
        return 0;
    }

    /**
     * Chuẩn hóa chuỗi ngày/giờ từ Gemini, giữ phần time nếu có.
     * @param string $raw "YYYY-MM-DD", "YYYY-MM-DD HH:mm", "DD/MM/YYYY", ...
     * @param string $defaultToday ngày mặc định nếu parse thất bại
     * @param string $defaultTime "HH:mm" khi raw chỉ có ngày (vd "09:00", "17:00")
     * @return string "YYYY-MM-DD" hoặc "YYYY-MM-DD HH:mm"
     */
    private function normalizeTaskFormDate($raw, $defaultToday = '', $defaultTime = '') {
        $s = trim((string)$raw);
        if ($s === '') {
            return $defaultToday . ($defaultTime !== '' ? ' ' . $defaultTime : '');
        }
        $datePart = '';
        $timePart = '';
        if (preg_match('/^(.+?)\s+(\d{1,2}:\d{2}(?::\d{2})?)\s*$/', $s, $m)) {
            $datePart = trim($m[1]);
            $t = $m[2];
            $timePart = (preg_match('/^\d{1,2}:\d{2}$/', $t)) ? $t : substr($t, 0, 5);
        } else {
            $datePart = preg_replace('/\s.*$/', '', $s);
        }
        if ($datePart === '') {
            return $defaultToday . ($defaultTime !== '' ? ' ' . $defaultTime : '');
        }
        $normDate = '';
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $datePart, $m)) {
            $normDate = $datePart;
        } elseif (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $datePart, $m)) {
            $d = (int)$m[1];
            $mo = (int)$m[2];
            $y = (int)$m[3];
            if ($d >= 1 && $d <= 31 && $mo >= 1 && $mo <= 12) {
                $normDate = sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
        } elseif (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2})$/', $datePart, $m)) {
            $d = (int)$m[1];
            $mo = (int)$m[2];
            $y = (int)$m[3];
            $y += ($y < 100) ? ($y < 50 ? 2000 : 1900) : 0;
            if ($d >= 1 && $d <= 31 && $mo >= 1 && $mo <= 12) {
                $normDate = sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
        }
        if ($normDate === '') {
            $ts = strtotime($datePart);
            $normDate = ($ts !== false) ? date('Y-m-d', $ts) : $defaultToday;
        }
        if ($normDate === '') {
            return $defaultToday . ($defaultTime !== '' ? ' ' . $defaultTime : '');
        }
        if ($timePart !== '') {
            return $normDate . ' ' . $timePart;
        }
        if ($defaultTime !== '') {
            return $normDate . ' ' . $defaultTime;
        }
        return $normDate;
    }

    /**
     * Gọi Gemini trích xuất thông tin task từ câu user (tiêu đề, ngày giờ, phân công, v.v.) để điền sẵn form.
     * Trả về mảng chỉ chứa key có giá trị: title, start_date, due_date, assigned_to, primary_assignee, priority.
     * @param string $message Câu user (vd: "tạo task dự án 23, tên Sửa bản vẽ A, từ bây giờ đến 5h chiều, cho chính tôi")
     * @return array ['title'=>'...', 'start_date'=>'YYYY-MM-DD', 'due_date'=>'YYYY-MM-DD', 'assigned_to'=>'...', 'primary_assignee'=>true, 'priority'=>'...']
     */
    private function extractTaskInitialValues($message) {
        $apiKey = $this->api_key;
        if (!$apiKey || trim((string)$message) === '') {
            return [];
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Ho_Chi_Minh'));
        $today = $now->format('Y-m-d');
        $currentTime = $now->format('H:i');
        $modelUrl = "https://generativelanguage.googleapis.com/v1beta/models/" . $this->ai_model . ":generateContent";
        $systemPrompt = <<<EOT
You are an extractor for a task creation form. Output ONLY a single JSON object, no markdown, no explanation.

From the user message, extract task fields. **You MUST use the current context below for all relative times. Do not use any other date for "today".**
Current date (use exactly this for "bây giờ", "now", "hôm nay", "today"): {$today}
Current time (24h): {$currentTime}

Output keys (use empty string "" or false when not mentioned):
- title: task name/title (e.g. "Sửa bản vẽ A"). Required if user gave a name.
- start_date: date in YYYY-MM-DD or datetime YYYY-MM-DD HH:mm. **Must be {$today}** for "bây giờ", "now", "hôm nay", "today", "từ bây giờ". Include time if user said "bây giờ" (use {$currentTime}) or specified time.
- due_date: date in YYYY-MM-DD or datetime YYYY-MM-DD HH:mm. **Must be {$today}** for "đến 5h chiều" / "until 5pm"; "5h chiều" = 17:00 so output "{$today} 17:00".
- start_time: optional, "HH:mm" if user specified start time (e.g. "from 9am"). Else use {$currentTime} for "bây giờ".
- due_time: optional, "HH:mm". "5h chiều" / "5pm" = "17:00", "chiều" = 17:00.
- assigned_to: comma-separated person names if user mentioned who to assign (excluding "chính tôi"/"me"). Empty string if only "cho chính tôi".
- primary_assignee: true if user said "cho chính tôi", "phân công chính tôi", "assign to me", "cho tôi", "tạo cho tôi"; false otherwise.
- priority: one of "low","medium","high","urgent" only if user mentioned priority; otherwise empty string.

Date format: Vietnamese 2/3 = day 2 month 3 (March 2). Japanese 2/3 = month 2 day 3 (Feb 3). **Always output dates as YYYY-MM-DD only** (e.g. {$today}).
Output must be valid JSON with exactly these keys when present: title, start_date, due_date, assigned_to, primary_assignee (boolean), priority.
EOT;
        $userPrompt = "User message: " . trim($message);
        $payload = json_encode([
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $systemPrompt . "\n\n" . $userPrompt]]]
            ],
            'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 512]
        ]);
        $headers = ['Content-Type: application/json', 'x-goog-api-key: ' . $apiKey];
        $ch = curl_init($modelUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        curl_close($ch);
        if (!$response) {
            return [];
        }
        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['candidates'][0]['content']['parts'][0]['text'])) {
            return [];
        }
        $text = trim((string) $data['candidates'][0]['content']['parts'][0]['text']);
        $text = trim(preg_replace('/^```\w*\s*|\s*```$/s', '', $text));
        $parsed = json_decode($text, true);
        if (!is_array($parsed)) {
            $start = strpos($text, '{');
            if ($start !== false) {
                $end = strrpos($text, '}');
                if ($end !== false && $end > $start) {
                    $parsed = json_decode(substr($text, $start, $end - $start + 1), true);
                }
            }
        }
        if (!is_array($parsed)) {
            return [];
        }
        $out = [];
        // Title: chấp nhận title, task_title, name
        $title = isset($parsed['title']) ? trim((string)$parsed['title']) : (isset($parsed['task_title']) ? trim((string)$parsed['task_title']) : (isset($parsed['name']) ? trim((string)$parsed['name']) : ''));
        if ($title !== '') {
            $out['title'] = $title;
        }
        // Chuẩn hóa date + time, giữ phần giờ nếu có
        $rawStart = isset($parsed['start_date']) ? trim((string)$parsed['start_date']) : '';
        if (isset($parsed['start_time']) && trim((string)$parsed['start_time']) !== '') {
            $rawStart = $rawStart !== '' ? $rawStart . ' ' . trim((string)$parsed['start_time']) : $today . ' ' . trim((string)$parsed['start_time']);
        }
        if ($rawStart !== '') {
            $out['start_date'] = $this->normalizeTaskFormDate($rawStart, $today, '09:00');
        }
        $rawDue = isset($parsed['due_date']) ? trim((string)$parsed['due_date']) : (isset($parsed['end_date']) ? trim((string)$parsed['end_date']) : '');
        if (isset($parsed['due_time']) && trim((string)$parsed['due_time']) !== '') {
            $rawDue = $rawDue !== '' ? $rawDue . ' ' . trim((string)$parsed['due_time']) : $today . ' ' . trim((string)$parsed['due_time']);
        }
        if ($rawDue !== '') {
            $out['due_date'] = $this->normalizeTaskFormDate($rawDue, $today, '17:00');
        }
        if (isset($parsed['assigned_to']) && trim((string)$parsed['assigned_to']) !== '') {
            $out['assigned_to'] = trim((string)$parsed['assigned_to']);
        }
        if (!empty($parsed['primary_assignee']) || (isset($parsed['primary_assignee']) && (string)$parsed['primary_assignee'] === 'true')) {
            $out['primary_assignee'] = true;
        }
        $pri = isset($parsed['priority']) ? trim((string)$parsed['priority']) : '';
        if ($pri !== '' && in_array($pri, ['low', 'medium', 'high', 'urgent'], true)) {
            $out['priority'] = $pri;
        }
        // Ép dùng ngày hệ thống khi user nói "bây giờ", "hôm nay", "5h chiều" (tránh Gemini trả ngày sai)
        $msgLower = mb_strtolower(trim((string)$message));
        $impliesToday = (mb_strpos($msgLower, 'bây giờ') !== false || mb_strpos($msgLower, 'hôm nay') !== false || mb_strpos($msgLower, 'today') !== false
            || mb_strpos($msgLower, 'chiều') !== false || preg_match('/\d+h\s*chiều|\d+\s*giờ\s*chiều/', $msgLower)
            || mb_strpos($msgLower, 'từ bây giờ') !== false);
        if ($impliesToday) {
            if (empty($out['start_date']) || (strcmp($out['start_date'], $today) < 0)) {
                $out['start_date'] = $today;
            }
            if (empty($out['due_date']) || (strcmp($out['due_date'], $today) < 0)) {
                $out['due_date'] = $today;
            }
        }
        return $out;
    }

    /**
     * Get teams (id, name) in the same department as the project. For context so Gemini can confirm exact team name.
     * @param int $project_id
     * @return array [['id'=>n,'name'=>'...'], ...]
     */
    private function getTeamsForProjectContext($project_id) {
        $project_id = intval($project_id);
        if ($project_id <= 0) return [];
        $this->connect();
        $proj = $this->fetchOne("SELECT department_id FROM " . DB_PREFIX . "projects WHERE id = " . $project_id);
        if (!$proj || empty($proj['department_id'])) return [];
        $dept_id = (int) $proj['department_id'];
        $rows = $this->fetchAll("SELECT id, name FROM " . DB_PREFIX . "team WHERE department_id = " . $dept_id . " AND is_active = 1 ORDER BY name ASC");
        return is_array($rows) ? $rows : [];
    }

    /**
     * Validate that all team_ids belong to the same department as the project. Returns [true] or [false, error].
     */
    private function validateTeamsInProjectDepartment(array $team_ids, $project_department_id) {
        $dept_id = intval($project_department_id);
        if ($dept_id <= 0) {
            return [false, 'Project has no department'];
        }
        $this->connect();
        foreach ($team_ids as $tid) {
            $tid = intval($tid);
            if ($tid <= 0) {
                continue;
            }
            $row = $this->fetchOne("SELECT department_id FROM " . DB_PREFIX . "team WHERE id = " . $tid);
            if (!$row || (int)$row['department_id'] !== $dept_id) {
                return [false, 'Team must belong to project department (same department as project)'];
            }
        }
        return [true];
    }

    /**
     * Build links array for execute_action response. Phân loại đúng: task → task.php, project → detail.php.
     * @param int $projectId
     * @param int|null $taskId
     * @param string $actionType e.g. task_create, task_update, project_update
     * @return array [['label'=>'...', 'url'=>'...', 'type'=>'project'|'task'], ...]
     */
    private function buildActionLinks($projectId, $taskId = null, $actionType = '') {
        $links = [];
        if ($projectId <= 0) {
            return $links;
        }
        $isTaskAction = ($actionType === 'task_create' || $actionType === 'task_update');
        if ($isTaskAction && $taskId > 0) {
            $links[] = [
                'label' => 'Trang task (Task ' . (int) $taskId . ')',
                'url'   => '/project/task.php?project_id=' . (int) $projectId,
                'type'  => 'task'
            ];
        }
        $links[] = [
            'label' => 'Trang dự án ' . (int) $projectId,
            'url'   => '/project/detail.php?id=' . (int) $projectId,
            'type'  => 'project'
        ];
        return $links;
    }

    /**
     * Execute a pending action (after user confirmation). 
     * POST body: { "action": { "type": "...", "id"?: N, "params": {...} } } 
     * OR { "actions": [{ "type": "...", ... }, { "type": "...", ... }] } for multiple actions
     */
    public function executeAction() {
        header('Content-Type: application/json; charset=utf-8');
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        
        // Support both single action and multiple actions
        $actions = [];
        if (isset($input['actions']) && is_array($input['actions'])) {
            // Multiple actions
            $actions = $input['actions'];
        } elseif (isset($input['action']) && is_array($input['action'])) {
            // Single action (backward compatible)
            $actions = [$input['action']];
        }
        
        if (empty($actions)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => 'action or actions required'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // Execute actions sequentially
        $results = [];
        $allSuccess = true;
        $lastError = null;
        
        foreach ($actions as $actionIndex => $action) {
            if (!is_array($action) || empty($action['type'])) {
                $results[] = ['status' => 'error', 'error' => 'action.type required', 'action_index' => $actionIndex];
                $allSuccess = false;
                continue;
            }
            
            $type = $action['type'];
            $params = isset($action['params']) ? $action['params'] : [];
            $id = isset($action['id']) ? intval($action['id']) : (isset($action['project_id']) ? intval($action['project_id']) : 0);
            // For task_update: id/project_id may be inside params (e.g. params.id, params.task_id, params.project_id)
            if ($type === 'task_update' && is_array($params)) {
                if (!$id && (isset($params['id']) || isset($params['task_id']))) {
                    $id = isset($params['id']) ? intval($params['id']) : intval($params['task_id']);
                }
                if (empty($params['project_id']) && !empty($action['project_id'])) {
                    $params['project_id'] = $action['project_id'];
                }
            }

            $_POST = is_array($params) ? $params : [];
            if ($id > 0) {
                $_POST['id'] = $id;
            }
            $_POST['_ai_triggered'] = 1; // Flag for Project/Task update logging

            $result = null;
        switch ($type) {
            case 'parent_project_create':
                if (!class_exists('ParentProject')) {
                    require_once DIR_MODEL . 'parentproject.php';
                }
                $m = new ParentProject();
                $result = $m->create(null);
                break;
            case 'parent_project_update':
                if (!$id && !empty($params['project_id'])) {
                    $pid = (int) $params['project_id'];
                    if ($pid > 0) {
                        if (!class_exists('Project')) {
                            require_once DIR_MODEL . 'project.php';
                        }
                        $p = new Project();
                        $proj = $p->getById($pid);
                        if ($proj && !empty($proj['parent_project_id'])) {
                            $id = (int) $proj['parent_project_id'];
                            $_POST['id'] = $id;
                        }
                    }
                }
                if (!$id && !empty($params['project_number'])) {
                    $pn = trim((string) $params['project_number']);
                    if ($pn !== '') {
                        if (!class_exists('ParentProject')) {
                            require_once DIR_MODEL . 'parentproject.php';
                        }
                        $mParent = new ParentProject();
                        $pp = $mParent->getByProjectNumber($pn);
                        if ($pp && !empty($pp['id'])) {
                            $id = (int) $pp['id'];
                            $_POST['id'] = $id;
                        }
                    }
                }
                if (!$id) {
                    $result = ['status' => 'error', 'error' => 'id or params.project_id or params.project_number (to resolve parent_project) required'];
                    break;
                }
                // Cấm cập nhật project_number (mã tòa nhà) — chỉ dùng để resolve id
                unset($_POST['project_number']);
                if (!class_exists('ParentProject')) {
                    require_once DIR_MODEL . 'parentproject.php';
                }
                $m = new ParentProject();
                $result = $m->update(null);
                break;
            case 'project_create':
                if (!class_exists('Project')) {
                    require_once DIR_MODEL . 'project.php';
                }
                $m = new Project();
                $result = $m->create(null);
                break;
            case 'project_update':
            case 'update_project_status':
            case 'update_project_order_type':
                if (!$id) {
                    $result = ['status' => 'error', 'error' => 'id required'];
                    break;
                }
                if (!class_exists('Project')) {
                    require_once DIR_MODEL . 'project.php';
                }
                $m = new Project();
                // Tất cả các update đều dùng update() method để có thể dùng chung notification
                if ($type === 'update_project_status') {
                    $status = isset($params['status']) ? $params['status'] : (isset($action['status']) ? $action['status'] : '');
                    if ($status === '') {
                        $result = ['status' => 'error', 'error' => 'status required'];
                    } else {
                        // Dùng update() thay vì updateStatusForAi() để có notification
                        $_POST['status'] = $status;
                        $result = $m->update();
                    }
                } elseif ($type === 'update_project_order_type') {
                    $orderType = isset($params['project_order_type']) ? trim((string) $params['project_order_type']) : '';
                    if ($orderType === '') {
                        $result = ['status' => 'error', 'error' => 'project_order_type required'];
                    } else {
                        // Dùng update() thay vì updateProjectOrderTypeForAi() để có notification
                        $_POST['project_order_type'] = $orderType;
                        $result = $m->update();
                    }
                } else {
                    $result = $m->update();
                }
                break;
            case 'task_create':
                if (empty($_POST['created_by']) && (isset($_SESSION['id']) || isset($_SESSION['user_id']))) {
                    $_POST['created_by'] = isset($_SESSION['id']) ? (int)$_SESSION['id'] : (int)$_SESSION['user_id'];
                }
                if (!class_exists('Task')) {
                    require_once DIR_MODEL . 'task.php';
                }
                $m = new Task();
                $result = $m->add();
                break;
            case 'task_update':
                if (!$id) {
                    $result = ['status' => 'error', 'message' => 'Task id required'];
                    break;
                }
                if (!class_exists('Task')) {
                    require_once DIR_MODEL . 'task.php';
                }
                $m = new Task();
                $result = $m->edit();
                break;
            case 'project_add_manager':
            case 'project_add_member':
                $pid = isset($params['project_id']) ? intval($params['project_id']) : $id;
                $uid = isset($params['user_id']) ? intval($params['user_id']) : 0;
                if (!$pid) {
                    $result = ['status' => 'error', 'message' => 'project_id required'];
                    break;
                }
                if (!$uid) {
                    $userName = $this->getUserNameFromParams($params);
                    if ($userName === '') {
                        $result = ['status' => 'error', 'message' => 'user_id or user_name/realname required'];
                        break;
                    }
                    if (!class_exists('Project')) {
                        require_once DIR_MODEL . 'project.php';
                    }
                    $mProj = new Project();
                    $proj = $mProj->getById($pid);
                    $dept_id = isset($proj['department_id']) ? (int)$proj['department_id'] : null;
                    list($uid, $err) = $this->resolveUserIdFromName($userName, $dept_id);
                    if ($uid <= 0) {
                        $result = ['status' => 'error', 'message' => $err];
                        break;
                    }
                }
                if (!class_exists('Project')) {
                    require_once DIR_MODEL . 'project.php';
                }
                $m = new Project();
                $result = $m->addMemberApi(['project_id' => $pid, 'user_id' => $uid, 'role' => $type === 'project_add_manager' ? 'manager' : 'member']);
                break;
            case 'project_add_team_members':
                $pid = isset($params['project_id']) ? intval($params['project_id']) : $id;
                $tid = isset($params['team_id']) ? intval($params['team_id']) : 0;
                if (!$pid) {
                    $result = ['status' => 'error', 'error' => 'project_id required'];
                    break;
                }
                if (!class_exists('Project')) {
                    require_once DIR_MODEL . 'project.php';
                }
                $m = new Project();
                if (!$m->canUserEditProject($pid)) {
                    $result = ['status' => 'error', 'message' => 'Forbidden', 'http_status' => 403];
                    break;
                }
                // Resolve team_name thành team_id nếu cần
                if (!$tid) {
                    $teamName = $this->getTeamNameFromParams($params);
                    if ($teamName === '') {
                        $result = ['status' => 'error', 'error' => 'team_id or team_name required'];
                        break;
                    }
                    $proj = $m->getById($pid);
                    $dept_id = isset($proj['department_id']) ? (int)$proj['department_id'] : 0;
                    list($tid, $err) = $this->resolveTeamIdFromName($teamName, $dept_id);
                    if ($tid <= 0) {
                        $result = ['status' => 'error', 'message' => $err];
                        break;
                    }
                }
                // Lấy danh sách user_id từ team_members
                $this->connect();
                $teamMembers = $this->fetchAll("SELECT user_id FROM " . DB_PREFIX . "team_members WHERE team_id = " . intval($tid));
                if (empty($teamMembers)) {
                    $result = ['status' => 'error', 'message' => 'Team has no members'];
                    break;
                }
                // Lấy userid (string) cho từng user_id để truyền vào addMember
                $userIds = array_map(function($tm) { return (int)$tm['user_id']; }, $teamMembers);
                $placeholders = str_repeat('%d,', count($userIds) - 1) . '%d';
                $users = $this->fetchAll(sprintf("SELECT id, userid FROM " . DB_PREFIX . "user WHERE id IN ($placeholders)", ...$userIds));
                $userMap = [];
                foreach ($users as $u) {
                    $userMap[(int)$u['id']] = $u['userid'];
                }
                // Thêm từng thành viên vào project với role = 'member'
                $addedCount = 0;
                $errors = [];
                foreach ($userIds as $uid) {
                    $userid = isset($userMap[$uid]) ? $userMap[$uid] : '';
                    $res = $m->addMemberApi(['project_id' => $pid, 'user_id' => $uid, 'role' => 'member']);
                    if (isset($res['status']) && $res['status'] === 'success') {
                        $addedCount++;
                    } else {
                        $errors[] = "User ID $uid: " . (isset($res['message']) ? $res['message'] : 'Failed');
                    }
                }
                if ($addedCount > 0) {
                    $result = ['status' => 'success', 'message' => "Added $addedCount team member(s) to project", 'added_count' => $addedCount];
                    if (!empty($errors)) {
                        $result['errors'] = $errors;
                    }
                } else {
                    $result = ['status' => 'error', 'message' => 'Failed to add team members', 'errors' => $errors];
                }
                break;
            case 'project_remove_manager':
            case 'project_remove_member':
                $pid = isset($params['project_id']) ? intval($params['project_id']) : $id;
                $uid = isset($params['user_id']) ? intval($params['user_id']) : 0;
                if (!$pid) {
                    $result = ['status' => 'error', 'message' => 'project_id required'];
                    break;
                }
                if (!$uid) {
                    $userName = $this->getUserNameFromParams($params);
                    if ($userName === '') {
                        $result = ['status' => 'error', 'message' => 'user_id or user_name/realname required'];
                        break;
                    }
                    if (!class_exists('Project')) {
                        require_once DIR_MODEL . 'project.php';
                    }
                    $mProj = new Project();
                    $proj = $mProj->getById($pid);
                    $dept_id = isset($proj['department_id']) ? (int)$proj['department_id'] : null;
                    list($uid, $err) = $this->resolveUserIdFromName($userName, $dept_id);
                    if ($uid <= 0) {
                        $result = ['status' => 'error', 'message' => $err];
                        break;
                    }
                }
                if (!class_exists('Project')) {
                    require_once DIR_MODEL . 'project.php';
                }
                $m = new Project();
                if (!$m->canUserEditProject($pid)) {
                    $result = ['status' => 'error', 'message' => 'Forbidden', 'http_status' => 403];
                    break;
                }
                $_POST['_ai_triggered'] = true; // Để Project::removeMember() ghi project log với nhãn [AI]
                $ok = $m->removeMember($pid, $uid);
                $result = $ok ? ['status' => 'success', 'message' => 'Removed'] : ['status' => 'error', 'error' => 'Remove failed'];
                break;
            case 'project_set_teams':
                $pid = isset($params['project_id']) ? intval($params['project_id']) : $id;
                $teams = isset($params['teams']) ? $params['teams'] : '';
                if (!$pid) {
                    $result = ['status' => 'error', 'error' => 'project_id required'];
                    break;
                }
                if (!class_exists('Project')) {
                    require_once DIR_MODEL . 'project.php';
                }
                $m = new Project();
                if (!$m->canUserEditProject($pid)) {
                    $result = ['status' => 'error', 'message' => 'Forbidden', 'http_status' => 403];
                    break;
                }
                $proj = $m->getById($pid);
                $dept_id = isset($proj['department_id']) ? (int)$proj['department_id'] : 0;
                $team_ids = array_filter(array_map('intval', is_array($teams) ? $teams : explode(',', (string)$teams)));
                if (!empty($team_ids)) {
                    $valid = $this->validateTeamsInProjectDepartment($team_ids, $dept_id);
                    if (!$valid[0]) {
                        $result = ['status' => 'error', 'message' => $valid[1]];
                        break;
                    }
                }
                // Dùng update() thay vì updateTeams() để có notification
                $_POST['id'] = $pid;
                $_POST['teams'] = is_array($teams) ? implode(',', array_map('intval', $teams)) : (string) $teams;
                $result = $m->update();
                break;
            case 'project_add_team':
            case 'project_remove_team':
                $pid = isset($params['project_id']) ? intval($params['project_id']) : $id;
                $tid = isset($params['team_id']) ? intval($params['team_id']) : 0;
                if (!$pid) {
                    $result = ['status' => 'error', 'error' => 'project_id required'];
                    break;
                }
                if (!$tid) {
                    $teamName = $this->getTeamNameFromParams($params);
                    if ($teamName === '') {
                        $result = ['status' => 'error', 'error' => 'team_id or team_name required'];
                        break;
                    }
                    if (!class_exists('Project')) {
                        require_once DIR_MODEL . 'project.php';
                    }
                    $mProj = new Project();
                    $projForDept = $mProj->getById($pid);
                    $dept_id = isset($projForDept['department_id']) ? (int)$projForDept['department_id'] : 0;
                    list($tid, $err) = $this->resolveTeamIdFromName($teamName, $dept_id);
                    if ($tid <= 0) {
                        $result = ['status' => 'error', 'message' => $err];
                        break;
                    }
                }
                if (!class_exists('Project')) {
                    require_once DIR_MODEL . 'project.php';
                }
                $m = new Project();
                if (!$m->canUserEditProject($pid)) {
                    $result = ['status' => 'error', 'message' => 'Forbidden', 'http_status' => 403];
                    break;
                }
                $proj = $m->getById($pid);
                if ($type === 'project_add_team') {
                    $dept_id = isset($proj['department_id']) ? (int)$proj['department_id'] : 0;
                    $valid = $this->validateTeamsInProjectDepartment([$tid], $dept_id);
                    if (!$valid[0]) {
                        $result = ['status' => 'error', 'message' => $valid[1]];
                        break;
                    }
                }
                $current = isset($proj['teams']) ? trim((string) $proj['teams']) : '';
                $arr = $current === '' ? [] : array_map('intval', array_filter(explode(',', $current)));
                if ($type === 'project_add_team') {
                    if (!in_array($tid, $arr, true)) {
                        $arr[] = $tid;
                    }
                } else {
                    $arr = array_values(array_filter($arr, function ($x) use ($tid) { return $x !== $tid; }));
                }
                // Dùng update() thay vì updateTeams() để có notification và project log (logProjectUpdateByField)
                $_POST['_ai_triggered'] = true; // Để Project::update() ghi project log với nhãn [AI]
                $_POST['id'] = $pid;
                $_POST['teams'] = implode(',', $arr);
                $result = $m->update();
                break;
            case 'project_clear_members':
                $pid = isset($params['project_id']) ? intval($params['project_id']) : $id;
                if (!$pid) {
                    $result = ['status' => 'error', 'error' => 'project_id required'];
                    break;
                }
                if (!class_exists('Project')) {
                    require_once DIR_MODEL . 'project.php';
                }
                $m = new Project();
                if (!$m->canUserEditProject($pid)) {
                    $result = ['status' => 'error', 'message' => 'Forbidden', 'http_status' => 403];
                    break;
                }
                // Xóa tất cả members (role = 'member'), giữ lại managers
                $this->connect();
                $this->query("DELETE FROM " . DB_PREFIX . "project_members WHERE project_id = " . intval($pid) . " AND role = 'member'");
                $result = ['status' => 'success', 'message' => 'All members cleared'];
                break;
            case 'project_clear_managers':
                $pid = isset($params['project_id']) ? intval($params['project_id']) : $id;
                if (!$pid) {
                    $result = ['status' => 'error', 'error' => 'project_id required'];
                    break;
                }
                if (!class_exists('Project')) {
                    require_once DIR_MODEL . 'project.php';
                }
                $m = new Project();
                if (!$m->canUserEditProject($pid)) {
                    $result = ['status' => 'error', 'message' => 'Forbidden', 'http_status' => 403];
                    break;
                }
                // Xóa tất cả managers (role = 'manager'), giữ lại members
                $this->connect();
                $this->query("DELETE FROM " . DB_PREFIX . "project_members WHERE project_id = " . intval($pid) . " AND role = 'manager'");
                $result = ['status' => 'success', 'message' => 'All managers cleared'];
                break;
            case 'project_clear_all_members':
                $pid = isset($params['project_id']) ? intval($params['project_id']) : $id;
                if (!$pid) {
                    $result = ['status' => 'error', 'error' => 'project_id required'];
                    break;
                }
                if (!class_exists('Project')) {
                    require_once DIR_MODEL . 'project.php';
                }
                $m = new Project();
                if (!$m->canUserEditProject($pid)) {
                    $result = ['status' => 'error', 'message' => 'Forbidden', 'http_status' => 403];
                    break;
                }
                // Xóa tất cả members và managers
                $this->connect();
                $this->query("DELETE FROM " . DB_PREFIX . "project_members WHERE project_id = " . intval($pid));
                $result = ['status' => 'success', 'message' => 'All members and managers cleared'];
                break;
            case 'project_clear_teams':
                $pid = isset($params['project_id']) ? intval($params['project_id']) : $id;
                if (!$pid) {
                    $result = ['status' => 'error', 'error' => 'project_id required'];
                    break;
                }
                if (!class_exists('Project')) {
                    require_once DIR_MODEL . 'project.php';
                }
                $m = new Project();
                if (!$m->canUserEditProject($pid)) {
                    $result = ['status' => 'error', 'message' => 'Forbidden', 'http_status' => 403];
                    break;
                }
                // Clear teams bằng cách set teams = '' - dùng update() để có notification
                $_POST['id'] = $pid;
                $_POST['teams'] = '';
                $result = $m->update();
                if (isset($result['status']) && $result['status'] === 'success') {
                    $result['message'] = 'All teams cleared';
                }
                break;
            case 'project_clear_all':
                // Clear tất cả teams, members và managers cùng lúc
                $pid = isset($params['project_id']) ? intval($params['project_id']) : $id;
                if (!$pid) {
                    $result = ['status' => 'error', 'error' => 'project_id required'];
                    break;
                }
                if (!class_exists('Project')) {
                    require_once DIR_MODEL . 'project.php';
                }
                $m = new Project();
                if (!$m->canUserEditProject($pid)) {
                    $result = ['status' => 'error', 'message' => 'Forbidden', 'http_status' => 403];
                    break;
                }
                // Clear teams
                $_POST = ['id' => $pid, 'teams' => '', '_ai_triggered' => 1];
                $result1 = $m->update();
                // Clear all members và managers
                $this->query("DELETE FROM " . DB_PREFIX . "project_members WHERE project_id = " . intval($pid));
                // Kết quả: nếu clear teams thành công thì coi như thành công
                if (isset($result1['status']) && $result1['status'] === 'success') {
                    $result = ['status' => 'success', 'message' => 'All teams, members and managers cleared'];
                } else {
                    $result = $result1;
                }
                break;
            case 'project_search':
                // Search projects by parent_project fields
                $searchFilters = [];
                if (isset($params['construction_number']) && trim($params['construction_number']) !== '') {
                    $searchFilters['construction_number'] = trim($params['construction_number']);
                }
                if (isset($params['contact_name']) && trim($params['contact_name']) !== '') {
                    $searchFilters['contact_name'] = trim($params['contact_name']);
                }
                if (isset($params['company_name']) && trim($params['company_name']) !== '') {
                    $searchFilters['company_name'] = trim($params['company_name']);
                }
                if (isset($params['project_name']) && trim($params['project_name']) !== '') {
                    $searchFilters['project_name'] = trim($params['project_name']);
                }
                if (isset($params['branch_name']) && trim($params['branch_name']) !== '') {
                    $searchFilters['branch_name'] = trim($params['branch_name']);
                }
                if (empty($searchFilters)) {
                    $result = ['status' => 'error', 'error' => 'At least one search filter required (construction_number, contact_name, company_name, project_name, branch_name)'];
                    break;
                }
                if (!class_exists('Project')) {
                    require_once DIR_MODEL . 'project.php';
                }
                $m = new Project();
                $projects = $m->getForAiContext(['limit' => 200, 'search_filters' => $searchFilters]);
                $result = ['status' => 'success', 'projects' => $projects, 'message' => 'Found ' . count($projects) . ' project(s)'];
                break;
            default:
                $result = ['status' => 'error', 'error' => 'Unknown action type'];
        }
        
        $result = $result ?: ['status' => 'error', 'error' => 'No result'];
        // Add project/task links for successful actions
        if (isset($result['status']) && $result['status'] === 'success') {
            $projectId = 0;
            $taskId = null;
            if ($type === 'task_create') {
                $projectId = isset($params['project_id']) ? (int)$params['project_id'] : 0;
                $taskId = isset($result['task_id']) ? (int)$result['task_id'] : null;
            } elseif ($type === 'task_update') {
                $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : (isset($params['project_id']) ? (int)$params['project_id'] : 0);
                $taskId = $id;
            } elseif ($type === 'project_create') {
                $projectId = isset($result['project_id']) ? (int)$result['project_id'] : 0;
            } elseif (in_array($type, ['project_update', 'update_project_status', 'update_project_order_type'], true)) {
                $projectId = $id;
            } elseif (in_array($type, ['project_add_manager', 'project_add_member', 'project_remove_manager', 'project_remove_member', 'project_add_team', 'project_remove_team', 'project_add_team_members', 'project_clear_teams', 'project_clear_members', 'project_clear_managers', 'project_clear_all_members', 'project_set_teams', 'project_clear_all'], true)) {
                $projectId = isset($params['project_id']) ? (int)$params['project_id'] : $id;
            }
            if ($projectId > 0) {
                $result['links'] = $this->buildActionLinks($projectId, ($taskId > 0) ? $taskId : null, $type);
            }
            if (($type === 'parent_project_update' || $type === 'parent_project_create') && $id > 0) {
                $result['links'] = isset($result['links']) ? $result['links'] : [];
                $result['links'][] = [
                    'label' => 'Trang công trình ' . (int) $id,
                    'url'   => '/parent_project/detail.php?id=' . (int) $id,
                    'type'  => 'parent_project'
                ];
            }
        }
        $results[] = $result;
        
        if (isset($result['status']) && $result['status'] !== 'success') {
            $allSuccess = false;
            $lastError = $result;
        }
        }
        
        // Return result: if single action, return single result (backward compatible)
        // If multiple actions, return aggregated result
        if (count($actions) === 1) {
            $finalResult = $results[0];
        } else {
            // Multiple actions: aggregate results
            $successCount = 0;
            $errorCount = 0;
            foreach ($results as $r) {
                if (isset($r['status']) && $r['status'] === 'success') {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            }
            $finalResult = [
                'status' => $allSuccess ? 'success' : 'partial',
                'message' => "Executed {$successCount} action(s) successfully" . ($errorCount > 0 ? ", {$errorCount} failed" : ''),
                'results' => $results,
                'success_count' => $successCount,
                'error_count' => $errorCount
            ];
            if (!$allSuccess && $lastError) {
                $finalResult['error'] = $lastError['error'] ?? 'Some actions failed';
            }
            // Aggregate links from all successful results (dedupe by url)
            $allLinks = [];
            $seenUrls = [];
            foreach ($results as $r) {
                if (!empty($r['links']) && is_array($r['links'])) {
                    foreach ($r['links'] as $link) {
                        $url = isset($link['url']) ? $link['url'] : '';
                        if ($url !== '' && !isset($seenUrls[$url])) {
                            $seenUrls[$url] = true;
                            $allLinks[] = $link;
                        }
                    }
                }
            }
            if (!empty($allLinks)) {
                $finalResult['links'] = $allLinks;
            }
        }
        
        $msg = (isset($finalResult['status']) && $finalResult['status'] === 'success')
            ? ('✓ ' . (isset($finalResult['message']) ? $finalResult['message'] : 'Đã thực hiện.'))
            : (($finalResult['status'] === 'partial' ? '⚠ ' : '✗ ') . (isset($finalResult['error']) ? $finalResult['error'] : (isset($finalResult['message']) ? $finalResult['message'] : 'Lỗi')));
        $this->chatHistory[] = [
            'role' => 'model',
            'content' => [['text' => $msg]]
        ];
        $this->saveChatHistory();
        echo json_encode($finalResult, JSON_UNESCAPED_UNICODE);
    }

    public function chat() {
        // Lấy API Key từ .env
        $apiKey = $this->api_key;
        if (!$apiKey) {
            http_response_code(500);
            echo json_encode(['error' => 'API Key not configured']);
            return;
        }
    
        // Lấy dữ liệu từ yêu cầu POST
        $input = json_decode(file_get_contents('php://input'), true);
        $hasMessage = isset($input['message']) && (is_string($input['message']) || is_numeric($input['message']));
        $taskFormData = isset($input['task_form_data']) && is_array($input['task_form_data']) ? $input['task_form_data'] : null;
        if (!$hasMessage && !$taskFormData) {
            http_response_code(400);
            echo json_encode(['error' => 'Message or task_form_data is required']);
            return;
        }
    
        $message = $hasMessage ? (string)$input['message'] : '';
        $pageContext = isset($input['context']) && is_array($input['context']) ? $input['context'] : [];
        $projectsFromRequest = isset($input['projects']) && is_array($input['projects']) ? $input['projects'] : null;
        $requestDepartmentId = isset($input['department_id']) ? (int)$input['department_id'] : null;
        if ($requestDepartmentId <= 0) {
            $requestDepartmentId = null;
        }

        // Nếu user trả lời bằng text chọn bộ phận (vd "ID 5: Thiết kế kiến trúc") thay vì bấm nút → dùng câu hỏi đang chờ + department_id
        if ($requestDepartmentId === null && isset($_SESSION['ai_pending_department_message']) && isset($_SESSION['ai_pending_departments']) && is_array($_SESSION['ai_pending_departments'])) {
            $pendingList = $_SESSION['ai_pending_departments'];
            $msgTrim = trim((string) $message);
            $detectedDeptId = null;
            if (preg_match('/^\s*ID\s*(\d+)/i', $msgTrim, $m) || preg_match('/(?:^|\s)id\s*(\d+)(?:\s|$|:)/ui', $msgTrim, $m) || preg_match('/^\s*(\d+)\s*[:：]/u', $msgTrim, $m)) {
                $num = (int) $m[1];
                foreach ($pendingList as $d) {
                    if ((int)$d['id'] === $num) {
                        $detectedDeptId = $num;
                        break;
                    }
                }
            }
            if ($detectedDeptId === null && $msgTrim !== '') {
                foreach ($pendingList as $d) {
                    $name = isset($d['name']) ? trim((string)$d['name']) : '';
                    if ($name !== '' && (mb_strpos($msgTrim, $name) !== false || $name === $msgTrim)) {
                        $detectedDeptId = (int) $d['id'];
                        break;
                    }
                }
            }
            if ($detectedDeptId !== null) {
                $requestDepartmentId = $detectedDeptId;
                $message = $_SESSION['ai_pending_department_message'];
                unset($_SESSION['ai_pending_department_message'], $_SESSION['ai_pending_departments']);
            }
        }

        // Gửi form tạo task từ UI chat (thay vì nhập text theo format)
        if ($taskFormData !== null) {
            $pid = isset($taskFormData['project_id']) ? (int)$taskFormData['project_id'] : (isset($pageContext['project_id']) ? (int)$pageContext['project_id'] : 0);
            $title = isset($taskFormData['title']) ? trim((string)$taskFormData['title']) : '';
            $startDate = isset($taskFormData['start_date']) ? trim((string)$taskFormData['start_date']) : '';
            $dueDate = isset($taskFormData['due_date']) ? trim((string)$taskFormData['due_date']) : '';
            $assignedTo = isset($taskFormData['assigned_to']) ? trim((string)$taskFormData['assigned_to']) : '';
            $primaryAssignMe = !empty($taskFormData['primary_assignee']);
            $priority = isset($taskFormData['priority']) ? trim((string)$taskFormData['priority']) : '';
            if ($pid > 0 && $title !== '') {
                if (!class_exists('Project')) {
                    require_once DIR_MODEL . 'project.php';
                }
                $projectModel = new Project();
                if ($projectModel->canUserEditProject($pid)) {
                    $proj = $projectModel->getById($pid);
                    $dept_id = isset($proj['department_id']) ? (int)$proj['department_id'] : null;
                    $assignedToIds = [];
                    if ($primaryAssignMe) {
                        $currentUserId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0);
                        if ($currentUserId > 0) {
                            $assignedToIds[] = $currentUserId;
                        }
                    }
                    if ($assignedTo !== '') {
                        $names = array_map('trim', preg_split('/[\s,]+/', $assignedTo, -1, PREG_SPLIT_NO_EMPTY));
                        foreach ($names as $oneName) {
                            if ($oneName === '') continue;
                            list($uid, $err) = $this->resolveUserIdFromName($oneName, $dept_id);
                            if ($uid > 0 && !in_array($uid, $assignedToIds, true)) {
                                $assignedToIds[] = $uid;
                            }
                        }
                    }
                    if (!class_exists('Task')) {
                        require_once DIR_MODEL . 'task.php';
                    }
                    $taskModel = new Task();
                    $_POST['project_id'] = $pid;
                    $_POST['title'] = $title;
                    $_POST['created_by'] = isset($_SESSION['id']) ? (int)$_SESSION['id'] : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0);
                    if ($startDate !== '') {
                        $_POST['start_date'] = $startDate;
                    }
                    if ($dueDate !== '') {
                        $_POST['due_date'] = $dueDate;
                    }
                    if (!empty($assignedToIds)) {
                        $_POST['assigned_to'] = implode(',', $assignedToIds);
                    }
                    if ($priority !== '') {
                        $p = mb_strtolower($priority);
                        $priorityMap = ['low' => 'low', 'medium' => 'medium', 'high' => 'high', 'urgent' => 'urgent', 'thấp' => 'low', 'trung bình' => 'medium', 'cao' => 'high', 'khẩn cấp' => 'urgent'];
                        $_POST['priority'] = isset($priorityMap[$p]) ? $priorityMap[$p] : (in_array($p, ['low', 'medium', 'high', 'urgent'], true) ? $p : 'medium');
                    }
                    $addResult = $taskModel->add();
                    $ok = (is_array($addResult) && isset($addResult['status']) && $addResult['status'] === 'success') || ($addResult && !is_array($addResult));
                    if ($ok) {
                        $taskId = is_array($addResult) && isset($addResult['task_id']) ? $addResult['task_id'] : (is_array($addResult) && isset($addResult['id']) ? $addResult['id'] : (is_numeric($addResult) ? (int)$addResult : null));
                        $taskLink = '<a href="/project/task.php?project_id=' . (int)$pid . '">Xem trang task</a>';
                        $successText = "Đã thêm task \"" . $title . "\" vào dự án. " . $taskLink;
                        $response = [
                            'candidates' => [
                                ['content' => ['parts' => [['text' => $successText]]]]
                            ],
                            'task_created' => true,
                            'task_id' => $taskId,
                            'project_id' => $pid
                        ];
                        $this->chatHistory[] = ['role' => 'user', 'content' => [['text' => '[TASK_FORM]']]];
                        $this->chatHistory[] = ['role' => 'model', 'content' => [['text' => $successText]]];
                        $this->saveChatHistory();
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(self::decodeUnicodeInResponse($response), JSON_UNESCAPED_UNICODE);
                        return;
                    }
                }
            }
            $errMsg = 'Không thể tạo task. Kiểm tra tiêu đề và quyền truy cập dự án.';
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'candidates' => [['content' => ['parts' => [['text' => $errMsg]]]]],
                'task_created' => false,
                'error' => $errMsg
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Khi user nói "thêm task" và có project_id (từ trang hoặc từ message như "dự án ID 23") → trả ask_task_form, hiện UI thay vì Gemini hỏi format
        $projectIdFromPage = isset($pageContext['project_id']) && (int)$pageContext['project_id'] > 0 ? (int)$pageContext['project_id'] : 0;
        $projectIdFromMessage = $message !== '' ? $this->getProjectIdFromMessage($message) : 0;
        $projectIdForForm = $projectIdFromPage > 0 ? $projectIdFromPage : $projectIdFromMessage;
        if ($projectIdForForm > 0 && $message !== '') {
            $msgNorm = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $message)));
            $addTaskPattern = '/\b(thêm|tạo|add)\s*(task|công việc|task vào|タスクを追加|タスク追加)/u';
            $hasAddTask = preg_match($addTaskPattern, $msgNorm)
                || mb_strpos($msgNorm, 'thêm task') !== false
                || mb_strpos($msgNorm, 'tạo task') !== false
                || mb_strpos($msgNorm, 'add task') !== false
                || mb_strpos($msgNorm, 'タスクを追加') !== false
                || (mb_strpos($msgNorm, 'tạo') !== false && (mb_strpos($msgNorm, 'task') !== false || mb_strpos($msgNorm, 'công việc') !== false))
                || (mb_strpos($msgNorm, 'thêm') !== false && (mb_strpos($msgNorm, 'task') !== false || mb_strpos($msgNorm, 'công việc') !== false));
            if ($hasAddTask) {
                if (!class_exists('Project')) {
                    require_once DIR_MODEL . 'project.php';
                }
                $projectModel = new Project();
                if ($projectModel->canUserEditProject($projectIdForForm)) {
                    $initialValues = $this->extractTaskInitialValues($message);
                    $shortMessage = empty($initialValues) ? 'Vui lòng điền form bên dưới để tạo task.' : 'Đã điền sẵn theo yêu cầu. Bạn có thể chỉnh sửa và bấm Tạo task.';
                    $response = [
                        'candidates' => [
                            ['content' => ['parts' => [['text' => $shortMessage]]]]
                        ],
                        'ask_task_form' => true,
                        'project_id' => $projectIdForForm,
                        'initial_values' => $initialValues
                    ];
                    $this->chatHistory[] = ['role' => 'user', 'content' => [['text' => $message]]];
                    $this->chatHistory[] = ['role' => 'model', 'content' => [['text' => $shortMessage]]];
                    $this->saveChatHistory();
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(self::decodeUnicodeInResponse($response), JSON_UNESCAPED_UNICODE);
                    return;
                }
            }
        }

        // Follow-up: user trả lời form task (Tiêu đề, Thời gian bắt đầu/kết thúc, Phân công cho) sau khi AI hỏi một lần theo format
        $projectIdForTask = isset($pageContext['project_id']) && (int)$pageContext['project_id'] > 0 ? (int)$pageContext['project_id'] : 0;
        if ($projectIdForTask > 0 && $this->needHistoryForMessage($message) && count($this->chatHistory) >= 2) {
            $lastModelText = $this->getLastModelMessageFromHistory();
            $lastModelLower = mb_strtolower($lastModelText);
            $lastAskedTaskForm = (mb_strpos($lastModelLower, 'tiêu đề') !== false && (mb_strpos($lastModelLower, 'thời gian bắt đầu') !== false || mb_strpos($lastModelLower, 'thời gian kết thúc') !== false) && mb_strpos($lastModelLower, 'phân công cho') !== false);
            $lastAskedTaskTitleOnly = !$lastAskedTaskForm && (mb_strpos($lastModelLower, 'tiêu đề') !== false || mb_strpos($lastModelLower, 'title') !== false) && (mb_strpos($lastModelLower, '?') !== false || mb_strpos($lastModelLower, 'gì') !== false);
            $msgTrim = trim((string) $message);
            $title = '';
            $startDate = '';
            $endDate = '';
            $assignedToNames = '';
            $priority = '';

            if ($lastAskedTaskForm && (mb_strpos($msgTrim, 'Tiêu đề') !== false || mb_strpos($msgTrim, 'Thời gian') !== false || mb_strpos($msgTrim, 'Phân công') !== false)) {
                if (preg_match('/Tiêu\s*đề\s*[:\s]+\s*(.+?)(?=\n|Thời gian|Phân công|Mức|$)/uis', $msgTrim, $m)) {
                    $title = trim($m[1]);
                }
                if (preg_match('/Thời\s*gian\s*bắt\s*đầu\s*[:\s]+\s*(.+?)(?=\n|Thời gian kết thúc|Phân công|Mức|$)/uis', $msgTrim, $m)) {
                    $startDate = trim($m[1]);
                }
                if (preg_match('/Thời\s*gian\s*kết\s*thúc\s*[:\s]+\s*(.+?)(?=\n|Phân công|Mức|$)/uis', $msgTrim, $m)) {
                    $endDate = trim($m[1]);
                }
                if (preg_match('/Phân\s*công\s*cho\s*[:\s]+\s*(.+?)(?=\n|Mức|$)/uis', $msgTrim, $m)) {
                    $assignedToNames = trim($m[1]);
                }
                if (preg_match('/Mức\s*ưu\s*tiên\s*[:\s]+\s*(.+)$/uis', $msgTrim, $m)) {
                    $priority = trim($m[1]);
                }
            } elseif ($lastAskedTaskTitleOnly) {
                $title = $msgTrim;
                if (preg_match('/tiêu\s*đề\s*là\s*[:\s]*(.+)/ui', $msgTrim, $m)) {
                    $title = trim($m[1]);
                } elseif (preg_match('/title\s*is\s*[:\s]*(.+)/ui', $msgTrim, $m)) {
                    $title = trim($m[1]);
                } elseif (preg_match('/tên\s*task\s*[:\s]*(.+)/ui', $msgTrim, $m)) {
                    $title = trim($m[1]);
                }
            }

            if ($title !== '' && ($lastAskedTaskForm ? ($startDate !== '' && $endDate !== '' && $assignedToNames !== '') : true)) {
                if (!class_exists('Project')) {
                    require_once DIR_MODEL . 'project.php';
                }
                $projectModel = new Project();
                if ($projectModel->canUserEditProject($projectIdForTask)) {
                    $proj = $projectModel->getById($projectIdForTask);
                    $dept_id = isset($proj['department_id']) ? (int)$proj['department_id'] : null;
                    $assignedToIds = [];
                    if ($assignedToNames !== '') {
                        $names = array_map('trim', preg_split('/[\s,]+/', $assignedToNames, -1, PREG_SPLIT_NO_EMPTY));
                        foreach ($names as $oneName) {
                            if ($oneName === '') continue;
                            list($uid, $err) = $this->resolveUserIdFromName($oneName, $dept_id);
                            if ($uid > 0) {
                                $assignedToIds[] = $uid;
                            }
                        }
                    }
                    if ($lastAskedTaskForm && empty($assignedToIds)) {
                        // Bắt buộc phân công nhưng không resolve được → bỏ qua, để Gemini xử lý
                    } else {
                        if (!class_exists('Task')) {
                            require_once DIR_MODEL . 'task.php';
                        }
                        $taskModel = new Task();
                        $_POST['project_id'] = $projectIdForTask;
                        $_POST['title'] = $title;
                        $_POST['created_by'] = isset($_SESSION['id']) ? (int)$_SESSION['id'] : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0);
                        if ($startDate !== '') {
                            $_POST['start_date'] = $startDate;
                        }
                        if ($endDate !== '') {
                            $_POST['due_date'] = $endDate;
                        }
                        if (!empty($assignedToIds)) {
                            $_POST['assigned_to'] = implode(',', $assignedToIds);
                        }
                        if ($priority !== '') {
                            $p = mb_strtolower(trim($priority));
                            $priorityMap = ['low' => 'low', 'medium' => 'medium', 'high' => 'high', 'urgent' => 'urgent', 'thấp' => 'low', 'trung bình' => 'medium', 'cao' => 'high', 'khẩn cấp' => 'urgent'];
                            $_POST['priority'] = isset($priorityMap[$p]) ? $priorityMap[$p] : (in_array($p, ['low', 'medium', 'high', 'urgent'], true) ? $p : 'medium');
                        }
                        $addResult = $taskModel->add();
                        $ok = (is_array($addResult) && isset($addResult['status']) && $addResult['status'] === 'success') || ($addResult && !is_array($addResult));
                        if ($ok) {
                            $taskId = is_array($addResult) && isset($addResult['task_id']) ? $addResult['task_id'] : (is_array($addResult) && isset($addResult['id']) ? $addResult['id'] : (is_numeric($addResult) ? (int)$addResult : null));
                            $taskLink = '<a href="/project/task.php?project_id=' . (int)$projectIdForTask . '">Xem trang task</a>';
                            $successText = "Đã thêm task \"" . $title . "\" vào dự án. " . $taskLink;
                            $response = [
                                'candidates' => [
                                    ['content' => ['parts' => [['text' => $successText]]]]
                                ],
                                'task_created' => true,
                                'task_id' => $taskId,
                                'project_id' => $projectIdForTask
                            ];
                            $this->chatHistory[] = ['role' => 'user', 'content' => [['text' => $message]]];
                            $this->chatHistory[] = ['role' => 'model', 'content' => [['text' => $successText]]];
                            $this->saveChatHistory();
                            header('Content-Type: application/json; charset=utf-8');
                            echo json_encode(self::decodeUnicodeInResponse($response), JSON_UNESCAPED_UNICODE);
                            return;
                        }
                    }
                }
            }
        }

        $response = null;
        $classification = null;
        if (self::$USE_TWO_ROUND_FLOW) {
            $classification = $this->classifyIntentAndDataRequest($message, $apiKey, $pageContext);
            if ($classification === null && $this->getProjectIdFromMessage($message) > 0) {
                $pid = $this->getProjectIdFromMessage($message);
                $classification = [
                    'intent' => 'search',
                    'data_request' => [
                        'projects' => true,
                        'statistics' => false,
                        'limit' => 20,
                        'department_id' => null,
                        'status_filter' => null,
                        'search_filters' => ['project_name' => (string) $pid]
                    ]
                ];
            }
            // Fallback: "dự án trên"/"dự án đó" = dự án đã nhắc trong hội thoại trước → lấy project_id từ chat history (không dùng trang hiện tại)
            $msgLower = mb_strtolower(trim($message));
            $refersToProjectAbove = (mb_strpos($msgLower, 'dự án trên') !== false || mb_strpos($msgLower, 'dự án đó') !== false
                || mb_strpos($msgLower, 'project above') !== false || mb_strpos($msgLower, 'that project') !== false
                || mb_strpos($msgLower, 'do ai tham gia') !== false || mb_strpos($msgLower, 'ai tham gia') !== false
                || mb_strpos($msgLower, '参加者') !== false || mb_strpos($msgLower, 'who particip') !== false);
            $refersToProjectThis = (mb_strpos($msgLower, 'dự án này') !== false);
            $needProjectFromHistory = $refersToProjectAbove && count($this->chatHistory) >= 2;
            $lastUserText = $needProjectFromHistory ? $this->getLastUserMessageFromHistory() : '';
            $pidFromHistory = ($lastUserText !== '' && $needProjectFromHistory) ? $this->getProjectIdFromMessage($lastUserText) : 0;
            if ($classification && isset($classification['data_request'])) {
                $dr = &$classification['data_request'];
                if (!isset($dr['search_filters']) || !is_array($dr['search_filters'])) {
                    $dr['search_filters'] = [];
                }
                $hasProjectName = !empty($dr['search_filters']['project_name']);
                if (!$hasProjectName && ($refersToProjectAbove || $refersToProjectThis)) {
                    $pid = 0;
                    $lastIds = isset($_SESSION['ai_last_project_ids']) && is_array($_SESSION['ai_last_project_ids'])
                        ? array_values(array_filter(array_map('intval', $_SESSION['ai_last_project_ids']))) : [];
                    if ($refersToProjectAbove && !empty($lastIds)) {
                        // "2 dự án đó", "tổng tiền của 2 dự án đó" → dùng lại danh sách id từ lượt fetch trước
                        $classification['intent'] = 'search';
                        $dr['projects'] = true;
                        $dr['statistics'] = false;
                        $dr['search_filters']['project_name'] = implode(',', $lastIds);
                    } elseif ($refersToProjectAbove && $pidFromHistory > 0) {
                        $pid = $pidFromHistory; // "dự án trên" / "dự án đó" (một id) = từ chat history
                        $classification['intent'] = 'search';
                        $dr['projects'] = true;
                        $dr['statistics'] = false;
                        $dr['search_filters']['project_name'] = (string) $pid;
                    } elseif ($refersToProjectThis && !empty($pageContext['project_id'])) {
                        $pid = is_numeric($pageContext['project_id']) ? (int)$pageContext['project_id'] : 0; // "dự án này" = trang hiện tại
                        if ($pid > 0) {
                            $classification['intent'] = 'search';
                            $dr['projects'] = true;
                            $dr['statistics'] = false;
                            $dr['search_filters']['project_name'] = (string) $pid;
                        }
                    } elseif ($refersToProjectAbove && $pidFromHistory > 0) {
                        $pid = $pidFromHistory;
                        if ($pid > 0) {
                            $classification['intent'] = 'search';
                            $dr['projects'] = true;
                            $dr['statistics'] = false;
                            $dr['search_filters']['project_name'] = (string) $pid;
                        }
                    }
                }
            }
            // Ép intent "search" khi classifier trả "general" nhưng user hỏi về dự án (đã có project_name từ history hoặc page ở trên)
            if ($classification && isset($classification['intent']) && $classification['intent'] === 'general'
                && isset($classification['data_request']['search_filters']['project_name'])) {
                $classification['intent'] = 'search';
                $classification['data_request']['projects'] = true;
                $classification['data_request']['statistics'] = false;
            }
            if ($classification && isset($classification['data_request'])) {
                $dr = $classification['data_request'];
                if (!empty($dr['projects']) || !empty($dr['statistics']) || !empty($dr['availability']) || !empty($dr['customer'])) {
                    $userDepts = $this->getUserDepartmentsForContext();
                    $isAdmin = (isset($_SESSION['authority']) && $_SESSION['authority'] === 'administrator');
                    if ($isAdmin && empty($userDepts)) {
                        $rows = $this->fetchAll("SELECT id, name FROM " . DB_PREFIX . "departments ORDER BY name ASC");
                        $userDepts = is_array($rows) ? $rows : [];
                    }
                    if (($dr['department_id'] === null || $dr['department_id'] === '') && count($userDepts) === 1) {
                        $dr['department_id'] = (int)$userDepts[0]['id'];
                    }
                    if ($requestDepartmentId === null && isset($_SESSION['ai_selected_department_id']) && (int)$_SESSION['ai_selected_department_id'] > 0) {
                        $savedDeptId = (int)$_SESSION['ai_selected_department_id'];
                        $allowed = $isAdmin;
                        if (!$allowed && !empty($userDepts)) {
                            foreach ($userDepts as $d) {
                                if ((int)$d['id'] === $savedDeptId) {
                                    $allowed = true;
                                    break;
                                }
                            }
                        }
                        if ($allowed) {
                            $requestDepartmentId = $savedDeptId;
                        } else {
                            unset($_SESSION['ai_selected_department_id']);
                        }
                    }
                    // Khi user hỏi theo project ID cụ thể (vd. "dự án 7 do nhóm nào làm") thì không bắt chọn department — fetch trực tiếp
                    $hasSpecificProjectId = isset($dr['search_filters']['project_name']) && trim((string)$dr['search_filters']['project_name']) !== '';
                    $needDepartment = ($dr['department_id'] === null || $dr['department_id'] === '') && (count($userDepts) > 1 || $isAdmin) && !$hasSpecificProjectId;
                    if ($needDepartment && $requestDepartmentId === null) {
                        $askDeptList = [];
                        foreach ($userDepts as $d) {
                            $askDeptList[] = ['id' => (int)$d['id'], 'name' => isset($d['name']) ? $d['name'] : ''];
                        }
                        $askDeptList = array_values(array_filter($askDeptList, function ($d) {
                            $name = isset($d['name']) ? trim((string)$d['name']) : '';
                            return $name !== '' && $name !== 'なし';
                        }));
                        $_SESSION['ai_pending_department_message'] = $message;
                        $_SESSION['ai_pending_departments'] = $askDeptList;
                        $response = [
                            'candidates' => [
                                ['content' => ['parts' => [['text' => 'Vui lòng chọn phòng ban bên dưới để tôi tìm thông tin.']]]]
                            ],
                            'ask_department' => true,
                            'departments' => $askDeptList
                        ];
                    } else {
                        if ($requestDepartmentId !== null) {
                            $dr['department_id'] = $requestDepartmentId;
                            $classification['data_request']['department_id'] = $requestDepartmentId;
                            if (isset($input['department_id']) && (int)$input['department_id'] > 0) {
                                $_SESSION['ai_selected_department_id'] = $requestDepartmentId;
                            }
                        }
                        $context = $this->fetchDataFromDataRequest($dr, $userDepts);
                        $userSelectedDeptIdForPrompt = (isset($input['department_id']) && (int)$input['department_id'] > 0) ? $requestDepartmentId : null;
                        $response = $this->sendToGemini($message, $apiKey, $pageContext, $projectsFromRequest, $context, $classification, $userSelectedDeptIdForPrompt);
                    }
                }
            }
        }
        if ($response === null && $classification === null) {
            $projectId = $this->getProjectIdFromMessage($message);
            if ($projectId > 0) {
                $projList = $this->getProjectsForContext(['id' => $projectId]);
                if (!empty($projList) && is_array($projList)) {
                    $context = ['projects' => $projList, 'statistics' => []];
                    $fakeDr = ['projects' => true, 'statistics' => false, 'limit' => 20, 'department_id' => null, 'status_filter' => null, 'search_filters' => []];
                    $response = $this->sendToGemini($message, $apiKey, $pageContext, $projectsFromRequest, $context, ['data_request' => $fakeDr], null);
                }
            }
        }
        // User trả lời ngắn (vd. "có") sau khi AI hỏi "có muốn hiển thị chi tiết?" → lấy project từ lượt trước trong history, fetch và gửi context để AI có dữ liệu hiển thị
        if ($response === null && $this->needHistoryForMessage($message) && count($this->chatHistory) >= 2) {
            $lastUserText = $this->getLastUserMessageFromHistory();
            if ($lastUserText !== '' && $this->getProjectIdFromMessage($lastUserText) > 0) {
                $pid = $this->getProjectIdFromMessage($lastUserText);
                $projList = $this->getProjectsForContext(['id' => $pid]);
                if (!empty($projList) && is_array($projList)) {
                    $context = ['projects' => $projList, 'statistics' => []];
                    $fakeDr = ['projects' => true, 'statistics' => false, 'limit' => 20, 'department_id' => null, 'status_filter' => null, 'search_filters' => []];
                    $response = $this->sendToGemini($message, $apiKey, $pageContext, $projectsFromRequest, $context, ['data_request' => $fakeDr], null);
                }
            }
        }
        if ($response === null) {
            $response = $this->sendToGemini($message, $apiKey, $pageContext, $projectsFromRequest);
        }
        $response = $this->parseAndStripActionFromResponse($response);

        // Kiểm tra phản hồi từ Gemini
        // if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
        //     $aiResponse = json_decode($response['candidates'][0]['content']['parts'][0]['text'], true);
    
        //     if (isset($aiResponse['action'])) {
        //         $action = $aiResponse['action'];
        //         $parameters = $aiResponse['parameters'] ?? [];
    
        //         // Thực hiện hành động dựa trên action
        //         switch ($action) {
        //             case 'get_projects':
        //                 $data = $this->getProjectsFromDatabase();
        //                 break;
    
        //             case 'get_project_details':
        //                 $projectId = $parameters['project_id'] ?? null;
        //                 $data = $this->getProjectDetailsFromDatabase($projectId);
        //                 break;
    
        //             default:
        //                 $data = ['error' => 'Unknown action'];
        //         }
    
        //         // Gửi dữ liệu đến Gemini để phân tích
        //         $analysisResponse = $this->sendToGeminiWithData($data, $apiKey);
    
        //         // Trả về kết quả phân tích
        //         header('Content-Type: application/json');
        //         echo json_encode($analysisResponse);
        //         return;
        //     }
        // }
    
        // Chuẩn hóa Unicode: giải mã \uXXXX trong text (Gemini đôi khi trả literal escape), rồi trả JSON UTF-8
        if (is_array($response)) {
            $response = self::decodeUnicodeInResponse($response);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Parse ACTION:... from Gemini response; put in pending_action or pending_actions (user must confirm). Strip ACTION block from text.
     * Matches single ACTION or multiple ACTIONS (one per line).
     * Supports both single action (backward compatible) and multiple actions.
     */
    private function parseAndStripActionFromResponse($response) {
        if (!is_array($response) || empty($response['candidates'][0]['content']['parts'][0]['text'])) {
            return $response;
        }
        $text = $response['candidates'][0]['content']['parts'][0]['text'];
        $actions = [];
        $textStripped = $text;
        
        // Match all ACTION: blocks (can be multiple, one per line or separated)
        // Pattern matches: ACTION: {...} (single line or multiline JSON)
        preg_match_all('/ACTION:\s*(\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\})\s*/s', $text, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $m) {
            $jsonStr = preg_replace('/\s+/', ' ', $m[1]); // normalize whitespace for json_decode
            $action = json_decode($jsonStr, true);
            if (is_array($action) && !empty($action['type'])) {
                $actions[] = $action;
                $textStripped = str_replace($m[0], '', $textStripped);
            }
        }
        
        if (!empty($actions)) {
            $textStripped = trim($textStripped);
            $response['candidates'][0]['content']['parts'][0]['text'] = $textStripped;
            
            // Backward compatibility: single action -> pending_action
            if (count($actions) === 1) {
                $response['pending_action'] = $actions[0];
            } else {
                // Multiple actions -> pending_actions (array)
                $response['pending_actions'] = $actions;
            }
            
            // Update chat history
            $n = count($this->chatHistory);
            if ($n > 0) {
                $last = &$this->chatHistory[$n - 1];
                if (isset($last['content'][0]['text'])) {
                    $last['content'][0]['text'] = $textStripped;
                    $this->saveChatHistory();
                }
            }
        }
        
        return $response;
    }

    function sendToGemini($messages, $apiKey, $pageContext = [], $projectsFromRequest = null, $preFetchedContext = null, $dataRequest = null, $userSelectedDepartmentId = null) {
        $modelUrl = "https://generativelanguage.googleapis.com/v1beta/models/".$this->ai_model.":generateContent";
       
        $usePreFetched = ($preFetchedContext !== null && is_array($preFetchedContext) && is_array($dataRequest) && isset($dataRequest['data_request']));

        if ($usePreFetched) {
            $context = $preFetchedContext;
            $dr = $dataRequest['data_request'];
            $contextKeys = [];
            if (isset($context['projects']) && is_array($context['projects'])) $contextKeys[] = 'projects';
            if (isset($context['statistics']) && is_array($context['statistics'])) $contextKeys[] = 'statistics';
            if (isset($context['availability']) && is_array($context['availability'])) $contextKeys[] = 'availability';
            if (isset($context['customer']) && $context['customer'] !== null) $contextKeys[] = 'customer';
            $searchFilters = isset($dr['search_filters']) && is_array($dr['search_filters']) ? $dr['search_filters'] : [];
            $statusFilter = isset($dr['status_filter']) && $dr['status_filter'] !== '' ? $dr['status_filter'] : null;
            $department_id = isset($dr['department_id']) && (int)$dr['department_id'] > 0 ? (int)$dr['department_id'] : null;
            $originalTeamName = isset($searchFilters['team_name']) ? $searchFilters['team_name'] : null;
            $projectOrStatsRequested = (!empty($contextKeys));
            $departmentJustChosen = ($userSelectedDepartmentId !== null && (int)$userSelectedDepartmentId > 0);
            $departmentFromSession = ($department_id !== null && $department_id > 0 && !$departmentJustChosen);
            $userDepts = $this->getUserDepartmentsForContext();
            $isAdmin = (isset($_SESSION['authority']) && $_SESSION['authority'] === 'administrator');
            if ($isAdmin && empty($userDepts)) {
                $rows = $this->fetchAll("SELECT id, name FROM " . DB_PREFIX . "departments ORDER BY name ASC");
                $userDepts = is_array($rows) ? $rows : [];
            }
        } else {
            // Không có preFetched (fallback): dùng context rỗng, Gemini trả lời dựa trên system prompt + page context
            $context = ['projects' => [], 'statistics' => []];
            if ($projectsFromRequest !== null && is_array($projectsFromRequest) && !empty($projectsFromRequest)) {
                $context['projects'] = $projectsFromRequest;
            }
            $contextKeys = [];
            if (!empty($context['projects'])) $contextKeys[] = 'projects';
            if (!empty($context['availability'])) $contextKeys[] = 'availability';
            if (isset($context['customer']) && $context['customer'] !== null) $contextKeys[] = 'customer';
            $searchFilters = [];
            $statusFilter = null;
            $department_id = null;
            $originalTeamName = null;
            $projectOrStatsRequested = !empty($contextKeys);
            $departmentJustChosen = false;
            $departmentFromSession = false;
            $userDepts = $this->getUserDepartmentsForContext();
            $isAdmin = (isset($_SESSION['authority']) && $_SESSION['authority'] === 'administrator');
            if ($isAdmin && empty($userDepts)) {
                $rows = $this->fetchAll("SELECT id, name FROM " . DB_PREFIX . "departments ORDER BY name ASC");
                $userDepts = is_array($rows) ? $rows : [];
            }
        }

        $realname = isset($_SESSION['realname']) ? $_SESSION['realname'] : 'ユーザー';
        $variablePrefix = $this->getServerDateBlock() . "\n[User]" . json_encode($this->buildUserContextForGemini(), JSON_UNESCAPED_UNICODE);
        $forceHistory = false;
        if ($this->needHistoryForMessage($messages)) {
            $forceHistory = true;
        }
        // Force history khi user vừa chọn department (để Gemini biết câu hỏi gốc)
        if ($departmentJustChosen || $departmentFromSession) {
            $forceHistory = true;
        }
        if ($projectOrStatsRequested && ($isAdmin || count($userDepts) > 1)) {
            if ($department_id === null || $department_id <= 0) {
                $forceHistory = true;
            }
        }
        // User hỏi lại đúng cùng một câu → không gửi [History] để tránh token tăng (lần 2 sẽ ~bằng lần 1)
        $lastUserText = '';
        foreach (array_reverse($this->chatHistory) as $h) {
            if (isset($h['role']) && $h['role'] === 'user' && isset($h['content']['text'])) {
                $lastUserText = is_string($h['content']['text']) ? trim($h['content']['text']) : '';
                break;
            }
        }
        $isRepeatQuestion = ($lastUserText !== '' && trim(is_string($messages) ? $messages : '') === $lastUserText);
        if ($isRepeatQuestion) {
            $forceHistory = false;
        }
        if ($forceHistory) {
            // Tăng số lượng history items khi user chọn department để đảm bảo câu hỏi gốc được bao gồm
            $maxItems = ($departmentJustChosen || $departmentFromSession) ? (self::$CHAT_HISTORY_MAX_TURNS * 3) : (self::$CHAT_HISTORY_MAX_TURNS * 2); // 3 lượt = 3 user + 3 model khi chọn department
            $history = array_slice($this->chatHistory, -$maxItems);
            if (!empty($history)) {
                $variablePrefix .= "\n[History]" . json_encode($history, JSON_UNESCAPED_UNICODE);
                // Thông báo đặc biệt khi user vừa chọn department
                if ($departmentJustChosen) {
                    $variablePrefix .= "\n[Important] The user has just selected a department in their current message. Please answer their ORIGINAL question from the chat history above using the department they just selected. Do NOT just acknowledge the department selection - you MUST answer the original question they asked before selecting the department.";
                } elseif ($departmentFromSession) {
                    $variablePrefix .= "\n[Important] The user's previous question is in the chat history above. Please answer that question using the department that was previously selected. Do NOT ask for department again.";
                } elseif (mb_strlen(trim($messages)) <= 25) {
                    // Tin nhắn ngắn (vd. "có", "yes", "ok") sau câu hỏi/đề xuất của AI → coi là trả lời cho lượt trước
                    $variablePrefix .= "\n[Important] The user's current message is a short reply (e.g. \"có\", \"yes\", \"ok\", \"はい\") to your previous message. Treat it as a direct answer: e.g. \"có\" / \"yes\" / \"ok\" = confirm and DO what you offered (show details, navigate, display). Do NOT reply with a new generic greeting. Answer in context of the conversation above.";
                }
                // Nếu có search filters từ history, thông báo cho Gemini
                if (!empty($searchFilters)) {
                    $filterDesc = [];
                    if (isset($searchFilters['branch_name'])) {
                        $filterDesc[] = "支店名(branch_name) = \"" . $searchFilters['branch_name'] . "\"";
                    }
                    if (isset($searchFilters['company_name'])) {
                        $filterDesc[] = "顧客名(company_name) = \"" . $searchFilters['company_name'] . "\"";
                    }
                    if (isset($searchFilters['project_name'])) {
                        $filterDesc[] = "建物名(project_name) = \"" . $searchFilters['project_name'] . "\"";
                    }
                    if (isset($searchFilters['contact_name'])) {
                        $filterDesc[] = "担当者名(contact_name) = \"" . $searchFilters['contact_name'] . "\"";
                    }
                    if (isset($searchFilters['construction_number'])) {
                        $filterDesc[] = "工事番号(construction_number) = \"" . $searchFilters['construction_number'] . "\"";
                    }
                    if (!empty($filterDesc)) {
                        $variablePrefix .= "\n[Search filters from previous message] User previously requested to filter projects by: " . implode(", ", $filterDesc) . ". **IMPORTANT: These filters are still active. When listing projects, you MUST filter by these conditions. Do NOT ignore them even if the current message only mentions department selection.**";
                    }
                }
                // Nếu có status filter từ history/session, thông báo cho Gemini
                if ($statusFilter !== null && $statusFilter !== '') {
                    $statusMap = [
                        'in_progress' => '進行中',
                        'completed' => '完了',
                        'quotation' => '見積',
                        'contract' => '請負/契約',
                        'draft' => '受付',
                        'open' => '納期検討/開始',
                        'paused' => '一時停止',
                        'cancelled' => '中止'
                    ];
                    $statusLabel = isset($statusMap[$statusFilter]) ? $statusMap[$statusFilter] : $statusFilter;
                    $variablePrefix .= "\n[Status filter from previous message] User previously requested to filter projects by status: " . $statusFilter . " (" . $statusLabel . "). **IMPORTANT: This status filter is still active. When listing projects or calculating totals, you MUST filter by status=\"" . $statusFilter . "\". Do NOT ignore this filter even if the current message only mentions department selection.**";
                }
            }
        }
        $systemPrompt = $this->buildSystemPromptStatic($realname, date('Y')) . $variablePrefix;

        $parts = [];
        // Tòa nhà (parent_project) theo project_number: #P000008 hoặc P000008 → fetch để Gemini xác nhận tên trước khi thực thi
        $messageText = is_string($messages) ? $messages : '';
        if ($messageText !== '' && preg_match('/#?P[A-Za-z0-9_-]+/i', $messageText, $m)) {
            $pn = ltrim($m[0], '#');
            if (!class_exists('ParentProject')) {
                require_once DIR_MODEL . 'parentproject.php';
            }
            $parentModel = new ParentProject();
            $parentRow = $parentModel->getByProjectNumber($pn);
            if ($parentRow && !empty($parentRow['id'])) {
                $parts[] = '[Parent project by project_number] project_number = "' . $pn . '", project_name = "' . (isset($parentRow['project_name']) ? addslashes($parentRow['project_name']) : '') . '", id = ' . (int)$parentRow['id'] . '. **Use project_name in your confirmation message** so the user can verify the building before executing (e.g. "Bạn muốn sửa số công trình của tòa nhà [project_name] (#' . $pn . ') thành …. Bấm Xác nhận để thực hiện."). For ACTION use params: project_number: "' . $pn . '" and the field(s) to update.';
            }
        }
        // Luôn thông báo cho Gemini về department đã chọn nếu có (kể cả khi lấy từ session)
        if ($projectOrStatsRequested && $department_id !== null && $department_id > 0) {
            // Tìm tên department
            $deptName = '';
            foreach ($userDepts as $dept) {
                if ((int)$dept['id'] === $department_id) {
                    $deptName = isset($dept['name']) ? $dept['name'] : '';
                    break;
                }
            }
            if ($isAdmin && $deptName === '') {
                $row = $this->fetchOne("SELECT name FROM " . DB_PREFIX . "departments WHERE id = " . $department_id);
                if ($row && isset($row['name'])) {
                    $deptName = $row['name'];
                }
            }
            if ($deptName !== '') {
                if ($departmentFromSession) {
                    // Department được lấy từ session (user đã chọn trước đó)
                    $parts[] = "[Selected department] User has previously selected department (部署) with id=" . $department_id . " and name=\"" . $deptName . "\". **CRITICAL: This department is already selected and active. Use this department automatically for ALL project/statistics queries. Do NOT ask for department again. Do NOT list departments. Do NOT mention department selection unless user explicitly wants to change it. Answer the user's question directly using ONLY the data provided below.**";
                } elseif ($departmentJustChosen) {
                    // User vừa chọn bộ phận từ UI (nút chọn) – trả lời câu hỏi gốc, không hỏi lại
                    $parts[] = "[Selected department - USER JUST CHOSE FROM UI] The user has just selected department (部署) id=" . $department_id . ", name=\"" . $deptName . "\" via the department selector. **CRITICAL: Do NOT ask to select department again. Do NOT list departments. Do NOT say 'please choose department' or 'vui lòng chọn bộ phận'. The data below is already filtered by this department. Answer the user's ORIGINAL question (from chat history) directly using the provided data only.**";
                } else {
                    // Department được chọn từ message hiện tại
                    $parts[] = "[Selected department] User has selected department (部署) with id=" . $department_id . " and name=\"" . $deptName . "\". **Do NOT ask for department again. Do NOT list departments. Answer the user's question directly using ONLY the data provided below.**";
                }
            }
        }
        
        $needsDepartmentChoice = (in_array('projects', $contextKeys, true) || in_array('statistics', $contextKeys, true) || in_array('availability', $contextKeys, true));
        if ($projectOrStatsRequested && !$isAdmin && empty($userDepts) && $needsDepartmentChoice) {
            $parts[] = "[User has no department] The user is not assigned to any department (部署に所属していません). Refuse to list projects or answer project/statistics questions. Reply in the user's language: they must be assigned to a department by an administrator (担当者に部署の割り当てを依頼してください).";
        } elseif ($projectOrStatsRequested && $needsDepartmentChoice && ($isAdmin || count($userDepts) > 1) && ($department_id === null || $department_id <= 0)) {
            // Chỉ hỏi department nếu chưa có trong session hoặc không hợp lệ (không hỏi khi chỉ yêu cầu customer)
            $deptList = [];
            if (!empty($userDepts)) {
                $deptList = array_map(function ($d) {
                    return ['id' => (int)$d['id'], 'name' => isset($d['name']) ? $d['name'] : ''];
                }, $userDepts);
            } elseif ($isAdmin) {
                $rows = $this->fetchAll("SELECT id, name FROM " . DB_PREFIX . "departments ORDER BY name ASC");
                $deptList = is_array($rows) ? array_map(function ($d) {
                    return ['id' => (int)$d['id'], 'name' => isset($d['name']) ? $d['name'] : ''];
                }, $rows) : [];
            }
            $parts[] = "[User is administrator or has multiple departments] Do NOT list projects or statistics yet. Ask which department (部署) they want. Send this list so they can choose: " . json_encode($deptList, JSON_UNESCAPED_UNICODE) . ". After they specify (e.g. by id or name), the next message will include that department's data.";
        }
        // Nếu có projects từ request (sau project_search), sử dụng trực tiếp thay vì fetch
        if ($projectsFromRequest !== null && is_array($projectsFromRequest) && !empty($projectsFromRequest)) {
            $context['projects'] = $projectsFromRequest;
            // Đảm bảo contextKeys có 'projects'
            if (!in_array('projects', $contextKeys, true)) {
                $contextKeys[] = 'projects';
            }
        }
        
        // Chỉ gửi status values khi có context projects/task (tiết kiệm token)
        if (in_array('projects', $contextKeys, true)) {
            $parts[] = "[Project status values] Use these exact value in params.status or ACTION. draft=受付, open=納期検討/開始, confirming=仮受, quotation=見積, contract=請負/契約( hợp đồng), in_progress=進行中, completed=納品/完了, paused=一時停止, cancelled=中止.";
        }
        if (in_array('projects', $contextKeys, true)) {
            $parts[] = "[Task status values] Use these exact value in params.status. new=新規, todo=未開始, in_progress=進行中, confirming=確認中, paused=一時停止, completed=完了, cancelled=キャンセル.";
        }
        // [Parent project by project_number] đã được thêm ở đầu $parts khi message có #P
        $pageProjectId = isset($pageContext['project_id']) ? intval($pageContext['project_id']) : 0;
        $projectIdForContext = $pageProjectId > 0 ? $pageProjectId : $this->getProjectIdFromMessage($messages);
        
        // Detect nếu user hỏi về tiền/amount/progress của một project cụ thể
        $msgLower = mb_strtolower(trim($messages));
        $moneyKeywords = ['bao nhiêu tiền', 'bao nhieu tien', 'bao nhiêu', 'bao nhieu', 'いくら', '金額', '金額は', '金額はいくら', 'giá trị', 'gia tri', 'số tiền', 'so tien', 'amount', 'tổng tiền', 'tong tien'];
        $isMoneyQuestion = false;
        foreach ($moneyKeywords as $kw) {
            if (mb_strpos($msgLower, $kw) !== false) {
                $isMoneyQuestion = true;
                break;
            }
        }
        
        $progressKeywords = ['tiến độ', 'tien do', '進捗', 'progress', '進捗状況', '進捗率', 'tiến trình'];
        $isProgressQuestion = false;
        foreach ($progressKeywords as $kw) {
            if (mb_strpos($msgLower, $kw) !== false) {
                $isProgressQuestion = true;
                break;
            }
        }
        
        // Nếu có project ID cụ thể và user hỏi về tiền/amount/progress, fetch project data
        if ($projectIdForContext > 0 && ($isMoneyQuestion || $isProgressQuestion)) {
            // Fetch project data với ID cụ thể
            $specificProject = $this->getProjectsForContext(['id' => $projectIdForContext]);
            if (!empty($specificProject) && is_array($specificProject)) {
                // Thêm vào context['projects'] nếu chưa có hoặc thay thế nếu đã có
                if (!isset($context['projects']) || !is_array($context['projects'])) {
                    $context['projects'] = [];
                }
                // Merge project cụ thể vào đầu danh sách
                $context['projects'] = array_merge($specificProject, $context['projects']);
                // Đảm bảo contextKeys có 'projects'
                if (!in_array('projects', $contextKeys, true)) {
                    $contextKeys[] = 'projects';
                }
            }
        }
        
        if ($projectIdForContext > 0) {
            $parts[] = "[Current page] User is viewing or referring to project (案件) with id=" . $projectIdForContext . ". When the user refers to \"this project\", \"dự án này\", \"案件\", or does not specify project_id, use " . $projectIdForContext . " as default (id or params.project_id) in ACTION.";
            $teamsInDept = $this->getTeamsForProjectContext($projectIdForContext);
            if (!empty($teamsInDept)) {
                $parts[] = "[Teams in project department] (confirm exact team name when user adds/removes team by name): " . json_encode($teamsInDept, JSON_UNESCAPED_UNICODE);
            }
        }
        
        // Dữ liệu hiện tại của trang – chỉ là dữ liệu đang hiển thị, KHÔNG phải toàn bộ hệ thống
        if (!empty($pageContext['page_project']) && is_array($pageContext['page_project'])) {
            $parts[] = "[Current page - Project data] **This is only the data of the single project currently open on the user's screen, NOT the full project list.** Use this when the user asks about \"this project\", \"dự án này\", \"案件\", or details of the project they are viewing.\n" . json_encode($pageContext['page_project'], JSON_UNESCAPED_UNICODE);
        }
        if (isset($pageContext['page_projects']) && is_array($pageContext['page_projects'])) {
            $count = count($pageContext['page_projects']);
            $parts[] = "[Current page - Project list] **This is only the projects currently visible on this page (e.g. current table page), NOT all projects in the system.** When the user asks \"trên trang có mấy dự án\" / \"hiện tại trên trang có mấy dự án\" / \"số kiện đang hiển thị\" / \"how many projects on the page\", you MUST answer with the number of items in the array below: **" . $count . "**. Do not say \"no projects\" or \"không có dự án\" unless the array is empty. Reply e.g. \"Trên trang hiện có " . $count . " dự án.\" or \"Hiện tại trên trang có " . $count . " dự án.\"\n" . json_encode($pageContext['page_projects'], JSON_UNESCAPED_UNICODE);
        }
        if (!empty($pageContext['page_tasks']) && is_array($pageContext['page_tasks'])) {
            $parts[] = "[Current page - Tasks] **This is only the tasks currently displayed on the task manager page for this project, NOT necessarily all tasks in the system.** Use this when the user asks about tasks of this project (\"các task\", \"danh sách task\", \"タスク\").\n" . json_encode($pageContext['page_tasks'], JSON_UNESCAPED_UNICODE);
        }
        
        // Nếu có projects từ request (sau project_search), thông báo cho Gemini
        if ($projectsFromRequest !== null && is_array($projectsFromRequest) && !empty($projectsFromRequest)) {
            $parts[] = "[Search results from previous action] These projects were found from a previous search. Please answer the user's question about these projects using the [Project list] data below. If the user asked about progress (進捗/tiến độ), you MUST show the progress percentage (progress field, 0-100) for each project. Display progress as \"XX%\" format (e.g. progress=50 → \"50%\").";
        }
        
        if ($isProgressQuestion) {
            $parts[] = "[User is asking about progress] The user is asking about project progress (進捗/tiến độ). When displaying projects, you MUST include the progress column showing progress percentage (progress field, 0-100) as \"XX%\" format. Example: <th>進捗率</th> in table header and <td>50%</td> in table body.";
        }
        
        // Ưu tiên gửi statistics trước nếu có, để tiết kiệm token
        // Chỉ gửi project list khi user thực sự cần liệt kê dự án cụ thể
        $needsProjectList = in_array('projects', $contextKeys, true) && !in_array('statistics', $contextKeys, true);
        $needsStatistics = in_array('statistics', $contextKeys, true);
        $needsAvailability = in_array('availability', $contextKeys, true);
        $needsCustomer = in_array('customer', $contextKeys, true);
        
        // Customer info (parent_project): thông tin khách hàng – chỉ hiển thị 12 trường
        if ($needsCustomer && isset($context['customer']) && is_array($context['customer'])) {
            $parts[] = "[Customer info] **You MUST use this data to answer.** When displaying customer info (顧客情報), show **ONLY** these 12 fields in your response: 会社名, 支店名, 担当者名, 役職, メールアドレス, 電話番号, 携帯番号, FAX, 郵便番号, 住所1, 住所2, メモ. Do NOT add project_name, construction_number, project_number, or any other columns. The JSON below has exactly these keys — list or format them in your reply. Do NOT say you don't have the information.\n" . json_encode($context['customer'], JSON_UNESCAPED_UNICODE);
        } elseif ($needsCustomer && (empty($context['customer']) || !is_array($context['customer']))) {
            $parts[] = "[Customer info] No parent project (tòa nhà) found for the given ID or project_number. Tell the user in their language that the building was not found or they should check the ID/number.";
        }
        
        // Availability: nhóm/người chưa được phân công dự án trong khoảng thời gian (rảnh việc)
        if ($needsAvailability && isset($context['availability']) && is_array($context['availability'])) {
            $av = $context['availability'];
            $hasNote = !empty($av['note']);
            $parts[] = "[Availability] **You MUST use this data to answer. Do NOT say you cannot check or ask user to check in the app.** Teams and people NOT assigned to any project in the given period (rảnh việc / 空いている). free_teams = list of {id, name}; free_members = list of {id, realname}. If \"note\" is present, tell the user to select department first. Otherwise list free_teams and free_members in the user's language.\n" . json_encode($context['availability'], JSON_UNESCAPED_UNICODE);
        }
        
        // Nếu chỉ hỏi về thống kê → chỉ gửi statistics, không gửi project list
        // NHƯNG nếu có status filter hoặc search filters, vẫn cần gửi projects để Gemini có thể filter
        $hasFilters = ($statusFilter !== null && $statusFilter !== '') || !empty($searchFilters) || $originalTeamName !== null;
        if ($needsStatistics && !$needsProjectList && !$hasFilters) {
            if (isset($context['statistics']) && !empty($context['statistics'])) {
                $parts[] = "[Statistics]\n" . json_encode($context['statistics'], JSON_UNESCAPED_UNICODE);
                // Thêm note về status filter nếu có
                if ($statusFilter !== null && $statusFilter !== '') {
                    $statusMap = [
                        'in_progress' => '進行中',
                        'completed' => '完了',
                        'quotation' => '見積',
                        'contract' => '請負/契約',
                        'draft' => '受付',
                        'open' => '納期検討/開始',
                        'paused' => '一時停止',
                        'cancelled' => '中止'
                    ];
                    $statusLabel = isset($statusMap[$statusFilter]) ? $statusMap[$statusFilter] : $statusFilter;
                    if ($statusFilter === 'not_started') {
                        // "Chưa tiến hành" → total = số案件 chưa bắt đầu
                        $parts[] = "[Status filter applied] **IMPORTANT: The statistics above have been filtered by status group: not_started (" . $statusLabel . "). Here, overview.total is the number of projects that have NOT started yet (status IN ['quotation','draft','contract','open','confirming']). Do NOT compute \"not started\" as total-active; use overview.total directly.**";
                    } else {
                        $parts[] = "[Status filter applied] **IMPORTANT: The statistics above have been filtered by status: " . $statusFilter . " (" . $statusLabel . "). The counts (overview.total, overview.active, overview.completed, by_department[].count) only include projects with status=\"" . $statusFilter . "\". Use these filtered statistics to answer the user's question.**";
                    }
                }
            }
        } elseif ($needsStatistics && !$needsProjectList && $hasFilters) {
            // Có filters → cần cả projects để filter
            if (isset($context['statistics']) && !empty($context['statistics'])) {
                $parts[] = "[Statistics]\n" . json_encode($context['statistics'], JSON_UNESCAPED_UNICODE);
            }
            if (isset($context['projects']) && !empty($context['projects'])) {
                $parts[] = "[Project list]\n" . json_encode($context['projects'], JSON_UNESCAPED_UNICODE);
                // Thêm filter notes nếu có
                if ($statusFilter !== null && $statusFilter !== '') {
                    $statusMap = [
                        'in_progress' => '進行中',
                        'completed' => '完了',
                        'quotation' => '見積',
                        'contract' => '請負/契約',
                        'draft' => '受付',
                        'open' => '納期検討/開始',
                        'paused' => '一時停止',
                        'cancelled' => '中止'
                    ];
                    $statusLabel = isset($statusMap[$statusFilter]) ? $statusMap[$statusFilter] : $statusFilter;
                    $parts[] = "[Status filter] **MUST filter projects by status: " . $statusFilter . " (" . $statusLabel . "). Only show projects with status=\"" . $statusFilter . "\".";
                }
                if (!empty($searchFilters) || $originalTeamName !== null) {
                    $filterNotes = [];
                    if ($originalTeamName !== null) {
                        $filterNotes[] = "チーム名(team_names) = \"" . $originalTeamName . "\"";
                    }
                    if (isset($searchFilters['team_name'])) {
                        $filterNotes[] = "チーム名(team_names) = \"" . $searchFilters['team_name'] . "\"";
                    }
                    if (isset($searchFilters['person_name'])) {
                        $filterNotes[] = "メンバー/マネージャー名(person_name) = \"" . $searchFilters['person_name'] . "\" - manager_names または member_names にこの名前を含む案件を優先して回答すること";
                        $filterNotes[] = "メンバー/マネージャー名(member_names/manager_names) に \"" . $searchFilters['person_name'] . "\" を含む";
                    }
                    if (isset($searchFilters['branch_name'])) {
                        $filterNotes[] = "支店名(branch_name) = \"" . $searchFilters['branch_name'] . "\"";
                    }
                    if (isset($searchFilters['company_name'])) {
                        $filterNotes[] = "顧客名(company_name) = \"" . $searchFilters['company_name'] . "\"";
                    }
                    if (isset($searchFilters['project_name'])) {
                        $filterNotes[] = "建物名(project_name) = \"" . $searchFilters['project_name'] . "\"";
                    }
                    if (isset($searchFilters['contact_name'])) {
                        $filterNotes[] = "担当者名(contact_name) = \"" . $searchFilters['contact_name'] . "\"";
                    }
                    if (isset($searchFilters['construction_number'])) {
                        $filterNotes[] = "工事番号(construction_number) = \"" . $searchFilters['construction_number'] . "\"";
                    }
                    if (isset($searchFilters['tantou'])) {
                        $filterNotes[] = "担当会社(tantou) = \"" . $searchFilters['tantou'] . "\" (CAILY/GUIS)";
                    }
                    if (isset($searchFilters['tantou'])) {
                        $filterNotes[] = "担当会社(tantou) = \"" . $searchFilters['tantou'] . "\" (CAILY/GUIS)";
                    }
                    if (isset($searchFilters['overdue']) && $searchFilters['overdue']) {
                        $filterNotes[] = "期限切れ(overdue) = true (end_date < 現在時刻 AND status NOT IN ['completed','cancelled','deleted']) - **Only consider projects whose deadlines have already passed and are not yet completed/cancelled**";
                    }
                    // Date filters - tìm các dự án có khoảng thời gian (start_date đến end_date) chứa thời gian đó
                    if (isset($searchFilters['date']) && !empty($searchFilters['date'])) {
                        // Format ngày để hiển thị cho Gemini (YYYY年MM月DD日)
                        $dateParts = explode('-', $searchFilters['date']);
                        if (count($dateParts) == 3) {
                            $year = intval($dateParts[0]);
                            $month = intval($dateParts[1]);
                            $day = intval($dateParts[2]);
                            $dateFormatted = sprintf('%d年%d月%d日', $year, $month, $day);
                            $filterNotes[] = "日付(date) = \"" . $searchFilters['date'] . "\" (" . $dateFormatted . ") - ngày này nằm trong khoảng start_date đến end_date của dự án";
                        } else {
                            $filterNotes[] = "日付(date) = \"" . $searchFilters['date'] . "\" (ngày này nằm trong khoảng start_date đến end_date của dự án)";
                        }
                    }
                    if (isset($searchFilters['date_type']) && !empty($searchFilters['date_type'])) {
                        $dateTypeLabels = [
                            'yesterday' => '昨日',
                            'today' => '今日',
                            'tomorrow' => '明日',
                            'this_week' => '今週',
                            'last_week' => '先週',
                            'this_month' => '今月',
                            'last_month' => '先月'
                        ];
                        $dateLabel = isset($dateTypeLabels[$searchFilters['date_type']]) ? $dateTypeLabels[$searchFilters['date_type']] : $searchFilters['date_type'];
                        $dateNote = "日付(date_type) = \"" . $searchFilters['date_type'] . "\" (" . $dateLabel;
                        // Thêm ngày cụ thể nếu có
                        if (isset($searchFilters['date']) && !empty($searchFilters['date'])) {
                            $dateParts = explode('-', $searchFilters['date']);
                            if (count($dateParts) == 3) {
                                $year = intval($dateParts[0]);
                                $month = intval($dateParts[1]);
                                $day = intval($dateParts[2]);
                                $dateFormatted = sprintf('%d年%d月%d日', $year, $month, $day);
                                $dateNote .= " = " . $dateFormatted;
                            }
                        } elseif (isset($searchFilters['date_start']) && isset($searchFilters['date_end'])) {
                            // Date range
                            $startParts = explode('-', $searchFilters['date_start']);
                            $endParts = explode('-', $searchFilters['date_end']);
                            if (count($startParts) == 3 && count($endParts) == 3) {
                                $startFormatted = sprintf('%d年%d月%d日', intval($startParts[0]), intval($startParts[1]), intval($startParts[2]));
                                $endFormatted = sprintf('%d年%d月%d日', intval($endParts[0]), intval($endParts[1]), intval($endParts[2]));
                                $dateNote .= " = " . $startFormatted . " ～ " . $endFormatted;
                            }
                        }
                        $dateNote .= ") - khoảng thời gian này nằm trong khoảng start_date đến end_date của dự án";
                        $filterNotes[] = $dateNote;
                    }
                    if (!empty($filterNotes)) {
                        $parts[] = "[Active search filters] **MUST filter projects by these conditions:** " . implode(", ", $filterNotes) . ". Only show projects that match ALL these filters. If no projects match, say \"該当する案件が見つかりませんでした\".";
                    }
                }
            }
        } 
        // Nếu cần cả hai → gửi statistics trước, rồi mới project list
        elseif ($needsStatistics && $needsProjectList) {
            if (isset($context['statistics']) && !empty($context['statistics'])) {
                $parts[] = "[Statistics]\n" . json_encode($context['statistics'], JSON_UNESCAPED_UNICODE);
            }
            if (isset($context['projects']) && !empty($context['projects'])) {
                $parts[] = "[Project list]\n" . json_encode($context['projects'], JSON_UNESCAPED_UNICODE);
                // Thêm status filter note nếu có
                if ($statusFilter !== null && $statusFilter !== '') {
                    $statusMap = [
                        'in_progress' => '進行中',
                        'completed' => '完了',
                        'quotation' => '見積',
                        'contract' => '請負/契約',
                        'draft' => '受付',
                        'open' => '納期検討/開始',
                        'paused' => '一時停止',
                        'cancelled' => '中止'
                    ];
                    $statusLabel = isset($statusMap[$statusFilter]) ? $statusMap[$statusFilter] : $statusFilter;
                    $parts[] = "[Status filter] **MUST filter projects by status: " . $statusFilter . " (" . $statusLabel . "). Only show projects with status=\"" . $statusFilter . "\". If no projects match, say \"該当する案件が見つかりませんでした\".";
                }
                // Nếu có search filters, nhắc Gemini phải filter theo các điều kiện đó
                // Sử dụng originalTeamName nếu có (vì team_name đã bị unset trong fetchContextByKeys)
                if (!empty($searchFilters) || $originalTeamName !== null) {
                    $filterNotes = [];
                    if ($originalTeamName !== null) {
                        $filterNotes[] = "チーム名(team_names) = \"" . $originalTeamName . "\"";
                    }
                    if (isset($searchFilters['team_name'])) {
                        $filterNotes[] = "チーム名(team_names) = \"" . $searchFilters['team_name'] . "\"";
                    }
                    if (isset($searchFilters['branch_name'])) {
                        $filterNotes[] = "支店名(branch_name) = \"" . $searchFilters['branch_name'] . "\"";
                    }
                    if (isset($searchFilters['company_name'])) {
                        $filterNotes[] = "顧客名(company_name) = \"" . $searchFilters['company_name'] . "\"";
                    }
                    if (isset($searchFilters['project_name'])) {
                        $filterNotes[] = "建物名(project_name) = \"" . $searchFilters['project_name'] . "\"";
                    }
                    if (isset($searchFilters['contact_name'])) {
                        $filterNotes[] = "担当者名(contact_name) = \"" . $searchFilters['contact_name'] . "\"";
                    }
                    if (isset($searchFilters['construction_number'])) {
                        $filterNotes[] = "工事番号(construction_number) = \"" . $searchFilters['construction_number'] . "\"";
                    }
                    // Date filters - tìm các dự án có khoảng thời gian (start_date đến end_date) chứa thời gian đó
                    if (isset($searchFilters['date']) && !empty($searchFilters['date'])) {
                        // Format ngày để hiển thị cho Gemini (YYYY年MM月DD日)
                        $dateParts = explode('-', $searchFilters['date']);
                        if (count($dateParts) == 3) {
                            $year = intval($dateParts[0]);
                            $month = intval($dateParts[1]);
                            $day = intval($dateParts[2]);
                            $dateFormatted = sprintf('%d年%d月%d日', $year, $month, $day);
                            $filterNotes[] = "日付(date) = \"" . $searchFilters['date'] . "\" (" . $dateFormatted . ") - ngày này nằm trong khoảng start_date đến end_date của dự án";
                        } else {
                            $filterNotes[] = "日付(date) = \"" . $searchFilters['date'] . "\" (ngày này nằm trong khoảng start_date đến end_date của dự án)";
                        }
                    }
                    if (isset($searchFilters['date_type']) && !empty($searchFilters['date_type'])) {
                        $dateTypeLabels = [
                            'yesterday' => '昨日',
                            'today' => '今日',
                            'tomorrow' => '明日',
                            'this_week' => '今週',
                            'last_week' => '先週',
                            'this_month' => '今月',
                            'last_month' => '先月'
                        ];
                        $dateLabel = isset($dateTypeLabels[$searchFilters['date_type']]) ? $dateTypeLabels[$searchFilters['date_type']] : $searchFilters['date_type'];
                        $dateNote = "日付(date_type) = \"" . $searchFilters['date_type'] . "\" (" . $dateLabel;
                        // Thêm ngày cụ thể nếu có
                        if (isset($searchFilters['date']) && !empty($searchFilters['date'])) {
                            $dateParts = explode('-', $searchFilters['date']);
                            if (count($dateParts) == 3) {
                                $year = intval($dateParts[0]);
                                $month = intval($dateParts[1]);
                                $day = intval($dateParts[2]);
                                $dateFormatted = sprintf('%d年%d月%d日', $year, $month, $day);
                                $dateNote .= " = " . $dateFormatted;
                            }
                        } elseif (isset($searchFilters['date_start']) && isset($searchFilters['date_end'])) {
                            // Date range
                            $startParts = explode('-', $searchFilters['date_start']);
                            $endParts = explode('-', $searchFilters['date_end']);
                            if (count($startParts) == 3 && count($endParts) == 3) {
                                $startFormatted = sprintf('%d年%d月%d日', intval($startParts[0]), intval($startParts[1]), intval($startParts[2]));
                                $endFormatted = sprintf('%d年%d月%d日', intval($endParts[0]), intval($endParts[1]), intval($endParts[2]));
                                $dateNote .= " = " . $startFormatted . " ～ " . $endFormatted;
                            }
                        }
                        $dateNote .= ") - khoảng thời gian này nằm trong khoảng start_date đến end_date của dự án";
                        $filterNotes[] = $dateNote;
                    }
                    if (!empty($filterNotes)) {
                        $parts[] = "[Active search filters] **MUST filter projects by these conditions:** " . implode(", ", $filterNotes) . ". Only show projects that match ALL these filters. If no projects match, say \"該当する案件が見つかりませんでした\".";
                    }
                }
            }
        }
        // Chỉ cần project list → gửi project list
        elseif ($needsProjectList) {
            // Tạo danh sách filter notes để gửi cho Gemini
            $filterNotes = [];
            
            // Thêm status filter note nếu có
            if ($statusFilter !== null && $statusFilter !== '') {
                $statusMap = [
                    'in_progress' => '進行中',
                    'completed' => '完了',
                    'quotation' => '見積',
                    'contract' => '請負/契約',
                    'draft' => '受付',
                    'open' => '納期検討/開始',
                    'paused' => '一時停止',
                    'cancelled' => '中止'
                ];
                $statusLabel = isset($statusMap[$statusFilter]) ? $statusMap[$statusFilter] : $statusFilter;
                $filterNotes[] = "ステータス(status) = \"" . $statusFilter . "\" (" . $statusLabel . ")";
            }
            
            // Nếu có search filters, nhắc Gemini phải filter theo các điều kiện đó
            // Sử dụng originalTeamName nếu có (vì team_name đã bị unset trong fetchContextByKeys)
            if (!empty($searchFilters) || $originalTeamName !== null) {
                if ($originalTeamName !== null) {
                    $filterNotes[] = "チーム名(team_names) = \"" . $originalTeamName . "\"";
                }
                if (isset($searchFilters['team_name'])) {
                    $filterNotes[] = "チーム名(team_names) = \"" . $searchFilters['team_name'] . "\"";
                }
                if (isset($searchFilters['person_name'])) {
                    $filterNotes[] = "メンバー/マネージャー名(person_name) = \"" . $searchFilters['person_name'] . "\" - manager_names または member_names にこの名前を含む案件を優先して回答すること";
                }
                if (isset($searchFilters['branch_name'])) {
                    $filterNotes[] = "支店名(branch_name) = \"" . $searchFilters['branch_name'] . "\"";
                }
                if (isset($searchFilters['company_name'])) {
                    $filterNotes[] = "顧客名(company_name) = \"" . $searchFilters['company_name'] . "\"";
                }
                if (isset($searchFilters['project_name'])) {
                    $filterNotes[] = "建物名(project_name) = \"" . $searchFilters['project_name'] . "\"";
                }
                if (isset($searchFilters['contact_name'])) {
                    $filterNotes[] = "担当者名(contact_name) = \"" . $searchFilters['contact_name'] . "\"";
                }
                if (isset($searchFilters['construction_number'])) {
                    $filterNotes[] = "工事番号(construction_number) = \"" . $searchFilters['construction_number'] . "\"";
                }
            }
            
            if (isset($context['projects']) && !empty($context['projects'])) {
                $parts[] = "[Project list]\n" . json_encode($context['projects'], JSON_UNESCAPED_UNICODE);
                
                // Gửi filter notes cho Gemini nếu có
                if (!empty($filterNotes)) {
                    $parts[] = "[Active filters] **IMPORTANT: The projects in the list above have already been filtered by the database query using these conditions:** " . implode(" AND ", $filterNotes) . ". **You MUST only show projects that match ALL these filters.** The data you received is already filtered, so you can directly list the projects. If the list is empty, say \"該当する案件が見つかりませんでした\" (No matching projects found).";
                }
            } elseif (isset($context['projects']) && is_array($context['projects']) && empty($context['projects'])) {
                // Có request projects nhưng không có dữ liệu → thông báo cho Gemini
                $filterDesc = [];
                if ($originalTeamName !== null) {
                    $filterDesc[] = "チーム名(team_names) = \"" . $originalTeamName . "\"";
                }
                if (isset($searchFilters['team_name'])) {
                    $filterDesc[] = "チーム名(team_names) = \"" . $searchFilters['team_name'] . "\"";
                }
                if ($statusFilter !== null && $statusFilter !== '') {
                    $statusMap = [
                        'in_progress' => '進行中',
                        'completed' => '完了',
                        'quotation' => '見積',
                        'contract' => '請負/契約',
                        'draft' => '受付',
                        'open' => '納期検討/開始',
                        'paused' => '一時停止',
                        'cancelled' => '中止'
                    ];
                    $statusLabel = isset($statusMap[$statusFilter]) ? $statusMap[$statusFilter] : $statusFilter;
                    $filterDesc[] = "ステータス(status) = \"" . $statusFilter . "\" (" . $statusLabel . ")";
                }
                if (!empty($filterDesc)) {
                    $parts[] = "[No projects found] User requested to list projects filtered by: " . implode(" AND ", $filterDesc) . ". However, no projects match these filters. Please inform the user: \"該当する案件が見つかりませんでした\" (No matching projects found).";
                } else {
                    $parts[] = "[No projects found] User requested to list projects, but no project data is available. Please inform the user that they need to select a department first or check the app for project details.";
                }
            } elseif ($statusFilter !== null && $statusFilter !== '') {
                // Có status filter nhưng không có projects → thông báo cho Gemini
                $statusMap = [
                    'in_progress' => '進行中',
                    'completed' => '完了',
                    'quotation' => '見積',
                    'contract' => '請負/契約',
                    'draft' => '受付',
                    'open' => '納期検討/開始',
                    'paused' => '一時停止',
                    'cancelled' => '中止'
                ];
                $statusLabel = isset($statusMap[$statusFilter]) ? $statusMap[$statusFilter] : $statusFilter;
                $parts[] = "[Status filter requested] User requested to filter projects by status: " . $statusFilter . " (" . $statusLabel . "). However, no project data is available. Please inform the user that they need to select a department first or check the app for project details.";
            }
        }
        $parts[] = "Q: " . trim($messages);
        $userPrompt = implode("\n\n", $parts);

        $useCache = self::$USE_CONTEXT_CACHE && ($cacheName = $this->createOrGetSystemPromptCache($apiKey));
        if ($useCache) {
            $fullUserText = $variablePrefix . "\n\n" . $userPrompt;
            $payload = json_encode([
                "contents" => [
                    ["role" => "user", "parts" => [["text" => $fullUserText]]]
                ],
                "cachedContent" => $cacheName
            ]);
        } else {
            $payload = json_encode([
                "contents" => [
                    ["role" => "model", "parts" => [["text" => $systemPrompt]]],
                    ["role" => "user", "parts" => [["text" => $userPrompt]]]
                ]
            ]);
        }

        // error_log để kiểm tra system_prompt và dữ liệu gửi Gemini (cắt bớt nếu quá dài)
        $maxLog = 80000;
       
        error_log("[AI sendToGemini] payload (contents): " . substr($payload, 0, $maxLog) . (strlen($payload) > $maxLog ? '...[' . strlen($payload) . ']' : ''));

        $headers = [
            "Content-Type: application/json",
            "x-goog-api-key: " . $apiKey,
        ];
    
        $ch = curl_init($modelUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log("Lỗi CURL: " . $error);
            return null;
        }
    
        curl_close($ch);
    
        $responseData = json_decode($response, true);
        if (is_array($responseData)) {
            $responseData = self::decodeUnicodeInResponse($responseData);
        }

        if (is_array($responseData) && isset($responseData['usageMetadata']) && is_array($responseData['usageMetadata'])) {
            $u = $responseData['usageMetadata'];
            $p = isset($u['promptTokenCount']) ? (int)$u['promptTokenCount'] : (isset($u['prompt_token_count']) ? (int)$u['prompt_token_count'] : 0);
            $c = isset($u['candidatesTokenCount']) ? (int)$u['candidatesTokenCount'] : (isset($u['candidates_token_count']) ? (int)$u['candidates_token_count'] : 0);
            $t = isset($u['totalTokenCount']) ? (int)$u['totalTokenCount'] : (isset($u['total_token_count']) ? (int)$u['total_token_count'] : $p + $c);
            error_log("[AI sendToGemini] token usage: prompt=" . $p . ", candidates=" . $c . ", total=" . $t);
        }

        $this->chatHistory[] = [
            'role' => 'user',
            'content' => array(
                'text' => $messages
            )
        ];
        $this->chatHistory[] = [
            'role' => 'model',
            'content' => $responseData['candidates'][0]['content']['parts'] ?? []
        ];
        $this->saveChatHistory();

        return $responseData;
    }

    /**
     * Remove ACTION: {...} block(s) from text so it is not shown in chat display/history.
     * Supports multiple ACTION blocks.
     */
    private function stripActionFromText($text) {
        if (!is_string($text) || $text === '') return $text;
        $stripped = preg_replace('/ACTION:\s*(\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\})\s*/s', '', $text);
        return trim($stripped);
    }

    /** Max chat history items to return for display (50 most recent). */
    private static $CHAT_HISTORY_DISPLAY_MAX = 50;

    /**
     * Return chat history for frontend to restore on page load. JSON: { history: [ {role, content} ] }. Last 50 items only.
     */
    public function getChatHistory() {
        header('Content-Type: application/json; charset=utf-8');
        $out = [];
        $history = array_slice($this->chatHistory, -self::$CHAT_HISTORY_DISPLAY_MAX);
        foreach ($history as $item) {
            $entry = ['role' => $item['role'] ?? ''];
            if ($entry['role'] === 'user' && isset($item['content']['text'])) {
                $entry['content'] = ['text' => $item['content']['text']];
            } elseif ($entry['role'] === 'model') {
                $parts = isset($item['content']['parts']) ? $item['content']['parts'] : (is_array($item['content']) ? $item['content'] : []);
                $text = '';
                foreach ($parts as $part) {
                    $text .= isset($part['text']) ? $part['text'] : '';
                }
                $text = $this->stripActionFromText($text);
                $entry['content'] = ['parts' => [['text' => $text]]];
            } else {
                continue;
            }
            $out[] = $entry;
        }
        echo json_encode(['history' => $out], JSON_UNESCAPED_UNICODE);
    }

    function getProjectsFromDatabase() {
        $query = "SELECT id, name, description, deadline, status FROM projects"; // Thay 'projects' bằng tên bảng của bạn
        $result = $this->db->query($query);
    
        if (!$result) {
            return ['error' => 'Failed to fetch projects'];
        }
    
        $projects = [];
        while ($row = $result->fetch_assoc()) {
            $projects[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'deadline' => $row['deadline'],
                'status' => $row['status']
            ];
        }
    
        return $projects;
    }

    function getProjectDetailsFromDatabase($projectId) {
        if (!$projectId) {
            return ['error' => 'Project ID is required'];
        }
    
        $query = "SELECT id, name, description, deadline, status FROM projects WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $projectId);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows === 0) {
            return ['error' => 'Project not found'];
        }
    
        return $result->fetch_assoc();
    }

    function sendToGeminiWithData($data, $apiKey) {
        $modelUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent";
    
        // System prompt để phân tích dữ liệu
        $systemPrompt = "Dưới đây là danh sách dữ liệu cần phân tích:\n\n" . json_encode($data) . "\n\nHãy phân tích và trả lời câu hỏi của người dùng.";
    
        // Construct the payload
        $payload = json_encode([
            "contents" => [
                [
                    "role" => "system", // System prompt to set the context
                    "parts" => [
                        [
                            "text" => $systemPrompt
                        ]
                    ]
                ]
            ]
        ]);
    
        // Set headers
        $headers = [
            "Content-Type: application/json",
            "x-goog-api-key: " . $apiKey,
        ];
    
        // Initialize cURL
        $ch = curl_init($modelUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
        // Execute the request
        $response = curl_exec($ch);
    
        // Handle errors
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log("Lỗi CURL: " . $error);
            return null;
        }
    
        curl_close($ch);
    
        // Decode and return the response
        $responseData = json_decode($response, true);
        return $responseData;
    }

    /**
     * Phase 3.1 & 3.2 – Send to Gemini with extra context (scheduling or assignment).
     * Builds system prompt with user context + context data + instruction. No DB writes.
     */
    private function sendToGeminiWithContext($userMessage, $contextJson, $instructionSuffix, $apiKey) {
        $modelUrl = "https://generativelanguage.googleapis.com/v1beta/models/" . $this->ai_model . ":generateContent";
        $userContext = $this->buildUserContextForGemini();
        $systemPrompt = "私はGUIS社のAIアシスタントです。ユーザーの質問に丁寧に応答します。常に日本語で応答します。\n";
        $systemPrompt .= "[Current user context]\n" . json_encode($userContext) . "\n\n";
        $systemPrompt .= "[Context data for this request]\n" . $contextJson . "\n\n";
        $systemPrompt .= $instructionSuffix;

        $payload = json_encode([
            "contents" => [
                [
                    "role" => "model",
                    "parts" => [["text" => $systemPrompt]]
                ],
                [
                    "role" => "user",
                    "parts" => [["text" => $userMessage]]
                ]
            ]
        ]);
        $headers = ["Content-Type: application/json", "x-goog-api-key: " . $apiKey];
        $ch = curl_init($modelUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            curl_close($ch);
            return null;
        }
        curl_close($ch);
        return json_decode($response, true);
    }

    /**
     * Phase 3.1 – Scheduling suggestion flow.
     * Gathers project and task context, sends to Gemini for schedule/workload suggestions only. No DB writes.
     */
    public function schedulingSuggestion() {
        $apiKey = $this->api_key;
        if (!$apiKey) {
            http_response_code(500);
            echo json_encode(['error' => 'API Key not configured']);
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $project_id = isset($input['project_id']) ? intval($input['project_id']) : (isset($_GET['project_id']) ? intval($_GET['project_id']) : 0);
        $message = isset($input['message']) ? $input['message'] : 'スケジュールや工数の提案をしてください。';

        $projects = $project_id > 0 ? $this->getProjectsForContext(['id' => $project_id]) : $this->getProjectsForContext(['limit' => 10]);
        $tasks = $project_id > 0 ? $this->getTasksForContext($project_id, ['limit' => 100, 'include_subtasks' => true]) : [];
        $context = ['projects' => $projects, 'tasks' => $tasks];
        $instruction = "上記のプロジェクト・タスク情報を元に、スケジュールや工数・マイルストーンの提案のみ行ってください。データの変更は行いません。実際の反映はユーザーがアプリで行い、編集権限のあるユーザーのみが変更できます。日本語で回答してください。";
        $response = $this->sendToGeminiWithContext($message, json_encode($context), $instruction, $apiKey);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response ?: ['error' => 'No response from AI'], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Phase 3.2 – Team/member assignment suggestion flow.
     * Gathers project context, sends to Gemini for assignment suggestions only. No DB writes.
     */
    public function assignmentSuggestion() {
        $apiKey = $this->api_key;
        if (!$apiKey) {
            http_response_code(500);
            echo json_encode(['error' => 'API Key not configured']);
            return;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $project_id = isset($input['project_id']) ? intval($input['project_id']) : (isset($_GET['project_id']) ? intval($_GET['project_id']) : 0);
        $message = isset($input['message']) ? $input['message'] : 'このプロジェクトへの担当・メンバー割り当てを提案してください。';

        $projects = $project_id > 0 ? $this->getProjectsForContext(['id' => $project_id]) : $this->getProjectsForContext(['limit' => 5]);
        $context = ['projects' => $projects];
        $instruction = "上記のプロジェクト情報を元に、担当者・メンバー割り当ての提案のみ行ってください。データの変更は行いません。実際の反映はユーザーがアプリで行い、編集権限のあるユーザーのみが変更できます。日本語で回答してください。";
        $response = $this->sendToGeminiWithContext($message, json_encode($context), $instruction, $apiKey);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response ?: ['error' => 'No response from AI'], JSON_UNESCAPED_UNICODE);
    }
}