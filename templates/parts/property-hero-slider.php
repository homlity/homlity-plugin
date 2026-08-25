<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Hero slider component: a full-bleed carousel of properties, meant for the
 * main hero of a real-estate home page.
 * Overridable at homlity-real-estate/parts/property-hero-slider.php inside a
 * theme or child theme.
 *
 * Expected args:
 *   $query        (\WP_Query)  properties to slide through
 *   $options      (array)      presentation options built by the widget
 *   $card_options (array)      card options, used only by the 'cards' layout
 */

use Homlity\PluginInmobiliario\Services\IconRenderer;
use Homlity\PluginInmobiliario\Services\PropertyCodeResolver;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\TemplateService;
use Homlity\PluginInmobiliario\Services\WhatsAppLinkService;

if (!isset($query) || !($query instanceof WP_Query)) {
    return;
}

if (!function_exists('homlity_hero_slider_icon')) {
    /**
     * Render an Elementor icon control value, falling back to a plain glyph
     * when the icon is empty or no renderer is available.
     *
     * @param mixed $iconConfig
     */
    function homlity_hero_slider_icon($iconConfig, string $fallback): string
    {
        if (is_string($iconConfig) && $iconConfig !== '') {
            $iconConfig = ['value' => $iconConfig, 'library' => 'fa-solid'];
        }

        if (is_array($iconConfig) && !empty($iconConfig['value'])) {
            ob_start();
            IconRenderer::render($iconConfig, ['aria-hidden' => 'true']);
            $html = (string) ob_get_clean();
            if ($html !== '') {
                return $html;
            }
        }

        return $fallback !== '' ? esc_html($fallback) : '';
    }
}

if (!function_exists('homlity_hero_slider_area')) {
    /**
     * Ensures area values end with one, and only one, square-metre unit.
     *
     * @param mixed $value
     */
    function homlity_hero_slider_area($value): string
    {
        if (function_exists('homlity_card_format_area')) {
            return homlity_card_format_area($value);
        }

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

if (!function_exists('homlity_hero_slider_location')) {
    /**
     * Build the human-readable location line, from the most specific term
     * available down to the country.
     */
    function homlity_hero_slider_location(int $postId): string
    {
        $taxonomies = [
            PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
            PropertyTaxonomies::TAXONOMY_CITY,
            PropertyTaxonomies::TAXONOMY_STATE,
            PropertyTaxonomies::TAXONOMY_COUNTRY,
        ];

        $parts = [];
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_post_terms($postId, $taxonomy, ['fields' => 'names']);
            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }
            $name = trim((string) $terms[0]);
            if ($name !== '' && !in_array($name, $parts, true)) {
                $parts[] = $name;
            }
        }

        return implode(', ', array_slice($parts, 0, 3));
    }
}

$options = isset($options) && is_array($options) ? $options : [];
$options = array_merge([
    'layout'            => 'hero',   // 'hero' | 'split' | 'cards'
    'slides_desktop'    => 1,
    'slides_tablet'     => 1,
    'slides_mobile'     => 1,
    'autoplay'          => true,
    'autoplay_delay'    => 5000,
    'pause_on_hover'    => true,
    'loop'              => true,
    'effect'            => 'slide',  // 'slide' | 'fade'
    'speed'             => 600,
    'show_arrows'       => true,
    'show_pagination'   => true,
    'pagination_type'   => 'bullets', // 'bullets' | 'fraction' | 'progressbar'
    'kenburns'          => false,
    'show_operation'    => true,
    'show_title'        => true,
    'show_location'     => true,
    'show_price'        => true,
    'show_features'     => true,
    'show_excerpt'      => false,
    'show_code'         => false,
    'feature_area'      => true,
    'feature_bedrooms'  => true,
    'feature_bathrooms' => true,
    'feature_parking'   => true,
    'feature_icon_area'      => ['value' => 'fas fa-ruler-combined', 'library' => 'fa-solid'],
    'feature_icon_bedrooms'  => ['value' => 'fas fa-bed', 'library' => 'fa-solid'],
    'feature_icon_bathrooms' => ['value' => 'fas fa-bath', 'library' => 'fa-solid'],
    'feature_icon_parking'   => ['value' => 'fas fa-car', 'library' => 'fa-solid'],
    'location_icon'     => ['value' => 'fas fa-location-dot', 'library' => 'fa-solid'],
    'show_button'       => true,
    'button_label'      => '',
    'button_icon'       => [],
    'show_whatsapp'     => false,
    'whatsapp_label'    => '',
    'whatsapp_icon'     => ['value' => 'fab fa-whatsapp', 'library' => 'fa-brands'],
    'link_new_tab'      => false,
    'link_whole_slide'  => true,
    'excerpt_words'     => 22,
], $options);

