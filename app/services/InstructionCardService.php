<?php
/**
 * Generates the small printed insert that ships with (or is handed over with) an
 * AR frame. It carries the frame's personal scan link — the product itself stays
 * completely unmarked, which is the point of the feature.
 *
 * Follows the same pattern as the order invoice: a real PDF when FPDF is
 * installed, a printable HTML card otherwise, so the feature never hard-depends
 * on an optional vendor library.
 */
class InstructionCardService
{
    public function output(array $frame): void
    {
        $scanUrl = ArFrameService::scanUrl($frame['slug']);
        $siteName = siteSetting('site_name', SITE_NAME);

        $fpdfLib = BASE_PATH . '/libs/fpdf/fpdf.php';
        if (is_file($fpdfLib)) {
            require_once $fpdfLib;
            $this->renderPdf($frame, $scanUrl, $siteName);
            return;
        }

        renderRaw('admin/ar_card_html', [
            'frame' => $frame,
            'scanUrl' => $scanUrl,
            'siteName' => $siteName,
        ]);
    }

    /**
     * A5 card. FPDF's core fonts are Latin-1 only, so text is transliterated —
     * the rupee sign and smart quotes would otherwise come out as mojibake.
     */
    private function renderPdf(array $frame, string $scanUrl, string $siteName): void
    {
        $pdf = new FPDF('P', 'mm', 'A5');
        $pdf->SetMargins(16, 16, 16);
        $pdf->AddPage();

        $pdf->SetFont('Arial', 'B', 18);
        $pdf->Cell(0, 10, $this->latin($siteName), 0, 1, 'C');

        $pdf->SetFont('Arial', '', 11);
        $pdf->SetTextColor(120, 120, 130);
        $pdf->Cell(0, 6, $this->latin('Your Living Photo'), 0, 1, 'C');
        $pdf->Ln(6);

        $pdf->SetTextColor(30, 30, 35);
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 8, $this->latin('This photo plays a video.'), 0, 1, 'C');

        $pdf->SetFont('Arial', '', 10.5);
        $pdf->SetTextColor(70, 70, 80);
        $pdf->MultiCell(0, 6, $this->latin(
            "Scan the small QR sticker on the frame with your phone camera and allow camera access. "
            . "Then point your camera at the photo itself - the video starts on its own. "
            . "Nothing is printed on the photo, and there is no app to install.\n\n"
            . "If the sticker is missing or damaged, open this link instead:"
        ), 0, 'C');
        $pdf->Ln(4);

        // The link, boxed so it reads as the thing to act on.
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(230, 57, 70);
        $pdf->SetDrawColor(230, 57, 70);
        $pdf->Cell(0, 12, $this->latin($this->displayUrl($scanUrl)), 1, 1, 'C');
        $pdf->Ln(6);

        $pdf->SetFont('Arial', '', 9.5);
        $pdf->SetTextColor(110, 110, 120);
        $pdf->MultiCell(0, 5.5, $this->latin(
            "Tips\n"
            . "- Works in Safari on iPhone and Chrome on Android. No app needed.\n"
            . "- Fit the whole photo in the camera view and hold steady.\n"
            . "- Good, even light helps. Avoid glare on the glass.\n"
            . "- Keep this card safe: it is your backup if the sticker comes off."
        ), 0, 'L');

        $pdf->Ln(6);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(150, 150, 160);
        $pdf->Cell(0, 5, $this->latin('Frame code: ' . $frame['slug']), 0, 1, 'C');

        $pdf->Output('I', 'living-photo-card-' . $frame['slug'] . '.pdf');
        exit;
    }

    /** Drop the scheme so the printed URL stays short and readable. */
    private function displayUrl(string $url): string
    {
        return preg_replace('#^https?://#i', '', $url);
    }

    /** FPDF core fonts are Latin-1; anything outside it must be folded down. */
    private function latin(string $text): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
        return $converted === false ? $text : $converted;
    }
}
