<?php
/**
 * Shortcodes for the "unavailable / retired property" landing page.
 *
 * These are intended to be dropped into a regular WordPress page built with
 * Elementor (or any page builder) and configured in:
 *   Configuración → Plantillas Elementor → Plantilla inmueble no disponible
 *
 * They read from UnavailablePropertyContext which is populated automatically
 * by PropertyUnavailableService before the page template is rendered.
 *
 * Available shortcodes
 * ─────────────────────────────────────────────────────────────────────────────
 * [homlity_unavailable_notice]
 *   Compact alert banner. Shows property type/city/price chips when available.
 *   Attributes:
 *     title         – Override heading text.
 *     message       – Override body text.
 *     button_text   – CTA label (default: "Ver otros inmuebles").
 *     button_url    – CTA destination (default: archive page URL).
 *     show_icon     – true|false (default: true)
 *     show_summary  – true|false – show property chips (default: true)
 *     layout        – compact|card (default: compact)
 *
 * [homlity_unavailable_similar_properties]
 *   Full listing of similar / related available properties.
 *   Uses query_mode=related_current (RelatedPropertiesQueryBuilder).
 *   Attributes:
 *     per_page    – number (default: 12)
 *     columns     – number (default: 3)
 *     template    – default|bootstrap
 *     sort        – true|false (default: true)
 *     view_toggle – true|false (default: false)
 *     pagination  – true|false (default: true)
 *
 * [homlity_unavailable_search_context]
 *   One-liner: "Resultados basados en: Apartamento · venta · Bogotá"
 *   Attribute: prefix – Override the leading label text.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Listing\ListingConfig;
use Homlity\PluginInmobiliario\Listing\ListingRenderer;

if (!defined('ABSPATH')) {
    exit;
}

class UnavailablePropertyShortcodesService implements ServiceInterface
{
    public function register(): void
    {
        add_shortcode('homlity_unavailable_notice',             [$this, 'renderNotice']);
        add_shortcode('homlity_unavailable_similar_properties', [$this, 'renderSimilarProperties']);
        add_shortcode('homlity_unavailable_search_context',     [$this, 'renderSearchContext']);
        add_action('wp_enqueue_scripts',                        [$this, 'enqueueStyles']);
    }

    /**
     * Enqueue the minimal inline CSS for the unavailable-property shortcodes.
     * Only added when the context is active (i.e., on a retired property URL).
     */
    public function enqueueStyles(): void
    {
        if (!UnavailablePropertyContext::isActive()) {
            return;
        }

        $css = '
.hml-unav-notice{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;
    background:#fff8f0;border:1px solid #fde89a;border-radius:8px;
    padding:.85rem 1.2rem;margin-bottom:1.5rem;}
.hml-unav-notice--card{flex-direction:column;align-items:flex-start;padding:1.25rem 1.5rem;}
.hml-unav-notice__icon{flex-shrink:0;color:#f59e0b;line-height:0;}
.hml-unav-notice__body{flex:1;min-width:0;}
.hml-unav-notice__title{font-size:.95rem;font-weight:700;margin:0 0 .2rem;color:#1a1a1a;}
.hml-unav-notice__message{font-size:.875rem;color:#555;margin:0 0 .5rem;}
.hml-unav-notice__chips{display:flex;flex-wrap:wrap;gap:.35rem;}
.hml-unav-notice__chip{background:#fff;border:1px solid #e5e5e5;border-radius:4px;
    padding:.15rem .5rem;font-size:.8rem;color:#444;}
.hml-unav-notice__action{flex-shrink:0;}
.hml-unav-notice__btn{display:inline-block;padding:.5rem 1.1rem;background:#f59e0b;
    color:#fff;border-radius:5px;font-size:.85rem;font-weight:600;
    text-decoration:none;white-space:nowrap;transition:opacity .15s;}
.hml-unav-notice__btn:hover{opacity:.88;color:#fff;}
.hml-unav-context{font-size:.9rem;color:#555;margin:0 0 1rem;}
.hml-unav-context strong{color:#333;}
@media(max-width:640px){.hml-unav-notice{gap:.75rem;}
    .hml-unav-notice__action{width:100%;}
    .hml-unav-notice__btn{display:block;text-align:center;}}
';

        wp_register_style('homlity-unavailable-shortcodes', false, [], HOMLITY_PLUGIN_VERSION); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
        wp_enqueue_style('homlity-unavailable-shortcodes');
        wp_add_inline_style('homlity-unavailable-shortcodes', $css);
    }

    // ── [homlity_unavailable_notice] ──────────────────────────────────────────

    public function renderNotice($atts): string
    {
        $atts = shortcode_atts([
            'title'        => '',
            'message'      => '',
            'button_text'  => '',
            'button_url'   => '',
            'show_icon'    => 'true',
            'show_summary' => 'true',
            'layout'       => 'compact',
        ], (array) $atts, 'homlity_unavailable_notice');

        $ctx        = UnavailablePropertyContext::getRecoveryContext();
        $summary    = is_array($ctx['property_summary'] ?? null) ? $ctx['property_summary'] : [];
        $archiveId  = (int) get_option('homlity_plugin_archive_page_id', 0);
        $archiveUrl = $archiveId ? (string) get_permalink($archiveId) : home_url('/inmuebles/');

        $title    = trim((string) $atts['title'])
            ?: __('Este inmueble ya no está disponible', 'homlity-real-estate');
        $message  = trim((string) $atts['message'])
            ?: __('El inmueble que consultaste fue retirado de disponibilidad. Encontramos opciones similares que pueden interesarte.', 'homlity-real-estate');
        $btnText  = trim((string) $atts['button_text'])
            ?: __('Ver otros inmuebles', 'homlity-real-estate');
        $btnUrl   = trim((string) $atts['button_url']) ?: $archiveUrl;
        $showIcon = filter_var($atts['show_icon'],    FILTER_VALIDATE_BOOLEAN);
        $showSum  = filter_var($atts['show_summary'], FILTER_VALIDATE_BOOLEAN);
        $layout   = in_array($atts['layout'], ['compact', 'card'], true) ? $atts['layout'] : 'compact';

        $chipKeys = [
            'type_name'         => '',
            'operation_name'    => '',
            'neighborhood_name' => '',
            'city_name'         => '',
            'price_formatted'   => '',
        ];

        ob_start();
        ?>
<div class="hml-unav-notice hml-unav-notice--<?php echo esc_attr($layout); ?>">

    <?php if ($showIcon): ?>
    <div class="hml-unav-notice__icon" aria-hidden="true">
        <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" width="36" height="36">
            <path d="M7 22L24 7l17 15v17a1 1 0 01-1 1H8a1 1 0 01-1-1V22z"
                  stroke="currentColor" stroke-width="2.5" stroke-linejoin="round" fill="none"/>
            <path d="M18 39V28h12v11" stroke="currentColor" stroke-width="2.5"
                  stroke-linejoin="round" fill="none"/>
            <circle cx="36" cy="13" r="9" fill="#fef2f2" stroke="#ef4444" stroke-width="2"/>
            <path d="M33 10l6 6M39 10l-6 6" stroke="#ef4444" stroke-width="2" stroke-linecap="round"/>
        </svg>
    </div>
    <?php endif; ?>

    <div class="hml-unav-notice__body">
        <p class="hml-unav-notice__title"><?php echo esc_html($title); ?></p>
        <p class="hml-unav-notice__message"><?php echo esc_html($message); ?></p>

        <?php if ($showSum && !empty($summary)): ?>
        <div class="hml-unav-notice__chips">
            <?php foreach (array_keys($chipKeys) as $key): ?>
                <?php if (!empty($summary[$key])): ?>
                    <span class="hml-unav-notice__chip"><?php echo esc_html((string) $summary[$key]); ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="hml-unav-notice__action">
        <a href="<?php echo esc_url($btnUrl); ?>" class="hml-unav-notice__btn">
            <?php echo esc_html($btnText); ?>
        </a>
    </div>

</div>
        <?php
        return (string) ob_get_clean();
    }

    // ── [homlity_unavailable_similar_properties] ──────────────────────────────

    public function renderSimilarProperties($atts): string
    {
        $atts = shortcode_atts([
            'per_page'    => '12',
            'columns'     => '3',
            'template'    => 'default',
            'sort'        => 'true',
            'view_toggle' => 'false',
            'pagination'  => 'true',
        ], (array) $atts, 'homlity_unavailable_similar_properties');

        $propertyId = UnavailablePropertyContext::getPropertyId();
        $bool = static function ($v, bool $default): bool {
            if ($v === null || $v === '') {
                return $default;
            }
            return (bool) filter_var($v, FILTER_VALIDATE_BOOLEAN);
        };

        $config = ListingConfig::fromArray([
            // Layout
            'default_view'       => 'grid',
            'show_view_toggle'   => $bool($atts['view_toggle'], false),
            'columns'            => max(1, (int) $atts['columns']),
            'posts_per_page'     => max(1, (int) $atts['per_page']),
            'orderby'            => 'date',
            'template'           => in_array($atts['template'], ['default', 'bootstrap'], true)
                ? $atts['template'] : 'default',
            // Toolbar
            'show_sort'          => $bool($atts['sort'], true),
            'show_results_count' => true,
            'show_pagination'    => $bool($atts['pagination'], true),
            // Query
            'query_mode'         => $propertyId > 0 ? 'related_current' : 'custom',
            'related_property_id' => $propertyId,
            'related_fallback'   => 'recent',
            'related_strategy'   => 'any',
            // Card defaults (ensure no PHP notices)
            'card_media_mode'    => 'single',
            'card_visual_preset' => 'default',
            'card_hover_effect'  => 'lift',
            'card_show_title'    => true,
            'card_show_excerpt'  => true,
            'card_show_operation' => true,
            'card_show_price'    => true,
            'card_show_features' => true,
            'card_show_whatsapp' => true,
            'card_whatsapp_icon_position' => 'left',
            'card_whatsapp_show_icon' => true,
            'card_feature_area'       => true,
            'card_feature_bedrooms'   => true,
            'card_feature_bathrooms'  => true,
            'card_feature_parking'    => true,
            'card_feature_area_lot'   => false,
            'card_feature_area_private' => false,
            'card_feature_area_built' => false,
            'card_feature_age'        => false,
            'card_feature_condition'  => false,
            'card_feature_code'       => false,
        ]);

        ob_start();
        (new ListingRenderer())->render($config);
        return (string) ob_get_clean();
    }

    // ── [homlity_unavailable_search_context] ─────────────────────────────────

    public function renderSearchContext($atts): string
    {
        $atts = shortcode_atts([
            'prefix' => '',
        ], (array) $atts, 'homlity_unavailable_search_context');

        $ctx     = UnavailablePropertyContext::getRecoveryContext();
        $summary = is_array($ctx['property_summary'] ?? null) ? $ctx['property_summary'] : [];
        $filters = (array) ($ctx['applied_filters'] ?? []);

        // Build a readable "type · operation · neighborhood · city" line.
        $parts = array_filter([
            $summary['type_name'] ?? '',
            isset($summary['operation_name']) && $summary['operation_name'] !== ''
                ? strtolower((string) $summary['operation_name'])
                : '',
            $summary['neighborhood_name'] ?? '',
            $summary['city_name']         ?? '',
        ]);

        $line = $parts ? implode(' · ', $parts) : implode(', ', $filters);
        if ($line === '') {
            return '';
        }

        $prefix = trim((string) $atts['prefix'])
            ?: __('Resultados basados en:', 'homlity-real-estate');

        return '<p class="hml-unav-context">'
            . esc_html($prefix) . ' <strong>' . esc_html($line) . '</strong></p>';
    }
}
