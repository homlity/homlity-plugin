<?php
/**
 * Plugin Name: InmoPress Listings Inmobiliaria
 * Description: InmoPress Listings Inmobiliaria, gestor de inmuebles, asesores y SEO listo para WordPress.
 * Version:     0.2.8
 * Author:      Codwelt SAS
 * Plugin URI:  https://codwelt.com
 * Text Domain: inmopress-listings-inmobiliaria
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PLUGIN_INMOBILIARIO_PATH', plugin_dir_path(__FILE__));
define('PLUGIN_INMOBILIARIO_URL', plugin_dir_url(__FILE__));
define('PLUGIN_INMOBILIARIO_VERSION', '0.2.8');
define('PLUGIN_INMOBILIARIO_SLUG', 'inmopress-listings-inmobiliaria');

// Autoload de Composer
if (file_exists(PLUGIN_INMOBILIARIO_PATH . 'vendor/autoload.php')) {
    require PLUGIN_INMOBILIARIO_PATH . 'vendor/autoload.php';
}

// Bootstrap principal del plugin
add_action('plugins_loaded', static function () {
    $plugin = new Codwelt\PluginInmobiliario\Core\PluginBootstrap();
    $plugin->init();
});

register_activation_hook(__FILE__, static function () {
    // Ensure CPT and taxonomies exist before seeding terms.
    (new Codwelt\PluginInmobiliario\Services\PropertyPostType())->registerPostType();
    (new Codwelt\PluginInmobiliario\Services\PropertyTaxonomies())->registerTaxonomies();
    (new Codwelt\PluginInmobiliario\Services\CapabilityService())->ensureCaps();

    (new Codwelt\PluginInmobiliario\Services\DataSeederService())->seed();

    flush_rewrite_rules();
});
