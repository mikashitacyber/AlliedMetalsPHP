<?php
require __DIR__ . '/../config/config.php';
requireRole('Customer');
require __DIR__ . '/../includes/ticket_card.php';
$u = currentUser();

$status = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');
$attention = ($_GET['attention'] ?? '') === 'quote' ? 'quote' : '';

$sql = "SELECT i.*, q.amount AS quote_amount, q.status AS quote_status, q.valid_until AS quote_valid_until
        FROM inquiries i
        LEFT JOIN inquiry_quotes q ON q.inquiry_id = i.inquiry_id
        WHERE i.user_id = ?";
$params = [$u['user_id']];
if ($status !== '') { $sql .= " AND i.status = ?"; $params[] = $status; }
if ($attention === 'quote') { $sql .= " AND q.status = 'offered' AND q.valid_until >= CURDATE()"; }
if ($q !== '') { $sql .= " AND (i.ticket_no LIKE ? OR i.type LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
$sql .= " ORDER BY i.created_at DESC";
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
                <select name="attention" aria-label="Filter requests needing action">
                    <option value="">All requests</option>
                    <option value="quote" <?= $attention === 'quote' ? 'selected' : '' ?>>Quotes needing response</option>
                </select>
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
                    <?= $q !== '' || $status !== '' || $attention !== '' ? 'No requests match these filters.' : 'You do not have any requests yet. Create one before uploading related files.' ?>
                    <?php if ($q === '' && $status === '' && $attention === ''): ?>
                        <p><a class="btn btn-primary btn-sm" href="new_inquiry.php">Create an Inquiry</a></p>
                    <?php endif; ?>
                </div>
            <?php else: foreach ($tickets as $t): renderTicketCard($t, "inquiry_detail.php?id={$t['inquiry_id']}"); endforeach; endif; ?>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
