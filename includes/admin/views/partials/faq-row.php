<?php
/**
 * Partial: single FAQ repeater row.
 *
 * Variables injected from parent view:
 *   $idx — row index (integer or '__IDX__' for clone template)
 *   $faq — array with FAQ data
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$idx = isset($idx) ? $idx : 0;
$faq = isset($faq) && is_array($faq) ? $faq : [];

$ff = static function (string $field) use ($idx): string {
    return 'homlity_seo[global_faqs][' . $idx . '][' . esc_attr($field) . ']';
};
$fv = static function (string $key) use ($faq): string {
    return esc_attr($faq[$key] ?? '');
};
$ft = static function (string $key) use ($faq): string {
    return esc_textarea($faq[$key] ?? '');
};

$categories = [
    'general'         => __('General', 'homlity-real-estate'),
    'arriendos'       => __('Arriendos', 'homlity-real-estate'),
    'ventas'          => __('Ventas', 'homlity-real-estate'),
    'compra'          => __('Compra de inmueble', 'homlity-real-estate'),
    'venta_inmueble'  => __('Venta de inmueble', 'homlity-real-estate'),
    'administracion'  => __('Administración de inmuebles', 'homlity-real-estate'),
    'avaluos'         => __('Avalúos', 'homlity-real-estate'),
    'requisitos'      => __('Requisitos', 'homlity-real-estate'),
    'financiacion'    => __('Financiación', 'homlity-real-estate'),
    'servicios'       => __('Servicios', 'homlity-real-estate'),
    'otra'            => __('Otra', 'homlity-real-estate'),
];

$show_on_options = [
    'all'      => __('Todas las páginas', 'homlity-real-estate'),
    'home'     => __('Página principal', 'homlity-real-estate'),
    'property' => __('Páginas de inmuebles', 'homlity-real-estate'),
    'zone'     => __('Páginas de barrio/zona', 'homlity-real-estate'),
    'contact'  => __('Página de contacto', 'homlity-real-estate'),
];
?>
<div class="homlity-repeater-header">
    <span class="homlity-repeater-title homlity-faq-title-preview">
        <?php echo !empty($faq['question']) ? esc_html(wp_trim_words($faq['question'], 10)) : esc_html__('Nueva pregunta', 'homlity-real-estate'); ?>
    </span>
    <label class="homlity-faq-active-toggle">
        <input type="checkbox" name="<?php echo esc_attr($ff('active')); ?>" value="1"<?php echo !empty($faq['active']) ? ' checked' : ''; ?>>
        <?php esc_html_e('Activa', 'homlity-real-estate'); ?>
    </label>
    <label class="homlity-faq-schema-toggle">
        <input type="checkbox" name="<?php echo esc_attr($ff('schema')); ?>" value="1"<?php echo !empty($faq['schema']) ? ' checked' : ''; ?>>
        <?php esc_html_e('Schema', 'homlity-real-estate'); ?>
    </label>
    <button type="button" class="button homlity-repeater-toggle"><?php esc_html_e('Colapsar', 'homlity-real-estate'); ?></button>
    <button type="button" class="button homlity-repeater-remove"><?php esc_html_e('Eliminar', 'homlity-real-estate'); ?></button>
</div>
<div class="homlity-repeater-body">
    <div class="homlity-faq-grid">
        <label class="homlity-faq-question-label">
            <?php esc_html_e('Pregunta', 'homlity-real-estate'); ?>
            <input type="text" name="<?php echo esc_attr($ff('question')); ?>" value="<?php echo esc_attr($fv('question')); ?>" class="large-text homlity-faq-question-input">
        </label>
        <div class="homlity-faq-meta-row">
            <label>
                <?php esc_html_e('Categoría', 'homlity-real-estate'); ?>
                <select name="<?php echo esc_attr($ff('category')); ?>">
                    <?php foreach ($categories as $val => $label): ?>
                        <option value="<?php echo esc_attr($val); ?>"<?php selected($faq['category'] ?? 'general', $val); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <?php esc_html_e('Mostrar en', 'homlity-real-estate'); ?>
                <select name="<?php echo esc_attr($ff('show_on')); ?>">
                    <?php foreach ($show_on_options as $val => $label): ?>
                        <option value="<?php echo esc_attr($val); ?>"<?php selected($faq['show_on'] ?? 'all', $val); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <?php esc_html_e('Orden', 'homlity-real-estate'); ?>
                <input type="number" name="<?php echo esc_attr($ff('order')); ?>" value="<?php echo esc_attr($fv('order')); ?>" class="small-text" min="0">
            </label>
        </div>
        <label class="homlity-faq-answer-label">
            <?php esc_html_e('Respuesta', 'homlity-real-estate'); ?>
            <textarea name="<?php echo esc_attr($ff('answer')); ?>" rows="4" class="large-text"><?php echo esc_textarea($ft('answer')); ?></textarea>
        </label>
    </div>
</div>
