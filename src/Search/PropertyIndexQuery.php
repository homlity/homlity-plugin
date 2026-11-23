<?php

namespace Homlity\PluginInmobiliario\Search;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Services\PropertyPostType;

if (!defined('ABSPATH')) {
    exit;
}

/** Translates the plugin's named filters to the denormalized search index. */
final class PropertyIndexQuery implements ServiceInterface
{
    /** @var array<string,mixed>|null */
    private ?array $comparisonReport = null;

    public function register(): void
    {
        add_action('pre_get_posts', [$this, 'prepareQuery'], 20);
        add_filter('posts_clauses', [$this, 'applyClauses'], 30, 2);
        add_action('wp', [$this, 'compareMainQuery'], 20);
        add_action('wp_footer', [$this, 'renderComparisonNotice']);
    }

    public static function isActive(\WP_Query $query): bool
    {
        if ($query->get('post_type') !== PropertyPostType::POST_TYPE) {
            return false;
        }
        $enabled = PropertyIndexAdminPage::useIndex();
        return (bool) apply_filters('homlity/search/use_index', $enabled, $query);
    }

    public function prepareQuery(\WP_Query $query): void
    {
        if (!self::isActive($query) || !is_array($query->get('homlity_index_filters'))) {
            return;
        }
        $metaQuery = (array) $query->get('meta_query');
        $query->set('homlity_compare_meta_query', $metaQuery);
        foreach (array_keys($metaQuery) as $key) {
            if (str_starts_with((string) $key, 'homlity_')) {
                unset($metaQuery[$key]);
            }
        }
        $query->set('meta_query', $metaQuery);
    }

    public function applyClauses(array $clauses, \WP_Query $query): array
    {
        $filters = $query->get('homlity_index_filters');
        if (!self::isActive($query) || !is_array($filters)) {
            return $clauses;
        }

        global $wpdb;
        $table = PropertyIndexSchema::tableName();
        if (strpos((string) ($clauses['join'] ?? ''), 'homlity_property_index') === false) {
            $clauses['join'] .= " INNER JOIN {$table} AS hpi ON hpi.post_id = {$wpdb->posts}.ID";
        }

        $where = [$wpdb->prepare('hpi.is_visible = %d', 1)];
        $priceColumn = ($filters['price_column'] ?? '') === '_property_price_rent' ? 'price_rent' : 'price_sale';
        if (!empty($filters['price_min']) && !empty($filters['price_max'])) {
            $where[] = $wpdb->prepare("hpi.{$priceColumn} BETWEEN %d AND %d", (int) $filters['price_min'], (int) $filters['price_max']);
        } elseif (!empty($filters['price_min'])) {
            $where[] = $wpdb->prepare("hpi.{$priceColumn} >= %d", (int) $filters['price_min']);
        } elseif (!empty($filters['price_max'])) {
            $where[] = $wpdb->prepare("hpi.{$priceColumn} <= %d", (int) $filters['price_max']);
        }
        foreach (['bedrooms', 'bathrooms', 'parking'] as $field) {
            if (!empty($filters[$field])) {
                $where[] = $wpdb->prepare("hpi.{$field} >= %d", (int) $filters[$field]);
            }
        }
        if (!empty($filters['area_min']) && !empty($filters['area_max'])) {
            $where[] = $wpdb->prepare('hpi.area BETWEEN %d AND %d', (int) $filters['area_min'], (int) $filters['area_max']);
        } elseif (!empty($filters['area_min'])) {
            $where[] = $wpdb->prepare('hpi.area >= %d', (int) $filters['area_min']);
        } elseif (!empty($filters['area_max'])) {
            $where[] = $wpdb->prepare('hpi.area <= %d', (int) $filters['area_max']);
        }
        if (!empty($filters['agent_id'])) {
            $where[] = $wpdb->prepare('hpi.agent_id = %d', (int) $filters['agent_id']);
        }
        if (!empty($filters['featured'])) {
            $where[] = 'hpi.featured = 1';
        }
        foreach (['lat' => 'latitude', 'lng' => 'longitude'] as $key => $column) {
            if (isset($filters[$key]) && is_array($filters[$key]) && count($filters[$key]) === 2) {
                $where[] = $wpdb->prepare("hpi.{$column} BETWEEN %f AND %f", (float) $filters[$key][0], (float) $filters[$key][1]);
            }
        }

        $rank = '';
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $keyword = strtolower(remove_accents($keyword));
            $likeAny = '%' . $wpdb->esc_like($keyword) . '%';
            $likePrefix = $wpdb->esc_like($keyword) . '%';
            $search = [
                $wpdb->prepare('hpi.code LIKE %s', $likeAny),
                $wpdb->prepare('hpi.sync_id LIKE %s', $likeAny),
                $wpdb->prepare("{$wpdb->posts}.post_title LIKE %s", $likeAny),
                $wpdb->prepare("{$wpdb->posts}.post_excerpt LIKE %s", $likeAny),
            ];
            $rankParts = [
                'WHEN hpi.code = ' . $wpdb->prepare('%s', $keyword) . ' THEN 0',
                'WHEN hpi.sync_id = ' . $wpdb->prepare('%s', $keyword) . ' THEN 0',
                'WHEN hpi.code LIKE ' . $wpdb->prepare('%s', $likePrefix) . ' THEN 1',
                'WHEN hpi.sync_id LIKE ' . $wpdb->prepare('%s', $likePrefix) . ' THEN 1',
                'WHEN ' . $wpdb->prepare("{$wpdb->posts}.post_title LIKE %s", $likePrefix) . ' THEN 2',
                'WHEN ' . $wpdb->prepare("{$wpdb->posts}.post_title LIKE %s", $likeAny) . ' THEN 3',
                'WHEN ' . $wpdb->prepare("{$wpdb->posts}.post_excerpt LIKE %s", $likeAny) . ' THEN 5',
            ];
            if (strlen($keyword) >= 3) {
                $search[] = $wpdb->prepare('MATCH(hpi.search_text) AGAINST(%s IN BOOLEAN MODE)', $keyword);
                $rankParts[] = 'WHEN MATCH(hpi.search_text) AGAINST(' . $wpdb->prepare('%s', $keyword) . ' IN BOOLEAN MODE) THEN 4';
            } else {
                $search[] = $wpdb->prepare('hpi.search_text LIKE %s', $likeAny);
                $rankParts[] = 'WHEN hpi.search_text LIKE ' . $wpdb->prepare('%s', $likeAny) . ' THEN 4';
            }
            $where[] = '(' . implode(' OR ', $search) . ')';
            $rank = 'MIN(CASE ' . implode(' ', $rankParts) . ' ELSE 9 END), ';
        }

