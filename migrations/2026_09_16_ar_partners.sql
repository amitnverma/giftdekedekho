-- Migration: B2B AR partners
-- Run once against the production database, after 2026_09_14_ar_frame_items.sql:
--   php tools/run-migration.php migrations/2026_09_16_ar_partners.sql
--
-- A partner is a shop that sells Living Photo AR to its own customers through
-- its own branded portal at /partner/{slug}. It creates the same ar_frames and
-- ar_frame_items rows the GiftDekeDekho queue does — so the compiler, the QR
-- sticker and the public /scan/{slug} page are shared — but everything a partner
-- makes is tagged with partner_id and kept out of GiftDekeDekho's own queue and
-- scan-anything bundle.
--
-- Partners prepay credits. The admin tops them up by hand after payment is
-- collected; every change to a balance is a row in ar_partner_credit_ledger, and
-- ar_partners.credit_balance is only ever changed in the same transaction.
--
-- Partner logins live in their own table rather than `users`, so a partner can
-- never sign in to the storefront or the admin, and never shows up as a
-- GiftDekeDekho customer.
--
-- Order matters for re-runs: CREATE TABLE IF NOT EXISTS is safe to repeat; the
-- ALTER TABLE ... ADD COLUMN statements at the end are not, so they come last.

CREATE TABLE IF NOT EXISTS `ar_partners` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`              VARCHAR(60)  NOT NULL,               -- portal URL: /partner/{slug}
  `name`              VARCHAR(120) NOT NULL,
  `tagline`           VARCHAR(200) DEFAULT NULL,
  `logo_path`         VARCHAR(500) DEFAULT NULL,           -- relative to public/uploads
  `brand_color`       CHAR(7)      NOT NULL DEFAULT '#e63946',
  `contact_name`      VARCHAR(120) DEFAULT NULL,
  `contact_phone`     VARCHAR(20)  DEFAULT NULL,
  `whatsapp`          VARCHAR(20)  DEFAULT NULL,
  `contact_email`     VARCHAR(180) DEFAULT NULL,
  `website_url`       VARCHAR(300) DEFAULT NULL,
  `allow_singles`     TINYINT(1)   NOT NULL DEFAULT 1,
  `allow_albums`      TINYINT(1)   NOT NULL DEFAULT 1,
  `max_album_pages`   TINYINT UNSIGNED NOT NULL DEFAULT 8,
  `max_video_mb`      SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  `base_credits`      INT UNSIGNED NOT NULL DEFAULT 99,    -- per AR item (a single, or one album page)
  `duration_prices`   JSON DEFAULT NULL,                   -- {"15":0,"30":84,...}; absent key = not offered
  `validity_prices`   JSON DEFAULT NULL,                   -- {"1y":0,"5y":198,"10y":297,"lifetime":495}
  `credit_packs`      JSON DEFAULT NULL,                   -- [{"price":1000,"credits":1100},...]
  `edit_window_days`  SMALLINT UNSIGNED NOT NULL DEFAULT 7,
  `credit_balance`    INT NOT NULL DEFAULT 0,              -- changed only alongside a ledger row
  `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,     -- portal access; customers' AR keeps working
  `notes`             TEXT DEFAULT NULL,                   -- admin-only
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_arp_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ar_partner_users` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `partner_id`      INT UNSIGNED NOT NULL,
  `name`            VARCHAR(120) NOT NULL,
  `email`           VARCHAR(180) NOT NULL,
  `password_hash`   VARCHAR(255) NOT NULL,
  `role`            ENUM('owner','editor') NOT NULL DEFAULT 'owner',
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at`   DATETIME DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_arpu_email` (`email`),
  KEY `idx_arpu_partner` (`partner_id`),
  CONSTRAINT `fk_arpu_partner` FOREIGN KEY (`partner_id`)
    REFERENCES `ar_partners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The partner's own customers. Never linked to GiftDekeDekho's `users`.
CREATE TABLE IF NOT EXISTS `ar_partner_customers` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `partner_id`    INT UNSIGNED NOT NULL,
  `name`          VARCHAR(120) NOT NULL,
  `phone`         VARCHAR(20)  DEFAULT NULL,
  `email`         VARCHAR(180) DEFAULT NULL,
  `reference`     VARCHAR(80)  DEFAULT NULL,               -- the partner's own order / bill number
  `notes`         TEXT DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_arpc_partner` (`partner_id`, `created_at`),
  CONSTRAINT `fk_arpc_partner` FOREIGN KEY (`partner_id`)
    REFERENCES `ar_partners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every credit movement. amount is signed; balance_after is the partner's
