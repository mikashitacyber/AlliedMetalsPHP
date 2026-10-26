<?php
// ONE-TIME USE. Resets a KNOWN existing account's password directly.
// Use this when an account exists (e.g. imported from another PC's database)
// but you don't know/remember what password it currently has.
// Change the two values below, open this file once in your browser, then DELETE IT.

$email = 'admin@alliedmetals.com.ph'; // the exact email of the account to fix
$newPassword = 'changeme123';          // whatever you want to log in with now

require __DIR__ . '/config/config.php';

$passwordError = passwordValidationError($newPassword);
if ($passwordError !== null) {
    exit(htmlspecialchars($passwordError, ENT_QUOTES, 'UTF-8') . ' Update the password in reset_password.php and try again.');
}

$check = $pdo->prepare("SELECT user_id, role FROM users WHERE email = ?");
$check->execute([$email]);
$user = $check->fetch();

if (!$user) {
    echo "No account found with that email in THIS database. Check phpMyAdmin's <code>users</code> table to confirm the exact email, or that the import actually ran.";
    exit;
}

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?")->execute([$hash, $email]);

echo "Password reset for <b>" . htmlspecialchars($email) . "</b> (role: " . htmlspecialchars($user['role']) . ").<br>";
echo "New password: " . htmlspecialchars($newPassword) . "<br><br>";
echo "<b>Log in now, then delete reset_password.php — it should not stay on the server.</b>";
