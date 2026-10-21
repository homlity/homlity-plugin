<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\Ai\LlmsFullGenerator;
use Homlity\PluginInmobiliario\Services\PropertySummaryComposer;
use Homlity\PluginInmobiliario\Services\PropertySummaryService;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Services\SeoGeoSettingsService;
use Homlity\PluginInmobiliario\Tests\Support\PropertyQualityFixtures;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/** Los textos públicos diferencian fichas sin alterar el texto original ni descripciones manuales. */
final class PropertySummaryComposerTest extends TestCase
{
    use PropertyQualityFixtures;

    public function testResumenConTodosLosCamposEnElOrdenEsperado(): void
    {
        $this->property(1, 'Descripción', $this->physical(['_property_price_sale' => '250000000',
            '_property_currency_sale' => 'COP', '_property_code' => 'H-100']));
        $this->assignTerm(1, PropertyTaxonomies::TAXONOMY_TYPE, 'Casa');
        $this->assignTerm(1, PropertyTaxonomies::TAXONOMY_OPERATION, 'Venta');
        $this->assignTerm(1, PropertyTaxonomies::TAXONOMY_NEIGHBORHOOD, 'Chicó');
        $this->assignTerm(1, PropertyTaxonomies::TAXONOMY_CITY, 'Bogotá');
        self::assertSame('Casa en Venta en Chicó, Bogotá · 100 m² · 3 hab · 2 baños · $250.000.000 COP · Ref. H-100', (new PropertySummaryComposer())->compose(1));
    }

    public function testCamposAusentesNoDejanSeparadoresHuerfanos(): void
    {
        $this->property(1, '', ['_property_code' => 'ABC']);
        self::assertSame('Ref. ABC', (new PropertySummaryComposer())->compose(1));
        $this->assignTerm(1, PropertyTaxonomies::TAXONOMY_CITY, 'Bogotá');
        self::assertSame('Bogotá · Ref. ABC', (new PropertySummaryComposer())->compose(1));
        $this->property(2);
        self::assertSame('Ref. 2', (new PropertySummaryComposer())->compose(2));
    }

    public function testOficinaOmiteMedidasCeroONegativas(): void
    {
        $this->property(1, '', ['_property_area' => '0', '_property_bedrooms' => '0', '_property_bathrooms' => '0']);
        $this->assignTerm(1, PropertyTaxonomies::TAXONOMY_TYPE, 'Oficina');
        self::assertSame('Oficina · Ref. 1', (new PropertySummaryComposer())->compose(1));
        update_post_meta(1, '_property_area', '25');
        update_post_meta(1, '_property_bathrooms', '-1');
        self::assertSame('Oficina · 25 m² · Ref. 1', (new PropertySummaryComposer())->compose(1));
    }

    public function testPreciosDualesReutilizanFormatoDelCatalogo(): void
    {
        $this->property(1, '', ['_property_price_sale' => '100000', '_property_currency_sale' => 'USD',
            '_property_price_rent' => '2500', '_property_currency_rent' => 'USD']);
        self::assertSame('Venta: ' . LlmsFullGenerator::formatPrice('100000', 'USD') . ' · Arriendo: '
            . LlmsFullGenerator::formatPrice('2500', 'USD') . ' · Ref. 1', (new PropertySummaryComposer())->compose(1));
    }

    public function testLlmsDiferenciaDosFichasConMismoExtractoYSinCodigo(): void
    {
        $excerpt = str_repeat('jardín ', 30);
        $one = $this->property(1, $excerpt);
        $two = $this->property(2, $excerpt);
        $service = new PropertySummaryService();
        $first = $service->filterLlmsDescription($excerpt, $one);
        $second = $service->filterLlmsDescription($excerpt, $two);
        self::assertNotSame($first, $second);
        self::assertStringStartsWith('Ref. 1 — ', $first);
        self::assertSame(20, substr_count($first, 'jardín'));
        self::assertSame($excerpt, $one->post_excerpt);
    }

    public function testLlmsOmiteTextoEnConflictoYEmpiezaPorTaxonomia(): void
    {
        $post = $this->property(1, 'Amplia casa', ['_property_code' => 'POP'], '', 'Pie de la Popa');
        $this->assignTerm(1, PropertyTaxonomies::TAXONOMY_TYPE, 'Oficina');
        self::assertSame('Oficina · Ref. POP', (new PropertySummaryService())->filterLlmsDescription('Amplia casa', $post));
    }

    public function testNoTocaOtrosTiposNiLlmsSiSeDesactiva(): void
    {
        $service = new PropertySummaryService();
        $post = new \WP_Post(['ID' => 5, 'post_type' => 'post']);
        self::assertSame('Original', $service->filterLlmsDescription('Original', $post));
        WpStubs::setOption(SeoGeoSettingsService::OPTION_NAME, ['ai_llms_structured_summary' => '']);
        self::assertSame('Original', $service->filterLlmsDescription('Original', $this->property(1)));
    }

    public function testMetaDescriptionSoloEnDuplicadosSinTextoManual(): void
    {
        $this->property(1);
        $this->property(2);
        $this->property(3, 'Otro texto');
        $service = new PropertySummaryService();
        WpStubs::$currentPostId = 1;
        self::assertSame('Original', $service->filterMetaDescription('Original'));
        WpStubs::$singularPostType = 'property';
        self::assertSame('Ref. 1', $service->filterMetaDescription('Original'));
        update_post_meta(1, 'rank_math_description', 'Descripción manual');
        self::assertSame('Descripción manual', $service->filterMetaDescription('Descripción manual'));
        WpStubs::$currentPostId = 3;
        self::assertSame('Original', $service->filterMetaDescription('Original'));
    }

    public function testFiltrosRegistradosAplicanElResumen(): void
    {
        (new PropertySummaryService())->register();
        $post = $this->property(1);
        self::assertStringStartsWith('Ref. 1 — ', apply_filters('rank_math/llms_txt/post_description', 'Original', $post));
    }

    private function render(\WP_Post $post): string
    {
        return (new \ReflectionMethod(LlmsFullGenerator::class, 'renderProperty'))->invoke(new LlmsFullGenerator(), $post);
    }

    public function testCatalogoCompletoEnlazaHermanosDelMismoInmueble(): void
    {
        $one = $this->property(1, 'Casa con jardín', $this->physical());
        $this->property(2, 'Casa con jardín', $this->physical());
        $text = $this->render($one);
        self::assertStringContainsString('- **Ver también:** ' . get_permalink(2), $text);
        self::assertStringContainsString('- **Descripción:** Casa con jardín', $text);
    }

    public function testCatalogoNoEnlazaTextoCopiadoYOmiteDescripcionEnConflicto(): void
    {
        $one = $this->property(1, 'Casa amplia', [], '', 'Pie de la Popa');
        $this->property(2, 'Casa amplia');
        $this->assignTerm(1, PropertyTaxonomies::TAXONOMY_TYPE, 'Oficina');
        $text = $this->render($one);
        self::assertStringContainsString('- **Tipo:** Oficina', $text);
        self::assertStringNotContainsString('**Ver también:**', $text);
        self::assertStringNotContainsString('**Descripción:**', $text);
    }
}
