<?php
$u = currentUser();
$path = basename($_SERVER['SCRIPT_NAME'], '.php');
?>
<aside class="shell-sidebar">
    <div class="brand"><img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Allied Metals" class="brand-logo"><small class="brand-caption">Admin Portal</small></div>
    <nav class="shell-nav">
        <a href="<?= BASE_URL ?>/admin/overview.php" class="<?= $path==='overview'?'active':'' ?>"><span class="ic">◧</span> Overview &amp; Reports</a>
        <a href="<?= BASE_URL ?>/mis_report.php" class="<?= $path==='mis_report'?'active':'' ?>"><span class="ic">▤</span> MIS Report</a>
        <a href="<?= BASE_URL ?>/dss_dashboard.php" class="<?= $path==='dss_dashboard'?'active':'' ?>"><span class="ic">◈</span> Decision Support</a>
        <a href="<?= BASE_URL ?>/employee_tasks.php" class="<?= $path==='employee_tasks'?'active':'' ?>"><span class="ic">⚒</span> Work Assignments</a>
        <a href="<?= BASE_URL ?>/customer_report.php" class="<?= $path==='customer_report'?'active':'' ?>"><span class="ic">☺</span> Customer Report</a>
        <a href="<?= BASE_URL ?>/admin/users.php" class="<?= $path==='users'?'active':'' ?>"><span class="ic">☺</span> Manage Users</a>
        <a href="<?= BASE_URL ?>/messages.php" class="<?= $path==='messages'?'active':'' ?>"><span class="ic">✉</span> Customer Messages</a>
        <a href="<?= BASE_URL ?>/admin/staff.php" class="<?= $path==='staff'?'active':'' ?>"><span class="ic">⚒</span> Manage Staff</a>
        <a href="<?= BASE_URL ?>/completed_inquiries.php" class="<?= $path==='completed_inquiries'?'active':'' ?>"><span class="ic">✓</span> Completed Inquiries</a>
        <a href="<?= BASE_URL ?>/documents.php" class="<?= $path==='documents'?'active':'' ?>"><span class="ic">▤</span> Document Archive</a>
    </nav>
    <div class="sidebar-foot">
        <a class="user-chip-link" href="<?= BASE_URL ?>/admin/profile.php" title="Open my profile and set my status">
        <div class="user-chip">
            <div class="avatar"><?php if (!empty($_SESSION['user']['profile_picture_path'])): ?><img src="<?= BASE_URL ?>/profile_picture.php?id=<?= (int)$u['user_id'] ?>" alt=""><?php else: ?><?= htmlspecialchars(mb_substr($u['full_name'], 0, 1)) ?><?php endif; ?><span class="status-dot <?= statusDotClass($_SESSION['user']['manual_status'] ?? 'available') ?>"></span></div>
            <div><div><?= htmlspecialchars($u['full_name']) ?></div><div class="role">Administrator</div></div>
        </div>
        </a>
        <a class="logout-link" href="<?= BASE_URL ?>/logout.php">← Sign out</a>
    </div>
</aside>
