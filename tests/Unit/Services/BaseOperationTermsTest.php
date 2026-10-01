<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Las gestiones base (Arriendo, Venta, Arriendo/Venta, Permuta) conservan su
 * identidad en term meta, así que el administrador puede cambiarles el nombre
 * y el slug para personalizar sus páginas sin que el plugin deje de
 * reconocerlas.
 */
final class BaseOperationTermsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WpStubs::$registeredTaxonomies[] = PropertyTaxonomies::TAXONOMY_OPERATION;
        add_filter('wp_update_term_data', [new PropertyTaxonomies(), 'protectBaseOperationIdentity'], 10, 4);
    }

    public function testElNombreYElSlugDeUnaGestionBaseSePuedenCambiar(): void
    {
        $venta = $this->seedBaseOperations()['venta'];

        wp_update_term($venta, PropertyTaxonomies::TAXONOMY_OPERATION, [
            'name' => 'Propiedades en venta',
            'slug' => 'propiedades-en-venta',
        ]);

        $term = get_term($venta, PropertyTaxonomies::TAXONOMY_OPERATION);
        self::assertSame('Propiedades en venta', $term->name);
        self::assertSame('propiedades-en-venta', $term->slug);
        self::assertSame(2, PropertyTaxonomies::baseOperationIdForTerm($venta));
    }

    public function testElSlugPersonalizadoSobreviveALaSincronizacionDeRegistrosBase(): void
    {
        $venta = $this->seedBaseOperations()['venta'];
        wp_update_term($venta, PropertyTaxonomies::TAXONOMY_OPERATION, ['slug' => 'comprar']);

        (new PropertyTaxonomies())->ensureBaseOperationTerms();

        self::assertSame('comprar', get_term($venta, PropertyTaxonomies::TAXONOMY_OPERATION)->slug);
        self::assertFalse(
            get_term_by('slug', 'venta', PropertyTaxonomies::TAXONOMY_OPERATION),
            'No debe recrearse una gestión "venta" duplicada.'
        );
    }

    public function testUnTerminoHeredadoSinIdentidadSeNormalizaAlAdoptarse(): void
    {
        WpStubs::$terms[PropertyTaxonomies::TAXONOMY_OPERATION][50] = new \WP_Term(50, PropertyTaxonomies::TAXONOMY_OPERATION, 'alquiler', 'Alquiler');

        (new PropertyTaxonomies())->ensureBaseOperationTerms();

        self::assertSame('arriendo', get_term(50, PropertyTaxonomies::TAXONOMY_OPERATION)->slug);
        self::assertSame(1, PropertyTaxonomies::baseOperationIdForTerm(50));
    }

    public function testUnTerminoPropioQueTomaElSlugLibreNoSuplantaALaGestionBase(): void
    {
        $venta = $this->seedBaseOperations()['venta'];
        wp_update_term($venta, PropertyTaxonomies::TAXONOMY_OPERATION, ['slug' => 'comprar']);
        WpStubs::$terms[PropertyTaxonomies::TAXONOMY_OPERATION][60] = new \WP_Term(60, PropertyTaxonomies::TAXONOMY_OPERATION, 'venta', 'Venta de oportunidad');

        self::assertSame(0, PropertyTaxonomies::baseOperationIdForTerm(60));
        self::assertSame(2, PropertyTaxonomies::baseOperationIdForTerm($venta));
    }

    /** @return array<string,int> slug base => term id */
    private function seedBaseOperations(): array
    {
        (new PropertyTaxonomies())->ensureBaseOperationTerms();

        $ids = [];
        foreach (PropertyTaxonomies::baseOperations() as $definition) {
            $ids[$definition['slug']] = (int) get_term_by('slug', $definition['slug'], PropertyTaxonomies::TAXONOMY_OPERATION)->term_id;
        }

        return $ids;
    }
}
