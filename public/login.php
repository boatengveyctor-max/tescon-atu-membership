<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/audit_logger.php';

// If user is already authenticated with any role, redirect directly to their portal
redirect_if_authenticated();

$error = '';
$flash = take_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $identifier = input_string($_POST, 'identifier', 254);
    $password = input_string($_POST, 'password', 72, false);
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

    try {
        if ($identifier === '' || strlen($identifier) > 254 || $password === '' || strlen($password) > 72) {
            $error = 'Please enter a valid student ID / email and password.';
        } elseif (is_rate_limited($ip) || is_account_rate_limited($identifier)) {
            $error = 'Too many failed sign-in attempts. Please wait 15 minutes and try again.';
        } else {
            $pdo = db();
            $st = $pdo->prepare(
                "SELECT id, full_name, index_number, email, status, password_hash, is_deleted 
                 FROM members 
                 WHERE (index_number = ? OR email = ?) AND is_deleted = 0 
                 LIMIT 1"
            );
            $st->execute([$identifier, $identifier]);
            $member = $st->fetch();

            if ($member && !empty($member['password_hash']) && verify_password_and_rehash(
                $pdo,
                'members',
                (int)$member['id'],
                (string)$member['password_hash'],
                $password
            )) {

                log_audit_event(
                    $pdo, 
                    (int)$member['id'], 
                    'member', 
                    'LOGIN_SUCCESS', 
                    'members', 
                    (int)$member['id'], 
                    null, 
                    ['index_number' => $member['index_number'], 'status' => $member['status']]
                );

                start_user_session([
                    'id'           => (int)$member['id'],
                    'username'     => (string)$member['full_name'],
                    'full_name'    => (string)$member['full_name'],
                    'role'         => 'member',
                    'index_number' => (string)$member['index_number']
                ]);

                header('Location: ' . APP_BASE_URL . '/member/dashboard.php', true, 303);
                exit;
            }

            log_audit_event(
                $pdo, 
                $member ? (int)$member['id'] : null, 
                'member', 
                'LOGIN_FAILURE', 
                'members', 
                $member ? (int)$member['id'] : null, 
                null, 
                ['identifier' => substr($identifier, 0, 100)]
            );

            usleep(250000);
            $error = 'Invalid student ID / email or password.';
        }
    } catch (Throwable $e) {
        error_log('Member sign in failure: ' . $e->getMessage());
        $error = 'Sign in is temporarily unavailable. Please try again later.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Member Sign in · TESCON ATU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= e(APP_BASE_URL) ?>/assets/css/style.css?v=aurora-light-1">
</head>
<body class="min-h-screen bg-slate-100 flex flex-col justify-between p-4 sm:p-6">
    <div class="w-full max-w-md mx-auto my-auto">
        <div class="text-center mb-6">
            <a href="index.php" class="inline-block font-black text-blue-900 tracking-wider text-xl">
                TESCON <span class="text-red-700">ATU</span>
            </a>
            <p class="text-xs uppercase tracking-widest text-slate-500 font-bold mt-1">Student Member Portal</p>
        </div>

        <?php if ($flash): ?>
            <div role="status" class="mb-4 rounded-xl p-4 shadow-sm text-sm font-medium <?= $flash['type'] === 'success' ? 'bg-emerald-100 text-emerald-900 border border-emerald-200' : 'bg-red-100 text-red-900 border border-red-200' ?>">
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div role="alert" class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-800 p-4 text-sm font-medium shadow-sm">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <main class="bg-white border border-slate-200 rounded-2xl p-7 sm:p-8 shadow-sm">
            <h1 class="text-2xl font-bold text-slate-900">Member sign in</h1>
            <p class="text-sm text-slate-600 mt-1">Enter your student ID or email and password to access your dashboard.</p>

            <form method="post" class="space-y-4 mt-6">
                <?= csrf_field() ?>

                <label class="block text-sm font-medium text-slate-700">
                    Student ID or Email address
                    <input name="identifier" 
                           autocomplete="username" 
                           maxlength="254"
                           required 
                           class="mt-1 w-full border border-slate-300 rounded-lg p-3 font-normal text-base focus:border-blue-900 focus:outline-none focus:ring-1 focus:ring-blue-900" 
                           placeholder="e.g. 1010101 or name@example.com" 
                           value="<?= e(input_string($_POST, 'identifier', 254)) ?>">
                </label>

                <label class="block text-sm font-medium text-slate-700">
                    Password
                    <input type="password" 
                           name="password" 
                           autocomplete="current-password" 
                           maxlength="72"
                           required 
                           class="mt-1 w-full border border-slate-300 rounded-lg p-3 font-normal text-base focus:border-blue-900 focus:outline-none focus:ring-1 focus:ring-blue-900" 
                           placeholder="••••••••">
                </label>

                <button type="submit" class="w-full bg-blue-900 text-white rounded-xl p-3.5 font-semibold hover:bg-blue-800 transition shadow-md mt-2">
                    Sign in to portal
                </button>
            </form>

            <div class="mt-6 pt-5 border-t border-slate-100 text-center text-sm">
                <p class="text-slate-600">
                    Don't have a membership account yet?
                </p>
                <a href="register.php" class="inline-block mt-1 font-semibold text-blue-900 hover:text-blue-700 hover:underline">
                    Apply for TESCON Membership &rarr;
                </a>
            </div>
        </main>

        <p class="text-center mt-6 text-sm text-slate-500">
            <a href="index.php" class="hover:text-slate-800 transition inline-flex items-center gap-1">
                &larr; Back to homepage
            </a>
        </p>
    </div>

    <footer class="text-center py-4 text-xs text-slate-400">
        &copy; <?= gmdate('Y') ?> TESCON ATU Membership System
    </footer>
</body>
</html>
