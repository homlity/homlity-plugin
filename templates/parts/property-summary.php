<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Homlity\PluginInmobiliario\Services\PropertyDescription;
use Homlity\PluginInmobiliario\Services\TemplateService;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Property summary component.
 * Overridable at homlity-real-estate/parts/property-summary.php
 *
 * Expected args: $post_id (int) and optional audio player settings.
 */

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$content = PropertyDescription::text((int) $post_id);

if (empty($content)) {
    return;
}

$contentPlain = trim(wp_strip_all_tags((string) $content));
$showAudio = !empty($show_audio_player) && $contentPlain !== '';
?>
<div class="property-summary">
    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>

<?php
if ($showAudio) {
    TemplateService::includeComponent('property-audio-player.php', [
        'audio_text' => $contentPlain,
        'audio_player_heading' => $audio_player_heading ?? __('Escucha', 'homlity-real-estate'),
        'audio_player_label' => $audio_player_label ?? __('este inmueble', 'homlity-real-estate'),
        'audio_default_rate' => $audio_default_rate ?? 1,
        'audio_voice' => $audio_voice ?? 'auto',
    ]);
}
?>
