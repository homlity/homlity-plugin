<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Ficha técnica del inmueble, versión PDF.
 * Se puede sobrescribir en el tema en homlity-real-estate/parts/property-technical-sheet-pdf.php
 *
 * Argumentos: $post_id (int), $settings (array, opcional — del widget).
 *
 * Reproduce la ficha del sistema
 * (resources/views/mails/sistema/layouts/fichaTecnicaLabel.blade.php): cabecera
 * y pie repetidos en cada página, y el cuerpo en tarjetas con encabezado en el
 * color de la marca. La retícula va en tablas porque es lo único con lo que
 * Dompdf reparte bien el ancho —no aplica `box-sizing`, así que un div con
 * padding no cabe tres veces en una fila—.
 *
 * Es una plantilla aparte de property-technical-sheet.php a propósito: esa
 * sigue sirviendo la ficha en pantalla, con sus botones y su maquetación
 * responsive, que en un archivo no pintan nada. Los datos salen de
 * TechnicalSheetData, así que no hay dos sitios donde mantenerlos.
 */

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

$sheet = TechnicalSheetData::forProperty((int) $post_id, isset($settings) && is_array($settings) ? $settings : []);

$show     = $sheet['show'];
$company  = $sheet['company'];
$agent    = $sheet['agent'];
$media    = $sheet['media'];
$primary  = $sheet['primary_color'];
$colors   = $sheet['colors'];
$asText   = static fn($value): string => TechnicalSheetData::text($value);

$headingStyle = 'color:' . $primary . ';';
// El asesor vive en la cabecera, que se repite en todas las páginas: por eso
// el interruptor del widget manda aquí y no sobre una tarjeta suelta. Apagado,
// la cabecera cae en la inmobiliaria.
$hasAgent = $show['advisor'] && trim((string) $agent['name']) !== '';

/**
 * Iconos como SVG en data URI.
 *
 * Dompdf los dibuja como vectores, así que se pueden teñir con el color de la
 * marca sin arrastrar un PNG por cada color. El original usa PNG en base64
 * porque Html2Pdf no dibuja SVG.
 */
$icon = static function (string $name, string $color): string {
    // Cada icono se arma con primitivas y no con un contorno de una sola
    // pieza: un path largo con curvas encadenadas Dompdf lo rellena entero y
    // el dibujo sale como una mancha.
    $shapes = [
        'phone' => '<path fill="%1$s" d="M4.6 2h2.1l1 2.6-1.3 1a8.4 8.4 0 0 0 4 4l1-1.3 2.6 1v2.1c0 .6-.5 1.1-1.1 1C6.9 12 4 9.1 3.6 3.1c0-.6.4-1.1 1-1.1z"/>',
        'mail' => '<rect x="2" y="3.5" width="12" height="9" rx="1" fill="none" stroke="%1$s" stroke-width="1.2"/>'
            . '<path d="M2.4 4 8 8.2 13.6 4" fill="none" stroke="%1$s" stroke-width="1.2"/>',
        'whatsapp' => '<path fill="%1$s" d="M8 2a6 6 0 0 0-5.1 9.2L2.2 14l2.9-.7A6 6 0 1 0 8 2z"/>'
            . '<path fill="#ffffff" d="M6.2 5.3c.2 0 .3 0 .4.3l.5 1.1-.5.6c.4.8 1 1.4 1.8 1.8l.6-.5 1.1.5c.2.1.3.2.3.4 0 .5-.4.9-.9 1-1.6 0-4-2.4-4-4 0-.5.3-1 .7-1.2z"/>',
        'check' => '<path fill="%1$s" d="M6.4 12.4 2.2 8.2l1.4-1.4 2.8 2.8 6-6L13.8 5z"/>',
        'clock' => '<circle cx="8" cy="8" r="6" fill="none" stroke="%1$s" stroke-width="1.2"/>'
            . '<path d="M8 4.4V8.2l2.6 1.6" fill="none" stroke="%1$s" stroke-width="1.2"/>',
        'globe' => '<circle cx="8" cy="8" r="6" fill="none" stroke="%1$s" stroke-width="1.2"/>'
            . '<path d="M2 8h12M8 2c1.8 2 1.8 10 0 12M8 2C6.2 4 6.2 12 8 14" fill="none" stroke="%1$s" stroke-width="1.2"/>',
        'arrow' => '<circle cx="8" cy="8" r="6" fill="%1$s"/>'
            . '<path fill="#ffffff" d="m6.6 4.8 4 3.2-4 3.2z"/>',
    ];

    if (!isset($shapes[$name])) {
        return '';
    }

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16">'
        . sprintf($shapes[$name], $color)
        . '</svg>';

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
};

