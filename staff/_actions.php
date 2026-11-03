<?php
require_once __DIR__ . '/../includes/document_storage.php';
require_once __DIR__ . '/../includes/notifications.php';

if (empty($_SESSION['inquiry_quote_csrf'])) {
    $_SESSION['inquiry_quote_csrf'] = bin2hex(random_bytes(32));
}

// Include this AFTER config.php + requireRole() in any staff page that needs
// to handle assign/status/reply/request-info actions on a ticket.
// Redirects back to whichever page included it (queue.php or assigned.php).

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['inquiry_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'save_quote' && $id > 0) {
        $token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
        $amount = is_string($_POST['amount'] ?? null) ? trim($_POST['amount']) : '';
        $notes = is_string($_POST['quote_notes'] ?? null) ? trim($_POST['quote_notes']) : '';
        $validUntil = is_string($_POST['valid_until'] ?? null) ? $_POST['valid_until'] : '';
        $validDateFormat = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $validUntil) === 1;
        $validDate = $validDateFormat ? DateTimeImmutable::createFromFormat('!Y-m-d', $validUntil) : false;
        $dateErrors = DateTimeImmutable::getLastErrors();
        $validDateInput = $validDate !== false
            && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
            && $validDate->format('Y-m-d') === $validUntil;

        if (!hash_equals($_SESSION['inquiry_quote_csrf'], $token)) {
            $_SESSION['flash'] = 'Refresh the page before saving the quotation.';
        } elseif (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $amount) || (float)$amount <= 0) {
            $_SESSION['flash'] = 'Enter a quotation amount greater than zero with no more than two decimal places.';
        } elseif (!$validDateInput || $validUntil < date('Y-m-d')) {
            $_SESSION['flash'] = 'Choose a valid quotation expiry date that is today or later.';
        } elseif (mb_strlen($notes, 'UTF-8') > 5000) {
            $_SESSION['flash'] = 'Quotation notes must be no more than 5,000 characters.';
        } else {
            try {
                $pdo->beginTransaction();
                $inquiryStmt = $pdo->prepare('SELECT user_id, type, status, assigned_staff_id, ticket_no FROM inquiries WHERE inquiry_id = ? FOR UPDATE');
                $inquiryStmt->execute([$id]);
                $inquiry = $inquiryStmt->fetch();
                if (!$inquiry || (int)$inquiry['assigned_staff_id'] !== (int)$u['user_id']
                    || !isQuotableInquiryType($inquiry['type']) || isInquiryComplete($inquiry['status'])) {
                    throw new InvalidArgumentException('Only the assigned staff member can quote an active order or service request.');
                }

                $quoteStmt = $pdo->prepare("
                    INSERT INTO inquiry_quotes (inquiry_id, amount, notes, valid_until, status, quoted_by, quoted_at, responded_at)
                    VALUES (?, ?, ?, ?, 'offered', ?, NOW(), NULL)
                    ON DUPLICATE KEY UPDATE amount = VALUES(amount), notes = VALUES(notes),
                        valid_until = VALUES(valid_until), status = 'offered', quoted_by = VALUES(quoted_by),
                        quoted_at = NOW(), responded_at = NULL
                ");
                $quoteStmt->execute([$id, $amount, $notes, $validUntil, (int)$u['user_id']]);

                if ($inquiry['status'] !== 'Quoted' && in_array('Quoted', inquiryStatuses($inquiry['type']), true)) {
                    $pdo->prepare("UPDATE inquiries SET status = 'Quoted' WHERE inquiry_id = ?")->execute([$id]);
                    $pdo->prepare('INSERT INTO status_history (inquiry_id, old_status, new_status, changed_by) VALUES (?, ?, ?, ?)')
                        ->execute([$id, $inquiry['status'], 'Quoted', (int)$u['user_id']]);
                }

                createUserNotification(
                    $pdo,
                    (int)$inquiry['user_id'],
                    'Quotation received',
                    "A quotation is ready for request {$inquiry['ticket_no']}.",
                    BASE_URL . '/customer/inquiry_detail.php?id=' . $id
                );
                $pdo->commit();
                $_SESSION['flash'] = "Quotation saved and sent to the customer.";
            } catch (InvalidArgumentException $quoteError) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $_SESSION['flash'] = $quoteError->getMessage();
            } catch (Throwable $quoteError) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Staff quotation save failed: ' . $quoteError->getMessage());
                $_SESSION['flash'] = 'The quotation could not be saved. Please try again.';
            }
        }
        header('Location: ' . basename($_SERVER['SCRIPT_NAME']) . '?id=' . $id);
        exit;
    }

    $ticketStatus = $pdo->prepare('SELECT status FROM inquiries WHERE inquiry_id = ?');
    $ticketStatus->execute([$id]);
    $currentStatus = $ticketStatus->fetchColumn();
    if ($currentStatus === false || isInquiryComplete($currentStatus)) {
        $_SESSION['flash'] = $currentStatus === false
            ? 'This inquiry could not be found.'
            : 'This inquiry is complete and can no longer be changed.';
        header('Location: ' . basename($_SERVER['SCRIPT_NAME']) . '?id=' . $id);
        exit;
    }

    if ($action === 'upload_attachments' && $id) {
        $assigned = $pdo->prepare('SELECT assigned_staff_id FROM inquiries WHERE inquiry_id = ?');
        $assigned->execute([$id]);
        if ((int)$assigned->fetchColumn() !== (int)$u['user_id']) {
            $_SESSION['flash'] = 'Only the staff member assigned to this inquiry can add documents.';
        } else {
            try {
                $count = isset($_FILES['attachments'])
                    ? storeInquiryAttachments($pdo, $id, $_FILES['attachments'], (int)$u['user_id'], 'Staff')
                    : 0;
                if ($count === 0) {
                    throw new InvalidArgumentException('Choose at least one file to upload.');
                }
                $_SESSION['flash'] = $count === 1 ? 'Document uploaded.' : "$count documents uploaded.";
                $ticket = $pdo->prepare('SELECT user_id, ticket_no FROM inquiries WHERE inquiry_id = ?');
                $ticket->execute([$id]);
                $customer = $ticket->fetch();
                if ($customer) {
                    createUserNotification(
                        $pdo,
                        (int)$customer['user_id'],
                        'Documents added to your request',
                        "New documents were added to request {$customer['ticket_no']}.",
                        BASE_URL . '/customer/inquiry_detail.php?id=' . $id
                    );
                }
            } catch (InvalidArgumentException $uploadError) {
                $_SESSION['flash'] = $uploadError->getMessage();
            } catch (Throwable $uploadError) {
                error_log('Staff document upload failed: ' . $uploadError->getMessage());
                $_SESSION['flash'] = 'The documents could not be saved. Please try again.';
            }
        }
    }

    if ($action === 'assign_department' && $id) {
        $pdo->prepare("UPDATE inquiries SET department = ? WHERE inquiry_id = ?")
            ->execute([$_POST['department'], $id]);
        $_SESSION['flash'] = "Assigned to " . $_POST['department'] . ".";
    }

    if ($action === 'status' && $id) {
        $old = $pdo->prepare("SELECT status, type FROM inquiries WHERE inquiry_id = ?");
        $old->execute([$id]);
        $inquiry = $old->fetch();
        $newStatus = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
        if ($inquiry && in_array($newStatus, inquiryStatuses($inquiry['type']), true)) {
            $pdo->prepare("UPDATE inquiries SET status = ? WHERE inquiry_id = ?")
                ->execute([$newStatus, $id]);
            $pdo->prepare("INSERT INTO status_history (inquiry_id, old_status, new_status, changed_by) VALUES (?, ?, ?, ?)")
                ->execute([$id, $inquiry['status'], $newStatus, $u['user_id']]);
            if ($newStatus !== $inquiry['status']) {
                $owner = $pdo->prepare('SELECT user_id, ticket_no FROM inquiries WHERE inquiry_id = ?');
                $owner->execute([$id]);
                $customer = $owner->fetch();
                if ($customer) {
                    createUserNotification(
                        $pdo,
                        (int)$customer['user_id'],
                        'Request status updated',
                        "Request {$customer['ticket_no']} is now {$newStatus}.",
                        BASE_URL . '/customer/inquiry_detail.php?id=' . $id
                    );
                }
            }
            $_SESSION['flash'] = "Status updated to \"" . $newStatus . "\".";
        } else {
            $_SESSION['flash'] = 'That status is not available for this inquiry.';
        }
    }

    if ($action === 'reply' && $id) {
        $pdo->prepare("INSERT INTO messages (inquiry_id, sender_id, sender_role, body) VALUES (?, ?, 'Staff', ?)")
            ->execute([$id, $u['user_id'], trim($_POST['reply'])]);
        $ticket = $pdo->prepare('SELECT user_id, ticket_no FROM inquiries WHERE inquiry_id = ?');
        $ticket->execute([$id]);
        $customer = $ticket->fetch();
        if ($customer) {
            createUserNotification(
                $pdo,
                (int)$customer['user_id'],
                'New reply on your request',
                "A staff member replied to request {$customer['ticket_no']}.",
                BASE_URL . '/customer/inquiry_detail.php?id=' . $id
            );
        }
        $_SESSION['flash'] = "Reply sent.";
    }

    if ($action === 'request_info' && $id) {
        $old = $pdo->prepare("SELECT status, user_id, ticket_no FROM inquiries WHERE inquiry_id = ?");
        $old->execute([$id]);
        $inquiry = $old->fetch();
        $oldStatus = $inquiry['status'] ?? null;
        $pdo->prepare("UPDATE inquiries SET status = 'Awaiting Your Reply' WHERE inquiry_id = ?")->execute([$id]);
        $pdo->prepare("INSERT INTO status_history (inquiry_id, old_status, new_status, changed_by) VALUES (?, ?, 'Awaiting Your Reply', ?)")
            ->execute([$id, $oldStatus, $u['user_id']]);
        $pdo->prepare("INSERT INTO messages (inquiry_id, sender_id, sender_role, body) VALUES (?, ?, 'Staff', 'Could you provide some additional information so we can proceed?')")
            ->execute([$id, $u['user_id']]);
        if ($inquiry) {
            createUserNotification(
                $pdo,
                (int)$inquiry['user_id'],
                'Staff requested more information',
                "Please review the latest reply on request {$inquiry['ticket_no']}.",
                BASE_URL . '/customer/inquiry_detail.php?id=' . $id
            );
        }
        $_SESSION['flash'] = "Requested additional info from customer.";
    }

    // Redirect back to whichever page (queue.php or assigned.php) included this file.
    header('Location: ' . basename($_SERVER['SCRIPT_NAME']) . '?id=' . $id);
    exit;
}
