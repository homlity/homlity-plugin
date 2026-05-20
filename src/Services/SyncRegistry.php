<?php
/**
 * Registry for on-demand property sync providers.
 *
 * CRM synchronizer plugins register a SyncProviderInterface implementation
 * during the 'homlity_plugin_register_sync_providers' WordPress action.
 * When PropertyCodeRoutingService receives a request for /inmueble/{CODE}
 * that is not found in the local database, it calls SyncRegistry::syncByCode()
 * which delegates to each registered provider in turn.
 *
 * Providers are tried in registration order; the first one that returns a
 * valid post ID wins.
 *
 * Boot timing:
 *   homlity-real-estate  bootstraps at plugins_loaded priority 20.
 *   SyncRegistry    fires 'homlity_plugin_register_sync_providers' at priority 30.
 *   External plugins hook into that action at their own priority 20 bootstrap,
 *   so their callbacks run when the action fires at priority 30.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Contracts\SyncProviderInterface;
use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class SyncRegistry implements ServiceInterface
{
    /** @var SyncProviderInterface[] keyed by provider ID */
    private static array $providers = [];

    public function register(): void
    {
        // Priority 30 — fires after all plugins have bootstrapped at priority 20,
        // so external plugins can hook into 'homlity_plugin_register_sync_providers'
        // during their own init and have their callbacks run when this action fires.
        add_action('plugins_loaded', [self::class, 'dispatch'], 30);
    }

    /**
     * Fires the registration hook. Called at plugins_loaded priority 30.
     * External plugins must have added their add_action() callbacks before
     * this priority runs (e.g. in their own plugins_loaded at priority 20).
     */
    public static function dispatch(): void
    {
        do_action('homlity_plugin_register_sync_providers');
    }

    /**
     * Registers a sync provider. Must be called inside the
     * 'homlity_plugin_register_sync_providers' action callback.
     */
    public static function addProvider(SyncProviderInterface $provider): void
    {
        self::$providers[$provider->getProviderId()] = $provider;
    }

    /**
     * Iterates registered providers in order and returns the first successful
     * post ID, or null if no provider could sync the property.
     */
    public static function syncByCode(string $code): ?int
    {
        $result = self::syncByCodeDetailed($code);
        return $result['post_id'] ?? null;
    }

    /**
     * @return array{post_id?: int, status: string, message: string}
     */
    public static function syncByCodeDetailed(string $code): array
    {
        $lastResult = [
            'status'  => 'not_found',
            'message' => __('Inmueble no existe o no está disponible.', 'homlity-real-estate'),
        ];

        foreach (self::$providers as $provider) {
            if (method_exists($provider, 'syncByCodeDetailed')) {
                $result = $provider->syncByCodeDetailed($code);
                if (!empty($result['post_id']) && (int) $result['post_id'] > 0) {
                    return ['post_id' => (int) $result['post_id'], 'status' => 'success', 'message' => ''];
                }

                if (!empty($result['status'])) {
                    $lastResult = [
                        'status'  => sanitize_key((string) $result['status']),
                        'message' => sanitize_text_field((string) ($result['message'] ?? $lastResult['message'])),
                    ];
                }
                continue;
            }

            $postId = $provider->syncByCode($code);
            if ($postId !== null && $postId > 0) {
                return ['post_id' => (int) $postId, 'status' => 'success', 'message' => ''];
            }
        }

        return $lastResult;
    }

    /** @return SyncProviderInterface[] */
    public static function getProviders(): array
    {
        return self::$providers;
    }
}
