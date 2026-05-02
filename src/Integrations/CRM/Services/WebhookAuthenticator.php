<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM\Services;

if (!defined('ABSPATH')) {
    exit;
}

class WebhookAuthenticator
{
    /**
     * @param array<string,mixed> $providerConfig
     */
    public function verify(\WP_REST_Request $request, array $providerConfig): bool
    {
        $token = sanitize_text_field((string) ($providerConfig['webhook_key'] ?? ''));
        $secret = sanitize_text_field((string) ($providerConfig['webhook_hmac_secret'] ?? ''));

        if ($secret !== '' && $this->verifyHmac($request, $secret, (int) ($providerConfig['webhook_tolerance'] ?? 300))) {
            return true;
        }

        if ($token === '') {
            return false;
        }

        $incoming = sanitize_text_field((string) $request->get_header('x-homlity-integration-key'));
        if ($incoming !== '' && hash_equals($token, $incoming)) {
            return true;
        }

        $auth = (string) $request->get_header('authorization');
        if (stripos($auth, 'Bearer ') !== 0) {
            return false;
        }

        return hash_equals($token, trim(substr($auth, 7)));
    }

    private function verifyHmac(\WP_REST_Request $request, string $secret, int $tolerance): bool
    {
        $signature = sanitize_text_field((string) $request->get_header('x-homlity-signature'));
        $timestamp = sanitize_text_field((string) $request->get_header('x-homlity-timestamp'));
        if ($signature === '' || $timestamp === '' || !ctype_digit($timestamp)) {
            return false;
        }

        $age = abs(time() - (int) $timestamp);
        if ($age > max(30, $tolerance)) {
            return false;
        }

        $rawBody = (string) $request->get_body();
        $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);

        return hash_equals($expected, $signature);
    }
}
