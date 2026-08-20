<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\SeoGeoSettingsService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Los ajustes de SEO & GEO: lectura, valores por defecto y guardado.
 *
 * Es el panel del que salen el nombre de la inmobiliaria, sus datos de
 * contacto, su color corporativo y los interruptores del schema. Todo lo que
 * se guarda mal aquí acaba publicado en el `wp_head` de todas las páginas.
 */
final class SeoGeoSettingsServiceTest extends TestCase
{
    /** @param array<string,mixed> $saved */
    private function save(array $saved): void
    {
        WpStubs::setOption(SeoGeoSettingsService::OPTION_NAME, $saved);
    }

    // ── Lectura ──────────────────────────────────────────────────────────────

    /** Un sitio recién instalado tiene que leer sin que nada falte. */
    public function testSinNadaGuardadoSeDevuelvenLosValoresPorDefecto(): void
    {
        $settings = SeoGeoSettingsService::getSettings();

        self::assertSame(SeoGeoSettingsService::defaults(), $settings);
        self::assertSame('#ff6752', $settings['brand_color_primary']);
        self::assertSame('RealEstateAgent', $settings['schema_type']);
    }

    /**
     * La fusión con los defaults es lo que permite añadir ajustes nuevos sin
     * romper los sitios que guardaron la opción antes de que existieran.
     */
    public function testLoGuardadoSeFusionaSobreLosValoresPorDefecto(): void
    {
        $this->save(['company_name' => 'Royal Propiedad Raíz']);

        $settings = SeoGeoSettingsService::getSettings();

        self::assertSame('Royal Propiedad Raíz', $settings['company_name']);
        self::assertArrayHasKey('schema_type', $settings, 'los ajustes nuevos siguen presentes');
        self::assertSame('RealEstateAgent', $settings['schema_type']);
    }

    /** Una opción corrupta —otro plugin, una migración— no puede tumbar el head. */
    public function testUnaOpcionQueNoEsUnArrayNoRompeLaLectura(): void
    {
        WpStubs::setOption(SeoGeoSettingsService::OPTION_NAME, 'esto no es un array');

        self::assertSame(SeoGeoSettingsService::defaults(), SeoGeoSettingsService::getSettings());
        self::assertNull(SeoGeoSettingsService::stored('company_name'));
    }

    public function testGetDevuelveElValorPedidoOElRespaldoIndicado(): void
    {
        $this->save(['company_name' => 'Royal']);

        self::assertSame('Royal', SeoGeoSettingsService::get('company_name'));
        self::assertSame('x', SeoGeoSettingsService::get('clave_inexistente', 'x'));
        self::assertNull(SeoGeoSettingsService::get('clave_inexistente'));
    }

    // ── stored(): lo elegido frente a lo predeterminado ──────────────────────

    /**
     * Ésta es la distinción que sostiene la precedencia de colores del PDF: un
     * sitio que nunca abrió la pestaña de marca no puede parecer que eligió el
     * naranja de fábrica, porque entonces le pisaría el color que sí configuró
     * en los ajustes del plugin.
     */
    public function testStoredDistingueLoElegidoDeLoPredeterminado(): void
    {
        self::assertSame('#ff6752', SeoGeoSettingsService::get('brand_color_primary'), 'get() siempre da algo');
        self::assertNull(SeoGeoSettingsService::stored('brand_color_primary'), 'stored() sólo si se eligió');

        $this->save(['brand_color_primary' => '#1f3c88']);

        self::assertSame('#1f3c88', SeoGeoSettingsService::stored('brand_color_primary'));
    }

    /** Guardar el campo en blanco es no haber elegido. */
    public function testUnValorGuardadoEnBlancoCuentaComoNoElegido(): void
    {
        $this->save(['brand_color_primary' => '', 'company_name' => '   ']);

        self::assertNull(SeoGeoSettingsService::stored('brand_color_primary'));
        self::assertNull(SeoGeoSettingsService::stored('company_name'));
    }

