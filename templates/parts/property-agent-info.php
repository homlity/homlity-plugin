<?php
/**
 * Agent info component.
 * Overridable at homlity-plugin/parts/property-agent-info.php
 *
 * Expected args: $post_id (int), $settings (array, optional — Elementor widget settings)
 */

use Homlity\PluginInmobiliario\Services\PropertyPostType;

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$s      = $settings ?? [];
$source = $s['data_source'] ?? 'dynamic';

// ── Build agent data ──────────────────────────────────────────────────────────

if ($source === 'static') {
    $photoUrl  = $s['static_photo']['url'] ?? '';
    $photoId   = (int) ($s['static_photo']['id'] ?? 0);
    $name      = $s['static_name']  ?? '';
    $role      = $s['static_role']  ?? '';
    $phone     = $s['static_phone'] ?? '';
    $email     = $s['static_email'] ?? '';
    $profileUrl = '';

    $avatarHtml = $photoId
        ? wp_get_attachment_image($photoId, 'thumbnail', false, ['alt' => esc_attr($name)])
        : ($photoUrl ? '<img src="' . esc_url($photoUrl) . '" alt="' . esc_attr($name) . '">' : '');

} else {
    $meta       = (new PropertyPostType())->metaKeys();
    $agentId    = (int) get_post_meta($post_id, $meta['agent_id'], true);
    $agentUser  = $agentId ? get_user_by('id', $agentId) : null;

    if (!$agentUser) {
        return;
    }

    $name  = $agentUser->display_name;
    $role  = '';
    $phone = get_user_meta($agentUser->ID, 'phone', true)
          ?: get_user_meta($agentUser->ID, 'billing_phone', true)
          ?: get_post_meta($post_id, $meta['agent_phone'], true);
    $email = $agentUser->user_email
          ?: get_post_meta($post_id, $meta['agent_email'], true);

    $profileUrl = home_url('/property-agent/' . $agentUser->user_nicename);
    $avatarHtml = get_avatar($agentUser->ID, 96);
    $photoUrl   = '';
}

// ── Build CTAs ────────────────────────────────────────────────────────────────

$ctas = [];

if ($source === 'dynamic') {
    $phoneDigits = $phone ? preg_replace('/\D+/', '', $phone) : '';
    $whatsAppUrl = $phoneDigits
        ? 'https://wa.me/' . $phoneDigits . '?text=' . rawurlencode(get_the_title($post_id) . ' - ' . get_permalink($post_id))
        : '';

    if (($s['show_cta_whatsapp'] ?? 'yes') === 'yes' && $whatsAppUrl) {
        $ctas[] = [
            'text'   => $s['cta_whatsapp_label'] ?? __('Contactar por WhatsApp', 'homlity-plugin'),
            'url'    => $whatsAppUrl,
            'target' => '_blank',
        ];
    }
    if (($s['show_cta_profile'] ?? 'yes') === 'yes' && $profileUrl) {
        $ctas[] = [
            'text'   => $s['cta_profile_label'] ?? __('Ver perfil del asesor', 'homlity-plugin'),
            'url'    => $profileUrl,
            'target' => '_self',
        ];
    }

} else {
    if (($s['cta1_show'] ?? 'yes') === 'yes' && !empty($s['cta1_text'])) {
        $cta1Url    = $s['cta1_url']['url'] ?? '';
        $cta1Target = !empty($s['cta1_url']['is_external']) ? '_blank' : '_self';
        if ($cta1Url) {
            $ctas[] = ['text' => $s['cta1_text'], 'url' => $cta1Url, 'target' => $cta1Target];
        }
    }
    if (($s['cta2_show'] ?? '') === 'yes' && !empty($s['cta2_text'])) {
        $cta2Url    = $s['cta2_url']['url'] ?? '';
        $cta2Target = !empty($s['cta2_url']['is_external']) ? '_blank' : '_self';
        if ($cta2Url) {
            $ctas[] = ['text' => $s['cta2_text'], 'url' => $cta2Url, 'target' => $cta2Target];
        }
    }
}

if (!$name && !$avatarHtml) {
    return;
}
?>
<div class="property-agent-block">
    <div class="property-agent-block__card">

        <?php if ($avatarHtml): ?>
            <div class="property-agent-block__avatar">
                <?php echo $avatarHtml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        <?php endif; ?>

        <div class="property-agent-block__info">

            <?php if ($name): ?>
                <p class="property-agent-block__name">
                    <?php if ($profileUrl): ?>
                        <a href="<?php echo esc_url($profileUrl); ?>"><?php echo esc_html($name); ?></a>
                    <?php else: ?>
                        <?php echo esc_html($name); ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if ($role): ?>
                <p class="property-agent-block__role"><?php echo esc_html($role); ?></p>
            <?php endif; ?>

            <?php if ($phone): ?>
                <p class="property-agent-block__phone"><?php echo esc_html($phone); ?></p>
            <?php endif; ?>

            <?php if ($email): ?>
                <p class="property-agent-block__email">
                    <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                </p>
            <?php endif; ?>

            <?php if ($ctas): ?>
                <div class="property-agent-block__actions">
                    <?php foreach ($ctas as $cta): ?>
                        <a
                            class="property-agent-block__cta"
                            href="<?php echo esc_url($cta['url']); ?>"
                            target="<?php echo esc_attr($cta['target']); ?>"
                            <?php echo $cta['target'] === '_blank' ? 'rel="noopener noreferrer"' : ''; ?>
                        >
                            <?php echo esc_html($cta['text']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
