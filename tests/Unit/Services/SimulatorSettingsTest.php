<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\SimulatorSettings;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * La configuración del simulador de gastos.
 *
 * De aquí salen los porcentajes con los que se le dice a un cliente cuánto le
 * va a costar comprar o arrendar. Un valor que se pierda al guardar, o una
 * casilla que se interprete al revés, produce una cifra equivocada que nadie
 * detecta hasta que alguien la compara con la escritura real.
 */
final class SimulatorSettingsTest extends TestCase
{
    // ── Valores por defecto y fusión ─────────────────────────────────────────

    public function testLosValoresPorDefectoCubrenLasDosSecciones(): void
    {
        $defaults = SimulatorSettings::defaults();

        self::assertArrayHasKey('arriendo', $defaults);
        self::assertArrayHasKey('venta', $defaults);
        self::assertSame('19', $defaults['arriendo']['porcentajeIva']);
        self::assertSame('19', $defaults['venta']['porcentajeIva']);
    }

    /**
     * Es lo que permite añadir un concepto nuevo sin romper los sitios que ya
     * habían guardado la configuración: lo suyo se respeta y lo nuevo aparece.
     */
    public function testLaFusionRespetaLoGuardadoYAniadeLoQueFalta(): void
    {
        $merged = SimulatorSettings::merge(['venta' => ['porcentajeTimbre' => '2.0']]);

        self::assertSame('2.0', $merged['venta']['porcentajeTimbre']);
        self::assertSame('0.30', $merged['venta']['porcentajeGastosNotariales'], 'lo no tocado conserva el defecto');
        self::assertArrayHasKey('arriendo', $merged, 'la sección ausente sigue completa');
    }

    /** La fusión es recursiva: el bloque anidado no se sustituye entero. */
    public function testLaFusionEsRecursivaEnLosBloquesAnidados(): void
    {
        $merged = SimulatorSettings::merge([
            'venta' => ['protecciones_familiares' => ['vigencia_desde' => '2027-01-01']],
        ]);

        self::assertSame('2027-01-01', $merged['venta']['protecciones_familiares']['vigencia_desde']);
        self::assertArrayHasKey(
            'valores_por_defecto',
            $merged['venta']['protecciones_familiares'],
            'el resto del bloque no se pierde'
        );
    }

    // ── Saneado del formulario ───────────────────────────────────────────────

    public function testUnaEntradaQueNoEsArrayDevuelveLosValoresPorDefecto(): void
    {
        self::assertSame(SimulatorSettings::defaults(), SimulatorSettings::sanitize('esto no es un array'));
        self::assertSame(SimulatorSettings::defaults(), SimulatorSettings::sanitize(null));
    }

    /**
     * Una casilla desmarcada no se envía. Si su ausencia se tomara como "deja
     * lo que había", el comercial no podría desactivar nunca un concepto: cada
     * guardado lo volvería a encender.
     */
    public function testUnaCasillaDesmarcadaQuedaApagada(): void
    {
        $guardado = SimulatorSettings::sanitize([
            'arriendo' => ['comisionActiva' => '1'],
        ]);

        self::assertSame('1', $guardado['arriendo']['comisionActiva']);
        self::assertSame('0', $guardado['arriendo']['seguroActivo'], 'no enviada = apagada');
    }

    /** Sólo un '1' exacto cuenta como marcada. */
    public function testSoloElUnoExactoCuentaComoMarcada(): void
    {
        foreach (['1' => '1', 'on' => '0', '0' => '0', 'true' => '0', '' => '0'] as $enviado => $esperado) {
            $guardado = SimulatorSettings::sanitize(['arriendo' => ['comisionActiva' => $enviado]]);
            self::assertSame($esperado, $guardado['arriendo']['comisionActiva'], var_export($enviado, true));
        }
    }

    public function testLosCamposDeTextoSeLimpianDeEtiquetas(): void
    {
        $guardado = SimulatorSettings::sanitize([
            'venta' => ['labelImpuestoDepartamental' => '<script>alert(1)</script>Beneficencia'],
        ]);

        self::assertStringNotContainsString('<script>', $guardado['venta']['labelImpuestoDepartamental']);
        self::assertStringContainsString('Beneficencia', $guardado['venta']['labelImpuestoDepartamental']);
    }

