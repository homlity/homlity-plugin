<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM;

use Homlity\PluginInmobiliario\Integrations\CRM\FieldMap\PropertyFieldSchema;
use Homlity\PluginInmobiliario\Integrations\CRM\Repository\SyncIndexRepository;
use Homlity\PluginInmobiliario\Integrations\CRM\Support\ArrayPath;
use Homlity\PluginInmobiliario\Integrations\CRM\Support\TaxonomyTermResolver;
use Homlity\PluginInmobiliario\Services\PropertyPostType;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyUpsertService
{
    private TaxonomyTermResolver $termResolver;
    private SyncIndexRepository $indexRepository;

    public function __construct()
    {
        $this->termResolver = new TaxonomyTermResolver();
        $this->indexRepository = new SyncIndexRepository();
    }

    /**
     * @param array<string,mixed> $normalized
     * @return array<string,mixed>
     */
    public function upsert(array $normalized): array
    {
        $external = is_array($normalized['external'] ?? null) ? $normalized['external'] : [];
        $source = sanitize_key((string) ($external['source'] ?? ''));
        $externalId = sanitize_text_field((string) ($external['id'] ?? ''));

        if ($source === '' || $externalId === '') {
            return ['ok' => false, 'error' => 'Missing external.source or external.id'];
        }

        $postId = $this->findByExternal($source, $externalId);
        $postData = is_array($normalized['post'] ?? null) ? $normalized['post'] : [];

        $title = sanitize_text_field((string) ($postData['title'] ?? ''));
        if ($title === '') {
            return ['ok' => false, 'error' => 'Missing post.title'];
        }

        $payload = [
            'post_type' => PropertyPostType::POST_TYPE,
            'post_title' => $title,
            'post_content' => wp_kses_post((string) ($postData['description'] ?? '')),
            'post_excerpt' => sanitize_textarea_field((string) ($postData['short_description'] ?? '')),
            'post_status' => $this->normalizePostStatus((string) ($postData['status'] ?? 'publish')),
        ];

        if ($postId > 0) {
            $payload['ID'] = $postId;
            $postId = (int) wp_update_post($payload, true);
        } else {
            $postId = (int) wp_insert_post($payload, true);
        }

        if (is_wp_error($postId) || $postId <= 0) {
            return ['ok' => false, 'error' => is_wp_error($postId) ? $postId->get_error_message() : 'Unable to save post'];
        }

        $this->saveMeta($postId, $normalized, $source, $externalId);
        $this->saveTaxonomies($postId, $normalized);
        $this->indexRepository->upsert(
            $source,
            $externalId,
            $postId,
            sanitize_text_field((string) (ArrayPath::get($normalized, 'external.hash') ?? '')),
            'synced',
            sanitize_text_field((string) (ArrayPath::get($normalized, 'external.updated_at') ?? ''))
        );

        return ['ok' => true, 'post_id' => $postId];
    }

    private function normalizePostStatus(string $status): string
    {
        $status = sanitize_key($status);
        return in_array($status, ['publish', 'draft', 'pending', 'private'], true) ? $status : 'publish';
    }

    private function findByExternal(string $source, string $externalId): int
    {
        $indexedPostId = $this->indexRepository->findPostId($source, $externalId);
        if ($indexedPostId > 0) {
            return $indexedPostId;
        }

        $query = new \WP_Query([
            'post_type' => PropertyPostType::POST_TYPE,
            'post_status' => ['publish', 'pending', 'draft', 'private'],
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'AND',
                ['key' => '_property_external_source', 'value' => $source],
                ['key' => '_property_external_id', 'value' => $externalId],
            ],
            'no_found_rows' => true,
        ]);

        if (empty($query->posts)) {
            return 0;
        }

        return (int) $query->posts[0];
    }

    /**
     * @param array<string,mixed> $normalized
     */
    private function saveMeta(int $postId, array $normalized, string $source, string $externalId): void
    {
        foreach (PropertyFieldSchema::metaMap() as $path => $metaKey) {
            $value = ArrayPath::get($normalized, $path);
            if ($value === null || $value === '') {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? '1' : '0';
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
     * @param array<string,mixed> $normalized
     */
    private function saveTaxonomies(int $postId, array $normalized): void
    {
        $taxonomies = is_array($normalized['taxonomy'] ?? null) ? $normalized['taxonomy'] : [];
        foreach ($taxonomies as $taxonomy => $terms) {
            if (!taxonomy_exists((string) $taxonomy)) {
                continue;
            }

            $terms = is_array($terms) ? $terms : [$terms];
            $terms = array_values(array_filter(array_map(static fn($v) => sanitize_text_field((string) $v), $terms)));
            if (!$terms) {
                continue;
            }

            $termIds = $this->termResolver->resolveTermIds((string) $taxonomy, $terms);
            if (!$termIds) {
                continue;
            }

            wp_set_object_terms($postId, $termIds, (string) $taxonomy, false);
        }
    }
}
