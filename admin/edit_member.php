<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_middleware.php';
require_role('admin');
require_once __DIR__ . '/../includes/audit_logger.php';

$pdo = db();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(404);
    exit('Member not found.');
}

$keys = [
    'full_name', 'index_number', 'phone_number', 'email', 
    'institution', 'program_of_study', 'level', 'region', 'constituency'
];

$labels = [
    'full_name' => 'Full name',
    'index_number' => 'Student ID',
    'phone_number' => 'Phone number',
    'email' => 'Email',
    'institution' => 'Department / Institution',
    'program_of_study' => 'Program',
    'level' => 'Level / year',
    'region' => 'Region',
    'constituency' => 'Constituency'
];

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $m = valid_member($_POST);
        $pdo->beginTransaction();

        $q = $pdo->prepare('SELECT * FROM members WHERE id = ? AND is_deleted = 0 FOR UPDATE');
        $q->execute([$id]);
        $old = $q->fetch();

        if (!$old) {
            throw new RuntimeException('Member record not found.');
        }

        $dup = $pdo->prepare('SELECT id FROM members WHERE (index_number = ? OR email = ?) AND id <> ? LIMIT 1');
        $dup->execute([$m['index_number'], $m['email'], $id]);

        if ($dup->fetch()) {
            throw new InvalidArgumentException('Another member already uses that student ID or email.');
        }

        $set = implode(',', array_map(fn($k) => "$k = ?", $keys));
        $pdo->prepare("UPDATE members SET $set, updated_at = UTC_TIMESTAMP() WHERE id = ?")
            ->execute([...array_values($m), $id]);

        $oldValues = [];
        $newValues = [];
        foreach ($keys as $k) {
            if ((string)$old[$k] !== $m[$k]) {
                $oldValues[$k] = $old[$k];
                $newValues[$k] = $m[$k];
            }
        }

        log_audit_event($pdo, (int)$_SESSION['user_id'], 'admin', 'UPDATE', 'members', (int)$id, $oldValues, $newValues);
        
        $pdo->commit();
        set_flash('success', 'Member details updated.');
        redirect('members.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $err = $e instanceof InvalidArgumentException || $e instanceof RuntimeException 
            ? $e->getMessage() 
            : 'Could not save changes.';
    }
}

$q = $pdo->prepare('SELECT * FROM members WHERE id = ? AND is_deleted = 0');
$q->execute([$id]);
$member = $q->fetch();

if (!$member) {
    http_response_code(404);
    exit('Member not found.');
}

$pageTitle = 'Edit member';
require __DIR__ . '/../includes/header.php';
?>

<a class="text-blue-800 underline" href="members.php">← Members</a>
<h1 class="text-3xl font-bold mt-4">Edit member</h1>

<?php if ($err): ?>
    <p role="alert" class="bg-red-50 text-red-800 p-3 rounded mt-4"><?= e($err) ?></p>
<?php endif; ?>

<form method="post" class="grid md:grid-cols-2 gap-5 mt-6 bg-white border rounded-xl p-6 shadow-sm">
    <?= csrf_field() ?>
    
    <?php foreach ($keys as $k): ?>
        <?php
        $formValue = $_POST[$k] ?? $member[$k];
        if (!is_string($formValue) || strlen($formValue) > 254) {
            $formValue = (string)$member[$k];
        }
        ?>
        <label class="block font-medium">
            <?= e($labels[$k]) ?>
            <input class="mt-1 w-full border rounded p-3" 
                   name="<?= e($k) ?>" 
                   required 
                   maxlength="<?= $k === 'email' ? 254 : 150 ?>" 
                   type="<?= $k === 'email' ? 'email' : 'text' ?>" 
                   value="<?= e($formValue) ?>">
        </label>
    <?php endforeach; ?>

    <div class="md:col-span-2">
        <button type="submit" class="bg-blue-900 text-white rounded px-5 py-3 font-medium">Save changes</button>
    </div>
</form>

<?php 
require __DIR__ . '/../includes/footer.php'; 
?>