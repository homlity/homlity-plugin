<?php
/**
 * Shared, deliberately small HTTP client for Homi JSON endpoints.
 */

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class HomiApiClient
{
    private static bool $requestInProgress = false;

    /**
     * @param array<string, scalar> $query
     * @param array<string, string> $headers
     * @return array{success: bool, status: int, data: array<string, mixed>, message: string, transport_error: bool}
     */
    public function get(string $url, array $query = [], array $headers = [], int $timeout = 15): array
    {
        return $this->request('GET', add_query_arg($query, $url), null, $headers, $timeout);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array{success: bool, status: int, data: array<string, mixed>, message: string, transport_error: bool}
     */
    public function post(string $url, array $payload, array $headers = [], int $timeout = 10): array
    {
        return $this->request('POST', $url, $payload, $headers, $timeout);
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, string> $headers
     * @return array{success: bool, status: int, data: array<string, mixed>, message: string, transport_error: bool}
     */
    private function request(string $method, string $url, ?array $payload, array $headers, int $timeout): array
    {
        if (self::$requestInProgress) {
            return $this->result(false, 0, [], 'recursive_request', true);
        }

        self::$requestInProgress = true;
        try {
            $args = [
                'method' => $method,
                'timeout' => max(1, min(30, $timeout)),
                'redirection' => 3,
                'sslverify' => true,
                'headers' => array_merge([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ], $headers),
            ];
            if ($payload !== null) {
                $encoded = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (!is_string($encoded)) {
                    return $this->result(false, 0, [], 'json_encoding_failed', true);
                }
                $args['body'] = $encoded;
            }

            $response = wp_remote_request($url, $args);
            if (is_wp_error($response)) {
                return $this->result(false, 0, [], 'transport_error', true);
            }

            $status = (int) wp_remote_retrieve_response_code($response);
            $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
            $data = is_array($decoded) ? $decoded : [];
            $success = $status >= 200 && $status < 300 && ($data['success'] ?? true) !== false;
            $message = $success ? '' : sanitize_text_field((string) ($data['message'] ?? 'request_rejected'));

            return $this->result($success, $status, $data, $message, false);
        } finally {
            self::$requestInProgress = false;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, status: int, data: array<string, mixed>, message: string, transport_error: bool}
     */
    private function result(bool $success, int $status, array $data, string $message, bool $transportError): array
    {
        return compact('success', 'status', 'data', 'message') + ['transport_error' => $transportError];
    }
}
