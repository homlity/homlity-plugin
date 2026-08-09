<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Property card – Bootstrap 5 variant.
 * Overridable at homlity-real-estate/parts/property-card-bootstrap.php in theme or child theme.
 *
 * Expected args: $post_id (int)
 *
 * IMPORTANT: This template outputs a column wrapper (col-*) so that cards are grid
 * items when injected into a Bootstrap `.row` container via AJAX.
 */

use Homlity\PluginInmobiliario\Services\CurrencyService;
use Homlity\PluginInmobiliario\Services\PropertyCodeResolver;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
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
    function homlity_normalize_card_icon_config($iconConfig): array
    {
        if (is_array($iconConfig) && !empty($iconConfig['value'])) {
            $value = trim((string) $iconConfig['value']);
            if ($value !== '' && strpos($value, 'fa-') !== false) {
                if (empty($iconConfig['library'])) {
                    if (strpos($value, 'fab ') === 0) {
                        $iconConfig['library'] = 'fa-brands';
                    } elseif (strpos($value, 'far ') === 0) {
                        $iconConfig['library'] = 'fa-regular';
                    } else {
                        $iconConfig['library'] = 'fa-solid';
                    }
                }
                return $iconConfig;
            }

            $iconConfig = $value;
        }

        if (!is_string($iconConfig)) {
            return [];
        }

        $legacyMap = [
            'grid'    => 'fas fa-ruler-combined',
            'bed'     => 'fas fa-bed',
            'bath'    => 'fas fa-bath',
            'car'     => 'fas fa-car',
            'lot'     => 'fas fa-draw-polygon',
            'home'    => 'fas fa-house',
            'house'   => 'fas fa-house',
            'ruler'   => 'fas fa-ruler',
            'clock'   => 'fas fa-clock',
            'diamond' => 'fas fa-check-circle',
            'hash'    => 'fas fa-hashtag',
        ];

        $value = trim($iconConfig);
        if ($value === '') {
            return [];
        }

        $value = $legacyMap[$value] ?? $value;
        if (strpos($value, 'fa-') === false) {
            return [];
        }

        $library = 'fa-solid';
        if (strpos($value, 'fab ') === 0) {
            $library = 'fa-brands';
        } elseif (strpos($value, 'far ') === 0) {
            $library = 'fa-regular';
        }

        return [
            'value' => $value,
            'library' => $library,
        ];
    }

    function homlity_card_feature_icon($iconConfig, string $fallback): string
    {
        $iconConfig = homlity_normalize_card_icon_config($iconConfig);
        if (!empty($iconConfig['value']) && class_exists('\Homlity\PluginInmobiliario\Services\IconRenderer')) {
            ob_start();
            \Homlity\PluginInmobiliario\Services\IconRenderer::render($iconConfig, ['aria-hidden' => 'true']);
            $iconHtml = ob_get_clean();
            if (is_string($iconHtml) && $iconHtml !== '') {
                return $iconHtml;
            }
        }
        return esc_html($fallback);
    }
}
if (!function_exists('homlity_card_format_area')) {
    /**
     * Ensures area values end with one, and only one, square-metre unit.
     *
     * @param mixed $value
     */
    function homlity_card_format_area($value): string
    {
        $area = trim(wp_strip_all_tags((string) $value));
        if ($area === '') {
            return '';
        }

        $area = preg_replace(
            '/(?:\s*(?:m\s*(?:2|²)|mts?\.?\s*(?:2|²)|metros?\s+(?:cuadrados?|2|²)))+\s*$/iu',
            '',
            $area
        );

        return rtrim((string) $area) . ' m²';
    }
}

