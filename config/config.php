<?php
// Detectar protocolo e host
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $protocol . '://' . $host);
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');

// Configurações de sessão segura
define('SESSION_LIFETIME', 7200); // 2 horas
define('SESSION_NAME', 'SC_SESSION');

// Upload limits
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('ALLOWED_IMAGE_EXTS', ['jpg', 'jpeg', 'png', 'webp', 'gif']);

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Error reporting (desabilitar em produção)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT_PATH . '/logs/error.log');

// Configurar sessão segura
function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'domain'   => $cookieParams['domain'],
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_name(SESSION_NAME);
        session_start();
        // Regenerar ID a cada 30 minutos
        if (!isset($_SESSION['last_regen']) || time() - $_SESSION['last_regen'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['last_regen'] = time();
        }
    }
}
