<?php
require __DIR__ . '/config/config.php';
requireAnyRole(['Staff', 'HR', 'Admin']);
require __DIR__ . '/includes/ticket_card.php';

$u = currentUser();
$isSupervisor = in_array($u['role'], ['HR', 'Admin'], true);
$search = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 160, 'UTF-8') : '';
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
$pageSize = 30;
$offset = ($page - 1) * $pageSize;
$visibility = $isSupervisor ? '' : ' AND i.assigned_staff_id = ?';
$searchSql = $search === ''
    ? ''
    : ' AND (i.ticket_no LIKE ? OR i.type LIKE ? OR c.full_name LIKE ? OR c.company LIKE ?)';
$visibilityParams = $isSupervisor ? [] : [(int)$u['user_id']];
$searchParams = $search === '' ? [] : array_fill(0, 4, '%' . $search . '%');
$where = "i.status IN ('Answered', 'Delivered', 'Closed')$visibility$searchSql";

$count = $pdo->prepare("
    SELECT COUNT(*)
    FROM inquiries i
    JOIN users c ON c.user_id = i.user_id
    WHERE $where
");
$count->execute(array_merge($visibilityParams, $searchParams));
$total = (int)$count->fetchColumn();
$totalPages = max(1, (int)ceil($total / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;

$stmt = $pdo->prepare("
    SELECT i.*, c.full_name, c.company, s.full_name AS assigned_staff_name
    FROM inquiries i
    JOIN users c ON c.user_id = i.user_id
    LEFT JOIN users s ON s.user_id = i.assigned_staff_id
    WHERE $where
    ORDER BY i.created_at DESC
    LIMIT $pageSize OFFSET $offset
");
$stmt->execute(array_merge($visibilityParams, $searchParams));
$inquiries = $stmt->fetchAll();

$selectedId = (int)($_GET['id'] ?? ($inquiries[0]['inquiry_id'] ?? 0));
$selected = null;
foreach ($inquiries as $inquiry) {
    if ((int)$inquiry['inquiry_id'] === $selectedId) {
        $selected = $inquiry;
        break;
    }
}

$messages = $attachments = [];
if ($selected) {
    $messagesStmt = $pdo->prepare('SELECT * FROM messages WHERE inquiry_id = ? ORDER BY message_id ASC');
    $messagesStmt->execute([$selectedId]);
    $messages = $messagesStmt->fetchAll();

    $attachmentsStmt = $pdo->prepare("
        SELECT a.*, uploader.full_name AS uploader_name
        FROM attachments a
        LEFT JOIN users uploader ON uploader.user_id = a.uploaded_by
        WHERE a.inquiry_id = ?
        ORDER BY a.uploaded_at, a.attachment_id
    ");
    $attachmentsStmt->execute([$selectedId]);
    $attachments = $attachmentsStmt->fetchAll();
}

$statusOrder = inquiryStatuses();
$departments = [];
$pageTitle = 'Completed Inquiries';
require __DIR__ . '/includes/header.php';
?>
<div class="shell">
    <?php if ($u['role'] === 'Staff'): require __DIR__ . '/staff/_sidebar.php'; ?>
    <?php elseif ($u['role'] === 'HR'): require __DIR__ . '/hr/_sidebar.php'; ?>
    <?php else: require __DIR__ . '/admin/_sidebar.php'; endif; ?>
    <main class="shell-main">
        <div class="shell-topbar">
            <div><h2>Completed Inquiries</h2><div class="sub"><?= $total ?> completed ticket<?= $total === 1 ? '' : 's' ?> · read-only archive</div></div>
        </div>
        <div class="shell-body">
            <div class="list-detail">
                <div class="list-col">
                    <form method="get" class="filter-row">
                        <input type="search" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" maxlength="160" placeholder="Search customer, company, ticket #…">
                        <button class="btn btn-outline btn-sm" type="submit">Search</button>
                    </form>
                    <?php if (!$inquiries): ?>
                        <div class="empty"><div class="ic">✓</div>No completed inquiries found.</div>
                    <?php else: foreach ($inquiries as $inquiry):
                        $query = http_build_query(['q' => $search, 'page' => $page, 'id' => $inquiry['inquiry_id']]);
                        ?>
                        <a href="completed_inquiries.php?<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" class="ticket <?= (int)$inquiry['inquiry_id'] === $selectedId ? 'selected' : '' ?>">
                            <div class="ticket-perf"></div>
                            <div class="ticket-top">
                                <span class="ticket-id mono"><?= htmlspecialchars($inquiry['ticket_no']) ?></span>
                                <span class="stamp <?= stampClassFor($inquiry['status']) ?>"><?= htmlspecialchars($inquiry['status']) ?></span>
                            </div>
                            <div class="ticket-body">
                                <h4><?= htmlspecialchars($inquiry['full_name'] . ' — ' . $inquiry['company']) ?></h4>
                                <div class="ticket-meta"><span><?= htmlspecialchars($inquiry['type']) ?></span></div>
                                <div class="ticket-meta"><span><?= date('M j, Y', strtotime($inquiry['created_at'])) ?></span></div>
                            </div>
                        </a>
                    <?php endforeach; endif; ?>
                    <?php if ($totalPages > 1): ?>
                        <div class="reply-actions">
                            <?php if ($page > 1): ?><a class="btn btn-outline btn-sm" href="?<?= http_build_query(['q' => $search, 'page' => $page - 1]) ?>">Previous</a><?php endif; ?>
                            <span>Page <?= $page ?> of <?= $totalPages ?></span>
                            <?php if ($page < $totalPages): ?><a class="btn btn-outline btn-sm" href="?<?= http_build_query(['q' => $search, 'page' => $page + 1]) ?>">Next</a><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="detail-col">
                    <?php
                    $selected = $selected ?: null;
                    $msgs = $messages;
                    $atts = $attachments;
                    require __DIR__ . '/staff/_ticket_detail_panel.php';
                    ?>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
