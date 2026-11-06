<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/audit_logger.php';
require_once __DIR__ . '/../includes/member_uploads.php';

// Prevent authenticated users (admins, auditors, members) from accessing registration
redirect_if_authenticated();

$error = '';
$verificationPhoto = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    try {
        $m = valid_member($_POST);

        $password = input_string($_POST, 'password', 72, false);
        $confirmPassword = input_string($_POST, 'confirm_password', 72, false);

        if (strlen($password) < 8 || strlen($password) > 72) {
            throw new InvalidArgumentException('Password must be between 8 and 72 characters long.');
        }

        if ($password !== $confirmPassword) {
            throw new InvalidArgumentException('Passwords do not match.');
        }

        $verificationPhoto = store_member_verification_photo($_FILES['verification_photo'] ?? []);

        $pdo = db();
        $pdo->beginTransaction();

        $q = $pdo->prepare('SELECT id, is_deleted FROM members WHERE (index_number = ? OR email = ?) LIMIT 1 FOR UPDATE');
        $q->execute([$m['index_number'], $m['email']]);
        $existing = $q->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if ((int)$existing['is_deleted'] === 1) {
                throw new DomainException('This student ID or email belongs to an archived account. Please contact the TESCON secretariat for assistance.');
            }
            throw new DomainException('That student ID or email is already registered. Please sign in to check your status.');
        }

        $photoColumn = $pdo->query("SHOW COLUMNS FROM members LIKE 'verification_photo'")->fetch();
        if ($verificationPhoto !== null && !$photoColumn) {
            throw new InvalidArgumentException('Photo uploads are not available yet. Please try again later.');
        }

        $m['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        if ($photoColumn) {
            $m['verification_photo'] = $verificationPhoto;
        }

        $cols = implode(',', array_keys($m));
        $marks = implode(',', array_fill(0, count($m), '?'));

        $st = $pdo->prepare("INSERT INTO members($cols, status, created_at, updated_at) VALUES($marks, 'pending', UTC_TIMESTAMP(), UTC_TIMESTAMP())");
        $st->execute(array_values($m));

        $id = (int)$pdo->lastInsertId();

        // Audit log without plaintext credentials or password hashes
        $auditData = $m;
        unset($auditData['password_hash']);
        $auditData['verification_photo'] = $verificationPhoto !== null;
        $auditData['status'] = 'pending';

        log_audit_event($pdo, null, 'public', 'REGISTER', 'members', $id, null, $auditData);

        $pdo->commit();
        $verificationPhoto = null;
        
        set_flash('success', 'Your application was received and is pending review. You can now sign in to your portal.');
        header('Location: login.php', true, 303);
        exit;
    } catch (DomainException | InvalidArgumentException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        delete_member_verification_photo($verificationPhoto);
        $error = $e->getMessage();
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        delete_member_verification_photo($verificationPhoto);
        if ((isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) || $e->getCode() === '23000') {
            $error = 'That student ID or email is already registered.';
        } else {
            error_log('Registration database error: ' . $e->getMessage());
            $error = 'We could not submit your application. Please try again later.';
        }
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        delete_member_verification_photo($verificationPhoto);
        error_log('Registration failed: ' . $e->getMessage());
        $error = 'We could not submit your application. Please try again later.';
    }
}

$title = 'Membership application';
$fields = [
    'full_name'        => 'Full name',
    'index_number'     => 'Index / Student ID',
    'phone_number'     => 'Phone number',
    'email'            => 'Email address',
    'department'       => 'Department',
    'program_of_study' => 'Program of study',
    'level'            => 'Level / Year',
    'region'           => 'Region',
    'constituency'     => 'Constituency'
];

$autocomplete = [
    'full_name'        => 'name',
    'index_number'     => 'off',
    'phone_number'     => 'tel',
    'email'            => 'email',
    'department'       => 'department',
    'program_of_study' => 'off',
    'level'            => 'off',
    'region'           => 'address-level1',
    'constituency'     => 'off',
];

