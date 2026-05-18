<?php

namespace Homlity\PluginInmobiliario\Homologation;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Central homologation service for the Homlity Plugin.
 *
 * This service is the single source of truth that maps external data
 * (from any CRM or integration) to canonical WordPress taxonomy terms.
 *
 * When you connect a new CRM or switch between CRMs, all data flows
 * through this service so that terms remain consistent across sources.
 *
 * Usage from an external plugin/adapter:
 *
 *   if (class_exists('\Homlity\PluginInmobiliario\Homologation\HomologationService')) {
 *       $svc = new \Homlity\PluginInmobiliario\Homologation\HomologationService();
 *       $termId = $svc->resolveOrCreate('city', 'property_city', 'my_crm', 'crm-id-123', 'Medellín', $parentId);
 *   }
 */
class HomologationService
{
    public function __construct(
        private readonly HomologationRepository $repository = new HomologationRepository(),
    ) {}

    /**
     * Resolve a source term ID to its canonical WordPress term_id.
     * Returns null if no mapping exists yet.
     */
    public function resolveToCanonical(string $entityType, string $source, string $sourceId): ?int
    {
        $row = $this->repository->findBySource($entityType, $source, $sourceId);

        if ($row === null) {
            return null;
        }

        $termId = (int) $row->canonical_term_id;

        return $termId > 0 ? $termId : null;
    }

    /**
     * Register (or update) a source → canonical mapping explicitly.
     *
     * Use this when you already have the canonical term_id and just want
     * to record the mapping (e.g. manual admin overrides).
     *
     * @return int The homologation row ID.
     */
    public function register(
        string $entityType,
        string $source,
        string $sourceId,
        string $sourceName,
        int    $canonicalTermId,
        string $canonicalName  = '',
        string $canonicalSlug  = '',
        string $sourceSlug     = '',
    ): int {
        if ($canonicalName === '') {
            $term = get_term($canonicalTermId);

            if ($term instanceof \WP_Term) {
                $canonicalName = $term->name;
                $canonicalSlug = $term->slug;
            }
        }

        return $this->repository->upsert(
            entityType:      $entityType,
            source:          $source,
            sourceId:        $sourceId,
            sourceName:      $sourceName,
            canonicalTermId: $canonicalTermId,
            canonicalName:   $canonicalName,
            canonicalSlug:   $canonicalSlug,
            sourceSlug:      $sourceSlug,
        );
    }

    /**
     * Main workhorse for CRM adapters and sync plugins.
     *
     * Given a source term, returns the canonical WordPress term_id — creating
     * the term and registering the mapping if neither exists yet.
     *
     * Resolution order:
     *  1. Lookup homologation table by (entityType, source, sourceId)
     *  2. Verify the resolved term still exists in WordPress
     *  3. If not found, search WordPress terms by name + parent
     *  4. If still not found, create the WordPress term
     *  5. Register the mapping and return the term_id
     *
     * @param string $entityType   One of the EntityType::* constants
     * @param string $taxonomy     WordPress taxonomy slug (e.g. 'property_city')
     * @param string $source       Source key identifying the external system (e.g. 'homlity_web')
     * @param string $sourceId     Stable ID of the term in the source system
     * @param string $sourceName   Human-readable name from the source system
     * @param int    $parentTermId WordPress term_id of the parent (0 = root)
     *
     * @return int WordPress term_id, or 0 on failure.
     */
    public function resolveOrCreate(
        string $entityType,
        string $taxonomy,
        string $source,
        string $sourceId,
        string $sourceName,
        int    $parentTermId = 0,
    ): int {
        // 1. Try to resolve via existing mapping
        if ($sourceId !== '') {
            $canonicalId = $this->resolveToCanonical($entityType, $source, $sourceId);

            if ($canonicalId !== null) {
                $term = get_term($canonicalId, $taxonomy);

                if ($term instanceof \WP_Term) {
                    return $canonicalId;
                }

                // Term was deleted in WordPress — remove stale mapping so we recreate
                $staleRow = $this->repository->findBySource($entityType, $source, $sourceId);
                if ($staleRow !== null) {
                    $this->repository->delete((int) $staleRow->id);
                }
            }
        }

        // 2. Search for the term in WordPress by name (respecting hierarchy)
        $termId = $this->findTermByName($sourceName, $taxonomy, $parentTermId);

        // 3. Create the term if it does not exist
        if ($termId === 0) {
            $termId = $this->createTerm($sourceName, $taxonomy, $parentTermId);
        }

        if ($termId === 0) {
            return 0;
        }

        // 4. Register the mapping so future lookups are instant
        $term = get_term($termId, $taxonomy);

        $this->repository->upsert(
            entityType:      $entityType,
            source:          $source,
            sourceId:        $sourceId !== '' ? $sourceId : sanitize_title($sourceName),
            sourceName:      $sourceName,
            canonicalTermId: $termId,
            canonicalName:   $term instanceof \WP_Term ? $term->name : $sourceName,
            canonicalSlug:   $term instanceof \WP_Term ? $term->slug : '',
        );

        return $termId;
    }

    // ─── Admin helpers ────────────────────────────────────────────────────────

    /**
     * @return object[]
     */
    public function getMappings(
        ?string $entityType = null,
        ?string $source     = null,
        int     $page       = 1,
        int     $perPage    = 50,
    ): array {
        return $this->repository->getAll($entityType, $source, $page, $perPage);
    }

    public function getTotal(?string $entityType = null, ?string $source = null): int
    {
        return $this->repository->getTotal($entityType, $source);
    }

    /** @return string[] */
    public function getSources(): array
    {
        return $this->repository->getSources();
    }

    /** @return array<string, array<string, int>> */
    public function getStats(): array
    {
        return $this->repository->getStats();
    }

    public function deleteMapping(int $id): bool
    {
        return $this->repository->delete($id);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function findTermByName(string $name, string $taxonomy, int $parent = 0): int
    {
        if (!taxonomy_exists($taxonomy)) {
            return 0;
        }

        // Exact match with parent constraint
        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'name'       => $name,
            'parent'     => $parent,
            'hide_empty' => false,
            'number'     => 1,
        ]);

        if (!is_wp_error($terms) && !empty($terms)) {
            return (int) $terms[0]->term_id;
        }

        // For root-level terms, also try without parent restriction
        if ($parent === 0) {
            $existing = get_term_by('name', $name, $taxonomy);
            if ($existing instanceof \WP_Term) {
                return $existing->term_id;
            }
        }

        return 0;
    }

    private function createTerm(string $name, string $taxonomy, int $parent = 0): int
    {
        if (!taxonomy_exists($taxonomy)) {
            return 0;
        }

        $args = [];
        if ($parent > 0) {
            $args['parent'] = $parent;
        }

        $result = wp_insert_term($name, $taxonomy, $args);

        if (is_wp_error($result)) {
            // term_exists → WordPress returns the existing term_id in the error data
            if ($result->get_error_code() === 'term_exists') {
                $existingId = $result->get_error_data();
                return is_numeric($existingId) ? (int) $existingId : 0;
            }

            return 0;
        }

        return (int) ($result['term_id'] ?? 0);
    }
}
