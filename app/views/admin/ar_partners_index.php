<?php /** Admin → AR Partners: every partner, with balance, activity and pending credit requests. */
$awaitingCount = count(array_filter($partners, fn($p) => ArPartner::awaitingActivation($p)));
?>
<?php if ($awaitingCount): ?>
    <div class="admin-alert" style="margin-bottom:16px;background:#fffbeb;border:1px solid #fcd34d;color:#92400e">
        <strong><?= $awaitingCount ?> seller registration<?= $awaitingCount === 1 ? '' : 's' ?> waiting for activation.</strong>
        Open <?= $awaitingCount === 1 ? 'it' : 'each one' ?> once payment is received and press “Activate” — that adds the credits and lets them sign in.
    </div>
<?php endif; ?>
<div class="admin-flex-between">
    <p class="admin-muted" style="margin:0;font-size:13px;max-width:60em">
        B2B shops that sell Living Photo AR to their own customers from their own branded page.
        Their content never appears in AR Frame Orders or on <code>/scan</code>.
    </p>
    <a class="admin-btn admin-btn-primary" href="<?= url('/admin/ar-partners/create') ?>">+ New Partner</a>
</div>

<div class="admin-card admin-mt">
    <?php if (!$partners): ?>
        <p class="admin-muted" style="margin:0">No partners yet. Create one to give a shop its own AR page.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Partner</th>
                        <th>Page</th>
                        <th style="text-align:right">Credits</th>
                        <th style="text-align:right">Rate</th>
                        <th style="text-align:right">Content</th>
                        <th style="text-align:right">Customers</th>
                        <th style="text-align:right">Opens</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($partners as $p): ?>
                        <tr>
                            <td>
                                <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= e(ArPartnerService::safeColor($p['brand_color'])) ?>;margin-right:6px"></span>
                                <a href="<?= url('/admin/ar-partners/' . (int)$p['id']) ?>"><strong><?= e($p['name']) ?></strong></a>
                                <?php if ((int)$p['login_count'] === 0): ?>
                                    <span class="admin-badge admin-badge-red" title="Nobody can sign in until a login is added">No login</span>
                                <?php endif; ?>
                                <?php if ((int)$p['pending_requests'] > 0): ?>
                                    <span class="admin-badge admin-badge-yellow"><?= (int)$p['pending_requests'] ?> credit request<?= (int)$p['pending_requests'] === 1 ? '' : 's' ?></span>
                                <?php endif; ?>
                            </td>
                            <td><a href="<?= url('/partner/' . $p['slug']) ?>" target="_blank">/partner/<?= e($p['slug']) ?> ↗</a></td>
                            <td style="text-align:right"><?= number_format((int)$p['credit_balance']) ?></td>
                            <td style="text-align:right"><?= number_format((int)$p['base_credits']) ?></td>
                            <td style="text-align:right"><?= number_format((int)$p['content_count']) ?></td>
                            <td style="text-align:right"><?= number_format((int)$p['customer_count']) ?></td>
                            <td style="text-align:right"><?= number_format((int)$p['opens']) ?></td>
                            <td>
                                <?php if ($p['is_active'] && !empty($p['is_trial'])): ?>
                                    <span class="admin-badge admin-badge-yellow" title="What they create is deleted automatically">Free trial</span>
                                <?php elseif ($p['is_active']): ?>
                                    <span class="admin-badge admin-badge-green">Active</span>
                                <?php elseif (ArPartner::awaitingActivation($p)): ?>
                                    <span class="admin-badge admin-badge-yellow">Awaiting activation</span>
                                <?php elseif (($p['signup_status'] ?? null) === 'rejected'): ?>
                                    <span class="admin-badge admin-badge-red">Declined</span>
                                <?php else: ?>
                                    <span class="admin-badge admin-badge-gray">Paused</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="admin-card admin-mt" style="max-width:620px" id="trial">
    <h3 class="admin-card-title">Sign-up plans &amp; pricing</h3>
    <p class="admin-muted" style="font-size:13px;margin-top:0">
        What <a href="<?= url('/partner/register') ?>" target="_blank">/partner/register ↗</a> offers — the credit packs, the highlighted pack,
        the free trial and their wording — and the pricing new partners start with.
    </p>
    <ul style="margin:0 0 12px;padding-left:18px;font-size:13.5px">
        <li><?= count($offer['packs']) ?> credit pack<?= count($offer['packs']) === 1 ? '' : 's' ?>:
            <?= e(implode(', ', array_map(fn($p) => GDD_CURRENCY_SYMBOL . number_format($p['price']), $offer['packs']))) ?></li>
        <li>Base rate: <?= number_format($offer['base_credits']) ?> credits per item</li>
        <li>Free trial:
            <?php if (!$trialsReady): ?>not available until its migration is run
            <?php elseif ($trial['enabled']): ?>on — <?= number_format($trial['credits']) ?> credits, content deleted after <?= (int)$trial['days'] ?> day<?= (int)$trial['days'] === 1 ? '' : 's' ?>
            <?php else: ?>off<?php endif; ?></li>
    </ul>
    <a class="admin-btn admin-btn-primary" href="<?= url('/admin/ar-partners/plans') ?>">Edit sign-up plans &amp; pricing</a>
</div>

<div class="admin-card admin-mt" style="max-width:620px">
    <h3 class="admin-card-title">Credit requests reach you on WhatsApp</h3>
    <p class="admin-muted" style="font-size:13px;margin-top:0">
        Partners pay for credits outside the site. Their “Buy Credits” button opens WhatsApp to this number, and each
        request also waits on the partner's page here until you mark it paid.
    </p>
    <form method="post" action="<?= url('/admin/ar-partners/settings') ?>" class="admin-form">
        <?= csrfField() ?>
        <label>WhatsApp number (with country code, e.g. 919876543210)
            <input type="text" name="ar_partner_support_whatsapp" value="<?= e($supportWhatsapp) ?>" inputmode="tel">
        </label>
        <div class="admin-form-actions">
            <button class="admin-btn admin-btn-primary" type="submit">Save</button>
        </div>
    </form>
</div>
