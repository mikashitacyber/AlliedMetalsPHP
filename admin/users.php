<?php
require __DIR__ . '/../config/config.php';
requireRole('Admin');

if (empty($_SESSION['customer_signup_admin_csrf'])) {
    $_SESSION['customer_signup_admin_csrf'] = bin2hex(random_bytes(32));
}
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    if (!hash_equals($_SESSION['customer_signup_admin_csrf'], $token)) {
        $error = 'Refresh the page and try again.';
    } elseif (isset($_POST['toggle_id'])) {
        $pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE user_id = ? AND role = 'Customer'")
            ->execute([(int)$_POST['toggle_id']]);
        header('Location: users.php');
        exit;
    } elseif (isset($_POST['approve_signup']) || isset($_POST['reject_signup'])) {
        $signupId = filter_var($_POST['signup_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$signupId || $signupId < 1) {
            $error = 'Choose a valid sign-up request.';
        } else {
            try {
                $pdo->beginTransaction();
                $request = $pdo->prepare('SELECT * FROM customer_signup_requests WHERE signup_id = ? FOR UPDATE');
                $request->execute([$signupId]);
                $signup = $request->fetch();
                if (!$signup) {
                    $pdo->rollBack();
                    $error = 'This sign-up request is no longer available.';
                } elseif (isset($_POST['approve_signup'])) {
                    $exists = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
                    $exists->execute([$signup['email']]);
                    if ($exists->fetchColumn()) {
                        $pdo->rollBack();
                        $error = 'An account with this email already exists. Reject this duplicate request.';
                    } else {
                        $pdo->prepare("INSERT INTO users (full_name, company, email, phone, password_hash, role)
                            VALUES (?, ?, ?, ?, ?, 'Customer')")
                            ->execute([$signup['full_name'], $signup['company'], $signup['email'], $signup['phone'], $signup['password_hash']]);
                        $pdo->prepare('DELETE FROM customer_signup_requests WHERE signup_id = ?')->execute([$signupId]);
                        $pdo->commit();
                        $_SESSION['flash'] = 'Customer sign-up approved. The customer can now sign in.';
                        header('Location: users.php');
                        exit;
                    }
                } else {
                    $pdo->prepare('DELETE FROM customer_signup_requests WHERE signup_id = ?')->execute([$signupId]);
                    $pdo->commit();
                    $_SESSION['flash'] = 'Customer sign-up request rejected.';
                    header('Location: users.php');
                    exit;
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Customer sign-up review failed: ' . $exception->getMessage());
                $error = 'The sign-up request could not be processed. Please try again.';
            }
        }
    }
}

$customers = $pdo->query("
    SELECT u.*, (SELECT COUNT(*) FROM inquiries i WHERE i.user_id = u.user_id) AS inquiry_count
    FROM users u WHERE role = 'Customer' ORDER BY created_at DESC
")->fetchAll();
$pendingSignups = $pdo->query('SELECT signup_id, full_name, company, email, phone, created_at
    FROM customer_signup_requests ORDER BY created_at ASC')->fetchAll();

$pageTitle = 'Manage Users';
require __DIR__ . '/../includes/header.php';
?>
<div class="shell">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <main class="shell-main">
        <div class="shell-topbar"><div><h2>Manage Users</h2><div class="sub">Customer accounts and sign-up approvals</div></div></div>
        <div class="shell-body">
            <?php if ($error): ?><div class="form-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <div class="panel" style="margin-bottom:20px;">
                <div class="panel-head"><h3>Pending Sign-up Requests (<?= count($pendingSignups) ?>)</h3></div>
                <div class="panel-body">
                    <?php if (!$pendingSignups): ?>
                        <div class="empty"><div class="ic">✓</div>No pending customer sign-ups.</div>
                    <?php else: ?>
                        <div class="table-scroll"><table class="data">
                            <tr><th>Name</th><th>Company</th><th>Email</th><th>Phone</th><th>Requested</th><th>Review</th></tr>
                            <?php foreach ($pendingSignups as $signup): ?>
                                <tr>
                                    <td class="row-name"><?= htmlspecialchars($signup['full_name']) ?></td>
                                    <td><?= htmlspecialchars($signup['company']) ?></td>
                                    <td><?= htmlspecialchars($signup['email']) ?></td>
                                    <td><?= htmlspecialchars($signup['phone'] ?? '') ?></td>
                                    <td><?= date('M j, Y', strtotime($signup['created_at'])) ?></td>
                                    <td>
                                        <div class="staff-controls">
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['customer_signup_admin_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="signup_id" value="<?= (int)$signup['signup_id'] ?>">
                                                <button class="btn btn-primary btn-sm" type="submit" name="approve_signup" value="1">Approve</button>
                                            </form>
                                            <form method="post" onsubmit="return confirm('Reject and delete this sign-up request?')">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['customer_signup_admin_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="signup_id" value="<?= (int)$signup['signup_id'] ?>">
                                                <button class="btn btn-outline btn-sm" type="submit" name="reject_signup" value="1">Reject</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="panel">
                <div class="panel-head"><h3>Customer Accounts</h3></div>
                <div class="panel-body">
                    <?php if (empty($customers)): ?>
                        <div class="empty"><div class="ic">☺</div>No approved customer accounts yet. Review pending requests above.</div>
                    <?php else: ?>
                    <div class="table-scroll"><table class="data">
                        <tr><th>Name</th><th>Company</th><th>Email</th><th>Inquiries Filed</th><th>Status</th><th></th></tr>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td class="row-name"><?= htmlspecialchars($customer['full_name']) ?></td>
                            <td><?= htmlspecialchars($customer['company']) ?></td>
                            <td><?= htmlspecialchars($customer['email']) ?></td>
                            <td><?= (int)$customer['inquiry_count'] ?></td>
                            <td><span class="pill <?= $customer['is_active'] ? '' : 'priority-med' ?>"><?= $customer['is_active'] ? 'Active' : 'Suspended' ?></span></td>
                            <td>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['customer_signup_admin_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="toggle_id" value="<?= (int)$customer['user_id'] ?>">
                                    <button class="toggle <?= $customer['is_active'] ? 'on' : '' ?>" type="submit" aria-label="<?= $customer['is_active'] ? 'Suspend customer' : 'Activate customer' ?>"><div class="knob"></div></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
