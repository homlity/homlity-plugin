<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Agent info component.
 * Overridable at homlity-real-estate/parts/property-agent-info.php
 *
 * Expected args: $post_id (int), $settings (array, optional — Elementor widget settings)
 */

use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\SeoGeoSettingsService;
use Homlity\PluginInmobiliario\Services\WhatsAppLinkService;

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$s      = $settings ?? [];
$source = $s['data_source'] ?? 'dynamic';

// ── Avatar fallback helper ────────────────────────────────────────────────────

$agentAvatarFallback = static function (string $alt): string {
    $logoUrl = SeoGeoSettingsService::get('company_logo', '');
    if (!$logoUrl) {
        return '';
    }
    return '<img src="' . esc_url($logoUrl) . '" alt="' . esc_attr($alt) . '">';
};

// ── Build agent data ──────────────────────────────────────────────────────────

if ($source === 'static') {
    $photoUrl  = $s['static_photo']['url'] ?? '';
    $photoId   = (int) ($s['static_photo']['id'] ?? 0);
    $name      = $s['static_name']  ?? '';
    $role      = $s['static_role']  ?? '';
    $phone     = $s['static_phone'] ?? '';
    $email     = $s['static_email'] ?? '';
    $profileUrl = '';

    if ($photoId) {
        $avatarHtml = wp_get_attachment_image($photoId, 'thumbnail', false, ['alt' => esc_attr($name)]);
    } elseif ($photoUrl) {
        $avatarHtml = '<img src="' . esc_url($photoUrl) . '" alt="' . esc_attr($name) . '">';
    } else {
        $avatarHtml = $agentAvatarFallback(get_bloginfo('name'));
    }

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
    $photoUrl   = '';

    // Resolve avatar: CRM photo → WP User Avatar plugin → Simple Local Avatars → company logo
    $avatarHtml = '';

    $crmPhotoUrl = (string) get_user_meta($agentUser->ID, '_homlity_advisor_photo', true);
    if ($crmPhotoUrl) {
        $avatarHtml = '<img src="' . esc_url($crmPhotoUrl) . '" alt="' . esc_attr($name) . '">';
    }

    if (!$avatarHtml) {
        $wpUaId = (int) get_user_meta($agentUser->ID, 'wp_user_avatar', true);
        if ($wpUaId > 0) {
            $avatarHtml = wp_get_attachment_image($wpUaId, [96, 96], false, ['alt' => esc_attr($name)]);
        }
    }

    if (!$avatarHtml) {
        $slaData = get_user_meta($agentUser->ID, 'simple_local_avatar', true);
        if (!empty($slaData['full'])) {
            $avatarHtml = '<img src="' . esc_url($slaData['full']) . '" alt="' . esc_attr($name) . '">';
        }
    }

    if (!$avatarHtml) {
        $avatarHtml = $agentAvatarFallback(get_bloginfo('name'));
    }
}

// ── Build CTAs ────────────────────────────────────────────────────────────────

$ctas = [];

if ($source === 'dynamic') {
    $whatsAppUrl = WhatsAppLinkService::buildPropertyLink((int) $post_id, (string) $phone);

    if (($s['show_cta_whatsapp'] ?? 'yes') === 'yes' && $whatsAppUrl) {
        $ctas[] = [
            'text'   => $s['cta_whatsapp_label'] ?? __('Contactar por WhatsApp', 'homlity-real-estate'),
            'url'    => $whatsAppUrl,
            'target' => '_blank',
        ];
    }
    if (($s['show_cta_profile'] ?? 'yes') === 'yes' && $profileUrl) {
        $ctas[] = [
            'text'   => $s['cta_profile_label'] ?? __('Ver perfil del asesor', 'homlity-real-estate'),
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
                <p class="property-agent-block__phone">
                    <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', (string) $phone)); ?>" data-homlity-contact-type="phone" data-property-id="<?php echo esc_attr($post_id); ?>">
                        <?php
                        $iconPhone = $s['icon_phone'] ?? [];
                        if (!empty($iconPhone['value'])): ?>
                            <span class="property-agent-block__icon property-agent-block__icon--phone" aria-hidden="true">
                                <?php \Elementor\Icons_Manager::render_icon($iconPhone, ['aria-hidden' => 'true']); ?>
                            </span>
                        <?php else: ?>
                            <span class="property-agent-block__icon property-agent-block__icon--phone" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false"><path d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.01-.24c1.11.37 2.29.56 3.5.56a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.97 21 3 13.03 3 3.99a1 1 0 0 1 1-1H7.5a1 1 0 0 1 1 1c0 1.21.19 2.39.56 3.5a1 1 0 0 1-.24 1.01l-2.2 2.29Z" /></svg>
                            </span>
                        <?php endif; ?>
                        <span><?php echo esc_html($phone); ?></span>
                    </a>
                </p>
            <?php endif; ?>

            <?php if ($email): ?>
                <p class="property-agent-block__email">
                    <a href="mailto:<?php echo esc_attr($email); ?>" data-homlity-contact-type="email" data-property-id="<?php echo esc_attr($post_id); ?>">
                        <?php
                        $iconEmail = $s['icon_email'] ?? [];
                        if (!empty($iconEmail['value'])): ?>
                            <span class="property-agent-block__icon property-agent-block__icon--email" aria-hidden="true">
                                <?php \Elementor\Icons_Manager::render_icon($iconEmail, ['aria-hidden' => 'true']); ?>
                            </span>
                        <?php else: ?>
                            <span class="property-agent-block__icon property-agent-block__icon--email" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false"><path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 4.24-8 5-8-5V6l8 5 8-5v2.24Z" /></svg>
                            </span>
                        <?php endif; ?>
                        <span><?php echo esc_html($email); ?></span>
                    </a>
                </p>
            <?php endif; ?>

            <?php if ($ctas): ?>
                <div class="property-agent-block__actions">
                    <?php foreach ($ctas as $cta): ?>
                        <a
                            class="property-agent-block__cta"
                            href="<?php echo esc_url($cta['url']); ?>"
                            target="<?php echo esc_attr($cta['target']); ?>"
                            data-homlity-contact-type="<?php echo (strpos((string) ($cta['url'] ?? ''), 'wa.me/') !== false || strpos((string) ($cta['url'] ?? ''), 'whatsapp.com/') !== false) ? 'whatsapp' : ''; ?>"
                            data-property-id="<?php echo esc_attr($post_id); ?>"
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
