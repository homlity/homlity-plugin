<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM;

use Homlity\PluginInmobiliario\Integrations\CRM\Contracts\CrmAdapterInterface;
use Homlity\PluginInmobiliario\Integrations\CRM\Repository\WebhookEventRepository;

if (!defined('ABSPATH')) {
    exit;
}

class CrmSyncQueueService
{
    private CrmIntegrationManager $manager;
    private PropertyUpsertService $upsert;
    private CrmSyncLogger $logger;
    private WebhookEventRepository $webhookEvents;

    public function __construct(CrmIntegrationManager $manager, PropertyUpsertService $upsert, CrmSyncLogger $logger)
    {
        $this->manager = $manager;
        $this->upsert = $upsert;
        $this->logger = $logger;
        $this->webhookEvents = new WebhookEventRepository();
    }

    public function register(): void
    {
        add_filter('cron_schedules', [$this, 'addCronSchedules']);
        add_action(CrmConfig::CRON_HOOK, [$this, 'processQueue']);

        if (!wp_next_scheduled(CrmConfig::CRON_HOOK)) {
            wp_schedule_event(time() + 60, 'homlity_crm_minute', CrmConfig::CRON_HOOK);
        }
    }

    public function addCronSchedules(array $schedules): array
    {
        if (!isset($schedules['homlity_crm_minute'])) {
            $schedules['homlity_crm_minute'] = [
                'interval' => 60,
                'display' => __('Homlity CRM - cada minuto', 'homlity-plugin'),
            ];
        }

        return $schedules;
    }

    /**
     * @param array<int,array<string,mixed>> $records
     * @return int
     */
    public function enqueueBatch(string $provider, array $records): int
    {
        $provider = sanitize_key($provider);
        if ($provider === '') {
            return 0;
        }

        $queue = $this->getQueue();
        $count = 0;
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $queue[] = [
                'id' => wp_generate_uuid4(),
                'provider' => $provider,
                'payload' => $record,
                'status' => 'pending',
                'attempts' => 0,
                'created_at' => gmdate('c'),
                'last_error' => '',
            ];
            $count++;
        }

        $this->saveQueue($queue);
        return $count;
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function enqueueWebhookEvent(string $provider, array $payload, string $eventId, string $eventType): int
    {
        $provider = sanitize_key($provider);
        if ($provider === '') {
            return 0;
        }

        $queue = $this->getQueue();
        $queue[] = [
            'id' => wp_generate_uuid4(),
            'provider' => $provider,
            'payload' => $payload,
            'status' => 'pending',
            'source' => 'webhook',
            'event_id' => sanitize_text_field($eventId),
            'event_type' => sanitize_key($eventType),
            'attempts' => 0,
            'created_at' => gmdate('c'),
            'last_error' => '',
        ];

        $this->saveQueue($queue);
        return 1;
    }

    public function processQueue(?int $limit = null): void
    {
        $configs = get_option(CrmConfig::OPTION_INTEGRATIONS, []);
        $defaultLimit = isset($configs['_global']['batch_size']) ? max(1, (int) $configs['_global']['batch_size']) : 20;
        $limit = $limit !== null ? max(1, $limit) : $defaultLimit;

        $queue = $this->getQueue();
        if (!$queue) {
            return;
        }

        $processed = 0;
        foreach ($queue as $index => $item) {
            if ($processed >= $limit) {
                break;
            }

            if (($item['status'] ?? '') !== 'pending') {
                continue;
            }

            $provider = sanitize_key((string) ($item['provider'] ?? ''));
            $adapter = $this->manager->getAdapter($provider);
            if (!$adapter) {
                $queue[$index]['status'] = 'failed';
                $queue[$index]['last_error'] = 'Unknown provider';
                $this->logFailure($item, null, 'Unknown provider');
                $processed++;
                continue;
            }

            $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];
            $this->markWebhookEvent($item, 'processing');
            $normalized = $adapter->mapRecordToNormalized($payload, ['from_queue' => true, 'provider' => $provider]);
            $result = $this->upsert->upsert($normalized);

            $queue[$index]['attempts'] = (int) ($queue[$index]['attempts'] ?? 0) + 1;
            $queue[$index]['processed_at'] = gmdate('c');

            if (!empty($result['ok'])) {
                $queue[$index]['status'] = 'done';
                $queue[$index]['post_id'] = (int) ($result['post_id'] ?? 0);
                $this->markWebhookEvent($item, 'done');
                $this->logSuccess($item, $normalized, $result);
            } else {
                $queue[$index]['status'] = 'failed';
                $queue[$index]['last_error'] = (string) ($result['error'] ?? 'Unknown error');
                $this->markWebhookEvent($item, 'failed');
                $this->logFailure($item, $normalized, (string) ($result['error'] ?? 'Unknown error'));
            }

            $processed++;
        }

