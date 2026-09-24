<?php
/** One partner: headline numbers, credit requests and balance, logins, and their content. */
$pid = (int)$partner['id'];
$pending = array_values(array_filter($requests, fn($r) => $r['status'] === 'pending'));
$contentState = ['live' => 'admin-badge-green', 'warn' => 'admin-badge-yellow', 'off' => 'admin-badge-gray'];
$activeLogins = array_filter($users, fn($u) => !empty($u['is_active']));
$awaiting = ArPartner::awaitingActivation($partner);
// The pack chosen at registration is the oldest request still open.
$signupRequest = $pending ? end($pending) : null;
$statusBadge = $partner['is_active'] ? ($trial ? ['admin-badge-yellow', 'Free trial'] : ['admin-badge-green', 'Active'])
    : ($awaiting ? ['admin-badge-yellow', 'Awaiting activation']
    : (($partner['signup_status'] ?? null) === 'rejected' ? ['admin-badge-red', 'Declined'] : ['admin-badge-gray', 'Paused']));
?>
<?php if ($awaiting): ?>
    <div class="admin-card" style="margin-bottom:16px;border:2px solid #f59e0b;background:#fffbeb">
        <h3 class="admin-card-title" style="margin-top:0">New seller registration — waiting for payment</h3>
        <p style="margin:0 0 6px">
            <strong><?= e($partner['contact_name'] ?: $partner['name']) ?></strong> registered
            <strong><?= e($partner['name']) ?></strong> on <?= e(date('d M Y, H:i', strtotime($partner['created_at']))) ?>.
            <?php if ($signupRequest): ?>
                They chose the <strong><?= e(GDD_CURRENCY_SYMBOL . number_format((int)$signupRequest['price'])) ?></strong> pack
                for <strong><?= number_format((int)$signupRequest['credits']) ?> credits</strong>.
            <?php endif; ?>
        </p>
        <p class="admin-muted" style="font-size:13px;margin:0 0 12px">
            <?= e(implode(' · ', array_filter([$partner['contact_phone'], $partner['contact_email']]))) ?>
            <?php if (!empty($partner['whatsapp'])): ?>
                · <a href="https://wa.me/<?= e(preg_replace('/\D/', '', $partner['whatsapp'])) ?>" target="_blank" rel="noopener">WhatsApp them ↗</a>
            <?php endif; ?>
            <br>They cannot sign in until you activate the account. Check the name and page address under
            “Edit branding &amp; pricing” first if you want to change them.
        </p>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?php if ($signupRequest): ?>
                <form method="post" action="<?= url('/admin/ar-partners/' . $pid . '/activate') ?>"
                      onsubmit="return confirm('Payment of <?= e(GDD_CURRENCY_SYMBOL . number_format((int)$signupRequest['price'])) ?> received? This activates the account and adds <?= (int)$signupRequest['credits'] ?> credits.')">
                    <?= csrfField() ?>
                    <input type="hidden" name="request_id" value="<?= (int)$signupRequest['id'] ?>">
                    <button class="admin-btn admin-btn-primary" type="submit">Payment received — activate &amp; add <?= number_format((int)$signupRequest['credits']) ?> credits</button>
                </form>
            <?php endif; ?>
            <form method="post" action="<?= url('/admin/ar-partners/' . $pid . '/activate') ?>"
                  onsubmit="return confirm('Activate without adding credits? You can add credits by hand below.')">
                <?= csrfField() ?>
                <button class="admin-btn" type="submit">Activate without credits</button>
            </form>
            <form method="post" action="<?= url('/admin/ar-partners/' . $pid . '/reject') ?>"
                  onsubmit="return confirm('Decline this registration? They will not be able to sign in.')">
                <?= csrfField() ?>
                <button class="admin-btn admin-btn-danger" type="submit">Decline</button>
            </form>
        </div>
    </div>
