<?php
/**
 * Canonical inventory and credential lookup for official Homlity plugins.
 */

namespace Homlity\PluginInmobiliario\ErrorReporting;

if (!defined('ABSPATH')) {
    exit;
}

final class OfficialPluginRegistry
{
    /** @var array<string, array<string, mixed>> */
    private const PLUGINS = [
        'homlity-real-estate' => [
            'directories' => ['homlity-real-estate'],
            'hook_prefixes' => ['homlity_plugin_', 'homlity_error_reporter_', 'homlity_purge_'],
            'action_groups' => ['homlity-real-estate'],
            'main_file' => 'homlity-real-estate/plugin-inmobiliario.php',
            'license_option' => 'homlity_license_key',
            'site_id_option' => 'homlity_license_site_id',
            'status_option' => 'homlity_license_status',
            'api_url_option' => 'plugin_homlity_sync_homi_api_url',
        ],
        'homlity-sync' => [
            'directories' => ['homlity-sync'],
            'hook_prefixes' => ['homlity_sync_', 'homlity_consignacion_'],
            'action_groups' => ['homlity-sync'],
            'main_file' => 'homlity-sync/plugin-homlity-sync.php',
            'license_option' => 'homlity_license_key',
            'site_id_option' => 'homlity_license_site_id',
            'status_option' => 'homlity_license_status',
            'api_url_option' => 'plugin_homlity_sync_homi_api_url',
        ],
        'homlity-wasi' => [
            'directories' => ['plugin-wasi-sync', 'homlity-wasi'],
            'hook_prefixes' => ['wasi_sync/'],
            'action_groups' => ['wasi-sync'],
            'main_file' => 'plugin-wasi-sync/plugin-wasi-sync.php',
            'license_option' => 'plugin_wasi_sync_license_key',
            'site_id_option' => 'plugin_wasi_sync_license_site_id',
            'status_option' => 'plugin_wasi_sync_license_status',
            'api_url_option' => 'plugin_wasi_sync_homi_api_url',
        ],
        'homlity-simi' => [
            'directories' => ['plugin-simi-sync', 'homlity-simi'],
            'hook_prefixes' => ['simi_sync/'],
            'action_groups' => ['simi-sync'],
            'main_file' => 'plugin-simi-sync/plugin-simi-sync.php',
            'license_option' => 'plugin_simi_sync_license_key',
            'site_id_option' => 'plugin_simi_sync_license_site_id',
            'status_option' => 'plugin_simi_sync_license_status',
            'api_url_option' => 'plugin_simi_sync_homi_api_url',
        ],
        'homlity-softinm' => [
            'directories' => ['plugin-softinm-sync', 'homlity-softinm'],
            'hook_prefixes' => ['softinm_sync/'],
            'action_groups' => ['softinm-sync'],
            'main_file' => 'plugin-softinm-sync/plugin-softinm-sync.php',
            'license_option' => 'plugin_softinm_sync_license_key',
            'site_id_option' => 'plugin_softinm_sync_license_site_id',
            'status_option' => 'plugin_softinm_sync_license_status',
            'api_url_option' => 'plugin_softinm_sync_homi_api_url',
        ],
    ];

    public function originForFile(string $file, bool $ownCodeOnly = false): ?string
    {
        $normalized = str_replace('\\', '/', $file);
        $pluginsRoot = rtrim(str_replace('\\', '/', WP_PLUGIN_DIR), '/') . '/';
        if (strpos($normalized, $pluginsRoot) !== 0) {
            return null;
        }

        $relative = substr($normalized, strlen($pluginsRoot));
        // Las librerías de terceros que empaquetamos (Action Scheduler, Guzzle…)
        // se ejecutan en nombre de quien las llama: no identifican al culpable.
        if ($ownCodeOnly && strpos($relative, '/vendor/') !== false) {
            return null;
        }

        $directory = strtok($relative, '/');
        foreach ($this->definitions() as $canonical => $definition) {
            if (in_array($directory, $definition['directories'], true)) {
                return $canonical;
            }
        }
        return null;
    }

    /**
     * Resuelve el plugin propietario de un hook programado.
     * Gana el prefijo más específico para que homlity_sync_* no caiga en
     * homlity-real-estate ni al revés.
     */
    public function originForHook(string $hook): ?string
    {
        $hook = trim($hook);
        if ($hook === '') {
            return null;
        }

        $origin = null;
        $matched = 0;
        foreach ($this->definitions() as $canonical => $definition) {
            foreach ((array) ($definition['hook_prefixes'] ?? []) as $prefix) {
                $prefix = (string) $prefix;
                if ($prefix !== '' && strpos($hook, $prefix) === 0 && strlen($prefix) > $matched) {
                    $origin = $canonical;
                    $matched = strlen($prefix);
                }
            }
        }
        return $origin;
    }

    /** Resuelve el plugin propietario de un grupo de Action Scheduler. */
    public function originForActionGroup(string $group): ?string
    {
        $group = sanitize_key($group);
        if ($group === '') {
            return null;
        }
        foreach ($this->definitions() as $canonical => $definition) {
            foreach ((array) ($definition['action_groups'] ?? []) as $candidate) {
                if (sanitize_key((string) $candidate) === $group) {
                    return $canonical;
                }
            }
        }
        return null;
    }

