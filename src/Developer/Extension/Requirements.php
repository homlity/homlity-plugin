<?php

declare(strict_types=1);

/**
 * @package Homlity\Developer
 * @since   2.8.0
 */

namespace Homlity\Developer\Extension;

use Homlity\Developer\Api;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Immutable declaration of what an extension needs in order to boot.
 *
 * Every constraint is a minimum version and every one is optional. An empty
 * Requirements object is always satisfied.
 *
 * @since 2.8.0
 */
final class Requirements
{
    private string $homlity;
    private string $api;
    private string $php;
    private string $wordpress;

    /** @var string[] Plugin basenames, e.g. 'woocommerce/woocommerce.php'. */
    private array $plugins;

    /**
     * @param string[] $plugins
     */
    private function __construct(
        string $homlity,
        string $api,
        string $php,
        string $wordpress,
        array $plugins
    ) {
        $this->homlity   = $homlity;
        $this->api       = $api;
        $this->php       = $php;
        $this->wordpress = $wordpress;
        $this->plugins   = $plugins;
    }

    /**
     * Build a requirement set from a plain array.
     *
     * Recognised keys — every one optional:
     *   'homlity'   Minimum Homlity Real Estate version, e.g. '2.8.0'.
     *   'api'       Minimum Developer API version, e.g. '1.0.0'.
     *   'php'       Minimum PHP version, e.g. '8.1'.
     *   'wordpress' Minimum WordPress version, e.g. '6.4'.
     *   'plugins'   Plugin basenames that must be active.
     *
     * Unknown keys are ignored, so a newer extension declaring a constraint
     * this version does not understand still boots instead of failing shut.
     *
     * @since 2.8.0
     *
     * @param array<string,mixed> $requirements
     */
    public static function create(array $requirements = []): self
    {
        $plugins = [];
        foreach ((array) ($requirements['plugins'] ?? []) as $basename) {
            $basename = trim((string) $basename);
            if ($basename !== '') {
                $plugins[] = $basename;
            }
        }

        return new self(
            trim((string) ($requirements['homlity'] ?? '')),
            trim((string) ($requirements['api'] ?? '')),
            trim((string) ($requirements['php'] ?? '')),
            trim((string) ($requirements['wordpress'] ?? '')),
            $plugins
        );
    }

    /**
     * A requirement set that every install satisfies.
     *
     * @since 2.8.0
     */
    public static function none(): self
    {
        return new self('', '', '', '', []);
    }

    /** @since 2.8.0 */
    public function homlityVersion(): string
    {
        return $this->homlity;
    }

    /** @since 2.8.0 */
    public function apiVersion(): string
    {
        return $this->api;
    }

    /** @since 2.8.0 */
    public function phpVersion(): string
    {
        return $this->php;
    }

    /** @since 2.8.0 */
    public function wordPressVersion(): string
    {
        return $this->wordpress;
    }

    /**
     * @since 2.8.0
     *
     * @return string[]
     */
    public function plugins(): array
    {
        return $this->plugins;
    }

    /**
     * Whether the current environment satisfies every declared constraint.
     *
     * @since 2.8.0
     */
    public function areSatisfied(): bool
    {
        return $this->unmetRequirements() === [];
    }

    /**
     * Human-readable reasons why the environment is not good enough.
     *
     * Returns an empty array when everything is satisfied. Each entry is a
     * translated sentence suitable for an admin notice.
     *
     * @since 2.8.0
     *
     * @return string[]
     */
    public function unmetRequirements(): array
    {
        $unmet = [];

        if ($this->homlity !== '' && !Api::isVersionSupported($this->homlity)) {
            $unmet[] = sprintf(
                /* translators: 1: required version, 2: installed version. */
                __('Requiere Homlity Real Estate %1$s o superior (instalada: %2$s).', 'homlity-real-estate'),
                $this->homlity,
                Api::pluginVersion() !== '' ? Api::pluginVersion() : __('ninguna', 'homlity-real-estate')
            );
        }

        if ($this->api !== '' && !Api::isApiVersionSupported($this->api)) {
            $unmet[] = sprintf(
                /* translators: 1: required API version, 2: available API version. */
                __('Requiere la Developer API %1$s o superior (disponible: %2$s).', 'homlity-real-estate'),
                $this->api,
                Api::VERSION
            );
        }

        if ($this->php !== '' && version_compare(PHP_VERSION, $this->php, '<')) {
            $unmet[] = sprintf(
                /* translators: 1: required PHP version, 2: running PHP version. */
                __('Requiere PHP %1$s o superior (en ejecución: %2$s).', 'homlity-real-estate'),
                $this->php,
                PHP_VERSION
            );
        }

        $wordPressVersion = Api::wordPressVersion();
        if ($this->wordpress !== '' && $wordPressVersion !== '' && version_compare($wordPressVersion, $this->wordpress, '<')) {
            $unmet[] = sprintf(
                /* translators: 1: required WordPress version, 2: installed WordPress version. */
                __('Requiere WordPress %1$s o superior (instalado: %2$s).', 'homlity-real-estate'),
                $this->wordpress,
                $wordPressVersion
            );
        }

        foreach ($this->plugins as $basename) {
            if (!self::isPluginActive($basename)) {
                $unmet[] = sprintf(
                    /* translators: %s: plugin basename. */
                    __('Requiere el plugin «%s» activo.', 'homlity-real-estate'),
                    $basename
                );
            }
        }

        return $unmet;
    }

    /**
     * `is_plugin_active()` lives in an admin-only file; this works everywhere.
     */
    private static function isPluginActive(string $basename): bool
    {
        $active = (array) get_option('active_plugins', []);
        if (in_array($basename, $active, true)) {
            return true;
        }

        $network = get_site_option('active_sitewide_plugins', []);

        return is_array($network) && isset($network[$basename]);
    }
}
