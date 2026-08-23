<?php

declare(strict_types=1);

/**
 * Emits the public property lifecycle hooks of the Homlity Developer API.
 *
 * Internal: this class is not part of the public contract. Extensions listen
 * to the hooks it fires, they do not call it.
 */

namespace Homlity\PluginInmobiliario\Core;

use Homlity\Developer\Events\PropertyChanges;
use Homlity\Developer\Events\PropertyContext;
use Homlity\Developer\Models\Property;
use Homlity\Developer\Support\Hooks;
use Homlity\PluginInmobiliario\Integrations\CRM\FieldMap\PropertyFieldSchema;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Turns "a property was written" into the documented public actions.
 *
 * There is exactly one rule here and everything else follows from it: a hook
 * fires only after the write has fully completed — post row, meta, taxonomies,
 * media and advisor — so that the {@see Property} handed to listeners is the
 * state that was actually persisted, never a half-written one.
 *
 * @internal
 */
final class PropertyEventDispatcher
{
    /**
     * Canonical fields that are never diffed nor exposed in a change set.
     *
     * They are the owner's personal data captured by the consignment form.
     * Publishing them to every listening extension would turn a lifecycle
     * hook into a data leak.
     *
     * @var string[]
     */
    private const PRIVATE_FIELDS = [
        'external.raw.identification',
        'external.raw.contact_name',
        'external.raw.contact_email',
        'external.raw.contact_phone',
        'external.raw.contact_whatsapp',
        'external.raw.consignant_type',
        'external.raw.data_consent',
        'external.raw.authorization_consent',
        'external.raw.truth_declaration',
        'external.raw.contact_consent',
    ];

    /**
     * Pre-write snapshots taken in this request, keyed by post ID.
     *
     * @var array<int,array<string,mixed>>
     */
    private static array $snapshots = [];

    /**
     * Capture the canonical state of a property before it is written.
     *
     * Returns an empty array for a property that does not exist yet.
     *
     * @return array<string,mixed>
     */
    public static function snapshot(int $postId): array
    {
        if ($postId <= 0) {
            return [];
        }

        $post = get_post($postId);
        if (!$post instanceof \WP_Post || $post->post_type !== PropertyPostType::POST_TYPE) {
            return [];
        }

        $state = [
            'post.title'             => (string) $post->post_title,
            'post.description'       => (string) $post->post_content,
            'post.short_description' => (string) $post->post_excerpt,
            'post.status'            => (string) $post->post_status,
        ];

        foreach (PropertyFieldSchema::metaMap() as $path => $metaKey) {
            if (in_array($path, self::PRIVATE_FIELDS, true)) {
                continue;
            }

            $state[$path] = self::scalarMeta($postId, $metaKey);
        }

        // Availability is not part of the CRM field map but it is what decides
        // whether a property is on the market, so a change to it must be visible.
        $state['availability.status']    = self::scalarMeta($postId, '_property_status');
        $state['availability.available'] = self::scalarMeta($postId, '_property_available');

        // Media arrays and taxonomies are compared through a stable signature:
        // listeners get "the gallery changed", and read the new one off the model.
        foreach (['gallery', 'videos', 'tour_360', 'photos_360'] as $mediaKey) {
            $state['media.' . $mediaKey] = self::mediaSignature($postId, '_property_' . $mediaKey);
        }

        foreach (self::trackedTaxonomies() as $taxonomy) {
            $state['taxonomy.' . $taxonomy] = self::taxonomySignature($postId, $taxonomy);
        }

        return $state;
    }

    /**
     * Remember a pre-write snapshot for a post so a later write can diff it.
     */
    public static function rememberSnapshot(int $postId): void
    {
        if ($postId > 0 && !isset(self::$snapshots[$postId])) {
            self::$snapshots[$postId] = self::snapshot($postId);
        }
    }

    /**
     * Take back the snapshot remembered for a post, if any.
     *
     * @return array<string,mixed>|null
     */
    public static function takeSnapshot(int $postId): ?array
    {
        if (!isset(self::$snapshots[$postId])) {
            return null;
        }

        $snapshot = self::$snapshots[$postId];
        unset(self::$snapshots[$postId]);

        return $snapshot;
    }

