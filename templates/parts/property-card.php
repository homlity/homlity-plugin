<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Property card component.
 * Overridable at homlity-real-estate/parts/property-card.php inside theme or child theme.
 *
 * Expected args: $post_id (int)
 */

use Homlity\PluginInmobiliario\Services\CurrencyService;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\WhatsAppLinkService;

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

if (!function_exists('homlity_card_extract_urls')) {
    /**
     * @param mixed $value
     * @return array<int,string>
     */
    function homlity_card_extract_urls($value): array
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }
            if ($trimmed[0] === '[' || $trimmed[0] === '{') {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return homlity_card_extract_urls($decoded);
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
if (!function_exists('homlity_card_feature_icon')) {
    function homlity_card_feature_icon($iconConfig, string $fallback): string
    {
        if (is_array($iconConfig) && !empty($iconConfig['value']) && class_exists('\Elementor\Icons_Manager')) {
            ob_start();
            \Elementor\Icons_Manager::render_icon($iconConfig, ['aria-hidden' => 'true']);
            $iconHtml = ob_get_clean();
            if (is_string($iconHtml) && $iconHtml !== '') {
                return $iconHtml;
            }
        }
        return esc_html($fallback);
    }
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
    'whatsapp_icon' => ['value' => 'fab fa-whatsapp', 'library' => 'fa-brands'],
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
    'feature_icon_area' => 'grid',
    'feature_icon_bedrooms' => 'bed',
    'feature_icon_bathrooms' => 'bath',
    'feature_icon_parking' => 'car',
    'feature_icon_area_lot' => 'lot',
    'feature_icon_area_private' => 'home',
    'feature_icon_area_built' => 'ruler',
    'feature_icon_age' => 'clock',
    'feature_icon_condition' => 'diamond',
    'feature_icon_code' => 'hash',
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
$featured = (bool) get_post_meta($post_id, $meta['featured'], true);
$agentPhone = get_post_meta($post_id, $meta['agent_phone'], true);
$agentId = (int) get_post_meta($post_id, $meta['agent_id'], true);
if (!$agentPhone && $agentId > 0) {
    $agentPhone = (string) get_user_meta($agentId, 'homlity_plugin_phone', true);
    if (!$agentPhone) {
        $agentPhone = (string) get_user_meta($agentId, 'billing_phone', true);
    }
}
$operationTerms = wp_get_post_terms($post_id, \Homlity\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_OPERATION, ['fields' => 'names']);
$operationLabel = (!is_wp_error($operationTerms) && !empty($operationTerms)) ? (string) $operationTerms[0] : '';
$tagTerms = wp_get_post_terms($post_id, \Homlity\PluginInmobiliario\Services\PropertyTaxonomies::TAXONOMY_TAG, ['fields' => 'names']);
$tagTerms = (!is_wp_error($tagTerms) && is_array($tagTerms)) ? array_slice($tagTerms, 0, 4) : [];

$images = [];
$coverImage = get_the_post_thumbnail_url($post_id, 'large');
if (!$coverImage) {
    $coverImage = (string) get_post_meta($post_id, '_property_featured_image_url', true);
}
if ($coverImage) {
    $images[] = $coverImage;
}

if ($cardOptions['media_mode'] === 'slider' || empty($images)) {
    $rawGallery = get_post_meta($post_id, $meta['gallery'], true);
    $galleryIds = array_filter(array_map('absint', explode(',', (string) $rawGallery)));
    foreach ($galleryIds as $attachmentId) {
        $url = wp_get_attachment_image_url($attachmentId, 'large');
        if ($url && !in_array($url, $images, true)) {
            $images[] = $url;
        }
        if (count($images) >= 6) {
            break;
        }
    }

    if (count($images) < 1) {
        foreach (homlity_card_extract_urls($rawGallery) as $url) {
            if (!in_array($url, $images, true)) {
                $images[] = $url;
            }
            if (count($images) >= 6) {
                break;
            }
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
if (!empty($cardOptions['show_whatsapp'])) {
    $whatsAppLink = WhatsAppLinkService::buildPropertyLink((int) $post_id, (string) $agentPhone);
}
$showWhatsappIcon = !empty($cardOptions['whatsapp_show_icon']);
$whatsappIconPosition = ($cardOptions['whatsapp_icon_position'] ?? 'left') === 'right' ? 'right' : 'left';
$whatsappIcon = is_array($cardOptions['whatsapp_icon'] ?? null) ? $cardOptions['whatsapp_icon'] : [];

$showPrice = !empty($cardOptions['show_price']) && in_array('price', $listingFields, true);
$visualPreset = (string) ($cardOptions['visual_preset'] ?? 'default');
$isCoverOverlayPreset = ($visualPreset === 'cover_overlay');
$presetClass = $visualPreset !== 'default' ? ' property-card--preset-' . sanitize_html_class(str_replace('_', '-', $visualPreset)) : '';
$hoverEffect = (string) ($cardOptions['hover_effect'] ?? 'lift');
$hoverClass = ' property-card--hover-' . sanitize_html_class($hoverEffect);
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
<article <?php post_class('property-card' . $presetClass . $hoverClass, $post_id); ?> data-property-id="<?php echo esc_attr($post_id); ?>">
    <a href="<?php echo esc_url(get_permalink($post_id)); ?>">
        <div class="property-card__gallery">
            <?php if ($featured && empty($tagTerms)): ?>
                <span class="property-card__featured-badge"><?php esc_html_e('Destacado', 'homlity-real-estate'); ?></span>
            <?php endif; ?>
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
                        <p class="property-card__overlay-title"><?php echo esc_html(get_the_title($post_id)); ?></p>
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
                                <span class="property-card__overlay-chip property-card__feature-item">
                                    <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_area'] ?? [], '▦'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                    <span class="property-card__feature-value"><?php echo esc_html($area); ?> m²</span>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($cardOptions['feature_bedrooms']) && $bedrooms): ?>
                                <span class="property-card__overlay-chip property-card__feature-item">
                                    <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_bedrooms'] ?? [], '🛏'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                    <span class="property-card__feature-value"><?php echo esc_html($bedrooms); ?></span>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($cardOptions['feature_bathrooms']) && $bathrooms): ?>
                                <span class="property-card__overlay-chip property-card__feature-item">
                                    <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_bathrooms'] ?? [], '🛁'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                    <span class="property-card__feature-value"><?php echo esc_html($bathrooms); ?></span>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($cardOptions['feature_parking']) && $parking): ?>
                                <span class="property-card__overlay-chip property-card__feature-item">
                                    <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_parking'] ?? [], '🚗'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                    <span class="property-card__feature-value"><?php echo esc_html($parking); ?></span>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!$isCoverOverlayPreset): ?>
        <div class="property-card__content">
        <?php if (!empty($cardOptions['show_title'])): ?>
            <p class="property-card__title"><?php echo esc_html(get_the_title($post_id)); ?></p>
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
                    <li class="property-card__feature-item" title="<?php esc_attr_e('Área', 'homlity-real-estate'); ?>">
                        <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_area'] ?? [], '▦'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <span class="property-card__feature-value"><?php echo esc_html($area); ?> m²</span>
                    </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_bedrooms']) && $bedrooms): ?>
                    <li class="property-card__feature-item" title="<?php esc_attr_e('Alcobas', 'homlity-real-estate'); ?>">
                        <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_bedrooms'] ?? [], '🛏'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <span class="property-card__feature-value"><?php echo esc_html($bedrooms); ?></span>
                    </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_bathrooms']) && $bathrooms): ?>
                    <li class="property-card__feature-item" title="<?php esc_attr_e('Baños', 'homlity-real-estate'); ?>">
                        <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_bathrooms'] ?? [], '🛁'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <span class="property-card__feature-value"><?php echo esc_html($bathrooms); ?></span>
                    </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_parking']) && $parking): ?>
                    <li class="property-card__feature-item" title="<?php esc_attr_e('Garajes', 'homlity-real-estate'); ?>">
                        <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_parking'] ?? [], '🚗'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <span class="property-card__feature-value"><?php echo esc_html($parking); ?></span>
                    </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_area_lot']) && $areaLot): ?>
                    <li class="property-card__feature-item" title="<?php esc_attr_e('Área lote', 'homlity-real-estate'); ?>">
                        <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_area_lot'] ?? [], '▣'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <span class="property-card__feature-value"><?php echo esc_html($areaLot); ?> m²</span>
                    </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_area_private']) && $areaPrivate): ?>
                    <li class="property-card__feature-item" title="<?php esc_attr_e('Área privada', 'homlity-real-estate'); ?>">
                        <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_area_private'] ?? [], '◫'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <span class="property-card__feature-value"><?php echo esc_html($areaPrivate); ?> m²</span>
                    </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_area_built']) && $areaBuilt): ?>
                    <li class="property-card__feature-item" title="<?php esc_attr_e('Área construida', 'homlity-real-estate'); ?>">
                        <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_area_built'] ?? [], '◧'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <span class="property-card__feature-value"><?php echo esc_html($areaBuilt); ?> m²</span>
                    </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_age']) && $age): ?>
                    <li class="property-card__feature-item" title="<?php esc_attr_e('Año/Edad', 'homlity-real-estate'); ?>">
                        <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_age'] ?? [], '◷'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <span class="property-card__feature-value"><?php echo esc_html($age); ?></span>
                    </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_condition']) && $condition): ?>
                    <li class="property-card__feature-item" title="<?php esc_attr_e('Estado', 'homlity-real-estate'); ?>">
                        <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_condition'] ?? [], '◆'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <span class="property-card__feature-value"><?php echo esc_html($condition); ?></span>
                    </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_code']) && $code): ?>
                    <li class="property-card__feature-item" title="<?php esc_attr_e('Código', 'homlity-real-estate'); ?>">
                        <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_code'] ?? [], '#'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <span class="property-card__feature-value"><?php echo esc_html($code); ?></span>
                    </li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
        <?php if ($displayPrice && $showPrice && empty($cardOptions['show_operation'])): ?>
            <p class="property-card__price">
                <?php echo esc_html($displayPrice); ?>
                <?php if ($displayPriceAdmin): ?>
                    <small> + <?php echo esc_html($displayPriceAdmin); ?> <?php esc_html_e('adm.', 'homlity-real-estate'); ?></small>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        </div>
        <?php endif; ?>
    </a>

    <?php if ($whatsAppLink): ?>
        <a class="property-card__whatsapp property-card__whatsapp--icon-<?php echo esc_attr($whatsappIconPosition); ?>" href="<?php echo esc_url($whatsAppLink); ?>" target="_blank" rel="noopener noreferrer" data-homlity-contact-type="whatsapp" data-property-id="<?php echo esc_attr($post_id); ?>">
            <?php if ($showWhatsappIcon): ?>
                <span class="property-card__whatsapp-icon" aria-hidden="true">
                    <?php if (!empty($whatsappIcon['value']) && class_exists('\Elementor\Icons_Manager')) : ?>
                        <?php \Elementor\Icons_Manager::render_icon($whatsappIcon, ['aria-hidden' => 'true']); ?>
                    <?php else : ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326z"/></svg>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
            <span class="property-card__whatsapp-label"><?php echo esc_html($cardOptions['whatsapp_label'] ?: __('Contactar por WhatsApp', 'homlity-real-estate')); ?></span>
        </a>
    <?php endif; ?>
</article>
