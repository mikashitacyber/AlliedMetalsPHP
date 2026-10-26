-- Run in phpMyAdmin after upgrade_v9.sql. Adds employee-initiated customer messaging.
USE allied_metals;

CREATE TABLE IF NOT EXISTS customer_conversations (
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

CREATE TABLE IF NOT EXISTS customer_direct_messages (
    message_id      INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id       INT NOT NULL,
    body            TEXT NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer_direct_messages_thread (conversation_id, message_id),
    FOREIGN KEY (conversation_id) REFERENCES customer_conversations(conversation_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(user_id)
) ENGINE=InnoDB;
