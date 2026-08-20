<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertyFaqGenerator;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Las preguntas frecuentes que se generan solas a partir de la ficha.
 *
 * Estas preguntas no son sólo texto en la página: alimentan el schema FAQPage
 * que se manda a Google. Una respuesta que diga "cuenta con 1 habitaciones", o
 * peor, que afirme un precio que el inmueble no tiene, se publica como dato
 * estructurado y queda indexada.
 */
final class PropertyFaqGeneratorTest extends TestCase
{
    private const POST_ID = 77;

    /**
     * Deja el post preparado como un inmueble con los metadatos indicados.
     *
     * @param array<string,string> $meta      claves sin el prefijo `_property_`
     * @param array<string,array<int,string>> $terms taxonomía => nombres
     */
    private function property(array $meta = [], array $terms = []): PropertyFaqGenerator
    {
        WpStubs::$postObjects[self::POST_ID] = new \WP_Post([
            'ID'        => self::POST_ID,
            'post_type' => 'property',
        ]);
        WpStubs::$postTitles[self::POST_ID] = 'Apartamento en El Poblado';
        WpStubs::$permalinks[self::POST_ID] = 'https://example.test/inmuebles/apto-poblado/';

        $prefixed = [];
        foreach ($meta as $key => $value) {
            $prefixed['_property_' . $key] = $value;
        }
        WpStubs::$postMeta[self::POST_ID] = $prefixed;

        foreach ($terms as $taxonomy => $names) {
            WpStubs::$postTerms[self::POST_ID][$taxonomy] = array_map(
                static function (string $name, int $i) use ($taxonomy): object {
                    return WpStubs::setTerm(100 + $i, $taxonomy, sanitize_title($name), $name);
                },
                $names,
                array_keys($names)
            );
        }

        return new PropertyFaqGenerator(self::POST_ID);
    }

    /** @param array<int,array<string,string>> $faqs */
    private function byKey(array $faqs): array
    {
        $indexed = [];
        foreach ($faqs as $faq) {
            $indexed[(string) $faq['key']] = $faq;
        }

        return $indexed;
    }

    /** Un inmueble completo: sirve para comprobar qué preguntas aparecen. */
    private function completo(): PropertyFaqGenerator
    {
        return $this->property(
            [
                'price_sale'     => '250000000',
                'currency_sale'  => 'COP',
                'price_admin'    => '350000',
                'currency_admin' => 'COP',
                'area'           => '85',
                'area_built'     => '80',
                'bedrooms'       => '3',
                'bathrooms'      => '2',
                'parking'        => '1',
                'stratum'        => '5',
                'code'           => 'APT-001',
            ],
            [
                'property_type'         => ['Apartamento'],
                'property_operation'    => ['Venta'],
                'property_city'         => ['Medellín'],
                'property_neighborhood' => ['El Poblado'],
                'property_feature'      => ['Piscina', 'Gimnasio'],
            ]
        );
    }

    // ── Lectura de la ficha ──────────────────────────────────────────────────

    /**
     * Sin esta guarda, pedir las FAQ de una página cualquiera devolvería una
     * lista de respuestas inventadas sobre un inmueble que no existe.
     */
    public function testUnPostQueNoEsInmuebleNoGeneraNingunaPregunta(): void
    {
        WpStubs::$postObjects[self::POST_ID] = new \WP_Post([
            'ID'        => self::POST_ID,
            'post_type' => 'page',
        ]);

        $generator = new PropertyFaqGenerator(self::POST_ID);

        self::assertSame([], $generator->get_property_data());
        self::assertSame([], $generator->generate_auto_faqs([]));
    }

    public function testUnIdentificadorVacioNoGeneraNada(): void
    {
        self::assertSame([], (new PropertyFaqGenerator(0))->generate_auto_faqs([]));
    }

    /** Los términos de una taxonomía se unen en un solo texto legible. */
    public function testLosTerminosDeCadaTaxonomiaSeUnenPorComas(): void
    {
        $data = $this->completo()->get_property_data();

        self::assertSame('Piscina, Gimnasio', $data['tax_feature']);
        self::assertSame('Medellín', $data['tax_city']);
        self::assertSame('Apartamento', $data['tax_type']);
        self::assertSame('El Poblado', $data['tax_neighborhood']);
    }

