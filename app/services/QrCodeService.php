<?php
/**
 * Generates QR code images using the bundled phpqrcode library (libs/phpqrcode).
 * Falls back to the public QR Server image API if the local library is unavailable,
 * so the feature keeps working even before the vendor library is installed.
 */
class QrCodeService
{
    /** Error-correction levels both back ends understand, weakest first. */
    private const EC_LEVELS = ['L', 'M', 'Q', 'H'];

    /**
     * @param string $ecLevel  L/M/Q/H. Higher survives more damage at the cost of
     *                         a denser code — worth it for anything printed and
     *                         stuck to a physical product, where scuffs are normal.
     * @param int    $pixelSize Module size in pixels. Bigger means a larger PNG,
     *                         which is what print resolution needs.
     */
    public function generate(string $data, string $destinationPath, string $ecLevel = 'M', int $pixelSize = 8): bool
    {
        $ecLevel = in_array($ecLevel, self::EC_LEVELS, true) ? $ecLevel : 'M';
        $pixelSize = max(1, min(32, $pixelSize));

        $qrLib = BASE_PATH . '/libs/phpqrcode/qrlib.php';

        if (is_file($qrLib)) {
            require_once $qrLib;
            try {
                $levels = ['L' => QR_ECLEVEL_L, 'M' => QR_ECLEVEL_M, 'Q' => QR_ECLEVEL_Q, 'H' => QR_ECLEVEL_H];
                QRcode::png($data, $destinationPath, $levels[$ecLevel], $pixelSize, 2);
                return is_file($destinationPath);
            } catch (Throwable $e) {
                error_log('phpqrcode error: ' . $e->getMessage());
            }
        }

        // Fallback: fetch a generated QR PNG from a public service.
        $side = max(400, $pixelSize * 50);
        $url = 'https://api.qrserver.com/v1/create-qr-code/'
             . '?size=' . $side . 'x' . $side
             . '&ecc=' . $ecLevel
             . '&data=' . urlencode($data);
        $image = @file_get_contents($url);

        // The API answers errors with a body too, and a saved error page would
        // pass every later is_file() check while showing a broken image on a
        // sheet somebody is about to print. Only accept a real PNG.
        if ($image === false || strncmp($image, "\x89PNG\r\n\x1a\n", 8) !== 0) {
            return false;
        }

        return file_put_contents($destinationPath, $image) !== false;
    }

    /**
     * The same PNG as a `data:` URI, for pages that embed the code rather than
     * link to it — printing is the point of those pages, and an embedded image
     * cannot go missing between the screen and the print dialog.
     *
     * Backed by a cache under the uploads directory, keyed on the exact code
     * being drawn, so re-printing costs nothing and a changed URL (a new domain,
     * say) simply produces a different key rather than a stale code.
     *
     * @return string|null null when neither back end could produce a code.
     */
    public function pngDataUri(string $data, string $ecLevel = 'M', int $pixelSize = 8): ?string
    {
        $absolute = UPLOAD_PATH . '/' . self::cachePath($data, $ecLevel, $pixelSize);

        if (!is_file($absolute)) {
            $dir = dirname($absolute);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return null;
            }
            if (!$this->generate($data, $absolute, $ecLevel, $pixelSize)) {
                @unlink($absolute);   // generate() may have left a partial write
                return null;
            }
        }

        $png = @file_get_contents($absolute);
        if ($png === false || $png === '') {
            return null;
        }
        return 'data:image/png;base64,' . base64_encode($png);
    }

    /**
     * Where pngDataUri() caches this exact code, relative to the uploads dir.
     *
     * Public so owners of the encoded data can clean up after themselves — the
     * cache has no expiry of its own. The `qrc_` prefix keeps it clear of the
     * `qr_<token>.png` files the video-photo flow writes to the same folder.
     */
    public static function cachePath(string $data, string $ecLevel = 'M', int $pixelSize = 8): string
    {
        $key = hash('sha256', $data . '|' . $ecLevel . '|' . $pixelSize);
        return 'qrcodes/qrc_' . substr($key, 0, 24) . '.png';
    }
}
