<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_middleware.php';

if (($_SESSION['role'] ?? '') === 'member') {
    header('Location: dashboard.php', true, 303);
    exit;
}

redirect_if_authenticated();

header('Location: ' . APP_BASE_URL . '/public/login.php', true, 303);
exit;