/** Un `<img>` de icono, ya dimensionado. */
$iconTag = static function (string $name, string $color, int $size = 11) use ($icon): string {
    $uri = $icon($name, $color);
    if ($uri === '') {
        return '';
    }

    return '<img src="' . $uri . '" width="' . $size . '" height="' . $size . '" alt="" style="vertical-align:middle">';
};

/** Filas de tres, rellenando la última con celdas vacías. */
$inThrees = static function (array $items): array {
    return array_chunk($items, 3);
};

$location = array_values(array_filter([
    $company['neighborhood'],
    $company['city'],
    $company['state'],
]));
?>
<div class="sheet-header">
    <div class="section-card" style="border-color:<?php echo esc_attr($primary); ?>">
        <table>
            <tr>
                <td style="width:170px" class="sheet-header__brand">
                    <?php if ($company['logo']) : ?>
                        <a href="<?php echo esc_url($company['website']); ?>">
                            <img src="<?php echo esc_url($company['logo']); ?>" alt="<?php echo esc_attr($company['name']); ?>" class="sheet-header__logo">
                        </a>
                    <?php else : ?>
                        <div class="section-value"><?php echo esc_html($company['name']); ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="section-heading" style="<?php echo esc_attr($headingStyle); ?>"><?php esc_html_e('Ficha técnica', 'homlity-real-estate'); ?></div>
                    <div class="sheet-header__contact">
                        <?php if ($hasAgent) : ?>
                            <div class="section-value"><?php echo esc_html($agent['name']); ?></div>
                            <div class="stat-label"><?php echo esc_html($company['name']); ?></div>
                            <?php if ($agent['phone']) : ?>
                                <a href="<?php echo esc_attr('tel:' . preg_replace('/(?!^\+)\D+/', '', $agent['phone'])); ?>">
                                    <?php echo $iconTag('phone', $primary); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <?php echo esc_html($agent['phone']); ?>
                                </a>
                            <?php endif; ?>
                            <?php if ($agent['email']) : ?>
                                <a href="<?php echo esc_attr('mailto:' . $agent['email']); ?>">
                                    <?php echo $iconTag('mail', $primary); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <span class="break-all"><?php echo esc_html($agent['email']); ?></span>
                                </a>
                            <?php endif; ?>
                            <?php if ($agent['whatsapp']) : ?>
                                <a href="<?php echo esc_url($agent['whatsapp']); ?>">
                                    <?php echo $iconTag('whatsapp', $primary); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <?php esc_html_e('WhatsApp', 'homlity-real-estate'); ?>
                                </a>
                            <?php endif; ?>
                        <?php else : ?>
                            <div class="section-value"><?php echo esc_html($company['name']); ?></div>
                            <?php if ($company['phone']) : ?>
                                <a href="<?php echo esc_attr('tel:' . preg_replace('/(?!^\+)\D+/', '', $company['phone'])); ?>">
                                    <?php echo $iconTag('phone', $primary); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <?php echo esc_html($company['phone']); ?>
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo esc_url($company['website']); ?>" class="break-all">
                                <?php echo $iconTag('globe', $primary); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <?php echo esc_html($company['website']); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </td>
                <?php if ($hasAgent && $agent['photo'] !== '') : ?>
                    <?php
                    // Sin `object-fit`, quién llena el marco depende de la
                    // orientación: una foto vertical se estira a lo ancho y se
                    // recorta por abajo —la cara queda arriba—, y una apaisada
                    // se estira a lo alto y se recorta por los lados. Cuando no
                    // se sabe se trata como vertical, que es como viene casi
                    // toda foto de perfil.
                    $fill = $agent['photo_orientation'] === 'landscape'
                        ? 'sheet-header__portrait-img--tall'
                        : 'sheet-header__portrait-img--wide';
                    ?>
                    <td style="width:84px">
                        <div class="sheet-header__portrait" style="border-color:<?php echo esc_attr($primary); ?>">
                            <img src="<?php echo esc_url($agent['photo']); ?>"
                                alt="<?php echo esc_attr($agent['name']); ?>"
                                class="<?php echo esc_attr($fill); ?>">
                        </div>
                    </td>
                <?php endif; ?>
            </tr>
        </table>
    </div>
