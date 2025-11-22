<?php
/**
 * Agent info component.
 * Overridable at plugin-inmobiliario/parts/property-agent-info.php
 *
 * Expected args: $post_id (int)
 */

use Codwelt\PluginInmobiliario\Services\PropertyPostType;

if (!isset($post_id)) {
    $post_id = get_the_ID();
}

$meta = (new PropertyPostType())->metaKeys();
$agentId = (int) get_post_meta($post_id, $meta['agent_id'], true);
$agentUser = $agentId ? get_user_by('id', $agentId) : null;
$agentPhone = $agentUser ? (get_user_meta($agentUser->ID, 'phone', true) ?: get_user_meta($agentUser->ID, 'billing_phone', true)) : '';
$agentEmail = $agentUser ? $agentUser->user_email : '';

// Backward compatibility with stored meta.
if (!$agentPhone) {
    $agentPhone = get_post_meta($post_id, $meta['agent_phone'], true);
}
if (!$agentEmail) {
    $agentEmail = get_post_meta($post_id, $meta['agent_email'], true);
}

if (!$agentUser) {
    return;
}

$phoneDigits = $agentPhone ? preg_replace('/\D+/', '', $agentPhone) : '';
$whatsApp = $phoneDigits
    ? 'https://wa.me/' . $phoneDigits . '?text=' . rawurlencode(get_the_title($post_id) . ' - ' . get_permalink($post_id))
    : '';
?>
<aside class="property-agent-block">
    <h2><?php esc_html_e('Asesor comercial', 'inmopress-listings-inmobiliaria'); ?></h2>
    <div class="property-agent-block__card">
        <div class="property-agent-block__avatar">
            <?php echo get_avatar($agentUser->ID, 96); ?>
        </div>
        <div class="property-agent-block__info">
            <h3>
                <a href="<?php echo esc_url(home_url('/property-agent/' . $agentUser->user_nicename)); ?>">
                    <?php echo esc_html($agentUser->display_name); ?>
                </a>
            </h3>
            <?php if ($agentPhone): ?>
                <p><?php echo esc_html($agentPhone); ?></p>
            <?php endif; ?>
            <?php if ($agentEmail): ?>
                <p><a href="mailto:<?php echo esc_attr($agentEmail); ?>"><?php echo esc_html($agentEmail); ?></a></p>
            <?php endif; ?>
            <div class="property-agent-block__actions">
                <?php if ($whatsApp): ?>
                    <a class="property-agent-block__whatsapp" href="<?php echo esc_url($whatsApp); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Contactar por WhatsApp', 'inmopress-listings-inmobiliaria'); ?>
                    </a>
                <?php endif; ?>
                <a class="property-agent-block__profile" href="<?php echo esc_url(home_url('/property-agent/' . $agentUser->user_nicename)); ?>">
                    <?php esc_html_e('Ver perfil del asesor', 'inmopress-listings-inmobiliaria'); ?>
                </a>
            </div>
        </div>
    </div>
</aside>
