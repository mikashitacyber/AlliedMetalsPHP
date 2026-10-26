<?php

function storeInquiryAttachments(PDO $pdo, int $inquiryId, array $files, int $uploaderId, string $uploaderRole): int {
    $keys = ['name', 'tmp_name', 'error'];
    foreach ($keys as $key) {
        if (!isset($files[$key]) || !is_array($files[$key])) {
            throw new InvalidArgumentException('The selected attachments could not be read.');
        }
    }
    if (array_keys($files['name']) !== array_keys($files['tmp_name']) || array_keys($files['name']) !== array_keys($files['error'])) {
        throw new InvalidArgumentException('The selected attachments could not be read.');
    }
    if (count($files['name']) > 5) {
        throw new InvalidArgumentException('You can attach no more than 5 files at a time.');
    }
    if (!class_exists('finfo')) {
        throw new RuntimeException('File validation is unavailable.');
    }

    $allowedTypes = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'doc' => ['application/msword', 'application/CDFV2', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/CDFV2', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'txt' => ['text/plain'],
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $validated = [];

    foreach ($files['name'] as $index => $originalName) {
        $uploadError = $files['error'][$index];
        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($uploadError !== UPLOAD_ERR_OK || !is_string($originalName) || !is_string($files['tmp_name'][$index])) {
            throw new InvalidArgumentException('One of the attachments could not be uploaded. Please try again.');
        }
        if (!is_uploaded_file($files['tmp_name'][$index])) {
            throw new InvalidArgumentException('One of the selected attachments is invalid.');
        }
        $fileSize = filesize($files['tmp_name'][$index]);
        if ($fileSize === false || $fileSize < 1 || $fileSize > 10 * 1024 * 1024) {
            throw new InvalidArgumentException('Each attachment must be no more than 10 MB.');
        }

        $clientName = basename(str_replace('\\', '/', $originalName));
        $extension = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));
        $mimeType = $finfo->file($files['tmp_name'][$index]);
        if (!isset($allowedTypes[$extension]) || !in_array($mimeType, $allowedTypes[$extension], true)) {
            throw new InvalidArgumentException('Attachments must be PDF, JPG, PNG, DOC, DOCX, XLS, XLSX, or TXT files.');
        }
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $clientName);
        $validated[] = [
            'tmp_name' => $files['tmp_name'][$index],
            'file_name' => substr($safeName, 0, 180),
            'extension' => $extension,
        ];
    }

    if (!$validated) return 0;

    $ticket = $pdo->prepare('SELECT ticket_no FROM inquiries WHERE inquiry_id = ?');
    $ticket->execute([$inquiryId]);
    $ticketNo = $ticket->fetchColumn();
    if (!$ticketNo || !preg_match('/\A[A-Za-z0-9_-]{1,20}\z/', $ticketNo)) {
        throw new RuntimeException('The inquiry for these attachments could not be found.');
    }

    $uploadDir = __DIR__ . '/../uploads/' . $ticketNo . '/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Could not create the inquiry attachment directory.');
    }

    $movedFiles = [];
    $insertedIds = [];
    try {
        $insert = $pdo->prepare(
            'INSERT INTO attachments (inquiry_id, file_name, file_path, uploaded_by, uploaded_role) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($validated as $upload) {
            $storedName = bin2hex(random_bytes(16)) . '.' . $upload['extension'];
            $destination = $uploadDir . $storedName;
            if (!move_uploaded_file($upload['tmp_name'], $destination)) {
                throw new RuntimeException('Could not store an inquiry attachment.');
            }
            $movedFiles[] = $destination;
            $relativePath = '/uploads/' . $ticketNo . '/' . $storedName;
            $insert->execute([$inquiryId, $upload['file_name'], $relativePath, $uploaderId, $uploaderRole]);
            $insertedIds[] = (int)$pdo->lastInsertId();
        }
    } catch (Throwable $error) {
        if ($insertedIds) {
            $placeholders = implode(',', array_fill(0, count($insertedIds), '?'));
            $pdo->prepare("DELETE FROM attachments WHERE attachment_id IN ($placeholders)")->execute($insertedIds);
        }
        foreach ($movedFiles as $movedFile) {
            if (is_file($movedFile) && !unlink($movedFile)) {
                error_log('Could not remove failed inquiry attachment: ' . $movedFile);
            }
        }
        throw $error;
    }

    return count($validated);
}
