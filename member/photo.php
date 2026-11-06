<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/member_helpers.php';
require_once __DIR__ . '/../includes/member_uploads.php';

$member = require_member_profile();
$photo = member_verification_photo_file($member['verification_photo'] ?? null);
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
header('Content-Disposition: inline; filename="member-profile-photo"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($photo['path']);
