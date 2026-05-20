<?php
/**
 * Generates the Schema.org JSON-LD @graph for individual property pages.
 *
 * Nodes produced:
 *   RealEstateListing → mainEntity: Apartment|House|… → Offer → RealEstateAgent
 *   BreadcrumbList
 */

if (!defined('ABSPATH')) {
    exit;
}

class Homlity_Property_Schema
{
    public function get_graph(int $post_id): array
    {
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return [];
        }

        $url = (string) get_permalink($post_id);
        if ($url === '') {
            return [];
        }

        $nodes = array_filter([
            $this->listing_node($post_id, $url),
            $this->property_node($post_id, $url),
            $this->offer_node($post_id, $url),
            $this->agent_node(),
            $this->breadcrumb_node($post_id, $url),
        ]);

        return array_values($nodes);
    }

    // ── RealEstateListing ─────────────────────────────────────────────────────

    private function listing_node(int $post_id, string $url): array
    {
        $images = Homlity_Schema_Helpers::images($post_id);
        $code   = Homlity_Schema_Helpers::get_meta($post_id, 'code');

        $node = [
            '@type'        => 'RealEstateListing',
            '@id'          => $url . '#listing',
            'name'         => Homlity_Schema_Helpers::clean(get_the_title($post_id)),
            'url'          => $url,
            'description'  => Homlity_Schema_Helpers::description($post_id),
            'image'        => !empty($images) ? $images : null,
            'datePosted'   => get_the_date('Y-m-d', $post_id),
            'dateModified' => get_the_modified_date('Y-m-d', $post_id),
            'identifier'   => $code !== '' ? $code : null,
            'mainEntity'   => ['@id' => $url . '#property'],
            'offers'       => ['@id' => $url . '#offer'],
        ];

        $node = Homlity_Schema_Helpers::drop_empty($node);
        return apply_filters('homlity_schema_listing_data', $node, $post_id);
    }

    // ── Property entity (Apartment / House / Place / …) ───────────────────────

    private function property_node(int $post_id, string $url): array
    {
        $type      = Homlity_Schema_Helpers::schema_type($post_id);
        $name      = Homlity_Schema_Helpers::clean(get_the_title($post_id));
        $images    = Homlity_Schema_Helpers::images($post_id);
        $address   = Homlity_Schema_Helpers::postal_address($post_id);
        $geo       = Homlity_Schema_Helpers::geo($post_id);
        $amenities = Homlity_Schema_Helpers::amenity_features($post_id);
        $add_props = Homlity_Schema_Helpers::additional_properties($post_id);
        $size      = Homlity_Schema_Helpers::floor_size($post_id);
        $bedrooms  = absint(Homlity_Schema_Helpers::get_meta($post_id, 'bedrooms'));
        $bathrooms = absint(Homlity_Schema_Helpers::get_meta($post_id, 'bathrooms'));
        $year      = Homlity_Schema_Helpers::get_meta($post_id, 'year_built');
        $floor     = Homlity_Schema_Helpers::get_meta($post_id, 'year_built'); // re-use filtered meta key slot
        // floor level is not a separate meta key in this plugin; leave null unless added via filter

        $node = [
            '@type'                  => $type,
            '@id'                    => $url . '#property',
            'name'                   => $name,
            'description'            => Homlity_Schema_Helpers::description($post_id),
            'url'                    => $url,
            'image'                  => !empty($images) ? $images : null,
            'address'                => !empty($address) ? $address : null,
            'geo'                    => !empty($geo) ? $geo : null,
            'numberOfBedrooms'       => $bedrooms > 0 ? $bedrooms : null,
            'numberOfBathroomsTotal' => $bathrooms > 0 ? $bathrooms : null,
            'yearBuilt'              => ($year !== '' && absint($year) >= 1900) ? absint($year) : null,
            'amenityFeature'         => !empty($amenities) ? $amenities : null,
            'additionalProperty'     => !empty($add_props) ? $add_props : null,
        ];

        if ($size > 0) {
            $node['floorSize'] = [
                '@type'    => 'QuantitativeValue',
                'value'    => $size,
                'unitCode' => 'MTK',
            ];
        }

        $node = Homlity_Schema_Helpers::drop_empty($node);
        return apply_filters('homlity_schema_property_data', $node, $post_id);
    }

    // ── Offer ─────────────────────────────────────────────────────────────────

    private function offer_node(int $post_id, string $url): array
    {
        $op       = Homlity_Schema_Helpers::operation($post_id);
        $price    = Homlity_Schema_Helpers::price($post_id);
        $currency = Homlity_Schema_Helpers::currency($post_id);

        $node = [
            '@type'            => 'Offer',
            '@id'              => $url . '#offer',
            'url'              => $url,
            'price'            => $price !== '' ? $price : null,
            'priceCurrency'    => $price !== '' ? $currency : null,
            'availability'     => Homlity_Schema_Helpers::availability($post_id),
            'businessFunction' => Homlity_Schema_Helpers::business_function($op),
            'itemOffered'      => ['@id' => $url . '#property'],
            'seller'           => ['@id' => home_url('/') . '#realestateagent'],
        ];

        $node = Homlity_Schema_Helpers::drop_empty($node);
        return apply_filters('homlity_schema_offer_data', $node, $post_id);
    }

    // ── RealEstateAgent ───────────────────────────────────────────────────────

    private function agent_node(): array
    {
        $a       = Homlity_Schema_Helpers::agency();
        $same_as = Homlity_Schema_Helpers::same_as_urls($a['same_as']);

        $logo = $a['logo'] !== '' ? ['@type' => 'ImageObject', 'url' => esc_url_raw($a['logo'])] : null;

        $address = Homlity_Schema_Helpers::drop_empty([
            '@type'           => 'PostalAddress',
            'streetAddress'   => $a['address'],
            'addressLocality' => $a['city'],
            'addressRegion'   => $a['region'],
            'addressCountry'  => $a['country'],
        ]);
        if (count($address) <= 1) {
            $address = null; // only @type → nothing useful
        }

        $node = [
            '@type'     => 'RealEstateAgent',
            '@id'       => home_url('/') . '#realestateagent',
            'name'      => $a['name'],
            'url'       => home_url('/'),
            'telephone' => $a['telephone'] ?: null,
            'email'     => $a['email'] !== '' ? sanitize_email($a['email']) : null,
            'logo'      => $logo,
            'address'   => $address,
            'sameAs'    => !empty($same_as) ? $same_as : null,
        ];

        $node = Homlity_Schema_Helpers::drop_empty($node);
        return apply_filters('homlity_schema_agent_data', $node, 0);
    }

    // ── BreadcrumbList ────────────────────────────────────────────────────────

    private function breadcrumb_node(int $post_id, string $url): array
    {
        $items = [];
        $pos   = 1;

        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => Homlity_Schema_Helpers::clean(get_bloginfo('name')),
            'item'     => home_url('/'),
        ];

        $catalog = (string) get_option('homlity_schema_catalog_url', '');
        if ($catalog !== '' && Homlity_Schema_Helpers::valid_url($catalog)) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $pos++,
                'name'     => __('Inmuebles', 'homlity-real-estate'),
                'item'     => esc_url_raw($catalog),
            ];
        }

        // Intermediate crumb: property type taxonomy term
        $type_terms = get_the_terms($post_id, Homlity_Schema_Helpers::type_taxonomy());
        if (is_array($type_terms) && !empty($type_terms)) {
            $term = reset($type_terms);
            $link = get_term_link($term);
            if (!is_wp_error($link)) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $pos++,
                    'name'     => Homlity_Schema_Helpers::clean($term->name),
                    'item'     => $link,
                ];
            }
        }

        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos,
            'name'     => Homlity_Schema_Helpers::clean(get_the_title($post_id)),
            'item'     => $url,
        ];

        $node = [
            '@type'           => 'BreadcrumbList',
            '@id'             => $url . '#breadcrumb',
            'itemListElement' => $items,
        ];

        return apply_filters('homlity_schema_breadcrumb_data', $node, $post_id);
    }
}
