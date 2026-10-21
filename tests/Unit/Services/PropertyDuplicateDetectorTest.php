<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertyDuplicateDetector;
use Homlity\PluginInmobiliario\Services\PropertyDuplicateService;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\PropertyQualityFixtures;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/** La detección distingue texto copiado de evidencia física y nunca modifica las fichas. */
final class PropertyDuplicateDetectorTest extends TestCase
{
    use PropertyQualityFixtures;

    public function testAgrupaTildesEtiquetasShortcodesEspaciosYFallbackAContent(): void
    {
        $this->property(1, '<p> CASA con JARDÍN </p> [galeria]');
        $this->property(2, '', [], "casa   con\njardin");
        $this->property(3, 'Texto distinto', [], 'Casa con jardín');
        $this->property(4, '');
        $this->property(5, '');
        $group = array_values((new PropertyDuplicateDetector())->findGroups());
        self::assertCount(1, $group);
        self::assertSame([1, 2], $group[0]['ids']);
        self::assertSame('copied_text', $group[0]['classification']);
    }

    public function testHuellaRecortaQuinientosCaracteresNoBytes(): void
    {
        $this->property(1, str_repeat('界', 500) . 'uno');
        $this->property(2, str_repeat('界', 500) . 'dos');
        $this->property(3, str_repeat('界', 499) . 'otro');
        self::assertSame([1, 2], (new PropertyDuplicateDetector())->groupFor(1)['ids']);
        self::assertNull((new PropertyDuplicateDetector())->groupFor(3));
    }

    public function testDireccionNormalizadaYMedidasIgualesProponenSameListing(): void
    {
        $this->property(1, 'Casa con jardín', $this->physical(['_property_address' => 'Carréra 10', '_property_area' => '100.0']));
        $this->property(2, 'Casa con jardín', $this->physical(['_property_address' => ' CARRERA   10 ']));
        $group = (new PropertyDuplicateDetector())->groupFor(1);
        self::assertSame('same_listing', $group['classification']);
        self::assertTrue($group['pairs'][0]['same_physical']);
        self::assertSame('Casa con jardín', get_post(1)->post_excerpt);
        self::assertSame('publish', get_post(2)->post_status);
    }

    public function testDistanciaMenorATreintaMetrosConMedidasIguales(): void
    {
        $this->property(1, 'Texto', $this->physical(['_property_address' => '', '_property_latitude' => '4.6', '_property_longitude' => '-74.1']));
        $this->property(2, 'Texto', $this->physical(['_property_address' => '', '_property_latitude' => '4.6001', '_property_longitude' => '-74.1']));
        self::assertSame('same_listing', (new PropertyDuplicateDetector())->groupFor(1)['classification']);
        update_post_meta(2, '_property_latitude', '4.601');
        (new PropertyDuplicateService())->invalidate();
        self::assertSame('copied_text', (new PropertyDuplicateDetector())->groupFor(1)['classification']);
    }

    public function testApartamentosDistintosDelMismoEdificioSonTextoCopiado(): void
    {
        $coords = ['_property_latitude' => '4.6', '_property_longitude' => '-74.1'];
        $this->property(1, 'Texto', $this->physical(array_merge($coords, ['_property_address' => 'CR 46 A 66 SUR 155 AP 1102'])));
        $this->property(2, 'Texto', $this->physical(array_merge($coords, ['_property_address' => 'CR 46 A 66 SUR 155 AP 1002'])));
        $group = (new PropertyDuplicateDetector())->groupFor(1);
        self::assertSame('copied_text', $group['classification']);
        self::assertFalse($group['pairs'][0]['same_physical']);
    }

    public function testCodigosDistintosImpidenUsarCoordenadasSinDireccionCoincidente(): void
    {
        $coords = ['_property_latitude' => '4.6', '_property_longitude' => '-74.1'];
        $this->property(1, 'Texto', $this->physical(array_merge($coords, ['_property_code' => 'AP-1102'])));
        $this->property(2, 'Texto', $this->physical(array_merge($coords, ['_property_code' => 'AP-1002'])));
        foreach ([['', ''], ['Calle 10', ''], ['', 'Calle 10'], ['AP 1102', 'AP 1002']] as [$first, $second]) {
            update_post_meta(1, '_property_address', $first);
            update_post_meta(2, '_property_address', $second);
            (new PropertyDuplicateService())->invalidate();
            self::assertSame('copied_text', (new PropertyDuplicateDetector())->groupFor(1)['classification']);
        }
    }

    public function testDireccionCoincidentePermiteCodigosDistintosSinDependerDeCoordenadas(): void
    {
        $this->property(1, 'Texto', $this->physical(['_property_code' => 'VENTA-1']));
        $this->property(2, 'Texto', $this->physical(['_property_code' => 'ARRIENDO-1']));
        self::assertSame('same_listing', (new PropertyDuplicateDetector())->groupFor(1)['classification']);
    }

