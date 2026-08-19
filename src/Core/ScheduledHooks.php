<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Core;

use Homlity\PluginInmobiliario\ErrorReporting\ErrorReporterService;
use Homlity\PluginInmobiliario\Integrations\CRM\CrmConfig;
use Homlity\PluginInmobiliario\Services\PropertyAnalyticsCleanupService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fuente única de verdad de los eventos WP-Cron que este plugin programa.
 *
 * Existe para que ningún cron propio pueda quedar programado sin dueño ni sin
 * limpieza: la desactivación los borra todos y las pruebas verifican que cada
 * hook declarado aquí es atribuible a homlity-real-estate.
 */
final class ScheduledHooks
{
    /** @return string[] */
    public static function owned(): array
    {
        return [
            CrmConfig::CRON_HOOK,
            ErrorReporterService::CRON_HOOK,
            PropertyAnalyticsCleanupService::CRON_HOOK,
        ];
    }

    /** Desprograma todos los eventos propios. Se invoca al desactivar el plugin. */
    public static function clearAll(): void
    {
        foreach (self::owned() as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }
}
