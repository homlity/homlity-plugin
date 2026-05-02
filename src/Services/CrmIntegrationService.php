<?php

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Integrations\CRM\CrmConfig;
use Homlity\PluginInmobiliario\Integrations\CRM\CrmIntegrationManager;
use Homlity\PluginInmobiliario\Integrations\CRM\CrmSyncLogger;
use Homlity\PluginInmobiliario\Integrations\CRM\CrmSyncQueueService;
use Homlity\PluginInmobiliario\Integrations\CRM\FieldMap\PropertyFieldSchema;
use Homlity\PluginInmobiliario\Integrations\CRM\PropertyUpsertService;
use Homlity\PluginInmobiliario\Integrations\CRM\Repository\PullSyncJobRepository;
use Homlity\PluginInmobiliario\Integrations\CRM\Repository\WebhookEventRepository;
use Homlity\PluginInmobiliario\Integrations\CRM\Services\CanonicalPropertySchema;
use Homlity\PluginInmobiliario\Integrations\CRM\Services\VersionedPropertySchemaValidator;
use Homlity\PluginInmobiliario\Integrations\CRM\Services\WebhookAuthenticator;
use Homlity\PluginInmobiliario\Integrations\CRM\Services\WebhookPayloadSchema;

if (!defined('ABSPATH')) {
    exit;
}

class CrmIntegrationService implements ServiceInterface
{
    private CrmIntegrationManager $manager;
    private PropertyUpsertService $upsert;
    private CrmSyncLogger $logger;
    private CrmSyncQueueService $queue;
    private WebhookEventRepository $webhookEvents;
    private PullSyncJobRepository $pullJobs;
    private WebhookAuthenticator $webhookAuthenticator;
    private VersionedPropertySchemaValidator $schemaValidator;

    public function __construct()
    {
        $this->manager = new CrmIntegrationManager();
        $this->upsert = new PropertyUpsertService();
        $this->logger = new CrmSyncLogger();
        $this->queue = new CrmSyncQueueService($this->manager, $this->upsert, $this->logger);
        $this->webhookEvents = new WebhookEventRepository();
        $this->pullJobs = new PullSyncJobRepository();
        $this->webhookAuthenticator = new WebhookAuthenticator();
        $this->schemaValidator = new VersionedPropertySchemaValidator();
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        $this->queue->register();
    }

