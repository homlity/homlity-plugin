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
$agentId = (int) get_post_meta($post_id, $meta['agent_id'], true);
if (!$agentPhone && $agentId > 0) {
    $agentPhone = (string) get_user_meta($agentId, 'homlity_plugin_phone', true);
    if (!$agentPhone) {
        $agentPhone = (string) get_user_meta($agentId, 'billing_phone', true);
    }
}
$agentPhoneDigits = $agentPhone ? preg_replace('/\D+/', '', $agentPhone) : '';

$operationTerms = wp_get_post_terms($post_id, \Homlity\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_OPERATION, ['fields' => 'names']);
$operationLabel = (!is_wp_error($operationTerms) && !empty($operationTerms)) ? (string) $operationTerms[0] : '';
$tagTerms = wp_get_post_terms($post_id, \Homlity\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_TAG, ['fields' => 'names']);
$tagTerms = (!is_wp_error($tagTerms) && is_array($tagTerms)) ? array_slice($tagTerms, 0, 4) : [];

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
if ($agentPhoneDigits && !empty($cardOptions['show_whatsapp'])) {
    $msg = rawurlencode(get_the_title($post_id) . ' - ' . get_permalink($post_id));
    $whatsAppLink = 'https://wa.me/' . $agentPhoneDigits . '?text=' . $msg;
}

