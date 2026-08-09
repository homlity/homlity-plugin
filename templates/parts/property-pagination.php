<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Compact property-listing pagination.
 *
 * Expected args:
 *   $current_page (int)
 *   $total_pages  (int)
 */

use Homlity\PluginInmobiliario\Listing\PaginationWindow;

if (!defined('ABSPATH')) {
    exit;
}

$currentPage = max(1, (int) ($current_page ?? 1));
$totalPages = max(1, (int) ($total_pages ?? 1));
$currentPage = min($currentPage, $totalPages);
$items = PaginationWindow::items($currentPage, $totalPages);
?>
<nav class="property-listing__pagination"
     data-current="<?php echo esc_attr($currentPage); ?>"
     data-pages="<?php echo esc_attr($totalPages); ?>"
     aria-label="<?php esc_attr_e('Paginación de inmuebles', 'homlity-real-estate'); ?>"
     <?php echo $totalPages <= 1 ? 'hidden' : ''; ?>>
    <button type="button"
            class="property-listing__page-btn property-listing__page-btn--edge"
            data-page="1"
            aria-label="<?php esc_attr_e('Ir al inicio', 'homlity-real-estate'); ?>"
            <?php disabled($currentPage, 1); ?>>
        <span aria-hidden="true">«</span>
        <span class="property-listing__page-label"><?php esc_html_e('Inicio', 'homlity-real-estate'); ?></span>
    </button>
    <button type="button"
            class="property-listing__page-btn property-listing__page-btn--edge"
            data-page="<?php echo esc_attr(max(1, $currentPage - 1)); ?>"
            aria-label="<?php esc_attr_e('Página anterior', 'homlity-real-estate'); ?>"
            <?php disabled($currentPage, 1); ?>>
        <span aria-hidden="true">‹</span>
        <span class="property-listing__page-label"><?php esc_html_e('Anterior', 'homlity-real-estate'); ?></span>
    </button>

    <?php foreach ($items as $item) : ?>
        <?php if ($item === 'ellipsis') : ?>
            <span class="property-listing__page-ellipsis" aria-hidden="true">…</span>
        <?php else : ?>
            <button type="button"
                    class="property-listing__page-btn<?php echo $item === $currentPage ? ' is-active' : ''; ?>"
                    data-page="<?php echo esc_attr($item); ?>"
                    aria-label="<?php echo esc_attr(sprintf(__('Página %d', 'homlity-real-estate'), $item)); ?>"
                    <?php echo $item === $currentPage ? 'aria-current="page"' : ''; ?>>
                <?php echo esc_html($item); ?>
            </button>
        <?php endif; ?>
    <?php endforeach; ?>

    <button type="button"
            class="property-listing__page-btn property-listing__page-btn--edge"
            data-page="<?php echo esc_attr(min($totalPages, $currentPage + 1)); ?>"
            aria-label="<?php esc_attr_e('Página siguiente', 'homlity-real-estate'); ?>"
            <?php disabled($currentPage, $totalPages); ?>>
        <span class="property-listing__page-label"><?php esc_html_e('Siguiente', 'homlity-real-estate'); ?></span>
        <span aria-hidden="true">›</span>
    </button>
    <button type="button"
            class="property-listing__page-btn property-listing__page-btn--edge"
            data-page="<?php echo esc_attr($totalPages); ?>"
            aria-label="<?php esc_attr_e('Ir al final', 'homlity-real-estate'); ?>"
            <?php disabled($currentPage, $totalPages); ?>>
        <span class="property-listing__page-label"><?php esc_html_e('Final', 'homlity-real-estate'); ?></span>
        <span aria-hidden="true">»</span>
    </button>
</nav>
