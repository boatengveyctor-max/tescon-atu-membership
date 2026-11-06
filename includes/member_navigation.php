<?php
declare(strict_types=1);

$activeMemberPage = $activeMemberPage ?? '';
$memberDisplayName = (string)($_SESSION['username'] ?? 'Member');
?>
<header class="member-header sticky top-0 z-30 no-print">
    <div class="member-header-inner">
        <a class="member-brand" href="dashboard.php">
            TESCON <span>ATU</span>
        </a>

        <nav class="member-nav" aria-label="Member portal">
            <a href="dashboard.php" <?= $activeMemberPage === 'dashboard' ? 'aria-current="page"' : '' ?>>Dashboard</a>
            <a href="id_card.php" <?= $activeMemberPage === 'id-card' ? 'aria-current="page"' : '' ?>>ID Card</a>
            <a href="announcements.php" <?= $activeMemberPage === 'announcements' ? 'aria-current="page"' : '' ?>>Announcements</a>
        </nav>

        <div class="member-account">
            <span class="member-account-name"><?= e($memberDisplayName) ?></span>
            <form method="post" action="logout.php">
                <?= csrf_field() ?>
                <button type="submit">Sign out</button>
            </form>
        </div>
    </div>
</header>
