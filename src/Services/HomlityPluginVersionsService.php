<?php
/**
 * Version catalogue and controlled installer for active Homlity plugins.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class HomlityPluginVersionsService implements ServiceInterface
{
    private const API_BASE = 'https://homi.homlity.com/api/v1/plugins/';
    private const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

    /** @var array<string, array<string, string>> */
    private const PRODUCTS = [
        'homlity-real-estate' => [
            'plugin' => 'homlity-real-estate/plugin-inmobiliario.php',
            'license_option' => 'homlity_license_key',
            'fingerprint_option' => 'plugin_homlity_sync_site_fingerprint',
        ],
        'homlity-sync' => [
            'plugin' => 'homlity-sync/plugin-homlity-sync.php',
            'license_option' => 'homlity_license_key',
            'fingerprint_option' => 'plugin_homlity_sync_site_fingerprint',
        ],
        'homlity-wasi' => [
            'plugin' => 'plugin-wasi-sync/plugin-wasi-sync.php',
            'license_option' => 'plugin_wasi_sync_license_key',
            'fingerprint_option' => 'plugin_wasi_sync_site_fingerprint',
        ],
        'homlity-simi' => [
            'plugin' => 'plugin-simi-sync/plugin-simi-sync.php',
            'license_option' => 'plugin_simi_sync_license_key',
            'fingerprint_option' => 'plugin_simi_sync_site_fingerprint',
        ],
        'homlity-softinm' => [
            'plugin' => 'plugin-softinm-sync/plugin-softinm-sync.php',
            'license_option' => 'plugin_softinm_sync_license_key',
            'fingerprint_option' => 'plugin_softinm_sync_site_fingerprint',
        ],
    ];

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('homlity-real-estate/v1', '/plugin-versions', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getPluginsResponse'],
            'permission_callback' => [$this, 'canUpdatePlugins'],
            'args' => [
                'refresh' => [
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
            ],
        ]);

        register_rest_route('homlity-real-estate/v1', '/plugin-versions/install', [
            'methods' => \WP_REST_Server::EDITABLE,
            'callback' => [$this, 'installVersionResponse'],
            'permission_callback' => [$this, 'canUpdatePlugins'],
            'args' => [
                'plugin' => [
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'version' => [
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'required' => true,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
            ],
        ]);
    }

    public function canUpdatePlugins(): bool
    {
        return current_user_can('update_plugins');
    }

    public function getPluginsResponse(\WP_REST_Request $request): \WP_REST_Response
    {
        $refresh = (bool) $request->get_param('refresh');
        $plugins = [];

        foreach ($this->activeHomlityPlugins() as $plugin) {
            $catalog = $this->getCatalog($plugin, $refresh);
            $plugins[] = $this->publicPluginPayload($plugin, $catalog);
        }

        return new \WP_REST_Response([
            'plugins' => $plugins,
            'generated_at' => current_time('c'),
            'history_endpoint' => self::API_BASE . '{slug}/versions',
            'message' => $plugins === []
                ? __('No se encontraron plugins Homlity activos.', 'homlity-real-estate')
                : '',
        ]);
    }

    /**
     * @return \WP_REST_Response|\WP_Error
     */
    public function installVersionResponse(\WP_REST_Request $request)
    {
        global $wp_version;

        if (version_compare((string) $wp_version, '6.3', '<')) {
            return new \WP_Error(
                'homlity_version_wordpress_too_old',
                __('El gestor de versiones requiere WordPress 6.3 o superior para garantizar el respaldo temporal y la restauración automática.', 'homlity-real-estate'),
                ['status' => 409]
            );
        }

        if ($request->get_param('confirm') !== true) {
            return new \WP_Error(
                'homlity_version_confirmation_required',
                __('Debes confirmar expresamente el cambio de versión.', 'homlity-real-estate'),
                ['status' => 400]
            );
        }

        $pluginFile = $this->normalizePluginFile((string) $request->get_param('plugin'));
        $targetVersion = trim((string) $request->get_param('version'));
        if ($pluginFile === '' || !$this->isValidVersion($targetVersion)) {
            return new \WP_Error(
                'homlity_version_invalid_request',
                __('El plugin o la versión solicitada no son válidos.', 'homlity-real-estate'),
                ['status' => 400]
            );
        }

        $plugin = $this->findActivePlugin($pluginFile);
        if ($plugin === null) {
            return new \WP_Error(
                'homlity_version_plugin_not_active',
                __('El plugin solicitado no es un plugin Homlity activo.', 'homlity-real-estate'),
                ['status' => 404]
            );
        }
        if (!empty($plugin['network_active']) && !current_user_can('manage_network_plugins')) {
            return new \WP_Error(
                'homlity_version_network_permission_required',
                __('Solo un administrador de la red puede cambiar la versión de un plugin activo en toda la red.', 'homlity-real-estate'),
                ['status' => 403]
            );
        }

        if (version_compare($targetVersion, $plugin['version'], '==')) {
            return new \WP_Error(
                'homlity_version_already_installed',
                __('La versión solicitada ya está instalada.', 'homlity-real-estate'),
                ['status' => 409]
            );
        }

        // Always bypass the cache here: an install must be authorized by a fresh Homi response.
        $catalog = $this->getCatalog($plugin, true);
        $release = $this->findRelease($catalog['versions'], $targetVersion);
        if ($release === null || empty($release['installable'])) {
            return new \WP_Error(
                'homlity_version_not_authorized',
                __('Homi no autorizó esa versión para este plugin o no entregó un paquete instalable.', 'homlity-real-estate'),
                ['status' => 409]
            );
        }

        $compatibilityError = $this->compatibilityError($release);
        if ($compatibilityError !== '') {
            return new \WP_Error('homlity_version_incompatible', $compatibilityError, ['status' => 409]);
        }

        $isDowngrade = version_compare($targetVersion, $plugin['version'], '<');
        $checksum = strtolower((string) ($release['checksum_sha256'] ?? ''));
        if ($isDowngrade && !preg_match('/^[a-f0-9]{64}$/', $checksum)) {
            return new \WP_Error(
                'homlity_version_checksum_required',
                __('Por seguridad, Homi debe publicar el checksum SHA-256 para permitir un downgrade.', 'homlity-real-estate'),
                ['status' => 409]
            );
        }

        $packageUrl = (string) ($release['download_url'] ?? '');
        if (!$this->isSecurePackageUrl($packageUrl)) {
            return new \WP_Error(
                'homlity_version_package_invalid',
                __('Homi no entregó una URL HTTPS válida para el paquete.', 'homlity-real-estate'),
                ['status' => 409]
            );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $lock = \WP_Upgrader::create_lock('upgrader', 15 * MINUTE_IN_SECONDS);
        if (!$lock) {
            return new \WP_Error(
                'homlity_version_upgrader_locked',
                __('Hay otra actualización de WordPress en curso. Intenta nuevamente en unos minutos.', 'homlity-real-estate'),
                ['status' => 409]
            );
        }

        $downloadedFile = '';
        try {
            $download = download_url($packageUrl, 300);
            if (is_wp_error($download)) {
                return new \WP_Error(
                    'homlity_version_download_failed',
                    sprintf(
                        /* translators: %s: download error. */
                        __('No fue posible descargar el paquete: %s', 'homlity-real-estate'),
                        $download->get_error_message()
                    ),
                    ['status' => 502]
                );
            }
            $downloadedFile = (string) $download;

            if ($checksum !== '' && !hash_equals($checksum, strtolower((string) hash_file('sha256', $downloadedFile)))) {
                return new \WP_Error(
                    'homlity_version_checksum_mismatch',
                    __('La verificación SHA-256 del paquete falló. No se modificó el plugin.', 'homlity-real-estate'),
                    ['status' => 409]
                );
            }

            $result = $this->runUpgrade($plugin, $release, $downloadedFile);
            if (is_wp_error($result)) {
                return new \WP_Error(
                    'homlity_version_install_failed',
                    $result->get_error_message(),
                    ['status' => 500]
                );
            }
            if ($result !== true) {
                return new \WP_Error(
                    'homlity_version_install_failed',
                    __('WordPress no pudo completar el cambio de versión.', 'homlity-real-estate'),
                    ['status' => 500]
                );
            }

            wp_clean_plugins_cache(true);
            $installedData = get_plugin_data(WP_PLUGIN_DIR . '/' . $pluginFile, false, false);
            $installedVersion = (string) ($installedData['Version'] ?? '');
            if (!version_compare($installedVersion, $targetVersion, '==')) {
                return new \WP_Error(
                    'homlity_version_installed_version_mismatch',
                    __('El paquete se instaló, pero su versión no coincide con la solicitada. Revisa el plugin antes de continuar.', 'homlity-real-estate'),
                    ['status' => 500, 'installed_version' => $installedVersion]
                );
            }

            return new \WP_REST_Response([
                'success' => true,
                'plugin' => $pluginFile,
                'previous_version' => $plugin['version'],
                'installed_version' => $installedVersion,
                'direction' => $isDowngrade ? 'downgrade' : 'upgrade',
                'message' => sprintf(
                    /* translators: 1: plugin name, 2: installed version. */
                    __('%1$s quedó en la versión %2$s.', 'homlity-real-estate'),
                    $plugin['name'],
                    $installedVersion
                ),
            ]);
        } finally {
            if ($downloadedFile !== '' && file_exists($downloadedFile)) {
                wp_delete_file($downloadedFile);
            }
            \WP_Upgrader::release_lock('upgrader');
        }
    }

    /**
     * @param array<string, mixed> $plugin
     * @param array<string, mixed> $release
     * @return bool|\WP_Error
     */
    private function runUpgrade(array $plugin, array $release, string $downloadedFile)
    {
        $pluginFile = (string) $plugin['plugin'];
        $update = new \stdClass();
        $update->slug = (string) $plugin['product_slug'];
        $update->plugin = $pluginFile;
        $update->new_version = (string) $release['version'];
        $update->package = $downloadedFile;
        $update->url = 'https://homlity.com/';
        $update->requires = (string) ($release['requires_wp'] ?? '');
        $update->requires_php = (string) ($release['requires_php'] ?? '');

        $transient = get_site_transient('update_plugins');
        if (!is_object($transient)) {
            $transient = new \stdClass();
        }
        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = [];
        }
        $transient->response[$pluginFile] = $update;

        // Some Homlity plugins also populate this transient. Keep this exact, freshly
        // authorized release as the final value during this one installation.
        $pinUpdate = static function ($value) use ($pluginFile, $update) {
            if (!is_object($value)) {
                $value = new \stdClass();
            }
            if (!isset($value->response) || !is_array($value->response)) {
                $value->response = [];
            }
            $value->response[$pluginFile] = $update;
            return $value;
        };
        add_filter('pre_set_site_transient_update_plugins', $pinUpdate, PHP_INT_MAX);
        set_site_transient('update_plugins', $transient);
        remove_filter('pre_set_site_transient_update_plugins', $pinUpdate, PHP_INT_MAX);

        // Target plugin updaters may intercept downloads. A local, already verified
        // package must win so a requested downgrade cannot silently become latest.
        $useVerifiedPackage = static function ($reply, $package, $upgrader, $hookExtra) use ($pluginFile, $downloadedFile) {
            if (($hookExtra['plugin'] ?? '') === $pluginFile && $package === $downloadedFile) {
                return $downloadedFile;
            }
            return $reply;
        };
        add_filter('upgrader_pre_download', $useVerifiedPackage, 1, 4);

        // Inspect the extracted archive before WordPress moves the existing plugin
        // to its backup. This prevents a validly downloaded but mispackaged release
        // from replacing a different directory or declaring another version.
        $validateSource = function ($source, $remoteSource, $upgrader, $hookExtra) use ($pluginFile, $release) {
            if (is_wp_error($source) || ($hookExtra['plugin'] ?? '') !== $pluginFile) {
                return $source;
            }

            global $wp_filesystem;
            if (!$wp_filesystem) {
                return new \WP_Error(
                    'homlity_version_filesystem_unavailable',
                    __('No fue posible validar el contenido del paquete.', 'homlity-real-estate')
                );
            }

            $localSource = str_replace(
                $wp_filesystem->wp_content_dir(),
                trailingslashit(WP_CONTENT_DIR),
                (string) $source
            );
            $expectedDirectory = dirname($pluginFile);
            $expectedMainFile = trailingslashit($localSource) . basename($pluginFile);
            if (!is_dir($localSource)
                || basename(untrailingslashit($localSource)) !== $expectedDirectory
                || !is_file($expectedMainFile)) {
                return new \WP_Error(
                    'homlity_version_package_layout_invalid',
                    __('El paquete no corresponde a la estructura del plugin seleccionado.', 'homlity-real-estate')
                );
            }

            $packageData = get_plugin_data($expectedMainFile, false, false);
            $packageVersion = (string) ($packageData['Version'] ?? '');
            if (!version_compare($packageVersion, (string) $release['version'], '==')) {
                return new \WP_Error(
                    'homlity_version_package_version_mismatch',
                    __('La versión declarada dentro del paquete no coincide con la seleccionada.', 'homlity-real-estate')
                );
            }

            $actualCompatibility = $this->compatibilityError([
                'requires_wp' => (string) ($packageData['RequiresWP'] ?? ''),
                'requires_php' => (string) ($packageData['RequiresPHP'] ?? ''),
            ]);
            if ($actualCompatibility !== '') {
                return new \WP_Error('homlity_version_package_incompatible', $actualCompatibility);
            }

            return $source;
        };
        add_filter('upgrader_source_selection', $validateSource, 5, 4);

        $networkActive = !empty($plugin['network_active']);
        try {
            $upgrader = new \Plugin_Upgrader(new \Automatic_Upgrader_Skin());
            $result = $upgrader->upgrade($pluginFile, ['clear_update_cache' => true]);

            // Plugin_Upgrader deactivates regular updates outside cron. Restore the
            // exact previous activation scope after success or automatic rollback.
            if ($result !== true) {
                // WordPress restores its temporary backup at shutdown after a failed
                // replacement, so reactivate immediately after that restoration.
                add_action('shutdown', static function () use ($pluginFile, $networkActive): void {
                    activate_plugin($pluginFile, '', $networkActive, true);
                }, 20);
                return $result;
            }

            $activation = activate_plugin($pluginFile, '', $networkActive, true);
            if (is_wp_error($activation)) {
                return new \WP_Error(
                    'homlity_version_reactivation_failed',
                    sprintf(
                        /* translators: %s: activation error. */
                        __('La versión se instaló, pero no pudo reactivarse: %s', 'homlity-real-estate'),
                        $activation->get_error_message()
                    )
                );
            }

            return true;
        } finally {
            remove_filter('upgrader_pre_download', $useVerifiedPackage, 1);
            remove_filter('upgrader_source_selection', $validateSource, 5);
            delete_site_transient('update_plugins');
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function activeHomlityPlugins(): array
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $active = array_fill_keys((array) get_option('active_plugins', []), true);
        $networkActive = [];
        if (is_multisite()) {
            foreach ((array) get_site_option('active_sitewide_plugins', []) as $file => $timestamp) {
                $active[(string) $file] = true;
                $networkActive[(string) $file] = true;
            }
        }

        $products = apply_filters('homlity_re_plugin_version_products', self::PRODUCTS);
        $products = is_array($products) ? $products : self::PRODUCTS;
        $byFile = [];
        foreach ($products as $slug => $definition) {
            if (!is_array($definition) || empty($definition['plugin'])) {
                continue;
            }
            $byFile[(string) $definition['plugin']] = [
                'product_slug' => sanitize_key((string) $slug),
                'license_option' => sanitize_key((string) ($definition['license_option'] ?? '')),
                'fingerprint_option' => sanitize_key((string) ($definition['fingerprint_option'] ?? '')),
            ];
        }

        $result = [];
        foreach (get_plugins() as $file => $data) {
            if (!isset($active[$file])) {
                continue;
            }

            $definition = $byFile[$file] ?? null;
            if ($definition === null) {
                $definition = $this->definitionFromHeaders($file, $data);
            }
            if ($definition === null || $definition['product_slug'] === '') {
                continue;
            }

            $result[] = [
                'plugin' => $file,
                'product_slug' => $definition['product_slug'],
                'license_option' => $definition['license_option'],
                'fingerprint_option' => $definition['fingerprint_option'],
                'name' => wp_strip_all_tags((string) ($data['Name'] ?? $definition['product_slug'])),
                'version' => (string) ($data['Version'] ?? ''),
                'requires_wp' => (string) ($data['RequiresWP'] ?? ''),
                'requires_php' => (string) ($data['RequiresPHP'] ?? ''),
                'network_active' => isset($networkActive[$file]),
            ];
        }

        usort($result, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        return $result;
    }

    /**
     * @param array<string, mixed> $headers
     * @return array<string, string>|null
     */
    private function definitionFromHeaders(string $file, array $headers): ?array
    {
        $updateUri = (string) ($headers['UpdateURI'] ?? '');
        $author = (string) ($headers['AuthorName'] ?? $headers['Author'] ?? '');
        $name = (string) ($headers['Name'] ?? '');
        if (stripos($updateUri, 'homi.homlity.com') === false
            && stripos($author, 'homlity') === false
            && stripos($name, 'homlity') === false) {
            return null;
        }

        $slug = '';
        if (preg_match('~/api/v1/plugins/([^/]+)/~', $updateUri, $matches)) {
            $slug = sanitize_key($matches[1]);
        }
        if ($slug === '') {
            $slug = sanitize_key((string) dirname($file));
        }

        return [
            'product_slug' => $slug,
            'license_option' => '',
            'fingerprint_option' => '',
        ];
    }

    /** @return array<string, mixed>|null */
    private function findActivePlugin(string $pluginFile): ?array
    {
        foreach ($this->activeHomlityPlugins() as $plugin) {
            if (hash_equals((string) $plugin['plugin'], $pluginFile)) {
                return $plugin;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $plugin
     * @return array<string, mixed>
     */
    private function getCatalog(array $plugin, bool $refresh): array
    {
        $licenseOption = (string) $plugin['license_option'];
        $licenseKey = $licenseOption !== '' ? trim((string) get_option($licenseOption, '')) : '';
        if ($licenseKey === '') {
            return [
                'mode' => 'unavailable',
                'versions' => [],
                'message' => __('Este plugin no tiene una licencia de Homi configurada.', 'homlity-real-estate'),
            ];
        }

        $cacheKey = 'homlity_re_versions_' . md5($plugin['plugin'] . '|' . $plugin['version'] . '|' . $licenseKey);
        if (!$refresh) {
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $query = [
            'license_key' => $licenseKey,
            'plugin_file' => (string) $plugin['plugin'],
            'site_url' => site_url('/'),
            'home_url' => home_url('/'),
            'current_version' => (string) $plugin['version'],
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'locale' => get_locale(),
        ];
        $fingerprintOption = (string) $plugin['fingerprint_option'];
        if ($fingerprintOption !== '') {
            $fingerprint = trim((string) get_option($fingerprintOption, ''));
            if ($fingerprint !== '') {
                $query['fingerprint'] = $fingerprint;
            }
        }

        $slug = rawurlencode((string) $plugin['product_slug']);
        $history = $this->requestHomi(self::API_BASE . $slug . '/versions', $query);
        if ($history['success']) {
            $versions = $this->normalizeReleases($history['data'], $plugin);
            if ($versions !== []) {
                $result = [
                    'mode' => 'history',
                    'versions' => $versions,
                    'message' => __('Catálogo de versiones cargado desde Homi.', 'homlity-real-estate'),
                ];
                set_transient($cacheKey, $result, self::CACHE_TTL);
                return $result;
            }
        }

        $latest = $this->requestHomi(self::API_BASE . $slug . '/update-check', $query);
        $versions = $latest['success'] ? $this->normalizeReleases($latest['data'], $plugin) : [];
        $result = [
            'mode' => $versions !== [] ? 'latest' : 'unavailable',
            'versions' => $versions,
            'message' => $versions !== []
                ? __('Homi todavía no publicó el historial; solo está disponible la versión más reciente.', 'homlity-real-estate')
                : ($latest['message'] !== ''
                    ? $latest['message']
                    : __('Homi no devolvió versiones disponibles para este plugin.', 'homlity-real-estate')),
        ];
        set_transient($cacheKey, $result, self::CACHE_TTL);
        return $result;
    }

    /** @return array{success: bool, data: array<string, mixed>, message: string} */
    private function requestHomi(string $url, array $query): array
    {
        $response = (new HomiApiClient())->get($url, $query, [], 15);
        if (!$response['success'] && $response['transport_error']) {
            return [
                'success' => false,
                'data' => [],
                'message' => __('No fue posible conectar con Homi.', 'homlity-real-estate'),
            ];
        }

        return [
            'success' => $response['success'],
            'data' => $response['data'],
            'message' => $response['success']
                ? ''
                : ($response['message'] !== 'request_rejected'
                    ? $response['message']
                    : __('Homi rechazó la consulta de versiones.', 'homlity-real-estate')),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $plugin
     * @return array<int, array<string, mixed>>
     */
    private function normalizeReleases(array $payload, array $plugin): array
    {
        if (isset($payload['data']) && is_array($payload['data'])) {
            $payload = array_replace($payload, $payload['data']);
        }

        $rawReleases = [];
        if (isset($payload[0])) {
            $rawReleases = $payload;
        }
        foreach (['versions', 'releases', 'available_versions'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $rawReleases = $payload[$key];
                break;
            }
        }

        if ($rawReleases === []) {
            $pluginData = is_array($payload['plugin'] ?? null) ? $payload['plugin'] : [];
            $packageData = is_array($payload['package'] ?? null) ? $payload['package'] : [];
            $version = (string) ($pluginData['new_version'] ?? $pluginData['version'] ?? $payload['new_version'] ?? '');
            $downloadUrl = (string) ($packageData['download_url'] ?? '');
            if ($downloadUrl === '' && is_string($pluginData['package'] ?? null)) {
                $downloadUrl = (string) $pluginData['package'];
            }
            if ($downloadUrl === '' && is_string($payload['package'] ?? null)) {
                $downloadUrl = (string) $payload['package'];
            }
            if ($this->isValidVersion($version) && $downloadUrl !== '') {
                $rawReleases = [[
                    'version' => $version,
                    'download_url' => $downloadUrl,
                    'checksum_sha256' => (string) ($packageData['checksum_sha256'] ?? $payload['checksum_sha256'] ?? ''),
                    'requires_wp' => (string) ($pluginData['requires_wp'] ?? $pluginData['requires'] ?? ''),
                    'requires_php' => (string) ($pluginData['requires_php'] ?? ''),
                    'tested_wp' => (string) ($pluginData['tested_wp'] ?? $pluginData['tested'] ?? ''),
                    'released_at' => (string) ($pluginData['released_at'] ?? ''),
                    'changelog' => (string) (($payload['sections']['changelog'] ?? '') ?: ($pluginData['changelog'] ?? '')),
                ]];
            }
        }

        $normalized = [];
        foreach ($rawReleases as $key => $raw) {
            if (is_string($raw)) {
                $raw = is_string($key)
                    ? ['version' => $key, 'download_url' => $raw]
                    : ['version' => $raw, 'download_url' => ''];
            }
            if (!is_array($raw)) {
                continue;
            }
            $package = is_array($raw['package'] ?? null) ? $raw['package'] : [];
            $version = (string) ($raw['version'] ?? $raw['new_version'] ?? (is_string($key) ? $key : ''));
            if (!$this->isValidVersion($version)) {
                continue;
            }
            $downloadUrl = (string) ($raw['download_url'] ?? $raw['package_url'] ?? $package['download_url'] ?? (is_string($raw['package'] ?? null) ? $raw['package'] : ''));
            $checksum = strtolower((string) ($raw['checksum_sha256'] ?? $raw['sha256'] ?? $package['checksum_sha256'] ?? ''));
            $direction = version_compare($version, (string) $plugin['version'], '<')
                ? 'downgrade'
                : (version_compare($version, (string) $plugin['version'], '>') ? 'upgrade' : 'current');

            $release = [
                'version' => $version,
                'download_url' => $downloadUrl,
                'checksum_sha256' => $checksum,
                'requires_wp' => (string) ($raw['requires_wp'] ?? $raw['requires'] ?? ''),
                'requires_php' => (string) ($raw['requires_php'] ?? ''),
                'tested_wp' => (string) ($raw['tested_wp'] ?? $raw['tested'] ?? ''),
                'released_at' => sanitize_text_field((string) ($raw['released_at'] ?? $raw['published_at'] ?? '')),
                'changelog' => wp_kses_post((string) ($raw['changelog'] ?? $raw['sections']['changelog'] ?? '')),
                'direction' => $direction,
            ];
            $release['compatibility_error'] = $this->compatibilityError($release);
            $release['installable'] = $direction !== 'current'
                && $this->isSecurePackageUrl($downloadUrl)
                && $release['compatibility_error'] === ''
                && ($direction !== 'downgrade' || (bool) preg_match('/^[a-f0-9]{64}$/', $checksum));
            $normalized[$version] = $release;
        }

        uksort($normalized, static fn (string $a, string $b): int => version_compare($b, $a));
        return array_values($normalized);
    }

    /** @param array<string, mixed> $release */
    private function compatibilityError(array $release): string
    {
        global $wp_version;

        $requiresWp = trim((string) ($release['requires_wp'] ?? ''));
        if ($requiresWp !== '' && version_compare((string) $wp_version, $requiresWp, '<')) {
            return sprintf(
                /* translators: %s: required WordPress version. */
                __('Requiere WordPress %s o superior.', 'homlity-real-estate'),
                $requiresWp
            );
        }
        $requiresPhp = trim((string) ($release['requires_php'] ?? ''));
        if ($requiresPhp !== '' && version_compare(PHP_VERSION, $requiresPhp, '<')) {
            return sprintf(
                /* translators: %s: required PHP version. */
                __('Requiere PHP %s o superior.', 'homlity-real-estate'),
                $requiresPhp
            );
        }
        return '';
    }

    /**
     * @param array<int, array<string, mixed>> $versions
     * @return array<string, mixed>|null
     */
    private function findRelease(array $versions, string $target): ?array
    {
        foreach ($versions as $release) {
            if (isset($release['version']) && hash_equals((string) $release['version'], $target)) {
                return $release;
            }
        }
        return null;
    }

    /** @param array<string, mixed> $plugin @param array<string, mixed> $catalog */
    private function publicPluginPayload(array $plugin, array $catalog): array
    {
        $versions = [];
        foreach ((array) $catalog['versions'] as $release) {
            if (!is_array($release)) {
                continue;
            }
            $integrityVerified = !empty($release['checksum_sha256']);
            unset($release['download_url'], $release['checksum_sha256']);
            $release['integrity_verified'] = $integrityVerified;
            $versions[] = $release;
        }

        return [
            'plugin' => $plugin['plugin'],
            'product_slug' => $plugin['product_slug'],
            'name' => $plugin['name'],
            'current_version' => $plugin['version'],
            'network_active' => !empty($plugin['network_active']),
            'catalog_mode' => $catalog['mode'],
            'message' => $catalog['message'],
            'versions' => $versions,
        ];
    }

    private function normalizePluginFile(string $pluginFile): string
    {
        $pluginFile = plugin_basename($pluginFile);
        return validate_file($pluginFile) === 0 ? $pluginFile : '';
    }

    private function isValidVersion(string $version): bool
    {
        return $version !== ''
            && strlen($version) <= 64
            && (bool) preg_match('/^[0-9][0-9A-Za-z.+_-]*$/', $version);
    }

    private function isSecurePackageUrl(string $url): bool
    {
        return $url !== ''
            && strtolower((string) wp_parse_url($url, PHP_URL_SCHEME)) === 'https'
            && wp_http_validate_url($url) !== false;
    }
}
