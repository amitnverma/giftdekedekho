# GiftDekeDekho

A complete e-commerce platform for personalized/customized gifts (Indian market,
₹ INR), built with vanilla PHP 8+ and MySQL using a hand-rolled MVC architecture
(no frameworks). Includes a full customer storefront, an admin panel, payment
gateway integrations, Shiprocket shipping, email/SMS notifications, and an
end-to-end "Video & Photo QR" gifting feature.

## Requirements

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Apache with `mod_rewrite` and `mod_headers` (XAMPP works out of the box)
- A `php.ini` with `pdo_mysql`, `fileinfo`, `gd`, and `curl` extensions enabled

## Installation

1. **Place the project** in your web server document root, e.g.
   `/Applications/XAMPP/xamppfiles/htdocs/giftdekedekho`.

2. **Create the database and import the schema**:

   ```sql
   CREATE DATABASE giftdekedekho CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

   ```bash
   mysql -u root -p giftdekedekho < database.sql
   ```

   This creates all tables and seeds default settings, categories, and an
   admin account.

3. **Configure the database connection** in [config/database.php](config/database.php)
   — either edit the defaults directly or set environment variables:
   `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.

4. **Configure site basics** in [config/config.php](config/config.php) — either
   edit the constants directly or set environment variables:
   - `APP_ENV` — `development` or `production`
   - `APP_URL` — full base URL, e.g. `http://localhost/giftdekedekho`
   - Third-party credentials (`RAZORPAY_KEY_ID`, `PAYPAL_CLIENT_ID`,
     `STRIPE_SECRET_KEY`, `SHIPROCKET_EMAIL`, `MSG91_API_KEY`, `SMTP_HOST`, etc.)
     act only as first-install fallbacks — the **admin panel Settings pages
     are the source of truth** and store live values in the `settings` table.

5. **Make `public/uploads/` writable** by the web server user (product images,
   category images, customization uploads, branding/section images, QR codes,
   etc. are all written there):

   ```bash
   chmod -R 775 public/uploads
   ```

6. **Visit the site** at `http://localhost/giftdekedekho/` and log in to the
   admin panel at `http://localhost/giftdekedekho/admin/login`.

   **Default admin credentials** (seeded by `database.sql` — change the
   password immediately after first login):
   - Email: `admin@giftdekedekho.com`
   - Password: `Admin@1234`

## Folder structure

```
app/
  controllers/   Front-end and admin controllers (front-controller routing)
  models/        PDO-backed data models
  services/      Payment, shipping (Shiprocket), notification (email/SMS), QR services
  views/         Storefront and admin view templates
config/          Database connection + site configuration constants
public/          CSS, JS, and uploaded media (web-accessible)
libs/            Optional vendor libraries (FPDF, phpqrcode, PHPMailer) — see below
index.php        Front controller / router entry point
sitemap.php      Dynamic XML sitemap (linked from robots.txt)
robots.txt       Crawler rules
.htaccess        Clean URLs, security headers, sensitive-path protection
database.sql     Full schema + seed data
```

## Configuring integrations (via Admin → Settings)

All of the following are configured live from the admin panel — no code edits
or redeploys needed:

- **Payments** (`Settings → Payments`): Razorpay, PayPal, and Stripe API keys
  with sandbox/live mode toggles, plus enabling/disabling Cash on Delivery.
- **Shipping** (`Settings → Shipping` / `Shipping Rules`): flat-rate and
  free-shipping-above-amount rules, plus a serviceable-pincode list (with CSV
  bulk upload) and Shiprocket account credentials for label/tracking sync.
- **Notifications** (`Settings → Notifications`): SMTP credentials for
  transactional email and an MSG91 API key for SMS alerts, with editable
  message templates.
- **Design** (`Design Studio`): branding (logo, colors, tagline), homepage
  hero banner, promo strip, featured-products section, trust badges,
  testimonials, footer/social links, and the About Us page — all stored as
  JSON in the `site_sections` table and rendered live on the storefront.

## Optional vendor libraries

These three libraries are **optional** — the platform works without them by
falling back to a simpler built-in implementation, but installing them
improves the experience:

| Library    | Used for                  | Install location                          | Fallback if missing                     |
|------------|---------------------------|--------------------------------------------|------------------------------------------|
| FPDF       | PDF order invoices        | `libs/fpdf/fpdf.php`                       | Printable HTML invoice                   |
| phpqrcode  | Video-Photo QR generation | `libs/phpqrcode/qrlib.php`                 | Public QR Server image API               |
| PHPMailer  | SMTP email delivery       | `libs/PHPMailer/src/{PHPMailer,SMTP,Exception}.php` | PHP's native `mail()` function   |

See the `README.txt` inside each `libs/<name>/` folder for exact download and
placement instructions.

## The Video & Photo QR feature

Customers can choose a "Video & Photo Message" customization option on
eligible products. After the order ships, an admin uploads the personalized
video/photo from the order detail page; the system generates a unique,
unguessable token and a QR code linking to `/watch/{token}` — a public page
where the recipient can view the message by scanning the code on the gift's
packaging.

## Security notes

- All database access uses PDO prepared statements.
- Passwords are hashed with `password_hash()` / verified with `password_verify()`.
- All state-changing forms are protected by CSRF tokens
  (`csrfField()` / `verifyCsrf()`).
- Admin logins are protected by IP allow-listing (optional, via
  `admin_ip_whitelist` setting) and rate limiting / lockout
  (`max_login_attempts`, `login_lockout_minutes` settings).
- Admin sessions auto-expire after `SESSION_TIMEOUT_SECONDS` of inactivity.
- `.htaccess` blocks direct browser access to `config/`, `app/`, and `libs/`,
  denies dotfiles/`.sql`/`.md`/`.log`, disables directory listing, and sets
  standard security response headers.
- User-supplied output is escaped via the `e()` helper to prevent XSS.

## SEO

- Clean URLs via `mod_rewrite` (`.htaccess` routes everything through
  `index.php`).
- `sitemap.php` generates a live XML sitemap of active categories and
  products, referenced from `robots.txt`.
- Per-page meta titles/descriptions are editable from the admin
  (`Settings → General` and per-product/category SEO fields).
