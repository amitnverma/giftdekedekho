FPDF library (used for generating PDF invoices in the admin panel).

Download the latest release from http://www.fpdf.org/ and extract
fpdf.php (and the accompanying font/ directory) directly into this folder,
so that the file app/controllers/AdminOrderController.php can require:

    libs/fpdf/fpdf.php

If this file is missing, the admin panel automatically falls back to a
printable HTML invoice (admin/invoice_html.php) — the "Download Invoice"
button will keep working either way.
