<?php
/**
 * Plugin Name: Homlity Plugin
 * Description: Homlity Plugin, gestor de inmuebles, asesores y SEO listo para WordPress.
 * Version:     0.3.0
 * Author:      Ecosistema Inmobiliario Homlity
 * Plugin URI:  https://github.com/homlity/homlity-plugin
 * Text Domain: homlity-plugin
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('HOMLITY_PLUGIN_FILE', __FILE__);
define('HOMLITY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('HOMLITY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HOMLITY_PLUGIN_VERSION', '0.3.0');
define('HOMLITY_PLUGIN_SLUG', 'homlity-plugin');
define('HOMLITY_PLUGIN_TEXT_DOMAIN', 'homlity-plugin');
define('HOMLITY_PLUGIN_SETTINGS_OPTION', 'homlity_plugin_settings');
define('HOMLITY_PLUGIN_VERSION_OPTION', 'homlity_plugin_version');
define('HOMLITY_PLUGIN_NAMESPACE_PREFIX', 'Homlity\\PluginInmobiliario\\');

if (!defined('PLUGIN_INMOBILIARIO_PATH')) {
    define('PLUGIN_INMOBILIARIO_PATH', HOMLITY_PLUGIN_PATH);
}
if (!defined('PLUGIN_INMOBILIARIO_URL')) {
    define('PLUGIN_INMOBILIARIO_URL', HOMLITY_PLUGIN_URL);
}
if (!defined('PLUGIN_INMOBILIARIO_VERSION')) {
    define('PLUGIN_INMOBILIARIO_VERSION', HOMLITY_PLUGIN_VERSION);
}
if (!defined('PLUGIN_INMOBILIARIO_SLUG')) {
    define('PLUGIN_INMOBILIARIO_SLUG', HOMLITY_PLUGIN_SLUG);
}

if (!function_exists('homlity_plugin_get_option')) {
    function homlity_plugin_get_option(string $optionName, string $legacyOptionName, $default = false)
    {
        $value = get_option($optionName, null);
        if ($value !== null && $value !== false) {
            return $value;
        }

        $legacyValue = get_option($legacyOptionName, null);
        if ($legacyValue !== null && $legacyValue !== false) {
            return $legacyValue;
        }

        return $default;
    }
}

if (!function_exists('homlity_plugin_update_option')) {
    function homlity_plugin_update_option(string $optionName, string $legacyOptionName, $value): bool
    {
        $updated = update_option($optionName, $value);
        update_option($legacyOptionName, $value);

        return $updated;
    }
}

if (!function_exists('homlity_plugin_maybe_migrate_option')) {
    function homlity_plugin_maybe_migrate_option(string $legacyOptionName, string $optionName): void
    {
        $current = get_option($optionName, null);
        if ($current !== null && $current !== false) {
            return;
        }

        $legacyValue = get_option($legacyOptionName, null);
        if ($legacyValue === null || $legacyValue === false) {
            return;
        }

        update_option($optionName, $legacyValue);
    }
}

if (!function_exists('homlity_plugin_apply_filters')) {
    function homlity_plugin_apply_filters(string $tag, ?string $legacyTag, ...$args)
    {
        $value = apply_filters_ref_array($tag, $args);
        if (!$legacyTag || $legacyTag === $tag) {
            return $value;
        }

        $bridgeState = $GLOBALS['homlity_plugin_filter_bridge'] ?? [];
        $bridgeState[$legacyTag] = true;
        $GLOBALS['homlity_plugin_filter_bridge'] = $bridgeState;

        $args[0] = $value;
        $value = apply_filters_ref_array($legacyTag, $args);

        unset($bridgeState[$legacyTag]);
        $GLOBALS['homlity_plugin_filter_bridge'] = $bridgeState;

        return $value;
    }
}

spl_autoload_register(static function (string $class): void {
    if (strpos($class, HOMLITY_PLUGIN_NAMESPACE_PREFIX) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen(HOMLITY_PLUGIN_NAMESPACE_PREFIX));
    $path = HOMLITY_PLUGIN_PATH . 'src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($path)) {
        require_once $path;
    }
});

// Autoload de Composer
if (file_exists(HOMLITY_PLUGIN_PATH . 'vendor/autoload.php')) {
    require HOMLITY_PLUGIN_PATH . 'vendor/autoload.php';
}

// Bootstrap principal del plugin
add_action('plugins_loaded', static function () {
    $plugin = new Homlity\PluginInmobiliario\Core\PluginBootstrap();
    $plugin->init();
});

register_activation_hook(__FILE__, static function () {
    // Ensure CPT and taxonomies exist before seeding terms.
    (new Homlity\PluginInmobiliario\Services\PropertyPostType())->registerPostType();
    (new Homlity\PluginInmobiliario\Services\PropertyTaxonomies())->registerTaxonomies();
    (new Homlity\PluginInmobiliario\Services\CapabilityService())->ensureCaps();

    (new Homlity\PluginInmobiliario\Services\DataSeederService())->seed();

    flush_rewrite_rules();
});
