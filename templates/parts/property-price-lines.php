<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Shared price contents for card and hero wrappers.
 * Overridable at homlity-real-estate/parts/property-price-lines.php.
 *
 * Expected args: $post_id, $price_lines, $price_class_prefix (optional),
 * $price_bootstrap (optional). The caller retains the styled price wrapper.
 */

$pricePrefix = $price_class_prefix ?? 'property-card';
$isMultiPrice = count($price_lines) > 1;
$adminClasses = $pricePrefix . '__price-admin' . (!empty($price_bootstrap) ? ' fs-6 fw-normal text-muted' : '');
?>
<?php foreach ($price_lines as $priceLine): ?>
    <?php if ($isMultiPrice): ?>
        <span class="<?php echo esc_html($pricePrefix); ?>__price-line <?php echo esc_html($pricePrefix . '__price-line--' . $priceLine['type']); ?>">
            <span class="<?php echo esc_html($pricePrefix); ?>__price-label"><?php echo esc_html($priceLine['label']); ?></span>
            <span class="<?php echo esc_html($pricePrefix); ?>__price-amount"><?php echo esc_html($priceLine['amount']); ?></span>
    <?php else: ?>
        <?php echo esc_html($priceLine['amount']); ?>
    <?php endif; ?>
    <?php if ($priceLine['type'] === 'rent' && ($priceLine['admin_included'] || $priceLine['admin'])): ?>
        <small class="<?php echo esc_html($adminClasses); ?>" aria-label="<?php echo esc_html(__('Administración', 'homlity-real-estate')); ?>">
            <?php if ($priceLine['admin_included']): ?>
                <?php echo esc_html(__('adm. incluida', 'homlity-real-estate')); ?>
            <?php else: ?>
                + <?php echo esc_html($priceLine['admin']); ?> <?php echo esc_html(__('adm.', 'homlity-real-estate')); ?>
            <?php endif; ?>
        </small>
    <?php endif; ?>
    <?php if ($isMultiPrice): ?>
        </span>
    <?php endif; ?>
<?php endforeach; ?>
