<?php
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
/**
 * Admin view: Homlity SEO & GEO settings page.
 *
 * Variables injected from SeoGeoSettingsService::renderPage():
 *   $settings — merged array (saved values + defaults)
 */

use Homlity\PluginInmobiliario\Services\SeoGeoSettingsService;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
$activeTab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$updated   = isset($_GET['updated']) && !empty(wp_unslash($_GET['updated'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$tabs = [
    'general'      => __('Datos generales',     'homlity-real-estate'),
    'contact'      => __('Contacto',             'homlity-real-estate'),
    'location'     => __('Ubicación',            'homlity-real-estate'),
    'coverage'     => __('Zonas de cobertura',   'homlity-real-estate'),
    'seo'          => __('SEO global',           'homlity-real-estate'),
    'property-seo' => __('SEO inmuebles',        'homlity-real-estate'),
    'faqs'         => __('FAQs globales',        'homlity-real-estate'),
    'schema'       => __('Schema',               'homlity-real-estate'),
    'social'       => __('Redes sociales',       'homlity-real-estate'),
    'brand'        => __('Marca visual',         'homlity-real-estate'),
    'tools'        => __('Herramientas',         'homlity-real-estate'),
    'advanced'     => __('Avanzado',             'homlity-real-estate'),
    'diagnostics'  => __('Diagnóstico SEO',      'homlity-real-estate'),
];

// Helpers
function hsg_field(string $key): string
{
    return 'homlity_seo[' . esc_attr($key) . ']';
}
function hsg_v(array $settings, string $key): string
{
    return esc_attr($settings[$key] ?? '');
}
function hsg_t(array $settings, string $key): string
{
    return esc_textarea($settings[$key] ?? '');
}
function hsg_u(array $settings, string $key): string
{
    return esc_url($settings[$key] ?? '');
}
function hsg_checked(array $settings, string $key): string
{
    return !empty($settings[$key]) ? ' checked' : '';
}
function hsg_row(string $label, string $input, string $help = ''): void
{
    echo '<tr>';
    echo '<th scope="row"><label>' . esc_html($label) . '</label></th>';
    echo '<td>' . $input; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped in each helper
    if ($help) {
        echo '<p class="description">' . esc_html($help) . '</p>';
    }
    echo '</td>';
    echo '</tr>';
}
function hsg_text(array $s, string $key, string $label, string $help = '', string $type = 'text'): void
{
    $input = '<input type="' . esc_attr($type) . '" name="' . hsg_field($key) . '" value="' . hsg_v($s, $key) . '" class="regular-text">';
    hsg_row($label, $input, $help);
}
function hsg_textarea(array $s, string $key, string $label, string $help = '', int $rows = 3): void
{
    $input = '<textarea name="' . hsg_field($key) . '" rows="' . esc_attr((string) $rows) . '" class="large-text">' . hsg_t($s, $key) . '</textarea>';
    hsg_row($label, $input, $help);
}
function hsg_checkbox(array $s, string $key, string $label, string $help = ''): void
{
    $input = '<label><input type="checkbox" name="' . hsg_field($key) . '" value="1"' . hsg_checked($s, $key) . '> ' . esc_html($label) . '</label>';
    hsg_row('', $input, $help);
}
function hsg_image(array $s, string $key, string $label, string $help = ''): void
{
    $url = hsg_u($s, $key);
    $preview = $url ? '<img src="' . esc_url($url) . '" class="homlity-img-preview">' : '';
    $input = '<div class="homlity-img-field">'
        . '<input type="url" name="' . hsg_field($key) . '" value="' . esc_attr($s[$key] ?? '') . '" class="regular-text homlity-img-url">'
        . ' <button type="button" class="button homlity-img-upload">' . esc_html__('Seleccionar', 'homlity-real-estate') . '</button>'
        . ' <button type="button" class="button homlity-img-remove" style="' . ($url ? '' : 'display:none') . '">' . esc_html__('Eliminar', 'homlity-real-estate') . '</button>'
        . '<div class="homlity-img-preview-wrap">' . $preview . '</div>'
        . '</div>';
    hsg_row($label, $input, $help);
}
function hsg_color(array $s, string $key, string $label, string $help = ''): void
{
    $input = '<input type="text" name="' . hsg_field($key) . '" value="' . hsg_v($s, $key) . '" class="homlity-color-picker" data-default-color="' . hsg_v($s, $key) . '">';
    hsg_row($label, $input, $help);
}
function hsg_select(array $s, string $key, string $label, array $options, string $help = ''): void
{
    $current = $s[$key] ?? '';
    $html    = '<select name="' . hsg_field($key) . '">';
    foreach ($options as $val => $text) {
        $html .= '<option value="' . esc_attr((string) $val) . '"' . selected($current, (string) $val, false) . '>' . esc_html($text) . '</option>';
    }
    $html .= '</select>';
    hsg_row($label, $html, $help);
}
function hsg_section(string $title): void
{
    echo '<tr><td colspan="2"><h3 class="homlity-section-heading">' . esc_html($title) . '</h3></td></tr>';
}
?>
<div class="wrap homlity-seo-geo-admin">

    <h1><?php echo esc_html__('SEO & GEO — Homlity Real Estate', 'homlity-real-estate'); ?></h1>

    <?php if ($updated): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Configuración guardada correctamente.', 'homlity-real-estate'); ?></p>
        </div>
    <?php endif; ?>

    <!-- Tab navigation -->
    <nav class="nav-tab-wrapper homlity-tabs-nav">
        <?php foreach ($tabs as $slug => $label): ?>
            <a href="#tab-<?php echo esc_attr($slug); ?>"
               class="nav-tab<?php echo $activeTab === $slug ? ' nav-tab-active' : ''; ?>"
               data-tab="<?php echo esc_attr($slug); ?>">
                <?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="homlity-seo-geo-form">
        <?php wp_nonce_field(SeoGeoSettingsService::NONCE_ACTION, SeoGeoSettingsService::NONCE_FIELD); ?>
        <input type="hidden" name="action" value="homlity_seo_geo_save">
        <input type="hidden" name="_current_tab" id="homlity-current-tab" value="<?php echo esc_attr($activeTab); ?>">

        <?php /* ═══════════════════════════════════════════════════════════
               TAB A: DATOS GENERALES
               ═══════════════════════════════════════════════════════════ */ ?>
        <div id="tab-general" class="homlity-seo-tab<?php echo $activeTab === 'general' ? ' is-active' : ''; ?>">
            <table class="form-table">
                <?php hsg_section(__('Información de la empresa', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'company_name', __('Nombre comercial', 'homlity-real-estate'), __('Nombre con el que se conoce la inmobiliaria al público.', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'company_legal_name', __('Razón social', 'homlity-real-estate'), __('Nombre legal registrado de la empresa.', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'company_nit', __('NIT / Identificación fiscal', 'homlity-real-estate')); ?>
                <?php hsg_select($settings, 'company_type', __('Tipo de negocio', 'homlity-real-estate'), [
                    'inmobiliaria'      => __('Inmobiliaria', 'homlity-real-estate'),
                    'agencia'           => __('Agencia inmobiliaria', 'homlity-real-estate'),
                    'agente'            => __('Agente independiente', 'homlity-real-estate'),
                    'constructora'      => __('Constructora', 'homlity-real-estate'),
                    'administradora'    => __('Administradora de inmuebles', 'homlity-real-estate'),
                    'otro'              => __('Otro', 'homlity-real-estate'),
                ]); ?>
                <?php hsg_text($settings, 'company_slogan', __('Eslogan comercial', 'homlity-real-estate'), __('Frase corta de posicionamiento de marca.', 'homlity-real-estate')); ?>
                <?php hsg_textarea($settings, 'company_description', __('Descripción corta', 'homlity-real-estate'), __('Se usa en schema y meta description de la empresa. Máx. 160 caracteres recomendados.', 'homlity-real-estate'), 2); ?>
                <?php hsg_textarea($settings, 'company_description_long', __('Descripción larga', 'homlity-real-estate'), __('Descripción completa de la inmobiliaria para uso en páginas.', 'homlity-real-estate'), 5); ?>
                <?php hsg_text($settings, 'company_years', __('Años de experiencia', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'company_registration', __('Matrícula inmobiliaria / Registro', 'homlity-real-estate'), __('Número de matrícula profesional, si aplica.', 'homlity-real-estate')); ?>

                <?php hsg_section(__('Imágenes de marca', 'homlity-real-estate')); ?>
                <?php hsg_image($settings, 'company_logo', __('Logo principal', 'homlity-real-estate'), __('Logo principal de la inmobiliaria. Formatos: PNG, SVG, WebP.', 'homlity-real-estate')); ?>
                <?php hsg_image($settings, 'company_logo_secondary', __('Logo secundario / horizontal', 'homlity-real-estate')); ?>
                <?php hsg_image($settings, 'company_favicon', __('Favicon', 'homlity-real-estate'), __('Ícono del sitio. Formato ICO o PNG 32×32 px.', 'homlity-real-estate')); ?>
                <?php hsg_image($settings, 'company_og_image', __('Imagen destacada (Open Graph)', 'homlity-real-estate'), __('Imagen por defecto para compartir en redes. Tamaño recomendado: 1200×630 px.', 'homlity-real-estate')); ?>
            </table>
            <?php submit_button(__('Guardar cambios', 'homlity-real-estate')); ?>
        </div>

        <?php /* ═══════════════════════════════════════════════════════════
               TAB B: CONTACTO
               ═══════════════════════════════════════════════════════════ */ ?>
        <div id="tab-contact" class="homlity-seo-tab<?php echo $activeTab === 'contact' ? ' is-active' : ''; ?>">
            <table class="form-table">
                <?php hsg_section(__('Correos electrónicos', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'contact_email', __('Correo principal', 'homlity-real-estate'), '', 'email'); ?>
                <?php hsg_text($settings, 'contact_email_sales', __('Correo de ventas', 'homlity-real-estate'), '', 'email'); ?>
                <?php hsg_text($settings, 'contact_email_support', __('Correo de soporte', 'homlity-real-estate'), '', 'email'); ?>

                <?php hsg_section(__('Teléfonos', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'contact_phone', __('Teléfono fijo', 'homlity-real-estate'), __('Incluir indicativo de país, ej: +57 601 1234567', 'homlity-real-estate'), 'tel'); ?>
                <?php hsg_text($settings, 'contact_mobile', __('Celular', 'homlity-real-estate'), __('Número de celular principal.', 'homlity-real-estate'), 'tel'); ?>

                <?php hsg_section(__('WhatsApp', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'contact_whatsapp', __('WhatsApp comercial', 'homlity-real-estate'), __('Número en formato internacional sin + ni espacios, ej: 573001234567', 'homlity-real-estate'), 'tel'); ?>
                <?php hsg_text($settings, 'contact_whatsapp_props', __('WhatsApp para inmuebles', 'homlity-real-estate'), __('Número específico para consultas de inmuebles.', 'homlity-real-estate'), 'tel'); ?>
                <?php hsg_text($settings, 'contact_whatsapp_url', __('URL de WhatsApp personalizada', 'homlity-real-estate'), __('URL completa de WhatsApp, ej: https://wa.me/573001234567', 'homlity-real-estate'), 'url'); ?>
                <?php hsg_text($settings, 'contact_whatsapp_text', __('Texto predeterminado para WhatsApp', 'homlity-real-estate'), __('Mensaje preescrito que aparece al abrir WhatsApp.', 'homlity-real-estate')); ?>

                <?php hsg_section(__('Web y horarios', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'contact_website', __('Sitio web', 'homlity-real-estate'), __('URL del sitio web principal, con https://', 'homlity-real-estate'), 'url'); ?>
                <?php hsg_text($settings, 'contact_hours', __('Horario de atención', 'homlity-real-estate'), __('Ej: Lunes a viernes 8am - 6pm, Sábados 9am - 1pm', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'contact_days', __('Días de atención', 'homlity-real-estate'), __('Ej: Lunes a Viernes y Sábados', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'contact_address', __('Dirección física', 'homlity-real-estate'), __('Dirección de la oficina principal.', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'contact_maps_link', __('Link de Google Maps', 'homlity-real-estate'), __('URL de Google Maps a la ubicación de la oficina.', 'homlity-real-estate'), 'url'); ?>
                <?php hsg_text($settings, 'contact_place_id', __('Google Place ID', 'homlity-real-estate'), __('ID único de Google My Business. Ej: ChIJrTLr-GyuEmsRBfy61i59si4', 'homlity-real-estate')); ?>
            </table>
            <?php submit_button(__('Guardar cambios', 'homlity-real-estate')); ?>
        </div>

        <?php /* ═══════════════════════════════════════════════════════════
               TAB C: UBICACIÓN
               ═══════════════════════════════════════════════════════════ */ ?>
        <div id="tab-location" class="homlity-seo-tab<?php echo $activeTab === 'location' ? ' is-active' : ''; ?>">
            <table class="form-table">
                <?php hsg_section(__('Ubicación principal de la inmobiliaria', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'geo_country', __('País', 'homlity-real-estate'), __('Nombre del país o código ISO, ej: Colombia / CO', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'geo_state', __('Departamento / Provincia / Estado', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'geo_city', __('Ciudad / Municipio', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'geo_locality', __('Localidad / Comuna', 'homlity-real-estate'), __('Opcional, si aplica en tu ciudad.', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'geo_neighborhood', __('Barrio principal', 'homlity-real-estate'), __('Barrio donde está la oficina principal.', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'geo_address', __('Dirección exacta', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'geo_postal_code', __('Código postal', 'homlity-real-estate')); ?>

                <?php hsg_section(__('Coordenadas', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'geo_latitude', __('Latitud', 'homlity-real-estate'), __('Ej: 4.710989', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'geo_longitude', __('Longitud', 'homlity-real-estate'), __('Ej: -74.072092', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'geo_maps_embed', __('URL de Google Maps Embed', 'homlity-real-estate'), __('URL del iframe de Google Maps para incrustar el mapa.', 'homlity-real-estate'), 'url'); ?>
                <?php hsg_text($settings, 'geo_timezone', __('Zona horaria', 'homlity-real-estate'), __('Ej: America/Bogota, America/Mexico_City, America/Lima', 'homlity-real-estate')); ?>
            </table>
            <?php submit_button(__('Guardar cambios', 'homlity-real-estate')); ?>
        </div>

        <?php /* ═══════════════════════════════════════════════════════════
               TAB D: ZONAS DE COBERTURA (Repeater)
               ═══════════════════════════════════════════════════════════ */ ?>
        <div id="tab-coverage" class="homlity-seo-tab<?php echo $activeTab === 'coverage' ? ' is-active' : ''; ?>">
            <p><?php esc_html_e('Agrega las zonas donde trabaja la inmobiliaria. Estas zonas se usan en el Schema JSON-LD (areaServed) y para generación de contenido SEO local.', 'homlity-real-estate'); ?></p>

            <div id="homlity-coverage-zones" class="homlity-repeater">
                <?php
                $zones = $settings['coverage_zones'] ?? [];
                foreach ($zones as $idx => $zone):
                    ?>
                    <div class="homlity-repeater-row" data-index="<?php echo esc_attr((string) $idx); ?>">
                        <?php include __DIR__ . '/partials/coverage-zone-row.php'; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="button button-secondary" id="homlity-add-zone">
                + <?php esc_html_e('Agregar zona de cobertura', 'homlity-real-estate'); ?>
            </button>

            <!-- Template (hidden) -->
            <template id="homlity-zone-template">
                <div class="homlity-repeater-row" data-index="__IDX__">
                    <?php
                    $idx  = '__IDX__';
                    $zone = [
                        'country' => '', 'state' => '', 'city' => '', 'neighborhood' => '',
                        'coverage_type' => 'all', 'priority' => 'media', 'description' => '',
                        'latitude' => '', 'longitude' => '', 'radius' => '', 'active' => '1', 'seo_slug' => '',
                    ];
                    include __DIR__ . '/partials/coverage-zone-row.php';
                    ?>
                </div>
            </template>

            <?php submit_button(__('Guardar cambios', 'homlity-real-estate')); ?>
        </div>

        <?php /* ═══════════════════════════════════════════════════════════
               TAB E: SEO GLOBAL
               ═══════════════════════════════════════════════════════════ */ ?>
        <div id="tab-seo" class="homlity-seo-tab<?php echo $activeTab === 'seo' ? ' is-active' : ''; ?>">
            <table class="form-table">
                <?php hsg_section(__('Meta tags globales', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'seo_meta_title', __('Meta title global', 'homlity-real-estate'), __('Título del sitio para motores de búsqueda. Máx. 60 caracteres.', 'homlity-real-estate')); ?>
                <?php hsg_textarea($settings, 'seo_meta_description', __('Meta description global', 'homlity-real-estate'), __('Descripción general del sitio. Máx. 160 caracteres.', 'homlity-real-estate'), 2); ?>
                <?php hsg_text($settings, 'seo_keywords', __('Keywords de referencia', 'homlity-real-estate'), __('Palabras clave internas de referencia (no se imprimen como meta keywords).', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'seo_main_phrase', __('Frase principal de posicionamiento', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'seo_secondary_phrases', __('Frases secundarias', 'homlity-real-estate'), __('Separadas por coma.', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'seo_name', __('Nombre SEO de la inmobiliaria', 'homlity-real-estate'), __('Cómo quieres aparecer en Google. Ej: Homlity Inmobiliaria Bogotá.', 'homlity-real-estate')); ?>
                <?php hsg_select($settings, 'seo_separator', __('Separador de títulos', 'homlity-real-estate'), [
                    '|' => '|', '-' => '-', '—' => '—', '·' => '·', '»' => '»',
                ], __('Carácter que separa el título de la página del nombre del sitio.', 'homlity-real-estate')); ?>

                <?php hsg_section(__('Open Graph y Twitter Cards', 'homlity-real-estate')); ?>
                <?php hsg_image($settings, 'seo_og_image', __('Imagen Open Graph global', 'homlity-real-estate'), __('Imagen por defecto para compartir en redes. 1200×630 px recomendado.', 'homlity-real-estate')); ?>
                <?php hsg_image($settings, 'seo_twitter_image', __('Imagen Twitter Card global', 'homlity-real-estate'), __('1200×600 px recomendado.', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'seo_enable_og', __('Activar Open Graph', 'homlity-real-estate'), __('Habilita etiquetas og: para Facebook, LinkedIn y otras redes.', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'seo_enable_twitter', __('Activar Twitter Cards', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'seo_enable_canonical', __('Activar canonical URLs', 'homlity-real-estate'), __('Ayuda a evitar contenido duplicado.', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'seo_enable_breadcrumbs', __('Activar breadcrumbs', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'seo_enable_schema', __('Activar Schema JSON-LD', 'homlity-real-estate'), __('Habilita los datos estructurados para Google Rich Results.', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'seo_enable_sitemap', __('Activar sitemap de inmuebles', 'homlity-real-estate'), __('Requiere soporte del plugin o tema de sitemap.', 'homlity-real-estate')); ?>

                <?php hsg_section(__('Indexación', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'seo_index_properties', __('Indexar páginas de inmuebles', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'seo_index_agents', __('Indexar páginas de asesores', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'seo_index_zones', __('Indexar páginas de barrios/zonas', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'seo_index_search', __('Indexar páginas de búsqueda', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'seo_noindex_filtered', __('Noindex en resultados con filtros activos', 'homlity-real-estate'), __('Evita que URLs con parámetros de filtro ?operacion=arriendo sean indexadas.', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'seo_noindex_empty', __('Noindex en resultados vacíos', 'homlity-real-estate'), __('Noindex cuando no se encuentran inmuebles en el listado.', 'homlity-real-estate')); ?>
            </table>
            <?php submit_button(__('Guardar cambios', 'homlity-real-estate')); ?>
        </div>

        <?php /* ═══════════════════════════════════════════════════════════
               TAB F: SEO PARA INMUEBLES
               ═══════════════════════════════════════════════════════════ */ ?>
        <div id="tab-property-seo" class="homlity-seo-tab<?php echo $activeTab === 'property-seo' ? ' is-active' : ''; ?>">

            <div class="homlity-variables-box">
                <strong><?php esc_html_e('Variables disponibles:', 'homlity-real-estate'); ?></strong>
                <code>{titulo}</code> <code>{tipo_inmueble}</code> <code>{operacion}</code>
                <code>{ciudad}</code> <code>{departamento}</code> <code>{barrio}</code>
                <code>{direccion}</code> <code>{area}</code> <code>{habitaciones}</code>
                <code>{banos}</code> <code>{garajes}</code> <code>{precio}</code>
                <code>{codigo}</code> <code>{asesor}</code> <code>{inmobiliaria}</code>
                <code>{numero}</code>
            </div>

            <table class="form-table">
                <?php hsg_text($settings, 'prop_title_tpl', __('Meta title de inmueble', 'homlity-real-estate'), __('Plantilla para el título SEO de cada inmueble.', 'homlity-real-estate')); ?>
                <?php hsg_textarea($settings, 'prop_desc_tpl', __('Meta description de inmueble', 'homlity-real-estate'), __('Plantilla para la descripción SEO de cada inmueble.', 'homlity-real-estate'), 3); ?>
                <?php hsg_text($settings, 'prop_slug_tpl', __('Plantilla de slug URL', 'homlity-real-estate'), __('Plantilla para construir la URL amigable del inmueble.', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'prop_alt_tpl', __('Alt de imágenes', 'homlity-real-estate'), __('Texto alternativo automático para imágenes del inmueble.', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'prop_caption_tpl', __('Caption de imágenes', 'homlity-real-estate'), __('Pie de imagen automático para las fotos del inmueble.', 'homlity-real-estate')); ?>
            </table>
            <?php submit_button(__('Guardar cambios', 'homlity-real-estate')); ?>
        </div>

        <?php /* ═══════════════════════════════════════════════════════════
               TAB G: FAQs GLOBALES (Repeater)
               ═══════════════════════════════════════════════════════════ */ ?>
        <div id="tab-faqs" class="homlity-seo-tab<?php echo $activeTab === 'faqs' ? ' is-active' : ''; ?>">
            <p><?php esc_html_e('Preguntas frecuentes globales de la inmobiliaria. Puedes marcar cuáles se incluyen en el Schema FAQPage de Google.', 'homlity-real-estate'); ?></p>

            <div id="homlity-global-faqs" class="homlity-repeater">
                <?php
                $faqs = $settings['global_faqs'] ?? [];
                foreach ($faqs as $idx => $faq):
                    ?>
                    <div class="homlity-repeater-row homlity-faq-row" data-index="<?php echo esc_attr((string) $idx); ?>">
                        <?php include __DIR__ . '/partials/faq-row.php'; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="button button-secondary" id="homlity-add-faq">
                + <?php esc_html_e('Agregar pregunta', 'homlity-real-estate'); ?>
            </button>

            <!-- Template (hidden) -->
            <template id="homlity-faq-template">
                <div class="homlity-repeater-row homlity-faq-row" data-index="__IDX__">
                    <?php
                    $idx = '__IDX__';
                    $faq = ['question' => '', 'answer' => '', 'category' => 'general', 'active' => '1', 'order' => '', 'show_on' => 'all', 'schema' => '1'];
                    include __DIR__ . '/partials/faq-row.php';
                    ?>
                </div>
            </template>

            <?php submit_button(__('Guardar cambios', 'homlity-real-estate')); ?>
        </div>

        <?php /* ═══════════════════════════════════════════════════════════
               TAB H: SCHEMA / DATOS ESTRUCTURADOS
               ═══════════════════════════════════════════════════════════ */ ?>
        <div id="tab-schema" class="homlity-seo-tab<?php echo $activeTab === 'schema' ? ' is-active' : ''; ?>">
            <table class="form-table">
                <?php hsg_select($settings, 'schema_type', __('Schema principal', 'homlity-real-estate'), [
                    'RealEstateAgent' => __('RealEstateAgent (recomendado para inmobiliarias)', 'homlity-real-estate'),
                    'LocalBusiness'   => __('LocalBusiness', 'homlity-real-estate'),
                    'Organization'    => __('Organization', 'homlity-real-estate'),
                ], __('Tipo de entidad Schema.org que representa mejor tu negocio.', 'homlity-real-estate')); ?>

                <?php hsg_section(__('Páginas donde se activa el Schema', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'schema_on_home', __('Activar en página principal', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'schema_on_properties', __('Activar en páginas de inmuebles', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'schema_on_agents', __('Activar en páginas de asesores', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'schema_on_zones', __('Activar en páginas de barrios/zonas', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'schema_faq_active', __('Incluir FAQPage Schema donde haya FAQs activas', 'homlity-real-estate'), __('Solo se emite si hay preguntas activas con "Usar en Schema" habilitado.', 'homlity-real-estate')); ?>
            </table>

            <div class="homlity-info-box">
                <strong><?php esc_html_e('Tipos de Schema generados automáticamente:', 'homlity-real-estate'); ?></strong>
                <ul>
                    <li><code>RealEstateAgent</code> / <code>LocalBusiness</code> / <code>Organization</code> — <?php esc_html_e('Con datos de empresa, dirección, contacto, redes sociales y zonas de cobertura.', 'homlity-real-estate'); ?></li>
                    <li><code>FAQPage</code> — <?php esc_html_e('Con preguntas frecuentes globales marcadas para Schema.', 'homlity-real-estate'); ?></li>
                    <li><code>GeoCoordinates</code> — <?php esc_html_e('Cuando latitud y longitud están configuradas.', 'homlity-real-estate'); ?></li>
                    <li><code>PostalAddress</code> — <?php esc_html_e('Cuando los datos de dirección están completos.', 'homlity-real-estate'); ?></li>
                </ul>
                <p><?php esc_html_e('Para validar el Schema, usa la herramienta Rich Results Test de Google.', 'homlity-real-estate'); ?></p>
            </div>

            <?php submit_button(__('Guardar cambios', 'homlity-real-estate')); ?>
        </div>

        <?php /* ═══════════════════════════════════════════════════════════
               TAB I: REDES SOCIALES
               ═══════════════════════════════════════════════════════════ */ ?>
        <div id="tab-social" class="homlity-seo-tab<?php echo $activeTab === 'social' ? ' is-active' : ''; ?>">
            <table class="form-table">
                <?php hsg_text($settings, 'social_facebook', __('Facebook', 'homlity-real-estate'), __('URL completa del perfil o página.', 'homlity-real-estate'), 'url'); ?>
                <?php hsg_text($settings, 'social_instagram', __('Instagram', 'homlity-real-estate'), '', 'url'); ?>
                <?php hsg_text($settings, 'social_linkedin', __('LinkedIn', 'homlity-real-estate'), '', 'url'); ?>
                <?php hsg_text($settings, 'social_youtube', __('YouTube', 'homlity-real-estate'), '', 'url'); ?>
                <?php hsg_text($settings, 'social_tiktok', __('TikTok', 'homlity-real-estate'), '', 'url'); ?>
                <?php hsg_text($settings, 'social_twitter', __('X / Twitter', 'homlity-real-estate'), '', 'url'); ?>
                <?php hsg_text($settings, 'social_pinterest', __('Pinterest', 'homlity-real-estate'), '', 'url'); ?>
                <?php hsg_text($settings, 'social_whatsapp', __('WhatsApp Business', 'homlity-real-estate'), '', 'url'); ?>
                <?php hsg_text($settings, 'social_google_biz', __('Google Business Profile', 'homlity-real-estate'), __('URL del perfil en Google My Business.', 'homlity-real-estate'), 'url'); ?>
                <?php hsg_text($settings, 'social_portals', __('Portales inmobiliarios', 'homlity-real-estate'), __('URLs de tus perfiles en portales inmobiliarios, separadas por coma.', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'social_google_reviews', __('URL de reseñas de Google', 'homlity-real-estate'), '', 'url'); ?>
            </table>
            <?php submit_button(__('Guardar cambios', 'homlity-real-estate')); ?>
        </div>

        <?php /* ═══════════════════════════════════════════════════════════
               TAB J: MARCA VISUAL
               ═══════════════════════════════════════════════════════════ */ ?>
        <div id="tab-brand" class="homlity-seo-tab<?php echo $activeTab === 'brand' ? ' is-active' : ''; ?>">
            <table class="form-table">
                <?php hsg_section(__('Colores', 'homlity-real-estate')); ?>
                <?php hsg_color($settings, 'brand_color_primary', __('Color principal', 'homlity-real-estate'), __('Color corporativo principal. Ej: #ff6752', 'homlity-real-estate')); ?>
                <?php hsg_color($settings, 'brand_color_secondary', __('Color secundario', 'homlity-real-estate')); ?>
                <?php hsg_color($settings, 'brand_color_accent', __('Color de acento', 'homlity-real-estate')); ?>
                <?php hsg_color($settings, 'brand_color_button', __('Color de botones', 'homlity-real-estate')); ?>
                <?php hsg_color($settings, 'brand_color_button_text', __('Color de texto en botón', 'homlity-real-estate')); ?>

                <?php hsg_section(__('Logos', 'homlity-real-estate')); ?>
                <?php hsg_image($settings, 'brand_logo', __('Logo principal', 'homlity-real-estate'), __('Versión estándar del logo.', 'homlity-real-estate')); ?>
                <?php hsg_image($settings, 'brand_logo_dark', __('Logo oscuro', 'homlity-real-estate'), __('Versión sobre fondos claros.', 'homlity-real-estate')); ?>
                <?php hsg_image($settings, 'brand_logo_light', __('Logo claro', 'homlity-real-estate'), __('Versión sobre fondos oscuros.', 'homlity-real-estate')); ?>

                <?php hsg_section(__('Imágenes predeterminadas', 'homlity-real-estate')); ?>
                <?php hsg_image($settings, 'brand_default_image', __('Imagen por defecto de inmueble', 'homlity-real-estate'), __('Se muestra cuando un inmueble no tiene fotos.', 'homlity-real-estate')); ?>
                <?php hsg_image($settings, 'brand_og_image', __('Imagen Open Graph de marca', 'homlity-real-estate'), __('Imagen por defecto para compartir. 1200×630 px.', 'homlity-real-estate')); ?>
            </table>
            <?php submit_button(__('Guardar cambios', 'homlity-real-estate')); ?>
        </div>

        <?php /* ═══════════════════════════════════════════════════════════
               TAB K: HERRAMIENTAS EXTERNAS
               ═══════════════════════════════════════════════════════════ */ ?>
        <div id="tab-tools" class="homlity-seo-tab<?php echo $activeTab === 'tools' ? ' is-active' : ''; ?>">
            <table class="form-table">
                <?php hsg_section(__('Google', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'tools_gsc', __('Google Search Console (meta verificación)', 'homlity-real-estate'), __('Solo el valor del atributo "content" de la meta tag de verificación.', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'tools_ga', __('Google Analytics ID', 'homlity-real-estate'), __('Ej: G-XXXXXXXXXX o UA-XXXXXXXX-1', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'tools_gtm', __('Google Tag Manager ID', 'homlity-real-estate'), __('Ej: GTM-XXXXXXX', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'tools_gmaps_key', __('Google Maps API Key', 'homlity-real-estate'), __('Requerida si usas el mapa de Google en el plugin.', 'homlity-real-estate')); ?>

                <?php hsg_section(__('Redes y remarketing', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'tools_meta_pixel', __('Meta Pixel ID (Facebook)', 'homlity-real-estate'), __('Ej: 1234567890123456', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'tools_tiktok_pixel', __('TikTok Pixel ID', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'tools_linkedin_insight', __('LinkedIn Insight Tag', 'homlity-real-estate'), __('Partner ID de LinkedIn. Ej: 1234567', 'homlity-real-estate')); ?>

                <?php hsg_section(__('Análisis y comportamiento', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'tools_hotjar', __('Hotjar ID', 'homlity-real-estate'), __('Ej: 1234567', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'tools_ms_clarity', __('Microsoft Clarity ID', 'homlity-real-estate'), __('Código de proyecto de Clarity. Ej: abcdefgh1', 'homlity-real-estate')); ?>

                <?php hsg_section(__('reCAPTCHA', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'tools_recaptcha_site', __('reCAPTCHA Site Key (pública)', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'tools_recaptcha_secret', __('reCAPTCHA Secret Key (privada)', 'homlity-real-estate'), __('Esta clave no se expone en el frontend.', 'homlity-real-estate'), 'password'); ?>
            </table>
            <?php submit_button(__('Guardar cambios', 'homlity-real-estate')); ?>
        </div>

        <?php /* ═══════════════════════════════════════════════════════════
               TAB L: AVANZADO
               ═══════════════════════════════════════════════════════════ */ ?>
        <div id="tab-advanced" class="homlity-seo-tab<?php echo $activeTab === 'advanced' ? ' is-active' : ''; ?>">
            <table class="form-table">
                <?php hsg_section(__('Slugs base de URLs', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'adv_slug_properties', __('Base slug inmuebles', 'homlity-real-estate'), __('Ej: "inmuebles" → /inmuebles/apartamento-bogota/', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'adv_slug_rent', __('Base slug arriendos', 'homlity-real-estate'), __('Ej: "arriendo"', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'adv_slug_sale', __('Base slug ventas', 'homlity-real-estate'), __('Ej: "venta"', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'adv_slug_zones', __('Base slug zonas', 'homlity-real-estate'), __('Ej: "zona"', 'homlity-real-estate')); ?>

                <?php hsg_section(__('SEO técnico', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'adv_canonical', __('Activar canonical en inmuebles', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'adv_redirect_slug', __('Redirigir si cambia el slug del inmueble', 'homlity-real-estate'), __('Activa redirección 301 automática al nuevo slug cuando se modifica.', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'adv_schema_per_property', __('Activar Schema por inmueble', 'homlity-real-estate'), __('Genera datos estructurados RealEstateListing en la ficha de cada inmueble.', 'homlity-real-estate')); ?>

                <?php hsg_section(__('Noindex automático', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'adv_noindex_inactive', __('Noindex a inmuebles inactivos', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'adv_noindex_no_photos', __('Noindex a inmuebles sin fotos', 'homlity-real-estate')); ?>
                <?php hsg_checkbox($settings, 'adv_noindex_no_desc', __('Noindex a inmuebles sin descripción', 'homlity-real-estate')); ?>
                <?php hsg_text($settings, 'adv_min_desc_length', __('Longitud mínima de descripción (caracteres)', 'homlity-real-estate'), __('Si la descripción tiene menos caracteres que este valor, el inmueble puede quedar con noindex.', 'homlity-real-estate'), 'number'); ?>
                <?php hsg_checkbox($settings, 'adv_alert_missing', __('Alertar en el editor cuando falta barrio, ciudad o precio', 'homlity-real-estate')); ?>
            </table>
            <?php submit_button(__('Guardar cambios', 'homlity-real-estate')); ?>
        </div>

        <?php /* ═══════════════════════════════════════════════════════════
               TAB M: DIAGNÓSTICO SEO
               ═══════════════════════════════════════════════════════════ */ ?>
        <div id="tab-diagnostics" class="homlity-seo-tab<?php echo $activeTab === 'diagnostics' ? ' is-active' : ''; ?>">
            <?php
            $checks = [
                [
                    'label'   => __('Nombre de inmobiliaria', 'homlity-real-estate'),
                    'ok'      => !empty($settings['company_name']),
                    'type'    => 'required',
                ],
                [
                    'label'   => __('Logo principal', 'homlity-real-estate'),
                    'ok'      => !empty($settings['company_logo']),
                    'type'    => 'recommended',
                ],
                [
                    'label'   => __('Correo principal', 'homlity-real-estate'),
                    'ok'      => !empty($settings['contact_email']),
                    'type'    => 'required',
                ],
                [
                    'label'   => __('Teléfono de contacto', 'homlity-real-estate'),
                    'ok'      => !empty($settings['contact_phone']) || !empty($settings['contact_mobile']),
                    'type'    => 'required',
                ],
                [
                    'label'   => __('WhatsApp configurado', 'homlity-real-estate'),
                    'ok'      => !empty($settings['contact_whatsapp']),
                    'type'    => 'recommended',
                ],
                [
                    'label'   => __('Ciudad principal', 'homlity-real-estate'),
                    'ok'      => !empty($settings['geo_city']),
                    'type'    => 'required',
                ],
                [
                    'label'   => __('Barrio principal', 'homlity-real-estate'),
                    'ok'      => !empty($settings['geo_neighborhood']),
                    'type'    => 'recommended',
                ],
                [
                    'label'   => __('Latitud y longitud', 'homlity-real-estate'),
                    'ok'      => !empty($settings['geo_latitude']) && !empty($settings['geo_longitude']),
                    'type'    => 'recommended',
                ],
                [
                    'label'   => __('Meta title global', 'homlity-real-estate'),
                    'ok'      => !empty($settings['seo_meta_title']),
                    'type'    => 'required',
                ],
                [
                    'label'   => __('Meta description global', 'homlity-real-estate'),
                    'ok'      => !empty($settings['seo_meta_description']),
                    'type'    => 'required',
                ],
                [
                    'label'   => __('FAQs globales configuradas', 'homlity-real-estate'),
                    'ok'      => count(array_filter($settings['global_faqs'] ?? [], fn($f) => !empty($f['active']))) > 0,
                    'type'    => 'recommended',
                ],
                [
                    'label'   => __('Schema JSON-LD activo', 'homlity-real-estate'),
                    'ok'      => !empty($settings['seo_enable_schema']),
                    'type'    => 'required',
                ],
                [
                    'label'   => __('Google Business Profile', 'homlity-real-estate'),
                    'ok'      => !empty($settings['social_google_biz']),
                    'type'    => 'recommended',
                ],
                [
                    'label'   => __('Redes sociales (al menos 2)', 'homlity-real-estate'),
                    'ok'      => count(array_filter([
                        $settings['social_facebook'], $settings['social_instagram'],
                        $settings['social_linkedin'], $settings['social_youtube'],
                    ])) >= 2,
                    'type'    => 'recommended',
                ],
                [
                    'label'   => __('Zonas de cobertura', 'homlity-real-estate'),
                    'ok'      => count(array_filter($settings['coverage_zones'] ?? [], fn($z) => !empty($z['active']))) > 0,
                    'type'    => 'recommended',
                ],
            ];

            $done    = count(array_filter($checks, fn($c) => $c['ok']));
            $total   = count($checks);
            $percent = $total > 0 ? (int) round($done / $total * 100) : 0;
            ?>

            <div class="homlity-diag-header">
                <h2>
                    <?php
                    /* translators: 1: completed checks, 2: total checks, 3: completion percentage. */
                    echo esc_html(sprintf(__('Puntuación SEO: %1$d / %2$d (%3$d%%)', 'homlity-real-estate'), $done, $total, $percent));
                    ?>
                </h2>
                <div class="homlity-progress-bar">
                    <div class="homlity-progress-fill" style="width:<?php echo esc_attr((string) $percent); ?>%"></div>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped homlity-diag-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Elemento', 'homlity-real-estate'); ?></th>
                        <th><?php esc_html_e('Estado', 'homlity-real-estate'); ?></th>
                        <th><?php esc_html_e('Prioridad', 'homlity-real-estate'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($checks as $check): ?>
                        <tr>
                            <td><?php echo esc_html($check['label']); ?></td>
                            <td>
                                <?php if ($check['ok']): ?>
                                    <span class="homlity-diag-ok">✓ <?php esc_html_e('Completo', 'homlity-real-estate'); ?></span>
                                <?php else: ?>
                                    <span class="homlity-diag-missing"><?php echo $check['type'] === 'required' ? esc_html__('✗ Pendiente', 'homlity-real-estate') : esc_html__('— Recomendado', 'homlity-real-estate'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $check['type'] === 'required'
                                    ? '<span class="homlity-badge homlity-badge-required">' . esc_html__('Requerido', 'homlity-real-estate') . '</span>'
                                    : '<span class="homlity-badge homlity-badge-recommended">' . esc_html__('Recomendado', 'homlity-real-estate') . '</span>';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top:16px">
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . SeoGeoSettingsService::PAGE_SLUG . '&tab=general')); ?>" class="button button-primary">
                    <?php esc_html_e('Completar configuración', 'homlity-real-estate'); ?>
                </a>
            </p>
        </div>

    </form><!-- /form -->
</div><!-- /wrap -->
