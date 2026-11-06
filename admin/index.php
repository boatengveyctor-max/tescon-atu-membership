<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/audit_logger.php';

redirect_if_authenticated();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $username = input_string($_POST, 'username', 80);
    $password = input_string($_POST, 'password', 72, false);
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

    try {
        if ($username === '' || strlen($username) > 80 || $password === '' || strlen($password) > 72) {
            $error = 'Please enter a valid username and password.';
        } elseif (is_rate_limited($ip) || is_account_rate_limited($username)) {
            $error = 'Too many failed sign-in attempts. Please wait 15 minutes and try again.';
        } else {
            $st = db()->prepare("SELECT id, username, role, password_hash, status FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
            $st->execute([$username]);
            $u = $st->fetch();

            if ($u && $u['status'] === 'active' && verify_password_and_rehash(
                db(),
                'users',
                (int)$u['id'],
                (string)$u['password_hash'],
                $password
            )) {

                log_audit_event(db(), (int)$u['id'], 'admin', 'LOGIN_SUCCESS', 'users', (int)$u['id'], null, ['username' => $u['username']]);
                start_user_session($u);
                header('Location: dashboard.php', true, 303);
                exit;
            }

            log_audit_event(db(), $u ? (int)$u['id'] : null, 'admin', 'LOGIN_FAILURE', 'users', $u ? (int)$u['id'] : null, null, ['username' => substr($username, 0, 100)]);
            usleep(250000);
            $error = 'Invalid username or password.';
        }
    } catch (Throwable $e) {
        error_log('Admin login failed: ' . $e->getMessage());
        $error = 'Login is temporarily unavailable.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin sign in · TESCON</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= e(APP_BASE_URL) ?>/assets/css/style.css?v=aurora-light-1">
</head>
<body class="min-h-screen bg-slate-100 grid place-items-center p-5">
    <main class="w-full max-w-md bg-white border rounded-2xl p-8 shadow-sm">
        <p class="font-black text-blue-900 tracking-wider text-sm">TESCON ADMINISTRATION</p>
        <h1 class="text-2xl font-bold mt-2">Sign in</h1>

        <?php if ($error): ?>
            <p class="mt-4 bg-red-50 text-red-800 p-3 rounded text-sm" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post" class="space-y-4 mt-6">
            <?= csrf_field() ?>
            <label class="block font-medium text-sm text-slate-700">
                Username
                <input name="username" autocomplete="username" maxlength="80" required class="mt-1 w-full border rounded p-3 font-normal text-base">
            </label>
            <label class="block font-medium text-sm text-slate-700">
                Password
                <input type="password" name="password" autocomplete="current-password" maxlength="72" required class="mt-1 w-full border rounded p-3 font-normal text-base">
            </label>
            <button type="submit" class="w-full bg-blue-900 text-white rounded p-3 font-semibold hover:bg-blue-800 transition">Sign in</button>
        </form>
    </main>
</body>
</html>