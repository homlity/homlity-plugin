<?php
/**
 * Property share component.
 * Overridable at homlity-plugin/parts/property-share.php
 *
 * Expected args: $post_id (int), $settings (array, optional — Elementor widget settings)
 */

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$s     = $settings ?? [];
$url   = get_permalink($post_id) ?: '';
$title = get_the_title($post_id) ?: '';

$shareTextTpl = $s['share_text'] ?? __('Mira este inmueble: {title}', 'homlity-plugin');
$shareText    = str_replace(['{title}', '{url}'], [$title, $url], $shareTextTpl);

$platforms = [
    'whatsapp'  => [
        'label' => $s['label_whatsapp']  ?? __('WhatsApp',      'homlity-plugin'),
        'href'  => 'https://api.whatsapp.com/send?text=' . rawurlencode($shareText . ' ' . $url),
        'copy'  => false,
    ],
    'facebook'  => [
        'label' => $s['label_facebook']  ?? __('Facebook',      'homlity-plugin'),
        'href'  => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url),
        'copy'  => false,
    ],
    'x'         => [
        'label' => $s['label_x']         ?? __('X',             'homlity-plugin'),
        'href'  => 'https://twitter.com/intent/tweet?text=' . rawurlencode($shareText) . '&url=' . rawurlencode($url),
        'copy'  => false,
    ],
    'linkedin'  => [
        'label' => $s['label_linkedin']  ?? __('LinkedIn',      'homlity-plugin'),
        'href'  => 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode($url),
        'copy'  => false,
    ],
    'telegram'  => [
        'label' => $s['label_telegram']  ?? __('Telegram',      'homlity-plugin'),
        'href'  => 'https://t.me/share/url?url=' . rawurlencode($url) . '&text=' . rawurlencode($shareText),
        'copy'  => false,
    ],
    'pinterest' => [
        'label' => $s['label_pinterest'] ?? __('Pinterest',     'homlity-plugin'),
        'href'  => 'https://pinterest.com/pin/create/button/?url=' . rawurlencode($url) . '&description=' . rawurlencode($shareText),
        'copy'  => false,
    ],
    'reddit'    => [
        'label' => $s['label_reddit']    ?? __('Reddit',        'homlity-plugin'),
        'href'  => 'https://www.reddit.com/submit?url=' . rawurlencode($url) . '&title=' . rawurlencode($title),
        'copy'  => false,
    ],
    'email'     => [
        'label' => $s['label_email']     ?? __('Correo',        'homlity-plugin'),
        'href'  => 'mailto:?subject=' . rawurlencode($title) . '&body=' . rawurlencode($shareText . ' ' . $url),
        'copy'  => false,
    ],
    'copy'      => [
        'label' => $s['label_copy']      ?? __('Copiar enlace', 'homlity-plugin'),
        'href'  => $url,
        'copy'  => true,
    ],
];

$hasCopy = ($s['show_copy'] ?? 'yes') === 'yes';
?>
<ul class="property-share-widget">
    <?php foreach ($platforms as $key => $platform): ?>
        <?php
        if (($s['show_' . $key] ?? 'yes') !== 'yes') {
            continue;
        }
        $icon = $s['icon_' . $key] ?? [];
        ?>
        <li class="property-share__item">
            <a
                class="property-share__link<?php echo $platform['copy'] ? ' property-share__copy' : ''; ?>"
                href="<?php echo esc_url($platform['href']); ?>"
                <?php if (!$platform['copy']): ?>
                    target="_blank"
                    rel="noopener noreferrer"
                <?php else: ?>
                    data-copy-url="<?php echo esc_url($url); ?>"
                    onclick="event.preventDefault(); if(navigator.clipboard){navigator.clipboard.writeText(this.dataset.copyUrl).then(function(){var el=this;},function(){});} var l=this.querySelector('.property-share__value');if(l){var orig=l.textContent;l.textContent='<?php echo esc_js(__('¡Copiado!', 'homlity-plugin')); ?>';setTimeout(function(){l.textContent=orig;},2000);}"
                <?php endif; ?>
                title="<?php echo esc_attr($platform['label']); ?>"
            >
                <?php if (!empty($icon['value']) && class_exists('\Elementor\Icons_Manager')): ?>
                    <span class="property-share__icon">
                        <?php \Elementor\Icons_Manager::render_icon($icon, ['aria-hidden' => 'true']); ?>
                    </span>
                <?php endif; ?>
                <span class="property-share__label"><?php echo esc_html($platform['label']); ?></span>
                <span class="property-share__value"><?php echo esc_html($platform['copy'] ? $url : $shareText); ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