    public function originForThrowable(\Throwable $throwable, bool $ownCodeOnly = false): ?string
    {
        for ($current = $throwable; $current !== null; $current = $current->getPrevious()) {
            $origin = $this->originForFile($current->getFile(), $ownCodeOnly);
            if ($origin !== null) {
                return $origin;
            }
            foreach ($current->getTrace() as $frame) {
                if (!empty($frame['file'])) {
                    $origin = $this->originForFile((string) $frame['file'], $ownCodeOnly);
                    if ($origin !== null) {
                        return $origin;
                    }
                }
            }
        }
        return null;
    }

    /** Causa raíz de una excepción re-lanzada (p. ej. por el queue runner). */
    public function rootCause(\Throwable $throwable): \Throwable
    {
        $root = $throwable;
        while (($previous = $root->getPrevious()) !== null) {
            $root = $previous;
        }
        return $root;
    }

    public function normalizeOrigin(string $origin): ?string
    {
        $key = sanitize_key($origin);
        if (isset($this->definitions()[$key])) {
            return $key;
        }
        foreach ($this->definitions() as $canonical => $definition) {
            if (in_array($key, $definition['directories'], true)) {
                return $canonical;
            }
        }
        return null;
    }

    /**
     * @return array{license_key: string, site_id: string, status: string, api_url: string, valid: bool}
     */
    public function credentials(string $origin): array
    {
        $definition = $this->definitions()[$origin] ?? [];
        $license = trim((string) get_option((string) ($definition['license_option'] ?? ''), ''));
        $siteId = trim((string) get_option((string) ($definition['site_id_option'] ?? ''), ''));
        $status = sanitize_key((string) get_option((string) ($definition['status_option'] ?? ''), ''));
        $configuredUrl = trim((string) get_option((string) ($definition['api_url_option'] ?? ''), ''));
        $apiUrl = $configuredUrl !== '' ? $configuredUrl : 'https://homi.homlity.com';
        // A transient validation outage does not invalidate the last accepted
        // installation; Homi still performs the authoritative check on delivery.
        $validStatuses = ['active', 'valid', 'activated', 'grace', 'connection_error'];

        return [
            'license_key' => $license,
            'site_id' => $siteId,
            'status' => $status,
            'api_url' => rtrim(esc_url_raw($apiUrl), '/'),
            'valid' => $license !== ''
                && $siteId !== ''
                && $apiUrl !== ''
                && in_array($status, $validStatuses, true),
        ];
    }

    public function version(string $origin): string
    {
        $definition = $this->definitions()[$origin] ?? null;
        if (!is_array($definition)) {
            return '';
        }
        $file = WP_PLUGIN_DIR . '/' . $definition['main_file'];
        if (!is_file($file)) {
            foreach ($definition['directories'] as $directory) {
                $candidates = glob(WP_PLUGIN_DIR . '/' . $directory . '/*.php') ?: [];
                foreach ($candidates as $candidate) {
                    $contents = file_get_contents($candidate, false, null, 0, 8192);
                    if (is_string($contents) && preg_match('/^[ \t\/*#@]*Version:\s*(.+)$/mi', $contents, $match)) {
                        return trim($match[1]);
                    }
                }
            }
            return '';
        }
        if (function_exists('get_plugin_data')) {
            $data = get_plugin_data($file, false, false);
            return (string) ($data['Version'] ?? '');
        }
        $contents = file_get_contents($file, false, null, 0, 8192);
        return is_string($contents) && preg_match('/^[ \t\/*#@]*Version:\s*(.+)$/mi', $contents, $match)
            ? trim($match[1])
            : '';
    }

    /** @return array<int, array<string, mixed>> */
    public function detectedPlugins(): array
    {
        $detected = [];
        foreach ($this->definitions() as $canonical => $definition) {
            $installed = false;
            foreach ($definition['directories'] as $directory) {
                if (is_dir(WP_PLUGIN_DIR . '/' . $directory)) {
                    $installed = true;
                    break;
                }
            }
            if (!$installed) {
                continue;
            }
            $credentials = $this->credentials($canonical);
            $detected[] = [
                'plugin' => $canonical,
                'version' => $this->version($canonical),
                'license' => $this->mask($credentials['license_key']),
                'site_id' => $this->mask($credentials['site_id']),
                'license_valid' => $credentials['valid'],
            ];
        }
        return $detected;
    }

    /** @return array<string, array<string, mixed>> */
    public function definitions(): array
    {
        $definitions = apply_filters('homlity_error_reporter_official_plugins', self::PLUGINS);
        return is_array($definitions) ? $definitions : self::PLUGINS;
    }

    private function mask(string $value): string
    {
        $length = strlen($value);
        if ($length === 0) {
            return '';
        }
        if ($length <= 8) {
            return str_repeat('•', $length);
        }
        return substr($value, 0, 4) . str_repeat('•', min(12, $length - 8)) . substr($value, -4);
    }
}
