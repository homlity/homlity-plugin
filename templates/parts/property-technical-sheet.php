<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Technical sheet content.
 * Expected args: $post_id (int)
 */

use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\WhatsAppLinkService;

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($post_id) || !$post_id) {
    $post_id = get_the_ID();
}

if (!$post_id) {
    return;
}

if (!function_exists('homlity_sheet_extract_urls')) {
    function homlity_sheet_extract_urls($value): array
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }
            if ($trimmed[0] === '[' || $trimmed[0] === '{') {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return homlity_sheet_extract_urls($decoded);
                }
            }
            $parts = preg_split('/[\r\n,;|]+/', $trimmed) ?: [];
            $urls = [];
            foreach ($parts as $part) {
                $url = esc_url_raw(trim((string) $part));
                if ($url !== '') {
                    $urls[] = $url;
                }
            }
            return array_values(array_unique($urls));
        }

        if (!is_array($value)) {
            return [];
        }

        $urls = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $url = esc_url_raw(trim($item));
                if ($url !== '') {
                    $urls[] = $url;
                }
                continue;
            }
            if (is_array($item)) {
                $url = esc_url_raw(trim((string) ($item['url'] ?? '')));
                if ($url !== '') {
                    $urls[] = $url;
                }
            }
        }
        return array_values(array_unique($urls));
    }
}

$meta = (new PropertyPostType())->metaKeys();
$pluginSettings = get_option(HOMLITY_PLUGIN_SETTINGS_OPTION, []);
$pluginSettings = is_array($pluginSettings) ? $pluginSettings : [];

$companyName = (string) ($pluginSettings['company_name'] ?? get_bloginfo('name'));
$primaryColor = sanitize_hex_color((string) ($pluginSettings['primary_color'] ?? '')) ?: '#ff6752';
$baseCurrency = strtoupper((string) ($pluginSettings['base_currency'] ?? 'COP'));
$companyLogo = get_site_icon_url(192) ?: '';

$title = get_the_title($post_id);
$description = apply_filters('the_content', get_post_field('post_content', $post_id));
$permalink = get_permalink($post_id);
$featured = get_the_post_thumbnail_url($post_id, 'large');
$publishedAt = get_the_date('Y-m-d', $post_id);
$updatedAt = get_the_modified_date('Y-m-d', $post_id);

$fmtMoney = static function (float $value, string $currency): string {
    if ($value <= 0) {
        return 'Sin dato';
    }
    $symbols = [
        'COP' => '$', 'USD' => '$', 'MXN' => '$', 'CLP' => '$', 'ARS' => '$',
        'EUR' => '€', 'GBP' => '£', 'BRL' => 'R$', 'PEN' => 'S/', 'CRC' => '¢',
        'CAD' => 'C$', 'AUD' => 'A$', 'NZD' => 'NZ$', 'CHF' => 'CHF',
        'JPY' => '¥', 'CNY' => '¥',
    ];
    $currency = strtoupper($currency);
    $prefix = ($symbols[$currency] ?? ($currency !== '' ? $currency : '$')) . ' ';
    return $prefix . number_format_i18n($value, 0);
};
$fmtMoneyWithDefault = static function (float $value, string $currency, string $defaultCurrency) use ($fmtMoney): string {
    $active = $currency !== '' ? $currency : $defaultCurrency;
    return $fmtMoney($value, $active);
};
$asText = static function ($value): string {
    $value = is_scalar($value) ? trim((string) $value) : '';
    return $value !== '' ? $value : 'Sin dato';
};

$termsToText = static function (string $taxonomy, int $targetPostId): string {
    $terms = get_the_terms($targetPostId, $taxonomy);
    if (is_wp_error($terms) || !$terms) {
        return 'Sin dato';
    }
    return implode(', ', array_map(static function ($term) {
        return $term->name;
    }, $terms));
};

