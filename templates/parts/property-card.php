<?php
/**
 * Property card component.
 * Overridable at homlity-plugin/parts/property-card.php inside theme or child theme.
 *
 * Expected args: $post_id (int)
 */

use Homlity\PluginInmobiliario\Services\CurrencyService;
use Homlity\PluginInmobiliario\Services\PropertyPostType;

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$meta = (new PropertyPostType())->metaKeys();
$currencyService = new CurrencyService();

$cardOptions = isset($card_options) && is_array($card_options) ? $card_options : [];
$cardOptions = array_merge([
    'media_mode' => 'single',
    'show_title' => true,
    'show_excerpt' => true,
    'show_operation' => true,
    'show_price' => true,
    'show_features' => true,
    'show_whatsapp' => true,
    'whatsapp_label' => '',
    'feature_area' => true,
    'feature_bedrooms' => true,
    'feature_bathrooms' => true,
    'feature_parking' => true,
    'feature_area_lot' => true,
    'feature_area_private' => true,
    'feature_area_built' => true,
    'feature_age' => true,
    'feature_condition' => true,
    'feature_code' => true,
], $cardOptions);

$settings = get_option(HOMLITY_PLUGIN_SETTINGS_OPTION, [
    'listing_fields' => ['price', 'excerpt', 'features', 'whatsapp'],
]);
$listingFields = $settings['listing_fields'] ?? ['price', 'excerpt', 'features', 'whatsapp'];

$price = get_post_meta($post_id, $meta['price_sale'], true);
$priceRent = get_post_meta($post_id, $meta['price_rent'], true);
$currency = get_post_meta($post_id, $meta['currency_sale'], true) ?: $currencyService->baseCurrency();
$currencyRent = get_post_meta($post_id, $meta['currency_rent'], true) ?: $currencyService->baseCurrency();
$priceAdmin = get_post_meta($post_id, $meta['price_admin'], true);
$adminIncluded = (bool) get_post_meta($post_id, $meta['admin_included'], true);
$area = get_post_meta($post_id, $meta['area'], true);
$areaLot = get_post_meta($post_id, $meta['area_lot'], true);
$areaPrivate = get_post_meta($post_id, $meta['area_private'], true);
$areaBuilt = get_post_meta($post_id, $meta['area_built'], true);
$bedrooms = get_post_meta($post_id, $meta['bedrooms'], true);
$bathrooms = get_post_meta($post_id, $meta['bathrooms'], true);
$parking = get_post_meta($post_id, $meta['parking'], true);
$condition = get_post_meta($post_id, $meta['condition'], true);
$age = get_post_meta($post_id, $meta['age'], true);
$code = get_post_meta($post_id, $meta['code'], true);
$agentPhone = get_post_meta($post_id, $meta['agent_phone'], true);
$agentPhoneDigits = $agentPhone ? preg_replace('/\D+/', '', $agentPhone) : '';

$operationTerms = wp_get_post_terms($post_id, \Homlity\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_OPERATION, ['fields' => 'names']);
$operationLabel = (!is_wp_error($operationTerms) && !empty($operationTerms)) ? (string) $operationTerms[0] : '';

$images = [];
$coverImage = get_the_post_thumbnail_url($post_id, 'large');
if ($coverImage) {
    $images[] = $coverImage;
}

if ($cardOptions['media_mode'] === 'slider') {
    $galleryIds = array_filter(array_map('absint', explode(',', (string) get_post_meta($post_id, $meta['gallery'], true))));
    foreach ($galleryIds as $attachmentId) {
        $url = wp_get_attachment_image_url($attachmentId, 'large');
        if ($url && !in_array($url, $images, true)) {
            $images[] = $url;
        }
        if (count($images) >= 6) {
            break;
        }
    }

    if (count($images) < 2) {
        $attachments = get_posts([
            'post_type' => 'attachment',
            'posts_per_page' => 6,
            'post_status' => 'inherit',
            'post_parent' => $post_id,
            'post_mime_type' => 'image',
            'orderby' => 'menu_order',
            'fields' => 'ids',
        ]);

        foreach ($attachments as $attachmentId) {
            $url = wp_get_attachment_image_url($attachmentId, 'large');
            if ($url && !in_array($url, $images, true)) {
                $images[] = $url;
            }
            if (count($images) >= 6) {
                break;
            }
        }
    }
}

$whatsAppLink = '';
if ($agentPhoneDigits && !empty($cardOptions['show_whatsapp']) && in_array('whatsapp', $listingFields, true)) {
    $msg = rawurlencode(get_the_title($post_id) . ' - ' . get_permalink($post_id));
    $whatsAppLink = 'https://wa.me/' . $agentPhoneDigits . '?text=' . $msg;
}

