-- OTP Table for Forgot Password feature
-- Run this in phpMyAdmin or MySQL CLI

CREATE TABLE IF NOT EXISTS `password_reset_otp` (
    `otp_id`      INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED     NOT NULL,
    `phone`       VARCHAR(20)      NOT NULL,
    `otp_code`    VARCHAR(6)       NOT NULL,
    `is_used`     TINYINT(1)       NOT NULL DEFAULT 0,
    `expires_at`  DATETIME         NOT NULL,
    `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`otp_id`),
    KEY `idx_user_otp` (`user_id`, `is_used`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