    /** La ficha se lee una sola vez aunque se pidan varias veces las FAQ. */
    public function testLaFichaSeLeeUnaSolaVez(): void
    {
        $generator = $this->completo();
        $primera = $generator->get_property_data();

        WpStubs::$postMeta[self::POST_ID]['_property_bedrooms'] = '99';
        $segunda = $generator->get_property_data();

        self::assertSame($primera, $segunda);
    }

    // ── Precio ───────────────────────────────────────────────────────────────

    public function testElPrecioDeVentaMandaSobreElDeArriendo(): void
    {
        $faqs = $this->byKey($this->property([
            'price_sale'    => '250000000',
            'currency_sale' => 'COP',
            'price_rent'    => '2000000',
            'currency_rent' => 'USD',
        ])->generate_auto_faqs([]));

        self::assertStringContainsString('250.000.000 COP', $faqs['price']['answer']);
        self::assertStringNotContainsString('USD', $faqs['price']['answer']);
    }

    /** Un inmueble sólo de arriendo tiene que anunciar su canon, no callarse. */
    public function testSinPrecioDeVentaSeUsaElDeArriendo(): void
    {
        $faqs = $this->byKey($this->property([
            'price_rent'    => '2000000',
            'currency_rent' => 'COP',
        ])->generate_auto_faqs([]));

        self::assertStringContainsString('2.000.000 COP', $faqs['price']['answer']);
    }

    /**
     * El CRM puede mandar el precio con símbolo y espacios. Quedarse con los
     * dígitos evita que `(float) '$ 250000000'` dé 0 y la pregunta desaparezca.
     */
    public function testElPrecioSeLeeAunqueVengaConSimboloYEspacios(): void
    {
        $faqs = $this->byKey($this->property([
            'price_sale' => '$ 250000000 ',
        ])->generate_auto_faqs([]));

        self::assertStringContainsString('250.000.000', $faqs['price']['answer']);
    }

    /** Sin precio no se afirma nada: mejor ninguna pregunta que una en blanco. */
    public function testSinPrecioNoSeGeneraLaPregunta(): void
    {
        $faqs = $this->byKey($this->property(['price_sale' => '0'])->generate_auto_faqs([]));

        self::assertArrayNotHasKey('price', $faqs);
    }

    public function testSinMonedaElPrecioSeMuestraIgual(): void
    {
        $faqs = $this->byKey($this->property(['price_sale' => '250000000'])->generate_auto_faqs([]));

        self::assertStringContainsString('250.000.000', $faqs['price']['answer']);
        self::assertStringNotContainsString('250.000.000 ', strip_tags($faqs['price']['answer']));
    }

    // ── Administración ───────────────────────────────────────────────────────

    /**
     * "Administración incluida" cambia el sentido de la respuesta: sin el
     * matiz, quien lee suma la cuota al precio y descarta el inmueble.
     */
    public function testLaAdministracionIncluidaSeAnuncia(): void
    {
        $incluida = $this->byKey($this->property([
            'price_admin'    => '350000',
            'admin_included' => '1',
        ])->generate_auto_faqs([]));
        self::assertStringContainsString('incluida en el precio', $incluida['admin']['answer']);

        $aparte = $this->byKey($this->property([
            'price_admin'    => '350000',
            'admin_included' => '',
        ])->generate_auto_faqs([]));
        self::assertStringNotContainsString('incluida en el precio', $aparte['admin']['answer']);
    }

    /** Si no hay moneda propia para la administración, sirve la de la venta. */
    public function testLaAdministracionHeredaLaMonedaDeLaVenta(): void
    {
        $faqs = $this->byKey($this->property([
            'price_admin'    => '350000',
            'currency_admin' => '',
            'currency_sale'  => 'COP',
            'price_sale'     => '250000000',
        ])->generate_auto_faqs([]));

        self::assertStringContainsString('350.000 COP', $faqs['admin']['answer']);
    }

    public function testSinValorDeAdministracionNoSeGeneraLaPregunta(): void
    {
        $faqs = $this->byKey($this->property(['price_admin' => '0'])->generate_auto_faqs([]));

        self::assertArrayNotHasKey('admin', $faqs);
    }

    // ── Conteos ──────────────────────────────────────────────────────────────

