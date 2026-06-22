<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Compact fallback shown when a visitor lands on an unpublished/unavailable
 * property URL and the rich recovery feature is disabled
 * (homlity_recovery_enabled = '0') AND no Elementor template is configured.
 *
 * Overridable at {theme}/homlity-real-estate/property-unavailable.php
 *
 * Template override priority:
 *   1. Elementor page configured in Configuración → Plantillas Elementor
 *   2. {theme}/homlity-real-estate/property-unavailable.php (theme override)
 *   3. This file (plugin fallback)
 */

use Homlity\PluginInmobiliario\Listing\ListingConfig;
use Homlity\PluginInmobiliario\Listing\ListingRenderer;
use Homlity\PluginInmobiliario\Services\UnavailablePropertyContext;

// ── Enqueue listing assets BEFORE get_header() so they land inside <head>. ──
// ListingRenderer::enqueueAssets() is also called from PropertyUnavailableService
// via wp_enqueue_scripts, but calling it here is safe (WP deduplicates handles).
ListingRenderer::enqueueAssets();

// ── Resolve context ────────────────────────────────────────────────────────────
$archivePageId = (int) get_option( 'homlity_plugin_archive_page_id', 0 );
$archiveUrl    = $archivePageId ? (string) get_permalink( $archivePageId ) : home_url( '/inmuebles/' );
$propertyId    = UnavailablePropertyContext::getPropertyId();

// WhatsApp CTA if an agency phone is configured.
$pluginSettings = get_option( HOMLITY_PLUGIN_SETTINGS_OPTION, [] );
$agencyPhone    = sanitize_text_field( (string) ( $pluginSettings['whatsapp_phone'] ?? $pluginSettings['agency_phone'] ?? '' ) );
$waLink         = '';
if ( $agencyPhone !== '' ) {
    $waLink = 'https://wa.me/' . preg_replace( '/\D/', '', $agencyPhone )
        . '?text=' . rawurlencode( __( 'Hola, estoy buscando un inmueble similar al que vi en su sitio web. ¿Pueden asesorarme?', 'homlity-real-estate' ) );
}

// ── Build the listing config for similar / recent properties ──────────────────
$unavailableConfig = ListingConfig::fromArray( [
    'default_view'         => 'grid',
    'show_view_toggle'     => false,
    'columns'              => 3,
    'posts_per_page'       => 12,
    'orderby'              => 'date',
    'query_mode'           => $propertyId > 0 ? 'related_current' : 'custom',
    'related_property_id'  => $propertyId,
    'related_fallback'     => 'recent',
    'related_strategy'     => 'any',
    'show_sort'            => true,
    'show_results_count'   => true,
    'show_pagination'      => true,
    // Card defaults – explicit to avoid any undefined-key notices.
    'card_media_mode'      => 'single',
    'card_visual_preset'   => 'default',
    'card_hover_effect'    => 'lift',
    'card_show_title'      => true,
    'card_show_excerpt'    => true,
    'card_show_operation'  => true,
    'card_show_price'      => true,
    'card_show_features'   => true,
    'card_show_whatsapp'   => true,
    'card_whatsapp_icon_position' => 'left',
    'card_whatsapp_show_icon'     => true,
    'card_feature_area'           => true,
    'card_feature_bedrooms'       => true,
    'card_feature_bathrooms'      => true,
    'card_feature_parking'        => true,
] );

get_header();
?>
<style>
/* ── Homlity unavailable-page fallback styles ──────────────────────────────── */
.hml-unav-page{max-width:1200px;margin:0 auto;padding:1.5rem 1rem 3rem;}

/* Compact alert banner (~80-120px tall) */
.hml-unav-alert{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;
    background:#fff8f0;border:1px solid #fde89a;border-radius:8px;
    padding:.9rem 1.2rem;margin-bottom:2rem;}
.hml-unav-alert__icon{flex-shrink:0;color:#f59e0b;line-height:0;}
.hml-unav-alert__icon svg{width:36px;height:36px;}
.hml-unav-alert__content{flex:1;min-width:0;}
.hml-unav-alert__title{font-size:1rem;font-weight:700;margin:0 0 .15rem;color:#1a1a1a;}
.hml-unav-alert__desc{font-size:.875rem;color:#555;margin:0;}
.hml-unav-alert__actions{display:flex;gap:.6rem;flex-wrap:wrap;flex-shrink:0;}
.hml-unav-btn{display:inline-block;padding:.55rem 1.2rem;border-radius:5px;
    font-size:.85rem;font-weight:600;text-decoration:none;white-space:nowrap;transition:opacity .15s;}
.hml-unav-btn--primary{background:#25d366;color:#fff;}
.hml-unav-btn--primary:hover{opacity:.88;color:#fff;}
.hml-unav-btn--outline{background:#fff;color:#333;border:1.5px solid #ccc;}
.hml-unav-btn--outline:hover{background:#f4f4f4;color:#111;}

/* Results section */
.hml-unav-results__title{font-size:1.15rem;font-weight:700;margin:0 0 1.25rem;color:#1a1a1a;}

@media(max-width:640px){
    .hml-unav-alert{gap:.75rem;}
    .hml-unav-alert__actions{width:100%;}
    .hml-unav-btn{flex:1;text-align:center;}
}
</style>

<div class="hml-unav-page">

    <div class="hml-unav-alert" role="alert">
        <div class="hml-unav-alert__icon" aria-hidden="true">
            <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M7 22L24 7l17 15v17a1 1 0 01-1 1H8a1 1 0 01-1-1V22z"
                      stroke="currentColor" stroke-width="2.5" stroke-linejoin="round" fill="none"/>
                <path d="M18 39V28h12v11" stroke="currentColor" stroke-width="2.5"
                      stroke-linejoin="round" fill="none"/>
                <circle cx="36" cy="13" r="9" fill="#fef2f2" stroke="#ef4444" stroke-width="2"/>
                <path d="M33 10l6 6M39 10l-6 6" stroke="#ef4444" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </div>

        <div class="hml-unav-alert__content">
            <p class="hml-unav-alert__title">
                <?php esc_html_e( 'Este inmueble ya no está disponible', 'homlity-real-estate' ); ?>
            </p>
            <p class="hml-unav-alert__desc">
                <?php esc_html_e( 'Encontramos propiedades similares que podrían interesarte.', 'homlity-real-estate' ); ?>
            </p>
        </div>

        <div class="hml-unav-alert__actions">
            <?php if ( $waLink !== '' ) : ?>
                <a href="<?php echo esc_url( $waLink ); ?>"
                   class="hml-unav-btn hml-unav-btn--primary"
                   target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e( 'Hablar con asesor', 'homlity-real-estate' ); ?>
                </a>
            <?php endif; ?>
            <a href="<?php echo esc_url( (string) $archiveUrl ); ?>"
               class="hml-unav-btn hml-unav-btn--outline">
                <?php esc_html_e( 'Ver otros inmuebles', 'homlity-real-estate' ); ?>
            </a>
        </div>
    </div>

    <div class="hml-unav-results">
        <h2 class="hml-unav-results__title">
            <?php esc_html_e( 'Inmuebles similares disponibles', 'homlity-real-estate' ); ?>
        </h2>
        <?php ( new ListingRenderer() )->render( $unavailableConfig ); ?>
    </div>

</div><!-- /.hml-unav-page -->

<?php get_footer(); ?>
