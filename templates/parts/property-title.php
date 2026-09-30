<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php
if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$propertyCode = !empty($show_code)
    ? \Homlity\PluginInmobiliario\Services\PropertyCodeResolver::forDisplay((int) $post_id)
    : '';

$operations = [];
if (!empty($show_operation)) {
    $operationTerms = get_the_terms((int) $post_id, \Homlity\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_OPERATION);
    if ($operationTerms && !is_wp_error($operationTerms)) {
        $operations = wp_list_pluck($operationTerms, 'name');
    }
}

// La gestión se integra en la frase ("Apartamento en venta en Chapinero")
// en lugar de quedar pegada al final del título.
[$titleBefore, $operationPhrase, $titleAfter] = \Homlity\PluginInmobiliario\Services\PropertyTitleComposer::split(
    (string) get_the_title($post_id),
    $operations
);
?>
<div class="property-title-widget">
    <?php echo esc_html($titleBefore); ?><?php if ($operationPhrase !== '') : ?><span class="property-title-widget__operation"><?php echo esc_html($operationPhrase); ?></span><?php endif; ?><?php echo esc_html($titleAfter); ?>
    <?php if ($propertyCode !== '') : ?>
        <span class="property-title-widget__code">
            <?php echo esc_html(sprintf(__('Código: %s', 'homlity-real-estate'), $propertyCode)); ?>
        </span>
    <?php endif; ?>
</div>
