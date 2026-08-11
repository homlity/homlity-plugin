<?php
/**
 * Bounded persistent queue with retry, aggregation and an atomic delivery lock.
 */

namespace Homlity\PluginInmobiliario\ErrorReporting;

if (!defined('ABSPATH')) {
    exit;
}

final class ErrorEventQueue
{
    public const OPTION = 'homlity_error_reporter_queue';
    public const STATE_OPTION = 'homlity_error_reporter_state';
    private const LOCK_OPTION = 'homlity_error_reporter_delivery_lock';
    private const MAX_EVENTS = 100;
    private const MAX_AGE = 604800;

    /** @param array<string, mixed> $event */
    public function enqueue(array $event): bool
    {
        $origin = (string) ($event['tags']['origin_plugin'] ?? '');
        if (empty($event['event_id']) || $origin === '') {
            return false;
        }
        $queue = $this->all();
        $aggregateKey = $this->aggregateKey($event);
        foreach ($queue as &$entry) {
            if (($entry['aggregate_key'] ?? '') !== $aggregateKey || ($entry['blocked'] ?? false)) {
                continue;
            }
            $entry['aggregate_count'] = min(9999, (int) ($entry['aggregate_count'] ?? 1) + 1);
            $entry['last_occurred_at'] = (string) ($event['occurred_at'] ?? gmdate('c'));
            $entry['payload']['context']['aggregate_count'] = $entry['aggregate_count'];
            $entry['payload']['occurred_at'] = $entry['last_occurred_at'];
            $this->save($queue);
            return true;
        }
        unset($entry);

        $now = time();
        $queue[] = [
            'event_id' => (string) $event['event_id'],
            'origin' => $origin,
            'payload' => $event,
            'created_at' => $now,
            'last_occurred_at' => (string) ($event['occurred_at'] ?? gmdate('c')),
            'attempts' => 0,
            'next_attempt_at' => $now,
            'blocked' => false,
            'aggregate_key' => $aggregateKey,
            'aggregate_count' => 1,
        ];
        if (count($queue) > self::MAX_EVENTS) {
            $queue = array_slice($queue, -self::MAX_EVENTS);
            $this->recordState(['last_local_error' => 'queue_capacity_reached']);
        }
        $this->save($queue);
        return true;
    }

    /** @return array<int, array<string, mixed>> */
    public function due(int $limit = 10, string $preferredEventId = ''): array
    {
        $now = time();
        $due = array_values(array_filter($this->all(), static function (array $entry) use ($now): bool {
            return empty($entry['blocked']) && (int) ($entry['next_attempt_at'] ?? 0) <= $now;
        }));
        if ($preferredEventId !== '') {
            usort($due, static function (array $left, array $right) use ($preferredEventId): int {
                $leftPreferred = ($left['event_id'] ?? '') === $preferredEventId;
                $rightPreferred = ($right['event_id'] ?? '') === $preferredEventId;
                return $leftPreferred === $rightPreferred ? 0 : ($leftPreferred ? -1 : 1);
            });
        }
        return array_slice($due, 0, max(1, $limit));
    }

    public function remove(string $eventId): void
    {
        $this->save(array_values(array_filter($this->all(), static fn (array $entry): bool => ($entry['event_id'] ?? '') !== $eventId)));
    }

    public function retry(string $eventId, int $status = 0): void
    {
        $queue = $this->all();
        foreach ($queue as &$entry) {
            if (($entry['event_id'] ?? '') !== $eventId) {
                continue;
            }
            $attempts = (int) ($entry['attempts'] ?? 0) + 1;
            $base = min(DAY_IN_SECONDS, 60 * (2 ** min(8, $attempts - 1)));
            $entry['attempts'] = $attempts;
            $entry['next_attempt_at'] = time() + $base + wp_rand(0, min(60, $base));
            $this->recordState([
                'last_error_at' => gmdate('c'),
                'last_http_status' => $status,
                'next_retry_at' => gmdate('c', (int) $entry['next_attempt_at']),
            ]);
            break;
        }
        unset($entry);
        $this->save($queue);
    }

