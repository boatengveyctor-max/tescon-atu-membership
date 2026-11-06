<?php
declare(strict_types=1);

$envFile = dirname(__DIR__) . '/.env';

if (is_file($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Unable to read the application environment file.');
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!preg_match('/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
            throw new RuntimeException('Invalid environment variable definition.');
        }

        $key = $matches[1];
        if (getenv($key) !== false) {
            continue;
        }

        $value = trim($matches[2]);
        if (strlen($value) >= 2 && (
            ($value[0] === '"' && $value[strlen($value) - 1] === '"')
            || ($value[0] === "'" && $value[strlen($value) - 1] === "'")
        )) {
            $value = substr($value, 1, -1);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}
