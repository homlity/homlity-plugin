<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Recovery landing page for retired/unavailable property URLs.
 *
 * This template is rendered by PropertyUnavailableService when a visitor
 * lands on a property URL that is no longer active AND the rich recovery
 * feature is enabled (homlity_recovery_enabled = '1').
 *
 * Theme overridable at: {theme}/homlity-real-estate/property-recovery.php
 *
 * Variables provided via $GLOBALS['homlity_property_recovery_context']:
 *   string[]  applied_filters     Human-readable filter labels.
 *   bool      has_enough_results  True when similar_count >= min_results.
 *   string[]  internal_links      [{label, url}] for SEO navigation.
 *   string    meta_description    Used for description meta (also in head).
 *   string    meta_title          Used for page title.
 *   array     property_summary    Historical data: title, type, city, price…
 *   array|null similar_args       WP_Query args for similar properties.
 *   int       similar_count       Number of similar properties found.
 *   int       status_code         HTTP status used (200 / 404 / 410).
 */

use Homlity\PluginInmobiliario\Listing\ListingConfig;
use Homlity\PluginInmobiliario\Listing\ListingRenderer;

$ctx = $GLOBALS['homlity_property_recovery_context'] ?? [];

$summary       = is_array($ctx['property_summary'] ?? null) ? $ctx['property_summary'] : [];
$similarArgs   = is_array($ctx['similar_args'] ?? null) ? $ctx['similar_args'] : null;
$similarCount  = (int) ($ctx['similar_count'] ?? 0);
$appliedFilters = (array) ($ctx['applied_filters'] ?? []);
$internalLinks  = (array) ($ctx['internal_links'] ?? []);
$hasEnough      = !empty($ctx['has_enough_results']);
$archivePageId  = (int) get_option('homlity_plugin_archive_page_id', 0);
$archiveUrl     = $archivePageId ? (string) get_permalink($archivePageId) : home_url('/inmuebles/');

// Enqueue card/listing assets before get_header() collects scripts.
ListingRenderer::enqueueAssets();

// Build context text for the "search was based on" line.
$contextLine = '';
$ctxParts    = array_filter([
    $summary['type_name'] ?? '',
    $summary['operation_name'] ? strtolower((string) $summary['operation_name']) : '',
    $summary['neighborhood_name'] ?? '',
    $summary['city_name'] ?? '',
]);
if ($ctxParts) {
    $contextLine = implode(' · ', $ctxParts);
}

