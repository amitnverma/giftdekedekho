<?php /** The partner's own customer list, with the add form below it. */ ?>
<div class="card">
    <div class="card-head">
        <h2>Customers <span class="muted small">(<?= count($customers) ?>)</span></h2>
        <form class="toolbar" method="get">
            <input type="search" name="search" value="<?= e($search) ?>" placeholder="Search name, phone, email or bill no." aria-label="Search">
            <button class="btn btn-ghost btn-sm" type="submit">Search</button>
            <a class="btn btn-sm" href="#add">+ Add Customer</a>
        </form>
    </div>
    <?php if ($customers): ?>
        <div class="table-scroll">
            <table class="p-table">
                <thead>
                    <tr><th>Name</th><th>Phone</th><th>Email</th><th>Bill / order no.</th><th class="num">DEx items</th><th>Added</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td><a href="<?= url($base . '/customers/' . (int)$c['id']) ?>"><?= e($c['name']) ?></a></td>
                            <td><?= e($c['phone'] ?? '—') ?></td>
                            <td><?= e($c['email'] ?? '—') ?></td>
                            <td><?= e($c['reference'] ?? '—') ?></td>
                            <td class="num"><?= (int)$c['content_count'] ?></td>
                            <td class="muted"><?= e(date('d M Y', strtotime($c['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty">
            <h3><?= $search !== '' ? 'No customer matches that search' : 'No customers yet' ?></h3>
            <p>Add the people you make DEx gifts for, then create singles and albums for them.</p>
        </div>
    <?php endif; ?>
</div>

<div class="card p-narrow" id="add">
    <div class="card-head"><h2>Add customer</h2></div>
    <form class="card-body" method="post" action="<?= url($base . '/customers') ?>">
        <?= csrfField() ?>
        <div class="grid-2">
            <div class="field">
                <label for="c-name">Name *</label>
                <input type="text" id="c-name" name="name" value="<?= old('name') ?>" required maxlength="120">
            </div>
            <div class="field">
                <label for="c-phone">Mobile</label>
                <input type="tel" id="c-phone" name="phone" value="<?= old('phone') ?>" maxlength="20" placeholder="For sending the DEx link on WhatsApp">
            </div>
            <div class="field">
                <label for="c-email">Email</label>
                <input type="email" id="c-email" name="email" value="<?= old('email') ?>" maxlength="180">
            </div>
            <div class="field">
                <label for="c-ref">Bill / order no.</label>
                <input type="text" id="c-ref" name="reference" value="<?= old('reference') ?>" maxlength="80">
            </div>
        </div>
        <div class="field">
            <label for="c-notes">Notes</label>
            <textarea id="c-notes" name="notes"><?= old('notes') ?></textarea>
        </div>
        <button class="btn" type="submit">Save customer</button>
    </form>
</div>
