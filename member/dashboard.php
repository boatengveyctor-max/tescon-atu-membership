<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/member_helpers.php';
require_once __DIR__ . '/../includes/formatters.php';

require_once __DIR__ . '/../includes/member_audit.php';

$member = require_member_profile();
$pdo = db();

try {
    log_member_view_once(
        $pdo,
        (int)$member['id'],
        'VIEW_PROFILE',
        'members',
        (int)$member['id'],
        'last_profile_view_log',
        ['status' => $member['status']]
    );
} catch (Throwable $e) {
    error_log('Member profile view audit log failure: ' . $e->getMessage());
}

// Fetch latest published announcements for dashboard banner
$annStmt = $pdo->query("SELECT * FROM announcements WHERE status = 'published' ORDER BY is_pinned DESC, created_at DESC LIMIT 3");
$recentAnnouncements = $annStmt->fetchAll();

$pageTitle = 'Member Portal';
$activeMemberPage = 'dashboard';
$status = (string)($member['status'] ?? 'pending');
$flash = take_flash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Member Dashboard · TESCON ATU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= e(APP_BASE_URL) ?>/assets/css/style.css?v=aurora-light-1">
</head>
<body class="member-portal member-dashboard min-h-screen flex flex-col justify-between">
    <?php require __DIR__ . '/../includes/member_navigation.php'; ?>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-8 flex-1 w-full space-y-8">
        <?php if ($flash): ?>
            <div role="status" class="rounded-xl p-4 shadow-sm text-sm font-medium <?= $flash['type'] === 'success' ? 'bg-emerald-100 text-emerald-900 border border-emerald-200' : 'bg-red-100 text-red-900 border border-red-200' ?>">
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>

        <!-- Welcome & Membership Status Banner -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-4">
                <?php if (!empty($member['verification_photo'])): ?>
                    <img
                        src="photo.php"
                        alt="<?= e((string)$member['full_name']) ?>"
                        class="h-16 w-16 shrink-0 rounded-2xl border-2 border-white object-cover shadow-md sm:h-20 sm:w-20"
                    >
                <?php endif; ?>
                <div class="min-w-0">
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900">
                    Welcome, <?= e((string)$member['full_name']) ?>
                </h1>
                <p class="text-sm text-slate-600 mt-1">
                    Student ID: <span class="font-mono font-semibold text-slate-800"><?= e((string)$member['index_number']) ?></span> · 
                    Chapter: <span class="font-semibold text-blue-900"><?= e((string)$member['institution']) ?></span>
                </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="id_card.php" class="inline-flex items-center gap-2 bg-blue-900 text-white px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold hover:bg-blue-800 transition shadow-sm">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                    </svg>
                    Digital ID Card
                </a>
                <a href="announcements.php" class="inline-flex items-center gap-2 bg-white border border-slate-300 text-slate-800 px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold hover:bg-slate-50 transition shadow-sm">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    Announcements
                </a>
            </div>
        </div>

        <?php if ($status === 'approved'): ?>
            <!-- Approved Member State -->
            <section class="bg-emerald-50 border border-emerald-200 rounded-2xl p-6 sm:p-8 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-100 border border-emerald-300 px-3 py-1 text-xs font-bold uppercase tracking-wider text-emerald-800">
                            <svg class="h-4 w-4 text-emerald-700" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/>
                            </svg>
                            Verified Member
                        </span>
                        <h2 class="text-2xl font-black text-slate-900 mt-3">Membership Active & Approved</h2>
                        <p class="text-slate-700 mt-1 max-w-xl text-sm leading-relaxed">
                            Congratulations! Your application has been approved by the TESCON ATU executive board. You are in good standing with full voting and participating rights.
                        </p>
                        
                        <div class="mt-4 flex flex-wrap gap-3">
                            <a href="id_card.php" class="inline-flex items-center gap-2 bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold hover:bg-emerald-800 transition shadow-sm">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Download ID Card
                            </a>
                            <a href="id_card.php?format=svg" class="inline-flex items-center gap-2 bg-white border border-emerald-300 text-emerald-900 px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold hover:bg-emerald-100 transition">
                                Vector SVG
                            </a>
                        </div>
                    </div>

                    <!-- Digital ID Badge Quick Card -->
                    <div class="member-card-preview shrink-0 w-full max-w-xs bg-gradient-to-br from-blue-950 via-blue-900 to-slate-900 text-white rounded-2xl p-5 shadow-xl border-t-4 border-red-600 relative overflow-hidden">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <p class="text-[10px] uppercase font-bold tracking-widest text-red-400">TESCON ATU CHAPTER</p>
                                <p class="text-xs font-medium text-blue-200">Membership Card</p>
                            </div>
                            <span class="bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">Active</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <p class="min-w-0 text-lg font-black tracking-tight leading-snug"><?= e((string)$member['full_name']) ?></p>
                            <?php if (!empty($member['verification_photo'])): ?>
                                <img
                                    src="photo.php"
                                    alt=""
                                    class="h-12 w-12 shrink-0 rounded-lg border border-white/40 object-cover"
                                >
                            <?php endif; ?>
                        </div>
                        <p class="text-xs font-mono text-blue-200 mt-1"><?= e((string)$member['index_number']) ?></p>
                        <div class="mt-4 pt-3 border-t border-white/10 flex justify-between items-center text-[11px] text-blue-200">
                            <span class="truncate max-w-[160px]"><?= e((string)$member['program_of_study']) ?></span>
                            <span>Level <?= e((string)$member['level']) ?></span>
                        </div>
                    </div>
                </div>
            </section>

        <?php elseif ($status === 'rejected'): ?>
            <!-- Rejected State -->
            <section class="bg-red-50 border border-red-200 rounded-2xl p-6 sm:p-8 shadow-sm">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 border border-red-300 px-3 py-1 text-xs font-bold uppercase tracking-wider text-red-800">
                    <svg class="h-4 w-4 text-red-700" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z" clip-rule="evenodd"/>
                    </svg>
                    Application Declined
                </span>
                <h2 class="text-xl sm:text-2xl font-bold text-red-950 mt-3">Membership Application Not Approved</h2>
                <p class="text-sm text-red-800 mt-1 max-w-2xl leading-relaxed">
                    Your application could not be approved by the membership verification committee at this time.
                </p>

                <?php if (!empty($member['rejection_reason'])): ?>
                    <div class="mt-4 p-4 rounded-xl bg-white border border-red-200 text-sm">
                        <strong class="text-red-900 block font-semibold">Feedback from Committee:</strong>
                        <p class="text-slate-700 mt-1"><?= e((string)$member['rejection_reason']) ?></p>
                    </div>
                <?php endif; ?>

                <p class="text-xs text-red-700 mt-4">
                    If you believe this was an error or need to rectify your submitted credentials, please contact the campus secretariat with your Student ID (<?= e((string)$member['index_number']) ?>).
                </p>
            </section>

        <?php else: ?>
            <!-- Pending State -->
            <section class="bg-amber-50 border border-amber-200 rounded-2xl p-6 sm:p-8 shadow-sm">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 border border-amber-300 px-3 py-1 text-xs font-bold uppercase tracking-wider text-amber-900">
                    <svg class="h-4 w-4 text-amber-700 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Under Review
                </span>
                <h2 class="text-xl sm:text-2xl font-bold text-slate-900 mt-3">Application Pending Verification</h2>
                <p class="text-sm text-slate-700 mt-1 max-w-2xl leading-relaxed">
                    Thank you for applying to join TESCON ATU. Your application has been logged and is awaiting verification by the branch executives.
                </p>

                <!-- Process Progress -->
                <div class="mt-6 grid sm:grid-cols-3 gap-3 text-xs">
                    <div class="bg-white p-3.5 rounded-xl border border-amber-200">
                        <p class="font-bold text-emerald-700 flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span> 1. Application Submitted
                        </p>
                        <p class="text-slate-500 mt-1">Details recorded in system</p>
                    </div>
                    <div class="bg-white p-3.5 rounded-xl border-2 border-amber-400 shadow-sm">
                        <p class="font-bold text-amber-800 flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full bg-amber-500 animate-pulse"></span> 2. Executive Review
                        </p>
                        <p class="text-slate-600 mt-1">Verification in progress</p>
                    </div>
                    <div class="bg-white p-3.5 rounded-xl border border-slate-200 opacity-60">
                        <p class="font-bold text-slate-500 flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full bg-slate-300"></span> 3. Membership Activation
                        </p>
                        <p class="text-slate-400 mt-1">Card issued upon approval</p>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- Announcements Feed Preview -->
        <section class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 shadow-sm">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">Branch Announcements</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Stay informed with updates from the executive secretariat.</p>
                </div>
                <a href="announcements.php" class="text-xs sm:text-sm font-semibold text-blue-900 hover:text-blue-700 flex items-center gap-1">
                    View all
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>

            <?php if (empty($recentAnnouncements)): ?>
                <p class="text-sm text-slate-500 italic py-4">No published announcements at the moment.</p>
            <?php else: ?>
                <div class="grid md:grid-cols-3 gap-4">
                    <?php foreach ($recentAnnouncements as $a): ?>
                        <?php
                        $isPinned = (int)$a['is_pinned'] === 1;
                        $cat = (string)$a['category'];
                        $badgeStyle = match ($cat) {
                            'urgent'  => 'bg-red-100 text-red-800',
                            'meeting' => 'bg-purple-100 text-purple-800',
                            'event'   => 'bg-blue-100 text-blue-800',
                            default   => 'bg-slate-100 text-slate-800'
                        };
                        ?>
                        <article class="p-4 rounded-xl border <?= $isPinned ? 'border-blue-300 bg-blue-50/30' : 'border-slate-200 bg-slate-50/50' ?> flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full <?= $badgeStyle ?>">
                                        <?= e(ucfirst($cat)) ?>
                                    </span>
                                    <time class="text-[11px] text-slate-400"><?= e(format_utc_date((string)$a['created_at'])) ?></time>
                                </div>
                                <h3 class="text-sm font-bold text-slate-900 line-clamp-2">
                                    <?= e((string)$a['title']) ?>
                                </h3>
                                <p class="text-xs text-slate-600 mt-2 line-clamp-3 leading-relaxed">
                                    <?= e((string)$a['content']) ?>
                                </p>
                            </div>
                            <div class="mt-4 pt-3 border-t border-slate-200/60">
                                <a href="announcements.php" class="text-xs font-semibold text-blue-900 hover:underline">
                                    Read announcement &rarr;
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Member Profile Details Grid -->
        <section class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 shadow-sm">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-slate-900">Profile Information</h2>
                <a href="id_card.php" class="text-xs font-semibold text-blue-900 hover:underline flex items-center gap-1">
                    View Membership Card &rarr;
                </a>
            </div>

            <dl class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 text-sm">
                <div>
                    <dt class="font-medium text-slate-500">Full Name</dt>
                    <dd class="mt-1 text-slate-900 font-semibold text-base"><?= e((string)$member['full_name']) ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Student ID / Index</dt>
                    <dd class="mt-1 font-mono text-slate-900 font-semibold text-base"><?= e((string)$member['index_number']) ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Email Address</dt>
                    <dd class="mt-1 text-slate-900 font-semibold"><?= e((string)$member['email']) ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Phone Number</dt>
                    <dd class="mt-1 text-slate-900 font-semibold"><?= e((string)$member['phone_number']) ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Department / Institution</dt>
                    <dd class="mt-1 text-slate-900 font-semibold"><?= e((string)$member['institution']) ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Program of Study</dt>
                    <dd class="mt-1 text-slate-900 font-semibold"><?= e((string)$member['program_of_study']) ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Level / Year</dt>
                    <dd class="mt-1 text-slate-900 font-semibold"><?= e((string)$member['level']) ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Region</dt>
                    <dd class="mt-1 text-slate-900 font-semibold"><?= e((string)$member['region']) ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Constituency</dt>
                    <dd class="mt-1 text-slate-900 font-semibold"><?= e((string)$member['constituency']) ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Date Registered</dt>
                    <dd class="mt-1 text-slate-700"><?= e(format_utc_date((string)$member['created_at'], 'M j, Y · g:i A')) ?> UTC</dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Last Status Update</dt>
                    <dd class="mt-1 text-slate-700"><?= e(format_utc_date((string)$member['updated_at'], 'M j, Y · g:i A')) ?> UTC</dd>
                </div>
            </dl>
        </section>
    </main>

    <?php require __DIR__ . '/../includes/member_footer.php'; ?>
</body>
</html>
