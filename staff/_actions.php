<?php
require_once __DIR__ . '/../includes/document_storage.php';
require_once __DIR__ . '/../includes/notifications.php';

// Include this AFTER config.php + requireRole() in any staff page that needs
// to handle assign/status/reply/request-info actions on a ticket.
// Redirects back to whichever page included it (queue.php or assigned.php).

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['inquiry_id'] ?? 0);
    $action = $_POST['action'] ?? '';

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
