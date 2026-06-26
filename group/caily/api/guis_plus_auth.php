<?php
require_once dirname(__DIR__) . '/env_config.php';

class GuisPlusApiAuth {
    public static function assertSecretOrFail() {
        $expected = trim((string) EnvConfig::get('GUIS_PLUS_SECRET', ''));
        if ($expected === '') {
            http_response_code(503);
            return ['success' => false, 'error' => 'App secret not configured'];
        }

        $provided = trim((string) (
            $_POST['app_secret'] ?? $_GET['app_secret'] ??
            ($_SERVER['HTTP_X_GUIS_APP_SECRET'] ?? '')
        ));
        if ($provided === '' || !hash_equals($expected, $provided)) {
            http_response_code(403);
            return ['success' => false, 'error' => 'Invalid app secret'];
        }

        return null;
    }
}
?>
