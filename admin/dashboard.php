<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_middleware.php';
require_role('admin');

$counts = db()->query("SELECT status, COUNT(*) total FROM members WHERE is_deleted = 0 GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$pageTitle = 'Admin dashboard';

require __DIR__ . '/../includes/header.php';
?>

<nav class="flex flex-wrap gap-3 mb-8">
    <a class="bg-blue-900 text-white px-4 py-2 rounded" href="dashboard.php">Overview</a>
    <a class="bg-white border px-4 py-2 rounded" href="pending.php">Applications</a>
    <a class="bg-white border px-4 py-2 rounded" href="members.php">Members</a>
    <a class="bg-white border px-4 py-2 rounded" href="announcements.php">Announcements</a>
    <form method="post" action="logout.php" class="inline">
        <?= csrf_field() ?>
        <button type="submit" class="bg-red-700 text-white px-4 py-2 rounded">Sign out</button>
    </form>
</nav>

<h1 class="text-3xl font-bold">Membership overview</h1>

<div class="grid sm:grid-cols-3 gap-5 mt-7">
    <?php foreach (['pending' => 'Pending review', 'approved' => 'Approved members', 'rejected' => 'Rejected'] as $k => $label): ?>
        <div class="bg-white border rounded-xl p-6 shadow-sm">
            <p class="text-slate-600"><?= e($label) ?></p>
            <p class="text-4xl font-bold mt-3"><?= number_format((int)($counts[$k] ?? 0)) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<?php 
require __DIR__ . '/../includes/footer.php'; 
?>