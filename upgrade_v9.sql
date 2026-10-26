-- Run in phpMyAdmin after upgrade_v8.sql. Adds employee work assignments.
USE allied_metals;

CREATE TABLE IF NOT EXISTS employee_tasks (
    task_id      INT AUTO_INCREMENT PRIMARY KEY,
    legacy_job_id INT NULL UNIQUE,
    title        VARCHAR(160) NOT NULL,
    task_type    ENUM('Delivery','Installation','Other') NOT NULL DEFAULT 'Other',
    details      TEXT NULL,
    site_address VARCHAR(255) NULL,
    due_date     DATE NULL,
    inquiry_id   INT NULL,
    assigned_to  INT NOT NULL,
    assigned_by  INT NOT NULL,
    status       ENUM('Assigned','In Progress','Completed') NOT NULL DEFAULT 'Assigned',
    work_notes   TEXT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    INDEX idx_employee_tasks_assignee_status (assigned_to, status),
    INDEX idx_employee_tasks_due_date (due_date),
    FOREIGN KEY (inquiry_id) REFERENCES inquiries(inquiry_id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(user_id),
    FOREIGN KEY (assigned_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS employee_task_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    task_id    INT NOT NULL,
    changed_by INT NOT NULL,
    action     VARCHAR(40) NOT NULL,
    old_values LONGTEXT NULL,
    new_values LONGTEXT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_employee_task_history_task_changed (task_id, changed_at),
    FOREIGN KEY (task_id) REFERENCES employee_tasks(task_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

SET SESSION group_concat_max_len = 10000;

INSERT INTO employee_tasks (
    legacy_job_id, title, task_type, details, site_address, due_date, inquiry_id,
    assigned_to, assigned_by, status, work_notes, created_at, completed_at
)
SELECT j.job_id,
    CONCAT(j.job_type, ' - ', customer.company),
    CASE j.job_type WHEN 'Installation' THEN 'Installation' ELSE 'Delivery' END,
    CONCAT_WS('\n\n',
        CONCAT('Migrated from customer order ', i.ticket_no, '.'),
        NULLIF(j.tech_notes, ''),
        IF(checklist.items IS NULL, NULL, CONCAT('Checklist:\n', checklist.items))
    ),
    j.site_address, j.scheduled_date, j.inquiry_id, j.technician_id, j.assigned_by,
    CASE j.status WHEN 'On the way' THEN 'In Progress' ELSE j.status END,
    j.tech_notes, j.created_at, j.completed_at
FROM job_orders j
JOIN inquiries i ON i.inquiry_id = j.inquiry_id
JOIN users customer ON customer.user_id = i.user_id
LEFT JOIN (
    SELECT job_id, GROUP_CONCAT(label ORDER BY item_id SEPARATOR '\n') AS items
    FROM job_checklist_items
    GROUP BY job_id
) checklist ON checklist.job_id = j.job_id
WHERE NOT EXISTS (
    SELECT 1 FROM employee_tasks migrated WHERE migrated.legacy_job_id = j.job_id
);

INSERT INTO employee_task_history (task_id, changed_by, action)
SELECT t.task_id, j.assigned_by, 'migrated'
FROM employee_tasks t
JOIN job_orders j ON j.job_id = t.legacy_job_id
WHERE NOT EXISTS (
    SELECT 1 FROM employee_task_history h
    WHERE h.task_id = t.task_id AND h.action = 'migrated'
);
