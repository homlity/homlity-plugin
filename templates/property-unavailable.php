<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Shown when a visitor lands on the permalink of an unpublished property.
 * Overridable at {theme}/homlity-real-estate/property-unavailable.php
 */

$archivePageId  = (int) get_option('homlity_plugin_archive_page_id', 0);
$archiveUrl     = $archivePageId ? get_permalink($archivePageId) : home_url('/inmuebles/');
$noticeMessage  = isset($hml_unavailable_message) && is_string($hml_unavailable_message) && trim($hml_unavailable_message) !== ''
    ? $hml_unavailable_message
    : __('El inmueble que buscas fue retirado o está fuera de publicación.', 'homlity-real-estate');

get_header();
?>
<div class="hml-unavailable-wrap">
    <div class="hml-unavailable-card" role="dialog" aria-modal="false" aria-labelledby="hml-unavailable-title">

        <div class="hml-unavailable-icon" aria-hidden="true">
            <svg viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- house outline -->
                <path d="M10 32L36 10l26 22v26a2 2 0 01-2 2H12a2 2 0 01-2-2V32z"
                      stroke="currentColor" stroke-width="3.5" stroke-linejoin="round" fill="none"/>
                <path d="M27 60V42h18v18" stroke="currentColor" stroke-width="3.5"
                      stroke-linejoin="round" fill="none"/>
                <!-- X badge -->
                <circle cx="54" cy="20" r="13" fill="#fff" stroke="currentColor" stroke-width="3"/>
                <path d="M49.5 15.5l9 9M58.5 15.5l-9 9"
                      stroke="currentColor" stroke-width="2.8" stroke-linecap="round"/>
            </svg>
        </div>

        <h1 id="hml-unavailable-title" class="hml-unavailable-title">
            <?php esc_html_e('Inmueble ya no disponible', 'homlity-real-estate'); ?>
        </h1>

        <p class="hml-unavailable-desc">
            <?php echo esc_html($noticeMessage); ?>
            <br>
            <?php esc_html_e('Explora nuestro catálogo para encontrar opciones similares.', 'homlity-real-estate'); ?>
        </p>

        <a href="<?php echo esc_url((string) $archiveUrl); ?>" class="hml-unavailable-btn">
            <?php esc_html_e('Ver otros inmuebles', 'homlity-real-estate'); ?>
        </a>

    </div>
</div>

<div class="hml-unavailable-results">
    <div class="hml-unavailable-results__inner">
        <h2 class="hml-unavailable-results__title"><?php esc_html_e('Resultados de búsqueda', 'homlity-real-estate'); ?></h2>
        <?php echo do_shortcode('[homlity_listing template="bootstrap" per_page="12" filters="true" view_toggle="true" sort="true"]'); ?>
    </div>
</div>

<style>
.hml-unavailable-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 72vh;
    padding: 40px 20px;
    box-sizing: border-box;
}

.hml-unavailable-card {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.13);
    padding: 52px 48px 44px;
    max-width: 480px;
    width: 100%;
    text-align: center;
    border: 1px solid rgba(0, 0, 0, 0.06);
}

.hml-unavailable-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 28px;
    color: #9ca3af;
}

.hml-unavailable-icon svg {
    width: 72px;
    height: 72px;
}

.hml-unavailable-title {
    font-size: 1.375rem;
    font-weight: 700;
    line-height: 1.3;
    color: #111827;
    margin: 0 0 14px;
}

.hml-unavailable-desc {
    font-size: 0.9375rem;
    line-height: 1.65;
    color: #6b7280;
    margin: 0 0 32px;
}

.hml-unavailable-btn {
    display: inline-block;
    background: var(--homlity-primary-color, #6355ff);
    color: #fff;
    text-decoration: none;
    font-size: 0.9375rem;
    font-weight: 600;
    padding: 13px 32px;
    border-radius: 40px;
    transition: opacity 0.18s ease;
}

.hml-unavailable-btn:hover {
    opacity: 0.88;
    color: #fff;
    text-decoration: none;
}

.hml-unavailable-results {
    margin: 0 auto 56px;
    max-width: 1240px;
    padding: 0 20px;
    box-sizing: border-box;
}

.hml-unavailable-results__inner {
    background: #fff;
    border: 1px solid rgba(0, 0, 0, 0.06);
    border-radius: 20px;
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.08);
    padding: 24px;
}

.hml-unavailable-results__title {
    color: #111827;
    font-size: 1.2rem;
    font-weight: 700;
    line-height: 1.35;
    margin: 0 0 16px;
}

@media (max-width: 520px) {
    .hml-unavailable-card {
        padding: 40px 28px 36px;
    }

    .hml-unavailable-title {
        font-size: 1.2rem;
    }

    .hml-unavailable-results__inner {
        padding: 16px;
    }
}
</style>

<?php get_footer(); ?>
