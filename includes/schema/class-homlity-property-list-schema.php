<?php
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_tax_query
/**
 * Generates Schema.org JSON-LD for property archive / taxonomy / catalog pages.
 *
 * Nodes produced:  CollectionPage → ItemList  +  BreadcrumbList
 */

if (!defined('ABSPATH')) {
    exit;
}

class Homlity_Property_List_Schema
{
    public function get_graph(): array
    {
        $url   = $this->current_url();
        $title = $this->current_title();
        $desc  = $this->current_description();
        $items = $this->item_list_elements();

        $item_list = [
            '@type'           => 'ItemList',
            '@id'             => $url . '#itemlist',
            'numberOfItems'   => count($items),
            'itemListElement' => $items,
        ];
        $item_list = apply_filters('homlity_schema_list_data', $item_list, $url);

        $collection = Homlity_Schema_Helpers::drop_empty([
            '@type'       => 'CollectionPage',
            '@id'         => $url . '#collection',
            'name'        => $title,
            'url'         => $url,
            'description' => $desc,
            'mainEntity'  => ['@id' => $url . '#itemlist'],
        ]);

        $graph = [$collection, $item_list];

        $crumb = $this->breadcrumb_node($url, $title);
        if (!empty($crumb)) {
            $graph[] = $crumb;
        }

        return $graph;
    }

    // ── Item list ─────────────────────────────────────────────────────────────

    private function limit(): int
    {
        $default = max(1, absint(get_option('homlity_schema_itemlist_limit', 20)));
        return absint(apply_filters('homlity_schema_itemlist_limit', $default));
    }

    private function item_list_elements(): array
    {
        $post_type = Homlity_Schema_Helpers::post_type();

        $args = [
            'post_type'              => $post_type,
            'post_status'            => 'publish',
            'posts_per_page'         => $this->limit(),
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'fields'                 => 'ids',
        ];

        // Narrow by current taxonomy term
        if (is_tax()) {
            $queried = get_queried_object();
            if ($queried instanceof WP_Term) {
                $args['tax_query'] = [[
                    'taxonomy' => $queried->taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $queried->term_id,
                ]];
            }
        }

        $query   = new WP_Query($args);
        $items   = [];
        $pos     = 1;

        foreach ($query->posts as $pid) {
            $link = get_permalink((int) $pid);
            if (!$link) {
                continue;
            }
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $pos++,
                'name'     => Homlity_Schema_Helpers::clean(get_the_title((int) $pid)),
                'url'      => $link,
            ];
        }

        return $items;
    }

    // ── BreadcrumbList ────────────────────────────────────────────────────────

    private function breadcrumb_node(string $url, string $title): array
    {
        $items = [[
            '@type'    => 'ListItem',
            'position' => 1,
            'name'     => Homlity_Schema_Helpers::clean(get_bloginfo('name')),
            'item'     => home_url('/'),
        ]];

        $pos = 2;

        if (is_tax()) {
            $catalog = (string) get_option('homlity_schema_catalog_url', '');
            if ($catalog !== '' && Homlity_Schema_Helpers::valid_url($catalog)) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $pos++,
                    'name'     => __('Inmuebles', 'homlity-real-estate'),
                    'item'     => esc_url_raw($catalog),
                ];
            }
        }

        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos,
            'name'     => $title,
            'item'     => $url,
        ];

        return [
            '@type'           => 'BreadcrumbList',
            '@id'             => $url . '#breadcrumb',
            'itemListElement' => $items,
        ];
    }

    // ── Current page metadata ─────────────────────────────────────────────────

    private function current_url(): string
    {
        return esc_url_raw(home_url(add_query_arg(null, null)));
    }

    private function current_title(): string
    {
        if (is_tax() || is_category() || is_tag()) {
            $queried = get_queried_object();
            if ($queried instanceof WP_Term) {
                return Homlity_Schema_Helpers::clean($queried->name);
            }
        }
        if (is_post_type_archive()) {
            $label = post_type_archive_title('', false);
            return $label ? Homlity_Schema_Helpers::clean((string) $label) : '';
        }
        $title = wp_title('', false);
        return $title ? Homlity_Schema_Helpers::clean($title) : Homlity_Schema_Helpers::clean(get_bloginfo('name'));
    }

    private function current_description(): string
    {
        if (is_tax() || is_category() || is_tag()) {
            $queried = get_queried_object();
            if ($queried instanceof WP_Term && !empty($queried->description)) {
                return Homlity_Schema_Helpers::clean($queried->description);
            }
        }
        return '';
    }
}
