<?php
function employeeTaskSnapshot(PDO $pdo, int $taskId): ?array {
    $stmt = $pdo->prepare("
        SELECT t.task_id, t.title, t.task_type, t.details, t.site_address, t.due_date,
            t.inquiry_id, i.ticket_no, t.assigned_to, assignee.full_name AS assignee_name,
            t.assigned_by, assigner.full_name AS assigner_name, t.status, t.work_notes,
            t.created_at, t.completed_at
        FROM employee_tasks t
        LEFT JOIN inquiries i ON i.inquiry_id = t.inquiry_id
        JOIN users assignee ON assignee.user_id = t.assigned_to
        JOIN users assigner ON assigner.user_id = t.assigned_by
        WHERE t.task_id = ?
    ");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    return $task ?: null;
}

function recordEmployeeTaskHistory(PDO $pdo, int $taskId, int $changedBy, string $action, ?array $before, ?array $after): void {
    $pdo->prepare("
        INSERT INTO employee_task_history (task_id, changed_by, action, old_values, new_values)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([
        $taskId,
        $changedBy,
        $action,
        $before === null ? null : json_encode($before, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        $after === null ? null : json_encode($after, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);
}
