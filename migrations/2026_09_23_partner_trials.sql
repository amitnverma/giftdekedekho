-- Migration: free DEx trial for partners
-- Run once against the production database, after 2026_09_17_partner_signups.sql:
--   php tools/run-migration.php migrations/2026_09_23_partner_trials.sql
--
-- A shop that registers at /partner/register now starts on a free trial
-- straight away: it is active, signed in, and given trial credits (300 by
-- default, set in Admin → AR Partners). The admin can also create a trial
-- account by hand.
--
-- Everything a trial account creates is deleted automatically a few days
-- after it is made (3 by default): ar_frames.delete_after holds that moment,
-- and the content stops scanning then even if the clean-up has not run yet.
-- The trial ends when the admin marks a credit pack paid, or presses
-- "End trial"; content made afterwards is kept as normal.
--
-- Until this runs, registration keeps the old "pay, then we activate" flow.
--
-- Not re-runnable: ADD COLUMN fails if the column already exists.

ALTER TABLE `ar_partners`
  ADD COLUMN `is_trial` TINYINT(1) NOT NULL DEFAULT 0 AFTER `signup_status`;

ALTER TABLE `ar_frames`
  ADD COLUMN `delete_after` DATETIME DEFAULT NULL AFTER `active_until`,  -- trial content: removed at this time
  ADD KEY `idx_arf_delete_after` (`delete_after`);
