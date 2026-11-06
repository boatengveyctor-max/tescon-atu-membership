<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_middleware.php';

$pageTitle = $pageTitle ?? APP_NAME;
$flash = take_flash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · TESCON</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= e(APP_BASE_URL) ?>/assets/css/style.css?v=aurora-light-1">
    <meta name="theme-color" content="#f5f8ff">
</head>
<body class="bg-slate-50 text-slate-900">
    <header class="bg-white border-b shadow-sm">
        <div class="max-w-7xl mx-auto px-5 py-4 flex justify-between items-center">
            <a class="font-black text-blue-900 tracking-wide text-lg" href="<?= e(APP_BASE_URL) ?>/public/index.php">
                TESCON <span class="text-red-700"> ATU </span>
            </a>
            
            <?php if (isset($_SESSION['role'])): ?>
                <span class="text-sm font-medium text-slate-600 bg-slate-100 px-3 py-1 rounded-full">
                    <?= e($_SESSION['username'] ?? '') ?> · <?= e(ucfirst($_SESSION['role'])) ?>
                </span>
            <?php endif; ?>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-5 py-8">
        <?php if ($flash): ?>
            <div role="status" class="mb-6 rounded-lg p-4 shadow-sm <?= $flash['type'] === 'success' ? 'bg-emerald-100 text-emerald-900 border border-emerald-200' : 'bg-red-100 text-red-900 border border-red-200' ?>">
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>