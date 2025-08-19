<?php

class Quotation extends ApplicationModel {
    function __construct() {
        parent::__construct();
        $this->table = DB_PREFIX . 'quotations';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'quotation_number' => array('notnull'),
            'issue_date' => array('notnull'),
            'sender_company' => array('notnull'),
            'sender_address' => array(),
            'sender_contact' => array(),
            'receiver_company' => array('notnull'),
            'receiver_address' => array(),
            'receiver_contact' => array(),
            'receiver_seal_path' => array(),
            'receiver_tel' => array(),
            'receiver_fax' => array(),
            'receiver_registration_number' => array(),
            'total_amount' => array('notnull'),
            'tax_rate' => array('notnull'),
            'total_with_tax' => array('notnull'),
            'delivery_date' => array(),
            'delivery_location' => array(),
            'payment_method' => array(),
            'valid_until_type' => array(),
            'valid_until' => array(),
            'subject' => array(),
            'notes' => array(),
            'parent_project_id' => array('notnull'),
            'selected_child_project_ids' => array(),
            'selected_branch_id' => array(),
            'status' => array(),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search')),
            'updated_by' => array()
        );
        $this->connect();
    }

    function getByParentProject() {
        $parent_project_id = $_GET['parent_project_id'];
        $query = sprintf(
            "SELECT * FROM %s WHERE parent_project_id = %d ORDER BY created_at DESC",
            $this->table,
            intval($parent_project_id)
        );
        return $this->fetchAll($query);
    }

    function create() {
        $data = $_POST;
        


        $errors = array();
        
        if (empty($data['quotation_number'])) {
            $errors['quotation_number'] = '見積番号は必須です';
        }
        
        if (empty($data['issue_date'])) {
            $errors['issue_date'] = '発行日は必須です';
        }
        
        if (empty($data['sender_company'])) {
            $errors['sender_company'] = '発注者会社名は必須です';
        }
        
        if (empty($data['receiver_company'])) {
            $errors['receiver_company'] = '受注者会社名は必須です';
        }
        
        if (empty($data['parent_project_id'])) {
            $errors['parent_project_id'] = '親プロジェクトIDは必須です';
        } elseif (!is_numeric($data['parent_project_id']) || intval($data['parent_project_id']) <= 0) {
            $errors['parent_project_id'] = '親プロジェクトIDは有効な数値で入力してください';
        }
        
        // Check if items exist and are valid
        if (empty($data['items'])) {
            $errors['items'] = '商品明細は必須です';
        } else {
            $items = json_decode($data['items'], true);
            
            if (!is_array($items) || count($items) === 0) {
                $errors['items'] = '商品明細は必須です';
            } else {
                // Validate each item has required fields
                foreach ($items as $index => $item) {
                    if (empty($item['title'])) {
                        $errors['items'] = '商品明細の件名は必須です';
                        break;
                    }
                    if (empty($item['quantity']) || $item['quantity'] <= 0) {
                        $errors['items'] = '商品明細の数量は1以上で入力してください';
                        break;
                    }
                }
            }
        }
        
        if (!empty($errors)) {
            return array('status' => 'error', 'errors' => $errors);
        }

        // Filter data to only include fields defined in the schema
        $filtered_data = array();
        foreach ($this->schema as $field => $rules) {
            if (isset($data[$field])) {
                $filtered_data[$field] = $data[$field];
            }
        }

        // Set default values for required fields if not provided
        $filtered_data['total_amount'] = $filtered_data['total_amount'] ?? 0;
        $filtered_data['tax_rate'] = $filtered_data['tax_rate'] ?? 10;
        $filtered_data['total_with_tax'] = $filtered_data['total_with_tax'] ?? 0;
        
        // Set default values for new receiver fields
        $filtered_data['receiver_tel'] = $filtered_data['receiver_tel'] ?? '';
        $filtered_data['receiver_fax'] = $filtered_data['receiver_fax'] ?? '';
        $filtered_data['receiver_registration_number'] = $filtered_data['receiver_registration_number'] ?? '';
        
        // Process selected_child_project_ids
        $selected_project_ids = array();
        
        // Check if it's sent as array from FormData
        foreach ($data as $key => $value) {
            if (strpos($key, 'selected_child_project_ids[') === 0) {
                $selected_project_ids[] = $value;
            }
        }
        
        if (!empty($selected_project_ids)) {
            $filtered_data['selected_child_project_ids'] = implode(',', $selected_project_ids);
        } elseif (isset($data['selected_child_project_ids'])) {
            if (is_array($data['selected_child_project_ids'])) {
                $filtered_data['selected_child_project_ids'] = implode(',', $data['selected_child_project_ids']);
            } else {
                $filtered_data['selected_child_project_ids'] = $data['selected_child_project_ids'];
            }
        } else {
            // Extract unique project IDs from items if not explicitly provided
            $project_ids = array();
            if (isset($items) && is_array($items)) {
                foreach ($items as $item) {
                    if (!empty($item['project_id']) && !in_array($item['project_id'], $project_ids)) {
                        $project_ids[] = $item['project_id'];
                    }
                }
            }
            $filtered_data['selected_child_project_ids'] = implode(',', $project_ids);
        }
        
        // Set creation timestamp
        $filtered_data['created_at'] = date('Y-m-d H:i:s');
        
        // Set updated_by field
        if (!empty($data['updated_by'])) {
            $filtered_data['updated_by'] = $data['updated_by'];
        }

        // Insert quotation using query_insert method with filtered data
        $quotation_id = $this->query_insert($filtered_data);
        
        if ($quotation_id) {
            // Insert quotation items if provided
            if (!empty($data['items']) && isset($items) && is_array($items)) {
                $this->insertQuotationItems($quotation_id, $items);
            }
            
            // Log the creation
            $this->logQuotationAction($quotation_id, 'created', '見積書を作成しました');
            
            return array('status' => 'success', 'id' => $quotation_id);
        }
        
        return array('status' => 'error', 'message' => '見積書の作成に失敗しました');
    }

    function update($params = null) {
        $data = $_POST;
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'error' => '見積書IDが指定されていません'];
       
        
        // Validate required fields
        $errors = array();
        
        if (empty($data['quotation_number'])) {
            $errors['quotation_number'] = '見積番号は必須です';
        }
        
        if (empty($data['issue_date'])) {
            $errors['issue_date'] = '発行日は必須です';
        }
        
        if (empty($data['sender_company'])) {
            $errors['sender_company'] = '発注者会社名は必須です';
        }
        
        if (empty($data['receiver_company'])) {
            $errors['receiver_company'] = '受注者会社名は必須です';
        }
        
        if (empty($data['parent_project_id'])) {
            $errors['parent_project_id'] = '親プロジェクトIDは必須です';
        } elseif (!is_numeric($data['parent_project_id']) || intval($data['parent_project_id']) <= 0) {
            $errors['parent_project_id'] = '親プロジェクトIDは有効な数値で入力してください';
        }

        // Check if items exist and are valid
        if (empty($data['items'])) {
            $errors['items'] = '商品明細は必須です';
        } else {
            $items = json_decode($data['items'], true);
            
            if (!is_array($items) || count($items) === 0) {
                $errors['items'] = '商品明細は必須です';
            } else {
                // Validate each item has required fields
                foreach ($items as $index => $item) {
                    if (empty($item['title'])) {
                        $errors['items'] = '商品明細の件名は必須です';
                        break;
                    }
                    if (empty($item['quantity']) || floatval($item['quantity']) <= 0) {
                        $errors['items'] = '商品明細の数量は1以上で入力してください';
                        break;
                    }
                    // if (empty($item['unit_price']) || floatval($item['unit_price']) < 0) {
                    //     $errors['items'] = '商品明細の単価は0以上で入力してください';
                    //     break;
                    // }
                }
            }
        }
        
        if (!empty($errors)) {
            return array('status' => 'error', 'errors' => $errors);
        }

        // Filter data to only include fields defined in the schema
        $filtered_data = array();
        foreach ($this->schema as $field => $rules) {
            if (isset($data[$field])) {
                $filtered_data[$field] = $data[$field];
            }
        }

        // Set default values for required fields if not provided
        $filtered_data['total_amount'] = $filtered_data['total_amount'] ?? 0;
        $filtered_data['tax_rate'] = $filtered_data['tax_rate'] ?? 10;
        $filtered_data['total_with_tax'] = $filtered_data['total_with_tax'] ?? 0;
        
        // Set default values for new receiver fields
        $filtered_data['receiver_tel'] = $filtered_data['receiver_tel'] ?? '';
        $filtered_data['receiver_fax'] = $filtered_data['receiver_fax'] ?? '';
        $filtered_data['receiver_registration_number'] = $filtered_data['receiver_registration_number'] ?? '';
        
        // Process selected_child_project_ids
        $selected_project_ids = array();
        
        // Check if it's sent as array from FormData
        foreach ($data as $key => $value) {
            if (strpos($key, 'selected_child_project_ids[') === 0) {
                $selected_project_ids[] = $value;
            }
        }
        
        if (!empty($selected_project_ids)) {
            $filtered_data['selected_child_project_ids'] = implode(',', $selected_project_ids);
        } elseif (isset($data['selected_child_project_ids'])) {
            if (is_array($data['selected_child_project_ids'])) {
                $filtered_data['selected_child_project_ids'] = implode(',', $data['selected_child_project_ids']);
            } else {
                $filtered_data['selected_child_project_ids'] = $data['selected_child_project_ids'];
            }
        } else {
            // Extract unique project IDs from items if not explicitly provided
            $project_ids = array();
            if (!empty($data['items'])) {
                $items = json_decode($data['items'], true);
                if (is_array($items)) {
                    foreach ($items as $item) {
                        if (!empty($item['project_id']) && !in_array($item['project_id'], $project_ids)) {
                            $project_ids[] = $item['project_id'];
                        }
                    }
                }
            }
            $filtered_data['selected_child_project_ids'] = implode(',', $project_ids);
        }
        
        // Set update timestamp
        $filtered_data['updated_at'] = date('Y-m-d H:i:s');
        
        // Set updated_by field
        if (!empty($data['updated_by'])) {
            $filtered_data['updated_by'] = $data['updated_by'];
        }

        try {
            // Update quotation using query_update method with filtered data
            $result = $this->query_update($filtered_data, ['id' => $id]);
            
            if ($result) {
                // Update quotation items if provided
                if (!empty($data['items'])) {
                    // Delete existing items first
                    $this->deleteQuotationItems($id);
                    
                    // Insert new items
                    $items = json_decode($data['items'], true);
                    if (is_array($items)) {
                        $this->insertQuotationItems($id, $items);
                    }
                }
                
                // Log the update
                $this->logQuotationAction($id, 'updated', '見積書を更新しました');
                
                return ['status' => 'success', 'message' => '見積書を更新しました'];
            } else {
                return ['status' => 'error', 'error' => '更新に失敗しました'];
            }
        } catch (Exception $e) {
            return ['status' => 'error', 'error' => 'データベースエラー: ' . $e->getMessage()];
        }
    }

    function insertQuotationItems($quotation_id, $items) {
        $item_table = DB_PREFIX . 'quotation_items';
        
        foreach ($items as $index => $item) {
            // Handle set_json_base64 decoding
            $set_json = null;
            if (!empty($item['set_json_base64'])) {
                try {
                    $set_json = base64_decode($item['set_json_base64']);
                } catch (Exception $e) {
                    $set_json = null;
                }
            } elseif (!empty($item['set_json'])) {
                // Fallback to original set_json if base64 version not available
                $set_json = $item['set_json'];
            }
            
            $item_data = array(
                'quotation_id' => $quotation_id,
                'project_id' => !empty($item['project_id']) ? intval($item['project_id']) : null,
                'title' => $item['title'] ?? '',
                'product_code' => $item['product_code'] ?? '',
                'product_name' => $item['product_name'] ?? '',
                'quantity' => $item['quantity'] ?? 0,
                'unit' => $item['unit'] ?? '',
                'unit_price' => $item['unit_price'] ?? 0,
                'amount' => $item['amount'] ?? 0,
                'notes' => $item['notes'] ?? '',
                'is_set' => isset($item['is_set']) ? ($item['is_set'] ? 1 : 0) : 0,
                'set_json' => $set_json,
                'sort_order' => $index
            );
            
            // Build SQL query manually to avoid table property conflicts
            $keys = array_keys($item_data);
            $values = array_values($item_data);
            $quoted_values = array();
            
            foreach ($values as $value) {
                if ($value === null) {
                    $quoted_values[] = 'NULL';
                } elseif (is_numeric($value)) {
                    $quoted_values[] = $value;
                } else {
                    $quoted_values[] = "'" . mysqli_real_escape_string($this->handler, $value) . "'";
                }
            }
            
            $sql = "INSERT INTO " . $item_table . " (" . implode(", ", $keys) . ") VALUES (" . implode(", ", $quoted_values) . ")";
            
            $result = $this->query($sql);
            
            if (!$result) {
                // Failed to insert item
            }
        }
    }

    function deleteQuotationItems($quotation_id) {
        $item_table = DB_PREFIX . 'quotation_items';
        $query = sprintf(
            "DELETE FROM %s WHERE quotation_id = %d",
            $item_table,
            intval($quotation_id)
        );
        return $this->query($query);
    }

    function execute($sql, $params = array()) {
        // Prepare and execute a parameterized query
        if ($this->handler) {
            $stmt = mysqli_prepare($this->handler, $sql);
            if ($stmt) {
                if (!empty($params)) {
                    $types = str_repeat('s', count($params)); // Assume all strings for now
                    mysqli_stmt_bind_param($stmt, $types, ...$params);
                }
                $result = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                return $result;
            } else {
                die('Prepare statement error: ' . mysqli_error($this->handler));
            }
        } else {
            die('データベースハンドラが見つかりません。');
        }
    }

    function getById($params = null) {
        // Handle both direct ID parameter and params array from API
        if (is_array($params)) {
            $id = isset($params['id']) ? intval($params['id']) : 0;
        } else {
            $id = $params ? intval($params) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
        }
        
        if (!$id) {
            return ['status' => 'error', 'message' => '見積書IDが指定されていません'];
        }
        
        try {
            $query = sprintf(
                "SELECT * FROM %s WHERE id = %d",
                $this->table,
                $id
            );
            $quotation = $this->fetchOne($query);
            
            if ($quotation) {
                // Get quotation items
                $items_query = sprintf(
                    "SELECT * FROM %s WHERE quotation_id = %d ORDER BY sort_order ASC",
                    DB_PREFIX . 'quotation_items',
                    $id
                );
                $quotation['items'] = $this->fetchAll($items_query);
                
                // Convert selected_child_project_ids from comma-separated string to array
                if (!empty($quotation['selected_child_project_ids'])) {
                    $quotation['selected_child_project_ids'] = explode(',', $quotation['selected_child_project_ids']);
                } else {
                    $quotation['selected_child_project_ids'] = array();
                }
                
                return [
                    'status' => 'success',
                    'data' => $quotation
                ];
            } else {
                return ['status' => 'error', 'message' => '指定された見積書が見つかりません'];
            }
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'データベースエラーが発生しました'];
        }
    }

    function delete() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'error' => '見積書IDが指定されていません'];
        
        try {
            // Delete quotation items first (due to foreign key constraint)
            $this->deleteQuotationItems($id);
            
            // Delete quotation using query_delete method
            $result = $this->query_delete(['id' => $id]);
            
            if ($result) {
                return ['status' => 'success', 'message' => '見積書を削除しました'];
            } else {
                return ['status' => 'error', 'error' => '削除に失敗しました'];
            }
        } catch (Exception $e) {
            return ['status' => 'error', 'error' => 'データベースエラー: ' . $e->getMessage()];
        }
    }

    function updateStatus() {
        try {
            $data = $_POST;
            
            if (empty($data['quotation_id'])) {
                return ['status' => 'error', 'error' => '有効な見積書IDが必要です'];
            }
            
            if (empty($data['status'])) {
                return ['status' => 'error', 'error' => 'ステータスが必要です'];
            }
            
            $quotation_id = intval($data['quotation_id']);
            $status = mysqli_real_escape_string($this->handler, $data['status']);
            
            // Validate status values
            $valid_statuses = ['下書き', '発行済み', '承認済み', '却下', '調整', 'キャンセル'];
            if (!in_array($status, $valid_statuses)) {
                return ['status' => 'error', 'error' => '無効なステータスです'];
            }
            
            $query = sprintf(
                "UPDATE %s SET status = '%s', updated_by = '%s', updated_at = NOW() WHERE id = %d",
                $this->table,
                $status,
                $data['updated_by'],
                $quotation_id
            );
            
            $result = $this->query($query);
            
            if ($result) {
                // Log the status change
                $this->logQuotationAction(
                    $quotation_id, 
                    'status_changed', 
                    'ステータスを変更しました', 
                    '', 
                    $status
                );
                
                return ['status' => 'success', 'message' => 'ステータスが更新されました'];
            } else {
                return ['status' => 'error', 'error' => 'ステータスの更新に失敗しました'];
            }
        } catch (Exception $e) {
            return ['status' => 'error', 'error' => 'データベースエラー: ' . $e->getMessage()];
        }
    }

    function getCompanySeal() {
        try {
            $query = sprintf(
                "SELECT image_path, name FROM %s 
                WHERE type = 'company' 
                AND is_active = 1 
                ORDER BY created_at ASC 
                LIMIT 1",
                DB_PREFIX . 'seals'
            );
            
            $result = $this->fetchOne($query);
            
            if ($result) {
                return [
                    'image_path' => $result['image_path'],
                    'name' => $result['name']
                ];
            }
            
            return null;
            
        } catch (Exception $e) {
            return null;
        }
    }

    // Get quotation history/logs
    function getLogs($params = null) {
        $quotation_id = isset($_GET['quotation_id']) ? intval($_GET['quotation_id']) : 0;
        if (!$quotation_id) return [];

        try {
            $query = sprintf(
                "SELECT l.*, u.realname, u.user_image FROM " . DB_PREFIX . "quotation_history l
                LEFT JOIN " . DB_PREFIX . "user u ON l.user_id = u.userid
                WHERE l.quotation_id = %d ORDER BY l.time DESC",
                $quotation_id
            );
            
            $logs = $this->fetchAll($query);
            return $logs ?: [];
            
        } catch (Exception $e) {
            return [];
        }
    }

    // Log quotation action
    private function logQuotationAction($quotation_id, $action, $note = '', $value1 = '', $value2 = '') {
        try {
            $user_id = $_SESSION['userid'] ?? '';
            $username = $_SESSION['realname'] ?? '';
            
            $data = [
                'quotation_id' => $quotation_id,
                'user_id' => $user_id,
                'username' => $username,
                'action' => $action,
                'note' => $note,
                'value1' => $value1,
                'value2' => $value2,
                'time' => date('Y-m-d H:i:s')
            ];
            
            $this->table =DB_PREFIX . 'quotation_history';
            $this->query_insert($data);
            $this->table = DB_PREFIX . 'quotations'; // Reset table back
        } catch (Exception $e) {
            // Silently fail if logging fails
        }
    }






} 