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
$audioLabel   = isset($audio_player_label) ? (string) $audio_player_label : __('Escuchar descripción', 'homlity-plugin');
$audioRate    = isset($audio_default_rate) ? (float) $audio_default_rate : 1.0;
$playerId     = 'hca-' . wp_unique_id();
?>
<<?php echo esc_attr($tag); ?> class="property-content-widget">
    <?php echo $contentHtml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</<?php echo esc_attr($tag); ?>>

<?php if ($showAudio): ?>
<button
    type="button"
    class="property-content-audio-trigger"
    data-player="<?php echo esc_attr($playerId); ?>"
    aria-label="<?php echo esc_attr($audioLabel); ?>"
>
    <span class="property-content-audio-trigger__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
    </span>
    <span class="property-content-audio-trigger__label"><?php echo esc_html($audioLabel); ?></span>
</button>

<div
    id="<?php echo esc_attr($playerId); ?>"
    class="property-content-audio-bar"
    role="region"
    aria-label="<?php esc_attr_e('Reproductor de audio', 'homlity-plugin'); ?>"
    data-text="<?php echo esc_attr($contentPlain); ?>"
    data-rate="<?php echo esc_attr((string) $audioRate); ?>"
    hidden
>
    <div class="property-content-audio-bar__track">
        <div class="property-content-audio-bar__progress"></div>
    </div>
    <div class="property-content-audio-bar__body">
        <span class="property-content-audio-bar__title" aria-hidden="true">
            <?php echo esc_html($audioLabel); ?>
        </span>
        <div class="property-content-audio-bar__controls">
            <button type="button" class="property-content-audio-bar__btn" data-audio="rewind" title="-15s" aria-label="-15 segundos">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                    <path d="M12 5V1L7 6l5 5V7c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z"/>
                    <text x="8.5" y="15.5" font-size="5.5" font-family="sans-serif" text-anchor="middle">15</text>
                </svg>
            </button>
            <button type="button" class="property-content-audio-bar__btn property-content-audio-bar__btn--play" data-audio="play-pause" aria-label="<?php esc_attr_e('Reproducir', 'homlity-plugin'); ?>">
                <svg class="hca-icon-play" viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                <svg class="hca-icon-pause" viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
            </button>
            <button type="button" class="property-content-audio-bar__btn" data-audio="forward" title="+15s" aria-label="+15 segundos">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                    <path d="M12 5V1l5 5-5 5V7c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6h2c0 4.42-3.58 8-8 8S4 16.42 4 12s3.58-8 8-8z"/>
                    <text x="15.5" y="15.5" font-size="5.5" font-family="sans-serif" text-anchor="middle">15</text>
                </svg>
            </button>
            <select class="property-content-audio-bar__rate" data-audio="rate" aria-label="<?php esc_attr_e('Velocidad', 'homlity-plugin'); ?>">
                <option value="0.75">0.75×</option>
                <option value="1"<?php selected($audioRate, 1.0); ?>>1×</option>
                <option value="1.25"<?php selected($audioRate, 1.25); ?>>1.25×</option>
                <option value="1.5"<?php selected($audioRate, 1.5); ?>>1.5×</option>
                <option value="1.75"<?php selected($audioRate, 1.75); ?>>1.75×</option>
                <option value="2"<?php selected($audioRate, 2.0); ?>>2×</option>
            </select>
        </div>
        <button type="button" class="property-content-audio-bar__close" data-audio="close" aria-label="<?php esc_attr_e('Cerrar reproductor', 'homlity-plugin'); ?>">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
        </button>
    </div>
</div>
<?php endif; ?>
