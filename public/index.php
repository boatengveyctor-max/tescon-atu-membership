<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_middleware.php';

// Strict portal isolation: redirect admins and auditors away from public landing pages
if (in_array($_SESSION['role'] ?? '', ['admin', 'auditor'], true)) {
    header('Location: ' . portal_dashboard_url((string)$_SESSION['role']), true, 303);
    exit;
}

$leadersByCategory = [
    'npp_national' => [],
    'regional' => [],
    'tescon' => []
];

try {
    $statement = db()->query(
        "SELECT id, full_name, position, category, region, image_url, bio
         FROM leaders
         WHERE status = 'active'
         ORDER BY category, display_order, full_name"
    );

    foreach ($statement->fetchAll() as $leader) {
        if (isset($leadersByCategory[$leader['category']])) {
            $leadersByCategory[$leader['category']][] = $leader;
        }
    }
} catch (PDOException $e) {
    error_log('Homepage leadership data could not be loaded: ' . $e->getMessage());
}

$regions = [];
foreach ($leadersByCategory['regional'] as $leader) {
    $region = trim((string)($leader['region'] ?? ''));
    if ($region !== '') {
        $regions[$region] = $region;
    }
}
natcasesort($regions);

$sections = [
    'npp_national' => [
        'eyebrow' => 'National leadership',
        'title' => 'NPP National Executives',
        'description' => 'Meet the national leaders serving and organizing the party.'
    ],
    'regional' => [
        'eyebrow' => 'Leadership across Ghana',
        'title' => 'Regional Executives',
        'description' => 'Explore regional leadership and the communities they represent.'
    ],
    'tescon' => [
        'eyebrow' => 'Student leadership',
        'title' => 'TESCON  ATU Leadership',
        'description' => 'Discover the student leaders building a stronger network on campus.'
    ]
];

function leader_initials(string $fullName): string
{
    $parts = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = '';

    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }

    return $initials !== '' ? $initials : 'T';
}

function leader_image_url(?string $url): ?string
{
    $url = trim((string)$url);
    if ($url === '') {
        return null;
    }

    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
        return $url;
    }

    if (filter_var($url, FILTER_VALIDATE_URL) && strtolower((string)parse_url($url, PHP_URL_SCHEME)) === 'https') {
        return $url;
    }

    return null;
}

$pageTitle = 'Connect. Lead. Serve.';
require __DIR__ . '/../includes/header.php';
?>

