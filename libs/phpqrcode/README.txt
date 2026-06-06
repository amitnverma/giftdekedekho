phpqrcode library (used to generate the Video-Photo QR codes).

Download from https://sourceforge.net/projects/phpqrcode/ and extract
qrlib.php (and its dependencies) directly into this folder, so that
app/services/QrCodeService.php can require:

    libs/phpqrcode/qrlib.php

If this file is missing, QrCodeService automatically falls back to the
public QR Server image API (https://api.qrserver.com) so QR generation
keeps working out of the box.
