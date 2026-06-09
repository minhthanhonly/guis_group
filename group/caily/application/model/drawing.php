<?php

class Drawing extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'project_drawings';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'project_id' => array('type' => 'int'),
            'task_id' => array('type' => 'int'),
            'drawing_slot' => array('type' => 'int'),
            'drawing_count' => array('type' => 'int'),
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

    private function canUserEditDrawingProject($projectId) {
        $projectId = intval($projectId);
        if ($projectId <= 0) {
            return false;
        }
        if (!class_exists('Project')) {
            require_once DIR_MODEL . 'project.php';
        }
        return (new Project())->canUserEditProject($projectId);
    }

    private function denyDrawingEditPermission() {
        return array(
            'status' => 'error',
            'message' => '権限がありません',
            'http_status' => 403,
        );
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
            'status' => isset($_POST['status']) ? $_POST['status'] : 'todo',
            'drawing_count' => isset($_POST['drawing_count']) ? max(1, intval($_POST['drawing_count'])) : 1,
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
        $existing = $this->getById(array('id' => $id));

        $data = array(
            'name' => $_POST['name'],
            'status' => isset($_POST['status']) ? $_POST['status'] : 'todo',
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => isset($_SESSION['userid']) ? $_SESSION['userid'] : ''
        );
        if ($existing && empty($existing['task_id']) && isset($_POST['drawing_count'])) {
            $data['drawing_count'] = max(1, intval($_POST['drawing_count']));
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

        $projectId = isset($drawing['project_id']) ? intval($drawing['project_id']) : 0;
        if (!$this->canUserEditDrawingProject($projectId)) {
            return $this->denyDrawingEditPermission();
        }

        $defaultKey = $this->resolveDefaultTaskDrawingTitleKey($drawing);
        $drawingStatus = isset($drawing['status']) ? (string) $drawing['status'] : '';
        if ($defaultKey !== null && $drawingStatus !== 'completed') {
            $this->query_update(array(
                'price' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ), array('id' => $id));
            return array('status' => 'success', 'price' => 0);
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

        $drawing = $this->getById(array('id' => $id));
        $result = $this->query_delete(['id' => $id]);
        
        if($result){
            if ($drawing) {
                $this->unlinkTaskAfterDrawingDelete($drawing);
                $projectId = isset($drawing['project_id']) ? intval($drawing['project_id']) : 0;
                if ($projectId > 0) {
                    $this->autoCalculateAllDrawingPricesForProject($projectId);
                }
            }
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
        $this->applyStatusAuditFields($data, $status);
        
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
        $audit = array(
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => isset($_SESSION['userid']) ? $_SESSION['userid'] : '',
        );
        $this->applyStatusAuditFields($audit, $status);
        $setParts = array(
            "status = '" . $this->quote($audit['status']) . "'",
            "updated_at = '" . $this->quote($audit['updated_at']) . "'",
            "updated_by = '" . $updated_by . "'",
        );
        if (!empty($audit['check_date'])) {
            $setParts[] = "check_date = '" . $this->quote($audit['check_date']) . "'";
            $setParts[] = "checked_by = '" . $this->quote($audit['checked_by']) . "'";
        }
        if (!empty($audit['revise_date'])) {
            $setParts[] = "revise_date = '" . $this->quote($audit['revise_date']) . "'";
            $setParts[] = "revise_by = '" . $this->quote($audit['revise_by']) . "'";
        }
        $query = sprintf(
            "UPDATE {$this->table} SET %s WHERE id IN (%s)",
            implode(', ', $setParts),
            $ids_str
        );
        
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
        $drawings = $this->fetchAll(sprintf(
            "SELECT * FROM %s WHERE id IN (%s)",
            $this->table,
            $ids_str
        ));
        $query = sprintf("DELETE FROM {$this->table} WHERE id IN (%s)", $ids_str);
        
        $result = $this->query($query);
        
        if($result){
            $projectId = 0;
            if (is_array($drawings)) {
                foreach ($drawings as $drawing) {
                    $this->unlinkTaskAfterDrawingDelete($drawing);
                    if ($projectId <= 0 && !empty($drawing['project_id'])) {
                        $projectId = intval($drawing['project_id']);
                    }
                }
            }
            if ($projectId > 0) {
                $this->autoCalculateAllDrawingPricesForProject($projectId);
            }
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

        $projectId = isset($drawing['project_id']) ? intval($drawing['project_id']) : 0;
        if (!$this->canUserEditDrawingProject($projectId)) {
            return $this->denyDrawingEditPermission();
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

        $projectId = isset($drawing['project_id']) ? intval($drawing['project_id']) : 0;
        if (!$this->canUserEditDrawingProject($projectId)) {
            return $this->denyDrawingEditPermission();
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

        $firstDrawing = $this->getById(['id' => intval($ids[0])]);
        if (!$firstDrawing) {
            return [
                'status' => 'error',
                'message' => 'ファイルが見つかりません'
            ];
        }
        $projectId = isset($firstDrawing['project_id']) ? intval($firstDrawing['project_id']) : 0;
        if (!$this->canUserEditDrawingProject($projectId)) {
            return $this->denyDrawingEditPermission();
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

        $firstDrawing = $this->getById(['id' => intval($ids[0])]);
        if (!$firstDrawing) {
            return [
                'status' => 'error',
                'message' => 'ファイルが見つかりません'
            ];
        }
        $projectId = isset($firstDrawing['project_id']) ? intval($firstDrawing['project_id']) : 0;
        if (!$this->canUserEditDrawingProject($projectId)) {
            return $this->denyDrawingEditPermission();
        }
        
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

    /**
     * Drawing status uses the same values as task status.
     */
    private function normalizeDrawingStatusFromTask($taskStatus) {
        $taskStatus = trim((string) $taskStatus);
        return $taskStatus !== '' ? $taskStatus : 'todo';
    }

    private function applyStatusAuditFields(&$data, $status) {
        $now = date('Y-m-d H:i:s');
        $userid = isset($_SESSION['userid']) ? $_SESSION['userid'] : '';
        if (in_array($status, array('approved', 'rejected', 'completed', 'cancelled'), true)) {
            $data['check_date'] = $now;
            $data['checked_by'] = $userid;
        }
        if (in_array($status, array('revision', 'revised', 'review', 'in-progress', 'confirming'), true)) {
            $data['revise_date'] = $now;
            $data['revise_by'] = $userid;
        }
    }

    private function buildTaskDrawingName($taskId, $title) {
        $safeTitle = trim((string) $title);
        return $safeTitle !== '' ? $safeTitle : 'タスク';
    }

    private function resolveCreatedByFromTaskAssignees($assignedToCsv) {
        $assignedToCsv = trim((string) $assignedToCsv);
        if ($assignedToCsv === '') {
            return '';
        }
        $ids = array_filter(array_map('intval', explode(',', $assignedToCsv)));
        if (empty($ids)) {
            return '';
        }
        $users = $this->fetchAll(
            "SELECT userid FROM " . DB_PREFIX . "user WHERE id IN (" . implode(',', $ids) . ")"
        );
        if (empty($users)) {
            return '';
        }
        $userids = array();
        foreach ($users as $user) {
            if (!empty($user['userid'])) {
                $userids[] = $user['userid'];
            }
        }
        return implode(',', $userids);
    }

    /**
     * When a task-synced drawing row is deleted, clear drawing_count on the linked task.
     */
    private function unlinkTaskAfterDrawingDelete($drawing) {
        if (empty($drawing) || !is_array($drawing)) {
            return;
        }

        $taskId = isset($drawing['task_id']) ? intval($drawing['task_id']) : 0;
        $projectId = isset($drawing['project_id']) ? intval($drawing['project_id']) : 0;
        $name = isset($drawing['name']) ? trim((string) $drawing['name']) : '';

        if ($taskId > 0) {
            $remaining = intval($this->fetchCount($this->table, sprintf('WHERE task_id = %d', $taskId)));
            if ($remaining > 0) {
                return;
            }
        } elseif ($projectId > 0 && $name !== '') {
            $remaining = intval($this->fetchCount(
                $this->table,
                sprintf(
                    "WHERE project_id = %d AND (task_id IS NULL OR task_id = 0) AND name = '%s'",
                    $projectId,
                    $this->quote($name)
                )
            ));
            if ($remaining > 0) {
                return;
            }
            $task = $this->fetchOne(sprintf(
                "SELECT id FROM %stasks WHERE project_id = %d AND title = '%s' AND drawing_count > 0 LIMIT 1",
                DB_PREFIX,
                $projectId,
                $this->quote($name)
            ));
            if (empty($task['id'])) {
                return;
            }
            $taskId = intval($task['id']);
        } else {
            return;
        }

        $this->query(sprintf(
            "UPDATE %stasks SET drawing_count = 0, updated_at = '%s' WHERE id = %d",
            DB_PREFIX,
            $this->quote(date('Y-m-d H:i:s')),
            $taskId
        ));
    }

    /**
     * Delete all drawings linked to a task (by task_id and legacy name match).
     */
    function deleteDrawingsForTask($taskId, $taskRow = null) {
        $taskId = intval($taskId);
        if ($taskId <= 0) {
            return;
        }

        $this->query(sprintf("DELETE FROM %s WHERE task_id = %d", $this->table, $taskId));

        if ($taskRow === null) {
            return;
        }

        $projectId = isset($taskRow['project_id']) ? intval($taskRow['project_id']) : 0;
        $title = isset($taskRow['title']) ? trim((string) $taskRow['title']) : '';
        if ($projectId <= 0 || $title === '') {
            return;
        }

        $this->query(sprintf(
            "DELETE FROM %s WHERE project_id = %d AND (task_id IS NULL OR task_id = 0) AND name = '%s'",
            $this->table,
            $projectId,
            $this->quote($title)
        ));
    }

    function deleteByTaskId($taskId) {
        $this->deleteDrawingsForTask($taskId);
    }

    /**
     * Fixed price share for bootstrap default tasks (child project).
     */
    private function getDefaultTaskDrawingPricePercents() {
        return array(
            'お客様との連絡・調整・納品対応' => 0.15,
            '全図面のチェック・確認作業' => 0.20,
        );
    }

    private function resolveDefaultTaskDrawingTitleKey($drawingRow) {
        if (empty($drawingRow) || !is_array($drawingRow)) {
            return null;
        }
        $pcts = $this->getDefaultTaskDrawingPricePercents();
        $taskTitle = isset($drawingRow['task_title']) ? trim((string) $drawingRow['task_title']) : '';
        if ($taskTitle !== '' && isset($pcts[$taskTitle])) {
            return $taskTitle;
        }
        $name = isset($drawingRow['name']) ? trim((string) $drawingRow['name']) : '';
        if ($name !== '' && isset($pcts[$name])) {
            return $name;
        }
        return null;
    }

    /**
     * Fixed % applies only when the linked default task is completed.
     */
    private function resolveDefaultTaskDrawingPricePercent($drawingRow) {
        $key = $this->resolveDefaultTaskDrawingTitleKey($drawingRow);
        if ($key === null) {
            return null;
        }
        $taskStatus = isset($drawingRow['task_status']) ? (string) $drawingRow['task_status'] : '';
        if ($taskStatus !== 'completed') {
            return null;
        }
        $pcts = $this->getDefaultTaskDrawingPricePercents();
        return isset($pcts[$key]) ? $pcts[$key] : null;
    }

    /**
     * Redistribute project amount: default tasks keep fixed %, others share the remainder by drawing_count.
     */
    function autoCalculateAllDrawingPricesForProject($projectId) {
        $projectId = intval($projectId);
        if ($projectId <= 0) {
            return false;
        }

        if (!class_exists('Project')) {
            require_once DIR_MODEL . 'project.php';
        }
        $project = (new Project())->getById(array('id' => $projectId));
        $amount = isset($project['amount']) ? floatval($project['amount']) : 0;
        if ($amount <= 0) {
            return false;
        }

        $drawings = $this->fetchAll(sprintf(
            "SELECT d.id, d.drawing_count, d.name, d.task_id, t.title AS task_title, t.status AS task_status
            FROM %s d
            LEFT JOIN %stasks t ON d.task_id = t.id
            WHERE d.project_id = %d
            ORDER BY d.id ASC",
            $this->table,
            DB_PREFIX,
            $projectId
        ));
        if (empty($drawings)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $fixedAllocated = 0;
        $otherDrawings = array();

        $defaultPcts = $this->getDefaultTaskDrawingPricePercents();

        foreach ($drawings as $drawing) {
            $drawingId = intval($drawing['id']);
            $taskId = isset($drawing['task_id']) ? intval($drawing['task_id']) : 0;
            $taskStatus = isset($drawing['task_status']) ? (string) $drawing['task_status'] : '';
            $defaultKey = $this->resolveDefaultTaskDrawingTitleKey($drawing);

            if ($defaultKey !== null) {
                if ($taskStatus === 'completed' && isset($defaultPcts[$defaultKey])) {
                    $price = round($amount * $defaultPcts[$defaultKey], 2);
                    $this->query_update(
                        array('price' => $price, 'updated_at' => $now),
                        array('id' => $drawingId)
                    );
                    $fixedAllocated += $price;
                } else {
                    $this->query(sprintf(
                        "UPDATE %s SET price = 0, updated_at = '%s' WHERE id = %d",
                        $this->table,
                        $this->quote($now),
                        $drawingId
                    ));
                }
                continue;
            }

            if ($taskId > 0 && $taskStatus !== 'completed') {
                $this->query(sprintf(
                    "UPDATE %s SET price = 0, updated_at = '%s' WHERE id = %d",
                    $this->table,
                    $this->quote($now),
                    $drawingId
                ));
            } elseif ($taskId <= 0 || $taskStatus === 'completed') {
                $otherDrawings[] = $drawing;
            }
        }

        if (empty($otherDrawings)) {
            return true;
        }

        $rest = max(0, $amount - $fixedAllocated);
        $totalQty = 0;
        foreach ($otherDrawings as $drawing) {
            $totalQty += max(1, intval($drawing['drawing_count']));
        }
        if ($totalQty <= 0) {
            return true;
        }

        $allocated = 0;
        $count = count($otherDrawings);
        foreach ($otherDrawings as $index => $drawing) {
            $qty = max(1, intval($drawing['drawing_count']));
            if ($index === $count - 1) {
                $price = round($rest - $allocated, 2);
            } else {
                $price = (int) floor(($rest * $qty) / $totalQty);
                $allocated += $price;
            }
            $this->query_update(
                array('price' => $price, 'updated_at' => $now),
                array('id' => intval($drawing['id']))
            );
        }

        return true;
    }

    /**
     * Sync one project_drawings row from a task (drawing_count, status, title, assignees).
     */
    function syncFromTask($taskRow, $options = array()) {
        if (empty($taskRow) || empty($taskRow['id']) || empty($taskRow['project_id'])) {
            return;
        }

        $taskId = intval($taskRow['id']);
        $projectId = intval($taskRow['project_id']);
        $drawingCount = isset($taskRow['drawing_count']) ? max(0, intval($taskRow['drawing_count'])) : 0;
        $taskStatus = isset($taskRow['status']) ? (string) $taskRow['status'] : 'todo';
        $title = isset($taskRow['title']) ? (string) $taskRow['title'] : '';
        $assignedTo = isset($taskRow['assigned_to']) ? (string) $taskRow['assigned_to'] : '';

        if ($drawingCount <= 0) {
            $this->deleteDrawingsForTask($taskId, $taskRow);
            return;
        }

        $drawingStatus = $this->normalizeDrawingStatusFromTask($taskStatus);
        $createdBy = $this->resolveCreatedByFromTaskAssignees($assignedTo);
        $now = date('Y-m-d H:i:s');
        $name = $this->buildTaskDrawingName($taskId, $title);

        $existing = $this->fetchAll(sprintf(
            "SELECT id FROM %s WHERE task_id = %d ORDER BY id ASC",
            $this->table,
            $taskId
        ));

        $keepId = null;
        if (empty($existing)) {
            $legacy = $this->fetchOne(sprintf(
                "SELECT id FROM %s WHERE project_id = %d AND (task_id IS NULL OR task_id = 0) AND name = '%s' ORDER BY id ASC LIMIT 1",
                $this->table,
                $projectId,
                $this->quote($name)
            ));
            if ($legacy && !empty($legacy['id'])) {
                $keepId = intval($legacy['id']);
                $existing = array(array('id' => $keepId));
            }
        }

        foreach ($existing as $index => $row) {
            $rowId = intval($row['id']);
            if ($index === 0) {
                $keepId = $rowId;
                continue;
            }
            $this->query_delete(array('id' => $rowId));
        }

        $rowData = array(
            'name' => $name,
            'status' => $drawingStatus,
            'drawing_count' => $drawingCount,
            'drawing_slot' => 1,
            'task_id' => $taskId,
            'updated_at' => $now,
        );
        if ($createdBy !== '') {
            $rowData['created_by'] = $createdBy;
        }

        if ($keepId) {
            $this->query_update($rowData, array('id' => $keepId));
        } else {
            $insertData = array_merge($rowData, array(
                'project_id' => $projectId,
                'task_id' => $taskId,
                'created_at' => $now,
            ));
            $newId = $this->query_insert($insertData);
            if ($newId) {
                $keepId = intval($newId);
            }
        }

        if ($keepId) {
            $this->applyDefaultTaskDrawingPriceFromTaskStatus($keepId, $taskRow);
        }
    }

    private function applyDefaultTaskDrawingPriceFromTaskStatus($drawingId, $taskRow) {
        $drawingId = intval($drawingId);
        if ($drawingId <= 0 || empty($taskRow) || !is_array($taskRow)) {
            return;
        }
        $title = isset($taskRow['title']) ? trim((string) $taskRow['title']) : '';
        $defaultKey = $this->resolveDefaultTaskDrawingTitleKey(array(
            'task_title' => $title,
            'name' => $title,
        ));
        if ($defaultKey === null) {
            return;
        }
        $taskStatus = isset($taskRow['status']) ? (string) $taskRow['status'] : '';
        if ($taskStatus === 'completed') {
            return;
        }
        $this->query_update(array(
            'price' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ), array('id' => $drawingId));
    }
} 