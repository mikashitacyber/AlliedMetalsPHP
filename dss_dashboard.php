<?php
require __DIR__ . '/config/config.php';
requireAnyRole(['Admin', 'HR']);

$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

$priorityStmt = $pdo->query("
    SELECT i.inquiry_id, i.ticket_no, i.type, i.department, i.status, i.priority, i.created_at,
        customer.full_name AS customer_name, customer.company,
        staff.full_name AS assigned_staff,
        TIMESTAMPDIFF(HOUR, i.created_at, NOW()) AS age_hours
    FROM inquiries i
    JOIN users customer ON customer.user_id = i.user_id
    LEFT JOIN users staff ON staff.user_id = i.assigned_staff_id
    WHERE i.status NOT IN ('Answered','Delivered','Closed')
      AND (i.priority = 'high' OR i.created_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR))
    ORDER BY CASE i.priority WHEN 'high' THEN 0 ELSE 1 END, i.created_at ASC
    LIMIT 100
");
$priorityTickets = $priorityStmt->fetchAll();

$staffStmt = $pdo->query("
    SELECT u.user_id, u.full_name, u.department, u.last_active_at, u.manual_status,
        SUM(CASE WHEN i.inquiry_id IS NOT NULL AND i.status NOT IN ('Answered','Delivered','Closed') THEN 1 ELSE 0 END) AS open_count
    FROM users u
    LEFT JOIN inquiries i ON i.assigned_staff_id = u.user_id
    WHERE u.role = 'Staff' AND u.is_active = 1
    GROUP BY u.user_id
    ORDER BY u.department, open_count, u.full_name
");
$staff = $staffStmt->fetchAll();
$staffByDepartment = [];
foreach ($staff as &$member) {
    $member['open_count'] = (int)$member['open_count'];
    [, $member['presence_class']] = presenceLabel($member['last_active_at'], $member['manual_status']);
    $staffByDepartment[$member['department'] ?? ''][] = $member;
}
unset($member);

$unassignedStmt = $pdo->query("
    SELECT i.inquiry_id, i.ticket_no, i.type, i.department, i.status, i.priority, i.created_at,
        customer.full_name AS customer_name, customer.company,
        TIMESTAMPDIFF(HOUR, i.created_at, NOW()) AS age_hours
    FROM inquiries i
    JOIN users customer ON customer.user_id = i.user_id
    WHERE i.assigned_staff_id IS NULL
      AND i.status NOT IN ('Answered','Delivered','Closed')
    ORDER BY CASE i.priority WHEN 'high' THEN 0 WHEN 'med' THEN 1 ELSE 2 END, i.created_at ASC
    LIMIT 100
");
$unassignedTickets = $unassignedStmt->fetchAll();
foreach ($unassignedTickets as &$ticket) {
    $candidates = $staffByDepartment[$ticket['department']] ?? [];
    usort($candidates, static function (array $a, array $b): int {
        return $a['open_count'] <=> $b['open_count']
            ?: (($a['presence_class'] === 'online' ? 0 : 1) <=> ($b['presence_class'] === 'online' ? 0 : 1))
            ?: strcasecmp($a['full_name'], $b['full_name']);
    });
    $ticket['suggested_staff'] = $candidates[0] ?? null;
}
unset($ticket);

$scheduleStmt = $pdo->query("
    SELECT t.due_date AS scheduled_date, t.task_id, t.title, t.task_type, t.status AS task_status,
        assignee.user_id AS assignee_id, assignee.full_name AS assignee_name,
        i.ticket_no, customer.company
    FROM employee_tasks t
    JOIN users assignee ON assignee.user_id = t.assigned_to
    LEFT JOIN inquiries i ON i.inquiry_id = t.inquiry_id
    LEFT JOIN users customer ON customer.user_id = i.user_id
    WHERE t.due_date >= CURDATE() AND t.status <> 'Completed'
    ORDER BY t.due_date, assignee.full_name, t.task_id
");
$scheduleGroups = [];
foreach ($scheduleStmt->fetchAll() as $task) {
    $key = $task['assignee_id'] . '|' . $task['scheduled_date'];
    $scheduleGroups[$key]['assignee_name'] = $task['assignee_name'];
    $scheduleGroups[$key]['scheduled_date'] = $task['scheduled_date'];
    $scheduleGroups[$key]['tasks'][] = $task;
}
$scheduleConflicts = array_values(array_filter($scheduleGroups, static fn($group) => count($group['tasks']) > 1));

$openCount = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status NOT IN ('Answered','Delivered','Closed')")->fetchColumn();
$agedCount = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status NOT IN ('Answered','Delivered','Closed') AND created_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
$highPriorityCount = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status NOT IN ('Answered','Delivered','Closed') AND priority = 'high'")->fetchColumn();
$unassignedCount = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE assigned_staff_id IS NULL AND status NOT IN ('Answered','Delivered','Closed')")->fetchColumn();

$pageTitle = 'Decision Support';
require __DIR__ . '/includes/header.php';
?>
<div class="shell">
    <?php if (($_SESSION['user']['role'] ?? '') === 'Admin'): ?>
        <?php require __DIR__ . '/admin/_sidebar.php'; ?>
    <?php else: ?>
        <?php require __DIR__ . '/hr/_sidebar.php'; ?>
    <?php endif; ?>
    <main class="shell-main">
        <div class="shell-topbar">
            <div><h2>Decision Support</h2><div class="sub">Operational recommendations based on current portal data</div></div>
            <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/mis_report.php">Open MIS Report</a>
        </div>
        <div class="shell-body">
            <div class="form-note" style="margin-bottom:18px;">Recommendations are rule-based and advisory only. Staff assignments and job schedules are not changed automatically.</div>
            <div class="kpi-row">
                <div class="kpi"><div class="lbl">Open inquiries</div><div class="val"><?= $openCount ?></div></div>
                <div class="kpi"><div class="lbl">High priority</div><div class="val"><?= $highPriorityCount ?></div></div>
                <div class="kpi"><div class="lbl">Open over 24 hours</div><div class="val"><?= $agedCount ?></div></div>
                <div class="kpi"><div class="lbl">Unassigned</div><div class="val"><?= $unassignedCount ?></div></div>
            </div>

            <div class="panel" style="margin-bottom:18px;">
                <div class="panel-head"><h3>Inquiries to review</h3><span class="sub">High priority or open for 24+ hours</span></div>
                <div class="panel-body">
                    <?php if (!$priorityTickets): ?>
                        <div class="empty"><div class="ic">✓</div>No high-priority or aged open inquiries need attention.</div>
                    <?php else: ?>
                        <div class="table-scroll"><table class="data">
                            <tr><th>Ticket</th><th>Customer</th><th>Request</th><th>Department</th><th>Priority / age</th><th>Status</th><th>Assigned staff</th></tr>
                            <?php foreach ($priorityTickets as $ticket): ?>
                                <tr>
                                    <td class="mono"><?= $escape($ticket['ticket_no']) ?></td>
                                    <td><?= $escape($ticket['customer_name']) ?><br><span style="color:var(--ink-soft);font-size:11.5px;"><?= $escape($ticket['company']) ?></span></td>
                                    <td><?= $escape($ticket['type']) ?></td>
                                    <td><?= $escape($ticket['department']) ?></td>
                                    <td><?= $escape(ucfirst($ticket['priority'])) ?> · <?= (int)$ticket['age_hours'] ?>h</td>
                                    <td><?= $escape($ticket['status']) ?></td>
                                    <td><?= $escape($ticket['assigned_staff'] ?? 'Unassigned') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel" style="margin-bottom:18px;">
                <div class="panel-head"><h3>Unassigned inquiry suggestions</h3><span class="sub">Lowest open workload in the matching department; online staff break ties</span></div>
                <div class="panel-body">
                    <?php if (!$unassignedTickets): ?>
                        <div class="empty"><div class="ic">✓</div>All open inquiries are assigned.</div>
                    <?php else: ?>
                        <div class="table-scroll"><table class="data">
                            <tr><th>Ticket</th><th>Customer</th><th>Department</th><th>Priority / age</th><th>Suggested staff</th><th>Reason</th></tr>
                            <?php foreach ($unassignedTickets as $ticket): ?>
                                <tr>
                                    <td class="mono"><?= $escape($ticket['ticket_no']) ?></td>
                                    <td><?= $escape($ticket['customer_name']) ?></td>
                                    <td><?= $escape($ticket['department']) ?></td>
                                    <td><?= $escape(ucfirst($ticket['priority'])) ?> · <?= (int)$ticket['age_hours'] ?>h</td>
                                    <?php if ($ticket['suggested_staff']): ?>
                                        <td><?= $escape($ticket['suggested_staff']['full_name']) ?></td>
                                        <td><?= (int)$ticket['suggested_staff']['open_count'] ?> open assigned · <?= $escape($ticket['suggested_staff']['presence_class'] === 'online' ? 'online now' : 'not currently online') ?></td>
                                    <?php else: ?>
                                        <td>—</td><td>No active staff in this department</td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </table></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid-2">
                <div class="panel">
                    <div class="panel-head"><h3>Staff workload</h3></div>
                    <div class="panel-body">
                        <?php if (!$staff): ?>
                            <div class="empty"><div class="ic">☺</div>No active Staff accounts.</div>
                        <?php else: ?>
                            <div class="table-scroll"><table class="data">
                                <tr><th>Staff</th><th>Department</th><th>Open inquiries</th><th>Availability</th></tr>
                                <?php foreach ($staff as $member): [$presenceText, $presenceClass] = presenceLabel($member['last_active_at'], $member['manual_status']); ?>
                                    <tr>
                                        <td><?= $escape($member['full_name']) ?></td>
                                        <td><?= $escape($member['department'] ?? '—') ?></td>
                                        <td><?= $member['open_count'] ?></td>
                                        <td><span class="presence <?= $escape($presenceClass) ?>"><span class="presence-dot"></span><?= $escape($presenceText) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-head"><h3>Potential employee schedule overlaps</h3><span class="sub">Multiple active tasks on one date</span></div>
                    <div class="panel-body">
                        <p class="muted" style="font-size:12px;margin-top:0;">The system stores due dates but not time slots, so these are same-day overlaps to review, not confirmed time conflicts.</p>
                        <?php if (!$scheduleConflicts): ?>
                            <div class="empty"><div class="ic">✓</div>No future same-day overlaps found.</div>
                        <?php else: ?>
                            <?php foreach ($scheduleConflicts as $group): ?>
                                <div style="margin-bottom:14px;">
                                    <strong><?= $escape($group['assignee_name']) ?></strong> · <?= $escape($group['scheduled_date']) ?>
                                    <ul><?php foreach ($group['tasks'] as $task): ?><li><?= $escape($task['title']) ?> (<?= $escape($task['task_type']) ?>, <?= $escape($task['task_status']) ?>)<?= $task['ticket_no'] ? ' — ' . $escape($task['ticket_no'] . ' / ' . $task['company']) : '' ?></li><?php endforeach; ?></ul>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
