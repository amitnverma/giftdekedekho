<?php
/**
 * Local testing helper for the Living Photo AR frame feature.
 *
 * Wraps the fiddly parts of testing this locally: keeping the target-compiler
 * service alive, creating throwaway frames without clicking through the admin
 * UI, printing the exact URLs to open (including the LAN address a phone needs),
 * and tearing it all down again.
 *
 * Dev-only. Every frame it creates is tagged so `clean` can never touch a real
 * one. Run it from anywhere:
 *
 *   php tools/ar-dev.php help
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("This script is CLI-only.\n");
}

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/models/BaseModel.php';
require __DIR__ . '/../app/models/ArFrame.php';
require __DIR__ . '/../app/services/ArTargetService.php';
require __DIR__ . '/../app/services/ArFrameService.php';

/** Marker that identifies a frame this script created. */
const DEV_TAG = '[ar-dev test frame]';

const SERVICE_HOST = '127.0.0.1';
const SERVICE_PORT = 9077;
const SERVICE_TOKEN = 'local-dev-token';

// ---------------------------------------------------------------- tiny helpers

function out(string $line = ''): void { echo $line . "\n"; }
function ok(string $line): void      { out("  \033[32m✓\033[0m " . $line); }
function bad(string $line): void     { out("  \033[31m✗\033[0m " . $line); }
function warn(string $line): void    { out("  \033[33m!\033[0m " . $line); }
function heading(string $t): void    { out("\n\033[1m" . $t . "\033[0m"); }

function lanIp(): ?string
{
    foreach (['en0', 'en1'] as $interface) {
        $ip = trim((string)@shell_exec('ipconfig getifaddr ' . $interface . ' 2>/dev/null'));
        if ($ip !== '') return $ip;
    }
    return null;
}

function serviceUp(): bool
{
    $ch = curl_init('http://' . SERVICE_HOST . ':' . SERVICE_PORT . '/health');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $status === 200 && is_string($body) && strpos($body, '"ok":true') !== false;
}

// ------------------------------------------------------------------- commands

/**
 * The compiler service is deliberately NOT started from here. It is a
 * long-running process, and daemonising it from a PHP CLI script means no
 * visible logs and a pid nobody owns. Run it in its own terminal tab instead —
 * this just tells you how, and whether it worked.
 */
function serviceInstructions(): void
{
    warn('The target compiler service is not running.');
    out('');
    out('  Open a second terminal tab and leave this running:');
    out("\n    \033[1mcd " . BASE_PATH . '/tools/mindar-compile');
    out('    GDD_AR_TOKEN=' . SERVICE_TOKEN . " npm run serve\033[0m\n");
    out('  (Needed because Apache runs as a different user that cannot reach');
    out('  nvm\'s node. On the production VPS, shell mode avoids this entirely.)');
}

function cmdCheck(): int
{
    heading('Environment');

    $compiler = new ArTargetService();
    $preflight = $compiler->preflight();
    out('  transport: ' . $compiler->mode());
    $preflight['ok'] ? ok($preflight['message']) : bad($preflight['message']);

    if (!$preflight['ok'] && $compiler->mode() === 'http') {
        serviceInstructions();
    }

    foreach ([ArFrameService::PHOTO_DIR, ArFrameService::TARGET_DIR, ArFrameService::VIDEO_DIR] as $dir) {
        $path = UPLOAD_PATH . '/' . $dir;
        if (!is_dir($path)) {
            bad($dir . '/ is missing');
        } elseif (!is_writable($path)) {
            bad($dir . '/ is not writable — the web server cannot save uploads here.'
                . ' Fix with:  chmod 777 public/uploads/' . $dir);
        } else {
            ok($dir . '/ writable');
        }
    }

    // The web server runs as a different user than this CLI, which is the usual
    // cause of "it works from the terminal but not in the browser".
    $webUser = trim((string)shell_exec("ps aux | grep '[h]ttpd' | awk '{print \$1}' | grep -v root | head -1"));
    if ($webUser !== '') {
        out('  web server user: ' . $webUser . ' (this CLI runs as ' . (get_current_user() ?: '?') . ')');
    }

    return $preflight['ok'] ? 0 : 1;
}

