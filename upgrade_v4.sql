-- Run this in phpMyAdmin's SQL tab against your existing allied_metals database,
-- AFTER upgrade_v3.sql. Lets staff set their own status (available / on break / offline).
USE allied_metals;
ALTER TABLE users ADD COLUMN manual_status ENUM('available','break','offline') NOT NULL DEFAULT 'available';
