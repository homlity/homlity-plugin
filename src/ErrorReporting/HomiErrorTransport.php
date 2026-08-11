<?php
/**
 * Authenticated Homi error-event transport. Credentials are resolved at send time.
 */

namespace Homlity\PluginInmobiliario\ErrorReporting;

use Homlity\PluginInmobiliario\Services\HomiApiClient;

if (!defined('ABSPATH')) {
    exit;
}

final class HomiErrorTransport
{
    private OfficialPluginRegistry $registry;
    private HomiApiClient $client;

    public function __construct(?OfficialPluginRegistry $registry = null, ?HomiApiClient $client = null)
    {
        $this->registry = $registry ?: new OfficialPluginRegistry();
        $this->client = $client ?: new HomiApiClient();
    }

    /** @param array<string, mixed> $event
     *  @return array{success: bool, status: int, data: array<string, mixed>, message: string, transport_error: bool}
     */
    public function send(string $origin, array $event, int $timeout = 10): array
    {
        $credentials = $this->registry->credentials($origin);
        if (!$credentials['valid']) {
            return [
                'success' => false,
                'status' => 403,
                'data' => [],
                'message' => 'license_or_installation_unavailable',
                'transport_error' => false,
            ];
        }

        $url = $credentials['api_url']
            . '/api/v1/plugin-installations/'
            . rawurlencode($credentials['site_id'])
            . '/error-events';

        return $this->client->post($url, $event, [
            'X-Plugin-License' => $credentials['license_key'],
        ], $timeout);
    }
}
