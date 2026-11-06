<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string)SESSION_IDLE_TIMEOUT);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => APP_BASE_URL !== '' ? APP_BASE_URL : '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function start_user_session(array $user): void
{
    session_regenerate_id(true);
    $_SESSION = [
        'user_id' => (int)$user['id'],
        'username' => (string)($user['username'] ?? $user['full_name'] ?? ''),
        'role' => (string)$user['role'],
        'index_number' => (string)($user['index_number'] ?? ''),
        'last_activity' => time()
    ];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