    public function block(string $eventId, int $status): void
    {
        $queue = $this->all();
        foreach ($queue as &$entry) {
            if (($entry['event_id'] ?? '') === $eventId) {
                $entry['blocked'] = true;
                $entry['next_attempt_at'] = 0;
                break;
            }
        }
        unset($entry);
        $this->save($queue);
        $this->recordState([
            'license_revalidation_required' => true,
            'last_error_at' => gmdate('c'),
            'last_http_status' => $status,
            'next_retry_at' => '',
        ]);
    }

    /** @param string[] $origins */
    public function unblockOrigins(array $origins): void
    {
        $queue = $this->all();
        $changed = false;
        foreach ($queue as &$entry) {
            if (!empty($entry['blocked']) && in_array((string) ($entry['origin'] ?? ''), $origins, true)) {
                $entry['blocked'] = false;
                $entry['next_attempt_at'] = time();
                $changed = true;
            }
        }
        unset($entry);
        if ($changed) {
            $this->save($queue);
            $this->recordState(['license_revalidation_required' => false, 'next_retry_at' => gmdate('c')]);
        }
    }

    /** @param array<string, mixed> $values */
    public function recordState(array $values): void
    {
        $state = get_option(self::STATE_OPTION, []);
        update_option(self::STATE_OPTION, array_merge(is_array($state) ? $state : [], $values), false);
    }

    /** @return array<string, mixed> */
    public function diagnostics(): array
    {
        $queue = $this->all();
        $state = get_option(self::STATE_OPTION, []);
        $state = is_array($state) ? $state : [];
        $next = [];
        $blocked = 0;
        foreach ($queue as $entry) {
            if (!empty($entry['blocked'])) {
                ++$blocked;
            } elseif (!empty($entry['next_attempt_at'])) {
                $next[] = (int) $entry['next_attempt_at'];
            }
        }
        return array_merge($state, [
            'queued' => count($queue),
            'blocked' => $blocked,
            'next_retry_at' => $next === [] ? '' : gmdate('c', min($next)),
            'max_queue' => self::MAX_EVENTS,
            'retention_days' => 7,
        ]);
    }

    public function acquireLock(): ?string
    {
        $token = wp_generate_uuid4();
        $existing = get_option(self::LOCK_OPTION, []);
        if (is_array($existing) && (int) ($existing['expires_at'] ?? 0) < time()) {
            delete_option(self::LOCK_OPTION);
        }
        return add_option(self::LOCK_OPTION, ['token' => $token, 'expires_at' => time() + 60], '', false) ? $token : null;
    }

    public function releaseLock(string $token): void
    {
        $existing = get_option(self::LOCK_OPTION, []);
        if (is_array($existing) && hash_equals((string) ($existing['token'] ?? ''), $token)) {
            delete_option(self::LOCK_OPTION);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function all(): array
    {
        $queue = get_option(self::OPTION, []);
        if (!is_array($queue)) {
            return [];
        }
        $cutoff = time() - self::MAX_AGE;
        $filtered = array_values(array_filter($queue, static fn ($entry): bool => is_array($entry) && (int) ($entry['created_at'] ?? 0) >= $cutoff));
        if (count($filtered) !== count($queue)) {
            $this->save($filtered);
        }
        return $filtered;
    }

    /** @param array<int, array<string, mixed>> $queue */
    private function save(array $queue): void
    {
        update_option(self::OPTION, array_values($queue), false);
    }

    /** @param array<string, mixed> $event */
    private function aggregateKey(array $event): string
    {
        $context = is_array($event['context'] ?? null) ? $event['context'] : [];
        $exception = is_array($event['exception'] ?? null) ? $event['exception'] : [];
        $parts = [
            (string) ($event['tags']['origin_plugin'] ?? ''),
            (string) ($context['operation'] ?? ''),
            (string) ($context['run_id'] ?? ''),
            (string) ($exception['type'] ?? ''),
            (string) ($exception['message'] ?? ''),
        ];
        return hash('sha256', implode('|', $parts));
    }
}
