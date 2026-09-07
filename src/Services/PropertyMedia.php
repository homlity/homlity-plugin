<?php
/**
 * Las fotos de un inmueble, vengan de donde vengan.
 *
 * Un inmueble dado de alta a mano guarda ids de la biblioteca de medios; uno
 * sincronizado desde un CRM guarda URLs externas —a veces como array, a veces
 * como JSON, a veces separadas por comas—. Cada consumidor resolvía su propia
 * variante, así que el mismo inmueble salía con foto en la galería y sin foto
 * en el Open Graph y en el JSON-LD. Esto es el único sitio que sabe leerlas.
 */

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class PropertyMedia
{
    /**
     * Todas las fotos del inmueble, sin repetir y en orden de importancia:
     * la destacada de WordPress, la portada que marcó el CRM y luego la
     * galería.
     *
     * El id va a 0 cuando la foto no está en la biblioteca de medios. Quien
     * pinte la imagen debe contar con ello: con id se pueden generar tamaños
     * intermedios y srcset, con una URL externa no.
     *
     * @return array<int,array{id:int, url:string}>
     */
    public static function images(int $postId): array
    {
        if ($postId <= 0) {
            return [];
        }

        $images = [];

        $thumbId = (int) get_post_thumbnail_id($postId);
        if ($thumbId > 0) {
            $url = wp_get_attachment_image_url($thumbId, 'full');
            if (is_string($url) && $url !== '') {
                $images[$url] = ['id' => $thumbId, 'url' => $url];
            }
        }

        $cover = trim((string) get_post_meta($postId, '_property_featured_image_url', true));
        if ($cover !== '' && filter_var($cover, FILTER_VALIDATE_URL)) {
            $cover = esc_url_raw($cover);
            if (!isset($images[$cover])) {
                $images[$cover] = ['id' => 0, 'url' => $cover];
            }
        }

        foreach (self::galleryImages($postId) as $image) {
            if (!isset($images[$image['url']])) {
                $images[$image['url']] = $image;
            }
        }

        return array_values($images);
    }

    /** @return string[] */
    public static function imageUrls(int $postId): array
    {
        return array_column(self::images($postId), 'url');
    }

    /**
     * La foto que representa al inmueble: portada de la ficha, imagen al
     * compartir, `image` del JSON-LD.
     *
     * @return array{id:int, url:string}
     */
    public static function mainImage(int $postId): array
    {
        return self::images($postId)[0] ?? ['id' => 0, 'url' => ''];
    }

    public static function mainImageUrl(int $postId): string
    {
        return self::mainImage($postId)['url'];
    }

    /**
     * Sólo la galería, en el orden en que se guardó.
     *
     * @return array<int,array{id:int, url:string}>
     */
    public static function galleryImages(int $postId): array
    {
        if ($postId <= 0) {
            return [];
        }

        $metaKeys = (new PropertyPostType())->metaKeys();
        $raw = get_post_meta($postId, $metaKeys['gallery'], true);

        $images = [];
        foreach (self::galleryItems($raw) as $item) {
            // Un id hay que resolverlo; una URL ya lo está. Pasar el id por
            // esc_url_raw() lo convertiría en "http://123".
            if (ctype_digit($item)) {
                $attachmentId = (int) $item;
                $url = $attachmentId > 0 ? wp_get_attachment_image_url($attachmentId, 'full') : false;
                if (is_string($url) && $url !== '') {
                    $images[$url] = ['id' => $attachmentId, 'url' => $url];
                }
                continue;
            }

            if (filter_var($item, FILTER_VALIDATE_URL)) {
                $url = esc_url_raw($item);
                if ($url !== '' && !isset($images[$url])) {
                    $images[$url] = ['id' => 0, 'url' => $url];
                }
            }
        }

        return array_values($images);
    }

    /** @return string[] */
    public static function galleryUrls(int $postId): array
    {
        return array_column(self::galleryImages($postId), 'url');
    }

    /**
     * Aplana el metadato de galería a una lista de cadenas —ids o URLs— sin
     * decidir todavía qué es cada una.
     *
     * @param mixed $raw
     * @return string[]
     */
    private static function galleryItems($raw): array
    {
        if ($raw === '' || $raw === null || $raw === false) {
            return [];
        }

        if (is_string($raw)) {
            $trimmed = trim($raw);
            $decoded = ($trimmed !== '' && ($trimmed[0] === '[' || $trimmed[0] === '{'))
                ? json_decode($trimmed, true)
                : null;
            $raw = is_array($decoded) ? $decoded : preg_split('/[\r\n,;|]+/', $trimmed);
        }

        if (!is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $item) {
            if (is_array($item)) {
                $item = $item['url'] ?? ($item['src'] ?? '');
            }
            if (!is_scalar($item)) {
                continue;
            }
            $item = trim((string) $item);
            if ($item !== '') {
                $items[] = $item;
            }
        }

        return $items;
    }
}
