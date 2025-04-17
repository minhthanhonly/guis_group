<?php
class AI extends ApplicationModel{

    public function __construct(){
        parent::__construct();
    }

    public function index() {
        $method = $_GET["method"];
        if ($method == "chat") {
            $this->chat();
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Method not found']);
        }
    }

    public function chat() {
        // Lấy API Key từ .env
        $apiKey = getenv('GEMINI_API_KEY', TRUE);
        if (!$apiKey) {
            http_response_code(500);
            echo json_encode(['error' => 'API Key not configured']);
            return;
        }
    
        // Lấy dữ liệu từ yêu cầu POST
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['message'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Message is required']);
            return;
        }
    
        $message = $input['message'];
    
        // Gửi câu hỏi đến Gemini để xác định hành động
        $response = $this->sendToGemini($message, $apiKey);
    
        // Kiểm tra phản hồi từ Gemini
        if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            $aiResponse = json_decode($response['candidates'][0]['content']['parts'][0]['text'], true);
    
            if (isset($aiResponse['action'])) {
                $action = $aiResponse['action'];
                $parameters = $aiResponse['parameters'] ?? [];
    
                // Thực hiện hành động dựa trên action
                switch ($action) {
                    case 'get_projects':
                        $data = $this->getProjectsFromDatabase();
                        break;
    
                    case 'get_project_details':
                        $projectId = $parameters['project_id'] ?? null;
                        $data = $this->getProjectDetailsFromDatabase($projectId);
                        break;
    
                    default:
                        $data = ['error' => 'Unknown action'];
                }
    
                // Gửi dữ liệu đến Gemini để phân tích
                $analysisResponse = $this->sendToGeminiWithData($data, $apiKey);
    
                // Trả về kết quả phân tích
                header('Content-Type: application/json');
                echo json_encode($analysisResponse);
                return;
            }
        }
    
        // Nếu không có hành động, trả về phản hồi gốc từ AI
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    function sendToGemini($messages, $apiKey) {
       
        $modelUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro-exp-03-25:generateContent";

        $systemPrompt = "Bạn là một trợ lý AI thông minh tên là Ái. Dựa trên câu hỏi của người dùng, hãy xác định hành động cần thực hiện. Các hành động có thể bao gồm:\n
        - get_projects: Lấy danh sách các dự án.\n
        - get_project_details: Lấy thông tin chi tiết về một dự án cụ thể.\n
        - other: Nếu không xác định được hành động, trả về 'other'.\n
        Hãy trả về hành động dưới dạng JSON với cấu trúc:\n
        { \"action\": \"<action_name>\", \"parameters\": { ... } }.";

    
        $payload = json_encode([
            "contents" => [
                [
                    "role" => "system", // System prompt to set the context
                    "parts" => [
                        [
                            "text" => $systemPrompt
                        ]
                    ]
                ],
                [
                    "role" => "user", // Specify the role as "user"
                    "parts" => [
                        [
                            "text" => $messages // The text content of the message,
                        ]
                    ]
                ]
            ]]);
    
        $headers = [
            "Content-Type: application/json",
            "x-goog-api-key: " . $apiKey,
        ];
    
        $ch = curl_init($modelUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
        $response = curl_exec($ch);
    
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log("Lỗi CURL: " . $error);
            return null;
        }
    
        curl_close($ch);
    
        $responseData = json_decode($response, true);
        return $responseData;
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
        $modelUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro-exp-03-25:generateContent";
    
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
}