<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/formatters.php';
require_role('member');
require_once __DIR__ . '/../includes/member_audit.php';

$pdo = db();
$category = input_string($_GET, 'category', 20);

try {
    log_member_view_once(
        $pdo,
        (int)$_SESSION['user_id'],
        'VIEW_ANNOUNCEMENTS',
        'announcements',
        null,
        'last_announcements_view_log',
        ['category_filter' => $category]
    );
} catch (Throwable $e) {
    error_log('Member announcements view audit log failure: ' . $e->getMessage());
}

$sql = "SELECT a.*, u.username AS author_name 
        FROM announcements a 
        LEFT JOIN users u ON a.author_id = u.id 
        WHERE a.status = 'published'";
$params = [];

if (in_array($category, ['general', 'urgent', 'event', 'meeting'], true)) {
    $sql .= " AND a.category = ?";
    $params[] = $category;
}

$sql .= " ORDER BY a.is_pinned DESC, a.created_at DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$announcements = $st->fetchAll();

$categories = [
    ''        => 'All Categories',
    'urgent'  => 'Urgent Notices',
    'meeting' => 'Meetings & General Assemblies',
    'event'   => 'Events & Campaigns',
    'general' => 'General News'
];

$pageTitle = 'Announcements';
$activeMemberPage = 'announcements';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chapter Announcements · TESCON ATU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= e(APP_BASE_URL) ?>/assets/css/style.css?v=aurora-light-1">
</head>
<body class="member-portal member-announcements min-h-screen flex flex-col justify-between">
    <?php require __DIR__ . '/../includes/member_navigation.php'; ?>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 py-8 flex-1 w-full">
        <!-- Hero & Filter bar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900">Branch Announcements</h1>
                <p class="text-slate-600 text-sm mt-1">Official communications, meeting circulars, and notices from the executive committee.</p>
            </div>

            <!-- Category filter pill list -->
            <div class="flex flex-wrap gap-2">
                <?php foreach ($categories as $catKey => $catLabel): ?>
                    <a href="announcements.php<?= $catKey !== '' ? '?category=' . urlencode($catKey) : '' ?>" 
                       class="px-3 py-1.5 rounded-full text-xs font-semibold transition <?= $category === $catKey ? 'bg-blue-900 text-white shadow-sm' : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-100' ?>">
                        <?= e($catLabel) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (empty($announcements)): ?>
            <div class="member-empty-state bg-white border border-slate-200 rounded-2xl p-12 text-center shadow-sm">
                <svg class="h-12 w-12 text-slate-400 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                </svg>
                <h2 class="text-base font-bold text-slate-800">No announcements in this category</h2>
                <p class="text-sm text-slate-500 mt-1">Check back later or browse all categories for executive updates.</p>
                <a href="announcements.php" class="inline-block mt-4 text-xs font-semibold text-blue-900 hover:underline">
                    View all announcements
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($announcements as $ann): ?>
                    <?php
                    $isPinned = (int)$ann['is_pinned'] === 1;
                    $cat = (string)$ann['category'];
                    $badgeColors = match ($cat) {
                        'urgent'  => 'bg-red-100 text-red-800 border-red-200',
                        'meeting' => 'bg-purple-100 text-purple-800 border-purple-200',
                        'event'   => 'bg-blue-100 text-blue-800 border-blue-200',
                        default   => 'bg-slate-100 text-slate-800 border-slate-200'
                    };
                    ?>
                    <article class="bg-white border <?= $isPinned ? 'border-blue-400 ring-2 ring-blue-500/10' : 'border-slate-200' ?> rounded-2xl p-6 sm:p-7 shadow-sm transition hover:shadow-md">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                            <div class="flex items-center gap-2">
                                <?php if ($isPinned): ?>
                                    <span class="inline-flex items-center gap-1 bg-amber-100 text-amber-900 border border-amber-300 text-[11px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                                        <svg class="h-3 w-3 text-amber-700" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.599-.8a1 1 0 01.894 1.79l-1.233.616 1.738 5.42a1 1 0 01-.285 1.05A3.989 3.989 0 0115 15a3.989 3.989 0 01-2.667-1.018 1 1 0 01-.285-1.05l1.715-5.347-2.763-1.106V16a1 1 0 01-2 0V6.479L6.237 7.585l1.715 5.348a1 1 0 01-.285 1.05A3.989 3.989 0 015 15a3.989 3.989 0 01-2.667-1.018 1 1 0 01-.285-1.05l1.738-5.42-1.233-.617a1 1 0 01.894-1.789l1.599.799L9 4.323V3a1 1 0 011-1z"/>
                                        </svg>
                                        Pinned
                                    </span>
                                <?php endif; ?>

                                <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider border <?= $badgeColors ?>">
                                    <?= e(ucfirst($cat)) ?>
                                </span>
                            </div>

                            <time class="text-xs text-slate-500 font-medium">
                                <?= e(format_utc_date((string)$ann['created_at'], 'M j, Y · g:i A')) ?>
                            </time>
                        </div>

                        <h2 class="text-xl font-black text-slate-900 leading-snug">
                            <?= e((string)$ann['title']) ?>
                        </h2>

                        <div class="mt-3 text-slate-700 text-sm sm:text-base leading-relaxed whitespace-pre-line">
                            <?= nl2br(e((string)$ann['content'])) ?>
                        </div>

                        <div class="mt-5 pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                            <span>Posted by: <strong class="text-slate-700"><?= e((string)($ann['author_name'] ?? 'Secretariat')) ?></strong></span>
                            <span>TESCON ATU Official</span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php require __DIR__ . '/../includes/member_footer.php'; ?>
</body>
</html>
