-- Run in phpMyAdmin after upgrade_v6.sql. Adds an audit trail for job-order changes.
USE allied_metals;

CREATE TABLE IF NOT EXISTS job_order_history (
    history_id  INT AUTO_INCREMENT PRIMARY KEY,
    job_id      INT NOT NULL,
    changed_by  INT NOT NULL,
    action      VARCHAR(40) NOT NULL,
    old_values  LONGTEXT NULL,
    new_values  LONGTEXT NULL,
    changed_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_job_order_history_job_changed (job_id, changed_at),
    FOREIGN KEY (job_id) REFERENCES job_orders(job_id),
    FOREIGN KEY (changed_by) REFERENCES users(user_id)
) ENGINE=InnoDB;
