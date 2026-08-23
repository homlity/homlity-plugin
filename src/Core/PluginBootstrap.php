<?php
/**
 * Main plugin bootstrap.
 */

namespace Homlity\PluginInmobiliario\Core;

use Homlity\Developer\Support\Hooks;
use Homlity\PluginInmobiliario\Core\Contracts\ServiceInterface;
use Homlity\PluginInmobiliario\ErrorReporting\ErrorReporterService;
use Homlity\PluginInmobiliario\Homologation\HomologationAdminPage;
use Homlity\PluginInmobiliario\Homologation\HomologationRestController;
use Homlity\PluginInmobiliario\Integrations\CF7\CF7IntegrationService;
use Homlity\PluginInmobiliario\Integrations\Elementor\ElementorProFormsIntegrationService;
use Homlity\PluginInmobiliario\Integrations\Divi\DiviIntegrationService;
use Homlity\PluginInmobiliario\Integrations\Shortcode\ShortcodeIntegrationService;
use Homlity\PluginInmobiliario\Integrations\WPBakery\WPBakeryIntegrationService;
use Homlity\PluginInmobiliario\Services\Ai\LlmsFullAdminService;
use Homlity\PluginInmobiliario\Services\Ai\LlmsFullService;
use Homlity\PluginInmobiliario\Services\AdminBarService;
use Homlity\PluginInmobiliario\Services\AgentProfileService;
use Homlity\PluginInmobiliario\Services\TechnicalSheetService;
use Homlity\PluginInmobiliario\Services\AdminMenuService;
use Homlity\PluginInmobiliario\Services\CapabilityService;
use Homlity\PluginInmobiliario\Services\CrmIntegrationService;
use Homlity\PluginInmobiliario\Services\CrmAdminService;
use Homlity\PluginInmobiliario\Services\CurrencyService;
use Homlity\PluginInmobiliario\Services\ElementorIntegrationService;
use Homlity\PluginInmobiliario\Services\ElementorTemplateSettingsService;
use Homlity\PluginInmobiliario\Services\CrmInfrastructureService;
use Homlity\PluginInmobiliario\Services\DashboardNewsService;
use Homlity\PluginInmobiliario\Services\I18nService;
use Homlity\PluginInmobiliario\Services\LocationMetaService;
use Homlity\PluginInmobiliario\Services\LocalityPostType;
use Homlity\PluginInmobiliario\Services\NinjaWhatsAppPropertyOverrideService;
use Homlity\PluginInmobiliario\Services\PropertyAjaxService;
use Homlity\PluginInmobiliario\Services\PropertyAnalyticsService;
use Homlity\PluginInmobiliario\Services\PropertyAnalyticsCleanupService;
use Homlity\PluginInmobiliario\Services\PropertyCodeRoutingService;
use Homlity\PluginInmobiliario\Services\PropertyContactClickTrackingService;
use Homlity\PluginInmobiliario\Services\PropertySearchService;
use Homlity\PluginInmobiliario\Services\PropertyTechnicalSheetDownloadTrackingService;
use Homlity\PluginInmobiliario\Services\PropertyUnavailableService;
use Homlity\PluginInmobiliario\Services\PropertyVisitTrackingService;
use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\SyncRegistry;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\SeoGeoSchemaService;
use Homlity\PluginInmobiliario\Services\SeoGeoSettingsService;
use Homlity\PluginInmobiliario\Services\SeoIntegrationService;
use Homlity\PluginInmobiliario\Services\SeoService;
use Homlity\PluginInmobiliario\Services\HomlityPlansService;
use Homlity\PluginInmobiliario\Services\HomlityPluginVersionsService;
use Homlity\PluginInmobiliario\Services\SimulatorService;
use Homlity\PluginInmobiliario\Services\SettingsService;
use Homlity\PluginInmobiliario\Services\TemplateService;
use Homlity\PluginInmobiliario\Services\UnavailablePropertyShortcodesService;
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
            // Public Developer API: lifecycle hooks and the extension registry.
            // First in the list so `homlity/loaded` is the only public signal
            // that the core finished booting, never a partially-wired one.
            new DeveloperApiService(),

            // Core WordPress services
            new ErrorReporterService(),
            new I18nService(),
            new VersionService(),
            new HomlityPlansService(),
            new SettingsService(),
            new HomlityPluginVersionsService(),
            new ElementorTemplateSettingsService(),
            new SimulatorService(),
            new PropertyPostType(),
            new PropertyTaxonomies(),
            new LocalityPostType(),
            new AdminMenuService(),
            new AdminBarService(),
            new DashboardNewsService(),
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
            new AgentProfileService(),
            new TechnicalSheetService(),
            new PropertySearchService(),

            // Homologation: canonical data mapping across all CRM integrations
            new HomologationRestController(),
            new HomologationAdminPage(),

            // Listing AJAX handler (shared by all page-builder adapters)
            new PropertyAjaxService(),
            new PropertyAnalyticsService(),

            // Page-builder integrations (each is a no-op when its builder is absent)
            new ElementorIntegrationService(),
            new ElementorProFormsIntegrationService(),
            new WPBakeryIntegrationService(),
            new DiviIntegrationService(),

            // Shortcode [homlity_listing] – always active, also used by WPBakery/Divi
            new ShortcodeIntegrationService(),

            // Shortcodes for unavailable-property landing pages
            // [homlity_unavailable_notice], [homlity_unavailable_similar_properties],
            // [homlity_unavailable_search_context]
            new UnavailablePropertyShortcodesService(),

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

            // AI context: /llms-full.txt public endpoint + admin settings page
            new LlmsFullService(),
            new LlmsFullAdminService(),
        ];

        foreach ($this->services as $service) {
            $service->register();
        }

        /**
         * Fires once the Homlity core has registered every one of its services.
         *
         * Runs on `plugins_loaded` priority 20. Post types, taxonomies and
         * rewrite rules are *not* registered yet — they land on `init`. Use
         * `homlity/initialized` when you need to query properties.
         *
         * @since 2.8.0
         */
        do_action(Hooks::LOADED);
    }
}
