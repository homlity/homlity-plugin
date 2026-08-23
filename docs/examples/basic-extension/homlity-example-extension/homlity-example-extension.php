<?php

declare(strict_types=1);

/**
 * Plugin Name:       Homlity Example Extension
 * Description:       Extensión de ejemplo para Homlity Real Estate: registra una extensión, escucha el ciclo de vida de los inmuebles y usa un filtro público.
 * Version:           1.0.0
 * Author:            Ecosistema Inmobiliario Homlity
 * Author URI:        https://homlity.com/
 * Requires at least: 5.8
 * Requires PHP:      8.0
 * Text Domain:       homlity-example-extension
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package HomlityExample
 */

if (!defined('ABSPATH')) {
    exit;
}

const HOMLITY_EXAMPLE_VERSION = '1.0.0';
const HOMLITY_EXAMPLE_FILE    = __FILE__;

/** Versión mínima de Homlity Real Estate con la que esta extensión funciona. */
const HOMLITY_EXAMPLE_REQUIRES_HOMLITY = '2.8.0';

require_once __DIR__ . '/src/Plugin.php';

/**
 * Arranque.
 *
 * Prioridad 21: Homlity registra su núcleo en `plugins_loaded` con prioridad
 * 20, así que a partir de la 21 la Developer API existe. La ventana de
 * registro de extensiones (`homlity/extensions/register`) se abre en la 25,
 * de modo que el `add_action` de más abajo llega a tiempo.
 */
add_action('plugins_loaded', static function (): void {
    // WordPress no garantiza el orden de carga de los plugins, así que el
    // helper puede no existir todavía aunque Homlity esté instalado.
    if (!function_exists('homlity_is_available') || !homlity_is_available()) {
        add_action('admin_notices', 'homlity_example_notice_missing');

        return;
    }

    // La comprobación de versión va aquí, no dentro de la extensión: si la
    // interfaz `ExtensionInterface` todavía no existe en esta versión de
    // Homlity, instanciar la clase sería un fatal error.
    if (!homlity_is_version_supported(HOMLITY_EXAMPLE_REQUIRES_HOMLITY)) {
        add_action('admin_notices', 'homlity_example_notice_outdated');

        return;
    }

    add_action('homlity/extensions/register', static function ($registry): void {
        $registry->register(new HomlityExample\Plugin());
    });
}, 21);

/**
 * Aviso cuando Homlity Real Estate no está instalado o activo.
 */
function homlity_example_notice_missing(): void
{
    if (!current_user_can('activate_plugins')) {
        return;
    }

    printf(
        '<div class="notice notice-warning"><p>%s</p></div>',
        esc_html__(
            'Homlity Example Extension necesita el plugin Homlity Real Estate activo.',
            'homlity-example-extension'
        )
    );
}

/**
 * Aviso cuando Homlity Real Estate está activo pero es demasiado antiguo.
 */
function homlity_example_notice_outdated(): void
{
    if (!current_user_can('activate_plugins')) {
        return;
    }

    printf(
        '<div class="notice notice-warning"><p>%s</p></div>',
        esc_html(
            sprintf(
                /* translators: 1: versión requerida, 2: versión instalada. */
                __(
                    'Homlity Example Extension necesita Homlity Real Estate %1$s o superior. Versión instalada: %2$s.',
                    'homlity-example-extension'
                ),
                HOMLITY_EXAMPLE_REQUIRES_HOMLITY,
                homlity_version() !== '' ? homlity_version() : '—'
            )
        )
    );
}

/**
 * Activación.
 *
 * Se ejecuta antes de que Homlity haya cargado, así que aquí no se puede usar
 * la Developer API: sólo se comprueba lo que WordPress sabe por sí mismo.
 */
register_activation_hook(__FILE__, static function (): void {
    if (version_compare(PHP_VERSION, '8.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('Homlity Example Extension necesita PHP 8.0 o superior.', 'homlity-example-extension'),
            '',
            ['back_link' => true]
        );
    }

    add_option('homlity_example_synced_log', []);
});

/**
 * Desactivación: se limpia lo que se creó al activar.
 */
register_deactivation_hook(__FILE__, static function (): void {
    delete_option('homlity_example_synced_log');
});