$showPrice = !empty($cardOptions['show_price']) && in_array('price', $listingFields, true);
$displayPrice = '';
$displayPriceAdmin = '';
if ($showPrice) {
    if ($price) {
        $displayPrice = homlity_plugin_apply_filters('homlity_plugin_format_price', null, $price, $currency);
    } elseif ($priceRent) {
        $displayPrice = homlity_plugin_apply_filters('homlity_plugin_format_price', null, $priceRent, $currencyRent);
    }
    if ($priceRent && $priceAdmin && !$adminIncluded) {
        $displayPriceAdmin = homlity_plugin_apply_filters('homlity_plugin_format_price', null, $priceAdmin, $currencyRent);
    }
}
?>
<article <?php post_class('property-card', $post_id); ?>>
    <a href="<?php echo esc_url(get_permalink($post_id)); ?>">
        <div class="property-card__gallery">
            <?php if ($cardOptions['media_mode'] === 'slider' && count($images) > 1): ?>
                <div class="property-card__gallery-slider">
                    <?php foreach ($images as $img): ?>
                        <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr(get_the_title($post_id)); ?>">
                    <?php endforeach; ?>
                </div>
            <?php elseif (!empty($images[0])): ?>
                <img src="<?php echo esc_url($images[0]); ?>" alt="<?php echo esc_attr(get_the_title($post_id)); ?>">
            <?php endif; ?>
        </div>
        <?php if (!empty($cardOptions['show_title'])): ?>
            <h2 class="property-card__title"><?php echo esc_html(get_the_title($post_id)); ?></h2>
        <?php endif; ?>
        <?php if (!empty($cardOptions['show_operation']) && $operationLabel): ?>
            <p class="property-card__operation"><?php echo esc_html($operationLabel); ?></p>
        <?php endif; ?>
        <?php if (!empty($cardOptions['show_excerpt']) && in_array('excerpt', $listingFields, true)): ?>
            <p class="property-card__excerpt">
                <?php echo esc_html(wp_trim_words(get_the_excerpt($post_id), 20)); ?>
            </p>
        <?php endif; ?>
        <?php if (!empty($cardOptions['show_features']) && in_array('features', $listingFields, true)): ?>
            <ul class="property-card__features">
                <?php if (!empty($cardOptions['feature_area']) && $area): ?>
                    <li><?php echo esc_html($area); ?> m²</li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_bedrooms']) && $bedrooms): ?>
                    <li><?php echo esc_html($bedrooms); ?> <?php esc_html_e('Habitaciones', 'homlity-plugin'); ?></li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_bathrooms']) && $bathrooms): ?>
                    <li><?php echo esc_html($bathrooms); ?> <?php esc_html_e('Baños', 'homlity-plugin'); ?></li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_parking']) && $parking): ?>
                    <li><?php echo esc_html($parking); ?> <?php esc_html_e('Garajes', 'homlity-plugin'); ?></li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_area_lot']) && $areaLot): ?>
                    <li><?php echo esc_html($areaLot); ?> <?php esc_html_e('m² lote', 'homlity-plugin'); ?></li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_area_private']) && $areaPrivate): ?>
                    <li><?php echo esc_html($areaPrivate); ?> <?php esc_html_e('m² privada', 'homlity-plugin'); ?></li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_area_built']) && $areaBuilt): ?>
                    <li><?php echo esc_html($areaBuilt); ?> <?php esc_html_e('m² construida', 'homlity-plugin'); ?></li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_age']) && $age): ?>
                    <li><?php echo esc_html($age); ?> <?php esc_html_e('años', 'homlity-plugin'); ?></li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_condition']) && $condition): ?>
                    <li><?php echo esc_html($condition); ?></li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_code']) && $code): ?>
                    <li>#<?php echo esc_html($code); ?></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
        <?php if ($displayPrice && $showPrice): ?>
            <p class="property-card__price">
                <?php echo esc_html($displayPrice); ?>
                <?php if ($displayPriceAdmin): ?>
                    <small> + <?php echo esc_html($displayPriceAdmin); ?> <?php esc_html_e('adm.', 'homlity-plugin'); ?></small>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </a>

    <?php if ($whatsAppLink): ?>
        <a class="property-card__whatsapp" href="<?php echo esc_url($whatsAppLink); ?>" target="_blank" rel="noopener noreferrer">
            <?php echo esc_html($cardOptions['whatsapp_label'] ?: __('Contactar por WhatsApp', 'homlity-plugin')); ?>
        </a>
    <?php endif; ?>
</article>
