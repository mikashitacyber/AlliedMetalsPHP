-- Run this in phpMyAdmin's SQL tab against your EXISTING allied_metals database.
-- (Select allied_metals in the left sidebar first, then use its SQL tab.)
USE allied_metals;

-- 1. Delivery info for the "Ready for Delivery" / "Out for Delivery" stages.
--    Staff-entered, not live GPS.
ALTER TABLE inquiries ADD COLUMN target_delivery_date DATE NULL;
ALTER TABLE inquiries ADD COLUMN driver_name VARCHAR(120) NULL;
ALTER TABLE inquiries ADD COLUMN driver_contact VARCHAR(30) NULL;

-- 2. Powers the "Online now / Last active X ago" indicator for staff.
ALTER TABLE users ADD COLUMN last_active_at DATETIME NULL;

-- 3. Convert existing tickets to the new order-tracking status names.
UPDATE inquiries SET status = 'In Fabrication' WHERE status = 'In Progress';
UPDATE inquiries SET status = 'Delivered'      WHERE status = 'Resolved';
UPDATE status_history SET old_status = 'In Fabrication' WHERE old_status = 'In Progress';
UPDATE status_history SET new_status = 'In Fabrication' WHERE new_status = 'In Progress';
UPDATE status_history SET old_status = 'Delivered' WHERE old_status = 'Resolved';
UPDATE status_history SET new_status = 'Delivered' WHERE new_status = 'Resolved';

-- 4. Remove the features your team dropped (payroll + guest inquiries).
DROP TABLE IF EXISTS payroll_entries;
ALTER TABLE users DROP COLUMN rate_per_day;
ALTER TABLE inquiries DROP COLUMN guest_name;
ALTER TABLE inquiries DROP COLUMN guest_company;
ALTER TABLE inquiries DROP COLUMN guest_email;
ALTER TABLE inquiries DROP COLUMN guest_phone;

-- 5. OPTIONAL: only run these two lines if you have NO leftover guest-submitted
--    tickets (rows where user_id is NULL). Check first with:
--      SELECT * FROM inquiries WHERE user_id IS NULL;
--    If that returns rows, delete them (or skip this step; it's harmless to leave).
-- ALTER TABLE inquiries MODIFY user_id INT NOT NULL;
-- ALTER TABLE messages MODIFY sender_id INT NOT NULL;
