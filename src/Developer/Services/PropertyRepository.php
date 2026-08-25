<?php
// Los nombres de hook de este archivo salen de constantes de Homlity\Developer\Support\Hooks
// (o los recibe el método como argumento ya prefijado). Todas valen 'homlity/...',
// pero el sniff sólo sabe leer literales y las marca como dinámicas.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

declare(strict_types=1);

/**
 * @package Homlity\Developer
 * @since   2.8.0
 */

namespace Homlity\Developer\Services;

use Homlity\Developer\Models\Agent;
use Homlity\Developer\Models\Image;
use Homlity\Developer\Models\Money;
use Homlity\Developer\Models\Property;
use Homlity\Developer\Support\Hooks;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reads properties out of WordPress and hands back {@see Property} models.
 *
 * This is the only supported way for an extension to obtain a Property. It
 * absorbs every historical shape the underlying meta has taken, so extensions
 * do not have to.
 *
 * Obtain it with `Homlity::properties()`.
 *
 * @since 2.8.0
 */
final class PropertyRepository
{
    /**
     * Find a property by its WordPress post ID.
     *
     * @since 2.8.0
     *
     * @param int $propertyId Post ID.
     * @return Property|null Null when the post does not exist or is not a property.
     */
    public function find(int $propertyId): ?Property
    {
        if ($propertyId <= 0) {
            return null;
        }

        $post = get_post($propertyId);
        if (!$post instanceof \WP_Post || $post->post_type !== PropertyPostType::POST_TYPE) {
            return null;
        }

        return $this->hydrate($post);
    }

    /**
     * Find a property by its agency-facing code, e.g. `VTAP1320041`.
     *
     * The lookup is case-sensitive and matches the stored `_property_code`
     * exactly; it does not trigger an on-demand CRM sync.
     *
     * @since 2.8.0
     *
     * @param string $code Property code.
     */
    public function findByCode(string $code): ?Property
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $ids = get_posts([
            'post_type'        => PropertyPostType::POST_TYPE,
            'post_status'      => ['publish', 'pending', 'draft', 'private'],
            'posts_per_page'   => 1,
            'fields'           => 'ids',
            'no_found_rows'    => true,
            'suppress_filters' => false,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'meta_query'       => [
                ['key' => '_property_code', 'value' => $code],
            ],
        ]);

