<?php

class PriceList extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'price_list_products';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'code' => array('notnull'),
            'name' => array('notnull'),
            'department_id' => array('notnull'),
            'unit' => array('notnull'),
            'price' => array('notnull'),
            'notes' => array(),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search'))
        );
        $this->connect();
    }

    function list() {
        $draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
        $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
        $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $order_column = isset($_GET['order_column']) ? $_GET['order_column'] : 'code';
        $order_dir = isset($_GET['order_dir']) ? $_GET['order_dir'] : 'ASC';
        $department_filter = isset($_GET['department_id']) ? $_GET['department_id'] : '';
        
        $whereArr = [];
        
        // Add permission check
        $user_id = $_SESSION['id'];
        if ($_SESSION['authority'] != 'administrator') {
            $whereArr[] = sprintf("p.created_by = %d", $user_id);
        }

        // Department filter
        if (!empty($department_filter)) {
            $whereArr[] = sprintf("p.department_id = %d", intval($department_filter));
        }

        // Search functionality
        if (!empty($search)) {
            $search = $this->quote($search);
            $whereArr[] = "(p.code LIKE '%$search%' 
                OR p.name LIKE '%$search%' 
                OR p.unit LIKE '%$search%')";
        }

        $where = !empty($whereArr) ? "WHERE " . implode(" AND ", $whereArr) : "";
        
        // Get total count
        $countQuery = sprintf("SELECT COUNT(*) as total FROM %s p %s", $this->table, $where);
        $totalRecords = $this->fetchOne($countQuery)['total'];
        
        // Get filtered count
        $filteredRecords = $totalRecords;
        
        // Order by
        $orderBy = "ORDER BY p.$order_column $order_dir";
        
        // Get data for current page
        $query = sprintf(
            "SELECT p.*, d.name as department_name
             FROM %s p 
             LEFT JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id
             %s %s LIMIT %d, %d",
            $this->table,
            $where,
            $orderBy,
            $start,
            $length
        );
        
        $data = $this->fetchAll($query);

        return array(
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data
        );
    }

    function create($params = null) {
        // Validate and sanitize input to ensure UTF-8 MB4 compatibility
        $code = isset($_POST['code']) ? $this->validateUTF8MB4($_POST['code']) : '';
        $name = isset($_POST['name']) ? $this->validateUTF8MB4($_POST['name']) : '';
        $unit = isset($_POST['unit']) ? $this->validateUTF8MB4($_POST['unit']) : '';
        $notes = isset($_POST['notes']) ? $this->validateUTF8MB4($_POST['notes']) : '';
        
        $data = array(
            'code' => $code,
            'name' => $name,
            'department_id' => isset($_POST['department_id']) ? intval($_POST['department_id']) : 0,
            'unit' => $unit,
            'price' => isset($_POST['price']) ? floatval($_POST['price']) : 0,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s')
        );

        // Validate required fields
        if (empty($data['code'])) {
            return [
                'status' => 'error',
                'message' => 'コードは必須です'
            ];
        }

        if (empty($data['name'])) {
            return [
                'status' => 'error',
                'message' => '商品名は必須です'
            ];
        }

        if (empty($data['department_id'])) {
            return [
                'status' => 'error',
                'message' => '部署は必須です'
            ];
        }

        if (empty($data['unit'])) {
            return [
                'status' => 'error',
                'message' => '単位は必須です'
            ];
        }

        if ($data['price'] <= 0) {
            return [
                'status' => 'error',
                'message' => '有効な単価を入力してください'
            ];
        }

        // Check if code already exists
        $existingQuery = sprintf(
            "SELECT id FROM %s WHERE code = '%s'",
            $this->table,
            $this->quote($data['code'])
        );
        $existing = $this->fetchOne($existingQuery);
        
        if ($existing) {
            return [
                'status' => 'error',
                'message' => 'このコードは既に使用されています'
            ];
        }

        // Insert product data
        $product_id = $this->query_insert($data);
        
        if (!$product_id) {
            return [
                'status' => 'error',
                'message' => '商品の作成に失敗しました'
            ];
        }

        return [
            'status' => 'success',
            'product_id' => $product_id,
            'message' => '商品を作成しました'
        ];
    }

    function update($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'message' => '商品IDが指定されていません'];
        
        // Validate and sanitize input to ensure UTF-8 MB4 compatibility
        $code = isset($_POST['code']) ? $this->validateUTF8MB4($_POST['code']) : '';
        $name = isset($_POST['name']) ? $this->validateUTF8MB4($_POST['name']) : '';
        $unit = isset($_POST['unit']) ? $this->validateUTF8MB4($_POST['unit']) : '';
        $notes = isset($_POST['notes']) ? $this->validateUTF8MB4($_POST['notes']) : '';
        
        $data = array(
            'code' => $code,
            'name' => $name,
            'department_id' => isset($_POST['department_id']) ? intval($_POST['department_id']) : 0,
            'unit' => $unit,
            'price' => isset($_POST['price']) ? floatval($_POST['price']) : 0,
            'notes' => $notes,
            'updated_at' => date('Y-m-d H:i:s')
        );

        // Validate required fields
        if (empty($data['code'])) {
            return [
                'status' => 'error',
                'message' => 'コードは必須です'
            ];
        }

        if (empty($data['name'])) {
            return [
                'status' => 'error',
                'message' => '商品名は必須です'
            ];
        }

        if (empty($data['department_id'])) {
            return [
                'status' => 'error',
                'message' => '部署は必須です'
            ];
        }

        if (empty($data['unit'])) {
            return [
                'status' => 'error',
                'message' => '単位は必須です'
            ];
        }

        if ($data['price'] <= 0) {
            return [
                'status' => 'error',
                'message' => '有効な単価を入力してください'
            ];
        }

        // Check if code already exists for other products
        $existingQuery = sprintf(
            "SELECT id FROM %s WHERE code = '%s' AND id != %d",
            $this->table,
            $this->quote($data['code']),
            $id
        );
        $existing = $this->fetchOne($existingQuery);
        
        if ($existing) {
            return [
                'status' => 'error',
                'message' => 'このコードは既に使用されています'
            ];
        }

        try {
            $result = $this->query_update($data, ['id' => $id]);
            
            if ($result) {
                return ['status' => 'success', 'message' => '商品を更新しました'];
            } else {
                return ['status' => 'error', 'message' => '更新に失敗しました'];
            }
        } catch (Exception $e) {
            error_log('Price list update error: ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'データベースエラー: ' . $e->getMessage()];
        }
    }

    function delete($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'message' => '商品IDが指定されていません'];

        $result = $this->query_delete(['id' => $id]);
        
        if ($result) {
            return ['status' => 'success', 'message' => '商品を削除しました'];
        } else {
            return ['status' => 'error', 'message' => '削除に失敗しました'];
        }
    }

    function getById($params = null) {
        // Handle both direct ID parameter and params array from API
        if (is_array($params)) {
            $id = isset($params['id']) ? $params['id'] : 0;
        } else {
            $id = $params;
        }
        
        $query = sprintf(
            "SELECT p.*, d.name as department_name 
             FROM %s p 
             LEFT JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id 
             WHERE p.id = %d",
            $this->table,
            intval($id)
        );
        return $this->fetchOne($query);
    }

    function getAllProducts() {
        $query = sprintf(
            "SELECT p.*, d.name as department_name 
             FROM %s p 
             LEFT JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id 
             ORDER BY p.code",
            $this->table
        );
        return $this->fetchAll($query);
    }

    function getProductsByDepartment($department_id) {
        $query = sprintf(
            "SELECT p.*, d.name as department_name 
             FROM %s p 
             LEFT JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id 
             WHERE p.department_id = %d 
             ORDER BY p.code",
            $this->table,
            intval($department_id)
        );
        return $this->fetchAll($query);
    }

    function searchProducts($search_term) {
        $search_term = $this->quote($search_term);
        $query = sprintf(
            "SELECT p.*, d.name as department_name 
             FROM %s p 
             LEFT JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id 
             WHERE p.code LIKE '%%%s%%' OR p.name LIKE '%%%s%%' 
             ORDER BY p.code",
            $this->table,
            $search_term,
            $search_term
        );
        return $this->fetchAll($query);
    }

    function duplicateProduct($product_id) {
        $product = $this->getById($product_id);
        if (!$product) {
            return ['status' => 'error', 'message' => '商品が見つかりません'];
        }

        // Generate new code with _COPY suffix
        $new_code = $product['code'] . '_COPY';
        $new_name = $product['name'] . ' (コピー)';

        $data = array(
            'code' => $new_code,
            'name' => $new_name,
            'department_id' => $product['department_id'],
            'unit' => $product['unit'],
            'price' => $product['price'],
            'notes' => $product['notes'],
            'created_at' => date('Y-m-d H:i:s')
        );

        $new_product_id = $this->query_insert($data);
        
        if (!$new_product_id) {
            return [
                'status' => 'error',
                'message' => '商品の複製に失敗しました'
            ];
        }

        return [
            'status' => 'success',
            'product_id' => $new_product_id,
            'message' => '商品を複製しました'
        ];
    }

    /**
     * Validate and ensure UTF-8 MB4 compatibility for strings containing emojis
     */
    private function validateUTF8MB4($str) {
        if (empty($str)) {
            return $str;
        }
        
        // Ensure the string is valid UTF-8
        if (!mb_check_encoding($str, 'UTF-8')) {
            // Try to convert from other encodings
            $str = mb_convert_encoding($str, 'UTF-8', 'auto');
        }
        
        // Clean up any malformed UTF-8 sequences
        $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
        
        return $str;
    }
}
?> 