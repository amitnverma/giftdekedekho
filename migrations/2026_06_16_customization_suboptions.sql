-- Migration: flexible product customization options
-- Run once against the production database.
--
-- 1. Allow arbitrary option types (was a fixed ENUM).
-- 2. Add sub_options to store selectable choices as JSON
--    (each: {"label":"Gold","price":20,"image":true}).

ALTER TABLE `product_customization_options`
  MODIFY `option_type` VARCHAR(80) NOT NULL,
  ADD COLUMN `sub_options` TEXT NULL AFTER `char_limit`;
