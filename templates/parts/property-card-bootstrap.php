<?php
/**
 * Property card – Bootstrap 5 variant.
 * Overridable at homlity-plugin/parts/property-card-bootstrap.php in theme or child theme.
 *
 * Expected args: $post_id (int)
 *
 * IMPORTANT: This template outputs a column wrapper (col-*) so that cards are grid
 * items when injected into a Bootstrap `.row` container via AJAX.
 */

use Homlity\PluginInmobiliario\Services\CurrencyService;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!isset($post_id)) {
    $post_id = get_the_ID();
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
$code      = get_post_meta($post_id, $meta['code'], true);

// Agent / contact
$agentId       = (int) get_post_meta($post_id, $meta['agent_id'], true);
$agentPhone    = get_post_meta($post_id, $meta['agent_phone'], true);
$featured      = (bool) get_post_meta($post_id, $meta['featured'], true);
$agentDigits   = $agentPhone ? preg_replace('/\D+/', '', $agentPhone) : '';

// Taxonomy labels
$operationTerms = wp_get_object_terms($post_id, PropertyTaxonomies::TAXONOMY_OPERATION, ['fields' => 'names']);
$typeTerms      = wp_get_object_terms($post_id, PropertyTaxonomies::TAXONOMY_TYPE,      ['fields' => 'names']);
$cityTerms      = wp_get_object_terms($post_id, PropertyTaxonomies::TAXONOMY_CITY,      ['fields' => 'names']);
$neighborhood   = wp_get_object_terms($post_id, PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD, ['fields' => 'names']);

$operationLabel = (!is_wp_error($operationTerms) && $operationTerms) ? $operationTerms[0] : '';
$typeLabel      = (!is_wp_error($typeTerms)      && $typeTerms)      ? $typeTerms[0]      : '';
$cityLabel      = (!is_wp_error($cityTerms)      && $cityTerms)      ? $cityTerms[0]      : '';
$neighborLabel  = (!is_wp_error($neighborhood)   && $neighborhood)   ? $neighborhood[0]   : '';

// Cover image
$coverImg = get_the_post_thumbnail_url($post_id, 'large') ?: '';
$galleryImages = [];
if ($coverImg) {
    $galleryImages[] = $coverImg;
}
if ($cardOptions['media_mode'] === 'slider') {
    $galleryIds = array_filter(array_map('absint', explode(',', (string) get_post_meta($post_id, $meta['gallery'], true))));
    foreach ($galleryIds as $attachmentId) {
        $url = wp_get_attachment_image_url($attachmentId, 'large');
        if ($url && !in_array($url, $galleryImages, true)) {
            $galleryImages[] = $url;
        }
        if (count($galleryImages) >= 6) {
            break;
        }
    }
}

// WhatsApp link
$whatsAppLink = '';
if ($agentDigits && !empty($cardOptions['show_whatsapp']) && in_array('whatsapp', $listingFields, true)) {
    $msg          = rawurlencode(get_the_title($post_id) . ' – ' . get_permalink($post_id));
    $whatsAppLink = 'https://wa.me/' . $agentDigits . '?text=' . $msg;
}

// Formatted price display
$displayPrice = '';
if ($priceSale) {
    $displayPrice = homlity_plugin_apply_filters('homlity_plugin_format_price', null, $priceSale, $currencySale);
} elseif ($priceRent) {
    $displayPrice = homlity_plugin_apply_filters('homlity_plugin_format_price', null, $priceRent, $currencyRent);
}
$showPrice = !empty($cardOptions['show_price']) && in_array('price', $listingFields, true);
?>
<div class="col-xl-3 col-lg-4 col-md-6 col-12 mb-3">
    <article class="card h-100 shadow-sm border-0 property-card-bs"
             itemscope itemtype="https://schema.org/Product"
             style="border-radius:16px;overflow:hidden;">

        <meta itemprop="url" content="<?php echo esc_url(get_permalink($post_id)); ?>"/>
        <?php if ($featured) : ?>
            <span class="badge bg-primary position-absolute top-0 end-0 m-2" style="z-index:1;">
                <?php esc_html_e('Destacado', 'homlity-plugin'); ?>
            </span>
        <?php endif; ?>

        <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="text-decoration-none">
            <?php if ($cardOptions['media_mode'] === 'slider' && count($galleryImages) > 1): ?>
                <div class="property-card__gallery-slider property-card__gallery-slider--bootstrap">
                    <?php foreach ($galleryImages as $img): ?>
                        <img src="<?php echo esc_url($img); ?>"
                             alt="<?php echo esc_attr(get_the_title($post_id)); ?>"
                             itemprop="image"
                             class="card-img-top"
                             style="height:220px;object-fit:cover;">
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <img src="<?php echo esc_url($coverImg); ?>"
                     alt="<?php echo esc_attr(get_the_title($post_id)); ?>"
                     itemprop="image"
                     class="card-img-top"
                     style="height:220px;object-fit:cover;"
                     onerror="this.style.display='none'">
            <?php endif; ?>
        </a>

        <div class="card-body d-flex flex-column gap-2 p-3">

            <?php if ($displayPrice && $showPrice) : ?>
            <p class="mb-0 fw-bold fs-5 text-primary" itemprop="price">
                <?php echo esc_html($displayPrice); ?>
                <?php if ($priceRent && $priceAdmin && !$adminIncluded) : ?>
                    <small class="fs-6 fw-normal text-muted">
                        + <?php echo esc_html(homlity_plugin_apply_filters('homlity_plugin_format_price', null, $priceAdmin, $currencyRent)); ?>
                        <?php esc_html_e('adm.', 'homlity-plugin'); ?>
                    </small>
                <?php endif; ?>
            </p>
            <?php endif; ?>

            <?php if (!empty($cardOptions['show_title'])) : ?>
                <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="text-decoration-none text-dark">
                    <h2 class="card-title fs-6 mb-0 fw-semibold" itemprop="name">
                        <?php echo esc_html(get_the_title($post_id)); ?>
                    </h2>
                </a>
            <?php endif; ?>

            <?php if (!empty($cardOptions['show_operation']) && ($typeLabel || $operationLabel)) : ?>
            <p class="mb-0 small text-muted property-card__operation">
                <?php echo esc_html(implode(' ', array_filter([$typeLabel, $operationLabel ? __('en', 'homlity-plugin') . ' ' . $operationLabel : '']))); ?>
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

            <?php if (!empty($cardOptions['show_excerpt']) && in_array('excerpt', $listingFields, true)) : ?>
            <p class="card-text small text-muted mb-0 property-card__excerpt" itemprop="description">
                <?php echo esc_html(wp_trim_words(get_the_excerpt($post_id), 18)); ?>
            </p>
            <?php endif; ?>

            <?php if (!empty($cardOptions['show_features']) && in_array('features', $listingFields, true)) : ?>
            <ul class="list-inline mb-0 small d-flex flex-wrap gap-2 property-card__features">
                <?php if (!empty($cardOptions['feature_area']) && $area) : ?>
                <li class="list-inline-item text-muted">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M1 1h6v6H1zm8 0h6v6H9zM1 9h6v6H1zm8 0h6v6H9z"/></svg>
                    <?php echo esc_html($area); ?> m²
                </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_bedrooms']) && $bedrooms) : ?>
                <li class="list-inline-item text-muted">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 2a1 1 0 0 0-1 1v10a1 1 0 0 0 2 0V3a1 1 0 0 0-1-1zm10 0a1 1 0 0 0-1 1v4H4V3a1 1 0 0 0-2 0v10a1 1 0 0 0 2 0v-2h8v2a1 1 0 0 0 2 0V3a1 1 0 0 0-1-1z"/></svg>
                    <?php echo esc_html($bedrooms); ?>
                </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_bathrooms']) && $bathrooms) : ?>
                <li class="list-inline-item text-muted">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M13 3a1 1 0 0 0-2 0v3H2a1 1 0 0 0-1 1v1a4 4 0 0 0 4 4h6a4 4 0 0 0 4-4V7a1 1 0 0 0-1-1h-1V3z"/></svg>
                    <?php echo esc_html($bathrooms); ?>
                </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_parking']) && $parking) : ?>
                <li class="list-inline-item text-muted">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M4 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm1 3v6h1.5V9H7a2 2 0 0 0 0-4H5zm1.5 1H7a.5.5 0 0 1 0 1H6.5V6z"/></svg>
                    <?php echo esc_html($parking); ?>
                </li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_area_lot']) && $areaLot) : ?>
                <li class="list-inline-item text-muted"><?php echo esc_html($areaLot); ?> m² <?php esc_html_e('lote', 'homlity-plugin'); ?></li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_area_private']) && $areaPrivate) : ?>
                <li class="list-inline-item text-muted"><?php echo esc_html($areaPrivate); ?> m² <?php esc_html_e('privada', 'homlity-plugin'); ?></li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_area_built']) && $areaBuilt) : ?>
                <li class="list-inline-item text-muted"><?php echo esc_html($areaBuilt); ?> m² <?php esc_html_e('construida', 'homlity-plugin'); ?></li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_age']) && $age) : ?>
                <li class="list-inline-item text-muted"><?php echo esc_html($age); ?> <?php esc_html_e('años', 'homlity-plugin'); ?></li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_condition']) && $condition) : ?>
                <li class="list-inline-item text-muted"><?php echo esc_html($condition); ?></li>
                <?php endif; ?>
                <?php if (!empty($cardOptions['feature_code']) && $code) : ?>
                <li class="list-inline-item text-muted">#<?php echo esc_html($code); ?></li>
                <?php endif; ?>
            </ul>
            <?php endif; ?>

            <?php if ($whatsAppLink) : ?>
            <a href="<?php echo esc_url($whatsAppLink); ?>"
               class="btn btn-success btn-sm mt-auto property-card__whatsapp"
               target="_blank" rel="noopener noreferrer">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" class="me-1" aria-hidden="true">
                    <path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592z"/>
                </svg>
                <?php echo esc_html($cardOptions['whatsapp_label'] ?: __('Más información', 'homlity-plugin')); ?>
            </a>
            <?php endif; ?>

        </div>
    </article>
</div>
