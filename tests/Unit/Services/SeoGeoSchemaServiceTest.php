<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\SeoGeoSchemaService;
use Homlity\PluginInmobiliario\Services\SeoGeoSettingsService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * El JSON-LD que se imprime en el `wp_head`.
 *
 * Esto es lo que lee Google, no lo que lee una persona: un campo mal escrito
 * no se ve en la página, se ve en Search Console semanas después. Y un JSON
 * roto invalida el bloque entero, así que hay que comprobar que lo emitido
 * decodifica y que cada dato aparece donde schema.org lo espera.
 */
final class SeoGeoSchemaServiceTest extends TestCase
{
    /** @param array<string,mixed> $settings */
    private function emitted(array $settings): string
    {
        WpStubs::setOption(SeoGeoSettingsService::OPTION_NAME, array_merge([
            'seo_enable_schema' => '1',
            'company_name'      => 'Royal Propiedad Raíz',
        ], $settings));

        ob_start();
        (new SeoGeoSchemaService())->outputSchema();

        return (string) ob_get_clean();
    }

    /**
     * Todos los bloques JSON-LD emitidos, ya decodificados.
     *
     * @return array<int,array<string,mixed>>
     */
    private function blocks(array $settings): array
    {
        preg_match_all(
            '#<script type="application/ld\+json">(.*?)</script>#s',
            $this->emitted($settings),
            $matches
        );

        return array_map(static function (string $json): array {
            $decoded = json_decode($json, true);
            self::assertIsArray($decoded, 'el JSON-LD emitido tiene que decodificar');

            return $decoded;
        }, $matches[1]);
    }

    /** @param array<string,mixed> $settings */
    private function org(array $settings): array
    {
        $blocks = $this->blocks($settings);
        self::assertNotEmpty($blocks, 'no se emitió ningún bloque');

        return $blocks[0];
    }

    // ── El interruptor general y los de cada tipo de página ──────────────────

    public function testConElSchemaDesactivadoNoSeImprimeNada(): void
    {
        self::assertSame('', $this->emitted(['seo_enable_schema' => '']));
    }

    /**
     * Los interruptores por tipo de página existen porque en la ficha de un
     * inmueble ya hay otro schema —el del propio inmueble— y duplicar la
     * organización en cada página es ruido.
     *
     * @dataProvider paginasConInterruptor
     */
    public function testCadaTipoDePaginaRespetaSuInterruptor(string $ajuste, callable $situar): void
    {
        $situar();

        self::assertSame('', $this->emitted([$ajuste => '']), 'apagado no imprime');
        self::assertNotSame('', $this->emitted([$ajuste => '1']), 'encendido imprime');
    }

    /** @return array<string,array{0:string,1:callable}> */
    public static function paginasConInterruptor(): array
    {
        return [
            'portada' => ['schema_on_home', static function (): void {
                WpStubs::$isFrontPage = true;
            }],
            'ficha de inmueble' => ['schema_on_properties', static function (): void {
                WpStubs::$singularPostType = 'property';
            }],
            'perfil del asesor' => ['schema_on_agents', static function (): void {
                WpStubs::$isAuthor = true;
            }],
            'archivo de zona' => ['schema_on_zones', static function (): void {
                WpStubs::$currentTaxonomy = 'property_city';
            }],
        ];
    }

    /**
     * Una página normal —"quiénes somos", el blog— no tiene interruptor
     * propio: ahí el schema de la organización se imprime siempre.
     */
    public function testUnaPaginaSinInterruptorPropioImprimeSiempre(): void
    {
        self::assertNotSame('', $this->emitted([
            'schema_on_home'       => '',
            'schema_on_properties' => '',
            'schema_on_agents'     => '',
            'schema_on_zones'      => '',
        ]));
    }

    // ── La organización ──────────────────────────────────────────────────────

    /** Sin nombre no hay organización que declarar. */
    public function testSinNombreDeEmpresaNoSeEmiteLaOrganizacion(): void
    {
        self::assertSame('', $this->emitted(['company_name' => '']));
    }

