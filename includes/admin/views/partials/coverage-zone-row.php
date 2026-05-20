<?php
/**
 * Partial: single coverage zone repeater row.
 *
 * Variables injected from the parent view:
 *   $idx  — row index (integer or '__IDX__' for the clone template)
 *   $zone — array with zone data
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$idx  = isset($idx) ? $idx : 0;
$zone = isset($zone) && is_array($zone) ? $zone : [];

$zf = static function (string $field) use ($idx): string {
    return 'homlity_seo[coverage_zones][' . $idx . '][' . esc_attr($field) . ']';
};
$zv = static function (string $key) use ($zone): string {
    return esc_attr($zone[$key] ?? '');
};
$zt = static function (string $key) use ($zone): string {
    return esc_textarea($zone[$key] ?? '');
};

$coverage_types = [
    'all'           => __('Todos', 'homlity-real-estate'),
    'venta'         => __('Venta', 'homlity-real-estate'),
    'arriendo'      => __('Arriendo', 'homlity-real-estate'),
    'administracion' => __('Administración', 'homlity-real-estate'),
    'avaluos'       => __('Avalúos', 'homlity-real-estate'),
    'captacion'     => __('Captación', 'homlity-real-estate'),
    'proyectos'     => __('Proyectos', 'homlity-real-estate'),
];
$priorities = [
    'alta'  => __('Alta', 'homlity-real-estate'),
    'media' => __('Media', 'homlity-real-estate'),
    'baja'  => __('Baja', 'homlity-real-estate'),
];
?>
<div class="homlity-repeater-header">
    <span class="homlity-repeater-title">
        <?php esc_html_e('Zona', 'homlity-real-estate'); ?>
        <strong class="homlity-zone-title-preview"><?php echo esc_html(implode(', ', array_filter([$zone['city'] ?? '', $zone['neighborhood'] ?? '']))); ?></strong>
    </span>
    <label class="homlity-zone-active-toggle">
        <input type="checkbox" name="<?php echo esc_attr($zf('active')); ?>" value="1"<?php echo !empty($zone['active']) ? ' checked' : ''; ?>>
        <?php esc_html_e('Activa', 'homlity-real-estate'); ?>
    </label>
    <button type="button" class="button homlity-repeater-toggle"><?php esc_html_e('Colapsar', 'homlity-real-estate'); ?></button>
    <button type="button" class="button homlity-repeater-remove"><?php esc_html_e('Eliminar zona', 'homlity-real-estate'); ?></button>
</div>
<div class="homlity-repeater-body">
    <div class="homlity-zone-grid">
        <label>
            <?php esc_html_e('País', 'homlity-real-estate'); ?>
            <input type="text" name="<?php echo esc_attr($zf('country')); ?>" value="<?php echo esc_attr($zv('country')); ?>" class="regular-text">
        </label>
        <label>
            <?php esc_html_e('Departamento / Provincia', 'homlity-real-estate'); ?>
            <input type="text" name="<?php echo esc_attr($zf('state')); ?>" value="<?php echo esc_attr($zv('state')); ?>" class="regular-text">
        </label>
        <label>
            <?php esc_html_e('Ciudad', 'homlity-real-estate'); ?>
            <input type="text" name="<?php echo esc_attr($zf('city')); ?>" value="<?php echo esc_attr($zv('city')); ?>" class="regular-text homlity-zone-city-input">
        </label>
        <label>
            <?php esc_html_e('Barrio / Zona', 'homlity-real-estate'); ?>
            <input type="text" name="<?php echo esc_attr($zf('neighborhood')); ?>" value="<?php echo esc_attr($zv('neighborhood')); ?>" class="regular-text homlity-zone-neighborhood-input">
        </label>
        <label>
            <?php esc_html_e('Tipo de cobertura', 'homlity-real-estate'); ?>
            <select name="<?php echo esc_attr($zf('coverage_type')); ?>">
                <?php foreach ($coverage_types as $val => $label): ?>
                    <option value="<?php echo esc_attr($val); ?>"<?php selected($zone['coverage_type'] ?? 'all', $val); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <?php esc_html_e('Prioridad SEO', 'homlity-real-estate'); ?>
            <select name="<?php echo esc_attr($zf('priority')); ?>">
                <?php foreach ($priorities as $val => $label): ?>
                    <option value="<?php echo esc_attr($val); ?>"<?php selected($zone['priority'] ?? 'media', $val); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <?php esc_html_e('Latitud', 'homlity-real-estate'); ?>
            <input type="text" name="<?php echo esc_attr($zf('latitude')); ?>" value="<?php echo esc_attr($zv('latitude')); ?>" class="small-text">
        </label>
        <label>
            <?php esc_html_e('Longitud', 'homlity-real-estate'); ?>
            <input type="text" name="<?php echo esc_attr($zf('longitude')); ?>" value="<?php echo esc_attr($zv('longitude')); ?>" class="small-text">
        </label>
        <label>
            <?php esc_html_e('Radio (km)', 'homlity-real-estate'); ?>
            <input type="number" name="<?php echo esc_attr($zf('radius')); ?>" value="<?php echo esc_attr($zv('radius')); ?>" class="small-text" min="0">
        </label>
        <label>
            <?php esc_html_e('Slug SEO', 'homlity-real-estate'); ?>
            <input type="text" name="<?php echo esc_attr($zf('seo_slug')); ?>" value="<?php echo esc_attr($zv('seo_slug')); ?>" class="regular-text">
        </label>
    </div>
    <label class="homlity-zone-desc-label">
        <?php esc_html_e('Descripción SEO de la zona', 'homlity-real-estate'); ?>
        <textarea name="<?php echo esc_attr($zf('description')); ?>" rows="2" class="large-text"><?php echo esc_textarea($zt('description')); ?></textarea>
    </label>
</div>
