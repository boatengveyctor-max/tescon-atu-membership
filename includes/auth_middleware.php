<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/security_headers.php';

require_once __DIR__ . '/csrf.php';

function e(?string $s): string 
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function portal_dashboard_url(?string $role): string
{
    return match ($role) {
        'admin'   => APP_BASE_URL . '/admin/dashboard.php',
        'auditor' => APP_BASE_URL . '/auditor/dashboard.php',
        'member'  => APP_BASE_URL . '/member/dashboard.php',
        default   => APP_BASE_URL . '/public/index.php'
    };
}

function redirect_if_authenticated(): void
{
    if (isset($_SESSION['last_activity']) && time() - (int)$_SESSION['last_activity'] > SESSION_IDLE_TIMEOUT) {
        $_SESSION = [];
        session_destroy();
        return;
    }

    $role = $_SESSION['role'] ?? null;
    if ($role !== null && in_array($role, ['admin', 'auditor', 'member'], true)) {
        header('Location: ' . portal_dashboard_url($role), true, 303);
        exit;
    }
}

function require_role(string $role): void 
{
    header('Cache-Control: private, no-store');

    if (isset($_SESSION['last_activity']) && time() - (int)$_SESSION['last_activity'] > SESSION_IDLE_TIMEOUT) {
        $_SESSION = [];
        session_destroy();
    }
    
    $_SESSION['last_activity'] = time();

    $currentRole = $_SESSION['role'] ?? null;

    if ($currentRole !== $role) {
        // If authenticated with a different role, redirect directly to their authorized portal
        if ($currentRole !== null && in_array($currentRole, ['admin', 'auditor', 'member'], true)) {
            header('Location: ' . portal_dashboard_url($currentRole), true, 303);
            exit;
        }

        // Unauthenticated access attempt: redirect to corresponding sign-in endpoint
        $loginUrl = match ($role) {
            'admin'   => APP_BASE_URL . '/admin/index.php',
            'auditor' => APP_BASE_URL . '/auditor/index.php',
            'member'  => APP_BASE_URL . '/public/login.php',
            default   => APP_BASE_URL . '/public/index.php'
        };
        header('Location: ' . $loginUrl, true, 303);
        exit;
    }

    // Live account status verification & instant session eviction:
    // If an admin/auditor user is deactivated in the database, evict the active session immediately.
    // If a member record has been soft-deleted, evict the session immediately.
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId > 0) {
        if ($currentRole === 'admin' || $currentRole === 'auditor') {
            $st = db()->prepare('SELECT status FROM users WHERE id = ? LIMIT 1');
            $st->execute([$userId]);
            $userStatus = $st->fetchColumn();
            if ($userStatus !== 'active') {
                $_SESSION = [];
                session_destroy();
                $evictUrl = match ($role) {
                    'admin'   => APP_BASE_URL . '/admin/index.php',
                    'auditor' => APP_BASE_URL . '/auditor/index.php',
                    default   => APP_BASE_URL . '/public/index.php'
                };
                header('Location: ' . $evictUrl, true, 303);
                exit;
            }
        } elseif ($currentRole === 'member') {
            $st = db()->prepare('SELECT is_deleted FROM members WHERE id = ? LIMIT 1');
            $st->execute([$userId]);
            $isDeleted = $st->fetchColumn();
            if ($isDeleted === false || (int)$isDeleted === 1) {
                $_SESSION = [];
                session_destroy();
                header('Location: ' . APP_BASE_URL . '/public/login.php', true, 303);
                exit;
            }
        }
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        require_once __DIR__ . '/csrf.php';
        csrf_validate();
    }
}

function verify_password_and_rehash(PDO $pdo, string $table, int $userId, string $hash, string $password): bool
{
    if (!in_array($table, ['users', 'members'], true)) {
        throw new InvalidArgumentException('Unsupported authentication table.');
    }

    if (!password_verify($password, $hash)) {
        return false;
    }

    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $updatedAt = $table === 'members' ? ', updated_at = UTC_TIMESTAMP()' : '';
        $statement = $pdo->prepare("UPDATE $table SET password_hash = ?$updatedAt WHERE id = ?");
        $statement->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
    }

    return true;
}