    public function testLaOrganizacionLlevaContextoTipoNombreYUrl(): void
    {
        $org = $this->org(['contact_website' => 'https://royal.test']);

        self::assertSame('https://schema.org', $org['@context']);
        self::assertSame('RealEstateAgent', $org['@type']);
        self::assertSame('Royal Propiedad Raíz', $org['name']);
        self::assertSame('https://royal.test', $org['url']);
    }

    /** Sin web configurada, la del propio sitio. */
    public function testSinWebPropiaSeUsaLaDelSitio(): void
    {
        $org = $this->org(['contact_website' => '']);

        self::assertSame(WpStubs::$homeUrl, $org['url']);
    }

    /**
     * `@type` sale de un desplegable, pero la opción se guarda como texto
     * libre: un valor inventado produciría un tipo que schema.org no conoce y
     * Google descartaría el bloque entero.
     */
    public function testUnTipoDeSchemaDesconocidoCaeAlPorDefecto(): void
    {
        self::assertSame('LocalBusiness', $this->org(['schema_type' => 'LocalBusiness'])['@type']);
        self::assertSame('Organization', $this->org(['schema_type' => 'Organization'])['@type']);
        self::assertSame('RealEstateAgent', $this->org(['schema_type' => 'Restaurante'])['@type']);
        self::assertSame('RealEstateAgent', $this->org(['schema_type' => ''])['@type']);
    }

    /** Lo que no está configurado no puede aparecer como clave vacía. */
    public function testLosCamposSinInformarNoSeEmiten(): void
    {
        $org = $this->org([]);

        foreach (['slogan', 'description', 'logo', 'image', 'email', 'contactPoint', 'openingHours', 'address', 'geo', 'sameAs', 'areaServed'] as $clave) {
            self::assertArrayNotHasKey($clave, $org, $clave);
        }
    }

    public function testElTelefonoSeEmiteComoPuntoDeContacto(): void
    {
        $org = $this->org(['contact_phone' => '+57 300 000 0000']);

        self::assertSame('ContactPoint', $org['contactPoint']['@type']);
        self::assertSame('+57 300 000 0000', $org['contactPoint']['telephone']);
        self::assertSame('customer service', $org['contactPoint']['contactType']);
    }

    public function testLaDireccionSeEmiteComoPostalAddress(): void
    {
        $org = $this->org([
            'geo_address'     => 'Calle 10 # 40-20',
            'geo_city'        => 'Medellín',
            'geo_state'       => 'Antioquia',
            'geo_postal_code' => '050021',
            'geo_country'     => 'CO',
        ]);

        self::assertSame([
            '@type'           => 'PostalAddress',
            'streetAddress'   => 'Calle 10 # 40-20',
            'addressLocality' => 'Medellín',
            'addressRegion'   => 'Antioquia',
            'postalCode'      => '050021',
            'addressCountry'  => 'CO',
        ], $org['address']);
    }

    /** Con un solo campo de dirección ya hay algo que declarar. */
    public function testUnSoloCampoDeDireccionYaEmiteElBloque(): void
    {
        $org = $this->org(['geo_city' => 'Medellín']);

        self::assertSame('Medellín', $org['address']['addressLocality']);
        self::assertArrayNotHasKey('streetAddress', $org['address']);
    }

    /**
     * Las coordenadas van como número: en JSON-LD una latitud entrecomillada
     * es una cadena, y los validadores la marcan.
     */
    public function testLasCoordenadasSeEmitenComoNumeros(): void
    {
        $org = $this->org(['geo_latitude' => '6.2442', 'geo_longitude' => '-75.5812']);

        self::assertSame('GeoCoordinates', $org['geo']['@type']);
        self::assertSame(6.2442, $org['geo']['latitude']);
        self::assertSame(-75.5812, $org['geo']['longitude']);
    }

