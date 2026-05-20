<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Property share component.
 * Overridable at homlity-real-estate/parts/property-share.php
 *
 * Expected args: $post_id (int), $settings (array, optional — Elementor widget settings)
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$s     = $settings ?? [];
$url   = get_permalink($post_id) ?: '';
$title = get_the_title($post_id) ?: '';

$bedrooms = get_post_meta($post_id, '_property_bedrooms', true);
$bathrooms = get_post_meta($post_id, '_property_bathrooms', true);
$parking = get_post_meta($post_id, '_property_parking', true);
$area = get_post_meta($post_id, '_property_area', true);
$priceSale = get_post_meta($post_id, '_property_price_sale', true);
$priceRent = get_post_meta($post_id, '_property_price_rent', true);

$formatNumber = static function ($value): string {
    if ($value === '' || $value === null) {
        return '';
    }
    $number = (float) preg_replace('/[^0-9\.\-]/', '', (string) $value);
    if ($number <= 0) {
        return '';
    }
    return number_format_i18n($number, 0);
};

$displayPrice = $formatNumber($priceSale);
if ($displayPrice === '') {
    $displayPrice = $formatNumber($priceRent);
}

$summaryParts = [];
if ($title !== '') {
    $summaryParts[] = $title;
}
if ($bedrooms !== '') {
    /* translators: %s: number of bedrooms */
    $summaryParts[] = sprintf(__('alcobas: %s', 'homlity-real-estate'), $bedrooms);
}
if ($bathrooms !== '') {
    /* translators: %s: number of bathrooms */
    $summaryParts[] = sprintf(__('baños: %s', 'homlity-real-estate'), $bathrooms);
}
if ($parking !== '') {
    /* translators: %s: number of parking spaces */
    $summaryParts[] = sprintf(__('parqueaderos: %s', 'homlity-real-estate'), $parking);
}
if ($area !== '') {
    /* translators: %s: property area in square meters */
    $summaryParts[] = sprintf(__('área: %sm2', 'homlity-real-estate'), $area);
}
if ($displayPrice !== '') {
    /* translators: %s: formatted property price */
    $summaryParts[] = sprintf(__('valor: %s', 'homlity-real-estate'), $displayPrice);
}

$propertySummary = trim(implode(' | ', $summaryParts));
if ($propertySummary === '') {
    $propertySummary = $title;
}

$shareTextTpl = $s['share_text'] ?? '{summary} {url}';
$shareText    = str_replace(
    ['{title}', '{url}', '{summary}', '{bedrooms}', '{bathrooms}', '{parking}', '{area}', '{price}'],
    [$title, $url, $propertySummary, (string) $bedrooms, (string) $bathrooms, (string) $parking, (string) $area, (string) $displayPrice],
    $shareTextTpl
);
$shareText = trim((string) $shareText);
if ($shareText === '') {
    $shareText = trim($propertySummary . ' ' . $url);
}
$headingText  = trim((string) ($s['heading_text'] ?? __('Compartir en:', 'homlity-real-estate')));

$platforms = [
    'whatsapp'  => [
        'label' => $s['label_whatsapp']  ?? __('WhatsApp',      'homlity-real-estate'),
        'href'  => 'https://api.whatsapp.com/send?text=' . rawurlencode($shareText . ' ' . $url),
        'copy'  => false,
    ],
    'facebook'  => [
        'label' => $s['label_facebook']  ?? __('Facebook',      'homlity-real-estate'),
        'href'  => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url),
        'copy'  => false,
    ],
    'x'         => [
        'label' => $s['label_x']         ?? __('X',             'homlity-real-estate'),
        'href'  => 'https://twitter.com/intent/tweet?text=' . rawurlencode($shareText) . '&url=' . rawurlencode($url),
        'copy'  => false,
    ],
    'linkedin'  => [
        'label' => $s['label_linkedin']  ?? __('LinkedIn',      'homlity-real-estate'),
        'href'  => 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode($url),
        'copy'  => false,
    ],
    'telegram'  => [
        'label' => $s['label_telegram']  ?? __('Telegram',      'homlity-real-estate'),
        'href'  => 'https://t.me/share/url?url=' . rawurlencode($url) . '&text=' . rawurlencode($shareText),
        'copy'  => false,
    ],
    'pinterest' => [
        'label' => $s['label_pinterest'] ?? __('Pinterest',     'homlity-real-estate'),
        'href'  => 'https://pinterest.com/pin/create/button/?url=' . rawurlencode($url) . '&description=' . rawurlencode($shareText),
        'copy'  => false,
    ],
    'reddit'    => [
        'label' => $s['label_reddit']    ?? __('Reddit',        'homlity-real-estate'),
        'href'  => 'https://www.reddit.com/submit?url=' . rawurlencode($url) . '&title=' . rawurlencode($title),
        'copy'  => false,
    ],
    'email'     => [
        'label' => $s['label_email']     ?? __('Correo',        'homlity-real-estate'),
        'href'  => 'mailto:?subject=' . rawurlencode($title) . '&body=' . rawurlencode($shareText . ' ' . $url),
        'copy'  => false,
    ],
    'copy'      => [
        'label' => $s['label_copy']      ?? __('Copiar enlace', 'homlity-real-estate'),
        'href'  => $url,
        'copy'  => true,
    ],
];

?>
<div class="property-share-widget property-share-widget--inline">
    <?php if ($headingText !== ''): ?>
        <p class="property-share-widget__heading"><?php echo esc_html($headingText); ?></p>
    <?php endif; ?>
    <ul class="property-share-widget__list">
        <?php foreach ($platforms as $key => $platform): ?>
            <?php
            if (($s['show_' . $key] ?? 'yes') !== 'yes') {
                continue;
            }
            $icon = $s['icon_' . $key] ?? [];
            if (is_string($icon) && $icon !== '') {
                $icon = ['value' => $icon, 'library' => 'fa-solid'];
            }
            ?>
            <li class="property-share__item">
                <a
                    class="property-share__link<?php echo $platform['copy'] ? ' property-share__copy' : ''; ?>"
                    href="<?php echo esc_url($platform['href']); ?>"
                    <?php if (!$platform['copy']): ?>
                        target="_blank"
                        rel="noopener noreferrer"
                    <?php else: ?>
                        data-copy-url="<?php echo esc_url($url); ?>"
                    <?php endif; ?>
                    title="<?php echo esc_attr($platform['label']); ?>"
                    aria-label="<?php echo esc_attr($platform['label']); ?>"
                >
                    <?php if (!empty($icon['value']) && class_exists('\Elementor\Icons_Manager')): ?>
                        <span class="property-share__icon">
                            <?php \Elementor\Icons_Manager::render_icon($icon, ['aria-hidden' => 'true']); ?>
                        </span>
                    <?php else: ?>
                        <span class="property-share__icon" aria-hidden="true">•</span>
                    <?php endif; ?>
                    <span class="screen-reader-text"><?php echo esc_html($platform['label']); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
