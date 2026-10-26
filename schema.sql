-- Fresh install: run this whole file in phpMyAdmin's SQL tab.
-- Creates the database and every table the system uses.
-- (Already have a database from an earlier version? Apply the required upgrade scripts in order.)

CREATE DATABASE IF NOT EXISTS allied_metals;
USE allied_metals;

CREATE TABLE users (
    user_id        INT AUTO_INCREMENT PRIMARY KEY,
    full_name      VARCHAR(120) NOT NULL,
    company        VARCHAR(160) NOT NULL,
    email          VARCHAR(160) NOT NULL UNIQUE,
    phone          VARCHAR(30),
    password_hash  VARCHAR(255) NOT NULL,
    role           ENUM('Customer','Staff','HR','Technician','Admin') NOT NULL DEFAULT 'Customer',
    department     VARCHAR(60) NULL,         -- only meaningful when role = 'Staff'
    profile_bio    VARCHAR(240) NULL,
    profile_picture_path VARCHAR(255) NULL,
    is_active      TINYINT(1) NOT NULL DEFAULT 1,
    last_active_at DATETIME NULL,            -- powers the "Online now / Last active" indicator
    manual_status  ENUM('available','break','offline') NOT NULL DEFAULT 'available', -- set by the user on their profile
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE customer_signup_requests (
    signup_id       INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(120) NOT NULL,
    company         VARCHAR(160) NOT NULL,
    email           VARCHAR(160) NOT NULL UNIQUE,
    phone           VARCHAR(30) NULL,
    password_hash   VARCHAR(255) NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE inquiries (
    inquiry_id           INT AUTO_INCREMENT PRIMARY KEY,
    ticket_no            VARCHAR(20) NOT NULL UNIQUE,
    user_id              INT NOT NULL,                 -- the customer who filed it
    type                 VARCHAR(80) NOT NULL,
    sector               VARCHAR(80),
    department           VARCHAR(60) NOT NULL DEFAULT 'Customer Service',
    assigned_staff_id    INT NULL,                     -- the staff member HR assigned it to
    location             VARCHAR(160),
    status               VARCHAR(30) NOT NULL DEFAULT 'Received',
    priority             ENUM('low','med','high') NOT NULL DEFAULT 'med',
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (assigned_staff_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE messages (
    message_id  INT AUTO_INCREMENT PRIMARY KEY,
    inquiry_id  INT NOT NULL,
    sender_id   INT NOT NULL,
    sender_role ENUM('Customer','Staff') NOT NULL,
    body        TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inquiry_id) REFERENCES inquiries(inquiry_id),
    FOREIGN KEY (sender_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE customer_conversations (
    conversation_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id     INT NOT NULL,
    customer_id     INT NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customer_conversation_pair (employee_id, customer_id),
    INDEX idx_customer_conversations_customer (customer_id, updated_at),
    FOREIGN KEY (employee_id) REFERENCES users(user_id),
    FOREIGN KEY (customer_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE customer_direct_messages (
    message_id      INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id       INT NOT NULL,
    body            TEXT NOT NULL,
    attachment_path VARCHAR(255) NULL,
    attachment_mime VARCHAR(40) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer_direct_messages_thread (conversation_id, message_id),
    FOREIGN KEY (conversation_id) REFERENCES customer_conversations(conversation_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

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

CREATE TABLE attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    inquiry_id    INT NOT NULL,
    file_name     VARCHAR(255) NOT NULL,
    file_path     VARCHAR(255) NOT NULL,
    uploaded_by   INT NULL,
    uploaded_role ENUM('Customer','Staff','HR','Admin','Technician') NULL,
    uploaded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attachments_uploaded_at (uploaded_at),
    FOREIGN KEY (inquiry_id) REFERENCES inquiries(inquiry_id),
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE status_history (
    history_id  INT AUTO_INCREMENT PRIMARY KEY,
    inquiry_id  INT NOT NULL,
    old_status  VARCHAR(30),
    new_status  VARCHAR(30) NOT NULL,
    changed_by  INT NOT NULL,
    changed_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inquiry_id) REFERENCES inquiries(inquiry_id)
) ENGINE=InnoDB;

CREATE TABLE employee_tasks (
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

CREATE TABLE employee_task_history (
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

-- No public sign-up exists. Create your first Admin with setup_first_admin.php,
-- then create every other account from inside the Admin portal.
