<?php
$u = currentUser();
$path = basename($_SERVER['SCRIPT_NAME'], '.php');
?>
<aside class="shell-sidebar">
    <div class="brand"><img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Allied Metals" class="brand-logo"><small class="brand-caption">Staff Portal</small></div>
    <nav class="shell-nav">
        <a href="<?= BASE_URL ?>/staff/queue.php" class="<?= $path==='queue'?'active':'' ?>"><span class="ic">☰</span> Inquiry Queue</a>
        <a href="<?= BASE_URL ?>/staff/assigned.php" class="<?= $path==='assigned'?'active':'' ?>"><span class="ic">★</span> Assigned to Me</a>
        <a href="<?= BASE_URL ?>/employee_work.php" class="<?= $path==='employee_work'?'active':'' ?>"><span class="ic">⚒</span> My Work</a>
        <a href="<?= BASE_URL ?>/messages.php" class="<?= $path==='messages'?'active':'' ?>"><span class="ic">✉</span> Customer Messages</a>
        <?php if (($u['department'] ?? '') === 'Customer Service'): ?>
        <a href="<?= BASE_URL ?>/customer_report.php" class="<?= $path==='customer_report'?'active':'' ?>"><span class="ic">☺</span> Customer Report</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/completed_inquiries.php" class="<?= $path==='completed_inquiries'?'active':'' ?>"><span class="ic">✓</span> Completed Inquiries</a>
        <a href="<?= BASE_URL ?>/documents.php" class="<?= $path==='documents'?'active':'' ?>"><span class="ic">▤</span> Document Archive</a>
    </nav>
    <div class="sidebar-foot">
        <a class="user-chip-link" href="<?= BASE_URL ?>/staff/profile.php" title="Open my profile and set my status">
        <div class="user-chip">
            <div class="avatar"><?php if (!empty($_SESSION['user']['profile_picture_path'])): ?><img src="<?= BASE_URL ?>/profile_picture.php?id=<?= (int)$u['user_id'] ?>" alt=""><?php else: ?><?= htmlspecialchars(mb_substr($u['full_name'], 0, 1)) ?><?php endif; ?><span class="status-dot <?= statusDotClass($_SESSION['user']['manual_status'] ?? 'available') ?>"></span></div>
            <div><div><?= htmlspecialchars($u['full_name']) ?></div><div class="role"><?= htmlspecialchars($u['department'] ?? 'Staff') ?></div></div>
        </div>
        </a>
        <a class="logout-link" href="<?= BASE_URL ?>/logout.php">← Sign out</a>
    </div>
</aside>
