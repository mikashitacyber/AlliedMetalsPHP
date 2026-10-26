<?php
require __DIR__ . '/../config/config.php';
requireRole('Customer');
require __DIR__ . '/../includes/ticket_card.php';
$u = currentUser();

$status = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM inquiries WHERE user_id = ?";
$params = [$u['user_id']];
if ($status !== '') { $sql .= " AND status = ?"; $params[] = $status; }
if ($q !== '') { $sql .= " AND (ticket_no LIKE ? OR type LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$statusOrder = inquiryStatuses();

$pageTitle = 'Track My Orders';
require __DIR__ . '/../includes/header.php';
?>
<div class="shell">
    <?php $activeTab = 'tickets'; require __DIR__ . '/_sidebar.php'; ?>
    <main class="shell-main">
        <div class="shell-topbar">
            <div><h2>Track My Orders</h2><div class="sub">Follow your inquiries and orders from first review through answer or delivery.</div></div>
        </div>
        <div class="shell-body">
            <form method="get" class="filter-row">
                <?php if (($_GET['upload'] ?? '') === '1'): ?><input type="hidden" name="upload" value="1"><?php endif; ?>
                <select name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <?php foreach ($statusOrder as $s): ?>
                        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search my inquiries…">
                <button class="btn btn-outline btn-sm" type="submit">Filter</button>
            </form>

            <?php if (($_GET['upload'] ?? '') === '1'): ?>
                <div class="form-note" style="margin-bottom:16px;">
                    Select the request you want to add documents to. On its details page, use <b>Add documents to this inquiry</b>. Files stay attached to the correct request.
                </div>
            <?php endif; ?>
            <?php if (empty($tickets)): ?>
                <div class="empty">
                    <div class="ic">☰</div>
                    <?= $q !== '' || $status !== '' ? 'No inquiries match these filters.' : 'You do not have any requests yet. Create one before uploading related files.' ?>
                    <?php if ($q === '' && $status === ''): ?>
                        <p><a class="btn btn-primary btn-sm" href="new_inquiry.php">Create an Inquiry</a></p>
                    <?php endif; ?>
                </div>
            <?php else: foreach ($tickets as $t): renderTicketCard($t, "inquiry_detail.php?id={$t['inquiry_id']}"); endforeach; endif; ?>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
