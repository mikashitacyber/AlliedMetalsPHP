<?php
function profilePictureUrl(int $userId): string {
    return BASE_URL . '/profile_picture.php?id=' . $userId;
}

function requireProfileFormCsrf(): bool {
    $token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    return isset($_SESSION['profile_csrf']) && hash_equals($_SESSION['profile_csrf'], $token);
}

function storeProfilePicture(array $file): array {
    $uploadError = $file['error'] ?? null;
    if (!is_int($uploadError)) {
        throw new InvalidArgumentException('Choose a profile picture to upload.');
    }
    if ($uploadError !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'The selected photo exceeds the server upload limit. Choose a smaller file.',
            UPLOAD_ERR_FORM_SIZE => 'The selected photo is too large. Choose a smaller file.',
            UPLOAD_ERR_PARTIAL => 'The photo upload was interrupted. Please select it and try again.',
            UPLOAD_ERR_NO_FILE => 'Choose a JPG, PNG, or WebP photo first.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server cannot receive uploads right now. Contact the administrator.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not store the upload. Contact the administrator.',
            UPLOAD_ERR_EXTENSION => 'The server stopped this upload. Contact the administrator.',
        ];
        throw new InvalidArgumentException($uploadErrors[$uploadError] ?? 'The photo upload failed. Please try again.');
    }
    if (!isset($file['tmp_name']) || !is_string($file['tmp_name'])) {
        throw new InvalidArgumentException('The selected profile picture is invalid.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('The selected profile picture is invalid.');
    }
    $size = filesize($file['tmp_name']);
    if ($size === false || $size < 1 || $size > 2 * 1024 * 1024) {
        throw new InvalidArgumentException('Profile pictures must be no more than 2 MB.');
    }
    if (!class_exists('finfo')) {
        throw new RuntimeException('Image validation is unavailable.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $image = @getimagesize($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!is_string($mime) || !isset($allowed[$mime]) || $image === false || ($image['mime'] ?? '') !== $mime) {
        throw new InvalidArgumentException('Use a valid JPG, PNG, or WebP image.');
    }
    if ($image[0] > 5000 || $image[1] > 5000 || $image[0] * $image[1] > 12000000) {
        throw new InvalidArgumentException('The image dimensions are too large. Use an image under 12 megapixels.');
    }

    $directory = __DIR__ . '/../uploads/profile/';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create the profile image directory.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $destination = $directory . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Could not store the profile picture.');
    }
    return ['/uploads/profile/' . $filename, $destination];
}

function removeStoredProfilePicture(?string $relativePath): void {
    if (!is_string($relativePath) || !preg_match('~\A/uploads/profile/[a-f0-9]{32}\.(?:jpg|png|webp)\z~', $relativePath)) {
        return;
    }
    $file = __DIR__ . '/../' . ltrim($relativePath, '/');
    if (is_file($file) && !unlink($file)) {
        error_log('Could not remove profile picture: ' . $file);
    }
}

function profileFormCsrfToken(): string {
    if (empty($_SESSION['profile_csrf'])) {
        $_SESSION['profile_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['profile_csrf'];
}