$meta            = (new PropertyPostType())->metaKeys();
$currencyService = new CurrencyService();
$settings        = get_option(HOMLITY_PLUGIN_SETTINGS_OPTION, []);
$listingFields   = $settings['listing_fields'] ?? ['price', 'excerpt', 'features', 'whatsapp'];
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
    'feature_icon_area' => ['value' => 'fas fa-ruler-combined', 'library' => 'fa-solid'],
    'feature_icon_bedrooms' => ['value' => 'fas fa-bed', 'library' => 'fa-solid'],
    'feature_icon_bathrooms' => ['value' => 'fas fa-bath', 'library' => 'fa-solid'],
    'feature_icon_parking' => ['value' => 'fas fa-car', 'library' => 'fa-solid'],
    'feature_icon_area_lot' => ['value' => 'fas fa-draw-polygon', 'library' => 'fa-solid'],
    'feature_icon_area_private' => ['value' => 'fas fa-house', 'library' => 'fa-solid'],
    'feature_icon_area_built' => ['value' => 'fas fa-ruler', 'library' => 'fa-solid'],
    'feature_icon_age' => ['value' => 'fas fa-clock', 'library' => 'fa-solid'],
    'feature_icon_condition' => ['value' => 'fas fa-check-circle', 'library' => 'fa-solid'],
    'feature_icon_code' => ['value' => 'fas fa-hashtag', 'library' => 'fa-solid'],
    'link_new_tab' => false,
], $cardOptions);

// Pricing
$priceSale     = get_post_meta($post_id, $meta['price_sale'], true);
$priceRent     = get_post_meta($post_id, $meta['price_rent'], true);
$currencySale  = get_post_meta($post_id, $meta['currency_sale'], true) ?: $currencyService->baseCurrency();
$currencyRent  = get_post_meta($post_id, $meta['currency_rent'], true) ?: $currencyService->baseCurrency();
$priceAdmin    = get_post_meta($post_id, $meta['price_admin'], true);
$adminIncluded = (bool) get_post_meta($post_id, $meta['admin_included'], true);

// Physical features
$area      = get_post_meta($post_id, $meta['area'], true);
$areaLot   = get_post_meta($post_id, $meta['area_lot'], true);
$areaBuilt = get_post_meta($post_id, $meta['area_built'], true);
$areaPrivate = get_post_meta($post_id, $meta['area_private'], true);
$bedrooms  = get_post_meta($post_id, $meta['bedrooms'], true);
$bathrooms = get_post_meta($post_id, $meta['bathrooms'], true);
$parking   = get_post_meta($post_id, $meta['parking'], true);
$condition = get_post_meta($post_id, $meta['condition'], true);
$age       = get_post_meta($post_id, $meta['age'], true);
$code      = PropertyCodeResolver::forDisplay((int) $post_id);

// Agent / contact
$agentId       = (int) get_post_meta($post_id, $meta['agent_id'], true);
$agentPhone    = get_post_meta($post_id, $meta['agent_phone'], true);
if (!$agentPhone && $agentId > 0) {
    $agentPhone = (string) get_user_meta($agentId, 'homlity_plugin_phone', true);
    if (!$agentPhone) {
        $agentPhone = (string) get_user_meta($agentId, 'billing_phone', true);
    }
}
$featured      = (bool) get_post_meta($post_id, $meta['featured'], true);

// Taxonomy labels
$operationTerms = wp_get_object_terms($post_id, PropertyTaxonomies::TAXONOMY_OPERATION, ['fields' => 'names']);
$typeTerms      = wp_get_object_terms($post_id, PropertyTaxonomies::TAXONOMY_TYPE,      ['fields' => 'names']);
$cityTerms      = wp_get_object_terms($post_id, PropertyTaxonomies::TAXONOMY_CITY,      ['fields' => 'names']);
$neighborhood   = wp_get_object_terms($post_id, PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD, ['fields' => 'names']);
$tagTerms       = wp_get_object_terms($post_id, PropertyTaxonomies::TAXONOMY_TAG, ['fields' => 'names']);

