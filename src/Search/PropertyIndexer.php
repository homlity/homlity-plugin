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
        $status = strtolower($meta('_property_status'));
        $available = strtolower($meta('_property_available'));
        $statusVisible = $status === '' || $status === 'active';
        $availableVisible = $available === '' || in_array($available, ['1', 'true', 'yes', 'active'], true);

        $code = substr($meta('_property_code'), 0, 100);
        $syncId = substr($meta('_homlity_sync_id'), 0, 100);
        $searchValues = [(string) $post->post_title, (string) $post->post_excerpt, $code, $syncId];
        foreach ([
            PropertyTaxonomies::TAXONOMY_FEATURE,
            PropertyTaxonomies::TAXONOMY_CITY,
            PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
        ] as $taxonomy) {
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
            'latitude' => $this->numberOrNull($meta('_property_latitude')),
            'longitude' => $this->numberOrNull($meta('_property_longitude')),
            'featured' => in_array(strtolower($meta('_property_featured')), ['1', 'true', 'yes', 'active'], true) ? 1 : 0,
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
        $rows = [];
        foreach (array_values(array_unique(array_filter(array_map('absint', $ids)))) as $postId) {
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

    private function integerOrNull(string $value): ?int
    {
        $number = $this->numberOrNull($value);
        return $number === null ? null : max(0, (int) round($number));
    }
}
