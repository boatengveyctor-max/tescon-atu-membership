<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/formatters.php';
require_role('admin');
require_once __DIR__ . '/../includes/audit_logger.php';

$pdo = db();
$error = '';
$flash = take_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input_string($_POST, 'action', 20);

    try {
        if ($action === 'create') {
            $title = input_string($_POST, 'title', 200);
            $content = input_string($_POST, 'content', 100000);
            $category = input_string($_POST, 'category', 20) ?: 'general';
            $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
            $status = input_string($_POST, 'status', 20) ?: 'published';

            if ($title === '' || mb_strlen($title) > 200) {
                throw new InvalidArgumentException('Please provide a title up to 200 characters.');
            }

            if ($content === '') {
                throw new InvalidArgumentException('Please provide announcement content.');
            }

            if (!in_array($category, ['general', 'urgent', 'event', 'meeting'], true)) {
                $category = 'general';
            }

            if (!in_array($status, ['published', 'draft', 'archived'], true)) {
                $status = 'published';
            }

            $st = $pdo->prepare(
                "INSERT INTO announcements (title, content, category, is_pinned, status, author_id, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
            );
            $st->execute([$title, $content, $category, $isPinned, $status, (int)$_SESSION['user_id']]);
            $newId = (int)$pdo->lastInsertId();

            log_audit_event(
                $pdo,
                (int)$_SESSION['user_id'],
                'admin',
                'CREATE_ANNOUNCEMENT',
                'announcements',
                $newId,
                null,
                ['title' => $title, 'category' => $category, 'is_pinned' => $isPinned, 'status' => $status]
            );

            set_flash('success', 'Announcement published successfully.');
            header('Location: announcements.php', true, 303);
            exit;

        } elseif ($action === 'delete') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Invalid announcement ID.');
            }

            $stFind = $pdo->prepare('SELECT * FROM announcements WHERE id = ? LIMIT 1');
            $stFind->execute([$id]);
            $existing = $stFind->fetch();

            if ($existing) {
                $stDel = $pdo->prepare('DELETE FROM announcements WHERE id = ?');
                $stDel->execute([$id]);

                log_audit_event(
                    $pdo,
                    (int)$_SESSION['user_id'],
                    'admin',
                    'DELETE_ANNOUNCEMENT',
                    'announcements',
                    $id,
                    ['title' => $existing['title']],
                    null
                );

                set_flash('success', 'Announcement deleted successfully.');
            }

            header('Location: announcements.php', true, 303);
            exit;
        }
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Announcement action failed: ' . $e->getMessage());
        $error = 'Failed to process announcement. Please try again.';
    }
}

$announcements = $pdo->query(
    "SELECT a.*, u.username AS author_name 
     FROM announcements a 
     LEFT JOIN users u ON a.author_id = u.id 
     ORDER BY a.is_pinned DESC, a.created_at DESC"
)->fetchAll();

$pageTitle = 'Manage Announcements';
require __DIR__ . '/../includes/header.php';
?>

<nav class="flex flex-wrap gap-3 mb-8">
    <a class="bg-white border px-4 py-2 rounded" href="dashboard.php">Overview</a>
    <a class="bg-white border px-4 py-2 rounded" href="pending.php">Applications</a>
    <a class="bg-white border px-4 py-2 rounded" href="members.php">Members</a>
    <a class="bg-blue-900 text-white px-4 py-2 rounded" href="announcements.php">Announcements</a>
    <form method="post" action="logout.php" class="inline">
        <?= csrf_field() ?>
        <button type="submit" class="bg-red-700 text-white px-4 py-2 rounded">Sign out</button>
    </form>
</nav>

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-3xl font-bold">Manage Announcements</h1>
        <p class="text-slate-600 text-sm mt-1">Publish notices and event updates displayed on member dashboards.</p>
    </div>
</div>

<?php if ($error): ?>
    <div role="alert" class="bg-red-100 text-red-900 p-4 rounded-xl mb-6 text-sm font-medium border border-red-200">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<!-- Create Announcement Form -->
