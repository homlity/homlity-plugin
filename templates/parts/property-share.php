<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Property share component.
 * Overridable at homlity-real-estate/parts/property-share.php
 *
 * Expected args: $post_id (int), $settings (array, optional — Elementor widget settings)
 */
if (!defined('ABSPATH')) {
    exit;
}

use Homlity\PluginInmobiliario\Services\SocialShareMessageService;

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$s     = $settings ?? [];
$url   = get_permalink($post_id) ?: '';
$title = html_entity_decode(
    wp_strip_all_tags((string) (get_the_title($post_id) ?: '')),
    ENT_QUOTES | ENT_HTML5,
    'UTF-8'
);

$legacyShareText = trim((string) ($s['share_text'] ?? ''));
if ($legacyShareText === '{summary} {url}') {
    $legacyShareText = '';
}
$messages = [];
foreach (array_keys(SocialShareMessageService::defaults()) as $platformKey) {
    $messages[$platformKey] = SocialShareMessageService::messageFor(
        $platformKey,
        (int) $post_id,
        $legacyShareText
    );
}
$withoutUrl = static fn(string $message): string => SocialShareMessageService::messageWithoutUrl($message, $url);
$headingText = trim((string) ($s['heading_text'] ?? __('Compartir en:', 'homlity-real-estate')));

$showLabels      = ($s['show_labels']       ?? 'yes') === 'yes';
$showToggle      = ($s['show_label_toggle'] ?? '')    === 'yes';
$toggleHideText  = trim((string) ($s['label_toggle_hide_text'] ?? __('Ocultar etiquetas',  'homlity-real-estate')));
$toggleShowText  = trim((string) ($s['label_toggle_show_text'] ?? __('Mostrar etiquetas', 'homlity-real-estate')));

$platforms = [
    'whatsapp'  => [
        'label' => $s['label_whatsapp']  ?? __('WhatsApp',      'homlity-real-estate'),
        'href'  => 'https://api.whatsapp.com/send?text=' . rawurlencode($messages['whatsapp']),
        'copy'  => false,
    ],
    'facebook'  => [
        'label' => $s['label_facebook']  ?? __('Facebook',      'homlity-real-estate'),
        'href'  => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url) . '&quote=' . rawurlencode($withoutUrl($messages['facebook'])),
        'copy'  => false,
    ],
    'x'         => [
        'label' => $s['label_x']         ?? __('X',             'homlity-real-estate'),
        'href'  => 'https://twitter.com/intent/tweet?text=' . rawurlencode($withoutUrl($messages['x'])) . '&url=' . rawurlencode($url),
        'copy'  => false,
    ],
    'linkedin'  => [
        'label' => $s['label_linkedin']  ?? __('LinkedIn',      'homlity-real-estate'),
        'href'  => 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode($url) . '&summary=' . rawurlencode($withoutUrl($messages['linkedin'])),
        'copy'  => false,
    ],
    'telegram'  => [
        'label' => $s['label_telegram']  ?? __('Telegram',      'homlity-real-estate'),
        'href'  => 'https://t.me/share/url?url=' . rawurlencode($url) . '&text=' . rawurlencode($withoutUrl($messages['telegram'])),
        'copy'  => false,
    ],
    'pinterest' => [
        'label' => $s['label_pinterest'] ?? __('Pinterest',     'homlity-real-estate'),
        'href'  => 'https://pinterest.com/pin/create/button/?url=' . rawurlencode($url) . '&description=' . rawurlencode($withoutUrl($messages['pinterest'])),
        'copy'  => false,
    ],
    'reddit'    => [
        'label' => $s['label_reddit']    ?? __('Reddit',        'homlity-real-estate'),
        'href'  => 'https://www.reddit.com/submit?url=' . rawurlencode($url) . '&title=' . rawurlencode($withoutUrl($messages['reddit'])),
        'copy'  => false,
    ],
    'email'     => [
        'label' => $s['label_email']     ?? __('Correo',        'homlity-real-estate'),
        'href'  => 'mailto:?subject=' . rawurlencode($title) . '&body=' . rawurlencode($messages['email']),
        'copy'  => false,
    ],
    'copy'      => [
        'label' => $s['label_copy']      ?? __('Copiar enlace', 'homlity-real-estate'),
        'href'  => $url,
        'copy'  => true,
    ],
];

$wrapperClass = 'property-share-widget property-share-widget--inline';
if (!$showLabels) {
    $wrapperClass .= ' property-share-widget--labels-hidden';
}
?>
<div class="<?php echo esc_attr($wrapperClass); ?>">
    <?php if ($headingText !== ''): ?>
        <p class="property-share-widget__heading"><?php echo esc_html($headingText); ?></p>
    <?php endif; ?>
    <?php if ($showToggle): ?>
        <button
            type="button"
            class="property-share__toggle-btn"
            data-label-hide="<?php echo esc_attr($toggleHideText); ?>"
            data-label-show="<?php echo esc_attr($toggleShowText); ?>"
            aria-pressed="<?php echo $showLabels ? 'true' : 'false'; ?>"
        >
            <?php echo esc_html($showLabels ? $toggleHideText : $toggleShowText); ?>
        </button>
    <?php endif; ?>
    <ul class="property-share-widget__list">
        <?php foreach ($platforms as $key => $platform): ?>
            <?php
            if (($s['show_' . $key] ?? 'yes') !== 'yes') {
                continue;
            }
            $icon = $s['icon_' . $key] ?? [];
            if (is_string($icon) && $icon !== '') {
                $icon = ['value' => $icon, 'library' => 'fa-solid'];
            }
            ?>
            <li class="property-share__item">
                <a
                    class="property-share__link<?php echo $platform['copy'] ? ' property-share__copy' : ''; ?>"
                    href="<?php echo esc_attr($platform['href']); ?>"
                    <?php if (!$platform['copy']): ?>
                        target="_blank"
                        rel="noopener noreferrer"
                    <?php else: ?>
                        data-copy-url="<?php echo esc_url($url); ?>"
                    <?php endif; ?>
                    title="<?php echo esc_attr($platform['label']); ?>"
                    aria-label="<?php echo esc_attr($platform['label']); ?>"
                >
                    <?php if (!empty($icon['value']) && class_exists('\Homlity\PluginInmobiliario\Services\IconRenderer')): ?>
                        <span class="property-share__icon">
                            <?php \Homlity\PluginInmobiliario\Services\IconRenderer::render($icon, ['aria-hidden' => 'true']); ?>
                        </span>
                    <?php else: ?>
                        <span class="property-share__icon" aria-hidden="true">•</span>
                    <?php endif; ?>
                    <span class="property-share__label"><?php echo esc_html($platform['label']); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