$operationLabel = (!is_wp_error($operationTerms) && $operationTerms) ? $operationTerms[0] : '';
$typeLabel      = (!is_wp_error($typeTerms)      && $typeTerms)      ? $typeTerms[0]      : '';
$cityLabel      = (!is_wp_error($cityTerms)      && $cityTerms)      ? $cityTerms[0]      : '';
$neighborLabel  = (!is_wp_error($neighborhood)   && $neighborhood)   ? $neighborhood[0]   : '';
$tagTerms       = (!is_wp_error($tagTerms) && is_array($tagTerms)) ? array_slice($tagTerms, 0, 4) : [];

// Cover image
$coverImg = get_the_post_thumbnail_url($post_id, 'large') ?: '';
$coverImg = $coverImg ?: (string) get_post_meta($post_id, '_property_featured_image_url', true);
$galleryImages = [];
if ($coverImg) {
    $galleryImages[] = $coverImg;
}
if ($cardOptions['media_mode'] === 'slider' || empty($galleryImages)) {
    $rawGallery = get_post_meta($post_id, $meta['gallery'], true);
    $galleryIds = array_filter(array_map('absint', explode(',', (string) $rawGallery)));
    foreach ($galleryIds as $attachmentId) {
        $url = wp_get_attachment_image_url($attachmentId, 'large');
        if ($url && !in_array($url, $galleryImages, true)) {
            $galleryImages[] = $url;
        }
        if (count($galleryImages) >= 6) {
            break;
        }
    }
    if (count($galleryImages) < 1) {
        foreach (homlity_card_extract_urls($rawGallery) as $url) {
            if (!in_array($url, $galleryImages, true)) {
                $galleryImages[] = $url;
            }
            if (count($galleryImages) >= 6) {
                break;
            }
        }
    }
}

// WhatsApp link
$whatsAppLink = '';
if (!empty($cardOptions['show_whatsapp'])) {
    $whatsAppLink = WhatsAppLinkService::buildPropertyLink((int) $post_id, (string) $agentPhone);
}
$showWhatsappIcon = !empty($cardOptions['whatsapp_show_icon']);
$whatsappIconPosition = ($cardOptions['whatsapp_icon_position'] ?? 'left') === 'right' ? 'right' : 'left';
$whatsappIcon = is_array($cardOptions['whatsapp_icon'] ?? null) ? $cardOptions['whatsapp_icon'] : [];

