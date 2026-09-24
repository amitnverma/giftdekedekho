<?php
/**
 * Video-duration and validity surcharge tables, shared by the partner form and
 * Sign-up plans & pricing. Posts duration_prices / duration_offered and
 * validity_prices / validity_offered, read by AdminArPartnerController::pricingInput().
 *
 * Expects $durationPrices and $validityPrices (offered options => credits). An
 * unticked row shows the current default price, ready to tick.
 */
$_defaultPlan = ArPartner::signupPlan();
?>
        <div class="admin-form-row" style="align-items:flex-start">
            <div style="flex:1">
                <p class="admin-section-title" style="margin:6px 0">Video duration surcharge</p>
                <table class="admin-table">
                    <thead><tr><th>Offer</th><th>Length</th><th>+ Credits</th></tr></thead>
                    <tbody>
                        <?php foreach (ArPartner::DURATIONS as $seconds):
                            $offered = array_key_exists($seconds, $durationPrices);
                            $price = $offered ? $durationPrices[$seconds] : ($_defaultPlan['duration_prices'][(string)$seconds] ?? ArPartner::DEFAULT_DURATION_PRICES[(string)$seconds] ?? 0); ?>
                            <tr>
                                <td><input type="checkbox" name="duration_offered[<?= $seconds ?>]" value="1" <?= $offered ? 'checked' : '' ?> aria-label="Offer <?= $seconds ?>s"></td>
                                <td><?= $seconds ?>s</td>
                                <td><input type="number" name="duration_prices[<?= $seconds ?>]" min="0" value="<?= (int)$price ?>" style="width:100px"></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="flex:1">
                <p class="admin-section-title" style="margin:6px 0">Validity surcharge</p>
                <table class="admin-table">
                    <thead><tr><th>Offer</th><th>Active for</th><th>+ Credits</th></tr></thead>
                    <tbody>
                        <?php foreach (ArPartner::VALIDITIES as $key => [$label]):
                            $offered = array_key_exists($key, $validityPrices);
                            $price = $offered ? $validityPrices[$key] : ($_defaultPlan['validity_prices'][$key] ?? ArPartner::DEFAULT_VALIDITY_PRICES[$key] ?? 0); ?>
                            <tr>
                                <td><input type="checkbox" name="validity_offered[<?= e($key) ?>]" value="1" <?= $offered ? 'checked' : '' ?> aria-label="Offer <?= e($label) ?>"></td>
                                <td><?= e($label) ?></td>
                                <td><input type="number" name="validity_prices[<?= e($key) ?>]" min="0" value="<?= (int)$price ?>" style="width:100px"></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

