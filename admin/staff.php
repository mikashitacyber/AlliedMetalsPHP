<?php
require __DIR__ . '/../config/config.php';
requireRole('Admin');

$departments = ["Design & Engineering","Equipment Sales","Fabrication","Installation","Service & Maintenance","Customer Service"];
$error = null;
if (empty($_SESSION['staff_department_csrf'])) {
    $_SESSION['staff_department_csrf'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_department'])) {
        $staffId = filter_var($_POST['staff_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $department = is_string($_POST['department'] ?? null) ? $_POST['department'] : '';
        $csrfToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
        if ($staffId === false || !in_array($department, $departments, true)
            || !hash_equals($_SESSION['staff_department_csrf'], $csrfToken)) {
            $_SESSION['flash'] = 'The department could not be updated. Refresh the page and try again.';
        } else {
            $pdo->prepare("UPDATE users SET department = ? WHERE user_id = ? AND role = 'Staff'")
                ->execute([$department, $staffId]);
            $_SESSION['flash'] = 'Staff department updated. The staff member should sign in again to refresh their access.';
        }
        header('Location: staff.php');
        exit;
    }
    if (isset($_POST['toggle_id'])) {
        $pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE user_id = ? AND role IN ('Staff','HR','Technician')")
            ->execute([(int)$_POST['toggle_id']]);
        header('Location: staff.php');
        exit;
    }
    if (isset($_POST['add_staff'])) {
        $fullName = is_string($_POST['full_name'] ?? null) ? trim($_POST['full_name']) : '';
        $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
        $role = is_string($_POST['role'] ?? null) ? $_POST['role'] : '';
        $departmentInput = is_string($_POST['department'] ?? null) ? $_POST['department'] : '';
        $department = $role === 'Staff' ? $departmentInput : null;
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $passwordError = passwordValidationError($password);
        if ($fullName === '' || mb_strlen($fullName, 'UTF-8') > 120) {
            $error = 'Full name is required and must be no more than 120 characters.';
        } elseif (strlen($email) > 160 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address (no more than 160 characters).';
        } elseif (!in_array($role, ['Staff', 'HR', 'Technician'], true)) {
            $error = 'Choose a valid account role.';
        } elseif ($role === 'Staff' && !in_array($departmentInput, $departments, true)) {
            $error = 'Choose a valid department for this Staff account.';
        } elseif ($passwordError !== null) {
            $error = $passwordError;
        } else {
            $exists = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $exists->execute([$email]);
            if ($exists->fetch()) {
                $error = "An account with this email already exists.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (full_name, company, email, password_hash, role, department)
                               VALUES (?, 'Allied Metals', ?, ?, ?, ?)")
                    ->execute([$fullName, $email, $hash, $role, $department]);
                header('Location: staff.php');
                exit;
            }
        }
    }
}

$staffList = $pdo->query("
    SELECT u.*,
        (SELECT COUNT(*) FROM inquiries i WHERE i.department = u.department AND i.status NOT IN ('Answered','Delivered','Closed')) AS open_tickets
    FROM users u WHERE role IN ('Staff','HR','Technician') ORDER BY role, created_at DESC
")->fetchAll();

$pageTitle = 'Manage Staff';
require __DIR__ . '/../includes/header.php';
?>
<div class="shell">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <main class="shell-main">
        <div class="shell-topbar"><div><h2>Manage Staff, HR &amp; Technicians</h2><div class="sub">Staff, HR and field technician accounts</div></div></div>
        <div class="shell-body">
            <div class="grid-2">
                <div class="panel">
                    <div class="panel-head"><h3>Staff, HR &amp; Technician Accounts</h3></div>
                    <div class="panel-body">
                        <div class="table-scroll"><table class="data">
                            <tr><th>Name</th><th>Role</th><th>Department</th><th>Open Tickets</th><th>Active</th></tr>
                            <?php foreach ($staffList as $s): ?>
                            <tr>
                                <td class="row-name"><?= htmlspecialchars($s['full_name']) ?></td>
                                <td><span class="pill"><?= $s['role'] === 'Technician' ? 'Field Technician' : htmlspecialchars($s['role']) ?></span></td>
                                <td>
                                    <?php if ($s['role'] === 'Staff'): ?>
                                        <form method="post" style="display:flex;align-items:center;gap:6px;">
                                            <input type="hidden" name="update_department" value="1">
                                            <input type="hidden" name="staff_id" value="<?= (int)$s['user_id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['staff_department_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                                            <select name="department" aria-label="Department for <?= htmlspecialchars($s['full_name'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?php foreach ($departments as $department): ?>
                                                    <option value="<?= htmlspecialchars($department, ENT_QUOTES, 'UTF-8') ?>" <?= $s['department'] === $department ? 'selected' : '' ?>><?= htmlspecialchars($department) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-outline btn-sm" type="submit">Save</button>
                                        </form>
                                    <?php else: ?>
                                        <?= htmlspecialchars($s['department'] ?? '—') ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= $s['role'] === 'Staff' ? $s['open_tickets'] : '—' ?></td>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="toggle_id" value="<?= $s['user_id'] ?>">
                                        <button class="toggle <?= $s['is_active']?'on':'' ?>" type="submit"><div class="knob"></div></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table></div>
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-head"><h3>Add an Account</h3></div>
                    <div class="panel-body">
                        <?php if ($error): ?><div class="form-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                        <form method="post" id="add-staff-form">
                            <input type="hidden" name="add_staff" value="1">
                            <div class="field"><label>Full name</label><input name="full_name" maxlength="120" required></div>
                            <div class="field"><label>Email</label><input type="email" name="email" maxlength="160" required></div>
                            <div class="field"><label>Role</label>
                                <select name="role" id="role-select">
                                    <option value="Staff">Staff</option>
                                    <option value="HR">HR</option>
                                    <option value="Technician">Field Technician</option>
                                </select>
                            </div>
                            <div class="field" id="department-field"><label>Department</label>
                                <select name="department">
                                    <?php foreach ($departments as $d): ?><option><?= $d ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field"><label>Temporary password</label><input type="password" name="password" minlength="8" maxlength="72" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,72}" title="Use at least 8 characters and no more than 72 bytes, including a letter and a number." autocomplete="new-password" required><small>Use at least 8 characters, including a letter and a number.</small></div>
                            <button class="btn btn-outline" type="submit">+ Add Account</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="<?= BASE_URL ?>/assets/js/add_staff_form.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