<section class="relative isolate overflow-hidden rounded-3xl bg-blue-950 text-white shadow-xl">
    <div class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top_right,_rgba(185,28,28,0.42),_transparent_42%),linear-gradient(120deg,_#172554,_#0f1d43_58%,_#1e3a8a)]"></div>
    <div class="absolute -right-20 -top-24 -z-10 h-72 w-72 rounded-full border border-white/10"></div>
    <div class="absolute -right-8 -top-12 -z-10 h-48 w-48 rounded-full border border-white/10"></div>
    <div class="grid gap-10 px-6 py-14 sm:px-10 sm:py-16 lg:grid-cols-[1.2fr_0.8fr] lg:items-center lg:px-14 lg:py-20">
        <div class="max-w-2xl">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-blue-100">
                <span class="h-2 w-2 rounded-full bg-red-500"></span>
                TESCON Membership
            </span>
            <h1 class="mt-6 text-4xl font-black leading-tight tracking-tight sm:text-5xl lg:text-6xl">
                Connect.<br>
                <span class="text-red-400">Lead.</span> Serve.
            </h1>
            <p class="mt-5 max-w-xl text-base leading-7 text-blue-100 sm:text-lg">
                Find your community, develop your leadership, and make a difference through the Tertiary Students Confederacy.
            </p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <a href="register.php" class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-700 px-6 py-3.5 font-bold text-white shadow-lg shadow-red-950/20 hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-blue-950">
                    Apply for membership
                    <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.69L10.22 5.03a.75.75 0 1 1 1.06-1.06l5.5 5.5a.75.75 0 0 1 0 1.06l-5.5 5.5a.75.75 0 1 1-1.06-1.06l4.22-4.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd"/>
                    </svg>
                </a>
                <a href="login.php" class="inline-flex items-center justify-center rounded-xl border border-white/25 px-6 py-3.5 font-semibold text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white">
                    Member sign in
                </a>
                <a href="#leadership" class="inline-flex items-center justify-center rounded-xl border border-white/25 px-6 py-3.5 font-semibold text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white">
                    Explore leadership
                </a>
            </div>
        </div>
        <div class="hidden lg:block">
            <div class="mx-auto max-w-sm rounded-3xl border border-white/15 bg-white/10 p-7 shadow-2xl backdrop-blur-sm">
                <div class="flex items-center justify-between">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-700 text-xl font-black">T</div>
                    <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-blue-100">One community</span>
                </div>
                <p class="mt-8 text-2xl font-bold leading-snug">A network built on service, purpose, and possibility.</p>
                <div class="mt-8 flex items-center gap-3 border-t border-white/15 pt-5">
                    <span class="h-1.5 flex-1 rounded-full bg-red-500"></span>
                    <span class="h-1.5 flex-1 rounded-full bg-white/30"></span>
                    <span class="h-1.5 flex-1 rounded-full bg-white/30"></span>
                </div>
                <p class="mt-3 text-sm text-blue-100">Your next step starts here.</p>
            </div>
        </div>
    </div>
</section>

<section id="leadership" class="scroll-mt-8 pb-4 pt-16 sm:pt-20">
    <div class="mx-auto mb-10 max-w-2xl text-center">
        <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-red-700">The people who serve</p>
        <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">Leadership directory</h2>
        <p class="mt-3 leading-7 text-slate-600">Get to know the teams guiding the party and TESCON community.</p>
    </div>

    <div class="space-y-16">
        <?php foreach ($sections as $category => $section): ?>
            <?php $leaders = $leadersByCategory[$category]; ?>
            <section aria-labelledby="heading-<?= e($category) ?>" class="leadership-section">
                <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-red-700"><?= e($section['eyebrow']) ?></p>
                        <h3 id="heading-<?= e($category) ?>" class="mt-2 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl"><?= e($section['title']) ?></h3>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600"><?= e($section['description']) ?></p>
                    </div>
                    <?php if ($category === 'regional' && $regions): ?>
                        <div class="region-filters flex gap-2 overflow-x-auto pb-1" role="group" aria-label="Filter regional executives by region">
                            <button type="button" class="region-filter is-active" data-region-filter="all" aria-pressed="true">All regions</button>
                            <?php foreach ($regions as $region): ?>
                                <button type="button" class="region-filter" data-region-filter="<?= e($region) ?>" aria-pressed="false"><?= e($region) ?></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($leaders): ?>
                    <div class="leader-carousel" data-carousel data-autoplay="7000" aria-label="<?= e($section['title']) ?>" aria-roledescription="carousel">
                        <div class="leader-carousel-viewport">
                            <div class="leader-carousel-track" data-carousel-track>
                                <?php foreach ($leaders as $leader): ?>
                                    <?php $imageUrl = leader_image_url($leader['image_url'] ?? null); ?>
                                    <article class="leader-carousel-card" data-carousel-card<?= $category === 'regional' ? ' data-region="' . e((string)($leader['region'] ?? '')) . '"' : '' ?>>
                                        <div class="leader-card-inner">
                                            <div class="leader-card-topline"></div>
                                            <?php if ($imageUrl !== null): ?>
                                                <img src="<?= e($imageUrl) ?>" alt="" loading="lazy" class="leader-avatar">
                                            <?php else: ?>
                                                <div class="leader-avatar leader-avatar-initials" aria-hidden="true"><?= e(leader_initials((string)$leader['full_name'])) ?></div>
                                            <?php endif; ?>
                                            <p class="mt-5 text-xs font-extrabold uppercase tracking-[0.14em] text-red-700"><?= e((string)$leader['position']) ?></p>
                                            <h4 class="mt-2 text-xl font-bold leading-snug text-slate-900"><?= e((string)$leader['full_name']) ?></h4>
                                            <?php if ($category === 'regional' && !empty($leader['region'])): ?>
                                                <p class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500">
                                                    <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M9.69 18.933 9.7 18.94l.012.006.004.002a.75.75 0 0 0 .568 0l.004-.002.012-.006.01-.007.036-.022a14.42 14.42 0 0 0 .568-.385 23.16 23.16 0 0 0 1.556-1.21c.97-.824 2.14-1.976 3.12-3.37C16.57 12.544 17.5 10.82 17.5 9a7.5 7.5 0 0 0-15 0c0 1.82.93 3.544 1.91 4.946.98 1.394 2.15 2.546 3.12 3.37a23.16 23.16 0 0 0 1.556 1.21 14.42 14.42 0 0 0 .568.385l.036.022ZM10 11.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" clip-rule="evenodd"/>
                                                    </svg>
                                                    <?= e((string)$leader['region']) ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if (!empty($leader['bio'])): ?>
                                                <p class="mt-4 text-sm leading-6 text-slate-600"><?= e((string)$leader['bio']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="mt-5 flex items-center justify-between gap-4">
                            <div class="carousel-dots" data-carousel-dots aria-label="Choose carousel page"></div>
                            <div class="flex gap-2">
                                <button type="button" class="carousel-control" data-carousel-prev aria-label="Previous leaders">
                                    <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.75 10a.75.75 0 0 1-.75.75H5.31l4.22 4.22a.75.75 0 1 1-1.06 1.06l-5.5-5.5a.75.75 0 0 1 0-1.06l5.5-5.5a.75.75 0 1 1 1.06 1.06L5.31 9.25H16a.75.75 0 0 1 .75.75Z" clip-rule="evenodd"/></svg>
                                </button>
                                <button type="button" class="carousel-control" data-carousel-next aria-label="Next leaders">
                                    <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.25 10a.75.75 0 0 1 .75-.75h10.69l-4.22-4.22a.75.75 0 1 1 1.06-1.06l5.5 5.5a.75.75 0 0 1 0 1.06l-5.5 5.5a.75.75 0 1 1-1.06-1.06l4.22-4.22H4a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-900">
                            <svg aria-hidden="true" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 18.5a6.5 6.5 0 0 0-13 0M9.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm12 7.5a6.5 6.5 0 0 0-5-6.32m1-9.9a4 4 0 0 1 0 7.44"/>
                            </svg>
                        </div>
                        <h4 class="mt-4 font-bold text-slate-900">Leadership profiles coming soon</h4>
                        <p class="mx-auto mt-1 max-w-lg text-sm leading-6 text-slate-600">Profiles for this team will appear here once they have been published.</p>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
</section>

<section class="my-14 overflow-hidden rounded-2xl bg-red-800 px-6 py-9 text-white sm:px-10">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-red-200">Be part of the movement</p>
            <h2 class="mt-2 text-2xl font-black sm:text-3xl">Your voice. Your community. Your future.</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-red-100">Take the next step and apply to join the TESCON ATU community.</p>
        </div>
        <a href="register.php" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-white px-5 py-3 font-bold text-red-800 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-red-800">Apply now</a>
    </div>
</section>

<script src="<?= e(APP_BASE_URL) ?>/assets/js/carousel.js" defer></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
