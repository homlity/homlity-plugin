<?php

namespace Homlity\PluginInmobiliario\Homologation;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API endpoints for the homologation system.
 *
 * Namespace: homlity/v1
 *
 * GET    /homologation/stats                  Stats per entity type × source
 * GET    /homologation/sources                Distinct source keys
 * GET    /homologation/mappings               Paginated list (filter: entity_type, source)
 * POST   /homologation/mappings               Create a manual mapping
 * DELETE /homologation/mappings/{id}          Remove a mapping
 * GET    /homologation/canonical/{entity_type} WordPress terms for an entity type
 * POST   /homologation/resolve                Resolve source → canonical term_id
 */
class HomologationRestController implements ServiceInterface
{
    private const NAMESPACE = 'homlity/v1';

    public function __construct(
        private readonly HomologationService $service = new HomologationService(),
    ) {}

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/homologation/stats', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getStats'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route(self::NAMESPACE, '/homologation/sources', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getSources'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route(self::NAMESPACE, '/homologation/mappings', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'getMappings'],
                'permission_callback' => fn() => current_user_can('manage_options'),
                'args'                => [
                    'entity_type' => ['type' => 'string', 'default' => ''],
                    'source'      => ['type' => 'string', 'default' => ''],
                    'page'        => ['type' => 'integer', 'default' => 1, 'minimum' => 1],
                    'per_page'    => ['type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 200],
                ],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'createMapping'],
                'permission_callback' => fn() => current_user_can('manage_options'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/homologation/mappings/(?P<id>\d+)', [
            'methods'             => \WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'deleteMapping'],
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args'                => [
                'id' => ['type' => 'integer', 'required' => true, 'minimum' => 1],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/homologation/canonical/(?P<entity_type>[a-z_]+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getCanonicalTerms'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route(self::NAMESPACE, '/homologation/resolve', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'resolve'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    public function getStats(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response(['data' => $this->service->getStats()], 200);
    }

    public function getSources(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response(['data' => $this->service->getSources()], 200);
    }

    public function getMappings(\WP_REST_Request $request): \WP_REST_Response
    {
        $entityType = sanitize_text_field($request->get_param('entity_type') ?? '');
        $source     = sanitize_text_field($request->get_param('source') ?? '');
        $page       = max(1, (int) ($request->get_param('page') ?? 1));
        $perPage    = min(200, max(1, (int) ($request->get_param('per_page') ?? 50)));

        $mappings = $this->service->getMappings(
            $entityType !== '' ? $entityType : null,
            $source !== '' ? $source : null,
            $page,
            $perPage,
        );

        $total = $this->service->getTotal(
            $entityType !== '' ? $entityType : null,
            $source !== '' ? $source : null,
        );

        return new \WP_REST_Response([
            'data'     => $mappings,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ], 200);
    }

    public function createMapping(\WP_REST_Request $request): \WP_REST_Response
    {
        $entityType     = sanitize_text_field($request->get_param('entity_type') ?? '');
        $source         = sanitize_text_field($request->get_param('source') ?? '');
        $sourceId       = sanitize_text_field($request->get_param('source_id') ?? '');
        $sourceName     = sanitize_text_field($request->get_param('source_name') ?? '');
        $canonicalTermId = (int) ($request->get_param('canonical_term_id') ?? 0);

        if (!EntityType::isValid($entityType)) {
            return new \WP_REST_Response(
                ['error' => __('Tipo de entidad no válido.', 'homlity-real-estate')],
                400
            );
        }

        if (empty($source) || empty($sourceId) || $canonicalTermId <= 0) {
            return new \WP_REST_Response(
                ['error' => __('Parámetros incompletos: source, source_id y canonical_term_id son obligatorios.', 'homlity-real-estate')],
                400
            );
        }

        $id = $this->service->register($entityType, $source, $sourceId, $sourceName, $canonicalTermId);

        return new \WP_REST_Response(['id' => $id], 201);
    }

    public function deleteMapping(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        if (!$this->service->deleteMapping($id)) {
            return new \WP_REST_Response(
                ['error' => __('No se encontró el mapeo o no se pudo eliminar.', 'homlity-real-estate')],
                404
            );
        }

        return new \WP_REST_Response(['deleted' => true], 200);
    }

    public function getCanonicalTerms(\WP_REST_Request $request): \WP_REST_Response
    {
        $entityType = sanitize_text_field($request->get_param('entity_type') ?? '');
        $taxonomy   = EntityType::taxonomy($entityType);

        if ($taxonomy === null) {
            return new \WP_REST_Response(
                ['error' => __('Tipo de entidad sin taxonomía asociada.', 'homlity-real-estate')],
                400
            );
        }

        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);

        if (is_wp_error($terms)) {
            return new \WP_REST_Response(['data' => []], 200);
        }

        $data = array_map(static fn($t) => [
            'term_id' => (int) $t->term_id,
            'name'    => $t->name,
            'slug'    => $t->slug,
            'parent'  => (int) $t->parent,
            'count'   => (int) $t->count,
        ], $terms);

        return new \WP_REST_Response(['data' => array_values($data)], 200);
    }

    public function resolve(\WP_REST_Request $request): \WP_REST_Response
    {
        $entityType = sanitize_text_field($request->get_param('entity_type') ?? '');
        $source     = sanitize_text_field($request->get_param('source') ?? '');
        $sourceId   = sanitize_text_field($request->get_param('source_id') ?? '');

        if (empty($entityType) || empty($source) || empty($sourceId)) {
            return new \WP_REST_Response(
                ['error' => __('Se requieren entity_type, source y source_id.', 'homlity-real-estate')],
                400
            );
        }

        $canonicalId = $this->service->resolveToCanonical($entityType, $source, $sourceId);

        return new \WP_REST_Response(['canonical_term_id' => $canonicalId], 200);
    }
}
