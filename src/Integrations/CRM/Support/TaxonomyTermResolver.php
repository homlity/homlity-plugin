<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM\Support;

if (!defined('ABSPATH')) {
    exit;
}

class TaxonomyTermResolver
{
    /**
     * @param array<int,string> $values
     * @return int[]
     */
    public function resolveTermIds(string $taxonomy, array $values): array
    {
        $ids = [];

        foreach ($values as $value) {
            $value = sanitize_text_field((string) $value);
            if ($value === '') {
                continue;
            }

            $slug = sanitize_title($value);
            $term = get_term_by('slug', $slug, $taxonomy);
            if (!$term || is_wp_error($term)) {
                $term = get_term_by('name', $value, $taxonomy);
            }

            if (!$term || is_wp_error($term)) {
                $created = wp_insert_term($value, $taxonomy, ['slug' => $slug]);
                if (is_wp_error($created)) {
                    continue;
                }
                $ids[] = (int) $created['term_id'];
                continue;
            }

            $ids[] = (int) $term->term_id;
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