get_header();
?>
<style>
.hml-recovery-wrap{max-width:1200px;margin:0 auto;padding:2rem 1rem;}
.hml-recovery-hero{text-align:center;padding:2.5rem 1.5rem;background:#f9f9f9;border-radius:8px;margin-bottom:2rem;border:1px solid #e8e8e8;}
.hml-recovery-icon{display:flex;justify-content:center;margin-bottom:1.25rem;color:#555;}
.hml-recovery-icon svg{width:64px;height:64px;}
.hml-recovery-title{font-size:1.6rem;font-weight:700;margin:0 0 .75rem;color:#1a1a1a;}
.hml-recovery-message{font-size:1rem;color:#555;max-width:640px;margin:0 auto .5rem;}
.hml-recovery-sub{font-size:.9rem;color:#888;max-width:560px;margin:0 auto 1.5rem;}
.hml-recovery-actions{display:flex;gap:.75rem;flex-wrap:wrap;justify-content:center;}
.hml-recovery-btn{display:inline-block;padding:.65rem 1.4rem;border-radius:5px;font-size:.9rem;font-weight:600;text-decoration:none;transition:opacity .15s;}
.hml-recovery-btn--primary{background:#1a73e8;color:#fff;}
.hml-recovery-btn--primary:hover{opacity:.88;color:#fff;}
.hml-recovery-btn--outline{background:#fff;color:#1a73e8;border:2px solid #1a73e8;}
.hml-recovery-btn--outline:hover{background:#f0f6ff;color:#1a73e8;}
.hml-recovery-summary{display:flex;flex-wrap:wrap;gap:.5rem .75rem;align-items:center;background:#fffbf0;border:1px solid #fde89a;border-radius:6px;padding:.9rem 1.1rem;margin-bottom:1.5rem;font-size:.9rem;color:#555;}
.hml-recovery-summary__label{font-weight:600;color:#333;margin-right:.25rem;}
.hml-recovery-summary__chip{background:#fff;border:1px solid #e0e0e0;border-radius:4px;padding:.2rem .55rem;font-size:.82rem;}
.hml-recovery-filters-applied{font-size:.85rem;color:#888;margin-bottom:1.75rem;padding:.65rem 1rem;background:#f4f4f4;border-radius:5px;}
.hml-recovery-filters-applied strong{color:#444;}
.hml-recovery-similar h2{font-size:1.25rem;font-weight:700;margin-bottom:1.25rem;color:#1a1a1a;}
.hml-recovery-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.25rem;margin-bottom:2rem;}
.hml-recovery-links{margin:2rem 0;}
.hml-recovery-links h3{font-size:1rem;font-weight:700;margin-bottom:.75rem;color:#333;}
.hml-recovery-links ul{list-style:none;padding:0;margin:0;display:flex;flex-wrap:wrap;gap:.5rem;}
.hml-recovery-links li a{display:inline-block;padding:.4rem .85rem;background:#f0f0f0;border-radius:20px;font-size:.85rem;color:#333;text-decoration:none;border:1px solid #e0e0e0;transition:background .15s;}
.hml-recovery-links li a:hover{background:#e0e8ff;color:#1a73e8;}
.hml-recovery-cta{background:#f0f6ff;border:1px solid #cde0ff;border-radius:8px;padding:1.5rem;text-align:center;margin-top:2rem;}
.hml-recovery-cta h3{font-size:1.1rem;font-weight:700;margin:0 0 .5rem;color:#1a1a1a;}
.hml-recovery-cta p{font-size:.9rem;color:#555;margin:0 0 1rem;}
.hml-recovery-no-results{text-align:center;padding:2.5rem 1.5rem;background:#f9f9f9;border-radius:8px;margin-bottom:2rem;border:1px dashed #ccc;}
.hml-recovery-no-results p{color:#666;margin-bottom:1.25rem;}
@media(max-width:640px){.hml-recovery-title{font-size:1.25rem;}.hml-recovery-grid{grid-template-columns:1fr;}}
</style>

<div class="hml-recovery-wrap">

    <?php /* ── Hero block ──────────────────────────────────────────────────── */ ?>
    <div class="hml-recovery-hero">
        <div class="hml-recovery-icon" aria-hidden="true">
            <svg viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 32L36 10l26 22v26a2 2 0 01-2 2H12a2 2 0 01-2-2V32z"
                      stroke="currentColor" stroke-width="3.5" stroke-linejoin="round" fill="none"/>
                <path d="M27 60V42h18v18" stroke="currentColor" stroke-width="3.5"
                      stroke-linejoin="round" fill="none"/>
                <circle cx="54" cy="20" r="13" fill="#fff4f4" stroke="#c0392b" stroke-width="2.5"/>
                <path d="M50 16l8 8M58 16l-8 8" stroke="#c0392b" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>
 
        <h1 class="hml-recovery-title">
            <?php esc_html_e('Este inmueble ya no está disponible', 'homlity-real-estate'); ?>
        </h1>

        <?php if ($hasEnough && $similarCount > 0): ?>
            <p class="hml-recovery-message">
                <?php esc_html_e('Pero encontramos propiedades similares según su ubicación, tipo de inmueble y características principales.', 'homlity-real-estate'); ?>
            </p>
        <?php else: ?>
            <p class="hml-recovery-message">
                <?php esc_html_e('El inmueble que consultaste fue retirado de disponibilidad. Puede que haya sido vendido, arrendado o simplemente retirado de nuestra oferta.', 'homlity-real-estate'); ?>
            </p>
        <?php endif; ?>

        <?php if ($contextLine): ?>
            <p class="hml-recovery-sub">
                <?php
                printf(
                    /* translators: %s: property type, operation, neighborhood, city */
                    esc_html__('La propiedad consultada correspondía a: %s.', 'homlity-real-estate'),
                    esc_html($contextLine)
                );
                ?>
            </p>
        <?php endif; ?>

        <div class="hml-recovery-actions">
            <?php if ($hasEnough && $similarCount > 0): ?>
                <a href="#hml-recovery-similar" class="hml-recovery-btn hml-recovery-btn--primary">
                    <?php esc_html_e('Ver propiedades similares', 'homlity-real-estate'); ?>
                </a>
            <?php endif; ?>
            <a href="<?php echo esc_url($archiveUrl); ?>" class="hml-recovery-btn hml-recovery-btn--outline">
                <?php esc_html_e('Buscar otro inmueble', 'homlity-real-estate'); ?>
            </a>
        </div>
    </div>

    <?php /* ── Property summary (historical data) ─────────────────────────── */ ?>
    <?php if (!empty($summary) && array_filter([$summary['type_name'], $summary['city_name'], $summary['bedrooms'], $summary['area']])): ?>
    <div class="hml-recovery-summary" role="complementary" aria-label="<?php esc_attr_e('Características del inmueble retirado', 'homlity-real-estate'); ?>">
        <span class="hml-recovery-summary__label"><?php esc_html_e('Inmueble retirado:', 'homlity-real-estate'); ?></span>

        <?php if (!empty($summary['type_name'])): ?>
            <span class="hml-recovery-summary__chip"><?php echo esc_html($summary['type_name']); ?></span>
        <?php endif; ?>

        <?php if (!empty($summary['operation_name'])): ?>
            <span class="hml-recovery-summary__chip"><?php echo esc_html($summary['operation_name']); ?></span>
        <?php endif; ?>

        <?php if (!empty($summary['neighborhood_name'])): ?>
            <span class="hml-recovery-summary__chip"><?php echo esc_html($summary['neighborhood_name']); ?></span>
        <?php endif; ?>

        <?php if (!empty($summary['city_name'])): ?>
            <span class="hml-recovery-summary__chip"><?php echo esc_html($summary['city_name']); ?></span>
        <?php endif; ?>

        <?php if (!empty($summary['bedrooms'])): ?>
            <span class="hml-recovery-summary__chip">🛏 <?php echo esc_html($summary['bedrooms']); ?></span>
        <?php endif; ?>

        <?php if (!empty($summary['bathrooms'])): ?>
            <span class="hml-recovery-summary__chip">🛁 <?php echo esc_html($summary['bathrooms']); ?></span>
        <?php endif; ?>

        <?php if (!empty($summary['parking'])): ?>
            <span class="hml-recovery-summary__chip">🚗 <?php echo esc_html($summary['parking']); ?></span>
        <?php endif; ?>

        <?php if (!empty($summary['area'])): ?>
            <span class="hml-recovery-summary__chip">▦ <?php echo esc_html($summary['area']); ?> m²</span>
        <?php endif; ?>

        <?php if (!empty($summary['price_formatted'])): ?>
            <span class="hml-recovery-summary__chip">
                <?php echo esc_html($summary['price_formatted']); ?>
            </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php /* ── Applied filters ──────────────────────────────────────────────── */ ?>
    <?php if (!empty($appliedFilters)): ?>
    <div class="hml-recovery-filters-applied">
        <strong><?php esc_html_e('Resultados basados en:', 'homlity-real-estate'); ?></strong>
        <?php echo esc_html(implode(', ', $appliedFilters)); ?>.
    </div>
    <?php endif; ?>

    <?php /* ── Similar properties grid ──────────────────────────────────────── */ ?>
    <?php if ($similarArgs !== null && $similarCount > 0): ?>
    <div class="hml-recovery-similar" id="hml-recovery-similar">
        <h2><?php esc_html_e('Inmuebles similares disponibles', 'homlity-real-estate'); ?></h2>
        <div class="hml-recovery-grid">
            <?php
            $similarQuery   = new WP_Query($similarArgs);
            $recoveryConfig = ListingConfig::fromArray([
                'card_media_mode'             => 'single',
                'card_visual_preset'          => 'default',
                'card_hover_effect'           => 'lift',
                'card_show_title'             => true,
                'card_show_excerpt'           => true,
                'card_show_operation'         => true,
                'card_show_price'             => true,
                'card_show_features'          => true,
                'card_show_whatsapp'          => true,
                'card_whatsapp_show_icon'     => true,
                'card_whatsapp_icon_position' => 'left',
                'card_feature_area'           => true,
                'card_feature_bedrooms'       => true,
                'card_feature_bathrooms'      => true,
                'card_feature_parking'        => true,
                'card_feature_area_lot'       => false,
                'card_feature_area_private'   => false,
                'card_feature_area_built'     => false,
                'card_feature_age'            => false,
                'card_feature_condition'      => false,
                'card_feature_code'           => false,
            ]);
            echo (new ListingRenderer())->renderCards($similarQuery, $recoveryConfig); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
        </div>
    </div>
    <?php endif; ?>

    <?php /* ── No-results / weak-results fallback ───────────────────────────── */ ?>
    <?php if (!$hasEnough): ?>
    <div class="hml-recovery-no-results">
        <p>
            <?php
            if ($similarCount > 0) {
                printf(
                    /* translators: %d: number of similar properties found */
                    esc_html(_n(
                        'No encontramos una opción idéntica, pero esta %d propiedad puede ajustarse a lo que buscas.',
                        'No encontramos opciones exactas, pero estas %d propiedades pueden ajustarse a lo que buscas.',
                        $similarCount,
                        'homlity-real-estate'
                    )),
                    (int) $similarCount
                );
            } else {
                esc_html_e('No encontramos propiedades con características idénticas en este momento. Puedes explorar nuestro catálogo completo o ajustar tu búsqueda.', 'homlity-real-estate');
            }
            ?>
        </p>
        <a href="<?php echo esc_url($archiveUrl); ?>" class="hml-recovery-btn hml-recovery-btn--primary">
            <?php esc_html_e('Explorar catálogo completo', 'homlity-real-estate'); ?>
        </a>
    </div>
    <?php endif; ?>

    <?php /* ── Internal SEO links ─────────────────────────────────────────────── */ ?>
    <?php if (!empty($internalLinks)): ?>
    <nav class="hml-recovery-links" aria-label="<?php esc_attr_e('Categorías relacionadas', 'homlity-real-estate'); ?>">
        <h3><?php esc_html_e('Explora propiedades similares', 'homlity-real-estate'); ?></h3>
        <ul>
            <?php foreach ($internalLinks as $link): ?>
                <li>
                    <a href="<?php echo esc_url((string) ($link['url'] ?? '')); ?>">
                        <?php echo esc_html((string) ($link['label'] ?? '')); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <?php /* ── CTA: contact advisor ─────────────────────────────────────────── */ ?>
    <div class="hml-recovery-cta">
        <h3><?php esc_html_e('¿No encontraste lo que buscabas?', 'homlity-real-estate'); ?></h3>
        <p><?php esc_html_e('Nuestros asesores pueden ayudarte a encontrar el inmueble perfecto según tus necesidades y presupuesto.', 'homlity-real-estate'); ?></p>
        <div class="hml-recovery-actions">
            <?php
            // WhatsApp CTA: use the agency phone if configured in settings
            $settings     = get_option(HOMLITY_PLUGIN_SETTINGS_OPTION, []);
            $agencyPhone  = sanitize_text_field((string) ($settings['whatsapp_phone'] ?? $settings['agency_phone'] ?? ''));
            if ($agencyPhone !== ''):
                $waLink = 'https://wa.me/' . preg_replace('/\D/', '', $agencyPhone)
                    . '?text=' . rawurlencode(__('Hola, estoy buscando un inmueble similar al que vi en su sitio web. ¿Pueden asesorarme?', 'homlity-real-estate'));
            ?>
                <a href="<?php echo esc_url($waLink); ?>" class="hml-recovery-btn hml-recovery-btn--primary" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('Hablar con un asesor', 'homlity-real-estate'); ?>
                </a>
            <?php endif; ?>
            <a href="<?php echo esc_url($archiveUrl); ?>" class="hml-recovery-btn hml-recovery-btn--outline">
                <?php esc_html_e('Buscar otro inmueble', 'homlity-real-estate'); ?>
            </a>
        </div>
    </div>

</div><!-- /.hml-recovery-wrap -->

<?php get_footer(); ?>
