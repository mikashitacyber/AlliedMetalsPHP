<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/document_storage.php';
requireRole('Customer');
$u = currentUser();
$types = [
    'New project or consultation',
    'Equipment inquiry',
    'Custom stainless steel fabrication',
    'Installation or commissioning',
    'Parts, repair, or preventive maintenance',
    'General inquiry',
];
$sectors = [
    'Hotels & Resorts',
    'Quick-Service / Multi-Branch',
    'Restaurants & Bars',
    'Corporate & Industrial Facilities',
    'Institutions & Education',
    'Government & Cultural Facilities',
];
$error = null;
$portalProblemType = 'Portal technical problem';
$quotationIntent = 'quotation';
$allowedTypes = [...$types, $portalProblemType];
$isQuotation = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (($_POST['form_intent'] ?? '') === $quotationIntent)
    : (($_GET['mode'] ?? '') === $quotationIntent);
$selectedType = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (is_string($_POST['type'] ?? null) ? $_POST['type'] : '')
    : match ($_GET['type'] ?? '') {
        'portal_problem' => $portalProblemType,
        'general' => 'General inquiry',
        default => $isQuotation ? 'Equipment inquiry' : '',
    };
$isPortalProblem = $selectedType === $portalProblemType;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $selectedType;
    $sector = is_string($_POST['sector'] ?? null) ? $_POST['sector'] : '';
    $location = is_string($_POST['location'] ?? null) ? trim($_POST['location']) : '';
    $message = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';

    if (!in_array($type, $allowedTypes, true) || (!$isPortalProblem && !in_array($sector, $sectors, true))) {
        $error = 'Choose a valid inquiry type' . ($isPortalProblem ? '.' : ' and establishment sector.');
    } elseif (mb_strlen($location, 'UTF-8') > 160) {
        $error = 'Project / site location must be no more than 160 characters.';
    } elseif (mb_strlen($message, 'UTF-8') > 5000) {
        $error = 'Your description must be no more than 5,000 characters.';
    } elseif ($isPortalProblem && $message === '') {
        $error = 'Describe the portal problem so our team can investigate it.';
    }

    if ($error === null) {
        $department = toRoutingDepartment($type);
        $priority = (stripos($type, 'repair') !== false) ? 'high' : 'med';

        // Ticket number: AM-<year>-<running count for that year, zero-padded>
        $year = date('Y');
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM inquiries WHERE YEAR(created_at) = ?");
        $countStmt->execute([$year]);
        $nextNum = $countStmt->fetchColumn() + 1;
        $ticketNo = sprintf('AM-%s-%05d', $year, $nextNum);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO inquiries (ticket_no, user_id, type, sector, department, location, status, priority)
                                    VALUES (?, ?, ?, ?, ?, ?, 'Received', ?)");
            $stmt->execute([$ticketNo, $u['user_id'], $type, $sector, $department, $location, $priority]);
            $inquiryId = $pdo->lastInsertId();

            $pdo->prepare("INSERT INTO messages (inquiry_id, sender_id, sender_role, body) VALUES (?, ?, 'Customer', ?)")
                ->execute([$inquiryId, $u['user_id'], $message !== '' ? $message : '(no description provided)']);

            if (isset($_FILES['attachments'])) {
                storeInquiryAttachments($pdo, (int)$inquiryId, $_FILES['attachments'], (int)$u['user_id'], 'Customer');
            }

            $pdo->commit();
            $_SESSION['flash'] = "Inquiry $ticketNo submitted.";
            header('Location: my_inquiries.php');
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Inquiry submission failed: ' . $e->getMessage());
            $error = $e instanceof InvalidArgumentException
                ? $e->getMessage()
                : "Something went wrong submitting your inquiry. Please try again.";
        }
    }
}