        return empty($ids) ? null : $this->find((int) $ids[0]);
    }

    /**
     * Find a property by the identifier it has in a source CRM.
     *
     * @since 2.8.0
     *
     * @param string $source     CRM key, e.g. `wasi`.
     * @param string $externalId Identifier inside that CRM.
     */
    public function findByExternalId(string $source, string $externalId): ?Property
    {
        $source     = sanitize_key($source);
        $externalId = trim($externalId);

        if ($source === '' || $externalId === '') {
            return null;
        }

        $ids = get_posts([
            'post_type'        => PropertyPostType::POST_TYPE,
            'post_status'      => ['publish', 'pending', 'draft', 'private'],
            'posts_per_page'   => 1,
            'fields'           => 'ids',
            'no_found_rows'    => true,
            'suppress_filters' => false,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'meta_query'       => [
                'relation' => 'AND',
                ['key' => '_property_external_source', 'value' => $source],
                ['key' => '_property_external_id', 'value' => $externalId],
            ],
        ]);

        return empty($ids) ? null : $this->find((int) $ids[0]);
    }

    // ─── Hydration ───────────────────────────────────────────────────────

    /**
     * Build a Property model from a post object.
     *
     * @internal Public so the event dispatcher can reuse it; not part of the
     *           stable contract. Use {@see self::find()}.
     */
    public function hydrate(\WP_Post $post): Property
    {
        $postId = (int) $post->ID;
        $meta   = $this->readMeta($postId);

        $data = [
            'id'                => $postId,
            'code'              => (string) ($meta['_property_code'] ?? ''),
            'title'             => (string) $post->post_title,
            'description'       => (string) $post->post_content,
            'short_description' => (string) $post->post_excerpt,
            'url'               => (string) get_permalink($postId),
            'status'            => (string) $post->post_status,
            'available'         => $this->isAvailable($post, $meta),
            'featured'          => $this->isTruthy($meta['_property_featured'] ?? ''),

            'operations'     => $this->termSlugs($postId, PropertyTaxonomies::TAXONOMY_OPERATION),
            'property_types' => $this->termSlugs($postId, PropertyTaxonomies::TAXONOMY_TYPE),
            'features'       => $this->termSlugs($postId, PropertyTaxonomies::TAXONOMY_FEATURE),

            'sale_price' => Money::fromMeta($meta['_property_price_sale'] ?? null, $meta['_property_currency_sale'] ?? ''),
            'rent_price' => Money::fromMeta($meta['_property_price_rent'] ?? null, $meta['_property_currency_rent'] ?? ''),
            'admin_fee'  => Money::fromMeta($meta['_property_price_admin'] ?? null, $meta['_property_currency_admin'] ?? ''),

            'bedrooms'     => (int) ($meta['_property_bedrooms'] ?? 0),
            'bathrooms'    => (int) ($meta['_property_bathrooms'] ?? 0),
            'parking'      => (int) ($meta['_property_parking'] ?? 0),
            'area'         => (float) ($meta['_property_area'] ?? 0),
            'area_private' => (float) ($meta['_property_area_private'] ?? 0),
            'stratum'      => (int) ($meta['_property_stratum'] ?? 0),

            'location' => [
                'address'            => (string) ($meta['_property_address'] ?? ''),
                'address_complement' => (string) ($meta['_property_address_complement'] ?? ''),
                'reference'          => (string) ($meta['_property_location_reference'] ?? ''),
                'latitude'           => $meta['_property_latitude'] ?? null,
                'longitude'          => $meta['_property_longitude'] ?? null,
                'country'            => $this->firstTermName($postId, PropertyTaxonomies::TAXONOMY_COUNTRY),
                'state'              => $this->firstTermName($postId, PropertyTaxonomies::TAXONOMY_STATE),
                'city'               => $this->firstTermName($postId, PropertyTaxonomies::TAXONOMY_CITY),
                'neighborhood'       => $this->firstTermName($postId, PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD),
                // Absent meta means "not hidden": most properties never set the flag.
                'show_exact_address' => !isset($meta['_property_show_exact_address'])
                    || $this->isTruthy($meta['_property_show_exact_address']),
            ],

            'images' => $this->readImages($postId, $meta),
            'videos' => $this->readUrlList($meta['_property_videos'] ?? null),

            'agent' => [
                'user_id'     => (int) ($meta['_property_agent_id'] ?? 0),
                'name'        => (string) ($meta['_property_agent_name'] ?? ''),
                'email'       => (string) ($meta['_property_agent_email'] ?? ''),
                'phone'       => (string) ($meta['_property_agent_phone'] ?? ''),
                'role'        => (string) ($meta['_property_agent_role'] ?? ''),
                'photo'       => (string) ($meta['_property_agent_photo'] ?? ''),
                'external_id' => (string) ($meta['_property_agent_external_id'] ?? ''),
            ],

            'external_source' => (string) ($meta['_property_external_source'] ?? ''),
            'external_id'     => (string) ($meta['_property_external_id'] ?? ''),
            'last_synced_at'  => (string) ($meta['_property_last_sync_at'] ?? ''),
        ];

        /**
         * Filters the field array used to build a public Property model.
         *
         * Runs for every property the Developer API hands out, so keep the
         * callback cheap. Returning a value that is not an array is ignored.
         *
         * @since 2.8.0
         *
         * @param array<string,mixed> $data     Hydration data. Keys mirror {@see Property::toArray()},
         *                                      except prices, images, location and agent, which are
         *                                      still value objects or raw sub-arrays at this point.
         * @param int                 $postId   Property post ID.
         * @param \WP_Post            $post     The underlying post.
         */
        $filtered = apply_filters(Hooks::FILTER_PROPERTY_DATA, $data, $postId, $post);

        return new Property(is_array($filtered) ? $filtered : $data);
    }

    // ─── Meta plumbing ───────────────────────────────────────────────────

    /**
     * The meta this model is built from, read key by key.
     *
     * Deliberately a whitelist rather than "everything except a blocklist":
     * a meta key added tomorrow — by this plugin or by another one — cannot
     * silently become part of the public model, and the owner's personal data
     * captured by the consignment form has no way in.
     *
     * @return array<string,mixed>
     */
    private function readMeta(int $postId): array
    {
        $keys = [
            '_property_code',
            '_property_status',
            '_property_available',
            '_property_featured',
            '_property_price_sale',
            '_property_currency_sale',
            '_property_price_rent',
            '_property_currency_rent',
            '_property_price_admin',
            '_property_currency_admin',
            '_property_bedrooms',
            '_property_bathrooms',
            '_property_parking',
            '_property_area',
            '_property_area_private',
            '_property_stratum',
            '_property_address',
            '_property_address_complement',
            '_property_location_reference',
            '_property_latitude',
            '_property_longitude',
            '_property_show_exact_address',
            '_property_gallery',
            '_property_videos',
            '_property_featured_image_url',
            '_property_agent_id',
            '_property_agent_name',
            '_property_agent_email',
            '_property_agent_phone',
            '_property_agent_role',
            '_property_agent_photo',
            '_property_agent_external_id',
            '_property_external_source',
            '_property_external_id',
            '_property_last_sync_at',
        ];

        $meta = [];
        foreach ($keys as $key) {
            $value = get_post_meta($postId, $key, true);
            if ($value === '' || $value === null) {
                continue;
            }

            $meta[$key] = $value;
        }

        return $meta;
    }

    /**
     * @param array<string,mixed> $meta
     * @return Image[]
     */
    private function readImages(int $postId, array $meta): array
    {
        $images  = [];
        $gallery = $meta['_property_gallery'] ?? '';
        $altBase = (string) get_the_title($postId);

        // Shape 1 — a CSV of attachment IDs (wp-admin editor).
        if (is_string($gallery) && $gallery !== '') {
            $decoded = json_decode($gallery, true);
            $gallery = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $gallery)));
        }

        foreach ((array) $gallery as $entry) {
            // Shape 2 — a nested array, as some CRM adapters produce.
            if (is_array($entry)) {
                $entry = $entry['url'] ?? ($entry['src'] ?? ($entry['full'] ?? ''));
            }

            if (!is_scalar($entry)) {
                continue;
            }

            $entry = trim((string) $entry);
            if ($entry === '') {
                continue;
            }

            // Shape 3 — an absolute URL (CRM pull).
            if (filter_var($entry, FILTER_VALIDATE_URL)) {
                $images[] = new Image($entry, 0, $altBase);
                continue;
            }

            // Shape 4 — an attachment ID.
            $attachmentId = (int) $entry;
            if ($attachmentId <= 0) {
                continue;
            }

            $url = wp_get_attachment_url($attachmentId);
            if (!is_string($url) || $url === '') {
                continue;
            }

            $alt = (string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
            $images[] = new Image($url, $attachmentId, $alt !== '' ? $alt : $altBase);
        }

        if ($images === []) {
            // Properties pulled from a CRM may only carry a remote cover image.
            $featured = (string) ($meta['_property_featured_image_url'] ?? '');
            if ($featured !== '' && filter_var($featured, FILTER_VALIDATE_URL)) {
                $images[] = new Image($featured, 0, $altBase);
            }
        }

        return $images;
    }

    /**
     * @param mixed $value
     * @return string[]
     */
    private function readUrlList($value): array
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $value   = is_array($decoded) ? $decoded : [$value];
        }

        $urls = [];
        foreach ((array) $value as $entry) {
            if (is_array($entry)) {
                $entry = $entry['url'] ?? '';
            }
            if (!is_scalar($entry)) {
                continue;
            }
            $entry = trim((string) $entry);
            if ($entry !== '' && filter_var($entry, FILTER_VALIDATE_URL)) {
                $urls[] = $entry;
            }
        }

        return $urls;
    }

    /**
     * Mirrors the availability rule the front end uses: an explicit status or
     * availability flag wins; when neither exists the property is available.
     *
     * @param array<string,mixed> $meta
     */
    private function isAvailable(\WP_Post $post, array $meta): bool
    {
        if ($post->post_status !== 'publish') {
            return false;
        }

        $status = strtolower(trim((string) ($meta['_property_status'] ?? '')));
        if ($status !== '' && $status !== 'active') {
            return false;
        }

        $available = strtolower(trim((string) ($meta['_property_available'] ?? '')));
        if ($available !== '') {
            return in_array($available, ['1', 'true', 'yes', 'active'], true);
        }

        return true;
    }

    /**
     * @param mixed $value
     */
    private function isTruthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on', 'active'], true);
    }

    /**
     * @return string[]
     */
    private function termSlugs(int $postId, string $taxonomy): array
    {
        $terms = get_the_terms($postId, $taxonomy);
        if (!is_array($terms)) {
            return [];
        }

        $slugs = [];
        foreach ($terms as $term) {
            if (is_object($term) && isset($term->slug)) {
                $slugs[] = (string) $term->slug;
            }
        }

        return $slugs;
    }

    private function firstTermName(int $postId, string $taxonomy): string
    {
        $terms = get_the_terms($postId, $taxonomy);
        if (!is_array($terms)) {
            return '';
        }

        foreach ($terms as $term) {
            if (is_object($term) && isset($term->name)) {
                return (string) $term->name;
            }
        }

        return '';
    }
}
