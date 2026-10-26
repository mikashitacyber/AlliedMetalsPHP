<?php
require_once __DIR__ . '/config/config.php';
requireAnyRole(['Customer', 'Staff', 'HR', 'Technician', 'Admin']);

$messageId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$messageId) {
    http_response_code(404);
    exit;
}

$viewer = currentUser();
$viewerId = (int)$viewer['user_id'];
$isEmployee = in_array($viewer['role'], ['Staff', 'HR', 'Technician', 'Admin'], true);
$stmt = $pdo->prepare("
    SELECT dm.attachment_path, dm.attachment_mime
    FROM customer_direct_messages dm
    JOIN customer_conversations conv ON conv.conversation_id = dm.conversation_id
    WHERE dm.message_id = ? AND " . ($isEmployee ? 'conv.employee_id' : 'conv.customer_id') . " = ?
");
$stmt->execute([$messageId, $viewerId]);
$attachment = $stmt->fetch();
$extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!$attachment || !isset($extensions[$attachment['attachment_mime']])) {
    http_response_code(404);
    exit;
}

$expectedPrefix = '/uploads/direct_messages/';
$relativePath = $attachment['attachment_path'];
if (!is_string($relativePath) || !str_starts_with($relativePath, $expectedPrefix)
    || !preg_match('~\A/uploads/direct_messages/([1-9][0-9]*)/([a-f0-9]{32})\.(jpg|png|webp)\z~', $relativePath, $matches)
    || $matches[3] !== $extensions[$attachment['attachment_mime']]) {
    http_response_code(404);
    exit;
}
$file = __DIR__ . '/uploads/direct_messages/' . $matches[1] . '/' . $matches[2] . '.' . $matches[3];
if (!is_file($file)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $attachment['attachment_mime']);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
header('Content-Length: ' . filesize($file));
readfile($file);
