<?php
require __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

function sendInquiryMessagesResponse(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    sendInquiryMessagesResponse(405, ['error' => 'Only GET requests are accepted.']);
}

$user = currentUser();
if (!$user || !in_array($user['role'], ['Customer', 'Staff'], true)) {
    sendInquiryMessagesResponse(401, ['error' => 'Please sign in to view this conversation.']);
}

$inquiryId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$afterId = filter_input(INPUT_GET, 'after_id', FILTER_VALIDATE_INT);
if (!$inquiryId || $inquiryId < 1 || $afterId === false || ($afterId !== null && $afterId < 0)) {
    sendInquiryMessagesResponse(400, ['error' => 'The conversation request is invalid.']);
}

if ($user['role'] === 'Customer') {
    $access = $pdo->prepare('SELECT 1 FROM inquiries WHERE inquiry_id = ? AND user_id = ?');
    $access->execute([$inquiryId, $user['user_id']]);
} else {
    $access = $pdo->prepare('SELECT 1 FROM inquiries WHERE inquiry_id = ?');
    $access->execute([$inquiryId]);
}

if (!$access->fetchColumn()) {
    sendInquiryMessagesResponse(404, ['error' => 'This conversation could not be found.']);
}

session_write_close();

$messages = $pdo->prepare("
    SELECT message_id, sender_role, body
    FROM messages
    WHERE inquiry_id = ? AND message_id > ?
    ORDER BY message_id ASC
");
$messages->execute([$inquiryId, $afterId ?: 0]);
sendInquiryMessagesResponse(200, ['messages' => $messages->fetchAll(PDO::FETCH_ASSOC)]);
