<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\PropertySearchService;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\SeoIntegrationService;
use Homlity\PluginInmobiliario\Services\TemplateService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

final class OperationArchitectureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WpStubs::$registeredTaxonomies[] = PropertyTaxonomies::TAXONOMY_OPERATION;
        foreach ([1 => ['arriendo', 'Arriendo'], 2 => ['alquiler', 'Alquiler'], 3 => ['venta', 'Venta'],
            4 => ['arriendo-venta', 'Arriendo/Venta'], 5 => ['alquiler-venta', 'Alquiler/Venta'],
            6 => ['alquiler-temporal', 'Alquiler temporal']] as $id => [$slug, $name]) {
            WpStubs::setTerm($id, PropertyTaxonomies::TAXONOMY_OPERATION, $slug, $name);
        }
        WpStubs::addFilter('homlity_plugin_format_price', static fn($amount, $currency): string => $currency . ' ' . $amount);
    }

    public function testBuscadorNoPublicaSinonimosComoOpcionesDistintasYConservaOperacionEspecializada(): void
    {
        $_GET['gestion'] = 'arriendo';
        ob_start();
        try {
            TemplateService::includeComponent('property-filter.php', ['settings' => ['show_operation' => 'yes']]);
        } finally {
            $html = (string) ob_get_clean();
        }
        self::assertMatchesRegularExpression('/value="alquiler"\s+selected/', $html);
        self::assertStringContainsString('value="alquiler-venta"', $html);
        self::assertStringContainsString('value="alquiler-temporal"', $html);
        self::assertStringNotContainsString('value="arriendo"', $html);
        self::assertStringNotContainsString('value="arriendo-venta"', $html);
    }

    public function testAlquilerEncuentraSinonimosYPropiedadesMixtas(): void
    {
        $args = (new PropertySearchService())->buildQueryArgs(['operation' => 2]);
        self::assertEqualsCanonicalizing([1, 2, 4, 5, 6], $args['tax_query'][0]['terms']);
        $preset = (new PropertySearchService())->buildQueryArgs(['preset_operation' => 2]);
        self::assertEqualsCanonicalizing([1, 2, 4, 5, 6], $preset['tax_query'][0]['terms']);
    }

    public function testVentaEncuentraMixtasYUnaBusquedaMixtaNoIncluyeOperacionesSimples(): void
    {
        $sale = (new PropertySearchService())->buildQueryArgs(['operation' => 3]);
        self::assertEqualsCanonicalizing([3, 4, 5], $sale['tax_query'][0]['terms']);
        $both = (new PropertySearchService())->buildQueryArgs(['operation' => 5]);
        self::assertEqualsCanonicalizing([4, 5], $both['tax_query'][0]['terms']);
        $specialized = (new PropertySearchService())->buildQueryArgs(['operation' => 6]);
        self::assertSame([6], $specialized['tax_query'][0]['terms']);
    }

    public function testEnlacesYRedireccionesConsolidanSoloSinonimosYConservanFiltros(): void
    {
        self::assertSame(home_url('/inmuebles/gestion/alquiler/tipo/casa/'), TemplateService::buildSeoArchiveUrl(['gestion' => 'arriendo', 'tipo' => 'casa']));
        self::assertSame(home_url('/inmuebles/gestion/alquiler-venta/ciudad/liberia/?paged=2&price_min=1000'),
            TemplateService::operationAliasArchiveRedirect('/inmuebles/gestion/arriendo-venta/ciudad/liberia/?paged=2&price_min=1000'));
        foreach (['/inmuebles/gestion/alquiler/', '/inmuebles/gestion/venta/', '/inmuebles/gestion/alquiler-temporal/', '/inmuebles/gestion/desconocida/', '/otra/gestion/arriendo/'] as $url) {
            self::assertSame('', TemplateService::operationAliasArchiveRedirect($url));
        }
    }

    public function testUnaGestionBaseRenombradaPrevaleceSobreLosSinonimos(): void
    {
        $term = WpStubs::setTerm(1, PropertyTaxonomies::TAXONOMY_OPERATION, 'rentar', 'Rentar');
        update_term_meta(1, '_homlity_base_operation_id', 1);
        update_term_meta(1, '_homlity_base_operation_key', 'rent');
        self::assertSame('rentar', PropertyTaxonomies::canonicalOperationSlug('alquiler'));
        self::assertSame($term, PropertyTaxonomies::canonicalOperationTerm($term));
        $args = (new PropertySearchService())->buildQueryArgs(['operation' => 1, 'price_min' => 1000]);
        self::assertSame('_property_price_rent', $args['meta_query'][2]['key']);
    }

    public function testColumnaAdministrativaMuestraAmbosPreciosConSusMonedas(): void
    {
        $this->property();
        ob_start();
        (new PropertyPostType())->renderAdminColumn('property_operation_value', 501);
        $html = (string) ob_get_clean();
        self::assertSame('Alquiler: CRC 2200<br>Venta: USD 350000', $html);
    }

    public function testSitemapDeBusquedasSoloPublicaLasVariantesPreferidas(): void
    {
        WpStubs::$sqlResults = [
            'SELECT DISTINCT t.slug' => [['slug' => 'arriendo'], ['slug' => 'alquiler']],
            'SELECT DISTINCT tA.slug' => [['slug_a' => 'arriendo-venta', 'slug_b' => 'casa'], ['slug_a' => 'alquiler-venta', 'slug_b' => 'casa']],
            'SELECT DISTINCT tOp.slug' => [['g' => 'arriendo', 't' => 'casa', 'c' => 'liberia'], ['g' => 'alquiler', 't' => 'casa', 'c' => 'liberia']],
        ];
        $method = new \ReflectionMethod(SeoIntegrationService::class, 'buildSearchResultUrls');
        $urls = $method->invoke(new SeoIntegrationService());
        self::assertContains(home_url('/inmuebles/gestion/alquiler/'), $urls);
        self::assertContains(home_url('/inmuebles/gestion/alquiler-venta/tipo/casa/'), $urls);
        self::assertContains(home_url('/inmuebles/gestion/alquiler/tipo/casa/ciudad/liberia/'), $urls);
        foreach ($urls as $url) {
            self::assertStringNotContainsString('/gestion/arriendo', $url);
        }
        self::assertSame(array_unique($urls), $urls);
    }

    private function property(): void
    {
        WpStubs::$postObjects[501] = new \WP_Post(['ID' => 501, 'post_type' => PropertyPostType::POST_TYPE, 'post_status' => 'publish']);
        WpStubs::setPost(501, 'Casa en alquiler o venta', 'https://example.test/casa/', [
            '_property_price_rent' => '2200', '_property_currency_rent' => 'CRC',
            '_property_price_sale' => '350000', '_property_currency_sale' => 'USD',
        ]);
        WpStubs::$postTerms[501][PropertyTaxonomies::TAXONOMY_OPERATION] = [get_term(5, PropertyTaxonomies::TAXONOMY_OPERATION)];
    }

    public function testDatosEstructuradosPublicanDosOfertasReferenciadasEnUnaFicha(): void
    {
        $this->property();
        require_once HOMLITY_PLUGIN_PATH . 'includes/schema/class-homlity-schema-helpers.php';
        require_once HOMLITY_PLUGIN_PATH . 'includes/schema/class-homlity-property-schema.php';
        $graph = (new \Homlity_Property_Schema())->get_graph(501);
        $offers = array_values(array_filter($graph, static fn(array $node): bool => $node['@type'] === 'Offer'));
        self::assertCount(2, $offers);
        self::assertSame(['2200', '350000'], array_column($offers, 'price'));
        self::assertSame(['CRC', 'USD'], array_column($offers, 'priceCurrency'));
        self::assertSame(['http://purl.org/goodrelations/v1#LeaseOut', 'http://purl.org/goodrelations/v1#Sell'], array_column($offers, 'businessFunction'));
        self::assertSame(array_column($offers, '@id'), array_column($graph[0]['offers'], '@id'));
    }

    public function testOfertaMixtaSinCanonNoInventaUnAlquilerConElPrecioDeVenta(): void
    {
        $this->property();
        update_post_meta(501, '_property_price_rent', '0');
        require_once HOMLITY_PLUGIN_PATH . 'includes/schema/class-homlity-schema-helpers.php';
        require_once HOMLITY_PLUGIN_PATH . 'includes/schema/class-homlity-property-schema.php';
        $graph = (new \Homlity_Property_Schema())->get_graph(501);
        $offers = array_values(array_filter($graph, static fn(array $node): bool => $node['@type'] === 'Offer'));
        self::assertCount(1, $offers);
        self::assertSame('350000', $offers[0]['price']);
        self::assertSame($offers[0]['@id'], $graph[0]['offers']['@id']);
    }
}
