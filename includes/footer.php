<?php
$chatUser = currentUser();
$showSupportChat = ($chatUser['role'] ?? '') === 'Customer' && empty($hideSupportChat);
$chatConfigured = appEnvironmentVariable('GROQ_API_KEY') !== null;
$showNotifications = ($chatUser['role'] ?? '') === 'Customer';
if ($showNotifications && empty($_SESSION['notifications_csrf'])) {
    $_SESSION['notifications_csrf'] = bin2hex(random_bytes(32));
}
if ($showSupportChat && empty($_SESSION['support_chat_csrf'])) {
    $_SESSION['support_chat_csrf'] = bin2hex(random_bytes(32));
}
?>
<div class="toast" id="toast"><span class="ic">✓</span><span id="toast-msg">Saved.</span></div>
<?php if ($showNotifications): ?>
<div class="notifications-widget" data-notifications data-endpoint="<?= BASE_URL ?>/notifications_api.php" data-csrf-token="<?= htmlspecialchars($_SESSION['notifications_csrf'], ENT_QUOTES, 'UTF-8') ?>">
    <section class="notifications-panel" data-notification-panel aria-label="Notifications" hidden>
        <header class="notifications-panel-head">
            <h2>Notifications</h2>
            <button type="button" class="notifications-read-all" data-notification-read-all>Mark all read</button>
        </header>
        <div class="notification-list" data-notification-list aria-live="polite">
            <p class="notification-empty">Loading notifications…</p>
        </div>
    </section>
</div>
<?php endif; ?>
<script src="<?= BASE_URL ?>/assets/js/theme.js" defer></script>
<?php if ($showSupportChat): ?>
<div class="support-chat" data-support-chat data-endpoint="<?= BASE_URL ?>/customer/support_chat_api.php" data-csrf-token="<?= htmlspecialchars($_SESSION['support_chat_csrf'], ENT_QUOTES, 'UTF-8') ?>">
    <section class="support-chat-panel" id="support-chat-panel" role="dialog" aria-modal="false" aria-labelledby="support-chat-title" hidden>
        <header class="support-chat-head">
            <div class="support-chat-brandmark" aria-hidden="true">A</div>
            <div class="support-chat-heading">
                <h2 id="support-chat-title">ALLI</h2>
                <span><i></i> FAQ support · <?= $chatConfigured ? 'ready' : 'setup required' ?></span>
            </div>
            <button class="support-chat-close" type="button" data-chat-close aria-label="Close support chat">×</button>
        </header>
        <div class="support-chat-messages" data-chat-messages role="log" aria-live="polite" aria-relevant="additions">
            <div class="support-chat-message assistant">
                <div class="support-chat-bubble">
                    <p>Hi<?= !empty($chatUser['full_name']) ? ', ' . htmlspecialchars(strtok($chatUser['full_name'], ' ')) : '' ?>. What can we help with?</p>
                </div>
            </div>
            <div class="support-chat-topics" aria-label="Suggested topics">
                <button type="button" data-chat-prompt="What services does Allied Metals offer?">Our services</button>
                <button type="button" data-chat-prompt="How do I track my inquiry?">Track an inquiry</button>
                <button type="button" data-chat-prompt="How can I contact Allied Metals?">Contact details</button>
            </div>
        </div>
        <div class="support-chat-note">Ask about company services or portal use. Do not enter passwords or private order details.</div>
        <form class="support-chat-compose" data-chat-form>
            <label class="sr-only" for="support-chat-input">Message ALLI</label>
            <input id="support-chat-input" name="message" type="text" maxlength="500" placeholder="Type a question…" autocomplete="off" required>
            <button type="submit" aria-label="Send message" title="Send message"><span aria-hidden="true">↑</span></button>
        </form>
    </section>
    <button class="support-chat-launcher" type="button" aria-expanded="false" aria-controls="support-chat-panel" data-chat-open>
        <span class="support-chat-launcher-icon" aria-hidden="true">?</span>
        <span>Ask ALLI</span>
    </button>
</div>
<?php endif; ?>
<script src="<?= BASE_URL ?>/assets/js/toast.js"></script>
<script src="<?= BASE_URL ?>/assets/js/no_back_cache.js"></script>
<script src="<?= BASE_URL ?>/assets/js/password_toggle.js"></script>
<script src="<?= BASE_URL ?>/assets/js/mobile_nav.js"></script>
<script src="<?= BASE_URL ?>/assets/js/profile_picture_preview.js?v=<?= filemtime(__DIR__ . '/../assets/js/profile_picture_preview.js') ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/inquiry_chat.js"></script>
<?php if (in_array($chatUser['role'] ?? '', ['Customer', 'Staff', 'HR', 'Technician', 'Admin'], true)): ?>
<script src="<?= BASE_URL ?>/assets/js/presence.js"></script>
<?php endif; ?>
<?php if ($showSupportChat): ?>
<script src="<?= BASE_URL ?>/assets/js/support_chat.js"></script>
<?php endif; ?>
<?php if ($showNotifications): ?>
<script src="<?= BASE_URL ?>/assets/js/notifications.js?v=<?= filemtime(__DIR__ . '/../assets/js/notifications.js') ?>" defer></script>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
<!-- This one line just hands the server's flash message to the JS function above —
     the actual toast logic itself lives in assets/js/toast.js, not here. -->
<script>document.addEventListener('DOMContentLoaded', () => toast(<?= json_encode($_SESSION['flash']) ?>));</script>
<?php unset($_SESSION['flash']); endif; ?>
</body>
</html>
