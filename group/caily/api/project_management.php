<?php
require_once('../application/loader.php');
require_once('../application/model/project.php');
require_once('../application/model/user.php');

class ProjectManagementAPI {
    private $project;
    private $task;
    private $category;
    private $tag;
    private $user;

    function __construct() {
        $this->project = new Project();
        $this->task = new Task();
        $this->category = new Category();
        $this->tag = new Tag();
        $this->user = new User();
    }

    // Category endpoints
    function getCategories() {
        return $this->category->list();
    }

    function addCategory($data) {
        return $this->category->add($data);
    }

    function editCategory($id, $data) {
        return $this->category->edit($id, $data);
    }

    function deleteCategory($id) {
        return $this->category->delete($id);
    }

    function getCategory($id) {
        return $this->category->get($id);
    }

    // Tag endpoints
    function getTags() {
        return $this->tag->list();
    }

    function addTag($data) {
        return $this->tag->add($data);
    }

    function editTag($id, $data) {
        return $this->tag->edit($id, $data);
    }

    function deleteTag($id) {
        return $this->tag->delete($id);
    }

    function getTag($id) {
        return $this->tag->get($id);
    }

    // Project endpoints
    function getProjects() {
        return $this->project->list();
    }

    function addProject($data) {
        return $this->project->add($data);
    }

    function editProject($id, $data) {
        return $this->project->edit($id, $data);
    }

    function deleteProject($id) {
        return $this->project->delete($id);
    }

    function getProject($id) {
        return $this->project->get($id);
    }

    function getProjectMembers($project_id) {
        return $this->project->getMembers($project_id);
    }

    function addProjectMember($project_id, $user_id, $role = 'member') {
        return $this->project->addMember($project_id, $user_id, $role);
    }

    function removeProjectMember($project_id, $user_id) {
        return $this->project->removeMember($project_id, $user_id);
    }

    function getProjectTasks($project_id) {
        return $this->project->getTasks($project_id);
    }

    // Task endpoints
    function addTask($data) {
        return $this->task->add($data);
    }

    function editTask($id, $data) {
        return $this->task->edit($id, $data);
    }

    function deleteTask($id) {
        return $this->task->delete($id);
    }

    function getTask($id) {
        return $this->task->get($id);
    }

    function getSubTasks($task_id) {
        return $this->task->getSubTasks($task_id);
    }

    // Time tracking endpoints
    function getTaskTimeEntries($task_id) {
        return $this->task->getTimeEntries($task_id);
    }

    function addTimeEntry($data) {
        return $this->task->addTimeEntry($data);
    }

    function updateTimeEntry($id, $data) {
        return $this->task->updateTimeEntry($id, $data);
    }

    // Comment endpoints
    function getTaskComments($task_id) {
        return $this->task->getComments($task_id);
    }

    function addComment($data) {
        return $this->task->addComment($data);
    }

    function getEvents() {
        $events = array();
        
        // Check for project updates
        $query = sprintf(
            "SELECT id, updated_at 
            FROM " . DB_PREFIX . "projects 
            WHERE updated_at > DATE_SUB(NOW(), INTERVAL 1 SECOND)"
        );
        $projectUpdates = $this->project->fetchAll($query);
        foreach ($projectUpdates as $update) {
            $events[] = array(
                'type' => 'project_updated',
                'project_id' => $update['id']
            );
        }
        
        // Check for task updates
        $query = sprintf(
            "SELECT id, project_id, updated_at 
            FROM " . DB_PREFIX . "tasks 
            WHERE updated_at > DATE_SUB(NOW(), INTERVAL 1 SECOND)"
        );
        $taskUpdates = $this->task->fetchAll($query);
        foreach ($taskUpdates as $update) {
            $events[] = array(
                'type' => 'task_updated',
                'task_id' => $update['id'],
                'project_id' => $update['project_id']
            );
        }
        
        // Check for new comments
        $query = sprintf(
            "SELECT c.id, c.task_id, t.project_id, c.created_at 
            FROM " . DB_PREFIX . "comments c
            INNER JOIN " . DB_PREFIX . "tasks t ON c.task_id = t.id
            WHERE c.created_at > DATE_SUB(NOW(), INTERVAL 1 SECOND)"
        );
        $newComments = $this->task->fetchAll($query);
        foreach ($newComments as $comment) {
            $events[] = array(
                'type' => 'comment_added',
                'task_id' => $comment['task_id'],
                'project_id' => $comment['project_id']
            );
        }
        
        // Check for time entry updates
        $query = sprintf(
            "SELECT te.id, te.task_id, t.project_id, te.updated_at 
            FROM " . DB_PREFIX . "time_entries te
            INNER JOIN " . DB_PREFIX . "tasks t ON te.task_id = t.id
            WHERE te.updated_at > DATE_SUB(NOW(), INTERVAL 1 SECOND)"
        );
        $timeUpdates = $this->task->fetchAll($query);
        foreach ($timeUpdates as $update) {
            $events[] = array(
                'type' => 'time_entry_updated',
                'task_id' => $update['task_id'],
                'project_id' => $update['project_id']
            );
        }
        
        return $events;
    }
}

