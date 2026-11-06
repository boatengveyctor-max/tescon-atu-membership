<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_middleware.php';
require_role('admin');
require_once __DIR__ . '/../includes/audit_logger.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            throw new InvalidArgumentException('Invalid member.');
        }

        $pdo->beginTransaction();

        $q = $pdo->prepare("SELECT id, status, is_deleted, deleted_at FROM members WHERE id = ? FOR UPDATE");
        $q->execute([$id]);
        $old = $q->fetch();

        if (!$old || $old['is_deleted']) {
            throw new RuntimeException('Member record is unavailable.');
        }

        $st = $pdo->prepare('UPDATE members SET is_deleted = 1, deleted_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ? AND is_deleted = 0');
        $st->execute([$id]);

        log_audit_event($pdo, (int)$_SESSION['user_id'], 'admin', 'DELETE', 'members', (int)$id, $old, ['is_deleted' => 1, 'deleted_at' => 'set']);
        
        $pdo->commit();
        set_flash('success', 'Member archived.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('error', $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Could not archive member.');
    }
    redirect('members.php');
}

$search = input_string($_GET, 'q', 150);
$page = max(1, (int)($_GET['page'] ?? 1));
$params = [];
$where = "status = 'approved' AND is_deleted = 0";

if ($search !== '') {
    $where .= ' AND (full_name LIKE ? OR phone_number LIKE ? OR index_number LIKE ?)';
    $s = '%' . $search . '%';
    $params = [$s, $s, $s];
}

$q = $pdo->prepare("SELECT COUNT(*) FROM members WHERE $where");
$q->execute($params);
$total = (int)$q->fetchColumn();

$pages = max(1, (int)ceil($total / PAGE_SIZE));
$page = min($page, $pages);

$st = $pdo->prepare("SELECT id, full_name, index_number, phone_number, email, institution, program_of_study, level, region, constituency FROM members WHERE $where ORDER BY full_name LIMIT " . PAGE_SIZE . ' OFFSET ' . (($page - 1) * PAGE_SIZE));
$st->execute($params);
$rows = $st->fetchAll();

$pageTitle = 'Approved members';
require __DIR__ . '/../includes/header.php';
?>

<nav class="flex gap-3 mb-7">
    <a class="bg-white border px-4 py-2 rounded shadow-sm hover:bg-slate-50 transition" href="dashboard.php">Overview</a>
    <a class="bg-white border px-4 py-2 rounded shadow-sm hover:bg-slate-50 transition" href="pending.php">Applications</a>
    <a class="bg-white border px-4 py-2 rounded shadow-sm hover:bg-slate-50 transition" href="announcements.php">Announcements</a>
</nav>

<div class="flex flex-wrap items-end justify-between gap-4">
    <h1 class="text-3xl font-bold">Approved members</h1>
    <form method="get" class="flex gap-2">
        <label class="sr-only" for="q">Search by name, phone or student ID</label>
        <input id="q" name="q" value="<?= e($search) ?>" placeholder="Name, phone or ID" class="border rounded px-3 py-2 bg-white">
        <button type="submit" class="bg-blue-900 text-white rounded px-4 py-2 hover:bg-blue-800 transition">Search</button>
    </form>
</div>

<div class="overflow-x-auto mt-6 bg-white border rounded-xl shadow-sm">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-100 text-slate-700 uppercase text-xs tracking-wider">
            <tr>
                <?php foreach (['Name', 'Student ID', 'Phone', 'Email', 'Department / Institution', 'Actions'] as $h): ?>
                    <th class="p-3"><?= e($h) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
            <?php foreach ($rows as $m): ?>
                <tr class="hover:bg-slate-50">
                    <td class="p-3 font-medium text-slate-900"><?= e($m['full_name']) ?></td>
                    <td class="p-3 text-slate-600"><?= e($m['index_number']) ?></td>
                    <td class="p-3 text-slate-600"><?= e($m['phone_number']) ?></td>
                    <td class="p-3 text-slate-600"><?= e($m['email']) ?></td>
                    <td class="p-3 text-slate-600"><?= e($m['institution']) ?></td>
                    <td class="p-3">
                        <div class="flex gap-3">
                            <a class="text-blue-800 hover:underline font-medium" href="edit_member.php?id=<?= $m['id'] ?>">Edit</a>
                            <form method="post" data-confirm="Archive this member record?" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                <button type="submit" class="text-red-700 hover:underline font-medium">Archive</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php if (!$rows): ?>
        <p class="p-6 text-slate-600 text-center">No matching members found.</p>
    <?php endif; ?>
</div>

<div class="flex items-center justify-between mt-5 text-sm">
    <div class="flex gap-2">
        <?php if ($page > 1): ?>
            <a class="bg-white border rounded px-3 py-2 shadow-sm hover:bg-slate-50 transition" href="?q=<?= urlencode($search) ?>&page=<?= $page - 1 ?>">Previous</a>
        <?php endif; ?>
    </div>
    
    <span class="text-slate-600">Page <?= $page ?> of <?= $pages ?> · <?= $total ?> records</span>
    
    <div class="flex gap-2">
        <?php if ($page < $pages): ?>
            <a class="bg-white border rounded px-3 py-2 shadow-sm hover:bg-slate-50 transition" href="?q=<?= urlencode($search) ?>&page=<?= $page + 1 ?>">Next</a>
        <?php endif; ?>
    </div>
</div>

<?php 
require __DIR__ . '/../includes/footer.php'; 
?>