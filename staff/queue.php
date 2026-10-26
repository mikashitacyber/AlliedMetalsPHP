<?php
require __DIR__ . '/../config/config.php';
requireRole('Staff');
require __DIR__ . '/../includes/ticket_card.php';
$u = currentUser();

$statusOrder = inquiryStatuses();
$departments = ["Design & Engineering","Equipment Sales","Fabrication","Installation","Service & Maintenance","Customer Service"];

require __DIR__ . '/_actions.php'; // handles assign/status/reply/request-info POSTs, redirects back here

// ---------- filters ----------
$statusFilter = $_GET['status'] ?? '';
$deptFilter = $_GET['dept'] ?? '';
$q = trim($_GET['q'] ?? '');

$sql = "SELECT i.*,
            u.full_name AS full_name,
            u.company AS company,
            s.full_name AS assigned_staff_name
        FROM inquiries i
        LEFT JOIN users u ON u.user_id = i.user_id
        LEFT JOIN users s ON s.user_id = i.assigned_staff_id
        WHERE i.status NOT IN ('Answered', 'Delivered', 'Closed')";
$params = [];
if ($statusFilter !== '') { $sql .= " AND i.status = ?"; $params[] = $statusFilter; }
if ($deptFilter !== '') { $sql .= " AND i.department = ?"; $params[] = $deptFilter; }
if ($q !== '') {
    $sql .= " AND (i.ticket_no LIKE ? OR u.full_name LIKE ? OR u.company LIKE ?)";
    array_push($params, "%$q%", "%$q%", "%$q%");
}
$sql .= " ORDER BY i.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll();

$selectedId = (int)($_GET['id'] ?? ($list[0]['inquiry_id'] ?? 0));
$selected = null; $msgs = $atts = [];
foreach ($list as $t) { if ($t['inquiry_id'] == $selectedId) { $selected = $t; break; } }
if ($selected) {
    $m = $pdo->prepare("SELECT * FROM messages WHERE inquiry_id = ? ORDER BY message_id ASC");
    $m->execute([$selectedId]); $msgs = $m->fetchAll();
    $a = $pdo->prepare("SELECT a.*, uploader.full_name AS uploader_name FROM attachments a LEFT JOIN users uploader ON uploader.user_id = a.uploaded_by WHERE a.inquiry_id = ?");
    $a->execute([$selectedId]); $atts = $a->fetchAll();
}

$pageTitle = 'Inquiry Queue';
require __DIR__ . '/../includes/header.php';
?>
<div class="shell">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <main class="shell-main">
        <div class="shell-topbar">
            <div><h2>Inquiry Queue</h2><div class="sub"><?= count($list) ?> matching tickets</div></div>
        </div>
        <div class="shell-body">
            <div class="list-detail">
                <div class="list-col">
                    <form method="get" class="filter-row">
                        <select name="status" onchange="this.form.submit()">
                            <option value="">All statuses</option>
                            <?php foreach ($statusOrder as $s): ?><option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
                        </select>
                        <select name="dept" onchange="this.form.submit()">
                            <option value="">All departments</option>
                            <?php foreach ($departments as $d): ?><option value="<?= $d ?>" <?= $deptFilter===$d?'selected':'' ?>><?= $d ?></option><?php endforeach; ?>
                        </select>
                        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search customer, company, ticket #…">
                        <button class="btn btn-outline btn-sm" type="submit">Filter</button>
                    </form>

                    <?php if (empty($list)): ?>
                        <div class="empty"><div class="ic">☰</div>No tickets match these filters.</div>
                    <?php else: foreach ($list as $t):
                        $stamp = stampClassFor($t['status']);
                        $qs = http_build_query(['status'=>$statusFilter,'dept'=>$deptFilter,'q'=>$q,'id'=>$t['inquiry_id']]);
                        ?>
                        <a href="queue.php?<?= $qs ?>" class="ticket <?= $t['inquiry_id']==$selectedId?'selected':'' ?>">
                            <div class="ticket-perf"></div>
                            <div class="ticket-top"><span class="ticket-id mono"><?= htmlspecialchars($t['ticket_no']) ?></span><span class="stamp <?= $stamp ?>"><?= htmlspecialchars($t['status']) ?></span></div>
                            <div class="ticket-body">
                                <h4><?= htmlspecialchars($t['full_name'] . ' — ' . $t['company']) ?></h4>
                                <div class="ticket-meta"><span><?= htmlspecialchars($t['type']) ?></span></div>
                                <div class="ticket-meta">
                                    <span>▤ <?= htmlspecialchars($t['department']) ?></span>
                                    <span>🕒 <?= date('M j, Y', strtotime($t['created_at'])) ?></span>
                                    <span class="pill priority-<?= $t['priority'] ?>"><?= $t['priority'] ?></span>
                                    <?php if ($t['assigned_staff_name']): ?><span>👤 <?= htmlspecialchars($t['assigned_staff_name']) ?></span><?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; endif; ?>
                </div>

                <div class="detail-col">
                    <?php require __DIR__ . '/_ticket_detail_panel.php'; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
