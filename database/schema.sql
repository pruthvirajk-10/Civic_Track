-- ========================================================
-- CivicTrack — Smart City Civic Complaint Management System
-- Database Schema: schema.sql
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ========================================================

CREATE DATABASE IF NOT EXISTS `civictrack_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `civictrack_db`;

-- Disable foreign key checks during creation
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 1. Departments Table
-- --------------------------------------------------------
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
    `dept_id` INT AUTO_INCREMENT PRIMARY KEY,
    `dept_name` VARCHAR(120) NOT NULL,
    `dept_code` VARCHAR(20) NOT NULL UNIQUE,
    `dept_head_email` VARCHAR(150) NULL,
    `phone` VARCHAR(25) NULL,
    `description` TEXT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_dept_code` (`dept_code`),
    INDEX `idx_dept_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. Users Table (Citizens, Department Officers, Admins)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(25) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('citizen', 'officer', 'admin') NOT NULL DEFAULT 'citizen',
    `dept_id` INT NULL,
    `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    `address` VARCHAR(255) NULL,
    `ward` VARCHAR(50) NULL,
    `avatar` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`dept_id`) REFERENCES `departments`(`dept_id`) ON DELETE SET NULL,
    INDEX `idx_user_email` (`email`),
    INDEX `idx_user_role` (`role`),
    INDEX `idx_user_dept` (`dept_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Complaint Categories Table
-- --------------------------------------------------------
DROP TABLE IF EXISTS `complaint_categories`;
CREATE TABLE `complaint_categories` (
    `category_id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_name` VARCHAR(100) NOT NULL UNIQUE,
    `dept_id` INT NOT NULL,
    `sla_hours` INT NOT NULL DEFAULT 48 COMMENT 'Expected resolution time in hours',
    `default_priority` ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Medium',
    `icon_class` VARCHAR(60) DEFAULT 'bi-exclamation-triangle',
    `description` VARCHAR(255) NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`dept_id`) REFERENCES `departments`(`dept_id`) ON DELETE RESTRICT,
    INDEX `idx_cat_dept` (`dept_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. Complaints Table
-- --------------------------------------------------------
DROP TABLE IF EXISTS `complaints`;
CREATE TABLE `complaints` (
    `complaint_id` INT AUTO_INCREMENT PRIMARY KEY,
    `reference_number` VARCHAR(30) NOT NULL UNIQUE,
    `citizen_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `dept_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NOT NULL,
    `priority` ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Medium',
    `image_url` VARCHAR(255) NULL,
    `latitude` DECIMAL(10, 8) NULL,
    `longitude` DECIMAL(11, 8) NULL,
    `address` TEXT NOT NULL,
    `ward` VARCHAR(60) NULL,
    `status` ENUM('Submitted', 'Under Review', 'Assigned', 'In Progress', 'Resolved', 'Closed', 'Reopened') DEFAULT 'Submitted',
    `assigned_officer_id` INT NULL,
    `sla_deadline` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `resolved_at` DATETIME NULL,
    FOREIGN KEY (`citizen_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `complaint_categories`(`category_id`) ON DELETE RESTRICT,
    FOREIGN KEY (`dept_id`) REFERENCES `departments`(`dept_id`) ON DELETE RESTRICT,
    FOREIGN KEY (`assigned_officer_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    INDEX `idx_comp_ref` (`reference_number`),
    INDEX `idx_comp_status` (`status`),
    INDEX `idx_comp_dept` (`dept_id`),
    INDEX `idx_comp_citizen` (`citizen_id`),
    INDEX `idx_comp_ward` (`ward`),
    INDEX `idx_comp_sla` (`sla_deadline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Complaint Status Logs Table (Audit trail & timeline)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `complaint_logs`;
CREATE TABLE `complaint_logs` (
    `log_id` INT AUTO_INCREMENT PRIMARY KEY,
    `complaint_id` INT NOT NULL,
    `user_id` INT NOT NULL COMMENT 'User who triggered status change',
    `action` VARCHAR(60) NOT NULL COMMENT 'e.g. Status Update, Assignment, Remark, Reopened',
    `previous_status` ENUM('Submitted', 'Under Review', 'Assigned', 'In Progress', 'Resolved', 'Closed', 'Reopened') NULL,
    `new_status` ENUM('Submitted', 'Under Review', 'Assigned', 'In Progress', 'Resolved', 'Closed', 'Reopened') NOT NULL,
    `remarks` TEXT NULL,
    `proof_image` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`complaint_id`) REFERENCES `complaints`(`complaint_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    INDEX `idx_log_complaint` (`complaint_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. Feedback Table
-- --------------------------------------------------------
DROP TABLE IF EXISTS `feedback`;
CREATE TABLE `feedback` (
    `feedback_id` INT AUTO_INCREMENT PRIMARY KEY,
    `complaint_id` INT NOT NULL UNIQUE,
    `citizen_id` INT NOT NULL,
    `rating` TINYINT UNSIGNED NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
    `comments` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`complaint_id`) REFERENCES `complaints`(`complaint_id`) ON DELETE CASCADE,
    FOREIGN KEY (`citizen_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    INDEX `idx_feedback_rating` (`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. Notifications Table
-- --------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `notification_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `complaint_id` INT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`complaint_id`) REFERENCES `complaints`(`complaint_id`) ON DELETE CASCADE,
    INDEX `idx_notif_user` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 8. Mobile SMS Alerts & Dispatch Log Table
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sms_logs`;
CREATE TABLE `sms_logs` (
    `sms_id` INT AUTO_INCREMENT PRIMARY KEY,
    `complaint_id` INT NULL,
    `recipient_user_id` INT NOT NULL,
    `recipient_name` VARCHAR(100) NOT NULL,
    `recipient_phone` VARCHAR(30) NOT NULL,
    `event_type` VARCHAR(60) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('Sent', 'Delivered', 'Failed') DEFAULT 'Delivered',
    `gateway_response` VARCHAR(255) NULL DEFAULT 'Simulation Gateway: OK (200)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`recipient_user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`complaint_id`) REFERENCES `complaints`(`complaint_id`) ON DELETE SET NULL,
    INDEX `idx_sms_phone` (`recipient_phone`),
    INDEX `idx_sms_comp` (`complaint_id`),
    INDEX `idx_sms_user` (`recipient_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 9. System Settings Table
-- --------------------------------------------------------
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
    `setting_key` VARCHAR(60) PRIMARY KEY,
    `setting_value` TEXT NOT NULL,
    `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enable foreign key checks
-- ========================================================
-- Table: password_reset_otp
-- Purpose: Secure OTP verification for password recovery via SMS
-- ========================================================
CREATE TABLE IF NOT EXISTS `password_reset_otp` (
    `otp_id`      INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED     NOT NULL,
    `phone`       VARCHAR(20)      NOT NULL,
    `otp_code`    VARCHAR(6)       NOT NULL,
    `is_used`     TINYINT(1)       NOT NULL DEFAULT 0,
    `expires_at`  DATETIME         NOT NULL,
    `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`otp_id`),
    KEY `idx_user_otp` (`user_id`, `is_used`, `expires_at`),
    CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
