<?php
/**
 * Main plugin bootstrap.
 */

namespace Codwelt\PluginInmobiliario\Core;

use Codwelt\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Codwelt\PluginInmobiliario\Services\CurrencyService;
use Codwelt\PluginInmobiliario\Services\I18nService;
use Codwelt\PluginInmobiliario\Services\PropertyPostType;
use Codwelt\PluginInmobiliario\Services\PropertyTaxonomies;
use Codwelt\PluginInmobiliario\Services\SeoService;
use Codwelt\PluginInmobiliario\Services\SeoIntegrationService;
use Codwelt\PluginInmobiliario\Services\SettingsService;
use Codwelt\PluginInmobiliario\Services\TemplateService;
use Codwelt\PluginInmobiliario\Services\ElementorIntegrationService;
use Codwelt\PluginInmobiliario\Services\VersionService;
use Codwelt\PluginInmobiliario\Services\AdminMenuService;
use Codwelt\PluginInmobiliario\Services\CapabilityService;
use Codwelt\PluginInmobiliario\Services\LocationMetaService;
use Codwelt\PluginInmobiliario\Services\UserMetaService;

if (!defined('ABSPATH')) {
    exit;
}

class PluginBootstrap
{
    /**
     * @var ServiceInterface[]
     */
    private array $services = [];

    public function init(): void
    {
        $this->services = [
            new I18nService(),
            new VersionService(),
            new SettingsService(),
            new PropertyPostType(),
            new PropertyTaxonomies(),
            new AdminMenuService(),
            new CapabilityService(),
            new LocationMetaService(),
            new UserMetaService(),
            new CurrencyService(),
            new SeoService(),
            new SeoIntegrationService(),
            new TemplateService(),
            new ElementorIntegrationService(),
        ];

        foreach ($this->services as $service) {
            $service->register();
        }
    }
}