function input_string(array $source, string $key, int $maxBytes = 4096, bool $trim = true): string
{
    $value = $source[$key] ?? null;
    if (!is_string($value) || strlen($value) > $maxBytes) {
        return '';
    }

    return $trim ? trim($value) : $value;
}

function redirect(string $p): never 
{
    $scriptDirectory = dirname(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/')));
    $location = rtrim($scriptDirectory === '.' ? '' : $scriptDirectory, '/') . '/' . ltrim($p, '/');
    header('Location: ' . $location, true, 303);
    exit;
}

function set_flash(string $t, string $m): void 
{
    $_SESSION['flash'] = [
        'type' => $t,
        'message' => $m
    ];
}

function take_flash(): ?array 
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($f) ? $f : null;
}

function valid_member(array $v): array 
{
    // Support either 'department' (used on campus chapter forms like ATU) 
    // or 'institution' (database column name), mapping to 'institution'.
    if ((!isset($v['institution']) || !is_string($v['institution']) || trim($v['institution']) === '') && isset($v['department'])) {
        $v['institution'] = $v['department'];
    }

    $limits = [
        'full_name' => 100,
        'index_number' => 40,
        'phone_number' => 20,
        'email' => 254,
        'institution' => 150,
        'program_of_study' => 150,
        'level' => 40,
        'region' => 100,
        'constituency' => 100
    ];

    $fieldLabels = [
        'full_name' => 'full name',
        'index_number' => 'student ID',
        'phone_number' => 'phone number',
        'email' => 'email address',
        'institution' => 'department / institution',
        'program_of_study' => 'program of study',
        'level' => 'level / year',
        'region' => 'region',
        'constituency' => 'constituency'
    ];

    $o = [];

    foreach ($limits as $k => $max) {
        if (!is_string($v[$k] ?? null)) {
            $label = $fieldLabels[$k] ?? str_replace('_', ' ', $k);
            throw new InvalidArgumentException("Please provide a valid $label.");
        }

        $s = trim($v[$k]);
        if ($s === '' || mb_strlen($s) > $max) {
            $label = $fieldLabels[$k] ?? str_replace('_', ' ', $k);
            throw new InvalidArgumentException("Please provide your $label (up to $max characters).");
        }
        $o[$k] = $s;
    }

    if (!filter_var($o['email'], FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Enter a valid email address.');
    }

    if (!preg_match('/^(?:\+233|0)[235]\d{8}$/', $o['phone_number'])) {
        throw new InvalidArgumentException('Enter a valid Ghana phone number.');
    }

    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9\/-]{2,39}$/', $o['index_number'])) {
        throw new InvalidArgumentException('Enter a valid student ID.');
    }

    return $o;
}

/**
 * Returns true when the given IP has exceeded the maximum number of failed
 * login attempts within the rolling time window.
 *
 * @param string $ip          IP address to check (already sanitised to 45 chars).
 * @param int    $maxAttempts Maximum allowed failures before the limit kicks in.
 * @param int    $windowMins  Look-back window in minutes.
 */
function is_rate_limited(string $ip, int $maxAttempts = 10, int $windowMins = 15): bool
{
    $st = db()->prepare(
        "SELECT COUNT(*) FROM audit_logs
         WHERE action_type = 'LOGIN_FAILURE'
           AND ip_address = ?
           AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? MINUTE)"
    );
    $st->execute([$ip, $windowMins]);
    return (int)$st->fetchColumn() >= $maxAttempts;
}

/**
 * Returns true when the given account identifier (username, student ID, or email)
 * has exceeded the maximum number of failed sign-in attempts within the rolling time window.
 *
 * @param string $identifier  Username, index number, or email.
 * @param int    $maxAttempts Maximum allowed failures before locking the identifier.
 * @param int    $windowMins  Look-back window in minutes.
 */
function is_account_rate_limited(string $identifier, int $maxAttempts = 5, int $windowMins = 15): bool
{
    $identifier = trim($identifier);
    if ($identifier === '') {
        return false;
    }

    $st = db()->prepare(
        "SELECT COUNT(*) FROM audit_logs
         WHERE action_type = 'LOGIN_FAILURE'
           AND (
               JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.identifier')) = ?
               OR JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.username')) = ?
           )
           AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? MINUTE)"
    );
    $st->execute([$identifier, $identifier, $windowMins]);
    return (int)$st->fetchColumn() >= $maxAttempts;
}