$agentName = '';
$agentPhone = '';
$agentEmail = '';
$agentAvatar = '';
$agentId = (int) get_post_meta($post_id, $meta['agent_id'], true);
if ($agentId > 0) {
    $user = get_user_by('id', $agentId);
    if ($user) {
        $agentName = (string) $user->display_name;
        $agentPhone = (string) (
            get_user_meta($agentId, 'homlity_plugin_phone', true)
            ?: get_user_meta($agentId, '_homlity_advisor_phone', true)
            ?: get_user_meta($agentId, 'phone', true)
            ?: get_user_meta($agentId, 'telefono', true)
            ?: get_user_meta($agentId, 'mobile_phone', true)
            ?: get_user_meta($agentId, 'celular', true)
            ?: get_user_meta($agentId, 'billing_phone', true)
        );
        $agentEmail = (string) $user->user_email;
        $agentAvatar = (string) (
            get_user_meta($agentId, '_homlity_advisor_photo', true)
            ?: get_avatar_url($agentId, ['size' => 120])
            ?: ''
        );
    }
}
$agentName = $agentName !== '' ? $agentName : (string) get_post_meta($post_id, $meta['agent_name'], true);
$agentPhone = $agentPhone !== '' ? $agentPhone : (string) get_post_meta($post_id, $meta['agent_phone'], true);
$agentEmail = $agentEmail !== '' ? $agentEmail : (string) get_post_meta($post_id, $meta['agent_email'], true);
$agentAvatar = $agentAvatar !== '' ? $agentAvatar : (string) get_post_meta($post_id, $meta['agent_photo'], true);
$whatsAppUrl = WhatsAppLinkService::buildPropertyLink((int) $post_id, (string) $agentPhone, 'Buen día, estoy interesado en ' . $title);

$gallery = get_post_meta($post_id, $meta['gallery'], true);
$images = homlity_sheet_extract_urls($gallery);
if (!$images && $featured) {
    $images[] = $featured;
}

$videos = homlity_sheet_extract_urls(get_post_meta($post_id, $meta['videos'], true));
$tours = homlity_sheet_extract_urls(get_post_meta($post_id, $meta['tour_360'], true));
$photos360 = homlity_sheet_extract_urls(get_post_meta($post_id, $meta['photos_360'], true));
$brochure = (string) get_post_meta($post_id, $meta['brochure'], true);
if ($brochure !== '' && !filter_var($brochure, FILTER_VALIDATE_URL)) {
    $brochure = '';
}

$infoItems = [
    ['Código', get_post_meta($post_id, $meta['code'], true)],
    ['Dirección', get_post_meta($post_id, $meta['address'], true)],
    ['Gestión', $termsToText(PropertyTaxonomies::TAXONOMY_OPERATION, $post_id)],
    ['Tipo', $termsToText(PropertyTaxonomies::TAXONOMY_TYPE, $post_id)],
    ['Categoría', $termsToText(PropertyTaxonomies::TAXONOMY_CATEGORY, $post_id)],
    ['Etiqueta', $termsToText(PropertyTaxonomies::TAXONOMY_TAG, $post_id)],
    ['País', $termsToText(PropertyTaxonomies::TAXONOMY_COUNTRY, $post_id)],
    ['Departamento/Provincia', $termsToText(PropertyTaxonomies::TAXONOMY_STATE, $post_id)],
    ['Ciudad', $termsToText(PropertyTaxonomies::TAXONOMY_CITY, $post_id)],
    ['Barrio', $termsToText(PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD, $post_id)],
    ['Lugares cercanos', $termsToText(PropertyTaxonomies::TAXONOMY_NEARBY, $post_id)],
    ['Publicado', $publishedAt],
    ['Actualizado', $updatedAt],
];

$dimensionItems = [
    ['Área total', get_post_meta($post_id, $meta['area'], true), 'm²'],
    ['Área lote', get_post_meta($post_id, $meta['area_lot'], true), 'm²'],
    ['Área privada', get_post_meta($post_id, $meta['area_private'], true), 'm²'],
    ['Área construida', get_post_meta($post_id, $meta['area_built'], true), 'm²'],
    ['Habitaciones', get_post_meta($post_id, $meta['bedrooms'], true), ''],
    ['Baños', get_post_meta($post_id, $meta['bathrooms'], true), ''],
    ['Garajes', get_post_meta($post_id, $meta['parking'], true), ''],
    ['Estado físico', get_post_meta($post_id, $meta['condition'], true), ''],
    ['Edad inmueble', get_post_meta($post_id, $meta['age'], true), ''],
];

$financeItems = [
    ['Valor venta', $fmtMoneyWithDefault((float) get_post_meta($post_id, $meta['price_sale'], true), (string) get_post_meta($post_id, $meta['currency_sale'], true), $baseCurrency)],
    ['Canon arriendo', $fmtMoneyWithDefault((float) get_post_meta($post_id, $meta['price_rent'], true), (string) get_post_meta($post_id, $meta['currency_rent'], true), $baseCurrency)],
    ['Administración', $fmtMoneyWithDefault((float) get_post_meta($post_id, $meta['price_admin'], true), (string) get_post_meta($post_id, $meta['currency_admin'], true), $baseCurrency)],
];

