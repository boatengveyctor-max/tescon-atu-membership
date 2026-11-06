<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function log_audit_event(
    PDO $pdo,
    ?int $actorId,
    string $actorType,
    string $action,
    string $table,
    ?int $targetId,
    ?array $oldValues,
    ?array $newValues
): void {
    $st = $pdo->prepare('INSERT INTO audit_logs(actor_id, actor_type, action_type, target_table, target_id, old_values, new_values, ip_address, user_agent, created_at) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())');
    
    $st->execute([
        $actorId,
        $actorType,
        $action,
        $table,
        $targetId,
        $oldValues === null ? null : json_encode($oldValues, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        $newValues === null ? null : json_encode($newValues, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500)
    ]);
}