<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Tests\Unit\Listing;

use Homlity\PluginInmobiliario\Listing\ListingConfig;
use Homlity\PluginInmobiliario\Listing\ListingRenderer;
use Homlity\PluginInmobiliario\Services\CardTitleResolver;
use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;
use Homlity\PluginInmobiliario\Tests\Support\TestCase;
use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

final class CardTitleTemplateTest extends TestCase
{
    public function testRepeatedTitlesAreDistinguishedInBothCardTemplates(): void
    {
        foreach ([501 => 'A-101', 502 => 'A-102'] as $id => $code) {
            WpStubs::setPost($id, 'Apartamento en Chicó', 'https://example.test/' . $id, ['_property_code' => $code]);
            WpStubs::$postTerms[$id][PropertyTaxonomies::TAXONOMY_OPERATION] = [
                WpStubs::setTerm(77, PropertyTaxonomies::TAXONOMY_OPERATION, 'venta', 'Venta'),
            ];
        }
        foreach (['default', 'bootstrap'] as $template) {
            foreach (['default', 'cover_overlay'] as $preset) {
                $query = new \WP_Query();
                $query->posts = [501, 502];
                $query->post_count = 2;
                $html = (new ListingRenderer())->renderCards($query, ListingConfig::fromArray([
                    'template' => $template,
                    'card_visual_preset' => $preset,
                    'card_title_operation' => true,
                    'card_show_whatsapp' => false,
                ]));
                foreach (['A-101', 'A-102'] as $code) {
                    self::assertStringContainsString('Apartamento en Chicó · Venta · Código ' . $code, $html);
                }
            }
        }
    }

    public function testTitleOptionsSurviveConfigurationFactories(): void
    {
        foreach ([
            ListingConfig::fromBuilderSettings(['card_title_code' => '', 'card_title_operation' => 'yes']),
            ListingConfig::fromAtts(['card_title_code' => '0', 'card_title_operation' => '1']),
            ListingConfig::fromArray(['card_title_code' => false, 'card_title_operation' => true]),
        ] as $config) {
            self::assertFalse($config->cardOptions()['title_code']);
            self::assertTrue($config->cardOptions()['title_operation']);
        }
        self::assertTrue(ListingConfig::fromBuilderSettings([])->cardOptions()['title_code']);
    }

    public function testOptionalPartsAndMissingValues(): void
    {
        WpStubs::setPost(501, 'Apartamento en Venta', 'https://example.test/501', []);
        self::assertSame('Apartamento en Venta', CardTitleResolver::forDisplay(501, [], '123', ['Venta']));
        self::assertSame('Apartamento en Venta · Arriendo', CardTitleResolver::forDisplay(501, ['title_code' => true, 'title_operation' => true], '', ['Venta', 'Arriendo', 'Arriendo']));
        self::assertSame('Apartamento en Venta', CardTitleResolver::forDisplay(501, ['title_operation' => true], '', new \WP_Error('error')));
    }
}
