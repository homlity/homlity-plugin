<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Services;

use Homlity\PluginInmobiliario\Services\AgentProfileService;
use Homlity\PluginInmobiliario\Services\AvailableAgentsService;
use Homlity\PluginInmobiliario\Services\CapabilityService;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Quién sale en el widget «Asesores con inmuebles disponibles».
 *
 * Esto vivía copiado tres veces dentro de los widgets de Elementor, Divi y
 * WPBakery, clases que no se pueden instanciar sin su constructor delante: no
 * había forma de comprobar nada. La consulta sigue sin poder ejecutarse aquí
 * —es un JOIN contra las metas de los inmuebles—, así que sus filas se fijan y
 * lo que se comprueba es lo que se hace con ellas, que es donde están las
 * reglas: a quién se enseña, a quién no y en qué orden.
 */
final class AvailableAgentsServiceTest extends TestCase
{
    /** La subcadena por la que se reconoce la consulta de recuentos. */
    private const CONSULTA = '_property_agent_id';

    /**
     * @param array<int,int> $counts Id del asesor => inmuebles disponibles.
     */
    private function givenCounts(array $counts): void
    {
        $rows = [];
        foreach ($counts as $agentId => $total) {
            $rows[] = ['agent_id' => (string) $agentId, 'total' => (string) $total];
        }

        WpStubs::$sqlResults[self::CONSULTA] = $rows;
    }

    /** @param array<string,mixed> $meta */
    private function givenAgent(int $id, string $name, array $meta = []): void
    {
        WpStubs::setUser(
            $id,
            strtolower(str_replace(' ', '-', $name)),
            ['display_name' => $name, 'user_email' => $name . '@inmobiliaria.test'],
            [CapabilityService::ROLE_ASSESSOR],
            $meta
        );
    }

    /** @return array<int,string> Los nombres, en el orden en que se enseñan. */
    private function nombres(int $limit = 12): array
    {
        return array_map(
            static fn(array $row): string => (string) $row['user']->display_name,
            AvailableAgentsService::agents($limit)
        );
    }

    // ── El interruptor «Mostrar en la web» ────────────────────────────────

    /**
     * El interruptor llegó cuando los sitios ya tenían su plantilla montada.
     * Si la ausencia de valor significara «oculto», actualizar el plugin
     * dejaría la página de asesores en blanco.
     */
    public function testUnAsesorSinElInterruptorTocadoSeEnsena(): void
    {
        $this->givenCounts([7 => 4]);
        $this->givenAgent(7, 'Elena Giraldo');

        self::assertSame(['Elena Giraldo'], $this->nombres());
    }

    public function testUnAsesorApagadoNoSeEnsena(): void
    {
        $this->givenCounts([7 => 4, 8 => 2]);
        $this->givenAgent(7, 'Elena Giraldo', [AgentProfileService::PUBLIC_META => '0']);
        $this->givenAgent(8, 'Marta Ruiz');

        self::assertSame(['Marta Ruiz'], $this->nombres());
    }

    public function testUnAsesorEncendidoALaFuerzaSeEnsena(): void
    {
        $this->givenCounts([7 => 4]);
        $this->givenAgent(7, 'Elena Giraldo', [AgentProfileService::PUBLIC_META => '1']);

        self::assertSame(['Elena Giraldo'], $this->nombres());
    }

    /**
     * Apagar a un asesor no toca sus inmuebles: siguen publicados y a la venta,
     * y él sigue siendo su contacto en la ficha. Lo único que se apaga es su
     * aparición en el listado.
     */
    public function testApagarAUnAsesorNoLoDescalificaComoAsesor(): void
    {
        $this->givenAgent(7, 'Elena Giraldo', [AgentProfileService::PUBLIC_META => '0']);

        self::assertTrue(AgentProfileService::qualifiesAsAgent(WpStubs::$users[7]));
        self::assertFalse(AgentProfileService::isPubliclyListed(WpStubs::$users[7]));
    }

