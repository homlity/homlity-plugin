<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Publica resúmenes reversibles sin cambiar excerpt ni content.
 *
 * @since 2.9.16
 */
class PropertySummaryService implements ServiceInterface
{
    public function register(): void
    {
        add_filter('rank_math/llms_txt/post_description', [$this, 'filterLlmsDescription'], 10, 2);
        // La recuperación de inmuebles no disponibles conserva su prioridad 20.
        add_filter('rank_math/frontend/description', [$this, 'filterMetaDescription'], 15);
    }

    /**
     * Solo antepone datos estructurados a las fichas de inmuebles.
     *
     * @since 2.9.16
     */
    public function filterLlmsDescription(string $description, \WP_Post $post): string
    {
        if ($post->post_type !== PropertyPostType::POST_TYPE || !SeoGeoSettingsService::get('ai_llms_structured_summary')) {
            return $description;
        }
        $summary = (new PropertySummaryComposer())->compose($post->ID);
        if ((new PropertyDuplicateDetector())->typeMismatch($post->ID) !== null) {
            return $summary;
        }
        $excerpt = trim(wp_trim_words(wp_strip_all_tags(strip_shortcodes($description)), 20, '…'));
        return $summary . ($excerpt !== '' ? ' — ' . $excerpt : '');
    }

    /**
     * Sustituye únicamente descripciones automáticas de fichas duplicadas.
     *
     * @since 2.9.16
     */
    public function filterMetaDescription(string $description): string
    {
        if (!is_singular(PropertyPostType::POST_TYPE)) {
            return $description;
        }
        $id = (int) get_queried_object_id();
        if (trim((string) get_post_meta($id, 'rank_math_description', true)) !== ''
            || (new PropertyDuplicateDetector())->groupFor($id) === null) {
            return $description;
        }
        return (new PropertySummaryComposer())->compose($id);
    }
}
