<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Core;

use Homlity\PluginInmobiliario\Core\ScheduledHooks;
use Homlity\PluginInmobiliario\ErrorReporting\OfficialPluginRegistry;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Auditoría de los cron propios: previene en nuestro propio código el patrón que
 * originó la incidencia de hcap_update_maxmind_db (acción programada que
 * sobrevive al código que debía atenderla).
 */
final class ScheduledHooksAuditTest extends TestCase
{
    /**
     * @dataProvider homlityScheduledHooksProvider
     */
    public function testCadaCronPropioEsAtribuibleAlPluginQueLoProgramaa(string $hook): void
    {
        self::assertSame(
            'homlity-real-estate',
            (new OfficialPluginRegistry())->originForHook($hook),
            "El hook {$hook} no es reconocible como propio: declara su prefijo en OfficialPluginRegistry"
        );
    }

    /**
     * @dataProvider homlityScheduledHooksProvider
     */
    public function testCadaCronPropioTieneUnCallbackRegistradoEnElCodigoDelPlugin(string $hook): void
    {
        $sources = [];
        $directory = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(HOMLITY_PLUGIN_PATH . 'src'));
        foreach ($directory as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $sources[] = (string) file_get_contents($file->getPathname());
            }
        }
        $code = implode("\n", $sources);

        // El callback se registra por constante, no por literal: basta con que el
        // hook se programe y se enganche desde el mismo servicio.
        self::assertMatchesRegularExpression(
            '/add_action\(\s*(self::CRON_HOOK|[A-Za-z]+::CRON_HOOK|\'' . preg_quote($hook, '/') . '\')/',
            $code,
            "Ningún servicio registra un callback para {$hook}"
        );
    }

    /** @return array<string,array{0:string}> */
    public static function homlityScheduledHooksProvider(): array
    {
        $cases = [];
        foreach (ScheduledHooks::owned() as $hook) {
            $cases[$hook] = [$hook];
        }

        return $cases;
    }

    public function testLaLimpiezaCentralizadaDesprogramaTodosLosCronsPropios(): void
    {
        foreach (ScheduledHooks::owned() as $hook) {
            wp_schedule_event(time(), 'hourly', $hook);
        }
        WpStubs::$cronEvents['hcap_update_maxmind_db'] = time(); // cron ajeno

        ScheduledHooks::clearAll();

        self::assertSame(
            ['hcap_update_maxmind_db'],
            array_keys(WpStubs::$cronEvents),
            'clearAll() debe borrar todos los crons propios y ninguno ajeno'
        );
    }

    public function testLaDesactivacionDelPluginInvocaLaLimpiezaCentralizada(): void
    {
        $plugin = (string) file_get_contents(HOMLITY_PLUGIN_PATH . 'plugin-inmobiliario.php');

        self::assertMatchesRegularExpression(
            '/register_deactivation_hook\(.*ScheduledHooks::clearAll\(\)/s',
            $plugin,
            'La desactivación debe limpiar todos los crons propios, no sólo uno'
        );
    }

    public function testNingunCronPropioQuedaFueraDelInventario(): void
    {
        $inventory = ScheduledHooks::owned();
        $scheduled = [];
        $directory = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(HOMLITY_PLUGIN_PATH . 'src'));
        foreach ($directory as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $code = (string) file_get_contents($file->getPathname());
            if (preg_match_all('/wp_schedule_(?:single_)?event\([^;]*?,\s*([^,)]+)\)/s', $code, $matches)) {
                foreach ($matches[1] as $argument) {
                    $scheduled[] = trim($argument);
                }
            }
        }

        self::assertSame(
            count($scheduled),
            count($inventory),
            'Hay ' . count($scheduled) . ' llamadas a wp_schedule_event en src/ y '
            . count($inventory) . ' hooks inventariados en ScheduledHooks::owned(): ' . implode(', ', $scheduled)
        );
    }
}