$cardOptions = isset($card_options) && is_array($card_options) ? $card_options : [];

if (!$query->have_posts()) {
    if (!empty($options['empty_message'])) {
        printf(
            '<p class="hml-hero-slider__empty">%s</p>',
            esc_html((string) $options['empty_message'])
        );
    }
    return;
}

$isCardsLayout = ($options['layout'] === 'cards');

// The card layout reuses the property card as the slide, so the hero-specific
// content options do not apply to it.
$sliderId = 'hml-hero-slider-' . wp_unique_id();

$meta            = (new PropertyPostType())->metaKeys();
$buttonLabel     = (string) ($options['button_label'] ?: __('Ver inmueble', 'homlity-real-estate'));
$whatsappLabel   = (string) ($options['whatsapp_label'] ?: __('WhatsApp', 'homlity-real-estate'));
$linkTargetAttrs = !empty($options['link_new_tab']) ? ' target="_blank" rel="noopener noreferrer"' : '';

// Placement is driven by custom properties the widget writes per device, so
// no position class is emitted here.
$wrapperClasses = [
    'hml-hero-slider',
    'hml-hero-slider--' . sanitize_html_class($options['layout']),
];
if (!empty($options['effect']) && $options['effect'] === 'fade') {
    $wrapperClasses[] = 'hml-hero-slider--fade';
}
if (!empty($options['kenburns']) && !$isCardsLayout) {
    $wrapperClasses[] = 'hml-hero-slider--kenburns';
}
?>
<div id="<?php echo esc_attr($sliderId); ?>"
     class="<?php echo esc_attr(implode(' ', $wrapperClasses)); ?>"
     data-homlity-hero-slider="1"
     data-layout="<?php echo esc_attr($options['layout']); ?>"
     data-slides-desktop="<?php echo esc_attr((string) $options['slides_desktop']); ?>"
     data-slides-tablet="<?php echo esc_attr((string) $options['slides_tablet']); ?>"
     data-slides-mobile="<?php echo esc_attr((string) $options['slides_mobile']); ?>"
     data-autoplay="<?php echo !empty($options['autoplay']) ? '1' : '0'; ?>"
     data-autoplay-delay="<?php echo esc_attr((string) $options['autoplay_delay']); ?>"
     data-pause-on-hover="<?php echo !empty($options['pause_on_hover']) ? '1' : '0'; ?>"
     data-loop="<?php echo !empty($options['loop']) ? '1' : '0'; ?>"
     data-effect="<?php echo esc_attr($options['effect']); ?>"
     data-speed="<?php echo esc_attr((string) $options['speed']); ?>"
     data-show-arrows="<?php echo !empty($options['show_arrows']) ? '1' : '0'; ?>"
     data-show-pagination="<?php echo !empty($options['show_pagination']) ? '1' : '0'; ?>"
     data-pagination-type="<?php echo esc_attr($options['pagination_type']); ?>">

    <div class="swiper hml-hero-slider__swiper">
        <div class="swiper-wrapper">
            <?php
            while ($query->have_posts()) :
                $query->the_post();
                $postId    = get_the_ID();
                $permalink = get_permalink($postId);
                ?>
                <div class="swiper-slide hml-hero-slider__slide">
                    <?php if ($isCardsLayout) : ?>
                        <?php TemplateService::includeComponent('property-card.php', [
                            'post_id'      => $postId,
                            'card_options' => $cardOptions,
                        ]); ?>
                    <?php else : ?>
                        <?php
                        $image = get_the_post_thumbnail_url($postId, 'full');
                        if (!$image) {
                            $image = (string) get_post_meta($postId, '_property_featured_image_url', true);
                        }

                        $operationTerms = wp_get_post_terms($postId, PropertyTaxonomies::TAXONOMY_OPERATION, ['fields' => 'names']);
                        $operationLabel = (!is_wp_error($operationTerms) && !empty($operationTerms))
                            ? (string) $operationTerms[0]
                            : '';

                        $locationLabel = homlity_hero_slider_location($postId);

                        $price     = get_post_meta($postId, $meta['price_sale'], true);
                        $priceRent = get_post_meta($postId, $meta['price_rent'], true);
                        $currency  = get_post_meta($postId, $meta['currency_sale'], true);
                        $currencyRent = get_post_meta($postId, $meta['currency_rent'], true);

                        $displayPrice = '';
                        if (!empty($options['show_price'])) {
                            if ($price) {
                                $displayPrice = homlity_plugin_apply_filters('homlity_plugin_format_price', null, $price, $currency);
                            } elseif ($priceRent) {
                                $displayPrice = homlity_plugin_apply_filters('homlity_plugin_format_price', null, $priceRent, $currencyRent);
                            }
                        }

                        $features = [];
                        if (!empty($options['show_features'])) {
                            $featureMap = [
                                'area'      => [$meta['area'], 'feature_icon_area', '▦'],
                                'bedrooms'  => [$meta['bedrooms'], 'feature_icon_bedrooms', '🛏'],
                                'bathrooms' => [$meta['bathrooms'], 'feature_icon_bathrooms', '🛁'],
                                'parking'   => [$meta['parking'], 'feature_icon_parking', '🚗'],
                            ];
                            foreach ($featureMap as $key => [$metaKey, $iconKey, $fallbackIcon]) {
                                if (empty($options['feature_' . $key])) {
                                    continue;
                                }
                                $value = get_post_meta($postId, $metaKey, true);
                                if (!$value) {
                                    continue;
                                }
                                $features[] = [
                                    'icon'  => homlity_hero_slider_icon($options[$iconKey] ?? [], $fallbackIcon),
                                    'value' => $key === 'area' ? homlity_hero_slider_area($value) : (string) $value,
                                ];
                            }
                        }

                        $whatsAppLink = '';
                        if (!empty($options['show_whatsapp'])) {
                            $agentPhone = (string) get_post_meta($postId, $meta['agent_phone'], true);
                            $whatsAppLink = WhatsAppLinkService::buildPropertyLink((int) $postId, $agentPhone);
                        }
                        ?>
                        <div class="hml-hero-slider__media">
                            <?php if ($image) : ?>
                                <img class="hml-hero-slider__image"
                                     src="<?php echo esc_url($image); ?>"
                                     alt="<?php echo esc_attr(get_the_title($postId)); ?>"
                                     loading="lazy" />
                            <?php endif; ?>
                            <span class="hml-hero-slider__scrim" aria-hidden="true"></span>
                        </div>

                        <div class="hml-hero-slider__content">
                            <?php if (!empty($options['show_operation']) && $operationLabel) : ?>
                                <span class="hml-hero-slider__operation"><?php echo esc_html($operationLabel); ?></span>
                            <?php endif; ?>

                            <?php if (!empty($options['show_title'])) : ?>
                                <h2 class="hml-hero-slider__title">
                                    <a href="<?php echo esc_url($permalink); ?>"<?php echo $linkTargetAttrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                                        <?php echo esc_html(get_the_title($postId)); ?>
                                    </a>
                                </h2>
                            <?php endif; ?>

                            <?php if (!empty($options['show_location']) && $locationLabel) : ?>
                                <p class="hml-hero-slider__location">
                                    <span class="hml-hero-slider__location-icon" aria-hidden="true"><?php echo homlity_hero_slider_icon($options['location_icon'] ?? [], '📍'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                    <span><?php echo esc_html($locationLabel); ?></span>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($options['show_excerpt'])) : ?>
                                <?php $excerpt = wp_trim_words(get_the_excerpt($postId), max(1, (int) $options['excerpt_words'])); ?>
                                <?php if ($excerpt) : ?>
                                    <p class="hml-hero-slider__excerpt"><?php echo esc_html($excerpt); ?></p>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($features) : ?>
                                <ul class="hml-hero-slider__features">
                                    <?php foreach ($features as $feature) : ?>
                                        <li class="hml-hero-slider__feature">
                                            <span class="hml-hero-slider__feature-icon" aria-hidden="true"><?php echo $feature['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                            <span class="hml-hero-slider__feature-value"><?php echo esc_html($feature['value']); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if ($displayPrice) : ?>
                                <p class="hml-hero-slider__price"><?php echo esc_html($displayPrice); ?></p>
                            <?php endif; ?>

                            <?php if (!empty($options['show_code'])) : ?>
                                <?php $code = PropertyCodeResolver::forDisplay((int) $postId); ?>
                                <?php if ($code) : ?>
                                    <p class="hml-hero-slider__code"><?php echo esc_html($code); ?></p>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if (!empty($options['show_button']) || $whatsAppLink) : ?>
                                <div class="hml-hero-slider__actions">
                                    <?php if (!empty($options['show_button'])) : ?>
                                        <a class="hml-hero-slider__button"
                                           href="<?php echo esc_url($permalink); ?>"<?php echo $linkTargetAttrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                                            <span class="hml-hero-slider__button-label"><?php echo esc_html($buttonLabel); ?></span>
                                            <?php $buttonIcon = homlity_hero_slider_icon($options['button_icon'] ?? [], ''); ?>
                                            <?php if ($buttonIcon) : ?>
                                                <span class="hml-hero-slider__button-icon" aria-hidden="true"><?php echo $buttonIcon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                            <?php endif; ?>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($whatsAppLink) : ?>
                                        <a class="hml-hero-slider__whatsapp"
                                           href="<?php echo esc_url($whatsAppLink); ?>"
                                           target="_blank" rel="noopener noreferrer">
                                            <?php $waIcon = homlity_hero_slider_icon($options['whatsapp_icon'] ?? [], ''); ?>
                                            <?php if ($waIcon) : ?>
                                                <span class="hml-hero-slider__whatsapp-icon" aria-hidden="true"><?php echo $waIcon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                            <?php endif; ?>
                                            <span><?php echo esc_html($whatsappLabel); ?></span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($options['link_whole_slide'])) : ?>
                            <a class="hml-hero-slider__overlay-link"
                               href="<?php echo esc_url($permalink); ?>"<?php echo $linkTargetAttrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                                <span class="screen-reader-text"><?php echo esc_html(get_the_title($postId)); ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>

        <?php if (!empty($options['show_pagination'])) : ?>
            <div class="swiper-pagination hml-hero-slider__pagination"></div>
        <?php endif; ?>
    </div>

    <?php if (!empty($options['show_arrows'])) : ?>
        <div class="swiper-button-prev hml-hero-slider__arrow hml-hero-slider__arrow--prev"></div>
        <div class="swiper-button-next hml-hero-slider__arrow hml-hero-slider__arrow--next"></div>
    <?php endif; ?>
</div>
<?php
wp_reset_postdata();
