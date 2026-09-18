-- Migration: public partner registration
-- Run once against the production database, after 2026_09_16_ar_partners.sql:
--   php tools/run-migration.php migrations/2026_09_17_partner_signups.sql
--
-- A shop can now register itself at /partner/register. It is created paused
-- (is_active = 0) with signup_status 'pending', plus a pending credit request
-- for the pack it chose. Payment is taken outside the site; the admin then
-- activates it, which adds the pack's credits.
--
-- NULL means the partner was created in the admin, as before. Until this runs,
-- the registration page says registration is not open yet.
--
-- Not re-runnable: ADD COLUMN fails if the column already exists.

ALTER TABLE `ar_partners`
  ADD COLUMN `signup_status` ENUM('pending','approved','rejected') DEFAULT NULL AFTER `is_active`;
