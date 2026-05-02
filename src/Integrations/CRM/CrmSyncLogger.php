<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM;

if (!defined('ABSPATH')) {
    exit;
}

class CrmSyncLogger
{
    private int $maxEntries;

    public function __construct(int $maxEntries = 500)
    {
        $this->maxEntries = max(50, $maxEntries);
    }

    /**
     * @param array<string,mixed> $entry
     */
    public function add(array $entry): void
    {
        $logs = get_option(CrmConfig::OPTION_LOGS, []);
        if (!is_array($logs)) {
            $logs = [];
        }

        $entry['timestamp'] = $entry['timestamp'] ?? gmdate('c');
        $entry['id'] = $entry['id'] ?? wp_generate_uuid4();

        array_unshift($logs, $entry);
        if (count($logs) > $this->maxEntries) {
            $logs = array_slice($logs, 0, $this->maxEntries);
        }

        update_option(CrmConfig::OPTION_LOGS, $logs, false);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list(int $limit = 100, ?string $provider = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $limit = max(1, min(500, $limit));
        $logs = get_option(CrmConfig::OPTION_LOGS, []);
        if (!is_array($logs)) {
            return [];
        }

        $provider = $provider !== null ? sanitize_key($provider) : null;
        $fromTs = $this->parseDateStart($dateFrom);
        $toTs = $this->parseDateEnd($dateTo);

        $filtered = array_values(array_filter($logs, function ($log) use ($provider, $fromTs, $toTs): bool {
            if ($provider !== null && $provider !== '' && sanitize_key((string) ($log['provider'] ?? '')) !== $provider) {
                return false;
            }

            $ts = strtotime((string) ($log['timestamp'] ?? ''));
            if ($ts === false) {
                return false;
            }

            if ($fromTs !== null && $ts < $fromTs) {
                return false;
            }
            if ($toTs !== null && $ts > $toTs) {
                return false;
            }

            return true;
        }));

        return array_slice($filtered, 0, $limit);
    }

    public function clear(): void
    {
        update_option(CrmConfig::OPTION_LOGS, [], false);
    }

    private function parseDateStart(?string $date): ?int
    {
        $date = trim((string) $date);
        if ($date === '') {
            return null;
        }

        $ts = strtotime($date . ' 00:00:00');
        return $ts === false ? null : $ts;
    }

    private function parseDateEnd(?string $date): ?int
    {
        $date = trim((string) $date);
        if ($date === '') {
            return null;
        }

        $ts = strtotime($date . ' 23:59:59');
        return $ts === false ? null : $ts;
    }
}
