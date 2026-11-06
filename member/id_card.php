<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/member_helpers.php';
require_once __DIR__ . '/../includes/formatters.php';
require_once __DIR__ . '/../includes/audit_logger.php';
require_once __DIR__ . '/../includes/member_uploads.php';

$member = require_member_profile();
$pdo = db();
$status = (string)($member['status'] ?? 'pending');
$format = input_string($_GET, 'format', 8);

// Handle vector SVG download directly
if ($format === 'svg') {
    if ($status !== 'approved') {
        http_response_code(403);
        exit('Official ID cards can only be downloaded once your application is approved.');
    }

    try {
        log_audit_event(
            $pdo,
            (int)$member['id'],
            'member',
            'DOWNLOAD_ID_CARD',
            'members',
            (int)$member['id'],
            null,
            ['format' => 'svg', 'index_number' => $member['index_number']]
        );
    } catch (Throwable $e) {
        error_log('ID card download audit log failure: ' . $e->getMessage());
    }

    $fullName = htmlspecialchars((string)$member['full_name'], ENT_XML1, 'UTF-8');
    $indexNumber = htmlspecialchars((string)$member['index_number'], ENT_XML1, 'UTF-8');
    $institution = htmlspecialchars((string)$member['institution'], ENT_XML1, 'UTF-8');
    $program = htmlspecialchars((string)$member['program_of_study'], ENT_XML1, 'UTF-8');
    $level = htmlspecialchars((string)$member['level'], ENT_XML1, 'UTF-8');
    $region = htmlspecialchars((string)$member['region'], ENT_XML1, 'UTF-8');
    $joinedYear = format_utc_date((string)$member['created_at'], 'Y');
    $expireYear = (int)$joinedYear + 3;
    $photoDataUri = member_verification_photo_data_uri($member['verification_photo'] ?? null);
    $photoSvg = $photoDataUri === null
        ? ''
        : '<image href="' . htmlspecialchars($photoDataUri, ENT_XML1, 'UTF-8') . '" x="0" y="0" width="160" height="200" preserveAspectRatio="xMidYMid slice"/>';
    $photoFallbackSvg = $photoDataUri === null
        ? '<circle cx="80" cy="75" r="40" fill="#334155"/><path d="M 25 180 C 25 130, 135 130, 135 180 Z" fill="#334155"/>'
        : '';

    $filename = 'TESCON-ID-' . preg_replace('/[^A-Za-z0-9_-]/', '-', (string)$member['index_number']) . '.svg';

    header('Content-Type: image/svg+xml');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 856 540" width="856" height="540">
  <defs>
    <linearGradient id="bgGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#0a192f"/>
      <stop offset="50%" stop-color="#172554"/>
      <stop offset="100%" stop-color="#1e1b4b"/>
    </linearGradient>
    <linearGradient id="nppRed" x1="0%" y1="0%" x2="100%" y2="0%">
      <stop offset="0%" stop-color="#dc2626"/>
      <stop offset="100%" stop-color="#b91c1c"/>
    </linearGradient>
    <linearGradient id="goldHolo" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#fef08a"/>
      <stop offset="50%" stop-color="#eab308"/>
      <stop offset="100%" stop-color="#ca8a04"/>
    </linearGradient>
    <clipPath id="memberPhotoClip"><rect x="0" y="0" width="160" height="200" rx="14"/></clipPath>
  </defs>

  <!-- Background Card -->
  <rect x="0" y="0" width="856" height="540" rx="28" fill="url(#bgGrad)"/>

  <!-- Top Tricolor NPP Header Stripes -->
  <rect x="0" y="0" width="856" height="14" rx="0" fill="#1d4ed8"/>
  <rect x="0" y="14" width="856" height="12" fill="#ffffff"/>
  <rect x="0" y="26" width="856" height="14" fill="#dc2626"/>

  <!-- Watermark Logo Outline -->
  <circle cx="680" cy="300" r="180" fill="none" stroke="rgba(255,255,255,0.04)" stroke-width="28"/>
  <circle cx="680" cy="300" r="120" fill="none" stroke="rgba(255,255,255,0.03)" stroke-width="12"/>

  <!-- Header Text -->
  <text x="50" y="80" fill="#ffffff" font-family="system-ui, -apple-system, sans-serif" font-size="24" font-weight="900" letter-spacing="3">TERTIARY STUDENTS CONFEDERACY</text>
  <text x="50" y="108" fill="#93c5fd" font-family="system-ui, -apple-system, sans-serif" font-size="16" font-weight="700" letter-spacing="1">ACCRA TECHNICAL UNIVERSITY (ATU) CHAPTER</text>
  <text x="50" y="128" fill="#fca5a5" font-family="system-ui, -apple-system, sans-serif" font-size="11" font-weight="700" letter-spacing="2">OFFICIAL MEMBERSHIP IDENTITY CARD</text>

  <!-- Security Hologram Badge -->
  <g transform="translate(710, 55)">
    <circle cx="50" cy="50" r="42" fill="url(#goldHolo)" opacity="0.9"/>
    <circle cx="50" cy="50" r="36" fill="none" stroke="#713f12" stroke-width="2" stroke-dasharray="4 2"/>
    <text x="50" y="46" fill="#713f12" font-family="system-ui, sans-serif" font-size="9" font-weight="900" text-anchor="middle">TESCON</text>
    <text x="50" y="60" fill="#713f12" font-family="system-ui, sans-serif" font-size="8" font-weight="800" text-anchor="middle">VERIFIED</text>
  </g>

  <!-- Photo / Avatar Box -->
  <g transform="translate(50, 160)">
    <rect x="0" y="0" width="160" height="200" rx="16" fill="#1e293b" stroke="#3b82f6" stroke-width="3"/>
    <g clip-path="url(#memberPhotoClip)">{$photoSvg}</g>
    {$photoFallbackSvg}
    <rect x="10" y="210" width="140" height="24" rx="12" fill="#10b981"/>
    <text x="80" y="226" fill="#ffffff" font-family="system-ui, sans-serif" font-size="11" font-weight="800" text-anchor="middle">ACTIVE MEMBER</text>
  </g>

  <!-- Member Data -->
  <g transform="translate(240, 175)">
    <text x="0" y="0" fill="#94a3b8" font-family="system-ui, sans-serif" font-size="12" font-weight="600" text-transform="uppercase">Full Name</text>
    <text x="0" y="30" fill="#ffffff" font-family="system-ui, sans-serif" font-size="26" font-weight="900">{$fullName}</text>

    <text x="0" y="70" fill="#94a3b8" font-family="system-ui, sans-serif" font-size="12" font-weight="600" text-transform="uppercase">Student ID / Index No.</text>
    <text x="0" y="98" fill="#60a5fa" font-family="monospace" font-size="22" font-weight="800">{$indexNumber}</text>

    <text x="0" y="135" fill="#94a3b8" font-family="system-ui, sans-serif" font-size="12" font-weight="600" text-transform="uppercase">Program of Study</text>
    <text x="0" y="158" fill="#f8fafc" font-family="system-ui, sans-serif" font-size="16" font-weight="700">{$program}</text>

    <g transform="translate(0, 185)">
      <text x="0" y="0" fill="#94a3b8" font-family="system-ui, sans-serif" font-size="11" font-weight="600" text-transform="uppercase">Level</text>
      <text x="0" y="20" fill="#ffffff" font-family="system-ui, sans-serif" font-size="14" font-weight="700">{$level}</text>

      <text x="120" y="0" fill="#94a3b8" font-family="system-ui, sans-serif" font-size="11" font-weight="600" text-transform="uppercase">Region</text>
      <text x="120" y="20" fill="#ffffff" font-family="system-ui, sans-serif" font-size="14" font-weight="700">{$region}</text>

      <text x="260" y="0" fill="#94a3b8" font-family="system-ui, sans-serif" font-size="11" font-weight="600" text-transform="uppercase">Valid Thru</text>
      <text x="260" y="20" fill="#f59e0b" font-family="system-ui, sans-serif" font-size="14" font-weight="800">12/{$expireYear}</text>
    </g>
  </g>

  <!-- Bottom Strip & Barcode Simulator -->
  <rect x="0" y="470" width="856" height="70" rx="0" fill="#0f172a"/>
  <rect x="0" y="526" width="856" height="14" rx="0" fill="#dc2626"/>

  <text x="50" y="505" fill="#64748b" font-family="monospace" font-size="13" letter-spacing="4">||| | ||||| || |||| ||| |||| || ||||| ||| ||||</text>
  <text x="50" y="520" fill="#94a3b8" font-family="monospace" font-size="10">AUTH: TESCON-GH-ATU-{$indexNumber}</text>

  <text x="806" y="510" fill="#94a3b8" font-family="system-ui, sans-serif" font-size="11" text-anchor="end">ISSUED BY TESCON ATU EXECUTIVE COMMITTEE</text>
</svg>
SVG;
    exit;
}