    /** Los textos largos son editoriales: conservan el marcado seguro. */
    public function testLosTextosLargosConservanElHtmlSeguro(): void
    {
        $guardado = SimulatorSettings::sanitize([
            'venta' => ['introConceptos' => '<p>Los <strong>gastos</strong> son estimados.</p><script>alert(1)</script>'],
        ]);

        self::assertStringContainsString('<strong>gastos</strong>', $guardado['venta']['introConceptos']);
        self::assertStringNotContainsString('<script>', $guardado['venta']['introConceptos']);
    }

    /** Un campo que el formulario no envía conserva su valor por defecto. */
    public function testUnCampoNoEnviadoConservaSuValorPorDefecto(): void
    {
        $guardado = SimulatorSettings::sanitize(['venta' => []]);

        self::assertSame('0.30', $guardado['venta']['porcentajeGastosNotariales']);
    }

    /**
     * Lo que llega por POST y no está declarado como campo no puede acabar en
     * la opción: es la única barrera contra que un formulario manipulado meta
     * claves arbitrarias en la configuración.
     */
    public function testUnCampoNoDeclaradoSeDescarta(): void
    {
        $guardado = SimulatorSettings::sanitize([
            'venta'    => ['campo_inventado' => 'valor'],
            'inventada' => ['lo_que_sea' => '1'],
        ]);

        self::assertArrayNotHasKey('campo_inventado', $guardado['venta']);
        self::assertArrayNotHasKey('inventada', $guardado);
    }

    // ── Protecciones familiares ──────────────────────────────────────────────

    public function testLosImportesDeLasProteccionesSeGuardanComoEnteros(): void
    {
        $bloque = SimulatorSettings::sanitizeProteccionesFamiliares([
            'valores_por_defecto' => ['tarifa_notarial_acto_sin_cuantia' => '95.400,50'],
        ]);

        self::assertSame(95, $bloque['valores_por_defecto']['tarifa_notarial_acto_sin_cuantia']);
        self::assertIsInt($bloque['valores_por_defecto']['tarifa_notarial_acto_sin_cuantia']);
    }

    public function testUnaVigenciaVaciaConservaLaDelDefecto(): void
    {
        $defaults = SimulatorSettings::defaultsProteccionesFamiliares();

        $bloque = SimulatorSettings::sanitizeProteccionesFamiliares(['vigencia_desde' => '']);

        self::assertSame($defaults['vigencia_desde'], $bloque['vigencia_desde']);
    }

    public function testLaVigenciaEnviadaSeGuarda(): void
    {
        $bloque = SimulatorSettings::sanitizeProteccionesFamiliares([
            'vigencia_desde' => '2027-01-01',
            'vigencia_hasta' => '2027-12-31',
        ]);

        self::assertSame('2027-01-01', $bloque['vigencia_desde']);
        self::assertSame('2027-12-31', $bloque['vigencia_hasta']);
    }

    /**
     * Quién paga el acto —vendedor o comprador— sale de un desplegable, pero
     * llega por POST. Un valor inventado dejaría el gasto sin asignar a nadie
     * en el reparto final.
     */
    public function testElResponsableSoloAceptaVendedorOComprador(): void
    {
        foreach (['vendedor', 'comprador'] as $responsable) {
            $bloque = SimulatorSettings::sanitizeProteccionesFamiliares([
                'afectacion_vivienda_familiar' => ['constitucion' => ['responsable_por_defecto' => $responsable]],
            ]);
            self::assertSame($responsable, $bloque['afectacion_vivienda_familiar']['constitucion']['responsable_por_defecto']);
        }

        $defaults = SimulatorSettings::defaultsProteccionesFamiliares();
        $bloque = SimulatorSettings::sanitizeProteccionesFamiliares([
            'afectacion_vivienda_familiar' => ['constitucion' => ['responsable_por_defecto' => 'el_notario']],
        ]);
        self::assertSame(
            $defaults['afectacion_vivienda_familiar']['constitucion']['responsable_por_defecto'],
            $bloque['afectacion_vivienda_familiar']['constitucion']['responsable_por_defecto']
        );
    }

    public function testLosInterruptoresDeCadaActoSeGuardanComoBooleanos(): void
    {
        $bloque = SimulatorSettings::sanitizeProteccionesFamiliares([
            'patrimonio_familia' => ['cancelacion' => ['activo' => '1', 'aplica_iva' => '0']],
        ]);

        self::assertTrue($bloque['patrimonio_familia']['cancelacion']['activo']);
        self::assertFalse($bloque['patrimonio_familia']['cancelacion']['aplica_iva']);
    }