    /** Los repetidores son arrays: stored() sólo tiene sentido para escalares. */
    public function testStoredIgnoraLosValoresQueNoSonEscalares(): void
    {
        $this->save(['coverage_zones' => [['city' => 'Medellín']]]);

        self::assertNull(SeoGeoSettingsService::stored('coverage_zones'));
    }

    public function testStoredRecortaLosEspaciosSobrantes(): void
    {
        $this->save(['company_name' => '  Royal  ']);

        self::assertSame('Royal', SeoGeoSettingsService::stored('company_name'));
    }

    // ── Guardado ─────────────────────────────────────────────────────────────

    /** @param array<string,mixed> $posted */
    private function submit(array $posted, string $tab = 'general'): array
    {
        $_POST = ['homlity_seo' => $posted, '_current_tab' => $tab];
        try {
            (new SeoGeoSettingsService())->handleSave();
            self::fail('handleSave() debería terminar en una redirección');
        } catch (\HomlityTestRedirect $redirect) {
            $this->lastRedirect = $redirect->location;
        } finally {
            $_POST = [];
        }

        $saved = get_option(SeoGeoSettingsService::OPTION_NAME, []);

        return is_array($saved) ? $saved : [];
    }

    private string $lastRedirect = '';

    protected function setUp(): void
    {
        parent::setUp();
        WpStubs::$capabilities = ['manage_options'];
        $this->lastRedirect = '';
    }

    protected function tearDown(): void
    {
        $_POST = [];
        parent::tearDown();
    }

    /**
     * Sin la comprobación de capacidad, cualquier suscriptor autenticado podría
     * reescribir los datos públicos de la inmobiliaria con un POST.
     */
    public function testGuardarExigePermisosDeAdministracion(): void
    {
        WpStubs::$capabilities = [];

        $this->expectException(\HomlityTestDie::class);
        (new SeoGeoSettingsService())->handleSave();
    }

    /** Y sin nonce, bastaría con que un administrador visitara una página ajena. */
    public function testGuardarExigeUnNonceValido(): void
    {
        WpStubs::$nonceValid = false;

        try {
            (new SeoGeoSettingsService())->handleSave();
            self::fail('debería haberse cortado');
        } catch (\HomlityTestDie) {
            self::assertSame(
                [['action' => SeoGeoSettingsService::NONCE_ACTION, 'field' => SeoGeoSettingsService::NONCE_FIELD]],
                WpStubs::$checkedNonces
            );
        }
    }

    public function testAlGuardarSeVuelveALaPestaniaDesdeLaQueSeEnvio(): void
    {
        $this->submit(['company_name' => 'Royal'], 'marca');

        self::assertStringContainsString('page=' . SeoGeoSettingsService::PAGE_SLUG, $this->lastRedirect);
        self::assertStringContainsString('tab=marca', $this->lastRedirect);
        self::assertStringContainsString('updated=1', $this->lastRedirect);
    }

    public function testElTextoDeLosCamposSeLimpiaDeEtiquetas(): void
    {
        $saved = $this->submit(['company_name' => '<script>alert(1)</script>Royal']);

        self::assertStringNotContainsString('<script>', $saved['company_name']);
        self::assertStringContainsString('Royal', $saved['company_name']);
    }

    /** La descripción admite varias líneas; sanitize_text_field las aplastaría. */
    public function testLasDescripcionesConservanLosSaltosDeLinea(): void
    {
        $saved = $this->submit(['company_description' => "Primera línea\nSegunda línea"]);

        self::assertStringContainsString("\n", $saved['company_description']);
    }

    public function testUnCorreoInvalidoNoSeGuarda(): void
    {
        $saved = $this->submit(['contact_email' => 'no-es-un-correo']);

        self::assertSame('', $saved['contact_email']);
    }