// Formatted price display
$displayPrice = '';
if ($priceSale) {
    $displayPrice = homlity_plugin_apply_filters('homlity_plugin_format_price', null, $priceSale, $currencySale);
} elseif ($priceRent) {
    $displayPrice = homlity_plugin_apply_filters('homlity_plugin_format_price', null, $priceRent, $currencyRent);
}
$showPrice = !empty($cardOptions['show_price']) && in_array('price', $listingFields, true);
$visualPreset = (string) ($cardOptions['visual_preset'] ?? 'default');
$isCoverOverlayPreset = ($visualPreset === 'cover_overlay');
$presetClass = $visualPreset !== 'default' ? ' property-card--preset-' . sanitize_html_class(str_replace('_', '-', $visualPreset)) : '';
$hoverEffect = (string) ($cardOptions['hover_effect'] ?? 'lift');
$hoverClass = ' property-card--hover-' . sanitize_html_class($hoverEffect);
?>
<div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
    <article class="card h-100 shadow-sm border-0 property-card-bs<?php echo esc_attr($presetClass . $hoverClass); ?>"
             itemscope itemtype="https://schema.org/Product"
             data-property-id="<?php echo esc_attr($post_id); ?>"
             style="border-radius:16px;overflow:hidden;">

        <meta itemprop="url" content="<?php echo esc_url(get_permalink($post_id)); ?>"/>
        <?php if ($featured && empty($tagTerms)) : ?>
            <span class="badge bg-primary position-absolute top-0 end-0 m-2 property-card__featured-badge" style="z-index:1;">
                <?php esc_html_e('Destacado', 'homlity-real-estate'); ?>
            </span>
        <?php endif; ?>

        <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="text-decoration-none"<?php if (!empty($cardOptions['link_new_tab'])): ?> target="_blank" rel="noopener noreferrer"<?php endif; ?>>
            <?php if (!empty($tagTerms)): ?>
                <div class="property-card__media-tags">
                    <?php foreach ($tagTerms as $tagName): ?>
                        <span class="property-card__media-tag"><?php echo esc_html($tagName); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($cardOptions['media_mode'] === 'slider' && count($galleryImages) > 1): ?>
                <div class="property-card__gallery-slider property-card__gallery-slider--bootstrap swiper" data-homlity-card-swiper="1">
                    <div class="swiper-wrapper">
                        <?php foreach ($galleryImages as $img): ?>
                            <div class="swiper-slide">
                                <img src="<?php echo esc_url($img); ?>"
                                     alt="<?php echo esc_attr(get_the_title($post_id)); ?>"
                                     itemprop="image"
                                     class="card-img-top"
                                     style="height:220px;object-fit:cover;">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="swiper-pagination"></div>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                </div>
            <?php else: ?>
                <img src="<?php echo esc_url($galleryImages[0] ?? $coverImg); ?>"
                     alt="<?php echo esc_attr(get_the_title($post_id)); ?>"
                     itemprop="image"
                     class="card-img-top"
                     style="height:220px;object-fit:cover;"
                     onerror="this.style.display='none'">
            <?php endif; ?>

            <?php if ($isCoverOverlayPreset): ?>
                <div class="property-card__overlay">
                    <?php if ((!empty($cardOptions['show_operation']) && ($typeLabel || $operationLabel)) || ($displayPrice && $showPrice)) : ?>
                        <p class="property-card__operation property-card__overlay-operation">
                            <?php if (!empty($cardOptions['show_operation']) && ($typeLabel || $operationLabel)) : ?>
                                <span class="property-card__operation-label">
                                    <?php echo esc_html(implode(' ', array_filter([$typeLabel, $operationLabel ? __('en', 'homlity-real-estate') . ' ' . $operationLabel : '']))); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($displayPrice && $showPrice) : ?>
                                <span class="property-card__operation-price property-card__overlay-price" itemprop="price"><?php echo esc_html($displayPrice); ?></span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($cardOptions['show_title'])) : ?>
                        <p class="property-card__overlay-title"><?php echo esc_html(get_the_title($post_id)); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($cardOptions['show_features'])) : ?>
                        <div class="property-card__overlay-features">
                            <?php if (!empty($cardOptions['feature_area']) && $area) : ?>
                                <span class="property-card__overlay-chip property-card__feature-item">
                                    <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_area'] ?? [], '▦'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                    <span class="property-card__feature-value"><?php echo esc_html(homlity_card_format_area($area)); ?></span>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($cardOptions['feature_bedrooms']) && $bedrooms) : ?>
                                <span class="property-card__overlay-chip property-card__feature-item">
                                    <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_bedrooms'] ?? [], '🛏'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                    <span class="property-card__feature-value"><?php echo esc_html($bedrooms); ?></span>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($cardOptions['feature_bathrooms']) && $bathrooms) : ?>
                                <span class="property-card__overlay-chip property-card__feature-item">
                                    <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_bathrooms'] ?? [], '🛁'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                    <span class="property-card__feature-value"><?php echo esc_html($bathrooms); ?></span>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($cardOptions['feature_parking']) && $parking) : ?>
                                <span class="property-card__overlay-chip property-card__feature-item">
                                    <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_parking'] ?? [], '🚗'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                    <span class="property-card__feature-value"><?php echo esc_html($parking); ?></span>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </a>

        <div class="card-body d-flex flex-column gap-2 p-3">

            <?php if (!$isCoverOverlayPreset && !empty($cardOptions['show_title'])) : ?>
                <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="text-decoration-none text-dark"<?php if (!empty($cardOptions['link_new_tab'])): ?> target="_blank" rel="noopener noreferrer"<?php endif; ?>>
                    <p class="card-title fs-6 mb-0 fw-semibold" itemprop="name">
                        <?php echo esc_html(get_the_title($post_id)); ?>
                    </p>
                </a>
            <?php endif; ?>

            <?php if (!$isCoverOverlayPreset && ((!empty($cardOptions['show_operation']) && ($typeLabel || $operationLabel)) || ($displayPrice && $showPrice))) : ?>
            <p class="mb-0 small text-muted property-card__operation">
                <?php if (!empty($cardOptions['show_operation']) && ($typeLabel || $operationLabel)) : ?>
                    <span class="property-card__operation-label">
                        <?php echo esc_html(implode(' ', array_filter([$typeLabel, $operationLabel ? __('en', 'homlity-real-estate') . ' ' . $operationLabel : '']))); ?>
                    </span>
                <?php endif; ?>
                <?php if ($displayPrice && $showPrice) : ?>
                    <span class="property-card__operation-price property-card__price fw-bold fs-5 text-primary" itemprop="price">
                        <?php echo esc_html($displayPrice); ?>
                        <?php if ($priceRent && $priceAdmin && !$adminIncluded) : ?>
                            <small class="fs-6 fw-normal text-muted">
                                + <?php echo esc_html(homlity_plugin_apply_filters('homlity_plugin_format_price', null, $priceAdmin, $currencyRent)); ?>
                                <?php esc_html_e('adm.', 'homlity-real-estate'); ?>
                            </small>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </p>
            <?php endif; ?>

            <?php if ($cityLabel || $neighborLabel) : ?>
            <p class="mb-0 small text-muted property-card__location"
               itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M8 1C5.24 1 3 3.24 3 6c0 4 5 9 5 9s5-5 5-9c0-2.76-2.24-5-5-5zm0 6.5A1.5 1.5 0 1 1 8 4a1.5 1.5 0 0 1 0 3.5z"/>
                </svg>
                <meta itemprop="addressLocality" content="<?php echo esc_attr($cityLabel); ?>"/>
                <?php echo esc_html(implode(', ', array_filter([$neighborLabel, $cityLabel]))); ?>
            </p>
            <?php endif; ?>

            <?php if (!$isCoverOverlayPreset && !empty($cardOptions['show_excerpt']) && in_array('excerpt', $listingFields, true)) : ?>
            <p class="card-text small text-muted mb-0 property-card__excerpt" itemprop="description">
                <?php echo esc_html(wp_trim_words(get_the_excerpt($post_id), 18)); ?>
            </p>
            <?php endif; ?>

            <?php if (!$isCoverOverlayPreset && !empty($cardOptions['show_features'])) : ?>
            <ul class="list-inline mb-0 small d-flex flex-wrap gap-2 property-card__features">
                <?php if (!empty($cardOptions['feature_area']) && $area) : ?>
                <li class="list-inline-item text-muted property-card__feature-item" title="<?php esc_attr_e('Área', 'homlity-real-estate'); ?>">
                    <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_area'] ?? [], '▦'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="property-card__feature-value"><?php echo esc_html(homlity_card_format_area($area)); ?></span>
                </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_bedrooms']) && $bedrooms) : ?>
                <li class="list-inline-item text-muted property-card__feature-item" title="<?php esc_attr_e('Alcobas', 'homlity-real-estate'); ?>">
                    <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_bedrooms'] ?? [], '🛏'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="property-card__feature-value"><?php echo esc_html($bedrooms); ?></span>
                </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_bathrooms']) && $bathrooms) : ?>
                <li class="list-inline-item text-muted property-card__feature-item" title="<?php esc_attr_e('Baños', 'homlity-real-estate'); ?>">
                    <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_bathrooms'] ?? [], '🛁'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="property-card__feature-value"><?php echo esc_html($bathrooms); ?></span>
                </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_parking']) && $parking) : ?>
                <li class="list-inline-item text-muted property-card__feature-item" title="<?php esc_attr_e('Garajes', 'homlity-real-estate'); ?>">
                    <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_parking'] ?? [], '🚗'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="property-card__feature-value"><?php echo esc_html($parking); ?></span>
                </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_area_lot']) && $areaLot) : ?>
                <li class="list-inline-item text-muted property-card__feature-item" title="<?php esc_attr_e('Área lote', 'homlity-real-estate'); ?>">
                    <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_area_lot'] ?? [], '▣'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="property-card__feature-value"><?php echo esc_html(homlity_card_format_area($areaLot)); ?></span>
                </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_area_private']) && $areaPrivate) : ?>
                <li class="list-inline-item text-muted property-card__feature-item" title="<?php esc_attr_e('Área privada', 'homlity-real-estate'); ?>">
                    <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_area_private'] ?? [], '◫'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="property-card__feature-value"><?php echo esc_html(homlity_card_format_area($areaPrivate)); ?></span>
                </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_area_built']) && $areaBuilt) : ?>
                <li class="list-inline-item text-muted property-card__feature-item" title="<?php esc_attr_e('Área construida', 'homlity-real-estate'); ?>">
                    <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_area_built'] ?? [], '◧'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="property-card__feature-value"><?php echo esc_html(homlity_card_format_area($areaBuilt)); ?></span>
                </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_age']) && $age) : ?>
                <li class="list-inline-item text-muted property-card__feature-item" title="<?php esc_attr_e('Año/Edad', 'homlity-real-estate'); ?>">
                    <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_age'] ?? [], '◷'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="property-card__feature-value"><?php echo esc_html($age); ?></span>
                </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_condition']) && $condition) : ?>
                <li class="list-inline-item text-muted property-card__feature-item" title="<?php esc_attr_e('Estado', 'homlity-real-estate'); ?>">
                    <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_condition'] ?? [], '◆'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="property-card__feature-value"><?php echo esc_html($condition); ?></span>
                </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_code']) && $code) : ?>
                <li class="list-inline-item text-muted property-card__feature-item" title="<?php esc_attr_e('Código', 'homlity-real-estate'); ?>">
                    <span class="property-card__feature-icon" aria-hidden="true"><?php echo homlity_card_feature_icon($cardOptions['feature_icon_code'] ?? [], '#'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="property-card__feature-value"><?php echo esc_html($code); ?></span>
                </li>
                <?php endif; ?>
            </ul>
            <?php endif; ?>

            <?php if ($whatsAppLink) : ?>
            <a href="<?php echo esc_url($whatsAppLink); ?>"
               class="btn btn-success btn-sm mt-auto property-card__whatsapp property-card__whatsapp--icon-<?php echo esc_attr($whatsappIconPosition); ?>"
               target="_blank" rel="noopener noreferrer"
               data-homlity-contact-type="whatsapp"
               data-property-id="<?php echo esc_attr($post_id); ?>">
                <?php if ($showWhatsappIcon): ?>
                    <span class="property-card__whatsapp-icon" aria-hidden="true">
                        <?php if (!empty($whatsappIcon['value']) && class_exists('\Homlity\PluginInmobiliario\Services\IconRenderer')) : ?>
                            <?php \Homlity\PluginInmobiliario\Services\IconRenderer::render($whatsappIcon, ['aria-hidden' => 'true']); ?>
                        <?php else : ?>
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326z"/></svg>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
                <span class="property-card__whatsapp-label"><?php echo esc_html($cardOptions['whatsapp_label'] ?: __('Más información', 'homlity-real-estate')); ?></span>
            </a>
            <?php endif; ?>

        </div>
    </article>
</div>