    /** Para quien necesite decidirlo por código —una fecha de baja, un CRM—. */
    public function testUnFiltroPuedeDecidirQuienSeEnsena(): void
    {
        $this->givenCounts([7 => 4, 8 => 2]);
        $this->givenAgent(7, 'Elena Giraldo');
        $this->givenAgent(8, 'Marta Ruiz');

        WpStubs::addFilter(
            'homlity_agent_is_publicly_listed',
            static fn(bool $listed, \WP_User $agent): bool => (int) $agent->ID !== 8
        );

        self::assertSame(['Elena Giraldo'], $this->nombres());
    }

    // ── Otras razones para no salir ───────────────────────────────────────

    /** Un usuario dado de baja por WordPress no se enseña. */
    public function testUnUsuarioDesactivadoNoSeEnsena(): void
    {
        $this->givenCounts([7 => 4, 8 => 2]);
        $this->givenAgent(7, 'Elena Giraldo');
        WpStubs::$users[7]->user_status = 1;
        $this->givenAgent(8, 'Marta Ruiz');

        self::assertSame(['Marta Ruiz'], $this->nombres());
    }

    /**
     * Un inmueble puede quedarse apuntando al id de un usuario borrado. Antes
     * de esto el recuento lo incluía y luego no había a quién enseñar.
     */
    public function testUnIdSinUsuarioDetrasNoRompeElListado(): void
    {
        $this->givenCounts([999 => 9, 8 => 2]);
        $this->givenAgent(8, 'Marta Ruiz');

        self::assertSame(['Marta Ruiz'], $this->nombres());
    }

    public function testSinAsesoresNoHayListado(): void
    {
        $this->givenCounts([]);

        self::assertSame([], AvailableAgentsService::agents(12));
    }

    /** Un recuento a cero o un id inválido no cuenta como asesor. */
    public function testLosRecuentosVaciosSeDescartan(): void
    {
        WpStubs::$sqlResults[self::CONSULTA] = [
            ['agent_id' => '0', 'total' => '5'],
            ['agent_id' => '8', 'total' => '0'],
        ];
        $this->givenAgent(8, 'Marta Ruiz');

        self::assertSame([], AvailableAgentsService::agents(12));
    }

    // ── Orden y cantidad ──────────────────────────────────────────────────

    public function testSeOrdenanDelQueMasInmueblesTieneAlQueMenos(): void
    {
        $this->givenCounts([7 => 2, 8 => 9, 9 => 5]);
        $this->givenAgent(7, 'Elena Giraldo');
        $this->givenAgent(8, 'Marta Ruiz');
        $this->givenAgent(9, 'Andrés Vélez');

        self::assertSame(['Marta Ruiz', 'Andrés Vélez', 'Elena Giraldo'], $this->nombres());
    }

    /**
     * get_users() no promete orden, así que dos asesores empatados se
     * intercambiaban de sitio entre una visita y la siguiente. El desempate
     * por nombre es lo que hace que el listado se quede quieto.
     */
    public function testLosEmpatesSeDesempatanPorNombre(): void
    {
        // Registrados a propósito en un orden que no es el alfabético: usort()
        // es estable, así que con los asesores ya ordenados la prueba pasaría
        // sin que el desempate existiera.
        $this->givenCounts([8 => 3, 9 => 3, 7 => 3]);
        $this->givenAgent(8, 'Marta Ruiz');
        $this->givenAgent(9, 'Andrés Vélez');
        $this->givenAgent(7, 'Elena Giraldo');

        self::assertSame(['Andrés Vélez', 'Elena Giraldo', 'Marta Ruiz'], $this->nombres());
    }

    public function testSeRespetaLaCantidadPedida(): void
    {
        $this->givenCounts([7 => 9, 8 => 5, 9 => 2]);
        $this->givenAgent(7, 'Elena Giraldo');
        $this->givenAgent(8, 'Marta Ruiz');
        $this->givenAgent(9, 'Andrés Vélez');

        self::assertSame(['Elena Giraldo', 'Marta Ruiz'], $this->nombres(2));
    }