    /** Media coordenada no sitúa nada. */
    public function testUnaCoordenadaSolaNoEmiteElBloqueGeo(): void
    {
        self::assertArrayNotHasKey('geo', $this->org(['geo_latitude' => '6.2442']));
        self::assertArrayNotHasKey('geo', $this->org(['geo_longitude' => '-75.5812']));
    }

    /** `sameAs` es la lista de perfiles: los huecos no pueden dejar rangos vacíos. */
    public function testLasRedesSocialesSeListanSinHuecos(): void
    {
        $org = $this->org([
            'social_facebook'  => 'https://facebook.com/royal',
            'social_instagram' => '',
            'social_linkedin'  => 'https://linkedin.com/company/royal',
        ]);

        self::assertSame([
            'https://facebook.com/royal',
            'https://linkedin.com/company/royal',
        ], $org['sameAs'], 'lista consecutiva, sin índices salteados');
    }

    // ── Zonas de cobertura ───────────────────────────────────────────────────

    /** Una zona desactivada en el panel no puede seguir anunciándose. */
    public function testSoloLasZonasActivasLleganAlSchema(): void
    {
        $org = $this->org(['coverage_zones' => [
            ['neighborhood' => 'El Poblado', 'city' => 'Medellín', 'state' => 'Antioquia', 'country' => 'Colombia', 'active' => '1'],
            ['neighborhood' => 'Laureles', 'city' => 'Medellín', 'state' => '', 'country' => '', 'active' => ''],
        ]]);

        self::assertCount(1, $org['areaServed']);
        self::assertSame('El Poblado, Medellín, Antioquia, Colombia', $org['areaServed'][0]['name']);
        self::assertSame('Place', $org['areaServed'][0]['@type']);
    }

    /**
     * Con zonas desactivadas de por medio, la lista tiene que renumerarse: en
     * JSON un array con índices salteados se codifica como objeto y deja de
     * ser una lista de lugares.
     */
    public function testLaListaDeZonasSeRenumera(): void
    {
        $json = $this->emitted(['coverage_zones' => [
            ['neighborhood' => '', 'city' => 'Cali', 'state' => '', 'country' => '', 'active' => ''],
            ['neighborhood' => '', 'city' => 'Medellín', 'state' => '', 'country' => '', 'active' => '1'],
        ]]);

        self::assertStringContainsString('"areaServed": [', $json, 'lista, no objeto');
    }

    public function testUnaZonaConCoordenadasLasIncluye(): void
    {
        $org = $this->org(['coverage_zones' => [[
            'neighborhood' => '', 'city' => 'Medellín', 'state' => '', 'country' => '',
            'active' => '1', 'latitude' => '6.2442', 'longitude' => '-75.5812',
        ]]]);

        self::assertSame(6.2442, $org['areaServed'][0]['geo']['latitude']);
    }

    public function testSinZonasActivasNoSeEmiteAreaServed(): void
    {
        $org = $this->org(['coverage_zones' => [
            ['neighborhood' => '', 'city' => 'Cali', 'state' => '', 'country' => '', 'active' => ''],
        ]]);

        self::assertArrayNotHasKey('areaServed', $org);
    }

    // ── FAQPage ──────────────────────────────────────────────────────────────

    /** @param array<int,array<string,string>> $faqs */
    private function faqBlock(array $faqs, string $activo = '1'): ?array
    {
        $blocks = $this->blocks(['schema_faq_active' => $activo, 'global_faqs' => $faqs]);
        foreach ($blocks as $block) {
            if (($block['@type'] ?? '') === 'FAQPage') {
                return $block;
            }
        }

        return null;
    }

    public function testLasFaqsMarcadasParaSchemaSeEmitenComoFaqPage(): void
    {
        $faq = $this->faqBlock([
            ['question' => '¿Aceptan mascotas?', 'answer' => 'Sí', 'active' => '1', 'schema' => '1'],
        ]);

        self::assertNotNull($faq);
        self::assertSame('https://schema.org', $faq['@context']);
        self::assertCount(1, $faq['mainEntity']);
        self::assertSame('Question', $faq['mainEntity'][0]['@type']);
        self::assertSame('¿Aceptan mascotas?', $faq['mainEntity'][0]['name']);
        self::assertSame('Sí', $faq['mainEntity'][0]['acceptedAnswer']['text']);
    }

