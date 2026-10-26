<?php
// Shared profile page for Staff, HR and Admin.
// The entry file (staff/profile.php, hr/profile.php, admin/profile.php) must set
// $sidebarInclude and $roleLabel, and call requireRole() BEFORE including this file.
require_once __DIR__ . '/profile_helpers.php';
$u = currentUser();
$error = null;
$row = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$row->execute([$u['user_id']]);
$me = $row->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = requireProfileFormCsrf() ? ($_POST['action'] ?? '') : '';
    if ($action === '') {
        $error = 'Refresh the page and try again.';
    }

    if ($action === 'status' && $error === null) {
        $allowed = ['available', 'break', 'offline'];
        $status = in_array($_POST['status'] ?? '', $allowed, true) ? $_POST['status'] : 'available';
        $pdo->prepare("UPDATE users SET manual_status = ? WHERE user_id = ?")->execute([$status, $u['user_id']]);
        $_SESSION['user']['manual_status'] = $status;
        $_SESSION['flash'] = 'Status updated.';
    }

    if ($action === 'profile' && $error === null) {
        $fullName = is_string($_POST['full_name'] ?? null) ? trim($_POST['full_name']) : '';
        $phone = is_string($_POST['phone'] ?? null) ? trim($_POST['phone']) : '';
        $bio = is_string($_POST['profile_bio'] ?? null) ? trim($_POST['profile_bio']) : '';
        if ($fullName === '' || mb_strlen($fullName, 'UTF-8') > 120) {
            $error = 'Your name is required and must be no more than 120 characters.';
        } elseif (mb_strlen($phone, 'UTF-8') > 30) {
            $error = 'Phone number must be no more than 30 characters.';
        } elseif (mb_strlen($bio, 'UTF-8') > 240) {
            $error = 'Your bio must be no more than 240 characters.';
        } else {
            $newImage = null;
            try {
                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $newImage = storeProfilePicture($_FILES['profile_picture']);
                }
                $oldPath = $me['profile_picture_path'] ?? null;
                $picturePath = $newImage[0] ?? $oldPath;
                if (($_POST['remove_picture'] ?? '') === '1' && $newImage === null) {
                    $picturePath = null;
                }
                $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, profile_bio = ?, profile_picture_path = ? WHERE user_id = ?")
                    ->execute([$fullName, $phone, $bio !== '' ? $bio : null, $picturePath, $u['user_id']]);
                if ($picturePath !== $oldPath) {
                    removeStoredProfilePicture($oldPath);
                }
                $_SESSION['user']['full_name'] = $fullName;
                $_SESSION['user']['profile_picture_path'] = $picturePath;
                $_SESSION['flash'] = 'Profile saved.';
            } catch (InvalidArgumentException $profileError) {
                $error = $profileError->getMessage();
            } catch (Throwable $profileError) {
                if ($newImage !== null && is_file($newImage[1]) && !unlink($newImage[1])) {
                    error_log('Could not remove failed profile picture upload: ' . $newImage[1]);
                }
                error_log('Profile update failed: ' . $profileError->getMessage());
                $error = 'Your profile could not be saved. Please try again.';
            }
        }
    }

    if ($action === 'password' && $error === null) {
        $row = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $row->execute([$u['user_id']]);
        $hash = $row->fetchColumn();
        $currentPassword = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
        $newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        $passwordError = passwordValidationError($newPassword);
        if (!is_string($hash) || !password_verify($currentPassword, $hash)) {
            $error = 'Your current password is incorrect.';
        } elseif ($passwordError !== null) {
            $error = $passwordError;
        } else {
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?")
                ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $u['user_id']]);
            $_SESSION['flash'] = 'Password changed.';
        }
    }

    if ($error === null) { header('Location: profile.php'); exit; }
}
$current = $me['manual_status'] ?? 'available';

$options = [
    'available' => ['Available', 'Shown as online while you are active'],
    'break'     => ['On break', 'Customers see you are away'],
    'offline'   => ['Offline', 'Customers see you are not available'],
];

