<?php
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

namespace Homlity\PluginInmobiliario\Search;

use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!defined('ABSPATH')) {
    exit;
}

/** Builds and persists the denormalized copy used by the future index query. */
final class PropertyIndexer
{
    /** @return array<string,mixed>|null */
    public function buildRow(int $postId): ?array
    {
        $post = get_post($postId);
        if (!$post instanceof \WP_Post || $post->post_type !== PropertyPostType::POST_TYPE) {
            return null;
        }

        $meta = static function (string $key) use ($postId): string {
            return trim((string) get_post_meta($postId, $key, true));
        };
        $priceSale = $this->numberOrNull($meta('_property_price_sale'));
        $priceRent = $this->numberOrNull($meta('_property_price_rent'));
        $displayPrice = $priceSale !== null && $priceSale > 0 ? $priceSale : $priceRent;
        $statusValues = get_post_meta($postId, '_property_status', false);
        $availableValues = get_post_meta($postId, '_property_available', false);
        $statusVisible = !metadata_exists('post', $postId, '_property_status')
            || $this->containsMetaValue($statusValues, ['active']);
        $availableVisible = !metadata_exists('post', $postId, '_property_available')
            || $this->containsMetaValue($availableValues, ['1', 'true', 'yes', 'active']);

        $code = substr($meta('_property_code'), 0, 100);
        $syncId = substr($meta('_homlity_sync_id'), 0, 100);
        $searchValues = [(string) $post->post_title, (string) $post->post_excerpt, $code, $syncId];
        $indexLocationTerms = (bool) apply_filters('homlity/search/index_location_terms', true, $postId);
        foreach ([
            PropertyTaxonomies::TAXONOMY_FEATURE,
            PropertyTaxonomies::TAXONOMY_CITY,
            PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
        ] as $taxonomy) {
            if (!$indexLocationTerms && in_array($taxonomy, [PropertyTaxonomies::TAXONOMY_CITY, PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD], true)) {
                continue;
            }
            $terms = get_the_terms($postId, $taxonomy);
            if (!is_wp_error($terms) && is_array($terms)) {
                foreach ($terms as $term) {
                    $searchValues[] = (string) ($term->name ?? '');
                }
            }
        }

        $postDate = (string) ($post->post_date ?? '');
        if ($postDate === '') {
            $postDate = gmdate('Y-m-d H:i:s');
        }

        return [
            'post_id' => $postId,
            'is_visible' => $post->post_status === 'publish' && $statusVisible && $availableVisible ? 1 : 0,
            'price_sale' => $priceSale,
            'price_rent' => $priceRent,
            'display_price' => $displayPrice,
            'bedrooms' => $this->integerOrNull($meta('_property_bedrooms')),
            'bathrooms' => $this->integerOrNull($meta('_property_bathrooms')),
            'parking' => $this->integerOrNull($meta('_property_parking')),
            'area' => $this->numberOrNull($meta('_property_area')),
            'latitude' => $this->coordinateOrNull($meta('_property_latitude'), 90.0),
            'longitude' => $this->coordinateOrNull($meta('_property_longitude'), 180.0),
            'featured' => $this->containsExactMetaValue(get_post_meta($postId, '_property_featured', false), '1') ? 1 : 0,
            'agent_id' => $this->integerOrNull($meta('_property_agent_id')),
            'code' => $code !== '' ? $code : null,
            'sync_id' => $syncId !== '' ? $syncId : null,
            'search_text' => $this->normalizeText(implode(' ', $searchValues)),
            'post_date' => $postDate,
            'indexed_at' => gmdate('Y-m-d H:i:s'),
        ];
    }

