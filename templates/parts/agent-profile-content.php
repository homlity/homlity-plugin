<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Agent profile content component. Also what [homlity_agent_profile] renders.
 * Overridable at homlity-real-estate/parts/agent-profile-content.php
 */

use Homlity\PluginInmobiliario\Listing\ListingConfig;
use Homlity\PluginInmobiliario\Listing\ListingRenderer;
use Homlity\PluginInmobiliario\Services\AgentProfileService;
use Homlity\PluginInmobiliario\Services\WhatsAppLinkService;

$agentUser = AgentProfileService::currentAgent();

if (!$agentUser) {
    echo '<main class="property-agent"><p>' . esc_html__('Asesor no encontrado.', 'homlity-real-estate') . '</p></main>';
    return;
}

$agent = AgentProfileService::agentData($agentUser);

$whatsAppUrl = $agent['phone'] !== ''
    ? WhatsAppLinkService::buildAgentLink(
        $agent['phone'],
        sprintf(
            /* translators: %s: advisor name */
            __('Hola %s, vi tu perfil en el sitio web y quiero más información.', 'homlity-real-estate'),
            $agent['name']
        )
    )
    : '';

$listingConfig = ListingConfig::fromArray([
    'default_view'        => 'grid',
    'show_view_toggle'    => false,
    'show_map_view'       => false,
    'columns'             => 3,
    'posts_per_page'      => 12,
    'orderby'             => 'date',
    'query_mode'          => 'custom',
    'use_current_agent'   => true,
    'show_sort'           => true,
    'show_results_count'  => true,
    'show_pagination'     => true,
    'card_media_mode'     => 'single',
    'card_visual_preset'  => 'default',
    'card_hover_effect'   => 'lift',
    'card_show_title'     => true,
    'card_show_excerpt'   => true,
    'card_show_operation' => true,
    'card_show_price'     => true,
    'card_show_features'  => true,
    'card_show_whatsapp'  => true,
    'card_whatsapp_icon_position' => 'left',
    'card_whatsapp_show_icon'     => true,
    'card_feature_area'      => true,
    'card_feature_bedrooms'  => true,
    'card_feature_bathrooms' => true,
    'card_feature_parking'   => true,
]);
?>
<main class="property-agent">
    <header class="property-agent__header">
        <?php if ($agent['avatar_html'] !== '') : ?>
            <div class="property-agent__avatar">
                <?php echo $agent['avatar_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        <?php endif; ?>
        <div class="property-agent__info">
            <h1 class="property-agent__name"><?php echo esc_html($agent['name']); ?></h1>

            <?php if ($agent['role'] !== '') : ?>
                <p class="property-agent__role"><?php echo esc_html($agent['role']); ?></p>
            <?php endif; ?>

            <?php if ($agent['property_count'] > 0) : ?>
                <p class="property-agent__count">
                    <?php
                    echo esc_html(sprintf(
                        /* translators: %d: number of available properties */
                        _n('%d inmueble disponible', '%d inmuebles disponibles', $agent['property_count'], 'homlity-real-estate'),
                        $agent['property_count']
                    ));
                    ?>
                </p>
            <?php endif; ?>

            <?php if ($agent['phone'] !== '') : ?>
                <p class="property-agent__phone">
                    <a href="tel:<?php echo esc_attr((string) preg_replace('/\s+/', '', $agent['phone'])); ?>">
                        <?php echo esc_html($agent['phone']); ?>
                    </a>
                </p>
            <?php endif; ?>

            <?php if ($agent['email'] !== '') : ?>
                <p class="property-agent__email">
                    <a href="mailto:<?php echo esc_attr($agent['email']); ?>"><?php echo esc_html($agent['email']); ?></a>
                </p>
            <?php endif; ?>

            <?php if ($whatsAppUrl !== '') : ?>
                <p class="property-agent__actions">
                    <a class="property-agent__cta" href="<?php echo esc_url($whatsAppUrl); ?>" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Contactar por WhatsApp', 'homlity-real-estate'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($agent['bio'] !== '') : ?>
        <div class="property-agent__bio">
            <?php echo wp_kses_post(wpautop($agent['bio'])); ?>
        </div>
    <?php endif; ?>

    <section class="property-agent__properties">
        <h2><?php esc_html_e('Inmuebles del asesor', 'homlity-real-estate'); ?></h2>
        <?php (new ListingRenderer())->render($listingConfig); ?>
    </section>
</main>