$showPrice = !empty($cardOptions['show_price']) && in_array('price', $listingFields, true);
$visualPreset = (string) ($cardOptions['visual_preset'] ?? 'default');
$isCoverOverlayPreset = ($visualPreset === 'cover_overlay');
$presetClass = $visualPreset !== 'default' ? ' property-card--preset-' . sanitize_html_class(str_replace('_', '-', $visualPreset)) : '';
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
<article <?php post_class('property-card' . $presetClass, $post_id); ?>>
    <a href="<?php echo esc_url(get_permalink($post_id)); ?>">
        <div class="property-card__gallery">
            <?php if (!empty($tagTerms)): ?>
                <div class="property-card__media-tags">
                    <?php foreach ($tagTerms as $tagName): ?>
                        <span class="property-card__media-tag"><?php echo esc_html($tagName); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($cardOptions['media_mode'] === 'slider' && count($images) > 1): ?>
                <div class="property-card__gallery-slider swiper" data-homlity-card-swiper="1">
                    <div class="swiper-wrapper">
                        <?php foreach ($images as $img): ?>
                            <div class="swiper-slide">
                                <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr(get_the_title($post_id)); ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="swiper-pagination"></div>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                </div>
            <?php elseif (!empty($images[0])): ?>
                <img src="<?php echo esc_url($images[0]); ?>" alt="<?php echo esc_attr(get_the_title($post_id)); ?>">
            <?php endif; ?>

            <?php if ($isCoverOverlayPreset): ?>
                <div class="property-card__overlay">
                    <?php if ($displayPrice && $showPrice): ?>
                        <p class="property-card__overlay-price"><?php echo esc_html($displayPrice); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($cardOptions['show_title'])): ?>
                        <h2 class="property-card__overlay-title"><?php echo esc_html(get_the_title($post_id)); ?></h2>
                    <?php endif; ?>
                    <?php if (!empty($cardOptions['show_operation']) && $operationLabel): ?>
                        <p class="property-card__overlay-operation">
                            <?php echo esc_html($operationLabel); ?>
                            <?php if ($displayPrice && $showPrice): ?>
                                <span class="property-card__operation-price"><?php echo esc_html($displayPrice); ?></span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($cardOptions['show_features'])): ?>
                        <div class="property-card__overlay-features">
                            <?php if (!empty($cardOptions['feature_area']) && $area): ?>
                                <span class="property-card__overlay-chip"><?php echo esc_html($area); ?> m²</span>
                            <?php endif; ?>
                            <?php if (!empty($cardOptions['feature_bedrooms']) && $bedrooms): ?>
                                <span class="property-card__overlay-chip"><?php echo esc_html($bedrooms); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($cardOptions['feature_bathrooms']) && $bathrooms): ?>
                                <span class="property-card__overlay-chip"><?php echo esc_html($bathrooms); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($cardOptions['feature_parking']) && $parking): ?>
                                <span class="property-card__overlay-chip"><?php echo esc_html($parking); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!$isCoverOverlayPreset): ?>
        <div class="property-card__content">
        <?php if (!empty($cardOptions['show_title'])): ?>
            <h2 class="property-card__title"><?php echo esc_html(get_the_title($post_id)); ?></h2>
        <?php endif; ?>
        <?php if (!empty($cardOptions['show_operation']) && $operationLabel): ?>
            <p class="property-card__operation">
                <?php echo esc_html($operationLabel); ?>
                <?php if ($displayPrice && $showPrice): ?>
                    <span class="property-card__operation-price"><?php echo esc_html($displayPrice); ?></span>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <?php if (!empty($cardOptions['show_excerpt']) && in_array('excerpt', $listingFields, true)): ?>
            <p class="property-card__excerpt">
                <?php echo esc_html(wp_trim_words(get_the_excerpt($post_id), 20)); ?>
            </p>
        <?php endif; ?>
        <?php if (!empty($cardOptions['show_features'])): ?>
            <ul class="property-card__features">
                <?php if (!empty($cardOptions['feature_area']) && $area): ?>
                    <li class="property-card__feature-item" title="<?php esc_attr_e('Área', 'homlity-plugin'); ?>">
                        <span class="property-card__feature-icon" aria-hidden="true">▦</span>
                        <span class="property-card__feature-value"><?php echo esc_html($area); ?> m²</span>
                    </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_bedrooms']) && $bedrooms): ?>
                    <li class="property-card__feature-item" title="<?php esc_attr_e('Alcobas', 'homlity-plugin'); ?>">
                        <span class="property-card__feature-icon" aria-hidden="true">🛏</span>
                        <span class="property-card__feature-value"><?php echo esc_html($bedrooms); ?></span>
                    </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_bathrooms']) && $bathrooms): ?>
                    <li class="property-card__feature-item" title="<?php esc_attr_e('Baños', 'homlity-plugin'); ?>">
                        <span class="property-card__feature-icon" aria-hidden="true">🛁</span>
                        <span class="property-card__feature-value"><?php echo esc_html($bathrooms); ?></span>
                    </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_parking']) && $parking): ?>
                    <li class="property-card__feature-item" title="<?php esc_attr_e('Garajes', 'homlity-plugin'); ?>">
                        <span class="property-card__feature-icon" aria-hidden="true">🚗</span>
                        <span class="property-card__feature-value"><?php echo esc_html($parking); ?></span>
                    </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_area_lot']) && $areaLot): ?>
                    <li class="property-card__feature-item" title="<?php esc_attr_e('Área lote', 'homlity-plugin'); ?>">
                        <span class="property-card__feature-icon" aria-hidden="true">▣</span>
                        <span class="property-card__feature-value"><?php echo esc_html($areaLot); ?> m²</span>
                    </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_area_private']) && $areaPrivate): ?>
                    <li class="property-card__feature-item" title="<?php esc_attr_e('Área privada', 'homlity-plugin'); ?>">
                        <span class="property-card__feature-icon" aria-hidden="true">◫</span>
                        <span class="property-card__feature-value"><?php echo esc_html($areaPrivate); ?> m²</span>
                    </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_area_built']) && $areaBuilt): ?>
                    <li class="property-card__feature-item" title="<?php esc_attr_e('Área construida', 'homlity-plugin'); ?>">
                        <span class="property-card__feature-icon" aria-hidden="true">◧</span>
                        <span class="property-card__feature-value"><?php echo esc_html($areaBuilt); ?> m²</span>
                    </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_age']) && $age): ?>
                    <li class="property-card__feature-item" title="<?php esc_attr_e('Año/Edad', 'homlity-plugin'); ?>">
                        <span class="property-card__feature-icon" aria-hidden="true">◷</span>
                        <span class="property-card__feature-value"><?php echo esc_html($age); ?></span>
                    </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_condition']) && $condition): ?>
                    <li class="property-card__feature-item" title="<?php esc_attr_e('Estado', 'homlity-plugin'); ?>">
                        <span class="property-card__feature-icon" aria-hidden="true">◆</span>
                        <span class="property-card__feature-value"><?php echo esc_html($condition); ?></span>
                    </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_code']) && $code): ?>
                    <li class="property-card__feature-item" title="<?php esc_attr_e('Código', 'homlity-plugin'); ?>">
                        <span class="property-card__feature-icon" aria-hidden="true">#</span>
                        <span class="property-card__feature-value"><?php echo esc_html($code); ?></span>
                    </li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
        <?php if ($displayPrice && $showPrice && empty($cardOptions['show_operation'])): ?>
            <p class="property-card__price">
                <?php echo esc_html($displayPrice); ?>
                <?php if ($displayPriceAdmin): ?>
                    <small> + <?php echo esc_html($displayPriceAdmin); ?> <?php esc_html_e('adm.', 'homlity-plugin'); ?></small>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        </div>
        <?php endif; ?>
    </a>

    <?php if ($whatsAppLink): ?>
        <a class="property-card__whatsapp" href="<?php echo esc_url($whatsAppLink); ?>" target="_blank" rel="noopener noreferrer">
            <?php echo esc_html($cardOptions['whatsapp_label'] ?: __('Contactar por WhatsApp', 'homlity-plugin')); ?>
        </a>
    <?php endif; ?>
</article>