    public function testCoordenadasSoloComoRespaldoDeDireccionAusenteSinCodigosContradictorios(): void
    {
        $coords = ['_property_latitude' => '4.6', '_property_longitude' => '-74.1'];
        $this->property(1, 'Texto', $this->physical($coords));
        $this->property(2, 'Texto', $this->physical(array_merge($coords, ['_property_address' => ''])));
        foreach ([['', ''], ['IGUAL', 'IGUAL'], ['UNO', ''], ['', 'DOS']] as [$first, $second]) {
            update_post_meta(1, '_property_code', $first);
            update_post_meta(2, '_property_code', $second);
            (new PropertyDuplicateService())->invalidate();
            self::assertSame('same_listing', (new PropertyDuplicateDetector())->groupFor(1)['classification']);
        }
    }

    public function testDiferenciasFisicasYDatosAusentesNoProponenConsolidacion(): void
    {
        $this->property(1, 'Texto', $this->physical());
        $this->property(2, 'Texto', $this->physical(['_property_bedrooms' => '4']));
        $this->property(3, 'Texto');
        $group = (new PropertyDuplicateDetector())->groupFor(1);
        self::assertSame('copied_text', $group['classification']);
        self::assertSame([false, false, false], array_column($group['pairs'], 'same_physical'));
    }

    public function testGrupoMixtoExigeCoincidenciaEnTodosLosPares(): void
    {
        $this->property(1, 'Texto', $this->physical());
        $this->property(2, 'Texto', $this->physical());
        $this->property(3, 'Texto', $this->physical(['_property_address' => 'Otro lugar']));
        $group = (new PropertyDuplicateDetector())->groupFor(1);
        self::assertSame('copied_text', $group['classification']);
        self::assertSame([true, false, false], array_column($group['pairs'], 'same_physical'));
    }

    public function testUnaConsultaDelInventarioYCacheInvalidadaAlGuardar(): void
    {
        $GLOBALS['wpdb'] = new class extends \HomlityTestWpdb {
            public array $queries = [];
            public function get_results(string $query, mixed $output = null): array
            {
                $this->queries[] = $query;
                return parent::get_results($query, $output);
            }
        };
        $this->property(1);
        $this->property(2);
        $detector = new PropertyDuplicateDetector();
        $original = $detector->findGroups();
        self::assertSame(12 * HOUR_IN_SECONDS, WpStubs::$transientExpirations[PropertyDuplicateDetector::TRANSIENT]);
        $this->property(3);
        self::assertSame($original, $detector->findGroups());
        self::assertCount(1, $GLOBALS['wpdb']->queries);
        self::assertStringContainsString("post_type = 'property' AND post_status = 'publish'", $GLOBALS['wpdb']->queries[0]);
        (new PropertyDuplicateService())->register();
        do_action('save_post_property', 3, get_post(3), true);
        self::assertFalse(get_transient(PropertyDuplicateDetector::TRANSIENT));
        self::assertSame([1, 2, 3], $detector->groupFor(1)['ids']);
        self::assertCount(2, $GLOBALS['wpdb']->queries);
    }

    public function testPieDeLaPopaConTipoOficinaYTextoCasa(): void
    {
        $this->property(1, 'Amplia casa en Pie de la Popa', [], '', 'Pie de la Popa');
        $this->assignTerm(1, PropertyTaxonomies::TAXONOMY_TYPE, 'Oficina');
        self::assertSame('casa', (new PropertyDuplicateDetector())->typeMismatch(1));
    }

    public function testTipoAsignadoMencionadoNoGeneraConflictoYRespetaLimitesDePalabras(): void
    {
        $this->property(1, 'Oficina junto a una casa');
        $this->assignTerm(1, PropertyTaxonomies::TAXONOMY_TYPE, 'Oficina');
        self::assertNull((new PropertyDuplicateDetector())->typeMismatch(1));
        get_post(1)->post_excerpt = 'Excelente localidad';
        self::assertNull((new PropertyDuplicateDetector())->typeMismatch(1));
        get_post(1)->post_excerpt = 'Casa amplia';
        get_post(1)->post_title = 'Oficina en Pie de la Popa';
        self::assertSame('casa', (new PropertyDuplicateDetector())->typeMismatch(1));
    }

    public function testSinExtractoNiContenidoElTituloNoGeneraConflicto(): void
    {
        $this->property(1, '', [], '', 'Casa en Pie de la Popa');
        $this->assignTerm(1, PropertyTaxonomies::TAXONOMY_TYPE, 'Oficina');
        self::assertNull((new PropertyDuplicateDetector())->typeMismatch(1));
        get_post(1)->post_content = 'Casa amplia';
        get_post(1)->post_title = 'Oficina en Pie de la Popa';
        self::assertSame('casa', (new PropertyDuplicateDetector())->typeMismatch(1));
    }

    public function testMapaDeSinonimosEsFiltrable(): void
    {
        $this->property(1, 'Hermosa vivienda');
        $this->assignTerm(1, PropertyTaxonomies::TAXONOMY_TYPE, 'Oficina');
        WpStubs::addFilter('homlity_property_type_keywords', static function (array $map): array {
            $map['casa'][] = 'vivienda';
            return $map;
        });
        self::assertSame('casa', (new PropertyDuplicateDetector())->typeMismatch(1));
    }
}
