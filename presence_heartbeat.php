<?php
require __DIR__ . '/config/config.php';

header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

if (!in_array($_SESSION['user']['role'] ?? '', ['Customer', 'Staff', 'HR', 'Technician', 'Admin'], true)) {
    http_response_code(401);
    exit;
}

$pdo->prepare("UPDATE users SET last_active_at = NOW() WHERE user_id = ?")
    ->execute([$_SESSION['user']['user_id']]);

http_response_code(204);
