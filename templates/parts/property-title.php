<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php
if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$propertyCode = !empty($show_code)
    ? \Homlity\PluginInmobiliario\Services\PropertyCodeResolver::forDisplay((int) $post_id)
    : '';

$operation = '';
if (!empty($show_operation)) {
    $operationTerms = get_the_terms((int) $post_id, \Homlity\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_OPERATION);
    if ($operationTerms && !is_wp_error($operationTerms)) {
        $operation = implode(', ', wp_list_pluck($operationTerms, 'name'));
    }
}
?>
<div class="property-title-widget">
    <?php echo esc_html(get_the_title($post_id)); ?>
    <?php if ($operation !== '') : ?>
        <span class="property-title-widget__operation">
            <?php echo esc_html($operation); ?>
        </span>
    <?php endif; ?>
    <?php if ($propertyCode !== '') : ?>
        <span class="property-title-widget__code">
            <?php echo esc_html(sprintf(__('Código: %s', 'homlity-real-estate'), $propertyCode)); ?>
        </span>
    <?php endif; ?>
</div>
