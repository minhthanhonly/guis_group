<?php

class Task extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'tasks';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'project_id' => array('type' => 'int'),
            'parent_id' => array('type' => 'int'),
            'title' => array(),
            'description' => array(),
            'status' => array(),
            'priority' => array(),
            'assigned_to' => array('type' => 'int'),
            'created_by' => array('type' => 'int'),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search')),
            'due_date' => array(),
            'progress' => array('type' => 'int'),
            'category_id' => array('type' => 'int'),
            'estimated_hours' => array('type' => 'float'),
            'actual_hours' => array('type' => 'float'),
            'position' => array('type' => 'int')
        );
        $this->connect();
    }

    function list($params = null) {
        $whereArr = [];
        
        // Handle both direct parameters and params array from API
        if (is_array($params)) {
            if (isset($params['project_id'])) {
                $whereArr[] = sprintf("t.project_id = %d", intval($params['project_id']));
            }
            
            if (isset($params['parent_id'])) {
                $whereArr[] = sprintf("t.parent_id = %d", intval($params['parent_id']));
            } else if (!isset($params['include_subtasks'])) {
                $whereArr[] = "t.parent_id IS NULL";
            }
            $include_subtasks = isset($params['include_subtasks']);
        } else {
            if (isset($_GET['project_id'])) {
                $whereArr[] = sprintf("t.project_id = %d", intval($_GET['project_id']));
            }
            
            if (isset($_GET['parent_id'])) {
                $whereArr[] = sprintf("t.parent_id = %d", intval($_GET['parent_id']));
            } else if (!isset($_GET['include_subtasks'])) {
                $whereArr[] = "t.parent_id IS NULL";
            }
            $include_subtasks = isset($_GET['include_subtasks']);
        }
        
        $where = !empty($whereArr) ? "WHERE " . implode(" AND ", $whereArr) : "";
        
        // Current user for reaction info
        $current_user_id = isset($_SESSION['userid']) ? $_SESSION['userid'] : '';
        
        $current_user_id_number = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
        
        $query = sprintf(
            "SELECT t.*, p.name as project_name, u.realname as assigned_to_name,
            u_creator.realname as created_by_name, u_creator.user_image as created_by_user_image,
            u_creator.userid as created_by_userid,
            (SELECT COUNT(*) FROM {$this->table} WHERE parent_id = t.id) as subtask_count,
            -- Task like/dislike counts
            (SELECT COUNT(*) FROM " . DB_PREFIX . "task_reactions tr WHERE tr.task_id = t.id AND tr.type = 'like') as like_count,
            (SELECT COUNT(*) FROM " . DB_PREFIX . "task_reactions tr2 WHERE tr2.task_id = t.id AND tr2.type = 'dislike') as dislike_count,
            -- Current user's reaction type (like/dislike)
            (SELECT tr3.type FROM " . DB_PREFIX . "task_reactions tr3 
             WHERE tr3.task_id = t.id AND tr3.user_id = '%s' LIMIT 1) as current_user_reaction,
            -- Current user's reaction note
            (SELECT tr4.note FROM " . DB_PREFIX . "task_reactions tr4 
             WHERE tr4.task_id = t.id AND tr4.user_id = '%s' LIMIT 1) as current_user_reaction_note,
            -- Users who liked
            (SELECT GROUP_CONCAT(u1.realname) FROM " . DB_PREFIX . "task_reactions tr5
             LEFT JOIN " . DB_PREFIX . "user u1 ON tr5.user_id = u1.userid
             WHERE tr5.task_id = t.id AND tr5.type = 'like') as liked_by_names,
            -- Users who disliked
            (SELECT GROUP_CONCAT(u2.realname) FROM " . DB_PREFIX . "task_reactions tr6
             LEFT JOIN " . DB_PREFIX . "user u2 ON tr6.user_id = u2.userid
             WHERE tr6.task_id = t.id AND tr6.type = 'dislike') as disliked_by_names
            FROM {$this->table} t 
            LEFT JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id 
            LEFT JOIN " . DB_PREFIX . "user u ON t.assigned_to = u.id 
            LEFT JOIN " . DB_PREFIX . "user u_creator ON t.created_by = u_creator.id
            %s
            ORDER BY t.position, t.created_at DESC",
            $this->quote($current_user_id),
            $this->quote($current_user_id),
            $where
        );
        
        $tasks = $this->fetchAll($query);
        
        // Load acknowledgements for all tasks
        $taskIds = array_map(function($task) { return $task['id']; }, $tasks);
        $acknowledgementsMap = [];
        if (!empty($taskIds)) {
            $ackQuery = sprintf(
                "SELECT task_id, user_id, acknowledged, acknowledged_at 
                FROM " . DB_PREFIX . "task_assignees 
                WHERE task_id IN (%s)",
                implode(',', array_map('intval', $taskIds))
            );
            $ackRows = $this->fetchAll($ackQuery);
            foreach ($ackRows as $ack) {
                $taskId = (int)$ack['task_id'];
                $uid = (int)$ack['user_id'];
                if (!isset($acknowledgementsMap[$taskId])) {
                    $acknowledgementsMap[$taskId] = [];
                }
                $acknowledgementsMap[$taskId][(string)$uid] = [
                    'acknowledged' => intval($ack['acknowledged']),
                    'acknowledged_at' => $ack['acknowledged_at']
                ];
            }
        }
        
        if ($include_subtasks) {
            foreach ($tasks as &$task) {
                $unread_count = $this->getTaskUnreadCommentCount($task['id']);
                $task['unread_count'] = $unread_count['unread_count'] ?? 0;
                if ($task['subtask_count'] > 0) {
                    $task['subtasks'] = $this->getSubtasks($task['id']);
                }
                // Parse reaction user names
                $task['liked_by_names'] = $task['liked_by_names'] ? explode(',', $task['liked_by_names']) : [];
                $task['disliked_by_names'] = $task['disliked_by_names'] ? explode(',', $task['disliked_by_names']) : [];
                // Add acknowledgements
                $task['acknowledgements'] = $acknowledgementsMap[$task['id']] ?? [];
            }
        } else {
            // Parse reaction user names even if not including subtasks
            foreach ($tasks as &$task) {
                $task['liked_by_names'] = $task['liked_by_names'] ? explode(',', $task['liked_by_names']) : [];
                $task['disliked_by_names'] = $task['disliked_by_names'] ? explode(',', $task['disliked_by_names']) : [];
                // Add acknowledgements
                $task['acknowledgements'] = $acknowledgementsMap[$task['id']] ?? [];
            }
        }
        
        return $tasks;
    }

    function getSubtasks($parent_id) {
        $query = sprintf(
            "SELECT t.*, u.realname as assigned_to_name
            FROM {$this->table} t 
            LEFT JOIN " . DB_PREFIX . "user u ON t.assigned_to = u.id 
            WHERE t.parent_id = %d 
            ORDER BY t.position, t.created_at ASC",
            intval($parent_id)
        );
        return $this->fetchAll($query);
    }

    function add() {
        $data = array(
            'project_id' => $_POST['project_id'],
            'parent_id' => isset($_POST['parent_id']) && $_POST['parent_id'] ? $_POST['parent_id'] : null,
            'title' => $_POST['title'],
            'description' => isset($_POST['description']) ? $_POST['description'] : '',
            'status' => isset($_POST['status']) ? $_POST['status'] : 'todo',
            'priority' => isset($_POST['priority']) ? $_POST['priority'] : 'medium',
            'assigned_to' => isset($_POST['assigned_to']) ? $_POST['assigned_to'] : null,
            'created_by' => isset($_POST['created_by']) ? $_POST['created_by'] : $_SESSION['user_id'],
            'due_date' => isset($_POST['due_date']) ? $_POST['due_date'] : null,
            'start_date' => isset($_POST['start_date']) ? $_POST['start_date'] : null,
            // 'category_id' => isset($_POST['category_id']) ? $_POST['category_id'] : null,
            // 'estimated_hours' => isset($_POST['estimated_hours']) ? $_POST['estimated_hours'] : 0,
            // 'actual_hours' => isset($_POST['actual_hours']) ? $_POST['actual_hours'] : 0,
            'progress' => isset($_POST['progress']) ? $_POST['progress'] : null,
            'position' => isset($_POST['position']) ? $_POST['position'] : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        $task_id = $this->query_insert($data);
        // if ($task_id && $data['project_id']) {
        //     $this->updateProjectProgress($data['project_id']);
        // }
        if($task_id){
            $this->logTaskAction($task_id, 'created', 'タスク作成', '', '');
            
            // Sync task_assignees table when task is created
            if (!empty($data['assigned_to'])) {
                $this->syncTaskAssignees($task_id, $data['assigned_to']);
            }
            
            // Send notification to assigned users if task is assigned
            if (!empty($data['assigned_to']) && $data['project_id']) {
                // Get project information
                $project = $this->fetchOne(
                    "SELECT project_number, name FROM " . DB_PREFIX . "projects WHERE id = " . intval($data['project_id'])
                );
                
                if ($project) {
                    $projectNumber = $project['project_number'] ?? '';
                    $projectName = $project['name'] ?? '';
                    $assignedUserIds = $data['assigned_to'];
                    
                    $this->notifyTaskCreated($task_id, $data['title'], $data['project_id'], $projectNumber, $projectName, $assignedUserIds);
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

    function edit() {
        $id = $_POST['id'];
        
        if (isset($_POST['status']) || isset($_POST['progress'])) {
            if (!$this->checkPermission($_POST['project_id'], $id)) {
               return [
                'status' => 'error',
                'message' => 'このタスクを更新する権限がありません'
               ];
            }
        }
        
        $old = $this->getById($id);
        $data = array(
            'project_id' => $_POST['project_id'],
            'title' => $_POST['title'],
            'description' => isset($_POST['description']) ? $_POST['description'] : '',
            'status' => isset($_POST['status']) ? $_POST['status'] : 'new',
            'priority' => isset($_POST['priority']) ? $_POST['priority'] : 'medium',
            'assigned_to' => isset($_POST['assigned_to']) ? $_POST['assigned_to'] : null,
            'due_date' => isset($_POST['due_date']) ? $_POST['due_date'] : null,
            'start_date' => isset($_POST['start_date']) ? $_POST['start_date'] : null,
            'estimated_hours' => isset($_POST['estimated_hours']) ? $_POST['estimated_hours'] : 0,
            'actual_hours' => isset($_POST['actual_hours']) ? $_POST['actual_hours'] : 0,
            'updated_at' => date('Y-m-d H:i:s')
        );

        if(isset($_POST['position'])){
            $data['position'] = $_POST['position'];
        }
        
        if (isset($_POST['progress'])) {
            $data['progress'] = intval($_POST['progress']);
        }
        if (isset($_POST['parent_id'])) {
            $data['parent_id'] = intval($_POST['parent_id']);
        }
        $result = $this->query_update($data, ['id' => $id]);
        
        // if ($result && $task['parent_id']) {
        //     $this->updateParentTaskProgress($task['parent_id']);
        // }
        // if ($result && $task['project_id']) {
        //     $this->updateProjectProgress($task['project_id']);
        // }
        if($result){
            // Log các trường thay đổi chính
            $fields = ['title','description','status','priority','due_date','start_date','progress'];
            $labels = [
                'title' => 'タスク名',
                'description' => '説明',
                'status' => 'ステータス',
                'priority' => '優先度',
                'assigned_to' => '担当者',
                'due_date' => '期限日',
                'start_date' => '開始日',
                'progress' => '進捗',
            ];
            foreach ($fields as $f) {
                $oldVal = $old[$f] ?? '';
                $newVal = $data[$f] ?? '';
                if ($oldVal != $newVal) {
                    if ($f === 'priority') {
                        $this->logTaskAction($id, 'priority_updated', '優先度変更', $oldVal, $newVal);
                    } else if ($f === 'status') {
                        $this->logTaskAction($id, 'status_changed', 'ステータス変更', $oldVal, $newVal);
                    } else if ($f === 'progress') {
                        $this->logTaskAction($id, 'progress_updated', '進捗変更', $oldVal, $newVal);
                    } else {
                        $this->logTaskAction($id, 'updated', $labels[$f].'を変更', $oldVal, $newVal);
                    }
                }
            }
            
            // Check if assigned_to changed and send notifications
            $oldAssignedTo = $old['assigned_to'] ?? '';
            $newAssignedTo = $data['assigned_to'] ?? '';
            if ($oldAssignedTo != $newAssignedTo && !empty($newAssignedTo) && $data['project_id']) {
                // Log assigned_to change
                $this->logTaskAction($id, 'assigned', '担当者変更', $oldAssignedTo, $newAssignedTo);
                
                // Get project information
                $project = $this->fetchOne(
                    "SELECT project_number, name FROM " . DB_PREFIX . "projects WHERE id = " . intval($data['project_id'])
                );
                
                if ($project) {
                    $projectNumber = $project['project_number'] ?? '';
                    $projectName = $project['name'] ?? '';
                    $taskTitle = $data['title'] ?? $old['title'] ?? '';
                    
                    // Send notification to newly assigned users
                    $this->notifyTaskAssigneeChanged($id, $taskTitle, $data['project_id'], $projectNumber, $projectName, $newAssignedTo, $oldAssignedTo);
                }
            } else if ($oldAssignedTo != $newAssignedTo) {
                // Log even if no notification is sent
                $this->logTaskAction($id, 'assigned', '担当者変更', $oldAssignedTo, $newAssignedTo);
            }

            return [
                'status' => 'success'
            ];
        }
        return [
            'status' => 'error'
        ];
    }

    function delete() {
        $id = isset($_POST['id']) ? $_POST['id'] : 0;
        if(!$id){
            return [
                'status' => 'error',
                'message' => 'タスクIDが指定されていません'
            ];
        }
        $old = $this->getById($id);
        if (!$old || !isset($old['project_id'])) {
            return [
                'status' => 'error',
                'message' => 'タスクが見つかりません'
            ];
        }
        $projectId = (int) $old['project_id'];
        $savedGetProjectId = isset($_GET['project_id']) ? $_GET['project_id'] : null;
        $_GET['project_id'] = $projectId;
        $permission = $this->getPermission();
        if ($savedGetProjectId !== null) {
            $_GET['project_id'] = $savedGetProjectId;
        } else {
            unset($_GET['project_id']);
        }
        $canDelete = !empty($permission['can_manage_project']);
        if (!$canDelete && !empty($permission['rule']['task_delete'])) {
            $currentUserId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
            $createdBy = isset($old['created_by']) ? (int) $old['created_by'] : 0;
            $canDelete = ($createdBy > 0 && $createdBy === $currentUserId);
        }
        if (!$canDelete) {
            return [
                'status' => 'error',
                'message' => 'このタスクを削除する権限がありません。作成者のみ削除できます。'
            ];
        }
        $query = sprintf(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE parent_id = %d",
            intval($id)
        );
        $subtasks = $this->fetchOne($query)['count'];
        
        if ($subtasks > 0) {
            return [
                'status' => 'error',
                'message' => 'このタスクにはサブタスクが存在するため、削除できません。'
            ];
        }
        
        // $query = sprintf(
        //     "SELECT COUNT(*) as count FROM " . DB_PREFIX . "time_entries WHERE task_id = %d",
        //     intval($id)
        // );
        // $timeEntries = $this->fetchOne($query)['count'];

        $query = sprintf(
            "DELETE FROM " . DB_PREFIX . "comments WHERE task_id = %d",
            intval($id)
        );
        $this->query($query);
        
        //$task = $this->getById($id);
        $result = $this->query_delete(['id' => $id]);
        // if ($result && $task['parent_id']) {
        //     $this->updateParentTaskProgress($task['parent_id']);
        // }
        // if ($result && $task['project_id']) {
        //     $this->updateProjectProgress($task['project_id']);
        // }
        if($result){
           // $this->logTaskAction($id, 'deleted', 'タスク削除', $old['status'], 'deleted');

            return [
                'status' => 'success'
            ];
        }
        return [
            'status' => 'error'
        ];
    }

    function updateStatus() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $status = isset($_POST['status']) ? $_POST['status'] : '';
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        
        if (!$id || !$status) {
            return ['status' => 'error', 'message' => 'Missing required parameters'];
        }
        
        if (!$this->checkPermission($project_id, $id)) {
            return ['status' => 'error', 'message' => 'このタスクを更新する権限がありません'];
        }
        
        $old = $this->getById($id);
        $data = array(
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        $result = $this->query_update($data, ['id' => $id]);
        
        if ($result) {
            $this->logTaskAction($id, 'status_changed', 'ステータス変更', $old['status'], $status);
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'message' => 'Update failed'];
        }
    }

    function updateProgress() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $progress = isset($_POST['progress']) ? intval($_POST['progress']) : 0;
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        
        if (!$id) return ['status' => 'error', 'message' => 'Missing task id'];
        
        if (!$this->checkPermission($project_id, $id)) {
            return ['status' => 'error', 'message' => 'このタスクを更新する権限がありません'];
        }
        
        $old = $this->getById($id);
        $data = array(
            'progress' => $progress,
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        $result = $this->query_update($data, ['id' => $id]);
        
        if ($result) {
            $this->logTaskAction($id, 'progress_updated', '進捗変更', $old['progress'], $progress);
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'message' => 'Update failed'];
        }
    }

    function updateAssignee() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $assigned_to = isset($_POST['assigned_to']) ? $_POST['assigned_to'] : '';
        // $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        
        if (!$id) return ['status' => 'error', 'message' => 'Missing task id'];
       
        $data = array(
            'assigned_to' => $assigned_to,
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        $result = $this->query_update($data, ['id' => $id]);
        
        if ($result) {
            // Sync task_assignees table
            $this->syncTaskAssignees($id, $assigned_to);
           // $this->logTaskAction($id, 'progress_updated', '進捗変更', $old['progress'], $progress);
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'message' => 'Update failed'];
        }
    }
    
    /**
     * Sync task_assignees table when assigned_to changes
     */
    function syncTaskAssignees($task_id, $assigned_to_csv) {
        // Get current assignees from task_assignees table
        $currentAssignees = $this->fetchAll(
            "SELECT user_id FROM " . DB_PREFIX . "task_assignees WHERE task_id = " . intval($task_id)
        );
        $currentUserIds = array_map(function($row) { return intval($row['user_id']); }, $currentAssignees);
        
        // Parse new assigned_to CSV
        $newUserIds = [];
        if (!empty($assigned_to_csv)) {
            $parts = explode(',', $assigned_to_csv);
            foreach ($parts as $part) {
                $userId = intval(trim($part));
                if ($userId > 0) {
                    $newUserIds[] = $userId;
                }
            }
        }
        
        // Remove assignees that are no longer assigned
        $toRemove = array_diff($currentUserIds, $newUserIds);
        if (!empty($toRemove)) {
            $this->query(
                "DELETE FROM " . DB_PREFIX . "task_assignees 
                WHERE task_id = " . intval($task_id) . " 
                AND user_id IN (" . implode(',', $toRemove) . ")"
            );
        }
        
        // Add new assignees (if not exists)
        $toAdd = array_diff($newUserIds, $currentUserIds);
        foreach ($toAdd as $userId) {
            $this->query(
                "INSERT INTO " . DB_PREFIX . "task_assignees (task_id, user_id, acknowledged, acknowledged_at) 
                VALUES (" . intval($task_id) . ", " . intval($userId) . ", 0, NULL)
                ON DUPLICATE KEY UPDATE task_id = task_id"
            );
        }
    }
    
    /**
     * Acknowledge task assignment
     */
    function acknowledgeTask() {
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $user_id = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
        
        if (!$task_id || !$user_id) {
            return ['status' => 'error', 'message' => 'Missing required parameters'];
        }
        
        // Verify user is assigned to this task
        $task = $this->getById($task_id);
        if (!$task) {
            return ['status' => 'error', 'message' => 'Task not found'];
        }
        
        // Check if user is in assigned_to
        $assignedToArray = !empty($task['assigned_to']) ? explode(',', $task['assigned_to']) : [];
        $isAssigned = false;
        foreach ($assignedToArray as $assignedId) {
            if (intval(trim($assignedId)) == $user_id) {
                $isAssigned = true;
                break;
            }
        }
        
        if (!$isAssigned) {
            return ['status' => 'error', 'message' => 'Bạn không được giao việc này'];
        }
        
        // Update or insert acknowledgement
        $query = sprintf(
            "INSERT INTO " . DB_PREFIX . "task_assignees (task_id, user_id, acknowledged, acknowledged_at) 
            VALUES (%d, %d, 1, NOW())
            ON DUPLICATE KEY UPDATE 
                acknowledged = 1, 
                acknowledged_at = NOW(),
                updated_at = NOW()",
            intval($task_id),
            intval($user_id)
        );
        
        $result = $this->query($query);
        
        if ($result) {
            $this->logTaskAction($task_id, 'acknowledged', 'Đã nhận việc', null, $user_id);
            return ['status' => 'success', 'message' => 'Đã đánh dấu nhận việc thành công'];
        } else {
            return ['status' => 'error', 'message' => 'Failed to acknowledge task'];
        }
    }
    
    /**
     * Get task acknowledgements for a specific task
     */
    function getTaskAcknowledgements($task_id) {
        $query = sprintf(
            "SELECT ta.*, u.realname as user_name, u.userid
            FROM " . DB_PREFIX . "task_assignees ta
            LEFT JOIN " . DB_PREFIX . "user u ON ta.user_id = u.id
            WHERE ta.task_id = %d
            ORDER BY ta.acknowledged DESC, ta.acknowledged_at DESC",
            intval($task_id)
        );
        return $this->fetchAll($query);
    }



    function updateDescription() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $description = isset($_POST['description']) ? $_POST['description'] : '';
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        
        if (!$id || !$description) {
            return ['status' => 'error', 'message' => 'Missing required parameters'];
        }
        
        if (!$this->checkPermission($project_id, $id)) {
            return ['status' => 'error', 'message' => 'このタスクを更新する権限がありません'];
        }
        
        $data = array(
            'description' => $description,
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        $result = $this->query_update($data, ['id' => $id]);
        
        if ($result) {
            $this->logTaskAction($id, 'description_updated', '説明変更');
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'message' => 'Update failed'];
        }
    }
    

    function updatePriority() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $priority = isset($_POST['priority']) ? $_POST['priority'] : '';
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        
        if (!$id || !$priority) {
            return ['status' => 'error', 'message' => 'Missing required parameters'];
        }
        
        if (!$this->checkPermission($project_id, $id)) {
            return ['status' => 'error', 'message' => 'このタスクを更新する権限がありません'];
        }
        
        $old = $this->getById($id);
        $data = array(
            'priority' => $priority,
            'updated_at' => date('Y-m-d H:i:s')
        );

        $result = $this->query_update($data, ['id' => $id]);
        if ($result) {
            $this->logTaskAction($id, 'priority_updated', '優先度変更', $old['priority'], $priority);
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'message' => 'Update failed'];
        }
    }

    function updateParentTaskProgress($parent_id) {
        $query = sprintf(
            "UPDATE {$this->table} 
            SET progress = (
                SELECT COALESCE(AVG(progress), 0) 
                FROM {$this->table} 
                WHERE parent_id = %d
            ),
            updated_at = '%s'
            WHERE id = %d",
            intval($parent_id),
            date('Y-m-d H:i:s'),
            intval($parent_id)
        );
        return $this->query($query);
    }

    function updateProjectProgress($project_id) {
        $query = sprintf(
            "UPDATE " . DB_PREFIX . "projects 
            SET progress = (
                SELECT COALESCE(AVG(progress), 0) 
                FROM {$this->table} 
                WHERE project_id = %d AND parent_id IS NULL
            ),
            updated_at = '%s'
            WHERE id = %d",
            intval($project_id),
            date('Y-m-d H:i:s'),
            intval($project_id)
        );
       
        return $this->query($query);
    }

    function getTimeEntries($taskId) {
        $query = sprintf(
            "SELECT te.*, u.realname as user_name 
            FROM " . DB_PREFIX . "time_entries te 
            LEFT JOIN " . DB_PREFIX . "user u ON te.user_id = u.id 
            WHERE te.task_id = %d 
            ORDER BY te.start_time DESC",
            intval($taskId)
        );
        return $this->fetchAll($query);
    }

    function getComments() {
        // Handle both direct parameter and GET parameter
        $taskId = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;
        
        if (!$taskId) {
            return [];
        }
        
        // Get pagination parameters
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
        $offset = ($page - 1) * $per_page;
        
        $query = sprintf(
            "SELECT c.*, u.realname as user_name, u.user_image,
            (SELECT COUNT(*) FROM " . DB_PREFIX . "comment_likes WHERE comment_id = c.id) as like_count,
            (SELECT GROUP_CONCAT(user_id) FROM " . DB_PREFIX . "comment_likes WHERE comment_id = c.id) as liked_by,
            (SELECT GROUP_CONCAT(name) FROM " . DB_PREFIX . "comment_likes WHERE comment_id = c.id) as liked_by_names
            FROM " . DB_PREFIX . "comments c 
            LEFT JOIN " . DB_PREFIX . "user u ON c.user_id = u.userid 
            WHERE c.task_id = %d 
            ORDER BY c.created_at DESC
            LIMIT %d OFFSET %d",
            intval($taskId),
            intval($per_page),
            intval($offset)
        );
        
        $comments = $this->fetchAll($query);
        
        // Convert liked_by to array
        foreach ($comments as &$comment) {
            $comment['liked_by'] = $comment['liked_by'] ? explode(',', $comment['liked_by']) : [];
            $comment['liked_by_names'] = $comment['liked_by_names'] ? explode(',', $comment['liked_by_names']) : [];
            $comment['like_count'] = intval($comment['like_count']);
        }
        
        return $comments;
    }

    function addTimeEntry($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
                $this->table = DB_PREFIX . 'time_entries';        $result = $this->query_insert($data);        $this->table = DB_PREFIX . 'tasks';
        return $result;
    }

    function updateTimeEntry($data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->table = DB_PREFIX . 'time_entries';
        $result = $this->query_update($data, ['task_id' => $data['task_id']]);
        $this->table = DB_PREFIX . 'tasks';
        return $result;
    }

    function addComment() {
        $data = $_POST;
        
        // Get task_id from the request
        $task_id = isset($data['task_id']) ? intval($data['task_id']) : 0;
        if (!$task_id) {
            return ['success' => false, 'message' => 'タスクIDが指定されていません'];
        }
        
        // Get task to get project_id
        $task = $this->getById($task_id);
        if (!$task) {
            return ['success' => false, 'message' => 'タスクが見つかりません'];
        }
        
        $project_id = $task['project_id'];
        
        $commentData = array(
            'task_id' => $task_id,
            'user_id' => $data['user_id'],
            'content' => $data['content'],
            'created_at' => date('Y-m-d H:i:s')
        );
        
        $this->table = DB_PREFIX . 'comments';
        $result = $this->query_insert($commentData);
        $this->table = DB_PREFIX . 'tasks'; // Reset table back to tasks
        
        // Send mention notifications if comment was added successfully
        if ($result && $project_id) {
            $this->sendMentionNotifications($project_id, $data['content'], $data['user_id'], $result, $task_id);
        }
        
        return ['success' => (bool)$result, 'id' => $result];
    }

    function getById($params = null) {
        // Handle both direct ID parameter and params array from API
        if (is_array($params)) {
            $id = isset($params['id']) ? $params['id'] : 0;
        } else {
            $id = $params;
        }

        $query = sprintf(
            "SELECT t.*, p.name as project_name, u.realname as assigned_to_name 
            FROM {$this->table} t 
            LEFT JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id 
            LEFT JOIN " . DB_PREFIX . "user u ON t.assigned_to = u.id 
            WHERE t.id = %d",
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
            "SELECT t.*, u.realname as assigned_to_name,
            (SELECT COUNT(*) FROM {$this->table} WHERE parent_id = t.id) as subtask_count
            FROM {$this->table} t 
            LEFT JOIN " . DB_PREFIX . "user u ON t.assigned_to = u.id 
            WHERE t.project_id = %d 
            ORDER BY t.position, t.created_at ASC",
            $project_id
        );
        return $this->fetchAll($query);
    }

    private function checkPermission($projectId, $taskId = null) {
        $project = null;
        $project = $this->fetchOne(
            "SELECT * FROM " . DB_PREFIX . "projects p" .  " WHERE p.id = " . intval($projectId)
        );
        
        $task = $this->getById($taskId);

        if (!$project || !$task) return false;
        
        $currentUserIdNumber = $_SESSION['id'];
        $currentUserId = $_SESSION['userid'];
        $isAssigned = in_array($currentUserIdNumber, explode(',', $task['assigned_to']));
        $isProjectManager = $_SESSION['authority'] == 'administrator';
       
        
        // If not manager by projects.manager_id, check groupware_project_members
        if (!$isProjectManager) {
            $memberCheck = $this->fetchOne(
                "SELECT COUNT(*) as count FROM " . DB_PREFIX . "project_members " .
                "WHERE project_id = " . intval($task['project_id']) . " " .
                "AND user_id = '" . $currentUserIdNumber . "' " .
                "AND role = 'manager'"
            );
            $isProjectManager = ($memberCheck && $memberCheck['count'] > 0);
        }
        if(!$isProjectManager){
            $departmentCheck = $this->fetchOne(
                "SELECT COUNT(*) as count FROM " . DB_PREFIX . "user_department ud " .
                "WHERE ud.department_id = " . intval($project['department_id']) . " " .
                "AND ud.userid = '" . $currentUserId . "' AND ud.project_manager = 1"
            );
            $isProjectManager = ($departmentCheck && $departmentCheck['count'] > 0);
        }

        $isTaskCreator = $task['created_by'] == $currentUserIdNumber;
        
        return $isAssigned || $isProjectManager || $isTaskCreator;
    }


    function getPermission() {
        $projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
        $project = null;
        $project = $this->fetchOne(
            "SELECT * FROM " . DB_PREFIX . "projects p" .  " WHERE p.id = " . intval($projectId)
        );

        if (!$project) return false;
        $departmentCheck = null;
        $currentUserIdNumber = $_SESSION['id'];
        $currentUserId = $_SESSION['userid'];
        $isAdmin = $_SESSION['authority'] == 'administrator';
        $isDepartmentManager = false;
        $isProjectManager = false;
        $isMember = false;
        
        // If not manager by projects.manager_id, check groupware_project_members
        if (!$isAdmin) {
            $memberCheck = $this->fetchOne(
                "SELECT COUNT(*) as count FROM " . DB_PREFIX . "project_members " .
                "WHERE project_id = " . intval($projectId) . " " .
                "AND user_id = '" . $currentUserIdNumber . "' " .
                "AND role = 'manager'"
            );
            $isProjectManager = ($memberCheck && $memberCheck['count'] > 0);
        }
        if (!$isAdmin) {
            $memberCheck = $this->fetchOne(
                "SELECT COUNT(*) as count FROM " . DB_PREFIX . "project_members " .
                "WHERE project_id = " . intval($projectId) . " " .
                "AND user_id = '" . $currentUserIdNumber . "' " .
                "AND role = 'member'"
            );
            $isMember = ($memberCheck && $memberCheck['count'] > 0);
        }
        if (!$isAdmin) {
            $departmentCheck = $this->fetchOne(
                "SELECT * FROM " . DB_PREFIX . "user_department ud " .
                "WHERE ud.department_id = " . intval($project['department_id']) . " " .
                "AND ud.userid = '" . $currentUserId . "' LIMIT 1"
            );

            $isDepartmentManager = ($departmentCheck && $departmentCheck['project_manager'] == 1);
        }

        $isTeamLeader = false;
        if (!$isAdmin) {
            $teamLeaderCheck = $this->fetchOne(
                "SELECT COUNT(*) as count FROM " . DB_PREFIX . "team_members " .
                "WHERE user_id = '" . $currentUserIdNumber . "' " .
                "AND leader = 1"
            );
            $isTeamLeader = ($teamLeaderCheck && $teamLeaderCheck['count'] > 0);
        }
        
        // Check if user is project creator
        $isCreator = false;
        if (!$isAdmin && isset($project['created_by'])) {
            $isCreator = $project['created_by'] == $currentUserId;
        }
        
        // Check if user is in the same department (even if not a member)
        $isInDepartment = false;
        if (!$isAdmin && $departmentCheck) {
            $isInDepartment = true;
        }
        
        // Fix: Check project_director safely
        $isProjectDirector = false;
        if ($departmentCheck && isset($departmentCheck['project_director'])) {
            $isProjectDirector = ($departmentCheck['project_director'] == 1);
        }

       
        return [
            'is_team_leader' => $isTeamLeader,
            'is_member' => $isAdmin || $isProjectManager || $isDepartmentManager || $isProjectDirector || $isMember || $isCreator,
            'is_director' => $isAdmin || $isProjectManager || $isDepartmentManager || $isProjectDirector,
            'can_manage_project' => $isAdmin || $isProjectManager || $isDepartmentManager,
            'can_manage_department' => $isAdmin || $isDepartmentManager,
            'is_creator' => $isCreator,
            'is_in_department' => $isInDepartment,
            'rule' => $departmentCheck
        ];
    }

    function updateOrder() {
        $taskIds = json_decode($_POST['task_ids'], true);
        $projectId = $_POST['project_id'];
        $draggedTaskId = isset($_POST['dragged_task_id']) ? intval($_POST['dragged_task_id']) : null;
        $newParentId = isset($_POST['new_parent_id']) ? (empty($_POST['new_parent_id']) ? null : intval($_POST['new_parent_id'])) : null;
        
        // Update positions
        $position = 0;
        foreach ($taskIds as $taskId) {
            $this->query_update(
                ['position' => $position],
                ['id' => $taskId, 'project_id' => $projectId]
            );
            $position++;
        }
        
        // Update parent_id for the dragged task if provided
        if ($draggedTaskId && $newParentId !== null) {
            // Check for circular reference
            if ($newParentId && $this->isDescendant($newParentId, $draggedTaskId)) {
                return [
                    'success' => false,
                    'message' => '循環参照を防ぐため、この操作は許可されていません'
                ];
            }
            
            // Update the parent_id
            $data = [
                'parent_id' => $newParentId,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $this->query_update($data, ['id' => $draggedTaskId]);
        }
        
        return [
            'success' => true,
            'message' => 'タスク順序が更新されました'
        ];
    }

    function setParent() {
        $taskId = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $parentId = isset($_POST['parent_id']) ? (empty($_POST['parent_id']) ? null : intval($_POST['parent_id'])) : null;
        
        if (!$taskId) {
            return [
                'success' => false,
                'message' => 'タスクIDが指定されていません'
            ];
        }
        
        // Get current task info
        $task = $this->getById($taskId);
        if (!$task) {
            return [
                'success' => false,
                'message' => 'タスクが見つかりません'
            ];
        }
        
        // If setting a parent, check for circular reference
        if ($parentId) {
            if ($taskId == $parentId) {
                return [
                    'success' => false,
                    'message' => '自分自身を親タスクに設定することはできません'
                ];
            }
            
            // Check if parent exists and is in the same project
            $parentTask = $this->getById($parentId);
            if (!$parentTask) {
                return [
                    'success' => false,
                    'message' => '親タスクが見つかりません'
                ];
            }
            
            if ($parentTask['project_id'] != $task['project_id']) {
                return [
                    'success' => false,
                    'message' => '異なるプロジェクトのタスクを親に設定することはできません'
                ];
            }
            
            // Check for circular reference (parent would become child of current task)
            if ($this->isDescendant($parentId, $taskId)) {
                return [
                    'success' => false,
                    'message' => '循環参照を防ぐため、この操作は許可されていません'
                ];
            }
        }
        
        // Update the task's parent_id
        $data = [
            'parent_id' => $parentId,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $result = $this->query_update($data, ['id' => $taskId]);
        
        if ($result) {
            // Update parent task progress if setting a parent
            // if ($parentId) {
            //     $this->updateParentTaskProgress($parentId);
            // }
            
            // // Update project progress
            // $this->updateProjectProgress($task['project_id']);
            
            return [
                'success' => true,
                'message' => $parentId ? 'サブタスクが作成されました' : 'サブタスクが解除されました'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'タスクの更新に失敗しました'
            ];
        }
    }
    
    private function isDescendant($taskId, $potentialAncestorId) {
        // Check if taskId is a descendant of potentialAncestorId using iterative approach
        $currentId = $taskId;
        $maxDepth = 10; // Prevent infinite loops
        $depth = 0;
        
        while ($currentId && $depth < $maxDepth) {
            $query = sprintf(
                "SELECT parent_id FROM {$this->table} WHERE id = %d",
                intval($currentId)
            );
            
            $result = $this->fetchOne($query);
            if (!$result || !$result['parent_id']) {
                break; // No parent found
            }
            
            $currentId = $result['parent_id'];
            $depth++;
            
            // Check if we found the potential ancestor
            if ($currentId == $potentialAncestorId) {
                return true;
            }
        }
        
        return false;
    }

    function updateTaskDate() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : null;
        $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : null;
        $progress = isset($_POST['progress']) ? intval($_POST['progress']) : null;
        
        if (!$id) {
            return [
                'success' => false,
                'message' => 'タスクIDが指定されていません'
            ];
        }
        
        $data = [
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($start_date !== null) {
            $data['start_date'] = $start_date;
        }
        if ($due_date !== null) {
            $data['due_date'] = $due_date;
        }
        if ($progress !== null) {
            $data['progress'] = $progress;
        }
        
        $result = $this->query_update($data, ['id' => $id]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'タスクが更新されました'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'タスクの更新に失敗しました'
            ];
        }
    }

    function updateTaskParent() {
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $parent_id = isset($_POST['parent_id']) ? (empty($_POST['parent_id']) ? null : intval($_POST['parent_id'])) : null;
        
        if (!$task_id) {
            return [
                'success' => false,
                'message' => 'タスクIDが指定されていません'
            ];
        }
        
        // Get current task info
        $task = $this->getById($task_id);
        if (!$task) {
            return [
                'success' => false,
                'message' => 'タスクが見つかりません'
            ];
        }
        
        // If setting a parent, check for circular reference
        if ($parent_id) {
            if ($task_id == $parent_id) {
                return [
                    'success' => false,
                    'message' => '自分自身を親タスクに設定することはできません'
                ];
            }
            
            // Check if parent exists and is in the same project
            $parentTask = $this->getById($parent_id);
            if (!$parentTask) {
                return [
                    'success' => false,
                    'message' => '親タスクが見つかりません'
                ];
            }
            
            if ($parentTask['project_id'] != $task['project_id']) {
                return [
                    'success' => false,
                    'message' => '異なるプロジェクトのタスクを親に設定することはできません'
                ];
            }
            
            // Check for circular reference (parent would become child of current task)
            if ($this->isDescendant($parent_id, $task_id)) {
                return [
                    'success' => false,
                    'message' => '循環参照を防ぐため、この操作は許可されていません'
                ];
            }
        }
        
        // Update the task's parent_id
        $data = [
            'parent_id' => $parent_id,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $result = $this->query_update($data, ['id' => $task_id]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => $parent_id ? 'サブタスクが作成されました' : 'サブタスクが解除されました'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'タスクの更新に失敗しました'
            ];
        }
    }

    // Lấy tất cả links của project
    function getLinksByProject($params = null) {
        $project_id = is_array($params) ? intval($params['project_id']) : intval($params);
        if (!$project_id) return [];
        $query = "SELECT l.* FROM " . DB_PREFIX . "task_links l
                  WHERE l.project_id = $project_id";
        return $this->fetchAll($query);
    }

    // Thêm link
    function addTaskLink() {
        $project_id = intval($_POST['project_id']);
        $source = intval($_POST['source_task_id']);
        $target = intval($_POST['target_task_id']);
        $type = isset($_POST['link_type']) ? $_POST['link_type'] : '0';
        if (!$source || !$target) return ['success' => false, 'message' => 'Thiếu thông tin'];
        $data = [
            'source_task_id' => $source,
            'project_id' => $project_id,
            'target_task_id' => $target,
            'link_type' => $type,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $this->table = DB_PREFIX . 'task_links';
        $result = $this->query_insert($data);
        $this->table = DB_PREFIX . 'tasks';
        return $result ? ['success' => true] : ['success' => false];
    }

    // Xóa link
    function deleteTaskLink() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'message' => 'No link id'];
        $this->table = DB_PREFIX . 'task_links';
        $result = $this->query_delete(['id' => $id]);
        $this->table = DB_PREFIX . 'tasks';
        if ($result) {
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'message' => 'Delete failed'];
        }
    }

    // Ghi log hành động task
    private function logTaskAction($task_id, $action, $note = '', $value1 = '', $value2 = '') {
        $user_id = $_SESSION['userid'] ?? '';
        $username = $_SESSION['realname'] ?? '';
        $data = [
            'task_id' => $task_id,
            'user_id' => $user_id,
            'username' => $username,
            'action' => $action,
            'note' => $note,
            'value1' => $value1,
            'value2' => $value2,
            'time' => date('Y-m-d H:i:s')
        ];
        $this->table = DB_PREFIX . 'task_logs';
        $this->query_insert($data);
        $this->table = DB_PREFIX . 'tasks'; // reset lại table
    }

    // Lấy lịch sử hành động của task
    function getLogs($params = null) {
        $task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;
        if (!$task_id) return [];

        $query = sprintf(
            "SELECT l.*, u.realname, u.user_image FROM " . DB_PREFIX . "task_logs l
            LEFT JOIN " . DB_PREFIX . "user u ON l.user_id = u.userid
            WHERE l.task_id = %d ORDER BY l.time DESC",
            $task_id
        );
        $logs = $this->fetchAll($query);
        return $logs;
    }
    
    function toggleLike() {
        $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
        $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : '';
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        $name = isset($_POST['name']) ? $_POST['name'] : '';
        
        if (!$comment_id || !$user_id) {
            return ['success' => false, 'message' => 'Invalid parameters'];
        }
        
        $this->table = DB_PREFIX . 'comment_likes';
        
        if ($action === 'like') {
            // Check if already liked
            $existing = $this->fetchOne(sprintf(
                "SELECT * FROM %s WHERE comment_id = %d AND user_id = '%s'",
                $this->table,
                $comment_id,
                $this->quote($user_id)
            ));
            
            if (!$existing) {
                $data = [
                    'comment_id' => $comment_id,
                    'user_id' => $user_id,
                    'name' => $name ?: $_SESSION['realname'] ?? 'Unknown',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $this->query_insert($data);
            }
        } else {
            // Unlike
            $result = $this->query(sprintf(
                "DELETE FROM " . DB_PREFIX . "comment_likes 
                 WHERE comment_id = %d AND user_id = '%s'",
                $comment_id, $user_id
            ));
        }
        
        // Get updated like count and names
        $query = sprintf(
            "SELECT COUNT(*) as like_count,
            GROUP_CONCAT(name) as liked_by_names
            FROM " . DB_PREFIX . "comment_likes
            WHERE comment_id = %d",
            $comment_id
        );
        
        $result = $this->fetchOne($query);
        $this->table = DB_PREFIX . 'tasks'; // Reset table
        
        return [
            'success' => true,
            'like_count' => intval($result['like_count']),
            'liked_by_names' => $result['liked_by_names'] ? explode(',', $result['liked_by_names']) : []
        ];
    }

    /**
     * Toggle like/dislike reaction for a task (with optional note)
     */
    function toggleTaskReaction() {
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $type = isset($_POST['type']) ? $_POST['type'] : '';
        $note = isset($_POST['note']) ? trim($_POST['note']) : '';
        $selected_reasons = isset($_POST['selected_reasons']) ? trim($_POST['selected_reasons']) : '';
        $custom_note = isset($_POST['custom_note']) ? trim($_POST['custom_note']) : '';
        $user_id = isset($_SESSION['userid']) ? $_SESSION['userid'] : '';
        
        if (!$task_id || !$user_id || !in_array($type, ['like', 'dislike'])) {
            return ['success' => false, 'message' => 'Invalid parameters'];
        }
        
        $this->table = DB_PREFIX . 'task_reactions';
        
        // Check existing reaction for this user & task
        $existing = $this->fetchOne(sprintf(
            "SELECT * FROM %s WHERE task_id = %d AND user_id = '%s'",
            $this->table,
            $task_id,
            $this->quote($user_id)
        ));
        
        $now = date('Y-m-d H:i:s');
        $is_delete = isset($_POST['delete']) && $_POST['delete'] == '1';
        
        // Prepare note data: store structured data as JSON if selected_reasons or custom_note provided
        $noteData = $note; // Default to combined note for backward compatibility
        if ($selected_reasons || $custom_note) {
            $selectedReasonsArray = $selected_reasons ? explode(',', $selected_reasons) : [];
            $selectedReasonsArray = array_filter(array_map('trim', $selectedReasonsArray));
            $noteData = json_encode([
                'selected_reasons' => $selectedReasonsArray,
                'custom_note' => $custom_note,
                'combined' => $note // Keep combined text for display
            ], JSON_UNESCAPED_UNICODE);
        }
        
        if ($is_delete && $existing) {
            // Delete reaction
            $this->query(sprintf(
                "DELETE FROM %s WHERE id = %d",
                $this->table,
                $existing['id']
            ));
        } else if ($existing) {
            // Update type & note
            $data = [
                'type' => $type,
                'note' => $noteData,
                'updated_at' => $now
            ];
            $this->query_update($data, ['id' => $existing['id']]);
        } else if (!$is_delete) {
            // Insert new reaction
            $data = [
                'task_id' => $task_id,
                'user_id' => $user_id,
                'type' => $type,
                'note' => $noteData,
                'created_at' => $now,
                'updated_at' => $now
            ];
            $this->query_insert($data);
        }
        
        // Recalculate like/dislike counts
        $countQuery = sprintf(
            "SELECT 
                SUM(CASE WHEN type = 'like' THEN 1 ELSE 0 END) as like_count,
                SUM(CASE WHEN type = 'dislike' THEN 1 ELSE 0 END) as dislike_count
            FROM " . DB_PREFIX . "task_reactions
            WHERE task_id = %d",
            $task_id
        );
        $counts = $this->fetchOne($countQuery);
        
        // Get current user's reaction after update/delete
        $current_reaction = null;
        if (!$is_delete) {
            $current_reaction = $type;
        } else {
            $current_reaction = null;
        }
        
        $this->table = DB_PREFIX . 'tasks'; // Reset table
        
        return [
            'success' => true,
            'task_id' => $task_id,
            'like_count' => intval($counts['like_count'] ?? 0),
            'dislike_count' => intval($counts['dislike_count'] ?? 0),
            'current_user_reaction' => $current_reaction
        ];
    }
    
    /**
     * Get task reaction details (for editing existing reaction)
     */
    function getTaskReaction() {
        $task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;
        $user_id = isset($_SESSION['userid']) ? $_SESSION['userid'] : '';
        
        if (!$task_id || !$user_id) {
            return ['success' => false, 'message' => 'Invalid parameters'];
        }
        
        $this->table = DB_PREFIX . 'task_reactions';
        $reaction = $this->fetchOne(sprintf(
            "SELECT * FROM %s WHERE task_id = %d AND user_id = '%s'",
            $this->table,
            $task_id,
            $this->quote($user_id)
        ));
        
        $this->table = DB_PREFIX . 'tasks'; // Reset table
        
        if ($reaction) {
            // Use null coalescing instead of logical OR to avoid boolean casting
            $note = isset($reaction['note']) ? $reaction['note'] : '';
            $selected_reasons = [];
            $custom_note = '';
            
            // Try to parse as JSON (new format)
            $decoded = json_decode($note, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $selected_reasons = isset($decoded['selected_reasons']) ? $decoded['selected_reasons'] : [];
                $custom_note = isset($decoded['custom_note']) ? $decoded['custom_note'] : '';
                // Use combined text if available, otherwise use custom_note
                $note = isset($decoded['combined']) ? $decoded['combined'] : $custom_note;
            } else {
                // Old format: plain text note
                $custom_note = $note;
            }
            
            return [
                'success' => true,
                'type' => $reaction['type'],
                'note' => $note,
                'selected_reasons' => $selected_reasons,
                'custom_note' => $custom_note
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Reaction not found'
        ];
    }
    
    // Detect mentions and send notifications
    private function sendMentionNotifications($projectId, $content, $commentUserId, $commentId, $taskId) {
        try {
            require_once(DIR_MODEL . 'NotificationService.php');
            $notiService = new NotificationService();

            $notiService->sendTaskCommentNotification($taskId, $commentId);

            // Extract mentioned users from content
            $mentionedUsers = $this->extractMentions($content);
            
            if (empty($mentionedUsers)) {
                return; // No mentions found
            }
            
            // Get project info for notification
            $project = $this->fetchOne(sprintf(
                "SELECT * FROM " . DB_PREFIX . "projects WHERE id = %d",
                intval($projectId)
            ));
            if (!$project) {
                return;
            }
            
            // Track sent notifications to avoid duplicates
            $sentUserIds = [];
            
            // Send notification to each mentioned user
            foreach ($mentionedUsers as $mentionedUser) {
                // Don't send notification if user mentions themselves
                if ($mentionedUser['userid'] == $commentUserId) {
                    continue;
                }
                
                // Don't send duplicate notifications to the same user
                if (in_array($mentionedUser['userid'], $sentUserIds)) {
                    continue;
                }
                
                $sentUserIds[] = $mentionedUser['userid'];
                
                $payload = [
                    'event' => 'task_mention',
                    'title' => 'タスクでメンションされました',
                    'message' => sprintf('%sさんがタスクでメンションしました', 
                        $_SESSION['realname']
                    ),
                    'data' => [
                        'project_id' => $projectId,
                        'project_name' => $project['name'],
                        'comment_id' => $commentId,
                        'comment_content' => $content,
                        'commenter_id' => $commentUserId,
                        'commenter_name' => $_SESSION['realname'],
                        'avatar' => $this->getUserImage(),
                        'url' => "/project/task.php?id=$projectId&task_id=$taskId#comment-$commentId"
                    ],
                    'project_id' => $projectId,
                    'user_ids' => [$mentionedUser['userid']]
                ];
                
                $notiService->create($payload);
            }
            
        } catch (Exception $e) {
            error_log('Failed to send mention notification: ' . $e->getMessage());
        }
    }
    
    // Extract mentions from content (supports both plain text and HTML)
    private function extractMentions($content) {
        $mentionedUsers = [];
        
        // First, try to extract from HTML mentions with data attributes
        if (strpos($content, 'data-user-id') !== false) {
            // Extract user IDs from HTML mentions - improved regex for multiple mentions
            preg_match_all('/<span[^>]*data-user-id="([^"]+)"[^>]*data-user-name="([^"]+)"[^>]*>@([^<]+)<\/span>/', $content, $matches);
            if (!empty($matches[1])) {
                $userIds = $matches[1];
                
                // Remove duplicates while preserving order
                $uniqueUserIds = array_unique($userIds);
                
                // Get user info by user IDs - using safe parameter binding
                if (!empty($uniqueUserIds)) {
                    $placeholders = str_repeat('?,', count($uniqueUserIds) - 1) . '?';
                    $query = "SELECT userid, realname, user_image FROM " . DB_PREFIX . "user WHERE userid IN ($placeholders)";
                    $users = $this->fetchAllWithParams($query, $uniqueUserIds);
                    
                    foreach ($users as $user) {
                        $mentionedUsers[] = $user;
                    }
                }
            }
        }
       
        // If no HTML mentions found, try plain text mentions
        if (empty($mentionedUsers)) {
            $plainText = strip_tags($content);
            preg_match_all('/@([^\s]+)/', $plainText, $matches);
            
            if (!empty($matches[1])) {
                $mentionedUsernames = $matches[1];
                
                // Remove duplicates while preserving order
                $uniqueUsernames = array_unique($mentionedUsernames);
                
                if (!empty($uniqueUsernames)) {
                    // Get mentioned users from database by username - using safe parameter binding
                    $placeholders = str_repeat('?,', count($uniqueUsernames) - 1) . '?';
                    $query = "SELECT userid, realname, user_image FROM " . DB_PREFIX . "user WHERE realname IN ($placeholders)";
                    $mentionedUsers = $this->fetchAllWithParams($query, $uniqueUsernames);
                }
            }
        }
        
        return $mentionedUsers;
    }

    /**
     * Fetch all records with prepared statement parameters
     * @param string $query SQL query with placeholders
     * @param array $params Parameters to bind
     * @return array Results
     */
    private function fetchAllWithParams($query, $params) {
        $this->connect();
        if (!$this->handler) {
            return [];
        }
        
        // Prepare statement
        $stmt = mysqli_prepare($this->handler, $query);
        if (!$stmt) {
            error_log("Prepare failed: " . mysqli_error($this->handler));
            return [];
        }
        
        // Bind parameters
        if (!empty($params)) {
            // Create types string (assuming all are strings for user IDs/names)
            $types = str_repeat('s', count($params));
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        
        // Execute query
        if (!mysqli_stmt_execute($stmt)) {
            error_log("Execute failed: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return [];
        }
        
        // Get results
        $result = mysqli_stmt_get_result($stmt);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        
        mysqli_stmt_close($stmt);
        return $data;
    }
    
    private function getUserImage() {
        return $_SESSION['user_image'] ?? '';
    }

    // Sửa comment task
    function updateComment() {
        $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
        $content = isset($_POST['content']) ? $_POST['content'] : '';
        $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : '';
        if (!$comment_id || !$user_id) {
            return ['success' => false, 'message' => 'Invalid parameters'];
        }
        $this->table = DB_PREFIX . 'comments';
        $comment = $this->fetchOne("SELECT * FROM " . DB_PREFIX . "comments WHERE id = $comment_id");
        if (!$comment) {
            $this->table = DB_PREFIX . 'tasks';
            return ['success' => false, 'message' => 'Comment not found'];
        }
        // Chỉ cho phép sửa nếu là chủ comment hoặc admin
        if ($_SESSION['authority'] != 'administrator' && $comment['user_id'] != $user_id) {
            $this->table = DB_PREFIX . 'tasks';
            return ['success' => false, 'message' => 'Permission denied'];
        }
        $data = [
            'content' => $content,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $result = $this->query_update($data, ['id' => $comment_id]);
        $this->table = DB_PREFIX . 'tasks';
        return $result ? ['success' => true] : ['success' => false, 'message' => 'Update failed'];
    }

    // Xóa comment task
    function deleteComment() {
        $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
        $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : '';
        if (!$comment_id || !$user_id) {
            return ['success' => false, 'message' => 'Invalid parameters'];
        }
        $this->table = DB_PREFIX . 'comments';
        $comment = $this->fetchOne("SELECT * FROM " . DB_PREFIX . "comments WHERE id = $comment_id");
        if (!$comment) {
            $this->table = DB_PREFIX . 'tasks';
            return ['success' => false, 'message' => 'Comment not found'];
        }
        // Chỉ cho phép xóa nếu là chủ comment hoặc admin
        if ($_SESSION['authority'] != 'administrator' && $comment['user_id'] != $user_id) {
            $this->table = DB_PREFIX . 'tasks';
            return ['success' => false, 'message' => 'Permission denied'];
        }
        $result = $this->query_delete(['id' => $comment_id]);
        $this->table = DB_PREFIX . 'tasks';
        return $result ? ['success' => true] : ['success' => false, 'message' => 'Delete failed'];
    }

   
    // API: Lấy số comment chưa đọc của 1 task cho user hiện tại dựa vào thời gian truy cập
    function getTaskUnreadCommentCount($task_id = null) {
        if(!$task_id) {
            $task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;
        }
        $user_id = $_SESSION['userid'] ?? '';
        if (!$task_id || !$user_id) return ['unread_count' => 0];
        // Lấy thời gian đã đọc gần nhất
        $sqlRead = "SELECT read_at FROM groupware_comment_reads WHERE task_id = $task_id AND user_id = '" . $this->quote($user_id) . "' ORDER BY read_at DESC LIMIT 1";
        $rowRead = $this->fetchOne($sqlRead);
        $read_at = $rowRead && !empty($rowRead['read_at']) ? $rowRead['read_at'] : null;
        if ($read_at) {
            // Đếm số comment mới hơn thời gian đã đọc
            $sql = "SELECT COUNT(*) as unread_count FROM " . DB_PREFIX . "comments WHERE user_id != '" . $this->quote($user_id) . "' AND task_id = $task_id AND created_at > '" . $this->quote($read_at) . "'";
        } else {
            // Nếu chưa từng đọc, trả về tổng số comment
            $sql = "SELECT COUNT(*) as unread_count FROM " . DB_PREFIX . "comments WHERE user_id != '" . $this->quote($user_id) . "' AND task_id = $task_id";
        }
        $row = $this->fetchOne($sql);
        return ['unread_count' => intval($row['unread_count'] ?? 0)];
    }

    // Khi truy cập task, update hoặc insert read_at = NOW()
    function markTaskCommentsAsRead() {
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $user_id = $_SESSION['userid'] ?? '';
        if (!$task_id || !$user_id) return ['success' => false, 'message' => 'Invalid parameters'];
        $now = date('Y-m-d H:i:s');
        // Nếu đã có bản ghi thì update, chưa có thì insert
        $sql = "SELECT id FROM groupware_comment_reads WHERE task_id = $task_id AND user_id = '" . $this->quote($user_id) . "'";
        echo $sql;
        $row = $this->fetchOne($sql);
        if ($row && isset($row['id'])) {
            echo "update";
            $update = "UPDATE groupware_comment_reads SET read_at = '$now' WHERE id = " . intval($row['id']);
            $this->query($update);
        } else {
            echo "insert";
            $insert = "INSERT INTO groupware_comment_reads (task_id, user_id, read_at) VALUES ($task_id, '" . $this->quote($user_id) . "', '$now')";
            $this->query($insert);
        }
        return ['success' => true];
    }

    /**
     * Send task notification
     */
    function sendTaskNotification($params = null) {
        try {
            require_once('NotificationService.php');
            $notiService = new NotificationService();
            
            // Validate required parameters
            if (!isset($params['event']) || !isset($params['title']) || !isset($params['message'])) {
                return false;
            }
            
            // Default values
            $defaultParams = [
                'project_id' => 0,
                'task_id' => 0,
                'user_ids' => [],
                'data' => [],
                'url' => '',
                'priority' => 'normal',
                'type' => 'task'
            ];
            
            $params = array_merge($defaultParams, $params);
            
            // Get target users
            $targetUserIds = [];
            
            // Direct user IDs
            if (!empty($params['user_ids'])) {
                $targetUserIds = array_merge($targetUserIds, $params['user_ids']);
            }
            
            // Remove duplicates and current user
            $targetUserIds = array_unique($targetUserIds);
            $targetUserIds = array_diff($targetUserIds, [$_SESSION['userid']]);
            
            if (empty($targetUserIds)) {
                return false;
            }
            
            // Prepare notification payload
            $payload = [
                'event' => $params['event'],
                'title' => $params['title'],
                'message' => $params['message'],
                'data' => array_merge($params['data'], [
                    'project_id' => $params['project_id'],
                    'task_id' => $params['task_id'],
                    'type' => $params['type'],
                    'priority' => $params['priority'],
                    'sender_id' => $_SESSION['userid'],
                    'sender_name' => $_SESSION['realname'] ?? 'Unknown',
                    'timestamp' => date('Y-m-d H:i:s')
                ]),
                'url' => $params['url'],
                'user_ids' => array_values($targetUserIds)
            ];
            
            // Send notification
            $result = $notiService->create($payload);
            
            return $result ? true : false;
            
        } catch (Exception $e) {
            error_log('Error sending task notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user realname for notifications
     */
    function getUserRealname() {
        if (isset($_SESSION['lastname']) && $_SESSION['lastname'] != '') {
            return $_SESSION['lastname'] . 'さん';
        }
        return (isset($_SESSION['realname']) ? $_SESSION['realname'] : 'Unknown') . 'さん';
    }

    /**
     * Get user image URL for notifications
     */
    private function getUserImageUrl() {
        $userImage = $this->getUserImage();
        if ($userImage && $userImage != '') {
            return '/assets/upload/avatar/' . $userImage;
        }
        return '/assets/img/avatars/1.png';
    }

    /**
     * Convert user IDs (numeric id) to userid (string)
     */
    private function convertIdsToUserIds($ids) {
        if (empty($ids)) {
            return [];
        }
        
        // Convert to array if string
        if (is_string($ids)) {
            $ids = array_filter(array_map('trim', explode(',', $ids)));
        }
        
        if (empty($ids)) {
            return [];
        }
        
        // Convert to integers and filter
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids);
        
        if (empty($ids)) {
            return [];
        }
        
        // Query user table to get userid from id
        $placeholders = str_repeat('%d,', count($ids) - 1) . '%d';
        $query = sprintf(
            "SELECT userid FROM " . DB_PREFIX . "user WHERE id IN ($placeholders)",
            ...$ids
        );
        
        $users = $this->fetchAll($query);
        
        if (empty($users)) {
            return [];
        }
        
        return array_column($users, 'userid');
    }

    /**
     * Notify users when a task is created and assigned
     */
    function notifyTaskCreated($taskId, $taskTitle, $projectId, $projectNumber, $projectName, $assignedUserIds) {
        error_log("notifyTaskCreated: " . print_r($assignedUserIds, true));
        if (empty($assignedUserIds)) {
            return false;
        }
        
        // Convert numeric IDs to userid strings
        $userIds = $this->convertIdsToUserIds($assignedUserIds);
        
        if (empty($userIds)) {
            return false;
        }
        
        $params = [
            'event' => 'task_created',
            'title' => '#'.$projectNumber.': タスクが作成されました',
            'message' => sprintf('%sがあなたにタスク「%s」を割り当てました', $this->getUserRealname(), $taskTitle),
            'project_id' => $projectId,
            'task_id' => $taskId,
            'user_ids' => $userIds,
            'data' => [
                'task_title' => $taskTitle,
                'project_name' => $projectName,
                'project_number' => $projectNumber,
                'avatar' => $this->getUserImageUrl(),
                'action' => 'task_created',
                'url' => "/project/task.php?project_id=$projectId",
            ],
            'type' => 'task',
            'priority' => 'normal'
        ];
        
        return $this->sendTaskNotification($params);
    }

    /**
     * Notify users when task assignees are changed
     */
    function notifyTaskAssigneeChanged($taskId, $taskTitle, $projectId, $projectNumber, $projectName, $newAssignedUserIds, $oldAssignedUserIds = []) {
        // Convert to arrays if strings
        if (is_string($newAssignedUserIds)) {
            $newAssignedUserIds = array_filter(array_map('trim', explode(',', $newAssignedUserIds)));
        }
        if (is_string($oldAssignedUserIds)) {
            $oldAssignedUserIds = array_filter(array_map('trim', explode(',', $oldAssignedUserIds)));
        }
        
        // Find newly assigned IDs (numeric IDs in new but not in old)
        $newlyAssignedIds = array_diff($newAssignedUserIds, $oldAssignedUserIds);
        
        if (empty($newlyAssignedIds)) {
            return false;
        }
        
        // Convert only newly assigned IDs to userid strings
        $newlyAssigned = $this->convertIdsToUserIds($newlyAssignedIds);
        
        if (empty($newlyAssigned)) {
            return false;
        }
        
        $params = [
            'event' => 'task_assigned',
            'title' => '#'.$projectNumber.': タスクが割り当てられました',
            'message' => sprintf('%sがあなたにタスク「%s」を割り当てました', $this->getUserRealname(), $taskTitle),
            'project_id' => $projectId,
            'task_id' => $taskId,
            'user_ids' => array_values($newlyAssigned),
            'data' => [
                'task_title' => $taskTitle,
                'project_name' => $projectName,
                'project_number' => $projectNumber,
                'avatar' => $this->getUserImageUrl(),
                'action' => 'task_assigned',
                'url' => "/project/task.php?project_id=$projectId",
            ],
            'type' => 'task',
            'priority' => 'normal'
        ];
        
        return $this->sendTaskNotification($params);
    }

    /**
     * Overview of all tasks grouped by department / team / user
     *
     * Returns:
     * - departments: [{id, name}]
     * - teams:       [{id, name, department_id}]
     * - users:       [{id, userid, realname, department_id, department_name, teams:[{id,name}]}]
     * - tasks:       [{
     *                    id, title, status, priority, project_id, project_number, project_name,
     *                    department_id, department_name, assigned_to_ids:[]
     *                 }]
     * - unassigned_users: same structure as users (no tasks assigned)
     */
    function listOverview($params = null) {
        // Optional filters - support both $params array and $_GET
        if (is_array($params)) {
            $department_id     = isset($params['department_id']) ? intval($params['department_id']) : 0;
            $team_id           = isset($params['team_id']) ? intval($params['team_id']) : 0;
            $user_id           = isset($params['user_id']) ? intval($params['user_id']) : 0;
            $exclude_completed = isset($params['exclude_completed']) ? intval($params['exclude_completed']) : 0;
        } else {
            $department_id     = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
            $team_id           = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;
            $user_id           = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
            $exclude_completed = isset($_GET['exclude_completed']) ? intval($_GET['exclude_completed']) : 0;
        }

        // 1. Load departments
        // Hiển thị cùng thứ tự như department.php (Department::list): is_active = 1, ORDER BY id ASC
        $departments = $this->fetchAll(
            "SELECT id, name 
             FROM " . DB_PREFIX . "departments 
             WHERE is_active = 1
             ORDER BY id ASC"
        );

        // 2. Load teams
        $teamWhere = "WHERE t.is_active = 1";
        if ($department_id > 0) {
            $teamWhere .= " AND t.department_id = " . intval($department_id);
        }
        $teams = $this->fetchAll(
            "SELECT t.id, t.name, t.department_id 
             FROM " . DB_PREFIX . "team t
             $teamWhere
             ORDER BY t.department_id ASC, t.name ASC"
        );

        // 3. Load users with department & team info
        $userWhereArr = ["(u.is_suspend = 0 OR u.is_suspend IS NULL OR u.is_suspend = '')"];
        if ($department_id > 0) {
            $userWhereArr[] = "ud.department_id = " . intval($department_id);
        }
        if ($team_id > 0) {
            $userWhereArr[] = "tm.team_id = " . intval($team_id);
        }
        if ($user_id > 0) {
            $userWhereArr[] = "u.id = " . intval($user_id);
        }
        $userWhere = "WHERE " . implode(" AND ", $userWhereArr);

        $userRows = $this->fetchAll(
            "SELECT 
                u.id,
                u.userid,
                u.realname,
                u.user_image,
                ud.department_id,
                d.name AS department_name,
                tm.team_id,
                t.name AS team_name
             FROM " . DB_PREFIX . "user u
             LEFT JOIN " . DB_PREFIX . "user_department ud ON ud.userid = u.userid
             LEFT JOIN " . DB_PREFIX . "departments d ON ud.department_id = d.id
             LEFT JOIN " . DB_PREFIX . "team_members tm ON tm.user_id = u.id
             LEFT JOIN " . DB_PREFIX . "team t ON tm.team_id = t.id AND t.is_active = 1
             $userWhere
             ORDER BY u.id ASC"
        );

        // Aggregate user data (one row per user, with teams array)
        $users = [];
        foreach ($userRows as $row) {
            $uid = $row['id'];
            if (!isset($users[$uid])) {
                $users[$uid] = [
                    'id' => $uid,
                    'userid' => $row['userid'],
                    'realname' => $row['realname'],
                    'user_image' => isset($row['user_image']) ? $row['user_image'] : '',
                    'department_id' => $row['department_id'],
                    'department_name' => $row['department_name'],
                    'teams' => []
                ];
            }
            if (!empty($row['team_id'])) {
                // Avoid duplicate team entries
                $exists = false;
                foreach ($users[$uid]['teams'] as $t) {
                    if ($t['id'] == $row['team_id']) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $users[$uid]['teams'][] = [
                        'id' => $row['team_id'],
                        'name' => $row['team_name']
                    ];
                }
            }
        }

        // 4. Load tasks with project & department info
        $taskWhereArr = ["1=1"];
        if ($department_id > 0) {
            $taskWhereArr[] = "p.department_id = " . intval($department_id);
        }
        // Filter by user (internal user id in assigned_to CSV)
        if ($user_id > 0) {
            $taskWhereArr[] = "FIND_IN_SET(" . intval($user_id) . ", t.assigned_to) > 0";
        }
        // Loại bỏ completed & cancelled từ phía DB nếu được yêu cầu
        if ($exclude_completed) {
            $taskWhereArr[] = "t.status NOT IN ('completed','cancelled')";
        }
        $taskWhere = "WHERE " . implode(" AND ", $taskWhereArr);
        // We do not filter by team here because team is derived from users
        $taskRows = $this->fetchAll(
            "SELECT 
                t.id,
                t.project_id,
                t.title,
                t.status,
                t.priority,
                t.assigned_to,
                t.created_by,
                t.due_date,
                t.start_date,
                t.progress,
                p.project_number,
                p.name AS project_name,
                p.department_id,
                p.end_date AS project_end_date,
                d.name AS department_name,
                u_creator.realname AS created_by_name,
                u_creator.user_image AS created_by_user_image
             FROM " . DB_PREFIX . "tasks t
             LEFT JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id
             LEFT JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id
             LEFT JOIN " . DB_PREFIX . "user u_creator ON t.created_by = u_creator.id
             $taskWhere
             ORDER BY p.department_id ASC, t.project_id ASC, t.position ASC, t.created_at DESC"
        );

        $taskIds = array_map(function($r) { return $r['id']; }, $taskRows);
        $acknowledgementsMap = [];
        if (!empty($taskIds)) {
            $ackQuery = sprintf(
                "SELECT task_id, user_id, acknowledged, acknowledged_at FROM " . DB_PREFIX . "task_assignees WHERE task_id IN (%s)",
                implode(',', array_map('intval', $taskIds))
            );
            $ackRows = $this->fetchAll($ackQuery);
            foreach ($ackRows as $ack) {
                $tid = (int)$ack['task_id'];
                $uid = (int)$ack['user_id'];
                if (!isset($acknowledgementsMap[$tid])) {
                    $acknowledgementsMap[$tid] = [];
                }
                $acknowledgementsMap[$tid][(string)$uid] = [
                    'acknowledged' => (int)$ack['acknowledged'],
                    'acknowledged_at' => $ack['acknowledged_at']
                ];
            }
        }

        $tasks = [];
        $assignedUserIdSet = [];
        foreach ($taskRows as $row) {
            // Parse assigned_to (internal user IDs, comma separated)
            $assignedIds = [];
            if (!empty($row['assigned_to'])) {
                $parts = explode(',', $row['assigned_to']);
                foreach ($parts as $part) {
                    $id = intval(trim($part));
                    if ($id > 0) {
                        $assignedIds[] = $id;
                        $assignedUserIdSet[$id] = true;
                    }
                }
            }

            $tasks[] = [
                'id' => $row['id'],
                'project_id' => $row['project_id'],
                'project_number' => $row['project_number'],
                'project_name' => $row['project_name'],
                'title' => $row['title'],
                'status' => $row['status'],
                'priority' => $row['priority'],
                'due_date' => $row['due_date'],
                'start_date' => $row['start_date'],
                'progress' => $row['progress'],
                'department_id' => $row['department_id'],
                'department_name' => $row['department_name'],
                'project_end_date' => isset($row['project_end_date']) ? $row['project_end_date'] : null,
                'assigned_to_ids' => $assignedIds,
                'created_by' => isset($row['created_by']) ? $row['created_by'] : null,
                'created_by_name' => isset($row['created_by_name']) ? $row['created_by_name'] : null,
                'created_by_user_image' => isset($row['created_by_user_image']) ? $row['created_by_user_image'] : null,
                'acknowledgements' => $acknowledgementsMap[$row['id']] ?? []
            ];
        }

        // 5. Determine unassigned users (no active tasks: exclude completed & cancelled)
        // Build set of users that have at least one active task
        $activeAssignedUserIdSet = [];
        $activeWhereArr = ["1=1", "t.status NOT IN ('completed','cancelled')"];
        if ($department_id > 0) {
            $activeWhereArr[] = "p.department_id = " . intval($department_id);
        }
        $activeWhere = "WHERE " . implode(" AND ", $activeWhereArr);
        $activeRows = $this->fetchAll(
            "SELECT t.assigned_to
             FROM " . DB_PREFIX . "tasks t
             LEFT JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id
             $activeWhere"
        );
        foreach ($activeRows as $row) {
            if (!empty($row['assigned_to'])) {
                $parts = explode(',', $row['assigned_to']);
                foreach ($parts as $part) {
                    $id = intval(trim($part));
                    if ($id > 0) {
                        $activeAssignedUserIdSet[$id] = true;
                    }
                }
            }
        }

        $unassigned_users = [];
        foreach ($users as $u) {
            $uid = $u['id'];
            if (!isset($activeAssignedUserIdSet[$uid])) {
                $unassigned_users[] = $u;
            }
        }

        // 6. Ensure users list includes all assignees and creators from tasks (for avatar/name display)
        $displayUserIds = $assignedUserIdSet;
        foreach ($taskRows as $row) {
            if (!empty($row['created_by'])) {
                $displayUserIds[(int)$row['created_by']] = true;
            }
        }
        $missingUserIds = array_diff(array_keys($displayUserIds), array_keys($users));
        if (!empty($missingUserIds)) {
            $placeholders = implode(',', array_map('intval', $missingUserIds));
            $extraRows = $this->fetchAll(
                "SELECT u.id, u.userid, u.realname, u.user_image,
                        (SELECT ud.department_id FROM " . DB_PREFIX . "user_department ud WHERE ud.userid = u.userid LIMIT 1) AS department_id,
                        (SELECT d.name FROM " . DB_PREFIX . "user_department ud2
                         LEFT JOIN " . DB_PREFIX . "departments d ON d.id = ud2.department_id
                         WHERE ud2.userid = u.userid LIMIT 1) AS department_name
                 FROM " . DB_PREFIX . "user u
                 WHERE u.id IN ($placeholders)"
            );
            foreach ($extraRows as $row) {
                $uid = (int)$row['id'];
                if (!isset($users[$uid])) {
                    $users[$uid] = [
                        'id' => $uid,
                        'userid' => $row['userid'],
                        'realname' => $row['realname'],
                        'user_image' => isset($row['user_image']) ? $row['user_image'] : '',
                        'department_id' => $row['department_id'],
                        'department_name' => $row['department_name'],
                        'teams' => []
                    ];
                }
            }
        }

        // Re-index users array (drop numeric keys)
        $usersList = array_values($users);

        return [
            'departments' => $departments,
            'teams' => $teams,
            'users' => $usersList,
            'tasks' => $tasks,
            'unassigned_users' => $unassigned_users
        ];
    }
}