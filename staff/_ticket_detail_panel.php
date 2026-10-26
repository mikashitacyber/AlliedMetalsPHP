<?php
// Expects: $selected (inquiry row w/ full_name, company), $msgs, $atts,
// $statusOrder (array), $departments (array) already set by the including page.
if (!$selected): ?>
    <div class="empty"><div class="ic">◧</div>Select a ticket to view its details.</div>
<?php else:
    $selectedStatusOrder = inquiryStatuses($selected['type']);
    $stepIdx = array_search($selected['status'], $selectedStatusOrder);
    $ticketComplete = isInquiryComplete($selected['status']);
    $canManageDocuments = in_array($u['role'], ['HR', 'Admin'], true)
        || ($u['role'] === 'Staff' && (int)$selected['assigned_staff_id'] === (int)$u['user_id']);
    ?>
    <div class="detail-head">
        <div class="ticket-id-lg mono"><?= htmlspecialchars($selected['ticket_no']) ?></div>
        <h3><?= htmlspecialchars($selected['full_name'] . ' — ' . $selected['company']) ?></h3>
        <div class="ticket-meta">
            <span><?= htmlspecialchars($selected['type']) ?></span>
            <span>🕒 <?= date('M j, Y', strtotime($selected['created_at'])) ?></span>
            <span class="pill priority-<?= $selected['priority'] ?>"><?= $selected['priority'] ?> priority</span>
            <?php if (!empty($selected['assigned_staff_name'])): ?>
                <span>👤 Assigned: <?= htmlspecialchars($selected['assigned_staff_name']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="timeline">
        <?php foreach ($selectedStatusOrder as $i => $s): ?>
            <div class="tl-step <?= $i<$stepIdx?'done':'' ?> <?= $i===$stepIdx?'current':'' ?>"><div class="dot"></div><div class="lbl"><?= $s ?></div></div>
        <?php endforeach; ?>
    </div>
    <div class="thread" data-inquiry-chat data-endpoint="<?= BASE_URL ?>/customer/inquiry_messages_api.php" data-inquiry-id="<?= (int)$selected['inquiry_id'] ?>" data-viewer-role="Staff" data-customer-name="<?= htmlspecialchars($selected['full_name'], ENT_QUOTES, 'UTF-8') ?>" data-last-message-id="<?= (int)($msgs ? end($msgs)['message_id'] : 0) ?>">
        <?php foreach ($msgs as $m): ?>
            <div class="msg <?= $m['sender_role']==='Customer'?'cust':'staff' ?>">
                <div class="who"><?= $m['sender_role']==='Customer' ? htmlspecialchars($selected['full_name']) : 'You (Staff)' ?></div>
                <div class="msg-body"><?= nl2br(htmlspecialchars($m['body'])) ?></div>
            </div>
        <?php endforeach; ?>
        <?php if ($canManageDocuments && $atts): ?>
            <div class="inquiry-documents">
                <strong>Documents</strong>
                <?php foreach ($atts as $a): ?>
                    <a class="attach-chip" href="<?= BASE_URL ?>/attachment_download.php?id=<?= (int)$a['attachment_id'] ?>">📎 <?= htmlspecialchars($a['file_name']) ?> · <?= htmlspecialchars($a['uploader_name'] ?? 'Customer') ?> · <?= date('M j, Y', strtotime($a['uploaded_at'])) ?></a>
                <?php endforeach; ?>
            </div>
        <?php elseif (!$canManageDocuments && $atts): ?>
            <p class="muted">Documents are available to the assigned staff member, HR, and Admin.</p>
        <?php endif; ?>
    </div>
    <p class="chat-sync-status" data-chat-status aria-live="polite" hidden></p>
    <?php if ($canManageDocuments && $u['role'] === 'Staff'): ?>
    <?php if (!$ticketComplete): ?>
    <form method="post" enctype="multipart/form-data" class="document-upload-form">
        <input type="hidden" name="action" value="upload_attachments">
        <input type="hidden" name="inquiry_id" value="<?= (int)$selected['inquiry_id'] ?>">
        <label for="staff-attachments">Add documents to this inquiry</label>
        <div class="reply-actions">
            <input id="staff-attachments" type="file" name="attachments[]" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.txt" multiple required>
            <button class="btn btn-outline btn-sm" type="submit">Upload files</button>
        </div>
        <small>Up to 5 files at a time, 10 MB each.</small>
    </form>
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($ticketComplete): ?>
        <div class="form-note">This inquiry is complete and read-only. Its messages and documents remain available for reference.</div>
    <?php else: ?>
    <div class="reply-box">
        <div class="staff-controls" style="margin-bottom:12px;">
            <form method="post" style="display:inline;">
                <input type="hidden" name="action" value="assign_department">
                <input type="hidden" name="inquiry_id" value="<?= $selected['inquiry_id'] ?>">
                <select name="department" onchange="this.form.submit()">
                    <option value="">Assign department…</option>
                    <?php foreach ($departments as $d): ?><option value="<?= $d ?>" <?= $selected['department']===$d?'selected':'' ?>><?= $d ?></option><?php endforeach; ?>
                </select>
            </form>
            <form method="post" style="display:inline;">
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="inquiry_id" value="<?= $selected['inquiry_id'] ?>">
                <select name="status" onchange="this.form.submit()">
                    <option value="">Change status…</option>
                    <?php foreach ($selectedStatusOrder as $s): ?><option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>" <?= $selected['status']===$s?'selected':'' ?>><?= htmlspecialchars($s) ?></option><?php endforeach; ?>
                </select>
            </form>
            <form method="post" style="display:inline;">
                <input type="hidden" name="action" value="request_info">
                <input type="hidden" name="inquiry_id" value="<?= $selected['inquiry_id'] ?>">
                <button class="btn btn-outline btn-sm" type="submit">Request More Info</button>
            </form>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="inquiry_id" value="<?= $selected['inquiry_id'] ?>">
            <textarea name="reply" placeholder="Write a reply to <?= htmlspecialchars($selected['full_name']) ?>…" required></textarea>
            <div class="reply-actions">
                <span style="font-size:11.5px;color:#8a969b;">Visible to the customer immediately.</span>
                <button class="btn btn-primary btn-sm" type="submit">Send Reply →</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
<?php endif; ?>
