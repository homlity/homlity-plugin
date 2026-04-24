<?php
/**
 * Main plugin bootstrap.
 */

namespace Homlity\PluginInmobiliario\Core;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Services\CurrencyService;
use Homlity\PluginInmobiliario\Services\I18nService;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\SeoService;
use Homlity\PluginInmobiliario\Services\SeoIntegrationService;
use Homlity\PluginInmobiliario\Services\SettingsService;
use Homlity\PluginInmobiliario\Services\TemplateService;
use Homlity\PluginInmobiliario\Services\ElementorIntegrationService;
use Homlity\PluginInmobiliario\Services\VersionService;
use Homlity\PluginInmobiliario\Services\AdminMenuService;
use Homlity\PluginInmobiliario\Services\CapabilityService;
use Homlity\PluginInmobiliario\Services\LocationMetaService;
use Homlity\PluginInmobiliario\Services\UserMetaService;

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
