<?php

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Assets of the property photo gallery: Splide for the carousels and PhotoSwipe for the lightbox.
 *
 * Every widget and template that renders templates/parts/property-gallery.php calls enqueue(),
 * so the gallery always has the same libraries and behaviour wherever it is printed.
 */
final class MediaGalleryAssets
{
    private const SPLIDE_VERSION = '4.1.4';
    private const PHOTOSWIPE_VERSION = '5.4.4';

    public static function enqueue(): void
    {
        $vendor = HOMLITY_PLUGIN_URL . 'assets/vendor/';

        wp_enqueue_style('homlity-real-estate-splide', $vendor . 'splide/splide-core.min.css', [], self::SPLIDE_VERSION);
        wp_enqueue_style('homlity-real-estate-photoswipe', $vendor . 'photoswipe/photoswipe.css', [], self::PHOTOSWIPE_VERSION);

        wp_enqueue_script('homlity-real-estate-splide', $vendor . 'splide/splide.min.js', [], self::SPLIDE_VERSION, true);
        wp_enqueue_script('homlity-real-estate-photoswipe', $vendor . 'photoswipe/photoswipe.umd.min.js', [], self::PHOTOSWIPE_VERSION, true);
        wp_enqueue_script(
            'homlity-real-estate-photoswipe-lightbox',
            $vendor . 'photoswipe/photoswipe-lightbox.umd.min.js',
            ['homlity-real-estate-photoswipe'],
            self::PHOTOSWIPE_VERSION,
            true
        );
        wp_enqueue_script(
            'homlity-real-estate-media-gallery',
            HOMLITY_PLUGIN_URL . 'assets/js/property-media-gallery.js',
            ['homlity-real-estate-splide', 'homlity-real-estate-photoswipe-lightbox'],
            HOMLITY_PLUGIN_VERSION,
            true
        );
        wp_localize_script('homlity-real-estate-media-gallery', 'homlityMediaGalleryI18n', [
            'prev' => __('Foto anterior', 'homlity-real-estate'),
            'next' => __('Foto siguiente', 'homlity-real-estate'),
            'first' => __('Ir a la primera foto', 'homlity-real-estate'),
            'last' => __('Ir a la última foto', 'homlity-real-estate'),
            'slideX' => __('Ir a la foto %s', 'homlity-real-estate'),
            'pageX' => __('Ir a la página %s', 'homlity-real-estate'),
            'play' => __('Reproducir', 'homlity-real-estate'),
            'pause' => __('Pausar', 'homlity-real-estate'),
            'carousel' => __('carrusel', 'homlity-real-estate'),
            'slideLabel' => __('%s de %s', 'homlity-real-estate'),
            'close' => __('Cerrar', 'homlity-real-estate'),
            'zoom' => __('Acercar', 'homlity-real-estate'),
            'error' => __('No se pudo cargar la imagen.', 'homlity-real-estate'),
        ]);

        // Keeps the tab switching (Fotos, Video, 360°) working in the same widget.
        wp_enqueue_script(
            'homlity-real-estate-gallery-tabs',
            HOMLITY_PLUGIN_URL . 'assets/js/property-gallery-tabs.js',
            ['homlity-real-estate-media-gallery'],
            HOMLITY_PLUGIN_VERSION,
            true
        );
    }
}
