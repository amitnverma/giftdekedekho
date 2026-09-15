-- Migration: several photos and videos behind one Living Photo QR code
-- Run once against the production database:
--   php tools/run-migration.php migrations/2026_09_14_ar_frame_items.sql
--
-- A frame used to be exactly one photo that played exactly one video. A gift can
-- now hold several: one sticker opens the camera, and each photo the recipient
-- points it at plays its own video.
--
-- The frame keeps everything that belongs to the gift as a whole — the slug on
-- the QR sticker, channel, customer, status. Each photo/video pair moves to
-- `ar_frame_items`, with its own compiled target, trackability and live test.
--
-- `ar_frames.target_path` becomes the file the scan page loads: the single
-- item's own target when there is one photo, or a bundle of every item's target
-- when there are several. `target_items` records which item sits at each index
-- of that file, because the index is all the browser reports on a match.
--
-- Existing frames are copied into one item each, pointing at the files they
-- already have, so nothing is recompiled and no printed sticker changes. The
-- old per-photo columns on ar_frames are left in place, unread, as a fallback.
--
-- Order matters for re-runs: ADD COLUMN is the only statement that fails a
-- second time, so everything before it is safe to repeat.

CREATE TABLE IF NOT EXISTS `ar_frame_items` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `frame_id`            INT UNSIGNED NOT NULL,
  `sort_order`          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `photo_path`          VARCHAR(500) NOT NULL,               -- relative to public/uploads
  `target_path`         VARCHAR(500) DEFAULT NULL,           -- this photo's own compiled .mind
  `video_type`          ENUM('youtube','vimeo','direct','upload') NOT NULL DEFAULT 'youtube',
  `video_url`           VARCHAR(500) DEFAULT NULL,
  `video_path`          VARCHAR(500) DEFAULT NULL,
  `playback_mode`       ENUM('fullscreen','overlay') NOT NULL DEFAULT 'fullscreen',
  `trackability_score`  SMALLINT UNSIGNED DEFAULT NULL,
  `trackability_flag`   ENUM('poor','fair','good') DEFAULT NULL,
  `trackability_json`   JSON DEFAULT NULL,
  `verified_at`         DATETIME DEFAULT NULL,               -- this photo passed the live scan test
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_arfi_frame` (`frame_id`, `sort_order`),
  CONSTRAINT `fk_arfi_frame` FOREIGN KEY (`frame_id`)
    REFERENCES `ar_frames` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- New frames no longer carry a photo of their own.
ALTER TABLE `ar_frames`
  MODIFY `photo_path` VARCHAR(500) DEFAULT NULL;

INSERT INTO `ar_frame_items`
  (`frame_id`, `sort_order`, `photo_path`, `target_path`, `video_type`, `video_url`, `video_path`,
   `playback_mode`, `trackability_score`, `trackability_flag`, `trackability_json`, `verified_at`, `created_at`)
SELECT f.`id`, 0, f.`photo_path`, f.`target_path`, f.`video_type`, f.`video_url`, f.`video_path`,
       f.`playback_mode`, f.`trackability_score`, f.`trackability_flag`, f.`trackability_json`, f.`verified_at`, f.`created_at`
FROM `ar_frames` f
WHERE f.`photo_path` IS NOT NULL AND f.`photo_path` <> ''
  AND NOT EXISTS (SELECT 1 FROM `ar_frame_items` i WHERE i.`frame_id` = f.`id`);

ALTER TABLE `ar_frames`
  ADD COLUMN `target_items` JSON DEFAULT NULL AFTER `target_path`;

-- A copied frame's target is its one item's target, at index 0.
UPDATE `ar_frames` f
JOIN `ar_frame_items` i ON i.`frame_id` = f.`id` AND i.`target_path` = f.`target_path`
SET f.`target_items` = CONCAT('[', i.`id`, ']')
WHERE f.`target_items` IS NULL;