$pageTitle = 'Digital Membership Card';
$activeMemberPage = 'id-card';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Digital Membership Card · TESCON ATU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= e(APP_BASE_URL) ?>/assets/css/style.css?v=aurora-light-1">
</head>
<body class="member-portal member-id-card min-h-screen flex flex-col justify-between">
    <?php require __DIR__ . '/../includes/member_navigation.php'; ?>

    <main class="max-w-5xl mx-auto px-4 py-8 flex-1 w-full">
        <!-- Top Action Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8 no-print">
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900">Official Membership Card</h1>
                <p class="text-slate-600 text-sm mt-1">Your verified digital credential for meetings, elections, and chapter participation.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <?php if ($status === 'approved'): ?>
                    <button id="btnDownloadPng" data-png-filename="TESCON-ATU-ID-<?= e(preg_replace('/[^A-Za-z0-9_-]/', '-', (string)$member['index_number'])) ?>.png" type="button" class="inline-flex items-center gap-2 bg-blue-900 text-white px-4 py-2.5 rounded-xl font-semibold text-sm hover:bg-blue-800 transition shadow-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Download PNG
                    </button>
                    <a href="id_card.php?format=svg" class="inline-flex items-center gap-2 bg-slate-800 text-white px-4 py-2.5 rounded-xl font-semibold text-sm hover:bg-slate-700 transition shadow-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        Download SVG
                    </a>
                    <button data-print-card type="button" class="inline-flex items-center gap-2 bg-white border border-slate-300 text-slate-800 px-4 py-2.5 rounded-xl font-semibold text-sm hover:bg-slate-50 transition shadow-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Print Card
                    </button>
                    <p id="cardActionStatus" class="w-full text-sm font-bold" role="status" aria-live="polite"></p>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 text-amber-900 border border-amber-300 px-3.5 py-1.5 text-xs font-bold">
                        Card Download Available Upon Approval
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($status !== 'approved'): ?>
            <div class="mb-8 rounded-2xl bg-amber-50 border border-amber-200 p-6 no-print">
                <div class="flex items-start gap-4">
                    <span class="p-2 bg-amber-100 rounded-xl text-amber-800">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Application Under Verification</h2>
                        <p class="text-sm text-slate-600 mt-1">
                            Your membership status is currently <strong><?= e(ucfirst($status)) ?></strong>. Below is a preview of your membership identification document. The official card will be validated for export once branch executives approve your application.
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ID Card Visual Container -->
        <div class="grid lg:grid-cols-2 gap-8 items-start">
            <!-- Front of Card -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 no-print">Front Side</h3>
                <div id="cardFront" class="print-card-container aspect-[856/540] w-full rounded-2xl bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 text-white p-6 sm:p-7 shadow-2xl relative overflow-hidden flex flex-col justify-between border border-slate-800">
                    <!-- Tricolor top ribbon -->
                    <div class="absolute top-0 left-0 right-0 h-2 bg-blue-600"></div>
                    <div class="absolute top-2 left-0 right-0 h-1.5 bg-white"></div>
                    <div class="absolute top-3.5 left-0 right-0 h-2 bg-red-600"></div>

                    <!-- Watermark Emblem -->
                    <div class="absolute -right-16 -bottom-16 w-64 h-64 rounded-full border-8 border-white/5 pointer-events-none flex items-center justify-center">
                        <div class="w-48 h-48 rounded-full border-4 border-white/5"></div>
                    </div>

                    <!-- Header -->
                    <div class="pt-3 flex justify-between items-start">
                        <div>
                            <p class="text-[10px] sm:text-xs font-black tracking-widest text-white uppercase">TERTIARY STUDENTS CONFEDERACY</p>
                            <p class="text-xs sm:text-sm font-bold text-blue-300">ACCRA TECHNICAL UNIVERSITY CHAPTER</p>
                            <p class="text-[9px] sm:text-[10px] font-bold text-red-400 tracking-wider uppercase">Official Membership Identity Card</p>
                        </div>

                        <!-- Hologram Stamp -->
                        <div class="shrink-0 h-12 w-12 sm:h-14 sm:w-14 rounded-full bg-gradient-to-tr from-yellow-500 via-amber-300 to-yellow-600 p-0.5 shadow-md flex items-center justify-center text-center">
                            <div class="h-full w-full rounded-full border border-amber-800/40 flex flex-col items-center justify-center">
                                <span class="text-[8px] font-black text-amber-950 leading-none">TESCON</span>
                                <span class="text-[7px] font-bold text-amber-900 leading-none mt-0.5">VALID</span>
                            </div>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="flex items-center gap-4 sm:gap-6 my-2">
                        <!-- Member photo, with a neutral fallback for applications without one -->
                        <div class="shrink-0 h-24 w-20 sm:h-28 sm:w-24 rounded-xl bg-slate-800 border-2 <?= $status === 'approved' ? 'border-emerald-500' : 'border-amber-500' ?> flex flex-col items-center justify-center text-slate-300 shadow-inner relative overflow-hidden">
                            <?php if (!empty($member['verification_photo'])): ?>
                                <img
                                    src="photo.php"
                                    alt="<?= e((string)$member['full_name']) ?>"
                                    class="absolute inset-0 h-full w-full object-cover"
                                >
                            <?php else: ?>
                                <svg class="h-12 w-12 text-slate-500 mt-2" fill="currentColor" viewBox="0 0 24 24">
                                    <path fill-rule="evenodd" d="M18.685 19.097A9.723 9.723 0 0021.75 12c0-5.385-4.365-9.75-9.75-9.75S2.25 6.615 2.25 12a9.723 9.723 0 003.065 7.097A9.716 9.716 0 0012 21.75a9.716 9.716 0 006.685-2.653zm-12.54-1.285A7.486 7.486 0 0112 15a7.486 7.486 0 015.855 2.812A8.224 8.224 0 0112 20.25a8.224 8.224 0 01-5.855-2.438A7.486 7.486 0 0112 15zM15.75 9a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" clip-rule="evenodd" />
                                </svg>
                            <?php endif; ?>
                            <span class="absolute bottom-0 inset-x-0 py-0.5 text-[8px] font-black uppercase text-center text-white <?= $status === 'approved' ? 'bg-emerald-600' : 'bg-amber-600' ?>">
                                <?= e(strtoupper($status)) ?>
                            </span>
                        </div>

                        <!-- Cardholder details -->
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-slate-400 uppercase font-semibold">Member Name</p>
                            <p class="text-base sm:text-xl font-black text-white truncate"><?= e((string)$member['full_name']) ?></p>

                            <p class="text-[11px] text-slate-400 uppercase font-semibold mt-1.5">Student ID / Index</p>
                            <p class="text-sm sm:text-base font-mono font-bold text-blue-300"><?= e((string)$member['index_number']) ?></p>

                            <div class="grid grid-cols-2 gap-2 mt-1.5 text-[10px] sm:text-xs text-slate-300">
                                <div>
                                    <span class="text-slate-400 block font-medium">Program</span>
                                    <span class="font-semibold truncate block"><?= e((string)$member['program_of_study']) ?></span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block font-medium">Level</span>
                                    <span class="font-semibold block"><?= e((string)$member['level']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="pt-2 border-t border-white/10 flex justify-between items-center text-[10px] sm:text-xs text-slate-400">
                        <span>Issued: <?= e(format_utc_date((string)$member['created_at'], 'M Y')) ?></span>
                        <span class="font-mono text-[9px] tracking-wider text-slate-300">GH-ATU-<?= substr(hash('sha256', (string)$member['index_number']), 0, 8) ?></span>
                        <span class="text-blue-300 font-semibold"><?= e((string)$member['region']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Back of Card -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 no-print">Back Side</h3>
                <div id="cardBack" class="print-card-container aspect-[856/540] w-full rounded-2xl bg-white border border-slate-300 p-6 sm:p-7 shadow-xl relative overflow-hidden flex flex-col justify-between text-slate-800">
                    <!-- Top magnetic strip representation -->
                    <div class="absolute top-4 left-0 right-0 h-10 bg-slate-900"></div>

                    <div class="pt-12 text-[10px] sm:text-xs leading-relaxed text-slate-600">
                        <p class="font-bold text-slate-900 text-xs mb-1">TERMS & CONDITIONS OF MEMBERSHIP</p>
                        <p>1. This card is the property of the Tertiary Students Confederacy (TESCON) Accra Technical University Chapter.</p>
                        <p class="mt-1">2. This credential is non-transferable and must be presented at branch congresses, meetings, and official gatherings.</p>
                        <p class="mt-1">3. If found, please return to the TESCON Secretariat, Accra Technical University, or contact branch leadership.</p>
                    </div>

                    <!-- Signature & Barcode Simulation -->
                    <div class="pt-3 border-t border-slate-200 flex justify-between items-end">
                        <div>
                            <p class="font-mono text-xs sm:text-sm tracking-widest text-slate-800 font-bold">|||| | || ||||| || ||| |||| || |||</p>
                            <p class="text-[9px] font-mono text-slate-400 mt-0.5"><?= e((string)$member['index_number']) ?></p>
                        </div>

                        <div class="text-right">
                            <div class="h-6 w-24 border-b border-slate-400 italic font-serif text-slate-700 text-xs flex items-end justify-center">
                                Branch Sec.
                            </div>
                            <p class="text-[9px] uppercase font-bold text-slate-500 mt-0.5">Authorised Signature</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hidden canvas for rendering image export -->
        <canvas id="exportCanvas" class="hidden"></canvas>
    </main>

    <?php require __DIR__ . '/../includes/member_footer.php'; ?>

    <script src="<?= e(APP_BASE_URL) ?>/assets/js/member-id-card.js" defer></script>
</body>
</html>
