<?php
// Call renderTicketCard($inquiry, $linkTo) from any page.
// $inquiry is one row from the inquiries table (as assoc array).
// $linkTo is the URL the whole card links to (e.g. inquiry_detail.php?id=5).
function stampClassFor(string $status): string {
    return match ($status) {
        'Received' => 'stamp-received',
        'In Review' => 'stamp-review',
        'Awaiting Your Reply' => 'stamp-awaiting',
        'Quoted' => 'stamp-quoted',
        'In Fabrication' => 'stamp-progress',
        'Ready for Delivery' => 'stamp-progress',
        'Out for Delivery' => 'stamp-progress',
        'Answered' => 'stamp-resolved',
        'Delivered' => 'stamp-resolved',
        default => 'stamp-closed', // 'Closed'
    };
}

function renderTicketCard(array $inquiry, string $linkTo, bool $showCustomer = false): void {
    $stamp = stampClassFor($inquiry['status']);
    $quoteSummary = null;
    $quoteSummaryClass = 'ticket-quote-summary';
    if (($inquiry['quote_status'] ?? null) === 'offered') {
        if (($inquiry['quote_valid_until'] ?? '') < date('Y-m-d')) {
            $quoteSummary = 'Quotation expired · contact Allied Metals for an update';
            $quoteSummaryClass .= ' expired';
        } else {
            $quoteSummary = 'Quotation needs your response · ₱' . number_format((float)$inquiry['quote_amount'], 2)
                . ' · valid through ' . date('M j, Y', strtotime($inquiry['quote_valid_until']));
            $quoteSummaryClass .= ' action-needed';
        }
    } elseif (($inquiry['quote_status'] ?? null) === 'accepted') {
        $quoteSummary = 'Quotation accepted';
    } elseif (($inquiry['quote_status'] ?? null) === 'declined') {
        $quoteSummary = 'Quotation declined';
    }
    $priorityLabel = match ($inquiry['priority']) {
        'high' => 'High priority',
        'low' => 'Low priority',
        default => 'Medium priority',
    };
    ?>
    <a href="<?= htmlspecialchars($linkTo) ?>" class="ticket">
        <div class="ticket-perf"></div>
        <div class="ticket-top">
            <span class="ticket-id mono"><?= htmlspecialchars($inquiry['ticket_no']) ?></span>
            <span class="stamp <?= $stamp ?>"><?= htmlspecialchars($inquiry['status']) ?></span>
        </div>
        <div class="ticket-body">
            <h4>
                <?php if ($showCustomer): ?>
                    <?= htmlspecialchars($inquiry['full_name'] . ' — ' . $inquiry['company']) ?>
                <?php else: ?>
                    <?= htmlspecialchars($inquiry['type']) ?>
                <?php endif; ?>
            </h4>
            <div class="ticket-meta">
                <span>▤ <?= htmlspecialchars($inquiry['department']) ?></span>
                <span>🕒 <?= date('M j, Y', strtotime($inquiry['created_at'])) ?></span>
                <span class="pill priority-<?= $inquiry['priority'] ?>"><?= $priorityLabel ?></span>
            </div>
            <?php if ($quoteSummary !== null): ?>
                <div class="<?= $quoteSummaryClass ?>"><?= htmlspecialchars($quoteSummary, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
        </div>
    </a>
    <?php
}