    /**
     * "1 habitaciones" en un dato estructurado es exactamente el tipo de
     * detalle que delata que el texto lo escribió una máquina.
     */
    public function testElSingularYElPluralSeEligenSegunLaCantidad(): void
    {
        $una = $this->byKey($this->property(['bedrooms' => '1', 'bathrooms' => '1', 'parking' => '1'])->generate_auto_faqs([]));
        self::assertStringContainsString('1 habitación', $una['bedrooms']['answer']);
        self::assertStringContainsString('1 baño', $una['bathrooms']['answer']);
        self::assertStringContainsString('1 parqueadero', $una['parking']['answer']);
        self::assertStringNotContainsString('habitaciones', $una['bedrooms']['answer']);

        $varias = $this->byKey($this->property(['bedrooms' => '3', 'bathrooms' => '2', 'parking' => '2'])->generate_auto_faqs([]));
        self::assertStringContainsString('3 habitaciones', $varias['bedrooms']['answer']);
        self::assertStringContainsString('2 baños', $varias['bathrooms']['answer']);
        self::assertStringContainsString('2 parqueaderos', $varias['parking']['answer']);
    }

    /**
     * La pregunta del parqueadero se responde con un "Sí": generarla cuando no
     * hay ninguno produciría "Sí, este inmueble cuenta con 0 parqueaderos".
     */
    public function testSinParqueaderoNoSeAfirmaQueSiLoHay(): void
    {
        $faqs = $this->byKey($this->property(['parking' => '0'])->generate_auto_faqs([]));

        self::assertArrayNotHasKey('parking', $faqs);
    }

    public function testLosConteosEnCeroNoGeneranPregunta(): void
    {
        $faqs = $this->byKey($this->property([
            'bedrooms' => '0', 'bathrooms' => '', 'stratum' => '0',
        ])->generate_auto_faqs([]));

        self::assertArrayNotHasKey('bedrooms', $faqs);
        self::assertArrayNotHasKey('bathrooms', $faqs);
        self::assertArrayNotHasKey('stratum', $faqs);
    }

    // ── Áreas ────────────────────────────────────────────────────────────────

    /** Cada área presente se enumera, y las que faltan no dejan hueco. */
    public function testSoloSeEnumeranLasAreasInformadas(): void
    {
        $faqs = $this->byKey($this->property([
            'area' => '85', 'area_built' => '80', 'area_private' => '0', 'area_lot' => '200',
        ])->generate_auto_faqs([]));

        $respuesta = $faqs['area']['answer'];
        self::assertStringContainsString('área total: 85 m²', $respuesta);
        self::assertStringContainsString('construida: 80 m²', $respuesta);
        self::assertStringContainsString('lote: 200 m²', $respuesta);
        self::assertStringNotContainsString('privada', $respuesta);
        self::assertStringNotContainsString(', .', $respuesta, 'sin comas colgando');
    }

    public function testSinNingunAreaNoSeGeneraLaPregunta(): void
    {
        $faqs = $this->byKey($this->property(['area' => '0'])->generate_auto_faqs([]));

        self::assertArrayNotHasKey('area', $faqs);
    }

    // ── Ubicación ────────────────────────────────────────────────────────────

    public function testLaUbicacionVaDeLoParticularALoGeneral(): void
    {
        $faqs = $this->byKey($this->completo()->generate_auto_faqs([]));

        self::assertStringContainsString('El Poblado, Medellín', $faqs['location']['answer']);
    }

    /** Con sólo ciudad no puede quedar una coma suelta al principio. */
    public function testSinBarrioLaUbicacionEsSoloLaCiudad(): void
    {
        $faqs = $this->byKey($this->property([], ['property_city' => ['Medellín']])->generate_auto_faqs([]));

        self::assertStringContainsString('ubicado en <strong>Medellín</strong>', $faqs['location']['answer']);
    }

    public function testSinUbicacionNoSeGeneraLaPregunta(): void
    {
        $faqs = $this->byKey($this->property()->generate_auto_faqs([]));

        self::assertArrayNotHasKey('location', $faqs);
    }

    // ── Interruptores ────────────────────────────────────────────────────────

