<?php
require __DIR__ . '/config/config.php';
requireRole('Customer');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function notificationsJson(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

$user = currentUser();
$userId = (int)$user['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT notification_id, title, message, target_url, created_at, read_at
        FROM user_notifications WHERE user_id = ? ORDER BY notification_id DESC LIMIT 20');
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();
    $count = $pdo->prepare('SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND read_at IS NULL');
    $count->execute([$userId]);
    notificationsJson(['notifications' => $notifications, 'unread_count' => (int)$count->fetchColumn()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    notificationsJson(['error' => 'Method not allowed.'], 405);
}

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($token) || empty($_SESSION['notifications_csrf']) ||
    !hash_equals($_SESSION['notifications_csrf'], $token)) {
    notificationsJson(['error' => 'Your session expired. Refresh the page and try again.'], 403);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) notificationsJson(['error' => 'Invalid request.'], 400);
$action = $payload['action'] ?? '';

if ($action === 'read_all') {
    $stmt = $pdo->prepare('UPDATE user_notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL');
    $stmt->execute([$userId]);
    notificationsJson(['updated' => $stmt->rowCount()]);
}

if ($action === 'read') {
    $notificationId = filter_var($payload['notification_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$notificationId || $notificationId < 1) notificationsJson(['error' => 'Invalid notification.'], 400);
    $stmt = $pdo->prepare('UPDATE user_notifications SET read_at = NOW() WHERE notification_id = ? AND user_id = ? AND read_at IS NULL');
    $stmt->execute([$notificationId, $userId]);
    notificationsJson(['updated' => $stmt->rowCount()]);
}

notificationsJson(['error' => 'Unknown request.'], 400);