<?php endif; ?>
<?php
// Trial content still waiting to be deleted — it can be rescued after the trial ends too.
$trialContent = array_filter($content, fn($row) => !empty($row['delete_after']));
?>
<?php if ($trial || $trialContent): ?>
    <div class="admin-card" style="margin-bottom:16px;border:2px solid #f59e0b;background:#fffbeb">
        <h3 class="admin-card-title" style="margin-top:0"><?= $trial ? 'Free trial account' : 'Trial content still scheduled for deletion' ?></h3>
        <p style="margin:0 0 10px;font-size:13.5px">
            <?php if ($trial): ?>
                Everything they create is deleted automatically <?= (int)$trial['days'] ?> day<?= (int)$trial['days'] === 1 ? '' : 's' ?> after it is made.
                Marking a credit request paid ends the trial; so does the button below.
            <?php endif; ?>
            <?= count($trialContent) ?> piece<?= count($trialContent) === 1 ? '' : 's' ?> of trial content
            <?= count($trialContent) === 1 ? 'is' : 'are' ?> waiting to be deleted<?php if ($trialContent): ?>
                — the next on <?= e(date('d M Y, H:i', strtotime(min(array_column($trialContent, 'delete_after'))))) ?><?php endif; ?>.
        </p>
        <form method="post" action="<?= url('/admin/ar-partners/' . $pid . '/end-trial') ?>" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap"
              onsubmit="return confirm(this.keep_content && this.keep_content.checked ? 'Keep the trial content? It will stay live for its normal validity.' : '<?= $trial ? 'End the free trial?' : 'Keep nothing?' ?>')">
            <?= csrfField() ?>
            <?php if ($trialContent): ?>
                <label class="admin-checkbox" style="margin:0"><input type="checkbox" name="keep_content" value="1" <?= $trial ? '' : 'checked' ?>> Keep the trial content (stop it being deleted)</label>
            <?php endif; ?>
            <button class="admin-btn admin-btn-primary" type="submit"><?= $trial ? 'End trial' : 'Keep trial content' ?></button>
        </form>
    </div>
<?php endif; ?>
<?php if (!$activeLogins): ?>
    <div class="admin-alert admin-alert-error" style="margin-bottom:16px">
        <strong>Nobody can sign in to this partner's page.</strong> It has no active login —
        <a href="#logins">add one below</a> with the email the partner will sign in with.
    </div>
<?php endif; ?>
<div class="admin-flex-between">
    <div style="display:flex;gap:14px;align-items:center">
        <?php if (!empty($partner['logo_path'])): ?>
            <img src="<?= e(ArFrameService::fileUrl($partner['logo_path'])) ?>" alt="" style="max-height:44px;max-width:160px">
        <?php else: ?>
            <span style="width:44px;height:44px;border-radius:8px;background:<?= e(ArPartnerService::safeColor($partner['brand_color'])) ?>;display:inline-block"></span>
        <?php endif; ?>
        <div>
            <a href="<?= e($portalUrl) ?>" target="_blank"><?= e(preg_replace('#^https?://#', '', $portalUrl)) ?> ↗</a>
            <span class="admin-badge <?= $statusBadge[0] ?>"><?= e($statusBadge[1]) ?></span>
            <div class="admin-muted" style="font-size:12.5px">
                <?= e(implode(' · ', array_filter([$partner['contact_name'], $partner['contact_phone'], $partner['contact_email']]))) ?: 'No contact details' ?>
            </div>
        </div>
    </div>
    <div>
        <a class="admin-btn" href="<?= url('/admin/ar-partners') ?>">← All partners</a>
        <a class="admin-btn admin-btn-primary" href="<?= url('/admin/ar-partners/' . $pid . '/edit') ?>">Edit branding &amp; pricing</a>
    </div>
</div>

<div class="admin-grid admin-grid-4 admin-mt">
    <div class="admin-card"><p class="admin-kpi-label">Credit balance</p><p class="admin-kpi-value"><?= number_format((int)$partner['credit_balance']) ?></p><p class="admin-kpi-sub"><?= number_format((int)$partner['base_credits']) ?> per basic item</p></div>
    <div class="admin-card"><p class="admin-kpi-label">Content</p><p class="admin-kpi-value"><?= number_format($counts['singles'] + $counts['albums']) ?></p><p class="admin-kpi-sub"><?= $counts['singles'] ?> singles · <?= $counts['albums'] ?> albums · <?= $counts['pages'] ?> photos</p></div>
    <div class="admin-card"><p class="admin-kpi-label">Their customers</p><p class="admin-kpi-value"><?= number_format($customers) ?></p><p class="admin-kpi-sub">Separate from store customers</p></div>
    <div class="admin-card"><p class="admin-kpi-label">Scan opens</p><p class="admin-kpi-value"><?= number_format($scans['opens']) ?></p><p class="admin-kpi-sub"><?= number_format($scans['visitors']) ?> unique · <?= number_format($scans['today']) ?> today</p></div>
