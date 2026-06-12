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

        $tableDirs = [
            'project', 'parent_project', 'member', 'customer', 'holiday',
            'company', 'price_list', 'specifications', 'folder', 'storage', 'todo',
            'form', 'addressbook', 'forum', 'schedule', 'administration',
            'timecard', 'shift',
        ];

        return [
            'directory' => $directory,
            'page' => $page,
            'is_login' => $isLogin,
            'needs_data_tables' => !$isLogin && in_array($directory, $tableDirs, true),
            'needs_flatpickr' => !$isLogin && in_array($directory, $tableDirs, true),
            'needs_quill' => !$isLogin,
            'needs_sortable' => in_array($directory, ['project', 'parent_project'], true),
            'needs_form_validation' => true,
            'needs_algolia' => !$isLogin,
            'needs_full_shell' => !$isLogin,
        ];
    }
}
