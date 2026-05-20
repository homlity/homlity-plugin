<?php
/**
 * Main plugin bootstrap.
 */

namespace Homlity\PluginInmobiliario\Core;

use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\Homologation\HomologationAdminPage;
use Homlity\PluginInmobiliario\Homologation\HomologationRestController;
use Homlity\PluginInmobiliario\Integrations\CF7\CF7IntegrationService;
use Homlity\PluginInmobiliario\Integrations\Divi\DiviIntegrationService;
use Homlity\PluginInmobiliario\Integrations\Shortcode\ShortcodeIntegrationService;
use Homlity\PluginInmobiliario\Integrations\WPBakery\WPBakeryIntegrationService;
use Homlity\PluginInmobiliario\Services\Ai\LlmsFullAdminService;
use Homlity\PluginInmobiliario\Services\Ai\LlmsFullService;
use Homlity\PluginInmobiliario\Services\AdminBarService;
use Homlity\PluginInmobiliario\Services\AdminMenuService;
use Homlity\PluginInmobiliario\Services\CapabilityService;
use Homlity\PluginInmobiliario\Services\CrmIntegrationService;
use Homlity\PluginInmobiliario\Services\CrmAdminService;
use Homlity\PluginInmobiliario\Services\CurrencyService;
use Homlity\PluginInmobiliario\Services\ElementorIntegrationService;
use Homlity\PluginInmobiliario\Services\CrmInfrastructureService;
use Homlity\PluginInmobiliario\Services\I18nService;
use Homlity\PluginInmobiliario\Services\LocationMetaService;
use Homlity\PluginInmobiliario\Services\NinjaWhatsAppPropertyOverrideService;
use Homlity\PluginInmobiliario\Services\PropertyAjaxService;
use Homlity\PluginInmobiliario\Services\PropertyAnalyticsService;
use Homlity\PluginInmobiliario\Services\PropertyAnalyticsCleanupService;
use Homlity\PluginInmobiliario\Services\PropertyCodeRoutingService;
use Homlity\PluginInmobiliario\Services\PropertyContactClickTrackingService;
use Homlity\PluginInmobiliario\Services\PropertyTechnicalSheetDownloadTrackingService;
use Homlity\PluginInmobiliario\Services\PropertyUnavailableService;
use Homlity\PluginInmobiliario\Services\PropertyVisitTrackingService;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\SyncRegistry;
use Homlity\PluginInmobiliario\Services\TemplateEditorService;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\SeoGeoSchemaService;
use Homlity\PluginInmobiliario\Services\SeoGeoSettingsService;
use Homlity\PluginInmobiliario\Services\SeoIntegrationService;
use Homlity\PluginInmobiliario\Services\SeoService;
use Homlity\PluginInmobiliario\Services\SettingsService;
use Homlity\PluginInmobiliario\Services\TemplateService;
use Homlity\PluginInmobiliario\Services\UserMetaService;
use Homlity\PluginInmobiliario\Services\VersionService;

if (!defined('ABSPATH')) {
    exit;
}

class PluginBootstrap
{
    /** @var ServiceInterface[] */
    private array $services = [];

    public function init(): void
    {
        $this->services = [
            // Core WordPress services
            new I18nService(),
            new VersionService(),
            new SettingsService(),
            new PropertyPostType(),
            new PropertyTaxonomies(),
            new AdminMenuService(),
            new AdminBarService(),
            new CapabilityService(),
            new LocationMetaService(),
            new UserMetaService(),
            new CurrencyService(),
            new SeoService(),
            new SeoIntegrationService(),
            new SeoGeoSettingsService(),
            new SeoGeoSchemaService(),
            new CrmInfrastructureService(),
            new CrmIntegrationService(),
            new CrmAdminService(),
            new TemplateService(),

            // Homologation: canonical data mapping across all CRM integrations
            new HomologationRestController(),
            new HomologationAdminPage(),

            // Listing AJAX handler (shared by all page-builder adapters)
            new PropertyAjaxService(),
            new PropertyAnalyticsService(),

            // Page-builder integrations (each is a no-op when its builder is absent)
            new ElementorIntegrationService(),
            new WPBakeryIntegrationService(),
            new DiviIntegrationService(),

            // Shortcode [homlity_listing] – always active, also used by WPBakery/Divi
            new ShortcodeIntegrationService(),

            // Contact Form 7 – [homlity_property_code] dynamic field (no-op when CF7 absent)
            new CF7IntegrationService(),

            // URL routing by property code: /inmueble/AW001PR → 301 → canonical URL.
            // SyncRegistry must be registered before PropertyCodeRoutingService so the
            // 'homlity_plugin_register_sync_providers' action fires before template_redirect.
            new SyncRegistry(),
            new PropertyCodeRoutingService(),

            // Show "unavailable" page when a visitor lands on an unpublished property URL
            new PropertyUnavailableService(),
            new PropertyVisitTrackingService(),
            new PropertyContactClickTrackingService(),
            new PropertyTechnicalSheetDownloadTrackingService(),
            new PropertyAnalyticsCleanupService(),
            new NinjaWhatsAppPropertyOverrideService(),

            // Admin template editor: Configuración → Plantillas
            new TemplateEditorService(),

            // AI context: /llms-full.txt public endpoint + admin settings page
            new LlmsFullService(),
            new LlmsFullAdminService(),
        ];

        foreach ($this->services as $service) {
            $service->register();
        }
    }
}
