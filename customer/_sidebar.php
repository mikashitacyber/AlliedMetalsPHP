<?php
// $activeTab should be set by the including page: overview | new | problem | tickets | documents | employees | profile.
$u = currentUser();
?>
<aside class="shell-sidebar">
    <div class="brand"><img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Allied Metals" class="brand-logo"><small class="brand-caption">Customer Portal</small></div>
    <nav class="shell-nav">
        <a href="<?= BASE_URL ?>/customer/dashboard.php" class="<?= $activeTab === 'overview' ? 'active' : '' ?>"><span class="ic">◧</span> Overview</a>
        <a href="<?= BASE_URL ?>/customer/new_inquiry.php" class="<?= $activeTab === 'new' ? 'active' : '' ?>"><span class="ic">✎</span> New Inquiry / Quotation</a>
        <a href="<?= BASE_URL ?>/customer/new_inquiry.php?type=portal_problem" class="<?= $activeTab === 'problem' ? 'active' : '' ?>"><span class="ic">⚠</span> Report a Portal Problem</a>
        <a href="<?= BASE_URL ?>/customer/my_inquiries.php" class="<?= $activeTab === 'tickets' ? 'active' : '' ?>"><span class="ic">☰</span> Track My Orders</a>
        <a href="<?= BASE_URL ?>/documents.php" class="<?= $activeTab === 'documents' ? 'active' : '' ?>"><span class="ic">▤</span> My Documents</a>
        <?php if ($activeTab !== 'overview'): ?>
            <button class="shell-nav-notifications" type="button" data-notification-toggle aria-expanded="false" aria-label="Notifications">
                <svg class="notification-bell-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                <span>Notifications</span>
                <span class="notification-count" data-notification-count hidden></span>
            </button>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/messages.php" class="<?= $activeTab === 'messages' ? 'active' : '' ?>"><span class="ic">✉</span> Messages</a>
        <div class="grp-label">Account</div>
        <a href="<?= BASE_URL ?>/customer/profile.php" class="<?= $activeTab === 'profile' ? 'active' : '' ?>"><span class="ic">⚙</span> Profile &amp; Company</a>
    </nav>
    <div class="sidebar-foot">
        <a class="user-chip-link" href="<?= BASE_URL ?>/customer/profile.php" title="Open my profile">
        <div class="user-chip">
            <div class="avatar"><?php if (!empty($_SESSION['user']['profile_picture_path'])): ?><img src="<?= BASE_URL ?>/profile_picture.php?id=<?= (int)$u['user_id'] ?>" alt=""><?php else: ?><?= htmlspecialchars(mb_substr($u['full_name'], 0, 1)) ?><?php endif; ?><span class="status-dot <?= statusDotClass($_SESSION['user']['manual_status'] ?? 'available') ?>"></span></div>
            <div><div><?= htmlspecialchars($u['full_name']) ?></div><div class="role"><?= htmlspecialchars($u['company']) ?></div></div>
        </div>
        </a>
        <a class="logout-link" href="<?= BASE_URL ?>/logout.php">← Sign out</a>
    </div>
</aside>
