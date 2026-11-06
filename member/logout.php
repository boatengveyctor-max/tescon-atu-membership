<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/audit_logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

csrf_validate();

if (($_SESSION['role'] ?? '') === 'member') {
    try {
        log_audit_event(
            db(), 
            (int)$_SESSION['user_id'], 
            'member', 
            'LOGOUT', 
            'members', 
            (int)$_SESSION['user_id'], 
            null, 
            null
        );
    } catch (Throwable $e) {
        error_log('Member logout audit failure: ' . $e->getMessage());
    }
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
}

session_destroy();

// Restart clean session solely to store the logout flash message
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
set_flash('success', 'You have been signed out successfully.');

header('Location: ' . APP_BASE_URL . '/public/login.php', true, 303);
exit;