<section class="bg-white border rounded-2xl p-6 sm:p-7 shadow-sm mb-10">
    <h2 class="text-lg font-bold text-slate-900 mb-4">Post New Announcement</h2>

    <form method="post" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">

        <div class="grid sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">
                    Announcement Title
                    <input type="text" name="title" required maxlength="200" 
                           placeholder="e.g. Mandatory General Meeting & Orientation"
                           class="mt-1 w-full border rounded-lg p-2.5 font-normal text-base">
                </label>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">
                    Category
                    <select name="category" class="mt-1 w-full border rounded-lg p-2.5 font-normal text-base bg-white">
                        <option value="general">General News</option>
                        <option value="urgent">Urgent Notice</option>
                        <option value="meeting">Meeting / Assembly</option>
                        <option value="event">Campus Event</option>
                    </select>
                </label>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">
                Announcement Body
                <textarea name="content" required rows="4" 
                          placeholder="Write the full communication details here..."
                          class="mt-1 w-full border rounded-lg p-3 font-normal text-base"></textarea>
            </label>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 pt-2">
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 cursor-pointer">
                <input type="checkbox" name="is_pinned" value="1" class="rounded h-4 w-4 text-blue-900">
                Pin to top of member feed
            </label>

            <button type="submit" class="bg-blue-900 text-white px-6 py-2.5 rounded-xl font-semibold hover:bg-blue-800 transition shadow-sm">
                Publish Announcement
            </button>
        </div>
    </form>
</section>

<!-- Current Announcements Table -->
<section class="bg-white border rounded-2xl p-6 sm:p-7 shadow-sm">
    <h2 class="text-lg font-bold text-slate-900 mb-4">Published & Draft Announcements (<?= count($announcements) ?>)</h2>

    <?php if (empty($announcements)): ?>
        <p class="text-slate-500 italic py-4">No announcements published yet.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b text-slate-500">
                        <th class="pb-3 font-medium">Title & Category</th>
                        <th class="pb-3 font-medium">Status</th>
                        <th class="pb-3 font-medium">Author</th>
                        <th class="pb-3 font-medium">Date Posted</th>
                        <th class="pb-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($announcements as $a): ?>
                        <tr>
                            <td class="py-4 pr-4">
                                <div class="flex items-center gap-2">
                                    <?php if ((int)$a['is_pinned'] === 1): ?>
                                        <span class="bg-amber-100 text-amber-900 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">Pinned</span>
                                    <?php endif; ?>
                                    <span class="bg-slate-100 text-slate-700 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">
                                        <?= e((string)$a['category']) ?>
                                    </span>
                                </div>
                                <p class="font-bold text-slate-900 text-base mt-1"><?= e((string)$a['title']) ?></p>
                                <p class="text-xs text-slate-600 mt-1 line-clamp-2 max-w-xl"><?= e((string)$a['content']) ?></p>
                            </td>
                            <td class="py-4 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full <?= $a['status'] === 'published' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' ?>">
                                    <span class="h-1.5 w-1.5 rounded-full <?= $a['status'] === 'published' ? 'bg-emerald-600' : 'bg-slate-400' ?>"></span>
                                    <?= e(ucfirst((string)$a['status'])) ?>
                                </span>
                            </td>
                            <td class="py-4 text-slate-600 whitespace-nowrap">
                                <?= e((string)($a['author_name'] ?? 'Admin')) ?>
                            </td>
                            <td class="py-4 text-slate-500 text-xs whitespace-nowrap">
                                <?= e(format_utc_date((string)$a['created_at'], 'M j, Y g:i A')) ?>
                            </td>
                            <td class="py-4 text-right whitespace-nowrap">
                                <form method="post" class="inline" data-confirm="Are you sure you want to delete this announcement?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                    <button type="submit" class="text-xs bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 px-3 py-1.5 rounded-lg font-semibold transition">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
