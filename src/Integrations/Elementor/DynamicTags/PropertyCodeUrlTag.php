<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\DynamicTags;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if (!defined('ABSPATH')) {
    exit;
}

/** Builds an arbitrary URL by appending the current property's code. */
class PropertyCodeUrlTag extends Data_Tag
{
    use ResolvesProperty;

    public function get_name(): string
    {
        return 'homlity-property-code-url';
    }

    public function get_title(): string
    {
        return __('Inmueble: enlace + código', 'homlity-real-estate');
    }

    public function get_categories(): array
    {
        return [TagsModule::URL_CATEGORY];
    }

    protected function register_controls(): void
    {
        $this->add_control('base_url', [
            'label' => __('Enlace base', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'placeholder' => 'https://wa.me/573001234567?text=Hola%20-%20Código%20',
            'description' => __('El código se agrega al final. Para WhatsApp puedes dejar el texto codificado y terminarlo con un espacio (%20).', 'homlity-real-estate'),
        ]);
        $this->add_control('separator', [
            'label' => __('Separador antes del código', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'placeholder' => '?codigo=',
            'description' => __('Opcional. Ejemplos: ?codigo=, / o %20.', 'homlity-real-estate'),
        ]);
        $this->register_property_control();
    }

    /** @param array<string,mixed> $options */
    public function get_value(array $options = []): string
    {
        return PropertyFields::codeUrl(
            $this->resolved_property_id(),
            (string) $this->get_settings('base_url'),
            (string) $this->get_settings('separator')
        );
    }
}
