<?php
/**
 * Integrates CPT and taxonomies into Yoast SEO and Rank Math sitemaps.
 */

namespace Codwelt\PluginInmobiliario\Services;

use Codwelt\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class SeoIntegrationService implements ServiceInterface
{
    public function register(): void
    {
        add_filter('wpseo_sitemap_post_types', [$this, 'addYoastPostType']);
        add_filter('wpseo_sitemap_taxonomies', [$this, 'addYoastTaxonomies']);

        add_filter('rank_math/sitemaps/post_types', [$this, 'addRankMathPostType']);
        add_filter('rank_math/sitemaps/taxonomies', [$this, 'addRankMathTaxonomies']);
    }

    public function addYoastPostType(array $postTypes): array
    {
        if (!in_array(PropertyPostType::POST_TYPE, $postTypes, true)) {
            $postTypes[] = PropertyPostType::POST_TYPE;
        }
        return $postTypes;
    }

    public function addYoastTaxonomies(array $taxonomies): array
    {
        $toAdd = [
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
        ];
        foreach ($toAdd as $tax) {
            if (!in_array($tax, $taxonomies, true)) {
                $taxonomies[] = $tax;
            }
        }
        return $taxonomies;
    }

    public function addRankMathPostType(array $postTypes): array
    {
        if (!in_array(PropertyPostType::POST_TYPE, $postTypes, true)) {
            $postTypes[] = PropertyPostType::POST_TYPE;
        }
        return $postTypes;
    }

    public function addRankMathTaxonomies(array $taxonomies): array
    {
        $toAdd = [
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
        ];
        foreach ($toAdd as $tax) {
            if (!in_array($tax, $taxonomies, true)) {
                $taxonomies[] = $tax;
            }
        }
        return $taxonomies;
    }
}
