<?php
require __DIR__ . '/config/config.php';

// If already logged in, skip straight to the right portal.
if ($user = currentUser()) {
    $dest = match ($user['role']) {
        'Staff' => 'staff/queue.php',
        'HR' => 'hr/dashboard.php',
        'Technician' => 'employee_work.php',
        'Admin' => 'admin/overview.php',
        default => 'customer/dashboard.php',
    };
    header('Location: ' . BASE_URL . '/' . $dest);
    exit;
}

$view = $_GET['view'] ?? 'landing'; // landing | login | signup
$errors = [];
$signupValues = ['full_name' => '', 'company' => '', 'email' => '', 'phone' => ''];
if (empty($_SESSION['customer_signup_csrf'])) {
    $_SESSION['customer_signup_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'signup') {
    $view = 'signup';
    foreach ($signupValues as $field => $_) {
        $signupValues[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
    }
    $fullName = $signupValues['full_name'];
    $company = $signupValues['company'];
    $email = strtolower($signupValues['email']);
    $phone = $signupValues['phone'];
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmPassword = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
    $token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';

    if (empty($_SESSION['customer_signup_csrf']) || !hash_equals($_SESSION['customer_signup_csrf'], $token)) {
        $errors[] = 'Refresh the page and try again.';
    } elseif ($fullName === '' || mb_strlen($fullName, 'UTF-8') > 120) {
        $errors[] = 'Full name is required and must be no more than 120 characters.';
    } elseif ($company === '' || mb_strlen($company, 'UTF-8') > 160) {
        $errors[] = 'Company / establishment is required and must be no more than 160 characters.';
    } elseif (strlen($email) > 160 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } elseif (mb_strlen($phone, 'UTF-8') > 30) {
        $errors[] = 'Phone number must be no more than 30 characters.';
    } elseif (($passwordError = passwordValidationError($password)) !== null) {
        $errors[] = $passwordError;
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    } elseif (!empty($_SESSION['customer_signup_last_at']) && time() - (int)$_SESSION['customer_signup_last_at'] < 60) {
        $errors[] = 'Please wait one minute before submitting another sign-up request.';
    } else {
        $_SESSION['customer_signup_last_at'] = time();
        $exists = $pdo->prepare('SELECT user_id FROM users WHERE email = ? UNION ALL SELECT signup_id FROM customer_signup_requests WHERE email = ? LIMIT 1');
        $exists->execute([$email, $email]);
        if ($exists->fetchColumn()) {
            $errors[] = 'An account or sign-up request already exists for this email. Contact Allied Metals if you need help.';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO customer_signup_requests (full_name, company, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$fullName, $company, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
                $_SESSION['customer_signup_csrf'] = bin2hex(random_bytes(32));
                header('Location: ' . BASE_URL . '/index.php?view=login&signup=pending');
                exit;
            } catch (PDOException $error) {
                if ($error->getCode() !== '23000') {
                    throw $error;
                }
                $errors[] = 'An account or sign-up request already exists for this email. Contact Allied Metals if you need help.';
            }
        }
    }
}

// ---------- handle POST for login ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'login') {
    $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    $u = false;
    if (strlen($email) <= 160 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $u = $stmt->fetch();
    }

    $legacyPassword = false;
    $passwordValid = false;
    if ($u) {
        $storedPassword = (string)$u['password_hash'];
        $passwordValid = password_verify($password, $storedPassword);
        if (!$passwordValid && password_get_info($storedPassword)['algoName'] === 'unknown') {
            $passwordValid = hash_equals($storedPassword, $password);
            $legacyPassword = $passwordValid;
        }
    }

    if (!$u || !$passwordValid) {
        $pending = false;
        if (!$u && strlen($email) <= 160 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $request = $pdo->prepare('SELECT password_hash FROM customer_signup_requests WHERE email = ?');
            $request->execute([$email]);
            $pendingHash = $request->fetchColumn();
            $pending = is_string($pendingHash) && password_verify($password, $pendingHash);
        }
        $errors[] = $pending
            ? 'Your customer sign-up is waiting for Admin approval. You can sign in after your account is approved.'
            : 'Email or password is incorrect.';
        $view = 'login';
    } elseif (!$u['is_active']) {
        $errors[] = "This account has been suspended. Contact Allied Metals for help.";
        $view = 'login';
    } else {
        $pdo->prepare("UPDATE users SET last_active_at = NOW() WHERE user_id = ?")
            ->execute([$u['user_id']]);
        if ($legacyPassword) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?")
                ->execute([$newHash, $u['user_id']]);
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'user_id' => $u['user_id'],
            'full_name' => $u['full_name'],
            'company' => $u['company'],
            'role' => $u['role'],
            'department' => $u['department'],
            'manual_status' => $u['manual_status'] ?? 'available',
            'profile_picture_path' => $u['profile_picture_path'] ?? null,
        ];
        $dest = match ($u['role']) {
            'Staff' => 'staff/queue.php',
            'HR' => 'hr/dashboard.php',
            'Technician' => 'employee_work.php',
            'Admin' => 'admin/overview.php',
            default => 'customer/dashboard.php',
        };
        header('Location: ' . BASE_URL . '/' . $dest);
        exit;
    }
}

