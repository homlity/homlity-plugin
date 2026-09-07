<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\DynamicTags;

use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lo común a las etiquetas del inmueble: el grupo del desplegable y el control
 * que permite fijar un inmueble concreto.
 */
trait ResolvesProperty
{
    public function get_group(): string
    {
        return 'homlity-real-estate';
    }

    /**
     * En la plantilla de ficha de Elementor no hay inmueble que consultar: sin
     * este control se maqueta a ciegas, con todos los datos en blanco.
     */
    protected function register_property_control(): void
    {
        $this->add_control('property_id', [
            'label' => __('Inmueble', 'homlity-real-estate'),
            'type' => Controls_Manager::NUMBER,
            'default' => '',
            'description' => __(
                'Vacío: toma el inmueble de la página o del bucle en el que esté. Pon un ID para fijarlo o para previsualizar en el editor.',
                'homlity-real-estate'
            ),
        ]);
    }

    protected function resolved_property_id(): int
    {
        return PropertyFields::resolvePropertyId($this->get_settings('property_id'));
    }
}
