<?php
require_once __DIR__ . '/config/config.php';

if (!currentUser()) {
    http_response_code(401);
    exit;
}

$requestedId = $_GET['id'] ?? null;
$userId = is_string($requestedId) || is_int($requestedId)
    ? filter_var($requestedId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($userId === false || $userId === null) {
    http_response_code(404);
    exit;
}

$stmt = $pdo->prepare('SELECT role, is_active, profile_picture_path FROM users WHERE user_id = ?');
$stmt->execute([$userId]);
$profile = $stmt->fetch();
$viewerId = (int)currentUser()['user_id'];
$isDirectoryEmployee = $profile
    && in_array($profile['role'], ['Staff', 'Technician'], true)
    && (int)$profile['is_active'] === 1;
$viewerRole = currentUser()['role'] ?? '';
$isEmployeeViewer = in_array($viewerRole, ['Staff', 'HR', 'Technician', 'Admin'], true);
$isVisibleCustomer = $profile
    && $profile['role'] === 'Customer'
    && (int)$profile['is_active'] === 1
    && $isEmployeeViewer;
if (!$profile || (!$isDirectoryEmployee && !$isVisibleCustomer && (int)$userId !== $viewerId)) {
    http_response_code(404);
    exit;
}

$path = $profile['profile_picture_path'];
if (!is_string($path) || !preg_match('~\A/uploads/profile/([a-f0-9]{32})\.(jpg|png|webp)\z~', $path, $matches)) {
    http_response_code(404);
    exit;
}
$file = __DIR__ . '/uploads/profile/' . $matches[1] . '.' . $matches[2];
if (!is_file($file)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$matches[2]]);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
header('Content-Length: ' . filesize($file));
readfile($file);
