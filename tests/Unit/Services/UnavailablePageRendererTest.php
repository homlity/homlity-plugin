<?php

declare(strict_types=1);
namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\UnavailablePageRenderer;
use Homlity\PluginInmobiliario\Services\DataSeederService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class UnavailablePageRendererTest extends TestCase
{
    private function page(string $builder = 'elementor'): void
    {
        WpStubs::$postObjects[50] = new \WP_Post(['ID' => 50, 'post_type' => 'page', 'post_content' => '<p>Contenido Divi</p>']);
        WpStubs::$postStatuses[50] = 'publish';
        update_option('homlity_plugin_unavailable_template_id', 50);
        update_post_meta(50, '_homlity_seeded_builder', $builder);
    }
    private function elementor(): object
    {
        require dirname(__DIR__, 2) . '/Support/UnavailableElementorDouble.php';
        $frontend = new \Elementor\UnavailableFrontendDouble();
        \Elementor\Plugin::$instance = (object) ['frontend' => $frontend];
        return $frontend;
    }
    private function render(): array
    {
        ob_start();
        try {
            $result = (new UnavailablePageRenderer())->renderConfiguredTemplate();
            return [$result, ob_get_contents()];
        } finally { ob_end_clean(); }
    }
    public function testElementorConservaCssSvgYEncolaRecursos(): void
    {
        $this->page(); $this->elementor();
        [$result, $html] = $this->render();
        self::assertTrue($result);
        self::assertStringContainsString('<style>', $html);
        self::assertStringContainsString('<svg>', $html);
        self::assertArrayHasKey('elementor-frontend', WpStubs::$enqueuedStyles);
        self::assertArrayHasKey('elementor-post-50', WpStubs::$enqueuedStyles);
        self::assertArrayHasKey('elementor-post-header', WpStubs::$enqueuedStyles);
        self::assertArrayHasKey('elementor-post-footer', WpStubs::$enqueuedStyles);
        self::assertArrayHasKey('elementor/theme/register_locations', WpStubs::$actions);
        self::assertArrayHasKey('get_header', WpStubs::$actions);
        self::assertArrayHasKey('get_footer', WpStubs::$actions);
    }
    public function testVacioNoImprimeNiCabecera(): void
    {
        $this->page(); $frontend = $this->elementor(); $frontend->html = '  ';
        self::assertSame([false, ''], $this->render());
        self::assertArrayNotHasKey('get_header', WpStubs::$actions);
    }
    public function testDiviNoLlamaAElementorYRestauraElPost(): void
    {
        $this->page('divi'); $frontend = $this->elementor();
        $GLOBALS['post'] = new \WP_Post(['ID' => 99]);
        add_filter('the_content', static fn($text) => $text . '<p>Filtrado</p>');
        [$result, $html] = $this->render();
        self::assertTrue($result); self::assertStringContainsString('Filtrado', $html);
        self::assertSame(0, $frontend->calls); self::assertSame(99, $GLOBALS['post']->ID);
    }
    public function testPlantillasNoPublicadasOAusentesNoSeRenderizan(): void
    {
        self::assertSame([false, ''], $this->render());
        update_option('homlity_plugin_unavailable_template_id', 50);
        self::assertSame([false, ''], $this->render());
        $this->page(); WpStubs::$postStatuses[50] = 'draft';
        self::assertSame([false, ''], $this->render());
        WpStubs::$postStatuses[50] = 'publish';
        self::assertSame([false, ''], $this->render());
    }
    public function testCanvasImprimeDocumentoYHooks(): void
    {
        $this->page(); $this->elementor();
        update_option('homlity_plugin_unavailable_page_layout', 'elementor_canvas');
        [$result, $html] = $this->render();
        self::assertTrue($result); self::assertStringContainsString('<!DOCTYPE html>', $html);
        self::assertStringContainsString('<head>', $html);
        foreach (['wp_head', 'wp_footer', 'wp_body_open'] as $hook) { self::assertArrayHasKey($hook, WpStubs::$actions); }
        self::assertArrayNotHasKey('get_header', WpStubs::$actions);
    }
    private function legacy(DataSeederService $seeder): array
    {
        return (new \ReflectionMethod($seeder, 'legacyUnavailableElementorData'))->invoke($seeder);
    }
    public function testSemillaIntactaSeActualizaYMigraCanvasUnaSolaVez(): void
    {
        $this->page(); $seeder = new DataSeederService();
        update_post_meta(50, '_elementor_data', wp_json_encode($this->legacy($seeder)));
        update_option('homlity_plugin_unavailable_page_layout', 'elementor_canvas');
        $seeder->repairUnavailableElementorPage();
        self::assertStringContainsString('homlity_unavailable_similar_properties', get_post_meta(50, '_elementor_data', true));
        self::assertSame('2', get_post_meta(50, '_homlity_seeded_template_version', true));
        self::assertSame('default', get_option('homlity_plugin_unavailable_page_layout'));
        update_option('homlity_plugin_unavailable_page_layout', 'elementor_canvas');
        $seeder->repairUnavailableElementorPage();
        self::assertSame('elementor_canvas', get_option('homlity_plugin_unavailable_page_layout'));
    }
    public function testSemillaEditadaNoSeTocaNiMigra(): void
    {
        $this->page(); $seeder = new DataSeederService(); $data = $this->legacy($seeder);
        $data[0]['settings']['custom'] = 'Usuario'; $raw = wp_json_encode($data);
        update_post_meta(50, '_elementor_data', $raw);
        update_option('homlity_plugin_unavailable_page_layout', 'elementor_canvas');
        $seeder->repairUnavailableElementorPage();
        self::assertSame($raw, get_post_meta(50, '_elementor_data', true));
        self::assertSame('elementor_canvas', get_option('homlity_plugin_unavailable_page_layout'));
    }
    public function testSemillaConOtraUrlDeBotonSeActualizaYMigraCanvas(): void
    {
        $this->page();
        $seeder = new DataSeederService();
        $data = $this->legacy($seeder);
        $data[0]['elements'][0]['elements'][2]['settings']['link']['url'] = 'https://otro-dominio.test/inmuebles/';
        update_post_meta(50, '_elementor_data', wp_json_encode($data));
        update_option('homlity_plugin_archive_page_id', 80);
        WpStubs::$permalinks[80] = 'https://example.com/catalogo/';
        update_option('homlity_plugin_unavailable_page_layout', 'elementor_canvas');
        update_post_meta(50, '_wp_page_template', 'elementor_canvas');

        $seeder->repairUnavailableElementorPage();

        self::assertStringContainsString('homlity_unavailable_similar_properties', get_post_meta(50, '_elementor_data', true));
        self::assertSame('2', get_post_meta(50, '_homlity_seeded_template_version', true));
        self::assertSame('default', get_option('homlity_plugin_unavailable_page_layout'));
        self::assertSame('default', get_post_meta(50, '_wp_page_template', true));
    }

    public function testTituloEditadoSeConservaAunqueCambieLaUrlDelBoton(): void
    {
        $this->page();
        $seeder = new DataSeederService();
        $data = $this->legacy($seeder);
        $data[0]['elements'][0]['elements'][0]['settings']['title'] = 'Título personalizado';
        $data[0]['elements'][0]['elements'][2]['settings']['link']['url'] = 'https://otro-dominio.test/inmuebles/';
        $raw = wp_json_encode($data);
        update_post_meta(50, '_elementor_data', $raw);
        update_option('homlity_plugin_unavailable_page_layout', 'elementor_canvas');
        update_post_meta(50, '_wp_page_template', 'elementor_canvas');

        $seeder->repairUnavailableElementorPage();

        self::assertSame($raw, get_post_meta(50, '_elementor_data', true));
        self::assertSame('', get_post_meta(50, '_homlity_seeded_template_version', true));
        self::assertSame('elementor_canvas', get_option('homlity_plugin_unavailable_page_layout'));
        self::assertSame('elementor_canvas', get_post_meta(50, '_wp_page_template', true));
    }

    public function testFichaCanvasUsaElDocumentoCompleto(): void
    {
        $this->page(); $this->elementor();
        update_option('homlity_plugin_single_template_id', 50);
        update_option('homlity_plugin_single_page_layout', 'elementor_canvas');
        ob_start();
        include dirname(__DIR__, 3) . '/templates/single-property.php';
        $html = ob_get_clean();
        self::assertStringContainsString('<head>', $html);
        self::assertStringContainsString('<!DOCTYPE html>', $html);
        self::assertArrayHasKey('wp_footer', WpStubs::$actions);
    }
    public function testBusquedaPorCodigoComparteRenderizadorYConserva404(): void
    {
        $this->page('divi'); $this->elementor();
        $GLOBALS['wp_query'] = new \WP_Query();
        $method = new \ReflectionMethod(\Homlity\PluginInmobiliario\Services\PropertyCodeRoutingService::class, 'renderNotFoundMessage');
        ob_start();
        try {
            $method->invoke(new \Homlity\PluginInmobiliario\Services\PropertyCodeRoutingService(), ['status' => 'not_found']);
            $html = ob_get_contents();
        } finally { ob_end_clean(); }
        self::assertSame([404], WpStubs::$statusHeaders);
        self::assertStringContainsString('Contenido Divi', $html);
        self::assertArrayHasKey('get_footer', WpStubs::$actions);
    }
    public function testEditorSoloActivaVistaPreviaConParametro(): void
    {
        $service = new \Homlity\PluginInmobiliario\Services\PropertyUnavailableService();
        WpStubs::$capabilities = ['edit_posts'];
        WpStubs::$postObjects[90] = new \WP_Post(['ID' => 90, 'post_type' => 'property', 'post_status' => 'draft']);
        $resolve = new \ReflectionMethod($service, 'previewPropertyId');
        unset($_GET['homlity_preview_unavailable']);
        self::assertSame(0, $resolve->invoke($service));
        $service->maybeShowUnavailable();
        self::assertSame([], WpStubs::$statusHeaders);
        $_GET['homlity_preview_unavailable'] = '90';
        self::assertSame(90, $resolve->invoke($service));
        WpStubs::$capabilities = [];
        self::assertSame(0, $resolve->invoke($service));
        unset($_GET['homlity_preview_unavailable']);
    }
    public function testVistaPreviaRenderizaCon200YNoindexSinRedireccion(): void
    {
        $this->page('divi');
        $GLOBALS['wp_query'] = new \WP_Query();
        $service = new \Homlity\PluginInmobiliario\Services\PropertyUnavailableService();
        update_option('homlity_recovery_enabled', '1');
        // Un reemplazo público normalmente provoca 301; la vista previa conserva el diseño.
        set_transient('homlity_rcv_90', [
            'status_code' => 301, 'redirect_url' => 'https://example.com/reemplazo/',
            'robots' => 'index,follow', 'is_retired' => true,
        ], 60);
        $method = new \ReflectionMethod($service, 'renderProperty');
        ob_start();
        try {
            $method->invoke($service, 90, true);
            $html = ob_get_contents();
        } finally { ob_end_clean(); }
        self::assertSame([200], WpStubs::$statusHeaders);
        self::assertSame('noindex,follow', $GLOBALS['homlity_property_recovery_context']['robots']);
        self::assertStringContainsString('Contenido Divi', $html);
        self::assertSame([], WpStubs::$redirects);
    }

    public function testNuevaSemillaUsaTemaYVersionDePlantilla(): void
    {
        WpStubs::$posts = [[50]];
        WpStubs::$postStatuses[50] = 'publish';
        $seeder = new DataSeederService();
        (new \ReflectionMethod($seeder, 'seedUnavailableElementorPage'))->invoke($seeder);
        self::assertSame(50, get_option('homlity_plugin_unavailable_template_id'));
        self::assertSame('default', get_option('homlity_plugin_unavailable_page_layout'));
        self::assertSame('2', get_post_meta(50, '_homlity_seeded_template_version', true));
        foreach (['notice', 'search_context', 'similar_properties'] as $shortcode) {
            self::assertStringContainsString('homlity_unavailable_' . $shortcode, get_post_meta(50, '_elementor_data', true));
        }
    }
    public function testAvisoSoloCuandoFaltaElShortcodeYEnlaceDeVistaPrevia(): void
    {
        $this->page();
        WpStubs::$capabilities = ['edit_posts'];
        $service = new \Homlity\PluginInmobiliario\Services\ElementorTemplateSettingsService();
        ob_start(); $service->unavailableTemplateNotice(); $html = ob_get_clean();
        self::assertStringContainsString('is-dismissible', $html);
        self::assertStringContainsString('[homlity_unavailable_similar_properties]', $html);
        update_post_meta(50, '_elementor_data', 'homlity_unavailable_similar_properties');
        ob_start(); $service->unavailableTemplateNotice(); self::assertSame('', ob_get_clean());
        WpStubs::$posts = [[90]];
        ob_start(); (new \ReflectionMethod($service, 'renderUnavailablePreviewLink'))->invoke($service); $html = ob_get_clean();
        self::assertStringContainsString('homlity_preview_unavailable=90', $html);
        self::assertStringContainsString('Ver cómo lo ve un visitante', $html);
    }

    public function testWpBakeryYNativoFuncionanSinElementor(): void
    {
        foreach (['wpbakery', 'native'] as $builder) {
            $this->page($builder);
            [$result, $html] = $this->render();
            self::assertTrue($result);
            self::assertStringContainsString('Contenido Divi', $html);
        }
    }

}
