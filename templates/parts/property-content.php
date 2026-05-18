<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php
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
$audioHeading = isset($audio_player_heading) ? (string) $audio_player_heading : __('Escucha', 'homlity-plugin');
$audioLabel   = isset($audio_player_label) ? (string) $audio_player_label : __('este inmueble', 'homlity-plugin');
$audioRate    = isset($audio_default_rate) ? (float) $audio_default_rate : 1.0;
?>
<<?php echo esc_attr($tag); ?> class="property-content-widget">
    <?php echo $contentHtml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</<?php echo esc_attr($tag); ?>>

<?php if ($showAudio) : ?>
<div class="property-content-audio-bar"
     role="region"
     aria-label="<?php esc_attr_e('Reproductor de audio', 'homlity-plugin'); ?>"
     data-text="<?php echo esc_attr($contentPlain); ?>"
     data-rate="<?php echo esc_attr((string) $audioRate); ?>">

    <button type="button"
            class="property-content-audio-bar__play-btn"
            data-audio="play-pause"
            aria-label="<?php esc_attr_e('Reproducir', 'homlity-plugin'); ?>">
        <svg class="hca-icon-play" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
            <path d="M8 5v14l11-7z"/>
        </svg>
        <svg class="hca-icon-pause" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
            <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
        </svg>
    </button>

    <div class="property-content-audio-bar__label" aria-hidden="true">
        <span class="property-content-audio-bar__heading"><?php echo esc_html($audioHeading); ?></span>
        <span class="property-content-audio-bar__sublabel"><?php echo esc_html($audioLabel); ?></span>
    </div>

    <div class="property-content-audio-bar__track-wrap" aria-hidden="true">
        <div class="property-content-audio-bar__track">
            <div class="property-content-audio-bar__progress">
                <span class="property-content-audio-bar__thumb"></span>
            </div>
        </div>
    </div>

    <span class="property-content-audio-bar__time" aria-live="off" aria-atomic="true">00:00</span>

    <div class="property-content-audio-bar__rate-wrap">
        <select class="property-content-audio-bar__rate"
                data-audio="rate"
                aria-label="<?php esc_attr_e('Velocidad de reproducción', 'homlity-plugin'); ?>">
            <option value="0.75"<?php selected($audioRate, 0.75); ?>>0.75x</option>
            <option value="1"<?php selected($audioRate, 1.0); ?>>1x</option>
            <option value="1.25"<?php selected($audioRate, 1.25); ?>>1.25x</option>
            <option value="1.5"<?php selected($audioRate, 1.5); ?>>1.5x</option>
            <option value="1.75"<?php selected($audioRate, 1.75); ?>>1.75x</option>
            <option value="2"<?php selected($audioRate, 2.0); ?>>2x</option>
        </select>
        <svg class="property-content-audio-bar__chevron" viewBox="0 0 10 6" width="10" height="6" fill="none" aria-hidden="true">
            <path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>

</div>
<?php endif; ?>
