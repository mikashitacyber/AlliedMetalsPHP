<?php
require __DIR__ . '/../config/config.php';
requireRole('Staff');
require __DIR__ . '/../includes/ticket_card.php';
$u = currentUser();

$statusOrder = inquiryStatuses();
$departments = ["Design & Engineering","Equipment Sales","Fabrication","Installation","Service & Maintenance","Customer Service"];

require __DIR__ . '/_actions.php';

$stmt = $pdo->prepare("
    SELECT i.*,
        u.full_name AS full_name,
        u.company AS company,
        s.full_name AS assigned_staff_name
    FROM inquiries i
    LEFT JOIN users u ON u.user_id = i.user_id
    LEFT JOIN users s ON s.user_id = i.assigned_staff_id
    WHERE i.assigned_staff_id = ?
      AND i.status NOT IN ('Answered', 'Delivered', 'Closed')
    ORDER BY i.created_at DESC
");
$stmt->execute([$u['user_id']]);
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

$pageTitle = 'Assigned to Me';
require __DIR__ . '/../includes/header.php';
?>
<div class="shell">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <main class="shell-main">
        <div class="shell-topbar">
            <div><h2>Assigned to Me</h2><div class="sub"><?= count($list) ?> tickets assigned to you by HR/Admin</div></div>
        </div>
        <div class="shell-body">
            <div class="list-detail">
                <div class="list-col">
                    <?php if (empty($list)): ?>
                        <div class="empty"><div class="ic">★</div>No tickets have been assigned to you yet.</div>
                    <?php else: foreach ($list as $t): $stamp = stampClassFor($t['status']); ?>
                        <a href="assigned.php?id=<?= $t['inquiry_id'] ?>" class="ticket <?= $t['inquiry_id']==$selectedId?'selected':'' ?>">
                            <div class="ticket-perf"></div>
                            <div class="ticket-top"><span class="ticket-id mono"><?= htmlspecialchars($t['ticket_no']) ?></span><span class="stamp <?= $stamp ?>"><?= htmlspecialchars($t['status']) ?></span></div>
                            <div class="ticket-body">
                                <h4><?= htmlspecialchars($t['full_name'] . ' — ' . $t['company']) ?></h4>
                                <div class="ticket-meta"><span><?= htmlspecialchars($t['type']) ?></span></div>
                                <div class="ticket-meta"><span>▤ <?= htmlspecialchars($t['department']) ?></span><span>🕒 <?= date('M j, Y', strtotime($t['created_at'])) ?></span><span class="pill priority-<?= $t['priority'] ?>"><?= $t['priority'] ?></span></div>
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
