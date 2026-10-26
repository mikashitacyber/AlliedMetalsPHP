-- Run in phpMyAdmin after upgrade_v12.sql. Adds pending customer sign-up requests.
USE allied_metals;

CREATE TABLE customer_signup_requests (
    signup_id       INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(120) NOT NULL,
    company         VARCHAR(160) NOT NULL,
    email           VARCHAR(160) NOT NULL UNIQUE,
    phone           VARCHAR(30) NULL,
    password_hash   VARCHAR(255) NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
