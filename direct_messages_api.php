<?php
require __DIR__ . '/config/config.php';
requireAnyRole(['Customer', 'Staff', 'HR', 'Technician', 'Admin']);
require_once __DIR__ . '/includes/notifications.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function directMessageJson(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function isDirectMessageEmployee(string $role): bool {
    return in_array($role, ['Staff', 'HR', 'Technician', 'Admin'], true);
}

function directMessagePresence(array $row): array {
    [$text, $class] = presenceLabel($row['last_active_at'], $row['manual_status']);
    return [$text, $class];
}

$user = currentUser();
$userId = (int)$user['user_id'];
$isEmployee = isDirectMessageEmployee($user['role']);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = is_string($_GET['action'] ?? null) ? $_GET['action'] : 'contacts';
    if ($action === 'contacts') {
        $search = is_string($_GET['q'] ?? null) ? trim(mb_substr($_GET['q'], 0, 80, 'UTF-8')) : '';
        if ($isEmployee) {
            $sql = "
                SELECT c.user_id, c.full_name, c.company, c.profile_picture_path,
                    c.last_active_at, c.manual_status, conv.conversation_id,
                    (SELECT COALESCE(NULLIF(body, ''), 'Photo') FROM customer_direct_messages dm
                     WHERE dm.conversation_id = conv.conversation_id
                     ORDER BY dm.message_id DESC LIMIT 1) AS last_message,
                    conv.updated_at
                FROM users c
                LEFT JOIN customer_conversations conv
                    ON conv.customer_id = c.user_id AND conv.employee_id = ?
                WHERE c.role = 'Customer' AND c.is_active = 1
            ";
            $params = [$userId];
            if ($search !== '') {
                $sql .= " AND (c.full_name LIKE ? OR c.company LIKE ?)";
                $term = '%' . $search . '%';
                $params[] = $term;
                $params[] = $term;
            }
            $sql .= " ORDER BY (c.manual_status = 'available' AND c.last_active_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)) DESC, c.full_name LIMIT 100";
        } else {
            $sql = "
                SELECT e.user_id, e.full_name, e.department, e.role, e.profile_picture_path,
                    e.last_active_at, e.manual_status, conv.conversation_id,
                    (SELECT COALESCE(NULLIF(body, ''), 'Photo') FROM customer_direct_messages dm
                     WHERE dm.conversation_id = conv.conversation_id
                     ORDER BY dm.message_id DESC LIMIT 1) AS last_message,
                    conv.updated_at
                FROM customer_conversations conv
                JOIN users e ON e.user_id = conv.employee_id
                WHERE conv.customer_id = ? AND e.is_active = 1
                ORDER BY conv.updated_at DESC
            ";
            $params = [$userId];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $contacts = [];
        foreach ($stmt->fetchAll() as $row) {
            [$presenceText, $presenceClass] = directMessagePresence($row);
            $contacts[] = [
                'id' => (int)$row['user_id'],
                'name' => $row['full_name'],
                'detail' => $isEmployee ? $row['company'] : ($row['role'] === 'Technician' ? 'Field Technician' : ($row['department'] ?: $row['role'])),
                'picture' => !empty($row['profile_picture_path']) ? BASE_URL . '/profile_picture.php?id=' . (int)$row['user_id'] : null,
                'presence_text' => $presenceText,
                'presence_class' => $presenceClass,
                'conversation_id' => $row['conversation_id'] === null ? null : (int)$row['conversation_id'],
                'last_message' => $row['last_message'],
            ];
        }
        directMessageJson(['contacts' => $contacts]);
    }

    if ($action === 'messages') {
        $conversationId = filter_var($_GET['conversation_id'] ?? null, FILTER_VALIDATE_INT);
        $afterId = filter_var($_GET['after_id'] ?? 0, FILTER_VALIDATE_INT);
        if (!$conversationId || $afterId === false) directMessageJson(['error' => 'Invalid conversation.'], 400);
        $access = $pdo->prepare("
            SELECT conversation_id FROM customer_conversations
            WHERE conversation_id = ? AND " . ($isEmployee ? 'employee_id' : 'customer_id') . " = ?
        ");
        $access->execute([$conversationId, $userId]);
        if (!$access->fetchColumn()) directMessageJson(['error' => 'Conversation not found.'], 404);
        $stmt = $pdo->prepare("
            SELECT message_id, sender_id, body, attachment_mime, created_at
            FROM customer_direct_messages
            WHERE conversation_id = ? AND message_id > ?
            ORDER BY message_id ASC LIMIT 100
        ");
        $stmt->execute([$conversationId, $afterId]);
        $messages = $stmt->fetchAll();
        foreach ($messages as &$message) {
            $message['image_url'] = $message['attachment_mime'] !== null
                ? BASE_URL . '/direct_message_image.php?id=' . (int)$message['message_id']
                : null;
        }
        unset($message);
        directMessageJson(['messages' => $messages]);
    }

    directMessageJson(['error' => 'Unknown request.'], 400);
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    directMessageJson(['error' => 'Method not allowed.'], 405);
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($csrfToken) || empty($_SESSION['direct_messages_csrf']) || !hash_equals($_SESSION['direct_messages_csrf'], $csrfToken)) {
    directMessageJson(['error' => 'Your session expired. Refresh the page and try again.'], 403);
}
$isMultipart = str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data');
$payload = $isMultipart ? $_POST : json_decode(file_get_contents('php://input'), true);
if ($isMultipart && (!is_array($payload) || $payload === []) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    directMessageJson(['error' => 'The picture request is too large for the server. Choose a smaller photo and try again.'], 413);
}
if (!is_array($payload)) directMessageJson(['error' => 'Invalid request.'], 400);
$action = is_string($payload['action'] ?? null) ? $payload['action'] : '';

if ($action === 'start') {
    if (!$isEmployee) directMessageJson(['error' => 'Only employees can start a direct conversation.'], 403);
    $customerId = filter_var($payload['customer_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$customerId || $customerId === $userId) directMessageJson(['error' => 'Choose a valid customer.'], 400);
    $customer = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'Customer' AND is_active = 1");
    $customer->execute([$customerId]);
    if (!$customer->fetchColumn()) directMessageJson(['error' => 'That customer account is not available.'], 404);
    $create = $pdo->prepare("
        INSERT INTO customer_conversations (employee_id, customer_id)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE conversation_id = LAST_INSERT_ID(conversation_id)
    ");
    $create->execute([$userId, $customerId]);
    $conversationId = (int)$pdo->lastInsertId();
    directMessageJson(['conversation_id' => $conversationId]);
}

if ($action === 'send') {
    $conversationId = filter_var($payload['conversation_id'] ?? null, FILTER_VALIDATE_INT);
    $body = is_string($payload['body'] ?? null) ? trim($payload['body']) : '';
    $hasUpload = isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if (!$conversationId || mb_strlen($body, 'UTF-8') > 2000 || ($body === '' && !$hasUpload)) {
        directMessageJson(['error' => 'Write a message or attach a picture. Messages can be up to 2,000 characters.'], 400);
    }
    $access = $pdo->prepare("
        SELECT conversation_id FROM customer_conversations
        WHERE conversation_id = ? AND " . ($isEmployee ? 'employee_id' : 'customer_id') . " = ?
    ");
    $access->execute([$conversationId, $userId]);
    if (!$access->fetchColumn()) directMessageJson(['error' => 'Conversation not found.'], 404);
    $storedImage = null;
    if ($hasUpload) {
        $upload = $_FILES['image'];
        if (!is_array($upload) || !is_int($upload['error'] ?? null) || $upload['error'] !== UPLOAD_ERR_OK) {
            $uploadError = is_array($upload) ? ($upload['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
            $message = match ($uploadError) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The picture is too large to upload. Choose a smaller photo.',
                UPLOAD_ERR_PARTIAL => 'The picture upload was interrupted. Check your connection and try again.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'The server could not receive the picture. Please try again later.',
                default => 'The picture upload failed. Please select it again.',
            };
            directMessageJson(['error' => $message], 400);
        }
        if (!isset($upload['tmp_name']) || !is_string($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
            directMessageJson(['error' => 'The selected picture is invalid.'], 400);
        }
        $size = filesize($upload['tmp_name']);
        if ($size === false || $size < 1 || $size > 5 * 1024 * 1024) {
            directMessageJson(['error' => 'Pictures must be no larger than 5 MB.'], 400);
        }
        if (!class_exists('finfo')) directMessageJson(['error' => 'Image validation is unavailable. Contact the administrator.'], 500);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        $imageInfo = @getimagesize($upload['tmp_name']);
        $allowedImages = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!is_string($mime) || !isset($allowedImages[$mime]) || $imageInfo === false || ($imageInfo['mime'] ?? '') !== $mime) {
            directMessageJson(['error' => 'Choose a valid JPG, PNG, or WebP picture.'], 400);
        }
        if ($imageInfo[0] > 5000 || $imageInfo[1] > 5000 || $imageInfo[0] * $imageInfo[1] > 12000000) {
            directMessageJson(['error' => 'The picture dimensions are too large. Choose an image under 12 megapixels.'], 400);
        }
        $directory = __DIR__ . '/uploads/direct_messages/' . $conversationId . '/';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            directMessageJson(['error' => 'The server could not prepare picture storage.'], 500);
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $allowedImages[$mime];
        $relativePath = '/uploads/direct_messages/' . $conversationId . '/' . $filename;
        $destination = $directory . $filename;
        if (!move_uploaded_file($upload['tmp_name'], $destination)) {
            directMessageJson(['error' => 'The server could not store the picture.'], 500);
        }
        $storedImage = [$relativePath, $mime, $destination];
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO customer_direct_messages (conversation_id, sender_id, body, attachment_path, attachment_mime) VALUES (?, ?, ?, ?, ?)")
            ->execute([$conversationId, $userId, $body, $storedImage[0] ?? null, $storedImage[1] ?? null]);
        $pdo->prepare("UPDATE customer_conversations SET updated_at = NOW() WHERE conversation_id = ?")
            ->execute([$conversationId]);
        $messageId = (int)$pdo->lastInsertId();
        if ($isEmployee) {
            $recipient = $pdo->prepare('SELECT customer_id FROM customer_conversations WHERE conversation_id = ?');
            $recipient->execute([$conversationId]);
            $customerId = $recipient->fetchColumn();
            if ($customerId !== false) {
                createUserNotification(
                    $pdo,
                    (int)$customerId,
                    'New message from Allied Metals',
                    'An employee sent you a direct message.',
                    BASE_URL . '/messages.php'
                );
            }
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($storedImage !== null && is_file($storedImage[2]) && !unlink($storedImage[2])) {
            error_log('Could not remove a failed direct message image upload: ' . $storedImage[2]);
        }
        error_log('Direct message send failed: ' . $error->getMessage());
        directMessageJson(['error' => 'The message could not be sent. Please try again.'], 500);
    }
    directMessageJson(['message_id' => $messageId]);
}

directMessageJson(['error' => 'Unknown request.'], 400);
