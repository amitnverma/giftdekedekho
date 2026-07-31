-- Migration: accept video sources other than YouTube
-- Run once against the production database.
--
-- YouTube embeds are refused in some contexts (player error 153 — the player
-- rejecting the origin it was loaded from), and a shop should not be locked to
-- one provider anyway. Adds Vimeo and direct video-file URLs alongside the
-- existing YouTube and uploaded-file options.
--
-- Existing rows are unaffected: 'youtube' and 'upload' keep their meaning and
-- the default is unchanged.

ALTER TABLE `ar_frames`
  MODIFY `video_type` ENUM('youtube','vimeo','direct','upload') NOT NULL DEFAULT 'youtube';
