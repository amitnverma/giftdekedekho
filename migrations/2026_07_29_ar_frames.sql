-- Migration: "Living Photo" AR video frames
-- Run once against the production database.
--
-- A customer gives us a photo plus a video. We print the photo into a physical
-- frame; pointing a phone camera at that print plays the video, using MindAR
-- image-target tracking. No QR code or visible marking is involved — the photo
-- itself is the trigger.
--
-- Deliberately NOT hard-linked to an order: walk-in counter sales have no order
-- row at all (payment is cash/card, handled outside this system), so
-- `order_item_id` is nullable and `channel` records where the frame came from.
-- ON DELETE SET NULL rather than CASCADE so removing an old order never breaks
-- a frame a customer already took home.
--
-- Statuses: online frames end at 'shipped', in-store frames at 'handed_over'.
-- `verified_at` is the live-scan test — for in-store sales this must be set
-- before the frame is handed over, which is the whole point of testing at the
-- counter instead of discovering a dead frame days later.

CREATE TABLE IF NOT EXISTS `ar_frames` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`                VARCHAR(32)  NOT NULL,               -- public scan URL, e.g. 'gdd-8f3k2p'
  `channel`             ENUM('online','in_store') NOT NULL DEFAULT 'online',
  `order_item_id`       INT UNSIGNED DEFAULT NULL,           -- online sales only; NULL for walk-ins
  `customer_name`       VARCHAR(120) DEFAULT NULL,           -- walk-in reference (no order row to read from)
  `customer_phone`      VARCHAR(15)  DEFAULT NULL,
  `photo_path`          VARCHAR(500) NOT NULL,               -- relative to public/uploads
  `target_path`         VARCHAR(500) DEFAULT NULL,           -- generated .mind file
  `video_type`          ENUM('youtube','upload') NOT NULL DEFAULT 'youtube',
  `video_url`           VARCHAR(500) DEFAULT NULL,           -- YouTube watch URL
  `video_path`          VARCHAR(500) DEFAULT NULL,           -- uploaded file, relative to public/uploads
  `playback_mode`       ENUM('fullscreen','overlay') NOT NULL DEFAULT 'fullscreen',
  `trackability_score`  SMALLINT UNSIGNED DEFAULT NULL,      -- 0-100, from the compiler's feature counts
  `trackability_flag`   ENUM('poor','fair','good') DEFAULT NULL,
  `trackability_json`   JSON DEFAULT NULL,                   -- raw feature counts, dimensions, compiler version
  `verified_at`         DATETIME DEFAULT NULL,               -- live camera test passed
  `status`              ENUM('pending_setup','target_generated','verified','printed','shipped','handed_over')
                        NOT NULL DEFAULT 'pending_setup',
  `is_active`           TINYINT(1) NOT NULL DEFAULT 1,       -- kill switch for the public scan page
  `created_by`          INT UNSIGNED DEFAULT NULL,           -- admin who created it (useful for counter sales)
  `notes`               TEXT DEFAULT NULL,
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_arf_slug` (`slug`),
  KEY `idx_arf_order_item` (`order_item_id`),
  KEY `idx_arf_status`     (`status`),
  KEY `idx_arf_channel`    (`channel`),
  KEY `idx_arf_created`    (`created_at`),
  CONSTRAINT `fk_arf_order_item` FOREIGN KEY (`order_item_id`)
    REFERENCES `order_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_arf_created_by` FOREIGN KEY (`created_by`)
    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
