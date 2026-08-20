<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\PropertyPostType;
use Homlity\PluginInmobiliario\Services\TechnicalSheetService;
use Homlity\PluginInmobiliario\Services\TemplateService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * El botón de la ficha técnica y su estado de descarga.
 *
 * Descargar la ficha no navega: la respuesta llega con `Content-Disposition:
 * attachment`, así que el navegador se queda en la página y no pinta ningún
 * indicador. Componer el PDF sí tarda —Dompdf arma el HTML, se trae las fotos
 * del inmueble y rasteriza—, y el botón se quedaba quieto todo ese rato sin
 * decir nada. Lo que sigue comprueba las piezas que enciende el JS: el
 * modificador que le dice a qué enlaces engancharse, el aro, los textos y la
 * región que lee el lector de pantalla.
 *
 * El JS no se ejecuta aquí, así que estas pruebas afirman el contrato entre la
 * plantilla y el script, no la animación. Si alguien renombra una clase o un
 * `data-` en un lado y no en el otro, la animación desaparece sin que nada
 * falle en tiempo de ejecución; de ahí que la comprobación mire ambos ficheros.
 */
final class TechnicalSheetButtonTest extends TestCase
{
    private const TEMPLATE = 'property-technical-sheet-button.php';
    private const SCRIPT = HOMLITY_PLUGIN_PATH . 'assets/js/technical-sheet-download.js';
    private const STYLES = HOMLITY_PLUGIN_PATH . 'assets/css/front-components.css';

    private const SCRIPT_HANDLE = 'homlity-real-estate-technical-sheet-download';

    private const POST_ID = 10;

    protected function setUp(): void
    {
        parent::setUp();

        WpStubs::$postObjects[self::POST_ID] = new \WP_Post([
            'ID' => self::POST_ID,
            'post_type' => PropertyPostType::POST_TYPE,
            'post_name' => 'apartamento-guatape',
        ]);
        WpStubs::$permalinks[self::POST_ID] = 'https://example.test/inmueble/apartamento-guatape/';
        WpStubs::$postTitles[self::POST_ID] = 'Apartamento en Guatapé';
    }

    /** Sin Dompdf no hay PDF que descargar y el botón abre la ficha en pantalla. */
    private function givenNoPdf(): void
    {
        WpStubs::addFilter('homlity_technical_sheet_pdf_available', static fn(): bool => false);
    }

    /** @param array<string,mixed> $settings */
    private function render(array $settings = []): string
    {
        ob_start();
        TemplateService::includeComponent(self::TEMPLATE, [
            'post_id' => self::POST_ID,
            'settings' => $settings,
        ]);

        return (string) ob_get_clean();
    }

    // ── El enganche del script ────────────────────────────────────────────

    public function testLaDescargaMarcaElEnlaceParaQueElScriptLoTome(): void
    {
        self::assertStringContainsString('property-tech-sheet-btn--async', $this->render());
    }

    /**
     * Abrir la ficha en el sitio sí navega, y de eso ya avisa el navegador con
     * su propio indicador. Enganchar el script ahí lo único que haría sería
     * cancelar la navegación y descargarse la página en un archivo.
     */
    public function testAbrirLaFichaEnElSitioNoLlevaElModificador(): void
    {
        self::assertStringNotContainsString(
            'property-tech-sheet-btn--async',
            $this->render(['link_action' => 'view'])
        );
    }

    /** Sin PDF el enlace apunta a una página, no a un archivo. */
    public function testSinDompdfElEnlaceTampocoLlevaElModificador(): void
    {
        $this->givenNoPdf();

        self::assertStringNotContainsString('property-tech-sheet-btn--async', $this->render());
    }

    public function testLaDescargaCargaElScript(): void
    {
        $this->render();

        self::assertArrayHasKey(self::SCRIPT_HANDLE, WpStubs::$enqueuedScripts);
        self::assertSame(
            HOMLITY_PLUGIN_URL . 'assets/js/technical-sheet-download.js',
            WpStubs::$enqueuedScripts[self::SCRIPT_HANDLE]['src']
        );
    }

