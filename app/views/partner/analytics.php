<?php
/**
 * Scan analytics for one partner. Every chart is a single series in the
 * partner's own colour; the "All content opens" table below is the table view
 * of the same numbers.
 */
$maxDaily = max(1, max($daily));
$hasViews = array_sum($daily) > 0;
$opened = array_values(array_filter($content, fn($r) => (int)$r['opens'] > 0));
$top = array_slice($opened, 0, 8);
$albums = array_values(array_filter($opened, fn($r) => $r['content_kind'] === 'album'));
$topMax = $top ? max(1, (int)$top[0]['opens']) : 1;
$albumMax = $albums ? max(1, (int)$albums[0]['opens']) : 1;

// Column chart geometry, in viewBox units.
$w = 600; $h = 180; $padL = 28; $padB = 22; $padT = 8;
$plotW = $w - $padL; $plotH = $h - $padB - $padT;
$slot = $plotW / count($daily);
$barW = max(2, $slot - 2);                         // 2-unit gap between columns
$niceMax = $maxDaily <= 4 ? 4 : (int)(ceil($maxDaily / 4) * 4);

$label = fn($r) => $r['title'] ?: $r['slug'];
?>
<div class="stats stats-4">
    <div class="stat"><div><div class="label">Total opens</div><div class="value"><?= number_format($totals['opens']) ?></div></div></div>
    <div class="stat"><div><div class="label">Unique visitors</div><div class="value"><?= number_format($totals['visitors']) ?></div></div></div>
    <div class="stat"><div><div class="label">Total albums</div><div class="value"><?= number_format($counts['albums']) ?></div></div></div>
    <div class="stat"><div><div class="label">Total frames</div><div class="value"><?= number_format($counts['pages']) ?></div><div class="sub">Singles + album pages</div></div></div>
</div>

<div class="charts">
    <div class="card">
        <div class="card-head"><h2>Views over time <span class="muted small">(30 days)</span></h2></div>
        <?php if (!$hasViews): ?>
            <div class="chart-empty">No views data yet.</div>
        <?php else: ?>
            <div class="col-chart">
                <svg viewBox="0 0 <?= $w ?> <?= $h ?>" role="img"
                     aria-label="Opens per day over the last 30 days, peaking at <?= $maxDaily ?>">
                    <?php for ($g = 0; $g <= 4; $g++):
                        $y = $padT + $plotH - $plotH * $g / 4; ?>
                        <line class="grid" x1="<?= $padL ?>" x2="<?= $w ?>" y1="<?= $y ?>" y2="<?= $y ?>"/>
                        <text class="axis" x="<?= $padL - 6 ?>" y="<?= $y + 3 ?>" text-anchor="end"><?= (int)($niceMax * $g / 4) ?></text>
                    <?php endfor; ?>
                    <?php $i = 0; foreach ($daily as $day => $count):
                        $x = $padL + $i * $slot + 1;
                        $bh = $count > 0 ? max(2, $plotH * $count / $niceMax) : 0;
                        $y = $padT + $plotH - $bh; ?>
                        <?php if ($bh > 0): ?>
                            <path d="M<?= round($x, 2) ?>,<?= round($padT + $plotH, 2) ?> V<?= round($y + min(4, $bh), 2) ?>
                                     q0,-<?= min(4, $bh) ?> <?= min(4, $barW / 2) ?>,-<?= min(4, $bh) ?>
                                     H<?= round($x + $barW - min(4, $barW / 2), 2) ?>
                                     q<?= min(4, $barW / 2) ?>,0 <?= min(4, $barW / 2) ?>,<?= min(4, $bh) ?>
                                     V<?= round($padT + $plotH, 2) ?> Z" fill="var(--brand)">
                                <title><?= e(date('D d M', strtotime($day))) ?>: <?= $count ?> open<?= $count === 1 ? '' : 's' ?></title>
                            </path>
                        <?php endif; ?>
                        <?php // An invisible full-height hit target, so small days are still easy to hover. ?>
                        <rect x="<?= round($x, 2) ?>" y="<?= $padT ?>" width="<?= round($slot, 2) ?>" height="<?= $plotH ?>" fill="transparent" style="fill:transparent">
                            <title><?= e(date('D d M', strtotime($day))) ?>: <?= $count ?> open<?= $count === 1 ? '' : 's' ?></title>
                        </rect>
                        <?php if ($i % 7 === 0): ?>
                            <text class="axis" x="<?= round($x + $barW / 2, 2) ?>" y="<?= $h - 6 ?>" text-anchor="middle"><?= e(date('d M', strtotime($day))) ?></text>
                        <?php endif; ?>
                    <?php $i++; endforeach; ?>
                </svg>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head"><h2>Top content by views</h2></div>
        <?php if (!$top): ?>
            <div class="chart-empty">No views data yet.</div>
        <?php else: ?>
            <div class="hbars">
                <?php foreach ($top as $row): ?>
                    <div class="hbar-row" title="<?= e($label($row)) ?>: <?= (int)$row['opens'] ?> opens">
                        <span class="name"><a class="btn-link" style="padding:0" href="<?= url($base . '/content/' . (int)$row['id']) ?>"><?= e($label($row)) ?></a></span>
                        <span class="val"><?= number_format((int)$row['opens']) ?></span>
                        <div class="hbar-track"><div class="hbar-fill" style="width:<?= round((int)$row['opens'] / $topMax * 100, 1) ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head"><h2>Opens per album</h2></div>
        <?php if (!$albums): ?>
            <div class="chart-empty">No views data yet.</div>
        <?php else: ?>
            <div class="hbars">
                <?php foreach (array_slice($albums, 0, 8) as $row): ?>
                    <div class="hbar-row" title="<?= e($label($row)) ?>: <?= (int)$row['opens'] ?> opens">
                        <span class="name"><?= e($label($row)) ?></span>
                        <span class="val"><?= number_format((int)$row['opens']) ?></span>
                        <div class="hbar-track"><div class="hbar-fill" style="width:<?= round((int)$row['opens'] / $albumMax * 100, 1) ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head"><h2>All content opens</h2></div>
        <?php if (!$content): ?>
            <div class="chart-empty">No views data yet.</div>
        <?php else: ?>
            <div class="table-scroll" style="max-height:360px;overflow:auto">
                <table class="p-table" style="min-width:460px">
                    <thead><tr><th>Content</th><th>Customer</th><th class="num">Opens</th><th class="num">Unique</th><th>Last opened</th></tr></thead>
                    <tbody>
                        <?php foreach ($content as $row): ?>
                            <tr>
                                <td><a href="<?= url($base . '/content/' . (int)$row['id']) ?>"><?= e($label($row)) ?></a></td>
                                <td><?= e($row['customer_name'] ?? '—') ?></td>
                                <td class="num"><?= number_format((int)$row['opens']) ?></td>
                                <td class="num"><?= number_format((int)$row['visitors']) ?></td>
                                <td class="muted small"><?= $row['last_opened'] ? e(timeAgo($row['last_opened'])) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<p class="hint">Counts each time a scan page is opened. Link previews in WhatsApp and similar apps are not counted.</p>