    /**
     * Las dos casillas son independientes a propósito: una FAQ puede mostrarse
     * en la página sin querer publicarla como dato estructurado.
     */
    public function testUnaFaqActivaPeroSinMarcarParaSchemaNoSeEmite(): void
    {
        self::assertNull($this->faqBlock([
            ['question' => '¿Aceptan mascotas?', 'answer' => 'Sí', 'active' => '1', 'schema' => ''],
        ]));
    }

    public function testUnaFaqMarcadaParaSchemaPeroInactivaNoSeEmite(): void
    {
        self::assertNull($this->faqBlock([
            ['question' => '¿Aceptan mascotas?', 'answer' => 'Sí', 'active' => '', 'schema' => '1'],
        ]));
    }

    public function testConElInterruptorDeFaqsApagadoNoSeEmiteElBloque(): void
    {
        self::assertNull($this->faqBlock(
            [['question' => '¿Aceptan mascotas?', 'answer' => 'Sí', 'active' => '1', 'schema' => '1']],
            ''
        ));
    }

    /**
     * El HTML no puede viajar dentro del JSON-LD. La respuesta admite marcado
     * al guardarse —es contenido editorial—, así que el saneado tiene que
     * ocurrir aquí, al emitir; y la pregunta puede traerlo si la escribió otro
     * plugin a través del filtro o una importación antigua.
     */
    public function testElHtmlSeQuitaDeLaPreguntaYDeLaRespuesta(): void
    {
        $faq = $this->faqBlock([[
            'question' => '<em>¿Aceptan mascotas?</em>',
            'answer'   => '<p>Sí, <strong>con depósito</strong>.</p>',
            'active'   => '1',
            'schema'   => '1',
        ]]);

        self::assertSame('¿Aceptan mascotas?', $faq['mainEntity'][0]['name']);
        self::assertSame('Sí, con depósito.', $faq['mainEntity'][0]['acceptedAnswer']['text']);
    }

    /** Una FAQ a medias no puede publicarse como pregunta sin respuesta. */
    public function testUnaFaqSinRespuestaSeSaltaYNoRompeElResto(): void
    {
        $faq = $this->faqBlock([
            ['question' => 'Sin respuesta', 'answer' => '', 'active' => '1', 'schema' => '1'],
            ['question' => 'Completa', 'answer' => 'Respuesta', 'active' => '1', 'schema' => '1'],
        ]);

        self::assertCount(1, $faq['mainEntity']);
        self::assertSame('Completa', $faq['mainEntity'][0]['name']);
    }

    public function testSiTodasLasFaqsEstanIncompletasNoSeEmiteElBloque(): void
    {
        self::assertNull($this->faqBlock([
            ['question' => 'Sin respuesta', 'answer' => '', 'active' => '1', 'schema' => '1'],
        ]));
    }

    // ── Codificación ─────────────────────────────────────────────────────────

    /**
     * Sin JSON_UNESCAPED_UNICODE, "Medellín" viaja como `Medellín`. Es
     * válido, pero ilegible al depurar y muy fácil de romper al tocar el
     * volcado.
     */
    public function testLosAcentosViajanSinEscapar(): void
    {
        $json = $this->emitted(['geo_city' => 'Medellín']);

        self::assertStringContainsString('Medellín', $json);
        self::assertStringNotContainsString('\u00ed', $json, 'sin escapes \\uXXXX');
    }

    public function testElBloqueSeEmiteComoScriptDeTipoJsonLd(): void
    {
        $json = $this->emitted([]);

        self::assertStringStartsWith('<script type="application/ld+json">', $json);
        self::assertStringEndsWith("</script>\n", $json);
    }
}
