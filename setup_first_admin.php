<?php
// ONE-TIME USE. Creates your very first Admin account directly in the database.
// Change the four values below, then open this file once in your browser, e.g.:
// http://localhost/latest%20version/AlliedMetalsPHP/setup_first_admin.php
// Afterward, log in with that email/password, then DELETE THIS FILE.

$fullName = 'Angela Lim';
$email    = 'admin@alliedmetals.com.ph';
$password = 'changeme123';   // change this to whatever you actually want to log in with
$company  = 'Allied Metals';

require __DIR__ . '/config/config.php';

$passwordError = passwordValidationError($password);
if ($passwordError !== null) {
    exit(htmlspecialchars($passwordError, ENT_QUOTES, 'UTF-8') . ' Update the password in setup_first_admin.php and try again.');
}

$check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
$check->execute([$email]);

if ($check->fetch()) {
    echo "An account with that email already exists. Nothing created. If you meant to reset it, change the \$email above or edit it directly in phpMyAdmin.";
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$pdo->prepare("INSERT INTO users (full_name, company, email, password_hash, role)
                VALUES (?, ?, ?, ?, 'Admin')")
    ->execute([$fullName, $company, $email, $hash]);

echo "Admin account created.<br>Email: " . htmlspecialchars($email) . "<br>Password: " . htmlspecialchars($password) .
     "<br><br><b>Log in now, then delete this file (setup_first_admin.php) — it should not stay on the server.</b>";
