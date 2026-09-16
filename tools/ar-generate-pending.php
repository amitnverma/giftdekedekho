<?php
/**
 * Compiles AR targets for Living Photo frames that are still waiting for one.
 *
 * Usage:  php tools/ar-generate-pending.php --dry-run
 *         php tools/ar-generate-pending.php
 *         php tools/ar-generate-pending.php --limit=5
 *
 * An online order queues its frame and compiles the target straight after
 * checkout. Anything that missed that step stays untouched until this runs:
 * orders placed before the automatic step existed, a compile that failed, or a
 * host that cannot do work after the response is sent.
 *
 * It matters because a photo with no target is not scannable — the QR sticker
 * opens a page saying the Living Photo is "still being prepared". This is the
 * catch-up, and the same job the admin's "Generate targets" button does.
 *
 * Safe to run repeatedly and from cron: photos that already have a target are
 * skipped, nothing is deleted, and frames are left in the queue on failure.
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
require __DIR__ . '/../app/models/ArFrameItem.php';
require __DIR__ . '/../app/services/ArTargetService.php';
require __DIR__ . '/../app/services/ArFrameService.php';

function out(string $line = ''): void { echo $line . "\n"; }

$dryRun = in_array('--dry-run', $argv, true);
$limit = 0;
foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
        $limit = (int)$m[1];
    }
}

$frames = new ArFrame();
if (!$frames->tableExists()) {
    exit("The ar_frames table does not exist yet. Run the AR migrations first.\n");
}

$service = new ArFrameService();
if (!$service->itemsReady()) {
    exit("Run migrations/2026_09_14_ar_frame_items.sql first — this script needs the per-photo table.\n");
}

$compiler = (new ArTargetService())->preflight();
if (empty($compiler['ok'])) {
    out('Compiler not ready: ' . ($compiler['message'] ?? 'unknown reason'));
    exit("Nothing compiled. Fix that first (usually: cd tools/mindar-compile && npm ci).\n");
}
out('Compiler: ' . ($compiler['message'] ?? 'ready'));

// Frames holding at least one photo that has never been compiled.
$pending = $frames->rawQuery(
    "SELECT f.id, f.slug, f.status, f.order_item_id,
            (SELECT COUNT(*) FROM ar_frame_items i WHERE i.frame_id = f.id) AS photos,
            (SELECT COUNT(*) FROM ar_frame_items i
              WHERE i.frame_id = f.id AND (i.target_path IS NULL OR i.target_path = '')) AS missing
       FROM ar_frames f
      WHERE EXISTS (SELECT 1 FROM ar_frame_items i
                     WHERE i.frame_id = f.id AND (i.target_path IS NULL OR i.target_path = ''))
      ORDER BY f.created_at ASC"
);

if (!$pending) {
    exit("Nothing pending — every photo already has a target.\n");
}

if ($limit > 0) {
    $pending = array_slice($pending, 0, $limit);
}

out(sprintf("%d frame%s waiting:", count($pending), count($pending) === 1 ? '' : 's'));
foreach ($pending as $row) {
    out(sprintf('  %s — %d of %d photo(s) missing a target [%s]',
        $row['slug'], (int)$row['missing'], (int)$row['photos'], $row['status']));
}

if ($dryRun) {
    exit("\nDry run — nothing was compiled.\n");
}

$done = 0;
$failed = 0;
foreach ($pending as $row) {
    out("\nCompiling " . $row['slug'] . ' …');
    @set_time_limit(0);
    $result = $service->generateTarget((int)$row['id'], true);

    if (!empty($result['ok'])) {
        $done++;
        out(sprintf('  done — %d photo(s) compiled%s',
            (int)($result['compiled'] ?? 0),
            isset($result['score']) ? sprintf(', trackability %d/100 (%s)', $result['score'], $result['flag']) : ''
        ));
        foreach (($result['failures'] ?? []) as $failure) {
            out('  photo ' . $failure['position'] . ' failed: ' . $failure['error']);
        }
        continue;
    }

    $failed++;
    out('  failed: ' . ($result['error'] ?? 'unknown error'));
}

out(sprintf("\n%d frame(s) compiled, %d failed.", $done, $failed));
out("Scan a sticker to confirm, then record the live test in the admin before printing.");
