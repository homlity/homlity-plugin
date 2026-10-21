<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Detecta problemas del inventario sin modificar los datos del CRM.
 *
 * @since 2.9.16
 */
class PropertyDuplicateDetector
{
    public const TRANSIENT = 'homlity_dup_groups';

    /**
     * Un grupo solo propone consolidación si todos sus pares coinciden físicamente.
     *
     * @return array<string,array{ids: int[], classification: string, pairs: array}>
     * @since 2.9.16
     */
    public function findGroups(): array
    {
        $cached = get_transient(self::TRANSIENT);
        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_excerpt, post_content FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY ID",
            PropertyPostType::POST_TYPE
        ));
        $buckets = [];
        foreach ($rows as $row) {
            $text = trim((string) $row->post_excerpt) !== '' ? $row->post_excerpt : $row->post_content;
            $text = self::normalize((string) $text);
            if ($text === '') {
                continue;
            }
            $text = function_exists('mb_substr') ? mb_substr($text, 0, 500, 'UTF-8') : implode('', array_slice(preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY), 0, 500));
            $buckets[md5($text)][] = (int) $row->ID;
        }

        $buckets = array_filter($buckets, static fn(array $ids): bool => count($ids) > 1);
        if ($buckets !== [] && function_exists('update_meta_cache')) {
            update_meta_cache('post', array_merge(...array_values($buckets)));
        }
        $groups = [];
        foreach ($buckets as $fingerprint => $ids) {
            $pairs = [];
            $sameListing = true;
            foreach ($ids as $i => $id) {
                foreach (array_slice($ids, $i + 1) as $other) {
                    $same = $this->samePhysical($id, $other);
                    $pairs[] = ['ids' => [$id, $other], 'same_physical' => $same];
                    $sameListing = $sameListing && $same;
                }
            }
            $groups[$fingerprint] = ['ids' => $ids, 'classification' => $sameListing ? 'same_listing' : 'copied_text', 'pairs' => $pairs];
        }
        set_transient(self::TRANSIENT, $groups, 12 * HOUR_IN_SECONDS);
        return $groups;
    }

    /**
     * @return array|null Grupo de la ficha, si tiene descripción duplicada.
     *
     * @since 2.9.16
     */
    public function groupFor(int $postId): ?array
    {
        foreach ($this->findGroups() as $group) {
            if (in_array($postId, $group['ids'], true)) {
                return $group;
            }
        }
        return null;
    }

    /**
     * Devuelve el tipo mencionado en conflicto, sin adivinar el tipo correcto.
     *
     * @since 2.9.16
     */
    public function typeMismatch(int $postId): ?string
    {
        $post = get_post($postId);
        $terms = get_the_terms($postId, PropertyTaxonomies::TAXONOMY_TYPE);
        if (!$post instanceof \WP_Post || !$terms || is_wp_error($terms)) {
            return null;
        }
        // El título suele generarse con el tipo y ocultaría contradicciones del texto del CRM.
        $text = self::normalize($post->post_excerpt . ' ' . $post->post_content);
        if ($text === '') {
            return null;
        }
        $keywords = apply_filters('homlity_property_type_keywords', [
            'casa' => ['casa', 'casas'],
            'apartamento' => ['apartamento', 'apartamentos', 'apto', 'aptos'],
            'apartaestudio' => ['apartaestudio', 'apartaestudios', 'apartamento estudio'],
            'oficina' => ['oficina', 'oficinas'],
            'local' => ['local', 'locales', 'local comercial'],
            'bodega' => ['bodega', 'bodegas'],
            'lote' => ['lote', 'lotes', 'terreno', 'terrenos'],
            'finca' => ['finca', 'fincas'],
            'consultorio' => ['consultorio', 'consultorios'],
        ]);
        $assigned = [];
        $mentioned = [];
        foreach ($keywords as $type => $words) {
            foreach ((array) $words as $word) {
                $pattern = '~(?<![\p{L}\p{N}])' . preg_quote(self::normalize((string) $word), '~') . '(?![\p{L}\p{N}])~u';
                foreach ($terms as $term) {
                    if (preg_match($pattern, self::normalize($term->name . ' ' . str_replace('-', ' ', $term->slug)))) {
                        $assigned[$type] = true;
                    }
                }
                if (preg_match($pattern, $text)) {
                    $mentioned[$type] = true;
                }
            }
        }
        if ($assigned === [] || array_intersect_key($assigned, $mentioned) !== []) {
            return null;
        }
        return $mentioned !== [] ? (string) array_key_first($mentioned) : null;
    }

    private static function normalize(string $text): string
    {
        $text = html_entity_decode(wp_strip_all_tags(strip_shortcodes($text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = remove_accents($text);
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function samePhysical(int $first, int $second): bool
    {
        foreach (['area', 'bedrooms', 'bathrooms'] as $field) {
            $a = get_post_meta($first, '_property_' . $field, true);
            $b = get_post_meta($second, '_property_' . $field, true);
            // La ausencia de datos no demuestra que sea el mismo inmueble.
            if (!is_numeric($a) || !is_numeric($b) || (float) $a !== (float) $b) {
                return false;
            }
        }
        $a = self::normalize((string) get_post_meta($first, '_property_address', true));
        $b = self::normalize((string) get_post_meta($second, '_property_address', true));
        if ($a !== '' && $b !== '') {
            // Compartir edificio o coordenadas no identifica el apartamento.
            return $a === $b;
        }
        $codeA = trim((string) get_post_meta($first, '_property_code', true));
        $codeB = trim((string) get_post_meta($second, '_property_code', true));
        if ($codeA !== '' && $codeB !== '' && $codeA !== $codeB) {
            // Sin una dirección coincidente, dos códigos distintos impiden inferir equivalencia.
            return false;
        }
        $coords = [];
        foreach ([$first, $second] as $id) {
            $lat = get_post_meta($id, '_property_latitude', true);
            $lng = get_post_meta($id, '_property_longitude', true);
            if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
                return false;
            }
            $coords[] = [deg2rad((float) $lat), deg2rad((float) $lng)];
        }
        [[$latA, $lngA], [$latB, $lngB]] = $coords;
        $h = sin(($latB - $latA) / 2) ** 2 + cos($latA) * cos($latB) * sin(($lngB - $lngA) / 2) ** 2;
        return 6371000 * 2 * asin(sqrt(min(1.0, $h))) < 30;
    }
}
