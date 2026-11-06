<?php
declare(strict_types=1);

function store_member_verification_photo(array $upload): ?string
{
    $error = $upload['error'] ?? null;
    if (!is_int($error)) {
        throw new InvalidArgumentException('Choose a valid photo file.');
    }

    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        throw new InvalidArgumentException('The photo must be 5 MB or smaller.');
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('The photo could not be uploaded. Please try again.');
    }

    $temporaryPath = $upload['tmp_name'] ?? null;
    if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
        throw new InvalidArgumentException('Choose a valid photo file.');
    }

    $size = filesize($temporaryPath);
    if ($size === false || $size < 1 || $size > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('The photo must be 5 MB or smaller.');
    }

    $image = getimagesize($temporaryPath);
    if ($image === false) {
        throw new InvalidArgumentException('Upload a valid JPEG or PNG image.');
    }

    $mimeToExtension = [
        'image/jpeg' => ['extension' => 'jpg', 'type' => IMAGETYPE_JPEG],
        'image/png' => ['extension' => 'png', 'type' => IMAGETYPE_PNG],
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        throw new RuntimeException('The server could not validate the uploaded photo.');
    }

    $mimeType = finfo_file($finfo, $temporaryPath);
    finfo_close($finfo);

    $imageType = is_string($mimeType) ? ($mimeToExtension[$mimeType] ?? null) : null;
    $width = (int)($image[0] ?? 0);
    $height = (int)($image[1] ?? 0);
    if (
        $imageType === null
        || (int)($image[2] ?? 0) !== $imageType['type']
        || $width < 64
        || $height < 64
        || $width > 8000
        || $height > 8000
        || $width * $height > 20000000
    ) {
        throw new InvalidArgumentException('Upload a valid JPEG or PNG image no larger than 20 megapixels.');
    }

    $directory = dirname(__DIR__) . '/private/member-photos';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('The server could not prepare private photo storage.');
    }

    $filename = bin2hex(random_bytes(32)) . '.' . $imageType['extension'];
    if (!move_uploaded_file($temporaryPath, $directory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('The server could not save the uploaded photo.');
    }

    $path = $directory . DIRECTORY_SEPARATOR . $filename;
    if (!chmod($path, 0600)) {
        if (is_file($path) && !unlink($path)) {
            error_log('Could not remove a member verification photo after a storage permission failure.');
        }
        throw new RuntimeException('The server could not secure the uploaded photo.');
    }

    return $filename;
}

function delete_member_verification_photo(?string $filename): void
{
    if ($filename === null || !preg_match('/\A[a-f0-9]{64}\.(?:jpg|png)\z/', $filename)) {
        return;
    }

    $path = dirname(__DIR__) . '/private/member-photos/' . $filename;
    if (is_file($path) && !unlink($path)) {
        error_log('Could not remove an uncommitted member verification photo.');
    }
}

function member_verification_photo_file(?string $filename): ?array
{
    if ($filename === null || !preg_match('/\A[a-f0-9]{64}\.(?:jpg|png)\z/', $filename)) {
        return null;
    }

    $path = dirname(__DIR__) . '/private/member-photos/' . $filename;
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        throw new RuntimeException('The server could not identify the stored photo.');
    }

    $mimeType = finfo_file($finfo, $path);
    finfo_close($finfo);
    if (!in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
        return null;
    }

    return ['path' => $path, 'mime_type' => $mimeType];
}

function member_verification_photo_data_uri(?string $filename): ?string
{
    $photo = member_verification_photo_file($filename);
    if ($photo === null) {
        return null;
    }

    $contents = file_get_contents($photo['path']);
    if ($contents === false) {
        throw new RuntimeException('The server could not read the stored member photo.');
    }

    return 'data:' . $photo['mime_type'] . ';base64,' . base64_encode($contents);
}