    /** @param int[] $ids */
    public function indexMany(array $ids): int
    {
        global $wpdb;
        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        if ($ids !== []) {
            if (function_exists('_prime_post_caches')) _prime_post_caches($ids, false, true);
            if (function_exists('update_postmeta_cache')) update_postmeta_cache($ids);
            if (function_exists('update_object_term_cache')) update_object_term_cache($ids, PropertyPostType::POST_TYPE);
        }
        $rows = [];
        foreach ($ids as $postId) {
            $row = $this->buildRow($postId);
            if ($row !== null) {
                $rows[] = $row;
            }
        }
        if ($rows === []) {
            return 0;
        }

        $columns = ['post_id', 'is_visible', 'price_sale', 'price_rent', 'display_price', 'bedrooms', 'bathrooms', 'parking', 'area', 'latitude', 'longitude', 'featured', 'agent_id', 'code', 'sync_id', 'search_text', 'post_date', 'indexed_at'];
        $valuesSql = [];
        $args = [];
        foreach ($rows as $row) {
            $placeholders = [];
            foreach ($columns as $column) {
                $value = $row[$column];
                if ($value === null) {
                    $placeholders[] = 'NULL';
                    continue;
                }
                $placeholders[] = in_array($column, ['post_id', 'is_visible', 'featured'], true) ? '%d' : '%s';
                $args[] = $value;
            }
            $valuesSql[] = '(' . implode(', ', $placeholders) . ')';
        }

        $updates = implode(', ', array_map(static fn(string $column): string => "{$column} = VALUES({$column})", array_slice($columns, 1)));
        $sql = 'INSERT INTO ' . PropertyIndexSchema::tableName()
            . ' (' . implode(', ', $columns) . ') VALUES ' . implode(', ', $valuesSql)
            . ' ON DUPLICATE KEY UPDATE ' . $updates;
        $wpdb->query($wpdb->prepare($sql, ...$args));

        return count($rows);
    }

    /** @param int[] $ids */
    public function delete(array $ids): int
    {
        global $wpdb;
        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        if ($ids === []) {
            return 0;
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
        return (int) $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . PropertyIndexSchema::tableName() . " WHERE post_id IN ({$placeholders})",
            ...$ids
        ));
    }

    private function normalizeText(string $value): string
    {
        $value = strtolower(remove_accents($value));
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function numberOrNull(string $value): ?float
    {
        $value = trim((string) preg_replace('/[^0-9,.-]/', '', $value));
        if ($value === '') {
            return null;
        }
        if (!str_contains($value, ',') && substr_count($value, '.') === 1 && preg_match('/\.\d{3}$/', $value)) {
            $value = str_replace('.', '', $value);
        }
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = strrpos($value, ',') > strrpos($value, '.')
                ? str_replace('.', '', str_replace(',', '.', $value))
                : str_replace(',', '', $value);
        } elseif (substr_count($value, ',') === 1) {
            $value = str_replace(',', '.', $value);
        } elseif (substr_count($value, ',') > 1 || substr_count($value, '.') > 1) {
            $value = str_replace([',', '.'], '', $value);
        }
        return is_numeric($value) ? (float) $value : null;
    }

    private function coordinateOrNull(string $value, float $limit): ?float
    {
        $value = trim((string) preg_replace('/\s+/', '', $value));
        if ($value === '' || !preg_match('/^-?\d+(?:[.,]\d+)?$/', $value)) {
            return null;
        }
        $coordinate = (float) str_replace(',', '.', $value);
        return abs($coordinate) <= $limit ? $coordinate : null;
    }

    /** @param mixed $values */
    private function containsMetaValue($values, array $allowed): bool
    {
        foreach ($this->flattenMetaValues($values) as $value) {
            if (in_array(strtolower(trim((string) $value)), $allowed, true)) return true;
        }
        return false;
    }

    /** @param mixed $values */
    private function containsExactMetaValue($values, string $expected): bool
    {
        foreach ($this->flattenMetaValues($values) as $value) {
            if ((string) $value === $expected) return true;
        }
        return false;
    }

    /** @param mixed $values */
    private function flattenMetaValues($values): array
    {
        $result = [];
        foreach ((array) $values as $value) {
            if (is_array($value)) $result = array_merge($result, $this->flattenMetaValues($value));
            else $result[] = $value;
        }
        return $result;
    }

    private function integerOrNull(string $value): ?int
    {
        $number = $this->numberOrNull($value);
        return $number === null ? null : max(0, (int) round($number));
    }
}
