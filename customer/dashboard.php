<?php
require __DIR__ . '/../config/config.php';
requireRole('Customer');
require __DIR__ . '/../includes/ticket_card.php';
$u = currentUser();

$stmt = $pdo->prepare("SELECT * FROM inquiries WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
$stmt->execute([$u['user_id']]);
$recent = $stmt->fetchAll();

$pageTitle = 'Overview';
require __DIR__ . '/../includes/header.php';
?>
<div class="shell">
    <?php $activeTab = 'overview'; require __DIR__ . '/_sidebar.php'; ?>
    <main class="shell-main">
        <div class="shell-topbar">
            <div><h2>Overview</h2><div class="sub">Welcome back, <?= htmlspecialchars($u['full_name']) ?>.</div></div>
            <div class="top-actions">
                <button class="notification-bell" type="button" data-notification-toggle aria-expanded="false" aria-label="Notifications">
                    <svg class="notification-bell-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                    <span class="notification-count" data-notification-count hidden></span>
                </button>
            </div>
        </div>
        <div class="shell-body">
            <div class="qa-grid">
                <a class="qa-card" href="new_inquiry.php?type=general"><div class="ic">✎</div><h4>Submit an Inquiry</h4><p>Ask a general question or submit a project or service request.</p></a>
                <a class="qa-card" href="new_inquiry.php?mode=quotation"><div class="ic">₱</div><h4>Request a Quotation</h4><p>Start a request with Equipment inquiry preselected.</p></a>
                <a class="qa-card" href="my_inquiries.php?upload=1"><div class="ic">⇪</div><h4>Upload Files</h4><p>Choose an existing request and attach its documents.</p></a>
                <a class="qa-card" href="my_inquiries.php"><div class="ic">☰</div><h4>Track My Orders</h4><p>Follow each request from review to delivery.</p></a>
                <a class="qa-card" href="new_inquiry.php?type=portal_problem"><div class="ic">⚠</div><h4>Report a Portal Problem</h4><p>Tell Customer Service about sign-in, upload, or website issues.</p></a>
            </div>
            <div class="panel">
                <div class="panel-head"><h3>Recent Inquiries</h3><a href="my_inquiries.php" style="font-size:12px;color:var(--signal-dark);font-weight:600;">View all →</a></div>
                <div class="panel-body">
                    <?php if (empty($recent)): ?>
                        <div class="empty"><div class="ic">☰</div>No inquiries yet.</div>
                    <?php else: foreach ($recent as $t): renderTicketCard($t, "inquiry_detail.php?id={$t['inquiry_id']}"); endforeach; endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