</div>

<div class="sheet-footer">
    <div class="sheet-footer__bar">
        <?php
        printf(
            /* translators: 1: nombre de la inmobiliaria, 2: año, 3: dominio del sitio. */
            esc_html__('%1$s — %2$s | Ficha técnica generada por %3$s', 'homlity-real-estate'),
            esc_html($company['name']),
            esc_html(date_i18n('Y')),
            esc_html((string) wp_parse_url(home_url('/'), PHP_URL_HOST))
        );
        ?>
    </div>
</div>

<main class="sheet">

    <div class="section-card no-break">
        <div class="section-heading" style="<?php echo esc_attr($headingStyle); ?>"><?php echo esc_html($sheet['title']); ?></div>
        <?php if ($show['address'] && $sheet['address'] !== '') : ?>
            <div class="section-value"><?php echo esc_html($sheet['address']); ?></div>
        <?php endif; ?>
        <div class="font-11 c-gray margin-top-4 break-all"><?php echo esc_html($sheet['permalink']); ?></div>
    </div>

    <div class="section-card no-break">
        <div class="section-heading" style="<?php echo esc_attr($headingStyle); ?>"><?php esc_html_e('Información pública de la inmobiliaria', 'homlity-real-estate'); ?></div>
        <table class="section-table">
            <tr>
                <td style="width:50%">
                    <div class="section-label"><?php esc_html_e('Documento', 'homlity-real-estate'); ?></div>
                    <div class="section-value"><?php echo esc_html($asText($company['document'])); ?></div>
                </td>
                <td style="width:50%">
                    <div class="section-label"><?php esc_html_e('Sitio web', 'homlity-real-estate'); ?></div>
                    <div class="section-value break-all c-gray"><?php echo esc_html($asText($company['website'])); ?></div>
                </td>
            </tr>
            <tr>
                <td style="width:50%">
                    <div class="section-label"><?php esc_html_e('Correo', 'homlity-real-estate'); ?></div>
                    <div class="section-value break-all"><?php echo esc_html($asText($company['email'])); ?></div>
                </td>
                <td style="width:50%">
                    <div class="section-label"><?php esc_html_e('Teléfono', 'homlity-real-estate'); ?></div>
                    <div class="section-value"><?php echo esc_html($asText($company['phone'])); ?></div>
                </td>
            </tr>
            <tr>
                <?php
                // La calle y la ciudad de la oficina son un solo dato: en dos
                // celdas parecía que la ficha decía dos veces dónde está la
                // inmobiliaria.
                $officeAddress = array_values(array_filter(array_merge(
                    [$company['address']],
                    $location
                )));
                ?>
                <td colspan="2">
                    <div class="section-label"><?php esc_html_e('Dirección', 'homlity-real-estate'); ?></div>
                    <div class="section-value"><?php echo esc_html($officeAddress ? implode(' · ', $officeAddress) : 'Sin dato'); ?></div>
                </td>
            </tr>
        </table>
    </div>

    <?php if ($show['finance']) : ?>
        <div class="section-card no-break">
            <div class="section-heading" style="<?php echo esc_attr($headingStyle); ?>"><?php esc_html_e('Finanzas y administración', 'homlity-real-estate'); ?></div>
            <table>
                <tr>
                    <?php foreach ($sheet['finance'] as $item) : ?>
                        <td class="stat-cell">
                            <div class="stat-card">
                                <div class="stat-label"><?php echo esc_html($item['label']); ?></div>
                                <div class="stat-value"><?php echo esc_html($item['value']); ?></div>
                            </div>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($show['dimensions']) : ?>
        <div class="section-card no-break">
            <div class="section-heading" style="<?php echo esc_attr($headingStyle); ?>"><?php esc_html_e('Dimensiones y ambientes', 'homlity-real-estate'); ?></div>
            <table>
                <?php foreach ($inThrees($sheet['dimensions']) as $row) : ?>
                    <tr>
                        <?php foreach ($row as $item) : ?>
                            <td class="stat-cell">
                                <div class="stat-card">
                                    <div class="stat-label"><?php echo esc_html($item['label']); ?></div>
                                    <div class="stat-value"><?php echo esc_html($item['value']); ?></div>
                                </div>
                            </td>
                        <?php endforeach; ?>
                        <?php for ($i = count($row); $i < 3; $i++) : ?>
                            <td class="stat-cell"></td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($show['info']) : ?>
        <div class="section-card no-break">
            <div class="section-heading" style="<?php echo esc_attr($headingStyle); ?>"><?php esc_html_e('Detalles clave', 'homlity-real-estate'); ?></div>
            <table class="section-table">
                <?php foreach ($inThrees($sheet['info']) as $row) : ?>
                    <tr>
                        <?php foreach ($row as $item) : ?>
                            <td style="width:33.33%">
                                <div class="section-label"><?php echo esc_html($item['label']); ?></div>
                                <div class="section-value"><?php echo esc_html($item['value']); ?></div>
                            </td>
                        <?php endforeach; ?>
                        <?php for ($i = count($row); $i < 3; $i++) : ?>
                            <td style="width:33.33%"></td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($show['description'] && trim(wp_strip_all_tags($sheet['description'])) !== '') : ?>
        <div class="section-card">
            <div class="section-heading" style="<?php echo esc_attr($headingStyle); ?>"><?php esc_html_e('Descripción del inmueble', 'homlity-real-estate'); ?></div>
            <div class="description"><?php echo wp_kses_post($sheet['description']); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($show['features'] && $sheet['features']) : ?>
        <div class="section-card">
            <div class="section-heading" style="<?php echo esc_attr($headingStyle); ?>"><?php esc_html_e('Características', 'homlity-real-estate'); ?></div>
            <table>
                <?php foreach ($inThrees($sheet['features']) as $row) : ?>
                    <tr>
                        <?php foreach ($row as $feature) : ?>
                            <td class="feature-cell">
                                <div class="feature-chip">
                                    <?php echo $iconTag('check', $primary); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <?php echo esc_html($feature); ?>
                                </div>
                            </td>
                        <?php endforeach; ?>
                        <?php for ($i = count($row); $i < 3; $i++) : ?>
                            <td class="feature-cell"></td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

    <?php
    $photos = array_slice($media['images'], 0, \Homlity\PluginInmobiliario\Services\TechnicalSheetData::PDF_GALLERY_LIMIT);
    $hasLinks = $media['videos'] || $media['tours'] || $media['photos_360'] || $media['brochure'];
    ?>
    <?php if ($show['media'] && ($photos || $hasLinks)) : ?>
        <div class="section-card">
            <div class="section-heading" style="<?php echo esc_attr($headingStyle); ?>"><?php esc_html_e('Catálogo multimedia', 'homlity-real-estate'); ?></div>

            <?php if ($photos) : ?>
                <table>
                    <?php foreach ($inThrees($photos) as $row) : ?>
                        <tr>
                            <?php foreach ($row as $photo) : ?>
                                <td class="photo-cell">
                                    <div class="photo-frame"><img src="<?php echo esc_url($photo); ?>" alt=""></div>
                                </td>
                            <?php endforeach; ?>
                            <?php for ($i = count($row); $i < 3; $i++) : ?>
                                <td class="photo-cell"></td>
                            <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

            <?php if ($hasLinks) : ?>
                <table class="media-links margin-top-6">
                    <tr>
                        <?php
                        $linkGroups = array_filter([
                            __('Videos', 'homlity-real-estate') => $media['videos'],
                            __('Recorridos 360', 'homlity-real-estate') => $media['tours'],
                            __('Fotos 360', 'homlity-real-estate') => $media['photos_360'],
                            __('Brochure', 'homlity-real-estate') => $media['brochure'] !== '' ? [$media['brochure']] : [],
                        ]);
                        foreach ($linkGroups as $label => $urls) :
                            ?>
                            <td>
                                <div class="section-label"><?php echo esc_html($label); ?></div>
                                <ul>
                                    <?php foreach ($urls as $url) : ?>
                                        <li class="break-all"><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($url); ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                        <?php endforeach; ?>
                        <?php for ($i = count($linkGroups); $i < 3; $i++) : ?>
                            <td></td>
                        <?php endfor; ?>
                    </tr>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($hasAgent && $agent['phone']) : ?>
        <div class="section-card no-break">
            <div class="section-heading" style="<?php echo esc_attr($headingStyle); ?>"><?php esc_html_e('¿Cómo seguimos?', 'homlity-real-estate'); ?></div>
            <table>
                <tr>
                    <?php
                    $ctas = [
                        [
                            __('¿Tiene alguna duda?', 'homlity-real-estate'),
                            __('Contacta al asesor', 'homlity-real-estate'),
                            'Buen día, estoy interesado en ' . $sheet['title'],
                        ],
                        [
                            __('¿Quiere conocer la vivienda?', 'homlity-real-estate'),
                            __('Agendar visita', 'homlity-real-estate'),
                            'Buen día, quiero agendar una visita al inmueble ' . $sheet['title'],
                        ],
                        [
                            __('¿Quiere hacer una oferta?', 'homlity-real-estate'),
                            __('Haz tu oferta aquí', 'homlity-real-estate'),
                            'Buen día, quiero hacer una oferta sobre el inmueble ' . $sheet['title'],
                        ],
                    ];
                    foreach ($ctas as [$question, $action, $message]) :
                        $ctaUrl = TechnicalSheetData::whatsAppWithMessage($agent, $message);
                        ?>
                        <td class="cta-cell">
                            <div class="stat-label"><?php echo esc_html($question); ?></div>
                            <?php if ($ctaUrl) : ?>
                                <a class="cta-btn" href="<?php echo esc_url($ctaUrl); ?>" style="border-color:<?php echo esc_attr($colors['button']); ?>;color:<?php echo esc_attr($colors['button_text']); ?>">
                                    <?php echo $iconTag('arrow', $colors['button']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <?php echo esc_html($action); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($show['legal']) : ?>
        <?php /* Sin `no-break`: es texto corrido y partirlo es normal. Con la
                tarjeta indivisible se iba entera a una página nueva, y una
                página en blanco por tres párrafos de letra pequeña no sale a
                cuenta. El original lo comenta igual —«puede partir»— aunque
                se dejó la clase puesta. */ ?>
        <div class="section-card">
            <?php /* Solo la fecha: el nombre del asesor, su teléfono, su correo,
                    la inmobiliaria y la URL del inmueble ya salen en la cabecera
                    y el pie de cada página, así que repetirlos aquí era relleno.
                    La hora sí es dato nuevo —dice a qué precios y disponibilidad
                    corresponde la ficha—. */ ?>
            <p class="text-center margin-0 font-11">
                <?php echo $iconTag('clock', $primary); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php
                printf(
                    /* translators: %s: fecha y hora de generación. */
                    esc_html__('Ficha técnica generada el %s', 'homlity-real-estate'),
                    esc_html(date_i18n('Y-m-d H:i'))
                );
                ?>
            </p>
            <p class="legal-note">
                <?php
                printf(
                    esc_html__('Propiedad sujeta a disponibilidad. Precio sujeto a cambios sin previo aviso. El envío de esta ficha no compromete a las partes a la suscripción de ningún documento legal. La información y medidas son aproximadas y deberán ratificarse con la documentación pertinente.', 'homlity-real-estate')
                );
                ?>
            </p>
        </div>
    <?php endif; ?>
</main>
