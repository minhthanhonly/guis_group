<?php

class Drawing extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'project_drawings';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'project_id' => array('type' => 'int'),
            'name' => array(),
            'status' => array(),
            'file_path' => array(),
            'price' => array(), // unit price for each drawing
            'created_by' => array(),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search')),
            'updated_by' => array(),
            'check_date' => array('except' => array('search')),
            'checked_by' => array(),
            'revise_by' => array(),
            'revise_date' => array(),
        );
        $this->connect();
    }

    function list($params = null) {
        $whereArr = [];
        
        // Handle both direct parameters and params array from API
        if (is_array($params)) {
            if (isset($params['project_id'])) {
                $whereArr[] = sprintf("d.project_id = %d", intval($params['project_id']));
            }
        } else {
            if (isset($_GET['project_id'])) {
                $whereArr[] = sprintf("d.project_id = %d", intval($_GET['project_id']));
            }
        }
        
        $where = !empty($whereArr) ? "WHERE " . implode(" AND ", $whereArr) : "";
        
        $query = sprintf(
            "SELECT d.*, c.realname as checked_by_name, r.realname as revise_by_name, u_updated.realname as updated_by_name
            FROM {$this->table} d 
            LEFT JOIN " . DB_PREFIX . "user c ON d.checked_by = c.userid
            LEFT JOIN " . DB_PREFIX . "user r ON d.revise_by = r.userid
            LEFT JOIN " . DB_PREFIX . "user u_updated ON d.updated_by = u_updated.userid
            %s
            ORDER BY d.created_at ASC",
            $where
        );
        
        $drawings = $this->fetchAll($query);
        
        // For each drawing, resolve created_by to names
        foreach ($drawings as &$drawing) {
            $drawing['created_by_names'] = '';
            if (!empty($drawing['created_by'])) {
                $user_ids = array_filter(array_map('trim', explode(',', $drawing['created_by'])));
                if (!empty($user_ids)) {
                    // Create a mapping of userid to realname to preserve order
                    $user_ids_escaped = array_map(function($id) {
                        return "'" . str_replace("'", "''", $id) . "'";
                    }, $user_ids);
                    $user_query = sprintf(
                        "SELECT userid, realname FROM %suser WHERE userid IN (%s)",
                        DB_PREFIX,
                        implode(',', $user_ids_escaped)
                    );
                    $users = $this->fetchAll($user_query);
                    
                    if ($users) {
                        // Create a mapping of userid to realname
                        $user_map = [];
                        foreach ($users as $user) {
                            $user_map[$user['userid']] = $user['realname'];
                        }
                        
                        // Build names array in the same order as created_by
                        $names = [];
                        foreach ($user_ids as $user_id) {
                            if (isset($user_map[$user_id])) {
                                $names[] = $user_map[$user_id];
                            }
                        }
                        
                        $drawing['created_by_names'] = implode(', ', $names);
                    }
                }
            }
        }

        unset($drawing);
        
        return $drawings;
    }

    function add() {
        if(!isset($_POST['project_id']) || !isset($_POST['name'])){
            return [
                'status' => 'error',
                'message' => 'データが不正です'
            ];
        }
        
        $project_id = $_POST['project_id'];
        $name = $_POST['name'];
        
        // Check if file with same name exists and delete it
        $existing_files = $this->getByNameAndProject($name, $project_id);
        $replaced = !empty($existing_files);
        
        if ($replaced) {
            foreach ($existing_files as $existing_file) {
                $this->query_delete(['id' => $existing_file['id']]);
            }
        }
        
        $data = array(
            'project_id' => $project_id,
            'name' => $name,
            'status' => isset($_POST['status']) ? $_POST['status'] : 'draft',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        // Only set created_by if explicitly provided
        if (isset($_POST['created_by'])) {
            $data['created_by'] = $_POST['created_by'];
        }
        
        
        $drawing_id = $this->query_insert($data);
        
        if($drawing_id){
            return [
                'status' => 'success',
                'id' => $drawing_id,
                'replaced' => $replaced
            ];
        }
        
        return [
            'status' => 'error'
        ];
    }

    function edit() {
        $id = $_POST['id'];
        
        $data = array(
            'name' => $_POST['name'],
            'status' => isset($_POST['status']) ? $_POST['status'] : 'draft',
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => isset($_SESSION['userid']) ? $_SESSION['userid'] : ''
        );
        
        $result = $this->query_update($data, ['id' => $id]);
        
        if($result){
            return [
                'status' => 'success'
            ];
        }
        return [
            'status' => 'error'
        ];
    }

    /**
     * Update price for a single drawing
     */
    function updatePrice() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) {
            return [
                'status' => 'error',
                'message' => 'ファイルIDが指定されていません'
            ];
        }

        // Get drawing to verify it exists
        $drawing = $this->getById(['id' => $id]);
        if (!$drawing) {
            return [
                'status' => 'error',
                'message' => 'ファイルが見つかりません'
            ];
        }

        // Check created_by only when NOT auto-calc (tính tự động không cần kiểm tra đã gán user)
        $isAutoCalc = isset($_POST['auto_calc']) && ($_POST['auto_calc'] === '1' || $_POST['auto_calc'] === 'true');
        if (!$isAutoCalc && empty($drawing['created_by'])) {
            return [
                'status' => 'error',
                'message' => '作成者が割り当てられていません。単価を変更する前に作成者を割り当ててください。'
            ];
        }

        // Allow empty price (NULL) or numeric value
        $price = isset($_POST['price']) && $_POST['price'] !== '' ? $_POST['price'] : null;

        // Basic numeric validation (optional)
        if ($price !== null && !is_numeric($price)) {
            return [
                'status' => 'error',
                'message' => '単価が不正です'
            ];
        }

        $updated_at = date('Y-m-d H:i:s');
        $updated_by_sql = isset($_SESSION['userid']) ? "'" . $this->quote($_SESSION['userid']) . "'" : "NULL";
        if ($price === null) {
            // Set price to NULL via raw SQL (query_update converts null to '' which fails for INT column)
            $query = sprintf(
                "UPDATE %s SET price = NULL, updated_at = '%s', updated_by = %s WHERE id = %d",
                $this->table,
                $this->quote($updated_at),
                $updated_by_sql,
                (int) $id
            );
            $result = $this->query($query);
        } else {
            $data = array(
                'price' => $price,
                'updated_at' => $updated_at,
                'updated_by' => isset($_SESSION['userid']) ? $_SESSION['userid'] : ''
            );
            $result = $this->query_update($data, ['id' => $id]);
        }

        if ($result) {
            return [
                'status' => 'success'
            ];
        }
        return [
            'status' => 'error',
            'message' => '単価の更新に失敗しました'
        ];
    }

    function delete() {
        $id = isset($_POST['id']) ? $_POST['id'] : 0;
        if(!$id){
            return [
                'status' => 'error',
                'message' => 'ファイルIDが指定されていません'
            ];
        }
        
        $result = $this->query_delete(['id' => $id]);
        
        if($result){
            return [
                'status' => 'success'
            ];
        }
        return [
            'status' => 'error'
        ];
    }

    function updateStatus() {
        $id = $_POST['id'];
        $status = $_POST['status'];
        $data = array(
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        $data['updated_by'] = isset($_SESSION['userid']) ? $_SESSION['userid'] : '';
        // If status is approved or rejected, set check date and checker
        if ($status === 'approved' || $status === 'rejected') {
            $data['check_date'] = date('Y-m-d H:i:s');
            $data['checked_by'] = $_SESSION['userid'];
        }
        // Nếu là revision hoặc revised thì set revise_date/by
        if ($status === 'revision' || $status === 'revised') {
            $data['revise_date'] = date('Y-m-d H:i:s');
            $data['revise_by'] = $_SESSION['userid'];
        }
        
        $result = $this->query_update($data, ['id' => $id]);
        
        if($result){
            return [
                'status' => 'success'
            ];
        }
        return [
            'status' => 'error'
        ];
    }

    function bulkUpdateStatus() {
        $ids = json_decode($_POST['ids'], true);
        $status = $_POST['status'];
        
        if (empty($ids) || !is_array($ids)) {
            return [
                'status' => 'error',
                'message' => 'IDが指定されていません'
            ];
        }
        
        $ids_str = implode(',', array_map('intval', $ids));
        
        $updated_by = isset($_SESSION['userid']) ? $this->quote($_SESSION['userid']) : '';
        // If status is approved or rejected, set check date and checker
        if ($status === 'approved' || $status === 'rejected') {
            $query = sprintf(
                "UPDATE {$this->table} SET status = '%s', updated_at = '%s', updated_by = '%s', check_date = '%s', checked_by = '%s' WHERE id IN (%s)",
                $status,
                date('Y-m-d H:i:s'),
                $updated_by,
                date('Y-m-d H:i:s'),
                $_SESSION['userid'],
                $ids_str
            );
        } else if ($status === 'revision' || $status === 'revised') {
            $query = sprintf(
                "UPDATE {$this->table} SET status = '%s', updated_at = '%s', updated_by = '%s', revise_date = '%s', revise_by = '%s' WHERE id IN (%s)",
                $status,
                date('Y-m-d H:i:s'),
                $updated_by,
                date('Y-m-d H:i:s'),
                $_SESSION['userid'],
                $ids_str
            );
        } else {
            $query = sprintf(
                "UPDATE {$this->table} SET status = '%s', updated_at = '%s', updated_by = '%s' WHERE id IN (%s)",
                $status,
                date('Y-m-d H:i:s'),
                $updated_by,
                $ids_str
            );
        }
        
        $result = $this->query($query);
        
        if($result){
            return [
                'status' => 'success'
            ];
        }
        return [
            'status' => 'error'
        ];
    }

    function bulkDelete() {
        $ids = json_decode($_POST['ids'], true);
        
        if (empty($ids) || !is_array($ids)) {
            return [
                'status' => 'error',
                'message' => 'IDが指定されていません'
            ];
        }
        
        $ids_str = implode(',', array_map('intval', $ids));
        $query = sprintf("DELETE FROM {$this->table} WHERE id IN (%s)", $ids_str);
        
        $result = $this->query($query);
        
        if($result){
            return [
                'status' => 'success'
            ];
        }
        return [
            'status' => 'error'
        ];
    }

    function getById($params = null) {
        // Handle both direct ID parameter and params array from API
        if (is_array($params)) {
            $id = isset($params['id']) ? $params['id'] : 0;
        } else {
            $id = $params;
        }

        $query = sprintf(
            "SELECT d.*, u.realname as created_by_name 
            FROM {$this->table} d 
            LEFT JOIN " . DB_PREFIX . "user u ON d.created_by = u.userid 
            WHERE d.id = %d",
            intval($id)
        );
        return $this->fetchOne($query);
    }

    function getByProject($params = null) {
        // Handle both direct project_id parameter and params array from API
        if (is_array($params)) {
            $project_id = isset($params['project_id']) ? intval($params['project_id']) : 0;
        } else {
            $project_id = intval($params);
        }
        
        if (!$project_id) {
            return [];
        }
        
        $query = sprintf(
            "SELECT d.*, u.realname as created_by_name
            FROM {$this->table} d 
            LEFT JOIN " . DB_PREFIX . "user u ON d.created_by = u.userid 
            WHERE d.project_id = %d 
            ORDER BY d.created_at DESC",
            $project_id
        );
        return $this->fetchAll($query);
    }
    
    function getByNameAndProject($name, $project_id) {
        $query = sprintf(
            "SELECT d.*, u.realname as created_by_name
            FROM {$this->table} d 
            LEFT JOIN " . DB_PREFIX . "user u ON d.created_by = u.userid 
            WHERE d.name = '%s' AND d.project_id = %d",
            $this->quote($name),
            intval($project_id)
        );
        return $this->fetchAll($query);
    }

    function assignUser() {
        $drawing_id = isset($_POST['drawing_id']) ? intval($_POST['drawing_id']) : 0;
        
        if (!$drawing_id) {
            return [
                'status' => 'error',
                'message' => 'ファイルIDが指定されていません'
            ];
        }
        
        // Get user_ids from POST (can be JSON array or single value)
        $user_ids = [];
        if (isset($_POST['user_ids'])) {
            $decoded = json_decode($_POST['user_ids'], true);
            if (is_array($decoded)) {
                $user_ids = $decoded;
            } else {
                $user_ids = [$_POST['user_ids']];
            }
        } else {
            // Fallback to current user if no user_ids provided
            $user_ids = [$_SESSION['userid']];
        }
        
        if (empty($user_ids)) {
            return [
                'status' => 'error',
                'message' => '担当者が指定されていません'
            ];
        }
        
        // Get current drawing to check existing created_by
        $drawing = $this->getById(['id' => $drawing_id]);
        if (!$drawing) {
            return [
                'status' => 'error',
                'message' => 'ファイルが見つかりません'
            ];
        }
        
        // Only allow one user per drawing - use the first user_id from the array
        $assigned_userid = is_array($user_ids) && !empty($user_ids) ? trim($user_ids[0]) : (is_string($user_ids) ? trim($user_ids) : '');
        
        if (empty($assigned_userid)) {
            return [
                'status' => 'error',
                'message' => '担当者が指定されていません'
            ];
        }
        
        // Update the drawing with single user (replace existing)
        $new_created_by = $assigned_userid;
        $data = array(
            'created_by' => $new_created_by,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => isset($_SESSION['userid']) ? $_SESSION['userid'] : ''
        );
        
        $result = $this->query_update($data, ['id' => $drawing_id]);
        
        if ($result) {
            return [
                'status' => 'success',
                'message' => '割り当てが完了しました'
            ];
        }
        
        return [
            'status' => 'error',
            'message' => '割り当てに失敗しました'
        ];
    }

    function unassignUser() {
        $drawing_id = isset($_POST['drawing_id']) ? intval($_POST['drawing_id']) : 0;
        
        if (!$drawing_id) {
            return [
                'status' => 'error',
                'message' => 'ファイルIDが指定されていません'
            ];
        }
        
        // Get user_ids from POST to unassign (can be JSON array or single value)
        $user_ids_to_remove = [];
        if (isset($_POST['user_ids'])) {
            $decoded = json_decode($_POST['user_ids'], true);
            if (is_array($decoded)) {
                $user_ids_to_remove = $decoded;
            } else {
                $user_ids_to_remove = [$_POST['user_ids']];
            }
        } else {
            // Fallback: remove all users if no user_ids provided
            // Or you can use current user: $user_ids_to_remove = [$_SESSION['userid']];
            // For now, remove all assigned users
        }
        
        // Get current drawing to check existing created_by
        $drawing = $this->getById(['id' => $drawing_id]);
        if (!$drawing) {
            return [
                'status' => 'error',
                'message' => 'ファイルが見つかりません'
            ];
        }
        
        $existing_created_by = $drawing['created_by'];
        
        if (empty($existing_created_by) || trim($existing_created_by) === '') {
            return [
                'status' => 'error',
                'message' => '割り当てられていません'
            ];
        }
        
        // Parse existing user IDs (userid strings)
        $existing_user_ids = array_filter(array_map('trim', explode(',', $existing_created_by)), function($id) {
            return !empty(trim($id));
        });
        
        // If after parsing we have no valid user IDs, return error
        if (count($existing_user_ids) === 0) {
            return [
                'status' => 'error',
                'message' => '割り当てられていません'
            ];
        }
        
        // If no specific user_ids provided, remove all users
        if (empty($user_ids_to_remove)) {
            $new_user_ids = [];
        } else {
            // Remove specified user IDs from the list
            $new_user_ids = array_filter($existing_user_ids, function($id) use ($user_ids_to_remove) {
                return !in_array(trim($id), array_map('trim', $user_ids_to_remove));
            });
        }
        
        // Update the drawing
        $new_created_by = !empty($new_user_ids) ? implode(',', $new_user_ids) : '';
        $data = array(
            'created_by' => $new_created_by,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => isset($_SESSION['userid']) ? $_SESSION['userid'] : ''
        );
        
        $result = $this->query_update($data, ['id' => $drawing_id]);
        
        if ($result) {
            return [
                'status' => 'success',
                'message' => '割り当てを解除しました'
            ];
        }
        
        return [
            'status' => 'error',
            'message' => '割り当て解除に失敗しました'
        ];
    }

    function bulkAssignUser() {
        $ids = json_decode($_POST['ids'], true);
        
        if (empty($ids) || !is_array($ids)) {
            return [
                'status' => 'error',
                'message' => 'IDが指定されていません'
            ];
        }
        
        // Get user_ids from POST (can be JSON array or single value)
        $user_ids = [];
        if (isset($_POST['user_ids'])) {
            $decoded = json_decode($_POST['user_ids'], true);
            if (is_array($decoded)) {
                $user_ids = $decoded;
            } else {
                $user_ids = [$_POST['user_ids']];
            }
        } else {
            // Fallback to current user if no user_ids provided
            $user_ids = [$_SESSION['userid']];
        }
        
        if (empty($user_ids)) {
            return [
                'status' => 'error',
                'message' => '担当者が指定されていません'
            ];
        }
        
        // Only allow one user per drawing - use the first user_id from the array
        $assigned_userid = is_array($user_ids) && !empty($user_ids) ? trim($user_ids[0]) : (is_string($user_ids) ? trim($user_ids) : '');
        
        if (empty($assigned_userid)) {
            return [
                'status' => 'error',
                'message' => '担当者が指定されていません'
            ];
        }
        
        $success_count = 0;
        
        foreach ($ids as $drawing_id) {
            $drawing = $this->getById(['id' => intval($drawing_id)]);
            if (!$drawing) continue;
            
            // Update the drawing with single user (replace existing)
            $new_created_by = $assigned_userid;
            $data = array(
                'created_by' => $new_created_by,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => isset($_SESSION['userid']) ? $_SESSION['userid'] : ''
            );
            
            $result = $this->query_update($data, ['id' => intval($drawing_id)]);
            if ($result) {
                $success_count++;
            }
        }
        
        if ($success_count > 0) {
            $message = $success_count . '件の割り当てが完了しました';
            return [
                'status' => 'success',
                'message' => $message
            ];
        }
        
        return [
            'status' => 'error',
            'message' => '割り当てに失敗しました'
        ];
    }

    function bulkUnassignUser() {
        $ids = json_decode($_POST['ids'], true);
        
        if (empty($ids) || !is_array($ids)) {
            return [
                'status' => 'error',
                'message' => 'IDが指定されていません'
            ];
        }
        
        // Get user_ids from POST to unassign (can be JSON array or single value)
        $user_ids_to_remove = [];
        if (isset($_POST['user_ids'])) {
            $decoded = json_decode($_POST['user_ids'], true);
            if (is_array($decoded)) {
                $user_ids_to_remove = $decoded;
            } else {
                $user_ids_to_remove = [$_POST['user_ids']];
            }
        }
        // If no user_ids provided, remove all users (same logic as unassignUser)
        
        $success_count = 0;
        $not_assigned_count = 0;
        
        foreach ($ids as $drawing_id) {
            $drawing = $this->getById(['id' => intval($drawing_id)]);
            if (!$drawing) continue;
            
            $existing_created_by = $drawing['created_by'];
            
            if (empty($existing_created_by) || trim($existing_created_by) === '') {
                $not_assigned_count++;
                continue;
            }
            
            // Parse existing user IDs
            $existing_user_ids = array_filter(array_map('trim', explode(',', $existing_created_by)), function($id) {
                return !empty(trim($id));
            });
            
            if (count($existing_user_ids) === 0) {
                $not_assigned_count++;
                continue;
            }
            
            // If no specific user_ids provided, remove all users
            if (empty($user_ids_to_remove)) {
                $new_user_ids = [];
            } else {
                // Remove specified user IDs from the list
                $new_user_ids = array_filter($existing_user_ids, function($id) use ($user_ids_to_remove) {
                    return !in_array(trim($id), array_map('trim', $user_ids_to_remove));
                });
            }
            
            // Check if anything changed
            if (count($new_user_ids) === count($existing_user_ids)) {
                $not_assigned_count++;
                continue;
            }
            
            // Update the drawing
            $new_created_by = !empty($new_user_ids) ? implode(',', $new_user_ids) : '';
            $data = array(
                'created_by' => $new_created_by,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => isset($_SESSION['userid']) ? $_SESSION['userid'] : ''
            );
            
            $result = $this->query_update($data, ['id' => intval($drawing_id)]);
            if ($result) {
                $success_count++;
            }
        }
        
        if ($success_count > 0) {
            $message = $success_count . '件の割り当て解除が完了しました';
            if ($not_assigned_count > 0) {
                $message .= '（' . $not_assigned_count . '件は割り当てられていませんでした）';
            }
            return [
                'status' => 'success',
                'message' => $message
            ];
        }
        
        if ($not_assigned_count > 0 && $not_assigned_count === count($ids)) {
            return [
                'status' => 'error',
                'message' => '選択されたファイルはすべて割り当てられていませんでした'
            ];
        }
        
        return [
            'status' => 'error',
            'message' => '割り当て解除に失敗しました'
        ];
    }
} 