<?php
/**
 * Table of a partner's content, shared by the dashboard, the Singles and Albums
 * lists, and a customer's page. Expects $rows (ArPartnerService::contentList).
 */
?>
<div class="table-scroll">
    <table class="p-table">
        <thead>
            <tr>
                <th style="width:58px"></th>
                <th>Title</th>
                <th>Customer</th>
                <th>Type</th>
                <th>Active until</th>
                <th>Status</th>
                <th class="num">Opens</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row):
                [$stateLabel, $stateTone] = ArPartnerService::contentState($row);
                $link = url($base . '/content/' . (int)$row['id']); ?>
                <tr>
                    <td>
                        <?php if (!empty($row['first_photo'])): ?>
                            <img class="thumb" src="<?= e(ArFrameService::fileUrl($row['first_photo'])) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <span class="thumb"></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= $link ?>"><?= e($row['title'] ?: $row['slug']) ?></a>
                        <div class="muted small">
                            <?= e(date('d M Y, h:i A', strtotime($row['created_at']))) ?>
                            <?php if (ArPartnerService::isEditable($row)): ?>
                                · <a href="<?= url($base . '/content/' . (int)$row['id'] . '/edit') ?>">Edit</a>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><?= e($row['customer_name'] ?? '—') ?></td>
                    <td>
                        <?= $row['content_kind'] === 'album' ? 'Album · ' . (int)$row['item_count'] . ' pages' : 'Single' ?>
                    </td>
                    <td><?= empty($row['active_until']) ? 'Lifetime' : e(date('d M Y', strtotime($row['active_until']))) ?></td>
                    <td><span class="tag tag-<?= $stateTone ?>"><?= e($stateLabel) ?></span></td>
                    <td class="num"><?= number_format((int)$row['opens']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