    /**
     * Announce that a property finished being written.
     *
     * @param int                 $postId  Property post ID.
     * @param array<string,mixed> $before  State captured by {@see self::snapshot()} before the write.
     * @param PropertyContext     $context Why and from where the write happened.
     */
    public static function dispatchSaved(int $postId, array $before, PropertyContext $context): void
    {
        // Drop any snapshot left over from `pre_post_update` for this post so
        // it cannot be mistaken for the previous state of a later write.
        self::takeSnapshot($postId);

        $property = self::buildProperty($postId);
        if ($property === null) {
            return;
        }

        $changes = PropertyChanges::diff($before, self::snapshot($postId));

        if ($context->isNew()) {
            /**
             * Fires after a new Homlity property has been created and fully written.
             *
             * Every meta value, taxonomy term, gallery image and advisor
             * assignment is already persisted when this runs.
             *
             * @since 2.8.0
             *
             * @param Property        $property Created property.
             * @param PropertyContext $context  Origin of the write.
             */
            do_action(Hooks::PROPERTY_CREATED, $property, $context);
        } else {
            /**
             * Fires after an existing Homlity property has been updated.
             *
             * The change set may be empty when a CRM re-sends an unchanged
             * record; check `$changes->isEmpty()` before doing expensive work.
             *
             * @since 2.8.0
             *
             * @param Property         $property Updated property.
             * @param PropertyChanges  $changes  Canonical fields that changed.
             * @param PropertyContext  $context  Origin of the write.
             */
            do_action(Hooks::PROPERTY_UPDATED, $property, $changes, $context);
        }

        if ($context->isExternal()) {
            /**
             * Fires after a property has been written by an external source.
             *
             * Runs in addition to `homlity/property/created` or
             * `homlity/property/updated`, never instead of them. Use
             * `$context->getSource()` to tell which CRM the record came from,
             * and `$context->isNew()` to tell an import from a refresh.
             *
             * @since 2.8.0
             *
             * @param Property        $property Synchronized property.
             * @param PropertyChanges $changes  Canonical fields that changed.
             * @param PropertyContext $context  Origin of the write.
             */
            do_action(Hooks::PROPERTY_SYNCHRONIZED, $property, $changes, $context);
        }

        if ($changes->has('media.gallery')) {
            /**
             * Fires when the image gallery of a property changed.
             *
             * Read the new gallery from `$property->getImages()`. The previous
             * one is available as `$changes->previous('media.gallery')`, an
             * opaque signature string meant only for comparison.
             *
             * @since 2.8.0
             *
             * @param Property        $property Property whose gallery changed.
             * @param PropertyChanges $changes  Change set, including 'media.gallery'.
             * @param PropertyContext $context  Origin of the write.
             */
            do_action(Hooks::PROPERTY_IMAGES_CHANGED, $property, $changes, $context);
        }
    }

    /**
     * Announce that a property is about to be permanently deleted.
     *
     * Fired on `before_delete_post`, while the post and its meta still exist,
     * so listeners can read the property one last time — to remove it from a
     * portal, for instance.
     */
    public static function dispatchDeleted(int $postId): void
    {
        $property = self::buildProperty($postId);
        if ($property === null) {
            return;
        }

        /**
         * Fires just before a Homlity property is permanently deleted.
         *
         * The post still exists at this point; it will not by the time the
         * request ends. Moving a property to the trash does not fire this —
         * listen to `homlity/property/status_changed` for that.
         *
         * @since 2.8.0
         *
         * @param Property $property Property about to be deleted.
         * @param int      $postId   Its post ID, for convenience.
         */
        do_action(Hooks::PROPERTY_DELETED, $property, $postId);
    }

    /**
     * Announce a WordPress post-status transition on a property.
     */
    public static function dispatchStatusChanged(string $newStatus, string $oldStatus, int $postId): void
    {
        // 'new' means the post is being created; `property/created` covers it.
        if ($oldStatus === 'new' || $newStatus === $oldStatus) {
            return;
        }

        $property = self::buildProperty($postId);
        if ($property === null) {
            return;
        }

        /**
         * Fires when the WordPress post status of a property changes.
         *
         * Covers publishing, unpublishing, trashing and restoring, whoever
         * caused it — wp-admin, a CRM sync, WP-CLI or another plugin.
         *
         * @since 2.8.0
         *
         * @param Property $property  Property whose status changed.
         * @param string   $newStatus New post status.
         * @param string   $oldStatus Previous post status.
         */
        do_action(Hooks::PROPERTY_STATUS_CHANGED, $property, $newStatus, $oldStatus);
    }

    /**
     * Forget every remembered snapshot.
     *
     * @internal Exists for the test suite.
     */
    public static function reset(): void
    {
        self::$snapshots = [];
    }

    // ─── Internals ───────────────────────────────────────────────────────

    private static function buildProperty(int $postId): ?Property
    {
        if ($postId <= 0) {
            return null;
        }

        return \Homlity\Developer\Homlity::properties()->find($postId);
    }

    /**
     * @return string[]
     */
    private static function trackedTaxonomies(): array
    {
        return [
            PropertyTaxonomies::TAXONOMY_OPERATION,
            PropertyTaxonomies::TAXONOMY_TYPE,
            PropertyTaxonomies::TAXONOMY_CATEGORY,
            PropertyTaxonomies::TAXONOMY_FEATURE,
            PropertyTaxonomies::TAXONOMY_CITY,
            PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
        ];
    }

    private static function scalarMeta(int $postId, string $metaKey): string
    {
        $value = get_post_meta($postId, $metaKey, true);

        if (is_array($value)) {
            return (string) wp_json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private static function mediaSignature(int $postId, string $metaKey): string
    {
        $value = get_post_meta($postId, $metaKey, true);

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value   = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $value)));
        }

        $parts = [];
        foreach ((array) $value as $entry) {
            if (is_array($entry)) {
                $entry = $entry['url'] ?? ($entry['src'] ?? ($entry['full'] ?? ''));
            }
            if (is_scalar($entry) && trim((string) $entry) !== '') {
                $parts[] = trim((string) $entry);
            }
        }

        return implode('|', $parts);
    }

    private static function taxonomySignature(int $postId, string $taxonomy): string
    {
        $terms = get_the_terms($postId, $taxonomy);
        if (!is_array($terms)) {
            return '';
        }

        $slugs = [];
        foreach ($terms as $term) {
            if (is_object($term) && isset($term->slug)) {
                $slugs[] = (string) $term->slug;
            }
        }

        sort($slugs);

        return implode('|', $slugs);
    }
}