    /**
     * La consulta no sabe quién está apagado —el interruptor es una meta del
     * usuario, no del inmueble—, así que pedirle justo los que se van a
     * enseñar dejaría el widget corto en cuanto hubiese uno apagado entre los
     * primeros. Se piden de más y se recorta después.
     */
    public function testUnAsesorApagadoNoDejaHuecoEnElListado(): void
    {
        $this->givenCounts([7 => 9, 8 => 5, 9 => 2]);
        $this->givenAgent(7, 'Elena Giraldo', [AgentProfileService::PUBLIC_META => '0']);
        $this->givenAgent(8, 'Marta Ruiz');
        $this->givenAgent(9, 'Andrés Vélez');

        self::assertSame(['Marta Ruiz', 'Andrés Vélez'], $this->nombres(2));
    }

    public function testSePidenMasFilasDeLasQueSeEnsenan(): void
    {
        $this->givenCounts([7 => 9]);
        $this->givenAgent(7, 'Elena Giraldo');

        AvailableAgentsService::agents(4);

        self::assertStringContainsString('LIMIT 12', $this->consultaEmitida());
    }

    /** Sin tope, un widget con el máximo puesto pediría 600 filas. */
    public function testElExcesoTieneTecho(): void
    {
        $this->givenCounts([7 => 9]);
        $this->givenAgent(7, 'Elena Giraldo');

        AvailableAgentsService::agents(200);

        self::assertStringContainsString('LIMIT 200', $this->consultaEmitida());
    }

    // ── La consulta ───────────────────────────────────────────────────────

    /**
     * El motor en memoria de las pruebas no ejecuta este JOIN, así que lo de
     * abajo mira la sentencia y no su resultado: comprueba que se sigue
     * pidiendo lo que el widget promete —inmuebles publicados, activos y
     * disponibles— y no que el filtrado funcione.
     */
    private function consultaEmitida(): string
    {
        global $wpdb;

        self::assertNotEmpty($wpdb->rawQueries, 'No se emitió ninguna consulta.');

        return (string) end($wpdb->rawQueries);
    }

    public function testSoloCuentaInmueblesPublicados(): void
    {
        $this->givenCounts([7 => 1]);
        $this->givenAgent(7, 'Elena Giraldo');

        AvailableAgentsService::agents(12);
        $sql = $this->consultaEmitida();

        self::assertStringContainsString("p.post_status = 'publish'", $sql);
        self::assertStringContainsString("p.post_type = 'property'", $sql);
    }

    /**
     * Un inmueble retirado o vendido sigue publicado en el sitio; si contara,
     * el número bajo el nombre del asesor no sería el de los que puede
     * enseñar.
     */
    public function testSoloCuentaInmueblesActivosYDisponibles(): void
    {
        $this->givenCounts([7 => 1]);
        $this->givenAgent(7, 'Elena Giraldo');

        AvailableAgentsService::agents(12);
        $sql = $this->consultaEmitida();

        self::assertStringContainsString("LOWER(pm_status.meta_value) = 'active'", $sql);
        self::assertStringContainsString("LOWER(pm_available.meta_value) IN ('1','true','yes','active')", $sql);
    }

    /** Un inmueble sin la meta puesta cuenta: la ausencia no es un «no». */
    public function testLaFaltaDeMetaNoDescartaElInmueble(): void
    {
        $this->givenCounts([7 => 1]);
        $this->givenAgent(7, 'Elena Giraldo');

        AvailableAgentsService::agents(12);
        $sql = $this->consultaEmitida();

        self::assertStringContainsString('pm_status.meta_id IS NULL', $sql);
        self::assertStringContainsString('pm_available.meta_id IS NULL', $sql);
    }

    /** El mismo inmueble no puede contar dos veces por tener metas repetidas. */
    public function testCadaInmuebleCuentaUnaSolaVez(): void
    {
        $this->givenCounts([7 => 1]);
        $this->givenAgent(7, 'Elena Giraldo');

        AvailableAgentsService::agents(12);

        self::assertStringContainsString('COUNT(DISTINCT p.ID)', $this->consultaEmitida());
    }
}