// Handle API requests
$api = new ProjectManagementAPI();
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$data = json_decode(file_get_contents('php://input'), true);

header('Content-Type: application/json');

try {
    switch ($action) {
        // Category endpoints
        case 'get_categories':
            echo json_encode($api->getCategories());
            break;
        case 'add_category':
            echo json_encode($api->addCategory($data));
            break;
        case 'edit_category':
            echo json_encode($api->editCategory($id, $data));
            break;
        case 'delete_category':
            echo json_encode($api->deleteCategory($id));
            break;
        case 'get_category':
            echo json_encode($api->getCategory($id));
            break;

        // Tag endpoints
        case 'get_tags':
            echo json_encode($api->getTags());
            break;
        case 'add_tag':
            echo json_encode($api->addTag($data));
            break;
        case 'edit_tag':
            echo json_encode($api->editTag($id, $data));
            break;
        case 'delete_tag':
            echo json_encode($api->deleteTag($id));
            break;
        case 'get_tag':
            echo json_encode($api->getTag($id));
            break;

        // Project endpoints
        case 'get_projects':
            echo json_encode($api->getProjects());
            break;
        case 'add_project':
            echo json_encode($api->addProject($data));
            break;
        case 'edit_project':
            echo json_encode($api->editProject($id, $data));
            break;
        case 'delete_project':
            echo json_encode($api->deleteProject($id));
            break;
        case 'get_project':
            echo json_encode($api->getProject($id));
            break;
        case 'get_project_members':
            echo json_encode($api->getProjectMembers($id));
            break;
        case 'add_project_member':
            echo json_encode($api->addProjectMember($id, $data['user_id'], $data['role']));
            break;
        case 'remove_project_member':
            echo json_encode($api->removeProjectMember($id, $data['user_id']));
            break;
        case 'get_project_tasks':
            echo json_encode($api->getProjectTasks($id));
            break;

        // Task endpoints
        case 'add_task':
            echo json_encode($api->addTask($data));
            break;
        case 'edit_task':
            echo json_encode($api->editTask($id, $data));
            break;
        case 'delete_task':
            echo json_encode($api->deleteTask($id));
            break;
        case 'get_task':
            echo json_encode($api->getTask($id));
            break;
        case 'get_subtasks':
            echo json_encode($api->getSubTasks($id));
            break;

        // Time tracking endpoints
        case 'get_task_time_entries':
            echo json_encode($api->getTaskTimeEntries($id));
            break;
        case 'add_time_entry':
            echo json_encode($api->addTimeEntry($data));
            break;
        case 'update_time_entry':
            echo json_encode($api->updateTimeEntry($id, $data));
            break;

        // Comment endpoints
        case 'get_task_comments':
            echo json_encode($api->getTaskComments($id));
            break;
        case 'add_comment':
            echo json_encode($api->addComment($data));
            break;

        case 'events':
            // Set headers for SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Disable nginx buffering

            // Keep the connection alive
            while (true) {
                // Check for new events
                $events = $api->getEvents();
                if (!empty($events)) {
                    foreach ($events as $event) {
                        echo "data: " . json_encode($event) . "\n\n";
                        ob_flush();
                        flush();
                    }
                }
                
                // Sleep for a short time to prevent high CPU usage
                sleep(1);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 