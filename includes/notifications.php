<?php

function createUserNotification(PDO $pdo, int $userId, string $title, string $message, string $url): void {
    if ($userId < 1 || $title === '' || $message === '' || !str_starts_with($url, '/') ||
        str_starts_with($url, '//') || preg_match('/[\r\n]/', $url)) {
        throw new InvalidArgumentException('Notification details are invalid.');
    }
    $stmt = $pdo->prepare('INSERT INTO user_notifications (user_id, title, message, target_url) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, mb_substr($title, 0, 120), mb_substr($message, 0, 255), mb_substr($url, 0, 500)]);
}