$featureTerms = PropertyTaxonomies::getVisibleFeatureTermsForPost((int) $post_id);
$features = [];
if ($featureTerms) {
    foreach ($featureTerms as $featureTerm) {
        $features[] = $featureTerm->name;
    }
}
?>
<?php
$_r = (int) hexdec( substr( ltrim( $primaryColor, '#' ), 0, 2 ) );
$_g = (int) hexdec( substr( ltrim( $primaryColor, '#' ), 2, 2 ) );
$_b = (int) hexdec( substr( ltrim( $primaryColor, '#' ), 4, 2 ) );
?>
<main class="homlity-tech-sheet" style="--sheet-primary:<?php echo esc_attr( $primaryColor ); ?>;--sheet-primary-rgb:<?php echo esc_attr( "$_r,$_g,$_b" ); ?>">

    <div class="homlity-tech-sheet__hero">
        <div class="homlity-tech-sheet__hero-brand">
            <?php if ($companyLogo) : ?>
                <img src="<?php echo esc_url($companyLogo); ?>" alt="<?php echo esc_attr($companyName); ?>" class="homlity-tech-sheet__hero-logo">
            <?php endif; ?>
            <div>
                <p class="homlity-tech-sheet__brand-label"><?php esc_html_e('Ficha técnica inmueble', 'homlity-real-estate'); ?></p>
                <h1><?php echo esc_html($title); ?></h1>
                <p class="homlity-tech-sheet__hero-subtitle"><?php echo esc_html($asText(get_post_meta($post_id, $meta['address'], true))); ?></p>
            </div>
        </div>
        <div class="homlity-tech-sheet__actions">
            <a class="homlity-tech-sheet__back" href="<?php echo esc_url($permalink); ?>"><?php esc_html_e('Volver al inmueble', 'homlity-real-estate'); ?></a>
            <button type="button" class="homlity-tech-sheet__print" onclick="window.print()"><?php esc_html_e('Imprimir ficha', 'homlity-real-estate'); ?></button>
        </div>
    </div>

    <section class="homlity-tech-sheet__card homlity-tech-sheet__advisor">
        <h2><?php esc_html_e('Información del asesor', 'homlity-real-estate'); ?></h2>
        <div class="homlity-tech-sheet__advisor-row">
            <div class="homlity-tech-sheet__advisor-avatar-wrap">
                <?php if ($agentAvatar) : ?>
                    <img src="<?php echo esc_url($agentAvatar); ?>" alt="<?php echo esc_attr($asText($agentName)); ?>" class="homlity-tech-sheet__advisor-avatar">
                <?php else : ?>
                    <div class="homlity-tech-sheet__advisor-avatar homlity-tech-sheet__advisor-avatar--fallback"></div>
                <?php endif; ?>
            </div>
            <div class="homlity-tech-sheet__grid homlity-tech-sheet__grid--advisor">
                <div><strong><?php esc_html_e('Nombre', 'homlity-real-estate'); ?>:</strong> <?php echo esc_html($asText($agentName)); ?></div>
                <div>
                    <strong><?php esc_html_e('Teléfono', 'homlity-real-estate'); ?>:</strong>
                    <?php if ($agentPhone) : ?>
                        <a href="<?php echo esc_attr('tel:' . preg_replace('/\s+/', '', (string) $agentPhone)); ?>" data-homlity-contact-type="phone" data-property-id="<?php echo esc_attr($post_id); ?>"><?php echo esc_html($agentPhone); ?></a>
                    <?php else : ?>
                        <?php esc_html_e('Sin dato', 'homlity-real-estate'); ?>
                    <?php endif; ?>
                </div>
                <div>
                    <strong><?php esc_html_e('Correo', 'homlity-real-estate'); ?>:</strong>
                    <?php if ($agentEmail) : ?>
                        <a href="<?php echo esc_attr('mailto:' . $agentEmail); ?>" data-homlity-contact-type="email" data-property-id="<?php echo esc_attr($post_id); ?>"><?php echo esc_html($agentEmail); ?></a>
                    <?php else : ?>
                        <?php esc_html_e('Sin dato', 'homlity-real-estate'); ?>
                    <?php endif; ?>
                </div>
                <div>
                    <strong><?php esc_html_e('WhatsApp', 'homlity-real-estate'); ?>:</strong>
                    <?php if ($whatsAppUrl) : ?>
                        <a href="<?php echo esc_url($whatsAppUrl); ?>" target="_blank" rel="noopener noreferrer" data-homlity-contact-type="whatsapp" data-property-id="<?php echo esc_attr($post_id); ?>"><?php esc_html_e('Contactar asesor', 'homlity-real-estate'); ?></a>
                    <?php else : ?>
                        <?php esc_html_e('Sin dato', 'homlity-real-estate'); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="homlity-tech-sheet__card">
        <h2><?php esc_html_e('Finanzas', 'homlity-real-estate'); ?></h2>
        <div class="homlity-tech-sheet__stats">
            <?php foreach ($financeItems as $item) : ?>
                <article class="homlity-tech-sheet__stat">
                    <span class="homlity-tech-sheet__stat-label"><?php echo esc_html($item[0]); ?></span>
                    <strong class="homlity-tech-sheet__stat-value"><?php echo esc_html($item[1]); ?></strong>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="homlity-tech-sheet__card">
        <h2><?php esc_html_e('Información general del inmueble', 'homlity-real-estate'); ?></h2>
        <div class="homlity-tech-sheet__grid">
            <?php foreach ($infoItems as $item) : ?>
                <div><strong><?php echo esc_html($item[0]); ?>:</strong> <?php echo esc_html($asText($item[1])); ?></div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="homlity-tech-sheet__card">
        <h2><?php esc_html_e('Dimensiones y ambientes', 'homlity-real-estate'); ?></h2>
        <div class="homlity-tech-sheet__grid">
            <?php foreach ($dimensionItems as $item) :
                $suffix = $item[2] !== '' && trim((string) $item[1]) !== '' ? ' ' . $item[2] : '';
                ?>
                <div><strong><?php echo esc_html($item[0]); ?>:</strong> <?php echo esc_html($asText($item[1]) . $suffix); ?></div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="homlity-tech-sheet__card">
        <h2><?php esc_html_e('Descripción completa', 'homlity-real-estate'); ?></h2>
        <div class="homlity-tech-sheet__description"><?php echo wp_kses_post($description); ?></div>
    </section>

    <section class="homlity-tech-sheet__card">
        <h2><?php esc_html_e('Características del inmueble', 'homlity-real-estate'); ?></h2>
        <?php if ($features) : ?>
            <ul class="homlity-tech-sheet__features-list">
                <?php foreach ($features as $feature) : ?>
                    <li><?php echo esc_html($feature); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p><?php esc_html_e('Sin características registradas.', 'homlity-real-estate'); ?></p>
        <?php endif; ?>
    </section>

    <section class="homlity-tech-sheet__card">
        <h2><?php esc_html_e('Catálogo multimedia', 'homlity-real-estate'); ?></h2>

        <?php if ($images) : ?>
            <h3><?php esc_html_e('Fotos', 'homlity-real-estate'); ?></h3>
            <div class="homlity-tech-sheet__gallery">
                <?php foreach (array_slice($images, 0, 18) as $image) : ?>
                    <a href="<?php echo esc_url($image); ?>" target="_blank" rel="noopener noreferrer">
                        <img src="<?php echo esc_url($image); ?>" alt="">
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($videos || $tours || $photos360 || $brochure) : ?>
            <div class="homlity-tech-sheet__media-links">
                <?php if ($videos) : ?>
                    <div>
                        <h3><?php esc_html_e('Videos', 'homlity-real-estate'); ?></h3>
                        <ul><?php foreach ($videos as $url) : ?><li><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($url); ?></a></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>
                <?php if ($tours) : ?>
                    <div>
                        <h3><?php esc_html_e('Recorridos 360', 'homlity-real-estate'); ?></h3>
                        <ul><?php foreach ($tours as $url) : ?><li><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($url); ?></a></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>
                <?php if ($photos360) : ?>
                    <div>
                        <h3><?php esc_html_e('Fotos 360', 'homlity-real-estate'); ?></h3>
                        <ul><?php foreach ($photos360 as $url) : ?><li><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($url); ?></a></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>
                <?php if ($brochure) : ?>
                    <div>
                        <h3><?php esc_html_e('Brochure', 'homlity-real-estate'); ?></h3>
                        <p><a href="<?php echo esc_url($brochure); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($brochure); ?></a></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="homlity-tech-sheet__card">
        <p class="homlity-tech-sheet__legal">
            <?php echo esc_html($companyName); ?> · <?php echo esc_html(date_i18n('Y-m-d H:i')); ?> ·
            <?php esc_html_e('Propiedad sujeta a disponibilidad. Precio e información sujetos a cambios sin previo aviso.', 'homlity-real-estate'); ?>
        </p>
    </section>
</main>
