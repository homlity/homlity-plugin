<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php
if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$propertyCode = !empty($show_code)
    ? \Homlity\PluginInmobiliario\Services\PropertyCodeResolver::forDisplay((int) $post_id)
    : '';
?>
<div class="property-title-widget">
    <?php echo esc_html(get_the_title($post_id)); ?>
    <?php if ($propertyCode !== '') : ?>
        <span class="property-title-widget__code">
            <?php echo esc_html(sprintf(__('Código: %s', 'homlity-real-estate'), $propertyCode)); ?>
        </span>
    <?php endif; ?>
</div>
