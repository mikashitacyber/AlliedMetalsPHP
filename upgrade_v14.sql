-- Add formal customer quotations to existing inquiries.
USE allied_metals;

CREATE TABLE inquiry_quotes (
    quote_id       INT AUTO_INCREMENT PRIMARY KEY,
    inquiry_id     INT NOT NULL UNIQUE,
    amount         DECIMAL(12,2) NOT NULL,
    notes          TEXT NOT NULL,
    valid_until    DATE NOT NULL,
    status         ENUM('offered','accepted','declined') NOT NULL DEFAULT 'offered',
    quoted_by      INT NOT NULL,
    quoted_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at   DATETIME NULL,
    FOREIGN KEY (inquiry_id) REFERENCES inquiries(inquiry_id),
    FOREIGN KEY (quoted_by) REFERENCES users(user_id)
) ENGINE=InnoDB;
