<?php
require __DIR__ . '/config/config.php';
requireAnyRole(['Staff', 'Technician']);
require __DIR__ . '/includes/employee_task_history.php';

$user = currentUser();
$show = ($_GET['show'] ?? '') === 'done' ? 'done' : 'active';
$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
if (empty($_SESSION['employee_work_csrf'])) {
    $_SESSION['employee_work_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taskId = filter_var($_POST['task_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $requestedStatus = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
    $workNotes = is_string($_POST['work_notes'] ?? null) ? trim($_POST['work_notes']) : '';
    $redirectShow = $show;
    if (!hash_equals($_SESSION['employee_work_csrf'], $token) || $taskId === false
        || !in_array($action, ['save_notes', 'advance'], true) || mb_strlen($workNotes, 'UTF-8') > 5000) {
        $_SESSION['flash'] = 'Refresh the page and check the task update.';
    } else {
        try {
            $pdo->beginTransaction();
            $taskStmt = $pdo->prepare('SELECT task_id, status FROM employee_tasks WHERE task_id = ? AND assigned_to = ? FOR UPDATE');
            $taskStmt->execute([$taskId, (int)$user['user_id']]);
            $task = $taskStmt->fetch();
            if (!$task || $task['status'] === 'Completed') {
                throw new InvalidArgumentException('This task is unavailable or already completed.');
            }
            $newStatus = $action === 'save_notes' ? $task['status'] : $requestedStatus;
            $validTransition = $newStatus === $task['status']
                || ($task['status'] === 'Assigned' && $newStatus === 'In Progress')
                || ($task['status'] === 'In Progress' && $newStatus === 'Completed');
            if (!$validTransition) {
                throw new InvalidArgumentException('Update the task one step at a time.');
            }

            $before = employeeTaskSnapshot($pdo, (int)$taskId);
            $pdo->prepare("
                UPDATE employee_tasks
                SET status = ?, work_notes = ?, completed_at = CASE WHEN ? = 'Completed' THEN NOW() ELSE NULL END
                WHERE task_id = ? AND assigned_to = ?
            ")->execute([$newStatus, $workNotes !== '' ? $workNotes : null, $newStatus, $taskId, (int)$user['user_id']]);
            $after = employeeTaskSnapshot($pdo, (int)$taskId);
            $historyAction = $newStatus !== $task['status'] ? 'status_changed' : 'notes_updated';
            recordEmployeeTaskHistory($pdo, (int)$taskId, (int)$user['user_id'], $historyAction, $before, $after);
            $pdo->commit();
            $_SESSION['flash'] = $newStatus === 'Completed' ? 'Task marked complete.' : 'Task update saved.';
            if ($newStatus === 'Completed') {
                $redirectShow = 'done';
            }
        } catch (InvalidArgumentException $taskError) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['flash'] = $taskError->getMessage();
        } catch (Throwable $taskError) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Employee work update failed: ' . $taskError->getMessage());
            $_SESSION['flash'] = 'The task update could not be saved. Please try again.';
        }
    }
    header('Location: employee_work.php?show=' . $redirectShow . ($taskId ? '&id=' . (int)$taskId : ''));
    exit;
}