    /**
     * El modo de cálculo del patrimonio de familia para VIS decide si el gasto
     * se cobra, se exime o se manda a revisar. Un valor desconocido tiene que
     * caer en el más conservador: pedir validación, no cobrar de más ni de menos.
     */
    public function testElModoDeCalculoDesconocidoPideValidacion(): void
    {
        foreach (['requiere_validacion', 'cobrado', 'exento', 'incluido'] as $modo) {
            $bloque = SimulatorSettings::sanitizeProteccionesFamiliares([
                'patrimonio_familia' => ['constitucion_vis' => ['modo_calculo' => $modo]],
            ]);
            self::assertSame($modo, $bloque['patrimonio_familia']['constitucion_vis']['modo_calculo'], $modo);
        }

        $bloque = SimulatorSettings::sanitizeProteccionesFamiliares([
            'patrimonio_familia' => ['constitucion_vis' => ['modo_calculo' => 'gratis_total']],
        ]);
        self::assertSame('requiere_validacion', $bloque['patrimonio_familia']['constitucion_vis']['modo_calculo']);
    }

    public function testUnBloqueVacioDevuelveLosValoresPorDefecto(): void
    {
        self::assertSame(
            SimulatorSettings::defaultsProteccionesFamiliares(),
            SimulatorSettings::sanitizeProteccionesFamiliares([])
        );
    }

    /** El saneado del formulario arrastra el bloque anidado aunque no venga. */
    public function testElBloqueAnidadoSeSaneaAunqueNoSeEnvie(): void
    {
        $guardado = SimulatorSettings::sanitize(['venta' => []]);

        self::assertSame(
            SimulatorSettings::defaultsProteccionesFamiliares(),
            $guardado['venta']['protecciones_familiares']
        );
    }

    // ── Resolución para el frontal ───────────────────────────────────────────

    public function testLaResolucionCompletaLoQueFalteConLosDefectos(): void
    {
        $resuelto = SimulatorSettings::resolveProteccionesFamiliares([
            'protecciones_familiares' => ['vigencia_desde' => '2027-01-01'],
        ]);

        self::assertSame('2027-01-01', $resuelto['vigencia_desde']);
        self::assertArrayHasKey('patrimonio_familia', $resuelto);
    }

    /** Una configuración corrupta no puede dejar el simulador sin tarifas. */
    public function testUnaConfiguracionCorruptaCaeALosDefectos(): void
    {
        $defaults = SimulatorSettings::defaultsProteccionesFamiliares();

        self::assertSame($defaults, SimulatorSettings::resolveProteccionesFamiliares([]));
        self::assertSame($defaults, SimulatorSettings::resolveProteccionesFamiliares([
            'protecciones_familiares' => 'esto no es un array',
        ]));
    }

    // ── Logo ─────────────────────────────────────────────────────────────────

    /** El logo aparece en el PDF que se le entrega al cliente. */
    public function testElLogoSaleDelTemaSiEstaConfigurado(): void
    {
        WpStubs::$themeMods['custom_logo'] = 42;
        WpStubs::$attachmentUrls[42] = 'https://example.test/logo.png';

        self::assertSame('https://example.test/logo.png', SimulatorSettings::resolveLogo());
    }

    public function testSinLogoDelTemaSeUsaElIconoDelSitio(): void
    {
        WpStubs::setOption('site_icon_url', 'https://example.test/icono-del-sitio.png');

        self::assertSame('https://example.test/icono-del-sitio.png', SimulatorSettings::resolveLogo());
    }

    /** Sin nada configurado, el icono del plugin: el PDF no puede salir sin logo. */
    public function testSinIconoDelSitioSeUsaElDelPlugin(): void
    {
        self::assertSame(HOMLITY_PLUGIN_URL . 'icono.png', SimulatorSettings::resolveLogo());
    }

    /** Un id de adjunto que ya no existe no puede dejar el logo en blanco. */
    public function testUnLogoBorradoCaeAlSiguienteRespaldo(): void
    {
        WpStubs::$themeMods['custom_logo'] = 999;
        WpStubs::$attachmentUrls[999] = '';
        WpStubs::setOption('site_icon_url', 'https://example.test/icono-del-sitio.png');

        self::assertSame('https://example.test/icono-del-sitio.png', SimulatorSettings::resolveLogo());
    }
}
