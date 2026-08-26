<?php
/**
 * Simulator frontend runtime, shortcodes and legacy migration.
 */

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;

if (!defined('ABSPATH')) {
    exit;
}

class SimulatorService implements ServiceInterface
{
    public const SCRIPT_HANDLE = 'homlity-real-estate-simulator-app';
    public const STYLE_HANDLE = 'homlity-real-estate-simulator';

    public function register(): void
    {
        add_action('init', [$this, 'registerShortcodes']);
        add_action('init', [$this, 'migrateLegacySettings'], 20);
        add_action('wp_enqueue_scripts', [self::class, 'registerAssets']);
    }

    public function registerShortcodes(): void
    {
        add_shortcode('homlity_simulador', [$this, 'renderShortcode']);
        add_shortcode('homlity_simulador_venta', [$this, 'renderShortcodeSale']);
        add_shortcode('homlity_simulador_arriendo', [$this, 'renderShortcodeRent']);

        // Compatibilidad con las páginas creadas por VisualInmueble antes de
        // que los simuladores pasaran a Homlity. Esas páginas siguen guardando
        // este shortcode en Elementor y no deben editarse sitio por sitio.
        add_shortcode('visualinmu_simulador_shortcode', [$this, 'renderLegacyShortcode']);
    }

    public static function registerAssets(): void
    {
        wp_register_style(
            self::STYLE_HANDLE,
            HOMLITY_PLUGIN_URL . 'assets/css/simulator.css',
            [],
            HOMLITY_PLUGIN_VERSION
        );

        wp_register_script(
            self::SCRIPT_HANDLE,
            HOMLITY_PLUGIN_URL . 'assets/js/simulator.js',
            [],
            HOMLITY_PLUGIN_VERSION,
            true
        );
    }

    public function migrateLegacySettings(): void
    {
        if (get_option('homlity_simulators_migrated_v1')) {
            return;
        }

        $legacy = get_option('codwelt_homlity_simulators', []);
        if (!is_array($legacy) || $legacy === []) {
            update_option('homlity_simulators_migrated_v1', 1, false);
            return;
        }

        $optionName = HOMLITY_PLUGIN_SETTINGS_OPTION;
        $stored = get_option($optionName, []);
        $stored = is_array($stored) ? $stored : [];

        $currentSimulators = SimulatorSettings::merge(is_array($stored['simulators'] ?? null) ? $stored['simulators'] : []);
        $defaultSimulators = SimulatorSettings::defaults();

        if ($currentSimulators !== $defaultSimulators) {
            update_option('homlity_simulators_migrated_v1', 1, false);
            return;
        }

        $stored['simulators'] = SimulatorSettings::sanitize($legacy);
        update_option($optionName, $stored, false);
        update_option('homlity_simulators_migrated_v1', 1, false);
    }

    public static function settings(): array
    {
        $stored = get_option(HOMLITY_PLUGIN_SETTINGS_OPTION, []);
        $stored = is_array($stored) ? $stored : [];
        $simulators = is_array($stored['simulators'] ?? null) ? $stored['simulators'] : [];

        return SimulatorSettings::merge(SimulatorSettings::sanitize($simulators));
    }

    public static function renderSimulator(string $mode): string
    {
        self::registerAssets();
        wp_enqueue_style(self::STYLE_HANDLE);
        wp_enqueue_script(self::SCRIPT_HANDLE);

        $mode = self::normalizeMode($mode);
        $settings = self::settings();
        $config = [
            'arriendo' => $settings['arriendo'],
            'venta' => $settings['venta'],
            'system' => [
                'logo' => SimulatorSettings::resolveLogo(),
                'isLoggedIn' => is_user_logged_in(),
            ],
        ];

        $elementId = wp_unique_id('homlity-simulator-');
        // wp_json_encode() devuelve false si la codificación falla (UTF-8 inválido
        // en un texto de ajustes, por ejemplo). Sin este respaldo el <script> de
        // abajo saldría como `var config = ;` y rompería toda la página.
        $configJson = wp_json_encode($config);
        $configJson = is_string($configJson) ? $configJson : '{}';
        $modeJson = wp_json_encode($mode);
        $modeJson = is_string($modeJson) ? $modeJson : '"venta"';
        $intro = trim((string) ($settings[$mode]['introConceptos'] ?? ''));

        ob_start();
        if ($intro !== '') : ?>
            <div class="homlity-simulator__intro">
                <?php echo wp_kses_post(wpautop($intro)); ?>
            </div>
        <?php endif; ?>
        <div class="homlity-simulator homlity-simulator--<?php echo esc_attr($mode); ?>">
            <codwelt-simulador id="<?php echo esc_attr($elementId); ?>"></codwelt-simulador>
        </div>
        <script>
            (function () {
                var config = <?php echo $configJson; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON ya codificado con wp_json_encode(). ?>;
                var elementId = <?php echo wp_json_encode($elementId); ?>;
                var mode = <?php echo $modeJson; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON ya codificado con wp_json_encode(). ?>;

                var applyConfig = function () {
                    var el = document.getElementById(elementId);
                    if (!el) return;
                    el.modo = mode;
                    el.configuracion = config;
                };

                applyConfig();

                if (window.customElements && window.customElements.whenDefined) {
                    window.customElements.whenDefined('codwelt-simulador').then(applyConfig);
                }
            }());
        </script>
        <?php

        return (string) ob_get_clean();
    }

    public function renderShortcode(array $atts = []): string
    {
        $atts = shortcode_atts(['modo' => 'venta'], $atts, 'homlity_simulador');

        return self::renderSimulator((string) $atts['modo']);
    }

    public function renderShortcodeSale(): string
    {
        return self::renderSimulator('venta');
    }

    public function renderShortcodeRent(): string
    {
        return self::renderSimulator('arriendo');
    }

    /**
     * Renders the shortcode used by the retired VisualInmueble integration.
     *
     * Some Elementor pages contain typographic quotes in the attribute value
     * (tipo=”arriendo”), so the mode is normalized before rendering.
     */
    public function renderLegacyShortcode(array $atts = []): string
    {
        $atts = shortcode_atts([
            'tipo' => 'venta',
            'modo' => '',
        ], $atts, 'visualinmu_simulador_shortcode');

        $mode = (string) ($atts['modo'] !== '' ? $atts['modo'] : $atts['tipo']);

        return self::renderSimulator($mode);
    }

    /** @internal Normalizes both current and legacy shortcode values. */
    public static function normalizeMode(string $mode): string
    {
        $mode = trim(str_replace(['"', "'", '“', '”', '‘', '’'], '', $mode));
        $mode = strtolower($mode);

        return in_array($mode, ['arriendo', 'arrendamiento', 'renta', 'rent'], true)
            ? 'arriendo'
            : 'venta';
    }
}
