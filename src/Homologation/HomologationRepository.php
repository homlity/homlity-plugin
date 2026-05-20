<?php
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
namespace Homlity\PluginInmobiliario\Homologation;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database access layer for the homologation mapping table.
 *
 * Table: wp_homlity_homologation
 * Each row maps one source term (CRM/integration) to one canonical WordPress term.
 */
class HomologationRepository
{
    private const TABLE = 'homlity_homologation';
    private const CACHE_GROUP = 'homlity_homologation';

    private function cacheKey(string $suffix): string
    {
        return md5($this->table() . ':' . $suffix);
    }

    private function clearCache(): void
    {
        wp_cache_delete($this->cacheKey('sources'), self::CACHE_GROUP);
        wp_cache_delete($this->cacheKey('stats'), self::CACHE_GROUP);
    }

    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    /**
     * Find a mapping by its source coordinates.
     */
    public function findBySource(string $entityType, string $source, string $sourceId): ?object
    {
        global $wpdb;
        $cacheKey = $this->cacheKey('by_source:' . $entityType . ':' . $source . ':' . $sourceId);
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached ?: null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE entity_type = %s AND source = %s AND source_id = %s LIMIT 1",
                $entityType,
                $source,
                $sourceId
            )
        );
        wp_cache_set($cacheKey, $row ?: null, self::CACHE_GROUP, 300);

        return $row ?: null;
    }

    /**
     * Find all source mappings that point to a given canonical term.
     */
    public function findByCanonical(string $entityType, int $canonicalTermId): array
    {
        global $wpdb;
        $cacheKey = $this->cacheKey('by_canonical:' . $entityType . ':' . $canonicalTermId);
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);
        if ($cached !== false) {
            return is_array($cached) ? $cached : [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()} WHERE entity_type = %s AND canonical_term_id = %d ORDER BY source",
                $entityType,
                $canonicalTermId
            )
        ) ?: [];
        wp_cache_set($cacheKey, $rows, self::CACHE_GROUP, 300);
        return $rows;
    }

    /**
     * Insert or update a mapping. Returns the row ID.
     */
    public function upsert(
        string $entityType,
        string $source,
        string $sourceId,
        string $sourceName,
        int    $canonicalTermId,
        string $canonicalName,
        string $canonicalSlug = '',
        string $sourceSlug    = '',
    ): int {
        global $wpdb;

        $existing = $this->findBySource($entityType, $source, $sourceId);

        if ($existing !== null) {
            $wpdb->update(
                $this->table(),
                [
                    'source_name'       => $sourceName,
                    'source_slug'       => $sourceSlug,
                    'canonical_term_id' => $canonicalTermId,
                    'canonical_name'    => $canonicalName,
                    'canonical_slug'    => $canonicalSlug,
                    'updated_at'        => current_time('mysql'),
                ],
                [
                    'entity_type' => $entityType,
                    'source'      => $source,
                    'source_id'   => $sourceId,
                ],
                ['%s', '%s', '%d', '%s', '%s', '%s'],
                ['%s', '%s', '%s']
            );
            $this->clearCache();

            return (int) $existing->id;
        }

        $wpdb->insert(
            $this->table(),
            [
                'entity_type'       => $entityType,
                'source'            => $source,
                'source_id'         => $sourceId,
                'source_name'       => $sourceName,
                'source_slug'       => $sourceSlug,
                'canonical_term_id' => $canonicalTermId,
                'canonical_name'    => $canonicalName,
                'canonical_slug'    => $canonicalSlug,
                'created_at'        => current_time('mysql'),
                'updated_at'        => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );
        $this->clearCache();

        return (int) $wpdb->insert_id;
    }

    /**
     * Delete a mapping by its primary key.
     */
    public function delete(int $id): bool
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $deleted = (bool) $wpdb->delete($this->table(), ['id' => $id], ['%d']);
        if ($deleted) {
            $this->clearCache();
        }
        return $deleted;
    }

    /**
     * Paginated list of mappings with optional filters.
     *
     * @return object[]
     */
    public function getAll(
        ?string $entityType = null,
        ?string $source     = null,
        int     $page       = 1,
        int     $perPage    = 50,
    ): array {
        global $wpdb;
        $table = esc_sql( $this->table() );

        $offset = max(0, ($page - 1) * $perPage);
        if ($entityType !== null && $entityType !== '' && $source !== null && $source !== '') {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE entity_type = %s AND source = %s ORDER BY entity_type, source, canonical_name LIMIT %d OFFSET %d",
                    $entityType,
                    $source,
                    $perPage,
                    $offset
                )
            ) ?: [];
        }
        if ($entityType !== null && $entityType !== '') {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE entity_type = %s ORDER BY entity_type, source, canonical_name LIMIT %d OFFSET %d",
                    $entityType,
                    $perPage,
                    $offset
                )
            ) ?: [];
        }
        if ($source !== null && $source !== '') {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE source = %s ORDER BY entity_type, source, canonical_name LIMIT %d OFFSET %d",
                    $source,
                    $perPage,
                    $offset
                )
            ) ?: [];
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY entity_type, source, canonical_name LIMIT %d OFFSET %d",
                $perPage,
                $offset
            )
        ) ?: [];
    }

    /**
     * Total count with the same optional filters.
     */
    public function getTotal(?string $entityType = null, ?string $source = null): int
    {
        global $wpdb;
        $table = esc_sql( $this->table() );
        if ($entityType !== null && $entityType !== '' && $source !== null && $source !== '') {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE entity_type = %s AND source = %s",
                    $entityType,
                    $source
                )
            );
        }
        if ($entityType !== null && $entityType !== '') {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE entity_type = %s",
                    $entityType
                )
            );
        }
        if ($source !== null && $source !== '') {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE source = %s",
                    $source
                )
            );
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    /**
     * All distinct source identifiers registered in the table.
     *
     * @return string[]
     */
    public function getSources(): array
    {
        global $wpdb;
        $cacheKey = $this->cacheKey('sources');
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);
        if ($cached !== false) {
            return is_array($cached) ? $cached : [];
        }

        $rows = $wpdb->get_col(
            "SELECT DISTINCT source FROM {$this->table()} ORDER BY source"
        ) ?: [];
        wp_cache_set($cacheKey, $rows, self::CACHE_GROUP, 300);
        return $rows;
    }

    /**
     * Aggregated count per entity_type × source — used for admin stats cards.
     *
     * @return array<string, array<string, int>>  $stats[$entityType][$source] = count
     */
    public function getStats(): array
    {
        global $wpdb;
        $cacheKey = $this->cacheKey('stats');
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);
        if ($cached !== false) {
            return is_array($cached) ? $cached : [];
        }

        $rows = $wpdb->get_results(
            "SELECT entity_type, source, COUNT(*) AS total
             FROM {$this->table()}
             GROUP BY entity_type, source
             ORDER BY entity_type, source"
        ) ?: [];

        $stats = [];

        foreach ($rows as $row) {
            $stats[$row->entity_type][$row->source] = (int) $row->total;
        }

        wp_cache_set($cacheKey, $stats, self::CACHE_GROUP, 300);
        return $stats;
    }

    /**
     * Creates the homologation table. Safe to call multiple times (IF NOT EXISTS).
     * Should be called on plugin activation.
     */
    public static function createTable(): void
    {
        global $wpdb;

        $table          = $wpdb->prefix . self::TABLE;
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type varchar(50) NOT NULL,
            canonical_term_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            canonical_name varchar(255) NOT NULL DEFAULT '',
            canonical_slug varchar(255) NOT NULL DEFAULT '',
            source varchar(100) NOT NULL,
            source_id varchar(255) NOT NULL,
            source_name varchar(255) NOT NULL DEFAULT '',
            source_slug varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_source_entity (entity_type, source, source_id(191)),
            KEY idx_canonical (entity_type, canonical_term_id),
            KEY idx_source_lookup (source, entity_type)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /** @return array{string, array} */
    private function buildWhere(?string $entityType, ?string $source): array
    {
        $conditions = [];
        $params     = [];

        if ($entityType !== null && $entityType !== '') {
            $conditions[] = 'entity_type = %s';
            $params[]     = $entityType;
        }

        if ($source !== null && $source !== '') {
            $conditions[] = 'source = %s';
            $params[]     = $source;
        }

        $whereSql = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return [$whereSql, $params];
    }
}
