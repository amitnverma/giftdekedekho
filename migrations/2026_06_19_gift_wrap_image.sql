-- Migration: per-option image (used by the Gift Wrap option's preview thumbnail)
-- Run once against the production database.
--
-- Stores an admin-uploaded image path for a customization option. Currently
-- used by the "gift_wrap" type so the gift-wrapping preview shown to customers
-- can be changed from the admin product form. NULL falls back to a default
-- gift-box image in the storefront.

ALTER TABLE `product_customization_options`
  ADD COLUMN `image_path` VARCHAR(255) NULL AFTER `image_set_id`;
