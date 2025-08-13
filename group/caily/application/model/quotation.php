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
            'notes' => array(),
            'parent_project_id' => array('notnull'),
            'selected_child_project_ids' => array(),
            'selected_branch_id' => array(),
            'status' => array(),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search'))
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
        
        // Debug: Log all received data
        error_log('=== CREATE QUOTATION - RECEIVED DATA ===');
        error_log('All POST data: ' . print_r($data, true));
        error_log('Items field raw value: ' . ($data['items'] ?? 'NOT_SET'));
        error_log('Items field type: ' . gettype($data['items'] ?? 'NOT_SET'));
        error_log('Items field length: ' . (isset($data['items']) ? strlen($data['items']) : 'NOT_SET'));
        error_log('Items field empty check: ' . (empty($data['items']) ? 'true' : 'false'));
        error_log('=== END RECEIVED DATA ===');

        $errors = array();
        
        if (empty($data['quotation_number'])) {
            $errors['quotation_number'] = '見積番号は必須です';
            error_log('quotation_number validation failed: ' . ($data['quotation_number'] ?? 'NULL'));
        }
        
        if (empty($data['issue_date'])) {
            $errors['issue_date'] = '発行日は必須です';
            error_log('issue_date validation failed: ' . ($data['issue_date'] ?? 'NULL'));
        }
        
        if (empty($data['sender_company'])) {
            $errors['sender_company'] = '発注者会社名は必須です';
            error_log('sender_company validation failed: ' . ($data['sender_company'] ?? 'NULL'));
        }
        
        if (empty($data['receiver_company'])) {
            $errors['receiver_company'] = '受注者会社名は必須です';
            error_log('receiver_company validation failed: ' . ($data['receiver_company'] ?? 'NULL'));
        }
        
        if (empty($data['parent_project_id'])) {
            $errors['parent_project_id'] = '親プロジェクトIDは必須です';
            error_log('parent_project_id validation failed: ' . ($data['parent_project_id'] ?? 'NULL'));
        } elseif (!is_numeric($data['parent_project_id']) || intval($data['parent_project_id']) <= 0) {
            $errors['parent_project_id'] = '親プロジェクトIDは有効な数値で入力してください';
            error_log('parent_project_id validation failed: invalid value ' . ($data['parent_project_id'] ?? 'NULL'));
        }
        
        // Check if items exist and are valid
        if (empty($data['items'])) {
            $errors['items'] = '商品明細は必須です';
            error_log('items validation failed: ' . ($data['items'] ?? 'NULL'));
        } else {
            // Debug: Check JSON string before decode
            $items_json = $data['items'];
            error_log('Raw items JSON string: ' . $items_json);
            error_log('JSON string length: ' . strlen($items_json));
            error_log('JSON string first 100 chars: ' . substr($items_json, 0, 100));
            error_log('JSON string last 100 chars: ' . substr($items_json, -100));
            
            // Check for JSON syntax errors
            $json_error = json_last_error();
            $json_error_msg = json_last_error_msg();
            error_log('Previous JSON error: ' . $json_error . ' - ' . $json_error_msg);
            
            $items = json_decode($items_json, true);
            $json_error = json_last_error();
            $json_error_msg = json_last_error_msg();
            
            error_log('JSON decode result: ' . print_r($items, true));
            error_log('JSON decode error code: ' . $json_error);
            error_log('JSON decode error message: ' . $json_error_msg);
            
            error_log('Decoded items: ' . print_r($items, true));
            error_log('Items type: ' . gettype($items));
            error_log('Items is array: ' . (is_array($items) ? 'true' : 'false'));
            error_log('Items count: ' . (is_array($items) ? count($items) : 'N/A'));
            
            // Re-check after potential fixes
            if (!is_array($items) || count($items) === 0) {
                $errors['items'] = '商品明細は必須です';
                error_log('items validation failed: not array or empty');
                error_log('Final items type: ' . gettype($items));
                error_log('Final items is array: ' . (is_array($items) ? 'true' : 'false'));
                error_log('Final items count: ' . (is_array($items) ? count($items) : 'N/A'));
                
                // Try to identify the JSON issue
                if ($json_error !== JSON_ERROR_NONE) {
                    error_log('JSON decode failed with error: ' . $json_error_msg);
                    
                    // Try to find the problematic character
                    $problematic_chars = array();
                    for ($i = 0; $i < strlen($items_json); $i++) {
                        $char = $items_json[$i];
                        $ord = ord($char);
                        if ($ord < 32 && $ord !== 9 && $ord !== 10 && $ord !== 13) {
                            $problematic_chars[] = "Position $i: char code $ord";
                        }
                    }
                    if (!empty($problematic_chars)) {
                        error_log('Problematic characters found: ' . print_r($problematic_chars, true));
                    }
                    
                    // Try to identify JSON syntax issues
                    error_log('JSON string analysis:');
                    error_log('  - Contains double quotes: ' . (strpos($items_json, '"') !== false ? 'yes' : 'no'));
                    error_log('  - Contains escaped quotes: ' . (strpos($items_json, '\"') !== false ? 'yes' : 'no'));
                    error_log('  - Contains backslashes: ' . (strpos($items_json, '\\') !== false ? 'yes' : 'no'));
                    error_log('  - Contains newlines: ' . (strpos($items_json, "\n") !== false ? 'yes' : 'no'));
                    error_log('  - Contains carriage returns: ' . (strpos($items_json, "\r") !== false ? 'yes' : 'no'));
                    
                    // Try to find the exact position of the syntax error
                    $lines = explode("\n", $items_json);
                    foreach ($lines as $line_num => $line) {
                        if (trim($line) !== '') {
                            error_log("  Line " . ($line_num + 1) . ": " . substr($line, 0, 100));
                        }
                    }
                    
                    // Try to validate JSON step by step
                    $test_json = $items_json;
                    error_log('Attempting to fix JSON...');
                    
                    // Remove any BOM or hidden characters
                    $test_json = trim($test_json);
                    $test_json = preg_replace('/[\x00-\x1F\x7F]/', '', $test_json);
                    error_log('After cleaning control characters: ' . substr($test_json, 0, 100));
                    
                    // Try to decode cleaned JSON
                    $cleaned_items = json_decode($test_json, true);
                    $cleaned_error = json_last_error();
                    $cleaned_error_msg = json_last_error_msg();
                    error_log('Cleaned JSON decode result: ' . print_r($cleaned_items, true));
                    error_log('Cleaned JSON decode error: ' . $cleaned_error . ' - ' . $cleaned_error_msg);
                    
                    if ($cleaned_error === JSON_ERROR_NONE && is_array($cleaned_items)) {
                        error_log('JSON fixed! Using cleaned version.');
                        $items = $cleaned_items;
                        $json_error = JSON_ERROR_NONE;
                        $json_error_msg = 'No error (fixed)';
                    } else {
                        // Try to fix common JSON issues
                        error_log('Attempting manual JSON fixes...');
                        
                        // Fix unescaped quotes in set_json
                        $fixed_json = $items_json;
                        
                        // Pattern to find and fix set_json with unescaped quotes
                        $pattern = '/"set_json":"(\[.*?\])"/';
                        $fixed_json = preg_replace_callback($pattern, function($matches) {
                            $inner_json = $matches[1];
                            // Escape inner quotes
                            $escaped_inner = str_replace('"', '\\"', $inner_json);
                            return '"set_json":"' . $escaped_inner . '"';
                        }, $fixed_json);
                        
                        error_log('After manual fix: ' . substr($fixed_json, 0, 200));
                        
                        $manual_items = json_decode($fixed_json, true);
                        $manual_error = json_last_error();
                        $manual_error_msg = json_last_error_msg();
                        
                        error_log('Manual fix decode result: ' . print_r($manual_items, true));
                        error_log('Manual fix decode error: ' . $manual_error . ' - ' . $manual_error_msg);
                        
                        if ($manual_error === JSON_ERROR_NONE && is_array($manual_items)) {
                            error_log('Manual fix successful! Using manually fixed version.');
                            $items = $manual_items;
                            $json_error = JSON_ERROR_NONE;
                            $json_error_msg = 'No error (manually fixed)';
                        }
                    }
                }
            }
            
            // Final validation check after all fixes
            error_log('Final validation check - items type: ' . gettype($items));
            error_log('Final validation check - items is array: ' . (is_array($items) ? 'true' : 'false'));
            error_log('Final validation check - items count: ' . (is_array($items) ? count($items) : 'N/A'));
            
            if (is_array($items) && count($items) > 0) {
                // Clear previous items error since we have valid items now
                if (isset($errors['items'])) {
                    unset($errors['items']);
                    error_log('Cleared previous items error - items are now valid');
                }
                
                // Validate each item has required fields
                foreach ($items as $index => $item) {
                    error_log('Validating item ' . $index . ': ' . print_r($item, true));
                    
                    if (empty($item['title'])) {
                        $errors['items'] = '商品明細の件名は必須です';
                        error_log('Item ' . $index . ' title validation failed: ' . ($item['title'] ?? 'NULL'));
                        break;
                    }
                    if (empty($item['quantity']) || $item['quantity'] <= 0) {
                        $errors['items'] = '商品明細の数量は1以上で入力してください';
                        error_log('Item ' . $index . ' quantity validation failed: ' . ($item['quantity'] ?? 'NULL'));
                        break;
                    }
                    // if (empty($item['unit_price']) || $item['unit_price'] < 0) {
                    //     $errors['items'] = '商品明細の単価は0以上で入力してください';
                    //     error_log('Item ' . $index . ' unit_price validation failed: ' . ($item['unit_price'] ?? 'NULL'));
                    //     break;
                    // }
                    
                    error_log('Item ' . $index . ' validation passed');
                }
                
                error_log('All items validation completed');
            }
        }
        
        if (!empty($errors)) {
            error_log('Validation errors found: ' . print_r($errors, true));
            return array('status' => 'error', 'errors' => $errors);
        }

        error_log('Validation passed, proceeding with quotation creation');

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

        error_log('Filtered data prepared for insertion (with selected_child_project_ids): ' . print_r($filtered_data, true));

        // Insert quotation using query_insert method with filtered data
        error_log('About to call query_insert with filtered data: ' . print_r($filtered_data, true));
        $quotation_id = $this->query_insert($filtered_data);
        
        error_log('Insert result: ' . ($quotation_id ? $quotation_id : 'false'));
        
        if ($quotation_id) {
            // Insert quotation items if provided
            if (!empty($data['items']) && isset($items) && is_array($items)) {
                error_log('About to insert quotation items for quotation ID: ' . $quotation_id);
                error_log('Items to insert: ' . print_r($items, true));
                $this->insertQuotationItems($quotation_id, $items);
                error_log('Quotation items inserted successfully');
            } else {
                error_log('No valid items to insert or items not properly decoded');
                error_log('Data items present: ' . (!empty($data['items']) ? 'yes' : 'no'));
                error_log('Items variable set: ' . (isset($items) ? 'yes' : 'no'));
                error_log('Items is array: ' . (isset($items) && is_array($items) ? 'yes' : 'no'));
            }
            
            error_log('Quotation created successfully with ID: ' . $quotation_id);
            return array('status' => 'success', 'id' => $quotation_id);
        }
        
        error_log('Failed to create quotation');
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
                
                return ['status' => 'success', 'message' => '見積書を更新しました'];
            } else {
                return ['status' => 'error', 'error' => '更新に失敗しました'];
            }
        } catch (Exception $e) {
            error_log('Quotation update error: ' . $e->getMessage());
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
                    error_log('Failed to decode set_json_base64: ' . $e->getMessage());
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
            
            error_log('Inserting item SQL: ' . $sql);
            error_log('Item data being inserted: ' . print_r($item_data, true));
            if (isset($item['set_json'])) {
                error_log('Set JSON data: ' . print_r($item['set_json'], true));
                error_log('Set JSON type: ' . gettype($item['set_json']));
            }
            $result = $this->query($sql);
            
            if (!$result) {
                error_log('Failed to insert quotation item: ' . print_r($item_data, true));
                error_log('SQL error: ' . mysqli_error($this->handler));
            } else {
                error_log('Successfully inserted item: ' . $item['title']);
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
            error_log('Error in getById: ' . $e->getMessage());
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
            error_log('Quotation delete error: ' . $e->getMessage());
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
            $valid_statuses = ['下書き', '発行済み', '承認済み', '却下', '調整'];
            if (!in_array($status, $valid_statuses)) {
                return ['status' => 'error', 'error' => '無効なステータスです'];
            }
            
            $query = sprintf(
                "UPDATE %s SET status = '%s', updated_at = NOW() WHERE id = %d",
                $this->table,
                $status,
                $quotation_id
            );
            
            $result = $this->query($query);
            
            if ($result) {
                return ['status' => 'success', 'message' => 'ステータスが更新されました'];
            } else {
                return ['status' => 'error', 'error' => 'ステータスの更新に失敗しました'];
            }
        } catch (Exception $e) {
            error_log('Quotation status update error: ' . $e->getMessage());
            return ['status' => 'error', 'error' => 'データベースエラー: ' . $e->getMessage()];
        }
    }
} 