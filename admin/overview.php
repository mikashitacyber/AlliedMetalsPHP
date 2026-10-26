<?php
require __DIR__ . '/../config/config.php';
requireRole('Admin');

// KPIs — this calendar month
$thisMonthStart = date('Y-m-01 00:00:00');
$totalThisMonth = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE created_at >= '$thisMonthStart'")->fetchColumn();
$openTickets = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE status NOT IN ('Answered','Delivered','Closed')")->fetchColumn();
$resolvedThisMonth = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE status IN ('Answered','Delivered','Closed') AND created_at >= '$thisMonthStart'")->fetchColumn();

// Avg first response time (hours between inquiry creation and first Staff message)
$avgRespRow = $pdo->query("
    SELECT AVG(TIMESTAMPDIFF(MINUTE, i.created_at, first_staff.first_reply)) AS avg_minutes
    FROM inquiries i
    JOIN (
        SELECT inquiry_id, MIN(created_at) AS first_reply
        FROM messages WHERE sender_role = 'Staff'
        GROUP BY inquiry_id
    ) first_staff ON first_staff.inquiry_id = i.inquiry_id
")->fetch();
$avgHours = $avgRespRow['avg_minutes'] ? round($avgRespRow['avg_minutes'] / 60, 1) : null;

// Inquiries by department (all-time, for the bar chart)
$byDept = $pdo->query("SELECT department, COUNT(*) as cnt FROM inquiries GROUP BY department ORDER BY cnt DESC")->fetchAll();
$maxDeptCount = max(array_column($byDept, 'cnt') ?: [1]);

// Monthly inquiry volume over the latest six calendar months.
$firstTrendMonth = new DateTimeImmutable('first day of this month');
$trendStart = $firstTrendMonth->modify('-5 months');
$monthlyCounts = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, COUNT(*) AS total
    FROM inquiries
    WHERE created_at >= ? AND created_at < ?
    GROUP BY month_key
");
$monthlyCounts->execute([
    $trendStart->format('Y-m-d 00:00:00'),
    $firstTrendMonth->modify('+1 month')->format('Y-m-d 00:00:00'),
]);
$monthlyCounts = array_column($monthlyCounts->fetchAll(), 'total', 'month_key');
$monthlyTrend = [];
for ($month = $trendStart; $month <= $firstTrendMonth; $month = $month->modify('+1 month')) {
    $monthKey = $month->format('Y-m');
    $monthlyTrend[] = [
        'label' => $month->format('M'),
        'count' => (int)($monthlyCounts[$monthKey] ?? 0),
    ];
}
$trendMax = max(max(array_column($monthlyTrend, 'count') ?: [0]), 1);
$trendPlot = ['left' => 48, 'right' => 620, 'top' => 22, 'bottom' => 178];
$trendPoints = [];
foreach ($monthlyTrend as $index => $month) {
    $x = $trendPlot['left'] + ($trendPlot['right'] - $trendPlot['left']) * $index / max(count($monthlyTrend) - 1, 1);
    $y = $trendPlot['bottom'] - ($trendPlot['bottom'] - $trendPlot['top']) * $month['count'] / $trendMax;
    $trendPoints[] = ['x' => round($x, 2), 'y' => round($y, 2), 'count' => $month['count'], 'label' => $month['label']];
}
$trendLinePath = implode(' ', array_map(
    static fn(array $point, int $index): string => ($index === 0 ? 'M' : 'L') . $point['x'] . ',' . $point['y'],
    $trendPoints,
    array_keys($trendPoints)
));

// Response time by staff member
$byStaff = $pdo->query("
    SELECT u.full_name, AVG(TIMESTAMPDIFF(MINUTE, i.created_at, m.created_at)) AS avg_minutes, COUNT(DISTINCT m.inquiry_id) as handled
    FROM messages m
    JOIN users u ON u.user_id = m.sender_id
    JOIN inquiries i ON i.inquiry_id = m.inquiry_id
    WHERE m.sender_role = 'Staff'
    GROUP BY u.user_id
    ORDER BY avg_minutes ASC
")->fetchAll();

$pageTitle = 'Overview & Reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="shell">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <main class="shell-main">
        <div class="shell-topbar"><div><h2>Overview &amp; Reports</h2><div class="sub"><?= date('F Y') ?></div></div></div>
        <div class="shell-body">
            <div class="kpi-row">
                <div class="kpi"><div class="lbl">Inquiries This Month</div><div class="val"><?= $totalThisMonth ?></div></div>
                <div class="kpi"><div class="lbl">Avg. First Response</div><div class="val"><?= $avgHours !== null ? $avgHours.' hrs' : '—' ?></div></div>
                <div class="kpi"><div class="lbl">Open Tickets</div><div class="val"><?= $openTickets ?></div></div>
                <div class="kpi"><div class="lbl">Resolved This Month</div><div class="val"><?= $resolvedThisMonth ?></div></div>
            </div>

            <div class="grid-2">
                <div class="panel">
                    <div class="panel-head"><h3>Inquiries by Department</h3></div>
                    <div class="panel-body">
                        <?php if (empty($byDept)): ?>
                            <div class="empty"><div class="ic">◧</div>No inquiries yet.</div>
                        <?php else: ?>
                        <div class="bar-chart">
                            <?php foreach ($byDept as $index => $row): $pct = round(($row['cnt']/$maxDeptCount)*100); ?>
                                <div class="col" style="--bar-delay:<?= (int)$index * 90 ?>ms">
                                    <div class="val"><?= $row['cnt'] ?></div>
                                    <div class="bar" style="height:<?= max($pct,4) ?>%"></div>
                                    <div class="lbl"><?= htmlspecialchars($row['department']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-head"><h3>Response Time by Staff</h3></div>
                    <div class="panel-body" style="display:flex;flex-direction:column;gap:12px;font-size:13px;">
                        <?php if (empty($byStaff)): ?>
                            <div class="empty" style="padding:20px;"><div class="ic">☺</div>No staff replies logged yet.</div>
                        <?php else: foreach ($byStaff as $s): ?>
                            <div style="display:flex;justify-content:space-between;">
                                <span><?= htmlspecialchars($s['full_name']) ?></span>
                                <b class="mono"><?= round($s['avg_minutes']/60, 1) ?> hrs</b>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
            <div class="panel inquiry-trend-panel">
                <div class="panel-head">
                    <h3>Inquiry Trend</h3>
                    <span class="chart-period">Last 6 months</span>
                </div>
                <div class="panel-body">
                    <div class="line-chart-wrap">
                        <svg class="line-chart" viewBox="0 0 640 230" role="img" aria-labelledby="inquiry-trend-title inquiry-trend-description">
                            <title id="inquiry-trend-title">Monthly inquiry trend</title>
                            <desc id="inquiry-trend-description"><?php foreach ($monthlyTrend as $month): ?><?= htmlspecialchars($month['label'], ENT_QUOTES, 'UTF-8') ?>: <?= $month['count'] ?> inquiries. <?php endforeach; ?></desc>
                            <?php for ($tick = 0; $tick <= 3; $tick++):
                                $y = $trendPlot['bottom'] - ($trendPlot['bottom'] - $trendPlot['top']) * $tick / 3;
                                $value = (int)round($trendMax * $tick / 3);
                            ?>
                                <line class="line-chart-grid" x1="<?= $trendPlot['left'] ?>" y1="<?= round($y, 2) ?>" x2="<?= $trendPlot['right'] ?>" y2="<?= round($y, 2) ?>"></line>
                                <text class="line-chart-y-label" x="<?= $trendPlot['left'] - 12 ?>" y="<?= round($y + 4, 2) ?>"><?= $value ?></text>
                            <?php endfor; ?>
                            <path class="line-chart-path" d="<?= htmlspecialchars($trendLinePath, ENT_QUOTES, 'UTF-8') ?>"></path>
                            <?php foreach ($trendPoints as $index => $point): ?>
                                <circle class="line-chart-point" cx="<?= $point['x'] ?>" cy="<?= $point['y'] ?>" r="4" style="animation-delay:<?= (int)$index * 150 ?>ms">
                                    <title><?= htmlspecialchars($point['label'], ENT_QUOTES, 'UTF-8') ?>: <?= $point['count'] ?> inquiries</title>
                                </circle>
                                <text class="line-chart-x-label" x="<?= $point['x'] ?>" y="207"><?= htmlspecialchars($point['label'], ENT_QUOTES, 'UTF-8') ?></text>
                            <?php endforeach; ?>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
