-- Run in phpMyAdmin after upgrade_v11.sql. Adds customer activity notifications.
USE allied_metals;

CREATE TABLE user_notifications (
    notification_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    title           VARCHAR(120) NOT NULL,
    message         VARCHAR(255) NOT NULL,
    target_url      VARCHAR(500) NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at         DATETIME NULL,
    INDEX idx_user_notifications_unread (user_id, read_at, notification_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;
