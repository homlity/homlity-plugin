<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Homlity\PluginInmobiliario\Services\TemplateService;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if (!isset($post_id)) {
    $post_id = get_the_ID();
}
$tag = isset($content_tag) ? strtolower((string) $content_tag) : 'div';
if (!in_array($tag, ['div', 'section', 'article', 'p'], true)) {
    $tag = 'div';
}
$contentHtml  = (string) apply_filters('the_content', get_post_field('post_content', $post_id));
$contentPlain = trim(wp_strip_all_tags($contentHtml));
$showAudio    = !empty($show_audio_player) && $contentPlain !== '';
$audioHeading = isset($audio_player_heading) ? (string) $audio_player_heading : __('Escucha', 'homlity-real-estate');
$audioLabel   = isset($audio_player_label) ? (string) $audio_player_label : __('este inmueble', 'homlity-real-estate');
$audioRate    = isset($audio_default_rate) ? (float) $audio_default_rate : 1.0;
$audioVoice   = isset($audio_voice) ? sanitize_key((string) $audio_voice) : 'auto';
?>
<<?php echo esc_attr($tag); ?> class="property-content-widget">
    <?php echo $contentHtml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</<?php echo esc_attr($tag); ?>>

<?php
if ($showAudio) {
    TemplateService::includeComponent('property-audio-player.php', [
        'audio_text' => $contentPlain,
        'audio_player_heading' => $audioHeading,
        'audio_player_label' => $audioLabel,
        'audio_default_rate' => $audioRate,
        'audio_voice' => $audioVoice,
    ]);
}
?>
