<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Integrations;

use Homlity\PluginInmobiliario\Integrations\Shared\GroupControlType;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;

/**
 * Elementor llama a sus grupos de sombra box-shadow/text-shadow y lanza
 * "Group not found" con el nombre de la capa de compatibilidad.
 */
final class GroupControlTypeTest extends TestCase
{
    public function testLasCapasDeCompatibilidadConservanElNombreConGuionBajo(): void
    {
        self::assertSame('box_shadow', GroupControlType::resolve(new \stdClass(), 'box_shadow'));
        self::assertSame('border', GroupControlType::resolve(new \stdClass(), 'border'));
    }

    public function testLosTraitsCompartidosNoPasanNombresDeSombraLiterales(): void
    {
        foreach (glob(HOMLITY_PLUGIN_PATH . 'src/Integrations/Shared/*.php') ?: [] as $file) {
            self::assertDoesNotMatchRegularExpression(
                "/add_group_control\\(\\s*'(box_shadow|text_shadow|box-shadow|text-shadow)'/",
                (string) file_get_contents($file),
                sprintf('%s debe usar GroupControlType::resolve() para las sombras.', basename($file))
            );
        }
    }
}
