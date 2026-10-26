<?php
require __DIR__ . '/../config/config.php';
requireRole('Customer');
require_once __DIR__ . '/../includes/profile_helpers.php';
$u = currentUser();
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$u['user_id']]);
$row = $stmt->fetch();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!requireProfileFormCsrf()) {
        $error = 'Refresh the page and try again.';
    } else {
        $fullName = is_string($_POST['full_name'] ?? null) ? trim($_POST['full_name']) : '';
        $company = is_string($_POST['company'] ?? null) ? trim($_POST['company']) : '';
        $phone = is_string($_POST['phone'] ?? null) ? trim($_POST['phone']) : '';
        $bio = is_string($_POST['profile_bio'] ?? null) ? trim($_POST['profile_bio']) : '';
        if ($fullName === '' || mb_strlen($fullName, 'UTF-8') > 120) {
            $error = 'Full name is required and must be no more than 120 characters.';
        } elseif ($company === '' || mb_strlen($company, 'UTF-8') > 160) {
            $error = 'Company is required and must be no more than 160 characters.';
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
                $oldPath = $row['profile_picture_path'] ?? null;
                $picturePath = $newImage[0] ?? $oldPath;
                if (($_POST['remove_picture'] ?? '') === '1' && $newImage === null) {
                    $picturePath = null;
                }
                $pdo->prepare("UPDATE users SET full_name = ?, company = ?, phone = ?, profile_bio = ?, profile_picture_path = ? WHERE user_id = ?")
                    ->execute([$fullName, $company, $phone, $bio !== '' ? $bio : null, $picturePath, $u['user_id']]);
                if ($picturePath !== $oldPath) {
                    removeStoredProfilePicture($oldPath);
                }
                $_SESSION['user']['full_name'] = $fullName;
                $_SESSION['user']['company'] = $company;
                $_SESSION['user']['profile_picture_path'] = $picturePath;
                $_SESSION['flash'] = 'Profile updated.';
                header('Location: profile.php');
                exit;
            } catch (InvalidArgumentException $profileError) {
                $error = $profileError->getMessage();
            } catch (Throwable $profileError) {
                if ($newImage !== null && is_file($newImage[1]) && !unlink($newImage[1])) {
                    error_log('Could not remove failed profile picture upload: ' . $newImage[1]);
                }
                error_log('Customer profile update failed: ' . $profileError->getMessage());
                $error = 'Your profile could not be saved. Please try again.';
            }
        }
    }
}

$pageTitle = 'Profile';
require __DIR__ . '/../includes/header.php';
?>
<div class="shell">
    <?php $activeTab = 'profile'; require __DIR__ . '/_sidebar.php'; ?>
    <main class="shell-main">
        <div class="shell-topbar">
            <div><h2>Profile &amp; Company</h2><div class="sub">Keep your contact and company details current.</div></div>
            <button class="theme-toggle" type="button" data-theme-toggle aria-pressed="false" aria-label="Switch to dark appearance" title="Switch to dark appearance">☾</button>
        </div>
        <div class="shell-body">
            <div class="panel" style="max-width:520px;">
                <div class="panel-head"><h3>Profile &amp; Company</h3></div>
                <div class="panel-body">
                    <?php if (!empty($error)): ?><div class="form-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(profileFormCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="field" style="display:flex;align-items:center;gap:14px;">
                            <input type="hidden" name="remove_picture" value="0" data-profile-picture-remove>
                            <input class="profile-picture-input" id="profile-picture" type="file" name="profile_picture" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-profile-picture-input>
                            <div class="profile-picture-control" data-profile-picture-control data-placeholder="<?= htmlspecialchars(mb_substr($row['full_name'], 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>">
                                <label class="profile-picture-picker" for="profile-picture" title="Choose a profile picture">
                                <?php if (!empty($row['profile_picture_path'])): ?>
                                    <img class="profile-photo-preview" src="<?= htmlspecialchars(profilePictureUrl((int)$row['user_id']), ENT_QUOTES, 'UTF-8') ?>" alt="Your profile picture">
                                <?php else: ?>
                                    <div class="profile-photo-preview profile-photo-placeholder"><?= htmlspecialchars(mb_substr($row['full_name'], 0, 1, 'UTF-8')) ?></div>
                                <?php endif; ?>
                                <span class="profile-picture-overlay">Change</span>
                                </label>
                                <?php if (!empty($row['profile_picture_path'])): ?>
                                    <button class="profile-picture-remove" type="button" data-profile-picture-remove-button aria-label="Remove profile picture" title="Remove profile picture">×</button>
                                <?php endif; ?>
                            </div>
                            <small class="profile-picture-status" role="alert" data-profile-picture-status hidden></small>
                        </div>
                        <div class="field"><label>Full name</label><input name="full_name" value="<?= htmlspecialchars($row['full_name']) ?>" maxlength="120" required></div>
                        <div class="field"><label>Company / Establishment</label><input name="company" value="<?= htmlspecialchars($row['company']) ?>" maxlength="160" required></div>
                        <div class="field"><label>Email address</label><input value="<?= htmlspecialchars($row['email']) ?>" disabled></div>
                        <div class="field"><label>Phone number</label><input name="phone" value="<?= htmlspecialchars($row['phone'] ?? '') ?>" maxlength="30"></div>
                        <div class="field"><label>Short bio / status message</label><textarea name="profile_bio" maxlength="240" rows="3"><?= htmlspecialchars($row['profile_bio'] ?? '') ?></textarea><small>Shown to signed-in portal users where your profile appears.</small></div>
                        <button class="btn btn-outline" type="submit">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
