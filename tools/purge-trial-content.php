<?php
/**
 * Deletes DEx content made on a free trial once its time is up.
 *
 * Usage:  php tools/purge-trial-content.php --dry-run
 *         php tools/purge-trial-content.php
 *
 * Trial content (ar_frames.delete_after set) stops scanning the moment its
 * time is up, and the partner and admin pages delete it as they are opened.
 * This removes the files on schedule even when nobody opens those pages —
 * run it from the site user's crontab, e.g. hourly:
 *
 *   0 * * * * cd /path/to/site && php tools/purge-trial-content.php
 *
 * Only rows whose delete_after has passed are touched, and only the files
 * those rows name. Safe to run repeatedly.
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
require __DIR__ . '/../app/models/ArPartner.php';
require __DIR__ . '/../app/models/ArPartnerCredit.php';
require __DIR__ . '/../app/services/ArPartnerService.php';

function out(string $line = ''): void { echo $line . "\n"; }

$dryRun = in_array('--dry-run', $argv, true);

if (!(new ArPartner())->trialsReady()) {
    exit("The trial migration has not been run: php tools/run-migration.php migrations/2026_09_23_partner_trials.sql\n");
}

$service = new ArPartnerService();
$total = 0;
do {
    $batch = $service->purgeDueTrialContent(100, $dryRun);
    foreach ($batch as $frame) {
        out(sprintf('%s frame #%d (%s), partner #%d, due %s',
            $dryRun ? 'Would delete' : 'Deleted', $frame['id'], $frame['slug'], $frame['partner_id'], $frame['delete_after']));
    }
    $total += count($batch);
} while (!$dryRun && count($batch) === 100);

out(($dryRun ? 'Due for deletion: ' : 'Deleted: ') . $total);