$statusWhere = $show === 'done' ? "t.status = 'Completed'" : "t.status <> 'Completed'";
$tasksStmt = $pdo->prepare("
    SELECT t.*, assigner.full_name AS assigner_name, i.ticket_no, i.type AS inquiry_type,
        i.status AS inquiry_status, i.location AS inquiry_location,
        customer.full_name AS customer_name, customer.company AS customer_company, customer.phone AS customer_phone
    FROM employee_tasks t
    JOIN users assigner ON assigner.user_id = t.assigned_by
    LEFT JOIN inquiries i ON i.inquiry_id = t.inquiry_id
    LEFT JOIN users customer ON customer.user_id = i.user_id
    WHERE t.assigned_to = ? AND $statusWhere
    ORDER BY t.due_date IS NULL, t.due_date, t.created_at DESC
");
$tasksStmt->execute([(int)$user['user_id']]);
$tasks = $tasksStmt->fetchAll();
$selectedId = (int)($_GET['id'] ?? ($tasks[0]['task_id'] ?? 0));
$selected = null;
foreach ($tasks as $task) {
    if ((int)$task['task_id'] === $selectedId) {
        $selected = $task;
        break;
    }
}
if (!$selected && $tasks) {
    $selected = $tasks[0];
    $selectedId = (int)$selected['task_id'];
}

$pageTitle = $show === 'done' ? 'Completed Work' : 'My Work';
require __DIR__ . '/includes/header.php';
?>
<div class="shell">
    <?php if ($user['role'] === 'Technician'): ?>
        <?php require __DIR__ . '/technician/_sidebar.php'; ?>
    <?php else: ?>
        <?php require __DIR__ . '/staff/_sidebar.php'; ?>
    <?php endif; ?>
    <main class="shell-main">
        <div class="shell-topbar">
            <div><h2><?= $pageTitle ?></h2><div class="sub"><?= $show === 'done' ? 'Work you have completed' : 'Tasks assigned to you' ?> · <?= count($tasks) ?> task<?= count($tasks) === 1 ? '' : 's' ?></div></div>
            <div class="top-actions">
                <a class="btn btn-outline btn-sm" href="employee_work.php">Active</a>
                <a class="btn btn-outline btn-sm" href="employee_work.php?show=done">Completed</a>
            </div>
        </div>
        <div class="shell-body">
            <?php if (!$tasks): ?>
                <div class="empty"><div class="ic">✓</div><?= $show === 'done' ? 'You have no completed tasks yet.' : 'No work is assigned to you right now.' ?></div>
            <?php else: ?>
            <div class="list-detail">
                <div class="list-col">
                    <?php foreach ($tasks as $task): ?>
                        <a class="ticket <?= (int)$task['task_id'] === $selectedId ? 'selected' : '' ?>" href="employee_work.php?show=<?= $show ?>&amp;id=<?= (int)$task['task_id'] ?>">
                            <div class="ticket-perf"></div>
                            <div class="ticket-top"><span class="ticket-id mono">TASK-<?= (int)$task['task_id'] ?></span><span class="stamp <?= $task['status'] === 'Completed' ? 'stamp-resolved' : ($task['status'] === 'In Progress' ? 'stamp-progress' : 'stamp-received') ?>"><?= $escape($task['status']) ?></span></div>
                            <div class="ticket-body">
                                <h4><?= $escape($task['title']) ?></h4>
                                <div class="ticket-meta"><span><?= $escape($task['task_type']) ?></span></div>
                                <div class="ticket-meta"><span>Due: <?= $escape($task['due_date'] ?: 'Not set') ?></span></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <section class="detail-col">
                    <div class="detail-head">
                        <div class="ticket-id-lg mono">TASK-<?= (int)$selected['task_id'] ?> · <?= $escape($selected['task_type']) ?></div>
                        <h3><?= $escape($selected['title']) ?></h3>
                        <div class="ticket-meta"><span>Assigned by <?= $escape($selected['assigner_name']) ?></span><span>Due <?= $escape($selected['due_date'] ?: 'No due date') ?></span></div>
                    </div>
                    <div class="ticket-info-strip">
                        <div><div class="strip-label">Instructions</div><div class="strip-value"><?= $selected['details'] ? nl2br($escape($selected['details'])) : 'No additional instructions' ?></div></div>
                        <?php if ($selected['site_address']): ?><div><div class="strip-label">Site address</div><div class="strip-value"><?= $escape($selected['site_address']) ?></div></div><?php endif; ?>
                        <?php if ($selected['ticket_no']): ?>
                            <div>
                                <div class="strip-label">Linked customer order</div>
                                <div class="strip-value"><?= $escape($selected['ticket_no']) ?> — <?= $escape($selected['customer_company']) ?></div>
                                <div class="muted" style="font-size:12px;margin-top:4px;"><?= $escape($selected['customer_name']) ?><?= $selected['customer_phone'] ? ' · ' . $escape($selected['customer_phone']) : '' ?> · <?= $escape($selected['inquiry_location'] ?: 'No order location') ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <form method="post" class="job-section">
                        <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['employee_work_csrf']) ?>">
                        <input type="hidden" name="task_id" value="<?= (int)$selected['task_id'] ?>">
                        <div class="field"><label for="task-work-notes">Work notes</label><textarea id="task-work-notes" name="work_notes" rows="4" maxlength="5000" <?= $selected['status'] === 'Completed' ? 'disabled' : '' ?>><?= $escape($selected['work_notes'] ?? '') ?></textarea></div>
                        <?php if ($selected['status'] !== 'Completed'): ?>
                            <?php $nextStatus = $selected['status'] === 'Assigned' ? 'In Progress' : 'Completed'; ?>
                            <input type="hidden" name="status" value="<?= $nextStatus ?>">
                            <button class="btn btn-outline btn-sm" type="submit" name="action" value="save_notes">Save Notes</button>
                            <button class="btn btn-primary" type="submit" name="action" value="advance"><?= $nextStatus === 'Completed' ? 'Mark Complete' : 'Start Work' ?></button>
                        <?php else: ?>
                            <span class="presence online"><span class="presence-dot"></span>Completed<?= $selected['completed_at'] ? ' ' . $escape(date('M j, Y g:i A', strtotime($selected['completed_at']))) : '' ?></span>
                        <?php endif; ?>
                    </form>
                </section>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
