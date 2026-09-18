<?php
/** One customer: their details and everything made for them. */
$cid = (int)$customer['id'];
$actions = [];
if (!empty($partner['allow_singles'])) {
    $actions[] = '<a class="btn btn-ghost" href="' . url($base . '/singles/create?customer=' . $cid) . '">+ Single</a>';
}
if (!empty($partner['allow_albums'])) {
    $actions[] = '<a class="btn btn-ghost" href="' . url($base . '/albums/create?customer=' . $cid) . '">+ Album</a>';
}
$headAction = $actions ? '<div class="toolbar">' . implode('', $actions) . '</div>' : null;
?>
<p style="margin:-8px 0 16px"><a class="btn-link" href="<?= url($base . '/customers') ?>">← All customers</a></p>

<div class="card">
    <div class="card-head"><h2>DEx content for <?= e($customer['name']) ?></h2></div>
    <?php if ($content): ?>
        <?php $rows = $content; require viewPath('partner/_content_table.php'); ?>
    <?php else: ?>
        <div class="empty">
            <h3>Nothing created yet</h3>
            <p>Use the buttons above to make a single or an album for this customer.</p>
        </div>
    <?php endif; ?>
</div>

<div class="card p-narrow">
    <div class="card-head"><h2>Details</h2></div>
    <form class="card-body" method="post" action="<?= url($base . '/customers/' . $cid) ?>">
        <?= csrfField() ?>
        <div class="grid-2">
            <div class="field">
                <label for="c-name">Name *</label>
                <input type="text" id="c-name" name="name" value="<?= e($customer['name']) ?>" required maxlength="120">
            </div>
            <div class="field">
                <label for="c-phone">Mobile</label>
                <input type="tel" id="c-phone" name="phone" value="<?= e($customer['phone']) ?>" maxlength="20">
            </div>
            <div class="field">
                <label for="c-email">Email</label>
                <input type="email" id="c-email" name="email" value="<?= e($customer['email']) ?>" maxlength="180">
            </div>
            <div class="field">
                <label for="c-ref">Bill / order no.</label>
                <input type="text" id="c-ref" name="reference" value="<?= e($customer['reference']) ?>" maxlength="80">
            </div>
        </div>
        <div class="field">
            <label for="c-notes">Notes</label>
            <textarea id="c-notes" name="notes"><?= e($customer['notes']) ?></textarea>
        </div>
        <button class="btn" type="submit">Save</button>
        <span class="muted small" style="margin-left:10px">Added <?= e(date('d M Y', strtotime($customer['created_at']))) ?></span>
    </form>
</div>
