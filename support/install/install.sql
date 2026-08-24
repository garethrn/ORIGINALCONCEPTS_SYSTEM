-- ============================================================
-- Original Concepts Support System – Database Schema
-- MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Users ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`          VARCHAR(150)  NOT NULL,
    `email`         VARCHAR(150)  NOT NULL UNIQUE,
    `password`      VARCHAR(255)  NOT NULL,
    `role`          ENUM('admin','manager','technician','client') NOT NULL DEFAULT 'technician',
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `last_login_at` DATETIME     NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_role`  (`role`),
    INDEX `idx_active`(`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Clients ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `clients` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(150)  NOT NULL,
    `company`     VARCHAR(150)  NULL,
    `email`       VARCHAR(150)  NULL,
    `phone`       VARCHAR(30)   NULL,
    `address`     VARCHAR(255)  NULL,
    `city`        VARCHAR(100)  NULL,
    `province`    VARCHAR(100)  NULL,
    `postal_code` VARCHAR(10)   NULL,
    `country`     VARCHAR(80)   NOT NULL DEFAULT 'South Africa',
    `latitude`    DECIMAL(10,7) NULL,
    `longitude`   DECIMAL(10,7) NULL,
    `notes`       TEXT          NULL,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email`  (`email`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Tickets ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tickets` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_id`       INT UNSIGNED NULL,
    `title`           VARCHAR(255) NOT NULL,
    `description`     TEXT         NOT NULL,
    `category`        VARCHAR(50)  NOT NULL DEFAULT 'other',
    `priority`        ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    `status`          ENUM('open','in_progress','waiting','resolved','closed','cancelled') NOT NULL DEFAULT 'open',
    `assigned_to`     INT UNSIGNED NULL,
    `location`        VARCHAR(255) NULL,
    `resolution`      TEXT         NULL,
    `attachment_path` VARCHAR(255) NULL,
    `created_by`      INT UNSIGNED NULL,
    `resolved_at`     DATETIME     NULL,
    `closed_at`       DATETIME     NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_tickets_client`    FOREIGN KEY (`client_id`)  REFERENCES `clients`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tickets_assignee`  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`)  ON DELETE SET NULL,
    CONSTRAINT `fk_tickets_creator`   FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)  ON DELETE SET NULL,
    INDEX `idx_status`    (`status`),
    INDEX `idx_priority`  (`priority`),
    INDEX `idx_category`  (`category`),
    INDEX `idx_client`    (`client_id`),
    INDEX `idx_assigned`  (`assigned_to`),
    INDEX `idx_created`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Ticket Comments ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ticket_comments` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_id`       INT UNSIGNED NOT NULL,
    `user_id`         INT UNSIGNED NULL,
    `comment`         TEXT         NOT NULL,
    `is_internal`     TINYINT(1)  NOT NULL DEFAULT 0,
    `attachment_path` VARCHAR(255) NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_tc_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tc_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE SET NULL,
    INDEX `idx_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Ticket History ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ticket_history` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_id`  INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NULL,
    `action`     VARCHAR(50)  NOT NULL,
    `note`       VARCHAR(500) NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_th_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_th_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE SET NULL,
    INDEX `idx_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Invoices ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `invoices` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_id`     INT UNSIGNED NOT NULL,
    `ticket_id`     INT UNSIGNED NULL,
    `line_items`    JSON         NOT NULL,
    `subtotal`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `tax_rate`      DECIMAL(5,2)  NOT NULL DEFAULT 15.00,
    `tax_amount`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_amount`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `notes`         TEXT          NULL,
    `status`        ENUM('draft','sent','paid','overdue','void') NOT NULL DEFAULT 'draft',
    `due_date`      DATE          NOT NULL,
    `paid_at`       DATETIME      NULL,
    `qb_invoice_id` VARCHAR(50)   NULL,
    `created_by`    INT UNSIGNED  NULL,
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_inv_client`  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_inv_ticket`  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_inv_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)  ON DELETE SET NULL,
    INDEX `idx_status`    (`status`),
    INDEX `idx_client`    (`client_id`),
    INDEX `idx_due_date`  (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Quotes ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `quotes` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_id`      INT UNSIGNED NOT NULL,
    `ticket_id`      INT UNSIGNED NULL,
    `line_items`     JSON          NOT NULL,
    `subtotal`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `tax_rate`       DECIMAL(5,2)  NOT NULL DEFAULT 15.00,
    `tax_amount`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_amount`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `notes`          TEXT          NULL,
    `status`         ENUM('draft','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft',
    `valid_until`    DATE          NOT NULL,
    `qb_estimate_id` VARCHAR(50)   NULL,
    `created_by`     INT UNSIGNED  NULL,
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_qt_client`  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_qt_ticket`  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_qt_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)  ON DELETE SET NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── QuickBooks Tokens ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `qb_tokens` (
    `id`                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `realm_id`                 VARCHAR(50)   NOT NULL UNIQUE,
    `access_token`             TEXT          NOT NULL,
    `refresh_token`            TEXT          NOT NULL,
    `expires_at`               DATETIME      NOT NULL,
    `refresh_token_expires_at` DATETIME      NULL,
    `created_at`               DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`               DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Application Settings ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `app_settings` (
    `setting_key`   VARCHAR(100) PRIMARY KEY,
    `setting_value` TEXT         NOT NULL,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES
    ('invoice_default_due_days', '30'),
    ('invoice_default_tax_rate', '15'),
    ('allow_manager_billing', '1'),
    ('allow_manager_clients', '1'),
    ('allow_technician_clients', '1')
ON DUPLICATE KEY UPDATE
    `setting_value` = VALUES(`setting_value`);

-- ─── Default Admin User ───────────────────────────────────────────────────────
-- Password: Admin@1234  (CHANGE THIS IMMEDIATELY AFTER INSTALLATION)
INSERT IGNORE INTO `users` (`name`, `email`, `password`, `role`, `is_active`, `created_at`, `updated_at`)
VALUES (
    'System Administrator',
    'admin@originalconcepts.co.za',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- password = Admin@1234
    'admin',
    1,
    NOW(),
    NOW()
);

-- ─── Sample Client ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `clients` (`name`, `company`, `email`, `phone`, `city`, `country`, `is_active`, `created_at`, `updated_at`)
VALUES ('Sample Client', 'Sample Company', 'client@example.com', '+27 000 000 0000', 'Johannesburg', 'South Africa', 1, NOW(), NOW());