-- balance once this row applied, so the history reads without recomputing.
CREATE TABLE IF NOT EXISTS `ar_partner_credit_ledger` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `partner_id`      INT UNSIGNED NOT NULL,
  `type`            ENUM('added','used','refunded','adjusted') NOT NULL,
  `amount`          INT NOT NULL,
  `balance_after`   INT NOT NULL,
  `description`     VARCHAR(255) NOT NULL,
  `frame_id`        INT UNSIGNED DEFAULT NULL,
  `request_id`      INT UNSIGNED DEFAULT NULL,
  `created_by`      INT UNSIGNED DEFAULT NULL,             -- admin user, or NULL when the partner spent it
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_arpl_partner` (`partner_id`, `id`),
  CONSTRAINT `fk_arpl_partner` FOREIGN KEY (`partner_id`)
    REFERENCES `ar_partners` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_arpl_frame` FOREIGN KEY (`frame_id`)
    REFERENCES `ar_frames` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Buy credits" from the portal. Payment happens outside the site; the admin
-- marks the request paid, which adds the credits.
CREATE TABLE IF NOT EXISTS `ar_partner_credit_requests` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `partner_id`      INT UNSIGNED NOT NULL,
  `price`           INT UNSIGNED NOT NULL,                 -- rupees, as offered at request time
  `credits`         INT UNSIGNED NOT NULL,
  `status`          ENUM('pending','fulfilled','cancelled') NOT NULL DEFAULT 'pending',
  `requested_by`    INT UNSIGNED DEFAULT NULL,             -- ar_partner_users.id
  `handled_by`      INT UNSIGNED DEFAULT NULL,             -- admin users.id
  `handled_at`      DATETIME DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_arpr_status` (`status`, `created_at`),
  KEY `idx_arpr_partner` (`partner_id`),
  CONSTRAINT `fk_arpr_partner` FOREIGN KEY (`partner_id`)
    REFERENCES `ar_partners` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per opening of a public scan page. visitor is a keyed hash of the
-- IP and browser, so unique visitors can be counted without storing either.
CREATE TABLE IF NOT EXISTS `ar_scan_events` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `frame_id`      INT UNSIGNED NOT NULL,
  `partner_id`    INT UNSIGNED DEFAULT NULL,
  `visitor`       CHAR(32) NOT NULL,
  `created_at`    DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_arse_frame` (`frame_id`, `created_at`),
  KEY `idx_arse_partner` (`partner_id`, `created_at`),
  CONSTRAINT `fk_arse_frame` FOREIGN KEY (`frame_id`)
    REFERENCES `ar_frames` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `ar_frames`
  MODIFY `channel` ENUM('online','in_store','partner') NOT NULL DEFAULT 'online';

-- Not re-runnable from here on.
ALTER TABLE `ar_frames`
  ADD COLUMN `partner_id`          INT UNSIGNED DEFAULT NULL AFTER `channel`,
  ADD COLUMN `partner_customer_id` INT UNSIGNED DEFAULT NULL AFTER `partner_id`,
  ADD COLUMN `title`               VARCHAR(160) DEFAULT NULL AFTER `partner_customer_id`,
  ADD COLUMN `content_kind`        ENUM('single','album') DEFAULT NULL AFTER `title`,
  ADD COLUMN `validity`            VARCHAR(10)  DEFAULT NULL AFTER `content_kind`,
  ADD COLUMN `active_until`        DATETIME     DEFAULT NULL AFTER `validity`,  -- NULL = no expiry
  ADD COLUMN `editable_until`      DATETIME     DEFAULT NULL AFTER `active_until`,
  ADD COLUMN `credits_charged`     INT UNSIGNED DEFAULT NULL AFTER `editable_until`,
  ADD KEY `idx_arf_partner` (`partner_id`, `created_at`),
  ADD CONSTRAINT `fk_arf_partner` FOREIGN KEY (`partner_id`)
    REFERENCES `ar_partners` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_arf_partner_customer` FOREIGN KEY (`partner_customer_id`)
    REFERENCES `ar_partner_customers` (`id`) ON DELETE SET NULL;

ALTER TABLE `ar_frame_items`
  ADD COLUMN `title`       VARCHAR(120) DEFAULT NULL AFTER `sort_order`,
  ADD COLUMN `max_seconds` SMALLINT UNSIGNED DEFAULT NULL AFTER `playback_mode`;  -- playback stops here
