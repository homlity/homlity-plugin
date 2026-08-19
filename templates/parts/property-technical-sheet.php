<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Technical sheet content.
 * Expected args: $post_id (int), $settings (array)
 *
 * $settings comes from the "Ficha técnica del inmueble" widget (Elementor,
 * Divi or WPBakery). Every section can be switched off there; the address is
 * hidden unless it is explicitly enabled.
 */

use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\TechnicalSheetData;

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($post_id) || !$post_id) {
    $post_id = get_the_ID();
}

if (!$post_id) {
    return;
}

$settings = isset($settings) && is_array($settings) ? $settings : [];

// Los datos salen de TechnicalSheetData, que es de donde los toma también la
// plantilla del PDF: con la preparación aquí dentro, cambiar de dónde sale un
// dato obligaba a tocar las dos y a que no se descuadraran por su cuenta.
$sheet = TechnicalSheetData::forProperty((int) $post_id, $settings);

$show            = $sheet['show'];
$showAddress     = $show['address'];
$showHero        = $show['hero'];
$showActions     = $show['actions'];
$showAdvisor     = $show['advisor'];
$showFinance     = $show['finance'];
$showInfo        = $show['info'];
$showDimensions  = $show['dimensions'];
$showDescription = $show['description'];
$showFeatures    = $show['features'];
$showMedia       = $show['media'];
$showLegal       = $show['legal'];

$meta         = (new PropertyPostType())->metaKeys();
$asText       = static fn($value): string => TechnicalSheetData::text($value);

$companyName  = $sheet['company']['name'];
$companyLogo  = $sheet['company']['logo'];
$primaryColor = $sheet['primary_color'];

$title       = $sheet['title'];
$description = $sheet['description'];
$permalink   = $sheet['permalink'];

$agentName   = $sheet['agent']['name'];
$agentPhone  = $sheet['agent']['phone'];
$agentEmail  = $sheet['agent']['email'];
$agentAvatar = $sheet['agent']['photo'];
$whatsAppUrl = $sheet['agent']['whatsapp'];

$images     = $sheet['media']['images'];
$videos     = $sheet['media']['videos'];
$tours      = $sheet['media']['tours'];
$photos360  = $sheet['media']['photos_360'];
$brochure   = $sheet['media']['brochure'];

