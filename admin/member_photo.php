<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/member_uploads.php';
require_role('admin');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(404);
    exit;
}

$statement = db()->prepare('SELECT verification_photo FROM members WHERE id = ? AND is_deleted = 0 LIMIT 1');
$statement->execute([$id]);
$filename = $statement->fetchColumn();

if (!is_string($filename) || !preg_match('/\A[a-f0-9]{64}\.(?:jpg|png)\z/', $filename)) {
    http_response_code(404);
    exit;
}

$photo = member_verification_photo_file($filename);
if ($photo === null) {
    http_response_code(404);
    exit;
}

$size = filesize($photo['path']);
if ($size === false) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $photo['mime_type']);
header('Content-Length: ' . (string)$size);
header('Content-Disposition: inline; filename="member-verification-photo"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($photo['path']);
