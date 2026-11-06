<?php
declare(strict_types=1);

function csrf_token(): string 
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return (string)$_SESSION['csrf_token'];
}

function csrf_field(): string 
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_validate(): void 
{
    $s = $_POST['csrf_token'] ?? '';
    
    if (!is_string($s) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $s)) {
        http_response_code(419);
        exit('Your form expired. Please reload and try again.');
    }
}