        $clauses['where'] .= ' AND ' . implode(' AND ', $where);
        $orderby = (string) ($filters['orderby'] ?? 'date');
        $direction = strtoupper((string) ($filters['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        if ($orderby === 'price_asc' || $orderby === 'price_desc') {
            $direction = $orderby === 'price_asc' ? 'ASC' : 'DESC';
            $clauses['orderby'] = "{$rank}IF(hpi.display_price IS NULL OR hpi.display_price = 0, 1, 0), hpi.display_price {$direction}, {$wpdb->posts}.ID DESC";
        } elseif ($orderby === 'date') {
            $clauses['orderby'] = "{$rank}hpi.post_date DESC, {$wpdb->posts}.ID DESC";
        } elseif ($rank !== '') {
            $existing = trim((string) ($clauses['orderby'] ?? ''));
            $clauses['orderby'] = $rank . ($existing !== '' ? $existing : "{$wpdb->posts}.ID DESC");
        }
        return $clauses;
    }

    public function compareMainQuery(): void
    {
        if (empty($_GET['homlity_index_compare']) || !current_user_can('manage_options')) {
            return;
        }
        global $wp_query;
        if (!$wp_query instanceof \WP_Query || $wp_query->get('post_type') !== PropertyPostType::POST_TYPE) {
            return;
        }
        if (!PropertyIndexSchema::isReady()) {
            $this->comparisonReport = ['error' => __('El índice no está listo.', 'homlity-real-estate')];
            return;
        }
        if (function_exists('nocache_headers')) nocache_headers();
        do_action('litespeed_control_set_nocache');

        $args = $wp_query->query_vars;
        if (is_array($wp_query->get('homlity_compare_meta_query'))) {
            $args['meta_query'] = $wp_query->get('homlity_compare_meta_query');
        }
        $args['posts_per_page'] = (int) ($args['posts_per_page'] ?? 12);
        $args['paged'] = (int) ($args['paged'] ?? 1);

        $started = microtime(true);
        add_filter('homlity/search/use_index', '__return_true', 9999);
        $indexQuery = new \WP_Query($args);
        remove_filter('homlity/search/use_index', '__return_true', 9999);
        $indexTime = microtime(true) - $started;

        add_filter('homlity/search/use_index', '__return_false', 9999);
        $started = microtime(true);
        $postmetaQuery = new \WP_Query($args);
        $postmetaTime = microtime(true) - $started;
        remove_filter('homlity/search/use_index', '__return_false', 9999);

        $indexIds = array_map('intval', wp_list_pluck($indexQuery->posts, 'ID'));
        $postmetaIds = array_map('intval', wp_list_pluck($postmetaQuery->posts, 'ID'));
        $different = array_values(array_unique(array_merge(array_diff($indexIds, $postmetaIds), array_diff($postmetaIds, $indexIds))));
        $classified = $this->classifyDifferences($different);
        $this->comparisonReport = [
            'index_time_ms' => round($indexTime * 1000, 2),
            'postmeta_time_ms' => round($postmetaTime * 1000, 2),
            'order_equal' => $indexIds === $postmetaIds,
            'found_equal' => (int) $indexQuery->found_posts === (int) $postmetaQuery->found_posts,
            'expected' => count($classified['expected']),
            'unexplained' => count($classified['unexplained']),
            'details' => array_merge($classified['expected'], $classified['unexplained']),
        ];
        error_log('[homlity index compare] ' . wp_json_encode($this->comparisonReport));
    }

    /** @param int[] $ids @return array{expected:array<int,mixed>,unexplained:array<int,mixed>} */
    private function classifyDifferences(array $ids): array
    {
        global $wpdb;
        $result = ['expected' => [], 'unexplained' => []];
        foreach ($ids as $id) {
            $rawSale = (string) get_post_meta($id, '_property_price_sale', true);
            $rawRent = (string) get_post_meta($id, '_property_price_rent', true);
            $raw = $rawSale !== '' ? $rawSale : $rawRent;
            $row = $wpdb->get_row($wpdb->prepare('SELECT price_sale, price_rent FROM ' . PropertyIndexSchema::tableName() . ' WHERE post_id = %d', $id), ARRAY_A);
            $indexed = $rawSale !== '' ? ($row['price_sale'] ?? null) : ($row['price_rent'] ?? null);
            $current = is_numeric($raw) ? (float) $raw : (float) preg_replace('/[^0-9.-]/', '', $raw);
            $isCopThousands = preg_match('/^-?\d{1,3}(?:\.\d{3})+$/', trim($raw)) === 1;
            $detail = ['post_id' => $id, 'meta' => $raw, 'postmeta' => $current, 'index' => $indexed];
            if ($isCopThousands && $indexed !== null && (float) $indexed !== $current) {
                $result['expected'][] = $detail;
            } else {
                $result['unexplained'][] = $detail;
            }
        }
        return $result;
    }

    public function renderComparisonNotice(): void
    {
        if (!is_array($this->comparisonReport) || !current_user_can('manage_options')) return;
        if (!empty($this->comparisonReport['error'])) {
            echo '<div class="notice notice-warning"><p>' . esc_html((string) $this->comparisonReport['error']) . '</p></div>';
            return;
        }
        $summary = sprintf(
            __('Comparación índice: %d diferencias esperadas por normalización, %d no explicadas. Orden igual: %s; totales iguales: %s. Índice %.2f ms / postmeta %.2f ms.', 'homlity-real-estate'),
            (int) $this->comparisonReport['expected'],
            (int) $this->comparisonReport['unexplained'],
            $this->comparisonReport['order_equal'] ? 'sí' : 'no',
            $this->comparisonReport['found_equal'] ? 'sí' : 'no',
            (float) $this->comparisonReport['index_time_ms'],
            (float) $this->comparisonReport['postmeta_time_ms']
        );
        $details = '';
        foreach ((array) ($this->comparisonReport['details'] ?? []) as $detail) {
            $details .= '<li>post_id ' . esc_html((string) ($detail['post_id'] ?? ''))
                . ': meta «' . esc_html((string) ($detail['meta'] ?? ''))
                . '»; postmeta=' . esc_html((string) ($detail['postmeta'] ?? ''))
                . '; índice=' . esc_html((string) ($detail['index'] ?? '')) . '</li>';
        }
        echo '<div class="notice notice-info"><p>' . esc_html($summary) . '</p>'
            . ($details !== '' ? '<details><summary>' . esc_html__('Detalle de diferencias', 'homlity-real-estate') . '</summary><ul>' . $details . '</ul></details>' : '')
            . '</div>';
    }
}