function cmdFrame(array $args): int
{
    $photo = $args[0] ?? (BASE_PATH . '/images/AnniversaryChocolateBouquet.jpeg');
    $youtube = $args[1] ?? 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    if (!is_file($photo)) {
        bad('Photo not found: ' . $photo);
        return 1;
    }

    if (!serviceUp() && (new ArTargetService())->mode() === 'http') {
        heading('Cannot compile yet');
        serviceInstructions();
        return 1;
    }

    $service = new ArFrameService();
    $frames = new ArFrame();

    $normalised = $service->normaliseYoutubeUrl($youtube);
    if ($normalised === null) {
        bad('Not a valid YouTube URL: ' . $youtube);
        return 1;
    }

    // Copy the source image in rather than moving it — storePhoto() expects a
    // real upload, and this is a dev shortcut around that.
    $extension = strtolower(pathinfo($photo, PATHINFO_EXTENSION)) === 'png' ? 'png' : 'jpg';
    $relative = ArFrameService::PHOTO_DIR . '/arphoto_dev_' . bin2hex(random_bytes(6)) . '.' . $extension;
    if (!copy($photo, UPLOAD_PATH . '/' . $relative)) {
        bad('Could not copy the photo into public/uploads/' . ArFrameService::PHOTO_DIR);
        return 1;
    }

    heading('Creating test frame');
    out('  photo:   ' . $photo);
    out('  video:   ' . $normalised);

    $id = $service->createFrame([
        'channel' => 'in_store',
        'photo_path' => $relative,
        'video_type' => 'youtube',
        'video_url' => $normalised,
        'customer_name' => 'AR Dev Test',
        'notes' => DEV_TAG,
    ]);

    $result = $service->generateTarget($id);
    if (empty($result['ok'])) {
        bad($result['error']);
        if (!empty($result['detail'])) out('     ' . $result['detail']);
        return 1;
    }

    $frame = $frames->find($id);
    ok(sprintf('Compiled in %.1fs — trackability %d/100 (%s)',
        ($result['metrics']['elapsed_ms'] ?? 0) / 1000, $result['score'], $result['flag']));

    printUrls($frame);
    return 0;
}