$pageTitle = 'Customer Service Portal';
require __DIR__ . '/includes/header.php';
?>

<?php if ($view === 'landing'): ?>
<div class="site-nav">
    <div class="brand"><img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Allied Metals" class="brand-logo"><small class="brand-caption">Customer Service Portal</small></div>
    <nav>
        <a href="#capabilities">Capabilities</a>
        <a href="?view=login">Track Your Order</a>
        <a href="#support">Support</a>
        <a href="#about">About</a>
    </nav>
    <div style="display:flex;gap:10px;">
        <a class="btn btn-ghost btn-sm" href="?view=signup">Create Account</a>
        <a class="btn btn-primary btn-sm" href="?view=login">Sign In</a>
    </div>
</div>

<div class="hero">
    <div class="eyebrow">SINCE 1965 · THE TOTAL KITCHEN COMPANY</div>
    <h1>Every inquiry gets<br>a <span>ticket</span>. Every ticket<br>gets a home.</h1>
    <p class="lead">Customers can request portal access to submit project inquiries, request quotations, and follow each order from fabrication to delivery. New accounts are reviewed by an Allied Metals Admin.</p>
    <div class="cta-row">
        <a class="btn btn-primary" href="?view=signup">Create Customer Account →</a>
        <a class="btn btn-ghost" href="#about">About Allied Metals</a>
    </div>
    <div class="stat-strip">
        <div><div class="num">60+</div><div class="lbl">Years in Philippine foodservice</div></div>
        <div><div class="num">24/7</div><div class="lbl">Submit requests anytime, even after hours</div></div>
        <div><div class="num">6</div><div class="lbl">Capability areas, one routing system</div></div>
        <div><div class="num">&lt; 1 day</div><div class="lbl">Target first-response time</div></div>
    </div>
</div>

<div class="section section-light" id="capabilities">
    <div class="section-head">
        <span class="eyebrow-lt">Capabilities</span>
        <h2>Route your inquiry to the right desk.</h2>
        <p>Pick the category closest to your need — the portal uses these same six categories to assign your ticket.</p>
    </div>
    <div class="cap-grid">
        <div class="cap-card"><div class="rivet"></div><div class="idx">01</div><h4>New Project / Consultation</h4><p>Layout, workflow, and equipment planning for a new or renovated kitchen.</p></div>
        <div class="cap-card"><div class="rivet"></div><div class="idx">02</div><h4>Equipment Inquiry</h4><p>Cooking, refrigeration, prep, warewashing, and storage equipment.</p></div>
        <div class="cap-card"><div class="rivet"></div><div class="idx">03</div><h4>Custom Stainless Fabrication</h4><p>Worktables, counters, sinks, hoods, racks, and site-fit components.</p></div>
        <div class="cap-card"><div class="rivet"></div><div class="idx">04</div><h4>Installation &amp; Commissioning</h4><p>Equipment and stainless steel integration before turnover.</p></div>
        <div class="cap-card"><div class="rivet"></div><div class="idx">05</div><h4>Parts, Repair &amp; Technical Service</h4><p>Parts, repair, and technical service for kitchens already running.</p></div>
        <div class="cap-card"><div class="rivet"></div><div class="idx">06</div><h4>Preventive Maintenance (PMS)</h4><p>Scheduled upkeep conversations after turnover.</p></div>
    </div>
</div>

<div class="section" id="about">
    <div class="section-head">
        <span class="eyebrow-lt">About</span>
        <h2>The Total Kitchen Company, since 1965.</h2>
        <p>Allied Metals plans, equips, fabricates, installs, and services commercial kitchens for hotels, restaurants, quick-service chains, institutions, and government facilities across the Philippines.</p>
    </div>
    <div class="about-grid">
        <div class="about-card"><h4>Who this portal is for</h4><p>This portal is for Allied Metals customers. New customer account requests are reviewed by an Admin before access is granted.</p></div>
        <div class="about-card"><h4>What you can do here</h4><p>Submit inquiries and quotation requests with your blueprints and photos, follow every order through fabrication and delivery, and message the staff member assigned to you.</p></div>
        <div class="about-card"><h4>How we route your request</h4><p>Each request goes to the matching department, then HR assigns it to a specific staff member who handles it and keeps the status up to date.</p></div>
    </div>
    <p style="margin:28px 0 0;"><a class="btn btn-outline" href="https://www.alliedmetals.com.ph" target="_blank" rel="noopener">Visit the official Allied Metals website ↗</a></p>
</div>

<div class="section section-light" id="support">
    <div class="section-head">
        <span class="eyebrow-lt">Support</span>
        <h2>Need help?</h2>
        <p>Contract clients can message their assigned staff member directly from the order page. For anything about your account itself, reach us here.</p>
    </div>
    <div class="about-grid">
        <div class="about-card"><h4>Forgot your password?</h4><p>Ask your Allied Metals account representative. The Admin can reset it for you.</p></div>
        <div class="about-card"><h4>Call us</h4><p class="mono">+63 2 8400 9716</p></div>
        <div class="about-card"><h4>Email us</h4><p class="mono">info@alliedmetals.com.ph</p></div>
        <div class="about-card"><h4>Visit us</h4><p>Muzon, San Jose del Monte, Bulacan</p></div>
    </div>
