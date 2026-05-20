<?php
/**
 * Generates Schema.org JSON-LD for home, contact, and institutional pages.
 *
 * Nodes produced:  RealEstateAgent (+ LocalBusiness)  +  WebSite
 */

if (!defined('ABSPATH')) {
    exit;
}

class Homlity_Agency_Schema
{
    public function get_graph(): array
    {
        $nodes = array_filter([
            $this->agent_node(),
            $this->website_node(),
        ]);

        return array_values($nodes);
    }

    // ── RealEstateAgent + LocalBusiness ───────────────────────────────────────

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
            $address = null;
        }

        $node = [
            '@type'       => ['RealEstateAgent', 'LocalBusiness'],
            '@id'         => home_url('/') . '#realestateagent',
            'name'        => $a['name'],
            'description' => $a['description'] ?: null,
            'url'         => home_url('/'),
            'telephone'   => $a['telephone'] ?: null,
            'email'       => $a['email'] !== '' ? sanitize_email($a['email']) : null,
            'logo'        => $logo,
            'address'     => $address,
            'sameAs'      => !empty($same_as) ? $same_as : null,
        ];

        $node = Homlity_Schema_Helpers::drop_empty($node);
        return apply_filters('homlity_schema_agent_data', $node, 0);
    }

    // ── WebSite + SearchAction ────────────────────────────────────────────────

    private function website_node(): array
    {
        $node = [
            '@type' => 'WebSite',
            '@id'   => home_url('/') . '#website',
            'name'  => Homlity_Schema_Helpers::clean(get_bloginfo('name')),
            'url'   => home_url('/'),
        ];

        $search_url = (string) get_option('homlity_schema_search_url', '');
        if ($search_url !== '' && Homlity_Schema_Helpers::valid_url(str_replace('{search_term_string}', 'x', $search_url))) {
            $node['potentialAction'] = [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => esc_url_raw($search_url),
                ],
                'query-input' => 'required name=search_term_string',
            ];
        }

        return $node;
    }
}
