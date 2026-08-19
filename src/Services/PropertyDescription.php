<?php
/**
 * El texto descriptivo de un inmueble.
 *
 * Existe para no volver a pasarlo por `the_content`. Ese filtro no significa
 * «dale formato a este texto»: es el punto por el que media docena de plugins
 * inyectan cosas en el cuerpo de una entrada. Elementor, en concreto, lo
 * engancha y devuelve el documento entero del constructor sin mirar siquiera
 * lo que recibe, así que la descripción acababa siendo la página completa
 * —eso mismo salía dentro de la tarjeta «Descripción del inmueble» del PDF—.
 * Botones de compartir, avisos de cookies y bloques de relacionados llegaban
 * por la misma puerta.
 */

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class PropertyDescription
{
    /**
     * La descripción, saneada y lista para pintar.
     *
     * El saneado es el mínimo que necesita un párrafo: se quitan los
     * shortcodes —algunos sacan la página entera igual que `the_content`, y en
     * un PDF no hay nada que puedan ejecutar útilmente— y se respetan los
     * saltos de línea. Si el inmueble está montado con un constructor, el
     * contenido suele estar vacío y se cae al extracto.
     *
     * Quien necesite el comportamiento anterior tiene el filtro.
     */
    public static function text(int $postId): string
    {
        if ($postId <= 0) {
            return '';
        }

        $raw = (string) get_post_field('post_content', $postId);
        if (trim(wp_strip_all_tags($raw)) === '') {
            $raw = (string) get_post_field('post_excerpt', $postId);
        }

        $text = wpautop(strip_shortcodes($raw));

        /**
         * La descripción de un inmueble, tal como la pintan la ficha técnica y
         * los widgets de contenido y resumen.
         *
         * @param string $text   Descripción ya saneada.
         * @param int    $postId Inmueble.
         */
        return (string) apply_filters('homlity_property_description', $text, $postId);
    }
}
