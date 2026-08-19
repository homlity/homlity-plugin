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
?>
<div class="property-tech-sheet-btn-wrap">
    <a
        class="property-tech-sheet-btn"
        href="<?php echo esc_url($target['url']); ?>"
        <?php echo $target['is_download'] ? 'download' : ''; ?>
        <?php echo $openNewTab ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
    >
        <?php echo esc_html($buttonText); ?>
    </a>
</div>