$isPortalProblem = $selectedType === $portalProblemType;
$pageTitle = $isPortalProblem ? 'Report a Portal Problem' : ($isQuotation ? 'Request a Quotation' : 'New Inquiry');
require __DIR__ . '/../includes/header.php';
?>
<div class="shell">
    <?php $activeTab = $isPortalProblem ? 'problem' : 'new'; require __DIR__ . '/_sidebar.php'; ?>
    <main class="shell-main">
        <div class="shell-topbar">
            <div>
                <h2><?= $isPortalProblem ? 'Report a Portal Problem' : ($isQuotation ? 'Request a Quotation' : 'New Inquiry / Quotation') ?></h2>
                <div class="sub"><?= $isPortalProblem ? 'Let us know about a problem using the portal.' : ($isQuotation ? 'Tell us what equipment or custom work you need priced.' : 'Tell us what you need — we’ll route it automatically.') ?></div>
            </div>
        </div>
        <div class="shell-body">
            <div class="grid-2">
                <div class="panel">
                    <div class="panel-head"><h3><?= $isPortalProblem ? 'Tell Us What Went Wrong' : ($isQuotation ? 'Quotation Request Details' : 'Submit an Inquiry or Quotation Request') ?></h3></div>
                    <div class="panel-body">
                        <?php if (!empty($error)): ?><div class="form-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                        <?php if ($isPortalProblem): ?>
                            <div class="form-note portal-problem-note">
                                Use this for sign-in, page, upload, or other portal issues. For equipment, delivery, or service concerns, submit a regular inquiry instead. Portal reports go to Customer Service.
                            </div>
                        <?php endif; ?>
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="form_intent" value="<?= $isQuotation ? $quotationIntent : 'inquiry' ?>">
                            <div class="form-row-2">
                                <div class="field">
                                    <?php if ($isPortalProblem): ?>
                                        <label>Problem type</label>
                                        <input type="hidden" name="type" value="<?= htmlspecialchars($portalProblemType, ENT_QUOTES, 'UTF-8') ?>">
                                        <input value="<?= htmlspecialchars($portalProblemType) ?>" disabled>
                                    <?php else: ?>
                                        <label>Inquiry type</label>
                                        <select name="type" id="inq-type" required>
                                            <?php foreach ($types as $option): ?>
                                                <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedType === $option ? 'selected' : '' ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$isPortalProblem): ?>
                                    <div class="field">
                                        <label>Establishment sector</label>
                                        <select name="sector" required>
                                            <?php foreach ($sectors as $option): ?>
                                                <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!$isPortalProblem): ?>
                                <div class="field">
                                    <label>Project / site location</label>
                                    <input name="location" maxlength="160" placeholder="e.g. Bay Leaf Hotel, Intramuros, Manila">
                                </div>
                            <?php endif; ?>
                            <div class="field">
                                <label><?= $isPortalProblem ? 'Describe the problem' : 'Describe what you need' ?></label>
                                <textarea name="message" id="inq-msg" rows="4" maxlength="5000" oninput="suggestDept()"
                                    placeholder="<?= $isPortalProblem ? 'What were you trying to do? What happened, and what did you expect? Include the page or steps that led to the problem.' : 'e.g. We need three stainless prep counters and a hood for our new pastry section…' ?>" <?= $isPortalProblem ? 'required' : '' ?>></textarea>
                                <?php if (!$isPortalProblem): ?>
                                    <div class="suggest-badge" id="suggest-box" style="display:none;align-items:center;gap:8px;background:#e8faff;border:1px solid #b8ecfc;color:var(--signal-dark);padding:9px 12px;border-radius:4px;font-size:12.5px;margin-top:8px;">
                                    <span>✨</span> Looks like a match for <b id="suggest-label" onclick="acceptSuggestion()" style="cursor:pointer;text-decoration:underline;"></b> — tap to route it there.
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="field">
                                <label><?= $isPortalProblem ? 'Attach a screenshot (optional)' : 'Attach documents' ?> <span style="font-weight:400;color:#8a969b;"><?= $isPortalProblem ? '(screenshots can help us investigate)' : '(blueprints, floor plans, photos, PDFs, spreadsheets)' ?></span></label>
                                <div class="dropzone">
                                    <div class="ic" style="font-size:26px;margin-bottom:8px;">⇪</div>
                                    <p>PDF, JPG, PNG, DOC, DOCX, XLS, XLSX, or TXT · max 10 MB each · up to 5 files</p>
                                    <input type="file" name="attachments[]" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.txt" multiple>
                                </div>
                            </div>
                            <button class="btn btn-primary" type="submit"><?= $isPortalProblem ? 'Submit Problem Report →' : 'Submit Inquiry →' ?></button>
                        </form>
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-head"><h3>What happens next</h3></div>
                    <div class="panel-body" style="font-size:13px;color:var(--ink-soft);line-height:1.7;">
                        <?php if ($isPortalProblem): ?>
                            <p>1. Your report becomes a support ticket and is sent to Customer Service.</p>
                            <p>2. If possible, include the page, what you were doing, and the error you saw.</p>
                            <p>3. Follow replies and status changes in <b>Track My Orders</b>.</p>
                        <?php else: ?>
                            <p>1. Your inquiry is stamped with a ticket number and logged to your account immediately.</p>
                            <p>2. It's routed to the matching department based on the type you selected.</p>
                            <p>3. You'll see replies and status changes the moment you check <b>Track My Orders</b>.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<?php if (!$isPortalProblem): ?><script src="<?= BASE_URL ?>/assets/js/inquiry_suggest.js"></script><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