    /**
     * Los interruptores llegan sólo cuando están marcados: lo que no viene en
     * el POST tiene que quedar apagado, no conservar el valor anterior.
     */
    public function testLosInterruptoresNoMarcadosQuedanApagados(): void
    {
        $this->save(['seo_enable_schema' => '1', 'schema_on_home' => '1']);

        $saved = $this->submit(['seo_enable_schema' => '1']);

        self::assertSame('1', $saved['seo_enable_schema']);
        self::assertSame('', $saved['schema_on_home']);
    }

    /** El campo se envía siempre, pero sólo cuenta como "sí" lo que no sea vacío. */
    public function testUnInterruptorSeNormalizaAUnoOACadenaVacia(): void
    {
        $saved = $this->submit(['seo_enable_og' => 'on', 'seo_enable_twitter' => '0']);

        self::assertSame('1', $saved['seo_enable_og']);
        self::assertSame('', $saved['seo_enable_twitter'], "'0' no es marcado");
    }

    /**
     * La clave secreta de reCAPTCHA se guarda aparte a propósito: no está en
     * los campos de texto que la vista imprime de vuelta al navegador.
     */
    public function testLaClaveSecretaDeRecaptchaSeGuarda(): void
    {
        $saved = $this->submit(['tools_recaptcha_secret' => '  6Lc-secreta  ']);

        self::assertSame('6Lc-secreta', $saved['tools_recaptcha_secret']);
    }

    // ── Repetidores ──────────────────────────────────────────────────────────

    public function testLasZonasDeCoberturaSeGuardanCampoACampo(): void
    {
        $saved = $this->submit(['coverage_zones' => [[
            'city'      => 'Medellín',
            'state'     => 'Antioquia',
            'radius'    => '15',
            'active'    => '1',
            'seo_slug'  => 'Zona Sur ',
            'latitude'  => '6.2442',
            'longitude' => '-75.5812',
        ]]]);

        $zona = $saved['coverage_zones'][0];
        self::assertSame('Medellín', $zona['city']);
        self::assertSame(15, $zona['radius'], 'el radio es un entero, no texto');
        self::assertSame('1', $zona['active']);
        self::assertSame('zona-sur', $zona['seo_slug'], 'el slug se normaliza para la URL');
        self::assertSame('all', $zona['coverage_type'], 'valor por defecto');
    }

    /** Una fila basura del repetidor no puede colarse en el schema. */
    public function testUnaFilaDeZonaQueNoEsUnArraySeDescarta(): void
    {
        $saved = $this->submit(['coverage_zones' => ['basura', ['city' => 'Cali']]]);

        self::assertCount(1, $saved['coverage_zones']);
        self::assertSame('Cali', $saved['coverage_zones'][0]['city']);
    }

    /**
     * La respuesta de una FAQ sí admite HTML —es contenido editorial—, pero
     * sólo el que WordPress considera seguro para una entrada.
     */
    public function testLaRespuestaDeUnaFaqAdmiteHtmlSeguroYNoScripts(): void
    {
        $saved = $this->submit(['global_faqs' => [[
            'question' => '¿Aceptan mascotas?',
            'answer'   => '<strong>Sí</strong><script>alert(1)</script>',
            'active'   => '1',
            'schema'   => '1',
            'order'    => '2',
        ]]]);

        $faq = $saved['global_faqs'][0];
        self::assertStringContainsString('<strong>Sí</strong>', $faq['answer']);
        self::assertStringNotContainsString('<script>', $faq['answer']);
        self::assertSame(2, $faq['order']);
        self::assertSame('general', $faq['category'], 'valor por defecto');
    }

    /** La pregunta es texto plano: va al schema y a un atributo. */
    public function testLaPreguntaDeUnaFaqNoAdmiteHtml(): void
    {
        $saved = $this->submit(['global_faqs' => [[
            'question' => '<em>¿Aceptan mascotas?</em>',
            'answer'   => 'Sí',
        ]]]);

        self::assertStringNotContainsString('<em>', $saved['global_faqs'][0]['question']);
    }
}
