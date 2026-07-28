<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
/**
 * Plugin Name: Homlity Real Estate
 * Description: Homlity Real Estate, gestor de inmuebles, asesores, SEO y GEO listo para WordPress.
 * Version:     2.3.2
 * Author:      Ecosistema Inmobiliario Homlity
 * Author URI:  https://homlity.com/
 * Plugin URI:  https://homlity.com/plugin-integracion-homlity-real-estate-para-wordpress/
 * Text Domain: homlity-real-estate
 * Domain Path: /languages
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

// Unique constants — never guarded, always point to this plugin's own directory.
define('HOMLITY_RE_PLUGIN_FILE', __FILE__);
define('HOMLITY_RE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('HOMLITY_RE_PLUGIN_URL',  plugin_dir_url(__FILE__));

if (!defined('HOMLITY_PLUGIN_FILE'))             define('HOMLITY_PLUGIN_FILE', __FILE__);
if (!defined('HOMLITY_PLUGIN_PATH'))             define('HOMLITY_PLUGIN_PATH', plugin_dir_path(__FILE__));
if (!defined('HOMLITY_PLUGIN_URL'))              define('HOMLITY_PLUGIN_URL', plugin_dir_url(__FILE__));
if (!defined('HOMLITY_PLUGIN_VERSION'))          define('HOMLITY_PLUGIN_VERSION', '2.3.2');
if (!defined('HOMLITY_PLUGIN_SLUG'))             define('HOMLITY_PLUGIN_SLUG', 'homlity-real-estate');
if (!defined('HOMLITY_PLUGIN_TEXT_DOMAIN'))      define('HOMLITY_PLUGIN_TEXT_DOMAIN', 'homlity-real-estate');
if (!defined('HOMLITY_PLUGIN_SETTINGS_OPTION'))  define('HOMLITY_PLUGIN_SETTINGS_OPTION', 'homlity_plugin_settings');
if (!defined('HOMLITY_PLUGIN_VERSION_OPTION'))   define('HOMLITY_PLUGIN_VERSION_OPTION', 'homlity_plugin_version');
if (!defined('HOMLITY_PLUGIN_NAMESPACE_PREFIX')) define('HOMLITY_PLUGIN_NAMESPACE_PREFIX', 'Homlity\\PluginInmobiliario\\');

add_action('init', static function (): void {
    load_plugin_textdomain(
        HOMLITY_PLUGIN_TEXT_DOMAIN,
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
});

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

if (!function_exists('homlity_render_simulator')) {
    function homlity_render_simulator(string $mode): string
    {
        return \Homlity\PluginInmobiliario\Services\SimulatorService::renderSimulator($mode);
    }
}

if (!function_exists('codwelt_homlity_render_simulator')) {
    function codwelt_homlity_render_simulator(string $mode): string
    {
        return homlity_render_simulator($mode);
    }
}

// Shims for functions expected by the homy theme but not loaded
// (companion plugin inactive or removed).

// ere_get_format_money() — Easy Real Estate plugin
if (!function_exists('ere_get_format_money')) {
    function ere_get_format_money($value, $decimal_num = 0, $decimal = '.', $thousands_sep = ',', $prefix = '', $suffix = ''): string
    {
        if ($value === '' || $value === null) {
            return '';
        }
        return (string) $prefix . number_format((float) $value, (int) $decimal_num, (string) $decimal, (string) $thousands_sep) . (string) $suffix;
    }
}

// homy_get_social_icon() — homy companion plugin
if (!function_exists('homy_get_social_icon')) {
    function homy_get_social_icon($icon = '', $class = ''): string
    {
        return '';
    }
}

// __DIR__ is resolved at compile time to this file's directory, so each plugin copy
// registers its own autoloader pointing to its own src/ — regardless of which copy
// set HOMLITY_PLUGIN_PATH first.
spl_autoload_register(static function (string $class): void {
    if (strpos($class, 'Homlity\\PluginInmobiliario\\') !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen('Homlity\\PluginInmobiliario\\'));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($path)) {
        require_once $path;
    }
});

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

// Bootstrap principal del plugin — guarded so only the first loaded copy runs init().
if (!defined('HOMLITY_PLUGIN_BOOTSTRAP_REGISTERED')) {
    define('HOMLITY_PLUGIN_BOOTSTRAP_REGISTERED', true);
    add_action('plugins_loaded', static function () {
        $plugin = new Homlity\PluginInmobiliario\Core\PluginBootstrap();
        $plugin->init();
    }, 20);
}

// Schema.org JSON-LD module — loaded after the main bootstrap (priority 25).
if (!defined('HOMLITY_SCHEMA_BOOTSTRAP_REGISTERED')) {
    define('HOMLITY_SCHEMA_BOOTSTRAP_REGISTERED', true);
    add_action('plugins_loaded', static function () {
        $schema_dir = __DIR__ . '/includes/schema/';
        require_once $schema_dir . 'class-homlity-schema-helpers.php';
        require_once $schema_dir . 'class-homlity-property-schema.php';
        require_once $schema_dir . 'class-homlity-property-list-schema.php';
        require_once $schema_dir . 'class-homlity-agency-schema.php';
        require_once $schema_dir . 'class-homlity-schema-manager.php';
        require_once $schema_dir . 'class-homlity-schema-admin.php';
        Homlity_Schema_Manager::init();
        Homlity_Schema_Admin::init();
    }, 25);
}

// Elementor FAQ widget module — loaded at priority 30, after Elementor itself.
if (!defined('HOMLITY_ELEMENTOR_FAQ_REGISTERED')) {
    define('HOMLITY_ELEMENTOR_FAQ_REGISTERED', true);
    add_action('plugins_loaded', static function () {
        require_once __DIR__ . '/includes/elementor/class-homlity-elementor-manager.php';
        Homlity_Elementor_Manager::init();
    }, 30);
}

// Consignment form module — loaded at priority 35.
if (!defined('HOMLITY_CONSIGNMENT_BOOTSTRAP_REGISTERED')) {
    define('HOMLITY_CONSIGNMENT_BOOTSTRAP_REGISTERED', true);
    add_action('plugins_loaded', static function () {
        $dir = __DIR__ . '/includes/consignment/';
        require_once $dir . 'class-homlity-consignment-validator.php';
        require_once $dir . 'class-homlity-consignment-payload-builder.php';
        require_once $dir . 'class-homlity-consignment-rest-controller.php';
        require_once $dir . 'class-homlity-consignment-notifications.php';
        require_once $dir . 'class-homlity-consignment-media-handler.php';
        require_once $dir . 'class-homlity-consignment-admin.php';
        require_once $dir . 'class-homlity-consignment-manager.php';
        Homlity_Consignment_Manager::init();
    }, 35);
}

// register_activation_hook is keyed to __FILE__, so it only fires for its own copy.
register_activation_hook(__FILE__, static function () {
    // Ensure CPT and taxonomies exist before seeding terms.
    (new Homlity\PluginInmobiliario\Services\PropertyPostType())->registerPostType();
    (new Homlity\PluginInmobiliario\Services\PropertyTaxonomies())->registerTaxonomies();
    (new Homlity\PluginInmobiliario\Services\CapabilityService())->ensureCaps();

    (new Homlity\PluginInmobiliario\Services\DataSeederService())->seed();
    update_option('homlity_plugin_builder_setup_pending', '1');

    // Create homologation table (safe: uses IF NOT EXISTS via dbDelta).
    Homlity\PluginInmobiliario\Homologation\HomologationRepository::createTable();

    // Register /llms-full.txt rewrite rule before flushing.
    Homlity\PluginInmobiliario\Services\Ai\LlmsFullService::activate();

    // Auto-create the public consignment page on first activation.
    // Uses a self-contained helper so the full manager doesn't need to be loaded.
    homlity_activation_create_consignment_page();

    flush_rewrite_rules();
});

// Some builders register their template post types after plugin activation.
// Complete the selected builder setup on the first regular WordPress init.
add_action('init', static function (): void {
    if ((string) get_option('homlity_plugin_builder_setup_pending', '') !== '1') {
        return;
    }

    (new Homlity\PluginInmobiliario\Services\DataSeederService())->seedBuilderTemplates();
    delete_option('homlity_plugin_builder_setup_pending');
}, 99);

/**
 * Creates the consignment page during plugin activation without requiring the full
 * consignment manager (which loads on plugins_loaded, after the activation hook).
 */
function homlity_activation_create_consignment_page(): void
{
    $option = 'homlity_consignment_page_id';
    $existing_id = (int) get_option($option, 0);

    if ($existing_id > 0) {
        $status = get_post_status($existing_id);
        if ($status !== false && $status !== 'trash') {
            return; // Page already exists
        }
    }

    $page_id = wp_insert_post([
        'post_type'    => 'page',
        'post_title'   => 'Consigna tu Inmueble',
        'post_name'    => 'consigna-tu-inmueble',
        'post_content' => '[homlity_consignment_form]',
        'post_status'  => 'publish',
        'post_author'  => (int) get_option('default_post_author', 1),
    ]);

    if (!is_wp_error($page_id) && $page_id > 0) {
        update_option($option, $page_id, false);
    }
}

register_deactivation_hook(__FILE__, static function () {
    Homlity\PluginInmobiliario\Services\Ai\LlmsFullService::deactivate();
});
