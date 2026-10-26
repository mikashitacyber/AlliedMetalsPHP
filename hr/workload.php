<?php
require __DIR__ . '/../config/config.php';
requireRole('HR');

$staff = $pdo->query("
    SELECT u.user_id, u.full_name, u.department,
        SUM(CASE WHEN i.status NOT IN ('Answered','Delivered','Closed') THEN 1 ELSE 0 END) AS open_tickets,
        SUM(CASE WHEN i.status IN ('Answered','Delivered','Closed') THEN 1 ELSE 0 END) AS resolved_tickets
    FROM users u
    LEFT JOIN inquiries i ON i.assigned_staff_id = u.user_id
    WHERE u.role = 'Staff'
    GROUP BY u.user_id
    ORDER BY open_tickets DESC
")->fetchAll();

$pageTitle = 'Staff Workload';
require __DIR__ . '/../includes/header.php';
?>
<div class="shell">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <main class="shell-main">
        <div class="shell-topbar"><div><h2>Staff Workload</h2><div class="sub">Open vs. resolved tickets per staff member</div></div></div>
        <div class="shell-body">
            <div class="panel">
                <div class="panel-body">
                    <?php if (empty($staff)): ?>
                        <div class="empty"><div class="ic">☺</div>No staff accounts yet.</div>
                    <?php else: ?>
                    <div class="table-scroll"><table class="data">
                        <tr><th>Staff</th><th>Department</th><th>Open Tickets</th><th>Resolved</th></tr>
                        <?php foreach ($staff as $s): ?>
                        <tr>
                            <td class="row-name"><?= htmlspecialchars($s['full_name']) ?></td>
                            <td><?= htmlspecialchars($s['department']) ?></td>
                            <td><?= (int)$s['open_tickets'] ?></td>
                            <td><?= (int)$s['resolved_tickets'] ?></td>
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
