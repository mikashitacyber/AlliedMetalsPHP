<?php
require __DIR__ . '/config/config.php';
requireAnyRole(['Customer', 'Staff', 'HR', 'Admin']);

$user = currentUser();
$isSupervisor = in_array($user['role'], ['HR', 'Admin'], true);
$isCustomer = $user['role'] === 'Customer';
$pageTitle = $isCustomer ? 'My Documents' : 'Document Archive';
$search = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 160, 'UTF-8') : '';
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
$pageSize = 50;
$offset = ($page - 1) * $pageSize;
$visibility = match (true) {
    $isSupervisor => '',
    $isCustomer => ' AND i.user_id = ?',
    default => ' AND i.assigned_staff_id = ?',
};
$searchSql = $search === ''
    ? ''
    : ' AND (a.file_name LIKE ? OR i.ticket_no LIKE ? OR c.full_name LIKE ? OR c.company LIKE ?)';
$searchParams = $search === '' ? [] : array_fill(0, 4, '%' . $search . '%');
$visibilityParams = $isSupervisor ? [] : [(int)$user['user_id']];

$count = $pdo->prepare("
    SELECT COUNT(*)
    FROM attachments a
    JOIN inquiries i ON i.inquiry_id = a.inquiry_id
    JOIN users c ON c.user_id = i.user_id
    WHERE 1 = 1 $visibility $searchSql
");
$count->execute(array_merge($visibilityParams, $searchParams));
$total = (int)$count->fetchColumn();
$totalPages = max(1, (int)ceil($total / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;

$documents = $pdo->prepare("
    SELECT a.attachment_id, a.file_name, a.uploaded_at, a.uploaded_role,
           i.inquiry_id, i.ticket_no, i.type, c.full_name AS customer_name, c.company,
           uploader.full_name AS uploader_name
    FROM attachments a
    JOIN inquiries i ON i.inquiry_id = a.inquiry_id
    JOIN users c ON c.user_id = i.user_id
    LEFT JOIN users uploader ON uploader.user_id = a.uploaded_by
    WHERE 1 = 1 $visibility $searchSql
    ORDER BY a.uploaded_at DESC, a.attachment_id DESC
    LIMIT $pageSize OFFSET $offset
");
$documents->execute(array_merge($visibilityParams, $searchParams));
$documents = $documents->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="shell">
    <?php if ($user['role'] === 'Customer'): $activeTab = 'documents'; require __DIR__ . '/customer/_sidebar.php'; ?>
    <?php elseif ($user['role'] === 'Staff'): require __DIR__ . '/staff/_sidebar.php'; ?>
    <?php elseif ($user['role'] === 'HR'): require __DIR__ . '/hr/_sidebar.php'; ?>
    <?php else: require __DIR__ . '/admin/_sidebar.php'; endif; ?>
    <main class="shell-main">
        <div class="shell-topbar">
            <div><h2><?= htmlspecialchars($pageTitle) ?></h2><div class="sub"><?= $isSupervisor ? 'Search documents across all customer inquiries.' : ($isCustomer ? 'Search documents attached to your inquiries.' : 'Documents attached to inquiries assigned to you.') ?></div></div>
        </div>
        <div class="shell-body">
            <form method="get" class="filter-row">
                <input type="search" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" maxlength="160" placeholder="Search file, ticket, customer, or company">
                <button class="btn btn-outline btn-sm" type="submit">Search</button>
            </form>
            <div class="panel">
                <div class="panel-body">
                    <?php if (!$documents): ?>
                        <div class="empty"><div class="ic">▤</div>No documents found.</div>
                    <?php else: ?>
                    <div class="table-scroll"><table class="data">
                        <thead><tr><th>Document</th><th>Ticket</th><th>Customer / Company</th><th>Uploaded by</th><th>Date</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($documents as $document): ?>
                            <tr>
                                <td class="row-name"><?= htmlspecialchars($document['file_name']) ?><br><small><?= htmlspecialchars($document['type']) ?></small></td>
                                <td><?= htmlspecialchars($document['ticket_no']) ?></td>
                                <td><?= htmlspecialchars($document['customer_name'] . ' — ' . $document['company']) ?></td>
                                <td><?= htmlspecialchars($document['uploader_name'] ?? ($document['uploaded_role'] ?: 'Customer')) ?></td>
                                <td><?= date('M j, Y', strtotime($document['uploaded_at'])) ?></td>
                                <td><a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>/attachment_download.php?id=<?= (int)$document['attachment_id'] ?>">Download</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                    <?php endif; ?>
                    <?php if ($totalPages > 1): ?>
                    <div class="reply-actions" style="margin-top:16px;">
                        <span><?= $total ?> documents · page <?= $page ?> of <?= $totalPages ?></span>
                        <div>
                            <?php if ($page > 1): ?><a class="btn btn-outline btn-sm" href="?<?= http_build_query(['q' => $search, 'page' => $page - 1]) ?>">Previous</a><?php endif; ?>
                            <?php if ($page < $totalPages): ?><a class="btn btn-outline btn-sm" href="?<?= http_build_query(['q' => $search, 'page' => $page + 1]) ?>">Next</a><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
