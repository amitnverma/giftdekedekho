-- Migration: stop repeat free trials
-- Run once against the production database, after 2026_09_23_partner_trials.sql:
--   php tools/run-migration.php migrations/2026_09_24_partner_trial_guard.sql
--
-- Records the network address a free trial was started from, so the same
-- connection cannot open trial after trial with new emails. The other checks
-- (same mobile number, same email in another form, same browser) need no
-- schema. Until this runs, those still apply and only the address check is off.
--
-- Not re-runnable: ADD COLUMN fails if the column already exists.

ALTER TABLE `ar_partners`
  ADD COLUMN `trial_ip` VARCHAR(45) DEFAULT NULL AFTER `is_trial`,  -- set only when registration granted a trial
  ADD KEY `idx_arp_trial_ip` (`trial_ip`, `created_at`);
