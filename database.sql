-- ============================================================
-- GiftDekeDekho — Full Database Schema + Seed Data
-- MySQL 8+ / MariaDB 10.5+
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

CREATE DATABASE IF NOT EXISTS `giftdekedekho` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `giftdekedekho`;

-- ----------------------------
-- Table: users
-- ----------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(120) NOT NULL,
  `email`         VARCHAR(180) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `phone`         VARCHAR(15)  DEFAULT NULL,
  `role`          ENUM('admin','customer') NOT NULL DEFAULT 'customer',
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: addresses
-- ----------------------------
CREATE TABLE IF NOT EXISTS `addresses` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `label`         VARCHAR(60) NOT NULL DEFAULT 'Home',
  `address_line1` VARCHAR(255) NOT NULL,
  `address_line2` VARCHAR(255) DEFAULT NULL,
  `city`          VARCHAR(100) NOT NULL,
  `state`         VARCHAR(100) NOT NULL,
  `pincode`       VARCHAR(10)  NOT NULL,
  `is_default`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_addresses_user` (`user_id`),
  CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: categories
-- ----------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`             VARCHAR(120) NOT NULL,
  `slug`             VARCHAR(140) NOT NULL,
  `parent_id`        INT UNSIGNED DEFAULT NULL,
  `image`            VARCHAR(255) DEFAULT NULL,
  `meta_title`       VARCHAR(255) DEFAULT NULL,
  `meta_description` VARCHAR(500) DEFAULT NULL,
  `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`       INT NOT NULL DEFAULT 0,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  KEY `idx_categories_parent` (`parent_id`),
  CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: products