$infoItems      = $sheet['info'];
$dimensionItems = $sheet['dimensions'];
$financeItems   = $sheet['finance'];
$features       = $sheet['features'];
?>
<?php
$_r = (int) hexdec( substr( ltrim( $primaryColor, '#' ), 0, 2 ) );
$_g = (int) hexdec( substr( ltrim( $primaryColor, '#' ), 2, 2 ) );
$_b = (int) hexdec( substr( ltrim( $primaryColor, '#' ), 4, 2 ) );
?>
<main class="homlity-tech-sheet" style="--sheet-primary:<?php echo esc_attr( $primaryColor ); ?>;--sheet-primary-rgb:<?php echo esc_attr( "$_r,$_g,$_b" ); ?>">

    <?php if ($showHero) : ?>
        <div class="homlity-tech-sheet__hero">
            <div class="homlity-tech-sheet__hero-brand">
                <?php if ($companyLogo) : ?>
                    <img src="<?php echo esc_url($companyLogo); ?>" alt="<?php echo esc_attr($companyName); ?>" class="homlity-tech-sheet__hero-logo">
                <?php endif; ?>
                <div class="homlity-tech-sheet__hero-text">
                    <p class="homlity-tech-sheet__brand-label"><?php esc_html_e('Ficha técnica inmueble', 'homlity-real-estate'); ?></p>
                    <h1><?php echo esc_html($title); ?></h1>
                    <?php if ($showAddress) : ?>
                        <p class="homlity-tech-sheet__hero-subtitle"><?php echo esc_html($asText(get_post_meta($post_id, $meta['address'], true))); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($showActions) : ?>
                <div class="homlity-tech-sheet__actions">
                    <a class="homlity-tech-sheet__back" href="<?php echo esc_url($permalink); ?>"><?php esc_html_e('Volver al inmueble', 'homlity-real-estate'); ?></a>
                    <button type="button" class="homlity-tech-sheet__print" onclick="window.print()"><?php esc_html_e('Imprimir ficha', 'homlity-real-estate'); ?></button>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($showAdvisor) : ?>
        <section class="homlity-tech-sheet__card homlity-tech-sheet__advisor">
            <h2><?php esc_html_e('Información del asesor', 'homlity-real-estate'); ?></h2>
            <div class="homlity-tech-sheet__advisor-row">
                <div class="homlity-tech-sheet__advisor-avatar-wrap">
                    <?php if ($agentAvatar) : ?>
                        <img src="<?php echo esc_url($agentAvatar); ?>" alt="<?php echo esc_attr($asText($agentName)); ?>" class="homlity-tech-sheet__advisor-avatar">
                    <?php else : ?>
                        <div class="homlity-tech-sheet__advisor-avatar homlity-tech-sheet__advisor-avatar--fallback"></div>
                    <?php endif; ?>
                </div>
                <div class="homlity-tech-sheet__grid homlity-tech-sheet__grid--advisor">
                    <div><strong><?php esc_html_e('Nombre', 'homlity-real-estate'); ?>:</strong> <?php echo esc_html($asText($agentName)); ?></div>
                    <div>
                        <strong><?php esc_html_e('Teléfono', 'homlity-real-estate'); ?>:</strong>
                        <?php if ($agentPhone) : ?>
                            <a href="<?php echo esc_attr('tel:' . preg_replace('/\s+/', '', (string) $agentPhone)); ?>" data-homlity-contact-type="phone" data-property-id="<?php echo esc_attr($post_id); ?>"><?php echo esc_html($agentPhone); ?></a>
                        <?php else : ?>
                            <?php esc_html_e('Sin dato', 'homlity-real-estate'); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong><?php esc_html_e('Correo', 'homlity-real-estate'); ?>:</strong>
                        <?php if ($agentEmail) : ?>
                            <a href="<?php echo esc_attr('mailto:' . $agentEmail); ?>" data-homlity-contact-type="email" data-property-id="<?php echo esc_attr($post_id); ?>"><?php echo esc_html($agentEmail); ?></a>
                        <?php else : ?>
                            <?php esc_html_e('Sin dato', 'homlity-real-estate'); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong><?php esc_html_e('WhatsApp', 'homlity-real-estate'); ?>:</strong>
                        <?php if ($whatsAppUrl) : ?>
                            <a href="<?php echo esc_url($whatsAppUrl); ?>" target="_blank" rel="noopener noreferrer" data-homlity-contact-type="whatsapp" data-property-id="<?php echo esc_attr($post_id); ?>"><?php esc_html_e('Contactar asesor', 'homlity-real-estate'); ?></a>
                        <?php else : ?>
                            <?php esc_html_e('Sin dato', 'homlity-real-estate'); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($showFinance) : ?>
        <section class="homlity-tech-sheet__card">
            <h2><?php esc_html_e('Finanzas', 'homlity-real-estate'); ?></h2>
            <div class="homlity-tech-sheet__stats">
                <?php foreach ($financeItems as $item) : ?>
                    <article class="homlity-tech-sheet__stat">
                        <span class="homlity-tech-sheet__stat-label"><?php echo esc_html($item['label']); ?></span>
                        <strong class="homlity-tech-sheet__stat-value"><?php echo esc_html($item['value']); ?></strong>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($showInfo) : ?>
        <section class="homlity-tech-sheet__card">
            <h2><?php esc_html_e('Información general del inmueble', 'homlity-real-estate'); ?></h2>
            <div class="homlity-tech-sheet__grid">
                <?php foreach ($infoItems as $item) : ?>
                    <div><strong><?php echo esc_html($item['label']); ?>:</strong> <?php echo esc_html($item['value']); ?></div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($showDimensions) : ?>
        <section class="homlity-tech-sheet__card">
            <h2><?php esc_html_e('Dimensiones y ambientes', 'homlity-real-estate'); ?></h2>
            <div class="homlity-tech-sheet__grid">
                <?php foreach ($dimensionItems as $item) : ?>
                    <div><strong><?php echo esc_html($item['label']); ?>:</strong> <?php echo esc_html($item['value']); ?></div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($showDescription) : ?>
        <section class="homlity-tech-sheet__card">
            <h2><?php esc_html_e('Descripción completa', 'homlity-real-estate'); ?></h2>
            <div class="homlity-tech-sheet__description"><?php echo wp_kses_post($description); ?></div>
        </section>
    <?php endif; ?>

    <?php if ($showFeatures) : ?>
        <section class="homlity-tech-sheet__card">
            <h2><?php esc_html_e('Características del inmueble', 'homlity-real-estate'); ?></h2>
            <?php if ($features) : ?>
                <ul class="homlity-tech-sheet__features-list">
                    <?php foreach ($features as $feature) : ?>
                        <li><?php echo esc_html($feature); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><?php esc_html_e('Sin características registradas.', 'homlity-real-estate'); ?></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($showMedia) : ?>
        <section class="homlity-tech-sheet__card">
            <h2><?php esc_html_e('Catálogo multimedia', 'homlity-real-estate'); ?></h2>

            <?php if ($images) : ?>
                <h3><?php esc_html_e('Fotos', 'homlity-real-estate'); ?></h3>
                <div class="homlity-tech-sheet__gallery">
                    <?php foreach (array_slice($images, 0, 18) as $image) : ?>
                        <a href="<?php echo esc_url($image); ?>" target="_blank" rel="noopener noreferrer">
                            <img src="<?php echo esc_url($image); ?>" alt="">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($videos || $tours || $photos360 || $brochure) : ?>
                <div class="homlity-tech-sheet__media-links">
                    <?php if ($videos) : ?>
                        <div>
                            <h3><?php esc_html_e('Videos', 'homlity-real-estate'); ?></h3>
                            <ul><?php foreach ($videos as $url) : ?><li><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($url); ?></a></li><?php endforeach; ?></ul>
                        </div>
                    <?php endif; ?>
                    <?php if ($tours) : ?>
                        <div>
                            <h3><?php esc_html_e('Recorridos 360', 'homlity-real-estate'); ?></h3>
                            <ul><?php foreach ($tours as $url) : ?><li><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($url); ?></a></li><?php endforeach; ?></ul>
                        </div>
                    <?php endif; ?>
                    <?php if ($photos360) : ?>
                        <div>
                            <h3><?php esc_html_e('Fotos 360', 'homlity-real-estate'); ?></h3>
                            <ul><?php foreach ($photos360 as $url) : ?><li><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($url); ?></a></li><?php endforeach; ?></ul>
                        </div>
                    <?php endif; ?>
                    <?php if ($brochure) : ?>
                        <div>
                            <h3><?php esc_html_e('Brochure', 'homlity-real-estate'); ?></h3>
                            <p><a href="<?php echo esc_url($brochure); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($brochure); ?></a></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($showLegal) : ?>
        <section class="homlity-tech-sheet__card">
            <p class="homlity-tech-sheet__legal">
                <?php echo esc_html($companyName); ?> · <?php echo esc_html(date_i18n('Y-m-d H:i')); ?> ·
                <?php esc_html_e('Propiedad sujeta a disponibilidad. Precio e información sujetos a cambios sin previo aviso.', 'homlity-real-estate'); ?>
            </p>
        </section>
    <?php endif; ?>
</main>
