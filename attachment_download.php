<?php
require __DIR__ . '/config/config.php';

$attachmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$user = currentUser();
if (!$attachmentId || $attachmentId < 1 || !$user) {
    http_response_code(404);
    exit;
}

$stmt = $pdo->prepare("
    SELECT a.file_name, a.file_path, i.user_id AS customer_id, i.assigned_staff_id
    FROM attachments a
    JOIN inquiries i ON i.inquiry_id = a.inquiry_id
    WHERE a.attachment_id = ?
");
$stmt->execute([$attachmentId]);
$attachment = $stmt->fetch();
if (!$attachment) {
    http_response_code(404);
    exit;
}

$role = $user['role'];
$allowed = match ($role) {
    'Customer' => (int)$attachment['customer_id'] === (int)$user['user_id'],
    'Staff' => (int)$attachment['assigned_staff_id'] === (int)$user['user_id'],
    'HR', 'Admin' => true,
    default => false,
};
if (!$allowed) {
    http_response_code(404);
    exit;
}

$relativePath = str_replace('\\', '/', (string)$attachment['file_path']);
if (!str_starts_with($relativePath, '/uploads/') || str_contains($relativePath, "\0")) {
    http_response_code(404);
    exit;
}
$uploadRoot = realpath(__DIR__ . '/uploads');
$filePath = realpath(__DIR__ . substr($relativePath, 1));
if (!$uploadRoot || !$filePath || strncasecmp($filePath, $uploadRoot . DIRECTORY_SEPARATOR, strlen($uploadRoot . DIRECTORY_SEPARATOR)) !== 0 || !is_file($filePath)) {
    http_response_code(404);
    exit;
}

$fileName = basename((string)$attachment['file_name']);
$fallbackName = preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName);
$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($filePath) ?: 'application/octet-stream';
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: attachment; filename="' . $fallbackName . '"; filename*=UTF-8\'\'' . rawurlencode($fileName));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($filePath);
