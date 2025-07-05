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
        
        $query = sprintf(
            "SELECT t.*, p.name as project_name, u.realname as assigned_to_name,
            (SELECT COUNT(*) FROM {$this->table} WHERE parent_id = t.id) as subtask_count
            FROM {$this->table} t 
            LEFT JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id 
            LEFT JOIN " . DB_PREFIX . "user u ON t.assigned_to = u.id 
            %s
            ORDER BY t.position, t.created_at DESC",
            $where
        );
        
        $tasks = $this->fetchAll($query);
        
        if ($include_subtasks) {
            foreach ($tasks as &$task) {
                if ($task['subtask_count'] > 0) {
                    $task['subtasks'] = $this->getSubtasks($task['id']);
                }
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
            //TODO: Log assigned_to


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
            $this->logTaskAction($id, 'deleted', 'タスク削除', $old['status'], 'deleted');
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

    function getComments($taskId) {
        $query = sprintf(
            "SELECT c.*, u.realname as user_name 
            FROM " . DB_PREFIX . "task_comments c 
            LEFT JOIN " . DB_PREFIX . "user u ON c.user_id = u.id 
            WHERE c.task_id = %d 
            ORDER BY c.created_at DESC",
            intval($taskId)
        );
        return $this->fetchAll($query);
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

    function addComment($data) {
        $data = $_POST;
        $commentData = array(
            'project_id' => $data['project_id'],
            'user_id' => $data['user_id'],
            'content' => $data['content'],
            'created_at' => date('Y-m-d H:i:s')
        );
        
        $this->table = DB_PREFIX . 'comments';
        $result = $this->query_insert($commentData);
        $this->table = DB_PREFIX . 'tasks'; // Reset table back to tasks
        
        // Send mention notifications if comment was added successfully
        if ($result) {
            $this->sendMentionNotifications($data['project_id'], $data['content'], $data['user_id'], $result);
            
        }
        
        return $result;
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
        
        
        return $isAssigned || $isProjectManager;
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
        
        $result = $this->query_delete(['id' => $id]);
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
} 