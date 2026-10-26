<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/document_storage.php';
requireRole('Customer');
$u = currentUser();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT i.*, s.full_name AS staff_name, s.last_active_at AS staff_last_active, s.manual_status AS staff_manual_status
    FROM inquiries i
    LEFT JOIN users s ON s.user_id = i.assigned_staff_id
    WHERE i.inquiry_id = ? AND i.user_id = ?");
$stmt->execute([$id, $u['user_id']]);
$ticket = $stmt->fetch();
if (!$ticket) { header('Location: my_inquiries.php'); exit; }

$ticketComplete = isInquiryComplete($ticket['status']);
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($ticketComplete) {
        $error = 'This inquiry is complete and can no longer be changed.';
    }
    $action = $ticketComplete ? '' : ($_POST['action'] ?? 'reply');
    if ($action === 'upload_attachments') {
        try {
            $count = isset($_FILES['attachments'])
                ? storeInquiryAttachments($pdo, $id, $_FILES['attachments'], (int)$u['user_id'], 'Customer')
                : 0;
            if ($count === 0) {
                throw new InvalidArgumentException('Choose at least one file to upload.');
            }
            $_SESSION['flash'] = $count === 1 ? 'Document uploaded.' : "$count documents uploaded.";
            header('Location: inquiry_detail.php?id=' . $id);
            exit;
        } catch (InvalidArgumentException $uploadError) {
            $error = $uploadError->getMessage();
        } catch (Throwable $uploadError) {
            error_log('Customer document upload failed: ' . $uploadError->getMessage());
            $error = 'The documents could not be saved. Please try again.';
        }
    } elseif ($action === 'reply') {
        $reply = is_string($_POST['reply'] ?? null) ? trim($_POST['reply']) : '';
        if ($reply === '' || mb_strlen($reply, 'UTF-8') > 5000) {
            $error = 'Enter a reply of no more than 5,000 characters.';
        } else {
            $pdo->prepare("INSERT INTO messages (inquiry_id, sender_id, sender_role, body) VALUES (?, ?, 'Customer', ?)")
                ->execute([$id, $u['user_id'], $reply]);
            $_SESSION['flash'] = "Reply sent to Allied Metals.";
            header('Location: inquiry_detail.php?id=' . $id);
            exit;
        }
    }
}

$msgs = $pdo->prepare("SELECT * FROM messages WHERE inquiry_id = ? ORDER BY message_id ASC");
$msgs->execute([$id]);
$msgs = $msgs->fetchAll();

$atts = $pdo->prepare("SELECT a.*, uploader.full_name AS uploader_name
    FROM attachments a LEFT JOIN users uploader ON uploader.user_id = a.uploaded_by
    WHERE a.inquiry_id = ? ORDER BY a.uploaded_at, a.attachment_id");
$atts->execute([$id]);
$atts = $atts->fetchAll();

$statusOrder = inquiryStatuses($ticket['type']);
$stepIdx = array_search($ticket['status'], $statusOrder);
[$presenceText, $presenceClass] = presenceLabel($ticket['staff_last_active'], $ticket['staff_manual_status'] ?? 'available');

$pageTitle = $ticket['ticket_no'];
$hideSupportChat = true;
require __DIR__ . '/../includes/ticket_card.php';
require __DIR__ . '/../includes/header.php';
?>
<div class="shell">
    <?php $activeTab = 'tickets'; require __DIR__ . '/_sidebar.php'; ?>
    <main class="shell-main">
        <div class="shell-topbar">
            <div><h2><?= htmlspecialchars($ticket['ticket_no']) ?></h2><div class="sub"><?= htmlspecialchars($ticket['type']) ?></div></div>
            <a class="btn btn-outline btn-sm" href="my_inquiries.php">← Back to Track My Orders</a>
        </div>
        <div class="shell-body">
            <div class="panel">
                <div class="ticket-info-strip">
                    <div>
                        <div class="strip-label">Handled by</div>
                        <?php if ($ticket['staff_name']): ?>
                            <div class="strip-value">
                                <?= htmlspecialchars($ticket['staff_name']) ?>
                                <span class="presence <?= $presenceClass ?>"><span class="presence-dot"></span><?= htmlspecialchars($presenceText) ?></span>
                            </div>
                        <?php else: ?>
                            <div class="strip-value muted">Waiting to be assigned to a staff member</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="timeline">
                    <?php foreach ($statusOrder as $i => $s): ?>
                        <div class="tl-step <?= $i < $stepIdx ? 'done' : '' ?> <?= $i === $stepIdx ? 'current' : '' ?>">
                            <div class="dot"></div><div class="lbl"><?= $s ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="thread" data-inquiry-chat data-endpoint="<?= BASE_URL ?>/customer/inquiry_messages_api.php" data-inquiry-id="<?= (int)$id ?>" data-viewer-role="Customer" data-last-message-id="<?= (int)($msgs ? end($msgs)['message_id'] : 0) ?>">
                    <?php if ($error): ?><div class="form-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                    <?php foreach ($msgs as $m): ?>
                        <div class="msg <?= $m['sender_role'] === 'Customer' ? 'cust' : 'staff' ?>">
                            <div class="who"><?= $m['sender_role'] === 'Customer' ? 'You' : 'Allied Metals' ?></div>
                            <div class="msg-body"><?= nl2br(htmlspecialchars($m['body'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($atts): ?>
                        <div class="inquiry-documents">
                            <strong>Documents</strong>
                            <?php foreach ($atts as $a): ?>
                                <a class="attach-chip" href="<?= BASE_URL ?>/attachment_download.php?id=<?= (int)$a['attachment_id'] ?>">📎 <?= htmlspecialchars($a['file_name']) ?> · <?= htmlspecialchars($a['uploader_name'] ?? 'Customer') ?> · <?= date('M j, Y', strtotime($a['uploaded_at'])) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <p class="chat-sync-status" data-chat-status aria-live="polite" hidden></p>
                <?php if ($ticketComplete): ?>
                    <div class="form-note">This inquiry is complete and read-only. Its messages and documents remain available for reference.</div>
                <?php else: ?>
                <form method="post" enctype="multipart/form-data" class="reply-box">
                    <input type="hidden" name="action" value="reply">
                    <textarea name="reply" maxlength="5000" placeholder="Write a reply to Allied Metals…" required></textarea>
                    <div class="reply-actions">
                        <span style="font-size:11.5px;color:#8a969b;">Staff typically replies within 1 business day.</span>
                        <button class="btn btn-primary btn-sm" type="submit">Send Reply →</button>
                    </div>
                </form>
                <form method="post" enctype="multipart/form-data" class="document-upload-form">
                    <input type="hidden" name="action" value="upload_attachments">
                    <label for="customer-attachments">Add documents to this inquiry</label>
                    <div class="reply-actions">
                        <input id="customer-attachments" type="file" name="attachments[]" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.txt" multiple required>
                        <button class="btn btn-outline btn-sm" type="submit">Upload files</button>
                    </div>
                    <small>Up to 5 files at a time, 10 MB each. PDF, JPG, PNG, DOC, DOCX, XLS, XLSX, or TXT.</small>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
