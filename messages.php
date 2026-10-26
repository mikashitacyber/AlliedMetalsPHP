<?php
require __DIR__ . '/config/config.php';
requireAnyRole(['Customer', 'Staff', 'HR', 'Technician', 'Admin']);

$user = currentUser();
$isCustomer = $user['role'] === 'Customer';
$pageTitle = $isCustomer ? 'Messages' : 'Customer Messages';
$bodyClass = 'messenger-page';
$hideSupportChat = $isCustomer;
if (empty($_SESSION['direct_messages_csrf'])) {
    $_SESSION['direct_messages_csrf'] = bin2hex(random_bytes(32));
}
require __DIR__ . '/includes/header.php';
?>
<div class="shell">
    <?php
    if ($isCustomer) {
        $activeTab = 'messages';
        require __DIR__ . '/customer/_sidebar.php';
    } else {
        if ($user['role'] === 'Staff') {
            require __DIR__ . '/staff/_sidebar.php';
        } elseif ($user['role'] === 'HR') {
            require __DIR__ . '/hr/_sidebar.php';
        } elseif ($user['role'] === 'Technician') {
            require __DIR__ . '/technician/_sidebar.php';
        } else {
            require __DIR__ . '/admin/_sidebar.php';
        }
    }
    ?>
    <main class="shell-main">
        <div class="shell-topbar">
            <div>
                <h2><?= $isCustomer ? 'Messages' : 'Customer Messages' ?></h2>
                <div class="sub"><?= $isCustomer ? 'Reply to a message from an Allied Metals employee.' : 'Find customers and start a direct conversation.' ?></div>
            </div>
        </div>
        <div class="shell-body">
            <div class="messenger" data-direct-messenger
                 data-role="<?= $isCustomer ? 'Customer' : 'Employee' ?>"
                 data-user-id="<?= (int)$user['user_id'] ?>"
                 data-endpoint="<?= BASE_URL ?>/direct_messages_api.php"
                 data-csrf-token="<?= htmlspecialchars($_SESSION['direct_messages_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                <section class="messenger-contacts panel" aria-label="<?= $isCustomer ? 'Employee conversations' : 'Customers' ?>">
                    <div class="messenger-contacts-head">
                        <h3><?= $isCustomer ? 'Conversations' : 'Customers' ?></h3>
                        <?php if (!$isCustomer): ?>
                            <label class="sr-only" for="customer-search">Search customers</label>
                            <input id="customer-search" type="search" maxlength="80" placeholder="Search customers…" data-customer-search>
                        <?php endif; ?>
                    </div>
                    <div class="messenger-contact-list" data-contact-list aria-live="polite">
                        <div class="messenger-empty">Loading…</div>
                    </div>
                </section>
                <section class="messenger-conversation panel" data-messenger-conversation aria-label="Direct message conversation">
                    <header class="messenger-conversation-head" data-conversation-head>
                        <button class="messenger-back-button" type="button" data-messenger-back aria-label="Back to conversations">← Conversations</button>
                        <div class="messenger-empty">Choose a customer to open a conversation.</div>
                    </header>
                    <div class="messenger-thread" data-message-thread aria-live="polite"></div>
                    <div class="messenger-error" data-messenger-error role="alert" hidden></div>
                    <form class="messenger-compose" data-message-form>
                        <div class="messenger-compose-tools">
                            <input class="messenger-file-input" type="file" id="direct-message-image" accept="image/*" data-message-image disabled>
                            <label class="messenger-attach-button" for="direct-message-image" title="Attach a picture" aria-label="Attach a picture">＋</label>
                            <span class="messenger-image-selection" data-image-selection hidden></span>
                            <button class="messenger-image-clear" type="button" data-image-clear hidden aria-label="Remove selected picture">×</button>
                        </div>
                        <div class="messenger-compose-row">
                            <label class="sr-only" for="direct-message-input">Write a message</label>
                            <textarea id="direct-message-input" name="body" rows="2" maxlength="2000" placeholder="Write a message…" disabled></textarea>
                            <button class="btn btn-primary" type="submit" disabled data-message-send>Send</button>
                        </div>
                        <small class="messenger-image-help">Photos are resized when needed. JPG, PNG, and WebP are supported.</small>
                    </form>
                </section>
            </div>
        </div>
    </main>
</div>
<script src="<?= BASE_URL ?>/assets/js/direct_messages.js?v=<?= filemtime(__DIR__ . '/assets/js/direct_messages.js') ?>" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
