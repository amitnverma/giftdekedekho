<?php
/** Change the signed-in login's own password. */
?>
<form class="p-narrow" method="post" action="<?= url($base . '/password') ?>" style="max-width:460px">
    <?= csrfField() ?>
    <div class="card">
        <div class="card-body">
            <p class="hint" style="margin-top:0">Signed in as <strong><?= e($partnerUser['email']) ?></strong>. Changing your password signs you out everywhere else.</p>
            <div class="field">
                <label for="current_password">Current password</label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
            </div>
            <div class="field">
                <label for="password">New password <span class="muted">(at least <?= PASSWORD_MIN_LENGTH ?> characters)</span></label>
                <input type="password" id="password" name="password" autocomplete="new-password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required>
            </div>
            <div class="field" style="margin-bottom:0">
                <label for="password_confirm">Repeat new password</label>
                <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required>
            </div>
        </div>
        <div class="card-foot">
            <span class="hint">Forgot it? Sign out, then use “Forgot your password?”.</span>
            <button class="btn" type="submit">Change password</button>
        </div>
    </div>
</form>
