<?php
/**
 * Front templates for properties, taxonomies, search and agent profiles.
 */

namespace Codwelt\PluginInmobiliario\Services;

use Codwelt\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateService implements ServiceInterface
{
    public function register(): void
    {
        add_filter('query_vars', [$this, 'registerQueryVars']);
        add_action('init', [$this, 'addRewriteRules']);
        add_filter('template_include', [$this, 'maybeLoadTemplate']);
        add_action('pre_get_posts', [$this, 'filterArchiveQuery']);
    }

    public function registerQueryVars(array $vars): array
    {
        $vars[] = 'property_agent';
        $vars[] = 'property_type';
        $vars[] = 'property_category';
        $vars[] = 'property_operation';
        $vars[] = 'property_city';
        $vars[] = 'property_neighborhood';
        $vars[] = 'property_nearby';
        $vars[] = 'property_tag';
        $vars[] = 'price_min';
        $vars[] = 'price_max';
        $vars[] = 'area_min';
        $vars[] = 'area_max';
        $vars[] = 'date_from';
        $vars[] = 'date_to';
        return $vars;
    }

    public function addRewriteRules(): void
    {
        add_rewrite_rule(
            '^property-agent/([^/]+)/?$',
            'index.php?property_agent=$matches[1]',
            'top'
        );
    }

    public function filterArchiveQuery($query): void
    {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->is_post_type_archive(PropertyPostType::POST_TYPE)) {
            $taxQuery = [];
            $metaQuery = ['relation' => 'AND'];
            $settings = get_option('inmopress_settings', ['archive_per_page' => 12, 'archive_order' => 'date_desc']);
            $perPage = isset($settings['archive_per_page']) ? (int) $settings['archive_per_page'] : 12;
            if ($perPage > 0) {
                $query->set('posts_per_page', $perPage);
            }

            $taxMap = [
                'property_category' => PropertyTaxonomies::TAXONOMY_CATEGORY,
                'property_type' => PropertyTaxonomies::TAXONOMY_TYPE,
                'property_operation' => PropertyTaxonomies::TAXONOMY_OPERATION,
                'property_city' => PropertyTaxonomies::TAXONOMY_CITY,
                'property_neighborhood' => PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
                'property_nearby' => PropertyTaxonomies::TAXONOMY_NEARBY,
                'property_tag' => PropertyTaxonomies::TAXONOMY_TAG,
            ];

            foreach ($taxMap as $param => $taxonomy) {
                if (!empty($_GET[$param])) {
                    $taxQuery[] = [
                        'taxonomy' => $taxonomy,
                        'field' => 'slug',
                        'terms' => array_map('sanitize_text_field', (array) $_GET[$param]),
                    ];
                }
            }

            $priceMin = isset($_GET['price_min']) ? (float) $_GET['price_min'] : null;
            $priceMax = isset($_GET['price_max']) ? (float) $_GET['price_max'] : null;
            if ($priceMin || $priceMax) {
                $priceFields = ['_property_price_sale', '_property_price_rent', '_property_price_admin'];
                $priceGroup = ['relation' => 'OR'];
                foreach ($priceFields as $field) {
                    $condition = [
                        'key' => $field,
                        'type' => 'NUMERIC',
                        'compare' => 'EXISTS',
                    ];
                    if ($priceMin && $priceMax) {
                        $condition['value'] = [$priceMin, $priceMax];
                        $condition['compare'] = 'BETWEEN';
                    } elseif ($priceMin) {
                        $condition['value'] = $priceMin;
                        $condition['compare'] = '>=';
                    } elseif ($priceMax) {
                        $condition['value'] = $priceMax;
                        $condition['compare'] = '<=';
                    }
                    $priceGroup[] = $condition;
                }
                $metaQuery[] = $priceGroup;
            }

            $areaMin = isset($_GET['area_min']) ? (float) $_GET['area_min'] : null;
            $areaMax = isset($_GET['area_max']) ? (float) $_GET['area_max'] : null;
            if ($areaMin || $areaMax) {
                $areaCondition = [
                    'key' => '_property_area',
                    'type' => 'NUMERIC',
                ];
                if ($areaMin && $areaMax) {
                    $areaCondition['value'] = [$areaMin, $areaMax];
                    $areaCondition['compare'] = 'BETWEEN';
                } elseif ($areaMin) {
                    $areaCondition['value'] = $areaMin;
                    $areaCondition['compare'] = '>=';
                } else {
                    $areaCondition['value'] = $areaMax;
                    $areaCondition['compare'] = '<=';
                }
                $metaQuery[] = $areaCondition;
            }

            if (count($metaQuery) > 1) {
                $query->set('meta_query', $metaQuery);
            }

            if ($taxQuery) {
                $query->set('tax_query', $taxQuery);
            }

            $dateFrom = !empty($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : null;
            $dateTo = !empty($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : null;
            if ($dateFrom || $dateTo) {
                $dateQuery = [];
                if ($dateFrom) {
                    $dateQuery['after'] = $dateFrom;
                }
                if ($dateTo) {
                    $dateQuery['before'] = $dateTo;
                }
                $query->set('date_query', [$dateQuery]);
            }

            $order = $settings['archive_order'] ?? 'date_desc';
            if ($order === 'price_desc') {
                $query->set('meta_key', '_property_price_sale');
                $query->set('orderby', 'meta_value_num');
                $query->set('order', 'DESC');
            } else {
                $query->set('orderby', 'date');
                $query->set('order', 'DESC');
            }
        }
    }

    public function maybeLoadTemplate(string $template): string
    {
        if (get_query_var('property_agent')) {
            return self::locateTemplate('property-agent.php', $template);
        }

        if (is_singular(PropertyPostType::POST_TYPE)) {
            return self::locateTemplate('single-property.php', $template);
        }

        if (is_post_type_archive(PropertyPostType::POST_TYPE)) {
            return self::locateTemplate('archive-property.php', $template);
        }

        if (is_tax([
            PropertyTaxonomies::TAXONOMY_TYPE,
            PropertyTaxonomies::TAXONOMY_OPERATION,
            PropertyTaxonomies::TAXONOMY_LOCATION,
            PropertyTaxonomies::TAXONOMY_CATEGORY,
            PropertyTaxonomies::TAXONOMY_TAG,
            PropertyTaxonomies::TAXONOMY_FEATURE,
            PropertyTaxonomies::TAXONOMY_COUNTRY,
            PropertyTaxonomies::TAXONOMY_STATE,
            PropertyTaxonomies::TAXONOMY_CITY,
            PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
            PropertyTaxonomies::TAXONOMY_NEARBY,
        ])) {
            return self::locateTemplate('taxonomy-property.php', $template);
        }

        return $template;
    }

    public static function locateTemplate(string $filename, string $fallback = ''): string
    {
        $candidates = [
            'plugin-inmobiliario/' . $filename,
            $filename,
        ];

        $themeFile = locate_template($candidates);
        if ($themeFile) {
            return $themeFile;
        }

        $pluginFile = PLUGIN_INMOBILIARIO_PATH . 'templates/' . $filename;
        if (file_exists($pluginFile)) {
            return $pluginFile;
        }

        return $fallback ?: $filename;
    }

    public static function includeComponent(string $component, array $args = []): void
    {
        $filename = 'parts/' . $component;
        $path = self::locateTemplate($filename, PLUGIN_INMOBILIARIO_PATH . 'templates/' . $filename);

        if (!file_exists($path)) {
            return;
        }

        if ($args) {
            extract($args, EXTR_SKIP);
        }

        include $path;
    }
}
