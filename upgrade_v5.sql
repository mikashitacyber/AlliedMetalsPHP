-- Run in phpMyAdmin (select allied_metals first, then its SQL tab), AFTER upgrade_v3 and upgrade_v4.
-- Adds the Field Technician role and job orders (delivery / installation assignments).
USE allied_metals;

ALTER TABLE users MODIFY role ENUM('Customer','Staff','HR','Technician','Admin') NOT NULL DEFAULT 'Customer';

CREATE TABLE IF NOT EXISTS job_orders (
    job_id         INT AUTO_INCREMENT PRIMARY KEY,
    inquiry_id     INT NOT NULL UNIQUE,            -- one job order per order
    technician_id  INT NOT NULL,                   -- the field technician assigned
    assigned_by    INT NOT NULL,                   -- the staff member who assigned it
    job_type       ENUM('Delivery','Installation','Delivery & Installation') NOT NULL DEFAULT 'Delivery',
    site_address   VARCHAR(255) NULL,
    scheduled_date DATE NULL,
    status         ENUM('Assigned','On the way','Completed') NOT NULL DEFAULT 'Assigned',
    tech_notes     TEXT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at   DATETIME NULL,
    FOREIGN KEY (inquiry_id) REFERENCES inquiries(inquiry_id),
    FOREIGN KEY (technician_id) REFERENCES users(user_id),
    FOREIGN KEY (assigned_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS job_checklist_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    job_id  INT NOT NULL,
    label   VARCHAR(200) NOT NULL,
    is_done TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (job_id) REFERENCES job_orders(job_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- The typed-in driver fields are replaced by the real assignment above.
ALTER TABLE inquiries DROP COLUMN target_delivery_date;
ALTER TABLE inquiries DROP COLUMN driver_name;
ALTER TABLE inquiries DROP COLUMN driver_contact;