    /** Sin ajustes guardados todo sale: es lo que ve un sitio recién instalado. */
    public function testPorDefectoSeGeneranTodasLasPreguntasPosibles(): void
    {
        $keys = array_keys($this->byKey($this->completo()->generate_auto_faqs([])));

        self::assertSame([
            'price', 'operation', 'location', 'type', 'area',
            'bedrooms', 'bathrooms', 'parking', 'admin', 'stratum',
            'features', 'code', 'contact',
        ], $keys);
    }

    /**
     * Cada interruptor tiene que apagar sólo lo suyo. Un `show_` mal escrito
     * en el panel deja la pregunta encendida y nadie se entera.
     *
     * @dataProvider interruptores
     */
    public function testCadaInterruptorApagaSuPropiaPregunta(string $ajuste, string $clave): void
    {
        $faqs = $this->byKey($this->completo()->generate_auto_faqs([$ajuste => 'no']));

        self::assertArrayNotHasKey($clave, $faqs);
        self::assertGreaterThan(10, count($faqs), 'apagar una no puede apagar el resto');
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function interruptores(): array
    {
        return [
            'precio'         => ['show_price', 'price'],
            'operación'      => ['show_operation', 'operation'],
            'ubicación'      => ['show_location', 'location'],
            'tipo'           => ['show_type', 'type'],
            'área'           => ['show_area', 'area'],
            'habitaciones'   => ['show_bedrooms', 'bedrooms'],
            'baños'          => ['show_bathrooms', 'bathrooms'],
            'parqueadero'    => ['show_parking', 'parking'],
            'administración' => ['show_admin', 'admin'],
            'estrato'        => ['show_stratum', 'stratum'],
            'características' => ['show_features', 'features'],
            'código'         => ['show_code', 'code'],
            'contacto'       => ['show_contact', 'contact'],
        ];
    }

    /**
     * La pregunta de contacto es la única que no depende de ningún dato: es la
     * llamada a la acción, y tiene que salir hasta en una ficha vacía.
     */
    public function testLaPreguntaDeContactoNoDependeDeLosDatos(): void
    {
        $faqs = $this->byKey($this->property()->generate_auto_faqs([]));

        self::assertCount(1, $faqs);
        self::assertArrayHasKey('contact', $faqs);
    }

    // ── Preguntas escritas a mano ────────────────────────────────────────────

    public function testUnaPreguntaManualOcultaNoSePublica(): void
    {
        $faqs = $this->property()->get_manual_faqs([
            ['faq_question' => 'Visible', 'faq_answer' => 'Sí', 'faq_visible' => 'yes'],
            ['faq_question' => 'Oculta', 'faq_answer' => 'No', 'faq_visible' => 'no'],
        ]);

        self::assertCount(1, $faqs);
        self::assertSame('Visible', $faqs[0]['question']);
    }

    /** Una fila del repetidor a medio llenar no puede publicarse a medias. */
    public function testUnaPreguntaManualIncompletaSeDescarta(): void
    {
        $faqs = $this->property()->get_manual_faqs([
            ['faq_question' => 'Sin respuesta', 'faq_answer' => ''],
            ['faq_question' => '', 'faq_answer' => 'Sin pregunta'],
            ['faq_question' => 'Completa', 'faq_answer' => 'Respuesta'],
        ]);

        self::assertCount(1, $faqs);
        self::assertSame('Completa', $faqs[0]['question']);
    }

    /** Sin posición declarada la pregunta va detrás de las automáticas. */
    public function testLaPosicionPorDefectoDeUnaPreguntaManualEsDetras(): void
    {
        $faqs = $this->property()->get_manual_faqs([
            ['faq_question' => 'Sin posición', 'faq_answer' => 'Respuesta'],
        ]);

        self::assertSame('after', $faqs[0]['position']);
    }

    public function testLasPreguntasManualesSeMarcanComoTales(): void
    {
        $faqs = $this->property()->get_manual_faqs([
            ['faq_question' => '¿Aceptan mascotas?', 'faq_answer' => 'Sí'],
        ]);

        self::assertStringStartsWith('manual_', $faqs[0]['key']);
    }

    // ── Combinación y duplicados ─────────────────────────────────────────────

    public function testLasPreguntasManualesRespetanSuPosicion(): void
    {
        $generator = $this->property();
        $auto = [['key' => 'price', 'question' => 'Auto', 'answer' => 'A']];
        $manual = [
            ['key' => 'm1', 'question' => 'Antes', 'answer' => 'B', 'position' => 'before'],
            ['key' => 'm2', 'question' => 'Después', 'answer' => 'C', 'position' => 'after'],
        ];

        $preguntas = array_column($generator->combine_faqs($auto, $manual), 'question');

        self::assertSame(['Antes', 'Auto', 'Después'], $preguntas);
    }

    /**
     * El caso real: el comercial escribe a mano una pregunta que el generador
     * ya produce. Sin deduplicar, el FAQPage lleva la misma pregunta dos veces
     * con respuestas distintas, y Google penaliza eso.
     */
    public function testUnaPreguntaManualIgualALaAutomaticaGanaYNoSeRepite(): void
    {
        $generator = $this->property();
        $auto = [['key' => 'price', 'question' => '¿Cuál es el precio?', 'answer' => 'Automática']];
        $manual = [['key' => 'm1', 'question' => '¿Cuál es el precio?', 'answer' => 'Manual', 'position' => 'before']];

        $combinadas = $generator->combine_faqs($auto, $manual);

        self::assertCount(1, $combinadas);
        self::assertSame('Manual', $combinadas[0]['answer'], 'la escrita a mano manda');
    }

    /**
     * La comparación ignora tildes, signos, mayúsculas y espacios de más:
     * nadie escribe la pregunta exactamente igual que el generador.
     *
     * @dataProvider variantesDeLaMismaPregunta
     */
    public function testLasVariantesDeLaMismaPreguntaSeConsideranDuplicadas(string $variante): void
    {
        $faqs = $this->property()->deduplicate_faqs([
            ['key' => 'a', 'question' => '¿Cuántas habitaciones tiene?', 'answer' => 'Primera'],
            ['key' => 'b', 'question' => $variante, 'answer' => 'Segunda'],
        ]);

        self::assertCount(1, $faqs, $variante);
        self::assertSame('Primera', $faqs[0]['answer']);
    }

    /** @return array<string,array{0:string}> */
    public static function variantesDeLaMismaPregunta(): array
    {
        return [
            'sin signos'        => ['Cuántas habitaciones tiene'],
            'sin tildes'        => ['¿Cuantas habitaciones tiene?'],
            'en mayúsculas'     => ['¿CUÁNTAS HABITACIONES TIENE?'],
            'con espacios extra' => ['¿Cuántas   habitaciones  tiene?'],
            'con espacios alrededor' => ['  ¿Cuántas habitaciones tiene?  '],
        ];
    }

    /** Dos preguntas distintas no pueden colapsar en una. */
    public function testDosPreguntasDistintasSobreviven(): void
    {
        $faqs = $this->property()->deduplicate_faqs([
            ['key' => 'a', 'question' => '¿Cuántas habitaciones tiene?', 'answer' => 'A'],
            ['key' => 'b', 'question' => '¿Cuántos baños tiene?', 'answer' => 'B'],
        ]);

        self::assertCount(2, $faqs);
    }

    // ── Puntos de extensión ──────────────────────────────────────────────────

    /** El filtro es la vía documentada para que un sitio añada sus preguntas. */
    public function testElFiltroPuedeAniadirPreguntasAutomaticas(): void
    {
        WpStubs::addFilter('homlity_faq_auto_questions', static function (array $faqs): array {
            $faqs[] = ['key' => 'propia', 'question' => '¿Y esto?', 'answer' => 'Añadida por el sitio'];

            return $faqs;
        });

        $faqs = $this->byKey($this->completo()->generate_auto_faqs([]));

        self::assertArrayHasKey('propia', $faqs);
    }

    /** Las claves de metadatos son filtrables para adaptarse a otro esquema. */
    public function testElFiltroPuedeCambiarLasClavesDeMetadatos(): void
    {
        WpStubs::addFilter('homlity_faq_property_meta_keys', static fn(array $keys): array => array_merge(
            $keys,
            ['bedrooms' => '_otro_plugin_habitaciones']
        ));
        $this->property();
        WpStubs::$postMeta[self::POST_ID] = ['_otro_plugin_habitaciones' => '4'];

        $faqs = $this->byKey((new PropertyFaqGenerator(self::POST_ID))->generate_auto_faqs([]));

        self::assertStringContainsString('4 habitaciones', $faqs['bedrooms']['answer']);
    }
}
