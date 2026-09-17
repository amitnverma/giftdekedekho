<?php /** Admin → AR Partners: every partner, with balance, activity and pending credit requests. */ ?>
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
                                <span class="admin-badge <?= $p['is_active'] ? 'admin-badge-green' : 'admin-badge-gray' ?>"><?= $p['is_active'] ? 'Active' : 'Paused' ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
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
