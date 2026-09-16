<?php
/**
 * Balance, packs and history. "Buy" records a request; payment is arranged
 * with GiftDekeDekho directly and the credits are added by their admin.
 */
$base_credits = (int)$partner['base_credits'];
$pending = array_values(array_filter($requests, fn($r) => $r['status'] === 'pending'));
$supportName = $brand['poweredBy'] ?? 'us';
if ($support) {
    $headAction = '<a class="btn" href="https://wa.me/' . e($support) . '?text='
        . rawurlencode('Hi, this is ' . $partner['name'] . '. I would like to buy AR credits.')
        . '" target="_blank" rel="noopener">+ Buy Credits</a>';
}
?>
<?php if ($packs): ?>
    <div class="packs-wrap">
        <p class="label">Credit packages</p>
        <div class="packs">
            <?php foreach ($packs as $i => $pack): ?>
                <div class="pack">
                    <div class="price"><?= e(GDD_CURRENCY_SYMBOL . number_format($pack['price'])) ?></div>
                    <div class="get">Get <?= number_format($pack['credits']) ?> credits</div>
                    <div class="per">→ <?= e(GDD_CURRENCY_SYMBOL . number_format(ArPartnerService::packItemPrice($pack, $partner), 2)) ?>/item</div>
                    <form method="post" action="<?= url($base . '/credits/request') ?>">
                        <?= csrfField() ?>
                        <input type="hidden" name="pack" value="<?= (int)$i ?>">
                        <button class="btn btn-ghost btn-sm" type="submit" style="width:100%">Request</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($pending): ?>
    <div class="banner banner-amber">
        <p>
            <strong><?= count($pending) === 1 ? 'Request pending' : count($pending) . ' requests pending' ?>:</strong>
            <?= e(implode(', ', array_map(
                fn($r) => number_format((int)$r['credits']) . ' credits for ' . GDD_CURRENCY_SYMBOL . number_format((int)$r['price']),
                $pending
            ))) ?>.
            Pay <?= e($supportName) ?> as agreed and the credits will be added here.
        </p>
        <?php if ($support): ?>
            <a class="btn btn-amber btn-sm" target="_blank" rel="noopener"
               href="https://wa.me/<?= e($support) ?>?text=<?= rawurlencode('Hi, this is ' . $partner['name'] . '. I have requested ' . number_format((int)$pending[0]['credits']) . ' AR credits (' . GDD_CURRENCY_SYMBOL . number_format((int)$pending[0]['price']) . '). How should I pay?') ?>">
                Message on WhatsApp
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="stats stats-3">
    <div class="stat stat-big"><div><div class="label">Credits</div><div class="value value-green"><?= number_format($balance) ?></div></div></div>
    <div class="stat stat-big"><div><div class="label">Rate</div><div class="value"><?= number_format($base_credits) ?><small>credits/item</small></div></div></div>
    <div class="stat stat-big"><div><div class="label">Can create</div><div class="value"><?= number_format(ArPartnerService::itemsAffordable($partner)) ?><small>items</small></div></div></div>
</div>
<p class="hint" style="margin:-6px 0 16px">
    “Can create” counts basic items. Longer videos and longer validity cost more — the create form shows the exact price.
</p>

<div class="card">
    <div class="card-head"><h2>Transaction History</h2></div>
    <?php if ($ledger): ?>
        <div class="table-scroll">
            <table class="p-table">
                <thead><tr><th>Date</th><th>Type</th><th class="num">Amount</th><th class="num">Balance</th><th>Description</th></tr></thead>
                <tbody>
                    <?php foreach ($ledger as $row): $amount = (int)$row['amount']; ?>
                        <tr>
                            <td class="muted small"><?= e(date('M d, Y H:i', strtotime($row['created_at']))) ?></td>
                            <td><span class="tag tag-<?= e($row['type']) ?>"><?= e(ArPartnerCredit::TYPES[$row['type']] ?? $row['type']) ?></span></td>
                            <td class="num <?= $amount < 0 ? 'amount-neg' : 'amount-pos' ?>"><?= $amount > 0 ? '+' : '' ?><?= number_format($amount) ?> credits</td>
                            <td class="num"><?= number_format((int)$row['balance_after']) ?> credits</td>
                            <td>
                                <?php if (!empty($row['frame_id'])): ?>
                                    <a href="<?= url($base . '/content/' . (int)$row['frame_id']) ?>"><?= e($row['description']) ?></a>
                                <?php else: ?>
                                    <?= e($row['description']) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty"><h3>No transactions yet</h3><p>Credits you receive and spend will be listed here.</p></div>
    <?php endif; ?>
</div>