    /** El script se pide al pie: en la cabecera correría antes de existir el botón. */
    public function testElScriptSeCargaAlPie(): void
    {
        $this->render();

        self::assertTrue(WpStubs::$enqueuedScripts[self::SCRIPT_HANDLE]['args']);
    }

    /** Un enlace que solo navega no necesita nada de esto cargado. */
    public function testAbrirLaFichaEnElSitioNoCargaElScript(): void
    {
        $this->render(['link_action' => 'view']);

        self::assertArrayNotHasKey(self::SCRIPT_HANDLE, WpStubs::$enqueuedScripts);
    }

    /**
     * Sin destino la plantilla se va antes de pintar nada; pedir el script
     * ahí lo dejaría cargado en la página para un botón que no existe.
     */
    public function testUnInmuebleInexistenteNoCargaElScript(): void
    {
        ob_start();
        TemplateService::includeComponent(self::TEMPLATE, ['post_id' => 999, 'settings' => []]);
        $html = (string) ob_get_clean();

        self::assertSame('', trim($html));
        self::assertArrayNotHasKey(self::SCRIPT_HANDLE, WpStubs::$enqueuedScripts);
    }

    // ── Lo que el script necesita encontrar en el marcado ─────────────────

    public function testElBotonTraeElAroDeCarga(): void
    {
        self::assertStringContainsString('property-tech-sheet-btn__spinner', $this->render());
    }

    /**
     * El texto va en su propio `<span>` porque el script lo reemplaza por
     * «Generando ficha…» mientras dura la espera. Suelto dentro del enlace no
     * habría forma de cambiarlo sin borrar también el aro.
     */
    public function testElTextoDelBotonVaEnSuPropioElemento(): void
    {
        self::assertMatchesRegularExpression(
            '#<span class="property-tech-sheet-btn__label">\s*Descargar ficha técnica\s*</span>#u',
            $this->render()
        );
    }

    public function testElTextoDelBotonSigueSiendoElQueConfiguraElWidget(): void
    {
        self::assertStringContainsString(
            '<span class="property-tech-sheet-btn__label">Bajar la ficha</span>',
            $this->render(['button_text' => 'Bajar la ficha'])
        );
    }