-- ----------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`       INT UNSIGNED NOT NULL,
  `name`              VARCHAR(255) NOT NULL,
  `slug`              VARCHAR(280) NOT NULL,
  `short_description` VARCHAR(500) DEFAULT NULL,
  `description`       LONGTEXT DEFAULT NULL,
  `base_price`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `sale_price`        DECIMAL(10,2) DEFAULT NULL,
  `stock_qty`         INT NOT NULL DEFAULT 0,
  `sku`               VARCHAR(80) DEFAULT NULL,
  `weight_grams`      INT DEFAULT NULL,
  `is_featured`       TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`         TINYINT(1) NOT NULL DEFAULT 1,
  `meta_title`        VARCHAR(255) DEFAULT NULL,
  `meta_description`  VARCHAR(500) DEFAULT NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_slug` (`slug`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_featured` (`is_featured`),
  KEY `idx_products_active` (`is_active`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: product_images
-- ----------------------------
CREATE TABLE IF NOT EXISTS `product_images` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_primary`  TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_pimg_product` (`product_id`),
  CONSTRAINT `fk_pimg_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: product_customization_options
-- ----------------------------
CREATE TABLE IF NOT EXISTS `product_customization_options` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`   INT UNSIGNED NOT NULL,
  `option_type`  ENUM('text_engraving','photo_upload','gift_wrap','message_card','video_photo') NOT NULL,
  `label`        VARCHAR(120) NOT NULL,
  `is_required`  TINYINT(1) NOT NULL DEFAULT 0,
  `extra_charge` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `char_limit`   INT DEFAULT NULL,
  `sort_order`   INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_pco_product` (`product_id`),
  CONSTRAINT `fk_pco_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: cart
-- ----------------------------
CREATE TABLE IF NOT EXISTS `cart` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id`         VARCHAR(128) NOT NULL,
  `user_id`            INT UNSIGNED DEFAULT NULL,
  `product_id`         INT UNSIGNED NOT NULL,
  `quantity`           INT NOT NULL DEFAULT 1,
  `customization_json` JSON DEFAULT NULL,
  `added_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cart_session` (`session_id`),
  KEY `idx_cart_user` (`user_id`),
  KEY `idx_cart_product` (`product_id`),
  CONSTRAINT `fk_cart_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: coupons
-- ----------------------------
CREATE TABLE IF NOT EXISTS `coupons` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`            VARCHAR(40) NOT NULL,
  `discount_type`   ENUM('flat','percent') NOT NULL DEFAULT 'flat',
  `discount_value`  DECIMAL(10,2) NOT NULL,
  `min_order_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `max_uses`        INT DEFAULT NULL,
  `used_count`      INT NOT NULL DEFAULT 0,
  `valid_from`      DATE NOT NULL,
  `valid_to`        DATE NOT NULL,
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupons_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: orders
-- ----------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`                INT UNSIGNED DEFAULT NULL,
  `guest_email`            VARCHAR(180) DEFAULT NULL,
  `guest_phone`            VARCHAR(15)  DEFAULT NULL,
  `address_snapshot_json`  JSON NOT NULL,
  `subtotal`               DECIMAL(10,2) NOT NULL,
  `discount`               DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `shipping_charge`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total`                  DECIMAL(10,2) NOT NULL,
  `payment_gateway`        ENUM('razorpay','paypal','stripe','cod') NOT NULL DEFAULT 'cod',
  `payment_status`         ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `payment_reference`      VARCHAR(255) DEFAULT NULL,
  `order_status`           ENUM('pending','confirmed','processing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `coupon_id`              INT UNSIGNED DEFAULT NULL,
  `shiprocket_order_id`    VARCHAR(100) DEFAULT NULL,
  `tracking_number`        VARCHAR(100) DEFAULT NULL,
  `tracking_url`           VARCHAR(500) DEFAULT NULL,
  `notes`                  TEXT DEFAULT NULL,
  `created_at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orders_user`           (`user_id`),
  KEY `idx_orders_order_status`   (`order_status`),
  KEY `idx_orders_payment_status` (`payment_status`),
  KEY `idx_orders_created`        (`created_at`),
  CONSTRAINT `fk_orders_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`   (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: order_items
-- ----------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`                INT UNSIGNED NOT NULL,
  `product_id`              INT UNSIGNED DEFAULT NULL,
  `product_name_snapshot`   VARCHAR(255) NOT NULL,
  `product_image_snapshot`  VARCHAR(255) DEFAULT NULL,
  `unit_price`              DECIMAL(10,2) NOT NULL,
  `quantity`                INT NOT NULL DEFAULT 1,
  `customization_json`      JSON DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_oi_order`   (`order_id`),
  KEY `idx_oi_product` (`product_id`),
  CONSTRAINT `fk_oi_order`   FOREIGN KEY (`order_id`)   REFERENCES `orders`   (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: order_video_photos
-- ----------------------------
CREATE TABLE IF NOT EXISTS `order_video_photos` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_item_id`    INT UNSIGNED NOT NULL,
  `admin_video_path` VARCHAR(500) DEFAULT NULL,
  `qr_code_path`     VARCHAR(500) DEFAULT NULL,
  `scan_url`         VARCHAR(500) DEFAULT NULL,
  `token`            CHAR(32) NOT NULL,
  `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ovp_token` (`token`),
  KEY `idx_ovp_order_item` (`order_item_id`),
  CONSTRAINT `fk_ovp_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: reviews
-- ----------------------------
CREATE TABLE IF NOT EXISTS `reviews` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`  INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NOT NULL,
  `rating`      TINYINT NOT NULL DEFAULT 5,
  `title`       VARCHAR(120) DEFAULT NULL,
  `body`        TEXT DEFAULT NULL,
  `is_approved` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reviews_product` (`product_id`),
  KEY `idx_reviews_user`    (`user_id`),
  KEY `idx_reviews_approved` (`is_approved`),
  CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: wishlist
-- ----------------------------
CREATE TABLE IF NOT EXISTS `wishlist` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `added_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wishlist` (`user_id`, `product_id`),
  KEY `idx_wishlist_user`    (`user_id`),
  KEY `idx_wishlist_product` (`product_id`),
  CONSTRAINT `fk_wishlist_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wishlist_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: settings
-- ----------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` LONGTEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: site_sections
-- ----------------------------
CREATE TABLE IF NOT EXISTS `site_sections` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `section_key`  VARCHAR(80) NOT NULL,
  `content_json` JSON DEFAULT NULL,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_site_sections_key` (`section_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: shipping_rules
-- ----------------------------
CREATE TABLE IF NOT EXISTS `shipping_rules` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `label`              VARCHAR(80) NOT NULL,
  `flat_rate`          DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `free_above_amount`  DECIMAL(10,2) DEFAULT NULL,
  `is_active`          TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: pincode_serviceability
-- ----------------------------
CREATE TABLE IF NOT EXISTS `pincode_serviceability` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pincode`         VARCHAR(10) NOT NULL,
  `is_serviceable`  TINYINT(1) NOT NULL DEFAULT 1,
  `estimated_days`  TINYINT UNSIGNED NOT NULL DEFAULT 5,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pincode` (`pincode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: login_attempts
-- ----------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier`  VARCHAR(180) NOT NULL,
  `ip_address`  VARCHAR(45) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_la_identifier` (`identifier`),
  KEY `idx_la_ip` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Admin user (password: Admin@1234)
INSERT INTO `users` (`name`, `email`, `password_hash`, `phone`, `role`) VALUES
('Admin', 'admin@giftdekedekho.com', '$2y$10$WfzpTT8lfFfFtzNkYzMSyOzZSnzSziXU9ToCzO5zEH2LrHq3WP2Mm', '9999999999', 'admin');

-- Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'GiftDekeDekho'),
('site_tagline', 'Personalized Gifts for Every Occasion'),
('site_email', 'hello@giftdekedekho.com'),
('site_phone', '+91 98765 43210'),
('site_address', 'Mumbai, Maharashtra, India'),
('currency_symbol', '₹'),
('currency_code', 'INR'),
('free_shipping_above', '999'),
('default_shipping_charge', '80'),
('low_stock_threshold', '5'),
('razorpay_key_id', ''),
('razorpay_key_secret', ''),
('razorpay_mode', 'test'),
('paypal_client_id', ''),
('paypal_client_secret', ''),
('paypal_mode', 'sandbox'),
('stripe_publishable_key', ''),
('stripe_secret_key', ''),
('stripe_mode', 'test'),
('smtp_host', 'smtp.gmail.com'),
('smtp_port', '587'),
('smtp_user', ''),
('smtp_pass', ''),
('smtp_from_name', 'GiftDekeDekho'),
('smtp_from_email', 'noreply@giftdekedekho.com'),
('msg91_api_key', ''),
('msg91_sender_id', 'GFTDKD'),
('shiprocket_email', ''),
('shiprocket_password', ''),
('primary_color', '#e63946'),
('accent_color', '#457b9d'),
('logo_path', '/images/GDKD logo.png'),
('social_facebook', 'https://facebook.com'),
('social_instagram', 'https://instagram.com'),
('social_twitter', ''),
('social_youtube', ''),
('footer_copyright', '© 2025 GiftDekeDekho. All rights reserved.'),
('whatsapp_number', '919876543210'),
('about_us_text', '<h2>About GiftDekeDekho</h2><p>We specialize in personalized gifts crafted with love for every occasion.</p>'),
('order_confirmed_template', 'Hi {{name}}, your order #{{order_id}} has been confirmed. Total: ₹{{total}}. Thank you for shopping with GiftDekeDekho!'),
('order_shipped_template', 'Hi {{name}}, your order #{{order_id}} has been shipped! Track it here: {{tracking_url}}'),
('order_delivered_template', 'Hi {{name}}, your order #{{order_id}} has been delivered. We hope you loved it!'),
('admin_ip_whitelist', ''),
('max_login_attempts', '5'),
('login_lockout_minutes', '15'),
('meta_title_suffix', ' | GiftDekeDekho'),
('google_analytics_id', '');

-- Site sections
INSERT INTO `site_sections` (`section_key`, `content_json`) VALUES
('hero_banner', '{"headline":"Make Every Moment Memorable","subheadline":"Personalized gifts crafted with love for every occasion","cta_text":"Shop Now","cta_url":"/category/all","image":"/images/ChatGPT Image May 29, 2026, 10_14_46 PM.png","is_active":true}'),
('promo_strip', '{"text":"🎁 Free Shipping on Orders Above ₹999 | Use Code GIFT10 for 10% Off","is_active":true}'),
('featured_products_section', '{"is_active":true,"heading":"Featured Gifts"}'),
('testimonials_section', '{"is_active":true,"heading":"What Our Customers Say","items":[{"name":"Priya S.","text":"Absolutely loved the personalized photo frame! Perfect quality and delivered on time.","rating":5,"avatar":""},{"name":"Rahul M.","text":"The engraved mug was exactly what I wanted. Will order again!","rating":5,"avatar":""},{"name":"Anjali K.","text":"Amazing packaging and the video-photo feature blew my mind!","rating":5,"avatar":""}]}'),
('trust_badges', '{"is_active":true,"items":[{"icon":"🚚","title":"Free Shipping","desc":"On orders above ₹999"},{"icon":"🎁","title":"Gift Wrapped","desc":"Every order beautifully packed"},{"icon":"⭐","title":"Premium Quality","desc":"Handcrafted with love"},{"icon":"🔄","title":"Easy Returns","desc":"7-day hassle-free returns"}]}');

-- Shipping rules
INSERT INTO `shipping_rules` (`label`, `flat_rate`, `free_above_amount`, `is_active`) VALUES
('Standard Delivery', 80.00, 999.00, 1);

-- Categories
INSERT INTO `categories` (`name`, `slug`, `parent_id`, `image`, `meta_title`, `meta_description`, `sort_order`) VALUES
('Birthday Gifts', 'birthday-gifts', NULL, '/images/ChatGPT Image May 29, 2026, 10_15_13 PM.png', 'Birthday Gifts | GiftDekeDekho', 'Unique personalized birthday gifts for everyone', 1),
('Anniversary Gifts', 'anniversary-gifts', NULL, '/images/ChatGPT Image May 29, 2026, 10_15_37 PM.png', 'Anniversary Gifts | GiftDekeDekho', 'Romantic personalized anniversary gift ideas', 2),
('Wedding Gifts', 'wedding-gifts', NULL, '/images/ChatGPT Image May 29, 2026, 10_50_01 PM.png', 'Wedding Gifts | GiftDekeDekho', 'Beautiful personalized wedding gifts', 3),
('Photo Gifts', 'photo-gifts', NULL, '/images/ChatGPT Image May 29, 2026, 10_50_05 PM.png', 'Photo Gifts | GiftDekeDekho', 'Custom photo gifts and printed memories', 4),
('Mugs & Drinkware', 'mugs-drinkware', NULL, '/images/Gemini_Generated_Image_demcoedemcoedemc.png', 'Custom Mugs | GiftDekeDekho', 'Personalised mugs and drinkware', 5),
('Cushions & Pillows', 'cushions-pillows', NULL, '/images/Gemini_Generated_Image_tiy0b6tiy0b6tiy0.png', 'Custom Cushions | GiftDekeDekho', 'Personalised cushions and pillows', 6),
('Keychains & Accessories', 'keychains-accessories', NULL, '/images/Gemini_Generated_Image_gr5cg7gr5cg7gr5c.png', 'Custom Keychains | GiftDekeDekho', 'Personalized keychains and accessories', 7),
('Video Photo Gifts', 'video-photo-gifts', NULL, '/images/Gemini_Generated_Image_e3wgxe3wgxe3wgxe.png', 'Video Photo Gifts | GiftDekeDekho', 'QR-code video photo gifts', 8);

-- Products
INSERT INTO `products` (`category_id`, `name`, `slug`, `short_description`, `description`, `base_price`, `sale_price`, `stock_qty`, `sku`, `weight_grams`, `is_featured`, `is_active`, `meta_title`, `meta_description`) VALUES
(1, 'Personalized Photo Frame', 'personalized-photo-frame', 'Beautiful wooden photo frame with custom text engraving.', '<p>Our personalized photo frame is crafted from premium wood with a natural finish. Add your own message, name, or special date to create a truly unique keepsake. Perfect for birthdays, anniversaries, and special occasions.</p><ul><li>Premium wood construction</li><li>Custom text engraving up to 50 characters</li><li>Available in 4×6 and 5×7 sizes</li><li>Gift-ready packaging</li></ul>', 799.00, 649.00, 50, 'GDD-PF-001', 450, 1, 1, 'Personalized Photo Frame | GiftDekeDekho', 'Buy personalized photo frame with custom text engraving online in India.'),
(5, 'Custom Magic Mug', 'custom-magic-mug', 'Heat-sensitive magic mug that reveals your photo when filled with hot liquid.', '<p>Our custom magic mug is a delightful surprise in every sip! The mug appears black when cold, but when you pour in a hot beverage, your personalized photo and message magically appear. Made from high-quality ceramic.</p><ul><li>11oz ceramic mug</li><li>Heat-sensitive colour-changing effect</li><li>Custom photo print</li><li>Dishwasher safe (hand wash recommended)</li></ul>', 549.00, 449.00, 75, 'GDD-MM-001', 380, 1, 1, 'Custom Magic Mug | GiftDekeDekho', 'Buy custom magic mug with your photo online in India.'),
(6, 'Personalized Heart Cushion', 'personalized-heart-cushion', 'Soft heart-shaped cushion with your custom photo print.', '<p>Express your love with our heart-shaped personalized cushion. Premium quality soft velvet cover with vibrant photo printing. Makes the perfect gift for Valentine''s Day, anniversaries, and birthdays.</p><ul><li>Heart shaped 12 inch</li><li>Soft velvet fabric</li><li>Vibrant photo printing</li><li>Includes cushion filler</li></ul>', 699.00, 549.00, 40, 'GDD-CU-001', 350, 1, 1, 'Personalized Heart Cushion | GiftDekeDekho', 'Buy personalized heart cushion with photo print online in India.'),
(8, 'Video-Photo QR Surprise Frame', 'video-photo-qr-surprise-frame', 'A printed photo with a hidden QR code that plays your personal video!', '<p>The most magical gift ever! A beautiful printed photo that contains a hidden QR code. When your loved one scans it with their phone camera, it instantly plays your personal video message. Perfect for birthdays, anniversaries, and any special occasion.</p><ul><li>Premium quality print</li><li>Hidden QR code technology</li><li>Works with any phone camera (no app needed)</li><li>Your personal video plays instantly</li><li>Available in A4 and 5×7 sizes</li></ul>', 1299.00, 999.00, 100, 'GDD-VP-001', 200, 1, 1, 'Video-Photo QR Surprise Frame | GiftDekeDekho', 'Buy video photo QR code frame with personal video message online in India.'),
(7, 'Engraved Metal Keychain', 'engraved-metal-keychain', 'Premium stainless steel keychain with custom text engraving.', '<p>Our engraved metal keychain is a timeless gift that they will carry every day. Made from premium stainless steel with laser engraving that lasts a lifetime. Available in multiple shapes.</p><ul><li>Premium stainless steel</li><li>Laser engraving</li><li>Multiple shapes available</li><li>Tarnish resistant</li></ul>', 349.00, 299.00, 100, 'GDD-KC-001', 50, 1, 1, 'Engraved Metal Keychain | GiftDekeDekho', 'Buy engraved metal keychain with custom text online in India.'),
(4, 'Photo Collage Canvas Print', 'photo-collage-canvas-print', 'Create a beautiful collage of your favourite memories on a premium canvas.', '<p>Turn your favourite photos into a stunning wall art piece. Our photo collage canvas print is printed on premium canvas with a solid wood frame. UV-resistant inks ensure your memories last for decades.</p><ul><li>Premium canvas material</li><li>Solid wood frame</li><li>UV-resistant inks</li><li>Ready to hang</li><li>Multiple collage layouts</li></ul>', 1499.00, 1199.00, 30, 'GDD-CC-001', 800, 1, 1, 'Photo Collage Canvas Print | GiftDekeDekho', 'Buy custom photo collage canvas print online in India.'),
(2, 'Personalized Couple Mug Set', 'personalized-couple-mug-set', 'His and Hers matching mug set with custom names and message.', '<p>Celebrate your love story with our matching couple mug set. Each mug is custom printed with names, dates, or a special message. Perfect anniversary or wedding gift for the couple in your life.</p><ul><li>Set of 2 ceramic mugs (11oz each)</li><li>Custom names and message printing</li><li>Microwave and dishwasher safe</li><li>Gift box included</li></ul>', 999.00, 799.00, 35, 'GDD-CM-001', 760, 1, 1, 'Personalized Couple Mug Set | GiftDekeDekho', 'Buy personalized couple mug set with custom names online in India.'),
(3, 'Personalized Wedding Tray', 'personalized-wedding-tray', 'Elegant wooden serving tray with names and wedding date engraving.', '<p>A sophisticated and functional wedding gift that the couple will treasure forever. Our personalized wooden serving tray features beautiful laser engraving of the couple''s names, wedding date, and your heartfelt message.</p><ul><li>Premium acacia wood</li><li>Laser engraved names and date</li><li>With handles for easy carrying</li><li>Food safe finish</li></ul>', 1899.00, 1499.00, 20, 'GDD-WT-001', 1200, 0, 1, 'Personalized Wedding Tray | GiftDekeDekho', 'Buy personalized wedding tray with engraving online in India.');

-- Product images
INSERT INTO `product_images` (`product_id`, `image_path`, `sort_order`, `is_primary`) VALUES
(1, '/images/Giftway-Birthday-Photo-Frame-Customised-Gift-Personalized-Image-Quotes-wise-Gifts-for-Birthday-Friend-Sister-Boyfriend-Girlfriend-Husband-Wife-A48x11-1-scaled.jpg', 0, 1),
(1, '/images/g7pDQUMpLVw29GFVaHAzURNUxapsVdrc2MHQuelK.jpg', 1, 0),
(2, '/images/Gemini_Generated_Image_demcoedemcoedemc.png', 0, 1),
(2, '/images/cuboid008_960x.jpg', 1, 0),
(3, '/images/Gemini_Generated_Image_tiy0b6tiy0b6tiy0.png', 0, 1),
(4, '/images/ChatGPT Image May 29, 2026, 10_14_58 PM.png', 0, 1),
(4, '/images/ChatGPT Image May 29, 2026, 10_14_46 PM.png', 1, 0),
(5, '/images/Gemini_Generated_Image_gr5cg7gr5cg7gr5c.png', 0, 1),
(6, '/images/Gemini_Generated_Image_e3wgxe3wgxe3wgxe.png', 0, 1),
(7, '/images/ChatGPT Image May 29, 2026, 10_50_01 PM.png', 0, 1),
(8, '/images/ChatGPT Image May 29, 2026, 10_50_05 PM.png', 0, 1);

-- Customization options
INSERT INTO `product_customization_options` (`product_id`, `option_type`, `label`, `is_required`, `extra_charge`, `char_limit`, `sort_order`) VALUES
(1, 'text_engraving', 'Custom Message (max 50 chars)', 1, 0.00, 50, 1),
(1, 'gift_wrap', 'Premium Gift Wrapping', 0, 50.00, NULL, 2),
(1, 'message_card', 'Personal Message Card', 0, 0.00, NULL, 3),
(2, 'photo_upload', 'Upload Your Photo', 1, 0.00, NULL, 1),
(2, 'message_card', 'Message to Print on Mug', 0, 0.00, NULL, 2),
(2, 'gift_wrap', 'Premium Gift Wrapping', 0, 50.00, NULL, 3),
(3, 'photo_upload', 'Upload Your Photo', 1, 0.00, NULL, 1),
(3, 'message_card', 'Custom Message', 0, 0.00, NULL, 2),
(3, 'gift_wrap', 'Premium Gift Wrapping', 0, 50.00, NULL, 3),
(4, 'photo_upload', 'Upload the Photo to Print', 1, 0.00, NULL, 1),
(4, 'video_photo', 'Add Video-Photo QR Code Feature', 0, 299.00, NULL, 2),
(4, 'gift_wrap', 'Premium Gift Wrapping', 0, 50.00, NULL, 3),
(5, 'text_engraving', 'Text to Engrave (max 30 chars)', 1, 0.00, 30, 1),
(5, 'gift_wrap', 'Gift Wrapping', 0, 30.00, NULL, 2),
(6, 'photo_upload', 'Upload Photos for Collage', 1, 0.00, NULL, 1),
(6, 'message_card', 'Personal Note', 0, 0.00, NULL, 2),
(7, 'photo_upload', 'Upload Your Photo', 1, 0.00, NULL, 1),
(7, 'text_engraving', 'Name / Message (max 40 chars)', 0, 0.00, 40, 2),
(7, 'gift_wrap', 'Premium Gift Wrapping', 0, 50.00, NULL, 3),
(8, 'text_engraving', 'Names / Date to Engrave (max 40 chars)', 1, 0.00, 40, 1),
(8, 'gift_wrap', 'Premium Gift Wrapping', 0, 50.00, NULL, 2),
(8, 'message_card', 'Wedding Message Card', 0, 0.00, NULL, 3);

-- Coupons
INSERT INTO `coupons` (`code`, `discount_type`, `discount_value`, `min_order_value`, `max_uses`, `valid_from`, `valid_to`, `is_active`) VALUES
('GIFT10', 'percent', 10.00, 500.00, 1000, '2025-01-01', '2026-12-31', 1),
('SAVE100', 'flat', 100.00, 999.00, 500, '2025-01-01', '2026-12-31', 1),
('NEWUSER', 'percent', 15.00, 299.00, NULL, '2025-01-01', '2026-12-31', 1);

-- Sample pincode data
INSERT INTO `pincode_serviceability` (`pincode`, `is_serviceable`, `estimated_days`) VALUES
('400001', 1, 3), ('400002', 1, 3), ('400003', 1, 3), ('400004', 1, 3),
('110001', 1, 4), ('110002', 1, 4), ('110003', 1, 4),
('560001', 1, 5), ('560002', 1, 5),
('600001', 1, 5), ('600002', 1, 5),
('700001', 1, 6), ('700002', 1, 6),
('500001', 1, 5), ('500002', 1, 5),
('411001', 1, 4), ('411002', 1, 4),
('380001', 1, 4), ('380002', 1, 4),
('302001', 1, 5), ('302002', 1, 5),
('226001', 1, 6), ('226002', 1, 6),
('800001', 1, 6), ('800002', 1, 6),
('999999', 0, 0);
