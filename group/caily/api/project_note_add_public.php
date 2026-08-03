<?php
/**
 * Public API: add a note to groupware_project_notes without login.
 * GET or POST:
 *   key (required), project_id (required),
 *   content (required), title (optional; auto from first line of content),
 *   is_important (optional, 0|1), needs_confirmation (optional, 0|1|2),
 *   display_column (optional), user_id (optional, default admin).
 */
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/loader.php';
$controller->requiring();

$params = array(
    'project_id'         => isset($_REQUEST['project_id']) ? $_REQUEST['project_id'] : '',
    'title'              => isset($_REQUEST['title']) ? $_REQUEST['title'] : '',
    'content'            => isset($_REQUEST['content']) ? $_REQUEST['content'] : '',
    'is_important'       => isset($_REQUEST['is_important']) ? $_REQUEST['is_important'] : '',
    'needs_confirmation' => isset($_REQUEST['needs_confirmation']) ? $_REQUEST['needs_confirmation'] : '',
    'display_column'     => isset($_REQUEST['display_column']) ? $_REQUEST['display_column'] : '',
    'user_id'            => isset($_REQUEST['user_id']) ? $_REQUEST['user_id'] : '',
    'key'                => isset($_REQUEST['key']) ? $_REQUEST['key'] : '',
);

if ($params['key'] != 'caily@123') {
    echo json_encode(array('status' => 'error', 'message_code' => 'invalid key', 'id' => null));
    exit;
}

require_once dirname(__DIR__) . '/application/model/project.php';

$project = new Project();
$project->connect();
$hash = $project->add_note_public($params);
$project->close();

echo json_encode($hash);
