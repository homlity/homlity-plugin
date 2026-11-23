<?php

namespace Homlity\PluginInmobiliario\Search;

use Homlity\Developer\Support\Hooks;
use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

if (!defined('ABSPATH')) {
    exit;
}

/** Coalesces all writes for one request into one index operation at shutdown. */
final class PropertyIndexSync implements ServiceInterface
{
    /** @var array<int,true> */
    private array $queued = [];

    /** @var array<int,true> */
    private array $deleted = [];

    public function register(): void
    {
        add_action('save_post_' . PropertyPostType::POST_TYPE, [$this, 'queuePost'], 99, 3);
        add_action('transition_post_status', [$this, 'queueTransition'], 99, 3);
        add_action('deleted_post', [$this, 'queueDeleted'], 99);
        add_action('trashed_post', [$this, 'queuePostId'], 99);
        add_action('untrashed_post', [$this, 'queuePostId'], 99);
        add_action('added_post_meta', [$this, 'queueMeta'], 99, 4);
        add_action('updated_post_meta', [$this, 'queueMeta'], 99, 4);
        add_action('deleted_post_meta', [$this, 'queueMeta'], 99, 4);
        add_action('set_object_terms', [$this, 'queueTerms'], 99, 4);
        add_action('edited_term', [$this, 'queueEditedTerm'], 99, 3);
        add_action(Hooks::PROPERTY_CREATED, [$this, 'queueLifecycle'], 99, 2);
        add_action(Hooks::PROPERTY_UPDATED, [$this, 'queueLifecycle'], 99, 3);
        add_action(Hooks::PROPERTY_SYNCHRONIZED, [$this, 'queueLifecycle'], 99, 3);
        add_action(Hooks::PROPERTY_STATUS_CHANGED, [$this, 'queueLifecycle'], 99, 3);
        add_action('shutdown', [$this, 'flush']);
    }

    public function queuePost(int $postId, $post = null, bool $update = false): void
    {
        if ($postId > 0) {
            $this->queue($postId);
        }
    }

    public function queueTransition(string $newStatus, string $oldStatus, $post): void
    {
        $postId = is_object($post) ? (int) ($post->ID ?? 0) : (int) $post;
        if ($postId > 0 && (get_post_type($postId) === PropertyPostType::POST_TYPE || is_object($post) && ($post->post_type ?? '') === PropertyPostType::POST_TYPE)) {
            $this->queue($postId);
        }
    }

    public function queueDeleted(int $postId): void
    {
        if ($postId > 0) {
            $this->deleted[$postId] = true;
        }
    }

    public function queuePostId(int $postId): void
    {
        if ($postId > 0 && get_post_type($postId) === PropertyPostType::POST_TYPE) {
            $this->queue($postId);
        }
    }

    public function queueMeta($metaId, int $postId, string $metaKey, $metaValue = null): void
    {
        if ($this->isIndexedMeta($metaKey) && get_post_type($postId) === PropertyPostType::POST_TYPE) {
            $this->queue($postId);
        }
    }

    public function queueTerms(int $objectId, $terms, $ttIds, string $taxonomy): void
    {
        if (in_array($taxonomy, [
            PropertyTaxonomies::TAXONOMY_FEATURE,
            PropertyTaxonomies::TAXONOMY_CITY,
            PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD,
        ], true) && get_post_type($objectId) === PropertyPostType::POST_TYPE) {
            $this->queue($objectId);
        }
    }

    public function queueLifecycle($property, $changes = null, $context = null): void
    {
        $postId = is_object($property) && method_exists($property, 'getId')
            ? (int) $property->getId()
            : (int) ($property->ID ?? 0);
        if ($postId > 0) {
            $this->queue($postId);
        }
    }

    public function queueEditedTerm(int $termId, int $ttId, string $taxonomy): void
    {
        if (!in_array($taxonomy, [PropertyTaxonomies::TAXONOMY_FEATURE, PropertyTaxonomies::TAXONOMY_CITY, PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD], true)) return;
        $ids = array_map('absint', (array) get_objects_in_term($termId, $taxonomy));
        if (count($ids) > 200) {
            (new PropertyIndexRebuilder())->scheduleIds($ids);
            return;
        }
        foreach ($ids as $id) $this->queue($id);
    }

    public function flush(): void
    {
        if ($this->queued === [] && $this->deleted === []) {
            return;
        }
        $deleted = array_map('intval', array_keys($this->deleted));
        $ids = array_map('intval', array_keys($this->queued));
        $this->queued = [];
        $this->deleted = [];
        if ((int) get_option(PropertyIndexSchema::SCHEMA_VERSION_OPTION, 0) !== PropertyIndexSchema::SCHEMA_VERSION) {
            if ($ids !== []) {
                (new PropertyIndexRebuilder())->scheduleIds($ids);
            }
            return;
        }
        $indexer = new PropertyIndexer();
        $indexer->delete($deleted);
        $indexer->indexMany(array_diff($ids, $deleted));
    }

    private function queue(int $postId): void
    {
        $this->queued[$postId] = true;
    }

    private function isIndexedMeta(string $metaKey): bool
    {
        return in_array($metaKey, [
            '_property_status', '_property_available', '_property_price_sale', '_property_price_rent',
            '_property_bedrooms', '_property_bathrooms', '_property_parking', '_property_area',
            '_property_latitude', '_property_longitude', '_property_featured', '_property_agent_id',
            '_property_code', '_homlity_sync_id',
        ], true);
    }
}
