<?php
require __DIR__ . '/../config/config.php';
requireRole('HR');
require __DIR__ . '/../includes/ticket_card.php';
require_once __DIR__ . '/../includes/notifications.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inquiry_id'])) {
    $id = (int)$_POST['inquiry_id'];
    $status = $pdo->prepare('SELECT status, assigned_staff_id, user_id, ticket_no FROM inquiries WHERE inquiry_id = ?');
    $status->execute([$id]);
    $inquiry = $status->fetch();
    if (!$inquiry || isInquiryComplete($inquiry['status'])) {
        $_SESSION['flash'] = !$inquiry
            ? 'This inquiry could not be found.'
            : 'This inquiry is complete and can no longer be assigned.';
    } else {
        $staffId = is_string($_POST['staff_id'] ?? null) && $_POST['staff_id'] !== '' ? (int)$_POST['staff_id'] : null;
        $pdo->prepare("UPDATE inquiries SET assigned_staff_id = ? WHERE inquiry_id = ?")->execute([$staffId, $id]);
        if ($staffId !== null && (int)$inquiry['assigned_staff_id'] !== $staffId) {
            createUserNotification(
                $pdo,
                (int)$inquiry['user_id'],
                'Staff assigned to your request',
                "A staff member was assigned to request {$inquiry['ticket_no']}.",
                BASE_URL . '/customer/inquiry_detail.php?id=' . $id
            );
        }
        $_SESSION['flash'] = $staffId ? "Ticket assigned." : "Assignment cleared.";
    }
    header('Location: dashboard.php?' . http_build_query($_GET));
    exit;
}

$deptFilter = $_GET['dept'] ?? '';
$unassignedOnly = isset($_GET['unassigned']);

$staffList = $pdo->query("SELECT user_id, full_name, department, last_active_at, manual_status FROM users WHERE role = 'Staff' AND is_active = 1 ORDER BY full_name")->fetchAll();

$sql = "SELECT i.*,
            u.full_name AS full_name,
            u.company AS company,
            s.full_name AS assigned_staff_name
        FROM inquiries i
        LEFT JOIN users u ON u.user_id = i.user_id
        LEFT JOIN users s ON s.user_id = i.assigned_staff_id
        WHERE i.status NOT IN ('Answered','Delivered','Closed')";
$params = [];
if ($deptFilter !== '') { $sql .= " AND i.department = ?"; $params[] = $deptFilter; }
if ($unassignedOnly) { $sql .= " AND i.assigned_staff_id IS NULL"; }
$sql .= " ORDER BY i.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$departments = ["Design & Engineering","Equipment Sales","Fabrication","Installation","Service & Maintenance","Customer Service"];

$pageTitle = 'Assign Tickets';
require __DIR__ . '/../includes/header.php';
?>
<div class="shell">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <main class="shell-main">
        <div class="shell-topbar"><div><h2>Assign Tickets</h2><div class="sub">Open tickets, assignable to any active staff member.</div></div></div>
        <div class="shell-body">
            <form method="get" class="filter-row">
                <select name="dept" onchange="this.form.submit()">
                    <option value="">All departments</option>
                    <?php foreach ($departments as $d): ?><option value="<?= $d ?>" <?= $deptFilter===$d?'selected':'' ?>><?= $d ?></option><?php endforeach; ?>
                </select>
                <label class="filter-checkbox">
                    <input type="checkbox" name="unassigned" value="1" onchange="this.form.submit()" <?= $unassignedOnly?'checked':'' ?>>
                    Unassigned only
                </label>
            </form>

            <div class="panel">
                <div class="panel-body">
                    <?php if (empty($tickets)): ?>
                        <div class="empty"><div class="ic">◧</div>No open tickets match these filters.</div>
                    <?php else: ?>
                    <div class="table-scroll"><table class="data">
                        <tr><th>Ticket</th><th>Customer</th><th>Type</th><th>Department</th><th>Status</th><th>Assign to</th></tr>
                        <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td class="mono"><?= htmlspecialchars($t['ticket_no']) ?></td>
                            <td><?= htmlspecialchars($t['full_name']) ?><br><span style="color:var(--ink-soft);font-size:11.5px;"><?= htmlspecialchars($t['company']) ?></span></td>
                            <td><?= htmlspecialchars($t['type']) ?></td>
                            <td><?= htmlspecialchars($t['department']) ?></td>
                            <td><span class="stamp <?= stampClassFor($t['status']) ?>" style="transform:none;padding:3px 8px;font-size:10px;"><?= htmlspecialchars($t['status']) ?></span></td>
                            <td>
                                <form method="post" style="display:flex;gap:6px;">
                                    <input type="hidden" name="inquiry_id" value="<?= $t['inquiry_id'] ?>">
                                    <select name="staff_id" onchange="this.form.submit()">
                                        <option value="">— Unassigned —</option>
                                        <?php foreach ($staffList as $s): ?>
                                            <option value="<?= $s['user_id'] ?>" <?= $t['assigned_staff_id']==$s['user_id']?'selected':'' ?>>
                                                <?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['department']) ?>) — <?= htmlspecialchars(presenceLabel($s['last_active_at'], $s['manual_status'])[0]) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
