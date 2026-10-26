-- Add uploader details so inquiry documents can be searched and attributed.
ALTER TABLE attachments
    ADD COLUMN uploaded_by INT NULL AFTER file_path,
    ADD COLUMN uploaded_role ENUM('Customer','Staff','HR','Admin','Technician') NULL AFTER uploaded_by,
    ADD INDEX idx_attachments_uploaded_at (uploaded_at),
    ADD CONSTRAINT fk_attachments_uploader FOREIGN KEY (uploaded_by) REFERENCES users(user_id);

-- Existing attachments were originally submitted by the customer who opened the inquiry.
UPDATE attachments a
JOIN inquiries i ON i.inquiry_id = a.inquiry_id
SET a.uploaded_by = i.user_id, a.uploaded_role = 'Customer'
WHERE a.uploaded_by IS NULL;
