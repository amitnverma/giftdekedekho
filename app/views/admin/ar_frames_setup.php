<?php
/**
 * Shown when the ar_frames table has not been created yet.
 *
 * Deployment ships code without running migrations, so this state is expected
 * on a fresh deploy rather than exceptional. Says exactly what to run instead of
 * leaving an SQL error on screen.
 */
?>
<div class="admin-alert admin-alert-error">
    <strong>Setup not finished — the AR frames table does not exist yet.</strong>
</div>

<div class="admin-card admin-mt" style="max-width:760px">
    <h3 class="admin-card-title">1 · Create the database table</h3>
    <p class="admin-help-text" style="margin-top:0">
        Deployment updates the code but does not run migrations. Run this once on the server,
        from the site directory:
    </p>
    <pre style="background:#0f1115;color:#c8e1ff;padding:14px 16px;border-radius:8px;overflow-x:auto;font-size:12.5px;line-height:1.6"><?= e($migrationCommand) ?></pre>
    <p class="admin-help-text">
        Then reload this page. Nothing else in the admin is affected while this is pending.
    </p>

    <hr class="admin-hr">

    <h3 class="admin-card-title">2 · Install the target compiler</h3>
    <?php if ($compiler['ok']): ?>
        <p style="margin-top:0"><span class="admin-badge admin-badge-green">Ready</span> <?= e($compiler['message']) ?></p>
    <?php else: ?>
        <p class="admin-help-text" style="margin-top:0">
            Turning a photo into an AR target needs the Node toolchain, which is not committed to
            the repository. Current status: <strong><?= e($compiler['message']) ?></strong>
        </p>
        <pre style="background:#0f1115;color:#c8e1ff;padding:14px 16px;border-radius:8px;overflow-x:auto;font-size:12.5px;line-height:1.6"><?= e($npmCommand) ?></pre>
        <p class="admin-help-text">
            Requires Node.js 18 or newer. See <code>tools/mindar-compile/README.txt</code> for the
            full notes, including what to do if <code>shell_exec</code> is disabled on this host.
        </p>
    <?php endif; ?>
</div>
