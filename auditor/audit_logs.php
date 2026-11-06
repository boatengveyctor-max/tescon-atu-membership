<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_middleware.php';
require_role('auditor');

$pdo = db();
$from = input_string($_GET, 'from', 10);
$to = input_string($_GET, 'to', 10);
$action = input_string($_GET, 'action', 30);
$actor = input_string($_GET, 'actor', 10);
$keyword = input_string($_GET, 'q', 200);

$where = [];
$args = [];

if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $where[] = 'created_at >= ?';
    $args[] = $from . ' 00:00:00';
}

if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $where[] = 'created_at <= ?';
    $args[] = $to . ' 23:59:59';
}

if ($action !== '' && preg_match('/^[A-Z_]{2,30}$/', $action)) {
    $where[] = 'action_type = ?';
    $args[] = $action;
}

if (in_array($actor, ['public', 'member', 'admin', 'auditor'], true)) {
    $where[] = 'actor_type = ?';
    $args[] = $actor;
}

if ($keyword !== '') {
    $where[] = '(CAST(actor_id AS CHAR) = ? OR CAST(target_id AS CHAR) = ? OR target_table LIKE ? OR ip_address LIKE ? OR user_agent LIKE ? OR old_values LIKE ? OR new_values LIKE ?)';
    $args = array_merge($args, [$keyword, $keyword, '%' . $keyword . '%', '%' . $keyword . '%', '%' . $keyword . '%', '%' . $keyword . '%', '%' . $keyword . '%']);
}

$cond = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$page = max(1, (int)($_GET['page'] ?? 1));

$st = $pdo->prepare("SELECT COUNT(*) FROM audit_logs $cond");
$st->execute($args);
$total = (int)$st->fetchColumn();

$pages = max(1, (int)ceil($total / PAGE_SIZE));
$page = min($page, $pages);

$st = $pdo->prepare("SELECT log_id, actor_id, actor_type, action_type, target_table, target_id, old_values, new_values, ip_address, user_agent, created_at FROM audit_logs $cond ORDER BY created_at DESC, log_id DESC LIMIT " . PAGE_SIZE . ' OFFSET ' . (($page - 1) * PAGE_SIZE));
$st->execute($args);
$rows = $st->fetchAll();

$pageTitle = 'Audit logs';
require __DIR__ . '/../includes/header.php';
?>

<nav class="flex gap-3 mb-7">
    <a href="dashboard.php" class="bg-white border px-4 py-2 rounded shadow-sm hover:bg-slate-50 transition">Overview</a>
    <form method="post" action="logout.php" class="inline">
        <?= csrf_field() ?>
        <button type="submit" class="bg-red-700 text-white px-4 py-2 rounded hover:bg-red-600 transition">Sign out</button>
    </form>
</nav>

<h1 class="text-3xl font-bold">Immutable audit log</h1>

<form method="get" class="grid sm:grid-cols-2 lg:grid-cols-6 gap-3 mt-6 bg-white border rounded-xl p-4 shadow-sm">
    <label class="block font-medium text-sm text-slate-700">
        From
        <input type="date" name="from" value="<?= e($from) ?>" class="mt-1 w-full border rounded p-2 font-normal">
    </label>
    
    <label class="block font-medium text-sm text-slate-700">
        To
        <input type="date" name="to" value="<?= e($to) ?>" class="mt-1 w-full border rounded p-2 font-normal">
    </label>
    
    <label class="block font-medium text-sm text-slate-700">
        Action
        <select name="action" class="mt-1 w-full border rounded p-2 font-normal bg-white">
            <option value="">All</option>
            <?php foreach (['REGISTER', 'APPROVE', 'REJECT', 'UPDATE', 'DELETE', 'LOGIN_SUCCESS', 'LOGIN_FAILURE', 'LOGOUT', 'VIEW_PROFILE'] as $a): ?>
                <option value="<?= e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e($a) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    
    <label class="block font-medium text-sm text-slate-700">
        Actor
        <select name="actor" class="mt-1 w-full border rounded p-2 font-normal bg-white">
            <option value="">All</option>
            <?php foreach (['public', 'member', 'admin', 'auditor'] as $a): ?>
                <option value="<?= e($a) ?>" <?= $actor === $a ? 'selected' : '' ?>><?= e($a) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    
    <label class="block font-medium text-sm text-slate-700 lg:col-span-2">
        Keyword
        <input name="q" value="<?= e($keyword) ?>" class="mt-1 w-full border rounded p-2 font-normal" placeholder="ID, IP, table or content">
    </label>
    
    <button type="submit" class="bg-blue-900 text-white rounded px-4 py-2 self-end hover:bg-blue-800 transition font-medium">Filter</button>
</form>

<div class="overflow-x-auto mt-5 bg-white border rounded-xl shadow-sm">
    <table class="w-full text-sm text-left">
        <thead class="bg-slate-100 text-slate-700 uppercase text-xs tracking-wider">
            <tr>
                <?php foreach (['Time (UTC)', 'Actor', 'Action', 'Target', 'IP address', 'Details'] as $h): ?>
                    <th class="p-3"><?= e($h) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
            <?php foreach ($rows as $r): ?>
                <tr class="hover:bg-slate-50 align-top">
                    <td class="p-3 whitespace-nowrap text-slate-600"><?= e($r['created_at']) ?></td>
                    <td class="p-3 text-slate-900 font-medium"><?= e($r['actor_type']) ?><?= $r['actor_id'] !== null ? ' #' . (int)$r['actor_id'] : '' ?></td>
                    <td class="p-3 text-slate-900"><?= e($r['action_type']) ?></td>
                    <td class="p-3 text-slate-600"><?= e($r['target_table']) ?><?= $r['target_id'] !== null ? ' #' . (int)$r['target_id'] : '' ?></td>
                    <td class="p-3 text-slate-600 font-mono text-xs"><?= e($r['ip_address']) ?></td>
                    <td class="p-3">
                        <details class="group">
                            <summary class="cursor-pointer text-blue-800 font-medium hover:underline">View values</summary>
                            <div class="mt-2 space-y-2 p-3 bg-slate-50 rounded border text-xs">
                                <div>
                                    <h3 class="font-semibold text-slate-700">Old values</h3>
                                    <pre class="overflow-auto max-w-xl font-mono text-slate-600 mt-1"><?= e(json_encode(json_decode((string)$r['old_values'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: 'null') ?></pre>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-slate-700">New values</h3>
                                    <pre class="overflow-auto max-w-xl font-mono text-slate-600 mt-1"><?= e(json_encode(json_decode((string)$r['new_values'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: 'null') ?></pre>
                                </div>
                            </div>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php if (!$rows): ?>
        <p class="p-6 text-slate-600 text-center">No audit entries match these filters.</p>
    <?php endif; ?>
</div>

<div class="mt-4 flex items-center justify-between text-sm">
    <div class="flex gap-2">
        <?php if ($page > 1): ?>
            <a class="bg-white border rounded px-3 py-2 shadow-sm hover:bg-slate-50 transition" href="?<?= http_build_query($_GET + ['page' => $page - 1]) ?>">Previous</a>
        <?php endif; ?>
    </div>
    
    <span class="text-slate-600">Page <?= $page ?> of <?= $pages ?> · <?= $total ?> entries</span>
    
    <div class="flex gap-2">
        <?php if ($page < $pages): ?>
            <a class="bg-white border rounded px-3 py-2 shadow-sm hover:bg-slate-50 transition" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
        <?php endif; ?>
    </div>
</div>

<?php 
require __DIR__ . '/../includes/footer.php'; 
?>