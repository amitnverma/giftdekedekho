PHPMailer library (used to send transactional emails over SMTP).

Download the source from https://github.com/PHPMailer/PHPMailer and copy
the `src/` directory into this folder, so app/services/NotificationService.php
can require:

    libs/PHPMailer/src/Exception.php
    libs/PHPMailer/src/PHPMailer.php
    libs/PHPMailer/src/SMTP.php

(If you install via Composer instead, update the require_once paths in
NotificationService::sendEmail() to point at vendor/phpmailer/phpmailer/src/.)

If PHPMailer isn't available, NotificationService automatically falls back
to PHP's native mail() function — so emails keep sending either way,
though SMTP delivery is strongly recommended for production.
