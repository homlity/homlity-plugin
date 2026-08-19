<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Technical sheet button for property.
 * Expected args: $post_id (int), $settings (array)
 */

use Homlity\PluginInmobiliario\Services\TechnicalSheetService;

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($post_id) || !$post_id) {
    $post_id = get_the_ID();
}

$settings = isset($settings) && is_array($settings) ? $settings : [];
$buttonText = (string) ($settings['button_text'] ?? __('Ver ficha técnica', 'homlity-real-estate'));
$openNewTab = ($settings['open_in_new_tab'] ?? 'yes') === 'yes';
$sheetUrl = TechnicalSheetService::sheetUrl((int) $post_id);
if ($sheetUrl === '') {
    return;
}
?>
<div class="property-tech-sheet-btn-wrap">
    <a
        class="property-tech-sheet-btn"
        href="<?php echo esc_url($sheetUrl); ?>"
        <?php echo $openNewTab ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
    >
        <?php echo esc_html($buttonText); ?>
    </a>
</div>
