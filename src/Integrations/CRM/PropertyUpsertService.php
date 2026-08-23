<?php
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
namespace Homlity\PluginInmobiliario\Integrations\CRM;

use Homlity\Developer\Events\PropertyContext;
use Homlity\Developer\Support\Hooks;
use Homlity\PluginInmobiliario\Core\PropertyEventDispatcher;
use Homlity\PluginInmobiliario\Homologation\EntityType;
use Homlity\PluginInmobiliario\Homologation\HomologationService;
use Homlity\PluginInmobiliario\Integrations\CRM\FieldMap\PropertyFieldSchema;
use Homlity\PluginInmobiliario\Integrations\CRM\Repository\SyncIndexRepository;
use Homlity\PluginInmobiliario\Integrations\CRM\Services\AdvisorSyncService;
use Homlity\PluginInmobiliario\Integrations\CRM\Support\ArrayPath;
use Homlity\PluginInmobiliario\Integrations\CRM\Support\TaxonomyTermResolver;
use Homlity\PluginInmobiliario\Services\CurrencyService;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyUpsertService
{
    private TaxonomyTermResolver $termResolver;
    private SyncIndexRepository  $indexRepository;
    private HomologationService  $homologation;
    private AdvisorSyncService   $advisorSync;

    public function __construct()
    {
        $this->termResolver    = new TaxonomyTermResolver();
        $this->indexRepository = new SyncIndexRepository();
        $this->homologation    = new HomologationService();
        $this->advisorSync     = new AdvisorSyncService();
    }

    /**
     * Create or update a property from a canonical normalized payload.
     *
     * @param array<string,mixed> $normalized Canonical property payload.
     * @param string              $origin     One of the {@see PropertyContext} ORIGIN_* constants.
     *                                        Describes who is writing, so the public lifecycle
     *                                        hooks can tell a CRM sync from a consignment.
     * @return array<string,mixed>
     */
    public function upsert(array $normalized, string $origin = PropertyContext::ORIGIN_CRM): array
    {
        $sourceKey = sanitize_key((string) ($normalized['external']['source'] ?? ''));

        /**
         * Filters a normalized property payload just before it is written.
         *
         * This is the last chance to change what Homlity persists: every field
         * of the canonical schema is present and the taxonomy terms have not
         * been resolved yet.
         *
         * A return value that is not an array is ignored, so a mistaken filter
         * cannot blank out a property.
         *
         * @since 2.8.0
         *
         * @param array<string,mixed> $normalized Canonical payload. See docs/developers/models/property.md.
         * @param string              $source     Key of the CRM the record came from, or ''.
         * @param string              $origin     One of the PropertyContext ORIGIN_* constants.
         */
        $filtered = apply_filters(Hooks::FILTER_PROPERTY_NORMALIZED, $normalized, $sourceKey, $origin);
        if (is_array($filtered)) {
            $normalized = $filtered;
        }

        $external   = is_array($normalized['external'] ?? null) ? $normalized['external'] : [];
        $source     = sanitize_key((string) ($external['source'] ?? ''));
        $externalId = sanitize_text_field((string) ($external['id'] ?? ''));

        if ($source === '' || $externalId === '') {
            return ['ok' => false, 'error' => 'Missing external.source or external.id'];
        }

        $postId   = $this->findByExternal($source, $externalId);

        // Secondary dedup: if no sync-index match, prevent a second post with the same property code.
        if ($postId === 0) {
            $code = sanitize_text_field((string) (ArrayPath::get($normalized, 'metrics.code') ?? ''));
            if ($code !== '') {
                $postId = $this->findByCode($code);
            }
        }

        // Read the previous state now, while it still is the previous state:
        // the public `homlity/property/updated` hook promises a real diff.
        $isNew         = $postId === 0;
        $previousState = $isNew ? [] : PropertyEventDispatcher::snapshot($postId);

        $postData = is_array($normalized['post'] ?? null) ? $normalized['post'] : [];

        $title = sanitize_text_field((string) ($postData['title'] ?? ''));
        if ($title === '') {
            return ['ok' => false, 'error' => 'Missing post.title'];
        }

        $payload = [
            'post_type'    => PropertyPostType::POST_TYPE,
            'post_title'   => $title,
            'post_content' => wp_kses_post((string) ($postData['description'] ?? '')),
            'post_excerpt' => sanitize_textarea_field((string) ($postData['short_description'] ?? '')),
            'post_status'  => $this->normalizePostStatus((string) ($postData['status'] ?? 'publish')),
        ];

        // Sin el `(int)` a propósito: con él, un WP_Error se convertía en 1 —eso
        // es lo que vale un objeto casteado a entero—, la comprobación de error
        // de abajo no llegaba a dispararse nunca, y el servicio seguía adelante
        // escribiendo las metas, las taxonomías y el asesor del inmueble sobre
        // el post con ID 1 del sitio.
        $saved = $postId > 0
            ? wp_update_post(array_merge($payload, ['ID' => $postId]), true)
            : wp_insert_post($payload, true);

        if (is_wp_error($saved)) {
            return ['ok' => false, 'error' => $saved->get_error_message()];
        }

        $postId = (int) $saved;
        if ($postId <= 0) {
            return ['ok' => false, 'error' => 'Unable to save post'];
        }

        $this->saveMeta($postId, $normalized, $source, $externalId);
        $this->saveMediaArrays($postId, $normalized);
        $this->saveAdvisor($postId, $source, $normalized);
        $this->saveDirectTaxonomies($postId, $normalized);
        $this->saveHomologatedTaxonomies($postId, $source, $normalized);
        $this->saveGeoHierarchy($postId, $source, $normalized);
        $this->saveSyncMeta($postId, $externalId, (string) ($external['updated_at'] ?? ''));

        $this->indexRepository->upsert(
            $source,
            $externalId,
            $postId,
            sanitize_text_field((string) (ArrayPath::get($normalized, 'external.hash') ?? '')),
            'synced',
            sanitize_text_field((string) (ArrayPath::get($normalized, 'external.updated_at') ?? ''))
        );

        // Everything is persisted — post, meta, media, advisor, taxonomies — so
        // the property handed to listeners is exactly the one that was saved.
        PropertyEventDispatcher::dispatchSaved(
            $postId,
            $previousState,
            new PropertyContext($origin, $source, $isNew)
        );

        return ['ok' => true, 'post_id' => $postId];
    }

    // ─── Post status ──────────────────────────────────────────────────────────

    private function normalizePostStatus(string $status): string
    {
        $status = sanitize_key($status);
        return in_array($status, ['publish', 'draft', 'pending', 'private'], true) ? $status : 'publish';
    }

    // ─── Post lookup ─────────────────────────────────────────────────────────

    private function findByExternal(string $source, string $externalId): int
    {
        $indexedPostId = $this->indexRepository->findPostId($source, $externalId);
        if ($indexedPostId > 0) {
            return $indexedPostId;
        }

        $query = new \WP_Query([
            'post_type'      => PropertyPostType::POST_TYPE,
            'post_status'    => ['publish', 'pending', 'draft', 'private'],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => '_property_external_source', 'value' => $source],
                ['key' => '_property_external_id',     'value' => $externalId],
            ],
            'no_found_rows'  => true,
        ]);

        if (empty($query->posts)) {
            return 0;
        }

        return (int) $query->posts[0];
    }

    private function findByCode(string $code): int
    {
        $query = new \WP_Query([
            'post_type'      => PropertyPostType::POST_TYPE,
            'post_status'    => ['publish', 'pending', 'draft', 'private'],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                ['key' => '_property_code', 'value' => $code],
            ],
        ]);

        if (empty($query->posts)) {
            return 0;
        }

        return (int) $query->posts[0];
    }

    // ─── Meta ────────────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $normalized
     */
    private function saveMeta(int $postId, array $normalized, string $source, string $externalId): void
    {
        $currencyPaths = ['pricing.sale_currency', 'pricing.rent_currency', 'pricing.admin_currency'];

        foreach (PropertyFieldSchema::metaMap() as $path => $metaKey) {
            $value = ArrayPath::get($normalized, $path);

            if (in_array($path, $currencyPaths, true)) {
                $currency = strtoupper(trim((string) $value));
                $value = $currency !== '' ? $currency : CurrencyService::DEFAULT_CURRENCY;
            }

            if ($value === null || $value === '') {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            if (is_array($value)) {
                continue; // arrays are handled by saveMediaArrays()
            }

            update_post_meta($postId, $metaKey, sanitize_text_field((string) $value));
        }

        update_post_meta($postId, '_property_external_source', $source);
        update_post_meta($postId, '_property_external_id', $externalId);
        update_post_meta($postId, '_property_last_sync_at', gmdate('c'));

        $raw = ArrayPath::get($normalized, 'external.raw');
        if (is_array($raw) && !empty($raw)) {
            update_post_meta($postId, '_property_sync_payload', wp_json_encode($raw));
        }
    }

    /**
     * Save array-typed media fields.
     *
     * @param array<string,mixed> $normalized
     */
    private function saveMediaArrays(int $postId, array $normalized): void
    {
        $media = is_array($normalized['media'] ?? null) ? $normalized['media'] : [];

        $arrayFields = [
            'gallery'    => '_property_gallery',
            'videos'     => '_property_videos',
            'tour_360'   => '_property_tour_360',
            'photos_360' => '_property_photos_360',
        ];

        foreach ($arrayFields as $key => $metaKey) {
            $urls = array_filter(
                array_map('esc_url_raw', (array) ($media[$key] ?? [])),
                static fn(string $u) => $u !== ''
            );
            if (!empty($urls)) {
                update_post_meta($postId, $metaKey, array_values($urls));
            }
        }
    }

    // ─── Advisor ─────────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $normalized
     */
    private function saveAdvisor(int $postId, string $source, array $normalized): void
    {
        $advisor = is_array($normalized['advisor'] ?? null) ? $normalized['advisor'] : [];

        $hasData = ($advisor['external_id'] ?? '') !== ''
            || ($advisor['email'] ?? '') !== ''
            || ($advisor['name'] ?? '') !== '';

        if (!$hasData) {
            return;
        }

        $userId = $this->advisorSync->resolveOrCreate($source, $advisor);
        if ($userId > 0) {
            update_post_meta($postId, '_property_agent_id', $userId);
        }
    }

    // ─── Taxonomies ──────────────────────────────────────────────────────────

    /**
     * Direct term creation for non-homologated taxonomies: nearby, tags.
     *
     * @param array<string,mixed> $normalized
     */
    private function saveDirectTaxonomies(int $postId, array $normalized): void
    {
        $taxonomies = is_array($normalized['taxonomy'] ?? null) ? $normalized['taxonomy'] : [];

        $direct = [
            PropertyTaxonomies::TAXONOMY_NEARBY,
            PropertyTaxonomies::TAXONOMY_TAG,
        ];

        foreach ($direct as $taxonomy) {
            if (!isset($taxonomies[$taxonomy])) {
                continue;
            }
            $this->assignTerms($postId, $taxonomy, (array) $taxonomies[$taxonomy]);
        }
    }

    /**
     * Homologation-aware saving for operation, type, category, and features.
     *
     * @param array<string,mixed> $normalized
     */
    private function saveHomologatedTaxonomies(int $postId, string $source, array $normalized): void
    {
        $taxonomies = is_array($normalized['taxonomy'] ?? null) ? $normalized['taxonomy'] : [];

        $homologated = [
            PropertyTaxonomies::TAXONOMY_OPERATION => EntityType::OPERATION,
            PropertyTaxonomies::TAXONOMY_TYPE      => EntityType::TYPE,
            PropertyTaxonomies::TAXONOMY_CATEGORY  => EntityType::CATEGORY,
            PropertyTaxonomies::TAXONOMY_FEATURE   => EntityType::FEATURE,
        ];

        foreach ($homologated as $taxonomy => $entityType) {
            $names = array_filter(
                array_map('sanitize_text_field', (array) ($taxonomies[$taxonomy] ?? [])),
                static fn(string $v) => $v !== ''
            );
            if (!$names) {
                continue;
            }

            $termIds = [];
            foreach ($names as $name) {
                $sourceId = sanitize_title($name);
                $termId   = $this->homologation->resolveOrCreate(
                    $entityType,
                    $taxonomy,
                    $source,
                    $sourceId,
                    $name,
                );
                if ($termId > 0) {
                    $termIds[] = $termId;
                }
            }

            if ($termIds) {
                wp_set_object_terms($postId, $termIds, $taxonomy, false);
            }
        }
    }

    /**
     * Build the geo hierarchy country → state → city → neighborhood,
     * record parent term_meta at each level, and assign all resolved
     * term IDs to both their own taxonomy and property_location.
     *
     * @param array<string,mixed> $normalized
     */
    private function saveGeoHierarchy(int $postId, string $source, array $normalized): void
    {
        $taxonomies = is_array($normalized['taxonomy'] ?? null) ? $normalized['taxonomy'] : [];

        $countryName      = sanitize_text_field((string) (($taxonomies[PropertyTaxonomies::TAXONOMY_COUNTRY]      ?? [])[0] ?? ''));
        $stateName        = sanitize_text_field((string) (($taxonomies[PropertyTaxonomies::TAXONOMY_STATE]        ?? [])[0] ?? ''));
        $cityName         = sanitize_text_field((string) (($taxonomies[PropertyTaxonomies::TAXONOMY_CITY]         ?? [])[0] ?? ''));
        $neighborhoodName = sanitize_text_field((string) (($taxonomies[PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD] ?? [])[0] ?? ''));

        $locationTermIds = [];

        $countryId = 0;
        if ($countryName !== '') {
            $countryId = $this->homologation->resolveOrCreate(
                EntityType::COUNTRY,
                PropertyTaxonomies::TAXONOMY_COUNTRY,
                $source,
                sanitize_title($countryName),
                $countryName,
            );
            if ($countryId > 0) {
                wp_set_object_terms($postId, [$countryId], PropertyTaxonomies::TAXONOMY_COUNTRY, false);
                $locationTermIds[] = $countryId;
            }
        }

        $stateId = 0;
        if ($stateName !== '') {
            $stateSourceId = sanitize_title($countryName . '-' . $stateName);
            $stateId = $this->homologation->resolveOrCreate(
                EntityType::STATE,
                PropertyTaxonomies::TAXONOMY_STATE,
                $source,
                $stateSourceId,
                $stateName,
                $countryId,
            );
            if ($stateId > 0) {
                wp_set_object_terms($postId, [$stateId], PropertyTaxonomies::TAXONOMY_STATE, false);
                $locationTermIds[] = $stateId;
                if ($countryId > 0) {
                    update_term_meta($stateId, '_parent_country', $countryId);
                }
            }
        }

        $cityId = 0;
        if ($cityName !== '') {
            $citySourceId = sanitize_title($stateName . '-' . $cityName);
            $cityId = $this->homologation->resolveOrCreate(
                EntityType::CITY,
                PropertyTaxonomies::TAXONOMY_CITY,
                $source,
                $citySourceId,
                $cityName,
                $stateId,
            );
            if ($cityId > 0) {
                wp_set_object_terms($postId, [$cityId], PropertyTaxonomies::TAXONOMY_CITY, false);
                $locationTermIds[] = $cityId;
                if ($stateId > 0) {
                    update_term_meta($cityId, '_parent_state', $stateId);
                }
                if ($countryId > 0) {
                    update_term_meta($cityId, '_parent_country', $countryId);
                }
            }
        }

        if ($neighborhoodName !== '') {
            $neighborhoodSourceId = sanitize_title($cityName . '-' . $neighborhoodName);
            $neighborhoodId = $this->homologation->resolveOrCreate(
                EntityType::NEIGHBORHOOD,
                PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
                $source,
                $neighborhoodSourceId,
                $neighborhoodName,
                $cityId,
            );
            if ($neighborhoodId > 0) {
                wp_set_object_terms($postId, [$neighborhoodId], PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD, false);
                $locationTermIds[] = $neighborhoodId;
                if ($cityId > 0) {
                    update_term_meta($neighborhoodId, '_parent_city', $cityId);
                }
                if ($stateId > 0) {
                    update_term_meta($neighborhoodId, '_parent_state', $stateId);
                }
                if ($countryId > 0) {
                    update_term_meta($neighborhoodId, '_parent_country', $countryId);
                }
            }
        }

        if ($locationTermIds) {
            wp_set_object_terms($postId, $locationTermIds, PropertyTaxonomies::TAXONOMY_LOCATION, false);
        }
    }

    // ─── Sync meta ───────────────────────────────────────────────────────────

    private function saveSyncMeta(int $postId, string $externalId, string $updatedAt): void
    {
        update_post_meta($postId, '_homlity_sync_id', $externalId);
        update_post_meta($postId, '_homlity_sync_source', 'homlity-sync');
        update_post_meta($postId, '_homlity_sync_updated_at', $updatedAt !== '' ? $updatedAt : current_time('mysql'));
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * @param string[] $terms
     */
    private function assignTerms(int $postId, string $taxonomy, array $terms): void
    {
        if (!taxonomy_exists($taxonomy)) {
            return;
        }

        $terms = array_values(array_filter(
            array_map(static fn($v) => sanitize_text_field((string) $v), $terms),
            static fn(string $v) => $v !== ''
        ));

        if (!$terms) {
            return;
        }

        $termIds = $this->termResolver->resolveTermIds($taxonomy, $terms);
        if ($termIds) {
            wp_set_object_terms($postId, $termIds, $taxonomy, false);
        }
    }
}
