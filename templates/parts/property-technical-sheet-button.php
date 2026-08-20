<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Technical sheet button for property.
 * Expected args: $post_id (int), $settings (array)
 *
 * The button downloads the sheet as a PDF. It only falls back to opening the
 * HTML sheet when the site asked for that explicitly, or when Dompdf is not
 * installed and there is no PDF to hand over.
 */

use Homlity\PluginInmobiliario\Services\TechnicalSheetService;
use Homlity\PluginInmobiliario\Services\TemplateService;

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($post_id) || !$post_id) {
    $post_id = get_the_ID();
}

$settings = isset($settings) && is_array($settings) ? $settings : [];
$buttonText = (string) ($settings['button_text'] ?? __('Descargar ficha técnica', 'homlity-real-estate'));

// Widgets saved before the sheet became a download have no `link_action`, and
// downloading is what those buttons were meant to do all along.
$wantsPdf = (string) ($settings['link_action'] ?? 'download') !== 'view';

$target = TechnicalSheetService::buttonTarget((int) $post_id, $wantsPdf);
if ($target['url'] === '') {
    return;
}

// A new tab for a file download just flashes an empty window shut, so the flag
// only applies to the HTML sheet.
$openNewTab = !$target['is_download'] && ($settings['open_in_new_tab'] ?? 'yes') === 'yes';

// Componer el PDF tarda, y una descarga no navega: sin esto el botón se queda
// mudo hasta que el archivo aparece. Solo hace falta en la descarga —abrir la
// ficha en el sitio ya lo indica el propio navegador—, así que el script no se
// carga en el otro caso.
$sheetData = '';
if ($target['is_download']) {
    wp_enqueue_script(
        'homlity-real-estate-technical-sheet-download',
        HOMLITY_PLUGIN_URL . 'assets/js/technical-sheet-download.js',
        [],
        HOMLITY_PLUGIN_VERSION,
        true
    );

    // Los textos que el script pinta y el nombre con el que guardar el
    // archivo. Van en el marcado y no en un wp_localize_script() porque el
    // mismo botón puede repetirse en una página con otro texto en cada widget.
    $sheetData = sprintf(
        ' data-filename="%s" data-loading-text="%s" data-ready-text="%s" data-error-text="%s"',
        esc_attr(TemplateService::technicalSheetPdfFilename((int) $post_id)),
        esc_attr__('Generando ficha…', 'homlity-real-estate'),
        esc_attr__('Ficha descargada.', 'homlity-real-estate'),
        esc_attr__('No se pudo generar la ficha. Vuelve a intentarlo.', 'homlity-real-estate')
    );
}
?>
<div class="property-tech-sheet-btn-wrap">
    <a
        class="property-tech-sheet-btn<?php echo $target['is_download'] ? ' property-tech-sheet-btn--async' : ''; ?>"
        href="<?php echo esc_url($target['url']); ?>"
        <?php echo $target['is_download'] ? 'download' : ''; ?>
        <?php echo $openNewTab ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
        <?php echo $sheetData; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cada valor sale ya escapado de arriba. ?>
    >
        <?php if ($target['is_download']) : ?><span class="property-tech-sheet-btn__spinner" aria-hidden="true"></span><?php endif; ?>
        <span class="property-tech-sheet-btn__label"><?php echo esc_html($buttonText); ?></span>
    </a>
    <?php if ($target['is_download']) : ?>
    <?php // Lo que la animación cuenta en pantalla, aquí lo lee el lector de pantalla. ?>
    <p class="property-tech-sheet-btn__status" role="status" aria-live="polite"></p>
    <?php endif; ?>
</div>
