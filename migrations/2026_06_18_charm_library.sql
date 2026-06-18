-- Migration: Charm / image-choice library
-- Run once against the production database.
--
-- Lets the admin upload reusable sets of selectable images ("charms"),
-- which customers pick from a visual grid while customising a product.
--
-- 1. charm_sets   — a named, reusable collection (e.g. "Wallet Charms").
-- 2. charms       — the individual selectable images inside a set.
-- 3. product_customization_options.image_set_id — binds an `image_choice`
--    option on a product to a charm set.

CREATE TABLE IF NOT EXISTS `charm_sets` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `charms` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `set_id`       INT UNSIGNED NOT NULL,
  `label`        VARCHAR(120) NOT NULL,
  `image_path`   VARCHAR(255) NOT NULL,
  `extra_charge` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `sort_order`   INT NOT NULL DEFAULT 0,
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_charms_set` (`set_id`),
  CONSTRAINT `fk_charms_set` FOREIGN KEY (`set_id`) REFERENCES `charm_sets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `product_customization_options`
  ADD COLUMN `image_set_id` INT UNSIGNED NULL AFTER `sub_options`;
