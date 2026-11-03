<?php
require __DIR__ . '/config/config.php';

$adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Admin'")->fetchColumn();
$error = null;
$created = false;

if (empty($_SESSION['first_admin_csrf'])) {
    $_SESSION['first_admin_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $adminCount === 0) {
    $token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    $fullName = is_string($_POST['full_name'] ?? null) ? trim($_POST['full_name']) : '';
    $company = is_string($_POST['company'] ?? null) ? trim($_POST['company']) : '';
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $passwordError = passwordValidationError($password);

    if (!hash_equals($_SESSION['first_admin_csrf'], $token)) {
        $error = 'Refresh this page and try again.';
    } elseif ($fullName === '' || mb_strlen($fullName, 'UTF-8') > 120) {
        $error = 'Enter a name of no more than 120 characters.';
    } elseif ($company === '' || mb_strlen($company, 'UTF-8') > 160) {
        $error = 'Enter a company name of no more than 160 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email, 'UTF-8') > 160) {
        $error = 'Enter a valid email address of no more than 160 characters.';
    } elseif ($passwordError !== null) {
        $error = $passwordError;
    } else {
        try {
            $pdo->beginTransaction();
            $lockedAdmins = $pdo->query("SELECT user_id FROM users WHERE role = 'Admin' FOR UPDATE");
            if ($lockedAdmins->fetch()) {
                $pdo->rollBack();
                $adminCount = 1;
            } else {
                $insert = $pdo->prepare(
                    "INSERT INTO users (full_name, company, email, password_hash, role)
                     VALUES (?, ?, ?, ?, 'Admin')"
                );
                $insert->execute([
                    $fullName,
                    $company,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                ]);
                $pdo->commit();
                $created = true;
                $adminCount = 1;
                $_SESSION['first_admin_csrf'] = bin2hex(random_bytes(32));
            }
        } catch (Throwable $setupError) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('First admin setup failed: ' . $setupError->getMessage());
            $error = 'The Admin account could not be created. Check whether the email is already in use, then try again.';
        }
    }
}

$pageTitle = 'First Admin Setup';
require __DIR__ . '/includes/header.php';
?>
<main class="auth-shell">
    <section class="auth-panel">
        <div class="auth-panel-inner">
            <h1>First Admin Setup</h1>
            <?php if ($created): ?>
                <div class="form-note">The first Admin account has been created. Sign in, then remove or disable <code>setup_first_admin.php</code>.</div>
                <a class="btn btn-primary" href="<?= BASE_URL ?>/index.php">Go to sign in</a>
            <?php elseif ($adminCount > 0): ?>
                <div class="form-note">An Admin account already exists. This setup page is disabled.</div>
                <p>For safety, remove or disable <code>setup_first_admin.php</code> from the server.</p>
            <?php else: ?>
                <p>Create the initial administrator account. Use a unique password and do not share it.</p>
                <?php if ($error !== null): ?><div class="form-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['first_admin_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="field">
                        <label for="full-name">Full name</label>
                        <input id="full-name" name="full_name" maxlength="120" required value="<?= htmlspecialchars(is_string($_POST['full_name'] ?? null) ? $_POST['full_name'] : '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="field">
                        <label for="company">Company</label>
                        <input id="company" name="company" maxlength="160" required value="<?= htmlspecialchars(is_string($_POST['company'] ?? null) ? $_POST['company'] : 'Allied Metals', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="field">
                        <label for="email">Email</label>
                        <input id="email" type="email" name="email" maxlength="160" autocomplete="email" required value="<?= htmlspecialchars(is_string($_POST['email'] ?? null) ? $_POST['email'] : '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="field">
                        <label for="password">Password</label>
                        <input id="password" type="password" name="password" autocomplete="new-password" required>
                        <small>At least 8 characters, including a letter and a number.</small>
                    </div>
                    <button class="btn btn-primary" type="submit">Create Admin account</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