    public function registerRoutes(): void
    {
        register_rest_route('homlity/v1', '/crm/providers', [
            'methods' => 'GET',
            'callback' => [$this, 'restProviders'],
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('homlity/v1', '/crm/schema/property', [
            'methods' => 'GET',
            'callback' => [$this, 'restSchema'],
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('homlity/v1', '/crm/schema/webhook', [
            'methods' => 'GET',
            'callback' => [$this, 'restWebhookSchema'],
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('homlity/v1', '/crm/schema/validate', [
            'methods' => 'POST',
            'callback' => [$this, 'restValidateSchema'],
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('homlity/v1', '/crm/pull/job-state/(?P<provider>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'restPullJobState'],
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('homlity/v1', '/crm/sync/(?P<provider>[a-zA-Z0-9_-]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'restSync'],
            'permission_callback' => static fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('homlity/v1', '/crm/webhook/(?P<provider>[a-zA-Z0-9_-]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'restWebhookSync'],
            'permission_callback' => [$this, 'canWebhook'],
        ]);

        register_rest_route('homlity/v1', '/crm/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'restGenericWebhook'],
            'permission_callback' => [$this, 'canGenericWebhook'],
        ]);

        register_rest_route('homlity/v1', '/crm/sync-batch/(?P<provider>[a-zA-Z0-9_-]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'restSyncBatch'],
            'permission_callback' => static fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('homlity/v1', '/crm/queue/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'restQueueStats'],
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);
        register_rest_route('homlity/v1', '/crm/queue/retry-failed', [
            'methods' => 'POST',
            'callback' => [$this, 'restRetryFailed'],
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('homlity/v1', '/crm/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'restLogs'],
            'permission_callback' => static fn() => current_user_can('manage_options'),
        ]);
    }

    public function restProviders(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'providers' => $this->manager->listProviders(),
            'option_key' => CrmConfig::OPTION_INTEGRATIONS,
        ], 200);
    }

    public function restSchema(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'schema' => PropertyFieldSchema::schema(),
            'meta_map' => PropertyFieldSchema::metaMap(),
            'canonical_schema' => CanonicalPropertySchema::definition(),
            'schema_version' => VersionedPropertySchemaValidator::CURRENT_SCHEMA_VERSION,
        ], 200);
    }

    public function restWebhookSchema(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'schema' => WebhookPayloadSchema::schema(),
            'auth' => [
                'token_header' => 'x-homlity-integration-key',
                'bearer' => 'Authorization: Bearer <token>',
                'hmac_headers' => [
                    'x-homlity-timestamp',
                    'x-homlity-signature: sha256=<hmac(timestamp.body)>',
                ],
            ],
        ], 200);
    }

    public function restValidateSchema(\WP_REST_Request $request): \WP_REST_Response
    {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            return new \WP_REST_Response([
                'valid' => false,
                'errors' => [
                    [
                        'field' => 'body',
                        'code' => 'invalid_json',
                        'message' => 'Request body must be a JSON object.',
                    ],
                ],
                'warnings' => [],
            ], 400);
        }

        return new \WP_REST_Response($this->schemaValidator->validateWebhookPayload($payload)->toArray(), 200);
    }

    public function restPullJobState(\WP_REST_Request $request): \WP_REST_Response
    {
        $provider = sanitize_key((string) $request->get_param('provider'));
        if ($provider === '') {
            return new \WP_REST_Response(['ok' => false, 'error' => 'Missing provider'], 400);
        }

        return new \WP_REST_Response([
            'ok' => true,
            'provider' => $provider,
            'job' => $this->pullJobs->findRunnable($provider),
            'contract' => [
                'checkpoint_fields' => ['cursor', 'updated_since', 'batch_size', 'status', 'locked_until', 'attempts'],
                'statuses' => ['pending', 'processing', 'retrying', 'synced', 'failed', 'paused', 'cancelled'],
            ],
        ], 200);
    }

    public function restSync(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->syncRecord($request, false);
    }

    public function restWebhookSync(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->syncRecord($request, true);
    }

    public function canWebhook(\WP_REST_Request $request): bool
    {
        $provider = sanitize_key((string) $request->get_param('provider'));
        if ($provider === '') {
            return false;
        }

        $configs = get_option(CrmConfig::OPTION_INTEGRATIONS, []);
        $providerConfig = is_array($configs[$provider] ?? null) ? $configs[$provider] : [];
        $hasToken = sanitize_text_field((string) ($providerConfig['webhook_key'] ?? '')) !== '';
        $hasHmac = sanitize_text_field((string) ($providerConfig['webhook_hmac_secret'] ?? '')) !== '';
        if (!$hasToken && !$hasHmac) {
            return false;
        }

        return $this->webhookAuthenticator->verify($request, $providerConfig);
    }

    public function canGenericWebhook(\WP_REST_Request $request): bool
    {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            return false;
        }

        $provider = sanitize_key((string) ($payload['source_key'] ?? $payload['provider'] ?? ''));
        if ($provider === '') {
            return false;
        }

        $configs = get_option(CrmConfig::OPTION_INTEGRATIONS, []);
        $providerConfig = is_array($configs[$provider] ?? null) ? $configs[$provider] : [];
        $hasToken = sanitize_text_field((string) ($providerConfig['webhook_key'] ?? '')) !== '';
        $hasHmac = sanitize_text_field((string) ($providerConfig['webhook_hmac_secret'] ?? '')) !== '';
        if (!$hasToken && !$hasHmac) {
            return false;
        }

        return $this->webhookAuthenticator->verify($request, $providerConfig);
    }

    public function restSyncBatch(\WP_REST_Request $request): \WP_REST_Response
    {
        $provider = sanitize_key((string) $request->get_param('provider'));
        $adapter = $this->manager->getAdapter($provider);
        if (!$adapter) {
            return new \WP_REST_Response(['ok' => false, 'error' => 'Unknown provider'], 404);
        }

        $payload = $request->get_json_params();
        $records = is_array($payload['records'] ?? null) ? $payload['records'] : [];
        $queued = $this->queue->enqueueBatch($provider, $records);

        return new \WP_REST_Response([
            'ok' => true,
            'provider' => $provider,
            'queued' => $queued,
            'stats' => $this->queue->stats(),
        ], 200);
    }

    public function restGenericWebhook(\WP_REST_Request $request): \WP_REST_Response
    {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            return new \WP_REST_Response(['ok' => false, 'error' => 'Invalid JSON payload'], 400);
        }

        $provider = sanitize_key((string) ($payload['source_key'] ?? $payload['provider'] ?? ''));
        $adapter = $this->manager->getAdapter($provider);
        if (!$adapter) {
            return new \WP_REST_Response(['ok' => false, 'error' => 'Unknown provider'], 404);
        }

        $eventId = sanitize_text_field((string) ($payload['event_id'] ?? ''));
        $eventType = sanitize_key((string) ($payload['event_type'] ?? $payload['event'] ?? 'property.updated'));
        if ($eventId === '') {
            $eventId = md5($provider . '|' . $eventType . '|' . wp_json_encode($payload));
        }

        $property = is_array($payload['property'] ?? null) ? $payload['property'] : $payload;
        $externalId = sanitize_text_field((string) ($payload['external_id'] ?? $property['external_id'] ?? $property['id'] ?? ''));
        $payloadHash = md5(wp_json_encode($payload));

        if ($this->webhookEvents->exists($provider, $eventId)) {
            return new \WP_REST_Response([
                'ok' => true,
                'duplicate' => true,
                'provider' => $provider,
                'event_id' => $eventId,
            ], 202);
        }

        $this->webhookEvents->record($provider, $eventId, $eventType, $externalId, $payloadHash, 'queued');
        $queued = $this->queue->enqueueWebhookEvent($provider, $payload, $eventId, $eventType);

        $this->logger->add([
            'level' => 'info',
            'provider' => $provider,
            'external_id' => $externalId,
            'post_id' => 0,
            'message' => 'Webhook event queued',
            'source' => 'generic_webhook',
            'event_id' => $eventId,
            'event_type' => $eventType,
        ]);

        return new \WP_REST_Response([
            'ok' => true,
            'provider' => $provider,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'queued' => $queued,
            'stats' => $this->queue->stats(),
        ], 202);
    }

    public function restQueueStats(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'ok' => true,
            'stats' => $this->queue->stats(),
        ], 200);
    }

    public function restRetryFailed(\WP_REST_Request $request): \WP_REST_Response
    {
        $provider = sanitize_key((string) $request->get_param('provider'));
        if ($provider === '') {
            $provider = null;
        }

        $retried = $this->queue->retryFailed($provider);

        return new \WP_REST_Response([
            'ok' => true,
            'retried' => $retried,
            'stats' => $this->queue->stats(),
        ], 200);
    }

    public function restLogs(\WP_REST_Request $request): \WP_REST_Response
    {
        $limit = (int) $request->get_param('limit');
        if ($limit <= 0) {
            $limit = 100;
        }
        $provider = sanitize_key((string) $request->get_param('provider'));
        if ($provider === '') {
            $provider = null;
        }
        $dateFrom = sanitize_text_field((string) $request->get_param('date_from'));
        $dateTo = sanitize_text_field((string) $request->get_param('date_to'));

        return new \WP_REST_Response([
            'ok' => true,
            'logs' => $this->logger->list($limit, $provider, $dateFrom ?: null, $dateTo ?: null),
        ], 200);
    }

    private function syncRecord(\WP_REST_Request $request, bool $fromWebhook): \WP_REST_Response
    {
        $provider = sanitize_key((string) $request->get_param('provider'));
        $adapter = $this->manager->getAdapter($provider);

        if (!$adapter) {
            return new \WP_REST_Response(['ok' => false, 'error' => 'Unknown provider'], 404);
        }

        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = [];
        }

        $normalized = $adapter->mapRecordToNormalized($payload, [
            'from_webhook' => $fromWebhook,
            'provider' => $provider,
        ]);

        $result = $this->upsert->upsert($normalized);
        $code = !empty($result['ok']) ? 200 : 422;

        $this->logger->add([
            'level' => !empty($result['ok']) ? 'success' : 'error',
            'provider' => $provider,
            'external_id' => (string) (($normalized['external']['id'] ?? '') ?: ''),
            'post_id' => (int) ($result['post_id'] ?? 0),
            'message' => !empty($result['ok']) ? 'Property synced successfully' : (string) ($result['error'] ?? 'Unknown error'),
            'normalized' => $normalized,
            'result' => $result,
            'source' => $fromWebhook ? 'webhook' : 'manual',
        ]);

        return new \WP_REST_Response([
            'ok' => (bool) ($result['ok'] ?? false),
            'provider' => $provider,
            'normalized' => $normalized,
            'result' => $result,
        ], $code);
    }
}