    /** Y sigue escapándose: el texto lo escribe quien edita la página. */
    public function testElTextoDelBotonSeEscapa(): void
    {
        $html = $this->render(['button_text' => '<script>alert(1)</script>']);

        self::assertStringNotContainsString('<script>alert(1)', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testElBotonLlevaLosTresTextosDeEstado(): void
    {
        $html = $this->render();

        self::assertStringContainsString('data-loading-text="Generando ficha…"', $html);
        self::assertStringContainsString('data-ready-text="Ficha descargada."', $html);
        self::assertStringContainsString('data-error-text="No se pudo generar la ficha.', $html);
    }

    /**
     * El nombre del archivo lo manda el servidor en la cabecera, pero al pedir
     * el PDF con fetch() hay proxys que la recortan; este es el respaldo, y
     * tiene que ser el mismo que pondría la descarga directa.
     */
    public function testElBotonLlevaElNombreDelArchivoComoRespaldo(): void
    {
        self::assertStringContainsString(
            'data-filename="' . TemplateService::technicalSheetPdfFilename(self::POST_ID) . '"',
            $this->render()
        );
        self::assertSame('apartamento-en-guatape.pdf', TemplateService::technicalSheetPdfFilename(self::POST_ID));
    }

    /**
     * La animación no la ve quien navega con lector de pantalla. `role=status`
     * hace que lo que el script escriba ahí se lea solo, sin robar el foco.
     */
    public function testHayUnaRegionQueAnunciaElEstado(): void
    {
        $html = $this->render();

        self::assertStringContainsString('property-tech-sheet-btn__status', $html);
        self::assertStringContainsString('role="status"', $html);
        self::assertStringContainsString('aria-live="polite"', $html);
    }

    /** Nada de esto pinta en un enlace que solo navega. */
    public function testAbrirLaFichaEnElSitioNoPintaAroNiRegionDeEstado(): void
    {
        $html = $this->render(['link_action' => 'view']);

        self::assertStringNotContainsString('property-tech-sheet-btn__spinner', $html);
        self::assertStringNotContainsString('property-tech-sheet-btn__status', $html);
        self::assertStringNotContainsString('data-loading-text', $html);
    }

    // ── Lo que ya funcionaba y no debía romperse ──────────────────────────

    public function testLaDescargaSigueSiendoUnEnlaceConDownload(): void
    {
        $html = $this->render();

        self::assertStringContainsString(
            'href="' . TechnicalSheetService::pdfUrl(self::POST_ID) . '"',
            html_entity_decode($html, ENT_QUOTES)
        );
        // El atributo suelto, no el `download=1` de la URL: es lo que hace que
        // el navegador guarde el archivo cuando el script no llega a cargarse.
        // El atributo suelto, no el `download=1` de la URL: es lo que hace que
        // el navegador guarde el archivo cuando el script no llega a cargarse.
        self::assertMatchesRegularExpression('#\sdownload[\s>]#', $html);
    }

    /**
     * Sin JS —o con el script bloqueado— el enlace tiene que seguir bajando el
     * PDF por sí solo. Es lo que hace que todo lo anterior sea un añadido y no
     * un requisito.
     */
    public function testLaDescargaNoDependeDelScript(): void
    {
        $html = $this->render();

        self::assertStringNotContainsString('href="#"', $html);
        self::assertStringNotContainsString('javascript:', $html);
    }

    public function testAbrirEnNuevaPestanaSoloAplicaALaFichaEnPantalla(): void
    {
        self::assertStringNotContainsString('target="_blank"', $this->render(['open_in_new_tab' => 'yes']));
        self::assertStringContainsString(
            'target="_blank"',
            $this->render(['link_action' => 'view', 'open_in_new_tab' => 'yes'])
        );
    }

    // ── El otro extremo del contrato ──────────────────────────────────────

    /**
     * Las clases y los `data-` de arriba no significan nada si el script mira
     * otras. Esto es lo único que ata los dos ficheros: PHPUnit no ejecuta el
     * JS, y un renombrado a medias no rompería ninguna prueba de las de arriba.
     */
    public function testElScriptBuscaLasMismasClasesQueEscribeLaPlantilla(): void
    {
        $js = (string) file_get_contents(self::SCRIPT);

        foreach ([
            '.property-tech-sheet-btn--async',
            '.property-tech-sheet-btn-wrap',
            '.property-tech-sheet-btn__status',
            '.property-tech-sheet-btn__label',
            'loadingText',
            'readyText',
            'errorText',
            'filename',
        ] as $needle) {
            self::assertStringContainsString($needle, $js, "El script ya no usa {$needle}.");
        }
    }

    /**
     * `is-loading` es la clase que el script pone y la hoja de estilos pinta;
     * es la que hace visible el aro. Sin ella el botón gira en el DOM y no en
     * la pantalla.
     */
    public function testLaHojaDeEstilosPintaElAroConLaClaseQueElScriptPone(): void
    {
        $js = (string) file_get_contents(self::SCRIPT);
        $css = (string) file_get_contents(self::STYLES);

        self::assertStringContainsString("'is-loading'", $js);
        self::assertStringContainsString(
            '.property-tech-sheet-btn.is-loading .property-tech-sheet-btn__spinner',
            $css
        );
        self::assertStringContainsString('.property-tech-sheet-btn__status.is-visible', $css);
    }
}