        $this->saveQueue($this->truncateQueue($queue));
    }

    /**
     * @return array<string,mixed>
     */
    public function stats(): array
    {
        $queue = $this->getQueue();
        $stats = ['pending' => 0, 'done' => 0, 'failed' => 0, 'total' => count($queue)];
        foreach ($queue as $item) {
            $status = (string) ($item['status'] ?? 'pending');
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        return $stats;
    }

    public function clearFinished(): void
    {
        $queue = array_values(array_filter($this->getQueue(), static function ($item): bool {
            $status = (string) ($item['status'] ?? 'pending');
            return $status === 'pending';
        }));
        $this->saveQueue($queue);
    }

    public function retryFailed(?string $provider = null): int
    {
        $provider = $provider !== null ? sanitize_key($provider) : null;
        $queue = $this->getQueue();
        $count = 0;

        foreach ($queue as $index => $item) {
            if (($item['status'] ?? '') !== 'failed') {
                continue;
            }
            if ($provider !== null && $provider !== '' && sanitize_key((string) ($item['provider'] ?? '')) !== $provider) {
                continue;
            }

            $queue[$index]['status'] = 'pending';
            $queue[$index]['last_error'] = '';
            $queue[$index]['requeued_at'] = gmdate('c');
            $count++;
        }

        if ($count > 0) {
            $this->saveQueue($queue);
        }

        return $count;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getQueue(): array
    {
        $queue = get_option(CrmConfig::OPTION_QUEUE, []);
        return is_array($queue) ? $queue : [];
    }

    /**
     * @param array<int,array<string,mixed>> $queue
     */
    private function saveQueue(array $queue): void
    {
        update_option(CrmConfig::OPTION_QUEUE, array_values($queue), false);
    }

    /**
     * @param array<int,array<string,mixed>> $queue
     * @return array<int,array<string,mixed>>
     */
    private function truncateQueue(array $queue): array
    {
        $max = 3000;
        if (count($queue) <= $max) {
            return $queue;
        }

        $pending = array_values(array_filter($queue, static fn($item): bool => ($item['status'] ?? '') === 'pending'));
        $finished = array_values(array_filter($queue, static fn($item): bool => ($item['status'] ?? '') !== 'pending'));
        $finished = array_slice($finished, -max(0, $max - count($pending)));

        return array_merge($pending, $finished);
    }

    /**
     * @param array<string,mixed> $item
     */
    private function markWebhookEvent(array $item, string $status): void
    {
        if (($item['source'] ?? '') !== 'webhook') {
            return;
        }

        $provider = sanitize_key((string) ($item['provider'] ?? ''));
        $eventId = sanitize_text_field((string) ($item['event_id'] ?? ''));
        if ($provider === '' || $eventId === '') {
            return;
        }

        $this->webhookEvents->markStatus($provider, $eventId, $status);
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,mixed>|null $normalized
     * @param array<string,mixed> $result
     */
    private function logSuccess(array $item, ?array $normalized, array $result): void
    {
        $this->logger->add([
            'level' => 'success',
            'provider' => (string) ($item['provider'] ?? ''),
            'queue_id' => (string) ($item['id'] ?? ''),
            'external_id' => (string) (($normalized['external']['id'] ?? '') ?: ''),
            'post_id' => (int) ($result['post_id'] ?? 0),
            'message' => 'Property synced successfully',
            'normalized' => $normalized,
            'result' => $result,
        ]);
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,mixed>|null $normalized
     */
    private function logFailure(array $item, ?array $normalized, string $error): void
    {
        $this->logger->add([
            'level' => 'error',
            'provider' => (string) ($item['provider'] ?? ''),
            'queue_id' => (string) ($item['id'] ?? ''),
            'external_id' => (string) (($normalized['external']['id'] ?? '') ?: ''),
            'post_id' => 0,
            'message' => $error,
            'normalized' => $normalized,
            'result' => ['ok' => false, 'error' => $error],
        ]);
    }
}