function cmdList(): int
{
    heading('AR frames');
    $rows = Database::getInstance()
        ->query('SELECT id, slug, channel, status, trackability_score, verified_at, notes
                 FROM ar_frames ORDER BY id DESC LIMIT 25')
        ->fetchAll();

    if (!$rows) {
        out('  (none — create one with:  php tools/ar-dev.php frame)');
        return 0;
    }

    printf("  %-4s %-13s %-9s %-17s %-6s %-9s %s\n",
        'ID', 'SLUG', 'CHANNEL', 'STATUS', 'SCORE', 'TESTED', '');
    foreach ($rows as $r) {
        printf("  %-4d %-13s %-9s %-17s %-6s %-9s %s\n",
            $r['id'], $r['slug'], $r['channel'], $r['status'],
            // Plain ASCII: printf pads by bytes, so a multibyte dash would
            // misalign the column.
            $r['trackability_score'] ?? '-',
            $r['verified_at'] ? 'yes' : 'no',
            strpos((string)$r['notes'], DEV_TAG) !== false ? '(dev)' : '');
    }
    return 0;
}

function cmdUrls(array $args): int
{
    $frames = new ArFrame();
    $frame = isset($args[0]) ? $frames->findBySlug($args[0]) : null;

    if ($frame === null) {
        $row = Database::getInstance()
            ->query('SELECT * FROM ar_frames WHERE target_path IS NOT NULL ORDER BY id DESC LIMIT 1')
            ->fetch();
        $frame = $row ?: null;
    }

    if ($frame === null) {
        bad('No frame with a generated target. Create one:  php tools/ar-dev.php frame');
        return 1;
    }

    printUrls($frame);
    return 0;
}

function printUrls(array $frame): void
{
    $base = rtrim(SITE_URL, '/');
    $path = parse_url($base, PHP_URL_PATH) ?: '';

    heading('Frame ' . $frame['slug']);

    out("\n  \033[1mDesktop\033[0m — works as-is, localhost counts as a secure origin");
    out('    scan page   ' . $base . '/scan/' . $frame['slug']);
    out('    admin frame ' . $base . '/admin/ar-frames/' . $frame['id']);
    out('    live test   ' . $base . '/admin/ar-frames/' . $frame['id'] . '/live-test');
    out('    the photo   ' . ArFrameService::fileUrl($frame['photo_path']));
    out('    Open "the photo" on your phone, then hold the phone up to your webcam.');

    $ip = lanIp();
    if ($ip !== null) {
        out("\n  \033[1mPhone\033[0m — must be https, or the browser blocks the camera");
        out('    https://' . $ip . $path . '/scan/' . $frame['slug']);
        out('    Same Wi-Fi. XAMPP\'s certificate is self-signed, so tap through the');
        out('    "Not Private" warning once (Advanced -> Proceed). After that the camera works.');
    }

    out("\n  \033[1mNo camera at all\033[0m — proves recognition headlessly");
    $photo = (new ArFrameService())->absolutePath((string)$frame['photo_path']);
    out('    node tools/mindar-compile/verify-match.mjs \\');
    out('      public/uploads/' . $frame['target_path'] . ' \\');
    out('      ' . ($photo ?? 'path/to/photo.jpg'));
    out('');
}

function cmdClean(): int
{
    heading('Removing dev test frames');

    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM ar_frames WHERE notes LIKE ?');
    $stmt->execute(['%' . DEV_TAG . '%']);
    $rows = $stmt->fetchAll();

    if (!$rows) {
        out('  (nothing tagged as a dev test frame)');
        return 0;
    }

    $service = new ArFrameService();
    foreach ($rows as $row) {
        $service->deleteFile($row['photo_path']);
        $service->deleteFile($row['target_path']);
        $service->deleteFile($row['video_path']);
        $db->prepare('DELETE FROM ar_frames WHERE id = ?')->execute([$row['id']]);
        ok('Removed ' . $row['slug']);
    }

    out("\n  " . count($rows) . ' frame(s) removed. Real frames were left untouched.');
    return 0;
}

function cmdHelp(): int
{
    out(<<<TXT

Living Photo AR — local testing helper

  php tools/ar-dev.php check              Verify the toolchain, permissions and users
  php tools/ar-dev.php frame [photo] [yt] Create a test frame and compile its target
  php tools/ar-dev.php list               List AR frames
  php tools/ar-dev.php urls [slug]        Print the testing URLs (newest frame by default)
  php tools/ar-dev.php clean              Delete only the frames this script created

Quickest path from nothing to a working scan:

  1. In a second terminal tab, leave the compiler running:
       cd tools/mindar-compile && GDD_AR_TOKEN=local-dev-token npm run serve
  2. php tools/ar-dev.php check
  3. php tools/ar-dev.php frame
  4. Open the "Desktop" scan URL it prints, then hold your phone
     (showing the photo URL it also prints) up to your webcam.

Finished testing?  php tools/ar-dev.php clean

TXT);
    return 0;
}

// ------------------------------------------------------------------ dispatch

$command = $argv[1] ?? 'help';
$args = array_slice($argv, 2);

switch ($command) {
    case 'check':  exit(cmdCheck());
    case 'frame':  exit(cmdFrame($args));
    case 'list':   exit(cmdList());
    case 'urls':   exit(cmdUrls($args));
    case 'clean':  exit(cmdClean());
    default:       exit(cmdHelp());
}
