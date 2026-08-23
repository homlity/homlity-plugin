<?php

declare(strict_types=1);

/**
 * Bootstrap de las pruebas unitarias del plugin.
 *
 * No requiere una instalación de WordPress: define las constantes del plugin
 * y carga stubs de las funciones de WordPress que usan las clases bajo prueba.
 */

$pluginRoot = dirname(__DIR__);

$autoload = $pluginRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "No se encontró vendor/autoload.php. Ejecuta: composer install\n");
    exit(1);
}
require $autoload;

// Constantes de WordPress ------------------------------------------------
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname($pluginRoot, 3) . '/');
}
if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}
if (!defined('WP_PLUGIN_DIR')) {
    // Árbol de plugins simulado (tests/Fixtures/plugins): permite reproducir
    // rutas reales —incluido el vendor/ donde Action Scheduler crea sus
    // excepciones— sin depender de la instalación de WordPress del entorno.
    define('WP_PLUGIN_DIR', __DIR__ . '/Fixtures/plugins');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
if (!defined('COOKIEPATH')) {
    define('COOKIEPATH', '/');
}
if (!defined('COOKIE_DOMAIN')) {
    define('COOKIE_DOMAIN', '');
}
if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}
if (!defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 604800);
}

// Constantes propias del plugin -----------------------------------------
if (!defined('HOMLITY_PLUGIN_FILE')) {
    define('HOMLITY_PLUGIN_FILE', $pluginRoot . '/plugin-inmobiliario.php');
}
if (!defined('HOMLITY_PLUGIN_PATH')) {
    define('HOMLITY_PLUGIN_PATH', $pluginRoot . '/');
}
if (!defined('HOMLITY_PLUGIN_URL')) {
    define('HOMLITY_PLUGIN_URL', 'http://example.test/wp-content/plugins/homlity-real-estate/');
}
if (!defined('HOMLITY_PLUGIN_VERSION')) {
    // La versión real de la cabecera del plugin, no una etiqueta inventada:
    // la Developer API la compara con version_compare() y una cadena que no
    // sea semver haría pasar pruebas que en producción fallarían.
    $pluginHeader = (string) file_get_contents($pluginRoot . '/plugin-inmobiliario.php');
    preg_match('/^\s*\*\s*Version:\s*(.+)$/mi', $pluginHeader, $versionMatch);

    define('HOMLITY_PLUGIN_VERSION', trim($versionMatch[1] ?? '0.0.0'));
}
if (!defined('HOMLITY_API_VERSION')) {
    define('HOMLITY_API_VERSION', '1.0.0');
}
if (!defined('HOMLITY_PLUGIN_SLUG')) {
    define('HOMLITY_PLUGIN_SLUG', 'homlity-real-estate');
}
if (!defined('HOMLITY_PLUGIN_TEXT_DOMAIN')) {
    define('HOMLITY_PLUGIN_TEXT_DOMAIN', 'homlity-real-estate');
}
if (!defined('HOMLITY_PLUGIN_SETTINGS_OPTION')) {
    define('HOMLITY_PLUGIN_SETTINGS_OPTION', 'homlity_plugin_settings');
}
if (!defined('HOMLITY_PLUGIN_VERSION_OPTION')) {
    define('HOMLITY_PLUGIN_VERSION_OPTION', 'homlity_plugin_version');
}
if (!defined('HOMLITY_PLUGIN_NAMESPACE_PREFIX')) {
    define('HOMLITY_PLUGIN_NAMESPACE_PREFIX', 'Homlity\\PluginInmobiliario\\');
}

// Los helpers globales de la Developer API. En producción los carga el archivo
// principal del plugin; aquí hace falta cargarlos a mano, y sólo después de
// definir ABSPATH: el archivo aborta si se incluye fuera de WordPress.
require_once $pluginRoot . '/src/Developer/functions.php';

require __DIR__ . '/Support/FakeSqlEngine.php';
require __DIR__ . '/Support/wp-functions.php';
require __DIR__ . '/Support/service-namespace-functions.php';
require __DIR__ . '/Support/action-scheduler-stubs.php';
