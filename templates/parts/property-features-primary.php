<?php
/**
 * Primary features component.
 * Overridable at homlity-plugin/parts/property-features-primary.php
 *
 * Expected args: $post_id (int)
 */

use Homlity\PluginInmobiliario\Services\PropertyPostType;

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$meta = (new PropertyPostType())->metaKeys();
?>
<section class="property-features property-features--primary">
    <h2><?php esc_html_e('Características principales', 'homlity-plugin'); ?></h2>
    <ul>
        <li><strong><?php esc_html_e('Área total', 'homlity-plugin'); ?>:</strong> <?php echo esc_html(get_post_meta($post_id, $meta['area'], true)); ?> m²</li>
        <li><strong><?php esc_html_e('Área de lote', 'homlity-plugin'); ?>:</strong> <?php echo esc_html(get_post_meta($post_id, $meta['area_lot'], true)); ?> m²</li>
        <li><strong><?php esc_html_e('Área privada', 'homlity-plugin'); ?>:</strong> <?php echo esc_html(get_post_meta($post_id, $meta['area_private'], true)); ?> m²</li>
        <li><strong><?php esc_html_e('Área construida', 'homlity-plugin'); ?>:</strong> <?php echo esc_html(get_post_meta($post_id, $meta['area_built'], true)); ?> m²</li>
        <li><strong><?php esc_html_e('Habitaciones', 'homlity-plugin'); ?>:</strong> <?php echo esc_html(get_post_meta($post_id, $meta['bedrooms'], true)); ?></li>
        <li><strong><?php esc_html_e('Baños', 'homlity-plugin'); ?>:</strong> <?php echo esc_html(get_post_meta($post_id, $meta['bathrooms'], true)); ?></li>
        <li><strong><?php esc_html_e('Parqueaderos', 'homlity-plugin'); ?>:</strong> <?php echo esc_html(get_post_meta($post_id, $meta['parking'], true)); ?></li>
        <li><strong><?php esc_html_e('Estado', 'homlity-plugin'); ?>:</strong> <?php echo esc_html(get_post_meta($post_id, $meta['condition'], true)); ?></li>
        <li><strong><?php esc_html_e('Edad (años)', 'homlity-plugin'); ?>:</strong> <?php echo esc_html(get_post_meta($post_id, $meta['age'], true)); ?></li>
        <li><strong><?php esc_html_e('Código de la propiedad', 'homlity-plugin'); ?>:</strong> <?php echo esc_html(get_post_meta($post_id, $meta['code'], true)); ?></li>
        <li><strong><?php esc_html_e('Dirección', 'homlity-plugin'); ?>:</strong> <?php echo esc_html(get_post_meta($post_id, $meta['address'], true)); ?></li>
    </ul>
</section>
