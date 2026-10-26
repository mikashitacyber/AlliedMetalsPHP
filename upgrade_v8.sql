-- Run in phpMyAdmin after upgrade_v7.sql. Adds profile photos and bios.
USE allied_metals;

ALTER TABLE users
    ADD COLUMN profile_bio VARCHAR(240) NULL AFTER department,
    ADD COLUMN profile_picture_path VARCHAR(255) NULL AFTER profile_bio;
