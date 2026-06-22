<?php
/**
 * Outputs Schema.org JSON-LD (RealEstateAgent / LocalBusiness / FAQPage) in wp_head.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class SeoGeoSchemaService implements ServiceInterface
{
    public function register(): void
    {
        add_action('wp_head', [$this, 'outputSchema'], 1);
    }

    public function outputSchema(): void
    {
        $s = SeoGeoSettingsService::getSettings();

        if (empty($s['seo_enable_schema'])) {
            return;
        }

        // Determine whether to output schema on this page type
        if (is_front_page() || is_home()) {
            if (empty($s['schema_on_home'])) {
                return;
            }
        } elseif (is_singular('property')) {
            if (empty($s['schema_on_properties'])) {
                return;
            }
        } elseif (is_author()) {
            if (empty($s['schema_on_agents'])) {
                return;
            }
        } elseif (is_tax(['property_neighborhood', 'property_city', 'property_location'])) {
            if (empty($s['schema_on_zones'])) {
                return;
            }
        }

        $org = $this->buildOrgSchema($s);
        if ($org) {
            echo '<script type="application/ld+json">' // phpcs:ignore
                . wp_json_encode($org, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                . '</script>' . "\n";
        }

        if (!empty($s['schema_faq_active'])) {
            $faqSchema = $this->buildFaqSchema($s['global_faqs'] ?? []);
            if ($faqSchema) {
                echo '<script type="application/ld+json">' // phpcs:ignore
                    . wp_json_encode($faqSchema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                    . '</script>' . "\n";
            }
        }
    }

    private function buildOrgSchema(array $s): ?array
    {
        $name = $s['company_name'] ?? '';
        $url  = !empty($s['contact_website']) ? $s['contact_website'] : get_home_url();

        if (empty($name)) {
            return null;
        }

        $allowed = ['RealEstateAgent', 'LocalBusiness', 'Organization'];
        $type    = in_array($s['schema_type'] ?? '', $allowed, true) ? $s['schema_type'] : 'RealEstateAgent';

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => $type,
            'name'     => $name,
            'url'      => $url,
        ];

        if (!empty($s['company_slogan']))          $schema['slogan']      = $s['company_slogan'];
        if (!empty($s['company_description']))      $schema['description'] = $s['company_description'];
        if (!empty($s['company_logo']))             $schema['logo']        = $s['company_logo'];
        if (!empty($s['company_og_image']))         $schema['image']       = $s['company_og_image'];
        if (!empty($s['contact_email']))            $schema['email']       = $s['contact_email'];

        // ContactPoint
        if (!empty($s['contact_phone'])) {
            $schema['contactPoint'] = [
                '@type'       => 'ContactPoint',
                'telephone'   => $s['contact_phone'],
                'contactType' => 'customer service',
            ];
        }

        // OpeningHours
        if (!empty($s['contact_hours'])) {
            $schema['openingHours'] = $s['contact_hours'];
        }

        // PostalAddress
        $addrFields = ['geo_address', 'geo_city', 'geo_state', 'geo_postal_code', 'geo_country'];
        if (array_filter(array_intersect_key($s, array_flip($addrFields)))) {
            $addr = ['@type' => 'PostalAddress'];
            if (!empty($s['geo_address']))     $addr['streetAddress']   = $s['geo_address'];
            if (!empty($s['geo_city']))        $addr['addressLocality'] = $s['geo_city'];
            if (!empty($s['geo_state']))       $addr['addressRegion']   = $s['geo_state'];
            if (!empty($s['geo_postal_code'])) $addr['postalCode']      = $s['geo_postal_code'];
            if (!empty($s['geo_country']))     $addr['addressCountry']  = $s['geo_country'];
            $schema['address'] = $addr;
        }

        // GeoCoordinates
        if (!empty($s['geo_latitude']) && !empty($s['geo_longitude'])) {
            $schema['geo'] = [
                '@type'     => 'GeoCoordinates',
                'latitude'  => (float) $s['geo_latitude'],
                'longitude' => (float) $s['geo_longitude'],
            ];
        }

        // sameAs
        $sameAs = array_values(array_filter([
            $s['social_facebook']  ?? '',
            $s['social_instagram'] ?? '',
            $s['social_linkedin']  ?? '',
            $s['social_youtube']   ?? '',
            $s['social_tiktok']    ?? '',
            $s['social_twitter']   ?? '',
            $s['social_pinterest'] ?? '',
            $s['social_google_biz'] ?? '',
        ]));
        if (!empty($sameAs)) {
            $schema['sameAs'] = $sameAs;
        }

        // areaServed from coverage zones
        $zones = array_filter($s['coverage_zones'] ?? [], static fn($z) => !empty($z['active']));
        if (!empty($zones)) {
            $schema['areaServed'] = array_values(array_map(static function (array $z): array {
                $place = ['@type' => 'Place'];
                $parts = array_filter([$z['neighborhood'], $z['city'], $z['state'], $z['country']]);
                if ($parts) {
                    $place['name'] = implode(', ', $parts);
                }
                if (!empty($z['latitude']) && !empty($z['longitude'])) {
                    $place['geo'] = [
                        '@type'     => 'GeoCoordinates',
                        'latitude'  => (float) $z['latitude'],
                        'longitude' => (float) $z['longitude'],
                    ];
                }
                return $place;
            }, array_values($zones)));
        }

        return $schema;
    }

    private function buildFaqSchema(array $faqs): ?array
    {
        $active = array_filter($faqs, static fn($f) => !empty($f['active']) && !empty($f['schema']));
        if (empty($active)) {
            return null;
        }

        $items = [];
        foreach (array_values($active) as $faq) {
            if (empty($faq['question']) || empty($faq['answer'])) {
                continue;
            }
            $items[] = [
                '@type'          => 'Question',
                'name'           => wp_strip_all_tags($faq['question']),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => wp_strip_all_tags($faq['answer']),
                ],
            ];
        }

        if (empty($items)) {
            return null;
        }

        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $items,
        ];
    }
}
