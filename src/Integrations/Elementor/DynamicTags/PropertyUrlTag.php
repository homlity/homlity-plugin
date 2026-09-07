<?php

namespace Homlity\PluginInmobiliario\Integrations\Elementor\DynamicTags;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module as TagsModule;

if (!defined('ABSPATH')) {
    exit;
}

/** Enlaces del inmueble: ficha, WhatsApp, mapa, folleto, recorrido 360°… */
class PropertyUrlTag extends Data_Tag
{
    use ResolvesProperty;

    public function get_name(): string
    {
        return 'homlity-property-url';
    }

    public function get_title(): string
    {
        return __('Inmueble: enlace', 'homlity-real-estate');
    }

    public function get_categories(): array
    {
        return [TagsModule::URL_CATEGORY];
    }

    protected function register_controls(): void
    {
        $this->add_control('link', [
            'label' => __('Enlace', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'options' => PropertyFields::urlChoices(),
            'default' => 'permalink',
        ]);

        $this->register_property_control();
    }

    /** @param array<string,mixed> $options */
    public function get_value(array $options = []): string
    {
        return PropertyFields::url($this->resolved_property_id(), (string) $this->get_settings('link'));
    }
}