</div>

<div class="admin-grid admin-mt" id="credits" style="align-items:start;grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr))">
    <div class="admin-card">
        <h3 class="admin-card-title">Credit requests</h3>
        <?php if (!$requests): ?>
            <p class="admin-muted" style="margin:0">No requests yet.</p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Requested</th><th>Pack</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($requests as $r): ?>
                            <tr>
                                <td style="font-size:12.5px"><?= e(date('d M Y H:i', strtotime($r['created_at']))) ?><br><span class="admin-muted"><?= e($r['requested_by_name'] ?? '') ?></span></td>
                                <td><?= e(GDD_CURRENCY_SYMBOL . number_format((int)$r['price'])) ?> → <?= number_format((int)$r['credits']) ?></td>
                                <td><span class="admin-badge <?= ['pending' => 'admin-badge-yellow', 'fulfilled' => 'admin-badge-green', 'cancelled' => 'admin-badge-gray'][$r['status']] ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                                <td style="white-space:nowrap">
                                    <?php if ($r['status'] === 'pending'): ?>
                                        <form method="post" action="<?= url('/admin/ar-partners/' . $pid . '/requests/' . (int)$r['id'] . '/fulfil') ?>" style="display:inline"
                                              onsubmit="return confirm('Payment received? This adds <?= (int)$r['credits'] ?> credits.')">
                                            <?= csrfField() ?>
                                            <button class="admin-btn admin-btn-primary admin-btn-sm" type="submit">Paid — add credits</button>
                                        </form>
                                        <form method="post" action="<?= url('/admin/ar-partners/' . $pid . '/requests/' . (int)$r['id'] . '/cancel') ?>" style="display:inline">
                                            <?= csrfField() ?>
                                            <button class="admin-btn admin-btn-sm" type="submit">Cancel</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="admin-card">
        <h3 class="admin-card-title">Adjust credits</h3>
        <form method="post" action="<?= url('/admin/ar-partners/' . $pid . '/credits') ?>" class="admin-form">
            <?= csrfField() ?>
            <div class="admin-form-row">
                <label>Credits <span class="admin-label-hint">Negative to remove</span>
                    <input type="number" name="amount" required step="1">
                </label>
                <label>Reason <span class="admin-label-hint">The partner sees this</span>
                    <input type="text" name="description" maxlength="255" placeholder="e.g. Refund for re-print">
                </label>
            </div>
            <label class="admin-checkbox"><input type="checkbox" name="allow_negative" value="1"> Allow a negative balance</label>
            <div class="admin-form-actions"><button class="admin-btn admin-btn-primary" type="submit">Apply</button></div>
        </form>
        <?php if ($trialsReady && !$trial): ?>
            <form method="post" action="<?= url('/admin/ar-partners/' . $pid . '/start-trial') ?>" class="admin-form admin-mt"
                  style="border-top:1px solid #e5e7eb;padding-top:12px"
                  onsubmit="return confirm('Put <?= e(addslashes($partner['name'])) ?> on a free trial? Everything they create from now on is deleted automatically after <?= (int)$trialDefaults['days'] ?> days.')">
                <?= csrfField() ?>
                <p class="admin-muted" style="font-size:13px;margin:0 0 6px">
                    <strong>Put on free trial</strong> — content they create from now on is deleted after <?= (int)$trialDefaults['days'] ?> days. Content they already have is not affected.
                </p>
                <div class="admin-form-row">
                    <label>Trial credits to add <input type="number" name="credits" min="0" value="<?= (int)$trialDefaults['credits'] ?>"></label>
                </div>
                <div class="admin-form-actions"><button class="admin-btn" type="submit">Start free trial</button></div>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="admin-card admin-mt" id="logins">
    <h3 class="admin-card-title">Logins</h3>
    <p class="admin-muted" style="font-size:13px;margin-top:0">
        Each person signs in at <?= e(preg_replace('#^https?://#', '', $portalUrl)) ?> with their login email. “Forgot password”
        on that page emails a reset link to the login email, so keep it correct. To set a password yourself, type it under
        New password and press Save.
    </p>
    <?php if ($users): ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Last sign-in</th><th>Active</th><th>New password</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($users as $u): $formId = 'user-' . (int)$u['id']; ?>
                        <tr>
                            <td><?= e($u['name']) ?></td>
                            <td><input type="email" name="email" form="<?= $formId ?>" value="<?= e($u['email']) ?>" required maxlength="180" aria-label="Login email" style="width:220px"></td>
                            <td>
                                <select name="role" form="<?= $formId ?>">
                                    <?php foreach (ArPartnerUser::ROLES as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= $u['role'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="admin-muted" style="font-size:12.5px"><?= $u['last_login_at'] ? e(timeAgo($u['last_login_at'])) : 'Never' ?></td>
                            <td><input type="checkbox" name="is_active" value="1" form="<?= $formId ?>" <?= $u['is_active'] ? 'checked' : '' ?> aria-label="Active"></td>
                            <td><input type="text" name="password" form="<?= $formId ?>" placeholder="Leave blank to keep" autocomplete="new-password" style="width:150px"></td>
                            <td>
                                <form id="<?= $formId ?>" method="post" action="<?= url('/admin/ar-partners/' . $pid . '/users/' . (int)$u['id']) ?>">
                                    <?= csrfField() ?>
                                    <button class="admin-btn admin-btn-sm" type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="admin-muted">No logins yet — the partner cannot sign in until you add one.</p>
    <?php endif; ?>

    <form method="post" action="<?= url('/admin/ar-partners/' . $pid . '/users') ?>" class="admin-form admin-mt">
        <?= csrfField() ?>
        <div class="admin-form-row">
            <label>Name <input type="text" name="name" required maxlength="120"></label>
            <label>Login email <input type="email" name="email" required maxlength="180" autocomplete="off"></label>
            <label>Password <input type="text" name="password" required minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password"></label>
            <label>Role
                <select name="role">
                    <?php foreach (ArPartnerUser::ROLES as $key => $label): ?>
                        <option value="<?= e($key) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="admin-form-actions"><button class="admin-btn admin-btn-primary" type="submit">+ Add login</button></div>
    </form>
</div>

<div class="admin-grid admin-mt" style="align-items:start;grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr))">
    <div class="admin-card">
        <h3 class="admin-card-title">Content <span class="admin-muted" style="font-weight:400">(latest 100)</span></h3>
        <?php if (!$content): ?>
            <p class="admin-muted" style="margin:0">Nothing created yet.</p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Title</th><th>Customer</th><th>Status</th><th style="text-align:right">Credits</th><th style="text-align:right">Opens</th></tr></thead>
                    <tbody>
                        <?php foreach ($content as $row): [$label, $tone] = ArPartnerService::contentState($row); ?>
                            <tr>
                                <td>
                                    <a href="<?= url('/admin/ar-frames/' . (int)$row['id']) ?>"><?= e($row['title'] ?: $row['slug']) ?></a>
                                    <div class="admin-muted" style="font-size:12px"><?= $row['content_kind'] === 'album' ? 'Album · ' . (int)$row['item_count'] : 'Single' ?> · <?= e(date('d M Y', strtotime($row['created_at']))) ?></div>
                                </td>
                                <td><?= e($row['customer_name'] ?? '—') ?></td>
                                <td>
                                    <span class="admin-badge <?= $contentState[$tone] ?>"><?= e($label) ?></span>
                                    <?php if (!empty($row['delete_after'])): ?>
                                        <div style="font-size:12px;color:#b91c1c">Trial · deleted <?= e(date('d M, H:i', strtotime($row['delete_after']))) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right"><?= number_format((int)$row['credits_charged']) ?></td>
                                <td style="text-align:right"><?= number_format((int)$row['opens']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="admin-card">
        <h3 class="admin-card-title">Credit history <span class="admin-muted" style="font-weight:400">(latest 50)</span></h3>
        <?php if (!$ledger): ?>
            <p class="admin-muted" style="margin:0">No transactions yet.</p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Date</th><th style="text-align:right">Amount</th><th style="text-align:right">Balance</th><th>Description</th></tr></thead>
                    <tbody>
                        <?php foreach ($ledger as $row): $amount = (int)$row['amount']; ?>
                            <tr>
                                <td style="font-size:12.5px;white-space:nowrap"><?= e(date('d M Y H:i', strtotime($row['created_at']))) ?></td>
                                <td style="text-align:right;color:<?= $amount < 0 ? '#b91c1c' : '#166534' ?>;font-weight:600"><?= $amount > 0 ? '+' : '' ?><?= number_format($amount) ?></td>
                                <td style="text-align:right"><?= number_format((int)$row['balance_after']) ?></td>
                                <td style="font-size:13px"><?= e($row['description']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
