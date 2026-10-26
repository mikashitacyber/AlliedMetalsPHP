<?php
require __DIR__ . '/config/config.php';
requireAnyRole(['Admin', 'HR']);

$today = new DateTimeImmutable('today');
$defaultStart = $today->modify('first day of this month')->format('Y-m-d');
$defaultEnd = $today->format('Y-m-d');
$startDate = is_string($_GET['from'] ?? null) ? $_GET['from'] : $defaultStart;
$endDate = is_string($_GET['to'] ?? null) ? $_GET['to'] : $defaultEnd;
$dateError = null;

$isValidDate = static function ($value): bool {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
};

if (!$isValidDate($startDate) || !$isValidDate($endDate)) {
    $dateError = 'Choose valid start and end dates.';
} elseif ($startDate > $endDate) {
    $dateError = 'The start date must be on or before the end date.';
}

if ($dateError !== null && ($_GET['export'] ?? '') === 'csv') {
    http_response_code(400);
    exit($dateError);
}

$summary = $byStatus = $byType = $byDepartment = $tickets = [];
if ($dateError === null) {
    $from = $startDate . ' 00:00:00';
    $toExclusive = (new DateTimeImmutable($endDate))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    $dateCondition = 'created_at >= ? AND created_at < ?';
    $dateParams = [$from, $toExclusive];

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total,
            SUM(CASE WHEN status NOT IN ('Answered','Delivered','Closed') THEN 1 ELSE 0 END) AS open_count,
            SUM(CASE WHEN status IN ('Answered','Delivered','Closed') THEN 1 ELSE 0 END) AS resolved_count,
            SUM(CASE WHEN assigned_staff_id IS NULL THEN 1 ELSE 0 END) AS unassigned_count
        FROM inquiries
        WHERE $dateCondition
    ");
    $stmt->execute($dateParams);
    $summary = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT status AS label, COUNT(*) AS total FROM inquiries WHERE $dateCondition GROUP BY status ORDER BY total DESC, label");
    $stmt->execute($dateParams);
    $byStatus = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT type AS label, COUNT(*) AS total FROM inquiries WHERE $dateCondition GROUP BY type ORDER BY total DESC, label");
    $stmt->execute($dateParams);
    $byType = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT department AS label, COUNT(*) AS total FROM inquiries WHERE $dateCondition GROUP BY department ORDER BY total DESC, label");
    $stmt->execute($dateParams);
    $byDepartment = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT i.ticket_no, i.created_at, customer.full_name AS customer_name, customer.company,
            i.type, i.department, i.status, staff.full_name AS assigned_staff
        FROM inquiries i
        JOIN users customer ON customer.user_id = i.user_id
        LEFT JOIN users staff ON staff.user_id = i.assigned_staff_id
        WHERE i.created_at >= ? AND i.created_at < ?
        ORDER BY i.created_at DESC, i.inquiry_id DESC
    ");
    $stmt->execute($dateParams);
    $tickets = $stmt->fetchAll();

    if (($_GET['export'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="allied-metals-mis-report-' . $startDate . '-to-' . $endDate . '.csv"');
        $output = fopen('php://output', 'w');
        if ($output === false) {
            error_log('Unable to open output stream for MIS report export.');
            http_response_code(500);
            exit('The report could not be exported.');
        }
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Ticket', 'Created', 'Customer', 'Company', 'Type', 'Department', 'Status', 'Assigned staff']);
        foreach ($tickets as $ticket) {
            $row = [
                $ticket['ticket_no'],
                $ticket['created_at'],
                $ticket['customer_name'],
                $ticket['company'],
                $ticket['type'],
                $ticket['department'],
                $ticket['status'],
                $ticket['assigned_staff'] ?? '',
            ];
            foreach ($row as &$value) {
                $value = (string)$value;
                if (preg_match('/^[\s]*[=+\-@]/', $value)) {
                    $value = "'" . $value;
                }
            }
            unset($value);
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
}

$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$pageTitle = 'MIS Report';
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
            <div><h2>MIS Report</h2><div class="sub">Inquiry and order activity for the selected period</div></div>
            <div style="display:flex;gap:8px;align-items:center;">
            <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/customer_report.php">Customer Report</a>
            <?php if ($dateError === null): ?>
                <a class="btn btn-outline btn-sm" href="?<?= http_build_query(['from' => $startDate, 'to' => $endDate, 'export' => 'csv']) ?>">Export CSV</a>
            <?php endif; ?>
            </div>
        </div>
        <div class="shell-body">
            <form method="get" action="<?= BASE_URL ?>/mis_report.php" class="mis-report-filters">
                <label for="report-from">From <input id="report-from" type="date" name="from" value="<?= $escape($startDate) ?>" required></label>
                <label for="report-to">To <input id="report-to" type="date" name="to" value="<?= $escape($endDate) ?>" required></label>
                <button class="btn btn-primary btn-sm" type="submit">Run report</button>
            </form>
            <?php if ($dateError !== null): ?>
                <div class="panel"><div class="panel-body"><?= $escape($dateError) ?></div></div>
            <?php else: ?>
                <p class="mis-report-period">Showing report for <?= date('M j, Y', strtotime($startDate)) ?> – <?= date('M j, Y', strtotime($endDate)) ?></p>
                <div class="kpi-row">
                    <div class="kpi"><div class="lbl">Inquiries Created</div><div class="val"><?= (int)$summary['total'] ?></div></div>
                    <div class="kpi"><div class="lbl">Currently Open</div><div class="val"><?= (int)$summary['open_count'] ?></div></div>
                    <div class="kpi"><div class="lbl">Currently Resolved</div><div class="val"><?= (int)$summary['resolved_count'] ?></div></div>
                    <div class="kpi"><div class="lbl">Unassigned</div><div class="val"><?= (int)$summary['unassigned_count'] ?></div></div>
                </div>
                <p style="margin:-12px 0 20px;color:var(--ink-soft);font-size:12px;">Open, resolved, and unassigned counts reflect each inquiry's current status, not its status during the selected dates.</p>
                <div class="grid-2">
                    <?php foreach (['Status' => $byStatus, 'Request type' => $byType, 'Department' => $byDepartment] as $title => $rows): ?>
                        <div class="panel">
                            <div class="panel-head"><h3>Inquiries by <?= $escape($title) ?></h3></div>
                            <div class="panel-body">
                                <?php if (!$rows): ?>
                                    <div class="empty"><div class="ic">◧</div>No inquiries in this period.</div>
                                <?php else: ?>
                                    <div class="table-scroll"><table class="data">
                                        <tr><th><?= $escape($title) ?></th><th>Count</th></tr>
                                        <?php foreach ($rows as $row): ?>
                                            <tr><td><?= $escape($row['label']) ?></td><td><?= (int)$row['total'] ?></td></tr>
                                        <?php endforeach; ?>
                                    </table></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="panel">
                        <div class="panel-head"><h3>Inquiry details</h3><span class="sub"><?= count($tickets) ?> records</span></div>
                        <div class="panel-body">
                            <?php if (!$tickets): ?>
                                <div class="empty"><div class="ic">◧</div>No inquiries in this period.</div>
                            <?php else: ?>
                                <div class="table-scroll"><table class="data">
                                    <tr><th>Ticket / Created</th><th>Customer</th><th>Type</th><th>Department</th><th>Status</th><th>Assigned staff</th></tr>
                                    <?php foreach ($tickets as $ticket): ?>
                                        <tr>
                                            <td class="mono"><?= $escape($ticket['ticket_no']) ?><br><?= $escape($ticket['created_at']) ?></td>
                                            <td><?= $escape($ticket['customer_name']) ?><br><span style="color:var(--ink-soft);font-size:11.5px;"><?= $escape($ticket['company']) ?></span></td>
                                            <td><?= $escape($ticket['type']) ?></td>
                                            <td><?= $escape($ticket['department']) ?></td>
                                            <td><?= $escape($ticket['status']) ?></td>
                                            <td><?= $escape($ticket['assigned_staff'] ?? 'Unassigned') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
