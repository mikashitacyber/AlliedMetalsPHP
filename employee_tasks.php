<?php
require __DIR__ . '/config/config.php';
requireAnyRole(['Admin', 'HR']);
require __DIR__ . '/includes/employee_task_history.php';

$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
if (empty($_SESSION['employee_tasks_csrf'])) {
    $_SESSION['employee_tasks_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
    if (!hash_equals($_SESSION['employee_tasks_csrf'], $token)) {
        $_SESSION['flash'] = 'Refresh the page and try again.';
        header('Location: employee_tasks.php');
        exit;
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $actorId = (int)currentUser()['user_id'];
    if ($action === 'create') {
        $title = is_string($_POST['title'] ?? null) ? trim($_POST['title']) : '';
        $taskType = is_string($_POST['task_type'] ?? null) ? $_POST['task_type'] : '';
        $details = is_string($_POST['details'] ?? null) ? trim($_POST['details']) : '';
        $siteAddress = is_string($_POST['site_address'] ?? null) ? trim($_POST['site_address']) : '';
        $dueDate = is_string($_POST['due_date'] ?? null) ? trim($_POST['due_date']) : '';
        $assigneeId = filter_var($_POST['assigned_to'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $inquiryValue = is_string($_POST['inquiry_id'] ?? null) ? $_POST['inquiry_id'] : '';
        $inquiryId = $inquiryValue === '' ? null : filter_var($inquiryValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $validTypes = ['Delivery', 'Installation', 'Other'];
        $dateValid = $dueDate === '' || (
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)
            && DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate)?->format('Y-m-d') === $dueDate
        );

        if ($title === '' || mb_strlen($title, 'UTF-8') > 160 || !in_array($taskType, $validTypes, true)
            || $assigneeId === false || $inquiryId === false || !$dateValid
            || mb_strlen($details, 'UTF-8') > 5000 || mb_strlen($siteAddress, 'UTF-8') > 255) {
            $_SESSION['flash'] = 'Check the task details and try again.';
        } elseif ($inquiryId !== null && !in_array($taskType, ['Delivery', 'Installation'], true)) {
            $_SESSION['flash'] = 'Only Delivery or Installation tasks can link to a customer order.';
        } else {
            $assigneeStmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ? AND role IN ('Staff','Technician') AND is_active = 1");
            $assigneeStmt->execute([$assigneeId]);
            $assigneeName = $assigneeStmt->fetchColumn();
            $inquiry = null;
            if ($inquiryId !== null) {
                $inquiryStmt = $pdo->prepare("
                    SELECT inquiry_id, type, status
                    FROM inquiries
                    WHERE inquiry_id = ? AND type <> 'General inquiry'
                    AND status NOT IN ('Answered','Delivered','Closed')
                ");
                $inquiryStmt->execute([$inquiryId]);
                $inquiry = $inquiryStmt->fetch();
            }

            if (!$assigneeName) {
                $_SESSION['flash'] = 'Choose an active Staff member or field technician.';
            } elseif ($inquiryId !== null && !$inquiry) {
                $_SESSION['flash'] = 'Choose an active customer order, not a general or completed inquiry.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare("
                        INSERT INTO employee_tasks (title, task_type, details, site_address, due_date, inquiry_id, assigned_to, assigned_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ")->execute([
                        $title,
                        $taskType,
                        $details !== '' ? $details : null,
                        $siteAddress !== '' ? $siteAddress : null,
                        $dueDate !== '' ? $dueDate : null,
                        $inquiryId,
                        $assigneeId,
                        $actorId,
                    ]);
                    $taskId = (int)$pdo->lastInsertId();
                    recordEmployeeTaskHistory($pdo, $taskId, $actorId, 'assigned', null, employeeTaskSnapshot($pdo, $taskId));
                    $pdo->commit();
                    $_SESSION['flash'] = 'Task assigned to ' . $assigneeName . '.';
                } catch (Throwable $taskError) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Employee task creation failed: ' . $taskError->getMessage());
                    $_SESSION['flash'] = 'The task could not be assigned. Check the employee task migration and try again.';
                }
            }
        }
    } elseif ($action === 'reassign') {
        $taskId = filter_var($_POST['task_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $assigneeId = filter_var($_POST['assigned_to'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $assigneeStmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ? AND role IN ('Staff','Technician') AND is_active = 1");
        $assigneeStmt->execute([$assigneeId]);
        $assigneeName = $assigneeStmt->fetchColumn();
        if ($taskId === false || $assigneeId === false || !$assigneeName) {
            $_SESSION['flash'] = 'Choose a valid active employee.';
        } else {
            try {
                $pdo->beginTransaction();
                $taskStmt = $pdo->prepare('SELECT task_id, status FROM employee_tasks WHERE task_id = ? FOR UPDATE');
                $taskStmt->execute([$taskId]);
                $task = $taskStmt->fetch();
                if (!$task || $task['status'] === 'Completed') {
                    throw new InvalidArgumentException('Completed or missing tasks cannot be reassigned.');
                }
                $before = employeeTaskSnapshot($pdo, $taskId);
                $pdo->prepare('UPDATE employee_tasks SET assigned_to = ? WHERE task_id = ?')->execute([$assigneeId, $taskId]);
                $after = employeeTaskSnapshot($pdo, $taskId);
                recordEmployeeTaskHistory($pdo, $taskId, $actorId, 'reassigned', $before, $after);
                $pdo->commit();
                $_SESSION['flash'] = 'Task reassigned to ' . $assigneeName . '.';
            } catch (InvalidArgumentException $taskError) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $_SESSION['flash'] = $taskError->getMessage();
            } catch (Throwable $taskError) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Employee task reassignment failed: ' . $taskError->getMessage());
                $_SESSION['flash'] = 'The task could not be reassigned. Please try again.';
            }
        }
    }
    header('Location: employee_tasks.php');
    exit;
}

$employees = $pdo->query("
    SELECT user_id, full_name, role, department
    FROM users
    WHERE role IN ('Staff','Technician') AND is_active = 1
    ORDER BY role, full_name
")->fetchAll();
$inquiries = $pdo->query("
    SELECT i.inquiry_id, i.ticket_no, i.type, c.company
    FROM inquiries i
    JOIN users c ON c.user_id = i.user_id
    WHERE i.type <> 'General inquiry' AND i.status NOT IN ('Answered','Delivered','Closed')
    ORDER BY i.created_at DESC
")->fetchAll();
$tasks = $pdo->query("
    SELECT t.*, assignee.full_name AS assignee_name, assignee.role AS assignee_role,
        assigner.full_name AS assigner_name, i.ticket_no, c.company
    FROM employee_tasks t
    JOIN users assignee ON assignee.user_id = t.assigned_to
    JOIN users assigner ON assigner.user_id = t.assigned_by
    LEFT JOIN inquiries i ON i.inquiry_id = t.inquiry_id
    LEFT JOIN users c ON c.user_id = i.user_id
    ORDER BY (t.status = 'Completed'), t.due_date IS NULL, t.due_date, t.created_at DESC
    LIMIT 100
")->fetchAll();
$historyByTask = [];
if ($tasks) {
    $taskIds = array_map(static fn($task) => (int)$task['task_id'], $tasks);
    $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
    $historyStmt = $pdo->prepare("
        SELECT h.task_id, h.action, h.old_values, h.new_values, h.changed_at, u.full_name AS changed_by_name
        FROM employee_task_history h
        JOIN users u ON u.user_id = h.changed_by
        WHERE h.task_id IN ($placeholders)
        ORDER BY h.changed_at DESC, h.history_id DESC
    ");
    $historyStmt->execute($taskIds);
    foreach ($historyStmt->fetchAll() as $history) {
        $historyByTask[(int)$history['task_id']][] = $history;
    }
}

$pageTitle = 'Work Assignments';
require __DIR__ . '/includes/header.php';
?>
<div class="shell">
    <?php if (currentUser()['role'] === 'Admin'): ?>
        <?php require __DIR__ . '/admin/_sidebar.php'; ?>
    <?php else: ?>
        <?php require __DIR__ . '/hr/_sidebar.php'; ?>
    <?php endif; ?>
    <main class="shell-main">
        <div class="shell-topbar"><div><h2>Work Assignments</h2><div class="sub">Assign deliveries, installations, and other work to employees</div></div></div>
        <div class="shell-body">
            <div class="grid-2">
                <section class="panel">
                    <div class="panel-head"><h3>Assign Work</h3></div>
                    <div class="panel-body">
                        <?php if (!$employees): ?>
                            <div class="form-note">Create or activate a Staff or Field Technician account before assigning work.</div>
                        <?php else: ?>
                        <form method="post" onsubmit="return confirm('Assign this task to the selected employee?');">
                            <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['employee_tasks_csrf']) ?>">
                            <input type="hidden" name="action" value="create">
                            <div class="field"><label for="task-title">Task title</label><input id="task-title" name="title" maxlength="160" required placeholder="e.g. Deliver equipment to customer site"></div>
                            <div class="form-row-2">
                                <div class="field"><label for="task-type">Work type</label>
                                    <select id="task-type" name="task_type" required>
                                        <option>Delivery</option><option>Installation</option><option>Other</option>
                                    </select>
                                </div>
                                <div class="field"><label for="task-assignee">Assign to</label>
                                    <select id="task-assignee" name="assigned_to" required>
                                        <option value="">Choose an employee</option>
                                        <?php foreach ($employees as $employee): ?>
                                            <option value="<?= (int)$employee['user_id'] ?>"><?= $escape($employee['full_name']) ?> — <?= $employee['role'] === 'Technician' ? 'Field Technician' : $escape($employee['department'] ?: 'Staff') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row-2">
                                <div class="field"><label for="task-date">Due date</label><input id="task-date" type="date" name="due_date"></div>
                                <div class="field"><label for="task-site">Customer / site address</label><input id="task-site" name="site_address" maxlength="255"></div>
                            </div>
                            <div class="field"><label for="task-inquiry">Link customer order (optional)</label>
                                <small>Customer orders can be linked to Delivery or Installation tasks.</small>
                                <select id="task-inquiry" name="inquiry_id">
                                    <option value="">No linked order</option>
                                    <?php foreach ($inquiries as $inquiry): ?>
                                        <option value="<?= (int)$inquiry['inquiry_id'] ?>"><?= $escape($inquiry['ticket_no'] . ' — ' . $inquiry['company'] . ' — ' . $inquiry['type']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field"><label for="task-details">Instructions / details</label><textarea id="task-details" name="details" maxlength="5000" rows="4"></textarea></div>
                            <button class="btn btn-primary" type="submit">Assign Task</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </section>
                <section class="panel">
                    <div class="panel-head"><h3>Assigned Work</h3><span class="sub">Latest <?= count($tasks) ?> tasks</span></div>
                    <div class="panel-body">
                        <?php if (!$tasks): ?>
                            <div class="empty"><div class="ic">✓</div>No employee tasks have been assigned yet.</div>
                        <?php else: ?>
                        <div class="table-scroll"><table class="data">
                            <thead><tr><th>Task</th><th>Assigned to</th><th>Due</th><th>Status</th><th>Reassign</th></tr></thead>
                            <tbody>
                            <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td><strong><?= $escape($task['title']) ?></strong><br><span class="muted"><?= $escape($task['task_type']) ?><?= $task['ticket_no'] ? ' · ' . $escape($task['ticket_no'] . ' — ' . $task['company']) : '' ?></span>
                                        <?php if ($task['details']): ?><details><summary>Instructions</summary><p><?= nl2br($escape($task['details'])) ?></p></details><?php endif; ?>
                                        <?php if ($task['site_address']): ?><div class="muted"><?= $escape($task['site_address']) ?></div><?php endif; ?>
                                        <?php if ($task['work_notes']): ?><details><summary>Employee notes</summary><p><?= nl2br($escape($task['work_notes'])) ?></p></details><?php endif; ?>
                                        <?php if (!empty($historyByTask[(int)$task['task_id']])): ?>
                                            <details><summary>Change history (<?= count($historyByTask[(int)$task['task_id']]) ?>)</summary>
                                                <ul><?php foreach ($historyByTask[(int)$task['task_id']] as $history): ?>
                                                    <li><?= $escape($history['changed_at']) ?> — <?= $escape($history['changed_by_name']) ?>: <?= $escape(ucfirst(str_replace('_', ' ', $history['action']))) ?></li>
                                                <?php endforeach; ?></ul>
                                            </details>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $escape($task['assignee_name']) ?><br><span class="muted"><?= $task['assignee_role'] === 'Technician' ? 'Field Technician' : 'Staff' ?></span></td>
                                    <td><?= $escape($task['due_date'] ?: 'Not set') ?></td>
                                    <td><?= $escape($task['status']) ?></td>
                                    <td>
                                        <?php if ($task['status'] !== 'Completed' && $employees): ?>
                                        <form method="post" style="display:flex;gap:6px;align-items:center;">
                                            <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['employee_tasks_csrf']) ?>">
                                            <input type="hidden" name="action" value="reassign">
                                            <input type="hidden" name="task_id" value="<?= (int)$task['task_id'] ?>">
                                            <select name="assigned_to" aria-label="Reassign <?= $escape($task['title']) ?>" required>
                                                <?php foreach ($employees as $employee): ?>
                                                    <option value="<?= (int)$employee['user_id'] ?>" <?= (int)$employee['user_id'] === (int)$task['assigned_to'] ? 'selected' : '' ?>><?= $escape($employee['full_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-outline btn-sm" type="submit">Save</button>
                                        </form>
                                        <?php elseif ($task['status'] !== 'Completed'): ?>
                                            No active employees
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table></div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
