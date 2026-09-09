<?php
/**
 * Resolve which vendor bundles to load per page (footer.php / header.php).
 */
if (!function_exists('app_resolve_asset_context')) {
    function app_resolve_asset_context($directory = '', $page = '') {
        if ($directory === '' || $directory === null) {
            $directory = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        }
        $directory = (string) $directory;
        $page = (string) $page;
        $isLogin = ($directory === 'login');
        // /project/ list: defer Quill / Sortable / jszip / chat / customer-modal / flatpickr until needed
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
            // Flatpickr JS/CSS: project list loads on demand (filter / quick-edit / todo)
            'needs_flatpickr' => !$isLogin && in_array($directory, $tableDirs, true) && !$isProjectList,
            'needs_quill' => !$isLogin && !$isProjectList,
            'needs_sortable' => in_array($directory, ['project', 'parent_project'], true) && !$isProjectList,
            'needs_chat' => !$isLogin && !$isProjectList,
            'needs_chat_css' => !$isLogin && !$isProjectList,
            'needs_customer_modal' => !$isLogin && !empty($_SESSION['show_project']) && !$isProjectList,
            'needs_form_validation' => true,
            'needs_algolia' => !$isLogin,
            'needs_full_shell' => !$isLogin,
            // Head CSS: non-blocking on project list for first paint
            'defer_task_timer_css' => $isProjectList,
            'defer_flatpickr_css' => $isProjectList,
        ];
    }
}

if (!function_exists('app_print_stylesheet')) {
    /**
     * @param string $href Absolute or root-relative stylesheet URL
     * @param array $options skip|blocking (default blocking true)
     */
    function app_print_stylesheet($href, $options = []) {
        if (!empty($options['skip'])) {
            return;
        }
        $blocking = !array_key_exists('blocking', $options) || !empty($options['blocking']);
        $safe = htmlspecialchars((string) $href, ENT_QUOTES, 'UTF-8');
        if ($blocking) {
            echo '<link rel="stylesheet" href="' . $safe . '" />' . "\n";
            return;
        }
        echo '<link rel="stylesheet" href="' . $safe . '" media="print" onload="this.media=\'all\'" />' . "\n";
        echo '<noscript><link rel="stylesheet" href="' . $safe . '" /></noscript>' . "\n";
    }
}
