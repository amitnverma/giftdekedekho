PHPMailer 7.1.1 (https://github.com/PHPMailer/PHPMailer, tag v7.1.1), used by
app/services/NotificationService.php to send email over SMTP.

Only the three files the app needs are committed, under src/:

    src/Exception.php
    src/PHPMailer.php
    src/SMTP.php

They are committed on purpose: deployment is `git reset --hard`, so anything
installed by hand on the server is easy to lose, and without these files no
email could be sent — the server has no working local mail transport.

To upgrade, copy the same three files from a newer release tag and update the
version above. LICENSE is PHPMailer's (LGPL 2.1).

SMTP details are set in Admin → Notifications, which also has a
"Send test email" button that shows the real error if sending fails.