</div>

<div class="footer">
    <div class="cols">
        <div>
            <div class="brand" style="margin-bottom:14px;"><img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Allied Metals" class="brand-logo"></div>
            <p style="max-width:280px;color:#8ea0a7;">The Total Kitchen Company for commercial foodservice teams.</p>
        </div>
        <div><h5>Portal</h5><a href="?view=login">Sign In</a><a href="#capabilities">Capabilities</a><a href="#about">About</a><a href="#support">Support</a><a href="https://www.alliedmetals.com.ph" target="_blank" rel="noopener">Official website ↗</a></div>
        <div><h5>Contact</h5><a>Muzon, San Jose del Monte, Bulacan</a><a>+63 2 8400 9716</a><a>info@alliedmetals.com.ph</a></div>
    </div>
    <div class="bottom"><span>© 2026 Allied Metals. Capstone project.</span><span>PHP + MySQL (XAMPP)</span></div>
</div>

<?php elseif ($view === 'signup'): ?>
<div class="auth-shell">
    <div class="auth-brand">
        <div class="brand"><img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Allied Metals" class="brand-logo"><small class="brand-caption">Customer Service Portal</small></div>
        <blockquote>Request secure portal access to follow your inquiries, quotations, and customer messages.</blockquote>
        <div class="est">CUSTOMER ACCOUNTS REQUIRE ADMIN APPROVAL</div>
    </div>
    <div class="auth-panel">
        <div class="auth-card">
            <div class="auth-tabs">
                <a href="?view=login">Sign In</a>
                <a class="active" href="?view=signup">Create Account</a>
            </div>
            <?php foreach ($errors as $error): ?>
                <div class="form-error"><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
            <form method="post">
                <input type="hidden" name="form" value="signup">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['customer_signup_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="field"><label for="signup-name">Full name</label><input id="signup-name" name="full_name" maxlength="120" value="<?= htmlspecialchars($signupValues['full_name']) ?>" autocomplete="name" required></div>
                <div class="field"><label for="signup-company">Company / Establishment</label><input id="signup-company" name="company" maxlength="160" value="<?= htmlspecialchars($signupValues['company']) ?>" autocomplete="organization" required></div>
                <div class="field"><label for="signup-email">Email address</label><input id="signup-email" type="email" name="email" maxlength="160" value="<?= htmlspecialchars($signupValues['email']) ?>" autocomplete="email" required></div>
                <div class="field"><label for="signup-phone">Phone (optional)</label><input id="signup-phone" name="phone" maxlength="30" value="<?= htmlspecialchars($signupValues['phone']) ?>" autocomplete="tel"></div>
                <div class="field"><label for="signup-password">Password</label><input id="signup-password" type="password" name="password" minlength="8" maxlength="72" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,72}" autocomplete="new-password" required><small>At least 8 characters, with a letter and a number.</small></div>
                <div class="field"><label for="signup-confirm">Confirm password</label><input id="signup-confirm" type="password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" required></div>
                <button class="btn btn-dark" type="submit">Request Account →</button>
            </form>
            <div class="auth-foot">Your account will remain pending until Allied Metals Admin reviews your request. Do not submit passwords for an existing account.</div>
        </div>
    </div>
</div>
<?php else: /* login */ ?>
<div class="auth-shell">
    <div class="auth-brand">
        <div class="brand"><img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Allied Metals" class="brand-logo"><small class="brand-caption">Customer Service Portal</small></div>
        <blockquote>"A pioneer in professional foodservice kitchens in the Philippines — built for operation, workflow, and service access."</blockquote>
        <div class="est">EST. 1965 — MUZON, SAN JOSE DEL MONTE, BULACAN</div>
    </div>
    <div class="auth-panel">
        <div class="auth-card">
            <div class="auth-tabs">
                <a class="active" href="?view=login">Sign In</a>
            </div>

            <?php foreach ($errors as $e): ?>
                <div class="form-error"><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
            <?php if (($_GET['signup'] ?? '') === 'pending'): ?>
                <div class="form-note" style="margin-bottom:16px;">Your account request was submitted. An Admin must approve it before you can sign in.</div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="form" value="login">
                <div class="field"><label for="login-email">Email address</label><input id="login-email" type="email" name="email" required maxlength="160" placeholder="you@company.com" autocomplete="username"></div>
                <div class="field"><label for="login-password">Password</label><input id="login-password" type="password" name="password" required autocomplete="current-password"></div>
                <button class="btn btn-dark" type="submit">Sign In →</button>
            </form>
            <div class="auth-foot">
                New customer? <a href="?view=signup">Request an account</a>. Sign-up requests require Admin approval. Need help signing in? Contact your Allied Metals account representative.
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
