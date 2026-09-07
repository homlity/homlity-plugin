<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\DynamicTags;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if (!defined('ABSPATH')) {
    exit;
}

/** Cualquier dato del inmueble: precio, área, ciudad, características… */
class PropertyTextTag extends Tag
{
    use ResolvesProperty;

    public function get_name(): string
    {
        return 'homlity-property-field';
    }

    public function get_title(): string
    {
        return __('Inmueble: dato', 'homlity-real-estate');
    }

    public function get_categories(): array
    {
        // También en la categoría numérica para que habitaciones, baños o el
        // precio se puedan enchufar a un contador, no sólo a un texto.
        return [TagsModule::TEXT_CATEGORY, TagsModule::NUMBER_CATEGORY];
    }

    protected function register_controls(): void
    {
        $this->add_control('field', [
            'label' => __('Dato', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'groups' => PropertyFields::textGroups(),
            'options' => PropertyFields::textChoices(),
            'default' => 'price',
        ]);

        $this->add_control('format', [
            'label' => __('Formato', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'options' => [
                'formatted' => __('Presentado (con moneda, m², Sí/No…)', 'homlity-real-estate'),
                PropertyFields::FORMAT_RAW => __('En bruto (el valor guardado)', 'homlity-real-estate'),
            ],
            'default' => 'formatted',
            'description' => __(
                'En bruto sirve para contadores, condiciones de visualización y cálculos: devuelve 350000000, no $ 350.000.000.',
                'homlity-real-estate'
            ),
        ]);

        $this->register_property_control();
    }

    protected function render(): void
    {
        echo esc_html(PropertyFields::text(
            $this->resolved_property_id(),
            (string) $this->get_settings('field'),
            (string) $this->get_settings('format')
        ));
    }
}
