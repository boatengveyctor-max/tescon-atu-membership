<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_middleware.php';

function require_member_profile(): array
{
    require_role('member');

    $statement = db()->prepare('SELECT * FROM members WHERE id = ? AND is_deleted = 0 LIMIT 1');
    $statement->execute([(int)$_SESSION['user_id']]);
    $member = $statement->fetch();

    if (!$member) {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . APP_BASE_URL . '/public/login.php', true, 303);
        exit;
    }

    return $member;
}
