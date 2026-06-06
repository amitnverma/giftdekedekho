<?php
/**
 * Generates QR code images using the bundled phpqrcode library (libs/phpqrcode).
 * Falls back to the public QR Server image API if the local library is unavailable,
 * so the feature keeps working even before the vendor library is installed.
 */
class QrCodeService
{
    public function generate(string $data, string $destinationPath): bool
    {
        $qrLib = BASE_PATH . '/libs/phpqrcode/qrlib.php';

        if (is_file($qrLib)) {
            require_once $qrLib;
            try {
                QRcode::png($data, $destinationPath, QR_ECLEVEL_M, 8, 2);
                return is_file($destinationPath);
            } catch (Throwable $e) {
                error_log('phpqrcode error: ' . $e->getMessage());
            }
        }

        // Fallback: fetch a generated QR PNG from a public service.
        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' . urlencode($data);
        $image = @file_get_contents($url);
        if ($image === false) return false;

        return file_put_contents($destinationPath, $image) !== false;
    }
}