$pageTitle = 'My Profile';
require __DIR__ . '/header.php';
?>
<div class="shell">
    <?php require $sidebarInclude; ?>
    <main class="shell-main">
        <div class="shell-topbar">
            <div><h2>My Profile</h2><div class="sub"><?= htmlspecialchars($roleLabel) ?><?= $me['department'] ? ' · ' . htmlspecialchars($me['department']) : '' ?></div></div>
            <button class="theme-toggle" type="button" data-theme-toggle aria-pressed="false" aria-label="Switch to dark appearance" title="Switch to dark appearance">☾</button>
        </div>
        <div class="shell-body">
            <?php if ($error): ?><div class="form-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="panel" style="margin-bottom:20px;">
                <div class="panel-head"><h3>My Status</h3></div>
                <div class="panel-body">
                    <p style="font-size:13px;color:var(--ink-soft);margin-top:0;">Customers see this next to your name on their orders.</p>
                    <form method="post" class="status-picker">
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(profileFormCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <?php foreach ($options as $value => [$label, $hint]): ?>
                            <button type="submit" name="status" value="<?= $value ?>" class="status-option s-<?= $value ?> <?= $current === $value ? 'selected' : '' ?>">
                                <span class="presence-dot"></span><?= $label ?><small><?= $hint ?></small>
                            </button>
                        <?php endforeach; ?>
                    </form>
                </div>
            </div>

            <div class="grid-2">
                <div class="panel">
                    <div class="panel-head"><h3>Profile</h3></div>
                    <div class="panel-body">
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="profile">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(profileFormCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <div class="field" style="display:flex;align-items:center;gap:14px;">
                                <input type="hidden" name="remove_picture" value="0" data-profile-picture-remove>
                                <input class="profile-picture-input" id="profile-picture" type="file" name="profile_picture" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-profile-picture-input>
                                <div class="profile-picture-control" data-profile-picture-control data-placeholder="<?= htmlspecialchars(mb_substr($me['full_name'], 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>">
                                    <label class="profile-picture-picker" for="profile-picture" title="Choose a profile picture">
                                    <?php if (!empty($me['profile_picture_path'])): ?>
                                        <img class="profile-photo-preview" src="<?= htmlspecialchars(profilePictureUrl((int)$me['user_id']), ENT_QUOTES, 'UTF-8') ?>" alt="Your profile picture">
                                    <?php else: ?>
                                        <div class="profile-photo-preview profile-photo-placeholder"><?= htmlspecialchars(mb_substr($me['full_name'], 0, 1, 'UTF-8')) ?></div>
                                    <?php endif; ?>
                                    <span class="profile-picture-overlay">Change</span>
                                    </label>
                                    <?php if (!empty($me['profile_picture_path'])): ?>
                                        <button class="profile-picture-remove" type="button" data-profile-picture-remove-button aria-label="Remove profile picture" title="Remove profile picture">×</button>
                                    <?php endif; ?>
                                </div>
                                <small class="profile-picture-status" role="alert" data-profile-picture-status hidden></small>
                            </div>
                            <div class="field"><label>Full name</label><input name="full_name" value="<?= htmlspecialchars($me['full_name']) ?>" maxlength="120" required></div>
                            <div class="field"><label>Email (your login)</label><input value="<?= htmlspecialchars($me['email']) ?>" disabled></div>
                            <div class="field"><label>Phone</label><input name="phone" value="<?= htmlspecialchars($me['phone'] ?? '') ?>" maxlength="30"></div>
                            <div class="field"><label>Short bio / status message</label><textarea name="profile_bio" maxlength="240" rows="3"><?= htmlspecialchars($me['profile_bio'] ?? '') ?></textarea><small>Shown to signed-in portal users where your profile appears.</small></div>
                            <button class="btn btn-outline" type="submit">Save Changes</button>
                        </form>
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-head"><h3>Change Password</h3></div>
                    <div class="panel-body">
                        <form method="post">
                            <input type="hidden" name="action" value="password">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(profileFormCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <div class="field"><label>Current password</label><input type="password" name="current_password" autocomplete="current-password" required></div>
                            <div class="field"><label>New password</label><input type="password" name="new_password" minlength="8" maxlength="72" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,72}" title="Use at least 8 characters and no more than 72 bytes, including a letter and a number." autocomplete="new-password" required><small>Use at least 8 characters, including a letter and a number.</small></div>
                            <button class="btn btn-outline" type="submit">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require __DIR__ . '/footer.php'; ?>
