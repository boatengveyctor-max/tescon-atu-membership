<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_middleware.php';
require_role('admin');
require_once __DIR__ . '/../includes/audit_logger.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $action = input_string($_POST, 'action', 10);
        $reason = input_string($_POST, 'reason', 500);

        if (!$id || !in_array($action, ['approve', 'reject'], true) || ($action === 'reject' && ($reason === '' || mb_strlen($reason) > 500))) {
            throw new InvalidArgumentException('Choose a valid action and provide a short rejection reason.');
        }

        $pdo->beginTransaction();

        $q = $pdo->prepare("SELECT * FROM members WHERE id = ? AND status = 'pending' AND is_deleted = 0 FOR UPDATE");
        $q->execute([$id]);
        $old = $q->fetch();

        if (!$old) {
            throw new RuntimeException('This application is no longer pending.');
        }

        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        $st = $pdo->prepare('UPDATE members SET status = ?, rejection_reason = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?');
        $st->execute([$newStatus, $action === 'reject' ? $reason : null, $id]);

        $oldAudit = ['status' => $old['status']];
        $newAudit = ['status' => $newStatus];
        
        if ($action === 'reject') {
            $newAudit['rejection_reason'] = $reason;
        }

        log_audit_event($pdo, (int)$_SESSION['user_id'], 'admin', strtoupper($action), 'members', (int)$id, $oldAudit, $newAudit);
        
        $pdo->commit();
        set_flash('success', 'Application ' . $newStatus . '.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('error', $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Could not update application.');
    }
    redirect('pending.php');
}

$page  = max(1, (int)($_GET['page'] ?? 1));
$total = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status = 'pending' AND is_deleted = 0")->fetchColumn();
$pages = max(1, (int)ceil($total / PAGE_SIZE));
$page  = min($page, $pages);

$rows = $pdo->prepare("SELECT * FROM members WHERE status = 'pending' AND is_deleted = 0 ORDER BY created_at ASC LIMIT " . PAGE_SIZE . ' OFFSET ' . (($page - 1) * PAGE_SIZE));
$rows->execute();
$rows = $rows->fetchAll();
$pageTitle = 'Pending applications';

require __DIR__ . '/../includes/header.php';
?>

<nav class="flex gap-3 mb-7">
    <a class="bg-white border px-4 py-2 rounded shadow-sm hover:bg-slate-50 transition" href="dashboard.php">Overview</a>
    <a class="bg-white border px-4 py-2 rounded shadow-sm hover:bg-slate-50 transition" href="members.php">Members</a>
    <a class="bg-white border px-4 py-2 rounded shadow-sm hover:bg-slate-50 transition" href="announcements.php">Announcements</a>
</nav>

<h1 class="text-3xl font-bold">Pending applications <span class="text-slate-500 font-normal">(<?= $total ?>)</span></h1>

<div class="mt-6 space-y-4">
    <?php foreach ($rows as $m): ?>
        <article class="bg-white border rounded-xl p-5 shadow-sm">
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div>
                    <strong class="text-slate-900"><?= e($m['full_name']) ?></strong>
                    <p class="text-slate-600 text-sm"><?= e($m['index_number']) ?></p>
                </div>
                <div>
                    <p class="text-slate-900"><?= e($m['email']) ?></p>
                    <p class="text-slate-600 text-sm"><?= e($m['phone_number']) ?></p>
                </div>
                <div>
                    <p class="text-slate-900"><?= e($m['institution']) ?></p>
                    <p class="text-slate-600 text-sm"><?= e($m['program_of_study']) ?> · <?= e($m['level']) ?></p>
                </div>
            </div>

            <?php if (!empty($m['verification_photo'])): ?>
                <figure class="mt-4">
                    <img
                        src="member_photo.php?id=<?= (int)$m['id'] ?>"
                        alt="Student ID verification photo for <?= e((string)$m['full_name']) ?>"
                        class="max-h-64 max-w-full rounded-lg border border-slate-200 object-contain"
                        loading="lazy"
                    >
                    <figcaption class="mt-1 text-xs text-slate-500">Private verification photo</figcaption>
                </figure>
            <?php endif; ?>

            <div class="mt-4 flex flex-wrap gap-3 items-center">
                <form method="post" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="bg-emerald-700 text-white rounded px-4 py-2 font-medium hover:bg-emerald-600 transition">Approve</button>
                </form>

                <form method="post" class="flex gap-2 items-center">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <input name="reason" maxlength="500" required placeholder="Reason for rejection" class="border rounded px-3 py-1.5 text-sm bg-white">
                    <button type="submit" class="bg-red-700 text-white rounded px-4 py-2 font-medium hover:bg-red-600 transition">Reject</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>

    <?php if (!$rows): ?>
        <p class="bg-white border rounded-xl p-6 text-slate-600 text-center shadow-sm">No pending applications.</p>
    <?php endif; ?>
</div>

<div class="flex items-center justify-between mt-5 text-sm">
    <div class="flex gap-2">
        <?php if ($page > 1): ?>
            <a class="bg-white border rounded px-3 py-2 shadow-sm hover:bg-slate-50 transition" href="?page=<?= $page - 1 ?>">Previous</a>
        <?php endif; ?>
    </div>

    <span class="text-slate-600">Page <?= $page ?> of <?= $pages ?> · <?= $total ?> applications</span>

    <div class="flex gap-2">
        <?php if ($page < $pages): ?>
            <a class="bg-white border rounded px-3 py-2 shadow-sm hover:bg-slate-50 transition" href="?page=<?= $page + 1 ?>">Next</a>
        <?php endif; ?>
    </div>
</div>

<?php 
require __DIR__ . '/../includes/footer.php'; 
?>