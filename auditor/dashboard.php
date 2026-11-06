<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_middleware.php';
require_role('auditor');

$pdo = db();
$total = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
$today = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs WHERE created_at >= UTC_DATE()')->fetchColumn();
$types = $pdo->query('SELECT action_type, COUNT(*) total FROM audit_logs GROUP BY action_type ORDER BY total DESC')->fetchAll();

$pageTitle = 'Audit dashboard';
require __DIR__ . '/../includes/header.php';
?>

<nav class="flex gap-3 mb-8 items-center">
    <a href="dashboard.php" class="bg-blue-900 text-white px-4 py-2 rounded shadow-sm">Overview</a>
    <a href="audit_logs.php" class="bg-white border px-4 py-2 rounded shadow-sm hover:bg-slate-50 transition">Audit logs</a>
    <form method="post" action="logout.php" class="inline">
        <?= csrf_field() ?>
        <button type="submit" class="bg-red-700 text-white px-4 py-2 rounded shadow-sm hover:bg-red-600 transition">Sign out</button>
    </form>
</nav>

<h1 class="text-3xl font-bold">Audit overview</h1>

<div class="grid sm:grid-cols-2 gap-5 mt-7">
    <div class="bg-white border rounded-xl p-6 shadow-sm">
        <p class="text-slate-600 text-sm font-medium">All recorded events</p>
        <p class="text-4xl font-bold mt-3 text-slate-900"><?= number_format($total) ?></p>
    </div>
    <div class="bg-white border rounded-xl p-6 shadow-sm">
        <p class="text-slate-600 text-sm font-medium">Events today (UTC)</p>
        <p class="text-4xl font-bold mt-3 text-slate-900"><?= number_format($today) ?></p>
    </div>
</div>

<section class="mt-8 bg-white border rounded-xl p-6 shadow-sm">
    <h2 class="font-bold text-xl text-slate-900">Events by action</h2>
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-4">
        <?php foreach ($types as $t): ?>
            <div class="bg-slate-50 rounded-lg p-4 border border-slate-100">
                <p class="text-sm font-medium text-slate-600"><?= e($t['action_type']) ?></p>
                <strong class="text-2xl font-bold text-slate-900 mt-1 block"><?= number_format((int)$t['total']) ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php 
require __DIR__ . '/../includes/footer.php'; 
?>