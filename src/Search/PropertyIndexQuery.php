<?php

namespace Homlity\PluginInmobiliario\Search;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!defined('ABSPATH')) {
    exit;
}

/** Translates the plugin's named filters to the denormalized search index. */
final class PropertyIndexQuery implements ServiceInterface
{
    /** @var array<int,array<string,mixed>> */
    private static array $comparisonReports = [];
    private static bool $comparisonInProgress = false;

    public function register(): void
    {
        add_action('pre_get_posts', [$this, 'prepareQuery'], 20);
        add_filter('posts_clauses', [$this, 'applyClauses'], 30, 2);
        add_filter('the_posts', [$this, 'compareQueryResults'], 30, 2);
        add_action('wp_footer', [$this, 'renderComparisonNotice']);
    }

    public static function isActive(?\WP_Query $query = null): bool
    {
        if ($query !== null && $query->get('post_type') !== PropertyPostType::POST_TYPE) {
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
        $rawKeyword = trim((string) ($filters['keyword'] ?? ''));
        if ($rawKeyword !== '') {
            $keyword = preg_replace('/[+<>()[\]~*"@]/', ' ', strtolower(remove_accents($rawKeyword))) ?? '';
            $keyword = trim((string) preg_replace('/\s+/', ' ', $keyword));
            $likeAny = '%' . $wpdb->esc_like($keyword) . '%';
            $likePrefix = $wpdb->esc_like($keyword) . '%';
            if (str_contains($keyword, '-') || preg_match('/\d/', $keyword) === 1) {
                $where[] = '(' . $wpdb->prepare('hpi.code = %s', $keyword)
                    . ' OR ' . $wpdb->prepare('hpi.sync_id = %s', $keyword)
                    . ' OR ' . $wpdb->prepare('hpi.code LIKE %s', $likePrefix)
                    . ' OR ' . $wpdb->prepare('hpi.sync_id LIKE %s', $likePrefix) . ')';
                $rank = 'MIN(CASE WHEN hpi.code = ' . $wpdb->prepare('%s', $keyword)
                    . ' OR hpi.sync_id = ' . $wpdb->prepare('%s', $keyword)
                    . ' THEN 0 WHEN hpi.code LIKE ' . $wpdb->prepare('%s', $likePrefix)
                    . ' OR hpi.sync_id LIKE ' . $wpdb->prepare('%s', $likePrefix) . ' THEN 1 ELSE 9 END), ';
            } else {
                $parts = $this->safeBooleanWords($keyword);
                $search = [];
                if ($parts !== []) {
                    $boolean = implode(' ', array_map(static fn(string $part): string => '+' . $part . '*', $parts));
                    $match = $wpdb->prepare('MATCH(hpi.search_text) AGAINST(%s IN BOOLEAN MODE)', $boolean);
                    if (count($parts) > 1) {
                        $search[] = '(' . $match . ' AND ' . $wpdb->prepare('hpi.search_text LIKE %s', '%' . $wpdb->esc_like(implode(' ', $parts)) . '%') . ')';
                    } else {
                        $search[] = $match;
                    }
                } else {
                    $search[] = $wpdb->prepare('hpi.search_text LIKE %s', $likeAny);
                }
                $where[] = '(' . implode(' OR ', $search) . ')';
                $rank = 'MIN(CASE WHEN ' . $wpdb->prepare('hpi.search_text LIKE %s', $likeAny) . ' THEN 4 ELSE 9 END), ';
            }
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

    /** @return string[] */
    private function safeBooleanWords(string $keyword): array
    {
        $clean = preg_replace('/[+\-<>()[\]~*"@]/', ' ', $keyword) ?? '';
        return array_values(array_filter(preg_split('/\s+/', trim($clean)) ?: [], static fn(string $word): bool => strlen($word) >= 3));
    }

    public function compareQueryResults(array $posts, \WP_Query $query): array
    {
        if (!self::shouldCompare($query)) {
            return $posts;
        }
        $this->compareQuery($query);
        return $posts;
    }

    public static function shouldCompare(?\WP_Query $query = null): bool
    {
        return !empty($_GET['homlity_index_compare'])
            && current_user_can('manage_options')
            && ($query === null || is_array($query->get('homlity_index_filters')));
    }

    /** @return array<string,mixed>|null */
    public function compareQuery(\WP_Query $query): ?array
    {
        $key = spl_object_id($query);
        if (!self::shouldCompare($query) || isset(self::$comparisonReports[$key])) {
            return self::$comparisonReports[$key] ?? null;
        }
        if (self::$comparisonInProgress) {
            return null;
        }
        if (!PropertyIndexSchema::isReady()) {
            return self::$comparisonReports[$key] = ['query_id' => $key, 'error' => __('El índice no está listo.', 'homlity-real-estate')];
        }
        if (function_exists('nocache_headers')) nocache_headers();
        do_action('litespeed_control_set_nocache');

        self::$comparisonInProgress = true;
        try {
            $args = $query->query_vars;
            if (is_array($query->get('homlity_compare_meta_query'))) {
                $args['meta_query'] = $query->get('homlity_compare_meta_query');
            }
            unset($args['homlity_compare_meta_query']);
            $args['posts_per_page'] = (int) ($args['posts_per_page'] ?? 12);
            $args['paged'] = (int) ($args['paged'] ?? 1);

            $started = microtime(true);
            $forceIndex = static fn(bool $enabled): bool => true;
            $forcePostmeta = static fn(bool $enabled): bool => false;
            add_filter('homlity/search/use_index', $forceIndex, 9999);
            $indexQuery = new \WP_Query($args);
            remove_filter('homlity/search/use_index', $forceIndex, 9999);
            $indexTime = microtime(true) - $started;

            add_filter('homlity/search/use_index', $forcePostmeta, 9999);
            $started = microtime(true);
            $postmetaQuery = new \WP_Query($args);
            $postmetaTime = microtime(true) - $started;
            remove_filter('homlity/search/use_index', $forcePostmeta, 9999);

            $indexIds = self::queryIds($indexQuery);
            $postmetaIds = self::queryIds($postmetaQuery);
            $comparisonIds = $indexIds;
            $postmetaComparisonIds = $postmetaIds;
            if ((int) $indexQuery->found_posts <= 500) {
                $allArgs = $args;
                $allArgs['fields'] = 'ids';
                $allArgs['posts_per_page'] = -1;
                $allArgs['paged'] = 1;
                $allArgs['no_found_rows'] = false;
                add_filter('homlity/search/use_index', $forceIndex, 9999);
                $allIndex = new \WP_Query($allArgs);
                remove_filter('homlity/search/use_index', $forceIndex, 9999);
                add_filter('homlity/search/use_index', $forcePostmeta, 9999);
                $allPostmeta = new \WP_Query($allArgs);
                remove_filter('homlity/search/use_index', $forcePostmeta, 9999);
                $comparisonIds = self::queryIds($allIndex);
                $postmetaComparisonIds = self::queryIds($allPostmeta);
            }
            $different = array_values(array_unique(array_merge(array_diff($comparisonIds, $postmetaComparisonIds), array_diff($postmetaComparisonIds, $comparisonIds))));
            $classified = $this->classifyDifferences($different, (array) $query->get('homlity_index_filters'));
            $report = [
                'query_id' => $key,
                'index_time_ms' => round($indexTime * 1000, 2),
                'postmeta_time_ms' => round($postmetaTime * 1000, 2),
                'index_found_posts' => (int) $indexQuery->found_posts,
                'postmeta_found_posts' => (int) $postmetaQuery->found_posts,
                'order_equal' => $comparisonIds === $postmetaComparisonIds,
                'found_equal' => (int) $indexQuery->found_posts === (int) $postmetaQuery->found_posts,
                'expected' => count($classified['expected']),
                'unexplained' => count($classified['unexplained']),
                'details' => array_merge($classified['expected'], $classified['unexplained']),
            ];
            self::$comparisonReports[$key] = $report;
            error_log('[homlity index compare] ' . wp_json_encode($report));
            return $report;
        } finally {
            self::$comparisonInProgress = false;
        }
    }

    /** @return int[] */
    private static function queryIds(\WP_Query $query): array
    {
        return array_values(array_map(static function ($post): int {
            return is_object($post) ? (int) ($post->ID ?? 0) : (int) $post;
        }, $query->posts));
    }

    /** @param int[] $ids @param array<string,mixed> $filters @return array{expected:array<int,mixed>,unexplained:array<int,mixed>} */
    private function classifyDifferences(array $ids, array $filters): array
    {
        global $wpdb;
        $result = ['expected' => [], 'unexplained' => []];
        foreach ($ids as $id) {
            $raws = [
                'price_sale' => (string) get_post_meta($id, '_property_price_sale', true),
                'price_rent' => (string) get_post_meta($id, '_property_price_rent', true),
                'area' => (string) get_post_meta($id, '_property_area', true),
            ];
            $row = $wpdb->get_row($wpdb->prepare('SELECT price_sale, price_rent, area, display_price FROM ' . PropertyIndexSchema::tableName() . ' WHERE post_id = %d', $id), ARRAY_A) ?: [];
            $reason = '';
            foreach ($raws as $field => $raw) {
                if ($raw === '') continue;
                $current = $this->currentSqlNumber($raw);
                if (isset($row[$field]) && $row[$field] !== null && (float) $row[$field] !== $current && preg_match('/[.,]|[^0-9.\-]/', $raw)) {
                    $reason = 'normalización de ' . $field;
                    break;
                }
            }
            $keyword = strtolower(remove_accents((string) ($filters['keyword'] ?? '')));
            if ($reason === '' && $keyword !== '') {
                foreach ([PropertyTaxonomies::TAXONOMY_CITY, PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD] as $taxonomy) {
                    $terms = get_the_terms($id, $taxonomy);
                    foreach ((array) $terms as $term) {
                        if (is_object($term) && str_contains(strtolower(remove_accents((string) ($term->name ?? ''))), $keyword)) {
                            $reason = 'coincidencia de término de ubicación en search_text';
                        }
                    }
                }
            }
            if ($reason === '' && ($filters['orderby'] ?? '') === 'price_asc' && (($row['display_price'] ?? null) === null || (float) ($row['display_price'] ?? 0) === 0)) {
                $reason = 'precio 0/NULL al final en orden ascendente';
            }
            $detail = ['post_id' => $id, 'reason' => $reason ?: 'no explicada', 'raw' => $raws, 'indexed' => $row];
            if ($reason !== '') {
                $result['expected'][] = $detail;
            } else {
                $result['unexplained'][] = $detail;
            }
        }
        return $result;
    }

    private function currentSqlNumber(string $raw): float
    {
        return preg_match('/^-?\d+/', trim($raw), $match) === 1 ? (float) $match[0] : 0.0;
    }

    public function renderComparisonNotice(): void
    {
        if (!current_user_can('manage_options') || self::$comparisonReports === []) return;
        echo '<div class="notice notice-info"><ul>';
        foreach (self::$comparisonReports as $report) {
            if (!empty($report['error'])) {
                echo '<li>' . esc_html((string) $report['error']) . '</li>';
                continue;
            }
            printf('<li>%s</li>', esc_html(sprintf(
                __('Consulta %d: %d esperadas, %d no explicadas; totales %d/%d; orden igual: %s. Índice %.2f ms / postmeta %.2f ms.', 'homlity-real-estate'),
                (int) $report['query_id'], (int) $report['expected'], (int) $report['unexplained'],
                (int) $report['index_found_posts'], (int) $report['postmeta_found_posts'],
                $report['order_equal'] ? 'sí' : 'no', (float) $report['index_time_ms'], (float) $report['postmeta_time_ms']
            )));
            foreach ((array) ($report['details'] ?? []) as $detail) {
                echo '<li>post_id ' . esc_html((string) ($detail['post_id'] ?? '')) . ': ' . esc_html((string) ($detail['reason'] ?? ''))
                    . '; raw=' . esc_html(wp_json_encode($detail['raw'] ?? []))
                    . '; índice=' . esc_html(wp_json_encode($detail['indexed'] ?? [])) . '</li>';
            }
        }
        echo '</ul></div>';
    }

    /** @return array<string,mixed>|null */
    public static function reportForQuery(\WP_Query $query): ?array
    {
        return self::$comparisonReports[spl_object_id($query)] ?? null;
    }
}
