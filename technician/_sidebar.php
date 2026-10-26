<?php
$u = currentUser();
$path = basename($_SERVER['SCRIPT_NAME'], '.php');
$showDone = ($_GET['show'] ?? '') === 'done';
?>
<aside class="shell-sidebar">
    <div class="brand"><img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Allied Metals" class="brand-logo"><small class="brand-caption">Field Portal</small></div>
    <nav class="shell-nav">
        <a href="<?= BASE_URL ?>/employee_work.php" class="<?= $path==='employee_work' && !$showDone ? 'active' : '' ?>"><span class="ic">★</span> My Work</a>
        <a href="<?= BASE_URL ?>/employee_work.php?show=done" class="<?= $path==='employee_work' && $showDone ? 'active' : '' ?>"><span class="ic">✓</span> Completed Work</a>
        <a href="<?= BASE_URL ?>/messages.php" class="<?= $path==='messages'?'active':'' ?>"><span class="ic">✉</span> Customer Messages</a>
    </nav>
    <div class="sidebar-foot">
        <a class="user-chip-link" href="<?= BASE_URL ?>/technician/profile.php" title="Open my profile and set my status">
        <div class="user-chip">
            <div class="avatar"><?php if (!empty($_SESSION['user']['profile_picture_path'])): ?><img src="<?= BASE_URL ?>/profile_picture.php?id=<?= (int)$u['user_id'] ?>" alt=""><?php else: ?><?= htmlspecialchars(mb_substr($u['full_name'], 0, 1)) ?><?php endif; ?><span class="status-dot <?= statusDotClass($_SESSION['user']['manual_status'] ?? 'available') ?>"></span></div>
            <div><div><?= htmlspecialchars($u['full_name']) ?></div><div class="role">Field Technician</div></div>
        </div>
        </a>
        <a class="logout-link" href="<?= BASE_URL ?>/logout.php">← Sign out</a>
    </div>
</aside>
