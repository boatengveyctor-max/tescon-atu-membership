<?php
declare(strict_types=1);

require_once __DIR__ . '/audit_logger.php';

function log_member_view_once(
    PDO $pdo,
    int $memberId,
    string $action,
    string $targetTable,
    ?int $targetId,
    string $sessionKey,
    ?array $metadata = null,
    int $intervalSeconds = 300
): void {
    $lastLogged = (int)($_SESSION[$sessionKey] ?? 0);
    if ($lastLogged > 0 && time() - $lastLogged <= $intervalSeconds) {
        return;
    }

    log_audit_event(
        $pdo,
        $memberId,
        'member',
        $action,
        $targetTable,
        $targetId,
        null,
        $metadata
    );
    $_SESSION[$sessionKey] = time();
}
