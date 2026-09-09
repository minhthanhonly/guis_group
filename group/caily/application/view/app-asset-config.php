<?php
/**
 * Resolve which vendor bundles to load per page (footer.php).
 */
if (!function_exists('app_resolve_asset_context')) {
    function app_resolve_asset_context($directory = '', $page = '') {
        if ($directory === '' || $directory === null) {
            $directory = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        }
        $directory = (string) $directory;
        $page = (string) $page;
        $isLogin = ($directory === 'login');
        // /project/ list: defer Quill / Sortable / jszip / chat / customer-modal until needed
        $isProjectList = ($directory === 'project' && ($page === 'index' || $page === ''));

        $tableDirs = [
            'project', 'parent_project', 'member', 'property', 'holiday',
            'company', 'price_list', 'specifications', 'folder', 'storage', 'todo',
            'form', 'addressbook', 'forum', 'schedule', 'administration',
            'timecard', 'shift',
        ];

        return [
            'directory' => $directory,
            'page' => $page,
            'is_login' => $isLogin,
            'is_project_list' => $isProjectList,
            'needs_data_tables' => !$isLogin && in_array($directory, $tableDirs, true),
            'needs_jszip' => !$isLogin && in_array($directory, $tableDirs, true) && !$isProjectList,
            'needs_flatpickr' => !$isLogin && in_array($directory, $tableDirs, true),
            'needs_quill' => !$isLogin && !$isProjectList,
            'needs_sortable' => in_array($directory, ['project', 'parent_project'], true) && !$isProjectList,
            'needs_chat' => !$isLogin && !$isProjectList,
            'needs_customer_modal' => !$isLogin && !empty($_SESSION['show_project']) && !$isProjectList,
            'needs_form_validation' => true,
            'needs_algolia' => !$isLogin,
            'needs_full_shell' => !$isLogin,
        ];
    }
}