$maxLengths = [
    'full_name'        => 100,
    'index_number'     => 40,
    'phone_number'     => 20,
    'email'            => 254,
    'department'       => 150,
    'program_of_study' => 150,
    'level'            => 40,
    'region'           => 100,
    'constituency'     => 100
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · TESCON ATU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= e(APP_BASE_URL) ?>/assets/css/style.css?v=aurora-light-1">
</head>
<body class="bg-slate-50 text-slate-900">
    <header class="bg-white border-b shadow-sm">
        <div class="max-w-5xl mx-auto px-5 py-4">
            <a class="font-black text-blue-900 tracking-wide text-lg" href="index.php">
                TESCON <span class="text-red-700">ATU</span>
            </a>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-5 py-10">
        <div class="max-w-3xl mx-auto bg-white rounded-2xl border border-slate-200 p-6 md:p-9 shadow-sm">
            <div class="border-b border-slate-100 pb-5">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-800">
                    <span class="h-1.5 w-1.5 rounded-full bg-blue-600"></span>
                    Student Membership
                </span>
                <h1 class="text-3xl font-bold mt-2">Membership application</h1>
                <p class="text-slate-600 mt-1">Complete all fields below to create your account and submit your membership application.</p>
            </div>

            <?php if ($error): ?>
                <div role="alert" class="mt-6 rounded-lg bg-red-100 text-red-900 p-4 border border-red-200 text-sm font-medium">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="grid md:grid-cols-2 gap-5 mt-7" data-validate>
                <?= csrf_field() ?>

                <?php foreach ($fields as $key => $label): ?>
                    <label class="block text-sm font-medium text-slate-700">
                        <?= e($label) ?>
                        <input class="mt-1 w-full rounded-lg border border-slate-300 p-3 font-normal text-base" 
                               name="<?= e($key) ?>" 
                               required 
                               maxlength="<?= (int)($maxLengths[$key] ?? 150) ?>" 
                               type="<?= $key === 'email' ? 'email' : 'text' ?>" 
                               autocomplete="<?= e($autocomplete[$key] ?? 'off') ?>"
                               value="<?= e(input_string($_POST, $key, (int)($maxLengths[$key] ?? 150)) ?: ($key === 'department' ? input_string($_POST, 'institution', 150) : '')) ?>">
                    </label>
                <?php endforeach; ?>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700" for="verification_photo">
                        Student ID / verification photo <span class="font-normal text-slate-500">(optional)</span>
                    </label>
                    <input
                        class="mt-1 w-full rounded-lg border border-slate-300 p-3 font-normal text-base"
                        id="verification_photo"
                        name="verification_photo"
                        type="file"
                        accept="image/jpeg,image/png,.jpg,.jpeg,.png"
                        aria-describedby="verification-photo-help"
                    >
                    <p class="mt-1 text-sm text-slate-500" id="verification-photo-help">
                        Attach a clear photo of your student ID for verification. JPEG or PNG, up to 5 MB.
                    </p>
                    <img
                        id="verification-photo-preview"
                        class="mt-3 hidden max-h-64 max-w-full rounded-lg border border-slate-200 object-contain"
                        alt="Preview of the selected verification photo"
                    >
                </div>

                <div class="md:col-span-2 pt-2 border-t border-slate-100 mt-2">
                    <h2 class="text-base font-bold text-slate-900">Account security</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Create a password to sign in to your membership portal and check your review status.</p>
                </div>

                <label class="block text-sm font-medium text-slate-700">
                    Password <span class="text-xs font-normal text-slate-500">(8–72 characters)</span>
                    <input class="mt-1 w-full rounded-lg border border-slate-300 p-3 font-normal text-base" 
                           name="password" 
                           required 
                           minlength="8" 
                           maxlength="72" 
                           type="password" 
                           autocomplete="new-password">
                </label>

                <label class="block text-sm font-medium text-slate-700">
                    Confirm password
                    <input class="mt-1 w-full rounded-lg border border-slate-300 p-3 font-normal text-base" 
                           name="confirm_password" 
                           required 
                           minlength="8" 
                           maxlength="72" 
                           type="password" 
                           autocomplete="new-password">
                </label>

                <div class="md:col-span-2 flex justify-end pt-5 border-t border-slate-100">
                    <button type="submit" class="rounded-xl bg-blue-900 text-white px-7 py-3.5 font-semibold hover:bg-blue-800 transition shadow-md">
                        Submit application
                    </button>
                </div>
            </form>
        </div>
    </main>

    <footer class="border-t bg-white mt-16">
        <div class="max-w-5xl mx-auto p-6 text-sm text-slate-500">
            &copy; <?= gmdate('Y') ?> TESCON ATU Membership
        </div>
    </footer>

    <script src="<?= e(APP_BASE_URL) ?>/assets/js/registration-photo.js" defer></script>
    <script src="<?= e(APP_BASE_URL) ?>/assets/js/validation.js" defer></script>
</body>
</html>
