<?php
require __DIR__ . '/config/config.php';
requireAnyRole(['Admin', 'HR', 'Staff']);

$currentUser = currentUser();
if ($currentUser['role'] === 'Staff' && ($currentUser['department'] ?? '') !== 'Customer Service') {
    http_response_code(403);
    exit('Customer Report is available to Customer Service staff only.');
}

$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$search = is_string($_GET['q'] ?? null) ? trim(mb_substr($_GET['q'], 0, 100, 'UTF-8')) : '';
$customerId = filter_var($_GET['customer'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$perPage = 25;
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
$customer = null;
$orders = [];
$customers = [];

if ($customerId !== false && $customerId !== null) {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.full_name, u.company, u.email, u.phone, u.is_active, u.created_at,
            COUNT(i.inquiry_id) AS inquiry_count,
            COALESCE(SUM(CASE WHEN i.status NOT IN ('Answered','Delivered','Closed') THEN 1 ELSE 0 END), 0) AS open_count
        FROM users u
        LEFT JOIN inquiries i ON i.user_id = u.user_id
        WHERE u.user_id = ? AND u.role = 'Customer'
        GROUP BY u.user_id
    ");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();
    if (!$customer) {
        http_response_code(404);
    } else {
        $stmt = $pdo->prepare("
            SELECT i.ticket_no, i.created_at, i.type, i.department, i.status, i.priority, i.location,
                staff.full_name AS assigned_staff
            FROM inquiries i
            LEFT JOIN users staff ON staff.user_id = i.assigned_staff_id
            WHERE i.user_id = ?
            ORDER BY i.created_at DESC, i.inquiry_id DESC
        ");
        $stmt->execute([$customerId]);
        $orders = $stmt->fetchAll();

        if (($_GET['export'] ?? '') === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="customer-' . (int)$customer['user_id'] . '-inquiries.csv"');
            $output = fopen('php://output', 'w');
            if ($output === false) {
                error_log('Unable to open output stream for customer report export.');
                http_response_code(500);
                exit('The report could not be exported.');
            }
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Customer', 'Company', 'Email', 'Phone', 'Ticket', 'Created', 'Type', 'Department', 'Status', 'Priority', 'Location', 'Assigned staff']);
            foreach ($orders as $order) {
                $row = [
                    $customer['full_name'], $customer['company'], $customer['email'], $customer['phone'] ?? '',
                    $order['ticket_no'], $order['created_at'], $order['type'], $order['department'],
                    $order['status'], $order['priority'], $order['location'] ?? '', $order['assigned_staff'] ?? '',
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
} else {
    $where = "u.role = 'Customer'";
    $params = [];
    if ($search !== '') {
        $where .= " AND (u.full_name LIKE ? OR u.company LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $term = '%' . $search . '%';
        $params = [$term, $term, $term, $term];
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $where");
    $countStmt->execute($params);
    $totalCustomers = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalCustomers / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.full_name, u.company, u.email, u.phone, u.is_active, u.created_at,
            COUNT(i.inquiry_id) AS inquiry_count,
            COALESCE(SUM(CASE WHEN i.status NOT IN ('Answered','Delivered','Closed') THEN 1 ELSE 0 END), 0) AS open_count
        FROM users u
        LEFT JOIN inquiries i ON i.user_id = u.user_id
        WHERE $where
        GROUP BY u.user_id
        ORDER BY u.created_at DESC, u.user_id DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $customers = $stmt->fetchAll();
}

$pageTitle = 'Customer Report';
require __DIR__ . '/includes/header.php';
?>
<div class="shell">
    <?php if ($currentUser['role'] === 'Admin'): ?>
        <?php require __DIR__ . '/admin/_sidebar.php'; ?>
    <?php elseif ($currentUser['role'] === 'Staff'): ?>
        <?php require __DIR__ . '/staff/_sidebar.php'; ?>
    <?php else: ?>
        <?php require __DIR__ . '/hr/_sidebar.php'; ?>
    <?php endif; ?>
    <main class="shell-main">
        <div class="shell-topbar">
            <div>
                <h2>Customer Report</h2>
                <div class="sub"><?= $customer ? 'Customer profile and inquiry/order history' : 'Customer accounts and inquiry activity' ?></div>
            </div>
            <?php if ($customer): ?>
                <a class="btn btn-outline btn-sm" href="?<?= http_build_query(['customer' => (int)$customer['user_id'], 'export' => 'csv']) ?>">Export customer CSV</a>
            <?php endif; ?>
        </div>
        <div class="shell-body">
            <?php if ($customerId !== false && $customerId !== null && !$customer): ?>
                <div class="panel"><div class="panel-body">
                    <div class="empty"><div class="ic">◧</div>Customer not found.</div>
                    <a class="btn btn-outline btn-sm" href="customer_report.php">Back to customer report</a>
                </div></div>
            <?php elseif ($customer): ?>
                <p><a class="btn btn-outline btn-sm" href="customer_report.php">← All customers</a></p>
                <div class="kpi-row">
                    <div class="kpi"><div class="lbl">Customer</div><div class="val" style="font-size:20px;"><?= $escape($customer['full_name']) ?></div></div>
                    <div class="kpi"><div class="lbl">Total Inquiries / Orders</div><div class="val"><?= (int)$customer['inquiry_count'] ?></div></div>
                    <div class="kpi"><div class="lbl">Currently Open</div><div class="val"><?= (int)$customer['open_count'] ?></div></div>
                    <div class="kpi"><div class="lbl">Account</div><div class="val" style="font-size:20px;"><?= $customer['is_active'] ? 'Active' : 'Suspended' ?></div></div>
                </div>
                <div class="panel" style="margin-bottom:20px;">
                    <div class="panel-head"><h3>Customer Information</h3></div>
                    <div class="panel-body">
                        <div class="table-scroll"><table class="data">
                            <tr><th>Company</th><td><?= $escape($customer['company']) ?></td><th>Email</th><td><?= $escape($customer['email']) ?></td></tr>
                            <tr><th>Phone</th><td><?= $escape($customer['phone'] ?? '—') ?></td><th>Account created</th><td><?= $escape($customer['created_at']) ?></td></tr>
                        </table></div>
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-head"><h3>Inquiry / Order History</h3><span class="sub"><?= count($orders) ?> records</span></div>
                    <div class="panel-body">
                        <?php if (!$orders): ?>
                            <div class="empty"><div class="ic">◧</div>This customer has not submitted any inquiries or orders.</div>
                        <?php else: ?>
                            <div class="table-scroll"><table class="data">
                                <thead><tr><th>Ticket / Date</th><th>Type</th><th>Department</th><th>Status</th><th>Priority</th><th>Location</th><th>Assigned staff</th></tr></thead>
                                <tbody><?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td class="mono"><?= $escape($order['ticket_no']) ?><br><?= $escape($order['created_at']) ?></td>
                                        <td><?= $escape($order['type']) ?></td>
                                        <td><?= $escape($order['department']) ?></td>
                                        <td><?= $escape($order['status']) ?></td>
                                        <td><?= $escape(ucfirst($order['priority'])) ?></td>
                                        <td><?= $escape($order['location'] ?? '—') ?></td>
                                        <td><?= $escape($order['assigned_staff'] ?? 'Unassigned') ?></td>
                                    </tr>
                                <?php endforeach; ?></tbody>
                            </table></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="panel">
                    <div class="panel-head"><h3>Customer Accounts</h3><span class="sub"><?= (int)$totalCustomers ?> customers</span></div>
                    <div class="panel-body">
                        <form method="get" class="filter-row">
                            <input type="search" name="q" value="<?= $escape($search) ?>" maxlength="100" placeholder="Search name, company, email, or phone">
                            <button class="btn btn-outline btn-sm" type="submit">Search</button>
                        </form>
                        <?php if (!$customers): ?>
                            <div class="empty"><div class="ic">☺</div>No customer accounts match your search.</div>
                        <?php else: ?>
                            <div class="table-scroll"><table class="data">
                                <thead><tr><th>Name</th><th>Company</th><th>Email</th><th>Inquiries</th><th>Open</th><th>Status</th><th></th></tr></thead>
                                <tbody><?php foreach ($customers as $row): ?>
                                    <tr>
                                        <td class="row-name"><?= $escape($row['full_name']) ?></td>
                                        <td><?= $escape($row['company']) ?></td>
                                        <td><?= $escape($row['email']) ?></td>
                                        <td><?= (int)$row['inquiry_count'] ?></td>
                                        <td><?= (int)$row['open_count'] ?></td>
                                        <td><?= $row['is_active'] ? 'Active' : 'Suspended' ?></td>
                                        <td><a class="btn btn-outline btn-sm" href="?customer=<?= (int)$row['user_id'] ?>">View report</a></td>
                                    </tr>
                                <?php endforeach; ?></tbody>
                            </table></div>
                            <?php if ($totalPages > 1): ?>
                                <div class="reply-actions" style="margin-top:16px;">
                                    <span class="muted">Page <?= $page ?> of <?= $totalPages ?></span>
                                    <div>
                                        <?php if ($page > 1): ?><a class="btn btn-outline btn-sm" href="?<?= http_build_query(['q' => $search, 'page' => $page - 1]) ?>">Previous</a><?php endif; ?>
                                        <?php if ($page < $totalPages): ?><a class="btn btn-outline btn-sm" href="?<?= http_build_query(['q' => $search, 'page' => $page + 1]) ?>">Next</a><?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
