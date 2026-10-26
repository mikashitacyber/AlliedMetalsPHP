-- Run in phpMyAdmin after upgrade_v10.sql. Enables image attachments in direct messages.
USE allied_metals;

ALTER TABLE customer_direct_messages
    ADD COLUMN attachment_path VARCHAR(255) NULL AFTER body,
    ADD COLUMN attachment_mime VARCHAR(40) NULL AFTER attachment_path;
